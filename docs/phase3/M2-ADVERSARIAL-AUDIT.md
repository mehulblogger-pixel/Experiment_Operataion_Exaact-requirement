# Phase 3 · M2 — Adversarial Audit

Conducted **after** M2 was reported complete, against the committed code
(`ee3cf61`), by probing the seams rather than re-reading the report. Both
findings were reproduced against a running application.

**No code was changed by this audit.** M2 is under HARD STOP.

Both findings are in code **M2 introduced**. Neither is pre-existing.

---

## FINDING A — an office-scoped delegation leaks onto entities that have no branch

**Severity: moderate. A delegation is honoured more widely than the administrator asked.**

`appr_delegators_for()` skips the branch test when the caller passes no office:

```php
$do = (int) ($d['office_id'] ?? 0);
if ($do > 0 && $officeId !== null && (int) $officeId !== $do) continue;
```

`appr_step_context()` only resolves `_office_id` for `HIRING_REQUEST`; every
other entity passes `null`. So a delegation the administrator scoped to one
branch is **unscoped for Offer, Salary and Requisition approvals**.

Reproduced — a delegation scoped to office 801:

```
hiring request in office 801 : true      correct
hiring request in office 802 : false     correct
an OFFER (no office context) : true      ← the delegation said "office 801 only"
```

The administrator's intent was "cover my Ahmedabad work while I'm away". What
they got also covers offer approvals company-wide.

**Why it happened:** `$officeId !== null` was written to mean "no branch context,
so do not filter". It should mean the opposite — a delegation that *names* a
branch should not apply where branch cannot be established.

**Recommended fix (small):** invert the default — when a delegation names an
office and the caller supplies no office context, the delegation does not apply.
Alternatively resolve `_office_id` for the other entities too. The first is
safer and one line; the second is more work and changes Offer/Salary behaviour,
which M2 was told not to do.

---

## FINDING B — a deactivated delegator still lends their authority on a named-user step

**Severity: moderate-to-high. Someone who has left the company keeps granting approval rights.**

`appr_delegators_for()` never checks whether the **delegator** is still active.
The role path does check — `(int) ($du['is_active'] ?? 0) === 1` — so the two
paths disagree:

```
delegator switched off:
  named-user step, appr_can_act = true    ← still acts for a departed approver
  role step,       appr_can_act = false   ← correctly refused
```

A delegation is created legitimately; the delegator then leaves and is switched
off; their delegate continues approving in their name on any step that names
that person directly.

**What limits it:** the delegate must still pass entitlement, branch scope and
segregation, and the delegation's own window and revocation still apply. So this
is not an open door — it is an authority that should have lapsed and did not.

**Why it happened:** I added the `is_active` check on the role path (where the
delegator must genuinely hold the role) and did not carry the same question to
the named-user path, where no role lookup was needed. An inconsistency, not a
deliberate choice.

**Recommended fix (small):** check the delegator's `is_active` once, in
`appr_delegators_for()`, so both paths inherit it — and drop the now-redundant
check on the role path so there is one rule rather than two.

---

## What the audit did NOT find

- A rule with **no conditions** (specificity 0) still matches correctly — the
  move from the strictly-greater-score loop to `usort` did not lose the global
  policy.
- A branch-scoped rule does not apply in another branch, and a hiring request
  carries its branch into the matcher.
- Delegation still cannot defeat segregation, chain, outlive its window, survive
  revocation, or manufacture a role the delegator never held.
- Configuration remains gated, entitlement-first, and audited.
- No cross-entity leakage in the **queue**: `appr_visible()` narrows only.

## Honest summary

M2's matrix, precedence, orphan handling and configuration security survived the
audit. **Delegation did not, twice** — in both cases because a scope question was
asked on one path and not on the sibling path. Both are my defects, both are
small, and neither is pre-existing.
