#!/usr/bin/env python3
"""ERRATUM TOOL — how incomplete is evidence/routes-dispatched.txt?

Phase A built that list and every later count rested on it: "565 dispatched
routes", "256 screens that render", "194 reached / 62 not", "200 routes with no
module gate".

Then D7 found /analytics, /analytics-kpis, /analytics-quality and
/analytics-drill — four screens that serve HTTP 200 with real headings
("Analytics & performance", "KPI library", "Data quality", "Breakdown") and are
ABSENT from the list. They are dispatched from lib/tapi_dash.php, which the
original parse did not read.

So the universe was under-counted and this measures by how much. It parses
route literals out of EVERY lib file and index.php, not just ops.php, and
diffs against the committed list.

A literal is not proof a route is dispatched — some are link targets or data —
so the output is a CANDIDATE list to probe, and the probe is what settles it.
"""
import os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(os.path.dirname(HERE)))
APP = os.path.join(ROOT, 'phpapp')

committed = set()
with open(os.path.join(HERE, 'routes-dispatched.txt')) as f:
    for line in f:
        r = line.strip()
        if r and r != 'route':
            committed.add(r)

# How routes are tested in this codebase:
#   $route === 'x'            in_array($route, ['x','y'], true)
#   case 'x':                 'x' => 'handler'
PATTERNS = [
    re.compile(r"\$route\s*===\s*'([a-z0-9][a-z0-9/-]*)'"),
    re.compile(r"\$base\s*===\s*'([a-z0-9][a-z0-9/-]*)'"),
    re.compile(r"in_array\(\s*\$(?:route|base)\s*,\s*\[([^\]]+)\]"),
    re.compile(r"case\s+'([a-z0-9][a-z0-9/-]*)'\s*:"),
]
INNER = re.compile(r"'([a-z0-9][a-z0-9/-]*)'")

found = {}
files = [os.path.join(APP, 'index.php')]
files += sorted(os.path.join(APP, 'lib', f) for f in os.listdir(os.path.join(APP, 'lib'))
                if f.endswith('.php'))

for path in files:
    try:
        code = open(path, encoding='utf-8', errors='replace').read()
    except OSError:
        continue
    base = os.path.basename(path)
    for pat in PATTERNS:
        for m in pat.finditer(code):
            g = m.group(1)
            names = INNER.findall(g) if ',' in g or "'" in g else [g]
            for n in names:
                found.setdefault(n, set()).add(base)

missing = sorted(set(found) - committed)
extra = sorted(committed - set(found))

print(f'routes in the committed list      : {len(committed)}')
print(f'route literals found across lib/   : {len(found)}')
print(f'  NOT in the committed list        : {len(missing)}')
print(f'  in the list but not re-found     : {len(extra)}  (the parse is narrower than Phase A\'s)')

print('\n--- candidates the committed list is missing, by file that dispatches them ---')
byfile = {}
for r in missing:
    for f in found[r]:
        byfile.setdefault(f, []).append(r)
for f in sorted(byfile, key=lambda x: -len(byfile[x])):
    rs = sorted(byfile[f])
    print(f'  {f:<22} {len(rs):>3}  {", ".join(rs[:10])}{" …" if len(rs) > 10 else ""}')

with open(os.path.join(HERE, 'route-candidates-missing.txt'), 'w') as out:
    for r in missing:
        out.write(r + '\n')
print(f'\nwritten to route-candidates-missing.txt — probe these to settle which are real')
