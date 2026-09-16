# Phase 3 · M3 CORRECTION #6 — TEST RESULTS

## New suite — `tests/test_p3m3c6_auditref.php`

**181 assertions, 0 failed, on both engines.**

The central assertion is the one the old test did not make. It takes the
`entity_kind` and `entity_id` each activity row **actually claims**, and tries to
**open them** — through a resolver written independently of the code under test, so
it cannot inherit the mistake it is checking for.

| Section | Covers |
|---|---|
| **C6.1 · case 1 — existing source** | For each entity: the event is retained, every row opens, a **live record is never described as unavailable** (§2), and a timeline-registered entity is still filed **against the record itself** — §8's business-function regression |
| **C6.2 · cases 3, 5, 6** | id 0 · negative id · unknown type · blank type · malformed reference — every row opens, and **nothing references the unusable source** |
| **C6.3 · case 4 — cross-tenant** | A foreign source is never referenced; and when the **fallback is also foreign**, no row is written at all rather than a foreign reference |
| **C6.4 · case 2 — deleted source** | The event survives, **nothing points at the deleted record**, it is filed under the governing policy, each row **says** the source is unavailable, and with no usable fallback **no row is written** |
| **C6.5 · D2 / D3 preserved** | Routine outcomes are still not events; identity, entity, entitlement, scope, segregation and provider failure remain six distinct reasons with six distinct sentences |
| **C6.7 · return-type contracts** | The contracts that make the four surviving E2 "never cast" mutations equivalent **today** — `appr_notify_gate()` and `appr_told_reason()` always return a string, `appr_may_be_asked()` and `appr_visible()` always return a strict boolean, `appr_audit_ref_ok()` / `appr_audit_subject()` keep theirs. Pinned by test rather than argued in prose, so the day one of them changes this fails first |
| **C6.6 · the five clauses** | Each clause of `appr_audit_ref_ok()` is failed on its own — no type, id 0, negative id, a foreign id, a timeline type this module does not own, a real record of an unlinkable type, and a resolver whose table has been renamed away |

### The suite has teeth — it fails against the pre-fix library

Run unchanged against `HEAD~` (the library before correction #6):

```
FAIL  C6.4 · HIRING_REQUEST · NOTHING points at the deleted record — THE DEFECT IS CLOSED  (want 0, got 2)
FAIL  C6.4 · HIRING_REQUEST · filed under the governing policy instead  (want 2, got 0)
FAIL  C6.1 · OFFER · and none is an orphan (blank type, live id)  (want 0, got 1)
FAIL  C6.3a · REQUISITION · no audit reference to the foreign record  (want 0, got 1)
…
```

## J2 / J3 / J4 — evidence corrections in `test_p3m3c5_entity.php`

**238 assertions, 0 failed** (was 225).

| | |
|---|---|
| **J2** | The child process now reports the database it is **really connected to** — the resolved SQLite path, or `SELECT DATABASE()` on MariaDB. Tenant A and tenant B are proved to be **different databases, before any security assertion is made**. The teardown check, which on MariaDB was literally `t_ok(true)`, now answers per engine: the file is gone on SQLite, the schema is gone from `information_schema.SCHEMATA` on MariaDB. The 25-assertion sabotage proof is untouched. |
| **J3** | New **C5.8** proves the two protections on cases where the other cannot be the cause. **Scope, isolated:** a far-branch record that *exists* → `RECIPIENT_OUT_OF_SCOPE`. **Entity resolution, isolated:** a record in the approver's own branch, first shown to be **allowed by scope**, then deleted → `ENTITY_UNRESOLVED` on the informational path and denied on the actionable path, with no scope protection standing behind it. Neither layer was removed. |
| **J4** | Both suites now delete the activity rows they create, **by id**, never by a `LIKE` sweep over somebody else's history; each asserts its own cleanup. |

### Four assertions rewritten, not weakened

C5.7 previously expected a refusal on a cross-tenant reference to write **one audit
row** for `HIRING_REQUEST` and `REQUISITION`. That row pointed at **another
workspace's id** — the J1 defect. The assertions now require **no row at all**
(nothing openable remains) and that **nothing references the foreign id**. Strictly
stronger.

The D2 suite's `R7 · D2-5` was likewise strengthened rather than widened: see the
mutation results document.

## Full regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **9939 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** | **9940 passed, 0 failed** |

| Suite | Result | Covers |
|---|---:|---|
| **`p3m3c6_auditref`** | **181 / 0** | J1 |
| `p3m3c5_entity` | **238 / 0** | G1 + J2 / J3 / J4 |
| `p3m3c4_gate` | 129 / 0 | E1, E2 |
| `p3m3c3_raiser` | **54 / 0** | D1, D2, D3 |
| `p3m3c2_identity` | 51 / 0 | C1, C2 |
| `p3m3c_notify` | 68 / 0 | F1, F2, F3 |
| `p3m3_sla` | 176 / 0 | SLA, escalation, inbox |
| `p3m1_approval` · `p3m2_matrix` | 114 / 0 · 111 / 0 | M1, M2 + M2 correction |
| `recruit_approval` · `offer_appr_dept` | 25 / 0 · 2 / 0 | offer / salary / requisition approval |
| `m4_hiring_request` | 78 / 0 | hiring request layer |

No test was skipped, disabled or weakened. The two assertions that changed meaning
(`C5.7`'s audit-row expectation and `R7 · D2-5`) are both **stricter** than the
versions they replace.
