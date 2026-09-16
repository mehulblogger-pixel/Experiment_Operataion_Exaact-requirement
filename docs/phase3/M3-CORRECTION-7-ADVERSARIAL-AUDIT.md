# Phase 3 · M3 CORRECTION #7 — ADVERSARIAL AUDIT

Scope: commits `fefc398` … `15839a8` — the idempotent permanent-condition rule.

Method: three probes, aimed at the parts of the change with the widest blast
radius and at the boundary of "the same condition".

**Three findings. One is HIGH and affects every module in the application.**

---

## 🔴 S1 · HIGH · The whole activity spine goes silent if one column is missing

To hold the condition key, correction #7 added `cond_key` to `activities` — and,
crucially, **to the INSERT statement inside `act_log()`**, the single function every
module in this application uses to write its audit trail.

Before this correction that INSERT referenced only columns from the table's own
`CREATE TABLE`, which always exist. It now depends on a column added by
`ensure_column()`. That is a new dependency, and it fails badly:

```php
// lib/db.php — ensure_column()
catch (Throwable $e) {
    …
    if (stripos($m, 'duplicate column') === false && …) throw $e;   // RE-THROWS
}

// lib/activity.php — act_log()
try { act_migrate(); … INSERT … }
catch (Throwable $e) { return 0; }                                  // SWALLOWS
```

A failed `ALTER` (no privilege, a lock or timeout on a large `activities` table, a
half-applied deploy) is re-thrown by `ensure_column`, swallowed by `act_log`, and
reported to nobody.

**Probe — the column removed, nothing else changed:**

```
P1 · control · act_log() writes normally                                    ok
P1 · with cond_key absent: act_log returned 0 and 0, rows written: 0
FAIL  P1 · the WHOLE APPLICATION can still write its audit trail            (want 2, got 0)
```

A CRM note and a quotation e-mail — nothing to do with approvals — **write nothing
and report success to their callers.** `act_migrate()` also latches its
`static $doneAt` before doing the work, so it never retries: the silence is
permanent for the life of the process.

This is a self-inflicted regression of exactly the kind this sequence keeps
finding: a change made for one module, landed in shared machinery, with a failure
mode nobody observes. The remedy is that the condition key must never be a
precondition for writing an audit row — write the row first and carry the key
separately, or build the column list from what the table actually has.

## 🟠 S2 · MEDIUM · A different DECISION on the same dead chain is suppressed

The identity is *event class · entity · entity id · chain · step · reason*. It does
**not** include the decision result.

```
after the first refused APPROVAL:                    1 row
after a refused REJECTION on the same chain:         1 row
FAIL  P2 · a refused REJECTION is recorded — a different decision is a different event
```

The audit records that an **approval** could not be notified. That somebody
subsequently tried to **reject** the same request, and that this too went
unnotified, is lost entirely.

The *condition* is unchanged, which is why the key matches — but the *event* is
"decision X was not notified", and X changed. A person took a second, different
action and the trail does not show it. The boundary of "the same thing happening
again" was drawn one field too wide.

## 🟡 S3 · LOW–MEDIUM · A condition that clears and returns is never recorded again

```
after the record was restored and deleted AGAIN:      1 row (was 1)
FAIL  P3 · a SECOND disappearance is a new occurrence, not the same one
```

The key never expires. Once a permanent condition has been recorded for a chain,
that chain can never record it again — even though the condition genuinely
**cleared** (the record existed again) and then genuinely **recurred**.

§7 of the brief asks that a genuinely new condition may create a new event. A
condition that goes away and comes back is new by any ordinary reading; the
implementation treats "has this ever been true?" as "is this still the same
occurrence?". Rated below S2 only because nothing in the application restores a
deleted record today — it would take an operator restore or a re-used id.

## My §7 test matrix had two missing columns

`C7.6` proves a new event is still recorded when the **event class** differs
(escalation after reminder) and when the **reason** differs. It does not test a
different **decision result** (S2) or a condition that **cleared and recurred**
(S3). I built a matrix for "what counts as different" and filled in two of its four
columns — the same shape of gap that E1 and G1 were, one level up.

---

## What held up

* **Mutations 12/12 caught**, clean baseline, no anchor misses, including the two I
  added for over-classification and for the scheduler's `rule_id`.
* **K1 closed on the instrument that found it** — ten identical refusals give one
  row on all four entities, and the first observation is still recorded.
* **H2 closed with the clock advanced between runs**, each run proved to have
  actually executed.
* **Full regression green on both engines** (10 025 / 10 026), so S1's failure mode
  is genuinely invisible to the suite — which is the point of the finding, not a
  defence of it.

## Verdict

The rule itself is right, and K1, K2 and H2 are behaviourally fixed. But the
correction put a new precondition into shared machinery whose failure is silent and
total, and drew the boundary of "the same condition" wide enough to swallow two
things that are not repetitions.

**Recommended status: M3 CORRECTION #7 NOT ACCEPTED**, pending **S1** (which should
be fixed before anything else in M3, since it is not confined to approvals),
**S2**, and **S3**.

Open across M3: **H1, H3, S1, S2, S3**.
