# End-to-end chains — decision pack (V51.1)

16 Nov 2026 · The 15 end-to-end chains (FLOW-01 to FLOW-15) follow one piece of work across modules. For example: quotation → contract → work order → job → report → invoice → receipt → profit. The New company tester ran the first 17 steps of FLOW-01. This tester ran the rest of FLOW-01 and all of FLOW-02 to FLOW-15, on a private copy with made-up data. Each chain was rerun from start to finish at the end, and that run counts.

- **Code check:** I checked the 3 serious findings in the program code myself. All 3 are confirmed.
- **Held v53:** it fixes none of these.
- **App changes:** none.

**How to answer:** reply with item numbers, for example *"A1 yes, A2 yes, A3 yes, B ok, C3 agree"*. Anything you don't mention stays untouched.

## Where the chains stand

| | Chains | Steps |
| --- | --- | --- |
| Passed | 0 | 167 |
| Failed | 12 | 24 |
| Need your decision | 3 | 20 |
| Blocked (test wording out of date) | — | 12 |
| The robot cannot do it | — | 3 |
| **Total** | **15** | **226** |

A chain counts as failed if any one of its steps fails, so **0 chains passed**. **Read with care:** this does not mean the chains are broken from end to end. **167 of 226 steps passed.** Most hand-overs work: quotation → contract → work order → job → report → invoice → receipt → ageing → Tally. The failures are 9 new defects, plus defects already reported in other packs.

**Watch point (server stuck at full processor power):** this tester checked the processor between every chain and during its practice runs. It **did not happen once.** The two earlier cases stay unexplained, and I keep watching for it in every future run.

**Tracker:** every one of the 1,501 tests in the test book has now been started. **None is left "not started".** Overall: 1,051 run, 756 passed, 295 failed; 163 blocked and 77 "cannot do" stay open. There are 249 defects in all.

## A — Fixes that need your yes (money, approvals, client rules)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-FLOW-001 (P1)** | **A report that was never submitted, reviewed or approved can be finalised and issued** by anyone allowed to issue reports. The screen hides the button, but sending the form directly works. *Confirmed in the code: the "must be approved first" check only applies once the report has an approval chain, and a draft has none yet.* | A report can be issued only after it has been submitted and, where approval rules apply, fully approved. Masters keep their existing override. **Approval workflow: needs your yes** |
| A2 | **DEF-FLOW-002 (P1)** | **A man-month deputation is valued at the monthly rate × the number of days.** One deputation showed **₹1,86,26,737.50** instead of ₹1,20,172.50 a month. The invoice itself came out right (₹1,20,172.50 + GST); the wrong figure sits on the deputation. *Confirmed in the code: the value is rate × man-days, whatever the rate's unit is.* | For a man-month rate, value = rate × months. Months are counted the way your existing "man-month basis" setting already says. **Money: needs your yes** |
| A3 | **DEF-FLOW-007 (P1)** | **A client on hold still gets new work through the portal.** Staff press "Accept & raise the call" on the client's portal request, and the work order is created. Everywhere else, your hold rule stops new business for that client. *Confirmed in the code: the portal path is the one door without the hold check.* | Apply the same hold check: "Accept & raise" is refused with the usual hold message, and the request stays open. **Client rule: needs your yes** |
| A4 | DEF-FLOW-005 (P2) | A sub-contractor engineer's travel voucher cannot be charged to the sub-contract job, so **profit is overstated** (₹10,000 shown instead of ₹8,800) | Let the voucher be charged to that job, so its cost is counted. **Money: needs your yes** |
| A5 | DEF-FLOW-008 (P2) | **A draft invoice made in the Billing workspace loses the client's credit days, payment terms and contract number**, so it is due on the day it is issued. A 60-day client was due at once | Carry the client's terms and the contract number onto the draft. **Billing: needs your yes** |
| A6 | DEF-FLOW-009 (P2) | Customer 360's "not yet matched" figure ignores tax deducted at source (TDS): ₹3,000 shown instead of ₹1,000 | Count TDS as settled, as the invoice itself does. **Money figure: needs your yes** |

## B — Fixes I will make once you say "B ok" (small, no rule change)

- **B1 DEF-FLOW-003 (P2):** the complaint form's "Deputation number" box drops the job number you type, so the complaint is stored with no job. I would keep it.
- **B2 DEF-FLOW-004 (P2):** the Analytics "CAPA effectiveness" figure never counts anything: it reads the wrong field. I would read the right one.
- **B3 DEF-FLOW-006 (P2):** a sub-contract job reads "Unassigned" and does not name the agency doing the work. I would name the agency.
- **B4 (small, not raised as a defect):** searching for a merged client finds it, marked merged, but the client it was merged into is plain text. I would make it a link.

