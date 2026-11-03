#!/usr/bin/env python3
"""Printable EXAACT Test Book: one HTML per volume (Start here + one per area) and
one combined HTML, every test fully expanded, colour-coded, with tick boxes.
Usage: python3 print_book.py <book.json> <out_dir>"""
import json, sys, os, html, re, collections, datetime

book = json.load(open(sys.argv[1]))
OUT = sys.argv[2]
os.makedirs(OUT, exist_ok=True)
E = lambda s: html.escape(str(s if s is not None else ''))
BUILT = datetime.date.today().strftime('%d %b %Y')

AREA_COLOURS = {
    'Start here': '#475569', 'New company': '#0f766e', 'Sales': '#2563eb', 'Operations': '#ea580c',
    'Recruitment': '#7c3aed', 'Marketplace': '#db2777', 'Quality & Accreditation': '#059669',
    'Reporting': '#0891b2', 'Money': '#ca8a04', 'Insights': '#4f46e5', 'Directory': '#65a30d',
    'Admin': '#dc2626', 'Across modules': '#9333ea', 'Your own pages': '#0d9488',
}
KIND = {  # label, colour
    'scenario': ('Scenario', '#2563eb'), 'edge': ('Edge case', '#ea580c'), 'screen': ('Screen check', '#7c3aed'),
    'access': ('Who can do it', '#dc2626'), 'flow': ('Start-to-end flow', '#0f766e'), 'phone': ('On a phone', '#16a34a'),
}
TAG = {'best': ('Best case', '#16a34a'), 'worst': ('Worst case', '#dc2626'), 'edge': ('Edge', '#d97706')}
PERSONA = {p['code']: p['name'] for p in book.get('personas', [])}
COMPANY = {c['code']: c['name'] for c in book.get('companies', [])}
AREAS = [a if isinstance(a, str) else a.get('name') for a in book['areas']]
cases = book['cases']
by_area = collections.OrderedDict((a, []) for a in AREAS)
for c in cases: by_area.setdefault(c['area'], []).append(c)

def slug(s): return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')

