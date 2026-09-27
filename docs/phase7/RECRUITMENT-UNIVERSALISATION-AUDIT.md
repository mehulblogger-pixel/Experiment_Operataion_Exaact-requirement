# Recruitment Universalisation — AUDIT ONLY

> **This is an audit. No production code, schema, data, route, permission,
> terminology or behaviour was changed.** The only repository change is this
> file. Nothing here is a decision; §35 and §37 list what still has to be
> decided.

| | |
|---|---|
| **Phase** | Pre-design audit |
| **Branch** | `claude/testing-branch-setup-0gqe8n` |
| **Commit at start** | `6d39d65` · working tree clean |
| **Date** | 2026-09-27 |
| **Scope** | `phpapp/` Recruitment and everything it depends on |
| **Method** | Static read of source + read-only queries against the live `rqv_ui` database |

**Evidence convention.** Every claim is tagged:

- **FACT** — read directly from source or from a read-only query. Reproducible.
- **OBSERVATION** — a pattern across several facts.
- **INFERENCE** — reasoning from facts. May be wrong; marked so it can be challenged.
- **OPEN QUESTION** — genuinely undecided; belongs to the design phase.

---

## 1. Executive summary

**The headline is not the one expected.** The instinct behind this audit was that
Recruitment is "heavily TPIA-oriented". The evidence does not support that as a
general statement, and supports something more awkward instead.

**FACT.** Of 65 constants defined in the Recruitment module, 12 are configurable
defaults (the lookup engine can override them) and 53 are absolute. **Of those 53
absolutes, only 5 are rendered to a user at all**, and only 2 of those 5 are
industry-shaped choices (`WF_TEAM_ROLES`, `RECRUIT_ENGAGEMENT_MODES`). The rest
are internal state machines — universal workflow plumbing, not industry assumptions.

**FACT.** The Recruitment KPI engine contains **zero** references to inspection,
deputation, man-days or work orders (`grep -cE "inspect|deputation|manday|call_code"`
over `lib/recruit_kpi.php` and `lib/recruit_cc.php` returns `0` for both).

**So the module is substantially more universal than expected.** The genuine
blockers are narrower and different:

1. **The configurable pipeline is built and effectively unused.** 935 of 935
   candidates carry the legacy hard-coded `stage`; **2** are on a configured
   pipeline. Two lifecycle systems exist side by side with a partial one-way sync.
2. **The approval engine is capable, wired for only 2 of its 4 declared entities,
   and has zero rules configured.** Requisition and Salary approvals can be
   configured and will never fire.
3. **The newest screens bypass the terminology engine.** `hiring_request.php` and
   `hiring_request_list.php` make **0** terminology calls — the mechanism that
   makes the product re-word itself per industry is not used by the screens most
   central to universalisation.
4. **Candidates have no office dimension at all** and the candidate register is
   unscoped (`$where = '1=1'`), while requisitions are scoped.
5. **The person-spec is absent from the hiring request**, so an approval does not
   constrain what is recruited — sharpest exactly for the staffing and agency
   models this transformation targets.

**INFERENCE.** The obstacle to universalisation is **not** de-TPIA-ing the code.
It is that three universalising mechanisms were built and then not adopted.
Removing TPIA vocabulary would deliver far less than finishing the adoption of
what already exists.

---

## 2. Scope

**In scope.** Recruitment execution (hiring request → requisition → candidate →
interview → offer → joining), its configuration, its approval/SLA/KPI/notification
dependencies, its screens, routes, tables, permissions, tests and documentation.

**Read but treated as protected context** (per the mission brief, audited for
conformance, never modified): Operations, Quality, Reporting, Money/Billing,
Workforce, Marketplace.

**Out of scope.** Any change. Any migration. Any design decision.

---

## 3. Repository inventory

**FACT.** Files whose *content* touches recruitment data
(`hiring_requests|requisitions|candidates|recruit_|job_offers|interview|cx_requirements|requisition_allocations`):
**115** — 81 under `lib/`, 33 under `views/`, plus `index.php`.

Filename was deliberately not used as the test: `lib/ops.php` (131 hits),
`lib/compliance.php`, `lib/portal.php`, `lib/search.php`, `lib/nextaction.php`
and the `connect_*` marketplace family all carry recruitment logic without
"recruit" in the name.

### 3.1 Core libraries

| ID | File | Purpose (from its own header) | Relevance |
|---|---|---|---|
| L01 | `lib/recruit.php` | Recruitment & Workforce Command Centre | Core — also holds the candidate→workforce conversion |
| L02 | `lib/hiringreq.php` | Hiring Request (Phase 2 · M4) | Core |
| L03 | `lib/reqfulfil.php` | Requisition fulfilment (Phase 2 · M3) | Core |
| L04 | `lib/recruitpipe.php` | Configurable pipeline / stage engine | Core — see §17 |
| L05 | `lib/recruit_approval.php` | Configurable approval matrix + SLA + reminders | Shared engine |
| L06 | `lib/recruit_iv.php` | Interviews (multi-round + scorecards) & Document DMS | Core |
| L07 | `lib/recruit_offer.php` | Salary structure, HR discussion, Offer & Onboarding | Core |
| L08 | `lib/recruit_kpi.php` | KPI, SLA & performance engine | Shared engine |
| L09 | `lib/recruit_cc.php` | Recruitment Command Centre (Phase 7) | Core |
| L10 | `lib/recruit_fulfil.php` | Multi-source fulfilment (Phase 4) | Core |
| L11 | `lib/recruit_assign.php` | Recruiter accountability (Phase 3 · M5) | Core |
| L12 | `lib/recruit_exec.php` | Integrated execution gate (Phase 3 · M6) | Core |
| L13 | `lib/recruit_export.php` | CSV exports | Peripheral |
| L14 | `lib/recruit_jd.php` | Auto job-description & posting generator | Peripheral |
| L15 | `lib/careers.php` | Public careers site + application intake | Entry point |
| L16 | `lib/candpool.php` | Candidate pool convergence (Revamp P11) | Identity |
| L17 | `lib/position.php` | Position master, org chart, manpower-plan validation | Establishment |
| L18 | `lib/comp_config.php` | Configurable compensation setup | Configuration |
| L19 | `lib/doc_templates.php` | Configurable Document Studio | Configuration |
| L20 | `lib/ops.php` | Dispatcher + candidate/requisition handlers + `CAND_STAGES` | **Shared — highest coupling** |

