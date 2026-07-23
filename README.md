# SGS Ahmedabad — Inspection Management System

**This file is the single source of truth for the architecture.** It is kept at
the repo root so it is present on every branch. If you (or another Claude
session) pick this up on a new branch, read this first — it explains what the
live app is, how it is built, every table, every route, and how to run and
extend it.

> ⚠️ **The live application is `phpapp/` — plain PHP 8 + MySQL.**
> It runs on **MilesWeb shared hosting** by simply uploading files (no build
> step, no Node, no Python, no Composer). The `accounts/`, `masters/`,
> `operations/`, `dashboard/` (Django) and `nodeapp/` (Node) folders are **older
> prototypes** kept for reference only. **Do not build features there.** All new
> work goes in `phpapp/`.

---

## 1. What the system does

An operations & finance system for a third-party inspection (TPI) services
network — HQ **Ahmedabad** plus affiliate branch offices (IBOs). It replaces a
planned Microsoft SharePoint/Power Apps/Power Automate/Power BI build with a
self-hosted PHP app that needs **no Microsoft licenses**.

Covered today:

- **Clients / Vendors** master (business partners) with contacts, addresses,
  registrations (GSTIN/PAN/TAN), contracts, purchase orders + line items.
- **Calls** (inspection calls received) → forwarding to an executing branch with
  mandatory credit → **Jobs** (allocation to an inspector/sub-con).
- **Job closure**, reports, deliverables, TAT tracking, **expenses**.
- **Sub-contractors**, rate matrix, **attendance**, holidays, comp-off.
- **Inter-office credit** logic + reconciliation.
- **Dashboards**: Operations, Financial, Utilization, People — scoped by role.
- **Invoicing & payment** per job + dashboard reconciliation.
- **Inspector master**: names, trade→skill, multi-SBU, certifications w/ expiry.
- **Back-office staff** with CTC / allowances (salary-gated).
- **Hiring / CV pipeline** (deputation resourcing): CV → shortlist → hold /
  reject / accept(=hired → inspector).
- Fully **configurable master lists** (incl. dependent/hierarchical) and
  **custom fields** on any form — a non-coder can add dropdowns without code.
- **Settings**: app name, logo upload, theme colour, financial-year start, TAT
  threshold.

See **`phpapp/PENDING.md`** for the living list of parked / deferred items.

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
| Scheduled jobs | `phpapp/cron.php` via cPanel Cron | Reminders, cert & PO alerts |

**No build tools.** There is nothing to compile. Editing a `.php` file and
re-uploading it is the entire deploy.

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
3. `require`s the 5 library files (`lib/db.php`, `helpers.php`, `ops.php`,
   `lookups.php`, `access.php`).
4. **Bootstrap probe**: runs a few tiny `SELECT`s against the *newest* columns
   (e.g. `SELECT stage FROM candidates LIMIT 1`). If any fails (fresh install or
   a not-yet-migrated upload) it calls **`boot()`**, which runs every idempotent
   migration + seed in one pass. This is why one uploaded file can upgrade the
   whole schema. **When you add a new table/column, add one probe line here.**
5. Parses the route from the URL path (single segment; ids/tabs via query
   string), requires login for everything except `/login`.
6. Dispatches: partner/PO routes are handled inline in `index.php`; everything
   operational is handled by **`ops_dispatch($route, $method)`** in `lib/ops.php`.
   Returns `view('notfound')` (404) if nothing matches.

### `boot()` (in `lib/db.php`)
```
migrate()           // core tables (users, business_partners, partner_*, lookups, custom_*, settings)
ops_migrate()       // ops tables + ALL ensure_column() upgrades  (calls ops_ensure_schema + ops_seed)
lk_migrate()/lk_seed()   // lookup type/value seeding
access_migrate()    // user scope columns, settings schema, one-time demo cleanup
ensure_admin()      // create the admin user from config
auto_seed()         // any first-run demo content
```

**Migrations are idempotent.** Helper `ensure_column($table,$col,$def)` adds a
column only if missing. `CREATE TABLE IF NOT EXISTS` for new tables. You never
edit existing data in a migration — only add.

---

## 5. File map (`phpapp/`)

