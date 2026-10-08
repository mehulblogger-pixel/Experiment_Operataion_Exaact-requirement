# EXAACT · Phase 7 — Master Deferred Backlog Register

**Application baseline:** `cdb9eee` · branch `claude/testing-branch-setup-0gqe8n`
**Compiled:** 2026-10-02 · documentation only, no code inspected for change
**Supersedes:** the scattered "open" wording in the individual Phase 7 audits

---

## 0. What this document is, and the one thing it refuses to do

Across Phase 7 the same item has been written down several times, in several
documents, at several stages of its life. A finding raised in an audit, answered by
a decision, implemented in a gate and verified in a closure audit can still be
sitting in the original document under the word "open" — because that document was
correct when it was written and was never meant to be rewritten afterwards.

That is how a programme ends up unable to answer the only question that matters at
release time: **what is actually still outstanding?**

This register answers that question once. Every item below has been traced to its
current state in the repository, and classified as **RESOLVED**, **DEFERRED**,
**OPEN — OWNER DECISION / EXTERNAL VERIFICATION REQUIRED**, or **RELEASE
BLOCKER**.

**The refusal:** no item here was invented. Every row comes from an existing Phase
7 document, gate record, test or commit, and the Evidence column says which. Where
the repository could not settle a question, the row says so in those words rather
than guessing — a genuine business decision is never closed by inference.

**One correction this register makes to an earlier statement of mine.** A previous
summary said the gate register had no written record for Gate 0. That was wrong: a
Gate 0 record exists at `## 20b. Gate 0 — reachability, baseline and pre-pipeline
safety (executed)` in the Implementation Readiness Audit. The earlier search
required a `§` character that this heading does not use. The genuinely missing
records were Gate 6, Gate 6A, Gate 6C and Gate 6C-F1, and those are now written.

---

## 1. Summary — the only numbers that matter at release time

| Classification | Count | Meaning |
|---|---:|---|
| **Release blockers** | **4 open, 1 closed** | Must be closed before production release. The code artifact (**B-1**) is **CLOSED** at `0f2123d`; the four remaining are verification steps not yet performed. |
| **Genuinely deferred** | **6** | Knowingly not done. None blocks release. |
| **Open — owner decision / external verification** | **3** | Cannot be settled from repository evidence. |
| **Resolved** | **11** | Recorded so they are never again mistaken for current blockers. |

**Nothing in the deferred list blocks the release.** Nothing in the resolved list
is a current blocker. The release blockers are the whole of the remaining critical
path. **B-1 is now closed** (release identity `2026.10.1`, commit `0f2123d`); the
four that remain — B-2, B-3, B-4, B-5 — are all verification activities rather than
defects, and none can be performed from the development environment.

---

## 2. RELEASE BLOCKERS

These are production-blocking. They are deliberately **not** in the deferred list.

### B-1 · Stale release version identity

| Field | Value |
|---|---|
| **ID** | B-1 |
| **Origin** | Gate 6 Closure & Release-Readiness audit, 2026-10-02 |
| **Description** | `APP_VERSION = '2026.07.1'` and `APP_VERSION_DATE = '2026-07-27'` in `phpapp/lib/preflight.php`. The stamp was last changed in commit `b963490`; **697 commits** have landed since, including every gate from 1A through 6C-F1. `tools/release.sh` names the package from `APP_VERSION`, and the administrator deployment tool reports it. |
| **Current status** | **RESOLVED — CLOSED at `0f2123d`, 2026-10-02** |
| **Why not already done** | *(Historical: it was deliberately excluded from the documentation-closure task, which was forbidden from changing the version, and belonged to the controlled release-version preparation step.)* **Done in that step.** |
| **Impact** | Business and operational. A package built today would be named `exaact-2026.07.1` — indistinguishable from a build predating all of Phase 7's gate work. Neither you nor a customer could tell the two apart, and a rollback decision would have nothing to anchor on. |
| **Dependency** | None. It is a two-constant change plus `php tools/make_deploy_check.php`. |
| **Priority** | **Critical** |
| **Production blocking?** | **Yes** |
| **Intended phase/gate** | Release Version Preparation — **completed** |
| **Evidence** | `phpapp/lib/preflight.php:25-26`; `tools/release.sh` (`VER=$(php -r 'require "lib/preflight.php"; echo APP_VERSION;')`); `git rev-list --count b963490..cdb9eee` = 697 |
| **Notes** | **Resolved at `0f2123d`.** `APP_VERSION = '2026.10.1'`, `APP_VERSION_DATE = '2026-10-02'`, with the checksum manifest regenerated in the same commit (677 files). Package identity now resolves to `exaact-2026.10.1`. Two files, four lines; no behaviour changed and no test required updating, because `APP_VERSION` is never compared or branched on anywhere in the application. The `2026.07.1` values above are retained as the historical record of what the blocker was. |

