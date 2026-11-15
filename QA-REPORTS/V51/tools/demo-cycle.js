#!/usr/bin/env node
// =============================================================================
//  DEMO-01 — one complete, real test cycle (Part 23, Step 6)
//  Screen: Sales → Inquiries → "+ New inquiry" (form "New client inquiry")
//  On a private copy of the app (worker 9, port 8990) with made-up data.
//  Proves: real screen interaction · server-side validation · valid submit ·
//  database verification · duplicate-submission check · console/network capture ·
//  screenshots · tracker update.
// =============================================================================
const fs = require('fs'), path = require('path');
const ROBOT = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad/robot';
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const { T } = require(ROBOT + '/harness/robot.js');
const L = require(ROBOT + '/harness/lib-g1.js');
const { execFileSync } = require('child_process');
const qa = require('./qa');
const EV = path.resolve(__dirname, '..', 'evidence');
const PORT = +(process.env.QA_PORT || 8990), DB = process.env.QA_DB || ROBOT + '/w9.sqlite', TID = process.env.QA_TEST_ID || 'DEMO-01';
const sql = (q, p = []) => JSON.parse(execFileSync('php', [ROBOT + '/harness/tq.php', DB, q, JSON.stringify(p)]).toString());

(async () => {
  const browser = await chromium.launch();
  const t = new T({ id: 'DEMO-01', def: { steps: [] }, base: 'http://127.0.0.1:' + PORT, db: DB, pwFile: ROBOT + '/pw.txt', browser, shotDir: EV });
  const log = [], consoleErrors = [], netFails = [], shots = [];
  const ok = (step, what, pass, evidence) => { log.push({ step, what, pass, evidence }); console.log(pass ? 'PASS' : 'FAIL', step, what); };
  const shot = async (name, what) => { const f = `${TID}-${name}.png`; await t.p.screenshot({ path: path.join(EV, f), fullPage: false }); shots.push([f, what]); return f; };
  qa.append('agent', { id: 'A00', by: 'A00', status: 'IN PROGRESS', current: TID + ': one complete test cycle on Sales → Inquiries → New inquiry', screen: '/inquiry-new' });

  try {
    // 1 — sign in as a real role and open the screen through the menu, as a person would
    await t.as('MARKETING_EXECUTIVE');
    t.p.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    t.p.on('pageerror', e => consoleErrors.push('page error: ' + e.message));
    t.p.on('requestfailed', r => netFails.push(r.method() + ' ' + r.url() + ' — ' + (r.failure() || {}).errorText));
    t.p.on('response', r => { if (r.status() >= 500) netFails.push(r.status() + ' ' + r.request().method() + ' ' + r.url()); });
    await L.salesTile(t, '/inquiries');
    await t.click('+ New inquiry'); await t.see('New client inquiry');
    ok(1, 'Signed in as Test MktExec (Marketing Executive); Sales → Inquiries → "+ New inquiry" opened the form "New client inquiry".', true, await shot('1-form', 'The empty "New client inquiry" form'));

    // 2 — server-side validation: strip the browser's required checks and send an empty form
    const before = sql('SELECT COUNT(*) n FROM crm_inquiries')[0].n;
    await t.p.evaluate(() => document.querySelectorAll('[required]').forEach(e => e.removeAttribute('required')));
    await L.submit(t, 'Create inquiry'); const f2 = await t.flash();
    const after2 = sql('SELECT COUNT(*) n FROM crm_inquiries')[0].n;
    ok(2, `Empty form sent past the browser checks → server answered: "${f2}". Rows in crm_inquiries: ${before} before, ${after2} after.`, !!f2 && after2 === before, await shot('2-server-validation', 'Server-side refusal of an empty inquiry'));

    // 3 — valid data
    await t.go('/inquiry-new');
    await L.pick(t, '[name=client_id]', 'TB Refinery Ltd'); await t.fill('[name=received_date]', L.today());
    await t.fill('[name=contact_name]', 'Mr Demo Patel'); await t.fill('[name=contact_email]', 'demo.patel@tbrefinery.test');
    await L.pick(t, '[name=sbu]', 'Oil, Gas & Chemicals'); await L.pick(t, '[name=source]', 'Email');
    await t.fill('[name=subject]', 'QA DEMO-01 — shutdown TPI, columns C-101/102');
    await t.fill('[name=service_requirement]', 'Stage + final inspection, 20 man-days');
    await shot('3-filled', 'The form filled with valid test data');
    await L.submit(t, 'Create inquiry'); const f3 = await t.flash();
    const m = f3.match(/(INQ-[\w-]+) created/);
    ok(3, `Valid submit → message: "${f3}".`, !!m, await shot('4-created', 'Success message after creating the inquiry'));

    // 4 — database: exactly one new row, with the values typed
    const rows = sql("SELECT i.id, i.inquiry_no, i.subject, i.status, i.client_id, p.legal_name client, i.contact_email, i.sbu, i.source, i.received_date, i.created_by FROM crm_inquiries i LEFT JOIN business_partners p ON p.id=i.client_id WHERE i.subject=?", ['QA DEMO-01 — shutdown TPI, columns C-101/102']);
    const after4 = sql('SELECT COUNT(*) n FROM crm_inquiries')[0].n;
    const r = rows[0] || {};
    const dbOk = rows.length === 1 && after4 === before + 1 && r.inquiry_no === (m && m[1]) && /TB Refinery/i.test(r.client || '') && r.contact_email === 'demo.patel@tbrefinery.test' && r.status === 'OPEN';
    fs.writeFileSync(path.join(EV, TID + '-db-row.json'), JSON.stringify(rows, null, 1));
    ok(4, `Database: crm_inquiries has exactly one new row (${before} → ${after4}): ${JSON.stringify(r)}.`, dbOk, TID + '-db-row.json');

    // 5 — the same submission sent twice (browser Back + submit again) must not create a second record
    await t.p.goBack(); await t.p.waitForLoadState('load');
    await L.submit(t, 'Create inquiry').catch(() => {}); const f5 = await t.flash();
    const dup = sql('SELECT COUNT(*) n FROM crm_inquiries WHERE subject=?', ['QA DEMO-01 — shutdown TPI, columns C-101/102'])[0].n;
    ok(5, `Back + submit the same form again → "${f5}". Rows with this subject: ${dup}.`, dup === 1, await shot('5-resubmit', 'Result of re-sending the same form'));

    // 6 — the new inquiry shows in the register
    await t.go('/inquiries'); const listed = (await t.text()).includes('QA DEMO-01');
    ok(6, `Register Sales → Inquiries lists the new inquiry: ${listed ? 'yes' : 'no'}.`, listed, await shot('6-register', 'The inquiry register showing the new record'));
  } catch (e) { ok(log.length + 1, 'Stopped: ' + e.message.split('\n')[0], false, ''); }

  ok(7, `Browser console errors: ${consoleErrors.length ? consoleErrors.join(' | ') : 'none'}. Failed / 5xx network requests: ${netFails.length ? netFails.join(' | ') : 'none'}.`, consoleErrors.length === 0 && netFails.length === 0, '');
  await browser.close();

  // ---- tracker: test, evidence, agent
  const pass = log.every(x => x.pass);
  qa.append('test', { id: TID, by: 'A00', module: 'CRM and Sales', submodule: 'Inquiries', title: 'DEMO — create a client inquiry: server-side validation, valid save, database check, double-submit check',
    pre: 'As: Marketing Executive (test.mktexec) · private copy, worker 9 · made-up client "TB Refinery Ltd"',
    steps: log.map(x => `${x.step}. ${x.what.split('→')[0]}`).join('\n'), expected: '1 form opens · 2 empty form refused by the SERVER, nothing saved · 3 "INQ-… created" · 4 exactly one row with the typed values, status OPEN · 5 second send creates no second row · 6 listed in the register · 7 no console errors or failed requests',
    actual: log.map(x => `${x.step}. ${x.pass ? 'PASS' : 'FAIL'} — ${x.what}`).join('\n'), status: pass ? 'COMPLETED – PASS' : 'COMPLETED – FAIL', executed: true,
    evidence: shots.map(s => 'evidence/' + s[0]).join('\n') + '\nevidence/' + TID + '-db-row.json' });
  shots.forEach(([f, what], i) => qa.append('evidence', { id: 'EV-' + TID + '-' + (i + 1), by: 'A00', ref: TID, type: 'screenshot', file: 'QA-REPORTS/V51/evidence/' + f, what }));
  qa.append('evidence', { id: 'EV-' + TID + '-DB', by: 'A00', ref: TID, type: 'database', file: 'QA-REPORTS/V51/evidence/' + TID + '-db-row.json', what: 'The crm_inquiries row read back after saving' });
  qa.append('agent', { id: 'A00', by: 'A00', status: 'IN PROGRESS', current: TID + ' finished: ' + (pass ? 'all 7 checks passed' : 'one or more checks failed') + '. Waiting for owner approval of the plan.', next: 'Owner review of discovery report and demo' });
  fs.writeFileSync(path.join(EV, TID + '-result.json'), JSON.stringify({ pass, log, consoleErrors, netFails }, null, 1));
  console.log('RESULT', pass ? 'PASS' : 'FAIL');
})();
