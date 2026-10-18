#!/usr/bin/env python3
"""EXAACT audit, Phase E — inventory every screen's fields, controls and labels.

One pass over every route that renders, as an administrator, recording the
things a screen-by-screen audit needs and a human cannot count by hand:

  heading, breadcrumb trail, every input/select/textarea (name, type,
  required, whether it carries a label), every button and its words, every
  table and its column headers, row counts, and the page's own byte size.

This dataset feeds three deliverables:
  04-SCREEN-AND-FIELD-AUDIT.md   the per-screen findings
  05-BUSINESS-GLOSSARY.md        the field labels and column headers, which are
                                 the words the business actually sees
  07-DASHBOARD-AND-KPI-AUDIT.md  the stat tiles and their captions

LIMITATION, stated up front: the throwaway database carries almost no
operational data (0 calls, 0 jobs), so this measures a screen's STRUCTURE —
which fields and controls exist, how they are labelled, how many there are —
not its behaviour at realistic data volume. Pagination, slow queries and
long-list usability need a populated database and are listed as remaining work.

Writes screens.json (full detail) and screens.tsv (one row per screen).
Prints aggregates only.
"""
import json, os, re, subprocess, sys
from collections import Counter

HERE = os.path.dirname(os.path.abspath(__file__))
SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = os.environ.get('EXAACT_BASE', 'http://127.0.0.1:8798')
USER = os.environ.get('EXAACT_USER', 'admin')

SKIP = re.compile(r'(^|-)(logout|delete|erase|destroy|purge|reset|restore|wipe|drop)(-|$)')


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    return m.group(1) if m else ''


def login():
    jar = f'{SP}/sf.ck'
    subprocess.run(['rm', '-f', jar])
    sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
       f'_csrf={csrf(sh("curl", "-s", "-c", jar, f"{BASE}/login"))}'
       f'&username={USER}&password=Pw12345678&viewport=1280',
       '-o', '/dev/null', f'{BASE}/login')
    code = sh('curl', '-s', '-b', jar, '-o', '/dev/null', '-w', '%{http_code}', f'{BASE}/admin').strip()
    return jar if code == '200' else None


TAG = re.compile(r'<(input|select|textarea)\b([^>]*)>', re.I)
ATTR = re.compile(r'(\w[\w-]*)\s*=\s*"([^"]*)"')
# A control the person never sees is not a field they fill in.
HIDDEN_TYPES = {'hidden', 'submit', 'button', 'image', 'reset'}


def controls(html):
    out = []
    for tag, attrs in TAG.findall(html):
        a = dict(ATTR.findall(attrs))
        t = (a.get('type') or ('select' if tag.lower() == 'select' else 'textarea')).lower()
        if t in HIDDEN_TYPES:
            continue
        name = a.get('name', '')
        if not name or name == '_csrf':
            continue
        out.append({
            'name': name,
            'type': t,
            'required': ('required' in attrs.lower()),
            'placeholder': a.get('placeholder', ''),
            'aria': a.get('aria-label', ''),
            'maxlength': a.get('maxlength', ''),
        })
    return out


def labels(html):
    return [re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', m)).strip()
            for m in re.findall(r'<label[^>]*>(.*?)</label>', html, re.S)]


def buttons(html):
    out = []
    for m in re.findall(r'<button[^>]*>(.*?)</button>', html, re.S):
        t = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', m)).strip()
        if t:
            out.append(t)
    for m in re.findall(r'<input[^>]+type="submit"[^>]*>', html, re.I):
        v = re.search(r'value="([^"]*)"', m)
        if v and v.group(1).strip():
            out.append(v.group(1).strip())
    # Links styled as buttons are buttons to the person using the screen.
    for m in re.findall(r'<a[^>]+class="[^"]*\bbtn\b[^"]*"[^>]*>(.*?)</a>', html, re.S):
        t = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', m)).strip()
        if t:
            out.append(t)
    return out


def tables(html):
    out = []
    for tbl in re.findall(r'<table\b.*?</table>', html, re.S):
        cols = [re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', h)).strip()
                for h in re.findall(r'<th\b[^>]*>(.*?)</th>', tbl, re.S)]
        rows = len(re.findall(r'<tr[^>]*>\s*<td', tbl))
        out.append({'cols': [c for c in cols if c], 'rows': rows})
    return out


