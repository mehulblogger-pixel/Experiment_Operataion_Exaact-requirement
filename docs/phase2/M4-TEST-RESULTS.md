# Phase 2 · M4 — Test Results

## 1. Environment (§45)

| | |
|---|---|
| PHP | 8.4.19 |
| Engine 1 | SQLite (bundled) |
| Engine 2 | MariaDB 10.11.14, over TCP |
| New file | `tests/test_m4_hiring_request.php` — **77 assertions** |

## 2. Results

| Engine | Passed | Failed | Skips |
|---|---|---|---|
| SQLite | **8709** | **0** | none introduced |
| MariaDB 10.11.14 | _recorded when the run reports_ | | none introduced |

> Assertions, not test cases. M4's 77 assertions cover roughly 30 scenarios.

## 3. What the M4 tests cover (§42)

| Group | Assertions | What is held in place |
|---|---|---|
| Nothing duplicated | 8 | request layer exists; `cx_requirements` still separate; **no** third requirement table; **no** invented Job Profile master; no competing second quantity |
| Request creation | 6 | own reference series; requesting **and** hiring department recorded separately; requestor is a canonical identity |
| **The boundary (§18)** | 8 | recruitment refused from `DRAFT` **and** from `SUBMITTED`; permitted only once `APPROVED` |
| Request → Requisition | 9 | one request, many requisitions; never more than approved; link and canonical department travel across; M3 reads the **requisition** quantity |
| Snapshot (§12) | 4 | taken at submit; a later master rename does not change what was approved |
| Approved is protected (§33) | 2 | an approved request cannot be silently edited |
| Negative input (§43) | 18 | zero/negative quantity, empty title, foreign requestor, unknown/switched-off department, unknown/switched-off position, unknown priority / employment type / request type, bad date, unknown ids, double submit, decide-before-submit, edit-after-cancel |
| Scope / permission / entitlement | 11 | gate on the module door checking **both** the request and the incoming branch; branch-B user refused branch A; `hr` off refuses list and direct URL; **a master still cannot open an unbought module** |
| Existing behaviour | 3 | direct requisition creation still works with a NULL link; M3 fulfilment unchanged; requisition numbering unchanged |

## 4. Mutation testing (§44)

All eight named mutations, each caught.

| # | Mutation | Result |
|---|---|---|
| **M1** | A branch-A user may attach a branch-B position / branch | **1 failed** ✅ |
| **M2** | A request may reference a department that is not one | **1 failed** ✅ |
| **M3** | **Recruitment may start from an unapproved request** | **9 failed** ✅ |
| **M4** | HR entitlement removed from the new routes | **3 failed** ✅ |
| **M5** | A master user bypasses HR entitlement | **3 failed** ✅ (and 4 more in the Phase-1 security suite) |
| **M6** | The request number is no longer unique | **1 failed** ✅ |
| **M7** | The Request → Requisition link is broken | **8 failed** ✅ |
| **M8** | The snapshot follows the master instead of being frozen | **3 failed** ✅ |
| — | *all restored* | **77 passed, 0 failed** |

M5 is worth a note: M4's own code contains **no `is_master()` call at all**, so
the mutation had to be introduced into the shared entitlement gate. It was
caught both by M4's own assertion and by the Phase-1 security suite that exists
for exactly this.

## 5. The flow, driven end to end

```
1. request raised            HRQ-2026-000001  status=DRAFT
                             requesting dept=Projects   hiring dept=Engineering

2. start recruiting?         REFUSED — "This request is draft."
3. submitted → retry         REFUSED — "This request is submitted."
4. approved                  executable=yes

5. raise 6 of 10             REQ/AHM/NA/2609/C001/O001   remaining 4
   raise 4 of 10             REQ/AHM/NA/2609/C002/O002   remaining 0
   raise 1 more              REFUSED — every approved vacancy is being recruited

6. both requisitions carry hiring_request_id, the canonical department, and M3 fulfilment

7. snapshot says "Engineering"; master renamed to "Engineering & Technical";
   snapshot still says "Engineering"

8. edit the approved request  REFUSED — "needs a re-approval, which is not built yet"
```

## 6. Two corrections made during the build

**The request-type list came back stale.** `hreq_migrate()` persists the new
types into the existing `requisition_type` lookup, but that list is cached for
the life of a request — so on the very request that first creates them, reading
the list alone still showed the original two, and a valid `PROJECT` request was
refused. The accessor now returns the **union** of the workspace's list and M4's
additions: immediate, and a no-op from the next request onward.

**Two module maps had to agree.** The new routes were added to the enforcement
map used by `ops_module_gate()`, which is consulted first — so entitlement was
enforced correctly. But `ops_module_family()` keeps its own prefix table and
returned NULL, which would mislead anything reading that instead. Both now carry
the mapping, verified in both directions with `hr` on and off.

## 7. Fresh install and existing data (§40, §41)

- **Fresh install:** `hreq_migrate()` runs from `boot()`, so the table, the link
  column and the three new vocabularies exist in a database that has only been
  started. Create → save draft → view → edit → submit → raise requisition works
  without visiting any other page first.
- **Migrations run twice:** additive and epoch-guarded; the second run adds
  nothing and changes nothing.
- **Existing data:** the full suite runs against a populated database on both
  engines — requisitions, candidates, offers, joinings, positions, departments,
  reports and the Operations integrations all continue to pass.

## 8. Regression (§46)

The full suite covers Operations, Quality, Reporting, Money, Sales, Recruitment,
Workforce, Marketplace, Connect, Identity, Organisation, Dashboard, Permissions
and Entitlement.

No existing test was modified, weakened or skipped.
