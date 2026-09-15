# Phase 2 · M3 — Requisition Structure

## 1. What a requisition is — and is not (§3)

A requisition is **a requirement for one or more fulfilments**. It is not a
Position, a Candidate, a Hire, an Employee, an Offer or an Appointment, and M3
keeps all of those distinct.

```
Requisition RQ-00125
   Job / designation : Mechanical Engineer
   Department        : Engineering   (canonical identity)
   Position          : P-00045       (the sanctioned seat it draws on)
   Required          : 10
```

It stays open until the requirement is met, cancelled or explicitly closed.

## 2. The chain (§20)

```
ORGANISATION → DEPARTMENT → POSITION → REQUISITION → FULFILMENTS → CANDIDATE / OFFER / JOINING
```

| Level | Where it lives | Answers |
|---|---|---|
| Department | `lookup_values` + `department_id` | which function |
| Position | `positions` | which sanctioned seat, under whom, how many heads |
| Requisition | `requisitions` | what is being asked for, how many, by when |
| Fulfilment | `candidates` rows | who is filling each seat, and how far they got |
| Joining | `candidates.inspector_id` → `inspectors` | who actually arrived |

**Position ≠ Vacancy ≠ Requisition** (§9). A position is an establishment seat;
a vacancy is an unfilled requirement against it; a requisition is the request to
fill some. `requisitions.position_id` links the requisition to the seat, and the
five-case manpower check (A–E) already validates against sanctioned headcount.

## 3. The fields that already existed (§6)

Audited before anything was added. `requisitions` carries **79 columns**;
nothing in this list was duplicated:

| Concept | Column | Concept | Column |
|---|---|---|---|
| number | `req_code` | quantity | `quantity` |
| designation | `designation` | start date | `start_date` |
| grade | `grade` | end date | `end_date` |
| department (text) | `department` | duration | `duration_months` |
| **department (identity)** | `department_id` *(added)* | status | `status` |
| position | `position_id` | requestor | `created_by` |
| branch | `office_id` | recruiter | `recruiter_id` |
| business unit | `sbu` | manager | `manager_id` |
| client | `client_id` | sourcing model | `sourcing_model` |
| deployment site | `deploy_location`, `project_site` | cost build-up | `cost_*` |
| description | `careers_summary`, `responsibilities` | commercials | `billing_rate`, `target_margin` … |

**Added by M3, and only these:** `cancelled_qty`, `cancel_reason`, `closed_at`,
`closed_by`, `closure_reason`.

## 4. The quantity finding, resolved (§5)

The earlier finding recorded that `quantity` and `start_date` were created by a
**lazy, route-triggered** migration: a fresh database did not carry them until
somebody happened to open a requisition page. §29 forbids exactly that.

`req_migrate()` and `reqf_migrate()` are now called from `boot()`. Both are
additive and epoch-guarded, so this is idempotent, and a freshly started
database reports **79 columns** on `requisitions` instead of 25.

**No second quantity field was created**, and no quantity data was removed. A
full-schema scan for other quantity-like columns found `cx_positions.quantity`
(a Marketplace requirement line), `cx_requirements.positions`,
`requisition_groups.headcount` and `dep_manpower.required` — all different
objects, all left alone. See `M3-REQUIREMENT-REQUISITION-MAP.md`.

## 5. Department (§19)

Requisition and candidate entry both use the canonical Department: the record
carries `department_id` for identity and keeps the text column populated so
every existing reader is unaffected. Customer wording such as `Engg` or
`Engineering Dept` resolves through the approved-term layer. Detail in
`M3-DEPARTMENT-FORMS.md`.

## 6. Target dates (§18)

Audited, not duplicated. `start_date`, `end_date` and `duration_months` already
exist and are now reliably present on a fresh install. `recruit_req_health()`
already reads `start_date` to judge whether a requirement is on track. **No new
date concept was created** and no KPI/SLA engine was built — that is Phase 3+.

## 7. Requestor and recruiter (§24)

Already present: `created_by` (requestor), `recruiter_id` (Responsible 1) and
`manager_id` (Responsible 2), all offered on the requisition form. M3 reused
them and built no assignment workflow — that is Phase 3.

## 8. Sourcing (§21)

`sourcing_model` and the `cost_*` build-up already distinguish own payroll, a
manpower agency, a sub-contract agency and a freelancer. M3 changed none of it.

Nothing in the model prevents one requirement being met from several sources
later: fulfilments are individual candidate rows, and each candidate already
carries its own `source`, `agency` and `roll_type`. Multi-source **allocation**
is deliberately not implemented (§45).

## 9. Scope, permissions and entitlement (§25, §26, §32)

Unchanged, and reused rather than re-implemented:

- the requisition module keeps its single branch-scope gate (`req_scope_gate()`,
  added in M14), and the new cancel route runs it **before** reading an id;
- the same coordinator-level bar as every other requisition action;
- `/requisitions` and its routes remain gated to the paid **hr** module;
- no `is_master()` override was introduced anywhere in M3, so a master user
  cannot bypass entitlement.
