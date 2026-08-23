# 01 — Roles

Every role in `ORG_ROLES` (`lib/access.php:10-26`). For each: the exact key, who they
are in real life, their device/context, what they see in the navigation, what they
are accountable for, and their hard boundaries. Defaults come from
`role_defaults_base()` (`access.php:434-473`) and `module_defaults()`
(`access.php:324-374`). "Master" = `is_superuser` (`access.php:482,515`).

Two derived groups gate most things:
- **`is_admin_level()`** = role ∈ `MGMT_ROLES` (`access.php:27`, `ops.php:547`).
- **`is_coordinator_level()`** = admin-level **+** `ASST_MANAGER`, `COORDINATOR` (`ops.php:548`).

Landing is always `/` → `views/dashboard.php`; the dashboard changes *emphasis* by
role (inspector branch `dashboard.php:23-43`; `$moneyFirst` for FINANCE `:65`;
`$schedFirst` for COORDINATOR/ASST_MANAGER `:66`) but there is **no per-role redirect**.

---

## MASTER_ADMIN — "Master Admin"
- **Key:** `MASTER_ADMIN` · master via `is_superuser`.
- **Who:** the system owner / root account for the installation — the person who can fix anything, switch modules on/off, and recover other logins. Meant to be one or very few.
- **Context:** desk, laptop.
- **Sees:** everything — all areas plus admin-only tiles (Roles & permissions `areas.php:209`, Control panel `:226`, Super admin).
- **Accountable for:** the integrity of the whole installation — access, licence, configuration, recovery.
- **Must never:** be routinely restricted or used for day-to-day data entry — it bypasses every gate (`access.php:530`), so a mistake here has no guardrail.

## ADMIN — "Admin (legacy)"
- **Key:** `ADMIN` · perms = **all**; offices/sbus `ALL` (shares the master case at `access.php:437-438`); modules = all (`:328`). Also the fallback for unknown roles (`access.php:483`).
- **Who:** a full administrator who is **not** the super-user — broad access governed by permissions (so it *can* be narrowed, unlike a master).
- **Context:** desk, laptop.
- **Sees:** all areas; but `is_master()`-only tiles (Roles & permissions, Control panel) are hidden unless also super-user.
- **Accountable for:** running the system day-to-day short of root operations.
- **Must never:** be assumed unbreakable — a per-user override or role change can strip it (this is what caused the recent lock-out). Keep a real `MASTER_ADMIN` as the recovery key.

## BUSINESS_DIRECTOR — "Business Director"
- **Key:** `BUSINESS_DIRECTOR` · perms = all dashboards + all `data.*` figures + `org.hierarchy.view` + `idems.audit.view`; offices/sbus `ALL` (`access.php:439-440`); modules = **view all** except `identity` (`:329,366-369`).
- **Who:** the top executive who reads the whole business but does not run the desks — sees every number across every branch.
- **Context:** desk, laptop; figures-first.
- **Sees:** every area (view-only); Masters/Users tiles show, **System settings hidden** (no `settings.manage`, `areas.php:213`).
- **Accountable for:** company-wide performance and margin.
- **Must never:** edit operational records or see identity documents by default.

## SBU_HEAD — "Business Unit Head"
- **Key:** `SBU_HEAD` · all dashboards + all `data.*` + `workforce.report.approve` + `org.hierarchy.view` + `idems.finalize` + `idems.audit.view`; offices `ALL`, **sbus `OWN`** (`access.php:441-442`); views a broad module set (`:330-331`).
- **Who:** the head of one business unit/segment across branches — owns that unit's numbers and sign-offs.
- **Context:** desk, laptop.
- **Sees:** Sales, Operations, Recruitment, Quality (pack-gated), Reporting, Money, Insights, Directory, Admin(Masters). No Users module.
- **Accountable for:** their SBU's revenue, utilisation and report sign-off.
- **Must never:** manage logins/settings; act outside their SBU's data.

