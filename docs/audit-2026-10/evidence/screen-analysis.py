#!/usr/bin/env python3
"""Turn screens.json into the findings for D4 (screens/fields) and D5 (glossary).

Each section answers one question an auditor has to answer with evidence rather
than impression. Prints compactly; the full detail stays in screens.json.
"""
import json, os, re
from collections import Counter, defaultdict

HERE = os.path.dirname(os.path.abspath(__file__))
S = json.load(open(os.path.join(HERE, 'screens.json')))


def rule(t):
    print('\n' + '=' * 78 + f'\n{t}\n' + '=' * 78)


rule('1. STRUCTURE — screens missing the furniture every screen should have')
no_h1 = [r for r, d in S.items() if not d['h1']]
no_cr = [r for r, d in S.items() if not d['crumbs']]
print(f'no heading ({len(no_h1)}): {", ".join(sorted(no_h1))}')
print(f'no breadcrumb ({len(no_cr)}): {", ".join(sorted(no_cr))}')

rule('2. VALIDATION — screens that ask for data but demand none of it')
#  A form with several fields and not one marked required cannot tell the
#  person what it needs. The browser accepts an empty submit and the server
#  decides, which is where "nothing happened" comes from.
bad = sorted(((len(d['controls']), r, d['h1']) for r, d in S.items()
              if d['required'] == 0 and len(d['controls']) > 3), reverse=True)
print(f'{len(bad)} screens have >3 fields and ZERO marked required. Worst 20:')
for n, r, h in bad[:20]:
    print(f'   {n:>3} fields  /{r:<28} {h[:40]}')

rule('3. FORM LENGTH — one screen, how many questions')
big = sorted(((len(d['controls']), d['required'], r, d['h1']) for r, d in S.items()
              if len(d['controls']) > 25), reverse=True)
print(f'{len(big)} screens carry more than 25 visible fields:')
for n, req, r, h in big:
    print(f'   {n:>3} fields ({req:>2} required)  /{r:<26} {h[:38]}')

rule('4. TABLES — columns that cannot fit a phone')
wide = []
for r, d in S.items():
    for t in d['tables']:
        if len(t['cols']) > 12:
            wide.append((len(t['cols']), r, d['h1'], ' | '.join(t['cols'][:6])))
for n, r, h, sample in sorted(wide, reverse=True):
    print(f'   {n:>3} cols  /{r:<24} {h[:28]:<30} {sample[:46]}')

rule('5. BUTTON WORDS — the same act, said differently')
#  Inconsistent verbs are the cheapest usability defect to find and the most
#  visible to a non-technical user. Clusters, not raw counts.
FAMILIES = {
    'save':    r'^(save|update|apply|commit|store|keep)\b',
    'create':  r'^(add|new|create|start|raise|register|record|post|draft)\b',
    'delete':  r'^(delete|remove|erase|discard|drop|clear)\b',
    'cancel':  r'^(cancel|close|back|dismiss|abandon|never ?mind)\b',
    'submit':  r'^(submit|send|issue|release|publish|finalise|finalize|approve|confirm)\b',
    'export':  r'^(export|download|csv|print|pdf)\b',
}
allb = Counter(b for d in S.values() for b in d['buttons'])
print(f'{len(allb)} distinct button labels across {sum(allb.values())} buttons.')
for fam, pat in FAMILIES.items():
    hits = {b: n for b, n in allb.items() if re.match(pat, b.strip(), re.I)}
    if not hits:
        continue
    top = sorted(hits.items(), key=lambda x: -x[1])
    print(f'\n  [{fam}] {len(hits)} different wordings:')
    print('   ' + ' · '.join(f'"{b}"({n})' for b, n in top[:12]))

rule('6. FIELD LABELS — the same thing, named differently (glossary input)')
lab = Counter()
for d in S.values():
    for l in d['labels']:
        l = l.strip(' *:')
        if 0 < len(l) <= 40:
            lab[l] += 1
TERMS = ['office', 'branch', 'client', 'customer', 'party', 'partner', 'vendor',
         'supplier', 'job', 'call', 'work order', 'inspector', 'engineer',
         'report', 'document', 'invoice', 'bill', 'contract', 'purchase order',
         'candidate', 'applicant', 'requisition', 'hiring request', 'nonconformity',
         'ncr', 'corrective action', 'capa', 'business unit', 'sbu']
print('How often each business word appears as a field label:')
for t in TERMS:
    hits = {l: n for l, n in lab.items() if re.search(r'\b' + re.escape(t) + r'\b', l, re.I)}
    if hits:
        tot = sum(hits.values())
        ex = ' · '.join(sorted(hits, key=lambda x: -hits[x])[:4])
        print(f'   {t:<18} {tot:>4} uses, {len(hits):>3} distinct labels   e.g. {ex[:54]}')

rule('7. FIELD NAMES — the same data, stored under different names')
nm = defaultdict(set)
for r, d in S.items():
    for c in d['controls']:
        nm[c['name']].add(r)
for probe in ['office_id', 'party_id', 'partner_id', 'client_id', 'vendor_id',
              'job_id', 'call_id', 'inspector_id', 'user_id', 'date', 'remarks', 'notes']:
    hits = sorted(n for n in nm if n == probe or n.endswith('_' + probe) or n.startswith(probe))
    if len(hits) > 1:
        print(f'   {probe:<14} -> {", ".join(hits[:10])}')

rule('8. THE LARGEST SCREENS BY BYTES — where a phone pays for it')
for sz, r, h in sorted(((d['size'], r, d['h1']) for r, d in S.items()), reverse=True)[:12]:
    print(f'   {sz/1024:>7.0f} KB  /{r:<26} {h[:38]}')
