# D4 — Screen, field and usability audit

Every screen that renders, every field on it, every control, measured.

**Evidence:** `evidence/screen-field-harness.py` (the measurement),
`evidence/screens.json` (full detail), `evidence/screens.tsv` (one row per
screen), `evidence/screen-analysis.txt` (the findings below),
`evidence/entry-form-feedback.json` (what each creation form does with an empty
submit), `evidence/record-screens.json` (the detail screens that need a `?id=`).

Per-area narrative, with scenarios and edge cases, is in `workflows/`.

---

## 1. The inventory

| | Count |
|---|---|
| Screens that render | **256** |
| …with a heading | 255 |
| …with a breadcrumb | 251 |
| …with at least one field of their own | 254 |
| **Visible form fields** | **3,886** |
| …marked `required` | **366** (9.4%) |
| Buttons and button-styled links | **4,919** |
| …**distinct wordings** | **456** |
| **Distinct field labels** | **1,441** |
| Tables | 463 |

Two things are excluded from every count, because they are the page frame
rather than the screen: the eight chrome controls repeated on all 256 screens
(`✕ « ★ ☰ ← 🔍` and `🧭 Go to…⌘K`), and the field `q`, which is the rail's
"Find a screen…" box.

**Stated limitation:** the test database holds almost no operational data
(0 work orders, 0 jobs, 665 business partners). This audit therefore measures
**structure** — what a screen asks and offers — not **behaviour at volume**.
Pagination, long lists and sorting with 10,000 rows are untested and are listed
in `09-UAT-PLAN.md`.

---

## 2. THE HEADLINE — four forms create a numbered record from an empty submit

Eight creation forms were submitted **completely empty** (CSRF token only)
against a throwaway database, and the row count of their target table compared
before and after.

| Form | Empty submit | What the person is told |
|---|---|---|
| **`/call-new`** | **CREATES `CALL-00001`** | *"CALL-00001 created. Now allocate a job."* |
| **`/candidate-new`** | **CREATES `CV-00001`** | *"CV-00001 added to the hiring pipeline."* |
| **`/incident-new`** | **CREATES `INC-2026-001`** | *"Incident INC-2026-001 recorded. **The six-hour clock started**…"* |
| **`/endorsement-new`** | **CREATES `MGH/AHM/2026/GEN/END/000001`** | *"Endorsement created… Upload the…"* |
| `/partner-new` | refused | *"Select at least one role (Client / Vendor / Both)."* |
| `/user-new` | refused | *"A login must belong to a team member. Pick the person from the team, or enter their name…"* |
| `/internal-audit-new` | refused | *"Name the auditor. An audit with no auditor on it is not evidence."* |
| `/hiring-request` | refused | **nothing at all** |

### What actually landed

`CALL-00001`: **8 of 71 columns filled, every one a system default** —
`call_code`, `ibo_office_id`, `status` = **OPEN**, `created_by`, `created_at`,
`billable_qty`, `engagement_type`. No client, no vendor, no site, no service,
no date.

`CV-00001`: **5 of 64 columns.** Opening it shows the heading
**"(no name) CV received"** — the application displays the ghost gracefully,
which is good, but it is in the hiring pipeline.

### Why this is P1 rather than a tidy-up

**Each of the four consumes a number from a business sequence.** `CALL-00001`
exists, so the next real work order is `CALL-00002`.
`MGH/AHM/2026/GEN/END/000001` is a formal, office-coded certificate reference.
For an inspection body under ISO/IEC 17020, a ghost in a work-order or
certificate series is something an assessor asks about, and "somebody pressed
Save on an empty form" is not a good answer.

**`/incident-new` is the worst of the four:** it starts a **CERT-In six-hour
notification clock** on a record with nothing in it.

### And the pattern is not carelessness

Three forms refuse with messages better than most software manages — one of them
explains the *business* reason rather than the technical rule:

> *"Name the auditor. An audit with no auditor on it is not evidence."*

So the knowledge exists in the codebase. **Four screens were missed.** That is
a bounded, cheap fix: `/call-new`, `/candidate-new`, `/incident-new`,
`/endorsement-new`.

