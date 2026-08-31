# EXAACT — MASTER REVAMP IMPLEMENTATION PROMPT

**The single consolidating instruction for transforming EXAACT into an Integrated
Technical Operations Platform & Marketplace.**

> Read this first, in full, before touching code. It is the capstone over the
> existing programs in `docs/revamp/`, `docs/connect/`, `docs/phase-2/`,
> `docs/phase-3/` and the governing rules in `CLAUDE.md`. It does not replace those
> documents — it tells you how to execute against them as one connected effort.

---

## PREAMBLE — What you are actually doing

You are performing a **controlled architectural transformation of an existing, live
application**. You must first understand the complete existing architecture and
preserve every working capability. **Your objective is not to rebuild the
application.** It is to *consolidate, connect, simplify and extend* EXAACT into a
focused **Integrated Technical Operations Platform and Marketplace**.

Treat the existing **Operations System as the backbone**. The **Marketplace is an
intelligent acquisition and resource-discovery layer that feeds that backbone** — not
a separate product, not a parallel database, not a second money truth. Every new
capability must terminate in the operational spine that already exists.

If at any point a task seems to require breaking, replacing, or duplicating something
that already works — **stop and re-read this document.** The correct move is almost
always EXTEND or CONNECT, never REBUILD.

---

## SECTION A — Mission & Product Definition

### A.1 What EXAACT is

**The Operating System for Technical Services, Inspection and Project Workforce
Execution.** Its defensible moat is the *connected lifecycle* that very few systems
join end to end:

> Technical competence → real-world assignment → field execution → verified outcome →
> commercial event → future matching intelligence.

### A.2 The one lifecycle (the product, stated as a sentence)

```
Requirement → Technical Resource Discovery → Competence Verification → Matching →
Selection → Mobilization → Scheduling → Field Execution → Inspection/Service Delivery →
Reporting → Technical Approval → Client Acceptance → Billing → Revenue →
Quality → Historical Competence & Performance Record → Improved Future Matching
```

### A.3 What EXAACT is NOT

EXAACT is **not** "ATS + HRMS + Inspection Software + Marketplace + ERP" bolted
together. That positioning is too broad and confusing and is explicitly rejected.

- Not "better than Zoho Recruit."
- Not "another HRMS."
- Not "just inspection software."
- Not a generic job board disconnected from operations.
- Not a generic ERP/LMS/BIM/payroll calculator (orchestrate those, never absorb them).

### A.4 The architectural principle above all others

**One technical person is one master record moving through engagement contexts —
never re-created per module.**

```
Freelancer Profile → Candidate → Verified Technical Professional → Matched Resource →
Assigned Inspector → Scheduled Resource → Inspection Executor → Report Author →
Performance Record → Future Marketplace Candidate
```

…is **one person**, seen whole, across contexts. The same holds for **one client
master** and **one project/work-order context**.

---

## SECTION B — Non-Negotiable Preservation Rules

These are absolute. A change that violates any of them is wrong even if it "works."

1. **DO NOT BREAK ANY EXISTING WORKING MODULE, DATABASE, API, WORKFLOW, ROLE, REPORT,
   PERMISSION, OR INTEGRATION.**
2. **Audit before modifying.** Map what exists before you change it (Section C).
3. **Reuse existing entities.** Resolve to the canonical engine; never fork a copy.
4. **Extend, do not duplicate.** New behaviour hangs off an existing engine wherever
   one exists.
5. **No parallel databases.** No second person table, client table, project table,
   report store, or money ledger.
6. **No competing workflows.** If a flow exists, improve it in place.
7. **Preserve backward compatibility.** Old data keeps working; old readers keep
   reading.
8. **Additive migrations only.** `CREATE TABLE IF NOT EXISTS`, `ensure_column()`.
   Never drop, rename, or repurpose a live column in a migration.
9. **Never change the permission model without sign-off.** No role gets a permission
   not in `docs/02-permission-matrix.md`. A new permission stops work until approved.
10. **Never add a status or transition** not in `docs/03-object-lifecycles.md` without
    asking.
11. **Docs and code move together.** Any change to who-can-do-what, or to a lifecycle,
    updates `docs/` in the *same commit*.
