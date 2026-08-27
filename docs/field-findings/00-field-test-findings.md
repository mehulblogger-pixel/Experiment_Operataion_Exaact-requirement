# Field-Test Findings Register (client walkthrough)

Raised from a live walkthrough. **Nothing is being changed yet** — this is the agreed checklist. We work
through it **one at a time, in an order you approve** (no simultaneous work). Point numbers match the
original list.

**Type:** 🐞 Bug · 🧭 UX/Flow · 🎯 Feature/Gap · ❓ Analysis-first (the "is this a bug? be brutal" ones).
**Sev (first-pass, to confirm):** P0 data-integrity/scope · P1 workflow-breaking · P2 important · P3 nice-to-have.
**Status:** ☐ open · ◐ in progress · ✅ done.

---

## A. Client registration / masters

| # | Type | Sev | Finding | Status |
|---|---|---|---|---|
| 1 | 🎯 | P2 | **No document upload** under Client Registration → Registration tab. | ☐ |
| 6 | 🐞 | P2 | **"Find on maps" / "Pick on app"** (site location picker) **not working**. | ☐ |

## B. Lead → Opportunity → Quotation (the sales spine)

| # | Type | Sev | Finding | Status |
|---|---|---|---|---|
| 2 | 🎯 | P2 | When a client has **no quote**, allow **adding a quote from the Purchase Order tab** under registration. | ☐ |
| 4 | ❓🐞 | P1 | **Opportunity dashboard** shows "still to generate quotation" **even though a quote was raised** (activity shows quotation submitted/won) — yet still offers "attach/generate new quotation". *Bug or intentional? → brutal analysis when we reach it.* | ☐ |
| 5 | 🐞 | P1 | A **lead moved to Opportunity/Deal** must transfer and **appear there**, and the board must update. Currently doesn't. | ☐ |
| 7 | 🎯 | P2 | **Lead generation:** choose **manday vs manmonth** deputation / technical manpower — how many, what skills/qualifications. **Manday → no site details; manmonth → site details required.** (Conditional intake.) | ☐ |
| 8 | ❓🐞 | P1 | **Lead stays "qualified", not "converted"** after the quote is submitted **and won** (quote is attached to the lead); and the **Opportunity doesn't show this lead as converted**. *Bug? → brutal analysis.* | ☐ |

## C. Contract

| # | Type | Sev | Finding | Status |
|---|---|---|---|---|
| 3 | 🎯 | P1 | **Add a contract:** "Qty sold" must be **multi-line** (multiple items — mandays / man-month / other); if **no quotation/line item exists, allow adding it inline**. Also add **Edit / Delete a contract** once created. | ☐ |
| 9 | 🧭 | P1 | After OM + BM **endorse the contract → jump to site address → no way back**; flow is confusing. Proposal: either add **"Raise new inspection call" under Jobs** in client registration, or (preferred) **open the other module as a modal/pop-up on the same screen** and, on save, show the added details **without a page refresh**. *Is inline/modal add feasible? (architecture decision).* | ☐ |

## D. Inspection call / job allocation

