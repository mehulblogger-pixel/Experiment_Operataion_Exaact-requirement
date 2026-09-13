# 15 — EXAACT ARCHITECTURE LOCK
### The authoritative Phase-0 architecture decision record

**Where any other document disagrees with this one, THIS DOCUMENT WINS.**

---

## 1. Purpose

This record locks the architecture decisions that govern the EXAACT Recruitment / SaaS programme. It exists to prevent three specific failures:

1. implementing technical debt merely because an audit recorded it;
2. redesigning working modules to make boundaries look tidy;
3. drifting from the agreed phase structure into a fragmented programme.

It is binding on every developer and every coding agent working on this repository. Phase 0 is **audit and architecture lock only** — no application code was changed.

---

## 2. Production database decision

- **Production database: MySQL/MariaDB.**
- **Existing automated regression harness: SQLite.**
- **The current automated suite therefore does not fully exercise the production MySQL/MariaDB database engine.**

EXAACT is **not** a SQLite application and must never be described as one. SQLite may remain as a fast supplementary test layer; it is never the production database.

**MySQL/MariaDB compatibility is the authoritative database-testing requirement for all future implementation.** Where a phase reports test results, the MySQL/MariaDB result is the authoritative one.

Measured Phase-0 baseline: **444 test files / 6948 assertions / 0 failures / 81.83 s**, on the SQLite harness, PHP 8.4.19.

---

## 3. SaaS entitlement principle

A customer may use **only** the modules it is subscribed/entitled to.

| Customer | Subscribed | Result |
|---|---|---|
| A | Operations + Reporting | Operations ✓ Reporting ✓ · Recruitment, Marketplace, Money, Sales **locked** |
| B | Recruitment + Reporting | Recruitment ✓ Reporting ✓ · Operations, Marketplace, Money **unavailable unless subscribed** |
| C | Operations + Recruitment + Marketplace + Reporting | all four ✓ |

A locked module may be **visible** as `LOCKED` / `NOT SUBSCRIBED` / `UPGRADE REQUIRED` but **must not be usable**.

**Enforcement must exist at every execution path:** navigation · page · route · direct URL · POST · AJAX · export · report · public route · background job.

**Hiding a menu item is never sufficient.**

Absent or unknown entitlement must mean **DENY**. An explicit, auditable "unlimited" marker is reserved for the control/platform-owner install only.

---

## 4. Module independence principle

An Operations-only customer is a completely valid tenant. Such a customer must be able to complete onboarding, configure the organisation, create users, and use Operations and Reporting — **without ever configuring Recruitment**, creating recruiters, configuring pipelines, candidate sources, a Career Page, interviews or offers.

Modules activate later without loss: existing organisation data, departments, designations, people, users and roles remain and are **reused**, not recreated.

Deactivation, downgrade, suspension or non-renewal **must never delete historical records**. Reactivation restores access to existing data.

---

## 5. Operations protection

**Operations is a functioning existing asset. PRESERVE.**

- Keep Operations as currently implemented, including its tables and libraries.
- Do **not** redesign Operations merely to make module boundaries look cleaner.
- Recruitment's save/update code currently lives inside `lib/ops.php`. This is recorded as a dependency risk (R-7), and is addressed only by *safe extraction* in Phase 2 — never by rebuilding Operations.
- **Every change touching `lib/ops.php` requires full Operations regression.**
- The Operations-only customer scenario (S-1) is a **mandatory gate on every phase**.

---

## 6. Quality protection

**Operations + Quality = PRESERVE. Quality is KEEP / PROTECT.**

- Keep Quality as currently implemented, including its 31 tables and libraries.
- Keep existing accreditation-pack behaviour unless a future phase explicitly requires a change.
- Do **not** create a standalone Quality engine.
- Do **not** duplicate Quality tables.
- Do **not** migrate Quality functionality into another module.
- Do **not** split Quality from Operations.

The Phase-0 finding that Quality is technically embedded within Operations, gated by accreditation-pack settings rather than by entitlement, **remains documented as an architectural OBSERVATION. It is not an implementation task.**

If Quality is ever required to become independently commercially subscribable, that is a **separate future architecture decision**, outside this programme.

---

## 7. Recruitment architecture

