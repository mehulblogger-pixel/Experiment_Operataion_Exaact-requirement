// Builds one Word document per Sales module + one Excel workbook from the robot's results.
// Usage: node build.js <resultsDir> <outDir> <versionLabel>
const fs = require('fs'), path = require('path');
const D = require('/opt/node22/lib/node_modules/docx');
const ExcelJS = require('exceljs');
const R = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad/robot';
const [resDir, outDir, version] = [process.argv[2], process.argv[3], process.argv[4] || 'v51.1'];
fs.mkdirSync(outDir, { recursive: true });

const book = JSON.parse(fs.readFileSync(path.join(R, 'harness/book.json'), 'utf8'));
const cases = book.cases.filter(c => c.area === 'Sales');
const res = {};
for (const f of fs.readdirSync(resDir)) if (f.endsWith('.json')) { const d = JSON.parse(fs.readFileSync(path.join(resDir, f), 'utf8')); res[d.id] = d; }
// Audit overlay: the central tracker holds the triaged verdict (script errors rerun, owner decisions, defects).
const QA = require('/home/user/Experiment_Operataion_Exaact-requirement/QA-REPORTS/V51/tools/qa');
const { items: TRK } = QA.fold();
const DEFS = {}; for (const [k, v] of Object.entries(TRK)) if (k.startsWith('defect|')) String(v.test || '').split(/[,; ]+/).filter(Boolean).forEach(t => (DEFS[t] = DEFS[t] || []).push(v));
const aud = id => (TRK['test|' + id] || {}).status || 'NOT STARTED';
const A_KEY = st => /PASS/.test(st) && !/FAIL/.test(st) ? 'PASS' : /FAIL/.test(st) ? 'FAIL' : /BLOCKED/.test(st) ? 'BLOCKED' : /OWNER DECISION/.test(st) ? 'OWNER' : /CANNOT/.test(st) ? 'CANNOT' : 'NOT';
const A_FILL = { PASS: 'E8F5E9', FAIL: 'FDECEA', BLOCKED: 'E8F0FE', OWNER: 'FFF4E5', CANNOT: 'EEEEEE', NOT: 'FFFFFF' };
const audNote = id => { const t = TRK['test|' + id] || {}; const d = (DEFS[id] || []).map(x => `${x.id} (${x.severity}): ${x.description || ''}`); return [t.note, ...d].filter(Boolean).join('\n'); };
const PERS = {}; (Array.isArray(book.personas) ? book.personas : []).forEach(p => PERS[p.code] = p.name);
const personaName = k => PERS[k] || (k ? k.replace(/_/g, ' ').toLowerCase().replace(/^./, x => x.toUpperCase()) : '');
const today = '15 Nov 2026';

const T_WORD = { PASSED: 'Passed', FAILED: 'Failed', NEEDS_YOU: 'Needs you', OUT_OF_DATE: 'Test wording out of date', NOT_RUN: 'Not run' };
const S_WORD = { pass: '✓ Passed', fail: '✗ Failed', needs: '? Needs you', outdated: '↻ Screen changed on purpose' };
const S_FILL = { pass: 'E8F5E9', fail: 'FDECEA', needs: 'FFF4E5', outdated: 'E8F0FE' };
const T_FILL = { PASSED: 'E8F5E9', FAILED: 'FDECEA', NEEDS_YOU: 'FFF4E5', OUT_OF_DATE: 'E8F0FE', NOT_RUN: 'EEEEEE' };

// JPEG / PNG size, to place screenshots at their real proportions.
function imgSize(buf) {
  if (buf[0] === 0x89) return { w: buf.readUInt32BE(16), h: buf.readUInt32BE(20) };
  let i = 2;
  while (i < buf.length) {
    if (buf[i] !== 0xFF) { i++; continue; }
    const m = buf[i + 1], len = buf.readUInt16BE(i + 2);
    if (m >= 0xC0 && m <= 0xCF && m !== 0xC4 && m !== 0xC8 && m !== 0xCC) return { h: buf.readUInt16BE(i + 5), w: buf.readUInt16BE(i + 7) };
    i += 2 + len;
  }
  return { w: 1366, h: 900 };
}