| # | Type | Sev | Finding | Status |
|---|---|---|---|---|
| 22 | 🐞 | **P0** | **INSPECTOR SCOPE LEAK.** A call allocated to **Mr. Vijay only** is also visible in **Mr. Ravi's schedule**. **✅ FIXED.** *Root cause (confirmed + reproduced):* the schedule queries filter correctly by `inspector_id`, but the **user-edit form let two logins be linked to the same team member** (`inspectors` row) — exactly the owner's "Add to Team Member" hypothesis — so both logins resolved to one person and saw their jobs. *Fix:* one team member = one login — a save-time guard (`inspector_login_conflict`) refuses linking a second login to an already-linked member, and a §7.11 integrity check (`inspector_one_login`) flags any pre-existing collision on `/data-control` + `/system-status`. Test `test_field22_inspector_login.php` (11 assertions), suite **3751/0**. *To clear existing bad data: give the second login its own team member; the integrity flag then goes green.* | ✅ |
| 12 | ❓→🎯 | P1 | **Cross-office scope.** Ahmedabad executes a Mumbai-office call. **✅ FIXED / DEFINED.** *Verified:* the executing office (Ahmedabad) **already sees** the call and job (calls are cross-office visible; jobs scope by executing office); the allocated inspector sees their job regardless of office. *Owner's rule (confirmed):* on a **cross-office job the executing office sees ONLY its inter-office credit + an Invoiced/Not-invoiced capsule (no amount)** — all other commercial (client billing, invoice value, revenue, profit) is **contracting-office only**. *Built:* `job_commercial_view($job)` → `FULL` (master / ALL / same-office / contracting office) vs `CREDIT_ONLY` (executing office on a cross-office job); the job Money tab hides the bills, profitability and invoice panels for `CREDIT_ONLY` and shows a compact **"Your inter-office credit"** capsule instead. Test `test_field12_cross_office_commercial.php` (10 assertions). Suite **3785/0**. | ✅ |
| 26 | 🐞 | P1 | **Second inspector / contractor (Ravi) not reflected** — not shown on the operational dashboard, during job allocation, etc. **✅ FIXED.** *Root cause:* the team-member INSERT used `$b['status'] ?? 'ACTIVE'`, which **does not coalesce an empty string** — a form posting `status=''` created a **blank-status inspector**, and every deputable surface filters on `status='ACTIVE'`, so he vanished from the allocate picker *and* the operational dashboard. (A SUBCON with an explicit ACTIVE status showed fine — verified.) *Fix:* the insert now coalesces empty → ACTIVE (`($b['status'] ?? '') ?: 'ACTIVE'`), and the inspector list + dashboard capacity queries treat a **blank status as active** (`COALESCE(NULLIF(status,''),'ACTIVE')='ACTIVE'`), surfacing the already-created Ravi; a genuinely-inactive person (real non-empty status) stays hidden. Test `test_field26_contractor_visible.php` (6 assertions). Suite **3791/0**. | ✅ |
| 25 | 🐞 | P2 | A call **allocated for the 28th shows in the backlog on the 27th** (future work counted as backlog), while also correctly in Schedule/Assignment registers as allocated. | ☐ |
| 10 | 🧭 | P1 | If something is missing on **New Inspection Call** and you click to add it, **you can't get back** to the inspection-call screen after adding. (Same return-navigation problem as #9.) | ☐ |
| 11 | 🐞🧭 | P1 | **Validation flow:** a missing field (e.g. **inspector not selected**) lets you proceed; only at "Allocate" does it bounce to the **first** screen with a highlight. It should **jump straight to the missing screen/field**. | ☐ |

## E. Report generation / IDEMS

| # | Type | Sev | Finding | Status |
|---|---|---|---|---|
| 13 | 🧭 | P3 | QA report opens with autofilled details, but the final button reads **"Create report & generate"** — should be **"Generate report"** first (label/flow). | ☐ |
| 14 | 🐞 | P2 | **"Improve wording"** returns *"couldn't reach writing assistance"* **even though AI is activated**. (AI integration/config.) | ☐ |
| 15 | 🎯 | P1 | **Prepared by** = the inspecting inspector automatically; **Reviewed by / Approved by** should be **automatic per the approval rules** — the system appears to **lack a way to set/route Reviewed-by & Approved-by**. | ☐ |
| 16 | 🎯❓ | P2 | Inspector needs to **download a draft copy** to review before sending for approval. **+ Question:** how is **vetting turned on/off** in the system? (Document the switch.) | ☐ |
| 17 | 🎯 | P3 | The report's **genuineness QR/scanner** should also **provide a download link** to the report when the page is opened. | ☐ |
| 18 | 🎯 | P2 | Add a block listing **applicable standards** (Standard Number, edition, etc.) as a **list** in each inspection report. | ☐ |

## F. Job close / hold points / expenses

