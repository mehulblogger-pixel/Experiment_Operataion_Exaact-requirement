# 99 — Gaps & Risks

The honest list, ranked by risk. Each item: what it is, where (`file:line`), why it
matters, and a recommended fix. **Nothing here is implemented in this session — this
is documentation only.** Fixes require your green light.

Paths relative to `phpapp/`. "⚠ implicit" = a right that exists only because no check
blocks it.

---

## R1 — HIGH · Client/Vendor/PO/Contract create & edit have NO permission guard
**What:** `partner-new` (`index.php:907`), `partner-edit` (`:997`), `partner-add`
(contact/address/registration/**contract**/relationship/note/**po**) (`:1031`), the
partner 360 detail `partner` (`:1140`), and PO view/line-add `po` (`:1164`) run **before**
`ops_dispatch()` (called at `index.php:1221`), so the module gate never touches them and
none carries an `ops_require`. Only `require_login()` (`index.php:767`) applies.
**Who reaches it:** *any* authenticated user of *any* role — including an **INSPECTOR**.
**Why it matters:** anyone can create/edit any client or vendor, **register a contract**,
create a **purchase order**, and roll up a contract's value — the commercial master data.
The `clients`/`vendors` *list* is gated (`index.php:879`) but the records behind it are not.
**Fix (recommended):** add `ops_require(can('mod.clients.edit')||can('mod.vendors.edit')||is_coordinator_level())`
to the create/edit/add routes, and `can('mod.clients.view')||…` to `partner`/`po` view;
gate contract-add by `crm.contract.register`/endorse rights and PO-add by an ops/finance right.

## R2 — HIGH · `job-close` had no permission guard (ignored `ops.job.close`) — **FIXED**
**What:** the `job-close` handler had **no `ops_require`**; it was protected only by the
module gate `mod.jobs.view` or the job-owner bypass (`ops.php:2384-2388`), so the dedicated
`ops.job.close` permission was **never evaluated**.
**Who reached it:** anyone holding `mod.jobs.view` — including **FINANCE, SBU_HEAD,
BRANCH_APP_MANAGER, ASST_MANAGER**, none of whom hold `ops.job.close` by default.
**Why it mattered:** closing a job records days/expenses and locks it (financial effect);
the wrong roles could do it, and the permission designed for it was inert.
**Fixed this session:** `ops_require(is_master() || can('ops.job.close') || job_owned_by_me($id))`
at the top of `job-close` (guards GET and POST). Deliberately keyed off the **real
permission**, not `is_coordinator_level()` (which the earlier note suggested but which would
keep letting ASST_MANAGER close despite lacking the permission — the app's rule is "gate by
ability, not tier"). Closers now: CO, BM, OM (hold `ops.job.close`), master, and the assigned
inspector on their own job; FIN/SBU/BAM/AM can no longer close. Tests:
`tests/test_job_close_guard.php`.

## R3 — HIGH · A per-user permission set REPLACES role defaults (silent lock-out)
**What:** if a login has any custom `permissions` value, it *replaces* the role defaults
entirely (`access.php:414-420,489-495`). Editing an admin and saving with fewer boxes ticked
drops them to exactly what's ticked.
**Why it matters:** this already locked the founder out of Users/Settings. It is a
foot-gun with no in-product warning, and recovery needed server/DB access.
**Fix (partly mitigated this session — a "Reset to role default" control now exists):**
consider also (a) pre-ticking the effective set so a no-op save preserves access, and
(b) a confirm when a save would remove a module an admin currently holds.

## R4 — HIGH · Contract registration has two doors, one unguarded — **permission door now closed**
**What:** the CRM path `quote-contract` requires `crm.contract.register` (`crm.php:1830`),
but `partner-add kind=contract` (`index.php`) used to create a `partner_contracts` row
with **no permission check** (only number-uniqueness).
**Why it mattered:** the second door bypassed both the permission and the endorse→approve
two-signature control (`contracts.php`), so a contract could be created off-book — by a
salesperson, coordinator or even an inspector.
**Fixed this session:** the partner-screen contract-add is now gated by
`ops_require(can('crm.contract.register') || is_master())` — the same permission the CRM
path uses — so only Accounts/back-office (and master) can register a contract from either
door (`index.php` partner-add; `tests/test_contract_backdoor_guard.php`).
**Still open (lower risk):** new numbers created via this door do not yet run through the
PENDING→endorse→approve lifecycle — a follow-up if the two-signature control is wanted on
this path too.

## R5 — MEDIUM · Voucher: reopen from any state + no segregation of duties
**What:** `voucher` reopen has **no source-status guard** — a coordinator can revert **any**
voucher, including **PAID**, to DRAFT (`ops.php:5001-5003`). And **approve** is done by the
same `is_coordinator_level()` who can submit (`ops.php:4992`) — no maker≠checker split
(unlike contracts and reports).
**Why it matters:** a PAID voucher can be silently reopened and altered; one person can
both submit and approve an expense claim.
**Fix:** restrict reopen to non-PAID (or manager-only with a reason); require the approver
to differ from the submitter.

## R6 — MEDIUM · Call `op_status` is a free-form manual picker with no transition rules
**What:** `call-status` (`tosrm.php:499`) lets any `tosrm_can_edit()` user set **any** of 15
`CALL_STATUSES` (`tosrm.php:26-42`), validated only for set-membership (`:134`) — no rule
about which state may follow which.
**Why it matters:** a call can be moved to a nonsensical state (e.g. COMPLETED with no job),
undermining any reporting built on `op_status`.
**Fix:** define an allowed-transition map and validate against it; or drive `op_status`
from the real events (jobs/reports) instead of a free picker.

## R7 — MEDIUM · SR_INSPECTOR does not get the inspector field UI
**What:** `is_inspector()` matches the literal `INSPECTOR` only (`ops.php:549`), so a
`SR_INSPECTOR` gets the desktop area rail (Reporting only) and the **non-inspector**
dashboard — **no** My Jobs, site check-in, or My Voucher (`layout_top.php:100-113`,
`dashboard.php:23`).
**Why it matters:** a senior inspector who also does field work is missing the phone-first
field tools; likely unintended.
**Fix:** make the inspector UI trigger on "has an `inspector_id`" (or role ∈ {INSPECTOR,
SR_INSPECTOR}) rather than the exact string.

## R8 — LOW-MED · BRANCH_APP_MANAGER holds IDEMS config perms but not `mod.idems.view`
**What:** the role has `idems.type.manage`/`idems.timestamp.edit` but no `mod.idems.view`
(`access.php:335-337,445-447`), so there is no Reporting rail item; the config screens are
reachable only via Admin tiles.
**Fix:** grant `mod.idems.view` (read) to this role, or surface the config screens under a
visible heading.

## R9 — LOW-MED · Concurrent edit with no record locking
**What:** a call/job can be edited by a coordinator and a manager with no lock; job-closure
expenses (coordinator) and the inspector's voucher (`ops.php:5535` vs `voucher_entries`)
write the same cost picture from two sides.
**Why it matters:** last-write-wins can quietly overwrite the other party's change.
**Fix:** optimistic locking (a version/updated_at check on save) on call/job edit.

## R10 — LOW · Vestigial statuses/fields the code never advances
**What:** legacy `calls.status` never reaches `CLOSED` in app flow (only up to ALLOCATED —
`tosrm.php:75-76`, writes at `ops.php:5440`); `jobs.stage` only ever becomes `CANCELLED`
(`tosrm.php:656`), never the intermediate `JOB_STAGES`; `report_docs` `ARCHIVED` is never set.
**Why it matters:** anyone reading the schema will assume lifecycles that don't run.
**Fix:** either wire them or remove them from the constants; at minimum, note them in-code.

## R11 — LOW · Admin area appears for roles with no real admin access
**What:** ASST_MANAGER and COORDINATOR reach the Admin area only via the coordinator-level
"SLA targets" tile (`areas.php:216`); MARKETING_MANAGER only via the `crm.template.manage`
Document-templates tile (`areas.php:222`). They have no users/settings/masters edit.
**Fix:** move those single tiles under a more accurate heading so "Admin" doesn't imply
administrative power.

## R12 — INFO · Deliberately ungated: attendance self-mark
**What:** `attend-mark` is intentionally ungated beyond "login linked to an inspector"
(`attend.php:189`, exempted at `ops.php:2328`). Documented for completeness — **not** a
recommended change; it lets any field staff punch their own day.

## Operational note — keep a Master Admin recovery key
Not a code defect, but critical: `MASTER_ADMIN` (`is_superuser`) is the only login that
cannot be locked out. If it is lost, recovery is via `reset-admin.txt` in the app folder,
a `config.local.php` admin-password change, or `UPDATE users SET is_superuser=1 …` in
phpMyAdmin (all in `lib/db.php:225-314`). Always retain one un-restricted Master Admin.

## Follow-up documentation (not risks, scope notes)
- The **client and vendor portals** use their own permission systems (`pcan()`,
  `vendor_users`) and were summarised, not fully mapped (`04-flows/portals-client-and-vendor.md`).
- Many operational **sub-transitions** (`assign-*`, `dep-*`, `call-attrs/override`) are
  dispatched with only the module gate; their per-verb guards were not individually verified
  in this pass and should be audited if transition-level certainty is needed.
