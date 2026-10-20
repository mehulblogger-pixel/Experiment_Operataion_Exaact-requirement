#!/usr/bin/env python3
"""Assemble the test book data (book.json) from:
  - sales_cases.py (hand-written Sales cases)
  - cases_*.json   (other areas and cross-module flows)
  - screens_today.json (every screen, measured) -> one "screen check" per screen
  - access_today.json  (who could open each screen on the copy, by role)
  - the left-navigation map
"""
import json, os, re, html, glob, importlib.util
from collections import OrderedDict, Counter

SP = os.path.dirname(os.path.abspath(__file__))
EV = '/home/user/Experiment_Operataion_Exaact-requirement/docs/audit-2026-10/evidence'
AREAS = ['Start here', 'Sales', 'Operations', 'Recruitment', 'Marketplace', 'Quality & Accreditation', 'Reporting', 'Money', 'Insights',
         'Directory', 'Admin', 'Across modules', 'Your own pages']
ROLE_NAMES = {
    'MASTER_ADMIN': 'Master Admin', 'BUSINESS_DIRECTOR': 'Business Director', 'SBU_HEAD': 'Business Unit Head',
    'BRANCH_MANAGER': 'Branch Manager', 'BRANCH_APP_MANAGER': 'Branch Application Manager', 'OPERATION_MANAGER': 'Operation Manager',
    'ASST_MANAGER': 'Asst. Manager', 'COORDINATOR': 'Coordinator', 'BUSINESS_DEV_MANAGER': 'Business Development Manager',
    'KEY_ACCOUNTS_MANAGER': 'Key Accounts Manager', 'MARKETING_MANAGER': 'Marketing Manager', 'MARKETING_EXECUTIVE': 'Marketing Executive',
    'FINANCE': 'Finance', 'QUALITY_HEAD': 'Quality Head', 'TECHNICAL_HEAD': 'Technical Head', 'SR_INSPECTOR': 'Senior Inspector',
    'INSPECTOR': 'Inspector', 'ADMIN': 'Admin (legacy)', 'several': 'Several people',
}

PERSONAS = [
    ('MASTER_ADMIN', 'Owns the company set-up: users, roles, modules, masters, approval rules. Desk.'),
    ('BUSINESS_DIRECTOR', 'Sees every branch; approves the largest quotations; reads Insights. Desk and phone.'),
    ('SBU_HEAD', 'Runs one business unit across branches. Desk.'),
    ('BRANCH_MANAGER', 'Runs a branch: approves its quotations and contract openings, its people and work. Desk.'),
    ('BRANCH_APP_MANAGER', 'Keeps the branch\'s application set-up and users in order. Desk.'),
    ('OPERATION_MANAGER', 'Runs work orders and jobs; endorses contract openings and amendments. Desk.'),
    ('ASST_MANAGER', 'Helps run operations: calls, allocation, availability. Desk.'),
    ('COORDINATOR', 'Raises work orders, allocates inspectors, closes jobs. Desk.'),
    ('BUSINESS_DEV_MANAGER', 'Finds and wins new customers: leads, deals, quotations. Desk and phone.'),
    ('KEY_ACCOUNTS_MANAGER', 'Looks after existing big customers: deals, quotations, follow-ups. Desk and phone.'),
    ('MARKETING_MANAGER', 'Leads the sales team: approves quotations, sets Sales up, reopens lost work. Desk.'),
    ('MARKETING_EXECUTIVE', 'Records leads and enquiries, prepares quotations, follows up. Desk and phone.'),
    ('FINANCE', 'Registers contracts, invoices, receipts, reconciliations, month-end. Desk.'),
    ('QUALITY_HEAD', 'Owns complaints, nonconformities, audits, accreditation records. Desk.'),
    ('TECHNICAL_HEAD', 'Owns methods, competence, technical review of reports. Desk.'),
    ('SR_INSPECTOR', 'Does inspections and reviews juniors\' reports. Phone first, in the field.'),
    ('INSPECTOR', 'Does inspections, fills reports and vouchers. Phone first, in the field.'),
]
COMPANIES = [
    ('A', 'Third-party inspection agency', 'Services by man-day; ISO/IEC 17020; multi-branch.', 'Inspection, testing & certification'),
    ('B', 'Testing & calibration laboratory', 'Tests per sample and per certificate; ISO/IEC 17025.', 'Testing & calibration laboratory'),
    ('C', 'Manpower supply / deputation', 'People on customer sites by the month; costing first.', 'Recruitment & staffing'),
    ('D', 'Manufacturer selling products', 'Items in Nos/kg with HSN codes and goods GST — expect gaps.', 'Manufacturing & engineering'),
    ('E', 'Trader / distributor (products + services)', 'Goods plus installation on one quotation.', 'Trading & distribution'),
    ('F', 'EPC / project company', 'Lump-sum milestones plus site supervision.', 'Construction, projects & infrastructure'),
    ('G', 'One-person consultancy', 'No approval levels at all.', 'Professional & consulting services'),
    ('H', 'Large multi-branch company', 'Several branches and business units; branch walls matter.', 'Inspection, testing & certification'),
]