**OBSERVATION.** `lib/ops.php` is both the router and the home of the candidate
and requisition handlers *and* the legacy `CAND_STAGES` constant. It is the single
largest recruitment dependency and is shared with every other module.

### 3.2 Shared consumers

`lib/connect_market.php`, `connect_identity.php`, `connect_analytics.php`,
`connect_client_dash.php`, `connect_match.php`, `connect_source.php`,
`connect_bench.php`, `connect_person.php` (Marketplace); `lib/portal.php`
(client/vendor portal); `lib/search.php`; `lib/nextaction.php`;
`lib/compliance.php`; `lib/workforce.php`; `lib/party.php`; `lib/navindex.php`;
`lib/licence.php`; `lib/tapi.php`.

### 3.3 Seeds and fixtures

`seed_recruit_cc.php`, `seed_demo.php`, `seed_demo_c.php`, `seed_connect.php`,
`seed_scenario_s01..s06.php`. **FACT.** Several write directly into `inspectors`
and `candidates`.

---

## 4. Recruitment architecture map

```
ENTRY          Careers page (public)   Recruitment CC   Requisition   Marketplace   Agency/Portal
                       │                      │             │              │            │
                       └──────────────┬───────┴─────────────┴──────────────┴────────────┘
                                      ▼
   ASK        hiring_requests ──(appr_start 'HIRING_REQUEST')──► recruit_approval_* ──► decision
                                      │ approved
                                      ▼
   ORDER      requisitions  ◄── hiring_request_id ──  (headcount ceiling enforced)
                                      │
                                      ▼
   PEOPLE     candidates ── requisition_allocations ──► fulfilment state
                  │  │
                  │  └── pipeline_id / pipeline_stage_id   (configured engine — 2 rows)
                  └───── stage                              (legacy constant — 935 rows)
                                      │
                                      ▼
   ASSESS     interviews + interview_scores + scorecards + assessment_criteria
                                      ▼
   COMMIT     job_offers ──(appr_start 'OFFER')──► recruit_approval_* ──► issued/accepted
                                      ▼
   WORKFORCE  inspectors  (the technical workforce spine — recruit.php:2211)
```

**FACT.** Shared engines are genuinely single: one approval engine
(`lib/recruit_approval.php`), one KPI/SLA engine (`lib/recruit_kpi.php`), one
pipeline engine (`lib/recruitpipe.php`). No duplicate engines were found. This
conforms to the protected architecture.

---

## 5. Complete screen inventory

**FACT.** 18 dedicated recruitment views plus 15 shared views that consume
recruitment data.

| ID | Screen | Route | View | Access | Purpose |
|---|---|---|---|---|---|
| S01 | Recruitment home | `/recruitment` | `ops/recruitment_home.php` | `mod.hiring.view` | Module landing |
| S02 | Recruitment Command Centre | `/recruitment-cc` | `ops/recruitment_cc.php` | `mod.hiring.view` | Funnel + KPIs |
| S03 | Hiring requests list | `/hiring-requests` | `ops/hiring_request_list.php` | `mod.hiring.view` | The ask, listed |
| S04 | Hiring request | `/hiring-request` | `ops/hiring_request.php` | `mod.hiring.edit` | Create/edit/decide |
| S05 | Requisitions list | `/requisitions` | `ops/requisition_list.php` | coordinator-level | Work orders, **office-scoped** |
| S06 | Requisition new/edit | `/requisition-new`, `/requisition-edit` | `ops/requisition_form.php` | coordinator-level | Execution record |
| S07 | Requisition detail | `/requisition` | `ops/requisition_detail.php` | coordinator-level | Allocations, fulfilment |
| S08 | Requisition allocations | `/requisition-allocations` | `ops/_allocation_panel.php` | coordinator-level | Multi-vacancy seats |
| S09 | Candidates list | `/candidates` | `ops/candidate_list.php` | coordinator-level | **Unscoped** — see §28 |
| S10 | Candidate new/edit | `/candidate-new`, `/candidate-edit` | `ops/candidate_form.php` | coordinator-level | Create/edit |
| S11 | Candidate detail | `/candidate` | `ops/candidate_detail.php` | coordinator-level | Profile, stages, offer |
| S12 | Candidate pool | `/candidate-pool` | `ops/candidate_pool.php` | coordinator-level | Convergence, read-only |
| S13 | Interviews | `/candidate-interview` | within `candidate_detail.php` | coordinator-level | Rounds + scorecards |
| S14 | Offer | `/candidate-offer`, `/offer-letter` | within `candidate_detail.php` | coordinator-level | Salary, offer, letter |
| S15 | Positions master | `/positions` | `ops/positions.php` | `hiring.admin` | Establishment |
| S16 | Org chart | `/positions-org` | `ops/positions_org.php` | `hiring.admin` | Reporting lines |
| S17 | Positions import | `/positions-import` | `ops/positions_import.php` | `hiring.admin` | Bulk load |
| S18 | Pipelines admin | `/recruit-pipelines` | `ops/recruit_pipelines.php` | `hiring.admin` | Configure stages |
| S19 | Compensation setup | `/comp-setup` | `ops/comp_setup.php` | `hiring.admin` | Salary headings |
| S20 | Document studio | `/doc-templates` | `ops/doc_templates.php` | `hiring.admin` | Letter templates |
| S21 | Careers admin | `/careers-admin` | `ops/careers_admin.php` | `hiring.admin` | Public posting |
| S22 | Careers page (public) | `/careers` | `lib/careers.php` | **unauthenticated** | Application intake |
| S23 | Approval rules | `/approval-rules` | `ops/approval_rules.php` | `settings.manage` | Matrix (shared) |
| S24 | My approvals | `/my-approvals` | `ops/my_approvals.php` | any approver | Inbox (shared) |
| S25 | Approval delegations | `/approval-delegations` | `ops/approval_delegations.php` | shared | Stand-ins |
| S26 | Marketplace sourcing | `/connect-source` | `ops/connect_source.php` | marketplace | Cross-module |
| S27 | Client portal hiring | `/portal` → hire | `portal/hire.php` | external client | Cross-module |

