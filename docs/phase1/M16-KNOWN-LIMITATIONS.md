# Milestone 16 — Known Limitations

Marked honestly. Nothing untested is recorded as a pass.

---

## L1 — The MilesWeb / mPanel host was NOT reached

**Status: NOT TESTED. The single largest open item.**

No credentials, SSH key or FTP configuration exist in this environment. In their
place the validated build was deployed to a **live HTTP host built here** (real
web server, real HTTP, MariaDB 10.11), and M16's tests were run against it — which
closed M15's L3 transport-layer gap. But these remain unverified until someone
runs the runbook on the real host:

* mPanel deployment end to end
* that host's PHP build and extension set
* its MySQL/MariaDB version and account name-space grants (`account_%`)
* shared-host connection contention (the app has a retry path; it was never put under real contention)
* real TLS certificate, HTTP→HTTPS redirect, HSTS
* panel cron scheduling and the correct PHP interpreter path
* real domain and wildcard subdomain

`M16-REAL-HOST-DEPLOYMENT-CHECKLIST.md` is written so a non-developer can execute
exactly these and record the answers.

---

## L2 — E-mail was not tested

**Status: NOT TESTED.**

No SMTP server exists in this environment and no credentials were supplied. §19
requires safe UAT recipients and a real mail path; neither was available. Sending
one test e-mail is step 10 of the runbook.

---

## L3 — No load testing

**Status: DOCUMENTED LIMITATION (by instruction, §24).**

Response times on the live host were 0.07–0.32 s, with a 13.1 s first request
that builds the schema. No concurrent users, no production-sized tables, no
slow-query analysis. A separate exercise if throughput numbers are wanted.

---

## L4 — Mobile and accessibility are sanity checks only

**Status: DOCUMENTED LIMITATION.**

The app serves a correct viewport meta and responds to a phone user-agent. That
is all that was checked. **No WCAG or accessibility conformance is claimed.**

---

## L5 — First-run experience has two steps that surprise people

**Status: PASS with a note — behaviour is correct, expectations are not.**

1. A **Licence Agreement** must be accepted before any schema is built.
2. A brand-new workspace redirects **every** route to "Welcome — let's set up
   your system" until onboarding is finished.

Both are correct. Both briefly misled this UAT — the second read as "every module
is reachable" until the page titles were inspected. They are now in the runbook.
No code change; changing them would be a UX decision, not a defect fix.

---

## L6 — `marketplace-*` routes are not in the module-gate family table

**Status: DOCUMENTED — the demonstrated defect is fixed, the shape is recorded.**

The two bypasses M16 found (`/marketplace-escrow`, `/financial-control`) are
fixed at their own guards and regression-tested. Separately, `marketplace-*`
routes are not classified by `ops_module_family()`, so `ops_module_gate()` does
not deny them at the route layer — they rely on their own handler guards, which
now check entitlement correctly.

Adding them to the family table would add a second layer, but it is not a
like-for-like change: some `marketplace-*` routes are **platform-owner** screens
(`marketplace-plans` is Super-Admin only), so classifying the prefix wholesale
could deny the owner their own console. Recorded for architectural review rather
than changed inside a closure milestone.

---

## L7 — Carried forward, unchanged

**M13 L1** pre-M13 sessions bind at next sign-in · **M15 L2** `invoices.invoice_no`
column default `''` vs `NULL` (latent; the application inserts NULL explicitly) ·
**M14 L1** object-level coverage is high-value-first, not exhaustive.
