# 05 — REQUIREMENT / REQUISITION AUDIT & TAXONOMY AUDIT
Covers deliverables **14, 15**.

---

## 1. Requirement / Requisition audit

### 1.1 Two demand records exist, and they do not know about each other

| Concept | Recruitment | Marketplace / Connect |
|---|---|---|
| Demand record | `requisitions` (`lib/ops.php:247-256`) | `cx_requirements` (`lib/connect_market.php:48-60`) |
| Headcount | `quantity` (`lib/recruit.php:44`) | `positions` (`lib/connect_market.php:56`) |
| Headcount breakdown | `requisition_groups.headcount` (`lib/recruit.php:111`) | `cx_positions.quantity` (`lib/connect_crew.php:15`) |
| Person put forward | `candidates` + `requisition_id` (`lib/ops.php:425`) | `cx_applications` (`lib/connect_market.php:61`) |
| Progress vocabulary | `CAND_STAGES` (`lib/ops.php:70`) | `CX_APP_STATUSES` (`lib/connect_market.php:29`) |
| Demand closed by fill | `REQ_STATUS='HIRED'` (`lib/ops.php:53`) | `CX_REQ_STATUSES='AWARDED'` (`lib/connect_market.php:21`) |
| Rate | `billing_rate` + `rate_basis` | `rate_min/max` + `rate_unit` |
| Rate offered by supplier | `candidates.expected_rate` | `cx_applications.proposed_rate` |

**Measured fact:** `grep requisition_id lib/connect_*.php` → **zero hits**. `grep cx_requirements\|cx_applications lib/recruit*.php lib/ops.php` → **zero hits**. Neither schema references the other.

**One deliberate half-bridge already exists:** `lib/connect_source.php:35-51` builds a *pseudo-requirement* array from a `jobs` row so the matcher can rank internal inspectors, marketplace professionals and client bench together. The team already recognised the duplication — but it works off `jobs`, never off `requisitions`.

### 1.2 Lifecycle maturity is asymmetric
- Marketplace **guards transitions**: `CX_REQ_TRANSITIONS` / `CX_APP_TRANSITIONS` + `cx_req_can_transition()` (`lib/connect_market.php:21-42`).
- Recruitment **does not**: `REQ_STATUS` is a flat list and status is set directly (e.g. `lib/ops.php:5091`); the requisition status is a free dropdown on the form (`views/ops/requisition_form.php:322`).

**Reuse opportunity:** the marketplace transition guard is the better engine and is already proven.

### 1.3 FINDING F5 — multi-source fulfilment does not exist (High)

The brief's worked example — *20 Welding Inspectors filled as 5 internal + 3 direct + 4 Supplier A + 3 Supplier B + 5 marketplace, as ONE requirement* — **cannot be represented today.**

Evidence:
- `sourcing_model` is a **single `VARCHAR(24)`** on the requisition (`lib/recruit.php:71`), values `OWN_PAYROLL | MANPOWER_AGENCY | SUBCON_AGENCY | FREELANCER` (`:26-31`). Its **only** behaviour is cost arithmetic in `req_cost_buildup()` (`:251-274`). It drives no routing, no allocation, no permission.
- `requisition_groups` splits headcount by **reporting contact / site**, not by source — no source column exists (`lib/recruit.php:111-116`).
- Source is recorded per **candidate**, not per allocation (`candidates.source`), and **nothing aggregates by it** — no `GROUP BY source` over candidates exists anywhere in the codebase.
- Fill is a source-blind count: `recruit_req_health()` computes `filled = COUNT(candidates WHERE stage IN ('OFFERED','ACCEPTED'))`, `vacancies = quantity - filled` (`lib/recruit.php:508-535`).

### 1.4 FINDING F5a — the first hire closes the whole requisition (High / behavioural bug)

`lib/ops.php:5090-5093`: accepting **one** candidate sets `hired_inspector_id = ?` and `status = 'HIRED'` on the requisition — **regardless of `quantity`**. `hired_inspector_id` is a single INT (`lib/ops.php:253`).

