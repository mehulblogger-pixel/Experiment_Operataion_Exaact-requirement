# Phase 3 · M2 — Architecture

## 1. The shape of the change

| Layer | M2 |
|---|---|
| Approval engine | **reused whole.** No second engine, no second rules engine, no second workflow |
| Rules table | **3 additive nullable columns**: `applies_office_id`, `effective_from`, `effective_to` |
| Priority | **reused `sort`** — the existing column the screen already called "Match order" |
| Levels / chain / steps | **untouched** |
| Delegation | **1 new table**, `approval_delegations` — the only thing that did not exist |
| Decision writer | still `hreq_apply_decision()`, M1's single writer |
| Executability | still `hreq_is_executable()`, the single question |
| Audit | the existing spine, two new subject kinds |

## 2. Submission, end to end

```
hreq_submit()
   │  entitlement · capability · scope · state      (validated BEFORE anything is created)
   │  snapshot taken
   ▼
appr_start('HIRING_REQUEST', id, hreq_appr_ctx($r))
   │        ctx = department · grade · designation · position_id
   │              · office_id  ← M2   · amount = headcount  ← M2
   ▼
appr_match()  ──▶  appr_match_all()  ──▶  appr_rule_score()
   │                  ranked list            null = does not apply
   │                  ties flagged           n    = specificity
   ▼
  a rule?  ── no ──▶  stays SUBMITTED, decided directly (M1 behaviour, unchanged)
   │ yes
   ▼
chain created, rule_id + rule_name STAMPED, request → UNDER_REVIEW
```

Nothing is mutated before the request is known to be valid: the chain is created
only after `hreq_submit()` has validated and taken its snapshot.

## 3. Deciding

```
appr_act(step)
   ├─ step is the current level?            (sequential ordering, unchanged)
   ├─ appr_can_act()   named user · delegation · role · delegation-for-role
   ├─ appr_guard()     entitlement → branch scope → segregation
   ├─ write step, advance or close chain
   ├─ appr_callback()  → hreq_apply_decision()   ← the one writer
   └─ a failed callback is surfaced, not discarded (M1 correction)
```

## 4. Why there is no new rules engine

The requirement was "when this type of hiring request is raised, these
authorities approve it". That is a row of conditions and an ordered list of
levels — which is exactly what `recruit_approval_rules` + `recruit_approval_levels`
already were. Adding branch and dates made the existing shape sufficient; a
generic condition/operator/value engine would have been more machinery for the
same sentence, and a harder screen for the administrator to read.

## 5. Why delegation is a table and not a column

A standing delegation has its own lifetime (from, to, revoked), its own scope
(entity, branch), its own parties (delegator, delegate) and its own audit. None
of that fits on a step, a rule or a user row. IDEMS's per-step `delegated_to`
integer is a different feature in a different engine.

## 6. What M2 deliberately did not build

- **Parallel approval** — the engine tracks one `current_seq`; §19 says not to
  build it for completeness.
- **`request_type` / `employment_type` / `priority` / `required_by` conditions**
  — each is one column and one line, but none had a stated business case, and an
  unused dimension is a box an administrator must understand and leave blank.
- **A "mandatory approval" tenant switch** — it would change every existing
  installation's behaviour on upgrade. §8 says document rather than change
  silently, so it is recorded as an open business decision.
- **Anything with a timer** — SLA, escalation, reminders and notifications are
  M3. The existing reminder/escalation code was left exactly as it was, and the
  M2 data does not disturb it.
