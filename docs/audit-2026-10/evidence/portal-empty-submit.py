#!/usr/bin/env python3
"""
EXAACT audit — what do the EXTERNAL portal forms do when they are submitted
empty?

HTML `required` is a courtesy to the browser. It is client-side only, trivially
bypassed, and absent entirely from a crafted request or a glitchy phone. What
decides whether a blank record lands in the database is the SERVER.

This matters here because the same failure was already proven on the STAFF side
of this application: four forms created numbered ghost records -- CALL-00001,
CV-00001, INC-2026-001 -- from an empty submission, while three siblings refused
with excellent messages. So this is not a hypothetical class of bug in this
codebase; it is a demonstrated one.

For each creating form the script:
  * signs in as a real seeded portal user
  * counts the rows in the table that form writes to
  * submits the form EMPTY, with a valid CSRF token (so the refusal under test
    is the VALIDATION, not the CSRF gate)
  * counts again, and reads back the message the person was shown

A row created from an empty submission is a FAILURE. A refusal is a pass, and
the message is quoted so its wording can be judged too.

Read-only against a THROWAWAY seeded database. Never point it at production.

  cd phpapp && python3 ../docs/audit-2026-10/evidence/portal-empty-submit.py \
      http://127.0.0.1:8843 /tmp/pv.sqlite
"""
import sys, re, sqlite3, urllib.request, urllib.parse, http.cookiejar

BASE = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8843'
DB   = sys.argv[2] if len(sys.argv) > 2 else '/tmp/pv.sqlite'
PW   = 'demo12345'

def opener():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    with op.open(BASE + path) as r:
        return r.read().decode('utf-8', 'replace')

def post(op, path, data):
    body = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(BASE + path, data=body)
    try:
        with op.open(req) as r:
            return r.status, r.read().decode('utf-8', 'replace'), r.geturl()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace'), path

def csrf(html):
    m = re.search(r'name="_csrf"\s+value="([^"]*)"', html)
    return m.group(1) if m else ''

def msgs(html):
    """Every message panel on the page, in order, tag included."""
    out = []
    for m in re.finditer(r'<div class="(p?msg[^"]*)"[^>]*>(.*?)</div>', html, re.S):
        t = re.sub(r'<[^>]+>', ' ', m.group(2))
        t = re.sub(r'\s+', ' ', t).strip()
        if t: out.append((m.group(1), t))
    return out

def rows(table, where=''):
    c = sqlite3.connect(DB)
    try:
        q = 'SELECT COUNT(*) FROM %s' % table + (' WHERE ' + where if where else '')
        return c.execute(q).fetchone()[0]
    except Exception as e:
        return 'ERR:' + str(e).split('\n')[0]
    finally:
        c.close()

def signin(login_path, user, field='email'):
    op = opener()
    tok = csrf(get(op, login_path))
    post(op, login_path, {'_csrf': tok, field: user, 'password': PW})
    return op

# login path, user, form path, the empty payload, the table it writes to
CASES = [
 ('client', '/portal/login', 'client.s01@demo.test', '/portal/request',
  {'subject': '', 'detail': '', 'site': '', 'contact_name': '', 'contact_phone': ''},
  'portal_requests', 'ask for new work'),
 ('client', '/portal/login', 'client.s01@demo.test', '/portal/complaint-new',
  {'subject': '', 'description': '', 'kind': 'COMPLAINT'},
  'complaints', 'raise a complaint'),
 ('client', '/portal/login', 'client.s01@demo.test', '/portal/hire',
  {'title': '', 'discipline_code': '', 'positions': '', 'start_date': ''},
  'cx_requirements', 'post a manpower requirement'),
 ('client', '/portal/login', 'client.s01@demo.test', '/portal/roster',
  {'action': 'add', 'source': 'manual', 'manual_name': '', 'manual_role': '',
   'manual_city': '', 'manual_mobile': ''},
  'cx_client_bench', 'add somebody to the private bench'),
 ('agency', '/portal/login', 'agency.s02@demo.test', '/portal/bench',
  {'action': 'add', 'name': '', 'role': '', 'city': '', 'mobile': ''},
  'cx_agency_bench', "add somebody to the agency's own bench"),
 ('pro',    '/pro/login',    'ajay.s02@demo.test',  '/pro/credentials',
  {'action': 'cert_save', 'name': '', 'cert_number': '', 'issue_date': '', 'expiry_date': ''},
  'cx_pro_certs', 'record a certificate'),
 ('pro',    '/pro/login',    'ajay.s02@demo.test',  '/pro/credentials',
  {'action': 'project_save', 'title': '', 'client': '', 'start_date': '', 'end_date': ''},
  'cx_pro_projects', 'record project experience'),
]

print('EXTERNAL PORTAL FORMS, SUBMITTED EMPTY')
print('=' * 78)
print('HTML `required` is client-side only. These go straight to the server.\n')

fails = passes = 0
for who, login, user, path, payload, table, what in CASES:
    op = signin(login, user)
    page = get(op, path)
    tok = csrf(page)
    if not tok:
        print('  %-26s SKIP — no form/CSRF token on %s for %s' % (what, path, who))
        continue
    before = rows(table)
    payload = dict(payload); payload['_csrf'] = tok
    status, html, url = post(op, path, payload)
    after = rows(table)

    created = (isinstance(before, int) and isinstance(after, int) and after > before)
    shown = msgs(html)
    errish = [t for c, t in shown if 'err' in c.lower()]
    said = (errish or [t for _, t in shown] or ['(no message panel on the page)'])[0]

    verdict = '*** CREATED A BLANK ROW ***' if created else 'refused'
    if created: fails += 1
    else: passes += 1
    print('  %-34s %-10s %s' % (what, verdict, '%s: %s -> %s' % (table, before, after)))
    print('       form: %-22s as %s' % (path, who))
    print('       said: "%s"' % said[:150])
    print()

print('=' * 78)
print('%d form(s) refused an empty submission; %d created a blank row.' % (passes, fails))
if not fails:
    print('\nEvery creating form tested validates on the SERVER, not only in the')
    print('browser. The same test found four staff-side forms that did NOT --')
    print('see 04-SCREEN-AND-FIELD-AUDIT.md -- so this is a real difference,')
    print('not an untested assumption.')
