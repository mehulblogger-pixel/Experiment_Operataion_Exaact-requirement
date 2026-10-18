# D7 — Dashboard and KPI audit

What each headline number means, where it comes from, and whether two screens
showing "the same" number can disagree.

**Evidence:** `evidence/kpi-trace.php` (the trace below),
`evidence/lifecycle-and-ownership.txt` §D, and the live screens.

---

## 1. What this audit can and cannot say

**Cannot:** reconcile figures. The test database holds **0 work orders and
0 jobs**, so no two dashboards can be compared by reading them. Every dashboard
rendered empty.

**Can, and arguably it matters more:** establish whether they would agree **by
construction** — one function per figure, called everywhere, cannot disagree
with itself; several functions for one figure can.

So this is a **definition and source audit**, not a figure reconciliation. The
figure reconciliation needs a populated database and is the first item in
`09-UAT-PLAN.md` §4.

---

## 2. A discovery that corrects an earlier finding

In `workflows/08-INSIGHTS.md` I wrote (I-F1) that a **KPI framework exists in
the schema and no screen reads it** — `kpi_defs`, `kpi_targets`,
`kpi_snapshots`, `kpi_versions`, `kpi_alerts` — and asked whether it had been
abandoned.

**It has not. It is a whole subsystem I had not found.**

```
TAPI — TPIA Analytics & Performance Intelligence (Phase 11)

This is an ANALYTICS / DECISION-VISIBILITY layer. It is NOT a system of
record and it does NOT recompute what the domain modules already compute.
It reads existing platform data through a small set of NAMED METRICS (each
one a thin, scoped query or a wrapper over an existing function), and lets a
configurable KPI MASTER compose those metrics into the numbers management
actually watches.
```
— `phpapp/lib/tapi.php`

Four files (`tapi.php`, `tapi_dash.php`, `tapi_score.php`, `tapi_gov.php`) and
**ten routes**, nine of which render as screens:

| Route | Heading |
|---|---|
| `/analytics` | **Analytics & performance** |
| `/analytics-kpis` | **KPI library** |
| `/analytics-quality` | **Data quality** |
| `/analytics-drill` | **Breakdown** |
| `/analytics-alerts`, `/analytics-review`, `/analytics-scorecard`, `/analytics-snapshot`, `/analytics-export`, `/analytics-kpi-edit` | (the rest of the hub) |

**`kpi_defs` has 14 rows in the test database**, so metrics are defined and
loaded. The other four tables are empty — targets, snapshots, versions and
alerts have not been populated here, which is what a fresh workspace looks
like.

The tile exists: **📈 Analytics & performance — "KPI cards, trends and
drill-down"**, gated on `tapi_can()` =
`is_master() || dash.operations || dash.financial || dash.utilization ||
dash.people`. The Insights area declares all four analytics routes in its
`$routes`, and the area description says *"…and the live analytics hub"*.

**Why I missed it:** the audit's route inventory was parsed from `ops.php`
alone, and TAPI dispatches from `tapi_dash.php`. That is the coverage error in
**`00a-ERRATUM-COVERAGE.md`**, and finding TAPI is what exposed it.

**I-F1 is therefore withdrawn and replaced:** the framework is built and wired.
What remains open is whether it is *used* — `kpi_targets` and `kpi_snapshots`
being empty means nobody has set a target or taken a snapshot in this
workspace, which is a question for the owner rather than a defect.

### What TAPI gets right, by its own declaration

> *"It is NOT a system of record and it does NOT recompute what the domain
> modules already compute."*

That is exactly the correct design for an analytics layer, and it is the same
principle as the MGH Books boundary ("the ERP never recomputes any of it").
Each named metric is *"a thin, scoped query or a wrapper over an existing
function"* — so the analytics hub reads the domain's own numbers rather than
inventing parallel ones. **If that discipline holds, a figure on `/analytics`
cannot disagree with the module it came from.** Verifying that it holds, metric
by metric, is Phase G work.

`tapi.php` also carries a **formula evaluator** (`tapi_formula_tokens`,
`tapi_formula_refs`, `tapi_formula_valid`, `tapi_formula_eval`, with its own
expression parser) — so a KPI is composed from named metrics by a formula with
validation, not hard-coded. With `kpi_versions` for the definition's history,
that is a properly-built metric layer: **you can say "our margin target was 18%,
measured this way, and here is the definition that was in force."**

---

## 3. One number, how many implementations

For each business figure: which functions claim to produce it, and how many
places call each (`evidence/kpi-trace.php` §B).

