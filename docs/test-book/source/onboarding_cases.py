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
C('ONB-001', '1 · A company applies', 'Applying (and waiting for approval) is a switch the platform owner turns on', 'scenario', ['rule'],
  'PLATFORM_OWNER', 'Companies can apply only while the platform owner allows it; it is OFF on a new installation.',
  'Signed in as the Master Admin of the CONTROL site (the platform owner).',
  [('Left menu → Admin → Super Admin → "Cloud workspaces" (or /tenants).', 'Heading "Cloud workspaces". If cloud mode is not on yet: "Switch cloud mode on" with a "Base domain" box — set it first (e.g. operations.mghaiapps.com).'),
   ('Panel "Workspace requests" → press "Online registration: OFF — turn on".', 'Message "Online workspace registration is ON — companies can now apply at /get-started." The button now reads "Online registration: ON — turn off".'),
   ('Signed out, open /login.', 'The line "Run an inspection company and want your own workspace? Start here →" now shows.'),
   ('Turn it OFF again and open /get-started signed out.', '"We set up workspaces personally… Online self-registration isn\'t open just yet." — no form. Turn it back ON for the next tests.'),
   ('Note: when SELF sign-up is on (ONB-006), /get-started forwards to /start instead — the company creates its own workspace without waiting.', 'Opening /get-started lands on "Start your free trial".')],
  'lib/tenants.php; views/ops/tenants.php', live=False)

C('ONB-002', '1 · A company applies', 'A new company applies and waits for approval — best case', 'scenario', ['best'],
  'NEWCOMER', 'The route for companies you want to approve yourself (self sign-up OFF).',
  'ONB-001: online registration ON; self sign-up OFF (ONB-006). Signed out, private window.',
  [('Open /get-started (or "Start here →" on the sign-in page).', 'Heading "Run your inspection company on Inspection Ops", ribbon "◆ Free during launch". Fields: Company name, Your name, "Work e-mail — we\'ll send your workspace details here", "Phone — optional", "Preferred workspace name — optional, letters & numbers" (with the domain shown after it), "Anything we should know? — optional". Button "Request my workspace →".'),
   ('Company name "TB Test Inspections", Your name "Test Owner", Work e-mail (one you can read), Phone, Preferred workspace name "tbtest", note "Testing onboarding" → "Request my workspace →".', 'A ✅ and "Request received" with three numbered steps ("We review", "We create your workspace and e-mail you how to sign in", "You sign in"), and that you will hear at the e-mail you gave, usually within one working day.'),
   ('Check that e-mail inbox.', 'An e-mail "We have your request for a … workspace" arrives (fixed 22 Oct — it used to send nothing).')],
  'views/public/get_started.php; lib/tenant_signup.php', )

C('ONB-003', '1 · A company applies', 'Applying with bad or repeated details', 'edge', ['edge', 'worst'],
  'NEWCOMER', 'A public form gets every kind of mistake.',
  'Online registration ON. Signed out.',
  [('Leave Company name empty → Request.', 'The browser asks for the company name (or "Please enter your company name.").'),
   ('Work e-mail "abc" → Request.', '"Enter a valid work e-mail address."'),
   ('Preferred workspace name "TB Test!!" (symbols).', 'Refused by the box (letters, numbers and hyphens only) — or, if it gets through, the name is made from the company name instead.'),
   ('Submit a second request with the SAME e-mail as ONB-002.', '"We already have a request in progress for that e-mail — we will be in touch shortly." Nothing new is created.'),
   ('Company name of 300 characters; note of 5,000 characters.', 'Saved or refused with a sentence — never an error page.'),
   ('Send 6 requests within an hour from the same computer, each with a different e-mail.', 'The 6th: "Too many requests from this connection. Please try again in an hour." (fixed 22 Oct — there was no limit).'),
   ('Leave the page open for a long time (or sign out in another tab), then press the button.', '"Your page had been open too long. Please press the button again." — nothing typed is lost.')],
  'lib/tenant_signup.php')