def clean(s):
    return html.unescape(re.sub(r'\s+', ' ', s or '')).strip()


def load_sales():
    spec = importlib.util.spec_from_file_location('sales_cases', os.path.join(SP, 'sales_cases.py'))
    m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
    return m.CASES


def nav_index():
    n = json.load(open(os.path.join(EV, 'navigation-dump.json')))
    idx = {}
    for area, v in n['areas'].items():
        for sec in v['sections']:
            for t in sec['tiles']:
                r = t['route'].lstrip('/').split('?')[0]
                idx.setdefault(r, (area, t['label']))
        for r in v.get('routes', []):
            idx.setdefault(r, (area, None))
    return idx


def area_of(route, d, nav):
    if route in nav: return nav[route][0]
    cr = [clean(c) for c in d.get('crumbs', [])]
    if len(cr) > 1 and cr[1] in AREAS: return cr[1]
    if route.startswith('my-') or route in ('change-password', 'notifications', 'search', 'my-signature', 'privacy', 'agreement'): return 'Your own pages'
    if route in ('sales',): return 'Sales'
    for a in AREAS:
        if route == a.lower().split(' ')[0]: return a
    if route.startswith(('contract', 'partner', 'client', 'vendor', 'po')): return 'Directory'
    if route.startswith(('marketplace', 'connect', 'rating', 'cx-')): return 'Marketplace'
    if route.startswith(('incident', 'consent', 'data-request', 'compliance')): return 'Quality & Accreditation'
    if route.startswith(('billing', 'subscription', 'books', 'financial')): return 'Money'
    return 'Admin'


# Sales screens file under the same module names as the hand-written Sales tests.
SALES_MODULES = [(r'^(leads|lead|lead-new|lead-convert|ads-roi|adspro.*)$', 'Leads'), (r'^opportunit', 'Opportunities'),
                 (r'^(pipelines|pipeline|stage-gate.*)$', 'Pipelines & funnels'), (r'^(approvals|approval-act)$', 'Approvals'),
                 (r'^inquir', 'Inquiries'), (r'^(quote-approval-rule.*|quote-delegations|sales-setup)$', 'Approvals'),
                 (r'^(quotes|quote|quote-new|quote-edit|quote-external|quote-revise)$', 'Quotes'), (r'^preorder', 'Pre-order checklist'),
                 (r'^project-costing', 'Project costing'), (r'^(templates|crm-template.*)$', 'Quote templates'),
                 (r'^(crm-dashboard|crm-reports)$', 'Sales dashboard'), (r'^sales$', 'Sales home')]


def module_of(route, d, nav):
    for pat, name in SALES_MODULES:
        if re.search(pat, route): return name
    if route in nav and nav[route][1]: return nav[route][1]
    cr = [clean(c) for c in d.get('crumbs', [])]
    if len(cr) > 2: return cr[2]
    # A record screen's heading is a sample record's name — name it by what it is instead.
    if '?id=' in d['path'] or re.search(r'-(edit|new)$', route):
        return route.replace('-', ' ').capitalize()
    return clean(d['h1'])[:60]


