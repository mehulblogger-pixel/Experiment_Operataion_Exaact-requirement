# Phase 3 · M3 CORRECTION #10 — TEST RESULTS

## New suite — `tests/test_p3m3c10_state.php`

**46 assertions, 0 failed — on SQLite AND on MariaDB.**

| Section | §§ | What it holds |
|---|---|---|
| **C10.1 · CASE C + F** | 5 | A structure that is there is `READY`, carries no error, and shows no failed attempts on the ledger |
| **C10.2 · §9 · CASE A + D** | 1, 4, 7, 10 | **The V1 false-green.** Column absent, **attempt count proved zero**, column perfectly creatable → both report `NOT_ATTEMPTED`, the message says *"Not attempted"*, and nothing claims a failure that never happened. Then **one real attempt succeeds** — so `FAILED` would have been a lie |
| **C10.3 · CASE B** | 3, 4, 7 | A genuinely failed attempt: `NOT_ATTEMPTED` before, the attempt proved to have happened, `FAILED` after, with the recorded reason — then `FAILED → retry → READY` |
| **C10.4 · CASE E** | 5, 7 | The index fails **on its own account** while the column stays `READY`, each with its own error; `cond_key` is still stored; the index retries to `READY` |
| **C10.5 · §6 + §8** | 6, 8 | `READY` survives ten observations. `NOT_ATTEMPTED` survives ten observations **with the attempt count still zero**. Then the budget is spent for real — 1, 2, 3 — and five more observations do not move it, while `FAILED` survives observation too |

## §10 — how the tests avoid the inference V1 was

Every `FAILED` assertion is preceded by an attempt **proved to have happened**
(`column_tries > 0`). Every `NOT_ATTEMPTED` assertion is preceded by an attempt
count **proved to be zero**. Neither state is inferred from the absence of the
other — which is exactly the inference V1 made.

`C10.2` goes one step further and proves the *counterfactual*: having asserted
`NOT_ATTEMPTED`, it makes one real attempt and shows it **succeeds**. A structure
one call away from existing was being reported as unavailable.

## A test that had to be corrected, not kept

`C9.6` required:

```php
t_ok(strpos($stC['index_error'], 'column is unavailable') !== false, …);
```

at a point where **nothing had attempted anything** — V1's defect written into a
test, demanding a claim about a failure that had not occurred. It now requires
`NOT_ATTEMPTED` for both, and additionally that the index does **not** assert a
column failure that never happened. The replacement is strictly more specific than
what it replaces.

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **10 196 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** | **10 197 passed, 0 failed** |
| `p3m3c10_state` | **46 / 0** on both engines |
| all M3 suites (`p3m`) | **1333 / 0** |

No test was skipped, disabled or weakened.
