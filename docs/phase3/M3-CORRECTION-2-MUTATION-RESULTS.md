# Phase 3 · M3 CORRECTION #2 — MUTATION RESULTS

**22 mutations. 20 CAUGHT. 2 survived as a proved mutual redundancy.**

Seven new for C1 and C2, plus the whole M3-correction battery of fifteen re-run
against the corrected source. Every mutation ran against a **copy of `phpapp/`
outside the repository**. A suite that dies without printing `RESULT:` counts as a
detection. Baseline: 0 failures.

---

## C1 — canonical requester identity

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| N01 | **the OLD name-based, `LIMIT 1` lookup restored** (the defect verbatim) | p3m:12 | **CAUGHT** |
| N02 | `requested_by_id` ignored — the first matching **name** wins | p3m:11 | **CAUGHT** |
| N03 | an **unresolved** identity falls open to a name match | p3m:5 | **CAUGHT** |
| N04 | a **cross-tenant** id falls back to a local namesake | p3m:2 | **CAUGHT** |
| N05 | the security check after identity removed (identity alone authorizes) | p3m:2 | **CAUGHT** |

N01 is the regression guard: it restores the audited defect in its original shape,
so it cannot quietly return. N02–N04 cover the three ways an identity can be
faked rather than resolved; N05 proves the brief's rule that **knowing who
somebody is does not authorize telling them.**

## C2 — fail-closed entity resolution

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| N06 | **a missing entity falls back to `appr_can_act()`** (the defect verbatim) | p3m:4 | **CAUGHT** |
| N07 | an **unknown** entity treated as a supported one | p3m:1 | **CAUGHT** |

## The M3-correction battery, re-run against the corrected source

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| M01 | the old role-recipient behaviour restored | p3m:7 | **CAUGHT** |
| M02 | notification visibility filtering removed entirely | p3m:8 | **CAUGHT** |
| M03 | the guard dropped from notification eligibility | p3m:9 | **CAUGHT** |
| M04 | segregation removed | p3m:17, m4_:3 | **CAUGHT** |
| M05 | branch / entity visibility removed | p3m:23 | **CAUGHT** |
| M06 | entitlement removed from the approval guard | p3m:8 | **CAUGHT** |
| M07 | the escalation disclosure check removed | p3m:1 | **CAUGHT** |
| M08 | entitlement removed from delegation writes (F2) | p3m:7 | **CAUGHT** |
| M09 | unlimited post-escalation reminders restored (F3) | p3m:1 | **CAUGHT** |
| M10 | reminder duplicate protection removed | p3m:9 | **CAUGHT** |
| M12 | capability protection removed from approval configuration | p3m:17 | **CAUGHT** |
| M13 | delegation validity ignored | p3m:6 | **CAUGHT** |
| M14 | the active-delegator protection removed | p3m:3 | **CAUGHT** |
| M15 | the delegation branch leak restored | p3m:2 | **CAUGHT** |

Several detect **more** failures than before correction #2 (M05 went 21 → 23,
M02 6 → 8), because the new suite adds assertions that the same protections hold.

## The two survivors — the same proof, re-verified on the corrected source

| # | Protection removed | Result |
|---|---|---|
| M11 | the active-status check in the **candidate** admission | **SURVIVED** |
| M16 | the active-status check in **`appr_may_be_asked()`** | **SURVIVED** |
| **M17** | **both of them at once** | **CAUGHT** (p3m:1) |

An inactive person is refused twice, and either refusal alone suffices — so
removing one leaves the other doing the work. M17 is the evidence that the
protection is real and tested: with **both** removed the suite fails on

> `M3.8 · an INACTIVE DELEGATE is not written to either — a mailbox is not a person`

**A harness defect found and corrected while doing this.** The scripted M17 in the
re-run reported SURVIVED — because an earlier edit had left its anchor pointing at
the *same single line as M16*, so it was never removing both. It is reported here
from a run that genuinely applies both edits to the **current** source, verified
after correction #2, not carried over from the earlier milestone. Reporting the
scripted line would have been reporting a result the harness never produced.
