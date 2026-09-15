# Phase 2 · M4 — Hiring Request ⇄ Recruitment Requisition ⇄ Marketplace Requirement

> The definitions below are **locked**. `M4-TERMINOLOGY-LOCK.md` is the record.

## 1. The three objects, and why none was merged (§4)

| Object | Table | What it is | Audience |
|---|---|---|---|
| **Hiring Request** | `hiring_requests` *(new)* | the business **asking** to recruit | the requesting manager |
| **Recruitment Requisition** | `requisitions` | approved demand **being executed** | the recruiter |
| **Marketplace Requirement** | `cx_requirements` | a demand a client **posts to the Connect network** | the network |

`requisitions` and `cx_requirements` **remain separate**, are not renamed, and
have no adapter between them. A test asserts both still exist.

**No new `requirements` table was created, `cx_requirements` was not renamed,
and `requisitions` was not merged with it.** There is no fourth object: what a
business calls "the requirement" is, at each stage of its life, one of the three
rows above. Introducing another row to represent the same fact would be the
"competing engine" §4 forbids, and the audit found no business fact needing one.
Tests A7–A8 assert all of this.

## 2. Cardinality (§20)

```
1 Requestor   →  N Hiring Requests
1 Hiring Request →  0..N Requisitions      (one request, executed in pieces)
1 Requisition →  1 Hiring Request or NULL  (NULL = raised directly, §19)
1 Requisition →  N Candidates              (M3, unchanged)
1 Candidate   →  1 Requisition             (M3, unchanged)
```

Nothing forces one request to one requisition. What is refused is executing more
than was approved.

## 3. Who owns which fact (§32)

| Fact | Owner |
|---|---|
| Department definition | Department master (`lookup_values`, canonical) |
| Department wording / synonyms | `lookup_terms` (M3) |
| Designation definition | Designation master |
| Position establishment & headcount | `positions` |
| **The hiring need** | `hiring_requests` |
| **Approval decision** | `hiring_requests` (Phase 3 moves routing to the approval engine) |
| **Recruitment execution** | `requisitions` |
| Execution quantity & fulfilment | `requisitions` + candidate rows (M3) |
| Candidate | `candidates` |
| Offer | `job_offers` |
| Joining | `inspectors` via `candidates.inspector_id` |
| Permissions | RBAC |

No fact has two owners. The request owns *what was asked and approved*; the
requisition owns *what is being executed*.

## 4. What crosses from request to requisition

When a requisition is raised, these travel across:

| From the request | To the requisition |
|---|---|
| hiring department (or requesting, if none) | `department_id` **and** `department` (code) |
| designation, grade | `designation`, `grade` |
| position | `position_id` |
| office, client | `office_id`, `client_id` |
| project reference | `project_site` |
| work location | `deploy_location` |
| required-by date | `start_date` |
| job description | `responsibilities` |
| request type | `req_type` |
| decision author and date | `approved_by`, `approval_date` |
| the chosen slice of headcount | `quantity` |
| the link itself | `hiring_request_id` |

The requisition is then an ordinary requisition: M3 fulfilment, the manpower
check, the candidate pipeline and every existing report work on it unchanged.

## 5. Direct creation is preserved (§19)

`/requisition-new` still works exactly as before and produces a requisition with
`hiring_request_id = NULL`. It is not deprecated and nothing was migrated.

Two paths therefore exist, deliberately:

```
Hiring Request → approval → Requisition      the governed path
Requisition (direct)                         the existing path, unchanged
```

Both are supported and deliberate — see `M4-REQUISITION-PATHS.md` for what each
is for, why both exist, and the audit showing the direct path cannot be used to
fake provenance or escape the licence, the module or branch scope.

A workspace that wants the governed path to be the **only** one needs a policy
switch. M4 deliberately does **not** invent it. The decision — always permitted,
disabled for selected customers, or a tenant/workspace policy — is recorded as
`../adr/ADR-001-direct-requisition-path.md` and belongs to Phase 3.

## 6. Numbering (§21, §22)

| Object | Series | Example |
|---|---|---|
| Hiring request | `HRQ-YYYY-NNNNNN` *(new)* | `HRQ-2026-000001` |
| Requisition | the existing generator, untouched | `REQ/AHM/NA/2609/C001/O001` |

The request number is not derived from the requisition's, and no existing
requisition number was reset or rewritten.

## 7. Marketplace (§4, §27)

`cx_requirements` and `cx_positions` are untouched. A hiring request records a
`project_ref` and a `client_id` — it **connects** to the client and project
concepts that already exist rather than building a project entity inside
Recruitment (§27).

Whether an approved hiring request should be able to post itself to the
Marketplace is a Phase 4 question (multi-source fulfilment). M4 does not prevent
it: a request already carries everything such a posting would need.
