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

- it ignores `cancelled_qty`, so vacancies the business gave up still show as
  demand. **This is the measured defect**, and it is the whole of the seven-vs-three
  gap above;
- it also spells out the filled stage as a literal `'ACCEPTED'` instead of reading
  M3's `REQF_FILLED_STAGES`. **This is not a behavioural defect today** — an
  earlier draft of this audit said a differently-configured workspace would be
  miscounted, and that was wrong: `REQF_FILLED_STAGES` is a constant, not a
  configurable list, so the literal and the constant are the same value. It is a
  question of *ownership*, not of arithmetic: the definition should have one home,
  so that the day it moves, every reader moves with it.

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
3. **A trustworthy stage ledger.** `candidate_events` exists and carries
   `from_stage`, `to_stage`, `actor`, `created_at` — but it was measured
   **incomplete and ambiguous** (section 5a). It must be completed before any
   stage-duration KPI may read it.
4. **Nothing else.** Target dates already exist and must not be invented
   (section 9).

### 5a. The stage ledger was measured, not assumed — and it is not yet trustworthy

§7 wants stage performance, and the obvious source is `candidate_events`. A probe
driven entirely through production functions found **three reasons it cannot be
read as it stands**:

| # | Measured | Consequence for a KPI |
|---|---|---|
| **E** | `offer_issue()` moves a candidate to **OFFERED** and writes **no ledger row** (`before === after`). Confirmed end to end through `offer_create → submit → approve → issue` | "Days from shortlist to offer" would **miss every offer issued the normal way** |
| **F** | When the execution gate reverts a joining that had no seat, the ledger still **ends at ACCEPTED**. The candidate is back at OFFERED; the ledger says they joined | A hire count read from the ledger **over-reports hires**, and the revert is invisible |
| **G/H** | `to_stage` holds **three vocabularies at once**: pipeline stage *names* ("Technical Interview") from `recruitpipe_cand_goto()`, legacy stage *codes* ("ACCEPTED") from the stage route, and literal `"Workflow: <name>"` strings from the pipeline-switch action | Grouping by `to_stage` **silently splits or merges stages** |

The probe was a throwaway and has been removed. Every assertion ran against
production code; no product code was changed to obtain these results.

**Note what F does *not* say.** The execution gate's own source comment records
that time-to-hire is computed from `candidates.decided_at`, and the gate
correctly clears that stamp when it reverts. So **today's** time-to-hire is not
wrong — the ledger is simply not the thing it reads. The exposure is
forward-looking: Phase 5 is the first work that would treat this ledger as
authoritative.

**Consequence for the plan.** Completing the ledger is a **correctness fix to an
existing record**, not a new feature, and it is a precondition for §7. It is
small: write a row where the offer engine and the execution gate already write
the stage, and record the stage in one unambiguous vocabulary alongside the
human-readable name.

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

**The target date exists and must never be invented.** Measured: a requisition
carries `hiring_request_id`; the business's own *needed by* date lives on
`hiring_requests.required_by` and arrives unchanged. `requisitions` has **no
target-date column of its own**. A requirement raised on the **direct path**
carries no hiring request — and therefore **no target date at all**.

So "days late against target" is **NO DATA** for a directly-raised requirement.
Not zero, not today, not the creation date. TAPI's existing `null`-for-no-data
convention is exactly the right instrument — one more reason to reuse it rather
than build a parallel one.

## 10. Which historical snapshots are required

Per §18, history must stay explainable when masters change. Already available:
`recruiter_assignments` (ownership), the approval chain,
`hiring_requests.snapshot_json` (what the request *meant* when it was approved,
so a later master edit cannot rewrite history), and
`requisition_allocation_events` (Phase 4).

**No new snapshot table is required.** The one gap is not a missing snapshot but
an **incomplete ledger** — `candidate_events`, per section 5a. Completing it is a
fix to an existing record, not a new history mechanism.

---

## REUSE → EXTEND → CONNECT → MAP → BUILD

| Need | Decision |
|---|---|
| KPI registry, scoping, periods, units | **REUSE** TAPI unchanged |
| Demand, fulfilment, source-wise figures | **CONNECT** to `reqf_counts()` / `rful_summary()` |
| SLA | **REUSE** `appr_sla_*` |
| Recruiter workload, historical ownership | **CONNECT** to the M5 ledger |
| Stage timing and conversion | **FIX then MAP** onto `candidate_events` — it is incomplete and ambiguous today (section 5a) |
| Working-day ageing | **REUSE** `is_working_day()` / `next_working_day()` |
| Command Centre demand arithmetic | **DEPRECATE** — replace with the authoritative counters |
| Recruitment metrics in TAPI | **EXTEND** the registry |
| A canonical ageing helper | **BUILD** — small, and the only genuinely new calculation |

**No new KPI engine. No new SLA engine. No new dashboard. No new tables.**

The audit is closed. Both questions it deliberately left open — stage timing and
target dates — were measured rather than assumed, and both answers are recorded
above.
