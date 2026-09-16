# Phase 3 · M3 CORRECTION #6 — COMPLETION REPORT

**Scope: J1 only, plus the J2 / J3 / J4 evidence corrections.** No redesign of M3,
no weakening of fail-closed behaviour, and no M4 work of any kind.

---

## 1 · J1 — root cause

Correction #3 (D2) guarded the audit trail with

```php
if (!array_key_exists($entity, ACT_ENTITIES)) return;   // "never a dangling reference"
```

a check on the entity **TYPE**. The reason that guard most often fires under is
`ENTITY_UNRESOLVED`, which means, by definition, that the **RECORD** is gone. So the
guard's own comment was false for the commonest case it handled.

Auditing the mechanism before coding — as §1 requires — found the same sentence in
**three** places, not one:

| | |
|---|---|
| **J1-a** (reported) | `appr_audit_notify()` writes a supported type with a missing record |
| **J1-b** (sibling) | `appr_audit_sla()` — every SLA, reminder and escalation event — had **no guard at all**, so it did the same on the path D2 never touched |
| **J1-c** (orphan) | For `OFFER` and `SALARY`, `act_log()` **blanks the unsupported kind and still writes the row, keeping the id**, so those events had been landing as `entity_kind=''` with a live `entity_id` since M3 §24 — the fabricated reference D2 forbids |

One sentence, and it is G1's sentence one layer in:
**A SUPPORTED TYPE IS NOT AN OPENABLE RECORD.**

## 2 · J1 — the fix

Not three patches. One rule, in one function, called by **every** writer in the
module — because the recurring failure across corrections #1–#5 has been a rule
applied at one call site and not its sibling.

`appr_audit_ref_ok($kind, $id)` answers all five clauses of §1:

1. the type is supported by the timeline (`ACT_ENTITIES`)
2. the id is valid (`> 0`)
3. the record exists
4. it belongs to **this** tenant
5. it resolves through the **existing** entity mechanism

## 3 · J1 — the fallback audit representation

`appr_audit_subject($req)` picks the smallest **existing** subject that opens:

1. **the source record itself**, when it is genuinely openable here;
2. else **the approval policy governing this chain** — `APPROVAL_POLICY`, already
   registered on the spine by M2, already opened by the existing screen
   `/recruit-approvals?id=<rule>`;
3. else **nothing**. A row nobody can follow is worse than no row.

No second audit system. No new entity kind, route or table. No historical approval
deleted because its source record disappeared. **Traceable event, without a dangling
source-entity reference** — which is what the brief asked for.

## 4 · J1 — tenant protection

Clause 4 is structural, and it covers the **fallback as well as the source**:
`appr_entity_record()` and `appr_rule()` both read the live connection, so a record
belonging to another workspace simply does not resolve here. `C6.3` proves both
directions — a foreign source is never referenced, and when the **fallback is also
foreign**, no row is written at all rather than a foreign reference.

## 5 · One accuracy trap, caught while building the fix

The obvious implementation appends *"source record unavailable"* on every fallback.
That is **false** for an offer whose record is alive and which fell back only
because its type is not on the timeline — the misleading event §2 forbids. The
wording is driven by a separate question, `appr_audit_source_gone()`, and asserted
in both directions: `C6.1` proves a live record is never described as unavailable,
`C6.4` proves a deleted one always is. Mutation **J1-H** attacks it directly and is
caught.

## 6 · J2 / J3 / J4 — evidence corrections

| | |
|---|---|
| **J2** | The runtime database mapping is now established **before any security assertion**: each side reports the database it is *really* connected to (resolved SQLite path, or `SELECT DATABASE()`), and the test asserts they differ. The teardown check — which on MariaDB was literally `t_ok(true)` — now answers per engine. The 25-assertion sabotage proof is untouched. |
| **J3** | `C5.8` proves the two protections on cases where the other cannot be the cause: a far-branch record that **exists** → `RECIPIENT_OUT_OF_SCOPE`; a record the approver was **first shown to be in scope for**, then deleted → `ENTITY_UNRESOLVED`. Neither layer removed. |
| **J4** | Both suites delete the activity rows they create, **by id**, and each asserts its own cleanup. Verified on SQLite and MariaDB. |

## 7 · D2 / D3 preserved (§3)

`C6.5` holds the line: routine outcomes (`SENT`, `NO_EMAIL`, `PROVIDER_FAILURE`) are
still not events; identity, entity, entitlement, scope, segregation and provider
failure remain **six distinct reasons with six distinct sentences**; no unsupported
`ACT_ENTITIES`; no fabricated ids. Mutation **P10R** attacks the D3 collapse
directly and is caught.

## 8 · Business-function regression (§8)

For a record that still exists, nothing moved: `C6.1` proves the event is retained,
every row opens, a **timeline-registered entity is still filed against the record
itself**, and a live record is never described as unavailable. For offer and salary
the change is an improvement — those events previously became orphans and now land
on a subject that opens.

## 9 · Evidence

| | |
|---|---|
| **New suite `p3m3c6_auditref`** | **181 / 0**, both engines |
| `p3m3c5_entity` (with J2/J3/J4) | **238 / 0**, both engines |
| **Full regression · SQLite** | **9939 passed, 0 failed** |
| **Full regression · MariaDB 10.11.14** | **9940 passed, 0 failed** |
| **Mutations** | **76 attempted, 54 caught, 0 survivors left unexplained** |

The new suite fails extensively against the pre-fix library, including
`C6.4 · NOTHING points at the deleted record — THE DEFECT IS CLOSED (want 0, got 2)`.

No test was skipped, disabled or weakened. Two assertions changed meaning
(`C5.7`'s audit-row expectation and `R7 · D2-5`); both are **stricter** than what
they replace, and the second was strengthened in the same edit that widened it.

## 10 · What this correction did NOT do

**H1, H2 and H3** from the correction #5 adversarial audit are **not in scope** and
**remain open**:

* **H1** — a direct call to `appr_act()` still approves an orphan chain.
* **H2** — `appr_tick()` still writes a reminder row on every run over a pending
  orphan. Correction #6 makes those rows **openable**; it does not change how often
  they are written.
* **H3** — `appr_sla_summary()` still counts orphans in its tiles.

## 11 · Honesty notes

* The **first** J1 mutation run is not reported: its baseline was dirty (1 failure)
  and one mutation never executed (`ANCHOR-MISS`). The whole battery was re-run.
* Re-running the earlier batteries produced **11 anchor misses and 6 survivors**.
  Every miss was re-anchored or shown superseded; every survivor was paired to a
  caught result. The `C1/C2` battery reads **0 caught** on its own anchors, which is
  stated plainly rather than hidden in a total.
* One pairing of mine was wrong: a two-way pairing "survived" only because a third
  protection was still standing. The three-way pairing is caught. Recorded because
  it is the same error as representing a multi-dimensional rule with one test row.
* The four surviving E2 "never cast" mutations are equivalent **today** because of
  a return-type contract. That contract is now **pinned by `C6.7`**, not argued in
  prose.

---

**PHASE 3 — M3 CORRECTION #6 COMPLETE — HARD STOP — READY FOR ADVERSARIAL AUDIT**
