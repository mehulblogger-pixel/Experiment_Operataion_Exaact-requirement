# EXAACT Sales — the rules only the owner can set

**For:** the product owner · **Written:** 19 Oct 2026

Each of these is a business rule the code cannot be corrected without, because
the correct behaviour depends on how *you* want the company to run. For each:
what the question is, what the software does today, the options, a
recommendation and what it unblocks. **No number, threshold or role below is
chosen for you** — where a recommendation needs a figure, it says "you set it".

Answer in any form — "D-02 yes", "D-03 option B", "D-09 warn at 15 %". As with
Q-B1, Q-G and Q-K, an answer that changes who can do what is written into
`docs/02-permission-matrix.md` in the same commit as the code.

**The eight that block the P0 and P1 fixes** are marked ★. Answering just those lets
the first remediation stage start (`SALES-REMEDIATION-ROADMAP.md` §2).


## Answered by the owner — 20 Oct 2026

| Id | The owner's answer (in their words) | How it is built |
|---|---|---|
| ★ D-01 | "Sales executive, sales manager and anyone who is assigned to do the job." | The four sales roles (Business Development Manager, Key Accounts Manager, Marketing Manager, Marketing Executive) work leads, deals and pipelines; anyone else the company assigns the work to is given the same module on **Roles & access**. *Built 20 Oct* |
| ★ D-02 | "It totally depends on the approval stages defined by the company during company setup or if company grows or changes its mind." | A quotation needs approval before sending **only where the company's approval rules call for it**. *Built 20 Oct* |
| ★ D-03 | "Value approval can be set in the rules of approval." · then "As you recommend." | What happens to a value no rule covers is itself a choice in Set up Sales: **the highest level (default)** · any approver · no approval needed. *Built 20 Oct* |
| ★ D-04 | "Measured before tax." | Rule values are compared with the quotation's subtotal, after discount, before GST. *Built 20 Oct* |
| ★ D-06 | "Approval may not be required … a single person company … if I am branch manager and instead of Sales executive I am preparing the quote then of course I can approve it but lower level cannot approve it. It again depends on approval rules." | A person who holds the approval authority for that level may approve their own quotation; a lower level never can (the step is not theirs). Each is recorded as a self-approval. A company with no rules and "no approval needed" approves nothing. *Built 20 Oct* |
| ★ D-07 | "Any commercial or terms and conditions cancel an approval." | Any change to who it is for, what is offered where, price, quantities, units, tax, validity, payment terms, advance, report-release terms or the terms & conditions returns the quotation to DRAFT and removes its approval. *Built 20 Oct* |
| ★ D-08 | "Person who is assigned to do that work." | The quotation's owner (else whoever raised it), or a master, records accepted / lost. *Built 20 Oct* |
| ★ D-12 | "Nothing only the payment reminder. They can be unblocked by person." | A blacklisted, inactive, on-hold or blocked customer gets **nothing new** — no enquiry, quotation, approval, sending, acceptance, contract, work order or job. What they already owe is still invoiced and reminded, and their payments received. A manager clears a hold under Client holds; someone who may edit the client record makes them Active again. *Built 20 Oct — invoicing work already done kept open, to be confirmed by the owner* |
| new | "Every module shall have simple questions and answers for the user to set up all these things very easily." | A **setup in plain questions** for each module. *Built 20 Oct for Sales (`/sales-setup`), on a shared engine every other module will use* |

**Confirmed by the owner, 20 Oct 2026 (second round):**

| Question | The owner's answer | Effect |
|---|---|---|
| D-12 — should invoicing work already done be blocked for a blocked customer? | "No" | It stays open, with payment reminders and receipts — as built |
| D-01 — is giving someone the Leads module on Roles & access enough for "anyone who is assigned"? | "Enough" | No per-record access is added — as built |
| D-18 — must a quotation lodged on a customer's portal go through approval? | "No" | Registered as sent or lost, no approval; a win is recorded on it by the person assigned — as built. **D-18 answered** |
| D-03 — the default for a value no level covers | "As you recommend" | The approvers of the **highest level** (option B) is now the default; with no levels at all, anyone who may approve. A company can choose otherwise in Set up Sales |

