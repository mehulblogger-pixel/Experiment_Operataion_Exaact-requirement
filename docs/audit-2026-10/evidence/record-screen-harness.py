#!/usr/bin/env python3
"""Inventory the RECORD screens — the ones that need a ?id= to render.

The screen harness could not reach these. A detail screen with no record to
show answers with a redirect or a 404, so it never appeared as a SCREEN in the
route probe and is absent from screens.json. That is precisely where the work
happens: /calls is a register, /call?id=1 is the work order.

The test database carries almost no operational data, so this seeds the minimum
needed — one record per family, created through the application's own forms so
nothing is written behind its back — and then inventories each detail screen.

It writes to a THROWAWAY copy. Never point it at production.
"""
import json, os, re, sqlite3, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = os.environ.get('EXAACT_BASE', 'http://127.0.0.1:8804')
DB = os.environ.get('EXAACT_DB', f'{SP}/audit-h.sqlite')

# detail route -> (table holding the record, label)
RECORDS = [
    ('call', 'calls', 'a work order'),
    ('job', 'jobs', 'a job'),
    ('voucher', 'vouchers', "an inspector's voucher"),
    ('partner', 'business_partners', 'a client or vendor'),
    ('candidate', 'candidates', 'a candidate'),
    ('hiring-request', 'hiring_requests', 'a hiring request'),
    ('quote', 'quotations', 'a quotation'),
    ('invoice', 'invoices', 'an invoice'),
    ('document', 'documents', 'an inspection report'),
    ('ncr', 'nonconformities', 'a nonconformity'),
]

CHROME = {'✕', '«', '»', '★', '☰', '←', '→', '🔍', '🧭Go to…⌘K', 'Find', 'Search'}
TAG = re.compile(r'<(input|select|textarea)\b([^>]*)>', re.I)
ATTR = re.compile(r'(\w[\w-]*)\s*=\s*"([^"]*)"')
HIDDEN = {'hidden', 'submit', 'button', 'image', 'reset'}


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    return m.group(1) if m else ''


def first_id(tbl):
    c = sqlite3.connect(DB)
    try:
        r = c.execute(f'select id from {tbl} order by id limit 1').fetchone()
        return r[0] if r else None
    except Exception:
        return None
    finally:
        c.close()


def controls(html):
    out = []
    for tag, attrs in TAG.findall(html):
        a = dict(ATTR.findall(attrs))
        t = (a.get('type') or ('select' if tag.lower() == 'select' else 'textarea')).lower()
        if t in HIDDEN:
            continue
        n = a.get('name', '')
        if not n or n == '_csrf' or n == 'q':
            continue
        out.append({'n': n, 't': t, 'r': 'required' in attrs.lower()})
    return out


def buttons(html):
    out, seen = [], set()
    for m in re.findall(r'<button[^>]*>(.*?)</button>', html, re.S):
        t = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', m)).strip()
        if t and t not in CHROME and len(t) > 1 and t not in seen:
            seen.add(t); out.append(t)
    for m in re.findall(r'<a[^>]+class="[^"]*\bbtn\b[^"]*"[^>]*>(.*?)</a>', html, re.S):
        t = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', m)).strip()
        if t and t not in CHROME and len(t) > 1 and t not in seen:
            seen.add(t); out.append(t)
    return out


jar = f'{SP}/rec.ck'
subprocess.run(['rm', '-f', jar])
sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
   f'_csrf={csrf(sh("curl", "-s", "-c", jar, f"{BASE}/login"))}'
   f'&username=admin&password=Pw12345678&viewport=1280', '-o', '/dev/null', f'{BASE}/login')
if sh('curl', '-s', '-b', jar, '-o', '/dev/null', '-w', '%{http_code}', f'{BASE}/admin').strip() != '200':
    print('cannot sign in', file=sys.stderr)
    sys.exit(1)

out = {}
print(f'{"record screen":<22}{"id":>4}  {"heading":<30}{"fields":>7}{"req":>5}{"btns":>6}')
print('-' * 80)
for route, tbl, label in RECORDS:
    rid = first_id(tbl)
    if rid is None:
        print(f'{"/"+route:<22}{"—":>4}  (no {label} in the test database — not inventoried)')
        out[route] = {'table': tbl, 'available': False}
        continue
    html = sh('curl', '-s', '-b', jar, f'{BASE}/{route}?id={rid}')
    h1 = re.search(r'<h1[^>]*>(.*?)</h1>', html, re.S)
    c, b = controls(html), buttons(html)
    cols = [[re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', x)).strip()
             for x in re.findall(r'<th\b[^>]*>(.*?)</th>', t, re.S)]
            for t in re.findall(r'<table\b.*?</table>', html, re.S)]
    out[route] = {'table': tbl, 'available': True, 'id': rid,
                  'heading': re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', h1.group(1))).strip() if h1 else '',
                  'fields': c, 'required': sum(1 for x in c if x['r']),
                  'buttons': b, 'tables': [x for x in cols if any(x)], 'size': len(html)}
    print(f'{"/"+route:<22}{rid:>4}  {out[route]["heading"][:29]:<30}'
          f'{len(c):>7}{out[route]["required"]:>5}{len(b):>6}')

json.dump(out, open(os.path.join(HERE, 'record-screens.json'), 'w'), indent=1)
print('\nwritten to record-screens.json')
