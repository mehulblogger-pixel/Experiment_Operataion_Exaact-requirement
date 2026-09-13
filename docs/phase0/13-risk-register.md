# 13 — RISK REGISTER
Covers deliverable **31**. Severity: **C**ritical / **H**igh / **M**edium / **L**ow.

| ID | Risk | Sev | Evidence | Mitigation |
|---|---|---|---|---|
| R-1 | **Entitlement fails open** — empty ceiling ⇒ every module entitled. Tenants may be using unpaid modules **right now**. | **C** | `lib/licence.php:131,149` | Phase 1.1 default-deny, preceded by a guarded per-tenant backfill (1.1a) |
| R-2 | Flipping to default-deny **locks out existing tenants** whose ceiling was never written. | **C** | same | Backfill + verify **before** the flip; staged rollout; reversible switch |
| R-3 | **Marketplace (65 tables) and Quality (31) are unsellable** and Marketplace defaults **ON** for every cloud tenant. | **C** | `marketplace_addon_on()` default `'1'`; `PRODUCT_MODULES` `lib/licence.php:29-50` | Phase 1.2–1.4 |
| R-4 | **Public careers route has zero entitlement checks** — a downgraded tenant keeps serving jobs and writing candidates. | **H** | `lib/careers.php` — 0 references to `licence_enabled` | Phase 1.5 |
| R-5 | Schema is **forward-only with no rollback**, and `run_schema()` order is implicit. A bad migration is permanent. | **H** | `lib/db.php:416-582`; `inspector_day_status` declared twice with divergent DDL | Additive-only rule; reconcile duplicate DDL (3.6); migration review gate |
| R-6 | **The suite never exercises MySQL**, which is what production runs. Green ≠ safe. | **H** | `tests/bootstrap.php` pins `DB_DRIVER=sqlite`; two live MySQL defects this session | Phase 2.1 — gate all later phases on it |
| R-7 | **Recruitment CRUD lives in `lib/ops.php`** (Operations). Every recruitment change risks Operations. | **H** | `lib/ops.php:4919-5172`; `candidates` referenced in 31 non-recruitment libs | Mandatory Operations regression (S-1); Phase 3.7 extraction |
| R-8 | **163 bare `is_master()` licence bypasses** let an admin reach unbought modules. | **H** | idems 59, ops 58, crm 23, areas 23 | Phase 1.8; permanent review rule |
| R-9 | **First hire closes a multi-vacancy requisition** — functional defect for staffing today. | **H** | `lib/ops.php:5090-5093` | Phase 3.1 |
| R-10 | **One human = up to 7 records**; 5 person tables have no uniqueness at all. | **H** | `04-…` §1.4 | Phase 7; CONNECT never MERGE |
| R-11 | Entitlement has **22 test assertions (0.3%)** and none end-to-end, though it is the brief's hard security boundary. | **H** | `tests/test_entitlement_lock.php` | Phase 2.2–2.3 |
| R-12 | **51 self-skip guards** can turn a refactor into a silent green. | **M** | 38 files; 1 fired this run | Phase 2.4 |
| R-13 | `tenants.php` holds **every tenant's DB credentials in plaintext**, readable by any code in the app. | **M** | `lib/tenants.php:58-70` | Out of programme scope; record + review file permissions (0600 already set) |
| R-14 | Tenant resolution by session/Host selects an entire database; a resolution bug is a **total** data exposure. | **M** | `config.php:87-151` | Phase 2.3 cross-tenant tests target resolution |
| R-15 | `lib/party.php` uses SQLite-only `||` concatenation inside try/catch — **names degrade silently on MySQL**. | **M** | `lib/party.php:24,29` | Phase 7.4 (mitigated globally by `PIPES_AS_CONCAT`, but the latent pattern remains) |
| R-16 | Approval engine writes requisition statuses **not in `REQ_STATUS`**; currently masked as dead code. | **M** | `lib/recruit_approval.php:234-236` | Phase 3.3 before Phase 4 activates the path |
| R-17 | **TAPI is not licensable** — no `mod.*` permission, no licence gate — so it can expose metrics of unlicensed modules. | **M** | `tapi_dash.php:165-168` | Phase 6.4 gate each metric |
| R-18 | Two candidate stage systems coarsely synced; users see two progress vocabularies. | **M** | `lib/recruitpipe.php:390-400` | Phase 3.2 |
| R-19 | Module deactivation is **not audited** — a customer dispute over lost access cannot be answered. | **L** | no activation log found | Phase 1.10 |
| R-20 | No test isolation — 444 files share one mutating DB; failures may be non-local. | **L** | `tests/run.php` | Phase 2.5 |
| R-21 | Scope creep: the brief lists 6 simultaneous objectives across 8 modules. | **H** | — | Phase gates + explicit DEFERRED list (`11-…`) |
| R-22 | UI regression on a protected asset (dashboard) while "simplifying". | **M** | brief §21 | REUSE/EXTEND/CONFIGURE only; no redesign authorised by this audit |

## Top three to act on first
**R-1 + R-2 together** (default-deny, safely backfilled) · **R-6** (MySQL testing, or nothing else can be trusted) · **R-3** (make Marketplace and Quality sellable, or the commercial model in the brief cannot exist).
