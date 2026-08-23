# Permission Matrix

Who can do what, module by module. **Every cell is traceable to a real `can()`
check or route guard** — the footnotes under the table give the file and line for
each row.

> **This is the built-in default.** A Master Admin can override any role
> (Settings → Roles & access) and any individual user on top of that
> (`phpapp/lib/access.php:410-424`). The order of precedence is: per-user override,
> then role override, then the default shown here.

---

## How to read this

**Verbs**

| Verb | Meaning |
|---|---|
| `View` | Can open and read, across their whole scope |
| `View own` | Can read only their own records |
| `View branch` | Can read, limited to their own office |
| `Create` · `Edit` · `Delete` | Self-explanatory |
| `Approve` | Can move the record to an approved state |
| `Reopen` | Can send an approved or closed record back for editing |
| `Export` | Can take the data out (CSV, PDF, Tally) |
| `—` | No access |
| `⚠` | **Implicit** — the role can do this because nothing stops it, not because it was granted. Taking the permission away does **not** take the ability away. Every one of these is listed again in `99-gaps-and-risks.md`. |

**Role codes** (sixteen roles make a wide table; the codes keep it readable)

| Code | Role | Code | Role |
|---|---|---|---|
| `MA` | MASTER_ADMIN | `CO` | COORDINATOR |
| `AD` | ADMIN (legacy) | `BDM` | BUSINESS_DEV_MANAGER |
| `BD` | BUSINESS_DIRECTOR | `KAM` | KEY_ACCOUNTS_MANAGER |
| `SH` | SBU_HEAD | `MM` | MARKETING_MANAGER |
| `BM` | BRANCH_MANAGER | `ME` | MARKETING_EXECUTIVE |
| `BAM` | BRANCH_APP_MANAGER | `FI` | FINANCE |
| `OM` | OPERATION_MANAGER | `SI` | SR_INSPECTOR |
| `AM` | ASST_MANAGER | `IN` | INSPECTOR |

---

## The matrix

| Module | MA | AD | BD | SH | BM | BAM | OM | AM | CO | BDM | KAM | MM | ME | FI | SI | IN |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **Calls / Enquiries** <sup>1</sup> | Create Edit Delete | Create Edit Delete | View | View | Create Edit (branch) | View · **Delete** | Create Edit | Create Edit | Create Edit | — | — | — | — | View | — | — |
| **Jobs** <sup>2</sup> | Create Edit Close | Create Edit Close | View ⚠Create ⚠Edit ⚠Close | View ⚠Create ⚠Edit ⚠Close | Create Edit Close (branch) | View ⚠Create ⚠Edit ⚠Close | Create Edit Close | Create Edit ⚠Close | Create Edit Close | — | — | — | — | View | — | View own · Close own |
| **Vouchers** <sup>3</sup> | View Edit Approve Reopen | View Edit Approve Reopen | ⚠View all ⚠Approve ⚠Reopen | ⚠View all ⚠Approve ⚠Reopen | ⚠View all Approve Reopen | ⚠View all ⚠Approve ⚠Reopen | ⚠View all Approve Reopen | ⚠View all ⚠Approve ⚠Reopen | ⚠View all Edit Approve Reopen | — | — | — | — | **—** | **—** | View own Edit own Submit own |
| **Profitability** <sup>4</sup> | View | View | View | View | View | — | View | — | — | — | — | View | — | View | — | — |
| **Business Partners** <sup>5</sup> | View Edit | View Edit | View | View | View Edit | ⚠Create ⚠Edit | View | View | View | View | View | View Edit | View | ⚠Create ⚠Edit | ⚠Create ⚠Edit | ⚠Create ⚠Edit |
| **Contracts** <sup>6</sup> | Edit | Edit | View | View | View | — | View | — | View | Edit | Edit | Edit | View | **Edit · Register** | — | — |
| **Purchase Orders** <sup>7</sup> | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit | ⚠View ⚠Edit |
| **Hiring / Candidates** <sup>8</sup> | Edit | Edit | View | View | Edit | — | Edit | — | Edit | — | — | — | — | — | — | — |
| **Inspector Master** <sup>9</sup> | Edit | Edit | **Edit** | **Edit** | Edit | **Edit** | Edit | — | **—** | — | — | — | — | — | — | — |
| **Attendance / reconcile** <sup>10</sup> | Edit | Edit | View | View | Edit | — | Edit | — | Edit | — | — | — | — | — | — | Mark own |
| **Office Overheads** <sup>11</sup> | Edit | Edit | View | — | View | **Edit** | — | — | — | — | — | — | — | — | — | — |
| **Lookups** <sup>12</sup> | Edit | Edit | **Edit** | **Edit** | Edit | Edit | Edit | — | **—** | — | — | — | — | — | — | — |
| **Custom Fields** <sup>12</sup> | Edit | Edit | **Edit** | **Edit** | Edit | Edit | Edit | — | **—** | — | — | — | — | — | — | — |
| **Users** <sup>13</sup> | Edit (global) | Edit (global) | View | — | Edit (branch) | Edit (branch) | — | — | — | — | — | — | — | — | — | — |
| **Dashboards / Reports** <sup>14</sup> | Edit | Edit | View | View | Edit | View | View | View | View | View | View | View | — | View | — | — |
| **Settings** <sup>15</sup> | Edit | Edit | View | — | — | — | — | — | — | — | — | — | — | — | — | — |
| **Roles & access** <sup>15</sup> | Edit | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| **Reports (IDEMS)** <sup>16</sup> | Edit Approve | Edit Approve | View | View **Approve** | Edit Approve | **—** | Edit Approve | Edit | Edit | — | — | — | — | View | Edit Approve | Edit |
| **Invoicing** <sup>17</sup> | Edit Export | Edit Export | View | View | View | — | — | — | View | — | — | — | — | **Edit Export** | — | — |
| **Quotations** <sup>18</sup> | Edit Approve Send | Edit Approve Send | View | View | View | — | — | — | — | Edit Send | Edit Send | **Edit Approve Send** | Edit (draft only) | View | — | — |
| **Complaints / NCR / CAPA** <sup>19</sup> | Edit **Decide Close** | Edit **Decide Close** | View | View | Edit | — | Edit | Edit | Edit | — | — | — | — | — | — | — |
| **Identity documents** <sup>20</sup> | Edit | Edit | — | — | — | — | — | — | — | — | — | — | — | — | — | — |

