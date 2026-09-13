# 08 — EXISTING TEST AUDIT & BASELINE RECONCILIATION
Covers deliverables **21, 22**.

---

## 1. Existing test audit

### 1.1 The runner
`tests/run.php` loads `lib.php` (assertions) + `bootstrap.php`, then `require`s every `tests/test_*.php` **into one shared PHP process**.

`bootstrap.php` pins `DB_DRIVER=sqlite`, points `SQLITE_PATH` at a throwaway file in the system temp directory, fakes minimal `$_SERVER`/`$_SESSION`, scrapes the `require .../lib/*.php` list out of `index.php` (219 libraries this run) and calls `boot()` once so all migrations and seeds execute.

### 1.2 THE AUTHORITATIVE BASELINE (measured, not quoted)

Executed during this audit:

```
php tests/run.php
bootstrap: app booted on a throwaway SQLite db (219 libs)
RESULT: 6948 passed, 0 failed
```

| Metric | Value |
|---|---|
| Test files | **444** (all executed) |
| Assertions passed | **6948** |
| Assertions failed | **0** |
| Exit code | **0** |
| Wall-clock runtime | **81.83 s** |
| PHP | 8.4.19 CLI |

**This is the one authoritative baseline. Any future claim must be re-measured, never quoted from an older report.**

### 1.3 What "6948" actually means — do not misread it
`tests/lib.php` is a 20-line harness. `t_ok()` counts **one assertion per call**; `t_eq()` delegates to it; `t_section()` prints a heading and **counts nothing**.

So **6948 = individual assertions, not test cases.** Static call sites: 4075 `t_ok`, 1802 `t_eq`, 7 `t_nothrow`, 484 `t_section`. Average ≈ 15.6 assertions per file. There is **no per-case pass/fail granularity** — a file containing one broken scenario still reports only individual assertion lines.

### 1.4 Coverage by area

| Area | Files | Assertions |
|---|---|---|
| Operations | 128 | 2010 |
| Core / admin | 100 | 1418 |
| Marketplace / Connect | 71 | 1279 |
| Money | 39 | 533 |
| Recruitment | 37 | 595 |
| Security | 24 | 311 |
| Reporting / IDEMS | 23 | 360 |
| SaaS / entitlement | 22 | 442 |
| **Total** | **444** | **6948** |

---

## 2. Baseline reconciliation — four structural weaknesses

These are not failures. They are reasons the green result is **weaker evidence than it appears**, and each becomes a mandatory fix in the testing roadmap.

### W1 — The harness engine is not the production engine. (Critical)
**Production database: MySQL/MariaDB. Existing automated regression harness: SQLite.**

`bootstrap.php` hard-pins `DB_DRIVER=sqlite`, so the current automated suite **does not fully exercise the production MySQL/MariaDB database engine**. Only 4 files mention MySQL and all test *string/config derivation*, never a connection.

SQLite may remain as a fast supplementary test layer, but it is never the production database. This session already produced two live MySQL-only defects that 6948 green assertions did not catch:
- `INSERT … ON CONFLICT` (SQLite syntax) crashing MariaDB with SQLSTATE 1064;
- `||` read as logical-OR on MySQL, turning concatenated names into `"0"`.

Both are exactly the class of bug that a SQLite-backed harness is blind to.

**Authoritative requirement going forward: MySQL/MariaDB is the database-testing target for every phase.**

### W2 — Module entitlement has almost no test coverage. (Critical)
Given entitlement is the brief's **hard security boundary**, coverage is:

- **One** file tests the paid-module ceiling: `tests/test_entitlement_lock.php` — **22 assertions** of 6948 (0.3%). It does assert the important things: ceiling narrows `module_entitled()`/`licence_enabled()`; `licence_save()` is clamped so the write path cannot self-unlock; the control install is never limited.
- `module_entitled`, `licence_entitled_ceiling` and `saas_entitled_modules` appear in **no other test file** as assertions.
- Adjacent tests (`test_access_gating`, `test_admin_gating_sweep`, `test_perms_by_licence`, `test_masters_offplan`, …) test **UI/list filtering**, not reachability.
- `ops_module_gate()` is asserted by only 2 files, both in **`peek` (read-only) mode**.

**No test drives a real request through the router for an un-entitled module and asserts a denial.** There is no cross-tenant entitlement test. This is the single largest gap between the brief's requirements (§26 SaaS entitlement testing) and reality.

### W3 — 51 self-skip guards can turn a refactor into a silent green. (Medium)
51 guard sites across 38 files follow the pattern:

```php
if (!function_exists('x')) { t_ok(true, '… not present — skipped'); return; }
```

22 of these abort the **entire file**. Affected files include `test_entitlement_lock.php`, `test_access_gating.php`, `test_admin_gating_sweep.php`, `test_backup.php`, `test_pwreset.php`, `test_tenant_migrate.php`.

**Measured good news:** in this run exactly **1 of 51 guards fired** (`test_module05_job360.php`), so 6947 of 6948 assertions are real work. The risk is *latent*: rename `module_entitled` and the whole entitlement file becomes one passing no-op instead of a failure.

### W4 — No test isolation. (Medium)
All 444 files share **one mutating SQLite database** in a single process — no per-test transaction, no reset. Many tests hand-save and restore settings. Order-dependent state leakage is possible, and failures can be non-local.

### Other observations
- `error_reporting` suppresses `E_NOTICE`/`E_WARNING`/`E_DEPRECATED`, so library warnings are invisible to the suite.
- Page rendering is out of scope; the README points at a separate Playwright crawl (`tools/smoke.js`, ~195 screens) that this suite does not run.
- Four files produce ≤3 assertions (`test_parse_all.php` produces 1).

---

## 3. Baseline statement for the programme

> **EXAACT test baseline, established at Phase 0:**
> 444 files / **6948 assertions** / **0 failures** / 81.83 s / PHP 8.4.19.
> Harness engine: **SQLite**. Production engine: **MySQL/MariaDB** — not yet exercised by the suite.
> Entitlement coverage: 22 assertions (0.3%), none end-to-end.
> MySQL coverage: **none**.
> Rendering coverage: none in this suite.

Every future phase must re-measure and restate these numbers. A phase may not be marked COMPLETED on a quoted figure.
