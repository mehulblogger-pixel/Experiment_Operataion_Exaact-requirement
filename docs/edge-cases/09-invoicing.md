# Module 09 — Invoicing · Edge-case analysis (pre-build)

> **Phase-2 update:** invoice-readiness (§33 — report-issued / release-accepted / contract-value checks,
> advisory unless `invoice_gate_strict`) is catalogued in [`51-phase-2-controls.md`](51-phase-2-controls.md#33--invoice-readiness-module-09--libinvreadyphp).

**Status:** ✅ BUILT (2026-08-25). Decision: (A) invoice-number integrity + overdue reminder; SoD
maker-checker and the invoice↔report gate deferred. A DB-enforced UNIQUE on the money-document
numbers (unissued drafts held as NULL / partial index on SQLite; defensive — skips + surfaces
legacy duplicates), a hardened `books_issue` that re-allocates on a collision, and an opt-in
`ar_overdue_reminders()` cron. Asserted by `tests/test_module09_invoicing.php`. P0.

---

## 0. Headline: the register is mature — the one unrecoverable gap is a duplicate invoice number

Invoicing is **two systems, deliberately bridged**:

1. **The Books register** (`lib/books.php` + `lib/booksui.php`) — a real relational model:
   `invoices / invoice_lines / receipts / receipt_allocations / credit_notes`, with GST
   (CGST/SGST vs IGST decided from state, frozen at create), TDS on the receipt, part-payment
   via decoupled receipt→allocation, round-to-rupee with a real `round_off` line, a running-
   balance ledger, receivables ageing buckets, credit notes, cancellation, and a Tally export.
2. **The legacy per-job mirror** — `jobs.invoice_raised / invoice_number / invoice_amount /
   payment_received / payment_amount`. Issuing a Books invoice **mirrors** its figures back onto
   these columns (`books_mirror_to_jobs`, `books.php:624-640`) so older screens (the client
   portal, profitability) keep working. The register is the truth; the mirror is a shadow.

The register does the hard things well. The gaps that remain are a **safety** gap, a
**completeness** gap, and two **larger** control gaps:

- **SAFETY (the headline): no DB-enforced UNIQUE on `invoices.invoice_no`.** The number is
  allocated **at issue** (not at draft — so abandoned drafts leave no gap, satisfying the GST
  gapless rule) via a read-max-then-write with a retry loop (`numbering_next` `numbering.php:127-135`;
  `books_next_number` `books.php:208-217`). But `idx_inv_no` is a **plain index**, not UNIQUE
  (`books.php:171`), and the code comment at `books.php:183-185` acknowledges the race. Two
  accountants pressing **Issue** at the same moment on shared hosting have a real TOCTOU window to
  mint the **same invoice number twice** — legally unrecoverable once it is in a filed return.
- **COMPLETENESS: no overdue-receivables reminder.** Ageing (`receivables.php`, buckets
  Not-due/0-30/31-60/61-90/90+) is **view-only**; `cron.php` mails CAPA-overdue and drains the
  Books bridge, but **nothing chases an overdue invoice**.
- **CONTROL (larger, deferred): no maker-checker / SoD.** One `finance.reconcile` user can create,
  issue, receipt and allocate the whole cycle — vouchers got an SoD guard, invoicing has none.
- **CONTROL (larger, deferred): invoice-before-report-issued is allowed.** Only job *closure*
  gates a line (and a master / `finance.reconcile` user can override even that); report *issue* is
  never checked, and there is no invoice↔report link.

---

## 1. What is deliberately NOT in scope (honouring the program rules)

- **Unifying the profitability / portal readers onto the register.** Today profitability
  (`ops_profitability`, `boss_profit`) and the client portal read the **mirror**
  (`jobs.invoice_amount / payment_received`), so a consolidated multi-job invoice shows only as
  per-job splits and a manual (no-`job_id`) line is invisible to both. Real, but a
  behaviour-changing reader migration — its own module (touches Module 20/32 profitability and
  Module 10 portal). Deferred.
- **Maker-checker / SoD on issue + receipt.** Valuable, but it changes the issue/receipt flow and
  must not lock out single-finance-person shops — a deliberate step of its own (§5-B), mirroring
  how vouchers got SoD.
- **Invoice-on-report-issued gate + invoice↔report link.** A real control, but adds a gate to the
  billing path and a new link; deliberate, larger (§5-C).
- **Proforma / RCM / multi-currency.** `kind` (always `TAX`), `is_rcm` and "no currency column"
  are latent/dead — completing them is a feature, not a fix. Deferred.

---

## 2. Proposed additive layer (recommended = §5-A)

**Invoice-number integrity + an overdue-receivables reminder — the two cleanly-additive,
non-behaviour-changing wins.**

1. **DB-enforced uniqueness on the money-document numbers** — add a **UNIQUE** index on
   `invoices.invoice_no` (over issued rows — `invoice_no <> ''`, so many DRAFTs with an empty
   number don't collide), and partner/series-scoped UNIQUE on `receipts.receipt_no` and
   `credit_notes.cn_no`. Built **defensively**: created only when the existing data has no
   duplicate in that series; if legacy duplicates exist the index is skipped and the duplicates are
   **surfaced** (the app already does exactly this for quote/contract numbers on the register — a
   "numbers used more than once" banner), never a hard crash on migrate.

2. **Harden `books_issue` to survive a collision** — wrap the number-allocation + write so that a
   UNIQUE violation (the concurrent-issue race) is **caught and the number re-allocated** (the
   retry the code already intends, now backed by a hard constraint instead of a hopeful read-max).
   On success nothing changes; under a race the loser re-mints instead of duplicating. Read
   behaviour and the number format are untouched.

3. **`ar_overdue_reminders()` cron** — same shape as `equipment_run_cal_reminders()`: find ISSUED
   / PART_PAID invoices past `due_date` (using the register, with the ageing helper's own
   due-date logic), and email the office/finance a concise overdue list (count + total + oldest
   bucket). Opt-in, idempotent, read-only over the register; it chases, it never changes an
   invoice. A per-invoice "reminded_at" stamp (additive column) keeps it from re-nagging daily.

Reuses: `numbering_next` / `books_next_number`, the ageing engine (`receivables.php`, `AR_BUCKETS`,
`books_outstanding`), the index helper, the cron harness, the existing duplicate-surfacing UI
pattern. **No new permission** (reuses `books_can_issue` / `ar_can`). Schema: one additive
`reminded_at` column + indexes (both via `ensure_column` / guarded index add) — no table rewrite.

---

## 3. Edge cases

1. **Existing duplicate invoice numbers in legacy data** → the UNIQUE index **cannot** build; the
   migration must detect this and **skip the index + surface the duplicates**, never crash the app
   on boot. (Same pattern as the quote/contract duplicate banner.)
2. **Many DRAFT invoices with empty `invoice_no`** → must not collide with each other. The unique
   constraint is over `invoice_no <> ''` (a partial/filtered unique, or an equivalent guard), so
   unissued drafts are exempt.
3. **Concurrent Issue (the race this exists for)** → both read max = N, both try N+1; the DB lets
   one commit and rejects the other; the rejected issue **re-allocates** N+2 and succeeds. No
   duplicate, no lost issue, no user-visible error beyond a transparent retry.
4. **Cancelled invoice keeps its number** → cancellation does not free the number (`books_cancel`
   keeps the row); the unique index must tolerate a CANCELLED row holding its number (it does — the
   number stays unique, just belongs to a cancelled doc).
5. **Credit note / receipt numbering** → same read-max-then-write shape; same hardening and unique
   guard, scoped to their own series.
6. **FY rollover** → numbers reset per FY (the FY is part of the stem); uniqueness is within the
   series+FY, so `INV/DEL/26-27/0001` and `…/27-28/0001` don't collide. The index is on the full
   `invoice_no` string, which already embeds the FY.
7. **Overdue reminder — a PAID invoice** → never chased (settled). **A within-terms invoice** →
   not yet due, not chased. **A CANCELLED invoice** → excluded.
8. **Overdue reminder idempotency** → `reminded_at` (or a since-last-run window) stops a daily
   re-nag on the same invoice; a genuinely newly-overdue one still triggers.
9. **Overdue reminder with no due_date** → fall back to `invoice_date + credit_days` (the ageing
   engine's own logic), or skip rather than guess; never treat a missing date as "overdue today".
10. **TDS-settled invoice** → its status is PAID/PART_PAID from allocations incl. TDS; the reminder
    reads status, so a TDS-settled invoice with low cash is not falsely chased.
11. **Performance** → the reminder is one scan of open issued invoices per run (not per-client
    N+1); the unique index is a one-time build.
12. **Read-only over the register** → neither piece touches the legacy mirror or the double-billing
    guard; the ledger, allocation and cancel logic are untouched.
13. **Mobile** → finance is desk-first; the reminder is an email, the register unchanged.

---

## 4. Guardrails (must stay green)

- Numbering-at-issue (gapless), the GST/IGST computation + `round_off`, the receipt→allocation
  decoupling, overpayment refusal, the double-billing NOT-EXISTS guard, the closed-job gate on a
  line, cancel/credit-note logic, the job mirror + un-mirror, the Tally export, and the ledger —
  **all unchanged**. The new pieces only *read* them, plus one additive column and indexes.
- `test_bill_by_project`, `test_invoice_idempotent_and_voucher_note`, `test_office_carry_to_invoice`,
  `test_invoice_references_panel`, `test_invoice_print`, `test_simplify_billing`,
  `test_tally_creditnote`, `test_books_bridge` / `test_books_receiver*` — untouched.
- No existing route, table, column, status or permission removed or narrowed.

---

## 5. OPEN DECISION — how far to take the invoicing hardening

- **(A) Invoice-number integrity + overdue reminder (recommended, P0):** DB-enforced UNIQUE on the
  money-document numbers (built defensively, surfacing legacy duplicates rather than crashing), a
  hardened `books_issue` that re-allocates on a collision, and an opt-in `ar_overdue_reminders()`
  cron. Closes the one legally-unrecoverable gap and adds the missing money-out chase, with no
  behaviour change to the register, no new permission, and only additive schema.
- **(B) Also add maker-checker / SoD on issue + receipt** — the same person cannot both issue an
  invoice and record its receipt (mirroring the voucher SoD), with a single-finance-person escape
  so small offices aren't locked out. Larger; changes the issue/receipt flow; its own step.
- **(C) Also gate invoicing on report issuance + link invoice↔report** — a line for a job whose
  report is not yet issued is blocked (or loudly warned), and the invoice records the report it
  bills. A real control on the billing path; behaviour-changing; deferred.

Default if you don't specify: **(A)**.

---

## 6. Tests

1. Uniqueness: after migration a UNIQUE index guards `invoices.invoice_no` (when data is clean);
   many empty-number DRAFTs coexist; a second row with an already-issued number cannot be inserted.
2. Defensive build: with a pre-seeded duplicate invoice number, the migration does **not** crash and
   the duplicates are detectable/surfaced (index skipped).
3. `books_issue` collision: when the next number is already taken, issue re-allocates and succeeds
   with a distinct number (no duplicate, no thrown error to the user).
4. `ar_overdue_reminders`: chases an ISSUED invoice past due; skips a PAID one, a within-terms one,
   and a CANCELLED one; is idempotent (a second run the same day re-nags nothing); a missing
   due_date is not treated as overdue.
5. Preservation: the numbering format, GST/round-off, allocation/overpayment refusal, double-billing
   guard and the job mirror are unchanged; no new permission.