Recruitment is the programme's subject. Its conceptual model:

```
REQUEST → FULFIL → HIRE / DEPLOY → MEASURE
```

Recruitment is not a job-posting system. The platform must support configurable operating models — Corporate HR, Recruitment Agency, Permanent Placement, RPO, Contract/Temporary/Flexi Staffing, Technical Manpower Supply, Project Workforce, TPIA, Vendor/Supplier networks, MSP/VMS, Marketplace and hybrids. One organisation may operate several. **No tenant is forced into one mutually exclusive customer type, and no customer is forced to use every flow.**

Existing recruitment strengths that are **PROTECTED**: the configurable candidate pipeline engine, the enforced offer lifecycle, document/letter templates, the client-side agency commercial model, and the fact that Career Page applicants already enter the same candidate engine as manual entry.

---

## 8. Hiring Request architecture

```
Requestor → Hiring Request → Job Profile → Job Description → Approval
          → Approved Requirement/Requisition → Recruiter Assignment → Recruitment execution
```

- **Sourcing may not begin before the required approval state.**
- Recruiter assignment supports: assigned recruiter · assigned by · assigned date/time · reassignment · notification · ownership · KPI attribution.
- **Career Page is OPTIONAL.** A complete hiring process must work perfectly without publishing a vacancy.
- **Manual CV intake must work without a Career Page.** All CV sources — email, WhatsApp/manual upload, referral, existing database, previous applicants, job portals, agencies, suppliers, marketplace, direct candidate, recruiter sourcing, import, Career Page — converge into the **same candidate/person engine**, capturing **Source Type** and, where applicable, **Source Party**.
- **Reuse the existing approval engine.** Widen its entity set and actually invoke it for requisitions. **A second approval engine is forbidden.**

---

## 9. Requirement / Requisition relationship

Recruitment `requisitions` and Marketplace `cx_requirements` are **two distinct records with distinct lifecycles and audiences**. Today they share no reference in either direction.

**Decision: CONNECT / MAP via an adapter. Do NOT physically merge the tables.**

Requisition status gains a guarded transition model, reusing the proven Marketplace pattern rather than inventing one.

---

## 10. Multi-source fulfilment

One requirement may be fulfilled through multiple sources and **remains ONE requirement**:

> 20 positions = 5 Internal + 3 Direct + 4 Supplier A + 3 Supplier B + 5 Marketplace

Required: fulfilment allocation · source type · source party · quantity · allocated · remaining · over-allocation protection · partial fulfilment · replacement · source traceability · marketplace connection · candidate/application relationship.

Current state (defect): accepting one candidate closes the entire requisition regardless of quantity (`lib/ops.php:5090-5093`). Corrected in Phase 2; the full model is Phase 4.

---

## 11. Person / Resource identity principle

**One real person must not be duplicated merely because their business context changes.**

One identity may be associated with: Candidate · Applicant · Employee · Worker · Contractor · Consultant · Freelancer · Marketplace Professional · Inspector · Supplier Worker · Bench Resource · Deployed Resource · Former Employee.

**Decision: CONNECT, never MERGE.**
- Establish a canonical Person/Resource **conceptual** model.
- The existing `inspectors` table **may remain the technical spine** for compatibility. **Do not rename it for cosmetic reasons.**
- Maintain existing person tables where they serve valid business purposes; the separation of portal/vendor/professional user tables from staff `users` is a deliberate security boundary and is **preserved**.
- Extend identity links rather than collapsing records. Duplicate detection may **suggest**; a human confirms.

---

## 12. Organisation relationship principle

`business_partners` (client/vendor), `cx_organisations` (marketplace) and `agencies` (supplier) represent the same real company in three places.

**Decision: CONNECT via cross-reference. Do NOT physically merge their tables** — they carry different lifecycles and permissions.

`candidates.agency` is free text with no foreign key; a `agency_id` relationship is added in Phase 2 so "which supplier supplied this CV" becomes answerable.

---

## 13. Taxonomy principle

Four distinct concepts must never be conflated:

| Concept | Meaning | Used on |
|---|---|---|
| **User security role** | what a user may **do** | `users.role` + `can()` |
| **Staff position / title** | our employee's job title | team, org chart |
| **Our departments** | our internal org units | org chart, approvals |
| **Vacancy department + designation** | what we are **recruiting for** | requisition, candidate, offer |

