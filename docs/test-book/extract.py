#!/usr/bin/env python3
"""Test book — measure every screen as it renders today.

Visits each GET route (and each record screen with a real sample id) as the
administrator on the throwaway local copy, and records only the screen's own
content (inside <main>): heading, breadcrumb, tabs, section headings, every
form with its fields (visible label, type, required, choices) and buttons,
other buttons and links styled as buttons, collapsible sections, tables.
Writes screens_today.json.
"""
import json, os, re, sqlite3, subprocess, sys
from html.parser import HTMLParser

SP = os.path.dirname(os.path.abspath(__file__))
BASE = 'http://127.0.0.1:8899'
DB = os.path.join(os.path.dirname(SP), 'tb.sqlite')
EV = '/home/user/Experiment_Operataion_Exaact-requirement/docs/audit-2026-10/evidence'
APP = '/home/user/Experiment_Operataion_Exaact-requirement/phpapp'
SKIP = re.compile(r'(^|-)(logout|signout|impersonate|switch|login|sso|view-as|act-as|lock|delete|erase|destroy|purge|reset|restore|wipe|drop|export|download|pdf|print|csv|xlsx|backup)(-|$)')
JAR = os.path.join(SP, 'admin.ck')


def sh(*a):
    return subprocess.run(a, capture_output=True).stdout.decode('utf-8', 'replace')


def login(user='admin', pw='Pw12345678'):
    subprocess.run(['rm', '-f', JAR])
    h = sh('curl', '-s', '-c', JAR, f'{BASE}/login')
    m = re.search(r'name="_csrf" value="([^"]+)"', h)
    sh('curl', '-s', '-b', JAR, '-c', JAR, '-X', 'POST', '-d',
       f'_csrf={m.group(1) if m else ""}&username={user}&password={pw}&viewport=1280', '-o', '/dev/null', f'{BASE}/login')
    return sh('curl', '-s', '-b', JAR, '-o', '/dev/null', '-w', '%{http_code}', f'{BASE}/admin').strip() == '200'


def get(path, retry=True):
    out = os.path.join(SP, 'page.html')
    code = sh('curl', '-s', '-b', JAR, '-c', JAR, '--max-time', '25', '-o', out, '-w', '%{http_code}', f'{BASE}/{path}').strip()
    try:
        html = open(out, encoding='utf-8', errors='replace').read()
    except OSError:
        html = ''
    # Signed out by something earlier in the crawl: sign in again and retry once.
    if retry and (code == '302' or 'name="password"' in html) and not path.startswith('login'):
        redir = sh('curl', '-s', '-b', JAR, '-o', '/dev/null', '-w', '%{redirect_url}', f'{BASE}/{path}').strip()
        if 'login' in redir or 'name="password"' in html:
            login(); return get(path, False)
    return code, html


def clean(s):
    return re.sub(r'\s+', ' ', s or '').strip()