const P = (text, o = {}) => new D.Paragraph({ spacing: { after: o.after ?? 80 }, heading: o.heading, children: [].concat(text).map(t => typeof t === 'string' ? new D.TextRun({ text: t, bold: o.bold, italics: o.italics, size: o.size, color: o.color }) : t) });
const cell = (text, o = {}) => new D.TableCell({
  width: o.w ? { size: Math.round(o.w * TW / 100), type: D.WidthType.DXA } : undefined,
  shading: o.fill ? { type: D.ShadingType.CLEAR, fill: o.fill, color: 'auto' } : undefined,
  margins: { top: 60, bottom: 60, left: 80, right: 80 },
  children: String(text ?? '').split('\n').map(l => new D.Paragraph({ children: [new D.TextRun({ text: l, bold: o.bold, size: o.size || 18 })] })),
});
const TW = 15398; // A4 landscape text width in twips (16838 - 2 x 720)
const table = (rows, widths) => { const n = rows[0].length; const w = widths || Array(n).fill(100 / n); return new D.Table({ width: { size: TW, type: D.WidthType.DXA }, columnWidths: w.map(x => Math.round(x * TW / 100)), layout: D.TableLayoutType.FIXED, rows: rows.map((r, ri) => new D.TableRow({ tableHeader: ri === 0, children: r.map((c, ci) => (c instanceof D.TableCell) ? c : cell(c, { w: w[ci], bold: ri === 0, fill: ri === 0 ? 'F1F3F4' : undefined })) })) }); };

function shotPara(file) {
  const fp = path.join(R, 'shots', file);
  if (!file || !fs.existsSync(fp)) return null;
  const buf = fs.readFileSync(fp), { w, h } = imgSize(buf), W = 560;
  return new D.Paragraph({ spacing: { after: 120 }, children: [new D.ImageRun({ type: /\.png$/i.test(file) ? 'png' : 'jpg', data: buf, transformation: { width: W, height: Math.round(h * W / w) } })] });
}

function stepDetail(s) {
  const bits = [s.observed ? 'Saw: ' + s.observed : ''];
  if (s.reason) bits.push('Why it needs you: ' + s.reason);
  if (s.proposed) bits.push('Proposed answer: ' + s.proposed);
  if (s.suggest) bits.push('Suggested new wording: ' + s.suggest);
  if (s.note) bits.push('Note: ' + s.note);
  return bits.filter(Boolean).join('\n');
}

