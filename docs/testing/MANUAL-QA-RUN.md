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
| 1 | First customer experience (new signup → dashboard) | ◑ | 1.1 first-access + first-login PASS (B′). 1.2 setup-wizard walk-through NOT YET. |
| 2 | Company configuration | ▷ | |
| 3 | Masters & taxonomy | ▷ | |
| 4 | Users / roles / permissions | ▷ | |
| 5 | Universal technical passport | ▷ | |
| 6 | Client & CRM | ▷ | |
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
| — | — | — | — | — | *(none logged yet)* | — | — |

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

### QA-1.2 — First-run setup wizard → dashboard  ▷ NOT YET
Set the admin password, create the first office, confirm the wizard completes and the next visit to `/` lands on the live dashboard (not back on `/setup`). To be run next.
