# Answer sheet 3 — "Edge cases" (17 Nov 2026)

The edge-case helpers tried the unusual things on today's program (release 74a263f): pressing a button twice, typing a page's address directly, opening an old browser tab, typing 30 February, a minus sign, an empty box. They found **281 faults** in every module.

**Nothing has been changed.** Every fault below was found and shown on a private copy. No fix has been made for any of them.

Most of the 281 come from **14 root causes**. I propose to fix each cause **once, at its root**, not screen by screen. That way the screens nobody has tested yet are covered too.

**Three kinds of item:**
- **Themes 1–14 (section 1):** what goes wrong and the permanent fix. Each one says whether it needs your yes. Most are plain bug fixes that follow your permission list and your lifecycle document.
- **Your rules, Q1–Q23 (section 3):** questions only you can answer. Each has my recommendation in **bold**. **I will not guess these.**
- **Already asked (section 4):** faults that are already on an earlier sheet or pack. **They are not asked again here.**

**The easiest answer:** *"Edge pack: themes 1–14 as proposed; Q1–Q23 as recommended."* If you disagree with one, name it, for example *"… except Q8 (b)"*.

**Nothing here gives anyone a new right or adds a status.** Where a role does more today than your list allows, the fix brings it back to the list.

---

## 1. The 14 themes

| # | Theme | Faults | Serious | Your yes? |
| --- | --- | --- | --- | --- |
| 1 | Another office can act by typing an address | 24 | 11 | No (Q9) |
| 2 | Closed, issued or decided records can still be changed | 26 | 4 | No (Q7, Q10) |
| 3 | A step done by someone the rules do not allow | 16 | 3 | No (Q2) |
| 4 | Logins, roles and approval cover | 16 | 6 | Q1, Q19 |
| 5 | Pressing twice, or reusing a code, makes a second record | 9 | 0 | No |
| 6 | Impossible dates accepted | 12 | 0 | No (Q4) |
| 7 | Wrong, blank or out-of-range values accepted, or silently changed | 27 | 0 | No |
| 8 | Salary, margin and private details shown to the wrong people | 10 | 3 | No (Q16) |
| 9 | Money figures wrong or disagreeing | 38 | 11 | **Yes (money)**, Q3, Q6 |
| 10 | Numbering | 6 | 0 | Q13, Q14 |
| 11 | A record made from another loses its office, client or link | 16 | 4 | No (Q5) |
| 12 | Buttons that only refuse, links that open nothing | 17 | 1 | No (Q20) |
| 13 | Messages and reminders that are untrue or never arrive | 21 | 1 | Q21, Q23 |
| 14 | One-off faults | 43 | 3 | Q8, Q15, Q22 |
| | **Total** | **281** | **47** | |

"No (Q9)" means: the theme is a plain fix, except the one point asked in Q9.

### Theme 1 — Another office can act by typing an address (24 faults, 11 serious)

**What happens:** the button is hidden, but the action behind it checks only the **role**, not the **office**. Typing the address still does it. Often "outside your office / branch scope" appears only **after** the change is saved.
- **Test Coord2 (Mumbai) cancelled an Ahmedabad deputation** (TB Refinery, 3 man-days) and added a timesheet row.
- A Mumbai coordinator turned Ahmedabad invoice **AHM/2627/0001 to Paid**, and set an Ahmedabad job's invoice amount to **₹1**, "payment received".
- **Test BranchMgr2 (Mumbai) issued an Ahmedabad report**; the client was notified.

**Fix:** one office check at the door of every action, **before** anything is saved, using the same rule as the lists. An automatic test tries every action from the wrong office.
**Your yes:** not needed (your list; branch batch B2, B4). Contract openings wait for Sales A1 / C2; report approval is Q9.

### Theme 2 — Closed, issued or decided records can still be changed (26 faults, 4 serious)

**What happens:** the **screen** goes read-only, but an old browser tab or typed address still changes the record, or moves it to a status your lifecycle does not allow.
- Instruments were **added to and removed from issued report EC-IRN-0002**.
- A Finalise holder of another office **deleted an issued report with no reason**; the client's verify page then said *"We do not recognise that code"*.
- An **Approved** attendance period went back to **Draft** while its 3 man-days stayed billable. The same for closed nonconformities, CAPAs, complaints, audits, reviews, sent quotations, and a **Completed** Marketplace booking moved back to **Booked**.

**Fix:** before any change, one question, *"is this record still open for this change?"*, answered from your lifecycle statuses, with one plain sentence ("This nonconformity is closed. Reopen it first."). Corrections only through the existing ways: reopen, new revision, credit note. **No new status.**
**Your yes:** not needed. Q7 and Q10 are asked.

### Theme 3 — A step done by someone the rules do not allow (16 faults, 3 serious)

- A **Business Development Manager** (no work-order right) **created work order CALL-00001 for ₹25,00,000**, with no quotation or contract.
- An **Inspector changed a vendor from Prospect to Approved**.
- A Branch Manager **approved a draft offer** of ₹55,000 a month at **13.3% margin** (target 20%); the margin reason was never asked.

**Fix:** each action checks **the same right that decides whether its button is shown**. Approval steps run only in order, by the person (or cover) at that step.
**Your yes:** not needed. Q2 is asked.

### Theme 4 — Logins, roles and approval cover (16 faults, 6 serious)

- **Branch Manager (Ahmedabad) deactivated the Mumbai Branch Manager and the Business Director.**
- A Branch Manager pressed **Save with no change** on Test Director and Test Finance: **both became Coordinators**.
- A Branch Manager removed the Master Admin's voucher cover and set "Test Director → Test Coord"; **Test Coord approved a ₹30,000 voucher at Director level**. Likewise **Test BDM approved his own quotation** through cover set by Test MktMgr.
- Password reset accepts *"password"* plus eight spaces.

**Fix:** one rule for "may this person manage that person" (own office, never a higher rank, Master-Admin-like roles only by a Master Admin). Save never changes a role by accident. One password rule everywhere. A refused "Add a person" creates nothing. **A delegate never signs their own work.**
**Your yes:** Q1 and Q19.

### Theme 5 — Pressing twice, or reusing a code, makes a second record (9 faults)

- **Record it** pressed twice: **RCP/2627/0002 and 0003, both ₹41,300**, same UTR.
- A double press makes two numbered nonconformities or complaints. Coupon **"EC10"** and contract number **EC-BN-0099** were saved twice; a new KPI **overwrote "Jobs closed"** (target 12 lost).

**Fix:** every form carries a one-time stamp, so a second press shows the record already made. Repeated codes are refused, naming the existing record.
**Your yes:** not needed.

### Theme 6 — Impossible dates accepted (12 faults)

- An invoice dated **30 Feb 2026** (and one dated "abc") was stored.
- A contract **ending 1 Apr 2026, starting 31 Mar 2027**; a **credit note of 31 Mar 2026** against an invoice of **17 Nov 2026**.

**Fix:** one shared date check: a real date, and an end never before its start. Your 13 Nov 2026 rule for enquiries (no "received" date in the future) stays as it is.
**Your yes:** not needed, except Q4.

### Theme 7 — Wrong, blank or out-of-range values accepted, or silently changed (27 faults)

- **Default GST 150%** was stored as 100% with "saved"; every new ₹1,000 line became ₹2,000.
- **Working norm 200 hours** said "saved" but stored **48 hours**.
- An empty "Owner and date" **erases** a nonconformity's due date; an empty "Adjust dates" erases an issued report's issue date.

**Fix:** a box keeps exactly what was typed or refuses with the reason. **An empty box never wipes a stored value.** An empty save says *"Nothing to save — no changes"* (Admin C12).
**Your yes:** not needed. Negative rates are already asked (Marketplace C12, Operations C9, Sales C14). Any business limit I will ask you first.

### Theme 8 — Salary, margin and private details shown to the wrong people (10 faults, 3 serious)

- A requisition posted to the **Marketplace** showed outside professionals *"Basic 20,000 · HRA 8,000 … Loaded cost 54,500, billed at 71,531 (23.8% margin)"*.
- The **public careers page** showed *"Loaded cost 66,000, billed at 82,500 (20.0% margin)"*.
- **Test Coord** opened an offer letter: *"₹50,000 a month (₹6,00,000 a year)"*.

**Fix:** costing figures are never copied into text that leaves the costing. One "may see salary / revenue" check on every page, letter, download and look-up.
**Your yes:** not needed. Q16 is asked.

### Theme 9 — Money figures wrong or disagreeing (38 faults, 11 serious)

