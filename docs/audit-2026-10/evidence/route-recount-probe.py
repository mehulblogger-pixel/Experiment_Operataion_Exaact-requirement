#!/usr/bin/env python3
"""Settle the erratum: which of the 355 missing candidates are real routes?

A literal in a switch is not proof of a route — idems.php yields 'checkbox',
'date', 'empty'; ai.php yields 'openai', 'gemini'; partnerimport.php yields
'city', 'pincode'. Those are field types, provider names and column names.

So each candidate is REQUESTED as an administrator and classified exactly as
the Phase A probe classified routes, which keeps the two counts comparable:

  SCREEN    200 with an <h1> or a breadcrumb — a screen a person can use
  PARTIAL   200 without either — a fragment, JSON, a file
  REDIRECT  302 — a refusal or a detail screen wanting ?id=
  FORBIDDEN 403
  NOTFOUND  404 — NOT a route; the literal was something else
  
Destructive names are skipped, as in Phase A.
"""
import json, os, re, subprocess, sys
from collections import Counter

HERE = os.path.dirname(os.path.abspath(__file__))
SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = os.environ.get('EXAACT_BASE', 'http://127.0.0.1:8811')
SKIP = re.compile(r'(^|[-/])(logout|delete|erase|destroy|purge|reset|restore|wipe|drop|del)([-/]|$)')


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    return m.group(1) if m else ''


jar = f'{SP}/rc.ck'
subprocess.run(['rm', '-f', jar])
sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
   f'_csrf={csrf(sh("curl", "-s", "-c", jar, f"{BASE}/login"))}'
   f'&username=admin&password=Pw12345678&viewport=1280', '-o', '/dev/null', f'{BASE}/login')
if sh('curl', '-s', '-b', jar, '-o', '/dev/null', '-w', '%{http_code}', f'{BASE}/admin').strip() != '200':
    print('cannot sign in', file=sys.stderr)
    sys.exit(1)

cands = [r.strip() for r in open(os.path.join(HERE, 'route-candidates-missing.txt')) if r.strip()]
skipped = [r for r in cands if SKIP.search(r)]
cands = [r for r in cands if not SKIP.search(r)]

rows = {}
for i, r in enumerate(cands):
    out = sh('curl', '-s', '-b', jar, '-w', '\n#C#%{http_code}', f'{BASE}/{r}')
    code = out.rsplit('#C#', 1)[-1].strip()
    html = out.rsplit('\n#C#', 1)[0]
    if code == '200':
        k = 'SCREEN' if ('<h1' in html or 'class="crumbs"' in html) else 'PARTIAL'
    else:
        k = {'302': 'REDIRECT', '403': 'FORBIDDEN', '404': 'NOTFOUND'}.get(code, 'HTTP' + code)
    h1 = re.search(r'<h1[^>]*>([^<]{0,60})', html)
    rows[r] = {'class': k, 'heading': (h1.group(1).strip() if h1 else '')}
    if i % 60 == 0:
        print(f'  {i}/{len(cands)}', file=sys.stderr)

c = Counter(v['class'] for v in rows.values())
print(f'candidates probed : {len(cands)}   (skipped as destructive: {len(skipped)})')
for k, n in sorted(c.items(), key=lambda x: -x[1]):
    print(f'  {k:<10} {n}')
real = sum(n for k, n in c.items() if k != 'NOTFOUND')
print(f'\nREAL routes the committed list was missing : {real}')
print(f'  of which render as a usable SCREEN       : {c.get("SCREEN", 0)}')
print(f'not routes at all (404 — field types, provider names, columns) : {c.get("NOTFOUND", 0)}')

# The external-facing families matter most: they were never probed at all.
fams = {'portal/': 'client portal', 'pro/': 'professional portal',
        'vendor/': 'vendor portal', 'analytics': 'analytics (TAPI)'}
print('\n--- by family ---')
for pre, label in fams.items():
    fam = {r: v for r, v in rows.items() if r.startswith(pre)}
    if not fam:
        continue
    fc = Counter(v['class'] for v in fam.values())
    print(f'  {label:<22} {len(fam):>3} probed   ' +
          '  '.join(f'{k}={n}' for k, n in sorted(fc.items(), key=lambda x: -x[1])))

with open(os.path.join(HERE, 'route-recount-probe.json'), 'w') as f:
    json.dump({'probed': rows, 'skipped_destructive': skipped}, f, indent=1)
with open(os.path.join(HERE, 'route-recount-probe.tsv'), 'w') as f:
    f.write('route\tclass\theading\n')
    for r in sorted(rows):
        f.write(f'{r}\t{rows[r]["class"]}\t{rows[r]["heading"]}\n')
print('\nwritten to route-recount-probe.json / .tsv')
