"""New company, from zero — tests written against the screens measured on the
throwaway copy on 22 Oct 2026 (onb_screens.json) and the journeys mapped from
the code (sign-up, approval, first sign-in, setup cockpit, modules, marketplace).

Known gaps found while mapping are written as what SHOULD happen, tagged 'gap',
with what the code does today in "why" — mark them Fail with a screenshot.
Writes cases_onboarding.json.
"""
import json, os

A = 'New company'
CASES = []


def C(id, module, title, kind, tags, persona, why, before, steps, ref='', live=True):
    CASES.append({'id': id, 'area': A, 'module': module, 'title': title, 'kind': kind, 'tags': tags, 'persona': persona,
                  'company': 'ANY', 'why': why, 'before': before, 'steps': [{'do': d, 'expect': e} for d, e in steps],
                  'ref': ref, 'live': live})


# ════════════════════════════════════════════════════════════════════════════
# 0 — The map
# ════════════════════════════════════════════════════════════════════════════
C('ONB-000', '0 · The three front doors', 'Where a newcomer can start — the three front doors', 'scenario', ['best'],
  'NEWCOMER', 'A newcomer is one of three people: a company that wants its own workspace, a professional who wants work, or a company/agency that wants people. Each has its own door.',
  'Signed OUT. A private browser window.',
  [('Open the site address (operations.mghaiapps.com).', 'You land on the marketplace front page (/connect) when the marketplace is on, otherwise on the sign-in page.'),
   ('On the marketplace front page, read the three cards.', '"I need to hire" → "Create a company account →"; "I’m a professional" → "Create a professional profile →"; "I’m an agency" → "Create an agency account →". Below: "Sign in as a professional", "Sign in as a company or agency", and a section "Get your own operations workspace" → "Start your workspace →" (only while online registration is ON). "Staff sign-in" at the top.'),
   ('Open the sign-in page (/login).', 'Heading "Sign in to continue"; Username, Password, "Forgot password?", "Sign in →". Links: "Go to the marketplace →" and, while online registration is ON, "Start here →". The line under it says to ask your office administrator for an account — staff never sign themselves up.')],
  'views/ops/connect_front.php; views/login_page.php')

# ════════════════════════════════════════════════════════════════════════════
# 1 — A company applies for its own workspace
# ════════════════════════════════════════════════════════════════════════════
C('ONB-001', '1 · A company applies', 'Online registration is a switch the platform owner turns on', 'scenario', ['rule'],
  'PLATFORM_OWNER', 'Companies can apply only while the platform owner allows it; it is OFF on a new installation.',
  'Signed in as the Master Admin of the CONTROL site (the platform owner).',
  [('Left menu → Admin → Super Admin → "Cloud workspaces" (or /tenants).', 'Heading "Cloud workspaces". If cloud mode is not on yet: "Switch cloud mode on" with a "Base domain" box — set it first (e.g. operations.mghaiapps.com).'),
   ('Panel "Workspace requests" → press "Online registration: OFF — turn on".', 'Message "Online workspace registration is ON — companies can now apply at /get-started." The button now reads "Online registration: ON — turn off".'),
   ('Signed out, open /login.', 'The line "Run an inspection company and want your own workspace? Start here →" now shows.'),
   ('Turn it OFF again and open /get-started signed out.', '"We set up workspaces personally… Online self-registration isn\'t open just yet." — no form. Turn it back ON for the next tests.')],
  'lib/tenants.php; views/ops/tenants.php', live=False)

C('ONB-002', '1 · A company applies', 'A new company fills "Start your workspace" — best case', 'scenario', ['best'],
  'NEWCOMER', 'This is the very first thing a new customer does.',
  'ONB-001: online registration ON. Signed out, private window.',
  [('Open /get-started (or "Start here →" on the sign-in page).', 'Heading "Run your inspection company on Inspection Ops", ribbon "◆ Free during launch". Fields: Company name, Your name, "Work e-mail — we\'ll send your workspace details here", "Phone — optional", "Preferred workspace name — optional, letters & numbers" (with the domain shown after it), "Anything we should know? — optional". Button "Request my workspace →".'),
   ('Company name "TB Test Inspections", Your name "Test Owner", Work e-mail (one you can read), Phone, Preferred workspace name "tbtest", note "Testing onboarding" → "Request my workspace →".', 'A ✅ and "Request received" with three numbered next steps, and that you will hear at the e-mail you gave, usually within one working day.'),
   ('Check that e-mail inbox.', 'An acknowledgement e-mail arrives.')],
  'views/public/get_started.php; lib/tenant_signup.php', )
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: the code sends NO e-mail on request or on approval, although the screen promises one — expect step 3 to fail.'

