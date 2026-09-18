# PHASE 3 · M6 — TEST RESULTS

## Baselines, recorded before any mutation testing (§3)

| | SQLite 3.45 | MariaDB 10.11 |
|---|---|---|
| Database | throwaway file | `exaact_m6` (fresh) |
| Assertions | **11090** | **11091** |
| Failures | **0** | **0** |
| Skips | 0 | 0 |
| Warnings | 0 | 0 |
| Execution time | 117 s | 119 s |
| Environment | healthy | healthy |

Mutation testing ran only after both were clean, and the harness **aborts** when
its baseline is not clean.

## Final results

| Suite | SQLite | MariaDB 10.11 |
|---|---|---|
| `test_p3m6_lifecycle.php` — the integrated lifecycle | **95 / 0** | **95 / 0** |
| `test_p3m6_security.php` — negative matrix, tenant, branch, RBAC, entitlement, input types | **80 / 0** | **80 / 0** |
| `test_p3m6_reconcile.php` — database = service = dashboard = export | **34 / 0** | **34 / 0** |
| `test_p3m6_concurrency.php` — real-process races | **21 / 0** | **21 / 0** |
| `p3m5` | 189 / 0 | 189 / 0 |
| `p3m4` | 172 / 0 | 172 / 0 |
| `p3m3` | 1511 / 0 | 1511 / 0 |
| `p3m2` | 111 / 0 | 111 / 0 |
| `p3m1` | 114 / 0 | 114 / 0 |
| `m4_` | 364 / 0 | 364 / 0 |
| `recruit` | 282 / 0 | 282 / 0 |
| **Complete regression** | **11180 / 0** | **11181 / 0** |

No test was weakened, deleted or skipped. No skip was introduced. M6 adds **259** assertions. The MariaDB total is one higher because one engine-specific assertion
exists only there.

## Test isolation (§43)

Every M6 suite builds its own offices, users, requests, requisitions and
candidates, and depends on no other file having run. Each was run **alone** and
in the **full ordered suite**, and the four were also run in reverse order.

One isolation defect of my own was found and fixed by exactly that check: **L8.5**
originally looked for a requirement in the dashboard's *top eight* open-demand
list. It passed alone and failed in the full run, where other fixtures crowd the
list — a probe depending on what else had run. It now asks the command centre's
own query instead of its top-eight slice.

## Failures classified (§45)

| Failure | Classification | Action |
|---|---|---|
| M4 section J pins naming `hreq_req_block_reason` | **TEST DEFECT** | repointed to the integrated gate **and** to the chain inside it — strictly stronger than before |
| `L8.5` top-eight dependence | **TEST DEFECT** (isolation) | probe rewritten |
| `S5.3` expected `STALE`, got `M4_BLOCKED` | **TEST DEFECT** | the probe reused a blocked requisition, so the owner never moved; a healthy one is used |
| `S6` "the hiring-request layer asks the licence" | **TEST DEFECT** | entitlement's choke point is `can()`; the probe looked in the wrong place |
| `C4.2` fell back to re-reading the same column | **TEST DEFECT** | it compared a value with itself; the worker now reports what it asked for |
| offers / interviews / pipeline / joining on a blocked or full requirement | **PRODUCT DEFECT** | fixed (see M6-ADVERSARIAL-AUDIT.md) |
| approval chain writing `'approved'` / `'on_hold'` | **PRODUCT DEFECT** | fixed |

## The third adversarial pass

Five further product defects were found after M6 was first declared accepted, and
all five are fixed and pinned: the gate deciding *which* requirement by type
conversion, a malformed value conjuring a seat, two routes announcing success on a
refused operation, a cast in the caller that defeated its own repair, and two
approvers both recording a decision on one request. See M6-ADVERSARIAL-AUDIT.md.

The concurrency suite was run **six consecutive times** on MariaDB after the
decision fix — 21/21 each time — because the defect it caught only appeared in two
runs out of five.

## Test defects of my own, disclosed

- **A4 in the first attack was vacuous** — it asserted "the stage did not change"
  on a candidate an earlier probe had already moved. Repaired with a fresh
  candidate and an assertion that the starting stage is what I think it is.
- **A5 and X3 in the second pass tested their own SQL.** Both performed the write
  directly instead of calling the production function, so after the fix they were
  asserting against themselves. Both now call the real path.
- **Two mutations survived the first battery for reasons that were mine, not the
  product's** — see M6-MUTATION-RESULTS.md.
