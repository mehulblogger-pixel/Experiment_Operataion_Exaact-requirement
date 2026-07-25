# Exaact Inspection & Operations Management System

**This file is the single source of truth for the architecture.** It is kept at
the repo root so it is present on every branch. If you (or another Claude
session) pick this up on a new branch, read this first — it explains what the
live app is, how it is built, every table, every route, and how to run and
extend it.

**The five modules, at a glance:**

| Module | Library | What it owns |
|---|---|---|
| **Operations** | `lib/ops.php` | Calls → jobs → closure, expenses, vouchers, invoicing, credit, hiring |
| **CRM** | `lib/crm.php` | Inquiry → quotation → approval → contract → revenue tracking |
| **Workforce** | `lib/workforce.php` | Availability, hours cap, working norms, org hierarchy, escalations |
| **Vocabulary** | `lib/terms.php` `lookups.php` | One name and one list per concept, both editable on screen |
| **IDEMS** | `lib/idems.php` | Inspection reports, formats, approvals, endorsement, evidence, audit (**§12a**) |
| **Platform** | `lib/access.php` `lookups.php` `pdf.php` `ai.php` | Roles & permissions, configurable masters, PDF, AI |

> 🏢 **Organisation model — read this before touching offices, targets or scope.**
> **Commercially**, the **Head Office is Mumbai** (the registered/commercial HO).
> **Operationally, there is NO head office.** Every office is an **independent
> office** with **its own targets, its own operations, its own P&L**, and runs
> its own calls/jobs/inspectors. Offices are peers — not branches reporting up to
> a managing office. Ahmedabad is simply one such office (the one this instance
> was first configured for); it is **not** a headquarters and must not be treated
> as one. Inter-office work is a **peer handoff with credit** between two equal
> offices, never "HQ → branch". Design every feature (dashboards, scope, targets,
> reminders) so each office stands alone by default, with roll-ups only for people
> whose access explicitly spans offices.

> ⚠️ **The live application is `phpapp/` — plain PHP 8 + MySQL.**
> It runs on **MilesWeb shared hosting** by simply uploading files (no build
> step, no Node, no Python, no Composer). The `accounts/`, `masters/`,
> `operations/`, `dashboard/` (Django) and `nodeapp/` (Node) folders are **older
> prototypes** kept for reference only. **Do not build features there.** All new
> work goes in `phpapp/`.

---

## 1. What the system does

An operations & finance system for a third-party inspection (TPI) services
network of **independent, peer offices** across India. Each office owns its
targets, operations and P&L; the **commercial HO is Mumbai**, but operations are
**decentralised — there is no operational head office**. Offices collaborate on
work through inter-office **credit** (a peer handoff, not an HQ→branch flow). It
replaces a planned Microsoft SharePoint/Power Apps/Power Automate/Power BI build
with a self-hosted PHP app that needs **no Microsoft licenses**.

The system now spans the **whole commercial lifecycle**:

> **Inquiry → Quotation → Approval → Contract → Call → Job → Inspection Report →
> Approval → Issue → Invoice**, with the workforce, documentation and compliance
> layers around it.

### A. Operations (the original core)
- **Clients / Vendors** master (business partners) with contacts, addresses,
  registrations (GSTIN/PAN/TAN), contracts, purchase orders + line items.
- **Calls** (inspection calls received) → forwarding to an executing branch with
  mandatory credit → **Jobs** (allocation to an inspector/sub-con).
- **Job closure**, reports, deliverables, TAT tracking, **expenses**.
- **Sub-contractors**, rate matrix, **attendance**, holidays, comp-off.
- **Inter-office credit** logic + reconciliation.
- **Invoicing & payment** per job + dashboard reconciliation.
- **Inspector master**: names, trade→skill, multi-SBU, certifications w/ expiry.
- **Hiring / CV pipeline** (deputation resourcing) with CV keyword extraction.
- **Dashboards**: role-aware landing page + Operations / Financial / Utilization /
  People insight boards, plus an **executive board** (FY revenue, YoY, target,
  top clients).

### B. CRM — Marketing & Sales (`lib/crm.php`)
- **Inquiries → Quotations** with revisions, per-line SBU, and auto quote numbers.
- **Approval matrix** by amount threshold and/or SBU, or routed up the
  **reporting-manager chain**.
- Quote document from an uploaded **Word template** (doc/format numbers stamped)
  **and** a client-ready **PDF** with signature + customisable letterhead.
- **Send to customer** by e-mail with the PDF attached; **auto follow-ups**
  (3/6/9-day, fortnight, month) until acceptance.
- Acceptance → **client & contract registration** → **operations packet** floated
  to the delivery team → job linked back to the quote for **revenue tracking**.
- **Advance / payment gate**: a job can be held (report not issued) until the
  agreed advance is received.
- Lost-reason capture, win/loss analysis, sales dashboard, monthly report.

### C. Workforce & organisation (`lib/workforce.php`)
- **Daily inspector availability board** per office (free / on job / leave /
  training / office / half-day / WFH), one click to set, on-job auto-derived.
- **Daily cap on logged working hours** (8.5 by default, set in Settings),
  enforced on the timesheet.
- **Working norms** — weekly days *and* hours **per designation per office**,
  inherited by each person unless overridden.
- **Reporting manager** per person → **automatic N+1 org hierarchy** (printable),
  driving both CRM approvals and inspection report sign-off.
- **Automated reporting**: overdue-report chase, escalation to the reporting
  manager, and a scheduled **MIS digest** to leadership.

### D. IDEMS — Inspection Documentation, Reporting & Endorsement (`lib/idems.php`)
The TPIA industry pack — see **§16** for the full description.

### E. Platform
- Fully **configurable master lists** (incl. dependent/hierarchical) and
  **custom fields** on any form — a non-coder can add dropdowns without code.
