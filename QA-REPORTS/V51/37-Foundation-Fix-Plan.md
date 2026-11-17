# Foundation fix: what exactly will change (for your OK before any coding)

17 Nov 2026 · Answer sheet 3, **themes 1 and 2 only** · **Nothing has been changed yet.**

You approved *"themes 1–14 as proposed"*. As your standing rule asks, this explains in detail, with examples, what the first batch (week 1, the "foundation fix") will change. That way it is right the first time.

**What this batch covers:**
- **Theme 1:** another office can act by typing an address, although the button is hidden.
- **Theme 2:** closed, issued or decided records can still be changed.
- **Every module except expense vouchers**, which are being rebuilt separately (section 2.8).

**How I checked:** I read the handler behind every one of the 50 faults (24 in theme 1, 26 in theme 2) in today's program. The code has not changed since the tested release 74a263f, apart from the deploy-check stamp. **All 50 faults are still there.** None is fully fixed. Three are partly fixed (section 7).

**How to answer:** *"Foundation plan OK; Q7, Q9, Q10 and N1–N3 as recommended."* Or name the item you want differently.

---

## 1. The two causes, and one permanent fix for each

### Cause 1: the button is hidden, but the action behind it does not check the office

Every change goes through two places:
1. **the screen**, which decides which buttons to draw;
2. **the action**, which makes the change when the button is pressed.

The lists and record pages already check the office. Many actions check only the **role**. So if someone types the address, or uses an old tab or a saved form, the change goes through.

Worse, the office check often comes only **after** saving. The action saves, then opens the record, and the record page says "outside your office / branch scope". By then the change is already made.

*Example (EC-MON-039):*
- Test Coord2 (Mumbai) cannot see Ahmedabad's invoices: the list shows 0 rows.
- He typed the receipt address and pressed Unmatch, then typed a match of ₹11,800.
- Result: Ahmedabad invoice **AHM/2627/0002 went from Paid to Issued**, and **AHM/2627/0001 became Paid**.

**The fix: one office check at the door of every action, before anything is saved.**
- **The rule is the one the list already uses.** If the list does not show you a record, no action can change it. No new rule is written, and nobody gets a new right.
- **The door finds the real record.** Some actions name a part of a record: a matching row (→ its receipt and invoice), a follow-up (→ its quotation), a timesheet row (→ its deputation), a lead number typed into a form (→ that lead). The door checks the office of the record that part belongs to. Today several doors read the wrong number, which is how F249, F259 and F260 got through.
- **The refusal is the sentence you already know:** *"This invoice is outside your office / branch scope."* Nothing is saved.
- **Company-wide roles are not affected.** Master Admin, Admin and Business Director, the Business Unit Head inside their own unit, and Finance or a Marketing Manager with all offices ticked work exactly as today.

**Reuse Gate (your rule of 17 Nov):**

| Existing piece | What I do with it |
| --- | --- |
| The office rules of the lists and record pages: `scope_allows()`, `scope_office_allows()`, `call_exec_scope_allows()`, `idems_doc_access()`, `partner_view_level()` | **Reused unchanged.** The door asks exactly these, so a list and its actions can never disagree |
| The "one door per module" that eight modules already have: leads, deals, quotations, complaints, requisitions, candidates, costings, hiring requests | **Same pattern, extended** to the modules that have none. The eight existing doors are moved onto the same list and behave exactly as now, so there is **one** door, not nine |
| Branch batch B2 (16 Nov): "check the office before saving" on work orders | The same rule for every action |
| The program's list of which module each address belongs to (`ops_module_gate`) | One more column: **which record the address changes, and by which number** |

- **What is new:** only that column, one line per action.
- **Why nothing existing could do it:** the eight doors each read only the number called `id`. Many actions name their record differently (`quote_id`, `job_id`, `receipt_id`, `alloc_id`, `lead_id`, a follow-up number).
- **Upkeep:** one line for each new action. The automatic test fails if an action that saves is missing from the list (section 5), so a future button cannot slip through.

### Cause 2: the screen goes read-only, but the action still accepts the change

When a record is closed, issued or decided, the screen hides its buttons. The action itself never asks the record's status. An old browser tab, or a typed address, still changes it. Sometimes it moves the record to a status your lifecycle does not allow.

*Example (EC-QUAA-024):*
- A nonconformity was closed on 17 Nov 2026.
- From an old tab, its disposition was changed to VOID, its containment to "changed after close", and its owner and date were changed.
- It still says **Closed**.

**The fix: one question before every change, "is this record still open for this change?"**
- **The answer comes from the statuses that already exist.** Where `docs/03` describes the record, from `docs/03`. Where it does not yet, from the record's own existing statuses (see N1).
- **No new status is added.**
- **The refusal is one plain sentence:** what the record is, then the existing way forward. For example: *"This nonconformity is closed. Reopen it first."*
- **Corrections only through the ways that already exist:** reopen, a new revision, a credit note, a new attendance period, a follow-up corrective action, a contract amendment.
- **Some things deliberately stay allowed after closing:**
  - viewing, printing, PDFs and downloads;
  - uploading documents and photos on a locked job;
  - marking a completed review's decisions done (their reminders must be able to stop: F174);
  - raising a follow-up corrective action.

**Reuse Gate:**

