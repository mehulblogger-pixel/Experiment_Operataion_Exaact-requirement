# Phase 2 · M3 — The Multi-Vacancy Model

**Ten vacancies now behave as ten vacancies.**

## 1. The defect

The hire action set `status='HIRED'` on the requisition the moment **one**
candidate was taken on, whatever the quantity. Reproduced by driving three hires
against a requisition for five:

```
BEFORE
before any hire         status=OPEN    hired_inspector_id=null
after 1st person joins  status=HIRED   hired_inspector_id=1     ← closed, with 4 seats open
after 2nd person joins  status=HIRED   hired_inspector_id=2
after 3rd person joins  status=HIRED   hired_inspector_id=3
```

Two things were wrong, not one:

1. A terminal status after the first hire.
2. `hired_inspector_id` is a single column **overwritten by every hire**, so it
   named the *most recent* person — not, as earlier documentation of ours said,
   the first. Hires 1 and 2 appeared nowhere on the requisition.

## 2. What was already right

This mattered more than the defect, because it decided the shape of the fix.

| Already there | Meaning |
|---|---|
| `requisitions.quantity` | how many were asked for |
| `candidates.requisition_id` | which requirement a person is against |
| `candidates.stage` | where that person got to |
| `candidates.inspector_id` | set when they actually joined the workforce |

**Every individual hire was already recorded — one candidate row each.** The
Recruitment Command Centre was already computing `filled` and `open` correctly
from them. Only the requisition-level summary was wrong.

So M3 added **no candidate table and no hire table** (§11), and derives the
summary from records that already exist.

## 3. The one thing that genuinely could not be represented

A vacancy **cancelled with nobody in it** — "we needed 10, hired 6, dropped the
other 4". No candidate row can stand for that, so it is a count on the
requisition: `cancelled_qty`, with a reason.

That is the entire schema addition, plus three columns recording an explicit
closure.

## 4. Three dimensions, kept apart (§14)

They answer different questions and move independently:

```
Pipeline stage       where a CANDIDATE got to        "Interview round 2"
Fulfilment           what a SEAT came to             filled / open / cancelled
Requisition status   what the WHOLE REQUIREMENT is   "Partly filled"
```

A candidate at *Interview round 2* has filled nothing. A requisition with three
of five filled is *partly filled* while two people are still interviewing.

## 5. The arithmetic (§15)

Stated once, in `reqf_counts()`:

```
requested    = quantity  (blank or zero means one person, as it always has)
filled       = candidates at ACCEPTED
joined       = those of them who have a workforce record
in_progress  = candidates at RECEIVED, SUBMITTED, SHORTLISTED, INTERVIEW, OFFERED, HOLD
lost         = candidates at REJECTED, WITHDRAWN, OFFER_DECLINED
cancelled    = cancelled_qty

remaining    = max(0, requested − filled − cancelled)
```

**Somebody in the pipeline never reduces what is remaining.** §15 is explicit
that two people selected but not yet joined must not disappear from the
requirement, and a mutation that subtracts `in_progress` fails the suite.

`filled` deliberately keeps the definition the Command Centre has always used
(`stage='ACCEPTED'`), so no existing number changes meaning.

## 6. Status is recomputed wherever the counts change

> **Corrected after the post-implementation audit.** This section originally read
> "Status is now a consequence, not a moment". That was true only on the hire
> path: the first cut of M3 recomputed the status in one place, so accepting a
> candidate without creating a workforce record, reversing a hire, editing the
> quantity, or moving a candidate between requisitions all left the stored status
> stale — a requisition could still read `HIRED` with a seat genuinely open. The
> recomputation now sits at every point a change passes through. See
> `M3-AUDIT.md`.

`reqf_derive_status()`:

| Condition | Status |
|---|---|
| somebody set `CLOSED`, `CANCELLED` or `DRAFT` | **left alone** — a person decided |
| `filled + cancelled >= requested` | `HIRED` — the existing status, labelled "Hired (filled)" |
| `filled > 0` | `PARTIALLY_FILLED` — the one genuinely new status |
| nothing filled yet | left alone — `OPEN` / `PROPOSED` / `OFFERED` stay |

`PARTIALLY_FILLED` was **added to** the existing `REQ_STATUS` list, not
substituted for anything, so no stored status changed meaning (§13). Workspaces
that have customised the list get the new value added to theirs too.

## 7. After the fix

```
AFTER
before any hire         status=OPEN              requested=5 filled=0 REMAINING=5
after 1st person joins  status=PARTIALLY_FILLED  requested=5 filled=1 REMAINING=4
after 2nd person joins  status=PARTIALLY_FILLED  requested=5 filled=2 REMAINING=3
after 3rd person joins  status=PARTIALLY_FILLED  requested=5 filled=3 REMAINING=2

cancel the 2 nobody filled:
                        status=HIRED   filled=3 cancelled=2 remaining=0
cancel one more:        refused — "Only 0 vacancies are still open."

summary: 10 requested — 6 filled — 4 cancelled — 0 remaining
```

The §17 example works exactly as written, and cancellation is never recorded as
a hire.

## 8. `hired_inspector_id` (§12)

**Preserved, not dropped.** It has only four consumers — the schema, the
requisition list join, the detail screen's cost figures, and the write — so
keeping it costs nothing. It still names the most recent hire, which is what it
has always held; it is simply no longer the only record of who was hired.

Every hire is listed individually on the requisition, from the candidate rows.

## 9. What a person sees (§33)

The requisition now opens with the position in one line:

```
   5          3           2            0            2
Requested   Filled   In progress   Cancelled    Remaining

5 requested — 3 filled — 2 in progress — 2 remaining      [ 2 ] [ Why? ] No longer needed
```

No internal ids, no implementation terminology. The cancel action appears only
when something is actually open, and only for a coordinator.
