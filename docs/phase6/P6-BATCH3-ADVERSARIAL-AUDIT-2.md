# Phase 6 · Batch 3 — Adversarial audit, second pass

Run **after** all three gates closed, against the shipped tree, deliberately
looking for what 189 batch assertions, 34 mutants and 12 653 regression
assertions did **not** ask. Every claim below was produced by a probe on both
engines, not by reading the code.

**Result: one HIGH finding that breaks an owner decision on the authoritative
engine, plus three lesser ones. Nothing was changed — Batch 3 is frozen pending
acceptance, and every fix here needs an owner decision.**

---

## A1 — the one-primary rule is not race-proof, and MariaDB is the bad case
**Severity: HIGH · breaks Q22 · REPORTED, NOT FIXED**

Q22 says a business partner has **0 or 1** primary contact. It is enforced by
`partner_contact_clear_primary()` then an insert — a check-then-write pair with
**no database constraint underneath it**. Every G-series assertion is
sequential, so nothing ever asked what two people at once would do.

Three real processes, released on a shared wall-clock barrier, each adding a
primary contact to one organisation:

| Engine | Contacts created | Primaries left | Verdict |
|---|---:|---:|---|
| SQLite 3.45.1 | 3 | **1** | holds — but only because SQLite serialises writers |
| **MariaDB 10.11.14 (authoritative)** | 3 | **3** | **the rule is broken** |

```
ADV-A · three simultaneous PRIMARY contact writes → primaries now = 3
          · p2@adv.test primary=1
          · p3@adv.test primary=1
          · p1@adv.test primary=1
```

Each process demoted the others and then inserted its own; the demotions all
landed before the inserts. "The primary contact" is ambiguous again — exactly
the state Q22 exists to prevent.

**This is Batch 3's own work failing its own decision**, and it is the same
shape as finding A5 (the organisation name race) with one important difference:
there the owner had *deliberately deferred* the uniqueness rule, so
detect-not-prevent was the agreed position. Here the rule was **approved and
implemented**, and it does not hold under concurrency.

**The remedy already exists in this codebase and is proved by Batch 3 itself:**
a database-generated key that carries `partner_id` only while the row is
primary, plus a unique index — the same technique as `uq_active_email`, which
no writer can forget and no race can beat. It is a schema change and therefore
an owner decision, not something to slip in during a freeze.

## A2 — the account key can be side-stepped with whitespace
**Severity: MEDIUM as a statement of integrity, LOW operationally · REPORTED**

`uq_active_email` is `LOWER(email)` with no `TRIM`, so a leading space makes a
different key.

| Variant | SQLite | MariaDB |
|---|---|---|
| exact repeat | refused | refused |
| **trailing** space | **accepted** | refused *(MySQL ignores trailing spaces in comparison)* |
| **leading** space | **accepted** | **accepted** |
| active rows left for one address | **3** | **2** |

The saving grace, and it was tested rather than assumed: the **sign-in query
still resolves to exactly one row on both engines**, because a person types the
address without the space. So the door Q23 was built to close stays closed —
but the claim "one active account per address" is really *one per exact
lower-cased string*, and the documents should say so. A `TRIM()` in the
generated expression closes it.

## A3 — the refusal is neutral; the *outcome* is an existence oracle
**Severity: MEDIUM · inherent to the design · REPORTED**

```
ADV-C · taken name → ok=false · free name → ok=true
```

Q21's neutral wording is intact: the sentence discloses nothing. But the
behaviour differs — a free name yields an account, a taken one does not — so
anyone can test whether an organisation is already a customer by trying to
register it. The security document's claim is therefore narrower than it reads:
**the wording discloses nothing, the behaviour does.** Closing it means
accepting a registration into a pending state regardless, which is a product
decision well beyond this batch.

## A4 — a stranger can write into a real customer's audit trail
**Severity: MEDIUM · REPORTED**

```
ADV-D · five refused public registrations added 5 activity rows to that customer's record
```

Each refused public registration audits itself against the organisation it
matched — good for staff, but the route is **unauthenticated and unthrottled**,
so anybody who guesses a customer's name can fill that customer's activity feed
with noise. The audit entry is right; its being reachable by a stranger without
limit is not. Rate limiting was already listed as out of scope; this raises its
value from "hygiene" to "an actual write primitive in a stranger's hands".

## A5 — a closed organisation still blocks a new registration
**Severity: LOW · behaviour to confirm, not obviously a defect · REPORTED**

```
ADV-E · an INACTIVE organisation of the same name → registration ok=false
```

`find_duplicate_partner()` does not filter on `status`, so an organisation that
was closed years ago still refuses a same-named applicant — who is then sent
down a claim path for a relationship that no longer exists. Defensible (it is
still the same company) but undocumented, and worth an explicit decision.

---

## Instrument defects in this pass — mine, recorded

Two probes were wrong before they were right, and both would have produced a
confident false conclusion:

1. **The race worker loaded nothing.** It lived in `/tmp`, so its
   `dirname(__DIR__)` resolved to `/`, it read no `index.php`, required no
   library, and every worker died. The first run reported **"0 contacts, 0
   primaries"** — which reads like a clean result and is in fact an empty one.
   Rebuilt on the same bootstrap the Batch 3 workers use.
2. **The first sign-in probe asked the wrong question**, checking only that the
   key rejected duplicates rather than what the login query actually resolves.

Same lesson as the four instruments recorded during the batch: **a probe with no
subject, or with the wrong question, passes while proving nothing.**

## What I would put to the owner

| # | Finding | Recommendation |
|---|---|---|
| **A1** | one-primary rule broken under concurrency on MariaDB | **Fix before lock** — generated key + unique index, the technique Batch 3 already proved |
| A2 | whitespace side-steps the account key | Cheap: `TRIM()` in the generated expression; or accept and reword the claim |
| A3 | registration outcome is an existence oracle | Accept and **state it plainly** in the security document |
| A4 | unauthenticated audit writes on a named customer | Rate-limit the public route, or audit refusals to a system log rather than the customer's feed |
| A5 | a closed organisation blocks re-registration | Confirm the intent, then document it |

**None of these was introduced by the Recruitment fix, and none changes the
Batch 1 or Batch 2 position.** A1 is the only one I would hold the lock for.
