# Gaps and Risks

The honest list. Every item was found by reading the code, and every one carries the
`file:line` where you can check it.

**This session made no code changes.** Each item states a recommended fix; none has
been implemented.

---

## How these are ranked

By **risk**, which means *likelihood × consequence*, not by how interesting the bug
is. An item is high if it could plausibly happen in an ordinary week and would cost
you money, data, or an accreditation finding.

| Band | Meaning |
|---|---|
| 🔴 **Critical** | Could hand out access nobody intended, or let money move without control. Fix before hiring anyone you do not know well. |
| 🟠 **High** | A real boundary that does not hold. Someone will find it by accident. |
| 🟡 **Medium** | Works today, will bite during a change or an audit. |
| 🔵 **Low** | Untidy or confusing rather than dangerous. |

---

## Status — four of these are now fixed

Risks **1, 2, 3 and 7** were fixed in commit `docs+fix` on this branch, with 56 new
assertions in `phpapp/tests/test_critical_access_fixes.php`. Each is marked
**✅ FIXED** below, with what changed and what it means for you. Risk **11** was
partly resolved as a necessary consequence of fixing 3.

The remaining sixteen are untouched and still describe the code as it stands.

**Two things are deliberately excluded.** Items recorded in `phpapp/PENDING.md` are
known and intentional — they are not repeated here as if they were discoveries.
Where a finding is a *new instance* of a pattern `PENDING.md` already names, that is
said explicitly.

---
---

# 🔴 Critical

---

## 1. An unrecognised role silently becomes a full administrator

**Where:** `phpapp/lib/access.php:408` (and the same pattern at
`phpapp/lib/ops.php:543`)

```php
if (!isset(ORG_ROLES[$role])) $role = 'ADMIN';
```

`ADMIN` is granted **every permission and every module, across every office and
business unit** (`phpapp/lib/access.php:362-363`, `phpapp/lib/access.php:253`).

**What goes wrong.** A typo in a user's role field, a role removed in a future
version, a bad CSV import, a hand-edited database row — any of these does not lock
the account down. It hands out full company-wide access, silently, with no warning
anywhere in the interface.

**Why this is ranked first.** Every other item on this list is a boundary that does
not hold for a role you *chose*. This one hands out administrator rights for a role
you did *not* choose. The model fails open at exactly the point where it must fail
shut.

**How likely is it, really?** More likely than it sounds. `role` is a plain string
column with no foreign key and no constraint. Any process that writes a user record —
import, SSO provisioning, a future migration — can produce a value not in the list.

### ✅ FIXED

An unrecognised role now resolves to `UNKNOWN_ROLE` (`phpapp/lib/access.php:31`), a
sentinel that is deliberately **not** a member of `ORG_ROLES` — so it matches no
case in `role_defaults_base()` or `module_defaults()` and carries no permission, no
module, and no scope beyond "own". Both resolution points were changed and now
agree: `ua()` (`phpapp/lib/access.php:440`) and `user_role()`
(`phpapp/lib/ops.php:552`).

Three further details:

- **A per-user permission list no longer rescues a broken role**
  (`phpapp/lib/access.php:446`). If the role column holds something this version
  does not understand, the account's configuration cannot be trusted as a whole.
- **The account can still sign in**, so somebody can be told what is wrong. It
  simply cannot do anything.
- **It is written down.** Each affected account is logged once per request, to the
  error log and to the compliance audit trail
  (`access_note_unknown_role()`, `phpapp/lib/access.php:418`), and the screen now
  labels the role "Unrecognised role — no access" rather than "User"
  (`phpapp/lib/ops.php:557`).

> ⚠ **Before you deploy:** audit for users whose role is not one of the sixteen.
> They have been running as full administrators and will now have **no access at
> all**. `SELECT id, username, role FROM users WHERE role NOT IN (…the sixteen…)`.
> This is the intended behaviour, but it is a lockout, so find them first.

**Original recommendation (now implemented).** Fall back to a **no-permission** role rather than to `ADMIN`.
The `role_defaults_base()` function already ends with exactly that
(`phpapp/lib/access.php:397`: empty permissions, `OWN` scope) — the fallback should
land there. Then either retire `ADMIN` entirely or make it a real, narrow role.
Audit for existing `ADMIN` users first, because retiring it will lock them out.

---

## 2. Business partners and purchase orders have no permission checks at all

**Where:** `phpapp/index.php:907-1220` — the routes `partner-new` (`:907`),
`partner-edit` (`:997`), `partner-add` (`:1031`), `partner` (`:1140`) and `po`
(`:1164`)

