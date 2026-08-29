# 02 — Permission Matrix

Who can do what to each object, traced to real `can()` checks and route guards.
Verbs: **View**, **Create**, **Edit**, **Delete**, **Approve**, **Close**, **Issue**,
**Reopen**, **Export**, **—** (none). **⚠ implicit** = allowed only because no check
blocks it (see `99-gaps-and-risks.md`). Paths relative to `phpapp/`.

**Column keys:** MA=MASTER_ADMIN, AD=ADMIN, BD=BUSINESS_DIRECTOR, SBU=SBU_HEAD,
BM=BRANCH_MANAGER, BAM=BRANCH_APP_MANAGER, OM=OPERATION_MANAGER, CO=COORDINATOR,
AM=ASST_MANAGER, FIN=FINANCE, INS=INSPECTOR. (SR_INSPECTOR = INSPECTOR + `idems.finalize`;
sales roles BDM/KAM/MM/ME are in the CRM mini-matrix below.)

**How to read cells:** the cell shows the strongest right the role holds *by default*.
Master (MA) bypasses every gate (`access.php:530`). "Edit" implies View. Per-user
overrides and the Settings→Roles editor can change any of this at runtime.

## Operational & admin modules

| Module / Object | MA | AD | BD | SBU | BM | BAM | OM | CO | AM | FIN | INS |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Calls** ¹ | E·D | E·D | View | View | Edit | View·D | Edit | Edit | Edit | View | — ᵃ |
| **Jobs** ² | E·C | E·C | Allocate | Allocate | E·C | View | E·C | E·C | Allocate | View ⚠ᵇ | View/Close **own** ᵇ |
| **Vouchers** ³ | Full | Full | View | View | Approve | — | Approve | Approve | Approve | — | **own** V/E/Submit |
| **Profitability** ⁴ | View | View | View | View | View | — | View | — | — | View | — |
| **Business Partners** ⁵ | E | E | E | E | E | E | E | E | E | View | — |
| **Contracts (open)** ⁶ | Endorse+Approve | Endorse+Approve | Endorse | Endorse | Approve | Approve | Endorse | — | — | Register/Reopen | — |
| **Purchase Orders** ⁷ | E | E | E | E | E | E | E | E | E | E | — |
| **Hiring / Candidates** ⁸ | E | E | View | View | E | — | E | E | — | — | — |
| **Inspector master** ⁹ | E | E | E | E | E | E | E | — | — | — | — |
| **Attendance** ¹⁰ | Recon | Recon | Recon | Recon | Recon | Recon | Recon | Recon | Recon | — | self-mark ⁱⁱ |
| **Office Overheads** ¹¹ | E | E | E | E | E | E | E | — | — | — | — |
| **Lookups** ¹² | E | E | E | E | E | E | E | — | — | — | — |
| **Custom fields (per-entity)** ¹² | E | E | E | E | E | E | E | — | — | — | — |
| **Custom form builder** ¹³ | E | E | — | — | — | — | — | — | — | — | — |
| **Users** ¹⁴ | E(global) | E(global) | — | — | E(own office) | E(own office) | — | — | — | — | — |
| **Dashboards** ¹⁵ | View | View | View | View | View | View(ops) | View | View | View | View(money) | View(own) |
| **Settings** ¹⁶ | E | E | — | — | — | — | — | — | — | — | — |
| **Invoicing / Receipts** ¹⁷ | Full | Full | — | — | View | — | — | View | — | Issue | — |
| **Inspection reports (IDEMS)** ¹⁸ | Full | Full | View | Finalise | Finalise | config only | Finalise | Edit | Edit | View | Edit(own) |

**Sales / CRM roles** (they touch only the CRM modules; `—` elsewhere):

| Module | BDM | KAM | MM (Marketing Mgr) | ME (Marketing Exec) |
|---|---|---|---|---|
| Inquiries ¹⁹ | Edit | Edit | Edit | Edit |
| Quotations ¹⁹ | Create·Send | Create·Send | Create·**Approve**·Send | Create |
| Orders/contracts ¹⁹ | Edit | Edit | Edit | View |
| Sales reports ¹⁹ | View | View | Edit | View |
| Clients (directory) | View | View | Edit | View |
| Contract register ⁶ | — | — | — | — |
| Templates ¹⁹ | — | — | Manage | — |

---

### Footnotes (each cell traceable)

