# Programme Phase Status

*The single place that records which phases are locked and which have not begun.
Updated at each phase lock.*

**Last updated:** 2026-09-18 · commit `c63fa88` · branch
`claude/testing-branch-setup-0gqe8n`

---

| Phase | Status | Evidence |
|---|---|---|
| **Phase 0** | **ACCEPTED / LOCKED** | `docs/phase0/` |
| **Phase 1** | **ACCEPTED / LOCKED** | `docs/PHASE1-COMPLETION-REPORT.md` · `docs/phase1/` |
| **Phase 2** | **ACCEPTED / LOCKED** | `docs/phase2/` |
| **Phase 3** | **ACCEPTED / LOCKED** | `docs/phase3/` |
| **Phase 4** | **ACCEPTED / LOCKED** | `docs/phase4/P4-COMPLETION-REPORT.md` |
| **Phase 5** | **ACCEPTED / LOCKED** | `docs/phase5/P5-COMPLETION-REPORT.md` |
| **Phase 6** | **ACTIVE — Batch 1 ACCEPTED / LOCKED; Batch 2 not started** | 2026-09-19 |

## Phase 6 — where it stands

| Stage | Status |
|---|---|
| §2 Pre-implementation audit · §2 Person addendum | ACCEPTED / LOCKED |
| §3 Canonical domain model (+ C1/C2 corrections) | ACCEPTED / LOCKED |
| §20 Duplicate & identity rules (+ organisation correction) | ACCEPTED / LOCKED |
| §26 Action-path matrix · R26 external-account addendum | ACCEPTED / LOCKED |
| §27 Business invariants | ACCEPTED / LOCKED, **updated with Batch 1 evidence** |
| Foundational implementation plan | ACCEPTED as the Batch 1 contract |
| **Batch 1 — identity write safety, authority, scope** | **ACCEPTED / LOCKED** — 2026-09-19 · `db02289`, `03d805b` |
| **Batch 2** | **NOT STARTED.** Not to be begun without an explicit owner instruction |

**Batch 1 delivered:** R22 · R24 · R25 · R15 (scoped) · R16 · R3 · R11, plus the
two defects the audit uncovered and the owner approved — the cross-axis resolver
and the unattributable audit trail.

**Batch 1 evidence:** 115 new assertions (baseline against unmodified code:
35 passed / 39 failed) · full regression **12 382 passed / 0 failed on SQLite**
and **12 387 passed / 0 failed on MariaDB** · **mutation 23 of 23 caught**, no
survivors, no FATALs · one adversarial pass, two material defects found and
fixed, three residuals recorded.

**Deliberately still open after Batch 1:** Q1–Q18 unanswered · R20 deferred
(so I22/I42 are PARTIAL) · R18 registered only (so I6/I41 are PARTIAL) ·
Q5/Q11 open (so I16 is PARTIAL) · R23 untouched (so I24 is VIOLATED) ·
**no identity convergence, no Person hub, nothing merged.**

### Locked on Batch 1 acceptance

Not to be changed without a new owner instruction:

- The **PARTIAL** statuses stand: **I16** (Q5/Q11 open) · **I22**, **I42**
  (R20 deferred) · **I6**, **I41** (R18 registered only).
- **Q1–Q18 remain open.** **R20 deferred. R23 untouched.**
- **No further Batch 1 changes.**
- **Database-computed uniqueness keys** are recorded as a **future architecture
  consideration, not a current defect**, and are not to be implemented now
  (`P6-BATCH1-COMPLETION-REPORT.md` §10).

### Candidate scope for Batch 2 — noted, NOT scoped, NOT started

The owner has indicated the natural next area is **person-relationship
integrity and convergence**, which Batch 1's foundation now makes safer to
attempt. Known candidates, recorded so the next instruction has a starting
point — **none of this is designed, planned or authorised**:

- candidate → inspector conversion, made transactional (**R2**)
- duplicate protection around inspector creation (**R20**)
- the remaining identity mechanisms and `candidates.person_ref` (**R18**, **R21**)
- identity history and reversal
- candidate / inspector / professional relationship consistency

**Phase 6 is NOT complete.** Batch 1 is the foundation it was scoped to be.

---

## Phase 5 — headline evidence

| | |
|---|---|
| Phase 5 battery | **145 assertions, 0 failed** — SQLite and MariaDB |
| Full regression | **SQLite 12,252 passed, 0 failed** · **MariaDB 12,255 passed, 0 failed** |
| Mutation battery | **39 of 39 caught** — clean baseline, 0 survivors, 0 unapplied mutants |
| Authoritative engine | **MariaDB 10.11.14** for production-oriented evidence |
| Product change | 12 files · 1,007 insertions · 32 deletions |

**No production deployment and no MilesWeb UAT is claimed** for any phase in this
table. These are development-branch acceptances based on test, mutation,
reconciliation, security and adversarial evidence. Deployment is a separate
exercise with its own evidence.

---

## What each phase owns

Recorded here so that no later phase quietly becomes a replacement for an earlier
one.

| Owner | Owns |
|---|---|
| **M3** | The meaning of "filled" · approval / SLA engine foundations · stage and approval behaviour already locked |
| **M4** | The approval ceiling · the re-approval boundary · the hiring-request execution boundary |
| **M5** | Recruiter assignment and accountability · historical recruiter ownership and credit |
| **Phase 4** | Multi-source allocation and source-promise behaviour |
| **Phase 5** | KPI measurement · reconciliation · ageing · performance measurement · historical measurement · the dashboard presentation of those measurements |

---

## Open owner-review items

| Item | Phase | Nature |
|---|---|---|
| `tests/test_m3_multi_vacancy.php` structural probe re-anchored to the canonical stage writer | Phase 3 file, identified during Phase 5 | **Test maintenance**, not a product defect. Phase 3 is not reopened. See "Phase 3 Test Maintenance Identified During Phase 5" in `docs/phase5/P5-COMPLETION-REPORT.md` |

## Carried-forward limitations

| Limitation | Phase |
|---|---|
| No concurrency battery — Phase 5 introduces no contended write, CAS or compensator | 5 |
| `out_of_order` and `uncoded` counts available from the engine but not yet on a user-facing data-quality screen | 5 |
| Pre-Phase-5 ledger rows carry no stage code and are reported as `uncoded` rather than guessed | 5 |
| M5 literal `'ACCEPTED'` tidiness item — no behavioural difference today; belongs to a locked milestone | 5 |