---

## 6. Complete route / entry-point inventory

**FACT.** The dispatcher (`lib/ops.php`) declares **447** distinct routes; **48**
are recruitment-related after removing routes belonging to other modules
(`quote-approval-*`, `idems-approval-*`, `crm-letterhead`).

**FACT.** `/careers` is served from `index.php:1100` **before the authenticated
router**, via `careers_route()`, which "always exits" (`index.php:1099`).

### 6.1 Entry points and what they can bypass

| Entry | Creates | Can bypass approval? | Can create incomplete data? |
|---|---|---|---|
| `/hiring-request` | hiring request | no — submit routes to the matrix | yes (DRAFT is intentional) |
| `/requisition-new` | requisition | **blocked by default** — `ops.php:5858` calls `hreq_direct_path_block_reason()` | n/a |
| `/candidate-new` | candidate | **n/a — no approval exists for creating a candidate** | **yes — a candidate needs no requisition** (`ops.php:6663` treats `requisition_id` as optional) |
| `/careers` (public) | candidate | n/a | yes — by design, an applicant |
| Marketplace / agency / portal | candidate | n/a | yes |
| Seeds | candidates, inspectors | yes | yes — fixtures only |

**FACT.** The direct-requisition block is a **setting**, default ON:
`setting_get('requisition_requires_request', '1')` — `lib/hiringreq.php:523`.
It is enforced in exactly two places: `lib/ops.php:5858` and
`lib/projcosting.php:350`.

**OBSERVATION.** Candidate creation has no prerequisite and no approval. A
candidate may exist with no requisition, no client and no office.

---

## 7. Workflow map

**FACT.** Lifecycle states, read from source:

- **Hiring request** (`HREQ_STATUS`, `hiringreq.php:37`) — DRAFT → SUBMITTED →
  UNDER_REVIEW → APPROVED | REJECTED | CANCELLED. Executable only from APPROVED
  (`HREQ_EXECUTABLE`, line 47).
- **Requisition** (`REQ_STATUS`, `ops.php:53`) — OPEN → PROPOSED → OFFERED →
  PARTIALLY_FILLED → HIRED → CLOSED | CANCELLED.
- **Candidate** (`CAND_STAGES`, `ops.php:70`) — RECEIVED → SUBMITTED →
  SHORTLISTED → INTERVIEW → OFFERED → ACCEPTED, with OFFER_DECLINED, HOLD,
  REJECTED, WITHDRAWN.
- **Offer** (`OFFER_STATUS`, `recruit_offer.php:32`) — DRAFT → PENDING_APPROVAL →
  APPROVED → ISSUED → VIEWED → ACCEPTED | DECLINED | EXPIRED | WITHDRAWN.

### 7.1 Transitions that start an approval

**FACT.** `appr_start()` is called from exactly **three** places in production
code:

| Where | Entity | Amount passed |
|---|---|---|
| `lib/hiringreq.php:919` | `HIRING_REQUEST` | `hreq_commitment()['total']` (money) |
| `lib/hiringreq.php:1003` | `HIRING_REQUEST` | same |
| `lib/recruit_offer.php:311` | `OFFER` | `ctc` |

**FACT.** `APPR_ENTITIES` (`recruit_approval.php:21`) declares four entities:
`HIRING_REQUEST`, `REQUISITION`, `OFFER`, `SALARY`. `REQUISITION` and `SALARY`
appear in `appr_start()` calls **only inside `tests/`** — never in application code.

**FACT.** `SELECT COUNT(*) FROM recruit_approval_rules` = **0**.

**INFERENCE.** With no rule configured, `appr_start()` returns "not started" and
every hiring request falls back to a single direct decision. All SLA, reminder,
escalation and multi-level machinery is dormant in this installation.

---

## 8. Candidate lifecycle

See §7. **The material finding is §17.**

**FACT.** Candidate movement is audited into `candidate_events` via
`rkpi_stage_log()`, falling back to a direct insert (`recruitpipe.php:445-449`).
`act_log()` counts: `hiringreq.php` 31, `recruit.php` 5, `recruit_offer.php` **0**,
`recruitpipe.php` **0**.

**OBSERVATION.** Two audit mechanisms coexist — `act_log` (entity audit) and
`candidate_events`/`rkpi_stage_log` (stage ledger). Offer transitions appear in
neither `act_log` nor the stage ledger.

**OPEN QUESTION.** Is the absence of `act_log` on offers intentional (the stage
ledger is the record) or a gap? Not determinable from source alone.

---

## 9. Hiring Request lifecycle

**FACT.** Re-approval on material change exists: `hreq_material_diff()`
(`hiringreq.php:601`) is called at `:858` and gates `hreq_require_reapproval()`
at `:868`. `HREQ_MATERIAL_FIELDS` defines what counts. The comment at
`hiringreq.php:77` records the rule: an **increase** spends authority nobody
granted; a decrease does not.

**FACT.** The approval context (`hreq_appr_ctx()`, `:389`) sends `amount` =
**headcount** and `commitment` = **money** as separate keys, deliberately
(`:404-416`), so existing amount-band rules keep meaning headcount.

**FACT.** Headcount ceiling enforced by `hreq_qty_guard()` (`:540`) and
re-checked after insert by `hreq_qty_enforce_after_write()` (`:565`) because
check-then-insert is not atomic — the comment at `hiringreq.php:1269` records a
measured over-allocation of 11 against an approved 10 on MariaDB.

---

## 10. Requisition lifecycle

**FACT.** Created from an approved request at `hiringreq.php:1255`. Carried
across: `office_id, client_id, department, department_id, designation, grade,
position_id, quantity, req_type, project_site, deploy_location, start_date,
responsibilities, budgeted_cost, rate_basis`, plus the request's `decided_by` and
`decided_at` as the requisition's own approval stamp.

**FACT.** **No person-spec is carried**, because the hiring request holds none.
`requisitions` has `skills`, `qualification`, `experience_min`,
`relevant_experience`, `discipline`, `category`, `trade_id`, `skill_id` — all
left blank at conversion.

**FACT.** `requisitions` has **no `experience_max` column**
(read from `information_schema`).

**FACT.** No re-approval guard exists on requisition edit — `grep` for
`reapprov|material_diff` against requisition handling returns nothing. Quantity
is guarded; budget is not.