- **GST counted as income:** a ₹1,00,000 job shows **revenue and profit ₹1,18,000** (already M2).
- **"Our state" blank:** a Maharashtra client was charged **CGST ₹900 + SGST ₹900 instead of IGST ₹1,800**, and the invoice could be issued.
- **Marketplace billing:** ₹30,000 billed for one person booked 5 days at ₹3,000. **To bill:** ₹75,000 shown for a ₹37,500 contract. **Costing:** ₹50,000 × 10 = **₹5,00,000.03**, so a second approval level.

**Fix:** each money figure is worked out **in one place** (billable share, revenue before GST, outstanding, man-day / man-month units, paise), and every screen, print and export reads it. The tax type comes only from a confirmed own state.
**Your yes: needed** (money). M1, M2 and M15 are already on sheet 2; Q3 and Q6 are rules.

### Theme 10 — Numbering (6 faults)

- The next stage report got a **new series, …/TBSTEE/IR/000001**, instead of …/GEN/IR/000002.
- **TB Group Parent and TB Group Child share one series** (TBGROU …/000001, /000002).

**Fix:** one numbering engine. The year comes from the document's own date, the series from the earlier document, and the setup example from the engine itself.
**Your yes:** Q13 and Q14 (invoice year: Money C1, sheet 2 R1).

### Theme 11 — A record made from another loses its office, client or link (16 faults, 4 serious)

- A work order with a **blank executing office is treated as Ahmedabad's**: Ahmedabad's Coordinator and Branch Manager **edited Mumbai's own work orders**. Nonconformities with no branch go to Ahmedabad too.
- A report Ahmedabad started on a Mumbai-sold job was **saved with no client and no vendor**, and Ahmedabad cannot fix it.
- **Test KAM (Mumbai)** made costing AHM/PC/2026-27/11/0001 and was **locked out at once**.

**Fix:** a blank office **never means Ahmedabad**: it takes the office of the source record. Whatever is made from another record carries its client, contract, office, lead and contact. Forms offer only offices the person may work in.
**Your yes:** not needed (your B1/B3 yes), except Q5.

### Theme 12 — Buttons that only refuse, links that open nothing (17 faults, 1 serious)

- After **Ahmedabad rejected a candidate, Mumbai cannot take him on**: every way the page suggests is refused.
- **Finance is shown** "+ Add", "Save checklist" and "Edit the quote"; all refuse. The "Open concerns" tile and credit-note links open "not found".

**Fix:** a button is shown only to someone it works for, as already done for the quotation Submit button. An automatic test opens every link.
**Your yes:** not needed, except Q20.

### Theme 13 — Messages and reminders that are untrue or never arrive (21 faults, 1 serious)

- **Overdue CAPA and complaint e-mails go out with an empty "To"**, on the live site too, so nobody is chased.
- Complaint and CAPA **deadline settings never save**, silently (known: Quality B1, B2). A refused award shows **"Updated."**.

**Fix:** a message is written from what really happened. A reminder with no real recipient goes on a **"Could not send"** list.
**Your yes:** Q21 and Q23.

### Theme 14 — One-off faults (43 faults, 3 serious)

Each is fixed at its own cause. The main groups:
- **Records:** restoring a backup **rolled the sealed audit trail back (112 → 104 rows)** and still said "intact". Fix: a restore never removes audit lines and is itself recorded.
- **Site rules:** a site typed as words was stored as 0, so a **blocking site requirement was never checked** (Test SrInspector allocated with no medical). Only **one** hand-raised hold point can exist company-wide.
- **Hindi / Gujarati:** text is cut inside a letter and then vanishes; Gujarati prints "?" on the quotation PDF.
- **Month ends:** a monthly schedule from 31 Oct **skips November**; 31 Jan 2027 + 1 month shows **03 Mar 2027**.
- **Follow-through:** a cancelled work order leaves JOB-00001 on Test Inspector's schedule; late reports count as **on time** in ratings; two pages can send the browser to an **outside website**.

**Your yes:** Q8, Q15 and Q22. The rest are plain fixes.

---

## 2. Serious first — all 47 serious faults

45 lines hold the 47 serious faults. Two lines hold a serious fault found twice on two screens (F025 + F096, F032 + F074). One line also names a medium-rated twin of a serious fault (F113). The **T** column is the theme.

| # | T | Case | Finding | What happens |
| --- | --- | --- | --- | --- |
| 1 | 1 | EC-E2E-005 + EC-OPS-085 | F033 + F113 | Contract openings are not office-limited: Ahmedabad signed and opened Mumbai's contract MUM/C/2026-27/00001, and Mumbai's Branch Manager opened an Ahmedabad contract |
| 2 | 1 | EC-MON-044 | F069 | Mumbai coordinator opens an Ahmedabad client's full ledger by typing the address |
| 3 | 1 | EC-MON-039 | F070 | Mumbai coordinator matched and unmatched money on Ahmedabad invoices (AHM/2627/0001 → Paid) |
| 4 | 1 | EC-MON-062 | F072 | Mumbai coordinator changed an Ahmedabad job's invoice number, amount (₹1) and 'payment received' |
| 5 | 1 | EC-MON-080 | F073 | Mumbai coordinator ended an Ahmedabad job's billing early (15 Nov 2026) and changed its terms |
| 6 | 1 | EC-OPS-049 | F107 | An expense line is deleted just by opening a link, with no confirmation, even by another branch |
| 7 | 1 | EC-OPS-061 | F108 | Test Coord2 (Mumbai) set an Ahmedabad deputation to Cancelled and added a timesheet row |
| 8 | 1 | EC-REP-029 | F210 | Mumbai Branch Manager approved an Ahmedabad report by sending the action |
| 9 | 1 | EC-REP-034 | F211 | Test BranchMgr2 (Mumbai) issued an Ahmedabad report; the client was notified |
| 10 | 1 | EC-REP-097 | F214 | Ahmedabad Coordinator edits and an unassigned Branch Manager endorses a Mumbai endorsement |
| 11 | 1 | EC-SALB-074 | F259 | Billing schedule saved on another branch's quotation; on an approved quotation it cancels the approval |
| 12 | 2 | EC-OPS-064 | F109 | An Approved attendance period can go back to Draft while its 3 man-days stay billable; reversed and duplicate periods accepted |
| 13 | 2 | EC-QUAB-108 | F187 | Instruments can be added to and removed from an ISSUED report |
| 14 | 2 | EC-REP-060 | F212 | Any Finalise holder from any office deletes an ISSUED report with no reason; the client's verify page then fails |
| 15 | 2 | EC-SALA-027 | F246 | The accepted quotation can be detached from a won deal, so one sale makes two contracts |
| 16 | 3 | EC-MKT-044 | F056 | Without a plan, 'Use template' and 'Duplicate → Open' still post live and use no credit |
| 17 | 3 | EC-REP-100 | F215 | An Inspector can change a vendor's status from Prospect to Approved |
| 18 | 3 | EC-SALA-026 | F245 | A Business Development Manager created work order CALL-00001 (₹25,00,000) with no quotation or contract |
| 19 | 4 | EC-ADM-015 | F001 | Password reset accepts 'password' plus spaces; the company password rule is skipped |
| 20 | 4 | EC-ADM-034 | F003 | Branch Manager pressing Save with no change turned Test Director and Test Finance into Coordinators |
| 21 | 4 | EC-ADM-035 | F004 | Branch Manager (Ahmedabad) deactivated the Mumbai Branch Manager and the Business Director |
| 22 | 4 | EC-ADM-049 | F013 | Admin (not Master) made a role copying Master Admin; that login opened Master-only screens |
| 23 | 4 | EC-ADM-060 | F016 | Branch Manager set 'Test Director → Test Coord' cover; Test Coord approved a ₹30,000 voucher at Director level |
| 24 | 4 | EC-SALB-036 | F258 | Test MktMgr set a cover for another approver; Test BDM then approved his own quotation |
| 25 | 8 | EC-E2E-025 | F039 | Posting a requisition to the Marketplace publishes our salary build-up and 23.8% margin to outside professionals |
| 26 | 8 | EC-REC-027 | F197 | Test Coord opens the offer letter and sees '₹50,000 a month (₹6,00,000 a year)' |
| 27 | 8 | EC-REC-045 | F204 | Posting generator copies salary and margin into the public careers page |
| 28 | 9 | EC-ADM-099 + EC-MON-084 | F025 + F096 | Revenue and profit include GST: a ₹1,00,000 job shows revenue and profit ₹1,18,000 (dashboards, analytics, Profit by work order: ₹59,000 for ₹50,000) |
| 29 | 9 | EC-E2E-001 | F031 | Billing-workspace draft for a Mumbai contract was issued in Ahmedabad's series (AHM/2627/0001); Mumbai shows ₹0 billed; due date ignores 45 credit days |
| 30 | 9 | EC-E2E-007 + EC-MON-003 | F032 + F074 | With 'Our state' blank, a Maharashtra client is charged CGST ₹900 + SGST ₹900 instead of IGST ₹1,800, and the invoice can be issued |
| 31 | 9 | EC-MKT-038 | F055 | Send to billing multiplies by people needed: ₹30,000 billed for one person booked 5 days at ₹3,000 |
| 32 | 9 | EC-MKT-048 | F058 | Client switches a ₹6,200/day booking to 2 man-months; billing would be ₹12,400 for two months |
| 33 | 9 | EC-MON-061 | F071 | Old 'manual invoice entry' on a job overwrites figures after a real invoice: revenue became ₹99,999 |
| 34 | 9 | EC-MON-053 | F095 | Printed invoice says 'Settled so far ₹1.00' on every issued invoice (known) |
| 35 | 9 | EC-OPS-041 | F110 | A job saved with Man-days 0 adds nothing to the contract: 15 booked days show as '10 of 10' |
| 36 | 9 | EC-SALA-058 | F253 | Costing: ₹50,000 × 10 man-months gives ₹5,00,000.03, pushing it into a second approval level |
| 37 | 11 | EC-E2E-001 | F029 | A report started by Ahmedabad on a Mumbai-sold job is saved with no client and no vendor, and Ahmedabad cannot fix it |
| 38 | 11 | EC-OPS-031 | F105 | A work order with a blank executing office is treated as Ahmedabad's: Ahmedabad opened and edited Mumbai's own work orders |
| 39 | 11 | EC-OPS-032 | F106 | For Mumbai's own work order the allocation form picks Ahmedabad and demands an inter-office credit |
| 40 | 11 | EC-QUAA-009 | F135 | Nonconformities raised without a branch are treated as Ahmedabad's: Mumbai loses its own record |
| 41 | 12 | EC-REC-019 | F194 | After Ahmedabad rejects a candidate, Mumbai cannot take him on: every route is refused |
| 42 | 13 | EC-QUAA-066 | F155 | CAPA and complaint overdue e-mails are sent with an empty To address, so they never arrive |
| 43 | 14 | EC-ADM-092 | F022 | Restoring a backup rolled back the sealed audit trail (112 → 104 rows) and still shows 'intact' |
| 44 | 14 | EC-QUAB-073 | F180 | A site typed as words is stored as 0, so a blocking site requirement is never enforced (Test SrInspector allocated without medical) |
| 45 | 14 | EC-REP-091 | F213 | Only one hand-raised hold/witness point can exist company-wide; the second gives an error |

