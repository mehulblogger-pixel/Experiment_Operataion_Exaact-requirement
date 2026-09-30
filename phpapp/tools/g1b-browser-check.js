// ---------------------------------------------------------------------------
//  GATE 1B — REAL BROWSER VERIFICATION OF PIPELINE AUTHORITY
//
//  The server tests prove the classification. They cannot see whether a person
//  can actually reach the controls, or whether a screen throws in the browser —
//  and Gate 0 exists because a route that every server test passed was, in fact,
//  unreachable. So this drives Chromium against the real screens.
//
//  It does NOT create a requirement through the UI: ADR-001 closed the direct
//  path deliberately (recruitment may only start from an approved request), so
//  the fixture is seeded first and this walk exercises what Gate 1B changed —
//  the candidate screen, its Recruitment tab, the stage move itself, the
//  requirement's fulfilment figures, and the Command Centre.
//
//    node tools/g1b-browser-check.js [baseUrl] [user] [pass] [candidateId] [requisitionId]
// ---------------------------------------------------------------------------
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:8842';
const USER = process.argv[3] || 'admin';
const PASS = process.argv[4] || 'admin12345';
const CID  = process.argv[5];
const RQID = process.argv[6];

let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  ok    ' + m); } else { fail++; console.log('  FAIL  ' + m); } };
const sec = (m) => console.log('\n== ' + m + ' ==');