class Screen(HTMLParser):
    VOID = {'input', 'br', 'img', 'hr', 'meta', 'link', 'source', 'wbr', 'col', 'area', 'base', 'embed', 'param', 'track'}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.depth_main = 0; self.in_main = False
        self.h1 = ''; self.sub = ''; self.crumbs = []
        self.tabs = []; self.sections = []; self.forms = []; self.buttons = []; self.tables = []; self.details = []
        self.labels_for = {}           # id -> label text
        self.stack = []                # open tags in main
        self.text_target = None        # (kind, obj)
        self.cur_form = None
        self.cur_label = None          # [text, for, fields_inside]
        self.cur_select = None
        self.cur_option = None
        self.cur_table = None; self.in_th = False
        self.last_text = ''            # recent visible text (for label fallback)
        self.caps = []                 # open captures: [tag, attrs, text]
        self.in_crumbs = False
        self.pending_label = ''        # a <label> with no for= and no field inside: names the next field
        self.td_index = -1; self.cur_cols = []
        self.links = []

    def attrs(self, a):
        return {k: (v or '') for k, v in a}

    def handle_starttag(self, tag, a):
        A = self.attrs(a)
        cls = A.get('class', '')
        if tag == 'nav' and 'crumbs' in cls: self.in_crumbs = True
        if tag == 'main': self.in_main = True
        if not self.in_main and not self.in_crumbs: return
        if tag not in self.VOID: self.stack.append((tag, A))
        if 'data-tab' in A and A['data-tab'] and A['data-tab'] not in self.tabs: self.tabs.append(A['data-tab'])
        if tag in ('h1', 'h2', 'h3', 'summary', 'label', 'option', 'th', 'button', 'a', 'legend') or (tag == 'p' and 'sub' in cls and not self.sub):
            self.caps.append([tag, A, ''])
        if tag == 'form':
            self.cur_form = {'action': A.get('action', ''), 'method': (A.get('method') or 'get').lower(), 'fields': [], 'buttons': []}
            self.forms.append(self.cur_form)
        if tag == 'label':
            self.cur_label = {'text': '', 'for': A.get('for', ''), 'fields': []}
        if tag in ('input', 'select', 'textarea'):
            t = (A.get('type') or ('select' if tag == 'select' else 'textarea' if tag == 'textarea' else 'text')).lower()
            if tag == 'input' and t in ('submit', 'button', 'image', 'reset'):
                if A.get('value') and self.cur_form is not None: self.cur_form['buttons'].append(clean(A['value']))
                return
            if t == 'hidden' or A.get('name') in ('_csrf', 'q', None, ''): return
            f = {'name': A.get('name', ''), 'type': t, 'required': 'required' in A, 'placeholder': clean(A.get('placeholder', '')),
                 'aria': clean(A.get('aria-label', '')), 'id': A.get('id', ''), 'label': '', 'max': A.get('max', ''), 'min': A.get('min', ''),
                 'maxlength': A.get('maxlength', ''), 'options': [], 'disabled': 'disabled' in A, 'readonly': 'readonly' in A,
                 'near': self.last_text[-80:]}
            if self.cur_label is not None: self.cur_label['fields'].append(f)
            elif self.pending_label: f['label'] = self.pending_label; self.pending_label = ''
            elif self.cur_table is not None and self.cur_table['cols'] and 0 <= self.td_index < len(self.cur_table['cols']):
                f['label'] = self.cur_table['cols'][self.td_index]
            (self.cur_form['fields'] if self.cur_form is not None else self.orphans()).append(f)
            if tag == 'select': self.cur_select = f
        if tag == 'table':
            self.cur_table = {'cols': [], 'caption': ''}; self.tables.append(self.cur_table)
        if tag == 'tr': self.td_index = -1
        if tag in ('td', 'th') and self.cur_table is not None: self.td_index += 1
        if tag in ('h1', 'h2', 'h3', 'table'): self.pending_label = ''
        if tag == 'a' and A.get('href', '').startswith('/'): self.links.append(A['href'])
        if tag == 'details': self.details.append('')
        if tag == 'tr' and self.cur_table is not None and self.cur_table['cols']: self.cur_table['done'] = True

    def orphans(self):
        if not self.forms or self.forms[0].get('action') != '(no form)':
            self.forms.insert(0, {'action': '(no form)', 'method': '', 'fields': [], 'buttons': []})
        return self.forms[0]['fields']

    def handle_endtag(self, tag):
        if tag == 'nav' and self.in_crumbs: self.in_crumbs = False
        if not self.in_main and not self.in_crumbs: return
        if self.caps and self.caps[-1][0] == tag:
            t, A, txt = self.caps.pop(); txt = clean(txt)
            cls = A.get('class', '')
            if t == 'h1' and not self.h1: self.h1 = txt
            elif t in ('h2', 'h3', 'legend') and txt: self.sections.append(txt)
            elif t == 'summary' and txt:
                if self.details: self.details[-1] = txt
            elif t == 'p': self.sub = txt
            elif t == 'label' and self.cur_label is not None:
                self.cur_label['text'] = txt
            elif t == 'option' and self.cur_select is not None and len(self.cur_select['options']) < 40 and txt:
                self.cur_select['options'].append(txt)
            elif t == 'th' and self.cur_table is not None and not self.cur_table.get('done') and txt:
                self.cur_table['cols'].append(txt)
            elif t == 'button' and txt:
                (self.cur_form['buttons'] if self.cur_form is not None else self.buttons).append(txt)
            elif t == 'a':
                if self.in_crumbs:
                    if txt: self.crumbs.append(txt)
                elif ('btn' in cls or A.get('role') == 'tab' or 'tab=' in A.get('href', '')) and txt:
                    if A.get('role') == 'tab' or ('tab=' in A.get('href', '') and 'btn' not in cls):
                        if txt not in self.tabs: self.tabs.append(txt)
                    else:
                        self.buttons.append(txt + '  →  ' + re.sub(r'=\d+', '=…', A.get('href', '')[:80]))
        if tag == 'label' and self.cur_label is not None:
            L = self.cur_label; self.cur_label = None
            if L['for']: self.labels_for[L['for']] = L['text']
            elif not L['fields']: self.pending_label = L['text']
            for f in L['fields']:
                if not f['label']: f['label'] = L['text']
        if tag == 'select': self.cur_select = None
        if tag == 'form': self.cur_form = None
        if tag == 'table': self.cur_table = None
        if tag == 'main': self.in_main = False
        if self.stack and self.stack[-1][0] == tag: self.stack.pop()

    def handle_data(self, d):
        if not self.in_main and not self.in_crumbs: return
        for c in self.caps: c[2] += d
        t = clean(d)
        if t: self.last_text = (self.last_text + ' ' + t)[-200:]

    def result(self):
        for fm in self.forms:
            for f in fm['fields']:
                if not f['label'] and f['id'] in self.labels_for: f['label'] = self.labels_for[f['id']]
                if not f['label']: f['label'] = f['aria'] or f['placeholder'] or (f['options'][0] if f['options'] else '') or f['name'].replace('_', ' ').replace('[]', '').strip().capitalize()
                f.pop('near', None) if f['label'] else None
        for t in self.tables: t.pop('done', None)
        return {'h1': self.h1, 'sub': self.sub, 'crumbs': self.crumbs, 'tabs': self.tabs, 'sections': self.sections,
                'collapsibles': [d for d in self.details if d], 'forms': [f for f in self.forms if f['fields'] or f['buttons']],
                'buttons': list(dict.fromkeys(self.buttons)), 'tables': [t for t in self.tables if t['cols']],
                'links': list(dict.fromkeys(self.links))}


