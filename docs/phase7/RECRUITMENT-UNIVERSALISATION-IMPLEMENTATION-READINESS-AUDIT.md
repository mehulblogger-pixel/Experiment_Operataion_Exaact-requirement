# EXAACT Recruitment Universalisation
## Implementation Readiness Audit

**Status:** READ-ONLY AUDIT — no implementation authorised, nothing built
**Date:** 2026-09-29
**Audited at HEAD:** `0e91b4e` on `claude/testing-branch-setup-0gqe8n`
**Method:** direct inspection of the repository at that HEAD — source files, constants,
routes, schema-creating code and the test tree. Previous reports were **not** taken on
trust; every fact below cites the file and line it came from.

---

## 1. Executive disposition

> ## READY WITH SPECIFIC PRE-IMPLEMENTATION CONDITIONS

The locked decisions can be implemented **inside the existing architecture**. Every
engine the programme needs already exists and is stronger than the specification's
summary suggests — the approval engine alone is ~2,400 lines with delegation, SLA,
escalation, notification gating and requester resolution. **Nothing requires a new
engine.**

**Updated by Task 7C (§20a):** of the three blockers below, **F3 is withdrawn** — there
was no conflict — and **F1 and F2 are resolved as decisions and deferred to Gates 5 and 4
as work**. **Gates 0 and 1 are unblocked.** The three paragraphs that follow are the
original 7B statement, kept for the record; read §20a for the verified position.

Three things make this "with conditions" rather than "ready":

