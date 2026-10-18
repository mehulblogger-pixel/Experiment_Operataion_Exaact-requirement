#!/usr/bin/env python3
"""Can each role CHANGE the books? Drive the real write gate and record it.

booksui.php:225 guards every writing route with
    ops_require($canIssue || can('data.credit'), 'You cannot change the books.')
so a GET of a writing route is a safe, non-destructive probe of that gate:
the form renders only if the role passed it. Nothing is posted; no record
is created. Runs against a throwaway copy of a UAT database.
"""
import re, subprocess, sys

SP   = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = 'http://127.0.0.1:8793'
USERS = [('admin','Master Admin'), ('aud.director','Business Director'),
         ('aud.branch','Branch Manager'), ('aud.manager','Operation Manager'),
         ('uat.coord','Coordinator'), ('uat.inspector','Inspector'),
         ('uat.finance','Finance')]
READ  = ['invoices','receipts','receivables','to-bill']
WRITE = ['invoice-new','receipt-new','credit-note-new']

def sh(*a): return subprocess.run(a, capture_output=True).stdout.decode('utf-8','replace')

def login(jar, user):
    subprocess.run(['rm','-f',jar])
    page = sh('curl','-s','-c',jar,f'{BASE}/login')
    m = re.search(r'name="_csrf" value="([^"]+)"', page)
    if not m: return False
    sh('curl','-s','-b',jar,'-c',jar,'-X','POST','-d',
       f'_csrf={m.group(1)}&username={user}&password=Pw12345678&viewport=1280',
       '-o','/dev/null', f'{BASE}/login')
    return True

def verdict(jar, route):
    out = sh('curl','-s','-b',jar,'-w','\n#CODE#%{http_code}', f'{BASE}/{route}')
    code = out.rsplit('#CODE#',1)[-1].strip()
    body = out.rsplit('\n#CODE#',1)[0]
    if 'cannot change the books' in body: return 'refused-write'
    if code == '403': return 'refused'
    if code == '302':
        # follow it once: ops_require() flashes then redirects
        out2 = sh('curl','-s','-b',jar,'-L','-w','\n#CODE#%{http_code}', f'{BASE}/{route}')
        if 'cannot change the books' in out2: return 'refused-write'
        return 'redirect'
    if code == '200' and '<h1' in body: return 'OPEN'
    return code

print(f"{'role':<20}" + ''.join(f'{r:<16}' for r in READ+WRITE))
print('-'*(20+16*len(READ+WRITE)))
for user, label in USERS:
    jar = f'{SP}/mw_{user}.ck'
    if not login(jar, user):
        print(f'{label:<20} !! cannot sign in'); continue
    row = [verdict(jar, r) for r in READ+WRITE]
    print(f'{label:<20}' + ''.join(f'{v:<16}' for v in row))
