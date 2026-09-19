# Phase 6 · Batch 3 — Implementation plan, as built

**Scope:** organisation representation, duplicate safety and cross-reference.
**Branch:** `claude/testing-branch-setup-0gqe8n` · **Baseline commit:** `42c2c42`
**Engines:** SQLite 3.45.1 (supplementary) · MariaDB 10.11.14 (authoritative) · PHP 8.4.19

This records what was actually built, against the plan the owner accepted in
`P6-BATCH3-ORGANISATION-IMPLEMENTATION-PLAN.md` and the locked decisions
Q19–Q23. Anything the plan proposed and this did not do is listed under
**Deliberately not done**.

---

## What the batch had to fix

An organisation could come into existence through eighteen writers. Four of them
asked whether the company was already on file; the rest did not — including the
**public, unauthenticated** sign-up. Nothing recorded an organisation being
created. Nothing could report the duplicates already in a workspace. An agency
contract had no way of saying which company it was with.

---

## F1 · F2 · F3 — the public sign-up (`lib/connect_org.php`)

| | |
|---|---|
| Verdict | **CONNECT** — the detector already existed and was simply never called here |
| Owner decision | **Q21** |

`connect_org_register()` now:

1. asks `find_duplicate_partner()` before writing anything;
2. refuses on **any** match — EXACT or POSSIBLE — with **one neutral sentence**
   that names no organisation, code, identifier, contact or account, and
   invites the visitor to ask their account contact or contact us;
3. audits the refusal against the organisation that matched (staff can see it;
   the visitor cannot);
4. writes party, organisation and login inside **one transaction**, under
   Batch 2's borrowed-transaction contract (`$own`; re-throw when not own);
5. takes **every schema step before** that transaction opens (see Defect 2);
6. audits the creation **outside** the transaction, per invariant I41.

The existing-account check was narrowed to `is_active=1`, so a withdrawn account
no longer blocks a legitimate registration.

## F5 · F6 — the staff writers (`lib/ops.php`, `lib/crm.php`, `lib/leads.php`)

| | |
|---|---|
| Verdict | **EXTEND** the detector, **CONNECT** the writers |
| Owner decision | **Q20** |

`find_duplicate_partner()` gained a `confidence` key and nothing else:

* **EXACT** — GSTIN, PAN or TAN. An authoritative identifier names one legal entity.
* **POSSIBLE** — the normalised legal name. Evidence, never proof.
* **NONE** — `null`, exactly as before.

An authoritative identifier **outranks** a name: a name hit is remembered and
returned only if nothing stronger is found further down the list. The original
`['row', 'by']` shape is unchanged, so every existing caller is unaffected.

`partner_find_or_problem()` wraps it for the **staff** paths and carries the
sentence a member of staff should read — which *does* name the record, because
staff are entitled to the register. The public path never uses that wording.

Wired in:

* `crm_register_client_for_quote()` — an accepted quotation whose company carries
  an existing tax identifier **attaches to that record**; a name look-alike is
  **allowed** and the creation is audited as resembling an existing record.
  (There is nobody standing at this door to ask, and refusing would leave an
  accepted quotation with no customer at all.)
* `lead_convert()` — an authoritative identifier **stops** and names the record,
  because a person *is* standing here and the lead screen already offers the
  customer master. A name match converts and is audited.
* `lead_possible_duplicate()` — carries the confidence through to the screen.

## F4 — the agency cross-reference (`lib/ops.php`)

| | |
|---|---|
| Verdict | **MAP** — explicitly not merge, not migrate |
| Owner decision | **Q19** |

One nullable `agencies.party_id`, offered on the agency master screen as an
optional picker. Never inferred from GSTIN, PAN, name or e-mail; never
back-filled; **no uniqueness constraint** — two agency contracts may point at one
company, because an agency row is a *contract* and a partner row is a *legal
identity*. An agency with no mapping stays valid. A cross-reference that points
at a record which is not there is refused at the screen, and linking one is
audited against the company.

## Q22 — one primary contact

