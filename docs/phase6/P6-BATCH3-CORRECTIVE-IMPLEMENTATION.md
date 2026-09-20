# Phase 6 · Batch 3 — Corrective Implementation

Identity · Organisation · Public registration · Primary contact · Merge safety

**Status: implementation complete, evidence recorded, AWAITING OWNER ACCEPTANCE.**
Not accepted. Not locked. That decision is the owner's.

Baseline: `8de9607` (Batch 3) → `aef8974` (recruitment fix) → `cdc6f30` (corrective
adversarial audit) → `713a3ca` (status-semantics audit) → this work.

---

## 1 · What was wrong, in one page

Six defects, found by an adversarial pass *after* Batch 3 had been accepted and
locked. Five of the six were introduced by Batch 3 itself.

| | Defect | What it meant in practice |
|---|---|---|
| **A1** | Three concurrent writers produced **three** main contacts, 3 runs out of 3 on MariaDB. No transaction, no lock, no constraint — only a non-unique index. | "Who is the main contact at this company?" had more than one answer. |
| **A2** | A leading space made `" ann@x.com"` a different person from `"ann@x.com"`. | A second account on one address that its owner could never sign in to. |
| **A3** | The public sign-up form answered "is this company already your customer?" 200 times out of 200 — by response size (12 389 vs 3 507 bytes) and timing (21 ms vs 284 ms), without reading a word. | The sign-up page was a customer-list lookup service. |
| **A4** | An unauthenticated refusal was written to the matched customer's **activity feed**, which shows the latest 8 entries. | Ten anonymous posts wiped a paying customer's history off their own dashboard. |
| **A5** | The duplicate detector never consulted company status. | Covered by R1–R6 below. The probe that found it was itself defective — see §8. |
| **A6** | SQLite's `PRAGMA table_info` does not list generated columns, so the migration re-ran an `ALTER` that always failed, and `continue` skipped the unique index **for the life of the install**. | A safety constraint that silently never existed, and nothing anywhere said so. |

The thread running through all six: **a rule that lives in PHP is a rule that a
second writer can race, a retry can repeat and a future author can forget.**

## 2 · The decisions, and what was built for each

### Q24 — primary contact (Option A: database-level)

`partner_contacts` now carries a column the **database** computes:

```sql
uq_primary  GENERATED ALWAYS AS
  (CASE WHEN COALESCE(is_primary,0)<>0 THEN partner_id ELSE NULL END) VIRTUAL
CREATE UNIQUE INDEX uq_pcont_primary ON partner_contacts (uq_primary)
```

NULL for every ordinary contact, so ordinary contacts stay unlimited. The
organisation's own id for a primary one, so a second primary **collides with the
first and is refused** — by the database, to every writer, including raw SQL.

`<>0` rather than `=1` is deliberate: any truthy flag counts as primary, so an
unexpected value fails *safe* (caught) rather than *open* (ignored).

`partner_contact_add()` keeps the friendly path — stand the old primary down,
then insert — and now survives losing the race: on a refusal it stands the
winner down and tries once more, which is what the caller asked for. If it loses
again the answer is no, never a second primary.

### Q25 — e-mail normalisation

One rule, `LOWER(TRIM(...))`, in one place:

```php
function email_key($e) { return strtolower(trim((string)$e)); }
```

Applied at every in-scope door: portal login, vendor login, both invitations,
public registration, account creation, contact writes, and the duplicate-contact
finding. The database computes the same rule in its own uniqueness key, so PHP
and the constraint cannot disagree about who is who. Display keeps whatever the
person typed; identity uses the canonical form.

PHP's `trim()` also clears tabs and newlines, which SQL `TRIM()` does not. That
is harmless *because* every writer normalises first and then validates, so a
stored address carries no surrounding whitespace at all and the two rules agree
on every row the product writes.

### Q26 — the public organisation oracle (Option C: controlled claim flow)

Every outcome a stranger can steer now ends at **one answer**:

> Thanks — we have your details.

Same sentence, same fields, same page, same size — whether the organisation is
brand new, already a customer, or the address already has an account. What
differs is the **e-mail**, which goes to the address they typed and which only
its owner can read.

When the organisation is already ours, an entry lands in `cx_access_requests`:
who asked, for which organisation, what matched, and how strongly. It creates
**no account, no organisation, no permission, no ownership and no merge.** A
person works the queue at `/access-requests`; approving there records a decision
and nothing else. Access is still given by the portal invitation that already
exists, which asks for its own authority and writes its own trail.

