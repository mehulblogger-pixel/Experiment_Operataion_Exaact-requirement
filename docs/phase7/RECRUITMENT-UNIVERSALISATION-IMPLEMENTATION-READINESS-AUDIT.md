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
| **C46** | The exact codes for the two B4 capabilities | **B — implementation choice** | The convention is visible and mechanical: `ACCESS_MODULES` generates `mod.<module>.view|edit`, and module-scoped rights read `hiring.admin`. `hiring.material_change.propose` / `hiring.review.clear` fit it |

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

**Everything else is implementable as documented.** F7 (route collision) is a defect to
fix, not a blocker on a decision.

## 21. Recommended next prompt

**Do not proceed to code.** The next controlled step is a short **decision-reconciliation
prompt** covering exactly F1, F2 and F3 — three narrow questions, each about how a
newly locked rule meets an existing deliberate one, with the existing behaviour quoted
so the answer is informed. None of the three reopens a closed decision.

After those three are answered, the natural next prompt is **Gate 0 + Gate 1 only**
(reachability, baseline, closed kind, kind-derived classification), with the same
batch-verify-report-stop discipline this programme has used throughout — because Gate 1
moves five subsystems through one choke point and deserves its own review.

---

## Final classification

> ## READY WITH SPECIFIC PRE-IMPLEMENTATION CONDITIONS

**Conditions, precisely:**

1. **Answer F1** — workforce hand-off at Accepted (RB-1) or at Joined (C37).
2. **Answer F2** — whether the documented master exception to segregation of duties
   survives being widened to all approval entities.
3. **Answer F3** — whether OPEN-4 narrows the Administrator role generally, or only its
   recruitment rights.
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
