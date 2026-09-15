# Phase 3 · M3 — MUTATION RESULTS

**27 mutations. 26 CAUGHT. 1 withdrawn, because the defect it was written to
guard turned out not to exist.**

Every mutation ran against a **copy of `phpapp/` outside the repository**, so a
mutation can never touch the working tree. A suite that dies without printing a
`RESULT:` line counts as a detection, not a pass. Baseline: 0 failures.

Suites run per mutation: `p3m` (M1 + M2 + M3), `recruit_approval`, `m4_`.

## SLA

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| M01 | due-date calculation | p3m:14 | **CAUGHT** |
| M02 | **the original defect restored — every step's clock started at chain creation** | p3m:10 | **CAUGHT** |
| M03 | the next step is never activated on approval | p3m:5 | **CAUGHT** |
| M04 | activation is not idempotent — a deadline can be extended | p3m:1 | **CAUGHT** (see below) |
| M05 | overdue detection | p3m:7, recruit_approval:2 | **CAUGHT** |
| M06 | reminder duplicate protection | p3m:2 | **CAUGHT** |
| M07 | escalation duplicate protection | p3m:1 | **CAUGHT** |
| M08 | the working-day clock, replaced by a calendar one | p3m:2 | **CAUGHT** |
| M09 | the holiday cache stops noticing the database changed | p3m:1 | **CAUGHT** |

M02 is the regression guard: it restores the exact defect the audit found, so it
cannot quietly return.

## Escalation

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| M10 | **escalation manufactures authority** | p3m:3 | **CAUGHT** |
| M11 | escalation delivery reported as success whatever happened | p3m:2 | **CAUGHT** |

## Inbox

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| M12 | branch / entity visibility | p3m:2 | **CAUGHT** |
| M13 | the waiting list stops checking whose request it is | p3m:2 | **CAUGHT** |
| M14 | entitlement on the waiting list | p3m:1 | **CAUGHT** |
| M15 | entitlement on the dashboard KPI | p3m:1 | **CAUGHT** |
| M16 | entitlement on the scheduler | p3m:2 | **CAUGHT** (see below) |

## Notifications

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| M17 | the delegate is dropped from the recipients | p3m:3 | **CAUGHT** |
| M18 | recipient validation — inactive people are written to | p3m:1 | **CAUGHT** (see below) |
| M19 | the requester lookup's "portability fix" | — | **WITHDRAWN — there was no defect** |

## Approval and delegation

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| M20 | the decision guard is bypassed | p3m:13 | **CAUGHT** |
| M21 | segregation is bypassed | p3m:14, m4_:3 | **CAUGHT** |
| M22 | the active-delegator protection | p3m:3 | **CAUGHT** |
| M23 | the delegation branch leak restored | p3m:2 | **CAUGHT** |
| M24 | delegation validity (start and expiry) | p3m:5 | **CAUGHT** |

## Write security

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| M25 | the capability check on SLA configuration | p3m:4 | **CAUGHT** |
| M26 | the capability check on policy and escalation configuration | p3m:1 | **CAUGHT** |
| M27 | entitlement in the configuration gate — a master bypass | p3m:2 | **CAUGHT** |

---

## Three survived on the first pass. All three were gaps in MY tests.

None was excused; each test was made able to tell the difference, and the
mutation was re-run.

**M04 — idempotent activation.** The test re-activated a step and compared the
due date before and after. Both were computed in the same second, so they were
identical whether or not the guard existed. It now checks against a deliberately
**different** deadline. → CAUGHT.

**M16 — entitlement on the scheduler.** The test switched the module off and
asserted `appr_tick()` returned 0 — but at that moment nothing was due, so it
returned 0 either way. The test now **constructs** an overdue step first, so
"it did nothing" can only mean the gate stopped it, and then proves the same step
*is* acted on once the module is back. → CAUGHT.

**M18 — recipient validation.** Every delegation test switched off the
*delegator* or the delegation. None switched off the **delegate**, so nothing
covered "an inactive person is still written to". An assertion was added. →
CAUGHT.

---

## M19 — and a correction to my own audit

M19 restored `appr_email_requester()`'s original query, which matches on
`TRIM((first_name || ' ' || last_name))`. The audit had recorded this as a
production defect on the grounds that `||` is string concatenation in SQLite and
logical OR in MySQL/MariaDB, so the requester would never be told the outcome of
their own request.

**The mutation survived on SQLite — expected — and then survived on MariaDB,
which it should not have.** So I probed the live MariaDB connection instead of
trusting my own reasoning:

```
piped    = 'M3 Req'      ← || concatenated, on MariaDB
cmp_full = 1
```

`lib/db.php:77` runs `SET SESSION sql_mode = 'PIPES_AS_CONCAT'` on **every** MySQL
connection this application opens — deliberately, with a comment giving exactly
the reasoning I thought I had discovered. The query was always correct on both
engines.

**The audit finding was wrong and is withdrawn. My change was reverted**; the
query is as it always was. There is no protection left to mutate, so M19 is
reported as withdrawn rather than as a survival.

What is kept is the **behavioural test** that the requester is genuinely told the
outcome of their own request. It did not exist before, it runs on both engines,
and it would have caught a real version of this defect.

The lesson is the one this project keeps teaching: **a finding read out of one
file is a hypothesis, not a defect, until something runs.**
