# D5 — Business glossary

What each business word means in EXAACT, what it is called on screen, and where
the screens drift from the agreed word.

---

## 1. The glossary already exists in the code — and it is good

Before writing this, I expected to have to invent definitions from field names.
That turned out to be unnecessary. **`phpapp/lib/terms.php` holds a canonical
vocabulary of 33 business terms, each with a singular, a plural, a group, and a
plain-English definition** — and the definitions are better than most
glossaries written by hand.

So this document does three jobs:

1. **Publish** that vocabulary, which is currently only in code.
2. **Check** it against the 1,441 distinct field labels measured across 256
   screens (`evidence/screen-analysis.txt`).
3. **Report the drift** — screens using a word the vocabulary does not have, or
   using an agreed word differently.

**And it corrects two findings I made in `04-SCREEN-AND-FIELD-AUDIT.md`
(§8/S4-8) before reading this file.** I had flagged "Vendor and Supplier used
interchangeably" and *"Office / branch"* as hedging. **Both were wrong** — see
§3 and §5.

---

## 2. How terminology works in this product

Three layers, which is why label counts alone cannot tell inconsistency from
design:

| Layer | What it does |
|---|---|
| **The canonical vocabulary** | 33 terms in `lib/terms.php`, each with singular, plural, group and definition |
| **Industry presets** | **Six industries**, each overriding selected terms. `general` · `inspection` · `manufacturing` · `trading` · `epc` · `fieldservice` |
| **The customer's own wording** | `/terminology` (64 fields), with **"Reset to standard wording"** |

Screens render terms through helpers — `T()`, `Tl()`, `TP()`, `Tlp()`, `TH()`,
**`T_REG()`** (the term + " register"), **`T_NEW()`** ("New " + the term) — so a
rename reaches every screen at once.

### What the industry presets change

The same screen says different things depending on the customer's industry:

| Canonical | inspection | manufacturing | trading | epc | fieldservice |
|---|---|---|---|---|---|
| **Work Order** | Inspection Call | — | — | — | — |
| **Job** | Deputation | — | — | — | — |
| **Team Member** | Inspection Engineer | **Operator** | **Executive** | **Site Engineer** | **Technician** |
| **Report** | Inspection Report | — | — | — | — |
| **Supplier Document** | Manufacturer Record | — | — | — | — |
| **Person-day** | Man-day | — | — | — | — |

**This is why the product can be sold as four packs** (TPIA, Technical
Staffing, Recruitment, Recruitment In-house HR — `workflows/10-ADMIN.md` §2).
The same software speaks the customer's language.

**It also explains a heading I questioned.** `/profitability` renders
**"Contract number register"** because its label is `T_REG('boss')`, and `boss`
is defined as *"The client contract / order number that profitability is tracked
against."* The heading is correct and terminology-driven; the tile's
*description* ("Profit and margin by job") describes the purpose. Not a defect.

---

## 3. The canonical vocabulary

Definitions are quoted from `lib/terms.php` — they are the business's own words,
not mine.

### Parties — and these five are **not** synonyms

This is the group I got wrong in D4, and the distinction is the heart of
third-party inspection:

| Term | On screen | Definition |
|---|---|---|
| `client` | **Client** | *"The party that engages us and gets what we produce."* |
| `vendor` | **Vendor** | *"The party whose goods or works the job concerns."* |
| `manufacturer` | **Manufacturer** | *"The works that actually makes the item."* |
| `supplier` | **Supplier** | *"The party that supplies the item to the client."* |
| `subvendor` | **Sub-vendor** | *"The vendor's own subcontractor."* |

**Read those together and the business model appears.** A client hires you to
inspect goods that a *vendor* is producing, which a *manufacturer* actually
makes, which a *supplier* will deliver to the client, and the vendor may have
its own *sub-vendor* doing part of it. **Five different companies, five
different relationships to the same inspection.**

**So "Vendor" and "Supplier" are deliberately different words for deliberately
different parties, and my D4 finding that they were used interchangeably was
wrong.** The 17 vendor-labels and 3 supplier-labels measured across the screens
are not synonym drift; they are two concepts being named correctly.

### Sales

| Term | On screen | Definition |
|---|---|---|
| `inquiry` | **Inquiry** | *"An enquiry received from a client."* |
| `quote` | **Quote** | *"Our commercial offer against an inquiry."* |
| `order` | **Sales Order** | *"A won quote, registered as an order."* |

### Operations

