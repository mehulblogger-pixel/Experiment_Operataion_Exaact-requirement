# Phase 3 · M3 CORRECTION #3 — COMPLETION REPORT
## Canonical Raiser Identity, Audit Integrity & Notification Preservation

## D1 — how canonical raiser identity is established for Offer / Salary / Requisition

**The audit came first and changed the scope.** `appr_start()` is called from
exactly two places in this application — `offer_submit()` and `hreq_submit()`.
**Nothing ever starts a SALARY or REQUISITION chain**, and requisitions are
inserted from five modules this correction must not touch.

| Entity | Canonical raiser identity | How |
|---|---|---|
| Hiring Request | `hiring_requests.requested_by_id` | already existed (M4) |
| **Offer** | `job_offers.created_by_id` | **new**, captured in `offer_create()` |
| **Salary structure** | `salary_structures.created_by_id` | **new**, captured in `sal_save()` |
| **Requisition** | the chain's `requester_id` | **new**, captured in `appr_start()` |
| *(any entity, fallback)* | `recruit_approval_requests.requester_id` | **new**, captured in `appr_start()` |

**No requisition column was added, deliberately.** A `created_by_id` populated on
one of five insert paths and null on the other four is a half-truth — an identity
that is sometimes right and sometimes absent, which is the exact condition that
invites the guessing this milestone exists to stop. The chain covers requisitions
completely, because a requisition chain can only exist if `appr_start()` made it.

### Resolution order — strictest first, and a name is never consulted

```
1. the business object's own raiser id
2. the chain's requester_id
3. nothing → FAIL CLOSED, with a reason
```

### Identity is not authorization

```
source entity → canonical raiser id → tenant/entity validation
              → notification eligibility → notification
```

`appr_resolve_requester()` answers **who**; `appr_told_reason()` answers
**whether** — entitlement, the record existing, branch scope, active status. Two
functions, two answers, and `appr_may_be_told()` is the second read as a yes/no:
one rule, two readers.

**Entity semantics were not touched** — no branch rules were invented for offer,
salary or requisition; only identity was established.

## D2 — how the audit noise was removed

`appr_audit_notify()` writes a row **only** for a reason in `APPR_NOTIFY_AUDITED`
and **only** when the entity is in `ACT_ENTITIES`.

- successes and delivery failures stay in `email_log`, with their error, where
  they already were
- blocked or unresolved notifications are recorded with their **real** reason
- `OFFER` and `SALARY` are not `ACT_ENTITIES`, so **nothing is written** for them
  rather than something invalid — the limitation is documented, which is what the
  brief asks for in place of fabricated audit data

Routine states — sent, could not deliver, the person has no e-mail address — are
**not events** and get no row.

## D3 — how failure reasons are now accurate

`APPR_NOTIFY_REASONS` gives each outcome a distinct code and the notifier
**returns** it: `SENT`, `ENTITY_UNRESOLVED`, `IDENTITY_UNRESOLVED`,
`TENANT_MISMATCH`, `RECIPIENT_INACTIVE`, `RECIPIENT_UNLICENSED`,
`RECIPIENT_OUT_OF_SCOPE`, `RECIPIENT_NOT_VISIBLE`, `SEGREGATION_BLOCKED`,
`NO_EMAIL`, `PROVIDER_FAILURE`. No second taxonomy: the existing eligibility rule
was extended to give its reason.

## Schema changes · migrations · historical data

| Column | Table | Type |
|---|---|---|
| `requester_id` | `recruit_approval_requests` | `INT NULL` |
| `created_by_id` | `job_offers` | `INT NULL` |
| `created_by_id` | `salary_structures` | `INT NULL` |

Additive, idempotent (`ensure_column()`), forward-only, tenant-safe, no
destructive change and no data deletion. **Every historical row is NULL and fails
closed** — never guessed. Tested on SQLite and MariaDB.

## Affected callers · notification consumers

`appr_email_requester()` — two callers, both in `appr_act()` (reject and
final-approve), both already passing `$req`; **no signature change was needed**.
`offer_create()` and `sal_save()` now record the raiser. `appr_start()` records
the chain's raiser. Nothing else consumes the requester notification.

## Security checks

Tenant isolation (structural — a foreign id has no row here, reported as
`TENANT_MISMATCH`), active-user rule, entitlement, branch scope for hiring
requests, existing approval visibility, and the deliberate decision that
segregation does **not** silence telling a raiser their own outcome.

## Test counts · mutations

**53 assertions, 0 failed** on both engines, covering D1-1…D1-15, D2-1…D2-6 and
D3-1…D3-7.

**28 mutations caught, 2 survived as a proved mutual redundancy, 5 anchors
superseded** by the rewrite with their intent re-covered by the new P-series.
P01 — putting a name back in charge — now breaks 31 assertions.

## SQLite · MariaDB · full regression

| | |
|---|---|
| **SQLite** | **9389 passed, 0 failed** |
| **MariaDB 10.11.14** (fresh `exaact_m3g`) | **9390 passed, 0 failed** |

M3 correction #3 53/0 · correction #2 50/0 · correction 68/0 · M3 original 176/0 ·
M1 114/0 · M2 111/0 · Phase-6 approvals 25/0 · offer context 2/0 · M4 hiring
request 78/0 · M4 correction 107/0 · recruitment admin 11/0. Operations,
Reporting, Quality, Money, Workforce, Marketplace and the Command Centre are
inside the whole-suite figures. Run **serially**. Nothing skipped or weakened.

## Known limitations

1. **Historical offers, salary structures, requisitions and chains carry no
   identity and notify nobody.** They fail closed by design. New records capture it.
2. **Requisitions have no `created_by_id`**, by the reasoning above. Their identity
   comes from the chain; a requisition chain created by some future code path that
   does not run as a user would fail closed.
3. **`OFFER` and `SALARY` notification outcomes are not on the activity timeline** —
   they are not `ACT_ENTITIES`, and inventing a dangling reference was refused.
   They remain visible in `email_log` whenever a send was attempted.
4. **`appr_visible($step, $req, $user)` still only half-honours its `$user`** — it
   passes it to `appr_can_act()` and evaluates the guard for the session user. Not
   a defect today; every caller asking about somebody else goes through
   `appr_may_be_asked()`, which impersonates. Recorded, not silently redesigned.
5. Escalation does not transfer authority · no mandatory-approval switch · no
   per-tenant time zone · parallel approval unsupported · four rule conditions
   unimplemented.

## Deferred F4 / F5

**F4** — the Recruitment Command Centre remains company-wide by design; no branch
filtering added. **F5** — unbounded scans deferred; measured at 4 ms for 15
candidates.

## Manual / UAT evidence

**None.** This correction changed no screen. The M3 screens still have not been
exercised by a human in a browser, and Phase 1 UAT on MilesWeb production remains
open.

## Commit · working tree

See the final message. Mutations ran against copies outside the repository, so the
working tree is clean.
