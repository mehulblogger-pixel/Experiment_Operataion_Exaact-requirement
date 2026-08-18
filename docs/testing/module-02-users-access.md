# Inspection Ops — Users & Access — Test & Documentation Report

> **Prompt 3 · Module MOD-USERS.** Traces to Inventory v1.0 / Governance v1.0. Read
> from `lib/ops.php` (`ops_users`, `ops_access`, the route gates), `lib/access.php`
> (`ua()`, `role_defaults`, `module_defaults`, `PERMISSIONS`, `ORG_ROLES`),
> `lib/security.php` (retire/unlock/2FA-reset), `lib/helpers.php` (login lockout,
> session), `lib/orgadmin.php` (org hierarchy + CSV import), `lib/mghsso.php` (SSO),
> and views `users.php`, `user_form.php`, `access.php`, `hierarchy.php`.

| | |
|---|---|
| **Module** | Users & Access (MOD-USERS) · Area Admin |
| **Personas** | P-MASTER, P-BM (branch mgr = `users.manage.branch`), P-COORD, P-INSP (negative) |
| **Risk weight** | **High** — this module is the security boundary of the whole app |
| **Verdict** | **Complete-with-verification** — the escalation clamps are present in code (§O, confirmed); confirm at runtime + close the audit/last-admin gaps |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Users & Access is the **security spine**: who can sign in, what role they hold, which
offices/business-units they are scoped to, and — through `ua()` — what every other
screen and action lets them do. Sub-areas:

- **Users** (`/users`, `/user-new`, `/user-edit`) — create/edit sign-in accounts:
  role, scope (`scope_offices`, `scope_sbus`, `home_office_id`), per-user permission
  overrides, reporting line, password (must-change), inspector link.
- **Roles & permissions** (`/access`) — Master-only editor of role→permission and
  role→module defaults (stored in settings `role_access` / `role_access_modules`).
- **Organisation** (`/hierarchy`) — the reporting tree, offices and people; Excel/CSV
  register import that builds the hierarchy.
- **Account security** — login lockout, password policy, 2FA/TOTP, session timeouts,
  retire/unlock/2FA-reset, SSO.

**The choke point `ua()`** (`lib/access.php`) resolves, once per request, the effective
role, permission set, office scope and BU scope, and the `master` flag. Every gate
(`is_master()`, `can()`, `ops_require()`, `scope_clause()`) reads from it. **This is the
single most important thing to get right.**

**Tables:** `users`, `user_prefs`, `login_attempts`, `sso_attempts` (+ reads `offices`,
`inspectors`).

---

## B. Screen-by-screen catalogue

**`/users`** — list (scoped: a branch manager sees own-office users). **`/user-new` /
`/user-edit`** — form with: username, first/last name, email, **role** (`ORG_ROLES`),
**is_superuser**, is_active, inspector link, **home_office_id**, **scope_offices**,
**scope_sbus**, **permissions** (CSV override), reports_to (name/position/email/id),
position_title, weekly_working_days, daily/half hours, **password** (+ must_change_pwd).
Password stored `password_hash(…, PASSWORD_DEFAULT)`; a new user with no password gets a
random hash + must-change.

**`/access`** — Master-only. Edit role→permission sets and role→module (view/edit)
grants; presets per role; stored in settings.

**`/hierarchy`** — org chart (offices ↔ people ↔ reporting line); `org-template`;
Excel/CSV import building the hierarchy (SUB-IMPORT).

**Account actions:** `/user-unlock` (clear login lockout), `/user-retire` (deactivate +
`security.php`), `/user-2fa-reset`, `/change-password`, `/two-factor`, `/sso`.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-USERS-form-001 | Username unique → duplicate refused. |
| TC-USERS-form-002 | Email format validated. |
| TC-USERS-form-003 | Password respects `pwd_min_len`; too short refused with the exact message. |
| TC-USERS-form-004 | New user with no password → random hash + `must_change_pwd=1`; first login forces change. |
| TC-USERS-form-005 | Scope fields accept `ALL` / office-id list / blank(=OWN); malformed ignored, not fatal. |
| TC-USERS-form-006 | `weekly_working_days` / hours within bounds. |

---

## D. Functions & logic

- **`ua()` resolution** (critical): master ⇒ all permissions; per-user `permissions`
  CSV override wins, else role defaults (`role_perms`); module access from
  `module_defaults` unless the user carries `mod.*` overrides; office scope from
  `scope_offices` (ALL / list / OWN=home office); BU scope from `scope_sbus`. **The
  result is cached for the request.** TC-USERS-ua-001…005 assert each branch with a
  crafted user row.
- **Login lockout:** after `LOGIN_MAX_TRIES` failures the account rests; `login_fail` /
  `login_locked_for` / `login_clear` on success. TC-USERS-sec-010.
- **Password change** blocks reuse of the current password; updates `pwd_changed_at`.
- **Password expiry:** `pwd_max_age_days` forces a change after N days. TC-USERS-sec-011.

---