C('ONB-004', '1 · A company applies', 'A company signs itself up and starts a 30-day free trial — best case', 'scenario', ['best', 'rule'],
  'NEWCOMER', 'The owner\'s decision of 22 Oct: a company can start on its own, try every module for 30 days without a card, and buy when ready.',
  'ONB-006: self sign-up ON. Signed out, private window. Use an e-mail you can read.',
  [('Open /start (or "Start your free trial →" on the sign-in page, or "Start your free trial →" on the marketplace front page).', 'Heading "Start your free trial", ribbon "◆ 30 days free · no card needed". Fields: Company name, Your name, "Work e-mail — this is how you sign in", "Mobile — optional", "Choose a password — at least … characters", and a tick "I agree: the trial is free for 30 days…". Button "Start my free trial →".'),
   ('Fill with test data ("TB Selfserve Co"), tick the agreement → "Start my free trial →".', '"We have sent a 6-digit code to <e-mail>." and a box "Enter the 6-digit code from your e-mail".'),
   ('Open the e-mail "… — your sign-up code ……" and type the code → "Create my workspace →".', 'You are inside your own new workspace at once — no waiting, no second password change. "Welcome! Your workspace is ready and your free trial has started." and "Free trial — 30 days left (until <date>)."'),
   ('The setup wizard "Welcome — let’s set up your system".', 'It does NOT ask for a password again (you just chose one): it starts at "1 · What does your company do?". Finish it → the setup page.'),
   ('Look at the left menu.', 'Every module is there during the trial.'),
   ('Check your inbox.', 'An e-mail "Your … workspace is ready" with the sign-in address, your username, the trial end date, what happens after the trial, and the 90-day rule.'),
   ('Sign out, then sign in at the product address with your e-mail and password.', 'Straight into your workspace.')],
  'lib/selfserve.php; views/public/start.php')

C('ONB-005', '1 · A company applies', 'Self sign-up — the worst cases', 'edge', ['edge', 'worst'],
  'NEWCOMER', 'A public form gets mistakes, repeats and robots.',
  'ONB-006: self sign-up ON. Signed out.',
  [('Empty company name / empty name / e-mail "abc" → Start.', '"Please enter your company name." / "Please enter your name." / "Enter a valid work e-mail address."'),
   ('Password "12345678".', 'Refused with a sentence saying why.'),
   ('Leave the agreement unticked.', '"Please tick that you agree to the trial terms."'),
   ('Use an e-mail that already has a workspace (ONB-004).', '"That e-mail already has a workspace — sign in instead, or use "Forgot password?"."'),
   ('On the code screen type a wrong code.', '"That code is not right. 4 tries left." After 5 wrong codes: "Too many wrong codes. Press "Send a new code"."'),
   ('Press "Send a new code".', '"A new code has been sent to <e-mail>." The old code no longer works.'),
   ('Wait more than 30 minutes, then type the code.', '"That code has run out. Press "Send a new code"."'),
   ('Press "Create my workspace →" twice quickly.', 'Only ONE company is created.'),
   ('Start 6 sign-ups within an hour from the same computer.', 'The 6th: "Too many sign-ups from this connection. Please try again in an hour."')],
  'lib/selfserve.php')

C('ONB-006', '1 · A company applies', 'The platform owner switches self sign-up on and sets the trial rules', 'scenario', ['rule'],
  'PLATFORM_OWNER', 'Self sign-up is the platform owner\'s choice; the numbers are theirs to set.',
  'Signed in as the control site\'s Master Admin; cloud mode on (ONB-001 step 1).',
  [('Super Admin → "Companies" → panel "🚀 Self sign-up & free trial".', 'Tick "Companies can sign themselves up", boxes "Free trial (days)" (30), "Trial storage limit (MB)" (not set — no limit), "Logins during the trial" (not set — no limit), "Warn unused trials every (days)" (15), "Delete unused trials after (days)" (90), tick "Delete unused free trials automatically", "Save".'),
   ('Tick "Companies can sign themselves up" → Save.', '"Self-serve sign-up settings saved." The panel title reads "— ON". /get-started now forwards to /start.'),
   ('Set "Delete unused trials after" to 10 (less than the 15-day warning) → Save.', '"Saved — but deletion comes before the first warning with these numbers…" Put it back to 90.'),
   ('Set a storage limit and a number of trial logins (your numbers) → Save.', 'Saved; new trial companies get them.'),
   ('Untick "Companies can sign themselves up" → Save.', '/start forwards to /get-started again (apply and wait for approval).')],
  'lib/selfserve.php; views/ops/saas_companies.php', live=False)

