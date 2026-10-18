#!/usr/bin/env python3
"""The Quality tile, AFTER the fix: does the door it offers actually open?

Same method that found the dead end: load /quality as each role, pull the
tile's own href out of the rendered page, and follow it. A door is only a
door if what is behind it opens.
"""
import re, subprocess

SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = 'http://127.0.0.1:8798'
USERS = [('admin', 'Master Admin'), ('aud.director', 'Business Director'),
         ('aud.branch', 'Branch Manager'), ('aud.manager', 'Operation Manager'),
         ('uat.coord', 'Coordinator'), ('uat.inspector', 'Inspector'),
         ('uat.finance', 'Finance')]


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    return m.group(1) if m else ''


print(f"{'role':<20}{'tile shown':<26}{'points at':<14}{'and it':<12}")
print('-' * 74)
for u, label in USERS:
    jar = f'{SP}/qd2_{u}.ck'
    subprocess.run(['rm', '-f', jar])
    sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
       f'_csrf={csrf(sh("curl", "-s", "-c", jar, f"{BASE}/login"))}'
       f'&username={u}&password=Pw12345678&viewport=1280', '-o', '/dev/null', f'{BASE}/login')
    q = sh('curl', '-s', '-b', jar, f'{BASE}/quality')
    m = re.search(r'<a[^>]+href="([^"]+)"[^>]*>(?:(?!</a>).)*?'
                  r'(Nonconformity workspace|Corrective actions)', q, re.S)
    if not m:
        print(f'{label:<20}{"not offered":<26}{"-":<14}{"-":<12}')
        continue
    href, name = m.group(1), m.group(2)
    code = sh('curl', '-s', '-b', jar, '-o', '/dev/null', '-w', '%{http_code}', f'{BASE}{href}')
    verdict = {'200': 'OPENS', '403': '*** 403 ***', '302': 'redirect'}.get(code, code)
    print(f'{label:<20}{name:<26}{href:<14}{verdict:<12}')