**Answered by the owner, 20 Oct 2026 (third round):**

| Question | The owner's answer | Effect |
|---|---|---|
| D-05 — must levels approve in order? | "Yes" | Level 2 cannot sign until every level-1 step has signed; steps at the same level sign in any order; a rejection at any level still ends the chain. *Built 20 Oct* |
| D-11 A — may a man-month quotation be made only from an approved costing? | "Yes but can also be edited by creator with note or remark to be mentioned mandatory" | Read as: a man-month quotation goes forward only from an APPROVED costing; its creator may still change it away from the costing, but only with a written note, kept in the quotation's history. *Built 20 Oct* — a man-month quotation is submitted, approved or sent only with an approved costing (any revision's link counts; a company can make it optional in Set up Sales); a quotation is made only from an approved costing; a price change away from it needs its creator and a note. *The owner is asked to say if this reading is wrong* |
| D-11 B / D-23 — may a costing's author approve it? | "No" | The author of a costing can never approve it, whatever their role. A costing moves only Draft → Submitted → Approved / Sent back (with a reason); reopening an approved one withdraws its approval. *Built 20 Oct* |
| D-11a — delegation covers quotations, and a step waiting too long escalates? | "Yes to both" | A quotation approver can name someone to approve for them while away (Sales → **Who covers for a quotation approver**), and a delegation for "everything they approve" covers quotations too. Set up Sales asks *after how many days* a waiting step is opened to the next level up (never · 1 · 2 · 3 · 7 days); the night job does it once, writes it in the quotation's history and e-mails the new approvers. *Built 20 Oct* |
| D-10 — the checklist gates send and accept, and a waiver needs a reason and the approver? | "Yes to both" | When the company requires every point, the checklist holds approval (direct and every signature), sending and acceptance; each tick records who and when; a revision starts unticked; someone who may approve waives it with a reason, shown on the quotation with their name. Submitting for approval is not held. *Built 20 Oct* |
| Enquiry branch — an enquiry belongs to the branch of the person who recorded it? | "Yes" | *To be built* |
| D-29 — applying an industry template only adds pipelines; wording and accreditation are administrator-only? | "Yes" | *To be built* |
| D-25 — a commercial change to an open contract needs two signatures and keeps the old values? | "Yes" | *To be built* |
| D-22 — a manager may reopen a lost lead or a closed deal, with a reason? | "Yes" | *To be built; the new transitions are written into `docs/03-object-lifecycles.md` in the same commit* |

**Still open:** D-09, D-13…D-17, D-19…D-21, D-24, D-26…D-28, D-30.
---

## A. Who does what

### D-01 ★ Which roles work leads, deals and pipelines?

- **Today:** only the Master Admin, Admin and Business Director. All four sales
  roles (BDM, KAM, Marketing Manager, Marketing Executive), the Branch Manager
  and Finance are refused (SA-11).
- **Options:** (A) all four sales roles, full use, each seeing their own branch;
  (B) A plus Branch Manager, view only; (C) as today.
- **Recommendation: B.** Salespeople must own their pipeline or the forecast is
  fiction; a Branch Manager needs to see it to run the branch. Finance needs
  only the "register contract from a won deal" door, which should be gated on
  the contract right rather than on leads (fixed regardless of this answer).
- **Unblocks:** SA-11, TM-L01, TM-P01/P02.

### D-08 ★ Who records the customer's decision — accepted or lost?

- **Today:** anyone who can *view* quotes, including Finance and the Branch
  Manager, from any status (SA-03).
- **Options:** (A) the quote's owner and the Marketing Manager; (B) A plus Key
  Accounts Manager and BDM; (C) A plus Finance, because Finance often receives
  the PO first.
