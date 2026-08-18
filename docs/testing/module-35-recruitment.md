# Inspection Ops — Recruitment / Workforce — Test & Documentation Report

> **Prompt 3 · Module MOD-HIRING.** Read from `lib/ops.php` (`ops_candidates`,
> candidate-stage/hire, `ops_requisitions`, `ops_inspectors`, `next_emp_code`),
> `lib/recruit.php` (`req_cost_buildup`/`req_commercials`, `cand_find_duplicates`,
> `recruit_fit_score`, `assignment_commercials`, `recruit_data`/`ops_recruitment_home`),
> `lib/workforce.php` (`ops_inspector_availability`, `reporting_chain`, `ops_work_norms`,
> `report_approver_user_id`). Views `recruitment_home.php`, `requisition_*.php`,
> `candidate_list.php`, `inspector_form.php`.

| | |
|---|---|
| **Module** | Recruitment / Workforce (MOD-HIRING) · Area HR/Operations |
| **Personas** | P-HR/P-COORD (candidates/requisitions), P-MANAGER (approve requisition), P-MASTER |
| **Risk weight** | Medium — deputation resourcing; the hire-to-inspector conversion seeds the workforce master |
| **Verdict** | Complete-with-defects (confirm conversion atomicity, person dedup, SBU scoping) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

The deputation-resourcing pipeline: a **requisition** (management-approved position,
`requisitions`) is raised; **candidates** (`candidates`, one row per CV/application) move
RECEIVED → SUBMITTED → SHORTLISTED → INTERVIEW → OFFERED → **ACCEPTED (Hired)** (+ HOLD/
REJECTED/WITHDRAWN/OFFER_DECLINED); on hire, a candidate becomes an **inspector**
(`inspectors`) on our roll (OWN) or an agency roll (AGENCY), with placement fee/guarantee, and
the requisition flips to HIRED. Commercial intelligence (`req_cost_buildup`/`req_commercials`,
`recruit_fit_score`, `assignment_commercials`) supports sourcing. The **workforce** side owns
the availability board, work norms, the reporting-manager chain (used for report approval),
and emp-code series.

Screens: `/recruitment`, `/candidates`, `/candidate?id=`, `/candidate-*`, `/requisitions`,
`/requisition?id=`, `/m/inspectors*`, `/availability`, `/work-norms`, `/hierarchy`. Tables:
`candidates`, `candidate_events`, `requisitions`, `inspectors`, `inspector_certs`, `work_norms`,
`agencies`.

---

## B. Screen-by-screen catalogue

**`/recruitment`** — command centre (fit scores, requisition health, deploy readiness,
expiring deputations). **`/candidates`** — list + stage filter; **`/candidate`** — CV, stage,
client submission/interview, credential request, commercials. **`/requisitions`** — list/new/
detail. **`/m/inspectors`** — workforce master ("add engineer": roll, team role, reporting
manager, salary/agency cost). **`/availability`**, **`/work-norms`**, **`/hierarchy`** (org tree).

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-HIRE-form-001 | Stage move rejects an unknown stage; hire only from ACCEPTED + `make_inspector` + no existing inspector link. |
| TC-HIRE-form-002 | Inspector: staff_kind whitelisted; weekly days ∈ {5,5.5,6}; team role whitelisted; emp-code auto if blank; salary/agency cost gated by `can_see_salary`. |
| TC-HIRE-form-003 | Availability: `day` matches YYYY-MM-DD; status validated; AVAILABLE/ON_JOB delete the override. |
| TC-HIRE-form-004 | Same-client resubmission needs an explicit "submit anyway" ack. |

---

## D. Functions & logic

- **Convert candidate → inspector** (candidate-stage, ACCEPTED + make_inspector): reads roll
  (OWN/AGENCY), kind (AGENCY→SUBCON), inserts the inspector with agency/placement fields, links
  `candidates.inspector_id`, flips the requisition to HIRED. **Not transactional** — an insert
  succeeding but the requisition update failing leaves an orphan; duplicate inspectors possible
  if the same person is ACCEPTED on two candidate rows (guarded only by `empty(inspector_id)`)
  (GAP-HIRE-001). **TC-HIRE-fn-001.**
- **Sourcing economics** (`req_cost_buildup`): own-payroll adds statutory; manpower agency
  adds statutory×(1+agency%); sub-con lump; freelancer fee (no statutory). **TC-HIRE-fn-002.**
- **Fit score** (`recruit_fit_score`): explainable 0–100 from trade/skill/cert substring match
  (no stemming/synonyms). **TC-HIRE-fn-003.**
- **Reporting chain** (`reporting_chain`, `report_approver_user_id`): inspectors carry
  `reports_to_id` → a user; a closed job's report routes to that manager. **TC-HIRE-fn-004.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| candidate RECEIVED → … → ACCEPTED | stage move | valid stage |
