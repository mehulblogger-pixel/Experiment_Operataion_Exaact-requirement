# Phase 2 · M3 — Post-Implementation Audit

*An adversarial review of the M3 work, carried out after it shipped and before
anything further was built. The aim was to break it, not to confirm it.*

**Five findings. Two are real defects in shipped code, one is a hazard I
introduced while fixing a non-problem, and one is an overclaim in my own
documentation.** All are fixed and pinned.

## A1 — The status only recomputed on the hire path *(critical)*

**What I shipped:** `reqf_sync()` was called in exactly one place — inside the
`make_inspector` branch of the hire action.

**What that missed:** four other paths change how many seats are filled. Proven
by driving each one:

| Path | What happened | Should have been |
|---|---|---|
| A candidate accepted **without** "make inspector" | stayed `OPEN` with a seat filled | `PARTIALLY_FILLED` |
| A hire **reversed** (`ACCEPTED` → `REJECTED`) | stayed **`HIRED` with a seat genuinely open** | `PARTIALLY_FILLED` |
| The **quantity edited** down to what was filled | stayed `PARTIALLY_FILLED` | `HIRED` |
| A candidate **moved** to another requisition | neither side updated | both re-derived |

The second is the same class of defect M3 exists to fix, reached by a different
route.

**Root cause:** I fixed the write at one site instead of putting the
recomputation where every mutation passes. That is the opposite of the
one-chokepoint discipline used in M14, and I should have applied it here.

**Fix:** the recomputation now sits at the points every change passes through —
the candidate stage-change handler (which every stage move goes through, beside
the `candidate_events` write), the requisition save (quantity), and the
candidate save on both create and move, syncing **both** requisitions when one
is moved.

**Note:** the *counts* were always correct — they are derived live. It was the
stored `status` that went stale, so the numbers on screen were right while the
status badge and any status-based filter or report were wrong.

## A2 — A requisition could not come back from "partly filled" *(moderate)*

With every hire reversed, a requisition sat for ever at `PARTIALLY_FILLED` with
nobody in it. My rule "nothing filled yet → leave the status alone" was written
to protect `OPEN` / `PROPOSED` / `OFFERED`, and it stranded the two statuses
counting itself had set.

**Fix:** a requisition that counting put into `HIRED` or `PARTIALLY_FILLED`
returns to `OPEN` when the hires behind it are gone. A status a *person* set is
still never overruled.

## A3 — Cancelled vacancies could exceed the requirement *(moderate)*

Cancel 6 of 10, then edit the quantity to 2, and the screen read
**"2 requested — 6 cancelled"**. `remaining` was clamped so nothing went
negative, but the figures were not something anybody could act on.

**Fix:** cancelled is clamped to what was asked for.

## A4 — A cache I added, and then removed *(hazard, self-inflicted)*

The audit measured `reqf_counts()` at **0.11 ms** per call, with no N+1 anywhere
(the requisition list does not use it). I nevertheless added a per-request memo,
and it immediately produced a worse failure than the one it solved: any write
that did not remember to drop it served **stale counts**.

**Resolution: removed.** Correct numbers matter more than a tenth of a
millisecond, and a cache that can silently go stale is a poor trade for a saving
that was never needed. The genuine waste — `reqf_sync()` computing the counts
twice inside one log line — was fixed directly.

This one is recorded in full because the lesson is mine: I optimised something I
had not shown to be slow, and made it less correct in the process.

## A5 — My own documentation overclaimed *(documentation)*

`M3-MULTI-VACANCY-MODEL.md` §6 was headed **"Status is now a consequence, not a
moment."** Given A1, that was true only on the hire path. Corrected, and the
heading now says what the code actually does.

## What the audit did NOT find

Checked and clear:

- **No N+1** — the requisition list and the Command Centre do not call the
  fulfilment counts; the Command Centre still uses its own single subquery.
- **Query cost is not a problem** — 4 counts + 1 row read, 0.11 ms.
- **A requisition with no quantity at all** correctly reads as one person.
- **An in-flight status** (`PROPOSED` / `OFFERED`) with nothing filled is left
  alone, as intended.
- **Route protection** — the cancel route runs the same branch-scope gate and
  permission bar as every other requisition route, before reading an id, and a
  mutation removing the gate fails the suite.
- **No `is_master()` override** anywhere in M3, so entitlement cannot be bypassed.
- **Idempotency** — running the migrations twice adds no column and changes no
  data.

## Testing after the audit

| | |
|---|---|
| `tests/test_m3_multi_vacancy.php` | **87 assertions** (68 + 19 for these findings) |
| Mutations | **12**, all caught |
| Full suite, SQLite | **8571 passed, 0 failed** |
| Full suite, MariaDB 10.11.14 | **8572 passed, 0 failed** |

The five audit fixes were each mutation-tested individually: removing the sync
from the stage change (2 failures), from the quantity edit (2), from the
candidate move (1); removing the return-to-open rule (1); and removing the
cancelled clamp (1).

## Honest summary

M3's central claim held up: ten vacancies do behave as ten vacancies, the
counting is right, and nothing was merged or rewritten that should not have
been. But the status — the thing a manager actually looks at — was only correct
on one of five paths, and a requisition could still read `HIRED` with a seat
open. That is the defect the milestone was named for, and it survived in a
narrower form because I fixed a symptom at one call site rather than putting the
rule where every change passes.

It is fixed now, and pinned by mutations rather than by assertion alone.
