# Phase 3 · M2 — The Authority Model

## 1. Six questions, all of them asked

M1 established that an approver needs more than one thing to be true. M2 adds
the matrix question in the middle and changes none of the others.

| # | Question | Where it is asked |
|---|---|---|
| 1 | **Entitlement** — has this workspace bought recruitment? | `appr_guard()`, first line, via `licence_blocks()`; the route map; `ops_my_approvals()` |
| 2 | **Capability** — may this person act in recruitment at all? | `can('mod.hiring.view')` on the routes; `appr_can_act()` for the step |
| 3 | **Matrix authority** — are they the approver *for this step*? | `appr_can_act()`: named user · configured role · org-chart token · **delegation** |
| 4 | **Scope** — is this record in their branch? | `appr_guard()` → `hreq_in_scope()` |
| 5 | **Segregation** — did they raise it? | `appr_guard()` → `hreq_segregation_blocks()` |
| 6 | **State** — is it actually awaiting this decision? | `appr_act()` (`seq` = `current_seq`) and `hreq_apply_decision()` |

Order is the security property: entitlement is settled before anything about the
person is consulted, so a master on an unlicensed workspace is refused before
their master-ness is ever read.

## 2. The distinction the brief asks for

> **Permission to approve is not the same as being the approver for this request.**

A Branch Manager may hold every recruitment capability there is and still not be
the approver for a particular hiring request — because the policy that matched
named a different authority, or named a person, or named a branch that is not
theirs. Both questions are asked, and neither substitutes for the other.

Conversely, being named by the policy is not enough either: a named approver who
has lost the module, moved branch, or raised the request themselves is refused.

## 3. How an approver is resolved

Four sources, in the order `appr_can_act()` asks them:

1. **A named user** — `approver_user_id`. Exact.
2. **Acting for that named user** — an active, in-window, in-scope delegation.
3. **A configured role** — `approver_role` against `users.role`.
4. **Acting for somebody who holds that role** — delegation again, and the
   delegator must genuinely hold the role and be active.

Plus **org-chart tokens** (`__MGR1__`, `__MGR2__`, `__MGR3__`, `__HOD__`) which
resolve against the position tree when the chain is created; where they cannot
be resolved the token is kept and a hiring admin may act, so a step is never
stranded.

**No job title is hard-coded anywhere.** Who approves is configuration.

## 4. Masters

`is_master_of('hiring')` — master, but only for a module the installation has
actually bought. M1 fixed the bare `is_master()` that walked past the licence,
and M2's mutation battery puts that defect back to prove the suite still catches
it.

The master exception to **segregation** (a one-person workspace has nobody else)
is M4's, stated once, and M2 neither broadened nor narrowed it.

## 5. A configured authority that nobody holds

An approval level may name a role no active user holds. M2 does three things and
none of them is "approve it anyway":

- the configuration screen **warns**, naming the level and the reason;
- `appr_preview()` shows **nobody** against that level;
- at runtime the request simply waits — non-executable, never auto-approved,
  never given an invented approver — and a master can clear it from *My
  approvals*, which is the recovery path M1 established.
