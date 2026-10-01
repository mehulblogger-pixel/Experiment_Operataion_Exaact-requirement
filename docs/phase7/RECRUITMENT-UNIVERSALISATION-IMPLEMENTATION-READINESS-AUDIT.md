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

## §20c — GATE 1A RECORD: RECONCILING THE RECRUITMENT PIPELINE CONSUMERS

Gate 1A was authorised to re-inventory every consumer of the legacy
`candidates.stage` column, classify each one, give every current-state consumer a
named replacement source, and surface the two data findings from Gate 0 — all
**without moving authority**, which is Gate 1B's job.

### C1 — Two Gate 0 figures were wrong. Both are corrected here.

| Gate 0 said | Gate 1A measured | Why Gate 0 was wrong |
|---|---|---|
| 12 independent readers | **9** | `lib/crmdash.php`, `lib/opportunities.php` and `lib/tosrm.php` mention `candidates` **zero** times. Their `stage` references are *opportunity* stages, `opportunities.stage_id`, `jobs.stage` and `sla_targets.stage`. They were counted by a grep for `stage`, not for candidate stage. |
| 3 legacy writers | **6 production write sites** | Two live writers were missed entirely, and the column default was not counted. |

The corrected write inventory (production paths only; seed/demo and test files
excluded):

| # | Site | What it writes | Guarded? |
|---|---|---|---|
| 1 | `lib/ops.php:202` | `stage VARCHAR(20) DEFAULT 'RECEIVED'` — the column default | n/a |
| 2 | `lib/ops.php:6680` | the candidate INSERT | n/a |
| 3 | `lib/ops.php:6380, 6383` | the joining transaction | yes — `rexec_block_reason()` |
| 4 | `lib/ops.php:6458–6459` | an ordinary stage move | yes |
| 5 | `lib/recruitpipe.php:456, 458` | the coarse pipeline→legacy sync | yes — skips legacy terminals |
| 6 | **`lib/recruit_offer.php:351`** — *missed by Gate 0* | `stage='OFFERED'` when an offer is issued, ledger-logged with `track='LEGACY'` | yes — skips all four legacy terminals |
| 7 | **`lib/recruit_exec.php:305, 308`** — *missed by Gate 0* | restores the prior stage when a joining is reverted for want of a seat, preserving `decided_at` only for genuine terminals | yes — falls back to `'OFFERED'` |
| 8 | `lib/careers.php:144` | the public intake INSERT | n/a |

Sites 6 and 7 matter disproportionately: both are *correct, deliberate* code
with sound reasons (6 makes "shortlist → offer" durations measurable; 7 is a
compensating revert). Neither is a bug. But both write a column that D1 says is
no longer the authority, so **both must be re-pointed in Gate 1B**, and a
migration plan that did not know they existed would have left the legacy column
being written by two paths nobody had accounted for.

### C2 — The eight-way classification, and a replacement source for every current-state consumer

| Consumer | Class | Replacement source |
|---|---|---|
| `lib/reqfulfil.php:108–125` | **A CURRENT STATE** + D KPI | `rpipe_current_state()` → stage `kind`, via the `REQF_*` sets recomputed on kinds (Gate 1B) |
| `lib/nextaction.php:165–186` | **A CURRENT STATE** | `rpipe_current_state()` → `closed` / `kind`; `joined_at` unchanged |
| `lib/recruit_assign.php:642` | **A CURRENT STATE** + D KPI | `rpipe_current_state()` → `closed` (replaces `NOT IN (terminals)`) |
| `lib/recruit_fulfil.php:265, 320, 641, 789` | **A CURRENT STATE** | stage `kind` = `terminal`/`closed` |
| `lib/ops.php` register + counts + handler | **A CURRENT STATE** + **H WRITE** | pipeline position; the handler stays the execution choke point |
| `lib/recruit.php:754–755, 989–1019, 1003` | **D KPI/REPORTING** | `kind`-based sets — **see C5, these do not agree with `REQF_*` today** |
| `lib/recruit_cc.php:187, 233, 319–366` | **D KPI/REPORTING** | `kind`-based sets |
| `lib/recruit_kpi.php:288–290, 339–345` | **B HISTORICAL** + D KPI | already reads the `candidate_events` ledger for history; sets move to kinds |
| `lib/candpool.php:166, 187, 209` | **C DISPLAY ONLY** | stage `name` for the label; no logic depends on it |
| `lib/search.php:430, 434` | **C DISPLAY ONLY** | stage `name` |
| `lib/recruit_export.php:68` | **C DISPLAY ONLY** | stage `name` |
| `lib/recruitpipe.php:451–459` | **E COMPATIBILITY** + **H WRITE/SYNC** | retired in Gate 1B — the sync exists only because legacy is authoritative |
| `lib/recruit_offer.php:350–354` | **H WRITE/SYNC** | write the pipeline position; keep the ledger entry |
| `lib/recruit_exec.php:290–315` | **H WRITE/SYNC** | revert the pipeline position |
| `lib/careers.php:144`, `lib/ops.php:202/6680` | **F MIGRATION** | the pipeline's first stage |
| `tests/*`, `lib/seed_demo_*.php` | **G TEST ONLY** | unchanged |

Nine independent reader files, three of them display-only. **Every
current-state consumer now has a named target**, which was the gate's
precondition for any later implementation.

### C3 — G0-1: the ten malformed values have a deterministic origin, and it is benign

Gate 0 measured ten rows whose legacy stage is not a defined stage: `OFFER`
(9 rows — the defined key is `OFFERED`) and `' RECEIVED'` (1 row, leading space).
Gate 1A was required to find deterministic evidence or else preserve and mark
them. **Deterministic evidence exists.** Each value has exactly one origin in the
entire codebase, and both are test fixtures:

- **`OFFER` ← `tests/test_rb3_step3_atomic.php:100`**, the `$mkCand` helper's
  default `$stage = 'OFFER'`. The test only ever compares the value with itself
  (`t_eq($stageOf($x), 'OFFER', …)`), so the typo never failed anything.
- **`' RECEIVED'` ← `tests/test_recruit_iv.php:8`**, a single INSERT with a
  literal leading space.

**No production code path can write either value.** This reframes the finding:
the ten rows are test-fixture residue in the local regression databases, which is
also why the identical ten appeared in `scope_regress` and `ops_reg` — all three
are databases the suite itself populates.

Two honest limits on that conclusion:

1. **Production was never reachable** in Gate 0 or Gate 1A (no database in the
   repository). This says the *local* occurrences are explained; it does **not**
   certify that production is clean. The diagnostic built in this gate is the
   instrument that will answer that against real tenant data.
2. The two fixtures are **left exactly as they are.** Correcting `'OFFER'` to
   `'OFFERED'` is a one-word change, but the standing constraints bar modifying
   tests, and these rows are currently the only live specimens of the malformed
   class — the new diagnostic test asserts against genuine residue rather than a
   value invented to be broken. Recommended as a separate, explicitly authorised
   cleanup.

No row was mapped, corrected, trimmed or moved.

### C4 — G0-2 and the new diagnostic

Gate 0 found one row closed on the legacy field while sitting on a live pipeline
stage. Gate 1A adds the mechanism that makes such rows visible for deliberate
reconciliation, in `lib/recruitpipe.php`:

| Function | What it does |
|---|---|
| `rpipe_legacy_stage_valid($v)` | Is this an exactly-defined stage? Lookup-resolved, so a renamed stage is judged against the workspace's own vocabulary. Exact, not case-folded or trimmed. |
| `rpipe_legacy_stage_class($v)` | Which `REQF_*` set the value falls in, or `''` — `''` is what makes a malformed row invisible to funnel arithmetic. |
| `rpipe_current_state($cand)` | **The single replacement source.** Answers `PIPELINE` / `LEGACY_ONLY` / `NONE`, never substituting legacy for a pipeline answer, and returns `closed = null` when the authority cannot say. |
| `rpipe_recon_class($cand)` | Classifies one candidate into `B_PIPELINE_OK` / `C_LEGACY_MAPPABLE` / `D_LEGACY_NO_EVIDENCE` / `E_CONFLICT` / `F_INVALID_LEGACY`. |
| `rpipe_recon_scan($limit)` | Counts every candidate and names the ones needing a person. |

Three design decisions worth recording:

- **`closed` is asked of the stage `kind`, never of a stage name** (C47). Until
  the `closed` kind exists in Gate 1B, no pipeline stage can report closed, and
  the helper correctly returns `false` rather than borrowing the legacy answer.