1. **Two locked rules meet existing, deliberate, documented behaviour that contradicts
   them** — `role_defaults_base('ADMIN')` grants **every permission in the product**
   (against "Administrator must NOT be unrestricted"), and segregation of duties is
   **entity-scoped to HIRING_REQUEST with a stated master exception** (against "no
   self-approval loophole"). Neither may be silently adapted in either direction.
2. **The legacy stage is not merely a second reader — it is the only writer at insert
   time** (`stage VARCHAR(20) DEFAULT 'RECEIVED'`), and the KPI engine's current-state
   queries read it exclusively. This is the largest migration surface and it gates
   most other gates.
3. **A route-name collision makes part of the pipeline surface unreachable** — see
   finding **F7**. It must be confirmed and fixed before the pipeline can be called
   authoritative.

None of the three is a business decision. All three are implementation work with a
known shape.

---

## 2. Repository state

| Item | Value |
|---|---|
| Branch | `claude/testing-branch-setup-0gqe8n` |
| HEAD audited | `0e91b4e` |
| Working tree at audit start | clean |
| Files modified by this audit | **none** |
| Files created by this audit | **this document only** |
| Commits / pushes | **none** |
| Application, schema, database, production | **untouched** |

**No database file exists in the repository** (`find` for `*.sqlite*` / `*.db` returns
nothing; `phpapp/data/` holds only three JSON seed files). **The "935 candidates on
legacy stage, 2 on a configured pipeline" figure therefore cannot be re-verified from
repository evidence at this HEAD** — it came from a live/dev database. What the code
*does* confirm is the mechanism that produces that ratio; see §7.

## 3. Authoritative documents

| Order | Document | Lines |
|---|---|---|
| 1 | `docs/phase7/RECRUITMENT-UNIVERSALISATION-BUSINESS-DECISION-CLOSURE-PACK.md` | 332 |
| 2 | `docs/phase7/RECRUITMENT-UNIVERSALISATION-BUSINESS-RULES-SPEC.md` | 4,078 |
| 3 | `docs/phase7/RECRUITMENT-UNIVERSALISATION-AUDIT.md` | — |
| 4 | Prior Phase 7 decisions — `Q32-WORKFORCE-INSPECTOR-BUSINESS-DECISION.md`, `P7-TEAMROLE-RB1-RB2.md`, `R20-INSPECTOR-DUPLICATE-CLOSURE.md` |  |

The Closure Pack governs its seventeen decisions; the Specification governs everything
else; its Sections 1–19 govern with Section 20 subordinate. **This audit changes no
decision** and resolves no business question.

## 4. The seven remaining implementation-detail items

| # | What it actually is, from the code | Class | Resolution route |
|---|---|---|---|
| **C15** | The default pipeline the legacy candidate population maps onto. `recruitpipe_seed()` already ships templates and `recruitpipe_for($req)` already resolves one by rule (`applies_sbu`, `applies_department`, `applies_grade`, `applies_employment`…), and `recruitpipe_default()` exists | **B — implementation choice** | Use the existing resolver, with `recruitpipe_default()` as the fallback. No new concept and no owner decision needed |
| **C16** | The legacy-stage → pipeline-stage mapping table. The legacy set is the 10 `CAND_STAGES` values (`lib/ops.php:70`); the target is a configured stage's `kind` + `stage_key` | **B — implementation choice**, gated on C47's closed kind | A per-pipeline mapping expressed in the same configuration surface as the stages themselves |
| **C17** | One-off migration versus lazy-on-next-touch | **B — implementation choice** | The codebase's own idiom is lazy and additive (`recruitpipe_migrate()` is `static $doneAt` guarded and runs at boot; every column is added with `ensure_column`). Lazy-on-touch matches it |
| **C18** | Whether terminal legacy candidates are migrated at all | **B — implementation choice** | `recruitpipe_legacy_terminal()` (line 404) already names the four closed legacy values, so the population is precisely identifiable either way |
| **C22** | Whether Role Workspaces are the per-role landing mechanism within the one home | **C — existing architecture reconciliation** | Role Workspaces exist; `ops_recruitment_home()` (route `recruitment`, `lib/ops.php:3492`) is the host. A reconciliation, not a decision |
| **C25** | The candidate state machine and its transition triggers | **A — already determined by locked rules** | D1 makes the pipeline authoritative, Q2 derives "active" from stage kinds, C47 supplies the closed kind and C14 splits current-state from history. **The state machine is a design artefact to be written down, not a decision to be taken** |
| **C46** | The exact codes for the two B4 capabilities | **B — implementation choice** | The convention is visible and mechanical: `ACCESS_MODULES` generates `mod.<module>.view` / `.edit`, and module-scoped rights read `hiring.admin`. `hiring.material_change.propose` / `hiring.review.clear` fit it |

**None of the seven is class D.** No further owner decision is required for any of them.

## 5. Existing architecture map

| Area | Existing component (verified) | Verdict |
|---|---|---|
| **A · Pipeline** | `lib/recruitpipe.php` — `recruit_pipelines` + `recruit_stages` tables, `RPIPE_STAGE_KINDS` (line 24), `RPIPE_COND_OPS`, `recruitpipe_for()`, `recruitpipe_effective_stages()`, `recruitpipe_stage_applies()` | **REUSE + EXTEND** — one kind to add |
| **B · Candidate lifecycle** | `recruitpipe_cand_state()` (407) resolves pipeline + effective stages + index; `recruitpipe_cand_goto()` (426) moves | **REUSE + EXTEND** |
| **C · Current-stage storage** | `candidates.stage VARCHAR(20) DEFAULT 'RECEIVED'` (`ops.php:202`) **and** `candidates.pipeline_id` / `pipeline_stage_id`, both `INT NULL` (`recruitpipe.php:85–86`) | **MAP + MIGRATE + eventually DEPRECATE** |
| **D · Stage ledger** | `candidate_events` (`ops.php:207`), written via `rkpi_stage_log()` (`recruit_kpi.php:86`) with `from_code` / `to_code` / `track` / `kind` / `remark` / `actor`; read by `rkpi_stage_history()` (113) | **REUSE — no change needed.** Already carries reason and actor |
| **E · Closed outcomes** | Only in the legacy field: `recruitpipe_legacy_terminal()` = ACCEPTED · REJECTED · WITHDRAWN · OFFER_DECLINED; plus `drop_point` / `drop_reason` captured on loss (`ops.php:6285–6286`) | **EXTEND** — the closed kind + its outcome vocabulary |
| **F · Requirement / requisition** | `candidates.requisition_id` (`ops.php:440`); `requisitions` carries `office_id`, `sbu`, `grade` | **REUSE** |
| **G · Hiring Request** | `lib/hiringreq.php` — `hiring_requests` with `approval_required INT DEFAULT 1`, `snapshot_json`, `decided_by/at`, `requested_by_id` | **REUSE + EXTEND** |
| **H · Versioning** | `reapproval_state VARCHAR(20) DEFAULT 'NONE'` + **one** `approved_snapshot_json` (`hiringreq.php:203–204`). **No version chain. No requisition change control at all.** Precedent: `quote_revisions` (`crm.php:202`, insert at 987, history read at 1727) | **EXTEND an existing pattern** |
| **I · Material change** | `HREQ_MATERIAL_FIELDS` (81), `hreq_material_diff()` (607 loop), `HREQ_REAPPROVAL_BLOCKS` (71), `hreq_is_executable()` (445) | **REUSE + ADJUST** |
| **J · Review Required** | **Absent.** No `review_required`, `requirement_version` or `version_no` column anywhere. Nearest relatives: the weighted match engine in `lib/recruit.php`, and the ledger | **EXTEND** — a new state on an existing controlled path |
| **K · Approval engine** | `lib/recruit_approval.php` — `APPR_ENTITIES` (21), `appr_match()` (1012), `appr_rule_score()` (1040), `appr_start()` (1303), `appr_can_act()` (1517), `appr_guard()` (1561), `appr_act()` (1583), delegation (1094–1206), SLA (1710–1793), inbox, escalation, condition reconciliation | **REUSE** — no redesign |
| **L · Offer lifecycle** | `lib/recruit_offer.php` — `OFFER_STATUS` (32) with nine values; `offer_approve()` (316), `offer_issue()` (323) with `issued_at`, `offer_accept()` (359), decline/withdraw | **REUSE + EXTEND** (audit only) |
| **M · Candidate Hiring approval** | **Absent as an approval entity.** Conversion point `rcv_convert()` carries no approval | **EXTEND** — a fifth `APPR_ENTITIES` value |
| **N · Workforce hand-off** | `rcv_convert()`, called when stage → `ACCEPTED` inside one transaction (`ops.php:6312`, RB-1); `candidates.joined_at` (`ops.php:469`), set by route `candidate-joined` (6242) with `act_log('CANDIDATE', id, 'JOINED')`; cleared at 6214 | **REUSE — and reconcile with C37** (see F1) |
| **O · Role permissions** | `lib/access.php` — `PERMISSIONS`, `ACCESS_MODULES` (302), `role_defaults_base()` (680) returning `['perms','offices','sbus']`, `custom_roles_all()` (70), `role_effective_key()`, `can()` (767) | **REUSE + EXTEND narrowly** |
| **P · Coordinator predicate** | `is_coordinator_level() = is_admin_level() \|\| role IN ('ASST_MANAGER','COORDINATOR')` (`ops.php:604`) — gates `candidates` (6501), `candidate-new/edit` (6517), `candidate-stage` (6250) and `candidate-flow` (`recruitpipe.php:465`) | **RECONCILE (C19)** |
| **Q · Recruitment scope** | `scope_clause($officeCol,$sbuCol)` (`access.php:794`), `scope_office_clause()` (785) | **REUSE** — join through the requisition per A7 |
| **R · Candidate visibility** | Candidate register is **unscoped**: `$where = '1=1'` + stage and free-text filters only (`ops.php:6503`) | **EXTEND** — apply the scope join |
| **S · KPI / SLA** | `lib/recruit_kpi.php` — `rkpi_filled_stages()` / `rkpi_active_stages()` / `rkpi_lost_stages()` (34–36) delegating to `REQF_*` constants; `rkpi_demand()`, `rkpi_settled_rows()` (540), `rkpi_metrics()` all filter `c.stage IN (…)`; **but** `rkpi_stage_durations($id,'PIPELINE')` (581) reads the ledger | **MAP + MIGRATE** |
| **T · Landing / navigation** | `ops_recruitment_home()` (route `recruitment`, 3492); `ops_recruitment_cc()` (`recruitment-cc`, 3496); `lib/recruit_cc.php` | **CONNECT / MAP** |
| **U · Mobile** | `assets/css/app.css` — **67** responsive breakpoint declarations, one stylesheet. **Zero** browser-driven tests (no chromium/playwright reference anywhere in `phpapp/tests/`) | **REUSE + VERIFY** |
| **V · Audit mechanisms** | `act_log()` (entity audit — 31 calls in `hiringreq.php`, **0** in `recruit_offer.php`) and `candidate_events` via `rkpi_stage_log()` | **REUSE one** (C13 says the existing ledger) |

**No area requires a genuinely new engine.** The only new *component* is the Review
Required state (J), and it is a state plus a clearance action on the existing pipeline
path and ledger — not an engine.

## 6. Data model map

| Data need | Existing home | Classification |
|---|---|---|
| Candidate current position | `candidates.pipeline_id` + `pipeline_stage_id` | **REUSE** (already present, nullable) |
| Candidate stage history | `candidate_events` (+ `from_code`/`to_code`/`track`/`kind`) | **REUSE** |
| Closed outcome + reason | `drop_point`, `drop_reason` on loss; ledger `remark` | **REUSE + EXTEND** (outcome subtype per C47) |
| Stage kinds | `recruit_stages.kind`, validated against `RPIPE_STAGE_KINDS` (`recruitpipe.php:332`) | **EXTEND** — one value |
| Requirement ↔ candidate | `candidates.requisition_id` | **REUSE** |
| Hiring Request ↔ requisition | `hreq_to_requisition()`, `hreq_approved_qty()`, `hreq_remaining_qty()` | **REUSE** |
| Requirement version chain | **absent**; `quote_revisions(quote_id, rev, changed_by, changed_at, summary, snapshot)` is the shape | **EXTEND EXISTING PATTERN** |
| Approval records | `recruit_approval_rules` / `_levels` / `_requests` / `_steps` | **REUSE** |
| Material-change records | `reapproval_state` + single `approved_snapshot_json` | **EXTEND** (chain + proposal rows) |
| Review Required | **absent** | **NEW — JUSTIFIED**, as a state on the candidate-requirement relationship (per OPEN-1) plus ledger rows |
| Offer records / history | `job_offers` with `status`, `issued_at`, `approved_at`, `accepted_at`, `declined_at`, `letter_html`, `offer_terms`, `ctc` | **REUSE** — status already carries the A2 boundary |
| Workforce linkage | `candidates.inspector_id`, `rcv_convert()`, `candidates.joined_at` | **REUSE** |
| Role permissions | `role_defaults_base()`, `custom_roles`, access editor | **REUSE + EXTEND** |
| Recruitment scope | `scope_clause()` on the requisition's `office_id`/`sbu` | **CONNECT EXISTING** (join, no column — A7) |
| KPI source data | `REQF_FILLED/ACTIVE/LOST_STAGES` + `candidate_events` | **MIGRATE** the three constants to kind-derived sets |

**No schema change is proposed or made by this audit.**

## 7. Pipeline authority audit

**What writes the current stage.**
1. `candidates.stage` is written **at insert by the column default** — `stage VARCHAR(20) DEFAULT 'RECEIVED'` (`ops.php:202`). Every candidate has a legacy stage whether anyone sets one or not.
2. Route `candidate-stage` (`ops.php:6249`) — the single legacy stage-move choke point, gated `is_coordinator_level()`, passing through `rexec_block_reason()`.
3. **`recruitpipe_cand_goto()` writes BOTH** (`recruitpipe.php:438` and again at 456/458): it sets `pipeline_id`/`pipeline_stage_id`, then performs a *coarse legacy sync* — `stage='INTERVIEW'` at an interview-kind stage, `stage='OFFERED'` at an offer-kind stage — and **never over a legacy terminal**.

**That is the two-half mechanism in one function**, and it is the thing D1/C14 must end.

**What reads the current stage.** The legacy field is read by the KPI engine's
current-state queries (`recruit_kpi.php:339–345`, `540–542`, `687–689`), fulfilment
(`reqfulfil.php:104–126`), the command centre (`recruit_cc.php:230–240`), the execution
gate (`recruit_exec.php:44`), the candidate register and its counts (`ops.php:6504`,
`6510`), Next Action (`nextaction.php:165–186`) and the pipeline engine's own closed
check (`recruitpipe.php:454`, `471`).

**What uses the configurable pipeline.** `recruitpipe_cand_state()`, the candidate
screen plan (`recruitpipe_screen_plan()`, 553), `rkpi_stage_durations(..., 'PIPELINE')`,
and Next Action's next-stage suggestion.

**The bridge required.** The three constants in `reqfulfil.php:35–39` —
`REQF_FILLED_STAGES = ['ACCEPTED']`, `REQF_ACTIVE_STAGES`, `REQF_LOST_STAGES` — are
**the single classification choke point** every consumer already delegates to (KPI, exec
gate, fulfilment, command centre). Making those three derive from **stage kinds** rather
than legacy codes migrates every consumer at once, without touching them. **This is the
highest-leverage reuse point in the entire programme.**

**Data state:** unverifiable at this HEAD (no database in the repository). The
mechanism above is sufficient to expect the ratio to be unchanged, and the
implementation plan must re-measure against the live database rather than assume.

### F7 — a route-name collision makes pipeline per-stage capture unreachable
`case $route === 'candidate-stage'` appears **twice** in the dispatcher: at
`ops.php:3476`, routed to `ops_candidates()` (the legacy stage-move handler at 6249),
and again at `ops.php:3502`, routed to `ops_recruit_candidate_stage()` — *"Per-stage
capture — notes + documents for one pipeline stage"*. PHP evaluates `switch(true)` cases
in order, so **3476 matches first and 3502–3503 is unreachable**. The
`candidate_stage_data` table and `cand_stage_note_save()` exist (`recruitpipe.php:95`,
`115`) with no reachable route. **Confirm by execution before Gate 1** — this audit
cannot run the application.

## 8. Review Required audit

**Nothing exists to reuse as a flag** — verified absent. What *can* be reused:

| Requirement | Existing mechanism |
|---|---|
| The evidence a reviewer sees | The weighted match engine in `lib/recruit.php` — seven factors, per-factor verdicts, header states *"Nothing here writes data or makes a decision on its own"* |
| A single controlled path to gate | Route `candidate-stage` (6249) and `candidate-flow` → `recruitpipe_cand_goto()`; `rexec_block_reason()` already refuses at exactly this point with a readable message |
| Reason + actor capture | `candidate_events.remark` / `actor` via `rkpi_stage_log()` — **already sufficient**, no new table |
| Reject writes a closed stage | Needs C47's closed kind; today only the legacy terminal exists |
| Requirement-specific scope (OPEN-1) | `candidates.requisition_id` is one requirement per candidate row, so "per requirement" maps to the row; a person with several rows has several relationships — which is also how C36's per-person availability must read |
| Visibility (OPEN-2) | Existing view permission for the candidate/process; clearance on a separate capability (C46) |
| Reconsideration (OPEN-3) | The ledger already stores `from_stage`/`to_stage`, so "the stage immediately preceding the rejection" is **derivable from existing history** — no new column |

**No automatic clearing or rejection may be wired to the match engine** — the engine's
own header already asserts it writes nothing, so honouring A1 means *not* changing it.

## 9. Versioning audit

| Element | State |
|---|---|
| Hiring Request versions | **Absent** — one `approved_snapshot_json` |
| Requisition versions | **Absent** — and requisitions have **no change control of any kind** |
| Approved version | Implicit in the single snapshot |
| Pending version | Only as a flag: `reapproval_state` ∈ NONE/REQUIRED/IN_PROGRESS/REJECTED |
| Material fields | `HREQ_MATERIAL_FIELDS` — a PHP constant, Hiring Request only |
| Thresholds | Absent; `hreq_commitment()` computes the total the C45 rule needs |
| Rejected proposal history | **Absent** |
| Effective version | Absent as a concept |
| Candidate ↔ version link | **Absent** |

**One mechanism, two field lists (A6)** is achievable by generalising the
`quote_revisions` shape — `(parent_id, rev, changed_by, changed_at, summary, snapshot)`
— over an entity discriminator, with the material list resolved per entity from
configuration. `reapproval_state` being single-valued is exactly what G2's
one-pending-proposal rule already implies, so the two fit without contradiction.

## 10. Approval audit

| Locked rule | How it fits the existing engine |
|---|---|
| Configurable rules | `recruit_approval_rules` + `_levels`; `appr_match()` / `appr_rule_score()` score against a context supplied at call time — **new-value matching (F1) is existing behaviour** |
| Approval Required ON by default | `hiring_requests.approval_required INT DEFAULT 1` (`hiringreq.php:160`) — **already the default** |
| Missing rule ⇒ wait + configuration problem | `appr_start()` starts no chain when nothing matches, and the record waits. The *surfacing* of it as a named configuration problem is the new part |
| **No self-approval** | `appr_guard()` (1561) enforces it via `hreq_segregation_blocks()` — comparing `requested_by_id → users.id`, *"not a name, not a role"*. **But it is entity-scoped to `HIRING_REQUEST`**, and the source says so: *"Applying segregation of duties to every entity is a customer-visible policy change and is recorded as a Phase-3 question, not slipped in here."* **The Closure Pack answers that recorded question.** Implementation = widen the guard's entity scope. **See F2 for the master exception.** |
| In-flight requests keep their configuration | `snapshot_json` + `submitted_at` already preserve *"what it meant when it was submitted, so a later master edit cannot silently change the business meaning of an approved request"* |
| Material change on proposed values | `appr_match()` takes the context at call time — no mechanism change |
| Offer vs Candidate Hiring distinct | `APPR_ENTITIES` needs a fifth value; `rcv_convert()` is the clean gate |
| Which entities fire | `HIRING_REQUEST` and `OFFER` fire; `REQUISITION` and `SALARY` are configurable and **never fire** — wiring, as documented |

**No approval engine redesign is required.** The engine is materially richer than the
specification credits: delegation with delegator-must-hold-the-role checking
(1541–1549), SLA states, escalation, inbox, and a condition-reconciliation subsystem.

## 11. Offer / workforce audit

The chain exists end to end: `DRAFT → PENDING_APPROVAL → APPROVED → ISSUED → VIEWED →
ACCEPTED / DECLINED / EXPIRED / WITHDRAWN`, with `offer_issue()` refusing an unapproved
offer.

**A2's boundary is directly readable** from `job_offers.status` / `issued_at` — **no
offer-to-version link is needed**, exactly as A2 concluded.

**C37 is very largely already implemented, under an earlier owner decision (RB-2):**

| C37 concept | Existing representation |
|---|---|
| Hired (= Offer Accepted) | `candidates.stage = 'ACCEPTED'`, labelled **"Accepted (Hired)"** (`ops.php:79`) |
| Joining Pending | `stage = 'ACCEPTED'` **and** `joined_at` empty — surfaced today as *"Accepted / hired · Mark as joined once they actually arrive"*, sourced in code as *"candidates.stage=ACCEPTED with no joined_at (RB-2)"* (`nextaction.php:177–181`) |
| Joined | `joined_at` set — *"Joined · Nothing required right now"* (175) |
| Counting joiners | `reqfulfil.php:121` counts filled-stage candidates with `joined_at <> ''` |

So C37 is a **REUSE + naming** exercise, not a build. What is genuinely new is making
the *Hired* point configurable (Q21) and stating the joining-pending visibility rule.

## 12. Permission audit

`can($perm)` (767) resolves licence → module → granted set. `role_defaults_base($role)`
(680) returns **`['perms' => [...], 'offices' => 'ALL|OWN', 'sbus' => 'ALL|OWN']`** —
which *is* the locked model's shape:

> **Role → Permission Profile → Recruitment Scope → actual access**

`role_effective_key()` maps a custom role to its base, and `custom_roles_all()` records
that *"a custom role is NEVER free-floating… can never accidentally grant everything."*
**This is the exact plug-in point for future role profiles, and no per-user layer is
needed or implied.**

Findings:

- **The existing role keys are operations-shaped** — MASTER_ADMIN, ADMIN,
  BUSINESS_DIRECTOR, SBU_HEAD, BRANCH_MANAGER, BRANCH_APP_MANAGER, OPERATION_MANAGER,
  ASST_MANAGER, COORDINATOR, BUSINESS_DEV_MANAGER, KEY_ACCOUNTS_MANAGER,
  MARKETING_MANAGER, MARKETING_EXECUTIVE, FINANCE, INSPECTOR, SR_INSPECTOR. **There is
  no Recruiter, Hiring Manager or Department Head key.**
- **No role default carries any recruitment permission.** `mod.hiring.*` and
  `hiring.admin` appear in `assignable_permissions()` but in no profile — so recruitment
  access today comes only from an explicit grant, or from admin/master.
- `data.salary` exists with help text *"Sensitive — grant only to finance / HR / senior
  managers"* — **B4's salary control needs no new code.**
- `hiring_admin_can() = is_admin_level() || can('hiring.admin')` — the module-scoped
  admin right already exists.

## 13. Mobile audit

One stylesheet, `assets/css/app.css`, carries **67** breakpoint declarations, and the
repository standard already separates phone-first field users from desk-first office
users. Every operational workflow the Pack names is an **ordinary form POST on an
existing route** — `candidate-stage`, `candidate-flow`, `candidate-joined`, the approval
act, interview capture — with no client-side framework in the path. **No architectural
rebuild is implied**; this is layout and target-size verification.

**But no claim can be made:** there are **zero** browser-driven tests in
`phpapp/tests/` (no chromium, no playwright reference). Verification is a real task, not
a formality.

## 14. Protected-module impact

| Module | Dependency found | Regression requirement |
|---|---|---|
| **Operations — KEEP / PROTECT** | `is_coordinator_level()` is an **Operations** role band gating candidate screens (`ops.php:604`); `lib/ops.php` also holds `CAND_STAGES` and both handlers | Reconciling C19 touches the most coupled file in the module. **Full Operations regression required** |
| **Workforce** | `rcv_convert()` creates the workforce record inside the joining transaction; `candidates.inspector_id`; `workforce.php:728` reads candidate `stage` + `joined_at` | Any change to the Accepted/Joined boundary is a **Workforce** change |
| **Reporting / dashboards** | `recruit_kpi.php`, `recruit_cc.php`, `recruit_export.php` all read legacy stage | Re-baseline every recruitment figure before and after the constants change |
| **Marketplace** | `cx_*` tables (ratings, requirements) sit beside requisitions; `seed_scenario_s02.php` links them | **Must stay separate** — no merge of requisitions and `cx_requirements` |
| **Quality / Money** | No direct recruitment dependency found | Smoke regression only |
| **APIs / portal** | `lib/portal.php` reads inspector-linked jobs, not candidates | Low risk; confirm before Gate 5 |

## 15. REUSE / EXTEND / CONNECT / MAP / MIGRATE / DEPRECATE / BUILD matrix

| Verdict | Items |
|---|---|
| **REUSE** | Approval engine and its matcher · delegation/SLA/escalation · `candidate_events` ledger + `rkpi_stage_log()` · match engine as evidence · `scope_clause()` / `scope_office_clause()` · `hreq_approved_qty()` comparison · `hreq_commitment()` · `custom_roles` + access editor · `data.salary` · `job_offers` statuses and `issued_at` · `joined_at` (RB-2) · `rexec_block_reason()` gate · `recruitpipe_for()` resolver · `candpool` convergence |
| **EXTEND** | `RPIPE_STAGE_KINDS` (+ `closed`) · closed-outcome vocabulary · `HREQ_MATERIAL_FIELDS` → per-organisation config · version chain on the `quote_revisions` shape · Review Required state + clearance · `APPR_ENTITIES` (+ Candidate Hiring) · `appr_guard()` entity scope · `role_defaults_base()` (+ three recruitment profiles) · offer auditing onto the existing ledger · candidate register scope join |
| **CONNECT** | Requisition and Salary approval wiring · funnel/KPI summary onto `/recruitment` · scope by join through the requisition |
| **MAP** | Legacy `CAND_STAGES` → stage kinds (C16) · the ten offer business events → `OFFER_STATUS` (C12) |
| **MIGRATE** | `REQF_FILLED/ACTIVE/LOST_STAGES` from legacy codes to kind-derived sets · the legacy candidate population onto a pipeline (C15–C18) |
| **DEPRECATE** | `recruitpipe_cand_goto()`'s coarse legacy sync (451–459) · legacy stage as an authority (retain the column; demote it) |
| **BUILD — justified** | **Review Required** only, and as a state plus a clearance action on the existing path and ledger — **no new engine** |

## 16. Dependency graph

```
F7 route collision  ─────────────────────────┐   (confirm/fix first: the pipeline
                                             │    surface must be reachable)
C47 closed outcomes                          │
   └─▶ RPIPE_STAGE_KINDS + 'closed'          │
          └─▶ REQF_FILLED/ACTIVE/LOST ◀──────┘
                 (the single classification choke point)
                    ├─▶ KPI / SLA  (recruit_kpi.php)
                    ├─▶ exec gate  (recruit_exec.php)
                    ├─▶ fulfilment (reqfulfil.php)
                    └─▶ command centre (recruit_cc.php)
                          └─▶ D1 pipeline authority
                                ├─▶ retire the coarse legacy sync
                                ├─▶ C14 current-state vs history split
                                ├─▶ C15–C18 legacy migration
                                └─▶ Q2 "active process" from kinds
                                      └─▶ D3 visibility · C36 availability

D2 three person-spec fields ─▶ D7 material list (3 of 4 additions)
A6 one version mechanism ──▶ A1 Review Required trigger (effective version)
                              ├─▶ OPEN-1 requirement-specific (candidates.requisition_id)
                              ├─▶ OPEN-2 visibility vs clearance right (C46)
                              ├─▶ OPEN-3 re-entry stage (derive from candidate_events)
                              └─▶ A2 boundary (job_offers.status / issued_at)
F1 ──▶ appr_match() on proposed values ──▶ B2 default ──▶ SELF-APPROVAL (appr_guard scope)
C19 is_coordinator_level() ──▶ A4/B4 role profiles ──▶ role_defaults_base()
C37 ──▶ RB-1 / RB-2 reconciliation (F1 finding) ──▶ Workforce hand-off
```

**Hard dependencies only.** The single most load-bearing edge is
`C47 → REQF_* constants → every consumer`.

## 17. Proposed implementation gates — DO NOT EXECUTE

### Gate 0 — Reachability and baseline
**Objective:** confirm F7 and capture a measured baseline.
**Files:** `lib/ops.php` (3476, 3502), `lib/recruitpipe.php`.
**Reuse:** existing routes. **Data impact:** none. **Permission impact:** none.
**Protected modules:** none. **Tests:** a route-reachability assertion; re-measure the
legacy-vs-pipeline candidate counts against the live database.
**Depends on:** nothing. **Rollback:** trivial — one dispatcher line.

### Gate 1 — Pipeline foundation (closed kind + classification)
**Objective:** add the `closed` kind with configurable outcomes; move `REQF_*` to
kind-derived sets.
**Files:** `lib/recruitpipe.php` (24, 332), `lib/reqfulfil.php` (35–39),
consumers via the constants.
**Reuse:** the kind vocabulary, stage save validation, the three constants as the choke
point. **Extensions:** one kind; an outcome vocabulary through the lookup engine.
**Data impact:** outcome value on a closed move; **no destructive change**.
**Permission impact:** none. **Protected modules:** Reporting/dashboards — re-baseline
every figure. **Tests:** kind validation; classification parity before/after; KPI
figures unchanged for unmigrated data. **Depends on:** Gate 0.
**Rollback:** the constants can return their literal legacy lists.

### Gate 2 — Requirement versioning (both records)
**Objective:** one version chain, two material lists.
**Files:** `lib/hiringreq.php`, `lib/recruit.php` (requisitions), pattern from
`lib/crm.php:202/987/1727`.
**Reuse:** `quote_revisions` shape, `approved_snapshot_json`, `hreq_material_diff()`,
`reapproval_state` as the single pending flag. **Extensions:** chain table; per-entity
material list from configuration; requisition change control (**new ground** — there is
none today). **Data impact:** additive. **Permission impact:** the propose capability
(C46). **Protected modules:** none directly. **Tests:** materiality against the
approved snapshot, not the previous edit; one pending proposal per record; refused and
withdrawn retained. **Depends on:** Gate 1 (for effective-version triggers to have a
closed outcome to write). **Rollback:** the chain is additive; the single snapshot
remains readable.

### Gate 3 — Review Required
**Objective:** the flag, its clearance, and reconsideration.
**Files:** new state on the candidate-requirement relationship; `lib/recruitpipe.php`
gate; `rkpi_stage_log()` for reason/actor; `lib/recruit.php` match engine as evidence
only.
**Reuse:** the ledger (reason + actor already present), the single controlled path,
`rexec_block_reason()`'s refusal idiom, the match engine unchanged.
**Extensions:** the state, the clearance action, the reconsideration action.
**Data impact:** additive. **Permission impact:** the clear-review capability (C46).
**Protected modules:** none. **Tests:** all active candidates flagged, unconditionally;
no score-based clearing or rejection; requirement-specific clearance; mandatory reasons;
reconsideration returns to the preceding stage derived from history; issued-offer
candidates untouched. **Depends on:** Gates 1–2. **Rollback:** the state is additive and
can be ignored by readers.

### Gate 4 — Approval integration
**Objective:** widen segregation to every entity; wire Requisition and Salary; add
Candidate Hiring; surface the missing-rule configuration problem.
**Files:** `lib/recruit_approval.php` (21, 1561), `lib/hiringreq.php`, the requisition
and salary paths.
**Reuse:** the whole engine. **Extensions:** `APPR_ENTITIES` +1; `appr_guard()` entity
scope. **Data impact:** none structural. **Permission impact:** none new.
**Protected modules:** none. **Tests:** no self-approval on any entity; no invented
approver; in-flight requests keep their chain; proposed-value matching; both gates in
the fixed order. **Depends on:** Gate 2 (F1 routes material changes).
**Rollback:** the guard's scope is one condition.
**Pre-condition: F2 must be answered first.**

### Gate 5 — Offer audit, Hired vs Joined, workforce
**Objective:** offer events onto the existing ledger; make the Hired point configurable;
name Joining Pending.
**Files:** `lib/recruit_offer.php`, `lib/ops.php` (6242, 6312), `lib/nextaction.php`,
`lib/workforce.php`, `lib/reqfulfil.php`.
**Reuse:** `OFFER_STATUS`, `issued_at`, `joined_at`, RB-2's existing states.
**Extensions:** audit calls; a Hired-point setting. **Data impact:** none structural.
**Protected modules:** **Workforce** — full regression. **Tests:** every offer event
audited and never rewritten; Hired ≠ Joined under both settings; A2 boundary holds.
**Depends on:** Gate 4. **Rollback:** auditing is additive; the setting has a default.
**Pre-condition: F1 must be answered first.**

### Gate 6 — Permissions and scope
**Objective:** three recruitment profiles; the two capabilities; reconcile C19; scope
the candidate register.
**Files:** `lib/access.php` (680, 302), `lib/ops.php` (604, 6501, 6503).
**Reuse:** `role_defaults_base()`, `custom_roles`, `can()`, `scope_clause()`,
`data.salary`. **Extensions:** three role keys; two capability codes; the register's
scope join. **Data impact:** none. **Permission impact:** the whole gate.
**Protected modules:** **Operations** — `is_coordinator_level()` is an Operations band;
full regression. **Tests:** no per-user layer; Administrator not unrestricted (**F3**);
profiles are defaults not floors; salary off by default; register scoped; no coordinator
without a recruitment right. **Depends on:** Gate 3 (the clear-review right must exist to
be granted). **Rollback:** profiles are data-shaped; the register filter is one clause.
**Pre-condition: F3 must be answered first.**

### Gate 7 — KPI / SLA migration
**Objective:** current state from the pipeline, history from the ledger.
**Files:** `lib/recruit_kpi.php`, `lib/recruit_cc.php`, `lib/recruit_export.php`.
**Reuse:** `rkpi_stage_durations(...,'PIPELINE')` already ledger-based; the `REQF_*`
choke point from Gate 1. **Extensions:** current-state reads move to the pipeline.
**Data impact:** C15–C18 migration executes here. **Protected modules:**
Reporting/dashboards — every figure re-baselined. **Tests:** figure-for-figure parity on
migrated data; a conflict between legacy and pipeline is **surfaced, never guessed**.
**Depends on:** Gates 1–3. **Rollback:** keep the legacy column intact throughout.

### Gate 8 — Mobile verification
**Objective:** prove the seven operational workflows on real mobile browsers.
**Files:** `assets/css/app.css`, the operational views.
**Reuse:** the existing responsive stylesheet. **Data impact:** none.
**Tests:** **the first browser-driven tests in the repository** at 360/390/412 px.
**Depends on:** Gates 3–6 (the workflows must exist to be tested).
**Rollback:** presentation only.

### Gate 9 — Full end-to-end regression
Both engines; tenant isolation; protected modules; the complete journey; every earlier
gate's tests re-run together.

## 18. Test-readiness matrix

| Area | Existing coverage | Gap |
|---|---|---|
| SQLite | Default engine throughout `phpapp/tests/` | — |
| **MariaDB authoritative** | Driver-aware tests exist (15 files reference `db_driver`/mariadb) | **No evidence of a full dual-engine run gate** |
| Tenant isolation | `_rb3s2_tenant.php`, `_rb3_tenant_emp.php` | Extend to new tables |
| Permissions | `test_module02_access.php`, `test_approver_roles.php`, `test_p3m4_security.php` | **No test that Administrator is not unrestricted** |
| Approval | `test_recruit_approval.php`, `test_issue_segregation.php` | **No cross-entity self-approval test** |
| Pipeline | `test_recruit_pipeline.php`, `test_recruitpipe_admin_control.php` | Closed kind; kind-derived classification; **route reachability (F7)** |
| Candidate lifecycle | `test_module35_recruitment.php`, `test_p3m6_concurrency.php` | Authority migration |
| Versioning | **none** | Entire area |
| Review Required | **none** | Entire area |
| Closed outcomes | `test_p5_kpi.php` touches stages | Outcome vocabulary + reporting |
| Offer audit | `test_recruit_offer.php` | **Auditing itself (`act_log` count 0)** |
| Workforce hand-off | `test_p7_teamrole_rb1_rb2.php`, `test_rb3_step3_atomic.php` | Hired-vs-Joined under both settings |
| KPI / SLA | `test_p5_kpi.php`, `test_p7_tpia_revenue_e2e.php` | Parity before/after migration |
| **Mobile / browser** | **zero** | **Entire area — no chromium/playwright anywhere** |
| Protected modules | Broad existing suite (570 test files) | Recruitment-change regression selection |

## 19. Risks

| # | Risk | Why it matters |
|---|---|---|
| R1 | `lib/ops.php` holds the dispatcher, both candidate handlers, `CAND_STAGES` **and** `is_coordinator_level()` | The single riskiest file; Gates 1, 6 and 7 all land in it |
| R2 | The `REQF_*` constants are read by five subsystems | One change moves all of them — powerful, and unforgiving if wrong |
| R3 | The coarse legacy sync writes two truths in one function | Removing it mid-migration can silently change KPI figures |
| R4 | Requisitions have **no change control at all** today | Gate 2's requisition half is genuinely new ground, not an extension |
| R5 | `act_log` count in `recruit_offer.php` is **0** | Offer auditing has no existing behaviour to regress against — write the tests first |
| R6 | Zero browser tests | Mobile verification starts from nothing |
| R7 | Live data state unverifiable from the repository | Re-measure before migrating; never assume 935/2 |
| R8 | A master bypasses `appr_can_act()` early (`1525`) | Interacts with the self-approval rule — see F2 |

## 20. Exact implementation blockers

**Three, and all three are conflicts between a locked rule and existing deliberate
behaviour. None may be resolved by adapting the business rule.**

> **SUPERSEDED IN PART BY §20a (Task 7C).** All three were verified against source.
> **F3 is WITHDRAWN — no conflict exists.** F1 and F2 are **resolved as decisions** and
> deferred as work to **Gate 5** and **Gate 4**. The statements below are the original
> 7B findings, retained as the record of what was found; **§20a carries the verified
> position and the resolutions.**

### F1 — When does the workforce record get created?
**Locked rule (C37):** *"Joining changes the joining state to Joined and permits
workforce handoff."*
**Existing behaviour (RB-1, implemented):** `ops.php:6301–6313` — *"EVERY ACCEPTED
CANDIDATE GETS A WORKFORCE RECORD… Whether a hire becomes a person was never a
recruiter preference"* — `rcv_convert()` runs when the stage becomes `ACCEPTED`, inside
the joining transaction, and the checkbox that used to gate it was deliberately removed.
**The conflict:** the workforce record is created at **Accepted** (RB-1); C37 attaches
hand-off to **Joined**.
**What is needed:** a one-line reconciliation stating whether RB-1's create-at-Accepted
stands with C37's Joined state layered on top (the reading the code and RB-2 already
support), or whether hand-off moves to Joined. **This is a prior-decision
reconciliation, not a new question** — but it must be stated before Gate 5.

