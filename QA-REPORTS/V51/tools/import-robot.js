#!/usr/bin/env node
// Feed the test-book robot's results into the tracker (only what changed since the last import).
// Usage: node import-robot.js <resultsDir> [--since <iso>]
const fs = require('fs'), path = require('path');
const qa = require('./qa');
const dir = process.argv[2];
const since = process.argv.indexOf('--since') > 0 ? process.argv[process.argv.indexOf('--since') + 1] : '';
const { items } = qa.fold();

// Robot verdict → the owner's status labels.
function statusOf(r) {
  if (r.status === 'PASSED') return 'COMPLETED – PASS';
  if (r.status === 'FAILED') return 'COMPLETED – FAIL';
  if (r.status === 'NEEDS_YOU') {
    // "needs" with rule:false = needs the real world (payment page, inbox, print) → a tool limitation;
    // otherwise it needs the owner's decision on what is right.
    return r.steps.some(s => s.status === 'needs' && s.rule === false) ? 'CANNOT DO – AI / TOOL LIMITATION' : 'OWNER ACCEPTANCE PENDING';
  }
  if (r.status === 'OUT_OF_DATE') return 'BLOCKED';        // the screen changed on purpose; the test's wording must be updated first
  return 'BLOCKED';                                          // INCOMPLETE — stopped early
}
const word = { pass: 'PASS', fail: 'FAIL', needs: 'NEEDS YOU', outdated: 'SCREEN CHANGED ON PURPOSE', notrun: 'NOT RUN' };
let n = 0;
for (const f of fs.readdirSync(dir).filter(f => f.endsWith('.json'))) {
  const r = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
  if (since && r.ranAt < since) continue;
  const st = statusOf(r), prev = items['test|' + r.id];
  if (prev && prev.status === st && prev.ranAt === r.ranAt) continue;
  const actual = r.steps.map(s => `${s.n}. ${word[s.status] || s.status} — ${s.observed || ''}${s.reason ? ' [why: ' + s.reason + ']' : ''}${s.proposed ? ' [proposed: ' + s.proposed + ']' : ''}${s.suggest ? ' [new wording: ' + s.suggest + ']' : ''}`).join('\n');
  const shots = r.steps.filter(s => s.shot && s.status !== 'pass').map(s => s.shot);
  qa.append('test', { id: r.id, by: 'A04', status: st, method: 'Browser (robot, Playwright) + database checks where the step needs them', executed: ['COMPLETED – PASS', 'COMPLETED – FAIL'].includes(st), ranAt: r.ranAt,
    actual: actual.length > 30000 ? actual.slice(0, 30000) + ' …' : actual, retest: '',
    evidence: [r.finalShot, ...shots].filter(Boolean).map(s => 'robot shots/' + s).join('\n') + (r.crash ? '\nStopped early: ' + r.crash : ''),
    note: st === 'BLOCKED' && r.status === 'OUT_OF_DATE' ? 'Blocked: the screen was changed on purpose after the test was written — the test wording must be updated (suggested wording recorded in Actual result).' : '' });
  shots.forEach((s, i) => qa.append('evidence', { id: `EV-${r.id}-${i + 1}`, by: 'A04', ref: r.id, type: 'screenshot', file: 'robot shots/' + s, what: 'Step not passed — what the screen showed' }));
  n++;
}
console.log('imported', n);
