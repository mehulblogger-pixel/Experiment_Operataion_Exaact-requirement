# 02 — SaaS ARCHITECTURE & MODULE ENTITLEMENT AUDIT
Covers deliverables **2, 3, 25**.

---

## 1. How multi-tenancy actually works

**Isolation model: one database per tenant.** Not a shared database with a `tenant_id` column.

- `config.php:87-91` — if `tenants.php` exists, cloud mode is on.
- Tenant selected from session (`config.php:103-107`, single-URL login) or subdomain (`config.php:123-135`).
- Resolution **rewrites the connection**: `$DB['driver']='sqlite'; $SQLITE=…` (`config.php:111-113,137-139`) or `$DB = array_merge($DB, $t['db'])` (`config.php:114-117,140-142`).
- `lib/db.php:14` `db()` consumes the already-resolved DSN. **There is no tenant-scoping layer beneath it.**
- Confirmed absence: no tenant table carries a `tenant_id` column.

**Consequence.** Cross-tenant leakage cannot occur through a forgotten `WHERE tenant_id = ?`. The isolation boundary is the DSN itself. This is a genuine strength and must be preserved.

**Residual risks (carried to the Risk Register):**
- R-1: wrong-DSN selection (a mis-set `$_SESSION['saas_tenant']` or spoofed `Host`) swaps the entire database at once.
- R-2: `tenants.php` is a plaintext PHP file holding **every tenant's database credentials**, readable by any code executing in the app.
- Correct mitigation already present: an unwired tenant sets `error='unconfigured'` and refuses, rather than silently falling back to the control database (`config.php:116-122`).

**Durable routing.** The control-database table `saas_tenants` carries `route_json`/`pending_json` (`lib/saas_tenants.php:47-74`) and the routing file rebuilds itself from it (`tenant_registry_heal()`, `lib/tenants.php:97`). Control-plane tables: `saas_tenants`, `saas_logins`, `tenant_requests`, `issued_licences`, `install_beats`. Note these are physically created in **every** database and are merely empty outside the control install (`lib/saas_tenants.php:18-20`) — a semantic, not physical, separation.

---

## 2. The entitlement engine

Three sources of truth, in strict precedence order (`lib/licence.php:72-118`):

1. **Signed licence** (on-premise). `lk_modules()` returns the purchased set; everything non-core outside it is forced off. Explicitly outranks the settings screen — *"the switch that sells the product cannot be flipped by the person who did not buy it."*
2. **Cloud entitlement ceiling** — the protected tenant setting `saas_entitled_modules`, written only by provisioning / super-admin / billing (`licence_entitled_ceiling()`, `lib/licence.php:122-140`).
3. **Tenant preference** — `modules_off` setting (or `MODULES_OFF` env var), the customer's own on/off choices *within* the ceiling.

`licence_enabled($key)` = not in the disabled list. `module_entitled($key)` = may this be switched on at all (`lib/licence.php:142-152`).

This layering is **sound and should be reused, not replaced.**

### 2.1 FINDING F1 — the ceiling FAILS OPEN (Critical)

```
licence_entitled_ceiling():  if trim($csv) === '' return null;      // lib/licence.php:131
module_entitled():           $ceil = …; if ($ceil === null) return true;   // lib/licence.php:149
```

An **empty** `saas_entitled_modules` means "no cloud limit" — so the tenant is entitled to **every** module. A workspace provisioned before the ceiling existed, or one where the write was missed, silently receives the whole ERP.

This is the root cause of the observed behaviour where a recruitment-agency workspace displayed Operations, Reporting, Quality and Money.

`saas_entitlement_ensure()` (`lib/saas_tenants.php:352+`) partially mitigates by grandfathering a provisioned tenant's ceiling to whatever is currently on — but that is a one-shot backfill, guarded by `saas_provisioned='1'`, not a default-deny.

**Required posture:** absent/unknown entitlement must mean **deny**, with an explicit, auditable "unlimited" marker reserved for the control install only.

### 2.2 FINDING F2 — Marketplace and Quality are not modules (Critical)

`PRODUCT_MODULES` (`lib/licence.php:29-50`) has exactly six entries:

| Key | Label | Core? |
|---|---|---|
| `admin` | Administration | **yes** |
| `operations` | Operations | no |
| `sales` | Sales & CRM | no |
| `reporting` | Inspection reporting (IDEMS) | no |
| `money` | Money | no |
| `hr` | People & hiring | no |

**Marketplace/Connect is absent.** It is gated by `marketplace_addon_on()`, a plain tenant setting whose default is **`'1'` when the install is cloud** — i.e. every cloud tenant gets Marketplace ON by default — further gated by `connect_enabled()`, another setting defaulting to `'1'`. Neither passes through the ceiling. **65 database tables** belong to this un-sellable capability.

**Quality is absent.** It is gated by `accredited_pack_on()` → accreditation-pack settings. **31 tables**.

**Commercial consequence.** From the brief: Customer D = *Marketplace + Operations + Reporting*; Customer E includes *Quality*. Neither is expressible today — the plan catalogue cannot grant or withhold them, billing cannot price them, and suspension cannot reach them.

### 2.3 FINDING F3 — enforcement surface is incomplete (High)

The gate is `ops_module_gate($route)` — a 446-entry route→feature map (`lib/ops.php:2425+`). It is **well built**: it refuses by owning module, additionally refuses accreditation-pack registers, and even blocks inspection configuration screens that nominally gate on core `settings`.

But it is invoked from **exactly one place**: `ops_dispatch()` (`lib/ops.php:2697`), which is called on the **last line** of the front controller (`index.php:1670`). Anything dispatched earlier never reaches it:

| Surface | Gated? | Evidence |
|---|---|---|
| `ops_dispatch` routes (435 routes) | **Yes** | `lib/ops.php:2697` |
| 9 authenticated routes handled inline in `index.php` (`clients`, `vendors`, `partner*`, `po`) | **No** | dispatched before `index.php:1670`; all belong to core `admin`, so impact is low |
| Public careers site + application intake | **No** | `lib/careers.php` has **zero** `licence_enabled`/`module_entitled` references; gated only by setting `careers_enabled` (`:34,154`) |
| Public marketplace / Connect / pro / vendor / portal front doors | Setting-gated only | `install_marketplace_enabled()`, `connect_enabled()` |
| `cron.php`, `cron_ads.php` (background jobs) | **No** | zero entitlement references in either file |
| `api.php` | N/A — **not a tenant data API** | It is solely the licence-server endpoint (`api.php:1-44`). No general REST surface exists. |

**The careers case is the sharpest.** A tenant that loses `hr` (downgrade, non-renewal, suspension) keeps serving its public jobs page and keeps writing `candidates` rows, because the page asks a *setting*, never the *entitlement*.

**Positive finding:** the absence of a general tenant REST/AJAX API materially reduces the bypass surface. The app is server-rendered forms, so route-level enforcement is close to complete once the gaps above are closed.

### 2.4 FINDING — no "locked / upgrade" UX state exists (Medium)

The brief requires four visible states (ACTIVE / AVAILABLE-NOT-SUBSCRIBED / SUSPENDED / DISABLED-BY-ADMIN). Today a module is either **on** or **invisible**. A search of all 394 views found no module-level "Upgrade required" or "Not subscribed" affordance — the only `Locked` strings relate to record locking (jobs, quotations), not entitlement.

`saas_tenants.status` supports `active|suspended` (`lib/saas_tenants.php:47-60`) and suspension is enforced at the door (`config.php:109-110,135-136` → `error='suspended'`), so **SUSPENDED exists at tenant level but not at module level**.

---

## 3. Plan catalogue (what can be sold today)

`superadmin_tiers()` (`lib/superadmin.php`):

| Plan | Modules | Gap vs brief |
|---|---|---|
| STARTER | admin, operations | — |
| RECRUITMENT | admin, hr | — |
| PRO | admin, operations, sales, hr, money | no reporting |
| ENTERPRISE | admin, operations, sales, hr, money, reporting | — |

Seat map: `['STARTER'=>2,'RECRUITMENT'=>3,'PRO'=>5,'ENTERPRISE'=>9]` (`lib/saas_tenants.php:27`).

**No plan can express Marketplace or Quality.** Customer D and Customer E from the brief are unsellable.