Today the internal organisation masters and the vacancy taxonomy share the same lists — the root cause of the "role vs designation vs department" confusion. Separated in Phase 2.

Organisation structure (department, business unit, location, site, position, reporting) is distinct from job/workforce taxonomy (job family, sector, discipline, domain, designation, role, trade, specialisation, skills, equipment, technology, certification, qualification, experience), which is distinct from user security role.

Where a richer controlled vocabulary already exists in Marketplace, **connect to it rather than growing a third vocabulary.**

---

## 14. Marketplace relationship

Marketplace/Connect is the **largest module by table count (65)** and is currently gated by an ordinary setting that **defaults to ON for every cloud tenant**.

**Decision: Marketplace becomes a properly commercially controllable module in Phase 1**, including its public front-door routes. Its existing engines — guarded transitions, taxonomy graph, identity link ledger, escrow/fee/credit rails — are **PROTECTED and reused**, not rebuilt.

---

## 15. KPI / TAPI principle

**A reusable KPI engine already exists: TAPI (`lib/tapi.php`, `lib/tapi_score.php`)** — configurable KPI definitions, a metric-adapter registry, a safe non-`eval` formula parser, target/threshold grading with a NO_DATA distinction, per-office/SBU/period targets, weighted scorecards, cron alerts and a presentation kit.

**Recruitment KPIs MUST be implemented as TAPI metrics. Building a second KPI engine is forbidden.**

Prerequisite: target and actual dates do not exist today, and `recruit_stages.sla_days` is stored but never read. **Dates must be captured before any KPI work.**

Each registered metric must carry its own module gate, because TAPI itself is not licence-gated.

---

## 16. Workflow principle

- Lifecycle transitions must be **guarded**, not set freely. A proven guard already exists in Marketplace; reuse that pattern.
- The configurable candidate pipeline becomes the **single authoritative** pipeline; the legacy stage vocabulary is contained or derived, never maintained in parallel.
- **A second pipeline engine is forbidden.**
- No status or transition may be added that is not in `docs/03-object-lifecycles.md` without approval.

---

## 17. Migration principle

Production is MySQL/MariaDB. Schema migration is currently **forward-only**: no version table, no down-migrations, no rollback.

Therefore every future migration must be:

- **additive**
- **idempotent**
- **backward-compatible where possible**
- **non-destructive** — no `DROP`, no destructive rename, no silent data loss

**Historical data must remain intact.** Deprecate by disuse: a superseded column stops being written and read; it is not removed. Where a free-text field gains a relationship, dual-write during transition.

---

## 18. Testing principle

Every phase tests all applicable levels: **1 Static · 2 Unit · 3 Database · 4 API (where applicable) · 5 UI · 6 Workflow · 7 Negative · 8 Security · 9 Cross-module regression · 10 End-to-end.**

- **Production database testing must use MySQL/MariaDB.** SQLite is supplementary.
- Every change affecting `lib/ops.php`, `lib/access.php`, `lib/licence.php`, `lib/db.php`, `config.php`, the dashboard, shared masters or shared identity **must trigger the relevant full regression suite**.
- **Operations is a protected module.**
- **Mandatory scenario S-1:** a NEW tenant with Operations + Reporting and Recruitment **not** subscribed — verify Operations works normally and Recruitment remains inaccessible at every execution path.
- A phase may never be marked COMPLETED on a quoted test figure. Numbers are **re-measured and restated** each time.

---

## 19. Dashboard protection

The existing dashboard is a **protected asset**. Do not redesign or replace it.

Permitted: **REUSE · EXTEND · CONFIGURE · CONNECT**. Dashboard extension only where a phase genuinely requires it. Section ordering may move into the workspace configuration layer that already exists, rather than being rebuilt.

---

## 20. Reuse / Extend / Connect rule

Preference order, always:

```
REUSE → EXTEND → CONNECT → MAP → MIGRATE → DEPRECATE → (only then) BUILD
```

A `BUILD` decision requires explicit written justification that no existing engine fits. Duplicate engines, masters, identities, organisations, approval chains, pipelines and KPI engines are forbidden.

