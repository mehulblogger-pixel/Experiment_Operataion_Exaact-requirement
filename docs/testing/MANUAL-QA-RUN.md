# EXAACT — Master Manual QA Run (execution register)

> **Controlling directive:** the master manual testing prompt (2026-09-05).
> This file is the **live execution + defect register** for that programme — the one
> place stage results and defects are logged so continuity is never lost between
> sessions (the build sandbox is ephemeral; this file is committed each stage).
>
> **Reuses** the existing governance pack in this folder — do not duplicate it:
> - `inventory-v1.0.md` — locked master screen/function inventory
> - `governance-v1.0.md` — IDs, severities, RTM, defect lifecycle, entry/exit
> - `module-01..36-*.md` — per-module test docs
> - `manual-test-execution-guide.md` — concrete login→screen→values→expected→pass/fail
> - `prompt-4-*` (E2E) / `prompt-5-*` (gap/readiness)
>
> **Method:** manual, human-style UI interaction. In the build sandbox a clean
> instance is driven through a real browser (Environment-B-equivalent) with
> screenshots as evidence. Environment A (clean laptop install) and the true live
> cloud tenant are performed on the owner's machine against exact steps supplied here.

## Environments
| Env | What | Who runs it |
|-----|------|-------------|
| A | Clean local install (laptop-as-server, SQLite) | Owner (guided) |
| B | Cloud / SaaS — live `operations.mghaiapps.com` | Owner (guided) |
| B′ | Clean sandbox instance (this repo, throwaway SQLite) | QA agent (here) |

## Stage register
Status: ✅ PASS · ◑ PARTIAL · ✗ FAIL · ⛔ BLOCKED · ▷ NOT YET · ↻ RETEST

| Stage | Title | Status | Notes |
|------:|-------|:------:|-------|
| 0 | Environment & application inventory | ✅ | See STAGE-0 below. Inventory already locked in `inventory-v1.0.md`. |
| 1 | First customer experience (new signup → dashboard) | ✅ | 1.1 first access+login, 1.2 setup wizard, 1.3 staff-account model, 1.4 cloud onboarding — all PASS (B′; 1.1 also confirmed on B live). |
| 2 | Company configuration | ✅ | All config screens render; company profile (GSTIN→PAN/state auto-derive), office add, duplicate reject, invalid-GSTIN edge — all PASS (B′). |
| 3 | Masters & taxonomy | ✅ | Master screens render; add/edit/delete values, duplicate-value guard, dependent-list parent guard, new-list create + duplicate-key guard — all PASS (B′). |
| 4 | Users / roles / permissions | ✅ | Create staff w/ roles, weak-pwd guard, permission gate blocks inspector from admin screens — PASS. **D-002 fixed**: duplicate username was a 500, now a friendly message. |
| 5 | Universal technical passport | ✅ | Pro self-register, cert add w/ expiry status (VALID/EXPIRED), owner view, privacy-safe public passport, duplicate/weak-pwd guards — all PASS (B′). |
| 6 | Client & CRM | ✅ | Client CRUD + all guards, primary contact, portal invite→accept→login, staff/portal isolation — all PASS. **D-003 raised (P2)**: fresh DB auto-seeds 779 real MGH partners into every tenant. |
| 7 | Technical requirements | ▷ | |
| 8 | Marketplace (match → apply → shortlist → engage) | ▷ | |
| 9 | Freelance / supplier ecosystem | ▷ | |
| 10 | Operations (assign → deploy → execute) | ▷ | |
| 11 | Scheduling & conflict engine | ▷ | |
| 12 | Mobilisation & gate pass | ▷ | |
| 13 | Inspection execution | ▷ | |
| 14 | Reporting engine | ▷ | |
| 15 | Quality / ISO 17020 | ▷ | |
| 16 | Commercial / finance | ▷ | |
| 17 | Client portal | ▷ | |
| 18 | Dashboards & analytics (trace every KPI) | ▷ | |
| 19 | Notifications & communication | ▷ | |
| 20 | Documents & evidence | ▷ | |
| 21 | Audit / security / data integrity | ▷ | |
| 22 | Multi-capability companies | ▷ | |
| 23 | Negative / chaos testing | ▷ | |
| 24 | Complete end-to-end business test | ▷ | |
| 25 | Local vs cloud reconciliation | ▷ | |
| 26 | Final regression | ▷ | |
| 27 | Final acceptance (GREEN/AMBER/RED) | ▷ | |

