#!/usr/bin/env python3
"""Reachability, done properly: crawl FROM the menu, following in-page links.

The first attempt counted only area tiles and the "Find a screen" index, and
reported 95 screens as unreachable. That was wrong. /to-bill ("Billing
workspace") links onward to /invoices, /receipts, /receivables and /tally, so
those four are two clicks from the Money menu -- reachable, just not tiled.
Counting doors without walking through them over-reports.

This crawls to a fixed point from the real entry points and records, for every
screen, the SHORTEST click path to it. A screen with no path is unreachable for
this role; a screen 4+ clicks deep is reachable but buried, which is a finding
of its own.
"""
import re, subprocess, sys, json, collections

SP   = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad'
BASE = 'http://127.0.0.1:8791'
SKIP = re.compile(r'(^|-)(logout|delete|erase|destroy|purge|reset|restore|wipe|drop)(-|$)')
HREF = re.compile(r'href="(/[^"#]*)"')

def sh(*a): return subprocess.run(a, capture_output=True).stdout.decode('utf-8','replace')

def login(jar, user):
    subprocess.run(['rm','-f',jar])
    p = sh('curl','-s','-c',jar,f'{BASE}/login')
    m = re.search(r'name="_csrf" value="([^"]+)"', p)
    sh('curl','-s','-b',jar,'-c',jar,'-X','POST',
       '-d',f'_csrf={m.group(1)}&username={user}&password=Pw12345678&viewport=1280',
       '-o','/dev/null',f'{BASE}/login')

def get(jar, route):
    out = sh('curl','-s','-b',jar,'-w','\n@@%{http_code}',f'{BASE}/{route}')
    return out.rsplit('@@',1)[-1].strip(), out.rsplit('\n@@',1)[0]

def norm(h):
    h = h.split('?')[0].strip('/')
    return h

def crawl(user):
    jar = f'{SP}/crawl_{user}.txt'
    login(jar, user)
    #  SEED FROM THE WHOLE MENU, not from '/'. The first run seeded on the
    #  dashboard alone and found nothing, because '/' redirects for a user with
    #  a configured start page -- so the crawl ended before it began. The rail
    #  is the real entry point, and every area home is on it.
    seeds = ['', 'my-work', 'search', 'sales', 'marketplace', 'operations',
             'recruitment', 'quality', 'reporting', 'money', 'insights',
             'directory', 'admin', 'my-jobs', 'documents', 'vouchers']
    seen, depth, order = {}, {}, []
    for s0 in seeds:
        seen[s0] = 0; depth[s0] = 0; order.append(s0)
    i = 0
    while i < len(order):
        route = order[i]; i += 1
        if depth[route] >= 4:            # far enough to call anything deeper "buried"
            continue
        code, html = get(jar, route)
        if code == '302':
            c2,_ = get(jar,'my-work')
            if c2 == '302': login(jar, user); code, html = get(jar, route)
        if code != '200':
            continue
        for h in HREF.findall(html):
            n = norm(h)
            if n in seen or SKIP.search(n) or n.startswith(('http','//')):
                continue
            seen[n] = depth[route] + 1; depth[n] = depth[route] + 1
            order.append(n)
        if i % 60 == 0: print(f'  {user}: visited {i}, found {len(seen)}', file=sys.stderr)
    return depth

routes = {r.strip() for r in open(f'{SP}/routes_all.txt') if r.strip() and not SKIP.search(r.strip())}
out = {}
for user in sys.argv[1:]:
    d = crawl(user)
    out[user] = d
    reach = routes & set(d)
    print(f'{user:16} dispatched={len(routes)}  reached={len(reach)}  unreached={len(routes-set(d))}')
json.dump(out, open(f'{SP}/crawl.json','w'))