This is the owner's explicit constraint honoured in the design: *the claim flow
must not become an automatic organisation takeover mechanism.* There is
deliberately no "approve and let them in" button.

### Q27 — unauthenticated audit writes (Option C: keep the evidence, move it)

- customer-facing **business activity** → `activities` (via `act_log`)
- security / system **evidence** → `portal_audit` (via `portal_security_event`)

`portal_audit` is the portal access trail staff already have; it is read by the
staff analytics screens (`tapi_can()`) and by **no** customer-facing screen. No
new audit engine was built. The evidence keeps what an investigator needs —
when, what, outcome, request context, and the tenant context when legitimately
known — and none of it is ever shown back to the person who typed the form.

### Q28 / R1–R6 — company status

The real vocabulary is `ACTIVE`, `INACTIVE`, `ON_HOLD`, `BLACKLISTED`,
`PROSPECT` — tenant-editable — plus `MERGED`, which only the merge writes.
`CLOSED` and `SUSPENDED` are **not** company statuses and no logic was written
for them.

| Rule | Built as |
|---|---|
| **R1** | `PARTNER_LIVE_SQL` excludes `MERGED` from the two organisation-duplicate findings, and only those. |
| **R2** | Every other status still produces findings — the exclusion names exactly one value. |
| **R3** | `find_duplicate_partner()` scans **every** record regardless of status. Narrowing it to ACTIVE was the tempting wrong fix and is covered by mutant CM13. |
| **R4** | `business_partners.merged_into_id`, written by `dd_merge`, read by `partner_survivor()`. |
| **R5** | Only the literal `MERGED` is treated as retired. Unknown, invented, blank and NULL statuses are all live. |
| **R6** | `hold_status` and vendor `approval_status` are not consulted by the detector at all. Being blocked is a commercial fact, not an identity fact. |

The principle, in one line: **status may make the system say less; it must never
make it allow more.**

### R4 — the survivor pointer, in detail

`dd_merge` already retired the losing record and wrote a sentence into its
description. A sentence cannot be followed by code, so a new registration
carrying the retired company's GSTIN was matched against the **dead** record.

`merged_into_id` follows the convention this product already uses twice
(`controlled_docs.superseded_by_id`, `decision_rules.superseded_by_id`).

- It is explicit and machine-readable. Nothing is inferred from names, tax
  identifiers, e-mail addresses, fuzzy matching or user-entered text.
- Identifiers on the retired record are **not** wiped. They are the evidence of
  why the two were ever thought to be the same company, and erasing them would
  contradict Q27.
- Chains resolve (A→B→C answers C). A corrupt cycle terminates rather than hangs.
- A record that has itself been retired cannot be chosen as the survivor of
  another merge.
- Isolation is structural: one database per tenant, so a pointer cannot name
  another tenant's record, and an id is never treated as permission.

## 3 · The migration machinery (A6 · §5 · §6 · §7)

Three helpers in `lib/db.php`, written once and used by both guards:

- `table_columns_incl_generated()` — `PRAGMA table_xinfo` on SQLite (which lists
  generated columns; `table_info` does not), `information_schema` on MariaDB.
  Deliberately **not** merged into `table_columns()`, which feeds write paths and
  must keep not seeing generated columns — you cannot INSERT into one.
- `table_index_names()` — so "the index exists" is a fact that is checked, not an
  absence of an exception.
- `ensure_unique_generated_index()` — detection and repair as separate steps.

What it guarantees:

- **Idempotent.** Once, twice, three times, after a partial failure, after a
  restart — it converges on the same schema.
- **A failed ALTER is not believed.** Two boots racing each other both ALTER;
  the loser's column is present all the same. The schema is asked, not the error.
- **Success is verified, never assumed.** `CREATE INDEX did not throw` is a
  different fact from `the index is there`, and only the second protects data.
- **Silence is impossible.** Every guard records `OK` / `DIRTY` / `FAILED` with a
  reason in a new `schema_guards` ledger. `schema_guards_not_ok()` answers "is
  the rule actually installed on this workspace?" by reading rather than faith.
- **A changed rule rebuilds the key.** When the e-mail key learned to `TRIM`,
  installs carrying the older key rebuild it automatically. A key whose rule is
  unknown is rebuilt once and then recorded.
- **DDL never runs inside a borrowed transaction.** MariaDB commits implicitly on
  any DDL, which would silently commit somebody else's half-written business
  data. Both guards refuse at their own door, and deliberately do not set their
  epoch marker when they do — so the next call outside a transaction still runs.