## Defect register
| ID | Stage | Severity | Root cause | Screen/route | Expected | Actual | Status |
|----|-------|----------|-----------|--------------|----------|--------|--------|
| D-001 | 9 | P3 (cosmetic/label) | Portal shell header hard-coded to "Client portal" for any non-hire-first user; agency case not handled | `/portal` (`views/portal/top.php`) | An agency sees its portal named as an **Agency workspace** | Agency saw **"Client portal"** | ✅ FIXED — `$portalKind` now shows "Agency workspace" for `portal_agency_org()`; title + H1 use it. Crawl clean. |
| D-002 | 4 | P3 (error handling) | Duplicate username not pre-checked; INSERT hit the UNIQUE constraint and threw a raw exception | `/user-new`, `/user-edit` (`lib/ops.php` `ops_users`) | Friendly "username already taken" message, form re-shown | **HTTP 500** (SQLSTATE UNIQUE); no bad row created (data safe) | ✅ FIXED — pre-check before any team-member is created: clash → flash + re-render form (200). Verified: dup now 200 "already taken", `r.patel` count stays 1. Harness 5992/0. |
| D-003 | 6 | **P2 (multi-tenant confidentiality / onboarding)** | `auto_seed()` loads `data/seed_data.json` (**327 real client + 452 real vendor names**) into ANY fresh DB; not gated to the control install | `lib/db.php` `auto_seed()` (called from `run_schema`) | A brand-new customer/tenant workspace starts **empty** of partners | Every fresh DB (incl. a new tenant workspace) boots with **779 real MGH client/vendor names** | ⏳ **OPEN — awaiting owner decision.** MGH's own live install is unaffected (its `business_partners` is non-empty → seed early-returns). Proposed fix: skip `auto_seed()` for tenant workspaces & licence installs (start empty); keep it as an explicit "load sample data" admin action. Not changed yet — behaviour + real-data decision belongs to the owner. |

---

## STAGE 0 — Environment & application inventory

**TEST ID:** QA-0.1 · **STAGE:** 0 · **MODULE:** Platform · **USER:** — · **ENV:** B′ (sandbox)
**OBJECTIVE:** Confirm the application boots on a clean instance and inventory the surface to test.

**ACTUAL (sandbox, this repo @ branch `claude/branch-selection-it2ne0`):**
- Runtime: **PHP 8.4.19**, framework-less front controller (`index.php`); SQLite (local) / MySQL (cloud).
- **196** engine modules (`lib/*.php`); **325** view/screen files; **285** DB tables; **~234** ops route-cases + public routes; **386** test files (**~5,972** automated checks, currently 0 failing); 207-screen crawl passes.
- Boots clean from a throwaway SQLite DB via the seed scenarios; auto-migrations are additive (`CREATE TABLE IF NOT EXISTS` / `ensure_column`).
- Master inventory already **locked** at `docs/testing/inventory-v1.0.md` and validated against `master-inventory-baseline.md`.

**RECONCILIATION NOTE (finding, not a defect):** the existing governance pack is
**operations-strong** (36 modules: calls→jobs→IDEMS→invoicing→ISO registers). The
Connect **marketplace + fintech + reputation** surfaces built recently (org signup,
pro passport, matching verdicts pending, plans/credits/escrow/rules/ledger, ratings &
rating-integrity, the new landing + branded login) are **newer than that inventory**
and need coverage rows added as we reach Stages 5–9 and 16. Logged so Stage 3/8 extend
the inventory rather than assume it is complete.