| ACCEPTED → inspector | hire | make_inspector + no existing link |
| requisition OPEN → … → HIRED/CLOSED/CANCELLED | conversion/close | auto-HIRED on hire |
| inspector ACTIVE ↔ INACTIVE | edit | board reads ACTIVE |
| placement PROVISIONAL → CONFIRMED/WAIVED | guarantee period | — |

- **TC-HIRE-life-001:** hiring a candidate creates one inspector and flips the requisition.
- **TC-HIRE-life-002:** the same person hired twice can create a duplicate inspector (GAP).

---

## F. Roles, permissions & data scope

Command centre: `mod.hiring.view`/master. Candidate/requisition write + pipeline view:
`is_coordinator_level()`. Allowances: Super Admin. Availability: `can_manage_availability`.
Work norms: master/`master.manage`. Report approval: the inspector's manager /
`workforce.report.approve`. **Requisitions/jobs office+SBU scoped; candidates SBU-only (blank
SBU in-scope)** (GAP).

- TC-HIRE-perm-001 (candidate write without coordinator level) → refused.
- TC-HIRE-scope-001: candidates leak across SBUs when SBU blank.

---

## G. Settings

`emp_code_prefix` (staff series; contractor series fixed EMP/SC-/FL-), daily hours cap,
half-day hours, default weekly days, `recruit_engagement_mode` (BOTH/RECRUITMENT/MANPOWER).
**TC-HIRE-set-001:** emp-code prefix drives new staff codes.

---

## H. Cross-module integration

**Jobs allocation** (a hired inspector is allocatable; board derives ON_JOB — MOD-05),
**Deputation** (deploy readiness reuses `pdso_mob_readiness` — MOD-05), **Competence** (certs
feed fit-score + the allocation gate — MOD-24), **Report approval** (reporting manager routes
sign-off — MOD-05). Idempotency: convert guarded only by the inspector link.

---

## I. Data integrity & audit

`candidate_events` logs stage changes. Person identity is **heuristic** (last-10 mobile /
email); `person_ref` linking is manual — the same human persists as many candidate rows,
double-counting the pipeline (GAP-HIRE-002). The inspector `name` is denormalised alongside
first/middle/last (sync risk). **TC-HIRE-int-010:** a hire links exactly one inspector;
**TC-HIRE-int-011:** stage history is complete.

---

## J. Reports & outputs

The command centre (fit/health/readiness), candidate list, requisition detail, the workforce
master, the org tree, the availability board. **TC-HIRE-out-001:** requisition commercials
(planned vs approved vs actual) roll up across hires.

---

## K. Negative, edge & resilience

Hire with an existing inspector link (blocked); the same person ACCEPTED on two rows
(duplicate inspector — GAP); a partial conversion (orphan); a candidate with blank SBU (leaks);
a same-client resubmission without ack (blocked); an inactive inspector (off the board).

---

## L. TPIA operational suitability

Models deputation resourcing end-to-end: a requisition, a CV pipeline with client-submission/
interview tracking, sourcing economics (own vs agency vs freelancer), and a clean hand-off to
the workforce master and the allocation/competence gates. The conversion atomicity and person
dedup are the items to firm up.

## M. Management usefulness

Requisition health, fit scores, deploy readiness, expiring deputations, placement-fee status,
and the org/reporting chain. Confirm pipeline counts are per-person, not per-CV.

## N. UI/UX

Command centre, guided stage flow with hire, "add engineer" form, availability board, org tree.
Terminology via `T*()`.

## O. Security

Candidate/requisition write coordinator-gated; salary/agency cost gated; allowances Super-Admin;
**conversion not transactional, candidate SBU scoping leaky, person dedup heuristic** — firm up.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E pipeline |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | **Gap** | §F candidate SBU |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §D convert |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I dedup/atomicity |
| 14 Audit | Y | §I events |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Partial | CV paste/extract |
| 22 Notifications | Partial | credential email |
| 23 Offline | N-A | — |
| 24 AI | Partial | CV extract/fit |
| 25 Licensing | Y | hiring module |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | guarantee period |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-HIRE-001 | (verify — Major) | **Convert-to-inspector is not transactional and un-deduplicated** — an insert succeeding but the requisition update failing leaves an orphan, and the same person ACCEPTED on two candidate rows creates duplicate inspectors (guarded only by `empty(inspector_id)`). Wrap in a transaction; dedup on phone/email. |
| GAP-HIRE-002 | (verify) | **Person identity is heuristic** (last-10 mobile / email) and `person_ref` linking is manual — one human persists as many candidate rows, double-counting the pipeline. Strengthen identity threading. |
| GAP-HIRE-003 | (verify) | **Candidate SBU scoping treats blank SBU as in-scope** — unscoped candidate rows leak across SBUs. Apply scoping; and `recruit_deploy_readiness` hard-codes compliance gates to false until an inspector exists. |

---

## R. Traceability

RTM slice: `/recruitment`, `/candidates`, `/candidate-*`, `/requisition*`, `/m/inspectors*`,
`/availability`, `/hierarchy` × dims 1–29 → TC-HIRE-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — conversion atomicity, person dedup, and candidate SBU
scoping are the exit conditions.