1. **Calls** — module gate `mod.calls.view` (`ops.php:2243`); create/edit `can('ops.call.create')||is_master()` (`ops.php:3695`) → BM,OM,AM,CO; delete `can('ops.call.delete')` (`ops.php:3588`) → BAM. Module edit default per `access.php:332-346`. **ᵃ** Inspectors have no calls module; they only see a call's context through their own job.
2. **Jobs** — module gate `mod.jobs.view` (`ops.php:2244`). Allocate/edit `is_coordinator_level()` (`ops.php:5163`) → all admin-level + AM + CO. **Close (`job-close`) is gated on `ops.job.close`** (R2 fixed): `ops_require(is_master() || can('ops.job.close') || job_owned_by_me($id))` (`ops.php` job-close) → CO, BM, OM (hold the permission) + master + the **job-owner inspector** bypass (`ops.php:2384-2388`). Roles that only *view* jobs — **FIN, SBU, BAM, AM** — can no longer close them (none hold `ops.job.close`). Report-approve `can_approve_report()` (`workforce.php:669-677`).
3. **Vouchers** — register `is_coordinator_level()` (`ops.php:4890`); a voucher's owner sees/edits their own (`can_view_voucher`/`can_edit_voucher` `ops.php:4738,4761`). Submit=owner or coord (`4989`); approve=coord, status SUBMITTED (`4993`); paid=coord (`4998`); reopen=coord, **no source-status guard** (`5002`, gaps §B-2). No segregation of duties on approve (gaps §B-2).
4. **Profitability** — computed, read-gate only `can('data.profitability')` (`ops.php:6124`); call-profit adds `data.revenue`/admin (`callprofit.php:62`). No lifecycle (see doc 03).
5. **Business Partners** — list gated `mod.clients/vendors.view` (`index.php:879`); create/edit/detail/sub-records are now **guarded (R1 fixed)**: create/edit/add need `mod.clients.edit`/`mod.vendors.edit`/coordinator-level/master; the 360 detail needs the matching view rights. An INSPECTOR can no longer create, edit or read partner records.
6. **Contracts (open)** — register `can('crm.contract.register')||is_master()` (`crm.php:1830`) → FIN; endorse `can_endorse_contract_open()` (`contracts.php:473-477`) → OM,SBU,BD,ADMIN + `users.manage.branch` (BM,BAM); approve `can_approve_contract_open()` (`contracts.php:478-481`) → BM,BAM; reopen `crm.contract.register` (`contracts.php:690`). **Note:** the *partner-screen* contract-add path (`index.php` partner-add `kind=contract`) is now gated by the **same** `can('crm.contract.register')||is_master()` check (R4 fixed) — so both doors are Accounts-only.
7. **Purchase Orders** — now **guarded (R1 fixed)**: viewing a PO (`po`) needs `mod.clients/vendors.view` (or coordinator-level/master); mutating one (pull-quote, add line) or adding via `partner-add kind=po` needs the edit right or `finance.reconcile`. No longer open to any logged-in user.
8. **Hiring** — module gate `mod.hiring.view` (`ops.php:2263`); create/edit & stage-move `is_coordinator_level()` (`ops.php:4317,4486,4557`). Engagement-mode config `is_admin_level()` (`ops.php:2502`).
9. **Inspector master** — `/m/inspectors`, master config `access='admin'` → `is_admin_level()` (`ops.php:2049,2460`); allowances/rates `is_master()` (`ops.php:3418`); salary field gated `can_see_salary()` (`ops.php:3428`).
10. **Attendance** — reconcile `is_coordinator_level()` (`ops.php:6177`); **ⁱⁱ** self-mark (`attend-mark`) is intentionally ungated beyond "login linked to an inspector" (`attend.php:189`, gaps §B-4).
11. **Office overheads** — edit `is_admin_level()||can('settings.manage')` (`ops.php:6041`); global default `settings.manage||master` (`ops.php:6052`).
12. **Lookups & per-entity custom fields** — `is_admin_level()` (`lookups.php:635`); delete built-in list `is_master()` only (`lookups.php:659`). Not in the module-gate map — guarded in the handler.
13. **Custom form builder** — `cform_can_manage()` = `is_master()||can('settings.manage')` (`customforms.php:64,91`).
14. **Users** — `can('users.manage.branch')||can('users.manage.global')` (`ops.php:6608`); branch managers limited to own office (`ops.php:6618`); role-access editor `is_master()` (`ops.php:2412`). Global default = MA/AD only.
15. **Dashboards** — landing `/` only needs login; `ops_reports` needs a `dash.*` perm (`ops.php:6416`), each tile gated individually. Inspector gets the personal branch (`dashboard.php:23`).
16. **Settings** — `can('settings.manage')` (`ops.php:6806`); module-licence toggle `is_master()` (`ops.php:6812`); seed/reset `is_master()` (`ops.php:2531+`).
17. **Invoicing/Receipts** — open/view `books_can()`=finance.reconcile||data.credit||master (`booksui.php:87`) → FIN, CO, BD/SBU/BM (via data.credit), MA; all mutations `$canIssue||data.credit` (`booksui.php:190`); **issue** `books_can_issue()`=finance.reconcile||master (`booksui.php:257`) → FIN; cancel same (`booksui.php:265`).
18. **IDEMS reports** — view `mod.idems.view` (`ops.php:2290`); create/edit `mod.idems.edit` + `idems_can_edit_doc` (`idems.php:4185,3467`); approve step `idems_can_act_step` (`idems.php:5732`); finalise `is_master()||idems.finalize` with **approver≠issuer** (`idems.php:4456,4460`); type/format config `idems.type.manage` (`idems.php:4604+`); timestamps `idems.timestamp.edit` → BAM (`idems.php:7357`); audit log `idems.audit.view` (`idems.php:9772`).
19. **CRM modules** — module gates `mod.inquiries/quotes/crm_orders/crm_reports.*`; fine-grained `crm.quote.create/approve/send`, `crm.template.manage` per `access.php:456-461`. Quote-approve default = MARKETING_MANAGER only (`access.php:459`); **not** BRANCH_MANAGER.

