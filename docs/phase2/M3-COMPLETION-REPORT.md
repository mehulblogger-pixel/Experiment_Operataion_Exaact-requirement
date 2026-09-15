# Phase 2 · M3 — Completion Report
### Requisition, Requirement & Multi-Vacancy Structural Foundation

**Ten vacancies now behave as ten vacancies.**

## 1. The defect, and what it actually was

The hire action set `status='HIRED'` on the requisition the moment **one**
candidate was taken on, whatever the quantity. Reproduced by driving three hires
against a requisition for five before changing anything:

```
before any hire         status=OPEN     hired_inspector_id=null
after 1st person joins  status=HIRED    ← closed, with four seats still open
after 2nd person joins  status=HIRED
after 3rd person joins  status=HIRED
```

The audit then found something that changed the whole shape of the fix:
**every individual hire was already being recorded.** Each candidate row carries
`requisition_id`, `stage` and `inspector_id`, and the Recruitment Command Centre
was already computing "filled" and "open" correctly from them. Only the
requisition-level summary was wrong.

So M3 added **no candidate table and no hire table**. It derives the summary from
records that already existed, and adds only what genuinely could not be
expressed: a vacancy cancelled with nobody in it.

## 2. What was added — the whole of it

| Column on `requisitions` | Why |
|---|---|
| `cancelled_qty`, `cancel_reason` | a vacancy given up on, which no candidate row can represent |
| `closed_at`, `closed_by`, `closure_reason` | "we stopped looking" ≠ "everybody joined" |

Plus one library, `lib/reqfulfil.php`, and one new status value,
`PARTIALLY_FILLED`, **added to** the existing `REQ_STATUS` list rather than
replacing anything.

## 3. Result

```
before any hire         status=OPEN              requested=5 filled=0 REMAINING=5
after 1st person joins  status=PARTIALLY_FILLED  requested=5 filled=1 REMAINING=4
after 3rd person joins  status=PARTIALLY_FILLED  requested=5 filled=3 REMAINING=2

cancel the 2 nobody filled  →  status=HIRED  filled=3 cancelled=2 remaining=0
cancel one more             →  refused: "Only 0 vacancies are still open."
```

## 4. The thirty questions (§43)

**1. What is the canonical Requirement object?**
There are two, deliberately: `requisitions` for an internal hiring requirement,
and `cx_requirements` for a Marketplace/Connect requirement. Different objects,
different audiences, not merged.

**2. What is the canonical Requisition object?**
`requisitions` — 79 columns. The only internal hiring request.

**3. How are `requisitions` and `cx_requirements` connected?**
They are not merged and no adapter table was created. Both are requirements seen
from different sides. `M3-REQUIREMENT-REQUISITION-MAP.md` documents the
relationship and why joining them is Phase 4 work, not M3's.

**4. What is the Position?**
`positions` — the establishment: a sanctioned seat with headcount, grade,
department and reporting line. `requisitions.position_id` links to it and the
existing five-case manpower check validates against sanctioned headcount.

**5. What is the Vacancy?**
An unfilled seat within a requisition's quantity. It is **derived**, not stored:
`remaining = requested − filled − cancelled`. Only cancelled vacancies need a
stored count, because nothing else represents them.

**6. How is quantity represented?**
`requisitions.quantity`, reused. Blank or zero means one person, as it always
has. **No second quantity field was created** — a full 308-table scan confirmed
the other quantity-like columns are different questions entirely.

**7. How is one individual fulfilment represented?**
By a **candidate row** — `requisition_id` + `stage` + `inspector_id`. This
already existed. §11 forbids a second candidate or hire table, and none was
created.

**8. How is remaining quantity calculated?**
`max(0, requested − filled − cancelled)`, where `filled` is candidates at
`ACCEPTED` — the definition the Command Centre has always used. Someone still in
the pipeline never reduces it; a mutation that subtracts them fails the suite.

**9. What happens after the first hire?**
The requisition becomes `PARTIALLY_FILLED` with `remaining` reduced by one. It
does **not** close. This is the §34 acceptance test and mutation M1.

**10. What happens after partial fulfilment?**
It stays `PARTIALLY_FILLED` and keeps accepting candidates.

**11. What happens after cancellation?**
`cancelled_qty` rises, the vacancies are **not** counted as hires, and the status
is re-derived. Cancelling more than are open is refused.

**12. What happens when all vacancies are fulfilled?**
`filled + cancelled >= requested` → `HIRED`, the existing status labelled
"Hired (filled)".

**13. What happened to `hired_inspector_id`?**
**Preserved.** It has only four consumers (schema, list join, detail screen,
write), and it still names the most recent hire — which is what it has always
held. It is simply no longer the only record of who was hired.

