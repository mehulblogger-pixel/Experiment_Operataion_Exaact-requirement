# Phase 2 · M4 — Completion Report
### Hiring Request, Recruitment Requisition & Marketplace Requirement Foundation

> **This report was corrected after the first cut of M4.** Three things were made
> unambiguous — terminology, the requestor capability model, and the direct
> requisition path — and two statements in the original report were wrong. Both
> corrections are marked **CORRECTED** below. The correction changed no data, no
> schema and no lifecycle; it changed which right is asked, what the screens call
> things, and what the documents say.

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

**2. What is a "Requirement"? — CORRECTED**
It is not one object, and the word is no longer used for one. Three different
things could be called a requirement, and they are now formally defined and kept
apart (`M4-TERMINOLOGY-LOCK.md`): the **Hiring Request** (`hiring_requests`, the
ask), the **Recruitment Requisition** (`requisitions`, approved demand being
executed) and the **Marketplace Requirement** (`cx_requirements`, a demand a
client posts to the Connect network). No new `requirements` table was created,
`cx_requirements` was not renamed, the two were not merged, and no fourth object
was introduced to satisfy a word. No screen prints a bare, unexplained
"Requirement" as the name of an object.

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

**8. Requestor relationship? — CORRECTED**
`requested_by_id` → `users.id` is the **canonical** requestor relationship,
validated against the register. It is now filled from whoever is signed in when
nothing was chosen, and a partial update that does not carry the field can no
longer erase it. The display name is kept alongside for history, and no existing
`created_by` column was rewritten.

The right to raise a request is a **capability, not a role name**. The original
documentation said "coordinator to raise", and the code asked
`is_coordinator_level()`. That was wrong twice over: it named roles instead of
the right, and the band it names is **wider than the permission matrix** — which
gives Asst. Manager no hiring right at all. The gate now asks the existing
capability `can('mod.hiring.edit')`, through the existing `can()` choke point:

> **Any user who holds the hiring CREATE capability and is within the applicable
> branch scope may raise a Hiring Request.**

No new permission was created and no second permission system exists. Approval
authority is held apart from creation authority, and the requestor may not decide
their own request. Full table in `M4-HIRING-REQUEST-ARCHITECTURE.md` §11.

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

**26. Branch scope? — CORRECTED**
`hreq_scope_gate()` on the module door, checking the request **and** the branch
named on the way in. Position and branch are re-validated on save.

The correction found the route gate was the **only** behavioural check: disabling
it outright broke no test, because it was held in place by a source-level
assertion and the acts that follow it trusted the route to have asked. Branch
scope is now asked at each write as well (`hreq_in_scope()`) **and at the read**,
the decision was split out of the refusal (`hreq_scope_reason()`) so it can be
tested without a redirect, and the tests that hold it there are behavioural — a
user in another branch, holding every right there is to hold, is refused submit,
decide, cancel, convert and edit with nothing written, and is refused the record
by direct URL. Two silent-erasure bugs were fixed alongside: a partial
update that did not carry `office_id` moved the request to *no branch*, and one
that did not carry `requested_by_id` erased the canonical requestor.

**27. HR entitlement? — CORRECTED**
`hiring-request(s)` → `hiring` → **hr**, in both module maps, verified with the
module on and off. The original report said M4 contained **no `is_master()`
call**. That is no longer true, and the correction states it plainly: there is
now exactly **one**, and it is an exception to **segregation of duties** only —
a master may decide a request they raised, because a one-person workspace has
nobody else, which is the same standing exception the application already writes
into report finalisation. It is **not** an exception to the licence, the module
capability or the branch scope: a master on a workspace that has not bought the
module is still refused, and a test proves it.

The correction also closed a gate the original missed. Written as a role band
alone, `hreq_can_decide()` **opened for a master holding core administration and
nothing else** — a role band knows nothing about what the workspace has bought.
M10's permanent gate probe caught it; the gate now asks the module question
first.

**28. Mutation tests?**
All eight from §44, plus a ninth for the new segregation rule. Detail and
results in `M4-TEST-RESULTS.md`.

**29. SQLite? — UPDATED after the correction**
Whole suite — **8816 passed, 0 failed**, including the 78 M4 assertions and the
106 M4-correction assertions. (Originally 8709/0 with 77.)

**30. MariaDB/MySQL? — UPDATED after the correction**
Whole suite on MariaDB 10.11.14 over TCP, into a **freshly created database** so
every migration ran from nothing — **8817 passed, 0 failed**, including the 78 M4
assertions and the 106 M4-correction assertions, which were confirmed present in
the MariaDB output rather than assumed. Observed, not inferred from SQLite.
(Originally 8710/0 with 77.)

**31. Modules regression-tested?**
All of them: Operations, Quality, Reporting, Money, Sales, Recruitment,
Workforce, Marketplace, Connect, Identity, Organisation, Dashboard, Permissions,
Entitlement.

**32. What remains for Phase 3?**
Approval routing and the matrix; SLA and escalation; the approval inbox and
notifications; recruiter assignment; the re-approval path; and the policy
decision on whether direct requisition creation should remain available — now
written up as a formal decision record, `../adr/ADR-001-direct-requisition-path.md`.

## 3. Two things worth your attention

**A decision I did not make for you.** The prompt assumes a Job Profile object
exists (§10, §11, §31). It does not, and M4 did not invent one — that would be a
substantial master with its own CRUD, vocabulary and versioning. The Job Profile
/ Job Description distinction is preserved using Designation + Position as the
reusable side. Whether you want a true Job Profile master is recorded as a
recommendation in the audit.

**Two paths exist, deliberately — this is architecture, not an accident.**

- **Path A — GOVERNED HIRING REQUEST PATH** *(added by M4)*: Hiring Request →
  Approval → Recruitment Requisition → Candidates. For hiring that needs
  authorising before headcount or money is committed.
- **Path B — LEGACY / DIRECT REQUISITION PATH** *(pre-dates M4, unchanged)*: a
  Recruitment Requisition created straight away, `hiring_request_id = NULL`. For
  demand already authorised outside EXAACT — a client's signed order, a won
  quotation, a manpower-services business whose client order *is* the
  authorisation.

Both write to the **same** requisition table, use the **same** quantity and
fulfilment model, and feed the **same** candidate pipeline. They differ only in
how the requisition came to exist, and the requisition screen now says which.
Provenance cannot be faked: `hiring_request_id` is not a field the requisition
form accepts, and exactly one function in the application writes it.

M4 deliberately does **not** invent the customer policy switch. Whether Path B is
always permitted, disabled for selected customers, or governed by a
tenant/workspace policy is recorded as `../adr/ADR-001-direct-requisition-path.md`
for Phase 3. Full detail: `M4-REQUISITION-PATHS.md`.

## 4. Phase 1 status — unchanged

Still not formally closed: MilesWeb production UAT, which build is live there,
the config/database incident on that host, and e-mail — never tested anywhere.

## 5. HARD STOP (§50)

M4 stops here. No approval routing, matrix, SLA, recruiter assignment, KPI,
multi-source fulfilment, supplier allocation, Marketplace sourcing, candidate
matching redesign, identity convergence or recruitment UX work has been started.
