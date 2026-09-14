# Milestone 16 — Real-Host UAT

Every line is marked **PASS · FAIL · NOT TESTED · BLOCKED · N/A**. Nothing
untested is recorded as a pass (§34).

**Where it ran:** a live HTTP deployment of the release built here (real web
server, real HTTP, MariaDB 10.11). **Not** the MilesWeb/mPanel host — that was
unreachable and its items are marked NOT TESTED.

---

## 1. The walkthrough (§26)

| Step | Result | Note |
|---|---|---|
| Open the application | **PASS** | Licence Agreement shown first |
| Accept the licence | **PASS** | schema then builds — 306 tables, 13.1 s |
| Sign in | **PASS** | |
| First-login setup | **PASS** | M11 sends a new workspace to "Welcome — let's set up your system" and every route redirects there until it is done |
| Dashboard | **PASS** | 0.09 s |
| Navigation | **PASS** | Calls, Jobs, Quotes, Reports, Costings all render distinct screens |
| Operations — Work order register | **PASS** | |
| Operations — Job register | **PASS** | |
| Reporting — Dashboards | **PASS** | |
| Entitlement-denied module | **PASS** | HTTP **403** + lock screen |
| Direct-URL denial | **PASS** | see §3 |
| Sign out | **PASS** | |

**CONFUSING (recorded, not fixed):** a brand-new workspace redirects *every*
route to `/setup` until onboarding is completed. Correct M11 behaviour, and it
briefly read as "every module is reachable" during this UAT until the page titles
were actually inspected. Worth a line in the runbook so the first-day
administrator is not misled.

## 2. Entitlement — S-1 on a live workspace (§11, §13)

Workspace entitled to **Operations + Reporting only**, signed in as a **master**,
tested by **direct URL** (not menu visibility):

| Route | Module | Expected | Result |
|---|---|---|---|
| `/calls` | Operations | ALLOW | **PASS** — "Work order register" |
| `/jobs` | Operations | ALLOW | **PASS** — "Job register" |
| `/reports` | Reporting | ALLOW | **PASS** — "Dashboards" |
| `/recruitment` | Recruitment | DENY | **PASS** — HTTP 403 |
| `/requisitions` | Recruitment | DENY | **PASS** — HTTP 403 |
| `/quotes` | Sales | DENY | **PASS** — HTTP 403 |
| `/invoices` | Money | DENY | **PASS** — HTTP 403 |
| `/connect` | Marketplace | DENY | **PASS** — HTTP 404 (front route refuses) |
| `/marketplace-escrow` | Marketplace | DENY | **FAIL → FIXED** — see §5 |
| `/financial-control` | Marketplace | DENY | **FAIL → FIXED** — see §5 |

**A master did not gain a paid module by being a master** (§13) — that is M10
holding on a live host, and it is the reason the two failures stood out.

## 3. Object security — M14 on the live host (§10)

Branch A user, Branch B records. Direct URL, POST and download all exercised.

| | |
|---|---|
| quote · lead · opportunity · complaint · receipt · requisition · **costing** by direct URL | **PASS — all DENIED** |
| Branch B's lead document by download | **PASS — DENIED**, body never served |
| `POST /lead-delete` on Branch B's lead | **PASS — DENIED**, and the lead survived |
| Owning branch still reads all of them + downloads its file | **PASS — not over-scoped** |

## 4. Entitlement lifecycle (§12)

```
connect OFF → /marketplace-escrow denied
connect ON  → "Marketplace escrow" opens
connect OFF → denied again
```

**PASS.** No record was deleted by disabling the module.

## 5. The one real failure this UAT found

`/marketplace-escrow` and `/financial-control` served their Marketplace screens
to a **master on an Operations-only workspace**.

Cause: `ops_require(is_master() || connect_market_can(), …)`. `is_master()`
short-circuits the OR, so `connect_market_can()` — which *does* check the licence
— was never consulted. This is the M10 anti-pattern on two routes M9 added after
that audit ran, and `marketplace-*` routes are not in the module-gate's family
table either, so the route gate did not catch them.

**Fixed** by deleting the master branch, not reordering it (reordering is
cosmetic — the master branch wins wherever it sits). `connect_market_can()`
already returns true for a master when Connect is live and false for everyone
when it is not.

Re-tested on the live host: denied when unentitled, opens when entitled, denied
again when switched off. Permanent regression test added and mutation-verified.

## 6. Module smoke tests

| Module | Result |
|---|---|
| **Operations** (§14) — login, dashboard, navigation, registers, branch scope | **PASS** |
| **Reporting** (§15) — dashboards render; entitlement still gates it | **PASS** |
| **Money** — invoices gated by entitlement; invoice detail branch-scoped | **PASS** |
| **Sales** — quotes gated; quote/lead/opportunity branch-scoped | **PASS** |
| **Recruitment** (§16) — requisitions gated by HR entitlement; requisition detail branch-scoped | **PASS** (regression only — no new functionality) |
| **Marketplace** (§17) — Connect boundary, tenant isolation, desk routes | **PASS after the §5 fix** |
| **Quality** — inside Operations; complaint detail branch-scoped | **PASS** |
| **Project costing** — Option B branch scope | **PASS** |

## 7. Infrastructure

| | |
|---|---|
| Cron (§20) | **PASS** — exit 0, "23 checks, 0 failed" |
| Backup/restore (§21) | **PASS** — deleted records recovered |
| File storage (§18) — upload stored, download served to the owner, denied cross-branch, invalid reference clean | **PASS** |
| Session cookies / headers (§22) | **PASS** — `secure` under HTTPS, `HttpOnly`, `SameSite=Lax`, `nosniff`, `SAMEORIGIN` |
| Error pages (§23) | **PASS** — no SQL, trace, path or credential |
| Performance sanity (§24) | **PASS** — 0.07–0.32 s; 13.1 s first build |
| Mobile (§27) | **PASS (sanity only)** — viewport meta present; **no WCAG claim** |
| **Email (§19)** | **NOT TESTED** — no SMTP in this environment |
| **Real mPanel deployment** | **NOT TESTED** — host unreachable |
| **Real TLS / HTTP→HTTPS / HSTS** | **NOT TESTED** — host configuration |
| **Panel cron scheduling** | **NOT TESTED** — host configuration |
