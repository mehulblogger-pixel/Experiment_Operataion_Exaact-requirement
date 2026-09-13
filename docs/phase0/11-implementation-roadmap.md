# 11 — IMPLEMENTATION ROADMAP (ARCHITECTURE-LOCKED)
Covers deliverable **29**. Authoritative decisions: `15-ARCHITECTURE-LOCK.md`.

**Nothing here has been implemented. Phase 0 is audit + architecture lock only.**
All phases below are **NOT STARTED**.

Statuses (brief §36): NOT STARTED / IN PROGRESS / BLOCKED / READY FOR TEST / TESTING / FAILED / FIXING / READY FOR UAT / UAT PASSED / COMPLETED / DEPRECATED / DEFERRED.

**Production database: MySQL/MariaDB. Existing automated regression harness: SQLite.** MySQL/MariaDB is the authoritative database-testing target for every phase.

---

## Scope rule — findings are not scope

Every Phase-0 finding is classed **A · REQUIRED NOW** / **B · PROTECT** / **C · FUTURE-DEFERRED** / **D · OBSERVATION**. Only **A** items inside an authorised phase may be built. No finding may be implemented merely because the audit recorded it.

## Programme structure (locked — no Phase 1A/1B/1C)

```
PHASE 0   Audit + Architecture Lock                          ← COMPLETE, awaiting approval
PHASE 1   SaaS Entitlement & Module Boundary
PHASE 2   Recruitment Structural Foundation
PHASE 3   Hiring Request → Approval → Recruiter Assignment
PHASE 4   Multi-Source Fulfilment
PHASE 5   Recruitment KPI / SLA
PHASE 6   Person / Organisation / Marketplace Convergence
PHASE 7   UX Consolidation + Final E2E
```

Subtasks exist inside a phase. Phases are not split into independent major phases.

---

# PHASE 1 — SaaS Entitlement & Module Boundary  `NOT STARTED`

**Objective.** A tenant can use exactly the modules it is entitled to. An unentitled module is inaccessible. An entitled module works. Historical data is never deleted because a module became inactive.

**Single controlled phase. No unrelated architecture cleanup may be added to it.**

| # | Scope item | Existing component | Decision |
|---|---|---|---|
| 1 | Correct entitlement ceiling behaviour — absent entitlement must mean **deny**, with an explicit auditable "unlimited" marker for the control install only | `licence_entitled_ceiling()`, `module_entitled()` (`lib/licence.php:122-152`) | REFACTOR |
| 2 | **Safely establish explicit entitlement for every existing tenant first**, verified, *before* the default changes | `saas_entitlement_ensure()` (`lib/saas_tenants.php:352+`) | EXTEND |
| 3 | Make **Marketplace** a properly commercially controllable module | `PRODUCT_MODULES`, `marketplace_addon_on()` | EXTEND |
| 4 | **Keep Operations unchanged** | — | **PROTECT** |
| 5 | **Keep Quality unchanged** | — | **PROTECT** |
| 6 | Ensure Recruitment/HR entitlement is correctly enforced | `ops_module_gate()` | VERIFY/EXTEND |
| 7 | Protect **public Recruitment/Careers routes** with `hr` entitlement | `lib/careers.php:154` | BUILD (small) |
| 8 | Protect **Marketplace public/front-door routes** with Marketplace entitlement | `install_marketplace_enabled()` | REFACTOR |
| 9 | Ensure **background/cron tasks** respect module entitlement | `cron.php`, `cron_ads.php` | BUILD (small) |
| 10 | Replace licence-bypassing bare `is_master()` with the existing licence-aware mechanism **where necessary** | `is_master_of()` (`lib/licence.php:169-176`) | REFACTOR |
| 11 | Define + implement module states: **ACTIVE / NOT SUBSCRIBED (LOCKED) / SUSPENDED / DISABLED BY ADMIN** | tenant `status` exists; module-level does not | BUILD |
| 12 | Audit module activation/deactivation (who, when, what) | `activities` | EXTEND |
| 13 | **Preserve historical data during deactivation** (already true — lock it with tests) | verified: no deletion path exists | PROTECT + TEST |
| 14 | Ensure **reactivation restores access** to existing data | — | TEST |
| 15 | Verify direct URL, POST, AJAX, public route and background access **cannot bypass** entitlement | `ops_module_gate()` single chokepoint | TEST |
| 16 | Add/strengthen **MySQL/MariaDB entitlement regression testing** | `tests/bootstrap.php` (SQLite harness) | BUILD |
| 17 | **Run full regression** — entitlement/access/licence code is platform-wide | 444 files / 6948 assertions | GATE |

### Phase 1 completion criteria
An unentitled module is unreachable via navigation, page, route, direct URL, POST, AJAX, export, report, public route and background job. The locked state is visible but not usable. Activation/deactivation is audited. No historical data is lost. MySQL/MariaDB entitlement regression passes. **Scenario S-1 (Operations-only tenant) passes.**

### Item 10 — explicit boundary
`is_master()` is replaced **only where it functions as a licence bypass**. This is a targeted correction, not a sweep of all 163 sites, and it must not alter Operations, Quality, Reporting, Money or Marketplace behaviour beyond entitlement enforcement.

---

# PHASE 2 — Recruitment Structural Foundation  `NOT STARTED`

Fixes existing Recruitment defects **before** the new operating model is added.