---

## 3. Your rules needed

**I will not set these myself.** Short answers are fine. "As recommended" is also an answer.

| # | Question (fault) | **Recommended**, and why |
| --- | --- | --- |
| Q1 | **Who may set cover** for a voucher approver? Today a Branch Manager set cover for the Director and removed the Master Admin's (F016) | **Master Admin and Admin only.** Cover decides who signs money, and your voucher setup is Master Admin only |
| Q2 | May a voucher chain be saved **without Finance**? (F017) | **No. Finance always signs last, and the setup refuses a chain without it.** Your 16 Nov rule: "Finance approves last" |
| Q3 | When does a voucher **count as cost** in a job's profit? Today a ₹21,000 voucher **sent back** already shows, and profit reads −₹16,709 (F047) | **From Approved.** Waiting claims show separately as "claimed, not approved". Only an approved voucher is payable |
| Q4 | May a visit day be **closed before that day**? On 17 Nov the days of 19 and 20 Nov were closed (F035) | **No.** A day not reached cannot have been inspected |
| Q5 | Work sold by Mumbai and carried out by Ahmedabad: **which office owns the costing**? (F034) | **The selling office (Mumbai)**, with the executing office shown for information. The salesperson builds and submits it |
| Q6 | **"Our state" is blank** (F032). Use the company-profile state (Gujarat), or refuse to issue? | **Refuse to issue until Finance fills it in. Offer Gujarat from the profile for Finance to confirm.** The GST type depends on it; never guess. Please check with your accountant |
| Q7 | An invoice **already sent to Tally** is cancelled (F082). Tally is one-way (Money D5) | **Ask first: "This went to Tally on 17 Nov 2026. Cancel here and reverse it in Tally by hand?"** It is then listed on a "Fix in Tally" list. Please check with your accountant |
| Q8 | A **work order is cancelled**: what happens to its jobs? (F043) | **(a) Unstarted jobs and future visit days are cancelled with it, and the inspector is told. Jobs with work done stay for closing and billing.** (b) Keep the jobs open, as today |
| Q9 | You said "approve rules unchanged" (branch batch B4). May **another office approve, vet or issue** a report? (F210, F211, F216) | **No: only the report's issuing, executing and contracting offices**, plus company-wide roles. Approvers named in the report's chain still sign |
| Q10 | May a **granted contract exception be withdrawn**? Today it can be refused afterwards, and who granted it is overwritten (F127) | **Yes, by whoever granted it or a higher level, with a reason.** Both names stay on record |
| Q11 | **Who decides a dispute about a finding**, and approves **departures and extensions**? (F147, F148) | **The Quality / Technical Head, as you chose in R3.** Never the person whose deadline moves |
| Q12 | **Which branch owns** a complaint, or a corrective action raised with no job? Today they have none, and every branch sees them | **The job's branch; else the client's home branch; else the person recording it.** The form shows which |
| Q13 | Nonconformity, CAPA, audit and review numbers: **calendar or financial year**? | **One rule for all numbers: the same as your answer to Reporting C11** |
| Q14 | The client part of the report number is cut from the name, so **TB Group Parent and TB Group Child share a series** (F223) | **A short unique client code (up to 8 letters) on each client record.** Numbers already issued stay |
| Q15 | **Month ends** (F119, F163, F186): 31 Jan + 1 month = ? | **The same day N months later; if that month is shorter, its last day (28 Feb).** The same rule for schedules, agreements and calibration. Tell me if calibration should end the day before instead |
| Q16 | The **Coordinator may not see salaries**, but opened the offer letter (F197). Who sends it? | **Someone allowed to see salaries, or it is e-mailed straight to the candidate and staff see only "sent".** Letting the Coordinator see it would be a new right |
| Q17 | Offer boxes "Approved budget", "Candidate expected" and "Internal benchmark": **per month or per year**? (F195) | **Per month, with the year shown beside it**, the same as the offer letter already shows the CTC. Each box is labelled |
| Q18 | **Working days in a month** for a man-day hiring request? Today the approver sees ₹3,000, while the requisition counts 22 days (₹66,000) (F190) | **A company setting. Please tell me the number.** The requisition uses 22 today |
| Q19 | After a **password reset or change**, sign out the person's other devices? (F002) | **Yes, both**, except the forced first change. One answer also covers Portal C1 |
| Q20 | A **deal-stage approval** went to Branch Managers, who cannot open deals, so the deal is stuck (F242) | **Refuse such a rule when it is saved. Only people whose offices cover the deal's branch approve**, as for quotations |
| Q21 | Identity-document **retention shortened** from 730 to 90 days: apply it to copies already held? (F178) | **Yes, after a confirmation showing how many copies go earlier.** The panel then says exactly what happens |
| Q22 | A client company **signs itself up**: is the founder its portal admin? Today they are not, and the Team page shows "not found" (F061) | **Yes**, so they can add their own team |
| Q23 | **Who receives overdue reminders** for CAPAs and complaints? (F155) | **The record's owner, with a copy to the Quality Head and the branch's manager.** The Quality Head is not on the list today, so adding them needs your yes |

---

## 4. Already asked elsewhere — not asked again

