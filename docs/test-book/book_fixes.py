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
    # Owner, 25 Oct: the welcome screen showed no password section (correct — the password was just set).
    ('ONB-020', 4):  {'expect': 'Next: "Welcome — let\u2019s set up your system" (sections 1 What does your company do? · 2 Money & calendar · 3 Data-protection contact). It does NOT ask for the password again — you set it a moment ago.'},
    # Owner, 25 Oct: the warning was easy to miss; it now names the numbers and stays on the panel.
    ('ONB-006', 3):  {'expect': 'Amber message "Saved (warn every 15 days, delete after 10 days) — but deletion comes before the first warning with these numbers…", and the same warning stays at the top of the panel. Put it back to 90 → "Self-serve sign-up settings saved (warn every 15 days, delete after 90 days)." and the panel warning disappears.'},
    # Cloud workspaces had no menu entry (owner: "guide exactly where to find this switch").
    ('ONB-001', 1):  {'do': 'Owner home → card "Cloud workspaces & requests" (also Admin → Super admin → "Cloud workspaces & requests"). Super Admin locked to its own address? Do this at https://opstest-admin.mghaiapps.com. Before the 24 Oct update: Admin → Control panel → panel "Tenant workspaces" → "Manage ›", or type /tenants after the address.'},
    # One choice instead of two switches (owner: "switch is off but still I can generate the account from /start").
    ('ONB-001', 2):  {'do': 'Panel "Workspace requests" → "How does a new company get a workspace?" → choose "Companies apply at /get-started, and you approve each one here" → Save.',
                      'expect': '"New companies: Companies apply at /get-started, and you approve each one here." The line "Now: …" shows that choice.'},
    ('ONB-001', 4):  {'do': 'Choose "Only you add companies" → Save. Signed out, open /get-started, then /start.',
                      'expect': 'Both closed: "We set up workspaces personally… Online self-registration isn\'t open just yet." — no form on either (before the 24 Oct update /start stayed open; that was the bug). Choose "Companies apply…" again for the next tests.'},
    ('ONB-001', 5):  {'do': 'Choose "Companies sign themselves up at /start" → Save. Signed out, open /get-started.',
                      'expect': 'It lands on "Start your free trial" — the company creates its own workspace without waiting.'},
    ('ONB-006', 2):  {'do': 'Choose "Companies sign themselves up at /start" on Cloud workspaces (or tick "Companies can sign themselves up" here) → Save.'},
    ('ONB-006', 5):  {'expect': 'Self sign-up is off. The note beside the tick says what happens instead: /start goes to the application form if applications are on, or is closed if you chose "Only you add companies" on Cloud workspaces.'},
    ('ONB-010', 1):  {'do': 'Owner home → "Cloud workspaces & requests" → panel "Workspace requests".'},
    ('ONB-012', 1):  {'do': 'Owner home → "Cloud workspaces & requests" → the request → "Decline".'},
    # The escalating chain (owner's decision, 24 Oct 2026): a quotation is signed by the
    # level its value falls in AND every level below it; a shared boundary is the lower level's.
    ('SAL-003', 4):  {'expect': 'Card "Level 2"; summary line "Level 2 — above ₹5,00,000 to ₹25,00,000 before tax: Marketing Manager."'},
    ('SAL-003', 5):  {'expect': 'Card "Level 3"; summary says "above ₹25,00,000". The line "Nothing above … is covered" disappears, and a line reads "A quote goes up the levels: it is signed by the level its value falls in and by every level below it, in order."'},
    ('SAL-071', 2):  {'expect': 'Level 1 only — ₹5,00,000 is the top of Level 1 ("up to"); Level 2 starts above it.'},
    ('SAL-071', 3):  {'expect': 'Level 1, then Level 2 — it has gone up past Level 1.'},
    ('SAL-071', 4):  {'do': 'A ₹40,00,000 quotation (inside Level 3, which has no upper limit).',
                      'expect': 'Level 1 → Level 2 → Level 3 (Branch Manager, Marketing Manager, Business Director), in that order.'},
}
BEFORE = {  # test id -> the "Before" line
    'ONB-002': 'Self sign-up OFF and applications ON. After the 24 Oct update: Owner home → Cloud workspaces & requests → choose "Companies apply at /get-started, and you approve each one here" → Save. Before it: Companies → 🚀 Self sign-up & free trial → untick "Companies can sign themselves up" → Save, then Cloud workspaces → "Online registration: OFF — turn on". Signed out, private window.',
    'ONB-003': 'Same as ONB-002: the APPLICATION form (/get-started), which has the "Preferred workspace name" box. While self sign-up is on, /get-started jumps to the free-trial form (/start), which has no workspace-name box — its name is made from the company name. Signed out.',
}
ADD = {  # test id -> steps appended at the end
    'SAL-003': [
        {'do': 'Edge: in the dashed card type Level 4, From 30000, Up to 100000 (inside Level 1). Add this level.',
         'expect': 'Refused: "Level 1 already covers ₹0 to ₹5,00,000. Levels may not overlap — a quotation moves up through them in order…". No card is added.'},
        {'do': 'Edge: type Level 1, From 3000000, Up to 0 (a higher range with a lower level number). Add this level.',
         'expect': 'Refused: "A higher value needs a higher level number…". No card is added.'},
        {'do': 'As Test BDM make three quotations — ₹2,00,000, ₹10,00,000 and ₹30,00,000 before tax — and submit each for approval. Open each one\'s approval steps.',
         'expect': '₹2,00,000: Level 1 only. ₹10,00,000: Level 1, then Level 2. ₹30,00,000: Level 1 → Level 2 → Level 3 (one after another, or all at once if Set up Sales says so).'},
    ],
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
    for tid, text in BEFORE.items():
        if tid in by_id: by_id[tid]['before'] = text
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
