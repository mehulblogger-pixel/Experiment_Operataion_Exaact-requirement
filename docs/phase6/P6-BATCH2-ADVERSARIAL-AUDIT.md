# Phase 6 · Batch 2 — Adversarial audit

*One complete attack on the finished batch, performed after implementation. Not a
second opinion on the tests — an attempt to defeat what they passed.*

**Rule:** no product code was changed during the attack. One material defect was
found; it was fixed with the smallest targeted change, and the affected test, the
complete regression on both engines and the mutation battery were re-run. No
further correction rounds were manufactured.

---

## The method

Six questions, each aimed at the way this batch could be technically correct and
still not protect anything:

1. Is the conversion the only writer, now that it has moved?
2. Does the transaction hold when somebody else already opened one?
3. Does the seat gate refuse the very person who just took the seat?
4. Can the repair be turned into a merge engine?
5. Does the detection report tell an administrator enough to act?
6. Does the battery prove what it claims, or does it agree with the code?

---

## Finding A — **MATERIAL** · a reported failure that left a committed row

`rcv_convert()` opened its own transaction with a `try/catch` that set
`$tx = false` when the call threw. PDO throws when a transaction is **already
open**, so inside a caller's transaction the conversion silently ran
**non-transactionally** — and its failure path then did `if ($tx) rollBack()`,
which did nothing.

**Proved by running it.** A caller opens a transaction; the conversion's third
write fails; the caller commits its own work:

```
result: ok=false  code=RACE_LOST
in a transaction still? true
AFTER THE CALLER COMMITTED → orphan inspectors 'Partial%': 1
```

**A reported FAILURE left a committed orphan team member** — both halves of the
owner's "no false success / no false failure" rule broken at once, and the exact
orphan this batch exists to remove.

**Severity: material.** No caller wraps the conversion today, so it is not a live
defect; it is a loaded gun pointed at the next caller, and the failure mode is
silent.

**Smallest targeted fix.** Participate in a borrowed transaction honestly:

```php
$own = !db()->inTransaction();
if ($own) $tx = db()->beginTransaction();
…
if ($own && $tx) db()->commit();
// on failure:
if ($tx) db()->rollBack();
if (!$own) throw $e;          // the caller owns the unwind, and is told
```

When the transaction is not ours we may neither commit nor roll it back, so the
failure is **re-thrown** and the caller unwinds. That is the honest contract.

**And the probe that locks it in:** `A17–A20` — the failure reaches the caller,
the caller's transaction is still its own, and after the caller rolls back
**nothing was committed**.

**Re-verified after the fix:** battery 78/0, SQLite 12 460/0, MariaDB 12 467/0,
mutation battery re-run in full.

## Finding B — no second writer, confirmed

Swept every writer of the conversion:

| Writer | Verdict |
|---|---|
| `lib/recruit.php` — inside the transaction | the only one ✔ |
| `lib/ops.php:1099` — `team_member_create()` | creates a team member, never a conversion ✔ |
| `lib/ops.php:4114` — the People form | creates a team member directly; **no duplicate check — recorded, deferred** |
| `lib/trace_audit.php:49` — master-only diagnostic seed | namespaced; creates no conversion ✔ |
| DEMO seeds | create team members, not conversions ✔ |

**`W1` now covers seeds too**, because mutant M15 showed the original exclusion
would have excused exactly the shape of writer that defeated Batch 1.

## Finding C — the seat gate does not refuse its own hire

A worry worth testing rather than reasoning about: the route consumes the seat on
the stage move, then the conversion asks M6's gate again. On a **one-seat**
requirement, would the gate refuse the person who just took it?

```
one-seat requirement · stage moved to ACCEPTED · then convert
   → ok=true  code=CONVERTED
```

**No.** `rexec_block_reason()` is a read-only check, and seat consumption is
Phase 4/M6's business, untouched. Recorded because a plausible-sounding
double-consumption would have broken every hire on a single-vacancy requirement,
which is the commonest shape in this product.

## Finding D — the repair cannot be turned into a merge engine

Attacked directly: two intact groups, and two records deliberately sharing a
mobile number and an e-mail address but never linked.

| Attack | Result |
|---|---|
| Live repair over intact groups | both untouched |
| Two records sharing a mobile **and** an e-mail | **not merged** |
| Dry run | changes nothing |

The repair reads **only** the activity spine's record of which applications a
link operation named together. No name, no e-mail, no mobile, no similarity. If
that record does not prove a previous state, it reports and stops.

**Mutant M13 makes it reach for a shared e-mail. `G8e` kills it.**

## Finding E — **RESIDUAL, recorded** · the report cannot tell two causes apart

`CONVERTED_NO_LEDGER` covers both *"the marketplace add-on is off"* (owner
decision BD2's **STATE B**) and *"this hire pre-dates Batch 2"*. Both are
legitimate and neither is a fault, but an administrator cannot tell which from
the report alone.

**Not fixed**, because distinguishing them needs a fact the system does not
record, and inventing one to make a report tidier is not a change this gate
allows. Recorded so it is a known shape rather than a surprise.

## Finding F — **RESIDUAL, recorded** · the People form still has no duplicate check

`ops_inspectors()` creates a team member directly from a form, with no check of
any kind. That is **R20 territory** — open by owner decision BD3 — and outside
this batch. It cannot produce the duplication Batch 2 is about (one application,
two staff records), because it is not a conversion.

## Finding G — the battery was attacked, and lost four times

Five mutants survived the first run. **Four were defective probes, not defective
code:**

| | The probe's flaw |
|---|---|
| **M10** | nothing converted as an unauthorised actor |
| **M17** | nothing checked whether a process reporting success named a record that exists |
| **M15** | `W1` excused seeds — the exact exclusion that let a second writer through in Batch 1 |
| **M13** | nothing tested that an **unrelated** group survives a **live** repair |

The fifth was an **equivalent mutant**, proved rather than assumed: M13's first
formulation deleted the "still where the record says" check, and for an intact
group the repair then rewrites the very reference the rows already carry — no
observable change. It was re-aimed at what owner decision BD4 actually forbids,
reaching for a similarity, which is both non-equivalent and the real failure mode.

> **The finding that matters about the evidence:** a battery at 14/18 looked like
> good code with a few gaps. It was good code with four probes that would have
> passed whatever the code did. That is the second time in Phase 6 this has been
> the shape of the result, and it is the argument for running mutations at all.

---

## Verdict

| | |
|---|---|
| Material defects found | **1** — Finding A |
| Fixed, smallest change | ✔ |
| Affected test added and green | ✔ (`A17–A20`) |
| Complete regression re-run, both engines | ✔ |
| Mutation battery re-run in full | ✔ |
| Residuals recorded, not fixed | **2** — Findings E, F |
| Correction rounds manufactured | **none** |

**The attack is closed.** Nothing found here reopens the batch's scope, changes
its architecture, touches Batch 1, or decides **R20** or **Q1–Q18**.
