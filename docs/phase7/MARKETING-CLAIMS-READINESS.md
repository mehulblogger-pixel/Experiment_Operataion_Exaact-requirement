# EXAACT — Marketing Claims Readiness

**Purpose: stop marketing promising something the product cannot reliably
deliver.** Each claim is matched to the functionality that supports it and to
what has actually been proven.

Baseline `3f5bb75` · 2026-09-20. **No marketing content was written.**

Columns: *Production proven* means deployed and exercised on a live host.
*Customer proven* means a real customer completed it in UAT. **Both are NO for
every claim today** — nothing has been deployed.

---

| # | Proposed claim | Supporting functionality | Evidence | Production proven | Customer proven | Safe to market? |
|---|---|---|---|---|---|---|
| 1 | "One system for recruitment and operations" | Recruitment, Workforce, Operations in one tenant database | 510 test files, 0 failures | No | No | **NOT YET — RB-1 breaks the join between them** |
| 2 | "Approval-controlled hiring" | One approval engine, matrix, delegation, SLA, escalation | Phase 3, 36/36 mutants | No | No | **QUALIFIED** — true only if Q29 closes Path B; today it is bypassable |
| 3 | "Know exactly how many people you have hired" | `requested`/`filled`/`joined` all computed | Phase 4, Phase 5 | No | No | **NOT YET — RB-2 reports filled as if joined** |
| 4 | "Track inspector utilisation" | `jobs`/`attendance` per inspector, `rkpi_`, TAPI dashboards | Phase 5, 145/0 | No | No | **NOT YET — RB-3 can split one person across two records** |
| 5 | "Fill one requirement from several sources" | `requisition_allocations` — one row per source's promise | Phase 4, 38/38 mutants | No | No | **YES** (once deployed) — genuinely built and proven in test |
| 6 | "Your data is isolated from other customers" | One database per tenant + workspace binding | 184 assertions, MariaDB, this action | No | No | **YES** (once deployed) — the strongest claim in the product |
| 7 | "You only see the modules you bought" | Fail-closed entitlement; UNKNOWN = DENY | Phase 1, 74 test files | No | No | **YES** (once deployed) |
| 8 | "Duplicate companies are caught before they are created" | One detector wired into all six creation doors | Phase 6 Batch 3, mutation-tested | No | No | **QUALIFIED** — true except two *simultaneous* registrations (Q1/Q2) |
| 9 | "Sign-up cannot be used to discover our customers" | Neutral response, byte-identical, verified by size and timing | Batch 3: 3 574 bytes both cases | No | No | **YES** (once deployed) — unusually strong, and measured |
| 10 | "Configurable hiring workflow" | One configurable pipeline per workspace | Phase 2 | No | No | **YES** (once deployed) |
| 11 | "Mobile-ready for field staff" | 29 `@media` rules, viewport meta | — | No | No | **NO — 192 of 315 ops views have unwrapped tables** |
| 12 | "Blacklist companies you will not work with" | `BLACKLISTED` status | — | No | No | **NO — it enforces nothing today** (Q30) |
| 13 | "Full audit trail" | Typed, retrievable, attributable; security evidence separated | Batch 1, Batch 3 | No | No | **YES** (once deployed) |
| 14 | "Marketplace and recruitment are kept separate" | `cx_requirements` vs `requisitions`, no shared table | 24 refs, 0 crossover | No | No | **YES** (once deployed) |

## Rules for marketing until production is proven

1. **No claim may say "proven in production" until a production deployment has
   happened.** Nothing has been deployed. This is not a formality — §27 of the
   programme rules forbids it.
2. **Claims 1, 3, 4, 11 and 12 must not be made at all** until their blockers
   close.
3. **Claims 2 and 8 must carry their qualification** if used.
4. Claims 5, 6, 7, 9, 10, 13 and 14 are **safe to prepare now** and become safe
   to publish once deployed and smoke-tested.
5. **Claim 9 is the strongest differentiator in the product** and is measured
   rather than asserted — it can be stated precisely, which competitors rarely
   can.
