#!/usr/bin/env python3
"""Test book — who can open which screen, measured on the throwaway copy.

Signs in as each role's test user and opens every screen captured in
screens_today.json. Records OPEN / REFUSED per role per screen.
Writes access_today.json.
"""
import json, os, re, subprocess
import html as html_lib

SP = os.path.dirname(os.path.abspath(__file__))
BASE = 'http://127.0.0.1:8899'
ROLES = ['BUSINESS_DIRECTOR', 'SBU_HEAD', 'BRANCH_MANAGER', 'BRANCH_APP_MANAGER', 'OPERATION_MANAGER', 'ASST_MANAGER', 'COORDINATOR',
         'BUSINESS_DEV_MANAGER', 'KEY_ACCOUNTS_MANAGER', 'MARKETING_MANAGER', 'MARKETING_EXECUTIVE', 'FINANCE', 'QUALITY_HEAD',
         'TECHNICAL_HEAD', 'SR_INSPECTOR', 'INSPECTOR']
REFUSAL = re.compile(r"don’t have access|do not have permission|Only the Master Admin|not available to your role|isn’t available|outside your office|"
                     r"You cannot|Only an administrator|Only someone who|not part of your plan|Access denied", re.I)


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def login(jar, user):
    subprocess.run(['rm', '-f', jar])
    h = sh('curl', '-s', '-c', jar, f'{BASE}/login')
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
       f'_csrf={m.group(1) if m else ""}&username={user}&password=Pw12345678&viewport=1280', '-o', '/dev/null', f'{BASE}/login')


def main():
    screens = json.load(open(os.path.join(SP, 'screens_today.json')))
    out = {}
    for role in ROLES:
        jar = os.path.join(SP, f'r_{role}.ck'); user = 'tb_' + role.lower()
        login(jar, user)
        res = {}
        for r, d in screens.items():
            page = os.path.join(SP, 'r_page.html')
            code = sh('curl', '-s', '-b', jar, '-c', jar, '--max-time', '20', '-o', page, '-w', '%{http_code}', f"{BASE}/{d['path']}").strip()
            html = open(page, encoding='utf-8', errors='replace').read() if os.path.exists(page) else ''
            if 'name="password"' in html and 'change-password' not in r:
                login(jar, user)
                code = sh('curl', '-s', '-b', jar, '-c', jar, '--max-time', '20', '-o', page, '-w', '%{http_code}', f"{BASE}/{d['path']}").strip()
                html = open(page, encoding='utf-8', errors='replace').read()
            # Open = the same screen the administrator saw (same heading); a refusal
            # redirects (302) or draws a different page.
            m = re.search(r'<main.*?<h1[^>]*>(.*?)</h1>', html, re.S)
            norm = lambda t: re.sub(r'\s+', ' ', html_lib.unescape(re.sub(r'<[^>]+>', ' ', t))).strip()
            h1 = norm(m.group(1)) if m else ''
            want = norm(d['h1'])
            ok = code == '200' and 'name="password"' not in html and h1[:18] == want[:18]
            res[r] = 'OPEN' if ok else 'REFUSED'
        out[role] = res
        print(role, sum(v == 'OPEN' for v in res.values()), 'open of', len(res), flush=True)
    json.dump(out, open(os.path.join(SP, 'access_today.json'), 'w'))


if __name__ == '__main__':
    main()
