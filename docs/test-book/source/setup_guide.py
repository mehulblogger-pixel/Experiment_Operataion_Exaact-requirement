"""Set up for testing: the test company, its branches, one test user per role,
and the settings every test in the book assumes.

Every screen name, field label and option below was read off the screens
measured on 20 Oct 2026 (screens_today.json): user-new, setup, company-profile,
quote-approval-rule-new, preorder-checklist, sales-setup. What each role can do
comes from docs/01-roles.md and docs/02-permission-matrix.md; the "opens"
figure is what the role could actually open on the throwaway copy
(access_today.json, out of 323 screens).

Names and e-mails here are test data only. No real person, password or address.
"""

# Two branches are enough to test every branch wall. Ahmedabad is "home";
# Mumbai is the "other branch" that people in Ahmedabad must not see into.
BRANCHES = [
    {'name': 'Ahmedabad', 'why': 'Home branch for most test users.'},
    {'name': 'Mumbai', 'why': 'The "other" branch: proves that a branch cannot see another branch\'s customers, quotes and jobs.'},
]

# Create them in this order: "Reports to" can only pick someone already on file.
# scope: what to do on the "Data scope" tab. 'leave' = tick nothing (the role's own default applies).
USERS = [
    dict(n=1, user='test.director', name='Test Director', role='BUSINESS_DIRECTOR', home='Ahmedabad', team='OFFICE', reports='— nobody / top of the chain —', dept='Management', scope='leave',
         sees='every branch',
         can='Reads every number in every branch: Sales, Operations, Money, Insights. Approves the biggest quotations (Level 3). Records a nonconformity.',
         cannot='Change records day to day, change system settings, see identity documents.'),
    dict(n=2, user='test.sbuhead', name='Test SBUHead', role='SBU_HEAD', home='Ahmedabad', team='OFFICE', reports='Test Director', dept='Management', scope='sbu',
         sees='every branch, ONE business unit (Industrial)',
         can='Reads and signs off its own business unit across all branches: revenue, utilisation, report sign-off.',
         cannot='Manage logins or settings; see other business units\' figures.'),
    dict(n=3, user='test.finance', name='Test Finance', role='FINANCE', home='Ahmedabad', team='OFFICE', reports='Test Director', dept='Finance', scope='leave',
         sees='every branch',
         can='Registers the contract from a won quotation, raises invoices, records receipts, reconciles credit and attendance. Reads quotations, jobs, vendors and clients.',
         cannot='Prepare or approve quotations, allocate or close jobs, manage users or settings.'),
    dict(n=4, user='test.qualityhead', name='Test QualityHead', role='QUALITY_HEAD', home='Ahmedabad', team='OFFICE', reports='Test Director', dept='Quality', scope='leave',
         sees='every branch',
         can='Owns complaints, nonconformities and corrective actions; closes them on evidence. Reads operations and reports for context.',
         cannot='Run operations, touch money, manage users or settings.'),
    dict(n=5, user='test.techhead', name='Test TechHead', role='TECHNICAL_HEAD', home='Ahmedabad', team='OFFICE', reports='Test Director', dept='Quality', scope='leave',
         sees='every branch',
         can='Same rights as the Quality Head (closes technical nonconformities).',
         cannot='Same limits as the Quality Head.'),
    dict(n=6, user='test.mktmgr', name='Test MktMgr', role='MARKETING_MANAGER', home='Ahmedabad', team='OFFICE', reports='Test Director', dept='Sales', scope='leave',
         sees='every branch',
         can='Leads Sales: approves quotations (Level 2), answers "Set up Sales", sets approval levels and the checklist, manages quote templates, reopens a lost lead or deal.',
         cannot='Run operations, register contracts, manage users or settings.'),
    dict(n=7, user='test.branchmgr', name='Test BranchMgr', role='BRANCH_MANAGER', home='Ahmedabad', team='OFFICE', reports='Test SBUHead', dept='Management', scope='leave',
         sees='Ahmedabad only',
         can='Runs the Ahmedabad branch: approves its quotations (Level 1), approves contract openings and amendments, its people, work and figures. Adds users in its own branch.',
         cannot='See or manage Mumbai; change system settings.'),
    dict(n=8, user='test.branchmgr2', name='Test BranchMgr2', role='BRANCH_MANAGER', home='Mumbai', team='OFFICE', reports='Test SBUHead', dept='Management', scope='leave',
         sees='Mumbai only',
         can='The same as Test BranchMgr, for Mumbai. Used to prove the wall between branches.',
         cannot='See anything of Ahmedabad.'),
    dict(n=9, user='test.bam', name='Test BAM', role='BRANCH_APP_MANAGER', home='Ahmedabad', team='OFFICE', reports='Test BranchMgr', dept='Administration', scope='leave',
         sees='Ahmedabad only',
         can='Keeps the branch set-up tidy: masters, report types and numbering, users of its own branch; may delete a wrongly raised work order; only role that may edit a locked report time.',
         cannot='Do inspections, approve or issue reports, see money.'),
    dict(n=10, user='test.opsmgr', name='Test OpsMgr', role='OPERATION_MANAGER', home='Ahmedabad', team='COORD', reports='Test BranchMgr', dept='Operations', scope='leave',
         sees='Ahmedabad only',
         can='Runs delivery: work orders, allocation, closing jobs, report approval; endorses contract openings and amendments; sees revenue and profit.',
         cannot='Sales screens, salary figures, users or settings.'),
    dict(n=11, user='test.asstmgr', name='Test AsstMgr', role='ASST_MANAGER', home='Ahmedabad', team='COORD', reports='Test OpsMgr', dept='Operations', scope='leave',
         sees='Ahmedabad only',
         can='Helps run the desk: raises work orders, allocates jobs, keeps the availability board current.',
         cannot='Close jobs, see any money, Sales, Recruitment, users or settings.'),
    dict(n=12, user='test.coord', name='Test Coord', role='COORDINATOR', home='Ahmedabad', team='COORD', reports='Test OpsMgr', dept='Operations', scope='leave',
         sees='Ahmedabad only',
         can='Turns a live contract into work: raises work orders, allocates inspectors, chases reports, closes jobs; sees credit figures only.',
         cannot='See revenue, salary or profit; approve own reports; manage users or settings.'),
    dict(n=13, user='test.coord2', name='Test Coord2', role='COORDINATOR', home='Mumbai', team='COORD', reports='Test BranchMgr2', dept='Operations', scope='leave',
         sees='Mumbai only',
         can='The same as Test Coord, for Mumbai. Used to prove the wall between branches in Operations.',
         cannot='See Ahmedabad work orders, jobs or customers.'),
    dict(n=14, user='test.bdm', name='Test BDM', role='BUSINESS_DEV_MANAGER', home='Ahmedabad', team='OFFICE', reports='Test MktMgr', dept='Sales', scope='leave',
         sees='Ahmedabad only',
         can='Finds new customers: leads, deals, enquiries, quotations; sends quotations and records the customer\'s answer on its own quotations.',
         cannot='Approve quotations, register contracts, touch operations or users.'),
    dict(n=15, user='test.kam', name='Test KAM', role='KEY_ACCOUNTS_MANAGER', home='Mumbai', team='OFFICE', reports='Test MktMgr', dept='Sales', scope='leave',
         sees='Mumbai only',
         can='Same rights as the BDM, for existing big customers. Sits in Mumbai so the Sales branch wall can be tested.',
         cannot='Same limits as the BDM; nothing of Ahmedabad.'),
    dict(n=16, user='test.mktexec', name='Test MktExec', role='MARKETING_EXECUTIVE', home='Ahmedabad', team='OFFICE', reports='Test MktMgr', dept='Sales', scope='leave',
         sees='Ahmedabad only',
         can='Records leads and enquiries, prepares quotations, does follow-ups.',
         cannot='Approve or send a quotation, see Insights, money or operations.'),
    dict(n=17, user='test.srinspector', name='Test SrInspector', role='SR_INSPECTOR', home='Ahmedabad', team='FIELD', reports='Test OpsMgr', dept='Operations', scope='leave',
         sees='Ahmedabad only', pay='Sr. Inspector · ₹60,000 a month · tick "field / production staff"',
         can='Does inspections and checks juniors\' reports before they go out. Phone first.',
         cannot='Everything outside reports.'),
    dict(n=18, user='test.inspector', name='Test Inspector', role='INSPECTOR', home='Ahmedabad', team='FIELD', reports='Test SrInspector', dept='Operations', scope='leave',
         sees='only their own jobs', pay='Inspector · ₹40,000 a month · tick "field / production staff"',
         can='Sees only the jobs given to them; fills reports, vouchers and attendance from a phone.',
         cannot='See other people\'s jobs, customers, money or any office screen.'),
    dict(n=19, user='test.inspector2', name='Test Inspector2', role='INSPECTOR', home='Mumbai', team='FIELD', reports='Test Coord2', dept='Operations', scope='leave',
         sees='only their own jobs', pay='Inspector · ₹40,000 a month · tick "field / production staff"',
         can='The Mumbai inspector: proves an Ahmedabad coordinator cannot allocate or see them without an inter-branch request.',
         cannot='As Test Inspector.'),
]