**What is missing.** Three hundred and thirteen lines of handler code containing
**zero** permission checks — no `can()`, no `ops_require()`, no role test of any
kind. And because these routes are handled *before* `ops_dispatch()` at
`phpapp/index.php:1221`, the central module gate never runs for them either. Neither
`partner-new`, `partner-edit`, `partner-add`, `partner` nor `po` appears in the gate
map (`phpapp/lib/ops.php:2242-2377`).

**Verify it yourself:**

```bash
awk 'NR>=907 && NR<=1220' phpapp/index.php | grep -c "ops_require\|can("
# returns 0
```

**What goes wrong.** Any signed-in user can create and edit clients and vendors, and
open and edit any purchase order — including pulling line items and values across
from a quotation (`phpapp/index.php:1169-1177`). **This includes an `INSPECTOR`**,
whose only module is Reports.

Note the contrast: the *list* screens at `phpapp/index.php:879` are correctly gated
on `mod.clients.view` / `mod.vendors.view`. Someone protected the register and left
the record editor open — which is why this has probably gone unnoticed: the menu
does not offer it, so nobody stumbles in. A typed or bookmarked URL reaches it.

### ✅ FIXED

All five routes now carry a guard (`phpapp/index.php:908`, `:1001`, `:1036`,
`:1146`, `:1173`), built on four small predicates in `phpapp/lib/ops.php:614-651`
so the rules are testable without a browser:

| Route | Now requires |
|---|---|
| `partner-new` | `partner_can_create()` — edit on clients **or** vendors |
| `partner-edit`, `partner-add` | `partner_can_edit($p)` — edit on a module the record belongs to |
| `partner` (detail) | `partner_can_view($p)` |
| `po` | `po_can_view($po)` on GET, `po_can_edit($po)` on POST — it borrows its partner's access |

A partner record can be a client, a vendor, or both, so **holding either module is
enough** for a dual-role record: refusing a vendor-module user because the company
also happens to be a client would hide half the directory from them. A record with
no role set yet needs either module rather than none — "no role" must not read as
"no permission required".

> ⚠ **One workflow changes.** A `COORDINATOR` holds clients/vendors **view**, not
> edit, so the "+ Add new" quick-add beside a client dropdown now refuses them. That
> matches the documented design — Finance registers the client and contract before
> operations begins — but if your coordinators do create clients in practice, grant
> them `mod.clients.edit` under Settings → Roles & access rather than reverting this.

**Original recommendation (now implemented).** Add `mod.clients.edit` / `mod.vendors.edit` checks to the
partner write routes and a gate to `po`.

---

## 3. Anyone in the management tier can approve and pay any voucher, anywhere

**Where:** `phpapp/lib/ops.php:4863`, `:4864`, `:4961-4977`

Three problems compound here, which is why this is critical rather than high.

**3a — No module gate.** `vouchers` does not appear in the route gate map at all
(`phpapp/lib/ops.php:2242-2377`). Access is decided purely by role tier. So
`BRANCH_APP_MANAGER` and `ASST_MANAGER` — who have **no voucher module access
whatsoever** — can view, approve and mark paid every voucher.

**3b — No branch filter.** The register query carries no office or business-unit
clause (`phpapp/lib/ops.php:4864`):

```sql
SELECT v.*, i.name inspector_name FROM vouchers v
LEFT JOIN inspectors i ON i.id=v.inspector_id ORDER BY v.month DESC, i.name
```

The `scope_clause()` machinery that exists for exactly this
(`phpapp/lib/access.php:479-496`) is not applied. A coordinator in one branch sees
every inspector's day-rates and expense claims company-wide. Everything else nearby
scopes properly — the dashboard does it two files away
(`phpapp/views/dashboard.php:46-52`) — which is what makes this look like an
oversight rather than a decision.

**3c — No separation of duties.** Submit, approve and mark-paid all accept
`is_coordinator_level()` (`phpapp/lib/ops.php:4962`, `:4965`, `:4970`). One
coordinator can carry a claim from creation to paid with no second pair of eyes.
Meanwhile **Finance, who actually pays, cannot mark anything paid** — they are not
in the tier.

**What goes wrong.** Inspector pay can be created, approved and marked paid by one
person, for any branch, with no audit trail (see risk 12). This is the classic shape
of an expense fraud, and it is also the kind of finding an external auditor writes
down.

### ✅ FIXED

All three parts, plus the screen that has to agree with them.

