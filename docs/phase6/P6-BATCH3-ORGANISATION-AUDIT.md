# Phase 6 · Batch 3 — Organisation representation, duplicate safety & cross-reference
## Audit

*What the shipped code actually does with the six organisation representations.
Read-only. Nothing here is fixed.*

**Baseline:** Batch 1 and Batch 2 — ACCEPTED / LOCKED. Neither is reopened.
**Method:** repository sweep plus **behavioural probes against a throwaway
database**. No product code, schema, route or permission was touched.

---

## 1. Executive summary

Three findings are **critical**, and all three live on one public route.

> **`/join` — the self-service sign-up — is unauthenticated, has no transaction,
> performs no organisation duplicate detection of any kind, and its single
> uniqueness check does not survive two people pressing the button at once.**

Proved by running it. Three simultaneous sign-ups with the **same e-mail
address**:

```
processes reporting success : 3
portal logins with that e-mail : 3
business partners created      : 3
marketplace organisations      : 3
```

And sequentially, with different e-mails:

```
/join "Zenith Energy Ltd" twice → 2 business partners · 2 marketplace organisations
an EXISTING client self-registers → a SECOND business partner
   (find_duplicate_partner() would have caught it by name — it is simply not called)
```

The organisation duplicate detector the business already owns is **good**, and
**four of the eight production writers do not call it**.

**No product code was changed. This is an audit.**

---

## 2. The six representations, and what each is actually for

| Representation | Rows describe | Authoritative for |
|---|---|---|
| **`business_partners`** | a company the business has a commercial relationship with | **The organisation spine.** Invoices, receipts, calls, jobs, quotations, contracts, leads, opportunities, portal ownership. Carries the tax identity (GSTIN, PAN, TAN, CIN) and the roles (`is_client`, `is_vendor`, `is_subcontractor`) |
| **`cx_organisations`** | a marketplace participant | **The marketplace audience.** `org_type`, `package_key`, approval state, and the capability set. Carries `party_id` → `business_partners` |
| **`agencies`** | a recruitment or manpower supplier, with a contract | **Recruitment commercials.** Placement fee, monthly rate, guarantee window, contract dates and renewal. Read by the conversion, by Money, and by the renewal reminders |
| **`partner_contacts`** | a named human at an organisation | **The contact list.** Who to call. Also the join to a portal login |
| **`client_users`** | a buyer-side portal **login** | Authentication and portal permissions for a client |
| **`vendor_users`** | a supplier-side portal **login** | Authentication and portal permissions for a vendor |

**None of these is interchangeable with another, and this audit proposes no
merge.** `client_users` and `vendor_users` are **accounts, not people**;
`partner_contacts` is **a contact record, not a Person**.

### Canonical role, stated plainly

| Question | Answer |
|---|---|
| Which is the organisation spine? | **`business_partners`** — every money, operations and portal path resolves through it |
| Is `cx_organisations` a second spine? | **No.** It is a marketplace *overlay* carrying `party_id` back to the spine. It is **not** the universal organisation master and must not become one |
| Is an agency a business partner? | **Not established, and not assumed.** §5 |
| Is a contact a person? | **No.** §7 |
| Is a portal account a person? | **No.** §8 |

---

## 3. Complete organisation writer inventory

*Every path that can create an organisation-like record. Seeds and tools are
listed because the instruction requires it, and marked.*

