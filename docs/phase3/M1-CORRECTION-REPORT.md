# Phase 3 · M1 Correction — Approval Chain State Integrity

## 1. Root cause

`hreq_cancel()` was written in **M4**, before approval chains existed. **M1**
connected hiring requests to the Phase-6 chain engine without revisiting it.

So cancelling a request left its chain `PENDING`. The step stayed in the
approver's inbox; approving it reported **"Approved — fully cleared"** because
`appr_act()` called `appr_callback()` and **discarded its return value**; and the
approval history kept an `APPROVED` step against a `CANCELLED` request.

`hreq_is_executable()` held throughout, so no recruitment could start. The defect
was **state integrity and a false record** — which, in an approval system, is the
part that matters.

## 2. Exact code changes

### `lib/recruit_approval.php`

**`appr_cancel_open($entity, $entityId, $reason)` — new.** Closes any chain still
open against an entity. It is **not a new cancellation mechanism**: every query
in this engine that decides whether a chain is live already filters
`status='PENDING'` — `appr_open()`, `appr_inbox()`, `appr_tick()` and
`appr_act()` — so moving the request row off `PENDING` closes it in the inbox, in
the SLA reminders and at the decision **at once**, with nothing else to change.
Pending steps are marked `CANCELLED` so the history reads correctly.

**`appr_callback()` — now reports.** Returns `true` when the decision was
applied, or a **string** giving the reason it could not be. **Only the
`HIRING_REQUEST` branch can return a reason.** `OFFER`, `SALARY` and
`REQUISITION` keep their original best-effort semantics exactly, because their
SQL legitimately matches no rows in ordinary cases (an offer already approved)
and treating that as failure would change behaviour this correction was told not
to touch.

**`appr_act()` — checks it.** Both the approve and the reject paths now test the
callback result and call `appr_undo_step()` on failure.

**`appr_undo_step()` — new.** Puts the step back to `PENDING`, reopens the chain,
and returns `[false, reason]`. A decision that could not be applied is not a
decision, so nothing about it is left behind.

### `lib/hiringreq.php`

**`hreq_cancel()`** calls `appr_cancel_open('HIRING_REQUEST', $id)` and records in
the audit entry whether an approval was withdrawn.

**No schema change. No new table, status store, audit system or permission.**

## 3. Affected approval consumers — audited before modifying shared code

| Consumer | Uses | Impact |
|---|---|---|
| `ops_my_approvals()` (`lib/recruit_approval.php`) | `appr_act()`, `appr_inbox()` | **the only production caller of `appr_act()`**; now receives a failure where it previously received a false success |
| `lib/navindex.php` | `appr_inbox_count()` | unchanged |
| `tests/test_recruit_approval.php` | `appr_act()`, `appr_inbox()` — a REQUISITION chain | unchanged, 25/25 pass |
| `tests/test_offer_appr_dept.php` | offer chains | unchanged, 2/2 pass |
| `appr_callback()` | called only from `appr_act()` | OFFER / SALARY / REQUISITION semantics preserved, asserted directly |

## 4. Tests

The eight behaviours the brief required, all proved behaviourally:

| Required | Evidence |
|---|---|
| submitted → approval PENDING | M1.11 |
| cancelled → chain no longer actionable | M1.11 — `appr_open()` returns null |
| not in the approver's actionable inbox | M1.11 — inbox filtered, count 0 |
| approver cannot approve it | M1.11 — "This step is not pending." |
| **no APPROVED step against a CANCELLED request** | M1.11 — the step records `CANCELLED` |
| **failed callback surfaced as failure** | M1.12 — both approve **and** reject |
| request remains non-executable | M1.11, M1.12 |
| existing consumers still work | M1.12 + the five consumer suites |

Because the fix means the natural route can no longer produce a failed callback,
**M1.12 forces one** — cancelling the request behind the engine's back with raw
SQL — so the second guarantee is proved independently rather than assumed.

