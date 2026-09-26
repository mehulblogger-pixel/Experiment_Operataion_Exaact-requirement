# 02 — Permission Matrix

Who can do what to each object, traced to real `can()` checks and route guards.
Verbs: **View**, **Create**, **Edit**, **Delete**, **Approve**, **Close**, **Issue**,
**Reopen**, **Export**, **—** (none). **⚠ implicit** = allowed only because no check
blocks it (see `99-gaps-and-risks.md`). Paths relative to `phpapp/`.

> **Company Business Capabilities are NOT permissions.** `lib/connect_capability.php`
> (Combination Engine) gates only *module visibility* per company and never grants or
> removes a `can()` right — this matrix remains the sole authority on who-can-do-what.
> The `/connect-capabilities` admin screen is MASTER_ADMIN-only. See
> `00-master-revamp-prompt.md` Part II.2.

**Column keys:** MA=MASTER_ADMIN, AD=ADMIN, BD=BUSINESS_DIRECTOR, SBU=SBU_HEAD,
BM=BRANCH_MANAGER, BAM=BRANCH_APP_MANAGER, OM=OPERATION_MANAGER, CO=COORDINATOR,
AM=ASST_MANAGER, FIN=FINANCE, INS=INSPECTOR. (SR_INSPECTOR = INSPECTOR + `idems.finalize`;
sales roles BDM/KAM/MM/ME are in the CRM mini-matrix below.)

**How to read cells:** the cell shows the strongest right the role holds *by default*.
Master (MA) bypasses every gate (`access.php:530`). "Edit" implies View. Per-user
overrides and the Settings→Roles editor can change any of this at runtime.

## Operational & admin modules

| Module / Object | MA | AD | BD | SBU | BM | BAM | OM | CO | AM | FIN | INS |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Calls** ¹ | E·D | E·D | View | View | Edit | View·D | Edit | Edit | Edit | View | — ᵃ |
| **Jobs** ² | E·C | E·C | Allocate | Allocate | E·C | View | E·C | E·C | Allocate | View ⚠ᵇ | View/Close **own** ᵇ |
| **Vouchers** ³ | Full | Full | View | View | Approve | — | Approve | Approve | Approve | — | **own** V/E/Submit |
| **Profitability** ⁴ | View | View | View | View | View | — | View | — | — | View | — |
| **Business Partners** ⁵ | E | E | E | E | E | E | E | E | E | View | — |
| **Contracts (open)** ⁶ | Endorse+Approve | Endorse+Approve | Endorse | Endorse | Approve | Approve | Endorse | — | — | Register/Reopen | — |
| **Purchase Orders** ⁷ | E | E | E | E | E | E | E | E | E | E | — |
| **Hiring / Candidates** ⁸ | E | E | View | View | E | — | E | E | — | — | — |
| **Inspector master** ⁹ | E | E | E | E | E | E | E | — | — | — | — |
| **Attendance** ¹⁰ | Recon | Recon | Recon | Recon | Recon | Recon | Recon | Recon | Recon | — | self-mark ⁱⁱ |
| **Office Overheads** ¹¹ | E | E | E | E | E | E | E | — | — | — | — |
| **Lookups** ¹² | E | E | E | E | E | E | E | — | — | — | — |
| **Custom fields (per-entity)** ¹² | E | E | E | E | E | E | E | — | — | — | — |
| **Custom form builder** ¹³ | E | E | — | — | — | — | — | — | — | — | — |
| **Users** ¹⁴ | E(global) | E(global) | — | — | E(own office) | E(own office) | — | — | — | — | — |
| **Dashboards** ¹⁵ | View | View | View | View | View | View(ops) | View | View | View | View(money) | View(own) |
| **Settings** ¹⁶ | E | E | — | — | — | — | — | — | — | — | — |
| **Invoicing / Receipts** ¹⁷ | Full | Full | — | — | View | — | — | View | — | Issue | — |
| **Inspection reports (IDEMS)** ¹⁸ | Full | Full | View | Finalise | Finalise | config only | Finalise | Edit | Edit | View | Edit(own) |

**Sales / CRM roles** (they touch only the CRM modules; `—` elsewhere):

| Module | BDM | KAM | MM (Marketing Mgr) | ME (Marketing Exec) |
|---|---|---|---|---|
| Inquiries ¹⁹ | Edit | Edit | Edit | Edit |
| Quotations ¹⁹ | Create·Send | Create·Send | Create·**Approve**·Send | Create |
| Orders/contracts ¹⁹ | Edit | Edit | Edit | View |
| Sales reports ¹⁹ | View | View | Edit | View |
| Clients (directory) | View | View | Edit | View |
| Contract register ⁶ | — | — | — | — |
| Templates ¹⁹ | — | — | Manage | — |

---

### Footnotes (each cell traceable)