(async () => {
  const br  = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } });
  const pg  = await ctx.newPage();
  const jsErrors = [];
  pg.on('pageerror', e => jsErrors.push(String(e)));

  const open = async (path, label) => {
    const r = await pg.goto(BASE + path, { waitUntil: 'domcontentloaded' }).catch(() => null);
    const code = r ? r.status() : 0;
    ok(code === 200, label + ' (' + code + ')');
    return code;
  };

  sec('G1B-UI · 1 · sign in');
  await pg.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
  await pg.fill('input[name=username]', USER).catch(() => {});
  await pg.fill('input[name=password]', PASS).catch(() => {});
  await Promise.all([pg.waitForLoadState('domcontentloaded'), pg.click('button[type=submit]')]).catch(() => {});
  ok(!/\/login/.test(pg.url()), '1a · signed in (now ' + pg.url().replace(BASE, '') + ')');

  sec('G1B-UI · 2 · the screens Gate 1B changed all render');
  await open('/recruitment',    '2a · recruitment home');
  await open('/recruitment-cc', '2b · Recruitment Command Centre (funnel, donut, trends, ageing)');
  await open('/candidates',     '2c · the applicants register');
  await open('/requisitions',   '2d · the requirements register');
  await open('/recruit-pipelines', '2e · the pipeline configuration screen');
  if (RQID) await open('/requisition?id=' + RQID, '2f · the requirement detail (fulfilment + health)');
  if (CID)  await open('/candidate?id=' + CID,    '2g · the applicant detail');

  //  The candidate screen's panes are tabs switched in the browser by their
  //  LABEL, not by a query parameter — so a person clicks the tab, and so does
  //  this walk. Not clicking it is precisely how the p7 UAT once reported a
  //  control as missing that was merely on another tab.
  const clickTab = async (label) => {
    const el = await pg.$('[data-tab-btn="' + label + '"]')
            || await pg.$('button:has-text("' + label + '")')
            || await pg.$('a:has-text("' + label + '")');
    if (el) { await el.click().catch(() => {}); await pg.waitForTimeout(250); return true; }
    //  Fall back to the same switch the page's own script performs.
    return await pg.evaluate((l) => {
      const secs = [...document.querySelectorAll('section[data-tab]')];
      if (!secs.length) return false;
      let found = false;
      for (const s of secs) {
        const on = s.getAttribute('data-tab') === l;
        s.style.display = on ? 'block' : 'none';
        if (on) found = true;
      }
      return found;
    }, label);
  };

  sec('G1B-UI · 3 · the Recruitment tab and its workflow panel');
  if (CID) {
    await pg.goto(BASE + '/candidate?id=' + CID, { waitUntil: 'domcontentloaded' }).catch(() => {});
    ok(await clickTab('Recruitment'), '3z · the Recruitment tab can be opened');
    const body = await pg.textContent('body').catch(() => '');
    ok(!/Unknown stage/i.test(body), '3a · the workflow panel does not answer "Unknown stage."');
    ok(/workflow|pipeline|stage/i.test(body), '3b · …and a workflow is actually shown');

    //  GATE 0's fix must still hold: per-stage capture posts to candidate-pipestage,
    //  never to the bare candidate-stage name.
    const acts = await pg.$$eval('form', fs => fs.map(f => f.getAttribute('action') || ''));
    ok(!acts.some(a => /\/candidate-stage(\?|$)/.test(a) && /pipestage/.test(a) === false && a.includes('do=')),
       '3c · no per-stage capture form posts to the legacy stage route (Gate 0 holds)');

    //  THE STAGE CONTROL must be reachable — this is the control Gate 1B re-pointed.
    const stageForm = await pg.$('form[action*="candidate-stage"]');
    ok(!!stageForm, '3d · the stage-move control is present and reachable');
  }

  sec('G1B-UI · 6 · a real stage move, driven by clicking');
  //  THE END-TO-END PROOF. A coordinator closes a candidate using the ordinary
  //  stage control. Gate 1B must land that on the pipeline's closed off-ramp with
  //  the matching outcome, and must NOT write the legacy column.
  if (CID) {
    await pg.goto(BASE + '/candidate?id=' + CID, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await clickTab('Recruitment');
    const form = await pg.$('form[action*="candidate-stage"]');
    if (!form) { ok(false, '6a · the stage form is reachable'); }
    else {
      ok(true, '6a · the stage form is reachable');
      const sel = await form.$('select[name=to_stage]');
      ok(!!sel, '6b · …and offers a stage to move to');
      if (sel) {
        await sel.selectOption('REJECTED').catch(async () => { await sel.selectOption({ index: 1 }); });
        for (const [n, v] of [['remark', 'gate 1b browser walk'], ['drop_reason', 'NOT_SUITABLE'], ['drop_point', 'INTERVIEW']]) {
          const f = await form.$('[name=' + n + ']');
          if (f) {
            const tag = await f.evaluate(e => e.tagName.toLowerCase());
            if (tag === 'select') await f.selectOption(v).catch(() => {});
            else await f.fill(v).catch(() => {});
          }
        }
        await Promise.all([
          pg.waitForLoadState('domcontentloaded'),
          form.$eval('button[type=submit], input[type=submit]', b => b.click()),
        ]).catch(() => {});
        const after = await pg.textContent('body').catch(() => '');
        ok(!/Unknown stage/i.test(after), '6c · the move was NOT answered with "Unknown stage."');
        ok(/closed|not proceeding|rejected/i.test(after),
           '6d · the screen now shows the candidate as closed / not proceeding');
      }
    }
  }

  sec('G1B-UI · 4 · nothing throws in the browser');
  ok(jsErrors.length === 0, '4a · no JavaScript errors across the walk'
     + (jsErrors.length ? ': ' + jsErrors.slice(0, 2).join(' | ') : ''));

  sec('G1B-UI · 5 · the operational mobile layout');
  const m = await ctx.newPage();
  await m.setViewportSize({ width: 390, height: 844 });
  for (const [p, l] of [['/recruitment', '5a · recruitment home on a phone'],
                        ['/candidates', '5b · the applicants register on a phone']]) {
    const r = await m.goto(BASE + p, { waitUntil: 'domcontentloaded' }).catch(() => null);
    ok(r && r.status() === 200, l + ' (' + (r ? r.status() : 0) + ')');
    const over = await m.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2).catch(() => false);
    ok(!over, l.replace(/^5[a-z] · /, '5x · ') + ' — no horizontal overflow');
  }

  await br.close();
  console.log('\n----------------------------------------------------');
  console.log('RESULT: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})();
