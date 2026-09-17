# PHASE 3 · M6 — CROSS-MILESTONE BUSINESS INVARIANTS

Twenty sentences about the business that must be true after **every** path.
Each names the control that enforces it and the probe that proves it.

| # | Invariant | Enforced by | Proved by |
|---|---|---|---|
| **I1** | No recruitment execution before an approved hiring request | `hreq_is_executable` via `rexec_block_reason` | lifecycle L3, L5 |
| **I2** | A material change after approval requires re-approval where M4's matrix says so | M4's `hreq_material_diff` — **not re-implemented** | L3.1, L7.3 |
| **I3** | A blocked request cannot be bypassed through a downstream candidate, interview, offer or joining path | the one gate, asked at all four | L3.2–L3.8, L4 |
| **I4** | A requisition cannot exceed the authorised quantity | M4's `hreq_qty_guard` | reconcile R6.4 |
| **I5** | One joining does not close a multi-seat requirement | M3's `reqf_derive_status` | L1.14, R3.1 |
| **I6** | Recruiter assignment is valid for the **resulting** requisition, not the source | M5 `rasg_row_scope($opt)` | M5 P4, P5 |
| **I7** | A recruiter cannot own work outside their tenant / branch / scope | `rasg_user_covers` + structural tenancy | S2.5, S7.5 |
| **I8** | Recruiter ownership changes **only** through the M5 door | `rasg_assign` + the compensator | M5 J1–J7, N |
| **I9** | The dashboard and the workload counter use one counting rule | `rasg_cand_scope`, `RASG_LIVE_REQ` | R2, R4, M5 P6 |
| **I10** | Candidate progression cannot bypass the execution boundary | stage route + **pipeline engine** | L3.7, L3.8 |
| **I11** | An offer cannot bypass the execution boundary | `offer_guard` on create / submit / approve / issue / accept | L3.5, L4.3, L4.5 |
| **I12** | A joining cannot exceed the available quantity | the gate's `JOIN` action **and** the compensating revert | L2.3, L2.6, C1, C2 |
| **I13** | Tenant A cannot observe or modify tenant B | one database per tenant; ids simply absent | S7.1–S7.9 |
| **I14** | Branch A cannot reach branch B unless authorised | `scope_allows`, `hreq_in_scope`, scoped dashboards | S2.1–S2.7 |
| **I15** | No entitlement means no functionality, on any surface | `can()` asks `licence_blocks` **before** the master flag | S6 |
| **I16** | Rejected / cancelled / closed states cannot be bypassed by a direct call | the gate reads the state itself | L5 |
| **I17** | Concurrent users cannot create an invalid final state | CAS + compensating reverts | C1–C6 |
| **I18** | Audit history never claims an operation that did not happen | refusals write nothing; reverts are audited as reverts | L2.8, M5 F8, mutation M18 |
| **I19** | A stale screen cannot overwrite newer authoritative state | M5's baseline test; the gate re-asked at the write | S5.1–S5.4 |
| **I20** | Database, service, dashboard and export reconcile | one WHERE builder, one counter | R1–R6 |

## The two sentences that cost the most when false

- **I11 / I12** — an offer is a commitment to a person, and a joining is a seat.
  Before M6 both could be done against headcount nobody had approved, and three
  people could join a two-seat requirement. That is a promise the business cannot
  fund and a payroll it did not agree to.
- **I18** — if a failed operation leaves a record saying it succeeded, every other
  invariant becomes unprovable.
