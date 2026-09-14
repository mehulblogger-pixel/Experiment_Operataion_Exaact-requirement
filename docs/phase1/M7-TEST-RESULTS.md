# Milestone 7 — Test Results

**Run date:** 2026-09-14 · **Command:** `php tests/run.php`

---

## 1. Headline

| | Before M7 | After M7 |
|---|---|---|
| Test files | 454 | **455** |
| Assertions passed | 7,567 | **7,675** |
| Failed | 0 | **0** |
| Skipped | 0 | **0** |
| Runtime | 95 s | **98 s** |

- **PHP:** 8.4.19
- **Database:** SQLite (see §2)
- New assertions: **108**, all in `tests/test_m7_report_export_entitlement.php`.
- **No existing test was modified, weakened, skipped or deleted.**

---

## 2. Database — stated plainly

**The suite ran on SQLite. MySQL/MariaDB was NOT available and was NOT tested.**

Verified rather than assumed: no `mysql`, `mysqld` or `mariadb` binary is
installed in this environment. `pdo_mysql` is loaded, but there is no server to
reach.

**No claim of production database validation is made.**

M7 adds **no SQL, no schema change, no new table, column or index**. All three
changes are boolean authorisation decisions taken before an existing query runs;
none alters a query, a join or a scope clause.

---

## 3. The new suite — 108 assertions

| Group | Covers | Assertions |
|---|---|---|
| **A** | Direct export URLs — 14 export routes across all five products, denied under S-1 and allowed under a full subscription, without the user first opening the report screen | 28 |
| **B** | Project costing (F1) — Sales alone suffices, Hiring alone suffices, neither denies; screen, edit and approve; and the printable sheet proven to sit behind the same guard | 11 |
| **C** | MIS export (F2) — profitability columns allowed with Money, withheld without it even from a master; report shape and Operations content unchanged | 5 |
| **D** | Analytics (F3) — ownership read from lineage, seven Reporting metrics export NO DATA when unentitled, Operations metrics still resolve, unknown lineage fails closed, core metrics preserved | 22 |
| **E** | Blank entitlement, and a module the company switched off itself | 7 |
| **F** | Forged `module`, `report`, `fmt`, `tenant`, `product` parameters authorise nothing | 6 |
| **G** | Cross-tenant — company B does not inherit A's export entitlement across a connection switch | 5 |
| **H** | S-1 through the export paths, as a master | 16 |
| **I** | Core and public exports preserved on the narrowest plan | 6 |
| restore | teardown | 2 |

`RESULT: 108 passed, 0 failed`

### The §23 matrix, point by point

| Scenario | Expected | Result |
|---|---|---|
| Entitled report | Allowed | pass |
| Unentitled report | Denied | pass |
| Disabled module report (company switched it off) | Denied | pass |
| Licence-blocked report | Denied | pass |
| Blank entitlement | Denied | pass |
| Unknown report ownership | Denied if paid/unsafe | pass — unknown lineage returns NO DATA |
| Master + unentitled report | Denied | pass |
| Master + entitled report | Normal RBAC | pass |
| Direct report URL | Enforced | pass |
| Direct export URL | Enforced | pass |
| CSV export | Enforced | pass (`quotes-export`, `recruit-export`, `voucher-csv`, analytics CSV) |
| Excel export | Enforced | pass (analytics XLSX — same boundary as the CSV) |
| PDF export | Enforced | pass (`document-pdf`, `quote-pdf`, `invoice-print`) |
| Download action | Enforced | pass (`report-template-download`, `crm-template-download`, `report-file`) |
| AJAX report action | Enforced | pass (analytics review/snapshot share `tapi_metric_value()`) |
| Cross-tenant access | Denied | pass |
| Core report | Preserved | pass |
| Existing entitled report | Preserved | pass |

Endpoint types not present in this repository were not invented to fill the
table. There is no separate AJAX report endpoint beyond the analytics routes, and
no scheduled-report download outside the deferred cron surface.

---

## 4. Both sides were proved

Every paid export in the suite is asserted **twice** — denied when the module is
absent and allowed when it is present. An implementation that simply blocked
everything would fail 28 of the group-A assertions alone.

---

## 5. The tests were mutation-checked, not just run

Each fix was removed in turn and the suite re-run, to confirm the tests actually
detect its absence:

| Mutation | Result |
|---|---|
| `tapi_metric_live()` check removed from `tapi_metric_value()` | **8 assertions fail** |
| `pc_modules_live()` removed from `pc_can()` | **2 assertions fail** |
| `licence_module_live('profitability')` removed from the MIS `$seeSalary` | **2 assertions fail** |
| all three restored | **108 passed, 0 failed** |

---

## 6. Reporting regression — §18

Reporting is protected architecture, and the specific risk was that enforcing
HR / Sales / Money would take Reporting down with it. It did not:

| Check | Result |
|---|---|
| Reporting suites (idems, report reviews, templates, endorsements, PDF) | pass |
| S-1: Reporting fully operational with HR, Sales and Money off | pass — asserted explicitly |
| `document-pdf`, `report-template-download`, `report-preview` under S-1 | ALLOWED |
| Reporting analytics metrics under S-1 | resolve to real values |
| TAPI suites (`tapi`, `tapi_dash`, `tapi_domain`, `tapi_gov`, `tapi_score`, `tapi_ui`) | **170 passed, 0 failed** |

---

## 7. Operations / Money / Sales / HR regression — §19

| Area | Result |
|---|---|
| Operations (calls, jobs, vouchers, scheduling, costing) | pass |
| Money (invoicing, receivables, profitability, Tally) | pass |
| Sales / CRM (quotes, leads, contracts, approval rules) | pass |
| Recruitment / HR (requisitions, candidates, exports, careers) | pass |
| MIS | pass |
| Client and vendor portals | pass |
| Entitlement M2 / M3 / M4 / M5 / M6 suites | pass |
| Deploy verification | pass — checksums regenerated |