> **RESOLVED by §20a.** The conflict is sharper than stated here: the row is created
> **`status='ACTIVE'`**, and nothing operational gates on `joined_at`. **RB-1's creation
> at Accepted stands; the operational boundary moves to `inspectors.status`.** Work
> deferred to **Gate 5**.

### F2 — Does the master exception to segregation survive?
**Locked rule:** *"The requester must not approve their own submission. Do not create a
self-approval loophole."*
**Existing behaviour:** `hreq_segregation_blocks()` compares `requested_by_id` to the
current user, *"not a name, not a role"* — with **one documented exception: a master**,
because *"a single-administrator workspace has nobody else to approve"*, mirroring
`idems.php`. `appr_can_act()` also returns true early for `is_master_of('hiring')`.
**What is needed:** confirmation that the existing master exception is preserved (it is
the reason single-admin workspaces function) or withdrawn. **Do not widen the guard to
all entities in Gate 4 without an answer**, because widening it inherits the exception
into four more entities.

> **RESOLVED by §20a**, with one owner confirmation outstanding. The exception is the
> **`is_superuser` master flag, not the ADMIN role**; the gap is **entity scope only**;
> the extension point is `appr_guard()` using the engine's own `appr_requester_id()`.
> Work deferred to **Gate 4**.

### F3 — `role_defaults_base('ADMIN')` returns every permission
**Locked rule (OPEN-4):** *"Do NOT make Administrator automatically equivalent to
unrestricted business authority."*
**Existing behaviour:** `access.php:684–685` — `case 'MASTER_ADMIN': case 'ADMIN': return ['perms' => $all, 'offices' => 'ALL', 'sbus' => 'ALL'];`
where `$all = array_keys(PERMISSIONS)`.
**The conflict is direct and in one line.** Narrowing ADMIN is a **customer-visible
change to an Operations-wide role**, not a recruitment-local change.
**What is needed:** whether OPEN-4 constrains the Administrator *role* generally, or
only what Administrator receives *for recruitment*. Gate 6 cannot be planned without
this.

