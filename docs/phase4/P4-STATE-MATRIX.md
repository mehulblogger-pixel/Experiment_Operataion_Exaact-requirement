# Phase 4 — State Matrix

*Every allocation state, every transition, who may cause it, and what is refused.
The lifecycle itself is in `docs/03-object-lifecycles.md` → Fulfilment
allocation; this document is the operational table behind it.*

---

## The states

| State | Holds seats? | Can take people? | Set by |
|---|---|---|---|
| **PLANNED** | Yes | Yes | A person, on creating the allocation |
| **ACTIVE** | Yes | Yes | **Derived** — somebody has arrived through it |
| **FULFILLED** | Yes | Only if room re-appears | **Derived** — everybody promised has arrived |
| **RELEASED** | Only what it delivered | No | A person — gives the rest back |
| **CANCELLED** | Only what it delivered | No | A person — the source will not deliver |

ACTIVE and FULFILLED are computed by `rful_sync_state()` from how many credited
people are in a filled stage. They are **never typed in**, so the state and the
numbers cannot disagree. FULFILLED is **not** a closed state: it is a live source
that happens to be full, and it returns to ACTIVE by itself if a credited person
later leaves a filled stage.

---

## The transition table

| From | To | Allowed? | Rule |
|---|---|---|---|
| — | PLANNED | Yes | `rful_allocate()` — passes all five gates and the ceiling |
| PLANNED | ACTIVE | Auto | First credited arrival |
| ACTIVE | FULFILLED | Auto | Credited arrivals reach the promise |
| FULFILLED | ACTIVE | Auto | A credited person leaves a filled stage |
| PLANNED / ACTIVE / FULFILLED | RELEASED | Yes | `rful_close()`; pinned to what it delivered |
| PLANNED / ACTIVE / FULFILLED | CANCELLED | Yes | `rful_close()`; pinned to what it delivered |
| RELEASED / CANCELLED | *anything* | **No** | Terminal. `BAD_STATE` *(F7, F8, F9, F13, F14)* |
| any | a state not in the list | **No** | `BAD_STATE` *(F13)* |

---

## Quantity changes, by state

| State | Resize allowed? | Refusal |
|---|---|---|
| PLANNED, ACTIVE | Yes, within the rules below | — |
| FULFILLED | Yes — it can be grown, or cut to what it delivered | — |
| RELEASED, CANCELLED | **No** | `BAD_STATE` *(F8, mutant T22)* |

And within an open state:

| Attempt | Result |
|---|---|
| Below what the source already delivered | `BELOW_FULFILLED` *(G2)* |
| Above what the requirement has left | `OVER_AUTHORISED` *(C2)* |
| Above the ceiling **while already over-committed** | `OVER_AUTHORISED` *(J12)* |
| **Down**, while already over-committed | **Allowed** — trimming is the correction *(J13)* |
| Same number | `NO_CHANGE`, nothing written *(C7)* |
| Zero, negative, a fraction, a word, an array | `BAD_QUANTITY` *(C8–C12)* |
| Against a stale expectation | `STALE` *(S6.4)* |
| Against a **malformed** expectation | `STALE` — never ignored *(S6.6)* |
| While another process is resizing it | `LOST_RACE` *(C4.4)* |

---

## Crediting a person, by state

| Allocation state | Credit allowed? | Refusal |
|---|---|---|
| PLANNED, ACTIVE, with room | Yes | — |
| PLANNED, ACTIVE, full | No | `OVER_ALLOCATED` *(D5)* |
| FULFILLED | Only if room re-appeared | `OVER_ALLOCATED` |
| RELEASED, CANCELLED | No | `BAD_STATE` *(F9)* |
| Belongs to another requirement | No | `NO_ALLOCATION` *(E1)* |
| Id is an array, a word, a negative, a fraction | No | `BAD_VALUE` *(S4 link)* |

**Clearing** a credit (posting an empty value) is a legitimate operation and is
distinguished from a malformed one: `''` and `null` mean *clear*; `"abc"` and
`[1]` are `BAD_VALUE` and change nothing. That distinction is load-bearing —
treating a malformed value as "nothing" would silently delete a true record of
where somebody came from *(S4.24–S4.25, mutant T16)*.

---

## What closing does to the numbers

Closing pins `allocated_qty` down to **exactly what the source delivered**:

```
before   RELEASED   6 promised, 1 arrived
after               1 promised, 1 arrived     →  5 seats returned to the requirement
```

The delivered person keeps their seat, keeps their credit, and stays in the
source's history. Only the undelivered remainder becomes sourceable again
*(F1–F6, R3.9–R3.10, mutant T21)*.
