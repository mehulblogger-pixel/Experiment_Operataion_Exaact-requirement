# Phase 3 · M1 — Testing

## 1. Environment

| | |
|---|---|
| PHP | 8.4.19 |
| Engine 1 | SQLite (bundled) |
| Engine 2 | **MariaDB 10.11.14** over TCP, into a **freshly created** database (`exaact_p3m1`), so every migration ran from nothing |
| New file | `tests/test_p3m1_approval.php` — **85 assertions** |

## 2. Results — both engines, identical source

| Engine | Whole suite | M1 suite | Skips |
|---|---|---|---|
| SQLite | **8902 passed, 0 failed** | 85 assertions, 0 failed | none introduced |
| **MariaDB 10.11.14** (authoritative) | **8903 passed, 0 failed** | **85 assertions, 0 failed** | none introduced |

The M1 suite was confirmed to have **actually executed on MariaDB**: all eleven
sections (`M1.0` … `M1.10`) appear in the MariaDB output and its assertions were
counted **inside that section**, not inferred from the total. MariaDB's total is
one higher than SQLite's because of a pre-existing engine-specific assertion,
unchanged by this work.

> These are **assertions, not test cases**. The 85 M1 assertions cover roughly 35
> scenarios.

No existing test was weakened, deleted or skipped. Two existing assertions were
**followed** through architectural change rather than worked around:
`hreq_decide()` stopped writing (the write moved to the one writer), and the
audit assertion moved from `activity_log(` to `act_log(` because the former does
not exist.

## 3. What the M1 suite covers

| Section | Holds in place |
|---|---|
| **M1.0 · reuse** | the Phase-6 engine is what M1 uses; **no second approval table**; the extension is one entity; **every column already existed**; `UNDER_REVIEW` is the existing status |
| **M1.1 · draft** | an authorized requestor may draft; a user without the capability may not; a draft is not executable and cannot convert |
| **M1.2 · submission** | a matching rule puts it `UNDER_REVIEW`; `approval_ref` records the chain; **the snapshot is taken**; the requestor is not overwritten; still not executable |
| **M1.3 · segregation** | the requestor **holds the approver role**, `appr_can_act()` says yes — and the engine still refuses, writing nothing, leaving the step pending |
| **M1.4 · branch scope** | a foreign-branch user holding the approver role is refused at the decision |
| **M1.5 · entitlement** | HR off refuses the approver; **refuses a master**; the inbox route asks the licence |
| **M1.6 · approval** | the chain callback moves the request; who and when recorded; **only now executable**; conversion permitted; history keeps decision, actor and reason |
| **M1.7 · rejection** | rejected is not executable, cannot convert, cannot be re-decided; replay refused; cancelled cannot be approved into life |
| **M1.8 · one decision** | the direct path stands aside for an open chain; with **no rule configured** the request stays `SUBMITTED` and M4's direct path works exactly as before |
| **M1.9 · audit** | rows **read back** from the real spine: raised, submitted, sent to approvers, `APPROVED via CHAIN`, rejection, cancellation, and the outcome recorded as an outcome |
| **M1.10 · write path** | `appr_act()` asks the guard **before its first write**; the guard asks entitlement, scope and segregation; **entitlement is asked first** |

## 4. Mutation testing

Twelve mutations, each applied to the live source, the four guard suites re-run,
the source restored from a byte-for-byte backup, and a clean baseline
re-confirmed. **Run against a copy of the application outside the repository**,
so a mutation can never be committed by accident.

| # | Protection deliberately removed | Expected | Actual | Verdict |
|---|---|---|---|---|
| 1 | entitlement check in `appr_guard()` | unlicensed workspace refuses | 6 failed | **CAUGHT** |
| 2 | approver identity (`appr_can_act()`) | only the step's approver may act | 1 failed | **CAUGHT** |
| 3 | branch scope in `appr_guard()` | foreign branch refused | 8 failed | **CAUGHT** |
| 4 | **self-approval check** | the requestor cannot decide their own request | 9 failed | **CAUGHT** |
| 5 | the whole guard removed from `appr_act()` | all three questions asked | 8 failed | **CAUGHT** |
| 6 | **the pre-M1 master bypass reintroduced** | a master does not escape the licence | 1 failed | **CAUGHT** |
| 7 | approval-state validation in the one writer | only `SUBMITTED`/`UNDER_REVIEW` decidable | 3 + 1 + 1 failed | **CAUGHT** |
| 8 | the executable-state check | nothing recruits before approval | 5 + 2 + 9 failed | **CAUGHT** |
| 9 | the audit call on the decision | every decision is audited | 3 failed | **CAUGHT** |
| 10 | the chain no longer starts on submission | a configured rule is honoured | 32 failed | **CAUGHT** |
| 11 | the direct decision no longer stands aside | the chain is authoritative | 2 failed | **CAUGHT** |
| 12 | the chain callback no longer updates the request | the decision reaches the request | 11 failed | **CAUGHT** |

**Twelve of twelve caught. None survived.**

Mutation 6 is worth singling out: it puts the **pre-M1 defect back** — the bare
`is_master()` that walked past the module licence — and the suite fails. That is
the regression guard for Finding B, so the defect cannot quietly return.

Mutation 9 matters for a different reason: the audit assertions are
**behavioural** (rows read back from `activities`), so removing the audit call
fails the suite. Under the old source-string assertions it would have passed —
which is how a call to a function that does not exist survived two milestones.
