#!/usr/bin/env node
// One-off: initialise the tracker from the real inventory (route table, test book) — Stage 1.
const fs = require('fs'), path = require('path');
const { append, MODULES } = require('./qa');
const ROOT = path.resolve(__dirname, '..');
const BOOK = process.env.QA_BOOK || '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad/robot/harness/book.json';
const inv = JSON.parse(fs.readFileSync(path.join(ROOT, 'data', 'inventory_raw.json'), 'utf8'));
const book = JSON.parse(fs.readFileSync(BOOK, 'utf8'));
const E2E = 'End-to-end (spans modules)';
if (!MODULES.includes(E2E)) MODULES.push(E2E);

// ---- where a route or a test belongs among the owner's 12 modules (+ Marketplace, + end-to-end)
function routeModule(r) {
  const x = r.route;
  if (r.module === 'masters' || /^m\//.test(x) || /^(lookup|masters)/.test(x)) return 'Setup and Masters';
  if (/licen|subscription|tenant|saas|plan-|billing-plan|workspace/.test(x)) return 'Licensing and SaaS';
  if (/portal/.test(x)) return 'Client Portal';
  if (/^cx-|connect|marketplace|talent|passport/.test(x)) return 'Marketplace (found in app; not in the 12-module list)';
  return { 'Sales': 'CRM and Sales', 'Operations': 'Operations', 'Recruitment': 'People and Organisation', 'Reporting': 'Reporting / IDEMS',
    'Money': 'Money and Finance', 'Quality & Accreditation': 'Quality and Accreditation', 'Insights': 'Insights and Dashboards',
    'Directory': 'Directory', 'Admin': 'Admin and Security' }[r.area] || 'Admin and Security';
}
function caseModule(c) {
  const m = c.module || '';
  if (/licen|cloud workspace|subscription|product package|users & billing|^companies$|platform|applies|creates it|first sign-in|unused trials|self-hosted|front doors|feature gates|super admin|control panel/i.test(m)) return 'Licensing and SaaS';
  if (/portal/i.test(m)) return 'Client Portal';
  if (/^masters$|company setup|company profile|system settings|terminology|set up for testing|setting up|modules & features|industry$|service scope|report formats/i.test(m)) return 'Setup and Masters';
  return { 'Sales': 'CRM and Sales', 'Operations': 'Operations', 'Recruitment': 'People and Organisation', 'Reporting': 'Reporting / IDEMS',
    'Money': 'Money and Finance', 'Quality & Accreditation': 'Quality and Accreditation', 'Insights': 'Insights and Dashboards',
    'Directory': 'Directory', 'Admin': 'Admin and Security', 'Marketplace': 'Marketplace (found in app; not in the 12-module list)',
    'New company': 'Licensing and SaaS', 'Start here': 'Setup and Masters', 'Across modules': E2E, 'Your own pages': 'Admin and Security' }[c.area] || 'Admin and Security';
}
const ACTION = /-(save|delete|approve|reject|status|submit|toggle|upload|export|import|send|revise|drop|reopen|attach|sync|test|float|close|cancel|decide|grant|refuse|assign|post|remove|retract|unapprove|lock|unlock|confirm|mark|set|update|add|run|backfill|spend|file|lines|card)$/;

// ---- modules
const subOf = {}; book.cases.forEach(c => { const m = caseModule(c); (subOf[m] = subOf[m] || new Set()).add(c.module); });
const areasOf = {}; inv.routes.forEach(r => { const m = routeModule(r); (areasOf[m] = areasOf[m] || new Set()).add(r.area || '(no area)'); });
for (const m of MODULES) append('module', { id: m, status: m === 'CRM and Sales' ? 'IN PROGRESS' : 'NOT STARTED', areas: [...(areasOf[m] || [])].join(', '),
  submodules: [...(subOf[m] || [])].sort().join(' · '), agents: m === 'CRM and Sales' ? 'A04 Workflow robot (Sales test book)' : 'to be assigned in Stage 2',
  notes: m.startsWith('Marketplace') ? 'Present in the app (136 test-book cases) but not in the owner\'s 12-module list — reported as an additional module.' : '' });

// ---- screens and actions from the route table
for (const r of inv.routes) append('screen', { id: r.route, module: routeModule(r), area: r.area || '', label: r.label || '', perm: r.module,
  type: ACTION.test(r.route) ? 'POST action' : 'screen', status: 'NOT STARTED' });

// ---- test cases from the test book
const clip = (s, n = 30000) => (s.length > n ? s.slice(0, n) + ' …' : s);
for (const c of book.cases) append('test', { id: c.id, module: caseModule(c), submodule: c.module, title: c.title,
  pre: [c.persona ? 'As: ' + c.persona : '', c.company ? 'Company: ' + c.company : '', c.before || ''].filter(Boolean).join('\n'),
  steps: clip((c.steps || []).map((s, i) => `${i + 1}. ${s.do}`).join('\n')), expected: clip((c.steps || []).map((s, i) => `${i + 1}. ${s.expect}`).join('\n')),
  status: 'NOT STARTED', source: 'EXAACT Test Book ' + book.version });

// ---- agents (Part 5)
const A = [
  ['A00', 'Master QA Orchestrator', 'Planning, tracker, triage, coordination, reports', 'All', 'IN PROGRESS', 'Stage 1 discovery'],
  ['A01', 'Architecture & Discovery', 'Source, schema, dependencies, documentation, historical findings', 'All', 'IN PROGRESS', 'Route/table inventory done; historical findings being read'],
  ['A02', 'UI & Button-by-Button', 'Every screen, button, link, tab, modal (crawler)', 'All', 'NOT STARTED', ''],
  ['A03', 'Forms & Validation', 'Required/format/duplicate/server-side validation on every form', 'All', 'NOT STARTED', ''],
  ['A04', 'End-to-End Workflows (test-book robot)', 'Runs the test book module by module', 'CRM and Sales', 'IN PROGRESS', 'Running all 170 Sales cases on v51.1 (8 parallel lanes)'],
  ['A05', 'Database & Data Integrity', 'Keys, relations, orphans, duplicate cost paths', 'All', 'NOT STARTED', ''],
  ['A06', 'Edge Cases & Failure', 'Invalid data, interrupted flows, double submits', 'All', 'NOT STARTED', ''],
  ['A07', 'Security & Access Control', 'Roles, direct URL, CSRF, XSS, SQLi, files, isolation', 'Admin and Security', 'NOT STARTED', ''],
  ['A08', 'Finance & Billing', 'Contracts, invoices, payments, credit notes, reconciliation', 'Money and Finance', 'NOT STARTED', ''],
  ['A09', 'Operations Billing Rules', 'Man-day / man-month scenarios, billing triggers', 'Operations', 'NOT STARTED', ''],
  ['A10', 'Mobile & Responsive', 'Phone viewports for every screen and key flows', 'All', 'NOT STARTED', ''],
  ['A11', 'Performance & Reliability', 'Page timings, slow queries, large data (local copy only)', 'All', 'NOT STARTED', ''],
  ['A12', 'Regression', 'App\'s own automated suite + retests after fixes', 'All', 'IN PROGRESS', 'Baseline run of the 722 automated tests on v51.1'],
  ['A13', 'Code Repair', 'Root cause + fix of APPROVED defects only', 'All', 'NOT STARTED', 'Waits for owner approval of each fix'],
];
for (const [id, name, task, module, status, current] of A) append('agent', { id, name, task, module, status, current, start: status === 'IN PROGRESS' ? new Date().toISOString().slice(0, 16).replace('T', ' ') : '' });

// ---- known limitations (Sheet 7)
const L = [
  ['L-01', 'All', 'Confirm which version the test copy and live site run (deploy-check.php)', 'This machine cannot reach opstest.mghaiapps.com or operations.mghaiapps.com — the network policy refuses the connection (proxy 403).', 'Network allow-list of this cloud environment.', 'Cannot prove the deployed files equal V51; a test failure on the site could be a deployment difference.', 'Owner opens /deploy-check.php on each site and sends a screenshot; or allows read-only access to opstest only.', 'Owner: choose one of the two.', 'BLOCKED'],
  ['L-02', 'All', 'Test against the production database engine (MySQL)', 'Testing runs on SQLite copies of the app with made-up data. No MySQL/MariaDB server is installed here; production data must never be used.', 'Environment; production is out of bounds by your rule.', 'SQL that behaves differently on MySQL (dates, strict mode, collation) could pass here and fail on the host.', 'Install MariaDB on this machine and run the regression suite and key flows on it as well.', 'Owner: approve installing a local MariaDB test server (no live data).', 'OWNER ACCEPTANCE PENDING'],
  ['L-03', 'All', 'E-mails actually reaching an inbox', 'No real SMTP delivery from the test copy.', 'Test copy must not e-mail real people.', 'Delivery, spam placement and formatting in mail clients are unproven.', 'Robot checks the e-mail row the app writes (email_log): recipient, subject, body.', 'Owner: confirm one real e-mail per type on opstest.', 'CANNOT DO – AI / TOOL LIMITATION'],
  ['L-04', 'Money and Finance', 'Real Razorpay payment and refund', 'The real card/UPI page cannot be driven; only test keys exist on the copy.', 'Third-party payment service.', 'The live payment round-trip is unproven by the robot.', 'Robot tests order creation, signature check, webhook handling with test payloads.', 'Owner: one small real payment and refund (already in the go-live checklist).', 'CANNOT DO – AI / TOOL LIMITATION'],
  ['L-05', 'CRM and Sales', 'Google Ads / Meta / WhatsApp / SMS integrations', 'External accounts and APIs are not available.', 'Third-party services.', 'Lead import and messaging are unproven end-to-end.', 'Test the screens and stored settings; simulate inbound data.', 'Owner: test with the real accounts on opstest.', 'CANNOT DO – AI / TOOL LIMITATION'],
  ['L-06', 'Operations', 'Real phone hardware (camera, GPS, offline sync, install as app)', 'Phones are emulated in the browser; no real device.', 'Hardware.', 'Camera capture, location stamps and offline behaviour on real phones are unproven.', 'Phone-size emulation for layout and flows; offline tested in browser.', 'Owner / inspector: short field check on a real phone.', 'CANNOT DO – AI / TOOL LIMITATION'],
  ['L-07', 'Reporting / IDEMS', 'Printed look of PDFs and Word documents', 'The robot can render and screenshot documents but cannot judge print quality on paper.', 'Human judgement.', 'Layout issues on paper may be missed.', 'Robot renders each document to PDF and checks content; screenshots attached.', 'Owner: look at one printout of each document type.', 'OWNER ACCEPTANCE PENDING'],
  ['L-08', 'All', 'Hosting specifics (MilesWeb PHP version, cron timing, file permissions, backups)', 'The host is not reachable and cannot be inspected.', 'Access.', 'Host-only problems would not show here.', 'deploy-check.php on the host reports PHP version, writable folders and the build stamp.', 'Owner: send the deploy-check page after each upload.', 'BLOCKED'],
  ['L-09', 'All', 'Performance under real load', 'Load testing against the live site is forbidden; local timings are not representative of the host.', 'Your rule + environment.', 'Real-world speed under many users is estimated only.', 'Measure page and query times locally with a larger made-up data set; flag slow queries.', 'None, unless you want a controlled load test on opstest at a quiet time.', 'CANNOT DO – AI / TOOL LIMITATION'],
  ['L-10', 'Operations', 'The physical inspection itself', 'Field work cannot be simulated — only its records.', 'Physical world.', 'None for the software, as long as the records are tested.', 'Records, statuses, reports and billing are tested.', 'None.', 'NOT APPLICABLE – WITH JUSTIFICATION'],
  ['L-11', 'All', 'Business rules with no fixed right answer', 'Some test steps say "record what happens" — only you can decide what is right.', 'Business decision.', 'Those steps cannot pass or fail until decided.', 'Robot does the step, records what happened and proposes an answer.', 'Owner: decide each proposed answer (listed per module).', 'OWNER ACCEPTANCE PENDING'],
  ['L-12', 'All', 'Multiple independent agents', 'Agents are real separate workers, but they share one machine: about 8 browser lanes at a time.', 'Machine size.', 'Large runs take hours rather than minutes.', 'Run in lanes; heavy modules split across lanes.', 'None.', 'NOT APPLICABLE – WITH JUSTIFICATION'],
  ['L-13', 'Licensing and SaaS', 'Licence server and SaaS control plane (separate apps: licence-server/, mgh-hire/, mgh_books_receiver/)', 'Not yet known whether they can run here.', 'To be investigated in Stage 2.', 'Licence issue/renew/expiry across apps may be untested.', 'Try to run them locally against the test copy.', 'None yet.', 'NOT STARTED'],
];
for (const [id, module, activity, limitation, reason, impact, alternative, action, status] of L) append('limitation', { id, module, activity, limitation, reason, impact, alternative, action, status });

// ---- incomplete work (Sheet 6)
const I = [
  ['I-01', 'All (except CRM and Sales)', 'Robot scripts for the test book', '1,261 of 1,431 test-book cases have no robot script yet (Sales has all 170).', 'Yes — Sales done', 'Not yet built', 'Write scripts module by module (Stage 3)', 'QA agents', 'Script each module', 'see estimate', 'NOT STARTED'],
  ['I-02', 'All', 'Button-by-button inventory', 'The route table lists 480 screens/actions; buttons inside each screen are not yet counted.', 'No', 'Not yet built', 'A crawler that opens every screen as every role and lists each control', 'A02', 'Build crawler', '', 'NOT STARTED'],
  ['I-03', 'Money and Finance / Operations', 'Man-day and man-month billing scenarios (Part 10)', '27 scenarios listed; some are covered by test-book cases, others need new tests and contract set-ups.', 'Partly (Money cases exist)', 'Not yet built', 'Contract fixtures per trigger; reconciliation checks', 'A08, A09', 'Map scenarios to existing cases, write the rest', '', 'NOT STARTED'],
  ['I-04', 'All', 'Deployed-version check', 'Cannot reach the sites (L-01).', 'Yes', 'Access issue', 'deploy-check screenshots or read-only access', 'Owner', 'Owner decision', '', 'BLOCKED'],
  ['I-05', 'All', 'MySQL-fidelity run', 'All runs are on SQLite (L-02).', 'No', 'Environment issue', 'Local MariaDB test server', 'Owner approval', 'Owner decision', '', 'BLOCKED'],
  ['I-06', 'CRM and Sales', 'Built but held changes (v52, v53)', 'Two finished updates are on hold at your request; V51 is tested as it is.', 'Yes', 'Owner decision', 'Your go-ahead per update', 'Owner', 'Decide after V51 results', '', 'OWNER ACCEPTANCE PENDING'],
];
for (const [id, module, what, why, attempted, type, needed, owner, next, effort, status] of I) append('incomplete', { id, module, what: what + ' — ' + why, why, attempted, type, needed, owner, next, effort, status });

// ---- workflows (Part 9)
const W = [
  ['WF-01', 'Master → Directory → CRM → Contract → Operations → Job → Reports → Billing → Invoice → Payment → Reporting', 'All'],
  ['WF-02', 'Customer → Quotation → Approval → Contract', 'Directory, CRM and Sales'],
  ['WF-03', 'Employee → User → Role → Login → Permission check', 'People and Organisation, Admin and Security'],
  ['WF-04', 'Inspector assignment → Job allocation → Inspection → Report → Release', 'Operations, Reporting / IDEMS'],
  ['WF-05', 'Job expenses → Inspector vouchers → Cost accounting → Financial reports (duplicate-cost check)', 'Operations, Money and Finance'],
  ['WF-06', 'Client portal access → client actions → document visibility → permissions', 'Client Portal'],
  ['WF-07', 'Subscription → Licence → user limits → feature access → renewal / expiry', 'Licensing and SaaS'],
];
for (const [id, name, modules] of W) append('workflow', { id, name, modules, stages: '', tests: book.cases.filter(c => c.area === 'Across modules').length ? 'plus 15 "Across modules" test-book cases' : '', status: 'NOT STARTED' });

// ---- billing scenarios (Part 10)
const MD = ['Job completed and closed', 'Billable before final closure where the contract permits', 'Inspection report raised', 'Final Inspection Report raised', 'Final Release Note raised', 'Stage inspection completed', 'Multiple inspections under one contract', 'Several inspections in one monthly invoice', 'Terminated before full completion', 'Partial completion', 'Additional man-days used', 'Reopened or corrected after closure', 'Previously billed activity submitted again', 'Job with no final report requirement', 'Job with no release-note requirement'];
const MM = ['Man-month completed', 'Monthly billing while the job is open', 'Billing after completion of the man-month', 'Terminated halfway through the month', 'Terminated before agreed completion', 'Inspector replaced', 'Deputation extended', 'Additional days used', 'Month partially used', 'Several deputations in one monthly invoice', 'Previously billed month processed again', 'Deputation across billing periods'];
MD.forEach((s, i) => append('billing', { id: 'MD-' + String(i + 1).padStart(2, '0'), jobtype: 'Man-day', scenario: s, status: 'NOT STARTED' }));
MM.forEach((s, i) => append('billing', { id: 'MM-' + String(i + 1).padStart(2, '0'), jobtype: 'Man-month', scenario: s, status: 'NOT STARTED' }));

append('note', { id: 'STAGE-1', by: 'A00', status: 'IN PROGRESS', actual: 'Tracker initialised from the route table (' + inv.routes.length + ' screens/actions) and the test book (' + book.cases.length + ' cases).' });
console.log('seeded');
