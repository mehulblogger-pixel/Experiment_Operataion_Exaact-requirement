# Phase 3 · M3 — SLA, Escalation, Inbox & Notifications — ARCHITECTURE

## The shape of it

M3 adds no engine. It puts a clock beside the approval engine that already
exists, and a set of screens that can read that clock.

```
Approval rule            recruit_approval_rules          (Phase 6, + M2 columns)
   └ level               recruit_approval_levels         sla_days · reminder_days · escalate_*
        │  chain created
        ▼
Approval request         recruit_approval_requests       (Phase 6)
   └ step                recruit_approval_steps          + M3: sla_days · reminder_days · activated_at
        │
        ├── becomes active ─────► appr_activate_step()   ← THE CLOCK STARTS HERE, AND ONLY HERE
        │                           · due   = appr_due_at(sla_days, branch)
        │                           · remind= appr_due_at(reminder_days, branch)
        │                           · audit  "SLA started"
        │
        ├── cron.php ($m8('hiring')) ► appr_tick()
        │        · reminder due   → notify + audit + move the threshold
        │        · SLA passed     → escalate ONCE + audit the delivery outcome
        │        · decides NOTHING
        │
        └── read ───► appr_sla_state()  derived, stored nowhere
                          └ /my-approvals · /hiring-request · Recruitment Command Centre
```

## What was reused, extended, and built

| | |
|---|---|
| **Reused unchanged** | `cron.php` + its per-module `$m8()` gate · `ops_mail()` + `email_log` + `/notifications` · `act_log()` + the activity spine · `appr_inbox()` / `appr_visible()` / `appr_can_act()` / `appr_guard()` · `is_working_day()` / `office_holidays()` · `hiring_admin_can()` · `licence_blocks()` |
| **Extended** | `appr_start()` · `appr_act()` · `appr_tick()` · `appr_email_approver()` / `appr_email_escalate()` / `appr_email_requester()` · `appr_delegators_for()` (refactored into one shared validity rule) · `/my-approvals` · the hiring-request screen · the Recruitment Command Centre |
| **Built** | `appr_activate_step()` · `appr_due_at()` · `appr_sla_state()` / `appr_sla_label()` / `appr_sla_days_late()` / `appr_sla_sentence()` · `appr_step_recipients()` · `appr_delegates_of()` · `appr_audit_sla()` · `appr_delivery_note()` · `appr_sla_summary()` · `appr_waiting_on_others()` · `appr_config_can()` |
| **Built as tables** | **Nothing.** |

## The three columns, and why they exist

`recruit_approval_steps` gains `sla_days`, `reminder_days` and `activated_at`.

They exist because §7 and §31 pull in opposite directions and both must hold:

- §7 — the clock must start when the step becomes **active**.
- §31 — the policy must be frozen at chain **creation**, so editing the matrix
  cannot rewrite an approval already running.

Satisfying both means the step must carry its own policy, so that activation can
compute a due date without re-reading a matrix that may since have changed.
`activated_at` is also what makes the activation **idempotent**: without it,
touching the chain again would recompute the deadline, and an SLA that can be
reset is not an SLA.

## Two questions kept apart

M2 separated them and M3 keeps them apart, because merging them is how authority
leaks:

| | |
|---|---|
| `appr_can_act($step)` | **does this step name you** — the configured role, the named user, or a valid M2 delegation |
| `appr_guard($req)` | **entitlement, branch scope, segregation** |

A same-role approver at another branch passes the first and fails the second.
The inbox asks both (`appr_visible()`), and so does every decision (`appr_act()`).

## One delegation rule, two readings

M3 needed the delegation table read the other way round — not *"who may this
person act for"* but *"who is currently acting for this approver"*, so a reminder
reaches the person the system is actually waiting for.

That is a second **reading**, never a second **rule**. Both directions now come
through `appr_valid_delegations()`, which is the only place dates, entity, branch
scope and delegator active status are decided. M2's correction was about two
copies of a delegation rule drifting apart; adding a reader is not a licence to
repeat it.

```
appr_valid_delegations($col, …)          ← dates · entity · branch · delegator active
   ├── appr_delegators_for()   delegate  → delegators   (authority — M2, unchanged)
   └── appr_delegates_of()     delegator → delegates    (notification — M3)
```

## The clock

`appr_due_at($days, $officeId)` counts **working days** using the calendar that
already existed: Sundays and that branch's public holidays. Where no branch can
be established — offer, salary and requisition carry no office, exactly as M2
established — the company-wide holidays apply and nothing else changes.

`office_holidays()`'s cache is now keyed on `db_epoch()`, like every other cache
in this codebase. One database per tenant means a process that serves two
workspaces would otherwise answer the second with the first one's holidays.

## What the scheduler may and may not do

```
MAY      notice an approval is overdue · remind · escalate · notify · audit
MAY NOT  approve · reject · reassign · advance a chain · manufacture authority
```

`appr_tick()` writes reminder bookkeeping and sends e-mail. It writes to no step
status, no request status and no approver field, and it never calls the authority
engine, because it never acts.

## Security chain, unchanged

```
ENTITLEMENT → CAPABILITY → ACTUAL AUTHORITY → TENANT → BRANCH/ENTITY SCOPE
            → SEGREGATION → VALID STATE → ACTION → AUDIT
```

M3 adds entitlement questions at three more writes (`appr_tick()`,
`appr_config_can()`, `appr_waiting_on_others()`, `appr_sla_summary()`) and takes
none away.

```
SLA ≠ AUTHORITY    ESCALATION ≠ AUTHORITY
NOTIFICATION ≠ AUTHORITY    INBOX VISIBILITY ≠ AUTHORITY
```
