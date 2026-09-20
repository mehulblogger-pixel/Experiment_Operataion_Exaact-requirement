# EXAACT — Deferred Backlog

**Deferred does not mean forgotten.** It means: deliberately outside the current
Revenue Release critical path, with a named trigger that would bring it back.

**Updated:** 2026-09-20 · baseline `3f5bb75`

---

## 1 · Architectural — deferred by programme decision

| ID | Description | Why deferred | Trigger for reopening | Revenue | Customer | Security |
|---|---|---|---|---|---|---|
| **A-01** | **Person Hub** / universal identity convergence | Phase 6 deliberately made identity *writing* safe instead, so convergence builds on ground that holds. Building a hub now would be the largest change in the programme, against a working system | A customer contractually requires one canonical person record across Recruitment, Workforce and Marketplace | None today | None today — the five representations are linkable | None |
| **A-02** | Remaining Phase-6 convergence (I2, I7, I30 residuals) | Same as A-01 | Same as A-01 | None | Low | None |
| **A-03** | **Q3** — person-identity resolution rules | Depends on A-01 | A-01 reopens | None | None | None |
| **A-04** | **Q7** — cross-representation merge policy | Depends on A-01 | A-01 reopens | None | None | None |
| **A-05** | **Q13** — identity precedence across modules | Depends on A-01 | A-01 reopens | None | None | None |
| **A-06** | **R18** — registered only, not implemented | Phase 6 Batch 1 registered it; implementation was never in scope | A customer-visible defect traces to it | None | None | None |
| **A-07** | **R21** — "keep separate" as a recordable decision (I32 VIOLATED) | No mechanism exists anywhere; it is an enhancement to duplicate handling, not a correctness fix | Duplicate-suggestion noise becomes a customer complaint | None | Low — a rejected suggestion reappears | None |

## 2 · Commercial / conditional

| ID | Description | Why deferred | Trigger for reopening | Revenue impact |
|---|---|---|---|---|
| **C-01** | **Q1 / Q2** — organisation uniqueness model | Two *simultaneous* same-name registrations are detected but not prevented (I34 PARTIAL). Everything else about organisation duplication is closed | **Marketplace commercialisation** genuinely depending on it, or a customer hitting it | Low today — the public route refuses duplicates; this is a narrow race |
| **C-02** | **R23** — taxonomy governance (I24 VIOLATED) | An unresolved free-text skill can become canonical through marketplace search. Does **not** prevent customer setup, recruitment, inspector assignment or reporting | Taxonomy drift produces misleading customer-facing data, or a customer needs controlled skill vocabulary contractually | Low |
| **C-03** | **F-14 / Q30** — `BLACKLISTED` enforces nothing | Recorded and accepted as a controlled limitation in Phase 6 Batch 3 | Owner decides it must block, or a customer trades with a company they believed barred | Low, but reputational |

## 3 · Future UX / enhancement

| ID | Description | Why deferred | Trigger |
|---|---|---|---|
| **U-01** | F-02 — three gating patterns for one nav question | Presentation inconsistency, not a breach; backend enforcement is correct | A new module is added and shown to the wrong users |
| **U-02** | F-03 — 267 routes vs 20 rail items; unquantified orphans | Needs an inventory before any action; no evidence of customer harm | A customer cannot find a feature they bought |
| **U-03** | F-09 — terminology engine used by 76 of 273 ops views | A tenant renaming "requisition" sees it change on a third of screens | A customer renames core vocabulary |
| **U-04** | F-10 — four person-finding mechanisms | Different scopes, legitimately different; consolidation needs owner input | Recruiter confusion is reported |
| **U-05** | F-11 — three KPI families, no stated authority per screen | `rkpi_` is canonical for recruitment; the issue is labelling | Two screens are quoted with different numbers in a customer meeting |
| **U-06** | F-13 — 87 status vocabularies | Mostly legitimately distinct domains | Label inconsistency becomes a training complaint |
| **U-07** | Technical debt: `portal_migrate()` / `indexes_migrate()` set their epoch marker before doing the work | Same family as the static-marker traps found in Batch 3; no current failure observed | A migration is seen to skip silently |

## 4 · Explicitly outside Phase 7

| Item | Note |
|---|---|
| **Live-host workspace incident** (legacy file-backed database, application-folder upload) | **Separate infrastructure/recovery issue — outside Phase 7 scope.** No application change is proposed, implied or required. Do not re-register, recreate, provision, delete or upload to resolve it |
| KPI calculation changes | Phase 5 is locked |
| Phase 6 public-registration security mechanism | Locked by Q26/Q27; no UX reason justifies changing it |
