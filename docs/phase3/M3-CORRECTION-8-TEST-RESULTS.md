# Phase 3 · M3 CORRECTION #8 — TEST RESULTS

## New suite — `tests/test_p3m3c8_spine.php`

**62 assertions, 0 failed, on both engines.**

Nothing here is mocked. The column is really dropped on whichever engine is
running, and the migration is really made to fail.

| Section | §7 | What it holds |
|---|---|---|
| **C8.1** | A | With `cond_key` present: the row is written, the metadata is **genuinely stored** (read back), no core error, optional metadata reports itself usable. Without this the later sections could pass vacuously |
| **C8.2** | B, E, F, H, I | `cond_key` **really dropped**, then eight representative callers — CRM lead, CRM opportunity, quotation, invoice, operations job, quality NCR, marketplace contract, platform partner — each writes its row, each reports success, none records a core error. A caller that *does* pass a key gets its core row **and** is told the metadata was not stored |
| **C8.3** | G | A **genuine** recruitment action end to end: a real chain, a real deleted source record, a real refusal returning `ENTITY_UNRESOLVED`, and its audit row written although `cond_key` could not be stored |
| **C8.4** | §11 | The fallback path still records the **actor**, the actor's **branch**, and the caller's own options — it removed the metadata dependency, not the controls |
| **C8.5** | C, D | The table is moved out from under the migration. It fails, says so, and **does not report success**; restored, it is **retried** and succeeds, and condition metadata starts being stored again |
| **C8.6** | §6, §9 | The table is replaced by a **view of itself**: the core INSERT fails for real, `act_log()` returns 0 rather than a row id, and the failure is **recorded**. After repair — **without moving the epoch** — auditing works again and `cond_key` returns, which it can only do if the migration genuinely retried |

## §17 — S1 reproduced before the fix

Same probe, both sides, column genuinely dropped on the running engine:

| | `act_log()` returned | rows written |
|---|---|---:|
| **Pre-fix (correction #7)** | `0, 0, 0` | **0 of 3** |
| **Post-fix (correction #8)** | `1, 2, 3` | **3 of 3** |

A CRM note, a quotation e-mail and a quality nonconformity — three modules with no
connection to recruitment.

## §13 — three tests of mine that were passing for the wrong reason

**1 · `t_columns()` returns a LIST, not a boolean.**
`t_ok(t_columns('activities', ['cond_key']), …)` is truthy whether or not the
column exists. It was passing for no reason at all — here, and in **correction
#7's own `C7.0`**, where it was the assertion that was supposed to guarantee the
column was there. Both now ask `in_array('cond_key', t_columns('activities'), true)`.

**2 · Moving the epoch let the column come back.**
The first version of `C8.2` moved the database epoch after dropping the column.
That lets the optional migration **re-add** it — modelling a host that *can* run
DDL, which is the opposite of the condition under test. The epoch is now
deliberately left alone there, so the process believes the column is present and
it is not.

**3 · Moving the epoch would also have hidden a latching guard.**
`C8.6` proves the migration retries after a failure. If it moved the epoch on the
way out, the guard would be reset anyway and an early-latching implementation
would pass. It does not, and `cond_key` is dropped first so its return can only
come from a real retry.

## A defect the FULL regression found that the focused suite could not

The first full run failed five assertions in `test_saas_login_as`, identically on
both engines:

```
FAIL  "Log in as" reports the company workspace ready (schema built in-process)
FAIL  one-shot guard check threw: no such column: must_change_pwd
```

That suite passes alone (9/0) and with its neighbours (169/0). The cause was this
correction's own suite: it set `$GLOBALS['__db_epoch']` to raw incremented values,
and `db()` assigns that same global from **its own counter** on every `db(true)`.
The workspace switch received an epoch number this suite had already used, so every
guard that had run against *this* database believed it had already run against the
*new* one, and the second workspace got a half-built schema.

A migration guard silently reporting "already done" for the wrong database is
exactly the class of defect this sequence exists to catch. The epoch is now moved
into a range that counter can never reach and handed back exactly as found, with an
assertion that it was restored.

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **10 087 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** | **10 088 passed, 0 failed** |

| Suite | Result |
|---|---:|
| **`p3m3c8_spine`** | **62 / 0** |
| `p3m3c7_idempotent` | 86 / 0 |
| `p3m3c6_auditref` | 181 / 0 |
| all M3 suites (`p3m`) | **1260+ / 0** |
| CRM · Money · Quality · Operations · Marketplace | within the full regression, green |

No test was skipped, disabled or weakened.
