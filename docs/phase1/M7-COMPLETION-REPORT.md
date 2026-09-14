# Milestone 7 — Completion Report

## Verdict: **PASS WITH DOCUMENTED LIMITATIONS**

M7 met every acceptance criterion with a fully green suite, no modified tests and
no schema change. The qualifier records nine boundaries listed in
`M7-KNOWN-LIMITATIONS.md` — three of which (L3, L4) are **visible behaviour
changes for unentitled customers** that are the enforcement working as intended,
and should be a commercial decision rather than a surprise.

---

## 1. Implementation — what changed and why

An export is a door with a different handle. Most were already guarded: export
routes travel through the same M5 gate as the screens they belong to. Three were
not, and all three shared one cause — **a report whose route says one thing and
whose contents say another.**

| # | Finding | Severity | Closed by |
|---|---|---|---|
| **F1** | **Project costing** — a Sales/HR sheet whose guards fell back to `is_admin_level() \|\| is_master()`, on routes absent from the gate map. A company with neither module could open **and print** client names, cost build-ups, margins and sell rates | **High** | `pc_modules_live()` asked first in `pc_can()`, `pc_can_edit()`, `pc_can_approve()` |
| **F2** | **The MIS export** — cost / profit / margin columns (Money · profitability) behind `data.salary`, an RBAC permission that never asked the licence, on a report mapped to the **core** reports module | **High** | `$seeSalary` asks for the profitability module first, at the one place feeding both the screen and the CSV |
| **F3** | **Analytics** — ten of twenty metrics read Inspection reporting, exported as CSV or XLSX from routes also mapped to **core** | **High** | ownership read from each metric's declared lineage; an unlicensed metric returns the engine's existing NO DATA |

**The instruction warned not to assume every report belongs to Reporting. The
inverse mattered more: do not assume a report mapped to a core route contains
only core data.** All three findings are that same mistake.

---

## 2. Report / export inventory — what was found

34 report/export routes, extracted from the source and resolved through the live
registry (`M7-REPORT-EXPORT-MATRIX.md`):

- **24 already enforced** by the M5 route gate — CSV, PDF, print, download and
  template routes across Sales, Money, HR, Reporting and Operations.
- **5 mapped to core** — `reports`, `mis`, `analytics`, `analytics-export`,
  `analytics-review`/`snapshot`. Reaching them is correct; their **contents**
  were the subject of F2 and F3.
- **2 outside the map and paid** — `project-costing`, `project-costing-print` (F1).
- **5 outside the map and genuinely core or public** — the CERT-In incident
  report, compliance, backup download, `verify` and `verify-pdf`.

---

## 3. Entitlement coverage — what is now protected

Every paid export path now resolves through the same chain:

```
route or metric lineage → access module → product module → licence → RBAC → export
```

Direct URLs, direct export URLs, CSV, XLSX, PDF, print and download actions are
all enforced without the user first visiting the report screen — asserted for 14
export routes across all five products.

**Screen and export cannot disagree.** F1 is enforced at the guard
`ops_projcosting()` requires before dispatching anything, the print route
included. F2 is enforced at the single `$seeSalary` value carried to both the
view and the CSV writer. Neither is a check that someone must remember to repeat.

---

## 4. Core exceptions — and why

| Report | Why it stays core |
|---|---|
| **CERT-In incident report** | Reporting a security incident is a legal obligation under the DPDP regime that every installation must be able to discharge |
| **Workspace backup download** | A company exporting its **own** data under core administration rights |
| **The reports hub, MIS, analytics screens** | Core management surfaces; their paid *contents* are enforced individually (F2, F3) |
| **`verify` / `verify-pdf`** | Public by design — a client checks a report they already hold using its printed code. "Verify it yourself" cannot sit behind a password, and nothing confidential is shown |
| **`portal.*` analytics metrics** | Administration, which is core |

Established from code evidence, not assumed, and each asserted by test on the
narrowest possible plan.

---

## 5. Master-user security

Every DENY assertion in the M7 suite runs **signed in as a master** — the hardest
case. Under S-1 a master is refused: the HR export, the Sales export and PDF, the
Money export and print, the MIS profitability columns, and the Sales/HR costing
sheet. The same master is allowed everything Operations and Reporting.

No `is_master()` usage was mass-rewritten. The only master-related change is the
`is_admin_level() || is_master()` fallback in project costing, which is a genuine
M7 bypass.

---

## 6. Cross-tenant testing

Company A entitled to Reporting, company B not. Across a live connection switch,
with no cache reload called on purpose:

| Check | Result |
|---|---|
| B inherits A's metric entitlement | **No** |
| B can export A's report metric value | **No** — NO DATA |
| B can reach A's report PDF route | **No** |
| B's own Operations entitlement applies | **Yes** |

No client-supplied tenant id takes part in any decision.

---

## 7. S-1 — exact results

Operations ON, Reporting ON, HR / Sales / Money OFF, signed in as a **master**:

