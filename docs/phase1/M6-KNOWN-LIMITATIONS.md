# Milestone 6 — Known Limitations

What M6 did **not** close, stated openly, plus one correction to what M5 claimed.

---

## C1 · Correction to M5 limitation L5 — it was not cosmetic

M5 recorded that panels rendered outside the router "may still appear for a
module the company has not bought", and judged the impact **cosmetic**:
*"a customer may see a doorway that will not open. Nothing is disclosed and
nothing is reachable."*

**That was wrong, and M6 found it.** Three of those pre-gate paths did more than
draw a tile:

- the HR placement-fee panel **wrote to the database**
  (`confirm_lapsed_placement_fees()` flips provisional fees to confirmed);
- the receivables panel **read the Money ledger**, gated only by RBAC
  permissions that never ask a licence question;
- the attention tiles **queried** Sales quotations and HR interviews.

All three are closed. The lesson is recorded rather than quietly fixed: a panel
that runs before the gate is an execution path, not decoration, and "cosmetic"
should not have been concluded without checking what each panel executes.

---

## L1 · Permissions that are not module permissions — narrowed, not eliminated

M5's L1 stands: the licence engine applies to permissions named
`mod.<module>.<action>`, so older permissions such as `idems.finalize`,
`crm.quote.approve`, `ops.job.close` and `data.profitability` are RBAC-only.

M6 closed the instances that sat on a **pre-gate** path — `ar_can()`
(`finance.reconcile`, `data.credit`), `crm.contract.register`, and the Sales
attention tile (`crm.quote.create`) — because there the route gate is not behind
them.

**What remains:** the same permissions elsewhere, where a route gate does refuse
first. Still defence-in-depth rather than an open door, and still best fixed at
the registry by bringing these permissions under the ownership map, rather than
patching call sites one at a time.

---

## L2 · Dashboard operational widgets

`$opsUser` and `$canSched` in `views/dashboard.php` are built from `ops.*`
workforce permissions, which are not module permissions. They control whether the
operational widgets are offered.

Left unchanged deliberately: they gate **visibility**, the underlying counts are
Operations counts on an Operations-centric dashboard, and a company without
Operations that has People & hiring is already sent to the recruitment home
instead. Changing them would be a dashboard redesign, which is protected
architecture and outside M6.

---

## L3 · Cron and background jobs — deferred to M7/M8

`cron.php` (daily reminders, expiry notices, the MIS digest) and `cron_ads.php`
(Ads Pro lead sync, which touches Sales data) run outside any request and outside
any gate. They are inventoried in the matrix and **deliberately untouched**,
because §14 of the M6 instruction places cron and background-job enforcement in
later work.

**Residual risk:** a company that has stopped paying for Sales may still have
leads synced by `cron_ads.php`, and may still receive module-specific reminder
e-mails. Nothing new is exposed to a user through the application; the leak is in
automated background activity.

---

## L4 · Public sign-up — deferred to M7/M8

`get-started` (`tenant_signup_route`) is public workspace onboarding. It is off
by default until the operator enables it, and it creates a PENDING application
for Super-Admin approval rather than a live workspace. Named in §14 as later work
and left alone.

---

## L5 · Export and report generation

Export routes (`recruit-export`, `quotes-export`, `tally-export` and similar) are
dispatched through `ops_dispatch()` and so are gated by M5's route gate. A
dedicated export-enforcement pass — checking what a given export *contains*
rather than which route produced it — is M7/M8 work and was not attempted.

---

## L6 · The portal permission maps are hand-written

`PORTAL_PERM_MODULES` and `VENDOR_PERM_MODULES` were written by reading what each
permission actually serves. A **new** portal permission added later, and not added
to its map, would fall through as "not module-bound" and be governed by the
portal's own permission alone.

That default was chosen deliberately: the alternative — refusing anything
unmapped — would break the marketplace keys, which are legitimately not bound to
a product module. A test asserting that every key in `PORTAL_PERMS` appears in
`PORTAL_PERM_MODULES` would close this properly; it is a build-time guard and was
not in M6's scope.

---

## L7 · MySQL was not tested

SQLite only. No MySQL/MariaDB server exists in this environment — verified, not
assumed. M6 adds no SQL and no schema change. See `M6-TEST-RESULTS.md` §2.

---

## L8 · Not yet verified on the live server

Everything here was verified in the test environment. The live workspace on
`operations.mghaiapps.com` has not been re-uploaded with M5 or M6.
`deploy-check.php` — regenerated in this milestone — is the instrument that
confirms the uploaded files match this code.
