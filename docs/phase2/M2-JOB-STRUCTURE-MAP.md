# Phase 2 · M2 — Job Structure Map

*How a sanctioned seat becomes a vacancy becomes a hire (§9, §10).*

## 1. The chain that exists today

```
Position                 requisitions.position_id        Requisition
(a sanctioned seat)  ────────────────────────────────>  (a request to fill seats)
                                                               │
                                                               │ candidates.requisition_id
                                                               ▼
                                                          Candidate
                                                               │ stage = 'ACCEPTED'
                                                               ▼
                                                            Hire
```

All three links already existed. M2 added none and changed none.

## 2. Position → Requisition (§9)

`requisitions.position_id` links a requisition to the seat it draws on. It is
set from the requisition screen (`ops_requisition_position()` in
`lib/position.php`) and drives the **manpower check**, which already
distinguishes five real cases:

| Case | Situation | Outcome |
|---|---|---|
| **A** | Vacancy available within sanctioned headcount | Proceed |
| **B** | Sanctioned headcount full, or fewer vacancies than requested | Escalation / approval |
| **C** | Requisition not linked to any position | New-position approval required |
| **D** | Replacement for a leaver | Headcount unchanged |
| **E** | Would exceed *budgeted* headcount | Financial approval |

This is a genuinely good piece of design and M2 left it intact.

## 3. Requisition → Candidate → Hire (§10)

- `candidates.requisition_id` ties an applicant to the vacancy.
- Seats filled are **counted**, not stored:
  `COUNT(candidates WHERE requisition_id = r.id AND stage = 'ACCEPTED')`.
- Open seats = `quantity − filled` (`lib/recruit_cc.php`). `requisitions.quantity`
  is created lazily by `req_migrate()`, not by the base `CREATE TABLE` — see
  `M2-QUANTITY-COLUMN-FINDING.md`.

The counting side already handles multiple vacancies correctly. The closure
side does not — see `M2-MULTI-VACANCY-BOUNDARY.md`.

## 4. The gap M2 identified but did not close

When a requisition is linked to a position, the requisition's **department**,
**designation** and **grade** are neither inherited from that position nor
validated against it.

Today they *cannot* be validated, because the two records draw on different
vocabularies:

```
positions.department      ← "department" master (or free text)
requisitions.department   ← "hr_department" master
```

So a requisition against the "Quality" position can legitimately carry
department `QAQC`, and nothing detects the mismatch.

**This is downstream of the open decision** in
`M2-DEPARTMENT-DESIGNATION-MAP.md` §5. Inheriting or validating these fields
before the two vocabularies are reconciled would either reject valid data or
silently rewrite it. It is deliberately left for the milestone that resolves
the vocabulary question.

## 5. What is deliberately NOT merged

| Kept separate | Why |
|---|---|
| Requisition vs `cx_requirements` | Different objects: an internal hiring request vs a marketplace requirement |
| Candidate vs Person | A candidate is an applicant; a person is a serving employee |
| Inspector vs Professional | Workforce record vs Connect-side profile |
| Position vs Job Profile | `positions` is the establishment (headcount, reporting line). It is not, and must not become, a job-description library |

M2 changed none of these boundaries.