- **Recommendation: B**, from SENT or EXPIRED only, with the PO number or
  customer's e-mail reference required on acceptance. ACCEPTED is final (a
  change after that is an amendment, D-25). Only a master may move a quote back
  to DRAFT, with a reason.
- **D-08a — does an EXPIRED quote count as *lost* in the win rate?** Today one
  screen counts it as lost and another ignores it. **Recommendation:** show it
  separately ("lapsed") and leave it out of the win rate — a lapsed quote is
  a follow-up failure, not a lost competition.
- **Unblocks:** SA-03, SA-48, SA-49, TM-Q20…Q26, TM-R04.

### D-30 A *Sales administrator* role?

- **Today:** pipelines and templates need Business Director or Master Admin;
  approval bands and the checklist need Marketing Manager; the Sales lists
  (sources, lost reasons, service types) need an administrator.
- **Recommendation:** a capability like the existing Recruitment Manager
  (`hiring.admin`) that configures **only** Sales — pipelines, bands, checklist,
  letter templates, Sales lists — and nothing system-wide. Off by default.
- **D-30a — should an enquiry belong to a branch?** Today it has no office, so
  branch scope does not apply to enquiries. **Recommendation:** yes, defaulted
  from the person who records it.

---

## B. Approval

### D-02 ★ Must a quotation be approved before it is sent?

- **Today:** the screen says yes; the server lets a DRAFT, an awaiting-approval
  and a rejected quote be sent (SA-01).
- **Options:** (A) always; (B) only when a band covers its value — a quote no
  band covers may be sent without approval; (C) never required.
- **Recommendation: B, with D-03 answered so that no value is uncovered.** This
  is what the lifecycle document already says.

### D-03 ★ What happens to a value no band covers?

- **Today:** a value above the highest band, in a gap between bands, for a
  business unit no rule names, or with no rules at all, gets one generic step
  and may be approved directly by anyone holding the approve right — a ₹9 lakh
  quote was approved by the Marketing Manager although the top band named the
  Business Director (SA-08).
- **Options:** (A) refuse to submit until an administrator adds a band; (B)
  route it to the highest band's approver; (C) as today, but warn.
- **Recommendation: B**, and show the administrator a coverage line on the band
  screen ("covered ₹0 – ₹10 lakh; above that: Business Director").

### D-04 ★ Is the approval value measured before or after tax and discount?

- **Today:** after discount, **including** GST.
- **Recommendation:** **before tax, after discount.** Tax is not margin, and an
  18 % vs 12 % service would otherwise cross a band on tax alone.
- **D-04a — overlapping bands: cumulative or exclusive?** Today every band that
  matches becomes a step, so a value on a shared boundary needs both levels and
  a ₹3 lakh quote in an overlap skipped level 1. **Recommendation: cumulative by
  level** — a ₹3 lakh quote needs level 1 *and* level 2 — with the band screen
  refusing overlapping ranges at the same level.

### D-05 Must levels approve in order?

- **Today:** level 2 can sign before level 1.
- **Recommendation: yes, in order.** The inbox already shows only the lowest
  open level, so this matches what people see.

### D-06 ★ May the preparer approve their own quotation or costing?

- **Today:** yes; the organisation's self-approval switch applies only to
  recruitment. The permission matrix says the ISO/IEC 17020 preparer/approver
  separation is preserved (SA-07).
- **Recommendation: no**, except a master, logged — by switching the existing
  self-approval setting on for quotations and costings. If you have a branch
  where one person must do both, allow it there and record each as a
  self-approval, so the assessor sees it was a decision.

### D-07 ★ Which changes void an approval?

- **Today:** none — an approved quote was re-priced from ₹59,000 to ₹1.06 crore
  and stayed approved (SA-02).
- **Recommendation:** any change to a line, rate, quantity, unit, tax,
  discount, validity, payment terms, advance or customer returns the quote to
  DRAFT. Changes to the covering letter, contact person or internal notes do
  not.