# ════════════════════════════════════════════════════════════════════════════
# 2 — The platform owner approves / creates the company
# ════════════════════════════════════════════════════════════════════════════
C('ONB-010', '2 · The platform owner creates it', 'Approving a workspace request', 'scenario', ['best', 'rule'],
  'PLATFORM_OWNER', 'A request becomes a company only when the platform owner approves it.',
  'ONB-002 request exists. Signed in as the control site\'s Master Admin.',
  [('Super Admin → "Cloud workspaces" → panel "Workspace requests".', 'A "1 new" pill. The row shows TB Test Inspections, the e-mail, phone, note and an editable workspace name "tbtest", with "Approve" and "Decline".'),
   ('Change the workspace name to one already in use → Approve.', '"A workspace named "…" already exists — type a different name…" Nothing is created.'),
   ('Set it back to "tbtest" → Approve → confirm "Approve "TB Test Inspections" and create this workspace?".', '"Workspace for “TB Test Inspections” created on a 30-day free trial. Its owner signs in with <e-mail> (temporary password: …). These details have been e-mailed to them." — nothing left to finish by hand.'),
   ('The applicant checks their inbox.', 'An e-mail "Your … workspace is ready" with the sign-in address, their username (their e-mail) and a temporary password.'),
   ('The applicant signs in at the product address with that e-mail and temporary password.', 'Asked to choose their own password, then the setup wizard; a "Free trial — 30 days left" line on every screen. (Fixed 22 Oct — approval used to create no login and send nothing.)'),
   ('Super Admin → Companies.', 'TB Test Inspections is listed: Plan "Free trial", "signed itself up", trial until a date 30 days ahead.')],
  'lib/tenant_signup.php; views/ops/tenants.php')

C('ONB-011', '2 · The platform owner creates it', 'Creating a company directly — "Companies" (with its plan)', 'scenario', ['best'],
  'PLATFORM_OWNER', 'The complete way to create a company today: it creates the owner\'s login, the plan and the seat limit together.',
  'Signed in as the control site\'s Master Admin.',
  [('Super Admin → "Companies" (/companies).', 'Heading "Companies". Form: "Company name *", "Workspace key (optional — made from the name)", "Owner name", "Owner email * (their login)", "Temporary password (blank = auto)", "Plan" (Starter / Recruitment / Professional / Enterprise, each listing its modules), "Where this company\'s data is stored" (Set it up for me / Use a MySQL database). Button "Create company".'),
   ('Company "TB Owner Co", Owner name "Test Owner", Owner email (one you can read), leave the password blank, Plan "Professional", data "Set it up for me" → "Create company".', '"Company "TB Owner Co" is ready. Its owner signs in at the one product URL with <email> (temporary password: …)…" The company is listed with Plan, Seats, Modules and Status.'),
   ('Edge: create another company with the SAME owner e-mail.', '"That owner email already belongs to a company."'),
   ('Edge: Workspace key "TB Owner!" (capitals, symbols).', '"The workspace key must be lowercase letters, digits or hyphens."'),
   ('Edge: leave Owner email empty.', '"A company name and a valid owner email are required."'),
   ('Read the message after "Create company".', '"…These sign-in details have been e-mailed to the owner." — or, where e-mail is not set up: "E-mail is not set up, so please pass these details on yourself."'),
   ('The owner checks their inbox.', 'An e-mail "Your … workspace is ready" with the address, username and temporary password; they must choose their own password at first sign-in.')],
  'lib/saas_tenants.php; views/ops/saas_companies.php')

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
   ('Section "Get started" (checklist) → press each "Do this".', '"Tell us what your company does" → Company profile; "Invite your team" → User register; "Add your people" → "Team member register"; "Raise your first work order" → New work order; "Post your first requirement" and "Build your bench" (only with the Marketplace) → those screens. Never the home page (fixed 22 Oct).'),
   ('Switch Operations off (Admin → Modules & features) and look at the checklist again.', '"Add your people" and "Raise your first work order" are no longer offered; switch Operations back on.'),
   ('"Setup health" lines (e.g. "You are the only user…") → "Fix".', 'Each opens the screen that fixes it.')],
  'lib/setup_cockpit.php; views/ops/cockpit_home.php')

