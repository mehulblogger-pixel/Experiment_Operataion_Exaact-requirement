# 01 — Current-State Inventory (Phase A / Discovery)

A factual map of what EXAACT already is, so the revamp classifies rather than
guesses. Sources: repo `README.md`, `docs/00–03`, `docs/phase-2` / `phase-3`,
`docs/99-gaps-and-risks.md`, and a direct read-only survey of `phpapp/lib`,
`phpapp/views`, and the schema.

**Scale:** ~120 engine files in `lib/`, **~215 database tables**, ~240 screens
(208 `views/ops` + `crm/` + `idems/` subfolders, 24 client-portal, 15
vendor-portal), **17 roles**, **30 permission-gated modules**, **3 layered
packaging switches**. Live app is `phpapp/` (PHP 8 + MySQL/SQLite, no build step).

---

## 1. The canonical spine (already in code)

The commercial-to-cash and inspection-to-issue spines both exist and are
documented as canonical (`docs/phase-2/02-canonical-application-model.md`):

**Commercial spine:** Lead → Opportunity → Quotation → (accept) → Order/Contract →
Call → Job/Deputation → Site execution (geo, photos, QAP autofill, attendance) →
Inspection Report → completeness gate → Submit → Vetting → Approval → Issue
(immutable, QR) → Release Note → Client-portal accept/reject → NCR → CAPA →
Invoice (after close) → Profitability / Reconciliation → Dashboards.

**Inspection spine (§3):** assignment → competence gate → impartiality gate →
schedule → inspect → evidence → applicable report(s) → submit → vetting →
return-to-inspector → approval chain → issuance-readiness gate → issue (sealed) →
deliver → invoice → pay → archive.

The spine is **threaded by the `contract_number` string**, not a first-class
Engagement entity (`engagement()` in `lib/engagement.php` is a *read-only*
grouping; the canonical Engagement entity is explicitly **deferred**).

---

## 2. Engines by capability area (`lib/`)

Core (README): `ops.php` (dispatcher + calls/jobs/candidates/vouchers/masters),
`crm.php`, `workforce.php`, `terms.php`, `idems.php`, `access.php`, `lookups.php`,
`pdf.php`, `ai.php`, `db.php`.

**People / resource master & competence:** `party.php` (canonical person resolver,
read-only mapping over 6 stores), `identity.php` (ID documents + access log +
`site_doc_requirements`), `competence.php` (authorisations / qualifications /
witness assessments — "is this engineer authorised for this work on this date"),
`inspectorprofile.php`, `recruit.php` + `recruit_cc.php` (recruitment command
centres), `rating.php`, `company.php`.

**Commercial pipeline:** `leads.php`, `opportunities.php`, `pipelines.php`,
`stagegate.php` (deal-stage approval gates), `contracts.php` (validity/overrides),
`industry.php` (CRM templates), `dedupe.php`, `engagement.php` (read-only spine
grouping).

**Operations & field execution:** `tosrm.php` (scheduling/resource mgmt — call
clarifications, nudges, SLA targets, readiness), `pdso.php` (Project Deputation &
Site Ops — manpower, checklist, timesheet, site log/history), `schedule.php` +
`schedboard.php`, `attend.php` + `attendreview.php` + `timesheet.php` +
`geofence.php` (self-service attendance, anomaly review, geofenced punch),
`trust.php` (evidence↔place/time chain, site check-in), `assets.php`,
`equipment.php` (calibration fitness), `services.php` (service scope/catalog),
`tasks.php` (canonical persisted task), `joblock.php`, `areas.php`.

**Inspection / quality:** `idems.php` (report engine), `urfe.php` (Universal
Report Foundation — report library/sections/fields/statuses), `uire.php`
(Universal Inspection Reporting — criteria packs), `uvae.php` / `uvaae.php`
(Universal Vendor Assessment / MS-Audit), `urade.php` (Universal
Release/Acceptance/Disposition), `ncr.php`, `capa.php`, `ncdca.php` (universal
NC/deviation/concession/dispute), `risks.php`, `samples.php` (item custody),
`methods.php`, `hwpoints.php` (hold/witness/review points), `impartiality.php`,
`audits.php` (internal audits + management review), `complaints.php` (+ appeals),
`confidentiality.php`, `satisfaction.php`, `disclosure.php`, `datacontrol.php`.