> **WITHDRAWN by §20a.** This finding was wrong in its conclusion, though right on the
> fact. `role_defaults_base()` is the **shipped default**, consulted only when the
> tenant has stored no `role_access` override — so it is *a default, not a floor*, exactly
> as A4 requires. And `can()`'s only blanket bypass is the **`is_superuser` master flag,
> not the ADMIN role**. The locked model is already implemented. What remains is defining
> the shipped Administrator profile, which is ordinary **Gate 6** work under OPEN-4's own
> gate. **Gate 6 can be planned.**

**Everything else is implementable as documented.** F7 (route collision) is a defect to
fix, not a blocker on a decision.

## 20a. Reconciliation of F1 · F2 · F3 (Task 7C)

**Verified independently against source at `fa2c177`.** One finding is withdrawn as an
overstatement of my own; the other two are confirmed with their resolutions determined.
**No application code was changed by this reconciliation** — see the classification.

### F1 — Accepted vs Joined: what "workforce record" actually means

**Evidence table — the six things the prompt asked to be distinguished:**

| Concept | Where it lives today | When it happens |
|---|---|---|
| **A · Preliminary person record** | `INSERT INTO inspectors (…)` in `rcv_convert()` (`lib/recruit.php:2210–2219`) | **At stage = ACCEPTED** |
| **B · Workforce activation / deployability** | **the same INSERT — `status` is hardcoded `'ACTIVE'`** (line 2212) | **At stage = ACCEPTED** |
| **C · Inspector creation** | as A | At ACCEPTED |
| **D · Workforce status** | `inspectors.status` — read as `status='ACTIVE'` by assets (`assets.php:254`), headcount (`audits.php:338`), availability and allocation | `ACTIVE` from creation |
| **E · Joining date** | `candidates.joined_at` (`ops.php:469`), set by route `candidate-joined` (6242) with `act_log('CANDIDATE', id, 'JOINED')`, cleared at 6214 | **Later, separately** |
| **F · Operational eligibility** | follows `inspectors.status='ACTIVE'` + `home_office_id` | **At ACCEPTED** |

