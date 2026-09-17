# PHASE 3 · M4 — TEST RESULTS

| Suite | SQLite | MariaDB 10.11 |
|---|---|---|
| `m4_` (hiring-request layer + corrections, incl. the M4 scenario suites) | **363 / 0** | **363 / 0** |
| `test_p3m4_reapproval.php` — the twelve validation scenarios | **71 / 0** | **71 / 0** |
| `test_p3m4_security.php` — security probes (incl. section J) | **76 / 0** | **76 / 0** |
| `test_p3m4_concurrency.php` — real-process concurrency | **24 / 0** | **24 / 0** |
| **Complete regression** | **10731 / 0** | **10732 / 0** |

No test was weakened, deleted or skipped. No skip was introduced. The MariaDB
total is one higher because one engine-specific assertion exists only there.

## Engine difference

**One, and it was a defect rather than a difference in behaviour:** the last-seat
race (C1) passed on SQLite and failed on MariaDB, because SQLite serialises writers
with a database-level lock. It is fixed, and both engines now behave identically.
See M4-CONCURRENCY-RESULTS.md.

## Assertions re-pointed, and why — none weakened

| Assertion | Was | Now | Why |
|---|---|---|---|
| `D0 · every database write lives in an audited function` | five writers | **seven** | M4 added `hreq_require_reapproval()` and `hreq_qty_enforce_after_write()`; the guard caught both, which is what it is for, and both audit what they do |
| `an approved request cannot be silently changed` | the edit was refused outright | the edit is **allowed**, the approval is **invalidated**, execution **stops**, the approved snapshot is **intact** and the ceiling still uses the **approved** figure | M4 replaces immutability with control; the protection is asserted more strongly, not less |
| `M4.3 / M4.4 re-approval state` | expected `IN_PROGRESS` | `REQUIRED` where no rule is configured | the documented behaviour, identical to a first submission; **M4.12** was added to prove the chain genuinely runs when a rule exists |
| `M4.12 chain count` | expected a new chain row | **exactly one OPEN chain** | `appr_start()` deliberately returns an already-open chain; the open-chain count is the invariant that matters |

## After the adversarial audit

The counts above are the **post-audit** figures. The audit found two execution
paths that never asked the boundary; the fixes and the eleven new section-J
assertions are included here, and both engines were re-run from scratch afterwards.
See M4-ADVERSARIAL-AUDIT.md.

## Test defects of my own, found and reported

- The tenant-B fixture was not fully built (died on *"no such table: requisitions"*).
- The entitlement probe used `VIEWER`, a role that does not exist — and an
  unrecognised role falls back to `ADMIN`, so the probe granted itself every
  permission and blamed the product.
- Two fixture assertions guessed values (`Site Engineer`, a clean remaining
  count) instead of reading the real fixture.
- Mutation **M6** was a no-op; **M5** exposed a genuine gap in my own tests.