---

## Footnotes — where each row is enforced

1. **Calls.** Module gate `mod.calls.view` (`phpapp/lib/ops.php:2243`, applied at
   `phpapp/lib/ops.php:2389`). Create/edit checks `can('ops.call.create')`
   (`phpapp/lib/ops.php:3695`). Delete checks `can('ops.call.delete')`
   (`phpapp/lib/ops.php:3588`). Setting the credit figure needs
   `can('mod.calls.edit') || is_coordinator_level()` (`phpapp/lib/ops.php:3998`).
   *Note the shape of the `BAM` cell: the Branch Application Manager is the only
   non-administrator who can **delete** a call, and cannot create or edit one.*

2. **Jobs.** Module gate `mod.jobs.view` (`phpapp/lib/ops.php:2244`). Allocate and
   edit check **`is_coordinator_level()`** — the role tier, *not* the
   `ops.job.allocate` permission (`phpapp/lib/ops.php:5136`). Reassign likewise
   (`phpapp/lib/ops.php:5663`). Closing a visit day likewise
   (`phpapp/lib/ops.php:5654`). **Closing a job checks no permission at all**
   (`phpapp/lib/ops.php:5531-5534`) — the module view gate or job ownership is the
   only barrier. An inspector reaches their own job through a named owner allowlist
   (`phpapp/lib/ops.php:2379-2387`).
   ⚠ `BD`, `SH` and `BAM` are all in the management tier
   (`phpapp/lib/access.php:27`) and all hold `mod.jobs.view`, so all three can
   allocate, edit, reassign and close jobs despite being documented as read-only or
   as having no operational role. ⚠ `AM` is deliberately **not** granted
   `ops.job.close` (`phpapp/lib/access.php:377`), and closes jobs anyway, because
   that permission is never consulted.

3. **Vouchers.** **This module has no central route gate at all** — `vouchers` does
   not appear in the gate map (`phpapp/lib/ops.php:2242-2377`). Access is decided
   entirely by role tier: the register requires `is_coordinator_level()`
   (`phpapp/lib/ops.php:4863`), and so do approve, mark-paid and reopen
   (`phpapp/lib/ops.php:4965`, `:4970`, `:4974`). Submit accepts the owner or the
   tier (`phpapp/lib/ops.php:4962`). Inspectors get their own via
   `is_inspector()` (`phpapp/lib/ops.php:4857`).
   ⚠ Because the module is never checked, `BAM` and `AM` — who have **no voucher
   module access whatsoever** — can still view, approve and pay every voucher.
   ⚠ The register query carries **no office or business-unit filter**
   (`phpapp/lib/ops.php:4864`), so "View all" means genuinely all, company-wide, for
   every role in the tier.
   **`FI` and `SI` are `—`:** Finance holds `mod.vouchers.view` but is not in the
   tier, so the screen refuses; a Senior Inspector is neither an `INSPECTOR` nor in
   the tier, so cannot open even their own.

