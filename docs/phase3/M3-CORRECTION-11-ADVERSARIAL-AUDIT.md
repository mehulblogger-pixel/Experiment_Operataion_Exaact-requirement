# M3 CORRECTION #11 — ADVERSARIAL AUDIT

    Environment
      SQLite        3.45.1
      MariaDB       10.11.14
      PHP           8.4.19
      Commit        c30d1a0
      Working tree  clean — NO product code was modified during the attack.
                    Every probe lives in a scratchpad copy of phpapp/.

---

## W1 TESTS

| Probe | Result |
|---|---|
| **A · failure isolation** | **PASS** — A genuinely failed (`act_log` → 0) and A sees its own `A_ERROR_987654` |
| **B · workspace switch** | **PASS** — B, having failed at nothing, sees `''`. No trace of A |
| **C · B's own failure** | **PASS** — B sees `B_ERROR_123456`, and A's is not returned |
| **D · A → B → A** | **PASS** — back in A, B's error is not returned |
| **E · rapid switching** | **PASS** — `A→B→A→B→A→B`, **0 leaks** in either direction |
| **Four-channel isolation** | **PASS when exercised** — see X2: the suite does not exercise it |
| **Epoch collision** | **PASS** — `A→B→C→A` gives epochs 12, 13, 14, 15: four distinct, no reuse |
| **Epoch restoration** | **PASS** — after the suites, `__db_epoch = 2`, not in the suite's private range |

**These were run against two GENUINELY SEPARATE DATABASES**, switched with real
`db(true)` calls driven by `SQLITE_PATH`, and on MariaDB with two real databases
(`atk_a`, `atk_b`). The identity of each was read back from `config.php` and
asserted different before any security claim.

**W1's fix is sound.** It survives an attack far stronger than the one that shipped
with it.

## W2 TESTS

| Probe | Result |
|---|---|
| **STORED persistence** | **PASS** — `STORED` and the value read back from the column |
| **Zero-row UPDATE** | 🔴 **FAIL — X1** |
| **Real metadata failure** | **PASS** — `FAILED`, with the reason on the write's own channel |
| **Core-row survival** | **PASS** — core row intact after the metadata write failed |
| **Cross-workspace update** | 🔴 **FAIL — X1** |

## STATE MODEL

| | |
|---|---|
| `NOT_ATTEMPTED` | holds — survives observation, becomes READY on a successful attempt |
| `FAILED` | holds — only after an attempt proved to have happened |
| `READY` | holds |
| `STORED` | 🔴 **does not hold** — see X1 |
| Transition 5 (READY column + FAILED index) | **PASS** — column stays `READY`, index `FAILED`, metadata still stores. **T2 holds** |

## FALSE-GREEN CHECKS

* B is interrogated **before** it is ever made to fail — a test that failed B first
  would pass whether or not the leak existed.
* Every leak assertion uses a marker that could only come from the other workspace.
* `STORED` is never accepted on the returned status alone; the value is read back
  out of the database.
* Database identity is asserted different **before** any isolation claim.

## MUTATIONS (attack on meaning, not count)

| Mutation | Result | Detecting assertion |
|---|---|---|
| metadata failure deletes the core row | **CAUGHT** (5 failures) | `C8.2 B · the core row exists even though the metadata could not be stored (want 1, got 0)`, `C8.3 G`, `C9.6 C` — named, not incidental |
| **key only the CORE channel, leave the other three global** | 🔴 **SURVIVED — 1415 passed, 0 failed** | **none** — see X2 |

**Invalid probes:** none discarded silently. One earlier mutation in this
correction's own battery (`W2-M5`) was invalid — it appended a comment instead of
removing the `UPDATE`, so it changed nothing and "survived". That was reported,
rewritten and re-run at the time.

## REGRESSION / CONTAMINATION

