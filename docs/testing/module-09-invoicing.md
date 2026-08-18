# Inspection Ops — Invoicing — Test & Documentation Report

> **Prompt 3 · Module MOD-INVOICING.** Read from `lib/ops.php` (`ops_invoicing`,
> `ops_invoicing_counts`, `invoicing_filter_sql`, `job-invoice`, `job-bill` handlers),
> `lib/bills.php` (chargeable-head recoveries: `chargeable_heads`, `job_bills`,
> `job_bills_missing`, `job_bills_block`, `job_recovered_total`, `job_bill_add`,
> `ops_job_bill`), `lib/books.php` (GST invoice engine: `books_invoice_create`,
> `books_line_add`, `books_invoice_recalc`, `books_is_igst`, `books_default_gst`,
> `books_invoice_issue`), `lib/tally.php` (GSTIN/state, export), views `invoicing.php`,
> `books/invoice.php`, `job_detail` bill panel.

| | |
|---|---|
| **Module** | Invoicing (MOD-INVOICING) · Area Money |
| **Personas** | P-ACCTS (`finance.reconcile`/`data.credit`), P-BM/P-SBU (oversight), P-MASTER, P-COORD (bill recoveries), P-INSP (negative) |
| **Risk weight** | **High** — produces the GST tax invoice sent to the client; a wrong tax split or number is a compliance defect |
| **Verdict** | Complete-with-defects (confirm GST split, numbering, round-off, credit-note-not-edit at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Invoicing has three layers:

1. **Job money tracking** (fields on `jobs`): `invoice_raised / invoice_number /
   invoice_date / invoice_due_date / invoice_amount / payment_received / payment_amount /
   credit_received`. Drives the **invoicing register** (unbilled / awaiting / overdue /
   outstanding) and per-job money state.
2. **GST invoice engine** (`books.php`, the "Money module"): a real tax invoice with
   **CGST+SGST vs IGST** decided from the client/office state, per-line GST, subtotal,
   round-off, total, GST-safe **numbering** (a series may not have gaps or edits — a wrong
   invoice is corrected by a **credit note**, never edited), and Tally/e-invoice export.
3. **Chargeable-head recoveries** (`bills.php`): heads the client is charged for
   (e.g. travel) must have a **bill on file before the job closes** (`job_bills_block`),
   or be explicitly declared **nil** (MOD-05 close). One-click **job → GST invoice**
   (`job-bill`) fills the amount from the quote/call so nobody re-keys it.

Screens: `/invoicing` (register + counts), `/job?id=` (invoice/payment panel + bill
recoveries), `/invoice?id=` (the GST invoice), `job-bill` (raise). Tables: `invoices`,
`invoice_lines`, `job_bills`, plus job money fields.

---

## B. Screen-by-screen catalogue

**`/invoicing`** — register scoped by office/SBU; counts (pending-to-bill / awaiting
payment / overdue / unbilled value / outstanding value); filter awaiting/overdue/all;
CSV export.

**`/job-invoice`** (panel on job) — record invoice raised, number, date, due date,
amount, payment received/date/amount, credit received.

**`/job-bill`** — one-click draft GST invoice from a **closed** job; amount auto-derived
(`books_line_add` reads billable rate/qty/value); redirects to `/invoice?id=`.

**`/invoice?id=`** — GST invoice: header (client GSTIN, place of supply, supplier state,
IGST flag), lines (desc, qty, rate, GST %, CGST/SGST/IGST, line total), subtotal, round-
off, total, amount in words; issue → number assigned; credit-note route.

**Bill recoveries** (job panel) — per chargeable head: recovered amount, upload proof;
missing heads flagged.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-INV-form-001 | Amounts numeric; negative handled; payment amount ≤ invoice amount (or flagged). |
| TC-INV-form-002 | GST % 0–100; default from settings (18) unless overridden per line. |
| TC-INV-form-003 | Due date ≥ invoice date; overdue derived when due < today and unpaid. |
| TC-INV-form-004 | Chargeable-head bill: amount + optional proof; a missing agreed head blocks job close (unless nil-declared). |
| TC-INV-form-005 | Client GSTIN / place-of-supply drive the IGST-vs-CGST+SGST decision. |

---

## D. Functions & logic  *(tax + numbering — highest scrutiny)*

- **IGST vs CGST+SGST** (`books_is_igst`): decided from the two parties' states; when
  **neither is known it does NOT guess** — defaults to the local CGST+SGST case, and the
  same source drives both the invoice and the ledger (no divergence). **TC-INV-calc-001**
  (inter-state → IGST), **TC-INV-calc-002** (intra-state → CGST+SGST split half/half with
  the remainder on SGST), **TC-INV-calc-003** (unknown state → local, not a guess).
- **Recalc** (`books_invoice_recalc`): line tax = round(amount × gst%/100, 2); CGST =
  round(tax/2, 2), SGST = tax − CGST (no lost paisa); subtotal/round-off/total roll up.
  **TC-INV-calc-004** — totals reconcile to the paisa across screen, PDF, export.
- **Numbering** (GST-safe): a series has **no gaps and no edits**; an issued invoice is
  immutable; a correction is a **credit note**. **TC-INV-num-001** (sequential, no gap),
  **TC-INV-num-002** (issued invoice cannot be edited — credit note only).
- **Job → invoice** (`books_line_add` with `job_id`): amount auto-derived from the
  quote/call; the job must be **closed** first. **TC-INV-fn-001.**
- **Bill block** (`job_bills_block`): agreed chargeable heads without a bill (and not
  nil-declared) block job close. **TC-INV-fn-002.**
- **Counts** (`ops_invoicing_counts`): unbilled uses invoice_value (falling back to
  expected_credit), outstanding uses invoice_amount; credit-GIVEN jobs excluded from
  pending-to-bill. **TC-INV-fn-003** — counts match the register rows.

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| job closed → invoiceable | close | report/bills gates (MOD-05) |
| → draft GST invoice | job-bill | job closed; Money module enabled |
| draft → issued | invoice issue | number assigned; becomes immutable |
| issued → paid | payment recorded | payment fields |
| issued → corrected | credit note | never an edit of the issued invoice |
| issued unpaid + due<today → overdue | time | register flag |

- **TC-INV-life-001:** an issued invoice is immutable — a correction goes via a credit
  note, not an edit.
- **TC-INV-life-002:** raising an invoice on an unclosed job is refused.

---

## F. Roles, permissions & data scope

Record invoice/payment: `data.credit` or `finance.reconcile`. Raise GST invoice:
master / `finance.reconcile` / `mod.invoicing.view`. Bill recoveries: coordinator /
accounts / master. Register scoped by office/SBU; credit columns gated by `data.credit`.

- TC-INV-perm-001 (P-INSP invoice POST) → refused.
- TC-INV-perm-002: a scoped accounts user sees only their office's invoices; credit
  figures gated.
- TC-INV-scope-001: no cross-office invoices via crafted id.

---

## G. Settings

Default GST % (`tally_gst_pct`, 18), supplier state/GSTIN, invoice series & numbering,
payment terms/credit days, e-invoice/Tally export config, round-off policy, chargeable-
head master. **TC-INV-set-001:** GST default applies to new lines unless overridden;
**TC-INV-set-002:** the invoice series is per configuration and gap-free.

---

## H. Cross-module integration

**Jobs** (source amount; close gate; job-bill), **Quotations/Orders** (billable rate/
value; contract number → invoice), **Calls** (PO number → invoice), **Credit/Reconcile**
(inter-office credit vs invoice — MOD-31), **Client master** (GSTIN/state → tax split),
**Books/Ledger** (posting; same IGST source), **Tally/e-invoice** (export), **Profit/MIS**
(invoiced/paid/overdue feed dashboards — MOD-32/34). Idempotency: double job-bill must not
create two invoices for one job — TC-INV-int-001.

---

## I. Data integrity & audit

Invoice ↔ line rollup never diverges (recalc on every line change); the IGST decision is
the same on the invoice and the ledger; numbering is gap-free and immutable; credit notes
reference the original. Payment recorded against the right invoice. **TC-INV-int-010:**
an issued invoice's figures cannot change silently; **TC-INV-int-011:** a credit note
nets correctly against the original.

---

## J. Reports & outputs

The GST invoice PDF (letterhead, GSTIN, place of supply, line table with tax columns,
totals, amount in words), the invoicing register CSV, Tally/e-invoice export. **TC-INV-out-001:**
PDF tax split == screen == export to the paisa; **TC-INV-out-002:** amount in words matches
the total; **TC-INV-out-003:** the export carries a valid, gap-free number.

---

## K. Negative, edge & resilience

Invoice on an unclosed job; a rounding edge (33.335); an unknown-state client (local, not
guessed); a payment exceeding the invoice; a duplicate job-bill; editing an issued invoice
(refused → credit note); a chargeable head with no bill at close; a credit-GIVEN inter-
office job (excluded from client billing).

---

## L. TPIA operational suitability

Handles the TPIA billing reality: per-job billing off the agreed rate/man-days,
chargeable-head recoveries (travel/lodging) with proof, inter-office credit kept separate
from client billing, GST-correct tax invoices with the CGST/SGST/IGST decision made from
real state data, and credit-note corrections rather than silent edits.

## M. Management usefulness

Unbilled / awaiting / overdue / outstanding at a glance; ageing; one-click billing from a
closed job; feeds profitability and MIS. Confirm outstanding == sum of unpaid issued
invoices.

## N. UI/UX

One click closed-job → draft invoice with the amount pre-filled; register counts as
filters; clear paid/unpaid/overdue states. Terminology (invoice/client via `T*()`).

## O. Security

Invoice/payment actions authorised server-side; credit columns gated by `data.credit`;
issued-invoice immutability enforced (edit refused); numbering not client-settable; export
authorised; no cross-office invoice access via crafted id.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D; tax + numbering priority |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I tax/numbering/rollup |
| 14 Audit | Y | §I |
| 15 Outputs | **Priority** | §J GST PDF/export fidelity |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Partial | Tally/e-invoice export |
| 22 Notifications | Partial | payment reminders |
| 23 Offline | N-A | accounts desktop |
| 24 AI | N-A | — |
| 25 Licensing | Y | Money module toggle |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | due dates, series per FY |
| 28 Performance | Partial | register at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-INV-001 | (verify — potential Major) | Confirm the **IGST vs CGST+SGST** decision and the per-line rounding reconcile to the paisa across invoice, ledger, PDF and export; and that an unknown state defaults local rather than guessing. |
| GAP-INV-002 | (verify — Major) | Confirm **GST-safe numbering**: the series is gap-free, an issued invoice is immutable, and corrections are credit notes (crafted edit of an issued invoice refused). |
| GAP-INV-003 | (verify) | Confirm **job-bill idempotency**: a double raise does not create two invoices for one job. |
| GAP-INV-004 | — | Confirm inter-office **credit-GIVEN** jobs are excluded from client billing so nothing is billed twice. |

---

## R. Traceability

RTM slice: `/invoicing`, `/job-invoice`, `/job-bill`, `/invoice`, bill recoveries × dims
1–29 → TC-INV-* → results → DEF/GAP. **Verdict: Complete-with-defects** — GST tax split +
rounding, gap-free immutable numbering, and job-bill idempotency are the exit conditions.