C('ONB-031', '4 · Setting up the company', 'The dashboard "Finish setting up" list agrees with what was done', 'edge', ['edge'],
  'MASTER_ADMIN', 'Two checklists must not disagree about the same work.',
  'ONB-020: setup wizard finished with a company name.',
  [('Open the Dashboard.', 'A panel "Finish setting up …" with its steps; "Say who you are" (the company name) is ticked, because you gave the name in the setup wizard (fixed 22 Oct). Its button opens Company profile.'),
   ('Compare with the cockpit (ONB-030).', 'The same things are shown as done in both places.')],
  'lib/setup.php')

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

C('ONB-047', '5 · Buying modules', 'A trial company chooses its own modules and pays online', 'scenario', ['best', 'rule'],
  'MASTER_ADMIN', 'The owner\'s decision: a company buys the modules it wants itself and pays directly.',
  'ONB-004 company, on its trial. The platform owner has set prices and Razorpay keys (TEST keys on a test copy).',
  [('Admin → "Your subscription" (/subscription) — or "Choose your plan →" on the trial line at the top of any screen.', 'Cards "Your workspace: Free trial", "Until", "Days left". "You are trying every module free until … Choose the modules you want to keep below — you pay only for those." Panel "Choose your plan".'),
   ('Tick nothing → "Choose this plan & pay →".', '"Pick at least one module." Nothing charged.'),
   ('Tick Operations and Sales & CRM, "How many people will sign in?" 3, "Pay" Monthly.', 'The "Plan price" updates as you tick (each module\'s price + 3 × the price per login).'),
   ('"Choose this plan & pay →" → pay in the Razorpay window with a TEST card / UPI.', '"Payment received. Your plan is active until <date one month ahead>."'),
   ('Look at the left menu and the subscription page.', 'Only Operations and Sales (plus Administration) remain; the other modules are switched off. Their records are kept.'),
   ('Add a 4th person.', '"This company is on 3 seats and all 3 are in use…" — buy more logins on the same page.'),
   ('Super Admin → Companies (the next day, or after "Check when every company was last used").', 'The company shows Plan "Own choice", its paid-until date, and "3 bought" logins.')],
  'lib/selfserve.php; views/ops/_subscription_plan.php', live=False)

C('ONB-048', '5 · Buying modules', 'When the trial ends without a plan: view-only, never locked out', 'scenario', ['rule'],
  'MASTER_ADMIN', 'The owner\'s rule: "lock out with notice to upgrade plan but they shall be able to access the data with view only permission".',
  'A trial company whose trial has ended (on a test copy a developer can move the date; otherwise wait for the trial to end).',
  [('Sign in as the owner.', 'Every screen says: "Your free trial ended on <date>. Your workspace is view-only — everything can be opened and exported, but nothing new can be saved." with "Choose your plan →".'),
   ('Open any register, record, report PDF; export to Excel.', 'Everything opens and exports.'),
   ('Try to save anything (a new lead, a company profile change).', '"Your free trial has ended, so your workspace is view-only: everything can be opened, printed and exported, but nothing new can be saved. Choose a plan to carry on." — taken to Your subscription. Nothing saved.'),
   ('Sign in as a colleague who is not an administrator and try to save.', 'The same refusal, ending "Ask your workspace owner to choose a plan." They stay on their screen.'),
   ('The owner chooses a plan and pays (ONB-047).', 'Saving works again at once.')],
  'lib/selfserve.php; index.php', live=False)

