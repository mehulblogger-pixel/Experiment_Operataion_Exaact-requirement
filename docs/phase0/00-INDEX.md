# EXAACT — PHASE 0: MASTER ARCHITECTURE AUDIT

**Status: PHASE 0 READY FOR REVIEW**
**Scope: audit, analysis, architecture, mapping, testing strategy, roadmap, risk. NO implementation performed.**

**Status of this document set: CORRECTED AND ARCHITECTURE-LOCKED.** See `15-ARCHITECTURE-LOCK.md` — the authoritative Phase-0 decision record. Where any document here disagrees with the architecture lock, **the architecture lock wins**.

**Production database: MySQL/MariaDB. Existing automated regression harness: SQLite.** The current automated suite therefore does not fully exercise the production MySQL/MariaDB database engine.

Baseline audited: the supplied working EXAACT codebase.
Measured at audit time: **154,873 lines of PHP**, **221 library files**, **394 view files**, **444 test files**, **435 distinct application routes**, **266 operational screens**.

No source file was modified during Phase 0. Every finding below carries `file:line` evidence.

---

## Deliverable map

The 32 required deliverables are covered by the documents in this folder.

| # | Deliverable | Document |
|---|---|---|
| 1 | Current Architecture Map | `01-architecture-map.md` |
| 2 | SaaS Architecture Audit | `02-saas-entitlement-audit.md` §1–2 |
| 3 | Module Entitlement Audit | `02-saas-entitlement-audit.md` §3–6 |
| 4 | Operations Audit | `03-module-audits.md` §1 |
| 5 | Recruitment Audit | `03-module-audits.md` §2 |
| 6 | Marketplace / Connect Audit | `03-module-audits.md` §3 |
| 7 | Reporting Audit | `03-module-audits.md` §4 |
| 8 | Quality Audit | `03-module-audits.md` §5 |
| 9 | Money Audit | `03-module-audits.md` §6 |
| 10 | Workforce Audit | `03-module-audits.md` §7 |
| 11 | Dashboard Audit | `03-module-audits.md` §8 |
| 12 | Identity / Person Audit | `04-identity-organisation-audit.md` §1 |
| 13 | Organisation Audit | `04-identity-organisation-audit.md` §2 |
| 14 | Requirement / Requisition Audit | `05-requirement-taxonomy-audit.md` §1 |
| 15 | Taxonomy Audit | `05-requirement-taxonomy-audit.md` §2 |
| 16 | Duplicate Audit | `06-duplicate-audit.md` §1 |
| 17 | Workflow Audit | `07-workflow-rbac-route-db-audit.md` §1 |
| 18 | RBAC Audit | `07-workflow-rbac-route-db-audit.md` §2 |
| 19 | API / Route Audit | `07-workflow-rbac-route-db-audit.md` §3 |
| 20 | Database Audit | `07-workflow-rbac-route-db-audit.md` §4 |
| 21 | Existing Test Audit | `08-test-audit-baseline.md` §1 |
| 22 | Test Baseline Reconciliation | `08-test-audit-baseline.md` §2 |
| 23 | Reuse / Extend / Connect / Refactor / Deprecate / Build Matrix | `06-duplicate-audit.md` §2 |
| 24 | Cross-Module Dependency Matrix | `09-dependency-matrix.md` |
| 25 | SaaS Entitlement Matrix | `02-saas-entitlement-audit.md` §7 |
| 26 | Data Migration Strategy | `10-migration-ux-kpi-strategy.md` §1 |
| 27 | UX Simplification Strategy | `10-migration-ux-kpi-strategy.md` §2 |
| 28 | KPI / SLA Strategy | `10-migration-ux-kpi-strategy.md` §3 |
| 29 | Detailed Implementation Roadmap | `11-implementation-roadmap.md` |
| 30 | Detailed Testing Roadmap | `12-testing-roadmap.md` |
| 31 | Risk Register | `13-risk-register.md` |
| 32 | Phase Completion Template | `14-phase-completion-template.md` |
| — | **Architecture Lock (authoritative decision record)** | **`15-ARCHITECTURE-LOCK.md`** |

---

## The five findings that decide the programme

