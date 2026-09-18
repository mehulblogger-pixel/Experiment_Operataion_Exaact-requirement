# Phase 5 — KPI Definitions

*One authoritative calculation per KPI. Every screen, export, dashboard and
report reads these; nothing recomputes any of them.*

The rule that governs every figure below: **a number that cannot be computed
truthfully is NO DATA, never zero.** Zero is a measurement. Null is the absence
of one. A screen showing "0 days late" for a requirement that has no target date
is telling a manager it is on time.

---

## The demand figures

These come from `rkpi_demand()`, which asks the modules that own them. It never
invents a rule of its own.

| KPI | Plain English | Calculation | Owner |
|---|---|---|---|
| **Requested** | Everything ever asked for | `SUM(quantity)`, a missing or zero quantity meaning one person | M3 |
| **Cancelled** | Vacancies the business gave up | `SUM(cancelled_qty)`, clamped to what was requested | M3 |
| **Approved headcount** | What we are actually allowed to hire | `SUM(requested − cancelled)` **per requirement** | M4 |
| **Filled** | People who joined | `COUNT` at `REQF_FILLED_STAGES`, clamped per requirement | M3 |
| **Open positions** | Still to find | `SUM(max(0, approved − filled))` **per requirement** | M3 |
| **Promised to a source** | Seats an agency/transfer/payroll has been asked for | `SUM(allocated_qty)` on **live** allocations | Phase 4 |
| **Nobody looking yet** | Approved, but given to no one | `SUM(max(0, approved − promised − direct arrivals))` per requirement | Phase 4 |
| **Over-promised** | Promised more than was approved | `SUM(max(0, promised + direct − approved))` per requirement | Phase 4 |

### Why "per requirement" is in bold

Every one of those sums is computed **for each requirement first**, and only then
added up. Netting them across requirements would let one over-filled requirement
cancel out another that is short — the board would read "0 open" while somebody
is still three people down. The reconciliation battery proves the set-based
aggregate equals the sum of `reqf_counts()` row by row; that proof is the licence
to aggregate at all.

---

## Ageing — three different questions, never mixed

| Basis | The question it answers | How |
|---|---|---|
| **Calendar** | How long has this person actually been waiting? | Plain day difference. What the candidate and the client experienced |
| **Business** | How long did *we* have to act? | Working days through the **branch's own calendar** (`is_working_day`) — Sundays and that office's holidays do not count against a recruiter |
| **SLA** | Is an approval late? | The approval engine owns this (`appr_sla_*`). Not recomputed here |

`rkpi_age($from, $to, $basis)` is the single entry point. A caller must *name*
the basis it means; an unrecognised basis returns **NO DATA**, never a silently
substituted default. A missing or unparseable date returns NO DATA too.

Two dates on the same day really are **0** — that is a measurement, and it is
distinct from null.

---

## Time to hire

**From** the day the CV was received — falling back to the day the record was
opened, the earliest moment we can honestly claim to have had the person —
**to** the day the joining was decided. **Calendar days.**

Published in exactly one place and read by the Command Centre, the analytics
registry and the recruiter table, so the three cannot drift into three answers.
A period with no joinings reports **NO DATA**, not 0.0 days.

---

## The target date

| | |
|---|---|
| **Source** | The business's own *needed by* date on the hiring request |
| **Reached by** | `requisitions.hiring_request_id` → `hreq_get()` |
| **Never** | Invented, substituted, or taken from the creation date |

`requisitions` has no target-date column of its own. A requirement raised on the
**direct path** carries no hiring request and therefore **no target date at
all** — its state is `NO_TARGET` and its days-late is null.

Where a target exists, lateness is measured against the day the requirement was
**settled** if it has been, so a requirement filled last month stops ageing on
the screen.

---

## Accountability — two questions that look alike

| | Question | Source | Why |
|---|---|---|---|
| **Carrying now** | What is this person responsible for today? | The current `recruiter_id` column | It *is* the current state. M5's `rasg_workload()` already computes it |
| **Delivered** | Who actually made this happen? | The **M5 assignment ledger**, read at the moment the outcome was decided | The current column cannot answer a question about the past: reassign a requirement today and last month's table rewrites itself |

**How delivery is attributed, and what each answer is worth:**

| Evidence | Meaning |
|---|---|
| `ledger` | A recorded assignment at or before the moment. The strongest answer there is |
| `ledger_before_first_change` | Every recorded change came *after* that moment, so the first change names who held it before: its `from_user_id`. Without this, a record assigned before the ledger existed and reassigned today would credit today's owner with yesterday's work |
| `ledger_single_owner` | No date to ask about, but ownership never moved — one owner throughout cannot be the wrong owner at any moment |
| `current_no_history` | The ledger has nothing for this record, so nothing can have been rewritten. The current column is the only evidence there is; it is used **and labelled** |
| `ambiguous_no_date` / `no_ledger` | Genuinely unattributable. **Said, not guessed** |

Unattributable outcomes are shown as a count on the screen. They are never
shared out among the people who *can* be named.

---

## Stage performance

Read from `candidate_events`, which Phase 5 completed (see the audit, section 5a).
Three rules keep the numbers honest:

- a **revert** is not a transition and closes no step;
- a **workflow switch** is not a transition either;
- the configured-pipeline ladder and the legacy stage ladder are **never averaged
  together** — a caller asks for one track and gets that track.

Durations are measured against the stage's **stable key**, not its display name,
so renaming a stage cannot rewrite history. The step a candidate is sitting in
right now is reported **separately** from completed steps; counting it as
complete would drag every average downwards the moment somebody stalls.

Rows written before Phase 5 carry no code and are reported as `uncoded` rather
than guessed at from their display text.

Per-stage SLA is `recruit_stages.sla_days`, which the pipeline engine has carried
since Phase 2 — read, not re-invented. A stage that sets none returns **null**,
because "no SLA configured" is not "zero days allowed".

---

## The metric registry

Recruitment metrics live in **TAPI**, the analytics layer the rest of the product
already uses. They are defined next to the engine that owns their arithmetic and
registered there, so recruitment joins one analytics layer instead of growing a
second one.

| Key | Label |
|---|---|
| `hiring.requisitions.live` | Live requirements |
| `hiring.demand.authorised` | Approved headcount |
| `hiring.demand.filled` | Positions filled |
| `hiring.demand.open` | Open positions |
| `hiring.demand.allocated` | Promised to sources |
| `hiring.demand.unallocated` | Not yet sourced |
| `hiring.pipeline.active` | Candidates in process |
| `hiring.hires` | People joined |
| `hiring.tth_avg_days` | Average time to hire |
| `hiring.approval.overdue` | Approvals overdue |
| `hiring.late_vs_target` | Requirements past their needed-by date |

Each declares a documented `method`, a lineage `source`, its unit and its
aggregation. **Entitlement is TAPI's existing rule, not a new one**: lineage maps
to an access module, and an installation without *People & hiring* is **withheld**
the number rather than shown a zero it might act on.

`hiring.late_vs_target` deliberately **excludes** requirements with no target
date. They are not counted as on time.
