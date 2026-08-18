# Inspection Ops — Dashboards & Analytics — Test & Documentation Report

> **Prompt 3 · Module MOD-REPORTS.** Read from `lib/ops.php` (`ops_pending_tasks`/
> `ops_render_pending_tasks`, `ops_reports`), `lib/areas.php` (`ops_area_def`/`ops_area_home`),
> `lib/tosrm.php` (`ops_operations_home`), `lib/tapi_dash.php` (`ops_tapi`, `tapi_dashboards`,
> `tapi_formula_valid`), `lib/mis.php` (`ops_mis`, `mis_utilisation`), `views/dashboard.php`.

| | |
|---|---|
| **Module** | Dashboards & Analytics (MOD-REPORTS) · Area Insights |
| **Personas** | every role (each sees their scope) — P-INSP, P-COORD, P-BM, P-SBU, P-ED/P-MD, P-MASTER |
| **Risk weight** | **Medium-High** — the decision surface; the W6 pending-tasks list is on every dashboard; a scope leak or a wrong KPI misleads |
| **Verdict** | Complete-with-defects (confirm tile gating, W6 scope, KPI-formula safety) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. W6 pending-tasks-on-every-dashboard verified on all three surfaces. |

---

## A. Module overview

The dashboard layer is role-adaptive and permission-gated end to end. The **home**
(`views/dashboard.php`) shows KPI tiles by role (inspector quick-cards; non-inspector open
calls/jobs/unbilled/outstanding; a compliance "waiting on somebody" band; an executive
strategic board for `$isExec && data.credit`), each tile **self-gated** so it never links to
an unlicensed screen. **Area homes** (`ops_area_def`: sales/quality/reporting/money/insights/
directory/admin) render permission-filtered tiles with live count badges (guarded closures).
**Operations home** (TOSRM) is office-scoped. **Analytics** (`ops_tapi`) offers six role
dashboards (executive/operations/quality/service/vendor/client) with user-definable KPIs via a
formula engine. **MIS** is the management P&L dashboard.

The **W6 pending-tasks list** (`ops_pending_tasks`) is embedded on **all three surfaces**
(role home, operations home, reports) — to vet / to approve / to fix / to issue / to release —
so **every** dashboard (ED, SBU head, branch manager, inspector) shows the same actionable
queue, scoped to the user.

Screens: `/`, `/operations`, `/sales|quality|reporting|money|insights|directory|admin`,
`/reports`, `/analytics*`, `/mis`.

---

## B. Screen-by-screen catalogue

**`/` home** — role-adaptive KPI tiles + the W6 pending-tasks panel + compliance band + charts
+ quick actions + (exec) strategic board. **Area homes** — tiles by area, count badges.
**`/operations`** — TOSRM office-scoped metrics + disruptions + cross-office. **`/reports`** —
role dashboards (operations/financial/utilisation/people). **`/analytics`** — TAPI KPI hub +
CSV export. **`/mis`** — management dashboard.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-DASH-form-001 | Every tile's show/gate is an explicit `can()`/`*_can_view()`/`licence_enabled()` predicate; an unlicensed tile does not render. |
| TC-DASH-form-002 | Count badges are guarded closures returning null on error (never fatal). |
| TC-DASH-form-003 | A user-defined KPI formula passes `tapi_formula_valid` before use. |
| TC-DASH-form-004 | Report filters (FY/month/office/SBU/inspector/type) apply within scope. |

---

## D. Functions & logic  *(W6 + gating — highest scrutiny)*

- **W6 pending tasks** (`ops_pending_tasks`): scoped via `scope_clause('d.office_id','d.sbu')`;
  chips = to-vet (`idems_can_vet`, VETTING) / to-approve (`idems_awaiting_my_approval_clause`)
  / to-fix (REJECTED where inspector = me) / to-issue (`idems.finalize`, APPROVED not
  finalized) / to-release (`mod.idems.edit`, finalized + rn_to_issue no RN). Empty → "all
  caught up". On **all three** dashboard surfaces. **TC-DASH-fn-001** — the queue matches
  `idems_can_act_step`/`idems_can_vet` for the same user; **TC-DASH-fn-002** — the "to-release"
  chip **silently returns 0 if `release_of_id` is absent** (GAP-DASH-001).
- **Tile gating**: rail link, area tile, and route gate use the identical predicate (areas.php
  design). **TC-DASH-fn-003.**
- **KPI formula engine** (`tapi_formula_valid`): user-editable formulas — an **injection
  surface if validation is weak** (GAP-DASH-002). **TC-DASH-fn-004.**
- **Scope** (`scope_offices`/`scope_sbus`/`scope_clause`): each role sees only its branch/SBU;
  ED/SBU-head with 'ALL' see everything. **TC-DASH-fn-005.**

---

## E. Status & lifecycle

No lifecycle — dashboards are computed views. **TC-DASH-life-001:** the W6 queue reflects live
report states; **TC-DASH-life-002:** a role change re-orders the home sections.

