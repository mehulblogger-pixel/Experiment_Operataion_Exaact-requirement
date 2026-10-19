# EXAACT Sales — remediation roadmap

**Written:** 19 Oct 2026 · **Status:** proposed — **nothing here is built until
the owner approves the scope** (§0).

Findings `SA-nn`: `SALES-USER-AND-INDUSTRY-AUDIT.md` §10. Decisions `D-nn`:
`SALES-BUSINESS-DECISIONS.md`. Tests `TM-xx`: `SALES-END-TO-END-TEST-MATRIX.md`.
Effort: **S** under a day · **M** one to three days · **L** a week or more,
including its tests and documentation.

---

## 0. What the owner is asked to approve

1. **The scope of Stage 1** (§2) — twelve server-side corrections that close the
   approval and acceptance bypasses and the four default-billing errors. Every
   one is a guard on a route that already exists; none needs a new screen.
2. **The eight ★ decisions** in `SALES-BUSINESS-DECISIONS.md` (D-01, 02, 03,
   04, 06, 07, 08, 12). Stage 1 items that do not depend on them (§2, marked
   "no decision") can start on approval of the scope alone.
3. **Whether Stages 2–6 follow in this order**, or whether something later is
   more urgent for the business.

**Nothing is live until uploaded** (`../14-LIVE-CHECKS-GUIDE.md` Part 2). The
earlier security fixes (GAP-048, GAP-049) are in the same position.

---

## 1. How every item will be built

- **Smallest reliable change.** A server-side guard on the route that already
  exists, not a new screen. The hidden buttons stay; the server stops relying on
  them.
- **Reuse, never duplicate.** The lifecycle document's quotation diagram becomes
  the code's transition table; `crm_log_change()` writes history;
  `access_owner_grants()` carries owner rules into stored grids (as Q-B1, Q-G,
  Q-K did); the organisation's self-approval switch already exists;
  `contract_lines_from_quote()`, `quote_validity()` and
  `lead_possible_duplicate()` exist unused; the version check that protects
  calls and jobs (`ops.php:616`) protects quotes; the GAP-047 figure validators
  cover costing inputs.
- **Test first.** Each item starts by turning its `TM-` rows into a guard-rail
  test that fails on today's code, then makes it pass. The probes in
  `../evidence/sales-audit/` are the starting fixtures.
- **Docs in the same commit.** A change to who can do what updates
  `docs/02-permission-matrix.md`; a change to a status or transition updates
  `docs/03-object-lifecycles.md` — and only after the owner's yes (CLAUDE.md).
- **Zero Training UI.** Every refusal is one sentence that says what to do next,
  in the style the enquiry form already uses.
- **Verified live** on throwaway copies over HTTP, BEFORE and AFTER, as for
  GAP-047/048/049.

---

## 2. Stage 1 — make the commercial controls hold (P0 / P1)