The marketplace has the same shape: `awarded_application_id` is one INT and `cx_requirement_award()` accepts exactly one application even when `positions > 1` (`lib/connect_market.php:57,318-330`).

**For a manpower-supply or staffing business this is a functional defect today, independent of the Recruitment programme.** A requisition for 20 people closes on the 1st placement.

The only many-rows-per-requirement allocation table in existence is `cx_bench_alloc` (`lib/connect_bench.php:33-38`) — an agency's private roster against a marketplace requirement, with **no quantity column**.

### 1.5 What is genuinely strong and must be preserved
- Rich commercial modelling on the requisition: `billing_rate`, `rate_basis`, `target_margin`, `negotiation_floor`, `expected_revenue/profit`, plus a full cost build-up (`lib/recruit.php:63-77`) and per-placement estimate/approved/actual via `assignment_commercials()` (`:858-900`).
- Client-side agency model: `client_id`, contact, `contract_ref`/`po_ref`/`quotation_ref`; client submission tracked on the candidate (`submitted_client_date`, `client_feedback`, `lib/ops.php:432-435`).
- Supplier fee + guarantee window (`agencies.one_time_fee/guarantee_days`, `inspectors.placement_fee/fee_status`).

---

## 2. Taxonomy audit

The brief requires three concepts be kept distinct. **Today they are conflated — this is the source of the "role vs designation vs department" confusion.**

### 2.1 The three concepts, as they exist

**(a) Organisation structure** — who *we* are
- `offices` (`lib/ops.php`), `positions` + `reports_to` (`lib/position.php:20`), `department` as a lookup master (`lk_ensure_type_map('department',…)`, `lib/lookups.php:54`), designation↔department link via `lookup_values.attr_department` (`lib/deptorg.php:20`), org chart `/positions-org`, department hub `/departments`.

**(b) Job / workforce taxonomy** — what the *work* is
- Recruitment side: `designation`, `discipline`, `category`, `skills`, `qualification`, `experience` — mostly **lookup values or free text on the requisition**.
- Connect side: a genuinely rich, versioned taxonomy — `cx_sectors`, `cx_disciplines`, `cx_job_families`, `cx_roles`, `cx_iti_trades`, `cx_qualification_levels`, `cx_prof_certifications`, `cx_equipment_types`, `cx_materials`, `cx_inspection_stages`, `cx_standards`, `cx_certifications_registry`, plus a **graph** layer `cx_tax_nodes/_edges/_aliases/_profile_tax` and `cx_taxonomy_versions`/`cx_qualtax_versions`.

**(c) User security role** — what a person may *do*
- `users.role` + the `can()` permission system (`lib/access.php`).

### 2.2 FINDING — the taxonomy is duplicated, and the good one is in the wrong module
Connect owns a **versioned, aliased, graph-backed** occupational taxonomy (≈20 tables). Recruitment ignores it entirely and uses flat `lookup_values` for `designation`/`discipline`. A requisition's "designation" is therefore an uncontrolled string relative to the marketplace's controlled vocabulary — so the same role cannot be matched across the two.

`db.php:462` states the graph is *"built from the flat masters"* — i.e. even inside Connect the taxonomy is stored twice by design-debt.

### 2.3 FINDING — "department" carries two different meanings
`RECRUIT_DEPARTMENTS` (`lib/lookups.php:379`) = *Recruitment/TA, Sourcing, Account Management, Business Development, HR & Compliance, Operations, Finance, Management* — these are an **agency's own internal departments**.

But the same `department` master is used on the **requisition**, where the value means *the department of the vacancy being filled*, which for an agency is the **client's** department and spans all industries.

Loading the agency's internal departments into the requisition's department dropdown is a **semantic collision**, and it is what makes the screens read as confusing. Same for `designation`.

### 2.4 Required separation (for Phase 1 design, not implemented)
| Concept | Means | Used on |
|---|---|---|
| **Security role** | what a user may do | `users.role` + `can()` |
| **Staff position/title** | our employee's job title | team/org chart |
| **Our departments** | our internal org units | org chart, approvals |
| **Vacancy department + designation** | what we are recruiting FOR | requisition, candidate, offer |

These are four distinct things sharing two masters today.
