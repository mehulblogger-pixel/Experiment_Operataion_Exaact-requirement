# Milestone 15 — MySQL / MariaDB Validation

**The limitation carried since the beginning is closed.** The application, and
the entire test suite, now run on the production engine.

| | |
|---|---|
| **Engine** | MariaDB **10.11.14** (Ubuntu 24.04), installed and run in this environment |
| **PHP** | 8.4.19, `pdo_mysql` |
| **Character set / collation** | `utf8mb4` / `utf8mb4_unicode_ci` |
| **Server SQL mode** | `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION` |
| **Session SQL mode after the app connects** | `PIPES_AS_CONCAT` — `lib/db.php` deliberately relaxes strict mode per connection, and it works |
| **Time zone** | `SYSTEM` |
| **Databases used** | five throwaway databases; no customer data at any point |

---

## 1. Results

| Suite | SQLite | MariaDB 10.11 |
|---|---|---|
| **Full suite** | **8,245 passed, 0 failed** | **8,246 passed, 0 failed** |
| M13 security | 51 / 0 | **51 / 0** |
| M14 object authorization | 46 / 0 | **46 / 0** |
| M5–M12 entitlement (six suites) | pass | **540 / 0** |

MariaDB runs one assertion *more* than SQLite: the account-prefix block is
genuinely engine-conditional and its MySQL branch had never been exercised.

## 2. Fresh installation (§4)

```
empty database → 306 tables, 1 admin seeded, 11.33s
run 2          → 306 tables, 1 user, 1.21s
run 3          → 306 tables, 1 user, 1.17s
canary row planted before run 2 → still present after run 3
```

Complete, deterministic, **idempotent**, and no duplicate schema objects. The
11s → 1.2s drop is schema creation versus steady state — no expensive repeated
initialization.

## 3. Existing installation (§5)

A populated install (users, calls, jobs, quotations, invoices, leads, settings)
was upgraded by re-running the boot/migration twice:

```
before : 306 tables · users 2 · calls 1 · jobs 1 · quotations 1 · invoices 1 · leads 1 · offices 18
after  : 306 tables · users 2 · calls 1 · jobs 1 · quotations 1 · invoices 1 · leads 1 · offices 18
LEGACY-Q subject preserved · LEGACY-INV total 98765.00 preserved
legacy user's branch scope preserved · settings preserved (incl. portal_enabled)
```

Forward-only, additive, non-destructive.

## 4. Tenant isolation on MySQL (§6, §8) — both directions

```
Tenant ALPHA : db=exaact_tenant_a  user#2  quote#1 (ALPHA-SECRET-WORK)
Tenant BETA  : db=exaact_tenant_b  user#2  quote#1 (BETA-SECRET-WORK)
separate databases: YES

IDENTITY A->B : REFUSED        IDENTITY B->A : REFUSED
READ     B->A : NOT PRESENT (separate database)
MUTATE   B->A : alpha's quote INTACT after beta ran UPDATE + DELETE
MUTATE   A->B : beta's quote  INTACT after alpha ran UPDATE + DELETE
```

Note both tenants deliberately hold **the same ids** — user 2, quote 1 — so an
id substitution had every chance to land. Two independent barriers hold: a
separate database per tenant, and M13's workspace binding on the identity.

## 5. Wrong-database protection (§7)

| Scenario | Result |
|---|---|
| Healthy workspace | boots, 306 tables |
| Workspace whose database was **deleted** | **controlled failure** — no silent empty workspace |
| Workspace whose database was **never wired up** | refused **before any database work** (`unconfigured`) |
| **Suspended** workspace | refused before any database work |
| **Unknown** subdomain | refused before any database work |

No configuration mistake silently produced an empty customer workspace, and none
fell through to the control database.

## 6. Background execution (§18)

```
engine: mysql · database: exaact_fresh · signed-in user: none (correct for CLI)
office scope: []  ·  cron entitlement guards present  ·  skip summary present
```

