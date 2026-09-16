# Phase 3 · M3 CORRECTION #9 — ADVERSARIAL AUDIT (second pass, after the U1 fix)

The first pass found **U1** — `act_optional_state()` spending the DDL budget it
exists to report on — and it was fixed inside this correction. This pass attacks
the fix.

**One finding. It is the mirror image of U1.**

---

## What held up

| | |
|---|---|
| Mutations | **10 attempted, 10 caught, 0 survivors**, clean baselines on both engines |
| New suite | **61 / 0 on SQLite, 61 / 0 on MariaDB** |
| Full regression | **SQLite 10 148 / 0 · MariaDB 10 149 / 0** |
| `U1-M1` | restoring the attempt-on-observe behaviour is **caught** — the rule is held by a test, not by a comment |
| `C9.8` | six polls during an outage, twice the whole budget, and the repair still succeeds |

T1 and T2 are fixed and each is held by a mutation that is caught, `T1-M1` on the
engine where it actually means something.

---

## 🟡 V1 · LOW–MEDIUM · The observer can no longer tell "cannot" from "not yet"

U1 was *asking does too much*. The fix made asking do nothing but read. The
consequence is that asking now says **too little to act on**.

**Probe — the column is absent but perfectly creatable, and nothing has attempted
yet (a fresh workspace, a page that has not written an audit row):**

```
V1 · column=false index=false column_error='' index_error='cond_key index: the column is unavailable'
V1 · …but a single attempt creates it: act_cond_column_ready() = true
FAIL  V1 · the observer can distinguish "unavailable" from "not attempted yet"

V2 · index=false index_error=''
V2 · …but one attempt creates it: act_cond_index_ready() = true
FAIL  V2 · the same for the index
```

A support screen reading this reports the feature **unavailable, with no reason
given**, about a structure that one ordinary call would create. Worse, the index's
message asserts *"the column is unavailable"* — a claim about a failure that has
not happened and may never happen.

`act_optional_state()` exists for diagnosis. An answer that cannot be acted on —
*is it broken, or has nobody asked yet?* — does not diagnose anything.

**The shape, which is now familiar:** a **two-valued** answer is being given to a
**three-valued** question.

| | |
|---|---|
| **READY** | the structure is there |
| **FAILED** | it was attempted and could not be made |
| **NOT ATTEMPTED** | nothing has tried yet — and nothing may be wrong at all |

Correction #9 collapsed the third state into the second. That is the same error as
"a supported type is not an openable record" and "a usable column is not an indexed
column", one level up: **a structure that is absent is not a structure that failed.**

The remedy is small and does not reopen U1: report the third state explicitly from
what is already known — whether an attempt has been recorded for this workspace —
without attempting anything.

---

## The pattern worth naming, because it has now happened four times in one correction

| | |
|---|---|
| the index check spent the **column's** budget | fixed |
| an **already-present** structure spent a budget slot | fixed |
| an **observer** spent the **repairer's** budget (U1) | fixed |
| an observer cannot distinguish **absent** from **failed** (V1) | **open** |

The first three were all "who may spend the budget". Writing that rule into the
code was right. But each fix was made to the *instance in front of me*, and the
fourth case is what the third fix created. The correction's own lesson —
**state the rule, do not patch the instance** — applies to the rule about rules.

---

## Verdict

The three defects this correction was raised for and found — **T1**, **T2** and
**U1** — are fixed, proven behaviourally, and held by mutations that are caught,
with a clean full regression on both engines.

**V1 is a new, lower-severity defect introduced by the U1 fix.** It costs no audit
row and no correctness, only the ability to diagnose — which is the entire purpose
of the function it affects.

**Recommended status: M3 CORRECTION #9 NOT ACCEPTED**, pending **V1**, with **U2**
(the unindexed lookup cost) still to be recorded rather than fixed.

Open across M3: **H1, H3, S2, S3, V1** (and **U2** as a documented cost).
