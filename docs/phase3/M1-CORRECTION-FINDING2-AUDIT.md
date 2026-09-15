# Phase 3 · M1 Correction — `appr_inbox()` consumer audit (Finding 2)

**Disposition: DEFERRED to the approval-matrix milestone. Not changed here.**

## The defect

`appr_inbox()` filters only by `appr_can_act()` — approver identity. It asks no
branch question, so a foreign-branch approver holding the configured role **sees**
another branch's hiring request in their inbox (subject line, job title). M1's
`appr_guard()` refuses the decision, so nothing can be *done*; the disclosure is
of the listing, not of a capability.

## Every consumer, as instructed

| Consumer | What it does with the result | What a scope filter would change |
|---|---|---|
| `ops_my_approvals()` → `views/ops/my_approvals.php` | renders the actionable list | rows an approver can see but not act on would disappear — **the intended fix** |
| `lib/navindex.php` — `appr_inbox_count()` | the "My approvals (n)" badge | the badge would stop counting items the person cannot act on — **also intended**, and arguably a bug fix on its own |
| `tests/test_recruit_approval.php` (4 assertions) | asserts counts of 0/1/0/1 for a **REQUISITION** chain | these fixtures create no office and no branch scope; behaviour would need re-verifying, not assuming |

There are **no other consumers** — no export, no dashboard tile, no API.

## Why it is not being fixed inside this correction

1. **It is not this correction's defect.** The brief is the cancellation /
   approval-chain state-integrity defect. Finding 2 is a separate, pre-existing
   visibility issue.
2. **It changes Offer and Salary visibility.** `appr_inbox()` is shared. Any
   scope filter applies to every entity, and this correction was explicitly told
   not to break or change those workflows. A Finance or Marketing approver on an
   offer chain frequently has **no office scope relationship** to the candidate's
   branch at all, so a naive `hreq_in_scope()`-style filter could empty their
   inbox — silently, which is the worst possible failure for an approval queue.
3. **The safe shape is not yet decided.** The correct fix is probably an
   entity-aware visibility hook mirroring `appr_guard()` — the same question that
   governs acting, asked of listing — so that what is shown and what may be acted
   on are the same set. That is the right design, but it needs the per-entity
   rules that the approval-matrix milestone will define.

## Recommended fix, recorded for that milestone

Give `appr_inbox()` the same entity-aware hook `appr_act()` now uses:

```
listing question  ==  acting question
```

For `HIRING_REQUEST` that is `appr_guard()` (entitlement → scope → segregation).
For `OFFER`, `SALARY` and `REQUISITION` it returns "visible" unchanged until
their own rules are decided, so nothing regresses on the day it lands.

**Interim risk accepted:** a cross-branch approver can read a hiring request's
subject line. They cannot act on it, cannot open it (the request screen enforces
scope on the read), and cannot convert it. The exposure is one line of text.
