# Phase 3 · M3 CORRECTION #2 — AUDIT

The audit made **before** writing the fixes, of the two defects the correction's
adversarial audit proved.

Both are instances of two rules this milestone has now learned the hard way:

> **A name is a display attribute, never a security identity.**
> **An unresolvable subject or entity must fail closed.**

---

## C1 · The decision e-mail resolved its recipient from a display name

### Root cause

`recruit_approval_requests.requester` stores **prose** — `_appr_actor()` writes
`user_name(current_user())`, a first-name/last-name string. `appr_email_requester()`
then searched for a person matching that string:

```sql
WHERE email<>'' AND (username=? OR TRIM((first_name || ' ' || last_name))=?) LIMIT 1
```

`LIMIT 1`, no ordering, no tenant/branch/permission question. Whoever held the
lower id won.

Proved: an `INSPECTOR` at another branch **holding no recruitment permission at
all** received a hiring request's title and outcome; the person who raised it
received nothing.

### Caller audit (required by the brief)

| Caller | Passes | Available |
|---|---|---|
| `appr_act()` — reject path | `$req` (the approval-request row) | `entity`, `entity_id`, `subject`, `requester` |
| `appr_act()` — final-approve path | `$req` | same |

Only two callers, both inside `appr_act()`, and **both already carry
`entity` + `entity_id`** — which is everything needed to reach the canonical
identity. **No refactor of the signature was necessary**, so none was made.

### Canonical identity source

`hiring_requests.requested_by_id`. M4 established it as the requestor of record,
and M4's own correction made it survive an edit that omits it. It is a stable id,
it is what segregation already uses, and it is what `appr_waiting_on_others()`
already matches on.

```
approval request → entity_id → hiring_requests.requested_by_id → users.id
```

No second person/identity mechanism was introduced. `requester` keeps doing what
it is for: labelling a screen.

### Legacy and unresolvable requesters — FAIL CLOSED

`appr_requester_user()` returns **null**, and nothing is sent, when:

- the entity is **not** `HIRING_REQUEST` — an offer, a salary structure and a
  requisition record their raiser as text and nothing else, so there is nothing to
  resolve and **guessing is the defect, not the fix**;
- the hiring request row cannot be fetched;
- `requested_by_id` is 0 or missing (a legacy row);
- the id does not exist in this database (an id from elsewhere).

Each of those is written to the **existing** activity spine —
*"Decision not notified — no canonical requester identity"* — so the silence is
visible rather than silent. **This is a deliberate behaviour change for
offer/salary/requisition**, recorded as a limitation.

### Security after identity

Identity settles **who**. The existing model settles **whether**:
`appr_may_be_told()` — entitlement, the record existing, branch scope, and active
status — at the **informational** level, because telling somebody the outcome of
their **own** request is not asking them to approve anything, so segregation must
not silence it. Knowing `requested_by_id` authorizes nothing on its own.

---

## C2 · A missing entity made the visibility test weaker

### Root cause

```php
$entity = strtoupper((string) ($req['entity'] ?? ''));
if ($entity !== 'HIRING_REQUEST') return true;     // ← a missing record lands here
```

With `$req` null — a step whose request row cannot be fetched — `$entity` came out
`''`, the test read *"not a hiring request"*, and the function returned **true**.
Entitlement, branch scope and segregation were all skipped. Thirteen recipients in
the probe.

The failure direction is what makes it a defect: **a missing security subject made
the answer looser.** It must make it stricter.

### Fix

```php
if ($entity === '' || !array_key_exists($entity, APPR_ENTITIES)) return false;
```

Three states are now distinguished, using the same `APPR_ENTITIES` map the rest of
the engine already uses:

| | |
|---|---|
| **known hiring request** | full guard — entitlement, branch scope, segregation |
| **known supported entity** (offer, salary, requisition) | unchanged — can-act alone |
| **unknown, blank or unresolvable** | **deny** |

An unknown entity is **not** a synonym for "some other supported entity".

A hiring request whose record is missing is already denied by `appr_guard()`'s
own *"that hiring request no longer exists"* branch, which this fix now allows the
code to reach. **No redundant second check was added** — this milestone has
already had two mutations survive on redundancy, and one line that is actually
reached is worth more than two that guard each other.

### Not changed

Offer, salary and requisition visibility is untouched, and **no branch requirement
was introduced for entities that cannot establish one** — the M2 boundary holds.

---

## Scope held

No database change, no migration, nothing outside `lib/recruit_approval.php` and
its tests. `appr_as_user()` was **not** redesigned — the audit proved it sound, so
it is re-tested as regression only. F4 and F5 remain deferred.
