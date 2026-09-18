# Phase 4 — Integration Matrix

*What Phase 4 asks of each earlier milestone, what it is forbidden to re-decide,
and what would break if the boundary moved.*

The rule for this phase is narrow and absolute: **Phase 4 answers exactly one
question nobody owned** — *of the authorised headcount, how much is promised to
which source, and how much has arrived?* Everything else is asked of whoever
already owns it.

---

| Milestone | Owns | Phase 4 asks it | Phase 4 may never |
|---|---|---|---|
| **M1 / M2** approvals & authority | Whether a hiring request is approved, and by whom | Indirectly, through M4 | Decide or bypass an approval |
| **M3** multi-vacancy fulfilment | What "filled" means (`REQF_FILLED_STAGES`); the requisition's derived status; `reqf_counts()` | `reqf_counts()` for AUTHORISED and FULFILLED | Write `requisitions.quantity`, `cancelled_qty` or `status` *(S7.3)* |
| **M4** the approved ceiling | Whether a requirement may execute at all; the headcount ceiling; re-approval state | Through M6's gate | Offer a second opinion on executability |
| **M5** recruiter accountability | Who is accountable (`recruiter_id`, `manager_id`) | Nothing — separate question | Use the verb "assign", or write an ownership column |
| **M6** the execution gate | Whether recruitment may spend a seat; **whether anybody holds one** | `rexec_block_reason()` before every allocate and resize | Decide a seat, or remove anybody from one |
| **Licence engine** | What the workspace has bought | `licence_blocks('mod.hiring.view')`, first, always | Bypass it for a master *(S3.9–S3.10)* |
| **Access / scope** | Which branches a person covers | `scope_allows()` on the actor | Accept a record id as proof of authorisation |
| **Lookups** | The configurable vocabularies | `lk_options_or('req_sourcing_model', …)` | Build a second source master |
| **Activity spine** | The audit record | `act_log()` on every accepted change | Log a refusal as if it happened |

---

## The seams, and what is asserted at each

**Phase 4 → M6 (executability).** `rful_exec_block()` is three lines and calls
`rexec_block_reason()`. It contains no rule of its own, so it cannot drift. A
cancelled requirement cannot take a new source or grow one *(S8.1–S8.4)*, and
mutant **T14** — removing the call — is caught.

**Phase 4 → M6 (seats).** The stage route's order is: M6 gates the joining → the
stage is written → M6's compensator settles the **seat** → Phase 4's compensator
settles the **credit**. Phase 4 runs last and can only reduce a credit, so it can
never interfere with a decision M6 has made *(C6, mutant T27)*.

**Phase 4 → M3 (what "filled" means).** `rful_filled_stages()` returns
`REQF_FILLED_STAGES`. There is no second definition of "filled" in this phase. If
M3's definition changes, Phase 4 follows automatically.

**Phase 4 → M3 (AUTHORISED).** Read from `reqf_counts()` as *requested less
cancelled*. So cancelling vacancies lowers the allocation ceiling with no code in
this phase knowing about vacancy cancellation at all.

**Phase 4 → M5 (accountability).** Deliberately no seam. Who is accountable for
chasing the work and which supplier is supplying the people are different
questions, and merging them would mean a supplier change silently reassigning a
recruiter. The engines share no column and no verb.

---

## What would break if a boundary moved

| If Phase 4 were allowed to… | What would break |
|---|---|
| …write `requisitions.quantity` | A sourcing decision would change an **approved** figure — exactly what M4 exists to prevent |
| …refuse a joining | Two engines would decide seats, and they would eventually disagree. M6 is the only one that may |
| …remove a credit **and** a seat | A lost race would eject a real employee. Phase 4's worst outcome must stay "the credit drops to the direct path" |
| …create a requisition per source | One approved demand would become several; the business would be committed to a multiple of what it approved |
| …define its own "filled" | The dashboard, the exports and the allocation panel would drift apart, silently |

---

## Backward compatibility

A workspace that upgrades and never touches sourcing is **unchanged**. With no
allocation rows: ALLOCATED is 0, everybody who joins is DIRECT-FULFILLED, the
panel does not render on requirements with nothing to show, and every pre-Phase-4
count reports exactly what it did before. The candidate form shows the source
picker only when the chosen requirement actually has sources with room.