CSS = """
@page { size: A4; margin: 14mm 12mm 16mm 12mm; }
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { font-family: 'DejaVu Sans', 'Liberation Sans', 'Noto Color Emoji', sans-serif; font-size: 8.9pt; color: #0f172a; margin: 0; line-height: 1.38; }
h1, h2, h3 { margin: 0; }
.cover { height: 255mm; display: flex; flex-direction: column; justify-content: space-between; padding: 18mm 14mm; color: #fff; border-radius: 6mm; page-break-after: always; }
.cover .kicker { font-size: 12pt; letter-spacing: .14em; text-transform: uppercase; opacity: .85; }
.cover h1 { font-size: 34pt; line-height: 1.1; margin-top: 8mm; }
.cover .sub { font-size: 13pt; margin-top: 5mm; opacity: .95; max-width: 150mm; }
.cover .meta { font-size: 10.5pt; opacity: .95; }
.cover .stats { display: flex; gap: 6mm; margin-top: 8mm; flex-wrap: wrap; }
.cover .stat { background: rgba(255,255,255,.16); border-radius: 3mm; padding: 4mm 6mm; min-width: 34mm; }
.cover .stat b { display: block; font-size: 20pt; }
.page { page-break-before: always; }
.sec-title { font-size: 17pt; padding: 0 0 2mm; margin: 0 0 4mm; border-bottom: 1.2mm solid var(--c); color: #0f172a; }
.sec-intro { color: #334155; margin: 0 0 4mm; }
table { border-collapse: collapse; width: 100%; }
.grid th, .grid td { border: .25mm solid #cbd5e1; padding: 1.6mm 2mm; text-align: left; vertical-align: top; }
.grid th { background: #f1f5f9; font-size: 8.4pt; text-transform: uppercase; letter-spacing: .04em; color: #334155; }
.grid tr { page-break-inside: avoid; }
.toc-mod { margin: 3.5mm 0 1.2mm; font-weight: 700; color: var(--c); font-size: 10.5pt; page-break-after: avoid; }
.toc-line { display: flex; gap: 3mm; padding: .5mm 0; border-bottom: .2mm dotted #cbd5e1; font-size: 8.6pt; page-break-inside: avoid; }
.toc-line .id { width: 22mm; font-weight: 700; flex: none; }
.toc-line .k { margin-left: auto; color: #64748b; flex: none; }
.modhead { background: var(--c); color: #fff; padding: 3mm 4.5mm; border-radius: 2.5mm; margin: 7mm 0 3.5mm; page-break-after: avoid; page-break-inside: avoid; }
.modhead.first { page-break-before: always; margin-top: 0; }
.modhead h2 { font-size: 15pt; }
.modhead .n { opacity: .9; font-size: 9.5pt; }
.card { border: .35mm solid #cbd5e1; border-left: 2.4mm solid var(--c); border-radius: 2mm; margin: 0 0 4mm; padding: 0; }
.card-h { display: flex; flex-wrap: wrap; gap: 1.6mm 3mm; align-items: center; padding: 2.6mm 3.2mm 2mm; background: color-mix(in srgb, var(--c) 9%, white); border-bottom: .25mm solid #e2e8f0; page-break-after: avoid; page-break-inside: avoid; }
.card-h .id { font-weight: 800; font-size: 10.5pt; color: var(--c); }
.card-h .title { font-weight: 700; font-size: 10.5pt; flex: 1 1 100mm; }
.chip { display: inline-block; font-size: 7.4pt; font-weight: 700; color: #fff; border-radius: 10mm; padding: .5mm 2.2mm; letter-spacing: .02em; }
.facts { display: grid; grid-template-columns: 24mm 1fr; gap: .5mm 3mm; padding: 1.6mm 3.2mm; font-size: 8.3pt; page-break-inside: avoid; }
.facts dt { color: #64748b; font-weight: 700; }
.facts dd { margin: 0; }
.steps { width: 100%; border-collapse: collapse; }
.steps th { background: #f8fafc; color: #475569; font-size: 7.6pt; text-transform: uppercase; letter-spacing: .05em; text-align: left; padding: 1.4mm 2mm; border-top: .25mm solid #e2e8f0; border-bottom: .25mm solid #e2e8f0; }
.steps td { padding: 1.3mm 1.8mm; vertical-align: top; border-bottom: .2mm solid #eef2f7; }
.steps tr { page-break-inside: avoid; }
.steps td.n { width: 7mm; font-weight: 800; color: var(--c); text-align: center; }
.steps td.do { width: 42%; }
.steps td.ex { width: 38%; color: #14532d; }
.steps td.res { width: 41mm; white-space: nowrap; font-size: 7.4pt; color: #334155; }
.box { display: inline-block; width: 3.3mm; height: 3.3mm; border: .3mm solid #475569; border-radius: .6mm; vertical-align: -0.6mm; margin-right: 1mm; background: #fff; }
.res-line { display: inline-block; margin: 0 1.6mm 0 0; }
.notes { height: 4.6mm; border-bottom: .25mm dashed #94a3b8; }
.verdict { display: flex; flex-wrap: wrap; gap: 2mm 5mm; align-items: center; padding: 2.4mm 3.2mm; background: #f8fafc; border-top: .25mm solid #e2e8f0; font-size: 8.4pt; page-break-inside: avoid; border-radius: 0 0 2mm 0; }
.verdict b { color: #0f172a; }
.verdict .fill { display: inline-block; min-width: 32mm; border-bottom: .25mm solid #94a3b8; height: 4mm; }
.verdict .wide { flex: 1 1 60mm; }
.tally th, .tally td { border: .25mm solid #cbd5e1; padding: 1.1mm 1.6mm; font-size: 8pt; }
.tally th { background: #f1f5f9; }
.tally td.c { width: 11mm; text-align: center; }
.tally tr { page-break-inside: avoid; }
.legend { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm 8mm; }
.legend div { page-break-inside: avoid; }
.callout { border: .4mm solid var(--c); background: color-mix(in srgb, var(--c) 7%, white); border-radius: 2.5mm; padding: 3mm 4mm; margin: 0 0 4mm; page-break-inside: avoid; }
.small { font-size: 8.2pt; color: #475569; }
.live { color: #b91c1c; font-weight: 700; }
"""

