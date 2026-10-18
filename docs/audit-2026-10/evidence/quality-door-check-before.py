#!/usr/bin/env python3
"""Does the Quality area offer a door that then refuses the person?

KEPT AS THE RECORD OF THE ORIGINAL MEASUREMENT (GAP-011). It was run against
the code BEFORE the fix and found Branch Manager, Operation Manager and
Coordinator each shown a tile that answered 403. Re-running it against the
fixed code will NOT reproduce that, because the tile now points at /capa for
those roles — which is the point. Use quality-door-check.py for the current
state; that one reads each role's tile href out of its own rendered page
instead of assuming the destination.


areas.php:134 shows the "Nonconformity workspace" tile when a person holds
mod.ncr.view OR mod.capa.view, and the tile points at /issues (or /ncr), both
of which are module 'ncr'. Four roles hold capa and not ncr. This drives it.
"""
import re, subprocess
SP='/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE='http://127.0.0.1:8795'
USERS=[('admin','Master Admin'),('aud.director','Business Director'),
       ('aud.branch','Branch Manager'),('aud.manager','Operation Manager'),
       ('uat.coord','Coordinator'),('uat.inspector','Inspector'),('uat.finance','Finance')]
def sh(*a): return subprocess.run(a,capture_output=True).stdout.decode('utf-8','replace')
def csrf(h):
    m=re.search(r'name="_csrf" value="([^"]+)"',h); return m.group(1) if m else ''
print(f"{'role':<20}{'tile offered on /quality':<28}{'/issues':<12}{'/ncr':<12}{'/capa':<12}")
print('-'*86)
for u,label in USERS:
    jar=f'{SP}/qd_{u}.ck'; subprocess.run(['rm','-f',jar])
    sh('curl','-s','-b',jar,'-c',jar,'-X','POST','-d',
       f'_csrf={csrf(sh("curl","-s","-c",jar,f"{BASE}/login"))}&username={u}&password=Pw12345678&viewport=1280',
       '-o','/dev/null',f'{BASE}/login')
    q=sh('curl','-s','-b',jar,f'{BASE}/quality')
    tile='YES' if 'Nonconformity workspace' in q else 'no'
    codes=[]
    for r in ('issues','ncr','capa'):
        c=sh('curl','-s','-b',jar,'-o','/dev/null','-w','%{http_code}',f'{BASE}/{r}')
        codes.append({'200':'opens','403':'REFUSED','302':'redirect'}.get(c,c))
    print(f'{label:<20}{tile:<28}'+''.join(f'{c:<12}' for c in codes))