4. **Profitability.** Module gate `mod.profitability.view`
   (`phpapp/lib/ops.php:2262`) **and** an explicit `can('data.profitability')` in the
   handler (`phpapp/lib/ops.php:6096`). One of the cleanest gates in the
   application — two independent checks, both explicit.

5. **Business Partners (clients & vendors).** The **list** screens check
   `can('mod.clients.view')` / `can('mod.vendors.view')` (`phpapp/index.php:879`).
   ⚠ **Creating, editing and viewing an individual partner are completely
   ungated** — `partner-new` (`phpapp/index.php:907`), `partner-edit`
   (`:997`), `partner-add` (`:1031`) and `partner` (`:1140`) contain no permission
   check of any kind, and are handled *before* `ops_dispatch()` at
   `phpapp/index.php:1221`, so the central gate never runs for them either. Any
   signed-in user, including an inspector, can create and edit clients and vendors
   by opening the URL.

6. **Contracts.** Module `crm_orders` (`phpapp/lib/access.php:249-284`). Registering
   the client and contract after a won quotation needs `crm.contract.register`,
   held only by Finance (`phpapp/lib/access.php:388`). Opening a contract is gated
   with the quotes module (`phpapp/lib/ops.php:2312`).

7. **Purchase Orders.** ⚠ **No permission check exists.** The `po` route
   (`phpapp/index.php:1164-1220`) has no guard, is not in the module gate map, and
   runs before `ops_dispatch()`. Every signed-in user can open and edit any purchase
   order, including pulling line items and values from a quotation.

8. **Hiring / Candidates.** Module gate `mod.hiring.view`
   (`phpapp/lib/ops.php:2263-2264`). The AI extraction endpoint additionally needs
   `is_coordinator_level()` (`phpapp/lib/ops.php:2511`); changing the engagement mode
   needs `is_admin_level()` (`phpapp/lib/ops.php:2502`).

9. **Inspector Master.** Reached as `/m/inspectors`, gated by
   `master_access_ok('admin')`, which resolves to **`is_admin_level()`**
   (`phpapp/lib/ops.php:2185`, master declared at `phpapp/lib/ops.php:2049`).
   *Two consequences worth a decision:* a **Coordinator cannot edit the inspector
   master** even though allocating jobs to inspectors is their main task; and a
   **Business Director can**, despite holding edit rights on nothing else.

10. **Attendance.** The reconcile screen uses module `reconcile`
    (`phpapp/lib/ops.php:2288`). Self-marking (`attend-mark`) is **deliberately
    ungated** at the central gate and checked in its own handler — the comment says
    so explicitly (`phpapp/lib/ops.php:2326-2328`). That is correct: any staff member
    with an inspector record marks their own day.

11. **Office Overheads.** Module `overheads`, covering both the per-office cost model
    and the month-end cost run (`phpapp/lib/ops.php:2304`). The Branch Application
    Manager is the only role with edit rights (`phpapp/lib/access.php:262`).

12. **Lookups and Custom Fields.** Both handled by `lk_admin()`, gated by
    **`is_admin_level()`** (`phpapp/lib/lookups.php:635`) — the management tier, not
    the `master.manage` permission. Neither route appears in the module gate map.
    *So `master.manage` — which reads as the permission governing master data — does
    not govern the lookup lists.* A Coordinator holds `mod.masters.view` and still
    cannot manage lookups; a Business Director holds no edit rights anywhere and can.

13. **Users.** Module `users` (`phpapp/lib/ops.php:2310`) plus
    `users.manage.branch` for one's own office or `users.manage.global` for all
    (`phpapp/lib/access.php:369-372`). Only the Master Admin holds the global form by
    default.

14. **Dashboards.** Module `reports` (`phpapp/lib/ops.php:2306`) plus the four
    `dash.*` permissions which decide *which* panels appear
    (`phpapp/views/dashboard.php:55-66`). The operational widgets are additionally
    gated on holding a real operations permission, so a Finance or Sales user never
    sees "raise a call" (`phpapp/views/dashboard.php:62`).

