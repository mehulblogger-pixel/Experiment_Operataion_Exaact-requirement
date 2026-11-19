// Compile the helpers' edge-case files into the test-book data files.
// node compile.js  → book/cases.json, book/findings.json, book/coverage.json
const fs = require('fs'), path = require('path');
const S = __dirname, OUT = path.join(S, 'source'), BOOK = path.join(S, 'book');
fs.mkdirSync(BOOK, { recursive: true });

// Book order and plain names for each helper's part.
const PARTS = [
  ['E2E', 'Whole journeys'], ['SALA', 'Sales — leads, enquiries, costing'], ['SALB', 'Sales — quotations'], ['SALC', 'Sales — Simple Sales, deals, targets, one firm record'],
  ['MON', 'Money'], ['OPS', 'Operations'], ['REP', 'Reports'], ['QUAA', 'Quality — NCR, CAPA, complaints'],
  ['QUAB', 'Quality — audits, competence, equipment'], ['REC', 'Recruitment & Directory'],
  ['MKT', 'Marketplace & portals'], ['ADM', 'Admin, sign-in & Insights'],
];
const personas = JSON.parse(fs.readFileSync(path.join(S, 'personas.json'), 'utf8'));
const extra = { MASTER_ADMIN: { login: 'your Master Admin login', name: 'Master Admin' },
  COORDINATOR2: personas.COORDINATOR?.[1], BRANCH_MANAGER2: personas.BRANCH_MANAGER?.[1], INSPECTOR2: personas.INSPECTOR?.[1] };
const who = (p) => {
  const k = String(p || '').trim();
  const u = extra[k] || (personas[k] && personas[k][0]);
  return u ? `${u.name} (${u.login})` : k.replace(/_/g, ' ').toLowerCase().replace(/^\w/, c => c.toUpperCase());
};
const readLines = (f) => fs.existsSync(f) ? fs.readFileSync(f, 'utf8').split('\n').filter(l => l.trim()).map((l, i) => {
  try { return JSON.parse(l); } catch (e) { console.error('bad line', f, i + 1); return null; } }).filter(Boolean) : [];
const str = (v) => Array.isArray(v) ? v.join('; ') : (v == null ? '' : typeof v === 'object' ? JSON.stringify(v) : String(v));

const cases = [], findings = [], coverage = [], seen = new Set();
for (const [code, part] of PARTS) {
  const rows = readLines(path.join(OUT, code + '.jsonl'));
  for (const o of rows) {
    if (!o.id || seen.has(o.id)) continue; seen.add(o.id);
    cases.push({
      id: o.id, part: code, partName: part, module: str(o.module) || part, screen: str(o.screen),
      buttons: Array.isArray(o.buttons) ? o.buttons.map(String) : (o.buttons ? [String(o.buttons)] : []),
      kind: String(o.kind || '').toUpperCase(), title: str(o.title), who: who(o.persona), persona: str(o.persona),
      why: str(o.why), before: str(o.before),
      steps: (Array.isArray(o.steps) ? o.steps : []).map(s => ({ do: str(s.do ?? s.action ?? s), expect: str(s.expect ?? '') })),
      flow: str(o.flow), basis: str(o.basis), ran: /private/i.test(str(o.verified)), saw: str(o.saw),
      finding: str(o.finding), question: str(o.question),
    });
  }
  for (const f of readLines(path.join(OUT, code + '-findings.jsonl')))
    findings.push({ part: code, partName: part, case: str(f.case), screen: str(f.screen), what: str(f.what), rule: str(f.rule),
      severity: (String(f.severity || 'low').toLowerCase().match(/high|medium|low/) || ['low'])[0] });
  const covFile = path.join(OUT, code + '-coverage.json');
  let routes = 0, routesCovered = 0, buttons = 0, buttonsCovered = 0, nav = 0, other = 0;
  if (fs.existsSync(covFile)) {
    try {
      const c = JSON.parse(fs.readFileSync(covFile, 'utf8'));
      for (const [r, v] of Object.entries(c.routes || {})) {
        routes++; if ((v.cases || []).length) routesCovered++;
        for (const b of Object.values(v.buttons || {})) {
          buttons++;
          if (Array.isArray(b) && b.length) buttonsCovered++;
          else if (/navigation/i.test(String(b))) nav++; else other++;
        }
      }
    } catch (e) { console.error('bad coverage', code, e.message); }
  }
  coverage.push({ part: code, partName: part, cases: rows.length, routes, routesCovered, buttons, buttonsCovered, nav, other,
    done: fs.existsSync(covFile) });
}
const sevRank = { high: 0, medium: 1, low: 2 };
findings.sort((a, b) => sevRank[a.severity] - sevRank[b.severity]);
fs.writeFileSync(path.join(BOOK, 'cases.json'), JSON.stringify(cases));
fs.writeFileSync(path.join(BOOK, 'findings.json'), JSON.stringify(findings));
fs.writeFileSync(path.join(BOOK, 'coverage.json'), JSON.stringify({ built: new Date().toISOString(), release: '69287af', parts: coverage }));
console.log('cases', cases.length, 'findings', findings.length);
for (const c of coverage) console.log(c.part, c.cases, 'routes', c.routesCovered + '/' + c.routes, 'buttons', c.buttonsCovered + '/' + c.buttons, 'nav', c.nav, 'other', c.other, c.done ? '' : '(coverage not written yet)');