**3a — the module is now checked.** Ten voucher routes were added to the gate map
(`phpapp/lib/ops.php:2357-2364`). An engineer holds no voucher module, so an
owner exception mirrors the existing job one — a named allowlist, ownership checked
per record (`phpapp/lib/ops.php:2467-2479`, predicate at `:4798`). Every link and
form on the voucher screen is in that allowlist, verified by a test, so no button
is offered that then refuses.

**3b — the register is scoped.** It now filters on
`COALESCE(v.office_id, i.home_office_id)` (`phpapp/lib/ops.php:4998`) — a voucher
carries its own office, and where it does not the engineer's home office stands in,
the same fallback the month-freeze check already used.

**3c — approval is separated from preparation.** A new `submitted_by_uid` column
(`phpapp/lib/ops.php:457`) records who put the claim forward, and
`voucher_can_approve()` (`phpapp/lib/ops.php:4842`) refuses that same person.
Anyone else at the same level still may — so a branch with two coordinators needs
no manager, and a branch with one does. `voucher_can_mark_paid()`
(`phpapp/lib/ops.php:4851`) now also accepts `finance.reconcile`, and the register
opens for accounts (`phpapp/lib/ops.php:4990`), so **the people who actually pay
can record payment** — which they could not before.

The screen was changed to match: the approve button is offered only to somebody who
may actually approve, and the submitter is told why it is not there
(`phpapp/views/ops/voucher_detail.php:215-222`).

> ⚠ **Two roles lose voucher access**, correctly but noticeably.
> `BRANCH_APP_MANAGER` and `ASST_MANAGER` hold **no voucher module** and were
> reaching the screens only because the module was never checked. They are now
> refused. That matches the documented design; if your Assistant Managers do handle
> vouchers, grant them `mod.vouchers.view` under Settings → Roles & access.
>
> **Vouchers already submitted are unaffected** — their `submitted_by_uid` is empty,
> so nobody is barred from approving work that is already in flight.

**Still open after this fix:** `BUSINESS_DIRECTOR` and `SBU_HEAD` hold
`mod.vouchers.view` and remain in the tier, so they can still approve. That is
risk 4, which is not addressed here.

**Original recommendation (now implemented).** Add `vouchers` to the gate map; apply
office scope to the register; split approval from preparation and grant
mark-paid to `FINANCE`.

---
---

# 🟠 High

---

## 4. Three "read-only" roles can allocate work and approve pay

**Where:** `phpapp/lib/access.php:27` combined with `phpapp/lib/ops.php:548`,
`:5136`, `:4863`

`is_coordinator_level()` admits nine roles — the seven in `MGMT_ROLES` plus
`ASST_MANAGER` and `COORDINATOR`. Job allocation and voucher approval are gated on
that tier. Three of those nine should never be near daily operations:

| Role | Documented as | Can actually |
|---|---|---|
| `BUSINESS_DIRECTOR` | "view on every module, edit on none" | Allocate, edit, reassign and close jobs; approve and pay any voucher |
| `SBU_HEAD` | read-only on all nineteen modules | The same |
| `BRANCH_APP_MANAGER` | custodian with no operational role and no voucher module | The same |

**Verify it yourself:** the tier is `MGMT_ROLES` + two
(`phpapp/lib/ops.php:548`); `MGMT_ROLES` contains `BUSINESS_DIRECTOR` and `SBU_HEAD`
(`phpapp/lib/access.php:27`); job allocation asks only for the tier
(`phpapp/lib/ops.php:5136`); the module gate asks only for `mod.jobs.view`
(`phpapp/lib/ops.php:2389`), which both hold.

**Why it happened.** `MGMT_ROLES` was recently narrowed to fix a real problem —
sales and finance roles were seeing operational widgets (commit `ff0b94a`,
`phpapp/lib/access.php:31-27`). That fix was correct. But the list is still a
*seniority* list being used as an *operational* list, and the oversight roles were
left in.

**Recommended fix.** Introduce a separate `OPS_ROLES` constant containing only the
roles that actually run operations — `BRANCH_MANAGER`, `OPERATION_MANAGER`,
`ASST_MANAGER`, `COORDINATOR`, plus the administrators — and point
`is_coordinator_level()` at that. Leave `MGMT_ROLES` for what it means: seniority.

---

## 5. The permissions that name operational actions do not control them

**Where:** `phpapp/lib/ops.php:5136` and `:5531`

Two of the four `ops.*` permissions are **never checked on the routes they name.**

