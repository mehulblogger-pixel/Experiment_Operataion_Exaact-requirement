// ---------------------------------------------------------------------------
//  GATE 6 — D2–D5 IN A REAL BROWSER
//
//  The server battery proves the qualification rules. It cannot prove the SCREEN,
//  because view() is defined in index.php and is unreachable from the test
//  harness. These assertions therefore drive the REAL routes over HTTP — the
//  realest path available — and read what a user would actually see.
//
//    node tools/g6-browser-check.js [baseUrl] [user] [pass] "TAG=.. ACTIVE=.. …"
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8851';
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
  const login = async (pg) => {
    await pg.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
    await pg.fill('input[name=username]', USER).catch(() => {});
    await pg.fill('input[name=password]', PASS).catch(() => {});
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

  ok(await login(pg), 'G6-0 · signed in');

  // -------------------------------------------------------------------------
  sec('G6 D2 · THE AVAILABILITY BOARD EXPLAINS THE ABSENCE');
  // -------------------------------------------------------------------------
  await open('/availability', 'D2-UI-1 · the availability board opens');
  let b = await body();
  ok(b.includes(N_ACTIVE), 'D2-UI-2 · the ACTIVE person is on the board (so the board is working)');
  ok(!b.includes(N_JOIN),  'D2-UI-3 · *** the joining-pending person is NOT on the board ***');
  ok(!b.includes(N_LEFT),  'D2-UI-4 · the leaver is not on the board');
  ok(/not yet joined/i.test(b),
     'D2-UI-5 · *** the board SAYS somebody is hired but not yet joined — the absence is explained ***');
  ok(/not available for\s+scheduling|not available for scheduling/i.test(b),
     'D2-UI-6 · …and says what that means operationally');
  //  The count must match the seeded reality, not merely be present.
  const expectPend = String(IDS.PENDCOUNT || '1');
  const pendShown = (b.match(/(\d+)\s+(?:person has|people have)\s+been hired/i) || [])[1];
  ok(pendShown === expectPend,
     'D2-UI-7 · the count is the real one (' + pendShown + ' shown, ' + expectPend + ' seeded)');
  const link = await pg.$('a[href*="status=PENDING_JOINING"]');
  ok(!!link, 'D2-UI-8 · it links to the team register filtered to the people concerned');

  // -------------------------------------------------------------------------
  sec('G6 D3 · THE TEAM REGISTER STATUS FILTER');
  // -------------------------------------------------------------------------
  await open('/m/inspectors', 'D3-UI-1 · the team register opens');
  b = await body();
  ok(b.includes(N_ACTIVE) && b.includes(N_JOIN) && b.includes(N_LEFT),
     'D3-UI-2 · *** the DEFAULT view still shows everyone, including the joiner and the leaver (§11) ***');
  const sel = await pg.$('select[name="status"]');
  ok(!!sel, 'D3-UI-3 · a status filter exists');
  const opts = await pg.$$eval('select[name="status"] option', els => els.map(e => e.value)).catch(() => []);
  ok(opts.includes('') && opts.includes('ACTIVE') && opts.includes('PENDING_JOINING') && opts.includes('INACTIVE'),
     'D3-UI-4 · it offers Everyone plus each status in the vocabulary');

  await open('/m/inspectors?status=PENDING_JOINING', 'D3-UI-5 · filtering to Joining pending');
  b = await body();
  ok(b.includes(N_JOIN),   'D3-UI-6 · *** HR can now answer "who have we hired that has not started?" ***');
  ok(!b.includes(N_ACTIVE), 'D3-UI-7 · …and the active person is excluded');
  ok(!b.includes(N_LEFT),   'D3-UI-8 · …and the leaver is excluded');

  await open('/m/inspectors?status=ACTIVE', 'D3-UI-9 · filtering to Active');
  b = await body();
  ok(b.includes(N_ACTIVE) && !b.includes(N_JOIN) && !b.includes(N_LEFT),
     'D3-UI-10 · Active lists only the active person');

  await open('/m/inspectors?status=INACTIVE', 'D3-UI-11 · filtering to Inactive');
  b = await body();
  ok(b.includes(N_LEFT) && !b.includes(N_ACTIVE) && !b.includes(N_JOIN),
     'D3-UI-12 · Inactive lists only the leaver');

  //  A filter must never be a back door into operational qualification.
  await open('/availability', 'D3-UI-13 · back to the availability board');
  b = await body();
  ok(!b.includes(N_JOIN),
     'D3-UI-14 · *** after filtering the register, the joiner is STILL not an available resource ***');

  // -------------------------------------------------------------------------
  sec('G6 D4 · THE HEADLINE COUNT SEPARATES TEAM FROM PIPELINE');
  // -------------------------------------------------------------------------
  //  Read the HEADLINE only. The first version of these assertions read the whole
  //  page and failed, because the D3 filter dropdown also contains the words
  //  "Joining pending" as an option label — a page-wide match proves nothing
  //  about what the headline claims.
  const headline = async () => (await pg.textContent('.master-head .sub').catch(() => '')) || '';
  await open('/m/inspectors', 'D4-UI-1 · the team register');
  let h = await headline();
  console.log('        headline: "' + h.trim() + '"');
  ok(/on the team/i.test(h), 'D4-UI-2 · the headline counts people "on the team"');
  ok(/joining pending/i.test(h),
     'D4-UI-3 · *** and names the joining-pending separately rather than counting them as workforce ***');
  const m = h.match(/(\d+)\s+on the team\s*·\s*(\d+)\s+joining pending/i);
  ok(!!m, 'D4-UI-4 · both numbers appear together in the headline');
  if (m) {
    //  Case 2 of the brief: some members are joining pending.
    ok(parseInt(m[2], 10) >= 1, 'D4-UI-5 · the joining-pending figure is at least the one seeded');
    ok(parseInt(m[1], 10) >= 1, 'D4-UI-5b · and the on-the-team figure counts the active person');
  }
  //  Case 1: a list with no joining-pending rows must not claim any.
  await open('/m/inspectors?status=ACTIVE', 'D4-UI-6 · a list containing no joiners');
  h = await headline();
  console.log('        headline (Active filter): "' + h.trim() + '"');
  ok(/on the team/i.test(h) && !/joining pending/i.test(h),
     'D4-UI-7 · *** …the headline says nothing about joining pending, rather than printing a stale number ***');

  // -------------------------------------------------------------------------
  sec('G6 D5 · THE DRAWER CLOSE CONTROL IS A REAL TOUCH TARGET');
  // -------------------------------------------------------------------------
  //  Desktop first: it must stay hidden, as it always was.
  await open('/m/inspectors', 'D5-UI-1 · desktop view');
  const deskVisible = await pg.isVisible('.side-close').catch(() => false);
  ok(!deskVisible, 'D5-UI-2 · on desktop the close control stays hidden (unchanged behaviour)');

  for (const w of [360, 390, 412]) {
    const mctx = await br.newContext({ viewport: { width: w, height: 780 }, isMobile: true, hasTouch: true });
    const mp = await mctx.newPage();
    await login(mp);
    await mp.goto(BASE + '/m/inspectors', { waitUntil: 'domcontentloaded' }).catch(() => {});
    //  Open the drawer the way a person does.
    await mp.click('.nav-toggle').catch(() => {});
    //  The drawer SLIDES in (.side has transform:translateX(-100%) with a .2s
    //  transition; .side.open sets transform:none). Measuring mid-slide reads a
    //  box that is still partly off-screen.
    //
    //  The first version of this wait compared successive positions and could
    //  declare "stable" BEFORE the transition had begun — the position had simply
    //  not changed yet. That is what produced a clipped reading at 360px and
    //  412px but not 390px: pure timing, not a layout fault.
    //
    //  The deterministic signal is the drawer's own computed transform reaching
    //  identity, which only happens when it is fully open.
    await mp.waitForFunction(() => {
      const side = document.querySelector('.side');
      if (!side || !side.classList.contains('open')) return false;
      const t = getComputedStyle(side).transform;
      return t === 'none' || t === 'matrix(1, 0, 0, 1, 0, 0)';
    }, { timeout: 5000 }).catch(() => {});
    const opened = await mp.$eval('#side', el => el.classList.contains('open')).catch(() => false);
    ok(opened, 'D5-UI · ' + w + 'px · the menu opens');
    const box = await mp.$eval('.side-close', el => {
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return { w: r.width, h: r.height, disp: cs.display, vis: cs.visibility,
               right: r.right, bottom: r.bottom, left: r.left, top: r.top };
    }).catch(() => null);
    ok(!!box, 'D5-UI · ' + w + 'px · the close control is present');
    if (box) {
      ok(box.w >= 44, 'D5-UI · ' + w + 'px · width is at least 44px (' + Math.round(box.w) + ')');
      ok(box.h >= 44, 'D5-UI · ' + w + 'px · height is at least 44px (' + Math.round(box.h) + ')');
      ok(box.disp !== 'none' && box.vis !== 'hidden', 'D5-UI · ' + w + 'px · it is visible');
      ok(box.left >= 0 && box.top >= 0 && box.right <= w + 1,
         'D5-UI · ' + w + 'px · it is not clipped off-screen'
         + ' [left=' + Math.round(box.left) + ' right=' + Math.round(box.right)
         + ' top=' + Math.round(box.top) + ' vw=' + w + ']');
    }
    //  …and it actually closes the drawer.
    await mp.click('.side-close').catch(() => {});
    const closed = await mp.$eval('#side', el => !el.classList.contains('open')).catch(() => false);
    const scrim  = await mp.$eval('#scrim', el => !el.classList.contains('on')).catch(() => true);
    ok(closed, 'D5-UI · ' + w + 'px · *** pressing it CLOSES the menu ***');
    ok(scrim,  'D5-UI · ' + w + 'px · …and clears the backdrop');
    await mctx.close();
  }

  sec('G6 · no JavaScript errors');
  ok(jsErrors.length === 0, 'G6-JS · the console is clean' + (jsErrors.length ? ' — ' + jsErrors[0] : ''));

  console.log('\n----------------------------------------------------');
  console.log('BROWSER RESULT: ' + pass + ' passed, ' + fail + ' failed');
  await br.close();
  process.exit(fail === 0 ? 0 : 1);
})();