| Existing piece | What I do with it |
| --- | --- |
| `crm_quote_transition_problem()`: quotation moves, enforced since 20 Oct | Reused. "Lost" is limited to the moves drawn in `docs/03` |
| `tosrm_can_transition()`: closed, cancelled and rejected work orders have no exit | Reused for allocating a job |
| `connect_engage_can_transition()`: Marketplace bookings | The booking edit form asks it too, not only the status buttons |
| `idems_can_edit_doc()`, `IDEMS_REOPEN_STATES`: the report's own edit and send-back rules | Reused for instruments, delete and the vetting return |
| `quote_is_locked()`, `job_lock_block()`, `capa_is_open()`, `tapi_period_locked()` | Reused as they are |
| Existing refusal sentences, e.g. *"This booking has ended, so it can no longer be changed."* (client portal) and *"That request has already moved on."* | Reused word for word wherever they fit |

- **What is new:** one short list per kind of record, saying which statuses are closed, which actions stay allowed, and the sentence to show.
- **Why it is needed:** 10 kinds of record (nonconformity, corrective action, complaint, internal audit, management review, breach, system failure, identity document, contract exception and attendance period) have no such check at all.
- **Upkeep:** one line per kind of record.

**Neither fix adds a permission, a status, a table, a column or a screen.** Both only ever **refuse**, and only what your permission list and lifecycles already forbid.

---

## 2. What changes, module by module

Each row is one action (a button, or the address behind it). The number (MON-1 …) is the row in **Appendix A**, which also names the handler and the existing check that is reused. "Today" is what the edge-case helpers saw on a private copy.

### 2.1 Invoices & payments: 14 actions