---

## 11. Acceptance / Joining lifecycle

**FACT.** `CAND_STAGES` has one terminal success state: `ACCEPTED` → "Accepted
(Hired)". `RCC_TERMINAL` (`recruit_cc.php:67`) = REJECTED, WITHDRAWN,
OFFER_DECLINED, HOLD.

**OBSERVATION.** "Accepted", "Hired" and "Joined" are **not** three states.
`ACCEPTED` carries the label "Accepted (Hired)", and joining is represented by
the existence of an `inspectors` row, not by a candidate state. `/candidate-joined`
is a route that performs the conversion.

**INFERENCE.** This is a **derived state**, not a duplicate concept: joined ≡
`candidates.inspector_id > 0`. Recorded as inference because no comment states it.

---

## 12. Workforce / Inspector handoff

**FACT.** `lib/recruit.php:2211` inserts into `inspectors` with columns:
`name, first_name, middle_name, last_name, email, mobile, trade_id, skill_ids,
sbus, sbu, designation, staff_kind, emp_code, home_office_id, agency_id,
roll_type, agency_name, agency_cost, placement_fee, fee_status, guarantee_upto,
team_role, dup_ack, status, created_at`.

**FACT.** The conversion is concurrency-hardened: `FOR UPDATE` row lock on
non-SQLite (`:2186`), employee number **claimed** via `emp_code_claim()` rather
than guessed — the comment at `:2192-2198` records four processes receiving
`EMP01` simultaneously on MariaDB before this was fixed.

**OBSERVATION.** This is the single densest concentration of field-services
vocabulary in the module: `trade_id`, `sbus`, `staff_kind`, `team_role`,
`roll_type`, `agency_cost`, `placement_fee`, `guarantee_upto`.

**INFERENCE.** Per the protected architecture, `inspectors` *is* the technical
workforce spine, so writing there is correct by design. What is industry-shaped
is the **column vocabulary**, not the relationship.

---

## 13. Database map

**FACT.** 319 tables in `rqv_ui`. 33 are recruitment-related.

| Table | Rows (live) | Role |
|---|---|---|
| `candidates` | 935 | Person in process |
| `requisitions` | 212 | Execution record |
| `hiring_requests` | 126 | The ask |
| `requisition_allocations` | 132 | Seats |
| `requisition_allocation_events` | 933 | Seat ledger |
| `recruiter_assignments` | 47 | Accountability |
| `candidate_events` | 49 | Stage ledger |
| `recruit_stages` | 32 | Configured stages |
| `assessment_criteria` | 25 | Scoring |
| `cx_requirements` | 19 | **Marketplace — distinct concept** |
| `inspectors` | 162 | Workforce spine |
| `job_offers` | 12 | Offers |
| `interviews` | 11 | Rounds |
| `recruit_pipelines` | 3 | Configured pipelines |
| `positions` | 3 | Establishment |
| `recruit_approval_rules` | **0** | Matrix — **empty** |
| `recruit_approval_requests` | **0** | Chains — **empty** |
| `candidate_stage_data` | 0 | Per-stage capture |
| `cx_positions` | 0 | Marketplace positions |
| `interview_scores` | 0 | Scorecard lines |

**FACT.** **No table in the database carries `tenant_id` or `company_id`** —
`information_schema` query returns no rows. Tenant isolation is structural: one
database per tenant. This conforms to the protected architecture.

**FACT.** `candidates` has **no office column**. Its only scoping-capable columns
are `client_id`, `sbu`, `requisition_id`.

---

## 14. Data-flow map

| Element | First entered | Stored | Re-entered | Authoritative? |
|---|---|---|---|---|
| Department | hiring request | `hiring_requests.hiring_department_id` | requisition (`department_id`) | carried — OK |
| Designation | hiring request | `hiring_requests.designation` | requisition | carried — OK |
| Headcount | hiring request | `quantity` | requisition `quantity` | carried + ceiling-guarded — OK |
| Budget | hiring request | `est_cost_per_person`, `est_cost_basis` | requisition `budgeted_cost`, `rate_basis` | carried — **but editable afterwards without re-approval** |
| Office | hiring request | `office_id` | requisition `office_id` | carried — OK |
| **Qualification** | **requisition** | `requisitions.qualification` | — | **not in the request at all** |
| **Experience (min)** | **requisition** | `requisitions.experience_min` | — | **not in the request** |
| **Experience (max)** | **nowhere** | **no column exists** | — | **missing system-wide** |
| **Key skills** | **requisition** | `requisitions.skills` | — | **not in the request** |
| Candidate name/mobile/email | candidate form or careers page | `candidates` | marketplace/agency paths | de-duplicated by `candpool.php` |
| Candidate stage | candidate screen | `candidates.stage` **and** `pipeline_stage_id` | — | **two columns, partial sync** |
| Salary/CTC | offer | `job_offers.ctc` | inspector `salary_ctc` at conversion | carried |

---

## 15. Duplicate-entry audit

**D-E1 — FACT.** Person-spec fields (`qualification`, `skills`, `experience_min`,
`discipline`, `category`, `trade_id`, `skill_id`) exist on `requisitions` and on
no earlier record, so they are typed **after** approval, by the recruiter, with
nothing to copy from. Not strictly re-entry — it is *first* entry at the wrong
stage. Effect: the approval does not constrain the recruitment.

**D-E2 — FACT.** `sbu` is stored on `candidates`, `requisitions`, `hiring_requests`
and `inspectors`. Carried forward at each conversion; not manually re-typed.

**D-E3 — OBSERVATION.** Candidate identity (name/mobile/email) arrives from six
paths (form, careers page, marketplace, agency, portal, import). `lib/candpool.php`
exists precisely to converge them, with `CANDPOOL_CONFIDENCE` thresholds. This is
**managed** duplication, not accidental.

---

## 16. Duplicate-screen audit

