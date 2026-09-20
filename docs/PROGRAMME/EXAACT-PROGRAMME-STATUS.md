# EXAACT — Authoritative Programme Status

**The single authoritative programme-status document.** Where any other document
disagrees with this one, this one wins.

**Baseline:** HEAD `3f5bb75` · branch `claude/testing-branch-setup-0gqe8n`
**Updated:** 2026-09-20

---

## A · Phase status

| Phase | Status | Evidence |
|---|---|---|
| Phase 0 — architecture audit | **LOCKED** | `docs/phase0/` (32 deliverables, `15-ARCHITECTURE-LOCK.md`) |
| Phase 1 — commercial boundary | **LOCKED** | `docs/phase1/PHASE1-CLOSURE-REPORT.md` · SQLite 8 267 / MariaDB 8 268, 0 failed |
| Phase 2 — organisation, vocabulary, requirement | **LOCKED** | `docs/phase2/M3-`, `M4-COMPLETION-REPORT.md` |
| Phase 3 — approval, authority, SLA | **LOCKED** | `docs/phase3/M6-*` (15 numbered corrections, 36/36 mutants) |
| Phase 4 — multi-source fulfilment | **LOCKED** | `docs/phase4/P4-COMPLETION-REPORT.md` · 38/38 mutants |
| Phase 5 — recruitment KPI | **LOCKED** | `docs/phase5/P5-COMPLETION-REPORT.md` · 145/0 · 39/39 mutants |
| Phase 6 — identity | **CLOSED FOR EXECUTION** | Batches 1, 2, 3 + corrective all ACCEPTED / LOCKED |
| Phase 7 Entry Audit | **COMPLETE** | `docs/phase7/P7-ENTRY-AUDIT.md` (15 findings, evidence-backed) |
| **Revenue Readiness** | **IN PROGRESS** | this programme · `docs/phase7/REVENUE-READINESS-AUDIT.md` |
| **Production UAT** | **PENDING — NOT PROVEN** | no production deployment has been performed or claimed |

### Phase 6 closure rule

Phase 6 is **closed for execution**. Its unresolved identity/convergence items
are **deferred backlog, not deleted** — see `DEFERRED-BACKLOG.md`. Phase 6 is not
to be reopened merely because historical Q/R items remain open.

**Not to be built:** Person Hub · universal identity convergence · a new identity
architecture.

## B · Protected areas register

Last verified on the Batch 3 corrective final run: **SQLite 12 834 / MariaDB
12 839 assertions, 0 failures, 510 test files, both engines.**

| Module | Status | Last verified | Protected from redesign? | Regression requirement | Known limitation |
|---|---|---|---|---|---|
| Operations | Working | full regression, 24 test files | **Yes** | full suite both engines | none recorded |
| Reporting | Working | 28 test files | **Yes** | full suite | none recorded |
| Money / Finance | Working | 17 test files | **Yes** | full suite | journey not traced end-to-end in this action |
| Workforce (`inspectors`) | Working **with an integrity gap** | 6 test files | **Yes** | full suite | **R20 — no uniqueness protection of any kind** |
| Marketplace | Working | 58 test files | **Yes** | full suite | allocation→workforce not browser-traced |
| Recruitment | Working **with two handoff gaps** | 25 test files | **Yes** | full suite | **F-05, F-06** |
| Dashboards | Working | — | **Yes** | full suite | no canonical home (F-01) |
| Entitlement | Working, fail-closed | 74 test files | **Yes** | full suite | none recorded |
| Tenant isolation | **Proven** | 184 assertions on MariaDB, this action | **Yes** | full suite | structural: one DB per tenant |
| KPI engine (`rkpi_`, 25 fns) | Working | Phase 5 · 145/0 · 39/39 mutants | **Yes — single engine** | full suite | source not labelled per screen (F-11) |
| Approval engine | Working | Phase 3 · 36/36 mutants | **Yes — single engine** | full suite | bypassable via Path B (Q29) |
| SLA / notification | Working | inside approval engine | **Yes — single engine** | full suite | access-request queue not notified (F-12) |
| Identity mechanisms | Working | Phase 6 Batches 1–3 | **Yes** | full suite | convergence deliberately deferred |
| Audit mechanisms | Working | Batch 1 (retrievable), Batch 3 (security separation) | **Yes** | full suite | none recorded |

## C · Owner decisions recorded, not taken

These are recorded here because they are **business decisions**, and this
programme must not take them silently.

| ID | Decision | Status |
|---|---|---|
| **Q29** | Requisition governance — Path A only, or both paths | **OPEN** (ADR-001, open since Phase 2) |
| **Q30** | Should `BLACKLISTED` enforce anything | **OPEN** |
| **Q31** | Role-based home — one obvious start per role | **OPEN** |
| **Q32** | Workforce vs Inspector — is every hire an Inspector | **OPEN** |

Full text in `docs/phase7/REVENUE-READINESS-AUDIT.md` §Owner decisions.

## D · What is and is not proven

| Level | Status |
|---|---|
| Automated test proven | **YES** — 12 839 assertions, both engines, mutation-tested |
| Local HTTP proven | **PARTIAL** — public registration only, ad-hoc, not in the repository |
| Local browser proven | **PARTIAL** — 18 assertions, ad-hoc, not in the repository |
| Production deployed | **NO** |
| Production smoke proven | **NO** |
| Customer UAT proven | **NO** |

**No production deployment is claimed anywhere in this programme.**