| Action | Today (case) | After |
| --- | --- | --- |
| Customer ledger (MON-1) | Test Coord2 (Mumbai) typed `/ledger?id=…` and read all **12 entries** of Ahmedabad client TB Refinery, closing balance **−₹35,449.40** | Refused with your existing client rule: *commercial details are the owning office's only*. Ahmedabad and company-wide roles see it as today |
| Match / unmatch money (MON-2, 3) | AHM/2627/0002 Paid → Issued, AHM/2627/0001 → Paid, by Test Coord2 | Refused unless both the receipt and every invoice are in your offices. Nothing is matched or unmatched |
| Job "manual invoice entry" (MON-4) | Test Coord2 set an Ahmedabad job to invoice **EC-MUM-HACK, ₹1, payment received** | Refused (the job's office). *Whether this old box should exist at all is theme 9 (F071).* |
| Job billing: terms, end early, add a charge, release a unit, charge a suspension (MON-5–9) | Test Coord2 ended Ahmedabad job EC-JOB-J01 early (**Terminated 15 Nov 2026**) and changed its terms | Refused (the job's office) |
| Draft invoice: add line, remove line, issue, cancel (MON-10–13) | *Not in the edge book.* Found while reading: these actions check only the role | The same rule as the invoice page: the invoice's office. A draft with no office yet stays Finance's work, as the page treats it |
| Cancel an invoice already sent to Tally (MON-14) | Cancelled with no warning; Tally keeps the ₹1,180 sale | **Your Q7** (section 3). Until you answer: unchanged |

### 2.2 Work orders, jobs & deputations: 31 actions (4 wait for an answer)

| Action | Today (case) | After |
| --- | --- | --- |
| Delete a work order (OPS-1) | Test BAM (Ahmedabad) deleted a **Mumbai** work order and its job | Only the office that runs it (the branch batch B2/B3 rule) |
| Remove a job expense line (OPS-2) | Test Coord2 removed an Ahmedabad Travel ₹100 line **just by opening a link** | Only the job's office. Only with the Remove button and its "Remove this line?" question; opening a link never deletes |
| Reopen a locked job (OPS-3) | Test BranchMgr2 (Mumbai) reopened an Ahmedabad job, **saved**, then saw "outside scope" | Refused before saving |
| Deputation panel, 8 actions: status, checklists, site register, timesheet, attendance periods (OPS-4–11) | Test Coord2 set the TB Refinery deputation (3 man-days) to **Cancelled** and added an **8 h timesheet row** | Refused before saving. A checklist item or attendance period must also belong to that deputation |
| Contract exceptions: list, request, endorse, refuse (OPS-12–15) | Test BranchMgr2 **endorsed an Ahmedabad exception**; Test Coord2 and Finance saw it | Only exceptions on quotations of your offices are listed and can be acted on. The Super Admin's grant is unchanged. *The Branch Application Manager being offered Endorse is theme 3.* |
| Recurring schedules: list, create, pause, Generate due now (OPS-16–19) | Ahmedabad created a **Mumbai** schedule; "Generate due now" made **2 work orders**, one of them Mumbai's | Only schedules of your offices. "Generate due now" runs only yours. The nightly run is unchanged |
| Set someone's availability (OPS-20) | Test Coord put Mumbai's **Test Inspector2 on leave** | Only people of your offices, the same rule as the availability board |
| Attendance reconciliation (OPS-21) | Ahmedabad saw Mumbai's Test Inspector2: **20 present / 2 leave** | Only people of your offices |
| Work-order form's quotation pickers (OPS-22, 23) | Test Coord read Mumbai's **EC-Q-0002 · ₹2,24,200 · 20 man-days @ ₹9,500** | Only quotations the quotation list shows you |
| Reassign / reschedule a **closed** job (OPS-24, 25) | JOB-A, closed 12 Nov, reassigned to Test SrInspector, no reason | *"This job is closed. Its assignment can no longer change."* |
| Allocate on a work order **Closed / Cancelled / Rejected** through its status (OPS-26) | JOB-00003 allocated; assignment e-mail sent | The existing sentence: *"This work order is closed — it cannot be allocated again. Raise a new work order for further work."* No e-mail |
| An **Approved** attendance period put back (OPS-27) | Approved → Draft accepted, while its **3 man-days stayed billable** | *"This attendance period was approved by the client. For a correction, add a new period."* Reversed and duplicate periods come with themes 6 and 5 |
| Refuse a **granted** exception (OPS-28) | Turned to Refused; who granted it was overwritten | **Your Q10.** Until you answer: *"That request has already moved on."* |
| Contract openings: list, first signature, final approval (OPS-29–31) | Ahmedabad signed and opened Mumbai's **MUM/C/2026-27/00001** | **Waiting for Sales A1 / C2** (section 3) |

### 2.3 Inspection reports & endorsements: 13 actions

| Action | Today (case) | After |
| --- | --- | --- |
| Approve / reject / send back (REP-1) | Test BranchMgr2 (Mumbai) made an Ahmedabad report **"fully approved"** | **Your Q9**: only the report's own offices |
| Finalise & issue (REP-2) | Test BranchMgr2 issued **MGH/AHM/2026/TBREFI/IR/000002**; the client was notified | Q9, as above |
| Vet / return / debrief (REP-3) | Another office vetted and forwarded an Ahmedabad report | Q9, as above |
| "Return for correction" at vetting on a report **Under review** (REP-4) | Sent it to Draft and deleted its approval steps | Only while the report is **At vetting**, as `docs/03` draws it. Under review it goes back only through the approver's send-back or the executing office's "send back to inspector" |
| Delete a report: office (REP-5) | Any Finalise holder, any office | Q9, as above |
| Delete an **issued** report (REP-6) | Deleted with no reason; the client's verify page then said *"We do not recognise that code"* | *"This report has been issued. It is never deleted — start a new revision to correct it."* Deleting a draft asks for a reason. *There is no Delete button on the report page today; this is reachable only by a typed request* |
| Add / remove an instrument (REP-7, 8) | EC-UT-01 added to, and one removed from, **issued EC-IRN-0002** | The same check as the report's own Edit: your office, your report, and Draft or Sent back only |
| Endorsements: open, edit, submit, endorse, delete (REP-9–13) | Test Coord (Ahmedabad) **edited Mumbai's endorsement** MGH/MUM/2026/TBSTEE/END/000001 | The endorsement list's office rule, already used for its files. *Who may endorse one assigned to someone else is Reporting C2, still waiting.* |

### 2.4 Quality & accreditation: 35 actions

| Record | Today (case) | After |
| --- | --- | --- |
| Nonconformity: office (QUA-1–7) | Test BranchMgr2 opened an Ahmedabad record the register hides, set owner "Mumbai BM", changed it to OFI | The register's office rule on the page and on all 6 actions |
| Nonconformity **closed** (QUA-2–6) | Changed after close; **closed twice**, the close note overwritten | *"This nonconformity is closed. Reopen it first."* |
| Reopen a nonconformity **not closed** (QUA-7) | A new Open one jumped to **"Disposition agreed"** with nothing recorded | Reopen only from Closed: *"This nonconformity is not closed, so there is nothing to reopen."* |
| Corrective action (CAPA) **closed** (QUA-8–18) | CAPA-2026-001, closed "it worked", flipped to **"did not work"** while still Closed; closed twice | *"This corrective action is closed. To do more, raise a follow-up action from it."* *Found while checking: the CAPA page has no office check; it gets the CAPA register's rule.* |
| A CAPA's actions (QUA-11–13) | "Done" accepted twice; an action ticked through **another CAPA's page** | An action already done is not done again; an action must belong to the CAPA on whose page it is pressed |
| Complaint **closed** (QUA-19–25) | CMP-2026-001: outcome Upheld → **Not upheld**, CAPA reference blanked, still Closed | *"This complaint is closed. Reopen it first, with a reason."* Reopen only from Closed. *The complaints module already has its office door.* |
| Breach **closed** (QUA-26) | CB-2026-001: "party told" blanked after closing | *"This breach is closed. Nothing on it can change now."* |
| System failure **resolved** (QUA-27) | Set back to "not answered" while still Resolved | *"This failure is marked resolved. Nothing on it can change now."* |
| Internal audit **closed** (QUA-28–30) | **IA-2026-002**: a Major nonconformity added after closing; a finding that raised **CAPA-2026-001** removed | *"This audit is closed. A new finding goes on the next audit."* A finding that raised a CAPA is never removed, on open audits too |
| Management review **complete** (QUA-31–34) | **MR-2026-02**: chair blanked, still Complete | *"This review is complete. Its decisions can still be marked done."* Marking a decision done and raising a CAPA from it stay allowed |
| Identity document **redacted** (QUA-35) | *"Recorded: a copy went to EC old tab plant"* for a copy that no longer exists | *"This copy was redacted on <date>. There is no copy left to send."* |

### 2.5 Sales: 9 actions

| Action | Today (case) | After |
| --- | --- | --- |
| Attach a quotation to a deal (SAL-1) | Test BDM (Ahmedabad) attached Mumbai's **Q-MUM-EC1 (₹9,16,860)** and then saw its status | Only quotations the quotation list shows you |
| Detach the **accepted** quotation from a won deal (SAL-2) | Q-00004 detached; Finance then registered **AHM/C/2026-27/00001** from the deal, while Q-00004 still waited for its own contract: **two contracts for one sale** | *"Q-00004 was accepted for this deal — it cannot be detached."* (your "one sale, one contract") |
| "Work this lead as a deal" (SAL-3) | Test KAM (Mumbai) made **OPP-2026-0006** from Ahmedabad's **already-won** lead L-2026-0002 | The lead's office rule. A won lead: *"This lead is already won — customer."* A lost one: *"Reopen it first."* |
| Billing schedule (SAL-4) | Test KAM saved **"Advance 100%"** on Test BDM's Ahmedabad quotation; Test MktExec (outside Test BDM's line) saved 50/50 | The same two checks as the quotation's own Edit: its office, and your 14 Nov "assigned work" rule |
| Follow-up e-mail download (SAL-5) | Test KAM downloaded an Ahmedabad follow-up e-mail (client address buyer@tb-client.test) | The door reads the follow-up's own quotation, not a wrong one |
| Remove a file from a **sent** quotation (SAL-6) | EC-PO-0001.pdf removed by a typed request | The existing sent-quotation sentence: what the client holds does not change |
| Add a file or PO number to a sent quotation (SAL-7) | late-po.pdf added; PO changed to EC-PO-0002 | **Waiting for Sales A16** (section 3). Until you answer: unchanged |
| Revise an **accepted** quotation (SAL-8) | The won order **left Accounts' "Awaiting a contract number" list** | *"The client has accepted this quotation, and that is final. To change what was agreed, raise a contract amendment; to correct a mistake on the quotation, ask for a re-edit."* |
| Mark Lost from Approved / Pending / Rejected / Expired (SAL-9) | Approved → Lost accepted | Only from Draft and Sent, as `docs/03` draws it. **N2** asks whether you want more |

### 2.6 Marketplace: 1 action

| Action | Today (case) | After |
| --- | --- | --- |
| Booking edit form (MKT-1) | A **Completed** booking was put back to **Booked** ("Booking updated.") | The form keeps the booking's status. Status moves only through the status buttons and the lifecycle. A completed or cancelled booking: *"This booking has ended, so it can no longer be changed."* (the portal's existing sentence). Booking again after a cancellation still makes a new linked booking, as today |