| A | B | Overlap | Verdict |
|---|---|---|---|
| `/candidate-new` | `/careers` (public) | Both create `candidates` | **Intentionally different** — authenticated intake vs public application. Different permissions, different data completeness. |
| `/candidate-pool` | `/candidates` | Both list candidates | **Intentionally different** — pool is read-only convergence across two sources; list is the working register. |
| `/recruitment` | `/recruitment-cc` | Both are landings | **Possible duplicate.** Home is a launchpad; CC is the funnel/KPI board. OPEN QUESTION whether two landings are warranted. |
| `/requisition-allocations` | `/requisition` detail | Allocation panel appears in both | **Same component** (`_allocation_panel.php`) reused — not duplication. |
| `/approval-rules` | `/quote-approval-rules`, `/idems-approval-rules` | Three approval-rule screens | **Different engines for different modules.** Recruitment uses `recruit_approval_*`. Not a recruitment duplicate, but worth recording: the product has three separate approval configuration screens. |

**No duplicate candidate-edit, interview, offer or joining screens were found.**

---

## 17. Duplicate-workflow audit — the most material finding

**FACT.** `candidates` carries **both** lifecycle systems:
`stage` (legacy constant `CAND_STAGES`) **and** `pipeline_id` + `pipeline_stage_id`
(the configurable engine).

**FACT (live data).**

```
total candidates   935
with legacy stage  935   (100%)
on a pipeline        2   (0.2%)
```

**FACT.** The sync is one-way and partial. `recruitpipe.php:451-459`, comment
verbatim: *"Coarse legacy sync — only at the interview/offer milestones, and never
over a terminal legacy stage."* Only `interview` and `offer` stage kinds write back
to `stage`.

**INFERENCE.** The configurable pipeline — the mechanism intended to make
recruitment industry-adaptive — is built, tested, admin-configurable, and
**effectively unadopted**. The live lifecycle is the hard-coded constant.

**OBSERVATION.** `CAND_STAGES` contains `'SUBMITTED' => 'Submitted to client'` —
a staffing/agency assumption in the default list every candidate currently uses.
It is routed through `lk_options_or('candidate_stage', CAND_STAGES)`, so it is
overridable; but overriding it does not change the *shape* of the funnel, only
the labels.

---

## 18. TPIA hardcoding inventory

**Method.** Counted occurrences of TPIA vocabulary **inside the recruitment module
only** (19 libs + 12 views), then classified each by whether it reaches a user,
whether it is overridable, and whether it is genuinely industry-specific.

| Term | lib | view | Classification | Evidence |
|---|---|---|---|---|
| `inspector` | 64 | 16 | **TPIA-COUPLED** — the workforce spine's name, protected by design | `recruit.php:2211` |
| `SBU` | 63 | 10 | **CONFIGURABLE** — `lk_options_or('sbu', OPS_SBUS)` + renamed by `T("sbu")`, never mandatory | `requisition_form.php:220` |
| `FIELD`/`COORD`/`OFFICE` | 83/42 | 40/18 | **TPIA-HARDCODED** — `WF_TEAM_ROLES` const, not in the lookup engine | `recruit.php:1904`; used `candidate_detail.php:531`, `requisition_form.php:230` |
| `discipline` | 31 | 16 | **COMMON** — a free-text column; only its placeholder is TPIA | `requisition_form.php:340` |
| `NDT` / CSWIP / UT-II | 4 | 3 | **TPIA-COUPLED (UI copy only)** — placeholder text, no logic | `requisition_form.php:340,355,395,409` |
| `deputation` | 7 | 4 | **CONFIGURABLE** — `REQ_WORK_MODELS` via `lk_options_or('req_work_model', …)` | `recruit.php:19` |
| `manday`/`man-month` | 6 | 3 | **CONFIGURABLE** — `REQ_RATE_BASIS` via `lk_options_or('req_rate_basis', …)` | `recruit.php:21` |
| `trade_id` | 9 | 4 | **COMMON** — a lookup FK; the lookup values are configurable | `requisitions` schema |
| `RCC_DEPARTMENTS` (INSPECTION, QAQC, NDT, HSE) | — | — | **TPIA-COUPLED (seed only)** — overridable via `lk_options_or('hr_department', …)` | `recruit_cc.php:51-53`, `:33` |
| `RECRUIT_ENGAGEMENT_MODES` | 1 | 1 | **CONFIGURABLE (setting-backed)** but absolute list | `recruit.php:1300-1309` |
| inspection / deputation in **KPIs** | **0** | **0** | **COMMON** | `grep -cE` over `recruit_kpi.php`, `recruit_cc.php` = 0 |

**FACT — the headline count.** 65 constants: **12** configurable defaults, **53**
absolute. Of the 53, **only 5 are rendered in any view**: `APPR_ENTITIES`,
`RECRUIT_ENGAGEMENT_MODES`, `RFUL_CLOSED_STATES`, `RFUL_LIVE_STATES`,
`WF_TEAM_ROLES`. The other 48 are internal state machines.

**INFERENCE.** Genuine TPIA *hardcoding* in Recruitment is narrow: essentially
`WF_TEAM_ROLES` and a handful of placeholder strings. The widely-used TPIA-sounding
concepts (SBU, deputation, man-day, department) are already routed through the
lookup engine.

---

## 19. Business-logic hardcoding inventory

**A — genuinely common.** Approval chain mechanics (`APPR_*`), fulfilment states
(`RFUL_*`, `REQF_*`), KPI bases (`RKPI_BASES` = `['calendar','business']`),
re-approval materiality (`HREQ_MATERIAL_FIELDS`), duplicate-confidence thresholds
(`CANDPOOL_CONFIDENCE`).

**B — configurable.** 12 constants with lookup override; 22 lookup types in use:
`agency_type, candidate_doc_type, candidate_source, candidate_stage, designation,
drop_point, drop_reason, employment_type, hiring_request_status, hr_department,
hr_priority, rate_type, req_allowance, req_duty_hours, req_rate_basis,
req_shift, req_sourcing_model, req_work_model, requisition_status,
requisition_type, roll_type, sbu`.

**C — TPIA-specific.** `WF_TEAM_ROLES`; TPIA placeholders in `requisition_form.php`;
TPIA-flavoured seeds in `RCC_DEPARTMENTS`.

**D — accidental / historical.** The coexistence of `CAND_STAGES` with the
pipeline engine (§17). `REQ_STATUS` and `CAND_STAGES` both living in `lib/ops.php`
rather than a recruitment library.

---

## 20. Configuration audit