## 7. Backup and restore (§22)

```
backup  : 20260914_..._m15_test.json.gz
change  : canary row deleted, a setting changed
restore : {"restored":6505,"tables":306}
after   : users 2→2, offices 19→19, deleted canary RETURNED, setting rolled back
```

Verified against the database directly, not through the in-process cache.

## 8. Index behaviour (§24)

185 secondary indexes created on MariaDB; `indexes_migrate()` re-runs safely.
`ix_calls_status` exists and MySQL lists it in `possible_keys`; on a 213-row
table the optimiser correctly prefers a scan. No missing or broken index.

---

## 9. What MySQL exposed that SQLite had hidden

This is the point of the milestone. **Three real application defects** were only
ever visible on the production engine:

### D1 — `LIMIT ?` silently returned nothing (High)

Seven queries bound the row limit as a parameter. PDO binds it as a *string*, and
MySQL rejects `LIMIT '8'`; SQLite accepts it. Most sites wrap the query in a
`catch` that returns an empty array, so on production these features would have
been **silently blank, with no error at all**:

| Site | What went dark on MySQL |
|---|---|
| `connect_verify.php` | the verification **moderation queue** |
| `connect_analytics.php` | demand-by-location analytics |
| `ncdca.php` | **repeat-NCR detection** |
| `schedboard.php` | the unassigned-calls scheduling board |
| `uvae.php` / `uvaae.php` | vendor assessment + audit history on the one-pager |
| `connect_channels.php` | recent channel messages |

Fixed at all seven: the limit is inlined as a validated integer, which cannot
carry SQL.

### D2 — spurious KPI versions on every cosmetic edit (Medium)

`tapi_kpi_version_snapshot()` compared `target` and `threshold` **as text**. They
are `DECIMAL` columns: MySQL returns `"20.00"` where the form sends `20`, so a
mere rename was judged a change of meaning and wrote a version into the
**governance audit history**. Now compared numerically, with a blank still
distinct from a zero.

### D3 — "6.0 years" on a client-facing document (Low)

`experience_years` is `DECIMAL(5,1)`. The candidate one-pager printed
`"6.0 years"` on MySQL and `"6 years"` on SQLite. Now trimmed — `6.5` still
prints as `6.5`.

## 10. Why the suite had never caught them

The suite could only ever run on SQLite. Beyond the three defects, MySQL exposed
several places where the **tests themselves** were engine-specific or not
isolation-safe — `sqlite_master`/`PRAGMA` introspection, `EXPLAIN QUERY PLAN`,
hardcoded `INTEGER PRIMARY KEY AUTOINCREMENT`, `ESCAPE '\'`, fixtures sharing a
`quote_no`/`username`, and absolute ledger totals.

The most instructive: **MySQL treats DDL as an implicit commit.** Several tests
wrap themselves in `beginTransaction()` and then call a `*_migrate()`; on MySQL
the transaction is already over by the time migrate returns, so the rollback
protects nothing and earlier tests' rows remain. Proved directly:

```
sqlite: BEGIN, INSERT, CREATE TABLE, INSERT, ROLLBACK -> 0 rows survived
mysql : BEGIN, INSERT, CREATE TABLE, INSERT, ROLLBACK -> 2 rows survived  [rollBack() FAILED]
```

Those assertions were never safe — they only looked safe on one engine. They are
now delta-based, which is both engine- and order-independent, and therefore
strictly stronger. **No test was weakened, skipped or deleted.**

## 11. Running it yourself

```
DB_DRIVER=mysql DB_HOST=127.0.0.1 DB_NAME=<throwaway> DB_USER=<user> DB_PASS=<pass> \
  php tests/run.php
```

`tests/bootstrap.php` drops every table first, so point it only at a throwaway
database. With no `DB_DRIVER` the suite behaves exactly as it always has, on a
temporary SQLite file.
