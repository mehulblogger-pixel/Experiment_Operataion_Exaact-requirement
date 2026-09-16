# Phase 3 · M3 CORRECTION #10 — ADVERSARIAL AUDIT

**Status caveat:** correction #10's mutation battery was still running and the full
regression and completion report are not yet produced. This audit covers the
implementation and its tests, not the completion evidence.

**Two findings. Both are the same defect V1 was, in the two places correction #10
did not look.**

---

## What held up

The three-state model is real where it was applied. `NOT_ATTEMPTED` is never
inferred from absence: every `FAILED` assertion in the new suite is preceded by an
attempt proved to have happened, and every `NOT_ATTEMPTED` by an attempt count
proved to be zero. `C10.2` closes the loop by showing that one real attempt
succeeds — so `FAILED` there would have been a lie about a structure one call from
existing. 46/0 on both engines; the battery's first verdict, `V1-M1` (absent
treated as FAILED), is **caught**.

Correction #10 also correctly made the attempt ledger **per workspace epoch**, so
one company's failed migration cannot make another company's read `FAILED`. I wrote
a mutation for that myself (`V1-M9`).

---

## 🟠 W1 · MEDIUM · The core error channel is NOT per-workspace

The ledger was made per workspace. `act_last_error()` — the channel that reports
whether the **core audit row** was written, the thing correction #8 exists to
protect — was not.

**Probe — a forced core INSERT failure in one workspace, then a switch to another:**

```
W1 · workspace A: act_log=0 act_last_error()='SQLSTATE[23000] … W1 FORCED'
W1 · workspace B, which has failed at nothing: act_last_error()='SQLSTATE[23000] … W1 FORCED'
FAIL  W1 · a core failure in one workspace is not reported in another
```

This application switches workspace **in process** — "Log in as", provisioning, a
cron sweeping tenants. So a support screen or diagnostic opened against company B
reports company A's failure, verbatim, including A's database error text.

Two things are wrong at once: a **false diagnosis** in B, and a small **cross-tenant
bleed** of an error string out of A. It is the exact defect `V1-M9` guards against
for the ledger, left standing on the more important channel.

## 🟡 W2 · LOW–MEDIUM · The WRITER still answers three situations with one boolean

Correction #10 gave the **observer** three states. `act_set_cond_key()` — the
**writer** — still returns a plain `true`/`false`.

```
W2 · stored=true · column-never-attempted=false · write-genuinely-failed=false
FAIL  W2 · the writer can distinguish "never attempted" from "attempted and failed"
```

A caller that wants to know whether metadata was stored, and if not why, gets the
same `false` for *"nothing has tried to create the column yet"* and *"the write was
attempted and failed"*. That is V1's sentence word for word, one layer out:

> **ABSENT IS NOT FAILED.**

Lower severity than W1 because no caller currently branches on it — but that is an
argument about today's callers, not about the contract.

---

## The pattern, stated plainly, because it is now the fifth instance

| | |
|---|---|
| the index check spent the column's budget | fixed in #9 |
| an already-present structure spent a slot | fixed in #9 |
| an observer spent the repairer's budget (U1) | fixed in #9 |
| an observer could not tell absent from failed (V1) | fixed in #10 |
| the **writer** cannot tell absent from failed (W2) | **open** |
| the **core error channel** is not per-workspace (W1) | **open** |

Each correction has fixed **the instance that was reported** and left the same
sentence standing elsewhere. Correction #10 even identified the rule — *absent is
not failed* — wrote it into a comment, and then applied it to exactly one function.

The reliable move, on the evidence of six rounds, is: when a rule is found,
**enumerate every place that answers the same question** and fix them together, or
record explicitly why each remaining one is out of scope. Both W1 and W2 sit in the
same file as the fix.

---

## Verdict

V1 is genuinely fixed for the observer, with honest evidence and a mutation that
catches its restoration.

But the correction named a rule and applied it once. **W1 is a cross-workspace
diagnostic defect in the channel that reports core audit integrity** — the most
important channel in this subsystem — and **W2 is the same two-valued answer in the
writer.**

**Recommended status: M3 CORRECTION #10 NOT ACCEPTED**, pending **W1** (which
should be treated as the priority: it is tenant bleed, however small) and **W2**.

Open across M3: **H1, H3, S2, S3, W1, W2** (and **U2** as a documented cost).
