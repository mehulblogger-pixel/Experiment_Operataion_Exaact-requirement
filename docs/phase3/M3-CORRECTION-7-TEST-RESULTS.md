# Phase 3 · M3 CORRECTION #7 — TEST RESULTS

## New suite — `tests/test_p3m3c7_idempotent.php`

**86 assertions, 0 failed, on both engines.**

| Section | §10 | What it holds |
|---|---|---|
| **C7.1 · the fixture is honest first** | R, S | The approver is proved **genuinely eligible** and the raiser proved **still refused**; every chain is proved to **carry a real rule**; every source record is proved to **exist before it is deleted** |
| **C7.2 · classification** | K | Three reasons are PERMANENT, eight are TRANSIENT; a transient condition has **no suppression key at all**; the key is stable across observations (no timestamp) and differs per chain |
| **C7.3 · K1, all four entities** | F–I | The record is deleted, the **first** refusal is recorded, and **nine more identical refusals add nothing** |
| **C7.4 · K2** | A, B | Deduplication is identical **with and without** a rule, and the two chains keep separate identities |
| **C7.5 · H2** | C, D, E | Three scheduler runs with the clock advanced between them: run 1 records once, runs 2 and 3 record **nothing**, and each run is proved to have **actually executed** |
| **C7.6 · a new condition is not suppressed** | J | An escalation on the **same** step after a reminder condition **is** recorded; two different permanent reasons are two different conditions |
| **C7.7 · transient conditions recur** | K | Two observations of `RECIPIENT_INACTIVE` produce **two** rows |
| **C7.8 · correction #6 still holds** | L–Q | Invalid id · missing entity · cross-tenant · unknown type · malformed id — every row still opens and produces a real link through **`act_link()`**, and nothing references the foreign id |

## §13 — evidence quality for the assertions that matter

| Assertion | Fixture / precondition | Action | Expected | Actual | Why it proves the intent |
|---|---|---|---|---|---|
| `C7.5 C run 1 recorded once` | live chain, rule present, record **deleted**, `reminder_at` in the past | `appr_tick()` | 1 new row | 1 | Paired with `acted > 0`, so a scheduler that **skipped** the step cannot pass it |
| `C7.5 D/E runs 2–3 wrote nothing` | clock advanced before each run | `appr_tick()` | 0 new rows | 0 | `acted > 0` each time proves the scheduler ran and chose not to write — not that it was idle |
| `C7.3 nine more add nothing` | source record deleted, first row already written | 9 × `appr_email_requester()` | no change | no change | The **first** row is asserted separately, so "suppress everything" cannot pass |
| `C7.4 B with rule_id` | chain asserted to **have** `rule_id > 0` | 5 × refusal | ≤ 1 row | 1 | This is the K2 trap closed: the old assertion passed only because its fixture had no rule |
| `C7.7 two transient rows` | record **exists**, recipient merely inactive | 2 × `appr_audit_notify()` | 2 rows | 2 | Proves suppression is scoped to permanent conditions, not blanket silence |
| `C7.1 S approver eligible` | separate raiser and approver, same branch | `appr_may_be_asked()` | `true` | `true` | Without it, every later "denied" result could be segregation rather than the rule under test |

### A test that previously passed for the wrong reason

`R7 · D2-1` in `test_p3m3c3_raiser.php` asserted that five offer decisions with no
identity write no repetitive rows. It passed because its fixture **carried no
`rule_id`** — there was no openable subject, so no row, for a reason unrelated to
suppression. That is K2. The new `C7.4` runs the same shape **with the rule
present**, and asserts the fixture has one before measuring anything.

### A first run of this suite that failed, and was right to

`C7.5 C` failed on its first execution — `want 1, got 0`. The scheduler was writing
**nothing**, because `appr_tick()` built its request row without `rule_id`, so
after correction #6 an orphan had no openable subject. H2's symptom had been
masked by dropped history. The fix is in the audit document (§4); the assertion is
what found it.

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **10 025 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** | **10 026 passed, 0 failed** |

| Suite | Result | Covers |
|---|---:|---|
| **`p3m3c7_idempotent`** | **86 / 0** | K1, K2, H2, K3 |
| `p3m3c6_auditref` | 181 / 0 | J1 |
| `p3m3c5_entity` | 238 / 0 | G1 + J2/J3/J4 |
| all M3 suites together (`p3m`) | **1208 / 0** | M1, M2, M3 + corrections #1–#7 |

No test was skipped, disabled or weakened. No existing assertion changed meaning
in this correction.