### B-2 · Production backup and restore not verified

| Field | Value |
|---|---|
| **ID** | B-2 |
| **Origin** | Release-readiness requirement; `P7-REVENUE-READINESS-PRODUCTION-PROOF.md` §H.1 |
| **Description** | No production backup has been taken, restored, or proven readable from this environment. The capability exists and is unit-tested (`backup_create`, `backup_list`, `backup_restore`, `backup_export`, `backup_import_json`, `backup_auto_daily`; `test_backup.php` 16 assertions passing), but capability is not evidence. |
| **Current status** | **RELEASE BLOCKER — not started** |
| **Why not already done** | This environment has no production access or credentials, by standing instruction. |
| **Impact** | Operational, and the most serious kind. A backup that has never been restored is not a backup. Releasing without it means a failed deployment has no proven recovery path. |
| **Dependency** | Production access, credentials, and an isolated scratch database to restore into. |
| **Priority** | **Critical** |
| **Production blocking?** | **Yes** |
| **Intended phase/gate** | Production Backup Verification → Production Restore Verification |
| **Evidence** | `phpapp/lib/backup.php`; `phpapp/tests/test_backup.php` (16 passed / 0 failed) |
| **Notes** | Must be proven on money-bearing tables **and** people/workforce tables, against expected row counts, without affecting production. The automatic daily job must be confirmed as actually running, not merely present. |

### B-3 · Production environment not verified

| Field | Value |
|---|---|
| **ID** | B-3 |
| **Origin** | Release-readiness requirement; UAT playbook §0 stated limitation |
| **Description** | Nothing about the live `operations.mghaiapps.com` environment has been verified from here: version and build identity, checksum integrity across all 677 deployable files, PHP version and extensions, MariaDB reachability and version, path writability, licence/entitlement state, cloud-mode and base-domain settings, and control-database separation from tenant databases. |
| **Current status** | **RELEASE BLOCKER — not started** |
| **Why not already done** | No production access from this environment, by standing instruction. |
| **Impact** | Technical and operational. Every test figure in this programme is development evidence; none of it says the live environment can run the release. |
| **Dependency** | Production access. |
| **Priority** | **Critical** |
| **Production blocking?** | **Yes** |
| **Intended phase/gate** | Production Environment Verification |
| **Evidence** | `docs/phase7/EXAACT-BUSINESS-UAT-END-TO-END-PLAYBOOK.md` §0 ("cannot reach `https://operations.mghaiapps.com` over the internet"); `phpapp/deploy-check.php` (generated, 677 files) |
| **Notes** | Read-only verification. The deploy-check tool is a browser tool for an administrator; it needs no shell on the server. |

### B-4 · Deployment, post-release checksum verification and smoke test not performed

| Field | Value |
|---|---|
| **ID** | B-4 |
| **Origin** | Release-readiness requirement; `P7-REVENUE-READINESS-PRODUCTION-PROOF.md` §H.1 |
| **Description** | No production deployment has occurred, so no post-upload checksum verification and no production smoke test exist. `tools/smoke.js` provides a route sweep usable as the mechanical half of a smoke test. |
| **Current status** | **RELEASE BLOCKER — not started** |
| **Why not already done** | Deployment is explicitly out of scope until the preceding steps pass. |
| **Impact** | Operational. An upload that silently truncated or omitted a file is exactly what the checksum manifest exists to catch. |
| **Dependency** | B-1, B-2, B-3 and Business UAT sign-off. |
| **Priority** | **Critical** |
| **Production blocking?** | **Yes** |
| **Intended phase/gate** | Release Packaging → Production Deployment → Post-Release Checksum Verification → Production Smoke Test |
| **Evidence** | `phpapp/tools/smoke.js` (214 lines, route sweep); `phpapp/tools/release.sh`; `phpapp/deploy-check.php` |
| **Notes** | The smoke test is reads only — no document issued, no money moved. `release.sh` refuses to build from a dirty working tree, which is the behaviour we want. |

