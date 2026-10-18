#!/usr/bin/env python3
"""Phase D — is every status-carrying object's lifecycle written down?

docs/03-object-lifecycles.md is, by CLAUDE.md, the authority on statuses and
transitions: "Never add a status or transition that is not in
docs/03-object-lifecycles.md without asking." An object whose statuses exist in
code but not in that document is outside that control — nobody can tell whether
a transition is intended.

This matches each status vocabulary found in the code (evidence/
lifecycle-and-ownership.txt, section A) against the objects the document
covers, by keyword. Keyword matching is approximate in both directions, so the
output is a RECONCILIATION LIST to read, not a verdict: a "not matched" row
means this script could not find it, which is the cue to look.
"""
import os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
DOC = os.path.join(ROOT, '03-object-lifecycles.md')
EV = os.path.join(HERE, 'lifecycle-and-ownership.txt')

# The document's own section headings = the objects it covers.
doc_objs = []
with open(DOC, encoding='utf-8') as f:
    for line in f:
        m = re.match(r'^## (.+?)\s*$', line)
        if m:
            doc_objs.append(m.group(1).strip())

# The code's status vocabularies, from section A of the inventory.
code_sets = []
with open(EV, encoding='utf-8') as f:
    inA = False
    for line in f:
        if line.startswith('===') and 'A. STATUS SETS' in line:
            inA = True
            continue
        if inA and line.startswith('status vocabularies found'):
            break
        if inA:
            m = re.match(r'^([A-Z][A-Z0-9_]+)\s+(\d+)\s+(.*)$', line.rstrip())
            if m:
                code_sets.append((m.group(1), int(m.group(2)), m.group(3)))

# Hand-written bridge between a constant's name and the document's wording.
# Written out rather than guessed by stemming, so each pairing is reviewable.
BRIDGE = {
    'CALL_STATUSES': 'Call', 'JOB_STATUS': 'Job', 'JOB_STATUSES': 'Job',
    'VOUCHER_STATUS': 'Voucher', 'VOUCHER_STATUSES': 'Voucher',
    'QUOTE_STATUS': 'Quotation', 'QUOTE_STATUSES': 'Quotation',
    'IDEMS_STATUS': 'Inspection report', 'IDEMS_APPR_STATUS': 'Approval request',
    'IDEMS_VET_STATUS': 'Inspection report', 'BOSS_STATUS': 'Profitability',
    'CREDENTIAL_STATUS': 'Credential', 'BILLABLE_STATUS': 'Billable event',
    'CX_REQ_STATUSES': 'Connect Requirement', 'CX_APP_STATUSES': 'Connect Application',
    'CX_DISPUTE_STATUSES': 'Connect Dispute',
    'CAND_STAGES': 'Candidate', 'CANDIDATE_STAGES': 'Candidate',
    'IV_STATUS': 'Interview', 'INTERVIEW_STATUS': 'Interview',
    'DOC_STATUSES': 'Candidate document', 'HREQ_STATUS': 'Hiring request',
    'OFFER_STATUS': 'Job offer', 'GATE_STATUS': 'Approval request',
    'AVAIL_STATUS': 'Workforce operational status',
    'ATT_STATUS': 'Workforce operational status',
}

matched, unmatched = [], []
for name, n, sample in code_sets:
    target = BRIDGE.get(name)
    if target and any(target.lower() in d.lower() for d in doc_objs):
        matched.append((name, n, target))
        continue
    # Last chance: the constant's stem appears in a heading.
    stem = name.split('_')[0].lower()
    if len(stem) > 3 and any(stem in d.lower() for d in doc_objs):
        matched.append((name, n, '(by stem)'))
        continue
    unmatched.append((name, n, sample))

print(f'objects with a written lifecycle (document headings) : {len(doc_objs)}')
print(f'status vocabularies defined in code                  : {len(code_sets)}')
print(f'  matched to a documented object                     : {len(matched)}')
print(f'  NOT matched — read these                           : {len(unmatched)}')

print('\n--- matched ---')
for n, c, t in sorted(matched):
    print(f'  {n:<26} {c:>2} statuses -> {t}')

print('\n--- not matched: a status vocabulary with no lifecycle section found ---')
for n, c, s in sorted(unmatched, key=lambda x: -x[1]):
    print(f'  {n:<26} {c:>2} statuses   {s[:62]}')

print('\n--- document headings with no status vocabulary matched to them ---')
hit = {t for _, _, t in matched}
for d in doc_objs:
    if not any(t.lower() in d.lower() for t in hit if t != '(by stem)'):
        print(f'  {d}')