Per-tenant à-la-carte override exists — `saas_tenant_set_modules()` / `enabled_modules` — so the commercial model is *already* à-la-carte-capable at the tenant level; the plan tiers are presets over it. This is the right foundation to extend.

---

## 4. Capability → module auto-configuration

`cockpit_capability_modules()` (`lib/setup_cockpit.php`) maps chosen business activities (e.g. `TECH_RECRUITMENT` → `['hr']`, `TPIA` → `['operations','reporting']`) to modules, and `cockpit_apply_capability_modules()` switches modules on/off accordingly **within** entitlement. This is a genuine strength: the tenant describes *what it does*, and the workspace configures itself.

Constraint to preserve: it must never raise a tenant above its ceiling. Confirmed it writes via `licence_save()` and respects `module_entitled()`.

---

## 5. Module activation / deactivation behaviour (as it exists)

- **Activation later:** `saas_tenant_set_modules()` + `saas_push_to_tenant()` (`lib/saas_tenants.php:600+`) pushes plan/seats/modules into the tenant's own database, with an **exec-free in-process fallback** for shared hosting (`saas_push_to_tenant_inproc`). Existing data is untouched — activation is additive. **This satisfies brief §7.**
- **Paid floor:** `saas_paid_modules()` / `saas_paid_seat_floor()` ensure a provider-side sync can top a tenant up but **never silently revoke** something the customer paid for (`lib/saas_tenants.php:~320`). Good design; keep.
- **Deactivation:** switching a module off writes `modules_off` / narrows the ceiling. **No data is deleted.** Confirmed there is no `DROP TABLE` in the migrate path and no module-removal data purge anywhere. **This satisfies brief §8's "must not casually delete historical records."**
- **Re-activation:** because nothing was deleted, historical records return intact.
- **Gap:** deactivation is not *audited* — there is no record of when a module was withdrawn or by whom, so a dispute ("we lost access on the 4th") cannot be answered from the system.

---

## 6. Permission layer interaction

`can()` already refuses a permission belonging to an unlicensed module, and the code explicitly documents the historical bug it fixed: ~30 screens guarded themselves with `can('mod.x.view') || is_master()`, and *"that bare `is_master()` walks straight past the licence"* (`lib/licence.php:155-165`). The fix is in place. **Any new screen must not reintroduce `|| is_master()` as a licence bypass** — this becomes a review rule.

---

## 7. SaaS Entitlement Matrix (deliverable 25)

Target state. **E** = entitlement-controlled, **S** = setting-controlled (today), **—** = not applicable.

| Capability | Sellable today? | Gate today | Tables | Required Phase-1 state |
|---|---|---|---|---|
| Administration (core) | core | always on | 37 | core, always on |
| Operations | yes | E `operations` | 63 | E (unchanged) |
| Recruitment / People | yes | E `hr` | 22 | E + **public careers route** |
| Reporting / IDEMS | yes | E `reporting` | 41 | E (unchanged) |
| Sales & CRM | yes | E `sales` | 23 | E (unchanged) |
| Money | yes | E `money` | 20 | E (unchanged) |
| **Marketplace / Connect** | **no** | **S** `marketplace_addon` (defaults ON in cloud) | **65** | **promote to E `marketplace`** |
| **Quality / Accreditation** | **no** | **S** accreditation packs | **31** | **promote to E `quality`** |

Enforcement points that must ALL be true for a module to be considered entitlement-protected:

| Layer | Mechanism | Status |
|---|---|---|
| Menu / nav | `ops_area_has()`, `licence_enabled()` | present |
| Route (authenticated) | `ops_module_gate()` via `ops_dispatch` | present for 435 routes |
| Route (public) | — | **MISSING** (careers, connect front doors) |
| Route (inline `index.php`) | — | **MISSING** (9 core-admin routes; low risk) |
| Permission | `can()` + licence refusal | present |
| Background jobs | — | **MISSING** (`cron.php`) |
| Direct URL | via route gate | present where route gate is |
| Form POST / AJAX | via route gate | present where route gate is |
| API | no tenant API exists | N/A |
| Ceiling default | `null` ⇒ allow-all | **FAIL-OPEN — must become deny** |
