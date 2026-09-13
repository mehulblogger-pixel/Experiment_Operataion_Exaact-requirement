# 03 — MODULE AUDITS
Covers deliverables **4, 5, 6, 7, 8, 9, 10, 11**.

For each module: subscription/entitlement dependency, database footprint, and the cross-module couplings that matter.

---

## 1. Operations audit (deliverable 4)
- **Entitlement:** `operations` (`PRODUCT_MODULES`, `lib/licence.php:29`). Covers features `calls, jobs, reconcile, vouchers, equipment, competence, impartiality, identity, complaints, ncr, capa, audits, datacontrol, confidentiality, overheads`.
- **Tables:** 63. Core: `calls, jobs, expenses, attendance, vouchers, inspectors, offices, engagements, dep_*` (deployment), `job_visits, service_catalog, service_scope, sla_targets, job_delays, recurring_services`.
- **Principal libs:** `ops.php` (8,285 ln), `tosrm.php` (2,589), `pdso.php`, `attend.php`, `sched.php`.
- **Note:** the `operations` feature list **also owns quality features** (`equipment, competence, impartiality, ncr, capa, audits, datacontrol, confidentiality`). So "Quality" is commercially inside Operations while being *separately* gated by accreditation-pack settings — a double gate with no single owner. See §5.
- **Owns the operations SLA engine** (`sla_targets`, `TOSRM_SLA_STAGES`, `tosrm_sla_eval()` `lib/tosrm.php:1467-1620`) — WITHIN / AT_RISK (80% of target) / BREACHED, hard-wired to `calls`/`jobs`/`report_docs`.
- **Status: HEALTHY — protect. No changes proposed.**

## 2. Recruitment audit (deliverable 5)
- **Entitlement:** `hr` → feature `hiring`.
- **Tables (22):** `requisitions, candidates, candidate_events, agencies` (in core schema, `lib/ops.php`), `requisition_groups`, `recruit_pipelines/_stages`, `candidate_stage_data`, `interviews, candidate_docs, interview_scores`, `salary_structures, hr_discussions, job_offers`, `recruit_approval_rules/_levels/_requests/_steps`, `positions`, `doc_templates`.
- **Configurable pipeline: YES** — `recruitpipe_for()`, conditional stages, three seeded templates (CORP18/SIMPLE/EXEC), editable at `/recruit-pipelines` (`lib/recruitpipe.php:47-358`).
- **Offer lifecycle is properly enforced:** DRAFT → PENDING_APPROVAL → APPROVED → ISSUED → VIEWED → ACCEPTED/DECLINED/EXPIRED/WITHDRAWN, with `offer_issue()` blocking unapproved (`lib/recruit_offer.php:31,265`). Letters from `doc_templates` (OFFER, APPOINTMENT, CONFIRMATION, RELIEVING, INTERNSHIP).
- **Careers page uses the SAME candidate engine** as manual entry — same table, codes and events (`lib/careers.php:65-139`). The brief's §29 requirement is already satisfied.

### 2.1 Recruitment — what is ABSENT (the programme's real backlog)
| Brief requirement | Status | Evidence |
|---|---|---|
| Hiring Request distinct from requisition; a **Requestor** | **ABSENT** | no `requestor`/`raised_by` field; only `created_by` (`lib/ops.php:254`) |
| **Approval before sourcing** | **ABSENT in practice** | engine declares `REQUISITION` (`lib/recruit_approval.php:18`) but `appr_start()` is called from **exactly one place** — `offer_submit()` (`lib/recruit_offer.php:250-254`). The requisition branch of the completion callback is **dead code**, and it writes `'approved'`/`'on_hold'` — values **not in `REQ_STATUS`** (`:234-236`). Approval today = three free-text boxes (`approval_ref`, `approved_by`, `approval_date`) |
| Recruiter **assignment** | **PARTIAL** | `recruiter_id`/`manager_id` exist (`lib/recruit_cc.php:22-23`) but are a manual picker — no assignment event, no assigned-at date, no notification, no workload balancing |
| **Target dates / SLA / KPI** | **ABSENT** | no `target_date`/`fill_by`/`due_date` anywhere. `recruit_stages.sla_days` **is stored and editable but never read** (`lib/recruitpipe.php:76`). Only metric: avg time-to-hire (`created_at → decided_at`, `lib/recruit_cc.php:200-202`). No time-to-source, time-to-fill, stage ageing or SLA adherence |
| **Source party** (which agency supplied the CV) | **ABSENT** | `candidates.agency` is free-text VARCHAR(150) with **no FK** (`lib/ops.php:197`); `source_type` column exists but is **never written** (absent from the save list `lib/ops.php:5147-5151`); careers writes `source='CAREERS'`, a value **not in `CAND_SOURCES`** |
| Multi-source fulfilment | **ABSENT** | see `05-requirement-taxonomy-audit.md` §1.3 |
| Joining / probation / confirmation | **ABSENT** | joining is a single date on `job_offers`; "onboarding" = move to ACCEPTED + create an `inspectors` row (`lib/ops.php:5067-5093`) |
| Requisition lifecycle configurable | **ABSENT** | candidate pipeline is configurable; requisition status is a free dropdown (`views/ops/requisition_form.php:322`) |

