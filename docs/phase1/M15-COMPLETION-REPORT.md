# Milestone 15 — Completion Report
## Production Readiness, MySQL Validation & Deployment Boundary

**Verdict: PASS WITH DOCUMENTED LIMITATIONS**
**SQLite: 8,245 passed, 0 failed · MariaDB 10.11: 8,246 passed, 0 failed**

---

### 1. Environment

MariaDB **10.11.14** (installed and run here) · PHP **8.4.19** with `pdo_mysql` ·
`utf8mb4` / `utf8mb4_unicode_ci` · server SQL mode `STRICT_TRANS_TABLES,…`,
relaxed per connection by the app to `PIPES_AS_CONCAT` · five throwaway
databases · **no customer data at any point**.

### 2. MySQL result — the limitation is closed

**The application and the entire test suite now run on the production engine.**
Every milestone since M2 reported "MySQL NOT EXECUTED". That sentence is retired.

The suite can be pointed at a real server (`DB_DRIVER=mysql …`); with no
environment set it behaves exactly as before on SQLite. **Every future milestone
can validate on MySQL.**

### 3. Fresh installation · migration · provisioning

**Fresh:** empty database → 306 tables + admin in 11.3 s; re-run twice → still 306
tables, 1 user, canary row intact, 1.2 s. Complete, deterministic, **idempotent**.

**Migration:** a populated install re-migrated twice — every record, user, branch
scope and setting preserved; no duplicate schema objects; forward-only and
non-destructive.

**Provisioning:** each tenant boots into its **own** database.

### 4. Cross-tenant isolation on MySQL — both directions

Both tenants deliberately held **the same ids** (user 2, quote 1):

```
IDENTITY A->B REFUSED   IDENTITY B->A REFUSED
READ   B->A  not present (separate database)
MUTATE B->A  alpha's quote INTACT after beta ran UPDATE + DELETE
MUTATE A->B  beta's quote  INTACT after alpha ran UPDATE + DELETE
```

**Wrong-database protection:** a deleted, unwired, suspended or unknown workspace
is refused — three of the four **before any database work**. No configuration
mistake silently produced an empty customer workspace.

### 5. Regressions on MySQL

**M13** 51/0 · **M14** 46/0 · **M5–M12 entitlement** 540/0 · Operations, Quality,
Money, Sales, Recruitment, Marketplace suites all pass · **S-1** intact ·
cron/CLI runs with the right database and no user · backup → change → restore
returned 6,505 rows across 306 tables with deleted rows recovered · error
disclosure still admin-only (M13 fix intact) · no credential tracked in git.

### 6. What MySQL found that SQLite never could — three real defects

| | Defect | Impact |
|---|---|---|
| **D1 High** | `LIMIT ?` bound as a parameter (7 queries) | Seven features would have arrived on production **blank and silent** — moderation queue, repeat-NCR detection, scheduling board, demand analytics, vendor history, channel messages. The errors were swallowed by `catch`, so nobody would have known to report them |
| **D2 Medium** | KPI versioning compared DECIMALs as text | Every cosmetic rename wrote a **spurious version into the governance audit history** |
| **D3 Low** | DECIMAL round-trip | Candidate one-pager read **"6.0 years"** on a client-facing document |

All three fixed and verified on both engines. **This is the milestone's real
return**: these were undetectable by design without a production engine.

### 7. Changes

**Application (9 files):** `connect_analytics`, `connect_verify`,
`connect_channels`, `ncdca`, `schedboard`, `uvae`, `uvaae` (D1) · `tapi_gov` (D2)
· `doc_templates` (D3).

**Harness:** `tests/bootstrap.php` (targets MySQL), `tests/lib.php` (engine-aware
introspection), plus the test files that were SQLite-specific or not
isolation-safe.

**No schema change. No data touched. No security control altered. No test
weakened, skipped or deleted.**

### 8. Deployment findings

`M15-DEPLOYMENT-CHECKLIST.md` gives the full sequence. The order that must not
vary: **backup → upload → open once (schema builds) → deploy-check → cron →
smoke test.** The database user needs `CREATE`/`ALTER` permanently, because the
app builds and extends its own schema. Uploaded documents live **in the
database**, so size the backup accordingly.

### 9. Project costing — ARCHITECTURAL DECISION REQUIRED (§27)

Current behaviour recorded, unchanged: `project_costings` has an `office_id`, but
`pc_all()` applies **no scope clause**, so every holder of `pc_can()` sees every
costing — including day rates, overheads, contingency, margin and expected
revenue. The detail agrees with the list, so there is no leak relative to the
product's own design.

**The question is policy, not code: should a branch manager see another branch's
margins?** Options, in my recommended order: (1) branch-scope it with the M14
pattern; (2) add a "see commercial detail" permission; (3) leave it, if costing
is a head-office function. Deliberately not decided inside a readiness milestone.

### 10. Known limitations

**L1** validated on MariaDB 10.11, not the exact mPanel host — first deploy is
the remaining test · **L2** `invoice_no` column default `''` vs `NULL` (latent
only; the app inserts NULL) · **L3** no live HTTP host, so TLS/headers/cookie
flags untested · **L4** pre-M13 sessions bind at next sign-in · **L5**
performance sanity-checked, not load-tested · **L6** project costing policy ·
**L7** MySQL test isolation is weaker (DDL implicitly commits) — now understood
and contained.

### 11. Final verdict

**PASS WITH DOCUMENTED LIMITATIONS.** MySQL/MariaDB works; fresh installation
works; migration preserves data; tenant provisioning and cross-tenant isolation
hold in both directions; M13, M14, entitlement, Operations and S-1 all intact on
the production engine; no critical or high production blocker remains. The
remaining limitations are deployment and UAT items, not security bypasses — with
one policy decision (project costing) referred to you.