- **Exhaustive, role-divided permissions** with one-click "recommended set per
  role" presets.
- **AI providers** (OpenAI / Claude / Gemini / Perplexity / GitHub Models):
  admin stores keys, model lists auto-refresh, features degrade gracefully when
  no key is set.
- **Installable PWA**: works on a phone, keeps local drafts, syncs on reconnect.
- **Settings**: app name, logo, theme, financial-year start, TAT threshold,
  revenue target, escalation days, hours cap, weekly working days, currency
  symbol, date format.
- **Terminology**: rename every business noun the app displays — Client, Quote,
  Inspection Call, Deputation, Report, Inspection Engineer, IBO, BOSS Number —
  once, and every heading, menu, button and e-mail follows.

See **`phpapp/PENDING.md`** for the living build log and any parked items.

---

## 2. Technology stack

| Layer | Choice | Why |
|---|---|---|
| Language | **PHP 8** (no framework) | Runs on any cPanel/MilesWeb host, upload-and-go |
| DB (prod) | **MySQL** (via PDO, `utf8mb4`) | Standard on MilesWeb |
| DB (dev) | **SQLite** (via PDO) | Zero-setup local testing (`DB_DRIVER=sqlite`) |
| Front controller | `phpapp/index.php` + `.htaccess` | Single entry, clean routes |
| CSS/JS | Hand-written, no build | `assets/css/app.css`, `assets/js/app.js` |
| Email | `mail()` / optional Office365 SMTP | Logged to `email_log`; mailto fallback |
| Scheduled jobs | `phpapp/cron.php` via cPanel Cron | Reminders, escalations, follow-ups, MIS digest |
| PDF | `lib/pdf.php` — hand-rolled `SimplePDF` | No library; quotes, reports, endorsement certificates |
| Word (.docx) | ZipArchive + token replace | Client-format output; **no** PHPWord dependency |
| Images | GD (compression, signatures) | Falls back gracefully if GD is absent |
| AI | `lib/ai.php` — `ai_chat()` over cURL | Optional; all AI features degrade cleanly without a key |
| Mobile / offline | `manifest.php` + `sw.js` + `assets/js/offline.js` | Installable PWA, no build step |

**No build tools.** There is nothing to compile. Editing a `.php` file and
re-uploading it is the entire deploy.

**Extensions used** (all standard on MilesWeb): `pdo_mysql`, `zip` (.docx),
`gd` (image compression & signatures), `curl` (AI), `exif` (photo timestamps).
Every one of them is optional-safe: the feature that needs it explains itself if
it is missing, and nothing else breaks.

---

## 3. Deployment (MilesWeb / any cPanel host)

1. Upload the **contents of `phpapp/`** to the web root (e.g. `public_html/`).
2. In MilesWeb → **Databases**, create a MySQL database + user.
3. Edit **`config.php`** — set `name`, `user`, `pass` (host stays `localhost`).
4. Visit the site. On the **first request** the app auto-creates every table and
   seeds defaults (offices, lookup lists, admin user). No migration command.
5. Log in as **`admin` / `admin12345`** (from `config.php`) and change the
   password from the app (Password link, top-right).
6. (Optional) Add two **cron jobs** pointing at `cron.php` — see
   `phpapp/README-MilesWeb-PHP.md`.

Full host-specific steps: **`phpapp/README-MilesWeb-PHP.md`**.

### `config.php`
```php
$DB = ['driver'=>'mysql','host'=>'localhost','name'=>'...','user'=>'...','pass'=>'...'];
$ADMIN = ['user'=>'admin','pass'=>'admin12345'];
// Env vars override (used by tests): DB_DRIVER, DB_HOST, DB_NAME, DB_USER, DB_PASS, ADMIN_PASSWORD
```

---

## 4. Request lifecycle (how a page is served)

`phpapp/index.php` (the only entry point) does, in order:

1. `session_start()`.
2. Installs a **readable error page** (`register_shutdown_function` +
   `set_exception_handler`) so a missing file or DB error shows a helpful
   message, never a blank 500. Missing-file errors name the missing file.
3. `require`s the library files, **in this order** (later ones may use earlier
   ones): `lib/db.php`, `helpers.php`, `ops.php`, `lookups.php`, `access.php`,
   `crm.php`, `pdf.php`, `ai.php`, `workforce.php`, `idems.php`, `seed_demo.php`.
4. **Bootstrap probe**: runs a few tiny `SELECT`s against the *newest* columns
   and tables (e.g. `SELECT irn FROM report_docs LIMIT 1`). If any fails (fresh
   install or a not-yet-migrated upload) it calls **`boot()`**, which runs every
   idempotent migration + seed in one pass. This is why one uploaded file can
   upgrade the whole schema. **When you add a new table/column, add one probe
   line here** — otherwise a live MySQL database will never gain it.
5. Serves **PWA assets** (`/sw.js`, `/manifest.php`, `/assets/*`) *before* the
   auth gate, with a path-traversal guard, so the app installs on a phone
   regardless of the host's rewrite rules.
6. Parses the route from the URL path (single segment; ids/tabs via query
   string), requires login for everything else.
7. Dispatches: partner/PO routes are handled inline in `index.php`; everything
   else goes through **`ops_dispatch($route, $method)`** in `lib/ops.php`, which
   first applies the **module gate** (`mod.<key>.view`) and then routes into the
   ops / CRM / workforce / IDEMS handlers. Returns `view('notfound')` if nothing
   matches.

### `boot()` (in `lib/db.php`)
```
migrate()           // core tables (users, business_partners, partner_*, lookups, custom_*, settings)
ops_migrate()       // ops tables + ALL ensure_column() upgrades  (calls ops_ensure_schema + ops_seed)
lk_migrate()/lk_seed()   // lookup type/value seeding
access_migrate()    // user scope columns, settings schema, one-time demo cleanup
crm_migrate()       // CRM tables (after lookups exist, so masters can be seeded)
idems_migrate()     // IDEMS tables + report-type & phrase-library seeding
ensure_admin()      // create the admin user from config
auto_seed()         // any first-run demo content
```

