# D9 — Test plan

What to test, in what order, and why each one is on the list.

**This does not replace `docs/phase7/UAT-MANUAL-EXECUTION-GUIDE-2026.10.1.md`** —
that is the owner-facing guide, 335 checks with exact click paths, and it stays
the document you work through. **This is the audit's own list:** the things this
audit could *not* settle, ordered by what they would cost if wrong.

Every item says what to do, what a pass looks like, and which finding it closes.

---

## 1. Before anything — one correction to make the testing honest

The owner has deployed the current build. **Confirm it before testing
anything**, because the version number cannot tell you: `APP_VERSION` has read
`2026.10.1` throughout October's work, so the sign-in footer looks identical
before and after a deployment.

**Do this:** upload `deploy-check.php` from the release zip to the site root and
open `/deploy-check.php`. It carries the checksums of all 686 files and reports
whether every one landed — including files in sub-folders, which browser file
managers silently skip. **That is the trap that has had earlier UAT runs
testing an old build.**

**Pass:** the page reports every file matching.

---

## 2. Priority 1 — one customer seeing another's data

**Everything in this section is about isolation. Nothing else on this page
matters as much.**

### T-1 · Portal party isolation — the biggest untested thing in the product

**Why:** `00a-ERRATUM-COVERAGE.md` / **GAP-012**. Three external portals —
client (`/portal/*`, 33 routes), vendor (`/vendor/*`, 11) and professional
(`/pro/*`, 26) — were **never in this audit's universe**. They are dispatched
ahead of staff login with their own tables and session keys, which is good
architecture, and portal access runs on a **separate** permission system
(`PORTAL_PERMS`, `pcan()`) that the permission matrix describes and **nobody has
verified**.

**Setup:** a non-production workspace. Two client companies, A and B, each with
a portal user. Two vendors. One professional. Give each some records — a report,
an invoice, a requirement.

**Steps:**
1. Enable the client portal for A and B (`/portal-users` → **Switch it on** →
   **Create the invitation**).
2. Sign in as A's portal user. Note the id of one of A's reports, invoices and
   requirements.
3. Sign in as **B's** portal user. In the address bar, request **A's** report,
   invoice and requirement by id. Repeat for every portal screen that takes an
   `?id=`.
4. Repeat the whole exercise for two vendors on `/vendor/*`.
5. As a professional on `/pro/*`, attempt to read another professional's
   documents, credentials and bookings by id.
6. As each portal user, request a **staff** route (`/users`, `/invoices`,
   `/settings`).
7. As a **staff** user, request a portal route.

**Pass:** every cross-party request is refused — not merely hidden from a list,
but refused when asked for directly by id. No portal user reaches a staff
screen. A staff user reaching a portal route gets the deliberate 404.

**Fail is a P0.** Stop and fix before anything else on this page.

### T-2 · Tenant isolation

**Why:** one database per tenant is the design; it has not been tested from
outside.

**Steps:** with two tenants provisioned, sign in to tenant 1 and request
records by id that exist only in tenant 2 — across work orders, invoices,
reports and users. Try the super-admin **"log in as"** path (T-9) and confirm it
cannot cross tenants except deliberately.

**Pass:** nothing from tenant 2 is reachable from tenant 1.

### T-3 · The privacy-safe talent search actually is

**Why:** the matrix states a client searching the marketplace pool sees
**privacy-safe cards** and must **request contact**, which the professional
approves (`connect_privacy_resolve()`). Never verified.

**Steps:** as a client portal user, search `/connect-talent`. Inspect what the
page returns — including the HTML source and any JSON — for e-mail addresses,
phone numbers or identity-document references. Request contact; confirm nothing
is exposed until the professional approves.

**Pass:** no contact detail reaches the browser before approval. *Check the
source, not just the rendering.*

---

## 3. Priority 1 — destructive actions nobody has fired

This audit **deliberately fired no destructive control**. Each of these needs
one careful test on a disposable copy, and each is a one-way door in
production.

### T-4 · What a work-order delete takes with it

**Why:** `workflows/03-OPERATIONS.md` O-F9. `Delete call` is gated
`is_master() || can('ops.call.delete')` — of the role presets, only the
**Branch Application Manager** holds it — and its dialog says
*"Delete this call and its jobs? This cannot be undone."* **So the cascade to
jobs is stated. What happens to an attached inspection report, an approved
billable event or a voucher line is unknown.**

**Steps:** build a complete chain — work order → job → issued report → approved
billable event → voucher line. Note every id. Delete the work order. Then look
for each downstream record directly.

