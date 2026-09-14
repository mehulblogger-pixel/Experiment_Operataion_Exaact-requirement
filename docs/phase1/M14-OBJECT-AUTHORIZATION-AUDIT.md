# Milestone 14 — Object-Level Authorization Audit

**Status:** complete · **Suite:** 8,245 passed, 0 failed · **Baseline:** 3819b5a (M13)

M13 left a number behind: **72 list-level scope checks against 9 object-level
ones.** M14's job was not to make those numbers match. It was to find out *why*
each important object is reachable, and then try to reach the ones that should
not be.

---

## 1. The inventory — what was actually there

A scan of every `SELECT/UPDATE/DELETE … WHERE id = ?` across `lib/`, `views/`
and `index.php` found:

| | |
|---|---:|
| by-id statements | **1,290** |
| distinct tables | **189** |
| tables with schema resolved | 306 |

Testing 1,290 statements one by one would be theatre. They were classified
instead, by the only question that decides whether a check is even *possible*:
**which scope dimension does this record carry?**

| Scope carried | Tables |
|---|---:|
| **No scope column at all** | **166** |
| Branch (`office_id` / `executing_office_id`) and/or Business Unit (`sbu`) | 43 (of those fetched by id) |
| Client (`client_id` / `partner_id`) | 38 |
| Owner (`user_id` / `created_by`) | 60 |
| Vendor / agency | 9 |
| Professional / organisation (Connect) | 13 |

The 166 with no scope column are configuration, vocabulary and templates. They
are **global within a tenant on purpose**, and §19 says leave them alone.

That left **43 branch-scoped registers reachable by id** as the real target.

---

## 2. The test that decides whether something is a vulnerability

Not "does this route have a check". That counts lines. The test used throughout
M14 was:

> **Does the register's own LIST hide this record from this user, while the
> detail serves it?**

If the list shows it too, the register is not branch-scoped in this product's
design, and adding a check would *hide records the application intends to be
seen* — the over-fix §19 and §30 warn about. If the list hides it and the detail
does not, the two doors disagree, and that is a defect by the application's own
standard.

Every finding below was decided that way, and every one was **executed**: real
routes, real dispatch, real session, two branches, with the victim record
carrying a marker string that either appeared in the response or did not.

---

## 3. The attacker

Deliberately built to isolate one variable:

* **every permission the product has** (full ADMIN permission set), and
* **the wrong branch** (scoped to Branch A; every victim record in Branch B).

Because the claim being tested is precisely that **permission is not scope**. A
user who may edit quotations must not thereby edit *every* quotation.

---

## 4. Confirmed vulnerabilities — seven, all proven before any fix

| # | Object | Attack that succeeded | Severity |
|---|---|---|---|
| **O1** | Quotation | Another branch's quote opened by id — customer, subject, commercial terms. Its PDF export too | **High** |
| **O2** | Opportunity | Opened, **edited**, **stage-changed** and **DELETED** by id | **High** |
| **O3** | Lead | Opened, **edited** and **DELETED** by id | **High** |
| **O4** | Lead document | Another branch's attached file **downloaded in full** by id | **High** |
| **O5** | Requisition | Another branch's manpower requisition opened by id, with its candidates | Medium |
| **O6** | Complaint | Another branch's complaint opened by id | Medium |
| **O7** | Receipt | Another branch's receipt — payer and amount — opened by id | Medium |

Proof of O2, verbatim from the harness:

```
lead-delete          MUTATION: *** OBJECT DELETED ***
lead-edit            MUTATION: *** OBJECT CHANGED ***
                     DIFF={"company_name":"MARKERCOMPANY -> PWNED", ...}
opportunity-delete   MUTATION: *** OBJECT DELETED ***
opportunity-move     MUTATION: *** OBJECT CHANGED ***
                     DIFF={"stage_id":" -> 2","probability":"0 -> 25", ...}
```

And O4:

```
=== download Branch B's lead document as a Branch A user ===
LEN=14 MARKERS=["MARKERFILEBODY"]
```

Read access was the least of it. **Delete worked.**

---