### A nuance that matters for the fix

| Form | Fields marked `required` | Server enforces? |
|---|---|---|
| `/call-new` | **0** of 139 | no |
| `/candidate-new` | **2** of 28 | **no** |
| `/incident-new` | **2** of 14 | **no** |
| `/endorsement-new` | 1 of 21 | no |

**Three of the four already carry `required` attributes and the server ignores
them.** So adding `required` in the HTML is not the fix — the browser hint can
be bypassed by any client. **The check has to be server-side**, which is where
the three good refusals do theirs.

### One silent refusal

**`/hiring-request`** refuses an empty submit and says **nothing**: the page
re-renders identically with no message and no inline error. The person presses
**Create request** and nothing appears to happen. **This is the "nothing
happens" the owner has reported.** P2.

---

## 3. Validation across the whole application

| | Count |
|---|---|
| Screens with more than 3 fields and **zero** marked required | **69** |
| Real entry/edit forms (creating or editing one record) | **25** |
| …marking **one field or fewer** required | **17** |
| …marking **zero** | 4 |

**But 69 is a misleading number, and saying so matters.** Most of those screens
are **configuration grids**, where "required" is meaningless:

| Screen | Fields | Why zero required is correct |
|---|---|---|
| `/access` | 200 | a grid of permission checkboxes |
| `/settings` | 183 | company-wide toggles |
| `/role-workspaces` | 153 | per-role landing pages |
| `/duplicates` | 119 | one `keep`/`reason` pair per candidate pair |
| `/comp-setup` | 74 | salary components in a repeating grid |
| `/terminology` | 64 | rename-anything boxes |

**The honest finding is about the 25 real entry forms**, not the 69:

| Form | Fields | Required |
|---|---|---|
| `/user-new` | 258 | 1 |
| `/call-new` | 139 | 1 |
| `/quote-new` | 96 | 1 |
| `/quote-external` | 72 | 2 |
| `/partner-new` | 64 | 1 |
| `/hiring-request` | 30 | 3 |
| `/candidate-new` | 28 | 2 |
| `/internal-audit-new` | 26 | 1 |
| `/inquiry-new` | 13 | **0** |
| `/invoice-new` | 8 | 2 |
| `/receipt-new` | 10 | 2 |

**`/quote-new` is the one to test next.** 96 fields, one required, and it was
*not* in the empty-submit test because its target table could not be identified
automatically (there is no `quotes` table — the header table is `quotations`).
Given that four comparable forms create ghost records, **a quote is the highest
remaining risk**, because it is a commercial document the business sends to a
customer. Listed as the first item in `09-UAT-PLAN.md`.

**Where validation is done well:** `/vouchers` requires **both** its fields
(`ins`, `month`) — a voucher is per inspector per month, so neither can be
guessed. `/invoice-new` requires `partner_id` and `office_id` — you cannot
invoice nobody, and the office decides the numbering series.
`/data-control` requires **four**.

---

## 4. Form length

| | Count |
|---|---|
| Screens with more than 25 visible fields | **25** |

The ten largest:

| Screen | Fields | Required | What it is |
|---|---|---|---|
| `/connect-qualifications` | **619** | 234 | Qualification & role taxonomy, five tabs |
| `/user-new` | 259 | 1 | Add a person — includes the permission grid |
| `/recruit-pipelines` | 206 | 21 | Hiring workflows, with stages |
| `/access` | 201 | 0 | Roles & permissions grid |
| `/settings` | 184 | 0 | System settings |
| `/role-workspaces` | 154 | 0 | Per-role landing pages |
| `/call-new` | 140 | 1 | New work order |
| `/duplicates` | 121 | 0 | Possible duplicates |
| `/quote-new` | 97 | 1 | New quote |
| `/comp-setup` | 75 | 0 | Compensation setup |

**Three of these are genuine form fatigue**, because a person fills them in one
sitting to create one record:

- **`/call-new`** — 140 fields for one work order.
- **`/quote-new`** — 97 fields for one quote, with repeating site and line
  blocks.
