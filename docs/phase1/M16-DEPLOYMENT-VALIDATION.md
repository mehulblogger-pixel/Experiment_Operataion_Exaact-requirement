# Milestone 16 — Deployment Validation

**Release under test:** `d3f5244` (M15) + the M16 changes below.

---

## 1. Honest statement of what was and was not tested

**The MilesWeb / mPanel host was NOT reached.** No credentials, no SSH key, no
FTP configuration exist in this environment, and `config.local.php` /
`tenants.php` are correctly absent. Every item below that depends on that
specific host is marked **NOT TESTED**, not PASS (§34).

What *was* done instead, at your direction: the validated build was deployed to a
**live HTTP host built here** — a real web server serving the real application
over real HTTP against MariaDB 10.11 — and M16's tests were run against it.
That is not the production host, but it is a genuine web deployment, and it
closes the gap M15 recorded as limitation **L3 ("no live HTTP host: transport
layer untested")**.

| | |
|---|---|
| Web server | PHP 8.4.19 built-in server, document root = the deployed build |
| Database | MariaDB 10.11.14, `utf8mb4` / `utf8mb4_unicode_ci` |
| Deployment method | `git archive` of the release, extracted like an upload |
| Workspaces | one control install + one hosted workspace, separate databases |

## 2. §3 — Release hygiene

```
branch : Testing -> claude/testing-branch-setup-0gqe8n
commit : d3f5244ba755a41bdf721c710899e4b64a2790f2
tree   : 0 uncommitted files at audit time
```

No test artefacts, no local database files, no `config.local.php` or
`tenants.php`, no `.log`/`.bak`/editor files, no `var_dump`/`print_r`/`die()`
debug code in the libraries or entry point, no forced `display_errors`.

## 3. §5 — Deployment sequence, as performed

```
upload (1,168 files) → open once → schema built → deploy-check → login → smoke test
```

| Step | Result |
|---|---|
| Application loads over HTTP | **PASS** — HTTP 200 on first request |
| **First-run licence gate** | **PASS** — the app shows a Licence Agreement and refuses to build a schema until it is accepted. *This is a first-deploy step the runbook must state; it surprised this deployment.* |
| Schema build via a real HTTP request | **PASS** — **306 tables in 13.1 s** |
| Database connection | **PASS** — correct database selected |
| Login | **PASS** |

**13 seconds** is the real first-page figure. A non-developer must be told not to
refresh or assume a hang.

## 4. §22 — HTTPS, session and cookie check (closes M15 L3)

Observed on the wire:

```
Set-Cookie: PHPSESSID=…; path=/; HttpOnly; SameSite=Lax           (plain HTTP)
Set-Cookie: PHPSESSID=…; path=/; secure; HttpOnly; SameSite=Lax   (X-Forwarded-Proto: https)
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Cache-Control: no-store, no-cache, must-revalidate
```

The `secure` flag is applied **conditionally and correctly** — present under
HTTPS (including behind a terminating proxy), absent on plain HTTP. `HttpOnly`,
`SameSite=Lax`, `nosniff` and `SAMEORIGIN` are always set, and
`session.use_strict_mode` is on.

**NOT TESTED:** real TLS certificate, HTTP→HTTPS redirect, HSTS — all host
configuration.

## 5. §7 — Fresh workspace provisioning

A hosted workspace (`s1.uat.local`) was created in the registry and opened over
HTTP. It provisioned **its own database — 306 tables** — and received no data
from the control install. **PASS**

## 6. §8 / §9 — Login, session and M13 on the live host

| Check | Result |
|---|---|
| Correct password | **PASS** (302 to the app) |
| Wrong password | **PASS** — "Invalid username or password", nothing more |
| Failed login leaves no tenant context | **PASS** — session is signed out |
| `/reset?w=<workspace>` cannot move a signed-in session | **PASS** |
| CSRF: POST with no token | **PASS** — refused, and the record survived |
| Error disclosure | **PASS** — see §8 below |

## 7. §10 — M14 object security on the live host

Signed in as a **Branch A** user, asking for **Branch B** records:

| Route | Method | Result |
|---|---|---|
| `/quote?id=1` | direct URL | **DENIED** |
| `/lead?id=1` | direct URL | **DENIED** |
| `/opportunity?id=1` | direct URL | **DENIED** |
| `/complaint?id=1` | direct URL | **DENIED** |
| `/receipt?id=1` | direct URL | **DENIED** |
| `/requisition?id=1` | direct URL | **DENIED** |
| `/project-costing?id=1` | direct URL | **DENIED** (new — M16 Option B) |
| `/lead-file?id=1` | **download** | **DENIED** — file body never served |
| `/lead-delete` | **POST** | **DENIED** — and the lead survived |

And the **owning** branch still reads every one of those records and downloads
its own document. Not over-scoped. **PASS**

## 8. §23 — Controlled failures

Unknown route, non-existent id, malformed id (`abc`), injection-shaped id
(`1 OR 1=1`), missing file — every response was checked for `SQLSTATE`,
`SELECT `, `Stack trace`, the filesystem path, `Fatal error`, `Warning:`, the
database name and the database password. **All clean.** **PASS**

## 9. §24 — Performance sanity (not a load test)

| | |
|---|---|
| First request (builds 306 tables) | 13.1 s |
| Login POST | 0.32 s |
| Dashboard | 0.09 s |
| Calls / Jobs / Quotes / Reports / Costings | 0.07 – 0.10 s |

No timeout, no fatal, no runaway query. **No load testing was done.**

## 10. §20 / §21 — Cron and backup on the host

Cron ran to completion, **exit 0**, including "Data integrity: 23 checks, 0
failed". Backup → delete every lead → restore → **the leads came back**. **PASS**

## 11. §19 / §27 — Mail and mobile

**Mail: NOT TESTED** — no SMTP server exists in this environment and no
credentials were supplied. Not a pass.

**Mobile:** the app serves `<meta name="viewport" content="width=device-width,
initial-scale=1">` and responds to a phone user-agent. A practical sanity check
only — **no accessibility or WCAG claim is made**.

## 12. Still NOT TESTED — requires the real host

Real mPanel deployment · that host's PHP build and extension set · its
MySQL/MariaDB version and account name-space grants · shared-host connection
contention · real TLS certificate and HTTP→HTTPS redirect · panel cron scheduling
· real SMTP · real domain and wildcard subdomain.

The runbook in `M16-REAL-HOST-DEPLOYMENT-CHECKLIST.md` is written so that you or
your host administrator can execute exactly these steps and record the results.
