# Milestone 7 — Known Limitations

What M7 did not close, stated openly. None is a regression.

---

## L1 · A withheld analytics metric shows NO DATA, not a refusal

A Reporting metric in a company without Reporting returns the engine's NO DATA
state. The KPI row still appears in the dashboard and in the exported file, with
no value.

Deliberate: NO DATA is TAPI's existing vocabulary, distinct from a real zero, and
using it means no change to the reporting architecture. **No value is disclosed.**
Removing the row entirely would be a presentation change to a protected engine
and was not attempted.

---

## L2 · Analytics access itself is still core-gated

`tapi_can()` is built from `dash.operations` / `dash.financial` /
`dash.utilization` / `dash.people` plus `is_master()` — none of which is a module
permission. A user who holds any of them can open the analytics screens whatever
the company has bought.

Left as is on purpose. The analytics dashboards are a core management capability,
and with F3 in place every individual number is enforced at the metric. Gating
the screen as well would deny an Operations customer a dashboard whose Operations
metrics they are fully entitled to. What is protected is the **data**, not the
page furniture.

---

## L3 · The MIS profitability columns are a visible behaviour change

A company with Operations but **not** Money loses the cost / profit / margin
columns from the MIS report and its CSV. That is the enforcement working, but it
is a change a real customer will notice if they were previously seeing figures
from a module they do not hold.

Flagged so it is a commercial decision, not a surprise. Customers entitled to
Money see no change.

---

## L4 · Project costing is denied outright without Sales or Hiring

`pc_modules_live()` requires at least one of `quotes`, `inquiries` or `hiring`. A
company with neither loses the costing screen and its printable sheet entirely,
including any sheets it created before a downgrade.

That follows from what the feature is — it prices a quotation or budgets a
requisition, and neither concept exists in such a company. Recorded because it is
a loss of access to previously entered data, not only to a function.

---

## L5 · Ownership is by source prefix, and the map is hand-written

`TAPI_SOURCE_MODULES` names the nine lineage sources that exist today. A **new**
metric with a new source, not added to the map, returns NO DATA.

That direction is the safe one — it withholds a number rather than publishing
one — but it will look like a bug to whoever adds the metric. A test asserting
that every registered metric has a mapped source would make this impossible to
miss; it is a build-time guard and was not in M7's scope.

---

## L6 · Non-module permissions remain non-module permissions

M5's L1 and M6's L1 still stand. M7 closed two more instances on report paths
(`data.salary` for MIS profitability, and the `is_admin_level()` fallback in
project costing), but `dash.*`, `crm.quote.approve`, `idems.finalize` and their
kind are still RBAC-only. Where a route gate or a metric check now stands in
front of them, the gap is closed in practice; the general fix remains registry
work.

---

## L7 · Exports are enforced at the boundary, not per row

By design, and as §22 required. An export authorised for a module returns the
rows the user's existing office / SBU / client scope already allows — M7 changed
no scope clause and no query. What M7 does **not** add is per-row entitlement
inside an authorised export, because there is no row-level entitlement concept in
this product to enforce.

---

## L8 · MySQL was not tested

SQLite only; no MySQL/MariaDB server exists in this environment — verified, not
assumed. M7 adds no SQL and no schema change. See `M7-TEST-RESULTS.md` §2.

---

## L9 · Not yet verified on the live server

M5, M6 and M7 have not been uploaded to `operations.mghaiapps.com`.
`deploy-check.php` — regenerated in this milestone — is the instrument that
confirms the uploaded files match this code.
