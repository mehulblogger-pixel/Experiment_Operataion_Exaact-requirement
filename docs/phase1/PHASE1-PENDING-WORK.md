# Phase 1 — Pending Work (carried forward)

Phase 2 was begun by explicit instruction while these items were still open.
They are **not** closed, and none of them is recorded as passed.

---

## P1 — Real-host UAT evidence was never recorded

**Status: NOT TESTED.**

The M16 sign-off sheet was worked through against `operations.mghaiapps.com`, but
the run could not establish the live build, and the security stages were blocked
part-way. Recorded results: 24 pass, 9 not tested, 12 not reached, 1 real product
finding (P3 below).

The blocked rows were blocked by the sheet's own instructions being too
technical — "put the record's id in the address bar" without saying where to find
a record id. That is a defect in the checklist, not in the software, and the rows
need rewriting in plain steps before they can be re-run.

Everything those rows cover **was** verified on a live HTTP host here
(cross-branch by URL/POST/download, S-1 entitlement by direct URL as a master,
error disclosure, CSRF, cron, backup/restore). What is missing is the same
evidence **on MilesWeb**.

## P2 — Which build is live on MilesWeb is still unknown

**Status: UNRESOLVED.**

`deploy-check.php` reported build `f10654e`. That string is a label stamped into
the file when it is generated, not a reading of the uploaded code, so it proves
only that *that file* was old. The decisive figures — **Up to date / Stale /
Missing** — were never read back.

I asserted from the label alone that the live build was 29 commits behind. That
was a conclusion drawn from a label and it may well be wrong; it is withdrawn
until the counts are seen.

## P3 — Configuration and data incident on the live host

**Status: OPEN, and the most urgent item here.**

Credentials were kept in `config.local.sample.php`, which the application never
reads — `config.php` loads `config.local.php` and nothing else. Creating a real
`config.local.php` pointed the application at a **different, empty database**,
which is why the licence agreement reappeared, companies looked deleted and only
a single admin user remained: those are the symptoms of an empty database, not of
deleted data.

Company records live in MySQL databases (`<accountprefix>_<companykey>`), which
deleting files cannot touch. The routing file `tenants.php` is only a cache and
rebuilds itself from the control database on an ordinary page load
(`tenant_registry_heal()`, called at `index.php:698`).

**Outstanding:** confirm in phpMyAdmin which database holds the real data
(~306 tables, a populated `users` table, a `saas_tenants` row per company), then
put those exact credentials in `config.local.php`. Until that is done the live
site is pointed somewhere unintended.

The one genuinely destructive scenario — a company created in SQLite mode, whose
data is a single `tenant-<key>.sqlite` file in the web root — has not been ruled
out.

## P4 — E-mail has never been tested anywhere

**Status: NOT TESTED.** No SMTP server exists in the development environment and
no credentials were supplied. Password reset and notifications are unverified.

## P5 — Smaller items carried forward

* No load testing; mobile checked for usability only, no accessibility claim.
* `marketplace-*` routes are absent from the route gate's family table. The two
  demonstrated bypasses are fixed at their own guards; classifying the prefix
  wholesale could deny the platform owner their own console, so it is left for
  architectural review.
* Sessions predating M13 acquire their workspace binding at next sign-in.
* `invoices.invoice_no` defaults to `''` while its unique index expects `NULL`
  for drafts — latent only; the application inserts `NULL` explicitly.
* Object-level scope coverage is high-value-first, not exhaustive.

---

## What IS established

Both engines pass the full automated suite on the current build, and the security
and entitlement guarantees were re-verified on a live HTTP deployment here:

| | |
|---|---|
| SQLite | 8,286 passed, 0 failed |
| MariaDB 10.11 | 8,287 passed, 0 failed |

That is strong evidence about the **software**. It is not evidence about
**MilesWeb**, and this file exists so the difference is not quietly lost.