def parse(html):
    p = Screen()
    try:
        p.feed(html)
    except Exception as e:
        return {'error': str(e)}
    return p.result()


def route_map():
    php = ("<?php error_reporting(0); $_SERVER['HTTP_HOST']='localhost'; $idx=file_get_contents('index.php');"
           "preg_match_all(\"#require __DIR__ \\\\. '(/lib/[a-z0-9_]+\\\\.php)';#i\",$idx,$m); foreach($m[1] as $r) require_once __DIR__.$r;"
           "echo json_encode(ops_route_module_map());")
    open(os.path.join(APP, '.tb_routes.php'), 'w').write(php)
    out = subprocess.run(['php', '.tb_routes.php'], cwd=APP, capture_output=True, env={**os.environ, 'DB_DRIVER': 'sqlite', 'SQLITE_PATH': DB}).stdout.decode()
    os.remove(os.path.join(APP, '.tb_routes.php'))
    try:
        return json.loads(out[out.index('{'):])
    except Exception:
        return {}


# record screen -> (table, extra query)  : opened with the first real id
RECORDS = {
    'lead': 'leads', 'opportunity': 'opportunities', 'quote': 'quotations', 'quote-edit': 'quotations',
    'inquiry-edit': 'crm_inquiries', 'project-costing': 'project_costings', 'contract': 'partner_contracts',
    'partner': 'business_partners', 'client': 'business_partners', 'call': 'calls', 'call-edit': 'calls', 'job': 'jobs',
    'job-edit': 'jobs', 'voucher': 'vouchers', 'candidate': 'candidates', 'hiring-request': 'hiring_requests',
    'requisition': 'requisitions', 'invoice': 'invoices', 'document': 'documents', 'ncr': 'nonconformities',
    'pipeline': 'pipelines', 'inspector': 'inspectors', 'vendor': 'business_partners', 'po': 'partner_purchase_orders',
    'quote-revise': 'quotations', 'lead-convert': 'leads', 'capa': 'capas', 'complaint': 'complaints',
}
EDITABLE = {'quote-edit': "WHERE status='DRAFT' AND is_current=1", 'quote-revise': "WHERE status='SENT'", 'lead-convert': "WHERE status='OPEN'",
            'call-edit': "WHERE 1=1", 'job-edit': "WHERE 1=1"}