---

## F. Roles, permissions & data scope

Dashboard perms: `dash.operations`/`dash.financial`/`dash.utilization`/`dash.people`. Data
perms: `data.credit`/`data.profitability`/`data.salary`/`finance.reconcile`. Every tile
permission-gated; scope via `scope_offices`/`scope_sbus`. `can()` checks licence before the
master bypass.

- TC-DASH-perm-001 (a scoped user) sees only their branch's KPIs/queue.
- TC-DASH-perm-002 (financial dashboard without `data.salary`) → cost columns hidden.

---

## G. Settings

`tat_threshold_days` (3), `fy_revenue_target` (exec board), terminology overrides,
`licence_enabled` toggles that reshape tiles, user-defined KPI definitions (DB-stored).
**TC-DASH-set-001:** a licence toggle removes the corresponding tiles.

---

## H. Cross-module integration

Aggregates **every** module: CRM, TOSRM/operations, finance/invoicing/profitability, quality
(NCR/CAPA/audits/complaints/confidentiality/competence), IDEMS (W6), recruitment, scheduling,
service scope. Idempotency: read-only.

---

## I. Data integrity & audit

W6 and area tiles are individually try-wrapped (a missing module never breaks the page) —
**but the home KPIs (`ops_val`) are not, so a schema drift in calls/jobs/quotations could
fatal the home** (GAP-DASH-003). MIS/exec revenue uses `expected_credit` by a COALESCE'd date,
so "revenue booked" differs from invoiced value on the same screen (ties to MOD-32).
`ops_reports` filters in PHP after a full scoped fetch + N+1 on expenses (scales poorly).
**TC-DASH-int-010:** the W6 counts equal the underlying report queues.

---

## J. Reports & outputs

The home/area/analytics dashboards, the TAPI CSV export (with audit), the MIS export
(cost columns gated). **TC-DASH-out-001:** the CSV respects the salary/credit gates.

---

## K. Negative, edge & resilience

A missing module (tile/W6 degrade gracefully); a home KPI on a drifted schema (could fatal —
GAP); a weak KPI formula (injection); a `release_of_id`-less install (to-release chip invisible);
a scoped user (branch-only); a large dataset (N+1 in reports).

---

## L. TPIA operational suitability

Delivers exactly what the user asked for in W6 — a pending-tasks list on **every** dashboard
(ED, SBU head, branch manager included) for easy access — plus role-adaptive KPIs and analytics
across the whole operation. The single-predicate gating keeps rail/home/route consistent.

## M. Management usefulness

The decision surface: KPIs, the W6 queue, the exec strategic board (FY target/YoY/utilisation),
and analytics. Confirm the figures agree with MOD-32 and the W6 queue is never silently empty.

## N. UI/UX

Role-adaptive tiles, self-gating so no dead links, the W6 panel above the role branch (every
role gets it), guided quick actions. Terminology via `T*()`.

## O. Security

Every tile permission-gated server-side; W6 scoped; **home KPIs not try-wrapped (harden)**;
KPI formula engine must validate against injection; CSV export respects data gates.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | N-A | computed |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | **Priority** | §F per-role scope |
| 9 Scope | **Priority** | §D W6 scope |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §D W6 |
| 12 Integration | **Priority** | §H every module |
| 13 Data integrity | **Priority** | §I KPI drift |
| 14 Audit | Partial | export audit |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L W6 |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O formula/KPI |
| 21 Import | N-A | — |
| 22 Notifications | Y | W6 queue |
| 23 Offline | N-A | — |
| 24 AI | Partial | analytics |
| 25 Licensing | Y | reshapes tiles |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | FY filters |
| 28 Performance | **Priority** | §I N+1 |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-DASH-001 | (verify) | The W6 **"to-release" chip silently returns 0 if `release_of_id` is absent** — the feature can be invisible with no signal. Ensure the column exists or surface the degraded state. |
| GAP-DASH-002 | (verify) | The **TAPI KPI formula engine is user-editable** — confirm `tapi_formula_valid` is strong enough to block injection/unsafe evaluation. |
| GAP-DASH-003 | (verify) | **Home KPIs (`ops_val`) are not individually try-wrapped** (unlike area tiles/W6) — a schema drift in calls/jobs/quotations could fatal the home page instead of degrading. Wrap them; and `ops_reports` filters in PHP after a full fetch with an N+1 on expenses (scale). |
| GAP-DASH-004 | — | Exec "revenue booked" uses `expected_credit` by a COALESCE'd date and differs from invoiced value on the same screen (reconcile with MOD-32). |

---

## R. Traceability

RTM slice: `/`, `/operations`, area homes, `/reports`, `/analytics`, `/mis` × dims 1–29 →
TC-DASH-* → results → DEF/GAP. **Verdict: Complete-with-defects** — tile/W6 scope enforcement,
KPI-formula safety, and home-KPI resilience are the exit conditions.
