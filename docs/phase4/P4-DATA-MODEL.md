# Phase 4 — Data Model

*One new table. One additive nullable column. One append-only ledger. Nothing
renamed, nothing dropped, nothing re-pointed.*

---

## What was NOT built, and why

The pre-implementation audit (`P4-PREIMPLEMENTATION-AUDIT.md`) went looking for
somewhere to put allocations before proposing a table. Three candidates were
examined and rejected **for stated reasons**, not from preference:

| Considered | Why it cannot host allocations |
|---|---|
| `requisition_groups` | It partitions headcount by **reporting contact and site**, is saved **replace-all** (`DELETE` then re-`INSERT` on every requisition save), and its total **overwrites** `requisitions.quantity`. Allocations kept here would be silently destroyed by an unrelated save, and would corrupt the approved quantity |
| `candidates.source` / `source_type` / `agency` | Free text on a person. Answers "where did *this* one come from", never "how many are we owed". No promise, no remainder, nothing to reconcile |
| A second `requisitions` row per source | The exact failure this phase prevents: twenty people becomes two requirements of twenty. Explicitly ruled out by §19 |

Two things that *were* reused rather than rebuilt:

- **The source vocabulary** extends the existing configurable
  `req_sourcing_model` lookup, already registered in Masters. **No new master.**
- **The source entities** are existing records: `business_partners`,
  `cx_requirements`, `cx_professionals`, `inspectors`. **No new party store.**

---

## `requisition_allocations` — the one new table

One row = one source's promise against one requirement.

| Column | Type | What it holds |
|---|---|---|
| `id` | PK | |
| `requisition_id` | INT NULL | The **one** approved demand this belongs to |
| `source` | VARCHAR(40) | A value of the configurable `req_sourcing_model` list |
| `source_entity_id` | INT NULL | An **existing** record — partner / marketplace requirement / professional / inspector |
| `source_label` | VARCHAR(160) | Display only. **Never an identity** |
| `allocated_qty` | INT | How many people this source is promised |
| `status` | VARCHAR(20) | PLANNED · ACTIVE · FULFILLED · RELEASED · CANCELLED |
| `target_date` | VARCHAR(20) | When they are needed by (optional) |
| `note` | VARCHAR(255) | Free text for a human |
| `created_by/_at`, `updated_by/_at` | VARCHAR | Who and when |
| `closed_by/_at`, `close_reason` | VARCHAR | Who ended it, when, and why |

Indexed on `(requisition_id, status)` and `(source, source_entity_id)`.

**There is no stored total anywhere.** ALLOCATED, SOURCED-FULFILLED,
DIRECT-FULFILLED, COMMITTED, UNALLOCATED and REMAINING are all **derived on
read** from these rows and from `candidates`. A figure that is never stored
cannot drift from the rows it came from — which is why the reconciliation battery
can re-derive every number straight from the tables and demand an exact match.

---

## `candidates.allocation_id` — one additive nullable column

Which source a person arrived through. `NULL` means the direct path, which stays
legitimate (ADR-001) and is counted as DIRECT-FULFILLED.

The pre-existing `candidates.source`, `source_type` and `agency` columns are
**left exactly as they are**. They are free-text provenance on a person; the new
column is a **foreign key into a promise**. Overloading the old ones would have
meant a string comparison deciding a capacity question.

Critically, `allocation_id` is **not in the candidate save's field list**. Like
M5's ownership columns, it left the blind list and travels one door
(`rful_attach()`), because a value that decides capacity must not be writable by
whatever happens to be posted.

---

## `requisition_allocation_events` — the ledger

Append-only. Records `ALLOCATED`, `REALLOCATED`, `RELEASED`, `CANCELLED`,
`ATTACHED`, `DETACHED`, `STATE`, and the three compensating reversals
(`ALLOCATE_REVERTED`, `REALLOCATE_REVERTED`, `ATTACH_REVERTED`, `LINK_REVOKED`)
with the before and after figures, the actor and the reason.

**A refused operation writes nothing here.** The ledger is what happened, not
what was attempted — asserted by probe H3.

---

## Schema discipline (§7)

- **Additive** — three creates and one `ensure_column`. Nothing altered, renamed,
  re-typed or dropped.
- **Forward-only** — no down-migration, because nothing existing was changed.
- **Idempotent** — `CREATE TABLE IF NOT EXISTS`, `ensure_column` is a no-op when
  the column exists, index creation is wrapped and ignored when it already exists.
- **Non-destructive** — a workspace upgraded mid-flight keeps every candidate,
  requisition and count it had. With no allocations, every Phase 4 figure reports
  the honest pre-Phase-4 picture: ALLOCATED 0, everybody DIRECT-FULFILLED.
- **Epoch-guarded** — `rful_migrate()` re-runs when `db_epoch()` changes, and
  **only marks itself done once the candidate column actually landed**, so a
  migration that ran before `candidates` existed does not leave the link column
  missing for the rest of the request.

## Tenancy

**One database per tenant; there is no `tenant_id` column and none was added.**
Isolation is structural. Consequently a source entity from another workspace is
not "filtered out" — it is simply not present, and `rful_source_entity_ok()`
refuses what it cannot find rather than writing an id it could not verify.