EDGE = {
    'text': ('a normal value; then leave it empty; then 300 characters; then special characters  & < > " \' ₹ ; then Hindi/Gujarati text (नमस्ते / નમસ્તે)',
             'Normal value saves and shows exactly as typed. {req}A 300-character value is cut or refused with a message. Special characters and Indian scripts show exactly as typed, never as code. A refusal keeps everything else you typed.'),
    'textarea': ('several lines with line breaks; then 3,000 characters; then leave it empty',
                 'Line breaks are kept when the record is reopened and printed. {req}Very long text is kept or refused with a message — never a blank page.'),
    'number': ('a normal value; then 0; then a negative number (-1); then a decimal (0.5); then a very large number (9999999999); then letters',
               'Normal value saves. 0 and negatives are accepted only where they make sense, otherwise refused with a message. Decimals keep their paise. Very large numbers show with Indian grouping (99,99,99,999). Letters are refused. {req}'),
    'email': ('a valid e-mail; then "abc@" ; then capitals ABC@EXAMPLE.TEST',
              'Valid saves. "abc@" is refused with a message. {req}'),
    'tel': ('a 10-digit mobile; then +91 98250 12345; then letters',
            'Numbers save as typed. Letters are refused or warned. {req}'),
    'date': ('today; then a past date (01-01-2019); then a far future date (31-12-2035); then leave it empty',
             'Dates save and show in your date format. Past and future dates are accepted only where they make sense. {req}Where there is a matching end/"to" date, an end before the start is refused.'),
    'month': ('this month; then a past month', 'Saves and filters by that month.'),
    'select': ('each choice in turn{opts}', 'Each choice saves and is still selected when the record is reopened. {req}'),
    'checkbox': ('tick it, save; untick it, save', 'The tick is remembered both ways.'),
    'radio': ('each choice in turn, saving each time', 'The chosen answer is remembered.'),
    'file': ('one allowed file; then several at once; then a file that is too large; then a file type that should not be allowed (.exe)',
             'Allowed files attach and are listed. Too-large and wrong-type files are refused with a message naming the limit. {req}'),
    'password': ('a password; check it is hidden while typing', 'Typed characters are hidden.'),
    'search': ('a word that matches; then a word that matches nothing; then special characters', 'Matching rows only; "nothing found" message for no matches; no error for special characters.'),
    'color': ('pick a colour', 'Saved.'),
    'time': ('a time', 'Saved.'),
    'url': ('a valid web address; then "not a url"', 'Valid saves; invalid refused.'),
}


def field_step(f):
    t = f['type'] if f['type'] in EDGE else 'text'
    do, exp = EDGE[t]
    opts = ''
    if t == 'select' and f.get('options'):
        o = [clean(x) for x in f['options'] if clean(x)]
        opts = ': ' + ', '.join(o[:25]) + (f' … ({len(o)} choices)' if len(o) > 25 else f' ({len(o)} choices)')
    req = 'It is required: leaving it empty is refused with a message saying so. ' if f.get('required') else ''
    lab = clean(f['label'])[:120] or f['name']
    flags = (' · required' if f.get('required') else '') + (' · read-only' if f.get('readonly') or f.get('disabled') else '')
    return {'do': f'Field "{lab}" ({t}{flags}) — try {do.format(opts=opts)}.',
            'expect': exp.format(req=req).strip()}


