# 99 — Gaps & Risks

The honest list, ranked by risk. Each item: what it is, where, why it matters, and its
status. **Update:** R1–R11 have since been implemented (each marked **FIXED** /
**MITIGATED** / **NOTED** below, with its test); R4 was fixed earlier. **R12 is INFO
only** (deliberately ungated — no change recommended). Any item still open names what
remains.

Paths relative to `phpapp/`. "⚠ implicit" = a right that exists only because no check
blocks it.

---

## R1 — HIGH · Client/Vendor/PO/Contract create & edit had NO permission guard — **FIXED**
**What:** `partner-new`, `partner-edit`, `partner-add`
(contact/address/registration/**contract**/relationship/note/**po**), the partner 360
detail `partner`, and PO view/line-add `po` run **before** `ops_dispatch()`, so the module
gate never touched them and none carried an `ops_require` — only `require_login()`.
**Who reached it:** *any* authenticated user of *any* role — including an **INSPECTOR** —
could create/edit any client or vendor, register a contract, create a purchase order, and
roll up a contract's value.
**Fixed this session (`index.php`):**
- **Create / edit / add** (`partner-new`, `partner-edit`, `partner-add`) →
  `ops_require(is_master() || can('mod.clients.edit') || can('mod.vendors.edit') || is_coordinator_level())`
  (a coordinator may onboard a party during intake).
- **View** (`partner` 360, `po`) → the same with the **view** rights.
- **PO mutations** (pull-quote, add line) → the edit right (or `finance.reconcile`).
- **Contract sub-form** keeps its stricter Accounts-only gate (`crm.contract.register`, R4).
An inspector can no longer create/edit or even read partner master data. Tests:
`tests/test_partner_routes_guarded.php`. **Note:** coordinators are allowed by tier here
(they lack `mod.clients.edit` by default but must onboard during intake) — grant them the
edit module explicitly if you prefer ability-only gating.

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

## R3 — HIGH · A per-user permission set REPLACES role defaults (silent lock-out) — **MITIGATED**
**What:** if a login has any custom `permissions` value, it *replaces* the role defaults
entirely (`access.php` `ua()`/`user_effective_perms()`). Editing a login and saving with
fewer boxes ticked used to drop them to exactly what was ticked.
**Why it mattered:** this had locked the founder out of Users/Settings; recovery needed
server/DB access. Worse, a *branch-level* admin only sees a safe subset of permissions, so
their save silently wiped every permission outside that subset — including ones they could
not even see.
**Fixed this session (four parts):**
1. **One source of truth** — `user_effective_perms($row)` resolves a login's real set, and
   `ua()` now calls it, so what the editor sees is exactly what the gate enforces.
2. **Effective pre-tick** — the access editor pre-ticks that effective set for an existing
   login (a legacy set with no module perms shows its resolved modules too), so a **no-op
   save preserves access** instead of narrowing it.
3. **No silent lock-out on save** — `assignable_permissions($globalMgr)` is the SINGLE set an
   editor may toggle (shared by form and handler); on save, any permission the login holds
   **outside** that set is **preserved**, never dropped. A branch admin can no longer wipe
   permissions they cannot see.
4. **Confirm on removal** — un-ticking a module the login currently holds asks first (skipped
   when "Reset to role default" is on).
   Plus the existing "Reset to role default" control. Tests: `tests/test_perms_no_lockout.php`.
   (`MASTER_ADMIN` remains un-lockable via `is_superuser` — keep one, per the recovery note below.)

## R4 — HIGH · Contract registration has two doors, one unguarded — **FULLY CLOSED**
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
**Lifecycle door closed (Revamp P5b):** a contract created through this door is now
registered as **PENDING** (`open_status='PENDING', is_active=0`) — the same two-signature
lifecycle as the CRM path — so it must be endorsed by a manager and approved by the branch
manager before it goes live. No single person can put a live contract on the books from
either door. (`index.php` partner-add; `tests/test_contract_backdoor_guard.php`.)

## R5 — MEDIUM · Voucher: reopen from any state + no segregation of duties — **FIXED**
**What:** `voucher` reopen had **no source-status guard** — a coordinator could revert **any**
voucher, including **PAID**, to DRAFT. And **approve** was done by the same
`is_coordinator_level()` who could submit — no maker≠checker split (unlike contracts and
reports).
**Why it mattered:** a PAID voucher could be silently reopened and altered; one person could
both submit and approve an expense claim.
**Fixed this session (`ops.php` voucher-status):**
- **Reopen guard** — a **PAID** voucher reopens only for `is_admin_level()` (a real manager),
  not any coordinator; SUBMITTED/APPROVED still reopen by coordinator-level; reopening clears
  the recorded submitter.
- **Segregation of duties** — the submitter is recorded (`vouchers.submitted_by`), and approve
  now requires the approver to be **neither the claimant** (`!voucher_owner_is_me`) **nor the
  submitter** (`submitted_by !== me`). Legacy vouchers with no recorded submitter still approve
  (graceful). The Approve button also hides from those two, with an in-place "maker ≠ checker"
  note. Tests: `tests/test_voucher_sod_and_reopen.php`.

## R6 — MEDIUM · Call `op_status` was a free-form picker with no transition rules — **FIXED**
**What:** `call-status` let any `tosrm_can_edit()` user set **any** of 15 `CALL_STATUSES`,
validated only for set-membership — no rule about which state may follow which.
**Why it mattered:** a call could be moved to a nonsensical state (e.g. CLOSED then back to
RECEIVED, or a cancelled call silently revived), undermining any reporting built on `op_status`.
**Fixed this session (`tosrm.php`):** the active statuses carry a forward **rank**
(`tosrm_status_rank`); `tosrm_allowed_next()` / `tosrm_can_transition()` allow forward /
same-phase moves, ON_HOLD or CANCELLED from any live status, REJECTED only during intake, and
give the three terminal states (CLOSED / REJECTED / CANCELLED) **no exit**. `tosrm_set_status`
rejects an illegal step; the picker now lists only the legal next steps. A manager
(`is_admin_level`) may still **override with a reason**, recorded as `[override]` in the status
history. Tests: `tests/test_call_status_transitions.php`.

## R7 — MEDIUM · SR_INSPECTOR did not get the inspector field UI — **FIXED**
**What:** `is_inspector()` matches the literal `INSPECTOR` only, so a `SR_INSPECTOR` got the
desktop area rail and the **non-inspector** dashboard — **no** My Jobs, site check-in, or
My Voucher.
**Why it mattered:** a senior inspector who also does field work was missing the phone-first
field tools.
**Fixed this session:** new `is_field_inspector()` (`ops.php`) recognises **INSPECTOR,
SR_INSPECTOR, or any non-management login seated on an inspector record** (a manager keeps the
desk UI even if linked). The field-tool triggers now use it — the navigation rail
(`layout_top.php`), the inspector dashboard branch (`dashboard.php`), the nav index
(`navindex.php`), the "my voucher" route (`ops.php`), own-job **site check-in** and bill
upload (`job_detail.php`, `bills.php`), site-ops (`pdso.php`), and the phone-first job-view
declutter (R7 + the inspector declutter share one predicate). `is_inspector()` stays the
strict literal-role check for strict-role behaviour. Tests: `tests/test_sr_inspector_field_ui.php`.

## R8 — LOW-MED · BRANCH_APP_MANAGER held IDEMS config perms but not `mod.idems.view` — **FIXED**
**What:** the role had `idems.type.manage`/`idems.timestamp.edit` but no `mod.idems.view`, so
there was no Reporting rail item; the config screens were reachable only via Admin tiles.
**Fixed this session:** `module_defaults('BRANCH_APP_MANAGER')` now includes `idems` in its
**view** set (read-only — not edit), so the Reporting module and its config screens appear on
the rail (`access.php`). Tests: `tests/test_bam_idems_view.php`. (Existing BAM logins that
already carry a custom permission set keep it until re-saved — the per-user override rule.)

## R9 — LOW-MED · Concurrent edit with no record locking — **FIXED (call/job edit)**
**What:** a call/job could be edited by a coordinator and a manager with no lock, so
last-write-wins quietly overwrote the other party's change.
**Fixed this session:** optimistic locking on call and job edit. Each carries a version
token (`calls.updated_at` / `jobs.updated_at`, stamped by `touch_row_version()` on every
save); the edit form embeds it as `row_version`, and `stale_edit_block()` refuses a save
whose baseline no longer matches — the editor is told to reopen, and **nothing is
overwritten**. An empty baseline (older form) never blocks. Tests:
`tests/test_optimistic_lock.php`.
**Still open (noted, not a call/job edit):** the *cost picture* is still written from two
sides — job-closure expenses (coordinator) and the inspector's voucher — which is a
data-model overlap rather than a concurrent-edit race; left as-is unless it bites.

## R10 — LOW · Vestigial statuses/fields the code never advances — **NOTED IN-CODE**
**What:** legacy `calls.status` never reaches `CLOSED` in app flow (only up to ALLOCATED);
`jobs.stage` only ever becomes `CANCELLED` (plus the default ALLOCATED), never the
intermediate `JOB_STAGES`; `report_docs` `ARCHIVED` is never set.
**Why it matters:** anyone reading the schema will assume lifecycles that don't run — e.g.
the ops-desk `report_pending` metric counts `jobs.stage='REPORT_PENDING'`, a value nothing
ever writes, so it is always 0.
**Done this session (the doc's "at minimum, note in-code"):** each is annotated at its
definition — `JOB_STAGES` (`ops.php`), `IDEMS_STATUS` ARCHIVED (`idems.php`), and the legacy
`calls.status` note (`tosrm.php`) — plus an inline note on the dead `report_pending` read.
Values are kept (not removed) to avoid a silent behaviour change; wire the transitions before
building reporting on them. Tests: `tests/test_vestigial_fields_noted.php`.

**Revamp P5 (the always-0 reader fixed):** the ops-desk `report_pending` metric
(`tosrm_ops_metrics`, `tosrm.php`) now reads the **real** signal — a CLOSED job whose report
is awaiting the reporting manager's sign-off (`closed_flag=1 AND report_approval='PENDING'`) —
instead of the vestigial `jobs.stage='REPORT_PENDING'`. It is no longer always 0.
The vestigial `jobs.stage` / `calls.status=CLOSED` / `report_docs.ARCHIVED` *fields* are still
kept-and-noted (removing or advancing them would add transitions — out of scope without sign-off);
only the misleading reader was corrected. Test: `tests/test_report_pending_metric.php`.

## R11 — LOW · Admin area appears for roles with no real admin access — **MOSTLY FIXED**
**What:** ASST_MANAGER reached the Admin area only via the coordinator-level "SLA targets"
tile; MARKETING_MANAGER only via the `crm.template.manage` "Document templates" tile.
**Fixed this session (`areas.php`):**
- **SLA targets** moved to **Quality** (a service-delivery setting), so ASST_MANAGER (and
  coordinators) reach it there and it no longer pulls them into Admin.
- **Document templates** moved to **Sales** for `crm.template.manage` holders; the Admin copy
  is now `idems.type.manage`/master only. A MARKETING_MANAGER reaches templates under Sales
  and no longer sees Admin.
- The Admin subtitle now reads "For administrators: …" so the label is honest.
- ASST_MANAGER and MARKETING_MANAGER no longer see the Admin rail entry. Tests:
  `tests/test_admin_area_honesty.php`.
**Residual (documented, not fixed):** COORDINATOR still sees Admin because they hold
`mod.masters.view` (the read-only Masters tile). Fully separating Masters would either move it
to Directory — which breaks BRANCH_APP_MANAGER, who edits masters but has no Directory area —
or drop coordinators' masters-view (a permission change out of scope here). Left as a
legitimate reference read; revisit if the Masters IA is reworked.

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
