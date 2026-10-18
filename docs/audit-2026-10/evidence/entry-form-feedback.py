#!/usr/bin/env python3
"""Phase E — submit each creation form empty, and see what the person is told.

Method matters here, and two earlier attempts got it wrong:

  · Searching for class="flash" found nothing, because this application renders
    a message as <div class="msg msg-TAG">. That produced a false "silent
    refusal" reading for forms that in fact explain themselves very well.
  · Following the redirect with `curl -L` re-posted the body to the redirect
    target, which looked like a redirect loop on /internal-audit-new. There is
    no loop: a plain GET of that form returns 200 before and after.

So this does exactly what a browser does: POST without following, then ONE GET
of the redirect target, and read the message from that GET. A flash is consumed
by the first page that renders it, so it can only be read once — which is why
the sequence has to be this precise.

Writes entry-form-feedback.json. Runs against a throwaway database; it creates
records where the application allows it, which is itself the finding.
"""
import json, os, re, sqlite3, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
SP = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = os.environ.get('EXAACT_BASE', 'http://127.0.0.1:8801')
DB = os.environ.get('EXAACT_DB', f'{SP}/audit-e.sqlite')

FORMS = [('partner-new', 'business_partners'), ('user-new', 'users'),
         ('call-new', 'calls'), ('candidate-new', 'candidates'),
         ('hiring-request', 'hiring_requests'),
         ('internal-audit-new', 'internal_audits'),
         ('incident-new', 'security_incidents'),
         ('endorsement-new', 'endorsements')]

#  A screen may carry several msg divs, and some are STATIC INFORMATION panels
#  that are part of the page (e.g. partner-new's "Commercial & billing details
#  (GSTIN, PAN, payment terms...)"). Taking the first one reported that panel as
#  the refusal message, which was wrong twice. So every msg div is collected and
#  the error/warning ones are preferred, falling back to info only if there is
#  nothing stronger.
MSG = re.compile(r'class="msg msg-([a-z]+)"[^>]*>([^<]{0,240})')
PRIORITY = {'error': 0, 'danger': 0, 'warning': 1, 'warn': 1, 'success': 2, 'info': 3}


def message_of(html):
    found = [(PRIORITY.get(tag, 4), tag, re.sub(r'\s+', ' ', txt).strip())
             for tag, txt in MSG.findall(html)]
    found = [f for f in found if f[2]]
    if not found:
        return '', ''
    found.sort(key=lambda x: x[0])
    return found[0][1], found[0][2]


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def csrf(h):
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    return m.group(1) if m else ''


def count(t):
    c = sqlite3.connect(DB)
    try:
        return c.execute(f'select count(*) from {t}').fetchone()[0]
    except Exception:
        return None
    finally:
        c.close()


jar = f'{SP}/eff.ck'
subprocess.run(['rm', '-f', jar])
sh('curl', '-s', '-b', jar, '-c', jar, '-X', 'POST', '-d',
   f'_csrf={csrf(sh("curl", "-s", "-c", jar, f"{BASE}/login"))}'
   f'&username=admin&password=Pw12345678&viewport=1280', '-o', '/dev/null', f'{BASE}/login')
if sh('curl', '-s', '-b', jar, '-o', '/dev/null', '-w', '%{http_code}', f'{BASE}/admin').strip() != '200':
    print('cannot sign in', file=sys.stderr)
    sys.exit(1)

out = {}
print(f'{"form":<21}{"outcome":<22}{"what the person is told"}')
print('-' * 104)
for route, tbl in FORMS:
    page = sh('curl', '-s', '-b', jar, '-c', jar, f'{BASE}/{route}')
    if 'name="_csrf"' not in page:
        continue
    before = count(tbl)
    #  KEEP THE POST'S OWN BODY. Three of these forms answer a bad submit with
    #  200 and re-render themselves with the message inline — they do not
    #  redirect. Discarding that body (an earlier version used -o /dev/null) read
    #  as "refused silently" for two forms that in fact explain themselves
    #  clearly. So the POST body is searched FIRST, and only a redirect sends us
    #  on to look at the next page.
    body = sh('curl', '-s', '-b', jar, '-c', jar, '-D', f'{SP}/.hdr',
              '-X', 'POST', '-d', f'_csrf={csrf(page)}', f'{BASE}/{route}')
    hdrs = open(f'{SP}/.hdr', encoding='utf-8', errors='replace').read()
    code = (re.search(r'HTTP/\S+\s+(\d+)', hdrs) or [None, '?'])[1]
    loc = (re.search(r'(?im)^location:\s*(\S+)', hdrs) or [None, ''])[1]
    tag, msg = message_of(body)
    if not msg and loc:
        # It redirected: the message is waiting for the page it sent us to.
        nxt = sh('curl', '-s', '-b', jar, '-c', jar,
                 f'{BASE}{loc}' if loc.startswith('/') else loc)
        tag, msg = message_of(nxt)
    after = count(tbl)
    created = (before is not None and after is not None and after > before)
    if created:
        outcome = '*** RECORD CREATED ***'
    elif msg:
        outcome = f'refused ({tag})'
    else:
        outcome = 'refused, SILENTLY'
    out[route] = {'table': tbl, 'http': code, 'redirect_to': loc,
                  'created': created, 'tag': tag, 'message': msg}
    print(f'{route:<21}{outcome:<22}{msg[:62] if msg else "— nothing —"}')

json.dump(out, open(os.path.join(HERE, 'entry-form-feedback.json'), 'w'), indent=1)
print('\nwritten to entry-form-feedback.json')