def chip(label, colour): return f'<span class="chip" style="background:{colour}">{E(label)}</span>'

def boxes(labels):
    return ''.join(f'<span class="res-line"><span class="box"></span>{E(l)}</span>' for l in labels)

def card_html(c, colour):
    k = KIND.get(c.get('kind'), (c.get('kind', '').title(), '#64748b'))
    tags = ''.join(chip(*TAG.get(t, (t.title(), '#64748b'))) for t in c.get('tags', []))
    persona = PERSONA.get(c.get('persona'), c.get('persona') or '—')
    comp = c.get('company') or 'ANY'
    company = 'Any company' if comp == 'ANY' else COMPANY.get(comp, comp)
    facts = [('Who', persona), ('Company', company), ('Module', c.get('module', ''))]
    if c.get('why'): facts.append(('Why', c['why']))
    if c.get('before'): facts.append(('Before you start', c['before']))
    # Only a screen address helps a tester; program file / document references do not.
    src = c.get('route') or c.get('ref')
    if isinstance(src, (list, tuple)): src = ', '.join(map(str, src))
    if src and not re.search(r'(\.php|\.md|docs/|lib/|views/|tests/)', str(src)):
        facts.append(('Screen', src))
    if c.get('live'): facts.append(('Note', 'Changes a real setting — test copy only, or note the old value and put it back.'))
    fh = ''.join(f'<dt>{E(a)}</dt><dd>{E(b)}</dd>' for a, b in facts)
    rows = []
    for i, s in enumerate(c.get('steps', []), 1):
        rows.append(f'<tr><td class="n">{i}</td><td class="do">{E(s.get("do",""))}</td>'
                    f'<td class="ex">{E(s.get("expect",""))}</td>'
                    f'<td class="res">{boxes(["Pass","Fail","Blocked"])}<div class="notes"></div></td></tr>')
    return (f'<div class="card" style="--c:{colour}">'
            f'<div class="card-h"><span class="id">{E(c["id"])}</span><span class="title">{E(c["title"])}</span>{chip(k[0], k[1])}{tags}</div>'
            f'<dl class="facts">{fh}</dl>'
            f'<table class="steps"><thead><tr><th>#</th><th>Do this</th><th>You should see</th><th>Result · notes</th></tr></thead>'
            f'<tbody>{"".join(rows)}</tbody></table>'
            f'<div class="verdict"><b>This test:</b> <span><span class="box"></span>Pass</span><span><span class="box"></span>Fail</span>'
            f'<span><span class="box"></span>Blocked</span><span><span class="box"></span>Not applicable</span>'
            f'<span>Tested by <span class="fill"></span></span><span>Date <span class="fill" style="min-width:22mm"></span></span>'
            f'<span class="wide">Screenshot / note ref <span class="fill" style="min-width:40mm"></span></span></div></div>')