| | |
|---|---|
| **Order 1** — #11 → workspace-switching → epoch check | **46 / 0** |
| **Order 2** — workspace-switching → #11 → epoch check | **55 / 0** |
| Epoch left behind | none (`2`, outside the suite's `920000+` range) |
| Full regression (at commit) | SQLite **10 232 / 0** · MariaDB **10 233 / 0** |

---

# NEW FINDINGS

## 🔴 X1 · MEDIUM · `STORED` is returned when NOTHING was written

An `UPDATE … WHERE id = ?` that matches no row **does not throw**. It is not
caught, so the writer reports success.

```
SQLite   zero-row UPDATE      → STORED   (rows written: 0)
SQLite   cross-workspace id   → STORED   (rows touched in B: 0)
MariaDB  zero-row UPDATE      → STORED   (rows written: 0)
MariaDB  cross-workspace id   → STORED   (rows touched in B: 0)
```

Same cause on both engines. This breaks the rule correction #11 was written to
establish — **a successful status must correspond to actual persisted state** —
and it breaks it in the one place the correction was supposed to make honest.

The **cross-workspace** form is the sharper one: called while workspace B is
active, with a row id belonging to workspace A, the system reports `STORED` having
written nothing, anywhere. Isolation is not violated — A's row was correctly
untouched — but the *answer* is false, and W2 exists precisely so callers can trust
that answer.

**The fix is one line of intent:** the UPDATE's affected-row count decides between
`STORED` and a not-stored status. `PDOStatement::rowCount()` is available on both
engines for an UPDATE.

## 🔴 X2 · MEDIUM · The "four channels" claim is not held by any test

Correction #11's headline is that the audit found four error channels, not one, and
keyed them all. Attacking that claim directly:

```
MUTANT · key only the CORE channel; leave column, index and write global
RESULT: 1415 passed, 0 failed          ← the entire M3 suite is blind to it
```

The four W1 mutations in the correction's own battery all replaced `act_err_set`
or `act_err_get` **wholesale**, so each broke the *core* channel too and was caught
by the core assertion. **No mutation attacked the three non-core channels.**

And the suite's own assertions for them are vacuous:

```php
t_eq(act_optional_error(), '',       'C11.2 B · nor on the column channel');
t_eq(act_optional_index_error(), '', 'C11.2 B · nor the index channel');
```

In `C11.2`, workspace A only ever generated a **core** error. The other three
channels were empty when checked, so those assertions pass whether or not the
channels are keyed. They assert the absence of something that was never there.

My Attack 5 probe — which generates a real **column-migration** error and a real
**write** error in A before switching — does catch it. The teeth exist; they are
not in the suite.

## 🟠 X3 · MEDIUM · The #11 suite's "workspace switching" never switches workspace

```php
//  Genuine in-process workspace switching (§3). …
$enter = function ($w) use ($WS) { $GLOBALS['__db_epoch'] = $WS[$w]; };
```

It assigns a number. It never calls `db(true)`, never changes `SQLITE_PATH` or
`DB_NAME`, and never touches a second database. **Tenant A and Tenant B are the
same database with a different integer**, and the comment claiming otherwise is
false.

W1's fix happens to survive the real test — I proved that above, on both engines,
with genuinely separate databases. But that is luck rather than evidence: the
suite verified the fix against a *model* of workspace switching, and §3 of the
brief asked specifically for the real thing.

---

# VERDICT

**M3 CORRECTION #11 — NOT ACCEPTED**

W1's *behaviour* is correct and survives a much harder attack than the one that
shipped with it. But:

* **X1** — the writer reports `STORED` when nothing was written, on both engines.
  This is a live defect in the contract the correction created.
* **X2** — the correction's central claim ("four channels, all keyed") is not held
  by any test; a mutation that keys only one survives the entire M3 suite.
* **X3** — the evidence for W1 was produced against a simulation of workspace
  switching, not workspace switching.

Two of the three are **evidence** defects rather than product defects, which is
the same pattern as every previous round: the fix was right, the proof was weaker
than it claimed to be.

Open across M3: **H1, H3, S2, S3, X1, X2, X3** (and **U2** as a documented cost).
