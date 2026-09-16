# Phase 3 · M3 CORRECTION — TEST RESULTS

## New suite — `tests/test_p3m3c_notify.php`

**68 assertions, 0 failed, on both engines.**

Every F1 assertion is made against the **actual recipient list**, and the decisive
ones again against what actually landed in `email_log` — never against a count of
notification events.

| Section | What it holds | Brief |
|---|---|---|
| **MC1 · role path** | the same-branch role approver **is** notified; the other-branch holder of the same role is **not**; and that agrees with the model — their queue is empty, the engine refuses their decision, and **no e-mail naming the request reaches the other branch** when the scheduler runs | A, B |
| **MC2 · segregation** | **both halves proved**: the requestor's own decision is refused at the engine, *and* they are not asked by e-mail to approve it; the person who can decide still is | C |
| **MC3 · named user** | a named eligible approver is notified; a named approver outside the branch scope is **not** — being named is not a bypass — and the engine refuses them too | D, E |
| **MC4 · delegation** | valid delegate notified; expired, inactive-delegator, out-of-branch and revoked all **not**; reactivating restores it; M2 semantics untouched | F, G, H, I |
| **MC5 · entitlement & tenant** | an unlicensed workspace notifies **nobody at all**, and notifies again once licensed; a foreign user id resolves to no recipient | J, K |
| **MC6 · escalation** | the configured contact **is** told, the message says plainly it grants no authority, and they still cannot approve; an escalation contact outside the branch is **not** told what it is about | L |
| **MC7 · lifecycle** | cancelled → no actionable reminder; completed → no reminder; one reminder to one recipient, and **ten further runs send no duplicate** | M, N, O |
| **MC8 · F2 entitlement** | recruitment ON + administrator → allowed; OFF → create, edit, revoke and deactivate all refused **at the write**, with nothing written; coordinator refused; **a delegate cannot revoke their own delegation**; a delegation naming a non-existent user grants nothing; re-enabling permits it again; the route remains gated as the second boundary | 1–11 |
| **MC9 · F3 reminders** | a reminder before escalation; the SLA passes and it escalates; the escalation goes out; **one hundred further runs send the approver nothing**, and the escalation is not re-sent; the step is still PENDING, reads **Escalated**, is still counted on the dashboard, and was neither approved nor rejected; the escalation recipient still gains no authority while the real approver keeps theirs; cancelling stops everything; a revoked delegation cannot resurrect a stopped reminder | 1–15 |

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **9286 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** (fresh `exaact_m3e`) | **9287 passed, 0 failed** |

| Suite | Result |
|---|---|
| M3 correction (`p3m3c_notify`) | 68 / 0 (SQLite **and** MariaDB) |
| M3 SLA (`p3m3_sla`) | 176 / 0 |
| M1 approval (`p3m1_approval`) | 114 / 0 |
| M2 matrix & delegation, incl. the M2 correction (`p3m2_matrix`) | 111 / 0 |
| Phase-6 approvals — offer / salary / requisition (`recruit_approval`) | 25 / 0 |
| Offer approval context (`offer_appr_dept`) | 2 / 0 |
| M4 hiring request (`m4_hiring_request`) | 78 / 0 |
| M4 correction (`m4_correction`) | 107 / 0 |
| Recruitment admin (`hiring_admin`) | 11 / 0 |

Operations, Reporting, Quality, Money, Workforce, Marketplace and the Recruitment
Command Centre are inside the whole-suite figures above.

**No test was skipped, weakened, deleted, or had its expected result changed.** No
existing suite needed altering for this correction — the four suites corrected
during M3 itself were already truthful before this work began.

## On running the two engines

They were run **serially**. Running them concurrently in one checkout produces
five spurious failures, because `test_saas_clean_company` writes a real
`phpapp/tenants.php` that `test_tenant_signup` then trips over. That is a
pre-existing test-isolation defect recorded in the M3 completion report, not a
product defect, and not this correction's to fix.