12. **Never touch the default branch.** Work only on the designated feature branch.
13. **Every user-facing screen passes the Zero-Training UI gate**
    (`docs/05-ui-ux-blueprint.md`). The blueprint governs look/feel only — it grants
    no permissions and never overrides the matrix or lifecycles.
14. **Regression before commit.** `phpapp/tests/run.php` must stay green (baseline
    **5213 passing**) and `php -l` clean on every changed file.

**Reversibility law:** every slice must be rollback-able by turning its module gate
off and/or dropping its additive table — with no existing data touched.

---

## SECTION C — Existing-Application Audit (do this before any change)

You do not start by building. You start by mapping. The audit already exists and must
be read and kept current, not re-derived:

- `docs/revamp/01-current-state-inventory.md` — current state.
- `docs/revamp/02-gap-and-reuse-map.md` — gap vs reuse per capability.
- `docs/connect/01-reuse-map.md` — blueprint module → EXAACT engine, with verb
  (REUSE / EXTEND / CONNECT / CONFIGURE / BUILD).
- `docs/connect/06-system-audit.md` — system audit.
- `docs/phase-2/01-verification-audit.md`, `docs/phase-2/02-canonical-application-model.md`.

**Before implementing anything**, for the capability in hand, produce a one-screen
map: *which existing engine owns this today, what verb applies, what is the smallest
additive change.* If that map is not in the docs above, add it before you code.

Ground truth to hold in your head (all under `phpapp/lib/` unless noted):

- **~175 engine files.** The platform mostly already exists.
- Front controller `phpapp/index.php` + `ops_dispatch()`; views in
  `phpapp/views/ops/*` and `phpapp/views/portal/*`; `boot()` runs additive
  migrations.
- **Bootstrap-probe migration pattern:** a `SELECT` against each newest table/column
  in `index.php` so a single uploaded file auto-upgrades a live MySQL database.

---

## SECTION D — Unified Master-Data Architecture

**Design law (inherited, non-negotiable —
`docs/phase-2/02-canonical-application-model.md` §79/80):** *read through the named
canonical engine; converge via read-views and mapping layers, never table merges;
migrations are additive and idempotent.*

### D.1 Person / Resource master — ONE human, many contexts

- **Backing stores stay as they are** (do **not** merge): `users`, `inspectors`,
  `candidates`, `partner_contacts`, `client_users`, `vendor_users`, and the
  marketplace passport `cx_professionals`.
- **`lib/party.php` is the resolver** (`party_key`, `party_records_for`,
  `party_roles_of`, `party_render_also`). **`cx_identity_link`** binds a marketplace
  professional to their operational identity (the "one person" join).
- **Lifecycle is a read-time projection** — `person_state(kind,id)` derives the current
  state (Lead → Prospect → Candidate → … → Mobilizing → Deployed → Inspector →
  Available → Demobilized → Redeployment pool → Alumni) from records that already
  exist. **No state stored redundantly. No person duplicated into another table.**
- **Additive speed-up only:** an optional nullable cached `party_key` column per store
  via `ensure_column()` — never required, never a merge.

### D.2 Organisation / Client master — ONE record, many participations

- **`business_partners`** is the single client/organisation master (with `parent_id`
  branches, `client_type`, `industry`). Marketplace-enabled orgs also carry a
  `cx_organisations` row (mapping layer, not a second master).
- One client participates as recruitment client, inspection client, marketplace
  client, project owner, vendor, employer, billing customer — **without duplicate
  masters.** Portal scope (`portal_partner_id`) keeps clients isolated.

### D.3 Project / Work-Order context — ONE thread

- The operational spine is threaded **today by the `contract_number` string**;
  `lib/engagement.php` is a read-only grouping over it. A first-class **Engagement
  entity** is deliberately **deferred** until after the Billable-Event slice (Section
  O, `docs/revamp/slices/P7-engagement-entity.md`). When introduced it is **additive**
  (nullable `engagement_id`, backfilled from `contract_number`, **dual-read**, never
  dropping the string).

### D.4 Credentials & Competence master

- `competence.php` (+ `verify_status`), `identity.php`, `inspector_certs`, and the
  marketplace `cx_pro_certs` / `connect_credentials`. One credential, verified once,
  visible everywhere it is relevant.

