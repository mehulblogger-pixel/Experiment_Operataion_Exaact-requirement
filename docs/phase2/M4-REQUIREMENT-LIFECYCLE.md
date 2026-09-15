# Phase 2 · M4 — Requirement Lifecycle

## 1. Three lifecycles, not one (§16)

They answer different questions and must not be conflated.

| Lifecycle | Question | Values |
|---|---|---|
| **Hiring request** | may recruitment begin? | `DRAFT` · `SUBMITTED` · `UNDER_REVIEW` · `APPROVED` · `REJECTED` · `CANCELLED` |
| **Requisition** | how is execution going? | `OPEN` · `PROPOSED` · `OFFERED` · `PARTIALLY_FILLED` · `HIRED` · `CLOSED` · `CANCELLED` |
| **Candidate stage** | how far has a person got? | `RECEIVED` … `INTERVIEW` … `ACCEPTED` … |

Each is a configurable lookup, not a hard-coded engine. `hiring_request_status`
was added through the existing lookup engine; the other two already existed and
were not touched.

## 2. The request lifecycle

```
        ┌──────────┐  submit   ┌───────────┐  approve  ┌──────────┐
        │  DRAFT   │──────────▶│ SUBMITTED │──────────▶│ APPROVED │
        └──────────┘           └───────────┘           └──────────┘
             │                       │ reject               │
             │ cancel                ▼                      │ raise
             ▼                 ┌──────────┐                 ▼
        ┌───────────┐          │ REJECTED │          ┌─────────────┐
        │ CANCELLED │          └──────────┘          │ REQUISITION │
        └───────────┘                                └─────────────┘
```

Where a workspace sets `approval_required = 0`, submitting goes straight to
`APPROVED`. The boundary still exists — it is simply satisfied at once.

## 3. What each transition does

| Transition | Effect |
|---|---|
| save (draft) | validates against the real masters; nothing is executable |
| **submit** | takes the **snapshot** (§12); moves to `SUBMITTED`, or `APPROVED` where approval is not required |
| approve / reject | records `decided_by`, `decided_at`, `decision_note` |
| cancel | closes the request; it can never start recruitment |
| raise requisition | only from `APPROVED`, and never beyond the approved headcount |

Every transition is logged through the existing `activity_log()`.

## 4. Where execution is prevented (§18)

One question, asked in one place:

```php
hreq_is_executable($request)   // APPROVED, or approval not required
```

`hreq_to_requisition()` asks it **before doing anything else**. Removing that
check is mutation §44-M3 and fails nine assertions.

```
DRAFT      → "This request is draft. Recruitment cannot start until it is approved."
SUBMITTED  → "This request is submitted. Recruitment cannot start until it is approved."
CANCELLED  → refused
APPROVED   → proceeds
```

## 5. After approval

| Situation | Behaviour |
|---|---|
| edit an approved request | **refused** — "needs a re-approval, which is not built yet" |
| edit a rejected or cancelled request | refused |
| raise more than approved | refused, naming how many are left |
| raise the remainder in pieces | allowed — one request, many requisitions |

## 6. Quantity through the lifecycle

```
Hiring request     quantity = 10        what was asked for
      ↓ approval may reduce it
Requisition(s)     6 + 4 = 10           what is being executed (M3)
      ↓
Fulfilments        candidates at ACCEPTED, per requisition (M3)
```

`hreq_remaining_qty()` is *approved − already being recruited*, and ignores
cancelled requisitions so giving one up releases its headcount back.

## 7. What Phase 3 will add

- routing a submitted request through the existing approval engine
  (`recruit_approval_requests` is already entity-agnostic);
- the matrix, delegation, SLA, escalation, inbox and notifications;
- the re-approval path for changing an approved request;
- recruiter assignment.

None of it requires the structure to change again — which is the point of M4.
