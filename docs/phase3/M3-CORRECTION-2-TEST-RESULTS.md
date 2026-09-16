# Phase 3 · M3 CORRECTION #2 — TEST RESULTS

## New suite — `tests/test_p3m3c2_identity.php`

**47 assertions, 0 failed, on both engines.**

| Section | Assertions | What it holds | Brief |
|---|---:|---|---|
| **ID1 · a name is not an identity** | 7 | **the original audit reproduced** — three people called *Ravi Sharma*, with the namesakes created **first** so the old `LIMIT 1` lookup would have found them. The chain still records the display name; the identity comes from `requested_by_id`. The real requester is told; the namesake in another branch and the namesake with no recruitment permission are told **nothing**, and nothing naming the request reaches either | C1-1 · C1-2 · C1-3 · C1-4 |
| **ID2 · legacy text never overrides identity** | 4 | the chain's `requester` text is rewritten to name somebody else entirely; the canonical requester is still resolved, still told, and the person the **text** named is not | C1-5 |
| **ID3 · unresolvable identity fails closed** | 7 | `requested_by_id` of 0 → resolves to nobody, no e-mail, **and the silence is recorded on the activity spine**; an id from another tenant → nobody, nothing sent; an **inactive** requester → not written to | C1-6 · C1-7 · C1-8 |
| **ID4 · no identity means no guess** | 7 | an entity that records its raiser only as text (an offer) resolves to nobody and tells nobody, least of all a namesake, and it is recorded; **invoking the notifier directly** cannot make it deliver to a name; and identity alone does not authorize — an unlicensed workspace sends nothing | C1-9 · C1-10 |
| **ID5 · unknown or unresolvable entity fails closed** | 16 | a valid hiring request is normally visible and has recipients; **the decisive one — the approver genuinely passes `appr_can_act()`, and a missing entity is still not visible, so it does not fall through**; blank, unknown, invalid-id and cross-tenant references all deny; a step whose request has vanished notifies **nobody**; a known non-hiring entity is unchanged; cross-branch, cancelled and completed behaviours all preserved | C2-1 … C2-10 |
| **ID6 · impersonation, regression only** | 6 | the signed-in user, branch scope and permissions all restore, including under an exception and under nesting — `appr_as_user()` was not redesigned | §6 |

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **9333 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** (fresh `exaact_m3f`) | **9334 passed, 0 failed** |

| Suite | Result |
|---|---|
| M3 correction #2 (`p3m3c2_identity`) | **47 / 0** |
| M3 correction (`p3m3c_notify`) — the whole F1/F2/F3 matrix | **68 / 0** |
| M3 original (`p3m3_sla`) | 176 / 0 |
| M1 approval (`p3m1_approval`) | 114 / 0 |
| M2 matrix, delegation and the M2 correction (`p3m2_matrix`) | 111 / 0 |
| Phase-6 approvals — offer / salary / requisition (`recruit_approval`) | 25 / 0 |
| Offer approval context (`offer_appr_dept`) | 2 / 0 |
| M4 hiring request (`m4_hiring_request`) | 78 / 0 |
| M4 correction (`m4_correction`) | 107 / 0 |
| Recruitment admin (`hiring_admin`) | 11 / 0 |

Operations, Reporting, Quality, Money, Workforce, Marketplace and the Recruitment
Command Centre are inside the whole-suite figures.

**F1, F2 and F3 are re-proved in full** by `p3m3c_notify` (68/0): same-branch role
approver allowed, cross-branch denied, segregation denied, named eligible allowed,
named ineligible denied, valid/expired/revoked delegate, inactive delegator,
unlicensed workspace, cross-tenant, escalation visibility, cancelled, completed,
and duplicate scheduler runs.

**Nothing was skipped, weakened, deleted or re-baselined.**

## Two assertions in my own new suite were wrong, and are corrected

Reported rather than quietly fixed, because both were passing-or-failing for the
wrong reason:

1. **`appr_visible()` takes a `$user` but only half-honours it.** It passes the
   user to `appr_can_act()` and then evaluates `appr_guard()` for the **session**
   user. My first draft asserted visibility for somebody else directly against it
   and failed. Eligibility for another person is asked through
   `appr_may_be_asked()`, which impersonates — so the test now asks the question
   the product asks. *(The API's asymmetry is noted as an observation for the next
   audit; it is not a defect today, because the only caller that asks about
   somebody else goes through `appr_may_be_asked()`.)*
2. **The cross-branch assertion passed on the wrong clause** — the user I used was
   an `INSPECTOR`, so it failed at "does the step name you", never reaching branch
   scope. It now uses a **same-role** approver at the other branch, who passes
   can-act and must still be refused.