C('ONB-049', '5 · Buying modules', 'Changing plan later — upgrade, renew, downgrade', 'edge', ['rule', 'edge'],
  'MASTER_ADMIN', 'The owner\'s rule of 19 Oct (Q-L): upgrade now with credit, renew and downgrade at the end of the period.',
  'A company with a paid plan running (ONB-047).',
  [('Your subscription → tick one more module → "Change or renew →".', 'An UPGRADE: it starts today; the unused days of the current plan are taken off the price; the new period runs one month from today.'),
   ('Choose exactly the same modules and logins again.', 'A RENEWAL: paid now, it starts the day after the current period ends; nothing is lost.'),
   ('Untick a module (a smaller plan).', '"Your smaller plan starts on <date>, when the current one ends. Nothing is charged now." The page shows "Booked: from <date> your plan becomes …". Everything stays switched on until then.'),
   ('On that date (or after), open Your subscription.', 'The smaller selection is offered to be paid.')],
  'lib/selfserve.php', live=False)

C('ONB-050', '5 · Buying modules', 'Trial storage limit', 'edge', ['rule', 'edge'],
  'MASTER_ADMIN', 'The owner\'s rule: the trial has a limit on storage. The number is the platform owner\'s (ONB-006).',
  'The platform owner has set "Trial storage limit (MB)" to a small number on a test copy, BEFORE the company signed up.',
  [('Your subscription during the trial.', 'A card "Trial storage used: … of … MB".'),
   ('Keep attaching files until the limit is reached, then attach one more.', '"Your free-trial storage is full, so this file was not added. Choose a plan to add more files — nothing else you typed has been lost."'),
   ('Save a record without a file.', 'Saved — only new files are refused.'),
   ('Choose a plan (ONB-047) and attach a file.', 'Accepted — the trial limit no longer applies; the paid allowance does (ONB-052).')],
  'lib/selfserve.php', live=False)

C('ONB-052', '5 · Buying modules', 'Storage allowance for paid companies — usage, warnings, full, extra storage', 'scenario', ['best', 'rule'],
  'PLATFORM_OWNER', 'The owner (22 Oct): "integrate storage usage and limits on the paid users also" — inspections can be about 100 MB of photos each.',
  'Signed in as the control site\'s Master Admin. A company on a plan (console-created or self-serve and paid). TEST companies only.',
  [('Super Admin → Companies → "💾 Storage allowance": Base storage per company (GB), Storage per login (GB), Extra storage pack size (GB), Pack price per month / per year → "Save and apply to every company".', '"Storage allowance saved and applied to N companies." (Use your own numbers.)'),
   ('Companies list → column "Storage".', 'Each company: space used and "…% of … GB" — or "no limit" where nothing is set.'),
   ('As that company\'s owner: Admin → Your subscription → panel "Storage".', 'A bar "… of … GB used" with the %, "Your allowance: … GB with your plan + … GB × N logins …", and "What is using the space" by area (Inspection reports & photos, Sales documents, …, Records and everything else).'),
   ('Upload a 4 MB camera photo on a report, then look again.', 'Only a few hundred KB more is used — photos are made smaller automatically on upload.'),
   ('(Test copy) give the company a tiny allowance so it is over 80%, then run "Check when every company was last used" and the nightly job.', 'Admins see "Storage …% used (… of …)" on every screen; the owner is e-mailed once at 80%, once at 95%, once at 100%.'),
   ('At 100%: attach a file to anything.', '"Your storage is full, so this file was not added…" — everything else typed is kept. Saving a record WITHOUT a file still works; every stored file still opens.'),
   ('Self-serve company: Your subscription → "Extra storage — packs of … GB" → 1 → pay.', 'The allowance grows by one pack at once; uploads work again. (Fewer packs later = a downgrade at the end of the period.)'),
   ('Console company: Super Admin → Manage → "Extra storage granted (GB)" → Save storage.', '"Extra storage for … saved and applied to their workspace." The allowance grows.')],
  'lib/selfserve.php; views/ops/subscription.php; views/ops/saas_companies.php', live=False)

