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
