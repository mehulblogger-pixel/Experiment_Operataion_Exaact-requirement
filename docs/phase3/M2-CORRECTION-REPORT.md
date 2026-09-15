# Phase 3 · M2 Correction — Delegation Scope & Delegator Authority Integrity

## 1. Root cause — Finding A

`appr_delegators_for()` read:

```php
if ($do > 0 && $officeId !== null && (int) $officeId !== $do) continue;
```

`$officeId !== null` treats **a missing branch context as "do not filter"**.
`appr_step_context()` resolves an office only for `HIRING_REQUEST`, so every
other entity passes `null` — and a delegation an administrator scoped to **one
branch was unscoped** for offer, salary and requisition approvals. The
administrator said "my Ahmedabad work while I'm away"; they also got company-wide
offer approvals.

## 2. Fix — Finding A

A delegation that **names** a branch does not apply where a branch cannot be
established:

```php
if ($do > 0) {
    if ($officeId === null) continue;        // no branch to check against → does not apply
    if ((int) $officeId !== $do) continue;   // wrong branch
}
```

**Offer / Salary / Requisition branch resolution was NOT changed.** Making those
entities resolve an office would alter their semantics, which this correction was
told not to do without proof it is safe. The smallest safe correction is the one
specified: *delegation has an office + caller has no office context = does not
apply*.

## 3. Root cause — Finding B

`appr_delegators_for()` never checked whether the **delegator** was still active.
The role path did check. So the two paths disagreed: with the delegator switched
off, a role step correctly refused and a named-user step still granted. Somebody
who had left the company kept lending their approval authority through a
delegate.

## 4. Fix — Finding B

`is_active` is asked **once**, in `appr_delegators_for()`, so both paths inherit
it. The role path's own check was removed, leaving only the question specific to
a role step — *does the delegator genuinely hold the role being delegated?* One
rule, two readers; not two rules.

## 4b. A third fix, found by the write-path audit

`appr_delegation_save()` and `appr_delegation_revoke()` relied on the **route**
for authorization — the same shape as M1's branch scope living only on the route.
The capability is now asked **at the write**. The test proved it immediately by
failing: a step was revoking a delegation while acting as a Coordinator. That is
now an assertion of its own — *a delegate cannot revoke their own delegation, and
the refused revocation changes nothing.*

## 5. Affected consumers

| Consumer | Effect |
|---|---|
| Hiring Request approval | branch-scoped delegations now behave as configured; inactive delegators no longer grant |
| Offer / Salary / Requisition approval | **an office-scoped delegation no longer leaks onto them.** Their own branch resolution, matching and semantics are untouched |
| `appr_can_act()` | one active-status rule instead of two |
| `appr_inbox()` / `appr_visible()` | **not touched** — M1 Finding 2's disposition stands unchanged |
| Delegation administration | authorization now asked at the write as well as the route |

## 6. Changed files

`lib/recruit_approval.php` · `tests/test_p3m2_matrix.php` · `deploy-check.php` ·
this report.

## 7. Database changes

**None.**

## 8. Focused tests

M2 suite **86 → 111 assertions**, covering every case the brief specifies.

**Finding A (5/5):** same-office applies · different-office denied · **no-branch
entity denied** · unscoped delegation preserved (and in any branch) · no
cross-branch leakage either way.

**Finding B (12/12):** active delegator allowed on both step kinds · deactivated
after creation → denied · named-user denied · role-based denied · reactivation
restores **only where the delegation is still valid** · expired stays denied ·
revoked stays denied · delegate still needs capability · still needs entitlement
· still needs branch scope · **still cannot approve the request they raised** ·
still cannot manufacture an authority the delegator never held.

## 9. Mutation table

| # | Protection removed | Actual | Verdict |
|---|---|---|---|
| A1 | office-context protection (the explicit null check) | 0 failed | **SURVIVED — accepted, proved below** |
| A2 | **the original office-scoped leakage restored verbatim** | 2 failed | **CAUGHT** |
| A3 | delegator active check | 3 failed | **CAUGHT** |
| A4 | **named-user inactive-delegator behaviour restored** | 3 failed | **CAUGHT** |
| A5 | role-path protection (delegator need not hold the role) | 5 failed | **CAUGHT** |
| A6 | delegation validity (start + expiry) | 4 failed | **CAUGHT** |
| A7 | delegation scope (entity + branch) | 2 failed | **CAUGHT** |
| A8 | segregation | 6 + 9 failed | **CAUGHT** |
| A9 | entitlement in the decision guard | 1 + 6 failed | **CAUGHT** |
| A10 | delegation configuration authorization **at the write** | 2 failed | **CAUGHT** |

**Nine of ten caught. One survived, with proof rather than an explanation.**

### Why A1 survived, and why that is not a gap

A1 removes the explicit `if ($officeId === null) continue;`. The **next line**
already produces the same outcome: `(int) null === 0`, and the block only runs
when `$do > 0`, so `0 !== $do` always continues. Verified directly against every
shape the value can take:

```
officeId=NULL    with the check: SKIP    without it: SKIP    same
officeId=0       with the check: SKIP    without it: SKIP    same
officeId=801     with the check: APPLY   without it: APPLY   same
officeId=802     with the check: SKIP    without it: SKIP    same
officeId='801'   with the check: APPLY   without it: APPLY   same
officeId=''      with the check: SKIP    without it: SKIP    same
officeId='0'     with the check: SKIP    without it: SKIP    same
```

The protection is real and **A2 proves it**: restoring the original condition
verbatim is caught. A1 removes one of two lines with identical effect. The line
stays — it states the intent and guards a future refactor where `$officeId` might
arrive as something other than an int — but it is not today's operative test, and
saying otherwise would be dressing up a redundancy as a control.

## 10–11. Regression, both engines, identical source

| Engine | Whole suite | M2 suite |
|---|---|---|
| SQLite | **9042 passed, 0 failed** | 111 assertions, 0 failed |
| **MariaDB 10.11.14** (fresh `exaact_m2c`) | **9043 passed, 0 failed** | **111 assertions, 0 failed** |

Both M2.9 correction sections confirmed present in the MariaDB output.

## 12. Existing approval regression

`recruit_approval` 25/0 · `offer_appr_dept` 2/0 · `m4_correction` 107/0 ·
`m4_hiring_request` 78/0 · `hiring_admin` 11/0. Nothing weakened, deleted or
skipped.

## 13. M1 regression

`p3m1_approval` **114 passed, 0 failed** — the M1 approval foundation and its
cancellation correction are untouched.

## 14. M1 inbox finding disposition

**Unchanged and intact.** This correction did **not** touch `appr_inbox()`. M1
Finding 2 was closed in M2 by the entity-aware `appr_visible()`, and mutation
D13 in the M2 battery still guards it. No global branch filter was added, then or
now.

## 15. Remaining M2 limitations

Unchanged from the M2 completion report: parallel approval unsupported; four rule
conditions not implemented (`request_type`, `employment_type`, `priority`,
`required_by`); **no mandatory-approval tenant switch** — no-match still means the
request is decided directly, which remains an open business decision; delegation
does not chain; the approval engine still writes no audit of its own for Offer,
Salary and Requisition.

## 16–17. Commit and tree

`16f4561` (the fix and its tests) and this report, on
`claude/testing-branch-setup-0gqe8n`. **Working tree clean** — mutation testing
runs against a copy outside the repository.

---

## PHASE 3 — M2 CORRECTION COMPLETE — HARD STOP — READY FOR M2 AUDIT