C('ONB-051', '5 · Buying modules', 'A company cannot set its own prices or fake a payment', 'access', ['worst', 'rule'],
  'MASTER_ADMIN', 'Two holes closed on 22 Oct: a company\'s own administrator could change the prices and payment keys its purchases used; and the page told the server what had been bought.',
  'Signed in as a company\'s Master Admin (not the platform owner).',
  [('Admin → Billing → change the price per user or the Razorpay keys → Save.', '"Prices and payment keys are set by your provider, not inside your workspace." Nothing saved.'),
   ('(Tester with browser tools) open the payment page, change the hidden module list, then pay.', 'Exactly what was priced is applied — the server uses its own record of the order.'),
   ('Press the browser\'s Back after paying and submit the payment confirmation again.', '"That payment has already been applied, or its order is not known here. Nothing was changed twice."')],
  'lib/billing.php; lib/saas_tenants.php; lib/selfserve.php', live=False)

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
  [('Team: Admin → Masters → Inspectors ("Team member register") → "+ Add team member" for each inspector / technician.', 'They are listed in the Team member register and appear on Operations → Team member availability.'),
   ('Those who need a login: Admin → User register → + Add a person → link to the team member.', 'They sign in and see their own jobs.'),
   ('Admin → Service scope: "1 · Services offered on this installation" → tick what you do → "Save services offered".', 'Saved.'),
   ('Operations → SLA targets: Client, Stage, Target (days) → "Save target".', 'Listed under "Current targets".'),
   ('Masters → Operations lists (26): check work-order types, inspection types.', 'Your values present.'),
   ('Operations → New work order: tabs Client & quote · What\'s inspected · When · Offices & money · Purchase order · Reporting → save.', 'A work order is raised; allocate a job to a field team member.'),
   ('Setup page → "Add your people" → "Do this".', 'Opens the "Team member register" (it used to point at an address that does not exist — fixed 22 Oct).')],
  'views/ops/call_form.php; service-scope; sla-targets')

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
  'Signed out. Marketplace on (the client portal does NOT need to be switched on for marketplace accounts).',
  [('/connect → "Create a company account →".', 'Heading "Set up your organisation": 1 "Your organisation\'s name", GSTIN (optional), PAN (optional, filled from the GSTIN if you give one); 2 "What does your organisation do?" (ticks in four groups: Inspection & Technical Services, Resource Supply, Recruitment, Project Services) and "Set me up automatically — based on what I ticked above (recommended)"; 3 Your name, Work e-mail, Mobile, "Choose a password". Button "Create my account →".'),
   ('Fill with test data, tick "Third-Party Inspection Agency" → Create my account.', '"Thanks — we have your details. We have sent the next step to <e-mail>" and "Sign in →". An e-mail "Your <app name> account is ready".'),
   ('Type a wrong GSTIN ("24ABCDE1234F1Z") and try again.', '"That GSTIN does not look right — it is 15 characters, e.g. 24ABCDE1234F1Z5. Leave it empty if you do not have one."'),
   ('Sign in → /portal/login (even with the client portal switched off by staff).', 'The sign-in page opens and you get in; with no inspection work on file it opens on hiring. (Fixed 22 Oct — it said "Not found".)'),
   ('Sign out → "Forgot your password?" → your e-mail → "Send me a link".', '"If that e-mail address has an account here, we have sent it a link…". The e-mail "… — choose a new password" has a link that works once, for one hour.'),
   ('Open the link → "Choose a new password" → type it twice → "Save and sign in".', 'Signed in with the new password. Opening the same link again: "This link is not valid — it may already have been used."'),
   ('Post a requirement ("What do you need? *", Discipline, Sector, Where, Positions, Start, Rate up to, Details, rate model, GST %, TDS %) → post.', '"Your requirement is posted and open for applications." Professionals (PRO-001) see it under Open jobs.'),
   ('Empty title → post.', '"Give the requirement a one-line title… Nothing was posted."')],
  'lib/connect_org.php; lib/portal.php')

