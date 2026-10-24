"""Corrections from the owner's test run of 24 Oct 2026 (Excel export).
Each is a step the book got wrong, or a step for a fix made that day. Results
are stored by test id and step number, so a step is only ever CHANGED in place
or ADDED at the end of its test — never inserted or removed."""

CHANGE = {  # (test id, step number) -> new fields
    ('SAL-000', 7):  {'expect': 'Sales, Insights (the Sales dashboard only) and Directory. Not Operations, Money or Quality & Accreditation.'},
    ('SAL-000', 8):  {'expect': 'Sales, Insights and Directory. No Operations, no Money, no Quality & Accreditation.'},
    ('SAL-000', 9):  {'expect': 'Sales, Money (profitability), Insights, Directory. Sales home shows the "Set up Sales" and "Document templates" tiles. No Admin, no Quality & Accreditation.'},
    ('SAL-000', 12): {'expect': 'Sales (quotations read only), Operations (read only), Money, Insights, Directory (clients and vendors, read only). No Reporting (inspection reports are not Finance\'s — decision D-07), no Quality & Accreditation, no Admin.'},
    ('SAL-001', 3):  {'do': 'Add "TB Defaulter Co". Then Left menu → Directory → Client holds (a separate screen, not the client\'s own form) → choose TB Defaulter Co, Status = Blocked, Reason "Unpaid for 180 days" → Save.'},
    ('SAL-001', 4):  {'do': 'Add "TB Paused Co" and on its own form choose Status "On hold". A "Reason" box appears — first try to save it empty, then fill "Credit review" and save.',
                      'expect': 'Empty: refused with "Please give the reason for marking it \'On hold\'…", and everything typed stays. With the reason: saved, and the client\'s page shows "On hold — Credit review".'},
    ('PLT-003', 1):  {'do': 'Platform setup → "Payment (Razorpay)" → paste the Key ID and Key Secret → "Save payment keys".',
                      'expect': '"Payment keys saved. Press \'Check payment keys\' to confirm them."'},
    ('PLT-004', 9):  {'do': 'Emergency: mPanel → File Manager → in the data folder (test copy: exaact_test/exaact_data_test; live site: exaact_data) create an empty file "superadmin-unlock"; open /platform-setup on the main address.'},
    ('PLT-005', 2):  {'do': 'BEFORE saving any price (step 4): as a company on its free trial, open Subscription.'},
}
ADD = {  # test id -> steps appended at the end
    'SAL-000': [
        {'do': 'As admin: open Test MktExec → Permissions → untick "Sales reports" (View) → Save user. Then sign in as Test MktExec.',
         'expect': 'Insights is gone from the menu and /crm-dashboard is refused. Open the person again as admin: the tick is still off (it no longer comes back by itself).'},
        {'do': 'As admin: open Test MktExec → Permissions → press "Use the Marketing Executive role\'s access" → Save user.',
         'expect': 'The green "✓ Exactly what the Marketing Executive role gives" line; Insights is back for Test MktExec.'},
    ],
    'SAL-001': [
        {'do': 'Add a client but leave "Payment terms" empty → Save.',
         'expect': 'Refused with "Payment terms are required for a client…" — and every box (name, contact, mobile, website…) still holds what you typed.'},
        {'do': 'After saving any client, read the trail under the page title and the Back button.',
         'expect': 'Home › Directory › Client register › <the client>; Back reads "← Client register". On its Edit form: … › <the client> › Edit, and Back goes to the client.'},
        {'do': 'Add another client whose primary contact has the SAME mobile as TB Refinery Ltd\'s contact (write it differently, e.g. with +91 and spaces).',
         'expect': 'Saved, with "Please check: this contact is also listed for … TB Refinery Ltd (…, same mobile)." It is a warning, not a refusal.'},
        {'do': 'As admin, open a client → Edit → change "Contracting / executing office" → Save. Then sign in as a coordinator (not admin) and open Edit on a client.',
         'expect': 'Admin: "Updated. Moved from … to …; the code … is kept." Coordinator: the office is shown but cannot be changed.'},
    ],
}

def apply(book):
    by_id = {c['id']: c for c in book['cases']}
    for (tid, n), fields in CHANGE.items():
        c = by_id.get(tid)
        if c and 1 <= n <= len(c['steps']): c['steps'][n - 1].update(fields)
    for tid, steps in ADD.items():
        c = by_id.get(tid)
        if not c: continue
        have = {s['do'] for s in c['steps']}
        c['steps'].extend(s for s in steps if s['do'] not in have)   # idempotent
    return book

if __name__ == '__main__':
    import json, sys
    p = sys.argv[1]; b = json.load(open(p))
    json.dump(apply(b), open(p, 'w'), ensure_ascii=False, separators=(',', ':'))