Plus new wording for the 12 out-of-date steps. For example:
- a costing now needs an enquiry or lead first;
- a requisition now starts from a hiring request;
- "Mark as joined" is a separate step;
- voucher panels appear only after the booking;
- overlapping approval levels are refused (your SAL-003 rule).

Each step's new wording is recorded in the tracker.

## C — Questions only you can answer (my proposal in **bold**)

| # | Chain / step | Question | Proposal |
| --- | --- | --- | --- |
| C1 | FLOW-01 / 7 | A quotation raised from an enquiry is not tied to the deal its lead created | **Tie it to that deal.** Same as New company C2: one answer covers both |
| C2 | FLOW-01 / 36 | Downloading the Tally file records an export batch | Same as Money **MON-051**. One answer covers both |
| C3 | FLOW-02 / 2 | The costing shows 23.8% margin against a 20% target, because the quoted rate already allows 5% for negotiation | **Keep the formula, and label it "23.8% at quoted rate (20% after 5% negotiation)"** |
| C4 | FLOW-02 / 23 | For a man-month deputation, the profit report shows no revenue row and ₹0 | **Bill it from the Billing workspace, so profit shows revenue = rate and cost = salary** |
| C5 | FLOW-03 / 11 | Who may decide a complaint? | **Quality Head and Technical Head. Don't bar the person who recorded it; the ISO 17020 "not involved" rule is enough.** Permission: your yes |
| C6 | FLOW-03 / 13 | Should the Quality Head have "Internal audits & management review"? | **Yes.** Permission: your yes |
| C7 | FLOW-03 / 15 | Management review does not list nonconformities raised and closed | **Add them to the review's inputs** |
| C8 | FLOW-03 / 16 | A review cannot be completed until every input is filled | **Keep the rule, and reword the test** |
| C9 | FLOW-04 / 7 | A marketplace award bills the whole booking (10 days × ₹3,500 = ₹35,000), not one day | **Bill the booking, and reword the test** |
| C10 | FLOW-04 / 10–12 | Both the award and the approved vouchers can bill the client: **a risk of charging twice** | **When vouchers are approved, cancel the award's billing line automatically.** Money: your rule |
| C11 | FLOW-05 / 13 | There is nowhere to record the agency's ₹15,000 bill to you | **Add a simple "Bills to pay" record linked to the job** (new work) |
| C12 | FLOW-09 / 9 | Work done by one office for another: what does each office's profit show? | **The office that signed the client shows the revenue before GST (₹25,000). The office that did the work shows its ₹15,000 credit, labelled "credit"** |
| C13 | FLOW-11 / 13 | How should a filled seat show? | **The requisition shows "1 of 2 filled", and the person counts on the project of their current job** |
| C14 | FLOW-12 / 7, 9 | No credit note can be made on a **paid** invoice, and the message at step 6 says otherwise | **Allow it, up to the invoice total, kept as a credit for the client.** Money: your rule |
| C15 | FLOW-12 / 10 | What is "invoiced value"? | **Invoices issued minus credit notes, on one basis (with or without GST, your choice). Receipts never change it** |
| C16 | FLOW-13 / 4 | After client B is merged into A, registers still print B's name on old invoices | **The document keeps the name it was issued under. The register shows A, with "billed as B" under it** |
| C17 | FLOW-13 / 10 | A client merge cannot be undone | **Keep a record of what moved, and let an administrator "Undo merge" within a period you choose. Tell me how long** |
| C18 | FLOW-15 / 9 | A brand-name change does not reach quotation and invoice prints. They print the legal name, on purpose | **Keep the legal name, and add a "trading as <brand>" line** |
| C19 | FLOW-15 / 5 | The working norm (hours per month) has no "effective from" date | **Add one.** (In this run the norm changed nothing at all. That is the known Directory defect DEF-DIR-009) |

## D — Known defects met again (not counted twice)

| Where in the chain | Known defect | Pack |
| --- | --- | --- |
| Quotation → contract: contract value includes GST (₹1,18,000 instead of ₹1,00,000) | DEF-SAL-011 | Sales |
| Approval history does not say "for zz.mm" when a delegate signs | DEF-SAL-008 | Sales |
| Profit by work order: revenue includes GST; the contracting office does not list the job | DEF-FIN-009, FIN-006 | Money |
| A rejected report cannot be reissued; revision rate stays 0% | DEF-REP-002, REP-006 | Reporting |
| Requisition edit never saves; client submission does not move the stage; outcome moves do not update the stage | DEF-REC-005, 010, 011 | Recruitment |
| Dashboards: loaded cost ₹0 | DEF-INS-003, INS-002 | Insights |
| The working norm changes nothing | DEF-DIR-009, DIR-004 | Directory |
| Also | DEF-SAL-007, QUA-009, MKT-004, OPS-011, OPS-035 | Their packs |

