# ADR-003 — The configured process decides the candidate screen

**Status:** DECIDED · 2026-09-26 · Narrows the UX-B6 recommendation for this screen.

## The complaint

> "We are having hiring workflow and I think so hardcoded overview / pipeline /
> review / interviews / documents and so on… so is this not confusing the thing."

It was. The candidate screen carried **two navigations for one journey**:

- the configured workflow strip — *"Hiring workflow — Executive Recruitment.
  Stage 1 of 8 · Requisition"* — with eight named stages; and
- directly beneath it, a fixed row of eight tabs: Overview, Pipeline, Interviews,
  Documents, Offer, Recruitment, CV, Timeline.

Both describe the same hiring. Neither knew about the other. They did not even
agree on words: the stage said *Management Interview*, the tab said *Interviews*;
the *Compensation* stage had no tab at all; the *Search* stage had nowhere to work.

## The fault underneath

The **workflow is configurable** — a company can run six stages or eighteen —
and the **screen was not**. A company that configured a short process still saw
all eight tabs, in an order its process does not follow, including ones it never
uses. The configuration was honest and the surface ignored it: the same shape of
bug as ADR-002, where a terminology setting worked in the engine and was
discarded on the busiest screen.

## The decision

A stage already carries a **kind** — `step`, `gate`, `interview`, `offer`,
`terminal` — so the mapping from *where this candidate is* to *what this screen
should open* existed and was simply never asked for.

`recruitpipe_screen_plan($cand)` now answers it, and the screen obeys:

1. The panel the **current stage** needs opens by default, through the tab
   engine's existing `data-tabs-order`. No new mechanism.
2. The screen says **why** — *"Open on Interviews because this hire is at
   Management Interview. Every other section is still one click away."*
3. Panels that map one-to-one onto a stage kind and that this process never uses
   are not offered.

## The two powers are granted on different evidence

This is the load-bearing part of the decision.

| Power | Risk | Granted when |
|---|---|---|
| **Ordering** | None — every tab is still there, one click away, and a person who picks another keeps it | Any candidate on a resolvable process |
| **Hiding** | Real — it takes a capability off the screen | Only when the process is **locked in** for this hire (`pipeline_id`) |

`recruitpipe_cand_state()` resolves a **default** pipeline for candidates that
were never put on one. Treating "a pipeline resolved" as "this company chose
this process" would have quietly stripped tabs from every legacy record on the
strength of a fallback nobody picked. That was caught in testing and is now the
rule: hiding requires the process to have actually been locked in, which happens
on the first real move through it.

Two further limits, for the same reason:

- **Only `Interviews` and `Offer` can ever be hidden.** Overview, CV, Timeline,
  Documents and the rest are the record itself — its history and its files — not
  steps. A process that does not mention them does not forbid them.
- **A tab the plan has never heard of is kept.** A panel added later is never
  silently dropped by a rule written before it existed.

## What this does NOT change

No permission, role, lifecycle, table, column or route. Nothing is deleted and
no data moves. A workspace that has configured nothing sees exactly what it saw.

## Why not merge the two strips into one visual control

Considered and rejected for now. The stage strip answers *where is this hire*
and the tabs answer *what am I looking at*; collapsing them would make moving a
candidate and reading their file the same gesture, which is how a stage gets
advanced by accident. Making the tabs **follow** the stages removes the
contradiction without removing the distinction.

## Verification

- `tests/test_candidate_screen_plan.php` — 114 assertions, most of them on what
  must still be **shown**, including an invariant asserted over all sixteen plan
  shapes: the tab that opens is always one the screen actually draws.
- Five mutations; four caught. The fifth — removing the guard that stops a focus
  naming an absent tab — fails nothing, because that guard is unreachable given
  how focus is derived today. It is kept as defence in depth and the reasoning
  is written in the code rather than left implied.
- Verified live: with the candidate at *Management Interview* the screen opens on
  **Interviews**; at *Offer* on **Offer**; at a gate stage on the **Pipeline**
  panel. A closed candidate is not forced anywhere.
- Full regression: SQLite 14,838 · MariaDB 14,841.
