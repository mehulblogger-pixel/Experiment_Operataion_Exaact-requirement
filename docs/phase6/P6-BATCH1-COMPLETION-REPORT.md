# Phase 6 · Batch 1 — Completion report
## Identity write safety · authority · scope

*What changed, why, and what the evidence says. Batch 1 converges no identities.
It makes identity writing safe first, so that convergence — when it starts — is
building on ground that holds.*

**Contract:** `P6-FOUNDATIONAL-IMPLEMENTATION-PLAN.md` plus the owner's five
locked decisions of this gate.

---

## 1. In plain words

Five things were true of this system before this batch, and are no longer:

1. **Opening a list created people.** Seventeen ordinary screens, just by being
   read, could invent staff records and link logins to them.
2. **A record number was treated as permission.** The candidate screen would
   remove *any* identity link in the workspace if you posted its number.
3. **The same action asked for different purchases** depending on which screen
   you came from.
4. **Nothing asked which branch you belong to.**
5. **Duplicate protection lived in the code, not the database** — and two
   identical live links had already been created.

Two more were found during the work and fixed here, both approved by the owner:

6. **Linking a candidate silently blocked the inspector link**, refusing it with
   a message naming an inspector that did not exist — so the people this feature
   exists for, recruited *and* deployed, were exactly the ones who could not be
   linked.
7. **The audit trail could not be retrieved.** Entries were stored as untyped
   notes with a dangling reference, findable from nowhere.

---

## 2. Requirements and invariants

| Requirement | Status |
|---|---|
| **R22** read must not create identity | **Done** |
| **R24** record id is never authorisation | **Done** |
| **R25** entitlement consistency across ledger writers | **Done** |
| **R15** branch/scope enforcement | **Done as scoped** — per-end visibility; relationship-level scope stays open (Q5/Q11) |
| **R16** cross-tenant protection and proof | **Done** — and now *tested*, not argued |
| **R3** database-level uniqueness | **Done** (U1/U2/U3) |
| **R11** concurrency | **Done** |
| **R18** *registration only* | Minimum wiring so a refusal has somewhere to go. Reversal, coverage of the other mechanisms: **not done, not claimed** |

| Invariant | Before | After | Evidence |
|---|---|---|---|
| **I23** a read never creates identity | VIOLATED | **HOLDS** | `A1–A5`, mutant `M1` |
| **I25** a record id is never authorisation | VIOLATED | **HOLDS** | `B1–B5`, `M3` |
| **I26** one entitlement per mechanism | VIOLATED | **HOLDS** | `C1–C5`, `M5`, `M5b` |
| **I27** the action owns its permission | VIOLATED | **HOLDS** | `A9–A12`, `M1`(ref), `M2` |
| **I28** uniqueness at database level | VIOLATED | **HOLDS** | `F0–F8`, `M9`, `M17` |
| **I29** concurrent creation yields one relationship | VIOLATED | **HOLDS** | `G1–G7`, `M12`, `M13` |
| **I15** cross-tenant links impossible | NOT ESTABLISHED | **HOLDS** | `E0–E5`, `M18` |
| **I16** branch scope cannot be bypassed | VIOLATED | **PARTIAL** | `D1–D5`, `M6`, `M7`, `M8` |
| **I22 / I42** no orphan, no silent half-state | VIOLATED | **PARTIAL** | `I1–I4`, `M16` |
| **I41** a failed observation is not a failed transaction | PARTIAL | **PARTIAL** — holds for this ledger; cannot hold generally while R18 is open | `I41a–c` |
| **I6** identity changes are audited | VIOLATED | **PARTIAL** | `Y-A1–Y-F`, `M20`, `M20b` |

**I16, I22, I42, I6 and I41 remain PARTIAL on purpose.** Q5/Q11 are open, R20 is
deferred, and R18 is only registered. Claiming otherwise is the one thing this
gate forbids.

---

## 3. What changed, file by file

| File | Change |
|---|---|
| `lib/connect_identity.php` | Three live-key columns, back-fill, duplicate pre-check and three UNIQUE indexes in the existing migration. Axis-aware resolvers. All three writers gain: entitlement → permission → scope → state → ownership → duplicate → write → audit. Conflict translation. Attributable audit. One new two-line private helper |
| `lib/ops.php` | `link_inspector_users()` removed from `inspectors_list()`; kept as an explicit, self-authorising, transactional reconciliation. New `team_unlinked_logins()`. Two system-status rows. The People screen gains one action. `/candidate-link-pro`, `/candidate-unlink-pro`, `/candidate-link-person` corrected |
| `lib/access.php` | New `scope_sbu_allows()` — the SBU-only scalar twin of `scope_allows()`/`scope_office_allows()` |
| `lib/activity.php` | `IDENTITY_LINK` registered in `ACT_ENTITIES`; three identity kinds in `ACT_KINDS` |
| `lib/seed_scenario_s06.php` | Now uses the canonical writers (adversarial Finding A) |
| `views/ops/users.php` | The backlog, in plain words, with one button |
| `tools/seed-scenario-s0{1,2,6}.php` | Act as Master Admin, as the in-app buttons already did |
| `docs/02-permission-matrix.md` | Updated in the same change, per project governance |

**Engines reused, unchanged:** `connect_identity_admin_can()` · `connect_enabled()` ·
`licence_module_live()` · `scope_allows()` · `scope_office_allows()` · `act_log()` ·
`ensure_column()` · `db_epoch()` · `ops_require()` · `org_import_link_team()` ·
`system_status()`. Protocol mapped from `books_unique_number_index()`.

**New components: two.** A two-line private key helper in the ledger, and
`scope_sbu_allows()` in the file that owns scope. **No new engine, table,
permission, route or screen.**

