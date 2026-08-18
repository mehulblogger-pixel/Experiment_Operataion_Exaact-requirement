# Inspection Ops — Licensing & Seats / Super-admin — Test & Documentation Report

> **Prompt 3 · Module MOD-SETTINGS (licensing).** Read from `lib/licence.php` (`licence_save`,
> `licence_disabled`, `licence_enabled`, `licence_owner`, `licence_is_core`, `licence_blocks`,
> `is_master_of`, `PRODUCT_MODULES`), `lib/licencekey.php` (`lk_state`, `lk_verify`,
> `lk_modules`, `lk_seat_block`, `lk_seats_used`, `lk_enforcing`, `lk_blocks_write`,
> `ops_licence`), `lib/superadmin.php` (`ops_super_admin`, `superadmin_seat_breakdown`),
> `lib/access.php` (`can` choke point, `is_master`), `lib/ops.php` (`ops_settings` modules form,
> `ops_module_gate`). Views `settings.php` (modules), `licence.php`, `super_admin.php`.

| | |
|---|---|
| **Module** | Licensing & Seats / Super-admin (MOD-LICENSING) · Area Admin |
| **Personas** | P-MASTER (modules, super-admin), P-ADMIN (`settings.manage`, licence) |
| **Risk weight** | **Medium-High** — controls which modules and how many seats a tenant has; a wrong toggle hides a module for everyone |
| **Verdict** | Complete-with-verification (confirm route-off safety, seat enforcement, key precedence) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Licensing decides which **product modules** (6: operations, admin [core], sales, reporting,
money, hr) an installation runs, and how many **seats** it may fill. Enforcement is central:
`can()` checks the licence **before** the master bypass, so switching a module off hides it
from **everyone including the person doing the switching** (`licence_blocks`), and
`ops_module_gate` refuses a switched-off module's routes with a distinct "not bought" vs "ask
admin" message. Precedence: a **signed licence key** (`lk_modules`) outranks the `MODULES_OFF`
env pin, which outranks the `modules_off` setting; **core modules are never switched off**.
The **licence key** engine (`lk_verify`, RSA-signed claims with a mandatory expiry) drives
states OPEN/TRIAL/VALID/GRACE/READONLY/INVALID/MISSING; READONLY blocks writes except an
allow-list. **Seat caps** (`lk_seat_block`) enforce on **new active-user creation only**. The
**super-admin** panel is a Master-only, read-mostly control surface (licence/seats/modules/
billing/tenants) that links to existing handlers.

Screens: `/settings` (modules form), `/licence`, `/super-admin` (alias `/control-panel`),
`/users` (seat check). Storage: `settings` KV (`modules_off`, `licence_key`, `licence_enforce`),
`issued_licences`.

---

## B. Screen-by-screen catalogue

**`/settings` modules form** — Master-only checkbox grid (`mod_on[key]`), core/pinned disabled,
warns "hides it from everybody incl. the switcher". **`/licence`** — paste/verify a signed key,
view state/seats. **`/super-admin`** — licence + seats + modules + billing + tenants + data
tools, seat-class costing (FULL/FIELD/PORTAL). **`/users`** — seat cap check on new-user create.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-LIC-form-001 | Modules form Master-only; refuses edits when `MODULES_OFF` env is set (pinned). |
| TC-LIC-form-002 | Core modules (admin) never written off; a core key listed in modules_off ignored. |
| TC-LIC-form-003 | Licence key: two dot-parts, base64url, `openssl_verify` SHA-256 vs pubkey, JSON claims, mandatory `exp`; each failure a plain-English error. |
| TC-LIC-form-004 | Seat-class prices clamped to sane defaults (negatives clamped). |

---

## D. Functions & logic  *(enforcement precedence — highest scrutiny)*

- **Central enforcement** (`can()` + `licence_blocks`): a module's `mod.x.*` perms return
  false for everyone (incl. master) when the module is off, checked **before** the master
  bypass. **TC-LIC-fn-001.**
- **Route-off** (`ops_module_gate`): a switched-off module's routes `ops_require(false)` with
  the right message; verify the guard is server-side, not just hidden nav. **TC-LIC-fn-002.**
- **Precedence**: signed key `lk_modules` > `MODULES_OFF` env > `modules_off` setting; core
  never off. **TC-LIC-fn-003.**
- **Seat cap** (`lk_seat_block`): single pool + optional field-seat split; 0 = unlimited;
  OPEN/TRIAL never block; **enforced only on new active-user creation, never on edit/
  reactivation** (GAP-LIC-001). **TC-LIC-fn-004** — a create over cap is refused; an edit that
  reactivates over cap is **not** caught.
- **Read-only mode** (`lk_blocks_write`): READONLY blocks POSTs except the allow-list
  (licence/billing/login/logout/change-password/verify). **TC-LIC-fn-005.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| licence OPEN/TRIAL → VALID/GRACE/READONLY/INVALID | key verify / expiry | signed claims + exp |
| enforcement off → sticky on | first key | `lk_mark_enforced` |
| module on ↔ off | modules form | master; core protected; env/key precedence |
| seats under → at cap | new-user create | `lk_seat_block` |

