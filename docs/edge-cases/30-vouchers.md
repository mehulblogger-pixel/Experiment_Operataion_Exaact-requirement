# Module 30 — Vouchers / Expenses (fast field capture) · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Decision: (A) quick-add expense + receipt photo + job bridge; GPS auto-capture deferred. The maker-checker + reopen guards (R5) are preserved verbatim (asserted by tests in `tests/test_module30_vouchers.php`). P1.

---

## 0. Headline: a monthly grid — add a fast "one expense + photo" path

A voucher is a **monthly sheet** (one `vouchers` row per inspector per month) with day-segment
`voucher_entries` rows. Capture today is a **12-column spreadsheet** (`voucher_detail.php`) —
phone-reflowed but still a grid: pull days from jobs, then type hours/mode/km/amounts per day,
save all, then submit. **There is no per-expense receipt photo** — only one whole-voucher
"supporting file". The strong **R5 controls** (maker≠checker on approve, PAID-reopen guard that
clears `submitted_by`, DRAFT-only edit lock, month-frozen lock) are in the `voucher-status`
route and **must be preserved exactly**.

**The spec wants** field submission to be *"Add expense → amount → type → receipt photo → job →
submit"*, auto-capturing date / claimant / currency / job / category. None of that fast path
exists, and the receipt-photo-per-expense doesn't either — even though the claimant (session),
date (today), currency (settings) and job are all already known.

---

## 1. Proposed additive layer (recommended = §4-A)

1. **Per-line receipt storage** — add `receipt_data` / `receipt_mime` / `receipt_name` to
   `voucher_entries` via `ensure_column` (additive, backward-compatible; old rows just have none).
2. **A "Quick add expense" form** — amount + expense **head** (type) + optional **job** + optional
   note + **receipt photo** → one handler `voucher-quick-add` that:
   - **auto-fills** claimant (session `my_inspector_id`), **date = today** (or a posted date),
     **month = today's**, currency (implied from settings), and creates/opens *this month's*
     voucher (reusing the existing find-or-create);
   - writes one `voucher_entries` row (`amounts` JSON `{head: amount}`, `row_total`, the receipt),
     and rolls up `vouchers.total` — exactly as `voucher-save` does;
   - is **gated by `can_edit_voucher($v)`** so it only works on a DRAFT, owned, unfrozen voucher.
3. **Receipt access** — a 📎 indicator on any line with a receipt, served by a gated
   `voucher-line-receipt` route (`can_view_voucher`), and shown in the mobile day-card too.

The monthly grid, the pull-from-jobs flow, and every R5 guard stay exactly as they are. No new
permission (reuses the same role gates).

**Deferred (noted):** auto-capturing **GPS location + the contemporaneous check-in photo** from
`site_visits` onto the expense line — the richest source, but a bigger wiring (join by
inspector+date, new lat/lon columns). Offered as §4-B.

## 2. Edge cases

1. **This month's voucher already SUBMITTED/APPROVED/PAID** → quick-add is refused with a clear
   message ("this month's voucher is already submitted — reopen it to add more"), never a silent
   write past the lock (`can_edit_voucher` enforced).
2. **Month frozen** (cost-run closed) → refused via `voucher_month_frozen`, same as the grid.
3. **Coordinator adding for an inspector** → allowed (as the grid already allows), claimant is the
   chosen inspector, not the coordinator; SOD on approve is unaffected.
4. **No amount / zero / non-numeric** → refused ("enter an amount"), no empty line created.
5. **No expense head chosen** → default to the "Others (specify)" catch-all head that every
   inspector always has (reuse `voucher_heads_for`), so the line is always categorised.
6. **Receipt too large / wrong type** → refused with a size/type message (mirror the existing
   supporting-file limits ~6 MB, PDF/JPG/PNG); the line is not created without a valid file only
   if a file was attached — a receipt is **optional** (a cash expense may have none).
7. **Receipt optional** → a line with no receipt is fine; the 📎 simply doesn't show.
8. **Job optional** → an ad-hoc expense with no job still records (date/claimant/amount/head); a
   posted job fills `job_id`/`sbu`/site from the job like the grid does.
9. **Duplicate rapid submits** → each quick-add creates its own line (expenses aren't unique per
   day, unlike the manual day-add); double-tap makes two lines, which the inspector can delete —
   acceptable and matches how the grid treats multiple same-day segments.
10. **Total roll-up** → `vouchers.total` recomputed after the insert, consistent with
    `voucher-save`; never diverges.
11. **Receipt serving** → only `can_view_voucher` may fetch it (the owner or a coordinator+),
    with the right content-type; a missing/blank receipt id → 404, not a leak.
12. **Mobile** → the quick-add is a compact single-column form; the file input opens the camera on
    a phone.

## 3. Guardrails (must stay green)

- **R5**: maker≠checker on approve, the PAID-reopen guard (and its `submitted_by` clear), the
  DRAFT-only edit lock, and the month-frozen lock — all unchanged (quick-add routes through
  `can_edit_voucher`; approval/reopen logic is untouched).
- `test_voucher_sod_and_reopen`, `test_voucher_mobile_layout`, `test_voucher_heads_and_register`,
  `test_voucher_booked_dates`, `test_voucher_multi_job_pull` — untouched.
- The per-job `expenses` table (job-close, client-billable) — untouched; this is the inspector's
  own monthly voucher path only.

---

## 4. OPEN DECISION — how far to auto-capture?

- **(A) Quick-add expense + receipt photo, auto-filling claimant/date/currency/(optional) job
  (recommended, P1):** the fast add the spec asks for, with per-line receipts, gated by the
  existing edit lock. Additive schema (3 receipt columns); no guard touched; no new permission.
- **(B) Also auto-capture GPS location + the check-in photo** from `site_visits` onto the expense
  line — the richest auto-fill, but it needs a join by inspector+date and new lat/lon columns, and
  raises "which visit does this expense belong to" ambiguity. Better as a focused follow-up once
  the quick-add exists.

Default if you don't specify: **(A)**.

## 5. Tests

1. `voucher-quick-add` on a fresh month creates/opens this inspector's DRAFT voucher and writes a
   categorised line (amount in `amounts` JSON, `row_total`, date=today), rolling up the total.
2. It is refused when the month's voucher is SUBMITTED/APPROVED/PAID or frozen (edit lock).
3. A receipt is stored on the line and served only to a permitted viewer; a line without a
   receipt is fine.
4. The R5 guards (maker≠checker, PAID-reopen, edit lock) are unchanged; no new permission.