| # | Type | Sev | Finding | Status |
|---|---|---|---|---|
| 19 | 🧭 | P2 | Once a report is **submitted, approved and locked**, the screen must offer **"Close the job"**. | ☐ |
| 20 | 🐞 | P1 | After issue, the job shows **hold points**; opening one asks close/waiver with a comment (good) — but on close/waive it **jumps back to the job main screen** and **closing one of several isn't reflected**. **✅ FIXED.** *Diagnosis:* the handler already redirects to `/job?id=X#holdpoints` and `hwp_close` correctly persists the status (verified: 2 open → close one → open 1, cleared 1). The bug was the **job screen's tab JS only understood a `#job=<tab-slug>` hash, not a bare element-id hash** like `#holdpoints` — so it opened the first tab (Overview) and the hold-points panel (on "Reports & QA") stayed hidden, reading as "main screen" and "not reflected". *Fix:* the tab init now **activates the tab containing a bare-id hash target and scrolls to it** (also opens a `<details>`), which fixes hold points *and every other in-page `#anchor` link on a tabbed screen*. Test `test_field20_holdpoint_nav.php` (9 assertions). Suite **3775/0**. | ✅ |
| 21 | 🐞 | P1 | Clicking **"Close the job"** shows the **expense sheet again**, letting you **re-enter expenses** — confusing/duplicative. **✅ FIXED.** *Diagnosis:* same root cause as #24 — the close/expense form re-appearing. #24's guard already stops the form re-showing (GET) and the second close (POST), and the success path already redirects to the job (not back to the form). #21's distinct concern is **duplicate expense rows**, and the closure-expense INSERT had **no data-layer guard**. *Fix:* record the day's closure expenses **exactly once per job** — the INSERT now runs only when no closure-expense row exists, so no path (a race, a re-submit that slips the UI) can double the engineer's claim. Test `test_field21_close_expense_once.php` (6 assertions; idempotent by construction). Suite **3766/0**. | ✅ |
| 24 | 🐞 | P1 | Even when a job is **already closed and expenses booked**, the **"Close job" button is not greyed out** and lets you **close again and re-enter expenses**. **✅ FIXED.** *Diagnosis:* the close buttons on every list/detail were already hidden by `closed_flag`, and the POST already refused a double-close — but the **GET `/job-close` form had no closed guard**, so reaching it on a closed job (stale page, back button, bookmarked form, 2nd tab, offline re-send) re-showed the expense sheet. *Fix:* one `closed_flag` guard **before the POST branch** short-circuits both GET and POST — a closed job never shows the form and never re-files expenses; message points to edit-expenses / Unlock. Test `test_field24_job_close_once.php` (9 assertions). Suite **3760/0**. | ✅ |

## G. Attendance / Mark IN–OUT

| # | Type | Sev | Finding | Status |
|---|---|---|---|---|
| 23 | 🎯 | P2 | **Mark IN / Mark OUT must be blocked when the schedule date doesn't match** (no marking a job on the wrong date). | ☐ |

## H. Repeat-inspection carry-forward (vendor)

| # | Type | Sev | Finding | Status |
|---|---|---|---|---|
| 27 | 🎯 | P2 | For a call on a contract/quotation, **show the last inspection at the same vendor (by date)**. Allow **forwarding the previous report to the next inspector**, who can either **continue from it (number increments 1 → 2 …)** or **create a fresh report** — their choice. **Carry the QAP forward.** If a **new/revised QAP** or an added item arrives on the inspection day, allow **uploading a new QAP that autofills/overwrites the scope** (with **hold points retained**) — overwriting **only after a pop-up confirmation**. (Large, multi-part.) | ☐ |

---

## Suggested working order (to confirm — you decide)

I'd sequence by risk, then by how many other points a fix unblocks:

1. **#22 inspector scope leak (P0)** — a segregation defect; fix first and re-verify against the §51/§35 scope work.
2. **Job-close integrity — #24, #21, #20** — close idempotency + the expense re-entry + hold-point navigation/state (they cluster).
3. **Allocation & scope — #12 (define the rule), #26 second inspector, #25 backlog date, #11 validation routing.**
4. **Navigation architecture — #9 / #10** — decide modal/inline-add vs return-links (one decision fixes both, and eases many others).
5. **Sales spine analysis — #4, #8 (brutal analysis), #5 lead→opportunity transfer.**
6. **Report features — #15 reviewed/approved routing, #16 draft + vetting switch, #18 standards block, #14 AI, #13 label, #17 QR link.**
7. **Intake & masters — #7 manday/manmonth intake, #1 doc upload, #2 quote-from-PO, #3 contract multi-line + edit/delete, #6 map picker, #23 mark-in date guard.**
8. **#27 repeat-inspection carry-forward** — largest; do last, once the report + call plumbing above is settled.

**Rules we keep (from the program):** non-destructive; no new permission without asking; test-backed; docs
in lockstep; and for anything touching money figures, explicit sign-off. Each item ships as its own change
with its own test and a status flip here.

*Register created — nothing changed. Tell me which item to start with (or approve the order above) and we
take them strictly one at a time.*
