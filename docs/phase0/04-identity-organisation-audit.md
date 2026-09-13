# 04 — IDENTITY / PERSON AUDIT & ORGANISATION AUDIT
Covers deliverables **12, 13**.

---

## 1. Identity / Person audit

### 1.1 Eleven tables represent a human

| Table | Created at | Identity columns | Module |
|---|---|---|---|
| `users` (staff login) | `lib/db.php:148` | `id`, `username` UNIQUE, `email`, `inspector_id`, `reports_to_id` | Auth/RBAC |
| `inspectors` (workforce) | `lib/ops.php:136` | `id`, `emp_code`, `email`, `mobile`, `passport_token`, `signature` | Ops/PDSO/competence |
| `candidates` (recruitment) | `lib/ops.php:192` | `id`, `cand_code`, `email`, `mobile`, `inspector_id`, `person_ref` | Recruitment |
| `cx_professionals` (marketplace) | `lib/connect_pro.php:32` | `id`, `email` **UNIQUE**, `mobile`, `passport_token` | Connect |
| `partner_contacts` | `lib/db.php:162` | `id`, `partner_id`, `email`, `mobile` | Partners/CRM |
| `client_users` (client portal) | `lib/portal.php:51` | `id`, `partner_id`, `contact_id`, `email` | Portal |
| `vendor_users` (vendor portal) | `lib/cvp.php:58` | `id`, `vendor_id`, `contact_id`, `email` | CVP |
| `back_office_staff` | `lib/ops.php:131` | `id`, `emp_code`, `email`, `mobile` | Ops/costing |
| `subcons` (agency inspector) | `lib/ops.php:142` | `id`, `agency`, `inspector_name`, `email` | Ops |
| `cx_bench` (agency roster) | `lib/connect_bench.php:25` | `id`, `org_id`, `name`, `professional_id` | Connect |
| `cx_client_bench` (client roster) | `lib/connect_client_bench.php:29` | `professional_id` or `manual_name/email/mobile` | Connect |

### 1.2 THREE identity mechanisms exist, and they do not agree

**(a) `lib/identity.php` is NOT identity resolution.** It is the ID-document vault — owns `person_documents` (`:142`) and `person_document_access` (`:161`). Its `person_kind` is nominally polymorphic but **every caller passes `INSPECTOR`** (`lib/competence.php:947`, `lib/pdso.php:316`, `lib/ops.php:4000`; `:797` hardcodes it).

**(b) `lib/party.php` — a stateless matcher owning no table.** `party_stores()` (`:21-38`) declares six stores (USER, INSPECTOR, CANDIDATE, CONTACT, CLIENT_USER, VENDOR_USER) — **`cx_professionals` is absent**. `party_key()` (`:45`) = ref → last-10 mobile → lowercased email; `party_records_for()` (`:87`) unions matches at query time. **Used in only three places** (`views/ops/candidate_detail.php:548`, `lib/entity360.php:55`, `lib/vendor360.php:24`).
*Latent defect:* its name expressions use SQLite `||` concatenation inside try/catch (`party.php:24,29`), so **on MySQL names degrade silently** — the same `||` class of bug already hit production this session.

**(c) `lib/connect_identity.php` — the only persisted link ledger.** Owns `cx_identity_link(professional_id, inspector_id, party_id, candidate_id, status)` (`:26-43`), with `cx_professionals` as hub and `connect_person_resolve()` (`lib/connect_person.php:27-51`) giving the transitive candidate↔inspector view.

### 1.3 Links that actually exist
- `users.inspector_id → inspectors.id` (`lib/ops.php:273`; uniqueness enforced `lib/access.php:843-855`) — the only FK-style person link outside Connect.
- `candidates.inspector_id → inspectors.id`, written on hire (`lib/ops.php:5088`).
- `candidates.person_ref` threads multiple applications by one human (`lib/recruit.php:1024,1086-1098`).
- `cx_identity_link` pro↔inspector (`:98`) and candidate↔pro (`:148`).
- `client_users.contact_id` / `vendor_users.contact_id → partner_contacts.id` — nullable, **never resolved back**.
- **No link at all** for `back_office_staff`, `subcons`, or `users ↔ cx_professionals`.

