# Phase 3 · M1 — Authorization & Decision Rules

## 1. Creation vs decision — two different rights

| Act | Right | Note |
|---|---|---|
| View a hiring request | `can('mod.hiring.view')` | route gate **and** on entry to the handler |
| Create / edit / submit / cancel / convert | `can('mod.hiring.edit')` | M4's corrected capability model, unchanged by M1 |
| **Decide — through a chain** | be the step's approver (`appr_can_act()`): the named user, or the configured role | plus the guard below |
| **Decide — directly** (no chain matched) | `hreq_can_decide()` = the module **and** a management role | M4's rule, unchanged |

**No job title is hard-coded anywhere.** M4 removed `is_coordinator_level()` from
the request layer and M1 did not reintroduce it. Who approves is *configuration*
— a role or a named user on an approval level an administrator set up — not a
constant in the source.

**No new permission was created.** M1 uses `mod.hiring.view`, `mod.hiring.edit`
and the approval engine's own approver identity, all of which already existed.

## 2. The decision guard

`appr_guard($req)` is asked at the mutation choke point, before anything is
written, and returns a reason or an empty string — so it can be tested without a
redirect.

```
1. ENTITLEMENT   licence_blocks('mod.hiring.view')   ← asked FIRST, master included
2. EXISTS        the hiring request is still there
3. SCOPE         hreq_in_scope()                     ← the decision, not just a route
4. SEGREGATION   hreq_segregation_blocks()
```

## 3. Self-approval

**A requestor cannot approve their own hiring request.** The comparison is
`hiring_requests.requested_by_id → users.id` against the signed-in actor — not a
name, not a role. The approval row's own `requester` column is a *display name*
and cannot answer this reliably, which is why the question is asked of the
hiring request.

It is enforced in `appr_guard()` and in `hreq_decide()`, both of which call the
**same** helper, `hreq_segregation_blocks()` — one rule, two readers. It is not
enforced by hiding a button: the test signs in as a requestor who **does** hold
the approver role, confirms `appr_can_act()` says yes, and then confirms the
engine still refuses the decision and writes nothing.

### The one exception, unchanged from M4

A **master** may decide a request they raised, because a single-administrator
workspace has nobody else. M1 **did not broaden it**:

- entitlement is still required (a master on an unlicensed workspace is refused);
- the approver identity is still required;
- branch scope is still required;
- the audit entry is still written.

## 4. What changed about masters

Before M1, `appr_can_act()` began `if (is_master()) return true;` — which walks
straight past the module licence. A master on a workspace that had **not bought
recruitment** could act on recruitment approval steps. It now asks
`is_master_of('hiring')`: master, **but only for a module this installation
actually has**. This is the existing licence-aware helper the rest of the
application already uses, not a new rule.

## 5. Rejection

A rejected request is not executable and cannot become a requisition. Its state
cannot be moved back to `APPROVED` by any route: `hreq_apply_decision()` accepts
only `SUBMITTED` and `UNDER_REVIEW`, and its step cannot be re-acted on.

**Until re-approval is built (a later milestone), a rejected request is final.**
The screen says so in those words. The way forward for the business is to raise a
new hiring request. M1 does not implement re-approval and does not pretend to.

## 6. Material changes after approval — recorded, not built

M4 already refuses edits to an `APPROVED`, `REJECTED` or `CANCELLED` request, and
M1 preserves that. The fields that will require **re-approval** when a later
milestone allows an approved request to change:

`quantity` · `requesting_department_id` · `hiring_department_id` · `position_id` ·
`designation` · `grade` · `employment_type` · `office_id` · `required_by` ·
`job_title`

These are exactly the facts the approval snapshot freezes, which is what makes
"what did the approver actually approve?" answerable. Building the re-approval
mechanism is **not** M1.
