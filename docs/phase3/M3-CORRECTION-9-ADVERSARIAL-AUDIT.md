# Phase 3 · M3 CORRECTION #9 — ADVERSARIAL AUDIT

**Status caveat, stated first.** Correction #9 is **not finished**. The full
regression on both engines has not been run since the last change, and the four
required documents (audit, test results, mutation results, completion report) are
not written. This audit therefore covers the **implementation and its tests**, not
the completion evidence. It is not a completion verdict.

---

## What held up

**T1 is genuinely fixed, and the proof is the mutation.** `T1-M1` restores the old
updatable-view fixture and is **CAUGHT on MariaDB** — the engine where it
previously proved nothing. That is the direct demonstration that the new test
fails when the core INSERT is no longer forced to fail.

**The failing operation is named, not inferred.** The trigger carries a marker
string, and the assertion is that `act_last_error()` *contains that marker* — so
"the core INSERT failed" is read from the error, with the row count as
corroboration only. The column and index are asserted healthy first, so neither
can be the operation that failed.

**The fixtures leave no residue.** After a full MariaDB run, `activities` carries
6 indexes — the 63 fill indexes are gone, the trigger is gone, the column and its
index are back.

---

## 🟠 U1 · MEDIUM–LOW · Observing the state consumes the budget the repair needs

`act_optional_state()` exists so that a caller can **ask** whether optional
metadata is usable — a health check, a support screen, a diagnostic. Each call
also **spends one of the three DDL attempts** reserved for repairing it.

**Probe — four health-check polls during an outage, then the schema is repaired:**

```
P1 · after three OBSERVATIONS during an outage, column ready = true      ok
P2 · after four polls during the outage, can the column still be repaired? false
FAIL  P2 · a health check must not exhaust the budget the repair needs
```

`P1` passes only because the column was still physically present, so the cheap
probe short-circuits. `P2` removes it, and four observations leave the feature
permanently unrepairable for that workspace in that process.

**This is the third instance of one family inside this single correction:**

| | |
|---|---|
| the **index** check spent the **column's** budget | found and fixed mid-correction |
| a structure **already present** still spent a budget slot | found and fixed mid-correction (the cheap probe) |
| an **observer** spends the **repairer's** budget | **still open** |

The rule the first two fixes were reaching for was never stated, so the third case
was missed: **only an attempt to repair may consume the repair budget.** Asking a
question is not an attempt.

Practical exposure is limited — a web request is a fresh process, so the budget
resets — but a CLI worker, a cron run or any long-lived process that polls can
disarm itself, and `act_optional_state()` was added precisely so that polling is
possible.

## 🟡 U2 · LOW · "Only performance is degraded" is true but under-stated

With the index absent, `appr_condition_seen()` runs
`SELECT id FROM activities WHERE cond_key = ?` against an **unindexed** column —
a full table scan, once per permanent-condition audit write, on a table that grows
without bound.

The claim in the correction #9 tests — *"only indexing/performance is degraded"* —
is accurate, and the trade-off is still the right one (a slow correct answer beats
a fast wrong one). But it should be recorded as *a full scan per write on the
application's largest table*, not as an unquantified aside, so that the operational
cost of running without the index is a known quantity rather than a discovered one.

## Note, not a finding

The MariaDB index-blocker fills `activities` to its 64-key limit and the SQLite one
occupies the index name with a table; the T1 fixture installs a trigger that blocks
**every** insert into `activities`. All three are removed in `finally`, and the run
above confirms they are. But while installed, each has application-wide blast radius
inside the test process — worth keeping in mind if any of them is ever moved into a
path where an abort could skip the `finally`.

---

## Verdict

The two defects this correction was raised for — **T1** (evidence that proved the
wrong thing on the authoritative engine) and **T2** (a performance structure
treated as a correctness precondition) — are fixed, and both are now held by
mutations that are caught.

But the correction introduced a third member of the very family T2 belonged to,
and left it open.

**Recommended status: M3 CORRECTION #9 NOT ACCEPTED**, pending **U1**, with **U2**
to be recorded rather than fixed — and pending the completion evidence that has not
yet been produced.

Open across M3: **H1, H3, S2, S3, U1** (and **U2** as a documented cost).