- **A pipeline answer requires the stage id to match.** `recruitpipe_cand_state()`
  falls back to `recruitpipe_for($req)` when the locked pipeline is inactive; the
  candidate's stage id then belongs to a different pipeline, is not found, and
  `$idx` stays `0` — which would read as *"on stage one of a pipeline they were
  never on"*. `rpipe_current_state()` reports `LEGACY_ONLY` in that case.
- **Nothing in this gate writes.** Proved, not asserted — see C6.

### C5 — G1A-1 and G1A-2: two findings Gate 1A discovered on its own

**G1A-1 — the readers do not agree on what the value *is*.**
`lib/nextaction.php:165` reads the column as `strtoupper(trim($v))`; `reqfulfil`
and `recruitpipe_legacy_terminal()` compare strictly. So `' RECEIVED'` is a live
Received candidate to one reader and an unclassifiable row to the other. This is
the same "two half-mechanisms doing one job" pattern as the stage column itself,
one level down. `rpipe_current_state()` carries **both** readings and raises
`legacy_reader_split` rather than silently picking one.

**G1A-2 — "filled" is computed two different ways in production today.**
This one is business-visible and independent of the pipeline question:

| Site | Counts "filled" as | Agrees with `REQF_FILLED_STAGES`? |
|---|---|---|
| `lib/reqfulfil.php:35` | `['ACCEPTED']` — the canonical definition | — defines it |
| `lib/recruit_cc.php:230` | derives from the constant | yes |
| `lib/recruit_kpi.php:288` | derives via `rkpi_filled_stages()` | yes |
| `lib/recruit_fulfil.php:259` | derives, with an `['ACCEPTED']` fallback | yes |
| **`lib/recruit.php:754`** | hardcoded `IN ('OFFERED','ACCEPTED')` | **no** |
| **`lib/recruit.php:1003`** | hardcoded `IN ('OFFERED','ACCEPTED')`, aliased `filled` | **no** |

In business terms: `recruit_req_health()` computes
`$vacancies = max(0, $qty - $filled)` and, when that reaches zero, tells the
manager **"All positions filled"** (`recruit.php:770`). Because it counts an
*issued* offer as filled, **a 5-seat requisition with 5 offers issued and none
accepted reports "All positions filled"**, while the canonical fulfilment engine
correctly reports 0 filled and 5 still in progress. If any of those five
candidates declines, the health panel has already told the manager the job was
done.

`recruit_cc.php:319/329` also hardcode `('OFFERED','ACCEPTED')`, but there the
series is deliberately labelled *offered* against a separate `$ccFill` *joined*
series — a two-line trend, not a divergent definition of filled. Not a defect.

**G1A-2 was NOT fixed in this gate.** It changes a number a manager reads on
screen, and Gate 1B already owns the `REQF_*` before/after figures, which is
where the two definitions must converge. Deferred deliberately, not overlooked.

### C6 — What changed, and the evidence

Changed files: `lib/recruitpipe.php` (additive helpers only — no existing
function altered), `tests/test_gate1a_stage_reconcile.php` (new),
`phpapp/deploy-check.php` (regenerated, 669 files).

No schema change. No migration. No route change. No permission change. No
lifecycle status or transition added. No protected module touched. No production
deployment.

| Check | Result |
|---|---|
| Gate 1A tests, SQLite | **82 / 0** |
| Gate 1A tests, MariaDB 10.11 | **82 / 0** |
| Full suite, SQLite | **15,240 / 0** (Gate 0 baseline 15,158 + 82) |
| Full suite, MariaDB 10.11 (authoritative) | **15,241 / 0** |
| Mutation targets killed | **7 / 7** |

**The +1 on MariaDB is explained, not waved away.** The two engines run
deliberately engine-conditional tests: SQLite-only lock-timeout tests
(`BUSY1`–`BUSY2`) against MariaDB-only row-locking concurrency tests, plus three
assertions whose *message text* embeds the engine name or an auto-increment id
(`A4`, `A6`, `C4`). A full assertion-level diff of both runs confirms
**zero** Gate 1A assertions among the differences, and the battery reports
**82 / 0 on each engine** — so nothing in this gate behaves differently on
MariaDB than on SQLite.

Two mutations initially **survived**, and both exposed a genuine weakness in the
test battery rather than in the code:

- A scan that "repaired" malformed values passed all 67 original assertions,
  because `strtoupper(trim('OFFER'))` is `'OFFER'` — a fixed point — and the
  read-only fingerprint had captured its "before" value *after* earlier scans
  had already done the damage.
- A scan that wrote `pipeline_stage_id` escaped for the same reason: only the
  stage-length component was compared against a pristine baseline.

Both were fixed at the root: the fingerprint is now taken **before any
diagnostic runs**, covers **every column a diagnostic could write**, and each of
the test's own rows is re-checked individually so compensating writes cannot net
out. The battery grew from 67 to 82 assertions, and all seven mutations now die.

A third issue surfaced in the full suite: the test's inactive-pipeline fixture
leaked a row into `recruit_pipelines`, failing an unrelated global count in
`tests/test_recruit_pipeline.php:12`. The fixture now removes itself the moment
it has served its purpose — and the candidate keeps its now-dangling
`pipeline_id`, which is a *better* specimen of an unresolvable position than the
original fixture was.

### C7 — Baseline

Gate 0's measured baseline could not be re-read: the container was rebuilt and
its `gate0_base` database no longer exists (no database ships in the repository).
The baseline was regenerated by the suite itself, as in Gate 0. **Production
remains unreachable, so no production figures are claimed here.**

### C8 — What Gate 1A deliberately did NOT do

- Did not move authority to the pipeline — that is Gate 1B.
- Did not add the `closed` stage kind — Gate 1B.
- Did not retire the coarse legacy sync — Gate 1B.
- Did not migrate, map, trim or correct a single candidate row.
- Did not repoint any of the nine readers or six writers. They still read and
  write the legacy column exactly as before; the replacement source now exists
  beside them, with a target named for each.
- Did not fix G1A-2, or the two test fixtures behind G0-1.

---

## §20d — GATE 1B RECORD: THE CONFIGURABLE PIPELINE IS NOW THE AUTHORITY

Gate 1B is the controlled implementation of D1/C14. The chain

> pipeline → stage → **stage kind** → current state → REQF classification →
> fulfilment / health / KPI / dashboards

is now the single authoritative answer to "where is this candidate?", and
`candidates.stage` is compatibility, history and migration data with no authority
of its own.

### D1 — The closed stage kind (C47)

| What | Where |
|---|---|
| `closed` added to the kind vocabulary | `RPIPE_STAGE_KINDS`, `lib/recruitpipe.php` |
| `terminal` kept and kept distinct | `rpipe_kind_is_terminal()` / `rpipe_kind_is_closed()` |
| Ten configurable closed OUTCOMES | `RPIPE_CLOSED_OUTCOMES` + lookup `candidate_closed_outcome` |
| Which outcome a closed stage represents | new column `recruit_stages.closed_outcome` |
| Every pipeline gets off-ramps | `recruitpipe_ensure_closed_stages()` — idempotent, additive, seq 9000+ |

`terminal` is a **successful** finish and classifies as FILLED; `closed` means
**not proceeding** and classifies as LOST. They are never interchangeable, and
closed is asked of the KIND — never of a stage's name, and never of the legacy
column. The ten outcomes are subtypes beneath **one** kind: "rejected" and
"accepted" did not become kinds, and a test asserts they cannot be configured as
kinds.

Closed stages are deliberately **not part of the progression**.
`recruitpipe_effective_stages()` excludes them, so nobody can be *advanced* into
Rejected and no progress bar ends in three dead ends; a new
`recruitpipe_resolvable_stages()` (progression + off-ramps) is what RESOLVES a
position, because a closed candidate genuinely is on a stage.

### D2 — One classification choke point, two renderings

| Function | Purpose |
|---|---|
| `RPIPE_KIND_CLASS` | **the one mapping**: kind → FILLED / ACTIVE / LOST |
| `RPIPE_LEGACY_KIND` | the one-way compatibility map: legacy value → kind |
| `reqf_classify($cand)` | one candidate, in PHP |
| `reqf_class_expr()` / `reqf_class_join()` | a set, in SQL |
| `reqf_kind_expr()` | the effective KIND, for stage-specific questions |

Two renderings exist because a per-row PHP call across a KPI query spanning every
requirement in the workspace is an N+1 nobody would accept. They are **not two
definitions**: both are generated from `RPIPE_KIND_CLASS`, and tests assert they
agree for every stage kind and every legacy value, including the malformed ones —
so drift between them fails the suite.

