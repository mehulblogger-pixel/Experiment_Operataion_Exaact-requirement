# 07 — WORKFLOW, RBAC, ROUTE/API AND DATABASE AUDIT
Covers deliverables **17, 18, 19, 20**.

---

## 1. Workflow audit (deliverable 17)

| Domain | Vocabulary | Transitions guarded? |
|---|---|---|
| Marketplace requirement | `DRAFT→OPEN→SHORTLISTING→AWARDED→CLOSED` (+CANCELLED/EXPIRED) | **YES** — `CX_REQ_TRANSITIONS` + `cx_req_can_transition()` (`lib/connect_market.php:21-42`) |
| Marketplace application | `APPLIED→SHORTLISTED→OFFERED→ACCEPTED/DECLINED` (+REJECTED/WITHDRAWN) | **YES** (`:29-35`) |
| Job offer | `DRAFT→PENDING_APPROVAL→APPROVED→ISSUED→VIEWED→ACCEPTED/DECLINED/EXPIRED/WITHDRAWN` | **YES** — `offer_issue()` blocks unapproved (`lib/recruit_offer.php:31,265`) |
| Requisition | `OPEN, PROPOSED, OFFERED, HIRED, CLOSED, CANCELLED` (`lib/ops.php:53`) | **NO** — free dropdown, set directly (`lib/ops.php:5091`) |
| Candidate | `CAND_STAGES` (10 values, `lib/ops.php:70-82`) | **NO** guard; configurable pipeline runs in parallel |
| Approvals | rule → multi-level chain → SLA → reminder → escalation | **YES** (`lib/recruit_approval.php`) but invoked **only for OFFER** |

**Findings.**
- W-1: the platform already contains a proven transition guard (Connect). Recruitment does not use one. **Reuse it, don't invent one.**
- W-2: `recruit_approval` writes requisition statuses `'approved'`/`'on_hold'` that are **not members of `REQ_STATUS`** (`lib/recruit_approval.php:234-236`) — latent data-integrity defect, currently masked because the path is never executed.
- W-3: two parallel candidate stage systems, coarsely synced (`lib/recruitpipe.php:390-400`).

---

## 2. RBAC audit (deliverable 18)

**Roles — 16 built-in** (`const ORG_ROLES`, `lib/access.php:10-19`): MASTER_ADMIN, BUSINESS_DIRECTOR, SBU_HEAD, BRANCH_MANAGER, BRANCH_APP_MANAGER, OPERATION_MANAGER, ASST_MANAGER, COORDINATOR, BUSINESS_DEV_MANAGER, KEY_ACCOUNTS_MANAGER, MARKETING_MANAGER, MARKETING_EXECUTIVE, FINANCE, SR_INSPECTOR, INSPECTOR, ADMIN (legacy).

**Per-tenant custom roles: YES, and safely bounded.** `custom_roles_all()` / `custom_role_add()` (`lib/access.php:70-124`) store `{KEY:{label,base}}` in the per-workspace `custom_roles` setting. A custom role **always derives from a built-in base** and `role_effective_key()` (`:103-109`) resolves it before every decision, so **it can never exceed its base**. Per-role permission overrides live in the `role_access` setting.

**Permissions.** Two catalogues — 37 fine-grained keys (`PERMISSIONS`, `:128-186`) and 31 module keys (`ACCESS_MODULES`, `:302-337`) expanded to `mod.<key>.view|edit`. Convention `<area>.<object>.<action>`.

**Resolution chain:** `can()` → `ua()` (`:723-766`) → `user_effective_perms()` (`:594-611`): master gets everything → per-user CSV override → `role_perms()` (tenant `role_access` JSON) → `role_defaults()`. Scope (offices/SBUs) is **separate** from permissions: `scope_clause()` (`:794-818`), `scope_allows()` (`:820-838`).

### 2.1 Licence × permission — correct by design
```php
access.php:767  function can($perm) {
access.php:768      if (licence_blocks($perm)) return false;     // licence checked FIRST
access.php:770      return $a['master'] || in_array($perm, $a['perms'], true);
```
The licence check runs **before** the master bypass. `licence_blocks()` (`lib/licence.php:182-188`) refuses `mod.*` permissions whose owning module is off; bare capabilities (`data.salary`, `dash.financial`) are deliberately not licensable (`licence.php:178-181`). **This is right and must be preserved.**