1. **Calls** — module gate `mod.calls.view` (`ops.php:2243`); create/edit `can('ops.call.create')||is_master()` (`ops.php:3695`) → BM,OM,AM,CO; delete `can('ops.call.delete')` (`ops.php:3588`) → BAM. Module edit default per `access.php:332-346`. **ᵃ** Inspectors have no calls module; they only see a call's context through their own job.
2. **Jobs** — module gate `mod.jobs.view` (`ops.php:2244`). Allocate/edit `is_coordinator_level()` (`ops.php:5163`) → all admin-level + AM + CO. **Close (`job-close`) is gated on `ops.job.close`** (R2 fixed): `ops_require(is_master() || can('ops.job.close') || job_owned_by_me($id))` (`ops.php` job-close) → CO, BM, OM (hold the permission) + master + the **job-owner inspector** bypass (`ops.php:2384-2388`). Roles that only *view* jobs — **FIN, SBU, BAM, AM** — can no longer close them (none hold `ops.job.close`). Report-approve `can_approve_report()` (`workforce.php:669-677`).
3. **Vouchers** — register `is_coordinator_level()` (`ops.php:4890`); a voucher's owner sees/edits their own (`can_view_voucher`/`can_edit_voucher` `ops.php:4738,4761`). Submit=owner or coord (`4989`); approve=coord, status SUBMITTED (`4993`); paid=coord (`4998`); reopen=coord, **no source-status guard** (`5002`, gaps §B-2). No segregation of duties on approve (gaps §B-2).
4. **Profitability** — computed, read-gate only `can('data.profitability')` (`ops.php:6124`); call-profit adds `data.revenue`/admin (`callprofit.php:62`). No lifecycle (see doc 03).
5. **Business Partners** — list gated `mod.clients/vendors.view` (`index.php:879`); create/edit/detail/sub-records are now **guarded (R1 fixed)**: create/edit/add need `mod.clients.edit`/`mod.vendors.edit`/coordinator-level/master; the 360 detail needs the matching view rights. An INSPECTOR can no longer create, edit or read partner records.
6. **Contracts (open)** — register `can('crm.contract.register')||is_master()` (`crm.php:1830`) → FIN; endorse `can_endorse_contract_open()` (`contracts.php:473-477`) → OM,SBU,BD,ADMIN + `users.manage.branch` (BM,BAM); approve `can_approve_contract_open()` (`contracts.php:478-481`) → BM,BAM; reopen `crm.contract.register` (`contracts.php:690`). **Note:** the *partner-screen* contract-add path (`index.php` partner-add `kind=contract`) is now gated by the **same** `can('crm.contract.register')||is_master()` check (R4 fixed) — so both doors are Accounts-only.
7. **Purchase Orders** — now **guarded (R1 fixed)**: viewing a PO (`po`) needs `mod.clients/vendors.view` (or coordinator-level/master); mutating one (pull-quote, add line) or adding via `partner-add kind=po` needs the edit right or `finance.reconcile`. No longer open to any logged-in user.
8. **Hiring** — module gate `mod.hiring.view` (`ops.php:2263`); create/edit & stage-move `is_coordinator_level()` (`ops.php:4317,4486,4557`). Engagement-mode config `is_admin_level()` (`ops.php:2502`). **Hiring-workflow (pipeline/stage) configuration** — route `recruit-pipelines`, gated `is_admin_level()` (`recruitpipe.php` `ops_recruit_pipelines()`); a Phase-2 configurable pipeline engine (per-tenant `recruit_pipelines`/`recruit_stages`). Adds **no new permission** — it reuses the admin-level config gate, exactly like engagement-mode config; the operating `mod.hiring` gates are unchanged. **Position master & org-chart** (Phase 3) — routes `positions`/`positions-org`, view gated `can('mod.hiring.view')`, manage gated `is_coordinator_level()` (`position.php`); `requisition-position` (link a requisition to a position) `is_coordinator_level()`. **Org-chart import** (`positions-import`, `is_coordinator_level()`, `position.php`) — paste or upload a CSV of an existing organogram (Name, Code, Department, Grade, Reports-to, Sanctioned, Occupied) to create the positions and build the reporting tree; header-aware, re-runnable (upserts by code/name, never duplicates), two-pass parent linking, unresolved managers reported. Adds **no new permission**. The manpower-plan validation (cases A–E) shown on the requisition is a **read-only check**, not a new status. Adds **no new permission** — reuses the hiring view + coordinator gates. **Interviews & Documents** (Phase 4) — routes `candidate-interview`/`candidate-doc`, gated `is_coordinator_level()` (`recruit_iv.php`); multi-round interviews with scorecards and a candidate document set with a status lifecycle. Sensitive document files (salary, medical, identity, PAN) are **download-restricted to `is_admin_level()`** (§39). Adds **no new permission**. **Salary structure, HR discussion, Offer & Onboarding** (Phase 5) — route `candidate-offer`/`offer-letter` (`recruit_offer.php`); manage `is_coordinator_level()`, **offer approval `is_admin_level()`** (a manager). An **unapproved offer can never be issued**. Salary figures shown only to `can_see_salary()` (`data.salary`). Onboarding reuses the existing `candidate-stage` → ACCEPTED person spine (no duplicate). Adds **no new permission**. **Configurable compensation** (Phase 5.1A) — route `comp-setup` (`comp_config.php`), `is_admin_level()`: admins define salary headings (earnings/deductions/employer) + statutory flags; the salary structure is computed from them. **Document Studio** (Phase 5.1B) — routes `doc-templates` (`is_admin_level()`) and `candidate-letter` (`can('mod.hiring.view')`) (`doc_templates.php`): configurable offer/appointment/other letter templates + letterhead, auto-filled from candidate data with missing fields highlighted. Adds **no new permission**. **Configurable approval matrix + SLA + escalations** (Phase 6) — routes `recruit-approvals` (configure rules/levels, `is_admin_level()`) and `my-approvals` (act on your own pending step, any signed-in approver whose role/named-user matches, or a master) (`recruit_approval.php`). Rules match by entity (requisition/offer/salary) and by department/BU/grade/position + value band (narrowest wins); each level carries an SLA, a reminder cadence and an escalation target; approve advances, reject/final-approve calls back into the entity (`job_offers`/`requisitions`). `offer_submit()` routes an offer through the chain when a rule matches, coexisting with the existing `is_admin_level()` offer approve as a fallback. Reminders/escalations run on the daily cron (`appr_tick()`) and e-mail through `ops_mail()`. Adds **no new permission** — reuses the admin-level config gate and, for acting, a runtime approver-identity check; **independent of** the platform's separate `/approval-rules` quote/report routing screen, which is untouched. **Recruitment exports + public careers intake** (Phase 7) — route `recruit-export` (`recruit_export.php`) streams CSV of candidates/requirements/offers/funnel gated the same as the Recruitment command centre (`recruit_home_can()`), with money columns still gated `can_see_salary()`. Route `careers-admin` (`is_admin_level()`) toggles a public **careers page** and picks which requirements are advertised (`requisitions.careers_published`, opt-in). The public page (`/careers`, `careers.php` `careers_route()`) is served **in front of `require_login()`** and is **off by default** (`careers_enabled` setting; when off, visitors are redirected to staff login); an application creates a `candidates` row (`source=CAREERS`, stage RECEIVED) against the advertised requisition — a honeypot + per-requisition de-dupe guard the intake, and the public form performs **no privileged action** on any existing record. Adds **no new permission** — additive columns only. **Auto job-description / posting generator** (`recruit_jd.php`) — route `jd-generate` (AJAX, `is_coordinator_level()`, CSRF-checked) turns a requisition's structured fields (role/department/grade/location/discipline/skills/qualification/experience + a new `responsibilities` free-text) into a job description / public posting; deterministic **template** when no AI key, **AI-enriched** (via the existing `ai_chat()` seam) when one is configured, always falling back to the template and always shown for human review. Boilerplate (intro / what-we-offer / how-to-apply / tone) is admin-configurable in the `jd_config` setting on the Careers page screen. Adds **no new permission**. **Recruitment Manager — module-scoped admin** (NEW permission `hiring.admin`, `access.php`): all recruitment **configuration** screens — `recruit-pipelines`, `comp-setup`, `doc-templates`, `recruit-approvals`, `careers-admin` — are now gated `hiring_admin_can()` = `is_admin_level() || can('hiring.admin')` (`ops.php`). A full administrator keeps access unchanged; additionally, a customer can grant a role the new `hiring.admin` permission on the **Roles & permissions** (`/access`) screen to appoint a **Recruitment Manager** who configures **only** the hiring module and holds **no** system-wide powers (cannot manage users, licensing, settings, or other modules). Off by default (in no role's defaults); enables selling Recruitment as a standalone product with its own scoped admin. Offer *approval* stays a manager action (`is_admin_level()`), and platform-wide `role-workspaces` stays master-only. **One-click "Recruitment-only company" preset** (`recruitment_only_provision()`, on the master-only Product-package screen) applies the `RECRUITMENT_HR` package **and** grants `hiring.admin` to the Coordinator role (via the non-destructive `role_grant_perm()`), producing a ready recruitment-only install with a scoped Recruitment Manager. **Extra ready-made letters** (`doc_tpl_seed_extra()`): Confirmation, Relieving/experience and Internship templates ship alongside Offer/Appointment (idempotent, editable, `OTHER` type) — all auto-filled from candidate data by the Document Studio. **Hiring request layer** (Phase 2 · M4) — routes `hiring-requests` / `hiring-request` (`hiringreq.php` `ops_hiring_requests()`), module-gated `mod.hiring.view` like every other hiring route. The request layer is deliberately **capability-gated, not role-gated**: raising, editing, submitting, cancelling or converting a hiring request needs **`mod.hiring.edit`** — the existing module edit right, the same one `projcosting.php` already requires before a costing may create a requisition — asked at each write (`hreq_save()`, `hreq_submit()`, `hreq_cancel()`, `hreq_to_requisition()`) and not only on the route. Adds **no new permission**. *Deciding* a request (approve/reject) is held apart from creating one: `hreq_can_decide()` = `can('mod.hiring.view')` **and** `is_admin_level()` — the module question first, so a role band cannot open a module the workspace has not bought — and the requestor (`hiring_requests.requested_by_id`) **may not decide their own request**, with a master the single stated exception (segregation of duties, mirroring the IDEMS approver≠issuer rule). Branch scope is enforced by `hreq_scope_gate()`, which checks the request **and** the branch named on the way in. Note this gate is **narrower** than the older `is_coordinator_level()` band used by the direct requisition route: the band includes Asst. Manager, to whom this matrix grants no hiring right; the capability does not. The direct requisition route (`requisition-new`, `is_coordinator_level()`) is **unchanged**; the two routes and the open policy question are recorded in `docs/adr/ADR-001-direct-requisition-path.md`. **Hiring request approval** (Phase 3 · M1) — the request is connected to the **existing** Phase-6 approval engine (`recruit_approval_requests`, which was already polymorphic: `entity` + `entity_id`). `APPR_ENTITIES` gains `HIRING_REQUEST`; **no new table, no new column and no new permission**. Deciding through a chain requires being that step's approver (`appr_can_act()` — the named user or the configured role, i.e. *configuration*, never a hard-coded job title), and then passing `appr_guard()`, which asks **entitlement first** (`licence_blocks('mod.hiring.view')`, so a master on an unlicensed workspace is refused), then branch scope (`hreq_in_scope()`), then segregation (`hreq_segregation_blocks()` — the requestor may not approve their own request, master excepted). Deciding **directly**, where no rule matched, keeps M4's rule (the module + `is_admin_level()`) and is refused while a chain is open. Two pre-existing defects were fixed for **every** entity, not just hiring requests: `/my-approvals` asked **no** module question at all (it is in neither route map) and now asks the licence; and `appr_can_act()` began with a bare `is_master()` that walked past the licence, now `is_master_of('hiring')`. The licence — not `mod.hiring.view` — is what `/my-approvals` asks, so a configured approver who legitimately holds no recruitment module (a Finance approver on an offer chain) is not locked out; narrowing the approver population is a policy question, not a defect. **Approval SLA, reminders & escalation** (Phase 3 · M3) — adds **no new permission** and **no new status**. Three things changed about who can do what. (1) **Approval policy is now gated at the write.** `appr_rule_save()`, `appr_rule_set_active()`, `appr_level_save()` and `appr_level_delete()` — which carry `sla_days`, `reminder_days`, `escalate_role` and `escalate_user_id` — previously trusted the `recruit-approvals` route for authorization, so a direct POST or AJAX call reached the policy without it. They now ask `appr_config_can()` themselves: **entitlement first** (`licence_blocks('mod.hiring.view')`, so a master on a workspace without recruitment is refused — no master bypass of entitlement), then the existing `hiring_admin_can()` capability. The population that may configure is unchanged; where the question is asked is not. (2) **The nightly approval tick** (`appr_tick()`) asks the same entitlement question itself, in addition to `cron.php`'s existing `$m8('hiring')` gate. (3) **Notification now follows M2 delegation**: `appr_step_recipients()` adds the current delegates of whoever the step names, so the person the system is waiting for is the person it writes to. This is **visibility only** — `appr_can_act()` remains the only thing that decides who may approve, and an escalation recipient gains nothing at all (`SLA ≠ authority`, `escalation ≠ authority`, `notification ≠ authority`, `inbox visibility ≠ authority`). The `/my-approvals` screen gains a read-only "waiting on someone else" list, restricted to **HIRING_REQUEST** rows whose `requested_by_id` is the viewer's own id, behind the same licence question as the rest of the route. **M3 CORRECTION** — an adversarial audit proved the notification layer broader than the approval layer, so the rule is now stated and enforced: **notification eligibility is never broader than approval visibility.** Recipients (named user, role holders, and their current M2 delegates) are only *candidates*; each is then asked the same question `/my-approvals` asks — `appr_visible()` = `appr_can_act()` + `appr_guard()` — via `appr_may_be_asked()`, which evaluates it **as that candidate** (`appr_as_user()`, session restored in a `finally`) because entitlement, branch scope and segregation are all questions about the current user. Consequences: a holder of the approver's role in **another branch** is no longer e-mailed a hiring request they cannot see or decide; a **requestor** who happens to hold the approver role is no longer asked by e-mail to approve their own request; a **named** approver outside the branch scope is refused too (being named is not a bypass); and an unlicensed workspace notifies nobody. Escalation notices use a second, lower disclosure level, `appr_may_be_told()` — entitlement, the record existing and branch scope, **without** segregation, since telling the person who raised a request that it is late is the point — so an escalation contact who cannot see the branch is not told what it concerns, and the message states that it confers no authority. `appr_role_emails()` ("every active holder of this role, anywhere") is **deleted**. Also: **approval delegation writes now ask `appr_config_can()`**, the same entitlement-then-capability gate as the approval matrix, closing an asymmetry in which a workspace without recruitment could not edit its matrix but could still move approval authority; create, edit, revoke and deactivate are all covered at the write. Adds **no new permission** and **no new status**. **Recruiter accountability — the assignment door** (Phase 3 · M5) — `recruit_assign.php`. Who is accountable for a requirement (`requisitions.recruiter_id` / `manager_id`) and who is chasing a candidate (`candidates.recruiter_id`) is no longer written by whatever form happened to post it. Every production path — `requisition-new`, `requisition-edit`, `candidate-new`, `candidate-edit` and the public careers intake — now changes ownership only through `rasg_assign()`, which asks, in this order and each failing closed: **entitlement first** (`licence_blocks('mod.hiring.view')`, with **no master bypass**), then the **existing** `is_coordinator_level()` band the recruitment write routes already require, then the record, then the **actor's** branch scope (`scope_allows`), then the requisition/candidate state, then the **M4** execution boundary, then — the questions nobody was asking before — that the person being given the work **exists in this workspace**, is **not deactivated** (`is_active` and `deactivated_at` both read) and **covers that branch themselves** (evaluated as that person via `appr_as_user()`, so the scope rule cannot drift from `ua()`). A change also carries the owner the screen was showing, and a save whose baseline no longer matches is refused rather than merged. **Adds no new permission and no new status**: the population that may assign is unchanged, what changed is that the questions are asked at the write instead of being implied by a dropdown. The public careers page **inherits** a requirement's recruiter rather than copying it, and skips **only** the permission question — an application can never hand work to somebody who has left. Ownership history is kept in an append-only `recruiter_assignments` ledger and on the activity spine, so a reassignment no longer erases who was accountable. **Visibility narrowed (same change):** the Recruitment command centre (`rcc_data()`) and the recruitment CSV export (`recruit_export.php`) built their WHERE clauses from the screen's own filters and **no branch scope at all**, so every branch's candidates, requirements and **recruiter names** were visible to anyone who could open recruitment. Both now scope like the registers beside them — requirements by `scope_clause()`, candidates through the requirement they are worked against (`scope_office_clause()`, whose "no office means everybody" rule keeps unattached candidates from vanishing) plus the module's existing `recruit_sbu_clause()`. No rule was invented; the existing ones are now applied where they were missing. **Integrated execution gate** (Phase 3 · M6) — `recruit_exec.php`. Recruitment may only SPEND an approved requirement through one composed question, `rexec_block_reason()`, which asks the existing authorities and reports the first refusal: the requirement's own lifecycle status (M3), then **M4's** execution boundary (`hreq_req_block_reason`), then — for a joining only — whether an approved seat is actually free (M3's `reqf_counts`). It is now asked by the paths that never asked before: **offer** create/submit/approve/issue/accept (`recruit_offer.php`), **interview scheduling** (`iv_schedule`), the **configured pipeline** (`recruitpipe_cand_goto`) and the **joining** itself, alongside the candidate and requisition saves M4 already covered. Consequences for who can do what: an offer can no longer be created or issued, an interview can no longer be scheduled, and a candidate can no longer be advanced, on a requirement whose approval has been invalidated or that is CANCELLED/CLOSED; and **no more people may join than were approved** — a joining written past the gate is reverted, audited and not counted. Deliberately NOT gated, and asserted as such: recording the OUTCOME of an interview that already happened (refusing it would destroy information); the NUMBER of offers, since the business runs more offers than seats because offers are declined, and only the joining consumes a seat; closing a candidate out (reject / withdraw / decline / hold), so a pending re-approval never traps a person; and ADR-001 direct candidates with no requisition, which have no approval to respect and no ceiling to apply. **Adds no new permission, no new status and no new route.** Also fixed in the same milestone: the Phase-6 approval engine's REQUISITION callback wrote `status='approved'` and `status='on_hold'` — neither is in the requisition lifecycle (`docs/03-object-lifecycles.md`), and the measured effect was that an approved requirement disappeared from the command centre's open demand and from every status-based query. The decision is now recorded in the chain, in `approved_by` and on the audit spine, and the STATUS is left to M3's `reqf_sync()`, the only thing entitled to derive it. **Capacity and incumbency — ratified business rule (invariant I21).** Recruitment must never exceed approved capacity and must never displace an established holder merely to manufacture a concurrency winner; where simultaneous claims cannot be deterministically resolved without risking displacement, the system may refuse the contested claims and leave the capacity available for a subsequent valid transaction. In practice: a joining is refused when no approved seat remains, and a joining written past that check is reverted by a compensating check that asks only whether a seat was free for the ARRIVING claim — it never re-ranks the people already seated. Two earlier attempts to guarantee a winner under a dead heat (ranking seat-holders by decision time, then by id) each let an arriving candidate displace an established one and were abandoned. The refusal never consumes the capacity: the reverted candidate returns to their prior stage, the requirement's counts are re-synced, no joining is recorded, and the seat is immediately usable by the next valid transaction. Moving a candidate who ALREADY holds a seat onto a different requirement is treated as a **joining on the destination**, not an advance, so it cannot push that requirement past its approved seats. Adds no permission and no status.
9. **Inspector master** — `/m/inspectors`, master config `access='admin'` → `is_admin_level()` (`ops.php:2049,2460`); allowances/rates `is_master()` (`ops.php:3418`); salary field gated `can_see_salary()` (`ops.php:3428`).
10. **Attendance** — reconcile `is_coordinator_level()` (`ops.php:6177`); **ⁱⁱ** self-mark (`attend-mark`) is intentionally ungated beyond "login linked to an inspector" (`attend.php:189`, gaps §B-4).
11. **Office overheads** — edit `is_admin_level()||can('settings.manage')` (`ops.php:6041`); global default `settings.manage||master` (`ops.php:6052`).
12. **Lookups & per-entity custom fields** — `is_admin_level()` (`lookups.php:635`); delete built-in list `is_master()` only (`lookups.php:659`). Not in the module-gate map — guarded in the handler.
13. **Custom form builder** — `cform_can_manage()` = `is_master()||can('settings.manage')` (`customforms.php:64,91`).
14. **Users** — `can('users.manage.branch')||can('users.manage.global')` (`ops.php:6608`); branch managers limited to own office (`ops.php:6618`); role-access editor `is_master()` (`ops.php:2412`). Global default = MA/AD only. **Role workspaces** (additive) — `role-workspaces` (`workspace.php` `ops_role_workspaces()`), `is_master()`: per-role landing page + a curated dashboard launchpad, stored per-tenant in the `workspace_config` setting. Every landing and tile is re-checked against the user's own permission-filtered menu (`ops_nav_index`) at render/redirect time, so a workspace **can never expose a screen the user is not already allowed to open**. Personal override `my-start` (self-service, any signed-in user) sets `users.start_route`, validated the same way. Adds **no new permission**. Data-scope hardening (same change): the dashboard "open requisitions" tile and the candidate-form requisition dropdown (`requisitions_list()`) now apply `scope_clause()` like the main list, so neither leaks other branches' requisitions.
15. **Dashboards** — landing `/` only needs login; `ops_reports` needs a `dash.*` perm (`ops.php:6416`), each tile gated individually. Inspector gets the personal branch (`dashboard.php:23`).
16. **Settings** — `can('settings.manage')` (`ops.php:6806`); module-licence toggle `is_master()` (`ops.php:6812`); seed/reset `is_master()` (`ops.php:2531+`).
17. **Invoicing/Receipts** — open/view `books_can()`=finance.reconcile||data.credit||master (`booksui.php:87`) → FIN, CO, BD/SBU/BM (via data.credit), MA; all mutations `$canIssue||data.credit` (`booksui.php:190`); **issue** `books_can_issue()`=finance.reconcile||master (`booksui.php:257`) → FIN; cancel same (`booksui.php:265`).
18. **IDEMS reports** — view `mod.idems.view` (`ops.php:2290`); create/edit `mod.idems.edit` + `idems_can_edit_doc` (`idems.php:4185,3467`); approve step `idems_can_act_step` (`idems.php:5732`); finalise `is_master()||idems.finalize` with **approver≠issuer** (`idems.php:4456,4460`); type/format config `idems.type.manage` (`idems.php:4604+`); timestamps `idems.timestamp.edit` → BAM (`idems.php:7357`); audit log `idems.audit.view` (`idems.php:9772`).
19. **CRM modules** — module gates `mod.inquiries/quotes/crm_orders/crm_reports.*`; fine-grained `crm.quote.create/approve/send`, `crm.template.manage` per `access.php:456-461`. Quote-approve default = MARKETING_MANAGER only (`access.php:459`); **not** BRANCH_MANAGER.

