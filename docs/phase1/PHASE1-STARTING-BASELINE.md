# PHASE 1 — CLEAN START IMPLEMENTATION READINESS REPORT

**Date:** 2026-09-14 · **Branch:** `claude/testing-branch-setup-0gqe8n`
**Status:** READINESS ONLY — no application code changed, no behaviour changed.

---

## 1. Current commit

`e60cf4d` — *recovery report: a healthy workspace is healthy, not recovered*

## 2. Current git status

Clean, with one disclosure. Implementation of the fail-closed ceiling had begun
(`lib/licence.php`, a 24-assertion test) before the clean-start instruction
arrived. **It has been reverted**, so the working tree is exactly `e60cf4d`. The
design and its tests will be re-applied at Milestone 3/5, not before.

## 3. Current test count

**7,176 assertions, 0 failing**, across **449 test files**. PHP 8.4.19, SQLite
harness.

## 4. Current entitlement implementation

All in `lib/licence.php`:

| Function | Line | Role |
|---|---|---|
| `licence_owner($accessModule)` | 55 | maps a fine-grained access module → product module |
| `licence_is_core($key)` | 65 | core modules can never be switched off |
| `licence_disabled($reload)` | 72 | the switched-off list (setting + env override) |
| `licence_entitled_ceiling()` | 122 | the paid ceiling — **returns `null` (no limit) when blank** |
| `module_entitled($key)` | 142 | core → yes; signed licence → decides; else ceiling; **`null` ceiling ⇒ true** |
| `licence_enabled($key)` | 153 | not in the disabled list |
| `licence_blocks($perm)` | 182 | blocks a `mod.<access>.<verb>` permission when its owner is disabled |

**The Phase-0 defect, confirmed in code:** `licence_entitled_ceiling()` returns
`null` on a blank ceiling (line 130) and `module_entitled()` treats `null` as
"allow everything" (line 149). **Unknown entitlement currently fails OPEN.**

## 5. Current module identifiers

Two namespaces, bridged by `licence_owner()`. This is existing architecture and
is to be preserved.

**Product modules** (`PRODUCT_MODULES`, the commercial unit — 6):

| Key | Label | Core |
|---|---|---|
| `operations` | Operations | no |
| `admin` | Administration | **YES** |
| `sales` | Sales & CRM | no |
| `reporting` | Inspection reporting | no |
| `money` | Money | no |
| `hr` | People & hiring | no |

**Recruitment identifier is `hr`.** No competing identifier will be created.

**Access modules** (fine-grained, owned by a product module): `calls`, `jobs`,
`reconcile`, `vouchers`, `equipment`, `competence`, `impartiality`, `identity`,
`complaints`, `ncr`, `capa`, `audits`, `datacontrol`, `confidentiality`,
`overheads` → **operations**; `masters`, `users`, `settings`, `clients`,
`vendors`, `reports`, `portal` → **admin**; `leads`, `inquiries`, `quotes`,
`crm_orders`, `crm_reports` → **sales**; `idems` → **reporting**; `invoicing`,
`profitability` → **money**; `hiring` → **hr**.

Marketplace and Quality are **not** product modules. Quality operates inside the
Operations boundary and will not be split.

## 6. Commercial entitlement source

The **control database**, `saas_tenants.enabled_modules` (JSON array of product
module keys), plus `plan`, `plan_expiry`, `status`, `extra_user_seats`. Written
by provisioning, the super-admin console and billing. Survives uploads.

Live evidence (3 workspaces): all three record `["admin","hr"]`.

## 7. Runtime entitlement source

Settings **inside each workspace's own database**:

| Setting | Meaning |
|---|---|
| `saas_entitled_modules` | the paid ceiling — what may be switched on |
| `modules_off` | what is currently switched off |
| `product_package` | the plan it was provisioned on |
| `saas_paid_modules` | separately-paid modules |
| `saas_provisioned` | first-boot completed |

Written by `saas_apply_plan_modules()` (lib/saas_tenants.php:345).

Live evidence — the one provisioned workspace: `saas_entitled_modules = "hr"`,
`modules_off = "operations,sales,reporting,money"`, effective = `admin, hr`.
**Default-deny would change nothing for it** (`would_lose: []`).

## 8. Existing route gating

`ops_module_gate()` (lib/ops.php) — **459 route→access-module entries** over 209
lines, enforced at **one chokepoint**: `ops_dispatch()`, lib/ops.php:2697.

Coverage by access module (top): idems 64, jobs 48, leads 44, invoicing 30,
quotes 28, **hiring 27**, settings 24, audits 19, ncr 18, calls 17, capa 15,
reports 12, identity 12, complaints 12, portal 11.

This is the system to reuse. No second route-gating system will be created.

## 9. Existing public-route gaps

Two public routes are handled in `index.php` **before** the dispatcher, so they
never reach `ops_module_gate()`:

| Route | index.php | Note |
|---|---|---|
| `careers`, `careers/*` | 1056 | public careers page + application intake (`hr`) |
| `get-started` | 1003 | public workspace signup |

`careers` belongs to `hr` and must be entitlement-gated. `get-started` is a
platform route, not a module route — to be confirmed, not assumed.

## 10. Existing API / AJAX gaps

`api.php` — **44 lines, no entitlement check of any kind** (no
`module_entitled`, no `ops_module_gate`, no `licence_enabled`). Its surface must
be enumerated before anything is changed.

`module_entitled()` has only **5 call sites** in the whole application and
`licence_blocks()` **1**. Enforcement today rests almost entirely on
`licence_disabled()` + `ops_module_gate()`, not on commercial entitlement.

## 11. Existing export / report gaps

