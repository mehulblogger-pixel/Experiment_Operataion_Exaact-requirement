# 13 — RISK REGISTER (ARCHITECTURE-LOCKED)
Covers deliverable **31**.

Severity: **C**ritical / **H**igh / **M**edium / **L**ow.
Class: **A** REQUIRED NOW · **B** PROTECT · **C** FUTURE/DEFERRED · **D** OBSERVATION.
Phase references use the locked structure (`11-implementation-roadmap.md`).

**Production database: MySQL/MariaDB. Existing automated regression harness: SQLite.**

| ID | Risk | Sev | Class | Evidence | Mitigation |
|---|---|---|---|---|---|
| R-1 | **Entitlement fails open** — absent ceiling ⇒ every module entitled. Tenants may be using unpaid modules **right now**. | **C** | A | `lib/licence.php:131,149` | **Phase 1** default-deny, preceded by a verified per-tenant entitlement backfill |
| R-2 | Switching to default-deny **locks out existing tenants** whose entitlement was never written. | **C** | A | same | **Phase 1 item 2** — backfill + verify *before* the flip; staged; reversible |
| R-3 | **Marketplace is unsellable** and defaults **ON** for every cloud tenant (65 tables outside the commercial model). | **C** | A | `marketplace_addon_on()` default `'1'`; `PRODUCT_MODULES` `lib/licence.php:29-50` | **Phase 1 item 3** |
| R-3b | Quality (31 tables) is likewise not a sellable module and is bundled inside Operations. | — | **D** | `accredited_pack_on()` | **NO ACTION.** Architecture lock §6–7: Operations + Quality = KEEP/PROTECT. Independent subscribability is a separate future decision. |
| R-4 | **Public careers route has zero entitlement checks** — a downgraded tenant keeps serving jobs and writing candidate records. | **H** | A | `lib/careers.php` — 0 references to `licence_enabled` | **Phase 1 item 7** |
| R-4b | Marketplace public front doors are setting-gated, not entitlement-gated. | **H** | A | `install_marketplace_enabled()` | **Phase 1 item 8** |
| R-5 | Schema is **forward-only, no rollback**, and `run_schema()` order is implicit. A bad migration is permanent. | **H** | B | `lib/db.php:416-582`; `inspector_day_status` declared twice with divergent DDL | Additive-only, idempotent, non-destructive rule; duplicate DDL reconciled in **Phase 2 item 7** |
| R-6 | **The automated harness runs SQLite while production runs MySQL/MariaDB**, so a green suite is not yet production evidence. | **H** | A | `tests/bootstrap.php` pins `DB_DRIVER=sqlite`; two live MySQL defects this session (`ON CONFLICT` 1064; `\|\|` → `"0"`) | **Phase 1 item 16**, then MySQL/MariaDB authoritative for every later phase |
| R-7 | **Recruitment CRUD lives inside `lib/ops.php`** (Operations). Every recruitment change carries Operations blast radius. | **H** | A | `lib/ops.php:4919-5172`; `candidates` referenced in 31 non-recruitment libs | Mandatory Operations regression + scenario S-1 on every phase; safe extraction in **Phase 2 item 8** |
| R-8 | Bare `is_master()` licence bypasses let an administrator reach unbought modules (163 sites: idems 59, ops 58, crm 23, areas 23). | **H** | A | `lib/ops.php:571` vs `is_master_of()` | **Phase 1 item 10** — targeted correction where it acts as a licence bypass, not a blanket sweep |
| R-9 | **First hire closes a multi-vacancy requisition** — live functional defect for staffing today. | **H** | A | `lib/ops.php:5090-5093` | **Phase 2 item 1** |
| R-10 | **One human = up to 7 records**; 5 person tables have no uniqueness rule at all. | **H** | A | `04-…` §1.4 | **Phase 6** — CONNECT, never MERGE |
| R-11 | Entitlement has **22 test assertions (0.3%)**, none end-to-end, though it is the hard security boundary. | **H** | A | `tests/test_entitlement_lock.php` | **Phase 1 items 15–16** |
| R-12 | **51 self-skip guards** convert a missing function into a passing no-op; a rename could silently green the entitlement file. | **M** | A | 38 files; 1 fired this run | **Phase 1** — strengthen alongside entitlement tests |
| R-13 | `tenants.php` holds **every tenant's database credentials in plaintext**, readable by any code in the app. | **M** | **C** | `lib/tenants.php:58-70` (file mode 0600) | Documented; out of programme scope; review at a future security phase |
| R-14 | Tenant resolution by session/Host selects an entire database; a resolution bug is a **total** data exposure. | **M** | A | `config.php:87-151` | **Phase 1** cross-tenant tests target resolution, not query scoping |
| R-15 | `lib/party.php` uses SQLite-dialect `\|\|` concatenation inside try/catch — **names degrade silently on the production MySQL/MariaDB engine**. | **M** | A | `lib/party.php:24,29` | **Phase 6**; currently masked globally by `PIPES_AS_CONCAT`, but the latent pattern remains |
| R-16 | Approval engine writes requisition statuses **not in `REQ_STATUS`**; masked today because the path is dead code. | **M** | A | `lib/recruit_approval.php:234-236` | **Phase 2 item 4**, before **Phase 3** activates the path |
| R-17 | **TAPI is not licence-gated** — any `dash.*` permission reaches it — so it could expose metrics of unentitled modules. | **M** | A | `tapi_dash.php:165-168` | **Phase 5** — gate each registered metric by module |
| R-18 | Two candidate stage systems coarsely synced; users see two progress vocabularies. | **M** | A | `lib/recruitpipe.php:390-400` | **Phase 2 items 2–3** |
| R-19 | Module deactivation is **not audited** — a customer dispute over lost access cannot be answered from the system. | **L** | A | no activation log found | **Phase 1 item 12** |
| R-20 | No test isolation — 444 files share one mutating database in one process; failures may be non-local. | **L** | **C** | `tests/run.php` | Deferred test hygiene |
| R-21 | **Scope creep** — the audit surfaced far more debt than the authorised phases. | **H** | A | this document set | The four-way class rule: only **A** items in an authorised phase may be built. Enforced by `15-ARCHITECTURE-LOCK.md` §17 |
| R-22 | UI regression on a protected asset (dashboard) while "simplifying". | **M** | B | brief §21 | REUSE / EXTEND / CONFIGURE only. **No dashboard rebuild is authorised.** |
| R-23 | An implementer treats a Phase-0 observation (e.g. Quality bundling, five audit trails) as a work item. | **H** | A | this register | Class column is binding; `15-ARCHITECTURE-LOCK.md` §17 lists explicit prohibitions |

## Top three to act on first
1. **R-1 + R-2 together** — default-deny, safely backfilled and verified first.
2. **R-6** — MySQL/MariaDB regression, or no later result is trustworthy.
3. **R-3** — make Marketplace sellable, or the commercial model cannot exist.

## Explicitly NOT risks to be "fixed" in this programme
R-3b (Quality bundling) · five audit trails · five billing shapes · four field/template stores · three criteria engines · Project/Engagement entity. All are **Class C/D**: documented, deliberately unscheduled.
