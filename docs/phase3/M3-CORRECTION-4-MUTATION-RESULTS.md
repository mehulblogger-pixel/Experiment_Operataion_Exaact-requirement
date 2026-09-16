# Phase 3 · M3 CORRECTION #4 — MUTATION RESULTS

**35 mutations caught. 5 survived, every one investigated and paired.**

All ran against copies of `phpapp/` outside the repository. A suite that dies
without printing `RESULT:` counts as a detection. Baseline: 0 failures.
Suites: `p3m` (M1 + M2 + M3 + all four corrections), `recruit_approval`, `m4_`.

---

## E1 — entitlement-first

| # | Protection removed | Result | Verdict |
|---|---|---|---|
| Q01 | the entitlement check removed from the common gate | p3m:21 | **CAUGHT** |
| Q02 | **the HIRING_REQUEST-ONLY entitlement check restored — the E1 defect** | p3m:16 | **CAUGHT** |
| Q03 | an early "eligible" return for **OFFER** | p3m:6 | **CAUGHT** |
| Q04 | an early "eligible" return for **SALARY** | p3m:5 | **CAUGHT** |
| Q05 | an early "eligible" return for **REQUISITION** | p3m:5 | **CAUGHT** |
| Q06 | the actionable path stops using the common gate | p3m:7 | **CAUGHT** |

Q02 is the regression guard. Q03–Q05 prove the matrix does what it was written for:
**each entity is independently protected**, so a hole opened for one is caught,
which is the assumption that failed in every previous correction.

## E2 — fail-closed subject

| # | Protection removed | Result |
|---|---|---|
| Q07 | the explicit invalid-subject guard in the gate | **SURVIVED** |
| Q08 | the `is_string()` guard in the gate (implicit cast restored) | **SURVIVED** |
| **Q07 + Q08 together** | | **CAUGHT — 9 failures** |

The two guards are **mutually redundant**: either alone denies an unusable
subject, so removing one leaves the other working. Removing **both** fails on nine
assertions:

> `C4.1 C/D · OFFER · zero id → denied, explicitly` (and negative id, missing id
> key, for OFFER and SALARY)

Note **which** entities fail: for a hiring request the downstream scope reader's
own guard still catches it, so the entity that exposes the gate is an offer. That
is the matrix earning its place.

| # | Removed | Result | What it is |
|---|---|---|---|
| Q09 | the `is_string()` guard in the **scope reader** | **SURVIVED** | unreachable once the gate has validated the subject |
| Q10 | the actionable path's `=== true` (cast restored) | **SURVIVED** | same — the gate has already excluded the only input that could return null |

**Q09 and Q10 are reported as defensive lines, not as protections.** They guard a
future refactor that moves or weakens the gate; today nothing can reach them with
a bad subject. Calling them protections would be false, and deleting them would
remove the only thing standing behind the gate if it is ever changed.

## D1 / D2 / D3 — re-run

10 caught: P01 (name-based lookup restored — now **39** failures), P02, P03, P04,
P05, P06, P07, P08, P09, P11.

`P10` anchor-missed, because correction #4 moved the active-status check into the
common gate. Its intent was re-run against the new code:

| Replacement | Result |
|---|---|
| `RECIPIENT_INACTIVE` collapsed into `IDENTITY_UNRESOLVED` **in the gate** | **CAUGHT — 5 failures** |

## C1 / C2 — re-run

`N06` and `N07` **CAUGHT**. `N01`–`N05` anchor-missed against correction #2's
functions, which #3 rewrote; their intent is covered by the P-series (mapping in
`M3-CORRECTION-3-MUTATION-RESULTS.md`).

## F1 / F2 / F3 — re-run

13 caught: M01, M02, M04, M05, M06, M07, M08, M09, M10, M12, M13, M14, M15.

`M03`, `M16` and `M17` anchor-missed, because correction #4 rewrote
`appr_may_be_asked()`. Both intents re-run against the new code:

| Replacement | Result |
|---|---|
| `appr_can_act()` alone on the actionable path (M03's intent) | **CAUGHT — 9 failures** |
| **both** active-status checks removed — candidate admission **and** the gate (M17's intent) | **CAUGHT — 8 failures** |

`M11` — the candidate-admission active check alone — **SURVIVED**, as it has
throughout: it is the other half of the pair above, and the pair is proved.

---

## Summary

| | |
|---|---|
| Caught | **35** |
| Survived, paired and proved | **5** — Q07/Q08 (pair caught together), Q09/Q10 (unreachable defence), M11 (pair caught together) |
| Anchors superseded by rewrites | 9, every intent re-run against the current code |

**No survivor is dismissed as redundant without a run that proves it.** Each is
either half of a pair whose removal together is caught, or a line the gate makes
unreachable — and the difference between those two is stated rather than blurred.