C('ONB-003', '1 · A company applies', 'Applying with bad or repeated details', 'edge', ['edge', 'worst'],
  'NEWCOMER', 'A public form gets every kind of mistake.',
  'Online registration ON. Signed out.',
  [('Leave Company name empty → Request.', 'The browser asks for the company name (or "Please enter your company name.").'),
   ('Work e-mail "abc" → Request.', '"Enter a valid work e-mail address."'),
   ('Preferred workspace name "TB Test!!" (symbols).', 'Refused by the box (letters, numbers and hyphens only) — or, if it gets through, the name is made from the company name instead.'),
   ('Submit a second request with the SAME e-mail as ONB-002.', '"We already have a request in progress for that e-mail — we will be in touch shortly." Nothing new is created.'),
   ('Company name of 300 characters; note of 5,000 characters.', 'Saved or refused with a sentence — never an error page.'),
   ('Press "Request my workspace →" 20 times quickly with different e-mails.', 'Each is accepted or politely refused; the site keeps working.')],
  'lib/tenant_signup.php')
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: the public form has no rate limit and no form token — repeated automatic submissions are not slowed down.'

# ════════════════════════════════════════════════════════════════════════════
# 2 — The platform owner approves / creates the company
# ════════════════════════════════════════════════════════════════════════════
C('ONB-010', '2 · The platform owner creates it', 'Approving a workspace request', 'scenario', ['best', 'rule'],
  'PLATFORM_OWNER', 'A request becomes a company only when the platform owner approves it.',
  'ONB-002 request exists. Signed in as the control site\'s Master Admin.',
  [('Super Admin → "Cloud workspaces" → panel "Workspace requests".', 'A "1 new" pill. The row shows TB Test Inspections, the e-mail, phone, note and an editable workspace name "tbtest", with "Approve" and "Decline".'),
   ('Change the workspace name to one already in use → Approve.', '"A workspace named "…" already exists — type a different name…" Nothing is created.'),
   ('Set it back to "tbtest" → Approve → confirm "Approve "TB Test Inspections" and create this workspace?".', 'Either "Workspace "tbtest" created. Their address: https://tbtest.…/ — they finish their own first-time setup there." (hosting panel connected), or a message telling you to create the database and sub-domain in the hosting panel, then "Mark set up".'),
   ('Open the new address.', 'The new company\'s own sign-in page.'),
   ('How does the owner of TB Test Inspections learn their sign-in?', 'They receive an e-mail with their address, their username (their e-mail) and a way to set their password.')],
  'lib/tenant_signup.php; views/ops/tenants.php')
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: approving here creates NO owner login and sends no e-mail — the new workspace\'s only login is "admin" with the server\'s default password, which you would have to hand over yourself. The "Companies" screen (ONB-011) does create the owner properly. Expect step 5 to fail.'

C('ONB-011', '2 · The platform owner creates it', 'Creating a company directly — "Companies" (with its plan)', 'scenario', ['best'],
  'PLATFORM_OWNER', 'The complete way to create a company today: it creates the owner\'s login, the plan and the seat limit together.',
  'Signed in as the control site\'s Master Admin.',
  [('Super Admin → "Companies" (/companies).', 'Heading "Companies". Form: "Company name *", "Workspace key (optional — made from the name)", "Owner name", "Owner email * (their login)", "Temporary password (blank = auto)", "Plan" (Starter / Recruitment / Professional / Enterprise, each listing its modules), "Where this company\'s data is stored" (Set it up for me / Use a MySQL database). Button "Create company".'),
   ('Company "TB Owner Co", Owner name "Test Owner", Owner email (one you can read), leave the password blank, Plan "Professional", data "Set it up for me" → "Create company".', '"Company "TB Owner Co" is ready. Its owner signs in at the one product URL with <email> (temporary password: …)…" The company is listed with Plan, Seats, Modules and Status.'),
   ('Edge: create another company with the SAME owner e-mail.', '"That owner email already belongs to a company."'),
   ('Edge: Workspace key "TB Owner!" (capitals, symbols).', '"The workspace key must be lowercase letters, digits or hyphens."'),
   ('Edge: leave Owner email empty.', '"A company name and a valid owner email are required."'),
   ('Write down the temporary password shown and pass it to the owner.', 'The owner also receives it (or a set-your-password link) by e-mail.')],
  'lib/saas_tenants.php; views/ops/saas_companies.php')
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: no e-mail is sent — the platform owner must pass the temporary password on by hand. Expect step 6 to fail.'

C('ONB-012', '2 · The platform owner creates it', 'Declining a request, and cleaning up test companies', 'edge', ['edge'],
  'PLATFORM_OWNER', 'A request that is not genuine is declined; test companies are removed after testing.',
  'A second test request from /get-started.',
  [('Cloud workspaces → the request → "Decline".', 'It leaves the new list and shows as declined.'),
   ('Companies → "TB Owner Co" → Suspend.', 'Its owner can no longer sign in; the message on their sign-in says the company is suspended.'),
   ('Companies → "TB Owner Co" → Remove (after testing).', 'It is removed from the list. Do this for every "TB…" company you created.')],
  'lib/saas_tenants.php')

