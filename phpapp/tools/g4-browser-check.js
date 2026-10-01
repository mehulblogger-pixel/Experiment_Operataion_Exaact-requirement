// ---------------------------------------------------------------------------
//  GATE 4 — REAL BROWSER VERIFICATION OF SELF-APPROVAL GOVERNANCE
//
//  The server tests prove the rule. They cannot see the two mistakes the SCREEN
//  must make impossible:
//
//    · a superuser meeting a refusal and concluding the product is broken, because
//      nothing tells them the exception exists and is switched off;
//    · an administrator believing self-approval is off when it is on, or the other
//      way round, because the setting is not stated where the rules are configured.
//
//    node tools/g4-browser-check.js [baseUrl] [user] [pass] "OTHER=.. OWN=.. ..."
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8848';
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

  sec('G4-UI · 1 · sign in as the superuser');
  await pg.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
  await pg.fill('input[name=username]', USER).catch(() => {});
  await pg.fill('input[name=password]', PASS).catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'), pg.click('button[type=submit]')]).catch(() => {});
  ok(!/\/login/.test(pg.url()), '1a · signed in');

  sec('G4-UI · 2 · the policy is stated where the rules are configured');
  await open('/recruit-approvals', '2a · the approval settings screen opens');
  let b = await body();
  ok(/Who may approve their own request/i.test(b), '2b · the self-approval policy is on the screen');
  ok(/Self-approval is OFF/i.test(b), '2c · *** …and states plainly that it is OFF ***');
  ok(!/Superuser exception enabled/i.test(b), '2d · …with the superuser exception not enabled');
  ok(/separation is the whole point/i.test(b), '2e · …explaining why the separation exists');
  const cbSelf = await pg.$('input[name=self_approval]');
  const cbMx   = await pg.$('input[name=master_exception]');
  ok(!!cbSelf && !!cbMx, '2f · both switches are present and separate');
  ok(await pg.$eval('input[name=self_approval]', el => !el.checked).catch(() => false),
     '2g · *** self-approval is unchecked — the locked default ***');
  ok(await pg.$eval('input[name=master_exception]', el => !el.checked).catch(() => false),
     '2h · *** …and so is the superuser exception ***');

  sec('G4-UI · 3 · a request raised by somebody else CAN be decided');
  await open('/hiring-request?id=' + IDS.OTHER, '3a · the other person\'s request opens');
  b = await body();
  ok(/Waiting for a decision/i.test(b), '3b · it is waiting');
  ok(!!(await pg.$('form#na-decide button[value=approve]')),
     '3c · *** Approve is offered — a different person may decide it ***');

  sec('G4-UI · 4 · *** the superuser\'s OWN request is blocked, and says why ***');
  await open('/hiring-request?id=' + IDS.OWN, '4a · their own request opens');
  b = await body();
  ok(/Waiting for a decision/i.test(b), '4b · it is waiting');
  ok(/You raised this request, so somebody else has to decide it/i.test(b),
     '4c · *** …and says they cannot decide it themselves ***');
  ok(/has not enabled the superuser self-approval exception/i.test(b),
     '4d · *** …naming the exception, so this reads as a setting and not a fault ***');
  ok(/change that in the approval settings/i.test(b),
     '4e · …with the way to change it, for somebody who may');
  ok(!(await pg.$('form#na-decide button[value=approve]')),
     '4f · *** and no Approve button is offered at all ***');

  sec('G4-UI · 5 · enabling the exception changes it, from the screen');
  await open('/recruit-approvals', '5a · back to the approval settings');
  await pg.check('input[name=master_exception]').catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'),
                     pg.click('form[action=""] button, button:has-text("Save policy")')]).catch(() => {});
  b = await body();
  ok(/Self-approval policy saved/i.test(b) || /Superuser exception enabled/i.test(b),
     '5b · the policy is saved');
  await open('/recruit-approvals', '5c · re-read from the server');
  b = await body();
  ok(/Superuser exception enabled/i.test(b), '5d · *** the exception now shows as enabled ***');
  ok(/Self-approval is OFF/i.test(b),
     '5e · *** …and self-approval is STILL off — two independent switches ***');

  sec('G4-UI · 6 · with it enabled, the same decision becomes possible');
  await open('/hiring-request?id=' + IDS.OWN, '6a · their own request again');
  b = await body();
  ok(!!(await pg.$('form#na-decide button[value=approve]')),
     '6b · *** Approve is now offered to the superuser on their own request ***');
  await pg.fill('form#na-decide input[name=note]', 'Approved under the superuser exception.').catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'),
                     pg.click('form#na-decide button[value=approve]')]).catch(() => {});
  b = await body();
  ok(/Request approved|APPROVED/i.test(b), '6c · …and the decision goes through');

  sec('G4-UI · 7 · nothing throws in the browser');
  ok(jsErrors.length === 0, '7a · no JavaScript errors'
     + (jsErrors.length ? ': ' + jsErrors.slice(0, 2).join(' | ') : ''));

  sec('G4-UI · 8 · the operational approval workflow on a real phone (360/390/412)');
  for (const w of [360, 390, 412]) {
    const m = await ctx.newPage();
    await m.setViewportSize({ width: w, height: 800 });
    const r = await m.goto(BASE + '/hiring-request?id=' + IDS.OTHER, { waitUntil: 'domcontentloaded' }).catch(() => null);
    ok(r && r.status() === 200, w + 'px · a request awaiting decision opens (' + (r ? r.status() : 0) + ')');
    const mb = (await m.textContent('body').catch(() => '')) || '';
    ok(/Waiting for a decision/i.test(mb), w + 'px · its state is readable');
    const btn = await m.$('form#na-decide button[value=approve]');
    ok(!!btn, w + 'px · *** Approve is usable on the phone ***');
    const over = await m.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2).catch(() => false);
    ok(!over, w + 'px · no horizontal overflow');
    const small = await m.evaluate(() => {
      const bs = [...document.querySelectorAll('form#na-decide button')];
      return bs.filter(x => x.getBoundingClientRect().height < 28).length;
    }).catch(() => 0);
    ok(small === 0, w + 'px · the decision buttons are thumb-sized');
    await m.close();
  }

  await br.close();
  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
