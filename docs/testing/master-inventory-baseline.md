# Master Inventory — Baseline (reference)

A **known-good baseline** of the application's modules, roles, statuses and
cross-cutting subsystems, drawn from the codebase. Use it to **validate what
Prompt 1 discovers**: anything here that Prompt 1 misses is a discovery gap and a
possible coverage risk; anything Prompt 1 finds that is *not* here should be added.

> The **code is the source of truth** (`lib/access.php`, `lib/ops.php`, the view
> files, the `CREATE TABLE`/`ensure_column` statements). This baseline is a checklist
> to test the discovery against, not a substitute for reading the code. Treat any
> item as **`⚠ confirm in code`** if the AI cannot evidence it.

---

## Navigation areas (the top-level menu)

`Sales` · `Quality` · `Reporting` · `Money` · `Insights` · `Directory` · `Admin`
· `Operations` (Operations has its own richer home).

## Modules (from `ACCESS_MODULES`) + owning area

| Module code | Name | Area |
|---|---|---|
| `leads` | Leads & pipeline | Sales |
| `inquiries` | CRM — Inquiries | Sales |
| `quotes` | CRM — Quotations | Sales |
| `crm_orders` | CRM — Orders / contracts | Sales |
| `crm_reports` | CRM — Sales reports | Sales / Insights |
| `idems` | Inspection reports (IDEMS) | Reporting |
| `calls` | Calls (work orders) | Operations |
| `jobs` | Jobs (deputations) | Operations |
| `vouchers` | Vouchers / expenses | Money |
| `invoicing` | Invoicing | Money |
| `profitability` | Profitability | Money / Insights |
| `overheads` | Overheads (office finance) | Admin |
| `reconcile` | Attendance reconcile | Operations / Money |
| `hiring` | Recruitment / candidates | Operations |
| `clients` | Clients | Directory |
| `vendors` | Vendors | Directory |
| `equipment` | Equipment & calibration | Quality |
| `competence` | Competence & authorisation | Quality |
| `impartiality` | Impartiality & conflicts | Quality |
| `identity` | Identity documents (site access) | Quality |
| `complaints` | Complaints & appeals | Quality |
| `ncr` | Nonconformities (NCR) | Quality |
| `capa` | Corrective actions (CAPA) | Quality |
| `audits` | Internal audits & management review | Quality |
| `confidentiality` | Confidentiality (§4.2) | Quality |
| `datacontrol` | Data & information control | Quality / Admin |
| `portal` | Client portal (external sign-in) | Directory / external |
| `masters` | Masters (lookups & dependent lists) | Admin |
| `reports` | Dashboards / reports | Insights |
| `users` | Users & access | Admin |
| `settings` | Settings | Admin |

**Not in `ACCESS_MODULES` but present as subsystems/screens — confirm & document:**
Vendor portal (`cvp`), Deputation & Site Operations (PDSO), Hold/Witness points,
Service-scope engine & report-formats-by-service, Terminology/vocabulary engine,
Seat/module **Licensing**, **Analytics & performance (TAPI)** hub, Company profile,
Approver mapping & approval rules, IRN numbering rules, Vetting checklist & gate,
Pre-order review checklist, Client holds, Books bridge / receiver.

---

## Roles (`ORG_ROLES`) + external roles

Internal: `MASTER_ADMIN`, `BUSINESS_DIRECTOR`, `SBU_HEAD`, `BRANCH_MANAGER`,
`BRANCH_APP_MANAGER`, `OPERATION_MANAGER`, `ASST_MANAGER`, `COORDINATOR`,
`BUSINESS_DEV_MANAGER`, `KEY_ACCOUNTS_MANAGER`, `MARKETING_MANAGER`,
`MARKETING_EXECUTIVE`, `FINANCE`, **`SR_INSPECTOR` (Senior Inspector)**,
`INSPECTOR`, `ADMIN` (legacy).

External: **client-portal** users (roles incl. quality/technical who accept reports,
and commercial/purchasing who do not) and **vendor-portal** users.

Each role carries **module view/edit rights** (`module_defaults`), **permission set**
(`role_defaults`/`PERMISSIONS`), and **data scope** (offices = ALL/OWN/list;
business-units = ALL/OWN/list). The role × module × scope matrix is a mandatory test
artefact — including the **negatives** (what each role must not see or do).

---

## Key lifecycles / statuses

- **Inspection report** (`IDEMS_STATUS`): `DRAFT → SUBMITTED → VETTING →
  UNDER_REVIEW → APPROVED → ISSUED`, with `REJECTED` (sent back) and `ARCHIVED`.
  Edit is locked once past DRAFT/REJECTED; ISSUED is immutable.
- **Vetting** (`IDEMS_VET_STATUS`): `VETTED`, `RETURNED`, `DEBRIEFED`. Gate (optional
  setting) routes a submitted report to the vetting authority first, then auto-
  forwards to the approver on VETTED.
- **Approval chain**: per-report steps (`report_approvals`) — PENDING → APPROVED /
  REJECTED / SENTBACK; resolved by rule, inspector→approver map, or a report-level
  pick; approver may be manager / coordinator / inspector / **senior inspector**.
- **Result / release status** (`IDEMS_RESULTS`): ACCEPTED, ACCEPTED_COND, REJECTED,
  HOLD, NA. **Release Note** gated by: marked-to-issue flag, open hold/witness
  points, open deviations/NCRs, client acceptance; RN skips vetting.