C('CLI-002', '8 · A company posts a requirement', 'Duplicates and agencies', 'edge', ['edge'],
  'CLIENT_USER', 'The same company must not end up twice; an agency gets its bench.',
  'CLI-001 done.',
  [('Create an account again with the same e-mail.', 'The screen shows the same neutral "we have sent the next step"; the e-mail says an account already exists. Nothing new is created.'),
   ('Same organisation name, new e-mail.', 'Nothing created; an access request waits for staff (Access requests); the e-mail says so.'),
   ('/connect → "Create an agency account →" and finish.', 'The agency signs in and has "My bench" to list its people.'),
   ('Portal menu → "Plan & credits"; and as a professional, menu → "Plan & credits".', 'Both open the plan page (subscription, credits, coupons). (Fixed 22 Oct — they were in no menu.)'),
   ('As a client that STAFF invited (not self-joined), with the client portal switched OFF, try to sign in.', '"The client portal is switched off at the moment. Ask your contact at … about access." — staff decide for their invited clients.')],
  'lib/connect_org.php; lib/portal.php')

# ════════════════════════════════════════════════════════════════════════════
# 10 — Unused free trials (the owner's 90-day rule)
# ════════════════════════════════════════════════════════════════════════════
C('ONB-070', '10 · Unused trials', 'Super Admin sees when every company was last used', 'scenario', ['best', 'rule'],
  'PLATFORM_OWNER', 'The owner: "Our superadmin panel must show when last data was accessed."',
  'Some companies exist; at least one self sign-up company.',
  [('Super Admin → Companies → "Check when every company was last used".', '"Checked N companies for when they were last used. Nothing was e-mailed or deleted."'),
   ('Read the column "Last used".', 'For each company: the date and "N days ago". A self sign-up company also shows "signed itself up", its trial or paid-until date, and — if it has never paid — "deleted <date> if unused" and how many warnings were sent.'),
   ('Use "Log in as ›" on a company, then check again.', 'Your own visit as platform owner does NOT count as the company using it.'),
   ('Sign in as a real user of that company, then check again.', '"Last used" moves to today.')],
  'lib/selfserve.php; views/ops/saas_companies.php')

C('ONB-071', '10 · Unused trials', 'A free trial nobody uses is warned every 15 days and deleted after 90', 'scenario', ['rule'],
  'PLATFORM_OWNER', 'The owner: "if any company with free trial does not access its login for 90 days the data shall be deleted and email notification for every 15 days shall be provided".',
  'A self sign-up company that has never paid. Runs from the nightly job; proven by the automated test tests/test_selfserve.php (dates moved forward). To see it live, leave a test company unused.',
  [('After 15 days with nobody signing in: the owner\'s inbox.', 'An e-mail "Your trial workspace <company> will be deleted on <date>" — nobody has signed in for 15 days; sign in to keep it, or choose a plan to keep it for good.'),
   ('Day 30, 45, 60, 75.', 'One more warning each time; Companies shows the count.'),
   ('Someone signs in on day 40.', 'The warnings start over from that day.'),
   ('Day 90 with nobody signing in.', 'The company and its data are deleted; the owner gets "Your trial workspace <company> has been deleted"; Companies lists it under deleted companies with the reason.'),
   ('A company that has EVER paid, unused for 120 days.', 'Never warned, never deleted — it is view-only once its plan ends.'),
   ('Super Admin unticks "Delete unused free trials automatically".', 'Nothing is deleted while it is off.')],
  'lib/selfserve.php; cron.php', live=False)

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