### D-10 The contract-review (pre-order) checklist

- **Today:** blocks only chain-step approval; ticks record no who or when; the
  quote's author can tick everything; ticks carry to revisions; no waiver
  (SA-13).
- **Recommendations:** (a) it gates every route towards the customer — approve,
  send, accept; (b) anyone working the quote may tick, but each tick records who
  and when, and the *approver* confirms the set; (c) a revision starts unticked;
  (d) a waiver needs a reason and the approver's name, and appears on the
  quote; (e) only an administrator edits the list.

### D-11 Project costing — mandatory, and approved first?

- **Today:** optional; a quote can be made from a DRAFT or REJECTED costing; its
  author can approve it (SA-14).
- **Options:** (A) optional, as today; (B) required for deputation / man-month
  quotes; (C) required above a value you set.
- **Recommendation: B**, and a quote may be made only from an APPROVED costing.
- **D-11a — an approver who is away or has left.** Today a step addressed to a
  deactivated person is stuck forever, and delegation does not cover quotes
  even though the setup screen says it covers everything. **Recommendation:**
  delegation covers quotes; a rule cannot name an inactive person; a step
  waiting longer than a time you set escalates to the next level.

### D-09 A margin floor?

- **Today:** none. A quote at a sixth of its loaded cost was approved without a
  warning (SA-14).
- **Options:** (A) show the margin only; (B) warn below a floor you set; (C)
  block below the floor unless a named role overrides with a reason.
- **Recommendation: C**, with the floor set per business unit by you. The
  margin shown is the live one, against the costing and against loaded cost —
  never the costing's old figure.
- **D-09a — who may see cost and margin?** Today everyone except inspectors,
  regardless of the profitability right. **Recommendation:** the quote's
  preparer, its approvers, Finance and management; not Marketing Executives.

---

## C. The customer

### D-12 ★ What may a blacklisted, inactive or on-hold customer still receive?

- **Today:** a blacklisted or inactive customer is never stopped anywhere; an
  on-hold one is stopped only for a non-manager's new quote (SA-17). There are
  two separate status fields.
- **Recommendation:**

| Customer | New enquiry | New quote | Send / accept | Contract | New work order | Invoice |
|---|---|---|---|---|---|---|
| Blacklisted | warn | refuse | refuse | refuse | refuse | allowed (to bill work already done) |
| Inactive | warn | warn — reactivate first | refuse | refuse | refuse | allowed |
| On hold (credit) | allowed | allowed | warn | manager override with reason | manager override with reason | allowed |

### D-26 Client rate contracts?

- **Today:** a repeat work order can be raised under an open contract and a PO
  line carries its rate, but the contract itself holds no rates; reusing a
  contract number re-points the contract to the new quote.
- **Recommendation:** yes — the accepted quote's lines become the contract's
  rate card (the function exists, unused), and a repeat order draws on it. This
  is also the fix for SA-23.

---

## D. Money

### D-13 Reimbursables — travel, lodging, boarding

- **Today:** one clause "at actuals" in the default terms; the engineer's bills
  never reach the invoice (SA-32).
- **Options per item:** included in the rate · billed at actuals · billed at
  actuals up to a cap · not applicable.
- **Recommendation:** a small "reimbursables" table on the quote with those four
  choices per item, printed on the quotation; the job's uploaded bills offered
  as invoice lines when the choice is "at actuals".
- **D-13a — a discount field?** Today a discount is a negative line, invisible
  as a discount. **Recommendation:** a discount figure (amount or %) on the
  quote, shown to the approver and on the PDF; whether some roles may give only
  up to a limit you set is your call.

### D-14 Multi-currency?

- **Today:** currency is free text; bands ignore it; the amount in words always
  says Rupees.
- **Recommendation:** for now, only fix the words (a USD quote says dollars) and
  restrict the field to a list. Full multi-currency (conversion for bands and
  reports) waits until there is export business that needs it.

