#!/usr/bin/env node
// =============================================================================
//  EXAACT V51 QA — central tracker store and workbook builder
// =============================================================================
//  The single source of truth is data/events.jsonl: an APPEND-ONLY log, one JSON
//  object per line. Every agent writes by appending (O_APPEND is atomic for a
//  single small write on Linux, so parallel agents never overwrite each other).
//  Nothing is ever edited or deleted — a status change is a new event, so the
//  full history of every item is kept.
//
//  The owner-facing Excel workbook is GENERATED from that log (`build`), as often
//  as needed. It is never edited by hand and never edited concurrently.
//
//  Usage:
//    node qa.js add <kind> '<json>'          append one event (needs .id, .by)
//    node qa.js build                        regenerate the workbook + summary
//
//  Kinds: module, screen, test, agent, defect, fix, workflow, billing, evidence,
//         limitation, incomplete, historical, note
// =============================================================================
const fs = require('fs'), path = require('path');
const ROOT = path.resolve(__dirname, '..');
const LOG = path.join(ROOT, 'data', 'events.jsonl');
const NODE_MODULES = process.env.QA_NODE_MODULES || '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad/docgen/node_modules';

const STATUSES = ['NOT STARTED', 'IN PROGRESS', 'COMPLETED – PASS', 'COMPLETED – FAIL', 'BLOCKED', 'NOT IMPLEMENTED',
  'CANNOT TEST', 'CANNOT DO – AI / TOOL LIMITATION', 'NOT APPLICABLE – WITH JUSTIFICATION', 'FIXED – RETEST PENDING',
  'RETEST PASSED', 'RETEST FAILED', 'OWNER ACCEPTANCE PENDING', 'OWNER ACCEPTED'];
const MODULES = ['Setup and Masters', 'Directory', 'CRM and Sales', 'Operations', 'People and Organisation', 'Reporting / IDEMS',
  'Quality and Accreditation', 'Money and Finance', 'Insights and Dashboards', 'Licensing and SaaS', 'Client Portal',
  'Admin and Security', 'Marketplace (found in app; not in the 12-module list)', 'End-to-end (spans modules)'];
const VERSION = 'V51 (git v51.1, commit c4530f7 + docs 1a8f3c6)';

function append(kind, obj) {
  if (!obj.id) throw new Error('event needs an id');
  if (obj.status && !STATUSES.includes(obj.status)) throw new Error('status not allowed: ' + obj.status);
  const ev = { ts: new Date().toISOString(), kind, by: obj.by || 'Orchestrator', ...obj };
  fs.appendFileSync(LOG, JSON.stringify(ev) + '\n');
  return ev;
}
function appendMany(list) {   // one write per event: still atomic per line
  for (const [kind, obj] of list) append(kind, obj);
}

function fold() {
  const items = {}, log = [];
  if (!fs.existsSync(LOG)) return { items, log };
  for (const line of fs.readFileSync(LOG, 'utf8').split('\n')) {
    if (!line.trim()) continue;
    let ev; try { ev = JSON.parse(line); } catch (e) { continue; }
    log.push(ev);
    const k = ev.kind + '|' + ev.id;
    const prev = items[k] || { _history: [] };
    if (ev.status && prev.status !== ev.status) prev._history.push(`${ev.ts.slice(0, 16).replace('T', ' ')} ${ev.status} (${ev.by})`);
    items[k] = { ...prev, ...ev, _history: prev._history, _first: prev._first || ev.ts };
  }
  return { items, log };
}
const of = (items, kind) => Object.values(items).filter(x => x.kind === kind);

