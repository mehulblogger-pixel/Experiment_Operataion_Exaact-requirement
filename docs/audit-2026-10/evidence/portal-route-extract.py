#!/usr/bin/env python3
"""
Inventory the three EXTERNAL portals that the audit's first route inventory
missed entirely (GAP-012): the client portal (/portal/*), the vendor portal
(/vendor/*) and the professional portal (/pro/*).

For every route it records:
  * the permission it checks (pcan / cvp_can / the pro equivalent), if any
  * whether it reads an id from the query string or the posted body
  * whether, in the same handler, that id is constrained to the signed-in
    party's own records -- the question that decides whether client A can
    read client B's data by typing a different number.

Read-only. Parses source; executes nothing. Re-run:
    python3 docs/audit-2026-10/evidence/portal-route-extract.py
"""
import re, sys, os, json

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..', 'phpapp')

PORTALS = [
    ('client',       'lib/portal.php',      'portal'),
    ('vendor',       'lib/cvp.php',         'vendor'),
    ('professional', 'lib/connect_pro.php', 'pro'),
]

# A handler is "scoped" if it ties the record back to the signed-in party.
SCOPE_HINTS = [
    r'client_id\s*=\s*\?', r'client_id=\?', r'\bclient_id\b\s*=\s*\$',
    r'vendor_id\s*=\s*\?', r'vendor_id=\?', r'\bvendor_id\b\s*=\s*\$',
    r'pro_id\s*=\s*\?',    r'pro_id=\?',    r'\bpro_id\b\s*=\s*\$',
    r'professional_id\s*=\s*\?',
    r'portal_client\(', r'cvp_vendor\(', r'connect_pro_me\(', r'cxp_me\(',
    r'\$me\[.id.\]', r'\$cl\[.id.\]', r'\$v\[.id.\]',
]
ID_HINTS = [r"\$_GET\['id'\]", r'\$_GET\["id"\]', r"\$_POST\['id'\]",
            r"\$_GET\['cid'\]", r"\$_GET\['rid'\]", r"\$_GET\['jid'\]",
            r"\$_GET\['vid'\]", r"\$_GET\['req'\]", r"\$_GET\['eng'\]"]
# The real gates, found by reading the routers: portal_need() / cvp_vendor_need()
# wrap the permission check and redirect with a message; pcan() is the raw test.
PERM_RE = re.compile(
    r"(?:portal_need|cvp_vendor_need|pcan|cvp_can|cxp_can|connect_pro_can|pro_can)"
    r"\s*\(\s*'([^']+)'")
LOGIN_ONLY = re.compile(r"(?:connect_pro_me|cxp_me|portal_user|cvp_vendor)\s*\(")
# Calls to the portal's own helpers, so an owner constraint that lives one level
# down -- which is where this codebase deliberately puts it -- is still seen.
HELPER_RE = re.compile(r"\b((?:portal|cvp|cxp|connect_pro|pro)_[a-z0-9_]+)\s*\(")

def helper_bodies(src):
    """name -> body, for every function defined in this file."""
    out = {}
    marks = [(m.start(), m.group(1)) for m in
             re.finditer(r'^function\s+([a-z0-9_]+)\s*\(', src, re.M)]
    for i, (pos, nm) in enumerate(marks):
        end = marks[i + 1][0] if i + 1 < len(marks) else len(src)
        out[nm] = src[pos:end]
    return out


def handlers(src, prefix):
    """Split the router into (route, body) pairs on its case/if dispatch."""
    pat = re.compile(
        r"(?:case|if\s*\(\s*\$route\s*===)\s*'(" + re.escape(prefix) + r"(?:/[a-z0-9\-/]*)?)'",
        re.I)
    marks = [(m.start(), m.group(1)) for m in pat.finditer(src)]
    out = []
    for i, (pos, route) in enumerate(marks):
        end = marks[i + 1][0] if i + 1 < len(marks) else len(src)
        out.append((route, src[pos:end]))
    return out

rows = []
for name, rel, prefix in PORTALS:
    path = os.path.join(ROOT, rel)
    if not os.path.exists(path):
        print('MISSING: ' + rel, file=sys.stderr); continue
    src = open(path, encoding='utf-8', errors='replace').read()
    fns = helper_bodies(src)
    for route, body in handlers(src, prefix):
        perms = sorted(set(PERM_RE.findall(body)))
        takes_id = any(re.search(p, body) for p in ID_HINTS)
        scoped = any(re.search(p, body) for p in SCOPE_HINTS)
        via = ''
        if takes_id and not scoped:
            # Follow the helpers this handler calls (and the helpers THEY call):
            # the owner constraint is deliberately placed in the read helper's
            # WHERE clause, not repeated in the handler.
            seen, queue = set(), list(HELPER_RE.findall(body))
            while queue:
                h = queue.pop(0)
                if h in seen or h not in fns:
                    continue
                seen.add(h)
                hb = fns[h]
                if any(re.search(pp, hb) for pp in SCOPE_HINTS):
                    scoped, via = True, h
                    break
                queue += HELPER_RE.findall(hb)
        rows.append({
            'portal': name, 'file': rel, 'route': '/' + route,
            'perm': ','.join(perms) if perms else
                    ('(login only)' if LOGIN_ONLY.search(body) else ''),
            'takes_id': takes_id, 'scoped': scoped, 'scoped_via': via,
            'lines': body.count('\n') + 1,
        })

# ---- report ---------------------------------------------------------------
print('PORTAL ROUTE INVENTORY  (routes the first inventory missed -- GAP-012)')
print('=' * 78)
for name, rel, prefix in PORTALS:
    sub = [r for r in rows if r['portal'] == name]
    print('\n%s portal  --  %s  --  %d routes' % (name.upper(), rel, len(sub)))
    print('-' * 78)
    print('%-30s %-26s %-4s %s' % ('ROUTE', 'PERMISSION CHECKED', 'ID?', 'OWNER CONSTRAINT'))
    for r in sorted(sub, key=lambda x: x['route']):
        if not r['takes_id']:
            own = '-  (reads no id)'
        elif r['scoped_via']:
            own = 'in ' + r['scoped_via'] + '()'
        elif r['scoped']:
            own = 'in the handler'
        else:
            own = '** NONE FOUND -- PROBE **'
        print('%-30s %-26s %-4s %s' % (
            r['route'][:30], (r['perm'] or '(none)')[:26],
            'yes' if r['takes_id'] else '-', own))

print('\n' + '=' * 78)
print('TOTAL external-portal routes: %d' % len(rows))
risky = [r for r in rows if r['takes_id'] and not r['scoped']]
print('Read an id: %d' % len([r for r in rows if r['takes_id']]))
print('  ...of those, owner-constrained in the handler or a read helper: %d'
      % len([r for r in rows if r['takes_id'] and r['scoped']]))
print('  ...no constraint this scan can see -- MUST BE PROBED LIVE: %d' % len(risky))
for r in risky:
    print('   %-12s %s' % (r['portal'], r['route']))
print('\nRoutes with no permission gate beyond being signed in: %d'
      % len([r for r in rows if not r['perm']]))
for r in sorted([r for r in rows if not r['perm']], key=lambda x: (x['portal'], x['route'])):
    print('   %-12s %s' % (r['portal'], r['route']))
print('\nNOTE: this is a source scan. A "** NO **" is a CANDIDATE to be probed')
print('live, not a finding -- the constraint may sit in a helper this scan')
print('cannot see. Nothing here is a finding until it is driven in a browser.')

open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'portal-route-extract.json'), 'w').write(
    json.dumps(rows, indent=1))