| # | Table | Entry point | Function | Permission | Duplicate check | Txn | Audit |
|---|---|---|---|---|---|---|---|
| 1 | `business_partners` | `/partner-new` (UI POST) | `index.php:1317` | staff, role-gated | **`find_duplicate_partner()` — GSTIN → PAN → TAN → name** ✔ | no | no |
| 2 | `business_partners` | partner import (bulk) | `partnerimport.php:320` | admin | **full detector** ✔ | no | no |
| 3 | `business_partners` | Operations partner save | `ops.php:3770` region | staff | **full detector** ✔ | no | no |
| 4 | `business_partners` | lead → client conversion | `leads.php:656` | staff | **name only** ⚠ | no | no |
| 5 | `business_partners` | CRM quote → client | `crm.php:2628` | staff | **NONE** ✘ | no | no |
| 6 | `business_partners` | **`/join` self-service** | `connect_org.php:148` | **PUBLIC, unauthenticated** | **NONE** ✘ | **no** | no |
| 7 | `business_partners` | first-run bootstrap | `db.php:376` | install | n/a | yes | n/a |
| 8 | `cx_organisations` | `/join` self-service | `connect_org.php:154` | **PUBLIC** | **NONE** ✘ | **no** | no |
| 9 | `cx_organisations` | apply-for-access form | `connect_org.php:106` | **PUBLIC** | **NONE** ✘ | no | no |
| 10 | `cx_organisations` | admin console add | `connect_org.php:230` | admin | **NONE** ✘ | no | no |
| 11 | `agencies` | **generic masters CRUD** | `ops_master_handle()` `ops.php:3947` | admin | **NONE** ✘ — a bare `INSERT INTO $table` | no | no |
| 12 | `partner_contacts` | `/partner-new` inline | `index.php:1336` | staff | **NONE** ✘ | no | no |
| 13 | `partner_contacts` | Operations contact add | `ops.php` | staff | **NONE** ✘ | no | no |
| 14 | `partner_contacts` | CRM | `crm.php:2597` | staff | **NONE** ✘ | no | no |
| 15 | `partner_contacts` | lead conversion | `leads.php:663` | staff | **NONE** ✘ | no | no |
| 16 | `partner_contacts` | partner import | `partnerimport.php:332` | admin | **NONE** ✘ | no | no |
| 17 | `client_users` | portal invite | `portal.php:641` | client admin | e-mail, PHP-side | no | no |
| 18 | `client_users` | **`/join`** | `connect_org.php:159` | **PUBLIC** | e-mail, **read-then-write** ⚠ | **no** | no |
| 19 | `vendor_users` | vendor portal invite | `cvp.php:381` | vendor admin | e-mail, PHP-side | no | no |
| — | all six | DEMO seeds S01–S06, `trace_seed`, `trace_audit`, `seed_demo`, `tools/acceptance-10co.php` | various | master / CLI | n/a | n/a | **namespaced demo data — NOT a defect** |

**No unexplained writer was found.** Background jobs, AJAX and API endpoints
write **none** of these tables — **recorded as ABSENT, confirmed, not assumed**.

---

## 4. Business partner audit

| Capability | Present? |
|---|---|
| Exact duplicate detection (GSTIN / PAN / TAN) | **Yes** — `find_duplicate_partner()` |
| Possible duplicate detection (normalised name) | **Yes**, in the same function, and it does **not** distinguish itself from an exact match |
| Organisation search | Yes |
| Multiple roles on one record | **Yes** — `is_client` / `is_vendor` / `is_subcontractor` flags. **This is the model's strength**, and `/partner-new` even tells the user to tick the extra role rather than create a duplicate |
| Tenant isolation | **Structural** — one database per tenant |
| Branch association | `home_branch_id` exists; **not enforced on creation** |
| Deletion / archive | `status` only; no deletion path found |
| Database-level uniqueness | **NONE** — no unique index on any column |
| Audit of creation | **NONE** |

### `find_duplicate_partner()` — good, and under-used

```
GSTIN → PAN → TAN → normalised name      (first match wins, returns ['row','by'])
```

**Callers: 4 of the 8 production writers.** It returns exact and possible matches
through the same door, so a caller cannot tell a tax-identifier match (decisive)
from a name match (suggestive). It is **read-then-write** with nothing behind it.

---

## 5. Marketplace organisation audit

| Question | Answer |
|---|---|
| How are records created? | `/join`, the public apply form, and the admin console |
| Does `/join` create them? | **Yes** — and a `business_partners` row, and a `client_users` login, in three unguarded statements |
| Duplicate detection? | **None on any path** |
| Tenant isolation? | Structural |
| Can an existing business partner be selected? | **No.** `connect_org_register()` always INSERTs a new party. `party_id` is set, but only to the row it just created |
| Can it represent the same real organisation as an existing partner? | **Yes, and it does** — proved in §1 |
| Is the relationship recorded? | Yes — `cx_organisations.party_id` |
| Multiple representations of one organisation? | **Yes** — two `/join` calls produce two |
| Does the marketplace depend on the representation? | Yes — `org_type`, `package_key`, capabilities and the agency bench all read it |

**It must not become the universal organisation master.** The minimum safe
mapping already exists (`party_id`); what is missing is that **nothing stops a
second one being created for a party that already has one.**

---

## 6. Agency audit

| Question | Answer |
|---|---|
| How is an agency created? | Through the **generic masters CRUD**, admin-gated. There is no dedicated agency screen |
| Duplicate detection? | **None.** `ops_master_handle()` is a bare `INSERT INTO $table` |
| Can an agency also be a business partner? | **Yes** |
| Is there any mapping between them? | **NO COLUMN EXISTS.** `agencies` has no `party_id`, no `partner_id` — proved |
| Can agency creation bypass the partner duplicate check? | **Yes** — it never reaches it. An agency was created carrying a GSTIN that already belonged to a business partner |
| Are agency name / e-mail / tax id unique? | **No**, at any level |
| May an agency legitimately hold several relationships? | **Yes** — recruitment *and* manpower supply are different `agency_type` values, and the contract fields differ per relationship |
| Historical references? | **Yes, and material.** `inspectors.agency_id`, `agency_cost`, `placement_fee`, `fee_status`, `guarantee_upto` — read by the Batch 2 conversion, by Money and by the renewal reminders |