**Rule for this whole section:** the same person, client, project, credential, report,
inspection, or invoice must never require duplicate entry.

---

## SECTION E — Technical Competence Passport

The freelancer/professional profile is not a form — it is a **Technical Competence
Passport**: public where the person allows, QR-verifiable, and the *same* dimensioned
record that powers matching.

- **Engines:** `connect_passport.php`, `connect_pro.php` (`cx_professionals`),
  `connect_credentials.php` (`cx_pro_certs`, `cx_pro_projects`), `competence.php`,
  `inspectorprofile.php`, `qr.php`.
- **Contents:** identity + verification tier
  (`registered → identity → credentials → proven`), the multi-select taxonomy profile
  (Section F), certifications with authority + number + validity + verify-route,
  project history, equipment/technology experience, industries, mobility & travel
  radius (`connect_geo.php`), availability, commercial expectations, ratings &
  performance history, and privacy controls (`connect_privacy.php` — the person owns
  what is public vs unlocked-on-engagement).
- **A person selects MANY, not one:** multiple skills, disciplines, specializations,
  certifications, project types, industries, equipment categories.
- **Verification is per-item** with expiry reminders; the passport shows *what is
  proven vs claimed.* Expired/expiring credentials are surfaced, and block
  `work.assign` where competence is mandatory.

---

## SECTION F — Technical Skills Taxonomy & Ontology

A searchable **ontology**, not a dropdown. This hierarchy is reused *everywhere* —
marketplace search, matching, requirements, recruitment, scheduling, competence
verification, inspection allocation, analytics.

### F.1 The reusable hierarchy

```
Discipline → Domain → Specialization → Sub-specialization →
Equipment / Technology → Activity → Certification
```

Worked examples the system must represent natively:

```
Mechanical → Inspection → Welding Inspection → CSWIP / AWS →
  Pressure Vessel / Piping / Structural Steel → Process → GTAW / SMAW / SAW …

Electrical → Power Systems → Transmission → Transmission Line → Technician →
  Erection / Testing / Commissioning / Maintenance → Voltage Range → Equipment Type
```

### F.2 How it is stored (already built — reuse)

- **Graph store:** `cx_tax_nodes`, `cx_tax_edges`, `cx_tax_aliases`, and
  `cx_profile_tax` (person↔node), driven by `connect_taxonomy.php`,
  `connect_tax_graph.php`, `connect_qualtax.php`
  (`connect_tax_node_add`, `connect_profile_tax_attach`).
- **Adopted master taxonomy (imported wholesale, admin-extensible, never hard-coded):**
  the 9 versioned tables from the blueprint — `sectors` (27), `equipment_groups`
  (+`equipment_types`, 11), `materials` (18), `disciplines` (22), `inspection_stages`
  (17), `standards` (13 families), `certifications_registry` (24), `taxonomy_versions`.
  Reconcile with existing `industry.php` / `methods.php` / `competence.php`. See
  `docs/connect/02-identities-and-taxonomy.md §3`.

### F.3 Coverage required

ITI trades through project managers, across: Mechanical, Electrical, Civil,
Instrumentation, Safety, QA/QC, NDT, Welding, Coating, Commissioning, Project
Management, Construction, Renewable Energy, Oil & Gas, Power, Infrastructure — and
**admin-extensible** for future domains. Every requirement = sector + equipment +
material + discipline(s) + stage + standards; every professional carries the same
dimensions, which is what makes matching explainable.

---

## SECTION G — Marketplace Architecture

The marketplace is a **two-sided acquisition layer feeding operations**, not a job
board.

- **Roles (adopted, no new role invented):** `inspector` (individual — applies/
  supplies), `company` (client/employer — posts; team sub-roles `owner` / `requester`
  / `approver` / `finance`), `agency` (manpower agency — **both** posts *and* supplies
  from its own bench), `admin` / `superadmin` (moderate). See
  `docs/connect/02-identities-and-taxonomy.md §1`.
- **Flow:**
  `Client creates requirement → system structures it → matching searches internal
  employees + candidates + verified freelancers + previously-deployed resources →
  ranking → client shortlists / requests → operations validates, contacts, negotiates,
  assigns, schedules, mobilizes → resource enters the existing Operations System with
  no manual recreation.`
