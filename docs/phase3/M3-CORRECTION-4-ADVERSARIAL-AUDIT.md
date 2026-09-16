# Phase 3 · M3 CORRECTION #4 — ADVERSARIAL AUDIT

An attack on correction #4, after it was reported complete. Findings proved with
running probes.

**Verdict: NOT ACCEPTED — for one reason, and it is the same shape as C2, one
entity-family over.**

E1 and E2 are correctly fixed, and the **business-function** dimension is clean:
the entitlement gate did **not** break the legitimate case it could most easily
have broken. Regression figures stand: SQLite 9518/0, MariaDB 9519/0, new suite
129/0, 35 mutations caught.

---

## G1 · MEDIUM — "Missing entity must deny" was implemented for the hiring request only

### What happens

`appr_visible()` asks whether the entity **type** is known, and then resolves the
**record** for `HIRING_REQUEST` alone:

```php
if ($entity === '' || !array_key_exists($entity, APPR_ENTITIES)) return false;   // known TYPE
if ($entity !== 'HIRING_REQUEST') return true;                                   // ← record never resolved
```

So a *known type* is treated as a *resolved record*. Delete the underlying row and
keep the chain, and the notification layer carries on.

```
FAIL  OFFER       · a step whose record has been deleted notifies nobody  (got 1: a4fin@t.test)
FAIL  OFFER       · and the informational level denies it                 (got "")
FAIL  SALARY      · …notifies nobody                                      (got 1)
FAIL  SALARY      · …denies it                                            (got "")
FAIL  REQUISITION · …notifies nobody                                      (got 1)
FAIL  REQUISITION · …denies it                                            (got "")
ok    HIRING_REQUEST · notifies nobody
ok    HIRING_REQUEST · denies it                          (got "ENTITY_UNRESOLVED")
```

The hiring request does exactly the right thing. The other three do not.

### Why it is this correction's

C2's rule was stated plainly: **"UNKNOWN / MISSING / INVALID ENTITY must result in
DENY."** I implemented the *unknown type* half for every entity and the *missing
record* half for one.

Worse, **my own matrix had the blind spot in exactly the dimension this milestone
keeps failing on.** Correction #4's matrix tested "unknown entity" (a bogus type
string) for all four entities — and never tested "known type, deleted record" for
the three that do not resolve one. I built a per-entity matrix specifically to
stop sibling-path omissions, and then omitted a column from it.

### Severity, honestly

**Medium, not high.** It is not an entitlement or identity bypass: the gate, the
subject check and `appr_can_act()` all still apply, so only a legitimate approver
of that chain is written to. What leaks is that **a record that no longer exists
still generates notifications**, and it fails in the wrong direction — a missing
security subject makes the answer *looser*. Reachability through the ordinary
lifecycle is low (cancellation does not delete rows), exactly as C2's original was.

### Classification
**M3 correction #4 defect.** The fix is the same one C2 used, applied to the other
three: resolve the record, and deny when it is not there.

---

## What held up under attack

### The business-function dimension — the one E1 could most easily have broken

M1 decided deliberately that `/my-approvals` asks the **licence**, not
`mod.hiring.view`, *"so a configured approver who legitimately holds no
recruitment module — a Finance approver on an offer chain — is not locked out."*
An entitlement gate is precisely the change that could have quietly reversed that.

```
ok  the finance approver genuinely holds NO recruitment permission
ok  M1 policy preserved — they are still eligible on an entitled workspace
ok  and are still notified — the fix asks the LICENCE, not the user permission
```

The gate asks the tenant's entitlement, not the person's permission. **The third
dimension you asked for — did fixing the negative case break a legitimate business
function — is clean.**

### And the rest

- **E1** — all four entities denied on an unlicensed workspace, on both the
  informational and actionable paths, including the delegate path. Six mutations,
  all caught, three of them opening the hole for one entity at a time.
- **E2** — null, zero, negative, missing key and non-array subjects all denied
  explicitly; no cast encodes policy; the guard pair is proved by removing both.
- **C1, D1, D2, D3, F1, F2, F3** — re-run and still caught.
- **`appr_as_user()`** — untouched and still sound.

---

## Honest note

Five corrections, and the same sentence keeps being the finding: **a rule applied
to one path and not its sibling.** This time I had already named the pattern,
built a matrix expressly to prevent it, and still left a column out of the matrix.

The lesson is narrower than "test more": **when a rule has two halves — unknown
*type* and missing *record* — the matrix needs a row for each half, not one row
for the rule.**

**Recommended status: M3 CORRECTION #4 NOT ACCEPTED**, pending G1. It is one
condition, applied in the place C2 already established.
