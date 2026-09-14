# Milestone 10 — Known Limitations

---

## L1 · The probe covers zero-argument gate predicates

Group D enumerates functions matching `*_can()` / `*_can_*()` that take **no
arguments**. Gates that take a record (`can_edit($doc)`, `cx_*_can_transition()`)
are not callable without a fixture and are not probed.

Those are overwhelmingly *transition* rules — "may this status move to that one"
— rather than module-access decisions, and they sit inside handlers the route
gate already covers. But it is a real edge of the audit, not a complete sweep.

**Closing it properly** would mean fixture-driven probing per record type; that
is a test-infrastructure project, not an enforcement one.

---

## L2 · The core baseline is a recorded list, not a derived one

The 52 gates in group D's baseline were each traced and classified, but the list
is written down rather than computed. If one of them is later changed to guard
something paid, the probe will still consider it expected.

The mitigation is direction-correct: the list only ever **shrinks** safely. A new
gate must be classified before it can be added to it, and adding one is a visible
edit in a security test.

---

## L3 · Non-module permissions are still not licence-checked

The standing limitation from M5 (L1), M6 (L1) and M7 (L6). `finance.reconcile`,
`data.credit`, `settings.manage`, `crm.contract.register`, `dash.*` and their
kind are RBAC-only.

M10 closed the instances where such a permission was the **only** thing in front
of a paid module on an ungated route — `books_can()`, `ads_can_manage()`, the
books bridge. Where a route gate stands in front, the gap is closed in practice.

The general fix remains registry work: bring these permissions under the
ownership map so the licence question is asked for every permission, not only the
`mod.*` family. It is the single largest remaining structural item from Phase 1.

---

## L4 · Ungated routes remain ungated — they are now individually safe

`/search`, `/ratings`, `/timesheets`, `/inspector-profile`, `/adspro`,
`/books-bridge` and the 21 Connect routes are still absent from
`ops_module_gate()`'s map. Each is now safe because its own gate asks the module
question — but that is a per-route guarantee, not a structural one.

Adding them to the map needs access modules they do not have (M9's L3 for
Marketplace, and the same for the others). A test asserting that every dispatched
route is either mapped or names a module-aware gate would make this structural;
it is the build-time guard M5's L2 and M8's L1 also called for.

---

## L5 · Two gates were hardened without being defects

`tally_can()`, `tally_can_manage()`, `billable_can()`, `billable_can_manage()`
were Category B and **not exploitable**. They were changed for defence in depth.

Recorded so the finding count is not read as larger than it is: **8 real defects,
4 precautionary changes.**

---

## L6 · `is_admin_level()` was not audited exhaustively

`is_admin_level()` and `is_coordinator_level()` are role-level checks that can
carry the same "no module question" defect. M10 corrected them where they
appeared in a gate the probe flagged (`rating_can`, `timesheet_can`,
`inspector_profile_can`, project costing in M7).

A full sweep of all `is_admin_level()` uses was **not** done — it was out of the
milestone's stated scope, which is master bypasses. Worth its own pass.

---

## L7 · MySQL/MariaDB was not executed

Verified, not assumed. No claim of validation. See `M10-TEST-RESULTS.md` §2.

---

## L8 · Not yet verified on the live server

M5 through M10 have not been uploaded to `operations.mghaiapps.com`. Note that
**M9's L1 still applies and must be done first**: Marketplace is now a paid
module, so hosted workspaces need `connect` added to their entitlement before
deployment, or the marketplace switches off for them.
