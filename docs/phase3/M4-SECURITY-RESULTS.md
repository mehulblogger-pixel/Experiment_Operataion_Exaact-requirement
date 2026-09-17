# PHASE 3 · M4 — SECURITY RESULTS

Suite `phpapp/tests/test_p3m4_security.php` — **65 assertions**, **65 PASS / 0 FAIL**
on **SQLite and MariaDB**. Every attack calls the production function the route
calls, with the payload a crafted POST or AJAX request would carry.

| # | Attack | Production path | Expected | Actual | Result |
|---|---|---|---|---|---|
| A1 | id that does not exist | `hreq_save(999999, …)` | deny | *"That hiring request no longer exists."* | **PASS** |
| A2 | same, to a requisition | `hreq_to_requisition(999999,1)` | deny | refused | **PASS** |
| A3 | same, to a decision | `hreq_apply_decision(999999,…)` | deny | refused | **PASS** |
| A4 | **stale approval URL** — decide an already-approved request | `hreq_apply_decision($h,'APPROVED',…)` | deny | *"Only a submitted request can be decided"* | **PASS** |
| A5 | **flip a rejected re-approval to approved** | `hreq_apply_decision($h,'APPROVED',…)` | deny | refused; execution stays blocked | **PASS** |
| B1 | POST `status=DRAFT` | `hreq_save()` | ignored | still `APPROVED` | **PASS** |
| B2 | POST `reapproval_state=REAPPROVED` | `hreq_save()` | ignored | still `NONE` | **PASS** |
| B3 | POST a forged `approved_snapshot_json` | `hreq_save()` | ignored | snapshot byte-identical | **PASS** |
| B4 | POST `decided_by=attacker` | `hreq_save()` | ignored | unchanged | **PASS** |
| B5 | POST `req_no=HACKED-1` | `hreq_save()` | ignored | unchanged | **PASS** |
| B6 | POST `id=424242` | `hreq_save()` | ignored | row identity unchanged | **PASS** |
| B7 | POST `quantity=500` | `hreq_save()` | accepted as a REQUEST, not as authority | typed 500; **approved figure still 10**; execution blocked | **PASS** |
| D1 | tenant B **reads** A's request by id | `hreq_get()` in DB B | deny | not found | **PASS** |
| D2 | tenant B **edits** it | `hreq_save()` | deny | refused | **PASS** |
| D3 | tenant B **approves / re-approves** it | `hreq_apply_decision()` | deny | refused | **PASS** |
| D4 | tenant B raises a requisition from it | `hreq_to_requisition()` | deny | refused | **PASS** |
| D5 | tenant B spends its headcount | `hreq_remaining_qty()` | 0 | 0 | **PASS** |
| D6 | A is unchanged afterwards | `hreq_get()` in DB A | intact | `APPROVED` / `NONE` | **PASS** |
| E1–E8 | branch B attempts **edit, material change, quantity change, approve, reject, submit, cancel, requisition creation** on a branch A request | the eight production helpers | deny each | all eight refused | **PASS** |
| E9 | the request after all eight | — | untouched | `APPROVED`, `NONE`, headcount 10 | **PASS** |
| F1 | replay requisition creation ×5 | `hreq_to_requisition()` | no further allocation | total still the approved 4 | **PASS** |
| F2 | replay the same material change ×5 | `hreq_save()` | no state churn | state unchanged | **PASS** |
| F3 | replay the decision with the opposite answer | `hreq_apply_decision()` | deny | refused; snapshot unchanged | **PASS** |
| G1 | `approval_required=0` after approval | `hreq_save()` | deny | refused, audited `DENIED` | **PASS** |
| G2 | the same **smuggled beside a material change** | `hreq_save()` | deny whole save | refused; designation unchanged — not half-applied | **PASS** |
| G3 | does it become a re-approval route? | — | no | state still `NONE` | **PASS** |
| H1–H5 | a real least-privilege role (`INSPECTOR`) attempts view, create, edit, requisition, decide | the production helpers | deny each | all refused | **PASS** |
| I1–I3 | the requestor decides their **own** request, and their **own re-approval** | `hreq_may_decide()` | deny | segregation blocks both | **PASS** |

## Two probe defects of my own, reported

1. **Tenant B was not fully built.** The first fixture migrated only the hiring,
   approval and activity tables, so the probe died on *"no such table:
   requisitions"* — testing the fixture, not the boundary. B is now built the way
   the application builds a tenant.
2. **The entitlement probe used a role that does not exist.** `VIEWER` is not in
   `ORG_ROLES`, and an unrecognised role falls back to `ADMIN`, so the probe
   granted itself every permission and then reported the product as fail-open. It
   now uses `INSPECTOR`, a real role whose default permission set is empty.

## An observation, not an M4 finding

`ua()` maps an **unrecognised** role to `ADMIN`. Custom roles resolve first through
`role_effective_key()`, so this is reached only by a role that is in no list at
all, and `can()` is otherwise strict — `mod.ops.view` and an invented permission
both returned **false** for the same user. It is **pre-existing**, not introduced
or touched by M4, and under §27/§38 it is **not reopened here**. It is recorded so
it is not lost.
