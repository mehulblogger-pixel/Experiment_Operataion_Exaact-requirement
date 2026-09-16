# Phase 3 · M3 CORRECTION #3 — AUDIT

Conducted **before** any schema or code change, as the brief required.

---

## The finding that changed the scope

Before deciding where to put a canonical raiser identity, I asked which entities
actually have approval chains. **`appr_start()` is called from exactly two places
in the application:**

| Caller | Entity |
|---|---|
| `lib/recruit_offer.php:254` (`offer_submit()`) | `OFFER` |
| `lib/hiringreq.php:549` (`hreq_submit()`) | `HIRING_REQUEST` |

**Nothing in this application ever starts a `SALARY` or `REQUISITION` chain.** They
are declared in `APPR_ENTITIES` and can be targeted by a rule, but no code path
creates one. So the *live* loss D1 reported was **offers only** — and a chain for
the other two can exist solely because something called `appr_start()` directly,
which means the authenticated user was present at that moment.

That single fact decided the design.

## 1 · Who creates each record, and what identity is available

| Entity | Created by | Identity at creation | Existing stable id? |
|---|---|---|---|
| Hiring Request | `hreq_save()` | authenticated user | **yes** — `hiring_requests.requested_by_id` (M4) |
| Offer | `offer_create()` | authenticated user | **no** — `created_by` is a name |
| Salary structure | `sal_save()` | authenticated user | **no** — `created_by` is a name |
| Requisition | five different modules | varies | **no** — `created_by` is a name; `recruiter_id` / `manager_id` exist but are the *responsible* people, not the raiser |

## 2 · Existing creator / owner fields

`job_offers.created_by`, `salary_structures.created_by`, `requisitions.created_by`
— all display names. `job_offers.approved_by` and `issued_by` are names too, and
neither is the raiser in any case. **No existing canonical raiser identity exists
for the three entities.** No existing identity table can be reused, and none was
created: `users.id` is the identity, as everywhere else.

## 3 · Can the approval request retain a canonical source-user reference?

**Yes, and it is the best answer.** The chain is created by `appr_start()`, which
always runs inside an authenticated request. One nullable column there answers the
question for **every** entity — including the two whose own tables carry nothing
and for which nothing starts a chain — instead of four columns across four tables
in four modules.

## 4 · Historical rows

All existing rows have no identity. They are NULL and **fail closed**. That is
stated, not discovered later.

## 5 · Requisitions — why no column was added

`INSERT INTO requisitions` appears in **five** places:
`lib/projcosting.php`, `lib/ops.php`, `lib/hiringreq.php` and two seed files.
Three of those are modules this correction is explicitly told not to modify.

A `created_by_id` populated on one path and null on four would be a **half-truth**
— an identity that is sometimes right and sometimes absent, which is precisely the
condition that invites the guessing this milestone exists to stop. The chain's
`requester_id` covers requisitions completely, because a requisition chain can only
exist if `appr_start()` created it.

**Decision: no requisition column. Documented rather than half-done.**

---

## What was implemented

| Change | Kind |
|---|---|
| `recruit_approval_requests.requester_id INT NULL` | additive, idempotent, nullable |
| `job_offers.created_by_id INT NULL` | additive, idempotent, nullable |
| `salary_structures.created_by_id INT NULL` | additive, idempotent, nullable |

Captured at creation from the authenticated session; never derived from a name.
No destructive change, no data deletion, no rename, tenant-safe (one database per
tenant), and each `ensure_column()` is a no-op on second run.

### Resolution order — strictest first

```
1. the BUSINESS OBJECT's own raiser id   hiring_requests.requested_by_id
                                         job_offers.created_by_id
                                         salary_structures.created_by_id
2. the CHAIN's requester_id              captured at appr_start()
3. nothing                               FAIL CLOSED, with a reason
```

A name is never consulted at any step.

### Identity is not authorization

```
source entity → canonical raiser id → tenant/entity validation
              → notification eligibility → notification
```

`appr_resolve_requester()` answers **who**. `appr_told_reason()` answers
**whether** — entitlement, the record existing, branch scope, active status. They
are separate functions returning separate answers, and the second is the same rule
`appr_may_be_told()` reads as a yes/no.

### Entity semantics untouched

No branch semantics were invented for offer, salary or requisition — their scope
resolution is exactly as M2 left it. Only identity was established.

---

## D2 · Audit integrity

`appr_audit_notify()` writes a row **only** for a reason worth recording
(`APPR_NOTIFY_AUDITED`) and **only** when the entity is in `ACT_ENTITIES`, so no
dangling reference is ever created. Successes and delivery failures stay in
`email_log`, where they already were, with their error.

`OFFER` and `SALARY` are not `ACT_ENTITIES`; for them **nothing is written**
rather than something invalid, and that limitation is documented — which is what
the brief asks for in place of fabricated audit data.

## D3 · Accurate reasons

`APPR_NOTIFY_REASONS` gives each outcome its own code, and the notifier returns
it. The existing vocabulary was extended rather than a second taxonomy created:
`appr_may_be_told()` is now `appr_told_reason() === ''`.

---

## Two defects of my own, found while doing this

1. **The new `ensure_column()` calls were placed above the `job_offers` table's
   own `CREATE TABLE`.** On a fresh workspace the column would never have been
   added, and the migration's boot-safety `catch` would have hidden it. Moved to
   after both tables exist.
2. **The resolver read another module's columns without ensuring its migration**,
   so the query raised, the catch swallowed it, and a **missing record** was
   reported as a **missing identity** — the very confusion D3 is about.

Both were caught by tests failing for the right reason, and both are fixed rather
than accommodated.