**Verified finding — confirmed, and sharper than 7B stated it.** The issue is not *that*
a record is created at Accepted; it is that the record is created **`ACTIVE`**. A
search of the Workforce and Operations spine found **no consumer that gates anything on
`joined_at`** — `workforce_origin()` (`workforce.php:728`) reads it for display only.
So today a **hired-but-not-joined person is already an operationally deployable team
member**, which is precisely what C37 says must not happen.

**Locked rule (C37):** Offer Accepted → **Hired** → **Joining Pending** → **Joined** →
workforce operational handoff; *"Joining-pending people… are not treated as ordinary
active recruitment candidates"*; *"Joining… permits workforce handoff."*

**Resolution — minimum-change architecture:**

> **RB-1 stands: the record is still created at Accepted.** Employee-number claiming,
> duplicate detection, branch resolution, team-role choice, race safety and the identity
> link are all correct and must not move. **What changes is one value: the status the row
> is created with.** Create it in a *not-yet-joined* status at Accepted; **promote it to
> `ACTIVE` when the joining is recorded**, audited on both sides.

This keeps **one** workforce spine, **one** identity, and **one** field that every
consumer already reads — `inspectors.status`. **No second workforce engine, no duplicate
Candidate → Employee identity, and no new gate on every allocation path** (the rejected
alternative — adding a `joined_at` check to each deployment route — would create a
second source of truth for one fact).

