# 06 — DUPLICATE AUDIT & REUSE/EXTEND/CONNECT/MERGE/MAP/DEPRECATE/BUILD MATRIX
Covers deliverables **16, 23**.

**Governing rule from the brief:** prefer `REUSE → EXTEND → CONNECT → MIGRATE → DEPRECATE`. Do **not** physically merge database tables without technical justification.

---

## 1. Duplicate audit — measured

### 1.1 Duplicate PERSON records — see `04-identity-organisation-audit.md`
Eleven human tables; three unreconciled identity mechanisms; five tables with **no uniqueness constraint at all**. One human can legitimately occupy seven rows.

### 1.2 Duplicate DEMAND records
`requisitions` vs `cx_requirements`; `candidates` vs `cx_applications`; `requisition_groups.headcount` vs `cx_positions.quantity`. **Zero schema references between them.** Full table in `05-requirement-taxonomy-audit.md` §1.1.

### 1.3 Duplicate ORGANISATION records
`business_partners` (client/vendor) vs `cx_organisations` (marketplace) vs `agencies` (supplier). No links.

### 1.4 A table declared twice — **live bug risk**
**`inspector_day_status`** is created in two files with **divergent definitions**:
- `lib/ops.php:503-505` — `status DEFAULT 'AVAILABLE'`, `set_by VARCHAR(150)`
- `lib/attend.php:44-46` — `status DEFAULT ''`, `set_by VARCHAR(120)`

Both use `IF NOT EXISTS`, so **whichever migration runs first wins**. `ops_migrate()` precedes `attend_migrate()` in `run_schema()` (`lib/db.php:417` vs `:433`), so the Ops shape survives today. **If that order ever changes, behaviour changes silently.** This is a defect to fix regardless of the Recruitment programme.

### 1.5 Other duplicated engines (measured)
| Concept | Implementations |
|---|---|
| Taxonomy | flat `cx_sectors/cx_disciplines/cx_equipment_types/…` **and** graph `cx_tax_nodes/_edges/_aliases` — `db.php:462` states the graph is "built from the flat masters" |
| Criteria engines | `inspection_criteria(+_packs)` / `audit_criteria(+_packs)` / `assessment_criteria(+_packs)` — three near-identical shapes (uire / uvaae / uvae) |
| Pipeline engines | `pipelines`+`pipeline_stages`+`lead_stage_history` (sales) vs `recruit_pipelines`+`recruit_stages`+`candidate_stage_data` (recruitment) |
| Outbox queues | `integration_outbox`, `books_outbox`, `ads_outbox` — same store-and-forward concept, three tables |
| Field/template metadata | `custom_fields/custom_values`, `report_fields/report_sections`, `report_lib_*` (urfe), `form_field_layout`+`custom_forms` — **four** places |
| Billing documents | `invoices/invoice_lines`, `job_bills`, `billable_bills`, `billing_orders`, `mkt_orders` — **five** |
| Audit trails | `idems_audit`, `activities`, `vendor_audit`, `portal_audit`, `evidence_chain` — **five** |
| Consent | `data_consents`, `disclosure_consents`, `cx_pro_contact_reveals` — **three** |
| Disputes | `cx_disputes` vs `cx_rating_disputes` |
| Ops/Connect mirrors | `engagements`↔`cx_engagements`; `positions`↔`cx_positions` |
| Stage vocabularies (within recruitment) | legacy `CAND_STAGES` **and** configurable `recruit_stages`, coarsely synced |
| SLA engines | `tosrm` operations SLA vs `recruit_approval` approval SLA vs `idems` escalations vs `attendreview` — **four**, no shared ageing helper |

---

## 2. Decision matrix (deliverable 23)

**No physical table merge is proposed anywhere in this matrix.** Every convergence is by link, adapter or shared service.

