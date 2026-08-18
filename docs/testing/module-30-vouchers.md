# Inspection Ops — Vouchers / Expenses — Test & Documentation Report

> **Prompt 3 · Module MOD-VOUCHERS.** Read from `lib/ops.php` (`ops_vouchers`,
> `voucher_generate`, `voucher_summary`, `voucher_month_frozen`/`can_edit_voucher`,
> `voucher_heads_for`/`voucher_modes_for`, `job_expenses_total`/`job_voucher_total`,
> `expense_extra_headings`, job-close `expenses` INSERT, `expense-delete`,
> `ops_attendance_recon`, `ops_seed_expense_masters`). Views `voucher_list.php`,
> `voucher_detail.php`, `voucher_print.php`, `job_close.php`.

| | |
|---|---|
| **Module** | Vouchers / Expenses (MOD-VOUCHERS) · Area Money |
| **Personas** | P-INSP (own voucher/claim), P-COORD (approve/pay), P-ACCTS, P-MASTER |
| **Risk weight** | **Medium-High** — engineer claims that feed cost & profit; a double-claim overstates cost / underpays |
| **Verdict** | Complete-with-defects (confirm close-replay idempotency, receipt enforcement, three-name sign-off) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Two distinct cost streams: (1) **closure expenses** (`expenses`) booked once at job-close —
5 fixed heads (travel/local/food/lodging/misc) + configurable extras; and (2) the monthly
**voucher** (`vouchers` + `voucher_entries`) — the engineer's "Statement of Travelling
Expenses": a per-day timesheet pulled from their jobs (`voucher_generate`, idempotent),
travel computed server-side (km × mode rate), per-head amounts, rolled to a header total,
with a **DRAFT → SUBMITTED → APPROVED → PAID** sign-off (three names) and a **month-freeze**
lock tied to the cost run. Both feed `job_profit`.

Screens: `/vouchers`, `/voucher?id=`, `/voucher-header/-save/-entry/-generate/-status/-csv/
-print/-file`, job-close expense capture, `/attendance-recon`. Tables: `expenses`,
`vouchers`, `voucher_entries`, `expense_heads`, `travel_modes`, `inspector_allowances`.

---

## B. Screen-by-screen catalogue

**`/vouchers`** — inspector sees own; coordinator+ sees all. **`/voucher`** — header (nature/
advance/office-incurred + supporting file), day rows (travel = km×rate, per-head amounts),
summary (less advance/office-incurred → balance to pay/recover), submit/approve/pay/reopen.
**Job close** — the closure expense row (5 heads + extras). **`/attendance-recon`** — voucher-
derived attendance vs HR CSV.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-VCH-form-001 | Daily hours cap enforced (~8.5h) on save/entry. |
| TC-VCH-form-002 | One date per voucher — duplicate-date add blocked; generate skips existing dates. |
| TC-VCH-form-003 | Travel = round(km × mode rate), server-side; per-head amounts stored only if ≠ 0. |
| TC-VCH-form-004 | Edit gated by `can_edit_voucher` (DRAFT + owner/coordinator + month not frozen). |
| TC-VCH-form-005 | **Receipt requirement NOT enforced** — `needs_receipt` stored but unchecked (GAP-VCH-002). |

---

## D. Functions & logic  *(idempotency + sign-off — highest scrutiny)*

- **Closure expense** (job-close INSERT): 5 heads + `extra` JSON. **The only double-claim
  guard is `closed_flag`** — INSERT happens before the `closed_flag=1` UPDATE (non-transactional),
  so a race or a legitimate reopen/reclose can duplicate; `expense-delete` exists to clean
  these up. **TC-VCH-fn-001 (GAP-VCH-001).**
- **Voucher generate** (`voucher_generate`): pulls the month's WORK days from jobs, idempotent
  (skips existing dates). **TC-VCH-fn-002.**
- **Summary/total** (`voucher_summary`): recomputes each row total + header; less advance/
  office-incurred → balance. **TC-VCH-fn-003.**
- **Sign-off**: DRAFT → SUBMITTED (owner/coord) → APPROVED (coord) → PAID (coord); reopen →
  DRAFT. **Approvers stored as free-text names, not FK; no event log** (GAP-VCH-003).
  **TC-VCH-fn-004.**
- **Recon** (`ops_attendance_recon`): voucher day-types vs HR CSV → OK/MISMATCH; advisory,
  no auto-posting. **TC-VCH-fn-005.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| voucher DRAFT → SUBMITTED | submit | owner/coord, from DRAFT |
| SUBMITTED → APPROVED | approve | coordinator, from SUBMITTED |
| APPROVED → PAID | pay | coordinator |
| any → DRAFT | reopen | coordinator |
| month → frozen | cost run | blocks edits |
| closure expense | job-close | one per close (closed_flag guard) |

