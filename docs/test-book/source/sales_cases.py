"""Sales test cases, hand-written against the screens measured on 20 Oct 2026
(screens_today.json) and the rules built that day (D-01 … D-29).

Each case: id, module, title, kind, tags, persona, company, why, before, steps[(do, expect)], ref, live.
`live` False = run on a test workspace, never on the live company (it changes company-wide settings).
"""

CASES = []


def C(id, module, title, kind, tags, persona, why, before, steps, ref='', company='ANY', live=True):
    CASES.append({'id': id, 'area': 'Sales', 'module': module, 'title': title, 'kind': kind, 'tags': tags, 'persona': persona,
                  'company': company, 'why': why, 'before': before, 'steps': [{'do': d, 'expect': e} for d, e in steps],
                  'ref': ref, 'live': live})


# ════════════════════════════════════════════════════════════════════════════
# S0 — Before you start: the test company, its people and its answers
# ════════════════════════════════════════════════════════════════════════════
C('SAL-000', 'Before you start', 'Check that each Sales test user sees exactly what their role allows', 'access', ['best', 'rule'],
  'MASTER_ADMIN', 'Every Sales rule depends on who is signed in. If a person\'s role, branch or ticks are wrong, every later result is wrong.',
  'Start here → SET-06 is done: the 19 test users exist, with NO extra permission ticks and nothing ticked on Data scope (except Test SBUHead).',
  [('Admin → User register. Find the Sales people: Test MktExec, Test BDM, Test KAM, Test MktMgr, Test BranchMgr, Test BranchMgr2, Test Finance, Test Director.', 'Each is listed with the role and home office from the Start here table (KAM and BranchMgr2 in Mumbai, the rest in Ahmedabad).'),
   ('Admin → User register: look at the Role column of every Test user.', 'No orange "own access" mark next to any of them. (A mark means that person\'s ticks differ from their role; open them → Permissions → press "Use the … role\'s access" → Save user.)'),
   ('Open Test BDM → Permissions tab. Change nothing.', 'A green line: "✓ Exactly what the Business Development Manager role gives. The person follows the role…". "Create / edit quotations", "Send quotation to the customer" and "Manage quote follow-ups & reminders" are ticked; Invoicing and the Operations rows are not.'),
   ('Open Test MktExec → Permissions tab. Change nothing.', 'A green line naming the Marketing Executive role. "Create / edit quotations" and "Manage quote follow-ups & reminders" ticked; "Send quotation to the customer", "See the inter-office credit figures", Invoicing, Vendors and every Operations row NOT ticked.'),
   ('Add a person (do not save): pick Role "Marketing Executive", then change it to "Finance", then back.', 'Each time the ticks change to that role\'s and the green line names the new role. Press Cancel.'),
   ('Add a person (do not save): pick Marketing Executive, then tick one extra box, e.g. "Operations dashboard".', 'The line turns orange: "⚠ Their own access: 1 more than the Marketing Executive role gives…" with a button "Use the Marketing Executive role\'s access". Press it: the extra tick goes and the line is green again. Press Cancel.'),
   ('Sign in as Test MktExec (private window). Look at the left menu.', 'Sales and Directory are there. Insights, Money and Operations are NOT.'),
   ('Sign in as Test BDM. Left menu.', 'Sales, Insights and Directory. No Operations, no Money.'),
   ('Sign in as Test MktMgr. Left menu, then Sales home.', 'Sales, Money (profitability), Insights, Directory, Admin (document templates). Sales home shows the "Set up Sales" tile.'),
   ('Sign in as Test BranchMgr. Sales → Quotes.', 'Only Ahmedabad quotations. Nothing from Mumbai.'),
   ('Sign in as Test KAM. Sales → Quotes and Directory → Client register.', 'Only Mumbai records.'),
   ('Sign in as Test Finance. Left menu.', 'Sales (quotations read only), Operations (read only), Reporting, Money, Insights. No Admin.'),
   ('Sign in as Test Director. Left menu.', 'Every area; System settings are not offered.')],
  'docs/01-roles.md; docs/02-permission-matrix.md; setup_guide.py')

C('SAL-001', 'Before you start', 'Test customers that cover every kind of buyer', 'scenario', ['best'],
  'MASTER_ADMIN', 'Different buyers trigger different rules (blocked, on hold, group companies, near-duplicate names).',
  'Signed in as Master Admin.',
  [('Left menu → Directory → Client register → add "TB Refinery Ltd" (state Gujarat), "TB Steel Works Pvt Ltd" (Maharashtra), "TB Pharma LLP" (Telangana).', 'Three customers listed as Active.'),
   ('Add "TB Valves Pvt Ltd" — you will later type "TB VALVES PRIVATE LIMITED" to test the duplicate question.', 'Listed as Active.'),
   ('Add "TB Defaulter Co" and, under Left menu → Directory → Client holds, set Status = Blocked with reason "Unpaid for 180 days".', 'Client holds lists TB Defaulter Co as Blocked with your reason.'),
   ('Add "TB Paused Co" and set it On hold with reason "Credit review".', 'Listed as On hold.'),
   ('Add "TB Group Parent" and a related company "TB Group Child" (group relationship on the client record).', 'The child shows the parent as its group.')],
  'D-12; SA-18; quote group contracts')

C('SAL-002', 'Before you start', 'Answer "Set up Sales" — record your company\'s answers before testing', 'scenario', ['best', 'rule'],
  'MARKETING_MANAGER', 'The answers decide how approval, self-approval, escalation, costing and the review checklist behave in every later test.',
  'Signed in as Test MktMgr.',
  [('Left menu → Sales → "Set up Sales" (tile on the Sales home).', 'Page headed "Sales — set up in a few questions" with sections: Approving a quote (it starts with "Who approves, and up to what value" — the levels, editable right here); Approving a deal as it moves stage; Other approvals in Sales; Reviewing the request before it is approved (contract review); Already set by your company\'s rules.'),
   ('Read "Other approvals in Sales".', 'One line each for Leads (no approval), Deals, Project costing, Quotes and Opening a contract — each saying who approves.'),
   ('Read "Already set by your company\'s rules".', 'Your approval levels are written out in sentences, including any value no level covers.'),
   ('Question "When a quote\'s value is not covered by any of your approval levels…" — choose "It goes to the approvers of your highest level". Question "May the person who prepared a quote approve it themselves…" — choose "Yes — when it is within their own authority". Question "When a quote needs more than one level, how do the levels approve?" — choose "One after another — level 1 first". Question "If a quote waits too long at one approval level…" — choose "After 1 day". Question "Must a man-month quote come from an approved project costing…" — choose "Yes — an approved costing first". Checklist: "Yes — show the checklist on every quote" and "Yes — every point must be ticked (or waived)". Press "Save these answers".', 'A message confirms the answers were saved. Reopen the page: every answer is still selected.'),
   ('Left menu → Directory → Activity (or the activity log).', 'One entry "Sales answered: …" per changed answer, in the words of the question.'),
   ('Sign in as Test MktExec and open the same page.', 'Refused — only someone who may change the quotation approval rules may answer.')],
  'lib/setupqa.php; D-03, D-06, D-11a, D-11A, D-10', live=False)

C('SAL-003', 'Before you start', 'Set approval levels that match a real company — on Set up Sales itself', 'scenario', ['best', 'rule'],
  'MARKETING_MANAGER', 'Approval routing is the heart of the Sales controls; every approval test uses these three levels.',
  'Signed in as Test MktMgr (or Master Admin). Start here → SET-07 describes the same three levels.',
  [('Sales home → "Set up Sales" → section "Approving a quote" → "Who approves, and up to what value".', 'One card per level (Level · From value · Up to value (0 = no limit) · Approved by (role) · …or one named person · For · In use · Save · Remove), and a dashed card at the end with "Add this level".'),
   ('In the dashed card: Level 1, From 0, Up to 500000, Approved by "Branch Manager", For "Every business unit". Press "Add this level".', 'You stay on Set up Sales. A card "Level 1" appears, and the summary above reads "Level 1 — from ₹0 to ₹5,00,000 before tax: Branch Manager."'),
   ('Look at the new dashed card.', 'It is already filled in: Level 2, From 500000 — where level 1 stops.'),
   ('Level 2: Up to 2500000, Approved by "Marketing Manager". Add this level.', 'Card "Level 2"; summary line for ₹5,00,000 to ₹25,00,000.'),
   ('Level 3: From 2500000, Up to 0, Approved by "Business Director". Add this level.', 'Card "Level 3"; summary says "from ₹25,00,000 upward". The line "Nothing above … is covered" disappears.'),
   ('Edge: in the dashed card type From 900000 and Up to 800000. Add this level.', 'Refused: "The "up to" value must be above the "from" value (or 0 for no upper limit)." No card is added.'),
   ('Change Level 1\'s "Approved by" to "Business Unit Head" and press that card\'s Save. Then change it back to Branch Manager and Save.', 'Each save stays on Set up Sales; the summary follows the change.'),
   ('Sales → Quotes → "Approval rules" (the full screen).', 'The same three levels, named "Level 1 — Branch Manager", "Level 2 — Marketing Manager", "Level 3 — Business Director".')],
  'D-02, D-04, D-05; owner SAL-002 (levels edited on Set up Sales); quote_approval_rules', live=False)

C('SAL-005', 'Before you start', 'A level for one business unit applies to that unit only; deal rules say what they cover', 'edge', ['edge', 'rule'],
  'MARKETING_MANAGER', 'Until 20 Oct 2026 a level marked for one business unit was saved as "every business unit" and stopped every quotation. And the deal-stage rule screen offered one choice, "Opportunities", which looked as if leads and quotations were missing.',
  'SAL-003 done. Signed in as Test MktMgr. At least two business units exist (SET-05).',
  [('Set up Sales → dashed card: Level 4, From 0, Up to 0, Approved by "Business Unit Head", For "Industrial only". Add this level.', 'The summary reads "Level 4 — from ₹0 upward before tax (Industrial only): Business Unit Head." (the unit by its name).'),
   ('As Test BDM, make a quotation for ₹1,00,000 in business unit "Oil, Gas & Chemicals" and submit it for approval.', 'It waits for Level 1 only (Branch Manager). Level 4 does not appear on it.'),
   ('Make a second ₹1,00,000 quotation in business unit "Industrial" and submit it.', 'It waits for Level 1 and then Level 4 (Business Unit Head).'),
   ('Back on Set up Sales, press Remove on Level 4 and confirm.', 'The card and its summary line are gone.'),
   ('Sales → Approvals → "Approval rules" (page "Approval rules for deal stages") → "＋ New rule".', '"Applies to" is a fixed box reading "Deals (opportunities)" — not a drop-down — with the note: quotations are approved by value levels on Set up Sales; leads need no approval. Press Cancel.')],
  'owner SAL-002; crm_rule_is_unit(); stage_gates', live=False)

C('SAL-006', 'Before you start', 'Approvals follow your organisation: the branch\'s manager, then the unit\'s SBU Head', 'access', ['rule', 'best'],
  'several', 'In a company with several branches and business units, a quotation must wait for the Branch Manager over ITS branch and the SBU Head of ITS business unit — not every manager in the company.',
  'SAL-003 levels, but Level 2 set to "Approved by: Business Unit Head". Test SBUHead\'s Data scope: only "Industrial". Test BranchMgr (Ahmedabad) and Test BranchMgr2 (Mumbai) exist.',
  [('Set up Sales → read the level summary.', 'No warning, or a "⚠ No Branch Manager covers …" line naming each branch nobody manages yet. Fix those on the person\'s Data scope.'),
   ('As Test BDM (Ahmedabad), make a ₹6,00,000 quotation, business unit Industrial, and submit it.', 'It waits for Level 1. The quotation names Test BranchMgr only — not Test BranchMgr2.'),
   ('Sign in as Test BranchMgr2 → Approvals.', 'The quotation is NOT listed. Opening its address directly offers no Approve.'),
   ('Sign in as Test BranchMgr → Approvals.', 'Section "Quotations" lists it. Open it and approve Level 1.'),
   ('Now it waits for Level 2. Sign in as Test SBUHead → Approvals.', 'Listed (Industrial is their unit). Approve.'),
   ('Repeat with a quotation in "Oil, Gas & Chemicals" (a unit nobody covers).', 'Level 2 falls back to every Business Unit Head, and Set up Sales showed a "⚠ No Business Unit Head covers the business unit Oil, Gas & Chemicals" line.')],
  'crm_role_approvers_for(); docs/02 19h', live=False)

