# 01 — CURRENT ARCHITECTURE MAP
Covers deliverable **1**, plus the shared-core (§9) and module-layer (§10) classification required by the brief.

---

## 1. Shape of the system

Plain PHP 8.4, **no framework**. Server-rendered forms; there is **no tenant-facing REST/AJAX API** (`api.php` is solely the licence server, `api.php:1-44`).

| Measure | Value |
|---|---|
| PHP lines | 154,873 |
| Library files (`lib/*.php`) | 221 |
| View files | 394 (266 under `views/ops/`) |
| Database tables | **306** |
| Application routes | 435 (via `ops_dispatch`) + ~25 public/inline |
| Test files / assertions | 444 / 6948 |

**Request path:** `index.php` (front controller) → `config.php` resolves the tenant and **rewrites the DB connection** → library load (219 files) → schema fingerprint check → public routes → `require_login()` → 9 inline routes → **`ops_dispatch()` at `index.php:1670`** → `ops_module_gate()` → handler → `view()`.

**Critical structural fact:** the module gate sits inside `ops_dispatch`, which is the **last** line of the front controller. Everything dispatched earlier is ungated. See `02-saas-entitlement-audit.md` §2.3.

---

## 2. Shared platform core (audited, not assumed)

The brief warns against assuming what is platform-core. Audited classification:

| Capability | Genuinely core? | Evidence |
|---|---|---|
| Tenant resolution / routing | **Yes** | `config.php:87-151`, `lib/tenants.php`, `lib/saas_tenants.php` |
| Settings | **Yes** | `settings` table; every module reads it |
| Users / authentication | **Yes** | `users` (`lib/db.php:148`), `index.php:783` login |
| Roles / permissions | **Yes** | `lib/access.php` — 16 roles, 37+31 permission keys, single choke point `can()` |
| Lookups / masters | **Yes** | `lookup_types`/`lookup_values` (`lib/lookups.php`) — used by every module |
| Offices / locations | **Yes** | `offices`; scope enforcement `scope_clause()` (`lib/access.php:794`) |
| Business partners (client/vendor) | **Yes** | `business_partners` + `partner_contacts` — shared by CRM, Ops, Recruitment, Money |
| Documents (ID vault) | **Partly** | `lib/identity.php` owns `person_documents` but every caller passes `person_kind='INSPECTOR'` — **nominally polymorphic, actually Ops-only** |
| Notifications / email | **Yes** | `ops_mail()` (`lib/ops.php:1869`), `email_log` |
| Audit trail | **No — fragmented** | five parallel trails: `idems_audit`, `activities`, `vendor_audit`, `portal_audit`, `evidence_chain` |
| KPI / analytics | **Yes (TAPI)** | `lib/tapi.php` — a real pluggable engine; see §4 |
| Approvals | **No** | the only general engine is recruitment-named (`recruit_approval_*`) |
| Person identity | **No** | three unreconciled mechanisms; see `04-identity-organisation-audit.md` |
| Projects / sites | **No** | no first-class project entity; site is a string on several tables |

**Conclusion.** The genuine platform core is: *tenant, settings, users, RBAC, lookups, offices, partners, mail, TAPI*. Identity, approvals, audit trail and projects are **claimed** as core by the brief but are **not** core today — each is either duplicated or module-owned. That gap is the main architectural debt.

---

## 3. Module layer

| Module | Sellable | Gate | Tables | Principal libraries |
|---|---|---|---|---|
| Administration | core | always on | 37 | `access`, `lookups`, `ops`, `orgadmin` |
| Operations | yes | `operations` | 63 | `ops`, `tosrm`, `pdso`, `attend`, `sched` |
| Reporting / IDEMS | yes | `reporting` | 41 | `idems` (10,942 ln), `urfe`, `uire` |
| Marketplace / Connect | **no** | setting | **65** | `connect_*` (≈25 files), `mkt_*` |
| Sales & CRM | yes | `sales` | 23 | `crm`, `leads`, `opportunities` |
| Quality | **no** | setting | **31** | `audits`, `capa`, `complaints`, `compliance`, `trust` |
| Recruitment / People | yes | `hr` | 22 | `recruit`, `recruitpipe`, `recruit_iv`, `recruit_offer`, `recruit_approval`, `position`, `deptorg`, `careers` |
| Money | yes | `money` | 20 | `books`, `costing`, `contracts`, `billable` |

**Marketplace is the largest module in the system by table count (65) and is not sellable.** Quality (31) likewise. Together they are **31% of the schema** sitting outside the commercial model.

---

## 4. TAPI — the analytics engine that must be reused, not rebuilt

`lib/tapi.php` (1,004 lines) is a genuine, pluggable KPI platform and is a **protected asset**:

- `kpi_defs` table — configurable KPI master: formula, unit, period, target, threshold, direction, scope, data-source lineage (`tapi.php:53-65`).
- A **metric-adapter registry** `tapi_metrics()` (`:94-190`) of named, scope-enforced atoms (`jobs.total`, `revenue.invoiced`, `sla.*`, `ncr.*`), extensible via `tapi_domain_metrics()` (`:856`).
- A **safe, non-`eval` formula parser** with a function whitelist (`:249-347`).
- Status grading with an explicit **NO_DATA vs real-zero** distinction (`:349-377`).
- Presentation kit: KPI cards, filter bar, SVG line/sparkline/pareto/heatmap (`:507-836`).
- `lib/tapi_score.php`: per-office/SBU/period target overrides (`kpi_targets`), weighted scorecards, cron-run alerts, and small-sample/fairness guardrails.

**A new module plugs in by registering metrics and seeding `kpi_defs`.** The brief's §20 requirement ("do not create a separate KPI engine if existing infrastructure can be reused") is therefore satisfiable — recruitment KPIs must be built as TAPI metrics.

Caveat: `tapi_can()` requires any `dash.*` permission or master, with **no `mod.*` permission and no licence gate** (`tapi_dash.php:165-168`) — so TAPI itself is not licensable and would expose metrics of unlicensed modules unless each metric is gated.

---

## 5. Dashboards (11 surfaces)

`''` (home, `views/dashboard.php`, 541 ln), `/command-centre`, `/operations`, `/recruitment`, `/mis`, `/crm-dashboard`, `/analytics` + `/analytics-kpis` (TAPI), `/owner`, area landings (`/sales`, `/quality`, `/money`, …), the setup cockpit, plus portal/vendor/pro dashboards.

**Degradation when a module is off works at two levels** and is genuine:
- whole-dashboard swap — `index.php:1183-1187`: if `!licence_enabled('operations') && licence_enabled('hr')`, home becomes the recruitment home;
- widget-level — `views/dashboard.php:120-140` gates the ops KPI tiles on `licence_enabled('operations')`.

**But** compliance and money bands are gated only by `can()`/`is_master()`, so any `|| is_master()` there bypasses the licence (`views/dashboard.php:147-186`).

Configurability is partial: section **order** is a hard-coded role switch (`views/dashboard.php:477-488`); the only tenant-editable layer is per-role landing + curated launchpad tiles in `lib/workspace.php`.

**Protected-asset ruling: the dashboard is REUSE/EXTEND. No redesign is justified by this audit.**
