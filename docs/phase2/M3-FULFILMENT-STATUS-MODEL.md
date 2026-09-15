# Phase 2 · M3 — Fulfilment & Status Model

## 1. Three questions, three answers (§13, §14)

They are routinely collapsed into one, which is how the original defect arose.

| Dimension | Answers | Where it lives |
|---|---|---|
| **Pipeline stage** | where a *candidate* got to | `candidates.stage` |
| **Fulfilment** | what a *seat* came to | derived from candidate rows + `cancelled_qty` |
| **Requisition status** | what the *whole requirement* is | `requisitions.status` |

```
Candidate: Interview round 2   →   Fulfilment: open       →   Requisition: partly filled
Candidate: Accepted            →   Fulfilment: filled     →   Requisition: partly filled
(nobody)                       →   Fulfilment: cancelled  →   Requisition: filled
```

## 2. Pipeline stages — existing, unchanged

Audited first. `CAND_STAGES` already provides the full progression and M3 added
nothing to it:

`RECEIVED` · `SUBMITTED` · `SHORTLISTED` · `INTERVIEW` · `OFFERED` ·
`OFFER_DECLINED` · `HOLD` · `REJECTED` · `ACCEPTED` · `WITHDRAWN`

The configurable pipeline engine (`recruit_pipelines` / `recruit_stages`) is
untouched — it remains the only pipeline engine.

## 3. How stages map to a seat

| Stages | Meaning for the seat |
|---|---|
| `ACCEPTED` | **filled** |
| `RECEIVED`, `SUBMITTED`, `SHORTLISTED`, `INTERVIEW`, `OFFERED`, `HOLD` | **in progress** — the seat is still open |
| `REJECTED`, `WITHDRAWN`, `OFFER_DECLINED` | **lost** — the seat is still open |

`filled` keeps the definition the Command Centre has always used, so no existing
number changes meaning.

## 4. Requisition statuses — reused, with one addition

Existing `REQ_STATUS`, all kept:

`OPEN` · `PROPOSED` · `OFFERED` · `HIRED` ("Hired (filled)") · `CLOSED` ·
`CANCELLED`

**Added:** `PARTIALLY_FILLED` — "Partly filled (still hiring)".

Only one status was added, because `HIRED` already means *filled* and renaming
it to `FULFILLED` would change the meaning of every stored row for no gain. The
list is a configurable lookup, so a workspace that has customised it gets the new
value added rather than its list reset.

## 5. Derivation rules

```
somebody set CLOSED / CANCELLED / DRAFT   →  leave it. a person decided.
filled + cancelled >= requested           →  HIRED
filled > 0                                →  PARTIALLY_FILLED
otherwise                                 →  leave it (OPEN / PROPOSED / OFFERED)
```

Two properties worth stating plainly:

- **Counting never overrules a person.** An explicitly closed or cancelled
  requisition stays that way even when a hire lands against it.
- **Counting never forces a status backwards** from an in-flight one when
  nothing is filled yet.

## 6. Cancellation is not a hire (§17)

`reqf_cancel($req, $qty, $reason)`:

- refuses zero, a negative number, and more than are actually open;
- records the count and the reason on the requisition;
- never touches a candidate, and never counts as `filled`;
- re-derives the status, so cancelling the last open seats completes the
  requirement without pretending anyone was hired.

```
Requested 10 — Joined 6 — Cancelled 4 — Remaining 0     →  fully accounted for
```

## 7. Explicit closure

`closed_at`, `closed_by` and `closure_reason` exist so that "we stopped looking"
is distinguishable from "everybody joined". M3 records the columns; a closure
*workflow* is Phase 3 (§45).

## 8. Audit (§36)

Status changes go through the existing `activity_log()`. **No second audit
system was created.**
