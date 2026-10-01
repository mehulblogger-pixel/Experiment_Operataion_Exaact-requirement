// ---------------------------------------------------------------------------
//  GATE 6B — R1-UI AND R2 IN A REAL BROWSER
//
//  Both locked decisions end on a screen, and view() is defined in index.php, so
//  no test in the PHP harness can render either one. These assertions drive the
//  REAL routes over HTTP and read what an administrator would actually see and
//  click — which is the only honest way to prove a screen.
//
//    node tools/g6b-browser-check.js [baseUrl] [user] [pass] "TAG=.. ACTIVE=.. …"
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8852';
const USER = process.argv[3] || 'admin';
const PASS = process.argv[4] || 'admin12345';
const IDS  = {};
(process.argv[5] || '').split(/\s+/).forEach(kv => { const [k, v] = kv.split('='); if (k) IDS[k] = v; });
const TAG = IDS.TAG || '';
const N_ACTIVE = TAG + 'Activo', N_JOIN = TAG + 'Joiner', N_LEFT = TAG + 'Leaver';

let pass = 0, fail = 0;
const ok  = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; console.log('  FAIL  ' + m); } };
const sec = (m) => console.log('\n== ' + m + ' ==');

(async () => {
  const br  = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const login = async (pg, u, p) => {
    await pg.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
    await pg.fill('input[name=username]', u).catch(() => {});
    await pg.fill('input[name=password]', p).catch(() => {});
    await Promise.all([pg.waitForLoadState('domcontentloaded'), pg.click('button[type=submit]')]).catch(() => {});
    return !/\/login/.test(pg.url());
  };
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg  = await ctx.newPage();
  const jsErrors = []; pg.on('pageerror', e => jsErrors.push(String(e)));
  const body = async () => (await pg.textContent('body').catch(() => '')) || '';
  const open = async (p, label) => {
    const r = await pg.goto(BASE + p, { waitUntil: 'domcontentloaded' }).catch(() => null);
    const code = r ? r.status() : 0;
    ok(code === 200, label + ' (' + code + ')');
    return code;
  };

  ok(await login(pg, USER, PASS), 'G6B-0 · signed in as an administrator');

  // =========================================================================
  sec('G6B R1-UI · CAN AN ADMINISTRATOR SEE AND CHANGE THE TRIGGER AT ALL?');
  // =========================================================================
  await open('/recruit-approvals', 'R1-UI-1 · the approval rules screen opens');
  let b = await body();
  //  The screen must still do its old job. If the panel broke the page, the Gate 4
  //  content would be gone and every assertion below would be about a broken page.
  ok(/Self-approval/i.test(b), 'R1-UI-2 · the Gate 4 self-approval panel is still there (the screen is not broken)');
  ok(/Rules \(/.test(b),       'R1-UI-3 · …and so is the rules list');

  ok(/re-checked|Review Required/i.test(b),
     'R1-UI-4 · *** the Review Required configuration is ON THE SCREEN, not database-only ***');
  const box = await pg.$('input[name="trigger_redefined"]');
  ok(!!box, 'R1-UI-5 · *** there is a control an administrator can actually operate ***');
  ok(!!(await pg.$('input[name="do"][value="review_triggers"]')),
     'R1-UI-6 · it posts to the handler that writes the setting');
  ok(await pg.isChecked('input[name="trigger_redefined"]').catch(() => false),
     'R1-UI-7 · *** it is ON by default — an existing organisation keeps the behaviour it had ***');
  ok(/Redefined: ON/i.test(b), 'R1-UI-8 · …and the state is stated in words, not only as a tick');

  //  WHAT CANNOT BE SWITCHED OFF must be visible AND must have no control.
  ok(/stricter always raises a review/i.test(b),
     'R1-UI-9 · *** the screen SAYS a stricter requirement always raises a review ***');
  //  \s+ not a space: the sentence wraps across lines in the source, and textContent
  //  keeps the newline. The screen does say it; the first version of this assertion
  //  was simply wrong about the whitespace.
  ok(/not\s+configurable/i.test(b), 'R1-UI-10 · …and says plainly that it cannot be changed');
  ok(!(await pg.$('input[name="trigger_stricter"]')),
     'R1-UI-11 · *** and offers NO control for it: the locked rule is not presented as a choice ***');
  ok(/relaxed/i.test(b) && /reopens nobody|never raises/i.test(b),
     'R1-UI-12 · and explains that relaxing a requirement reopens nobody');

  // =========================================================================
  sec('G6B R1-UI · DOES SWITCHING IT OFF ACTUALLY STICK?');
  // =========================================================================
  await pg.uncheck('input[name="trigger_redefined"]').catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'),
                     pg.click('form:has(input[name="do"][value="review_triggers"]) button')]).catch(() => {});
  b = await body();
  ok(/saved/i.test(b), 'R1-UI-13 · saving reports success');
  //  RELOAD FROM THE SERVER — not the page we were handed. A value that only looks
  //  saved until the next visit is the defect this assertion exists to catch.
  await open('/recruit-approvals', 'R1-UI-14 · the screen reopens');
  b = await body();
  ok((await pg.isChecked('input[name="trigger_redefined"]').catch(() => true)) === false,
     'R1-UI-15 · *** after a full reload it is still OFF — the change persisted ***');
  ok(/Redefined: OFF/i.test(b), 'R1-UI-16 · …and the screen says OFF');
  //  And the mandatory one is untouched by that.
  ok(/stricter always raises a review/i.test(b),
     'R1-UI-17 · *** switching the optional trigger off left the mandatory one in place ***');

  //  BACK ON AGAIN, because a switch that only goes one way is not a switch.
  await pg.check('input[name="trigger_redefined"]').catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'),
                     pg.click('form:has(input[name="do"][value="review_triggers"]) button')]).catch(() => {});
  await open('/recruit-approvals', 'R1-UI-18 · the screen reopens again');
  ok(await pg.isChecked('input[name="trigger_redefined"]').catch(() => false),
     'R1-UI-19 · *** it is ON again — the switch works both ways ***');

  // =========================================================================
  sec('G6B R1-UI · WHO IS ALLOWED ONTO THE SCREEN');
  // =========================================================================
  //  An ordinary operational user must not reach the configuration at all. The
  //  route's own gate is reused, so this proves the reuse rather than a new check.
  if (IDS.PLAINUSER) {
    const ctx2 = await br.newContext({ viewport: { width: 1280, height: 900 } });
    const pg2 = await ctx2.newPage();
    const inOk = await login(pg2, IDS.PLAINUSER, IDS.PLAINPASS || 'plain12345');
    ok(inOk, 'R1-UI-20 · an ordinary coordinator can sign in');
    const r = await pg2.goto(BASE + '/recruit-approvals', { waitUntil: 'domcontentloaded' }).catch(() => null);
    const code = r ? r.status() : 0;
    const b2 = (await pg2.textContent('body').catch(() => '')) || '';
    const refused = code === 403 || code === 302 || /not allowed|only an administrator|no permission|sign in/i.test(b2);
    ok(refused, 'R1-UI-21 · *** …and is refused the approval configuration (' + code + ') ***');
    ok(!(await pg2.$('input[name="trigger_redefined"]')),
       'R1-UI-22 · *** and is never shown the trigger control ***');
    await ctx2.close();
  } else {
    ok(false, 'R1-UI-20 · the seed did not provide an ordinary user — the permission case could not be proved');
  }

  // =========================================================================
  sec('G6B R2 · THE UTILISATION BREAKDOWN ON SCREEN');
  // =========================================================================
  await open('/reports', 'R2-UI-1 · the reports screen opens');
  b = await body();
  ok(/Utilization|Utilisation/i.test(b), 'R2-UI-2 · the utilisation panel is on the page');

  //  Read the utilisation TABLE specifically. The person's name also appears in
  //  this screen's own filter dropdown, so asserting on the whole page body would
  //  pass for the wrong reason — which is exactly the trap D4-UI-7 fell into.
  const utilNames = await pg.$$eval('h3:has-text("Utilization") + div table tr td:first-child',
                                    els => els.map(e => (e.textContent || '').trim()))
                      .catch(() => []);
  const utilText = utilNames.join('|');
  ok(utilNames.length >= 1,
     'R2-UI-3 · the breakdown has rows (' + utilNames.length + ') — without this nothing below means anything');
  ok(utilText.includes(N_ACTIVE),
     'R2-UI-4 · an ACTIVE person IS in the breakdown — the report still works');
  ok(!utilText.includes(N_JOIN),
     'R2-UI-5 · *** the HIRED-BUT-NOT-JOINED person is NOT in the breakdown ***');
  ok(utilText.includes(N_LEFT),
     'R2-UI-6 · *** a leaver IS still listed — their days were delivered and still count ***');

  //  DISCOVERABILITY. Excluded from a sum is not hidden from the business: the
  //  same screen's person filter must still be able to name them.
  const filterNames = await pg.$$eval('select[name="insp"] option', els => els.map(e => (e.textContent || '').trim()))
                        .catch(() => []);
  ok(filterNames.length > 1, 'R2-UI-7 · the person filter is populated');
  ok(filterNames.some(n => n.includes(N_JOIN)),
     'R2-UI-8 · *** the not-yet-joined person is STILL selectable in the filter ***');
  ok(filterNames.some(n => n.includes(N_ACTIVE)), 'R2-UI-9 · …and so is the active one');

  //  The breakdown must not be the only place they survive: the team register is
  //  where they belong, and Gate 6 D3/D4 put them there on purpose.
  await open('/m/inspectors?status=PENDING_JOINING', 'R2-UI-10 · the team register, filtered to joiners, opens');
  b = await body();
  ok(b.includes(N_JOIN), 'R2-UI-11 · *** they are plainly visible there, as somebody hired and awaited ***');
  ok(!b.includes(N_LEFT), 'R2-UI-12 · …and the filter is really filtering');

  // =========================================================================
  sec('G6B · THE SCREEN ON A PHONE');
  // =========================================================================
  for (const w of [360, 390, 412]) {
    const mctx = await br.newContext({ viewport: { width: w, height: 760 }, isMobile: true, hasTouch: true });
    const mp = await mctx.newPage();
    await login(mp, USER, PASS);
    await mp.goto(BASE + '/recruit-approvals', { waitUntil: 'domcontentloaded' }).catch(() => {});
    const cb = await mp.$('input[name="trigger_redefined"]');
    ok(!!cb, 'R1-UI-M · ' + w + 'px · the trigger control is present on a phone');
    if (cb) {
      const m = await cb.evaluate(el => {
        const r = el.getBoundingClientRect(); const cs = getComputedStyle(el);
        return { left: r.left, right: r.right, disp: cs.display, vis: cs.visibility };
      }).catch(() => null);
      ok(m && m.disp !== 'none' && m.vis !== 'hidden', 'R1-UI-M · ' + w + 'px · …and visible');
      ok(m && m.left >= 0 && m.right <= w + 1,
         'R1-UI-M · ' + w + 'px · not clipped off-screen [left=' + Math.round(m ? m.left : -1)
         + ' right=' + Math.round(m ? m.right : -1) + ' vw=' + w + ']');
    }
    //  No horizontal page scroll — the blueprint's rule for every screen.
    const over = await mp.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
                   .catch(() => 0);
    ok(over <= 1, 'R1-UI-M · ' + w + 'px · no sideways scrolling (' + over + 'px over)');

    //  AND THE LAYOUT ACTUALLY STACKS. "No sideways scroll" is not enough on its
    //  own: a 280px rail beside a content column that has been squeezed to a
    //  60px sliver also fits the viewport, and is unusable. So this asserts the
    //  thing the media query is responsible for — one column on a phone, with a
    //  content area wide enough to read. Without it, removing the media query
    //  changed nothing any assertion could see.
    const lay = await mp.evaluate(() => {
      const g = document.querySelector('.ar-split');
      if (!g) return null;
      const tracks = getComputedStyle(g).gridTemplateColumns.trim().split(/\s+/).length;
      const kids = [...g.children].map(c => Math.round(c.getBoundingClientRect().width));
      return { tracks: tracks, widest: Math.max.apply(null, kids), kids: kids.length };
    }).catch(() => null);
    ok(!!lay, 'R1-UI-M · ' + w + 'px · the rules layout is on the page');
    if (lay) {
      ok(lay.tracks === 1,
         'R1-UI-M · ' + w + 'px · *** the two-column rules layout STACKS to one column ('
         + lay.tracks + ' track(s)) ***');
      ok(lay.widest >= 240,
         'R1-UI-M · ' + w + 'px · …so the content column is usably wide (' + lay.widest + 'px)');
    }
    //  And it is still operable, not merely visible.
    await mp.check('input[name="trigger_redefined"]').catch(() => {});
    ok(await mp.isChecked('input[name="trigger_redefined"]').catch(() => false),
       'R1-UI-M · ' + w + 'px · *** the control can actually be operated by touch ***');
    await mctx.close();
  }

  sec('G6B · no JavaScript errors');
  ok(jsErrors.length === 0, 'G6B-JS · the console is clean' + (jsErrors.length ? ' — ' + jsErrors[0] : ''));

  console.log('\n----------------------------------------------------');
  console.log('BROWSER RESULT: ' + pass + ' passed, ' + fail + ' failed');
  await br.close();
  process.exit(fail === 0 ? 0 : 1);
})();