The `REQF_*` constants survive with a changed job, stated in the code: they are
now the **one-way legacy compatibility map** for candidates who have never been
moved on a pipeline and therefore have no kind to ask. That map cannot override a
pipeline answer, produces values in the same vocabulary, and migration drains it.

### D3 — G1A-2 corrected: the business defect is gone

Measured on the same fixture before and after, with legacy-only candidates (which
is what an existing un-migrated workspace looks like):

| Case | Figure | Before Gate 1B | After Gate 1B |
|---|---|---|---|
| 5 seats, 5 offers issued, **0 accepted** | fulfilment filled | 0 | 0 |
| | fulfilment in progress | 5 | 5 |
| | **requirement health filled** | **5** | **0** |
| | **requirement health vacancies** | **0** | **5** |
| | **"All positions filled"** | **YES** | **no** |
| 5 seats, the same five accept | fulfilment filled | 5 | 5 |
| | requirement health filled | 5 | 5 |
| | "All positions filled" | YES | YES |

The row in bold is the defect: a requirement for five people with five offers out
and nobody accepted told the manager the job was done, while the fulfilment engine
correctly said five were still to hire. `recruit.php:754` and `:1003` no longer
compute "filled" from a hardcoded `IN ('OFFERED','ACCEPTED')`; they ask the choke
point, where `offer` classifies as ACTIVE. Case B is unchanged, which is the point
— the fix corrects the wrong answer without moving the right one.

`recruit_cc.php`'s offered-vs-joined trend remains two deliberately distinct
series (`$CK IN ('offer','terminal')` against `$CE='FILLED'`), as required.

### D4 — Current-state consumers moved

| File | Sites moved | Kept as-is |
|---|---|---|
| `lib/reqfulfil.php` | all four counts (filled / joined / in progress / lost) | — |
| `lib/recruit.php` | health, dashboard pipeline / offers / joinings / dormant / overdue interviews, commercial rollup | — |
| `lib/recruit_cc.php` | funnel + donut grouping, requirement filled, trends, department load, drop reasons, waiting, ageing, time-to-hire | the offered-vs-joined series' distinct meaning |
| `lib/recruit_kpi.php` | the demand spine's fl/inprog/lostn/dir, ageing, analytics registry | ledger history, which is already event-based |
| `lib/recruit_fulfil.php` | all four "fulfilled" counts; `rful_filled_stages()` retired | — |
| `lib/recruit_assign.php` | the assignability guard, workload filled/active/offers/joins/overdue, unassigned | — |
| `lib/nextaction.php` | the whole candidate resolver | RB-2's `joined_at` rule, untouched |
| `lib/recruit_exec.php` | seat counts, the "except" release, JOIN-vs-ADVANCE, both compensators | the seat-ceiling reasoning, untouched |
| `lib/recruitpipe.php` | the flow-route guard, the workflow panel, the stage tab | — |
| `lib/ops.php` | the stage route's two writes; the revert now told the prior position | the choke point itself, the transaction shape, permissions |

**Display-only uses of a stage NAME were deliberately left alone** (`candpool.php`,
`search.php`, `recruit_export.php`), as were the event-ledger reads that are
already history rather than current state.

One semantic change is worth flagging: `recruit.php`'s "dormant candidates"
opportunity card used to read `IN ('HOLD','REJECTED','WITHDRAWN')`, which was
inconsistent twice over — it omitted OFFER_DECLINED, who are just as
re-approachable, and included HOLD, who have not been closed at all. It now asks
for closed candidates. Recorded because it changes what that card lists.

### D5 — Legacy writers retired or re-pointed

| # | Writer | Now |
|---|---|---|
| 1 | column default `DEFAULT 'RECEIVED'` | unchanged — it is the intake record |
| 2 | `ops.php` candidate INSERT | unchanged — intake, before any process |
| 3 | `ops.php` joining transaction | **writes the pipeline position**, inside the same transaction |
| 4 | `ops.php` ordinary move | **writes the pipeline position** |
| 5 | `recruitpipe.php` coarse sync | **deleted**; nothing replaces it |
| 6 | `recruit_offer.php` offer-issued | **moves the pipeline to the offer stage**, ledger entry kept |
| 7 | `recruit_exec.php` joining revert | **restores the prior pipeline position** |
| 8 | `careers.php` public intake | unchanged — intake |

There is deliberately **no reverse synchroniser**, and therefore no
pipeline → legacy → pipeline authority loop.

The stage route keeps the legacy vocabulary in its dropdown and translates the
requested target into the candidate's own pipeline stage
(`rpipe_stage_for_legacy_target()`): accept → the terminal stage, offer → the
offer stage, each closure → the off-ramp carrying that outcome. It returns null
for the early legacy values (a pipeline may configure six `step` stages, so
"shortlisted" names none of them) and for a candidate with no process at all, and
the legacy column is then written as before — for those rows it is the only record
there is. **It is never written as well as the pipeline.**

A closure through the real route keeps its drop point, drop reason, decision stamp
and ledger remark: asserted, not assumed, by driving the actual route in a
subprocess.

### D6 — Migration: deterministic, idempotent, additive, explicit

| Function | Purpose |
|---|---|
| `rpipe_migration_plan($cand)` | what WOULD happen and why — MIGRATE / SKIP / REVIEW. Never writes |
| `rpipe_migration_apply($plan)` | one move, guarded by `COALESCE(pipeline_stage_id,0)=0` |
| `rpipe_reconcile_run($limit, $apply)` | a bounded, resumable pass; `$apply=false` is a dry run |

A candidate is migrated **only where the evidence is certain**: their legacy value
translates to a kind, and their pipeline configures **exactly one** stage of that
kind. Everything else is surfaced for a person.

Determinism is a property of the configuration, not of the value, and both
branches are proved: on a requirement with no grade, CORP18's L2 interview is
conditional and does not apply, so `INTERVIEW` maps to the single L1 and IS
certain; on a SENIOR requirement two interview stages apply and **the same value
is refused**. `SHORTLISTED` never maps.

Resumable by construction rather than by bookkeeping: every pass re-derives its
plans and only moves candidates who still have no position, so a second pass
migrates nobody and a bounded pass reports that more remain. The legacy value is
**never cleared** — it is the historical record and the only evidence a later
reconciliation would have. Each move is written to the existing ledger on a new
`MIGRATION` track with kind `MIGRATE`, and `rkpi_stage_durations()` skips it, so
no stage duration is ever measured across a reconciliation. Nothing runs because a
page was opened.

### D7 — G0-1, G0-2, G1A-1 under pipeline authority

**G0-1 (malformed legacy values)** — unchanged and still never repaired. `OFFER`
and `' RECEIVED'` translate to **no kind**, so they classify as `''` — genuinely
unknown, not quietly ACTIVE and not quietly LOST. A scan and a dry-run migration
leave them byte-identical, and migration refuses to place them, saying why. The
two test fixtures behind them were **not modified**, as instructed, and no claim
is made about production, which remains unreachable.

**G0-2 (pipeline live, legacy closed)** — the pipeline decides the classification,
because it is the authority; the disagreement is still detected, still classified
`E_CONFLICT`, and migration explicitly **skips** such a candidate naming the
conflict. The legacy evidence is preserved, never deleted, and nobody is
automatically rejected, reopened or moved because the old value disagrees.

**G1A-1 (readers disagreed about normalisation)** — fixed at the root rather than
by picking a winner. `nextaction.php`'s `strtoupper(trim())` and
`recruit_assign.php`'s identical normalisation are **gone**: both now ask the
classification, so there is no normalisation rule of their own left to disagree
with anybody. The strict/lenient split is still reported on the state for the
diagnostic's benefit.

### D8 — Candidate pool safety (D3)

`recruitpipe_cand_state()` resolves the DEFAULT pipeline for a candidate with no
requirement — right for showing what a process would look like, wrong for writing
a position. Without a guard, editing a company-wide pool candidate would have
enrolled them in active recruitment merely because a default pipeline exists.
Guards added in `rpipe_stage_for_legacy_target()` and
`offer_move_to_offer_stage()`; migration SKIPs such candidates naming D3; and an
applied migration pass provably leaves them in the pool.

### D9 — Verification

| Check | Result |
|---|---|
| Gate 1B battery, SQLite | **191 / 0** |
| Gate 1B battery, MariaDB 10.11 | **191 / 0** |
| Full suite, SQLite | **15,451–15,452 / 0** |
| Full suite, MariaDB 10.11 (authoritative) | **15,451–15,453 / 0** |
| Mutation targets killed | **12 / 12** |
| Browser (Chromium), desktop + phone | **22 / 0** |
| Whole-app crawl, every role | **208 screens, all render cleanly** |