| # | Scope item | Evidence |
|---|---|---|
| 1 | Multi-vacancy requisition closure defect — first hire must not close a 20-vacancy requirement | `lib/ops.php:5090-5093` |
| 2 | Configurable candidate pipeline becomes the **authoritative** pipeline | `lib/recruitpipe.php:238-400` |
| 3 | Remove/contain the legacy stage inconsistency | `lib/recruitpipe.php:390-400` |
| 4 | Fix the requisition approval callback status defect (writes values absent from `REQ_STATUS`) | `lib/recruit_approval.php:234-236` |
| 5 | Source party FK — `candidates.agency_id` | `lib/ops.php:197` (free-text, no FK) |
| 6 | `source_type` correction (column exists, never written); add `CAREERS` to the source vocabulary | `lib/recruit_cc.php:29`, `lib/careers.php:111` |
| 7 | Duplicate `inspector_day_status` DDL reconciliation | `lib/ops.php:503-505` vs `lib/attend.php:44-46` |
| 8 | Recruitment persistence extraction from `lib/ops.php` **where safe** | `lib/ops.php:4919-5172` |
| 9 | Separate internal organisation department/designation semantics from vacancy taxonomy | `05-…` §2.3 |
| 10 | Preserve existing Recruitment data; preserve Operations behaviour | — |

**Every change touching `lib/ops.php` requires Operations regression.**

---

# PHASE 3 — Hiring Request → Approval → Recruiter Assignment  `NOT STARTED`

```
Requestor → Hiring Request → Job Profile → Job Description → Approval
          → Approved Requirement/Requisition → Recruiter Assignment → Recruitment execution
```

Recruiter assignment must support: assigned recruiter · assigned by · assigned date/time · reassignment · notification · ownership · future KPI attribution.

- **Sourcing may not begin before the required approval state.**
- **Career Page remains OPTIONAL.** Manual CV intake MUST work fully without it.
- All CV sources (email, referral, recruiter, agency, direct/manual, portal, other) enter the **same candidate/person engine**.
- Reuse the existing approval engine — widen `APPR_ENTITIES` and actually invoke `appr_start()` for `REQUISITION` (today only OFFER calls it). **No second approval engine.**
- Reuse the proven transition-guard pattern (`cx_req_can_transition`, `lib/connect_market.php:37-42`) for requisition status.

---

# PHASE 4 — Multi-Source Fulfilment  `NOT STARTED`

One requirement, many sources — e.g. 20 positions = 5 internal + 3 direct + 4 Supplier A + 3 Supplier B + 5 marketplace, remaining **ONE** requirement.

Implement: fulfilment allocation · source type · source party · quantity · allocated · remaining · over-allocation protection · partial fulfilment · replacement · source traceability · marketplace connection · candidate/application relationship.

**Do NOT physically merge requisitions and Marketplace requirements.** Use CONNECT / MAP / adapter architecture. Precedent: `lib/connect_source.php:35-51`.

---

# PHASE 5 — Recruitment KPI / SLA  `NOT STARTED`

**Reuse TAPI (`lib/tapi.php`, `lib/tapi_score.php`). Do NOT build another KPI engine.**

**First capture dates** (none exist today): target · actual · stage · assignment · submission · interview · offer · joining · closure. `recruit_stages.sla_days` is stored but never read — start reading it.

**Then register Recruitment metrics into TAPI** (`kpi_defs` + metric adapters): sourcing volume · screening volume · submission volume · interview conversion · selection conversion · offer conversion · joining conversion · time-to-source · time-to-fill · stage ageing · SLA adherence · recruiter workload. Include recruiter-level KPIs and scorecards via `tapi_score`.

Each registered metric must carry its own module gate — TAPI itself is not licence-gated (`tapi_dash.php:165-168`).

---

# PHASE 6 — Person / Organisation / Marketplace Convergence  `NOT STARTED`

**Use CONNECT, not MERGE.** Maintain existing tables where they have valid business purposes.

- Establish a canonical Person/Resource **conceptual** model.
- The existing `inspectors` table **may remain the technical spine** for compatibility. **Do not rename it for cosmetic reasons.**
- Extend identity links between: Candidate · Inspector · Marketplace Professional · User · Bench · Client resource · Supplier resource.
- Connect organisation relationships — Business Partner ↔ Marketplace Organisation ↔ Agency — **without physically merging their tables**.
- Add duplicate detection where none exists (5 person tables have no uniqueness rule).
- Correct `lib/party.php` SQLite-dialect `||` concatenation that degrades silently on the production MySQL/MariaDB engine.

---

# PHASE 7 — UX Consolidation + Final E2E  `NOT STARTED`

Only after the underlying architecture is stable.

Setup hub · recruitment navigation simplification · **dashboard extension only where necessary, preserving the existing dashboard** · workspace configuration where appropriate · Zero Training UI gate (`docs/05-ui-ux-blueprint.md`) · final end-to-end journeys.

---

## ENTER ONCE → USE EVERYWHERE

No phase may require a user to re-enter information the platform already holds — departments, designations, organisations, people, candidates, recruiters, suppliers, clients, job profiles, taxonomy, documents. Where structures differ, **CONNECT/MAP** them.

## Class C — FUTURE / DEFERRED (documented, deliberately not implemented)
Five audit trails · five billing document shapes · four field/template metadata stores · three criteria engines · Project/Engagement as a first-class entity · flat-vs-graph taxonomy consolidation inside Connect · test-harness isolation (444 files share one mutating database) · Quality becoming independently subscribable (**separate future architecture decision**).

## Class B — PROTECT (must not be disturbed)
Operations engine · Quality engine · Money engine · Reporting/IDEMS · existing Dashboard · TAPI KPI engine · existing identity mechanisms · existing Marketplace engines · per-tenant database isolation · the no-deletion-on-deactivation behaviour.