# ════════════════════════════════════════════════════════════════════════════
# 3 — First sign-in
# ════════════════════════════════════════════════════════════════════════════
C('ONB-020', '3 · First sign-in', 'The owner signs in for the first time', 'scenario', ['best'],
  'MASTER_ADMIN', 'The first five minutes decide whether a new customer stays.',
  'ONB-011 done (TB Owner Co, plan Professional). Signed out, private window.',
  [('Sign in at the product address with the owner e-mail and the temporary password.', 'Either straight in, or "Your workspace is still being set up. Please try signing in again in a moment."'),
   ('After signing in.', 'Forced to "Change password": "Please choose your own password before going any further." Nothing else opens until it is changed.'),
   ('Try a weak password ("12345678", or the company name).', 'Refused with a sentence saying why.'),
   ('Set a strong password.', 'Next: "Welcome — let’s set up your system" (sections 1 Your admin password · 2 What does your company do? · 3 Money & calendar · 4 Data-protection contact).'),
   ('Company / brand name "TB Owner Co", Your industry "Inspection, testing & certification", Financial year starts in April, Currency ₹, Date format "25 Jul 2026", Grievance officer name and e-mail → "Finish setup →".', '"Setup complete — welcome. You can change any of this later…" and you are taken to "Set up your workspace" (the setup cockpit).'),
   ('Look for a software licence agreement.', 'None is shown inside a company workspace — that is for self-hosted installs only (ONB-060).')],
  'index.php; lib/setup.php; lib/saas_tenants.php')

C('ONB-021', '3 · First sign-in', 'First sign-in — the worst cases', 'edge', ['edge', 'worst'],
  'MASTER_ADMIN', 'People forget passwords, close tabs half-way and come back later.',
  'A fresh company from ONB-011.',
  [('Wrong password five times.', '"Too many failed attempts. Try again in N minute(s)."'),
   ('"Forgot password?" → your owner e-mail → "Send reset link →".', 'An e-mail with a link valid 60 minutes. Open it, set a new password (the two must match, 8+ characters).'),
   ('Use the same reset link a second time.', '"This reset link is invalid or has expired…"'),
   ('Close the browser in the middle of "Welcome — let’s set up your system", sign in again.', 'You are brought back to the same setup screen — it cannot be skipped by going elsewhere.'),
   ('On the setup screen, type /quotes in the address bar.', 'Taken back to setup until it is finished.')],
  'lib/pwreset.php; index.php')

# ════════════════════════════════════════════════════════════════════════════
# 4 — The setup cockpit
# ════════════════════════════════════════════════════════════════════════════
C('ONB-030', '4 · Setting up the company', 'The setup cockpit — every card, in order', 'scenario', ['best'],
  'MASTER_ADMIN', 'One page tells a new company what is left to do; every card must lead somewhere real.',
  'ONB-020 done. Signed in as the owner.',
  [('Left menu → Admin → "Company setup" (/workspace/setup).', 'Heading "Set up your workspace" with a readiness ring (%). Cards: Company profile · Features · Forms · Dropdown lists · People & access · Offices · Wording · Branding & theme, each with its state (Not started / Configured / Complete) and a button.'),
   ('Card "Company profile" → open it.', '"Your company": Legal name, Brand / trading name, Address, "GSTIN — fills PAN & State automatically", PAN, State, E-mail, Phone, Website, Footer line for PDFs, Report header colour, Logo, and "What does your company do?" ticks. Fill all with test data (GSTIN 24AAAAA0000A1Z5) → "Save company profile". Back on the cockpit the card reads Complete.'),
   ('Card "Offices" → "+ Add a branch".', 'Add "Ahmedabad" and "Mumbai" (Name, Code, City, Type "Branch office"). The card counts them.'),
   ('Card "Dropdown lists" → "+ Add a dropdown value".', 'The Masters page, grouped by area (Recruitment & people, Directory, Sales & CRM, Operations, Inspection reporting, Money & billing). Add one value.'),
   ('Card "Wording" → "Change wording".', '"Terminology" with "Start from your trade" and a "Use this wording" per area.'),
   ('Card "Branding & theme".', 'Settings opens on branding (logo, colour).'),
   ('Section "Getting started" (checklist) → press each "Do this".', 'Each opens the right screen (Invite your team → people; Add your people → team; first job / first requirement → that screen).'),
   ('"Setup health" lines (e.g. "You are the only user…") → "Fix".', 'Each opens the screen that fixes it.')],
  'lib/setup_cockpit.php; views/ops/cockpit_home.php')
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: every "Do this" in the checklist links to the home page (the code reads the wrong field), and "Add your people" points to /inspectors, which does not exist. Expect step 7 to fail.'