```bash
grep -rn "ops\.job\.close" phpapp/lib phpapp/views phpapp/index.php
```

Every hit is a role default, a permission-catalogue entry, a dashboard-widget
decision, or a report-writing eligibility test. **Not one is on the job-close
route.** `ops.job.allocate` is the same story.

| Action | What appears to govern it | What actually governs it |
|---|---|---|
| Allocate / edit a job | `ops.job.allocate` | `is_coordinator_level()` (`:5136`) |
| Close a job | `ops.job.close` | **nothing** — `mod.jobs.view` or job ownership (`:5531`) |
| Reassign a job | `ops.job.allocate` | `is_coordinator_level()` (`:5663`) |

**What goes wrong.** Granting or removing these permissions changes almost nothing,
while appearing in the admin interface as though it does. `ASST_MANAGER` is the live
example: `ops.job.close` was deliberately withheld from it
(`phpapp/lib/access.php:377`) and the role closes jobs anyway. An administrator
tightening access will believe they have done something they have not.

For the record, the other two are properly enforced: `ops.call.create`
(`phpapp/lib/ops.php:3695`) and `ops.call.delete` (`phpapp/lib/ops.php:3588`).

**Recommended fix.** Add the real checks — `can('ops.job.allocate')` on new/edit/
reassign, `can('ops.job.close')` on close (alongside the existing owner path for
inspectors). Grant them to the tier that holds them today so nobody loses access on
the day of the change.

---

## 6. The central route gate only ever asks about viewing

**Where:** `phpapp/lib/ops.php:2389`

```php
if ($mod && !can("mod.$mod.view")) { ... refuse ... }
```

`mod.<module>.edit` is never consulted anywhere in the gate. The map covers about
three hundred routes, including destructive ones — `call-delete`, `job-close`,
`ncr-close`, `capa-close`, `iddoc-reveal`, `iddoc-redact`, `document-delete`.

**What goes wrong.** Write protection depends entirely on each handler adding its own
second check. Most do. Where one does not, **view access is write access** — and the
administrator granting "view only" has no way to know which is which.

This is the root cause of risks 2 and 5 rather than a separate problem, but it is
listed on its own because the fix is architectural: every future route inherits the
weakness unless the gate itself changes.

**Recommended fix.** Extend the map so each route declares the *action* as well as
the module (`['jobs','edit']`), and have the gate require `mod.jobs.edit` for those.
It can be done incrementally: default everything to `view` as today, and tighten the
write routes module by module.

---

## 7. Per-user permission overrides cannot physically fit in the column that stores them

**Where:** `phpapp/lib/access.php:723`

```php
ensure_column('users', 'permissions', "VARCHAR(600) DEFAULT ''");
```

The override is a comma-separated list of permission keys. There are **98 keys**, and
the full list is **1,724 characters**. About **34 keys fit**.

**Verify it yourself:** the catalogue is `PERMISSIONS`
(`phpapp/lib/access.php:31-84`, 36 entries) plus two per module
(`phpapp/lib/access.php:167-202`, 31 modules) = 98.

**What goes wrong.** On MySQL in non-strict mode the value is silently truncated —
and truncation lands mid-key, so the final permission becomes a meaningless fragment.
In strict mode the save fails outright. Either way, an administrator hand-tuning a
senior user's permissions gets a wrong answer with no error to explain it.

**Why it has not bitten yet:** per-user overrides are the exception, and the roles
that need many permissions (`MASTER_ADMIN`) do not use the column — they get
everything from the bypass.

### ✅ FIXED

The column is now `TEXT`, both for new installs and for existing ones
(`phpapp/lib/access.php:769-782`). MySQL will not take a `DEFAULT` on `TEXT`;
nothing relies on one, because every read of this column already coalesces a
missing value to `''` — checked across `access.php`, `datacontrol.php`,
`identity.php`, `ops.php` and `user_form.php`.

A test stores the complete 98-key list and reads it back byte-for-byte, then
asserts the **last** key in the list still works — truncation would have cut it
mid-word.

> ⚠ **Check for damage already done.** This widens the column; it cannot recover
> what MySQL already truncated. Any row at or near 600 characters was silently cut
> and its final permission is a meaningless fragment:
> `SELECT id, username, LENGTH(permissions) FROM users WHERE LENGTH(permissions) > 560;`
> Re-save those users' permissions from the admin screen.
>
> **SQLite installs were never affected** — SQLite ignores the declared length.

**Original recommendation (now implemented).** Widen the column to `TEXT`.