## Connect — marketplace (external portals, K2b)

The manpower-marketplace staff desk (K2a) reuses coordinator/master and adds **no**
permission. The **professional-identity console** (`/connect-identity`, linking an
internal `inspectors` record to a marketplace `cx_professionals` record — a
relationship, never a merge) reuses the **same** coordinator/manager/master gate
(`connect_identity_admin_can` → `connect_market_can`) and adds **no new
permission**.

**Phase 6 · Batch 1 — one entitlement for every writer of the identity ledger
(R25 · invariant I26). This CHANGES who can do what, and is the only such change
in the batch.** `cx_identity_link` is written from two places: the marketplace
console above, and the recruitment candidate screen (`/candidate-link-pro`,
`/candidate-unlink-pro`). The console asked for Connect; the recruitment routes
asked only whether Recruitment was bought, so the same ledger was protected by
two different questions and a workspace without Connect could still create,
change and remove marketplace-professional identity relationships. Every writer —
`connect_identity_link_create()`, `connect_identity_candidate_link_create()` and
`connect_identity_unlink()` — now asks `connect_identity_admin_can()` **itself**,
so a route cannot inherit a weaker gate than the ledger requires. **No new
permission and no new entitlement engine**: the existing composition
(`licence_module_live('connect')` + the coordinator/manager/master band) is
reused unchanged. **Consequence:** a customer entitled to Recruitment but *not*
to Connect can no longer link a candidate to a marketplace professional. This was
approved by the owner on the explicit ground that such a customer has no
marketplace professional to link to, so the capability was unusable in any case
and permitting the write was entitlement leakage. The coordinator/manager/master
band itself is unchanged.