---

## 4. Evidence

### Tests

| | |
|---|---|
| New battery | `tests/test_p6_batch1.php` — **115 assertions** |
| Written | **before** the implementation |
| Baseline against unmodified code | **35 passed · 39 failed** |
| Final | **115 passed · 0 failed**, both engines |
| Real-process workers | `_p6_worker.php`, `_p6_tenant_worker.php`, `_p6_legacy_worker.php` |

Every assertion reads business or database state. Return codes are never
accepted as evidence.

### Regression

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **12 382 passed · 0 failed** |
| **MariaDB 10.11.14** (authoritative, server restarted first) | **12 387 passed · 0 failed** |

Operations · Workforce · Recruitment · Marketplace · Reporting · Money ·
Dashboard/KPI all green, including the locked Phase 5 KPI battery.

### Mutation

**23 of 23 caught · no survivors · no FATALs · clean baseline.** A crashed suite
is reported as FATAL and is **not** counted as a catch. Detail, including the
first run's five survivors and what each one revealed, is in
`P6-BATCH1-MUTATION-RESULTS.md`.

### Security

Every attack in the owner's list was run: direct URL, forged POST,
wrong-candidate id, wrong branch, entitlement off, direct function bypass, direct
SQL, four-process race, two-tenant isolation, legacy duplicates, partial failure,
retry. All held. Detail in `P6-BATCH1-SECURITY-RESULTS.md`.

### Adversarial

One complete attack after implementation. **Two material defects found**, both
fixed with the smallest change and fully re-verified; **three residuals accepted
and recorded**. No correction treadmill. Detail in
`P6-BATCH1-ADVERSARIAL-AUDIT.md`.

---

## 5. Tests changed, and why that is not weakening

Seven existing test files were touched. **No assertion was weakened, removed or
skipped, and no mutant was allowed to live because of it.**

| File(s) | Change | Reason |
|---|---|---|
| `test_connect_identity`, `test_candidate_pro_link`, `test_connect_source`, `test_connect_deploy`, `test_import_team_link`, `test_s06_gap_showcase` | one line: `t_as_admin()` at the top, `t_as_nobody()` at the end | the actions they exercise now check authority **themselves**. With no session there is no actor, and the correct answer is "no". This is a fixture, not a relaxation |
| `test_recruit_onboarding_kit` | snapshot + restore the marketplace switch, and **assert the restore worked** | pre-existing pollution that leaked a switched-off workspace into every later test (adversarial Finding B) |

`tests/lib.php` gains `t_as_admin()` / `t_as_nobody()` — a fixture helper, in the
file that already owns test fixtures.

> **One prediction in the plan was wrong, and it is worth naming.** The plan said
> `test_import_team_link.php` would pass unmodified, reasoning that it calls
> `link_inspector_users()` directly rather than through `inspectors_list()`. That
> reasoning held — the read-path removal did not break it. What broke it was the
> authority guard the same plan specified two paragraphs earlier, which I failed
> to carry into the prediction. The test needed a session, nothing more.

---

## 6. Known limitations — stated, not minimised

| | |
|---|---|
| **I16 is PARTIAL.** Per-end visibility is enforced. Whether a relationship between a branch-scoped inspector and a tenant-global professional itself carries a branch is **Q5/Q11**, open. Mutant `M8` exists to stop anyone answering it quietly |
| **I22/I42 are PARTIAL.** Two racing reconciliations can no longer orphan a team member, but duplicate-inspector prevention in general is **R20**, deferred |
| **I6 is PARTIAL.** The identity ledger now audits attributably. `candidates.person_ref`, `candidates.inspector_id` and `users.inspector_id` still write no audit — **R18**, not in this batch |
| **R23 is untouched.** A marketplace search still creates taxonomy nodes. Excluded by the mandate; not fixed, not claimed |
| **Finding C.** The live keys are set by the writer, so a *future* writer could forget them. Guarded by `W1`, `F8` and `M17`; a generated-column hardening was measured on both engines and is recommended as a follow-up |
| **Finding D.** An unlink with no stated expectation performs no ownership check. Guarded by `W2` |
| **Finding E.** The scope helpers fail open if `access.php` were absent — the codebase's load-order idiom, recorded rather than changed |
| **No deployment claim.** Nothing here asserts a production deploy or a UAT on MilesWeb |

---

## 7. What this batch did NOT do

- **No identity convergence.** Nothing merged, retired or renamed.
- **No Person table or hub.** Q3/Q7/Q13 untouched.
- **Candidate, professional, inspector, employee and user remain distinct.**
- **Requisitions and `cx_requirements` remain distinct.**
- **Marketplace, Operations and Dashboard/KPI were not redesigned.**
- **Q1–Q18 remain unanswered.**
- **R1, R2, R4, R7, R19, R20, R21, R23, R26–R31 remain unimplemented.**
- **No locked M3/M4/M5/Phase 4/Phase 5 rule was changed.**

---

## 8. The one change to who can do what

A workspace entitled to Recruitment but **not** to Connect can no longer create,
change or remove a marketplace-professional identity relationship. Owner-approved
on the ground that such a workspace has no marketplace professional to link to,
so the capability was unusable and permitting the write was entitlement leakage.
`docs/02-permission-matrix.md` was updated in the same change, as governance
requires. **No new permission exists.** The coordinator/manager/master band is
unchanged.

---

## 9. Verdict

Every acceptance condition in the owner's gate is met, with the four PARTIALs
above stated rather than rounded up.

**PHASE 6 — BATCH 1: COMPLETE, PENDING OWNER REVIEW.**

Phase 6 is **not** complete. Batch 1 is the foundation it was scoped to be.