const modules = [...new Set(cases.map(c => c.module))];
const summary = [];
let idx = 0;
for (const mod of modules) {
  idx++;
  const mc = cases.filter(c => c.module === mod);
  const counts = { PASS: 0, FAIL: 0, BLOCKED: 0, OWNER: 0, CANNOT: 0, NOT: 0 };
  mc.forEach(c => counts[A_KEY(aud(c.id))]++);
  summary.push({ mod, n: mc.length, ...counts });
  const body = [
    P(`Sales — ${mod}`, { heading: D.HeadingLevel.TITLE }),
    P(`Test book with the robot's results and the audit verdict · app ${version} · run ${today} on a private test copy with made-up data (never the test or live site).`, { italics: true, color: '555555' }),
    P('Summary', { heading: D.HeadingLevel.HEADING_1 }),
    table([['Tests', 'Passed', 'Failed', 'Blocked', 'Owner decision', 'Cannot test here'], [mc.length, counts.PASS, counts.FAIL, counts.BLOCKED, counts.OWNER, counts.CANNOT].map(String)]),
    P('The counts are the AUDIT verdict from the central tracker, after triage: robot-script errors were fixed and rerun, and a robot "Failed" or "Needs you" was confirmed by hand before it became a defect. Where the audit verdict differs from the raw robot result, both are shown.', { size: 18, color: '444444' }),
    P(''),
    P('What the results mean: Passed — the robot saw exactly what the step expects. Failed — it saw something else; the screenshot shows what. Needs you — the step has no fixed right answer, or needs the real world (a real payment, an e-mail reaching an inbox, how a print looks); the robot did everything up to that point and proposes an answer. Screen changed on purpose — the screen was changed by a later decision of yours; the new wording to use is suggested.', { size: 18, color: '444444' }),
    table([['Test', 'Title', 'Who', 'Robot result', 'Audit verdict', 'Defect'], ...mc.map(c => { const r = res[c.id] || {}; const st = r.status || 'NOT_RUN'; const a = aud(c.id); return [c.id, c.title, personaName(c.persona), T_WORD[st], new D.TableCell({ shading: { type: D.ShadingType.CLEAR, fill: A_FILL[A_KEY(a)], color: 'auto' }, margins: { top: 60, bottom: 60, left: 80, right: 80 }, children: [new D.Paragraph({ children: [new D.TextRun({ text: a, size: 18, bold: A_KEY(a) !== 'PASS' })] })] }), (DEFS[c.id] || []).map(x => x.id).join(', ')]; })], [10, 42, 13, 11, 14, 10]),
  ];
  for (const c of mc) {
    const r = res[c.id] || { steps: [], status: 'NOT_RUN' };
    body.push(new D.Paragraph({ pageBreakBefore: true, heading: D.HeadingLevel.HEADING_1, children: [new D.TextRun(`${c.id} — ${c.title}`)] }));
    body.push(table([['Audit verdict', 'Robot result', 'Who', 'Company', 'Kind'], [aud(c.id), T_WORD[r.status] || r.status || 'Not run', personaName(c.persona), c.company || '', c.kind || '']]));
    if (audNote(c.id)) body.push(P(['Audit note: ', audNote(c.id)], { size: 18, color: '7A1F00' }));
    if (c.why) body.push(P(['Why: ', c.why], { size: 18 }));
    if (c.before) body.push(P(['Before you start: ', c.before], { size: 18 }));
    const byN = {}; (r.steps || []).forEach(s => byN[s.n] = s);
    const rows = [['#', 'What to do', 'What should happen', 'Robot result', 'What the robot saw']];
    (c.steps || []).forEach((st, i) => {
      const s = byN[i + 1] || {};
      rows.push([String(i + 1), st.do, st.expect, new D.TableCell({ shading: s.status ? { type: D.ShadingType.CLEAR, fill: S_FILL[s.status], color: 'auto' } : undefined, margins: { top: 60, bottom: 60, left: 80, right: 80 }, children: [new D.Paragraph({ children: [new D.TextRun({ text: S_WORD[s.status] || 'Not run', size: 18, bold: s.status && s.status !== 'pass' })] })] }), stepDetail(s)]);
    });
    body.push(table(rows, [4, 28, 26, 12, 30]));
    if (r.crash) body.push(P('The robot stopped early: ' + r.crash, { color: 'B00020', size: 18 }));
    const pics = (r.steps || []).filter(s => s.status !== 'pass' && s.shot);
    for (const s of pics) { const im = shotPara(s.shot); if (im) { body.push(P(`Screenshot — step ${s.n} (${S_WORD[s.status]})`, { bold: true, size: 18 })); body.push(im); } }
  }
  const doc = new D.Document({ creator: 'EXAACT test robot', title: `Sales — ${mod}`, styles: { default: { document: { run: { font: 'Calibri', size: 20 } } } }, sections: [{ properties: { page: { size: { width: 11906, height: 16838, orientation: D.PageOrientation.LANDSCAPE }, margin: { top: 720, bottom: 720, left: 720, right: 720 } } }, children: body }] });
  const fn = `${String(idx).padStart(2, '0')} - V51.1 Sales - ${mod.replace(/[\\/:*?"<>|]/g, '-')}.docx`;
  D.Packer.toBuffer(doc).then(b => fs.writeFileSync(path.join(outDir, fn), b));
}