`partner_contact_add()` clears any existing primary before setting a new one, so
a business partner has **0 or 1** primary contact. **No global contact-e-mail
uniqueness** was imposed: the same person may legitimately be a contact at
several organisations. Contact uniqueness remains **deferred**.

## Q23 — the portal account boundary

The boundary was established **from the code**, not assumed:

* `portal_login()` and the vendor equivalent both resolve
  `WHERE LOWER(email)=? AND is_active=1` and take the first row;
* they are separate login routes over separate tables.

So the real boundary is **one active account per address, per account list** —
not per person, not global, not across tables. One human may legitimately hold
both a client and a vendor account. That is what was enforced, and nothing
wider:

* a `uq_active_email` column **generated by the database** from
  `is_active` + `email`, plus a unique index, on `client_users` and
  `vendor_users`;
* a generated column was chosen over a maintained one because **no writer can
  forget it** — the recurring defect in this codebase is a rule applied only
  where somebody remembered to apply it;
* a workspace that already holds two active accounts on one address keeps its
  data: that one index is skipped and the state is **reported**, never repaired;
* both invite writers translate the conflict into a business sentence instead of
  a crash.

## F8 — organisation creation is audited

`partner_audit_created()` — one helper, reusing `act_log()` and the already
registered `PARTNER` entity. No new audit engine. Silent on failure and always
outside a transaction: an audit that can fail a business write is worse than no
audit. Used by the public sign-up (creation and refusal), the quotation path,
the lead conversion and the agency mapping.

## F9 — the historical report

`identity_state_findings()` — Batch 2's report, **extended**, not replaced. Eight
new organisation states, all detection-only, each classified and each saying
whether a person is needed:

`PARTNER_DUPLICATE_TAXID` · `PARTNER_POSSIBLE_DUPLICATE_NAME` ·
`MARKETPLACE_MULTIPLE_FOR_PARTY` · `MARKETPLACE_UNMAPPED` ·
`MARKETPLACE_PARTY_MISSING` · `AGENCY_POSSIBLE_ORGANISATION` ·
`CONTACT_DUPLICATE` · `CONTACT_MULTIPLE_PRIMARY` · `ACCOUNT_DUPLICATE_ACTIVE` ·
`ACCOUNT_ORGANISATION_MISSING`

Nothing is merged, deleted, back-filled or rewritten.

---

## Two defects found while proving it

**1 · `portal_invite()` had two legitimate authorities, and the first guard knew
only one.** The function serves the staff portal register *and* a client's own
admin inviting a colleague. A blanket staff check would have stopped every
client admin in the product. It now asks for either authority, and the client
admin is bounded to their **own** organisation at the function, not only at the
screen. Caught by the full regression, not by the batch's own tests.

**2 · On MariaDB a migration ran inside the registration transaction.** MariaDB
commits implicitly on DDL, so the first registration in a fresh process
committed half way through, the commit then failed, and the visitor was told the
registration had failed **for an account that existed**. SQLite hid it entirely,
because its DDL is transactional. Fixed at the cause — every schema step is now
taken before the transaction opens — and the system-failure message no longer
borrows the duplicate wording. Test **C4** reproduces the live first-run by
bumping the migration epoch; it fails on MariaDB without the fix.

---

## Deliberately not done

* **No** global e-mail uniqueness, **no** person table, **no** identity hub, **no**
  organisation merge, **no** fuzzy or AI matching, **no** new engine of any kind.
* **No** historical cleanup: not one duplicate merged, contact removed, invoice,
  recruitment record or agency contract changed, or mapping back-filled.
* **No** `business_partners` uniqueness constraint (Q1–Q18 remain open).
* **No** contact-e-mail uniqueness (**R30**, deferred by Q22).
* **No** broad refactor of `ops.php`, `connect_org.php`, `crm.php`, `leads.php`
  or the portal architecture.
* The agency picker lists **vendor** records. A company not yet marked as a
  vendor is ticked as one on its own record first — the existing path — rather
  than this screen inventing a second way to do it. Noted as a limitation.
