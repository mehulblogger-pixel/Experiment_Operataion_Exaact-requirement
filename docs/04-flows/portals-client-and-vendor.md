# Flow — Client & Vendor Portals (summary)

These are **separate login worlds** with their own permission systems, **not** in
`ORG_ROLES`. This pass (Option A) summarises them; a full role-by-role treatment is
listed as follow-up work in `99-gaps-and-risks.md`.

## Connect — one public front door (`/connect`)

The single URL to share for the marketplace. `connect_front_route()` (dispatched in
`index.php` before `require_login`, view `views/ops/connect_front.php`) renders one
page with three "create account" paths — **professional** (`/pro/register`),
**hiring** (`/join?type=COMPANY`), **agency** (`/join?type=MANPOWER_AGENCY`) — and two
labelled sign-in buttons: **professional** (`/pro/login`) and **company/agency**
(`/portal/login`). The individual login/register pages cross-link back to it and to
each other, so nobody lands in the wrong world. It's a thin unifying door over the
existing engines — no auth or data model changed.

## Client portal
- **Engine:** `lib/portal.php`; screens `views/portal/*`; permission check `pcan()`;
  per-user rights managed at `views/ops/portal_users.php` / `portal_user_perms.php`.
- **Who:** a client's own staff. Two ways in — (1) **self-service**: a company or
  agency registers itself at the public **`/join`** page (`connect_org_register`),
  which **auto-approves** and immediately provisions a business-partner party, an
  ACTIVE `cx_organisations` record and a working portal login (no admin step —
  verify later); or (2) **invited** by the branch (`portal_invite`, the invitee
  sets their own password from the link). Individual freelancers use `/pro/register`
  instead. All three are marketplace-facing logins scoped to their own data.
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
- **Review vouchers (Connect marketplace):** the platform is a **matchmaker**, not a
  paymaster — the professional claims their fee + **actual** expenses (with receipts,
  after the inspection; no advances), and the **client who posted the job** reviews
  the claim. On the awarded job (`portal/hire-req`) and the voucher page
  (`views/portal/voucher.php`) the client sees the fee, the day lines, the expense
  heads and the **receipts** (served ownership-scoped via `portal/voucher-file`), and
  either **returns it for clarification** with a note (→ the voucher's `REJECTED`
  state, note recorded; the professional reopens, revises and resubmits) or
  **approves** it (→ `APPROVED`). Gated by a new client-portal permission
  `market.vouchers`; ownership is by the voucher's `poster_party_id`, so a client
  only ever sees vouchers on its **own** posted jobs. No new voucher status — the
  client is simply the reviewer for a client-posted engagement.
- **Agency bench (Connect marketplace, self-service):** when the signed-in portal
  user's party is an **ACTIVE manpower/recruitment agency org** (`portal_agency_org`),
  a **“My bench”** tab appears (`views/portal/bench.php`, route `portal/bench`). The
  agency manages its **own private roster** (`cx_bench`, org-scoped), puts people
  forward to open requirements (`cx_bench_alloc`), and confirms/releases those
  allocations — reusing the same `connect_bench_*` engine the coordinator desk uses,
  scoped to the agency's own org id. A plain client never sees the tab (the route
  404s); one agency can never see another's roster. **No new permission** — the tab
  is gated by the data (being an agency), not a right. The **bench is private**: these
  people are never written to the shared self-listed pool (`cx_professionals`).
- **Boundary:** a client sees only their own records — never other clients', never
  internal money/cost figures. Marketplace postings are scoped to the poster party
  (`cx_requirements_for_party`); an agency's bench and allocations are scoped to its
  org id.

## Vendor portal
- **Engine:** `lib/cvp.php`; screens `views/vendor/*`; logins in `vendor_users`.
- **Who:** a vendor/manufacturer whose goods are inspected.
- **Typical journey:** log in → dashboard → see **their own** inspection activity and
  raised issues; respond to issues.
- **Boundary:** scoped strictly to that vendor's own activity.

> Because these use `pcan()` / `vendor_users` rather than the staff `can()` model, none
> of the staff permission matrix (doc 02) applies to them. Keep them documented
> separately so the two access surfaces are never conflated.