---

## 8. The Branch Application Manager's two signature powers are unreachable

**Where:** `phpapp/lib/access.php:261-262` against `phpapp/lib/ops.php:2293-2295`

The role holds `idems.type.manage` and `idems.timestamp.edit`. The code names it as
the **only** role that may edit a locked timestamp
(`phpapp/lib/access.php:370-371`).

The role has **no IDEMS module access** — its modules are `masters`, `overheads`,
`users` (edit) and `calls`, `jobs`, `reports` (view). Every screen those two
permissions unlock — `report-types`, `irn-rules`, `document-timestamp` — is gated on
`mod.idems.view` (`phpapp/lib/ops.php:2293-2295`), which the role does not have.

**What goes wrong.** The one person who is supposed to be able to correct a locked
timestamp cannot reach the screen. When a genuine correction is needed, the only
route is a Master Admin — which defeats the separation the role was created for.

**Recommended fix.** Add `idems` to this role's module view defaults. Its edit rights
on report configuration already come from the fine-grained permissions, so view
access on the module is enough and grants nothing extra.

---

## 9. A screen exists that nobody can open, gated on permissions that do not exist

**Where:** `phpapp/lib/ops.php:2407` and `phpapp/lib/hwpoints.php:75-76`

The Hold & Witness Points routes are gated on `mod.hold-points.view` — and
`hold-points` **is not in the module catalogue** (`phpapp/lib/access.php:167-202`),
so the permission cannot be granted to anyone. Only `MASTER_ADMIN` passes, via the
bypass in `can()`.

**Verify it yourself:**

```bash
grep -c "'hold-points' *=>" phpapp/lib/access.php   # 0 — not a real module
grep -n "'hold-points'=>'hold-points'" phpapp/lib/ops.php   # the gate
```

Meanwhile the menu tile is gated on something entirely different —
`hwp_can_view()`, which any user with report access satisfies
(`phpapp/lib/hwpoints.php:75`). **So the tile appears in Quality & Accreditation for
most users, and clicking it bounces them to the dashboard with "you don't have
access".**

**A second defect in the same two lines.** `hwp_can_view()` and `hwp_can_edit()` also
test `can('ops.job.view')` and `can('ops.job.edit')` — **neither permission exists**
(`grep -c "ops\.job\.view" phpapp/lib/access.php` returns 0). Those clauses are dead
conditions that are always false.

**Note:** this is a new instance of the pattern your `phpapp/PENDING.md:2285-2287`
already names as item **B1** — "a menu item that refuses reads as a broken app".

**Recommended fix.** Add `hold-points` to `ACCESS_MODULES` with a sensible label and
give it to the roles that need it; or map the routes onto the `jobs` module, which is
where hold points conceptually belong. Separately, delete the two dead `ops.job.*`
clauses.

---

## 10. The Senior Inspector role is declared but not implemented

**Where:** `phpapp/lib/access.php:18`, `:285`, `:391-394` — and nowhere else

`SR_INSPECTOR` appears in the role list and has defaults. **No other file in the
application tests for it.**

```bash
grep -rn "SR_INSPECTOR" phpapp/lib phpapp/views phpapp/index.php
```

Every hit outside `access.php` is either an unrelated job *designation* list
(`phpapp/lib/ops.php:31`) or seed data.

| Consequence | Cause |
|---|---|
| Gets the desk rail instead of the phone-first field menu | `is_inspector()` tests `=== 'INSPECTOR'` (`phpapp/lib/ops.php:549`) |
| Sees the whole office's records, not their own | the `self` flag tests the same (`phpapp/lib/access.php:439`) |
| **Cannot open their own voucher at all** | the register offers "mine" to `INSPECTOR` and the desk path to the tier; this role is neither (`phpapp/lib/ops.php:4857-4863`) |

**What goes wrong.** Promote your best inspector and they lose access to their own
expense claim. Not in `PENDING.md`, so not a deliberate omission.

**Recommended fix.** Either implement it — make `is_inspector()` and the `self` flag
accept both keys, and add the role to the voucher owner path — or remove it from
`ORG_ROLES` until it is ready. Until then, leave senior people on `INSPECTOR`;
naming them as an approver already works without a role change.

---
---

# 🟡 Medium

---

## 11. Finance is shown an Operations menu it cannot use

**Where:** `phpapp/views/layout_top.php:128` against `phpapp/lib/ops.php:4863`