**Branch visibility of parties, and of the numbers that describe them
(owner decision, this change).** Three things were settled together; none of them
adds, removes or widens a **permission** — each narrows what an already-permitted
user is *shown*, which is scope, not authority.

1. **A branch sees its own parties.** The client and vendor directory
   (`/clients`, `/vendors`) and the dashboard tiles that count them now scope on
   `business_partners.home_branch_id` through the existing
   `scope_office_clause()`. That column has always existed and has always been
   editable on the 360 screen; nothing scoped on it, so every branch read the
   whole company's directory. **A party with no branch set stays visible to
   everyone** — the same "unassigned belongs to nobody, so it must not vanish"
   rule leads, opportunities and complaints already use. Every existing party is
   unassigned, so a strict filter would have emptied the register for every
   branch user on the day it shipped; the directory tightens as the field is
   filled in instead of going dark. Making it strict later is a one-line change
   and a data question, not a permission question.

2. **The two-office rule for work orders is now stated once.** A work order
   belongs to the office that **contracted** it as well as the one **executing**
   it, and both must see it. The calls register has always done this; the
   dashboard's open-work-orders count scoped on the executing office alone and so
   **under-reported against the very list it linked to**. The rule now lives in
   `call_office_clause()` (`access.php`) and both callers ask it. The register's
   behaviour is unchanged — this is the count catching up, not a widening.

