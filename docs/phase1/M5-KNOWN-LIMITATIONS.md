# Milestone 5 — Known Limitations

Everything M5 did **not** close, stated openly. None of these is a regression:
each is a boundary that existed before M5 and still exists after it. They are
recorded so the next milestone starts from fact rather than from an assumption
that runtime enforcement is now total.

---

## L1 · Permissions that are not module permissions are not licence-checked

**What it is.** The licence engine applies only to permissions named
`mod.<module>.<action>`. Older fine-grained permissions such as
`idems.finalize`, `idems.type.manage`, `finance.reconcile`, `data.credit` and
`ops.job.close` are **not** licence-gated — they are RBAC only.

**What it means.** A person holding `idems.finalize` in a company with no
Reporting entitlement passes that particular check.

**Why it is not exploitable today.** Every screen reachable through one of these
permissions is behind a route that the module gate refuses first. The request
never arrives. This is a defence-in-depth gap, not an open door.

**Recommended fix.** Bring these permissions under the same ownership map, so
the licence question is asked for every permission rather than for the `mod.*`
family only. That is a registry change, not a runtime one — it belongs with the
module registry, not here.

---

## L2 · The family table names families, not every route

**What it is.** `ops_module_family()` claims routes by a hand-verified prefix
list. A route whose name does not start with one of those prefixes is not
classified.

**Why it was built this way.** An auto-derived recogniser was implemented and
measured first. It produced wrong inferences on real routes — `issue-licence`
read as the NCR register, `approval-rules` read as Sales. Wrongly blocking a
paying customer's working screen is as serious a defect as wrongly allowing an
unpaid one, so the shipped table only claims what was verified by reading.

**Residual risk.** A **new** route added in future, in a paid family, whose name
does not begin with a listed prefix, and which nobody adds to the explicit map,
would be ungated.

**Recommended control.** A test that fails when a dispatched route is claimed by
neither the map nor the family table would make this impossible to reintroduce
silently. It is a build-time guard, and was not in M5's scope.

---

## L3 · One master site is intentionally unchanged

`lib/areas.php:206` reads:

```php
if (can('mod.invoicing.view') && (can('finance.reconcile') || can('data.credit') || is_master()))
```

Entitlement is already established by the `&&` before master picks among
sub-permissions. This is master authority applied **after** the question, which
is the behaviour M5 exists to produce. Changing it would have been change for
its own sake.

---

## L4 · `is_master_of()` on a site that names a core module

Where a screen legitimately admits several kinds of user — for example the
vendor register, which admits Reporting staff *and* client/vendor staff — master
authority is retained through the core module named at that site. A master is
therefore still granted there even without Reporting.

This is correct rather than a leak: the screen admits `mod.clients.edit` holders
independently of Reporting, and the route itself is gated on Reporting, so a
non-entitled company never reaches the page. It is recorded because it is the
one place where "master is denied in an unbought module" has a documented
exception.

---

## L5 · Menu visibility versus route enforcement

M5 enforces at the **gate**. Menu and launchpad visibility uses the same map
through `ops_module_gate($route, true)` in read-only mode, so the two agree for
mapped routes. Panels rendered by other means — a dashboard tile built from a
direct permission check, for instance — may still appear for a module the
company has not bought, even though clicking through is refused.

**Impact.** Cosmetic, and one-directional: a customer may see a doorway that
will not open. Nothing is disclosed and nothing is reachable. Tidying tile
visibility is presentation work, not enforcement work.

---

## L6 · MySQL was not tested

The suite ran on SQLite. MySQL/MariaDB was not available in this environment and
was not exercised. M5 adds no SQL, no schema change and nothing engine-specific,
so the logic is identical on either engine — but this is stated as a fact about
what was run, not as a claim about what was proved. See
`M5-TEST-RESULTS.md` §2.

---

## L7 · Not yet verified on the live server

Everything above was verified in the test environment. The live workspace on
`operations.mghaiapps.com` has not been re-uploaded with these changes. Until it
is, `deploy-check.php` on the live site is the instrument that confirms the
uploaded files match this code — its checksums were regenerated as part of M5.
