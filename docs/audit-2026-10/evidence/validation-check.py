#!/usr/bin/env python3
"""Phase E — is "no required field" a validation hole, or only silent guidance?

screens.json shows the creation forms marking almost nothing as required:
/user-new 1 of 259, /call-new 1 of 140, /quote-new 1 of 97, /partner-new 1 of 66.

`required` is a BROWSER hint. The server may still refuse an empty submit, and
the two cases need completely different write-ups:

  server refuses  -> the data is safe; the person is told AFTER filling a long
                     form what it needed. A guidance defect.
  server accepts  -> an empty or near-empty record enters the business. A data
                     integrity defect.

So each form is posted empty (CSRF only) against a throwaway copy of a UAT
database, and the row count for its table is compared before and after. This
WRITES, which is why it runs only here, on a disposable database, never against
production.
"""
import json, os, re, sqlite3, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = os.environ.get('EXAACT_BASE', 'http://127.0.0.1:8801')
DB = os.environ.get('EXAACT_DB', f'{SP}/audit-e.sqlite')

# route, the table a successful create would land in, what the screen is for
FORMS = [
    ('partner-new', 'business_partners', 'a new client or vendor'),
    ('call-new', 'calls', 'a new work order'),
    ('quote-new', 'quotes', 'a new quotation'),
    ('user-new', 'users', 'a new person'),
    ('candidate-new', 'candidates', 'a new candidate'),
    ('hiring-request', 'hiring_requests', 'a new hiring request'),
    ('internal-audit-new', 'internal_audits', 'a planned internal audit'),
    ('inquiry-new', 'inquiries', 'a new enquiry'),
]


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    return m.group(1) if m else ''


def count(tbl):
    c = sqlite3.connect(DB)
    try:
        return c.execute(f'select count(*) from {tbl}').fetchone()[0]
    except Exception:
        return None
    finally:
        c.close()


jar = f'{SP}/val.ck'
subprocess.run(['rm', '-f', jar])
sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
   f'_csrf={csrf(sh("curl", "-s", "-c", jar, f"{BASE}/login"))}'
   f'&username=admin&password=Pw12345678&viewport=1280', '-o', '/dev/null', f'{BASE}/login')
if sh('curl', '-s', '-b', jar, '-o', '/dev/null', '-w', '%{http_code}', f'{BASE}/admin').strip() != '200':
    print('cannot sign in', file=sys.stderr)
    sys.exit(1)

print(f'{"form":<22}{"creates":<20}{"required":<10}{"empty submit":<16}{"what the person is told"}')
print('-' * 104)
out = {}
for route, tbl, what in FORMS:
    page = sh('curl', '-s', '-b', jar, f'{BASE}/{route}')
    if 'name="_csrf"' not in page:
        print(f'{route:<22}{tbl:<20}{"-":<10}{"form not open":<16}')
        continue
    reqd = len(re.findall(r'<(?:input|select|textarea)[^>]*\brequired\b', page, re.I))
    before = count(tbl)
    resp = sh('curl', '-s', '-b', jar, '-L', '-X', 'POST', '-d',
              f'_csrf={csrf(page)}', f'{BASE}/{route}')
    after = count(tbl)
    if before is None:
        verdict, msg = 'table unknown', ''
    elif after is not None and after > before:
        verdict = '*** ACCEPTED ***'
        msg = 'a record was created from an empty form'
    else:
        verdict = 'refused'
        m = re.search(r'class="flash[^"]*"[^>]*>(.{0,150})', resp, re.S)
        msg = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', m.group(1))).strip()[:56] if m else '(no message found)'
    out[route] = {'table': tbl, 'required_in_html': reqd,
                  'before': before, 'after': after, 'verdict': verdict, 'message': msg}
    print(f'{route:<22}{tbl:<20}{reqd:<10}{verdict:<16}{msg}')

json.dump(out, open(os.path.join(HERE, 'validation-check.json'), 'w'), indent=1)
print('\nwritten to validation-check.json')
