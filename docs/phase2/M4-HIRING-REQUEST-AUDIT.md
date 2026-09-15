# Phase 2 · M4 — Hiring Request Audit

*Produced before any change, per §2. Nothing was modified to produce it.*

## 1. The headline

**There is no request layer.** A requisition is created directly, already
`OPEN` — a status whose own label reads *"Open (approved, sourcing)"* — and
candidates can be attached to it immediately. Nothing structurally distinguishes
*"we would like to hire"* from *"this is approved, start sourcing"*.

That is the one thing §18 requires and the current model cannot express. It is
the demonstrated architectural need for M4, and it is the only new object M4
introduces.

Almost everything else M4 asks for already exists and is reused.

## 2. Does a hiring request already exist, under another name? (§2)

Searched all 308 tables for request/hiring/indent/demand/ticket concepts:

| Table | What it actually is |
|---|---|
| `recruit_approval_requests` | the **existing approval engine's** request record — generic (`entity`, `entity_id`) |
| `portal_requests`, `data_requests`, `tenant_requests` | client-portal, GDPR and tenant provisioning — unrelated |
| `quote_edit_requests`, `stage_gate_requests` | Sales and project gates — unrelated |

**No hiring request exists.** But `recruit_approval_requests` matters: it is
entity-agnostic, so a hiring request can plug into the approval engine later
without a new engine — exactly what §17 requires.

## 3. What `requisitions` already carries

**35 of the 45 fields M4 asks about already exist** on `requisitions` and are
reused rather than rebuilt:

`req_code` · `office_id` · `sbu` · `department` · `department_id` ·
`designation` · `grade` · `position_id` · `quantity` · `req_type` · `status` ·
`created_by` · `recruiter_id` · `manager_id` · `client_id` · `project_site` ·
`deploy_location` · `locations` · `start_date` · `end_date` ·
`duration_months` · `careers_summary` · `responsibilities` · `work_model` ·
`shift` · `duty_hours` · `sourcing_model` · `experience_min` · `qualification` ·
`skills` · `discipline` · `category` · `contract_ref` · `po_ref`

Genuinely absent: `priority`, `employment_type`, a required-by date, a
structured requestor, a request reason, a job-profile link, a project link.

## 4. Request type — a mechanism already exists (§8)

`requisitions.req_type` is backed by the **`requisition_type` lookup**, which is
customer-configurable. It currently carries two values:

```
NEW          New position (new project / expansion)
REPLACEMENT  Replacement (engineer who left)
```

§8's other suggestions (`ADDITIONAL_MANPOWER`, `TEMPORARY`, `PROJECT`,
`BACKFILL`, `CONTRACT`) are **absent**. The correct move is to extend that
existing list, not create a second `request_type` vocabulary.

## 5. Vocabularies that do NOT exist (§24, §25)

Checked: `priority`, `urgency`, `employment_type`, `contract_type`,
`work_model`, `request_reason` — **none has a lookup**. `work_model` exists as a
free-text column only.

So §24's "use an existing priority master if available" has no candidate. New
lists are needed — created through the **existing lookup engine**, so they are
customer-extendable from day one, not hard-coded.

## 6. Job Profile does not exist (§10, §11, §31)

This is worth stating plainly because three sections of the prompt assume it
does.

- There is **no** `job_profiles` table and no reusable job-definition object.
- `lib/recruit_jd.php` generates a job description **from the requisition's own
  fields** (`jd_fields($req)`) against a single workspace-level template. It is a
  renderer, not a profile master.
- The nearest reusable definitions that genuinely exist are **Designation** (the
  title, canonical vocabulary from M2) and **Position** (the establishment seat,
  carrying department, grade and reporting line).

**M4 does not invent a Job Profile master.** Creating one is a substantial new
object with its own CRUD, vocabulary and versioning, and §4 forbids new tables
"without a demonstrated architectural need". What M4 does is preserve the §11
*distinction*: the reusable side is Designation + Position, and the **Job
Description** — the text specific to this request — is carried on the request
itself. Whether a true Job Profile master is wanted is recorded as a decision,
not assumed.

## 7. Requestor is a name string (§6)

`requisitions.created_by` is `VARCHAR(150)` holding a display name. So is
`candidates.created_by`, and so is `created_by` on roughly forty other tables —
this is an application-wide convention, not a recruitment quirk.

§6 is explicit that a canonical identity relationship must be used where one is
available. M4 therefore adds a **structured requestor** on the new object and
keeps the legacy name column untouched everywhere else. No existing table is
rewritten, and no historical name is converted into an invented identity (§39).

## 8. Nothing prevents sourcing before approval (§18)

- `requisitions.status` defaults to `'OPEN'`, labelled *"Open (approved,
  sourcing)"*. There is **no `DRAFT`**.
- `approved_by`, `approval_ref` and `approval_date` exist but are **free text the
  author fills in themselves**. Nothing validates or enforces them.
- The code's own comments say *"management approval for a position — mandatory
  before hiring"*, so the intent was always that a requisition represents an
  approved need. The enforcement was never built.

## 9. What exists for the rest

| M4 asks for | Already exists | Reused how |
|---|---|---|
| Department (canonical) | M2/M3 vocabulary + `department_id` | directly |
| Designation | canonical vocabulary | directly |
| Position | `positions` + five-case manpower check | directly |
| Quantity + fulfilment | M3 `quantity`, `reqfulfil.php` | directly |
| Branch / location | `offices`, `office_id`, scope gates | directly |
| Project / engagement | `client_id`, `project_site`, `cx_engagements` | connected, not duplicated |
| Custom fields | `custom_save()` / `custom_values_map()` on entity `requisition` | extended to the new entity |
| Approval engine | `recruit_approval_requests` + rules/levels/steps | structural hook only (§17) |
| Snapshot | per-object columns; no generic versioning engine | per-request capture |
| Scope | `req_scope_gate()`, `cand_scope_gate()` | same pattern for the new object |
| Entitlement | `hiring` → **hr** module map | same |

## 10. What M4 will therefore build

One new object, because one is genuinely missing:

```
hiring_requests        the request layer — who asked, for what, why,
                       how many, by when, and whether it is executable yet
requisitions.hiring_request_id   additive link, execution back to request
```

Plus: extend the existing `requisition_type` list; add `priority` and
`employment_type` lists through the existing lookup engine; a scope gate for the
new object following the established pattern.

**Direct requisition creation is preserved** (§19). Existing requisitions,
candidates, numbering and history are untouched (§22, §39).

## 11. Open decision, recorded not assumed

**Is a reusable Job Profile master wanted?** It does not exist today. M4 keeps
the Job Profile / Job Description distinction using Designation + Position as
the reusable side, which is honest about what the application actually has. A
true Job Profile master — a named, versioned bundle of responsibilities, skills,
qualifications and experience, reusable across requests — would be its own
milestone. Recommended, not built here.