| Fault (appendix) | Already on | Note |
| --- | --- | --- |
| F025 + F096, F046 | Sheet 2 **M2** | Revenue counted with GST |
| F095 | Sheet 2 **M1** | Printout "Settled so far ₹1.00" |
| F097 | Sheet 2 **M8** | No "Draft invoice" button for roles that may raise |
| F124 | Sheet 2 **M15** | Deleted work order can still be billed |
| F052 | Sheet 2 **M12** | Empty commission save |
| F068 | Sheet 2 **M13** | Duplicate coupon code. *The 150% coupon (F060) is new, in theme 7* |
| F084 | Sheet 2 **M11** | Same choice for the Business Unit Head as for the Operation Manager |
| F094 | Money pack **C1**; sheet 2 **R1** | Invoice year |
| F031, F098 | Chains pack **A5** | Due date ignores credit days |
| F104 | Chains pack **A6** | TDS not counted as received |
| F099, F100, F101, F102, F103 | Money pack **B1, B3, B4, C4, E1** | — |
| F082 | Money pack **D5** | Q7 asks only about cancelling |
| F055, F058 | Chains pack **C9 / C10** | Which way bills a Marketplace award; F055 also breaks your 11 Nov rule |
| F033 + F113 | Sales pack **A1, C2** | Contract openings across offices |
| F268, F046 | Sales pack **A2** | Contract value with GST |
| F248, F252 | Sales pack **C1, A3** | Blocked customer |
| F233, F240 | Sales pack **C14** | Negative or empty values on leads and deals |
| F250 | Sales pack **C5** | Two Won stages |
| F254, F271, F272, F280 | Sales pack **B9** | Paise not shown |
| F234, F277 | Sales pack **B3** | File-size messages |
| F261 | Sales pack **A16** | Documents after sending |
| F036 | New company **C2** = Chains **C1** | Quotation not tied to its deal |
| F005, F007 | New company **A4** (same family) | Login limit; refused "Add a person" |
| F028 | Insights pack **A4** | KPI targets and snapshots |
| F045 | Chains pack **B2** | CAPA effectiveness |
| F048, F049, F053, F067 | Marketplace pack **B9, B1, C12, B11** | — |
| F109, F118, F129, F130, F131, F132 | Operations pack **B** list (025, 016, 024, 041, 006/009, 007) | — |
| F119, F122, F123 | Operations pack **A5, A10, A4** | F119 also skips a month (Q15) |
| F133, F137, F142, F143, F154, F159 | Quality pack **B7, B5, B11, B3, B2, B1** | — |
| F134, F160 · F182 · F180 | Quality pack **B14** family · **B9** family · **B15** | — |
| F184 and the quality dates | Quality pack **C1** | — |
| F198 · F200 · F196 | Recruitment pack **A6** · **B3 / B11** · **B18** family | — |
| F041 + F209 | Recruitment pack **B5**; plan 29 answer **C** | The lock is lifted when the process ends |
| F228, F229 · F214 · F224–F226 | Reporting pack **B1, B11** · **C2** · **B7** family | — |
| F213, F219 | Sheet 1 **X1** (follow-up) | Hold & witness points |
| F105, F106 · F210, F211, F216, F221 | Sheet 1 **B1 / B3** · **B4 / B5** | Follow your yes; Q9 is the only new point |
| F223 | Plan 29 **Q1** | Series per client and type; Q14 is new |

---

## 5. What happens after your answer

1. Before touching the code, I explain each money change (theme 9) with a worked example, as you asked.
2. I fix the themes **at their root**, one theme at a time. I test each on both database types, then rerun the edge cases and the robot tests of every affected module.
3. I send a **test-copy (opstest) zip** with the changed-files list and the rollback steps. Where a change alters who can do what, the rule documents are updated in the same change.
4. You check it on opstest, as before.

Nothing goes to the live site until you say testing is complete and answer yes to "Shall I prepare the live update?"

---

**One-line answer:** *"Edge pack: themes 1–14 as proposed; Q1–Q23 as recommended"*. Or name the exceptions, for example *"… except Q8 (b), Q18 = 26 days"*.

---

## Appendix — all 281 faults

**How to read it:**
- **F001…F281** is this sheet's own number. The **case** is the edge-case book's number.
- The **theme** is the number from section 1.
- **Four faults were found twice** (the same fault on two screens). Each pair is one row showing both numbers and both cases: F025 + F096, F032 + F074, F033 + F113 and F041 + F209. So there are 277 rows for 281 faults.
- "(known)" means an earlier pack already reported it, and it is still present on today's program.