- **Engines:** `connect_market.php`, `connect_hiring.php`, `connect_source.php`
  (unified sourcing across internal + bench + marketplace), `cx_requirements` /
  `cx_applications`, `connect_bench.php` / `connect_client_bench.php` (agency bench),
  `connect_reqtools.php`.
- **The bridge to operations is mandatory:** award → deploy via `connect_deploy.php`
  (award creates the operational job/PDSO). The marketplace **never** dead-ends.

---

## SECTION H — Matching & Ranking Engine

Explainable matching = **hard filters + weighted scoring + plain-language reasons.**

- **Engine:** `connect_match.php` (+ competence eligibility gate, `pdso.php`,
  `search.php`).
- **Hard filters (must pass):** mandatory qualifications, mandatory certifications
  (valid, not expired), discipline, licence/eligibility, availability window,
  client-approval status (no barred resources).
- **Weighted score (rank within filtered set):** certifications, skills match,
  discipline depth, years of experience, *project-type* experience, *industry*
  experience, location & travel radius (`connect_geo.php`), availability freshness,
  rate compatibility, previous performance / ratings, prior deployment with this
  client.
- **Explainability:** every ranked card states *why* it ranks where it does and *what
  is missing*. Weights are configurable data, never hard-coded magic numbers. An
  Area-Lead / placement-fee fallback exists when the pool is thin.

---

## SECTION I — Client-Portal Integration

Marketplace and operations are **one seamless client experience**, scoped strictly per
client party.

- A client sees, in one portal: requirements, marketplace matches, shortlist, resource
  profiles, projects, inspections, reports, approvals, billing.
- **Role-based dashboards** are live-computed from that client's own data
  (`lib/connect_client_dash.php`) — Technical Manager, Project Manager, Commercial,
  Site, Admin — every tile a live COUNT/SUM over existing tables, never hard-coded, and
  never leaking another client's figures. Money renders in Indian grouping.
- Wired into `views/portal/dashboard.php` and `views/portal/hiring.php`; access gated
  by `pcan()` so a tile only appears for a user allowed the register behind it.
- Isolation is structural (`portal_partner_id`): no client sees another's data.

---

## SECTION J — Recruitment & Staffing Integration

Add recruitment/staffing **without becoming a generic ATS.**

- **Reuse** `recruit.php` and the existing candidate stores; a candidate is the *same
  person master* (Section D), not a new identity.
- The recruitment funnel feeds the **same** matching, competence, mobilization and
  billing spine as the marketplace. A "candidate joined" is a billable-event source
  (placement fee), reusing the existing placement-fee path.
- **Do not** build ATS features that do not terminate in the operational spine
  (no standalone résumé CRM, no parallel interview product for its own sake).

---

## SECTION K — Operations & Scheduling Integration

The backbone. Everything upstream exists to feed it.

- **Spine:** `Lead → Opportunity → Quotation → Contract/PO → Call (op_status) →
  Job/Deputation (pdso) → Field Execution → Evidence → Report → Vetting → Approval →
  Issue → Release/IRN`.
- **Scheduling & allocation:** `pdso.php`, `schedule.php`, `schedboard.php`,
  availability, **allocation-conflict detection** (double-booking), mobilization
  readiness.
- **Mobilization readiness & Gate Pass** (`docs/revamp/slices/P2-mobilization-readiness.md`):
  one view answering *"what is blocking this person from mobilizing?"* composed from
  gates that already exist — competence (`work.assign`), identity docs +
  `site_doc_requirements`, medical/police/safety as **configurable** checklist items
  (`customforms.php` + `decisionrules.php` + lookups, never hard-coded client rules),
  PPE/asset, travel, and a minimal `gate_pass` record if none exists.

---

## SECTION L — Inspection Execution

Offline-first field execution and evidence capture.

- **Engines:** `idems.php` / `idems_autoform.php`, `trust.php` (geo check-in/out),
  `geofence.php`, `attend.php`, `timesheet.php`, `hwpoints.php` (hold/witness points),
  `stagegate.php`, PWA (`docs/edge-cases/47-mobile-pwa.md`).
- **Inspector sees:** today's assignments, inspection calls, documents, QAP/ITP,
  checklist, evidence capture, offline tasks, report submission.
