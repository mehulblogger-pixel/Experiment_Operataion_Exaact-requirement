# Phase 3 · M3 CORRECTION — MUTATION RESULTS

**17 mutations. 15 CAUGHT. 2 survived as a proved mutual redundancy.**

Every mutation ran against a **copy of `phpapp/` outside the repository**. A suite
that dies without printing `RESULT:` counts as a detection. Baseline: 0 failures.
Suites per mutation: `p3m` (M1 + M2 + M3 + M3-correction), `recruit_approval`, `m4_`.

## The twelve the brief required

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| M01 | **the OLD role-recipient behaviour restored** — every holder of the role, anywhere | p3m:5 | **CAUGHT** |
| M02 | notification visibility filtering removed entirely | p3m:6 | **CAUGHT** |
| M03 | the guard dropped from notification eligibility (`can_act` only) | p3m:6 | **CAUGHT** |
| M04 | segregation removed | p3m:17, m4_:3 | **CAUGHT** |
| M05 | branch / entity visibility removed | p3m:21 | **CAUGHT** |
| M06 | entitlement removed from the approval guard | p3m:8 | **CAUGHT** |
| M07 | the escalation disclosure check removed | p3m:1 | **CAUGHT** |
| M08 | **entitlement removed from delegation writes — the F2 defect restored** | p3m:7 | **CAUGHT** |
| M09 | **unlimited post-escalation reminders restored — the F3 defect restored** | p3m:1 | **CAUGHT** |
| M10 | reminder duplicate protection removed | p3m:9 | **CAUGHT** |
| M12 | capability protection removed from approval configuration | p3m:17 | **CAUGHT** |
| M13 | delegation validity (start and expiry) ignored | p3m:6 | **CAUGHT** |
| M14 | the active-delegator protection removed | p3m:3 | **CAUGHT** |
| M15 | the delegation branch leak restored | p3m:2 | **CAUGHT** |

**M01, M08 and M09 are the regression guards.** Each restores one of the three
audited defects in its original form, so none can quietly return.

The brief also asks for *"remove tenant isolation"*. **There is no line to remove.**
Tenant isolation here is structural — one database per tenant, no `tenant_id`
column — so a foreign user id simply does not exist and no recipient resolves.
That is asserted behaviourally (`MC5 K`) rather than mutated, and saying otherwise
would mean inventing a cross-database read to knock down.

## The two survivors, and the proof they are redundant rather than unguarded

| # | Protection removed | Result |
|---|---|---|
| M11 | the active-status check in the **candidate** admission | **SURVIVED** |
| M16 | the active-status check in **`appr_may_be_asked()`** | **SURVIVED** |
| M17 | **both of them at once** | **CAUGHT** (p3m:1) |

An inactive person is refused twice, in two places, and either refusal alone is
sufficient — so removing one leaves the other doing the work. That is a
redundancy, not a gap, and M17 is what proves it: with **both** removed the suite
fails on

> `M3.8 · an INACTIVE DELEGATE is not written to either — a mailbox is not a person`

So the protection is real, it is tested, and the two survivals are two ways of
spelling the same guarantee. The checks stay: the candidate one keeps a dead row
out of the list in the first place, the eligibility one is the line every path must
cross. Reporting either as "a protection that is not tested" would be false, and
excusing them without M17 would have been the kind of hand-waving this project
does not accept.
