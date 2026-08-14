# MGH Recruitment & Workforce — existing-system baseline & enhancement plan

**Purpose.** This is the regression-protection reference required before any HR /
Recruitment / Manpower enhancement (per the enhancement directive §1, §2, §45,
§49). It records what already exists, what is partial, and what is genuinely
missing — so every change is *reuse → extend → build*, never *replace*, and so
we can prove nothing broke.

**Governing rule: ZERO-BREAK.** Existing Operations, CRM, scheduling, inspection,
deputation, attendance, expense, billing, costing, competence, auth/roles/audit
and AI must keep working before, during and after. Additive & backward-compatible
only; nullable columns, no destructive migrations, no renamed tables/fields.

---

## 1. Existing-system baseline (what is already here)

### 1.1 Screens & routes
| Area | Routes | View(s) | Handler |
|---|---|---|---|
| Requirements | `/requisitions`, `/requisition?id=`, `/requisition-new`, `/requisition-edit` | `requisition_list/detail/form.php` | `ops_requisitions()` |
| Candidates | `/candidates`, `/candidate?id=`, `/candidate-new`, `/candidate-edit`, `/candidate-stage`, `/candidate-cv`, `/candidate-client`, `/candidate-credential`, `/candidate-erase` | `candidate_list/detail/form.php` | `ops_candidates()` |
| Agencies | (masters) | — | `agency_get()` + masters |
| Sub-contractors | `/m/subcons`, `/m/subcon-rates` | masters | generic masters |
| Deputation | `/deputations`, `/job?id=` (panel) | `deputations.php`, `_deputation_panel.php` | `pdso_*` (lib/pdso.php) |
| Availability | `/availability` | `availability.php` | availability engine |
| Attendance | `/attend-mark`, `/attendance-recon` | `attendance_recon.php` | `ops_attend_action()`, `ops_attendance_recon()` |
| Timesheet | `/timesheet` | `timesheet.php` | `ops_timesheet()` |
| Work norms | `/work-norms` | — | `ops_work_norms()` |
| Back-office | `/m/back-office` | masters | masters |
| Competence | `/competence`, `/identity`, `/ratings` | competence/identity views | `lib/competence.php`, `lib/identity.php` |
| Costing | `/cost-run`, `/office-finance`, `/sbu-pl` | costing views | `lib/costing.php` |
| Billing | `/invoicing`, `/job-bill`, `/receivables` | books views | `lib/bills.php`, `lib/books.php` |

### 1.2 Data model (key tables — DO NOT rename/drop)
- **requisitions** (17 cols): `req_code, office_id, sbu, designation, project_site,
  req_type, outgoing_inspector_id, budgeted_cost, approved_by/ref/date, status,
  hired_inspector_id, notes`.
- **candidates** (41 cols): identity, `client_id, call_id, requisition_id,
  trade_id, skill_id, designation, source, agency, proposed_site, sbu,
  experience_years, expected_rate, rate_type`, **CV+AI** (`cv_link, cv_text,
  cv_keywords, cv_file_name, cv_analyzed_at`), **pipeline** (`stage, decided_at`),
  **conversion** (`inspector_id`), **client submission** (`submitted_client_date,
  client_feedback, client_feedback_date, client_feedback_note`), **interview**
  (`interview_required, interview_date, interview_done_date, interview_outcome`),
  `credential_requested`.
- **candidate_events** (7): stage/history trail.
- **agencies** (16): `agency_type, contact, gstin, contract_start/end,
  one_time_fee, monthly_rate, guarantee_days`.
- **subcons / subcon_rates**: sub-contractor pool + rate matrix.
- **inspectors** (32) + **inspector_certs** (16) + **inspector_allowances** +
  **authorisations** + **qualifications** + **witness_assessments** — the
  competence/certification spine.
- **Deputation**: `dep_status_events, dep_checklist, dep_site_log, dep_timesheet,
  dep_att_approval, dep_manpower, dep_site_history` — mobilisation + site ops.
