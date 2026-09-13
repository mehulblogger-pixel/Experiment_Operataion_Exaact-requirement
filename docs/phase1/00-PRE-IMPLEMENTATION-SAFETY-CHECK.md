# PHASE 1 — PRE-IMPLEMENTATION SAFETY CHECK

**Status: AWAITING REVIEW. No application code has been modified.**
Branch: `claude/testing-branch-setup-0gqe8n` (local `Testing`), working tree clean at `d94de60`.

Two blockers are identified in §11 and §13. **Neither can be resolved by guessing.**

---

## 1. Current entitlement mechanism

Entitlement is decided by **one** engine, `licence_disabled()` / `module_entitled()` (`lib/licence.php:72-152`), in strict precedence:

| Rank | Source | Where stored | Who writes it |
|---|---|---|---|
| 1 | Signed licence (on-premise) | licence key | vendor licence server |
| 2 | **Cloud entitlement ceiling** | tenant DB setting `saas_entitled_modules` | provisioning / super-admin / billing |
| 3 | Tenant preference | tenant DB setting `modules_off` (or `MODULES_OFF` env) | the customer's own Features screen |

`licence_enabled($k)` = not in the disabled list. `module_entitled($k)` = may it be switched on at all.

**The permission layer is already correctly ordered** — `can()` calls `licence_blocks()` *before* the master check (`lib/access.php:767-770`). This is right and will be preserved.

### 1.1 The defect (Phase-0 F1)
```php
licence_entitled_ceiling():  if (trim($csv) === '') return null;   // lib/licence.php:131
module_entitled():           if ($ceil === null) return true;      // lib/licence.php:149
```
**Blank ceiling ⇒ entitled to everything.**

### 1.2 ENTITLEMENT IS HELD IN TWO STORES — this is the crux of Phase 1

| Store | Field | Meaning |
|---|---|---|
| **Control DB** `saas_tenants` | `enabled_modules` (JSON), `plan`, `status` | **what the customer bought** |
| **Tenant DB** `settings` | `saas_entitled_modules` (CSV ceiling), `modules_off` | **what the tenant's own code enforces** |

They are synchronised only by an explicit push — `saas_push_to_tenant()` → `saas_apply_plan_modules()` / `saas_apply_modules_list()` (`lib/saas_tenants.php:330-345`), with an exec-free in-process fallback.

**If that push never ran, the control DB knows the truth and the tenant DB does not.** That is precisely the fail-open state.

### 1.3 A safe migration mechanism ALREADY EXISTS
`saas_entitlement_ensure()` (`lib/saas_tenants.php:351-366`) runs **at boot** (`lib/db.php:523`) and grandfathers a tenant onto a ceiling equal to what is currently switched on — without removing anything. It is idempotent and non-destructive.

**It is guarded by three conditions**, and one of them is the entire lockout risk:

```php
if (current_tenant() === '') return;                                  // tenants only  ✔ correct
if (setting_get('saas_provisioned','') !== '1') return;               // ← THE GAP
if (trim(setting_get('saas_entitled_modules','')) !== '') return;     // already set   ✔ correct
```

**A tenant whose `saas_provisioned` is not `'1'` never receives a ceiling.** Under default-deny it would lose every non-core module.

---

## 2. Existing tenant entitlement states

Determinable **from code**:

- Tenants are represented by `saas_tenants` rows in the control database; routing is resolved in `config.php:87-151`, which **rewrites the DB connection** — one database per tenant, no `tenant_id` column anywhere.
- `saas_tenants.status` ∈ `active | pending | suspended` — **tenant-level only. There is no per-module state today.**
- `enabled_modules` is a JSON array, defaulting to `'[]'`.
- `saas_tenant_set_plan()` populates `enabled_modules` from the plan tier; à-la-carte uses `saas_tenant_set_modules()`.
- Blank ceiling today means **ALLOW ALL** (§1.1).
- The control/platform install is *already* explicitly exempt: `licence_entitled_ceiling()` returns `null` when `current_tenant() === ''`.
- A self-hosted single business is governed by the **signed licence**, which outranks the ceiling and is unaffected.

**NOT determinable from here — the actual per-tenant values.** Those live in the production MySQL/MariaDB databases on the customer's server. See §11 / §13-A.

---

## 3. Affected modules

| Module | Key | Phase-1 action |
|---|---|---|
| Administration | `admin` (core) | none — always on |
| Operations | `operations` | **enforce only. PROTECTED — no redesign** |
| Reporting / IDEMS | `reporting` | enforce only |
| Recruitment / HR | `hr` | enforce only (incl. public careers routes) |
| Sales & CRM | `sales` | enforce only |
| Money | `money` | enforce only |
| **Marketplace / Connect** | *none today* | **make properly controllable** |
| Quality | *none today* | **NO CHANGE — KEEP/PROTECT** (lock §6) |