def area_body(area, items, with_cover=True):
    colour = AREA_COLOURS.get(area, '#334155')
    mods = collections.OrderedDict()
    for c in items: mods.setdefault(c.get('module') or 'General', []).append(c)
    nsteps = sum(len(c.get('steps', [])) for c in items)
    kinds = collections.Counter(c.get('kind') for c in items)
    out = []
    if with_cover:
        stats = ''.join(f'<div class="stat"><b>{v}</b>{E(KIND.get(k,(k,))[0])}</div>' for k, v in kinds.most_common())
        out.append(f'<section class="cover" style="background:linear-gradient(150deg,{colour},#0f172a)">'
                   f'<div><div class="kicker">EXAACT Test Book</div><h1>{E(area)}</h1>'
                   f'<div class="sub">{len(items)} tests · {nsteps} steps · {len(mods)} modules. Every test is written out in full — tick a box on every step and give each test a verdict.</div>'
                   f'<div class="stats">{stats}</div></div>'
                   f'<div class="meta">Test copy: {E(book.get("site",""))}<br>Built {BUILT} from the live test book (includes the v12 steps).<br>Record results here, then enter them in the online test book to export to Excel.</div></section>')
    # contents
    toc = [f'<section style="--c:{colour}"><h2 class="sec-title" style="--c:{colour}">{E(area)} — contents</h2>']
    for m, cs in mods.items():
        toc.append(f'<div class="toc-mod" style="--c:{colour}">{E(m)} <span class="small">({len(cs)} tests)</span></div>')
        for c in cs:
            toc.append(f'<div class="toc-line"><span class="id">{E(c["id"])}</span><span>{E(c["title"])}</span>'
                       f'<span class="k">{E(KIND.get(c.get("kind"),(c.get("kind",""),))[0])} · {len(c.get("steps",[]))} steps</span></div>')
    toc.append('</section>')
    out.append(''.join(toc))
    for mi, (m, cs) in enumerate(mods.items()):
        out.append(f'<div class="modhead{" first" if mi == 0 else ""}" style="--c:{colour}"><h2>{E(m)}</h2><div class="n">{E(area)} · {len(cs)} tests</div></div>')
        out.extend(card_html(c, colour) for c in cs)
    # tally sheet
    t = [f'<section class="page" style="--c:{colour}"><h2 class="sec-title" style="--c:{colour}">{E(area)} — results tally</h2>'
         f'<p class="sec-intro">Copy each test\'s verdict here so the whole area can be read on a few pages.</p>'
         f'<table class="tally"><thead><tr><th>Test</th><th>Title</th><th>Pass</th><th>Fail</th><th>Blocked</th><th>N/A</th><th>Tester · date</th></tr></thead><tbody>']
    for c in items:
        t.append(f'<tr><td><b>{E(c["id"])}</b></td><td>{E(c["title"])}</td><td class="c"><span class="box"></span></td><td class="c"><span class="box"></span></td>'
                 f'<td class="c"><span class="box"></span></td><td class="c"><span class="box"></span></td><td style="width:32mm"></td></tr>')
    t.append('</tbody></table></section>')
    out.append(''.join(t))
    return ''.join(out)

