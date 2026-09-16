# Phase 3 · M3 CORRECTION #4 — AUDIT

## E1 · Root cause

`appr_told_reason()` asked the licence question **inside** the hiring-request
branch:

```php
if ($entity !== 'HIRING_REQUEST' || !function_exists('hreq_get')) return '';   // ← three entities leave here
if (licence_blocks('mod.hiring.view')) return 'RECIPIENT_UNLICENSED';
```

So an offer, a salary structure and a requisition reached **eligible** having
never been asked for an entitlement. Proved with a probe: with **People & hiring
switched off**, an offer decision still produced a send to its raiser, naming the
offer, recorded in `email_log`.

**The same hole existed on the actionable path.** `appr_may_be_asked()` →
`appr_visible()` → `appr_guard()`, and `appr_guard()` also returns `''` for
non-hiring entities. It was masked because `appr_tick()` carries its own licence
gate, so reminders and escalations never travelled it — but assignment
notifications from `appr_start()` and `appr_act()` do.

**Why it is this correction's.** The shape is mine from correction #1, but it was
**dormant**: correction #2 had stopped offer notifications altogether. Correction
#3 restored them and activated it. Restoring a feature without re-asking its
security questions is the "consequence, not presence" lesson applied to
everything except my own fix.

## E1 · Fix

Not four duplicated checks. One **common gate**, evaluated before any
entity-specific question can return:

```
appr_notify_gate($req, $user)
    1 · a VALID SECURITY SUBJECT
    2 · a KNOWN, SUPPORTED ENTITY
    3 · the APPLICABLE MODULE ENTITLEMENT
        ↓
    entity-specific visibility and scope — which can only NARROW
```

**Both** readers pass through it:

| Reader | Level | After the gate |
|---|---|---|
| `appr_told_reason()` | informational | entity resolution + branch scope (hiring request only) |
| `appr_may_be_asked()` | actionable | `appr_visible()` = `appr_can_act()` + `appr_guard()` |

`APPR_ENTITY_MODULE` writes down which module each approval entity belongs to —
all four are People & hiring — so the question is asked for every entity from one
place, and a future entity cannot arrive without one.

**`appr_guard()` was not touched.** It governs *decisions* and belongs to M1/M2;
the entitlement gate was added at the *notification* predicate, so decision
semantics are unchanged.

### Security order achieved

```
1 valid security subject   → 2 tenant/security context (impersonation)
→ 3 applicable entitlement → 4 entity resolution → 5 entity visibility/scope
→ 6 active user / delegation validity → 7 segregation → 8 eligibility
```

No entity-specific early return can skip 1–3.

---

## E2 · Root cause

`appr_as_user()` returns `null` for an id it cannot use. `(string) null` is `''`,
and `''` is this predicate's word for **eligible**. A type conversion was deciding
a security question.

Its sibling `appr_may_be_asked()` cast the same `null` with `(bool)` — `false`, so
it failed **closed**. The two readers of one rule disagreed **by accident of a
cast**, not by decision.

## E2 · Fix

The subject is tested **explicitly**, and no cast encodes policy:

```php
if (!is_array($user))                        return 'IDENTITY_UNRESOLVED';
if ((int) ($user['id'] ?? 0) <= 0)           return 'IDENTITY_UNRESOLVED';
if ((int) ($user['is_active'] ?? 0) !== 1)   return 'RECIPIENT_INACTIVE';
...
return is_string($r) ? $r : 'IDENTITY_UNRESOLVED';     // never (string) $r
```

and on the actionable path `=== true`, never `(bool)`.

Covered: not an array, missing `id` key, `0`, negative, and a non-user value.

---

## The predicate matrix — run against all four entities

The brief's central instruction, and the answer to the recurring defect: **passing
the matrix for the hiring request proves nothing about the other three.**

| | HIRING_REQUEST | OFFER | SALARY | REQUISITION |
|---|---|---|---|---|
| A valid + entitled + visible | eligible | eligible | eligible | eligible |
| B unlicensed | denied | **denied** | **denied** | **denied** |
| C/D null · no id · zero · negative · non-array | denied | denied | denied | denied |
| E inactive subject | denied | denied | denied | denied |
| F cross-tenant subject | denied | denied | denied | denied |
| G out of scope | denied | *n/a — carries no branch (M2)* | *n/a* | *n/a* |
| H segregation | denied on the **actionable** path | *n/a* | *n/a* | *n/a* |
| I unknown / blank entity | denied | denied | denied | denied |
| J valid delegate | eligible | eligible | — | — |
| K expired delegate | denied | denied | — | — |
| L revoked delegate | denied | denied | — | — |
| M inactive delegator | denied | denied | — | — |

**G and H are marked *n/a* rather than faked.** Offer, salary and requisition
carry no branch — M2 established that deliberately, and this correction did not
invent one. Segregation applies to being *asked to approve*, and telling a raiser
their own outcome is deliberately exempt.

## Scope held

No schema change, no migration. Nothing outside `lib/recruit_approval.php` and its
tests. `appr_as_user()` was **not** redesigned — re-tested as regression only.
F4 and F5 remain deferred.
