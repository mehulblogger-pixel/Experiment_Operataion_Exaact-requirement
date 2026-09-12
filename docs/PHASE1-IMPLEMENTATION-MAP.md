# PHASE 1 — Implementation Map

**Phase:** 1 only — Company Setup Cockpit + Configuration Orchestrator.
**Rule:** reuse first, extend second, consolidate third, create only when necessary.
**Status of this document:** produced *before* any cockpit code is written (per §5). It is the contract the build follows.

---

## A. Canonical source for every configuration concept (§44)

There is exactly **one** authoritative engine per concept. The Cockpit orchestrates these; it never becomes a second source of truth.

| Concept | Canonical engine (file) | Key functions / tables | Cockpit's relationship |
|---|---|---|---|
| Module / feature on-off | `lib/licence.php` | `PRODUCT_MODULES`, `licence_summary()`, `licence_enabled()`, `licence_save()`, `PRODUCT_PACKAGES` | **Reads & calls** — toggling routes to `licence_save()` |
| Navigation / areas | `lib/areas.php` | `ops_area_def()`, `ops_area_has()`, `ops_area_tile_count()` | **Reads only** — reflects what a module enables |
| Terminology | `lib/terms.php` | `T/TP/Tl/TH`, `term_overrides()`, `term_save()`, `ops_terminology()` | **Links to** `/terminology`; reads current words |
| Masters / dropdowns | `lib/lookups.php` | `lookup_types`, `lookup_values`, `lk_*`, `ops_lookups`/`/lookup` | **Links to** `/lookup`; reads catalogue |
| Custom fields | `lib/lookups.php` | `custom_fields`, `custom_values`, `custom_fields_for()` | **Reads** for form status |
| Built-in form design | `lib/formdesign.php` | `fd_forms()`, `fd_overrides()`, `fd_custom_fields()` | **Links to** `/form-designer`; reads status |
| Custom forms | `lib/customforms.php` | `custom_forms`, `cforms_all()`, `cform_*` | **Reads** for form list |
| Roles & permissions | `lib/access.php` | `ORG_ROLES`, `can()`, `role_perms()`, `role_access` setting, `ua()` | **Links to** `/access`, `/users`; reads roles |
| Settings store | `lib/access.php` | `settings(skey,svalue)`, `setting_get/set()` | **Reads/writes** cockpit-owned keys only |
| Audit trail | `lib/idems.php` | `idems_log()` (sealed chain, `setting` entity); `setting_set()` auto-audits | **Reuses** — no new audit engine (§48, §85) |
| First-run setup | `lib/setup.php` | `ops_setup()`, `setup_needed()`, `setup_done()` | **Integrates** — cockpit is the post-setup home |
| Guided onboarding | `lib/onboarding.php` | `ops_welcome()`, `onboarding_steps()`, `onboarding_incomplete()` | **Consumes** its steps — no competing wizard (§36) |
| Public workspace signup | `lib/tenant_signup.php` | `tenant_signup_submit()`, `tenant_request_approve()` | Upstream of cockpit (unchanged) |
| Marketplace capabilities | `lib/connect_capability.php` | `cx_org_capabilities` (org_party, code, enabled) | **Vocabulary aligned**; see note ¶C |
| Billing / payment | `lib/billing.php` | Razorpay, `billing_*` | **Links to** `/subscription` (unchanged) |

---

## B. Component-by-component analysis

Legend for **Verdict**: KEEP (use as-is) · EXTEND (add to it) · REUSE (call from cockpit) · CONSOLIDATE (bring under cockpit) — no DELETE in Phase 1.

### 1. `setup.php`
- **Purpose:** browser DB config (Phase A) + first-run basics: admin password, company name, industry, FY, currency, privacy contact (Phase B).
- **Route:** `/setup`, `/setup-save`. Gated: `setup_needed() && is_master()` redirect at `index.php:1099`.
- **Tables:** `settings` (writes `setup_done`, company keys); `config.local.php` (DB creds).
- **Consumers:** `index.php` boot gate.
- **Reuse:** the first-run basics remain the entry gate.
- **Extension:** none to the file. The cockpit becomes the *ongoing* configuration home that setup hands off to.
- **Regression risk:** LOW — cockpit does not touch the setup gate.

### 2. `onboarding.php`
- **Purpose:** mode-aware "getting started" next-steps computed from real data.
- **Route:** `/welcome` (`ops_welcome`). Shown once/session while `onboarding_incomplete()` at `index.php:1138`.
- **Tables:** none of its own — reads users/requirements/etc.
- **Consumers:** `index.php` home redirect.
- **Reuse:** the cockpit **consumes `onboarding_steps()`** as its "Get started" strip — one set of steps, shown in a richer home.
- **Extension:** the first-login redirect target becomes the cockpit; `/welcome` is kept and redirected to it (no duplicate questions, §36).
- **Regression risk:** LOW — steps come from the same function; `/welcome` stays reachable.

