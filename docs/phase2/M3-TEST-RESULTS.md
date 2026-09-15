# Phase 2 · M3 — Test Results

## 1. Environment (§50)

| | |
|---|---|
| PHP | 8.4.19 |
| Engine 1 | SQLite (bundled) |
| Engine 2 | MariaDB 10.11.14, over TCP |
| Suite | `php tests/run.php` — the whole suite |
| New file | `tests/test_m3_multi_vacancy.php` — **87 assertions** (68 from the build, 19 from the audit) |

Figures are recorded from the runs themselves. Nothing is predicted from the
other engine, and nothing is claimed for an engine that was not exercised.

## 2. Results

Figures below are the **post-audit** run, against the final tree.

| Engine | Passed | Failed | Skips | Duration |
|---|---|---|---|---|
| SQLite | **8571** | **0** | none introduced | ~6 min |
| MariaDB 10.11.14 | **8572** | **0** | none introduced | ~9 min |

*(The pre-audit tree reported 8552 / 0 and 8553 / 0 on the two engines.)*

> **Assertions, not test cases.** One scenario usually costs several: hire three
> people against a five-vacancy requisition, then assert the status, the filled
> count, the remaining count and that each hire is listed = four assertions from
> one scenario. M3's 68 assertions cover roughly 25 scenarios.

## 3. What the M3 tests cover (§37)

| Group | Assertions | What is held in place |
|---|---|---|
| Nothing duplicated | 6 | **no** fulfilment/vacancy/hire table; the existing `quantity` reused; `hired_inspector_id` preserved; `cx_requirements` untouched |
| **The §34 acceptance test** | 6 | one hire does **not** close a five-vacancy requisition |
| Individual hires | 4 | all three listed separately; each with its own workforce record; `hired_inspector_id` still names the most recent |
| Pipeline ≠ fulfilment | 5 | two people in progress fill nothing and never vanish from the count; a rejection consumes no seat |
| Completion | 3 | the fifth person joins → the requisition finally reads as filled |
| Cancellation | 8 | four cancelled are **not** counted as hires; over-cancelling refused; one-line summary correct |
| Explicit decisions | 4 | a CLOSED / CANCELLED / DRAFT requisition is never overruled by arithmetic |
| Quantity boundaries | 7 | zero, negative, 100000, and more hires than seats — remaining never goes negative |
| Invalid input | 7 | unknown id, zero and negative cancellations, missing requisition |
| The fix is at the hire moment | 4 | the old `status='HIRED'` write is gone; `PARTIALLY_FILLED` added to the existing list |
| Route protection | 5 | the cancel route runs the **same** branch-scope gate and permission bar, **before** reading an id |
| Idempotency | 2 | running the migrations twice adds no column and changes no data |
| **Audit findings** | **19** | the status is recomputed at every mutation point; a reversed hire reopens the seat and can return all the way to `OPEN`; a quantity cut completes or clamps correctly |

## 4. Mutation testing (§39)

| # | Mutation | Result |
|---|---|---|
| **M1** | The first hire closes the requisition again | **2 failed** ✅ |
| **M2** | Remaining quantity always returns 0 | **13 failed** ✅ |
| **M6** | A cancelled vacancy is counted as a hire | **6 failed** ✅ |
| — | Over-cancellation allowed | **2 failed** ✅ |
| — | A person's explicit CLOSED/CANCELLED decision is overruled | **1 failed** ✅ |
| — | Someone in the pipeline counts as having filled a seat | **2 failed** ✅ |
| — | Branch-scope gate removed from the cancel route | **2 failed** ✅ |
| — | *(build mutations above; audit mutations below)* | |
| **A1** | Sync removed from the stage-change chokepoint | **2 failed** ✅ |
| **A1b** | Sync removed from the quantity edit | **2 failed** ✅ |
| **A1c** | Sync removed from the candidate move (both sides) | **1 failed** ✅ |
| **A2** | A requisition can no longer return from partly filled to open | **1 failed** ✅ |
| **A3** | Cancelled no longer clamped to what was asked for | **1 failed** ✅ |
| — | *all restored* | **87 passed, 0 failed** |

No mutation passed silently. No existing test was weakened, and no skip was
introduced.

> §39 also lists tenant scope, master-bypass and foreign-candidate mutations.
> M3 introduced no new tenant mechanism, no `is_master()` override and no new
> candidate-attachment path — it reuses `req_scope_gate()`, the existing
> coordinator bar and the existing `candidates.requisition_id` link. The gate
> mutation above proves the reused protection is load-bearing on the one new
> route. Tenant isolation itself is structural in EXAACT (one database per
> customer) and is covered by the existing suite.

## 5. The defect, reproduced before it was fixed

```
BEFORE                                            AFTER
before any hire   status=OPEN                     status=OPEN              REMAINING=5
1st person joins  status=HIRED   ← closed          status=PARTIALLY_FILLED  REMAINING=4
2nd person joins  status=HIRED                     status=PARTIALLY_FILLED  REMAINING=3
3rd person joins  status=HIRED                     status=PARTIALLY_FILLED  REMAINING=2
```

And the §17 example, end to end:

```
cancel the 2 nobody filled  →  status=HIRED  filled=3 cancelled=2 remaining=0
cancel one more             →  refused: "Only 0 vacancies are still open."
summary                     →  10 requested — 6 filled — 4 cancelled — 0 remaining
```

## 6. A correction this milestone forced

Driving three hires against one requisition showed that `hired_inspector_id`
holds the **most recent** hire, not the first: the hire action overwrites it
every time. Our own earlier documentation said "only ever the first". That was
wrong, and `M2-MULTI-VACANCY-BOUNDARY.md` and `M2-COMPLETION-REPORT.md` are
corrected. It matters for anything reading the column expecting the original
hire, and for how it is described now that it is preserved.

## 7. Fresh install and existing data (§29, §30)

- **Fresh install:** a database that has only been booted now carries
  `quantity`, `start_date`, `department_id`, `position_id` and the fulfilment
  columns — **79 columns**, not 25. Requisition functionality no longer depends
  on somebody opening another page first.
- **Existing data:** the full suite runs against a populated database on both
  engines; requisitions, candidates, pipeline links, offers, joinings and the
  Operations integrations all continue to pass.
- **Idempotency:** the migrations are run a second time inside the test, and add
  no column and change no data.

## 8. Regression (§41)

The full suite covers Operations, Quality, Reporting, Money, Sales, Recruitment,
Workforce, Marketplace, Connect, Identity, Organisation, Dashboard, Permissions
and Entitlement, and passes in full on both engines.

The only non-M3 change was regenerating `deploy-check.php`, which the suite's own
checksum test requires after any source change — it caught a stale manifest
mid-milestone and was re-run.