**The suite total is not a deterministic invariant, on either engine**, and it is
worth stating exactly why rather than quoting one number as if it were.

Repeated runs gave 15,451 and 15,452 on SQLite and 15,451 through 15,453 on
MariaDB, **always with zero failures**. Diffing two runs' assertion lists shows
the assertion SET is symmetric — 11 lines differ each way, and every one of them
is the SAME assertion carrying a run-dependent value in its message text:

- `tests/test_php_close_tag_in_comment.php` writes its own scratch PHP files under
  the system temp directory and reports how many it scanned (1230 vs 1231), so the
  figure moves with what else is in `/tmp`;
- the concurrency tests legitimately resolve races differently from run to run
  (`CONVERTED` vs `BUSY`, one winner vs a dead heat, `owner=216` vs `owner=0`) —
  each outcome is asserted as acceptable, which is the point of those tests;
- MariaDB additionally runs real row-locking races where SQLite runs lock-timeout
  tests, which is the pre-existing engine difference Gate 1A already recorded.

None of this involves Gate 1B: the focused battery is exactly **191 on both
engines**, so nothing in this gate behaves differently on MariaDB than on SQLite.

Three mutations initially survived and each exposed a real gap:

- the offer-issued legacy write and the joining-revert legacy write both survived,
  because the battery had not driven those production paths end to end. It now
  does — `offer_create → submit → approve → issue`, and a two-candidates-one-seat
  race through the real compensator.
- removing the closed kind from the vocabulary killed only one assertion, so the
  battery now also asserts that a stage can be **configured** as closed through
  the ordinary save path and that an invented kind such as "rejected" is refused.

A real defect was found this way too: the revert, given `null` for "they had no
prior pipeline position", left the candidate on the terminal stage they had just
been refused. It now restores **both** columns to exactly what they were, with `0`
meaning "no position at all".

### D10 — Findings, classified

| Finding | Class | Note |
|---|---|---|
| The Command Centre funnel is coarser for migrated candidates | **deferred — reporting gate** | The kind vocabulary has one `step` kind, so CV Screening and HOD Shortlisting both bucket as RECEIVED. The funnel stays truthful (it is cumulative) but a per-pipeline-stage funnel is a screen redesign and out of scope here. Recorded in `RCC_KIND_BUCKET`. |
| `tools/p7-browser-uat.js` cannot create a requirement | **deferred — pre-existing** | It gets 403 on `/requisition-new` because ADR-001 closed the direct path. The script predates that decision; it is stale relative to ADR-001, not to this gate. `tools/g1b-browser-check.js` covers what Gate 1B changed. |
| The two G0-1 test fixtures still write undefined stage values | **test-only** | Left untouched as instructed. A one-word fix each, needing explicit authorisation. |
| `rexec_filled_stages()` still returns legacy values | **documentation only** | Retained solely so the stage route can ask "does this requested TARGET mean a joining?" before any move exists — a question about a requested value, not about a candidate's state. Commented as such. |
| Workforce `Accepted`-vs-`Joined`, `is_coordinator_level()`, self-approval | **deferred — later gates** | F1 → Gate 5, F2 → Gate 4, F3/OPEN-4 → Gate 6, unchanged. |

### D11 — What Gate 1B deliberately did NOT do

No new lifecycle engine, no second state machine, no second classification engine,
no new current-state column, no duplicated event ledger. No requisition or hiring
request versioning, Review Required, approval changes, offer audit engine,
self-approval configuration, role permission redesign, Hired-vs-Joined workforce
change, inspector status change, mobile or Role Workspace redesign, Person Hub,
organisation convergence, or Marketplace change. No candidate deleted, no legacy
stage data deleted, no historical event or KPI fact rewritten, no tenant touched
but the one under test. **No production deployment, and no claim about production
data — production remains unreachable from this environment.**

---

## §20e — GATE 2 RECORD: REQUIREMENT VERSIONING & CHANGE CONTROL

Gate 2 implements the locked versioning architecture for **both** the Hiring
Request and the Recruitment Requisition, through **one** mechanism.

### E1 — The defect this gate removes

M4 already detected material change correctly and already asked the existing
approval engine for a new decision. What it did **not** do was keep the approved
requirement effective while the change waited: `hreq_save()` wrote the incoming
values straight into the live row and only *then* noticed the change was material.
The approved snapshot survived in its column, so nothing was lost — but every
screen, count and export then read the **proposed** values as though an approver
had agreed to them. **A pending change was silently effective.**

That is now impossible. A material change to an approved requirement does not
touch the record; it becomes a proposal.

### E2 — Schema (additive, idempotent, nothing destructive)

| Object | Purpose |
|---|---|
| `requirement_versions` | one row per approved version; **append-only**, never updated, never deleted |
| `requirement_change_proposals` | proposals with their full lifecycle and decision history |
| `requirement_change_proposals.pending_key` | `"ENTITY:id"` while PENDING, `NULL` otherwise, under a unique index |
| `hiring_requests.min_experience_years / min_qualification / essential_skills` | D2's CORE person specification, nullable |
| `requisitions.` same three | the requisition's own copy, which it may make stricter |
| `requisitions.preferred_skills / screening_questions / sourcing_requirements / client_requirements / recruitment_notes / evaluation_criteria` | D2's execution detail |
| `requisitions.approved_snapshot_json / approved_snapshot_at / change_state` | mirrors what the hiring request already had |
| `job_offers.req_version_at_issue` | A2's boundary, stamped at issue |

**The pending-uniqueness trick is worth recording.** A partial unique index would
be the natural way to say "one pending proposal per requirement", and MariaDB does
not have them. So `pending_key` carries the identity only while the row is PENDING
and `NULL` otherwise: a unique index treats NULLs as distinct on **both** engines,
so any number of decided proposals coexist while a second PENDING one cannot be
inserted. The database enforces the rule, not a check the next caller might forget
— and a test proves it by attempting the insert directly.

### E3 — One mechanism, two entities

`lib/reqversion.php` holds the whole engine. The only per-entity knowledge is in
`rver_entities()`; everything else is shared. **No second approval engine, no
second audit engine, no second lifecycle engine, and no "master requirement" table
replacing the two it versions.** Each entity keeps its own material-field list
(A6) and its own version chain (§20): a Requisition version never mutates the
Hiring Request's, which a test asserts directly.

### E4 — Materiality, and what "configurable" means here

`rver_material_fields($entity)` returns the shipped list **plus** whatever the
organisation adds. Configuration may only **ADD**: the shipped list is a floor, not
a default an administrator can empty. Removing a protection is exactly the change
an organisation should not be able to make by accident, and the audit matrix an
approver relies on is what would quietly stop being true. A configured field the
table does not have is ignored, so a typo cannot make every save look material.

### E5 — Budget: total commitment, not a single field

    per-person cost x quantity x applicable periods + one-time cost

Judged as **one number**, because the fields trade off against each other: halving
a duration while doubling a rate is not a change anybody would call a change, and
comparing field by field would fire a needless re-approval on it.

| Threshold | Shipped | Configurable as |
|---|---|---|
| Percentage | 10% | `rver_budget_pct` |
| Absolute | ₹1,00,000 | `rver_budget_abs` |
| Rule | greater of the two | `rver_budget_rule` — `GREATER` / `LESSER` / `PCT` / `ABS` |

A **decrease is never material**. Where nothing was approved before, the absolute
floor decides — a percentage of nothing would make any first estimate material. A
change landing **exactly on** the threshold is inside it, which is the reading an
approver signing a limit expects; a rupee past it is material. Completing a partial
estimate (a rate with no duration, which contributes one period) to a full one is
judged like any other increase.

### E6 — A8: stricter is fine, weaker is change-controlled

Judged against the **approved Hiring Request minimum**, never against the previous
Requisition version. Three floor kinds, each with its direction stated in code:
experience (higher is stricter), qualification (later in the configured order is
stricter), essential skills (a larger set is stricter).

| Case | Outcome |
|---|---|
| Requisition asks 5 years, floor is 3 | stricter — allowed, ordinary edit |
| Diploma + certification against a Diploma floor | stricter — allowed |
| Requisition 5 → 2 years, floor is 3 | **weakening below the floor** — change control |
| Requisition 5 → 4 years, floor is 3 | above the floor — ordinary edit |
| Requisition 2 → 3 years, floor is 3 | **towards** the floor — never a weakening |
| An essential skill the approved request named is dropped | weakening — change control |
| No hiring request at all (the direct path) | no floor exists, so nothing to breach |

