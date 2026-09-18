# PHASE 3 · M6 — END-TO-END STATE MATRIX

Every combination that can produce an invalid business state, with the service
that refuses it, the refusal, and what the database and the audit must show.
No status is added by M6; every name below already existed.

## A · Hiring request × execution

| Hiring request | Re-approval | Requisition may be raised | Recruitment may execute | Controlled by |
|---|---|---|---|---|
| `DRAFT` | — | **BLOCKED** | **BLOCKED** | `hreq_to_requisition` / `hreq_is_executable` |
| `SUBMITTED` | — | **BLOCKED** | **BLOCKED** | same |
| `UNDER_REVIEW` | — | **BLOCKED** | **BLOCKED** | same |
| `APPROVED` | `NONE` | **ALLOWED** | **ALLOWED** | — |
| `APPROVED` | `REQUIRED` | **BLOCKED** | **BLOCKED** | `HREQ_REAPPROVAL_BLOCKS` |
| `APPROVED` | `IN_PROGRESS` | **BLOCKED** | **BLOCKED** | same |
| `APPROVED` | `REAPPROVED` | **ALLOWED** | **ALLOWED** | — |
| `APPROVED` | `REJECTED` | **BLOCKED** | **BLOCKED** | same |
| `REJECTED` | — | **BLOCKED** | **BLOCKED** | `hreq_is_executable` |
| `CANCELLED` | — | **BLOCKED** | **BLOCKED** | same |

**Expected on BLOCKED:** no row written, the business sentence returned, the
candidate/requisition unchanged, and **no audit row claiming success**.

## B · Requisition state × action (the M6 gate)

`rexec_block_reason($requisitionId, $action, $candidateId)`

| Requisition | ADVANCE | INTERVIEW | OFFER | JOIN | Refusal |
|---|---|---|---|---|---|
| `OPEN` | ALLOW | ALLOW | ALLOW | ALLOW while a seat remains | — |
| `PROPOSED` | ALLOW | ALLOW | ALLOW | ALLOW while a seat remains | — |
| `OFFERED` | ALLOW | ALLOW | ALLOW | ALLOW while a seat remains | — |
| `PARTIALLY_FILLED` | ALLOW | ALLOW | ALLOW | ALLOW while a seat remains | — |
| `HIRED` | ALLOW | ALLOW | ALLOW | **BLOCKED** (no seat) | *"All N approved positions … have already been filled."* |
| `CLOSED` | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | *"That requirement is closed, so you cannot …"* |
| `CANCELLED` | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | *"That requirement is cancelled, so you cannot …"* |
| *(empty / unreadable)* | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | fails closed |
| *(no requisition — ADR-001)* | ALLOW | ALLOW | ALLOW | ALLOW | no approval, no ceiling |
| *(id not in this workspace)* | **BLOCKED** | **BLOCKED** | **BLOCKED** | **BLOCKED** | *"That requirement no longer exists."* |

## C · Seats × joining

| Authorised | Filled | Cancelled | Remaining | A further joining |
|---|---|---|---|---|
| 10 | 0 | 0 | 10 | ALLOW |
| 10 | 4 | 0 | 6 | ALLOW — and the requirement is `PARTIALLY_FILLED`, **not** `HIRED` |
| 10 | 10 | 0 | 0 | **BLOCKED** |
| 10 | 3 | 7 | 0 | **BLOCKED** — cancelled vacancies are not free seats |
| 2 | 2 | 0 | 0 | **BLOCKED**, and a joining written past the gate is **reverted**, audited, and left out of the ledger |
| 2 | 1 | 0 | 1 | ALLOW — and the person already in a seat does not compete with themselves |

| 1 | 0 | 0 | 1 | **two simultaneous claims** → both may be **REFUSED**, and the seat stays available for the next valid transaction (policy **I21c**) — never over-filled, never taken from an incumbent |

**Expected on the revert:** the candidate returns to their prior stage,
`reqf_sync()` restores the requirement's status, an activity row records
*"Joining reverted — no approved seat remained"*, and the joining is **not**
counted anywhere. Where both claims in a dead heat are refused, the requirement's
remaining-seat count must return to what it was, so the capacity is released for a
subsequent attempt rather than consumed by the refusal.

## D · Candidate stage × execution

| Stage | Advance | Interview | Offer | Join | Close (reject/withdraw/decline/hold) |
|---|---|---|---|---|---|
| `RECEIVED` · `SUBMITTED` · `SHORTLISTED` · `INTERVIEW` | gate | gate | gate | gate | **always allowed** |
| `OFFERED` | gate | gate | gate | gate | **always allowed** |
| `HOLD` | gate | gate | gate | gate | **always allowed** |
| `ACCEPTED` | gate | gate | gate | already seated | allowed |
| `REJECTED` · `WITHDRAWN` · `OFFER_DECLINED` | settled | settled | settled | settled | — |

*"Always allowed"* is deliberate and is asserted: a pending re-approval must
never trap a candidate. Closing somebody out is not spending authority.

## E · Recruiter assignment × lifecycle (M5, unchanged by M6)

| Condition | Result | Code |
|---|---|---|
| requisition `HIRED` / `CLOSED` / `CANCELLED` | **BLOCKED** | `BAD_STATE` |
| hiring request awaiting re-approval | **BLOCKED** | `M4_BLOCKED` |
| recruiter deactivated or unknown | **BLOCKED** | `RECRUITER_INACTIVE` / `RECRUITER_UNKNOWN` |
| recruiter outside the branch | **BLOCKED** | `RECRUITER_OUT_OF_SCOPE` |
| candidate moving to a requirement in another branch | **BLOCKED** | `RECRUITER_OUT_OF_SCOPE` (asked of the **destination**) |
| the screen's owner has moved | **BLOCKED** | `STALE` |
| a concurrent loser | **BLOCKED** | `LOST_RACE` |
| malformed value (array, `"abc"`, `1e3`) | **BLOCKED** | `BAD_VALUE` |

## F · Cross-cutting

| Condition | Every action above |
|---|---|
| another tenant's id | **BLOCKED** — structurally absent; the gate reports *"no longer exists"* |
| another branch | **BLOCKED** — `scope_allows` / `hreq_in_scope` |
| module not licensed | **BLOCKED** — `can()` asks `licence_blocks` **before** the master flag |
| role below the recruitment write band | **BLOCKED** — `NO_PERMISSION`, and the answer does not vary with what was guessed |