### D-15 GST type on the quotation?

- **Recommendation:** show it (IGST, or CGST + SGST) from the customer's state
  and yours, as the invoice will; it changes nothing in the total, but customers
  ask.

### D-16 Invoicing beyond the contract value?

- **Today:** nothing stops it — 1,100,586 was invoiced under a 500,000 contract
  (SA-23).
- **Options:** (A) warn; (B) refuse beyond a tolerance you set; (C) refuse, with
  Finance override and reason.
- **Recommendation: C.**
- **D-16a — is the contract value before or after tax?** Today it is the quote's
  total *including* GST. **Recommendation:** store it before tax, show the tax
  separately — invoiced-to-date then compares like with like.

### D-17 How does an order's quantity divide into jobs for billing?

- **Today:** each job defaults to the whole order's quantity (SA-15).
- **Recommendation:** each job bills its own man-days; Finance may change it,
  and a job line that would take the order past its quantity is flagged. Also:
  a work-order rate different from the quote's rate is allowed, but shown on the
  work order and the invoice.

### D-20 What does the accounting system (MGH Books) receive?

- **Today:** every revision of an accepted quote is pushed as a separate
  document, and the ERP also pushes its issued invoices — so Books could invoice
  twice.
- **Options:** (A) push only issued invoices; (B) push the accepted quote as an
  *order* (never an invoice) and its revisions replace it; (C) as today.
- **Recommendation: A**, unless your accountant uses quotes in Books; then B.

---

## E. The lifecycle

### D-21 Should a won opportunity create an enquiry automatically?

- **Today:** no — and the earlier description that said it did was wrong.
- **Recommendation: no.** The enquiry usually comes *before* the deal is won, and
  a repeat order has none. Keep it optional and linkable.

### D-22 May a lost lead or a closed deal be reopened?

- **Today:** a LOST lead cannot be reopened but can, oddly, be converted; WON and
  LOST deals cannot be reopened but can be edited.
- **Recommendation:** reopen, by a manager, with a reason, logged; closed
  records otherwise locked except notes.

### D-23 The project-costing lifecycle

- **Recommendation:** DRAFT → SUBMITTED → APPROVED / REJECTED; reopen only to
  DRAFT and only by the approver, clearing the approval; locked once its quote
  is accepted (audit §3.5). No new statuses.

### D-24 Withdrawn, cancelled and partly accepted quotations

- **Today:** none of the three exists; there is no void and no delete.
- **Recommendation:** add **WITHDRAWN** (we pull an offer before the customer
  answers) and **CANCELLED** (raised in error), each with a reason. Handle a
  *partial* acceptance as a revision containing only the accepted lines, which
  needs no new status. *Per CLAUDE.md, the two new statuses need your explicit
  yes before they are added to the lifecycle document and the code.*

### D-25 Contract amendments, extensions and cancellations

- **Today:** an open contract's value and dates can be changed by an edit with
  no approval; Reopen wipes both signatures (SA-19).
- **Recommendation:** an amendment is a new version of the contract that takes
  the same two signatures as the opening and keeps the old values; an extension
  is an amendment of the end date; cancellation is a status with a reason. A
  change of scope after acceptance is a new quotation revision that becomes the
  amendment. *Needs your yes for the new statuses.*

### D-18 A quote lodged on the customer's procurement portal

- **Today:** registered with whatever status the form sends, including ACCEPTED,
  with no approval (SA-04).
- **Recommendation:** registered as SENT; it goes through the same approval
  bands as any quote **before** it is lodged where a band covers it — or, if your
  teams lodge first and record later, it is registered as SENT and flagged
  "lodged without approval" for the approver to confirm.

### D-19 May work start before the contract is registered?

- **Today:** a work order can be raised against a DRAFT quote, or a contract
  number that does not exist.
- **Options:** (A) never; (B) with a manager's "letter of intent" override and a
  reason; (C) as today.
