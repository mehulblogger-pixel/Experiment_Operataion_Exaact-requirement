# Phase 3 · M1 — Hiring Request Lifecycle

No new status vocabulary was created. Every state below already existed in M4's
`HREQ_STATUS`, which is a **lookup** (`hiring_request_status`) an administrator
can label, not a hard-coded engine. `UNDER_REVIEW` **is** "approval pending" —
M1 mapped to it rather than inventing `APPROVAL_PENDING` beside it.

```
        ┌──────────┐  submit   ┌───────────┐            ┌──────────────┐
        │  DRAFT   │──────────▶│ SUBMITTED │───────────▶│ UNDER_REVIEW │
        └──────────┘           └───────────┘  a rule    └──────────────┘
             │                       │         matched         │
             │                       │ decide directly         │ the chain decides
             │                       ▼                         ▼
             │                 ┌──────────┐             ┌──────────┐
             │                 │ APPROVED │◀────────────│ APPROVED │
             │                 └──────────┘             └──────────┘
             │                       │                         │
             │                 ┌──────────┐             ┌──────────┐
             │                 │ REJECTED │◀────────────│ REJECTED │
             │                 └──────────┘             └──────────┘
             │
             │ cancel  (from DRAFT, SUBMITTED or UNDER_REVIEW)
             ▼
        ┌───────────┐
        │ CANCELLED │
        └───────────┘
```

## Transitions

| From → To | Who | What happens |
|---|---|---|
| — → `DRAFT` | `mod.hiring.edit` + scope | validated against the real masters; **not executable** |
| `DRAFT` → `SUBMITTED` | `mod.hiring.edit` + scope | **snapshot taken**; submission time recorded; requestor preserved |
| `SUBMITTED` → `UNDER_REVIEW` | the system | only where an administrator's rule matches; `approval_ref` records the chain |
| `UNDER_REVIEW` → `APPROVED`/`REJECTED` | the step's approver, past the guard | through `appr_callback()` → the one writer |
| `SUBMITTED` → `APPROVED`/`REJECTED` | the module + a management role, past segregation | directly; refused while a chain is open |
| any open state → `CANCELLED` | `mod.hiring.edit` + scope | |

## What may never happen

| Attempt | Result |
|---|---|
| `DRAFT` → `APPROVED` directly | refused — only `SUBMITTED`/`UNDER_REVIEW` can be decided |
| recruit from `DRAFT`, `SUBMITTED`, `UNDER_REVIEW`, `REJECTED` or `CANCELLED` | refused by `hreq_is_executable()` |
| approve an already-rejected or cancelled request | refused by the one writer |
| act on the same approval step twice (replay) | refused — the step is no longer `PENDING` |
| decide directly while a chain is open | refused — the chain is authoritative |
| edit an `APPROVED` / `REJECTED` / `CANCELLED` request | refused (M4, preserved) |

`hreq_is_executable()` remains the **single** authoritative question, and M1
added no second "is it approved?" test anywhere.
