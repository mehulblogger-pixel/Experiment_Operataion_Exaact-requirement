# Phase 6 — External Account Representation Addendum

*The R26 audit: what `client_users` and `vendor_users` actually are, and whether
Phase 6 must treat them as Person representations.*

**Trigger:** `P6-ACTION-PATH-MATRIX.md` (§26) recorded four portals dispatching in
front of `require_login()`, two of which carry their own person-ish tables, and
recorded the question **NOT ESTABLISHED** rather than assuming either way (Q14,
R26).

**Scope:** documentation / audit only. No PHP, JavaScript, HTML, CSS, database,
schema, migration, route, API, permission, workflow, identity function or taxonomy
function is changed. **R22–R28 are not implemented. Q7–Q16 are not answered. No
Person-hub decision is made.**

---

## The verdict, up front

| Representation | Is it a Phase 6 **Person** representation? |
|---|---|
| **`client_users`** | **NO** — it is an external **ACCOUNT** |
| **`vendor_users`** | **NO** — it is an external **ACCOUNT** |
| **`partner_contacts`** | **NOT ESTABLISHED** — and this is the finding this audit produced |

The two tables R26 named turn out **not** to be the person records. They point at
one — **`partner_contacts`** — which no Phase 6 document has recorded, and which
holds the identity fields a real external human has.

---

## 1 · The evidence

### `client_users` — `lib/portal.php`

```sql
CREATE TABLE IF NOT EXISTS client_users (
    id, partner_id INT, contact_id INT NULL,
    email VARCHAR(200), name VARCHAR(150), password_hash VARCHAR(255),
    is_active INT DEFAULT 1, must_change INT DEFAULT 1,
    invite_token VARCHAR(64), invite_expires VARCHAR(30),
    last_login_at VARCHAR(30), last_login_ip VARCHAR(60),
    created_by VARCHAR(150), created_at VARCHAR(30))
-- added later:
    perms VARCHAR(400), site_ids VARCHAR(400), role_preset VARCHAR(30)
```

### `vendor_users` — `lib/cvp.php`

```sql
CREATE TABLE IF NOT EXISTS vendor_users (
    id, vendor_id INT, contact_id INT NULL,
    email VARCHAR(200), name VARCHAR(150), password_hash VARCHAR(255),
    is_active INT DEFAULT 1, must_change INT DEFAULT 1, perms VARCHAR(400),
    invite_token VARCHAR(64), invite_expires VARCHAR(30),
    last_login_at VARCHAR(30), last_login_ip VARCHAR(60),
    created_by VARCHAR(150), created_at VARCHAR(30))
```

**They are structurally the same table twice**, for two audiences.

### `partner_contacts` — `lib/db.php`

```sql
CREATE TABLE IF NOT EXISTS partner_contacts (
    id, partner_id INT, address_id INT NULL, name VARCHAR(150),
    designation VARCHAR(120), department VARCHAR(120),
    email VARCHAR(200), mobile VARCHAR(40), phone VARCHAR(40),
    is_primary INT DEFAULT 0)
```

**This is a person.** Name, job title, department, e-mail, mobile, phone — the
same shape as every internal person representation, for a human at a client or
vendor organisation.

### The chain

```
business_partners          ← the ORGANISATION (the spine)
      └── partner_contacts ← the external PERSON
              └── client_users / vendor_users   ← that person's LOGIN
```

`client_users.contact_id` → `partner_contacts.id`, confirmed by the join at
`portal.php:608` (`LEFT JOIN partner_contacts pc ON pc.id = cu.contact_id`).

**This mirrors the internal split** the addendum already recorded: `users` is the
account, `inspectors` is the operational resource. Here the account is
`client_users` / `vendor_users` and the person is `partner_contacts`.

---

## 2 · Answers to each R26 question

