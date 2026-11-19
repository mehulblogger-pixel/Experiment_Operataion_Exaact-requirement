// The Sales test book (owner, 19 Nov 2026 — "provide me all edge cases particularly
// for sales a detailed testing"): every Sales case from parts SALA, SALB and SALC,
// each finding with its status in this update, and the questions only the owner can
// answer. Reads source/*.jsonl (the helpers' files) and writes one HTML page; with a
// PDF path it also prints it.
//   node sales-book.js <update-number> [out.pdf]
const fs = require('fs'), path = require('path');
const S = __dirname, SRC = path.join(S, 'source'), OUT = path.join(S, 'book', 'sales-test-book.html');
const release = process.argv[2] || '';
const PARTS = [
  ['SALC', 'Part 1 — Simple Sales: Sales home, Today, Pipeline board, new lead, the one log form, orders, renewals, targets, Sales settings, one firm record'],
  ['SALA', 'Part 2 — Leads, deals, enquiries and costing'],
  ['SALB', 'Part 3 — Quotations, approvals, billing schedule and contracts'],
];
// Status of each finding, by its place (1, 2, …) in that part's findings file.
const STATUS = {
  //  "now" = fixed in this update; "earlier" = fixed in update 69287af or before.
  SALA: { now: [2, 3, 4, 5, 6, 7, 8, 11, 12, 13, 18, 20, 21, 22, 24, 25, 26, 27, 33],
          earlier: [1, 9, 10, 14, 15, 16, 17, 19, 23, 28, 29, 30, 31, 32, 34] },
  SALB: { now: [4, 5, 6, 7, 8, 9, 10, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24], earlier: [1, 2, 3, 25], ask: [11] },
  SALC: { earlier: Array.from({ length: 23 }, (_, i) => i + 1) },
};
// Questions that this update has answered (they are no longer asked).
const ANSWERED = {
  'EC-SALC-043': 'Done: each office lists only its own people, and saving someone of another office is refused.',
  'EC-SALA-005': 'Your answer: Saturday is a working day, and it stays a setting (Sales settings → weekly off). Nothing to change.',
  'EC-SALC-050': 'Your answer: the full-year target. Done — "This financial year" now counts the whole year\'s target (and a quarter or month its whole target).',
  'EC-SALA-003': 'Done: a negative lead value is refused, on the new-lead form and on edit.',
  'EC-SALA-011': 'Done: the customer made from a lead gets the lead\'s branch, and the enquiry the business unit (asked on the convert screen when the lead has none).',
  'EC-SALC-088': 'Done for the branch (as EC-SALA-011). Payment terms are still entered on the customer\'s record.',
  'EC-SALA-022': 'Done: a deal\'s approval goes only to people who can open Sales leads and work for its branch; a rule for a role without Sales access is refused.',
  'EC-SALA-036': 'Done: two Won stages, or a Won stage first, is flagged and cannot be made the default; new deals always open on an open stage.',
  'EC-SALB-052': 'Done the way docs/03 draws it: only a Draft or a Sent quotation is marked lost by hand; the screen says what to do with the others.',
  'EC-SALB-053': 'Done: an accepted quotation is final and is not revised (the won order stays with Accounts).',
  'EC-SALB-060': 'Done: once sent, documents can no longer be removed; only a master can still add one (e.g. the client\'s PO).',
  'EC-SALB-011': 'Done: the subject box takes up to 255 letters and says so; a longer one is refused with its length instead of being cut.',
};
const personas = JSON.parse(fs.readFileSync(path.join(S, 'personas.json'), 'utf8'));
const who = (p) => { const u = personas[p] && personas[p][0]; return u ? `${u.name} (login ${u.login})` : (p === 'MASTER_ADMIN' ? 'your Master Admin login' : String(p || '').replace(/_/g, ' ').toLowerCase()); };
const read = (f) => fs.existsSync(f) ? fs.readFileSync(f, 'utf8').split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const str = (v) => Array.isArray(v) ? v.join('; ') : (v == null ? '' : typeof v === 'object' ? JSON.stringify(v) : String(v));
const LABEL = { now: ['Fixed in this update', 'st-now'], earlier: ['Fixed in update 69287af or before', 'st-earlier'], ask: ['Waiting for your decision', 'st-ask'], open: ['Not fixed yet — next round', 'st-open'] };