**Migrations are idempotent.** Helper `ensure_column($table,$col,$def)` adds a
column only if missing. `CREATE TABLE IF NOT EXISTS` for new tables. You never
edit existing data in a migration — only add.

---

## 5. File map (`phpapp/`)

```
index.php                 Front controller: bootstrap, boot-probe, router, PWA assets,
                          partner + PO routes
config.php                DB credentials + admin login (edit on the server)
cron.php                  Scheduled runner: reminders, escalations, quote follow-ups,
                          IDEMS approval SLA, MIS digest  (call from cPanel Cron)
manifest.php              PWA manifest (name/theme follow Settings)
sw.js                     Service worker (offline page cache; never caches POSTs)
.htaccess                 Pretty-URL rewrite + service-worker headers

lib/db.php                PDO connection, base schema, boot(), migrate(), admin/seed, pk_clause()
lib/helpers.php           e(), flash(), redirect(), user_name(), small utilities
lib/ops.php               THE ops engine: constants, schema, migrations, roles, masters,
                          calls, jobs, candidates(hiring), inspectors, vouchers, invoicing,
                          reports, users, settings, dispatcher, AJAX, emails, reminders
lib/lookups.php           Configurable lookup lists (types+values, hierarchy), custom fields,
                          trade→skill data, admin CRUD, seeding
lib/access.php            Roles (ORG_ROLES), permissions (PERMISSIONS), ACCESS_MODULES, can(),
                          scope_clause(), ua(), role presets, settings, theme, FY helpers
lib/terms.php             Terminology: T()/TP()/Tl()/TH() and the T_REG()/T_DETAIL()/T_NEW()
                          heading builders, the /terminology screen, module_tabs(), and the
                          merged Approval rules + Document templates hubs
lib/crm.php               CRM: inquiries, quotations + revisions, approval matrix, Word/PDF
                          quote docs, send + follow-ups, contract registration, ops packet,
                          CV keyword engine, sales reports
lib/pdf.php               SimplePDF class (A4, text, lines, JPEG embed, xref) + signature
                          normalisation + quotation PDF builder
lib/ai.php                AI provider registry, key storage (masked), model refresh,
                          ai_chat() across OpenAI/Anthropic/Gemini/Perplexity/GitHub Models
lib/workforce.php         Availability board, daily hours cap, working norms, reporting-manager
                          chain + org hierarchy, report approval, SLA escalation, MIS digest
lib/idems.php             IDEMS engine (largest): report types, IRN numbering, report
                          instances, form builder, approvals, signatures, report PDF,
                          client .docx formats, form-from-format, prefill from call/job,
                          endorsements, writing assistant, smart remarks, AI review,
                          evidence, audit dashboard, self-learning

views/
  layout_top.php / layout_bottom.php   Page shell + nav (PWA tags, offline.js)
  login_page.php dashboard.php list.php form.php detail.php po_detail.php notfound.php
  ops/  calls.php call_form.php call_detail.php
        jobs.php job_form.php job_detail.php job_close.php my_jobs.php
        candidate_*.php requisition_*.php inspector_list.php inspector_form.php
        availability.php hierarchy.php work_norms.php          (workforce)
        masters.php master_list.php master_form.php
        lookups.php lookup_values.php custom_fields.php
        reports.php settings.php access.php users.php user_form.php
        ai_settings.php attendance_recon.php voucher*.php
  ops/crm/     inquiry_*.php quote_*.php template_*.php approval_rule_*.php reports.php
  ops/idems/   register.php doc_form.php doc_detail.php fill.php builder.php
               form_from_template.php templates.php numbering.php report_types.php
               approver_map.php approval_rules.php review.php evidence.php smart.php
               endorse_list.php endorse_form.php endorse_detail.php
               writing.php phrases.php learning.php audit.php my_signature.php

assets/css/app.css        All styling (theme via --brand CSS var)
assets/js/app.js          Progressive enhancement (see §10)
assets/js/offline.js      Draft autosave, offline queue + sync, connection banner

PENDING.md                Living build log + parked work (READ THIS)
README-MilesWeb-PHP.md    Host-specific deploy + cron steps
```

---

## 6. Database schema (all tables)

Created by `ensure_schema()` (db.php) and `ops_ensure_schema()` (ops.php); later
columns added by `ensure_column()` in `migrate()` / `ops_migrate()`.

**Core / partners**
- `users` — id, username, password_hash, first/last_name, email, role,
  is_superuser, is_active, `inspector_id` (self-service link), `scope_*`
  (office/SBU scoping, added by access_migrate).
- `business_partners` — the client/vendor master. code, legal_name,
  display_name, is_client, is_vendor, is_subcontractor, client_type, industry,
  ownership_type, status, parent_id, gstin, pan, cin, tan, msme_udyam, state,
  website, description, `inspection_types` (CSV, carried into calls),
  `home_branch_id` (drives the code), created_at.
- `partner_contacts` — partner_id, `address_id`, name, designation,
  `department`, `project`, email, mobile, phone, is_primary.
- `partner_addresses` — partner_id, address_type, label, line1, line2,
  `town_village`, `district`, city, state, pincode, country, is_primary.
- `partner_registrations` — partner_id, doc_type, number, valid_to, notes.
- `partner_contracts` — partner_id, contract_number, title, `sbu`, value,
  start/end_date, notes.
- `partner_purchase_orders` — partner_id, contract_id, `sbu` (CSV multi-SBU),
  po_number, po_type, title, value (rolled up from line items), start/end, notes.
