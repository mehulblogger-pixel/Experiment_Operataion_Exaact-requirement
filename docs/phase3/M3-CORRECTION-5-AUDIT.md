# Phase 3 · M3 CORRECTION #5 — AUDIT

Conducted **before** any code change, as §1.2 required.

---

## G1 · Root cause

```php
if ($entity === '' || !array_key_exists($entity, APPR_ENTITIES)) return false;   // known TYPE
if ($entity !== 'HIRING_REQUEST') return true;                                   // ← record never resolved
```

`appr_visible()` asked whether the entity **type** was known and then resolved the
**record** for the hiring request alone. A *supported type* was therefore treated
as a *resolved record*, and a step whose offer, salary structure or requisition
had been deleted still produced recipients and passed the informational level.

C2's rule — *"unknown, missing or invalid entity must DENY"* — had its
**unknown-type** half implemented for every entity and its **missing-record** half
for one.

## Existing resolution, per entity (§1.2)

| Entity | Source table | PK | Existing resolver | Can the record legitimately be absent? | Does a chain survive deletion? |
|---|---|---|---|---|---|
| HIRING_REQUEST | `hiring_requests` | `id` | **`hreq_get()`** | no | yes — nothing cascades |
| OFFER | `job_offers` | `id` | **`offer_get()`** | no | yes |
| SALARY | `salary_structures` | `id` | *none by id* (`sal_current()` is by candidate) | no | yes |
| REQUISITION | `requisitions` | `id` | *none named* — read inline in five modules | no | yes |

**Tenant relationship:** one database per tenant, **no `tenant_id` column**. A
record belonging to another workspace is not in this database, so an id cannot
resolve to it. Isolation is structural; the correction adds no tenant clause and
asserts the property behaviourally instead of assuming it.

**No entity legitimately supports a missing source record**, and **no historical
behaviour intentionally relied on one** — an approval chain outliving its source
is an orphan, not a supported state. Deletion does not cascade, which is why the
orphan is reachable at all.

## Entity-resolution contract

```
SUPPORTED TYPE   ≠   RESOLVED RECORD
```

`appr_entity_record($entity, $id)` is the **single resolution point for
notification**. It reuses `hreq_get()` and `offer_get()` where they exist and
reads the source table directly where they do not — rather than inventing a second
identity/entity framework or adding functions to modules this correction must not
touch. A resolution that **errors** is a resolution that **failed**: the catch
denies.

Three callers now share it:

| Caller | Previously |
|---|---|
| `appr_visible()` | resolved for the hiring request only |
| `appr_told_reason()` | resolved the hiring request in its own closure |
| `appr_requester_id()` | carried **its own inline query per entity** — a second implementation of the same rule |

**`appr_guard()` keeps its own resolution deliberately.** It governs *decisions*,
and a decision must not depend on the notification layer. Two layers, each
self-contained.

**Informational is not a bypass.** `appr_told_reason()` resolves the record too,
before anything else is considered.

## What was not changed

No schema change, no migration. No branch semantics invented for offer, salary or
requisition. Identity architecture, entitlement logic and recipient selection
untouched except where G1 required. `appr_as_user()` not redesigned.

---

## The matrix has a row for each half of the rule

The narrower lesson from the last audit: a matrix built to stop sibling omissions
was itself missing a column. So for **each** of the four entities:

| | |
|---|---|
| A | valid type + valid record → existing behaviour (and **§12** — the raiser is still notified) |
| B | valid type + **deleted record** → deny |
| C | valid type + invalid id (`0`, negative) → deny |
| D | valid type + **cross-tenant id** → deny |
| E | unknown type → deny |
| F | malformed reference (blank type, missing id) → deny |

Each denied case also asserts: no recipient · nothing sent · nothing logged as
sent · no approval-state change · and the scheduler silent. Each is run on **both**
the actionable and the informational path.