### 3. `licence.php`
- **Purpose:** the module/feature engine (6 sellable modules, packages, per-company on/off).
- **Route:** `/settings` (Modules), `/licence`, `/product-package`.
- **Tables:** `settings` (`modules_off`, `product_package`), signed key via `licencekey.php`.
- **Consumers:** `can()`, `areas.php`, every gated screen.
- **Reuse:** module list from `licence_summary()`; toggles via `licence_save()`.
- **Extension:** add a small **declarative feature/dependency map** in the orchestrator that *reads* `PRODUCT_MODULES` (module → covered access-modules = its features). No change to licence.php storage.
- **Regression risk:** LOW.

### 4. `areas.php`
- **Purpose:** flat, module-gated navigation (the left rail + area homes).
- **Route:** area homes via `ops_area_home()` (`ops.php:3244`).
- **Tables:** none — computed from licence + permissions.
- **Consumers:** `layout_top.php`, route gating.
- **Reuse:** cockpit's Navigation view reads `ops_area_def()`/`ops_area_has()` to show what each enabled feature surfaces (§19).
- **Extension:** none.
- **Regression risk:** NONE (read-only).

### 5. `terms.php`
- **Purpose:** per-company terminology.
- **Route:** `/terminology`.
- **Tables:** `settings` (`term_overrides`).
- **Reuse:** cockpit Terminology card links to `/terminology` and previews current overrides (§27).
- **Regression risk:** NONE.

### 6. `lookups.php`
- **Purpose:** master lists + custom fields.
- **Route:** `/lookup`, `/masters`, `/custom-fields`.
- **Tables:** `lookup_types`, `lookup_values`, `custom_fields`, `custom_values`.
- **Reuse:** cockpit Masters card links contextually; capability **catalogue** is a lookup list (`business_activity`) built via the same engine (§10, §24).
- **Regression risk:** LOW.

### 7. `formdesign.php` + `customforms.php`
- **Purpose:** one-screen form builder (built-in) + no-code custom forms.
- **Route:** `/form-designer`, `/cforms`.
- **Tables:** `form_field_layout`, `custom_fields`, `custom_forms`, `custom_records`.
- **Reuse:** cockpit Forms hub lists forms with status and a Configure link to `/form-designer` (§21). No second builder (§22, §72).
- **Form-builder capability audit (§22):** supported today — add / delete / reorder / hide / required / change label / build dropdown / edit options / (paragraph, number, date, dropdown types). **Not yet supported** (documented for a later phase, not built now): duplicate field, change type of an existing field, explicit validation rules, conditional visibility, live preview. Recorded in `PHASE1-KNOWN-LIMITATIONS.md`.
- **Regression risk:** NONE (links only).

### 8. `access.php`
- **Purpose:** roles, permissions, scope, settings helpers.
- **Route:** `/access`, `/users`, `/role-workspaces`.
- **Tables:** `settings` (`role_access`), `users`.
- **Reuse:** cockpit Roles card links to `/access` / `/users`; reads `ORG_ROLES` + role presence for health checks (§25, §26).
- **Regression risk:** NONE (links + reads).

---

## C. What is genuinely new in Phase 1 (minimum, orchestration-only)

Per §45 "keep new tables focused on orchestration" and §67 "no orphaned settings":

| New artefact | Type | Why it is needed (not a duplicate) |
|---|---|---|
| `lib/setup_cockpit.php` | Orchestrator service (§74, §76) | The one new architectural capability: answers "what has this tenant enabled / configured / still needs", consuming the engines above. Contains no config storage. |
| `company_capabilities` table | Orchestration data (§45) | The tenant's multi-select "what we do" (§11 many-to-many). Distinct from `cx_org_capabilities`, which is per-marketplace-party and gates marketplace visibility; this is the tenant-level business profile. Vocabulary is aligned so the two never disagree. |
| Capability **catalogue** | **Reused** from `connect_cap_catalog()` | *Refined during build:* rather than seed a new `business_activity` master list, the cockpit reuses the existing grouped, module-mapped capability catalogue in `connect_capability.php` — an even stronger reuse (one catalogue, already extensible "additively; never remove"). No new master list is created. |
| `settings` keys `cockpit_*` | Config values | e.g. `cockpit_profile_done`, resume pointer. Auto-audited by `setting_set()`. |
| `views/ops/cockpit_*.php` | Views | Presentation only. |

Everything else is reads/links into existing engines.

---

## D. Regression-risk summary

| Area | Risk | Mitigation |
|---|---|---|
| First-login redirect | LOW | Keep `/welcome`; redirect it to cockpit; once-per-session guard preserved |
| Module toggling | LOW | Route strictly through `licence_save()`; a test asserts no duplicate module record |
| Existing admin routes | NONE | All kept working; cockpit links to them (§42, §78) |
| Tenant isolation | NONE new | New tables live inside each tenant's own isolated DB; a test asserts no cross-tenant read (§46, §62) |
| Existing tenants | NONE | Migration derives state from existing config; nothing reset (§39, §40) |

---

*This map changes no behaviour. It fixes the canonical sources and the minimal new surface before a line of cockpit code is written.*
