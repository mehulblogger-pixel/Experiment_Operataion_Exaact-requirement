# 10 — Execution Stages (Stage 0–9)

The controlled, staged execution of the master revamp
([`docs/00-master-revamp-prompt.md`](../00-master-revamp-prompt.md)). Each stage is
a gate: **no stage starts until the previous stage's exit criteria are met**, and
every change obeys the four non-negotiable laws (nothing deleted, nothing breaks,
nothing truncated, no duplicate systems). Every stage is its own reversible
change-control record; no code lands without its record approved.

Legend — **KEEP / EXTEND / CONNECT / CONFIGURE / BUILD** (verbs from
`docs/connect/01-reuse-map.md`). Status: ✅ done · ▶ in progress · ⬜ not started.

| Stage | Name | Verb | Status |
|---|---|---|---|
| 0 | Full application audit & dependency mapping | — | ▶ (docs/revamp/01–02, connect/06) |
| 1 | Freeze & protect existing architecture | KEEP | ✅ (auto-walk + 5234 tests as the safety net) |
| 2 | Company Capability engine | BUILD | ✅ (`connect_capability.php`) |
| 3 | Universal Technical Passport & taxonomy | EXTEND | ▶ (passport + `cx_tax_*` exist; deepen) |
| 4 | Marketplace integration | EXTEND | ▶ (post/apply/match/deploy exist; supplier types) |
| 5 | Operations integration | CONNECT | ▶ (award→job bridge exists) |
| 6 | UX contextualization (Combination Engine nav gating) | CONFIGURE | ⬜ (engine ready; apply to nav) |
| 7 | Conflict & edge-case engine | EXTEND/BUILD | ⬜ |
| 8 | Full regression testing | — | ♻ continuous (auto-walk + suite) |
| 9 | Final acceptance validation (10-company matrix) | — | ⬜ |

---

## Stage 0 — Audit & dependency mapping
**Do:** for each capability in hand, produce the one-screen map — which engine owns
it, what verb applies, the smallest additive change — against
`docs/revamp/01-current-state-inventory.md`, `02-gap-and-reuse-map.md`,
`docs/connect/01-reuse-map.md`, `06-system-audit.md`.
**Exit:** no change is designed before its reuse map exists.

## Stage 1 — Freeze & protect
**Do:** stand up the safety net so any regression is caught immediately — the test
suite (**5234 passing**) plus the one-command `bash tools/auto-walk.sh`, which
drives ~196 screens across every role and fails on any PHP error.
**Exit:** green baseline recorded; every later stage must return to it.

## Stage 2 — Company Capability engine ✅
**Done:** `lib/connect_capability.php` — multi-select capabilities, additive
`cx_org_capabilities`, master-only `/connect-capabilities`, the Combination Engine
(`connect_cap_modules` / `connect_cap_shows`, visibility-only, permissive by
default), the Freelance-Supplier three-pool reader, 21 tests.
**Exit met:** suite 5234/0; auto-walk all-clean; no existing company affected.

## Stage 3 — Universal Technical Passport & taxonomy (EXTEND)
**Do:** deepen the passport (`connect_passport`, `connect_pro`, `connect_credentials`)
and the taxonomy graph (`cx_tax_*` + adopted 9-table ontology) — multi-skill,
verification states, CV-assisted creation (`connect_cv`/`cvp`), the location/travel
engine (`connect_geo`, Pan-India disables radius).
**Exit:** a professional carries every dimension matching reads; verification states
are explicit; nothing hard-codes a per-job form.

## Stage 4 — Marketplace integration (EXTEND)
**Do:** supplier types (individual / freelance-supplier co / manpower supplier /
TPIA / technical-services co) on `connect_market`/`connect_hiring`; demand flow
(requirement → structured need → matching); ranked, explainable matches
(`connect_match`).
**Exit:** a client can search each supplier type; every match states why + what's missing.

## Stage 5 — Operations integration (CONNECT)
**Do:** ensure every marketplace engagement converts to operational work with no
re-keying (`connect_deploy` award→job), through mobilization readiness + scheduling.
**Exit:** no marketplace outcome dead-ends; the spine carries it to execution.

## Stage 6 — UX contextualization (CONFIGURE) — the next build
**Do:** apply `connect_cap_shows()` to gate the live navigation per company +
capabilities + role + context, so a pure recruiter doesn't see inspection modules.
**Guardrail:** default-permissive stays the floor; gating is added screen-group by
screen-group, each re-crawled, so no company ever loses a screen it needs.
**Exit:** each of the 10 archetypes (A–J) sees a coherent, capability-appropriate app.

## Stage 7 — Conflict & edge-case engine (EXTEND/BUILD)
**Do:** the negative paths (master-prompt §24, §37) — availability/double-booking,
expired credentials blocking assignment, mobilization/gate-pass blocks, offline
sync recovery, report rejection loop, billing mismatch reconciliation, duplicate
prevention — extending `docs/edge-cases/`.
**Exit:** every listed failure has a defined, tested behaviour.

## Stage 8 — Full regression testing (continuous)
**Do:** after every stage, `php tests/run.php` at ≥ baseline and `bash
tools/auto-walk.sh` all-clean, plus positive/negative/boundary/offline/permission
cases for the stage.
**Exit:** green before any commit; a fix that drops a test is reverted, not shipped.

## Stage 9 — Final acceptance validation
**Do:** run the 10-company matrix (§38) and the end-to-end scripted runs
(`docs/qa-edge-cases/`); confirm the Definition of Done (master-prompt §W / §40).
**Exit:** the platform behaves correctly for one, several and all capabilities;
marketplace connects to operations; existing functionality intact.

---

*Sequencing follows `docs/revamp/03-target-architecture.md §8`; this document adds
the capability/supplier/UX stages the refined master prompt introduced. Stage 2 is
delivered; Stage 6 (nav gating) is the recommended next build.*
