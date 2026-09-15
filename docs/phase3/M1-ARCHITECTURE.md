# Phase 3 · M1 — Architecture

## 1. What M1 added: almost nothing

| Layer | Change |
|---|---|
| Database | **none.** No table, no column. |
| Approval engine | one entry in `APPR_ENTITIES`; one branch in `appr_callback()`; one guard (`appr_guard()`); one licence-aware master helper swap; one licence check on the inbox route |
| Hiring request | `hreq_apply_decision()` (the one decision writer), `hreq_segregation_blocks()`, `hreq_approval()` / `hreq_approval_steps()` / `hreq_appr_ctx()`; `hreq_submit()` starts a chain; `hreq_decide()` stands aside for one |
| Audit | `HIRING_REQUEST` and `REQUISITION` registered on the existing spine; six dead calls replaced with real ones |
| UI | approval state, rejection state and an approval-history table on the request screen; the inbox names and links hiring requests |

## 2. The flow

```
          hreq_save()                 DRAFT          not executable
               │
          hreq_submit()      ── snapshot taken here ──
               │
               ├── a rule matches ──▶ appr_start()  ──▶  UNDER_REVIEW
               │                      (existing engine)   approval_ref = chain id
               │                                │
               │                          appr_act()  ← approver, from /my-approvals
               │                                │   appr_can_act()  who
               │                                │   appr_guard()    entitlement → scope → segregation
               │                                ▼
               │                          appr_callback('HIRING_REQUEST')
               │                                │
               └── no rule matches ──▶ SUBMITTED │
                                          │      │
                                    hreq_decide()│   capability → scope → segregation → no open chain
                                          │      │
                                          ▼      ▼
                                   hreq_apply_decision()      ← THE ONE WRITER
                                          │        state check · write · audit
                                          ▼
                                   APPROVED / REJECTED
                                          │
                                   hreq_is_executable()       ← THE ONE QUESTION
                                          │
                                   hreq_to_requisition()
```

## 3. Two doors, one writer

A hiring request can be decided two ways, and they must never disagree:

- **Through the chain** — where an administrator has configured a rule. The
  chain is authoritative: `hreq_decide()` refuses while one is open.
- **Directly** — where no rule matches. This is M4's behaviour, unchanged, so a
  workspace that has configured nothing is not forced into an approval process
  it never asked for.

Both end at **`hreq_apply_decision()`**, the only function that writes a
decision. It holds the state rule (only a `SUBMITTED` or `UNDER_REVIEW` request
can be decided) and writes the audit entry, so a cancelled or already-decided
request is refused whichever door the decision arrives through.

It deliberately asks **no** authority question, because its two callers each ask
the right one. That is only safe if the set of callers is exactly those two —
which the test suite asserts by enumerating callers across `lib/`, rather than
trusting a comment.

## 4. The authorization chain, in order

| # | Question | Where |
|---|---|---|
| 1 | **Entitlement** — has this workspace bought recruitment? | `appr_guard()` first line; `licence_blocks()`; `ops_my_approvals()`; `can()` on the request routes |
| 2 | **Who** — are you the approver for this step? | `appr_can_act()` (named user, or the configured role) |
| 3 | **Scope** — is this request in your branch? | `appr_guard()` → `hreq_in_scope()` |
| 4 | **Segregation** — did you raise it? | `appr_guard()` → `hreq_segregation_blocks()` |
| 5 | **State** — is it open for decision? | `hreq_apply_decision()` |
| 6 | **Audit** | `act_log()` |

Order is the security property: entitlement is asked **before** anything about
the person, so a master on an unlicensed workspace is refused before their
master-ness is ever consulted.

## 5. What is deliberately entity-scoped

`appr_guard()` returns an empty string for `OFFER`, `SALARY` and `REQUISITION`.
M1 was asked to secure the Hiring Request path, not to change how the other three
have behaved since Phase 6. Extending segregation and branch scope to them is a
customer-visible policy change and is recorded as a Phase-3 question.

Two fixes are **not** entity-scoped, because they are defects rather than policy:
the master bypass in `appr_can_act()` (it ignored the licence) and the missing
licence check on `/my-approvals`. Both applied to every entity and both are fixed
for every entity.
