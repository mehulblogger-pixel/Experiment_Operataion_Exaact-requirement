# 10 — DATA MIGRATION, UX SIMPLIFICATION AND KPI/SLA STRATEGY
Covers deliverables **26, 27, 28**.

---

## 1. Data migration strategy (deliverable 26)

### 1.1 The binding constraint
The schema engine is **forward-only**. There is no `schema_version` table, no down-migration and no `DROP TABLE` in the migrate path (`lib/db.php:416-582`). Changes apply via `CREATE TABLE IF NOT EXISTS` + `ensure_column()` (693 call sites), triggered by a code fingerprint (`index.php:370-663`).

### 1.2 Rules adopted
1. **Additive only.** New columns/tables only. Never drop, never rename a column in place.
2. **Deprecate by disuse.** A superseded column stops being written and read; it is not removed.
3. **Backfill idempotently.** Every backfill must be safe to run repeatedly (the boot chain may re-run it).
4. **Dual-write during transition.** When a free-text field gains an FK (e.g. `candidates.agency` → `agency_id`), write both and read the FK with a text fallback until coverage is proven.
5. **No data deletion on module deactivation.** Already true and verified — preserve it (brief §8).
6. **Every migration must be tested on MySQL**, not only SQLite (see `08-…` W1).

### 1.3 Specific migrations implied by this audit (not yet scheduled)
| Change | Type | Risk |
|---|---|---|
| `saas_entitled_modules` default-deny semantics | behavioural, no schema | **High** — must not lock out existing tenants; needs a guarded backfill first |
| New module keys `marketplace`, `quality` | additive to `PRODUCT_MODULES` + ceiling backfill | Medium |
| `candidates.agency_id` FK | additive column + name-match backfill | Low |
| Requisition target/SLA dates | additive columns | Low |
| Hiring-request requestor fields | additive columns | Low |
| Per-source fulfilment allocation | **new table** | Medium |
| `inspector_day_status` duplicate DDL reconciliation | corrective | Medium |

---

## 2. UX simplification strategy (deliverable 27)

### 2.1 Measured surface
| Surface | Count |
|---|---|
| Application routes | 435 |
| Screens under `views/ops/` | 266 |
| Left-nav areas | 8 |
| Settings tiles | 12 |
| Dashboard surfaces | 11 |

### 2.2 Root cause of the perceived complexity
It is **not** primarily screen count. It is that **entitlement fails open** (F1) and **two capabilities are not modules** (F2), so a tenant sees capabilities it never bought. A recruitment agency is shown Operations, Quality, Money and Marketplace because the platform cannot currently express "this tenant bought recruitment only."

**Therefore: fixing entitlement IS the primary UX simplification.** It removes whole areas rather than rearranging them. No screen redesign is required to achieve the largest share of the benefit.

### 2.3 Secondary simplifications (evidence-backed, ordered)
1. **Semantic collision in masters** — one `department`/`designation` master serves both *our internal org* and *the vacancy we are filling* (`05-…` §2.3). Separating these removes the single most confusing thing in the recruitment screens.
2. **Dashboard section order is a hard-coded role switch** (`views/dashboard.php:477-488`) while a per-tenant config layer already exists (`lib/workspace.php`). Move order into the existing layer — **CONFIGURE, not rebuild**.
3. **Setup scatter** — org-chart import, departments, positions, org chart and masters are reachable from four different places. Consolidate into one Setup hub (navigation only, no logic change).
4. **Two candidate stage systems** (`06-…` D15) present two different progress vocabularies to the same user.

### 2.4 Constraints
- `views/dashboard.php` is a **protected asset** (brief §21). REUSE/EXTEND/CONFIGURE only.
- The UI/UX blueprint in `docs/05-ui-ux-blueprint.md` and the "Zero Training UI" gate govern all user-facing changes (per repository `CLAUDE.md`).
- No permission may be granted that is not in `docs/02-permission-matrix.md`.

---

## 3. KPI / SLA strategy (deliverable 28)

### 3.1 Mandatory reuse — TAPI
`lib/tapi.php` (1,004 ln) + `lib/tapi_score.php` already provide everything the brief's §20 asks for:
- `kpi_defs` — configurable KPI master (formula, unit, period, target, threshold, direction, scope, lineage);
- a **metric-adapter registry** (`tapi_metrics()`, extensible via `tapi_domain_metrics()`);
- a **safe non-`eval` formula parser** with a function whitelist;
- status grading with an explicit **NO_DATA vs real-zero** rule;
- `kpi_targets` per office/SBU/period, weighted **scorecards**, and cron-run **alerts**;
- small-sample / causality / fairness guardrails;
- a presentation kit (cards, filter bar, SVG line/sparkline/pareto/heatmap).

**Ruling: recruitment KPIs MUST be implemented as TAPI metrics + `kpi_defs` rows. Building a second KPI engine is forbidden (brief §20).**

Caveat to fix: `tapi_can()` requires only any `dash.*` permission or master — **no `mod.*` permission, no licence gate** (`tapi_dash.php:165-168`). Each registered metric must therefore carry its own module gate, or TAPI will expose metrics of unlicensed modules.

### 3.2 SLA — extend one engine, do not add a fifth
Four SLA/escalation mechanisms exist (`tosrm` operations SLA, `recruit_approval` approval SLA, `idems` escalations, `attendreview`), with **no shared ageing helper**.

`recruit_approval`'s *mechanism* is genuinely generic — rule matching by dept/SBU/grade/position/amount band with narrowest-match (`appr_match()` `:120-151`), multi-level chains with `sla_days`/`reminder_days`/`escalate_role`, and a cron tick for reminders and escalation (`:253-300`, wired at `cron.php:383-385`). Only its **storage names** are recruitment-specific.

**Plan:** extract a small shared ageing/SLA helper; have recruitment consume it; widen `APPR_ENTITIES` and **actually invoke the approval chain for `REQUISITION`** (today it is dead code, `03-…` §2.1).

### 3.3 Prerequisite that blocks all recruitment KPI work
**No target dates exist**, and `recruit_stages.sla_days` is stored but **never read**. Until target/actual dates are captured per requisition and per stage, time-to-source, time-to-fill, stage ageing and SLA adherence are **uncomputable**. Date capture must therefore precede any KPI dashboard work.
