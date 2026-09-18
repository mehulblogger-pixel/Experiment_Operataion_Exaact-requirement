# Phase 5 — Adversarial Audit

*Attacking the finished work rather than confirming it. No product code was
changed while attacking; the fixes came afterwards.*

## The question asked

Not "does it work?" but **"what would I have to do to make one of these figures
lie, and can I do it without touching the code?"**

Phase 5 is read-only, so the attack surface is not "can I break something" but
**"can I put data in front of it that makes it say something false?"** Every
attack below is a state no screen can create and every real database eventually
contains: a corrupted row, a half-finished import, a manual fix applied at 2am,
two servers whose clocks disagree.

## What held

| Attack | Result |
|---|---|
| A negative quantity on a requirement | No negative figure. The row rule reads a missing or non-positive quantity as one person |
| A negative `cancelled_qty` | Cancels nothing; cannot inflate the approved headcount |
| A negative `allocated_qty` on a promise | Cannot invent capacity, and still agrees with Phase 4 |
| An allocation belonging to **another** requirement | Lends no seats here |
| Working days across a year boundary | Counted correctly |
| A span of thirty-six years | **Refused** rather than counted day by day — a guard against a corrupt date spinning a loop, not a business rule |
| A backwards span | Reads negative rather than silently zero |
| A `hiring_request_id` pointing at nothing | `NO_TARGET`. Not a crash, not a date |
| A requirement id that does not exist | No target, counts as nothing |
| A metric key that does not exist | `NO DATA`. Not an error and not a zero |
| A recruitment metric without lineage | Impossible — none can be registered without one |
| Two ledger entries at the **same instant** either side of a hire | One answer, deterministically; no crash |
| The Command Centre route without entitlement | Gated before anything is read |

## What did not hold — and was fixed

### A ledger whose timestamps run backwards produced a NEGATIVE stage duration

Three events written at 10 June, 1 June, 20 June — the order a clock skew between
two application servers, an import, or a manual fix all produce. The engine
reported a step of **−9 days**.

A negative duration is not a fast stage. It is a broken record, and left in it
drags every average down and can make a stage look instantaneous.

**The fix, and the choice inside it.** The step is **excluded and counted**, not
clamped to zero. Clamping would silently convert a data fault into a plausible
measurement — the reader would see a stage that took no time and believe it.
Counting it puts the fault where somebody can go and fix the record, which is the
same choice this engine already makes for uncoded and reverted rows.

The sound steps around it are still measured: one bad row does not discard the
rest.

Now covered by probes **L5, L5b, L5c** and by mutations **P37** (restore the
negative) and **P38** (clamp instead of exclude).

## Limitations, stated rather than omitted

- **No concurrency evidence, because there is nothing to race.** Phase 5 adds no
  compare-and-swap, no compensator and no contended write. Its only writes are
  ledger entries appended beside a change that has already committed. A failed
  ledger write is a failure to *observe*, never a failure to transact.
- **The `out_of_order` and `uncoded` counts are reported by the engine but are
  not yet surfaced on a screen.** They are available to any caller; putting them
  in front of a user is a deliberate follow-up, not an oversight, because the
  right place for them is a data-quality view that does not exist yet.
- **Pre-Phase-5 ledger rows carry no stage code.** They are reported as
  `uncoded` rather than guessed at from their display text. No backfill is
  attempted, because the display text genuinely is ambiguous — that ambiguity is
  the defect the codes exist to remove, and inventing codes from it would bake
  the ambiguity in while looking like a fix.
- **`lib/recruit_assign.php` (M5) still spells `'ACCEPTED'` as a literal** rather
  than reading `REQF_FILLED_STAGES`. There is no behavioural difference today —
  they are the same single value — so it was not changed inside a locked
  milestone. Recorded as a tidiness item.
