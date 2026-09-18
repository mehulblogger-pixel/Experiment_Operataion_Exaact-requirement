# PHASE 4 — PRE-IMPLEMENTATION AUDIT

Read before any code: the Phase-0 architecture lock, the Phase 1–3 evidence, the
M1–M6 completion reports, the permission matrix, the recruitment schema, the
marketplace engine, and the organisation, workforce and operations surfaces.

## The fifteen questions

| # | Question | What the repository actually has |
|---|---|---|
| 1 | What represents **recruitment demand**? | `hiring_requests` — the approved business demand. `hreq_approved_qty()` is the approved figure, captured in the approval snapshot at the decision (M4) |
| 2 | What represents a **requisition**? | `requisitions` — the authorised execution object. `quantity` is its authorised headcount; `hiring_request_id` ties it to the demand; M4's `hreq_qty_guard()` stops allocation exceeding the approved figure |
| 3 | What represents a **Marketplace requirement**? | `cx_requirements` (`connect_market.php`) — its own engine, with `positions`, applications (`cx_applications`), engagements. **Separate, and staying separate** |
| 4 | What represents a **supplier / agency**? | `business_partners`, with `is_client` / `is_vendor` / `is_subcontractor` flags — the canonical organisation master. Also `subcons` (a sub-contractor **person** roster keyed by a free-text `agency` name) and `cx_organisations` (marketplace-side org identity) |
| 5 | What represents a **freelancer / consultant**? | `cx_professionals` (marketplace professional identity) and `inspectors` (the workforce person). `REQ_SOURCING_MODELS` already names `FREELANCER` as a cost model |
| 6 | What represents an **internal / workforce source**? | `inspectors` (the workforce record) and `users` (staff). `OWN_PAYROLL` is the existing cost model |
| 7 | What represents a **candidate / professional**? | `candidates` (recruitment) and `cx_professionals` (marketplace) — connected, never merged. `candidates.source`, `source_type` and `agency` already exist |
| 8 | What **source / cost fields** exist? | `requisitions.sourcing_model` + the cost build-up (`cost_wage`, `cost_statutory_pct`, `cost_agency_pct`, `cost_reimburse`, `cost_oneoff`) — **one model per requisition** |
| 9 | Source logic in **Recruitment**? | `REQ_SOURCING_MODELS` drives the per-person cost build-up (`req_cost_buildup`). It answers "how do we pay for this person", not "how many from each source" |
| 10 | Source logic in **Marketplace**? | Its own posting → application → award → engagement chain, with its own entitlement (`connect_enabled()`) |
| 11 | Operations fulfilment logic? | `reqfulfil.php` — M3's multi-vacancy counter: `requested`, `filled`, `in_progress`, `lost`, `cancelled`, and the derived requisition status |
| 12 | Which tables already carry **source** information? | `requisitions.sourcing_model`; `candidates.source` / `source_type` / `agency`; `subcons.agency`; `cx_requirements` (marketplace as a channel) |
| 13 | Which routes create **source-specific demand**? | None. Every route creates a requisition; the source is a *cost attribute* of it |
| 14 | Where can **duplicate requirements** be created today? | `requisition-new` (direct), `hreq_to_requisition()` (from an approved request, ceiling-guarded), `pc_line_to_requisition()` (from a costing line). Nothing stops a person raising a second requisition for the same demand by hand — M4 guards the **approved quantity**, which is the control that matters |
| 15 | Which downstream modules consume requisition **quantity**? | `reqfulfil` (status derivation), the command centre (`rcc_data` — ordered/filled/open seats), the recruiter workload counter (`rasg_workload`), the CSV export, `requisition_groups` (which **overwrites** quantity), and M4's ceiling |

## The finding that decides the architecture

**`requisition_groups` is not an allocation table.** It partitions a requisition's
headcount by **reporting contact and site**, it is saved **replace-all**
(`DELETE` then re-`INSERT` on every requisition save), and its total
**overwrites** `requisitions.quantity`.

Using it for fulfilment sources would therefore:

- destroy allocation history on every requisition save;
- invert the ceiling rule — groups *set* the quantity instead of being bounded by it;
- and conflate *where a person reports* with *who supplies them*, which are
  independent (an agency person and an internal person can report to the same site).

So allocations need their own record. Everything else is reused.

## REUSE → EXTEND → CONNECT → MAP → BUILD

| Need | Decision | Why |
|---|---|---|
| Approved demand | **REUSE** `hiring_requests` + M4's approved snapshot | it is already the authority |
| Authorised execution quantity | **REUSE** `requisitions.quantity` + M4's ceiling | unchanged |
| "Filled" definition | **REUSE** `REQF_FILLED_STAGES` / `reqf_counts()` | M3 owns counting |
| Execution boundary | **REUSE** `rexec_block_reason()` (M6), which asks M4 | one gate |
| Recruiter accountability | **REUSE** `rasg_assign()` (M5) | one door |
| Source vocabulary | **EXTEND** the existing configurable lookup `req_sourcing_model` | already registered in Masters; adding values needs no code |
| Source entity | **MAP** to existing entities — `business_partners` (agency / supplier / client bench), `cx_requirements` (marketplace), `cx_professionals` / `inspectors` (freelancer, internal), `users` (internal) | no new organisation, professional or agency master |
| Candidate → source | **EXTEND** `candidates` with one additive column | `source`, `source_type`, `agency` already exist and are preserved |
| Allocation record | **BUILD** one table — `requisition_allocations` | nothing existing can hold (allocated, fulfilled) per source under one requisition without the damage described above |

**One table is built. Nothing else.**

## What is deliberately NOT built

- No second approval, SLA, notification, ownership, candidate, identity, KPI or
  dashboard engine.
- No merge of `requisitions` and `cx_requirements`.
- No new agency / supplier / client / organisation master.
- No source-specific UI workflows — the business demand is common, and source
  execution runs through the existing recruitment lifecycle. Where a source needs
  its own screen, that is a later decision, recorded rather than pre-empted.
