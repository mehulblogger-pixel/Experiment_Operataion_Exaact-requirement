# 02 — Gap & Reuse Map (Phases B / C)

Maps every major requirement of the ITOP directive onto the **existing** EXAACT
codebase and classifies it (KEEP / IMPROVE / EXTEND / CONNECT / REFACTOR / BUILD /
HIDE·DEFER / AVOID — see `00-program.md` §4). The headline: **almost everything
already exists.** Genuine BUILD is rare and named explicitly.

---

## 1. Directive engines → existing code

### Part 6 · Engine 1 — Technical Competence & Credential Vault
**Verdict: EXTEND (mostly KEEP).** Exists as `lib/competence.php` (`authorisations`,
`qualifications`, `witness_assessments`), `lib/identity.php` (`person_documents`,
`person_document_access`, `site_doc_requirements`), inspector certs
(`inspector_certs` with expiry + cron reminders), and the competence **gate** that
already fires on `work.assign` under the accreditation pack.
- **Have:** credential category/number/issue/expiry/document/verification, expiry
  reminders, authorised-for-this-work-on-this-date logic, client site-doc
  requirements, DPDP-gated ID docs (`person.iddoc.*`), encryption at rest (§53).
- **Gap to close (EXTEND):** a single "credential vault" surface per person with
  the directive's status vocabulary (Valid / Expiring Soon / Expired / Under
  Verification / Rejected / Superseded / Missing) and **configurable
  client-specific competency requirement sets** (reuse `customforms` + lookups;
  do not hardcode certificates). This is the recommended **Priority-1 slice** (§6).

### Engine 2 — Requirement ↔ Resource Matching
**Verdict: EXTEND / CONNECT.** Requirements exist across `requisitions`,
`po_line_items` (trade/skill/activity/site/manpower), calls, and candidate
sourcing; competence data exists in Engine 1.
- **Gap:** a **match view** that scores a resource against a requirement and
  returns Fully Qualified / Partially Qualified / Missing Documents / Expiring
  Credential / Client Approval Required / Not Qualified — a read-model over
  competence + identity + requirement, not new master data.

### Engine 3 — Mobilization & Gate Pass
**Verdict: EXTEND / CONNECT (small BUILD for gate-pass record + configurable
checklist).** Deputation & site readiness already exist in `lib/pdso.php`
(`dep_checklist`, `dep_manpower`, `dep_site_log/history`, `dep_status_events`,
`dep_att_approval`, `dep_timesheet`), plus competence/impartiality gates,
`site_doc_requirements`, `trust`/`geofence` site check-in.
- **Gap:** a **configurable mobilization checklist** (documents/medical/police/
  safety/travel/PPE/gate-pass stages — reuse `customforms` + `decisionrules` +
  competence/identity gates) and one **"what is blocking this person from
  mobilizing?"** readiness view (compose from existing gates). Gate-pass
  request/approval is the only likely net-new record — keep it minimal and
  additive.

### Part 6 · Engine 4 + Part 7 (QAP/ITP) + Part 11 (IRN) — Inspection Operations
**Verdict: KEEP.** Fully present in `lib/idems.php` + `uire.php` + `hwpoints.php`
(Hold/Witness/Review/Surveillance/Custom points) + `urade.php` (release/acceptance/
disposition) + `uvae`/`uvaae` (vendor inspection/assessment). IRN numbering is
configurable and duplicate-proof; report lifecycle has vetting → approval chain →
issuance-readiness gate → sealed issue; QAP/ITP is data-driven via the no-code
builder and criteria packs. **No rebuild — configure client-specific packs.**

### Part 8/9 — Field Execution & Offline-First
**Verdict: KEEP / IMPROVE.** PWA + offline queue + draft autosave + geofenced
punch + evidence-with-GPS + phone-first field mode already ship.
- **Gap (IMPROVE):** finish draft-protection on any remaining field forms and make
  the sync-state vocabulary (Saved locally / Pending sync / Syncing / Synced / Sync
  failed) uniform and visible everywhere it applies.

### Part 10 — URFE (reporting foundation)
**Verdict: KEEP (reuse only).** `lib/urfe.php` + `idems.php` are the shared
foundation (field library, sections, table engine, evidence, findings, statuses,
versioning, audit, template builder). **Directive rule honored: no second report
engine, no second PDF stack, no per-module document vault.**

### Part 12 — Findings / NCR / Actions / Risk
**Verdict: KEEP.** `ncr.php`, `capa.php`, `ncdca.php`, `risks.php`, `samples.php`,
`hwpoints.php`, `complaints.php` cover observation→finding→NCR→CAPA→disposition→
closure with severity/responsibility/due/evidence/review/audit. Reuse; do not add a
parallel issue system.