TEAM = {'FIELD': 'Field team member — goes to site', 'COORD': 'Coordinator / office-based', 'OFFICE': 'Back office'}

# How to fill the "Add a person" screen, tab by tab (measured labels).
FORM = [
    ('Who they are', [
        ('Username *', 'The "Username" column below, e.g. test.bdm'),
        ('Password', 'One test password of your own, at least 10 characters. Use the same one for all test users and keep it private. Never reuse your real password.'),
        ('"They must choose their own at the next sign-in"', 'Leave it UNTICKED for test users, so you can sign in as them straight away.'),
        ('First name / Last name', 'First name "Test", last name as in the list (e.g. "BDM"), so the user shows as "Test BDM".'),
        ('Email', 'An address you can read. Gmail adds "+anything": your-name+bdm@gmail.com reaches your own inbox. Approval and escalation e-mails then reach you.'),
        ('Role — what they can see & do', 'The "Role" column below. This one choice sets all their permissions.'),
        ('Same permissions as', 'Leave empty.'),
        ('Home office', 'Ahmedabad or Mumbai, as listed.'),
        ('Department — optional', 'As listed (Sales, Operations, Finance, Quality, Management, Administration).'),
        ('Team member *', 'Leave "➕ Add this person to the team (use the name above)".'),
        ('Which team', 'As listed: Field team member, Coordinator / office-based, or Back office.'),
        ('Is active', 'Ticked.'),
    ]),
    ('Data scope', [
        ('Offices this person can see', 'Tick NOTHING. "None ticked" means the role decides: Director, Business Unit Head, Marketing Manager, Finance, Quality and Technical Head see every office; everyone else sees only their home office.'),
        ('Business units this person can see', 'Leave "Every business unit" ticked — except Test SBUHead: untick it and tick only "Industrial".'),
    ]),
    ('Reporting & position', [
        ('Position / designation', 'Optional; for inspectors pick Inspector / Sr. Inspector.'),
        ('Reports to', 'As listed. This builds the organisation chart and the "reporting manager" approvals.'),
    ]),
    ('Cost & where it belongs', [
        ('Cost to the company, per month (₹)', 'Only for the three inspectors: ₹40,000 (Inspector), ₹60,000 (Senior Inspector). Test figures, so the profit and utilisation screens have something to show.'),
        ('"This person is field / production staff"', 'Tick for the three inspectors only.'),
    ]),
    ('Permissions', [
        ('All the tick-boxes', 'Change NOTHING. Pick the Role first (tab "Who they are"); the ticks then show exactly what that role gives, and a green line reads "✓ Exactly what the … role gives". If instead you see "⚠ Their own access", press the button "Use the … role\'s access". (Before the 20 Oct update is uploaded the ticks are wrongly the Coordinator\'s: after "Save user", open the person again → Permissions → tick "↺ Reset this login\'s access to its … role default" → Save user.)'),
        ('Check', 'In Admin → User register, no test user may carry the orange "own access" mark. It means their access is not their role\'s, and the tests would not mean anything for them.'),
    ]),
]