C('ONB-031', '4 · Setting up the company', 'The dashboard "Finish setting up" list agrees with what was done', 'edge', ['edge'],
  'MASTER_ADMIN', 'Two checklists must not disagree about the same work.',
  'ONB-020: setup wizard finished with a company name.',
  [('Open the Dashboard.', 'A panel "Finish setting up …" with its steps; "Say who you are" (the company name) is ticked, because you gave the name in the setup wizard.'),
   ('Compare with the cockpit (ONB-030).', 'The same things are shown as done in both places.')],
  'lib/setup.php')
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: the setup wizard stores the name in a different setting from the one the dashboard checks — "Say who you are" stays unticked until Company profile is saved.'

C('ONB-032', '4 · Setting up the company', 'Adding the company\'s people — and the seat limit', 'scenario', ['best', 'edge'],
  'MASTER_ADMIN', 'A plan includes a number of logins; the next one must be refused politely, not crash.',
  'TB Owner Co on plan Professional (5 logins included).',
  [('Admin → User register → "+ Add a person": add people as in Start here → SET-06 until 5 logins exist (owner included).', 'Each is saved; each sees their role\'s menu (BDM/KAM/Marketing see Sales, not "My jobs").'),
   ('Add a sixth person.', '"This company is on 5 seats and all 5 are in use. Buy more seats — or ask your provider to add them — before creating another login." — nothing saved, nothing typed is lost.'),
   ('As the platform owner: Companies → TB Owner Co → "Purchased seats (over the plan base)" 2 → "Save seats". As the owner, add the sixth person again.', 'Saved.'),
   ('Deactivate one person, then add another.', 'A deactivated login frees its seat.')],
  'lib/saas_tenants.php')

# ════════════════════════════════════════════════════════════════════════════
# 5 — Buying modules: one, several, all
# ════════════════════════════════════════════════════════════════════════════
MODS = [('Operations', 'Operations and Quality & Accreditation'), ('Sales & CRM', 'Sales'), ('Inspection reporting', 'Reporting'),
        ('Money', 'Money'), ('People & hiring', 'Recruitment'), ('Marketplace & Connect', 'Marketplace')]
C('ONB-040', '5 · Buying modules', 'What each plan includes', 'scenario', ['rule'],
  'PLATFORM_OWNER', 'The platform owner sells plans; each plan is a set of modules. Administration (masters, users, settings, clients, vendors) is in every plan.',
  'Signed in as the control site\'s Master Admin. Companies screen.',
  [('Open the "Plan" list on the Companies form.', 'Starter — Administration + Operations · Recruitment — Administration + People & hiring · Professional — Administration + Operations + Sales & CRM + People & hiring + Money · Enterprise — all of those + Inspection reporting.'),
   ('Note the logins each includes.', 'Starter 2 · Recruitment 3 · Professional 5 · Enterprise 9 (plus seats bought).')],
  'lib/superadmin.php; lib/saas_tenants.php')

C('ONB-041', '5 · Buying modules', 'A company buys ONE module (Sales & CRM only)', 'scenario', ['best'],
  'PLATFORM_OWNER', 'A trading company or consultancy buys only the CRM; nothing else may appear.',
  'A fresh test company "TB Sales Only" from ONB-011 (any plan).',
  [('Companies → TB Sales Only → "Modules this company has bought": tick only Sales & CRM (Administration is "(always on)") → "Save modules".', 'Saved; the Modules column shows Administration, Sales & CRM.'),
   ('Sign in as its owner (or "Log in as ›").', 'Left menu: Dashboard, Search, Sales, Insights, Directory, Admin. NO Operations, Quality & Accreditation, Reporting, Money, Recruitment or Marketplace.'),
   ('Type /calls, /invoices, /requisitions, /documents in the address bar.', 'Each shows a screen saying that part is not in your plan — not an error page, not the screen itself.'),
   ('Admin → "Modules & features".', 'Sales & CRM and Administration on; every other module shows that it is not in the plan. Pressing "Add this to my workspace" on one says "“…” isn’t in your plan yet. Upgrade to add it to your workspace."'),
   ('Add a person: look at the Role list and the Permissions tab.', 'Only roles and ticks that make sense for Sales are offered; Operations/Money ticks are not offered.'),
   ('Complete the Sales setup: MOD-SAL.', 'Sales works on its own: lead → enquiry → quotation → sent → accepted, with no Operations.')],
  'lib/licence.php; lib/setup_cockpit.php')

