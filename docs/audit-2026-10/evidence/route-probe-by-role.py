#!/usr/bin/env python3
"""GET every dispatched route as EACH role, and record what each one gets.

This is the evidence Phase B was missing: the first probe ran as one role, so
"unreachable" meant "unreachable for an administrator" and role-dependent
navigation (the inspector's own rail) was invisible.

Same two safeguards as before: never fire a destructive route, and refuse to
trust a result taken while logged out.
"""
import re, subprocess, sys, json, collections

SP   = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = 'http://127.0.0.1:8791'
SKIP = re.compile(r'(^|-)(logout|delete|erase|destroy|purge|reset|restore|wipe|drop)(-|$)')
ROLES = [('admin','Master Admin'), ('aud.director','Business Director'),
         ('aud.branch','Branch Manager'), ('aud.manager','Operation Manager'),
         ('uat.coord','Coordinator'), ('uat.inspector','Inspector'),
         ('uat.finance','Finance')]

def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')

def login(jar, user):
    subprocess.run(['rm','-f',jar])
    page = sh('curl','-s','-c',jar,f'{BASE}/login')
    m = re.search(r'name="_csrf" value="([^"]+)"', page)
    sh('curl','-s','-b',jar,'-c',jar,'-X','POST',
       '-d', f'_csrf={m.group(1)}&username={user}&password=Pw12345678&viewport=1280',
       '-o','/dev/null', f'{BASE}/login')

def get(jar, route):
    out = sh('curl','-s','-b',jar,'-w','\n@@%{http_code}', f'{BASE}/{route}')
    return out.rsplit('@@',1)[-1].strip(), out.rsplit('\n@@',1)[0]

routes = [r.strip() for r in open(f'{SP}/routes_all.txt') if r.strip() and not SKIP.search(r.strip())]
result = {}

for user, label in ROLES:
    jar = f'{SP}/ck_{user}.txt'
    login(jar, user)
    code, _ = get(jar, '')
    if code not in ('200','302'):
        print(f'!! {user}: cannot sign in ({code})', file=sys.stderr); continue
    rows = {}
    for i, r in enumerate(routes):
        code, html = get(jar, r)
        if code == '302':
            c2, _ = get(jar, 'my-work')          # a screen every signed-in user may open
            if c2 == '302':
                login(jar, user); code, html = get(jar, r)
        if code == '200' and ('<h1' in html or 'class="crumbs"' in html): k = 'SCREEN'
        elif code == '200':  k = 'PARTIAL'
        elif code == '302':  k = 'REDIRECT'
        elif code == '403':  k = 'FORBIDDEN'
        elif code == '404':  k = 'NOTFOUND'
        else:                k = 'HTTP' + code
        rows[r] = k
        if i % 150 == 0: print(f'  {user} {i}/{len(routes)}', file=sys.stderr)
    result[user] = {'label': label, 'rows': rows}
    c = collections.Counter(rows.values())
    print(f'{label:20} ' + '  '.join(f'{k}={n}' for k, n in sorted(c.items())))

json.dump(result, open(f'{SP}/roleprobe.json','w'))