### 2.7 Insights: 2 actions

| Action | Today (case) | After |
| --- | --- | --- |
| Take a month's snapshot (INS-1) | **2027-03** (a future month) frozen; **2026-10** taken four times by four roles, with no warning | A month not yet ended is refused. Taking a month again asks first: *"A snapshot of 2026-10 was taken on 17 Nov 2026 by Test Director. Take it again and replace it?"* Final and Locked months stay refused, as today. *Who may use this screen is theme 3 (F028).* |
| Change a period's state (INS-2) | Any state to any state | **N3** |

### 2.8 Expense vouchers: not in this batch

- **No theme 1 or 2 fault is on a voucher screen.** The voucher office rule, the month lock and "approvers never edit" are rebuilt in the expenses module this week (document 36). They are tested there with the same automatic test (section 5).
- **Two faults here touch voucher-related data** and are covered by this batch:
  - **OPS-2** (F107): old job expense lines. After the rebuild they stay as history, but they must still be safe.
  - **OPS-21** (F116): attendance reconciliation reads the voucher day rows.

  I will build both on the voucher lines the rebuild keeps, so the two pieces of work do not collide.

---

## 3. Questions for you

**I will not decide these myself.** Each has my recommendation in **bold**.

### Asked on sheet 3, still to answer

| # | Question | **Recommended** | Until you answer |
| --- | --- | --- | --- |
| Q7 | An invoice **already sent to Tally** is cancelled (F082, MON-14). Tally is one-way (Money D5) | **Ask first: *"This went to Tally on 17 Nov 2026. Cancel here and reverse it in Tally by hand?"* It then stays on the Tally screen's existing "Show what has already gone" list, marked *"Cancelled here — reverse in Tally"*, instead of disappearing.** No new screen. Please check with your accountant | Cancel works as today |
| Q9 | May **another office approve, vet, issue or delete** a report? (F210, F211, F216, F212; REP-1, 2, 3, 5) | **No: only the report's issuing, executing and contracting offices, plus company-wide roles.** A person **named** in the report's chain (or their delegate) still signs from any office. A step for "any Branch Manager" goes only to Branch Managers whose offices reach the report | Those 4 actions stay as today |
| Q10 | May a **granted contract exception be withdrawn**? (F127, OPS-28) | **Yes, by whoever granted it or a higher level, with a reason.** It moves to the existing Refused status. The grant's name stays, and the withdrawal is a separate History line (who, when, why). Allocations already made under it stay. This is a new move in the exception's lifecycle, so I write it into `docs/03` | *"That request has already moved on."* |

