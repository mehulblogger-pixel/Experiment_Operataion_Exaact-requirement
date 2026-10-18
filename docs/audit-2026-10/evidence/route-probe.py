#!/usr/bin/env python3
"""GET every served route as an administrator and record what comes back.

Two things this script has to get right, both learned the hard way:
  · it must not log itself out (the first run hit /logout alphabetically and
    every route after it reported "redirect to login");
  · it must not fire destructive actions. Routes whose names say they delete,
    erase, restore or reset are NOT probed at all and are reported as such.
"""
import re, subprocess, sys, time

SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = 'http://127.0.0.1:8790'
JAR = f'{SP}/ckP.txt'

SKIP = re.compile(r'(^|-)(logout|delete|erase|destroy|purge|reset|restore|wipe|drop)(-|$)')

def sh(*a):
    #  BYTES, not text. Some routes return a PDF or an image, and decoding one
    #  as UTF-8 killed the whole run three quarters of the way through.
    out = subprocess.run(a, capture_output=True).stdout
    return out.decode('utf-8', errors='replace')

def login():
    subprocess.run(['rm', '-f', JAR])
    page = sh('curl', '-s', '-c', JAR, f'{BASE}/login')
    m = re.search(r'name="_csrf" value="([^"]+)"', page)
    sh('curl', '-s', '-b', JAR, '-c', JAR, '-X', 'POST',
       '-d', f'_csrf={m.group(1)}&username=admin&password=Pw12345678&viewport=1280',
       '-o', '/dev/null', f'{BASE}/login')

def get(route):
    out = sh('curl', '-s', '-b', JAR, '-w', '\n@@%{http_code}', f'{BASE}/{route}')
    code = out.rsplit('@@', 1)[-1].strip()
    html = out.rsplit('\n@@', 1)[0]
    return code, html

login()
routes = [r.strip() for r in open(f'{SP}/routes_all.txt') if r.strip()]
rows, skipped = [], []
# Prove the session is live before trusting a single result.
assert get('admin')[0] == '200', 'not signed in — fix the login before probing'
for i, r in enumerate(routes):
    if SKIP.search(r):
        skipped.append(r); continue
    code, html = get(r)
    # logged out? re-authenticate and retry once.
    if code == '302' and 'login' not in r:
        probe, _ = get('admin')
        if probe == '302':
            login(); code, html = get(r)
    has_h1 = '<h1' in html
    crumbs = 'class="crumbs"' in html
    if code == '200' and (has_h1 or crumbs): kind = 'SCREEN'
    elif code == '200':                      kind = 'PARTIAL'      # renders, but no heading
    elif code == '302':                      kind = 'REDIRECT'     # action, or needs a record id
    elif code == '403':                      kind = 'FORBIDDEN'
    elif code == '404':                      kind = 'NOTFOUND'
    else:                                    kind = 'HTTP' + code
    h1 = ''
    m = re.search(r'<h1[^>]*>(.*?)</h1>', html, re.S)
    if m: h1 = re.sub(r'\s+', ' ', re.sub(r'<[^>]*>', '', m.group(1))).strip()[:70]
    rows.append((r, code, kind, h1))
    if i % 100 == 0: print(f'  ... {i}/{len(routes)}', file=sys.stderr)

with open(f'{SP}/probe.tsv', 'w') as f:
    for r in rows: f.write('\t'.join(r) + '\n')
with open(f'{SP}/probe_skipped.txt', 'w') as f:
    f.write('\n'.join(skipped))

import collections
c = collections.Counter(r[2] for r in rows)
print('probed :', len(rows))
print('skipped:', len(skipped), '(destructive by name — not fired)')
for k, n in c.most_common(): print(f'  {k:10} {n}')