- `po_line_items` — purchase_order_id, description, item_type, `trade_id`,
  `skill_id`, `activity_id`, `site`, `manpower`, quantity, rate, consumed,
  `gst_pct`, `base_amount`, `tax_amount`, `total_amount`, `last_alert`.
- `partner_notes`, `partner_relationships`.

**Operations**
- `offices` — code, name, city, `is_ahmedabad`, `coordinator_name/email`,
  `manager_name/email` (per-office coordinator/manager for handoff &
  notifications). Offices are **peers** — each independent with its own targets
  and P&L. `is_ahmedabad` is only a legacy "primary office" flag from the first
  install; it does **not** confer HQ status and should not be used to grant any
  office authority over others. (Historical note: an earlier version modelled
  Ahmedabad as a managing office — that framing is wrong and is being unwound;
  see the Organisation model at the top.)
- `calls` — call_code, client_id, vendor_id, ibo_office_id (the peer office a
  call originated from / is credited to), `executing_office_id` (the peer office
  that will do the work), region, sbu, `activity_id`, `inspection_type`,
  `inspection_type_other`, `site_address_id`, `po_id`, `po_line_item_id`,
  product_category/other, `deputation_type`, `expected_credit`, `credit_type`,
  `notify_manager`, `forwarded_at`, call_received_date, inspection_required_date,
  notes, status (OPEN/FORWARDED/…), created_by/at.
- `jobs` — job_code, call_id, executing_office_id, inspector_id, subcon_id,
  scheduled/start/end dates, `job_type`, `stage` (ALLOCATED…CLOSED),
  `inspection_type`, `activity_id`, reporting_frequency + `report_custom_days`,
  `deliverables` (CSV), report_link, closed_flag, tat_days, sbu, mandays,
  subcon_cost, expected_credit + credit_type/direction, **invoicing**:
  `invoice_raised/number/date/due_date/amount`, `payment_received/date/amount`,
  `credit_received`.
- `expenses` — job_id, inspector_id, sbu, travel/local/food/lodging/misc, date.
- `inspectors` — first/middle/last_name, name, emp_code, `trade_id`, `sbus`
  (CSV), `skill_ids` (CSV), `designation`, `staff_kind` (ASSET/FREELANCER/
  SUBCON), email, mobile, `salary_ctc` (salary-gated), leave/compoff balances,
  status.
- `inspector_certs` — inspector_id, name, number, issued_date, valid_to,
  status, last_reminder (expiry reminders).
- `back_office_staff` — name, emp_code, designation, department, office_id,
  email, mobile, ctc, allowances (salary-gated), status.
- `subcons`, `subcon_rates`, `boss_numbers`, `holidays`, `attendance`,
  `credit_recon`, `email_log`.

**Hiring / CV pipeline** (added this session)
- `candidates` — cand_code, first/middle/last_name, client_id, call_id,
  trade_id, skill_id, designation, source (ASSET/FREELANCER/SUBCON), agency,
  proposed_site, sbu, experience_years, email, mobile, cv_link, expected_rate,
  rate_type, cv_received_date, **stage** (RECEIVED→SUBMITTED→SHORTLISTED→
  INTERVIEW→HOLD/REJECTED/ACCEPTED/WITHDRAWN), decided_at, `inspector_id`
  (set when hired), remarks.
- `candidate_events` — candidate_id, from_stage, to_stage, remark, actor,
  created_at (full stage-change history).

**Configuration engines**
- `lookup_types` — type_key, label, `parent_type_id` (for hierarchy).
- `lookup_values` — type_id, `parent_value_id`, code, label, sort.
- `custom_fields` — entity, label, field_type (text/number/date/select/
  dependent), lookup_type_id, required.
- `custom_values` — entity, entity_id, field_id, value.
- `settings` — skey (PK), svalue (MEDIUMTEXT — holds base64 logo, AI config …).

**CRM — Marketing & Sales** (`crm_migrate()` in `lib/crm.php`)
- `crm_inquiries` — inquiry_no, client, contact, subject, service requirement,
  sbu, source, received_date, assigned_to, status.
- `quotations` — quote_no, rev, parent_id, is_current, inquiry_id, client,
  contact, subject, sbu, validity, totals, status, owner_id, contract_number,
  lost_reason, advance %, created_by.
- `quote_lines` — quote_id, sbu (per line), service_type, subtypes, description,
  location, order_type, qty, unit, rate, amount.
- `quote_revisions` — full field-level history of every revision.
- `quote_approval_rules` — match_type (ANY/SBU), sbu, min/max amount, level,
  approver_role (incl. `REPORTS_TO`) / approver_user_id.
- `quote_approvals` — generated chain steps per quote, status, acted_by, remarks.
- `quote_followups` — kind (3/6/9-day, fortnight, month), due_date, status.
- `crm_templates` — Word quote formats + e-mail templates, with document_number,
  format_number, doc_revision, issue_date.

**Workforce & organisation** (`ops_migrate()` + `lib/workforce.php`)
- `inspector_day_status` — inspector_id, day, status, note, set_by (availability
  board; "on job" is derived, this stores manual overrides).
- `work_norms` — designation, office_id, weekly_days, weekly_hours
  (most-specific match wins; blank = any).
- `inspectors` gains `home_office_id`, `weekly_working_days`, `reports_to_id`,
  `signature`.
- `users` gains `reports_to_id/_name/_position/_email`, `position_title`,
  `weekly_working_days`, `signature`.
- `jobs` gains `report_approval`, `report_approved_by/_at`, `report_approval_note`,
  `last_escalation` (report sign-off by the reporting manager).

**IDEMS — inspection documentation** (`idems_migrate()` in `lib/idems.php`)
- `report_types` — code, name, category, active, is_system (32 TPIA types seeded;
  admin adds unlimited).