## What each chain showed (₹ figures)

- **FLOW-01, inspection from quotation to profit:**
  - Quotation ₹1,00,000 + GST = ₹1,18,000, but the contract shows **₹1,18,000** ✗ (should be ₹1,00,000).
  - Work order, job and report issued: OK.
  - Job closed → billable line ₹25,000: OK.
  - Draft invoice: ₹25,000 + CGST ₹2,250 + SGST ₹2,250 = ₹29,500. Amount OK, but **contract missing** ✗ (A5).
  - Receipt ₹29,000 + TDS ₹500 → Paid: OK.
  - Profit and dashboard revenue ₹29,500 ✗ (includes GST).
  - Tally figures: OK.
- **FLOW-02, man-month deputation:**
  - Costing rate ₹1,20,172.50 → quotation 6 months = ₹7,21,035: OK.
  - Contract includes GST ✗.
  - Requisition → candidate → team member: OK.
  - **Deputation value ₹1,86,26,737.50** ✗ (A2).
  - Invoice ₹1,20,172.50 + GST = ₹1,41,804, due in 30 days: OK.
  - Profit row missing ✗ (C4).
- **FLOW-03, complaint → nonconformity → CAPA:**
  - The job is dropped from the complaint ✗ (B1).
  - Nonconformity and CAPA: OK.
  - The CAPA effectiveness figure is empty ✗ (B2).
- **FLOW-04, marketplace:**
  - Award ₹35,000 for 10 days.
  - Voucher: ₹7,800; commission ₹350; professional gets ₹7,625; client pays ₹7,975: all as configured.
  - The award line is still waiting to bill, a double-billing risk (C10).
- **FLOW-05, sub-contract:**
  - Job cost ₹15,000: OK.
  - The ₹1,200 voucher is not counted, so profit shows ₹10,000 instead of ₹8,800 ✗ (A4).
  - Business-unit profit and the dashboard direct cost: OK.
- **FLOW-06, overdue and on hold:**
  - 70 days overdue lands in the right column. A receipt reduces ageing correctly.
  - **The portal raises work for a client on hold** ✗ (A3).
- **FLOW-07, contract amendment:**
  - The revision and the new work order: OK.
  - The amendment mixes values with and without GST (Sales DEF-SAL-011).
- **FLOW-08, office costs and month close:**
  - Costs spread across business units; profit matches the dashboard.
  - Reopen, correct and close again: OK.
  - Dashboards show loaded cost ₹0 ✗ (known).
- **FLOW-09, work done for another office:**
  - The ₹15,000 credit and its settlement: OK.
  - The signing office's profit misses the job ✗ (known).
- **FLOW-10, client rejects a report:**
  - A nonconformity is raised automatically: OK.
  - The report cannot be reissued ✗ (known).
- **FLOW-11, hiring request → requisition:** OK.
- **FLOW-12, client terms and part payments:**
  - **The invoice is due at once instead of in 60 days** ✗ (A5).
  - Payments with TDS → Paid ₹1,18,000: OK.
  - Customer 360 shows ₹3,000 not yet matched instead of ₹1,000 ✗ (A6).
- **FLOW-13, merging two clients:** all 5 records moved, and the totals are unchanged.
- **FLOW-14, quotation approval levels with delegation and escalation:**
  - The levels sign in order; delegation and escalation work; a rejection needs a reason; a resubmission keeps the history.
  - Only "signed for" is missing (known).
  - The rules were put back exactly afterwards.
- **FLOW-15, company settings:**
  - Terminology, SLA, masters and switching a module off and on: all OK. Data was intact and everything was put back exactly.
  - The norm changed nothing ✗ (known).
  - The brand-name question is C18.

## F — What is NOT tested yet

- **FLOW-01 step 11:** the content of the report PDF needs a person to read it.
- **FLOW-10 step 8:** it cannot be reached until reports can be reissued (Reporting DEF-REP-002).
- **FLOW-07 step 13:** the second work order is not billed inside this chain, so "both billed" could not be seen.
- **Only SQLite was used.** The MariaDB run comes with the fix batches.
- **Real payments and real e-mail:** not used here. The money figures are the app's own.

So the chains picture is: **most hand-overs work; 3 serious defects (a draft report can be issued, a man-month deputation valued per day, the portal ignores a client hold); 6 medium; 19 questions, two of them about charging twice or crediting a paid invoice; the processor watch point did not recur.**

## After you answer

- **A1** joins the Reporting batch.
- **A2, A4, A5 and A6** join the **Money** batch.
- **A3** joins the **"branch and access"** batch, because it is about who may raise work for whom.
- **B** goes in a small batch.
- Each batch gets:
  - the full suite on SQLite and MariaDB;
  - the robot tests again;
  - a zip for the test copy;
  - the changed-files list;
  - the rollback steps.
