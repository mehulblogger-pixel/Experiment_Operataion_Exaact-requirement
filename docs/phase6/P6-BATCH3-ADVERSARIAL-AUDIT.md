# Phase 6 · Batch 3 — Adversarial audit

Written against my **own** work, after the tests were green. The question is not
"do the tests pass" but "what did I build that a careful attacker, a busy
colleague or a slow Tuesday afternoon would break?"

Each finding says what it is, how it was proved, and what was done about it.

---

## Finding A1 — a guard that knew only one of its two authorities
**Severity: HIGH (would have broken a live feature). Fixed.**

`portal_invite()` was given a single staff-level authority check. The function
serves **two** doors: the staff portal register, and a client's own admin
inviting a colleague. Every client admin in the product would have been told
"You cannot give portal access."

Proved by the full regression: 13 assertions in `test_cvp_governance` went red.
The batch's own tests did **not** catch it, because they only ever acted as
staff — the classic instrument that agrees with the code.

**Fixed** by asking for either authority, with the client admin bounded to their
**own** organisation *at the function*, not only at the screen. Mutation **M18**
now aims at exactly that boundary.

**Lesson recorded:** a function-level guard must be written from the list of its
*callers*, not from the one caller in front of me.

## Finding A2 — MariaDB committed my transaction out from under me
**Severity: HIGH (a false failure on the public route). Fixed at the cause.**

The registration ran `connect_cap_migrate()` inside its transaction. MariaDB
commits implicitly on any DDL, so on the **first registration in a fresh
process** the transaction ended half way through; the rows were committed; the
`commit()` that followed threw "there is no active transaction"; and the visitor
was told their registration had failed — **for an account that existed**.

SQLite hid it completely: its DDL is transactional. It surfaced only in the full
MariaDB suite, and only because the epoch guard made this the first call.

**Root cause:** a migration inside a business transaction. **Fixed** by taking
every schema step before the transaction opens — not by catching the exception,
which would have left the atomicity broken and merely quietened the symptom.

Test **C4** reproduces the live first-run by bumping the migration epoch. With
the fix reverted it fails on MariaDB, and **C4d** — *what it reported and what it
wrote agree* — is the assertion that names the real damage. C4b/C4c (one
organisation, one account) **pass** under the defect, which is precisely why a
count of rows is not enough on its own.

**Second defect in the same place:** the catch path returned the *duplicate*
message for a system failure, telling somebody whose database call had failed
that their organisation might already be a customer. Different fact, different
sentence, now.

## Finding A3 — the identifier was read and thrown away
**Severity: MEDIUM. Fixed.**

`connect_org_register()` read `gstin` / `pan` for the duplicate check and then
did not store them. The one check that cannot be argued with was therefore left
with nothing to match against: the company that had just proved who it was could
be duplicated the next day by anybody typing a slightly different name.

Found by an adversarial probe, not by a test: two simultaneous registrations
carrying one GSTIN produced **zero** rows carrying that GSTIN.

**Fixed** — an identifier supplied at registration is stored (column-guarded, so
an older install is unaffected). **M11** and **M12** prove it is kept, and that
the same company under another name is refused next time.

## Finding A4 — the public form does not ask for a tax identifier
**Severity: MEDIUM. Reported, not changed.**

`views/ops/connect_join.php` collects a name, a contact and capabilities — no
GSTIN. So on the live public route the **EXACT** branch is dormant and the
protection is effectively **name-based**. Name normalisation is good (it strips
`Pvt`, `Ltd`, `Limited`, `LLP`, `Company`, punctuation and case), so
"Northwind Energy Pvt. Ltd." and "Northwind Energy Limited" are one company to
it — but "Northwind Energies" is not.

Adding a field to a public sign-up form is a product decision with a conversion
cost, and it is not in the accepted plan. **Recommended for owner decision**, not
done unilaterally. The code is ready for it the moment the field exists.

## Finding A5 — the name check is check-then-write, and therefore not race-proof
**Severity: MEDIUM. Reported; closing it needs a decision the owner has deferred.**

Probed with real concurrent processes:

| Probe | Result |
|---|---|
| Same company, same address, three at once | **1** organisation, 1 account, 1 success — settled by the account key |
| Same GSTIN, different names, different addresses | **1** organisation — the loser saw the stored identifier |
| **Same name**, different addresses, two at once | **2 organisations. Both succeeded.** |

The third is a genuine gap: the name check is a read followed by a write with
nothing serialising the pair. Only a uniqueness rule in the **database** can
settle it — and a uniqueness rule on `business_partners` is exactly what the
owner has deferred (Q1–Q18 open), and cannot be added while historical
duplicates exist, which this batch is forbidden to clean up.

What the batch does instead: the state is **reported**
(`PARTNER_POSSIBLE_DUPLICATE_NAME`), so it is visible rather than silent. The
honest statement is therefore: **the common case is prevented; the simultaneous
two-stranger case is detected, not prevented.**

## Finding A6 — the detector reads every organisation, every time
**Severity: LOW today, MEDIUM at scale. Reported.**

`find_duplicate_partner()` loads the whole `business_partners` table and compares
in PHP. It was already like this; Batch 3 now calls it from more doors
(registration, quotation acceptance, lead conversion). At a few thousand
organisations this is unnoticeable; at fifty thousand it is a visible pause on
every accepted quotation.

Not changed: rewriting the detector's query shape is a behaviour risk for a
performance problem nobody has yet, and §28 asks for the minimum safe change.
**Recommended for a later batch:** indexed lookups on `gstin` / `pan` / `tan`
plus a stored normalised-name column — which is also what a future uniqueness
rule would need.

## Finding A7 — "detection only" is a promise that has to be tested
**Severity: LOW. Closed.**

A report that quietly repairs is worse than no report. **J3** and **J4** assert
that running the detection changes nothing at all, and mutation **M25** — which
flips a finding to *safe to repair, no human needed* — is caught. **M26**, which
calls two agency contracts for one company a duplicate, is caught by **J5**.

## Finding A8 — the agency picker offers vendor records only
**Severity: LOW. Reported.**

The cross-reference picker lists companies marked as vendors. An agency that is
on file only as a client would not appear; the established path is to tick the
vendor role on that company's own record, which the "add a company" screen
already does without creating a second record. Documented as a limitation rather
than given a second way to do the same thing.

## Finding A9 — things I deliberately did not do
* No uniqueness on `business_partners` (Q1–Q18 open).
* No contact-e-mail uniqueness (**R30**, deferred by **Q22**).
* No merging, no migration, no back-fill, no historical cleanup of any kind.
* No new engine: the duplicate detector, the audit trail and the state report are
  all the ones that already existed.
* No change to the permission matrix, and no new permission.

## What would still worry me on the morning of a release

1. **A5** — two strangers registering the same company name in the same second.
   Detected, not prevented. It needs the uniqueness decision.
2. **A4** — the public form not asking for a tax identifier keeps the strongest
   check dormant on the one route that has no human behind it.
3. The e-mail-in-use reply on `/join` still confirms that an address has an
   account (pre-existing; see the security results).

None of these is introduced by this batch. All three are written down rather
than smoothed over.