- **`/connect-qualifications`** — 619 fields and five tabs on one page. Each tab
  should be its own screen.

The rest are grids, where the count reflects how much is configurable rather
than how much a person types.

**`/user-new` at 259 is a special case:** the per-user permission grid is
embedded in the page, which is why eight 14-column tables appear on a form
whose actual job is to add a person.

---

## 5. Mobile — where a phone pays

### Page weight

| Screen | Size |
|---|---|
| `/connect-qualifications` | **549 KB** |
| `/connect-taxonomy-admin` | 269 KB |
| `/vendor-profile-save` | 242 KB |
| `/duplicates` | 211 KB |
| `/user-new` | 196 KB |
| `/quote-new` | 172 KB |
| `/lookups` | 164 KB |
| `/call-new` | 160 KB |
| `/access` | 158 KB |

**A 549 KB page is a problem on a site with patchy mobile data**, and
`/connect-qualifications` is the clearest case for splitting its five tabs.

**But none of these nine is a field screen.** They are configuration and
admin screens, used at a desk. CLAUDE.md's rule — *inspectors are phone-first
in the field; coordinators, managers and finance are desk-first* — means the
weight is in the right place. **The phone-first screens (`/my-jobs`,
`/my-voucher`, site check-in, `/my-interviews`) are not in this list at all.**

### Wide tables

20 tables carry more than 12 columns, but that number needs correcting:
**18 of the 20 are the same permission grid** (Module · View · Add · Edit ·
Archive · Delete · Approve …) repeated once per module group on `/access` and
`/user-new`.

**Only two are genuinely wide data tables:**

| Screen | Columns | Verdict |
|---|---|---|
| `/call-profit` — Profit by work order | **16** | Inherent to a profit breakdown. Desk-first users |
| `/schedule` — Scheduling board | **15** | A week grid: people down, days across. Inherent |

**So the "tables too wide for a phone" finding largely dissolves on
inspection** — which is worth saying, because the raw count of 20 looks alarming
and the reality is two inherent cases plus one repeated grid.

### What was not measured

A systematic **390px pass** was done in an earlier milestone (R-19) and **was
not repeated here**. This audit measured column counts and page weight, which
are proxies. Real small-screen usability needs a browser at 390px, and that is
in `09-UAT-PLAN.md`.

---

## 6. Screens missing their furniture

| Issue | Screens |
|---|---|
| **No heading** | **`/connect-concierge`** — 1 of 256 |
| **No breadcrumb** | `/complaints-policy`, `/connect`, `/get-started`, `/join`, `/verify` — 5 of 256 |

**`/connect-concierge` is a finding.** It is the guided requirement-builder
wizard in Marketplace, the breadcrumb is derived centrally for every screen, and
a wizard with no title leaves the person nothing to orient by. One-line fix.

**The five without breadcrumbs are correct.** `/connect`, `/join` and `/verify`
are public pages where the person is not inside the application yet;
`/get-started` and `/complaints-policy` are standalone. **251 of 256 having a
derived, consistent breadcrumb is a genuinely good result** — it was 128 screens
with none before the central navigation model was built.

---

## 7. Terminology — the same act, said differently

**456 distinct button wordings across 4,919 buttons.** Clustered by what they do:

### Saving — 46 different wordings

`"Save changes"` **(151)** · `"Save"` **(78)** · `"Apply"` (13) ·
`"Save pricing & keys"` (4) · `"Apply the key"` (4) · `"Save provider key"` (4) ·
`"Save connection"` (3) · `"Save settings"` (3) · `"Save prices"` (2) ·
`"Save letterhead"` (2) · `"Save signature"` (2) …

**Two dominant forms for one act** — "Save changes" (151) and "Save" (78). The
qualified ones ("Save letterhead", "Save October 2026") are *good*, because they
name what will be written. The bare split between "Save" and "Save changes" is
the inconsistency.

### Creating — 49 different wordings

`"Record it"` (7) · `"Add rule"` (4) · `"Add template"` (4) ·
`"Create form & add fields →"` (3) · `"Add"` (3) · `"Record"` (3) ·
`"Add & select"` (2) · `"Add phrase"` (2) · `"Create the invitation"` (2) …