A weakening always requires a reason, a new Requisition version and audit history;
whether it *also* requires approval is the organisation's configuration to decide.

### E7 — Approval routing (F1), and B2

A chain configured specifically for material changes **wins**; otherwise the change
inherits the requirement's own chain. Either way it is the **existing** approval
engine that is asked, with the **proposed** values as context — a change is judged
by what it is becoming, not by what it was. Two entities were added to
`APPR_ENTITIES` (`HREQ_CHANGE`, `REQ_CHANGE`) so an organisation can configure such
a chain; configure no rule for them and the change simply inherits.

**B2 is honoured exactly.** Approval Required is ON by default (the hiring request
carries it per record; a requisition inherits it from its request, else from a
workspace setting that also defaults ON). "No matching rule" is **not** "approval
off": the proposal waits, nothing self-approves, no approver is invented, and the
configuration gap is stated on the screen and in the audit trail so an
administrator can fix it. Where approval genuinely is not required, the change
still gets a reason, a new version and a full audit trail.

### E8 — What a pending change stops (A5/Q6)

Four organisation-configured levels, expressed in the **existing** `REXEC_ACTIONS`
vocabulary and answered inside the **existing** execution gate, per action:

| Level | Effect |
|---|---|
| 1 | pause everything |
| **2 — shipped default** | **continue screening and interviewing; no offer, no joining** |
| 3 | everything except joining |
| 4 | carry on |

`lib/recruit_exec.php` asks `rver_block_reason()` per action, so a refusal reads
like every other execution refusal in the product. A change to the hiring request a
requirement was raised from stops execution on that requirement too — the authority
being changed is the one it spends.

### E9 — The lifecycle, and G2

`PROPOSE → PENDING → decision → APPROVED / REJECTED / WITHDRAWN`.

A refused proposal is **kept for ever**, is **never amended in place** (a new
attempt is a new row), does not change the approved version, and its refused values
remain available to prefill a later attempt. A withdrawn proposal records its
withdrawal reason, closes, leaves the approved version untouched and restores
normal operation. Q10's reason is **mandatory** for both proposing and withdrawing;
Q11's supporting documents are optional.

### E10 — Approval applies the change; nothing else does

`rver_apply()` is the only place an approved requirement's values move, and it does
the record write, the new version and the proposal's closure **in one transaction**
— so there is never a version whose values the record does not carry. It closes the
proposal **first**, conditionally on it still being PENDING, so two approvers
deciding in the same instant cannot both win and the loser changes nothing.

### E11 — The architectural boundary was respected, not widened

`lib/hiringreq.php` owns the `hiring_requests` table, and the M4 suite enforces
that by reading every other library for SQL against it. Rather than widen that
guard, the versioning engine **asks** that layer:
`hreq_apply_approved_version()`, `hreq_write_version_fields()`,
`hreq_write_approved_snapshot()`, `hreq_set_reapproval()` — each audited, each
inside the layer. The table still has exactly one owner, and the engine stays
entity-agnostic.

The existing `reapproval_state` marker is **driven** by the proposal rather than
replaced by a second one, so every existing reader — `hreq_is_executable()`, the
registers, the screens, the guards — keeps working unchanged.

### E12 — Candidates, and the issued-offer boundary (Q13 / A2)

Candidates keep their requirement across a version change: none is lost, none is
reassigned, and `rver_applicable_version()` answers, for one candidate, which
version they have to meet. That is the relationship **Gate 3** needs, and it is all
Gate 2 provides — **Review Required is not implemented here.**

A candidate holding an **issued, viewed or accepted** offer, or already in the
workforce, stays on the version in force when that commitment was made. A **draft**
offer remains in scope.

**A defect was found and fixed while proving this.** The boundary was first derived
from the offer's issue timestamp against the version chain, which is ambiguous
whenever an approval and an issue land in the same second — and "which requirement
was this person promised?" must not depend on clock resolution. The version is now
**stamped on the offer at issue** (`job_offers.req_version_at_issue`), with the
timestamp derivation kept only as the fallback for offers issued before this gate.

### E13 — Who may propose (Q8 / C46 / A4)

Permission `hiring.material_change.propose`, registered in the catalogue and
configurable in the Recruitment group, per **role** and never per user.

Q8's model is role defaults **plus** a specific permission **plus** scope — three
things that add up. So a role that may already change this requirement keeps that
ability, the permission **extends** it to roles whose defaults do not include
changing requirements, and the recruitment scope applies on top of both. Requiring
the new permission *instead* would have silently removed, on upgrade, something
every existing recruiter can do today — and an upgrade that removes a capability
nobody asked to remove is not a safe upgrade. A user with neither is refused, and a
manager scoped to another branch is refused on this one.

### E14 — Verification

| Check | Result |
|---|---|
| Gate 2 battery, SQLite | **169 / 0** |
| Gate 2 battery, MariaDB 10.11 | **169 / 0** |
| Full suite, SQLite | **15,608 / 0** |
| Full suite, MariaDB 10.11 (authoritative) | **15,631 / 0** |
| Mutation targets killed | **24 / 24** |
| Browser (Chromium), desktop + phone | **22 / 0** |
| Whole-app crawl, every role | **all screens render cleanly** |

The suite totals differ between engines for the reasons recorded in §20d: a
handful of pre-existing tests embed run-dependent values or count their own scratch
files, and MariaDB runs row-locking races where SQLite runs lock-timeout tests. The
focused Gate 2 battery is **169 on both engines**, so nothing in this gate behaves
differently on MariaDB.

Two problems were found by the work itself and fixed at the root:

- **The rejection path reported failure for a rejection that had succeeded.**
  `rver_reject()` moves the state marker, and the old code then ran its own
  state-guarded write, matched no rows, and returned false — so a race could end
  with *both* processes saying they lost. The two decision branches are now
  symmetric.
- **My own tenant-isolation test found leftovers of its own making**: tenant B's
  database outlives the process, so it is now cleared before it is entered. An
  isolation test that passes on its own residue proves nothing.

### E15 — Findings, classified

| Finding | Class |
|---|---|
| Self-approval configuration (configurable, OFF by default, master exception) | **deferred — Gate 4**, as locked. Gate 2 reuses the existing framework and proves a change is judged by the same segregation the first approval was |
| Review Required, and what a stricter version does to attached candidates | **deferred — Gate 3**, as instructed. Gate 2 provides `rver_applicable_version()` and the audience split, and implements nothing else |
| The requisition edit path is gated inside `lib/ops.php`'s handler | **documentation only** — the gate is one call; the requisition's own save remains where it was |
| `hreq_require_reapproval()` remains for re-approvals opened before Gate 2 | **documentation only** — existing records continue under the configuration they were raised under (§8) |

### E16 — What Gate 2 deliberately did NOT do

No Review Required, no automatic candidate re-evaluation, no candidate
reconsideration, no offer audit engine, no Candidate Hiring approval, no workforce
Accepted-vs-Joined change, no inspector status change, no role catalogue or
permission redesign, no mobile redesign, no Person Hub, no organisation
convergence, no Marketplace change, and no new KPI, approval, audit or lifecycle
engine. **Gate 1B's pipeline authority is untouched.** No candidate deleted, no
historical version deleted, no event or KPI fact rewritten. **No production
deployment, and no claim about production data.**

---

## §20f — GATE 3 RECORD: REVIEW REQUIRED & REQUIREMENT-CHANGE IMPACT

Gate 2 made an approved requirement immutable. Gate 3 answers the question Gate 2
deliberately left open: **what happens to the people already in the process when the
requirement they were sourced against legitimately changes.**

### F1 — The gap this gate closes

A manager raised a requirement, it was approved, recruiters went out and found five
people against a three-year minimum. The requirement was then properly changed
through Gate 2's change control, and the approved minimum became eight years.

Those five people stayed exactly where they were, and nothing told anybody. They
continued to be shortlisted, interviewed and offered against a bar **nobody had ever
checked them against** — and the business would have found out at the worst possible
moment, with an offer already in somebody's hands.

Gate 3 makes that impossible: when a stricter approved version becomes effective,
**every** active candidate in that process is put in front of a human who must say,
per person, Continue or Reject, with a reason.

### F2 — Schema (additive, idempotent, non-destructive)

| Object | Purpose |
|---|---|
| `candidate_reviews` | one row per review episode: what changed, which two versions, who raised it, who decided it, when, and why |
| `candidate_reviews.open_key` | `"candidate:ENTITY:id"` while OPEN, `NULL` once decided, under a unique index |

