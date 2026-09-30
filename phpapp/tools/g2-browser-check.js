// ---------------------------------------------------------------------------
//  GATE 2 — REAL BROWSER VERIFICATION OF CHANGE CONTROL
//
//  The server tests prove the versioning. They cannot see the one mistake the UI
//  must make impossible: believing a PROPOSED value is already in force. So this
//  drives Chromium and reads what a person would actually see.
//
//    node tools/g2-browser-check.js [baseUrl] [user] [pass] [hiringRequestId]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8844';
const USER = process.argv[3] || 'admin';
const PASS = process.argv[4] || 'admin12345';
const HID  = process.argv[5];

let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; console.log('  FAIL  ' + m); } };
const sec = (m) => console.log('\n== ' + m + ' ==');

(async () => {
  const br  = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg  = await ctx.newPage();
  const jsErrors = [];
  pg.on('pageerror', e => jsErrors.push(String(e)));
  const open = async (p, label) => {
    const r = await pg.goto(BASE + p, { waitUntil: 'domcontentloaded' }).catch(() => null);
    const code = r ? r.status() : 0;
    ok(code === 200, label + ' (' + code + ')');
    return code;
  };

  sec('G2-UI · 1 · sign in and reach the requirement screens');
  await pg.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
  await pg.fill('input[name=username]', USER).catch(() => {});
  await pg.fill('input[name=password]', PASS).catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'), pg.click('button[type=submit]')]).catch(() => {});
  ok(!/\/login/.test(pg.url()), '1a · signed in');
  await open('/hiring-requests', '1b · the hiring request register renders');
  await open('/requisitions',    '1c · the requirements register renders');

  sec('G2-UI · 2 · the change-control panel says what is in force');
  if (HID) {
    await open('/hiring-request?id=' + HID, '2a · the hiring request opens');
    const body = await pg.textContent('body').catch(() => '');
    ok(/Change control/i.test(body), '2b · the change-control panel is on the screen');
    ok(/Version 1 in force/i.test(body), '2c · …and states which version is IN FORCE');
    ok(/awaiting a decision/i.test(body), '2d · …that a change is awaiting a decision');
    ok(/not yet in force/i.test(body),
       '2e · *** …and that the proposed values are NOT YET IN FORCE ***');
    ok(/the approved .* above is what recruitment is working to/i.test(body),
       '2f · …saying plainly what recruitment is actually working to');

    //  THE APPROVED VALUE AND THE PROPOSED ONE ARE BOTH SHOWN, SIDE BY SIDE.
    ok(/Approved now/i.test(body) && /Proposed/i.test(body),
       '2g · the approved and proposed values are shown side by side, not merged');
    ok(/the project grew/i.test(body), '2h · the stated reason is shown (Q10)');
    ok(/SUPERVISOR/i.test(body), '2i · the proposed designation is visible as a proposal');

    //  A REFUSED PROPOSAL IS STILL ON THE RECORD.
    ok(/Previous proposals/i.test(body), '2j · the proposal history is shown');
    ok(/Rejected/i.test(body), '2k · …including the refused one');
    ok(/not justified/i.test(body), '2l · …with the reason it was refused');
    ok(/never amended/i.test(body), '2m · …and says a refused proposal is never amended in place');

    //  AND THE HEADLINE FIGURE IS STILL THE APPROVED ONE.
    const proposedQtyLeaked = /Approved headcount[^0-9]{0,40}9\b/i.test(body);
    ok(!proposedQtyLeaked, '2n · *** the proposed headcount has NOT replaced the approved one ***');
  }

  sec('G2-UI · 3 · nothing throws in the browser');
  ok(jsErrors.length === 0, '3a · no JavaScript errors'
     + (jsErrors.length ? ': ' + jsErrors.slice(0, 2).join(' | ') : ''));

  sec('G2-UI · 4 · the operational mobile layout');
  const m = await ctx.newPage();
  await m.setViewportSize({ width: 390, height: 844 });
  for (const [p, l] of [['/hiring-requests', '4a · the register on a phone'],
                        [HID ? '/hiring-request?id=' + HID : '/hiring-requests', '4b · the requirement on a phone']]) {
    const r = await m.goto(BASE + p, { waitUntil: 'domcontentloaded' }).catch(() => null);
    ok(r && r.status() === 200, l + ' (' + (r ? r.status() : 0) + ')');
    const over = await m.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2).catch(() => false);
    ok(!over, l + ' — no horizontal overflow');
  }

  await br.close();
  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