- `idems_counters` — scope_key → last_serial (drives zero-duplicate IRNs).
- `report_docs` — **the report instance**: irn (unique), type, title, client,
  vendor, call_id, job_id, project, PO, drawing/QAP rev, standards, location,
  material, inspector, approver, dates, result, release_status, status,
  `data` (JSON body), rev, finalized, deleted (soft), audit stamps.
- `report_sections` / `report_fields` — the **no-code form** per report type
  (18 field types, conditional show-if, calculated formulas, table columns).
- `report_files` — evidence & signatures: field_key, kind (photo/file/signature/
  sig_inspector/sig_approver/src_*), mime, base64 data, gps, sha1 (dedupe),
  caption, taken_at, bytes/orig_bytes.
- `idems_approver_map` — inspector → approver, plus a temporary approver with a
  date window (leave cover).
- `idems_approval_rules` — chain by report type / office / client / SBU, level,
  approver kind (inspector-map / reports-to / user / role), SLA hours.
- `report_approvals` — generated steps, status, acted_by, remarks, delegated_to,
  sla_due, escalated.
- `report_templates` — uploaded client .docx formats scoped by type/client/office,
  with their document/format/revision numbers.
- `endorsements` + `endorsement_files` — manufacturer document verification; the
  **original file is stored unaltered**, the endorsement is a separate record.
- `tech_phrases` — standard engineering phrase library (35 seeded) + usage counts.
- `learned_suggestions` — wording harvested from approved/issued reports
  (scope FIELD / CLIENT / REMARK / NCR), with uses + mute flag.
- `idems_audit` — **immutable** compliance log: entity, irn, action, field,
  old/new value, reason, user, role, office, ip, device, timestamp.

---

## 7. Routes (all)

Single-segment paths; ids & tabs via `?id=` / `?tab=`. Handled in `index.php`
(partners/PO) or `ops_dispatch()` (everything else).

| Route | Purpose |
|---|---|
| `/login` `/logout` `/change-password` | Auth |
| `/` | Dashboard (counts) |
| `/clients` `/vendors` | Partner lists (search, paginate) |
| `/partner-new` `/partner-edit` `/partner` `/partner-add` | Partner CRUD + sub-records |
| `/po` | Purchase order detail + line items |
| `/calls` `/call-new` `/call-edit` `/call` `/call-delete` | Calls |
| `/jobs` `/job-new` `/job-edit` `/job` `/job-close` `/job-invoice` | Jobs |
| `/my-jobs` | Inspector self-service |
| `/candidates` `/candidate-new` `/candidate-edit` `/candidate` `/candidate-stage` | **Hiring pipeline** |
| `/m/<entity>` `/m/<entity>/new|edit|delete` | Generic masters (offices, back-office, inspectors, subcons, subcon-rates, boss, holidays, attendance, credit-recon) |
| `/masters` | Master data hub |
| `/lookups` `/lookup` `/custom-fields` | Masters — every dropdown, grouped by module + custom fields |
| `/terminology` | Rename every business noun the app displays |
| `/approval-rules?module=…` | Approval rules — one screen, a tab per module |
| `/templates?kind=…` | Document templates — one screen, a tab per kind |
| `/reports` | Dashboards (4 families, filter bar) |
| `/users` `/user-new` `/user-edit` `/hierarchy` | User admin + **org hierarchy** |
| `/settings` `/access` `/ai-settings` | Settings, role presets, **AI providers** |
| `/quick-add` `/partner-meta` `/partner-sites` `/partner-pos` `/po-lines` | AJAX (JSON) |

**CRM (Marketing & Sales)**

| Route | Purpose |
|---|---|
| `/inquiries` `/inquiry-new` `/inquiry-edit` | Customer inquiries |
| `/quotes` `/quote` `/quote-new` `/quote-edit` `/quote-revise` | Quotations + revisions |
| `/quote-status` `/quote-approve` `/quote-contract` `/quote-float` | Lifecycle, approvals, contract, ops packet |
| `/quote-doc` `/quote-pdf` | Word document / client PDF |
| `/crm-templates` `/quote-approval-rules` `/crm-reports` | Templates, approval matrix, sales dashboard |

**Workforce**

| Route | Purpose |
|---|---|
| `/availability` | Daily inspector availability board (AJAX status set) |
| `/work-norms` | Weekly days & hours per designation / office |
| `/report-approve` | Inspection report sign-off by the reporting manager |

**IDEMS (inspection documentation)**

| Route | Purpose |
|---|---|
| `/documents` `/document` `/document-new` `/document-edit` | Document Register + report instances |
| `/document-fill` | Fill the designed form (autosave, suggestions, evidence) |
| `/document-submit` `/document-approve` `/document-finalize` `/document-delete` | Lifecycle + approval chain |
| `/document-pdf` `/document-docx` | Report PDF / **client-format Word** |
| `/document-smart` `/document-release-note` | Suggested remarks / auto Release Note |
| `/document-review` | Source documents + conflict checks + AI review |
| `/document-evidence` `/report-file` | Evidence gallery / file streaming |
| `/document-timestamp` | Branch-App-Manager-only date change (audited) |
| `/report-types` `/report-type-edit` `/report-builder` `/report-field-edit` | Report types + **no-code form builder** |
| `/report-templates` `/report-template-edit` `/report-template-download` | Client Word formats (reached via `/templates`) |
| `/report-form-from-template` | **Build the form from an uploaded format** |
| `/irn-rules` | Configurable IRN numbering |
| `/approver-map` `/idems-approval-rules` `/idems-approval-rule-edit` | Approver mapping + chains (reached via `/approval-rules`) |
| `/endorsements` `/endorsement*` `/endorsement-cert` | Manufacturer document endorsement |
| `/writing-assistant` `/phrase-library` `/phrase-edit` | Technical writing assistant |
| `/learning` | Self-learning insights |
| `/audit-log` | Super-admin audit & compliance dashboard (CSV export) |
| `/my-signature` | Any user captures their signature |
| `/sw.js` `/manifest.php` `/assets/*` | PWA assets (served before the auth gate) |