C('SAL-007', 'Before you start', 'Approval rules of 21 Oct: no levels = no approval; costing-based quotes approved once; levels at the same time', 'scenario', ['rule', 'edge'],
  'several', 'Owner, 21 Oct 2026: "skip the second approval"; "if there is no approval chain then top management is not required to review or approve quotation"; "setting in sales setup wherein simultaneous approval are required".',
  'A TEST workspace. SAL-003 levels exist. Test BDM, Test BranchMgr, Test MktMgr.',
  [('Set up Sales → note your levels, then Remove every level. As Test BDM make a ₹1,00,000 quotation and press Submit for approval.', '"No approval is needed for this quote — your company has no approval levels. It is ready to send." Status Approved. Put your levels back afterwards (SAL-003).'),
   ('With the levels back: make a project costing from an enquiry (SAL-050), get it approved (SAL-051), "Make a quotation" from it, change nothing, Submit for approval.', '"No approval is needed … it is at the prices of its approved project costing, which the approval levels already signed." Status Approved — no second round.'),
   ('Make another quotation from the same costing; as its creator change one rate, with the note the screen asks for; Submit.', 'It goes to the approval levels like any quotation (the price is no longer the costing\'s).'),
   ('Set up Sales → "When a quote needs more than one level, how do the levels approve?" → "All at the same time — every level is asked at once" → Save these answers.', 'Saved. The escalation question now notes it is not used in this mode.'),
   ('As Test BDM submit a ₹6,00,000 quotation (two levels). Sign in as Test MktMgr (level 2) → Approvals.', 'It is listed straight away — level 2 does not wait for level 1. The quotation reads "Now with level 1: …; level 2: … (all at the same time)".'),
   ('Test MktMgr approves first, then Test BranchMgr.', 'After the second signature: "All approvals complete — quote approved and ready to send."'),
   ('Switch the answer back to "One after another" and Save.', 'Level 2 is again asked only after level 1 has signed.')],
  'owner 21 Oct 2026; crm_quote_needs_approval(); quote_approval_mode', live=False)

C('SAL-004', 'Before you start', 'Company types to test — run the Sales tests once for each', 'scenario', ['best'],
  'MASTER_ADMIN', 'The same software behaves differently for a services company, a manpower supplier and a product company; each must be tried.',
  'A TEST workspace (not your live company), because applying an industry changes the wording everyone sees.',
  [('Profile A — Third-party inspection agency (services by man-day). Left menu → Admin → Settings → Your industry → "Inspection, testing & certification" → Make it the company\'s industry (tick "I understand the changes listed above.").', 'Wording and ISO/IEC 17020 rules match an inspection body.'),
   ('Profile B — Testing laboratory: "Testing & calibration laboratory".', 'The ISO/IEC 17025 laboratory rules are switched on, inspection rules off; the panel listed both changes before you confirmed.'),
   ('Profile C — Manpower supply / deputation: "Recruitment & staffing". Quote by Man-month.', 'Man-month quotations need an approved project costing (if your Set up Sales answer says so).'),
   ('Profile D — Manufacturer selling products: "Manufacturing & engineering". Try to quote 50 cabinets.', 'Quote line units offered are Man-day, Man-month, Per visit, Audit day, Per lot / lump sum, Per document, Per km, Other — there is no "Nos / pieces / kg" and no HSN code. Record as a GAP for product companies unless your administrator adds a unit in Masters.'),
   ('Profile E — Trader (products + services): "Trading & distribution".', 'Same as D: product lines must use "Other" or a unit added in Masters; record what you see.'),
   ('Profile F — Construction / EPC: "Construction, projects & infrastructure". Quote lump-sum stages.', 'Per lot / lump sum works; milestone billing is a Money test.'),
   ('Profile G — One-person consultancy: no approval levels at all; Set up Sales "No approval is needed".', 'Quotes can be sent straight from Draft.'),
   ('After each profile, go back to Profile A on the test workspace.', 'Wording returns to inspection words.')],
  'D-29; CHARGE_UNITS; industry templates', live=False)

# ════════════════════════════════════════════════════════════════════════════
# S1 — Leads
# ════════════════════════════════════════════════════════════════════════════
C('SAL-010', 'Leads', 'A sales executive records a new lead — best case', 'scenario', ['best'],
  'MARKETING_EXECUTIVE', 'A lead is the first record of possible business; if it cannot be recorded quickly it never enters the funnel.',
  'Signed in as Test MktExec.',
  [('Left menu → Sales → Leads.', 'Page headed "Leads" with Year filter, Find, "+ New lead", "⬇ CSV", "Board" and "List".'),
   ('Press "+ New lead".', 'Page "New lead" with sections: Who they are · What they want · Manpower deputation — if this lead is for deputing · Who chases it, and what happens next.'),
   ('"…or type a new company / person\'s name": "TB Cement Ltd". Contact name "Ravi Shah", E-mail ravi@tbcement.test, Telephone 9825012345, Where they came from = "Exhibition or event", The requirement "Third-party inspection of kiln shell, 12 days", Value they are worth ₹4,50,000, Expected to close = next month, Allocated to = admin — me, Branch = "— mine —". Press "Create it".', 'The lead opens with tabs Overview · Contacts · Quotes · Documents · Actions · History, at the first stage of the lead pipeline.'),
   ('Open the History tab.', 'One move recorded, by you, today.'),
   ('Left menu → Sales → Leads → List.', 'TB Cement Ltd appears with your name and branch.')],
  'D-01; lib/leads.php lead_create')

C('SAL-011', 'Leads', 'Lead form — every field, empty and extreme values', 'edge', ['edge'],
  'MARKETING_EXECUTIVE', 'Bad input must be refused with a clear sentence and nothing you typed lost.',
  'Signed in as Test MktExec. Left menu → Sales → Leads → + New lead.',
  [('Press "Create it" with everything empty.', 'Refused with a sentence naming what is missing (at least the company). No lead is created.'),
   ('Company name of 250 characters.', 'Saved (cut to the allowed length) or refused with a clear message — never a blank or error page.'),
   ('Company name with special characters: "Shri Ram & Sons (P) Ltd. — युनिट २".', 'Saved and shown exactly as typed everywhere (list, detail, quote).'),
   ('Company name "<b>Bold</b> <script>alert(1)</script>".', 'Shown as plain text; no bold, no pop-up.'),
   ('E-mail "ravi@" (invalid).', 'The browser or the page refuses it with a message.'),
   ('Value they are worth = -5000.', 'Refused, or saved as 0 with a message — record what happens.'),
   ('Value = 99999999999 (99,999 crore).', 'Saved and shown with Indian grouping, not in scientific form.'),
   ('Expected to close = a date in 2019.', 'Accepted, but the lead shows as overdue on the board.'),
   ('Type of deputation = "Man-month (site deputation)", How many people = 0.', 'Refused or warned; never a lead for zero people without a message.'),
   ('Double-click "Create it" quickly.', 'Only ONE lead is created.')],
  'docs/audit-2026-10/04-SCREEN-AND-FIELD-AUDIT.md §2–3')

C('SAL-012', 'Leads', 'Moving a lead through its stages', 'scenario', ['best'],
  'MARKETING_EXECUTIVE', 'Stage moves feed the funnel numbers and the stalled-lead warnings.',
  'Lead "TB Cement Ltd" from SAL-010, at its first stage.',
  [('Open the lead → Actions tab → "Move it to the next step".', 'Buttons for each stage, e.g. "Move to Qualified →", New, Qualified, Proposal sent, Negotiation, Won →, and a Lost box.'),
   ('Press "Move to Qualified →".', 'Message "Moved." The Overview shows Qualified. History has a second row with days spent in the previous stage.'),
   ('Press the button of the stage it is already in.', 'Refused: "This lead is already at …". The days-in-stage clock does NOT reset.'),
   ('Move to Proposal sent, then Negotiation.', 'Each move appears in History with who and when.')],
  'lead_move; lead_stage_history')

C('SAL-013', 'Leads', 'Only the responsible person marks a lead lost — with a reason AND remarks', 'scenario', ['worst', 'rule'],
  'MARKETING_EXECUTIVE', 'Owner, 20 Oct 2026: "if lead is lost the responsible person must mark as lost with a valid reason and remarks." A loss without both teaches the company nothing.',
  'A lead at Negotiation, "Handled by" Test MktExec.',
  [('Sign in as Test BDM (not responsible for it) and open the lead → Actions tab.', 'Under "It did not happen": "Only the person responsible for this lead — Test MktExec — marks it lost." No Lost button.'),
   ('Sign in as Test MktExec → Actions tab → "It did not happen". Leave "Why it was lost" on "choose a reason…", type remarks → press "Lost".', 'The browser asks you to choose a reason; nothing changes.'),
   ('Choose "Price too high / lost on price", leave "Remarks — what happened" empty → "Lost".', 'The browser asks for the remarks; nothing changes.'),
   ('Remarks "Competitor 20% cheaper" → "Lost".', 'The lead shows "Closed — this one did not happen." with your reason and remarks.'),
   ('Lead register → List → tick two open leads of Test MktExec → "Mark lost" → choose a reason, leave "Remarks — required" empty → apply.', 'Refused: "Add your remarks — what happened, in a sentence. Both the reason and the remarks are required." Nothing is marked lost.'),
   ('Same, with remarks "No budget this year".', 'Both are marked lost. A lead belonging to someone else in the same tick-list is left alone "(…not yours to mark lost)".'),
   ('Left menu → Insights → Sales dashboard → "Why we lose".', 'The reasons are counted.')],
  'owner 20 Oct 2026; lead_lost_problem(); lead_can_mark_lost()')

C('SAL-014', 'Leads', 'Reopening a lost lead — only a manager, only with a reason', 'scenario', ['rule', 'worst'],
  'several', 'Customers come back; a reopened lead must keep its history and say who reopened it and why (owner\'s rule D-22).',
  'The lost lead from SAL-013.',
  [('Sign in as Test MktExec, open the lost lead.', 'No "Reopen this lead" option is offered.'),
   ('Sign in as Test MktMgr, open it.', 'Under the lost banner: "Reopen this lead".'),
   ('Open it and press Reopen with the reason empty.', 'The browser asks for a reason; nothing changes.'),
   ('Reason "They called back — budget approved" → Reopen.', 'Message "Reopened — back at "Negotiation"" (the stage it was lost from). Lost reason no longer shown.'),
   ('History tab / activity.', 'An entry "Lead … reopened by Test MktMgr — They called back — budget approved", noting why it had been lost.')],
  'D-22; lead_reopen', 'ANY')

C('SAL-015', 'Leads', 'A lost lead cannot be converted into a customer', 'edge', ['rule', 'worst'],
  'MARKETING_MANAGER', 'Before 20 Oct a lost lead could be turned straight into a customer, bypassing the decision that it was lost.',
  'A lead marked lost (not reopened).',
  [('Open it and change the address bar from /lead?id=… to /lead-convert?id=… (same number), press Enter, then press "Convert it".', 'Refused: "This lead is marked lost. A manager reopens it first, with a reason — then it can be converted."'),
   ('Reopen it (SAL-014), then convert.', 'Conversion now works.')],
  'D-22')

C('SAL-016', 'Leads', 'Converting a won lead into a customer and an enquiry', 'scenario', ['best'],
  'MARKETING_EXECUTIVE', 'Conversion carries the company, contact and requirement across so nothing is retyped.',
  'An open lead at Negotiation, for a company NOT yet on the customer list.',
  [('Actions tab → "Won →".', 'You are taken to "Convert …" with sections "The customer" and "What comes across".'),
   ('Leave "Attach to a customer we already have" on "— create a new one", check the name, enter State "Gujarat" → "Convert it".', 'Message "Converted. The customer is on the master and an inquiry has been raised — carry on from there."'),
   ('Left menu → Sales → Inquiries.', 'A new enquiry for this company, Status Open, assigned to the lead\'s owner (not blank), showing your branch.'),
   ('Left menu → Directory → Client register.', 'The new customer is listed once.'),
   ('Open the lead again.', 'Banner "Won — this became a customer." with a link to the customer and the enquiry.')],
  'lead_convert; enquiry assignee fix 20 Oct')

C('SAL-017', 'Leads', 'Three people press Convert on the same lead at the same moment', 'edge', ['worst', 'edge'],
  'several', 'Two clicks at once used to create two customers and two enquiries (SA-18).',
  'An open lead. Three browsers (or three people) on its Convert screen.',
  [('All three press "Convert it" within the same second.', 'Exactly ONE customer and ONE enquiry are created. The other two see "That lead is being converted, or has just been converted, by someone else." (or "That lead has already been converted.")'),
   ('Client register: search the name.', 'Listed once.')],
  'SA-18; lead claim')

C('SAL-018', 'Leads', 'Converting a lead for a company that is already a customer', 'edge', ['edge'],
  'MARKETING_EXECUTIVE', 'Choosing the existing customer keeps its history in one place.',
  'Lead for "TB Steel Works Pvt Ltd" typed as free text.',
  [('Convert screen.', 'The screen points out a possible duplicate customer.'),
   ('Pick "TB Steel Works Pvt Ltd" in "Attach to a customer we already have" → Convert it.', 'No new customer is created; the enquiry is raised for the existing one.')],
  'lead_possible_duplicate')

C('SAL-019', 'Leads', 'Lead documents: attach, list, remove', 'scenario', ['best', 'edge'],
  'MARKETING_EXECUTIVE', 'RFQs and drawings travel from the lead into the quotation.',
  'An open lead.',
  [('Documents tab → File(s): pick an RFQ PDF, What is it? "Requirement / RFQ", Note "Rev B" → "Attach".', 'The file is listed with kind, note and date.'),
   ('Attach several files at once.', 'All are listed.'),
   ('Attach a 10 MB file (the limit is 8 MB per file).', 'Refused with a message naming the 8 MB limit; nothing half-saved.'),
   ('Attach a .exe file.', 'It should be refused as a file type not allowed — record what happens.'),
   ('Make a quotation from this lead ("+ Make a quotation") and save it.', 'The quotation\'s History says "N file(s) carried over from the lead" and the files are on the quotation.'),
   ('Back on the lead, press ✕ on a file.', 'The file is removed after confirmation.')],
  'lead_files_carry_to_quote')