- **TC-LIC-life-001:** a switched-off module disappears from nav and its routes guard off for
  everyone including master.
- **TC-LIC-life-002:** an expired key drops to READONLY (writes blocked except allow-list).

---

## F. Roles, permissions & data scope

Modules form + super-admin + `/access`: **Master only**. Licence: `settings.manage`/master.
MASTER_ADMIN gets all perms/offices/SBUs but **licence gating still applies to master**.
`is_master_of()` prevents bare `is_master()` guards exposing unbought modules.

- TC-LIC-perm-001 (non-master module change) → refused.
- TC-LIC-perm-002 (super-admin without master) → refused.

---

## G. Settings

`modules_off` (CSV), `licence_key`, `licence_enforce`, `licence_pubkey`, seat pricing
(`billing_price_*`, `seat_field_roles`); env `MODULES_OFF`/`LICENCE_ENFORCE`/`LICENCE_KEY`/
`LICENCE_PUBKEY`/`SEAT_LIMIT`. **TC-LIC-set-001:** `MODULES_OFF` env pins the module set;
**TC-LIC-set-002:** `SEAT_LIMIT` env is **display-only, not enforced** (GAP).

---

## H. Cross-module integration

**Every module's availability** (enforced in `can()` + `ops_module_gate`), **Settings/Admin**
(the admin core module owns masters/users/settings/clients/vendors/reports/portal — MOD-14),
**Access** (`can()`/`ua()` — MOD-02), **Billing** (`billing_grant` self-service overlays seats/
state). Idempotency: module save is a no-op beyond the last value.

---

## I. Data integrity & audit

Seats = live `COUNT(users WHERE is_active=1)` with **no reservation/audit table** — counts can
race under concurrent creation (GAP). Two seat sources (signed key vs `billing_grant`)
reconciled only by max(). Enforcement is sticky (`licence_enforce=1` on first key). **TC-LIC-int-010:**
a switched-off module's routes are inaccessible; **TC-LIC-int-011:** a tampered/absent key
grants only core (a missing pubkey makes every key INVALID → read-only — support footgun).

---

## J. Reports & outputs

The modules state (`licence_summary`), the licence state/seats screen, the super-admin panel
(seat breakdown, billing, tenants). **TC-LIC-out-001:** the seat breakdown matches active
users by class.

---

## K. Negative, edge & resilience

A non-master toggling modules (refused); `MODULES_OFF` pinned (edits refused); a core module
in modules_off (ignored); a create over seat cap (refused); a **reactivation-via-edit over cap
(not caught)**; an expired key (READONLY); an absent pubkey (all keys INVALID → read-only); the
stale header comment claiming operations is core (it is switchable).

---

## L. TPIA operational suitability

Lets a vendor/tenant run exactly the modules they bought, with a signed-key + env-pin + setting
precedence that resists tampering, seat classes (full/field/portal) for TPIA staffing, and a
Master-only super-admin surface. The create-only seat enforcement and the two seat sources are
the items to tighten.

## M. Management usefulness

The super-admin panel gives a single control surface (licence, seats, modules, billing,
tenants); module licensing controls scope and cost. Confirm seat counts and the enforcement
precedence hold.

## N. UI/UX

Modules checkbox grid with clear warnings, licence paste/verify, super-admin control panel.
Terminology via `T*()`.

## O. Security

Modules/super-admin/access Master-only; licence gating applies even to master; module-off
guards routes server-side; key RSA-verified with mandatory expiry; READONLY write-blocks;
**seat enforcement create-only, seats race-prone** — tighten.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E licence states |
| 6 Validation | Y | §C key verify |
| 7 Negative | Y | §K |
| 8 Roles | **Priority** | §F master-only |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E |
| 12 Integration | **Priority** | §H every module |
| 13 Data integrity | **Priority** | §I seat race |
| 14 Audit | Partial | §I |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O enforcement |
| 21 Import | N-A | — |
| 22 Notifications | N-A | — |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | **Priority** | §D self |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | key expiry/grace |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-verification.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-LIC-001 | (verify) | **Seat caps enforce only on new-user creation** — an edit that reactivates a user, or a path not routed through user-new, bypasses the check; a tenant can sit over cap indefinitely. Enforce on activation too. |
| GAP-LIC-002 | (verify) | **Seats = live `COUNT(active users)` with no reservation table** — counts can race under concurrent creation; two seat sources (signed key vs `billing_grant`) are reconciled only by `max()`. Add a reservation/audit or a single source of truth. |
| GAP-LIC-003 | (verify) | Confirm a **switched-off module guards its routes server-side** (not just hidden nav) for every route, and that the stale header comment ("operations can never be switched off") is corrected — `operations` is switchable. `SEAT_LIMIT` env is display-only; either enforce or remove. |

---

## R. Traceability

RTM slice: `/settings` (modules), `/licence`, `/super-admin`, `/users` (seat check) × dims
1–29 → TC-LIC-* → results → DEF/GAP. **Verdict: Complete-with-verification** — route-off
enforcement, seat-cap coverage, and key precedence are the exit conditions.