Nothing else. **No `candidates.review_required` column** — that is the global
candidate state the locked rules forbid, and it would make one person's review on one
vacancy stop their interview on another. No new version store, no second pipeline, no
second approval or audit engine, and no candidate office column.

The one-open-review rule is enforced by the **database**, using the same mechanism
Gate 2 proved portable for its one-pending proposal: `open_key` carries the
relationship's identity only while the review is open, and NULLs are distinct in a
unique index on both engines. Two open reviews for one relationship would be two
questions where there is one decision to make, and a reviewer who answered one would
leave the candidate blocked by the other. A test proves it by attempting the duplicate
insert directly, below every line of application code.

### F3 — Why a review belongs to a relationship, and why that needed no new table

In this product a candidate **row is one application**: one human against one
requirement, with `person_ref` threading a person's several rows together. So a
review that belongs to a row belongs, by construction, to exactly one requirement.
A person on three requirements has three rows, and changing the first cannot reach the
other two.

That is asserted behaviourally (clear R1 → R2 still in review; reject R2 → R1 still
live) **and** structurally (the `candidates` table has no `review_required` column),
because a global flag is invisible in behaviour right up until two applications of one
person disagree.

A review also records the requirement it was raised against. If a candidate is later
reallocated to a different requirement, that review stops speaking for a process it
was never about — it stays OPEN on the record, because nobody answered it, but it does
not block work on an unrelated requirement. Move them back and it blocks again.

### F4 — Direction, as a narrow extension of Gate 2's own comparison

Gate 2 answers "what changed, and is it material". Gate 3 needed one more thing:
**direction**. `rver_strictness()` consumes `rver_diff()`'s own output — so there is
exactly one algorithm that decides whether two field sets differ — and reuses
`RVER_SPEC_FLOOR`'s directions and `rver_qual_rank()`'s configured order, the same
semantics A8 already judges a requisition's floor with.

| Direction | Meaning | Raises a review? |
|---|---|---|
| **stricter** | the bar a candidate must clear went UP | **always — A1, not configurable** |
| **redefined** | what is wanted changed, neither up nor down (the role, grade, department, branch) | yes by default, can be switched off |
| **relaxed** | the bar came down | **never** (§18) |
| **neither** | headcount, budget, the client, the authorisation basis | no |

A budget increase is material — Gate 2 stops execution while it is pending — but it
does not change what a candidate has to *be*, so forcing a human to re-read every CV
because a rate moved would teach people to click through reviews without looking. That
is the failure mode this engine exists to avoid.

**`redefined` is a decision this gate had to take, and it is flagged for
confirmation.** The locked rules name "stricter"; they do not say what should happen
when a vacancy stops being for a Welder and becomes one for an Electrician. Leaving it
out would mean five welders quietly remain attached to an electrician vacancy — the
same class of silent wrongness as unapproved headcount. So it ships **on**, is
configurable off per workspace, and is recorded here as a default to confirm rather
than a rule assumed.

**Which pair of versions is compared** is the immediately preceding one, never version
1. That distinction is invisible on a rising history and decisive on a falling one: a
bar that goes 10 → 4 → 6 is a **rise** against the previous version (review) and a
**fall** against the first (no review). The second reading is wrong, and only a
non-monotonic history says so, which is why one is in the battery.

### F5 — A1: all of them, with no exemption anywhere

The population is: attached to the affected process, **still active** by Gate 1B's
authority (pipeline → stage → kind → class), and not past the A2 boundary. Nothing
else. There is deliberately no suitability input of any kind in the trigger path.

Proved across the full spectrum against a new 10-year bar — 25 years, 11 years, 9
years, 1 year, and nothing recorded at all — all five reviewed, at different pipeline
stages. And the ones it must **not** reach: rejected, withdrawn and offer-declined
candidates are not reviewed, because they already left.

A class that cannot be established is treated as **not active** and reported by the
sweep rather than silently dropped: an unknown position is not evidence of activity,
and asserting a review against a candidate nobody can place would be guessing.

### F6 — A2: the issued-offer boundary

Answered by Gate 2's single answer to "which version applies to this person", which
reads the version **stamped on the offer at issue**. A candidate whose applicable
version is older than the one that just became effective is holding a commitment made
against the requirement as it then stood, and a later version does not reach them.

| Candidate | Reached by a later stricter version? |
|---|---|
| no offer, at any active stage | **yes** |
| **draft** offer | **yes** — a draft is a document, not a promise |
| **issued** offer | no |
| offer **accepted** | no — the boundary is Offer Issued, not Hired |
| already **joined** | no |

### F7 — What a review stops, and what it never permits

Answered inside the **existing** execution gate, per action, in the existing
`REXEC_ACTIONS` vocabulary — there is no parallel action-policy engine. The shipped
answer is that nothing about the candidate advances. An organisation may let screening
and interviewing continue; an **offer** and a **joining** can never be unblocked,
because those are commitments to a human being.

Enforced at the owning service, not on a screen, and driven directly in the tests
through: the stage route, the configured-pipeline route, offer creation, interview
scheduling, the joining, workforce conversion, and reallocation to another
requirement.

**Reallocation needed a real fix.** `rexec_block_reason()`'s third parameter means
"do not count this candidate against their own seat", and the reallocation path
deliberately passes `0` there because the candidate holds no seat on the destination.
Reading "which candidate is this action about" off that parameter meant the one path
that passes 0 skipped the review check entirely — exactly the alternate route the
locked rules say to test. It is now its own parameter, defaulting to the old one, so
no existing caller changed behaviour.

### F8 — Outcomes, reasons and history

**Continue** records that somebody with authority looked at this person against the
new requirement and said yes, and why. Historical interviews, assessments, scorecards
and stage history are untouched and remain valid evidence; continuing moves nobody and
re-scores nothing.

**Reject** closes the candidate immediately, needs **no further approval** (asserted by
counting approval requests before and after), and lands on the organisation's own
configured `closed` off-ramp through Gate 1B's own closed-stage reader — preferring an
off-ramp whose outcome is *Not suitable*, falling back to the ordinary rejection
off-ramp. There is no `REVIEW_REJECTED` state.

A reason is **mandatory** for Continue, Reject and reconsideration. Blank, whitespace
and punctuation standing in for a reason are all refused — an audit trail of empty
strings is not an audit trail. Every event goes into the **existing** candidate ledger
through the one stage-ledger writer, so a review sits in the same history, in the same
order, as everything else that happened to that candidate.

### F9 — §18 and §19: nothing automatic, and back to the right stage

A relaxed requirement raises no review, moves nobody, builds no queue and revisits no
rejection. A rejected candidate stays rejected.

A **deliberate** reconsideration returns them to the stage the event ledger records
them on immediately **before** they were closed — rejected out of an interview, back
to the interview; closed at screening, back to screening — never to the first stage,
because being reconsidered is not being re-applied. The original rejection is appended
to, never rewritten. Where the ledger does not record the origin, the engine says so
and asks for a deliberate move rather than guessing a stage.

### F10 — Evidence, and the line it must never cross

The reviewer is shown how the candidate stands against the requirement as it now is,
so they are not holding two records side by side in their head. It is labelled on
screen as **evidence to weigh, not a decision**, and it is read by nothing: not by the
trigger, not by the audience, not by the block, not by continue or reject. A candidate
who plainly fails every line still needs a human to reject them; one who exceeds every
line still needs a human to clear them. Much of the mutation battery exists to prove
that this cannot acquire a vote.

### F11 — Findings, classified

| Finding | Class | What was done |
|---|---|---|
| **G3-1 — the A8 floor itself was not change-controlled.** Gate 2 put `min_experience_years`, `min_qualification` and `essential_skills` on the *requisition's* material list and made the approved *hiring request's* values the floor a requisition may not weaken — but left them off the request's own list. The floor could be edited on an already-approved request with no proposal, no approval and no new version: the ceiling was guarded and the thing it was measured against was not. It is also why a stricter hiring request could not reach a candidate at all, since no version was ever created to be stricter *than*. | **narrow extension required** | Added to `HREQ_MATERIAL_FIELDS`, the only direction Gate 2 allows a material list to move (add, never remove). No engine was changed |
| **Review Required replaces M4's block after a re-approval.** A re-approval that redefines the role lifts M4's requirement-level block and a candidate-level review takes its place. An existing lifecycle test asserted "re-approval restores execution" | **intended behaviour change** | The test now asserts both halves — the review stands in M4's place, and execution is restored once a person has cleared it. Strengthened, not relaxed |
| **Gate 2's own suite issued an offer straight after a version change.** Correctly blocked now | **intended behaviour change** | The reviews are resolved first, the way a recruiter would, before the offer. Gate 2's boundary assertions are unchanged |
| **`crev_migrate` was not wired into `boot()`** — caught by the repository's own guard, which exists because a table created only by a lucky code path crashes a fresh MariaDB install | **real defect, fixed** | Wired into the boot migrate chain |
| **Gate 2's permission and proposal lifecycle were never recorded in `docs/02-permission-matrix.md` or `docs/03-object-lifecycles.md`** | **documentation gap, fixed** | Both gates' permissions and both lifecycles are now recorded, as the repository's own hard rule requires |
| The matching engine scores marketplace professionals, not recruitment candidates — there is no candidate↔requisition score engine in the product | **documentation only** | None built (out of scope). The suitability spectrum is proved with candidate attributes instead, which is what a reviewer actually sees |