- **TC-VCH-life-001:** a re-posted close form files no second expenses row (guarded by
  `closed_flag` — but see idempotency gap).
- **TC-VCH-life-002:** a frozen month blocks voucher edits.

---

## F. Roles, permissions & data scope

Inspector: own voucher only (`my_inspector_id`). Coordinator-level: view all, approve, pay,
reopen, generate, edit. `expense-delete`: coordinator/`finance.reconcile`/master. Allowances:
Super Admin. Voucher routes deliberately **not** in the module gate so inspectors reach them.

- TC-VCH-perm-001 (approve without coordinator level) → refused.
- TC-VCH-perm-002: an inspector cannot see another's voucher.

---

## G. Settings

Expense-head master (`EXPENSE_HEADS_SEED`), travel modes (`TRAVEL_MODES_SEED`), per-inspector
allowances/rate overrides, base closure headings (renamable via `expense_heading` lookup),
daily hours cap. **Two parallel head taxonomies** (`expense_heads` vs `expense_heading`) risk
drift (GAP). **TC-VCH-set-001:** new heads appear on the close form and voucher.

---

## H. Cross-module integration

**Jobs close** (closure expense capture — MOD-05), **Profitability** (`job_expenses_total` +
`job_voucher_total` feed `job_profit`, `job_recovered_total` offsets — MOD-32), **Attendance**
(recon vs HR CSV — MOD-31), **Cost run** (month freeze). Idempotency: the close guard is the
weak point.

---

## I. Data integrity & audit

No DB uniqueness on `expenses(job_id)` or `voucher_entries(voucher_id, entry_date)` — guards
are per-request SELECT-then-INSERT (race-tolerant, not race-proof). Approvers free-text
(weak trail). **TC-VCH-int-010:** a reopened/reclosed job does not double-book expenses;
**TC-VCH-int-011:** recovered offset is capped at expenses+voucher (a mis-keyed bill cannot
invent profit).

---

## J. Reports & outputs

The voucher CSV/print (Statement of Travelling Expenses), the closure expense on the job, the
attendance-recon report. **TC-VCH-out-001:** the print total == the summed rows less advance/
office-incurred.

---

## K. Negative, edge & resilience

A close replayed (guarded by `closed_flag`, but the INSERT-before-flag race); a reopened/
reclosed job (can duplicate — clean via `expense-delete`); a duplicate voucher day
(SELECT-then-INSERT); a claim needing a receipt with none (not enforced); a negative amount
(not rejected); a frozen month edit (blocked); an HR-vs-app attendance mismatch (advisory).

---

## L. TPIA operational suitability

Fits the field-engineer reality: entitlement-limited heads/modes with per-km rates, a monthly
statement generated from real jobs, a three-name sign-off, and a freeze tied to the cost run.
The receipt-enforcement and idempotency gaps are the items to close for financial control.

## M. Management usefulness

Voucher totals and closure expenses feed job/SBU profit; recon flags attendance mismatches.
Confirm no double-claims and that receipts are enforced where required.

## N. UI/UX

Generate-from-jobs, server-side travel calc, print/CSV, clear balance-to-pay. Terminology via
`T*()`.

## O. Security

Own-voucher scoping; approve/pay coordinator-gated; **no receipt enforcement, weak approver
trail, no DB uniqueness on expense lines** — harden for audit.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E sign-off |
| 6 Validation | Partial | §C receipts |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F own-voucher |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §D |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I idempotency |
| 14 Audit | Partial | §I free-text approvers |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Y | HR CSV recon |
| 22 Notifications | N-A | — |
| 23 Offline | **Y** | close replay idempotency |
| 24 AI | N-A | — |
| 25 Licensing | Y | Money module |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | month freeze |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-VCH-001 | (verify — Major) | Closure-expense **double-claim** is guarded only by `closed_flag`, and the INSERT happens before the flag UPDATE (non-transactional) — a race or a reopen/reclose can duplicate. Add a DB uniqueness/transaction; the offline queue is the real risk. |
| GAP-VCH-002 | (verify) | **`needs_receipt` is never enforced** — heads flagged as requiring a receipt accept amounts with no attachment. Enforce a receipt where configured. |
| GAP-VCH-003 | (verify) | Voucher sign-off names (checked/approved/authorized) are **free-text, not user FKs, with no event log** — weak audit trail. Tie to user ids + log. |
| GAP-VCH-004 | — | Two parallel expense-head taxonomies (`expense_heads` vs `expense_heading` lookup) risk label/mapping drift; and no negative/ceiling validation on amounts. |

---

## R. Traceability

RTM slice: `/vouchers`, `/voucher-*`, job-close expenses, `/attendance-recon` × dims 1–29 →
TC-VCH-* → results → DEF/GAP. **Verdict: Complete-with-defects** — close-replay idempotency,
receipt enforcement, and the sign-off trail are the exit conditions.