| # | Duplicate pair | Decision | Justification |
|---|---|---|---|
| D1 | Candidate vs Person | **CONNECT** | `candidates` is correctly a row *per application*, not per person. Link it to the spine via `inspectors`, extending the existing `candidates.inspector_id` + `person_ref`. Never merge. |
| D2 | Candidate vs Marketplace Professional | **CONNECT (already partly built)** | `cx_identity_link` + `candidate-link-pro` route exist. Extend coverage; keep "a relationship, never a merge". |
| D3 | Employee vs Inspector | **MAP** | `users.inspector_id` already exists and is uniqueness-enforced. Adopt as the standard. |
| D4 | Worker vs Resource vs Bench | **CONNECT** | `cx_bench`/`cx_client_bench` already point at `cx_professionals`; bridge that to the spine. |
| D5 | Client vs Organisation vs Agency | **CONNECT** | Add nullable cross-reference IDs between `business_partners`, `cx_organisations`, `agencies`. Do not merge — different lifecycles and permissions. |
| D6 | **`candidates.agency` free text → Agency** | **BUILD (small) — high value** | Add `candidates.agency_id` FK to `agencies`, backfill by name match, keep the text column. Unblocks the brief's §13 *Source Party*. |
| D7 | Requirement vs Requisition | **CONNECT, do NOT merge** | Two lifecycles, two audiences, 65-vs-22 table estates. Introduce a thin *fulfilment* link so a requisition can be sourced via marketplace. Precedent already exists: `lib/connect_source.php:35-51`. |
| D8 | Application vs Submission | **MAP** | Map `CAND_STAGES` ↔ `CX_APP_STATUSES` in one adapter; do not unify tables. |
| D9 | Job vs Position | **REUSE** | `positions` (org sanctioned role) and `jobs` (work order) are genuinely different. No action. |
| D10 | Job Profile vs Designation | **EXTEND** | Designation is a lookup; job profile is emerging (`recruit_jd`). Keep separate, link. |
| D11 | Recruiter vs User | **REUSE** | `recruiter_id` already references `users`. No action. |
| D12 | Bench vs Candidate Pool | **CONNECT** | `candpool_pro_matches()` already bridges; formalise. |
| D13 | Project vs Engagement | **DEFER** | No first-class project entity exists. Out of scope for this programme; record as debt. |
| D14 | Two pipeline engines (sales vs recruitment) | **REUSE separately** | Different domains, both working. No convergence justified. |
| D15 | Legacy `CAND_STAGES` vs configurable pipeline | **REFACTOR (recruitment-internal)** | Make the configurable pipeline the single source of truth; derive the legacy stage. Removes a live consistency risk. |
| D16 | **`inspector_day_status` declared twice** | **FIX NOW** | Divergent DDL, order-dependent. Reconcile to one definition. Independent of this programme. |
| D17 | Four SLA/ageing engines | **BUILD (one small shared helper) + REUSE** | Do not build a fifth. Extract a generic ageing/SLA helper and have recruitment consume it, per brief §20. |
| D18 | Five audit trails | **DEFER** | Large, risky, no programme dependency. Record as debt. |
| D19 | Five billing shapes | **DEFER** | Money module is healthy; no programme dependency. |
| D20 | Four field/template metadata stores | **DEFER** | No programme dependency. |
| D21 | Taxonomy: flat vs graph, Connect vs Recruitment | **CONNECT + EXTEND** | Recruitment should consume the Connect taxonomy for discipline/role rather than growing a third vocabulary. Phase-gated behind entitlement work. |
| D22 | **KPI engine** | **REUSE (mandatory)** | TAPI already provides `kpi_defs`, metric adapters, a safe formula parser, scorecards and alerts. Recruitment KPIs **must** be TAPI metrics. Building a second KPI engine is explicitly forbidden by brief §20. |
| D23 | **Approval engine** | **EXTEND, do not duplicate** | `recruit_approval` mechanism is generic (rule matching, multi-level, SLA, reminders, escalation, cron). Its *storage* is recruitment-named. Widen `APPR_ENTITIES` and actually invoke it for `REQUISITION`. |

### 2.1 Items that are NOT duplicates (explicitly cleared)
- `business_partners` handling both clients and vendors — a deliberate, working single-table design. Leave alone.
- `positions` vs `jobs` — different concepts.
- Portal / vendor / pro user tables being separate from `users` — a deliberate security boundary (`lib/portal.php:49-50`). **Do not merge.**