### F12 — Verification

| Check | Result |
|---|---|
| Gate 3 battery, SQLite | **225 / 0** |
| Gate 3 battery, MariaDB 10.11 | **225 / 0** |
| Full suite, SQLite | **15,859 / 0** |
| Full suite, MariaDB 10.11 *(authoritative)* | **15,860 / 0** on the committed code (15,862 / 0 on an earlier run) |
| Mutation targets killed | **24 / 24** |
| Browser (Chromium), desktop + 360 / 390 / 412 px | **65 / 0**, repeatable — seven runs, the last two on the final code |

The MariaDB total differs by two between runs for the reason recorded in §20d and
again in §20e: a handful of pre-existing tests count their own scratch files or run
row-locking races that resolve differently. It is the **failure** count that is
asserted, and it is zero on both engines, every run. The focused Gate 3 battery is
**225 on both**, so nothing in this gate behaves differently on MariaDB.

Three problems in the verification itself were found and fixed at the root:

- **Two mutations survived the first pass**, and both were gaps in the tests rather
  than in the code. One ("the wrong pair of versions is compared") survived because
  every version in the battery was stricter than the last, so comparing against
  version 1 gave the same answer — fixed by adding the non-monotonic history in F4.
  The other ("two concurrent resolutions both succeed") survived because the PHP
  pre-check answers first whenever two processes do not actually overlap, so a
  behavioural test passes whether the real protection is there or not — fixed by a
  two-process race **and** a direct assertion that every resolving write is
  conditional on the review still being open and reports a no-match as a loss.
- **The browser check was non-deterministic**, giving 53/12 and 65/0 on alternate
  runs. `php -S` fails quietly when the port is already held, so the browser was
  reading a previous run's database while the seeder reported the new one — evidence
  that cannot be trusted, which is worse than a failure. The runner now refuses to
  start against a port it did not open, asserts its own seeded scenario before
  driving anything, and tears its server down afterwards.
- **This gate's own test file leaked shared state into later suites.** It raised far
  more than eight live requirements, pushing an existing reconciliation test's own
  requirement off a dashboard that shows the eight biggest; and it granted a
  permission to a *role*, which is a workspace-wide override of that role's whole
  permission set. Both are now put back at the end of the file, with the reviews,
  versions and candidates all left intact as evidence.

### F13 — What Gate 3 deliberately did NOT do

No candidate re-scoring, no automatic clearance, no automatic rejection, no
reconsideration queue, no Candidate Hiring approval, no offer-audit redesign, no
workforce-conversion or inspector-boundary change, no permission-architecture
redesign, no KPI, SLA, Marketplace, agency or careers-page change, no organisation
convergence, no Person Hub, no candidate office column, no new identity master, and
no new approval, audit, pipeline or version engine.

**Gate 1B's pipeline authority and Gate 2's versioning are unchanged**, and Gate 2's
guarantees are re-proved in this gate's own battery: approved-version immutability,
the proposal lifecycle, one pending proposal, refused proposals retained with their
values, the mandatory reason, budget materiality, the A8 floor, proposed-value
routing, the offer version stamp and the pre-Gate-2 fallback behind it.

No candidate deleted, no rejection rewritten, no historical version removed, no event
or KPI fact altered. **No production deployment, and no claim about production data.**

---

## §20g — GATE 4 RECORD: APPROVAL GOVERNANCE & SELF-APPROVAL

Gate 4 was asked to make self-approval configurable, off by default, with an
explicitly enabled master exception. The audit it began with found something larger:
**for five of the six approval entities the rule did not exist at all.**

### G1 — The audit, before any code was changed

| Question | What the code actually said |
|---|---|
| Where is approval configuration stored? | the per-tenant `settings` table (`setting_get`/`setting_set`). One database per tenant, so a setting **is** organisation configuration. `setting_set()` already audits who changed which key, old → new, on the sealed chain |
| Was self-approval configurable? | **No.** There was no setting anywhere |
| What happened when requester = approver? | **hiring request:** blocked. **requisition, offer, salary, and Gate 2's two change entities:** *not checked at all* — `appr_guard()` opened with `if ($entity !== 'HIRING_REQUEST') return '';`. `offer_approve()` had no check either |
| Was there a master exception? | Yes — **hardcoded and unconditional**: `hreq_segregation_blocks()` began `if (is_master()) return false;`. No way to switch it off, nothing recorded when used |
| Any per-user exception? | **None.** The only individual attribute is `users.is_superuser`, the platform's master model |
| How do existing organisations behave? | No setting has ever existed, so every one of them is in the implicit case — and the implicit case is **not one answer** (see G4) |
| How is absence interpreted? | `setting_get($k, $def)` returns the caller's default, so the default *is* the interpretation |
| Which entities use the common engine? | `HIRING_REQUEST`, `REQUISITION`, `OFFER`, `SALARY`, `HREQ_CHANGE`, `REQ_CHANGE`. **Candidate Hiring does not exist as an approval entity** — Gate 3 deferred it |
| How are requester and approver compared? | `hiring_requests.requested_by_id` vs the session, for hiring requests only. The engine also records `recruit_approval_requests.requester_id` from the session for **every** entity, which no guard read |
| How is configuration resolved per organisation? | from that tenant's own `settings` row, cached per request, with no cross-tenant path |

### G2 — What was built, and what was reused instead of built

Two organisation settings, both **OFF** by default — and the default is the locked
rule, because `setting_get`'s default is what absence means:

| Setting | Question |
|---|---|
| `appr_self_approval` | may **anyone** decide a request they raised? |
| `appr_self_master_exception` | may a **superuser**, when the above is off? |

Reused rather than rebuilt:

- **`appr_requester_id()`** — the existing resolver, built for the notification
  predicates, which answers "who raised this" for every entity from the business
  object's own id column, **never from a name**, and returns a reason rather than
  guessing. Gate 4 did not write a requester resolver; it asked the one that was
  already trusted.
- **`setting_set()`'s audit** — §13's configuration audit needed nothing built. The
  trail already records the setting, its old value, its new value, who changed it and
  when, hash-chained. Asserted rather than assumed.
- **`act_log()`** for the exception record, **`is_master()`** for the master model,
  and the existing approval engine untouched.

No new approval engine, no new audit engine, no second permission mechanism, no
per-user flag, no username comparison, no route-level rule.

### G3 — The rule, in one place, for every entity

`appr_guard()` now asks entitlement for every entity (from the `APPR_ENTITY_MODULE`
map that already existed for exactly that purpose), branch scope where the entity has
one, and then **`appr_self_block_reason()`** — the one segregation question — for all
six. The direct `offer_approve()` fallback asks the same function, because a control
present on the chain and absent on the fallback is absent.

Order inside the rule, and why each step is where it is:

1. **who raised this** — from the existing resolver. If it cannot be answered, nothing
   is asserted either way: a row from before the engine recorded identities would
   otherwise make every historical approval in every existing workspace undecidable,
   which is a control that stops the business rather than protecting it.
2. **is the decider the same person** — integers on both sides.
3. **has the organisation allowed it outright** — then proceed.
4. **is this a superuser, with the exception deliberately enabled** — then proceed
   **and record it**.
5. otherwise refuse, and say which rule refused: a superuser is told the exception
   exists and is switched off, so the refusal reads as a setting rather than a fault.

### G4 — The migration, decided on measured evidence (§6)

The brief forbids assuming anything about an existing organisation. The evidence is
that an existing organisation does **not** have one behaviour, which is exactly why a
single guess would have been wrong:

| Entity | What an existing organisation does today |
|---|---|
| hiring request | self-approval blocked, **master allowed unconditionally** |
| every other entity | **no requester/approver comparison existed** |

