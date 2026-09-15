# Phase 3 · M2 — The Approval Matrix: conditions and precedence

## 1. What a policy can consider

A policy is **one row** in `recruit_approval_rules`. These are the conditions it
can pin down — and no others. M2 deliberately did not build a generic rules
language.

| Condition | Column | Notes |
|---|---|---|
| What is being approved | `entity` | `HIRING_REQUEST`, `REQUISITION`, `OFFER`, `SALARY` |
| Requesting/hiring department | `applies_department` | **vocabulary-aware** — a rule written "Quality" matches a request filed "QAQC" (M3's `dept_canon()`), and only where the customer has approved that term |
| Business unit | `applies_sbu` | |
| Grade / band | `applies_grade` | |
| Position / designation | `applies_position` | |
| **Branch / office** | `applies_office_id` | **added by M2.** Blank = the customer's global policy |
| Size band | `min_amount` / `max_amount` | for a hiring request this reads the **headcount**; for an offer, money |
| **In force from / until** | `effective_from` / `effective_to` | **added by M2.** Blank = no limit |
| On or off | `active` | |
| Match order | `sort` | the existing column; the screen already called it "Match order" |

Each list field accepts several comma-separated values.

### Deliberately not implemented

`request_type`, `employment_type`, `priority` and `required_by` are **not**
conditions. Each would have been one more column and one more line in the
matcher, but none had a business case put to us, and every unused dimension is a
box an administrator has to understand and leave blank. They can be added later
in exactly the same shape. **Parallel approval** is likewise not supported — the
engine tracks one `current_seq`, and M2 was told not to build it for
completeness (§19).

## 2. Precedence — deterministic, and now written down

```
1. SPECIFICITY   how many conditions the rule actually pins down —
                 department, business unit, grade, position, branch,
                 plus one point for a matched size band
2. MATCH ORDER   `sort`, ascending — lower wins
3. AGE           `id`, ascending — the older rule wins
```

**A rule that names more, wins.** A rule that does not apply at all — wrong
branch, outside its dates, outside its size band, a condition that does not match
— is not ranked; it is discarded.

This was already the behaviour before M2 (a strictly-greater-score loop over
`ORDER BY sort, id`). What M2 changed: branch now counts toward specificity,
dates can exclude a rule, and the whole ranking is exposed by
`appr_match_all()` so the administrator can see the runners-up rather than only
the winner. The tie is asserted five times in a row in the tests to demonstrate
it is stable, not coincidental.

### Worked example

```
Global Hiring Rule                  specificity 0
Engineering                         specificity 1   ← wins for an Engineering hire
Engineering + Ahmedabad             specificity 2   ← wins for one in Ahmedabad
```

## 3. Conflicts

| Situation | What happens |
|---|---|
| Two rules, different specificity | the more specific one wins |
| Two rules, same specificity, different `sort` | lower `sort` wins |
| Two rules, identical specificity **and** `sort` | the **older** wins — deterministic, but almost certainly not intended, so the configuration screen **warns** and names the other rule |
| An inactive rule matches | ignored entirely |
| A rule outside its effective dates | ignored entirely |
| A rule whose level names a role nobody holds | it still matches — but the screen warns that the policy **cannot run**, and the request stays non-executable rather than being auto-approved |

Ambiguity is surfaced at configuration time, where it can be fixed, rather than
being resolved silently at approval time.

## 4. No match — behaviour unchanged from M1

**Where no rule matches, nothing changes from M1:** the request stays
`SUBMITTED` and is decided directly by someone holding the module and a
management role. A workspace that has configured no policy is not forced into an
approval process it never asked for.

M2 did **not** add a "mandatory approval" tenant switch. That would change the
behaviour of every existing installation on upgrade, and it is a policy decision
for the business owner rather than a technical one. It is recorded here as an
open question, not implemented.

## 5. History is not rewritten

`recruit_approval_requests` stores **`rule_id` and `rule_name`** at the moment
the chain starts. Renaming, re-scoping, deactivating or deleting a policy
afterwards does not touch a chain that already exists — the request keeps the
basis it was actually approved under. Asserted directly: a rule is renamed after
approval and the finished chain still reports the original name.