Export and report routes are dispatched through `ops_dispatch()` and therefore
inherit `ops_module_gate()`. **Not yet verified** route by route; to be
enumerated in Milestone 7 rather than claimed here.

## 12. Existing background-job gaps

`cron.php` (386 lines) and `cron_ads.php` (111 lines) contain **no**
`module_entitled`, `licence_enabled` or `ops_module_gate` call. Module-specific
scheduled work currently runs regardless of entitlement.

## 13. Marketplace current state

Not a `PRODUCT_MODULES` key, so no ceiling governs it. Cloud installs default it
**ON**. Live evidence for all three workspaces: `marketplace_addon` never set,
`connect_enabled` never set, **not present in any purchase record**.

Therefore: **on by legacy default, purchased by nobody.** No backfill is
warranted and none will be manufactured.

## 14. `is_master()` findings

**441 call sites** (excluding the definition) — lib/ops.php 58, views/ 96, the
rest across lib/. Phase 0 estimated ~163; the measured figure is higher and this
report uses the measured one.

No mass replacement. Milestone 10 is a targeted review classifying each relevant
use as platform-owner / tenant-administration / module functionality / global,
changing only genuine entitlement-boundary violations.

## 15. Existing tenant migration / backfill status

| Workspace | Provisioned | Ceiling | Classification |
|---|---|---|---|
| `acme-fire-safety-recuirtment-company` | yes | `hr` | **SAFE** — loses nothing |
| `sachee-hr-recruitment-services` | no | blank | never opened — no state to migrate |
| `xyz-recurit` | no | blank | never opened — no state to migrate |

Summary: `AMBIGUOUS: 0`, `ERROR: 0`, `measurable: 1`, `safe_to_flip: true`.

**Backfill requirement is therefore near-nil**, because the only workspace with
runtime state already carries a correct ceiling. This will still be verified
per-tenant at Milestone 4; no purchase will be invented and no default converted
into a purchase.

## 16. Infrastructure remediation status

**COMPLETE and FROZEN.** Tenant data above the web root; deleted workspaces
never silently re-created; safer tenant database creation; deployment
verification; live-configuration identification; credential-file protection;
installation safety guard; database-capability testing corrected. All covered by
tests inside the 7,176.

The outstanding hosting `GRANT` is a deployment matter and **is not a Phase 1
blocker**: Phase 1 concerns which modules a workspace may use, not how its
database is created.

## 17. Protected files

`lib/ops.php` (Operations), Quality tables and screens, Reporting, Money,
Dashboard, TAPI (`lib/tapi.php`), existing Marketplace/Connect engines, existing
Recruitment engines, the approval engine, the pipeline engine, the permission
engine, the audit/activity engine. Not to be redesigned, replaced or duplicated.

## 18. Files likely to change

| File | Expected change |
|---|---|
| `lib/licence.php` | ceiling precedence; fail-closed; module states |
| `lib/saas_tenants.php` | commercial→runtime entitlement sync; audit entries |
| `lib/ops.php` | **additions only** to `ops_module_gate` where routes are unmapped |
| `index.php` | gate the public `careers` route |
| `api.php` | entitlement check at the API boundary |
| `cron.php`, `cron_ads.php` | per-module guard around module-specific work |
| `views/ops/saas_companies.php` | administrator entitlement UX |
| `views/…` entitlement error page | consistent user-facing message |
| `tests/…` | new entitlement, negative-access and cross-tenant suites |
| `docs/phase1/…` | the required documents |

## 19. Proposed minimal implementation plan

Milestones exactly as instructed, stopping at each:

**M2** module registry — document the two namespaces and the `licence_owner()`
bridge; add nothing new.
**M3** precedence — one deterministic order (global licence → commercial
entitlement → suspension → administrator disable → ACTIVE), documented, no second
engine.
**M4** migration — evidence-based, per tenant, nothing guessed.
**M5** runtime enforcement — fail closed, with a plan-derived fallback so a lost
ceiling repairs itself rather than locking a paying customer out.
**M6–M8** route, API/export/report, public and background enforcement — reusing
`ops_module_gate()`.
**M9** Marketplace as a commercially controlled module, legacy default never
treated as a purchase.
**M10** targeted `is_master()` review.
**M11–M12** administrator UX and entitlement error UX.
**M13–M16** security/cross-tenant, regression, UAT, completion docs.

## 20. Risks

| Risk | Mitigation |
|---|---|
| Fail-closed locks out a paying customer | plan-derived fallback before denial; live evidence shows `would_lose: []`; governed by a switch that can be turned off without a deployment |
| Control install locked out of its own console | already exempt (licence.php:128); asserted by test before anything else |
| Adding gate entries breaks a working Operations route | additions only; full Operations regression per change |
| 441 `is_master()` sites | no mass replacement; targeted, documented, tested individually |
| **MySQL not exercised here** | no "MySQL tested" claim will be made; SQLite harness declared as supplementary throughout |
| Marketplace default misread as a purchase | the three states stay distinct in code and in reports |

## 21. Infrastructure remediation is FROZEN

**Confirmed.** It will not be undone, redesigned or refactored. Tenant
architecture will not be migrated during Phase 1.

## 22. No new infrastructure investigation will be started

**Confirmed.** No Step 2J or equivalent. An infrastructure issue will be raised
only if it is encountered during Phase 1 implementation, Phase 1 cannot safely
function without it, and it cannot be worked around — and then as a single
**INFRASTRUCTURE BLOCKER DISCOVERED** report, stopping before any change.

## 23. Phase 1 is the active development phase

**Confirmed.** Storage investigation closed. Phase 2+ functionality will not be
implemented. The application will be reused and extended, not rebuilt.

---

**PHASE 1 READINESS REPORT COMPLETE — AWAITING APPROVAL TO BEGIN MILESTONE 2.**