def screen_case(route, d, nav, access, seq):
    area = area_of(route, d, nav); module = module_of(route, d, nav)
    if any(re.search(p, route) for p, _ in SALES_MODULES): area = 'Sales'
    h1 = clean(d['h1'])
    cr = [clean(c) for c in d.get('crumbs', [])]
    where = ' → '.join(cr[1:] + [h1]) if len(cr) > 1 else h1
    steps = []
    nav_hint = f'Left menu → {nav[route][0]} → {nav[route][1]}' if route in nav and nav[route][1] else f'Go to {where} (or type /{d["path"]} after the site address)'
    steps.append({'do': f'Open the screen: {nav_hint}.', 'expect': f'The page heading reads "{h1}"' + (f'; the breadcrumb reads {" › ".join(cr)}' if cr else '') + '; the page loads in under 3 seconds with no error.'})
    if d.get('sub'): steps.append({'do': 'Read the line under the heading.', 'expect': f'It says: "{clean(d["sub"])[:200]}" — and it is true for what you see.'})
    for t in d.get('tabs', []):
        steps.append({'do': f'Tab "{clean(t)}" — open it.', 'expect': 'It opens to its own content; nothing typed on another tab is lost; switching back keeps the content.'})
    for c in d.get('collapsibles', []):
        steps.append({'do': f'Open the fold-out section "{clean(c)[:90]}".', 'expect': 'It opens and closes; its content belongs to it.'})
    seen_forms = set()
    for fm in d.get('forms', []):
        key = (fm['action'].split('?')[0], tuple(x['label'] for x in fm['fields']))
        if key in seen_forms and not fm['fields']: continue
        seen_forms.add(key)
        btns = [clean(b) for b in fm.get('buttons', []) if clean(b) not in ('✕',)]
        if fm['fields']:
            steps.append({'do': f'Form{(" “" + btns[-1] + "”") if btns else ""}: fill every field with valid values and press {("“" + btns[-1] + "”") if btns else "save"}.',
                          'expect': 'Saved with a confirmation message; the saved values show exactly as typed when reopened.'})
            steps.append({'do': f'Same form: press {("“" + btns[-1] + "”") if btns else "save"} with everything empty.', 'expect': 'Refused with one clear sentence naming what is missing; no half-saved record.'})
            for f in fm['fields']:
                steps.append(field_step(f))
            steps.append({'do': f'Same form: press {("“" + btns[-1] + "”") if btns else "save"} twice quickly (double-click).', 'expect': 'Saved once — never two records.'})
        for b in btns[:-1] if fm['fields'] else btns:
            steps.append({'do': f'Button "{b}" — press it.', 'expect': 'It does what its words say' + (' and asks to confirm first, because it deletes or cannot be undone' if re.search(r'delete|remove|✕|reject|cancel|withdraw|close', b, re.I) else '') + '; a message confirms what happened.'})
    for b in d.get('buttons', []):
        label, _, href = b.partition('  →  ')
        steps.append({'do': f'Button / link "{clean(label)}" — press it.', 'expect': f'It opens {("the screen at " + href.strip()) if href.strip() else "what its words say"}; Back returns here.'})
    for tb in d.get('tables', []):
        cols = [clean(c) for c in tb['cols']][:16]
        steps.append({'do': f'Table with columns: {", ".join(cols)}.', 'expect': 'Every column has a value or a dash; numbers line up; long text wraps; an empty list says so in words; on a phone the table scrolls inside itself.'})
    steps.append({'do': 'Narrow the window to phone width (about 390 pixels) or open on a phone.', 'expect': 'Readable and usable without the whole page scrolling sideways; buttons big enough to tap.'})
    opens = [ROLE_NAMES.get(r, r) for r, v in sorted(access.items()) if v.get(route) == 'OPEN']
    refused = [ROLE_NAMES.get(r, r) for r, v in sorted(access.items()) if v.get(route) == 'REFUSED']
    if access:
        steps.append({'do': 'Who may open it: sign in as each role (or ask your administrator) and open this screen.',
                      'expect': ('Measured on a test copy today — opened for: ' + (', '.join(opens) or 'nobody but the administrator') + '. Refused for: ' + (', '.join(refused) or 'nobody') +
                                 '. Check this against what your business wants (docs/02-permission-matrix.md). Record any surprise.' +
                                 (' Note: this was measured on one sample record, so a refusal can be the branch wall.' if '?id=' in d['path'] else ''))})
    # The id is the screen's own address, so a rebuild never moves saved results onto another screen.
    return {'id': f'SCR-{route}', 'area': area, 'module': module, 'title': f'Screen check — {h1[:80]}', 'kind': 'screen', 'tags': ['screen'],
            'persona': 'MASTER_ADMIN', 'company': 'ANY', 'why': 'Every tab, field and button on this screen, tried with good, bad and extreme input.',
            'before': f'Signed in as the Master Admin. Screen address: /{d["path"].split("?")[0]}' + (' (any record of this kind)' if '?id=' in d['path'] else ''),
            'steps': steps, 'ref': f'measured screen /{d["path"]}', 'live': True, 'route': route}


def load_setup():
    spec = importlib.util.spec_from_file_location('setup_guide', os.path.join(SP, 'setup_guide.py'))
    m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
    return m