C('ONB-042', '5 · Buying modules', 'A company buys SEVERAL modules (Operations + Inspection reporting + Money)', 'scenario', ['best'],
  'PLATFORM_OWNER', 'A typical inspection body that does not need a CRM or hiring.',
  'A fresh test company "TB Ops Co".',
  [('Companies → TB Ops Co → tick Operations, Inspection reporting, Money → Save modules.', 'Saved.'),
   ('Sign in as its owner.', 'Left menu: Operations, Quality & Accreditation, Reporting, Money, Insights, Directory, Admin. NO Sales, Recruitment, Marketplace.'),
   ('Raise a work order (Operations → New work order).', 'Section "1. Client & quote" works without Sales: the quote is optional / not offered.'),
   ('Set up each bought module: MOD-OPS, MOD-REP, MOD-MON.', 'Each can be completed without the modules not bought.')],
  'lib/licence.php')

C('ONB-043', '5 · Buying modules', 'A company buys ALL modules (Enterprise + Marketplace)', 'scenario', ['best'],
  'PLATFORM_OWNER', 'The whole platform for a large multi-branch company.',
  'A fresh test company "TB All Co" on plan Enterprise.',
  [('Companies → TB All Co → tick every module (incl. Marketplace & Connect) → Save modules.', 'Saved.'),
   ('Sign in as its owner.', 'Every area is in the left menu: Sales, Marketplace, Operations, Recruitment, Quality & Accreditation, Reporting, Money, Insights, Directory, Admin.'),
   ('Admin → "Modules & features".', 'Seven sections, each with "Turn off this feature" except Administration ("Always on — every workspace needs it").'),
   ('Set up every module: MOD-ADM, MOD-SAL, MOD-OPS, MOD-REP, MOD-MON, MOD-HR, MOD-MKT.', 'Each completes.'),
   ('Run one flow across modules: Across modules → FLOW tests.', 'Data passes from Sales to Operations to Reporting to Money.')],
  'lib/licence.php')

C('ONB-044', '5 · Buying modules', 'Adding a module later, removing one, and switching one off', 'edge', ['edge', 'rule'],
  'PLATFORM_OWNER', 'Companies grow and shrink; nothing they recorded may be lost when a module goes.',
  'TB Sales Only from ONB-041 with a few leads and quotations.',
  [('Platform owner: add Operations to TB Sales Only → Save modules.', 'At its next page the owner sees Operations in the menu; nothing in Sales changed.'),
   ('Owner: Admin → Modules & features → Operations → "Turn off this feature" → confirm "Turn it off & hide these".', 'Operations disappears from the menu for everyone; the owner can turn it back on at any time ("Add this to my workspace") because it is still bought.'),
   ('Platform owner: untick Sales & CRM → Save modules.', 'Sales disappears from the company\'s menu. Its leads and quotations are NOT deleted.'),
   ('Tick Sales & CRM again.', 'Every lead and quotation is back exactly as it was.'),
   ('As the owner, while Sales is not bought, look at Search.', 'Search does not return Sales records.')],
  'lib/licence.php; lib/setup_cockpit.php')

C('ONB-045', '5 · Buying modules', 'Ready-made bundles for a whole installation (control site / self-hosted)', 'scenario', ['rule'],
  'PLATFORM_OWNER', 'A self-hosted customer is set up as one product bundle.',
  'Control site (or a self-hosted test install) as Master Admin. TEST install only.',
  [('Admin → "Product package".', 'Bundles: EXAACT TPIA · EXAACT Technical Staffing · EXAACT Recruitment · EXAACT Recruitment — In-house HR · EXAACT Enterprise (the current one says "In use"), and "Set up as a Recruitment-only company".'),
   ('Apply "EXAACT TPIA".', 'Sales and People & hiring are hidden; inspection features on.'),
   ('Apply "EXAACT Technical Staffing".', 'Sales hidden; Operations, People & hiring, Money on.'),
   ('Apply "EXAACT Recruitment".', 'Operations and Inspection reporting hidden.'),
   ('Apply "EXAACT Recruitment — In-house HR".', 'Only Recruitment and Administration remain; Sales, Money, Operations, Reporting and Marketplace hidden.'),
   ('Apply "EXAACT Enterprise".', 'Everything back on; nothing recorded in between was lost.')],
  'lib/licence.php PRODUCT_PACKAGES', live=False)

C('ONB-046', '5 · Buying modules', 'The company\'s own subscription page', 'scenario', ['best'],
  'MASTER_ADMIN', 'A customer must be able to see what they pay for.',
  'Signed in as a company owner.',
  [('Admin → "Your subscription" (/subscription).', 'Heading "Your subscription" with "What your plan includes today" (modules, seats) and "Payment history".'),
   ('Look for a way to buy another module or more seats.', 'Offered — or, while online payment is not switched on: "Online payment is not switched on for your workspace yet."')],
  'lib/saas_tenants.php')