const parts = PARTS.map(([code, name]) => {
  const cases = read(path.join(SRC, code + '.jsonl'));
  const st = STATUS[code] || {};
  const findings = read(path.join(SRC, code + '-findings.jsonl')).map((f, i) => {
    const n = i + 1; const s = (st.now || []).includes(n) ? 'now' : (st.earlier || []).includes(n) ? 'earlier' : (st.ask || []).includes(n) ? 'ask' : 'open';
    return { ...f, n, status: s };
  });
  return { code, name, cases, findings };
});
const allF = parts.flatMap(p => p.findings);
const count = (s) => allF.filter(f => f.status === s).length;
const sevCount = (sev, s) => allF.filter(f => f.severity === sev && (!s || f.status === s)).length;
const questions = parts.flatMap(p => p.cases.filter(c => str(c.question).trim() && !ANSWERED[c.id]).map(c => ({ id: c.id, part: p.code, title: str(c.title), q: str(c.question) })));
const totalCases = parts.reduce((a, p) => a + p.cases.length, 0);

const caseHtml = (c, p) => {
  const fs_ = p.findings.filter(f => f.case === c.id);
  const steps = (Array.isArray(c.steps) ? c.steps : []).map((s, i) => `<tr><td class="n">${i + 1}</td><td>${esc(str(s.do ?? s))}</td><td>${esc(str(s.expect ?? ''))}</td><td class="tick">☐</td></tr>`).join('');
  return `<section class="case" id="${esc(c.id)}">
  <div class="ch"><span class="cid">${esc(c.id)}</span><span class="kind">${esc(str(c.kind))}</span><h3>${esc(str(c.title))}</h3></div>
  <div class="meta"><b>Who:</b> ${esc(who(c.persona))} · <b>Screen:</b> ${esc(str(c.screen))}${c.buttons && c.buttons.length ? ' · <b>Buttons:</b> ' + esc(str(c.buttons)) : ''}</div>
  ${c.why ? `<p class="why"><b>Why it matters:</b> ${esc(str(c.why))}</p>` : ''}
  ${c.before ? `<p class="before"><b>Before you start:</b> ${esc(str(c.before))}</p>` : ''}
  <table class="steps"><thead><tr><th class="n">#</th><th>Do this</th><th>You should see</th><th class="tick">OK?</th></tr></thead><tbody>${steps}</tbody></table>
  ${fs_.map(f => `<p class="fnd ${LABEL[f.status][1]}"><b>${LABEL[f.status][0]}:</b> ${esc(f.what)}</p>`).join('')}
  ${str(c.question).trim() ? `<p class="q"><b>${ANSWERED[c.id] ? 'Answered' : 'Question for you'}:</b> ${esc(ANSWERED[c.id] || str(c.question))}</p>` : ''}
</section>`;
};

