# Phase 2 — `requisitions.quantity`: resolving the M1 / M2 contradiction

**Raised because M1 stated the column does not exist and M2 stated it does.**
Both statements were wrong, in different ways. No schema and no behaviour was
changed in resolving this — only the documents.

## 1. The answer

`requisitions.quantity` is a **lazily created column**. It is not in the table's
`CREATE TABLE`, and it is added the first time any requisition screen is opened.

| Where | Column present? | Column count |
|---|---|---|
| Base `CREATE TABLE` (`lib/ops.php:247`) | **no** | 17 |
| Fresh database, straight after `boot()` | **no** | 25 |
| Same database, after `req_migrate()` | **yes** | 78 |
| The MariaDB database the full test suite ran against | **no** | 25 |
| Any installation where Requisitions has been opened | **yes** | 78 |

Verified on both engines, not inferred.

## 2. Its definition and origin

```php
// lib/recruit.php:44 — inside req_migrate()
['quantity','INT DEFAULT 1'],
```

Applied by `foreach ($cols as $c) ensure_column('requisitions', $c[0], $c[1]);`
at `lib/recruit.php:78`.

- **Introduced:** commit `b963490`, 2026-08-27 — the same commit that introduced
  `req_migrate()` itself.
- **Definition as created:** `quantity INT DEFAULT 1` (confirmed on SQLite via
  `PRAGMA table_info`; the MySQL path uses the same `ensure_column` call).
- **Not gated by any module or licence check.** `req_migrate()` guards only on
  `db_epoch()` and on `ensure_column()` existing.

## 3. Why it is absent in a fresh or test database

`req_migrate()` runs lazily, from the routes that need it:

| Caller | Context |
|---|---|
| `lib/ops.php:4937` | `ops_requisitions()` — every requisition list / new / edit route |
| `lib/projcosting.php:346` | before a costing raises a requisition |
| `lib/recruit.php:693, 711, 726` | recruitment screens |
| `lib/seed_recruit_cc.php:16` | the Command-Centre demo seed |

No test exercises a requisition route, so the suite's database never reaches
any of these calls — which is why the MariaDB test database has 25 columns.
The column's absence there is an artefact of what the tests touch, **not**
evidence about the product.

## 4. The one path that can create a requisition before the column exists

`lib/seed_demo.php:152` inserts requisitions with an explicit base-column list
and does **not** call `req_migrate()` first. So demo-seeded rows can exist while
`quantity` does not.

That window closes the moment anyone opens `/requisitions`: `req_migrate()` adds
the column with `DEFAULT 1`, and those existing rows read as 1 — which is the
right answer for a demo requisition anyway.

## 5. What each document got wrong

### M1 (`00-PHASE2-BASELINE.md`)

**Observation: correct.** "25 columns, none of them a quantity" is exactly what a
fresh database contains. M1 inspected honestly and reported what it saw.

**Conclusion: wrong.** M1 treated that as a permanent structural absence and
wrote that the fix requires *adding* a quantity column ("It is additive to fix:
a quantity column…"). The column already exists in the product. M1 inspected a
database that had never had a requisition screen opened, and generalised from it.

Two further consequences:

- M1 said `recruit_req_health()` reads `$req['quantity']`, "so `$qty` silently
  falls back to `1` for every requisition ever raised." That is wrong for any
  real installation. It can only be true in a database seeded by `seed_demo.php`
  where Requisitions has never been opened — and opening it fixes it.
- M1 said `start_date` is "also absent". It is created by the same migration
  (`lib/recruit.php:48`), so the same correction applies.

### M2 (`M2-MULTI-VACANCY-BOUNDARY.md`, `M2-JOB-STRUCTURE-MAP.md`)

**Substance: correct.** The multi-vacancy closure defect is real and unchanged:
`lib/ops.php:5178` sets a single `hired_inspector_id` and a terminal
`status='HIRED'` on the first hire, whatever the quantity.

**Phrasing: wrong.** M2 wrote "`requisitions.quantity`" as an unqualified schema
fact without noting that it is lazily created, and without reconciling that
against M1's explicit statement to the contrary. The contradiction should have
been caught when M2 was written; the M2 audit read the same 25-column dump and
did not follow it through to `req_migrate()`.

## 6. Effect on the multi-vacancy defect

None. The defect stands exactly as M1 described it and as
`M2-MULTI-VACANCY-BOUNDARY.md` documents it. If anything it is slightly worse
than M1 implied: because `quantity` *does* exist and *is* editable on the
requisition form, a user can genuinely ask for 10 people — and the closure rule
will still mark the requisition `HIRED` after the first one.

M2 changed none of this, as instructed.

## 7. Effect on the M2 test results

None. The M2 suite asserts the presence of `hired_inspector_id` and
`position_id` — both base columns, present in every database. It never asserted
anything about `quantity`, so no result changes. A regression test has been
added (`tests/test_m2_org_structure.php`) pinning the lazy-creation behaviour, so
this contradiction cannot recur silently.
