# 11 — DETAILED IMPLEMENTATION ROADMAP
Covers deliverable **29**.

**Nothing in this roadmap has been implemented. Phase 0 is audit only.**
Statuses use the brief's §36 system: NOT STARTED / IN PROGRESS / BLOCKED / READY FOR TEST / TESTING / FAILED / FIXING / READY FOR UAT / UAT PASSED / COMPLETED / DEPRECATED / DEFERRED.

All phases below are **NOT STARTED**.

---

## Sequencing principle

The brief asks for Recruitment convergence. **Recruitment cannot be validated until "a recruitment-only tenant" is a state the platform can actually enforce.** Today it is not (F1, F2, F3). Therefore entitlement hardening comes first — it is also the largest single UX simplification (`10-…` §2.2).

```
PHASE 1  Entitlement becomes a hard boundary      ← prerequisite for everything
PHASE 2  Test infrastructure catches what matters ← prerequisite for safe change
PHASE 3  Recruitment structural corrections       ← fixes live defects
PHASE 4  Recruitment REQUEST → approval → assignment
PHASE 5  Multi-source FULFIL
PHASE 6  MEASURE (KPI/SLA on TAPI)
PHASE 7  Identity convergence
PHASE 8  UX simplification consolidation
```

---

## PHASE 1 — Entitlement as a hard security boundary  `NOT STARTED`
**Objective:** a tenant can use exactly what it bought, enforced at every layer, defaulting to deny.

| Sub-phase | Task | Existing component | Decision |
|---|---|---|---|
| 1.1 | Make the ceiling **default-deny**: absent `saas_entitled_modules` must not mean "all". Introduce an explicit, auditable "unlimited" marker for the control install only. | `licence_entitled_ceiling()`, `module_entitled()` (`lib/licence.php:122-152`) | **REFACTOR** |
| 1.1a | **Guarded backfill first** — write an explicit ceiling for every existing tenant from its current live modules, verified, before flipping the default. | `saas_entitlement_ensure()` (`lib/saas_tenants.php:352+`) | **EXTEND** |
| 1.2 | Promote **Marketplace** to a real module key `marketplace`. | `PRODUCT_MODULES`, `marketplace_addon_on()` | **EXTEND** |
| 1.3 | Promote **Quality** to a real module key `quality` (decouple from the `operations` feature list and the accreditation-pack setting). | `PRODUCT_MODULES`, `accredited_pack_on()` | **EXTEND** |
| 1.4 | Add plan slots for both; extend `superadmin_tiers()` so Customer D and Customer E are expressible. | `lib/superadmin.php` | **EXTEND** |
| 1.5 | Gate the **public careers route** on `hr` entitlement (not only the `careers_enabled` setting). | `lib/careers.php:154` | **BUILD (small)** |
| 1.6 | Gate public Connect/marketplace front doors on `marketplace` entitlement. | `install_marketplace_enabled()` | **REFACTOR** |
| 1.7 | Gate `cron.php` / `cron_ads.php` tasks per module. | `cron.php` | **BUILD (small)** |
| 1.8 | Replace bare `|| is_master()` licence bypasses with `is_master_of($module)` — 163 known sites (idems 59, ops 58, crm 23, areas 23). | `is_master_of()` (`lib/licence.php:169-176`) | **REFACTOR** |
| 1.9 | Add module states **ACTIVE / NOT SUBSCRIBED (locked) / SUSPENDED / DISABLED BY ADMIN** with a "locked, upgrade" affordance. Preserve existing terminology. | none today | **BUILD** |
| 1.10 | Audit-log module activation/deactivation (who, when, what). | `activities` | **EXTEND** |

**Completion criteria:** an un-entitled module is unreachable via menu, route, direct URL, form POST, public route and cron; the locked state is visible; every change is logged; Phase-2 entitlement tests pass.

## PHASE 2 — Test infrastructure  `NOT STARTED`
| Sub-phase | Task | Decision |
|---|---|---|
| 2.1 | Add a **MySQL test run** of the full suite (the product ships on MySQL; the suite only exercises SQLite — `08-…` W1). | **BUILD** |
| 2.2 | Add **end-to-end entitlement tests** that drive a real request through the router for an un-entitled module and assert denial (none exist today). | **BUILD** |
| 2.3 | Add **cross-tenant** entitlement tests. | **BUILD** |
| 2.4 | Convert the 51 self-skip guards so a missing function **fails** rather than passes (`08-…` W3). | **REFACTOR** |
| 2.5 | Add upgrade / downgrade / re-activation lifecycle tests (brief §26). | **BUILD** |
| 2.6 | Add the Operations-only tenant regression scenario (brief §27). | **BUILD** |

