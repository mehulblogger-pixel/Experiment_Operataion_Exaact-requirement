# Phase 3 · M3 CORRECTION #11 — MUTATION RESULTS

Clean baseline, executed against the final source, no anchor misses.

**9 attempted · 9 caught · 0 survivors.**

| # | §11 requirement | Result | Failures |
|---|---|---|---:|
| W1-M1 | restore a process-global last-error | **CAUGHT** | 7 |
| W1-M2 | do not key the error on write | **CAUGHT** | 9 |
| W1-M3 | return A's error after the switch to B | **CAUGHT** | 7 |
| W1-M4 | use an incorrect workspace key — all workspaces share one | **CAUGHT** | 7 |
| W2-M1 | return FAILED for NOT_ATTEMPTED | **CAUGHT** | 2 |
| W2-M2 | return NOT_ATTEMPTED after a genuine write failure | **CAUGHT** | 1 |
| W2-M3 | return STORED when the UPDATE actually fails | **CAUGHT** | 3 |
| W2-M4 | optional metadata failure removes the core audit row | **CAUGHT** | 5 |
| **W2-M5** | *(mine)* STORED returned without the UPDATE running at all | **CAUGHT** | 20 |

## W2-M5 is mine, and its first version was invalid

§11 attacks what the writer **returns**. Nothing in it attacks whether `STORED`
corresponds to a value that is actually in the column — and "reports success
without doing the work" is the failure mode this whole sequence keeps finding.

**Its first version survived, and that was my fault, not a finding.** The mutation
appended a comment after the return instead of removing the UPDATE, so it changed
nothing:

```
SURVIVED — W2-M5 STORED is returned without the UPDATE running at all     ← a no-op mutation
```

Rewritten to genuinely skip the `UPDATE`, it is **caught with 20 failures**. A
mutation that does not change behaviour is not a survivor; it is a broken
experiment, and reporting it as evidence either way would be false.

The assertion that catches it is `C11.5 B`, which does not stop at the returned
status:

```php
t_eq(act_set_cond_key($id, 'PC|C11|1'), ACT_COND_STORED, '… reports STORED');
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$id]), 'PC|C11|1',
     '… and the value is genuinely there — STORED is not merely claimed');
```

## W2-M4 is the one that matters most

Correction #8's guarantee is that the **core audit row survives optional metadata
failure**. `W2-M4` makes a failed `cond_key` write delete the row it was attached
to, and is caught. That line is now held by a test rather than by the order of two
statements.

## Scope of the run

Suites per mutation: `p3m`, `recruit_approval`, `m4_`, `activity`. A suite that
dies without printing `RESULT:` counts as a detection. Mutations are applied to a
copy of `phpapp/` in the scratchpad; the repository is never mutated.
