# Phase 3 · M3 CORRECTION #10 — COMPLETION REPORT

**Scope: V1 only.** S2, S3, H1, H3 untouched; U2 recorded, not altered. No M4 work.

---

## 1 · What V1 was

Correction #9 made observation read-only — right — and then answered a
**three-valued** question with a boolean:

```
column=false  column_error=''  index_error='cond_key index: the column is unavailable'
```

with **no attempt having occurred**, against a column one ordinary call would
create. Two faults: a structure merely **absent** reported like one that **failed**,
and a message asserting a failure that had not happened.

## 2 · The fix

| State | Established by |
|---|---|
| `READY` | a cheap metadata read |
| `FAILED` | **recorded evidence of a real attempt** |
| `NOT_ATTEMPTED` | an attempt count of zero |

Derived **independently** for column and index. **FAILED is never inferred from
absence.**

The attempt ledger — a `static` buried in the retry logic, which is why
"attempted" and "never attempted" were indistinguishable from outside — is now
observable state, keyed **per workspace epoch**, written only from inside a real
attempt. An observer may read it and may never write it, preserving U1.

Messages state facts: `Ready` · `Not attempted` · `Failed: <recorded reason>`.

## 3 · Evidence

| | |
|---|---|
| **New suite `p3m3c10_state`** | **46 / 0 on SQLite · 46 / 0 on MariaDB** |
| **Full regression · SQLite** | **10 196 passed, 0 failed** |
| **Full regression · MariaDB 10.11.14** | **10 197 passed, 0 failed** |
| **Mutations** | **9 attempted, 9 caught, 0 survivors** |

Two mutations are mine: **V1-M8** plants the literal V1 string into the message
layer (§11 attacks the states, nothing attacks the *words*, and half of V1 was a
message); **V1-M9** shares the ledger across workspaces, because an observable
ledger that is not per-tenant turns a diagnostic improvement into cross-tenant
bleed.

## 4 · §14 acceptance criteria

All met: V1 reproduced; all three states genuine; absent ≠ failed; column and index
independent; observation completely read-only, consuming no budget and performing
no DDL; a real failed migration produces `FAILED` and a successful one `READY`;
retry from `FAILED` to `READY` works; messages reflect actual state; the V1
false-green specifically prevented; corrections #9, S1, T1, T2, U1 green; both
engines green; epoch state restored by the suite.

## 5 · What this correction did NOT do — and what it exposed

**S2, S3, H1, H3 remain open.** **U2** remains a documented cost.

The adversarial audit of this correction found two **pre-existing** defects that
the new state model made visible by contrast:

* **W1** — every error channel in the spine is a process global while the
  application switches workspace in process, so a failure in company A is returned
  in company B.
* **W2** — `act_set_cond_key()` returns one boolean for *"never attempted"* and
  *"attempted and failed"* — V1's sentence on the writer side.

Both were **deliberately not fixed here.** Unlike U1 — which correction #9
introduced, and therefore fixed inside itself — W1 and W2 predate this correction,
and §13 scopes it to V1 only. They are fixed by **correction #11**.

---

**PHASE 3 — M3 CORRECTION #10 COMPLETE**

M3 itself remains **NOT ACCEPTED**.
