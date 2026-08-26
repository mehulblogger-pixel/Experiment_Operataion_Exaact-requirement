# Module 11 — Vendor / Supplier-Inspector Portal · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-26). Decision: (A) vendor qualification visibility + expiry alert;
vendor-perms admin UI (§5-B) and a vendor billing/payable view (§5-C) deferred. Read-only
`cvp_vendor_qualification()` + status history + a `QUALIFICATION_EXPIRING` feed alert, scoped by
session vendor_id, exposing status + dates only (never score/rating/band/risk/notes). Additive
`qualification` perm key (blank-perms = everything). Asserted by `tests/test_module11_vendor.php`. P1.

---

## 0. Headline: confidentiality is sound — the vendor just can't see their own standing

The vendor portal (the vendor half of `lib/cvp.php`) is as isolated and disciplined as the client
portal: its own table (`vendor_users`) and session key (`vuid`) that can never carry a staff or
client identity; dispatch before the auth gate; a **confidentiality-first visibility gate**
(`cvp_visibility_sql` / `cvp_can_see`, safe default = *not shown*); reports reach a vendor only when
staff explicitly set `report_docs.vendor_visible=1`; **both** single-record fetches already put
`vendor_id` in the WHERE clause (so the client-side single-fetch scope gap fixed in Module 10 does
**not** exist here); no cost/margin column is ever selected; tokens are single-use with a 7-day
expiry. Cross-vendor isolation is asserted by `test_cvp_vendor.php`.

What a vendor sees today is only: **shared reports**, **nonconformities raised to them** (with a
respond loop), an alerts feed and an assistant. What they **cannot** see — though staff maintain it —
is **their own qualification standing**: `vendor_profiles` holds `approval_status`
(PROSPECT → UNDER_ASSESSMENT → APPROVED / CONDITIONAL / **EXPIRED** / SUSPENDED / BLACKLISTED),
`valid_until` (**approval expiry**), `reassess_on`, and a full `vendor_status_events` timeline —
maintained by `idems_vendor_apply_assessment()` — but **no vendor consumer exists**. A
supplier-inspector's loudest real question — *"Am I still an approved vendor, and when must I
re-qualify?"* — has no answer in the portal, and there is **no expiry alert** either.

One confidentiality note to document (not a bug): `cvp_vendor_report()` does `SELECT d.*`, and
`report_docs` carries `client_id`, so a report staff **deliberately share** names the client on it.
That is intended (a vendor audit naturally names the buyer); it is not a cost/margin leak (those
live on `jobs`/`billing`, not `report_docs`).

---

## 1. What is deliberately NOT in scope (program rules)

- **No numeric grading exposed.** The vendor sees their *status and expiry date*, never
  `last_score`, `vendor_rating`, `last_band`, `risk_class`, `notes`, or the internal `reason`/`actor`
  on a status event — those are our internal assessment, not the vendor's to see.
- **No vendor billing / payable surface.** It doesn't exist today; billing figures are commercial,
  and building a "what we owe you" view is a separate, larger, opt-in decision (§5-C). Deferred.
- **No new hard control**, no deletion, no permission granted outside the existing model. The new
  `qualification` perm key is *additive* and blank-perms still = everything, so no existing vendor
  is affected.
- **No document/credential upload, job/PO acknowledgement, or org-admin self-service** — each is a
  new write-path/feature of its own. Deferred.

---

## 2. Proposed additive layer (recommended = §5-A)

**Show the vendor their own qualification standing, and warn them before it lapses.**

1. **`cvp_vendor_qualification()`** (new, `lib/cvp.php`) — reads the session vendor's own
   `vendor_profiles` row via `idems_vendor_profile(cvp_vendor_id())` and returns **only vendor-safe
   fields**: `approval_status` (+ its editable-lookup label), `valid_until`, `reassess_on`,
   `approved_on`, `vendor_type`, `product_category`, plus a computed `days_to_expiry` and an
   `expiring`/`expired` flag. **Omits** score, rating, band, risk class, notes, updated_by. Returns
   null cleanly when no profile row exists (a brand-new vendor is simply "not yet assessed").

2. **`cvp_vendor_qualification_events()`** — the status timeline from `vendor_status_events`, each
   entry reduced to **new-status label + date + source** (assessment / audit / expiry / manual).
   The internal `score`, `reason` and `actor` are **not** included.

3. **A "Your qualification" surface** — a new `vendor/qualification` route + `views/vendor/
   qualification.php` (status pill, approved-on, valid-until with a clear "expires in N days" /
   "expired" line, next reassessment, type/category, and the safe history), plus a compact status
   card on the vendor **dashboard**. Nav gains a "Qualification" entry. Gated by a new
   `VENDOR_PERMS` key `'qualification'` — and because blank perms = everything, every existing
   vendor sees it automatically.

