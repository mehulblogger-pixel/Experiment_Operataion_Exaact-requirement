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

## One status reading (B2)

- **Job stage, derived.** Job 360, the work-order page, the contract page and
  the schedule registers derive a job's stage from facts. The stored stage field
  is never advanced by the application, so it is not used. The order of checks:
  - closed → *Closed*;
  - *Cancel assignment* → *Cancelled*;
  - put on hold on purpose → *On hold*;
  - a report uploaded, in review or issued → *Report submitted*;
  - the inspection ended with a report owed → *Report pending*;
  - the scheduled or start date reached → *Inspection in progress*;
  - otherwise *Allocated*, or *No engineer yet* when nobody is on the job.

  Function: `job_stage_label()`. The words come from the existing job-stage list.
- **Work-order register:**
  - one status pill per row (the lifecycle status);
  - the progress word sits under it, and is left out when the pill already says the order is closed, cancelled or rejected.
- **Cancelled or rejected work orders are in no count:**
  - Ops home "Open";
  - the backlog;
  - the Director dashboard;
  - Customer 360;
  - the contract summary;
  - the client portal, where the order now reads *Cancelled*.
- **Backlog reason:** "No engineer yet" when nobody is on a job. A declined or released assignment counts as nobody. Statuses show by their label, not their code.

## No false "ready" (B3)

- **Ready for scheduling.**
  - A work order moves to Ready for scheduling only when its details are complete, or when the existing validation override was recorded with a reason (`tosrm_call_ready`).
  - The page says what is missing.
  - The card note now reads "marked ready for scheduling". Older forwarded orders also count here, so the card no longer says "all inputs complete".
- **Client portal.** A job's words follow its dates, compared with today:
  - an end date still ahead is "Inspection under way", not "finished";
  - a start date still ahead is "Scheduled".
- **Card notes say what they count.** Examples:
  - "Reports awaiting sign-off" means closed jobs whose report is not yet signed off;
  - the one-line summary uses the card names.
- **Data health.**
  - The Backlog tab and the Ops desk list open jobs whose report frequency is blank or not on the list (e.g. the demo's "SINGLE"). A person corrects them.
  - Nothing is changed automatically.
  - Until corrected, a blank frequency is not counted in "Reports to write", and any other unknown value is counted as needing a report.

## One resource classification (B4)

Helper: `res_class()` in `lib/workforce.php`. It uses the three existing fields
(person type, whose roll, team role). Nothing new is stored.

| Group | Who | Counted as free capacity? |
|---|---|---|
| In-house | Our people on our own roll | Yes |
| Agency payroll | Our people paid through an agency | Yes (company-controlled) |
| External | Freelancers and sub-contractors | **No.** Listed under "External — confirm with agency" on the availability page. |

**Resources** are the field team plus coordinators. Office staff are not
listed as free on the availability board, the scheduling board, the capacity
outlook or "who else could take this date". The allocate picker is unchanged:
everyone stays deputable there.

**Declined or released work:**
- a declined assignment, a released hold or a cancelled assignment no longer makes the person busy;
- the work order returns to "No engineer yet".

**Registers:**
- the schedule register, the assignment register and the jobs register name the sub-contractor firm, tagged "External";
- hold and acceptance show their labels (e.g. "Declined"), not codes.

**Agency-staff roster** now also lists agency-payroll staff.

**Data health:** an external person also marked "own roll" shows a *check* tag. Nothing is changed automatically.

## One competence warning (B5)

**The warning window is a setting.**
- The "expiring soon" window is no longer fixed at 45 days. It is a company setting, `credential_window_days`, with a default of 45.
- A manager changes it on Competence → "Warn before a certificate lapses". Who may change it is the same `competence_can_authorise()` check used for authorisation enforcement.
- Each change is written to the audit log.
- The credential pill, the eligibility pill and the training watch all read this one window.

**The same pill everywhere.**
- The existing eligibility verdict now also shows on the scheduling board's "who's free" suggestions and on the allocate form's "who else could take this date".
- A person with a lapsed required certificate is no longer offered first.

**Nothing new blocks.** The existing pack block at allocation is unchanged.
