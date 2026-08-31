# DEMO-S03 — Client & Client-Portal foundation

The client-side foundation that later scenario prompts (04–12) reuse: **6 client
organisations**, their branches, locations, departments, contacts, portal users
(five role types + lifecycle states) and a spread of requirements — all on the
**one existing client master** (`business_partners`) and the existing portal/CRM
tables. No duplicate client database: every client is simultaneously an
operational client (CRM/ops) and a marketplace client (one master record).

## Load / remove

Admin → System Settings → **"DEMO-S03 — client & client-portal foundation"** → Load.
Or CLI:
```
php tools/seed-scenario-s03.php            # load / refresh (idempotent)
php tools/seed-scenario-s03.php --status
php tools/seed-scenario-s03.php --remove
```
Password everywhere: `demo12345`. Prints a real PASS/FAIL dashboard; re-running
purges DEMO-S03 first (no duplicates).

## The 6 clients (each a single master, marketplace-enabled)

| Code | Client | Type / industry |
|---|---|---|
| DEMO-S03-CLIENT-01 | EXAACT Demo EPC Projects India Pvt Ltd | EPC · Oil & Gas (5 locations, 2 branches) |
| DEMO-S03-CLIENT-02 | EXAACT Demo Energy & Refinery Ltd | Asset owner · Refinery |
| DEMO-S03-CLIENT-03 | EXAACT Demo Heavy Engineering Ltd | Manufacturer · Fabrication |
| DEMO-S03-CLIENT-04 | EXAACT Demo Power Transmission Solutions Ltd | Power / Transmission *(key for Prompt 04)* |
| DEMO-S03-CLIENT-05 | EXAACT Demo Renewable Projects Ltd | Solar & Wind |
| DEMO-S03-CLIENT-06 | EXAACT Demo Industrial Services | Small business |

## Portal logins (all `/portal/login`, password `demo12345`)

Five role types via the existing portal presets/permissions:

| Type | Example login | Access |
|---|---|---|
| Client Admin (FULL) | `epc.admin.s03@demo.test` | manage users, all projects/requirements/reports/commercial |
| Technical Manager (QUALITY + market.post) | `epc.tech.s03@demo.test`, `power.tech.s03@demo.test` | create requirements, search pool, review reports |
| Project Manager (FULL + deputation) | `epc.project.s03@demo.test`, `power.project.s03@demo.test` | requirements, schedules, deployments |
| Commercial (COMMERCIAL) | `epc.commercial.s03@demo.test` | invoices, vouchers |
| Site User (READONLY, restricted) | `epc.site.s03@demo.test` | deputation acknowledgements only |

Lifecycle states seeded: **Invited** (`epc.invited.s03@demo.test`, `power.invited.s03@demo.test`),
**Active** (most), **Suspended** (`energy.suspended.s03@demo.test` — login denied, history retained).

## Role-based portal dashboards (live-computed, never hard-coded)

Each portal user sees a dashboard for their role, every tile computed **live** from
that client's own data via `connect_client_dash()` (`lib/connect_client_dash.php`),
strictly scoped to the signed-in party so no client sees another's figures. The
render is wired into `views/portal/dashboard.php` and `views/portal/hiring.php`.
The showcase client **EPC (C1)** is seeded so its three role dashboards render these
exact values:

| Technical Manager (`epc.tech.s03`) | Project Manager (`epc.project.s03`) | Commercial (`epc.commercial.s03`) |
|---|---|---|
| Open Requirements **4** | Active Projects **3** | Pending Commercial Review **3** |
| Matching in Progress **2** | Resources Deployed **18** | Draft Engagements **2** |
| Shortlisted **2** | Upcoming Deployments **6** | Pending Client Approval **4** |
| Resource Requests **3** | Pending Mobilizations **4** | Invoices Awaiting Review **3** |
| Pending Technical Reviews **2** | Schedule Conflicts **2** | Approved Invoice Value **₹12,50,000** |
| Expiring Credentials **4** | Pending Site Actions **3** | |

The dashboard asserts each value from the seeded records (`cx_requirements`,
`cx_applications`, `cx_pro_certs`, `jobs`/`calls`/`job_visits`, `dep_att_approval`,
`cx_engagements`, `billable_events`, `report_docs`) — change the data and the tiles
change. Money is rendered in Indian lakh grouping (`connect_inr_group`).

Contacts are **named** people (e.g. Rajesh Sharma — Technical Manager, Quality
Assurance), each with designation + department, and every major client carries an
**activity timeline** (requirement created → searched → shortlisted → invited →
reviewed → engaged → report) rendered under its dashboard.

## Requirements seeded (reused by Prompt 04 matching)

5 active (OPEN) · 2 draft · 2 completed (CLOSED) · 1 cancelled:

| Client | Requirement | Status |
|---|---|---|
| Power (C4) | Transmission Line & Substation Testing Technician (×3) | OPEN |
| Heavy Eng (C3) | Welding Inspector (CSWIP) — Pressure Vessels (×2) | OPEN |
| Energy (C2) | NDT UT Level II — Oil & Gas (×2) | OPEN |
| EPC (C1) | QA/QC Mechanical Engineer (×4) | OPEN |
| Renewable (C5) | Electrical Commissioning Engineer (×2) | OPEN |
| EPC / Power | Piping Inspector · Protection Engineer | DRAFT |
| EPC / Energy | Coating Inspector · Turnaround Inspector | CLOSED |
| Heavy Eng | Dimensional Inspector | CANCELLED |

## Acceptance test — 12/12 PASS

Verified against the database, not asserted: 6 client orgs (each also a
`cx_organisations`), branch hierarchy (`parent_id`), ≥12 locations, ≥20 distinct
departments, ≥30 **named** contacts, ≥20 portal users, all 5 role presets present, the
invited/active/suspended lifecycle, the 5/2/2/1 requirement status spread,
multi-client isolation (requirements scoped by `poster_party_id`), activity timeline
present, and — computed through the same `connect_client_dash()` the portal renders —
the Technical (**4/2/2/3/2/4**), Project (**3/18/6/4/2/3**) and Commercial
(**3/2/4/3 + ₹12,50,000**) role dashboards all landing on their exact target values.

## Reuse contract for later prompts

- **Prompt 04 (matching)** searches these OPEN requirements (transmission, welding,
  NDT, QA/QC, commissioning) against the professional pool.
- **Prompts 06–10 (ops → billing)** convert any of these to jobs via the existing
  award → deploy bridge; the client master is the same record throughout.
- Multi-client isolation is structural (portal scopes by `portal_partner_id`), so
  no client sees another's data.

## Existing-system impact

No existing workflow broken; no duplicate client master or portal created. Uses
`business_partners` (+ `parent_id` branches, `client_type`, `industry`),
`cx_organisations`, `partner_addresses`, `partner_contacts` (with `department`),
`client_users` (`role_preset` / `perms` / `invite_token` / `must_change`),
`cx_requirements`, `cx_applications`, `cx_pro_certs`, `jobs`/`calls`/`job_visits`,
`dep_att_approval`, `cx_engagements`, `billable_events`, `report_docs` and
`activities` — all pre-existing. The only new file is `lib/connect_client_dash.php`
(additive render engine); no route, permission or lifecycle was changed. Every
DEMO-S03 record is namespaced and removed by `--remove`. App test suite unchanged at
5213 passing.
