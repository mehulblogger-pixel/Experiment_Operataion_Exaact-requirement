# PROMPT 1 — Master Application Discovery & Screen/Function Inventory

> **Run this first. Once.** Its output — the *Master Application Screen & Function
> Inventory* — is then reviewed, corrected against `master-inventory-baseline.md`,
> and **locked as v1.0**. Every later prompt cites that version.

---

## Paste this to the AI developer/tester

You are the lead QA architect for **Inspection Ops**, a multi-tenant
**Third-Party Inspection Agency (TPIA)** operations platform (PHP, front-controller
`index.php`, module handlers in `lib/`, views in `views/`, aligned to **ISO/IEC
17020**). Your job in this step is **discovery only** — do not test yet, do not fix
anything.

Produce a **complete, exhaustive Master Application Screen & Function Inventory** by
reading the codebase, not by guessing. Work from the real sources of truth:

- **Routes/screens:** the dispatch tables and route cases (e.g. the `$route === …`
  cases and the module map in `lib/ops.php`), plus `index.php` top-level routes, the
  **portal** routes (`lib/portal.php`) and the **vendor portal** (`lib/cvp.php`).
- **Navigation:** the area/menu builder (`lib/areas.php`) and the nav index — these
  name the modules a user actually sees (Sales, Quality, Reporting, Money, Insights,
  Directory, Admin, Operations).
- **Modules & access:** `ACCESS_MODULES` and `PERMISSIONS` and the roles
  (`ORG_ROLES`) in `lib/access.php`.
- **Screens' contents:** the view files under `views/` (fields, buttons, tabs,
  panels, empty states) and the handlers that render them.
- **Data model:** the `CREATE TABLE` / `ensure_column` statements (tables, columns,
  keys) across `lib/*.php`.
- **Lifecycles:** status constants and transition logic (e.g. report statuses,
  approval/vetting states, quote statuses, NCR/CAPA states).

### Output — one Word (.docx) document titled *"Inspection Ops — Master Screen & Function Inventory v1.0"*

Cover page: app name, repository/branch, build/commit hash, environment, date,
author, and a revision-history table.

Then the inventory, organised **Area → Module → Sub-module → Screen**, and for each
**Screen** a table of:

| Screen | Route/URL | Purpose (1 line) | Primary role(s) | Key sections/tabs | Fields (name · type · req?) | Buttons/actions (→ what) | Statuses touched | Reads from → Writes to (tables) | Cross-module links in/out | Settings that affect it |
|---|---|---|---|---|---|---|---|---|---|---|---|

Follow the screen tables with these **consolidated registers** (each its own table):

1. **Module register** — every module, its owning area, its route family, its DB
   tables, and the roles that can view/edit it.
2. **Role & permission matrix** — every role (Master Admin, Business Director, SBU
   Head, Branch Manager, Branch Application Manager, Operation Manager, Asst.
   Manager, Coordinator, Business Dev / Key Accounts / Marketing roles, Finance,
   **Senior Inspector**, **Inspector**, Admin-legacy, plus **client-portal** and
   **vendor-portal** external roles) × every module → view / edit / act / none.
   Include **data scope** (office/branch, business-unit) per role.
3. **Status/lifecycle register** — for every stateful entity (inspection report,
   approval step, vetting, quotation, order/contract, call, job, NCR, CAPA action,
   complaint, deputation, candidate, invoice, licence): the states, the legal
   transitions, and what triggers each.
4. **Settings register** — every configurable setting/toggle and the behaviour it
   changes (numbering rules, terminology, SLA targets, AI on/off, vetting gate,
   vetting checklist, pre-order checklist, client-acceptance-required, seat prices,
   report-blank-fill, etc.).
5. **Masters/lookup register** — every master list and dependent (cascading) list,
   and which forms it feeds.
6. **Output register** — every generated artefact (system PDF, company Word/.docx,
   Release Note, certificates, QR verify page, register exports, MIS/analytics
   exports, email templates) and where it is produced.
7. **Cross-cutting subsystem register** — the platform capabilities that are not a
   single screen but cut across the app: **numbering/IRN engine**, **audit trail &
   tamper-evident hash chain**, **automatic signatures**, **controlled timestamps**,
   **geo-tagged / EXIF evidence integrity**, **terminology/vocabulary engine**,
   **multi-tenant seat/module licensing**, **offline/mobile PWA**, **AI-assist
   (extract/polish/QA)**, **notifications/email**, **service-scope engine**,
   **hold/witness points**, **KPI/analytics (TAPI) engine**.

### Rules

- **Completeness over brevity.** If a screen, field, button, status, table or
  setting exists in the code, it must appear. A missing item is itself a defect to
  be reported later.
- **No invention.** Every row must be traceable to a file/route/table. Where you are
  unsure, mark it **`⚠ to confirm`** rather than guessing.
- **Assign stable IDs now.** Give every Module a code (`MOD-CALL`, `MOD-IDEMS`, …),
  every Screen an ID (`SCR-CALL-REGISTER`, …), every Status a code. Prompts 2–5
  reuse these IDs. This is the top of the traceability chain.
- **Flag ambiguity and duplication.** If two screens/lists look like duplicates, or
  a term is used inconsistently, list it under *Discovery Observations* at the end.
- **End with a coverage self-check:** total counts (areas, modules, screens, fields,
  buttons, statuses, tables, settings, outputs) and an explicit statement of any
  area you could not fully read and why.

Do **not** proceed to testing. Output only the inventory document.

---

## After it returns

1. Open `master-inventory-baseline.md` and reconcile — anything in the baseline the
   AI did not find is a **discovery gap** (record it; it may be a real coverage
   risk).
2. Correct, add IDs where missing, and **stamp it `v1.0`** with today's date.
3. From here on, **the locked inventory is the single source of truth.** Do not let
   later prompts invent modules or screens that are not in it; if the code changes,
   bump the inventory version first.