def first_id(table, where=''):
    try:
        c = sqlite3.connect(DB)
        r = c.execute(f'SELECT id FROM {table} {where} ORDER BY id LIMIT 1').fetchone()
        return r[0] if r else None
    except Exception:
        return None


def main():
    if not login(): print('login failed'); sys.exit(1)
    rmap = route_map()
    routes = set(rmap.keys())
    for line in open(os.path.join(EV, 'routes-dispatched.txt')):
        r = line.strip().split('\t')[0]
        if r: routes.add(r)
    routes |= {'sales', 'sales-setup', 'quote-delegations', 'industry', 'quote-approval-rules', 'preorder-checklist'}
    out = {}
    for r in sorted(routes):
        if SKIP.search(r) or not re.match(r'^[a-z0-9-]+$', r): continue
        path = r
        if r in RECORDS:
            i = first_id(RECORDS[r], "WHERE is_vendor=1" if r == 'vendor' else EDITABLE.get(r, ''))
            if i is None: continue
            path = f'{r}?id={i}'
        code, html = get(path)
        if code != '200' or '<main' not in html: continue
        if re.search(r"don’t have access|do not have permission|Not found</h1>", html): continue
        d = parse(html)
        if not d.get('h1'): continue
        d['route'] = r; d['path'] = path; d['module'] = rmap.get(r, '')
        out[r] = d
        if len(out) % 60 == 0: login()   # keep the session fresh on a long crawl
    # Second pass: every record screen reached by a real link on a screen already seen.
    seen_links = {}
    for d in out.values():
        for h in d.get('links', []):
            m = re.match(r'^/([a-z0-9-]+)\?(.*\bid=\d+.*)$', h)
            if m and m.group(1) not in out and not SKIP.search(m.group(1)): seen_links.setdefault(m.group(1), h[1:])
    for r, path in sorted(seen_links.items()):
        code, html = get(path)
        if code != '200' or '<main' not in html or re.search(r"don’t have access|do not have permission|Not found</h1>", html): continue
        d = parse(html)
        if not d.get('h1'): continue
        d['route'] = r; d['path'] = path; d['module'] = rmap.get(r, ''); out[r] = d
    json.dump(out, open(os.path.join(SP, 'screens_today.json'), 'w'), indent=0)
    nf = sum(len(f['fields']) for d in out.values() for f in d['forms'])
    nb = sum(len(d['buttons']) + sum(len(f['buttons']) for f in d['forms']) for d in out.values())
    print(f'screens {len(out)}  fields {nf}  buttons {nb}  tabs {sum(len(d["tabs"]) for d in out.values())}')


if __name__ == '__main__':
    main()