### Asked in earlier packs, still waiting (not asked again)

| Item | Faults | What waits |
| --- | --- | --- |
| **Sales A1 + C2**: contract openings across offices | F033 + F113 | OPS-29–31. With *"A1 yes, C2 as recommended"*, Branch and Operation Managers sign only their own offices' contracts, while the Business Unit Head and Director sign for all theirs. They use the same door, in this batch |
| **Sales A16**: documents after sending | F261 | SAL-7. Removing a file is refused in this batch either way (SAL-6) |
| **Reporting C2**: endorsing a document assigned to someone else | F214 (part) | Only the office half is in this batch |
| **Operations A4** | F123 | Your theme-2 yes already covers it (OPS-26). A10's double-booking warning stays in the Operations pack |

### New, found while planning

| # | Question | **Recommended** |
| --- | --- | --- |
| N1 | `docs/03` does not yet describe 10 records: nonconformity, corrective action, complaint, internal audit, management review, breach, system failure, identity document (redaction), contract exception, client attendance period. Their statuses exist only in the program. May I **write their existing statuses into `docs/03`**, exactly as the program has them, so "closed" has one written meaning? | **Yes.** Writing them down adds no status. Example: *Corrective action: Open → In progress → Waiting to see whether it worked → Closed (it worked) / Closed as ineffective.* |
| N2 | Quotations marked **Lost**: `docs/03` allows only Draft → Lost and Sent → Lost. Meanwhile the program offers **Revise on a Lost quotation**, and even says *"if the client comes back, raise a revision"*, but `docs/03` shows Lost as final | **Allow Expired → Lost** (an expired offer that died is lost business). **Keep Revise on Lost** (the client came back). Write both into `docs/03`. Approved, Pending and Rejected stay refused: withdraw or revise first. *Without your yes, only Draft and Sent can be marked Lost, and Revise on Lost stays as today* |
| N3 | An analytics period can be moved from **Locked back to Open** by anyone who can open the screen, and then re-frozen | **Forward only (Open → Under review → Final → Locked).** An administrator may move **Final back to Under review**, with a reason. **Locked is never reopened.** *Without your yes, the period states stay as today* |

---

## 4. What does NOT change

- **No permission, no status and no screen is added.** Who may do what, by role, is exactly your permission list. The buttons are drawn as today.
- **No table, column or stored record is changed by the update.** Nothing is reopened, re-closed or rewritten. All history stays.
- **Lists and registers show the same rows as today.** They already follow the rule.
- **Company-wide roles** work as today. Nightly runs (reminders, the Tally export, expiry of quotations) are unaffected, because nobody is signed in.
- **A record with no office** is treated exactly as its list treats it today. Theme 11 (next batch) fixes "blank means Ahmedabad". The door then follows automatically, because it asks the same rule.
- **Still allowed on closed records:** viewing, printing, PDFs, downloads, and the existing ways forward (reopen where it exists, new revision, credit note, new period, follow-up CAPA, contract amendment). Also uploading documents and photos on a locked job, and marking a completed review's decisions done.
- **The eight existing module doors** (leads, deals, quotations, complaints, requisitions, candidates, costings, hiring requests) behave exactly as today. Their tests must pass unchanged.
- **Untouched:** approval chains, amounts, numbering, money calculations, vouchers, recruitment, the client and vendor portals.

---

## 5. How it is tested

1. **The "wrong office" test (new, automatic).**
   - **Who it plays:** Mumbai-only people (Coordinator, Branch Manager, Finance with Mumbai ticked, KAM).
   - **What it does:** sends **every action in Appendix A** for an Ahmedabad record, as a typed request, the way the helpers did.
   - **What it checks:** each one is refused with its sentence, and **the database is unchanged** (every row compared before and after).
   - **It also checks nobody is locked out:** the same action by Ahmedabad's own people works, and so does it by Master Admin, Admin and Director.
2. **The "closed record" test (new, automatic).**
   - **What it does:** puts each kind of record into each of its closed statuses, then sends every change action from an "old tab".
   - **What it checks:** each is refused, nothing changes, and the actions that stay allowed still work.
3. **The "nothing forgotten" check.** The test reads the program's full list of addresses. Any address that saves and is missing from the door list fails the test. So a button added next month cannot slip past.
4. **The full suite** (about 21,000 checks) runs on both database types, including the existing office tests (`test_scope_playbook`, `test_p2_idor_scope`, the branch batch tests). They must pass unchanged.
5. **The robot** reruns all 50 theme 1–2 edge cases and the robot tests of every affected module, before and after.
6. **Your acceptance check on opstest:** 12 checks, about 30 minutes, in the report that comes with the zip. Their content:

| # | Sign in as | Do | You should see |
| --- | --- | --- | --- |
| 1 | Test Coord2 | Type the ledger address of an Ahmedabad client | Refused |
| 2 | Test Coord2 | Unmatch money on an Ahmedabad receipt | Refused; the invoice stays Paid |
| 3 | Test Coord2 | Send "Cancelled" for an Ahmedabad deputation | Refused; status unchanged |
| 4 | Test BranchMgr2 | Reopen a locked Ahmedabad job | Refused; still locked |
| 5 | Test BAM | Delete a Mumbai work order | Refused; it stays |
| 6 | Test Coord (Ahmedabad) | Open a Mumbai endorsement address | Refused |
| 7 | Quality Head | Close a nonconformity, then save its disposition from an old tab | *"This nonconformity is closed. Reopen it first."* |
| 8 | Quality Head | Same with a closed CAPA's plan | Refused |
| 9 | Test SrInspector | Add an instrument to an issued report from an old tab | Refused |
| 10 | Test BDM | Detach the accepted quotation from a won deal | Refused |
| 11 | Test BDM | Revise an accepted quotation | Refused, with the way forward |
| 12 | Test Coord | Everyday work in your own office (allocate, close a job, match money) | Works exactly as before |

---

## 6. Rollback

- **Before uploading:** take a backup, as always.
- **To undo:** upload and extract the previous zip.
- **Why that is enough:** this batch changes **no table and no stored record**. The previous program works on the same data with nothing to undo.
- **Still opstest only.** Nothing goes to the live site until you say testing is complete and answer yes to *"Shall I prepare the live update?"*

**Documents updated in the same change:**
- `docs/02`: one note saying every action now checks the office before saving, using the list's rule, and that no right changed.
- `docs/03`: the moves now enforced (REP-4, SAL-9, OPS-27, MKT-1), plus whatever you answer to N1, N2, N3 and Q10.

---

## 7. Findings found partly fixed in today's code

- **F108:** the deputation **list** was limited by office in branch batch B3, but its actions were not.
- **F210 / F211:** approving now works **only while a report is Under review** (DEF-REP-016, 17 Nov), but the office is still not checked.
- **F141:** since 16 Nov (R3), only the Quality / Technical Head contains a nonconformity or agrees its disposition. The page, "assign" and "raise a CAPA" are still not office-checked.
- **F123:** allocation is refused on a work order whose old status is Closed, but **not** on one closed through its current status.

**None of the 50 is fully fixed.**

---

**One-line answer:** *"Foundation plan OK; Q7, Q9, Q10 and N1–N3 as recommended."*

---

## Appendix A: every action in this batch (105)

**How to read it:**
- **"Existing check reused"** is the check the program already applies to the list or record page. The door asks this check before saving.
- **"Closed check"** is the theme 2 question.
- File and line numbers are today's program, `phpapp/lib/`.

### Invoices & payments (14)

| # | Action (address) | Fault | Handler | Office check reused | Closed check |
| --- | --- | --- | --- | --- | --- |
| MON-1 | Customer ledger and its CSV (`ledger`) | F069 | booksui.php:397 | `partner_view_level()` = full (client's own office) | — |
| MON-2 | Match money (`receipt-allocate`) | F070 | booksui.php:369, books.php:890 | receipt: `scope_office_allows()` (receipts list); each invoice: `scope_allows()` (invoice page) | — (cancelled and draft invoices are already refused) |
| MON-3 | Unmatch (`receipt-unallocate`) | F070 | booksui.php:382, books.php:962 | the matching row → its receipt and invoice, as MON-2 | — |
| MON-4 | Manual invoice entry on a job (`job-invoice`) | F072 | ops.php:8813 | job page: `scope_allows(job office, unit)` | — |
| MON-5–9 | Job billing: terms, end early, add charge, release unit, suspension (`job-billing`) | F073 | billunits.php:822 | job page rule | — |
| MON-10–13 | Draft invoice: add line, remove line, issue, cancel | found while reading | booksui.php:285–312 | invoice page rule (`scope_allows` when the invoice has an office) | existing status checks kept |
| MON-14 | Cancel an invoice sent to Tally (`invoice-cancel`) | F082 | books.php:783 | — | **Q7** |

### Work orders, jobs & deputations (31)

| # | Action (address) | Fault | Handler | Office check reused | Closed check |
| --- | --- | --- | --- | --- | --- |
| OPS-1 | Delete a work order (`call-delete`) | F114 | ops.php:5536 | `call_exec_scope_allows()` (B2/B3) | — |
| OPS-2 | Remove a job expense line (`expense-delete`) | F107 | ops.php:8891 | job page rule; the button only (the address alone refuses, as `job-unlock` already does) | — |
| OPS-3 | Reopen a locked job (`job-unlock`) | F115 | joblock.php:206 | job page rule | — |
| OPS-4–11 | Deputation: status, checklist seed, checklist item, site register entry, close entry, timesheet, submit period, period status (`dep-*`) | F108 | pdso.php:882 | `tosrm_job_scope_ok()` (B2); item and period must belong to the job | OPS-27 |
| OPS-12–15 | Contract exceptions: list, request, endorse, refuse (`contract-overrides`, `contract-override`) | F112 | contracts.php:1322, 1400 | quotation rule: `scope_allows(quotation office, unit)` | OPS-28 |
| OPS-16–19 | Recurring: list, create, pause / resume, Generate due now (`recurring`) | F118 | tosrm.php:1880 | `scope_allows(schedule's executing office)`; the form offers only your offices | — |
| OPS-20 | Set availability (`availability`) | F117 | workforce.php:638 | the person's home office in your offices (the board's own rule, workforce.php:220) | — |
| OPS-21 | Attendance reconciliation (`attendance-recon`) | F116 | ops.php:9638 | as OPS-20 | — |
| OPS-22, 23 | Quotation pickers (`client-quotes`, `quote-context`) | F121 | ops.php:961, 4711, 4728 | quotation list rule (`scope_clause` / `scope_allows`) | — |
| OPS-24, 25 | Reassign / reschedule (`assign-reassign`, `assign-reschedule`) | F122 | tosrm.php:760, 772 | already B2 | job closed → refuse |
| OPS-26 | Allocate a job (`job-new`) | F123 | ops.php:8461 | already `call_can_allocate()` | `tosrm_can_transition()` terminal states (Closed, Cancelled, Rejected) |
| OPS-27 | Attendance period status (`dep-approval-status`) | F109 | pdso.php:566, 944 | as OPS-4 | Approved / Locked never move back |
| OPS-28 | Refuse an exception (`contract-override` refuse) | F127 | contracts.php:1389 | as OPS-12 | decided (Approved / Refused) → **Q10** |
| OPS-29–31 | Contract openings: list, first signature, final approval | F033 + F113 | contracts.php:792, 798, 1172, 1200 | **waits for Sales A1 / C2** | — |

