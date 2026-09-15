# Phase 2 · M2 — Test Results

## 1. Headline

| Engine | Version | Result |
|---|---|---|
| SQLite | bundled | **8317 passed, 0 failed** |
| MySQL / MariaDB | MariaDB 10.11.14 | **8318 passed, 0 failed** |

Both figures are the **whole suite**, not just M2. The one-assertion
difference is engine-specific coverage that only runs under MySQL; it is not a
skipped test on SQLite.

The new file `tests/test_m2_org_structure.php` contributes **31 checks**, all
passing on both engines.

> On counting: the numbers above are **assertions**, not test cases. One
> scenario usually costs several assertions (set up two spellings of one
> department → assert the name list, the hub row, its positions, its headcount
> and its people = five assertions from one scenario). M2's 31 assertions cover
> roughly 12 distinct scenarios.

## 2. What the M2 tests actually prove

| Group | Checks | What it holds in place |
|---|---|---|
| Masters are reused | 5 | Both masters exist; **no** `departments` or `designations` table was created |
| `dept_canon()` resolution | 5 | Code and label resolve to one canonical name, case- and whitespace-insensitively |
| Nothing is lost | 3 | Unknown free text, blank and `null` all survive untouched |
| The real defect, end-to-end | 6 | One department written two ways appears **once**, carrying both the headcount and both people |
| Live-master bindings | 5 | A designation added in Settings reaches the back-office form and renders as a label in the Command Centre |
| Cross-tenant cache | 2 | The list stays cached within a request, and is rebuilt when the database changes underneath it |
| Boundary | 4 | `hired_inspector_id`, `position_id`, and both pipeline engines are untouched |

## 3. Mutation testing

Every fix was reverted one at a time to prove the tests are load-bearing and
not merely decorative.

| Mutation | Result |
|---|---|
| `dept_canon()` returns the raw stored value | **6 failed** ✅ caught |
| `lk_options_or` cache no longer keyed on `db_epoch()` | **5 failed** ✅ caught |
| Back-office staff form reads the frozen constant again | **1 failed** ✅ caught |
| Command Centre labels read the frozen constant again | **1 failed** ✅ caught |
| *(all restored)* | **31 passed, 0 failed** |

No mutation passed silently. No test was weakened to obtain a green result, and
no skips were introduced.

## 4. Defects reproduced before they were fixed

Neither fix was made on suspicion. Both were demonstrated first.

**(a) One department, two rows.** Seeding a position with `Quality` and two
people — one stored `Quality`, one stored `QUALITY`, exactly as two shipped
screens write them:

```
BEFORE                                   AFTER
[Quality]  positions=1 sanctioned=3      [Quality]  positions=1 sanctioned=3
           people=1                                 people=2
[QUALITY]  positions=0 sanctioned=0
           people=1
```

**(b) A cross-tenant leak in the master accessor.** `lk_options_or()` cached
its list on the list key alone. Because EXAACT puts each workspace in its own
database and swaps that database *inside* a request, the cache outlived the
database it was read from:

```
Tenant A reads:                                  INSPECTOR = Site Inspector
Tenant B really has (from its own database):     INSPECTOR = Inspector
Tenant B was SHOWN:                              INSPECTOR = Site Inspector   ← leak
```

Fixed by keying the cache on `db_epoch()`, the convention already used by
`db()` itself and by dozens of other caches in the codebase. `trade_options()`
had the identical flaw and was fixed the same way. Re-verified after the fix:

```
Tenant B is SHOWN:                               INSPECTOR = Inspector   ← correct
```

**Scope of that leak, stated accurately:** it exposed *configured list values*
(the labels a workspace chooses for its own dropdowns) — not customer records,
invoices or people. It required two workspaces to be touched within one PHP
process, which happens on the login path. It is a real multi-tenancy defect and
is now closed, but it was not a customer-data breach and is not described as
one.

## 5. Regression

No existing test was modified, weakened or skipped. The full suite was run
before and after on both engines.

The only non-M2 change was regenerating `deploy-check.php`, which the suite
itself demanded after five source files changed — the checksum test named its
own remedy (`php tools/make_deploy_check.php`) and passed once it was run.