**Pass:** either everything dependent is removed together, **or** the delete is
refused because dependents exist. **An orphaned billable event or a report
pointing at a work order that no longer exists is a fail** — one is a money
problem, the other an accreditation problem.

### T-5 · Backup, restore, and restoring somebody else's file

**Why:** `workflows/10-ADMIN.md` **A-F3, P1**. `/backup` has **no module
gate**, and **↺ Restore** / **↺ Upload & restore** replace the workspace's
data. An uploaded archive *becomes the database*. Nothing in the permission
matrix mentions backup or restore.

**Steps:**
1. Who can even open `/backup`? Try it as every role.
2. **💾 Back up now**, **⬇️ Download**. Confirm the file is complete and
   readable.
3. On a disposable workspace: change some data, then **↺ Restore**. Confirm the
   change is gone and nothing else broke.
4. **Upload & restore a file that is not a valid backup** — a text file, a zip
   of something else, a backup from a *different* workspace. **Especially the
   last one.**
5. Check the audit trail for all of the above.

**Pass:** only an explicitly authorised role can reach it; an invalid or
foreign archive is **rejected with a clear message**; every restore is logged.

**A foreign backup being accepted would be a P0** — it would mean one
customer's data can be loaded into another's workspace.

### T-6 · Merging two companies

**Why:** `workflows/09-DIRECTORY.md` **D-F3**. A merge rewrites history across
six areas — quotes, work orders, reports, invoices, nonconformities, candidates.
`reason` is not marked required, and **undo was not tested**.

**Steps:** create two partner records, each with records in several areas. Merge
them with `keep` and a `reason`. Then check every area for records pointing at
the discarded id. Try a merge with an empty `reason`. Try to undo.

**Pass:** no record points at the discarded id; `reason` is required; and either
undo works or **the screen says plainly that it cannot be undone** before you
press it.

### T-7 · Trimming the audit trail

**Why:** `workflows/06-REPORTING.md` **R-F11**. `/compliance` →
**"Trim what is past retention"** posts `_do=trim` behind *"Remove audit entries
older than N days? This cannot be undone."* **Retention-bounded, confirmed and
honestly explained — this is good design.** The open questions are the period
and the authority.

**Steps:** read `audit_retain_days()`. Who can change it? Who can press trim?
Trim on a copy and confirm only entries older than the period went. Confirm the
trim itself is recorded.

**Pass:** the period is long enough for your accreditation cycle, only an
authorised role can trim, and the trim is itself in the trail.

### T-8 · The identity-document sweep

**Why:** `workflows/05-QUALITY.md` E-10. **Save & sweep** on `/identity` sits
on the form holding `iddoc_retain_days` and `iddoc_purpose`. **I verified the
control and its form, not the deletion.**

**Steps:** with documents older than the retention period, press
**Save & sweep**. Confirm what went and what stayed. Confirm the
**🔎 Access review (DPO)** log shows the sweep.

**Pass:** only past-retention documents are removed, and the act is logged.

### T-9 · Super-admin impersonation

**Why:** `workflows/10-ADMIN.md` **A-F5, P1**. `/companies` offers
**"log in as"**. Nothing in the permission matrix mentions it.

**Steps:** impersonate a user in a test workspace. Make a change. Then:
1. Does the **audit trail** record the change — and under whose name, the real
   person's or the impersonated one's? *(The trail does record the role held at
   the time, which is a good sign.)*
2. Can the impersonator **act**, or only look?
3. Is the impersonation itself logged, and **can the customer see it**?
4. Can it cross tenants?

**Pass:** every impersonated action is attributable to the real person, and the
impersonation is visible to the customer.

---

## 4. Priority 1 — the four ghost records, and the one untested form

### T-10 · The four forms that create a numbered record from nothing

**Why:** `04-SCREEN-AND-FIELD-AUDIT.md` **S4-1**. Confirmed by execution on a
throwaway database.

| Form | Created | Note |
|---|---|---|
| `/call-new` | `CALL-00001`, status OPEN, 8 of 71 columns, all defaults | burns a work-order number |
| `/candidate-new` | `CV-00001`, shown as "(no name)" | **2 fields marked required; server ignored them** |
| `/incident-new` | `INC-2026-001` | **starts the CERT-In six-hour clock** |
| `/endorsement-new` | `MGH/AHM/2026/GEN/END/000001` | a formal certificate reference |

**Steps:** after the fix, submit each empty. Then submit each with *one* field
filled. Then check the sequence: is the next real record numbered
consecutively, and were any ghost numbers consumed?

**Pass:** each is refused with a message naming what is missing — the standard
is already set by `/internal-audit-new`: *"Name the auditor. An audit with no
auditor on it is not evidence."* **And no sequence number is consumed by a
refused submit.**