### 2.2 FINDING — the `is_master()` bypass surface (High)
`is_master()` (`lib/ops.php:571`) returns the raw flag **with no licence check**. The licence-aware replacement `is_master_of()` (`lib/licence.php:169-176`) exists but **adoption is partial**. Bare `is_master()` occurrences:

| File | Count |
|---|---|
| `lib/idems.php` | 59 |
| `lib/ops.php` | 58 |
| `lib/crm.php` | 23 |
| `lib/areas.php` | 23 |

Any screen guarded `can('mod.x.view') || is_master()` lets an administrator reach a module the tenant did not buy. This is the **largest remaining entitlement hole after the fail-open ceiling**, and it is also why the dashboard's compliance/money bands can render for an unlicensed tenant (`views/dashboard.php:147-186`).

**Review rule for all future phases: `|| is_master()` is forbidden as a licence bypass; use `is_master_of($module)`.**

---

## 3. Route / API audit (deliverable 19)

| Surface | Count | Gate |
|---|---|---|
| `ops_dispatch` routes | 435 | `ops_module_gate()` — 446-entry route→feature map (`lib/ops.php:2425`) |
| Inline `index.php` routes (post-login) | 9 (`clients`, `vendors`, `partner*`, `po`) | **none** (all core `admin`; low risk) |
| Public routes | ~15 (`login`, `forgot`, `reset`, `verify`, `verify-pdf`, `p/…`, `portal/…`, `vendor/…`, `pro/…`, `join`, `get-started`, `connect`, `careers`, `buy`, `complaints-policy`) | setting-gated at best |
| Background jobs | `cron.php`, `cron_ads.php` | **none** — zero entitlement references |
| API | `api.php` only | **Not a tenant API** — licence server only (`api.php:1-44`) |

**Positive finding:** the absence of a general tenant REST/AJAX API materially shrinks the bypass surface described in brief §3. Route-level enforcement is close to complete once the public and cron gaps are closed.

**Negative finding (F3):** `lib/careers.php` contains **zero** `licence_enabled`/`module_entitled` references. The public careers page and its application intake — which writes `candidates` rows — are gated only by the `careers_enabled` **setting** (`:34,154`).

---

## 4. Database audit (deliverable 20)

**306 distinct tables** across 307 `CREATE TABLE` statements in 114 library files. Largest declarers: `ops.php` 26, `idems.php` 21, `db.php` 12, `crm.php` 11, `connect_taxonomy.php` 10.

Per module: marketplace/connect **65**, operations **63**, reporting/idems **41**, admin/core **37**, quality **31**, sales/crm **23**, recruitment/hr **22**, money **20**, saas-control **5**.

**Isolation: one database per tenant** (see `02-…` §1). **No tenant table carries a `tenant_id` column** — confirmed by grep. The isolation boundary is the DSN, not the query.

**Migrations: code-as-schema, idempotent, no rollback.**
- `boot()` → `run_schema(true)`; `migrate_all()` → `run_schema(false)` (DDL only, never re-seeds) — `lib/db.php:416-582`.
- A hand-ordered sequence of ~80 `if (function_exists('x_migrate')) x_migrate();` calls, dependency-ordered, ending with `indexes_migrate()` then `ensure_admin()`.
- Every migration uses `CREATE TABLE IF NOT EXISTS` + `ensure_column()` — **693 `ensure_column` call sites**. `ensure_column` (`lib/db.php:225-246`) introspects first and swallows duplicate-column errors (MySQL 1060 / SQLSTATE 42S21) while re-throwing everything else, so it is **race-safe**.
- Triggered by a **code fingerprint** compared with `settings.schema_sig` (`index.php:370-663`), so migrations run once per deploy.

**Idempotent: yes. Rollback: none.** There is no `schema_version` or migration-history table, no down-migrations, and no `DROP TABLE` in the migrate path. **Schema only moves forward — a bad column is permanent unless hand-fixed.** This is a Phase-gate constraint: every schema change proposed in later phases must be additive and reversible by ignoring, never by dropping.

**Risk R-5:** `run_schema()` is **order-dependent** and that order is implicit in a hand-written list. `inspector_day_status` (§1.4 of `06-…`) proves the risk is real.
