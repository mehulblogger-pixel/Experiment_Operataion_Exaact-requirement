# Phase 3 · M3 CORRECTION #4 — TEST RESULTS

## New suite — `tests/test_p3m3c4_gate.php`

**129 assertions, 0 failed, on both engines.**

| Section | Assertions | What it holds |
|---|---:|---|
| **C4.1 · the predicate matrix, all four entities** | 88 | for **each** of hiring request, offer, salary and requisition: eligible when valid + entitled + visible (informational **and** actionable); **denied when the workspace is unlicensed — the E1 fix**; denied for a null subject, a zero id, a negative id, a missing `id` key and a non-array; denied when inactive; denied for an unknown and for a blank entity; and a cross-tenant subject resolving to `TENANT_MISMATCH`. Every denied case also asserts **no recipient, nothing sent, nothing logged as sent** |
| **C4.2 · scope and segregation, honestly scoped** | 8 | a hiring request denies an out-of-branch recipient by name; offer, salary and requisition **carry no branch** — stated, not faked; segregation denies *asking* the requestor to approve their own request and keeps them out of the recipient list, while telling them their own outcome stays allowed by design |
| **C4.3 · delegation, on more than one entity** | 10 | valid / expired / revoked delegate and inactive delegator on **both** hiring request and offer, plus an unlicensed workspace notifying neither approver nor delegate |
| **C4.4 · the eligible side effects, exactly** | 16 | for all four entities: **exactly one** recipient, the right address, no cross-branch or requestor leakage, and the canonical raiser written to **exactly once** |
| **C4.5 · impersonation, regression only** | 6 | user, scope and permissions restored, under an exception and under nesting — `appr_as_user()` not redesigned |

### The matrix, as run

| | HIRING_REQUEST | OFFER | SALARY | REQUISITION |
|---|---|---|---|---|
| A valid + entitled + visible | eligible | eligible | eligible | eligible |
| B unlicensed | denied | **denied** | **denied** | **denied** |
| C/D null · no id · zero · negative · non-array | denied | denied | denied | denied |
| E inactive subject | denied | denied | denied | denied |
| F cross-tenant subject | denied | denied | denied | denied |
| G out of scope | denied | *n/a — no branch* | *n/a* | *n/a* |
| H segregation | denied (actionable) | *n/a* | *n/a* | *n/a* |
| I unknown / blank entity | denied | denied | denied | denied |
| J valid delegate | eligible | eligible | — | — |
| K / L / M expired · revoked · inactive delegator | denied | denied | — | — |

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **9518 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** (fresh `exaact_m3h`) | **9519 passed, 0 failed** |

| Suite | Result | Covers |
|---|---|---|
| **M3 correction #4** (`p3m3c4_gate`) | **129 / 0** | E1, E2, the matrix |
| M3 correction #3 (`p3m3c3_raiser`) | **53 / 0** | D1, D2, D3 |
| M3 correction #2 (`p3m3c2_identity`) | **50 / 0** | C1, C2 |
| M3 correction (`p3m3c_notify`) | **68 / 0** | F1, F2, F3 |
| M3 original (`p3m3_sla`) | 176 / 0 | SLA, escalation, inbox |
| M1 approval (`p3m1_approval`) | 114 / 0 | |
| M2 matrix, delegation, M2 correction (`p3m2_matrix`) | 111 / 0 | |
| Phase-6 approvals — offer / salary / requisition (`recruit_approval`) | 25 / 0 | |
| Offer approval context (`offer_appr_dept`) | 2 / 0 | |
| M4 hiring request (`m4_hiring_request`) | 78 / 0 | |
| M4 correction (`m4_correction`) | 107 / 0 | |
| Recruitment admin (`hiring_admin`) | 11 / 0 | |

Operations, Reporting, Quality, Money, Workforce, Marketplace and the Recruitment
Command Centre are inside the whole-suite figures.

**No test was skipped, weakened, deleted or re-baselined, and no existing suite
needed altering for this correction** — the fix made three entities stricter and
nothing that previously passed depended on the hole.

## Side-effect testing (§11), both directions

Every **denied** case asserts: no recipient produced · nothing sent · nothing
logged as sent · the reason reported accurately · no approval-state change.
Every **eligible** case asserts: the exact recipient set (one address, the right
one) · no same-name, cross-branch or requestor leakage · the raiser notified
exactly once, for all four entities.