- **Recommendation: B.** Urgent inspection calls are real; an unrecorded start is
  not.

---

## F. The shape of the business

### D-27 Recruitment's commercial agreement

- **Today:** a requisition carries one typed fee, a rate basis and free-text
  references; none links to a quotation or contract; whether the fee is charged
  to the client or paid to an agency is ambiguous.
- **Options:** (A) a recruitment *fee agreement* on the quotation (percentage of
  annual compensation or fixed, payable on joining / after a retention period,
  with a replacement-guarantee period); (B) keep it inside Recruitment, linked
  to the contract; (C) out of scope.
- **Recommendation: A** if you sell recruitment to clients; it reuses the quote,
  approval and contract spine instead of a second one.

### D-28 Which business models is Sales built for?

- **Recommendation:** inspection (A) and technical manpower (B) now — they share
  the costing engine and the man-day/man-month units; recruitment (C) next via
  D-27; manufacturing, construction and professional services only when you
  decide to sell into them, through one optional **commercial terms** section on
  the quotation (minimum period, mobilisation, overtime, retention, milestones)
  switched on per business unit — not six separate forms. An item master for
  trading is out of scope.

### D-29 Applying an industry template

- **Today:** a Business Director can apply one; it builds pipelines **and** sets
  the company's wording **and** switches the accreditation pack — a test of the
  *Manufacturing* funnel turned the ISO/IEC 17020 pack off (SA-21).
- **Recommendation:** split it — *add this template's pipelines* (Sales
  administrator, D-30) and *set the company's industry, wording and
  accreditation* (administrator only, with a confirmation that names what will
  change). Add one new template, **Technical manpower supply**; no others now.

---

## Index

| ★ | Id | Short question | Findings it unblocks |
|---|---|---|---|
| ★ | D-01 | Who works leads, deals, pipelines | SA-11 |
| ★ | D-02 | Approve before send | SA-01 |
| ★ | D-03 | A value no band covers | SA-08 |
| ★ | D-04 | Band value before/after tax and discount (+ D-04a overlaps) | SA-08 |
| | D-05 | Levels in order | SA-05 |
| ★ | D-06 | Preparer approves own | SA-07 |
| ★ | D-07 | Edits that void an approval | SA-02 |
| ★ | D-08 | Who records accepted / lost (+ D-08a lapsed in win rate) | SA-03, SA-28 |
| | D-09 | Margin floor (+ D-09a who sees margin) | SA-14, SA-33 |
| | D-10 | Contract-review checklist | SA-13 |
| | D-11 | Costing mandatory (+ D-11a away approver) | SA-14, SA-25 |
| ★ | D-12 | Blacklisted / inactive / on-hold customers | SA-17 |
| | D-13 | Reimbursables (+ D-13a discount field) | SA-32, SA-39 |
| | D-14 | Multi-currency | SA-29 |
| | D-15 | GST type on the quote | SA-29 |
| | D-16 | Invoicing beyond contract (+ D-16a contract value before tax) | SA-23 |
| | D-17 | Order quantity across jobs | SA-15 |
| | D-18 | Portal-lodged quotes | SA-04 |
| | D-19 | Work before contract | SA-34 |
| | D-20 | What Books receives | SA-35 |
| | D-21 | Won deal → enquiry (recommended no) | — |
| | D-22 | Reopening leads and deals | SA-27 |
| | D-23 | Costing lifecycle | SA-14 |
| | D-24 | Withdrawn / cancelled / partial | SA-50 |
| | D-25 | Contract amendments | SA-19, SA-10 |
| | D-26 | Client rate contracts | SA-23, SA-31 |
| | D-27 | Recruitment fee agreement | SA-37 |
| | D-28 | Which business models | SA-37, §4 |
| | D-29 | Industry template application | SA-21 |
| | D-30 | Sales administrator (+ D-30a enquiry branch) | SA-33 |

Thirty decisions; seven sub-questions (a).
