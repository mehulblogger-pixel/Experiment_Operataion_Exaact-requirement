# Phase 3 · M3 CORRECTION #3 — ADVERSARIAL AUDIT

An attack on correction #3, after it was reported complete. Both findings proved
with a running probe.

**Verdict: NOT ACCEPTED. D1, D2 and D3 are correctly fixed — but two things fail
OPEN in the eligibility predicate, and one of them is an entitlement bypass that
correction #3 made live.**

Regression figures stand and are unaffected: SQLite 9389/0, MariaDB 9390/0, new
suite 53/0, 28 mutations caught. Neither finding is caught by any test.

---

## E1 · HIGH — Entitlement is not applied to offer, salary or requisition notifications

### What happens

`appr_told_reason()` asks the licence question **inside** the hiring-request
branch:

```php
if ($entity !== 'HIRING_REQUEST' || !function_exists('hreq_get')) return '';   // ← everything else leaves here
if (licence_blocks('mod.hiring.view')) return 'RECIPIENT_UNLICENSED';
```

So three of the four entities return "eligible" before entitlement is ever asked.

```
FAIL  Q1 · an UNLICENSED workspace refuses an OFFER notification too
          (want RECIPIENT_UNLICENSED, got PROVIDER_FAILURE)
FAIL  Q1 · and nothing is written to the raiser            (want 0, got 1)
```

With **People & hiring switched off**, an offer decision still produces a send to
its raiser, naming the offer, recorded in `email_log`.

### Why it is this correction's

The shape is mine, from correction #1 — `appr_may_be_told()` had the same
structure. But it was **dormant**: correction #2 had stopped offer notifications
altogether, so nothing travelled that path. **Correction #3 restored the
notification and, with it, activated the bypass.** Restoring a feature without
re-asking its security questions is exactly the "test the consequence, not only
the presence" lesson from the last audit, and I did not apply it to my own fix.

It also contradicts a rule this project has enforced since Phase 1 —
*entitlement first, and no path skips it* — on the one boundary that leaves the
application.

### Classification
**M3 correction #3 defect (activated dormant).** Entitlement belongs above the
entity switch, not inside one branch of it.

---

## E2 · MEDIUM — A security subject with no id is silently *eligible*

`appr_told_reason()` delegates to `appr_as_user()`, which returns `null` when the
user has no usable id. `(string) null` is `''` — and `''` is this function's word
for **"may be told"**.

```
FAIL  Q2 · a user with NO ID is not silently eligible                  (got "")
FAIL  Q2 · …on a hiring request either                                 (got "")
```

A malformed subject therefore produces the *most permissive* answer the predicate
can give.

### The asymmetry that makes it worth reporting

Its sibling, `appr_may_be_asked()`, casts the same `null` with `(bool)` — which is
`false`, so it fails **closed**. The two readers of the same rule disagree about
what an unresolvable subject means, and they disagree **by accident of a cast**,
not by decision.

### Reachability, stated honestly

**Not reachable through any current caller.** `appr_resolve_requester()` and
`appr_step_recipients()` only ever pass rows fetched from `users`, which have ids.
This is a latent fail-open, not a live leak.

But this milestone's stated principle is *"any unresolvable security subject or
entity must fail closed"*, and C2 applied it to the **entity** while leaving the
**subject** open. That is the same recurring shape — a question asked on one path
and not its sibling — one level up from where I last found it.

### Classification
**M3 correction #3 defect (latent).** One explicit guard; no structural change.

---

## What held up under attack

- **D1** — canonical identity from the business object, then the chain, then fail
  closed; offer, salary and requisition raisers are told again; namesakes in
  another branch and without permission are told nothing; a hand-made request
  cannot post to an arbitrary id. Eleven mutations, all caught, and P01 — putting
  a name back in charge — now breaks 31 assertions.
- **D2** — no repetitive rows, no dangling references, and every row that is
  written points at a supported entity.
- **D3** — each failure reports its real reason; nothing collapses into
  `IDENTITY_UNRESOLVED`.
- **Offer creation has exactly one insert path**, so the identity it captures
  cannot be bypassed by a second door. *(Probed, not assumed.)*
- **F1/F2/F3 and C2** — re-run in full and still caught.
- **`appr_as_user()`** — untouched and still sound.

---

## Honest note

Two corrections ago the pattern was *a question asked on one path and not its
sibling*. One correction ago it was *a fix whose side effects I tested for
presence, not consequence*. This time it is **both at once**: I restored a
notification (the consequence I was told to restore) without re-asking the
security question that the dormant path had never had to answer, and I applied
fail-closed to entities while leaving the subject predicate open.

**Recommended status: M3 CORRECTION #3 NOT ACCEPTED**, pending E1 and E2. Both are
small — E1 moves one check above a branch; E2 adds one guard — and both are mine.