| # | Correction | Findings | Depends on | Change | Acceptance criteria | Regression | Effort |
|---|---|---|---|---|---|---|---|
| 1.1 | **Approval follows the branch** | SA-06 | no decision | Resolve the approval step to its quote *before* the scope gate's early return, as `quote-file` already does (`crm.php:1410`) | An out-of-branch approval is refused; an in-branch one works | TM-A20, A21 | **S** |
| 1.2 | **One transition table for quotations** | SA-01, SA-03, SA-48, SA-49 | D-02, D-08 | A from → to table at `quote-status` matching the lifecycle; SENT only from APPROVED; ACCEPTED/LOST only from SENT/EXPIRED and only for the D-08 roles; ACCEPTED final; LOST needs a reason; a repeated decision is a no-op | Every transition not in the table is refused with a sentence; the buttons and the server agree | TM-Q07…Q10, Q20…Q28 | **M** |
| 1.3 | **An edit voids the approval** | SA-02 | D-07 | A save that changes any D-07 field on a PENDING_APPROVAL or APPROVED quote returns it to DRAFT, voids its steps, logs old → new total | The ₹59,000 → ₹1.06 crore case lands in DRAFT | TM-Q14…Q16 | **S** |
| 1.4 | **A rejection ends the chain; stale steps cannot be signed** | SA-05 | D-05 | `quote-approve` signs only while PENDING_APPROVAL; any REJECTED step sets the quote REJECTED; if D-05 says so, only the lowest open level | The four sequences refuse | TM-A10…A13 | **S** |
| 1.5 | **Portal-lodged quotes cannot invent a status** | SA-04 | D-18 (default: SENT) | Whitelist the status; total = Σ lines; origin and total required on the server | `ACCEPTED`, `NOT_A_STATUS`, an empty submit and a mismatched total are refused | TM-Q30…Q33 | **S** |
| 1.6 | **Contract only on a current, accepted quote** | SA-09, SA-10 | no decision | `quote-contract` requires ACCEPTED and `is_current`; every action on a superseded revision is refused; Command Centre "Committed" sums current revisions | DRAFT, LOST and superseded registrations refused; Committed = 4,24,800 on the probe dataset | TM-K01…K03, Q40…Q42, R05 | **S** |
| 1.7 | **Every commercial decision is recorded** | SA-12 | no decision | One `crm_log_change` line per transition — who, when, from, to, reason, old/new total; one-click approval logged; a rejected chain archived, not deleted; lead, deal, enquiry and costing saves logged through the activity spine | Each lifecycle row writes exactly one line | TM-H01…H05 | **M** |
| 1.8 | **Default billing figures are the job's own** | SA-15, SA-16, SA-22, SA-36 | no decision (D-17 recommendation is the default) | Invoice line defaults to the job's man-days; billable reconcile keeps the event's amount and stores the invoice id; a work order from a deal takes the accepted quote's subtotal; an invoice refuses a job of another customer | The money probe reproduces 19,000 / 47,790 / 1,00,000 / refusal | TM-B05, B06, B12, K20 | **M** |
| 1.9 | **The people who sell can sell** | SA-11, SA-20 | D-01 | Grant `mod.leads` to the D-01 roles via `access_owner_grants()`; gate `opportunity-contract` on `crm.contract.register`; gate `/contract-open` on the endorse/approve functions, not the quotes module; matrix rows in the same commit | The named roles open and use Leads; Finance registers from a won deal; OM endorses | TM-P01, P02, O09, K05 | **S** |
| 1.10 | **Preparer ≠ approver** | SA-07 | D-06 | Apply the existing self-approval switch to quotations and costings; master exception logged | With the switch on, the creator cannot approve | TM-A15, C05 | **S** |
| 1.11 | **No value without an approver; bands that cannot leave holes** | SA-08 | D-03, D-04 | Unmatched value per D-03; band value per D-04; band screen shows a coverage line | ₹9 lakh above the top band goes to the top approver (if D-03 = B) | TM-A01…A07 | **M** |
| 1.12 | **A blacklisted customer gets no new business** | SA-17 | D-12 | One rule, checked at send, acceptance, contract registration and work order, reading both status fields | Each cell of the D-12 table behaves as decided | TM-D10…D13 | **M** |

**Gate to Stage 2:** every row above has a passing guard-rail test, the full
suite is green, and each was re-driven live on a throwaway copy.

---

## 3. Stage 2 — clean data and honest forms (P2)