**155 functions** in the running application have names saying they compute a
headline number.

### Profit — **one source, used widely. Good.**

| Function | Call sites |
|---|---|
| **`job_profit`** | **19 across 7 files** |
| `boss_profit` | 2 |
| `ops_call_profit` | 1 |
| `profit_reconciliation` | 2 |
| `callprofit_rows`, `ops_profitability` | **no callers found** |

`job_profit` is the single source and it is used everywhere. `boss_profit` and
`ops_call_profit` are the contract-level and work-order-level presentations.
**A figure with one implementation cannot disagree with itself**, which is the
best possible result here.

### Outstanding — **one source, and it is the Books-boundary one. Good.**

| Function | Call sites |
|---|---|
| **`books_outstanding`** | **6 across 4 files** |
| `ops_receivables` | 1 |
| `settlement_outstanding_total` | **no callers** |

### Revenue — **more scattered, and some of it is dead**

| Function | Call sites |
|---|---|
| `job_revenue_for` | 3 |
| `revenue_reader_mode` | 5 |
| `revenue_reader_modes` | 3 |
| `can_see_revenue` | 4 |
| `costing_office_revenue` · `costing_sbu_revenue` · `reports_revenue_office` · `mkt_ledger_revenue_by_stream` · `revenue_reader_set_mode` | **no callers found** |

**Five revenue functions with no callers.** Either dead code, or called
dynamically in a way a static scan cannot see. Worth a look: dead revenue
functions are a maintenance hazard, because the next person to need a revenue
figure may reach for the wrong one.

### Man-days / utilisation — **three implementations**

`job_mandays` (5 sites) · `mis_utilisation` (1) · `mis_person_utilisation` (2).

Three functions for what a director reads as one number. They may legitimately
differ (a job's man-days, a company utilisation rate, a person's utilisation
rate are three different questions) — **but the denominator matters**:
utilisation requires expected working days, which come from
**`/work-norms`** (weekly days and hours per designation per office,
`workflows/09-DIRECTORY.md` §2.11). **Whether all three use the same norms is
untested**, and it is the kind of difference that makes two screens disagree by
a few percent and destroys trust in both.

### Margin — **no function at all**

`/mis` has a **Margin** column. **Not one of the 155 functions mentions
margin.** So the headline margin figure is computed inline — in a view, or by a
differently-named function — which means it cannot be traced, reused or tested
as a unit.

**Finding D7-1 (P2):** the one figure a director looks at first has no named
implementation.

---

## 4. Does anything recompute what MGH Books owns?

The Books contract is explicit: *"The ERP never recomputes any of it, so the two
can never disagree."* Books owns **paid, outstanding and IRN**.

The trace found **34 places** summing an amount in a receipt/invoice context.
Read in context, they fall into three groups:

| Group | Where | Verdict |
|---|---|---|
| **EXAACT's own invoice and receipt records** | `books.php` (10 sites, incl. `SUM(r.amount + r.tds_amount)`) | **Correct.** Books is **off by default**; when it is not connected, EXAACT *is* the record. These are its own books, not a recomputation of Books' answer |
| **The "legacy" figure stored on the job** | `SUM(j.invoice_amount)` in `contracts.php`, `crm.php`, `ops.php`, `tapi.php` | **This is the known dual source**, and it is exactly what `/revenue-reconciliation` exists to reconcile against the ledger — with a `revenue_reader_mode` to choose which to trust |
| **Reconciliation screens themselves** | `revrecon.php`, `costrecon.php` | **Correct by design** — their job is to compare the two |

**No violation of the Books boundary was found.** `books_outstanding` is the
single source for outstanding and it is the bridge function.

**But the second group is the real risk**, and the product already knows it:

> `/revenue-reconciliation` — *"Where a job's legacy invoice figure disagrees
> with the books ledger."*

**There are two revenue sources** — `jobs.invoice_amount` (stamped when the job
closed) and the invoice lines in the ledger — and **four files sum the legacy
one, including `tapi.php`.** So the analytics layer reads the legacy figure.

**Finding D7-2 (P1):** if `/analytics` and `/sbu-pl` read different sources for
revenue, they will disagree, and the reconciliation screen exists because they
*do* diverge in practice. **Which source each dashboard reads must be
established and stated on the screen.** The `revenue_reader_mode` /
`cost_reader_mode` switches exist for precisely this — but a *mode* only helps
if every screen honours it.

---

## 5. The system counts its own disagreements

