# Phase 3 · M3 CORRECTION #5 — COMPLETION REPORT
## Unresolved Source Entity — Fail Closed Across All Approval Entities

## G1 — root cause

`appr_visible()` asked whether the entity **type** was known and then resolved the
**record** for `HIRING_REQUEST` alone:

```php
if ($entity === '' || !array_key_exists($entity, APPR_ENTITIES)) return false;   // known TYPE
if ($entity !== 'HIRING_REQUEST') return true;                                   // record never resolved
```

A *supported type* was treated as a *resolved record*, so a step whose offer,
salary structure or requisition had been deleted still produced recipients and
passed the informational level. C2's rule — *unknown, missing or invalid entity
must DENY* — had its **unknown-type** half implemented for every entity and its
**missing-record** half for one.

## Entity-resolution architecture

```
SUPPORTED TYPE   ≠   RESOLVED RECORD
```

`appr_entity_record($entity, $id)` is the **single resolution point for
notification**. It reuses the existing resolvers where they exist and reads the
source table directly where they do not — no second identity/entity framework, and
no functions added to modules this correction must not touch.

| Entity | Source | Resolver used |
|---|---|---|
| HIRING_REQUEST | `hiring_requests` | **`hreq_get()`** (existing) |
| OFFER | `job_offers` | **`offer_get()`** (existing) |
| SALARY | `salary_structures` | direct read — no by-id resolver exists |
| REQUISITION | `requisitions` | direct read — no named resolver exists |

Three callers now share it — `appr_visible()`, `appr_told_reason()` and
`appr_requester_id()`, the last of which previously carried **its own inline query
per entity**, a second implementation of the same rule.

**`appr_guard()` keeps its own resolution deliberately**: it governs *decisions*,
and a decision must not depend on the notification layer.

**A resolution that ERRORS is a resolution that FAILED** — the catch denies, and
that is now exercised by a test that renames the source table out from under it.

## All four entity behaviours

| | HIRING_REQUEST | OFFER | SALARY | REQUISITION |
|---|---|---|---|---|
| valid type + valid record | as before | as before | as before | as before |
| **valid type + deleted record** | deny | **deny** | **deny** | **deny** |
| invalid id (0, negative) | deny | deny | deny | deny |
| cross-tenant id | deny | deny | deny | deny |
| unknown type | deny | deny | deny | deny |
| malformed reference | deny | deny | deny | deny |

Each denied case: **no recipient · nothing sent · nothing logged as sent · no
approval-state change · the scheduler silent.**

## Cross-tenant behaviour

Isolation is **structural** — one database per tenant, no `tenant_id` column — so
an id cannot resolve to another workspace's record. The correction adds no tenant
clause and instead **proves** the property: for each entity the foreign id is shown
absent from that entity's own source table, and `appr_entity_record()` returns
null for it.

## Informational notification behaviour

**Informational is not a bypass.** `appr_told_reason()` resolves the source record
too, for every entity, before anything else is considered — subject, entitlement,
entity resolution, then scope. Mutation **G1-G** (informational proceeds despite an
unresolved entity) is caught with 25 failures.

## Regression

| Suite | Result | Covers |
|---|---|---|
| `p3m3c5_entity` | **179 / 0** | G1 |
| `p3m3c4_gate` | 129 / 0 | **E1, E2** |
| `p3m3c3_raiser` | 53 / 0 | **D1, D2, D3** |
| `p3m3c2_identity` | **51 / 0** | **C1, C2** |
| `p3m3c_notify` | 68 / 0 | **F1, F2, F3** |
| `p3m3_sla` · `p3m1_approval` · `p3m2_matrix` | 176 / 0 · 114 / 0 · 111 / 0 | M3 SLA · M1 · M2 + M2 correction |
| `recruit_approval` · `offer_appr_dept` | 25 / 0 · 2 / 0 | offer / salary / requisition approval |
| `m4_hiring_request` · `m4_correction` · `hiring_admin` | 78 / 0 · 107 / 0 · 11 / 0 | |

No display-name lookup returned · canonical identity intact · no repetitive audit
noise · no dangling `ACT_ENTITIES` · `ENTITY_UNRESOLVED` and `IDENTITY_UNRESOLVED`
still distinct · entitlement still asked for all four entities · invalid subjects
still fail closed · F1/F2/F3 unchanged · `appr_as_user()` re-tested, not redesigned.

## Mutation results

**G1: 10 designed, 10 CAUGHT.** Earlier batteries re-run: **30 caught**, **4
superseded anchors re-run against current code and all caught**, **5 survivors**,
each paired and proved — including **N06/N07, which correction #5 itself made
redundant** with the new record check (proved: either alone survives, both
together caught with 37 failures). Full detail in the mutation results document.

## SQLite · MariaDB · full regression

| | |
|---|---|
| **SQLite** | **9698 passed, 0 failed** |
| **MariaDB 10.11.14** (fresh `exaact_m3j`) | **9699 passed, 0 failed** |

Operations, Reporting, Quality, Money, Workforce, Marketplace and the Command
Centre are inside those figures. Run **serially**. **Nothing skipped, weakened or
re-baselined.**

## Business-function regression (§12) — explicitly protected

For **all four entities**, a valid record still notifies its canonical raiser
(`C5.1 A`). `INVALID → DENY` did **not** become `VALID → DENY`.

## Known limitations

1. **An approval chain can still outlive its source record.** Deletion does not
   cascade; this correction makes the orphan *harmless* (it notifies nobody) rather
   than impossible. Cleaning up orphan chains is a lifecycle change outside G1.
2. **`appr_guard()` resolves the record a second time** for hiring-request
   decisions. Deliberate — the decision layer must not depend on the notification
   layer — and the cost is one read.
3. Historical offers, salary structures, requisitions and chains carry no identity
   and notify nobody · requisitions have no `created_by_id` · `OFFER` and `SALARY`
   notification outcomes are not on the activity timeline · `appr_visible()` still
   only half-honours its `$user` · escalation does not transfer authority · no
   mandatory-approval switch · no per-tenant time zone · parallel approval
   unsupported · four rule conditions unimplemented.

## Deferred F4 / F5

**F4** — the Recruitment Command Centre remains company-wide by design.
**F5** — unbounded scans deferred; G1 adds one source read per candidate, measured
in single-digit milliseconds.

## Manual / UAT evidence

**None.** This correction changed no screen. The M3 screens still have not been
exercised by a human in a browser, and Phase 1 UAT on MilesWeb production remains
open.

## Commit · working tree

See the final message. Mutations ran against copies outside the repository, so the
working tree is clean.