| # | Correction | Findings | Depends on | Acceptance | Regression | Effort |
|---|---|---|---|---|---|---|
| 2.1 | **No duplicate customers** — normalised name + GSTIN "did you mean" at acceptance and on typed enquiries; row lock on lead conversion; Ads Pro import calls `lead_possible_duplicate()`; enquiry numbers from a sequence with a unique index | SA-18 | — | The four duplicate cases yield one record + a visible "possible duplicate" | TM-L04, L08, L09, E06, E08, D01…D03 | **M** |
| 2.2 | **The quotation form is honest** — three-level requirement (draft / submit / send, audit §6.2); a refused save keeps what was typed; a once-only form token; the calls/jobs version check | SA-26, SA-43 | — | Empty submit refused; double click → one quote; concurrent save told "changed" | TM-Q01…Q06, Q29, Q53 | **M** |
| 2.3 | **Lines belong to their own quote's sites** — reject a foreign or missing site id; temporary keys let a new quote pair lines before saving | SA-24 | — | | TM-Q50…Q52 | **S** |
| 2.4 | **Lead, deal and enquiry transitions** (audit §3.1–3.3) — validate enquiry status; a deal is lost only when every linked quote is closed with none accepted; closed records locked except notes; a quote for another customer cannot win a deal | SA-27 | D-22 | | TM-L05…L07, O02…O05, E02…E04 | **M** |
| 2.5 | **Costing lifecycle and inputs** — §3.5 transitions; GAP-047-style validation of heads, percentages and one-offs; quote only from APPROVED costing; reopen clears the approval | SA-14 (part) | D-11, D-23 | | TM-C02…C08, C11 | **M** |
| 2.6 | **Approvals in the one inbox; nobody stuck** — quote approvals listed in `/approvals`; home tile counts match; a band cannot name an inactive person or an unheld role; delegation covers quotes; band form validated; band changes logged | SA-25, SA-45 | D-11a | | TM-A40…A45, H04 | **M** |
| 2.7 | **Contract-review checklist at every door** — approve, send, accept; who/when per tick; revision starts unticked; waiver with reason | SA-13 | D-10 | | TM-A30…A35 | **M** |
| 2.8 | **Every record has a branch** — default office from the user; "OWN" business-unit scope with no unit fails closed (as the October office-scope fix did); delete no longer implies edit for enquiries | SA-33 | D-30a | | TM-P10…P13, E10 | **S** |
| 2.9 | **Industry template split** — "add pipelines" separate from "set wording and accreditation"; the latter `settings.manage` only, with a confirmation that names what will change | SA-21 | D-29 | BD cannot switch the accreditation pack | TM-P09, T03 | **S** |
| 2.10 | **Contract amendments** — commercial edits on an OPEN contract become a pending amendment with two signatures; Reopen only from CLOSED/REJECTED; registration in one transaction | SA-19, SA-47, SA-51 | D-25 | | TM-K07, K08, K10…K12 | **L** |

---

## 4. Stage 3 — the price approved is the price billed (P1 / P2)

| # | Correction | Findings | Depends on | Acceptance | Regression | Effort |
|---|---|---|---|---|---|---|
| 3.1 | **The contract keeps the accepted quote's rate card** — call the existing `contract_lines_from_quote()`; quantities per unit, not summed; a repeat quote draws down without re-pointing the contract | SA-23, SA-31 | D-26 | | TM-K33, K40 | **M** |
| 3.2 | **Work-order rate defaults from the rate card, and a difference is shown** on the work order and the invoice | SA-23 | D-17 | 9,500 vs 8,000 visible at both | TM-K30 | **S** |
| 3.3 | **Contract value before tax; invoiced-to-date against it** | SA-23 | D-16, D-16a | 11,00,586 against 5,00,000 warns or is refused per D-16 | TM-K31, K32 | **M** |
| 3.4 | **Live margin on the quote and a floor** | SA-14 | D-09, D-09a | Below the floor: per D-09; margin hidden from roles D-09a excludes | TM-C09, P07 | **M** |
| 3.5 | **Costing → quote carries business unit, owner, GST and inclusions/exclusions** | SA-30 | — | | TM-C10 | **S** |
| 3.6 | **Work before contract** per D-19 (letter-of-intent override); a CLOSED contract's message says CLOSED; no job on a CLOSED contract | SA-34 | D-19 | | TM-K50…K53 | **S** |
| 3.7 | **Reimbursables and discount** — the reimbursables table; job bills offered as invoice lines; a named discount the approver sees | SA-32, SA-39 | D-13, D-13a | | TM-B10, Q61 | **M** |
| 3.8 | **Books receives one document per deal** | SA-35 | D-20 | | TM-X01 | **S** |
| 3.9 | **Invoice GST from the quote** | SA-23 | D-15 | | TM-B13 | **S** |

---

## 5. Stage 4 — one number for each question (P2)

