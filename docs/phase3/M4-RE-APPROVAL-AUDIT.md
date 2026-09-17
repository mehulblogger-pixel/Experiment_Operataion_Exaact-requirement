# PHASE 3 · M4 — RE-APPROVAL & REQUISITION CONTROL · AUDIT

Inspected in the repository before any code was written. Every row cites what was
actually found.

## A–T · findings

| # | Area | Verdict | Evidence | M4 action |
|---|---|---|---|---|
| A | Hiring Request persistence | **EXISTS · WORKING** | `hiring_requests` (`lib/hiringreq.php:77`), indexed on status/office, `req_no` UNIQUE | REUSE |
| B | Hiring Request lifecycle | **EXISTS · WORKING** | `HREQ_STATUS` — DRAFT/SUBMITTED/UNDER_REVIEW/APPROVED/REJECTED/CANCELLED; `HREQ_EXECUTABLE = ['APPROVED']` | REUSE — **no new status** (see §9 below) |
| C | Requisition persistence | **EXISTS · WORKING** | `requisitions` + `requisitions.hiring_request_id` (additive, NULL for direct) | REUSE |
| D | Quantity handling | **PARTIALLY WORKING → DEFECTIVE** | ceiling enforced **only** at `hreq_to_requisition()` via `hreq_remaining_qty()`; the generic edit path `lib/ops.php:5028` updates `requisitions … quantity` with **no ceiling check**, and `req_groups_save()` then overwrites `quantity` outright | **EXTEND** — the ceiling must hold on every write |
| E | Approval chain | **EXISTS · WORKING** | M1/M2/M3 `appr_start()`, `appr_act()`, `appr_guard()`, matrix, authority, delegation | REUSE — no second engine |
| F | Approval snapshot | **PARTIALLY WORKING** | `snapshot_json` exists and is written **at SUBMIT only** (`lib/hiringreq.php:538`). It is **never written at approval** and **never read back anywhere** | **EXTEND** — snapshot at the decision, and make it readable |
| G | Approval decision writer | **EXISTS · WORKING** | `hreq_apply_decision()` (`:577`) — guards state, writes decision, audits through `act_log` | **EXTEND** — capture the approved snapshot here |
| H | Executable boundary | **EXISTS · UNENFORCED** | `hreq_is_executable()` (`:356`) has **exactly one caller** in the whole product — `hreq_to_requisition()` (`:675`) | **CONNECT** — this is the M4 headline |
| I | Candidate sourcing boundary | **MISSING** | no sourcing, candidate, shortlist, interview or offer path consults the boundary | **CONNECT** |
| J | Candidate ↔ requisition | **EXISTS · WORKING** | `lib/reqfulfil.php` — `reqf_counts()`, `reqf_derive_status()`, `reqf_sync()`, `reqf_cancel()` | REUSE |
| K | Recruiter assignment | **EXISTS · WORKING** | `requisitions.recruiter_id`, Command Centre KPIs | CONNECT |
| L | Approval audit | **EXISTS · WORKING** | the M3 activity spine; `hreq_apply_decision()` already logs | REUSE — **no parallel audit table** |
| M | SLA / notification | **EXISTS · WORKING** | M3 `appr_tick()`, inbox, reminders, escalation | REUSE |
| N | Permissions | **EXISTS · WORKING** | `hreq_can_view()` = `mod.hiring.view`; `hreq_can_create()` = `mod.hiring.edit`; `hreq_can_decide()` = view **+** `is_admin_level()` | REUSE |
| O | Entitlement | **EXISTS · WORKING** | `can()` routes through the licence gate; asked **at the write**, not only on the route (`hreq_save()` opening lines) | REUSE — keep the pattern |
| P | Tenant isolation | **EXISTS · STRUCTURAL** | one database per tenant; no `tenant_id` column to forge | REUSE |
| Q | Branch / office scope | **EXISTS · WORKING** | `hreq_in_scope()` → `scope_allows($r['office_id'])`, enforced inside `hreq_save()` and `hreq_to_requisition()` | REUSE |
| R | Direct URL | **PARTIALLY WORKING** | the hiring-request writers ask the right **at the write**, so a direct hit is refused; the requisition edit path in `lib/ops.php` is a **separate** path with its own guards | VERIFY + EXTEND |
| S | POST / AJAX | **PARTIALLY WORKING** | same as R — the pattern is right for hiring requests, unproven for the requisition quantity path | VERIFY + EXTEND |
| T | Reports / dashboard | **EXISTS · WORKING** | `lib/recruit_cc.php` feeds the Command Centre and already renders `appr_sla_summary()` | REUSE — **no new dashboard** |

## The two findings that define M4

**1 · The boundary is declared and not enforced.** `hreq_is_executable()` is the
documented authoritative gate, and exactly one function asks it. Sourcing,
candidate creation, shortlisting, interviews and offers never do. This is the M3
lesson in a new place: *a truthful value that nothing reads*.

**2 · The headcount ceiling holds at creation and nowhere else.** A requisition
raised from a request cannot exceed the approved headcount. Editing that
requisition afterwards can, because `lib/ops.php:5028` writes `quantity` with no
reference to the approved request, and deployment groups then overwrite it.

**A third, in M4's favour:** an APPROVED hiring request is currently **immutable** —
`hreq_save()` refuses with *"This request is approved. Changing it needs a
re-approval, which is not built yet."* Nothing can silently change an approved
request today, because nothing can change it at all. **M4's job is to open that
door safely, not to close a hole.** That is a much better starting position than
M3 had.

## §9 · The re-approval state model, documented before implementation

`CLAUDE.md` forbids adding a status or transition that is not in
`docs/03-object-lifecycles.md` without asking. **This design therefore adds no
lifecycle status.** The six Hiring Request statuses and the seven Requisition
statuses are untouched.

Re-approval is a **separate, additive attribute** of an approved request:

| `reapproval_state` | means | executable? |
|---|---|---|
| `NONE` | approved and unchanged | **yes** |
| `REQUIRED` | approved, then materially changed | **no** |
| `IN_PROGRESS` | re-approval chain running | **no** |
| `REAPPROVED` | re-approved against the new snapshot | **yes** |
| `REJECTED` | re-approval refused | **no** |

Cancellation stays where it already is — the lifecycle status `CANCELLED`.

The boundary becomes: **executable = status APPROVED *and* `reapproval_state` not
in (REQUIRED, IN_PROGRESS, REJECTED)** — one expression, in the one function that
already exists.

**Decision requiring your confirmation:** if you would rather see re-approval as
first-class *statuses* on the Hiring Request (e.g. `REAPPROVAL_REQUIRED`), that
changes the documented lifecycle and needs your approval plus an update to
`docs/03-object-lifecycles.md` in the same commit. The additive-attribute design
above is the smaller change and is what will be built unless you say otherwise.

## §21 · Direct requisition policy (ADR-001) — observed, not changed

A requisition may be created **directly**, carrying `hiring_request_id = NULL`;
the column was made additive for exactly that reason. `hreq_to_requisition()`
enforces the approval boundary; a directly raised requisition **has no hiring
request to enforce against**. That is the deferred ADR-001 question and this audit
**does not change it**. M4 will state the policy plainly and enforce the ceiling
only where an approved request exists — widening permissions or forcing every
requisition through a hiring request would be a policy change, not a control.
