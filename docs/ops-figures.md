# Operations figures — one definition each

*Stage 1 (owner, 24 Nov 2026: "approve B0–B5, all as recommended").
The code is `phpapp/lib/opskpi.php`. Every card, chip, register filter, MIS line,
inspector tile and e-mail digest that shows one of these figures asks that file.
Nothing on a screen works a figure out on its own.*

Stage 0 found the same figure worked out in up to four places. For example,
"not yet scheduled" read 7 / 0 / 0 / 20 on four screens at the same moment.
Each card also opened an unfiltered list, so nobody could check the number
behind it.

**The rule now:** each Operations-home card opens its register filtered by the
same definition, under the same branch scope. The number on the card is the
number of rows you see. Example: the card says "No engineer yet 7", you click it,
and the work-order register lists those 7 and says *Showing: No engineer yet*.

This page grants no permission and adds no status. It reads only existing fields.

## Words used below

| Word | Meaning |
|---|---|
| **Live work order** | Not closed, cancelled or rejected, on either of the two status fields. |
| **Done** | Every job under the work order that is not cancelled is closed, and there is at least one such job. The work-order page uses the same rule. |
| **Live job** | A job that is not cancelled (`stage` ≠ CANCELLED, which is set by *Cancel assignment*). |
| **Somebody on it** | A live job with a person or a sub-contractor firm, where the hold was not released and the assignment was not declined. A declined or released assignment counts as nobody (B4). |

## Work-order figures (the work-order register, `/calls?f=…`)

| Card | Counts | Opens |
|---|---|---|
| New requests | Live work orders awaiting triage (status Received / Draft / Under review) that nobody is on yet | `/calls?f=new` |
| No engineer yet | Live work orders, not done, with nobody on a job. Replaces the four "not yet scheduled" counts. The home's "Waiting for a person" list and the scheduling board use the same rule. | `/calls?f=no_engineer` |
| Clarification open | Live work orders with an open clarification. A clarification left open on a closed order no longer counts. | `/calls?f=clarification` |
| Ready to schedule | Live work orders whose status is Ready for scheduling | `/calls?f=ready` |
| Work orders overdue | Live work orders, not done, whose client's required date has passed | `/calls?f=wo_overdue` |
| Critical | Live work orders, not done, with priority Critical | `/calls?f=critical` |

**Scope for these cards:** the executing office's desk (the rule the home has
always used), within what the register itself may show (office and business
unit). A card can only be the same as before or smaller. It can never show
something the register would hide.

## Job figures (the jobs register, `/jobs?f=…`)

| Card | Counts | Opens |
|---|---|---|
| Today's services | Live jobs scheduled for today | `/jobs?f=today` |
| Jobs overdue | Live, open jobs whose planned end (else their scheduled date) has passed. A cancelled job is never overdue. | `/jobs?f=jobs_overdue` |
| Reports awaiting sign-off | Closed jobs whose report still waits for the reporting manager's sign-off (the job-close track) | `/jobs?f=signoff` |
| Reports to write | Live, open jobs that need a report (a frequency other than "No report") with none uploaded and none issued yet. A job whose report is already issued is not counted, so it can never be both "report to write" and "ready to close". | `/jobs?f=to_write` and the inspector's *My jobs → Reports to write* |

**Scope for these cards:** the jobs register's own scope (office and business
unit). The inspector's tiles count only that inspector's own jobs.

## Report-document figure

| Name | Counts | Where |
|---|---|---|
| Reports awaiting issue | Report documents still in drafting or review (client and professional boards), or approved but not yet issued (*Your pending tasks*) | Client / professional KPI board, dashboard pending tasks |

## Other screens that now read the same definitions

- **Work-order register:**
  - the chips are "No engineer yet" and "Work orders overdue";
  - each row's progress pill (Closed / No engineer yet / In progress / Cancelled / Rejected) is the same rule as the CSV column.
- **Jobs register:** the "overdue" chip and row pill come from *Jobs overdue*. A cancelled job shows "Cancelled" and is counted as neither open nor overdue.
- **MIS / Reports dashboard:**
  - "No engineer yet" (was "Pending scheduling");
  - "Jobs overdue" (was "Overdue closure");
  - cancelled jobs are left out of open, closed and overdue.
- **Weekly / monthly management e-mail:** "jobs overdue" and "Reports awaiting sign-off" (was "awaiting approval": every job with a pending approval, open or closed).
- **Inspector dashboard and My jobs:** "Reports to write" and "Jobs overdue".