- **Quotation** (`QUOTE_STATUS`) → order/contract (contract number, expiry, quantity
  exhaustion gates).
- **Call** → **Job/deputation** (allocation, multi-date, per-day closure).
- **NCR**: OPEN → … → CLOSED (feeds CAPA & release eligibility). **CAPA**: many
  actions/owners/dates → verification → close. **Complaint/appeal** lifecycle.
- **Deputation (PDSO)**: mobilisation/site-ops statuses. **Invoice**: only after job
  close (finance/admin override). **Candidate/recruitment** pipeline. **Licence/seat**
  state.

---

## Report-type catalogue (IDEMS)

Ready-to-fill (have a designed form): Inspection Report (`IR`), Release Note (`RN`),
Vendor Audit (`VAR`), Vendor Assessment (`VASR`), Expediting (`ER`), Fire
Extinguisher Inspection (`FEXT`), the universal families (UIR_*/URD_*/UVA_*/UAUD_*/
PDSO_*), and the project-site **progress reports** Daily/Weekly/Fortnightly/Monthly
(`DPR`/`WPR`/`FNR`/`MPGR`). Plus the seeded catalogue of other TPIA type codes
(some form-less; the Report-types screen shows "ready vs no form yet" and offers a
reversible "hide empty types"). Test: the **report builder** (no-code form design),
**auto-design from an uploaded template**, **company Word format vs system PDF**, and
type curation.

---

## Cross-cutting subsystems (must each be tested as a dimension, not skipped)

1. **IRN numbering engine** — configurable token pattern, per-office override, zero-
   duplicate serial scoping.
2. **Audit trail & tamper-evident hash chain** — every change logged; chain verifies;
   detects tamper.
3. **Automatic signatures** — set once, stamped on every issued PDF; inspector &
   approver.
4. **Controlled timestamps** — server time is truth; device clock recorded but never
   trusted.
5. **Geo-tagged / EXIF evidence integrity** — photo location/time from the file;
   browser location kept separately; not spoofable.
6. **Smart photo & evidence** — compression, dedupe, captions, PDF embedding (any
   format → placeholder if unrenderable), supporting-document gallery.
7. **Terminology / vocabulary engine** — rename any business noun; it flows to every
   screen and output; industry packs; neutral defaults; **no hardcoded agency name**.
8. **Seat & module licensing** — seat prices/limits, module licence gates (a disabled
   module says no everywhere).
9. **Offline-first / mobile PWA** — service worker, manifest, offline capture + sync.
10. **AI-assist** — QAP→scope extract, voice dictation + polish/translate, smart
    remarks, auto Release Note draft, deterministic QA auditor. Must work on/off and
    never alter facts/numbers/dispositions.
11. **Notifications / email** — assignment, approval, overdue/SLA escalation, MIS
    digest, client "report issued", vetting-required; correct recipients only.
12. **Service-scope engine & report-formats-by-service** — which services are offered
    where, and the format each allocates.
13. **Hold / witness points** — derived from report dispositions; open points gate
    release.
14. **KPI / analytics (TAPI)** — KPI master, formula engine, role dashboards, drill-
    down, targets/scorecard/alerts, data-quality.
15. **Recruitment / workforce** — command centre, fit/health/readiness, commercials
    (estimate→approved→actual, variance/margin, billing packet), manpower modes.
16. **Deputation & site operations (PDSO)** — site-ops layer over a job.
17. **Confidentiality spine** — undertakings, client NDAs, breach register, file
    authorisation.
18. **Compliance registers** — equipment/calibration, competence/authorisation,
    impartiality/conflicts, identity documents, complaints/appeals, internal audit &
    management review, data & information control.
19. **Import / migration** — Excel/CSV user register building the org hierarchy;
    partner import; malformed-row handling.
20. **Pre-order controls** — blocked/on-hold client gate; configurable pre-order
    review checklist on quotations.
21. **Dashboards & pending tasks** — every role dashboard shows that role's pending
    tasks in list form (to vet / approve / fix / issue / release).

---

## Known configurable settings that change behaviour (test each on & off)

IRN format / company code / serial width · terminology overrides & industry pack ·
SLA/TAT targets · AI provider on/off · **vetting gate** (require vetting before
approval) · **vetting checklist** (+ require-all) · **pre-order checklist** (+
require-all) · **client-acceptance-required for release** · report-blank-fill ("NA")
· weekly working days / daily-hour caps · seat prices/limits & module licences ·
office/branch cost model & overheads · Office365/SMTP mail · release-note disclaimer
& label · report document-control identity (format/doc/revision numbers).

---

## Cross-module spine (the seams Prompt 4 must prove)

`Lead → Quotation (pre-order checklist, blocked-client) → Order/Contract → Call →
Job/Deputation (allocate) → Site execution (geo, photos, QAP autofill, attendance) →
Inspection Report → Completeness gate → Submit (locks) → Vetting → Approval → Issue
(auto-signature, immutable, QR) → Release Note (gated) → Client portal accept/reject
→ NCR → CAPA → Invoice (after close) → Profitability / Reconciliation → Dashboards`.

Compliance backbone crosses all of it: equipment/calibration, competence, impartiality,
identity, confidentiality, audit trail & immutability.

---

## Use this baseline as a scorecard

For each item above, record against Prompt 1's output: **Found? (Y/N) · Screen/Route
· Notes**. Every `N` is a discovery gap to resolve before locking the inventory.
