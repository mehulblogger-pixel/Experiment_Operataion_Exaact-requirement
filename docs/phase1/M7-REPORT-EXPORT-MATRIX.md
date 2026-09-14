# Milestone 7 — Report / Export Matrix

Every report and export route found in the repository, with the module it
actually belongs to. Built by extracting the route→module map out of
`ops_module_gate()` and resolving each route through the live registry — not
from documentation.

Ownership chain: **Route → Access module → Product module → Entitlement.**

---

## 1. Exports already enforced by the route gate (M5)

These resolve to an owning module through the explicit map or the paid-family
table. No M7 change was needed; all are asserted by test.

| Route | Kind | Access module | Product module | Entitlement |
|---|---|---|---|---|
| `quotes-export` | CSV | `quotes` | **sales** | required |
| `quote-pdf` | PDF | `quotes` | **sales** | required |
| `crm-template-download` | download | `crm_reports` | **sales** | required |
| `crm-reports` | report | `crm_reports` | **sales** | required |
| `recruit-export` | CSV | `hiring` | **hr** | required |
| `tally-export` | export | `invoicing` | **money** | required |
| `invoice-print` | print / PDF | `invoicing` | **money** | required |
| `document-pdf` | PDF | `idems` | **reporting** | required |
| `report-preview` | report | `idems` | **reporting** | required |
| `report-builder` | report | `idems` | **reporting** | required |
| `report-types`, `report-type-edit`, `report-type-preview` | report config | `idems` | **reporting** | required |
| `report-templates`, `report-template-edit`, `report-template-preview` | template | `idems` | **reporting** | required |
| `report-template-download` | download | `idems` | **reporting** | required |
| `report-autoform`, `report-form-from-template`, `report-field-edit` | report build | `idems` | **reporting** | required |
| `report-file` | download | `idems` | **reporting** | required |
| `report-ack` | action | `idems` | **reporting** | required |
| `report-reviews` | report | `idems` | **reporting** | required |
| `report-approve` | action | `jobs` | **operations** | required |
| `report-equip-add`, `report-equip-del` | action | `equipment` | **operations** | required |
| `job-forward-report` | action | `jobs` | **operations** | required |
| `voucher-csv` | CSV | `vouchers` | **operations** | required |
| `voucher-print` | print | `vouchers` | **operations** | required |
| `profitability` | report | `profitability` | **money** | required |

---

## 2. Reports and exports mapped to CORE — the M7 subject

Reaching these is core and correct. What they **contain** was not.

| Route | Kind | Mapped to | Contents | M7 outcome |
|---|---|---|---|---|
| `reports` | hub | `reports` (core) | Links to module reports, each separately gated | unchanged — core |
| `mis` | report + CSV | `reports` (core) | Jobs / man-days / revenue (**Operations**) **plus** cost, profit, margin (**Money · profitability**) | **F2** — profitability columns now require Money, in the screen and the CSV alike |
| `analytics` | dashboards | `reports` (core) | 20 KPIs: 10 read `idems` (**Reporting**), 8 Operations, 2 core portal | **F3** — a metric whose module is not live returns NO DATA |
| `analytics-export` | CSV / XLSX | `reports` (core) | the same KPI matrix | **F3** — same enforcement, same boundary |
| `analytics-review`, `analytics-snapshot` | report | `reports` (core) | the same metrics | **F3** — inherited |

### The analytics metric registry, by declared lineage

| Metric | Declared source | Access module | Product module |
|---|---|---|---|
| `jobs.total`, `jobs.closed`, `jobs.open`, `jobs.mandays` | `ops/jobs` | `jobs` | operations |
| `revenue.invoiced` | `ops/jobs` | `jobs` | operations — *labelled FINANCE, but it sums `jobs.invoice_amount`; the lineage decides, not the label* |
| `calls.total` | `ops/calls` | `calls` | operations |
| `sla.evaluable` | `tosrm/calls` | `calls` | operations |
| `ncr.total`, `ncr.open`, `ncr.closure_avg_days` | `ncr/nonconformities` | `ncr` | operations |
| `capa.effective` | `capa` | `capa` | operations |
| `reports.total`, `reports.issued`, `report.tat_avg_days`, `reports.under_review`, `release.conditional`, `release.not_released` | `idems/report_docs` | `idems` | **reporting** |
| `vendor.reassess_due` | `idems/vendor_profiles` | `idems` | **reporting** |
| `portal.active_users`, `portal.events` | `portal/*` | `portal` | admin (**core**) |

A source not named in `TAPI_SOURCE_MODULES` resolves to **no owner** and the
metric returns NO DATA. Unknown ownership withholds a number rather than
publishing one.

---

## 3. Reports outside the route map — classified individually

| Route | Class | Why | M7 outcome |
|---|---|---|---|
| `project-costing`, `project-costing-print` | **PAID (sales / hr)** | A costing sheet prices a quotation or budgets a requisition | **F1** — at least one owning module must be live; the screen and the printable sheet share one guard |
| `incidents`, `incident`, `incident-new`, `incident-report` | **CORE** | CERT-In security-incident reporting is a legal obligation of every installation under the DPDP regime | unchanged — correctly core |
| `compliance` | **CORE** | Compliance register | unchanged — core |
| `backup` (+ `?download=`) | **CORE** | A company exporting its **own** data under core administration rights | unchanged — core |
| `verify`, `verify-pdf` | **PUBLIC** | A client verifies a report they already hold, by its printed code, without an account. Exposes nothing confidential | unchanged — public by design |

---

## 4. Export paths on other surfaces

| Surface | Path | Gate | Status |
|---|---|---|---|
| Client portal | report PDF download | `portal_need('reports')` → `pcan('reports')` | enforced — **M6** |
| Client portal | invoice views | `pcan('invoices')` | enforced — **M6** |
| Client portal | voucher file downloads | `portal_need('market.vouchers')` | Marketplace — own switch |
| Vendor portal | report access | `vcan('reports')` | enforced — **M6** |
| Recruitment | `positions-import` CSV template | route `positions-import` → `hiring` → **hr** | enforced — M5 |

---

## 5. Client-supplied parameters

Asserted by test: none of these takes part in any export authorisation.

| Supplied by the client | Effect on an export |
|---|---|
| `module`, `product`, `report` | none — the module comes from the route map or the metric's own lineage |
| `tenant` | none — the tenant comes from the request's own resolution |
| `fmt` (csv / xlsx) | chooses the file format **after** authorisation, never whether it is authorised |
| `id` of a record | subject to the existing office / SBU / client scope, unchanged by M7 |