def start_here_body():
    su = book['setup']; col = AREA_COLOURS['Start here']
    total_steps = sum(len(c.get('steps', [])) for c in cases)
    o = [f'<section class="cover" style="background:linear-gradient(150deg,#1e3a8a,#0f172a)">'
         f'<div><div class="kicker">EXAACT</div><h1>Test Book</h1><div class="sub">The complete, printable book: {len(cases)} tests and {total_steps} steps across {len(AREAS)} areas, every section written out in full.</div>'
         f'<div class="stats"><div class="stat"><b>{len(cases)}</b>tests</div><div class="stat"><b>{total_steps}</b>steps</div><div class="stat"><b>{len(AREAS)}</b>areas</div><div class="stat"><b>{len(su.get("users",[]))}</b>test users</div></div></div>'
         f'<div class="meta">Test copy: {E(book.get("site",""))}<br>Built {BUILT} from the live test book (includes the v12 billing steps).<br>Never test on the live site.</div></section>']
    # how to use
    o.append(f'<section style="--c:{col}"><h2 class="sec-title" style="--c:{col}">How to use this book</h2>'
             '<div class="callout" style="--c:#2563eb"><b>On every step</b> do what the left column says, compare with “You should see”, and tick <b>Pass</b>, <b>Fail</b> or <b>Blocked</b>. Write what you actually saw on the dashed line when it differs.<br>'
             '<b>At the end of each test</b> tick one verdict box, write your name and the date, and note the screenshot reference (e.g. “Sales-14.png”).<br>'
             '<b>Afterwards</b> enter the results in the online test book (same test IDs) and export to Excel — that is the copy that comes back for fixing.</div>'
             '<h3 style="margin:4mm 0 2mm">What the colours and labels mean</h3><div class="legend">'
             + ''.join(f'<div>{chip(v[0], v[1])} {E({"scenario":"An everyday task done the normal way.","edge":"Unusual input or timing — the system must refuse or cope.","screen":"Every field, tab and button on one screen is where it should be.","access":"The right people can, the wrong people cannot.","flow":"A whole business process, start to end, across modules.","phone":"The same task on a phone at 390 px."}.get(k,""))}</div>' for k, v in KIND.items())
             + ''.join(f'<div>{chip(v[0], v[1])} {E({"best":"Everything entered correctly.","worst":"Things go wrong — check the system says why and keeps your data.","edge":"A boundary: zero, maximum, today, a duplicate."}.get(k,""))}</div>' for k, v in TAG.items())
             + '</div><h3 style="margin:5mm 0 2mm">Result codes</h3><table class="grid"><tr><th>Tick</th><th>When</th></tr>'
             '<tr><td><span class="box"></span>Pass</td><td>You saw exactly what “You should see” says.</td></tr>'
             '<tr><td><span class="box"></span>Fail</td><td>You saw something else, an error, or nothing. Write what you saw.</td></tr>'
             '<tr><td><span class="box"></span>Blocked</td><td>You could not do the step (an earlier step failed, a screen would not open). Say what stopped you.</td></tr>'
             '<tr><td><span class="box"></span>Not applicable</td><td>The test does not apply to your company (whole-test verdict only).</td></tr></table>'
             '<h3 style="margin:5mm 0 2mm">The volumes</h3><table class="grid"><tr><th>Volume</th><th>Area</th><th>Tests</th><th>Steps</th></tr>'
             + ''.join(f'<tr><td>{i:02d}</td><td><b style="color:{AREA_COLOURS.get(a,"#334155")}">{E(a)}</b></td><td>{len(by_area.get(a,[]))}</td><td>{sum(len(c.get("steps",[])) for c in by_area.get(a,[]))}</td></tr>' for i, a in enumerate(AREAS, 1))
             + '</table></section>')
    if su.get('questions'):
        o.append(f'<section class="page" style="--c:{col}"><h2 class="sec-title" style="--c:{col}">Answer these before you start</h2>'
                 + ''.join(f'<div class="callout" style="--c:#d97706"><b>{E(q["q"])}</b><br><span class="small">{E(q.get("why",""))}</span></div>' for q in su['questions']) + '</section>')
    # people
    o.append(f'<section class="page" style="--c:{col}"><h2 class="sec-title" style="--c:{col}">Who is who — the roles in the tests</h2><table class="grid"><tr><th>Role</th><th>What they do</th></tr>'
             + ''.join(f'<tr><td><b>{E(p["name"])}</b></td><td>{E(p.get("does",""))}</td></tr>' for p in book.get('personas', [])) + '</table>'
             f'<h2 class="sec-title" style="--c:{col};margin-top:6mm">Company types</h2><table class="grid"><tr><th>Code</th><th>Company</th><th>What it is</th><th>Industry</th></tr>'
             + ''.join(f'<tr><td><b>{E(c["code"])}</b></td><td>{E(c["name"])}</td><td>{E(c.get("what",""))}</td><td>{E(c.get("industry",""))}</td></tr>' for c in book.get('companies', [])) + '</table></section>')
    o.append(f'<section class="page" style="--c:{col}"><h2 class="sec-title" style="--c:{col}">The test users</h2>'
             '<p class="sec-intro">Create these once (Admin → Users). Passwords are not printed here — use the test password you chose, the same for all test users, and keep it private.</p>'
             '<table class="grid"><tr><th>#</th><th>Username</th><th>Name · role</th><th>Branch · team</th><th>Sees</th><th>Can</th><th>Cannot</th></tr>'
             + ''.join(f'<tr><td>{E(u.get("n",""))}</td><td><b>{E(u.get("user",""))}</b></td><td>{E(u.get("name",""))}<br><span class="small">{E(u.get("roleName") or u.get("role",""))}</span></td>'
                       f'<td>{E(u.get("home",""))}<br><span class="small">{E(u.get("teamName") or u.get("team",""))}</span></td><td>{E(u.get("sees",""))}</td><td>{E(u.get("can",""))}</td><td>{E(u.get("cannot",""))}</td></tr>' for u in su.get('users', []))
             + '</table>'
             f'<h2 class="sec-title" style="--c:{col};margin-top:6mm">Branches</h2><table class="grid"><tr><th>Branch</th><th>Why</th></tr>'
             + ''.join(f'<tr><td><b>{E(b["name"])}</b></td><td>{E(b.get("why",""))}</td></tr>' for b in su.get('branches', [])) + '</table></section>')
    if su.get('form'):
        o.append(f'<section class="page" style="--c:{col}"><h2 class="sec-title" style="--c:{col}">Filling in a test user (Admin → Users → + New)</h2>'
                 + ''.join(f'<div class="toc-mod" style="--c:{col}">{E(t.get("tab",""))}</div><table class="grid"><tr><th>Field</th><th>What to enter</th></tr>'
                           + ''.join(f'<tr><td><b>{E(f.get("f",""))}</b></td><td>{E(f.get("v",""))}</td></tr>' for f in t.get('fields', [])) + '</table>' for t in su['form']) + '</section>')
    if su.get('settings'):
        o.append(f'<section class="page" style="--c:{col}"><h2 class="sec-title" style="--c:{col}">Settings to check before testing</h2><table class="grid"><tr><th>Done</th><th>Step</th><th>Where</th><th>What</th></tr>'
                 + ''.join(f'<tr><td><span class="box"></span></td><td><b>{E(s.get("step",""))}</b>{"<br><span class=live>live setting</span>" if s.get("live") else ""}</td><td>{E(s.get("where",""))}</td><td>{E(s.get("what",""))}</td></tr>' for s in su['settings']) + '</table>'
                 + (f'<h2 class="sec-title" style="--c:{col};margin-top:6mm">Watch for these</h2><table class="grid"><tr><th>Who</th><th>What to watch</th></tr>'
                    + ''.join(f'<tr><td><b>{E(w.get("who",""))}</b></td><td>{E(w.get("what",""))}</td></tr>' for w in su.get('watch', [])) + '</table>' if su.get('watch') else '')
                 + '</section>')
    return ''.join(o)

def doc(title, body):
    return f'<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{E(title)}</title><style>{CSS}</style></head><body>{body}</body></html>'

manifest = []
sh = start_here_body()
open(os.path.join(OUT, '00-start-here.html'), 'w').write(doc('EXAACT Test Book — Start here', sh))
manifest.append(('00-start-here', 'Start here — how to use, users, settings'))
full = [sh]
for i, a in enumerate(AREAS, 1):
    items = by_area.get(a, [])
    if not items: continue
    body = area_body(a, items)
    name = f'{i:02d}-{slug(a)}'
    open(os.path.join(OUT, name + '.html'), 'w').write(doc(f'EXAACT Test Book — {a}', body))
    manifest.append((name, a))
    full.append(f'<div class="page"></div>' + body if False else body)
open(os.path.join(OUT, 'complete.html'), 'w').write(doc('EXAACT Test Book — complete', ''.join(full)))
json.dump(manifest, open(os.path.join(OUT, 'manifest.json'), 'w'))
print(len(manifest), 'volumes written to', OUT)