**Classification: D — larger implementation, belongs to Gate 5.** The decision is now
determined; the work is not a reconciliation edit. **Why it cannot be done here:** every
`status='ACTIVE'` consumer changes behaviour for hired-not-joined people — asset issue
lists, active headcount, availability and allocation. That is a deliberate,
customer-visible change across **Operations and Workforce**, the two most protected
modules, and it needs their regression suites around it.

**Code / data impact when built:** one changed literal in the `rcv_convert()` INSERT; one
status promotion on the joining path; a status value added to the `inspectors.status`
vocabulary. **No schema change, no migration of existing rows** (existing ACTIVE rows
are people who already joined or were added through Masters).

### F2 — Segregation of duties and the master exception

**Verified finding — confirmed exactly as reported.**

- The rule exists and is correct in form: `hreq_segregation_blocks()`
  (`hiringreq.php:363`) → `hreq_is_own_request()` compares
  **`hiring_requests.requested_by_id` to `current_user()['id']`** — *"not a name, not a
  role."*
- **Two readers, one rule:** the direct decision path `hreq_may_decide()` (367) and the
  chain via `appr_guard()` (`recruit_approval.php:1577`).
- **The gap is entity scope only.** `appr_guard()` returns `''` immediately unless
  `entity === 'HIRING_REQUEST'` (1563), and the source states why: *"Applying
  segregation of duties to every entity is a customer-visible policy change and is
  recorded as a Phase-3 question, not slipped in here."* **The Closure Pack answers that
  recorded question.** So `OFFER`, `SALARY`, `REQUISITION` — and a future
  Candidate Hiring entity — have **no segregation check today**.
- **The exception is `is_master()` — the `is_superuser` flag, not the ADMIN role.**
  `is_master()` = `ua()['master']`, and `ua()` sets master from `$u['is_superuser']`
  (`access.php:729`). It is **code**, one line, stated once, and mirrored by the same
  standing exception in `idems.php` for report finalisation. Its rationale is recorded:
  *"A single-administrator workspace has nobody else to approve."*

**Resolution:**

> **Extend the existing rule through the existing engine — do not build a second
> security layer.** `appr_guard()` gains the generic requester comparison for **every**
> approval-capable entity, using the approval engine's own `appr_requester_id()` /
> `appr_resolve_requester()` (`recruit_approval.php:2292`, `2311`) rather than a
> per-entity helper. Delegation is unaffected: `appr_can_act()` already requires a
> delegator to genuinely hold the delegated role (1541–1549), and segregation is asked
> separately, so a delegate cannot be used to approve the requester's own record.

**On the master exception — flagged, not decided by me.** Preserving it is the safe
default and is what I recommend, because **removing it would leave a
single-administrator workspace unable to approve anything it raises** — recruitment
would be unusable in exactly the smallest installations. But the Closure Pack's
guardrail says *"Do not allow self-approval"* without naming an exception, so **the
owner should confirm explicitly** whether the existing master exception survives being
carried into four more entities. **Until confirmed, do not widen the guard** — widening
it inherits the exception wherever it goes.

**Classification: D — larger implementation, belongs to Gate 4**, with one owner
confirmation attached (above). **No code change here:** widening segregation changes
behaviour on three live entities that have behaved one way since Phase 6, and needs the
approval regression suite around it.

**Audit retention when built** — all already present in `recruit_approval_requests` /
`_steps` and `act_log`: requester, approver, entity, action, delegation, timestamp,
result. Nothing new to store.

### F3 — Administrator permissions: **finding withdrawn**

**My 7B wording was wrong and I am correcting it.** 7B said *"The conflict is direct and
in one line."* **There is no conflict.** The fact was right; the conclusion was not.

**What the code actually does:**

1. `can($perm)` (`access.php:767`) = licence check → **`$a['master'] || in_array($perm, $a['perms'])`**. The **only** blanket bypass is `master`.
2. `master` is the **`is_superuser` flag**, not the ADMIN role — `ua()` (729):
   `$role = !empty($u['is_superuser']) ? 'MASTER_ADMIN' : strtoupper($u['role'])`.
   **So a user whose role is ADMIN does not bypass `can()`.**
3. `role_perms($role)` (557–565) reads the tenant's stored **`role_access`** override
   **first**, and only falls back to `role_defaults_base()` when none is stored.
   **`role_defaults_base('ADMIN') => $all` is therefore a SHIPPED DEFAULT, not a
   floor** — an administrator can store a narrower set through the existing access
   editor and it wins.