### Inspection reports & endorsements (13)

| # | Action (address) | Fault | Handler | Office check reused | Closed check |
| --- | --- | --- | --- | --- | --- |
| REP-1 | Approve / reject / send back / delegate (`document-approve`) | F210 | idems.php:7150 | `idems_doc_access()` (**Q9**) | already: only while Under review |
| REP-2 | Finalise & issue (`document-finalize`) | F211 | idems.php:5293 | as REP-1 (**Q9**) | already: needs Approved |
| REP-3 | Vet / return / debrief (`document-vet`) | F216 | idems.php:7295 | as REP-1 (**Q9**) | already: refused once issued |
| REP-4 | Return for correction at vetting | F217 | idems.php:7340 | — | only while At vetting (`docs/03`) |
| REP-5 | Delete a report: office (`document-delete`) | F212 | idems.php:5540 | as REP-1 (**Q9**) | — |
| REP-6 | Delete a report: issued | F212 | idems.php:5540 | — | Issued → refuse; draft needs a reason |
| REP-7, 8 | Add / remove an instrument (`report-equip-add`, `report-equip-del`) | F187 | equipment.php:410, 416 | `idems_can_edit_doc()` (office + own report) | the same function: Draft / Sent back only |
| REP-9–13 | Endorsement: open, edit, submit, endorse, delete | F214 | idems.php:9759, 9838–9900 | `scope_allows(endorsement office, unit)`, as its files (idems.php:9736) | existing locks kept |

### Quality & accreditation (35)

| # | Action | Fault | Handler | Office check reused | Closed check |
| --- | --- | --- | --- | --- | --- |
| QUA-1 | Nonconformity page (`ncr-item`) | F141 | ncr.php:516 | NCR register rule `scope_allows(office, unit)` (ncr.php:218) | — |
| QUA-2–5 | Contain, disposition, raise CAPA, assign owner / date | F141, F138 | ncr.php:566–613 | as QUA-1 | Closed → refuse |
| QUA-6 | Close | F141, F139 | ncr.php:615 | as QUA-1 | already closed → refuse |
| QUA-7 | Reopen | F141, F140 | ncr.php:629 | as QUA-1 | only from Closed |
| QUA-8 | CAPA page (`capa-item`) | found while checking | capa.php:425 | CAPA register rule `scope_office_allows()` (capa.php:140) | — |
| QUA-9, 10, 14–18 | CAPA: cause, add action, plan, done, verify, close, escalate | F152 | capa.php:464–600 | as QUA-8 | Closed / Closed as ineffective → refuse |
| QUA-11–13 | CAPA action done / dropped / reopened | F150 | capa.php:233–262, 496–512 | as QUA-8 | the action belongs to this CAPA; done is not done twice |
| QUA-19–24 | Complaint: acknowledge, validity, investigation, decide, notify, CAPA reference | F157 | complaints.php:488–568 | already `cmp_scope_gate()` | Closed → refuse |
| QUA-25 | Complaint reopen | found with F157 | complaints.php:580 | as above | only from Closed, with a reason |
| QUA-26 | Breach save (`conf-breach`) | F165 | confidentiality.php:398 | — | Closed → refuse |
| QUA-27 | System failure update (`failure-update`) | F167 | datacontrol.php:617 | — | Resolved → refuse |
| QUA-28 | Audit record: dates, summary (`audit-record`) | F169 (EC-QUAB-017) | audits.php:546 | — | Closed → refuse |
| QUA-29 | Audit add finding | F169 | audits.php:563 | — | Closed → refuse |
| QUA-30 | Audit remove finding | F170 | audits.php:580 | — | Closed → refuse; a finding with a CAPA → refuse |
| QUA-31–34 | Review: header, input, add decision, re-count | F175 | audits.php:663–706 | — | Complete → refuse (decision done and raise CAPA stay allowed) |
| QUA-35 | ID document "Log it" (`iddoc-share`) | F177 | identity.php:621 | — | Redacted → refuse (the existing reveal check, identity.php:609) |

### Sales (9)

