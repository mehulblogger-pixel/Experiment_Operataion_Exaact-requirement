# PHASE 3 · M5 — ASSIGNMENT STATE MATRIX

Every state is tested. One state passing proves nothing about the others — that
assumption is what this milestone exists to remove.

## A · Requisition status

| Status | Assignment | Why | Probe |
|---|---|---|---|
| `OPEN` | **ALLOW** | live demand; somebody must carry it | D · OPEN |
| `PROPOSED` | **ALLOW** | live demand | D · PROPOSED |
| `OFFERED` | **ALLOW** | live demand | D · OFFERED |
| `PARTIALLY_FILLED` | **ALLOW** | still hiring — M3 gave this its own status precisely because a ten-seat requirement does not finish on the first hire | D · PARTIALLY_FILLED |
| `HIRED` | **DENY** `BAD_STATE` | the work is done; changing the owner now would rewrite who did it (**I12**) | D · HIRED |
| `CLOSED` | **DENY** `BAD_STATE` | finished | D · CLOSED |
| `CANCELLED` | **DENY** `BAD_STATE` | abandoned; the record keeps whoever carried it | D · CANCELLED |
| *(empty / unreadable)* | **DENY** `BAD_STATE` | an unreadable state is refused, never assumed safe | D · blank |

No status was added and none was changed. `docs/03-object-lifecycles.md` is
untouched by M5.

## B · Candidate stage

| Stage | Assignment | Why | Probe |
|---|---|---|---|
| `RECEIVED` · `SHORTLISTED` · `OFFERED` · `ACCEPTED` | **ALLOW** | the person is still being worked | D · candidate |
| `REJECTED` · `WITHDRAWN` · `OFFER_DECLINED` | **DENY** `BAD_STATE` | the outcome is settled; who chased them is history | D · candidate |

## C · Hiring-request state behind the requisition

The M4 boundary is asked about the hiring request behind the record, so the two
milestones cannot disagree.

| Hiring request | Assignment on its requisition | Probe |
|---|---|---|
| `APPROVED`, re-approval `NONE` | **ALLOW** | E2 |
| re-approval `REQUIRED` | **DENY** `M4_BLOCKED` | E4 |
| re-approval `IN_PROGRESS` | **DENY** `M4_BLOCKED` | E4 (same block list) |
| re-approval `REJECTED` | **DENY** `M4_BLOCKED` | E4 |
| re-approved (`REAPPROVED`) | **ALLOW** again | E6 |
| **a stale screen opened before the block** | **DENY** `M4_BLOCKED` | H1 |
| requisition with **no** hiring request (ADR-001 direct path) | **ALLOW** — there is no approval to respect | E (implicit: every other fixture) |

## D · The negative matrix, recorded

| Condition | Expected | Actual | Probe |
|---|---|---|---|
| correct tenant + scope + permission + active recruiter | ALLOW | `OK` | A1 |
| wrong tenant (an id from no row in this database) | DENY | `RECRUITER_UNKNOWN` | B4 |
| wrong branch — the person being assigned | DENY | `RECRUITER_OUT_OF_SCOPE` | B1 |
| wrong branch — the person doing the assigning | DENY | `OUT_OF_SCOPE` | B2 |
| no entitlement | DENY | `NO_ENTITLEMENT`, asked first, no master bypass | C1–C3 |
| no permission | DENY | `NO_PERMISSION` | B6 |
| inactive recruiter | DENY | `RECRUITER_INACTIVE` | B3 |
| invalid requisition state | DENY | `BAD_STATE` | D |
| M4-blocked request | DENY | `M4_BLOCKED` | E4 |
| stale / replayed action | DENY | `STALE` | G1–G4 |
| concurrent loser | DENY | `LOST_RACE` | C1–C3 |
| record does not exist | DENY | `NO_RECORD` | B5 |
| assigning the person who already holds it | no-op | `NO_CHANGE`, nothing written, nothing audited | A3, A4, M5 |