- **Workforce time/cost**: `attendance, inspector_day_status, holidays,
  work_norms, expenses, vouchers, job_bills`.
- **Compliance/privacy**: `person_documents, person_document_access`.

### 1.3 Master pipelines / lookups (already configurable)
- **CAND_STAGES** (rendered via `lk_options_or('candidate_stage', …)`): CV received
  → Submitted → Shortlisted → Interview → Offered → (declined/hold/rejected) →
  **Accepted (Hired)** → Withdrawn.
- **CAND_SOURCES**: Own employee · Freelancer · Sub-contractor · HR/Manpower agency.

### 1.4 Existing intelligence & integration (reuse — do not rebuild)
- **AI** (`lib/ai.php`: `ai_enabled, ai_chat`, provider config) — and candidate
  **CV extraction is already wired** (`cv_text/cv_keywords/cv_analyzed_at`).
- **Mobilisation readiness**: `pdso_mob_readiness()`; **manpower gap**:
  `pdso_manpower_gap()`; **document/compliance gating**: `pdso_checklist()`.
- **Availability engine** (Operations) — reuse for "who is free" queries.
- **Costing engine** (`costing.php`): person split, cost runs, office/SBU cost mix,
  real-vs-standard cost, revenue/headcount.
- **CRM master**: leads / opportunities / quotations keyed by `client_id` — the
  single client relationship layer (no second client master).

### 1.5 The lifecycle that already works end-to-end
```
CRM (client / lead / opportunity / quotation)
  → Requisition (req_code, office, designation, budgeted_cost, approval)
  → Candidate (CV + AI, pipeline stage, agency/source)
  → Client submission → feedback → interview → decision
  → Accepted (Hired) → converted to Inspector (candidates.inspector_id)
  → Deputation / mobilisation (pdso: checklist, readiness, manpower, site log)
  → Attendance / timesheet → Expenses / vouchers / job bills
  → Costing (person split, cost run) → Billing (job bill → invoice → receipt)
```

---

## 2. Gap analysis vs the enhancement directive (52 points)

Legend: **✅ exists (reuse)** · **🟡 partial (extend)** · **⬜ missing (build)**

| # | Capability | State | Note |
|---|---|---|---|
| §7 | Requirement fields (client/contact/project refs, quantity, discipline/category, skills/quals, deployment block, billing rate/margin/floor) | 🟡 | Has office/sbu/designation/site/budgeted_cost/approval; enrich additively |
| §8/§35 | Simple + Advanced (progressive disclosure) | ⬜ | Build as form behaviour |
| §9 | Person → Talent → multiple Applications | 🟡 | Candidate is per-record with one `requisition_id`; add optional person link, no destructive migration |
| §10 | Candidate 360 (tabbed) | 🟡 | `candidate_detail` rich already; reorganise into tabs + timeline |
| §11 | Duplicate detection (mobile/email/name) | ⬜ | Build on create/import |
| §12 | Ownership / already-submitted-to-client warning | 🟡 | Source/agency/submission dates exist; add the warning |
| §13 | Configurable pipeline **templates** (per mode) | 🟡 | One configurable list today; add named templates |
| §14 | AI CV intelligence | ✅ | Wired; enhance extraction quality/UX |
| §15 | AI requirement extraction (paste email/JD) | ⬜ | Build on `ai_chat` |
| §16 | Explainable Workforce Fit score | ⬜ | Build (deterministic + explainable) |
| §17 | Deployment Readiness score | 🟡 | `pdso_mob_readiness` exists; surface + aggregate docs/medical/gate-pass |
| §18 | Requirement Health score | ⬜ | Build (vacancies vs pipeline vs time-to-mobilise) |
| §19 | Workforce availability reuse | ✅ | Availability engine; surface "existing resources may fulfil" |
| §20 | Assignment / deputation lifecycle | ✅ | `pdso_*`; reuse & enhance |
| §21 | Deputation extension intelligence (ending ≤30d) | 🟡 | End dates + cross-office tracker exist; add extension flow |
| §22–24 | Costing estimate → approved → actual → variance + margin | 🟡 | `budgeted_cost`, `callprofit`, costing engine; add per-assignment estimate/actual/variance |
| §25 | Billing readiness packet | 🟡 | Attendance gates close; `bills.php`; add "billing ready" summary |
| §26 | TPIA mode | ✅ | Native flow + industry packs |
| §27 | Manpower mode | 🟡 | Config toggle + worker/shift/replacement emphasis |
| §28 | CRM integration (single client master) | ✅ | `client_id` everywhere |
| §29 | Agency management (fee/guarantee/replacement) | ✅ | `agencies`; enhance replacement tracking |
| §30–41 | World-class UI/UX, Command Centre, global search, AI UX | 🟡 | Global search exists; **Command Centre missing**; detail-page tabs partial |
| §42 | Performance | ✅ | Keep queries lean; async AI |
| §43 | Security / privacy (candidate PII) | ✅ | Roles/audit/`person_document_access` exist; apply least-privilege |
| §44 | Safe migration | — | Nullable additions only |