C('SAL-020', 'Leads', 'Log a contact and the next step', 'scenario', ['best'],
  'MARKETING_EXECUTIVE', 'Follow-up discipline is what wins business; the next step appears on the dashboard.',
  'An open lead.',
  [('Contacts tab → "Log a contact": How = Telephone call, Which way = We contacted them, When = today, Who you spoke to "Ravi", What was said "Wants revised rate", How it went "Positive", Time it took = 15 minutes, Next thing to do "Send revised rate", By when = tomorrow → "Save this contact".', 'The contact appears in history; the lead shows Next: Send revised rate by tomorrow.'),
   ('Set By when to yesterday.', 'The lead shows as overdue on the Leads board and in "Your next actions" on the Sales home.')],
  'lead-contact')

C('SAL-021', 'Leads', 'Leads board and list — filters, year, CSV', 'scenario', ['edge'],
  'MARKETING_MANAGER', 'Managers read the pipeline from this list; filters must not hide or double-count.',
  'At least 10 leads across two financial years and two branches.',
  [('Left menu → Sales → Leads → Board.', 'One column per stage; each lead appears once.'),
   ('Year = previous FY → Find.', 'Only leads created in that FY.'),
   ('Year = All years.', 'Every lead.'),
   ('Press "⬇ CSV".', 'A file downloads with the same rows as on screen.'),
   ('Sign in as Test KAM (Mumbai branch).', 'Ahmedabad leads are NOT shown; leads with no branch are shown to everyone.')],
  'scope_office_clause leads')

C('SAL-022', 'Leads', 'Who may open, add and edit leads', 'access', ['rule'],
  'several', 'Owner\'s rule D-01: sales executives, sales managers and anyone assigned work leads.',
  'Test users from SAL-000.',
  [('As Test MktExec, Test BDM, Test KAM, Test MktMgr: Left menu → Sales → Leads → + New lead.', 'Each can open Leads and add a lead.'),
   ('As Test Inspector: open Sales.', 'Leads is not offered; typing /leads is refused.'),
   ('As Test Finance: open /leads.', 'Refused (Finance does not work leads).'),
   ('As Test MktExec: try to delete a lead (Actions → Remove this lead).', 'Not offered or refused — Delete is not granted to sales roles.'),
   ('As Test MktExec: Left menu → Sales → Pipelines & funnels → Edit stages.', 'Refused — configuring pipelines stays company setup.')],
  'D-01; matrix fn 19b')

C('SAL-023', 'Leads', 'Ads Pro: imported leads never duplicate a lead already here', 'edge', ['edge', 'worst'],
  'MASTER_ADMIN', 'A contact who filled a web form and was also typed in by hand must stay one lead.',
  'Ads Pro connected (Left menu → Admin → Ads Pro connection). A lead typed by hand with e-mail meera@pumps.test.',
  [('In Ads Pro, create a lead with e-mail "MEERA@Pumps.TEST " (capitals and a space). Here: Ads Pro connection → "Bring leads across".', 'No new lead; the existing lead is linked.'),
   ('Ads Pro lead with a new e-mail but the same company as an open lead.', 'Linked to the open lead.'),
   ('A genuinely new person and company.', 'A new lead is created.'),
   ('"Test the connection" with a wrong API token.', 'A clear failure message; nothing imported.')],
  'ads_existing_lead', live=False)

# ════════════════════════════════════════════════════════════════════════════
# S2 — Opportunities (deals)
# ════════════════════════════════════════════════════════════════════════════
C('SAL-024', 'Leads', 'Reassigning a lead waits for a manager\'s approval', 'scenario', ['rule', 'worst', 'best'],
  'several', 'Owner, 20 Oct 2026: "Reassigning shall be done only after manager approval." A lead taken from someone without a manager agreeing is a lead the team fights over.',
  'Start here → SET-06 done, with Test MktExec and Test BDM reporting to Test BranchMgr. A lead "Handled by" Test MktExec, Ahmedabad, still open.',
  [('As Test MktExec, open the lead → Details → change "Handled by" to Test BDM.', 'A box appears: "Why it is being reassigned — a manager approves it before it changes hands".'),
   ('Leave the reason empty → Save.', 'The browser asks for the reason.'),
   ('Reason "BDM already handles this customer" → Save.', '"Saved. The reassignment has been sent to a manager for approval; it stays with its owner until then." The lead still shows Test MktExec, and a panel "Reassignment waiting for a manager" names who asked, to whom, the reason and the managers it waits for. No Approve button for you.'),
   ('Change "Handled by" again to Test KAM with a reason → Save.', 'The owner is NOT changed: "A reassignment of this lead is already waiting for a manager…"'),
   ('Sign in as Test BranchMgr → left menu → Approvals.', 'A section "Lead reassignments" with the lead, "From Test MktExec to Test BDM", who asked and the reason, a Note box, Approve and Reject.'),
   ('Press Reject with the Note empty.', 'Refused: "Say why it is rejected — the person who asked reads this."'),
   ('Type "Agreed — BDM owns the account" → Approve.', '"Approved — the lead now belongs to its new owner." Signed in as Test MktExec, the lead now shows Test BDM, and its activity says who approved it and why.'),
   ('As Test MktMgr (a sales manager over Ahmedabad), change "Handled by" on another Test MktExec lead with a reason → Save.', 'It changes at once — no request — and the reason is in its history.'),
   ('As Test MktExec, give a lead that has NO owner to Test BDM (register → tick it → "Allocate to…").', 'Allocated at once: a lead nobody owns needs no approval.'),
   ('Edge: as Test MktExec, ask to reassign a lead, then (as its owner) mark it lost before the manager decides. As Test BranchMgr press Approve.', 'Refused: "The lead has changed since this was asked (closed, or given to someone else), so the request is closed." It disappears from Approvals.')],
  'owner 20 Oct 2026; lead_change_owner(); lead_reassign_decide(); docs/03 lead reassignment request')

C('SAL-030', 'Opportunities', 'Open a deal and move it to Won', 'scenario', ['best'],
  'BUSINESS_DEV_MANAGER', 'Deals carry the value and probability that make the forecast.',
  'Signed in as Test BDM. Customer TB Refinery Ltd exists.',
  [('Left menu → Sales → Opportunities → "+ New opportunity".', 'Form "New opportunity".'),
   ('What is the opportunity? "Refinery shutdown TPI 2026", Customer = TB Refinery Ltd, Pipeline = default, Estimated value ₹12,00,000, Expected close next month, Branch Ahmedabad, Where it came from = An existing customer, Competing against "Bureau X", Contact "Mr Patel", Next action "Site visit", By when tomorrow → "Open the opportunity".', 'The deal opens with tabs Overview · Quotes · Actions · History at the first stage.'),
   ('Actions tab → "Move it to" = Quotation sent (50%) → "Move it".', 'Message "Moved." Probability shows 50%.'),
   ('Move it to Won (100%).', 'Message "Won. Raise the order when the customer confirms." Banner "Won — TB Refinery Ltd said yes."'),
   ('Left menu → Insights → Sales dashboard.', 'Won value includes ₹12,00,000; "Who is selling" credits Test BDM.')],
  'opp_move')

C('SAL-031', 'Opportunities', 'Marking a deal lost: reason, competitor, what happened', 'scenario', ['worst', 'rule'],
  'BUSINESS_DEV_MANAGER', 'Loss analysis by reason and competitor.',
  'An open deal.',
  [('Move it to = Lost, leave "Why it was lost" empty → Move it.', 'Refused: "A deal marked lost needs a reason — it is the only thing that makes the loss useful later."'),
   ('Reason "Awarded to a competitor", Lost to "Bureau X", What actually happened "L1 by 8%" → Move it.', 'Message "Recorded as lost, with the reason." Banner shows reason, competitor and note.'),
   ('Left menu → Sales → Opportunities → "Why deals were lost — last 12 months".', 'The reason and value are counted.')],
  'opp_move LOST')

C('SAL-032', 'Opportunities', 'Moving a deal to the stage it is already in', 'edge', ['edge'],
  'BUSINESS_DEV_MANAGER', 'A no-op move used to reset the days-in-stage clock and the stalled-deal figures.',
  'An open deal at Qualified for 5 days.',
  [('Move it to = Qualified (the current stage) → Move it.', 'Refused: "This deal is already at "Qualified". Pick a different stage to move it to." Days in stage still 5.')],
  'opp_move same stage')

C('SAL-033', 'Opportunities', 'A stage rule holds a big deal until a manager agrees', 'scenario', ['rule'],
  'several', 'Stage gates stop a large deal being called won or lost without a manager.',
  'Left menu → Sales → Pipelines & funnels → stage rules ("Approval rules for deal stages"): a rule "Won above ₹10 lakh needs Marketing Manager".',
  [('As Test BDM, move a ₹12,00,000 deal to Won.', 'Message "Not moved yet — "<rule>" applies to a deal this size, so the move to Won is waiting on approval." The deal is NOT won.'),
   ('Try to move it again.', 'Refused: "This deal is already waiting on an approval to move. Withdraw that request first."'),
   ('As Test MktMgr: Left menu → Sales → Approvals → Waiting.', 'The request is listed: Deal, Move, Value, Raised by, Waiting on.'),
   ('Approve it.', 'The deal is Won; "Who is selling" credits Test BDM (who asked), not the manager.'),
   ('Repeat with a ₹2,00,000 deal.', 'No approval needed; it moves at once.')],
  'gate_match; opp_move bypassGate')

C('SAL-034', 'Opportunities', 'Reopening a closed deal — manager, reason, and not once it is an order', 'scenario', ['rule', 'worst'],
  'several', 'Owner\'s rule D-22.',
  'One lost deal; one won deal with no order raised; one won deal already raised as an order or sent to Accounts.',
  [('As Test BDM, open the lost deal.', 'No "Reopen this deal".'),
   ('As Test MktMgr, open the lost deal → "Reopen this deal", reason "Revised scope requested" → Reopen.', '"Reopened — back at …" the stage it was lost from; lost reason cleared; history notes who and why.'),
   ('As Test MktMgr, open the won deal with no order → Reopen with a reason.', 'Reopened; "Won by" is cleared.'),
   ('Open the won deal that already became an order.', 'No Reopen offered. Forcing it gives: "This deal has already become an order or a contract, so it is not reopened — change the order or contract instead."')],
  'D-22; opp_reopen')

C('SAL-035', 'Opportunities', 'From a deal: costing, quotation, attach and detach', 'scenario', ['best'],
  'BUSINESS_DEV_MANAGER', 'The deal is where the price is built and the quotation raised.',
  'An open deal for TB Refinery Ltd.',
  [('Quotes tab → "＋ Create costing for this deal".', 'A project costing opens, linked to the deal.'),
   ('Back on the deal: "attach existing…" → pick another costing → Attach.', 'Message "Costing linked."'),
   ('"＋ Create a new quotation".', 'Message "Quotation Q-… drafted from this deal — add the line items and submit it for approval." It opens as Draft for TB Refinery Ltd.'),
   ('Deal Quotes tab.', 'The quotation is listed with status and value. "Detach" removes the link.'),
   ('Mark the deal Lost, then try "＋ Create a new quotation".', 'Refused: "A lost deal does not get a quotation."')],
  'opp_create_quote')

C('SAL-036', 'Opportunities', 'Deals: branch scope and who can see them', 'access', ['rule'],
  'several', 'A branch sees its own deals; unassigned deals are visible to all so nothing gets lost.',
  'Deals in Ahmedabad, Mumbai and one with no branch.',
  [('As Test KAM (Mumbai): Left menu → Sales → Opportunities → List.', 'Mumbai deals and the no-branch deal; not Ahmedabad.'),
   ('Type the address of an Ahmedabad deal (/opportunity?id=…).', 'Refused: "This opportunity is outside your office / branch scope."'),
   ('As Test Director.', 'Every deal.')],
  'opp_scope_gate')

# ════════════════════════════════════════════════════════════════════════════
# S3 — Enquiries (inquiries)
# ════════════════════════════════════════════════════════════════════════════
C('SAL-040', 'Inquiries', 'Record an enquiry — best case', 'scenario', ['best'],
  'MARKETING_EXECUTIVE', 'An enquiry is a specific request to quote; quotations are raised from it.',
  'Signed in as Test MktExec (home branch Ahmedabad).',
  [('Left menu → Sales → Inquiries → "+ New inquiry".', 'Page "New client inquiry".'),
   ('client = TB Refinery Ltd, Received date today, Contact person "Mr Patel", Contact email patel@tbrefinery.test, Business Unit = Oil, Gas & Chemicals, Source = Email, Status Open, Assigned to = yourself, Subject "Shutdown TPI — columns C-101/102", Service requirement "Stage + final inspection, 20 man-days", Notes "Tender closes 30th" → "Create inquiry".', 'Message "INQ-… created. You can now raise a quotation against it."'),
   ('Inquiry register.', 'The row shows Inquiry no., Received, Client, Subject, Business Unit, Owner, Source, Status = Open, and under the number the branch "Ahmedabad".'),
   ('Press "Quote" on the row.', 'A new quotation opens pre-filled with the client and subject.')],
  'crm_inquiries; office_id 20 Oct')