**So the locked model is already implemented:** **Role → Permission Profile (stored
override, else shipped default) → Recruitment Scope (`offices`/`sbus`, `scope_clause()`)
→ actual access (`can()`)** — and A4's *"a default, not a floor"* holds literally.

**What genuinely remains** is narrower and is not a blocker: the **shipped default value
for Administrator** is "every permission", so a brand-new organisation's Administrator
starts unrestricted until someone narrows it. **OPEN-4 already governs this** —
Administrator is one of the eight roles whose profile *"must be explicitly defined
before its corresponding workflow capabilities are enabled."* Defining the shipped
Administrator profile is therefore **ordinary Gate 6 work under OPEN-4's own gate**, not
a conflict to reconcile.

**One related item for Gate 6, recorded not resolved:** `is_admin_level()`
(`ops.php:603`) grants decision-shaped rights to a **role band** — `MGMT_ROLES`, seven
roles including ADMIN — outside `can()`. `hreq_can_decide()` and `is_coordinator_level()`
both rest on it. That is the **same defect class as C19** and belongs with it.

**Classification: B — documentation / interpretation mismatch only. No code change
required, now or later, to satisfy the locked rule.**

### Cross-check of the three together

| Scenario | Today | Verdict |
|---|---|---|
| Administrator raises a Hiring Request, then tries to approve it | ADMIN is **not** master, so `hreq_segregation_blocks()` returns true → **refused** | **Already correct** |
| Administrator proposes a material change, then tries to approve it | Runs on the `HIRING_REQUEST` entity, so `appr_guard()` applies → **refused**. *If a future material-change entity is added, it needs the F2 widening first* | Correct today; **F2 dependency noted** |
| Administrator acts as a delegated approver | `appr_can_act()` requires the delegator genuinely to hold the configured role; segregation is asked separately | **Already correct** |
| Candidate accepts the offer | Hired ✔ and Joining Pending ✔ (RB-2) — **but the workforce row is created `ACTIVE`** | **F1 — premature** |
| Candidate joins | `joined_at` set; **no status change** | **F1 — handoff not marked** |
| A retained preliminary record must not imply Joined | It currently implies **ACTIVE** | **F1** |

**F1 is the only live behavioural gap.** F2 is an entity-scope gap. F3 is not a gap.

### Test scenarios identified (none written — no code changed)

**F1 (Gate 5):** offer accepted → Hired with Joining Pending · workforce row exists but
is **not** ACTIVE · not offered for allocation or asset issue while joining-pending ·
joining recorded → status promotes to ACTIVE, audited · un-joining reverses it ·
existing RB-1 consumers and workforce reports unaffected for already-joined people.
**F2 (Gate 4):** requester refused on **each** approval entity · an unrelated approver
succeeds · a delegate of a legitimate approver succeeds · a delegate cannot approve the
requester's own record · the master exception behaves exactly as the owner confirms.
**F3 (Gate 6):** Administrator can administer configuration · a stored `role_access`
override for ADMIN wins over the shipped default · Administrator cannot bypass approval
or segregation · no per-user recruitment permission layer appears.
All of the above on **SQLite and MariaDB**, which the existing test architecture supports.

### Protected-module impact of this reconciliation

**None.** No application file was changed. When the two deferred items are built:
**F1 → Workforce and Operations** (every `status='ACTIVE'` reader), plus Reporting for
headcount; **F2 → the approval surfaces of Offer, Salary and Requisition**. Quality,
Money, Marketplace, dashboards and APIs are untouched by either.

### Net effect on the blocker list

| Blocker | Status after 7C |
|---|---|
| **F1** | **Resolved as a decision; deferred to Gate 5 as work.** Architecture determined: keep RB-1's creation, move the *operational* boundary to `inspectors.status` |
| **F2** | **Resolved as a decision; deferred to Gate 4 as work**, with **one owner confirmation outstanding** — does the documented master exception survive being widened to all entities? |
| **F3** | **WITHDRAWN.** No conflict exists. The shipped Administrator profile is ordinary Gate 6 work under OPEN-4 |
| **F7** | Unchanged — a defect to confirm and fix in Gate 0 |

**Gate 0 and Gate 1 are unblocked by all three** — neither touches workforce timing,
approval segregation or role profiles.

## 20b. Gate 0 — reachability, baseline and pre-pipeline safety (executed)

**Documentation-plus-minimal-correction gate. Executed at `6238875`.** Everything
below is measured, not inferred.

### F2 — closed and recorded

The owner selected **option C**. Recorded in the Closure Pack §5.12 and its register:
**requester ≠ approver always; the master / superuser exception is configurable per
organisation and OFF by default**; every use is audit-recorded as requester = approver
under the master exception; **no per-user exception**. Migration of existing workspaces
is explicitly left to **Gate 4** as an implementation matter — *"existing workspaces
enabled, new workspaces disabled"* is a discussion hypothesis and is **not** a locked
rule.

### F7 — VERIFIED as a live functional defect, and corrected

**Evidence table (source at `6238875`):**

| Location | Handler | Purpose | Reachable? | Callers / tests |
|---|---|---|---|---|
| `ops.php:3476` (candidate family case) | `ops_candidates()` → `ops.php:6249` | Legacy stage move — **the single execution choke point** (`rexec_block_reason()`, the joining transaction, drop reason/point) | **YES** — matched first | `views/ops/candidate_detail.php:463`; workers `_p6b2_worker`, `_rb3s3_worker`, `_p4_worker`; `test_p3m4_security.php:230`; `test_p3m6_security.php:211`; `tools/p7-browser-uat.js:183` |
| `ops.php:3502` (before fix) | `ops_recruit_candidate_stage()` → `recruitpipe.php:701` | Pipeline per-stage capture — notes, document upload, document delete | **NO — shadowed** | `recruitpipe.php:767, 791, 802` — **three live forms**; no test referenced it by route |

**The defect was worse than 7B reported.** The shadowed handler is **not** dead code —
three live Pipeline-tab forms post to it. Those forms send `do=notes|upload|deletedoc`
and **no `to_stage`**, so they reached the legacy handler, which read `''`, failed
`isset(lk_options_or('candidate_stage', CAND_STAGES)[''])` and answered
**"Unknown stage."** So **stage notes could not be saved, stage documents could not be
uploaded, and stage documents could not be deleted** — three user-facing functions
broken behind a message that described none of them.

**Correction — and why it runs the other way.** The naive fix (removing
`candidate-stage` from the family case) would have handed the route to the pipeline
handler and **silently disabled the execution choke point**. The legacy name was
therefore **not moved**. Per-stage capture took a distinct name,
**`candidate-pipestage`**, chosen so it does not even share the `/candidate-stage`
prefix that the browser UAT's `form[action^=…]` selector matches and `.first()`-picks.

**Changed: four lines.** One dispatcher case name (`lib/ops.php`) and three form actions
(`lib/recruitpipe.php`). **No business logic, no permissions, no lifecycle, no schema.**
Module gating needs no map entry — `ops_module_family('candidate-pipestage')` resolves to
`hiring` through the `candidate` family, asserted in the test, which is why
`candidate-flow` needs no entry either.

### Baseline — candidate pipeline adoption (measured)

**Production is NOT reachable from this container.** The repository holds no database,
and the MariaDB instance available here carries ~300 **regression and mutation
fixtures**, not the live tenant. **The figures below are the seeded regression fixture
measured on `gate0_base`, the database this run's own suite built — not current
production facts.**

| Metric | Measured |
|---|---|
| Total candidates | **935** |
| With a legacy stage (non-empty) | **935 (100%)** |
| With `pipeline_id` | **2** |
| With `pipeline_stage_id` | **2** |
| With both legacy and pipeline stage | **2** |
| With neither | **0** |
| Configured pipelines | **4** |
| Configured stages | **33** |
| **Approval rules configured** | **0** |

**The audit's historical 935 / 2 / 4 / 33 / 0 figures are reproduced exactly on the
fixture.** They remain historical with respect to production, which must be re-measured
before any migration.

### TWO NEW FINDINGS — data integrity, discovered by measurement

**G0-1 · Ten candidate rows carry a legacy stage that is not a valid stage.**

| Value | Rows | Valid `CAND_STAGES` key? | In any `REQF_*` set? |
|---|---|---|---|
| `OFFER` | **9** | **NO** (the valid key is `OFFERED`) | **NO** |
| `' RECEIVED'` *(leading space)* | **1** | **NO** | **NO** |

Verified against `CAND_STAGES` and the lookup-resolved set (10 keys either way), and
against all three classification constants. These ten rows are therefore **neither
filled, nor active, nor lost** — they are **invisible to the funnel arithmetic** — and
**unmapped** for any legacy→pipeline migration. The same ten appear in `scope_regress`
and `ops_reg`, so this is **systematic seed data, not a one-off.**