**Gate: Phase 3 onward may not begin until 2.1, 2.2 and 2.6 pass.**

## PHASE 3 — Recruitment structural corrections  `NOT STARTED`
Live defects found by this audit, independent of new features.

| Task | Evidence | Decision |
|---|---|---|
| 3.1 First hire must not close a multi-vacancy requisition | `lib/ops.php:5090-5093` | **FIX** |
| 3.2 Reconcile the two candidate stage systems; configurable pipeline becomes the source of truth | `lib/recruitpipe.php:390-400` | **REFACTOR** |
| 3.3 Fix approval callback writing statuses absent from `REQ_STATUS` | `lib/recruit_approval.php:234-236` | **FIX** |
| 3.4 `candidates.agency` → add `agency_id` FK (unblocks Source Party) | `lib/ops.php:197` | **BUILD (small)** |
| 3.5 Write `source_type`; add `CAREERS` to the source vocabulary | `lib/recruit_cc.php:29`, `lib/careers.php:111` | **FIX** |
| 3.6 Reconcile duplicate `inspector_day_status` DDL | `lib/ops.php:503` vs `lib/attend.php:44` | **FIX** |
| 3.7 Move requisition/candidate persistence out of `lib/ops.php` into recruitment libs | `09-…` §1 | **REFACTOR** |
| 3.8 Separate *our* department/designation from *the vacancy's* | `05-…` §2.3 | **BUILD** |

## PHASE 4 — REQUEST → approval → assignment  `NOT STARTED`
4.1 Hiring Request with a **Requestor** and a pre-approved state · 4.2 **Invoke the existing approval engine for `REQUISITION`** (widen `APPR_ENTITIES`; today only OFFER calls `appr_start()`) · 4.3 Recruiter assignment as an event (assigned-by, assigned-at, notification) · 4.4 Adopt a guarded transition table for requisition status, reusing the Connect pattern (`cx_req_can_transition`).

## PHASE 5 — Multi-source FULFIL  `NOT STARTED`
5.1 Per-source allocation against one requirement (internal / direct / agency / supplier / marketplace) with quantities · 5.2 Allocation vs remaining vs over-allocation guards · 5.3 Source traceability to a **party**, not a string · 5.4 **CONNECT** requisition ↔ marketplace requirement via a thin fulfilment link (no table merge; precedent `lib/connect_source.php:35-51`).

## PHASE 6 — MEASURE  `NOT STARTED`
6.1 Target + actual dates per requisition and per stage (**prerequisite**; none exist) · 6.2 Read `recruit_stages.sla_days` (stored but never read) · 6.3 Register recruitment metrics in **TAPI** and seed `kpi_defs` — no new KPI engine · 6.4 Gate each TAPI metric by module (TAPI itself is unlicensed) · 6.5 Recruiter scorecards via existing `tapi_score`.

## PHASE 7 — Identity convergence  `NOT STARTED`
7.1 Adopt `inspectors` as the person spine (**CONNECT, never MERGE**) · 7.2 Add uniqueness/duplicate detection where none exists · 7.3 Extend `cx_identity_link` coverage · 7.4 Fix `lib/party.php` SQLite-only `||` concatenation that degrades silently on MySQL · 7.5 Cross-reference `business_partners` ↔ `cx_organisations` ↔ `agencies`.

## PHASE 8 — UX consolidation  `NOT STARTED`
8.1 One Setup hub · 8.2 Move dashboard section order into the existing `workspace_config` layer (CONFIGURE, not rebuild) · 8.3 Zero-Training-UI gate per `docs/05-ui-ux-blueprint.md`.

---

## Explicitly DEFERRED (recorded, not scheduled)
Five audit trails · five billing document shapes · four field/template metadata stores · three criteria engines · Project/Engagement as a first-class entity · flat-vs-graph taxonomy consolidation inside Connect.