# ════════════════════════════════════════════════════════════════════════════
# 6 — Setting up each module after buying it
# ════════════════════════════════════════════════════════════════════════════
C('MOD-ADM', '6 · Setting up each module', 'Administration (in every plan) — set up first', 'scenario', ['best'],
  'MASTER_ADMIN', 'Every other module reads these: company details, branches, business units, people, wording.',
  'A new company after ONB-020.',
  [('Company profile (ONB-030 step 2).', 'Complete.'),
   ('Admin → Organisation → Offices: your branches.', 'Listed.'),
   ('Admin → Masters → Business Unit → Edit values: your business units.', 'Listed (they appear in every "Business unit" choice).'),
   ('Admin → User register → + Add a person: your people (Start here → SET-06 shows how).', 'Each person follows their role (green line on Permissions).'),
   ('Admin → Roles & permissions: leave as is unless you want to change a role for everybody.', 'The grid per role.'),
   ('Admin → Terminology: "Start from your trade" → "Use this wording".', 'Words across the app follow your trade.'),
   ('Directory → Client register / Vendor register: add a first client and vendor.', 'Listed.')],
  'docs/test-book Start here')

C('MOD-SAL', '6 · Setting up each module', 'Sales & CRM — what to set up after buying it', 'scenario', ['best'],
  'MARKETING_MANAGER', 'Sales has its own guided setup page.',
  'Sales & CRM bought. MOD-ADM done.',
  [('Sales home → "Set up Sales": answer every question and add your approval levels in place.', 'Saved (SAL-002, SAL-003).'),
   ('Sales → Pre-order checklist: your points.', 'Saved.'),
   ('Sales → Pipelines & funnels → "Create one" (Name *, What is it for, Start from, Make it the default).', 'Your lead and deal pipelines.'),
   ('Admin → Document templates (/crm-templates): "Letterhead for client PDF — per company" and "Signature for client PDFs" → Save.', 'Quotation PDFs carry your letterhead and signatory.'),
   ('Sales → Quotes → Templates: a quotation e-mail and a follow-up e-mail.', 'Used when sending.'),
   ('Optional: Admin → Settings → Your industry → add your trade\'s pipelines.', 'Pipelines added; wording unchanged (D-29).'),
   ('Make one lead → enquiry → quotation → send.', 'Works end to end.')],
  'lib/setupqa.php')

C('MOD-OPS', '6 · Setting up each module', 'Operations — what to set up after buying it', 'scenario', ['best'],
  'BRANCH_MANAGER', 'Work orders, jobs, the team and their availability.',
  'Operations bought. MOD-ADM done.',
  [('Team: Admin → User register → + Add a person → "Which team": Field team member for inspectors (their own logins).', 'They appear on Operations → Team member availability.'),
   ('Admin → Service scope: "1 · Services offered on this installation" → tick what you do → "Save services offered".', 'Saved.'),
   ('Operations → SLA targets: Client, Stage, Target (days) → "Save target".', 'Listed under "Current targets".'),
   ('Masters → Operations lists (26): check work-order types, inspection types.', 'Your values present.'),
   ('Operations → New work order: tabs Client & quote · What\'s inspected · When · Offices & money · Purchase order · Reporting → save.', 'A work order is raised; allocate a job to a field team member.'),
   ('Look for one place that lists the team (team member register).', 'A team register exists, reachable from the menu.')],
  'views/ops/call_form.php; service-scope; sla-targets')
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: there is no separate team-member register (/inspectors does not exist) — team members are only added through Add a person. Expect step 6 to fail; note what you find.'

C('MOD-REP', '6 · Setting up each module', 'Inspection reporting — what to set up after buying it', 'scenario', ['best'],
  'BRANCH_APP_MANAGER', 'Report types, numbering, formats and signatures must exist before the first report.',
  'Inspection reporting bought (needs Operations for jobs). MOD-OPS done.',
  [('Admin → Report types: "⭐ Ready-made inspection report" → "Set up the ready inspection report"; or "Add a report type" (Code *, Name *, Category, Active, Format No., Document No., Revision) → "Add type".', 'Types listed.'),
   ('Admin → IRN numbering rules: "IRN format — must contain {SERIAL}", Company code, Serial width → "Save numbering rules". Optional per-office patterns.', 'Saved; a format without {SERIAL} is refused.'),
   ('Admin → Report formats by service line → "Save report formats".', 'Saved.'),
   ('Admin → Quote & report approvals (report): who approves reports.', 'Saved.'),
   ('Each signatory: My signature → upload.', 'Shown on issued reports.'),
   ('On a job, start a report, fill it, submit, approve, issue.', 'An IRN in your format is given; the PDF carries the signature.')],
  'irn-rules; report-types; service-formats')