- **Offline-first:** capture works without connectivity; sync-state is explicit and
  recoverable (`docs/revamp/slices/P3-inspection-execution-polish.md`). Evidence is
  chained (`report_files`, `evidence_chain`) with SHA-256 and no-delete revisions.

---

## SECTION M — Reporting Engine (URFE — mandatory reuse)

- **The Universal Report Foundation Engine (URFE/IDEMS/UIRE) is LOCKED and reused —
  never re-implemented.** All report types (Original / Duplicate / Triplicate
  counterparts, serials, IRN, issue, disposition via `urade`) go through it.
- Reports carry SHA-256 integrity + QR verify + retention (`retention.php`,
  `controldocs.php`) + no-delete revisions.
- The **report template builder** (`docs/edge-cases/48-report-template-builder.md`)
  extends URFE; it does not fork it.
- A report is authored by the **same person master** who executed the inspection
  (Section D) — the report author *is* a performance-history event.

---

## SECTION N — ISO 17020 & Quality Integration

Impartiality, competence, traceability, auditability are first-class, reusing what
exists:

- **Competence** gate on assignment (`competence.php`, `work.assign`) —
  `docs/edge-cases/24-competence.md`.
- **Impartiality / conflict of interest** — `impartiality`,
  `docs/edge-cases/25-impartiality.md`.
- **Quality cases:** `ncr.php`, `capa.php`/`ncdca.php`, `qualitycase.php`, risks,
  `hwpoints.php`, `complaints.php`, `reportreview.php`.
- **Identity, confidentiality, audits, data control, evidence** — edge-cases 26–29, 44.
- Traceability rides the **audit chain + activity spine + retention + DPDP** already in
  place. Nothing here is a new subsystem; it is disciplined reuse.

---

## SECTION O — Commercial & Finance Integration

Inspection-to-invoice **and** deployment-to-invoice, with **one money truth**.

- **★ Billable Event ledger** (the one strategic additive BUILD —
  `docs/revamp/03-target-architecture.md §4`,
  `docs/revamp/slices/P4-billable-event-ledger.md`): a persisted ledger of *billable
  candidates*, each bound to the operational occurrence that earned it
  (`REPORT_ISSUED` / `IRN` / `JOB_CLOSED` / `TIMESHEET_APPROVED` / `OT_APPROVED` /
  `CANDIDATE_JOINED` / `MILESTONE`), with lifecycle `PENDING → APPROVED → BILLED →
  (CANCELLED | DISPUTED)` and `UNIQUE(source_module, source_kind, source_id)` for
  idempotent derivation. It guarantees the directive's core promise: **operational
  work never disappears before reaching billing.**
- **It is NOT a second money truth.** The **books ledger stays canonical** (`books.php`
  — `invoices`, `invoice_lines`, `receipts`, `credit_notes`). Once a billable event is
  invoiced, `invoice_line_id` is stamped and its amount is **reconciled to the invoice
  line** so it can never drift. `finevent.php` remains the read-model; `billable_events`
  adds the missing *pre-invoice* band.
- **Engagements** (`cx_engagements`), vouchers (`connect_engvoucher.php`), receivables,
  revrecon, costing, `job_profit` — all reused. Finance sees approved billable events,
  pending billing, draft invoices, PO consumption, expenses, revenue leakage.
- **Cost dual-truth convergence** (`docs/revamp/slices/P8-cost-dualwrite-detector.md`,
  `P9-revenue-reconciliation.md`): converge by **dual-read + reconciliation check**;
  switch a reader only after parity is proven. Never a table merge.

---

## SECTION P — Permissions & User Roles (strict RBAC + contextual dashboards)

- **RBAC is authoritative in `docs/02-permission-matrix.md`.** No role gets a
  permission not listed there. A new permission is proposed, written into the matrix,
  and **approved before code.** Engines: `access.php` (`can()`, `ORG_ROLES`), `pcan()`
  (client portal), `vendor_users` (vendor portal), `security.php`, IDOR/scope gates.
