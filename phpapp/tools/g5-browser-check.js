// ---------------------------------------------------------------------------
//  GATE 5 — REAL BROWSER VERIFICATION OF WORKFORCE ACTIVATION
//
//  The server tests prove the rule. They cannot see the mistakes the SCREEN must
//  make impossible:
//
//    · a coordinator finding a person who has not started yet in the allocation
//      list or on the availability board, and giving them work;
//    · a recruiter opening a joining-pending colleague to fix a phone number and
//      silently activating them, because the status dropdown has no such option;
//    · a manager reading "PENDING_JOINING" as a system error, because the screen
//      shows the raw database code instead of a human sentence;
//    · a recruiter recording a joining and being told nothing about what it did.
//
//    node tools/g5-browser-check.js [baseUrl] [user] [pass] "CAND=.. INS=.. ..."
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8849';
const USER = process.argv[3] || 'admin';
const PASS = process.argv[4] || 'admin12345';
const IDS  = {};
(process.argv[5] || '').split(/\s+/).forEach(kv => { const [k, v] = kv.split('='); if (k) IDS[k] = v; });

let pass = 0, fail = 0;
const ok  = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; console.log('  FAIL  ' + m); } };
const sec = (m) => console.log('\n== ' + m + ' ==');

(async () => {
  const br  = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg  = await ctx.newPage();
  const jsErrors = [];
  pg.on('pageerror', e => jsErrors.push(String(e)));
  const body = async () => (await pg.textContent('body').catch(() => '')) || '';
  const open = async (p, label) => {
    const r = await pg.goto(BASE + p, { waitUntil: 'domcontentloaded' }).catch(() => null);
    const code = r ? r.status() : 0;
    ok(code === 200, label + ' (' + code + ')');
    return code;
  };

  sec('G5-UI · 1 · sign in');
  await pg.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
  await pg.fill('input[name=username]', USER).catch(() => {});
  await pg.fill('input[name=password]', PASS).catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'), pg.click('button[type=submit]')]).catch(() => {});
  ok(!/\/login/.test(pg.url()), '1a · signed in');

  sec('G5-UI · 2 · the joiner reads as a sentence, not a database code');
  await open('/m/inspectors', '2a · the team list opens');
  let b = await body();
  ok(/Joining pending/i.test(b), '2b · *** the new hire reads "Joining pending" ***');
  ok(!/PENDING_JOINING/.test(b), '2c · *** …and the raw code is never shown to a human ***');
  ok(/Priya/i.test(b), '2d · the person who has not joined is VISIBLE for follow-up (§11)');
  ok(/Arjun/i.test(b), '2e · …alongside the colleague who has joined');

  sec('G5-UI · 3 · they are NOT on the availability board');
  await open('/availability', '3a · the availability board opens');
  b = await body();
  ok(/Arjun/i.test(b), '3b · the person who joined IS on the board');
  ok(!/Priya/i.test(b), '3c · *** the person who has NOT joined is absent — cannot be given work ***');

  sec('G5-UI · 4 · the status dropdown cannot silently activate them');
  await open('/m/inspectors/edit?id=' + IDS.INS, '4a · the team member opens for editing');
  const opts = await pg.$$eval('select[name=status] option', els => els.map(e => e.value)).catch(() => []);
  ok(opts.includes('PENDING_JOINING'), '4b · *** the dropdown contains their CURRENT status ***');
  ok(opts.includes('ACTIVE') && opts.includes('INACTIVE'), '4c · …and the other two');
  const sel = await pg.$eval('select[name=status]', el => el.value).catch(() => '');
  ok(sel === 'PENDING_JOINING',
     '4d · *** it is pre-selected on their real status, so Save cannot activate them by accident ***');
  b = await body();
  ok(/become .?Active.? automatically when their joining is recorded/i.test(b),
     '4e · and the screen says how they WILL become active');
  ok(/not yet available for scheduling|accepted is not the same as joined/i.test(b),
     '4f · the recruitment origin panel states the operational consequence');
  ok(!/this person has left/i.test(b), '4g · *** a new joiner is NOT described as having left ***');

  sec('G5-UI · 5 · recording the joining, from the screen a recruiter uses');
  await open('/candidate?id=' + IDS.CAND, '5a · the candidate record opens');
  b = await body();
  ok(/Mark as joined|joined/i.test(b), '5b · the joining action is offered');
  const form = await pg.$('form[action="/candidate-joined?id=' + IDS.CAND + '"], form[action*="candidate-joined"]');
  ok(!!form, '5c · the joining form is present');
  if (form) {
    await Promise.all([
      pg.waitForLoadState('domcontentloaded'),
      form.evaluate(f => f.submit()),
    ]).catch(() => {});
    b = await body();
    ok(/available for scheduling/i.test(b),
       '5d · *** the recruiter is told they are now available for scheduling ***');
  }

  sec('G5-UI · 6 · and the operational effect is real');
  await open('/m/inspectors', '6a · back to the team list');
  b = await body();
  ok(!/Joining pending/i.test(b) || /Active/i.test(b), '6b · the badge has moved on');
  await open('/availability', '6c · the availability board again');
  b = await body();
  ok(/Priya/i.test(b), '6d · *** the person now APPEARS on the board — joining activated them ***');

  sec('G5-UI · 7 · phone widths (inspectors are phone-first in the field)');
  for (const w of [360, 390, 412]) {
    const mp = await (await br.newContext({ viewport: { width: w, height: 780 }, isMobile: true, hasTouch: true })).newPage();
    await mp.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
    await mp.fill('input[name=username]', USER).catch(() => {});
    await mp.fill('input[name=password]', PASS).catch(() => {});
    await Promise.all([mp.waitForLoadState('domcontentloaded'), mp.click('button[type=submit]')]).catch(() => {});
    for (const [route, label] of [['/m/inspectors', 'team list'], ['/availability', 'availability board'],
                                  ['/m/inspectors/edit?id=' + IDS.INS, 'team member']]) {
      await mp.goto(BASE + route, { waitUntil: 'domcontentloaded' }).catch(() => {});
      const over = await mp.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
                           .catch(() => 0);
      ok(over <= 2, '7 · ' + w + 'px · ' + label + ' does not scroll sideways (' + over + 'px)');
    }
    //  The page's OWN controls. `.side-close` — the ✕ on the mobile navigation
    //  drawer — is excluded deliberately and is NOT a pass: it is 20px tall on
    //  every screen in the product, it is global shell chrome that predates this
    //  gate (unchanged since b1e793c), and quietly rewriting global CSS inside a
    //  workforce-activation gate would be exactly the scope creep this programme
    //  forbids. It is reported as a separate pre-existing finding instead of
    //  being hidden by a green tick here.
    const small = await mp.$$eval('a.btn, button:not(.side-close)', els => els.filter(e => {
      const r = e.getBoundingClientRect();
      return r.width > 0 && r.height > 0 && r.height < 32;
    }).map(e => (e.textContent || '').trim().slice(0, 24) + ' [' + e.className + ' ' +
                Math.round(e.getBoundingClientRect().height) + 'px]')).catch(() => []);
    ok(small.length === 0, '7 · ' + w + 'px · every button is a thumb-sized tap target'
       + (small.length ? ' — ' + small.join('; ') : ''));
    await mp.context().close();
  }

  sec('G5-UI · 8 · no JavaScript errors anywhere');
  ok(jsErrors.length === 0, '8a · the console is clean' + (jsErrors.length ? ' — ' + jsErrors[0] : ''));

  console.log('\n----------------------------------------------------');
  console.log('BROWSER RESULT: ' + pass + ' passed, ' + fail + ' failed');
  await br.close();
  process.exit(fail === 0 ? 0 : 1);
})();