### 1.4 FINDING F4 — nothing prevents cross-table duplication (High)
Every uniqueness guard is **within one table**. `cx_professionals` blocks repeat email/mobile (`connect_pro.php:216,232`); `client_users`/`vendor_users` block repeat email in their own table; `users.username` is UNIQUE.

**`inspectors`, `candidates`, `back_office_staff`, `subcons`, `partner_contacts` have NO uniqueness at all**, with free-text insert paths at `ops.php:1046, 3982, 5082`.

One hired freelancer can therefore legitimately exist as: a `candidates` row *per application*, an `inspectors` row, a `users` row, a `cx_professionals` row, a `cx_bench` row, and — if they later work for a client — a `partner_contacts` + `client_users` pair. **Up to seven records for one human.**

Detectors exist but only **suggest**: `connect_identity_suggestions()` (`:172`), `candpool_pro_matches()` (`lib/candpool.php:52`), `connect_pro_duplicates()` (`:193`). All require human confirmation **by design** — *"a relationship, never a merge"* (`connect_identity.php:12-17`). That design choice is sound and should be preserved; the gap is that there is no canonical spine for the relationship to hang from.

### 1.5 Canonical spine candidate: `inspectors`
On the code as it stands, `inspectors` is referenced by 51 lib files (vs `cx_professionals` 25, `candidates` 19). It is already the target of **every existing hard person link** — `users.inspector_id`, `candidates.inspector_id`, `cx_identity_link.inspector_id`, `cx_applications.inspector_id` — and carries the attribute tables (`inspector_certs`, `person_documents.person_id`, `passport_token`, `signature`).

`cx_professionals` has the only UNIQUE key and is the hub of the *newer* ledger, but is Connect-only and invisible to `party_stores()`.

**Ruling: `inspectors` is the spine the rest of the 155k LOC already points at. Its name is wrong for a general platform (it means "workforce person"), but renaming is cosmetic and risky — CONNECT, do not MERGE.**

---

## 2. Organisation audit

### 2.1 Organisation entities
- **`business_partners`** — the single table for clients *and* vendors, discriminated by type; with `partner_contacts`, `partner_addresses`, `partner_registrations`, `partner_contracts`, `partner_purchase_orders`, `partner_relationships` (37 admin/core tables). **This is a genuine shared core and works well.**
- **`cx_organisations`** (`lib/connect_*`) — the marketplace's own organisation record.
- **`agencies`** (`lib/ops.php:259`) — recruitment/ops supplier agencies, with `one_time_fee`/`guarantee_days`.
- **`offices`** — our own locations; the scope spine for RBAC (`scope_clause()`).

### 2.2 FINDING — the organisation is stored three ways
`business_partners` (client/vendor), `cx_organisations` (marketplace), `agencies` (supplier) are three representations of "a company we deal with", with no link between them.

Consequence: a recruitment agency that is also a marketplace supplier and a billing vendor exists three times, and its performance cannot be viewed as one relationship.

Also note `candidates.agency` is a **free-text VARCHAR(150)** with **no `agency_id` FK** (`lib/ops.php:197`) — so "which supplier gave us this CV" is an unjoinable string. This directly blocks the brief's §13 *Source Party* requirement.

### 2.3 Internal structure
`offices`, `positions` + `reports_to` (`lib/position.php:20`), `department` lookup master, designation↔department via `lookup_values.attr_department` (`lib/deptorg.php:20`), org chart `/positions-org`, department hub `/departments`, and an organogram importer (`lib/organogram.php`) that creates offices, departments, designations, positions, codes and reporting lines from Excel/CSV/PPT/image.

**This is a strength and is recent.** The gap is semantic, not structural — see `05-requirement-taxonomy-audit.md` §2.3.