| Finding | Case | Area | Severity | What happens | Theme |
| --- | --- | --- | --- | --- | --- |
| F001 | EC-ADM-015 | Admin | High | Password reset accepts 'password' plus spaces; the company password rule is skipped | 4 |
| F002 | EC-ADM-016 | Admin | Medium | After a password reset, the person's other signed-in devices keep working | 4 |
| F003 | EC-ADM-034 | Admin | High | Branch Manager pressing Save with no change turned Test Director and Test Finance into Coordinators | 4 |
| F004 | EC-ADM-035 | Admin | High | Branch Manager (Ahmedabad) deactivated the Mumbai Branch Manager and the Business Director | 4 |
| F005 | EC-ADM-030 | Admin | Medium | Reactivate ignores the login limit: 23 active logins on a 22-login plan | 4 |
| F006 | EC-ADM-022 | Admin | Medium | A repeated username shows a raw database error page and leaves a stray team member | 4 |
| F007 | EC-ADM-027 | Admin | Medium | Every refused 'Add a person' still creates a team member; retries pile up copies | 4 |
| F008 | EC-ADM-021 | Admin | Medium | A login is stored with a blank username | 4 |
| F009 | EC-ADM-025 | Admin | Low | Username saved with spaces around it, so the login can never be used | 4 |
| F010 | EC-ADM-028 | Admin | Low | E-mail 'not-an-email' is saved without a check | 4 |
| F011 | EC-ADM-023 | Admin | Low | A repeated e-mail gets a misleading refusal message | 4 |
| F012 | EC-ADM-037 | Admin | Medium | Reporting loop (Test OpsMgr reports to Test Coord and back) accepted; the voucher chain points back at the claimant | 4 |
| F013 | EC-ADM-049 | Admin | High | Admin (not Master) made a role copying Master Admin; that login opened Master-only screens | 4 |
| F014 | EC-ADM-048 | Admin | Low | Two custom roles with the same name 'EC Site Lead' cannot be told apart | 4 |
| F015 | EC-ADM-042 | Admin | Low | 'Apply recommended set' saves at once but says 'Review and Save' | 13 |
| F016 | EC-ADM-060 | Admin | High | Branch Manager set 'Test Director → Test Coord' cover; Test Coord approved a ₹30,000 voucher at Director level | 4 |
| F017 | EC-ADM-055 | Admin | Medium | Voucher chain saved with no Finance level; vouchers then approved without Finance | 3 |
| F018 | EC-ADM-085 | Admin | Medium | SLA target −3, 0, 'abc' and 2.5 accepted as 'updated'; the Business Director could save it | 7 |
| F019 | EC-ADM-067 | Admin | Low | Numbering setup: prefix silently cut to 20 characters, 0 digits and start −5 accepted; the example is wrong | 10 |
| F020 | EC-ADM-069 | Admin | Low | Annual revenue target −₹5,00,000 and turnaround threshold −3 days saved | 7 |
| F021 | EC-ADM-078 | Admin | Low | Two custom fields with the same label on one form; a 500-character label accepted | 5 |
| F022 | EC-ADM-092 | Admin | High | Restoring a backup rolled back the sealed audit trail (112 → 104 rows) and still shows 'intact' | 14 |
| F023 | EC-ADM-097 | Admin | Medium | 'Apply the key' with an empty box removes the licence key without asking | 14 |
| F024 | EC-ADM-096 | Admin | Low | Master Admin's entries are labelled 'Admin (legacy)' in the audit trail | 14 |
| F025 + F096 | EC-ADM-099 + EC-MON-084 | Insights + Money | High | Revenue and profit include GST: a ₹1,00,000 job shows revenue and profit ₹1,18,000 (dashboards, analytics, Profit by work order: ₹59,000 for ₹50,000) *(duplicate: the same fault found twice, listed once)* | 9 |
| F026 | EC-ADM-106 | Insights | Medium | '+ New KPI' with the code of an existing KPI overwrote 'Jobs closed' (target 12 lost); '/ 0' and 'ninety' accepted | 5 |
| F027 | EC-ADM-107 | Insights | Medium | A future period (2027-03) frozen; a frozen period re-frozen four times without warning | 2 |
| F028 | EC-ADM-105 | Insights | Medium | Coordinator, Branch Manager and Director can still save company KPI targets and snapshots (known) | 3 |
| F029 | EC-E2E-001 | End-to-end | High | A report started by Ahmedabad on a Mumbai-sold job is saved with no client and no vendor, and Ahmedabad cannot fix it | 11 |
| F030 | EC-E2E-001 | End-to-end | Medium | 'Start next stage report' began a new series at 000001 instead of the next number | 10 |
| F031 | EC-E2E-001 | End-to-end | High | Billing-workspace draft for a Mumbai contract was issued in Ahmedabad's series (AHM/2627/0001); Mumbai shows ₹0 billed; due date ignores 45 credit days | 9 |
| F032 + F074 | EC-E2E-007 + EC-MON-003 | End-to-end + Money | High | With 'Our state' blank, a Maharashtra client is charged CGST ₹900 + SGST ₹900 instead of IGST ₹1,800, and the invoice can be issued *(duplicate: the same fault found twice, listed once)* | 9 |
| F033 + F113 | EC-E2E-005 + EC-OPS-085 | End-to-end + Operations | High / Medium | Contract openings are not office-limited: Ahmedabad signed and opened Mumbai's contract MUM/C/2026-27/00001, and Mumbai's Branch Manager opened an Ahmedabad contract *(duplicate: the same fault found twice, listed once)* | 1 |
| F034 | EC-E2E-006 | End-to-end | Medium | Test KAM (Mumbai) made a costing with Ahmedabad as executing office and was locked out of it at once | 11 |
| F035 | EC-E2E-001 | End-to-end | Low | On 17 Nov 2026 the visit days of 19 and 20 Nov 2026 could be closed | 6 |
| F036 | EC-E2E-001 | End-to-end | Low | A quotation made from a costing loses the lead, the deal and the contact (Meera Kulkarni) | 11 |
| F037 | EC-E2E-001 | End-to-end | Low | Two screens disagree by 46 paise on the same job (₹5,541 vs ₹5,540.54) | 9 |
| F038 | EC-E2E-001 | End-to-end | Low | Finance tile reads 'Unbilled ₹4,695.38 — 0 job(s) to invoice' while two units are due | 9 |
| F039 | EC-E2E-025 | End-to-end | High | Posting a requisition to the Marketplace publishes our salary build-up and 23.8% margin to outside professionals | 8 |
| F040 | EC-E2E-010 | End-to-end | Medium | An offer on a ₹50,000-a-month structure stores CTC ₹0; the hired person's cost on every job reads ₹0 | 9 |
| F041 + F209 | EC-E2E-011 + EC-REC-060 | End-to-end + Recruitment | Medium | After an offer is declined the candidate stays 'Locked — in recruitment with Ahmedabad' (decline also accepted with no reason) *(duplicate: the same fault found twice, listed once)* | 14 |
| F042 | EC-E2E-030 | End-to-end | Medium | Accepting a TB Steel Works portal request makes CALL-00002 with no office, contract or value; the coordinator is then locked out | 11 |
| F043 | EC-E2E-003 | End-to-end | Medium | Cancelling a work order leaves its job open and on Test Inspector's schedule for 19–20 Nov | 14 |
| F044 | EC-E2E-015 | End-to-end | Low | A nonconformity raised from JOB-00001 stores no client | 11 |
| F045 | EC-E2E-015 | End-to-end | Medium | 'CAPA effectiveness' stays 'No data' after a CAPA was verified effective (known) | 14 |
| F046 | EC-E2E-001 | End-to-end | Medium | Known money faults seen again: contract value with GST, revenue with GST, due date = invoice date, man-month valued per day | 9 |
| F047 | EC-E2E-021 | End-to-end | Medium | A ₹21,000 voucher sent back (not approved) already counts as job cost; profit shows −₹16,709 | 9 |
| F048 | EC-MKT-002 | Marketplace | Low | Posting form accepts rate from ₹5,000 above rate to ₹3,000 and end before start | 6 |
| F049 | EC-MKT-006 | Marketplace | Medium | A 10 man-day booking shows as '1 man-days'; saving it unchanged rebooks 1 day (₹3,200 instead of ₹32,000) | 9 |
| F050 | EC-MKT-008 | Marketplace | Low | Desk voucher boxes refuse paise (₹1,250.50) and the message has a blank | 9 |
| F051 | EC-MKT-009 | Marketplace | Low | Commission strip shows 'Settled ₹0' after the voucher is marked Paid | 9 |
| F052 | EC-MKT-013 | Marketplace | Medium | Empty commission save sets commission 0%; 150% silently becomes 100% | 7 |
| F053 | EC-MKT-036 | Marketplace | Medium | Desk 'Record booking' accepts −₹500 a day and end before start | 7 |
| F054 | EC-MKT-037 | Marketplace | Medium | A Completed engagement can be moved back to Booked | 2 |
| F055 | EC-MKT-038 | Marketplace | High | Send to billing multiplies by people needed: ₹30,000 billed for one person booked 5 days at ₹3,000 | 9 |
| F056 | EC-MKT-044 | Client portal | High | Without a plan, 'Use template' and 'Duplicate → Open' still post live and use no credit | 3 |
| F057 | EC-MKT-045 | Client portal | Low | Refused award/status actions still flash 'Updated.' | 13 |
| F058 | EC-MKT-048 | Client portal | High | Client switches a ₹6,200/day booking to 2 man-months; billing would be ₹12,400 for two months | 9 |
| F059 | EC-MKT-051 | Client portal | Low | 'Return for clarification' accepts an empty note | 14 |
| F060 | EC-MKT-057 | Client portal | Medium | Coupon of 150% saved; using it subscribes Starter at ₹0 without payment | 7 |
| F061 | EC-MKT-067 | Client portal | Medium | The founder of a self-service client company is not its portal admin; the Team page gives 'not found' | 14 |
| F062 | EC-MKT-070 | Client portal | Low | 'Switch it off' says every portal address answers 'not found', but sign-in stays open | 13 |
| F063 | EC-MKT-072 | Client portal | Medium | 'Open concerns' tile links to a page that does not exist | 12 |
| F064 | EC-MKT-078 | Professional portal | Low | Mobile 'abc' accepted on professional sign-up | 7 |
| F065 | EC-MKT-079 | Professional portal | Medium | Applying twice to the same job uses a paid credit with nothing applied | 5 |
| F066 | EC-MKT-084 | Professional portal | Low | Certificate expiry before its issue date, and project end before start, saved | 6 |
| F067 | EC-MKT-099 | Marketplace | Low | Bench day rate −₹100 saved; allocation to a Closed requirement accepted | 7 |
| F068 | EC-MKT-112 | Marketplace | Medium | Second coupon with the same code 'EC10' saved; 150% saved | 5 |
| F069 | EC-MON-044 | Money | High | Mumbai coordinator opens an Ahmedabad client's full ledger by typing the address | 1 |
| F070 | EC-MON-039 | Money | High | Mumbai coordinator matched and unmatched money on Ahmedabad invoices (AHM/2627/0001 → Paid) | 1 |
| F071 | EC-MON-061 | Money | High | Old 'manual invoice entry' on a job overwrites figures after a real invoice: revenue became ₹99,999 | 9 |
| F072 | EC-MON-062 | Money | High | Mumbai coordinator changed an Ahmedabad job's invoice number, amount (₹1) and 'payment received' | 1 |
| F073 | EC-MON-080 | Money | High | Mumbai coordinator ended an Ahmedabad job's billing early (15 Nov 2026) and changed its terms | 1 |
| F075 | EC-MON-049 | Money | Medium | Ageing detail: Due always 'not set', Job column repeats the invoice, Open goes to the wrong record | 12 |
| F076 | EC-MON-059 | Money | Medium | After a part payment the home screen shows two Outstanding figures (₹44,250 and ₹34,250) | 9 |
| F077 | EC-MON-060 | Money | Medium | Money desk counts only deputations: 7 overdue invoices show as '0 overdue' | 9 |
| F078 | EC-MON-056 | Money | Medium | To bill shows each deputation at the whole order value: ₹75,000 for a ₹37,500 contract | 9 |
| F079 | EC-MON-028 | Money | Medium | Credit note GST defaults to 18%: a 12% invoice credited ₹180 tax on ₹1,000 (₹120 was charged) | 9 |
| F080 | EC-MON-030 | Money | Medium | Credit note dated 31 Mar 2026 accepted against an invoice of 17 Nov 2026 | 6 |
| F081 | EC-MON-069 | Money | Medium | Default GST 150% saved as 100% with 'saved'; new lines then charge 100% GST | 7 |
| F082 | EC-MON-068 | Money | Medium | An invoice already sent to Tally can be cancelled with no warning or reversing entry | 2 |
| F083 | EC-MON-033 | Money | Medium | Pressing Record twice records two receipts of ₹41,300 with the same UTR | 5 |
| F084 | EC-MON-091 | Money | Medium | Business Unit Head is refused Office costs and Month-end cost run although the list gives Edit | 3 |
| F085 | EC-MON-088 | Money | Medium | Renewing a contract number accepts a number already in use (two EC-BN-0099) and end before start | 5 |
| F086 | EC-MON-046 | Money | Low | Credit-note links on the ledger open 'Not found' | 12 |
| F087 | EC-MON-047 | Money | Low | Same-day ledger rows put the credit note before its invoice, showing a false negative balance | 9 |
| F088 | EC-MON-089 | Money | Low | Negative office cost saved; month-end run then shows two different cost figures | 7 |
| F089 | EC-MON-090 | Money | Low | Overhead 150% and contingency −10% accepted | 7 |
| F090 | EC-MON-019 | Money | Low | Impossible invoice dates (30 Feb 2026) and text ('abc') stored | 6 |
| F091 | EC-MON-043 | Money | Low | Receipt 'How it came' accepts any value ('ZZZ') | 7 |
| F092 | EC-MON-011 | Money | Low | 'Add the line' pressed twice adds the line twice | 5 |
| F093 | EC-MON-081 | Money | Low | 'Released — those units are due to bill now' shown when nothing was released | 13 |
| F094 | EC-MON-017 | Money | Medium | Invoice number follows the year the draft was started, not the invoice date (known) | 10 |
| F095 | EC-MON-053 | Money | High | Printed invoice says 'Settled so far ₹1.00' on every issued invoice (known) | 9 |
| F097 | EC-MON-021 | Money | Medium | Roles allowed to raise invoices have no button to start a draft (known) | 12 |
| F098 | EC-MON-001 | Money | Medium | Drafts get due date = invoice date, ignoring the client's 30 credit days (known) | 9 |
| F099 | EC-MON-052 | Money | Low | Draft rows in the Invoices list have an empty link (known) | 12 |
| F100 | EC-MON-057 | Money | Low | An empty draft invoice is made when nothing is ticked (known) | 7 |
| F101 | EC-MON-071 | Money | Low | Second sync reports an old billable event as newly found (known) | 13 |
| F102 | EC-MON-066 | Money | Low | Downloading the Tally CSV preview marks vouchers as handed over (known) | 14 |
| F103 | EC-MON-073 | Money | Low | No Cancel button on the billable-events board (known) | 12 |
| F104 | EC-MON-036 | Money | Low | Customer 360 'Received' leaves out TDS: ₹54,000 shown for ₹54,000 + ₹5,000 TDS (known) | 9 |
| F105 | EC-OPS-031 | Operations | High | A work order with a blank executing office is treated as Ahmedabad's: Ahmedabad opened and edited Mumbai's own work orders | 11 |
| F106 | EC-OPS-032 | Operations | High | For Mumbai's own work order the allocation form picks Ahmedabad and demands an inter-office credit | 11 |
| F107 | EC-OPS-049 | Operations | High | An expense line is deleted just by opening a link, with no confirmation, even by another branch | 1 |
| F108 | EC-OPS-061 | Operations | High | Test Coord2 (Mumbai) set an Ahmedabad deputation to Cancelled and added a timesheet row | 1 |
| F109 | EC-OPS-064 | Operations | High | An Approved attendance period can go back to Draft while its 3 man-days stay billable; reversed and duplicate periods accepted | 2 |
| F110 | EC-OPS-041 | Operations | High | A job saved with Man-days 0 adds nothing to the contract: 15 booked days show as '10 of 10' | 9 |
| F111 | EC-OPS-040 | Operations | Medium | Contract check ignores the job being added: a 5-day job allocated with 1 of 10 days left; no 'running low' warning | 9 |
| F112 | EC-OPS-043 | Operations | Medium | Contract exceptions not office-limited: Mumbai's Branch Manager endorsed an Ahmedabad exception | 1 |
| F114 | EC-OPS-033 | Operations | Medium | Ahmedabad's Branch Application Manager deleted a Mumbai work order | 1 |
| F115 | EC-OPS-048 | Operations | Medium | Another branch's Branch Manager reopened a locked Ahmedabad job; lock texts show raw dates | 1 |
| F116 | EC-OPS-071 | Operations | Medium | HR reconciliation shows every branch's staff present/leave days | 1 |
| F117 | EC-OPS-073 | Operations | Medium | Any coordinator can mark another branch's inspector on leave | 1 |
| F118 | EC-OPS-077 | Operations | Medium | Recurring schedules are not office-limited: Ahmedabad made and ran a Mumbai schedule | 1 |
| F119 | EC-OPS-076 | Operations | Medium | A monthly schedule from 31 Oct jumps to 1 Dec, skipping November; made work orders have no number | 14 |
| F120 | EC-OPS-078 | Operations | Medium | Every late report counts as on time in ratings: 18 days late scores 73, same as on time | 14 |
| F121 | EC-OPS-039 | Operations | Medium | Quotation pickers return another branch's client quotes, totals and rates | 1 |
| F122 | EC-OPS-050 | Operations | Medium | Reassign works on a closed job with no reason; reschedule to 29 Feb 2026 accepted and ignored | 2 |
| F123 | EC-OPS-057 | Operations | Medium | A closed work order can still be allocated and the e-mail goes out (known) | 2 |
| F124 | EC-OPS-034 | Operations | Medium | Deleting a work order leaves its ₹8,000 billable event, reports and expenses (known) | 9 |
| F125 | EC-OPS-012 | Operations | Low | When Finance is the claimant, 'waits for' names Finance itself, with no warning | 13 |
| F126 | EC-OPS-018 | Operations | Low | Voucher totals keep three decimals (₹12,833.333) instead of paise | 9 |
| F127 | EC-OPS-044 | Operations | Low | A granted contract exception can be Refused afterwards, overwriting who granted it | 2 |
| F128 | EC-OPS-028 | Operations | Low | Work order refused if the browser did not fill 'Total credit', although the app works it out itself | 14 |
| F129 | EC-OPS-063 | Operations | Low | Timesheet hours −4 and 30 accepted (known) | 7 |
| F130 | EC-OPS-069 | Operations | Low | A shift crossing midnight cannot be closed (known) | 14 |
| F131 | EC-OPS-086 | Operations | Low | Empty work order saved with a number; required date before received date accepted (known) | 7 |
| F132 | EC-OPS-089 | Operations | Low | No 'someone else saved' warning on the first edit from two tabs (known) | 14 |
| F133 | EC-QUAA-006 | Quality | Medium | A long Hindi/Gujarati nonconformity title is cut inside a letter and then vanishes | 14 |
| F134 | EC-QUAA-008 | Quality | Low | Pressing 'Raise it' twice makes two numbered nonconformities | 5 |
| F135 | EC-QUAA-009 | Quality | High | Nonconformities raised without a branch are treated as Ahmedabad's: Mumbai loses its own record | 11 |
| F136 | EC-QUAA-014 | Quality | Low | An owner typed as a name gets a reminder that can never arrive | 13 |
| F137 | EC-QUAA-020 | Quality | Medium | Empty Owner and date erases the nonconformity's due date (known) | 7 |
| F138 | EC-QUAA-024 | Quality | Medium | Containment, disposition and owner can be changed on a CLOSED nonconformity | 2 |
| F139 | EC-QUAA-025 | Quality | Low | A closed nonconformity can be closed again, overwriting the close note | 2 |
| F140 | EC-QUAA-027 | Quality | Medium | Reopen on a not-closed nonconformity jumps it to 'Disposition agreed' | 2 |
| F141 | EC-QUAA-028 | Quality | Medium | Nonconformity pages are not office-checked: another branch can open, assign and classify | 1 |
| F142 | EC-QUAA-029 | Quality | Low | Two history panels on one nonconformity (known) | 14 |
| F143 | EC-QUAA-031 | Quality | Medium | The complaint and client are dropped when a nonconformity is saved from a complaint (known) | 11 |
| F144 | EC-QUAA-035 | Quality | Low | Departures store the login name (test.qualityhead) instead of the person's name | 14 |
| F145 | EC-QUAA-037 | Quality | Low | An unknown status still says 'Departure updated.' | 13 |
| F146 | EC-QUAA-041 | Quality | Medium | After a departure status change the app can send the browser to an outside website | 14 |
| F147 | EC-QUAA-044 | Quality | Medium | Disputes have no Decide button; a hidden request lets a Branch Manager decide and re-decide | 3 |
| F148 | EC-QUAA-046 | Quality | Medium | A Branch Manager can approve his own extension by a direct request | 3 |
| F149 | EC-QUAA-047 | Quality | Medium | An extension with a blank date erases the due date; earlier or past dates accepted as 'extensions' | 7 |
| F150 | EC-QUAA-055 | Quality | Low | 'Done' on an action already done is accepted again; the action is not checked to belong to that CAPA | 2 |
| F151 | EC-QUAA-060 | Quality | Low | Refusal to close a CAPA talks about 'archive', a word the screen never uses | 13 |
| F152 | EC-QUAA-061 | Quality | Medium | Plan, cause and effectiveness of a CLOSED CAPA can be changed; it can be closed again | 2 |
| F153 | EC-QUAA-063 | Quality | Medium | 'Escalate' closes a CAPA without the close right; a second press opens another CAPA | 3 |
| F154 | EC-QUAA-064 | Quality | Medium | CAPA deadline settings never save, and no message says so (known) | 13 |
| F155 | EC-QUAA-066 | Quality | High | CAPA and complaint overdue e-mails are sent with an empty To address, so they never arrive | 13 |
| F156 | EC-QUAA-080 | Quality | Low | A CAPA can be raised on a complaint that was never upheld | 3 |
| F157 | EC-QUAA-084 | Quality | Medium | Outcome and CAPA reference of a CLOSED complaint can be changed | 2 |
| F158 | EC-QUAA-086 | Quality | Medium | 'Not ours' complaints cannot be closed: telling needs an outcome that is never asked for | 14 |
| F159 | EC-QUAA-089 | Quality | Medium | Complaint deadlines and the published description never save, silently (known) | 13 |
| F160 | EC-QUAA-091 | Quality | Low | Double press creates two numbered complaints | 5 |
| F161 | EC-QUAA-094 | Quality | Low | An impartiality declaration with no person is accepted | 7 |
| F162 | EC-QUAA-097 | Quality | Low | An undertaking signed for a future date shows as 'Lapsed' | 14 |
| F163 | EC-QUAA-098 | Quality | Low | NDA end: 31 Jan 2027 + 1 month shows 03 Mar 2027 instead of 28 Feb 2027 | 14 |
| F164 | EC-QUAA-100 | Quality | Low | Containment written on a breach later never reaches its nonconformity | 11 |
| F165 | EC-QUAA-102 | Quality | Low | A CLOSED breach can be saved again; 'party told' can be blanked | 2 |
| F166 | EC-QUAA-109 | Quality | Medium | Saving a failure row erases its CAPA reference, so a duplicate CAPA gets raised | 7 |
| F167 | EC-QUAA-110 | Quality | Low | A resolved failure can be edited back to 'not answered'; an invented CAPA number passes | 2 |
| F168 | EC-QUAB-004 | Quality | Low | Auditor 'EC  Kiran Shah' (double space) passes as different from area owner 'EC Kiran Shah' | 14 |
| F169 | EC-QUAB-016 | Quality | Medium | A CLOSED internal audit still accepts new findings and date changes | 2 |
| F170 | EC-QUAB-018 | Quality | Medium | A finding that raised CAPA-2026-001 can be removed, even on a closed audit | 2 |
| F171 | EC-QUAB-021 | Quality | Low | Audit cycle below the 30-day minimum (29) stored; 0 says 'Saved.' while nothing is saved | 7 |
| F172 | EC-QUAB-032 | Quality | Medium | Management review says 'no earlier review' although MR-2026-01 exists | 14 |
| F173 | EC-QUAB-038 | Quality | Low | A CAPA raised from a review decision gets the wrong source, reference 'MR-2' and a different due date | 11 |
| F174 | EC-QUAB-040 | Quality | Medium | After a review is completed its open decisions lose the 'Done' button; reminders never stop | 12 |
| F175 | EC-QUAB-041 | Quality | Medium | A completed management review can be changed; the chair was blanked | 2 |
| F176 | EC-QUAB-054 | Quality | Low | Short identity numbers (4 characters or fewer) show in full in the masked column | 8 |
| F177 | EC-QUAB-060 | Quality | Low | 'A copy went out' can be logged on a document already redacted | 2 |
| F178 | EC-QUAB-061 | Quality | Medium | Lowered retention (730 → 90 days) is shown on screen but not applied to copies held | 13 |
| F179 | EC-QUAB-065 | Quality | Low | A filter that matches nobody says 'No freelancers yet' although some exist | 13 |
| F180 | EC-QUAB-073 | Quality | High | A site typed as words is stored as 0, so a blocking site requirement is never enforced (Test SrInspector allocated without medical) | 14 |
| F181 | EC-QUAB-083 | Quality | Low | Re-activating a suspended authorisation needs no reason and erases the suspension record | 14 |
| F182 | EC-QUAB-087 | Quality | Medium | The reason for allocating with a lapsed certificate is stored but shown nowhere | 14 |
| F183 | EC-QUAB-090 | Quality | Low | Names in the Training watch link to a page that opens nothing | 12 |
| F184 | EC-QUAB-091 | Quality | Low | Authorisation for 'one client' with no client shows as live; To before From accepted as Active | 6 |
| F185 | EC-QUAB-101 | Quality | Low | Certificate column says 'none on file' for a lapsed or future-dated certificate | 13 |
| F186 | EC-QUAB-102 | Quality | Medium | Calibration 'Valid to': 31 Jan 2026 + 1 month = 3 Mar 2026, so 'in calibration' runs days too long | 14 |
| F187 | EC-QUAB-108 | Quality | High | Instruments can be added to and removed from an ISSUED report | 2 |
| F188 | EC-QUAB-109 | Quality | Medium | Quarantining an instrument does not flag the issued reports it was used on | 14 |
| F189 | EC-QUAB-111 | Quality | Low | An instrument can be added to report number 99999, which does not exist | 7 |
| F190 | EC-REC-006 | Recruitment | Medium | Hiring request shows ₹3,000 for 1 person × ₹1,500 a man-day × 2 months; the requisition says ₹66,000 | 9 |
| F191 | EC-REC-011 | Recruitment | Medium | Requisition from a 12-month, 2-person hiring request shows 1 month only (₹1.65 L against ₹19,80,000) | 9 |
| F192 | EC-REC-012 | Recruitment | Medium | A Coordinator sees 'Loaded cost 66,000, billed at 82,500 (20.0% margin)' and salary 50,000 | 8 |
| F193 | EC-REC-017 | Recruitment | Medium | Mumbai's duplicate warning shows full details of a candidate locked with Ahmedabad | 8 |
| F194 | EC-REC-019 | Recruitment | High | After Ahmedabad rejects a candidate, Mumbai cannot take him on: every route is refused | 12 |
| F195 | EC-REC-024 | Recruitment | Medium | Offer compares monthly CTC with yearly budget: 'Budget ₹6,00,000 · ₹-5,50,000' | 9 |
| F196 | EC-REC-026 | Recruitment | Low | Offer letter prints the joining date as 2026-12-01 | 14 |
| F197 | EC-REC-027 | Recruitment | High | Test Coord opens the offer letter and sees '₹50,000 a month (₹6,00,000 a year)' | 8 |
| F198 | EC-REC-028 | Recruitment | Medium | Branch Manager approves a DRAFT offer at 13.3% margin; the margin reason is never asked | 3 |
| F199 | EC-REC-030 | Recruitment | Low | 'Hired' message says 'you can now allocate deputations' before the person has joined | 13 |
| F200 | EC-REC-033 | Recruitment | Medium | Candidate saved with an empty name and −3 years' experience (known) | 7 |
| F201 | EC-REC-039 | Recruitment | Low | Coordinator sees the Budget column (₹66,000 loaded cost) on the Requisition register | 8 |
| F202 | EC-REC-042 | Recruitment | Low | Delegation from 29 Feb 2027 (not a real date) saved | 6 |
| F203 | EC-REC-044 | Recruitment | Medium | Public careers page shows internal requisition, quotation and costing numbers | 8 |
| F204 | EC-REC-045 | Recruitment | High | Posting generator copies salary and margin into the public careers page | 8 |
| F205 | EC-REC-046 | Recruitment | Medium | Test Coord2 (Mumbai) receives an Ahmedabad posting text including the margin | 8 |
| F206 | EC-REC-050 | Recruitment | Medium | Test Coord approved placement commercials (bill ₹82,500) by a direct request | 3 |
| F207 | EC-REC-056 | Recruitment | Medium | Ahmedabad coordinator reads Mumbai client TB Steel Works' contact mobile and e-mail | 8 |
| F208 | EC-DIR-014 | Directory | Low | Working norms −5 and 200 hours, 8 days stored as 48 hours / 6 days with 'saved' | 7 |
| F210 | EC-REP-029 | Reporting | High | Mumbai Branch Manager approved an Ahmedabad report by sending the action | 1 |
| F211 | EC-REP-034 | Reporting | High | Test BranchMgr2 (Mumbai) issued an Ahmedabad report; the client was notified | 1 |
| F212 | EC-REP-060 | Reporting | High | Any Finalise holder from any office deletes an ISSUED report with no reason; the client's verify page then fails | 2 |
| F213 | EC-REP-091 | Reporting | High | Only one hand-raised hold/witness point can exist company-wide; the second gives an error | 14 |
| F214 | EC-REP-097 | Reporting | High | Ahmedabad Coordinator edits and an unassigned Branch Manager endorses a Mumbai endorsement | 1 |
| F215 | EC-REP-100 | Reporting | High | An Inspector can change a vendor's status from Prospect to Approved | 3 |
| F216 | EC-REP-069 | Reporting | Medium | Another office's Finalise holder vets and forwards an Ahmedabad report | 1 |
| F217 | EC-REP-070 | Reporting | Medium | Vetting 'Return for correction' sends an Under-review report to Draft and deletes its approval steps | 2 |
| F218 | EC-REP-055 | Reporting | Medium | Evidence review shows every office's flagged photos; an inspector can clear another inspector's flag | 3 |
| F219 | EC-REP-092 | Reporting | Medium | Test Director (view) and Test BAM (setup only) can clear hold/witness points | 3 |
| F220 | EC-REP-012 | Reporting | Medium | A second report for the same client and date is refused as a duplicate even with a different PO | 14 |
| F221 | EC-REP-013 | Reporting | Medium | New report offers all 17 offices; filing under another office locks the creator out | 11 |
| F222 | EC-REP-051 | Reporting | Medium | An inspector from another office on a deputation has no job page, so no 'Start next stage report' | 12 |
| F223 | EC-REP-005 | Reporting | Low | TB Group Parent and TB Group Child share one report series (TBGROU …/000001, /000002) | 10 |
| F224 | EC-REP-031 | Reporting | Low | 'Finalize & issue' shown to the report's own approver, who is then refused | 12 |
| F225 | EC-REP-057 | Reporting | Low | 'Submit for approval' shown to the contracting office, which is refused | 12 |
| F226 | EC-REP-062 | Reporting | Low | 'Draft Release Note' shown on an approved, not issued report | 12 |
| F227 | EC-REP-008 | Reporting | Low | Serial width outside 3–10 stored as 6 with 'saved' | 10 |
| F228 | EC-REP-066 | Reporting | Low | 'Adjust dates' with an empty value erases an issued report's issue date (known) | 7 |
| F229 | EC-REP-074 | Reporting | Low | An empty approval-rule form is saved as a rule (known) | 7 |
| F230 | EC-REP-073 | Reporting | Low | A temporary approver cover with To before From saved (known) | 6 |
| F231 | EC-SALA-001 | Sales | Medium | A Business Development Manager can file a lead in any branch and is then locked out | 11 |
| F232 | EC-SALA-002 | Sales | Low | Lead 'Expected close' accepts 29 Feb 2027 and 2026-13-45 | 6 |
| F233 | EC-SALA-003 | Sales | Medium | Lead edit saves an empty company, −₹5,000 and an unreal date | 7 |
| F234 | EC-SALA-006 | Sales | Low | A 0-byte file is refused as 'over 8 MB'; a big file loses the person's place | 13 |
| F235 | EC-SALA-007 | Sales | Low | Downloaded lead files lose every Hindi/Gujarati letter in the name | 14 |
| F236 | EC-SALA-008 | Sales | Low | Removing a lead document writes nothing in the lead's history | 14 |
| F237 | EC-SALA-011 | Sales | Medium | 'They said yes' makes an enquiry with no business unit and a customer with no home branch | 11 |
| F238 | EC-SALA-013 | Sales | Medium | Bulk 'Mark lost' leaves leads on their stage; reopen returns them to the first stage | 14 |
| F239 | EC-SALA-017 | Sales | Medium | A Business Development Manager can open a deal in another branch and is then locked out | 11 |
| F240 | EC-SALA-019 | Sales | Low | Deal edit saves an empty name | 7 |
| F241 | EC-SALA-020 | Sales | Medium | A deal can be moved to a stage of another pipeline and vanishes from its board | 14 |
| F242 | EC-SALA-022 | Sales | Medium | A deal-stage rule sent to Branch Managers, who cannot open deals, leaves the deal stuck | 12 |
| F243 | EC-SALA-023 | Sales | Low | 'Withdraw the request' shown to people who are refused | 12 |
| F244 | EC-SALA-024 | Sales | Low | After a withdrawal the app can send the browser to an outside website | 14 |
| F245 | EC-SALA-026 | Sales | High | A Business Development Manager created work order CALL-00001 (₹25,00,000) with no quotation or contract | 3 |
| F246 | EC-SALA-027 | Sales | High | The accepted quotation can be detached from a won deal, so one sale makes two contracts | 2 |
| F247 | EC-SALA-028 | Sales | Medium | Ahmedabad salesperson attached a Mumbai quotation (₹9,16,860) to a deal and saw it | 1 |
| F248 | EC-SALA-029 | Sales | Medium | Blocked customer TB Defaulter Co still gets new quotations, with no banner | 3 |
| F249 | EC-SALA-031 | Sales | Medium | Test KAM (Mumbai) made a deal from an Ahmedabad lead that was already Won | 1 |
| F250 | EC-SALA-036 | Sales | Low | A funnel may have two Won stages, or Won first; new deals then count at 100% | 14 |
| F251 | EC-SALA-041 | Sales | Low | 'Bring leads across' says 'You cannot create leads' to people who can | 13 |
| F252 | EC-SALA-050 | Sales | Medium | An existing enquiry can be switched to a blocked customer | 3 |
| F253 | EC-SALA-058 | Sales | High | Costing: ₹50,000 × 10 man-months gives ₹5,00,000.03, pushing it into a second approval level | 9 |
| F254 | EC-SALA-060 | Sales | Low | Costing approval box says '₹5 L' and the print drops paise | 9 |
| F255 | EC-SALA-064 | Sales | Medium | A quotation from a costing whose enquiry has a typed client is made with no client | 11 |
| F256 | EC-SALA-069 | Sales | Medium | Costing directions disagree: CTC ₹50,000 → rate ₹71,531.25, but that rate → CTC ₹52,499 | 9 |
| F257 | EC-SALA-072 | Sales | Low | ✕ on a role of a submitted costing silently does nothing | 13 |
| F258 | EC-SALB-036 | Sales | High | Test MktMgr set a cover for another approver; Test BDM then approved his own quotation | 4 |
| F259 | EC-SALB-074 | Sales | High | Billing schedule saved on another branch's quotation; on an approved quotation it cancels the approval | 1 |
| F260 | EC-SALB-046 | Sales | Medium | Test KAM (Mumbai) downloaded the follow-up e-mail of an Ahmedabad quotation | 1 |
| F261 | EC-SALB-060 | Sales | Medium | Files and PO number of a SENT quotation can still be deleted or changed | 2 |
| F262 | EC-SALB-055 | Sales | Medium | Revising an old revision from another tab shows an error page | 14 |
| F263 | EC-SALB-053 | Sales | Medium | Revise offered on an Accepted quotation; the won order then leaves Accounts' list | 2 |
| F264 | EC-SALB-038 | Sales | Medium | Pre-order checklist points in Gujarati are cut, then show blank; a later Save loses all points | 14 |
| F265 | EC-SALB-012 | Sales | Medium | Gujarati letters in a customer name print as '?' on the quotation PDF | 14 |
| F266 | EC-SALB-014 | Sales | Medium | '+ Add line' resets the order type of every line | 14 |
| F267 | EC-SALB-065 | Sales | Medium | Contract end date 1 Apr 2026 before start 31 Mar 2027 accepted | 6 |
| F268 | EC-SALB-062 | Sales | Medium | Contract value from a ₹10,00,000 + 18% quotation is ₹11,80,000 (known) | 9 |
| F269 | EC-SALB-045 | Sales | Low | Follow-ups accept 29 Feb 2026, '31/02/2026' and status 'WHATEVER' | 6 |
| F270 | EC-SALB-047 | Sales | Low | Finance shown three buttons that all refuse; 'Follow-up removed' when nothing was removed | 12 |
| F271 | EC-SALB-013 | Sales | Low | Quotation live total shows ₹9,439.99 while ₹9,440.00 is saved | 9 |
| F272 | EC-SALB-004 | Sales | Low | Quotation register drops paise (₹5,89,999.99 shown as ₹5,90,000) | 9 |
| F273 | EC-SALB-028 | Sales | Low | Pressing Approve twice says 'You are not an approver' — wrong reason | 13 |
| F274 | EC-SALB-052 | Sales | Low | Approved, Pending, Rejected or Expired quotations can be marked Lost; your lifecycle allows only Draft and Sent | 2 |
| F275 | EC-SALB-037 | Sales | Low | Delegation dated 31 Feb 2026 accepted | 6 |
| F276 | EC-SALB-043 | Sales | Low | Quotation PDF e-mailed to the client has no revision mark (-R1) | 10 |
| F277 | EC-SALB-058 | Sales | Low | A file of exactly 8 MB refused as 'too large — the limit is 8 MB' | 13 |
| F278 | EC-SALB-059 | Sales | Low | Downloaded quotation files with Hindi/Gujarati names come out as '_____.pdf' | 14 |
| F279 | EC-SALB-061 | Sales | Low | After a re-edit is granted there is no Edit button | 12 |
| F280 | EC-SALB-069 | Sales | Low | Milestone shares shown rounded (three of 33.33% read 99.99%); amounts without paise | 9 |
| F281 | EC-SALB-011 | Sales | Low | Quotation subject has no length limit; on the live site a long one may be cut silently | 7 |

**End of appendix:** 277 rows, 281 faults (four rows each hold a fault found twice).