This is the most reassuring thing in the audit, and worth stating plainly: the
product has **functions whose job is to count places where it disagrees with
itself.**

| Function | Called by | What it counts |
|---|---|---|
| `billable_mismatch_count` | — | billable events whose figures disagree |
| `calls_status_disagreement_count` | — | work orders whose status disagrees with something |
| `revrecon_count` | areas.php | jobs whose legacy invoice figure ≠ the ledger |
| `costrecon_count` | areas.php | jobs whose legacy sub-contractor cost ≠ the committed ledger |
| **`cost_dualwrite_*`** family — `_count`, `_flag`, `_render`, `_scan`, `_summary` | areas.php, views | costs written on **both** doors |

`revrecon_count` and `costrecon_count` drive **amber count badges on the Money
tiles**, so the disagreement is visible on the menu rather than hidden in a
report.

**The `cost_dualwrite_*` family is a whole scanner** for the double-write
problem — an inspector's expense captured at job closure *and* on their voucher,
which would overstate cost and understate profit. A product that scans for its
own double-counting is a product built by someone who has been bitten by it.

**Finding D7-3 (P2):** `billable_mismatch_count` and
`calls_status_disagreement_count` have **no callers found**. Two
self-disagreement counters that nothing displays. If they work, they belong on
a tile badge like the other two. *That is a cheap, high-value fix.*

---

## 6. Every headline figure, and where it comes from

| Figure | Screens | Source | Grade |
|---|---|---|---|
| **Profit** | `/mis`, `/sbu-pl`, `/call-profit`, `/profitability`, `/reports` | `job_profit` (one function, 19 sites) | **traced** |
| **Outstanding** | `/invoices`, `/receivables` | `books_outstanding` — **MGH Books owns it** when connected | **traced** |
| **Settled / paid** | `/invoices` | Books | **traced** |
| **Revenue** | `/mis`, `/sbu-pl`, `/reports`, `/analytics` | **two sources** — `jobs.invoice_amount` (legacy) and the invoice ledger, with `revenue_reader_mode` to choose | **D7-2 — which one each screen reads is unestablished** |
| **Direct cost** | `/mis`, `/sbu-pl` | job costs + `cost_reader_mode`, with a dual-write scanner | **traced, mode-dependent** |
| **Office cost** | `/sbu-pl` | `/office-finance`, apportioned by the **"Spreads across Business Units by"** basis, committed by `/cost-run` | **traced** |
| **Margin** | `/mis` | **no named function** | **D7-1 — untraceable** |
| **Man-days** | `/mis`, `/reports` | `job_mandays`, with `/work-norms` as the denominator | **three implementations; same-norms unverified** |
| **Utilisation** | `/reports`, `/capacity-outlook` | `mis_utilisation`, `mis_person_utilisation` | **as above** |
| **Credit / Loaded cost / Net** | `/reports` | the inter-office model (ADR-005): contracting office books client revenue, executing office earns the credit | **READ** |
| **Inspections · Closed** | `/mis` | work-order and job counts | **traced** |
| **Win rate / value by stage** | `/crm-dashboard`, `/crm-reports` | funnel over leads/opportunities/quotes | **traced** |
| **Free inspector-days · Unallocated demand · Gap · Signal** | `/capacity-outlook` | availability vs allocated jobs | **traced** |
| **KPI cards, trends, scorecards** | `/analytics*` | **TAPI named metrics + formula evaluator over `kpi_defs`** | **traced to the framework; individual metrics not verified** |

---

## 7. What is missing from the dashboards

Three areas generate figures a director needs and **do not reach any dashboard
measured**:

| Area | Figures that exist but do not surface |
|---|---|
| **Recruitment** | time-to-hire, `drop_point` analysis (where in 15 stages candidates are lost), source effectiveness. `recruit_kpi.php` exists with its own SUMs, and **no TAPI metric covers any of it** |
| **Quality** | ~~absent~~ — **covered**: `ncr.total`, `ncr.open`, `ncr.closure_avg_days`, `capa.rated`, `capa.effective`, `sla.evaluable/within/breached`, `release.released/conditional/not_released`, `vendor.reassess_due`. What is *not* in the registry is `audits_readiness` and the complaint clocks |
| **Marketplace** | partly — `portal.active_users`, `portal.events`. Supply vs demand, fill funnel, time-to-award and rate benchmarks live on `/connect-analytics` in its own area |

