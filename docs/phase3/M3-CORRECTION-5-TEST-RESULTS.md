# Phase 3 · M3 CORRECTION #5 — TEST RESULTS

## New suite — `tests/test_p3m3c5_entity.php`

**179 assertions, 0 failed, on both engines.**

| Section | Assertions | What it holds |
|---|---:|---|
| **C5.1 · A — a real record behaves exactly as before** | 24 | for each entity: the record resolves; the informational and actionable levels allow it; **exactly one** recipient, the right address; and **§12** — a *valid* entity still notifies its canonical raiser, so `INVALID→DENY` did not become `VALID→DENY` |
| **C5.2 · B–F — every way an entity can fail to resolve** | 104 | for each entity × `id 0` · negative id · cross-tenant id · unknown type · blank type · malformed reference: **informational denies, actionable denies, no recipient, nothing sent** — plus the foreign id proved absent from that entity's own source table, so isolation is shown rather than assumed |
| **C5.3 · B — THE DEFECT: record deleted, chain survives** | 36 | eligible while the record exists; then for each entity the record stops resolving, the informational level returns **`ENTITY_UNRESOLVED`** (not `IDENTITY_UNRESOLVED`), the actionable level denies, **no recipient**, the decision notifier denies, nothing sent, **no approval state changed**, and the **scheduler is silent about it too** |
| **C5.4 · the rescue path is gone** | 8 | for each entity the approver **genuinely passes `appr_can_act()`** and visibility still denies — so a missing record cannot fall through to a generic rescue |
| **C5.5 · reasons and audit integrity** | 2 | an unresolved entity is `ENTITY_UNRESOLVED`; no dangling audit reference was created anywhere along the way |
| **C5.6 · a resolver that errors denies** | 4 | the source table is **renamed out from under the resolver** — what a half-applied migration looks like — and the resolver returns nothing rather than raising, and the notification denies. Restored in a `finally` |

### The matrix, per entity — a row for each half of the rule

| | HIRING_REQUEST | OFFER | SALARY | REQUISITION |
|---|---|---|---|---|
| A valid type + valid record | as before | as before | as before | as before |
| **B valid type + deleted record** | deny | **deny** | **deny** | **deny** |
| C invalid id (0, negative) | deny | deny | deny | deny |
| D cross-tenant id | deny | deny | deny | deny |
| E unknown type | deny | deny | deny | deny |
| F malformed reference | deny | deny | deny | deny |

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **9698 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** (fresh `exaact_m3j`) | **9699 passed, 0 failed** |
| M3 correction #5 on MariaDB | **179 / 0** |

| Suite | Result | Covers |
|---|---|---|
| **`p3m3c5_entity`** | **179 / 0** | G1 |
| `p3m3c4_gate` | 129 / 0 | E1, E2 |
| `p3m3c3_raiser` | 53 / 0 | D1, D2, D3 |
| `p3m3c2_identity` | **51 / 0** | C1, C2 |
| `p3m3c_notify` | 68 / 0 | F1, F2, F3 |
| `p3m3_sla` | 176 / 0 | SLA, escalation, inbox |
| `p3m1_approval` · `p3m2_matrix` | 114 / 0 · 111 / 0 | M1, M2 + M2 correction |
| `recruit_approval` · `offer_appr_dept` | 25 / 0 · 2 / 0 | offer / salary / requisition approval |
| `m4_hiring_request` · `m4_correction` · `hiring_admin` | 78 / 0 · 107 / 0 · 11 / 0 | |

Operations, Reporting, Quality, Money, Workforce, Marketplace and the Recruitment
Command Centre are inside the whole-suite figures. **Nothing skipped, weakened,
deleted or re-baselined.**

## One existing assertion was superseded — and made stricter, not removed

`ID5 · a KNOWN non-hiring entity is still visible on can-act alone` asserted
visibility for an offer using a **made-up id** — the precise behaviour G1 removes.
Its *intent* (no branch requirement is imposed on a non-hiring entity) is still
true, so it now asserts that against a **real** offer record, and the made-up id
became a second assertion proving the other half of the rule. The suite went
**50 → 51**.

## Two of my own test gaps, closed rather than excused

- **C5.6 did not exist.** The resolver's `catch` — *"a resolution that errors is a
  resolution that failed"* — was never exercised, so the mutation that swallows it
  survived. The condition is now **constructed** by renaming the source table.
- The superseded assertion above would have quietly kept passing for the wrong
  reason had G1 been implemented differently.
