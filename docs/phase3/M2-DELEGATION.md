# Phase 3 · M2 — Delegation

> **While somebody is away, another person may approve in their place — for a
> stated period, and only for the authority they actually hold.**

## 1. Why this is the one thing M2 built

Nothing in the application could represent it. The only delegation that existed
is IDEMS's `report_approvals.delegated_to`: a single nullable integer on one step
of a different module's approval engine, with **no delegator, no dates, no scope
and no revocation**. It answers "pass *this* step to someone", not "while I am
away, B acts for me". Reusing it would have meant writing recruitment delegation
into the inspection engine.

One additive table, `approval_delegations`:

| Column | Meaning |
|---|---|
| `delegator_user_id` | who is away |
| `delegate_user_id` | who acts for them |
| `entity` | one kind of approval, or blank for everything they approve |
| `office_id` | one branch, or blank for any branch they cover |
| `effective_from` / `effective_to` | the window; blank means open-ended |
| `active`, `revoked_by`, `revoked_at` | revocation, kept rather than deleted |
| `reason`, `created_by`, `created_at` | why, and by whom |

## 2. When a delegate may act

Every one of these must hold:

1. the delegation is **active** (not revoked);
2. **today is inside** its window;
3. it covers **this entity** (or is blank);
4. it covers **this branch** (or is blank);
5. the **delegator genuinely holds** the authority — for a role-based step, the
   delegator must currently hold that role and be active;
6. the delegate independently satisfies **module entitlement, capability, branch
   scope and segregation**, exactly as any approver must.

Delegation is a **fourth eligibility source** inside the existing
`appr_can_act()`, alongside named user, configured role and org-chart token. It
is not a separate path around the guard.

## 3. What delegation can never do

| | |
|---|---|
| **Let you approve your own request** | the segregation rule compares the **acting** user against `hiring_requests.requested_by_id`. If the approver delegates to the requestor, the requestor is still refused — asserted as a behavioural test, because this is the case a reader will most doubt |
| **Manufacture authority** | a delegator who does not hold the step's role delegates nothing |
| **Chain** | A→B→C does not reach through. C may act for B; C may **not** act for A. `appr_delegation_chains()` detects the shape and the resolver refuses to walk it twice |
| **Escape entitlement or scope** | both are asked of the acting user, unchanged |
| **Outlive its window** | a future delegation grants nothing; an expired one grants nothing |
| **Survive revocation** | revoking takes effect immediately |
| **Be created to yourself** | refused at save time |

## 4. Who may configure it

`hiring_admin_can()` — the same gate as the rest of approval configuration, and
the route is entitlement-mapped to the paid recruitment module, so an unlicensed
workspace is refused even for a master. Moving approval authority is at least as
privileged as writing the rule that demands the approval.

## 5. Audit

Creation, modification and revocation all go on the **existing** activity spine
under `APPROVAL_DELEGATE`. No second audit system. Delegated approvals are
already visible in the approval history, which records who actually acted.

## 6. Not implemented, deliberately

- **Chained delegation.** Refused rather than supported (§22).
- **Auto-expiry sweep.** A delegation outside its window simply stops granting
  anything; nothing needs to run to make that true, so no timer was added — and
  timers belong to M3 anyway.
- **Per-level delegation.** Delegation is of a person's authority, not of one
  step. The per-step hand-off IDEMS has is a different feature and was not
  copied.