**Protected assets:** Operations engine · Quality engine · Money engine · Reporting/IDEMS · existing Dashboard · TAPI KPI engine · existing identity mechanisms · existing Marketplace engines · per-tenant database isolation · the no-deletion-on-deactivation behaviour.

---

## 21. Deferred architecture debts (documented, deliberately NOT implemented)

Class **C / D**. Recorded so they are not rediscovered as surprises — **not** scheduled work.

- Quality bundled inside Operations (**observation only** — architecture lock §6)
- Five parallel audit trails
- Five billing document shapes
- Four field/template metadata stores
- Three near-identical criteria engines
- Flat-vs-graph taxonomy duplication inside Marketplace
- Project / Engagement as a first-class entity
- Test-harness isolation (444 files share one mutating database)
- `tenants.php` storing tenant database credentials in plaintext (file mode 0600; future security review)

---

## 22. Phase roadmap (locked)

```
PHASE 0   Audit + Architecture Lock                          ← COMPLETE, awaiting approval
PHASE 1   SaaS Entitlement & Module Boundary
PHASE 2   Recruitment Structural Foundation
PHASE 3   Hiring Request → Approval → Recruiter Assignment
PHASE 4   Multi-Source Fulfilment
PHASE 5   Recruitment KPI / SLA
PHASE 6   Person / Organisation / Marketplace Convergence
PHASE 7   UX Consolidation + Final E2E
```

**No Phase 1A / 1B / 1C as independent major phases.** Subtasks are permitted inside a phase; the programme stays operationally simple. Detail: `11-implementation-roadmap.md`.

### Core business flows locked

**Corporate HR** — Requestor → Hiring Request → Job Profile/Description → Approval → Recruiter Assignment → Sourcing → CV Intake → Screening → Shortlisting → Interview → Selection → Offer → Appointment → Joining → Confirmation → Closure. *Career Page OPTIONAL; manual/email/referral/agency/recruiter intake must work without it.*

**Agency** — Client → Client Requirement → Account Manager → Recruiter → Sourcing → Screening → Client Submission → Client Interview/Feedback → Selection → Placement/Joining → Commercial Outcome.

**TPIA** — Client/Project/Workforce Need → Requirement → Fulfilment Plan → Internal/Direct/Agency/Supplier/Marketplace → Screening/Verification → Client Approval → Selection → Mobilisation → Deployment → Operations → Quality → Reporting → Money.

### Enter once → use everywhere
No phase may require re-entry of information the platform already holds — departments, designations, organisations, people, candidates, recruiters, suppliers, clients, job profiles, taxonomy, documents. Where structures differ, **CONNECT/MAP** them.

---

## 23. Explicitly forbidden changes

During all future implementation it is **forbidden** to:

1. rebuild Operations
2. rebuild Quality
3. split Quality from Operations without a separate future approval
4. rebuild the Dashboard
5. build a second KPI engine
6. build a second identity engine
7. merge candidate and person tables
8. merge recruitment and marketplace requirement tables
9. delete historical records because a module is deactivated
10. force Career Page usage
11. create duplicate department/designation masters unnecessarily
12. create duplicate organisation records unnecessarily
13. create a second approval engine
14. create a second pipeline engine
15. implement every Phase-0 technical debt immediately
16. change unrelated modules without dependency justification

**A Phase-0 finding is not authorisation to act.** Only **Class A** items inside an authorised phase may be implemented.

---

## 24. Architecture approval status

| Item | Status |
|---|---|
| Phase-0 audit package (docs 00–14) | **Substantially accepted** |
| Correction pass — production database terminology | **Applied** |
| Correction pass — Operations + Quality = KEEP/PROTECT | **Applied** |
| Correction pass — findings are not automatic scope (A/B/C/D) | **Applied** |
| Correction pass — phase structure consolidated to Phase 0–7 | **Applied** |
| Application code changed in this pass | **NONE** |
| Phase 1 | **NOT STARTED — awaiting approval** |

**PHASE 0 ARCHITECTURE LOCKED — READY FOR PHASE 1**

*This record is authoritative. Any later document, comment or instruction that contradicts it must be reconciled against this file before implementation proceeds.*