| Export path | Result |
|---|---|
| Operations — `voucher-csv`, `voucher-print` | **WORK** |
| Reporting — `document-pdf`, `report-template-download` | **WORK** |
| Reporting — analytics metrics | **WORK** — real values |
| HR — `recruit-export` | **DENIED** |
| Sales — `quotes-export`, `quote-pdf` | **DENIED** |
| Money — `tally-export`, `invoice-print` | **DENIED** |
| Money — MIS profitability columns | **DENIED** |
| Sales/HR — project costing screen and printable sheet | **DENIED** |
| Core — reports hub, analytics, MIS | **OPEN** |
| **Reporting remains fully operational** | **Yes** — asserted explicitly |

---

## 8. Focused M7 tests

`tests/test_m7_report_export_entitlement.php` — **108 assertions, 0 failed.**
Every paid export asserted twice, denied and allowed. Mutation-checked: removing
F3 fails 8 assertions, F1 fails 2, F2 fails 2.

---

## 9. Full regression

**7,675 passed · 0 failed · 0 skipped · 455 test files · 98 seconds · PHP 8.4.19.**
Baseline before M7: 7,567. **No existing test was modified, weakened, skipped or
deleted.**

TAPI suites specifically: **170 passed, 0 failed.**

---

## 10. MySQL / MariaDB

**Not available, and not tested.** Verified rather than assumed: no `mysql`,
`mysqld` or `mariadb` binary exists in this environment. **No claim of production
database validation is made.** M7 adds no SQL and no schema change.

---

## 11. Files changed

| File | Change |
|---|---|
| `lib/projcosting.php` | `pc_modules_live()`; the three `pc_can*()` guards ask it first |
| `lib/mis.php` | `$seeSalary` asks for the profitability module before `data.salary` |
| `lib/tapi.php` | `TAPI_SOURCE_MODULES`, `tapi_metric_module()`, `tapi_metric_live()`; `tapi_metric_value()` returns NO DATA for an unlicensed metric |
| `tests/test_m7_report_export_entitlement.php` | **new** — 108 assertions |
| `deploy-check.php` | checksums regenerated |
| `docs/phase1/M7-*.md` | five documents |

**Five source files. Nothing else was touched.**

---

## 12. Files protected — verified untouched

`lib/access.php`, `lib/licence.php`, `lib/ops.php`, `lib/idems.php` and the report
engine, `lib/portal.php`, `lib/cvp.php`, `lib/receivables.php`, `lib/recruit.php`,
`lib/careers.php`, `lib/entitlement_migrate.php`, `lib/saas_tenants.php`,
`lib/tapi_dash.php`, `lib/tapi_gov.php`, `lib/tapi_score.php`,
`views/dashboard.php`, `index.php`.

No rebuild of Operations, Quality, Reporting, Money, Dashboard, TAPI,
Recruitment, Marketplace, RBAC, the entitlement engine or the audit engine.

---

## 13. Known limitations

Nine, in `M7-KNOWN-LIMITATIONS.md`. The two worth a business decision:

- **L3** — a company with Operations but not Money loses the profit and margin
  columns from MIS. Correct enforcement, visible change.
- **L4** — a company with neither Sales nor Hiring loses the costing screen
  entirely, including sheets created before a downgrade.

---

## 14. Deferred M8 work — explicitly not implemented

| Deferred | Status |
|---|---|
| `cron.php`, `cron_ads.php` — cron / background / scheduled job entitlement | untouched |
| Public sign-up (`get-started`) and public application routes | untouched |
| Other public routes | untouched |
| Marketplace entitlement | untouched — not a product module |
| Connect entitlement (`/pro`, `/join`, `/connect`) | untouched |
| Registry work: bringing non-`mod.*` permissions under the ownership map | L6 |
| Build-time guard: every metric must have a mapped lineage source | L5 |

---

## 15. Acceptance criteria

| Criterion | Result |
|---|---|
| Paid reports require entitlement | **PASS** |
| Paid exports require entitlement | **PASS** |
| Direct report URLs cannot bypass | **PASS** |
| Direct export/download URLs cannot bypass | **PASS** |
| AJAX report/export actions cannot bypass | **PASS** |
| Master cannot bypass paid-module entitlement | **PASS** |
| Unknown/unsafe paid ownership fails closed | **PASS** |
| Core reports remain functional | **PASS** |
| Existing entitled reports remain functional | **PASS** |
| Existing data-scope rules intact | **PASS** — no query or scope clause changed |
| Cross-tenant isolation | **PASS** |
| S-1 passes | **PASS** |
| Reporting remains operational | **PASS** — asserted explicitly |
| Operations / Money / Sales functionality operational | **PASS** |
| Focused M7 tests pass | **PASS** — 108/0 |
| Full regression passes | **PASS** — 7,675/0 |
| No unnecessary schema/data changes | **PASS** — none |
| MySQL status honestly recorded | **PASS** — not available, not tested |
| Documentation complete | **PASS** — five documents |
| M8 work not prematurely implemented | **PASS** |

---

**STOP. M8 has not been started.** Awaiting authorisation for
*M8 — Public / Background Enforcement*.