**Four verbs for one act** — Add, Create, Record, Start. Many are well chosen
for their context ("Record it" for an incident, "Create the invitation" for a
portal user, "Start the draft" for an invoice). Some are bare.

### Destroying — 5 wordings

`"Delete"` **(304)** · `"Remove"` **(90)** · `"Clear"` (4) ·
`"Remove demo data"` (2) · `"Clear all"` (1)

**Delete and Remove are used 304 and 90 times for what may be two different
acts or one.** If Remove means "take out of this list" and Delete means
"destroy the record", that distinction is worth keeping and documenting. If they
are interchangeable, pick one. **Right now a person cannot tell which is which,
and one of them is irreversible.**

### Cancelling — 8 wordings

`"Cancel"` (20) · `"Back to dashboard"` (3) · `"Cancel changes"` (1) ·
`"Back to Quotes"` (1) · `"Back to service scope"` (1) · `"Back to settings"` (1)

Consistent enough, and the "Back to X" forms name their destination, which is
right.

---

## 8. Field labels — 1,441 of them

How often each business word appears as a label, and how many *different* labels
use it:

| Word | Uses | Distinct labels | Examples |
|---|---|---|---|
| **report** | 69 | **60** | Report type · Report type (blank = any) · Final Inspection Report … |
| **job** | 57 | 5 | Job family · Job title · Legacy job field (pre-switch) |
| **client** | 34 | **20** | Client · Client + Add new · Add to both Client & Vendor |
| **office** | 31 | **19** | Office · Office (blank = any) · Overheads (office finance) |
| **vendor** | 26 | **17** | Vendor Inspection · Vendor Surveillance · Vendor assessment |
| **business unit** | 18 | 6 | Business Unit · **Business unit** · Business Unit (primary) |
| **document** | 18 | 13 | Document number · Document review |
| **contract** | 16 | 12 | Contract Staffing · Contract Recruitment |
| **customer** | 14 | 9 | Customer · TDS ledger (tax the customer withheld) |
| **inspector** | 12 | 7 | Inspector · Freelance Inspector Supply |
| **branch** | 10 | 4 | Branch · Office / branch · Branch Manager |
| **supplier** | 3 | 3 | Supplier Prequalification · Vendor / Supplier Audit |

### What I first concluded from that table, and what survived

I read the variety as inconsistency and raised four findings. **Then I read
`phpapp/lib/terms.php`, which holds a canonical 33-term vocabulary with
plain-English definitions, and two of the four did not survive.**
`05-BUSINESS-GLOSSARY.md` has the full vocabulary; the corrections are here.

| First conclusion | After reading the vocabulary |
|---|---|
| ~~Vendor (17 labels) and Supplier (3) are the same concept~~ | **WRONG, and the serious one.** They are **two of five separately defined parties**: a *client* engages us · a *vendor*'s goods the job concerns · a *manufacturer* makes the item · a *supplier* delivers it to the client · a *sub-vendor* is the vendor's own subcontractor. **Acting on my finding would have collapsed the business model of third-party inspection.** Withdrawn |
| ~~*"Office / branch"* hedges between two words~~ | **WRONG.** `office` is defined as *"A branch or head office of this company"* — so the label is helping the reader. Withdrawn |
| **Client (20) and Customer (9)** are used for one concept | **STANDS, and it is the most visible drift.** `client` is a defined term; **`customer` is not in the vocabulary at all** — yet it is the column heading on `/invoices`, `/receipts` and `/activities`, and the definition of `call` itself says *"the customer has asked for"*. See D5-1 |
| **`Business Unit` vs `Business unit`** | **STANDS.** The canonical term is **Business Unit**; the lower-case form is drift. See D5-2 |
| "report" has 60 distinct labels | **Mostly fine** — a *report type* and a *Final Inspection Report* are different things. But **"Report" and "Document" are each used across several areas meaning different things**, and the product already has an ambiguity rule for exactly this problem in one module (`HREQ_AMBIGUOUS` forbids bare "requirement"). It should cover these two. See D5-8 |

