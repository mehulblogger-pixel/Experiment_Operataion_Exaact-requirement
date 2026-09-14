# Milestone 15 — Production Readiness Audit

**Status:** complete · **SQLite 8,245 / MariaDB 8,246, 0 failed** · **Baseline:** 11415c0 (M14)

M13 attacked the security boundary. M14 attacked object authorization. **M15 asks
whether this build can safely leave the development environment** — and it
finally answers the question that has followed every milestone so far: *does it
work on MySQL?*

It does. And getting there found three real defects that only production would
ever have shown.

---

## 1. Findings, classified (§2)

| # | Finding | Class | Status |
|---|---|---|---|
| **D1** | `LIMIT ?` bound as a parameter — seven queries returned **silently empty** on MySQL | **DEFECT (High)** | **FIXED** |
| **D2** | KPI versioning compared DECIMALs as text — every cosmetic edit wrote a spurious governance version | **DEFECT (Medium)** | **FIXED** |
| **D3** | `"6.0 years"` on the candidate one-pager (DECIMAL round-trip) | **DEFECT (Low)** | **FIXED** |
| T1 | Suite could only run on SQLite (`sqlite_master`, `PRAGMA`, `EXPLAIN QUERY PLAN`, `AUTOINCREMENT`, `ESCAPE '\'`) | DEFECT (test) | FIXED — engine-aware |
| T2 | Tests relying on `beginTransaction()` for isolation — MySQL implicitly commits on DDL | DEFECT (test) | FIXED — delta-based |
| T3 | Fixtures sharing `quote_no` / `username`, and a draft invoice taking the `''` column default | DEFECT (test) | FIXED |
| C1 | `config.local.php` / `tenants.php` must be created per environment | **CONFIGURATION REQUIREMENT** | documented |
| C2 | Database user needs `CREATE`/`ALTER` permanently — the app builds its own schema | **CONFIGURATION REQUIREMENT** | documented |
| P1 | HTTPS, domain + wildcard subdomain, SMTP, cron scheduling | **DEPLOYMENT REQUIREMENT** | checklist |
| P2 | Pre-M13 sessions carry no workspace binding until next sign-in | **DEPLOYMENT REQUIREMENT** | checklist §6 |
| E1 | No live HTTP host here — TLS, headers, cookie flags untested | **ENVIRONMENT LIMITATION** | L3 |
| E2 | `invoices.invoice_no` defaults to `''` while the unique index expects `NULL` for drafts | **DEFERRED** | L2 — app already inserts NULL |
| E3 | Project costing is branch-global and holds commercial rates | **ARCHITECTURAL DECISION REQUIRED** | §27 below |

Nothing was changed merely because it differed from an ideal deployment.

## 2. The three application defects

Full evidence in `M15-MYSQL-VALIDATION.md` §9. In business terms:

**D1 is the serious one.** Seven features would have arrived on production
**blank and silent** — no error, no warning, just nothing: the verification
moderation queue, repeat-NCR detection, the scheduling board, demand analytics,
vendor assessment history on the one-pager, recent channel messages. Because the
failures were swallowed by `catch` blocks, nobody would have known to report
them; the screens would simply have looked "empty" forever.

**D2 quietly corrupts an audit trail.** Renaming a KPI wrote a new *version* into
the governance history, because `"20.00"` from MySQL is not the string `20` from
the form. Over months the history would fill with changes that never happened.

**D3** is cosmetic but client-facing: a candidate one-pager reading "6.0 years".

All three were invisible on SQLite by construction, which is exactly why M15
needed a real engine rather than more reasoning about one.

## 3. What was proven working

Fresh install · idempotent re-run (×3) · migration of a populated install ·
tenant provisioning · cross-tenant isolation **both directions with identical
ids** · wrong-database protection (deleted / unwired / suspended / unknown, all
refused before touching data) · M13 and M14 intact on MySQL · all M5–M12
entitlement suites on MySQL · Operations, Quality, Money, Sales, Recruitment,
Marketplace suites on MySQL · cron/CLI context · backup and restore round trip ·
185 indexes present and applicable · controlled failures with no SQL, path or
credential disclosure · no credential tracked in git.

## 4. §27 — Project costing: ARCHITECTURAL DECISION REQUIRED

M14 recorded that project costings are **branch-global**. M15 changed nothing and
restates the current behaviour for your decision:

* `project_costings` **has** an `office_id` column.
* `pc_all()` — the list — applies **no scope clause at all**, so every user with
  `pc_can()` sees every costing in the tenant.
* The detail therefore agrees with the list. There is no leak relative to the
  product's own design.
* The content includes **day rates, overheads, contingency, negotiation margin
  and expected revenue** — the commercially sensitive numbers.

The question for you is not technical: **should a branch manager see another
branch's margins?** Three options, in the order I would recommend them:

1. **Branch-scope it** like leads and complaints — one gate, the M14 pattern, low risk.
2. **Add a separate permission** (e.g. "see commercial detail") so costing stays tenant-wide but the numbers are restricted.
3. **Leave it** — correct if costings are a head-office function and every holder of `pc_can()` is trusted with them.

Not a security finding; a policy decision. Deliberately not taken inside a
readiness milestone.

## 5. Changes made

**Application (4 files):** `lib/connect_analytics.php`, `lib/connect_verify.php`,
`lib/connect_channels.php`, `lib/ncdca.php`, `lib/schedboard.php`,
`lib/uvae.php`, `lib/uvaae.php` (D1) · `lib/tapi_gov.php` (D2) ·
`lib/doc_templates.php` (D3).

**Test harness:** `tests/bootstrap.php` (can target MySQL), `tests/lib.php`
(engine-aware introspection), and the test files that were SQLite-specific or not
isolation-safe.

**No schema change. No data touched. No security control altered. No test
weakened, skipped or deleted.**