**But note:** `recruit_kpi.php` and `audits_readiness` / `capa_readiness`
*exist as functions*, and TAPI's whole purpose is composing named metrics into
dashboards. **So the plumbing to surface them is already there** — this may be
a configuration gap (nobody has defined those KPIs in `kpi_defs`) rather than a
build gap. `kpi_defs` having only 14 rows supports that reading.

**Finding D7-4 (P2), restated from I-F2:** Quality, Recruitment and Marketplace
figures do not reach the management dashboard — and the likely fix is **defining
them as TAPI metrics**, not writing new code.

---

## 8. Findings

| Id | Severity | Finding |
|---|---|---|
| **D7-2** | **P1** | **Revenue has two sources** — the legacy figure stamped on the job and the invoice ledger — and **four files sum the legacy one, including `tapi.php`**. The product knows they diverge (that is what `/revenue-reconciliation` is for), so two dashboards reading different sources *will* disagree. Which source each screen reads must be established, and said on the screen |
| **D7-1** | **P2** | **Margin has no named function.** The first figure a director reads is computed inline and cannot be traced, reused or unit-tested |
| **D7-3** | **P2** | **Two self-disagreement counters have no callers** — `billable_mismatch_count` and `calls_status_disagreement_count`. `revrecon_count` and `costrecon_count` drive amber tile badges; these two should too |
| **D7-4** | **P2, narrowed** | **Recruitment** has no metric in TAPI's registry of 28 — no hire, candidate or requisition figure — although `recruit_kpi.php` and the data exist. **Quality is covered** (`ncr.total`, `ncr.open`, `ncr.closure_avg_days`, `capa.rated`, `capa.effective`, `sla.*`, `release.*`, `vendor.reassess_due`) and Marketplace partly (`portal.active_users`, `portal.events`). **The fix is defining the missing metrics, not writing code** |
| **D7-5** | **P2** | **Three utilisation implementations** (`job_mandays`, `mis_utilisation`, `mis_person_utilisation`) and it is unverified that all three use the same `/work-norms` denominator |
| **D7-6** | **P3** | **Five revenue functions with no callers** — `costing_office_revenue`, `costing_sbu_revenue`, `reports_revenue_office`, `mkt_ledger_revenue_by_stream`, `revenue_reader_set_mode`. Dead code, or dynamic calls a static scan cannot see; either way a hazard for the next person needing a revenue figure |
| ~~I-F1~~ | — | **WITHDRAWN.** "A KPI framework exists and no screen reads it" was wrong: TAPI is a built, wired analytics subsystem with ten routes, a KPI master, a formula evaluator and definition versioning. `kpi_defs` has 14 rows. Finding it is what exposed the audit's own coverage error (`00a-ERRATUM-COVERAGE.md`) |
| **D7-7** | — | *Credit:* **no violation of the Books boundary found** — `books_outstanding` is the single source and it is the bridge · **`job_profit` is one function used in 19 places** · TAPI declares itself *"NOT a system of record"* and *"does NOT recompute what the domain modules already compute"* · a **formula evaluator with validation** over named metrics, with **`kpi_versions`** so a definition has history · **`revrecon_count` and `costrecon_count` on amber tile badges**, so disagreement is visible from the menu · and the **`cost_dualwrite_*` scanner**, a whole family of functions hunting the product's own double-counting |

---

## 9. What has to happen before any figure is trusted

In order:

1. **Populate a database** with a realistic month: work orders, jobs, reports,
   billable events, invoices, receipts, costs. Nothing below is possible
   without it.
2. **Pick one month and one business unit, and reconcile by hand.** Compute
   revenue, cost, profit and margin independently, then compare against
   `/mis`, `/sbu-pl`, `/call-profit`, `/reports` and `/analytics`. **Any two
   that differ is a finding.**
3. **Establish which revenue source each screen reads** (D7-2), and set
   `revenue_reader_mode` / `cost_reader_mode` deliberately rather than by
   default.
4. **Run the three reconciliation screens** — revenue, cost, reimbursable
   duplication — and confirm they are empty before trusting a profit figure.
5. **Give margin a named function** (D7-1).
6. **Check the three utilisation functions against the same `/work-norms`**
   (D7-5).
7. **Define the missing Quality, Recruitment and Marketplace metrics in
   `kpi_defs`** and put them on the management dashboard (D7-4).
8. **Audit the TAPI metric definitions one by one** against the domain
   functions they claim to wrap — the layer's own discipline is the reason to
   trust it, so it should be verified rather than assumed.

Steps 1 and 2 are the whole job. Until they are done, **this audit describes the
dashboards; it does not validate them.**
