# Milestone 7 — Report / Export Entitlement Enforcement

**Status:** complete · **Suite:** 7,675 passed, 0 failed · **Baseline:** c2f6f8f (M6)

---

## 1. What this milestone was for, in plain language

An export is a door with a different handle.

M5 guarded the screens. M6 guarded the portals and the actions. M7 asks the last
question: **can a company download data belonging to a module it has not bought?**

Most of the answer was already yes-it-is-guarded — export routes travel through
the same gate as the screens they belong to, which is why `quotes-export`,
`recruit-export`, `tally-export`, `document-pdf` and their siblings were already
refused. M7 is about the three places where that was not true, and all three
share a single cause: **a report whose route says one thing and whose contents say
another.**

The instruction warned about exactly this — *do not assume that every report
belongs to the Reporting product.* The inverse turned out to matter more: **do not
assume that a report mapped to a core route contains only core data.**

---

## 2. The three findings

### F1 · Project costing — a Sales/HR sheet nobody had to buy

A costing sheet prices a manpower quotation (Sales) or budgets the roles a
requisition must fill (HR). It is deliberately module-neutral between those two.

Its guard was:

```php
can('mod.quotes.view') || can('mod.hiring.view') || can('mod.inquiries.view')
    || is_admin_level() || is_master()
```

The three `can()` terms ask the licence properly. The last two do not — and the
entire `project-costing` family is **absent from the route map**, so the M5 gate
passed it through with nothing to check. A company with neither Sales nor Hiring
could open **and print** client names, cost build-ups, margins and sell rates.

**Closed** by requiring at least one owning module to be live, in `pc_can()`,
`pc_can_edit()` and `pc_can_approve()`.

**The export inherits it for free.** `ops_projcosting()` requires `pc_can()`
before it dispatches any of its routes, `/project-costing-print` included. That
satisfies §11 structurally rather than by remembering to repeat a check: there is
no arrangement in which the screen refuses and the printable sheet does not.

### F2 · The MIS export — Money columns on a core report

`/mis` maps to the **core** `reports` module, so every company reaches it, which
is right: jobs, man-days and revenue are Operations data.

But the report also emits **Engineer time, Expenses, Sub-contractor, Total direct
cost, Profit and Margin %** — the profitability product — behind
`$seeSalary = can('data.salary')`. `data.salary` is an RBAC permission, not a
module permission, so it never asked whether the company has Money.

**Closed** at `mis.php` line 147, where `$seeSalary` is computed **once** and
carried in `$S['seeSalary']` to both `views/ops/mis.php` and the CSV writer. One
value, both doors: the screen and its export cannot disagree about which columns
exist. The Operations content of the report is untouched.

### F3 · Analytics — ten metrics that read Inspection reporting

`/analytics` and `/analytics-export` also map to the core `reports` module, and
`tapi_can()` is built from `dash.*` permissions, none of which is a module
permission. The export writes CSV or XLSX.

Of the twenty registered metrics, **ten read `idems/report_docs`** — report
counts, turnaround, review state, release status. A company with no Reporting
subscription could export all of them.

**Closed using evidence the engine already carries.** Every metric declares its
lineage source (`ops/jobs`, `idems/report_docs`, `ncr/nonconformities`…) — that is
not new, it is the data-lineage promise TAPI was built on. So ownership is *read*
rather than invented, and no second ownership model is created:

```
metric → its declared source → access module → product module → entitlement
```

The check sits in `tapi_metric_value()`, the one function every metric resolves
through. A metric belonging to a module that is not live returns **NO DATA** —
the engine's own existing vocabulary for "there is no number here", distinct from
a real zero. Nothing about TAPI's architecture changes.

**A detail worth recording:** `revenue.invoiced` is labelled FINANCE, but its
lineage is `ops/jobs` — it sums `jobs.invoice_amount`, which is Operations data.
The label does not decide; the lineage does. Classifying it by its label would
have wrongly withheld an Operations metric from Operations customers.

---

## 3. What was already correct, and left alone

Reported because "we checked and it was fine" is evidence too:

- **Export routes generally.** 31 of the 34 report/export routes resolve to an
  owning module through the M5 map or the paid-family table, and were already
  enforced. See `M7-REPORT-EXPORT-MATRIX.md`.
- **The client portal's report PDF download** sits behind `portal_need('reports')`
  → `pcan('reports')`, which M6 made entitlement-aware. No change needed.
- **`/verify` and `/verify-pdf`** are public by design: a client checks a report
  they already hold, using its printed code. "Verify it yourself" cannot sit
  behind a password, and it exposes nothing confidential.
- **The CERT-In incident report** (`incident-report`) is unmapped, and correctly
  so — reporting a security incident is a legal obligation under the DPDP regime
  that every installation must be able to discharge. **Core.**
- **The workspace backup download** is a company exporting its **own** data under
  core administration rights. **Core.**

---

## 4. What was NOT built

- **No new reporting system**, no second report engine, no duplicated report
  definitions, no changed report storage, no dashboard redesign, **no change to
  TAPI's architecture**.
- **No new entitlement system.** No `report_entitled()`, `export_entitled()`,
  `can_export_module()` or `report_subscription()`. Everything routes through
  `licence_module_live()` → `licence_blocks()` → the existing registry.
- **No schema change.** No report-entitlement table, no duplicated ownership, no
  data migration.
- **No new error framework.** Denials use the existing conventions and name only
  the module; a withheld metric is NO DATA, which the engine already renders.
- **No M8 work.** Cron, background jobs, public sign-up, public routes,
  Marketplace and Connect remain untouched and are listed as deferred.

---

## 5. Performance

Entitlement is asked at the authorisation boundary, never per row:

- **F1** — once per request, in `pc_can()`.
- **F2** — once per report, at `mis.php:147`; the CSV writer reads the value.
- **F3** — once per metric per report (twenty at most), in front of a single
  scoped aggregate. Not inside any loop, row or exported record.

---

## 6. Files changed

| File | Change |
|---|---|
| `lib/projcosting.php` | `pc_modules_live()`; `pc_can()` / `pc_can_edit()` / `pc_can_approve()` ask it first |
| `lib/mis.php` | `$seeSalary` asks for the profitability module before the salary permission |
| `lib/tapi.php` | `TAPI_SOURCE_MODULES`, `tapi_metric_module()`, `tapi_metric_live()`; `tapi_metric_value()` returns NO DATA for an unlicensed metric |
| `tests/test_m7_report_export_entitlement.php` | new — 108 assertions |
| `deploy-check.php` | checksums regenerated |

**Files protected and untouched:** `lib/access.php`, `lib/licence.php`,
`lib/ops.php` (the gate), `lib/idems.php` and the report engine, `lib/portal.php`,
`lib/cvp.php`, `lib/receivables.php`, `lib/recruit.php`, `lib/entitlement_migrate.php`,
`lib/saas_tenants.php`, `lib/tapi_dash.php`, `lib/tapi_gov.php`, `lib/tapi_score.php`,
`views/dashboard.php`, `index.php`.