## BRANCH_MANAGER — "Branch Manager"
- **Key:** `BRANCH_MANAGER` · all dashboards + all `data.*` + `ops.call.create` + `ops.job.allocate` + `ops.job.close` + `workforce.availability` + `workforce.report.approve` + `master.manage` + `users.manage.branch` + `org.hierarchy.view` + `idems.finalize` + `idems.audit.view`; **offices `OWN`**, sbus `ALL` (`access.php:443-444`); edits calls/jobs/idems/vouchers/hiring/reconcile/clients/vendors/masters/reports/users/complaints/capa/datacontrol/portal (`:332-334`).
- **Who:** the person who runs a single branch and is accountable for that branch's margin, staffing and delivery.
- **Context:** desk, laptop.
- **Sees:** all seven areas + Operations + Recruitment; Admin shows Masters + Users; System settings hidden (no `settings.manage`).
- **Accountable for:** the branch P&L, its people, its quality outcomes, and approving its work.
- **Must never:** manage users **outside their own office** (`users.manage.branch`, not global); change system settings.

## BRANCH_APP_MANAGER — "Branch Application Manager"
- **Key:** `BRANCH_APP_MANAGER` · `dash.operations, dash.utilization, users.manage.branch, master.manage, ops.call.delete, org.hierarchy.view, idems.type.manage, idems.timestamp.edit, idems.audit.view`; offices `OWN`, sbus `ALL` (`access.php:445-447`); edits masters/overheads/users, views calls/jobs/reports (`:335-337`). **Only role with `idems.timestamp.edit`.**
- **Who:** the branch's system/config custodian — sets up masters, report types/numbering, users, and can delete a wrongly-raised call.
- **Context:** desk, laptop.
- **Sees:** Operations (calls/jobs view), Money (overheads), Insights, Directory (client holds), Admin (Masters, Users, report-config tiles via `idems.type.manage`). **No Reporting rail item** despite IDEMS-config perms (see flag).
- **Accountable for:** the branch's configuration correctness and login hygiene.
- **Must never:** run inspections or approve/issue reports (has no `mod.idems.view` or `idems.finalize`).
- **⚠ Flag:** holds `idems.type.manage`/`idems.timestamp.edit` but not `mod.idems.view`, so the Reporting area doesn't appear — the config screens are reachable via Admin tiles only.

## OPERATION_MANAGER — "Operation Manager"
- **Key:** `OPERATION_MANAGER` · `dash.operations, dash.utilization, data.revenue, data.profitability, ops.call.create, ops.job.allocate, ops.job.close, workforce.availability, workforce.report.approve, idems.finalize`; offices/sbus `OWN` (`access.php:448-450`); edits calls/jobs/idems/vouchers/hiring/reconcile/complaints/capa/portal, views crm_orders/clients/vendors/masters/profitability/reports (`:338-340`).
- **Who:** the head of operations for a branch/unit — owns delivery: getting calls scheduled, jobs allocated, reports out.
- **Context:** desk, laptop.
- **Sees:** Operations + Recruitment, Quality (complaints/capa), Reporting, Money (profitability), Insights, Directory, Admin (Masters). **No Sales** (only `crm_orders`, which has no Sales tile).
- **Accountable for:** on-time, in-spec delivery and inspector utilisation.
- **Must never:** manage users or system settings; see salary/credit figures (has revenue+profit but not `data.salary`/`data.credit`).

## ASST_MANAGER — "Asst. Manager"
- **Key:** `ASST_MANAGER` · `dash.operations, ops.call.create, ops.job.allocate, workforce.availability`; offices/sbus `OWN` (`access.php:451-452`); edits calls/jobs/idems/complaints, views clients/vendors/reports/capa (`:341-343`). **`is_coordinator_level` but NOT `is_admin_level`.**
- **Who:** a deputy who helps run the desk — raises calls, allocates jobs, keeps the board moving; no money authority.
- **Context:** desk, laptop; scheduling-first (`dashboard.php:66`).
- **Sees:** Operations, Quality (complaints/capa), Reporting, Insights, Directory; **no Money, no Recruitment, no Sales**. Admin appears **only** via the coordinator-level SLA-targets tile (`areas.php:216`).
- **Accountable for:** keeping the daily operations board current.
- **Must never:** close jobs (no `ops.job.close`), see any money figures, manage users/settings.