// One Excel workbook: a summary sheet, one row per test, one row per step.
(async () => {
  const wb = new ExcelJS.Workbook(); wb.creator = 'EXAACT test robot';
  const head = (ws, cols) => { ws.columns = cols; ws.getRow(1).font = { bold: true }; ws.getRow(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF1F3F4' } }; ws.views = [{ state: 'frozen', ySplit: 1 }]; ws.autoFilter = { from: { row: 1, column: 1 }, to: { row: 1, column: cols.length } }; };
  const s1 = wb.addWorksheet('Summary');
  head(s1, [{ header: 'Module', key: 'mod', width: 30 }, { header: 'Tests', key: 'n', width: 8 }, { header: 'Passed', key: 'PASS', width: 9 }, { header: 'Failed', key: 'FAIL', width: 9 }, { header: 'Blocked', key: 'BLOCKED', width: 9 }, { header: 'Owner decision', key: 'OWNER', width: 15 }, { header: 'Cannot test here', key: 'CANNOT', width: 16 }, { header: 'Not started', key: 'NOT', width: 11 }]);
  summary.forEach(r => s1.addRow(r));
  const tot = summary.reduce((a, r) => { for (const k of ['n', 'PASS', 'FAIL', 'BLOCKED', 'OWNER', 'CANNOT', 'NOT']) a[k] = (a[k] || 0) + r[k]; return a; }, { mod: 'All Sales' });
  s1.addRow(tot).font = { bold: true };
  const s2 = wb.addWorksheet('Tests');
  head(s2, [{ header: 'Test', key: 'id', width: 12 }, { header: 'Module', key: 'mod', width: 22 }, { header: 'Title', key: 't', width: 70 }, { header: 'Who', key: 'who', width: 22 }, { header: 'Robot result', key: 'r', width: 22 }, { header: 'Audit verdict', key: 'a', width: 26 }, { header: 'Defect', key: 'def', width: 16 }, { header: 'Audit note', key: 'an', width: 60 }, { header: 'Your result', key: 'mine', width: 14 }, { header: 'Your note', key: 'note', width: 40 }]);
  cases.forEach(c => { const r = res[c.id] || {}; const row = s2.addRow({ id: c.id, mod: c.module, t: c.title, who: personaName(c.persona), r: T_WORD[r.status || 'NOT_RUN'], a: aud(c.id), def: (DEFS[c.id] || []).map(x => x.id).join(', '), an: audNote(c.id) }); row.alignment = { wrapText: true, vertical: 'top' }; row.getCell('a').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF' + A_FILL[A_KEY(aud(c.id))] } }; row.getCell('r').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF' + T_FILL[r.status || 'NOT_RUN'] } }; });
  const s3 = wb.addWorksheet('Steps');
  head(s3, [{ header: 'Test', key: 'id', width: 12 }, { header: 'Module', key: 'mod', width: 20 }, { header: 'Step', key: 'n', width: 6 }, { header: 'What to do', key: 'do', width: 60 }, { header: 'What should happen', key: 'ex', width: 55 }, { header: 'Robot result', key: 'r', width: 22 }, { header: 'What the robot saw', key: 'saw', width: 70 }]);
  cases.forEach(c => { const byN = {}; ((res[c.id] || {}).steps || []).forEach(s => byN[s.n] = s); (c.steps || []).forEach((st, i) => { const s = byN[i + 1] || {}; const row = s3.addRow({ id: c.id, mod: c.module, n: i + 1, do: st.do, ex: st.expect, r: S_WORD[s.status] || 'Not run', saw: stepDetail(s) }); row.alignment = { wrapText: true, vertical: 'top' }; if (s.status) row.getCell('r').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF' + S_FILL[s.status] } }; }); });
  await wb.xlsx.writeFile(path.join(outDir, '00 - V51.1 Sales - all tests, robot results and audit verdicts.xlsx'));
  console.log(JSON.stringify(summary), JSON.stringify(tot));
})();