### Part 13 — Technical Recruitment
**Verdict: KEEP / EXTEND.** `lib/recruit.php` + `recruit_cc.php`, `candidates` +
`candidate_events` (RECEIVED→SUBMITTED→SHORTLISTED→INTERVIEW→…→ACCEPTED), CV keyword
engine, placement-fee/guarantee logic (`confirm_lapsed_placement_fees` cron).
- **Gap (EXTEND):** white-labeled CV generation + contact masking if not already
  present; interview/assessment tracking depth. Keep it focused — no generic HR.

### Part 14/15 — Technical Staffing, Timesheet & Muster
**Verdict: KEEP / CONNECT.** Deputation (`pdso`), attendance (`attend`,
`geofence`, `attendreview`), timesheet (`timesheet`, `dep_timesheet`), commercial
structures on `po_line_items` / calls / jobs.
- **Gap (CONNECT/REFACTOR):** converge the time stores (see §3 R9) so approved
  timesheet feeds billing without re-entry.

### Part 16/17 — Billable Event Engine + Commercial/Invoice
**Verdict: the one strategic BUILD (as an EXTEND of `finevent`).** Today
`lib/finevent.php` is a **read-only projection** and "billable" is a *value on the
call/job*, not a discrete operational-event→charge object. Invoicing
(`books.php` ledger: `invoices`/`invoice_lines`/`receipts`/`credit_notes`),
readiness gate (`invready.php`), rate/PO logic, receivables and revenue
reconciliation all exist.
- **BUILD (additive, non-destructive):** a persisted **Billable Event** that binds
  an approved operational occurrence (report issued / IRN / approved timesheet /
  approved OT / candidate joined / milestone) to source module + record + client +
  contract + service + qty/unit/rate/rule/amount + approval + billing status +
  invoice linkage. Built as a thin ledger **on top of** existing approvals and the
  books ledger — not a replacement, and reconciled against `finevent`/§29 so it
  cannot drift. This is the directive's core differentiator and the highest-value
  new work.

### Part 22/23 — Data/Security/Privacy + Integration architecture
**Verdict: KEEP.** RBAC + scope + single-record IDOR gate (§51/§72), encryption at
rest (§53), audit chain protection (§54), retention (`retention.php`), DPDP
(`compliance.php`). Integration layer exists: `webhookq.php` (generic outbox,
retry/backoff), `booksbridge`, `tally`, `adssync`, `mghsso` — all with
config/logging/retry. Structured, not hardcoded in business logic.

### Part 24 — Analytics
**Verdict: KEEP / EXTEND.** `tapi*` (KPI defs/targets/snapshots/scorecards/alerts),
`mis.php`, `chain.php`, entity/customer/vendor-360. Extend with the directive's
specific operational metrics (billing leakage, mobilization lead time, redeployment
time) as KPI definitions — configuration, not new infrastructure.

---

## 2. Build / Extend / Integrate / Avoid matrix (summary)

| Classification | Items |
|---|---|
| **KEEP (reuse as-is)** | URFE/IDEMS reporting, QAP/ITP builder, IRN, hold/witness points, NCR/CAPA/risk/samples, vendor assessment (uvae/uvaae), release/disposition (urade), books ledger + invoicing, RBAC/scope/IDOR/audit/retention/DPDP, PWA/offline core, TAPI analytics, portals, packs/licensing/tenants |
| **EXTEND (additive)** | Competence → full credential vault + configurable client requirement sets; recruitment (white-label CV, masking); analytics KPIs; requirement↔resource match view |
| **CONNECT (wire existing together)** | Mobilization readiness ("what's blocking this person"); timesheet→billing feed; person master lifecycle view over `party` |
| **REFACTOR (converge safely)** | Vestigial statuses (R10); cost dual-write (R9); financial dual-truth readers (§29/§80); contract second-door lifecycle (R4) |
| **BUILD (genuinely new)** | Persisted **Billable Event** ledger (as EXTEND of `finevent`); minimal **gate-pass** record + configurable mobilization checklist; (later, if approved) first-class **Engagement** entity to replace the `contract_number` string |
| **CONFIGURE (presets over existing switches)** | Product **packages** (TPIA / Technical Staffing / Recruitment / Enterprise) as presets over packs + licensing + module gates; role **cockpits** as curated area homes |
| **HIDE / DEFER** | Package-gate marketing connectors (`adspro`/`adsroi`) out of TPIA/Staffing packages; defer Engagement entity until after the Billable Event slice |
| **AVOID (out of strategic focus)** | Generic OKRs, corporate social feeds, generic employee-engagement portals, broad LMS, public manpower marketplace, unvalidated AI video interviewing, 3D/BIM, proprietary payroll calc engine — *orchestrate payroll/accounting via integration instead* |