| Question | `client_users` | `vendor_users` |
|---|---|---|
| **What it represents** | A named person's **login** to the client portal | A named person's **login** to the vendor portal |
| **External account only?** | **Yes** — password, must-change flag, invite token and expiry, permissions, last-login IP | **Yes** — identical set |
| **Does it represent a real person?** | It **names** one, but the person data lives on `partner_contacts`, to which it links | Same |
| **Identity fields** | `email`, `name` only | `email`, `name` only |
| **Tenant scope** | **Structural** — one database per tenant; no tenant column | Structural |
| **Organisation scope** | **`partner_id`** → `business_partners` | **`vendor_id`** → `business_partners` |
| **Link to Candidate** | **NONE** | **NONE** |
| **Link to Inspector** | **NONE** | **NONE** |
| **Link to Professional** | **NONE** | **NONE** |
| **Link to `users`** | **NONE** | **NONE** |
| **Link to any other person representation** | **`contact_id` → `partner_contacts`** — the only one | Same |
| **Creation paths** | `portal_invite($partnerId, $email, $name, $contactId)` — staff-initiated invitation | `cvp_vendor_invite($vendorId, $email, $name, $contactId)` — staff-initiated |
| **Update paths** | Portal account admin; the client's own admin at `portal.php:887` | Vendor account admin, `cvp.php:993` |
| **Deletion / deactivation** | **Deactivation, not deletion** — `UPDATE client_users SET is_active=?` | Same |
| **Duplicate control** | **`SELECT COUNT(*) … WHERE LOWER(email)=?` — PHP-side, GLOBAL across all partners.** No unique index | Same, across all vendors |
| **Must Phase 6 treat it as a Person representation?** | **NO** | **NO** |

### Why the verdict is NO — stated as evidence, not preference

1. **Every column is about authentication or authorisation**: password hash,
   must-change, invite token, invite expiry, permissions, site scope, role preset,
   last-login IP. The only two identity fields, `email` and `name`, exist so the
   invitation can be addressed and displayed.
2. **The record explicitly defers to a person record.** `contact_id` exists
   precisely to say *"the human this account belongs to is over there."*
3. **It holds no link to any person representation** — not candidate, inspector,
   professional, `users` or `person_ref`.
4. **It is organisation-scoped by construction.** A person is not scoped to one
   organisation; an account at that organisation is.
5. **It is deactivated, never deleted** — the behaviour of a credential, not of a
   person record.

### Two things the NO verdict does **not** mean

- It does **not** mean these people are outside Phase 6. The **person** behind the
  account is `partner_contacts`, and that is the open question below.
- It does **not** mean the accounts are irrelevant to identity. The
  **`contact_id` link is itself an identity relationship**, and it is created
  automatically — see §3.

---

## 3 · An automatic identity link, found by this audit

`portal_backfill_contact_links()` links a portal account to a contact record:

```sql
UPDATE client_users SET contact_id = (
    SELECT pc.id FROM partner_contacts pc
    WHERE pc.partner_id = client_users.partner_id
      AND LOWER(pc.email) = LOWER(client_users.email) AND pc.email <> ''
    ORDER BY pc.is_primary DESC, pc.id LIMIT 1)
WHERE (contact_id IS NULL OR contact_id = 0) AND EXISTS (…)
```

It is called from **inside `portal_migrate()`** — so it runs at migration time,
on boot or first use of the portal.

**Measured against the §20 rules — and it does better than most:**

| Rule | Verdict |
|---|---|
| Deterministic evidence | **✔** exact lower-cased e-mail — rank-3 evidence |
| Scoped | **✔** restricted to the **same `partner_id`** — the only auto-link in the system that is scope-bounded |
| Non-destructive | **✔** only fills a null; never overwrites an existing link |
| Deterministic tie-break | **✔** `ORDER BY is_primary DESC, id LIMIT 1` |
| Suggestion, not automatic resolution | **✘ VIOLATED** — it links without anybody confirming |
| Audited | **✘** no entry |
| Reversible | **✘** no unlink path |

`portal_invite()` does the same match at creation time for a typed address, with
the same scoping.

> **This is a materially better auto-link than Finding A of §26.** It is
> deterministic, organisation-scoped, non-destructive and tie-broken — it only
> fails the confirmation, audit and reversal rules. Recorded, **not fixed**, as
> **R29**.

---

## 4 · The finding this audit produced: `partner_contacts`

**`partner_contacts` is a record of a real human, and no Phase 6 document has
recorded it.**