## Connect — marketplace (external portals, K2b)

The manpower-marketplace staff desk (K2a) reuses coordinator/master and adds **no**
permission. The **external self-service** side (K2b) adds two named portal
permissions, in the portals' own permission systems — separate from `ORG_ROLES`:

20. **Client portal — post a requirement** — `pcan('market.post')` (`portal.php` `PORTAL_PERMS`; routes `portal/hire`, `portal/hire-req`). A logged-in **client (company)** posts a technical-manpower requirement (posted to its own `poster_party_id`) and manages **its own** requirements' applications (shortlist / offer / award / reject) — ownership enforced in the route. Granted by default to a full-access client user; removable per client user via the portal team editor. Maps `company` → client portal (adopted role→portal mapping).
21. **Vendor portal — apply to requirements** — `vcan('market.apply')` (`cvp.php` `VENDOR_PERMS`; route `vendor/opportunities`). A logged-in **vendor / agency** browses OPEN/SHORTLISTING requirements and applies as itself (`applicant_party_id` = the vendor party; one application per requirement). Maps `agency` → vendor portal. (Agency-side *posting* reuses the same engine and is a later toggle; not enabled in K2b.)

Neither touches `ORG_ROLES` or any staff permission; the staff desk lifecycle
(K2a) and these external capabilities operate on the same additive
`cx_requirements` / `cx_applications` tables.

### Qualification & role taxonomy — configuration (K13 / #2)

The ITI→MBA qualification taxonomy (`/connect-qualifications`) adds **no new
permission** — it reuses the **Lookups** gate (row 12) exactly:

- **View** (read-only, at a glance): `connect_qualtax_can()` — master, admin-level
  or coordinator-level (same readers as the K0 industry taxonomy).
- **Configure** (add / edit / switch on–off every job family, role, qualification
  level, ITI trade, certification): `connect_qualtax_manage_can()` = `is_admin_level()`
  — the same door as Lookups & custom fields. The masters are runtime data, not
  hard-coded; a seeded row is marked **built-in** (`is_system=1`) and can be edited
  or switched off by an admin.