**14. How do Candidates connect?**
`candidates.requisition_id`, unchanged. Many candidates to one requisition, each
with its own stage and workforce record — which is precisely what makes
"3 of 10 filled" computable without a new table.

**15. How do Offers connect?**
`job_offers.candidate_id` → candidate → requisition. One requisition already
reaches many offers, through its many candidates. The Offer engine is untouched.

**16. How does Joining connect?**
`candidates.inspector_id` → `inspectors`. One requisition reaches many joinings
the same way. The Onboarding engine is untouched.

**17. How are Department and Position connected?**
The requisition carries `department_id` (canonical identity) and `position_id`.
The position carries its own department. Department and Position remain separate
concepts — §3's separation is preserved.

**18. How is tenant scope enforced?**
Structurally: EXAACT gives each customer its own database. M3 introduced no
tenant mechanism and no new cache.

**19. How is branch scope enforced?**
By the existing `req_scope_gate()`, which the new cancel route runs **before**
reading any id. No second access-control mechanism was created; a mutation
removing the gate fails the suite.

**20. How is entitlement enforced?**
Unchanged. `/requisitions` and its routes stay gated to the paid **hr** module,
and M3 introduced **no `is_master()` override anywhere**, so a master user
cannot bypass entitlement.

**21. What existing code was reused?**
`requisitions.quantity`, `candidates.requisition_id` / `stage` / `inspector_id`,
`CAND_STAGES`, `REQ_STATUS`, `req_scope_gate()`, `activity_log()`, the
`recruit_pipelines` engine, the position master and its manpower check, and the
canonical Department layer.

**22. What was extended?**
`requisitions` with five additive columns; `REQ_STATUS` with one value;
`req_migrate()` and `reqf_migrate()` wired into `boot()`.

**23. What was mapped?**
Candidate stages → seat outcomes (filled / in progress / lost), and counts →
requisition status. Both stated once, in `lib/reqfulfil.php`.

**24. What database changes were made?**
Five nullable/defaulted columns on `requisitions`. Additive, forward-only,
idempotent, non-destructive, on both engines. Nothing dropped, nothing deleted,
no stored value rewritten.

**25. What migrations were executed?**
`req_migrate()` and `reqf_migrate()`, both now at boot. The test runs them twice
and asserts no column is added and no data changes.

**26. What existing data was tested?**
The full suite runs against a populated database on both engines — requisitions,
candidates, pipeline links, offers, joinings, reporting and the Operations
integrations.

**27. What remains deferred to Phase 3/4/5/6?**
Hiring Request workflow, requisition approval workflow, recruiter assignment
workflow, multi-source **allocation** to named suppliers, KPI/SLA, person
identity convergence, and a closure workflow over the `closed_*` columns.

**28. What was tested on SQLite?**
The whole suite — **8552 passed, 0 failed** — including all 68 M3 assertions.

**29. What was tested on MariaDB/MySQL?**
The whole suite on MariaDB 10.11.14 over TCP — **8553 passed, 0 failed** —
including all 68 M3 assertions. Observed, not inferred from SQLite, which
reported **8552 / 0** against the same tree.

**30. What mutation tests prove the critical rules?**
Seven, all caught: the first hire closing the requisition (M1); remaining always
zero (M2); a cancelled vacancy counted as a hire (M6); over-cancellation;
overruling a person's explicit closure; counting pipeline candidates as filled;
and removing the branch-scope gate from the new route.

## 5. A correction this milestone forced

Driving three hires showed `hired_inspector_id` holds the **most recent** hire,
not the first — the hire action overwrites it each time. Our earlier
documentation said "only ever the first". That was wrong and is corrected in
`M2-MULTI-VACANCY-BOUNDARY.md` and `M2-COMPLETION-REPORT.md`.

## 6. §44 stop conditions — all checked, none triggered

A 308-table scan found **no** competing requisition system, **no** second
requisition quantity field and **no** existing fulfilment/hire object. No
destructive migration was needed; `hired_inspector_id` was preserved without
difficulty; `requisitions` and `cx_requirements` were not merged; Person Identity,
Marketplace and Operations were not touched.

## 7. Out of scope — not implemented (§45)

No hiring-request workflow, approval workflow, recruiter KPI, SLA engine,
multi-source allocation, supplier allocation, Marketplace sourcing workflow,
identity convergence, new candidate-pipeline engine, new Offer or Appointment
engine, new reporting engine, new dashboard, or unrelated UX redesign.

## 8. Phase 1 status — unchanged

Still **not** formally closed: MilesWeb production UAT, which build is live
there, the config/database incident on that host, and e-mail — never tested
anywhere.

## 9. Stop

M3 stops here, for your audit before the next milestone.