C('SAL-041', 'Inquiries', 'Enquiry form refuses an incomplete enquiry and keeps your typing', 'edge', ['edge'],
  'MARKETING_EXECUTIVE', 'An enquiry with no client, contact or subject is a blank row somebody has to chase.',
  '"+ New inquiry".',
  [('Press "Create inquiry" with nothing filled.', 'The form returns with "Please add a client (pick one, or type a name); the contact person; a contact e-mail or mobile; the Business Unit (business unit); a subject." No enquiry created.'),
   ('Fill everything except e-mail and mobile.', 'Refused: needs "a contact e-mail or mobile". Everything else you typed is still there.'),
   ('Give a mobile but no e-mail.', 'Accepted — e-mail OR mobile is enough.'),
   ('Received date in the future (next month).', 'Record what happens — ideally a warning; the enquiry must not count as overdue.'),
   ('Received date 90 days ago with Status Open.', 'The register shows a red "⏳ 90d" overdue pill.')],
  'inquiry-new validation; Module 19 SLA')

C('SAL-042', 'Inquiries', 'Typing a near-duplicate customer name is pointed out', 'edge', ['edge'],
  'MARKETING_EXECUTIVE', 'Two spellings of one company split its history (SA-18).',
  'Customer "TB Valves Pvt Ltd" exists.',
  [('New inquiry with "…or client name" = "TB VALVES PVT. LTD." and everything else valid → Create inquiry.', 'Saved, with a warning: "A client with a similar name is already on file: TB Valves Pvt Ltd (C-…). If it is the same company, pick it on this inquiry so its history stays in one place."'),
   ('Edit the enquiry and pick TB Valves Pvt Ltd.', 'Saved against the existing customer.')],
  'SA-18')

C('SAL-043', 'Inquiries', 'A blocked or on-hold customer gets no new enquiry', 'scenario', ['rule', 'worst'],
  'MARKETING_EXECUTIVE', 'Owner\'s rule D-12: nothing new for a blocked customer, only payment reminders.',
  'TB Defaulter Co (Blocked) and TB Paused Co (On hold) from SAL-001.',
  [('New inquiry for TB Defaulter Co → Create inquiry.', 'Refused with one sentence saying the customer is blocked and where it is cleared (Client holds).'),
   ('Same for TB Paused Co.', 'Refused (on hold).'),
   ('As a manager clear the hold on TB Paused Co (Client holds → Status Active).', 'The enquiry can now be created.')],
  'D-12; partner_trade_block')

C('SAL-044', 'Inquiries', 'An enquiry belongs to the branch that recorded it', 'access', ['rule'],
  'several', 'Owner\'s answer of 20 Oct: an enquiry is the recorder\'s branch; others do not see or work it.',
  'Test MktExec (Ahmedabad) records an enquiry; Test KAM is in Mumbai.',
  [('As Test KAM: Left menu → Sales → Inquiries (Financial year: All years).', 'The Ahmedabad enquiry is NOT listed.'),
   ('Type /inquiry-edit?id=<that enquiry> in the address bar.', 'Refused: "This inquiry belongs to another branch."'),
   ('Start a quotation from it: /quote-new?inquiry=<id>.', 'The new quotation is NOT filled from that enquiry.'),
   ('As Test Director.', 'Every branch\'s enquiries.'),
   ('An older enquiry with no branch.', 'Every branch sees it, marked "No branch — every branch sees it".')],
  'crm_inquiry_in_scope; matrix fn 19c')

C('SAL-045', 'Inquiries', 'Deleting an enquiry', 'access', ['rule', 'worst'],
  'several', 'An enquiry already quoted is part of the record.',
  'One enquiry with a quotation, one without.',
  [('As Test MktExec, look for Delete on the register.', 'Not offered (administrators only).'),
   ('As Master Admin, press Delete on the enquiry WITH a quotation.', 'Refused: "A quote has been raised against INQ-…, so it is part of the record and cannot be deleted. Set its status to Dropped instead."'),
   ('Delete the one without a quotation.', '"Inquiry INQ-… deleted." and an activity entry is written.')],
  'inquiry-delete')

# ════════════════════════════════════════════════════════════════════════════
# S4 — Project costing (the price behind a man-month or project quotation)
# ════════════════════════════════════════════════════════════════════════════
C('SAL-050', 'Project costing', 'Build a costing for a 12-month site deputation — started from its enquiry', 'scenario', ['best', 'rule'],
  'BUSINESS_DEV_MANAGER', 'A man-month price carries salary, statutory, travel and overheads for months; the costing proves the margin. Owner, 20 Oct 2026: it is tagged with the enquiry or lead it prices.',
  'Signed in as Test BDM. An open enquiry from TB Steel Works Pvt Ltd, Ahmedabad, business unit Industrial, subject "QA/QC deputation 12 months".',
  [('Left menu → Sales → Project costing → "＋ New costing" → press "Create costing" without choosing anything under "For".', 'Refused: "Say which enquiry or lead this costing is for — pick one under "For"."'),
   ('Open the enquiry (Sales → Inquiries → Edit) → press "＋ Project costing".', 'The Project costing page opens with "＋ New costing" open and the enquiry already chosen under "For — enquiry".'),
   ('Leave Title, Client and Executing office empty, Basis "Long — per man-month" → "Create costing".', '"PC-… created". Under the title: "For: enquiry …" (a link) and the business unit Industrial. Client, office and title came from the enquiry.'),
   ('Same from a lead: open an open lead → Quotes tab → "+ Project costing" → Create costing.', 'The costing says "For: lead <its reference> — <company>".'),
   ('"＋ Add a role": Role "QA/QC Engineer", Personnel / salary 60,000, Statutory 9,000, Transport / travel 4,000, Accommodation / rentals 8,000, Food / meal allowance 3,000, PPE / safety kit 500, Units (BOQ) 12, Target margin (%) 20 → "Add role".', 'The role row shows Direct/mo = ₹84,500, a Loaded/mo, a Rate/lump, Units 12, Revenue, Profit and Margin ≈ 20%.'),
   ('Add a second role "Welding Inspector" with Personnel 45,000, Units 12, Target margin 15.', 'Both roles listed; totals add up.'),
   ('"Loadings, basis & details": Overhead % 8, Insurance % 1, Bad debt % 1, Contingency % 2, Negotiation % 3 → "Save settings".', 'Rates rise with the loadings; Negotiation % lifts the proposed rate only.'),
   ('Press "🖨 Print / PDF".', 'A printable sheet opens with every role and head.')],
  'owner 20 Oct 2026; pc_link_source(); pc_line_calc')

C('SAL-051', 'Project costing', 'A costing is approved through the same levels as a quotation — never by its author', 'scenario', ['rule'],
  'several', 'Owner, 20 Oct 2026: "approval level as of quotation shall be applicable." D-11 B: maker and checker are different people.',
  'The costing from SAL-050 (prepared by Test BDM). The levels from SAL-003.',
  [('As Test BDM: Approval → "Submit for approval".', '"Costing submitted for approval — N levels to pass, in order." The Approval panel lists each level, e.g. "Level 1 — Branch Manager (Test BranchMgr) · waiting" and, when its revenue is ₹5,00,000 or more, "Level 2 — Marketing Manager (Test MktMgr) · after the level before".'),
   ('Still as Test BDM, look at Approval.', 'No Approve button; it says "You prepared this costing, so someone else approves it."'),
   ('As Test MktMgr (level 2), open it before level 1 has signed and try to approve.', 'No Approve button (or refused: "This costing is waiting for someone else at its current approval level").'),
   ('As Test BranchMgr2 (Mumbai) → Approvals.', 'The costing is NOT listed — it is an Ahmedabad costing.'),
   ('As Test BranchMgr → Approvals → section "Project costings" → open it → "Send back" with the Reason empty.', 'Refused: "Say why it is being sent back, so its author knows what to change."'),
   ('Reason "Check accommodation rate" → Send back.', 'Status "Sent back" with the reason shown to the author.'),
   ('As Test BDM: correct it, Submit again. As Test BranchMgr: Approve.', '"Your level is signed — it now waits for level 2." (Or "Costing approved" when it has one level.)'),
   ('As Test MktMgr: Approve.', 'Status Approved. Each level shows "✓ approved by …".'),
   ('"Make a quotation" from it, then open the quotation.', 'The quotation carries the same enquiry and business unit.')],
  'owner 20 Oct 2026; D-11 B; pc_build_approvals(); pc_can_act()')

C('SAL-052', 'Project costing', 'A costing moves only in the allowed order', 'edge', ['edge', 'rule'],
  'several', 'A costing approved from Draft, or submitted with no roles, means nothing.',
  'A new costing with no roles.',
  [('Press "Submit for approval" with no roles (or force /project-costing-submit).', 'Refused: "Add at least one role before submitting."'),
   ('As an approver, try to approve a Draft (not submitted) costing.', 'Refused: "Only a submitted costing can be approved — this one is draft."'),
   ('Reopen an Approved costing ("Reopen as draft").', 'Message "Reopened as draft — its approval was withdrawn; submit it again when it is ready." Approved by is cleared.'),
   ('If a quotation was made from it, open that quotation\'s history.', 'A line "Costing PC-… was reopened by … — its approval was withdrawn."')],
  'docs/03 Project costing lifecycle')

C('SAL-053', 'Project costing', 'A quotation is made only from an approved costing', 'scenario', ['rule'],
  'MARKETING_MANAGER', 'Owner\'s rule D-11 A: approval is what makes the costing\'s prices the company\'s prices.',
  'One Draft costing, one Approved costing.',
  [('On the Draft costing press "📝 Create quotation".', 'Refused: "Make the quotation once this costing is approved — submit it for approval first."'),
   ('On the Approved costing press "📝 Create quotation".', 'Message "Quotation Q-… drafted from this costing — review and send." The quotation has one line per role, unit Man-month, quantity = Units (BOQ), rate = the proposed rate.'),
   ('Check the quotation\'s owner.', 'It belongs to you (the person who made it).')],
  'D-11 A; pc_make_quote')

C('SAL-054', 'Project costing', 'A man-month quotation goes forward only on an approved costing', 'scenario', ['rule', 'worst'],
  'BUSINESS_DEV_MANAGER', 'Owner\'s rule D-11 A; can be switched off in Set up Sales.',
  'Set up Sales: "Yes — an approved costing first".',
  [('New quote typed by hand with one line Unit = Man-month, Qty 6, Rate 90,000, and no costing → "Submit for approval" (or "Send" if no approval is needed).', 'Refused: "A man-month quote goes forward only from an approved project costing — make one for it (Project costing), have it approved, and link it to this quote."'),
   ('Attach an approved costing to it (Quote → Costing → attach existing… → Attach), then submit.', 'Accepted.'),
   ('Reopen that costing while the quotation waits for approval; approver presses Approve.', 'Refused with the same costing sentence — the costing is no longer approved.'),
   ('Revise the sent quotation (new revision) and send the revision.', 'Allowed — a revision stands on its quotation\'s costing.'),
   ('A man-day quotation with no costing.', 'Goes forward normally — the rule is for man-month only.'),
   ('Set up Sales → "No — the costing is optional"; retry the first step.', 'Allowed.')],
  'crm_quote_costing_problem', live=False)

C('SAL-055', 'Project costing', 'Changing a costing-based quotation\'s prices needs its creator and a note', 'scenario', ['rule', 'edge'],
  'several', 'Owner\'s answer to D-11 A: the creator may edit, with a mandatory note kept in history.',
  'A quotation made from an approved costing by Test MktMgr, still a draft.',
  [('As Test MktMgr: Edit → change only Contact person and Payment terms → Save quote.', 'Saved; no note asked for.'),
   ('Edit → change a Rate ₹ on a line → Save quote, leaving "Why the prices differ from the costing" empty.', 'Refused: "This quote was priced from costing PC-…. Say why its prices now differ, in "Why the prices differ from the costing" — the note is kept in its history. Nothing was saved." Everything you typed is still on the form.'),
   ('Type the note "Customer agreed ₹55,000 a month in the meeting of 18 Oct" → Save quote.', 'Saved. History: "Prices changed away from costing PC-… — note: Customer agreed …".'),
   ('As Test BDM (not the creator): change a rate with a note.', 'Refused: "Only the person who made it can change its prices away from the costing."'),
   ('Change the rate back exactly to the costing\'s rate.', 'Saved without a note (back in line with the costing).')],
  'crm_quote_costing_edit_problem')

C('SAL-056', 'Project costing', 'Costing numbers at the edges', 'edge', ['edge'],
  'MARKETING_MANAGER', 'Wrong arithmetic in a costing becomes a loss-making contract.',
  'A Draft costing.',
  [('Role with every head 0 and Target margin 20.', 'Rate 0; the quotation made from it would need a priced line — record what happens.'),
   ('Target margin 100 (or 150).', 'No division-by-zero or nonsense rate; record the result.'),
   ('Negative salary -10,000.', 'Refused or flagged — record what happens.'),
   ('"…or fixed rate/lump override" 1,00,000 with margin 20.', 'The fixed rate is used instead of the derived one.'),
   ('Basis "Short / fixed — lump sum": Units 5.', 'Revenue = one lump (quantity counted as 1), not ×5.'),
   ('"Loadings applied on" = Sell rate (Excel-style) vs Direct cost.', 'The loaded cost changes as described on screen; compare with your own Excel.')],
  'pc_line_calc')

C('SAL-057', 'Project costing', 'A costing role becomes a recruitment requirement', 'scenario', ['best'],
  'MARKETING_MANAGER', 'Manpower supply: the costed role is what HR must hire.',
  'An Approved costing; the Recruitment module is on.',
  [('On a role row press "→ Requirement".', 'A recruitment requisition opens with the role, loaded cost, proposed rate and target margin filled in; headcount and duration are left to set.'),
   ('As Test MktExec (no hiring edit right), press "→ Requirement".', 'Refused with a sentence naming the requisition.')],
  'pc_make_requisition')