3. **Three counts that ignored scope now honour it.** The open-leads and
   open-deals dashboard tiles and `quotes_expired_count()` (the Command Centre's
   "Quotations lapsed") were hand-written `COUNT(*)`s that reported the whole
   company while their registers scoped by branch. Each now uses
   `scope_office_clause()` on the column its own register already scopes on.

**The rule these four share:** a count and the list it links to must answer the
same question. Where they disagreed, the list was right. Evidence and the full
tile-by-tile trace are in `docs/phase7/OFFICE-SCOPE-DASHBOARD-AUDIT.md`;
`tests/test_office_scope_counts.php` holds the behavioural proof, and each fix
was mutation-tested by reverting it and confirming the suite fails.

**Scope on identity relationships (R15 · invariant I16 — PARTIAL, deliberately).**
No identity path evaluated branch scope at all. Each writer now applies
**per-end visibility**: you may not build or break a relationship out of a record
you are not allowed to open — an inspector by `scope_allows()` on its
`home_office_id` + `sbu`, a candidate by the new `scope_sbu_allows()` (candidates
carry a business unit and **no** branch, so `scope_allows(null, …)` would read
the missing office as Ahmedabad and wrongly refuse every branch-scoped user), and
a marketplace professional by existence alone, because it is **tenant-global** and
carries neither. This grants nothing: it applies an existing rule where it was
missing. Whether the *relationship itself* carries a branch — a branch-scoped
inspector linked to a tenant-global professional — is **open (Q5/Q11)** and is
**not** decided here. `scope_sbu_allows()` is the SBU-only scalar twin of
`scope_allows()`/`scope_office_allows()` and confers no rights of its own.

**A record id is never authorisation (R24 · invariant I25).**
`/candidate-unlink-pro` passed the posted `link_id` straight to the ledger, so any
live link in the workspace — including a professional↔inspector link with no
connection to candidates — could be removed from a candidate screen. The ledger
now takes the record the caller is acting for and refuses any link that is not
that record's, using the **same words** it uses for a link that does not exist, so
the refusal cannot be used to enumerate other people's relationships. The
marketplace console states the same expectation for its own axis. **No permission
changes**; the population that may unlink is identical.