## 5. The fix — one door per module, not thirty-five checks

Thirty-five routes were reachable. Guarding each in turn would have been
thirty-five chances to forget one, and the next route added would be the
thirty-sixth. So the guard went where the **module is entered**, the same shape
`ops_module_gate()` already has for entitlement:

| Module | Gate | Routes covered |
|---|---|---:|
| Leads | `lead_scope_gate()` in `ops_leads()` | 12 |
| Opportunities | `opp_scope_gate()` in `ops_opportunities()` | 13 |
| Quotations | `crm_quote_scope_gate()` in `ops_crm_quotes()` | 26 |
| Complaints | `cmp_scope_gate()` in `ops_complaints()` | 12 |
| Requisitions | `req_scope_gate()` in `ops_requisitions()` | 4 |
| Receipts | inline, mirroring the invoice guard beside it | 1 |

Three route families name a **child** id rather than the record's own — an
attached file, an approval step. Each gate resolves those to the parent, because
a document is exactly as confidential as the thing it is filed against. That is
also why a blanket gate would have been wrong: on `lead-file`, `id` is a
*file* id, and looking it up in `leads` would have guarded the wrong object.

### One new helper, and the reason it had to exist

The codebase had **two** list rules, and they disagree about one thing:

```
scope_clause()          COALESCE(office, Ahmedabad) IN (...)    — no office means Ahmedabad
scope_office_clause()   (office IS NULL OR office IN (...))     — no office means EVERYONE sees it
```

`scope_allows()` was already the object-level twin of the first. The second had
**no twin**, so `scope_office_allows()` was added. Borrowing `scope_allows()`
for leads, opportunities, complaints and receipts would have quietly started
hiding **unassigned** records — the ones the register deliberately shows to every
branch so that nothing nobody has claimed gets lost. That is a bug the tests
would never have caught, because it hides data rather than exposing it.

Tested explicitly, and passing: an unassigned lead, complaint and receipt remain
visible to a branch-scoped user.

---

## 6. Attacked and found sound

| Surface | Result |
|---|---|
| **Call, job, invoice, voucher, CAPA details** | Already guarded (Phase 2 / M13). Re-attacked, all refused |
| **Cross-tenant, both directions** | Refused. One database per tenant means an id from A names nothing in B, *and* M13's workspace binding refuses the identity |
| **Export / print** | `quote-pdf`, `invoice-print`, `voucher-print` all refused. The quote PDF closed with its module's gate |
| **Master** | Crosses branch scope — ALL-scope by architecture, documented, and still bound to one tenant by M13 |
| **Malformed ids** (zero, negative, 20-digit, nonexistent, `7abc`, empty, `1 OR 1=1`, array, padded) | Every one reaches a clean decision. No PHP warning, no SQL error, no partial write |

---

## 7. Classified, NOT fixed — and why

§19 and §30 are explicit: prove the intended scope before changing it.

| Object | Classification | Evidence |
|---|---|---|
| **Candidates** | **Not branch-scoped by design.** Not a vulnerability | The candidates list shows the same record to the same user. Carries `sbu` only; the attacker's Business-Unit scope is ALL. Detail and list agree |
| **Project costings** | **Branch-global by design of its own list** | `pc_all()` has no scope clause whatsoever. Gating the detail would hide records the list shows. Guarded by `pc_can()` at module level. **Recorded as an open surface for architectural review — see limitations L2** |
| **Internal audits, risks, samples** | Branch-global by design | Their lists carry no scope clause either |
| **166 tables with no scope column** | Global within tenant | Configuration, vocabulary, templates |

A vulnerability is a disagreement between two doors. Where both doors agree,
M14 recorded the reason and left the code alone.

---

## 8. Changes — seven files, 122 insertions, 0 deletions

`lib/access.php` (the new twin helper) · `lib/leads.php` · `lib/opportunities.php` ·
`lib/complaints.php` · `lib/crm.php` · `lib/ops.php` · `lib/booksui.php`.

No schema change. No data touched. No existing authorization weakened, no second
authorization system, no RBAC redesign, no mass edit of by-id queries.
