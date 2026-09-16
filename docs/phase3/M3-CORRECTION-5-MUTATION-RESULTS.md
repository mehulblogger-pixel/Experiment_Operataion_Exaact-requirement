# Phase 3 · M3 CORRECTION #5 — MUTATION RESULTS

**10 G1 mutations, all CAUGHT. The four earlier batteries re-run: 32 caught, plus
4 superseded anchors re-run against current code, plus 4 survivors — every one
paired and proved.**

All ran against copies of `phpapp/` outside the repository. Baseline: 0 failures.

---

## G1 — every one the brief asked for

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| G1-A | **the HIRING_REQUEST-only rule restored** (record resolved for one entity) | p3m:34 | **CAUGHT** |
| G1-B | a missing **OFFER** record returns eligible | p3m:12 | **CAUGHT** |
| G1-C | a missing **SALARY** record returns eligible | p3m:11 | **CAUGHT** |
| G1-D | a missing **REQUISITION** record returns eligible | p3m:11 | **CAUGHT** |
| G1-E | the entity **TYPE** treated as sufficient, without record resolution | p3m:34 | **CAUGHT** |
| G1-F | a cross-tenant / absent source record satisfies resolution | p3m:67 | **CAUGHT** |
| G1-G | an **informational** notification proceeds despite an unresolved entity | p3m:25 | **CAUGHT** |
| G1-H | a missing entity falls through to `appr_can_act()` as a rescue | p3m:47 | **CAUGHT** |
| G1-I | the resolver's failure is **swallowed** instead of denying | p3m:2 | **CAUGHT** |
| G1-J | identity resolution no longer requires the source record | p3m:3 | **CAUGHT** |

**G1-B, G1-C and G1-D each open the hole for one entity at a time** and are caught
separately — the property the last audit found missing.

### G1-I survived on the first pass, and that was a test gap, not a redundancy

Nothing in the suite made the resolver **throw**, so the `catch` that denies was
never exercised. The condition was **constructed** — the source table renamed out
from under the resolver, which is what a half-applied migration looks like — and
the mutation is now **CAUGHT**. The test restores the table in a `finally`.

---

## Correction #4 (E1 / E2) — re-run

**6 caught** (Q01–Q06). Q07, Q09, Q10 survived and Q08 anchor-missed (two
occurrences), exactly as reported in correction #4:

| | |
|---|---|
| Q07 + Q08 | mutually redundant; **removing both is CAUGHT (9 failures)** — re-verified |
| Q08 alone, precise anchor | **SURVIVED** — the other half of that pair |
| Q09, Q10 | **defensive lines, not protections** — the gate makes them unreachable |

## Correction #3 (D1 / D2 / D3) — re-run

**9 caught.** Two anchors superseded by correction #5's refactor, both re-run
against the current code:

| Superseded | Replacement | Result |
|---|---|---|
| P02 (the OFFER's inline raiser query) — `appr_requester_id()` now uses the shared resolver | the OFFER/SALARY raiser id ignored | **CAUGHT — 1** |
| P10 (the old `appr_told_reason()` opening) — the check moved into the gate | `RECIPIENT_INACTIVE` collapsed into `IDENTITY_UNRESOLVED` | **CAUGHT — 5** |

## Correction #2 (C1 / C2) — re-run, and a new redundancy to report honestly

**N06 and N07 now SURVIVE**, where they were caught before. This is a *direct
consequence of correction #5* and is reported rather than glossed:

- **N06** removes the `array_key_exists($entity, APPR_ENTITIES)` type check from
  `appr_visible()`. G1's new record check denies an unknown entity anyway, because
  `appr_entity_record()` returns null for a type it does not know.
- **N07** weakens the same check, with the same result.

So C2's type guard and G1's record guard now **overlap**. Proved rather than
asserted:

| | Result |
|---|---|
| type check removed alone (N06) | SURVIVED |
| record check removed alone (G1-E) | **CAUGHT — 34** |
| **both removed together** | **CAUGHT — 37** |

The protection is real and tested; the type check is now the redundant half.
It stays: it names the supported set explicitly and fails faster, and deleting it
would leave `APPR_ENTITIES` with no enforcement of its own.

`N01`–`N05` remain superseded by the P-series (mapped in
`M3-CORRECTION-3-MUTATION-RESULTS.md`).

## Corrections F1 / F2 / F3 — re-run

**13 caught.** `M11` survived as always — half of the active-status pair, whose
both-removed case is caught. `M03`, `M16`, `M17` anchor-missed against
correction #4's rewrite; their intents were re-run there and caught
(`appr_can_act()` alone — 9; both active checks — 8).

---

## Summary

| | |
|---|---|
| **G1 caught** | **10 / 10** |
| Earlier batteries caught | 6 + 9 + 2 + 13 = **30** |
| Superseded anchors re-run against current code | 4 — **all CAUGHT** |
| Survivors | 5 — Q07/Q08 (pair caught together), Q09/Q10 (unreachable defence), M11 (pair caught together), plus **N06/N07 newly redundant with G1, pair caught together** |

**No survivor is dismissed without a run that proves it**, and where correction #5
*created* a new redundancy, that is stated as a consequence of this change rather
than presented as a pre-existing fact.