**Consequence for Gate 1:** C16's mapping table must handle unknown and
whitespace-corrupted legacy values **explicitly and visibly**, not silently drop them.
This is exactly D1's principle 4 — *"a conflict is reported, not guessed."*

**G0-2 · One row is closed on the legacy field while sitting on a live pipeline stage.**
`stage IN (ACCEPTED, REJECTED, WITHDRAWN, OFFER_DECLINED)` **and**
`pipeline_stage_id > 0` — **1 row**. The first real instance of the legacy/pipeline
disagreement class. It must be **surfaced for a human**, per D1.

**Neither is fixed here.** Both are Gate 1 prerequisites, recorded not repaired.

### PART E — the `REQF_*` choke point, measured (and a 7B claim refined)

| Consumer | Uses `REQF_*`? | Reads `candidates.stage` independently? |
|---|---|---|
| `lib/reqfulfil.php` (owner of the constants) | ✔ all three | — |
| `lib/recruit_kpi.php` | ✔ all three | ✔ (`c.stage IN (…)` in demand, settled rows, metrics) |
| `lib/recruit_exec.php` | ✔ filled | ✔ |
| `lib/recruit_fulfil.php` | ✔ filled | — |
| `lib/recruit_cc.php` | ✔ filled | ✔ |

**Refinement of a 7B statement.** 7B called the three constants *"the single
classification choke point."* That is true **for classification** — filled / active /
lost — and **five** files delegate to them. But **twelve** library files read
`candidates.stage` **without** going through them: `candpool.php`, `crmdash.php`,
`nextaction.php`, `opportunities.php`, `ops.php`, `recruit.php`, `recruit_assign.php`,
`recruit_export.php`, `recruit_offer.php`, `recruitpipe.php`, `search.php`, `tosrm.php`.
**Changing the constants moves classification, not every reader.** Gate 1 must treat
those twelve as a separate, enumerated surface.

### PART F/G — legacy and pipeline baselines

**Writers of `candidates.stage`:** the column default `DEFAULT 'RECEIVED'`
(`ops.php:202` — every row gets one at insert, which is why adoption is 935/2); route
`candidate-stage` (`ops.php:6249`); and **`recruitpipe_cand_goto()`'s coarse legacy
sync** (`recruitpipe.php:451–459`), which writes `INTERVIEW` / `OFFERED` and never over
a legacy terminal. **That is two half-mechanisms inside one function** and is what D1/C14
must end.

**The configured pipeline does fall back to legacy today, in three named places:**
`recruitpipe_cand_state()` resolves by rule when no pipeline is locked;
`recruitpipe_legacy_terminal()` gates the flow closed (`recruitpipe.php:454`, `471`);
and `na_candidate()` lets a terminal legacy stage **win over** the pipeline
(`nextaction.php:169–186`). **No authority was changed in Gate 0.**

### PART H/I — test baseline, both engines

| Engine | Result |
|---|---|
| **SQLite** | **15,158 passed · 0 failed** |
| **MariaDB 10.11** (authoritative) | **15,158 passed · 0 failed** |

MariaDB was genuinely run, not assumed: the local server was started, and because
`root` authenticates by unix socket a TCP user was created for the harness
(`DB_DRIVER=mysql`). Targeted suites all green beforehand: recruitment 471 · p4 924 ·
p3m6 259 · p5 kpi 145 · p3m1 approval 114 · p3m4 security 77 · rb3 atomic 83 · teamrole
RB1/RB2 61 · module02 access 21 · quality gate 24 · invoicing 19 · calls 18 · job360 14.

**One consequential detail:** the first full run came back **15,157 / 1**, the single
failure being the **deploy checksum manifest**, stale because two shipped files changed.
It names its own remedy, so `php tools/make_deploy_check.php` was run and
`phpapp/deploy-check.php` regenerated — **669 files, mechanically derived**. The suite is
green on both engines only with that regeneration included.

### PART J/K — the focused test, and its mutation

**`phpapp/tests/test_gate0_stage_route.php` — 24 assertions, all passing.** It reads the
**source**, because the defect was a dispatch-ordering fact: it asserts no second case
claims the bare name, that the family case still owns `candidate-stage`, that
`ops_candidates()` still handles it, that `candidate-pipestage` exists after the family
case and is no longer shadowed, that all three forms post to the new name and none to
the old, that the new name does not share the UAT's prefix, that module family
resolution still returns `hiring`, and that all thirteen routes in the family case are
still listed.

**Mutation:** reverting the case name to `candidate-stage` re-creates the shadowing.
**3 assertions fail**, including the reachability condition itself. The test genuinely
guards the invariant rather than merely passing beside it.

### Gate 0 acceptance — all thirteen criteria met

F2 documented as closed · F7 independently verified and its correction tested ·
fixture data measured and production inaccessibility explicitly recorded · the 935/2
figures **not** presented as current production facts · `REQF_*` consumers mapped (and
the choke-point claim refined) · legacy-stage consumers mapped · pipeline consumers and
their three legacy fallbacks mapped · SQLite baseline captured · **MariaDB baseline
genuinely captured** · protected-module baselines captured · **no Gate 1 implementation
occurred** · the working tree holds only the intended documentation, the four-line
correction, the regenerated checksum manifest and one new test.

### Gate 1 prerequisites produced by Gate 0

1. **G0-1** — C16's mapping must handle `OFFER`, `' RECEIVED'` and any other
   non-member legacy value **explicitly and visibly**.
2. **G0-2** — the legacy/pipeline conflict class exists in real data (1 row) and must be
   **surfaced, never guessed**.
3. **Twelve** library files read `candidates.stage` outside the `REQF_*` choke point and
   must be enumerated as their own migration surface.
4. **Re-measure against production** before migrating; this container cannot see it.
5. The `REQF_*` change must be accompanied by **figure-for-figure re-baselining**, since
   five subsystems move together.
6. `recruitpipe_cand_goto()`'s coarse legacy sync and the three pipeline→legacy fallbacks
   are the **first things D1/C14 must retire**, and each has a named line.

## 21. Recommended next prompt

**Updated by Task 7C.** The reconciliation prompt this section originally recommended
has been carried out — see §20a. F3 is withdrawn, and F1 and F2 are decided with their
work placed in Gates 5 and 4.

**The next controlled step is now Gate 0 + Gate 1 only** — confirm and fix the
`candidate-stage` route collision (F7), re-measure the live legacy-versus-pipeline
candidate state, add the `closed` stage kind with its configurable outcomes, and move the
three `REQF_*` constants to kind-derived sets. Same batch-verify-report-stop discipline:
Gate 1 moves five subsystems through one choke point and deserves its own review.

**One question to put to the owner alongside it, not blocking it:** does the documented
**master exception** to segregation of duties survive being carried into Offer, Salary,
Requisition and Candidate Hiring approval? Gate 4 needs the answer; Gates 0 and 1 do
not.

---

## Final classification

> ## READY WITH SPECIFIC PRE-IMPLEMENTATION CONDITIONS

**Conditions, precisely:**

1. ~~**Answer F1**~~ — **done (§20a).** RB-1's creation at Accepted stands; the
   operational boundary moves to `inspectors.status`. **Work deferred to Gate 5.**
2. **Answer F2's remaining question** — whether the documented **master exception** to
   segregation of duties survives being widened to all approval entities. The rest of F2
   is decided (§20a); **work deferred to Gate 4**. *This is the only outstanding owner
   question.*
3. ~~**Answer F3**~~ — **withdrawn (§20a).** No conflict existed; defining the shipped
   Administrator profile is ordinary Gate 6 work under OPEN-4.
4. **Confirm and fix F7** — the duplicate `candidate-stage` dispatcher case, which makes
   pipeline per-stage capture unreachable.
5. **Re-measure the live candidate data state** before any migration; the repository
   contains no database and the 935/2 figure cannot be re-verified from it.
6. **Sequence C47 before C43** — the closed-outcome vocabulary must be settled before
   the closed kind is built, or reporting semantics get built twice.
7. **Treat `REQF_FILLED/ACTIVE/LOST_STAGES` as the single classification choke point**
   and re-baseline every recruitment figure across that change.
8. **Write the offer-audit tests before the offer-audit code** — `act_log` count in
   `recruit_offer.php` is 0, so there is no existing behaviour to regress against.

**No business decision from the Closure Pack or the Specification is reopened by this
audit, and no open business question is answered in it.**

---

# STOP

**Audit only. Nothing was implemented, committed or pushed.**