**The lesson, and it is why `05-BUSINESS-GLOSSARY.md` exists:** label counts
cannot distinguish inconsistency from design. Variety is what you get when five
parties each have a correct name, when **six industry presets** rename the same
term, and when the customer can edit the words at `/terminology`. **Only the
canonical vocabulary can tell them apart.**

---

## 9. Field names — the same data under different names

| Concept | Names in use |
|---|---|
| **office** | `office_id`, `executing_office_id`, `home_office_id`, `ibo_office_id` |
| **a user** | `approver_user_id`, `delegate_user_id`, `delegator_user_id`, `signatory_user_id`, `temp_user_id` |
| **a date** | `call_received_date`, `cv_received_date`, `end_date`, `inspection_date`, `inspection_required_date`, `invoice_date`, `issue_date`, `receipt_date`, `date_format`, `date_guard` |
| **notes** | `remarks`, `review_remarks`, `notes`, `exp_notes` |
| **an inspector** | `inspector_id`, `replacing_inspector_id` |

**Most of this is correct and should not be "fixed".**
`executing_office_id` vs `office_id` vs `ibo_office_id` is the cross-office
model being explicit; `delegator_user_id` vs `delegate_user_id` is a delegation
having two ends. Collapsing them would lose meaning.

**The one worth a look is `remarks` vs `notes`** — four names for free text with
no obvious distinction.

---

## 10. Record screens — the ones that need a record to exist

The main harness could not reach these: a detail screen with no record answers
with a redirect, so it never appeared as a screen. A second harness
(`evidence/record-screen-harness.py`) inventoried them against records created
during the empty-submit test.

| Screen | Heading | Fields | Required | Controls |
|---|---|---|---|---|
| `/call?id=` | **Work order CALL-00001** | 13 | **4** | **15** |
| `/candidate?id=` | **(no name) CV received** | 106 | 2 | **38** |
| `/hiring-request?id=` | HRQ-TEST-0001 Approved | 32 | 3 | 6 |
| `/partner?id=` | 2MM BETON | 0 | 0 | 2 |

### Two findings from this

**1. The record screen is stricter than the creation screen.** `/call?id=`
marks **4 fields required**; `/call-new` marks **1**. So the system insists on
more when you *edit* a work order than when you *create* one. **The
requirements exist — they are simply not applied at the point of entry**, which
is the same conclusion as §2 reached from the other direction.

**2. `/candidate?id=` carries 106 fields and 38 controls** — a numbered
15-stage pipeline (Organogram Verification → CV Screening → HOD Shortlisting →
L1 Interview → Document Collection → Salary Structure → HR Discussion → Salary
Acceptance → Reference Verification → One-Pager Approval → Offer Letter → Offer
Acceptance → Onboarding) plus **Advance →**, keyword analysis, tracking and
notes. Defensible — a candidate genuinely has that much state — but not usable
on a phone, and hard for a new recruiter to navigate.

### Not inventoried, and this is the gap

**`/job?id=`, `/voucher?id=`, `/quote?id=`, `/invoice?id=`, `/document?id=`,
`/ncr?id=`** — no such records exist in the test database, and creating a job
needs a linked inspector and trade the test data does not have.

**`/job?id=` is the single highest-value remaining measurement in this audit.**
The job is where the work actually happens.

---

## 11. Concurrency — two people, one record

```php
function stale_edit_block($table, $id, $postedVersion) {
    if (!in_array($table, ['calls', 'jobs'], true)) return '';   // allow-list
    ...
    return 'Someone else saved changes to this record while you had it open. '
         . '... so nothing of theirs was overwritten.';
}
```

A `row_version` token is stamped on every save; a save carrying a stale token is
**refused**, with a message that tells the person plainly that nothing of the
other person's work was lost.