Finance holds view access to Calls, Jobs and Vouchers, which puts **Operations** in
the rail. The voucher register then demands the management tier, which Finance was
deliberately removed from (`phpapp/lib/access.php:31-27`) — so the screen refuses.

A second oddity in the same area: **Finance cannot mark a voucher paid, but a
coordinator can** (`phpapp/lib/ops.php:4970`). The control is inverted.

**Note:** another instance of `PENDING.md` item **B1**.

### ◐ PARTLY RESOLVED, as a consequence of fixing risk 3

The voucher half is fixed: the register now opens for `finance.reconcile` holders
(`phpapp/lib/ops.php:4990`) and Finance can mark a voucher paid
(`phpapp/lib/ops.php:4851`). The inversion is gone — the people who pay can record
payment.

**Still open:** the rest of the Operations area. Finance holds view on Calls and
Jobs, so those screens appear and behave read-only, which is coherent; but the rail
item is still driven by a different rule from the screens behind it.

**Remaining fix.** Decide what Finance should see of Operations as a whole, and
drive the rail item from the same rule the screens use.

---

## 12. Voucher transitions have no audit trail, and reopen has no precondition

**Where:** `phpapp/lib/ops.php:4957-4980`

**No history.** Not one voucher transition is recorded. Compare with a call's status
change, which writes old status, new status, reason, actor and timestamp to
`call_status_events` plus the compliance log (`phpapp/lib/tosrm.php:139-141`). There
is no way to answer "who reopened this, and what did it say before?"

**Reopen accepts any state.** Unlike submit, approve and mark-paid — each of which
checks the current status — `reopen` checks only the role
(`phpapp/lib/ops.php:4974`). **A `PAID` voucher can be sent back to `DRAFT` and
edited.** Combined with risk 3c, one person can pay a claim, reopen it, change the
figures and re-approve it, leaving no record.

**Recommended fix.** Write a `voucher_status_events` row on every transition, copying
the shape already used for calls. Add a status precondition to reopen, and require a
reason. Consider refusing reopen on `PAID` entirely — reversing a payment should be a
credit, not an edit.

---

## 13. The job stage is a free dropdown, not a state machine

**Where:** `phpapp/views/ops/job_form.php:171`, saved at `phpapp/lib/ops.php:4121`

`jobs.stage` holds eight values (`phpapp/lib/ops.php:32`) and is edited as an
ordinary form field. **No transition table, no ordering, no check that one state may
follow another.** Anyone who can edit the job can set any stage at any time,
including straight to `CLOSED` — which is misleading, because the stage is *not* what
closes a job (`closed_flag` is).

**What goes wrong.** The stage looks like a lifecycle in the interface and is not one.
Reporting built on it will be wrong. This is the "transitions with no permission
guard" case in its purest form.

**Recommended fix.** Decide what the stage is *for*. If it is a status label, rename
it so nobody mistakes it for a controlled state. If it is a lifecycle, give it a
transition table and a guard, as calls have (`phpapp/lib/tosrm.php:132-143`).

---

## 14. Being senior silently substitutes for the revenue permission

**Where:** `phpapp/lib/ops.php:580`

```php
function can_see_salary()  { return can('data.salary'); }
function can_see_revenue() { return can('data.revenue') || is_admin_level(); }
```

Two adjacent lines, two different rules. `BRANCH_APP_MANAGER` — built specifically to
have full custodial power and **no sight of the commercials**
(`phpapp/lib/access.php:372`) — sees revenue anyway, because it is in the management
tier.

**Recommended fix.** Remove the `|| is_admin_level()` and grant `data.revenue`
explicitly to the roles that should have it. Every management role except
`BRANCH_APP_MANAGER` already holds it, so the change affects exactly the role it
should.

---

## 15. Two people can edit the same record with no locking

**Where:** absent throughout

```bash
grep -rn "row_version\|optimistic\|SELECT .* FOR UPDATE" phpapp/lib/ops.php
# no results
```

There is no version column, no optimistic-concurrency check and no row locking on
calls, jobs or vouchers. Two coordinators opening the same call and saving a minute
apart: **the second silently overwrites the first**, with no warning to either.

**Do not confuse this with `joblock.php`**, which is a *time-based* closure lock (a
job not closed within the grace period freezes — `phpapp/lib/joblock.php:108-115`).
That is a good feature and it addresses a different problem entirely.

**How likely?** Rises with headcount. In a branch with four coordinators sharing a
queue it will happen; with one it never will.