- **Hard-delete a built-in row**: `is_master()` only (mirrors Lookups' "delete
  built-in list = Super Admin only"). Admin-added rows are deletable by any admin.

No `ORG_ROLES` entry, object status or module gate is introduced.

### Verification & moderation desk (K14 / #3)

The verification desk (`/connect-verify`) adds **no new named permission** — it
reuses the coordinator/moderation level, like other back-office review desks:

- **Review** (approve / reject a pending identity or credential check, which
  recomputes the professional's verification tier): `connect_verify_can()` =
  `is_master()` or `is_coordinator_level()`.
- **Submit** a check about oneself: a **professional** does this from their own
  `/pro` portal (`pro/verify`) on their own record — the portal's own session
  (`cxpid`), not an `ORG_ROLES` permission.
- The tier ladder (Registered → ID-verified → Credential-verified → Proven) is
  written to the existing `cx_professionals.verification_tier` column and is
  elevated **only** by a genuine VERIFIED decision (human moderator now, or a
  KYC/DigiLocker provider through the same seam later) — never by a deterministic
  format pre-screen alone. No object status or module gate is introduced.

### In-app messaging (K15 / #4)

Per-engagement two-way threads (`cx_messages`, keyed to a `cx_applications` row)
add **no new named permission**:

- **Staff desk** (`/connect-messages`): read/post on any marketplace thread —
  `connect_msg_staff_can()` = `is_master()` or `is_coordinator_level()` (same as
  the rest of the marketplace desk).
- **Professional** (`/pro/messages`): read/post **only on their own engagements**
  (`applicant_professional_id` = the logged-in `cxpid`), enforced by
  `connect_msg_pro_owns()`; their own `/pro` portal session, not an `ORG_ROLES`
  permission.
- The engine is identity-agnostic (`staff | professional | client | vendor |
  inspector`), so the client and vendor portals attach to the same threads later
  under their own portal sessions. No object status or module gate is introduced.

### Engagements / bookings + freelancer self-service (K20)

The booking model (`cx_engagements`) and the freelancer gap-fill add **no new
permission**:

- **Record / edit a booking basis** (man-days / man-months / long-term deputation /
  continuous / regular frequency), rate, dates and lifecycle status: on the
  requirement desk, which is already coordinator/master-gated (K2a). A booking can
  be recorded only **after** the requirement is AWARDED, and its subject is derived
  from the awarded application (professional / inspector / bench).
- **Withdraw an application**: a professional withdraws only their **own** live
  application (APPLIED / SHORTLISTED / OFFERED → WITHDRAWN) via their `/pro`
  session (`connect_pro_withdraw`, scoped to `cxpid`); never someone else's.
- **My bookings** (`/pro/bookings`): a professional sees only engagements where they
  are the subject (subject-scoped). No object status beyond the documented
  application/requirement lifecycles is introduced; `cx_engagements.status`
  (BOOKED / ACTIVE / COMPLETED / CANCELLED) is the engagement's own lifecycle.

### Matching, cross-pool trust & AI re-ranking (K17 / #6)

Adds **no new permission**. The recommender and its cross-pool Trust Score render
on the requirement desk, which is already coordinator/master-gated (K2a). The
optional **"Rank with AI"** toggle simply reuses the existing `ai.php` seam
(`ai_enabled()`) plus a `connect_ai_match` on/off setting (admin, via Settings);
AI may only reorder/annotate the rule-provided shortlist — it can never change
eligibility, invent a candidate, or bypass any gate.

### Labour-market analytics (K19 / #8)

The analytics dashboard (`/connect-analytics`) is **read-only** and adds **no new
permission**: `connect_analytics_can()` = `is_master()` or `is_coordinator_level()`
(the marketplace/reporting desk). Every figure is a live aggregation over the
existing cx_* tables — no new table, status or module gate is introduced.

### Agency bench workspace (K18 / #7)

The agency bench (`/connect-bench`) adds **no new named permission** and enforces
the **privacy invariant**:

- **Manage** an agency's private roster + allocations: `connect_bench_can()` =
  `is_master()` or `is_coordinator_level()` (the marketplace desk).
- **Privacy**: bench people live in their own `cx_bench` table, scoped by
  `org_id`, and are **never** written into the shared self-registered pool
  (`cx_professionals`) or surfaced in public search / the shared recommender — an
  agency's employees stay private to that agency (an invariant asserted by
  `test_connect_bench.php`). Every bench read/allocation is org-scoped, so one
  agency can never see or allocate another's people.
- Only orgs of type `MANPOWER_AGENCY` / `RECRUITMENT_AGENCY` may hold a bench.
  No object status or module gate is introduced.

### WhatsApp / SMS / Email channel (K16 / #5)

Outbound alerts (`/connect-channels`) add **no new named permission**:

- **View** the channel desk (outbound log, counts): `connect_channels_can()` =
  `is_master()` or `is_coordinator_level()`.
- **Configure** (set delivery mode; edit / approve / enable templates):
  `connect_channels_manage_can()` = `is_admin_level()` — same door as Settings /
  Lookups.
- **Consent** is the professional's own: they opt in per channel (WhatsApp / SMS /
  Email) from their `/pro` profile; WhatsApp/SMS need a mobile. No opt-in → no
  message. Nothing is *sent* until an admin sets mode `live` AND a provider is
  connected AND the template is APPROVED — until then messages are recorded, not
  sent. No object status or module gate is introduced.