**Reading is not creating (R22 · invariant I23).** `inspectors_list()` called
`link_inspector_users()`, which created `inspectors` rows and wrote
`users.inspector_id` — at seventeen call sites, inheriting whatever gate the
calling page had. The call is removed and nothing lazy replaces it.
`link_inspector_users()` survives as an **explicit** reconciliation on the People
screen that asks for `users.manage.branch` / `users.manage.global` **itself**
rather than inheriting the caller's gate (invariant I27), and is transactional so
a failure cannot leave a team member belonging to nobody. **No new permission** —
this is the right the People screen already required to link a login to a team
member by hand. The residual backlog is reported by `team_unlinked_logins()` on
the People screen and in system status, so it is visible rather than silently
healed. The **award→deployment bridge** (the "Create deployment" action on
the awarded requirement desk, which creates a PDSO `jobs` deputation from a
marketplace award — assigning the internal inspector the awarded person is linked
to) runs inside that same coordinator/master requirement desk and adds **no new
permission**; final operational authorization continues through the existing
competence/PDSO controls (ISO 17020 §41). The **unified sourcing screen**
(`/connect-source?job=ID`, linked from the job detail) ranks internal inspectors,
marketplace professionals and the client's bench for an inspection job and lets
staff assign the awarded person onto `jobs.inspector_id`; it reuses the same
coordinator/master talent right (`connect_source_can` → `connect_market_can`),
adds **no new permission**, and refuses to place a marketplace professional until
they are linked to an inspector record — so competence/authorization is not
bypassed (§41). **Requirement reuse** (duplicate + templates) runs inside the
existing client `market.post` right (client `portal/hire`) and the
coordinator/master requirement desk (staff Duplicate) — **no new permission**.
The **matching-weights admin** (`/connect-match-weights`, `connect_match_weights_can`)
is **master/admin only** and tunes ranking configuration, not access — it grants
no rights over any record, so it adds no data permission to the matrix. The **external self-service** side (K2b) adds two named portal
permissions, in the portals' own permission systems — separate from `ORG_ROLES`:

20. **Client portal — post a requirement + search the pool** — `pcan('market.post')` (`portal.php` `PORTAL_PERMS`; routes `portal/hire`, `portal/hire-req`, `portal/find`). A logged-in **client (company)** posts a technical-manpower requirement (posted to its own `poster_party_id`) and manages **its own** requirements' applications (shortlist / offer / award / reject) — ownership enforced in the route. The **same** permission also gates `portal/find` and `portal/hiring`: the client searches the shared professional pool (one keyword + filters), sees privacy-safe cards, **requests contact** (the professional approves — no contact is exposed by the search itself; enforced by `connect_privacy_resolve`), **invites** a professional onto one of **its own** open requirements (ownership re-checked in the route), and — on `portal/hiring`, the **buyer home** for a marketplace-first client — sees its own open requirements, applicants awaiting a decision, sent contact requests and saved searches (all scoped to `portal_partner_id()`). A marketplace-first client (a self-service `cx_organisations` signup with no inspection footprint) is routed to `portal/hiring` as its home and its nav drops the inspection menu; an established inspection client keeps its dashboard and merely gains a hiring shortcut. The same right also gates `portal/roster` — the client's **private bench**: a relationship over `cx_professionals` (add from marketplace/previous/manual, private notes & ratings, rehire onto its own open requirement), with the private relationship data scoped to `client_party_id` and never exposed to the professional or other clients. No new permission — browsing/inviting/the buyer home/the private bench are the discovery half of the same hire right. Granted by default to a full-access client user; removable per client user via the portal team editor. Maps `company` → client portal (adopted role→portal mapping).
21. **Vendor portal — apply to requirements** — `vcan('market.apply')` (`cvp.php` `VENDOR_PERMS`; route `vendor/opportunities`). A logged-in **vendor / agency** browses OPEN/SHORTLISTING requirements and applies as itself (`applicant_party_id` = the vendor party; one application per requirement). Maps `agency` → vendor portal. (Agency-side *posting* reuses the same engine and is a later toggle; not enabled in K2b.)

Neither touches `ORG_ROLES` or any staff permission; the staff desk lifecycle
(K2a) and these external capabilities operate on the same additive
`cx_requirements` / `cx_applications` tables.

### Qualification & role taxonomy — configuration (K13 / #2)

The ITI→MBA qualification taxonomy (`/connect-qualifications`) adds **no new
permission** — it reuses the **Lookups** gate (row 12) exactly:

- **View** (read-only, at a glance): `connect_qualtax_can()` — master, admin-level
  or coordinator-level (same readers as the K0 industry taxonomy).
- **Configure** (add / edit / switch on–off every job family, role, qualification
  level, ITI trade, certification): `connect_qualtax_manage_can()` = `is_admin_level()`
  — the same door as Lookups & custom fields. The masters are runtime data, not
  hard-coded; a seeded row is marked **built-in** (`is_system=1`) and can be edited
  or switched off by an admin.