| Term | On screen | Definition |
|---|---|---|
| `call` | **Work Order** *(Inspection Call)* | *"A piece of work the customer has asked for, with its dates and location."* |
| `job` | **Job** *(Deputation)* | *"One person put on one work order, for particular dates."* |
| `engineer` | **Team Member** *(Inspection Engineer / Operator / Executive / Site Engineer / Technician)* | *"The person who carries out the work."* |
| `inspector` | **Inspector** | *"A workforce member whose team role is Field — the ones who can be sent to site. **Every inspector is workforce; not every workforce member is an inspector.**"* |
| `office` | **Office** | *"**A branch or head office** of this company."* |
| `sbu` | **Business Unit** | *"The line of business a job belongs to — what the branch is judged by."* |
| `manday` | **Person-day** *(Man-day)* | *"One person for one working day."* |

**`job` = "one person put on one work order"** is the sentence that explains the
whole allocation model: a work order with four people on it is four jobs. Every
capacity, utilisation and profit figure in the product follows from that one
definition.

### Reporting

| Term | On screen | Definition |
|---|---|---|
| `report` | **Report** *(Inspection Report)* | *"A document we issue against a job and send to the client."* |
| `endorsement` | **Endorsement** | *"Our review and sign-off of somebody else's record."* |
| `mfr_record` | **Supplier Document** *(Manufacturer Record)* | *"A document produced by the works rather than by us — test certificate, mill certificate, calibration record."* |
| `qa` | **QA** | *"The review a report goes through before it is issued. **QA is a stage in a report's life, not a separate document.**"* |

**The `report` / `endorsement` / `mfr_record` distinction is precise and
commercially important:** we *issue* a report; we *endorse* someone else's
record; a supplier document is theirs, not ours. Confusing the three would be a
liability question.

### Money

| Term | On screen | Definition |
|---|---|---|
| `boss` | **Contract Number** | *"The client contract / order number that profitability is tracked against. It is not typed on a deputation — it comes down from the quotation and the inspection call, and the register fills itself."* |
| `invoice` | **Invoice** | *"A bill raised on the client."* |
| `voucher` | **Voucher** | *"The monthly statement of travelling expenses."* |
| `billing_readiness` | **Billing readiness** | *"A check that everything needed to bill is present and agreed. **It is not an invoice and raises no money** — it is what tells you an invoice can safely be raised."* |

### People and Recruitment

| Term | On screen | Definition |
|---|---|---|
| `user` | **User** | *"A login account."* |
| `workforce` | **Workforce** | *"Everybody employed or engaged by this company. A workforce record is created when somebody is hired, whatever job they do."* |
| `candidate` | **Candidate** | *"A person being considered for hiring."* |
| `professional` | **Professional** | *"Somebody who lists themselves on the marketplace. **A professional is not this company's staff until they are hired**, which is what makes them workforce."* |
| `hiring_request` | **Hiring Request** | *"A request for headcount, raised before recruiting starts so it can be approved. **Approving it is what allows a Requisition to be raised against it.**"* |
| `requisition` | **Requisition** | *"Approved demand for a new position — the role you are hiring for."* |
| `offer` | **Offer** | *"The job offer made to a selected candidate."* |
| `placement` | **Placement** | *"A candidate successfully hired and placed."* |
| `recruiter` | **Recruiter** | *"The person who runs the hiring for a requisition."* |

**Four person-words, carefully separated:** a **user** is a login, a
**workforce** member is engaged by the company, an **inspector** is a workforce
member who goes to site, and a **professional** is on the marketplace and is
*not* yours until hired. **Most systems confuse at least two of those.**

---

## 4. The ambiguity policy — already written, and worth promoting

`lib/hiringreq.php` carries this:

```php
const HREQ_TERMS = [
    'request'     => ['Hiring Request', 'Hiring Requests'],
    'requisition' => ['Recruitment Requisition', 'Recruitment Requisitions'],
    'marketplace' => ['Marketplace Requirement', 'Marketplace Requirements'],
];
// The words that are ambiguous on their own and must always carry a qualifier.
const HREQ_AMBIGUOUS = ['requirement', 'requirements'];
```

**"Requirement" is declared ambiguous and must always carry a qualifier.** That
is exactly right: in this product a "requirement" could be a hiring request, a
recruitment requisition, a marketplace posting, or a skill requirement on a
hiring request.

**Recommendation:** this policy currently governs one module. **It should be the
pattern for the whole product**, and there are two more words that need it:

| Ambiguous word | What it could mean |
|---|---|
| **"Report"** | an inspection report · a dashboard · an expediting report · a deputation report · a management report |
| **"Document"** | an inspection report · a supplier document · a candidate document · a controlled document · a template |

Both appear in several areas meaning different things, and neither carries a
qualifier rule.

---

## 5. Drift — where the screens disagree with the vocabulary

Measured against the 1,441 distinct field labels and 456 button wordings.

### D5-1 — "Customer" is not in the vocabulary (P2)

`client` is defined. **`customer` is not** — yet it appears in **9 distinct
labels** and as a column heading on three money screens (`/invoices`,
`/receipts`, `/activities` all show **Customer**).

The definition of `call` even uses it: *"A piece of work **the customer** has
asked for."*

**So the product's own canonical definition uses a word the canonical vocabulary
does not contain.** Either add `customer` as a term (perhaps as the money-facing
name for a client), or change those labels to Client. **This one is real
drift**, and it is the most visible: it is on the invoice and receipt registers.

### D5-2 — `Business Unit` vs `Business unit` (P3)

The canonical term is **Business Unit**. Both capitalisations appear across the
measured labels, among 6 distinct label forms. A one-line fix, and the kind of
thing a customer notices on a screenshot.

### D5-3 — "Office / branch" is **correct**, not hedging (withdrawn)

In D4 I flagged the label *"Office / branch"* as hedging between two words.
**The vocabulary defines `office` as "A branch or head office of this
company"** — so office *includes* branch by definition, and a label that says
"Office / branch" is helping the reader, not dithering. **Withdrawn.**

### D5-4 — Vendor / Supplier / Manufacturer are **correctly distinct** (withdrawn)

See §3. **Withdrawn**, and it was the more serious of my two errors: acting on it
would have collapsed five defined parties into one and broken the business
model.

### D5-5 — "Inquiry" vs "Enquiry" (P3)

The term is `Inquiry` (American spelling) and the definition reads *"An
**enquiry** received from a client"* (British spelling). Screens show
**Inquiries**, **Inquiry register** and **New client inquiry**; the status
constant is `INQUIRY_STATUS`. Internally consistent on screen; the definition
itself mixes the two. Trivial.

### D5-6 — Four names for free text (P3)

`remarks` · `review_remarks` · `notes` · `exp_notes` — no stated distinction.
Not a vocabulary term, so nothing governs it.

### D5-7 — Terms used on screens that the vocabulary does not define (P2)

Words carrying real business meaning, appearing on screens, with **no canonical
definition**:

| Word | Where | Why it needs defining |
|---|---|---|
| **Nonconformity / NCR** | the whole Quality area | The central quality record, and `03-WORKFLOW-CATALOGUE.md` shows it has no lifecycle section either |
| **Corrective action / CAPA** | Quality | Same |
| **Departure / Concession / Waiver** | `/departures` | Three words on one screen for what may be one thing or three |
| **Deputation** | Operations | 31 states and 13 tables. It is an *industry preset* for `job`, but the deputation business line is bigger than that |
| **Billable event** | Money | Has a lifecycle; no term definition |
| **Expediting** | Reporting | A whole register |
| **Engagement** | Marketplace | A distinct record with its own lifecycle |

**That list overlaps almost exactly with the areas missing lifecycle
documentation** (Quality, deputation, the sales funnel top). The same areas are
undefined *and* unspecified — which is consistent, and tells you where the
documentation effort should go.

---

## 6. The one-page glossary, for the business

The words, in the order the work happens. Use this as the shared vocabulary.