## COORDINATOR — "Coordinator"
- **Key:** `COORDINATOR` · `dash.operations, dash.financial, data.credit, ops.call.create, ops.job.allocate, ops.job.close, workforce.availability`; offices/sbus `OWN` (`access.php:453-455`); edits calls/jobs/idems/vouchers/hiring/reconcile/complaints/portal, views crm_orders/clients/vendors/masters/reports/invoicing/capa (`:344-346`). `is_coordinator_level`, not admin-level.
- **Who:** the operational engine of a branch — receives contracts from accounts, raises inspection calls, allocates and schedules inspectors, chases reports, closes jobs.
- **Context:** desk, laptop; scheduling-first.
- **Sees:** Operations + Recruitment, Quality (complaints/capa), Reporting, Money (invoice tracker; billing workspace via `data.credit`), Insights, Directory, Admin (Masters, SLA targets).
- **Accountable for:** turning a live contract into scheduled, completed, closed work.
- **Must never:** see revenue/salary/profit (has `data.credit` only), approve their own reports, manage users or settings.

## BUSINESS_DEV_MANAGER — "Business Development Manager"
- **Key:** `BUSINESS_DEV_MANAGER` · `dash.operations, dash.financial, data.credit, crm.quote.create, crm.quote.send, crm.followup.manage`; offices `OWN`, sbus `ALL` (`access.php:456-457`, shared with KAM); edits inquiries/quotes/crm_orders, views crm_reports/clients/reports (`:348-350`).
- **Who:** front-line sales — chases leads, builds and sends quotations, follows up.
- **Context:** desk/mobile; sales-flavoured dashboard (`dashboard.php:63`).
- **Sees:** Sales, Insights, Directory (clients). Nothing operational, no money beyond credit.
- **Accountable for:** pipeline and won quotations.
- **Must never:** touch operations, approve quotes, register contracts, or manage users.

## KEY_ACCOUNTS_MANAGER — "Key Accounts Manager"
- **Key:** `KEY_ACCOUNTS_MANAGER` · **identical defaults to BUSINESS_DEV_MANAGER** (`access.php:456-457`, `:348-350`).
- **Who:** sales owner for named key accounts — same rights as BDM, focused on retention/upsell.
- **Context:** desk/mobile.
- **Sees / Accountable / Must never:** as BUSINESS_DEV_MANAGER.

## MARKETING_MANAGER — "Marketing Manager"
- **Key:** `MARKETING_MANAGER` · `dash.operations, dash.financial, data.credit, data.revenue, data.profitability, crm.quote.create, crm.quote.approve, crm.quote.send, crm.followup.manage, crm.template.manage`; offices/sbus `ALL` (`access.php:458-459`); edits inquiries/quotes/crm_orders/crm_reports/clients, views reports/profitability (`:351-353`).
- **Who:** senior sales/marketing — can approve quotations and manage templates across all offices.
- **Context:** desk, laptop.
- **Sees:** Sales (incl. pre-order checklist via `crm.quote.approve`), Money (profitability), Insights, Directory, Admin (Document-templates tile via `crm.template.manage`, `areas.php:222`).
- **Accountable for:** sales conversion, quote quality, templates.
- **Must never:** run operations, register contracts, manage users/settings.

