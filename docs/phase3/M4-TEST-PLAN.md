# PHASE 3 · M4 — TEST PLAN

Written before implementation, so the tests describe the contract rather than the
code. Every group names the **business outcome** it proves, not the function it
calls — M3's repeated lesson was that proving a helper proves nothing.

| # | Group | What must be proved |
|---|---|---|
| 1 | **Unit** | materiality is computed against the **approved snapshot**, not the last edit; quantity increase is material, decrease is not; `approval_required` cannot be edited after approval at all |
| 2 | **Database** | migrations are forward-only, additive, idempotent and non-destructive; re-running changes nothing; an existing approved request keeps its history; SQLite **and** MariaDB, with any difference named |
| 3 | **Workflow** | approve → non-material edit → still executable; approve → material edit → **not** executable, re-approval required; re-approve → executable again; re-approval rejected → still not executable |
| 4 | **RBAC** | only `mod.hiring.edit` may change; only the decide right may re-approve; the requestor may not re-approve their own change (segregation, M1/M2 rules unchanged) |
| 5 | **Entitlement** | every M4 operation denied without entitlement through navigation, page, **direct URL, POST, AJAX, export, report and the scheduler** — fail **closed** |
| 6 | **Tenant isolation** | real `db(true)` switching, DB A ≠ DB B proved first; snapshots, re-approval state, quantities, audit and notifications cannot cross |
| 7 | **Branch scope** | Branch B cannot view, edit, approve, reject, re-submit or reach by direct URL/POST/AJAX a Branch A request; tested with no branch, named branch, parent/child and foreign branch |
| 8 | **Approval** | the existing M1/M2/M3 engine is the only path; `appr_guard()` still decides; **no second engine appears** |
| 9 | **Re-approval** | a new chain is created through `appr_start()`; the approved snapshot is captured at the decision; previous approval evidence survives untouched |
| 10 | **Requisition quantity** | total executable requisition quantity can never exceed approved headcount — at creation **and** at edit (`lib/ops.php`) **and** through deployment groups |
| 11 | **Multi-vacancy** | 10 approved seats: hire 1 → 9 remain, hire 2 → 8 remain … all 10 → filled/closed by the existing lifecycle. **No "first hire closes the requisition."** |
| 12 | **Execution boundary** | sourcing, candidate creation, submission, shortlist, interview, offer, recruiter assignment and requisition actions each refuse while approval is pending — **each proved through its own path, not through the UI** |
| 13 | **Audit** | approval granted · material change detected · approval invalidated · re-approval submitted / approved / rejected · quantity changed · execution blocked · execution restored — all on the **existing** spine |
| 14 | **SLA** | a re-approval step is picked up by the existing `appr_tick()` — no M4 SLA engine |
| 15 | **Notification** | recipients follow the existing visibility/actionability rules — no M4 notifier |
| 16 | **Concurrency** | two users editing one approved request; two creating requisitions at once; two raising quantity at once; two re-approving at once; execution racing a change. **Real processes.** No headcount over-allocation, no duplicate re-approval, no lost approval state, no wrong snapshot |
| 17 | **Direct URL** | manipulated id, foreign tenant id, foreign branch id, old approval URL — every one denied or a valid controlled transition |
| 18 | **POST/AJAX/API** | modified quantity, salary, department, designation, status, requester, approver; stale page; repeated submit; double click; back button; replayed request |
| 19 | **Regression** | the complete suite on both engines — approval, inbox, SLA, scheduler, notifications, audit, Recruitment Command Centre, tenant isolation, Operations, Quality, Reporting, Money, Workforce, Marketplace, CRM |

## Two guarantees the plan holds itself to

- **No test may pass because a button is hidden.** Every boundary assertion calls
  the production path directly.
- **Mutation coverage for each control**: removing the boundary check, treating a
  material change as non-material, skipping the snapshot at approval, dropping the
  quantity ceiling on the edit path, and letting a second re-approval start — each
  must be caught by a named assertion, for the intended reason.