# ════════════════════════════════════════════════════════════════════════════
# S5 — Quotations: the form, tab by tab
# ════════════════════════════════════════════════════════════════════════════
C('SAL-060', 'Quotes', 'Create a quotation — every tab of the form', 'scenario', ['best'],
  'BUSINESS_DEV_MANAGER', 'This is the document the customer reads and the one everything downstream is priced from.',
  'Signed in as Test BDM. Customer TB Refinery Ltd exists.',
  [('Left menu → Sales → Quotes → "+ New quote".', 'Page "New quote" with tabs: Client & header · Offices · Sites · Commercials · Line items · Terms.'),
   ('Tab "Client & header": Client = TB Refinery Ltd, Business Unit (primary) = Oil, Gas & Chemicals, Contact person "Mr Patel", Contact e-mail, Contact mobile, Subject / scope title "Shutdown TPI — columns", tick types "Stage / In-process inspection" and "Final inspection", Product category "Pressure vessels", Where did this quote come from? = Our quotation, Primary / owning Office = Ahmedabad.', 'Nothing is lost when switching tabs.'),
   ('Tab "Offices": tick Mumbai as an extra executing office.', 'Ticked.'),
   ('Tab "Sites": "+ Add site" → Name "Jamnagar unit 2", Type "Plant", Address, City Jamnagar, State Gujarat, PIN 361142, Site contact, Mobile, Nearest Office Ahmedabad.', 'The site appears and is offered in each line\'s Site box BEFORE saving.'),
   ('Tab "Commercials": Validity (days) 30, Currency INR, GST % 18, Payment terms "Against submission of report", Advance % 0, Signed by = company signature.', 'Accepted.'),
   ('Tab "Line items": "+ Add line" → Business Unit, Activity, Service (only the types ticked above are offered), Description "Stage inspection", Site = Jamnagar unit 2, Order = Line-item order, Qty 10, Unit Man-day, Rate ₹ 8,000.', 'Amount ₹80,000 shown on the line; totals: subtotal ₹80,000, GST ₹14,400, total ₹94,400.'),
   ('Tab "Terms": the company default text is filled in; add one sentence.', 'Your sentence stays.'),
   ('Press "Create quote".', 'Message "Q-… created." The quotation opens in Draft with tabs Overview · Jobs & order · History · Sites & docs.'),
   ('Press "⬇ PDF (for client)" and "Word (editable)".', 'Both files open with the client, lines, totals, GST, terms and signature.')],
  'quote-new; crm_save_lines')

C('SAL-061', 'Quotes', 'A draft needs only a customer and a subject — and a refusal keeps everything you typed', 'edge', ['edge'],
  'BUSINESS_DEV_MANAGER', 'People lose an hour of typing when a refused form comes back empty (SA-26).',
  '"+ New quote".',
  [('Press "Create quote" with no client and no typed name.', 'Refused: "Say who the quote is for — pick the client, or type their name. Nothing was saved." The form comes back with everything else as you typed it, lines included.'),
   ('Client chosen, Subject empty.', 'Refused: "Give the quote a subject — what is being offered. Nothing was saved."'),
   ('Client and subject only, no lines → Create quote.', 'Created as a Draft.'),
   ('On that draft press "Submit for approval".', 'Refused: "Before it can go further this quote needs at least one line with a quantity and a rate, … Edit it to add them."'),
   ('Add a line with Qty and Rate but blank Unit, submit.', 'Refused: needs "a unit on every line".')],
  'crm_quote_ready_problem')

C('SAL-062', 'Quotes', 'Numbers on a quotation at their limits', 'edge', ['edge'],
  'BUSINESS_DEV_MANAGER', 'A wrong tax or a negative validity on a customer document is embarrassing and costly.',
  'A draft quotation open for editing.',
  [('Validity (days) = -5 → Save quote.', 'Refused with a message about Validity (days) (allowed 0–3650). Nothing saved; typing kept.'),
   ('Validity 0.', 'Accepted — 0 means the quotation never expires.'),
   ('Validity 4000.', 'Refused (above 3650).'),
   ('GST % = 500.', 'Refused (GST % must be 0–100).'),
   ('Advance % = 120.', 'Refused (0–100).'),
   ('A line Qty = -2.', 'Refused with a message naming "the quantity on line 1"; nothing saved.'),
   ('Add a discount line with Rate -5,000 on a ₹80,000 quotation.', 'Accepted; subtotal ₹75,000.'),
   ('A discount line larger than the total (Rate -1,00,000).', 'Refused: "The lines total less than nothing. A discount line can reduce the quotation, not take it below zero."'),
   ('Qty 0.5 man-day, Rate 7,999.99.', 'Amount ₹3,999.995 is shown rounded to paise consistently on screen, PDF and Word.'),
   ('Rate 9,99,99,999 (a very large value).', 'Shown with Indian grouping on screen and in the PDF.')],
  'crm_quote_figures_problem')

C('SAL-063', 'Quotes', 'Double-click and two people editing the same quotation', 'edge', ['worst', 'edge'],
  'several', 'A double click used to make two quotations; a second editor silently overwrote the first (SA-26).',
  'Two browsers.',
  [('"+ New quote", fill a valid quotation, double-click "Create quote".', 'One quotation is created. If the form is sent twice you see "That quote was already saved — here it is."'),
   ('Two people open "Edit" on the same draft. A changes Contact person and saves. B then changes Subject and saves.', 'B is refused: "Someone else saved changes to this record while you had it open. Reopen it to see the latest version, then re-apply your change — your edit was not saved, so nothing of theirs was overwritten." A\'s change is kept.')],
  'form_once; stale_edit_block')

C('SAL-064', 'Quotes', 'Sites and lines pair correctly — including sites added before saving', 'edge', ['edge'],
  'BUSINESS_DEV_MANAGER', 'A line pointing at another quotation\'s site, or losing its site on save, sends the inspector to the wrong place.',
  '"+ New quote" with two sites.',
  [('Add sites "Plant A" and "Plant B", then two lines: line 1 Site = Plant A, line 2 Site = Plant B, before ever saving → Create quote.', 'On the saved quotation line 1 is at Plant A and line 2 at Plant B.'),
   ('Edit, remove Plant A (✕), save.', 'Line 1 no longer points at a deleted site; record what it shows.'),
   ('Leave a site row completely blank and save.', 'Ignored; it does not shift the other sites\' order.')],
  'crm_quote_loc_map')

C('SAL-065', 'Quotes', 'Quotation register — filters, export, statuses', 'scenario', ['edge'],
  'MARKETING_MANAGER', 'The register is the manager\'s daily view of every quotation.',
  'Quotations in each status across two FYs.',
  [('Left menu → Sales → Quotes.', 'Page "Quote register" with Financial year, Search, "⬇ Export", "Approval rules", "Templates", "+ External", "+ New quote", and columns Quote · Client · Subject · Business Unit · Total · Contract · Status.'),
   ('Financial year = previous FY → Search.', 'Only quotations from that FY.'),
   ('"⬇ Export".', 'A file with the same rows.'),
   ('Open a quotation that has been revised twice.', 'Only the current revision is listed as current; earlier revisions are reachable from the revision list.')],
  'quotes register')

C('SAL-066', 'Quotes', 'Raising a revision of a sent quotation', 'scenario', ['best'],
  'BUSINESS_DEV_MANAGER', 'A sent quotation is locked; change is a new revision so both versions stay on record.',
  'A quotation in Sent.',
  [('Overview → "Revise (new rev)" (or "Create a new revision"): What changed? "Rate reduced 5% after negotiation" → "Create revision".', 'Rev 01 opens as Draft with every header field, site, line and the terms carried over; the review checklist starts unticked.'),
   ('Open Rev 00.', 'Banner says it is an earlier revision; nothing on it can move.'),
   ('Try to accept Rev 00 (force /quote-status with to=ACCEPTED).', 'Refused: "This is an earlier revision (R00) — a later revision has replaced it, so nothing on it can move. Work on the current revision."'),
   ('History tab of Rev 01.', 'A snapshot and the reason for the revision.')],
  'quote-revise; transition table')

C('SAL-067', 'Quotes', 'A quotation lodged on the customer\'s portal (external quote)', 'scenario', ['rule'],
  'BUSINESS_DEV_MANAGER', 'Owner\'s rule D-18: a portal quote needs no approval; it is registered as sent or lost.',
  'Signed in as Test BDM.',
  [('Left menu → Sales → Quotes → "+ External": Submitted through = Client\'s portal, Portal "Ariba", Their reference "RFQ-7781", Submitted on today, Status "Sent — awaiting reply", Client TB Refinery Ltd, Business Unit, Executing Office, Subject, one line Qty 5 Man-day Rate 9,000 → "Register it".', 'Registered as Sent with no approval step.'),
   ('Try Status other than Sent or Lost.', 'Only "Sent — awaiting reply" and "Lost / regretted" are offered.'),
   ('Register one with no client.', 'Refused with a message.'),
   ('Later mark it accepted (as the person assigned).', 'Accepted; a contract can be registered on it.')],
  'D-18; quote-external')

C('SAL-068', 'Quotes', 'Quotation follow-ups and documents', 'scenario', ['best'],
  'BUSINESS_DEV_MANAGER', 'Chasing a sent quotation on time wins more business.',
  'A sent quotation.',
  [('Overview → Follow-ups: "Add a follow-up on" = in 3 days, Note "Call Mr Patel" → "+ Add".', 'Listed as Pending.'),
   ('Set its Status Done, Done on today, What was said → "Save follow-ups".', 'Saved.'),
   ('Sites & docs tab → Kind "Purchase order", Files (several at once, 8 MB each), PO number, PO date, tick "Share with the team member on every work…" → Attach.', 'Files listed; the PO number and date are kept.'),
   ('"✉️ Chase".', 'A follow-up e-mail draft opens.'),
   ('"✉️ Send from my mailbox".', 'A draft opens in your mail program with the PDF attached; you press Send yourself.')],
  'quote-followup; quote-files')

# ════════════════════════════════════════════════════════════════════════════
# S6 — Approvals: rules, levels in order, self-approval, cover and escalation
# ════════════════════════════════════════════════════════════════════════════
C('SAL-070', 'Approvals', 'A quotation the rules do not cover goes straight to the customer', 'scenario', ['rule', 'best'],
  'BUSINESS_DEV_MANAGER', 'Owner\'s rule D-02: approval only where the company\'s rules require it.',
  'Set up Sales: unmatched value → "No approval is needed". Approval levels exist only from ₹5,00,000 up.',
  [('A quotation of ₹2,00,000 before tax, Draft.', 'The Actions bar shows "✉ Send to client" and "No approval needed — your approval rules do not cover this value." No Submit button.'),
   ('Press "✉ Send to client".', 'Status "Sent — awaiting reply"; History records the move.')],
  'D-02; D-03 option none', live=False)

C('SAL-071', 'Approvals', 'Value is measured before tax — the boundary', 'edge', ['rule', 'edge'],
  'BUSINESS_DEV_MANAGER', 'Owner\'s rule D-04: the approval band is compared with the subtotal before GST.',
  'Levels from SAL-003 (Level 1 up to ₹5,00,000; Level 2 from ₹5,00,000).',
  [('Quotation subtotal ₹4,90,000 + GST 18% = total ₹5,78,200 → Submit for approval.', 'Approval chain shows Level 1 (Branch Manager) only — the total above ₹5 lakh does not matter.'),
   ('Subtotal exactly ₹5,00,000.', 'Record which level(s) appear; the owner\'s band rule decides. Both levels where bands share the boundary.'),
   ('Subtotal ₹5,00,001.', 'Level 2 is in the chain.'),
   ('A ₹40,00,000 quotation (above every band).', 'Unmatched value → the approvers of the highest level (Level 3, Business Director), per your Set up Sales answer.')],
  'D-04; crm_quote_band_value', live=False)

C('SAL-072', 'Approvals', 'Levels sign in order', 'scenario', ['rule'],
  'several', 'Owner\'s answer D-05: level 2 cannot sign before level 1.',
  'A ₹10,00,000 quotation whose chain has Level 1 (Branch Manager) and Level 2 (Marketing Manager), submitted by Test BDM.',
  [('As Test MktMgr (Level 2) open it → Approval chain → Approve.', 'Refused: "Level 1 approves first — this quote reaches level 2 when it has."'),
   ('As Test BranchMgr (Level 1) → comment optional → Approve.', 'Level 1 row shows Approved, by whom and when.'),
   ('As Test MktMgr → Approve.', 'Status "Approved (ready to send)". "✉ Send to client" appears.'),
   ('Repeat, but Level 2 presses Reject with a comment while Level 1 is still pending.', 'Allowed — a rejection at any level ends the chain. Status "Rejected by approver" with the comment.')],
  'D-05')

C('SAL-073', 'Approvals', 'A rejection needs a comment and sends the quotation back round', 'scenario', ['worst'],
  'BRANCH_MANAGER', 'The preparer must know what to change.',
  'A quotation waiting at Level 1.',
  [('Approval chain → Reject with the comment empty.', 'Refused: "Enter a comment saying why you are rejecting it."'),
   ('Comment "Rate below floor" → Reject.', 'Banner "Rejected by <you> on <date> — Comment: Rate below floor". Message "Rejected. The quote now shows as rejected by you, with your comment."'),
   ('As Test BDM: "Edit first", correct, then "↻ Submit for approval again".', 'A fresh chain is built; the earlier rejection stays in History.')],
  'quote-approve reject')