**Note for whoever fixes it:** three of the four already carry `required`
attributes that the server ignores, so **adding `required` in the HTML is not
the fix.** It must be server-side.

### T-11 · `/quote-new` — the one comparable form never tested

**Why:** **S4-2.** 96 fields, 1 required. Excluded from the empty-submit test
because its target table could not be identified automatically (there is no
`quotes` table; the header is `quotations`). Four comparable forms create ghost
records. **A quote is a document you send to a customer.**

**Steps:** submit empty. Then with a client but no lines. Then with two sites
and one line — **which site does the line belong to?** Then a value above every
approval band's `max_amount` — does it need no approval, or is it blocked?

**Pass:** an empty quote is refused; a quote with no lines cannot be sent; the
site/line pairing is unambiguous; and **no value falls through the top of the
approval bands** (a gap at the top is the dangerous direction).

### T-12 · `/hiring-request`'s silent refusal

**Why:** **S4-3.** The only one of eight forms that refuses and says nothing —
the page re-renders identically. **This is the "nothing happens" that has been
reported.**

**Pass:** the refusal names what is missing.

---

## 5. Priority 2 — controls that may not control anything

### T-13 · Does a client hold actually stop an order?

**Why:** `workflows/09-DIRECTORY.md` **D-F1**. The tile says *"Put a client on
hold or block them **before ordering**"*, and **nothing measured enforces it**.

**Steps:** set a client to ON_HOLD, then BLACKLISTED. Try to raise an enquiry, a
quote, a work order and an invoice for them.

**Pass:** each is refused or warns clearly. **A credit control that does not
block is a note, not a control.**

### T-14 · Does a quarantined instrument stop being used — and what about past results?

**Why:** `workflows/05-QUALITY.md` **E-7**, and it is the one I would test first
in Quality. `EQUIP_STATUS` has **QUARANTINE** for an instrument suspected out of
tolerance.

**Steps:** quarantine an instrument that has been used on issued reports. Can it
be allocated to new work? **Are the reports it produced flagged for review?**

**Pass:** no new allocation, and past results are at least *findable* — ideally
flagged. An out-of-tolerance instrument may invalidate every report it touched,
and that is a recall.

### T-15 · Does competence enforcement block allocation?

**Why:** **Q-F6.** `/competence` has **"Switch enforcement on"**, implying
competence can be recorded without being enforced.

**Steps:** with enforcement **off**, allocate an unauthorised person — expect it
to work. Switch it **on**, try again.

**Pass:** with enforcement on, the allocation is refused and the reason names the
missing authorisation.

### T-16 · Does the pre-order checklist block quote approval?

**Why:** `workflows/01-SALES.md` **E-8.** `require_all` should make every item
mandatory before approval — and there is **no waiver path**, unlike hold points
which have WAIVED.

**Pass:** with `require_all` on and one item unticked, approval is refused. Then
ask the owner: **should there be a recorded waiver?**

---

## 6. Priority 2 — the figures

### T-17 · Reconcile one month by hand

**Why:** `07-DASHBOARD-AND-KPI-AUDIT.md`. **This audit describes the dashboards;
it does not validate them.** The test database had 0 work orders and 0 jobs.

**This is the largest single piece of remaining work, and nothing below it is
possible without step 1.**

**Steps:**
1. **Populate a realistic month** — work orders, jobs, reports, billable
   events, invoices, receipts, office costs, cross-office work.
2. Pick **one month and one business unit**. Compute revenue, direct cost,
   profit and margin **independently**, by hand or in a spreadsheet.
3. Compare against `/mis`, `/sbu-pl`, `/call-profit`, `/profitability`,
   `/reports` **and `/analytics`**.
4. **Any two that disagree is a finding.**
5. Establish **which revenue source each screen reads** — the legacy figure
   stamped on the job, or the invoice ledger (**D7-2**). Four files sum the
   legacy one, including `tapi.php`.
6. Run the three reconciliation screens — revenue, cost, reimbursable
   duplication — and confirm they are **empty** before trusting any profit
   figure.
7. Check the three utilisation functions against the same `/work-norms`
   denominator (**D7-5**).

**Pass:** every screen agrees with the hand calculation, and the three
reconciliation screens are empty.

### T-18 · Does a mid-year numbering change break a series?

**Why:** `workflows/10-ADMIN.md` **A-F7.** `/settings` shows a live **Example**
beside each numbering rule, which prevents the input mistake but not the
consequence. **For a GST invoice series this matters legally.**

**Steps:** with invoices already issued, change the prefix, digit width and
financial-year handling. Issue another.

