# Phase 3 · M2 — Approval Matrix, Authority & Delegation: Audit

**No code was written before this audit.** Everything below was read from the
code or proved against a running application.

## The nine questions

### 1. Does a configurable approval matrix already exist?

**Yes — and it is substantial.** Phase 6 built it and M1 connected the Hiring
Request to it. `lib/recruit_approval.php`, four tables, an administration screen
(`/recruit-approvals`, gated `hiring_admin_can()`), and a cron tick.

### 2. What existing approval rule objects can be reused?

All four, unchanged:

| Table | Holds |
|---|---|
| `recruit_approval_rules` | entity · `applies_department` · `applies_sbu` · `applies_grade` · `applies_position` · `min_amount`/`max_amount` · `active` · **`sort`** |
| `recruit_approval_levels` | `rule_id` · **`seq`** · label · `approver_role` **or** `approver_user_id` · sla/reminder · escalation target |
| `recruit_approval_requests` | entity · entity_id · **`rule_id` + `rule_name`** · subject · amount · status · `current_seq` |
| `recruit_approval_steps` | per level: approver, status, `acted_by`, `acted_at`, `remarks` |

### 3. How are approvers currently selected?

Three sources, already built:

1. **A named user** — `approver_user_id`.
2. **A configured role** — `approver_role`, matched against `users.role`.
3. **An org-chart token** — `APPR_ORG_APPROVERS` in `lib/deptorg.php`:
   `__MGR1__` / `__MGR2__` / `__MGR3__` (walk `reports_to_id` up the position
   tree) and `__HOD__` (head of the position's department). Resolved at chain
   creation by `appr_resolve_org_approver()`; where it cannot be resolved, the
   token is kept and a hiring admin may act so a step is never stranded.

**This is already an authority model, not a job-title list.** No approval role is
hard-coded.

### 4. How are roles assigned to users?

`users.role` (one of `ORG_ROLES`), with custom roles resolving to a base through
`role_effective_key()`. Per-user permission overrides sit in `users.permissions`.

### 5. Does delegation already exist?

**Not for approvals.** The only delegation in the application is IDEMS's
`report_approvals.delegated_to` — a **per-step hand-off** inside a different
module's own approval engine. It has no delegator, no effective dates, no scope
and no standing period. It answers "pass *this* step to someone", not "while I am
away, B acts for me".

**This is the one genuine gap.** See §"What must be built".

### 6. Can existing approval steps represent multiple levels?

**Yes, sequentially and correctly.** `recruit_approval_levels.seq` defines the
order; `recruit_approval_requests.current_seq` tracks the live level; `appr_act()`
refuses a step whose `seq` is not the current one ("An earlier level is still
pending") and advances to the next on approval. Multi-level already works.

**Parallel approval is not supported** by this shape (one `current_seq`), and M2
will not build it — §19.

### 7. Can existing rules represent conditions?

Partly. Supported today: department (already vocabulary-aware through
`dept_canon()` since M3), business unit, grade, position, and an amount band.

**Not supported today: branch/office, and effective dates.**

### 8. What is missing?

| Gap | Severity |
|---|---|
| **No branch/office dimension** on a rule — a customer cannot say "Ahmedabad hires need the branch manager" | the biggest functional gap; §28 requires branch to matter |
| **No effective dates** — a policy cannot be scheduled or retired, only switched off | §30 |
| **Precedence is real but undocumented** — specificity score first, then `sort`, then `id`. Deterministic, never random, but never written down or tested | §7 |
| **No orphan-role warning** — M1's Finding 3 | §12 |
| **No standing delegation** | §20 |
| **Inbox visibility is not entity-aware** — M1's Finding 2 | §25 |

### 9. What is the smallest safe extension?

**REUSE** the four tables, the chain engine, the org-chart approver resolution,
the administration screen, the audit spine, M1's guard and its one decision
writer.

**REUSE `sort` as the priority field** — it exists, the screen already labels it
"Match order". No new priority column.

**EXTEND** `recruit_approval_rules` with three additive, nullable columns —
`applies_office_id`, `effective_from`, `effective_to` — through the existing
idempotent `ensure_column()`. No table change anywhere else.

**BUILD** exactly one new table, `approval_delegations`, because nothing in the
application can represent a standing, dated, scoped delegation (see question 5).

**CONNECT** delegation into `appr_can_act()` as a fourth eligibility source, and
the branch/date conditions into `appr_match()`.

## What must be built, and why it is unavoidable

**`approval_delegations`.** The requirement is a standing authority transfer with
a delegator, a delegate, an effective window, a scope and a revocation trail.
IDEMS's `delegated_to` is a single nullable integer on one step of another
module's table: it cannot carry dates, scope, a delegator or an audit of its own,
and reusing it would mean writing recruitment delegation into the inspection
engine. No other table comes close. One additive table is the smallest honest
answer.

## Precedence as it exists today — to be documented and tested, not invented

```
1. specificity  — count of matched conditions (department, BU, grade, position,
                  plus one point for a matched amount band)
2. sort         — "Match order" on the screen, ascending
3. id           — creation order, ascending
```

`appr_match()` keeps the first rule with a **strictly greater** score while
iterating in `ORDER BY entity, sort, id`. So an equal-specificity tie is already
resolved by `sort`, then by `id`: deterministic, never random. M2's job is to
**write this down, add branch and dates to the specificity calculation, and test
it** — not to replace it.