C('MOD-MON', '6 · Setting up each module', 'Money — what to set up after buying it', 'scenario', ['best'],
  'FINANCE', 'Invoices need the company\'s GSTIN, numbering and (optionally) Tally ledgers.',
  'Money bought. Company profile has the GSTIN.',
  [('Company profile: GSTIN, PAN, State filled.', 'Invoices carry them.'),
   ('Money → New invoice: sections "Who it is for" and "Terms" → save a test invoice.', 'An invoice number in the series of the invoicing branch.'),
   ('Money → Tally export: our state, Sales / Bank / TDS / CGST / SGST / IGST / Round-off ledgers → Save; "⬇ CSV (to look at first)".', 'A CSV with the test invoice.'),
   ('Masters → Money & billing lists (5): payment terms etc.', 'Your values present.')],
  'invoice-new; tally')

C('MOD-HR', '6 · Setting up each module', 'People & hiring — what to set up after buying it', 'scenario', ['best'],
  'MASTER_ADMIN', 'Requisitions, candidates and approvals need their workflows and rules.',
  'People & hiring bought.',
  [('Recruitment → Hiring workflows: the default "Corporate Recruitment Workflow" and its Stages; "＋ New workflow" (Workflow name, Code, Description, Company, Business unit, Department, Position, Employment type, Grade, Location) → "Save workflow".', 'Your workflow with its stages.'),
   ('Recruitment approvals: self-approval policy and "Also raise a review when the requirement is redefined" → Save policy / Save triggers; "＋ New rule" for who approves.', 'Saved.'),
   ('Admin → Forms (/workspace/setup/forms): the Requisitions and Candidates forms — add a field.', 'The field appears on the form.'),
   ('Masters → Recruitment & people lists (27).', 'Your values present.'),
   ('Optional: Careers page (Admin → Careers page) switched on.', 'Public /careers takes applications into Candidates.'),
   ('Raise a requisition → approve → add a candidate → move through the stages.', 'Works end to end.')],
  'recruit-pipelines; recruit-approvals; workspace/setup/forms')

C('MOD-MKT', '6 · Setting up each module', 'Marketplace & Connect — what to set up after buying it', 'scenario', ['best'],
  'MASTER_ADMIN', 'The marketplace joins companies that need people with professionals and agencies.',
  'Marketplace bought.',
  [('Platform owner: Marketplace plans & limits → "Global settings": Marketplace add-on, "Charge for the marketplace" (OFF = free during launch), plans for clients and professionals, credit packs, coupons → Save settings.', 'Saved.'),
   ('Company: Directory → Client portal → switch the portal on.', 'Client and agency accounts can sign in at /portal/login (see CLI-001).'),
   ('Company: Marketplace → Manpower marketplace: post a requirement; Agency bench; Talent search.', 'Each works.'),
   ('Verification desk (/connect-verify): professionals waiting for ID / credential checks.', 'The queue with "✓ Verify" / "✕ Reject".')],
  'marketplace-plans; connect-verify; portal-users')

# ════════════════════════════════════════════════════════════════════════════
# 7 — A professional joins the marketplace
# ════════════════════════════════════════════════════════════════════════════
C('PRO-001', '7 · A professional joins', 'A professional creates a profile — best case', 'scenario', ['best'],
  'PROFESSIONAL', 'Inspectors and engineers list themselves for work.',
  'Signed out. Marketplace on.',
  [('/connect → "Create a professional profile →".', 'Heading "List yourself for work": Your name, E-mail, Mobile, "Password (8+ characters)", "Create my profile"; links "Sign in" and "See all options →".'),
   ('Fill with test data (your e-mail) → Create my profile.', 'Signed in at once and taken to "My profile". Menu: Home, Open jobs, My applications, My bookings, My vouchers, My profile, My reputation, Messages, Get verified.'),
   ('My profile: expertise, mobility, tax (GST status / GSTIN / PAN), credentials, CV.', 'Saved.'),
   ('Open jobs.', 'Requirements matching your expertise; "all" shows every open one. Apply with a rate and a note.'),
   ('Get verified → submit PAN and a credential.', 'Shown as pending; your tier stays "Registered" until checked.'),
   ('Staff (Verification desk) → "✓ Verify".', 'Your tier rises (ID-verified → Credential-verified).'),
   ('Your public passport link (/p/…).', 'Shows your verified profile.')],
  'lib/connect_pro.php; lib/connect_verify.php')