# Company settings. where = the left-menu path; live = safe on the live company.
SETTINGS = [
    dict(step='Company details', where='Admin → Company profile', live=True,
         what='Check that Legal name, Brand name, Address, GSTIN, E-mail, Phone and Logo are filled in with your REAL details. Quotations, invoices and reports print them. Do not change them for testing.'),
    dict(step='First-time setup (only on a new test workspace)', where='The "Welcome — let’s set up your system" screen appears by itself the first time', live=False,
         what='1 Admin password: your own. 2 Company / brand name: "TB Inspection Services (TEST)". Your industry: "Inspection, testing & certification". 3 Financial year starts in: April. Currency symbol: ₹. Date format: 25 Jul 2026. 4 Grievance officer: your own name and e-mail. Press "Finish setup →".'),
    dict(step='Modules switched on', where='Admin → Modules & features', live=True,
         what='Sales, Operations, Money, Recruitment, Quality & Accreditation, Reporting, Insights and Directory must be ON — otherwise those tests cannot be run. Marketplace only if you sell through it. If a module is not in your plan, mark its tests "Blocked" with the note "module not in plan".'),
    dict(step='Branches', where='Admin → Organisation → Offices tab', live=True,
         what='Ahmedabad and Mumbai must exist (they do on the live copy). If one is missing: add it with Name, Code (AMD / MUM), City and Type "Branch office".'),
    dict(step='Business units', where='Admin → Masters → Business Unit → Edit values', live=True,
         what='"Industrial" and at least one other unit (e.g. "Oil, Gas & Chemicals") must exist — the SBU Head tests need two.'),
    dict(step='Test users', where='Admin → User register → + Add a person', live=True,
         what='The 19 people in the table below, in that order. Sign in once as each in a private window to check they reach their home screen.'),
    dict(step='Approval levels for quotations', where='Sales home → "Set up Sales" → "Who approves, and up to what value"', live=False,
         what='Use the dashed card and "Add this level", three times. Level 1: From 0, Up to 500000, Approved by Branch Manager, For Every business unit. Level 2: From 500000, Up to 2500000, Marketing Manager. Level 3: From 2500000, Up to 0 (= no limit), Business Director. All "In use". The summary above the cards then describes all three in sentences.'),
    dict(step='Set up Sales answers', where='Sales home → "Set up Sales" tile (signed in as Test MktMgr)', live=False,
         what='Value not covered by any level → "It goes to the approvers of your highest level". Preparer may approve own quote → "Yes — when it is within their own authority". Waits too long → "After 1 day". Man-month quote needs an approved costing → "Yes — an approved costing first". Checklist → "Yes — show the checklist on every quote" and "Yes — every point must be ticked (or waived)". Press "Save these answers".'),
    dict(step='Pre-order review checklist', where='Sales → Pre-order checklist', live=False,
         what='Tick "Show the pre-order checklist on quotations" and "Require every point ticked before a quote can be approved". Check points, one per line (example test points): Scope and standards understood · Site access and safety needs known · A competent inspector is available · No conflict of interest · Payment terms agreed. Press "Save pre-order checklist".'),
    dict(step='Test customers', where='Directory → Client register', live=True,
         what='TB Refinery Ltd (Gujarat), TB Steel Works Pvt Ltd (Maharashtra), TB Pharma LLP (Telangana), TB Valves Pvt Ltd, TB Defaulter Co (Blocked, "Unpaid for 180 days"), TB Paused Co (On hold, "Credit review"), TB Group Parent with TB Group Child. Details in test SAL-001. The "TB" prefix lets you find and archive them later.'),
    dict(step='Industry per company type', where='Admin → Settings → Your industry', live=False,
         what='Only on a TEST workspace — it changes the wording everyone sees. A inspection agency → "Inspection, testing & certification"; B laboratory → "Testing & calibration laboratory"; C manpower → "Recruitment & staffing"; D manufacturer → "Manufacturing & engineering"; E trader → "Trading & distribution"; F EPC → "Construction, projects & infrastructure"; G consultancy → "Professional & consulting services"; H large group → as A, with both branches and several business units.'),
    dict(step='After testing', where='Admin → User register', live=True,
         what='Open each test user and untick "Is active" so nobody can sign in with them. Archive the "TB …" customers. Put the approval levels and Set up Sales answers back to your real ones if you changed them on the live company.'),
]