**Pass:** the existing series is untouched and the new number is unambiguous —
or the change is refused mid-series with an explanation.

---

## 7. Priority 2 — the things the audit measured but could not open

### T-19 · The record screens that had no records

**Why:** `04-SCREEN-AND-FIELD-AUDIT.md` §10. `/job?id=` is named as **the
highest-value remaining measurement in the audit** — the job is where the work
actually happens — along with `/voucher?id=`, `/quote?id=`, `/invoice?id=`,
`/document?id=` and `/ncr?id=`.

**Steps:** with real records, inventory each: fields, which are required, every
control and what it does, and every onward link. Then fold the result into the
relevant `workflows/` document.

### T-20 · The 70 newly-found screens

**Why:** **GAP-012.** 70 screens render that were never inventoried, mostly
action handlers (`ncr-*`, `capa-*`, `audit-*`, `complaint-*`, `equip-*`,
`hw-point-*`, `iddoc-*`, `billable-*`, `lead-*`, `opportunity-*`,
`project-costing-*`, `pipeline-*`) plus the analytics hub.

**Steps:** re-run `evidence/screen-field-harness.py` over the corrected route
list, and re-run the module-gate classification and the reachability crawl over
all ≈725 routes.

### T-21 · Behaviour at volume

**Why:** nothing in this audit was exercised with data.

**Steps:** with 10,000 work orders and 50,000 jobs: `/calls`, `/jobs`,
`/schedule`, `/documents`, `/invoices`, `/duplicates`, `/mis`. Check
pagination, sorting, CSV export and the time each takes.

### T-22 · 390px, and accessibility

**Why:** CLAUDE.md requires inspectors to be phone-first and accessibility to be
built in. This audit used column counts and page weight as proxies and
**assessed neither directly**.

**Steps:** at 390px, walk the inspector's whole journey — `/my-jobs`, opening a
job, site check-in, uploading a photograph, closing a visit, `/my-voucher`,
`/my-interviews`. Then: keyboard-only navigation, focus order, contrast, and a
screen reader on the main registers.

---

## 8. Priority 3

| # | Test | Finding |
|---|---|---|
| T-23 | Two people edit one **quotation**, **invoice** or **partner** simultaneously. Expect last-save-wins, silently — the protection covers only `calls` and `jobs` | S4-4 |
| T-24 | **Generate due now** pressed twice in a minute on a recurring service — one work order or two? | `03-OPERATIONS.md` E-8 |
| T-25 | A hiring workflow changed while candidates are mid-pipeline — do they keep the workflow they started on? (`pipeline_id` suggests yes) | `04-RECRUITMENT.md` E-13 |
| T-26 | A requirement **expires** on the marketplace with applicants mid-process — what happens to the open applications? | `02-MARKETPLACE.md` E-5 |
| T-27 | A WhatsApp template still **PENDING** provider approval, with the channel set to **Live** — is sending blocked? | `02-MARKETPLACE.md` E-6 |
| T-28 | Start a report on one of the **28 report types with no form designed** | `06-REPORTING.md` R-F7 |
| T-29 | Can an **issued** report be withdrawn or superseded? No status represents it; ARCHIVED is not the same thing | `06-REPORTING.md` R-F6 |
| T-30 | Switch a module **off** with live data in it, then back on — is the data intact? | `10-ADMIN.md` E-8 |
| T-31 | Change **terminology** after records exist — do stored report titles and template wording follow, or only labels? | `10-ADMIN.md` E-9 |
| T-32 | Is **SSO** available on this plan, or does the tile promise a screen that does not exist? | `10-ADMIN.md` A-F2 |
| T-33 | A **lost inspection stamp** — what invalidates it, and any endorsement made with it? | `09-DIRECTORY.md` D-F7 |
| T-34 | `books_outbox` with Books unreachable mid-push — is a failed push visible anywhere? Sales shows a **Failed** tab; Money shows nothing. Check whether `/integrations` covers it | `07-MONEY.md` Y-F1 |

---

## 9. How this plan relates to the owner's guide

| | `docs/phase7/UAT-MANUAL-EXECUTION-GUIDE-2026.10.1.md` | This plan |
|---|---|---|
| Audience | The owner, working through the product | Whoever closes the audit's findings |
| Content | 335 checks, every one with an exact click path | 34 tests, each closing a named finding |
| Organisation | By journey, as a person uses the system | By **consequence if wrong** |
| Use it for | Confirming the product works | Confirming the product is **safe** |

**Work them together:** the guide tells you whether a feature works; this plan
tells you whether a mistake in it would cost you a customer's data, a GST
series, or an accreditation.

**And run T-1 first.** Everything else on this page is recoverable.