## E. Status & lifecycle

User states: **active → inactive/retired** (`user-retire`), **locked → unlocked**
(`user-unlock`), **must-change-password**, **2FA enabled/disabled/reset**. Legal
transitions only by an authorised manager; a **retired** user cannot sign in; an
**erased** (GDPR) user has secrets blanked (`totp_secret`, `signature`, `recovery_codes`
cleared — `lib/compliance.php`).

---

## F. Roles, permissions & data scope  *(the heart of this module)*

Gates observed in code:
- `/users`, `/user-*` → `users.manage.branch OR users.manage.global`. A **branch**
  manager is scoped to `home_office_id`; a **global** manager (Master/…) is not.
- `/access` → **`is_master()` only** ("Only the Master Admin can edit role access").
- `/hierarchy` → users viewers.

| TC ID | Persona | Check → Expected |
|---|---|---|
| TC-USERS-perm-001 | P-INSP | GET `/users` and POST `/user-new` (crafted) → **refused**. |
| TC-USERS-perm-002 | P-COORD | manage users → **refused** (coordinator lacks `users.manage.*`). |
| TC-USERS-perm-003 | P-BM | `/users` lists **only own-office** users; editing another office's user by crafted `id` → **refused / scoped out**. |
| TC-USERS-perm-004 | P-BM | opening `/access` → **refused** (master-only). |
| TC-USERS-scope-001 | P-BM | user list query is scoped; no cross-office user returned in list or export. |

**Data scope is enforced through `ua()` + `scope_clause()`** — every list/report that a
scoped role sees must apply it. Cross-scope leakage anywhere is a **Blocker**.

---

## G. Settings

`session_idle_min` (5–1440), `session_max_hours` (1–168), `pwd_min_len`,
`pwd_max_age_days`, `pwd_default_cache`, `twofa_roles` (which roles must use 2FA),
`user_retire_days`, `role_access`, `role_access_modules`, `seat_field_roles`. Test each
boundary (min/max clamps) and on/off. TC-USERS-set-001…004.

---

## H. Cross-module integration

`ua()` gates **every** module — so a wrong permission here is felt everywhere. Inspector
link ties a user to an `inspectors` row (signatures, "My Jobs", named-approver). Reports
line drives approval fallback (`REPORTS_TO`) and the org chart. Seat/licence counts
(SUB-LIC) read active users. TC-USERS-int-001: change a user's role → their menu,
dashboard, and allowed actions change immediately (cache is per-request).

---

## I. Data integrity & audit trail

- Passwords are **hashed** (`password_hash`), never stored or logged in plaintext;
  `password_verify` on login. Confirm no password/secret is written to `email_log`,
  audit, or a debug path. TC-USERS-sec-020.
- User create/edit/retire and **role-access changes** must be audited (who/when).
  TC-USERS-audit-001; **GAP-USERS-001** if role-access edits are not logged.
- Login attempts recorded (`login_attempts`); SSO attempts (`sso_attempts`).

---

## J. Reports & outputs

`org_register_csv` export of the hierarchy; `person_data_export` (GDPR data request).
Confirm exports respect scope and never leak salary/secret fields to an unentitled
requester. TC-USERS-out-001.

---

## K. Negative, edge & resilience

- Import (`/hierarchy` CSV): a malformed row is rejected **with a reason**; a partial
  import does not corrupt the tree. TC-USERS-imp-001 (SUB-IMPORT).
- Deactivating the **only** Master Admin must be prevented (no lock-out of admin).
  TC-USERS-edge-001 — **verify (GAP-USERS-002).**
- Concurrent edit of the same user record → last-write or conflict, no corruption.

---

## L. TPIA operational suitability

Roles map to a real TPIA org (ED/SBU head/branch manager/coordinator/senior
inspector/inspector); scope models multi-office/multi-BU agencies; the **inspector →
approver** map and reporting line support the ISO 17020 authorisation/independence
expectations. Senior Inspector distinctly may vet/finalise.

## M. Management usefulness

Org chart + hierarchy give management the structure; seat usage informs licensing.
Confirm a manager can see who reports to whom and each person's scope.

## N. UI/UX & accessibility

Role/permission editor is dense — confirm presets make it usable and that a mis-set
permission is recoverable. Terminology on role labels flows via `ORG_ROLES` labels.

---

## O. Security review  *(release-gating — must pass)*

**Confirmed by code inspection (`ops_users`, `lib/ops.php`):** the app **clamps every
escalation vector**. A non-global (branch) manager (`$globalMgr = can('users.manage.
global')` = false; only MASTER_ADMIN/ADMIN hold `users.manage.global` by default) is
restricted to:
- **Assignable roles** = `['OPERATION_MANAGER','ASST_MANAGER','COORDINATOR','INSPECTOR']`
  only; a posted `role` outside this list falls back to `COORDINATOR` — **cannot mint a
  MASTER_ADMIN**.
- **`is_superuser`** is **derived** (`$role === 'MASTER_ADMIN' ? 1 : 0`), never taken
  from the posted body — a crafted `is_superuser=1` is ignored.