C('SAL-074', 'Approvals', 'Preparer approving their own quotation — both answers of Set up Sales', 'scenario', ['rule'],
  'BRANCH_MANAGER', 'Owner\'s rule D-06: within their own authority, yes; a lower level never.',
  'Test BranchMgr prepares a ₹3,00,000 quotation (Level 1 = Branch Manager).',
  [('Set up Sales "Yes — when it is within their own authority". Submit, then as the same Test BranchMgr press Approve.', 'Approved; History says it was a self-approval.'),
   ('Test MktExec prepares a ₹3,00,000 quotation and tries to approve it.', 'No Approve button: the Level 1 step is not theirs.'),
   ('Set up Sales "No — someone else must always approve it". Repeat the first step.', 'Refused: "Your company asks that someone other than the person who prepared a quote approves it."')],
  'D-06; quote_self_approval', live=False)

C('SAL-075', 'Approvals', 'Any commercial or terms change cancels an approval', 'scenario', ['rule', 'worst'],
  'BUSINESS_DEV_MANAGER', 'Owner\'s rule D-07: what was approved must be what is sent.',
  'An Approved quotation (not yet sent).',
  [('Edit → change only the Contact person → Save quote.', 'Saved; still Approved.'),
   ('Edit → change a line Rate → Save quote.', 'Message "Quote saved. Because its commercial terms changed, the approval was cancelled — submit it for approval again." Status Draft. History: "Edited — the commercial terms changed, so the approval was cancelled (total ₹… → ₹…)."'),
   ('Repeat with: Payment terms, Validity, GST %, Advance %, a site address, Terms & conditions text, the customer.', 'Each one cancels the approval.'),
   ('A quotation Pending approval: change a quantity.', 'The approval in progress is cancelled the same way.')],
  'D-07; QUOTE_COMMERCIAL_FIELDS')

C('SAL-076', 'Approvals', 'An approver who is away names someone to cover', 'scenario', ['rule'],
  'MARKETING_MANAGER', 'Owner\'s answer D-11a: delegation covers quotations.',
  'A quotation waiting at a Marketing Manager step.',
  [('As Test KAM, open it.', 'No Approve button (not their step).'),
   ('As Test MktMgr: Set up Sales → "Set who covers for an approver who is away" (page "Who covers for a quotation approver") → Who is away = Test MktMgr, Who acts for them = Test KAM, Branch = Any, From today, To next week, Reason "Annual leave" → "Add delegation".', 'Message "Delegation created." Listed with State active.'),
   ('As Test KAM: Sales home "waiting for me" / Quotes → awaiting my approval.', 'The quotation is listed.'),
   ('Test KAM presses Approve.', 'Approved; the row records Test KAM signing for Test MktMgr.'),
   ('Revoke the delegation; submit another quotation; Test KAM opens it.', 'No Approve button any more.'),
   ('As Test Finance open "Who covers for a quotation approver" and try to add a delegation.', 'Refused — only someone who manages the quotation approval rules.'),
   ('A delegation whose To date is in the past.', 'Shown as ended; it gives no approval right.')],
  'D-11a; ops_quote_delegations')

C('SAL-077', 'Approvals', 'A quotation waiting too long is opened to the level above', 'scenario', ['rule'],
  'several', 'Owner\'s answer D-11a: escalation after the days the company sets.',
  'Set up Sales "After 1 day". A quotation submitted yesterday, waiting at Level 1 (Branch Manager); Level 3 approver is Business Director.',
  [('Wait for the night job (or ask your administrator to run cron.php).', 'The quotation\'s History says the step was "opened to the level above"; the Business Director receives an e-mail.'),
   ('As Test Director: "waiting for me".', 'The quotation is listed and can be approved.'),
   ('The Branch Manager can still approve it.', 'Yes — escalation adds approvers, it does not remove the original.'),
   ('Run the night job again.', 'Nothing is escalated twice.'),
   ('Set up Sales "Never — it waits for its own approver".', 'Nothing escalates.')],
  'crm_escalate_quote_approvals', live=False)

C('SAL-078', 'Approvals', 'Approvers only see and sign quotations of their own branch', 'access', ['rule'],
  'BRANCH_MANAGER', 'SA-06: a branch manager approves for their branch.',
  'A Mumbai quotation waiting at a Branch Manager step; Test BranchMgr is Ahmedabad, Test BranchMgr2 is Mumbai.',
  [('As Test BranchMgr: waiting-for-me count and list.', 'The Mumbai quotation is NOT counted or listed.'),
   ('Type its address /quote?id=….', 'Refused: "This quotation is outside your office / branch scope."'),
   ('As Test BranchMgr2.', 'Listed and can be approved.')],
  'SA-06; crm_quote_ids_awaiting_me')

C('SAL-079', 'Approvals', 'Approval rules screen — add, edit, remove, overlaps', 'edge', ['edge'],
  'MARKETING_MANAGER', 'Wrong bands route quotations to the wrong person.',
  'Quote & report approvals screen.',
  [('Add a rule From ₹10,00,000 Up to ₹5,00,000 (end below start).', 'Refused or warned — record what happens.'),
   ('Add two Level-1 rules with overlapping bands.', 'Record whether a quotation in the overlap needs both.'),
   ('Edit a rule\'s approver role while a quotation is waiting on it.', 'Record whether the waiting step changes; the next submission uses the new rule.'),
   ('Remove (✕) a rule.', 'Asks to confirm; removed; existing approved quotations keep their history.'),
   ('Rule for one Business Unit only (Applies to).', 'Quotations of other units are not routed by it.')],
  'quote_approval_rules')

# ════════════════════════════════════════════════════════════════════════════
# S7 — The contract-review (pre-order) checklist
# ════════════════════════════════════════════════════════════════════════════
C('SAL-080', 'Pre-order checklist', 'Set the checklist points', 'scenario', ['best'],
  'MASTER_ADMIN', 'Inspection and testing bodies must review each request before accepting it (ISO/IEC 17020 / 17025).',
  'Signed in as Master Admin.',
  [('Left menu → Sales → Pre-order checklist.', 'Page "Pre-order review checklist" with "Show the pre-order checklist on quotations", "Require every point ticked before a quotation …" and "Check points — one per line".'),
   ('Tick both, keep the starter points (Enquiry / tender reviewed…, Client is not on hold or blocked, Commercials checked…, Payment terms…, Executing office(s)…, EMD / bank guarantee…, Impartiality…) → "Save pre-order checklist".', 'Saved.'),
   ('Open any draft quotation.', 'Panel "Pre-order review" with every point, "0/7 done", and "Every point must be ticked — or the checklist waived — before it is approved, sent, or accepted."')],
  'preorder_checklist_*', live=False)

C('SAL-081', 'Pre-order checklist', 'The checklist holds approving, sending and accepting', 'scenario', ['rule'],
  'several', 'Owner\'s answer D-10: it gates send and accept, not only the approval signature.',
  'Checklist required (SAL-080).',
  [('Quotation needing no approval, 0/7 ticked → "✉ Send to client".', 'Refused: "The review checklist on this quote is not complete (0/7 ticked). Tick every point first — or someone who may approve can waive it, with a reason."'),
   ('Submit for approval (allowed), then the approver presses Approve.', 'Submitting works; the approval is refused with the same sentence.'),
   ('Tick all 7 → "Save checklist".', 'Each point shows who ticked it and the date; pill "7/7 done".'),
   ('Approve, then send.', 'Both work.'),
   ('A quotation already Sent before the checklist was required → Mark accepted.', 'Refused until ticked or waived.')],
  'D-10; crm_quote_checklist_problem', live=False)

C('SAL-082', 'Pre-order checklist', 'Waiving the checklist — who, with a reason, shown on the quotation', 'scenario', ['rule', 'worst'],
  'several', 'A point that cannot be confirmed (e.g. bank guarantee terms come with the PO) must not block business forever.',
  'Checklist required; a quotation with 5/7 ticked.',
  [('As Test BDM (the preparer, not an approver): look for "A point cannot be confirmed? Waive the checklist".', 'Not offered (or refused: "Only someone who may approve this quote can waive its checklist.").'),
   ('As Test BranchMgr (approver): open "Waive the checklist", leave the reason empty.', 'Refused: "Say why the checklist is being waived — the reason is shown on the quote."'),
   ('Reason "Bank guarantee terms come with the PO" → Waive.', 'Message "Checklist waived. The reason and your name are shown on the quote." Panel: "⚠ Waived by Test BranchMgr on <date> — Bank guarantee terms come with the PO". History has the waiver.'),
   ('Raise a revision.', 'The revision starts unticked and NOT waived.'),
   ('Accept or lose the quotation, then try to change its ticks.', 'Refused: its checklist can no longer change.')],
  'D-10 waiver')

# ════════════════════════════════════════════════════════════════════════════
# S8 — Sending, accepting, losing, expiring
# ════════════════════════════════════════════════════════════════════════════
C('SAL-090', 'Quotes', 'Only the person assigned records the customer\'s answer', 'access', ['rule'],
  'several', 'Owner\'s rule D-08: the person assigned to the work records accepted or lost.',
  'A Sent quotation owned by Test BDM.',
  [('As Test Finance (can view quotations) open it.', 'No "Mark accepted (won)" or "Mark lost". A line says "Test BDM, who is assigned to this quote, records the client\'s answer."'),
   ('As Test KAM.', 'Same.'),
   ('As Test BDM.', '"Mark accepted (won)" and "Mark lost" are offered.'),
   ('As Master Admin.', 'Offered (master).')],
  'D-08')

C('SAL-091', 'Quotes', 'Marking a quotation lost needs a reason', 'scenario', ['worst'],
  'BUSINESS_DEV_MANAGER', 'Loss reasons drive pricing decisions.',
  'A Sent quotation.',
  [('"Mark lost" → Reason "— pick a reason —" → "Confirm lost".', 'Refused; it asks why it was lost.'),
   ('Reason "Other (specify)" with "Other — please specify" empty.', 'It should ask for the text — record what happens.'),
   ('Reason "Awarded to a competitor" → Confirm lost.', 'Status "Lost / regretted"; History has the reason.'),
   ('Try to accept it afterwards.', 'Refused: "This quote is marked lost. If the client comes back, raise a revision."')],
  'quote-status LOST')

C('SAL-092', 'Quotes', 'Accepting a quotation typed for a company already on file', 'scenario', ['rule', 'edge'],
  'BUSINESS_DEV_MANAGER', 'A name is evidence, not proof; the person recording the acceptance decides (SA-18).',
  'A Sent quotation whose client was typed as "TB VALVES PRIVATE LIMITED"; "TB Valves Pvt Ltd" is on file.',
  [('Press "Mark accepted (won)".', 'Not accepted yet. A box: "Before recording the acceptance: is "TB VALVES PRIVATE LIMITED" the same company as TB Valves Pvt Ltd (C-…), already on file?"'),
   ('"Yes — same company, mark accepted".', 'Accepted against the existing customer; no second customer is created.'),
   ('Another quotation typed "TB Valves Ltd." → "No — a different company, mark accepted".', 'Accepted and a new customer is created.'),
   ('A quotation typed exactly "TB Valves Pvt Ltd".', 'Accepted and linked without asking.')],
  'crm_quote_possible_customer')

C('SAL-093', 'Quotes', 'Acceptance carries back up the funnel', 'scenario', ['best'],
  'BUSINESS_DEV_MANAGER', 'One acceptance should close the deal and convert the lead without retyping.',
  'A quotation raised from a lead that is linked to an open deal.',
  [('Mark accepted (won).', 'Status "Accepted / closed-won".'),
   ('Open the deal.', 'Moved to its Won stage.'),
   ('Open the lead.', 'Converted, with the customer.'),
   ('Client register.', 'The customer is listed once.')],
  'quote_land_on_client; lead_convert_on_quote_win')

C('SAL-094', 'Quotes', 'A quotation expires when its validity ends', 'edge', ['edge'],
  'BUSINESS_DEV_MANAGER', 'An offer past its validity is not binding.',
  'A Sent quotation with Validity 1 day, sent 2 days ago (or ask the administrator to run the night job).',
  [('After the night job, open it.', 'Status "Expired"; counted separately from Lost.'),
   ('Mark accepted (after expiry).', 'Allowed — expiry never blocks accepting; the button says "Mark accepted (after expiry)".'),
   ('Validity 0.', 'Never expires.')],
  'crm_expire_quotes')

C('SAL-095', 'Quotes', 'Moves that are not allowed are refused with a reason', 'edge', ['rule', 'worst'],
  'BUSINESS_DEV_MANAGER', 'The quotation lifecycle is enforced at the server, not only by hiding buttons.',
  'Ask your administrator for the quotation addresses; type them in the browser.',
  [('A Draft quotation: force a move to Accepted.', 'Refused: "A quote can be accepted only once it has gone to the client."'),
   ('An Accepted quotation: force Lost.', 'Refused: "The client has accepted this quote, and that is final. A change after acceptance is a new revision."'),
   ('A quotation needing approval: force Sent from Draft.', 'Refused: "This quote needs approval before it goes to the client — your approval rules cover its value. Submit it for approval first."'),
   ('Force a status that does not exist ("EXPIRED" or "REJECTED" typed by hand).', 'Refused: "That is not a step in a quote\'s life…"'),
   ('An already Sent quotation: send again.', 'Refused: "This quote is sent — it cannot be sent again. Raise a revision to send a new offer."')],
  'crm_quote_transition_problem')

