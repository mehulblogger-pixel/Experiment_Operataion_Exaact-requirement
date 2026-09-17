# M3 CORRECTION #12 — MUTATION RESULTS

Mutations are applied to a **copy** of `phpapp/` in the scratchpad, never to the
repository. Suites run per mutation: `p3m`, `recruit_approval`, `m4_`,
`activity`. Baseline on the unmutated copy: **0 failures**.

**Attempted 7 · Caught 7 · Survived 0.**

These are the seven mutations written for Correction #12. Counts inherited from
Corrections #5–#11 are not repeated here and are not claimed as newly caught.

---

## X2 — four channels, four independent mutations

Each mutation makes **exactly one** channel ignore the workspace and leaves the
other three correctly scoped. A mutation counts as caught only when **that
channel's own assertion** fails, for the intended reason.

| mutation | verdict | the assertion that detected it |
|---|---|---|
| `M-X2-CORE` | **CAUGHT** | `C12.4 core · *** B cannot see A's core error ***` — want `''`, got `SQLSTATE[23000] … C12_CORE_A` |
| `M-X2-COLUMN` | **CAUGHT** | `C12.4 column · *** B cannot see A's column error ***` — want `''`, got `cond_key column: SQLSTATE[HY000] … no such table: activities` |
| `M-X2-INDEX` | **CAUGHT** | `C12.4 index · *** B cannot see A's index error ***` — want `''`, got `cond_key index: SQLSTATE[HY000] … there is already a table named idx_act_cond` |
| `M-X2-METADATA` | **CAUGHT** | `C12.4 write · *** B cannot see A's write error ***` — want `''`, got `cond_key: SQLSTATE[23000] … C12_WRITE_A` |

Each is also caught a second time by its own channel's step-4 assertion
(`and re-entering A yields neither B's nor A's stale <channel> error`). No
mutation is counted as caught because a *different* channel failed: the
mutated channel's own message is in the failure text in every row above.

`M-X2-CORE` additionally fails `C11.2 B · §5 · no trace of A reaches B`. That is
the #11 suite's core-channel assertion — the same channel, so it is corroboration,
not the basis of the claim; `C12.4 core` was verified to fail on its own
(52 passed, 2 failed, both `C12.4 core`).

### The pre-fix run, reported as it happened

On the first battery, run against the suite as originally written,
**`M-X2-COLUMN` and `M-X2-INDEX` SURVIVED** the whole M3 suite (0 failures).
They were not excused. The cause was found — the fixture repaired A before the
switch, emptying the channel it was about to test — the fixture was corrected,
and the battery re-run. This is recorded because the surviving mutation is the
evidence that those two channels were previously untested.

---

## X1 — `STORED` must mean persisted

| mutation | verdict | the assertion that detected it |
|---|---|---|
| `M-X1-A` — the read-back is removed; not throwing means `STORED` again (the original defect, restored) | **CAUGHT** | `C12.2 · *** a zero-row UPDATE is NOT STORED ***`, and `C12.2 · it reports FAILED` — want `FAILED`, got `STORED` |
| `M-X1-B` — a missing row is still called `STORED` (only the truncation check survives) | **CAUGHT** | same two assertions — want `FAILED`, got `STORED` |
| `M-X1-C` — `rowCount()` used instead of a read-back (the MariaDB trap the brief warned about) | **CAUGHT** | `C12.2 · with a reason that names the cause` |

`M-X1-C` is the important one: it encodes the *plausible wrong fix*. It is
caught on SQLite by the reason text, and it is the case the measured table in
the AUDIT shows would additionally misreport an idempotent re-write as `FAILED`
on MariaDB.