def setup_cases(g):
    """One tick-off test per set-up step, so the tester can record what was done."""
    out = []
    for i, s in enumerate(g.SETTINGS, 1):
        if s['step'] == 'Test users':
            steps = [{'do': f"{u['n']}. Create {u['name']}: Username {u['user']} · Role {ROLE_NAMES[u['role']]} · Home office {u['home']} · Which team {g.TEAM[u['team']]} · Reports to {u['reports']} · Department {u['dept']}"
                            + (' · Data scope: untick "Every business unit", tick only "Industrial"' if u['scope'] == 'sbu' else ' · Data scope: tick nothing')
                            + (f" · Position & pay: {u['pay']}" if u.get('pay') else '') + ' · Permissions: tick nothing. Press "Save user".',
                      'expect': f"Saved and listed in the User register with role {ROLE_NAMES[u['role']]}. Signed in as them (private window) they reach their home screen and see {u['sees']}."}
                     for u in g.USERS]
            why = 'Every test in the book is done as one of these people. Wrong role or branch here = wrong results everywhere.'
        else:
            steps = [{'do': f"{s['where']}: {s['what']}", 'expect': 'Saved without an error; reopening the screen shows what you entered.' if s['step'] != 'Company details' else 'Every field holds your real company details.'}]
            why = 'Later tests assume this is in place.'
        out.append({'id': f'SET-{i:02d}', 'area': 'Start here', 'module': 'Set up for testing', 'title': s['step'], 'kind': 'scenario', 'tags': ['best'],
                    'persona': 'MARKETING_MANAGER' if 'Set up Sales' in s['where'] else 'MASTER_ADMIN', 'company': 'ANY', 'why': why,
                    'before': 'Read "Start here — users & settings" (left menu, top) first.', 'steps': steps, 'ref': 'setup_guide.py', 'live': s['live']})
    return out


def main():
    screens = json.load(open(os.path.join(SP, 'screens_today.json')))
    access = json.load(open(os.path.join(SP, 'access_today.json'))) if os.path.exists(os.path.join(SP, 'access_today.json')) else {}
    nav = nav_index()
    guide = load_setup()
    cases = setup_cases(guide) + list(load_sales())
    only = os.environ.get('TB_FILES')   # e.g. "cases_ops_rec_mkt.json" — publish only finished parts
    for fp in sorted(glob.glob(os.path.join(SP, 'cases_*.json'))):
        if only and os.path.basename(fp) not in only.split(','): continue
        try:
            extra = json.load(open(fp))
        except Exception as e:
            print('skip', fp, e); continue
        for c in extra:
            c.setdefault('company', 'ANY'); c.setdefault('live', True); c.setdefault('tags', []); c.setdefault('ref', '')
            c['steps'] = [s for s in c.get('steps', []) if isinstance(s, dict) and s.get('do')]
            cases.append(c)
    # screen checks: skip endpoints that only redraw another screen under a save/test/sync address
    h1s = Counter(clean(d['h1']) for d in screens.values())
    seq = 0
    for route in sorted(screens):
        d = screens[route]
        if re.search(r'-(save|test|import|sync|spend|backfill|act|drain|del|delete|apply|order|verify)$', route) and h1s[clean(d['h1'])] > 1: continue
        seq += 1
        cases.append(screen_case(route, d, nav, access, seq))
    # One name per module within an area: screen checks take the tests' spelling of it.
    canon = {}
    for c in cases:
        if c['kind'] != 'screen': canon.setdefault((c['area'], c['module'].lower()), c['module'])
    for c in cases:
        if c['kind'] == 'screen':
            k = (c['area'], c['module'].lower())
            if k in canon: c['module'] = canon[k]
            elif (c['area'], (c['area'] + ' home').lower()) in canon and c['module'].lower() == c['area'].lower():
                c['module'] = canon[(c['area'], (c['area'] + ' home').lower())]
    ids = Counter(c['id'] for c in cases)
    dupes = [i for i, n in ids.items() if n > 1]
    assert not dupes, dupes
    for c in cases:
        if c['area'] not in AREAS: c['area'] = 'Admin' if c['area'] == 'Settings' else c['area']
    areas = [a for a in AREAS if any(c['area'] == a for c in cases)] + sorted({c['area'] for c in cases} - set(AREAS))
    book = OrderedDict(
        title='EXAACT Test Book', version='2026-10-20', site='https://operations.mghaiapps.com',
        areas=areas,
        personas=[{'code': c, 'name': ROLE_NAMES[c], 'does': d} for c, d in PERSONAS],
        companies=[{'code': c, 'name': n, 'what': w, 'industry': i} for c, n, w, i in COMPANIES],
        setup=guide.book_data(ROLE_NAMES, access),
        cases=cases)
    out = os.path.join(SP, 'site', 'book.json')
    os.makedirs(os.path.dirname(out), exist_ok=True)
    json.dump(book, open(out, 'w'), ensure_ascii=False, separators=(',', ':'))
    k = Counter((c['area'], c['kind']) for c in cases)
    print('cases', len(cases), 'steps', sum(len(c['steps']) for c in cases), 'bytes', os.path.getsize(out))
    for a in areas: print(' ', a, {kk: v for (aa, kk), v in k.items() if aa == a})


if __name__ == '__main__':
    main()