- **Progressive disclosure by role** — each user sees only what is relevant:
  - **Freelancer:** passport, credentials, skills, availability, travel prefs,
    opportunities, applications, assignments, schedule, tasks/reports, earnings,
    performance history.
  - **Client:** requirements, matches, shortlist, resource profiles, projects,
    inspections, reports, approvals, billing.
  - **Operations Coordinator:** incoming requirements, resource availability, matching
    recommendations, scheduling, allocation conflicts, mobilization, pending actions.
  - **Inspector:** today's assignments, calls, documents, QAP/ITP, checklist, evidence,
    offline tasks, report submission.
  - **Technical Approver:** reports pending review, technical findings, NCRs,
    clarifications, approve/reject workflow.
  - **Finance:** approved billable events, pending billing, draft invoices, PO
    consumption, expenses, revenue leakage.
- Role flows are documented per role in `docs/04-flows/<role>.md` and must stay in
  sync. Segregation of duties (maker ≠ checker) is preserved everywhere it exists.

---

## SECTION Q — UI/UX Revamp

- **Governing standard:** `docs/05-ui-ux-blueprint.md` — every user-facing screen must
  pass the **"Zero-Training UI" gate.** Must **never look like an ERP** (SAP / Oracle /
  SGS portal).
- **Progressive disclosure (Rule 3):** show essential fields first; reveal deeper
  technical options intelligently based on earlier selections (the taxonomy drill-down
  of Section F drives this).
- **Field vs desk (do not average them):** inspectors are **phone-first in the field**;
  coordinators, managers and finance are **desk-first on a laptop.** Design for both.
- Fast, simple, mobile-first where the user is mobile. The blueprint governs
  look/feel/interaction only — it grants **no** permissions and never overrides the
  matrix or lifecycles.

---

## SECTION R — Integration Strategy (build vs integrate vs external)

- **Orchestrate, don't absorb.** Payroll posting, accounting (Tally), job boards,
  messaging (WhatsApp/SMS/email), biometrics, SSO — all through the existing
  integration boundary: `webhookq` (generic outbox, retry/backoff), `tally`,
  `booksbridge`, `adssync`, `mghsso`. EXAACT stays the operational brain; specialist
  systems stay external.
- **AVOID list (respected):** no proprietary payroll calculation, no generic
  ERP/LMS/marketplace/BIM rebuild. Internal money/CRM/AI are native reuse (`books.php`,
  `crm.php`, `ai.php`, `finevent.php`), not external webhooks.

---

## SECTION S — India-Specific Requirements

- **DPDP** data-protection compliance rides the existing retention/privacy/audit
  spine (`connect_privacy.php`, `retention.php`, `datacontrol.php`).
- Indian industrial-operations norms: money in Indian grouping (lakh/crore),
  GST/PO-consumption on the commercial side, local mobility/travel-radius realities in
  matching, and India-first certification registries (CSWIP, ASNT, NACE, BGAS, etc.) in
  the taxonomy.
- Site-document and gate-pass norms for Indian plants/refineries as **configurable**
  checklist items, never hard-coded.

---

## SECTION T — Testing & Acceptance Criteria

You must **prove** every workflow, not assert it.

- **Regression gate:** `phpapp/tests/run.php` stays green — baseline **5213 passing**,
  0 failing — after every slice. `php -l` clean on every changed file.
- **MySQL portability:** production is MySQL, dev is SQLite. Guard placeholder counts
  (HY093), set permissive `sql_mode` at seed start, compute dates in PHP and bind as
  params (no SQLite-only date math).
- **Per slice, four case classes** (directive Part 27): positive, negative, boundary,
  offline, permission.
- **Scenario seeds** (`docs/demo-scenario-s0N.md`, `tools/seed-scenario-s0N.php`) are
  idempotent, purge-first, namespaced, and each prints a **real derived PASS/FAIL
  dashboard** — a screen is not "done" because it renders; the dashboard must assert
  the actual values from live queries.
- **End-to-end proof:** the scripted runs in `docs/qa-edge-cases/` (job→invoice,
  report→issue, quotation→contract, site-execution) must pass on seeded data.

---

## SECTION U — Negative & Failure Scenarios (must be designed, not discovered)

Every one of these has a defined, tested behaviour:

- Scheduling **conflict / double-booking** → detected and surfaced, never silently
  allowed.
- **Expired / expiring credential** → blocks `work.assign`; surfaced on passport and
  dashboards.