- **Hard-delete a built-in row**: `is_master()` only (mirrors Lookups' "delete
  built-in list = Super Admin only"). Admin-added rows are deletable by any admin.

No `ORG_ROLES` entry, object status or module gate is introduced.

### Verification & moderation desk (K14 / #3)

The verification desk (`/connect-verify`) adds **no new named permission** — it
reuses the coordinator/moderation level, like other back-office review desks:

- **Review** (approve / reject a pending identity or credential check, which
  recomputes the professional's verification tier): `connect_verify_can()` =
  `is_master()` or `is_coordinator_level()`.
- **Submit** a check about oneself: a **professional** does this from their own
  `/pro` portal (`pro/verify`) on their own record — the portal's own session
  (`cxpid`), not an `ORG_ROLES` permission.
- The tier ladder (Registered → ID-verified → Credential-verified → Proven) is
  written to the existing `cx_professionals.verification_tier` column and is
  elevated **only** by a genuine VERIFIED decision (human moderator now, or a
  KYC/DigiLocker provider through the same seam later) — never by a deterministic
  format pre-screen alone. No object status or module gate is introduced.

### Rating-integrity desk (Review & Reputation)

Two-way ratings (`cx_ratings`, K9) gain a **payer-reputation** view (a professional
records how a client paid — on-time / late / partial / unpaid — so a freelancer sees
a client's reputation before accepting) and a **rating-integrity dispute**
(`cx_rating_disputes`, lifecycle in `03-object-lifecycles.md`). Adds **no new named
permission**:

- **Raise a report** on a rating **about oneself**: a **professional** from their own
  `/pro/reputation` (portal session `cxpid`), a **client** from `/portal/reputation`
  (scoped to `portal_partner_id()`). Ownership is re-checked in the route — a party can
  only report a rating whose ratee is themselves.
- **Investigate & decide** (`/rating-disputes`): reuses the coordinator/moderation
  level — `connect_market_can()` — like the K9b dispute and the verification desk.
  Outcome `UPHELD` / `ANNOTATED` / `REMOVED`; a removed rating is hidden from scores,
  never deleted. No object status (beyond the new object's own) or module gate is
  introduced.

### In-app messaging (K15 / #4)

Per-engagement two-way threads (`cx_messages`, keyed to a `cx_applications` row)
add **no new named permission**:

- **Staff desk** (`/connect-messages`): read/post on any marketplace thread —
  `connect_msg_staff_can()` = `is_master()` or `is_coordinator_level()` (same as
  the rest of the marketplace desk).
- **Professional** (`/pro/messages`): read/post **only on their own engagements**
  (`applicant_professional_id` = the logged-in `cxpid`), enforced by
  `connect_msg_pro_owns()`; their own `/pro` portal session, not an `ORG_ROLES`
  permission.
- The engine is identity-agnostic (`staff | professional | client | vendor |
  inspector`), so the client and vendor portals attach to the same threads later
  under their own portal sessions. No object status or module gate is introduced.

### Reusable KPI board (ops "concern" + client + freelancer + inspector dashboards)

`connect_kpi_board($scope)` + `connect_kpi_render()` (`lib/connect_kpi.php`) are
**read-only** and add **no new permission, table or status**. One engine powers
all four dashboards; the difference is only the **audience + scope**:

- **Ops dashboard** (`views/dashboard.php`, managers/coordinators branch): staff
  scope — aggregates across all clients. Shown only when `connect_enabled()`.
- **Client portal dashboard** (`views/portal/dashboard.php`): client scope —
  `party_id = portal_partner_id()`, so a client sees only their own figures.
- **Freelancer dashboard** (`views/pro/dashboard.php`, `audience = 'pro'`): the
  professional's own scope — `party_id = cx_professionals.id` from the `/pro`
  session (`$_SESSION['cxpid']`). The freelancer sees only **their own** cockpit:
  assignments (`connect_engage_summary_pro`), booked value (deterministic
  man-days/months × rate from `connect_engage_describe` — never a payout), their
  applications (`cx_applications.applicant_professional_id`), client ratings on
  their own awards (`cx_ratings` ⋈ `cx_applications`), and verification tier/trust
  (`connect_verify_*` / `connect_trust_score_pro`). Escrow/earnings stay out of
  scope (gated slice #10).
- **Inspector dashboard** (`views/dashboard.php`, `is_field_inspector()` branch,
  `audience = 'inspector'`): the field engineer's own scope — `party_id =`
  the user's `inspector_id`. Reuses **only** the core `jobs` register scoped to
  their own `inspector_id` (active / completed / overdue / reports-pending — the
  same counts the inspector dashboard already ran) plus the inspector rating
  summary (`cx_rating_summary_for_inspector`). **Least-privilege: the board
  carries NO money figure** — billing and profitability are never the
  inspector's — so it adds no visibility the role did not already have.
- Every metric **reuses an existing engine** — `financial_rollup(['partner_id'])`
  (revenue), `complaints.partner_id` (concerns), `report_docs.client_id` (reports
  pending), `jobs↔calls.client_id` (inspections), `cx_ratings`/`rating_all`
  (ratings), plus the professional-side engines above — so no metric is
  re-implemented. Each figure is only ever as visible as the register behind it
  (client scoping via `portal_partner_id()`; freelancer scoping via the `/pro`
  session identity).
- **One card design.** `connect_kpi_render()` emits the design-system's universal
  KPI card markup (`.kpi-row` / `.kpi` / `.tone-*` / `.pill`, per
  `docs/DESIGN-SYSTEM.md`), so on any screen that loads `assets/css/app.css` the
  board **is** the shared component. The self-contained client/freelancer portals
  get an identical look from a single zero-specificity `:where()` fallback that
  reads each portal's own tokens — the real component always wins where present,
  so there is one look and no style drift.

### Engagements / bookings + freelancer self-service (K20)

The booking model (`cx_engagements`) and the freelancer gap-fill add **no new
permission**:

- **Record / edit a booking basis** (man-days / man-months / long-term deputation /
  continuous / regular frequency), rate, dates and lifecycle status: on the
  requirement desk, which is already coordinator/master-gated (K2a). A booking can
  be recorded only **after** the requirement is AWARDED, and its subject is derived
  from the awarded application (professional / inspector / bench).
- **Withdraw an application**: a professional withdraws only their **own** live
  application (APPLIED / SHORTLISTED / OFFERED → WITHDRAWN) via their `/pro`
  session (`connect_pro_withdraw`, scoped to `cxpid`); never someone else's.
- **My bookings** (`/pro/bookings`): a professional sees only engagements where they
  are the subject (subject-scoped). No object status beyond the documented
  application/requirement lifecycles is introduced; `cx_engagements.status`
  (BOOKED / ACTIVE / COMPLETED / CANCELLED) is the engagement's own lifecycle.

### Engagement vouchers — inclusive / exclusive, per-day / per-deployment (K21)

The claim raised against a booking (`lib/connect_engvoucher.php`). Adds **no new
named permission**; it reuses the identities already in place:

- **Rate model chosen at posting.** The client sets `rate_inclusive`
  (all-inclusive vs fee-only) and `voucher_cadence` (per-day vs per-deployment) on
  the requirement when posting; the booking inherits them, and the desk can adjust
  them on the booking form (coordinator/master gate, K2a). This decides whether the
  voucher may claim reimbursable heads (travel / hotel / local conveyance /
  allowances) — an INCLUSIVE rate claims none.
- **Raise / edit / submit a voucher**: the subject owns only their **own** vouchers.
  A marketplace professional does this in their `/pro` session (`/pro/vouchers`,
  scoped to `cxpid`); the same engine serves an **inspector on a company/agency
  roll** and an **agency-bench** person (subject-scoped, `connect_engv_for_subject`).
  A draft is editable; once **SUBMITTED** it is locked to the raiser.
- **Approve / mark paid**: on the marketplace desk's existing coordinator/master
  gate — **no new permission**. The voucher lifecycle
  (`DRAFT → SUBMITTED → APPROVED → PAID`, `REJECTED` return path) is recorded in
  `docs/03-object-lifecycles.md`. Every money figure stays scoped to the person who
  earned it; nothing here widens who can see billing or profitability.
- **Platform commission + settlement + report gate** (matchmaker model — the
  platform is not a paymaster and carries no liability): a nominal commission on the
  **fee only**, split 50/50 (rate = admin setting `connect_commission_pct`, default
  5%). Neither side "pays" through the platform — **both confirm** payment (client
  paid / professional received), and only when both confirm does the voucher settle
  (→ PAID). The professional's **inspection report** (`cx_engagement_reports`) is
  withheld from the client until the engagement is cleared; the client report-serve
  route (`portal/report-file`) enforces this (HTTP 402 until cleared). **No new
  permission** — the client uses `market.vouchers`, the professional their own `/pro`
  session; commission and settlement are recorded, not gated by a new right.
  The **commission rate** is set on the marketplace board (`/connect-requirements`)
  by a **master** only (`setting connect_commission_pct`), which also shows the
  platform's earned / settled / in-review commission rollup. On a **client-posted**
  job the ops desk shows the vouchers **read-only** — the approve / send-back /
  mark-paid and raise actions are hidden (the client drives them in its portal);
  `connect_requirement_client_posted()` decides this from the poster being a client
  party.
- **Client review of a posted-job voucher** (marketplace matchmaker model): the
  client who posted the job reviews the professional's claim in its portal — sees the
  fee, day lines and **receipts**, then **returns for clarification** (with a note) or
  **approves**. Gated by a **new client-portal permission `market.vouchers`** (portal
  permission system, not the staff `can()` matrix — doc 02's staff matrix does not
  govern portals); ownership is by the voucher's `poster_party_id`, so a client sees
  only its own posted-job vouchers. The professional reopens a returned voucher and
  resubmits. No new voucher status.
- **Supporting documents (receipts / bills)**: the raiser attaches receipts to back
  the claim; the approver sees them with the voucher. Allowed only while the voucher
  is DRAFT or SUBMITTED, frozen once approved/paid. A professional serves and manages
  only their own (`/pro/voucher-file`, subject-scoped); the desk views/uploads on the
  same coordinator/master marketplace gate (`/connect-voucher-file`). Additive
  `cx_engagement_voucher_files`; **no new permission**.

### Matching, cross-pool trust & AI re-ranking (K17 / #6)

Adds **no new permission**. The recommender and its cross-pool Trust Score render
on the requirement desk, which is already coordinator/master-gated (K2a). The
optional **"Rank with AI"** toggle simply reuses the existing `ai.php` seam
(`ai_enabled()`) plus a `connect_ai_match` on/off setting (admin, via Settings);
AI may only reorder/annotate the rule-provided shortlist — it can never change
eligibility, invent a candidate, or bypass any gate.

### Labour-market analytics (K19 / #8)

The analytics dashboard (`/connect-analytics`) is **read-only** and adds **no new
permission**: `connect_analytics_can()` = `is_master()` or `is_coordinator_level()`
(the marketplace/reporting desk). Every figure is a live aggregation over the
existing cx_* tables — no new table, status or module gate is introduced.

### Agency bench workspace (K18 / #7)

The agency bench (`/connect-bench`) adds **no new named permission** and enforces
the **privacy invariant**:

- **Manage** an agency's private roster + allocations: `connect_bench_can()` =
  `is_master()` or `is_coordinator_level()` (the marketplace desk).
- **Privacy**: bench people live in their own `cx_bench` table, scoped by
  `org_id`, and are **never** written into the shared self-registered pool
  (`cx_professionals`) or surfaced in public search / the shared recommender — an
  agency's employees stay private to that agency (an invariant asserted by
  `test_connect_bench.php`). Every bench read/allocation is org-scoped, so one
  agency can never see or allocate another's people.
- Only orgs of type `MANPOWER_AGENCY` / `RECRUITMENT_AGENCY` may hold a bench.
  No object status or module gate is introduced.

### WhatsApp / SMS / Email channel (K16 / #5)

Outbound alerts (`/connect-channels`) add **no new named permission**:

- **View** the channel desk (outbound log, counts): `connect_channels_can()` =
  `is_master()` or `is_coordinator_level()`.
- **Configure** (set delivery mode; edit / approve / enable templates):
  `connect_channels_manage_can()` = `is_admin_level()` — same door as Settings /
  Lookups.
- **Consent** is the professional's own: they opt in per channel (WhatsApp / SMS /
  Email) from their `/pro` profile; WhatsApp/SMS need a mobile. No opt-in → no
  message. Nothing is *sent* until an admin sets mode `live` AND a provider is
  connected AND the template is APPROVED — until then messages are recorded, not
  sent. No object status or module gate is introduced.

---

**Public workspace signup (`tenant_requests`).** A new company applies for its own
operations workspace at `/get-started` (public; off until the operator enables it).
Approve / decline / provision on the Workspaces panel reuse **`can_manage_tenants()`**
(Master Admin, base/control domain only) — **no new permission**. See
`03-object-lifecycles.md` → `tenant_requests.status`.


---

**Multi-source fulfilment (Phase 4)** — `recruit_fulfil.php`, route
`requisition-allocations` (POST only), module `hiring`. An approved requirement's
headcount can now be **promised to several sources** (own payroll, direct
recruitment, internal transfer, manpower agency, sub-contract agency, supplier,
freelancer, consultant, marketplace, client bench) without becoming several
requirements. **Adds no new permission, no new route family and no new status on
any existing object**; the one new object's lifecycle is in
`03-object-lifecycles.md` → *Fulfilment allocation*.

*Who may change sourcing.* `rful_may_touch()` asks, in this order, each failing
closed: **entitlement first** (`licence_blocks('mod.hiring.view')`, with **no
master bypass** — a superuser on a workspace that has not bought hiring is
refused exactly like anyone else), then the **existing** `is_coordinator_level()`
band the recruitment write routes already require, then the record, then the
**actor's** branch scope (`scope_allows`). The order matters and is asserted: a
refusal is decided about the *person* before anything that would describe the
record, so refusals cannot be used to probe another branch's requirement or its
remaining headcount. Promising or resizing seats is **recruitment execution**, so
it also passes **M6's** gate (`rexec_block_reason`), which asks **M4's**
boundary — Phase 4 adds no second opinion about whether a requirement may be
executed. Giving seats **back** (release / cancel) is deliberately *not* gated on
executability: tidying up after a requirement is cancelled is not execution.

*Who may credit a source with a person.* The candidate↔allocation link
(`candidates.allocation_id`, one additive nullable column) is accountability, not
a form field, so — exactly as M5 did with ownership — it **left the blind field
list** on the candidate save and travels one door, `rful_attach()`, asked about
the requirement the save is **producing**, not the one the candidate is leaving.
`rful_enforce_candidate()` re-reads the row after **every** write path (candidate
create, candidate edit, stage move) and removes any link that points at another
requirement's allocation, at a closed one, or at a source already credited to its
promise — so a link written by a raw statement or by a field list somebody adds
in future is undone, not merely refused at the door.

*What Phase 4 may never do.* It never decides whether somebody holds a position
and never removes anybody from one — that stays M6's. The worst a Phase 4 control
can do is drop a **credit** back to the direct path. Under contention it applies
the ratified capacity rule unchanged (invariant I21): **never overfill, never
displace an established holder**; where simultaneous claims cannot be resolved
without displacement the contested claims are refused and the capacity is left
for a later valid transaction. A source entity named on an allocation (a supplier
from `business_partners`, a marketplace requirement from `cx_requirements`, a
professional from `cx_professionals`, a person from `inspectors`) must exist **in
this workspace** — isolation is structural, one database per tenant, so a row
from elsewhere is simply absent and is refused rather than written — and a source
whose entity lives behind a module the workspace has not bought (marketplace,
professionals → `connect`) is refused outright, because hiding the option from a
dropdown is not a control.

*Vocabulary.* The source list **extends the existing configurable
`req_sourcing_model` lookup** already registered in Masters — no new master was
built. A workspace may add its own sources without a line of code; the shipped
values always stay valid so that narrowing the list never makes yesterday's
allocations unreadable.
