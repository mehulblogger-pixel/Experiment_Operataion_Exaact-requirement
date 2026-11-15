# Operations — decision pack (V51.1)

15 Nov 2026 · All 147 Operations tests were run twice on private copies of V51.1 with made-up data. The second run gave the same result for every scenario test. I checked the serious findings in the program code myself (noted below). Nothing has been changed in the app.

**How to answer:** reply with item numbers, for example *"A1 yes, A2 yes … A12 warn, B ok, C1 agree, C5 cap 100"*. Anything you don't mention stays untouched.

## Where Operations stands

| | Tests |
| --- | --- |
| Passed | 64 |
| Failed (47 defects) | 49 |
| Need your decision (section C) | 13 |
| Blocked (mostly test wording out of date) | 19 |
| The robot cannot do it | 2 |
| **Total** | **147** |

Operations has the most defects of any module so far. Most of them are of three kinds:
- the branch rules are not checked everywhere;
- statuses can be moved where they should not;
- forms accept impossible values (negative hours, an end date before the start date).

## A — Fixes that need your yes (security, permissions, workflow, money)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-OPS-001 (P1)** = Admin **DEF-ADM-002** | A coordinator with no home office sees every branch's work orders. *Same cause as Admin A1. One fix covers both.* | As Admin A1: no office means see nothing until an office is set |
| A2 | **DEF-OPS-046 (P1)** | **A coordinator of another branch can change a work order's status, priority and criticality.** The change is saved first, and only then does the screen say "outside your scope". *Confirmed in the code: these actions check the role but not the branch.* Not fixed in v52/v53 | Check the branch before saving anything |
| A3 | **DEF-OPS-031 (P1)** + 003, 033, 034 | **Inspectors and Senior Inspectors cannot open their own expense vouchers.** *Confirmed in the code: the Inspector role has no voucher right, and the gate lets an inspector into their own jobs but not their own vouchers.* Also: Finance is refused although your decision D-07 gives Finance "View" (003); the Asst. Manager is refused (033); the Business Director is offered Approve and Reopen (034) | Make voucher access match the permission list row by row: own vouchers for inspectors, View for Finance, no Approve for the Director unless you say otherwise |
| A4 | **DEF-OPS-019 (P1)** | A work order that was closed through its status can still have a job allocated, and the assignment e-mail goes out | Refuse allocation on a closed or cancelled work order |
| A5 | **DEF-OPS-015 (P1)** | A work order made by a recurring schedule has **no number and no office**, so no branch sees it; only the Master Admin sees it, with no number | Give it a number and the schedule's office, like any other work order |
| A6 | DEF-OPS-008 | A manager override can move a work order back out of Cancelled or Closed | Treat Cancelled and Closed as final, or allow reopening only with a reason. **Your choice: it changes the lifecycle** |
| A7 | DEF-OPS-002, 037, 011 | Branch scope gaps:<br>• the Operations home and scheduling board show other branches' work orders when the executing office is blank (002);<br>• the deputations list is not limited by branch (037);<br>• the contracting office **cannot** open a work order it forwarded (011) | Apply the branch rule on these screens. Let the contracting office see what it forwarded, as Money A4 also asks |
| A8 | DEF-OPS-004, 020 | A Business Development Manager can open the Operations home and its work lists (004). The Branch Application Manager can allocate jobs (020). Neither is in the permission list | Refuse both, as the permission list says, or tell me the list is wrong |
| A9 | DEF-OPS-029 | Closing a job demands a Final Inspection Report and a Release Note, but "New report" cannot make either. **An inspector cannot close the job at all** (OPS-106) | Either let "New report" make those two types, or stop demanding them. **Your rule** |
| A10 | DEF-OPS-018, 028 | Leave and availability are ignored when allocating (018). Reassigning skips the double-booking check (028) | Warn (not block) when the person is on leave or already booked, the same in both places |
| A11 | DEF-OPS-032 · HN-3 = Money **D1** | The same expense is counted twice. The netting option fixes it only in job profit | As Money D1: count it once everywhere |
| A12 | DEF-OPS-010 · HF-191 | Deleting a work order leaves its report and its **billable event** behind, so a deleted job can still be billed | Delete or cancel them with it, or refuse to delete a work order that has a report or a billable event. **Your choice: billing** |
| A13 | DEF-OPS-022, 021 | The job Money tab hides "other cost", and the Operation Manager sees "profit: Master Admin only" (022). The job's total credit is required by the browser but never calculated (021) | Show other cost; give profit to whoever the permission list says; calculate the total credit. **Money figures: needs your yes** |