**Financial / billing:** `books.php` (the real ledger — invoices, invoice_lines,
receipts, receipt_allocations, credit_notes) + `booksui.php`, `bills.php`
(chargeable expenses), `costing.php` (office cost runs/allocations),
`projcosting.php` (bid/deputation cost build-up), `callprofit.php` (per-inspection
profit, computed), `receivables.php` (ageing, read-only), `revrecon.php` (revenue
reconciliation, read-only), `finevent.php` (financial-event stream, read-only
projection), `invready.php` (invoice-readiness gate), `settlement.php`
(inter-office), `billing.php` (per-user SaaS billing / Razorpay).

**Reporting / documents:** `urfe.php`, `reportreview.php` (client accept/reject),
`controldocs.php` (§8.3 document control), `numbering.php`, `tmplpreview.php`,
`idems_autoform.php`, `compose.php`.

**Platform / config / security:** `security.php`, `visibility.php` (one visibility
vocabulary + single-record IDOR gate), `settingmeta.php` (settings governance),
`decisionrules.php`, `retention.php` (§8.4 retention), `compliance.php`
(consents/erasure/incidents — DPDP), `customforms.php` (no-code register/form
builder), `datatable.php` (shared table for ~42 registers), `trace_audit.php`
(golden-thread), `licence.php` + `licencekey.php` + `licenceissue.php` +
`licencesync.php` (product licensing), `agreement.php`, `tenants.php` + `cpanel.php`
(multi-workspace SaaS), `orgadmin.php`, `superadmin.php`, `setup.php`, `reset.php`,
`packs.php` (industry-pack framework), `search.php`, `navindex.php`, `bulk.php`
(dry-run preview), `advisor.php`, `portal.php` (client portal), `cvp.php` (vendor
portal).

**360 / analytics:** `entity360.php`, `customer360.php`, `vendor360.php`,
`mis.php` (single source of numbers), `crmdash.php`, `chain.php` (CRM→ops→books
thread), `activity.php` (the activity spine — one `activities` table),
`tapi*.php` (TAPI analytics: KPI defs/targets/snapshots/scorecards/alerts).

**Integrations:** `booksbridge.php` (MGH Books), `tally.php` (Tally export),
`adssync.php` + `adspro.php` + `adsroi.php` (Ads Pro / ROI), `partnerimport.php`,
`mghsso.php` (SSO), `webhookq.php` (generic outbound `integration_outbox`, Phase 3
§50 — retry/backoff, delivery OFF by default).

---

## 3. Screens by area (~240)