| # | Correction | Findings | Acceptance | Regression | Effort |
|---|---|---|---|---|---|
| 4.1 | **One definition per measure** (reconciliation §5): FY means FY; acceptance rate with EXPIRED shown as lapsed; "Became opportunities" scoped; "due in 30 days" excludes overdue (shown separately); funnel conversion over stages actually reached; won value by accepted date | SA-28 · D-08a | The probe dataset reproduces the "Expected" column of reconciliation §6 on every screen | TM-R01…R08 | **M** |
| 4.2 | **The five missing reports** (reconciliation §7) plus *contracts at risk* | SA-40 | Each answers its question on the probe dataset | TM-R10…R14 | **L** |
| 4.3 | **One Sales dashboard** — the monthly table becomes its tab; linked from the Sales hub | SA-41 | One heading, one door | — | **S** |

---

## 6. Stage 5 — usability and wording (P3)

| # | Correction | Findings | Effort |
|---|---|---|---|
| 5.1 | "Enquiry" on every screen; distinct headings for the two template screens and the two approval-rule screens; label the hub's Command Centre button | SA-41, SA-42 | **S** |
| 5.2 | Currency from a list; amount in words in the quote's currency | SA-29 | **S** |
| 5.3 | A settings panel for the costing loading defaults; label target margin vs negotiation allowance | SA-46, §6.1 | **S** |
| 5.4 | Round quantity once before multiplying; verify on MariaDB | SA-44 | **S** |
| 5.5 | Notify each approval level as it opens | SA-45 | **S** |
| 5.6 | Refuse deleting a lead something points at; fix the `call-new?contract_id=` message; refuse revising an old revision with a sentence | SA-51, SA-52 | **S** |
| 5.7 | Give `crm_orders` a meaning or remove it from the grid | SA-53 | **S** |

---

## 7. Stage 6 — the business models (after D-24, D-27, D-28)

| # | Work | Depends on | Effort |
|---|---|---|---|
| 6.1 | WITHDRAWN and CANCELLED quotation statuses; partial acceptance as a revision of the accepted lines | D-24 | **M** |
| 6.2 | **Technical manpower supply** industry template | D-29 | **S** |
| 6.3 | An optional **commercial terms** section on the quotation, per business unit: minimum period, mobilisation / demobilisation, overtime, extension, hour unit | D-28 | **L** |
| 6.4 | **Recruitment fee agreement** on the quotation: % of annual compensation or fixed, on joining / after retention, replacement guarantee; requisition linked to the contract | D-27 | **L** |
| 6.5 | Bill-to address with its own GSTIN | SA-38 | **M** |

**Later, only on a business decision to sell into those markets:** multi-currency
with conversion; IGST / CGST type at quote stage; construction (BOQ,
provisional quantities, retention, milestones, variation orders); trading
(item master, price breaks, MOQ); professional services retainers and
milestones; two industry templates side by side.

---

## 8. Documentation owed

| Document | Change | When |
|---|---|---|
| `docs/03-object-lifecycles.md` | Lead, Opportunity, Enquiry and Project-costing sections; the quotation diagram's corrections | with Stage 1.2 / 2.4 / 2.5, after D-22…D-25 |
| `docs/02-permission-matrix.md` | Rows for leads, deals, pipelines, costing, "record the customer's decision", contract from a won deal | with Stage 1.2 / 1.9 |
| `docs/04-flows/*.md` | The BDM / KAM / MM flows start where the code lets them | with Stage 1.9 |
| `../workflows/01-SALES.md` | Corrected in this audit's commit (status section) | done |
| `../09-UAT-PLAN.md` | T-11 and T-16 answered by this audit | done |

---

## 9. If only five things are done

1. **1.3 — an edit voids the approval.** The single most consequential gap, and
   about a day's work once D-07 is answered.
2. **1.2 — one transition table for quotations.** Closes send-without-approval
   and view-is-decide together.
3. **1.8 — default billing figures are the job's own.** Stops over-billing that
   needs no bad intent to happen.
4. **1.9 — the people who sell can sell.** Without it, the pipeline and the
   forecast are a director's notebook.
5. **1.7 — every commercial decision is recorded.** The evidence an assessor and
   a dispute both need.

---

## 10. Effort, roughly

| Stage | Items | Effort |
|---|---|---|
| 1 — controls hold | 12 | about two weeks |
| 2 — clean data, honest forms | 10 | about three weeks |
| 3 — price approved = price billed | 9 | about two weeks |
| 4 — one number per question | 3 | about two weeks |
| 5 — usability | 7 | a week |
| 6 — business models | 5 | per decision |