---

## 8. Configuration engines (the "agile" part)

**Lookup lists** (`lib/lookups.php`) — **every** dropdown in the app is data,
not code. ~60 lists / ~500 values ship seeded.
- `lookup_types` + `lookup_values`; a type can have a `parent_type_id` and a
  value a `parent_value_id` → **dependent / cascading** lists (e.g. SBU→Activity,
  Trade→Skill, Product→Wax→Tier).
- `lk_options_or($key, $CONST)` returns the configured values, **falling back to
  a PHP constant** if the list is empty — so the app works before seeding and
  after a list is emptied. Cached per request (it is called once per table row).
- `lk_module_lists()` is the registry: `[key, label, constant, module]`. Adding
  a row there is all it takes to make a new dropdown editable. `lookup_types.module`
  groups them on `/lookups` (Sales · Operations · Reporting · People · Money ·
  Directory) with a search box.
- `lk_rename_type()` / `lk_drop_type()` migrate a shipped list in place, keeping
  its values and the records pointing at them.
- Super-admin can add/edit/delete **any** list (including built-ins) from
  `/lookups`.

**One list per concept.** Where two modules used to keep their own list of the
same thing, they now share one:

| Concept | One list | Was |
|---|---|---|
| Type of work | `inspection_type` (37) | Sales' 14-value list + Operations' 30-value list |
| Charge unit | `charge_unit` (8) | quote unit + rate basis + PO line unit |
| Deliverable | the `report_types` register (37) | a separate 14-value list |
| Rejection wording | `Rejected` / `Sent back` | also "Returned" |
| Result wording | `… with observations` (ISO/IEC 17020) | "with conditions" / "with comments" |

**Terminology** (`lib/terms.php`) — every business noun the app displays comes
from one place, editable at `/terminology`.
- `T()` / `TP()` singular & plural, `Tl()` / `Tlp()` lower-case (acronyms such as
  IBO, SBU, IRN, BOSS are never lower-cased), `TH()` / `THP()` sentence case.
- `T_REG()` / `T_DETAIL()` / `T_NEW()` / `T_EDIT()` build the standard heading
  shapes, so no screen invents its own pattern.
- 27 terms across Parties / Sales / Operations / Reporting / Money / People.
  Only what differs from the shipped default is stored, so later changes to the
  defaults still land.
- Shipped vocabulary: Client · Vendor / Manufacturer / Supplier / Sub-vendor ·
  Quote · Inspection Call · Deputation · Report · Inspection Engineer · User ·
  IBO · SBU · BOSS Number · Man-day.

**Heading standard.** List screen = `<Thing> register`; detail = `<Thing> <code>`;
form = `New <thing>` / `Edit <thing>`; sentence case; `&` not "and"; no `/` or
emoji in a heading; the sidebar label is the first words of the heading it opens.

**Settings** covers the values that are not lists: financial year, revenue
target, TAT threshold, report-escalation days, max hours per day, default weekly
working days, employee-code prefix, currency symbol, date format, required
source documents, high-risk audit actions, branding / theme / logo, letterhead,
SMTP and AI providers.

**Custom fields** (`custom_fields` + `custom_values`) — add your own fields to
any form (`call`, `job`, `partner`, or any master) from `/custom-fields`. Types:
text / number / date / select (from a list) / dependent (cascading). Rendered
automatically by `render_custom_fields($entity, $vals)` and saved by
`custom_save()`.

---

## 9. Access model — two independent dials

Defined in `lib/access.php`. `ua()` computes the effective access for the
logged-in user; `can($perm)` checks a permission; `scope_clause($officeCol,
$sbuCol)` injects a WHERE fragment for row-level scoping.

**Roles** (`ORG_ROLES`): MASTER_ADMIN, BUSINESS_DIRECTOR, SBU_HEAD,
BRANCH_MANAGER, BRANCH_APP_MANAGER, OPERATION_MANAGER, ASST_MANAGER, COORDINATOR,
**BUSINESS_DEV_MANAGER, KEY_ACCOUNTS_MANAGER, MARKETING_MANAGER,
MARKETING_EXECUTIVE** (the sales roles), FINANCE, INSPECTOR, ADMIN (legacy).
`MGMT_ROLES` = the admin-level subset; `SALES_ROLES` = the CRM funnel owners.

**Permissions** (`PERMISSIONS`), grouped as they appear in the access editor:

| Group | Permissions |
|---|---|
| Dashboards & sensitive figures | `dash.operations` `dash.financial` `dash.utilization` `dash.people` `data.credit` `data.salary` `data.profitability` |
| Operations | `ops.call.create` `ops.job.allocate` `ops.job.close` `ops.call.delete` `workforce.availability` `workforce.report.approve` |
| Inspection documentation (IDEMS) | `idems.finalize` `idems.type.manage` `idems.timestamp.edit` `idems.audit.view` |
| Money | `finance.reconcile` |
| Marketing & Sales (CRM) | `crm.quote.create` `crm.quote.approve` `crm.quote.send` `crm.followup.manage` `crm.contract.register` `crm.template.manage` |
| Administration | `master.manage` `users.manage.branch` `users.manage.global` `org.hierarchy.view` `settings.manage` |

**Modules** (`ACCESS_MODULES`) add a `mod.<key>.view` / `.edit` pair each:
`inquiries` `quotes` `crm_orders` `crm_reports` `idems` `calls` `jobs` `vouchers`
`invoicing` `profitability` `hiring` `reconcile` `clients` `vendors` `masters`
`overheads` `reports` `users` `settings`.

- **SCOPE** (dial 1): which offices/SBUs a user sees — enforced in SQL via
  `scope_clause()`. Business Director = all; SBU Head = their SBU across offices;
  Branch Manager = their branch; etc.