def stat_tiles(html):
    """The dashboard pattern: a big number with a caption under it."""
    return [re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', m)).strip()[:80]
            for m in re.findall(r'<div[^>]+class="[^"]*\b(?:kpi|stat|metric|tile|card)\b[^"]*".*?</div>',
                                html, re.S)[:40]]


def main():
    jar = login()
    if not jar:
        print('cannot sign in', file=sys.stderr)
        return 1

    #  route-probe-admin.tsv has NO header row: columns are
    #  route, http status, classification, rendered heading. Assuming a header
    #  would silently drop the first screen (/access), so the shape is detected
    #  rather than assumed.
    with open(os.path.join(HERE, 'route-probe-admin.tsv')) as f:
        rows = [l.rstrip('\n').split('\t') for l in f if l.strip()]
    ri, ki = 0, 2
    if rows and rows[0][0] == 'route':
        try:
            ri, ki = rows[0].index('route'), rows[0].index('class')
        except ValueError:
            pass
        rows = rows[1:]
    screens = [r[ri] for r in rows
               if len(r) > ki and r[ki] == 'SCREEN' and not SKIP.search(r[ri])]

    out = {}
    for i, route in enumerate(screens):
        html = sh('curl', '-s', '-b', jar, f'{BASE}/{route}')
        if '<h1' not in html and 'class="crumbs"' not in html:
            # Session may have lapsed; re-establish once and retry.
            jar2 = login()
            if jar2:
                html = sh('curl', '-s', '-b', jar2, f'{BASE}/{route}')
        h1 = re.search(r'<h1[^>]*>(.*?)</h1>', html, re.S)
        c = controls(html)
        t = tables(html)
        out[route] = {
            'h1': re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', h1.group(1))).strip() if h1 else '',
            'crumbs': 'class="crumbs"' in html,
            'size': len(html),
            'controls': c,
            'required': sum(1 for x in c if x['required']),
            'labels': labels(html),
            'buttons': buttons(html),
            'tables': t,
            'tiles': stat_tiles(html),
        }
        if i % 40 == 0:
            print(f'  {i}/{len(screens)}', file=sys.stderr)

    with open(os.path.join(HERE, 'screens.json'), 'w') as f:
        json.dump(out, f, indent=1)

    with open(os.path.join(HERE, 'screens.tsv'), 'w') as f:
        f.write('route\theading\tcrumbs\tfields\trequired\tlabels\tbuttons\ttables\tmax_cols\trows\tbytes\n')
        for r, d in sorted(out.items()):
            mc = max([len(x['cols']) for x in d['tables']], default=0)
            rw = sum(x['rows'] for x in d['tables'])
            f.write(f"{r}\t{d['h1']}\t{'y' if d['crumbs'] else 'n'}\t{len(d['controls'])}\t"
                    f"{d['required']}\t{len(d['labels'])}\t{len(d['buttons'])}\t"
                    f"{len(d['tables'])}\t{mc}\t{rw}\t{d['size']}\n")

    tot = len(out)
    forms = [d for d in out.values() if d['controls']]
    print(f'\nscreens inventoried      : {tot}')
    print(f'  with a heading         : {sum(1 for d in out.values() if d["h1"])}')
    print(f'  with a breadcrumb      : {sum(1 for d in out.values() if d["crumbs"])}')
    print(f'  with at least one field: {len(forms)}')
    print(f'  total visible fields   : {sum(len(d["controls"]) for d in out.values())}')
    print(f'  fields marked required : {sum(d["required"] for d in out.values())}')
    print(f'  screens with NO required field but >3 fields: '
          f'{sum(1 for d in forms if d["required"] == 0 and len(d["controls"]) > 3)}')
    print(f'  screens with >25 fields: {sum(1 for d in forms if len(d["controls"]) > 25)}')
    print(f'  tables with >12 columns: '
          f'{sum(1 for d in out.values() for t in d["tables"] if len(t["cols"]) > 12)}')
    print(f'  distinct button words  : {len({b for d in out.values() for b in d["buttons"]})}')
    print(f'  distinct field labels  : {len({l for d in out.values() for l in d["labels"]})}')
    print('\nwritten: screens.json, screens.tsv')
    return 0


if __name__ == '__main__':
    sys.exit(main())
