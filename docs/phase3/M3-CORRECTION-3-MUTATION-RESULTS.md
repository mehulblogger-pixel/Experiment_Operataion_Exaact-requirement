# Phase 3 · M3 CORRECTION #3 — MUTATION RESULTS

**28 mutations caught. 2 survived as a proved mutual redundancy. 5 anchors
superseded by the rewrite, with their intent re-covered.**

Every mutation ran against a **copy of `phpapp/` outside the repository**. A suite
that dies without printing `RESULT:` counts as a detection. Baseline: 0 failures.
Suites per mutation: `p3m` (M1 + M2 + M3 + all three corrections),
`recruit_approval`, `m4_`.

---

## D1 — canonical raiser identity

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| P01 | **the NAME-based lookup restored in place of canonical identity** | p3m:31 | **CAUGHT** |
| P02 | the OFFER's canonical raiser id ignored | p3m:1 | **CAUGHT** |
| P03 | the CHAIN's canonical raiser id ignored | p3m:3 | **CAUGHT** |
| P04 | the offer stops capturing its raiser at creation | p3m:2 | **CAUGHT** |
| P05 | an **arbitrary same-name user** accepted when the id does not resolve | p3m:3 | **CAUGHT** |
| P06 | tenant validation removed — the id trusted without a row | p3m:2 | **CAUGHT** |
| P07 | notification eligibility removed — identity alone authorizes | p3m:6 | **CAUGHT** |

P01 is the regression guard, and it detects **31** failures: putting a name back in
charge breaks a third of the approval suite now.

## D2 — audit integrity

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| P08 | the repetitive identity-missing audit row restored | p3m:1 | **CAUGHT** |
| P09 | a **dangling** audit reference written for an unsupported entity | p3m:4 | **CAUGHT** |

## D3 — accurate reasons

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| P10 | every eligibility failure collapses into `IDENTITY_UNRESOLVED` | p3m:1 | **CAUGHT** |
| P11 | the resolver reports every failure as `IDENTITY_UNRESOLVED` | p3m:1 | **CAUGHT** |

## C2 — re-run unchanged

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| N06 | **a missing entity falls back to `appr_can_act()`** (the C2 defect) | p3m:4 | **CAUGHT** |
| N07 | an unknown entity treated as a supported one | p3m:1 | **CAUGHT** |

## C1 — five anchors superseded, and why that is not a gap

`N01`–`N05` targeted correction #2's `appr_email_requester()` and
`appr_requester_user()` **verbatim**. Correction #3 rewrote both functions, so
those anchors no longer exist and the harness reported `ANCHOR-MISS (0)` — which is
the harness being honest, not a mutation passing.

Their intent is re-covered on the new code, and more thoroughly:

| Old mutation | Re-covered by |
|---|---|
| N01 old name-based `LIMIT 1` lookup | **P01** |
| N02 `requested_by_id` ignored, name wins | **P02 + P03 + P01** |
| N03 unresolved identity falls open to a name | **P05** |
| N04 cross-tenant id falls back to a namesake | **P05 + P06** |
| N05 security check after identity removed | **P07** |

## F1 / F2 / F3 — the full battery, re-run

| # | Protection removed | Result |
|---|---|---|
| M01 | the old role-recipient behaviour restored | **CAUGHT** (p3m:7) |
| M02 | notification visibility filtering removed | **CAUGHT** (p3m:8) |
| M03 | the guard dropped from notification eligibility | **CAUGHT** (p3m:9) |
| M04 | segregation removed | **CAUGHT** (p3m:17, m4_:3) |
| M05 | branch / entity visibility removed | **CAUGHT** (p3m:23) |
| M06 | entitlement removed from the approval guard | **CAUGHT** (p3m:8) |
| M07 | the escalation disclosure check removed | **CAUGHT** (p3m:1) |
| M08 | entitlement removed from delegation writes (F2) | **CAUGHT** (p3m:7) |
| M09 | unlimited post-escalation reminders restored (F3) | **CAUGHT** (p3m:1) |
| M10 | reminder duplicate protection removed | **CAUGHT** (p3m:9) |
| M12 | capability protection removed from approval configuration | **CAUGHT** (p3m:17) |
| M13 | delegation validity ignored | **CAUGHT** (p3m:6) |
| M14 | the active-delegator protection removed | **CAUGHT** (p3m:3) |
| M15 | the delegation branch leak restored | **CAUGHT** (p3m:2) |

## The two survivors — investigated again, not labelled

| # | Protection removed | Result |
|---|---|---|
| M11 | the active-status check in the **candidate** admission | **SURVIVED** |
| M16 | the active-status check in **`appr_may_be_asked()`** | **SURVIVED** |
| **both at once** | | **CAUGHT** (p3m:1) |

An inactive person is refused twice and either refusal alone suffices. Removing
**both** on the **current** source still fails on

> `M3.8 · an INACTIVE DELEGATE is not written to either — a mailbox is not a person`

so the protection is real and tested. Verified again here rather than carried over
from the previous milestone, because the code has changed twice since.

**A harness limitation, stated plainly:** the scripted `M17` in `m3c.py` only ever
removed *one* of the two checks — an old edit left its anchor pointing at the same
line as `M16`. The both-removed result above comes from a run that genuinely
applies both edits to the current source. The scripted `SURVIVED` line for M17 is
the harness's, not the code's, and is not reported as a result.
