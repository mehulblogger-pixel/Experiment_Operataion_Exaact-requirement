# Phase 3 · M3 CORRECTION #7 — MUTATION RESULTS

Clean baseline (`baseline failures: 0`), executed against the final source, no
anchor misses, no survivors.

**12 attempted · 12 caught · 0 survivors.**

| # | §11 requirement | Mutation | Result | Failures |
|---|---|---|---|---:|
| L1a | 1 · remove deduplication | …on the **decision** path | **CAUGHT** | 5 |
| L1b | 1 · remove deduplication | …on the **scheduler** path | **CAUGHT** | 2 |
| L2 | 2 · rule_id **absence** bypasses dedup | a chain with no `rule_id` is exempt | **CAUGHT** | 5 |
| L3 | 3 · rule_id **presence** bypasses dedup | a chain **with** a `rule_id` is exempt | **CAUGHT** | 10 |
| L4 | 4 · one row per scheduler run | a timestamp in the key makes every observation unique again | **CAUGHT** | 6 |
| L5 | 5 · suppress the initial event | the **first** observation is suppressed too | **CAUGHT** | 11 |
| L6 | 6 · suppress genuinely new conditions | the key ignores event class and reason — every condition collapses into one | **CAUGHT** | 7 |
| L6b | 6 · (over-classification) | a **transient** reason is classified permanent and wrongly suppressed | **CAUGHT** | 4 |
| L7 | 7 · bypass entity validation | the audit reference checks only the TYPE again | **CAUGHT** | 26 |
| L8 | 8 · bypass tenant validation | an absent or cross-tenant record satisfies the reference | **CAUGHT** | 22 |
| L9 | 9 · bypass openability | the subject chooser always returns the source entity, openable or not | **CAUGHT** | 63 |
| L9b | (this correction's own regression) | the scheduler stops carrying `rule_id`, so orphan events are dropped again | **CAUGHT** | 2 |

## Two mutations that are mine, not the brief's

**L6b — over-classification.** §2 warns against calling a condition permanent
merely because one lookup failed. The battery as listed can only detect
*under*-suppression; nothing in it detects the opposite error, where a transient
condition is wrongly silenced and a real recurring failure disappears. L6b adds
`RECIPIENT_INACTIVE` and `PROVIDER_FAILURE` to the permanent list and is caught.

**L9b — the defect this correction itself introduced and then fixed.** The
scheduler had to start carrying `rule_id` so an orphan's event has a subject that
opens (see the audit document, §4). Removing it again restores the silent
history-dropping that made H2 *look* fixed after correction #6. Without this
mutation nothing would hold that line.

## Note on splitting L1

§11 lists "remove permanent-condition deduplication" as one mutation. It is
applied at **two** call sites, and the identical text appears in both — the exact
shape that made `Q08` ambiguous in the correction #6 battery. It is therefore run
twice, once per path. A single anchor would have silently tested only one of them.

## Scope of the run

Suites per mutation: `p3m`, `recruit_approval`, `m4_`. A suite that dies without
printing `RESULT:` counts as a detection. Mutations are applied to a copy of
`phpapp/` in the scratchpad; the repository is never mutated.
