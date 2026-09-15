# Phase 2 · M2 — Completion Report
### Organisation, Department, Designation & Job Structure

## 1. What was asked, and what happened

M2 asked for an audit first, then only the changes the audit proved necessary.
The audit found that **EXAACT's organisation model was already right**. No new
table was needed and none was created. What was wrong was narrower and more
interesting: the model was being *read* inconsistently, so the software
contradicted its own data.

Five files changed. No schema change. No data migration. No stored value
rewritten.

## 2. The audit (§3)

| Area | Finding |
|---|---|
| Organisation | `offices` already nests (`parent_office_id`), with type, region and a head. Company → Office → Department → Position → Person is complete. |
| Department | 8 tables carry a `department` column. **No `departments` table exists** — the authoritative master is `lookup_values` type `department` (17 values, system list). |
| Designation | 13 tables carry a designation-like column. The master is `lookup_values` type `designation` (19 values, system list). Designations already link to a department via `attr_department`. |
| Job structure | `positions` (sanctioned/occupied/budgeted headcount, reporting line) → `requisitions.position_id` → `candidates.requisition_id`. All links already present, plus a five-case manpower check. |
| Existing libraries | `deptorg.php`, `orgadmin.php`, `organogram.php`, `position.php` already implement the department hub, office tree, org-chart import and position master. Reused, not replaced. |

Also worth recording: `designation` means three different things across the app
(`users.position_title` = a serving employee's title; `requisitions.designation`
= the title being recruited for; `inspectors.designation` = a workforce title),
and `cx_materials.grades` is *steel grades* — nothing to do with jobs. Keeping
these apart is why §4's distinctions matter.

## 3. What was fixed

### (a) One department no longer appears as several
Three shipped screens wrote the same column differently — some the code
(`QUALITY`), some the label (`Quality`). Grouping on the raw value split one
real department in two: one row held the positions and the sanctioned
headcount, the other held a person.

Reproduced first, then fixed:

```
BEFORE                                   AFTER
[Quality]  positions=1 sanctioned=3      [Quality]  positions=1 sanctioned=3
           people=1                                 people=2
[QUALITY]  positions=0 sanctioned=0
           people=1
```

`dept_canon()` resolves a stored value to the one label the master already
defines for it. It is **read-side only** — nothing stored is rewritten, and a
department the master does not recognise passes through untouched, so free text
typed before today cannot be lost.

### (b) Settings changes now actually reach the screens
The back-office staff form and the Recruitment Command Centre read a frozen PHP
constant rather than the live master. A designation added in Settings never
appeared on that form and rendered as a raw code in the Command Centre. Both
now read the master, with the constant kept only as the fallback for a
workspace whose lookups are not yet seeded.

### (c) A cross-tenant leak in the master accessor — found while testing
This was not on the M2 list. Three tests failed in a way that did not fit the
explanation, so it was investigated rather than worked around.

`lk_options_or()` cached its list on the list key alone. EXAACT gives each
workspace its own database and swaps that database *inside* a request, so the
cache outlived the database it was read from:

```
Tenant A reads:                                INSPECTOR = Site Inspector
Tenant B really has (from its own database):   INSPECTOR = Inspector
Tenant B was SHOWN:                            INSPECTOR = Site Inspector   ← leak
```

Fixed by keying the cache on `db_epoch()` — the convention `db()` itself and
dozens of other caches already follow. `trade_options()` had the identical flaw
and was fixed the same way.

**Stated accurately:** this exposed *configured dropdown labels*, not customer
records, invoices or people, and needed two workspaces touched in one PHP
process. It is a genuine multi-tenancy defect, now closed — but it was not a
customer-data breach and is not being reported as one.

### (d) The Position master's Department box
Was a bare text input, which is where much of the free-text drift began. It is
now steered by the master list while still accepting anything already typed —
the same pattern the team-member form already used.

## 4. Testing