## B — Fixes I will make once you say "B ok" (small, no rule change)

**Validation: refuse impossible values with a plain message**
- **Timesheets:** hours of −4 or 30 (024).
- **Attendance periods:** reversed, duplicate or overlapping (025).
- **Work order dates:** a required date before the received date (009).
- **Recurring schedule:** an end before the start (017), and a blank title that says "created" when nothing was saved (016).
- **Amounts:**
  - a negative inter-office credit (012);
  - a negative voucher amount, which today gets a wrong message showing internal field names (047);
  - a ₹0 expense row written when a job is closed with no expenses (030).
- **Empty records:** an empty New work order form that saves a numbered empty work order (006 · HF-190).

**Statuses and counts**
- Closing or cancelling a work order does not change these, because they still read the old status (043 · HN-4 · I-10; not fixed in v52/v53):
  - the home "Open calls" count;
  - the "Unscheduled" tile;
  - the client-portal status.
- The "Report pending" counter opens an unfiltered list (005).

**Missing protections and doors**
- The "someone else saved changes" warning does not work on the first edit of a work order or job (007).
- Deputation status changes have no such warning at all (038).
- Two screens have no menu entry: Attendance review (039 · HF-170) and Ratings settings (042).
- A night shift cannot be closed (041).
- The timesheet ignores the attendance IN/OUT from the dashboard (040).

**Buttons and wording**
- A Close job button is shown to Finance, the Asst. Manager and the SBU Head, who cannot use it (027).
- The hold-points form is shown although that module is not licensed (023).
- Two addresses lead to the same screen (013).
- There is no help text explaining exception versus clarification (014).
- A frozen-month voucher is read-only with no reason given (035).

**Links and counts**
- An NCR raised from a job is not linked to the job (026).
- System status ignores unlinked Senior Inspector logins (044 · HN-6).
- An unlinked inspector login sees "you're all caught up" with all-zero figures instead of "your login is not linked" (045 · HN-6).

**Phone:** the scheduling board cuts off day values (036).

Plus new wording for the out-of-date tests.

## C — Questions only you can answer (my proposal in **bold**)

| # | Test | Question | Proposal |
| --- | --- | --- | --- |
| C1 | OPS-010 | Should New work order require the same fields that "ready for scheduling" checks? | **Yes** |
| C2 | OPS-023 | A second work order for the same client, quote line, site and date | **Warn, don't block** |
| C3 | OPS-029 | A recurring schedule started in the past creates one back-dated work order per press | **No. List the missed periods and ask before creating them** |
| C4 | OPS-056 · OPS-112 | ₹9,99,99,999 misc. and ₹99,99,999 travel close with no warning | **Warn above a ceiling you set. I will not choose the number** |
| C5 | OPS-098 | A complaint penalty of 1,000,000 points is accepted | **Cap it at 100** |
| C6 | OPS-099 | A person with no data scores 62 (three stars) | **Show "no data yet" instead of a score** |
| C7 | OPS-112 | A report upload dated next month is accepted | **Refuse future dates** |
| C8 | OPS-103 | A duplicate stamp serial gives no warning; a future issue date is accepted | **Warn on duplicates; refuse future dates** |
| C9 | SCR-call-edit · SCR-job-edit | A negative quoted rate, sub-contractor cost or other cost is accepted | **Refuse negatives. Money: needs your yes** |
| C10 | OPS-076 | Deputations can move backwards, and the lifecycle is not written down. Can a mobilised deputation be cancelled? | **Write the lifecycle down; backward moves only with a reason** |
| C11 | OPS-077 | Is mobilising more people than asked for allowed? | **Allow, and show "+n over"** |
| C12 | OPS-078 | A manpower line saves with no client | **Require a client or project** |
| C13 | OPS-062 · OPS-066 | The scheduling board limits very long ranges; it loads in 0.1–0.7 s on a small copy | **About 8 weeks at a time, and say so. Target under 3 s on the live server, to be measured there** |
| C14 | OPS-117 | Finance can view the "Needs a person" board | **Fine, as long as Finance cannot allocate** |
| C15 | OPS-125 | The asset register has no delete button | **Keep it that way (assets have history); update the test** |
| C16 | OPS-111 | `docs/01-roles.md` still carries the old Senior Inspector warning | **Update the document** |
| C17 | HF-034 | There are no site-entry document rules on the copy | **You or your team enter the real rules; I test them after** |
| C18 | HF-144 · HF-147 | WhatsApp messages for assignments? A "jobs nearby that day" hint when allocating? | **WhatsApp opt-in later through Connect; the hint yes, as a hint only. Both are new work** |
| C19 | HF-088 | The main program file is now 10,823 lines | **Split it, but only after the current fixes** |