**F1 — Entitlement currently FAILS OPEN. (Critical)**
`licence_entitled_ceiling()` returns `null` when the tenant setting `saas_entitled_modules` is empty, and `module_entitled()` then returns `true` for every module (`lib/licence.php:122-152`). A tenant whose ceiling was never written is entitled to **everything**. The locked principle in the brief — entitlement as a hard security boundary — is therefore not yet met by default. This is the single highest-priority fix and is the root cause of a recruitment workspace displaying the full ERP.

**F2 — Marketplace is not a sellable module. (Critical for the commercial model)**
`PRODUCT_MODULES` contains only six entries: `operations, admin(core), sales, reporting, money, hr` (`lib/licence.php:29-50`). Marketplace/Connect is gated by a plain tenant setting that **defaults to ON for every cloud tenant** (`marketplace_addon_on()`, default `'1'` when cloud). Quality is gated by accreditation-pack settings (`accredited_pack_on()`). Neither passes through the entitlement ceiling, so neither can be sold, withheld, suspended or audited as a module. Customer D (Marketplace + Operations + Reporting) is **not expressible** in today's plan catalogue.

**Scope ruling:** *Marketplace* is corrected in Phase 1. *Quality* is **KEEP / PROTECT** — its bundling inside Operations is recorded as an **observation only**, not an implementation task (architecture lock §6–7).

**F3 — Public routes bypass the module gate. (High)**
The gate `ops_module_gate()` is enforced at exactly one chokepoint, `ops_dispatch()` (`lib/ops.php:2697`), invoked from the **last line** of the front controller (`index.php:1670`). Everything dispatched earlier bypasses it: 9 authenticated routes handled inline in `index.php`, plus all public routes. Critically, `lib/careers.php` contains **zero** references to `licence_enabled` or `module_entitled`; the public careers page and its application intake — which writes `candidates` rows — are gated only by the tenant setting `careers_enabled` (`lib/careers.php:34,154`). A tenant that loses the `hr` module keeps serving jobs and collecting applicants.

**F4 — One human exists as up to eleven records, with three unreconciled identity layers. (High)**
Eleven tables represent a person (`users, inspectors, candidates, cx_professionals, partner_contacts, client_users, vendor_users, back_office_staff, subcons, cx_bench, cx_client_bench`). Three separate identity mechanisms exist and do not agree: `lib/identity.php` (an ID-document vault, not resolution), `lib/party.php` (a stateless query-time matcher owning no table, used in only 3 places), and `lib/connect_identity.php` (the only persisted link ledger, Connect-only). `inspectors, candidates, back_office_staff, subcons, partner_contacts` have **no uniqueness constraint at all**.

**F5 — Multi-source fulfilment does not exist, and the first hire closes the requisition. (High)**
There is no per-source allocation quantity anywhere. `sourcing_model` is a single `VARCHAR(24)` on the requisition driving **cost arithmetic only** (`lib/recruit.php:26-31,251-274`). Worse, accepting one candidate sets `status='HIRED'` on the whole requisition regardless of `quantity` (`lib/ops.php:5090-5093`), and the marketplace equivalent awards a single application via one `awarded_application_id` even when `positions > 1` (`lib/connect_market.php:57,318-330`). A requirement for 20 welding inspectors cannot be fulfilled as specified in the brief.

---

## How findings convert to work — the four-way rule

A Phase-0 finding is **not** automatic implementation scope. Every finding carries one of:

| Class | Meaning |
|---|---|
| **A · REQUIRED NOW** | Explicitly inside the authorised phase scope |
| **B · PROTECT** | Healthy functionality that must not be disturbed |
| **C · FUTURE / DEFERRED** | Known debt, documented, deliberately not implemented |
| **D · OBSERVATION** | Useful finding, no current implementation action |

**No developer or coding agent may implement a finding merely because it appears in this audit. Only authorised phase scope may be implemented.**

## Sequencing consequence

F1 is a **prerequisite**. Until entitlement is default-deny and Marketplace is a real module, no Recruitment work can be safely validated — because "a recruitment-only tenant" is not yet a state the platform can enforce. Phase 1 is therefore entitlement and module boundary, not recruitment features.

Authoritative decisions: `15-ARCHITECTURE-LOCK.md`. Detailed sequencing: `11-implementation-roadmap.md`.
