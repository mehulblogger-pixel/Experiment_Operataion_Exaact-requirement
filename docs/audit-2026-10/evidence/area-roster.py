#!/usr/bin/env python3
"""The COMPLETE screen roster per navigation area — tiles and record screens.

The tile list alone is not the area. A tile opens a register (/quotes); the
work happens on the record screen behind it (/quote?id=...), and on the action
handlers behind that. "Do not skip any screen" means all three.

So an area's roster here is every screen whose GATING MODULE belongs to that
area, plus the area's own tiles (some tiles are module-free hubs), plus the
screens those tiles link to. Module -> area comes from the application's own
access_module_areas().

It also strips LAYOUT CHROME from each screen's button list. Every page repeats
the rail and header controls (✕ « ★ ☰ ← 🔍 ⌘K, the rail's Find box), so
counting them as screen buttons overstates every screen by about eight and
buries the controls that matter.

Writes area-roster.json; prints a summary.
"""
import json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
SCREENS = json.load(open(os.path.join(HERE, 'screens.json')))
WF = json.load(open(os.path.join(HERE, 'workflows-data.json')))

# module -> area, mirroring access_module_areas() in phpapp/lib/access.php
MOD_AREA = {
    'leads': 'Sales', 'inquiries': 'Sales', 'quotes': 'Sales',
    'crm_orders': 'Sales', 'crm_reports': 'Sales',
    'calls': 'Operations', 'jobs': 'Operations', 'vouchers': 'Operations',
    'reconcile': 'Operations',
    'hiring': 'Recruitment',
    'idems': 'Reporting',
    'invoicing': 'Money', 'profitability': 'Money', 'overheads': 'Money',
    'equipment': 'Quality & Accreditation', 'competence': 'Quality & Accreditation',
    'impartiality': 'Quality & Accreditation', 'identity': 'Quality & Accreditation',
    'complaints': 'Quality & Accreditation', 'ncr': 'Quality & Accreditation',
    'confidentiality': 'Quality & Accreditation', 'capa': 'Quality & Accreditation',
    'audits': 'Quality & Accreditation', 'datacontrol': 'Quality & Accreditation',
    'reports': 'Insights',
    'clients': 'Directory', 'vendors': 'Directory', 'masters': 'Directory',
    'portal': 'Directory',
    'users': 'Admin', 'settings': 'Admin',
}

MODULE = {}
fx = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(HERE))), 'phpapp',
                  'tests', 'fixtures', 'routes_gated.tsv')
for line in open(fx):
    if line.startswith('#') or not line.strip():
        continue
    p = line.rstrip('\n').split('\t')
    if len(p) >= 2:
        MODULE[p[0]] = p[1]

# Controls the layout repeats on every single page.
CHROME = {'✕', '«', '»', '★', '☰', '←', '→', '🔍', '🧭Go to…⌘K', 'Find', 'Search',
          'Go to…', '⌘K', 'Skip to content', 'Sign out', 'Menu'}


def real_buttons(bs):
    out, seen = [], set()
    for b in bs:
        t = b.strip()
        if not t or t in CHROME or len(t) <= 1:
            continue
        if t in seen:
            continue
        seen.add(t)
        out.append(t)
    return out


roster = {}
for area in WF:
    tiles = {t['route']: t for t in WF[area]['tiles'] if t['route']}
    # every screen gated by a module belonging to this area
    by_module = {r for r, m in MODULE.items() if MOD_AREA.get(m) == area and r in SCREENS}
    # everything the tiles link onward to, which catches module-free hubs
    linked = set()
    for r in tiles:
        linked |= set(WF[area]['screens'].get(r, {}).get('onward', {}))
    linked &= set(SCREENS)

    routes = sorted(set(tiles) | by_module | linked)
    detail = {}
    for r in routes:
        s = SCREENS.get(r, {})
        if not s:
            continue
        detail[r] = {
            'heading': s.get('h1', ''),
            'module': MODULE.get(r, ''),
            'is_tile': r in tiles,
            'tile_label': tiles.get(r, {}).get('label', ''),
            'tile_desc': tiles.get(r, {}).get('desc', ''),
            'section': tiles.get(r, {}).get('section', ''),
            'fields': [{'n': c['name'], 't': c['type'], 'r': c['required']}
                       for c in s.get('controls', [])],
            'required': s.get('required', 0),
            'buttons': real_buttons(s.get('buttons', [])),
            'tables': [x['cols'] for x in s.get('tables', []) if x['cols']],
            'onward': WF[area]['screens'].get(r, {}).get('onward', {}),
        }
    roster[area] = {'home': WF[area]['home'], 'screens': detail}

json.dump(roster, open(os.path.join(HERE, 'area-roster.json'), 'w'), indent=1)

print(f'{"area":<26}{"screens":>8}{"tiles":>7}{"fields":>8}{"buttons":>9}{"tables":>8}')
print('-' * 66)
tot = 0
for a, d in roster.items():
    s = d['screens']
    tot += len(s)
    print(f'{a:<26}{len(s):>8}{sum(1 for x in s.values() if x["is_tile"]):>7}'
          f'{sum(len(x["fields"]) for x in s.values()):>8}'
          f'{sum(len(x["buttons"]) for x in s.values()):>9}'
          f'{sum(len(x["tables"]) for x in s.values()):>8}')
print('-' * 66)
print(f'{"TOTAL":<26}{tot:>8}')
print('\nwritten to area-roster.json')
