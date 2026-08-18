# Inspection Ops — Quotations — Test & Documentation Report

> **Prompt 3 · Module MOD-QUOTES.** Traces to Inventory v1.0 / Governance v1.0. Read
> from `lib/crm.php` (`ops_crm_quotes`, `crm_quote_recalc`, `crm_save_lines`,
> `crm_build_approvals`, `crm_can_act_approval`, `quote_is_locked`, `quote_can_retract`,
> pre-order/blocked-client gates), `lib/pdf.php` (`quote_pdf_build`), and views
> `quote_list.php`, `quote_form.php`, `quote_detail.php`, `quote_external.php`.

| | |
|---|---|
| **Module** | Quotations (MOD-QUOTES) · Area Sales |
| **Personas** | P-MKT / P-BDM (create), P-BM/P-SBU/P-MASTER (approve), P-COORD (view), P-INSP (negative) |
| **Risk weight** | **High** — produces a **price document that goes to the client**; a wrong figure is a Blocker |
| **Verdict** | Complete-with-defects (confirm money & lock cases at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

The quotation is the **priced offer** a TPIA sends a client, its approval, and its
conversion into an order/contract that seeds a Call. Lifecycle:
`DRAFT → PENDING_APPROVAL → APPROVED → (sent) → WON / LOST / REJECTED`, with a **lock**
after it is accepted/lost/sent (unlock only by a granted edit-request), **revisions**
(new rev, resubmit a rejected quote), and **contract registration** (unique number,
expiry & quantity-exhaustion gates).

Screens: `SCR-SALES-quotes` (`/quotes` register), `/quote-new` `/quote-edit`,
`/quote?id=` (detail: lines, locations, approvals, files, followups, **pre-order
checklist**, costing), `/quote-pdf`, `/quote-external` (external-quote capture),
`/preorder-checklist` (config). Tables: `quotations`, `quote_lines`, `quote_locations`,
`quote_approvals`, `quote_approval_rules`, `quote_revisions`, `quote_files`,
`quote_followups`, `quote_edit_requests`.

---

## B. Screen-by-screen catalogue

**`/quote-new` / `/quote-edit`** — header (client [with **blocked-client gate**],
contact, subject, SBU, executing offices [multi], inspection types, product category,
origin, T&C, signatory, site/location, vendor site, currency, validity days, payment
terms, advance %, advance-required, report-vs-payment, **GST %**), line items (desc,
qty, unit, rate → amount), multiple site locations.

**`/quote?id=`** — detail with: status/lock banner; **pre-order review checklist**
(config-driven; may **block approval** when require-all is on); approval chain (level,
who, pending-with); revisions; contract registration; files; followups; linked project
costing; order-jobs. Actions: submit for approval, approve/unapprove, revise, send/
compose (own mailbox), register contract, mark WON/LOST, raise order.

**`/quotes`** — register with search/filter, export (`crm_quotes_export`).

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-QUOTES-form-001 | Client required; **blocked client** refused for non-manager (BLOCKED) / warned (HOLD) — *(I added this; TC-QUOTES-gate-001)*. |
| TC-QUOTES-form-002 | Qty/rate numeric; negative/zero handled; amount = qty×rate. |
| TC-QUOTES-form-003 | GST % within 0–100; validity days ≥ 0. |
| TC-QUOTES-form-004 | Multi-office / multi-site: each line's office & site stored; site auto-fills from picked address. |
| TC-QUOTES-form-005 | Currency & payment terms from client master prefill; editable. |

---

## D. Functions & logic  *(money — highest scrutiny)*

- **`crm_quote_recalc`:** `subtotal = Σ line amounts`; `gst_amount = round(subtotal ×
  gst_pct/100, 2)`; `total_amount = subtotal + gst_amount`. **TC-QUOTES-calc-001..004:**
  verify subtotal, GST rounding (2 dp, half-up), total, and **amount-in-words** match to
  the paisa across screen, PDF and any export. **A mismatch is a Blocker** (wrong number
  to the client).
- **Contract number:** unique (`uq_contract_number` index) — auto-generate, no
  duplicate, auto-close on exhaustion, expiry warning. TC-QUOTES-contract-001..003.
- **Revisions:** `quote-revise` creates a new rev; the prior rev stays on file;
  `is_current` moves. Resubmit a rejected quote → back to PENDING_APPROVAL.

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| DRAFT → PENDING_APPROVAL | submit | lines present; pre-order checklist (if require-all) complete |
| PENDING_APPROVAL → APPROVED | all levels approve | **pre-order checklist blocks the final approval when require-all is on** *(I added this — TC-QUOTES-gate-002)* |
| PENDING_APPROVAL → REJECTED | any approver rejects (reason required) | reason mandatory |
| APPROVED → (sent) → WON/LOST | send + outcome | |
| any → locked | accepted/lost/sent | `quote_is_locked`; edit needs a granted unlock (Master) |

**TC-QUOTES-life-001:** an approver **rejection requires a comment** (else refused).
**TC-QUOTES-life-002:** a locked quote is read-only; editing needs `quote-unlock`
(edit-request granted by Super Admin), then re-locks.

---

## F. Roles, permissions & data scope

Create/edit: `crm.quote.create` / `mod.quotes.edit` / `is_master_of('quotes')`.
Approve: `crm.quote.approve` (+ the resolved approver of the pending step). Send:
`crm.quote.send`. Contract register: `crm.contract.register`. Unlock: Master only.

- TC-QUOTES-perm-001 (P-INSP): `/quotes` and `/quote-new` POST (crafted) → refused.
- TC-QUOTES-perm-002 (P-BDM): can create but **cannot approve** own quote unless an
  approver — confirm self-approval is not possible where independence is required.
- TC-QUOTES-scope-001: a scoped user sees only their office/BU quotes.

---

## G. Settings

`preorder_checklist_on/require/items`, quote letterhead (`quote_lh_*`), signature
(`quote_sig_*`), default T&C (`quote_terms`), GST default (18), currency (`billing_
currency`), contract idle/warn days. **TC-QUOTES-set-001:** with pre-order require-all
**on**, approval is blocked until the checklist is complete; **off**, it is not.

---

## H. Cross-module integration

**Client** (blocked-client gate; master details required), **Orders/Contracts** (raise
order → `partner_contracts`/`billing_orders`, unique contract number), **Calls** (order
carried into a call; quotation_id link), **Project costing** (attach/create),
**Leads/Opportunities** (quote from a lead). **Idempotency:** double-submit/approve
must not create two chains or two contracts. TC-QUOTES-int-001..003.

---

## I. Data integrity & audit trail

- Money never diverges between `quote_lines` rollup and `quotations.total_amount` (recalc
  on every line change). TC-QUOTES-int-010.
- `crm_log_change` records edits, approvals, rejections (who/when/why). Confirm the audit
  captures the **approved figure** and who approved it (a later edit must re-open
  approval, not silently change an approved price — TC-QUOTES-int-011, **verify
  GAP-QUOTES-001**).
- Contract-number uniqueness enforced by DB index (not just app check).

---

## J. Reports & outputs

`quote_pdf_build` — letterhead, doc/format numbers, line table, totals, **amount in
words**, T&C, signature. **TC-QUOTES-out-001:** PDF totals == screen == export to the
paisa; currency symbol correct; GST line correct. Send from the user's own mailbox
(composer) — the sent copy matches the approved version.

---

## K. Negative, edge & resilience

Empty quote (no lines) cannot be submitted; huge line count; a rounding edge (e.g.
33.335); a quote for a blocked client; a duplicate contract number (DB refuses);
concurrent approval by two approvers of the same level.

---

## L. TPIA operational suitability

Fits the TPIA pre-order flow: enquiry/tender review (pre-order checklist), multi-office
execution, inspection-type scoping, contract with expiry & quantity gates feeding
scheduling limits. Blocked-client control is an audit-flagged pre-order gate.

## M. Management usefulness

Sales dashboard (win rates, value by stage), pipeline, followups, ageing; the register
export. Confirm value-by-stage matches the quotes.

## N. UI/UX

Quote playbook (guided next step), lock/unlock clarity, revision history, "+ Add new"
client inline. Terminology (quote/inquiry labels via `T*()`).

## O. Security

Authorisation on approve/send/contract actions (not just hidden buttons); CSRF on posts;
a locked quote cannot be edited via crafted POST; the unlock is Master-gated;
file-download authorisation for `quote_files`.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 UI/Fields/Buttons/Functions | Y | §B–D; money is the priority |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles/Perms | Y | §F |
| 9 Data scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §L |
| 12 Integration | Y | §H |
| 13 Data integrity | Partial | GAP-QUOTES-001 (edit after approval) |
| 14 Audit | Y | §I (crm_log_change) |
| 15 Outputs | **Priority** | §J — money fidelity is release-gating |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Partial | external-quote capture |
| 22 Notifications | Y | approval/send emails |
| 23 Offline | N-A | desktop sales |
| 24 AI | N-A | — |
| 25 Licensing | N-A | — |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | validity, contract expiry |
| 28 Performance | Partial | register at volume |
| 29 Backup | N-A here | — |

**Verdict:** **Complete-with-defects** — the money-fidelity cases (§D/§J) and the
edit-after-approval question (GAP-QUOTES-001) must pass/close before **Complete**.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-QUOTES-001 | (verify — potential Major) | Confirm that editing an **approved** quote re-opens approval rather than silently changing an approved/sent price. If it can change post-approval without re-approval, that is a control gap. |
| GAP-QUOTES-002 | — | Confirm an approver cannot approve a quote they created where independence is required. |
| GAP-QUOTES-003 | — | Verify GST rounding is consistent (half-up, 2 dp) across recalc, PDF and export; and amount-in-words matches the total exactly. |

---

## R. Traceability

RTM slice: `/quotes`, `/quote-*` × dims 1–29 → TC-QUOTES-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — money fidelity + edit-after-approval are the exit
conditions.