# ════════════════════════════════════════════════════════════════════════════
# S9 — Contract: registration, opening, amendment, group companies
# ════════════════════════════════════════════════════════════════════════════
C('SAL-100', 'Contracts', 'Register the contract on an accepted quotation and get it opened', 'scenario', ['best', 'rule'],
  'several', 'Opening a contract number is a two-signature act: a manager endorses, the branch manager approves.',
  'An Accepted quotation (current revision).',
  [('As Test Finance: quotation → "Won — client & contract registration (Accounts)" → "✓ Register the contract & request opening".', 'A contract number is registered as Pending.'),
   ('As Test OpsMgr: "Endorse (manager)".', 'History "Contract … opening endorsed by …. Awaiting branch-manager approval."'),
   ('As Test OpsMgr try "Approve & open (branch manager)".', 'Refused (not a branch manager), or if they are, refused because the approver must not be the endorser.'),
   ('As Test BranchMgr: "Approve & open (branch manager)".', 'Contract OPEN; "Order floated to operations." Operations sees the handover.'),
   ('Try to register a contract on an earlier revision, or on a Sent (not accepted) quotation.', 'Refused.')],
  'contract-open; quote-contract')

C('SAL-101', 'Contracts', 'An amendment to an open contract takes two signatures and keeps the old figures', 'scenario', ['rule'],
  'several', 'Owner\'s answer D-25.',
  'An OPEN contract worth ₹5,00,000 ending 31 Mar.',
  [('As Test Finance: contract → "✎ Edit contract" → change Title only → Save changes.', 'Saved at once.'),
   ('Change Value to ₹6,50,000 and End date to 30 Jun, leave "Reason for the amendment" empty → Save changes.', 'Refused: "Changing the value, dates or quantities of an open contract is an amendment — say why… The title and notes were saved; the figures were not."'),
   ('Reason "PO amendment 2" → Save changes.', 'Message "Amendment requested — value ₹5,00,000 → ₹6,50,000; end … → …. It needs a manager\'s endorsement and the branch manager\'s approval…" The contract still shows ₹5,00,000.'),
   ('Panel "Amendments": Test OpsMgr "Endorse", Test BranchMgr "Approve amendment".', 'Contract now ₹6,50,000 ending 30 Jun; the amendment row keeps the old figures.'),
   ('Request another amendment and Reject it without a reason, then with one.', 'Without: refused. With: "Amendment rejected — the contract keeps its current figures."'),
   ('Request two amendments in a row.', 'The second is refused while the first waits.')],
  'D-25; contract_amendments')

C('SAL-102', 'Contracts', 'Re-opening a contract only when closed or rejected', 'edge', ['rule', 'worst'],
  'FINANCE', 'Re-opening an open contract used to wipe both of its signatures (SA-19).',
  'One OPEN contract, one CLOSED contract.',
  [('Force "reopen" on the OPEN contract.', 'Refused: "Only a closed or rejected contract can be re-opened. To change an open contract, edit it — a change to its figures becomes an amendment." Signatures intact.'),
   ('On the CLOSED contract press "Request re-opening".', 'Back to Pending for two new signatures.')],
  'SA-19')

C('SAL-103', 'Contracts', 'One quotation, contracts for several group companies', 'scenario', ['edge'],
  'FINANCE', 'A parent company\'s quotation often covers subsidiaries with their own contracts.',
  'Accepted quotation for TB Group Parent; TB Group Child is related.',
  [('Collapsible "Register a contract for another group company" → Group company = TB Group Child, Generate automatically, Value, Start, End → "Register group contract".', 'A Pending contract for the child, linked to the same quotation; the parent\'s contract is unchanged.'),
   ('The list of group companies.', 'Unrelated customers are not offered; the parent is not offered again.')],
  'quote-add-contract')

C('SAL-104', 'Contracts', 'A deal won without a quotation still gets a contract', 'scenario', ['edge'],
  'FINANCE', 'Some customers send a PO without asking for a formal quotation.',
  'A Won deal with no quotation.',
  [('On the deal, "Register the contract" (Accounts).', 'A Pending contract is registered; it follows the same two-signature opening.'),
   ('As Test MktExec try the same.', 'Refused — needs the contract-registration right.')],
  'opportunity-contract; matrix fn 19b')

# ════════════════════════════════════════════════════════════════════════════
# S10 — Blocked and on-hold customers across Sales
# ════════════════════════════════════════════════════════════════════════════
C('SAL-110', 'Client holds', 'A blocked customer gets nothing new — only payment reminders', 'scenario', ['rule', 'worst'],
  'several', 'Owner\'s rule D-12.',
  'TB Defaulter Co is Blocked. It has one Sent quotation from before the block and one unpaid invoice.',
  [('New lead → convert for TB Defaulter Co.', 'Refused with the block sentence.'),
   ('New enquiry, new quotation, Submit, Approve, Send, Mark accepted, register contract — each for TB Defaulter Co.', 'Each refused with one sentence naming the block and where it is cleared.'),
   ('Mark its old quotation Lost.', 'Allowed — closing business is fine.'),
   ('Money: invoice for work already done; payment reminder; receipt.', 'Still allowed (owner: invoicing work already done stays open).'),
   ('As a manager: Left menu → Directory → Client holds → set Active.', 'Everything works again.')],
  'D-12')

# ════════════════════════════════════════════════════════════════════════════
# S11 — Numbers: dashboard and registers must agree
# ════════════════════════════════════════════════════════════════════════════
C('SAL-120', 'Sales dashboard', 'Every dashboard figure matches the register it comes from', 'scenario', ['rule'],
  'MARKETING_MANAGER', 'A number that disagrees with its own list cannot be trusted for decisions.',
  'Several leads, deals and quotations in each status.',
  [('Left menu → Insights → Sales dashboard → period 90 days.', 'Sections: The funnel · From first contact to won · Deals that have stopped moving · Why we lose · Who is selling · Effort behind it.'),
   ('Count of open deals in "Who is selling" vs Sales → Opportunities (List, same period).', 'Equal.'),
   ('"Why we lose" reasons vs lost deals in Opportunities → "Why deals were lost — last 12 months" (choose 12 months).', 'Equal counts and values.'),
   ('"Deals that have stopped moving" vs the board (days beyond the stage\'s Days allowed).', 'Same deals.'),
   ('"⬇ Funnel CSV".', 'The file matches the screen.'),
   ('Switch 30 days / 12 months / 2 years.', 'Figures change consistently; nothing negative.')],
  '07-DASHBOARD-AND-KPI-AUDIT')

# ════════════════════════════════════════════════════════════════════════════
# S12 — Who may do what in Sales (one row per role)
# ════════════════════════════════════════════════════════════════════════════
for i, (role, can, cannot) in enumerate([
    ('MARKETING_EXECUTIVE', 'Leads (view, add), Opportunities, Inquiries (add, edit), Quotes (create, edit, submit), Project costing (view/edit)', 'Approve quotations (unless named on a step), delete leads, edit pipelines, Set up Sales, register contracts'),
    ('BUSINESS_DEV_MANAGER', 'Leads, Opportunities, Inquiries, Quotes (create, send), follow-ups', 'Approve quotations by default, Set up Sales, register contracts'),
    ('KEY_ACCOUNTS_MANAGER', 'Leads, Opportunities, Inquiries, Quotes (create, send), follow-ups', 'Approve quotations by default, Set up Sales'),
    ('MARKETING_MANAGER', 'Everything a BDM can, plus approve quotations, approval rules, Set up Sales, approver cover, quote templates, reopen lost leads/deals', 'Register contracts (unless granted), delete enquiries'),
    ('BRANCH_MANAGER', 'Approve quotation steps routed to Branch Manager (own branch), approve contract opening', 'See other branches\' quotations, edit pipelines'),
    ('BUSINESS_DIRECTOR', 'See every branch, approve, Insights', 'Delete records unless administrator'),
    ('FINANCE', 'Read quotations, register contracts, edit contract figures (as amendment), read client register', 'Create quotations, mark accepted/lost, work leads'),
    ('OPERATION_MANAGER', 'Endorse contract opening and amendments (Work orders)', 'Quotations'),
    ('COORDINATOR', 'Customer and work-order side', 'Leads, Opportunities, Quotes'),
    ('INSPECTOR', 'Own jobs only', 'Any Sales screen'),
]):
    C(f'SAL-13{i}', 'Who may do what', f'{role.replace("_", " ").title()} — what they may and may not do in Sales', 'access', ['rule'],
      role, 'docs/02-permission-matrix.md is the rule; any difference is a defect or a decision for the owner.',
      f'Signed in as the test user with role {role}.',
      [('Open Left menu → Sales and each tile in turn: Leads, Opportunities, Inquiries, Quotes, Quote templates, Pre-order checklist, Project costing, Approvals, Pipelines & funnels, Set up Sales.', 'Opens exactly what the matrix allows; anything else is refused with a sentence (never a blank page or an error).'),
       (f'Try what the permission matrix says they may do: {can}.', 'Each works.'),
       (f'Try what the matrix says they may NOT do: {cannot}.', 'Each is refused (button absent, or a refusal sentence when the address is typed).'),
       ('Type the address of a refused screen directly.', 'Refused the same way — hiding the button is not enough.')],
      'docs/02-permission-matrix.md fn 19, 19a–19f')

# ════════════════════════════════════════════════════════════════════════════
# S13 — Phone, and other working conditions
# ════════════════════════════════════════════════════════════════════════════
C('SAL-140', 'Phone & working conditions', 'Sales on a phone (salespeople in the field)', 'phone', ['phone', 'edge'],
  'BUSINESS_DEV_MANAGER', 'Salespeople log contacts and check quotations from a customer\'s site.',
  'A phone (or the browser narrowed to 390 pixels).',
  [('Leads list, a lead, "Log a contact".', 'Usable without sideways scrolling of the page; buttons big enough to tap.'),
   ('Open a quotation and its PDF.', 'Readable; tables scroll inside themselves.'),
   ('New quote — the Line items tab.', 'Usable; record any field that cannot be reached.')],
  'R-19 phone pass')

C('SAL-141', 'Phone & working conditions', 'Losing the connection or the session mid-form', 'edge', ['worst', 'edge'],
  'BUSINESS_DEV_MANAGER', 'Lost work is the most common complaint about business software.',
  'A long new quotation half typed.',
  [('Leave the form open longer than the sign-in timeout, then press Create quote.', 'You are asked to sign in; record whether the typing is kept.'),
   ('Turn off the network, press Save, turn it back on.', 'The browser shows an error; pressing Save again saves ONE quotation.'),
   ('Press the browser Back button after saving.', 'No second quotation is created.')],
  'form_once')

# ════════════════════════════════════════════════════════════════════════════
# S14 — The same Sales work, company type by company type
# ════════════════════════════════════════════════════════════════════════════
C('SAL-150', 'Company types', 'Inspection agency: a man-day quotation, approved, sent, won', 'scenario', ['best'],
  'several', 'The core business: third-party inspection priced per man-day.', 'Profile A (SAL-004). Approval levels from SAL-003.',
  [('[Test MktExec] Enquiry for TB Refinery Ltd: "Stage + final inspection of 2 columns, 10 man-days".', 'Enquiry Open.'),
   ('[Test MktExec] "Quote" from the enquiry: lines Stage inspection 6 Man-day × ₹8,000 and Final inspection 4 Man-day × ₹8,000; GST 18%.', 'Subtotal ₹80,000; GST ₹14,400; total ₹94,400. Enquiry shows Quoted.'),
   ('[Test MktExec] Submit for approval.', 'Level 1 Branch Manager step.'),
   ('[Test BranchMgr] Tick the checklist, Approve.', 'Approved.'),
   ('[Test MktExec] Send to client, then Mark accepted (won).', 'Accepted; contract registration offered to Accounts.')],
  'end-to-end, Sales part', company='A')

C('SAL-151', 'Company types', 'Testing laboratory: per-sample and per-document lines', 'scenario', ['best', 'edge'],
  'BUSINESS_DEV_MANAGER', 'A lab sells tests and certificates, not man-days.', 'Profile B.',
  [('New quote: lines "Tensile test" Qty 12 Unit Other Rate ₹1,500; "Test certificate" Qty 12 Unit Per document Rate ₹300.', 'Subtotal ₹21,600.'),
   ('Service box on the line.', 'Offers only the types ticked on Client & header (inspection types). Record whether lab test names fit — if not, record as a gap for laboratories.'),
   ('PDF.', 'Units print as "Other" and "Per document"; record whether the customer would understand it.')],
  'CHARGE_UNITS; INSPECTION_TYPES', company='B')

C('SAL-152', 'Company types', 'Manpower supplier: costing → man-month quotation → requirement', 'scenario', ['best', 'rule'],
  'several', 'Deputation business lives or dies on the man-month margin.', 'Profile C; Recruitment on.',
  [('[Test MktMgr] Project costing for 3 QA/QC engineers × 12 months (SAL-050).', 'Costing Draft with margin shown.'),
   ('[Test MktMgr] Submit; [Test Director] Approve.', 'Approved.'),
   ('[Test MktMgr] "📝 Create quotation".', 'Man-month quotation drafted.'),
   ('[Test MktMgr] Submit, approve, send, accept.', 'Each step works because the costing is approved.'),
   ('[Test MktMgr] On the costing, "→ Requirement" for the engineer role.', 'A requisition for HR with the costed rate.')],
  'D-11', company='C')

