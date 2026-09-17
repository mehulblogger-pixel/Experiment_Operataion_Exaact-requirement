# PHASE 3 · M5 — BUSINESS INVARIANT MATRIX

An invariant is a sentence about the business that must be true after **every**
path, not after the path somebody happened to test. Each one below names the
control that enforces it and the probe that proves it, so a later change that
breaks the sentence breaks a test rather than a customer.

| # | Invariant | Enforced by | Proved by |
|---|---|---|---|
| **I1** | Every current recruiter assignment belongs to exactly one valid tenant | Tenancy is **structural** — one database per tenant, no `tenant_id` column to forge. The door additionally refuses a person id that is not a row in **this** database (`rasg_recruiter_state()` → `RECRUITER_UNKNOWN`), so an id borrowed from another workspace is not written | `p3m5_assign` **B4**, **L1–L3** |
| **I2** | A recruiter cannot be assigned outside authorised scope | Two separate questions, both asked: the **actor's** scope over the record (`scope_allows`) and the **recruiter's own** scope over that record, evaluated as that person through `appr_as_user()` so the rule cannot drift from `ua()` | **B1**, **B2**, reconciliation **R3** |
| **I3** | Inactive / deactivated recruiters cannot receive new work | `rasg_recruiter_state()` reads **both** markers this codebase uses — `is_active` and `deactivated_at` — because reading one is how a leaver keeps receiving CVs | **B3**, **K1**, **I·create/edit** |
| **I4** | Changing the current recruiter does not erase historical ownership | Append-only `recruiter_assignments` ledger, written on every move, plus an activity row on the audit spine | **F1–F5**, **F8** |
| **I5** | Recruiter KPI counts reconcile with underlying records | One counter (`rasg_workload()`), one definition of live demand (`RASG_LIVE_REQ`), read by the command centre and the tests alike | reconciliation **R1**, **R2** |
| **I6** | Reassignment does not duplicate workload | The move is a single compare-and-swap on one column; the ledger records the handover rather than a second holding | **R5.2–R5.4** |
| **I7** | Unassignment does not create phantom ownership | Unassigning writes `NULL` — never 0, never a placeholder — and `rasg_unassigned()` makes the result visible instead of invisible; `rasg_phantoms()` reports ownership that points at nobody | **F6**, **F7**, **R4**, **L1–L3** |
| **I8** | A requisition blocked by M4 cannot be made executable through recruiter assignment | The door asks `hreq_req_block_reason()` about the requisition behind the record, requisition or candidate alike | **E4**, **E6**, **H1** |
| **I9** | Recruiter assignment cannot bypass Recruitment entitlement | `licence_blocks('mod.hiring.view')` is the **first** question in the door, before permission, and there is no `is_master()` escape inside it | **C1–C3** |
| **I10** | Recruiter assignment cannot bypass permission | The door asks the same band the recruitment write routes already require. It grants nothing new and removes nothing | **B6** |
| **I11** | Concurrent assignment cannot produce an invalid final owner | Compare-and-swap against the expected owner, a matched-row test **and** a read-back; proved with real processes, not a sequential simulation | concurrency **C1–C6**, **M1–M5** |
| **I12** | Historical records remain historically attributable after reassignment | `rasg_ever_held()` over the ledger; and a **finished** requirement (HIRED / CLOSED / CANCELLED) cannot have its owner rewritten at all | **F5**, **R5.5**, state matrix |
| **I13** | No sibling route may bypass the same business control | Ownership left every blind field list; a repository-wide sweep asserts that no library outside the door writes an ownership column | **J1–J7** |

## What each invariant costs when it is false

These are not abstractions — each one was chosen because its failure has a price
a business can name.

- **I1 / I7** — work that belongs to nobody looks like work that belongs to
  somebody. It is never chased, and the dashboard shows *"User #7"* carrying it.
- **I2** — a branch's hiring lands on a person who cannot see that branch.
- **I3** — CVs keep arriving on the desk of somebody who left the company.
- **I4 / I12** — after a reassignment, nobody can say who was accountable last
  quarter. Every performance conversation becomes an argument.
- **I5** — a manager makes decisions from a number the records do not support.
- **I6** — two recruiters each think the other is chasing it.
- **I11** — two managers reassign at once, one silently loses, and the person
  they told is not the person who has it.
