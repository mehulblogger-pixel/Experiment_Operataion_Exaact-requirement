# Milestone 10 — Master Bypass Matrix

Every occurrence traced route → action → permission → access module → product
module. Built from the branch after M9, not from memory.

**Inventory:** 400 `is_master()` sites across 133 files · 105 `is_master_of()`
sites · plus `is_admin_level()` / `is_coordinator_level()` equivalents that carry
no `is_master()` text at all.

**After M10:** 398 `is_master()` · 110 `is_master_of()`. Eight sites changed.

---

## 1. Category A — genuine paid-module bypasses, all corrected

| # | File | Route / Action | Permission(s) in the gate | Access module | Product module | Master check (before) | Category | Action taken | Test |
|---|---|---|---|---|---|---|---|---|---|
| A1 | `lib/books.php` | `/search` → Invoices section (**ungated route**); also `invoices`, `to-bill`, `invoice*` | `finance.reconcile`, `data.credit` — **neither is a module permission** | `invoicing` | **money** | bare `is_master()` | **A** | `books_money_live()` asked first | ✅ |
| A2 | `lib/books.php` | `invoice-issue` | `finance.reconcile` | `invoicing` | **money** | bare `is_master()` | **A** | same helper | ✅ |
| A3 | `lib/books.php` | `invoice-cancel` | `finance.reconcile` | `invoicing` | **money** | bare `is_master()` | **A** | same helper | ✅ |
| A4 | `lib/rating.php` | `ratings`, `ratings-config` — **ungated** | `mod.jobs.view` + coordinator level | `jobs` | **operations** | bare `is_master()` | **A** | module required; `is_master_of('jobs')` | ✅ |
| A5 | `lib/timesheet.php` | `timesheets`, `timesheet` — **ungated** | `mod.hiring.view`, `mod.jobs.view` + coordinator | `hiring`, `jobs` | **hr / operations** | bare `is_master()` | **A** | either module required; `is_master_of(['hiring','jobs'])` | ✅ |
| A6 | `lib/inspectorprofile.php` | `inspector-profile` — **ungated** | `mod.hiring.view`, `mod.jobs.view` + coordinator | `hiring`, `jobs` | **hr / operations** | bare `is_master()` | **A** | same treatment | ✅ |
| A7 | `lib/adspro.php` | `adspro`, `adspro-*` — **ungated** | `settings.manage` (core) | `leads` | **sales** | bare `is_master()` | **A** | Sales required — matches M8's cron gate | ✅ |
| A8 | `lib/search.php` | `/search` → Contracts section — **ungated** | `mod.clients.view`, `crm.contract.register`, `data.credit` + coordinator | `clients`, `quotes` | **admin / sales** | bare `is_master()` | **A** | `is_master_of(['clients','quotes'])` | ✅ |
| A9 | `lib/booksbridge.php` | `books-bridge`, `-save`, `-drain` — **ungated** | `settings.manage` (core) | `invoicing` | **money** | bare `is_master()` | **A** | Money required — matches M8's cron gate on `books_bridge_drain()` | ✅ |

---

## 2. Hardened, but **not** exploitable — stated plainly

| File | Route | Why it was safe | Why it was changed anyway |
|---|---|---|---|
| `lib/tally.php` — `tally_can()`, `tally_can_manage()` | `tally`, `tally-export`, `tally-settings`, `tally-undo` — **mapped to `invoicing`** | The route gate establishes Money first | Every term is RBAC or a bare master flag, so safety rested on the route map rather than the gate |
| `lib/billable.php` — `billable_can()`, `billable_can_manage()` | `billable-events`, `billable-*` — **mapped to `invoicing`**; the menu line already ANDs `mod.invoicing.view` | Same | Same |

---

## 3. Category B — entitlement already established

The large majority. A master never reaches these in an unentitled workspace.

| Surface | What establishes entitlement first |
|---|---|
| Every handler behind a mapped route (`jobs`, `calls`, `quotes`, `invoices`, `candidates`, `documents`, …) | `ops_module_gate()` at `ops_dispatch()` — M5 |
| All 21 Connect / Marketplace routes and their 16 gates | `connect_enabled()` — M9 |
| Client and vendor portal surfaces | `pcan()` / `vcan()` — M6 |
| Public careers page and its application POST | `careers_enabled()` — M5 / M8 |
| The nightly run's 28 paid steps | per-step gates — M8 |
| Dashboard paid panels, receivables, recruitment home | M6 |
| Project costing screen and printable sheet, MIS profitability columns, analytics metrics | M7 |
| `hiring_admin_can()`, `pipe_can()`, `gate_can_manage()`, `industry_can()`, `pcmp_can()`, `ncr_can_close()`, `capa_can_close()`, `tosrm_*`, `equipment_can_manage()`, `sched_board_can()` | their routes are mapped to a paid module |

---

## 4. Category C — legitimately core (the recorded probe baseline)

52 gate predicates open for a master holding only core administration. Each is
core functionality or Category B. This exact list is pinned in the M10 test: a
**new** opener that is not on it fails the build by name.

`act_can_view`, `act_can_write`, `asset_can_manage`, `asset_can_view`,
`attend_review_can`, `billing_can_manage`, `capa_can_close`, `cdoc_can_manage`,
`cdoc_can_view`, `cform_can_manage`, `cmp_can_decide`, `cockpit_can`,
`competence_can_authorise`, `cvp_vendor_can_manage`, `disclosure_can_manage`,
`disclosure_can_view`, `drule_can_manage`, `drule_can_view`,
`equipment_can_manage`, `fd_can`, `gate_can_manage`, `hiring_admin_can`,
`iddoc_can_manage`, `iddoc_can_view`, `idems_can_approve_template`,
`idems_can_vet`, `imp_can_decide`, `industry_can`, `job_qap_can`,
`lk_can_manage`, `method_can_manage`, `method_can_view`, `mkt_tax_admin_can`,
`ncr_can_close`, `notifications_can_view`, `pcmp_can`, `pdso_can_view`,
`pipe_can`, `portal_can_manage`, `retention_can_manage`, `retention_can_view`,
`risk_can_manage`, `risk_can_view`, `sample_can_manage`, `sample_can_view`,
`sat_can_manage`, `sat_can_view`, `sched_board_can`, `svc_can_manage`,
`tapi_can`, `tosrm_can_edit`, `tosrm_ops_desk_can`.

---

## 5. Category D — not an authorisation decision

The remainder of the 400: audit actor names, log attribution, UI labels and
copy, "you are an administrator" hints, seed and demo helpers, and comparisons
used for sorting or display. No entitlement consequence.

## 6. Category E — ambiguous

**None remaining.** Every candidate the probe raised was traced to a concrete
route and module before being classified.

---

## 7. The required matrix, as a state table

For every corrected gate:

| Tenant state | Master | Expected | Result |
|---|---:|---|---|
| Module entitled | Yes | ALLOW | ✅ |
| Module not entitled | Yes | DENY | ✅ |
| Licence blocked | Yes | DENY | ✅ |
| Tenant disabled | Yes | DENY | ✅ |
| Unknown (blank record) | Yes | DENY | ✅ |
| Invalid record | Yes | DENY | ✅ |
| Control install | Yes | ALLOW | ✅ |
| Entitled, permission held, **not** master | No | ALLOW | ✅ |
| Not entitled, permission held, not master | No | DENY | ✅ |
| Entitled, permission **not** held, not master | No | DENY | ✅ |