**FACT.** Already configurable without code: vocabulary (12 term packs, 13
industry templates), candidate pipeline stages, approval matrix (rules, levels,
SLA, escalation, delegation), compensation headings, document/letter templates,
positions and org chart, careers posting, role workspaces, and the 22 lookup
types above.

**FACT.** Configuration mechanisms already exist and are single. **No second
configuration system is needed** for anything listed.

**Cannot currently be configured.** `WF_TEAM_ROLES`; the *shape* (not labels) of
the legacy candidate funnel; which entities require approval (`APPR_ENTITIES` is a
const); whether a requisition edit needs re-approval; the person-spec fields
(they do not exist to configure).

---

## 21. Permission audit

**FACT.** Recruitment declares exactly three permissions: `mod.hiring.view`,
`mod.hiring.edit`, `hiring.admin` (`lib/access.php:156,198`).

**FACT.** Candidate screens guard on `is_coordinator_level()` — a role predicate,
not a recruitment permission (`ops.php:6867`).

**OBSERVATION.** Two authorisation vocabularies are in play for one module:
module permissions (`mod.hiring.*`) and role-level predicates
(`is_coordinator_level()`). Requisition and candidate screens use the latter;
hiring-request screens use the former.

**OPEN QUESTION.** Is `is_coordinator_level()` on candidate screens intended, or
inherited from when candidates lived in Operations?

---

## 22. Terminology audit

| Term | Where | Consistent? | Note |
|---|---|---|---|
| Hiring Request | `hiring_requests`, S03/S04 | yes | The business ask |
| Requisition | `requisitions`, S05–S07 | yes | Execution |
| Requirement | `cx_requirements` (Marketplace) | yes — **deliberately distinct** | Must not merge |
| Candidate | `candidates` | yes | |
| Professional / Inspector / Employee | `inspectors`, `cx_*` | **ambiguous** | Three words, one spine |
| Accepted / Hired | `CAND_STAGES['ACCEPTED']` = "Accepted (Hired)" | **one state, two words in one label** | |
| Joined | no state — derived from `inspector_id` | **implicit** | |

**FACT — terminology-engine coverage** (count of `T()/Tl()/TP()/TH()/T_*` calls):

```
requisition_detail.php   13     recruitment_cc.php    11
recruitment_home.php      8     candidate_detail.php   5
requisition_list.php      4     candidate_form.php     2
candidate_list.php        2     requisition_form.php   2
hiring_request.php        0     hiring_request_list.php 0
```

**INFERENCE.** The two newest and most universalisation-critical screens do not
use the mechanism that makes the product re-word itself per industry. This
contradicts ADR-002 ("one word per object").

---

## 23. Dashboard / KPI audit

**FACT.** Command-centre cards: *In process, Offer issued, Offer accepted, On
hold, Rejected, Dropped, Accepted did not join* (`recruit_cc.php`).

**FACT.** `grep -cE "inspect|deputation|manday|call_code"` over `recruit_kpi.php`
and `recruit_cc.php` = **0** and **0**. `RKPI_BASES = ['calendar','business']`.

**OBSERVATION.** The KPI layer is industry-neutral. Its funnel order
(`RCC_FUNNEL_ORDER`) is absolute, so the *shape* of the funnel is fixed even
though the labels are configurable — consistent with §17.

---

## 24. Notification / SLA audit

**FACT.** `lib/recruit_approval.php` contains 68 references to SLA, reminder or
escalation. Four mail-send sites across the recruitment approval/KPI/hiring-request
libraries.

**FACT.** With `recruit_approval_rules` empty, no chain is created, so **no SLA,
reminder or escalation currently fires** for recruitment in this installation.

---

## 25. Reporting audit

**FACT.** `lib/recruit_export.php` provides CSV exports for the recruitment desk.
`lib/connect_analytics.php` carries 11 recruitment references.

**OBSERVATION.** Recruitment reporting is thin relative to Operations reporting.
No recruitment-specific hard-coded status filters were found beyond the lifecycle
constants already inventoried.

---

## 26. API / integration audit

**FACT.** 10 JSON endpoints in `lib/ops.php`; 3 in recruitment libraries.
Authentication is session-based; `/careers` is the only unauthenticated
recruitment surface and it exits before the router (`index.php:1100`).

**OBSERVATION.** No REST API exists for recruitment. Integration is via the
Marketplace (`connect_*`) and the portal, both in-process.

---

## 27. Mobile audit

**FACT.** 2 test files reference mobile widths. No browser-driven tests exist
(`grep -rl "chromium|playwright" tests/*.php` = 0).

**OBSERVATION.** Mobile behaviour of recruitment screens is **not verified by any
automated test**. This audit did not drive a browser, so no claim is made about
actual rendering at 360/390/412px.

**OPEN QUESTION.** Requires a browser pass to answer properly. Deliberately not
guessed.

---

## 28. Security audit

**S1 — FACT.** Tenant isolation is structural (one database per tenant); no
`tenant_id` column exists anywhere. Cross-tenant leakage is not possible through
query omission.

**S2 — FACT.** The **candidate register is unscoped**: `ops.php:6503` sets
`$where = '1=1'` and adds only stage and free-text filters. Contrast
`ops.php:5840`, where the requisition register uses
`scope_clause('r.office_id','r.sbu')`.

**S3 — FACT.** `candidates` has **no office column**, so no office filter is
*possible* without a schema change. This is a **missing dimension**, not a removed
filter.

**S4 — FACT.** `/candidate?id=` is guarded by `is_coordinator_level()` alone
(`ops.php:6867`) with no record-level scope check — any coordinator-level user in
any office can open any candidate by id.

**INFERENCE.** S2–S4 are one issue, not three: candidates are company-wide by
design. For a single-office TPIA that is harmless. For a multi-office staffing
business or an agency running separate desks it is a confidentiality question.

**OPEN QUESTION.** Should candidates acquire an office dimension? This is a
design decision with migration consequences — explicitly **not** decided here.

**S5 — OBSERVATION.** `/careers` accepts unauthenticated input that creates
`candidates` rows. Rate-limiting and abuse controls were not examined in this pass.

---

## 29. Test coverage audit

**FACT.** 69 recruitment-related test files out of 547.

**FACT.** Test types present:

```
HTTP-level tests      0
browser tests         0
mobile-width tests    2
```