---

## 3. Disconnects, duplicates & vestigial state (Phase C — consolidation targets)

Non-destructive fixes, each additive/reversible; none is a rebuild.

1. **Vestigial statuses (R10) — REFACTOR.** `calls.status=CLOSED`, `jobs.stage`
   intermediates, `report_docs.ARCHIVED` are never advanced; metrics reading them
   (e.g. ops-desk `report_pending`) are always 0. Fix: point metrics at the real
   state (`op_status` / `closed_flag` / `report_approval`) or advance the field —
   *decide per field, update `docs/03-object-lifecycles.md` in the same commit.*
2. **Cost dual-write (R9) — REFACTOR/CONNECT.** Job-closure expenses and the
   inspector voucher both write the cost picture. Converge via a read-view /
   canonical calculator (the app already prefers `job_profit()` snapshots), not a
   table merge.
3. **Financial dual-truth (§29/§80) — REFACTOR.** `jobs.invoice_amount` /
   `payment_received` legacy snapshot vs the books ledger. `finance_truth_unified`
   is ON; finish migrating remaining readers after §29 reconciliation confirms
   parity. **Do not switch a reader before reconciliation passes.**
4. **Contract second door (R4) — REFACTOR.** `partner-add kind=contract` bypasses
   the PENDING→endorse→approve two-signature lifecycle. Route it through the same
   lifecycle. (Touches a control → confirm before changing.)
5. **Operational→commercial bridge — BUILD (see Engine 16).** The gap that most
   justifies new code; everything else here is convergence.

---

## 4. Master data — the single person/resource record

**Verdict: KEEP the mapping-layer design; EXTEND with a lifecycle view — do NOT
merge tables.** `lib/party.php` already resolves one human across `users`,
`inspectors`, `candidates`, `partner_contacts`, `client_users`, `vendor_users` by
ref→mobile→email. The directive's "one person, many lifecycle states"
(Lead→Candidate→…→Deployed→Inspector→Alumni) is satisfied by an **EXTEND**: a
lifecycle/role projection over `party` (read-time), preserving history, **not** a
destructive table merge. This is faithful to both the directive ("use lifecycle
transitions, don't duplicate") and the codebase's §79/80 law ("mapping layers,
never table merges").

---

## 5. Product packages (Part 19) — CONFIGURE, don't build

Map the four packages to presets over the **three switches that already exist**:

| Package | packs | licensed bundles | emphasis (module gates on) |
|---|---|---|---|
| **EXAACT TPIA** | inspection (17020) / labs (17025) | operations, reporting, money, admin | calls, jobs, idems, equipment, competence, impartiality, ncr, capa, audits, invoicing; **hide** recruitment/staffing depth & marketing |
| **EXAACT Technical Staffing** | (pack off or minimal) | operations, hr, money, admin | hiring, deputation, competence, attendance/timesheet, invoicing; **hide** QAP/IRN/field-inspection |
| **EXAACT Recruitment** | (off) | sales, hr, admin | leads, inquiries, quotes, candidates, interviews, placement invoicing; **hide** muster/QAP/IRN/site deployment |
| **EXAACT Enterprise** | all | all | controlled access to everything |

Deliverable in Phase F: named preset definitions + a one-click "apply package"
that sets `packs_enabled`, `modules_off`, and recommended role/module defaults —
all existing mechanisms.

---

## 6. Recommended Priority-1 first slice (for approval before any code)

**Slice: Technical Competence / Credential Vault (EXTEND).** Highest directive
priority, lowest risk, pure additive.

Change-control skeleton (directive Part 25 — to be filled and approved first):
- **Existing state:** `competence.php` + `identity.php` + `inspector_certs`;
  gates fire on `work.assign`.
- **Problem:** no single per-person vault view; credential status vocabulary not
  uniform; client-specific requirement sets not configurable.
- **Proposed solution:** a read-first "Credential Vault" panel per person +
  configurable requirement sets via `customforms`/lookups; status derivation
  (Valid/Expiring/Expired/Under-Verification/Rejected/Superseded/Missing).
- **Reuse:** competence, identity, inspector_certs, lookups, customforms, datatable.
- **DB impact:** additive only (a small requirement-set config table or reuse
  `custom_forms`); no changes to existing columns.
- **API/route impact:** new read routes; reuse existing gates.
- **Migration:** `CREATE TABLE IF NOT EXISTS` + one bootstrap probe line.
- **Dependency/regression:** competence gate on `work.assign` unchanged; run
  `phpapp/tests/`; add competence status + config cases (positive/negative/
  boundary/expired/permission).
- **Rollback:** module gate off / drop the new additive table; no data loss.

**No code is written until this slice's change-control record is approved.**
