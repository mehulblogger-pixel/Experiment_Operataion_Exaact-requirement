# Phase 4 — Negative Security Matrix

*What must be refused, why it is refused, and the probe that proves it is refused
at the **write** rather than by the screen.*

**Hidden button ≠ security. Disabled button ≠ security. Locked screen ≠
security.** Every probe below calls the production function a crafted POST would
reach, with no screen in the way, and asserts **both** that the operation was
refused **and** that nothing was written.

---

## The order the questions are asked, and why it is load-bearing

```
1. entitlement   — has this workspace bought hiring?       (no master bypass)
2. permission    — is this person a coordinator or above?
3. the record    — does the requirement exist?
4. branch scope  — is it in THIS person's office / BU?
5. executability — may this requirement be executed at all? (M6 → M4)
6. the values    — is the source real, the quantity a whole number of people?
7. the ceiling   — is there a seat left to promise?
```

Steps 1–4 are decided **about the person**, before anything that would describe
the record. That is what stops a refusal being used as an oracle: a coordinator
from another branch asking for 99,999 people gets `OUT_OF_SCOPE`, not
`OVER_AUTHORISED`, so refusals cannot be used to probe another branch's approved
headcount *(S9.1)*. An inspector gets `NO_PERMISSION` for the same call *(S9.3)*.

---

## The matrix

| # | Attack | Must happen | Probe | Mutant |
|---|---|---|---|---|
| N1 | A non-coordinator promises seats to a source | `NO_PERMISSION`, nothing written | S1.1–S1.2 | T12 |
| N2 | …resizes, cancels or credits | `NO_PERMISSION`, nothing moves | S1.3–S1.8 | T12 |
| N3 | A real coordinator acts on **another branch's** requirement | `OUT_OF_SCOPE`, nothing written | S2.1–S2.6 | T13 |
| N4 | A workspace without hiring sources anything | `NO_ENTITLEMENT`, nothing written | S3.1–S3.8 | T11 |
| N5 | A **superuser** on that workspace does the same | `NO_ENTITLEMENT` — **no master bypass** | S3.9–S3.10 | T11 |
| N6 | A source that is an array / word / boolean / negative / `1e3` / empty | `BAD_SOURCE`, never coerced | S4 (7 probes) | T17 |
| N7 | A quantity that is an array / word / boolean / `2.5` / `-2` / `0` / `2e1` / `" 2 "` | `BAD_QUANTITY`, never coerced | S4 (8 probes) | T15 |
| N8 | An allocation id that is an array / word / negative / fraction | `BAD_VALUE` | S4 link (4 probes) | T16 |
| N9 | A **malformed** link value on a person who already has a credit | `BAD_VALUE` — and the credit is **not** cleared | S4.24–S4.25 | T16 |
| N10 | A supplier id from another workspace / from nowhere | `SOURCE_ENTITY_UNKNOWN` | S5.1, S5.6 | T24 |
| N11 | A supplier named on a source type that has none | `SOURCE_ENTITY_UNKNOWN`, not ignored | S5.5 | — |
| N12 | A marketplace source without the marketplace module | `NO_ENTITLEMENT` — refused, not hidden | S5.7 | T25 |
| N13 | A form left open while somebody else allocated | `STALE`, not added on top | S6.1–S6.2 | T20 |
| N14 | A **malformed** stale expectation | `STALE` — never ignored | S6.6–S6.7 | T20 |
| N15 | A stale resize | `STALE`, the quantity does not move | S6.4–S6.5 | — |
| N16 | Sourcing turned into a second requisition | Impossible — one row, quantity untouched | S7.1–S7.3, A11–A12 | — |
| N17 | An allocation re-pointed at another requirement, then credited | `NO_ALLOCATION` | S7.4, E1 | T8 |
| N18 | A link written by **raw SQL** past the door | Undone by the compensator | E3–E4 | T9, T34 |
| N19 | A link **smuggled through the create path's POST** | Never reaches the column; the door decides | — | T30 |
| N20 | Sourcing a CANCELLED requirement | `EXECUTION_BLOCKED`, nothing written | S8.1–S8.4 | T14 |
| N21 | Growing an allocation on a blocked requirement | `EXECUTION_BLOCKED`, it does not move | S8.3–S8.4 | T14 |
| N22 | Giving seats **back** on a blocked requirement | **Allowed** — tidying up is not execution | S8.5 | — |
| N23 | A requirement that does not exist | `NO_REQUISITION` and nothing more | S9.2 | — |
| N24 | Crediting a source past its promise | `OVER_ALLOCATED`, the person keeps their seat | D5–D8 | T5, T32 |
| N25 | Promising a seat somebody already filled directly | `OVER_AUTHORISED` | J3–J6 | T2 |
| N26 | Cutting an allocation below what it delivered | `BELOW_FULFILLED` | G2–G3 | T7 |
| N27 | Acting on a closed allocation | `BAD_STATE` | F7–F9 | T22, T28 |
| N28 | Two processes promising the last seats at once | Never both written | C1 | T3, T31 |
| N29 | Two arrivals for one remaining source seat | Never both credited | C2 | T6, T32 |
| N30 | An arriving claim pushing out an established holder | **Never** — the arrival is refused | C3 | T23 |

---

## What is deliberately NOT refused, and why

Asserting what a control must *not* do matters as much as what it must:

- **A candidate carrying a source link before they join.** The agency sent them;
  that is true whether or not they are hired. `rful_fulfilled()` counts only
  people in a filled stage, so such a link consumes nothing *(C6.7)*.
- **Giving seats back on a blocked or cancelled requirement.** Refusing this
  would strand capacity and force people around the system *(S8.5)*.
- **A person found directly, with no source at all.** ADR-001; the pre-Phase-4
  world produces them, and a workspace that never uses sourcing must keep working
  exactly as before.
- **Trimming an over-committed allocation.** The correction must never be the
  thing that is refused *(J13)*.
- **Refusing *both* contested claims under a dead heat.** Permitted by the
  ratified capacity rule. "Somebody must win" is not an invariant of this system;
  "nobody is overfilled and nobody is displaced" is *(C1.6)*.