async function build() {
  const ExcelJS = require(path.join(NODE_MODULES, 'exceljs'));
  const { items, log } = fold();
  const tests = of(items, 'test'), defects = of(items, 'defect'), fixes = of(items, 'fix'), agents = of(items, 'agent');
  const screens = of(items, 'screen'), modules = of(items, 'module');
  const cnt = (arr, s) => arr.filter(x => x.status === s).length;
  const applicable = tests.filter(t => t.status !== 'NOT APPLICABLE – WITH JUSTIFICATION');
  const EXECUTED = ['COMPLETED – PASS', 'COMPLETED – FAIL', 'RETEST PASSED', 'RETEST FAILED', 'OWNER ACCEPTED'];
  const PASSES = ['COMPLETED – PASS', 'RETEST PASSED', 'OWNER ACCEPTED'];
  const executed = applicable.filter(t => EXECUTED.includes(t.status));
  const passed = executed.filter(t => PASSES.includes(t.status));
  const pct = (a, b) => b ? Math.round(a * 1000 / b) / 10 : 0;

  const wb = new ExcelJS.Workbook(); wb.creator = 'EXAACT QA Orchestrator'; wb.created = new Date();
  const hdr = (ws, cols) => {
    ws.columns = cols.map(([header, key, width]) => ({ header, key, width }));
    const r = ws.getRow(1); r.font = { bold: true, color: { argb: 'FFFFFFFF' } }; r.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F4E79' } };
    r.alignment = { wrapText: true, vertical: 'middle' }; ws.views = [{ state: 'frozen', ySplit: 1 }];
    ws.autoFilter = { from: { row: 1, column: 1 }, to: { row: 1, column: cols.length } };
  };
  const COLOR = { 'COMPLETED – PASS': 'FFE8F5E9', 'RETEST PASSED': 'FFE8F5E9', 'OWNER ACCEPTED': 'FFE8F5E9', 'COMPLETED – FAIL': 'FFFDECEA', 'RETEST FAILED': 'FFFDECEA',
    'BLOCKED': 'FFFFF4E5', 'CANNOT TEST': 'FFEEEEEE', 'CANNOT DO – AI / TOOL LIMITATION': 'FFEEEEEE', 'NOT IMPLEMENTED': 'FFF3E5F5', 'IN PROGRESS': 'FFE3F2FD',
    'OWNER ACCEPTANCE PENDING': 'FFFFF8E1', 'FIXED – RETEST PENDING': 'FFE3F2FD' };
  const add = (ws, row, statusKey = 'status') => { const r = ws.addRow(row); r.alignment = { wrapText: true, vertical: 'top' }; const c = COLOR[row[statusKey]]; if (c) { const cell = r.getCell(statusKey); cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: c } }; } return r; };

  // 1 — Live Dashboard
  const d = wb.addWorksheet('1 Live Dashboard');
  d.columns = [{ width: 52 }, { width: 22 }, { width: 70 }];
  const kv = (k, v, note = '') => { const r = d.addRow([k, v, note]); r.alignment = { wrapText: true, vertical: 'top' }; return r; };
  d.addRow(['EXAACT — Live Testing Dashboard']).font = { bold: true, size: 16 };
  kv('Application version under test', VERSION);
  kv('Last generated', new Date().toISOString().replace('T', ' ').slice(0, 16) + ' UTC', 'Regenerated from data/events.jsonl after every batch of results.');
  kv('');
  const openP = s => defects.filter(x => x.severity === s && !['RETEST PASSED', 'OWNER ACCEPTED', 'NOT APPLICABLE – WITH JUSTIFICATION'].includes(x.status)).length;
  [['Total modules identified', modules.length], ['Total screens and actions identified (route table)', screens.length],
   ['Total buttons and actions identified (crawl)', of(items, 'button').length, 'Filled by the Stage 3 screen crawl.'],
   ['Total test cases planned', tests.length], ['Total tests executed', executed.length, 'Executed = COMPLETED – PASS/FAIL, RETEST PASSED/FAILED, OWNER ACCEPTED.'],
   ['Total passed', passed.length], ['Total failed', executed.length - passed.length],
   ['Total pending (NOT STARTED / IN PROGRESS)', cnt(tests, 'NOT STARTED') + cnt(tests, 'IN PROGRESS')], ['Total blocked', cnt(tests, 'BLOCKED')],
   ['Total not implemented', cnt(tests, 'NOT IMPLEMENTED')], ['Total cannot test (incl. AI / tool limitation)', cnt(tests, 'CANNOT TEST') + cnt(tests, 'CANNOT DO – AI / TOOL LIMITATION')],
   ['Total owner acceptance pending', cnt(tests, 'OWNER ACCEPTANCE PENDING')],
   ['Total defects identified', defects.length], ['Total defects fixed', fixes.length], ['Defects awaiting retest', cnt(defects, 'FIXED – RETEST PENDING')],
   ['Retests passed', cnt(defects, 'RETEST PASSED')], ['Retests failed', cnt(defects, 'RETEST FAILED')],
   ['Testing completion %', pct(executed.length, applicable.length) + ' %', 'Applicable test cases executed ÷ applicable test cases × 100. Blocked, pending, not implemented and cannot-test are NOT counted as completed.'],
   ['Pass rate %', pct(passed.length, executed.length) + ' %', 'Tests passed ÷ tests executed × 100. Kept separate from completion.'],
   ['Outstanding P0 (critical)', openP('P0')], ['Outstanding P1 (high)', openP('P1')],
  ].forEach(r => kv(...r));
  kv(''); d.addRow(['Module-wise progress', 'Planned / Executed / Passed / Failed / Blocked / Cannot / Owner']).font = { bold: true };
  for (const m of MODULES) { const t = tests.filter(x => x.module === m); const ex = t.filter(x => EXECUTED.includes(x.status)); const pa = ex.filter(x => PASSES.includes(x.status));
    kv(m, `${t.length} / ${ex.length} / ${pa.length} / ${ex.length - pa.length} / ${cnt(t, 'BLOCKED')} / ${cnt(t, 'CANNOT TEST') + cnt(t, 'CANNOT DO – AI / TOOL LIMITATION')} / ${cnt(t, 'OWNER ACCEPTANCE PENDING')}`, `completion ${pct(ex.length, t.length)} % · pass rate ${pct(pa.length, ex.length)} %`); }
  kv(''); d.addRow(['Agent-wise progress', 'Status']).font = { bold: true };
  agents.sort((a, b) => a.id.localeCompare(b.id)).forEach(a => kv(`${a.id} — ${a.name}`, a.status, a.current || ''));

  // 2 — Module-wise tracker
  const m2 = wb.addWorksheet('2 Module Tracker');
  hdr(m2, [['Module', 'id', 34], ['App areas / menus', 'areas', 30], ['Submodules found', 'submodules', 60], ['Screens & actions', 'screens', 12], ['Test cases', 'tests', 10], ['Assigned agents', 'agents', 30], ['Status', 'status', 22], ['Notes', 'notes', 60]]);
  modules.forEach(m => add(m2, { id: m.id, areas: m.areas, submodules: m.submodules, screens: screens.filter(s => s.module === m.id).length, tests: tests.filter(t => t.module === m.id).length, agents: m.agents, status: m.status, notes: m.notes }));

  // 3 — Agents
  const a3 = wb.addWorksheet('3 Agent Tracker');
  hdr(a3, [['Agent ID', 'id', 10], ['Agent name', 'name', 30], ['Assigned task', 'task', 50], ['Module', 'module', 22], ['Current activity', 'current', 50], ['Current screen', 'screen', 22], ['Start', 'start', 17], ['Last update', 'ts', 17], ['Completion', 'done', 17], ['Status', 'status', 22], ['Findings', 'findings', 40], ['Defects', 'defects', 14], ['Dependencies', 'deps', 30], ['Next action', 'next', 40], ['Status history', '_h', 60]]);
  agents.forEach(a => add(a3, { ...a, ts: a.ts.slice(0, 16).replace('T', ' '), _h: a._history.join('\n') }));

  // 4 — Screens & buttons
  const s4 = wb.addWorksheet('4 Screen & Button Tracker');
  hdr(s4, [['Route / control', 'id', 32], ['Kind', 'type', 10], ['Module', 'module', 26], ['App area', 'area', 18], ['Screen label', 'label', 34], ['Permission module', 'perm', 16], ['Status', 'status', 22], ['Evidence', 'evidence', 40], ['Notes', 'notes', 50]]);
  [...screens, ...of(items, 'button')].sort((a, b) => (a.module || '').localeCompare(b.module || '') || a.id.localeCompare(b.id)).forEach(s => add(s4, { ...s, type: s.kind === 'button' ? 'action' : (s.type || 'route') }));

  // 5 — Test cases
  const t5 = wb.addWorksheet('5 Test Case Register');
  hdr(t5, [['Test ID', 'id', 13], ['Module', 'module', 22], ['Submodule', 'submodule', 22], ['Test scenario', 'title', 50], ['Preconditions', 'pre', 40], ['Test steps', 'steps', 60], ['Expected result', 'expected', 50], ['Actual result', 'actual', 60], ['Status', 'status', 22], ['Agent', 'by', 14], ['Evidence', 'evidence', 30], ['Execution date', 'date', 16], ['Defect ID', 'defect', 12], ['Retest status', 'retest', 16]]);
  tests.sort((a, b) => a.id.localeCompare(b.id)).forEach(t => add(t5, { ...t, date: t.executed ? t.ts.slice(0, 10) : '' }));

  // 6 — Incomplete
  const i6 = wb.addWorksheet('6 Incomplete & Pending');
  hdr(i6, [['ID', 'id', 10], ['Module', 'module', 22], ['What is incomplete', 'what', 50], ['Why', 'why', 45], ['Attempted?', 'attempted', 12], ['Type', 'type', 18], ['What is needed', 'needed', 45], ['Responsible', 'owner', 16], ['Next action', 'next', 40], ['Estimated effort', 'effort', 14], ['Status', 'status', 22]]);
  of(items, 'incomplete').forEach(x => add(i6, x));

  // 7 — Cannot do
  const c7 = wb.addWorksheet('7 AI Cannot Do - Cannot Test');
  hdr(c7, [['Limitation ID', 'id', 12], ['Module', 'module', 22], ['Activity', 'activity', 40], ['Exact limitation', 'limitation', 45], ['Reason', 'reason', 40], ['Business impact', 'impact', 40], ['Alternative verification', 'alternative', 45], ['Action required from owner / developer', 'action', 45], ['Status', 'status', 26]]);
  of(items, 'limitation').forEach(x => add(c7, x));

  // 8 — Defects
  const d8 = wb.addWorksheet('8 Defect Register');
  hdr(d8, [['Defect ID', 'id', 11], ['Severity', 'severity', 8], ['Module', 'module', 20], ['Screen', 'screen', 20], ['Description', 'description', 50], ['Steps to reproduce', 'steps', 50], ['Expected', 'expected', 40], ['Actual', 'actual', 40], ['Evidence', 'evidence', 30], ['Root cause', 'root', 40], ['Affected files', 'files', 30], ['Proposed correction', 'proposal', 45], ['Approval', 'approval', 16], ['Assigned', 'assigned', 14], ['Fix date', 'fixdate', 12], ['Retest result', 'retest', 16], ['Source test', 'test', 12], ['Status', 'status', 22]]);
  defects.sort((a, b) => a.id.localeCompare(b.id)).forEach(x => add(d8, x));

  // 9 — Fix & retest
  const f9 = wb.addWorksheet('9 Fix & Retest');
  hdr(f9, [['Fix ID', 'id', 10], ['Defect ID', 'defect', 10], ['Changed files', 'files', 45], ['Change', 'change', 50], ['Developer / agent', 'by', 14], ['Fix date', 'date', 12], ['Original test ID', 'test', 12], ['Retest result', 'retest', 16], ['Regression outcome', 'regression', 40], ['Commit', 'commit', 12], ['Status', 'status', 22]]);
  fixes.forEach(x => add(f9, x));

  // 10 — Workflows
  const w10 = wb.addWorksheet('10 Business Workflows');
  hdr(w10, [['Workflow ID', 'id', 11], ['Workflow', 'name', 50], ['Modules crossed', 'modules', 40], ['Stages', 'stages', 70], ['Test cases', 'tests', 30], ['Stopped at / manual step needed', 'stopped', 40], ['Status', 'status', 22], ['Evidence', 'evidence', 30]]);
  of(items, 'workflow').forEach(x => add(w10, x));

  // 11 — Finance & billing
  const b11 = wb.addWorksheet('11 Finance & Billing');
  hdr(b11, [['Scenario ID', 'id', 11], ['Job type', 'jobtype', 14], ['Scenario', 'scenario', 50], ['Contract trigger', 'trigger', 22], ['Contracted qty', 'qty', 10], ['Rate', 'rate', 10], ['Utilised', 'used', 10], ['Chargeable', 'chargeable', 10], ['Previously billed', 'prev', 10], ['Newly billable', 'newly', 10], ['Remaining unbilled', 'remain', 10], ['Invoice amount', 'amount', 12], ['Tax', 'tax', 10], ['Duplicate check', 'dup', 20], ['Difference found', 'diff', 40], ['Status', 'status', 22], ['Evidence', 'evidence', 30]]);
  of(items, 'billing').forEach(x => add(b11, x));

  // 12 — Evidence
  const e12 = wb.addWorksheet('12 Evidence Register');
  hdr(e12, [['Evidence ID', 'id', 14], ['Test / defect', 'ref', 14], ['Type', 'type', 14], ['File / location', 'file', 70], ['What it shows', 'what', 60], ['Captured', 'ts', 17]]);
  of(items, 'evidence').forEach(x => add(e12, { ...x, ts: x.ts.slice(0, 16).replace('T', ' ') }));

  // 13 — Historical findings
  const h13 = wb.addWorksheet('13 Historical Findings');
  hdr(h13, [['ID', 'id', 10], ['Source', 'source', 30], ['Module', 'module', 22], ['Finding', 'description', 60], ['Original status', 'original', 18], ['V51 classification', 'v51', 26], ['Evidence', 'evidence', 60], ['How it will be retested', 'retest', 50], ['Status', 'status', 22]]);
  of(items, 'historical').sort((a, b) => a.id.localeCompare(b.id)).forEach(x => add(h13, x));

  // 14 — Activity log (append-only, newest first)
  const l14 = wb.addWorksheet('14 Activity Log');
  hdr(l14, [['Time (UTC)', 'ts', 17], ['By', 'by', 14], ['Kind', 'kind', 11], ['Item', 'id', 16], ['Status', 'status', 22], ['Detail', 'detail', 90]]);
  log.slice().reverse().slice(0, 5000).forEach(e => l14.addRow({ ts: e.ts.slice(0, 19).replace('T', ' '), by: e.by, kind: e.kind, id: e.id, status: e.status || '', detail: e.actual || e.current || e.description || e.what || e.activity || e.title || '' }));

  const out = path.join(ROOT, 'EXAACT_LIVE_TESTING_TRACKER.xlsx');
  await wb.xlsx.writeFile(out + '.tmp'); fs.renameSync(out + '.tmp', out);   // never leave a half-written workbook
  const summary = { generated: new Date().toISOString(), tests: tests.length, executed: executed.length, passed: passed.length, failed: executed.length - passed.length,
    blocked: cnt(tests, 'BLOCKED'), notStarted: cnt(tests, 'NOT STARTED'), cannot: cnt(tests, 'CANNOT TEST') + cnt(tests, 'CANNOT DO – AI / TOOL LIMITATION'), owner: cnt(tests, 'OWNER ACCEPTANCE PENDING'),
    completionPct: pct(executed.length, applicable.length), passRatePct: pct(passed.length, executed.length), defects: defects.length, screens: screens.length, events: log.length };
  fs.writeFileSync(path.join(ROOT, 'data', 'summary.json'), JSON.stringify(summary, null, 1));
  console.log(JSON.stringify(summary));
}

module.exports = { append, appendMany, fold, MODULES, STATUSES, VERSION };
if (require.main === module) {
  const [cmd, kind, json] = process.argv.slice(2);
  if (cmd === 'add') console.log(JSON.stringify(append(kind, JSON.parse(json))));
  else if (cmd === 'build') build().catch(e => { console.error(e); process.exit(1); });
  else console.log('usage: qa.js add <kind> <json> | build');
}