C('PRO-002', '7 · A professional joins', 'Professional sign-up — the worst cases', 'edge', ['edge', 'worst'],
  'PROFESSIONAL', 'Duplicates and weak passwords on a public form.',
  'PRO-001 done.',
  [('Register again with the same e-mail.', '"That e-mail is already registered — sign in instead."'),
   ('Register with a new e-mail but the same 10-digit mobile.', '"A profile already uses this mobile number — sign in to that account instead of creating a duplicate."'),
   ('Password "1234".', '"Choose a password of at least 8 characters."'),
   ('Empty name / e-mail "abc".', '"Enter your name." / "Enter a valid e-mail address."'),
   ('/pro/login → "Forgot your password?" → your e-mail.', 'An e-mail with a link valid 1 hour.')],
  'lib/connect_pro.php')

# ════════════════════════════════════════════════════════════════════════════
# 8 — A company / agency joins to post requirements
# ════════════════════════════════════════════════════════════════════════════
C('CLI-001', '8 · A company posts a requirement', 'A company that needs people creates an account and posts a requirement', 'scenario', ['best'],
  'CLIENT_USER', 'The buyer side of the marketplace.',
  'Signed out. Marketplace on. Client portal switched ON by staff (MOD-MKT step 2).',
  [('/connect → "Create a company account →".', 'Heading "Set up your organisation": 1 "Your organisation\'s name"; 2 "What does your organisation do?" (ticks in four groups: Inspection & Technical Services, Resource Supply, Recruitment, Project Services) and "Set me up automatically — based on what I ticked above (recommended)"; 3 Your name, Work e-mail, Mobile, "Choose a password". Button "Create my account →".'),
   ('Fill with test data, tick "Third-Party Inspection Agency" → Create my account.', '"Thanks — we have your details. We have sent the next step to <e-mail>" and "Sign in →". An e-mail "Your <app name> account is ready".'),
   ('Sign in → /portal/login.', 'The client portal; with no inspection work on file it opens on hiring.'),
   ('Post a requirement ("What do you need? *", Discipline, Sector, Where, Positions, Start, Rate up to, Details, rate model, GST %, TDS %) → post.', '"Your requirement is posted and open for applications." Professionals (PRO-001) see it under Open jobs.'),
   ('Empty title → post.', '"Give the requirement a one-line title… Nothing was posted."')],
  'lib/connect_org.php; lib/portal.php')
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: if staff have not switched the client portal on, the account is created and the e-mail says "sign in now", but /portal/login shows "Not found". Also: no GSTIN/PAN boxes on the form, and the portal has no "forgot password" although the e-mail mentions one.'

C('CLI-002', '8 · A company posts a requirement', 'Duplicates and agencies', 'edge', ['edge'],
  'CLIENT_USER', 'The same company must not end up twice; an agency gets its bench.',
  'CLI-001 done.',
  [('Create an account again with the same e-mail.', 'The screen shows the same neutral "we have sent the next step"; the e-mail says an account already exists. Nothing new is created.'),
   ('Same organisation name, new e-mail.', 'Nothing created; an access request waits for staff (Access requests); the e-mail says so.'),
   ('/connect → "Create an agency account →" and finish.', 'The agency signs in and has "My bench" to list its people.'),
   ('Look for the client plans page (subscribe, credits, coupons) from the portal menu.', 'Reachable from the menu.')],
  'lib/connect_org.php; lib/portal.php')
CASES[-1]['tags'].append('gap'); CASES[-1]['why'] += ' Measured 22 Oct: the Plans pages (/portal/plans, /pro/plans) are not in any menu — reachable only by typing the address. Expect step 4 to fail.'

# ════════════════════════════════════════════════════════════════════════════
# 9 — Self-hosted install (licence)
# ════════════════════════════════════════════════════════════════════════════
C('ONB-060', '9 · Self-hosted install', 'A customer installs EXAACT on their own server', 'scenario', ['best'],
  'MASTER_ADMIN', 'The other way to buy: a licence for one company\'s own server.',
  'A fresh self-hosted TEST install (never production).',
  [('Open the new install.', '"Software Licence Agreement": full name, designation, organisation, work e-mail, three ticks → "Accept and continue installation". Missing fields: "Please fill your name, designation and organisation."'),
   ('"Set up the system": "Start now — one click" (or "Connect MySQL instead" → Test & save).', 'The sign-in page.'),
   ('Sign in as admin with the installer password.', 'Forced password change, then "Welcome — let’s set up your system", then the setup cockpit (ONB-030).'),
   ('Admin → Licence → "Paste the licence key MGH sent you" → "Apply the key".', 'The modules in the key switch on; others show as not in your plan.'),
   ('Without a key, with enforcement on.', 'A 14-day trial; after it the system becomes read-only, never locked.')],
  'lib/agreement.php; lib/licencekey.php', live=False)

out = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'cases_onboarding.json')
json.dump(CASES, open(out, 'w'), ensure_ascii=False, indent=1)
print(len(CASES), 'cases', sum(len(c['steps']) for c in CASES), 'steps')