**OBSERVATION.** Coverage is deep on engines (approval `p3m*` series ~15 files,
allocations `p4*`, hiring request `m4*`) and absent on delivery — no test drives a
route, renders a screen, or checks a permission through HTTP.

**Not tested (evidence-based):** the legacy-vs-pipeline stage divergence (§17);
requisition budget drift after approval (§10); candidate record-level access
(§28 S4); mobile rendering.

---

## 30. Documentation-vs-code audit

| Claim | Source | Code reality |
|---|---|---|
| Configurable pipeline drives the candidate screen | ADR-003 | **Partially true** — engine exists and is wired; 2 of 935 candidates use it (§17) |
| One word per object, via the terminology engine | ADR-002 | **Contradicted** on the two hiring-request screens: 0 terminology calls (§22) |
| Direct requisition path is closed | ADR-001 | **True** — enforced `ops.php:5858`, `projcosting.php:350`, default ON |
| Four entities are approvable | `APPR_ENTITIES` | **Half true** — 2 of 4 wired (§7.1) |
| `docs/recruitment/RECRUITMENT-MODULE.md` R6 states Requisition/Salary never fire | that document | **Confirmed by this audit** |

---

## 31. Legacy-code audit

| Item | Classification | Evidence |
|---|---|---|
| `CAND_STAGES` in `lib/ops.php:70` | **ACTIVE** (and dominant) | 935/935 rows |
| `recruit_pipelines` / `recruit_stages` | **ACTIVE but unadopted** | 3 pipelines, 32 stages, 2 candidates |
| `REQUISITION` / `SALARY` in `APPR_ENTITIES` | **DUPLICATE CANDIDATE / inert** | no production `appr_start` |
| `candidate_stage_data` (0 rows) | **UNKNOWN** | table exists, unused in this install |
| `cx_positions` (0 rows) | **UNKNOWN** | Marketplace-side |
| `WF_TEAM_ROLES` | **ACTIVE** | rendered on 2 screens |
| `interview_scores` (0 rows) | **UNKNOWN** | scorecards exist, lines unused here |

**No file was found that is provably dead.** Zero-row tables may simply be unused
in this tenant.

---

## 32. Industry portability matrix

Assessed against the current code. "Works with configuration" means using
mechanisms that already exist (lookups, term packs, pipelines, approval matrix).

| Industry | Works as-is | With config | Needs extension | Hard-coded blocker |
|---|---|---|---|---|
| TPIA | ✔ | — | — | — |
| Laboratory | ✔ | labels | — | — |
| Manufacturing | — | ✔ | person-spec | `WF_TEAM_ROLES` reads oddly |
| Trading | — | ✔ | person-spec | — |
| Professional services | — | ✔ | person-spec | — |
| Construction | ✔ | ✔ | — | — |
| IT services | — | ✔ | person-spec, notice period | `WF_TEAM_ROLES` (FIELD/COORD/OFFICE) |
| Healthcare | — | ✔ | **licence/registration validity**, person-spec | — |
| Education | — | ✔ | qualification as a first-class concept | — |
| Logistics | — | ✔ | person-spec | — |
| Real estate | — | ✔ | person-spec | — |
| Recruitment agency | — | ✔ | **person-spec is critical**, client submission states | — |
| General B2B | — | ✔ | person-spec | — |

**OBSERVATION.** The same two gaps recur in 11 of 13 rows: **the person-spec**,
and `WF_TEAM_ROLES`. No industry is blocked by the TPIA vocabulary that prompted
this audit.

---

## 33. TPIA vs universal matrix

| Feature | Current | Common? | TPIA? | Configurable? | Hard-coded? |
|---|---|---|---|---|---|
| Hiring Request | `hiring_requests` | ✔ | — | statuses, priorities, types | material-change fields |
| Approval | `recruit_approval_*` | ✔ | — | fully | entity list |
| Requisition | `requisitions` | ✔ | — | statuses, types | — |
| Department | vocab engine | ✔ | seed only | ✔ | — |
| Designation | lookup | ✔ | — | ✔ | — |
| Skills / Qualification | requisition columns | ✔ | placeholders only | free text | **absent from request** |
| Candidate | `candidates` | ✔ | — | stage labels | **funnel shape** |
| Inspector / Workforce | `inspectors` | ✔ (as spine) | column vocabulary | — | `WF_TEAM_ROLES` |
| Project / Client / Branch | FKs | ✔ | — | ✔ | — |
| SBU | lookup + term pack | ✔ | — | ✔ | — |
| Deployment / rate basis | `REQ_WORK_MODELS`, `REQ_RATE_BASIS` | ✔ | flavour | ✔ | — |
| Interview / Assessment | `interviews`, `scorecards` | ✔ | — | criteria packs | `IV_ROUNDS`, `IV_MODES` |
| Offer / Joining | `job_offers` | ✔ | — | templates | `OFFER_STATUS` |
| Marketplace | `cx_*` | ✔ | — | — | distinct by design |
| Dashboard / KPI / SLA | `recruit_kpi`, `recruit_cc` | ✔ | **none found** | bases | funnel order |

---

## 34. Critical findings

| ID | Cat. | Severity | Finding | Evidence | Business impact | Urgent prod fix? |
|---|---|---|---|---|---|---|
| F-01 | A | **High** | Two candidate lifecycle systems coexist; the configurable one is 0.2% adopted | §17 | Universalisation mechanism exists but does not govern behaviour | No — design decision |
| F-02 | F | **High** | Approval matrix wired for 2 of 4 entities; 0 rules configured | §7.1 | Requisition/Salary rules are configurable and inert | No — but the screen misleads |
| F-03 | E | **High** | No person-spec on the hiring request; `experience_max` absent system-wide | §10, §14 | Approval does not constrain recruitment; worst for agency/staffing | No |
| F-04 | M | Medium | Two newest screens make 0 terminology calls, contradicting ADR-002 | §22 | Industry re-wording does not reach the hiring request | No |
| F-05 | H | Medium | Candidate register unscoped; no office dimension; record guarded by role only | §28 | Company-wide candidate visibility | **Depends on answer to OQ-3** |
| F-06 | I | Medium | Requisition budget editable after approval with no re-approval | §10 | Approved money can drift | No |
| F-07 | B | Low | `WF_TEAM_ROLES` hard-coded FIELD/COORD/OFFICE, rendered on 2 screens | §18 | Reads oddly outside field services | No |
| F-08 | B | Low | TPIA placeholder copy in `requisition_form.php` (4 sites) | §18 | Cosmetic industry signal | No |
| F-09 | L | Medium | 0 HTTP tests, 0 browser tests for recruitment | §29 | Delivery layer unverified | No |
| F-10 | J | Low | Offer transitions absent from `act_log` and stage ledger | §8 | Audit gap on a commercial action | No |
| F-11 | G | Low | Two module landings (`/recruitment`, `/recruitment-cc`) | §16 | Possible confusion | No |
| F-12 | C | Info | Three separate approval-rule screens across modules | §16 | Not a recruitment duplicate; recorded for architecture review | No |