> **`agency = business_partner` is NOT established.** An agency row carries a
> *contract* — fee, rate, guarantee window, renewal date. A business partner
> carries a *legal identity*. One real company may hold one legal identity and
> several agency contracts over time. Collapsing them would destroy commercial
> history that Money and Recruitment read today.

---

## 7. Partner contact audit

Proved by running it:

```
three identical contacts on one organisation → all three accepted
is_primary = 1 rows on that organisation     → 3
one contact e-mail across two organisations  → accepted (and legitimate)
```

| Question | Answer |
|---|---|
| Duplicate contacts possible? | **Yes, unlimited** |
| Uniqueness anywhere? | **None**, at any level |
| Can several rows claim `is_primary`? | **Yes — proved.** "The primary contact" is therefore ambiguous, and readers take whichever row sorts first |
| Same person at several organisations? | **Yes, and legitimate** — a consultant, a group finance officer. Must stay possible |
| What does a contact belong to? | `partner_id` only. It has no independent existence |
| Contacts without an organisation? | **No** — `partner_id` is always set by every writer |
| Does creating a contact create a login? | **No** |
| Can a login exist without a contact? | **Yes** — `/join` creates one with `contact_id` unset |
| E-mail-based automatic linking? | **Yes** — `portal_backfill_contact_links()`, and it is **correctly bounded to the same `partner_id`**. It is the one automatic identity link in the product that is scope-safe |
| Is that linking audited? | **No** |
| Unlink / relink? | **No path** |
| Stale links after an organisation change? | **Possible** — nothing re-checks |

**`partner_contacts` is not a Person table and this audit does not make it one.**
Human identity is not inferred from e-mail anywhere in the proposal.

---

## 8. Client user / vendor user audit

| Question | `client_users` | `vendor_users` |
|---|---|---|
| Creation | portal invite · **`/join` (public)** | vendor portal invite |
| Organisation link | `partner_id` | `vendor_id` |
| Contact link | `contact_id`, optional | `contact_id`, optional |
| E-mail uniqueness | **PHP-side only, read-then-write, no index** | same |
| Tenant | structural | structural |
| Branch / SBU scope | **none** | **none** |
| Organisation change / account transfer | **no path found** | **no path found** |
| Audit | **none** | **none** |
| Is it a Person record? | **No — an account** | **No — an account** |

### Authorisation — the one thing that is right

`portal_partner_id()` reads the organisation **from the signed-in session**, not
from a posted id:

```php
function portal_partner_id() { $u = portal_user(); return $u ? (int)$u['partner_id'] : 0; }
```

**A portal account cannot be pointed at another organisation by a forged id**,
because no route accepts one. Ownership is established from the account, exactly
as invariant **I25** requires. This is recorded as a **positive finding**.

**Cross-tenant:** structural. One database per tenant; no organisation path opens
a second connection. Same basis as Batch 1's tenancy, which **is** tested.

---

## 9. Duplicate detection matrix

| Representation | Exact duplicate evidence | Possible duplicate evidence | Legitimate multiple | Detection today |
|---|---|---|---|---|
| `business_partners` | GSTIN · PAN · TAN · CIN | normalised name · website · e-mail domain | subsidiaries, separate legal entities in a group | **exists, used by 4 of 8 writers**, exact and possible not distinguished |
| `cx_organisations` | `party_id` already present | name | one party with genuinely distinct marketplace audiences | **none** |
| `agencies` | GSTIN | name · contact e-mail | **one company, several contracts over time** | **none** |
| `partner_contacts` | (partner, e-mail) | name · mobile | **one person at several organisations** | **none** |
| `client_users` | e-mail | — | one person with accounts at two client organisations | PHP-side, raceable |
| `vendor_users` | e-mail | — | as above | PHP-side, raceable |

**Ambiguous cases must REPORT, ASK or REVIEW — never merge.** No fuzzy matching,
no AI matching, no automatic organisation merging is proposed anywhere.

---

## 10. Organisation role matrix

**Role is an attribute, not a separate organisation** — and the model already
gets this right where it is used:

| Role | Representation today | Mechanism |
|---|---|---|
| Client | `business_partners.is_client` | **attribute** ✔ |
| Vendor | `business_partners.is_vendor` | **attribute** ✔ |
| Sub-contractor | `business_partners.is_subcontractor` | **attribute** ✔ |
| Marketplace participant | `cx_organisations` + `party_id` | **relationship** ✔ |
| Business capability | `cx_org_capabilities` | **relationship** ✔ (unique-indexed — the one place that is) |
| **Recruitment / manpower agency** | **`agencies`, with no link at all** | **duplicated data** ✘ |

**One gap, and only one:** an agency has no way to say which organisation it is.
Every other role is already an attribute or a relationship.

---

## 11. Tenant and scope matrix

| Boundary | Status |
|---|---|
| Tenant | **Structural** — database per tenant. No cross-tenant organisation path exists |
| Branch | `business_partners.home_branch_id` exists but is **not enforced** on creation or on duplicate search |
| SBU | **not applicable** to organisations in this model |
| Owner | **Enforced for portal accounts** (§8) — from the session, never a posted id |
| Marketplace scope | `cx_organisations.party_id` + capabilities |

**A record ID is not used as authorisation anywhere in the organisation paths.**
This is a genuine positive.

---

## 12. Concurrency findings

| Scenario | Result |
|---|---|
| **C — three simultaneous `/join`, same organisation, same e-mail** | **TESTED · FAILS.** 3 logins with one e-mail, 3 partners, 3 marketplace organisations |
| A — two simultaneous business-partner creations | **NOT TESTED.** By inspection the protection is `SELECT → INSERT` with no index, so the outcome is the same class as C |
| B — two simultaneous agency creations | **NOT TESTED.** No check exists at all, so concurrency is not the weak point |
| D — partner created while an organisation representation is created | **NOT TESTED** |

**Every duplicate control on every organisation path is `SELECT → INSERT`, and
no organisation table carries a unique index.**

---

## 13. Historical integrity findings

Queries designed; **no historical record was read from production and none was
changed.** Classification for each pattern:

| Pattern | Class |
|---|---|
| Two `business_partners` sharing a GSTIN / PAN / TAN | **duplicate** |
| Two sharing only a normalised name | **possible duplicate** |
| Two `cx_organisations` with the same `party_id` | **possible duplicate** — may be legitimate |
| `cx_organisations` with `party_id = 0` | **orphan / unmapped** |
| `agencies` whose name or GSTIN matches a partner | **ambiguous — manual review** |
| `agencies` with no partner relationship | **valid** — there is no column to fill |
| Several `partner_contacts` with the same (partner, e-mail) | **duplicate** |
| Several `is_primary = 1` on one partner | **duplicate** |
| One contact e-mail across organisations | **legitimate multiple** |
| `client_users` / `vendor_users` with `contact_id = 0` | **valid** — optional |
| Accounts whose `partner_id` names no partner | **orphan** |
| Two accounts sharing an e-mail | **duplicate** |

**Batch 3 repairs none of it.**

---

## 14. Invariant status

| Invariant | Status | Evidence |
|---|---|---|
| **I8** one organisation may hold multiple roles without becoming multiple organisations | **PARTIAL** *(was HOLDS)* | True for client/vendor/sub-contractor flags. **False for agencies**, which are a separate representation with no link, and false in practice because `/join` creates a second organisation for a company that already exists |
| **I34** organisation identity not duplicated merely because the role differs | **VIOLATED** | §1 — an existing client self-registering gets a second partner |
| **I35** every organisation representation resolves to the spine | **VIOLATED** | `agencies` has no cross-reference column at all (**R4**) |
| **I28** uniqueness protected at database level | **VIOLATED** *(for organisations)* | No unique index on any of the six tables |
| **I29** concurrent creation produces one record | **VIOLATED** | §12 scenario C, tested |
| **I25** a record id is never authorisation | **HOLDS** *(organisation paths)* | §8 — ownership from the session |
| **I15** cross-tenant impossible | **HOLDS** *(same structural basis as Batch 1, which is tested)* | §11 |
| **I16** branch scope | **NOT APPLICABLE** to organisation creation as modelled today |
| **I22 · I42** partial failure is safe and reconcilable | **VIOLATED** | `/join` writes three tables with **no transaction**; a failure leaves an organisation nobody can sign in to, and nothing reports it |
| **I32** "keep separate" is recordable | **VIOLATED** | No mechanism, for organisations either |
| **I41** a failed observation is not a failed transaction | **NOT APPLICABLE** | These paths write no audit at all |

### One new invariant proposed