- **PERMISSIONS** (dial 2): what a user can do/see — `can()`. Salary/loaded cost
  is gated by `data.salary` (`can_see_salary()`); credit/revenue by `data.credit`.

**Role presets** — `/access` shows each role with a plain-English description,
its recommended data scope, and **"✨ Apply recommended set"** which stores the
built-in recommended permissions in one click (recommended rows are badged).
`role_defaults($role)` supplies those defaults; per-user overrides live on the
user record. `merge_new_module_defaults()` grants brand-new modules (e.g. CRM,
IDEMS) to roles that were configured before those modules existed — so an
upgrade never leaves a role unable to see a new area.

Helper level checks: `is_master()`, `is_admin_level()` (MGMT_ROLES),
`is_coordinator_level()` (admin ∪ ASST_MANAGER ∪ COORDINATOR), `is_inspector()`.

> **Only the Branch Application Manager** carries `idems.timestamp.edit` — the
> right to change a locked date, always with a reason and always audited.

---

## 10. Front-end (`assets/js/app.js`)

Plain JS, no framework. `init()` wires: `initSkillSelect`/`initTradeSkills`
(trade→skill cascade), `initCascades` (dependent lookups), `initActivity`
(activity-by-SBU), `initForwardCredit` (credit required when forwarding),
`initSubconVendor`, `initRegAutofill` (GSTIN→PAN/state), `initDisplayName`,
`initOtherNew` ("Other → add new"), `initCustomFreq` (custom report frequency),
`initClientInspection`, `initCallLinks` (deputation site / PO / line pickers),
`initQuickAdd` (inline add client/vendor/office/product/activity), plus
`enhanceSelect` for searchable `<select class="searchable">`. Theme colour is a
single `--brand` CSS variable set by `theme_style_tag()`, with luminance-based
legible text; the logo is an inline data URI.

---

## 11. Email & scheduled jobs

- All mail goes through `ops_mail()` and is recorded in `email_log` (attachments
  supported — the quotation PDF is sent this way). Sending is via PHP `mail()`;
  an optional Office365 SMTP path can be configured. Each Call/Job also has an
  **Open in Outlook** (mailto) button.

**`cron.php` — run it once daily** (cPanel Cron; steps in
`README-MilesWeb-PHP.md`). It performs, in order:

| Task | Function | What it does |
|---|---|---|
| Report reminders | `ops_run_reminders()` | Chases inspectors for due/overdue reports per the job's reporting frequency |
| **Report escalation** | (same) | E-mails the inspector's **reporting manager** when a report is overdue past the threshold (default 3 days, throttled weekly) |
| Certificate expiry | `ops_run_cert_reminders()` | Inspector certificates within 30 days of expiry |
| PO near-completion | `ops_run_po_alerts()` | Fires at ~85% of a PO line consumed |
| Placement fees | `confirm_lapsed_placement_fees()` | Flips provisional → confirmed once the guarantee lapses |
| Quote follow-ups | `crm_run_followups()` | 3/6/9-day, fortnight, month chase e-mails on sent quotes |
| **IDEMS approval SLA** | `idems_run_sla_escalations()` | E-mails managers when a report approval is past its SLA |
| **MIS digest** | `ops_run_mis_digest()` | Weekly (Mondays) + monthly (1st) ops/sales/money summary to leadership |

Everything is idempotent and guarded, so running cron more than once a day is
harmless.

---

## 12. Local development & testing (no MySQL needed)

Use SQLite so nothing external is required:

```bash
cd phpapp
DB_DRIVER=sqlite php -S 127.0.0.1:8000 index.php
# open http://127.0.0.1:8000  (admin / admin12345)
```

- A throwaway `data.sqlite` is created on first request. Delete it to reset.
- Lint before committing: `php -l lib/ops.php` (and any changed file).
- The bootstrap probe means you never run a migration by hand — just load a page.
- **Prod is always MySQL**; SQLite is dev-only. Both are driven by the same
  `pk_clause()` / `ensure_column()` so schema stays identical.

---

## 12a. IDEMS — Inspection Documentation, Reporting & Endorsement (`lib/idems.php`)

The TPIA industry pack. A TPIA does two things — **issues its own reports**, and
**endorses documents produced by manufacturers**. IDEMS covers both in one place.
Built in 13 phases; all are shipped.

### The report lifecycle

```
Call / Job  ──prefill──▶  New report (auto IRN)
                              │
                              ▼
                     Fill the designed form
        (prefilled · suggestions · photos+GPS · signature · autosave)
                              │
                              ▼
        Submit ──▶ Approval chain (multi-level, SLA, delegate)
                              │
                              ▼
            Finalize & issue  ──▶  immutable, signatures frozen
                              │
                   ┌──────────┴──────────┐
                   ▼                     ▼
             Report PDF          Client-format .docx
                                         │
                                         ▼
                              auto-drafted Release Note
```

### Key concepts

**1. Report types** — 32 standard TPIA types seeded (Inspection Report, Stage/
Final, NCR, Deviation, Release Note, certificates, Surveillance, Expediting,
Vendor/Factory Assessment, timesheets, summaries…). Admin adds unlimited more
with no coding.

**2. IRN (Inspection Reference Number)** — designed with tokens, e.g.
`{COMPANY}/{BRANCH}/{YEAR}/{CLIENT}/{TYPE}/{SERIAL}` → `MGH/AHM/2026/RIL/IR/000458`.
The running serial is scoped by everything before it; a **unique index plus
atomic scope counters guarantee no duplicate can exist**. Configured at
`/irn-rules` — no code.

**3. No-code form builder** (`/report-builder`) — sections and 18 field types:
text, paragraph, number, date, time, single/multi dropdown, yes-no, choice
buttons, **calculated**, heading, note, **repeatable table**, photo, file, GPS,
signature, QR/barcode. Per field: mandatory, hidden, width, options (list or
`lookup:<master>`), **conditional show-if**, **formulas**, table columns.

