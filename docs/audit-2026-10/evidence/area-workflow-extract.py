#!/usr/bin/env python3
"""Build the per-area workflow dataset behind docs/audit-2026-10/workflows/.

For each of the ten areas in the left navigation, joins four things already
measured, so each workflow document is assembled from evidence rather than
written from memory:

  navigation-dump.json   the area's tiles, as the running menu declares them
                         (Operations and Recruitment are NOT served by the area
                         engine — they have hand-built home screens — so for
                         those the links are read from the rendered page)
  screens.json           every screen's heading, fields, buttons, tables
  route module map       which module gates each screen
  the live page          the onward links each screen offers, so a document can
                         follow the chain the way a person does

Writes workflows-data.json. Prints a per-area summary only.
"""
import json, os, re, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = os.environ.get('EXAACT_BASE', 'http://127.0.0.1:8804')

NAV = json.load(open(os.path.join(HERE, 'navigation-dump.json')))
SCREENS = json.load(open(os.path.join(HERE, 'screens.json')))

# route -> module, from the committed fixture (same resolution the dispatcher uses)
MODULE = {}
fx = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(HERE))), 'phpapp',
                  'tests', 'fixtures', 'routes_gated.tsv')
if os.path.exists(fx):
    for line in open(fx):
        if line.startswith('#') or not line.strip():
            continue
        p = line.rstrip('\n').split('\t')
        if len(p) >= 2:
            MODULE[p[0]] = p[1]

ORDER = ['Sales', 'Marketplace', 'Operations', 'Recruitment',
         'Quality & Accreditation', 'Reporting', 'Money', 'Insights',
         'Directory', 'Admin']


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    return m.group(1) if m else ''


def login():
    jar = f'{SP}/aw.ck'
    subprocess.run(['rm', '-f', jar])
    sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
       f'_csrf={csrf(sh("curl", "-s", "-c", jar, f"{BASE}/login"))}'
       f'&username=admin&password=Pw12345678&viewport=1280', '-o', '/dev/null', f'{BASE}/login')
    return jar if sh('curl', '-s', '-b', jar, '-o', '/dev/null',
                     '-w', '%{http_code}', f'{BASE}/admin').strip() == '200' else None


def clean(route):
    """'/templates?kind=quote' -> 'templates' so it can be looked up."""
    return route.lstrip('/').split('?')[0].split('#')[0]


LINK = re.compile(r'<a[^>]+href="/([a-z0-9][a-z0-9/-]*)(?:\?[^"]*)?"[^>]*>(.*?)</a>', re.S | re.I)
CHROME = {'login', 'logout', 'admin', 'dashboard', 'search', 'my-work', 'start',
          'privacy', 'help', 'owner', 'flow-gaps', 'to-fix', 'profile'}


def onward(jar, route):
    """Links this screen offers, excluding the layout's own chrome."""
    html = sh('curl', '-s', '-b', jar, f'{BASE}/{route}')
    # The left rail and header repeat on every page; cut to the main region if
    # the layout marks one, so onward links are the screen's own.
    m = re.search(r'<main\b.*?</main>', html, re.S | re.I)
    body = m.group(0) if m else html
    seen = {}
    for r, txt in LINK.findall(body):
        r = clean(r)
        if not r or r in CHROME or r == route:
            continue
        t = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', txt)).strip()
        if r not in seen and t:
            seen[r] = t[:50]
    return seen


def main():
    jar = login()
    if not jar:
        print('cannot sign in', file=sys.stderr)
        return 1

    out = {}
    for area in ORDER:
        a = NAV['areas'].get(area, {})
        tiles = []
        for sec in a.get('sections', []):
            for t in sec.get('tiles', []):
                tiles.append({'section': sec.get('section', ''), 'label': t.get('label', ''),
                              'route': clean(t.get('route', '')), 'desc': t.get('desc', '')})
        if not tiles:
            # Custom home screen: read its own links instead of a tile list.
            home = clean(a.get('home', '')) or clean(area.lower())
            for r, label in onward(jar, home).items():
                tiles.append({'section': '(from the screen itself)', 'label': label,
                              'route': r, 'desc': ''})

        detail = {}
        for t in tiles:
            r = t['route']
            if not r or r in detail:
                continue
            s = SCREENS.get(r, {})
            detail[r] = {
                'heading': s.get('h1', ''),
                'module': MODULE.get(r, ''),
                'fields': [{'name': c['name'], 'type': c['type'], 'required': c['required']}
                           for c in s.get('controls', [])],
                'required': s.get('required', 0),
                'labels': s.get('labels', [])[:60],
                'buttons': s.get('buttons', [])[:40],
                'tables': [{'cols': x['cols'], 'rows': x['rows']} for x in s.get('tables', [])],
                'onward': onward(jar, r),
                'measured': bool(s),
            }
        out[area] = {'home': clean(a.get('home', '')), 'tiles': tiles, 'screens': detail,
                     'engine': bool(a.get('sections'))}
        nf = sum(len(d['fields']) for d in detail.values())
        nb = sum(len(d['buttons']) for d in detail.values())
        print(f'{area:<26} tiles {len(tiles):>3}   screens {len(detail):>3}   '
              f'fields {nf:>4}   buttons {nb:>4}   '
              f'{"area engine" if a.get("sections") else "custom home"}')

    json.dump(out, open(os.path.join(HERE, 'workflows-data.json'), 'w'), indent=1)
    print('\nwritten to workflows-data.json')
    return 0


if __name__ == '__main__':
    sys.exit(main())
