# Phase 2 — Baseline Audit (M1)

**Build audited:** `b4f0fb2` · 307 tables · traced in code, not inferred from filenames.
**Status: audit only. No code has been changed.**

---

## 1. The three findings that shape Phase 2

### F1 — Multi-vacancy is structurally impossible today  ·  **CRITICAL**

§30 makes this a mandatory test: 10 vacancies, 1 joins, 9 remain open. On this
build that cannot happen, for three independent reasons:

* `requisitions` has **no vacancy-count column** at all (25 columns, none of them a quantity).
  > **CORRECTED (see `M2-QUANTITY-COLUMN-FINDING.md`).** This was what the
  > inspected database contained, but it is not true of the product. `quantity`
  > (`INT DEFAULT 1`) is created lazily by `req_migrate()` — `lib/recruit.php:44`,
  > introduced in `b963490` — the first time any requisition screen is opened. It
  > is absent only in a database where that has never happened, which is why the
  > test database shows 25 columns. The column exists; the defect below is the
  > closure rule, not a missing field.
* It carries `hired_inspector_id` — **singular**. One requisition, one hire.
* `lib/ops.php:5178` closes the whole thing on the first join:
  ```php
  UPDATE requisitions SET hired_inspector_id=?, status='HIRED' WHERE id=?
  ```

The concept is half-present but has nothing to read from:
`recruit_req_health()` (lib/recruit.php:510) computes `$qty - $filled` from
`$req['quantity']`.

> **CORRECTED.** The original text said `quantity` is "a column `requisitions`
> does not have — so `$qty` silently falls back to `1` for every requisition ever
> raised", and that `start_date` is "also absent". Both columns are created by
> `req_migrate()` (`lib/recruit.php:44` and `:48`). In any installation where the
> Requisitions screen has been opened — which is every installation that has a
> requisition — both are present and `recruit_req_health()` reads real values.
> The only way to hold a requisition without them is a `seed_demo.php` seed that
> has never been followed by a visit to Requisitions, and that visit creates them.
> `recruit_req_health()` was **not** written against the wrong shape.

**This is the single largest structural item in Phase 2** and the reason M3/M4
exist.

> **CORRECTED.** The fix is smaller than stated here: the quantity column already
> exists, and the fill count is already derived from candidates
> (`lib/recruit_cc.php`). What is genuinely missing is only the third item — a
> **closure rule that compares the two**, replacing the single
> `hired_inspector_id` and the terminal `status='HIRED'` set on the first hire.
> See `M2-MULTI-VACANCY-BOUNDARY.md` §5 for the recommended shape.

### F2 — "Job Profile" does not exist; `positions` is not it

§8 requires Job Profile, Job Title, Position, Vacancy and Requisition to be
distinct. Today:

* `positions` is an **org-chart position** — `sanctioned_headcount`,
  `occupied_headcount`, `budgeted_headcount`, `reports_to_id`, `hod_name`. It
  answers "how many of this post does the company have, and who does it report
  to", not "what does this job require".
* There is **no reusable job profile** — no stored skills, qualification,
  experience, competencies or salary band that a vacancy can be raised from.
* `requisitions.designation` and `candidates.designation` are **free text**.

`positions` should be reused as the establishment/headcount layer, not bent into
a job profile.

### F3 — Free text where a master already exists

| Field | Today | Master that already exists |
|---|---|---|
| `candidates.agency` | **free text** | `agencies` table — 16 columns incl. contract terms, fees, guarantee days |
| `department` on `requisitions`, `positions`, `candidates`, `users` | **free text** | `lookup_values` already carries a `department` key |
| `designation` on `requisitions`, `candidates` | **free text** | `lookup_values` already carries `designation` / `designations` |

§14 names the agency case explicitly. Historical values must be preserved and
mapped, never overwritten.

---

## 2. A–Z classification