**Recommended fix.** Add an `updated_at` column, carry it as a hidden form field, and
refuse the save if it has changed since the form was opened — offering the user a
comparison rather than an error. Start with calls and jobs; vouchers are already
protected by the month freeze.

---

## 16. Scope defaults fail open when a user is incompletely set up

**Where:** `phpapp/lib/access.php:429` and `:435`

```php
else $offices = $u['home_office_id'] ? [(int)$u['home_office_id']] : 'ALL';  // :429
else $sbus = 'ALL';                                                          // :435
```

**Line 429:** a user whose role is office-scoped (`OWN`) but who has **no home office
set** gets `ALL` — every office in the company.

**Line 435:** a role declared `sbus => 'OWN'` with no explicit business-unit scope
gets `ALL`. That is `OPERATION_MANAGER`, `ASST_MANAGER`, `COORDINATOR`,
`MARKETING_EXECUTIVE`, `INSPECTOR` and `SR_INSPECTOR`
(`phpapp/lib/access.php:364-395`) — **six of the sixteen roles do not have the
business-unit restriction their definition claims**, unless someone sets it per user.

**What goes wrong.** A half-configured new starter sees more than intended, and
nothing in the interface says so. The role screen says "OWN"; the behaviour is "ALL".

**Recommended fix.** For offices, fall back to an empty scope (see nothing) rather
than `ALL`, and surface "no home office set" as a warning on the user screen. For
business units, decide whether `OWN` means anything without an explicit list — if
not, stop declaring it in the role defaults, because it is currently documentation
that does not match behaviour.

---

## 17. "My Jobs" is unscoped for anyone without a linked inspector record

**Where:** `phpapp/lib/ops.php:5892-5895`

If a coordinator-level user has no linked inspector record, the fallback returns
**every open job in the company**:

```sql
SELECT ... FROM jobs j ... WHERE j.closed_flag=0 ORDER BY j.scheduled_date DESC
```

No office or business-unit clause — the same omission as the voucher register
(risk 3b). The dashboard two files away does apply scope
(`phpapp/views/dashboard.php:46-52`), so the inconsistency is within the same
feature area.

**Recommended fix.** Apply `scope_clause('j.executing_office_id', 'j.sbu')` to the
fallback query, exactly as the dashboard does.

---
---

# 🔵 Low

---

## 18. A call has three competing notions of "done"

**Where:** `phpapp/lib/tosrm.php:117-128`, `phpapp/lib/ops.php:3957`, `:5413`

The legacy `calls.status` column moves automatically; the newer `calls.op_status`
moves only when a person picks from a dropdown; and the register derives "done" from
whether every job under the call is closed. They can disagree, and have — commit
`b7cdc71` (23 Aug 2026) fixed a case where the detail screen said "Done" and the
register said "In progress, not allocated, no report" about the same call.

That fix addressed the symptom. The three-way split remains.

**Recommended fix.** Retire the legacy column. `tosrm_derive_status()` already
translates it (`phpapp/lib/tosrm.php:117-124`), so a migration could backfill
`op_status` once and the old column could stop being read. Not urgent, but every
feature built on call status until then has to know which of the three it means.

---

## 19. Master-data permissions do not govern master data

**Where:** `phpapp/lib/lookups.php:635`, `phpapp/lib/ops.php:2185`

Lookups and custom fields are gated on **`is_admin_level()`** — the management tier —
not on the `master.manage` permission, which is what an administrator would
reasonably expect. Neither route is in the module gate map.

Two odd consequences:

- A **`COORDINATOR`** holds `mod.masters.view` and cannot manage lookups.
- A **`BUSINESS_DIRECTOR`**, who holds edit rights on nothing anywhere, can.

The same applies to the **inspector master** (`/m/inspectors`, access level `admin` —
`phpapp/lib/ops.php:2049`, resolved at `:2185`): a coordinator who allocates jobs to
inspectors all day cannot add one, while the board-level read-only role can.

**Recommended fix.** Point `master_access_ok('admin')` and `lk_admin()` at
`can('master.manage')`, and grant that permission to the roles that should hold it.
It is already defined and already assigned to `BRANCH_MANAGER` and
`BRANCH_APP_MANAGER` (`phpapp/lib/access.php:369-372`) — it is simply not consulted.

---

## 20. Marketing Manager has company-wide profitability

**Where:** `phpapp/lib/access.php:384`

`data.profitability` and `data.revenue`, scoped `offices => 'ALL'`,
`sbus => 'ALL'` — broader financial reach than the `OPERATION_MANAGER` who actually
delivers the work.