| | |
|---|---|
| **Identity fields** | `name`, `designation`, `department`, `email`, `mobile`, `phone` — **including mobile**, which `users` does not have |
| **Organisation scope** | `partner_id` → `business_partners` |
| **Tenant scope** | Structural |
| **Unique index** | **NONE** |
| **Duplicate control** | **NONE found** on any write path |
| **Write paths** | `INSERT` from `crm.php`, `leads.php`, `ops.php`, `partnerimport.php` · `UPDATE` from `compliance.php` |
| **Link to any person representation** | **NONE** — no candidate, inspector, professional, `users` or `person_ref` reference exists in either direction |
| **Is it a Phase 6 Person representation?** | **NOT ESTABLISHED** |

### Why NOT ESTABLISHED rather than YES or NO

**The evidence that it is one:** it describes a specific human being with the same
field set as every internal person representation, and the same human can
plausibly appear elsewhere — a client's engineer applying for a job becomes a
`candidate`; a vendor's supervisor deployed to a site could become an
`inspector`.

**The evidence that it is not:** it has never been treated as one. It carries no
link to any person representation, nothing detects duplicates in it, and its
purpose in the system is to be the addressee of correspondence and the contact on
a partner record.

**Deciding either way is a business judgement, not a technical one**, and the
master prompt's rule against assuming is explicit. Recorded as **Q17**.

> **The scale of the decision, stated so it is not underestimated.** Saying YES
> would add a person representation written by five modules with no duplicate
> control at all. Saying NO leaves a real category of human — client and vendor
> staff — permanently outside the identity model, which may be exactly right if
> the business never needs to know that a client contact is also a candidate.

---

## 5 · Where this leaves the representation inventory

**Documented Person representations remain FIVE.** This audit adds none.

| | |
|---|---|
| Confirmed **ACCOUNT** representations, not Person | `client_users`, `vendor_users` |
| **NOT ESTABLISHED** — pending Q17 | `partner_contacts` |

> **No Person-hub decision is made.** Q7 (`users`), Q13 (`person_ref`) and the
> emergent-model option are all untouched, and `client_users`, `vendor_users` and
> `partner_contacts` are **not** proposed as a hub.

---

## 6 · New open questions

**Q17 — Is `partner_contacts` a Phase 6 Person representation?
BUSINESS DECISION REQUIRED.** It records a real human at a client or vendor
organisation, with more identity fields than `users` has. It has never been
treated as one, and nothing links it to any person representation. See §4 for the
evidence on both sides.

**Q18 — Should portal account e-mail uniqueness be global or per-organisation?
BUSINESS DECISION REQUIRED.** Both invite paths refuse an e-mail that exists
**anywhere** in their table, so one person cannot hold portal access at two client
companies with the same address. That may be correct, or it may block a legitimate
consultant who works for two clients. Enforced in PHP only; **no unique index
exists**, so the check is also raceable.

---

## 7 · New requirements

*Added to R1–R28. **None implemented.***

| # | Requirement | Severity |
|---|---|---|
| **R29** | `portal_backfill_contact_links()` links accounts to contacts automatically, with **no confirmation, no audit and no reversal** — better scoped than any other auto-link, but still outside the §20 rules | Material (governance) |
| **R30** | **`partner_contacts` has no duplicate control on any of its five write paths**, and no unique index. Whether it needs one depends on Q17 | Gated on Q17 |
| **R31** | **Portal account e-mail uniqueness is PHP-side only and raceable** — no unique index on `client_users.email` or `vendor_users.email` | Material (concurrency), gated on Q18 |

---

## 8 · Confirmations

- **No product code changed.** No PHP, JavaScript, HTML, CSS, database, schema,
  migration, route, API, permission, workflow, identity function, taxonomy
  function, Recruitment, Marketplace, Operations, Reporting, Money or Workforce
  change.
- **R22–R31 are not implemented.**
- **The §26 action-path findings are not weakened.** This addendum adds to them.
- **No canonical Person hub is decided**, and none of the tables examined here is
  proposed as one.
- **Q7–Q18 remain open.**
- **Nothing was assumed.** `client_users` and `vendor_users` received a verdict on
  evidence; `partner_contacts` received **NOT ESTABLISHED** because the evidence
  supports both readings and the choice is the owner's.
