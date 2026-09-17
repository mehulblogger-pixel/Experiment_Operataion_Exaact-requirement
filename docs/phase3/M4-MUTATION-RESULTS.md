# PHASE 3 · M4 — MUTATION RESULTS

Mutations applied to a **copy** of `phpapp/` in the scratchpad, never the
repository, and run against `p3m4` and `m4_` on **MariaDB**, where the concurrency
races are real. Baseline: **0 failures**.

**Attempted 9 · Caught 9 · Survived 0.**

| Mutation | Expected to be caught by | Actual | Result |
|---|---|---|---|
| **M1** the executable boundary ignores the re-approval state | execution tests | `…recruitment is blocked while it is unapproved`; `C4 · execution agrees with that answer` | **CAUGHT** |
| **M2** a material change is treated as non-material | material-change tests | `…the standing approval is invalidated` (want `REQUIRED`, got `NONE`); `C3 · exactly ONE open approval chain` (want 1, got 0) | **CAUGHT** |
| **M3** the approved snapshot is not captured at approval | snapshot tests | `M4.1 · the approved snapshot exists`; `…the standing approval is invalidated` | **CAUGHT** |
| **M4** the quantity ceiling is removed from the edit path | quantity-edit test | `M4.5 · a direct write of 6 is caught after the fact`; `C2 · total allocation NEVER exceeds the approved 10 (got 12)` | **CAUGHT** |
| **M5** a second re-approval chain is allowed | duplicate-chain test | `M4.13 · the second caller is told it is ALREADY awaiting re-approval` | **CAUGHT** |
| **M6** the tenant boundary is lost | tenant-isolation test | suite FATAL — a leaked row reaches a workspace where its dependencies do not exist | **CAUGHT** |
| **M7** branch scope is removed | branch-security test | `E · a branch B user cannot cancel a branch A request`; `M4.10 · nor raise a requisition from it` | **CAUGHT** |
| **M8** entitlement / permission enforcement removed | entitlement test | `H · a user without the hiring right has no create/edit capability`; `H · cannot edit an approved request` | **CAUGHT** |
| **M9** segregation of duties removed | self-approval test | `I · segregation blocks them`; `I · so they may NOT decide their own request` | **CAUGHT** |

---

## Two mutations survived their first run. Neither was deleted.

**M5 survived — and the investigation found a real independent protection, plus a
real gap in my tests.** Making the compare-and-swap always succeed changed nothing
observable, because `appr_start()` independently refuses to open a second chain
when one is already open. That protection is genuine and is asserted in M4.12. But
it left **my own claim unpinned**: a future change to `appr_start()` would have
removed the guarantee silently. **M4.13** now pins the claim on its own terms — the
second and third callers must be told the re-approval is *already* open — and M5 is
caught.

**M6 survived because the mutation was broken, not because the test was.** It read
a global cache that nothing ever wrote to — a no-op dressed as a mutation, so
"survived" was a broken experiment rather than a finding. Rewritten to actually
populate and return the cross-workspace cache, it is caught.

**The battery was also moved onto MariaDB** after M5's first run, because several
of these controls are only meaningfully exercised where writers genuinely run in
parallel.

## Re-run after the adversarial audit

The whole battery was run again against the **fixed** tree, after the two
execution-boundary defects were closed and section J was added:

**baseline 0 failures · attempted 9 · caught 9 · survived 0.**

Nothing inherited is claimed as newly caught — these are the same nine mutations,
re-proved on the code that ships. **M1** (the executable boundary ignores the
re-approval state) is now caught by both the M4 scenario suite and the `m4_` layer
suite; **M6** (the tenant boundary is lost) still kills the suite outright, which
counts as a detection.