**Billing fact for Money C14 (recorded, not judged):** a job allocated with 0 man-days and "no report" produced, at closure, a billable event of 1 × ₹8,000 and a draft invoice of ₹9,440. Whether that is right depends on the billing rules you still have to write down (Money C14).

## D — Older Operations problems checked again on V51.1

**19 older items checked:**
- **Still exist (8):** HN-3, HN-4, I-10, HN-6, HF-159, HF-170, HF-190, HF-191. Each is now one of the defects above (A2, A11, A12, B).
- **Fixed (5):** HF-117, HF-118, HF-145, HF-146, HF-209.
- **No longer apply (2):** HF-123, HF-135.
- **Need you (4):** HF-034, HF-088, HF-144, HF-147 (C17–C19).

## E — Not built

- E1: WhatsApp notifications.
- E2: a "nearby jobs" hint at allocation.

Both are new work (C18) and not defects.

## F — What is NOT tested yet in Operations

**14 screen checks are blocked, and 5 more ran only in part**
- The 14 blocked checks are mostly blocked by wording: your "Inspection & certification" wording pack, button names, breadcrumbs. Many of their steps could not be exercised with the copy's data.
- The 5 partial runs:
  - New work order: 25 of 157 steps;
  - Edit work order: 60 of 157;
  - Edit job: 119 of 155;
  - Voucher: 41 of 60;
  - Close job: the checker's first save closed the job. The close screen itself is covered by the scenario tests.
- None of these counts as passed.

**Smaller gaps in this run**
- **HF-159:** the branch check on "assign hold" is not proven. The robot sent a wrong value there, so this needs a rerun.
- **HN-4:** the "critical" tile movement could not be shown (it was 0 before and after).
- **OPS-067 step 4:** needs a Recruitment candidate. The Recruitment tester is running now.

**Simulated, not real**
- Camera photos were attached from a file.
- The night shift and "wait for the date" were set in the database.
- Airplane mode was the browser going offline.
- A real WhatsApp message cannot be sent from here.

So the Operations picture is: **5 serious defects (A1–A5; A1 is the same fix as Admin A1), 21 medium and 21 small; 8 older problems still present (all folded into the defects above); 19 questions; 19 screen checks not finished.**

## After you answer

The serious items in all the packs are closely related:
- Admin A1;
- Operations A1–A5;
- Reporting A1–A3.

Most of them come from one gap: who may see or change a record is not checked everywhere. Either the branch check is missing (Operations A2, Reporting A1) or it fails open (Admin A1 = Operations A1), or the "own record" rule is missing (Reporting A2, Operations A3). My recommendation is **one "branch and access" fix batch** covering all of them. It would have the full suite on SQLite and MariaDB, the robot tests of the three modules again, one zip for the test copy, the list of changed files and rollback steps. Then a separate batch for each module's B list.