Security retested in the same run (M1.0–M1.10, unchanged and passing):
entitlement, capability, branch scope, self-approval, direct helper invocation,
master on an unlicensed tenant, foreign branch.

## 5. Mutation results

Seven mutations, run against a **copy of the application outside the repository**.

| # | Protection removed | Actual | Verdict |
|---|---|---|---|
| C1 | **the audit defect restored** — cancel no longer closes the chain | 5 failed | **CAUGHT** |
| C2 | `appr_cancel_open` leaves the steps pending | 1 failed | **CAUGHT** |
| C3 | `appr_cancel_open` leaves the chain PENDING | 2 failed | **CAUGHT** |
| C4 | the failed callback discarded again (approve) | 4 failed | **CAUGHT** |
| C5 | the failed callback discarded again (reject) | 4 failed | **CAUGHT** |
| C6 | the callback stops reporting the refusal | 8 failed | **CAUGHT** |
| C7 | `appr_undo_step` no longer puts the step back | 2 failed | **CAUGHT** |

**Seven of seven caught, none survived.** C1 is the regression guard: it puts the
original defect back, so it cannot quietly return.

### First run: two survived, and they were my test's fault

| # | First run | Why | Action |
|---|---|---|---|
| C2 | SURVIVED | the assertion read `status !== 'APPROVED'`, which a `PENDING` step satisfies — weaker than what the code promises | assertion strengthened to `=== 'CANCELLED'` |
| C5 | SURVIVED | only the **approve** path was exercised; reject shares the same discard | a reject-path twin was added |

Neither was acceptable defence-in-depth, so the tests were strengthened rather
than the survivals explained away.

## 6. Regression

| Engine | Whole suite | M1 suite |
|---|---|---|
| SQLite | **8931 passed, 0 failed** | 114 assertions, 0 failed |
| **MariaDB 10.11.14** (fresh database `exaact_m1d`) | **8932 passed, 0 failed** | **114 assertions, 0 failed** |

Existing approval consumers: `recruit_approval` 25/0 · `offer_appr_dept` 2/0 ·
`m4_correction` 107/0 · `m4_hiring_request` 78/0 · `m3_multi_vacancy` 99/0.
No test weakened, deleted or skipped.

## 7. Finding 2 disposition — DEFERRED, with the consumer audit done

`appr_inbox()` lists a foreign-branch hiring request although the guard prevents
action. **Not changed here**, for the reasons in
`M1-CORRECTION-FINDING2-AUDIT.md`: the function is shared, and a naive scope
filter would apply to Offer and Salary chains where an approver often has no
office relationship to the candidate's branch — it could silently empty a real
approval queue. All three consumers are documented there, with the recommended
shape (an entity-aware visibility hook mirroring `appr_guard()`, so what is
listed and what may be acted on are the same set) recorded for the
approval-matrix milestone.

**Interim risk accepted and stated:** a cross-branch approver can read one
subject line. They cannot act on it, open the request, or convert it.

## 8. Finding 3 disposition — RECORDED FOR M2

A chain level naming a role nobody holds parks the request at `UNDER_REVIEW`;
recoverable, because a master sees the step and the refusal message points there.
The orphan-role warning belongs with approval-matrix configuration, in M2.

## 9. Changed files

`lib/recruit_approval.php` · `lib/hiringreq.php` · `tests/test_p3m1_approval.php` ·
`deploy-check.php` · `docs/phase3/M1-ARCHITECTURE.md` ·
`docs/phase3/M1-LIFECYCLE.md` · `docs/phase3/M1-CORRECTION-FINDING2-AUDIT.md` ·
this report.

## 10. Commit and tree

Branch `claude/testing-branch-setup-0gqe8n`: `7f5a27e` (the fix), `fa91318`
(the two strengthened tests), and this report. **Working tree clean** — mutation
testing runs against a copy outside the repository.

---

## PHASE 3 — M1 COMPLETE — HARD STOP — READY FOR M1 AUDIT
