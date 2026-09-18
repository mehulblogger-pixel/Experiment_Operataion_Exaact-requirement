# Phase 5 — Pre-Implementation Audit

*What already exists, what is reusable, what disagrees, and what is genuinely
missing. Written before any code is changed, as §2 requires.*

---

## The headline finding, measured rather than asserted

**The Recruitment Command Centre carries its own arithmetic, and it disagrees
with the authoritative counters.**

A probe was built through the production paths — a ten-person requirement, three
people joined, four vacancies given up as no longer needed, two seats promised to
an agency — and the three engines were asked the same question:

```
M3  reqf_counts()  : requested=10  cancelled=4  filled=3  remaining=3
M3  AUTHORISED     : 6                       (requested less cancelled)
P4  rful_summary() : authorised=6  allocated=2  fulfilled=3  unallocated=1
CC  rcc_data()     : ordered=10   filled=3   open_positions=7
```

| Question | Records say | Command Centre says |
|---|---|---|
| Authorised headcount | **6** | 10 |
| Open positions | **3** | **7** |
| Source allocation | 2 promised, 1 unallocated | *not reported at all* |

A coordinator reading that screen sees **seven open positions where the records
say three**. `lib/recruit_cc.php` contains **zero** references to `reqf_counts()`,
`rful_summary()` or `REQF_FILLED_STAGES`; it reads `requisitions.quantity`
directly and counts `stage='ACCEPTED'` inline. Two specific consequences:

- it ignores `cancelled_qty`, so vacancies the business gave up still show as demand;
- it hard-codes the filled stage instead of M3's `REQF_FILLED_STAGES`, so a
  workspace that configures its pipeline differently is miscounted.

This is the same class of defect M5 already fixed once in this very file, when
`PARTIALLY_FILLED` was missing from the live-demand list and a ten-seat
requirement with three hires reported **0 open positions where the records said
7**. The comment recording that fix is still in the file, directly above the
arithmetic that is now wrong for a different reason. The lesson did not
generalise: the fix was applied to the *status list*, not to the *ownership of
the calculation*.

**Phase 5 §3 exists precisely for this.** It is not a new requirement; it is the
correction of a live defect.

---

## 1. What already exists

| Area | Where | State |
|---|---|---|
| **KPI/metric engine** | `lib/tapi.php` (+`tapi_dash`, `tapi_score`, `tapi_gov`) | **Mature and exactly the right shape.** A registry of named metrics, each with `label`, `unit`, `agg`, `source`, a documented `method` string and a `resolve` closure. Carries office/SBU scoping (`tapi_scope` → `scope_clause`), period filtering (`tapi_period_sql`), and returns **`null` for NO DATA rather than zero** |
| **Recruitment Command Centre** | `lib/recruit_cc.php`, `views/ops/recruitment_cc.php` | Funnel, status donut, stage counts, conversion, recruiter table, tracker. **Its demand/fulfilment arithmetic is private and wrong (above)** |
| **Approval SLA** | `appr_sla_summary/state/label/days_late/sentence`, `appr_due_at` | Complete. Already consumed by the Command Centre |
| **Fulfilment** | `reqf_counts()` (M3), `rful_summary()` (Phase 4) | Authoritative. Phase 4 verified `rful_summary` agrees with M3 |
| **Recruiter ownership history** | `recruiter_assignments` ledger (M5) | Append-only, with actor and reason |
| **Execution boundary** | `hreq_is_executable()`, `rexec_block_reason()` | Authoritative |
| **Exports** | `lib/recruit_export.php` | Scoped (M5 fixed this); consumes `rcc_data` |
| **Ageing** | `days_between()` (`ops.php:1458`) | A plain calendar-day difference. Null-safe |
| **Working days** | `is_working_day()`, `next_working_day()` (office-aware, honours holidays), `working_days_in_month()`, `working_days_for()` | **Exists** — §7 says reuse it rather than write a new one |

## 2. What is reusable as-is

`tapi_metrics()` registry and its scope/period plumbing · `reqf_counts()` ·
`rful_summary()` · the `appr_sla_*` family · `recruiter_assignments` ·
`hreq_is_executable()` · `days_between()` · `is_working_day()` /
`next_working_day()` · `scope_clause()` / `scope_office_clause()` ·
`licence_blocks()` · the dashboard and Command Centre **shells**.

## 3. What requires extension

