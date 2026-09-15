# Phase 2 · M4 — Completion Report
### Hiring Request, Requirement & Requisition Foundation

## 1. What the audit found, and what was built

The audit (`M4-HIRING-REQUEST-AUDIT.md`) found **one** thing genuinely missing:
a request layer. A requisition was created directly, already `OPEN` — a status
whose own label reads *"Open (approved, sourcing)"* — and candidates could be
attached at once. Nothing distinguished *"we would like to hire"* from *"this is
approved, start sourcing"*.

**35 of the 45 fields M4 asks about already existed** on `requisitions`, and are
reused. So M4 adds one table and one column, and nothing else.

## 2. The thirty-two questions (§49)

**1. What is a Hiring Request?**
The business request to recruit: who asked, what is needed, where, how many, by
when and why. `hiring_requests`. It is not a requisition, a position, a
candidate or an approval.

**2. What is a Requirement?**
In M4 it is **not a separate row** — it is what a hiring request becomes once
approved. The audit found no business fact needing a fourth object, and §4
forbids a competing table without a demonstrated need.

**3. What is a Requisition?**
The recruitment execution record — M3's object, unchanged.

**4. How are they related?**
`Requestor → Hiring Request → [approval] → Requisition → vacancies →
fulfilments → candidate → offer → joining`.

**5. Are `requisitions` and `cx_requirements` still separate?**
Yes. Not merged, not renamed, no adapter. A test asserts both exist.

**6. Cardinality?**
One request → **many** requisitions. One requisition → one request **or NULL**
(raised directly). One requisition → many candidates (M3).

**7. Authoritative quantity?**
Two numbers, two facts: `hiring_requests.quantity` is what was **asked for**;
`requisitions.quantity` is what is being **executed** (M3's). No third. A test
pins that `reqf_counts()` still reads the requisition's.

**8. Requestor relationship?**
`requested_by_id` → `users.id`, validated against the register. The display name
is kept alongside for history, and no existing `created_by` column was rewritten.

**9. Requesting Department?**
`requesting_department_id` — a canonical Department identity.

**10. Hiring / Vacancy Department?**
`hiring_department_id`, **separate**, never assumed identical.

**11. How does Job Profile connect?**
It does not exist. The audit established there is no Job Profile object —
`recruit_jd.php` renders a description from a requisition's own fields. M4
**did not invent one**. The reusable side is Designation (canonical) + Position.

**12. How does Job Description connect?**
`hiring_requests.job_description` — specific to this request, carried into the
requisition's `responsibilities` on conversion.

**13. How does Position connect?**
`position_id` → `positions`, validated for existence, active state and branch
scope. `new_position_requested` marks a request with no establishment behind it.

**14. Location?**
`office_id` (branch, the existing architecture), `work_location`,
`project_ref` and `client_id`. No project entity was built inside Recruitment.

**15. Target dates?**
`required_by` on the request, becoming the requisition's `start_date`. Audited
first — no duplicate date concept was created.

**16. Status model?**
Three separate lifecycles: request, requisition, candidate stage. Each a
configurable lookup. `hiring_request_status` was added through the existing
engine.

**17. Approval foundation created?**
`approval_required`, `approval_ref`, `decided_by`, `decided_at`,
`decision_note`, and one state transition (`hreq_decide()`). The existing
`recruit_approval_requests` table is entity-agnostic, so Phase 3 plugs in
without a new engine.

**18. Approval functionality deferred?**
Routing, matrix, delegation, SLA, escalation, inbox, notifications, and the
re-approval path for editing an approved request.

**19. How is execution prevented before the right state?**
`hreq_is_executable()`, asked by `hreq_to_requisition()` **before anything
else**. Removing it is mutation M3 and fails nine assertions.

**20. How are historical values preserved?**
`snapshot_json`, taken at submission. Renaming a master afterwards does not
change what was approved — mutation M8.

**21. What existing architecture was reused?**
Department and Designation vocabularies (M2/M3), Position and its manpower
check, M3's quantity and fulfilment, offices and the scope architecture, the
custom-field engine, `activity_log()`, the `requisition_type` lookup, the
lookup engine itself, and the `hiring` → **hr** entitlement map.

**22. New tables / columns?**
One table, `hiring_requests`. One column, `requisitions.hiring_request_id`.
Three new lookup lists created through the existing engine (`hr_priority`,
`employment_type`, `hiring_request_status`) — the audit confirmed none existed.

**23. What was migrated?**
**Nothing.** No existing row was changed.

**24. What was not migrated?**
Historical requisitions keep `hiring_request_id = NULL`. No requestor, approval
date or department was invented for them (§39).

**25. Tenant isolation?**
One database per customer. M4 added no cross-tenant surface and no cache.

**26. Branch scope?**
`hreq_scope_gate()` on the module door, checking the request **and** the branch
named on the way in. Position and branch are re-validated on save.

**27. HR entitlement?**
`hiring-request(s)` → `hiring` → **hr**, in both module maps, verified with the
module on and off. M4 contains **no `is_master()` call**, so a master cannot
bypass it — proven by mutation M5.

**28. Mutation tests?**
All eight from §44, all caught. Detail in `M4-TEST-RESULTS.md`.

**29. SQLite?**
Whole suite — **8709 passed, 0 failed**, including all 77 M4 assertions.

**30. MariaDB/MySQL?**
Whole suite on MariaDB 10.11.14 over TCP — **8710 passed, 0 failed**, including
all 77 M4 assertions. Observed, not inferred from SQLite.

**31. Modules regression-tested?**
All of them: Operations, Quality, Reporting, Money, Sales, Recruitment,
Workforce, Marketplace, Connect, Identity, Organisation, Dashboard, Permissions,
Entitlement.

**32. What remains for Phase 3?**
Approval routing and the matrix; SLA and escalation; the approval inbox and
notifications; recruiter assignment; the re-approval path; and the policy
decision on whether direct requisition creation should remain available.

## 3. Two things worth your attention

**A decision I did not make for you.** The prompt assumes a Job Profile object
exists (§10, §11, §31). It does not, and M4 did not invent one — that would be a
substantial master with its own CRUD, vocabulary and versioning. The Job Profile
/ Job Description distinction is preserved using Designation + Position as the
reusable side. Whether you want a true Job Profile master is recorded as a
recommendation in the audit.

**Two paths now exist, deliberately.** The governed path
(request → approval → requisition) and the existing direct path
(`/requisition-new`), which §19 required be preserved. A workspace wanting the
governed path to be the *only* one needs a policy switch — a Phase 3 decision,
not something M4 imposes.

## 4. Phase 1 status — unchanged

Still not formally closed: MilesWeb production UAT, which build is live there,
the config/database incident on that host, and e-mail — never tested anywhere.

## 5. HARD STOP (§50)

M4 stops here. No approval routing, matrix, SLA, recruiter assignment, KPI,
multi-source fulfilment, supplier allocation, Marketplace sourcing, candidate
matching redesign, identity convergence or recruitment UX work has been started.
