# Module 50 — Cross-module Consistency & Regression (capstone) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: every health verdict exists, on its own screen — and nothing shows "is the platform OK?"

Across the programme, many read-only verdict/health helpers were built, each single-source and each
surfaced on **its own** admin screen: `idems_audit_verify()` (chain intact), `integrity_summary()`
(§7.11 data checks), `compliance_status()`/`compliance_counts()` (ISO/legal readiness),
`licence_health()` (Module 36), `integration_health()` (Module 46), `email_failed_count()`
(Module 38), `profit_reconciliation()` (Module 32). There is **no top-level aggregator**: the two
existing roll-ups are orthogonal — `attention_summary()` is *business tasks* (what needs doing) and
`compliance_status()` is *regulatory readiness*. Neither answers "is the platform itself healthy?",
and no `/health` or `/system-status` route existed.

Separately, the programme leaned on **canonical single-source engines** (`job_profit`, `boss_profit`,
`contract_state`/`contract_classify`, `profit_reconciliation`, `idems_audit_verify`,
`compliance_status`) but nothing **asserted** they stay single-source — a future duplicate/shadow
implementation would silently reintroduce the exact drift Module 32 was built to catch.

---

## 1. Built (additive; pure aggregation + a regression guard)

### A — `/system-status` (a read-only platform-health board)
`system_status()` (`lib/ops.php`) fans in each subsystem's own verdict into one list of rows
(`key, label, severity ok|warn|bad, headline, detail, url`): audit chain, data integrity, compliance
readiness, licence, integrations (worst of the connected rows), email delivery, and — salary-gated
like `/profitability` — profit-engine consistency. `system_status_worst()` rolls the rows to one
severity. `ops_system_status()` renders `/system-status` (core `admin` module, gated by
`notifications_can_view()`). Every helper call is `function_exists`-guarded and try/caught, so a
missing subsystem is skipped, never fatal. It is the system-health parallel of `attention_summary()`,
touches no engine, adds no table, stores nothing. Linked from the Settings screen beside the audit
trail / notification log / integration health.

### B — `tests/test_module50_consistency.php` (the cross-module regression guard)
Modeled on `test_no_dead_permission_gates.php`:
- **Single-definition invariant** — each canonical engine/verdict (`job_profit`, `boss_profit`,
  `contract_state`, `contract_classify`, `profit_reconciliation`, `idems_audit_verify`,
  `compliance_status`, `licence_health`, `integration_health`, `attention_summary`, `system_status`)
  is defined **exactly once** across `lib/*.php` — a future shadow copy fails the build.
- **Shape invariants** — `idems_audit_verify` has `ok`; `licence_health` has `needs_attention` +
  `severity`; every `integration_health` row has `severity` + `url`; and the Module-32 identity
  `overstatement == partial − canonical` still holds.
- **Wiring** — `/system-status` is dispatched, admin-mapped, fans in all seven helpers, returns
  well-formed rows, and the Settings screen links to it.

---

## 2. Edge cases handled

1. Each subsystem read is guarded — a pre-migration audit chain reports "check unavailable" (warn),
   an absent integration/licence is simply omitted, none is fatal.
2. Profit consistency shows only to salary-cleared viewers (matches `/profitability`).
3. The board's banner reflects the worst severity; an all-healthy platform says so.
4. The read is passive — it runs the already-computed checks and calls no external service.
5. The single-definition test scans real `lib/*.php` sources, so it catches an accidental duplicate
   anywhere, not just in a known file.

## 3. Guardrails (green)

Every aggregated helper, every engine, and every existing health screen — unchanged. No new
permission (reuses the admin observability gate); no schema change; nothing deleted. The board is
strictly read-only.

## 4. Tests

`tests/test_module50_consistency.php` (64 assertions): the aggregator's shape and wiring; the
single-definition invariant for eleven canonical engines; the health-helper shape invariants; and the
profit-reconciliation identity. Suite **3234 passing** — the only 3 failures are the long-standing
pre-existing baseline (the service Release-dependency seed-vs-test drift in `test_services.php`),
unrelated to any module in this programme.

---

## Programme close

This capstone ties together ~30 additive modules delivered on branch
`claude/quotation-management-workflow-5dokb2`, every one non-destructive: signals surfaced, verdicts
made visible, canonical engines reused (never duplicated), audit/provenance completed, and access- or
control-changing fixes **flagged for explicit decision** rather than silently applied. The test suite
grew from ~2954 to 3234 passing with only the 3 pre-existing baseline failures remaining throughout.
