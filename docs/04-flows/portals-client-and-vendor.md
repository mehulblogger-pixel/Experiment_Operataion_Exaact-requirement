# Flow — Client & Vendor Portals (summary)

These are **separate login worlds** with their own permission systems, **not** in
`ORG_ROLES`. This pass (Option A) summarises them; a full role-by-role treatment is
listed as follow-up work in `99-gaps-and-risks.md`.

## Client portal
- **Engine:** `lib/portal.php`; screens `views/portal/*`; permission check `pcan()`;
  per-user rights managed at `views/ops/portal_users.php` / `portal_user_perms.php`.
- **Who:** a client's own staff, invited by the branch.
- **Typical journey:** log in → dashboard → see **their own** calls, reports and
  invoices; raise a complaint; where enabled, **decide report acceptance**
  (`views/portal/report_decide.php`).
- **Hire manpower (Connect marketplace):** with the `market.post` right, a client
  posts a technical-manpower requirement straight to the open board
  (`views/portal/hire.php` → `cx_requirement_create(..., $post=true)`). The posting
  form carries the **engagement terms** the ops desk uses — **deputation basis**
  (man-days / man-months / long-term deputation / continuous / regular frequency),
  **rate model** (all-inclusive vs fee-only-expenses-extra) and **voucher cadence**
  (per-day vs per-deployment). These are stored on the requirement
  (`cx_requirement_save_terms`) and **inherited by the engagement** at booking, so
  the voucher model (K21) is fixed from the moment the client posts. The client then
  shortlists and awards its own applicants (`views/portal/hire_req.php`). This uses
  the existing `market.post` permission only — no new right is introduced.
- **Boundary:** a client sees only their own records — never other clients', never
  internal money/cost figures. Marketplace postings are scoped to the poster party
  (`cx_requirements_for_party`).

## Vendor portal
- **Engine:** `lib/cvp.php`; screens `views/vendor/*`; logins in `vendor_users`.
- **Who:** a vendor/manufacturer whose goods are inspected.
- **Typical journey:** log in → dashboard → see **their own** inspection activity and
  raised issues; respond to issues.
- **Boundary:** scoped strictly to that vendor's own activity.

> Because these use `pcan()` / `vendor_users` rather than the staff `can()` model, none
> of the staff permission matrix (doc 02) applies to them. Keep them documented
> separately so the two access surfaces are never conflated.
