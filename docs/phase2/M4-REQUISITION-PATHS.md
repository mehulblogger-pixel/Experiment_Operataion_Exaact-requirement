# Phase 2 · M4 — The Two Routes Into Recruitment

**Status: both routes are SUPPORTED and DELIBERATE.** Neither is a bug, neither
is a duplicate workflow, and M4 removed neither.

## 1. The two paths

### Path A — GOVERNED HIRING REQUEST PATH *(added by M4)*

```
Requestor  ->  Hiring Request  ->  Approval  ->  Recruitment Requisition  ->  Candidates / Fulfilment
               hiring_requests     (Phase 3)     requisitions                 candidates (M3)
```

- The business **asks**; somebody else **approves**; only then may recruitment
  start.
- `hreq_is_executable()` is the single question that decides whether execution
  may begin, and `hreq_to_requisition()` asks it **before** it writes anything.
- Cardinality is **one request → many requisitions**; the total executed can
  never exceed the approved quantity.
- Provenance is recorded: `requisitions.hiring_request_id` points back at the ask.

**Use it when** hiring needs authorisation before money or headcount is
committed — an internal establishment hire, a budgeted position, anything that a
manager or a board has to say yes to.

### Path B — LEGACY / DIRECT REQUISITION PATH *(pre-dates M4, unchanged)*

```
Recruiter  ->  Recruitment Requisition (created OPEN)  ->  Candidates / Fulfilment
               requisitions, hiring_request_id = NULL      candidates (M3)
```

- The requisition is created already `OPEN` — a status whose own label reads
  *"Open (approved, sourcing)"* — because in this path the authorisation
  happened **outside** EXAACT, or does not apply at all.
- `hiring_request_id` is `NULL`, and that NULL means exactly one thing:
  *no hiring request lies behind this record*. Nothing is invented for it.

**Use it when** the demand is already authorised elsewhere: a client's signed
order for contract manpower, a staffing agency working a live client
requirement, a project costing that has already been quoted and won, or a
customer that simply does not run an internal approval step.

## 2. Why both exist — this is not an accident

EXAACT is sold to two kinds of business at once:

- an **employer** hiring into its own establishment, where the request *is* the
  control, and
- a **recruitment / manpower services** business, where the client's order is
  the authorisation and an internal approval step would be pure friction.

Forcing Path A on the second would break a working product. Removing Path B
would break every existing customer and every requisition already in the
database. So both are supported, and the record says which one produced it.

## 3. How they stay one architecture, not two

| Concern | How it stays single |
|---|---|
| The execution record | **one** table, `requisitions`, for both paths |
| Quantity & fulfilment | **one** model, M3's `reqfulfil.php`, for both paths |
| Candidates & hiring | **one** pipeline, for both paths |
| The link | **one** additive, nullable column, `requisitions.hiring_request_id` |
| Who may write that link | **exactly one** function, `hreq_to_requisition()` — verified by test C3 |
| Provenance on screen | the requisition detail says which path produced it |

There is no second requisition table, no second quantity, no second candidate
spine and no second approval engine. The two paths differ only in **how the
requisition came to exist**.

## 4. Can Path B be used to get around Path A's controls?

Audited, with the answer stated plainly.

| Question | Finding |
|---|---|
| Can a browser set `hiring_request_id` on the direct path and fake provenance? | **No.** It is not in `req_extra_fields()` nor in the requisition save field list; `lib/ops.php` never writes the column at all. Test C3. |
| Can a direct requisition be attached to an unapproved request afterwards? | **No.** The only writer is `hreq_to_requisition()`, which refuses unless `hreq_is_executable()` is true. Tests C3, C4. |
| Is Path B a way to escape the module licence? | **No.** Both paths pass `ops_module_gate()` → `can('mod.hiring.view')` → `licence_blocks()` first. |
| Is Path B a way to escape branch scope? | **No.** `req_scope_gate()` guards it, exactly as `hreq_scope_gate()` guards Path A. |

**One asymmetry is recorded, not hidden.** Path A's create gate is now the
capability `mod.hiring.edit`. Path B's create gate is still the role band
`is_coordinator_level()` — untouched by this correction, because §3 of the
correction brief says the direct route is not to be changed here. The practical
effect: a user in the Coordinator role band who holds the hiring module for
*viewing only* can still create a requisition directly, though they could not
raise a hiring request. Every default role the permission matrix grants
`Hiring = Edit` holds `mod.hiring.edit`, so no shipped configuration behaves
differently today. Aligning Path B's gate to the same capability is **option
(d)** in the ADR below.

## 5. What M4 deliberately did NOT decide

M4 does **not** invent a customer policy switch, and there is no setting that
turns Path B off. That decision belongs to Phase 3 and is recorded as an ADR:

→ `docs/adr/ADR-001-direct-requisition-path.md`

## 6. Phase-3 tidy-ups recorded here, not done here

- Extend `hreq_label()` to the older recruitment screens that still print
  `T('requisition')` unqualified (see `M4-TERMINOLOGY-LOCK.md` §5).
- Decide Path B's gate along with the ADR (option (d)).
- Approval routing, matrix, SLA, delegation, escalation, inbox, notifications
  and re-approval all remain Phase 3, as M4 stated.