> **I44 — A public, unauthenticated route must never be able to create a second
> organisation for a company the system already knows.**
> *Owning domain:* Organisation. *Testable condition:* register through `/join`
> using the legal name, GSTIN or PAN of an existing partner; assert no second
> `business_partners` row. *Status today:* **VIOLATED** (§1).

No other invariant is proposed. The register is not inflated.

---

## 15. Security findings

| # | Finding | Severity |
|---|---|---|
| **S1** | `/join` is public and unauthenticated, and three concurrent calls create three logins with the **same e-mail** — the account-uniqueness rule the portal depends on | **CRITICAL** |
| **S2** | `/join` creates a business partner and a marketplace organisation with **no duplicate detection**, so an outsider can inject organisation records that shadow real customers | **CRITICAL** |
| **S3** | `/join` has **no transaction**: a partial failure leaves an organisation nobody can sign in to, unreported | **HIGH** |
| S4 | Portal ownership is resolved **from the session**, never from a posted id | **POSITIVE** |
| S5 | No organisation creation is audited anywhere | **MEDIUM** |
| S6 | The generic masters CRUD writes `agencies` with no check of any kind | **MEDIUM** |

---

## 16. Findings summary

| Severity | Count | Findings |
|---|---|---|
| **Critical** | **2** | S1 `/join` concurrency · S2 `/join` no duplicate detection |
| **High** | **3** | S3 `/join` no transaction · agencies have no cross-reference (**R4**) · no unique index on any organisation table |
| **Medium** | **5** | `crm.php` no check · `leads.php` name-only · `partner_contacts` unlimited duplicates and multiple primaries · no creation audit · agencies via generic CRUD |
| **Low** | **2** | Contact links never re-checked after an organisation change · no unlink/relink path for a contact↔account link |
| **Informational** | **3** | Portal ownership is session-based ✔ · the auto-link is correctly scope-bounded ✔ · role-as-attribute is already right for client/vendor/sub-contractor ✔ |

**Total: 15.**

---

## 17. Open questions

### Implementation defects (no decision needed)
- `/join` does not call the detector the business already owns.
- `/join` is not transactional.
- `crm.php` calls no detector.

### Architecture questions (owner decision required)
- **Q19** — Should `agencies` carry a `party_id` cross-reference? *(Recommended: yes, optional and additive — §18.)*
- **Q20** — Should `find_duplicate_partner()` distinguish an **exact** tax-identifier match from a **possible** name match, so callers can refuse one and warn on the other?
- **Q21** — Should `/join` **refuse** on an exact match, or **route to a claim/review flow**? A public route that says "your company already exists" discloses that it exists.
- **Q22** — Is `(partner_id, lower(email))` the right contact uniqueness key, and should one primary be enforced?
- **Q23** — Should portal account e-mail uniqueness (**R31**) be global or per organisation?

### Later-batch work
R4 (agency cross-reference) · R27 (`/join` duplicate control) · R30
(`partner_contacts` duplicates) · R31 (portal e-mail) · organisation creation
audit · historical reconciliation.

**Q1–Q18 remain untouched and unanswered.**

---

## 18. Recommended implementation boundaries

Detail in `P6-BATCH3-ORGANISATION-IMPLEMENTATION-PLAN.md`. The shape:

| Action | What |
|---|---|
| **REUSE** | `find_duplicate_partner()` — it is good; call it where it is missing |
| **EXTEND** | give it an exact/possible verdict; give `/join` a transaction |
| **CONNECT** | `/join` → the detector |
| **MAP** | one optional, additive `party_id` on `agencies` — **a cross-reference, not a merge** |
| **MIGRATE** | **nothing** |
| **DEPRECATE** | **nothing** |
| **BUILD** | **nothing** |

---

## 19. Explicitly NOT to change

- **No table is merged.** `business_partners`, `cx_organisations` and `agencies`
  stay distinct.
- **No generic replacement organisation table. No Organisation hub. No Person hub.**
- **No historical migration. No automatic duplicate merging. No fuzzy matching.**
- **`cx_organisations` does not become the universal organisation master.**
- **`partner_contacts` does not become a Person table.**
- **`client_users` / `vendor_users` remain accounts.**
- **Batch 1 and Batch 2 are untouched**, as are Operations, Reporting, Money,
  Workforce, Marketplace architecture, the Phase 5 KPI engine, Phase 4
  allocation and the Recruitment conversion.

---

## 20. Scope confirmation

**Batch 3 did not create a Person hub, Organisation hub, merge engine, fuzzy
identity engine, duplicate engine, new permission engine, or new KPI engine.**

**No product code was changed.** The probes in §1, §6, §7 and §12 ran against a
throwaway database, changed nothing, and are reproducible from the code quoted
beside them.