**The protection covers two record types out of roughly twenty.** The allow-list
is `['calls', 'jobs']`, and the restriction is deliberate ("never interpolate
arbitrary names" — a sound reason).

**Everything else has no concurrency control: last save wins, silently.**
Quotations · invoices · receipts · **business partners** · hiring requests ·
candidates · reports · nonconformities.

**`business_partners` is the worst place for this**, because every other area
points at that record (`workflows/09-DIRECTORY.md` D-F2).

*Recommendation: extend the allow-list record type by record type, starting with
`quotations` and `invoices`, since those carry money.*

---

## 12. Findings

| Id | Severity | Finding |
|---|---|---|
| **S4-1** | **P1** | **Four creation forms create a numbered record from an empty submit** — `/call-new`, `/candidate-new`, `/incident-new`, `/endorsement-new`. Each consumes a business sequence number; the incident also starts a six-hour regulatory clock. **Three of the four already mark fields `required` and the server ignores it**, so the fix must be server-side |
| **S4-2** | **P1** | **`/quote-new` is untested** — 96 fields, 1 required, and the only comparable form not covered by the empty-submit test. A quote is a document sent to customers |
| **S4-3** | **P2** | **`/hiring-request` refuses silently** — the page re-renders identically with no message. This is the "nothing happens" that has been reported |
| **S4-4** | **P2** | **No concurrency control outside `calls` and `jobs`** — including `business_partners`, the record every area depends on |
| **S4-5** | **P2** | **`Delete` (304 uses) and `Remove` (90 uses)** may be two acts or one; a person cannot tell which, and one is irreversible |
| **S4-6** | **P2** | **Three genuine form-fatigue screens**: `/connect-qualifications` (619 fields, five tabs, **549 KB**), `/call-new` (140), `/quote-new` (97) |
| **S4-7** | **P2** | **`/connect-concierge` has no heading** — the only screen of 256 without one, and it is a wizard |
| **S4-8** | **P2** | **"Customer" is not in the canonical vocabulary** yet heads a column on the invoice, receipt and activity registers (D5-1). Plus **`Business Unit` vs `Business unit`** (D5-2, P3). *The Vendor/Supplier and Office/Branch halves of this finding were withdrawn after reading the vocabulary — see §8* |
| **S4-9** | **P3** | **"Save" (78) vs "Save changes" (151)** — two bare forms for one act. The *qualified* ones ("Save October 2026") are good and should stay |
| **S4-10** | **P3** | `remarks` · `review_remarks` · `notes` · `exp_notes` — four names for free text |
| **S4-11** | — | **A finding that dissolved on inspection:** "20 tables too wide for a phone" is really **two** inherent cases (`/call-profit` 16 columns, `/schedule` 15) plus the same permission grid repeated 18 times. Recorded because the raw count looks alarming and the reality is not |
| **S4-12** | — | *Credit:* **251 of 256 screens carry a derived, consistent breadcrumb** (it was 128 with none before the navigation model) · page weight sits on **desk screens, not the inspector's phone**, as CLAUDE.md requires · the stale-edit message tells the person nothing was lost · three refusal messages explain the *business* reason, one of them exemplary: *"Name the auditor. An audit with no auditor on it is not evidence."* · save buttons that **name what they will write** ("Save October 2026", "Save access for Coordinator", "Save letterhead") · **Undo** on the permission grid · and column headings written for people: *"What was wrong"*, *"Where it came from"*, *"Last anything"*, *"Left to match"*, *"What to do"* |

---

## 13. What this audit did not measure

1. **Behaviour at volume.** 0 work orders, 0 jobs in the test data. No screen
   was exercised with realistic row counts.
2. **Real small-screen usability.** Column counts and page weight are proxies;
   a 390px browser pass is needed.
3. **The job, voucher, quote, invoice, report and nonconformity record
   screens** — no such records existed to open.
4. **Destructive controls.** **Delete call**, **Restore**, **Upload &
   restore**, **Merge them**, **Trim what is past retention** were all
   inventoried and **none was fired**. What each cascades to is unknown, and
   three of them are named as priority tests in `09-UAT-PLAN.md`.
5. **Accessibility.** Keyboard navigation, focus order, contrast and screen
   readers were not assessed. CLAUDE.md requires accessibility to be built in;
   this audit cannot say whether it is.