C('SAL-153', 'Company types', 'Manufacturer selling products: can Sales quote goods?', 'scenario', ['gap', 'edge'],
  'BUSINESS_DEV_MANAGER', 'A product company quotes items, quantities in Nos/kg, HSN codes and goods GST.',
  'Profile D. Item to quote: 50 × "SS316 fire hose reel cabinet", HSN 8424, ₹18,500 each, GST 18%.',
  [('New quote → Line items: Description "SS316 fire hose reel cabinet", Qty 50.', 'Accepted.'),
   ('Unit.', 'No "Nos / pieces / kg" in the built-in list (Man-day, Man-month, Per visit, Audit day, Per lot / lump sum, Per document, Per km, Other). Use Other. Record as a GAP unless your administrator adds "Nos" under Admin → Masters (charge unit list) — if added, check it appears here.'),
   ('HSN code.', 'There is no HSN / SAC field on a quotation line. Record as a GAP.'),
   ('Service box.', 'Offers inspection types only. Record as a GAP for product companies.'),
   ('Rate ₹18,500, GST 18% → totals.', 'Subtotal ₹9,25,000; GST ₹1,66,500; total ₹10,91,500.'),
   ('Delivery period, freight, packing, warranty.', 'No dedicated fields — only Terms text. Record as a GAP.'),
   ('PDF.', 'Record how the document reads to a buyer of goods.')],
  'CHARGE_UNITS; no HSN field (crm.php)', company='D')

C('SAL-154', 'Company types', 'Trader: products and services on one quotation', 'scenario', ['gap', 'edge'],
  'BUSINESS_DEV_MANAGER', 'A distributor sells items plus installation.', 'Profile E.',
  [('Line 1: 20 × valve (Unit Other), line 2: installation 3 Man-day.', 'Both lines saved; one GST % for the whole quotation.'),
   ('Different GST for goods (e.g. 12%) and service (18%) on one quotation.', 'Not possible — one GST % per quotation. Record as a GAP (D-15).')],
  'D-15 GST type', company='E')

C('SAL-155', 'Company types', 'EPC / project company: lump-sum stages', 'scenario', ['best'],
  'BUSINESS_DEV_MANAGER', 'Projects are priced by milestone.', 'Profile F.',
  [('Lines "Design review" 1 Per lot / lump sum ₹2,00,000; "Site supervision" 6 Man-month ₹1,20,000.', 'Man-month line triggers the costing rule if Set up Sales requires it.'),
   ('Open order (ARC / call-off) vs Line-item order on each line.', 'Both choices saved and printed.')],
  'ORDER_TYPES', company='F')

C('SAL-156', 'Company types', 'One-person consultancy: no approval at all', 'scenario', ['best', 'rule'],
  'MASTER_ADMIN', 'A one-person company approves nothing.', 'Profile G: no approval levels; Set up Sales "No approval is needed".',
  [('New quote → Create → "✉ Send to client".', 'Sent straight from Draft; "No approval needed" message shown.'),
   ('Set up Sales "Already set by your company\'s rules".', 'Says there are no approval levels.')],
  'D-03 none; D-06', company='G', live=False)

C('SAL-157', 'Company types', 'Large multi-branch company: branch walls and director view', 'scenario', ['rule'],
  'several', 'A big company must keep branches apart and let the director see all.', 'Two branches, users per branch.',
  [('Each branch records enquiries, leads, deals and quotations.', 'Each branch sees its own plus unassigned records only.'),
   ('Branch managers approve only their branch.', 'As SAL-078.'),
   ('Director: Sales dashboard.', 'All branches added up; equals the sum of each branch\'s lists.')],
  'scope rules', company='H')

# ════════════════════════════════════════════════════════════════════════════
# S15 — Sales from start to end (the module's own flow)
# ════════════════════════════════════════════════════════════════════════════
C('SAL-160', 'Sales flow', 'Lead to opened contract — every hand-off, every number carried over', 'flow', ['best'],
  'several', 'The whole Sales module in one run; any break here stops revenue.',
  'Profile A; test users; checklist required; approval levels.',
  [('[Test MktExec] New lead "TB Ports Ltd", value ₹6,00,000.', 'Lead at first stage.'),
   ('[Test MktExec] Log a contact; move to Qualified → Proposal sent.', 'History shows both moves.'),
   ('[Test MktExec] Won → Convert (new customer, State Gujarat).', 'Customer + enquiry created; enquiry assigned to you, branch Ahmedabad.'),
   ('[Test BDM] Open a deal for TB Ports Ltd, value ₹6,00,000; create a quotation from the deal.', 'Draft quotation linked to the deal.'),
   ('[Test BDM] Lines 60 Man-day × ₹10,000 = ₹6,00,000 subtotal; GST 18% = ₹1,08,000; total ₹7,08,000.', 'Totals exact.'),
   ('[Test BDM] Submit for approval.', 'Chain: Level 2 (Marketing Manager) because ₹6,00,000 is above ₹5,00,000 before tax.'),
   ('[Test MktMgr] Tick the checklist (7/7), Approve.', 'Approved.'),
   ('[Test BDM] Send to client.', 'Sent; follow-up offered.'),
   ('[Test BDM] Mark accepted (won).', 'Accepted. Deal → Won. Lead → Converted.'),
   ('[Test Finance] Register the contract & request opening.', 'Contract Pending, value ₹6,00,000 (before tax).'),
   ('[Test OpsMgr] Endorse. [Test BranchMgr] Approve & open.', 'Contract OPEN; order floated to Operations.'),
   ('[Test Coord] Left menu → Operations → work orders.', 'A work order can be raised under this contract number with the customer, sites and lines carried over — nothing retyped.'),
   ('Insights → Sales dashboard.', 'Won value includes ₹6,00,000; Test BDM credited.')],
  'docs/audit-2026-10/workflows/01-SALES.md; 03-WORKFLOW-CATALOGUE')

C('SAL-161', 'Sales flow', 'The worst day: rejection, revision, price change, approval cancelled, lost, reopened, won', 'flow', ['worst'],
  'several', 'Real deals rarely go straight; every detour must keep the record honest.',
  'As SAL-160.',
  [('[Test BDM] Submit a ₹6,00,000 quotation.', 'Pending at Level 2.'),
   ('[Test MktMgr] Reject: "Rate below floor".', 'Rejected with comment.'),
   ('[Test BDM] Edit first → rate up → Submit for approval again.', 'Fresh chain.'),
   ('[Test MktMgr] Approve. [Test BDM] change Payment terms before sending.', 'Approval cancelled → Draft.'),
   ('[Test BDM] Submit again; approved; Send.', 'Sent.'),
   ('[Test BDM] Customer asks 5% less → Revise (new rev) → Submit → approved → Send Rev 01.', 'Rev 00 locked as earlier revision.'),
   ('[Test BDM] Mark Rev 01 lost ("Price too high").', 'Lost; deal lost with reason.'),
   ('[Test MktMgr] Reopen the deal: "Customer came back with budget".', 'Deal open at its previous stage.'),
   ('[Test BDM] Revise Rev 01 → Rev 02 → approve → send → accept.', 'Accepted on Rev 02 only; Rev 00/01 cannot be accepted.'),
   ('Quotation History tab.', 'Every step, with who, when and why, in order.')],
  'transition table; D-07; D-22')

# ════════════════════════════════════════════════════════════════════════════
# S16 — Pipelines, templates, industry, the Sales home
# ════════════════════════════════════════════════════════════════════════════
C('SAL-170', 'Pipelines & funnels', 'Create a pipeline and edit its stages', 'scenario', ['best', 'edge'],
  'MASTER_ADMIN', 'Stage names, probabilities and days allowed drive the forecast and stalled warnings.',
  'Signed in as Master Admin (pipelines are company setup).',
  [('Left menu → Sales → Pipelines & funnels → "Create one": Name "Export deals", What is it for = Opportunity funnel, Start from = a plain four-stage, tick "Make it the default for new work" → "Create it".', 'Listed with its stages; Default = yes; the previous default is no longer default.'),
   ('"Edit stages": rename a stage, set Probability 30 and Days allowed 14 → Save.', 'Saved; deals on that stage keep their place.'),
   ('"Add a stage": Name "Technical clarification", What it means = Still in play, Probability 40, Days allowed 7 → "Add it".', 'Added at the end; order can be changed.'),
   ('Probability 150 or -10.', 'Refused or capped — record what happens.'),
   ('Remove a stage that has deals on it.', 'Refused or the deals are protected — record what happens; deals must never vanish.'),
   ('Two stages both "Won — closes the deal".', 'Record what happens.'),
   ('As Test MktExec: open Pipelines & funnels.', 'Can view; cannot edit (pipe_can = company setup).')],
  'pipelines; pipe_can')

C('SAL-171', 'Pipelines & funnels', 'Stage approval rules (deal stage gates)', 'scenario', ['rule'],
  'MASTER_ADMIN', 'Who must agree before a big deal is called won or lost.',
  'Signed in as Master Admin.',
  [('Left menu → Sales → Approvals → "Approval rules" (page "Approval rules for deal stages") → "＋ New rule".', 'Form for a rule: value band, which stage it guards, approver.'),
   ('Rule "Won above ₹10 lakh" guarding the Won stage, approver Marketing Manager → save.', 'Listed with Value band, Guards, Approver, Used = 0.'),
   ('Use it (SAL-033), then come back.', 'Used = 1.'),
   ('Remove the rule.', 'Removed; deals already approved keep their history.')],
  'stage-gates')

C('SAL-172', 'Quote templates', 'Quotation and e-mail templates', 'scenario', ['best', 'edge'],
  'MARKETING_MANAGER', 'The covering letter and follow-up e-mails carry the company\'s voice.',
  'Signed in as Test MktMgr.',
  [('Left menu → Sales → Quote templates → "+ Add template" (Add quote / e-mail template).', 'Form with name, kind and body; a token reference lists the fields you can insert.'),
   ('Body with tokens {{quote_no}}, {{client_name}}, {{contact_name}} → save, then use "✉️ Chase" on a sent quotation.', 'The e-mail draft has the real quotation number, client and contact.'),
   ('A token that does not exist, e.g. {{nonsense}}.', 'Left as typed or removed — record what happens; never an error.'),
   ('Letterhead and signature (Document templates screen): upload a letterhead image and a signature.', 'The PDF uses them.'),
   ('As Test MktExec try to edit a template.', 'Refused unless they hold the template-manage right.')],
  'crm-templates; crm_fill_email')

C('SAL-173', 'Industry', 'Applying an industry template only adds pipelines; wording and accreditation are an administrator\'s', 'scenario', ['rule'],
  'several', 'Owner\'s answer D-29: a funnel test must never re-word the application or switch ISO/IEC 17020 off.',
  'Test workspace on Inspection. Test Director and Master Admin.',
  [('As Test Director: Left menu → Admin → Settings → Your industry → pick "Manufacturing & engineering".', 'Only "Add these pipelines" is offered; no "Make this our company\'s industry" panel.'),
   ('"Add these pipelines".', 'Message "… pipelines added. 3 pipelines created … Your wording and accreditation rules are unchanged." The inspection words and ISO/IEC 17020 rules are still in place.'),
   ('As Master Admin pick "Manufacturing & engineering".', 'A separate panel "Make this our company\'s industry" lists each change, e.g. "The inspection (ISO/IEC 17020) accreditation rules will be turned OFF." and a tick-box "I understand the changes listed above."'),
   ('Press "Make … our industry" without ticking.', 'Refused: "Tick the confirmation first — it lists what will change."'),
   ('Tick and confirm.', 'Wording changes; accreditation off; no pipeline created by this step.'),
   ('Pick "Inspection, testing & certification" and confirm.', 'ISO/IEC 17020 rules back on.')],
  'D-29', live=False)

C('SAL-174', 'Sales home', 'The Sales home and "Your next actions"', 'scenario', ['best'],
  'BUSINESS_DEV_MANAGER', 'The first screen a salesperson sees each morning must say what to do next.',
  'A few overdue follow-ups, leads and quotations waiting.',
  [('Left menu → Sales.', 'Page "Sales" with tiles: Leads, Opportunities, Inquiries, Quotes, Quote templates, Pre-order checklist, Project costing, Approvals, Pipelines & funnels, Set up Sales; and "Your next actions".'),
   ('"Your next actions".', 'Lists what needs doing next (for example overdue follow-ups, enquiries waiting for a quote, quotations waiting for my approval) — each one a link. Record what it shows.'),
   ('Open each link.', 'It goes to the exact record.'),
   ('The number of quotations "waiting for me".', 'Equals the list Quotes → awaiting my approval (including delegated and escalated ones, own branch only).')],
  'sales home; crm_quote_ids_awaiting_me')

C('SAL-175', 'Approvals', 'Add and edit an approval rule — every field', 'edge', ['edge'],
  'MARKETING_MANAGER', 'The rule form decides who approves what.',
  'Quote & report approvals → "+ Add rule" (page "Add approval rule").',
  [('Save with every field empty.', 'Refused with a message naming what is missing.'),
   ('Level 0 or -1.', 'Refused, or treated as level 1 — record what happens.'),
   ('A specific person who is deactivated.', 'Should not be offered, or refused — record what happens (a rule naming a person who has left blocks quotations).'),
   ('Edit an existing rule ("Edit") and change the band.', 'Saved; quotations submitted afterwards use the new band.')],
  'quote-approval-rule-new / -edit')
