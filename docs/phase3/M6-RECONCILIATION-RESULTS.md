# PHASE 3 · M6 — RECONCILIATION RESULTS

One controlled fixture, every figure derived independently from the source
records, then compared against the service, the dashboard and the export.

## The fixture

A **10-seat** approved hiring request → one requisition → one recruiter → **12
candidates**: 4 joined, 2 offered, 3 in process (interview / shortlisted /
received), 3 lost (rejected / withdrawn / declined), and 2 interview rounds
booked for one of them.

## The numbers

| Figure | Expected from the records | Service | Dashboard | Export | Result |
|---|---|---|---|---|---|
| Authorised seats | 10 | 10 | 10 (`posted`) | — | **PASS** |
| Filled | 4 | 4 | 4 (`recruited`) | — | **PASS** |
| Remaining seats | **6** | 6 | 6 (`open`) | — | **PASS** |
| Candidates | 12 | 12 | — | 12 | **PASS** |
| Active candidates | 9 | 9 | — | — | **PASS** |
| In process | 5 (the two offered are still in play) | 5 | — | — | **PASS** |
| Lost | 3 | 3 | — | — | **PASS** |
| Offers | 2 | 2 | — | — | **PASS** |
| Joins | 4 | 4 | — | — | **PASS** |
| Interviews | **2 rounds**, not 1 candidate | 2 | — | — | **PASS** |
| Requisition status | `PARTIALLY_FILLED` | — | still **open demand** | — | **PASS** |
| Allocation vs approval | 10 = 10 | — | — | — | **PASS** |
| Unallocated approved seats | 0 | 0 | — | — | **PASS** |

Two completeness checks that no individual number can hide behind:

- **every candidate is in exactly one bucket** — joined + active + lost = 12;
- **candidate count is NOT the filled count** — 12 people, 4 seats taken.

## Why the export cannot drift from the screen

The CSV export and the command centre share **one** WHERE builder
(`rcc_cand_where` / `rcc_req_where`), and the candidate scope rule is defined
once in `rasg_cand_scope()` and read by both the dashboard and the recruiter
workload counter. A test asserts the sharing, so the two cannot be separated
without failing.

## Scope

Every figure is bounded by the scope of whoever asks: a branch-B user sees none
of branch A's requirements, candidates, recruiters or workload, on the dashboard,
in the workload counter and in the export alike.