---

## 35. Open questions

| ID | Question | Why it cannot be answered by audit |
|---|---|---|
| OQ-1 | Should the configurable pipeline replace `CAND_STAGES`, coexist, or be retired? | Requires a product decision about migration of 935 rows |
| OQ-2 | Wire `REQUISITION`/`SALARY` approvals, or remove them from the matrix? | Business policy |
| OQ-3 | Should candidates acquire an office dimension? | Confidentiality policy + migration |
| OQ-4 | Is `is_coordinator_level()` on candidate screens intentional? | No comment states intent |
| OQ-5 | Should the person-spec sit on the request (binding) or the requisition (flexible)? | Business policy |
| OQ-6 | Is the absence of `act_log` on offers intentional? | No comment states intent |
| OQ-7 | Should requisition budget changes force re-approval? | Business policy |
| OQ-8 | Do two module landings serve different audiences? | Needs user evidence |
| OQ-9 | What is actual mobile behaviour at 360/390/412px? | Needs a browser pass |

---

## 36. Evidence index

| Claim | Location |
|---|---|
| Candidate→workforce conversion | `lib/recruit.php:2211`; locking `:2186`; emp-code claim `:2192` |
| `WF_TEAM_ROLES` | `lib/recruit.php:1904`; used `views/ops/candidate_detail.php:531`, `views/ops/requisition_form.php:230` |
| `REQ_WORK_MODELS`, `REQ_RATE_BASIS` | `lib/recruit.php:19,21` |
| `RECRUIT_ENGAGEMENT_MODES` | `lib/recruit.php:1300-1309` |
| `CAND_STAGES`, `CAND_SOURCES`, `REQ_STATUS` | `lib/ops.php:70,83,53` |
| `HREQ_STATUS`, `HREQ_EXECUTABLE` | `lib/hiringreq.php:37,47` |
| `hreq_appr_ctx` headcount vs commitment | `lib/hiringreq.php:389-417` |
| Direct-path setting + block | `lib/hiringreq.php:519-533`; enforced `lib/ops.php:5858`, `lib/projcosting.php:350` |
| Headcount ceiling + post-write recheck | `lib/hiringreq.php:540,565,1269` |
| Request→requisition carry-across | `lib/hiringreq.php:1255-1265` |
| `appr_start` production call sites | `lib/hiringreq.php:919,1003`; `lib/recruit_offer.php:311` |
| `APPR_ENTITIES` | `lib/recruit_approval.php:21` |
| Approval rule schema | `lib/recruit_approval.php:33-44` |
| Coarse legacy stage sync | `lib/recruitpipe.php:451-459` |
| Candidate audit ledger | `lib/recruitpipe.php:445-449` |
| Candidate register unscoped | `lib/ops.php:6503` |
| Requisition register scoped | `lib/ops.php:5840` |
| Candidate route guard | `lib/ops.php:6867` |
| Careers served pre-router | `index.php:1099-1101` |
| `RCC_DEPARTMENTS` + override | `lib/recruit_cc.php:51-53`, `:33` |
| `RCC_TERMINAL` | `lib/recruit_cc.php:67` |
| TPIA placeholders | `views/ops/requisition_form.php:159,340,355,395,409` |
| Recruitment permissions | `lib/access.php:156,198` |
| Live row counts, schema, tenancy | read-only `information_schema` and `SELECT COUNT(*)` against `rqv_ui` |

---

## 37. Recommended next audit / design decisions

**This section recommends nothing be built.** It lists what the design phase
should decide first, ordered by how much else depends on the answer.

1. **Decide OQ-1 (pipeline vs `CAND_STAGES`) before anything else.** Every other
   universalisation question — funnel shape, industry stages, KPI grouping —
   depends on which lifecycle is authoritative. Auditing further without this
   answer produces work that may be discarded.
2. **Decide OQ-5 (where the person-spec lives).** It determines whether approval
   is binding, and it is the gap that recurs in 11 of 13 industries.
3. **Decide OQ-2 and OQ-3**, both of which have migration consequences and should
   not be discovered late.
4. **Then run two audits this one could not do:** a browser pass for OQ-9, and an
   HTTP-level permission pass for OQ-4 and F-05.
5. **Do not begin by removing TPIA vocabulary.** On this evidence it is the
   smallest of the obstacles, and removing it would not move any industry from
   "needs extension" to "works with configuration".

---

## Audit conformance to the protected architecture

| Principle | Verified? | Evidence |
|---|---|---|
| One approval engine | ✔ | only `recruit_approval_*` in recruitment |
| One SLA/notification engine | ✔ | `recruit_kpi.php` + `recruit_approval.php` |
| One KPI engine | ✔ | `recruit_kpi.php` |
| One configurable pipeline | ✔ | `recruitpipe.php` — but see §17 |
| No duplicate identity engines | ✔ | `candpool.php` converges; `cx_identity_link` is a ledger |
| No canonical Person table introduced | ✔ | none found |
| Requisition ≠ Marketplace Requirement | ✔ | `requisitions` vs `cx_requirements`, separate tables and screens |
| `inspectors` is the workforce spine | ✔ | `recruit.php:2211` |
| `users` is not the person master | ✔ | no recruitment write to `users` |
| `business_partners` is the org spine | ✔ | `candidates.client_id` → `business_partners` |
| Taxonomy ≠ Department | ✔ | `lookup_*` vs `hr_department` vocabulary |

**No violation of the protected architecture was found.**