**§15 answer — HR vs Recruitment.** `hr` is defined as *"People & hiring — Requisitions, candidates, placement"* and covers exactly one access feature, `hiring` (`lib/licence.php:48`). **There is no separate HR/payroll module.** In this codebase `hr` **is** Recruitment. No new identifier will be invented.

---

## 4. Affected routes

Enforcement today: `ops_module_gate()` — a 446-entry route→feature map (`lib/ops.php:2425`) called from **one** chokepoint, `ops_dispatch()` (`lib/ops.php:2697`), invoked on the **last line** of the front controller (`index.php:1670`).

Gated route counts by feature (top): `idems` 64 · `jobs` 48 · `leads` 44 · `invoicing` 30 · `quotes` 28 · **`hiring` 27** · `settings` 24 · `audits` 19 · `ncr` 18 · `calls` 17 · `capa` 15.

**Unprotected paths:**
- 9 authenticated routes handled inline in `index.php` before dispatch (`clients`, `vendors`, `partner*`, `po`) — all belong to core `admin`, so impact is low; will be verified, not rewritten.
- All public routes (§6).

---

## 5. Affected APIs

**There is no tenant-facing API.** `api.php` (44 lines) is solely the vendor licence-server endpoint. There are no JSON/AJAX data endpoints outside the normal route dispatcher.

**Consequence:** the API bypass surface named in the spec is genuinely N/A, and route-level enforcement is close to complete once §4 and §6 gaps close. This will be stated as evidence, not assumed.

---

## 6. Affected public routes

Dispatched before `require_login()`:

`login · logout · forgot · reset · verify · verify-pdf · complaints-policy · buy · buy-verify · portal · vendor · pro · join · get-started · connect · connect/start · careers · p/<token>`

**Entitlement-relevant:**

| Route | Module | Current gate | Risk |
|---|---|---|---|
| `careers`, `careers/*` | `hr` | **setting `careers_enabled` only — `lib/careers.php` has ZERO entitlement references** | A tenant losing `hr` keeps publishing jobs and **writing `candidates` rows** via `careers_apply()` |
| `connect`, `connect/start`, `join` | Marketplace | `connect_enabled()` / `marketplace_addon_on()` — settings, **default `'1'` in cloud** | Marketplace reachable without entitlement |
| `pro`, `vendor`, `portal` | Marketplace / portals | own session + `mkt_can_use` | verify only |
| `buy`, `get-started`, `verify`, `p/…` | platform/public by design | — | no change |

---

## 7. Affected background jobs

`cron.php` (~32 guarded task blocks) and `cron_ads.php`. **Both contain zero entitlement references.** Tasks for unentitled modules can still run (reminders, escalations, syncs).

Phase-1 action: gate task blocks by module. **No task logic will be rewritten** — only wrapped.

---

## 8. Proposed database changes

**Preferred: NONE.** Every value Phase 1 needs already exists (`saas_tenants.enabled_modules`, `status`, `plan`; tenant `saas_entitled_modules`, `modules_off`, `saas_provisioned`).

Possible additive-only items, to be confirmed by §13-A output:

| Change | Type | Necessary? |
|---|---|---|
| Module activation/deactivation audit entries | **rows in existing `activities`** | yes (spec §12) — reuse, no new table |
| Per-module state (`suspended` / `admin-disabled`) | possibly `saas_tenants` additive column **or** encode in existing `enabled_modules` | **decide after §13-A** — prefer no schema change |

**No `DROP`, no destructive rename, no data rewrite. Schema is forward-only (no rollback exists), so anything added must be ignorable.**

---

## 9. Protected files

Touched **only** if unavoidable, with the smallest possible change + full regression:

`lib/licence.php` (the engine — the one file that *must* change) · `lib/access.php` · `lib/ops.php` · `lib/db.php` · `config.php` · `lib/saas_tenants.php` · `views/dashboard.php` · `lib/idems.php` · Marketplace engines · Money · Quality · TAPI · identity.

**Never touched in Phase 1:** Quality behaviour, Operations workflows, dashboard design, recruitment workflows, marketplace engines, TAPI, identity mechanisms.

---

## 10. Regression tests that will be run

**Baseline re-measured today, not quoted:**

```
php tests/run.php   →  6948 passed, 0 failed   ·  81 s
PHP 8.4.19  ·  444 test files  ·  engine: SQLite (harness)
```

Will be run after every change: full suite (6948) · Operations subset (128 files / 2010 assertions) · new entitlement suite (unit, negative, security, cross-tenant) · scenarios S-1 Operations-only, Recruitment-only, Marketplace, Mixed.

**Authoritative MySQL/MariaDB run: see §13-B — currently NOT POSSIBLE in this environment.**

---

## 11. Identified lockout risks