```
index.php                 Front controller: bootstrap, router, partner + PO routes
config.php                DB credentials + admin login (edit on the server)
cron.php                  Reminder runner (call from cPanel Cron)
.htaccess                 Pretty-URL rewrite to index.php

lib/db.php        (163)   PDO connection, base schema, boot(), migrate(), admin/seed, pk_clause()
lib/helpers.php   (88)    e(), flash(), redirect(), user_name(), small utilities
lib/ops.php       (1600+) THE ops engine: constants, schema, migrations, roles, masters,
                          calls, jobs, candidates(hiring), inspectors, reports, users, settings,
                          dispatcher, quick-add + AJAX endpoints, emails, reminders
lib/lookups.php   (418)   Configurable lookup lists (types+values, hierarchy), custom fields,
                          trade→skill data, admin CRUD (lk_admin), seeding
lib/access.php    (210)   Roles (ORG_ROLES), permissions (PERMISSIONS), can(), scope_clause(),
                          ua(), settings get/set, theme/logo, FY helpers, access_migrate()

views/
  layout_top.php / layout_bottom.php   Page shell + nav
  login.php dashboard.php list.php form.php detail.php po_detail.php notfound.php
  ops/  calls.php call_form.php call_detail.php
        jobs.php job_form.php job_detail.php job_close.php my_jobs.php
        candidate_list.php candidate_form.php candidate_detail.php   (hiring pipeline)
        inspector_list.php inspector_form.php
        masters.php master_list.php master_form.php
        lookups.php lookup_values.php custom_fields.php
        reports.php settings.php users.php user_form.php change_password.php

assets/css/app.css        All styling (theme via --brand CSS var)
assets/js/app.js          Progressive enhancement (see §10)

PENDING.md                Living list of deferred / parked work (READ THIS)
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
- `offices` — code, name, city, is_ahmedabad, `coordinator_name/email`,
  `manager_name/email` (forwarding & notifications).
- `calls` — call_code, client_id, vendor_id, ibo_office_id,
  `executing_office_id`, region, sbu, `activity_id`, `inspection_type`,
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
- `settings` — skey (PK), svalue (MEDIUMTEXT — holds base64 logo).

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
| `/lookups` `/lookup` `/custom-fields` | Configure lists & custom fields |
| `/reports` | Dashboards (4 families, filter bar) |
| `/users` `/user-new` `/user-edit` | User & access admin |
| `/settings` | App name, logo, theme, FY start, TAT threshold |
| `/quick-add` `/partner-meta` `/partner-sites` `/partner-pos` `/po-lines` | AJAX (JSON) |

---

## 8. Configuration engines (the "agile" part)

**Lookup lists** (`lib/lookups.php`) — every dropdown is data, not code.
- `lookup_types` + `lookup_values`; a type can have a `parent_type_id` and a
  value a `parent_value_id` → **dependent / cascading** lists (e.g. SBU→Activity,
  Trade→Skill, Product→Wax→Tier).
- `lk_options_or($key, $CONST)` returns the configured values, **falling back to
  a PHP constant** if the list is empty — so the app works before seeding and
  after a list is emptied.
- Seeded types: `deputation_type`, `inspection_type`, `deliverable`,
  `expense_heading`, `industry`, `department`, `client_type`, `designation`,
  plus `sbu`, `region`, `activity`, `product`, `trade`, `skill`.
- Super-admin can add/edit/delete **any** list (including built-ins) from
  `/lookups`.

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
FINANCE, INSPECTOR, ADMIN (legacy). `MGMT_ROLES` = the admin-level subset.

**Permissions** (`PERMISSIONS`): `dash.operations`, `dash.financial`,
`dash.utilization`, `dash.people`, `data.credit`, `data.salary`,
`ops.call.create`, `ops.job.allocate`, `ops.job.close`, `ops.call.delete`,
`master.manage`, `finance.reconcile`, `users.manage.branch`,
`users.manage.global`, `settings.manage`. `role_defaults($role)` sets sensible
defaults (e.g. COORDINATOR sees financial dash + credit but **not salary**;
BRANCH_APP_MANAGER can delete calls). Per-user overrides are stored on the user.

- **SCOPE** (dial 1): which offices/SBUs a user sees — enforced in SQL via
  `scope_clause()`. Business Director = all; SBU Head = their SBU across offices;
  Branch Manager = their branch; etc.
- **PERMISSIONS** (dial 2): what a user can do/see — `can()`. Salary/loaded cost
  is gated by `data.salary` (`can_see_salary()`); credit/revenue by `data.credit`.

Helper level checks: `is_master()`, `is_admin_level()` (MGMT_ROLES),
`is_coordinator_level()` (admin ∪ ASST_MANAGER ∪ COORDINATOR), `is_inspector()`.

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

- All mail goes through `ops_mail()` and is recorded in `email_log`. Sending is
  via PHP `mail()`; an optional Office365 SMTP path can be configured. Each
  Call/Job also has an **Open in Outlook** (mailto) button.
- `cron.php` → `ops_run_reminders()` → certificate-expiry reminders + PO
  near-completion alerts (`ops_run_po_alerts`, fires ~85% consumed). Wire it to
  cPanel Cron (steps in `README-MilesWeb-PHP.md`).

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

## 15. Legacy prototypes (reference only — do not extend)

| Folder | Stack | Status |
|---|---|---|
| `accounts/ masters/ operations/ dashboard/ config/ templates/` | Django + SQLite/Postgres | Superseded prototype |
| `nodeapp/` | Node/Express | Superseded prototype |
| **`phpapp/`** | **PHP 8 + MySQL** | **✅ Live app — all work here** |

The Docker/Caddy/render.yaml files at the root belong to the Django prototype.
They are not used by the PHP app, which deploys by file upload to MilesWeb.