| | Area | What actually exists | Verdict |
|---|---|---|---|
| A | Recruitment tables | 16: `requisitions`, `candidates`, `candidate_docs`, `candidate_stage_data`, `interviews`, `interview_scores`, `job_offers`, `positions`, `recruit_pipelines`, `recruit_stages`, `recruit_approval_*` (4) | **REUSE** |
| B | Recruitment libraries | `recruit.php`, `recruitpipe.php`, `position.php`, `careers.php`, plus recruitment handlers in `ops.php` | **REUSE / EXTEND** |
| C | Routes | `recruitment`, `requisitions`, `requisition`, `candidates`, `candidate*`, `careers*`, `recruit-config`, `recruit-export`, `candidate-pool`, `jd-generate`, `positions-import` — all mapped to access module `hiring` in the route gate | **REUSE** |
| D | Forms | Requisition new/edit, candidate new/edit, interview, offer, appointment — `views/ops/` | **REUSE / EXTEND** |
| E | Masters | `lookup_types` / `lookup_values` — a working configurable master engine, already holding `department`, `designation` | **REUSE** — do not build a parallel master system |
| F | Candidate / person | `candidates` (49 cols) with `inspector_id`; `inspectors`; `cx_professionals`; `users` | **CONNECT** via G below |
| G | Organisation | `offices` (branch), `business_partners` (clients/vendors), `cx_organisations` (marketplace), `agencies` | **REUSE / MAP** — no new organisation engine |
| H | Department | **No table.** Free text on 4 tables; a `department` lookup key exists | **MAP** (free text → lookup) |
| I | Designation / job | Free text; `lookup_values` keys exist; `cx_job_families` exists in Connect taxonomy | **MAP + BUILD** (job profile, F2) |
| J | Position | `positions` — establishment + headcount + reporting line | **REUSE** as establishment layer |
| K | Requisition | `requisitions` (25 in a fresh DB, **78 once `req_migrate()` runs**) — links `position_id`, `recruiter_id`, `manager_id`, `office_id`, `sbu` | **EXTEND** (closure rule only — `quantity` and target dates already exist; see `M2-QUANTITY-COLUMN-FINDING.md`) |
| L | Pipeline | `recruit_pipelines` + `recruit_stages` — configurable, with `applies_*` conditions, `responsible_role`, `mandatory`, `sla_days`, `required_docs` | **REUSE** — already the authoritative engine |
| M | Interviews | `interviews`, `interview_scores` | **REUSE** |
| N | Offers | `job_offers` (19) — `ctc`, `joining_date`, `letter_html`, full lifecycle dates | **REUSE** |
| O | Appointments | Document Studio (`doc_templates`, `doc_render_template`) | **REUSE** |
| P | Joining / onboarding | `job_offers.joining_date`, `accepted_at`; candidate → inspector conversion at `ops.php:5178` | **EXTEND** (see F1) |
| Q | Permissions | `mod.hiring.view`, `mod.hiring.edit`, `hiring.admin`; product module key `hr` | **REUSE** — no new keys (§19–20) |
| R | Reports | Recruitment reports + `recruit-export` | **REUSE** |
| S | Dashboard | Recruitment Command Centre | **REUSE** — do not rebuild (§25) |
| T | Exports | `recruit-export`, CSV | **REUSE** |
| U | Marketplace / Connect | `cx_requirements` (34, incl. a `positions` **count**), `cx_applications`, `cx_engagements`, `cx_bench`, taxonomy (`cx_tax_nodes`, `cx_job_families`, `cx_disciplines`, `cx_iti_trades`) | **CONNECT / MAP** — never merge (§14) |
| V | Workforce | `inspectors`; `candidates.inspector_id` already links a hired candidate to a workforce record | **REUSE** |
| W | Identity | **`cx_identity_link` already carries `candidate_id`**, alongside `professional_id`, `inspector_id`, `party_id`, with `method`, `status`, `linked_by`, `linked_at`, `unlinked_at` | **REUSE** — the person spine already exists |
| X | Approval | `recruit_approval_rules` / `levels` / `steps` / `requests` | **REUSE — do not touch in Phase 2** (§17, Phase 3 owns it) |
| Y | Audit | `act_log()` / `act_for_entity()`, already used for `CANDIDATE` (and CALL, QUOTE, LEAD, INVOICE, …) | **CONNECT** — extend to REQUISITION and POSITION |
| Z | Custom fields | `custom_fields`, `custom_values`, `custom_forms`, `custom_records` — a working engine, **not currently wired to recruitment entities** | **CONNECT** (§20) |

---

## 3. Duplicate-engine check (§37 stop conditions)

Four candidates were examined. **None is a duplicate**, so no stop condition fires:

| Apparent duplicate | Verdict |
|---|---|
| `pipelines` / `pipeline_stages` vs `recruit_pipelines` / `recruit_stages` | **Not duplicates.** The first carries `entity_kind` and stage `probability` — it is the **sales** pipeline behind leads and opportunities. The second carries `applies_department`, `applies_position`, `responsible_role`, `required_docs` — recruitment. Two domains, one engine each |
| `requisitions` vs `cx_requirements` | **Distinct by design and must stay so** (§14). `requisitions` is internal recruitment execution (office, sbu, position, recruiter, manager). `cx_requirements` is a marketplace posting (poster party, sector/discipline codes, rate band, `positions` count). Connect them by mapping, never merge |
| `agencies` vs `cx_organisations` vs `business_partners` | Three real, different organisation kinds. Map, do not merge |
| `recruit_approval_*` vs `quote_approval_*` vs `idems_approval_rules` | Separate approval configurations per domain, already established. Phase 2 adds none |

---

## 4. What Phase 2 must actually build — and why

Everything else on the list is reuse, extension or mapping. Genuinely new
structure is limited to:

1. **Vacancy quantity and fill tracking on a requisition** (F1) — no existing
   column can carry it; `cx_requirements.positions` belongs to a different object
   that must not be merged.
2. **A reusable Job Profile** (F2) — no existing table expresses "what this job
   requires" independently of a live vacancy. `positions` is establishment, not
   profile.
3. **Target-date fields** (§11) — `requisitions` has only `approval_date` and
   `created_at`; KPI/SLA later needs request/required-by/shortlist/interview/
   offer/joining dates. Structure only, no KPI engine (§28).

Each will be justified again, in code, at its own milestone.

---

## 5. Carried into Phase 2 as constraints

* Recruitment stays on product module key `hr`; no new entitlement key.
* Every new route registers through `ops_module_gate()`; the family map already
  routes the `hiring` prefixes.
* Branch scope on `requisitions` is already enforced object-level (M14 gate);
  anything new alongside it must be equally scoped.
* `candidates.stage` (free text) and `candidates.pipeline_stage_id` both exist —
  §15 forbids one field carrying several concepts. Which is authoritative must be
  settled in M6, not assumed.
* Historical documents already snapshot correctly (`job_offers.letter_html`
  stores the rendered letter). §27's rule is established; keep to it.
