# Phase 4 — Action Path Sweep

*Every way a fulfilment allocation or a source credit can be created, changed or
destroyed — including the ones nobody would think of as a "screen" — and what
each path asks before it writes.*

The sweep exists because the recurring defect family across this whole programme
has been **a rule applied where somebody remembered to apply it**: helper fixed
but not caller, entity but not sibling, origin but not destination.

---

## Paths that change an ALLOCATION

| Path | Function reached | Gates asked | Notes |
|---|---|---|---|
| Requisition screen → "Add source" | `rful_allocate()` | all five + ceiling | Carries `expect_allocated`, so a stale form is refused |
| Requisition screen → "Change" | `rful_reallocate()` | all five + ceiling + `BELOW_FULFILLED` | Carries `expect` |
| Requisition screen → "Give back" | `rful_close('RELEASED')` | entitlement, permission, record, scope | **Not** gated on executability — tidying up is not execution |
| A crafted POST to `/requisition-allocations` | the same three | identical | The route is a thin shell; it decides nothing |
| Any future caller | the same three | identical | The engine asks; callers do not re-implement |

There is **no** other writer of `requisition_allocations` in the codebase.

---

## Paths that change a SOURCE CREDIT (`candidates.allocation_id`)

This is the dangerous half, because the candidate row is written by several
paths that predate Phase 4.

| Path | What Phase 4 does | Probe |
|---|---|---|
| Candidate **create** (`candidate-new`) | `allocation_id` is **not** in the INSERT column list. The row is created unlinked; the compensator runs; then the link is set through `rful_apply_posted()` | mutant T30 |
| Candidate **edit** (`candidate-edit`) | Ownership-style: the field leaves the blind list, `rful_apply_posted()` runs **before** the write, asked about the requirement the save is **producing**; `rful_enforce_candidate()` runs after | mutant T10 |
| Candidate **stage move** (`candidate-stage`) | The move is what *credits* a source, so `rful_enforce_candidate()` settles the source's ceiling after M6 has settled the seat | mutant T27 |
| Public **careers intake** | Creates a candidate with no `allocation_id` and no way to supply one; the column is not in its field list | — |
| A raw `UPDATE candidates SET allocation_id=…` | Undone on the next pass of `rful_enforce_candidate()` | E3–E4 |
| Any future blind field list | Same — the compensator re-reads the row rather than trusting the writer | T9, T34 |

---

## Why the compensator exists as well as the door

A door only protects the callers that walk through it. The compensator re-reads
the row **after** every write path and removes any link that:

- points at an allocation that does not exist,
- points at **another requirement's** allocation,
- points at a source already credited to its promise.

It applies the ratified capacity rule when it acts: the **established** credits
stay, the **arriving** one goes. It never removes anybody from a seat — the worst
it can do is drop a credit back to the direct path *(D8, C2.5, J10, mutant T23)*.

---

## What Phase 4 deliberately does NOT touch

| Path | Why it is untouched |
|---|---|
| Offer create / submit / approve / issue / accept | Offers are not seats. M6 gates them; a source credit changes nothing about them |
| Interview scheduling and outcomes | Recording what happened must never be refused |
| Closing a candidate out (reject / withdraw / decline / hold) | A person must never be trapped by a sourcing question |
| `requisitions.quantity`, `cancelled_qty`, `status` | M3 and M4 own these. Phase 4 **reads** them and writes none *(S7.3)* |
| `candidates.source`, `source_type`, `agency` | Pre-existing free-text provenance. Left exactly as they were |
| The requisition cost model (`req_sourcing_model` on the requisition) | A promise of people is a different question from what they cost |

---

## Read paths

`rful_summary()` is the single derivation used by the panel, the candidate
picker, the reconciliation battery and every test. There is **no second
calculation** anywhere, so no two screens can disagree; and because nothing is
stored, no figure can drift from the rows *(the whole `$r4check`)*.
