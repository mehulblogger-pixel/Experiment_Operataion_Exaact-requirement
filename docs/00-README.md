# Role & Flow Documentation — Exaact Inspection & Operations Management System

This folder documents **who each role is, what they can see and do, and the exact
path they walk through the app** — extracted from the code, not invented. Every
claim about a permission carries a `file:line` reference so you can verify it.

> **Read order:** `01-roles.md` → `02-permission-matrix.md` →
> `03-object-lifecycles.md` → the relevant `04-flows/<role>.md` →
> `99-gaps-and-risks.md`.

All file references are relative to the `phpapp/` folder unless stated otherwise.

## What the app is (in one paragraph)

Exaact ("Inspection Ops") is a multi-tenant operations platform for a **Third-Party
Inspection Agency (TPIA)** — a firm whose engineers inspect goods and works at
vendor sites on behalf of clients, and who also place inspectors on longer
**deputation** postings (man-day or man-month). It runs the whole commercial-to-cash
thread: **Lead → Opportunity → Quotation → (accept) → Contract registered by
Accounts → floated to Operations → Inspection Call raised from the contract → Job
allocated to an inspector → inspection done, report written & issued → job closed
with expenses → GST invoice raised → payment recorded.** One contract carries many
calls until the client goes inactive. Around this spine sit an ISO/IEC 17020
accreditation layer, a Money module, costing/profitability, recruitment, a client
portal, a vendor portal, and analytics.

## Who uses it

- **Desk staff** on laptops: sales, accounts/finance, coordinators, managers, quality.
- **Field inspectors on phones**, often on patchy networks (the app ships a service
  worker and an offline queue). Design for phone-first inspectors and desk-first
  everyone-else; never average the two.
- **Clients** and **vendors** get separate, limited portals (their own permission
  systems — see the glossary).

## Glossary (defined once, used everywhere)

| Term | Plain meaning |
|---|---|
| **Permission (`can('x')`)** | A named right, e.g. `ops.job.close`. The whole app asks `can('x')` before letting someone act. Defined in `lib/access.php`. |
| **Module** | A screen-area, e.g. `jobs`, `quotes`. Each module has a **view** right (`mod.jobs.view`) and an **edit** right (`mod.jobs.edit`); edit implies view (`access.php:372`). |
| **Role** | A job type, e.g. `COORDINATOR`. A role has a **default** set of permissions and modules (`access.php` `role_defaults_base()` + `module_defaults()`). |
| **Per-user override** | A login can be given a **custom** permission list that **replaces** its role defaults entirely (`access.php:489-495`). This is powerful and a common footgun — see `99-gaps-and-risks.md`. |
| **Master / Super Admin** | A login with `is_superuser = 1`. It is resolved to role `MASTER_ADMIN` and **bypasses every permission gate** (`access.php:530`, `487`). The installation's root key. |
| **`is_admin_level()`** | True for the management roles in `MGMT_ROLES` (`access.php:27`, `ops.php:547`). |
| **`is_coordinator_level()`** | `is_admin_level()` **plus** `ASST_MANAGER` and `COORDINATOR` (`ops.php:548`). Gates most day-to-day operations actions. |
| **Office / branch scope** | `'ALL'` (every office) or `'OWN'` (the person's home office only) — `access.php` `ua()` `:499-504`. |
| **SBU / Business Unit** | A reporting/segment code on records (`data.*` figures roll up by it). |
| **Module gate** | `ops_module_gate()` (`ops.php:2226`) maps a route to a module and blocks it unless the viewer holds `mod.<module>.view`; unmapped routes pass through. |
| **Accreditation pack** | An installation switch (`accredited_pack_on()`). ISO-17020 registers (NCR, CAPA, audits, equipment, competence, impartiality, data control) are hidden when it is off, even for a master. |
| **Call** | An inspection/deputation work order raised from a contract. |
| **Job** | One allocation of a call to an inspector (a call can spawn several jobs). |
| **Voucher** | An inspector's monthly expense + time claim; it is also the timesheet the cost run reads. |
| **IDEMS** | The inspection-report engine (report types, IRN numbering, fill, issue, vendor qualification, expediting). |
| **Client portal / Vendor portal** | Separate logins with their **own** permission systems (`pcan()` for clients, `vendor_users` for vendors) — **not** in `ORG_ROLES`. Summarised in `01-roles.md`. |
| **`⚠ implicit`** | A permission that exists only because *no check blocks it*, rather than by an explicit grant. Listed in `99-gaps-and-risks.md`. |

## Ground rules this documentation follows

- Plain business language; technical terms defined here once.
- Mermaid for every diagram (renders in GitHub and VS Code, stays diffable).
- Every permission claim carries a `file:line`.
- The docs describe the **built-in role defaults**. Per-user CSV overrides and the
  Settings → Roles & access editor can change any of it at runtime.
