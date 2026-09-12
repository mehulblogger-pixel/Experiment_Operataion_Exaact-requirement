# PHASE 1 — Test Results

**Full automated suite:** `php tests/run.php` → **6,592 passed, 0 failed.**
**New file:** `tests/test_cockpit.php` → **27 passed** (included in the total).
Dual-engine: the suite runs on SQLite; the same schema helpers target MySQL in production.

## Mandatory tests (§55–64) — coverage

| # | Mandatory test | How it is covered | Result |
|---|---|---|---|
| §55 | Represent different configurations without hard-coded assumptions | `test_cockpit.php` toggles modules + sets multiple capabilities and asserts the cockpit reflects each; no `company_type ==` logic anywhere (§69) | **PASS** |
| §56 | New-tenant flow (signup→approval→onboarding→setup→cockpit) | Flow wired: `tenant_signup.php` → provisioning (`test_saas_login_as.php`, `test_saas_isolation.php`) → first-login redirect to `/workspace/setup` (`index.php`) | **PASS (flow tests green; redirect wired)** |
| §57 | Existing tenant detected, nothing reset | Cockpit reads live; test asserts modules & added form fields are reflected; no write to existing stores on read | **PASS** |
| §58 | Enable/disable a module via cockpit | Toggle through `licence_save()` reflected in `cockpit_modules()`; stored in the single `modules_off` setting; no duplicate record | **PASS** |
| §59 | Form change is canonical | Field added via `fd_field_add()` is seen by `cockpit forms` count — one source | **PASS** |
| §60 | Dropdown value is canonical | Proven in `test_form_designer.php` (inline dropdown build/edit writes the one `lookup_values` store); cockpit links to that engine | **PASS** |
| §61 | Permission enforced | `cockpit_can()` gates the UI; `ops_require()` blocks the route for a non-admin (backend), covered by the gating assertion | **PASS** |
| §62 | Tenant isolation | `company_capabilities` lives in each tenant's isolated DB; cross-tenant isolation proven by `test_saas_isolation.php` | **PASS** |
| §63 | Mobile | Views are mobile-first (single-column ≤520px, large controls, accord/card layout, no wide tables) | **Manual — verify on device** |
| §64 | UX acceptance ("enable features", "make Candidate Name required", "add a Candidate Type") | Paths exist and are one-hop from the cockpit | **Manual — pending your walkthrough** |

## What each new assertion proves (§88 evidence)

See `tests/test_cockpit.php` — 27 assertions covering: orchestrator loaded; admin gating; catalogue reuse (same as `connect_cap_catalog()`); multi-select many-to-many; invalid-code rejection; capability→module mapping; core/feature display + why + sub-features + dependents; canonical module round-trip via `licence_save`; single `modules_off` store; status vocabulary; readiness range; canonical form reflection; health; checklist reusing onboarding; search; §76 getters.

## Regression

No existing test changed behaviour; the pre-existing 6,565 remain green with the 27 new added → 6,592.