4. **Qualification-expiry alert** — a new `CVP_EVENTS` key `QUALIFICATION_EXPIRING`, emitted by the
   existing state-derived `cvp_notify_sync('VENDOR', …)` when `valid_until` is within the reminder
   window (`idems_vendor_reminder_days()`), or already past. Read-only, idempotent (the existing
   `cvp_notify_once` natural key stops duplicates), no engine edit — the vendor finally learns their
   approval is lapsing without waiting for us to email.

Reuses: `idems_vendor_profile`, `idems_vendor_status_events`, `idems_vendor_reminder_days`,
`cvp_vendor_id`, the visibility discipline, `cvp_notify_sync`/`cvp_notify_once`, the vendor view
harness. **No new permission outside the additive `qualification` key; no schema change; no hard
control.**

---

## 3. Edge cases

1. **No `vendor_profiles` row** → "not yet assessed" state; no crash, no fabricated status.
2. **`valid_until` blank** (approved with no expiry recorded) → shown as "no expiry recorded", not
   "expired"; not alerted.
3. **Already EXPIRED status** → shown plainly as expired with the lapse date; an alert is raised.
4. **Expiring within the reminder window** → "expires in N days" + a `QUALIFICATION_EXPIRING` alert;
   idempotent across reloads.
5. **SUSPENDED / BLACKLISTED** → shown as the status (the vendor is entitled to know their standing)
   but with no internal reason/score; this is their status, not our rationale.
6. **Cross-vendor safety** → the profile read filters by session `cvp_vendor_id()`; another vendor's
   profile is unreachable, exactly like every other vendor read.
7. **Confidentiality** → no numeric score/rating/band/risk/notes/actor is ever selected into the
   vendor-facing shape; only status + dates + type/category.
8. **Blank-perms existing vendor** → sees the new panel automatically (blank = everything); a vendor
   explicitly scoped to a subset without `qualification` does not — consistent with the existing
   `vcan()` model.
9. **Status history with internal reasons** → the events read maps to safe fields only; an internal
   note in `reason` never reaches the vendor.
10. **Performance** → one profile row + one bounded events read per view; the alert is folded into
    the existing sync pass, no extra query storm.
11. **Portal disabled / access expired** → the existing `cvp_vendor_require` / access-expiry gate
    still governs; the panel is behind it like every other vendor screen.

---

## 4. Guardrails (must stay green)

- The vendor identity model, the confidentiality visibility gate, the `vendor_visible` share flag,
  the single-fetch scope, the NCR respond loop, the invite-token lifecycle, and every existing
  vendor read — **all unchanged**. The module only **reads** `vendor_profiles`/`vendor_status_events`
  and **appends** feed entries.
- `test_cvp_vendor`, `test_cvp_notify`, `test_cvp_issues`, `test_cvp_governance`, `test_cvp`,
  `test_simplify_portals`, `test_module16_vendor360` — untouched.
- No existing route/table/column/permission removed or narrowed; no internal grading exposed.

---

## 5. DECISION (recommended option built in this autonomous run)

- **(A) Vendor qualification visibility + expiry alert (recommended, P1) — BUILDING:** the read-only
  "Your qualification" panel (status, expiry, reassessment, safe history) + the
  `QUALIFICATION_EXPIRING` feed alert, exposing only the vendor's own standing and never the numeric
  grade. Additive; no schema change; no hard control.
- **(B) Also a vendor perms admin / presets UI** — a staff screen to scope vendor-user permissions
  (today `cvp_vendor_invite` never sets `perms`, so all vendors get everything). Useful, but a
  staff-side admin feature of its own. Deferred.
- **(C) Also a vendor billing / payable view** — "what we owe you and its status". Commercial data;
  only ever the payable status, never our cost/margin; a larger, opt-in decision. Deferred.

---

## 6. Tests

1. `cvp_vendor_qualification()`: returns the session vendor's status + valid_until + reassess_on;
   **omits** score/rating/band/risk/notes; null-safe when no profile exists; another vendor's
   profile is never returned.
2. Expiry: a profile valid_until within the reminder window is flagged `expiring`; a past one is
   `expired`; a blank one is neither.
3. `cvp_vendor_qualification_events()`: returns status transitions with dates but no score/reason/
   actor.
4. Alert: `cvp_notify_sync('VENDOR', …)` raises a `QUALIFICATION_EXPIRING` entry for an expiring
   profile and is idempotent on a second run.
5. Preservation: the `qualification` perm is additive (blank perms still = everything); the
   confidentiality gate, the share flag and the existing vendor reads are unchanged.
