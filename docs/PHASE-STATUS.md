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
| **Phase 6** | **NOT STARTED** | — |

**Phase 6 has not begun.** No Phase 6 design, implementation, schema change or
test exists. The next development activity is Phase 6, and only after the owner
explicitly authorises it.

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