### B-5 · Business UAT not executed or signed off

| Field | Value |
|---|---|
| **ID** | B-5 |
| **Origin** | `docs/phase7/EXAACT-BUSINESS-UAT-END-TO-END-PLAYBOOK.md` |
| **Description** | The playbook exists and is comprehensive — 111 sections, eight journeys (A Recruitment, B TPIA order-to-cash, C Marketplace, D Money & billing, E Dashboards, F Search, G Inspector's phone, H Negative & security), plus safety rules and a "do not press" list. It has not been executed against the live site, and until this task it did not cover the two Gate 6B screens. |
| **Current status** | **RELEASE BLOCKER — not started** (playbook ready; two checks added 2026-10-02) |
| **Why not already done** | Requires you, on the live site. It is a business acceptance activity, not a development one. |
| **Impact** | Business. The gates prove the rules behave correctly; UAT proves the product does what you actually want. |
| **Dependency** | B-3 (so you are testing a verified environment), and the playbook's own §0 reconciliation step. |
| **Priority** | **Critical** |
| **Production blocking?** | **Yes** |
| **Intended phase/gate** | Business UAT |
| **Evidence** | Playbook §0–§22; new checks **UAT-G6B-01** and **UAT-G6B-02** (§23) |
| **Notes** | The playbook's one stated limitation stands: it was written without reaching production, so its §0 reconciliation step must be done first and must not be skipped. |

---

## 3. GENUINELY DEFERRED — knowingly not done, none release-blocking

### D-01 · A-F7 — reports and pickers that still list everyone

| Field | Value |
|---|---|
| **ID** | D-01 |
| **Origin** | `GATE6-UX-AUDIT.md` finding **A-F7 · P2** |
| **Description** | Several lists still offer every team member where a narrower list might be right: the recruitment-form pickers and the MIS person filter. Gate 6B's R2 closed only the per-person utilisation breakdown. |
| **Current status** | **DEFERRED — open by instruction** |
| **Why deferred** | Explicit owner instruction during Gate 6 ("DO NOT CHANGE THIS YET"), reaffirmed in Gate 6B's "Do NOT change" list and verified intact by the Gate 6C audit. The sibling questions were separated from R2 deliberately because each list answers a different business question. |
| **Impact** | Low, and arguably none. Including somebody who starts next week in a *picker* is usually correct — it is how their login and paperwork exist before day one. Only a report that sums capacity was wrong, and that is fixed. |
| **Dependency** | An owner decision per list: which of these lists should narrow, and to what. |
| **Priority** | **Low** |
| **Production blocking?** | **No** |
| **Intended phase/gate** | A later UX/reporting gate, after release |
| **Evidence** | `GATE6-UX-AUDIT.md` A-F7; §20i; Gate 6C scope verification (`inspOpts` still unfiltered, by design) |
| **Notes** | R2 must not be widened into these paths without that decision. Gate 6C's browser mutation **G6B-B6** exists specifically to catch the exclusion leaking into the MIS filter. |

### D-02 · A-F4 — aggregated "Next Action" view

| Field | Value |
|---|---|
| **ID** | D-02 |
| **Origin** | `GATE6-UX-AUDIT.md` finding **A-F4 · P1** |
| **Description** | "Next Action" guidance appears on individual record pages but there is no single aggregated place that lists everything awaiting the signed-in user. |
| **Current status** | **DEFERRED — scoped out by instruction** |
| **Why deferred** | Gate 6's authorisation said explicitly: no aggregated follow-up system. The need was partly served instead by the availability board's "hired but not yet joined" message and the team register's status filter and split headline. |
| **Impact** | Medium on convenience, none on correctness. Nothing is unreachable; it takes more clicks to find. |
| **Dependency** | A design decision on what the aggregate should contain and whose work it shows. |
| **Priority** | **Medium** |
| **Production blocking?** | **No** |
| **Intended phase/gate** | A later UX gate |
| **Evidence** | `GATE6-UX-AUDIT.md` A-F4; Gate 6 D2/D3/D4 implementation at `7cce780` |
| **Notes** | Recorded as scoped out rather than closed, so it is not mistaken for delivered. |

### D-03 · Person Hub / identity convergence

| Field | Value |
|---|---|
| **ID** | D-03 |
| **Origin** | Phase 6 closure (`PHASE-B-CLOSURE-AUDIT.md` and the Phase 6 deferral table) |
| **Description** | A single "Person Hub" converging the several identity records a human can hold across the product. |
| **Current status** | **DEFERRED — programme decision** |
| **Why deferred** | Recorded verbatim in the Phase 6 register as "Deferred by programme decision; no customer impact today." |
| **Impact** | Technical/architectural. No customer-visible impact today; the identity link paths that matter operationally were closed separately (`connect_identity.php`, and the R20 duplicate work). |
| **Dependency** | A programme decision to reopen it, and a scope. |
| **Priority** | **Low** |
| **Production blocking?** | **No** |
| **Intended phase/gate** | A future architecture phase |
| **Evidence** | Phase 6 deferral table row: "Person Hub / identity convergence \| Deferred by programme decision; no customer impact today" |
| **Notes** | Not to be confused with the identity-link defect found and fixed during Gate 5, which was a real MariaDB data-integrity bug and is closed (§20h.8a). |

### D-04 · Secondary button touch targets at 36px

| Field | Value |
|---|---|
| **ID** | D-04 |
| **Origin** | UX-B audit series (`UX-B7-AUDIT.md`, `UX-B9-AUDIT.md`) |
| **Description** | `.btn` and `.btn.small` chips render at 36px, below the 44px touch-target guidance that the blueprint applies to primary controls. |
| **Current status** | **DEFERRED — deliberately, twice** |
| **Why deferred** | "Deferred to B7/B10 as instructed" and "Deferred to B7/B10; not reopened." The primary controls that must be reachable on a phone were raised to 44px, including the mobile menu close control in Gate 6 D5. |
| **Impact** | Low accessibility/usability on phones, on secondary chips only. |
| **Dependency** | A UX decision on whether every chip should grow, or only those on phone-first screens. |
| **Priority** | **Low** |
| **Production blocking?** | **No** |
| **Intended phase/gate** | A later UX gate |
| **Evidence** | UX-B audit rows; `assets/css/app.css` `.side-close` at 44px (Gate 6 D5) |
| **Notes** | Inspector screens are phone-first and were treated first, which is the right order. |

### D-05 · No SQLite busy timeout configured

| Field | Value |
|---|---|
| **ID** | D-05 |
| **Origin** | `RB3-EMPLOYEE-NUMBER-EVIDENCE.md` finding **F1** |
| **Description** | Two simultaneous SQLite writers mean one is refused outright with "database is locked", because no busy timeout is configured. |
| **Current status** | **DEFERRED — pre-existing, and not a production risk** |
| **Why deferred** | Recorded as "Pre-existing, and production is MariaDB. Changing it affects every write path in the application and belongs in its own change, not inside RB-3." |
| **Impact** | Technical, development and demo environments only. Production runs MariaDB, which does not have this behaviour. |
| **Dependency** | A decision to touch every write path, which needs its own change and its own regression. |
| **Priority** | **Low** |
| **Production blocking?** | **No** |
| **Intended phase/gate** | Its own change, post-release |
| **Evidence** | `RB3-EMPLOYEE-NUMBER-EVIDENCE.md` F1; `phpapp/lib/db.php` (the busy-timeout comment) |
| **Notes** | Worth revisiting only if SQLite ever becomes a supported production engine. |

### D-06 · Demo unload clears an approver mapping by employee number

| Field | Value |
|---|---|
| **ID** | D-06 |
| **Origin** | Residue of `RB3-EMPLOYEE-NUMBER-EVIDENCE.md` finding **F2** |
| **Description** | `lib/seed_demo_c.php:1717` deletes from `idems_approver_map` by a subselect on `emp_code IN ('EMP01','EMP02','EMP03','EMP04','SC-001')`. |
| **Current status** | **DEFERRED — minor residue; the data-loss half of F2 is RESOLVED (see R-08)** |
| **Why deferred** | The serious case — deleting **people** by employee number — was fixed: the demo now records the ids it genuinely inserted and the unload deletes by those ids, leaving reused rows alone. What remains is a mapping row, not a person. |
| **Impact** | Operational and narrow. Unloading the demo in a workspace whose real staff hold `EMP01`–`EMP04` or `SC-001` could clear that person's approver-mapping row. It is recoverable by re-adding the mapping; no person, job, invoice or money record is lost. |
| **Dependency** | Same treatment as the fixed path: record the ids the demo inserted, delete by id. |
| **Priority** | **Medium** |
| **Production blocking?** | **No** — but see Notes |
| **Intended phase/gate** | A small follow-up change, post-release |
| **Evidence** | `phpapp/lib/seed_demo_c.php:1717`; the fix pattern in `phpapp/lib/seed_demo.php:1-16` and `:748`, `:768` |
| **Notes** | **Operational guard until fixed:** do not run a demo unload in a production workspace that has real staff holding those employee numbers. This is a one-line instruction, not a release blocker, because the demo unload is an administrator action nobody performs accidentally. |

---

## 4. OPEN — OWNER DECISION / EXTERNAL VERIFICATION REQUIRED

These could not be settled from repository evidence. They are **not** guessed.

### O-01 · Should a requisition read "filled" when the offer is accepted?

| Field | Value |
|---|---|
| **ID** | O-01 |
| **Origin** | `RECRUITMENT-BUSINESS-DECISION-PACK.md` — "**OWNER DECISION:** should a requisition be 'filled' when the offer is accepted" |
| **Description** | Whether acceptance of an offer, or the recorded joining, is the moment a vacancy stops being a vacancy. |
| **Current status** | **OPEN — OWNER DECISION REQUIRED** (probably already answered in substance) |
| **Why open** | Gate 5 settled the *workforce* half decisively: acceptance creates the person in a not-yet-joined state, and only joining makes them operationally active. RB-2 ("a requirement reports filled when nobody has joined") was closed on that basis. But the question as originally written was never formally retired in the decision pack, so the register will not record it as closed by inference. |
| **Impact** | Business reporting. If the answer is "joining", the behaviour already matches. If it is "acceptance", a vacancy count somewhere may need revisiting. |
| **Dependency** | One sentence from you. |
| **Priority** | **Medium** |
| **Production blocking?** | **No** |
| **Intended phase/gate** | Formal retirement during release preparation, or a later reporting gate if the answer changes anything |
| **Evidence** | `RECRUITMENT-BUSINESS-DECISION-PACK.md`; §20h (Gate 5); `REVENUE-BLOCKERS.md` RB-2 |
| **Notes** | Expected outcome: confirm "joining", and mark the question retired as already satisfied. |

### O-02 · Should an un-convertible candidate be blocked from ACCEPTED entirely?

| Field | Value |
|---|---|
| **ID** | O-02 |
| **Origin** | `RECRUITMENT-BUSINESS-DECISION-PACK.md` — "**OWNER DECISION:** should an un-convertible candidate be blocked from ACCEPTED entirely?" |
| **Description** | Where a candidate cannot be converted into a team member (missing data, a duplicate employee number, an unresolvable branch), should the product refuse to mark them ACCEPTED, or accept them and report the problem? |
| **Current status** | **OPEN — OWNER DECISION REQUIRED** |
| **Why open** | A policy question, not a technical one. Both answers are implementable and neither is obviously right: refusing protects data, accepting protects the recruiter's workflow. |
| **Impact** | Business process. Affects what a recruiter can do when a hire's paperwork is incomplete. |
| **Dependency** | Your decision. |
| **Priority** | **Medium** |
| **Production blocking?** | **No** — today's behaviour is defined and tested, whichever way you later decide |
| **Intended phase/gate** | A later recruitment gate if the answer changes current behaviour |
| **Evidence** | `RECRUITMENT-BUSINESS-DECISION-PACK.md`; `rcv_convert()` in `phpapp/lib/recruit.php` and its refusal reporting |
| **Notes** | Gate 5 made the conversion itself safe and auditable, which lowers the urgency considerably. |

### O-03 · Stale local `phpapp/tenants.php` artifact

| Field | Value |
|---|---|
| **ID** | O-03 |
| **Origin** | Gate 6C audit; Gate 6C-F1 correction |
| **Description** | The original working copy still holds a git-ignored `phpapp/tenants.php` left by the defective pre-F1 test runs. Its presence makes five workspace-signup assertions fail **in that copy only**, because it records a base domain and so makes cloud mode read as already on. |
| **Current status** | **OPEN — EXTERNAL ACTION REQUIRED** (housekeeping, not a defect) |
| **Why open** | The environment's safety control refused the deletion, and the instruction was not to work around that refusal. |
| **Impact** | Development hygiene only. Not a product defect and not a production artifact. |
| **Dependency** | Someone with local file permissions deleting the file once. |
| **Priority** | **Low** |
| **Production blocking?** | **No** |
| **Intended phase/gate** | Housekeeping, any time |
| **Evidence** | Gate 6C §8; Gate 6C-F1 clean-worktree runs — SQLite 16206/0 and MariaDB 16205/0 with the file absent and never regenerated |
| **Notes** | Proven not to recur: the corrected battery does not create it, on either engine, in the full suite or the protected suites. A fresh clone or CI checkout is unaffected. |

---

## 5. RESOLVED — recorded so they are never again read as current blockers

| ID | Item | Origin | Resolved by | Evidence |
|---|---|---|---|---|
| R-01 | **§20 F1** — workforce record created at Accepted conflicts with locked rule C37 | Implementation Readiness Audit §20 | **Gate 5.** §20a prescribed "create it in a not-yet-joined status at Accepted; promote it to ACTIVE when the joining is recorded, audited on both sides" — exactly what Gate 5 built. | §20a; §20h; `0215b85` |
| R-02 | **§20 F2** — does the master exception to segregation survive? | Implementation Readiness Audit §20 | **Gate 4.** `appr_guard()` gained the generic requester comparison for every approval-capable entity, through the existing engine rather than a second security layer. | §20a; §20g; `b1e793c` |
| R-03 | **§20 F3** — `role_defaults_base('ADMIN')` returns every permission (OPEN-4) | Implementation Readiness Audit §20 | **Finding withdrawn** in §20a after reconciliation. Not a blocker and never was. | §20a "F3 — Administrator permissions: **finding withdrawn**" |
| R-04 | **§20 F7** — route collision on the candidate stage route | Implementation Readiness Audit; Gate 0 | **Gate 0.** The duplicate dispatcher was corrected and the route renamed `candidate-pipestage`, chosen so it does not even share a prefix with `/candidate-stage`. | §20b; `42c6550`; `test_gate0_stage_route.php` |
| R-05 | **RB-1** — a hired person may never exist in Workforce | `REVENUE-BLOCKERS.md` | **Gate 5.** `rcv_convert()` creates the team member at acceptance in `PENDING_JOINING`. | §20h; `REVENUE-BLOCKERS.md`; `0215b85` |
| R-06 | **RB-2** — a requirement reports "filled" when nobody has joined | `REVENUE-BLOCKERS.md` | **Gate 5.** Joining is the single activation boundary; acceptance no longer makes anybody operationally active. | §20h; `0215b85`, `135f6d1` |
| R-07 | **RB-3** — duplicate inspectors freely creatable, nothing reports them | `REVENUE-BLOCKERS.md` | **R20 closure plus the RB-3 steps.** | `R20-INSPECTOR-DUPLICATE-CLOSURE.md`; `RB3-STEP2-COMPLETION.md`; `RB3-STEP3-COMPLETION.md` |
| R-08 | **RB-3 F2** — demo unload could delete real people by employee number | `RB3-EMPLOYEE-NUMBER-EVIDENCE.md` | **Fixed.** The seed records the ids it genuinely inserted, in the existing settings table, and the unload deletes by those ids; rows the seed merely reused are never recorded and are left alone. Residue D-06 covers one mapping table. | `phpapp/lib/seed_demo.php:1-16`, `:748`, `:768` |
| R-09 | **A-F1** — a joining-pending person could still be assigned a job (P0) | `GATE6-UX-AUDIT.md` | **Gate 6.** The assignment dropdown now asks `wf_active_sql()` instead of carrying its own negative copy of the rule. | `378b678`; `test_gate6_assignment_boundary.php` |
| R-10 | **A-F2, A-F3, A-F5, A-F6** — absence unexplained; joiners unlistable; joiners counted as workforce; mobile close target | `GATE6-UX-AUDIT.md` | **Gate 6 D2–D5.** Board message, register status filter, split headline, 44px close control. | `7cce780`; `test_gate6_d2_availability.php` |
| R-11 | **Gate 6C F-1** — the Gate 6B battery mutated process-wide tenant state | Gate 6C audit | **Gate 6C-F1 correction** at `cdb9eee`. Replaced with local settings-cache invalidation; no application code changed; full regression restored to zero failures on both engines. | §20m; `cdb9eee` |
| R-12 | **Master lists cannot be found** — the Master Admin searched for office expense heads, public holidays and back-office staff and reported "nothing such found"; all three exist | Business UAT 1.3.8 / 1.3.9 / 1.3.11 | **CLOSED 2026-10-04.** `/masters` carries **194 cards** across three layers, a dozen groups and a collapsed section — scanning was never going to work. One search box now filters every card as you type, opens the collapsed section when the match is inside it, quiets group tallies that would contradict the filtered view, and steps the explainer panel aside. Verified in a browser at 390px: "holiday" → Public holidays; "expense" → Office expense heads + voucher columns; "back-office" → Back-office staff — the three the owner reported as missing | UAT guide §1.3 |
| R-13 | **Retire / reactivate has no control where it is needed** — the screen says "reactivate", there is no "unlock", and no control sits where a user is retired | Business UAT 1.4.7 | **CLOSED 2026-10-04.** The actions all existed — deactivate, reactivate, remove the sign-in, reset two-step — but only as buttons on a row of the `/users` list. Opening somebody's record to decide about them offered no way to act on what you had just read. `/user-edit` now carries an **Account status** panel with the same endpoints, wording and confirmations. It adds no permission: `/user-retire` already refuses deactivating your own account and removing the last Master Admin, and a browser run confirmed it still does. Round-trip verified — Can sign in / Deactivate → Cannot sign in / Reactivate → back again | UAT guide §1.4.7 |
| R-14 | **Recruitment is named three ways** — module toggle "Hiring / candidates" (filed under the *Operations* heading on `/access`), permission group "Recruitment", playbook "recruitment administrator" | Business UAT 1.5.3 | **CLOSED 2026-10-04.** Recruitment now has its own heading in `module_groups()` and is labelled "Recruitment" in `ACCESS_MODULES`, matching its permission group. Grouping and labels only — grants nothing, removes nothing | `lib/access.php` `module_groups()`, `ACCESS_MODULES` |
| R-15 | **Masters UI** — must scroll to the bottom to add a record; the page jumps back to the top after every edit; no search box on long lists; module pickers are single-select where multiple is needed; not usable on a phone | Business UAT 1.3.1 / 1.3.2 / 1.3.3 / 1.3.4 | **PARTLY CLOSED 2026-10-04.** Done: lookup lists gained a "+ Add a value" button at the top (the add form sits below the table, so adding to a 40-value list meant scrolling past all 40) and find-as-you-type over their values; `/m/<list>` already had both. **Offices tab closed 2026-10-04:** every inline office action redirects with `#office-<id>`, the rows carry that id, and the page lands on the row and marks it for a moment. The shared tab-and-hash handler in `app.js` only resolves a bare element id inside a tabbed panel, and this list is not one, so the fragment alone was not enough — the gap is closed for the office tree. Verified in a browser at 390px: before, an action on the furthest-down row returned to scroll position 0; after, 5061 of a 5857px page with the row centred. **Lookup screen closed 2026-10-04:** the two configuration panels ("Appears on these forms" and the module picker) sat above the values, so opening a forty-value list showed settings first; they now sit below the values and the add form under a "List settings — set once, rarely changed" heading, as the tester asked. **R-15 is closed.** The single-select module picker is carried forward as R-18, and a systematic mobile pass as R-19 — both are separate pieces of work, not leftovers of this one | UAT guide §1.3 |
| R-16 | **Company identity is entered twice** — Branding & Theme under Settings sets the name in the application header; Settings → Your company sets the name on documents. Two fields, one fact | Business UAT 1.2.1 | **CLOSED 2026-10-04.** Not a duplicate — three fields doing three jobs, and one of them was mislabelled. `app_name` (Settings › Branding) is what the software calls itself; `company_name`, written by the **Brand / trading name** field on Your company, is what the business is called on invoices, the GST export and the privacy notice; `company_legal_name` is the registered name. The Brand field was labelled "shown in the app", which is only true while Application name is blank — so both screens appeared to claim the same job. Each field now states what it is for, which one is in force right now, and where the other lives | `/settings` |
| R-17 | **No first-run onboarding** — a new workspace gives no guided path through company settings, masters, users and approvals | Business UAT 1.2.6 | **CLOSED 2026-10-04.** `setup_journey()` (`lib/setup.php`) widens the `org_start_here()` pattern to the whole workspace: ten ordered steps — company identity, currency and financial year, offices, designations, departments, logins, home offices, roles, e-mail, and approvals where recruitment is on. Each carries an honest done-signal read from real data, the reason it matters, and where it stands; the panel shows on the home page for administrators, marks the next step, dims the finished ones and collapses to one line when all are done. It grants nothing — every link goes to a screen that already requires administrator level. `tests/test_setup_journey.php` (129 assertions) proves each step is well formed, that the progress count is counted rather than asserted, and that a done-signal moves when the data moves. Verified in a browser at 390px and 1280px: no overflow, no page errors | `lib/orgadmin.php` `org_start_here()` |
| R-18 | **A master list belongs to one module only** — the picker on `/lookup?key=…` is single-select, so a list used by two modules has to pick one | Business UAT 1.3.2 | **OPEN.** Not a UI fix: `lookup_types.module` is a single `VARCHAR(30)` and `lk_types_grouped()` / `lk_group_enabled()` read it as one value. Making it many changes the column's meaning and the grouping logic, so it wants its own gate rather than being folded into a labelling pass | `lib/lookups.php` |
| R-19 | **Systematic mobile pass** — individual screens have been corrected as they were found, but no screen-by-screen audit at 390px has been done | Business UAT 1.3.2 / Journey G | **OPEN.** Journey G in the playbook is the test for this and has not been run. Worth doing after the journeys, so the audit is driven by what actually hurt | `docs/05-ui-ux-blueprint.md` |
| R-20 | **The permission model is two verbs wide** — every module carries only `view` and `edit`; the owner wants view / add / edit / delete / activate / deactivate per module, sub-module and sub-sub-module | Business UAT 1.5.2 / 1.5.4 / A1, raised four times | **OPEN — OWNER DECISION REQUIRED, then its own gate.** Measured today: 39 fine-grained action rights + 31 modules × 2 verbs = **101 distinct rights**, asked at **763 `can()` call sites** across 98 distinct keys. Six verbs over the same 31 modules is 186 before sub-modules; with sub-modules realistically 300–500. That is not a settings change — it needs an audit of every screen and action, a decision on how sub-modules are expressed, a migration so existing saved permission sets neither lose nor gain access silently, a rewrite of `docs/02-permission-matrix.md`, and a different UI, because a 400-tick wall is worse than the 101-tick wall the owner already finds hard. **Step 1 is the audit, which is also what the owner asked for in their own words: "check what already exists and what is not there"** | `lib/access.php` `PERMISSIONS`, `ACCESS_MODULES` |
| R-21 | **Back does not go back** — from Masters → Offices, Back lands on Organisation & people, not Masters. The tester reports this across several screens: "it does not take back to the screen from where we clicked but to the previous function under that domain" | Business UAT 1.3.1 | **OPEN.** A `redirect_back()` helper already exists and follows the referrer; the Back *links* on these screens are hard-coded to a parent instead. Mechanical to fix once the screens are enumerated, and separate from R-15 | `lib/helpers.php` `redirect_back()` |
| R-22 | **The Masters screen shows different content depending on how you arrive** — "one when we login → Admin → Masters, another when we are in a master list and click back" | Business UAT 1.3.6 | **OPEN — needs reproduction.** Two candidate causes: the Back link lands on a different route (see R-21), or the card list is filtered by licence/module state that differs by entry path. Not yet diagnosed | `views/ops/masters.php` |
| R-23 | **Recruitment intake asks for nothing and the requisition asks for everything** — a hiring request carries no minimum qualification, skills or job description; those are only asked on the requisition, and only behind its Edit button. Business-unit and department pickers do not fetch their lists | Business UAT A2 / A6 / A7 | **OPEN.** Three related findings in the recruitment intake flow, all reported in one run. A6 is a design question (where the requirement is captured), A2 and A7 look like unfilled dropdowns — a defect | `lib/hiringreq.php`, `views/ops/requisition_form.php` |

---

## 6. How this register was reconciled

Every Phase 7 source named in the closure brief was inspected: the Phase 6
deferral table, the Recruitment Universalisation Audit, the Business Decision Pack,
the Business Rules Specification, the Business Decision Closure Pack, the
Implementation Readiness Audit (including §20, §20a, §20b and §20c–§20m), the Gate 6
UX Audit, the RB-3 evidence documents, the revenue-blocker documents, and the UAT
and release-readiness documents.

Where a document still said "open", the current repository state decided the row —
function bodies compared across commits, tests executed, and routes driven in a
browser during Gates 6C and 6C-F1. Where the repository could not decide, the row
says **OPEN — OWNER DECISION / EXTERNAL VERIFICATION REQUIRED** and explains what
is missing.

**No duplicates:** each item appears exactly once, in exactly one classification.
Items that appeared under several names across documents were merged, and the merge
is visible in the Origin column.
