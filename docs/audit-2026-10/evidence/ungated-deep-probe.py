#!/usr/bin/env python3
"""EPhase F, GAP-008 — read the shortlist: ungated routes that OPEN for a
least-privileged role.

"No module gate" is not a hole on its own. 158 of the 200 ungated routes refuse
a least-privileged role anyway, because they apply their own check. This probe
reads the 25 that do open, and asks the two questions that decide whether an
open screen matters:

  1. Can they CHANGE anything?  -> every <form action> on the page, minus the
     site-wide /search box. A read-only screen with no form posts nothing.
  2. Can they SEE other people's records? -> the number of data-table rows, and
     whether the page carries the app's own "not yours" wording.

What it does NOT do: submit anything. Establishing that a form exists is enough
to flag a route for reading; firing it would write to records, and the charter
forbids that outside a controlled test of a specific finding.

Run against a local server on a throwaway copy of a UAT database.
"""
import json, os, re, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = os.environ.get('EXAACT_BASE', 'http://127.0.0.1:8798')
PROBES = [('uat.inspector', 'Inspector'), ('uat.finance', 'Finance')]

# The shortlist, from ungated-triage.py: bucket OPEN-WIDE and OPEN-ONE.
SHORTLIST = ['approvals-setup', 'complaints-policy', 'connect', 'get-started',
             'my-approvals', 'my-signature', 'quality', 'writing-assistant',
             'advisor', 'command-centre', 'cost-reconciliation', 'directory',
             'flow-gaps', 'insights', 'money', 'operations', 'passport-share',
             'project-costings', 'ratings', 'ratings-config',
             'reimbursable-dedup', 'reporting', 'revenue-reconciliation',
             'sales', 'timesheet']

# Wording the application uses when it has resolved authority and is saying so.
# Finding it is evidence the screen handled the question rather than ignoring it.
REFUSAL_WORDING = re.compile(
    r"not yours to change|nothing here is yours|you (?:can|may) ?n[o']?t|"
    r"ask your administrator|read-only|view only|you do not have",
    re.I)


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    return m.group(1) if m else ''


def login(user):
    jar = f'{SP}/deep_{user}.ck'
    subprocess.run(['rm', '-f', jar])
    sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
       f'_csrf={csrf(sh("curl", "-s", "-c", jar, f"{BASE}/login"))}'
       f'&username={user}&password=Pw12345678&viewport=1280',
       '-o', '/dev/null', f'{BASE}/login')
    # Refuse to trust a result taken while signed out.
    probe = sh('curl', '-s', '-b', jar, '-o', '/dev/null', '-w', '%{http_code}', f'{BASE}/my-work')
    return jar if probe.strip() == '200' else None


def look(jar, route):
    out = sh('curl', '-s', '-b', jar, '-w', '\n#C#%{http_code}', f'{BASE}/{route}')
    code = out.rsplit('#C#', 1)[-1].strip()
    html = out.rsplit('\n#C#', 1)[0]
    if code != '200':
        return {'code': code, 'verdict': 'refused'}
    # Forms that could change something. The layout's own search box is not one.
    actions = sorted({a for a in re.findall(r'<form[^>]+action="([^"]*)"', html)
                      if a and a not in ('/search', '#')})
    # Rows inside a data table — the app's own class for a record list.
    rows = len(re.findall(r'<tr[^>]*>\s*<td', html))
    h1 = re.search(r'<h1[^>]*>([^<]{0,60})', html)
    return {'code': code,
            'h1': (h1.group(1).strip() if h1 else ''),
            'size': len(html),
            'writes': actions,
            'rows': rows,
            'says_no': bool(REFUSAL_WORDING.search(html)),
            'verdict': 'CAN POST' if actions else 'read-only'}


result = {}
for user, label in PROBES:
    jar = login(user)
    if not jar:
        print(f'!! cannot sign in as {user}', file=sys.stderr)
        continue
    print(f'\n=== as {label} ===')
    print(f'{"route":<24}{"opens":<9}{"rows":<7}{"says no":<9}{"can post to"}')
    print('-' * 86)
    result[label] = {}
    for r in SHORTLIST:
        d = look(jar, r)
        result[label][r] = d
        if d['verdict'] == 'refused':
            print(f'{r:<24}{"no ("+d["code"]+")":<9}{"-":<7}{"-":<9}-')
            continue
        posts = ', '.join(d['writes']) if d['writes'] else '(nothing)'
        print(f'{r:<24}{"yes":<9}{d["rows"]:<7}{("yes" if d["says_no"] else ""):<9}{posts}')

with open(os.path.join(HERE, 'ungated-deep-probe.json'), 'w') as f:
    json.dump(result, f, indent=1)
print('\nwritten to ungated-deep-probe.json')