**4. Form from the client's format** (`/report-form-from-template`) — upload the
client's Word file with `{{tokens}}` in it and the system **builds the matching
form**: labels are read from the wording next to each token, field types are
inferred, `{{field.column}}` tokens become a repeatable table, standard header
tokens are skipped, and you confirm an editable plan before anything is created.

**5. Prefill from the call/job** — creating a report from a call or job carries
across client, vendor, SBU, office, product, PO + line item, site address,
inspector, dates, BOSS number and contract. An **alias map** then aligns the
*designed* fields even when the client uses their own wording (`Customer`,
`Supplier`, `Works location`, `Inspected by`, `Call No`, `Division`…). Only empty
fields are filled; auto-filled ones are badged `auto`.

**6. Approvals** — `/approver-map` sets each inspector's approver (common
approver supported, plus a **temporary approver with a date window** for leave).
`/idems-approval-rules` designs multi-level chains matched by report type /
office / client / SBU, each level with an SLA. Submission is blocked if no
approver resolves; approve / reject / send-back (remarks mandatory) / delegate;
overdue approvals escalate from cron.

**7. Signatures & timestamps** — inspectors and users store a signature once
(`/my-signature`, or the inspector master). It is added automatically on
approval and **frozen onto the report at issue**. System dates are locked;
**only a Branch Application Manager** may change one, with a mandatory reason,
and the change is written to the immutable audit log.

**8. Manufacturer endorsement** (`/endorsements`) — upload an MTC, NDT, hydro,
dimensional, painting, PWHT, hardness, FAT, calibration, welding or heat-treatment
record. **The original is stored unaltered.** The approver reviews it and
Endorses / Endorses-with-comments / Rejects; a **separate endorsement certificate
PDF** is produced that references the original.

**9. Writing assistant (works with no AI)** — inspectors type shorthand
("dimension ok", "minor dent") and get correct engineering language from an
editable **phrase library** (35 seeded across observation / acceptance /
rejection / conclusion / recommendation / hold / witness / deviation). Unmatched
text is still tidied (domain spell-check, capitalisation, punctuation) without
changing meaning. Available standalone (`/writing-assistant`) and as an
**Improve wording** button on every paragraph field.

**10. Smart remarks & Release Note** — the system reads the report's own findings,
detects adverse signals, and drafts summary / observations / deviations / hold &
witness points / conclusion / acceptance / recommendations, and proposes the
result and release status. From an approved report, one click **drafts a Release
Note** consistent with those findings, linked both ways and duplicate-guarded.

**11. AI-assisted review** (`/document-review`) — upload the PO, QAP, drawings,
specs, MTCs and certificates. **Rule-based checks always run without AI**:
missing documents, traceability gaps, drawing **revision mismatch**, **expired
calibration**. With an AI key configured it adds a deeper review (conflicts,
suggested hold/witness points, draft remarks). **The inspector is always the
approving authority** — nothing is applied automatically.

**12. Evidence** — photos are compressed in the browser *and* on the server
(~80–90% smaller), tagged with capture time and GPS, **de-duplicated by content
hash**, and shown in a gallery organised by section with captions.

**13. Audit & compliance** (`/audit-log`) — every action (including logins and
failed logins) with user, role, office, IP and timestamp. **Records are immutable
and never purged; deletes are soft.** Automated compliance checks (inspectors
with no approver, reports stuck in review, timestamp changes, users without a
signature, failed-login spikes), activity charts, full filters and CSV export.

**14. Self-learning** (`/learning`) — wording from **approved/issued** reports is
harvested and offered back as "Used before ×N" chips on the next report of that
type (client-specific first). Entries can be promoted into the standard phrase
library or muted. **Suggestions only — technical conclusions are never altered.**

**15. Offline field use** — installable PWA (`manifest.php`, `sw.js`): screens
opened once work without signal, the fill form **autosaves drafts locally**, and
entries submitted offline are **queued and synced on reconnect**. Field-mode CSS
gives large touch targets and 16px inputs (no iOS zoom).

---

## 13. How to add a feature (the recipe)

1. **Schema**: add a `CREATE TABLE IF NOT EXISTS` in `ops_ensure_schema()` (or
   `ensure_column()` in `ops_migrate()` for a new column). Add one probe line in
   `index.php`'s bootstrap block so uploads auto-upgrade.
2. **Constants**: choice lists near the top of `lib/ops.php` (and expose as a
   lookup via `lk_ensure_type_map` if it should be user-editable).
3. **Route**: add to `ops_dispatch()` and a handler function.
4. **View(s)**: add under `views/ops/`, follow the `crumbs` + `master-head` +
   `panel` + `grid` patterns already in use.
5. **Nav**: add a link in `views/layout_top.php` (gate with `can()` /
   `is_coordinator_level()`).
6. **Test** with the SQLite server, **lint**, update `PENDING.md`, commit, push.

---

## 14. Git / branches

- Active development branch: **`claude/quotation-management-workflow-5dokb2`**.
- Commit per logical batch; push with `git push -u origin <branch>`.
- Do **not** create a PR unless explicitly asked.
- `docs/ROADMAP.md` holds the longer-term plan (licensing, per-user billing).

---

## 15. Repository layout

This branch contains **only the live application**:

| Path | What it is |
|---|---|
| **`phpapp/`** | **The live app — PHP 8 + MySQL. All work happens here.** |
| `README.md` | This architecture reference |
| `.gitignore` | Repo ignore rules |

The earlier **Django** prototype (`accounts/ masters/ operations/ dashboard/
config/ templates/ static/ partners/`), the **Node** prototype (`nodeapp/`),
`docs/`, and their Docker/Caddy/render deployment files have been **removed from
this branch** — they were superseded by `phpapp/` and are still available in the
git history if ever needed.