## MARKETING_EXECUTIVE — "Marketing Executive"
- **Key:** `MARKETING_EXECUTIVE` · `crm.quote.create, crm.followup.manage`; offices/sbus `OWN` (`access.php:460-461`); edits inquiries/quotes, views crm_orders/crm_reports/clients (`:354-356`).
- **Who:** junior sales — creates quotes and does follow-ups within their office.
- **Context:** desk/mobile.
- **Sees:** Sales, Directory (clients); **no Insights** (no `mod.reports.view`).
- **Accountable for:** quote drafting and follow-up.
- **Must never:** approve/send quotes, touch operations or money.

## FINANCE — "Finance"
- **Key:** `FINANCE` · `dash.financial, data.credit, data.revenue, data.salary, data.profitability, finance.reconcile, crm.contract.register`; offices/sbus `ALL` (`access.php:462-463`); edits invoicing/crm_orders, views quotes/crm_reports/profitability/reports/jobs/calls/vouchers/idems (`:357-359`). **Not `is_admin_level`.**
- **Who:** accounts/finance — registers contracts off won quotes, raises and issues invoices, records receipts, reconciles credit and attendance.
- **Context:** desk, laptop; money-first dashboard (`dashboard.php:65`).
- **Sees:** Sales (quotes view), Operations (calls/jobs/vouchers **view-only**), Reporting, Money (invoicing/profitability/billing), Insights. **No Directory, no Admin, no Recruitment, no Quality.**
- **Accountable for:** billing, collections, and the numbering integrity of invoices.
- **Must never:** allocate/close jobs or edit operations records; manage users/settings.

## SR_INSPECTOR — "Senior Inspector"
- **Key:** `SR_INSPECTOR` · `idems.finalize`; offices/sbus `OWN` (`access.php:466-470`); edits `idems` only (`:360`). **`is_inspector()` is false** (matches literal `INSPECTOR` only, `ops.php:549`).
- **Who:** an experienced inspector who also acts as an approver / can finalise reports.
- **Context:** **phone in the field**, and desk for approvals.
- **Sees:** the **normal area rail** with only Reporting (from `mod.idems.view`) + Dashboard/Search — **not** the inspector "My work" menu.
- **Accountable for:** field inspection and report sign-off/issue.
- **Must never:** manage operations/users (no such perms).
- **⚠ Flag:** does **not** get the inspector home / "My Jobs / My Voucher" menu that a plain INSPECTOR gets, because `is_inspector()` is `INSPECTOR`-only. Likely unintended — see `99-gaps-and-risks.md`.

## INSPECTOR — "Inspector"
- **Key:** `INSPECTOR` · perms = **empty**; offices/sbus `OWN` (`access.php:464-465`); edits `idems` only (`:360`). `is_inspector()` ✅; `ua()['self'] = true` (`access.php:514`).
- **Who:** the field engineer who goes to site, checks in, does the inspection, writes the report, records their own expenses.
- **Context:** **phone-first, patchy 4G** — offline queue matters.
- **Sees:** only the "My work" group — My Jobs, My Reports / New report / Endorsements, My Voucher (`layout_top.php:100-113`). No area rail.
- **Accountable for:** doing and reporting the inspection accurately and on time, and their own expense/timesheet claim.
- **Must never:** see money figures, other people's jobs (own-scope), manage anything. *(Note: a scoped exception lets an inspector open/close **their own** job — see the flows.)*

---

## The two portal permission systems (summary — not `ORG_ROLES`)

These are **separate** from staff roles and must not be confused with them:

- **Client portal** — external client logins with their own per-user permission set,
  checked via `pcan()` and managed at `views/ops/portal_users.php` /
  `portal_user_perms.php` (engine in `lib/portal.php`, screens in `views/portal/*`).
  Clients see only their own calls, reports, invoices, and can raise complaints and
  (where enabled) decide report acceptance.
- **Vendor portal** — external vendor logins (`vendor_users`, engine `lib/cvp.php`,
  screens `views/vendor/*`) seeing only their own inspection activity and issues.

A full role-by-role treatment of the portals is out of scope for this pass (Option A:
staff roles in depth, portals summarised); they are flagged in `99-gaps-and-risks.md`
as a distinct access surface to document next.
