# MGH Hire — object lifecycles

The code must never introduce a status or transition not listed here without this
file being updated in the same change.

## Requisition (SRF)

```
draft ─▶ pending_approval ─▶ approved ─▶ (candidates flow) ─▶ filled
                    │             │
                    └─▶ on_hold ◀─┘
approved / pending_approval ─▶ closed / cancelled
```

- A requisition is created as `pending_approval` (raised by a Recruiter/Admin).
- Only an **approved** requisition may take candidates.
- A Hiring Manager/Admin may `hold`; a Recruiter/Admin may `close`.

## Candidate

Status is separate from pipeline **stage**. Stage = where in the flow; status =
overall state.

```
active ─▶ (moves through stages) ─▶ offer ─▶ hired
  │
  ├─▶ on_hold ─▶ active
  └─▶ rejected / withdrawn   (terminal)
```

- A candidate starts `active` at the first active stage.
- Entering an **offer**-type stage sets status `offer`.
- Reaching a **terminal**-type stage sets status `hired` (ready for onboarding).
- `reject` sets status `rejected` (terminal); `hold`/`resume` toggles `on_hold`.

## Pipeline stages

Stages are **configurable data**, not code (Pipeline screen). Each stage has a
`kind` that changes its behaviour:

| kind | Meaning |
|---|---|
| `step` | An ordinary step. |
| `gate` | A decision point (pass to advance, or reject). |
| `interview` | An interview round (schedule + record outcome). |
| `offer` | Entering it flips the candidate to `offer`. |
| `terminal` | The final stage — reaching it marks the candidate `hired`. |

**Every** stage move, decision, interview, document and offer action writes a row
to `candidate_events` — the immutable audit timeline shown on the candidate page.

## Default pipeline (shipped, editable)

Requisition Approved · Organogram Verified · Sourcing · CV Screening ·
HOD Shortlisting · L1 Interview · L2 Interview · Document Collection ·
Salary Structure · HR Discussion · Candidate Approval · Medical Examination ·
Reference Verification · Medical Fitness Clearance · One-Pager Approval ·
Offer Released · Offer Accepted · Onboarding
