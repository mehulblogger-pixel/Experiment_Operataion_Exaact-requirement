# EXAACT Recruitment & Selection — product plan (platform-native)

**Decision:** Recruitment & Selection is a **commercially independent product built
on the shared EXAACT platform**, not a separately coded application. One codebase,
many sellable products. A customer can buy *only* Recruitment and never see
Operations, Inspection, Sales CRM, Money or the Marketplace, while the same
person/candidate, workflow and data can later light up more EXAACT capabilities
with no migration.

> The earlier standalone `mgh-hire/` app is kept as a working UX reference/proof
> only. All platform work happens in `phpapp/`. Nothing is deleted.

---

## Governing principle

**Build once, sell many ways. Commercial separation ≠ technical duplication.**
Every change is additive, reversible, and must never delete data, screens,
permissions, lifecycles or APIs, nor break an existing install (per repo CLAUDE.md
and the client's master brief §1–§3).

---

## What already exists on the platform (audit, 2026-09) — REUSE, don't rebuild

| Capability the brief asks for | Platform reality | Verdict |
|---|---|---|
| Product entitlement / "sell one module" | `PRODUCT_MODULES` + `PRODUCT_PACKAGES`, enforced in the single choke point `can()`→`licence_blocks()` (`lib/licence.php`) | **REUSE** |
| Multi-tenancy (isolated customers) | Per-tenant separate databases (`lib/tenants.php`) | **REUSE** |
| Roles & permissions | `lib/access.php` (`ORG_ROLES`, `PERMISSIONS`, `ACCESS_MODULES`; `hiring` module exists) + per-tenant `role_access` overrides | **EXTEND** (add HR roles) |
| Configurable approval matrix + SLA + escalation | `lib/stagegate.php` (`stage_gates`) and `idems_approval_rules` (`lib/idems.php`) | **REUSE as pattern** |
| Email incl. **SMTP** (STARTTLS/SSL/AUTH) + outbox log | `ops_mail()`/`smtp_send()` + `email_log` (`lib/ops.php`) | **REUSE** (wire recruitment events in) |
| Masters / no-code lists + custom fields | `lib/lookups.php`, `lib/customforms.php` | **REUSE** |
| Offer-letter / one-pager templates (.docx token merge) | `report_templates` (`lib/idems.php`) | **REUSE** |
| PDF generation | `SimplePDF` (`lib/pdf.php`) | **REUSE** |
| CV read / skill match / duplicate detection | `lib/connect_cv.php`, `recruit_fit_score()`, `cand_find_duplicates()` | **REUSE** |
| Person spine (candidate → employee, no duplication) | `cx_identity_link` + `lib/connect_person.php` + `person_ref` | **REUSE** (the crown jewel — brief §16/§34 already solved) |
| Analytics (funnel, TTH, ageing, drop-off, recruiter P&L) | `recruitment_cc` (`lib/recruit_cc.php`) | **REUSE** |
| AI extraction seam | `lib/ai.php`, already wired via `recruit_ai_extract()` | **REUSE** |

Roughly **40–50% of the brief already exists in reusable form.**

## Genuine gaps to BUILD (additive)

1. **Configurable pipeline / stage engine** — today's candidate stages are
   hardcoded (`CAND_STAGES`); the brief (§9, §45–46) needs per-tenant, per-
   company pipelines with conditional stages. *Highest priority.*
2. **Position master, Org-chart, Manpower plan** (§12–14).
3. **Structured Offer + Onboarding entities** (§32–34) — reuse `report_templates`
   for the letter.
4. **Multi-round interviews + scorecards** (§22) — today single-round columns.
5. **Reference checks** (§29) and **Medical workflow** (§28) as first-class,
   with data-permission separation (§39).
6. **Salary-structure builder** (§25) — beyond a single CTC field.
7. **Candidate document set / DMS** (§23–24).
8. **Wire recruitment notifications** into `ops_mail()` (offer, interview,
   rejection, SLA) — transport already exists.

---

## Phased roadmap

- **Phase 0 — Audit & architecture** ✅ *(done)* — this document.
- **Phase 1 — Sell Recruitment on its own** ✅ *(this change)* — a new product
  package `RECRUITMENT_HR` isolates an in-house HR customer to Recruitment +
  Administration only (Operations, Inspection reporting, Sales CRM, Invoicing and
  the Marketplace all hidden). Verified; existing presets unaffected.
- **Phase 2 — Configurable pipeline engine** ✅ *(done)* — per-tenant
  `recruit_pipelines`/`recruit_stages` tables + admin screen (`recruit-pipelines`,
  `is_admin_level()`), seeded with the client's 18-stage template plus *Simple*
  and *Executive* templates. Resolver picks the applicable pipeline per
  requisition; conditional stages skip by rule (L2 for senior grades only; medical
  only when required). `lib/recruitpipe.php` + `views/ops/recruit_pipelines.php`;
  wired into boot; 18 engine assertions + full suite green.
- **Phase 2b — Candidate driven by the configured pipeline** ✅ *(done)* — the
  candidate screen leads with the resolved workflow as its primary tracker
  (done/current/upcoming) with Advance/Back/jump controls. Position stored
  additively in `candidates.pipeline_id`/`pipeline_stage_id`; every move audited
  to `candidate_events`; legacy `candidates.stage` kept in coarse sync only at
  the interview/offer milestones (never over a terminal stage, so the explicit
  Hire → create-inspector action is never triggered as a side-effect). New
  `candidate-flow` route gated `is_coordinator_level()`; panel injected at the
  top of `candidate_detail`; requisition gains an additive `grade` column for
  seniority-based conditions. Legacy `CAND_STAGES` flow preserved. 28 pipeline
  assertions + full suite (6013) green.
- **Phase 3 — Position master, Org-chart, Manpower-plan validation** ✅ *(done)* —
  `lib/position.php`: `positions` table (code/name/department/BU/grade/level/
  reports-to/office/HOD/sanctioned·occupied·budgeted headcount) with a CRUD admin
  screen (`positions`) and an org-chart view (`positions-org`) built from the
  reporting lines. `requisitions.position_id` (additive) links an SRF to a
  position; the requisition detail shows a **manpower-plan validation** panel
  computing cases A–E (§14): A proceed / B escalate (full or short) / C new-
  position approval / D replacement / E over-budget → finance. Never a silent
  bypass. Routes gated `mod.hiring.view` (see) + `is_coordinator_level()`
  (manage) — no new permission. 15 assertions + full suite (6028) green.
- **Phase 4 — Interviews (multi-round + scorecards) & Document DMS** ✅ *(done)* —
  `lib/recruit_iv.php`: `interviews` table (many rounds per candidate; L1/L2/L3/
  HR/Technical/Management/Panel/Client/Practical/Assessment) with a scorecard
  (competencies, rating, recommendation, result PASS/FAIL/HOLD/RE_INTERVIEW/
  NO_SHOW/CANCELLED); `candidate_docs` table with a configurable type list and the
  full status lifecycle (NOT_REQUIRED→…→VERIFIED/REJECTED/RESUBMIT, derived
  EXPIRED). Two new tabs (Interviews, Documents) on the candidate screen; routes
  `candidate-interview`/`candidate-doc` gated `is_coordinator_level()`; sensitive
  documents (salary/medical/identity/PAN) are download-restricted to admin-level
  (§39). No new permission. 22 assertions + full suite (6050) green.
- **Phase 5 — Compensation/salary-structure, Offer (template + approval), HR
  discussion, Onboarding hand-off to the person spine.**
- **Phase 6 — Approvals (routed, per-tenant), SLA + escalation, notifications
  wired to `ops_mail()`.**
- **Phase 7 — Recruitment dashboards/reports hardening, careers intake, exports.**

Each phase updates `docs/01-roles.md`, `docs/02-permission-matrix.md` and
`docs/03-object-lifecycles.md` in the same commit as its code, per CLAUDE.md.

---

## Client template (configured, never hardcoded — brief §45)

`Corporate Recruitment Workflow`: Staff Requisition → Organogram Verification →
Candidate Sourcing → CV Screening → HOD Shortlisting → L1 → L2 → Document
Collection → Salary Structure → HR Discussion → Salary Acceptance → Medical
Examination → Reference Verification → Medical Fitness Clearance → One-Pager
Approval → Offer Letter → Offer Acceptance → Onboarding.

Other companies (Technical Staffing, Simple, Executive) configure different
pipelines on the **same** engine.

---

## Phase 1 change record (this commit)

`lib/licence.php`:
- Added product package **`RECRUITMENT_HR`** ("EXAACT Recruitment — In-house HR"):
  `off = operations, reporting, sales, money`; `connect = 0` (marketplace off);
  leaving `hr` + core `admin` on.
- Extended `product_package_apply()` and `product_package_matches()` to honour an
  **optional** `connect` key. Presets without the key (TPIA, STAFFING, RECRUITMENT,
  ENTERPRISE) are unchanged — the marketplace switch is only touched when a preset
  explicitly declares it. No permissions, statuses or transitions were added, so
  the permission matrix and object lifecycles are unchanged.
- Applied per-tenant on the existing **Product package** chooser screen
  (Settings → Product package); fully reversible by choosing another package.
