#!/usr/bin/env python3
"""Count the rows of SALES-END-TO-END-TEST-MATRIX.md by result, per section.
Run from the repository root:  python3 docs/audit-2026-10/evidence/sales-audit/count_matrix.py
A row's result is read from its 'Today' column: PASS, **FAIL**, ◐ (partial),
or READ / UNTESTED."""
import re, sys, pathlib
p = pathlib.Path(__file__).resolve().parents[2] / 'sales' / 'SALES-END-TO-END-TEST-MATRIX.md'
sec, rows = None, {}
for line in p.read_text().splitlines():
    m = re.match(r'^## \d+\. (.+?) \(', line)
    if m: sec = m.group(1); rows.setdefault(sec, []); continue
    if line.startswith('## 14'): sec = None
    if sec and re.match(r'^\| TM-', line):
        cells = [c.strip() for c in line.strip('|').split('|')]
        today = cells[-2]
        k = ('PASS' if today.startswith('PASS') else 'FAIL' if today.startswith('**FAIL**')
             else 'PART' if today.startswith('◐') else 'READ')
        rows[sec].append(k)
tot = {'PASS':0,'FAIL':0,'PART':0,'READ':0}
print('| | Rows | PASS | FAIL | ◐ partial | READ / UNTESTED |\n|---|---|---|---|---|---|')
for s, ks in rows.items():
    c = {k: ks.count(k) for k in tot}
    for k in tot: tot[k] += c[k]
    print(f"| {s} | {len(ks)} | {c['PASS']} | {c['FAIL']} | {c['PART']} | {c['READ']} |")
print(f"| **Total** | **{sum(tot.values())}** | **{tot['PASS']}** | **{tot['FAIL']}** | **{tot['PART']}** | **{tot['READ']}** |")