### Dirty data (A1 · Scenario C)

A workspace upgraded from before the guard may already hold two primaries. Three
things must not happen: the boot must not fail over data we found there, no
contact may be deleted, and no survivor may be chosen at random.

The repair is deterministic and non-destructive: **the contact that was primary
first — the lowest id — keeps the flag; the others become ordinary contacts.**
Nobody is removed, no field but the flag is touched, an administrator can set a
different primary afterwards in one click, and every demotion is written to the
organisation's trail. Only then is the constraint created.

For portal **accounts** there is deliberately no reconciler: two live accounts on
one address cannot be repaired without deactivating somebody's login, and that is
a person's decision, not a migration's. The guard records `DIRTY` and names the
address — which is the part that used to be invisible.

## 4 · Files changed

| File | Why |
|---|---|
| `lib/db.php` | the generated-key guard helpers, the `schema_guards` ledger, access-request migration wired into boot |
| `lib/ops.php` | `merged_into_id` column; the one-primary guard, reconciler and race-safe writer; `partner_survivor()`; status-aware `find_duplicate_partner()` + `dup_hit()`; the `/access-requests` route |
| `lib/helpers.php` | `email_key()` — the canonical e-mail rule |
| `lib/portal.php` | `portal_acct_migrate()` rebuilt on the shared guard (A6 + TRIM); `t_cols_of()` fixed; `portal_security_event()`; canonical e-mail at login and invitation |
| `lib/cvp.php` | canonical e-mail at the vendor login and invitation |
| `lib/connect_org.php` | the neutral public answer; access-request store, queue and screen; security evidence instead of activity pollution |
| `lib/connect_identity.php` | R1 in the two organisation-duplicate findings; canonical e-mail in the duplicate-contact finding |
| `lib/dedupe.php` | writes `merged_into_id`; refuses a retired record as a survivor |
| `lib/indexes.php` | installs both enforced guards last, when every table exists |
| `lib/areas.php` | the Access requests tile |
| `views/ops/connect_join.php` | one panel, one size, both outcomes |
| `views/ops/connect_access_requests.php` | the staff queue (new) |
| `tests/…` | see §5 |

### Database migrations

| Change | Table | Kind |
|---|---|---|
| `merged_into_id INT NULL` | `business_partners` | additive column |
| `uq_primary` generated + `uq_pcont_primary` UNIQUE | `partner_contacts` | additive key + constraint |
| `uq_active_email` rebuilt with `TRIM` | `client_users`, `vendor_users` | rebuilt generated key |
| `schema_guards` | new | guard ledger |
| `cx_access_requests` | new | access-request queue |

All additive. No column is dropped, no row is deleted, no history is rewritten.

## 5 · Limitations, stated plainly

1. **The response-shape oracle is closed; a determined attacker can still learn
   something from a *second* step.** A genuine sign-up produces a usable account
   and a matched one does not, so somebody who then attempts to sign in learns
   which happened. Closing that too would mean no account is usable until an
   e-mail is confirmed. That is a product decision about onboarding, not a defect
   fix, and it is not in this batch's scope.
2. **Timing is levelled, not equalised.** The dominant cost (password hashing) is
   now paid on every path before anything is decided, which removes the 13×
   difference the audit measured. No artificial padding was added, per §9.
3. **`BLACKLISTED` on a company record still blocks nothing.** It is a red badge;
   the enforced control is `hold_status`. Worth fixing, out of scope here, and
   recorded in the status-semantics audit.
4. **The access-request queue has no notification.** Staff see a count on the
   admin tile. Nobody is e-mailed when a request arrives.
5. **`portal_acct_migrate` reports `DIRTY` rather than repairing duplicate live
   accounts.** Deliberate — see §3.
6. **One database per tenant means §15 has no tenant predicate to remove.**
   Isolation here is structural, not a WHERE clause. The tests assert the
   property (every pointer and every request names a record in this workspace)
   rather than claiming a filter exists. Mutation cannot delete a predicate that
   does not exist, so that mutant is replaced by CM17, which redirects an access
   request to the wrong organisation.

## 6 · Remaining owner decisions

None are required to accept this batch. Two are worth a decision later:

- Should `BLACKLISTED` actually block trading, or be merged into `hold_status`?
- Should public sign-up require e-mail confirmation before an account is usable,
  closing limitation 1 completely?