- **`permissions`** override is `array_intersect`-ed with a small whitelist
  (`dash.operations, dash.utilization, data.credit, ops.call.create, ops.job.allocate,
  ops.job.close, master.manage`) — **cannot inject** `settings.manage`/`idems.finalize`.
- **Scope** is forced to own home office (`scope_offices=''`); cannot self-widen.
- **Cross-office edit** refused up front: `if (!$globalMgr && user.home_office_id !==
  myOffice) → "You can only manage users in your own office."`

| TC ID | Title → Expected | Result (code inspection) |
|---|---|---|
| **TC-USERS-esc-001** | As P-BM, set `role=MASTER_ADMIN` (UI + crafted POST) → refused/clamped | **PASS** — role clamped to sub-roles |
| **TC-USERS-esc-002** | As P-BM, set `is_superuser=1` (crafted) → refused | **PASS** — superuser derived, not user-set |
| **TC-USERS-esc-003** | As P-BM, inject `permissions` beyond own rights → clamped | **PASS** — whitelist intersect |
| **TC-USERS-esc-004** | As P-BM, edit a user in another office → refused | **PASS** — own-office guard |

> **Runtime confirmation still required** (execute the four as live crafted requests to
> confirm no bypass path), plus the one caveat: if an admin **explicitly grants** a
> non-Master user a per-user `users.manage.global`, that user becomes a global manager
> and can then assign MASTER_ADMIN — that is an intentional admin decision, not a bug,
> but should be a **deliberate, audited** grant (see GAP-USERS-001).
| TC-USERS-sec-010 | Login lockout after `LOGIN_MAX_TRIES`; unlock clears it. | Critical |
| TC-USERS-sec-011 | Password expiry & must-change enforced. | Major |
| TC-USERS-sec-012 | 2FA/TOTP required for `twofa_roles`; reset requires authorisation. | Major |
| TC-USERS-sec-013 | Session idle & max-hours expiry force re-login. | Major |
| TC-USERS-sec-020 | No password/secret in logs/exports/audit. | Blocker |
| TC-USERS-sec-021 | CSRF token required on all user/access POSTs. | Critical |

> **These are the single most important checks in the programme — and they PASS by
> code inspection** (clamps above). Execute the four as live crafted requests to close
> them at runtime; if all hold, MOD-USERS' security dimension is satisfied. A regression
> that removed any clamp would be a Blocker/P1.

---

## P. Coverage scorecard (29 dimensions)

| # | Dim | Covered | TC / note |
|---|---|---|---|
| 1 UI | Y | §B |
| 2 Fields | Y | §C |
| 3 Buttons | Y | §B |
| 4 Functions | Y | §D (ua) |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles/Perms | Y (clamps confirmed) | §F/§O — esc-001..004 PASS by inspection; confirm at runtime |
| 9 Data scope | Y | §F/§O-004 own-office guard |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §L |
| 12 Integration | Y | §H |
| 13 Data integrity | Y | §I |
| 14 Audit/Immutability | Partial | GAP-USERS-001 |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX/a11y | Partial | §N |
| 19 Gap analysis | Y | §Q |
| 20 Security | Y (clamps confirmed) | §O — escalation vectors closed in code; runtime confirm |
| 21 Import | Partial | §K |
| 22 Notifications | Partial | invite/reset emails |
| 23 Offline | N-A | admin, online |
| 24 AI | N-A | — |
| 25 Licensing/Seats | Y | §H (seat count) |
| 26 Terminology | Y | role labels |
| 27 Time/FY | Y | pwd age, session, retire days |
| 28 Performance | Partial | user-list at volume |
| 29 Backup | N-A here | platform-level |

**Verdict:** **Complete-with-verification** — the security clamps are present and
correct in code; run the four escalation cases live to confirm, and close the audit and
last-admin gaps below.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-USERS-003 | **Resolved (verify at runtime)** | `ops_users` **does clamp** assignable role (to 4 sub-roles), derives `is_superuser`, whitelists the `permissions` override, and forces own-office scope for a non-global manager. Confirm live; keep as a standing regression check. |
| GAP-USERS-001 | Minor·P3 | Ensure **role-access changes** (`/access`) and user create/edit/retire are in the audit trail (password change already logs via `idems_log`; extend to role/permission/scope edits). |
| GAP-USERS-002 | Minor·P3 | Prevent deactivating/retiring the **last** active Master Admin (no admin lock-out). |
| GAP-USERS-004 | Minor·P3 | Confirm the **branch-manager user list** query is office-scoped (no cross-office users in list/export). |

---

## R. Traceability

RTM slice: `/users`, `/user-*`, `/access`, `/hierarchy` × dimensions 1–29 → TC-USERS-*
→ results → DEF/GAP. **Module verdict: Complete-with-verification** — the escalation
clamps are confirmed in code and are the app's strongest security control; execute the
four escalation cases live and close the three Minor gaps to sign **Complete**.