| Area | ~count | Notable screens |
|---|---|---|
| People / Recruitment / Workforce | 22 | candidate_*, requisition_*, recruitment_home, recruitment_cc, deputations, departures, agency_staff, inspector_*, competence, availability, attendance_recon, attendance_review, timesheet, work_norms, hierarchy |
| CRM / Sales | 30 | crm/inquiry_*, crm/quote_*, crm/approval_rule_*, leads, lead_*, opportunities, opportunity_*, pipelines, stage_gates, activities, preorder_checklist, dedupe, customer360, entity360, adspro, adsroi |
| Calls / Jobs / Operations | 28 | operations_home, ops_desk, command_centre, calls, call_*, raise_call, jobs, job_*, schedule_board, capacity_outlook, hwpoints, tasks, approvals, client_holds, contract_openings/detail/overrides, agreement, service_scope, sla_targets, trace/trace_thread |
| Inspection / IDEMS | 40 | idems/register, builder, autoform, smart, fill, writing, doc_*, report_types, numbering, templates, template_preview, form_from_template, vet_review, vetting_checklist, approval_rules, approver_map, endorse_*, evidence, learning, audit, release_register, expediting_*, vendor_register/detail; verify (public), methods_*, samples_*, equipment_*, cdocs_*, drules_* |
| Quality / NCR / CAPA / Audits / Compliance | 35 | ncr_*, capa_*, audits_*, complaints/*, incidents/*, risks_*, impartiality, confidentiality, conf_breach, disclosure, consents, satisfaction_*, reviews_*, compliance, data_control, data_requests, privacy, retention, person_erase, identity, identity_access |
| Finance / Billing / Costing | 28 | billing, billing_pay, invoicing, invoices, invoice_*, receipts, receipt_*, receivables, to_bill, ledger, recurring, voucher_*, cost_run, project_costing*, profitability_*, office_finance, sbu_pl, books_bridge, tally_export |
| 360 / Dashboards / Analytics | 13 | dashboard (landing), tapi + tapi_* (KPIs, scorecard, drill, alerts, quality), mis, reports, system_status, customer360, entity360 |
| Settings / Admin / Platform | 35 | settings, setup, super_admin, reset_data, company, terminology, custom_fields, lookups, masters, cforms_admin, cform_*, users, user_form, access, tenants, sso, two_factor, integrations, ai_settings, licence*, search |
| Portals (external) | 39 | client portal_users/perms + `views/portal/` (24: dashboard, calls, reports, report_decide, invoices, complaints, deputations, issues, request, assistant, off…); `views/vendor/` (15: dashboard, reports, issues, qualification, alerts, assistant, off…) |

**Mobile / field / offline:** `my_jobs`, `my_work`, `idems/fill`, `idems/writing`,
`idems/smart`, `idems/my_signature`, self-service `availability` + attendance
self-mark; PWA (`manifest.php`, `sw.js`, `assets/js/offline.js`) with local draft
autosave and an offline sync queue; `portal/off.php`, `vendor/off.php`; public
login-less `verify.php` (report authenticity) and `crm/quote_external.php`.

---

## 4. Roles, permissions, modules

**17 ORG_ROLES:** MASTER_ADMIN, BUSINESS_DIRECTOR, SBU_HEAD, BRANCH_MANAGER,
BRANCH_APP_MANAGER, OPERATION_MANAGER, ASST_MANAGER, COORDINATOR,
BUSINESS_DEV_MANAGER, KEY_ACCOUNTS_MANAGER, MARKETING_MANAGER, MARKETING_EXECUTIVE,
FINANCE, SR_INSPECTOR, INSPECTOR, ADMIN (legacy). Groupings: `MGMT_ROLES` →
`is_admin_level()`; `SALES_ROLES`; `is_coordinator_level()` = MGMT ∪ ASST_MANAGER ∪
COORDINATOR. Master (`is_superuser`) bypasses every gate. Two **separate** external
permission systems: client portal (`pcan()`, `lib/portal.php`) and vendor portal
(`vendor_users`, `lib/cvp.php`).

**Permission groups:** Dashboards & sensitive figures (`dash.*`, `data.credit/
revenue/salary/profitability`); Operations (`ops.call.create`, `ops.job.allocate/
close`, `ops.call.delete`, `workforce.availability/report.approve`); IDEMS
(`idems.finalize/type.manage/timestamp.edit/audit.view/template.approve`); Money
(`finance.reconcile`); CRM (`crm.quote.create/approve/send`, `crm.followup.manage`,
`crm.contract.register`, `crm.template.manage`); Identity docs (`person.iddoc.view/
manage` — never a role default, DPDP); Complaints/quality (`complaints.decide`,
`capa.close`, `ncr.close`); Administration (`master.manage`, `users.manage.branch/
global`, `org.hierarchy.view`, `settings.manage`).

**30 modules** (`mod.<key>.view/.edit`): inquiries, quotes, crm_orders,
crm_reports, leads, idems, calls, jobs, vouchers, invoicing, profitability, hiring,
reconcile, clients, vendors, masters, overheads, portal, equipment, competence,
impartiality, identity, complaints, ncr, confidentiality, capa, audits,
datacontrol, reports, users, settings. Resolution precedence in `ua()`:
MASTER_ADMIN=all → per-user CSV override → Settings→Roles JSON → built-in default;
new modules back-filled via `merge_new_module_defaults()`.

---

## 5. Packaging switches (already present — the basis for product packages)

1. **Industry packs** (`lib/packs.php`): `inspection` (ISO/IEC 17020) / `labs`
   (ISO/IEC 17025). `accredited_pack_on()` gates whether accreditation rules
   *fire* via 5 hooks (`work.assign`, `document.issue`, `record.close`,
   `customer.create`, `timeline.extra`); clause numbering switches 17020↔17025.
2. **Product-module licensing** (`lib/licence.php`): 6 saleable bundles —
   `operations`, `admin` (core, never off), `sales`, `reporting`, `money`, `hr` —
   enforced inside `can()` via `licence_blocks()` *before* the master bypass; a
   signed key outranks the settings screen.
3. **Per-role / per-user module gates**: 30 `mod.<key>` areas through the single
   `can()` choke point; scope (ALL vs OWN offices/SBUs) is a parallel axis
   (`scope_clause()`).

Finer config: terminology overrides (`terms.php`), custom fields per entity, the
no-code custom form/register builder (`customforms.php`), and settings.

---

## 6. Object lifecycles (from `docs/03-object-lifecycles.md`)

- **Call** — two status columns. Legacy `calls.status`: OPEN→FORWARDED→ALLOCATED
  (`CLOSED` read but **never written** — vestigial). `calls.op_status` (TOSRM):
  RECEIVED→UNDER_REVIEW→ACCEPTED→READY_TO_SCHEDULE→SCHEDULED→ASSIGNED→IN_PROGRESS→
  COMPLETED→CLOSED (rank-based, forward-only) + ON_HOLD/REJECTED/CANCELLED.
- **Job** — real state = `closed_flag` + `report_approval` (''→PENDING→APPROVED/
  REJECTED) + `invoice_raised`. `jobs.stage` is **vestigial** (only ever written
  CANCELLED).
- **Voucher** — DRAFT→SUBMITTED→APPROVED→PAID (+reopen); month-freeze locks edits;
  maker≠checker enforced.
- **Quotation** — DRAFT→PENDING_APPROVAL→APPROVED→SENT→ACCEPTED/LOST/EXPIRED;
  immutable once SENT (revisions via parent_id/rev/is_current).
- **Contract opening** — PENDING→OPEN→(CLOSED); two-signature endorse→approve,
  approver≠endorser.
- **Inspection report** — DRAFT→SUBMITTED→VETTING→UNDER_REVIEW→APPROVED→ISSUED
  (+REJECTED "Sent back"; `ARCHIVED` **vestigial**); `finalized` locks at issue;
  approver≠issuer; issue gated by chain+completeness+AI-QA+calibration.
- **Profitability** — **computed, no stored record, no status** (`job_profit()`
  pure calculator); only month-end cost freeze is persisted (an edit lock).

---

## 7. Known gaps already logged (`docs/99-gaps-and-risks.md`, R1–R12)

R1–R11 marked FIXED/MITIGATED; residuals that matter to this revamp:

- **R4 (open, lower risk):** contract registration has a second door
  (`partner-add kind=contract`) that skips the PENDING→endorse→approve
  two-signature lifecycle.
- **R9 (noted):** the cost picture is written from **two sides** — job-closure
  expenses (coordinator) and the inspector's voucher — a data-model overlap.
- **R10 (noted):** vestigial statuses the code never advances — `calls.status=
  CLOSED`, `jobs.stage` intermediates, `report_docs ARCHIVED`; e.g. an ops-desk
  `report_pending` metric reads `jobs.stage='REPORT_PENDING'` (never written → always 0).
- **Financial dual-truth (§29/§80):** `jobs.invoice_amount` / `payment_received`
  (legacy snapshot read by boss_profit/MIS) flagged "reconcile before switching
  readers"; `finance_truth_unified` is ON so canonical readers use `job_profit`.
- Portals use separate permission systems (`pcan()`, `vendor_users`) — only
  summarized, not fully permission-mapped.

These are the CONNECT / REFACTOR items in `02-gap-and-reuse-map.md` — not rebuilds.
