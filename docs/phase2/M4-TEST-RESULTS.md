# Phase 2 · M4 — Test Results

## 1. Environment (§45)

| | |
|---|---|
| PHP | 8.4.19 |
| Engine 1 | SQLite (bundled) |
| Engine 2 | MariaDB 10.11.14, over TCP |
| New file | `tests/test_m4_hiring_request.php` — **78 assertions** |
| New file | `tests/test_m4_correction.php` — **79 assertions** (the correction) |

## 2. Results

### Original M4 (commit 9b0b7ef)

| Engine | Passed | Failed | Skips |
|---|---|---|---|
| SQLite | 8709 | 0 | none introduced |
| MariaDB 10.11.14 | 8710 | 0 | none introduced |

### After the correction (commit 1110fdf) — both engines, identical source

| Engine | Passed | Failed | M4 correction suite | Skips |
|---|---|---|---|---|
| SQLite | **8816** | **0** | 106 assertions, 0 failed | none introduced |
| **MariaDB 10.11.14** (authoritative) | **8817** | **0** | **106 assertions, 0 failed** | none introduced |

The MariaDB run used a **freshly created database** (`exaact_m4d`), so every
migration ran from nothing. The correction suite was confirmed to have actually
executed there — its four sections (A terminology, B requestor authorization,
C direct requisition path, D every mutation path) appear in the MariaDB output,
and its assertions were counted inside that section on both engines: **106 on
each**. MariaDB's total is one higher than SQLite's because of a pre-existing
engine-specific assertion, unchanged by this work.

> These are **assertions, not test cases**. The 106 correction assertions cover
> roughly 40 scenarios; M4's own 78 cover roughly 30.

An earlier MariaDB run returned 8790/0 but **started before section D existed**,
so it is not counted and is recorded here only to say why.

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

## 3b. What the CORRECTION tests cover

`tests/test_m4_correction.php` — **79 assertions** in three groups.

| Group | What is held in place |
|---|---|
| **A · terminology** | the three locked words; a workspace's own wording honoured and qualified only when ambiguous ("Requirement" → "Recruitment Requirement"); no bare `Requirement` object label on the hiring request, hiring request list or requisition form screens; no unexplained "Requirements" in the navigation; the hiring request layer reachable from the navigation at all; `hiring_requests`, `requisitions` and `cx_requirements` all still separate; **no `requirements` table**; one additive nullable link column |
| **B · requestor authorization** | the capability opens the door and the role name does not (a junior role holding `mod.hiring.edit` may raise; the role literally named **Coordinator** without it may not); the old `is_coordinator_level()` band is **wider than the permission matrix** and the capability refuses where it allowed; VIEW and CREATE are separate rights; **no new permission** — both are already in the catalogue; no role-name check survives in the request layer; create does not confer decide; **the requestor may not decide their own request**, refused at the helper with nothing written; the master exception is to segregation **only** — an unbought module still refuses a master; the right is asked inside `hreq_save/submit/cancel/to_requisition/decide`, so a direct call cannot bypass the route; the route asks capability, then scope; **branch scope proved behaviourally** — another branch's user, holding every right there is to hold, is refused edit, submit, decide, cancel, convert and the record by direct URL; a partial update cannot erase the branch or the requestor |
| **C · direct requisition path** | the direct route still exists and its gate is unchanged; a directly-raised requisition has a NULL link and M3 fulfilment reads it unchanged; `hiring_request_id` is **not browser-settable** and the requisition save path never writes it; **exactly one** function in the application writes it; an unapproved request still cannot become a requisition; the requisition screen names its provenance |

## 4. Mutation testing (§44 + the correction)

Each mutation was applied to the **live source**, the guard suites re-run, the
source restored from a byte-for-byte backup, and a clean baseline re-confirmed.
The harness counts a suite that dies without printing a result as a **detection**,
never as a silent zero.

### 4a. The eight named mutations from §44 (original M4)

| # | Protection removed | Expected | Actual | Verdict |
|---|---|---|---|---|
| **M1** | branch scope on the incoming position / branch | refused | 1 failed | **CAUGHT** |
| **M2** | department identity validation | refused | 1 failed | **CAUGHT** |
| **M3** | **the executable-state boundary** | recruitment refused from an unapproved request | 9 failed | **CAUGHT** |
| **M4** | HR entitlement on the new routes | routes refused | 3 failed | **CAUGHT** |
| **M5** | master bypasses HR entitlement | master still refused | 3 failed (+4 in the Phase-1 security suite) | **CAUGHT** |
| **M6** | uniqueness of the request number | refused | 1 failed | **CAUGHT** |
| **M7** | the request → requisition link | link preserved | 8 failed | **CAUGHT** |
| **M8** | the frozen approval snapshot | snapshot frozen | 3 failed | **CAUGHT** |

### 4b. The correction battery — re-run in full against the corrected code

| # | Protection deliberately removed | Expected | Actual | Verdict |
|---|---|---|---|---|
| **1** | the requestor capability check in `hreq_save()` | a user without `mod.hiring.edit` is refused | 3 failed (`m4_correction`) | **CAUGHT** |
| **2** | the branch-scope **decision** (`hreq_scope_reason()` always allows) | another branch's request refused by URL and on the way in | 2 failed | **CAUGHT** |
| **2b** | the branch-scope **route gate wrapper** only | *expected to survive* | 0 failed | **SURVIVED — accepted, see below** |
| **3** | module entitlement (`hreq_can_view()` always true) | an unbought module refuses, master included | 2 failed | **CAUGHT** |
| **4** | a master bypass inserted **ahead of** the capability | the licence is still asked first | 1 failed | **CAUGHT** |
| **5** | the executable-state guard in `hreq_to_requisition()` | recruitment refused from an unapproved request | 1 + 9 failed (both suites) | **CAUGHT** |
| **6** | the request → requisition link (written as NULL) | provenance preserved | 8 failed | **CAUGHT** |
| **7** | the approval snapshot (written empty) | snapshot frozen at submit | 3 failed | **CAUGHT** |
| **8** | the direct requisition route (blocked outright) | the direct path still works | 1 failed | **CAUGHT** |
| **9** | segregation of duties in `hreq_decide()` | the requestor cannot decide their own request | 3 failed | **CAUGHT** |
| **10** | write-time branch scope (`hreq_in_scope()` always true) | every write refuses out-of-branch | 5 failed | **CAUGHT** |

**Ten of eleven caught. One survived, and the distinction matters.**

### Why mutation 2b survived, and why that is not a failed security test

A mutation that survives is only acceptable if the protection it removed was
**redundant**, and that has to be shown rather than asserted. Here it is shown
three ways:

- Mutation **2** removes the scope **decision** — the thing the gate actually
  asks. It is **CAUGHT**.
- Mutation **10** removes the **write-time** scope check. It is **CAUGHT**.
- The **read** path asks the question again where the record is read, so a
  bookmarked URL to another branch's request is refused even with the gate gone.

Mutation 2b removes only the outer route wrapper. Nothing is unprotected as a
result, because reads and writes each ask independently — which is precisely
what the correction changed, after the **original** version of this mutation
(removing the gate when it *was* the only check) survived and exposed the gap.
That earlier survival was a genuine finding and is recorded as such; this one is
the redundancy that replaced it.

The rule this table follows:

> **CAUGHT** = the mutation removed a protection and a test failed.
> **SURVIVED (accepted)** = the mutation removed a *duplicate* of a protection
> that is independently enforced and independently mutation-proved elsewhere.
> Any other survival is a coverage gap and is fixed, not explained.

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