**Headline:** the spine is solid and TPIA-native. The real value to add is
**(a) a Recruitment & Workforce Command Centre**, **(b) requirement + candidate
enrichment with Simple/Advanced modes**, **(c) an intelligence layer** (duplicate
detection, fit score, requirement health, deployment readiness, AI requirement
extraction), and **(d) assignment-level commercials** (estimate→actual→margin,
billing readiness) — every item additive.

---

## 3. Phased, zero-break roadmap

Order follows the directive's priority: **STABILITY → REUSE → ENHANCEMENT →
INTELLIGENCE → WORLD-CLASS UX**. Each phase is independently shippable, additive,
and ends with a regression pass against §45.

- **Phase 0 — Baseline (this document).** No code.
- **Phase 1 — Recruitment & Workforce Command Centre.** New read-only `/recruitment`
  home reusing existing data: Today (requirements needing action, follow-ups,
  interviews, joinings, deployments, deputations expiring, missing documents,
  billing blockers), Risks, Opportunities (existing workforce/dormant candidates
  match, upcoming availability, extensions). **Zero schema change, zero risk.**
- **Phase 2 — Requirement enrichment + Simple/Advanced.** Additive nullable columns
  on `requisitions` (quantity, discipline/category, skills, qualification,
  experience, deployment block, billing_rate, target_margin, negotiation_floor,
  structured client/contact/project refs). Progressive-disclosure form; old
  requisitions still valid.
- **Phase 3 — Candidate 360 + duplicate & submission protection.** Tabbed 360 +
  timeline over the existing candidate; duplicate detection (mobile/email/name)
  on create/import; "already submitted to this client" warning. Additive.
- **Phase 4 — Intelligence.** Explainable Workforce Fit score; Requirement Health;
  Deployment Readiness (wrap `pdso_mob_readiness` + compliance docs); AI
  requirement extraction from pasted text; AI CV enhancements. Computed/additive;
  AI always human-approved.
- **Phase 5 — Commercials.** Per-assignment estimate → approved → actual cost +
  cost/margin variance (extend costing); billing-readiness packet handed to the
  existing billing/finance flow.
- **Phase 6 — Manpower mode, Person/Talent multi-application, UX polish, full
  regression.** Manpower configuration; optional person link enabling multiple
  applications without duplicating candidates; consistent status/table/mobile UX;
  §45 regression sign-off.

---

## 4. Regression checklist (run before/after every phase — §45)
Existing must pass: **Login · Roles/permissions · CRM/clients/projects · Calls ·
Scheduling · Inspectors · Competence/certs · Attendance · Expenses · Deputation ·
Reports · Billing · Costing · existing Recruitment (requisitions/candidates) ·
Dashboards · AI · Audit · Notifications.** Verify record counts and relationships
unchanged; verify the end-to-end lifecycle in §1.5 still completes.

---

## 5. Unrelated findings (parked — do NOT fix in this program unless required, §46)
- (none recorded yet — log here as discovered)
