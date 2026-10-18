#!/usr/bin/env python3
"""EXAACT audit, GAP-008 — triage the routes that carry no module gate.

Inputs, both already in this folder:
  ungated-routes.txt        the routes whose module resolves to nothing
                            (produced by ungated-routes-list.php)
  route-probe-by-role.json  every dispatched route x seven roles, measured

A route with no module gate is not automatically a hole: most enforce something
of their own further in, and some are public by design. What matters is the
combination "no module gate" AND "opens as a least-privileged role".

Inspector and Finance are used as the probes. They are the two narrowest roles
in the business (32 and 59 screens of 256), so a route that opens for either,
with no module gate, is the shortlist worth reading line by line.

Buckets printed:
  PUBLIC      public by design — sign-in, verification, the agreement
  SELF        a personal screen whose content is the signed-in person's own
  OPEN-WIDE   opens for Inspector AND Finance — read it
  OPEN-ONE    opens for one of them — read it
  ENFORCES    refused or redirected for both — it applies its own check
"""
import json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))

with open(os.path.join(HERE, 'ungated-routes.txt')) as f:
    ungated = [r.strip() for r in f if r.strip() and r.strip() != 'route']

with open(os.path.join(HERE, 'route-probe-by-role.json')) as f:
    probe = json.load(f)

by_label = {v['label']: v['rows'] for v in probe.values()}
ins = by_label.get('Inspector', {})
fin = by_label.get('Finance', {})
adm = by_label.get('Master Admin', {})

# Public by design: the app must serve these before anyone is signed in.
PUBLIC = {'login', 'logout', 'verify', 'welcome', 'agreement', 'privacy', 'terms',
          'join', 'forgot', 'reset', 'reset-password', 'signup', 'register',
          'health', 'healthz', 'robots.txt', 'favicon.ico', 'manifest.json',
          'offline', 'sw.js', 'invite', 'accept-invite', 'pay', 'pay-return'}
# A personal screen: its content IS the signed-in person's own record, so every
# role opening it is correct. Named explicitly rather than pattern-matched, so a
# new route cannot slip into this bucket by being called "my-something".
SELF = {'my-work', 'my-jobs', 'my-voucher', 'my-vouchers', 'my-interviews',
        'my-reports', 'my-tasks', 'tasks', 'profile', 'me', 'change-password',
        'notifications', 'inbox', 'approvals', 'start', 'dashboard', 'home',
        'search', 'help', 'whatsnew', 'two-factor', 'totp', 'sessions'}

buckets = {k: [] for k in ('PUBLIC', 'SELF', 'OPEN-WIDE', 'OPEN-ONE', 'ENFORCES')}
for r in ungated:
    i, f_, a = ins.get(r, '-'), fin.get(r, '-'), adm.get(r, '-')
    if r in PUBLIC:
        b = 'PUBLIC'
    elif r in SELF:
        b = 'SELF'
    elif i == 'SCREEN' and f_ == 'SCREEN':
        b = 'OPEN-WIDE'
    elif i == 'SCREEN' or f_ == 'SCREEN':
        b = 'OPEN-ONE'
    else:
        b = 'ENFORCES'
    buckets[b].append((r, i, f_, a))

print(f'routes with no module gate: {len(ungated)}\n')
for b in ('OPEN-WIDE', 'OPEN-ONE', 'SELF', 'PUBLIC', 'ENFORCES'):
    rows = buckets[b]
    print(f'== {b}  ({len(rows)}) ==')
    if b == 'ENFORCES':
        print('   (refused or redirected for both probes — listed in the TSV, not here)\n')
        continue
    print(f'   {"route":<30}{"Inspector":<12}{"Finance":<12}{"Admin":<12}')
    for r, i, f_, a in sorted(rows):
        print(f'   {r:<30}{i:<12}{f_:<12}{a:<12}')
    print()

with open(os.path.join(HERE, 'ungated-triage.tsv'), 'w') as out:
    out.write('route\tbucket\tinspector\tfinance\tadmin\n')
    for b, rows in buckets.items():
        for r, i, f_, a in sorted(rows):
            out.write(f'{r}\t{b}\t{i}\t{f_}\t{a}\n')
print(f'full table written to ungated-triage.tsv')