So the two settings are migrated separately, each on its own evidence:

- **`appr_self_approval` → OFF, for every organisation.** For hiring requests this
  changes nothing — they were already blocked. For the other entities there was no
  setting to preserve; there was no rule. That absence was a defect, recorded by Gate
  2 as "widen `appr_guard()`'s entity scope" and deferred to this gate. Persisting it
  as though a customer had chosen it would turn a bug into a policy. **This is a
  deliberate tightening, reported here rather than slipped in.**
- **`appr_self_master_exception` → ON for an organisation already in use, OFF for a
  new one.** A superuser in an existing workspace *can* approve their own hiring
  request today; switching that off underneath them would remove a capability they
  rely on. Their actual behaviour is preserved, written down explicitly instead of
  left implicit — and they can now switch it off, which they could not do before.

The evidence test is "has this organisation ever used recruitment approvals or raised
a hiring request", measured from its own data rather than from a version marker,
because a marker says when the code arrived and the data says whether anybody was
working under the old rules. The hiring-request half of that question is asked of the
layer that **owns** that table (`hreq_any_exists()`), because querying it from the
approval engine would have put a second reader of `hiring_requests` outside its
owning layer — which the M4 suite checks for, and which Gate 2 established the pattern
for: ask the owner, never widen the guard.

The migration is additive, forward-only, idempotent (a marker short-circuits it),
non-destructive (no approval row is touched, no decision re-made, no history
rewritten) and auditable (both `setting_set()` writes and a `SELF_APPROVAL_MIGRATION`
entry recording which branch was taken).

### G5 — A configuration change never reaches a decision in flight (§10)

The policy in force when a chain opens is **stamped onto the request**
(`recruit_approval_requests.self_policy`), exactly as this engine already freezes each
level's SLA policy onto its step. A request raised under one policy stays judged under
it; a request raised afterwards gets the new one; a pre-Gate-4 row carries no stamp
and is judged under the current configuration, which is the only policy it has.

### G6 — §11 re-proved, in the right units

Approved commitment **₹10,00,000**, proposed **₹13,00,000**, threshold
**₹12,00,000** — and the proposed figure chooses the rule. Asserted on a
**requisition** change, deliberately: for a hiring request this product reads the
approval band as **headcount** and keeps money as a separate key (documented in
`hreq_appr_ctx()`), so a money threshold asserted there would be asserting nothing.
Same rule, different unit; the unit has to match the example or the test is theatre.
And the proposer of the change cannot decide it — the hole this gate closed.

### G7 — Findings, classified

| Finding | Class | What was done |
|---|---|---|
| **G4-1 — segregation existed for one entity out of six.** `appr_guard()` returned no-block for requisition, offer, salary and both of Gate 2's change entities. A proposer could approve their own material change | **real defect, fixed** | the rule moved to the common choke point for every entity, using the identity the engine already recorded |
| **G4-2 — Gate 2's change entities were never registered with the engine.** `HREQ_CHANGE`/`REQ_CHANGE` were in `APPR_ENTITIES` but not in `APPR_ENTITY_SOURCE` or `APPR_ENTITY_MODULE`, so `appr_entity_record()` returned null and `appr_requester_id()` answered `ENTITY_UNRESOLVED`. The measurable effect: **the proposer of a material change was never told it had been approved or rejected**, because `appr_email_requester()` could not resolve who to write to | **real defect, fixed** | registered in both maps; a change's requester resolves from the proposal's `proposed_by_id` (the proposer), not from the requirement's original raiser |
| **G4-3 — the master exception was hardcoded and silent** | **real defect, fixed** | organisation-configurable, and audited every time it is used |
| **G4-4 — a decision button was offered that the service would always refuse.** Now that the exception can be off, a superuser meets this often | **usability, fixed** | the screen already asked the full question; it now also names the exception and links an administrator to the setting, so a refusal reads as configuration rather than breakage |
| **G4-5 — `.btn.small` is ~24px tall on a phone.** Approve and Reject sit next to each other on a workflow a manager does in the field | **real defect, fixed** | raised to a 34px touch target at ≤640px only; desktop density untouched |
| **The claim "deliberately in NO role default"** (written in Gate 3's code comment and Gate 3's permission-matrix section) | **documentation error, corrected** | ADMIN and MASTER_ADMIN hold `array_keys(PERMISSIONS)` by design, so an administrator does hold it. Corrected in both places to "no *operational* role's defaults", and Gate 4 asserts the distinction that actually matters |
| **Candidate Hiring approval** does not exist as an approval entity | **out of scope, reported** | nothing built — Gate 3 deferred it, and inventing it here would be scope creep |

### G8 — Verification

| Check | Result |
|---|---|
| Gate 4 battery, SQLite | **142 / 0** |
| Gate 4 battery, MariaDB 10.11 | **142 / 0** |
| Full suite, SQLite | **16,004 / 0** |
| Full suite, MariaDB 10.11 *(authoritative)* | **16,003 / 0** |
| Mutation targets killed | **8 / 8** |
| Browser (Chromium), desktop + 360 / 390 / 412 px | **42 / 0**, three consecutive runs |
| Whole-app crawl, every role | **all screens render cleanly** |
| Gate 2 suite | **170 / 0** |
| Gate 3 suite | **225 / 0** |
| Approval suites (P3-M1, P3-M4, M4, M4-correction) | **116 / 176 / 93 / 107, all 0 failed** |

Four problems in the verification itself were found and fixed at the root:

- **The historical fixtures depended on the unconditional master bypass.** Forty-five
  assertions failed the moment it became configurable, because almost every suite
  models an established workspace whose single administrator raises a record and then
  decides it. That is a real organisation with the exception deliberately enabled, so
  `tests/bootstrap.php` now says so once, out loud, instead of each fixture silently
  depending on a default — and the Gate 4 battery sets both switches explicitly for
  every assertion it makes, so the blocked paths are proved rather than assumed.
- **The concurrency worker called the internal writer, not the guarded entry point**,
  and so appeared to show a self-approval winning a race. `hreq_decide()` is where
  scope, capability and segregation are asked; `hreq_apply_decision()` is the writer
  both the direct path and the approval callback share, deliberately unguarded because
  the chain's own guard has already run. The worker was testing nothing.
- **One mutation survived because it was ineffective**, not because the code was
  unguarded: it was inserted after the branch it was meant to flip. It was moved so it
  actually bites, rather than the test being weakened.
- **This gate's own test file left a reference bound to the settings cache.** Every
  test file is required into the same global scope, so a later file's variable of the
  same name assigned a string straight into the cache and crashed the suite three
  files later, in a test with nothing to do with settings. The reference is now
  released immediately.

### G9 — What Gate 4 deliberately did NOT do

No inspector operational status (Gate 5), no Administrator-profile reconciliation
(Gate 6), no decision on Gate 3's deferred "redefined" trigger, no Person Hub, no
organisation-master convergence, no new KPI or audit engine, no Candidate Hiring
approval entity, no native mobile work, and **no production deployment**.

Gate 1B's pipeline authority, Gate 2's versioning and Gate 3's Review Required are
all unchanged and re-proved in their own suites. No approval history was rewritten, no
decision re-made, no approval record deleted.

---

# STOP

**Scope of this document.** §1–§21 and §20a are audit only — they were written
under Prompts 7B and 7C and implemented nothing. §20b (Gate 0) and §20c
(Gate 1A) are *records of authorised gates that did change code*, each within the
narrow scope its gate named:

| Section | Gate | Code changed? |
|---|---|---|
| §1–§21, §20a | 7B audit, 7C reconciliation | no |
| §20b | Gate 0 | yes — one route name, three form actions, one new test |
| §20c | Gate 1A | yes — additive helpers, one new test, regenerated manifest |
| §20d | Gate 1B | yes — pipeline authority activated across the recruitment chain |
| §20e | Gate 2 | yes — requirement versioning and change control for both entities |
| §20f | Gate 3 | yes — Review Required when a stricter approved version becomes effective |
| §20g | Gate 4 | yes — self-approval governance across every approval entity |

**Gate 4 stops here and waits for an explicit pass before Gate 5.**

Pipeline is authoritative for current recruitment state, unchanged by Gate 2.
Legacy `candidates.stage` is no longer an independent current-state authority.
An approved requirement is immutable and a pending change is not effective. A
stricter approved version now reaches every active candidate as a relationship-
specific Review Required, which only a person holding `hiring.review.clear` may
decide, and which no score can create, clear or excuse. No production deployment
was performed.
