# Module 41 — Document Control (ISO 17020 §8.3) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: a real register, invisible on the two shared surfaces

A genuine controlled-document register exists (`lib/controldocs.php`, table `controlled_docs`) —
doc code, type, revision, status (DRAFT/CURRENT/SUPERSEDED/WITHDRAWN), owner, approver, effective +
**review-due** dates, file, and a never-overwrite supersession chain. It is correctly built and
tested. But it is an **island**: the review-due signal (`review_due < today`) is computed in
`cdoc_counts()` yet **never reaches the cron reminder dispatcher or the compliance readiness board** —
the two surfaces every other accreditation register plugs into. A procedure can pass its review date
forever; only someone opening `/cdocs` sees the amber badge. Separately, **supersession was the one
lifecycle event not written to the sealed trail** (create + status-current were).

---

## 1. Built (additive, read-mostly, no hard control)

1. `cdoc_readiness($today)` — current count, `review_overdue`, and `never_approved` (a current
   document with no recorded approval), built on the same query `cdoc_counts()` already uses.
2. **Compliance-board row** (`compliance.php`, §8.3) — "The document in use is the current, approved
   revision", bad when overdue, warn when unapproved, deep-linking to the register.
3. `cdoc_run_reminders($today)` — chases overdue/unapproved documents by e-mail to the quality owner,
   mirroring `reviews_run_reminders()`. Wired into `cron.php`, **weekly-guarded** (a year-scale
   review cycle needs no daily nudge). Notifies; changes no document.
4. **Supersession logged to the sealed trail** — `cdoc_supersede()` now writes a `SUPERSEDED`
   `idems_log` entry (the previously-missing lifecycle event), completing the evidence chain.
5. The **first tests** over readiness/reminder and the supersede-audit write.

---

## 2. Edge cases handled

1. A current doc with a blank `review_due` is never counted overdue (only `review_due<>''` past
   today).
2. A DRAFT/SUPERSEDED/WITHDRAWN doc is never counted (only CURRENT).
3. The reminder fires only when something is actually overdue/unapproved AND a recipient exists;
   otherwise 0. A failed send never breaks the nightly run.
4. The compliance row is gated by `cdoc_pack_on()` (the accreditation pack) exactly as the register
   is — it doesn't appear on a non-accredited install.
5. Supersession still creates the new draft + marks the old row superseded (unchanged); the audit
   write is additive and best-effort (`function_exists('idems_log')`).

## 3. Guardrails (green)

The register, versioning/supersession chain, the obsolete-control unambiguity, the routes and views —
all unchanged. No new permission (reuses the existing `cdoc_can_*` gates); no schema change; no hard
control (approval is still not *enforced* before CURRENT — noted, left for a deliberate decision);
nothing deleted. `test_cdocs` untouched.

## 4. Deferred (noted)

A per-person **read-acknowledgement register** (staff confirm they read the current SOP) — the
natural second step, reusing the `confidentiality_undertakings` shape, but it needs a new table and
write path. Enforcing approval before CURRENT is a hard control (needs a go). A distinct
`mod.doccontrol.*` permission (it currently piggybacks `mod.datacontrol.view`).

## 5. Tests

`tests/test_module41_doccontrol.php` (13 assertions): readiness counts current/overdue/unapproved;
the reminder fires when overdue with a recipient; supersession writes a SUPERSEDED entry to the sealed
trail and marks the old revision; the compliance board carries a §8.3 row; the cron reminder is
wired and weekly-guarded.