**STATUS:** ✅ PASS (environment reachable & inventoried) — with the reconciliation note above.
**EVIDENCE:** counts above; prior whole-system runs (5,972/0, 207 screens clean).
**RETEST REQUIRED:** No.

*(Stage 0's local-install (Env A) and live-cloud (Env B) confirmations are owner-run —
supply STAGE 0.x steps and results and they'll be logged here.)*

---

## STAGE 1 — First customer experience

### QA-1.1 — First access + first sign-in (clean install)
**TEST ID:** QA-1.1 · **STAGE:** 1 · **MODULE:** Platform / Auth · **USER:** brand-new admin · **ENV:** B′ (sandbox, empty SQLite)
**OBJECTIVE:** From a completely empty database, confirm the front door renders, a brand-new customer can reach the staff sign-in, and the default admin can log in.

**STEPS / ACTUAL:**
| # | Action | Value | Expected | Actual (B′ sandbox) | Actual (B live) | ✓/✗ |
|--|--------|-------|----------|--------|--------|:--:|
| 1 | Open the site root `/` | — | Public front door loads | 302 → `/connect`; marketplace landing 200 (hero, role cards, "Free for everyone") | Front door loads (owner) | ✓ |
| 2 | Go to staff sign-in `/login` | — | Branded sign-in page | 200; "Operations Workspace", Username+Password, CSRF | Login box appears (owner) | ✓ |
| 3 | Sign in | admin / real pwd | Accepted, session started | 302 → `/` | Accepted (owner) | ✓ |
| 4 | Follow post-login landing | — | Lands in the app | 302 → **`/setup`** wizard (empty DB, no office) | → **dashboard** (existing data → wizard skipped) | ✓ |

**Env B/B′ reconciliation:** empty install (B′) → setup wizard; provisioned install with data (B live) → straight to dashboard. Difference is expected — confirms `setup_needed()` gating works both ways. **Both PASS.**

**FINDING (behaviour-as-designed, not a defect):** on a genuinely empty install the admin is routed to the **first-time setup wizard `/setup`**, not straight to the dashboard — because no office/business-partner is seeded yet (`setup_needed()` = true). This is correct onboarding, but it means "new signup → dashboard" for the very first customer is really "new signup → **setup wizard** → dashboard". Stage 1.2 will walk that wizard (set admin password, create first office) and confirm it then lands on the dashboard.

**STATUS:** ✅ PASS (front door + first login). Stage 1 overall ◑ PARTIAL until 1.2 (wizard walk-through) is done.
**EVIDENCE:** sandbox curl trace — `/`→302 `/connect` (200), `/login` 200 + CSRF, POST `/login` 302 `/`, authed `/`→302 `/setup` 200.
**RETEST REQUIRED:** No (re-run only if auth/front-route code changes).

### QA-1.3 — Staff account creation model (design confirmation)  ✅ PASS
**Owner observation (B live):** the staff sign-in page has **no "create account" option.**
**Verified in code — this is by design, not a defect.** The app has two doors with different signup models:
- **Staff / operations (`/login`)** — **no public self-signup** (intentional for an internal ops tool). Staff logins are created *inside* the app by the Master Admin or an office manager at **`/users` → Add user** (`lib/ops.php:7938` INSERT; form `views/ops/user_form.php`; gated by `is_master()` / office-manager). The first admin comes from the install itself (`admin` account, password set in the setup wizard). The login page states this: *"No account? Ask your office administrator to create one."*
- **Marketplace (`/connect`)** — **self-signup enabled**: freelancers and hiring companies register themselves via `/connect` → `/join` (`lib/connect_pro.php`, self-registered pool `cx_professionals`).

**No expected behaviour invented; no change made.** Full CRUD + role/permission coverage of `/users` is scheduled at **Stage 4 (Users / roles / permissions)** — cross-referenced here so it isn't missed.

### QA-1.4 — Cloud onboarding model for a NEW operations company (architecture confirmation)  ✅ PASS (finding logged)
**Owner question:** "If a company wants to use our **operations** platform on the cloud, how do they use it and create an account?"
**Verified in code (`lib/tenants.php`, `lib/cpanel.php`).** The platform is **multi-tenant: one codebase, many workspaces, one database each.** Each operations customer = a **workspace** on its own subdomain (e.g. `acme.operations.mghaiapps.com`) with its **own database, own admin, own settings** — tenants are fully isolated (`current_tenant()`; comment: "one tenant can never see another's records").

**Three distinct "company/account" doors — do not conflate:**
| Who | Wants to… | Door | Self-serve today? | Creates |
|---|---|---|---|---|
| **Operations customer** | Run their inspection office on the platform | Workspace provisioning at **`/tenants`** (Master Admin, base domain only; `can_manage_tenants()`) | ❌ **No public signup** — operator-provisioned | A tenant workspace (subdomain + DB + admin) |
| **Marketplace hiring company** | Hire freelancers from the shared pool | **`/connect` → `/join?type=COMPANY`** | ✅ Yes | A marketplace hiring client (`market.post`) — **not** an ops workspace |
| **Freelancer** | Get freelance work | **`/connect`** | ✅ Yes | Self-registered pro (`cx_professionals`) |

**How a new operations company is onboarded today (operator-assisted):** Master Admin opens **`/tenants`** on the main site → either (a) **automatic** if a cPanel API token is set (`cpanel_provision_workspace()` creates DB + DB-user + subdomain), or (b) **assisted/manual** (default): operator creates the MySQL DB + subdomain in cPanel by hand, then registers them on `/tenants`; the app builds the schema on first visit and the workspace gets its own `admin`. The customer then adds their staff at `/users`.

**FINDING (DESIGN GAP for self-serve scale — not a defect):** the multi-tenant machinery **works**, but onboarding was **sales-assisted, not instant self-serve** — there was **no public "Start your workspace" page** for the *operations* side that captures a company and queues provisioning.

**RESOLUTION (built this session — `lib/tenant_signup.php`):** public **`/get-started`** page (off by default) where a new inspection company applies → request lands **PENDING** in the Super-Admin Workspaces panel (`/tenants`) → **Approve** provisions the workspace (auto via cPanel API, or assisted two-click). Lifecycle **PENDING→APPROVED→PROVISIONED / REJECTED** (docs updated same commit); approval reuses `can_manage_tenants()` — **no new permission**. Additive `tenant_requests` table; nothing existing touched. 18 tests, full harness 5990/0, all screens render.

### QA-1.2 — First-run setup wizard → dashboard  ✅ PASS
**TEST ID:** QA-1.2 · **STAGE:** 1 · **MODULE:** Platform / Setup · **USER:** brand-new admin · **ENV:** B′ (sandbox, empty SQLite)
**OBJECTIVE:** Walk the first-time wizard end to end and confirm it completes, changes the admin password, and lands on the live dashboard without looping back to `/setup`.

| # | Action | Value | Expected | Actual | ✓/✗ |
|--|--------|-------|----------|--------|:--:|
| 1 | Sign in as default admin | `admin` / `admin12345` | Accepted | 302 → `/` | ✓ |
| 2 | Authed `/` | — | Routes to the wizard | 302 → `/setup` | ✓ |
| 3 | `/setup` renders | — | Wizard form with all fields | 200; fields present: admin_pass, app_name, industry, fy_start_month, currency_symbol, date_format, grievance_name, grievance_email, _csrf | ✓ |
| 4 | Submit wizard | new pwd + company "Acme Inspection Ops" + industry=inspection + FY/currency/date + DPDP contact | Saved, setup marked done | 302 → `/` | ✓ |
| 5 | Authed `/` again | — | **Dashboard**, not `/setup` | 200 → `/welcome`, title now "Acme Inspection Ops"; **no loop back to `/setup`** | ✓ |
| 6 | Re-login with **new** password | `admin` / `Acme#Secure2026` | Accepted | 302 → `/` | ✓ |
| 7 | Login with **old** default password | `admin` / `admin12345` | Rejected | 200, error "Invalid…", no session | ✓ |

**FINDING (behaviour-as-designed, not a defect):** the wizard collects **company name, industry, financial-year start, currency, date format, DPDP grievance contact, and the admin password** — it does **not** ask the admin to create an office. Offices are auto-seeded on first boot and edited later under Settings. (Earlier plan text said "create first office"; the real design is lighter — corrected here.)

**SECURITY (good):** setting a new admin password in the wizard **immediately retires the config default** (`admin12345` no longer works) and is not reverted by `admin_sync_from_config()` on the next request.

**STATUS:** ✅ PASS. **RETEST REQUIRED:** No (unless the setup route or admin-sync changes).

---

**Stage 1 verdict: ✅ PASS.** First access, first login, setup wizard, staff-account model, and the cloud onboarding model (with the new public workspace-signup build) are all confirmed. Ready for Stage 2 (Company configuration).

---

## STAGE 2 — Company configuration

**ENV:** B′ (sandbox, fresh install past the setup wizard) · **USER:** admin (Master)

### QA-2.1 — Every configuration screen renders
| Screen | Route | Result |
|--------|-------|:------:|
| Company profile | `/company-profile` | ✅ 200 |
| Settings | `/settings` | ✅ 200 |
| Org chart | `/hierarchy?tab=chart` | ✅ 200 |
| Offices | `/hierarchy?tab=offices` | ✅ 200 |
| People | `/hierarchy?tab=people` | ✅ 200 |
| Role access (permissions) | `/access` | ✅ 200 |
| Licence | `/licence` | ✅ 200 |
| Masters | `/masters` | ✅ 200 |
| Lookups | `/lookups` | ✅ 200 |

### QA-2.2 — Company profile save + GSTIN intelligence  ✅ PASS
Saved legal name, brand, address, **GSTIN `24ABCDE1234F1Z5`**, email, phone. On reload:
GSTIN persisted; **PAN auto-derived** to `ABCDE1234F`; **state auto-derived** to 24 (Gujarat) from the GSTIN's first two digits — so header and GST split can never disagree. ✅

### QA-2.3 — Invalid GSTIN edge  ✅ PASS
Saving GSTIN `NOTAGSTIN` shows the warning *"does not look valid…"* **but still saves the rest** (email updated) — graceful, no data loss, no hard block. ✅ (matches design: warn, don't reject.)

### QA-2.4 — Add an office  ✅ PASS
`do=office-save` "Vadodara Branch" (code VAD, city Vadodara, BRANCH) → 302, appears in the Offices list. ✅

### QA-2.5 — Duplicate office name rejected  ✅ PASS (negative)
Re-adding "Vadodara Branch" → rejected: *"…already exists — names must be unique."* No duplicate created. ✅

**Stage 2 verdict: ✅ PASS.** Company profile, GSTIN-driven PAN/state derivation, office CRUD + uniqueness guard, and all nine config screens confirmed. No defects. Ready for Stage 3 (Masters & taxonomy).

---

## STAGE 3 — Masters & taxonomy

**ENV:** B′ (sandbox, fresh install past setup) · **USER:** admin (Master) · Engine: `lib/lookups.php` (typed `lookup_types` + `lookup_values`; `/lookups` list, `/lookup?key=` values).

### QA-3.1 — Master screens render  ✅
`/lookups` (all lists), `/masters`, `/lookup?key=inspection_type` (38 rows), `/lookup?key=trade`, `/lookup?key=skill` (dependent list, 64 rows), `/custom-fields?entity=call` — all 200. *(Note: `/masters` shows 0 rows on a fresh install — it lists admin-defined operational master tables, none exist yet; the taxonomy itself lives under `/lookups`. Behaviour-as-designed, not a defect.)*

### QA-3.2 — Add a value  ✅ PASS
Added "Pre-Shipment Inspection" (code PSI) to `inspection_type` → appears in the list.

### QA-3.3 — Duplicate value rejected  ✅ PASS (negative)
Re-adding the same label → *"…is already on this list."* No duplicate. (Dedup is scoped per parent, so the same label under a different parent is still allowed — correct for dependent lists.)

### QA-3.4 — Dependent-list parent guard  ✅ PASS (edge)
Adding a `skill` value with **no parent trade** → blocked: *"Pick which Trade / discipline this belongs under."* Prevents orphan dependent values.

### QA-3.5 — Create a new master list  ✅ PASS
Created "Weld Process" (key auto-derived) → appears in the lists table.

### QA-3.6 — Duplicate list key rejected  ✅ PASS (negative)
Re-creating "Weld Process" → *"A list with that key already exists."*

### QA-3.7 — Delete a value  ✅ PASS
Deleting the PSI value (by its exact row id) → removed. Delete is guarded by `type_id` (a mismatched id silently no-ops rather than deleting across lists — good defensive behaviour; the first test attempt hit this and was a test-harness id-extraction error, not an app fault).

**Stage 3 verdict: ✅ PASS.** Typed master lists, value CRUD, duplicate + dependent-parent guards, and custom-list creation all confirmed. No defects. Ready for Stage 4 (Users / roles / permissions).

---

## STAGE 4 — Users / roles / permissions

**ENV:** B′ (sandbox) · **USER:** admin (Master) creates staff; then re-tested as the created **inspector**.

### QA-4.1 — Users screens render  ✅
`/users` (list) and `/user-new` (create form) → 200.

### QA-4.2 — Create staff with roles  ✅ PASS
Created **INSPECTOR** (`r.patel`) and **COORDINATOR** (`s.shah`) — each auto-linked to a new team-member from first/last name, assigned to the head office. Both appear in `/users`. Seats unlimited on a fresh (OPEN-state) install, so no seat block. ✅

### QA-4.3 — Weak-password guard  ✅ PASS (negative)
Creating a user with password `123` → rejected ("…at least…"); the account is **not** created. Admin-set passwords face the same strength rule as self-chosen ones. ✅

### QA-4.4 — Duplicate username → **D-002 (fixed this session)**  ↻→✅
Creating a second `r.patel` originally threw **HTTP 500** (UNIQUE constraint, unhandled). Data was safe (no duplicate row), but the UX was a raw error page. **Fixed:** a pre-check now runs *before* any team-member is auto-created — a clash shows *"The username 'r.patel' is already taken — choose a different one."* and re-renders the form (200). Re-verified: dup → 200 with the message, `r.patel` count stays 1, no orphan team-member. ✅

### QA-4.5 — Permission gate enforces the matrix  ✅ PASS (the key test)
Signed in as the new **inspector** (valid session: GET `/` → 200), then hit admin-only screens:
| Inspector → | Result |
|---|---|
| `/users` | ✅ blocked → redirect to `/` |
| `/user-new` | ✅ blocked → `/` |
| `/access` (role editor) | ✅ blocked → `/` |
| `/company-profile` | ✅ blocked → `/` |
| `/lookups` | ✅ blocked → `/` |
The permission matrix genuinely gates the UI — a limited role cannot reach administration, even by typing the URL directly. ✅

**Stage 4 verdict: ✅ PASS** (1 defect found and fixed: D-002). Staff creation, role assignment, password strength, and — most importantly — **real permission enforcement** all confirmed. Ready for Stage 5 (Universal technical passport).

---

## STAGE 5 — Universal technical passport

**ENV:** B′ (sandbox) · Marketplace/pro portal on by default (`connect_enabled`=1). Engine: `lib/connect_pro.php`, `lib/connect_credentials.php`, `lib/connect_passport.php`.

### QA-5.1 — Public pro pages render  ✅
`/pro/login` and `/pro/register` → 200 (no login).

### QA-5.2 — Register a professional  ✅ PASS
Registered "Ramesh Patel" (email, ≥8-char password, mobile) → 302 to `/pro/profile`, session set, an unguessable `passport_token` minted.

### QA-5.3 — Pro dashboard + passport link  ✅
`/pro` → 200; dashboard exposes the person's public passport URL.

### QA-5.4/5.5 — Certifications with expiry status  ✅ PASS
Added two certs (via `action=cert_save`):
| Cert | Expiry | Computed status |
|------|--------|-----------------|
| CSWIP 3.1 Welding Inspector (TWI) | 2030-12-31 | **VALID** ✅ |
| NDT Level II old (ASNT) | 2020-01-01 | **EXPIRED** ✅ |
Both saved (`cx_pro_certs`); the owner's `/pro/credentials` shows both, with an **"Expired"** badge on the lapsed one. Status logic: EXPIRED (past), EXPIRING (≤60 days), VALID.

### QA-5.6 — Public passport `/p/<token>` (privacy contract)  ✅ PASS
Public page (no login) → 200, shows the professional's **name** and verified/live status. **No leakage**: cert number, mobile, and password hash are all absent. By design the public passport publishes only **verified** credentials + live status + reputation — a self-registered pro's *unverified* marketplace certs stay private to the owner until verified. (Behaviour-as-designed, not a gap.)

### QA-5.7 — Negatives  ✅ PASS
Duplicate email → "already registered"; password `123` → "at least 8 characters". Neither account created.

**Notes (test-harness only, no app defect):** two false blanks in the first pass were my test using the wrong POST key (`act` instead of `action`) and an out-of-context status snippet; corrected and re-run clean.

**Stage 5 verdict: ✅ PASS.** Self-registration, structured certifications with correct expiry status, owner credential view, and a **privacy-safe public passport** all confirmed. No defects. Ready for Stage 6 (Client & CRM).

---

## STAGE 6 — Client & CRM

**ENV:** B′ (sandbox) · Client create at `/partner-new`; portal is opt-in (`portal_enabled`=0 by default).

### QA-6.1 — CRM screens render  ✅
`/clients`, `/vendors`, `/leads`, `/opportunities`, `/portal-users` → all 200.

### QA-6.2 — Create a client  ✅ PASS
Created "Reliance Testing Ltd" (client, 30-day terms, GSTIN `24ADUPL3517E2ZJ`, primary contact Anil Mehta). Result: lands on the partner detail page (`/partner?id=…`), **primary contact saved**, **PAN auto-derived** (`ADUPL3517E`) from the GSTIN. *(Note: `/clients` list is paginated over the auto-seeded partners — see D-003 — so a brand-new row isn't on page 1; the record exists and opens correctly.)*

### QA-6.3 — Client guards  ✅ PASS (negatives)
| Attempt | Result |
|---------|--------|
| No payment terms | ✅ "Payment terms are required…" |
| Invalid GSTIN | ✅ "GSTIN should be 15…" |
| No role ticked | ✅ "Select at least one role…" |
| Duplicate company | ✅ "…already exists…" (matched by name/GSTIN/PAN/TAN) |

### QA-6.4/6.5/6.6 — Client-portal invite → accept → login  ✅ PASS
Portal is off by default (all `/portal/*` correctly 404 until enabled — verified). After the admin switches it on:
- **Invite** (`/portal-users`): admin creates an invite — a token is minted, **admin never chooses a password** (good: the client sets their own). ✅
- **Accept** (`/portal/accept?t=…`): client sets their own password → 200. ✅
- **Login** (`/portal/login`): client signs in → 302 to `/portal`; dashboard shows **their own company** "Reliance Testing". ✅

### QA-6.7 — Staff ⇄ portal isolation  ✅ PASS (security)
A **portal-authenticated client** hitting staff routes `/users` and `/company-profile` → **302 to `/login`** — a client session cannot reach the staff application at all. Confirms the two audiences are fully walled off.

**Stage 6 verdict: ✅ PASS** (functionally). Client CRUD, every guard, the primary-contact carry, the passwordless-invite portal flow, and staff/portal isolation all confirmed. **One P2 finding (D-003)** — fresh-DB partner seeding — is raised for the owner's decision; it does not block the CRM functionality but must be resolved before onboarding real, separate customers. Ready for Stage 7 (Technical requirements).