| Engine | Result |
|---|---|
| SQLite | **8317 passed, 0 failed** |
| MariaDB 10.11.14 | **8318 passed, 0 failed** |

Whole suite, both engines. M2 adds 31 assertions (~12 scenarios), green on
both. Every fix was mutation-tested — reverted one at a time, and each
mutation was caught (6, 5, 1 and 1 failures respectively). No test was weakened
and no skip was introduced. Details in `M2-TEST-RESULTS.md`.

## 5. STOP — one decision is yours, not mine

Two department vocabularies exist: `department` (17 values, used by people,
positions, the org chart and approvals) and `hr_department` (8 values, used by
requisitions and candidates). Five of the recruitment codes have no equivalent
in the authoritative master:

```
QAQC      NDT      HSE      HR      FINANCE
```

Three look like the same department under another name — **is "QA / QC" the
same department as "Quality"? Is "HSE / Safety" the same as "Safety / HSE"?
Does "Finance" belong inside "Commercial / Finance" or stand alone?** — and two
(`NDT`, `HR`) are genuinely new.

That is a judgement about how your company is organised, not a technical
question. Acting on it means rewriting stored department values on live
requisitions and candidates. M2's stop conditions cover exactly this, so it was
reported rather than improvised.

**Consequence while it stands:** a requisition's department cannot be compared
with a position's department, so requisitions do not roll up under their
department in the Department hub.

**My recommendation:** make `department` the single master; add `NDT` and `HR`;
map `QAQC → QUALITY`, `HSE → SAFETY`, `FINANCE → COMMERCIAL` in one reversible
migration with a dry-run report first; then point the requisition and candidate
forms at it and retire `hr_department`. That is a self-contained milestone. It
needs your answer on the three mappings before it can start.

## 6. Explicitly not done

- **Multi-vacancy closure** — documented in `M2-MULTI-VACANCY-BOUNDARY.md`, not
  fixed, as instructed. `hired_inspector_id`, `status='HIRED'`, quantity, fill
  count and closure rules are all untouched, and the tests assert it.
- **Sales pipelines** (`pipelines` / `pipeline_stages`) and **Recruitment
  pipelines** (`recruit_pipelines` / `recruit_stages`) — untouched; still the
  only engines. No second pipeline, approval or identity engine was created.
- **Candidate ↔ agency relationships** — untouched.
- **Requisition field inheritance from Position** — blocked behind the decision
  in §5; forcing it now would reject valid data or silently rewrite it.
- **A `grade` master** — `positions.grade` and `requisitions.grade` are free
  text and no grade master is registered. Creating one is a new master, not a
  reuse, and the audit did not prove it necessary. Recorded as an open item.
- No Phase 3/4/5 work. No unrelated refactoring.

## 7. Phase 1 status — unchanged

Phase 1 is **still not formally closed**. MilesWeb production UAT remains
pending, as do: which build is actually live there (the Up-to-date / Stale /
Missing counts from `/deploy-check.php` have still not been captured), the
config/database incident on that host, and e-mail, which has never been tested
anywhere. See `docs/phase1/PHASE1-PENDING-WORK.md`.

## 8. Files changed

| File | Change |
|---|---|
| `lib/deptorg.php` | `dept_canon_map()` / `dept_canon()` added; applied at 4 grouping sites |
| `lib/lookups.php` | `lk_options_or()` and `trade_options()` caches keyed on `db_epoch()` |
| `lib/ops.php` | Back-office staff form + list read the live masters |
| `lib/recruit_cc.php` | `rcc_designations()` added; 2 label lookups use the live master |
| `views/ops/positions.php` | Department steered by the master (datalist) |
| `tests/test_m2_org_structure.php` | New — 31 assertions |
| `deploy-check.php` | Regenerated (the suite's checksum test required it) |
| `docs/phase2/*` | This set of six documents |

## 9. Next

M3 has **not** been started, per instruction. M2 stops here.
