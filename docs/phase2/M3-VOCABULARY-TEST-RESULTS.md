# Phase 2 · M3 — Test Results

## 1. Environment

| | |
|---|---|
| PHP | 8.4.19 |
| Engine 1 | SQLite (bundled) |
| Engine 2 | MariaDB 10.11.14 (`mysql_native_password`, TCP) |
| Suite | `php tests/run.php` — the whole suite, not a subset |
| New file | `tests/test_m3_department_vocabulary.php` — **100 assertions** |

Figures are filled in from the runs below; nothing here is claimed for an engine
that was not actually exercised, and no figure is predicted from another run.

> Both figures below are from one run against the final tree, not carried over
> from an earlier run. (For the record, the runs before the last two assertions
> were added reported 8420/0 and 8421/0 — the +2 is exactly those assertions.)

## 2. Results

| Engine | Passed | Failed | Skips |
|---|---|---|---|
| SQLite | **8422** | **0** | none introduced |
| MariaDB 10.11.14 | **8423** | **0** | none introduced |

The one-assertion difference is engine-specific coverage that only runs under
MySQL. It is not a test skipped on SQLite.

> **Assertions, not test cases.** The 98 figure counts assertions. One scenario
> usually costs several (create a department, then assert its code, its type, its
> active flag and that it survives deactivation = four assertions from one
> scenario). M3's 98 assertions cover roughly 40 distinct scenarios.

## 3. What the M3 tests cover (§39)

| Group | Assertions | What is held in place |
|---|---|---|
| Foundation | 11 | The term layer exists; **no competing `departments` table**; hierarchy reuses the column that already existed |
| Normalisation | 6 | Case, spacing, punctuation, `&`; `H.R.`≡`H R`≡`HR`; a punctuation-only string is rejected |
| Canonical CRUD | 7 | Create, read, update, deactivate, **reactivate**; switching off never deletes |
| Identity vs wording | 3 | Renaming the display name leaves the identity and the canonical name untouched |
| Matching priority | 8 | L1/L2/L3 resolve; L4/L5 only suggest; L6 unknown; a suggestion never resolves |
| Conflict | 5 | One term cannot mean two departments; the original mapping survives the attempt |
| Hierarchy | 7 | Nesting works; self-parent, child-as-parent and circular saves all refused |
| Duplicates | 5 | Near-duplicate name held then confirmable; duplicate **code always refused** |
| Negative input | 10 | Empty, spaces-only, punctuation-only, unknown id, very long, and a designation must not resolve as a department |
| Legacy values | 6 | QAQC/NDT/HR/HSE/FINANCE recorded or already resolving — **never guessed** |
| Approve / reject | 7 | Pending resolves to nothing; approval makes it resolve and is stamped; rejection never resolves |
| The three fixes | 12 | Approval routing, public rendering, entitlement on the establishment |
| Permissions | 9 | Field user / coordinator / admin; the admin guard runs **before** any edit action |
| Old entry points | 2 | A department added through Settings → Masters still resolves and renders as its name |
| Tenant cache | 2 | Cached within a request; rebuilt when the database changes underneath it |

## 4. Mutation testing (§39)

Every guard was broken deliberately, one at a time, to prove the tests are
load-bearing rather than decorative.

| # | Mutation | Result |
|---|---|---|
| 1 | Conflict refusal removed — one term could mean two departments | **2 failed** ✅ |
| 2 | A suggestion allowed to resolve — fuzzy overrides approval | **1 failed** ✅ |
| 3 | Cycle prevention disabled | **4 failed** ✅ |
| 4 | Duplicate detection disabled | **3 failed** ✅ |
| 5 | Vocabulary cache no longer keyed on the database epoch (tenant leak) | **1 failed** ✅ |
| 6 | Entitlement guard on the establishment removed | **1 failed** ✅ |
| 7 | Approval matching no longer follows the canonical department | **1 failed** ✅ |
| 8 | Admin guard removed from the edit actions | **2 failed** ✅ |
| 9 | Term reconcile reverted to first-run-only (a department added the old way would never match) | **2 failed** ✅ |
| — | *all restored* | **100 passed, 0 failed** |

No mutation passed silently. No test was weakened and no skip was introduced.

## 5. Defects reproduced before they were fixed

Nothing was fixed on suspicion.

### 5.1 Approval routing (HIGH)

An offer approval rule scoped to a department never fired on a requisition
raised through the standard form — the rule box is free text, the requisition
stores a coded value from a different list.

```
Rule configured for department: 'Quality'   (typed into a free-text box)

ctx 'QAQC'    (a requisition filed under QA / QC)  -> NO RULE MATCHED
ctx 'Quality' (the same department, master label)  -> MATCHED
```

After M3, a rule matches any **approved** word for the same department, and
still refuses an unapproved one — so nothing routes by guesswork.

### 5.2 Raw codes on the public careers page

`lib/careers.php` made zero calls to any label resolver and rendered the stored
value directly, so an applicant saw `QAQC` instead of `QA / QC`. Now resolved
through `dept_label()`.

### 5.3 A core route exposed paid-module data

`/positions` is gated to the paid **hr** module; `/departments` is not, yet the
hub rolled up positions and sanctioned headcount. Driven through the real
dispatcher with `hr` switched off:

```
BEFORE                                         AFTER
/positions   -> refused                        /positions   -> refused
/departments -> rendered, headcount visible    /departments -> rendered, no establishment data
                position name: YES, "7": YES                   position name: no,  "7": no
                dept_hub sanctioned total: 7                   dept_hub sanctioned total: 0
```

With the module on, everything is shown again — verified in both directions.

## 5.4 A gap found by probing M3's own work

Departments can still be added through **Settings → Masters** and the organogram
importer, neither of which knows about terms. Such a department appeared in the
list but never matched, and displayed its raw code — the exact defect the
milestone exists to remove. The term reconcile now runs on every entry rather
than only the first, repairing any department that lacks a canonical term. Found
by probing, not by assumption; pinned by two assertions and mutation 9.

## 6. A regression the suite caught on M3 itself

The first full run failed one assertion:

```
every table-creating migration is wired into boot() — MISSING: vocab_migrate
```

`lookup_terms` was being created lazily on first use, which works but breaks the
convention the codebase already enforces. Wired into `boot()` immediately after
`lk_migrate()` and re-run. Recorded here rather than quietly fixed: the existing
suite caught an architectural omission in new work, which is exactly its job.

## 7. Manual walkthrough (§40)

All three screens were driven through the real route handler as a logged-in
administrator, not merely unit-tested.

| Screen | Result |
|---|---|
| Department hub | renders; links to the list and to the confirm queue |
| Department list | renders; create form present, including "What your team calls it" |
| Words to confirm | renders; offers exactly `QAQC`, `NDT`, `HR`, `HSE`, `FINANCE` |

**§41 jargon check** — the rendered text of all three screens was scanned for
`canonical_id`, `term_norm`, `term_compact`, `vocabulary_key`, `value_id` and
`lookup_terms`. **None appeared.** A user sees "Department", "Also known as" and
"Words to confirm".

## 8. Regression across existing modules (§49)

The full suite covers Operations, Quality, Reporting, Money, Sales, Recruitment,
Workforce, Marketplace/Connect, Identity, Organisation, Dashboard, Permissions
and Entitlement. It passes in full on both engines.

No existing test was modified, weakened or skipped. The only non-M3 change was
regenerating `deploy-check.php`, which the suite's own checksum test requires
after any source change.