| # | Action (address) | Fault | Handler | Office check reused | Closed check |
| --- | --- | --- | --- | --- | --- |
| SAL-1 | Attach a quotation to a deal (`opportunity-quote`) | F247 | opportunities.php:575 | quotation rule `scope_allows()` (the quotation door) | — |
| SAL-2 | Detach a quotation from a deal | F246 | opportunities.php:591 | as SAL-1 | Accepted → refuse |
| SAL-3 | Work a lead as a deal (`opportunity-from-lead`) | F249 | opportunities.php:638 | lead rule `scope_office_allows()` (the lead door, by `lead_id`) | Won → refuse; Lost → reopen first |
| SAL-4 | Billing schedule (`quote-milestones`) | F259 | milestones.php:483, ops.php:4352 | quotation door (by `quote_id`) + `crm_quote_can_work()` (14 Nov assigned work) | existing `ms_can_edit_quote()` |
| SAL-5 | Follow-up e-mail (`followup-compose`) | F260 | crm.php:3482, gate crm.php:2289 | quotation door, reading the follow-up's own quotation | — |
| SAL-6 | Remove a file (`quote-file-delete`) | F261 | crm.php:3535 | already the quotation door | `quote_is_locked()` (sent) → refuse |
| SAL-7 | Add a file / PO number after sending (`quote-files`) | F261 | crm.php:3497 | already the quotation door | **waits for Sales A16** |
| SAL-8 | Revise (`quote-revise`) | F263 | crm.php:3074 | already the quotation door | Accepted → refuse |
| SAL-9 | Mark Lost (`quote-status`) | F274 | crm.php:1856 | already | only from Draft and Sent (**N2**) |

### Marketplace (1) and Insights (2)

| # | Action (address) | Fault | Handler | Check |
| --- | --- | --- | --- | --- |
| MKT-1 | Booking edit form (`connect-requirement`) | F054 | connect_engage.php:221 | `connect_engage_can_transition()`; ended → the portal's sentence (connect_engage.php:295) |
| INS-1 | Take a snapshot (`analytics-snapshot`) | F027 | tapi_dash.php:268, tapi_gov.php:102 | month ended (shared date check, theme 6); retake asks; Final / Locked refused (existing `tapi_period_locked()`) |
| INS-2 | Period state | F027 | tapi_gov.php:90 | **N3** |

**Actions per module:**

| Module | Actions | Of which waiting for an answer |
| --- | --- | --- |
| Invoices & payments | 14 | 1 (Q7) |
| Work orders, jobs & deputations | 31 | 4 (OPS-28 Q10; OPS-29–31 Sales A1 / C2) |
| Inspection reports & endorsements | 13 | 4 (REP-1, 2, 3, 5: Q9) |
| Quality & accreditation | 35 | 0 |
| Sales | 9 | 2 (SAL-7 A16; SAL-9 widening N2) |
| Marketplace | 1 | 0 |
| Insights | 2 | 1 (INS-2: N3) |
| **Total** | **105** | **13** |

---

## Appendix B: the 50 faults and where each is covered

| Theme 1 fault | Row(s) | | Theme 2 fault | Row(s) |
| --- | --- | --- | --- | --- |
| F033 + F113 | OPS-29–31 (Sales A1 / C2) | | F027 | INS-1, INS-2 |
| F069 | MON-1 | | F054 | MKT-1 |
| F070 | MON-2, 3 | | F082 | MON-14 (Q7) |
| F072 | MON-4 | | F109 | OPS-27 |
| F073 | MON-5–9 | | F122 | OPS-24, 25 |
| F107 | OPS-2 | | F123 | OPS-26 |
| F108 | OPS-4–11 | | F127 | OPS-28 (Q10) |
| F112 | OPS-12–15 | | F138 | QUA-2–5 |
| F114 | OPS-1 | | F139 | QUA-6 |
| F115 | OPS-3 | | F140 | QUA-7 |
| F116 | OPS-21 | | F150 | QUA-11–13 |
| F117 | OPS-20 | | F152 | QUA-9, 10, 14–18 |
| F118 | OPS-16–19 | | F157 | QUA-19–25 |
| F121 | OPS-22, 23 | | F165 | QUA-26 |
| F141 | QUA-1–7 | | F167 | QUA-27 |
| F210 | REP-1 (Q9) | | F169 | QUA-28, 29 |
| F211 | REP-2 (Q9) | | F170 | QUA-30 |
| F214 | REP-9–13 (+ Reporting C2) | | F175 | QUA-31–34 |
| F216 | REP-3 (Q9) | | F177 | QUA-35 |
| F247 | SAL-1 | | F187 | REP-7, 8 |
| F249 | SAL-3 | | F212 | REP-5, 6 |
| F259 | SAL-4 | | F217 | REP-4 |
| F260 | SAL-5 | | F246 | SAL-2 |
| | | | F261 | SAL-6, 7 (A16) |
| | | | F263 | SAL-8 |
| | | | F274 | SAL-9 (N2) |

24 theme 1 faults (F033 and F113 are one fault found twice) and 26 theme 2 faults. Parts of a fault that belong to another theme are fixed with that theme: F027's future month and F122's 29 Feb 2026 (theme 6, dates), F109's reversed and duplicate periods (themes 6 and 5), F112's Branch Application Manager (theme 3), F259's "assigned work" half is included here because it reuses the quotation Edit's existing check.