const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Sales Test Book</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--ink:#1e293b;--mut:#64748b;--line:#cbd5e1;--brand:#0f5c5c;--now:#047857;--nowbg:#ecfdf5;--ear:#1d4ed8;--earbg:#eff6ff;--ask:#b45309;--askbg:#fffbeb;--opn:#b91c1c;--opnbg:#fef2f2}
body{font:10.5px/1.45 Arial,Helvetica,sans-serif;color:var(--ink);margin:0;background:#fff}
.page{padding:0 4px}h1{font-size:22px;color:var(--brand);margin:0 0 4px}h2{font-size:15px;color:var(--brand);margin:18px 0 6px;border-bottom:2px solid var(--brand);padding-bottom:3px}
.cover{page-break-after:always}.cover p{font-size:12px;max-width:70ch}
table{border-collapse:collapse;width:100%}th,td{border:1px solid var(--line);padding:4px 6px;vertical-align:top;text-align:left}th{background:#f1f5f9}
.sum td.n,.sum th.n{text-align:right;width:70px}
.case{border:1px solid var(--line);border-radius:6px;padding:8px 10px;margin:0 0 10px;page-break-inside:avoid}
.ch{display:flex;gap:8px;align-items:baseline;flex-wrap:wrap}.ch h3{font-size:12.5px;margin:0;flex:1 1 300px}
.cid{font-weight:700;color:var(--brand)}.kind{font-size:9px;border:1px solid var(--line);border-radius:9px;padding:0 6px;color:var(--mut)}
.meta{color:var(--mut);margin:3px 0}.why,.before{margin:3px 0}
.steps td.n,.steps th.n{width:18px;text-align:center}.steps .tick{width:34px;text-align:center;font-size:13px}
.fnd{margin:5px 0 0;padding:4px 7px;border-radius:4px}.st-now{background:var(--nowbg);color:var(--now)}.st-earlier{background:var(--earbg);color:var(--ear)}
.st-ask{background:var(--askbg);color:var(--ask)}.st-open{background:var(--opnbg);color:var(--opn)}
.q{margin:5px 0 0;padding:4px 7px;border-left:3px solid var(--ask);background:var(--askbg)}
.pill{display:inline-block;border-radius:9px;padding:0 7px;font-size:9.5px;font-weight:700}
.partname{page-break-before:always}.fl td:first-child{white-space:nowrap}
@media print{.case{break-inside:avoid}}
</style></head><body><div class="page">
<div class="cover">
<h1>Sales — detailed edge-case test book</h1>
<p><b>${totalCases} test cases</b> in three parts, with the exact figures to type and what each screen must show.
Written for the test copy (opstest) with dummy data only${release ? `, update <b>${esc(release)}</b>` : ''}, 19 Nov 2026.</p>
<h2>How to use this book</h2>
<p>1. Use the test copy only, never the live site. 2. Each case says <b>who</b> to log in as, what to set up <b>before you start</b>, then each step: <b>Do this</b> → <b>You should see</b>. Tick ☐ when it matches. 3. Where a case shows a coloured box, it found a problem: <span class="pill st-now">green</span> fixed in this update (check it now works), <span class="pill st-earlier">blue</span> fixed earlier, <span class="pill st-ask">amber</span> waiting for your decision, <span class="pill st-open">red</span> not fixed yet. In a case with a green box, a step that says “Seen: …” describes the old, wrong behaviour; what you should see now is the “Correct: …” part (or the plain rule in the green box). 4. Dates in the cases are worked out for Thursday 19 Nov 2026; on another day count the same way. 5. No passwords are written here; use the test logins you already have.</p>
<h2>What the testing found</h2>
<table class="sum"><thead><tr><th>Part</th><th class="n">Cases</th><th class="n">Problems found</th><th class="n">Fixed now</th><th class="n">Fixed earlier</th><th class="n">Your decision</th><th class="n">Not yet</th></tr></thead><tbody>
${parts.map(p => `<tr><td>${esc(p.name)}</td><td class="n">${p.cases.length}</td><td class="n">${p.findings.length}</td>${['now', 'earlier', 'ask', 'open'].map(s => `<td class="n">${p.findings.filter(f => f.status === s).length}</td>`).join('')}</tr>`).join('')}
<tr><th>All Sales</th><th class="n">${totalCases}</th><th class="n">${allF.length}</th><th class="n">${count('now')}</th><th class="n">${count('earlier')}</th><th class="n">${count('ask')}</th><th class="n">${count('open')}</th></tr>
</tbody></table>
<p>Serious (“high”) problems: ${sevCount('high')} found, ${sevCount('high', 'now') + sevCount('high', 'earlier')} fixed. The ones not yet fixed are listed in the table below so nothing is lost.</p>
</div>
<h2>Every problem found, and where it stands</h2>
<table class="fl"><thead><tr><th>Case</th><th>Level</th><th>What was wrong</th><th>Status</th></tr></thead><tbody>
${allF.map(f => `<tr><td><a href="#${esc(f.case)}">${esc(f.case)}</a></td><td>${esc(f.severity)}</td><td>${esc(f.what)}</td><td><span class="pill ${LABEL[f.status][1]}">${LABEL[f.status][0]}</span></td></tr>`).join('')}
</tbody></table>
<h2>Questions only you can answer (${questions.length})</h2>
<p>Nothing was changed for these. Each says what it affects.</p>
<table class="fl"><thead><tr><th>Case</th><th>Question and what it affects</th></tr></thead><tbody>
${questions.map(q => `<tr><td><a href="#${esc(q.id)}">${esc(q.id)}</a></td><td>${esc(q.q)}</td></tr>`).join('')}
</tbody></table>
${parts.map(p => `<h2 class="partname">${esc(p.name)} — ${p.cases.length} cases</h2>${p.cases.map(c => caseHtml(c, p)).join('')}`).join('')}
</div></body></html>`;
fs.writeFileSync(OUT, html);
console.log('cases', totalCases, 'findings', allF.length, 'fixed now', count('now'), 'earlier', count('earlier'), 'ask', count('ask'), 'open', count('open'), 'questions', questions.length);
if (process.argv[3]) {
  const { chromium } = require('playwright');
  (async () => { const b = await chromium.launch(); const pg = await b.newPage(); await pg.goto('file://' + OUT);
    await pg.pdf({ path: process.argv[3], format: 'A4', printBackground: true, margin: { top: '12mm', bottom: '12mm', left: '10mm', right: '10mm' },
      displayHeaderFooter: true, headerTemplate: '<span></span>',
      footerTemplate: '<div style="font-size:8px;width:100%;text-align:center;color:#64748b">Sales test book · page <span class="pageNumber"></span> of <span class="totalPages"></span></div>' });
    await b.close(); console.log('pdf', process.argv[3]); })();
}