| # | Risk | Who is exposed | Mitigation |
|---|---|---|---|
| **L-1** | Tenant with **`saas_provisioned !== '1'`** has no ceiling; default-deny removes every non-core module | **Unknown — requires live inventory** | §13-A inventory, then explicit backfill before the flip |
| **L-2** | Tenant whose control-side `enabled_modules` is `'[]'` — neither store knows the truth | Unknown | **STOP and report per spec §8. Do not guess.** |
| L-3 | Tenant using a module it never formally bought (fail-open drift) | Unknown | Inventory reveals it; it is a **commercial** decision, not a technical one — will be reported, not silently revoked |
| L-4 | Self-hosted signed-licence install mistakenly treated as a tenant | Low — `lk_modules()` outranks the ceiling | assert in tests |
| L-5 | Control/platform install accidentally restricted | Low — already exempt at `lib/licence.php:129` | assert in tests |
| L-6 | Marketplace becoming a module switches it **off** for cloud tenants who have it on by default (`marketplace_addon` defaults `'1'`) | **Potentially every cloud tenant** | Backfill `marketplace` into the ceiling wherever the setting is currently on, *before* enforcing |
| L-7 | `is_master()` replacement removes genuine **platform-owner** authority | Admin/master users | **No mass replacement.** Categorise each site; change only true tenant-entitlement bypasses (spec §17) |

---

## 12. Exact implementation sequence

Each step ends in a verifiable artefact. **Nothing is enforced until Step 5.**

| Step | Action | Behaviour change? |
|---|---|---|
| **1** | **Read-only entitlement inventory tool** on the control install: for every tenant, report control-side `plan`/`enabled_modules`/`status` **and** tenant-side `saas_provisioned`/`saas_entitled_modules`/`modules_off`, plus the effective module list today vs. what default-deny would give. Output is a report — **it writes nothing.** | **NONE** |
| **2** | **You run it on the live server and return the output.** I analyse it and resolve L-1/L-2/L-3/L-6 against real data. | none |
| **3** | **Explicit entitlement backfill**, derived from the inventory — control-side truth preferred, current live state as fallback, ambiguous tenants **reported not guessed**. Idempotent, additive, reversible. | writes ceilings only; **no access removed** |
| **4** | **Verify**: re-run the inventory and confirm each tenant's effective modules are **identical** to before the backfill. | none |
| **5** | **Flip the ceiling to default-deny** (`lib/licence.php`), keeping the control install explicitly unlimited and the signed licence outranking. | **first behaviour change** |
| **6** | Marketplace becomes a real module key; plan slots extended. | yes |
| **7** | Close enforcement gaps: public careers route, marketplace front doors, cron task gating, the 9 inline `index.php` routes (verify). | yes |
| **8** | Module state model (ACTIVE / NOT SUBSCRIBED / SUSPENDED / DISABLED BY ADMIN) + precedence, reusing existing states; locked-but-visible UI. | yes |
| **9** | Targeted `is_master()` audit — categorise, fix only genuine entitlement bypasses, test each changed route. | yes |
| **10** | Activation/deactivation audit trail into existing `activities`. | additive |
| **11** | Entitlement regression suite: unit, negative, security, cross-tenant, deactivation→preservation→reactivation, and the four scenarios. | tests only |
| **12** | Full regression + Operations regression + MySQL/MariaDB run (§13-B) + `docs/phase1/` completion evidence. | none |

---

## 13. TWO BLOCKERS REQUIRING YOUR DECISION

### 13-A · I cannot see your live tenant entitlement state — and must not guess

Spec §7/§8 require inventorying existing tenant state **before** changing semantics. The values live in your production MySQL/MariaDB databases, which this environment cannot reach.

**Proposal:** Step 1 builds a **strictly read-only** inventory tool (no writes, no behaviour change, safe to run on production). You run it; you send me the output; I then design the backfill against real data.

**Without this, flipping default-deny is guessing — which §8 and §33 forbid.**

### 13-B · MySQL/MariaDB cannot be executed in this environment

Verified just now: **no MySQL/MariaDB server binary, and the Docker daemon is not running.** So I can write the MySQL-capable harness, but I **cannot produce the authoritative MySQL/MariaDB result here**.

Spec §30/§40 make that result mandatory for completion. Options:

| Option | What it gives |
|---|---|
| **A (recommended)** | I build the MySQL harness + a SQLite-dialect linter; **you run it** against a scratch MySQL/MariaDB on your hosting and return the output |
| **B** | You provide a throwaway (non-production) MySQL host/credentials for a scratch database |
| **C** | Proceed with SQLite only — **then Phase 1 cannot be declared complete** under §30/§40 |

**I will not report "tests pass" on SQLite alone, and I will not declare Phase 1 complete without a real MySQL/MariaDB run.**

---

## 14. Verdict of this safety check

**CONDITIONAL PROCEED.**

Steps 1–2 (read-only inventory) are safe to execute now and change nothing. **Steps 3–12 must not begin until the inventory output from your live server is available**, because the lockout risks L-1, L-2, L-3 and L-6 cannot be quantified without it.

**Awaiting your decision on 13-A and 13-B. No application code will be modified until you confirm.**