# Facts the test book could not settle; the tester answers them before starting.
QUESTIONS = [
    ('Does anybody already use the live company for real work?',
     'If yes, the steps marked "test copy only" change rules your staff work under (approval levels, Set up Sales, checklist, industry). Do them on a separate test workspace, or note the old values and put them back afterwards.'),
    ('Does your plan allow 19 more users?',
     'If the system refuses a new user because of a user limit, do not delete real users — tell me, and test with one user per role you need most (Sales first: MktExec, BDM, KAM, MktMgr, BranchMgr, BranchMgr2, Finance, Director).'),
]

# Things measured on the copy that disagree with the permission matrix. The tester
# should watch for them; they are not fixed until the tests confirm them.
WATCH = [
    ('Test Coord', 'may see Revenue figures and change KPI targets — the matrix says credit only.'),
    ('Test Finance', 'was refused Reporting screens — the matrix says Finance reads reports.'),
    ('Test OpsMgr', 'was refused Money (profitability) — the matrix says it sees revenue and profit.'),
    ('Test BranchMgr', 'may see every office on the cost and profit-and-loss screens — it should see Ahmedabad only.'),
]


def book_data(role_names, access):
    users = []
    for u in USERS:
        a = access.get(u['role'], {})
        users.append({**u, 'roleName': role_names[u['role']], 'teamName': TEAM[u['team']],
                      'opens': sum(v == 'OPEN' for v in a.values()) if a else None, 'of': len(a) if a else None})
    return {'branches': BRANCHES, 'users': users, 'form': [{'tab': t, 'fields': [{'f': f, 'v': v} for f, v in fs]} for t, fs in FORM],
            'settings': SETTINGS, 'questions': [{'q': q, 'why': w} for q, w in QUESTIONS],
            'watch': [{'who': w, 'what': x} for w, x in WATCH]}