15. **Settings and Roles & access.** The settings tile needs
    `can('mod.settings.view') && can('settings.manage')`
    (`phpapp/lib/areas.php:213`). **Roles & access is Master Admin only**, checked
    with `is_master()` (`phpapp/lib/ops.php:2412`) — `ADMIN` cannot reach it despite
    holding every permission, because that check asks for the bypass flag rather than
    a permission.

16. **Reports (IDEMS).** Module `idems` (`phpapp/lib/ops.php:2290`); issuing and
    locking needs `idems.finalize` (`phpapp/lib/access.php:367-395`).
    **`BAM` is `—`, and that is a defect, not a design.** The Branch Application
    Manager holds `idems.type.manage` and `idems.timestamp.edit` — its two signature
    powers, and the code names it as the only role that may edit a locked timestamp
    (`phpapp/lib/access.php:370-371`) — but the role has **no IDEMS module access**
    (`phpapp/lib/access.php:261-262`), and every one of those screens is gated on the
    IDEMS module (`phpapp/lib/ops.php:2293-2295`). The permissions are real and the
    screens are unreachable.

17. **Invoicing.** Module `invoicing` (`phpapp/lib/ops.php:2246`). Recording an
    invoice against a job needs `can('data.credit') || can('finance.reconcile')`
    (`phpapp/lib/ops.php:5467`) — note that `data.credit` is a *visibility*
    permission being used as a *write* gate, which is why a Coordinator can record
    invoices. Raising one needs
    `is_master() || can('finance.reconcile') || can('mod.invoicing.view')`
    (`phpapp/lib/ops.php:5487`).

18. **Quotations.** Module `quotes` (`phpapp/lib/ops.php:2287`) plus the
    fine-grained `crm.quote.*` set. **`crm.quote.approve` is held by
    `MARKETING_MANAGER` alone** among the sales roles
    (`phpapp/lib/access.php:384`) — the separation that stops a salesperson
    approving their own deal. `MARKETING_EXECUTIVE` can draft but not send
    (`phpapp/lib/access.php:386`).

19. **Complaints, Nonconformities and Corrective actions.** Modules `complaints`,
    `ncr`, `capa` (`phpapp/lib/ops.php:2340-2358`). The three *judgement*
    permissions — `complaints.decide`, `ncr.close`, `capa.close` — are granted to
    **no role except the two administrators** (`phpapp/lib/access.php:362-397`, and
    absent from every other branch of `role_defaults_base`). This is deliberate and
    correct: ISO/IEC 17020 §7.5.4 and §8.7.3 require that whoever decides was not
    involved, so somebody must hand the permission out on purpose
    (`phpapp/lib/access.php:76-83`).

20. **Identity documents.** Module `identity`, plus `person.iddoc.view` and
    `person.iddoc.manage`. Granted to nobody by default, and **actively stripped**
    from every blanket "all modules" grant for non-administrators
    (`phpapp/lib/access.php:288-293`). The strongest privacy control in the codebase
    and it should not be relaxed.

---

## What this table is really telling you

Three patterns run through it, and they are worth stating plainly.

**1. The `ops.*` permissions mostly do not do their job.** Four permissions exist
that appear to govern operations: `ops.call.create`, `ops.call.delete`,
`ops.job.allocate`, `ops.job.close`. The first two are properly enforced. The second
two are **never checked on the routes they name** — a search across the whole
codebase finds `ops.job.allocate` and `ops.job.close` used only to decide which
dashboard widgets appear and who may write a report, never to decide who may
allocate or close. Those actions are gated on the management tier instead. The
practical effect: granting or removing them changes almost nothing.

**2. The central gate only ever asks about viewing.** `ops_module_gate()` checks
`mod.<module>.view` and nothing else (`phpapp/lib/ops.php:2389`), including for
routes that delete and close. Write protection depends entirely on each handler
adding its own second check. Most do. Where one does not, **view access is write
access**.

**3. Being senior is treated as being operational.** Nine roles pass
`is_coordinator_level()`, and it is that tier — not module rights, not the `ops.*`
permissions — that opens job allocation and voucher approval. Because the tier was
built from the *management* list, three roles that should never touch daily
operations (Business Director, SBU Head, Branch Application Manager) can allocate
work and approve pay.

None of this is catastrophic in a company where everyone is trusted. All of it
matters the moment you hire someone you do not yet know well. The recommended fixes
are in `99-gaps-and-risks.md`.