| # | Word | Means |
|---|---|---|
| 1 | **Lead** | A company worth pursuing, before any specific job |
| 2 | **Opportunity** | A live deal you are working to win or lose |
| 3 | **Inquiry** | A specific request from a client to quote something |
| 4 | **Quote** | Our commercial offer against an inquiry |
| 5 | **Sales Order** | A won quote, registered |
| 6 | **Contract Number** | The client's contract/order number that profit is tracked against. It arrives from the quote and the work order — nobody types it |
| 7 | **Work Order** *(Inspection Call)* | A piece of work the customer has asked for, with its dates and location |
| 8 | **Job** *(Deputation)* | **One person put on one work order, for particular dates.** Four people on a work order is four jobs |
| 9 | **Team Member** *(Inspection Engineer)* | The person who carries out the work |
| 10 | **Inspector** | A workforce member whose team role is Field. Every inspector is workforce; not every workforce member is an inspector |
| 11 | **Office** | A branch or head office of this company |
| 12 | **Business Unit** | The line of business a job belongs to — what the branch is judged by |
| 13 | **Person-day** *(Man-day)* | One person for one working day |
| 14 | **Client** | The party that engages us and gets what we produce |
| 15 | **Vendor** | The party whose goods or works the job concerns |
| 16 | **Manufacturer** | The works that actually makes the item |
| 17 | **Supplier** | The party that supplies the item to the client |
| 18 | **Sub-vendor** | The vendor's own subcontractor |
| 19 | **Report** *(Inspection Report)* | A document **we** issue against a job and send to the client |
| 20 | **QA** | The review a report goes through before issue. **A stage, not a document** |
| 21 | **Endorsement** | Our review and sign-off of **somebody else's** record |
| 22 | **Supplier Document** *(Manufacturer Record)* | A document produced by the works, not by us — test certificate, mill certificate, calibration record |
| 23 | **Billing readiness** | A check that everything needed to bill is present. **Not an invoice; raises no money** |
| 24 | **Invoice** | A bill raised on the client |
| 25 | **Voucher** | The monthly statement of travelling expenses |
| 26 | **User** | A login account |
| 27 | **Workforce** | Everybody employed or engaged by this company |
| 28 | **Candidate** | A person being considered for hiring |
| 29 | **Professional** | Somebody who lists themselves on the marketplace. **Not our staff until hired** |
| 30 | **Hiring Request** | A request for headcount, approved before recruiting starts. Approving it allows a Requisition |
| 31 | **Requisition** | Approved demand for a position — the role you are hiring for |
| 32 | **Offer** | The job offer made to a selected candidate |
| 33 | **Placement** | A candidate successfully hired and placed |
| 34 | **Recruiter** | The person who runs the hiring for a requisition |

**Never use bare "requirement"** — say Hiring Request, Recruitment Requisition,
or Marketplace Requirement. *(The product already enforces this in one module;
it should everywhere.)*

---

## 7. Findings

| Id | Severity | Finding |
|---|---|---|
| **D5-1** | **P2** | **"Customer" is not in the canonical vocabulary**, yet it is a column heading on the invoice, receipt and activity registers, and the definition of `call` uses the word. Add it as a term or change the labels |
| **D5-7** | **P2** | **Seven business words with real meaning have no definition**: Nonconformity/NCR, Corrective action/CAPA, Departure/Concession/Waiver, Deputation, Billable event, Expediting, Engagement. The same areas also lack lifecycle documentation |
| **D5-8** | **P2** | **The ambiguity policy governs one module only.** `HREQ_AMBIGUOUS` correctly forbids bare "requirement" in Recruitment. **"Report" and "Document" need the same rule** across the product |
| **D5-2** | **P3** | `Business Unit` vs `Business unit` |
| **D5-5** | **P3** | The `inquiry` definition mixes American and British spellings |
| **D5-6** | **P3** | `remarks` · `review_remarks` · `notes` · `exp_notes` — four names for free text, no stated distinction |
| **D5-3** | — | **WITHDRAWN** — *"Office / branch"* is accurate, because `office` is defined as "a branch or head office" |
| **D5-4** | — | **WITHDRAWN, and the more serious of my two errors** — Vendor, Supplier, Manufacturer and Sub-vendor are **four defined parties**, not synonyms. Acting on my D4 finding would have collapsed the business model |
| **D5-9** | — | *Credit:* a **33-term vocabulary with plain-English definitions in code**, rendered through helpers so a rename reaches every screen · **six industry presets** so the same software speaks the customer's language · a **customer-editable** `/terminology` screen with **Reset to standard wording** · an **explicit ambiguity policy** · and definitions that draw the distinctions that matter: *job* = one person on one work order · *QA* is a stage not a document · *billing readiness* raises no money · a *professional* is not staff until hired · every inspector is workforce but not vice versa |

---

## 8. A note on method, since I got two findings wrong

My first pass counted labels and inferred inconsistency from variety:
19 "office" labels, 17 "vendor", 20 "client" — it looked like drift.

**It was mostly a vocabulary doing its job.** Variety in labels is what you get
when five different parties each have a correct name, when six industries
rename the same term, and when the customer can edit the words.

**Label counts cannot distinguish inconsistency from design. Only the
vocabulary can** — which is why this document exists and why the canonical list
should be published to the business rather than left in a PHP file.

The corrections are kept in place rather than edited away, and
`04-SCREEN-AND-FIELD-AUDIT.md` §8 now points here.
