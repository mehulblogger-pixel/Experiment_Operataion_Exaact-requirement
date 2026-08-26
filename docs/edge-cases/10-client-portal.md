# Module 10 — Client Portal · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-25). Decision: (A) register-backed portal invoices + close the
intra-client site-scope hole; the survey surface / invoice-raised email deferred. `portal_invoices()`
now returns an additive superset (register rows via `portal_invoices_register()` with
`books_settled` outstanding, then legacy mirror rows the register doesn't cover, de-duped);
single-record `portal_call()`/`portal_report()`/complaint job-picker now scope by site like the
lists. Asserted by `tests/test_module10_portal.php`. Client-safe columns only; partner scope in the
WHERE clause. P0.

---

## 0. Headline: the portal is disciplined — but it shows the client the wrong money

The client portal (`lib/portal.php` + the shared `lib/cvp.php` "Client & Vendor Portal" engine) is
the most carefully-written subsystem in the app: its own identity table (`client_users`) and
session key (`cuid`) that **can never** be `current_user()`; dispatch **before** the staff auth
gate; IDOR avoided by putting `client_id`/`partner_id` **in the WHERE clause** (an id belonging to
another client simply "does not exist"); reports visible **only** when `finalized=1`; PDFs served
from the already-authorised row (no raw file-id route); cost/margin/credit/internal columns **never
SELECTed**; invite tokens single-use with a 7-day expiry. There is **no cross-client leak**.

The one place it tells the client something **wrong** is **invoices**. `portal_invoices()`
(`portal.php:325-336`) reads the **legacy `jobs` mirror** — `j.invoice_number / invoice_amount /
payment_received` — not the real `invoices` register. A grep of `portal.php` for `FROM invoices` /
`invoice_lines` / `receipts` returns **zero** matches. Consequences:

- A **consolidated invoice** covering several jobs shows as one row **per job** (its notion of "an
  invoice" is really "a job with a number stamped on it"), or inconsistently.
- A **manual / ad-hoc invoice** raised directly in the register with **no `job_id`** is **invisible**
  to the client — there is no `jobs` row to mirror it.
- **Payments** come from the `jobs.payment_received` boolean, not `receipts`/`receipt_allocations`,
  so a **part-payment** reads as unpaid (or paid), never "half".
- The dashboard **outstanding / overdue** tiles (`portal_dashboard()`, `portal_invoice_overdue()`)
  inherit the same blind spot.

This is the highest trust-cost defect in the portal: a client comparing the portal to their own
books finds the numbers disagree. Module 09 just made the register the enforced source of truth for
numbering; this module makes the **client** see that same register.

**Second gap (least privilege):** intra-client **site scope** (`site_ids` on a portal user, applied
via `portal_site_sql`) is enforced on the **list** views but **omitted on single-record fetches** —
`portal_call()` (`:248-259`), and the complaint job-picker (`:783-786`) filter by `client_id` but
not by site. A site-restricted user of the **same company** can therefore open a record at a site
they weren't scoped to by guessing an id. **Not** a cross-client breach, but inconsistent with the
list views and a least-privilege hole worth closing.

---

## 1. What is deliberately NOT in scope (honouring the program rules)

- **Deleting or rewriting the mirror path.** The `jobs.invoice_*` mirror and every existing call
  site keep working; the register becomes an **additional, preferred** source, not a replacement.
- **A satisfaction-survey portal surface** (`lib/satisfaction.php` has no portal wiring), **quote
  accept/reject in the portal**, **PO upload**, **an "invoice raised" email** — all real additive
  opportunities, but each is a new client-facing feature/write-path of its own. Deferred (noted
  §5-C).
- **Merging `portal.php` and `cvp.php`.** A structural refactor, not a fix.
- **Exposing any new column.** The register read selects **only** client-safe fields (number, date,
  due, gross, outstanding) — never cost, margin, credit terms or internal notes. The portal's
  confidentiality-by-omission discipline is preserved exactly.

---

## 2. Proposed additive layer (recommended = §5-A)

**Make the portal's invoices come from the register, additively — and close the site-scope hole.**

1. **`portal_invoices_register()`** (new, `lib/portal.php`) — reads the real `invoices` scoped by
   `partner_id = portal_partner_id()` (from the session, never the request), status
   `IN ('ISSUED','PART_PAID','PAID')` (never a DRAFT — the client only ever saw issued money), with
   settlement from `books_settled()` (outstanding, so a part-payment reads as half). Selects **only
   client-safe columns**: `invoice_no, invoice_date, due_date, total, outstanding, status`, plus the
   jobs/contract it bills (by code, for context) — never a cost or margin field.

2. **`portal_invoices()` returns the superset** — register rows first, then any **legacy mirror
   rows not represented in the register** (a job with an `invoice_number` that no register row
   covers — a pre-Books invoice), de-duplicated by number/job so nothing is shown twice. So:
   consolidated and manual invoices appear; old mirror-only invoices still appear; nothing that was
   visible before disappears.

3. **Dashboard outstanding / overdue read the register** — `portal_dashboard()` and
   `portal_invoice_overdue()` compute from the same register-backed source, so the tiles match the
   books (and match the internal receivables screen from Module 09).

4. **Close the intra-client site-scope hole (bounded, least-privilege):** apply the **same**
   `portal_site_sql` the **list view already applies** to the matching single-record fetch — so
   `portal_call()` is scoped by site exactly as `portal_calls()` is, and the complaint job-picker
   matches its list. Applied **only where the list already scopes by site** (so behaviour stays
   consistent, never newly-restrictive beyond the established rule). A blank `site_ids` (the common
   case) still means "all sites" — no working portal user loses access.

Reuses: `portal_partner_id()`, `portal_site_sql()`, `books_settled()`, the register from Module 09,
the existing invoice view (fed the same row shape + the new `outstanding` field). **No new
permission** (reuses the `invoices` portal perm and `portal_need`). **No schema change.**

---

## 3. Edge cases

1. **Consolidated invoice (many jobs, one register doc)** → shown **once** as one invoice with its
   real number and outstanding, not once per job. (The core fix.)
2. **Manual invoice, no `job_id`** → now visible (it was invisible); scoped by `partner_id`.
3. **Pre-Books legacy invoice (mirror only, no register row)** → still visible via the superset's
   mirror tail, so nothing a client saw yesterday vanishes.
4. **Same invoice in both register and mirror** (issued via Books, which mirrors to the job) → shown
   **once** — de-duplicated by invoice number, preferring the register row (it has the true
   outstanding). No double-count.
5. **DRAFT invoice** → never shown (client only ever sees issued money); matches the register's own
   `status IN ('ISSUED','PART_PAID','PAID')`.
6. **CANCELLED invoice** → not shown as owed; if it was previously mirrored, the un-mirror already
   cleared the job columns, so it drops out consistently.
7. **Part-paid invoice** → outstanding from `books_settled` shows the real remaining balance, not a
   paid/unpaid boolean.
8. **TDS-settled invoice** → outstanding reflects TDS + cash allocations (the register truth), so a
   fully-TDS-settled invoice reads as paid, not perpetually open.
9. **Cross-client safety** → `partner_id` stays in the WHERE clause (never trusted from the
   request); an invoice for another client "does not exist". The register read must never widen this.
10. **Confidentiality** → the SELECT lists only safe columns; no `SELECT *` on `invoices` (which
    carries `sbu`, internal notes, tally_ref). Explicit column list, reviewed.
11. **Site scope, blank** → a user with no `site_ids` sees everything for their company, unchanged.
12. **Site scope, restricted** → `portal_call()` now refuses an out-of-scope same-company call id
    exactly as the list would never have shown it; a legitimately in-scope id is unaffected.
13. **Empty state** → a client with no invoices sees the same "nothing yet" the view already renders.
14. **Performance** → one register query + `books_settled` per shown invoice (bounded by the
    client's own invoices), plus the mirror tail; not an app-wide scan.

---

## 4. Guardrails (must stay green)

- The portal identity model (`client_users`/`cuid` never `current_user()`), the pre-auth dispatch,
  the finalized-only report gate, the secure PDF serving, the invite-token lifecycle, the CVP
  visibility gate, and every existing partner-scoped read — **all unchanged**.
- The `jobs.invoice_*` mirror and every non-portal reader of it (profitability, MIS, the money desk)
  — **untouched**; this module only changes what the **portal** reads.
- `test_portal_convert`, `test_portal_contact_link`, `test_simplify_portals`, `test_cvp`,
  `test_cvp_issues`, `test_cvp_notify`, `test_cvp_governance`, `test_cvp_vendor` — untouched.
- No existing route, table, column, status or permission removed or narrowed; no confidential column
  newly exposed.

---

## 5. OPEN DECISION — how far to take the portal fix

- **(A) Register-backed portal invoices + close the site-scope hole (recommended, P0):** the client
  sees the real invoices register (consolidated + manual invoices visible, part-payments honest,
  dashboard matches the books), via an additive superset that keeps every legacy row visible; plus
  the bounded least-privilege fix so single-record fetches scope by site exactly as the list views
  do. No new permission; no schema change; only client-safe columns.
- **(B) Register-backed portal invoices only** — the money-correctness fix alone, leaving the
  site-scope tightening for a dedicated least-privilege pass.
- **(C) Also add a client-facing feature** — a satisfaction-survey response surface (wiring the
  existing `satisfaction.php` to the portal) and/or an "invoice raised" email. Larger; a new
  write/notify path; deferred by default.

Default if you don't specify: **(A)**.

---

## 6. Tests

1. `portal_invoices_register`: returns a client's issued register invoices with a real `outstanding`;
   a DRAFT is excluded; a manual (no-`job_id`) invoice **is** included; another client's invoice is
   **not** returned (partner scope).
2. Superset: a legacy mirror-only invoice still appears; an invoice present in both register and
   mirror appears **once** (de-duped); a part-paid invoice shows partial outstanding, not a boolean.
3. Dashboard outstanding/overdue computed from the register match `books_settled`.
4. Confidentiality: the portal invoice rows carry no cost/margin/internal column.
5. Site scope: with a restricted `site_ids`, `portal_call()` refuses an out-of-scope same-company
   call id (as the list already hides it); a blank `site_ids` still sees everything; no cross-client
   id is ever returned.
6. Preservation: the finalized-only report gate, the mirror path and every existing partial-scope
   read are unchanged; no new permission.