- **Missing site documents / failed gate pass** → mobilization blocked with the reason.
- **Failed offline sync** → explicit, recoverable sync-state; no lost evidence.
- **Rejected report** → returns through the vetting/approval loop; NCR where warranted.
- **Billing mismatch** → billable-event ↔ invoice-line reconciliation flags drift;
  disputes have a lifecycle (`connect_disputes.php`).
- **Barred / conflicted resource** → impartiality and client-approval hard filters.
- **Duplicate person / client / document** → dedupe (`dedupe.php`) flags to quiet
  review, never auto-penalty.

See `docs/edge-cases/` (50 numbered files) and `docs/field-findings/` for the full
register — extend those, don't restart them.

---

## SECTION V — Implementation Sequence

**Audit → Architecture → Extension → Integration → Testing.** No code until the slice's
change-control record is approved (`docs/revamp/00-program.md`, Part 25).

Roadmap (from `docs/revamp/03-target-architecture.md §8`):

| # | Slice | Class |
|---|---|---|
| P1 | Technical Competence / Credential Vault | EXTEND |
| P2 | Mobilization readiness + Gate Pass | CONNECT + small BUILD |
| P3 | Inspection execution polish (offline sync-state, QAP packs) | KEEP/IMPROVE |
| P4 | ★ Billable Event ledger + Commercial cockpit | BUILD (additive, reconciled) |
| P5 | Consolidation: vestigial statuses, cost/invoice dual-truth | REFACTOR |
| P6 | Product-package presets + role cockpits | CONFIGURE |
| Later | First-class Engagement entity | DEFER → BUILD (after P4) |

Marketplace/passport/matching slices (K0 taxonomy → K1 passport → K2 post/apply → K3
matching …) land in `docs/connect/`. Each slice is its own reversible commit with its
own change-control record.

---

## SECTION W — Definition of Done

A slice is **not** done because screens exist. It is done when **all** of these hold:

1. The capability terminates in the operational spine (nothing dead-ends).
2. No existing module, API, workflow, role, report, permission, or integration
   regressed. `phpapp/tests/run.php` green at ≥ baseline; `php -l` clean.
3. No duplicate person / client / project / credential / report / invoice was created.
4. Every new status is in `docs/03-object-lifecycles.md`; every permission/module change
   is in `docs/02-permission-matrix.md`; role flows updated — **same commit** as code.
5. Migrations are additive, idempotent, and bootstrap-probed in `index.php`.
6. Positive, negative, boundary, offline and permission cases pass.
7. The scenario seed's derived PASS/FAIL dashboard is ALL PASS and asserts real values.
8. Every user-facing screen passes the Zero-Training UI gate for both field and desk.
9. The slice is reversible (module gate off / additive table drop) with no data loss.
10. The default branch is untouched; work is on the designated feature branch, with a
    clear commit and (only if asked) a PR.

---

## THE FIVE STANDING ARCHITECTURAL RULES (pin these)

1. **No duplicate modules.** If functionality exists, improve/extend it — never build a
   competing module.
2. **No duplicate data.** The same person, client, project, credential, report,
   inspection, or invoice is never entered twice.
3. **Progressive disclosure.** Essential fields first; deeper technical options appear
   intelligently from earlier selections.
4. **Industry-specific intelligence.** The `Discipline → Domain → Specialization →
   Sub-specialization → Equipment/Technology → Activity → Certification` hierarchy is
   reusable across marketplace search, matching, requirements, recruitment, scheduling,
   competence verification, inspection allocation, and analytics.
5. **One operational spine.** `CLIENT/REQUIREMENT → MATCHING → VALIDATION → SELECTION →
   ALLOCATION → SCHEDULING → EXECUTION → REPORTING → TECHNICAL REVIEW → CLIENT
   APPROVAL → BILLABLE EVENT → INVOICE → QUALITY/PERFORMANCE HISTORY → IMPROVED FUTURE
   MATCHING.** Keep it connected end to end.

---

*This master prompt is the capstone. Where it summarises, the linked `docs/revamp/`,
`docs/connect/`, `docs/phase-2/3/`, `docs/edge-cases/`, `docs/02-permission-matrix.md`
and `docs/03-object-lifecycles.md` carry the detail. On any conflict, `CLAUDE.md` and
the permission matrix / object lifecycles win — they are the law; this document is the
plan for executing against them.*