Entirely defensible for a commercial director. Listed only because it is the kind of
grant that is easier to inherit than to notice, and it should be a decision rather
than a default.

**Recommended fix.** Confirm it is intended. If the role is a commercial director,
leave it. If it is a marketing function, drop `data.profitability` and narrow the
scope.

---
---

# Summary

| # | Risk | Band | Where | Fix size |
|---|---|---|---|---|
| 1 | ~~Unrecognised role → full admin~~ | ✅ **fixed** | `access.php:440` | done |
| 2 | ~~Partner & PO routes ungated~~ | ✅ **fixed** | `index.php:908-1173` | done |
| 3 | ~~Vouchers: no gate, no scope, no separation~~ | ✅ **fixed** | `ops.php:2357`, `:4998`, `:4820` | done |
| 4 | Read-only roles can allocate & approve pay | 🟠 | `access.php:27` | Small |
| 5 | `ops.job.*` permissions not enforced | 🟠 | `ops.php:5136`, `:5531` | Small |
| 6 | Route gate never checks `.edit` | 🟠 | `ops.php:2389` | Architectural |
| 7 | ~~Permission column too small~~ | ✅ **fixed** | `access.php:769` | done |
| 8 | Branch App Manager's powers unreachable | 🟠 | `access.php:261` | One line |
| 9 | Hold-points unreachable; phantom permissions | 🟠 | `ops.php:2407` | Small |
| 10 | `SR_INSPECTOR` unimplemented | 🟠 | `access.php:18` | Small |
| 11 | Finance shown a menu it cannot use | ◐ partly | `layout_top.php:128` | Small |
| 12 | No voucher audit trail; reopen unguarded | 🟡 | `ops.php:4974` | Small |
| 13 | Job stage is a free dropdown | 🟡 | `ops.php:4121` | Design decision |
| 14 | Seniority substitutes for `data.revenue` | 🟡 | `ops.php:580` | One line |
| 15 | No record locking | 🟡 | absent | Medium |
| 16 | Scope defaults fail open | 🟡 | `access.php:429`, `:435` | Small |
| 17 | My Jobs fallback unscoped | 🟡 | `ops.php:5892` | One line |
| 18 | Three notions of call "done" | 🔵 | `tosrm.php:117` | Migration |
| 19 | `master.manage` does not govern masters | 🔵 | `lookups.php:635` | Small |
| 20 | Marketing Manager sees all profitability | 🔵 | `access.php:384` | Decision |

**Those four are done.** Between them they closed the gaps that could hand out
administrator rights, let anyone edit your client list, and let one person pay
themselves. The next most valuable is **risk 4** — the management tier being used as
an operations tier — because it is the root of several others and is a small change
to one constant.

---

## What is genuinely well built

It would be dishonest to end on twenty problems without saying that the same codebase
gets several hard things right — and the good decisions are commented with their
reasoning, which is rarer than it should be.

- **The licence check runs before the Master Admin bypass**
  (`phpapp/lib/access.php:452-455`). Even the superuser cannot open a module the
  customer has not bought. Deliberate, explained, correct.
- **Identity documents are stripped from every blanket grant**
  (`phpapp/lib/access.php:288-293`). "A reasonable default for revenue figures and a
  bad one for passports." Real privacy engineering.
- **The assignment console refuses inspectors before consulting any permission**
  (`phpapp/lib/tosrm.php:218-223`), so it cannot leak to the field even if an account
  acquires a stray permission. This is the model the rest of the gates should follow.
- **Job closure enforces the business rules rigorously** — the report, the bills, the
  site check-in, every visit day — and checks them on the server because "this is a
  promise made to a customer" (`phpapp/lib/ops.php:5573-5576`).
- **A closed month freezes its vouchers** (`phpapp/lib/ops.php:4715-4730`), because
  changing a day behind a completed cost run would leave figures that still add up
  and are still wrong.
- **Closing a job is idempotent** (`phpapp/lib/ops.php:5544-5551`) — a refresh or an
  offline re-send cannot double an engineer's expense claim.
- **Call status transitions are fully audited** (`phpapp/lib/tosrm.php:139-141`).
- **The ISO/IEC 17020 judgement permissions are granted to nobody by default**
  (`phpapp/lib/access.php:76-83`), so somebody has to hand them out on purpose.

The pattern across all twenty findings is consistent, and it is worth stating as one
sentence: **this application governs its business rules far more carefully than it
governs its permissions.** A job cannot be closed without its bills, its report and
its check-in — but almost anyone can be the one to close it.