- **TAPI registers nothing for recruitment.** Its metrics cover jobs, calls,
  reports and revenue. Recruitment metrics must be **added to that registry**,
  with `resolve` closures that call `reqf_counts()`, `rful_summary()`,
  `appr_sla_*` and the M5 ledger — never recomputing.
- **The Command Centre must consume those metrics** instead of its own
  arithmetic.
- **`rcc_recruiter_perf()` reads `requisitions.recruiter_id`** — the *current*
  field. §5 forbids exactly this for historical workload. It must read the M5
  ledger for anything historical, and may keep the current field only for
  "currently assigned".

## 4. What is duplicated

| Duplication | Verdict |
|---|---|
| Demand/fulfilment counted in `rcc_data()` **and** `reqf_counts()`/`rful_summary()` | **Remove the duplicate.** The Command Centre becomes a consumer |
| Filled stage hard-coded in `rcc_data()` **and** defined in `REQF_FILLED_STAGES` | **Remove the duplicate** |
| Export consumes `rcc_data` | Correct — it inherits the fix automatically |

## 5. What is genuinely missing

1. **Recruitment metrics in the TAPI registry** — the substance of Phase 5.
2. **A canonical ageing helper.** `days_between()` gives calendar days; working
   days exist but are not combined into one ageing call. §7 requires calendar,
   business and SLA ageing to be distinguishable and never mixed.
3. **Stage-transition timing.** `candidate_events` records `from_stage`,
   `to_stage`, `created_at` — so stage ageing and conversion are derivable
   **without new tables**. This needs confirming against real data before any
   schema is proposed.
4. **Recruitment target dates.** To be audited against existing fields
   (`required_by`, `target_date` on allocations, `appr_due_at`) before inventing
   any. §8: *a KPI is invalid if its target date is invented or ambiguous.*

## 6. Which dashboard to extend

**The Recruitment Command Centre** (`recruit_cc.php` / `recruitment_cc.php`) —
it already has the shell, the filters, the scope clauses and the audience. Not a
new screen. TAPI's own dashboard (`tapi_dash.php`) remains the configurable
executive layer.

## 7. Which metrics already exist

Funnel by stage · status buckets · stage counts · conversion ratios ·
time-to-hire (`tth`) · offers issued · approval SLA (pending / due today /
overdue / escalated / due soon) · recruiter table (posted / working / recruited /
earned / lost).

## 8. Which calculations disagree

Measured above: **`ordered` 10 vs 6**, **`open_positions` 7 vs 3**, and source
allocation absent. This is the defect Phase 5 must fix first.

## 9. Which dates are authoritative

| Event | Authoritative source |
|---|---|
| Request submitted / decided | `hiring_requests` + the approval chain |
| Approval due | `appr_due_at()` |
| Requisition raised | `requisitions.created_at` |
| Recruiter assigned | **`recruiter_assignments` ledger** — not the requisition row |
| Candidate entered | `candidates.created_at`, or `cv_received_date` where set |
| Stage moved | **`candidate_events.created_at`** |
| Offer issued / accepted | `job_offers` |
| Joined | `candidates.decided_at` at a filled stage |
| Allocation promised | `requisition_allocations.created_at`, `target_date` |

## 10. Which historical snapshots are required

Per §18, history must stay explainable when masters change. Already available:
`recruiter_assignments` (ownership), `candidate_events` (stage), the approval
chain, and `requisition_allocation_events` (Phase 4). **No new snapshot table is
proposed on the evidence so far** — this is to be confirmed, not assumed, before
any schema change.

---

## REUSE → EXTEND → CONNECT → MAP → BUILD

| Need | Decision |
|---|---|
| KPI registry, scoping, periods, units | **REUSE** TAPI unchanged |
| Demand, fulfilment, source-wise figures | **CONNECT** to `reqf_counts()` / `rful_summary()` |
| SLA | **REUSE** `appr_sla_*` |
| Recruiter workload, historical ownership | **CONNECT** to the M5 ledger |
| Stage timing and conversion | **MAP** onto `candidate_events` |
| Working-day ageing | **REUSE** `is_working_day()` / `next_working_day()` |
| Command Centre demand arithmetic | **DEPRECATE** — replace with the authoritative counters |
| Recruitment metrics in TAPI | **EXTEND** the registry |
| A canonical ageing helper | **BUILD** — the only new code contemplated so far, and small |

**No new KPI engine. No new SLA engine. No new dashboard.** On the evidence so
far, **no new tables** — to be confirmed against the stage-timing and target-date
questions above before anything is built.