**Two parallel stage systems** (legacy `CAND_STAGES` + configurable pipeline) are only **coarsely synced** — the legacy `stage` is touched solely at interview/offer milestones (`lib/recruitpipe.php:390-400`). This is a live consistency risk.

## 3. Marketplace / Connect audit (deliverable 6)
- **Entitlement: NONE.** Gated by settings `marketplace_addon` (**default `'1'` in cloud**) and `connect_enabled` (default `'1'`).
- **Tables: 65 — the largest module in the system.**
- **Strengths to reuse:** guarded status transitions (`CX_REQ_TRANSITIONS`, `cx_req_can_transition()` `lib/connect_market.php:21-42`); a versioned, aliased, **graph-backed occupational taxonomy** (~20 tables); the only persisted identity link ledger; escrow/fee/credit commercial rails (`mkt_*`, 15 tables).
- **Finding:** the best transition guard and the best taxonomy in the platform live in the module that cannot be sold.

## 4. Reporting / IDEMS audit (deliverable 7)
- **Entitlement:** `reporting` → feature `idems`.
- **Tables:** 41. `lib/idems.php` is the largest file in the system (10,942 ln).
- Has its own SLA escalation scanner (`idems_run_sla_escalations()`, `idems.php:6815`) and its own audit trail (`idems_audit`).
- **Bare `is_master()` appears 59× in this file** — the largest licence-bypass surface in the codebase (see `07-…` §2).
- **Status: HEALTHY — protect. Do not touch in this programme.**

## 5. Quality audit (deliverable 8)
- **Entitlement: NONE.** Gated by `accredited_pack_on()` → accreditation-pack settings, *and* commercially bundled inside the `operations` feature list.
- **Tables: 31** — `internal_audits, audit_findings, mgmt_reviews, capa(+_actions,_events), complaints, nonconformities, ncr_events, impartiality_*, confidentiality_*, controlled_docs, risk_items, retention_rules, satisfaction_surveys, data_consents, data_requests, security_incidents, …`
- `ops_module_gate()` **does** refuse accreditation registers when no pack is on (`lib/ops.php`, the `accredited_pack_on()` block) — so route protection exists; **entitlement** protection does not.
- **Finding:** Quality is a sellable product in the brief (Customer E) but has no module key, no price, no plan slot.

## 6. Money audit (deliverable 9)
- **Entitlement:** `money` → features `invoicing, profitability`.
- **Tables: 20** — `invoices/invoice_lines, receipts, receipt_allocations, credit_notes, job_bills, billable_*, billing_orders, cost_runs, cost_allocations, project_costings, tally_exports, books_outbox`.
- **Finding — billing documents are fragmented across five shapes:** `invoices/invoice_lines` (books) vs `job_bills` (bills) vs `billable_bills` (billable) vs `billing_orders` (billing) vs `mkt_orders` (marketplace). Same concept, five implementations.
- **Status: HEALTHY for its own purpose — protect. Fragmentation is noted, not scheduled.**

## 7. Workforce audit (deliverable 10)
There is **no `workforce` module**. Workforce is an emergent capability spread across:
- `inspectors` + `inspector_certs` + `competence` + `person_documents` (Operations),
- `cx_professionals` + `cx_bench` + `cx_client_bench` (Connect),
- `candidates` (Recruitment),
- `back_office_staff`, `subcons` (Operations).

**Finding:** "the workforce" is exactly the concept fragmented by F4. A single bench/resource view does not exist; `lib/connect_source.php:35-51` is the only place that ranks internal + marketplace + client-bench people together, and it works off `jobs`, not `requisitions`.

## 8. Dashboard audit (deliverable 11)
Eleven dashboard surfaces; main dashboard `views/dashboard.php` (541 ln). Widget sources, configurability and module-degradation behaviour are documented in `01-architecture-map.md` §5.

**Classification per the brief's required verbs:**

| Item | Ruling |
|---|---|
| Main dashboard (`views/dashboard.php`) | **REUSE** — protected asset, no redesign justified |
| Recruitment Command Centre | **EXTEND** — add SLA/ageing tiles once target dates exist |
| TAPI analytics engine | **REUSE + EXTEND** — register recruitment metrics; do NOT build a second KPI engine |
| Dashboard section order (hard-coded role switch) | **CONFIGURE** — candidate for the workspace_config layer that already exists |
| Compliance/money bands gated by `|| is_master()` | **REFACTOR** — replace with `is_master_of()` |
