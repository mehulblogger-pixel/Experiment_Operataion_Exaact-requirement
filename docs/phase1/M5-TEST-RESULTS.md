# Milestone 5 — Test Results

**Run date:** 2026-09-14 · **Command:** `php tests/run.php`

---

## 1. Headline

| | Before M5 | After M5 |
|---|---|---|
| Passing | 7,398 | **7,474** |
| Failing | 0 | **0** |
| Skipped | 0 | **0** |
| New assertions | — | 76 |

No test was weakened, skipped, deleted or rewritten to obtain a green result.
No test was marked as skipped for any reason.

---

## 2. What the environment was — stated plainly

**The suite ran on SQLite, not MySQL/MariaDB.** The test harness boots the real
application on a throwaway SQLite database.

MySQL was **not** available in this environment and **was not tested**. Nothing
in this document should be read as evidence of MySQL production behaviour.

What that does and does not limit:

- The M5 changes are **pure PHP decision logic** — route classification, boolean
  entitlement checks, and permission ordering. None of them adds, changes or
  depends on SQL, schema, column types, collation or driver behaviour. The
  reasoning they encode is identical on either engine.
- What SQLite cannot prove is anything engine-specific. M5 introduces nothing
  engine-specific, which is why this is recorded as a stated fact rather than a
  risk. It remains an honest gap, not a dismissed one.

**No schema change was made in M5.**

---

## 3. The new suite — `tests/test_m5_runtime_entitlement.php` (76 assertions)

Every group is written as a pair: the DENY it must now produce, **and** the
ALLOW that proves an entitled company sees no change.

| Group | Covers | Assertions |
|---|---|---|
| **A** | Unmapped routes in a paid family — classification, DENY without entitlement, ALLOW with it, and no over-blocking of unrelated or deliberately ungated routes | 15 |
| **B** | Public careers page — closed without HR, open with HR, still opt-in | 3 |
| **C** | Master bypass — master denied in an unbought module, master retained in a bought one and in core, non-master and signed-out never granted | 12 |
| **D** | Unowned access module denied; `admin` product key still resolves; all 7 core access modules never blocked; owned-and-entitled still passes | 13 |
| **S-1** | The mandatory acceptance scenario, signed in as a master | 29 |
| Control | The control install and self-hosted single business are never enforced against | 7 |

`RESULT: 76 passed, 0 failed`

---

## 4. Regression scope

Full suite, every existing test file, including the areas M5 touches most:

| Area | Result |
|---|---|
| Operations (calls, jobs, vouchers, scheduling) | pass |
| Reporting / idems (the 30 master sites) | pass |
| Money (invoicing, profitability) | pass |
| Recruitment / HR + careers | pass — 19 assertions, incl. both careers states |
| Entitlement engine (M3) | pass — 80 assertions |
| Entitlement migration (M4) | pass — 76 assertions |
| Module registry (M2) | pass — 63 assertions |
| Permissions / no-lockout | pass |
| Deploy verification | pass — checksums regenerated after the code change |

---

## 5. Existing tests changed in M5

**None.** No existing test's expectation, setup or assertion was modified.

For the record, the two tests changed in Milestone 3 were each documented in the
file itself at the time, and neither was touched again here:

- `test_entitlement_lock.php` — expectation corrected; it had asserted the
  defect (a blank entitlement record granting everything).
- `test_capability_autoconfig.php` — setup corrected; its own comment already
  called the value it used "the buggy default". Every assertion was kept.

---

## 6. Checks run before each push

- `php -l` on every changed file — clean.
- Focused suites for each item as it landed, before moving to the next.
- Full suite after items A + C + D, and again after B + E.
- `php tools/make_deploy_check.php` re-run, because the deploy verifier's
  staleness guard correctly flagged the changed libraries. That guard firing was
  the *expected* signal that real code had changed, not a regression.
