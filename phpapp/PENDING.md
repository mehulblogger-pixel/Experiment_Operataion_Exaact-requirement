# Pending / parked items — Exaact Inspection & Operations Management System

Living list of things explicitly deferred, so nothing is forgotten. Newest on top.

## 🏭 PLANNED — Universal Vendor Assessment / Audit / Qualification / Performance platform (Aug 2026)

Industry-neutral, configurable vendor-evaluation platform (assessment, audit,
qualification/approval, performance & reassessment). **Build by REUSE** — the
plumbing already exists and must not be duplicated: the config-driven report
engine (types→sections→fields→JSON, no-code builder) is the questionnaire /
criteria / scoring form layer; `report_files` is the evidence engine; NCR
(`partner_id`,`report_doc_id`,`audit_finding_id`) + CAPA are findings/corrective
action; `report_approvals` + `idems_build_approval_chain` is multi-level
approval; `frozen_schema`+`content_seal`+revision lineage is criteria-version
freeze; `idems_audit` (hash-chained) + `tenants.php` (separate-DB) are audit &
tenancy; the lookups engine is the master lists; the AI Report Auditor already
reads field metadata generically; `business_partners` (+ `partner_registrations
.valid_to`) is the vendor master. VASR/VAR/FAR report types are already SEEDED
but are empty shells (no form yet).

Genuinely MISSING (the vendor domain): (1) **scoring in the form engine** —
per-field weight/score + category rollup (keystone; also upgrades every report
type); (2) vendor attributes on the partner — vendor type, product/service
category, risk class, approval status, rating, reassessment/requalification
dates; (3) seeded vendor-type / product-category / risk-class lookups; (4) an
assessment↔vendor linkage; (5) a vendor-scoped audit (`audits.php` today is
internal QMS only, no partner_id, hardcoded checklist); (6) approved-vendor
register + qualification/validity/requalification workflow; (7) vendor scorecard
/ performance (today's `rating.php` is inspector-only); (8) vendor portal
self-assessment + document upload.

Phased build (per spec §56):
- **P1 Assessment foundation** — *scoring keystone DONE + VASR form DONE (Aug
  2026)*; remaining: vendor attributes + seeded lookups; bind assessment to a
  vendor.
  - ✅ **Scoring in the form engine** — `report_fields` gained `weight`,
    `max_score`, `score_map` (ensure_column, guarded). `idems_field_score()`
    normalises any answer to 0–100 (score_map JSON / max_score ceiling / ftype
    defaults for yesno·rating); `idems_score_doc()` rolls fields up to weighted
    per-section + overall scores with a plain-English band
    (`idems_score_band()`); returns null when a type carries no weighted fields,
    so ordinary reports are completely untouched (regression-verified on IR).
    20 unit tests + integration test pass.
  - ✅ **VASR assessment form** — `idems_build_vendor_assessment()` +
    `idems_install_vendor_assessment_sections()` seed the "VASR — Vendor
    Assessment Report" type (guarded migration `vasr_form_seeded_v1`):
    identification + 6 weighted category sections (QMS, capability, competence,
    delivery, HSE, financial) on a shared 4-point scale ("Not applicable"
    excluded from scoring) + findings table + scored recommendation + sign-off.
    Renders through the shared PDF engine.
  - ✅ **Scorecard display** — traffic-light scorecard on the report detail page
    (overall gauge + per-category bars) AND printed in the PDF (weighted
    scorecard block: overall score, band, colour-coded category bars).
  - ✅ **Score → QA** — deterministic QA now flags recommendation-vs-score
    contradictions (approves despite low score / rejects a strong vendor).
  - ⬜ Remaining P1: vendor attributes on the partner (type, category, risk
    class, approval status, rating, reassessment dates); seeded vendor-type /
    product-category / risk-class lookups; assessment↔vendor linkage;
    admin UI to set per-field weights/scales in the report builder.
- **P2 Audit** — vendor-scoped VAR audit form; findings → NCR/CAPA (reuse);
  audit report.
- **P3 Qualification** — approved-vendor register; approval status; validity /
  reassessment dates; multi-level approval (reuse chain); requalification.
- **P4 Performance** — vendor scorecard fed from NCR/complaints/delivery; Vendor
  360 (reuse the customer-360 assembly pattern); periodic reassessment cycle.
- **P5 AI** — vendor-specific checks into the existing AI Auditor (score-vs-
  finding conflict, capacity inconsistency, cert-expiry-overrides-score).
- **P6 Advanced** — vendor portal self-assessment; predictive risk; automatic
  reassessment triggers; vendor comparison / trend.

Design rule: never hard-code an industry or scoring weights — everything
admin-configurable via lookups + the criteria library + the form/scoring engine.


## 📋 PARKED — reporting completeness (Aug 2026)

- **Release Note — reference-documents table.** The auto-generated Release Note
  already carries the identifying details, the (released) PO item table and the
  ITP activity list forward from the inspection report. It should ALSO carry a
  **table of all the documents referred to** in issuing the release note (the
  inspection report's "Reference documents" table — QAP/ITP, drawings,
  specifications, MTCs, etc.), so the RN stands on its own with the full
  document basis for the release. (Needs: add a `reference_documents` field to
  `idems_install_release_note_sections`, a one-time migration to add it to the
  already-seeded RN type, and `reference_documents` in the generator's `$carry`
  list in `ops_idems_release_note`.)

- **Remaining report formats — build by FAMILY, not one-by-one.** Only the
  Inspection Report and the Release Note are fully form-built today. The
  catalogue (`IDEMS_REPORT_SEED`) registers ~37 type *names*, but a type is only
  a name until it has a designed form. Do NOT hand-build 35 forms — they cluster
  into ~6 families that share building blocks; build one strong base per family
  and DERIVE the variants (exactly how the Release Note was derived from the IR),
  so each inherits the shared render engine + the AI Report Auditor automatically.

  Recommended build order:
  1. **Inspection variants** (low effort — IR base + per-type tweaks): DIR Daily
     Inspection, DVR Daily Visit, SIR Stage, FIR Final, STIR Site, RIR
     Re-inspection, SUR Surveillance, FLR Flash, OBR Observation, PPR Punch-point.
  2. **Certificates** (low — short one-page: header + statement + reference +
     sign-off): COC Certificate of Conformity, IC Inspection Certificate, WC
     Witness Certificate, HC Hold Certificate, TCRV Test-cert Review, TCR
     Technical Clarification.
  3. **Vendor family** (medium — question/finding/rating/score tables; also
     unlocks the Phase-3 vendor-scoring AI checks): VAR Vendor Audit, VASR Vendor
     Assessment, FAR Factory Assessment.
  4. **Expediting / progress** (medium — progress-% + dates tables; unlocks the
     Phase-3 expediting AI checks): ER Expediting, MPR Manufacturing Progress.
  5. **Summaries** (higher — these AGGREGATE other reports, i.e. the cross-report
     rollup, build last): WS Weekly, FNR Fortnightly, MPGR Monthly, CSR Client
     Summary, PCR Project Closure.

  Already full working MODULES — do NOT rebuild as report forms (at most add a
  branded PDF export): NCR, CAPA / Corrective Action, Deviation, Complaints, and
  the admin ones (TS Timesheet, ATR Attendance, TVR Travel, EXP Expense).


## 🎨 PARKED — UI / UX optimization pass (Aug 2026)

**Owner note (Aug 2026):** after the functional build is well along, do a
dedicated **UI/UX optimization pass** across the whole app. This is Stage J of
the master roadmap (steps 96–100) plus the audit's UI/UX findings. Keep it on
the list so it happens deliberately, not piecemeal. Scope to cover:

- **Navigation & findability** — breadcrumb consistency, a global command
  palette (type-to-jump beyond the search box).
- **Dense registers** — saved views / column pickers so each user tailors the
  big tables (respecting the configuration-first rule — a user's view is theirs).
- **Accessibility (WCAG)** — visible keyboard focus, ARIA on interactive
  controls, keyboard navigation, contrast checked in BOTH light and dark themes.
- **Forms** — richer inline client-side validation to complement the strong
  server-side gates; consistent required-field and error styling.
- **Dashboards** — summary-before-detail, state encoded in form as well as
  number (pills / severity stripes) so what needs attention reads at a glance.
- **Consistency sweep** — every new screen built since the last design pass
  (custom forms, timesheet, ratings, inspector profile, items & samples, and
  whatever else lands) re-checked against the design-token / theme layer.
- **Mobile/field polish** — the field-user screens get a final touch-target and
  offline-affordance review.

Do this as one coherent pass (or a few grouped batches) so the product reads as
one system, rather than tweaking screens in isolation. New screens built before
this pass should still follow the existing design tokens so the later pass is
polish, not rework.

## ⭐ STANDING RULE — configuration-first, flexible per customer (Aug 2026)

**Owner principle (Aug 2026):** the system must be **highly flexible and
configurable to each customer's requirements.** This is already the product's
strongest area (no-code masters, custom fields, custom forms, terminology &
industry packs, per-module licensing) and it must stay that way. Every roadmap
step from here on is built **configuration-first:**

1. **No hardcoded lists.** Any dropdown / category / status / type a customer
   might want to change goes through the **Masters (lookup) engine**, never a
   literal list in code.
2. **Extensible, not fixed.** New entities carry **custom fields** so a customer
   can add their own data points without code; new registers use the **no-code
   custom-forms** path where it fits.
3. **Toggle-able per customer.** New modules/features hang off the existing
   **licence-by-module + settings** switches, so each customer gets only what
   they bought / need — nothing is force-on for everyone.
4. **Renamable.** User-facing labels route through the **terminology engine** so
   a customer can call things by their own words.
5. **Sensible defaults, full override.** Ship a good default configuration so it
   works on day one, but let an admin change it from Settings/Masters — never
   require a code change to reconfigure.
6. **Enforce, don't assume.** Where a rule is configurable (e.g. ISO gates, work
   norms, approval matrices), the setting drives the behaviour and the screen
   says which state it is in.

When a step below is built, check it against this rule before it ships.

## 🔗 IN PROGRESS — MGH Books integration (single finance platform) (Aug 2026)

**Spine BUILT (Aug 2026):** `lib/booksbridge.php` + `/books-bridge`. The ERP→Books
connector foundation is in, following the live Ads Pro contract: an **outbox**
(the local save never fails if Books is down), **idempotency + a payload-hash
loop-breaker**, **bounded retry with visible failure**, drained by cron or a
button. Gated twice — the Money module must be licensed AND the per-customer
"Books connected" switch on (off by default → the ERP keeps its own invoicing).
An **issued invoice** triggers a push of the party + invoice; a **dry-run** mode
delivers without a live Books server (for testing the wiring), and the Books
reference is stamped back onto the ERP invoice so nothing is billed twice.
16 unit checks.

**UPDATE (Aug 2026) — the connector is now COMPLETE end-to-end on both sides.**
Everything on the "remaining" list below has been built and tested (suite
354/354; the receiver also live HTTP-smoke-tested over a real socket):

- ✅ **Parties / quotes / receipts / credit-notes push** — the whole billable
  trail flows ERP→Books, not just the invoice. An **accepted quotation** carries
  the **exact ERP-rendered PDF** (base64) so Books holds the identical accepted
  document. All idempotent, loop-broken, no-ops when Books is off. Triggers wired
  in `crm.php` (quote accept), `books.php` (receipt, credit note).
- ✅ **Status-back** — Books' invoice no. / IRN / paid vs outstanding written onto
  the ERP invoice (`books_apply_status`), shown on the invoice screen; the ERP
  never recomputes it, so the two cannot disagree. `cron.php` pulls it.
- ✅ **App-switcher** — a "📗 Accounts &amp; GST ↗" link in the Money nav opens
  Books already signed in via the shared SSO, gated on `books_switch_ready()`
  (connected + web address set). No token minted — the ERP stays consumer-only.
- ✅ **The Books-side receiver** — `mgh_books_receiver/` (NEW, separate folder for
  the Books app): `api.php` + `lib/receiver.php`. Implements the three verbs the
  connector calls (`set` / `import` / `status`), owns the money truth (a receipt
  pays invoices oldest-first, a credit note reduces the one it names), replies in
  the exact shape the ERP expects. Storage + login are behind a **13-method
  adapter seam** (`Bkrecv_Store` + `bkrecv_auth_handler`) so the Books team points
  it at Books' own tables/login WITHOUT touching the calculation engine — proven
  by running the same money scenario through two different backends to identical
  figures (`tests/test_books_receiver_adapter.php`).

**Still genuinely open (needs the Books team / the live box, not ERP code):**
- ⬜ **Bind the receiver to Books' real tables & login** — implement one
  `Bkrecv_Store` against Books' schema + register Books' auth. Walked through in
  `mgh_books_receiver/README.md`. (Cannot be done from the ERP repo — needs the
  Books codebase.)
- ⬜ **Canonical shared invoice template** — align the ERP's own standalone invoice
  PDF to Books' invoice format so a customer who later subscribes sees identical
  paperwork. (Deferred: needs Books' final template.)
- ⬜ **Set the same production `MGH_SSO_SECRET` on both boxes** + matching accounts
  (owner/ops task on the live VPS).

## 🔗 DESIGN — MGH Books integration (single finance platform) (Aug 2026)

**Owner decision (Aug 2026):** build out the *whole* Inspection ERP first —
every missing / partial / redesign item on the roadmap — and do the MGH Books
integration **as the final phase**, once the product itself is complete. This
section records the agreed design so nothing is lost. **Do not start this until
the rest of the roadmap is built.**

### The one rule that governs everything: Books is OPTIONAL, never required
A customer who does **not** subscribe to MGH Books must lose nothing. The
Inspection ERP therefore **keeps its own invoicing built in as the default**, and
Books is an **add-on that takes over only when a customer has subscribed.** Two
modes, one product, both fully working on their own:

| | **Standalone mode** (no Books subscription) | **Connected mode** (Books subscribed) |
|---|---|---|
| Who issues the invoice | The Inspection ERP itself (its existing invoicing) | MGH Books |
| Document format | Same canonical template | Same canonical template |
| What the customer gets | Raise & track GST invoices, receipts, ageing, Tally export | The above **+ general ledger, GSTR returns, payroll, CA reports** |
| Customer effort | Nothing — works out of the box | Nothing — just links their Books |

**Non-breaking guarantee:** flip the switch off and the ERP falls straight back
to its own invoicing. Neither app is ever gutted; the bridge is additive.

### The switch — rides the existing licence system (no code for the owner)
Add one per-customer flag, **"MGH Books connected: yes/no,"** set from Super
Admin (it sits alongside the existing per-module licensing, e.g. the "Money"
module). Off → ERP invoices internally (default for everyone). On → the ERP's
"Raise invoice" becomes **"Send to Books,"** Books becomes the system of record,
and every billable event is stamped **"sent to Books #INV-123"** so nothing is
ever billed twice.

### Same-format documents
Make **one canonical invoice / financial-document template shared by both
sides**, so the customer sees the *identical* document whether it came from the
ERP (standalone) or from Books (connected). Recommendation: adopt **Books'
format as canonical** (Books is the dedicated finance product) and align the
ERP's built-in invoice to match, so a customer who later subscribes sees no
change in their paperwork — it just gains real books behind it.

### Single sign-on — already ~95% in place
Both apps are already two consumers of the **same MGH identity hub**
(`id.mghaiapps.com`): same signed-token format (`payload.sig`, HMAC-SHA256), same
`{email, name, exp}` payload, same `MGH_SSO_SECRET`, **email as the shared
identity**. Inspection ERP consumes it in `lib/mghsso.php`; Books consumes it in
`sso.php`. To finish: set the *same real production secret* on both and provision
matching accounts (same email). Then add an **app-switcher** so from the ERP a
"📗 Accounts & GST" link opens Books already logged in, and back.

### Data flow — one way, ERP → Books
- **Parties** (clients/vendors, with GSTIN / address / state) → Books customer/supplier accounts.
- **Accepted quotation** → Books estimate/proforma (optional).
- **Billable event** (closed job / "to-bill" line — amount, HSN/SAC, man-days, reimbursable expenses) → Books draft invoice; Books issues the GST invoice + IRN and records the receipt.
- **Status back** (invoice no. / IRN / issued date / paid vs outstanding) → shown on the ERP job & receivables so operations still sees the truth without recomputing it.

### Hosting & mechanism (facts captured Aug 2026)
- Owner runs a **MilesWeb VPS** → both apps can be **co-hosted on the same
  server**, and the bridge is a **private, fast server-to-server link** (no
  customer sees the plumbing).
- Books must run in **server / multi-user mode** (its `api.php` + database backend
  enabled) to receive pushed data. Confirm on the live box at build time.
- The ERP authenticates its push into Books either via a **dedicated service
  token** or by **piggybacking the shared SSO session** — decide at build time
  once server mode is confirmed. Books' `api.php` already exposes `import` / `set`
  / `create_user` actions and a documented import-JSON format.

### What this replaces on the roadmap
This integration **satisfies the two hardest finance items** with an app the
owner already owns, instead of building them from scratch:
- Roadmap **Step 78 — general ledger / double-entry** → provided by Books.
- Roadmap **Step 79 — payroll & leave** → provided by Books (PF/ESI/PT/TDS/Form 16).
It also strengthens the later multi-currency / global story, since Books owns the
tax engine.

### Security note
The MGH Books copy shared for review was a **"super-admin test" ZIP that contains
a live-looking `MGH_SSO_SECRET` and admin tooling** — treat it as sensitive, do
**not** circulate it, and wire SSO with the **real production secret**, not the
one in that zip.

---

## 📋 THE OPEN LIST — everything still outstanding, in one place (July 2026)

**Read this first.** Everything below this section is a running history, newest
on top, and a lot of it has since been built — the A1–C5 gap review, the whole
2026 accreditation pack, the portal, the trust layer. Those entries are kept
because the reasoning in them is still worth reading, but **this register is the
authority on what is actually open.** 113 unticked boxes live further down; these 69 are the ones that still
matter, and they are all here.

### 1 · Yours, not mine — no code, and some of it is legal

| # | What | Why it matters |
|---|---|---|
| ~~O1~~ | **Done — credentials changed** | Confirmed by the owner |
| O2 | **Backups running, and one restore actually tried** | Every photograph, bill and signed report is in the database. A backup nobody has restored is a hope |
| O3 | **Grievance officer + privacy notice** (Settings → Compliance) | DPDP Act. Two text fields, legally required |
| O4 | **Two-step sign-in on** for roles that move money | Built, switched off |
| O5 | **Written confirmation of Indian data centre + NTP sync** from MilesWeb | DPDP evidence. Two e-mails |
| O6 | **Book the CERT-In empanelled audit** | Required annually |
| O7 | **Confirm the site-entry document rules** match what your clients demand at the gate | I seeded a plausible example, not your real ones |
| O8 | **Confirm the demo now loads through the web page** or still needs the command line | Tells us whether the host's time limit was the cause |

### 2 · Money and billing

| # | What | Note |
|---|---|---|
| ~~M1~~ | **Part-payments — BUILT** | `lib/books.php`. Receipts and allocations are separate rows, so one payment lands across four invoices and half a payment reads as half. TDS the customer withheld settles the invoice too, which is the single most common reason an ageing report is not believed |
| ~~M2~~ | **Credit notes — BUILT, and now export to Tally** | Real credit notes against an issued invoice, capped at what the invoice was for. Cancelling is refused once anything has settled, and keeps the number so the GST series has no gaps. **Now closed:** `/tally` has a third kind, **Credit notes** — each goes over as a Tally *Credit Note* voucher that reverses the sale (party credited, sales + tax ledgers debited) and hangs off the **original invoice via "Agst Ref"**, so it reduces that bill on the ageing at the Tally end instead of being reversed by hand. Tax nature and place of supply come from the invoice it reduces. Export is remembered (`tally_exports` kind `CN` + a `tally_ref` stamp on the credit note) so it is never imported twice, and Undo brings it back and clears the stamp. Its voucher-type name is a setting |
| M3 | **Nothing is read back from Tally** | One-way on purpose, but worth stating |
| M4 | **HSN/SAC is now per invoice line**, defaulting to the company setting | Was a setting only. `invoice_lines.hsn_sac` carries it per line; the default still comes from the Tally settings |
| ~~M5~~ | **Consolidated invoicing — BUILT** | One invoice across many deputations, drafted from /to-bill which lists every closed job nobody has billed, grouped by customer |
| ~~M6~~ | **Customer satisfaction — BUILT** | `lib/satisfaction.php`, `/satisfaction`. A survey is a small lifecycle: REQUESTED when we ask, RECEIVED with an overall score + per-aspect ratings + would-recommend + comments, or DECLINED. A response at or below a configurable threshold raises a follow-up flag that is surfaced, never auto-graded into a complaint — whether a 2/5 is a grievance or a grumble is a person's call. The average is over answered surveys only, so an unanswered request never drags it down, and the response rate counts answered-of-asked. Aspects are an editable Master; the scale and threshold are Settings; extra data via custom fields; the whole feature is toggle-able per customer and off closes the register. Roadmap 5.4 / ISO 9001 §9.1.2 |

### 3 · The accreditation pack — depth, not breadth

| # | What | Note |
|---|---|---|
| A1 | **Training records do not yet grant an authorisation automatically** | The basis is recorded; somebody still types the authorisation |
| A2 | **Risk grading of a person** is not built | And should not be, without a clear rule from you |
| A3 | **Competency scoring / learning-management library** | Deliberately not built as "AI" — these would be rules engines |
| A4 | **Consent register is built but not wired in** | `data_consents` exists; nothing writes to it |
| A5 | **Data-subject requests have no clock** | Incidents count down from six hours; requests do not count down at all |
| A6 | **Erasure covers system users and client contacts only** | Not candidates |
| A7 | **The CERT-In incident report is composed by hand** | The screen states the address and the deadline; it does not file it |
| A8 | **Audit trim is a button, not a schedule** | `audit_trim_old()` runs only when pressed |
| A9 | **SBOM is regenerated by hand** | `php tools/sbom.php` |
| A10 | **ISO/IEC 17021 certification-body engine** | Roadmap phase 6, untouched. A real project, not a configuration change |

### 4 · Client portal

| # | What | Note |
|---|---|---|
| ~~P1~~ | **Accepting a portal request now raises the call — BUILT** | `portal_request_to_call()` in `lib/portal.php`. The "Accept &amp; raise the call" action creates the inspection call from the request, links the two (`portal_requests.call_id`), and drops the coordinator on the call ready to set scope, price, the executing office and who goes — which stay ours to decide, so the call is left OPEN. It carries across only what the client gave us (who, what, where, when-wanted), folded into the call notes. Idempotent — a request already linked is never converted twice. Raising work needs the calls permission; without it, it falls back to Accepted so nothing is lost |
| ~~P2~~ | **Client is e-mailed when a report is issued — BUILT** | `idems_notify_client_issued()` in `lib/idems.php`, fired from the finalize/issue handler. A short note that the report is ready, with a link to sign in to the portal and the public verification code — **never the report itself or any finding**, because confidentiality is the whole point and the portal is where a client reads one. Recipients are the active portal users allowed to see reports (blank perms = everything), or the primary partner contact if there are none. Gated by a Settings toggle (on by default, config-first); wrapped so a mail failure never blocks issuing. On a box with no SMTP the mail log still records what would have gone, matching the rest of the system |
| ~~P3~~ | **Portal accounts linked to client-record contacts — BUILT** | `client_users.contact_id` is now populated. The invite screen offers a saved-contact picker (name + e-mail come from the contact, the account is linked to it); a typed address that matches a saved contact is linked automatically; and a boot-time backfill links pre-existing accounts by matching e-mail — so the two lists of the same people stop drifting apart. The "Who can sign in" list shows the linked contact. A contact with no e-mail cannot become an account (with a reason on screen) |

### 5 · Interface and platform

| # | What | Note |
|---|---|---|
| U1 | **Nothing is pinned in the navigation** | Favourites would be next, but it wants watching real use rather than guessing |
| U11 | **Global search is built, across 13 registers** | `lib/search.php` — customers, contacts, leads, inquiries, quotations, calls, deputations, reports, complaints, nonconformities, corrective actions, people, equipment. Own box in the top bar, "/" to reach it, a unique reference jumps straight to the record. **It is `LIKE '%term%'`, not a search index** — correct at today's volume, a table scan per source, and the thing to replace when a register passes roughly a million rows. That replacement needs B0 decided first — blueprint 002 U2 |
| ~~F1~~ | **Opportunities — BUILT** | `lib/opportunities.php`. The deal, kept distinct from the quotation as confirmed. One deal carries many quotations, so the forecast counts the business once instead of three times. Weighted forecast, win rate and loss reasons all become answerable. Reuses the lead pipeline engine with `entity_kind='OPPORTUNITY'`. A won deal raises the order in one act, carrying the customer, the accepted quotation, its value and the branch — the join that was empty on all 160 existing orders. Offered only where operations is licensed |
| ~~F2~~ | **Customer 360 — BUILT** | `lib/customer360.php`, `/customer?id=`. Answers "what do I need to know before I ring them": what they owe and how late, what we are selling them, what work is in flight, where we have let them down, and when anybody last spoke to them. It re-computes nothing — the outstanding figure is `books_outstanding()`, the same function the ledger and the ageing use, so the three cannot disagree. Every section is behind a licence and a permission check, so a Sales-only install shows no trace of deputations rather than an empty panel |
| **F3** | **The flow is joined but mostly unused** | /flow-gaps measures it: 160 orders with no quotation, 100 closed jobs with no report, 97 closed and never billed. The links and the prompts exist now; the back-data does not. Somebody has to work the list |
| ~~F4~~ | **The advisor — BUILT** | `lib/advisor.php`, `/advisor`, sidebar **What to fix**. Ten checks across selling, doing and billing. Every finding carries five things and one without all five is refused: the rows so it is checkable, what it costs computed in rupees or days, why it happened, numbered steps against real screens in this product, and the role that owns it. Ranked by money rather than count. It never repairs anything itself — a tool that quietly fixed the data would hide how often the handover is skipped, and that rate is the thing worth managing. Each check asks `adv_on()` first, so a Sales-only licence gets the deal checks and no mention of deputations, and the all-clear sentence is assembled from what was actually examined rather than written once |
| ~~F5~~ | **Approval on deal stages — BUILT** | `lib/stagegate.php`, `/approvals` and `/stage-gates`. A quotation of ₹40 lakh already walked an approval chain; the deal it belonged to could be dragged to Won by anybody who could see it, and the forecast is built from the deal. Rules band by value, business unit, pipeline and destination stage; the narrowest matching rule wins. The deal does not move until somebody agrees — it is a gate, not a warning — and **the requester cannot approve their own request, including an administrator**. Approving replays the identical move through `opp_move()` with the gate suppressed, so there is no second code path. The win is credited to whoever raised the request, not whoever agreed to it, or a manager approving forty deals a quarter would appear to have won all forty. **No rules configured = nothing gated**, exactly as before |
| ~~F6~~ | **Ads Pro joined — BUILT** | `lib/adspro.php`, `lib/adsroi.php`, `/adspro` + `/ads-roi`. Ads Pro knows spend and which campaign produced which lead; this system knows the invoice and the receipt. Joined they answer the question neither can alone: **cost per rupee actually collected**. Leads come across into the ordinary lead register carrying campaign and UTM, idempotent on the Ads Pro id, never overwriting a lead edited here. Spend is cached locally so the report answers for a closed year when Ads Pro is unreachable. Outcomes go back so Ads Pro optimises on cash rather than a pixel estimate. The token is refused over plain http to anything but this machine. Nothing appears anywhere until it is connected |
| ~~F7~~ | **MGH single sign-on — BUILT** | `lib/mghsso.php`, per `SSO_CONTRACT.md` from the Ads Pro repository, verbatim on the wire format. **Consumer only, deliberately** — minting tokens would let this system assert any identity to every sibling app. Four refusals that the contract does not require and this product does: it will not create a user (a login here carries a role, office and scope that cannot be invented from an e-mail); it will not waive 2FA; it will not accept a token twice; a deactivated account stays locked. `/sso` shows every attempt, accepted and refused |
| ~~F8~~ | **Leads and enquiries flow BOTH ways — BUILT** | `lib/adssync.php`. A lead or enquiry raised here reaches Ads Pro, and its status follows it — so Ads Pro stops advertising to somebody mid-conversation with us, and can build audiences from our won customers. Four things make it safe: **(1) an outbox, not a live call** — a save queues and returns in 27ms with Ads Pro completely down, and never fails because of it; **(2) field ownership, not last-write-wins** — Ads Pro owns campaign/platform/UTM and we never write them back, we own status/stage/value and it never overwrites ours, and a pull **fills blanks only** so nothing a person typed is ever lost; **(3) a loop breaker** — a hash of the last payload sent, so a push identical to the last is dropped before it leaves; **(4) bounded retry** — a failure is retried 6 times then stands as a visible, explained failure. Drained by `cron.php` or a button |
| ~~F9~~ | **Licence keys — BUILT** | `lib/licencekey.php`, `/licence`, `tools/licence-issue.php`. A signed key says who bought what, how many seats, until when. **Signed with a private key only MGH holds** — a customer with root, the database and the source still cannot forge one; both forgery routes were tested and refused. **Verified offline**, so an install behind a corporate firewall works like one on a VPS and an MGH outage can never stop a customer invoicing. **Expiry makes the system read-only, never locked** — every screen, export and PDF still works, only new records are refused, and the licence screen accepts a key in every state including the broken ones. No `LICENCE_ENFORCE` = no enforcement at all, so every install running today is untouched; enforcement on with no key = a 14-day trial from first boot |
| ~~F10~~ | **Automatic renewal — BUILT** | `licence-server/` (deploy to id.mghaiapps.com) + `lib/licencesync.php`. Customer pays → Razorpay webhook extends the subscription → the installation collects a fresh key by itself. Daily normally, **every 15 minutes once an expiry is close**, and a **Just paid? Check now** button for instant. Nobody types anything. The webhook verifies the signature **before parsing the body**, and refuses a duplicate delivery — Razorpay always retries, and extending twice would gift a free year. **An outage at MGH is a non-event**: the fetched key is cached and the customer carries on until its own expiry. A key that fails verification never replaces a good one, so a misconfigured hub cannot become a fleet-wide outage. The signing key is generated on the server, outside the web root, and is gitignored |
| **F9** | **Ads Pro needs its own patch merged before this works live** | Branch `claude/machine-api-key` on `mehulblogger-pixel/ads-manager`. Two blockers found by running our connector against Ads Pro's real code: (1) its only bearer token is a **login session that expires in 7 days**, so any integration dies weekly — added `MGH_API_KEY`, non-expiring, scoped to one account's workspaces, refusing a workspace that account is not a member of rather than silently substituting one; (2) **there was no route to ask what a campaign cost** — `p6GetCampaignBreakdown()` existed and had never been given an action name. Our spend import had been pointed at `cc_attribution`, an attribution-model endpoint with no spend in it, and had been reporting success while storing nothing. Both corrected and verified against a database built from Ads Pro's own `schema.sql` |
| U12 | **No notification centre** | Flash messages and e-mail only; nothing persists — blueprint 002 U4 |
| U13 | **The shared table is built, and adopted on 3 of 42 registers** | `lib/datatable.php` — server-side sorting on a whitelist, paging, per-user column choice, bulk actions, filter-aware CSV. Live on **activity, leads and nonconformities**. The other 39 still hand-roll their own `<table>` — blueprint 002 U1. **Inline editing and grouping are not built at all** |
| U14 | **Accessibility is a shell everywhere the table has not reached** | Was 1 `aria-` attribute across 122 screens. The three adopted registers now carry 34–38 each (`th scope`, live `aria-sort`, an `sr-only` caption, labelled checkboxes) because the component supplies them. The other 119 screens are unchanged, and the 4 `:focus` rules in the stylesheet still stand — blueprint 002 U5 |
| U15 | **No real light/dark mode** | A "Midnight" colour preset exists; no `prefers-color-scheme`, no toggle, and printing assumes light |
| U16 | **No recently-viewed or favourites** | Blueprint 002 U6 |

### 8 · Making it universal — the pack boundary

| # | What | Note |
|---|---|---|
| **U-P1** | **Six hook points exist; four are unused** | `work.assign` and `document.issue` carry the inspection pack. `record.close`, `customer.create` and `timeline.extra` are declared and empty |
| U-P2 | **Only two of the six ISO call sites are converted** | Report finalisation still calls `report_equipment_block()` and `report_signatory_warning()` directly in `idems.php`; they should fire `document.issue` |
| U-P3 | **17 lib files still name ISO/IEC 17020 in prose** | Harmless in comments, wrong in anything user-facing on a non-inspection install |
| U-P4 | **The inspection registers are always visible** | Equipment, competence, impartiality, NCR and the rest show in the menu whether or not the pack is on. They should follow the pack |
| U-P5 | **Terminology is not pack-aware** | "Deputation", "inspection call", "engineer" are renameable, but a pack should be able to ship its own defaults |
| U-P6 | **No second pack exists** | Until a trading or manufacturing pack is written, "universal" is a claim rather than a demonstration |

### 9 · From blueprints 003–008 — architecture and platform

| # | What | Note |
|---|---|---|
| ~~B0~~ | **ANSWERED — it is a VPS on MilesWeb** | Not shared hosting, as I had assumed and built around. That unblocks B1 (REST API), B5 (backups) and B6 (caching and a job queue), and makes encryption at rest possible. Nothing built so far depended on the shared-hosting limits in a way that has to be undone — the choices made under that assumption (no Composer, hand-written XML/PDF, server-side paging) all still hold, they are simply no longer forced |
| B1 | **No REST API** | Nothing exists. The single biggest item in blueprint 005 and the prerequisite for every external integration |
| B2 | **No webhooks, API keys or OAuth** | Follows B1 |
| B3 | **No field-level or record-level permissions** | Permissions stop at module + branch today |
| B4 | **No password policy** | No minimum, no age, no reuse rule |
| B5 | **NO BACKUP FEATURE** | The compliance screen *tells you to take one*; that is advice, not a feature. **No longer blocked** — a VPS can run a scheduled dump |
| B6 | **No caching layer, no job queue** | `cron.php` runs 26 steps once a day. **No longer blocked** — the VPS answer makes both possible |
| B7 | **No territories** | Blueprint 003 |
| B8 | **Partly — the FUNNEL builder is built** | `/pipelines` and `/pipeline?id=` — create, clone, rename, reorder, set probability and service level, retire. Guard rails: a pipeline must keep a WON and a LOST stage, a stage holding deals is retired rather than deleted, and a WON stage is forced to 100%. **Still missing:** the workflow, form, dashboard and report designers — blueprint 008 |
| B9 | **No configuration export/import, versioning or sandbox** | Blueprint 008 |
| B10 | **No localisation** beyond English and ₹ | Blueprint 008 |
| B11 | **No usage analytics or in-app feedback** | Blueprint 007. Also blocks the "reduce manual work by 60%" target in 004, which is unmeasurable today |
| B12 | **No automated functional tests** | `tools/lint.sh` runs five static checks and there is a smoke crawl; everything else is verified by hand over HTTP — thorough but not repeatable |
| B13 | **No user manual, admin manual or API documentation** | Blueprint 006 |
| B14 | **AI that needs history cannot ship** | Lead/opportunity scoring, churn, renewal and payment-delay prediction, forecasting — all learn from outcomes, and there are no leads, opportunities or activities yet to learn from |
| U2 | **48 destinations is unchanged** | The fold made them findable; it did not decide they all deserve to be there. Your call on what to drop |
| U3 | **Version number in the app + upgrade note** | Roadmap 2.3. Nobody can tell which build a client is running |
| U4 | **Release artifact** — versioned zip with a checksum | Roadmap 2.4 |
| U5 | **Licence-key decision** | `SEAT_LIMIT` is read and displayed; nothing enforces it. Enforce it or delete it |
| U6 | **Web setup screen** so `config.php` need not be hand-edited | Optional |
| U7 | **Play Store / App Store** | Roadmap phase 7. The PWA works today; this is packaging |
| U8 | **CSP still carries `'unsafe-inline'`** | The screens use inline handlers. Script injected into a page could still run; it could not reach another site |
| U9 | **Per-source login throttle could lock a whole office** | Counted per IP at 30 attempts |
| U10 | **Two-step enrolment has no QR code** | The setup key is typed in |

### 6 · Demo and test data

| # | What | Note |
|---|---|---|
| D1 | **Evidence photographs are 1-pixel placeholders** | Real EXIF reading and compression need a real photograph |
| D2 | **Nothing is e-mailed** | The mail log records what would have been sent |

### 7 · Health work, whenever there is a quiet week

| # | What | Note |
|---|---|---|
| H1 | **Carve `Money` out of `ops.php`** | `job_money`, `job_profit`, invoicing. Pure logic, called everywhere |
| H2 | **Carve `Notification` out of `ops.php`** | SMTP and every `send_*_email` |
| H3 | **`ops.php` is now ~4,900 lines and called 700+ times** | It is where the regressions come from. H1 and H2 are not cosmetic |

### What is NOT on this list, because it is done

The A1–C5 gap review, the whole ISO/IEC 17020:2026 pack, the trust layer, the
client portal, the CRM funnel (P0–P7), the Tally export, receivables ageing,
the nonconformity register, client acceptance of reports, multi-action
corrective actions, branch scoping, portal permissions, site-entry documents,
confidentiality, and the competence review cycle. Entries about them survive
below as history; they are not open.

## ▣ A CRM that works on the day it is installed (July 2026)

The requirement: *"a complete professional CRM with its individual dashboard,
pipelines, funnels all prebuilt as per the industry"*, then *"prebuilt in
multiple number which can be adjusted by the user as per their flow ... or
create new ones."*

**Twelve industry templates** (`lib/industry.php`) — inspection, manufacturing,
trading, professional services, construction, IT services, healthcare,
education, logistics, real estate, staffing, and a plain B2B default. Each ships
**three** pipelines: a new-business funnel, a second funnel for the business that
does not behave like new business (renewals, repeat orders, contract extensions,
variations), and a lead pipeline. Plus the sources deals actually come from in
that trade and the reasons they are actually lost.

The funnels differ where it matters. A construction bid sits at 5% for months
and is lost on L1 price; a trading enquiry moves in three days; an IT renewal
starts at 45% because the customer is already yours.

**Applying a template creates; it never edits.** A live system has deals sitting
on stages — rewriting those stages would move every deal somewhere it was never
put. The new pipelines become the default for new work and the old ones keep
their deals until they close. The screen says so before you press it.

**The funnel builder** (`lib/pipelines.php`) closes a gap worth naming: pipelines
and stages have always been read from the database rather than hard-coded, but
there was **no screen to change them**. "Configurable" was true of the data model
and false of the product. Now: create, clone, rename, reorder, set probability
and service level, retire. Five guard rails, each because breaking it corrupts
live data — a pipeline must keep a WON and a LOST stage (every rule reads `kind`,
not names); a stage holding deals is retired rather than deleted; a WON stage is
forced to 100% because one left at 80% understates every forecast.

**The CRM's own dashboard** (`lib/crmdash.php`) — kept apart from the main
dashboard because "what is happening today" and "how is selling going" are
different questions. The funnel, weighted forecast, win rate, average deal,
sales cycle, deals that have stopped moving, why we lose, who is selling, and
the activity behind it.

**On the funnel chart being honest.** The bars are what is sitting in each stage
now. The conversion beside each is NOT bar B ÷ bar A — that compares two
snapshots and means nothing when stages move at different speeds. It comes from
the stage history: of everything that ever reached the previous stage, how much
ever reached this one. A stage nobody has passed through shows "—", not 0%,
because zero means "everybody stopped here" and no data means nobody has arrived.

## ▣ Customer 360, and the flow end to end (July 2026)

`lib/customer360.php` — the last item in the CRM plan, and built last on
purpose: it is an assembly, not a feature, and it could not be honest until the
things it assembles existed. Six months ago this screen would have shown a name,
an address and a list of calls.

It answers the question somebody actually has, which is never "show me the
customer record" but **"what do I need to know before I ring them?"** — what they
owe and how overdue, what we are selling them and what it is worth, what work is
in flight and what is finished-but-unbilled, where we have let them down, and how
long since anybody spoke to them.

**It re-computes nothing.** The outstanding figure comes from
`books_outstanding()`, the same function the ledger and the ageing report use;
the ageing bands are `AR_BUCKETS`, the same bands the receivables screen uses. A
360 screen with its own arithmetic is one that disagrees with the ledger, and
then nobody believes either. Verified: 360 says ₹1,53,400 outstanding and the
ledger closes at ₹1,53,400.

**Counts are true, lists are capped.** The panels show the newest five; the
headline numbers are real counts. A headline that says 10 because the list stops
at 5 is the kind of lie somebody repeats in a meeting.

**Nothing is assumed installed.** Every section is behind a licence AND a
permission check. On a Sales-only install the Work and quality sections are
absent entirely — not empty panels headed "Deputations".

**The flow now runs end to end**, every hop navigable both ways and every break
counted:

    Lead → Opportunity → Quotation → Order → Deputation → Report → Invoice → Receipt

## ▣ Sales & CRM can now be sold on its own (July 2026)

The owner asked whether the whole sales module including the CRM could be
delivered separately and completely. Measured rather than answered from memory:

**It could not.** `PRODUCT_MODULES` had a 'Sales & CRM' entry, but `operations`
was flagged **core**, so it could never be switched off — "sold separately" was
a line in a settings screen, not something that could be delivered.

**It can now, and it is tested.** `MODULES_OFF=operations,reporting,hr` gives a
working Sales & CRM + Books install: 107 screens crawled, all rendering cleanly,
every operations screen correctly refused.

**What had to change**

* `operations` is no longer core. Administration stays core — every install
  needs masters, users and settings. A trading company or a consultancy buying
  the CRM has no deputations to schedule.
* **`is_master()` was walking straight past the licence.** `can()` already
  refused a permission belonging to an unbought module, but 33 screens guarded
  themselves with `can('mod.x.view') || is_master()`, and the bare `is_master()`
  ignored it. An administrator on a Sales-only install was being offered the
  equipment register, the nonconformity register and the report engine. New
  `is_master_of($modules)`: being the administrator means you can do anything the
  PRODUCT does, not that you own modules you have not bought. All 33 converted.
* Nav guards using bare capabilities (`idems.type.manage`, `dash.operations`)
  are invisible to the licence, which only understands `mod.<x>.<y>`. Those four
  are gated explicitly.
* `overheads` moved from admin to operations — the cost run and office overheads
  exist to cost deputations.

**Coupling, measured:** the whole sales/CRM/books stack calls exactly **three**
functions that live in operations-only files — `deliverable_options()`,
`idems_log()` and `ncr_can_view()` — and all three are already behind
`function_exists()` or a licence check.

## ▣ CRM → operations → the books, as one flow (July 2026)

The owner's requirement: *"Our system must flow from CRM to operations and
then to MGH books."* Two structural breaks stood in the way, and both were
measured before anything was built.

**Break 1 — CRM and operations were not actually joined.** `calls.quotation_id`
existed and **0 of 160 orders carried one**. The field was saved by the form and
shown by no screen, so nobody ever noticed it was empty.

**Break 2 — operations had nothing to hand over to.** There was no invoice
table, no payments table and no ledger. An invoice was six columns typed onto a
deputation and a payment was a yes/no flag. Part-payment, TDS, credit notes, one
invoice across several jobs and a customer ledger were all impossible.

**What was built**

* `lib/books.php` / `lib/booksui.php` — invoices with lines and tax, receipts
  with allocations, credit notes, and a customer ledger with a running balance.
  Numbering is allocated at issue so a dropped draft leaves no gap; a cancelled
  invoice keeps its number; reversing value is a credit note.
* `/to-bill` — every closed deputation nobody has billed, grouped by customer,
  one click to a draft invoice. This is the operations→books handover, and it
  did not exist in any form before.
* Receivables ageing and the Tally export now read the invoice register. The old
  ageing showed an 80%-paid customer for the full amount and never cleared one
  who settled through TDS.
* `lib/chain.php` — the thread from any record to any other, a strip on five
  detail screens, and `/flow-gaps`, which counts the breaks: 160 orders with no
  quotation, 100 closed jobs with no report, 97 closed and never billed.

**What is deliberately still open** — F1, F2 and F3 in the register above.
Opportunities and Customer 360 are not built, and the back-data on 160 existing
orders is not going to join itself up.

## ▣ Global search — one box, thirteen registers (July 2026)

Blueprint 002 U2. `lib/search.php`. The box in the navigation filters MENU
ITEMS: it answers "where is the quotation screen" and never "where is quotation
Q-2607-001". This one searches records, and it has its own box in the top bar so
the two are not confusable.

**Thirteen sources:** customers & vendors, contacts, leads, inquiries,
quotations, inspection calls, deputations, reports, complaints & appeals,
nonconformities, corrective actions, people, equipment. Searching a customer's
name returns the company, the person you deal with there, the lead and the
inquiry — in one page.

**What makes it safe, each one verified:**

* **A source the person cannot see is never queried.** Not queried and then
  filtered — that is how row counts leak. Checked with a branch manager holding
  a cut-down permission set: the sources offered matched their menu exactly.
* **Branch scope applies here as on every register.** Checked with a
  Mumbai-scoped user: their own job code jumped straight to the record; two job
  codes from other branches returned an empty page, not a redirect and not a
  title.
* **The term is bound and its LIKE metacharacters are escaped**, so typing `%`
  matches a literal percent sign instead of every row in the system.
* **A source that throws is named on the page**, so "no results" and "that
  register is broken" never look the same. Tested by forcing one to throw: the
  other twelve returned their matches and Equipment was named as unavailable.

**Typing a whole reference goes straight to the record** — but only when there
is exactly one match and the term contains a digit, so somebody browsing towards
a company by name is not thrown into a record.

**Stated plainly, because it will matter later:** this is `LIKE '%term%'` with a
small LIMIT per source, not a search index. A leading wildcard cannot use an
index, so each source is a table scan — microseconds today, not microseconds at
a million rows a table. MySQL FULLTEXT and SQLite FTS5 are different enough that
maintaining both would guarantee they drift, so the right move is one index once
the hosting decision (B0) is made. The sources are structured so they can be
re-pointed at one without touching a screen.

## ▣ One table component, adopted on three registers (July 2026)

Blueprint 002 U1. `lib/datatable.php`, built once and used by the activity
timeline, the lead register and the nonconformity register.

**What it does, and why each part is the way it is:**

* **Sorting and paging happen in the database, not the browser.** That follows
  from the hosting answer: sorting client-side means shipping every row first,
  which is fine at 160 rows and useless at the volume blueprint 005 talks about,
  and MilesWeb has no cache to hide behind.
* **Sort columns are whitelisted.** The screen declares a SQL expression per
  column; `dt_sql_tail()` is the only place an `ORDER BY` is assembled, and a
  `?sort=` the screen did not declare is ignored rather than rejected. Verified:
  `?sort=a.id; DROP TABLE activities--` sorts by the default and the table is
  still there.
* **Page size is capped at 200.** `?per=99999` falls back to the screen's default.
* **Column choice is per user, in a new `user_prefs` table** — not a cookie, so
  a register you arranged at your desk is arranged on your phone.
* **CSV exports what the filters match, not the page you are looking at.** The
  old activity export carried a hard `LIMIT 300` and said nothing about it.
* **Accessibility is in the component**, so one build fixes it on every screen
  that adopts it: `<th scope>`, `aria-sort` that reflects the real state, an
  `sr-only` caption naming the register and its row count, and a label on every
  checkbox. Those three screens went from ~1 `aria-` attribute to 34–38 each.

**Two defects fixed on the way**, both found by paging rather than by reading:
the activity register silently stopped at 300 rows, and `ncr_all()` interpolated
today's date with `db()->quote()` instead of binding it.

**Bulk actions** are on leads: assign to me, and mark lost with a reason. Both
re-check branch scope from the ids that were posted, and "mark lost" skips a
lead that is already converted or lost rather than overwriting it — tested with
one of each.

**What is honestly still open:** 39 registers have not adopted it, and neither
inline editing nor grouping is built at all. U13 in the register above says so.

## ✅ The eleven gaps from the July review — all closed (July 2026)

Owner: *"they are very very shallow and is not integrated with the data."* Fair.
The registers satisfied a clause reading and fell over on contact with a
multi-branch company. Eleven items, all shipped, each verified over HTTP.

**Where I took the decision myself**, because the answer was needed to build and
waiting would have stopped everything. Any of these can be changed — they are
settings and lists, not structure:

1. **Client portal roles** — six permissions rather than fixed roles, with five
   presets (full · quality/technical · commercial/purchasing · accounts ·
   read-only). **Seeing a report and accepting one are separate**, because
   accepting binds the company. Optional site restriction. A blank permission
   list means everything, so nobody invited before this lost access.
2. **How somebody becomes authorised** — the authorisation records the *basis*
   (training · witnessed job · examination · documented experience · carried
   over), a **review cycle in months**, and a **witnessing interval**. Review is
   counted from the last review or, failing that, from the grant date, so an
   authorisation nobody ever reviewed still becomes due.
3. **A rejected report raises a MINOR nonconformity**, not a major one. "Wrong
   PO number" and "we disagree with the conclusion" are not the same event and
   grading them by rule would be guessing — a person reads it and decides.
4. **A confidentiality breach raises a MAJOR one**, automatically. Losing
   control of a client's information is not a minor lapse.
5. **A report signed by somebody unauthorised warns and records, not blocks.**
   Unlike an uncalibrated instrument — where the measurement is void — this is a
   management failure to put right, not a reason to withhold a report a client
   is waiting for.
6. **Site-entry documents block with a manager override that states a reason**,
   the same shape as the certificate gate. Refusing outright teaches people to
   back-date documents, which destroys the evidence.
7. **Nothing is auto-accepted by silence.** A client who does not answer is
   reported as not having answered, with the age. A rule that reads silence as
   consent is discovered during a dispute.
8. **An unassigned record stays visible to every branch.** Scoping hides other
   branches' work, never orphans.

**Still open, deliberately:**

- [ ] **Part-payments.** A payment is a flag and one amount, so receivables
      ageing ages the whole invoice or none of it. Doing it honestly needs a
      payments table.
- [ ] **Credit notes** are not exported to Tally; a cancelled invoice already
      imported has to be reversed by hand.
- [ ] **Nothing is read back from Tally.** One-way on purpose.
- [ ] **Training records do not yet grant an authorisation automatically** — the
      basis is recorded, but somebody still types the authorisation.
- [ ] **The 17021 certification-body engine** (roadmap phase 6) is untouched.

## ✅ The money goes to Tally, and the ageing says how old it is (July 2026)

Roadmap 5.2 and 5.3, the last two buildable items in Phase 5.

**5.2 asked for a decision, not a feature: build GST into the invoice side, or
export to Tally.** The recommendation on record was the export, and it was
taken. A full engine is HSN/SAC, place of supply, IGST against CGST+SGST,
credit notes, e-invoice and IRN — roughly three times the work — and at the end
of it the firm still keeps its books in Tally. Two systems holding the same
ledger is not an improvement on one.

**`/tally` does the smaller, truer job.** Raised invoices become Sales vouchers
Tally will import: the party ledger, a `New Ref` bill allocation carrying the
invoice number so it lands on the ageing, the sales ledger and the tax ledgers.
Payments become Receipt vouchers with an `Agst Ref` that knocks the invoice off
again. Ledger names, voucher types and the default rate are the accountant's,
set on the screen; the defaults are what Tally ships with, so a first export
usually works untouched — except our own state, which has no honest default.

**Four decisions worth the argument:**

1. **Nothing is guessed.** The IGST-or-CGST+SGST choice comes from comparing
   state codes — the client's from the first two digits of their GSTIN, ours
   from settings. A client with neither GSTIN nor state is **refused by name,
   with the reason on screen**, and left out of the file. Picking one silently
   is a return that has to be revised later.
2. **The invoice amount is the invoice amount.** The money desk falls back to
   the quoted value or the inter-office credit when nobody has typed a figure
   yet. That is right for a worklist and wrong for a ledger, so a row without a
   real invoice amount is refused too.
3. **Exporting is remembered.** Importing the same sales voucher twice is a real
   and painful mistake, so each batch is recorded against the job and the same
   invoice is never offered again — with an **Undo** for the day the import
   fails at the other end.
4. **No library.** Tally's import format is XML over a documented envelope,
   written by hand for the same reason the .docx and PDF writers were: shared
   hosting has no Composer.

**5.3 — `/receivables`.** Not-yet-due / 0–30 / 31–60 / 61–90 / 90+ by client,
worst first, drill through a row to the invoices behind it, CSV out. Aged **from
the due date**, because an invoice on 60-day terms is not late on day 45; a
switch ages from the invoice date instead, and any row with no due date says so
rather than quietly mixing two meanings into one column. Not-yet-due is its own
column so the top bucket keeps its meaning and the total still agrees with the
money desk — **verified: both say ₹36,64,000 across 51 invoices.**

**Two bugs the testing found, not the reading.**

- The `tally_exports` join placeholder binds *before* everything in the WHERE.
  Passing the arguments in logical order compared the invoice date against the
  word "SALES" and returned nothing at all.
- `GST_STATE_CODES` looked like it had string keys. PHP turns `'24'` into the
  integer 24 while leaving `'01'` a string, so the strict compare that decides
  the tax split had `24 !== '24'` — **and called a Gujarat-to-Gujarat sale
  inter-state.** Every code now goes through one function that returns a
  two-character string. This is why the demo has an inter-state client: without
  Girnar in Maharashtra the screen looked right.

**The demo can now demonstrate both.** Every seeded invoice used to be fifteen
days old, which made an ageing report a single column proving nothing, so the
ages are spread across all five buckets on purpose. The named clients carry a
GSTIN (one in Maharashtra against a Gujarat seller, so there is a real IGST
row); the ten Edge Clients deliberately have none — that is the refusal path,
and it needs rows to refuse. Eighteen numbered scenarios in
`docs/DEMO-TEST-PACK.md` §12.

**Still open on the money side:**

- [ ] **Part-payments.** A payment is a flag and one amount, so an invoice
      cannot be half settled — the ageing ages all of it or none of it. Doing
      this honestly needs a payments table; inventing one to serve a report
      would put a second version of the truth next to the first.
- [ ] **Credit notes and cancellations** are not exported. A cancelled invoice
      already in Tally has to be reversed by hand.
- [ ] **Nothing is read back from Tally.** If Accounts marks a payment there, it
      does not come here. One-way on purpose, but worth stating.
- [ ] **HSN/SAC is one code for the whole company**, held as a setting for
      reference only — it is not written per line, because the line does not
      carry one.
- [ ] Roadmap **5.4 satisfaction capture** and **5.5 consolidated invoicing**
      are the remaining Phase 5 items.

## ✅ The sidebar folds, and you can type to find a screen (July 2026)

Owner, from a phone: *"It feels the side bar navigation is clutter and very
difficult to find things."* Correct — a master admin was offered **48
destinations, all expanded, all the time, weighted the same**. On a phone that
is a wall you scroll, not a menu you read.

**The actual bug underneath it.** Eleven screens — equipment, competence,
impartiality, complaints, corrective actions, internal audits, management
review, evidence review, data control, client portal, identity documents — were
rendered **loose, with no group heading at all**. They read as a continuation of
Operations, which is why that one group looked nineteen items long. They are
their own subject and now say so: **Quality & accreditation**. Operations is
back to 8.

**Three changes, all in the view layer** — no routes, no data, no permissions:

1. **Group headings fold.** Real `<button>`s, so keyboard and screen readers
   work. Which sections are shut is remembered per browser. The section holding
   the page you are on is **forced open whatever the memory says** — being on a
   screen whose menu entry is hidden is worse than any amount of scrolling.
2. **Type to find.** A box at the top filters what is already on the page: no
   request, no index to keep in step with the menu, works offline. Enter opens
   the top match, so three letters and a tap is the whole journey. Headings with
   no match are taken off screen with their section.
3. **The 60px icon rail is unaffected** — a folded section still shows every
   icon there, because at that width there is no heading to click to get it back.

**A CSS bug found by looking at the screenshot rather than the test.** Setting
`hidden` on a heading did nothing on screen: an author `display:flex` beats the
browser's own `[hidden]` rule, so the filter left headings standing over
nothing. The test had asserted the *attribute* and passed. It now measures what
is actually painted.

**Still open on navigation:**

- [ ] Nothing is pinned. If somebody uses four screens all day they still walk
      the tree or type. Favourites would be the next step, but it wants watching
      real use first rather than guessing which four.
- [ ] The item count is unchanged — this made 48 findable, it did not decide
      that any of them do not deserve to be there. Trimming is a separate
      judgement and needs the owner's call on what to drop.

## ✅ The demo dataset now covers every module (July 2026)

Owner: *"demo data is of no use at present due to addition of many other
features."* Measured, and worse than expected: the seed filled Operations and
**every one of the 25 registers added since loaded empty** — sales, reporting,
the whole accreditation pack, the trust layer, the client portal. A demonstration
showed about a third of the product and two dozen blank screens, including the
entire ISO/IEC 17020 story that is the actual differentiator.

**Now 32 modules, all seeded**, from one shared cast of clients, engineers and
deputations so the modules are visibly about each other rather than twenty
unrelated islands. `docs/DEMO-TEST-PACK.md` lists every scenario with the screen
to open and what should happen there.

**Roughly half the records are deliberately wrong**, and that is the point. A
register full of tidy compliant rows cannot tell you whether a rule is working or
whether the rule was never written. So the seed plants: an instrument whose
calibration expired and which nobody withdrew; a calibration that FAILED; an
authorisation lapsed on a sub-contractor; one suspended with its reason; an
impartiality declaration that lapsed unnoticed; a complaint past its
acknowledgement deadline; an appeal the original decider must be refused; a
corrective action verified as **not effective**; an overdue internal audit;
a management review that cannot be completed; an identity document held past its
retention date. Every one is a numbered row in the test pack, and `t_demo.php`
asserts each still exists — because those are exactly the rows a future tidy-up
would remove, and the test pack would quietly stop being testable while still
reading as though it worked.

**The demo deliberately ships with one failing integrity check** — the retention
case. A demo where everything is green cannot show you that the checks run at all.

**It cannot rot quietly again.** `demo_coverage()` checks the real database and
the result is shown on the screen where the demo is loaded: add a module, add its
line to `demo_modules_expected()`, and the screen says "no demo data" in plain
sight until it has some. Same fix as `module_groups()`, the new-module guess and
the smoke crawl's path list — derive it from the real thing rather than trusting
somebody to remember.

**Removal is complete and reversible.** Load → remove → load again is a cycle,
asserted by test. Removal also switches the client portal back **off**: leaving a
client-facing door open after "remove demo data" is exactly the kind of thing
nobody checks.

**Still open on the demo:**

- [ ] Evidence photographs are 1-pixel placeholders so the register loads fast.
      Real EXIF reading and image compression need a real photograph uploaded by
      hand.
- [ ] Nothing is e-mailed; the mail log records what would have been sent.
- [ ] No GST/e-invoice data, because that module does not exist yet (roadmap 5.2).

## ✅ PHASE 5.1 — the client portal (July 2026)

`lib/portal.php`, client screens under `/portal`, staff screen `/portal-users`.

**The design rule, and it is not a preference.** A client user is **not** a
staff user with fewer permissions. That is how client portals leak, every time:
somebody adds a screen, forgets one gate, and a competitor reads another
competitor's inspection reports. So:

- Client identity lives in **its own table** (`client_users`) and **its own
  session key** (`$_SESSION['cuid']`). `current_user()` can never return a
  client, so no staff screen is reachable by one even if a route is added
  carelessly a year from now. The browser test drives a signed-in client at
  `/`, `/calls`, `/documents`, `/portal-users` and `/data-control` and checks
  each one throws them at the **staff** sign-in.
- Every read takes the company **from the session** and puts it **in the WHERE
  clause** — never checked afterwards. Another company's id does not come back
  at all, so the by-id screens answer **404, not 403**: "it does not exist to
  you" rather than "it exists and you may not have it."
- The queries **do not SELECT** the columns a client must not see. No cost, no
  credit, no margin, no salary, no internal note. What is never loaded cannot
  leak through a template written in a hurry.

**What a client sees:** their calls and where each has got to; the visits and
who is coming; the reports we have **issued**, downloadable as the same PDF the
office sends; their invoices and what is outstanding; a form to ask for the next
inspection. **A draft is never shown** — a draft is not a report, and showing
one is how a finding gets quoted back before it is final.

**Passwords.** We never choose one and never e-mail one. An invitation is a
one-time link that lasts seven days; the invitee sets their own. So there is no
client password anybody here has ever known, lost, or can be blamed for. The
link is burnt on use, and a second visit says so on the way *in* rather than
after somebody has chosen a password and typed it twice.

**Off by default.** Until an administrator switches it on, every portal address
answers a bare 404 with no shell and no hint that a portal exists. Switched on
is still not open: nobody can reach it until they are invited.

**A request is a request, not a booking.** A client may ask for an inspection;
they cannot put work into our register. A coordinator reads it, decides, and
raises the call — because scope, price and who goes are ours. Declining is
refused unless a reason is given: a request declined in silence is why clients
stop using a portal and go back to telephoning.

**Still open on the portal:**

- [ ] Accepting a request does not yet **create the call** for you — it records
      the decision and a coordinator raises it by hand. One-click conversion is
      the obvious next step and was left out deliberately until the request
      form has been used in anger and we know which fields are worth carrying.
- [ ] No e-mail to the client when a report is issued. They see it on the
      portal; they are not told to look. Wants the notification work.
- [ ] Contacts on the client record are not linked to portal accounts
      (`client_users.contact_id` exists and is unused). Inviting is by typing
      the address.

## ✅ PHASE 4 — the trust layer (July 2026)

`lib/trust.php`, screens `/evidence-review` and the public `/verify`.

**The owner corrected the design before it was built, and the correction is the
whole shape of the module:**

> *"Geotagging on the report may not be correct, because practically many
> inspectors will complete the activity on site and then go to another place or
> home to prepare the report."*

Exactly so. An engineer photographs a weld at 11:40 at the plant, drives home,
and writes the report at nine that evening. Any system that takes the location
of the *report* — or even of the *upload* — and shows it as where the inspection
happened is not adding trust, it is manufacturing a lie a client can catch.

**So three facts are kept strictly apart, and none may stand in for another:**

1. **Where the camera was when the shutter fired.** Read out of the
   photograph's own EXIF data — written by the phone at the moment of capture,
   travelling inside the file, surviving the drive home. **This is the
   evidence.**
2. **Where the browser was at upload.** Recorded, but labelled as exactly that
   everywhere it appears, because it is usually a kitchen table.
3. **Where the report was written.** Not recorded at all. It has no evidential
   meaning and collecting it would only tempt somebody to show it.

**And a fourth, deliberate act — the site check-in.** One tap, on site, at the
time, now with **arrival / departure** and an optional photograph. It is the
fallback for the many corporate phones that strip EXIF, and it produces the line
a client actually wants: *"on site 09:12 to 14:40."*

**A late upload is never flagged.** Uploading in the evening is the normal
working pattern; flagging it would train everybody to ignore the flags, which is
how a review queue dies. There is a test asserting no such flag exists.

**Flagged, never blocked** — a blocked photograph is a photograph that never
gets taken, and a fake-GPS app defeats blocking anyway. Seven signals, four of
them serious enough to want a person: taken far from the check-in, impossible
travel between two captures, sub-metre "accuracy" (the signature of a fake-GPS
app), coordinates too round to be a real fix.

**The hash chain (4.4).** Every evidence item is hashed into an append-only
chain at capture; each entry hashes the one before it, so altering or removing
anything breaks every hash after it. sha256, no blockchain, no third party —
a thing that can be explained to an assessor in one sentence.

**The client-verifiable report (4.5).** A code in four readable groups, printed
on the report, checked at **`/verify` without an account and without asking
you.** It says: genuine or not, unaltered or not, how much of the evidence was
located on site, and whether the engineer was authorised. It shows **no client
name, no findings, no prices** — a verification page that leaked those would
breach the confidentiality the report is issued under.

*Verified:* 83 rule tests, 34 browser checks, each run twice. Lint green
(168 files), 104 screens.

### What a browser can and cannot do — the owner asked, and the answer is mixed
**No Android app is needed.** `capture="environment"` opens the phone's rear
camera directly from the web page, `navigator.geolocation` gives the fix, and
the app is already installable to the home screen. The browser test drives a
real Chromium with a real geolocation permission and a real JPEG, so this is
demonstrated rather than asserted.

**What a browser genuinely cannot do, said plainly:**
- **Mobile network time is not available to any web page.** Nor, on a normal
  Android device, to an app — "network time" in most apps is just an NTP call.
  This is why the server stamps every check-in and every upload, and why the
  device clock is recorded only to be *compared*, never trusted.
- **Mock-location detection.** Native Android has `isFromMockProvider()`; the
  web has nothing equivalent. Hence heuristics, and hence *flagged not blocked*.

### Four real bugs, all found by the tests rather than by reading
- **Compression was destroying every location.** `idems_compress_image()`
  re-encodes through GD, which writes a clean JPEG with **no EXIF** — and the
  location was being read two lines *after* it. Every photograph would have
  come out saying "not in the photograph" for ever. Both upload paths now read
  before compressing, and a test asserts the ordering in each.
- **Two verification codes for one report.** The report screen calls
  `verify_code_for()` once for the printed code and again inside `verify_url()`,
  both with the same in-memory row — so the second call minted a *second* code
  and overwrote the first. The code on the paper and the code in the link
  disagreed, and only one worked.
- **A fatal error on the public verification page.** `auth_live()` judges one
  authorisation row; it was being called with a person's id. That put a stack
  trace on the one page in this application that strangers are invited to open.
  Fixed, and the whole lookup is now wrapped so it can never happen again — a
  failure reads as "we cannot answer right now", not as a crash.
- **The boot probe did not know about the new columns.** `site_visits.kind` and
  the check-in photograph would never have appeared on a live database. This is
  the standing rule of this codebase and it caught me anyway.

### And one false alarm in last week's work
The §7.11 integrity check "uploaded files still decode" was reporting **correct**
evidence as corrupt: `report_files` stores a `data:` URL, not bare base64, and
the check decoded it as though it were bare. A screen that cries wolf stops
being read, so this mattered more than it looks.

## ✅ ROADMAP 3.6 — control of data & information (§7.11) (July 2026)
## 🏁 The ISO/IEC 17020:2026 transition pack is now complete (3.1 – 3.8)

`lib/datacontrol.php`, screen `/data-control`. **New in the 2026 edition, with
no 2012 equivalent.** It arrived because inspection bodies stopped keeping
records on paper and nobody had written down what that means. It asks four
questions a body running on software cannot answer by pointing at a policy — and
three of the four this application can genuinely *produce* rather than store.

**1 · Was the software validated — for what you use it for, and again when it
changed?** This is the one part that cannot be automated, and pretending
otherwise would be the worst thing the module could do. So instead it says,
loudly, when the version you are **running** has no record against it. That is
the finding a body actually collects: it validated version one in 2024 and has
upgraded four times since. The register covers the spreadsheet somebody built
and the report template with a formula in it too, because the clause does. A
record with no stated **purpose** is refused — "fit for purpose" means nothing
without the purpose. A FAILED validation stays on the register, because a
failure is the record.

**2 · Can you show the data is intact?** Seventeen checks that **run**, against
the live database, producing a dated pass/fail: every deputation belongs to a
real call, every bill to a real deputation, no report is marked issued with
nobody named, no corrective action is closed as effective without the check
behind it, no complaint is closed without the complainant being told, no
identity document is held past its retention date, uploaded files still decode,
no account carries a permission that no longer exists. You can press the button
in front of an assessor. Each line says **why** it matters, because that is what
gets read. A failing run is recorded exactly like a passing one — a check that
only gets written down when it passes is not a check.

**3 · Who can get at it?** Read from the permission engine, not from an org
chart: eight powers as columns, dormant accounts, who has two-step on. Nobody
types it, so nobody can type it wrong.

**4 · When it broke, what happened to the data?** The failure log **writes
itself** — `ops_fatal()` records an entry before it even renders the error page,
so a fatal that breaks the rendering still leaves a record. Same fault twice in
an hour is one entry, and the same fault at a different line number is still the
same fault. An entry **cannot be resolved** until it says whether data or
results were affected; answering "yes" then demands a corrective action, which
can be raised from the entry carrying its own words across.

*Verified:* 77 rule tests, 44 browser checks, each run twice from a dirty
database. Lint green (165 files), 99 screens.

### Three modules were enforcing rules nobody could see
Asserting "the whole 2026 pack is on one screen" failed — and it was right to.
**§4.1 impartiality, §6.1 competence and §6.2 equipment had no line on the
compliance screen at all.** Each enforces its rules perfectly well on its own
screen, which is where the work happens; but the compliance screen is what a
director opens before an assessment, and three of the seven accreditation
modules simply were not on it. Enforced somewhere is not the same as visible
here. All three now have measured lines.

### The smoke crawl was calling a working screen broken
It scans page text for the wording of PHP errors. The failure log's whole job is
to display the wording of faults the application recorded about itself — so the
crawl read a page that was working perfectly as broken. Three screens that
legitimately quote error text (`/data-control`, `/audit-log`, `/incidents`) now
skip the prose scan only; they are still fetched and still fail on HTTP 500 or a
JavaScript error.

### Where the transition pack stands
| Item | Clause | State |
|---|---|---|
| 3.1 Equipment & calibration | §6.2 | ✅ |
| 3.2a Competence & authorisation | §6.1 | ✅ |
| 3.3 Impartiality & conflicts | §4.1 | ✅ |
| 3.4 Complaints & appeals | §7.5, §7.6 | ✅ |
| 3.5 CAPA · internal audit · management review | §8.7–8.9 | ✅ |
| 3.6 Control of data & information | §7.11 | ✅ |
| 3.7 Type A / Type non-A | 2026 model change | ✅ |
| 3.8 Identity documents | DPDP Act 2023 | ✅ |

**"17020:2026 transition-ready" can now be said without an asterisk.** The
deadline is 27 March 2029 and the edition published 27 March 2026 — the selling
window is now, not eventually.

## ✅ ROADMAP 3.5 — CAPA, internal audit, management review (§8.7–8.9) (July 2026)

`lib/capa.php` and `lib/audits.php`. Six screens. This is the register behind the
corrective-action reference that complaints (3.4) already demanded.

**§8.7 — corrective actions.** Almost every body has a spreadsheet for this, and
almost every one loses the same two marks:

1. **The effectiveness review never happens.** Somebody does the action, ticks
   "closed", and nobody goes back to see whether the problem stopped. Here a
   corrective action **cannot be closed** until somebody has gone back, on a
   date, and said whether it worked — and if it did **not** work, closing it as
   done is refused outright. The honest ending is offered instead: close it as
   ineffective and raise a successor that carries the history forward. A body
   that can show that is a body that is actually improving.
2. **Nobody asks whether it happened elsewhere.** §8.7.2 d). One line in the
   standard, the most-missed one in practice, because it is the only part that
   costs real thinking. It is a required answer, with a note. "No" is fine —
   as long as somebody actually looked.

Also required before closing: the cause, **how the cause was worked out** (an
assessor asks that next, every time), and the plan.

**§8.8 — internal audits.** Two things:

- **An auditor may not audit their own work.** Named on the plan, checked,
  refused. It is the commonest way a small body's internal audit becomes
  worthless, and it takes one line to prevent.
- **Clause coverage.** The question is not "did you audit" but "did you audit
  *all* of it". A board shows every clause and when it was last covered; a
  clause nothing has looked at inside the cycle shows red. An audit older than
  the cycle stops counting.
- A **nonconformity with no corrective action blocks the audit's close**, and
  raising one carries the audit's own words across rather than retyping them.

**§8.9 — management review.** The clause most often written the night before an
assessment. §8.9.2 lists fifteen required inputs, and **fourteen of them are
questions this application already knows the answer to**: how many complaints,
how many upheld, how many corrective actions open, how many carried out and
never checked, how much equipment is out of calibration, whose ticket lapsed,
which impartiality threats are undecided, how the work volume moved. Those are
**measured** and put in front of the chair, who writes what they mean. The one
that cannot be measured says so rather than inventing a figure.

A review is **not complete** until every input has been addressed and at least
one decision has come out of it. §8.9.3 asks for outputs; fifteen inputs and no
decisions is minutes of a meeting.

Deliberately not done: no scoring, no automatic root cause, no "AI-suggested"
corrective action. A root cause that software guessed is a root cause nobody
owns, and an assessor finds that out in one question.

*Verified:* 84 rule tests, 48 browser checks, each run twice from a dirty
database. Lint green (162 files), 98 screens.

### A third hand-maintained list found to have gone stale
`tools/smoke.js` — the crawl that opens every screen — worked from a fixed list
of paths. **Seven modules shipped since it was written and not one of them was
ever opened by it**: equipment, competence, impartiality, identity, complaints,
corrective actions, internal audits. The crawl was reporting "83 screens, all
clean" while never touching them. It now unions the fixed list with everything
on the administrator's sidebar, so a module with a menu entry is crawled the
moment it ships — 98 screens, and the ten it had been missing all render.

That is the third list of this kind in two days (`module_groups()`,
`NEW_MODULES`, now this). The pattern is worth naming: **any list that has to be
extended by hand when a module is added will eventually stop being extended.**
Each of the three now derives itself from the real catalogue.

## ✅ ROADMAP 3.4 — complaints & appeals (§7.5, §7.6) (July 2026)

`lib/complaints.php`, `complaints` · `complaint_events`, screens `/complaints`
and `/complaint`, plus **one page that opens without signing in**.

Every inspection body says it takes complaints seriously. What an assessor asks
for is a **list**: how many, when each arrived, when each was acknowledged, who
decided it, whether that person had anything to do with the inspection being
complained about, and when the complainant was told. A body that cannot produce
that list has a nonconformity however well it handled the complaint in the room.

**Three rules the software will not let you break:**

1. **§7.5.4 — the decider must not have been involved.** The engineer on the
   work, whoever prepared the report, whoever finalised it and whoever approved
   it are all refused, by name, with the clause quoted. The coordinator who
   allocated the deputation is deliberately **not** counted — allocating work is
   not carrying out an inspection, and counting it would leave a small branch
   with nobody left who is allowed to decide anything.
2. **§7.6 — an appeal is decided by somebody else again.** Whoever decided the
   original cannot decide the appeal against it. Otherwise it is not an appeal,
   it is the same person being asked twice. An appeal also *inherits* what the
   original was about, so rule 1 still bites on it.
3. **Nothing closes until the complainant has been told.** The most common
   finding in this clause is a register full of resolved complaints where nobody
   wrote back. Closing refuses, and says so. The one honest exception —
   anonymous with no way to reach them — is stated rather than silently skipped.

Plus: an **upheld** complaint cannot close with nothing changed (a corrective
action reference is required — 3.5 builds the register itself), turning a
complaint away as "not ours" requires a reason on the record, and a decision
requires the reasons written out, because that paragraph is what goes to the
complainant.

**The published description (§7.5.1)** is at **`/complaints-policy`** and opens
**without signing in** — a page behind a password is not "available to any
interested party". It ships filled in, because blank is the finding, and the
register says out loud when the wording is still ours rather than the company's.

**No public complaint form.** An open submission form on the internet needs rate
limiting, spam handling and abuse review to be worth having, and a half-built
one is worse than a published address. Deferred deliberately, said plainly.

*Verified:* 69 rule tests, 41 browser checks, each run twice from a dirty
database. Lint green (152 files), 83 screens.

### Two real bugs found while wiring this up

- **The access editor could not reach five modules.** It renders
  `module_groups()`, not `ACCESS_MODULES` — and equipment, competence,
  impartiality, identity and complaints were in the catalogue but in no group.
  An administrator literally could not grant or deny any of them. Now grouped
  under *Accreditation & compliance*, **and** the function ends with a catch-all
  so a module can never go missing from that screen again.
- **A module added later looked identical to one that was denied.** A saved
  permission set is a list of what was ticked; a module added afterwards is
  simply absent, which is indistinguishable from deliberately unticked. The old
  answer was a hand-maintained `NEW_MODULES` list that nobody remembered to
  extend — so the last five modules were invisible to any install that had ever
  saved Roles & access. Saving now **stamps the module catalogue as it stood at
  that moment**, so "new" is a fact rather than a guess, and unticking a module
  is no longer silently undone. The old list survives only as the one-off
  catch-up for installs that saved before the stamp existed.

## ✅ Identity documents — built, with the guardrails, not after them (July 2026)

`lib/identity.php`, `person_documents` · `person_document_access`, screen
`/identity`. The owner's instruction was four words: *"That is required for
identity verification."* That is a **purpose**, and under the DPDP Act 2023 a
purpose is what makes holding somebody's passport lawful. Everything here
follows from taking that seriously rather than treating it as a form of words.

**Why build it at all.** An engineer cannot enter a refinery, a port, a shipyard
or a defence yard without a gate pass, and a gate pass is issued against a
document. So the coordinator ends up holding a scan whether the software helps
or not — today it sits in a mailbox and on a laptop, copied to whoever asked
last. *That* is the real risk, and it is worse than holding it here.

**The four guardrails, all shipped in the same commit:**

1. **The purpose is on the screen**, not in a policy nobody opens — and it is
   copied onto the row at the moment of filing. Changing the wording later does
   **not** rewrite what was already agreed with somebody. (Tested.)
2. **A separate permission.** `person.iddoc.view` and `person.iddoc.manage`, in
   **no role's defaults**. A Business Director's blanket "every module" grant is
   explicitly stripped of `identity` — running operations is not a reason to
   read a colleague's passport. Only the master administrator gets through
   without being granted it, and every look they take is logged like anyone's.
3. **A retention limit that actually runs.** Default 730 days past the
   document's own expiry, counted from the *later* of expiry and filing so a
   passport good for eight more years is not binned next year. The nightly job
   sweeps it. Redaction, not deletion: the file and the number go, the row
   stays — it is the evidence that identity *was* checked.
4. **A record of who looked.** Every open, download, reveal of a full number,
   and every copy sent out to a plant's security desk. That last one is the
   disclosure that normally lives only in somebody's sent items.

**Two deliberate refusals:**

- **The number is masked** (`•••• 4517`) unless somebody gives a reason. The
  reason is kept, and the number is shown once — via the session, never the
  URL, so a reload does not show it again.
- **No allocation is blocked on this.** ISO/IEC 17020 does not ask for it, and a
  body that cannot depute an engineer because a visa scan is missing has built a
  compliance problem, not solved one. The deputation screen **tells** the
  coordinator, which is the useful half.

Joined to the machinery that already existed: the subject-access export carries
the documents *and the access log* (not the bytes — the person already has their
own passport, and one more loose copy helps nobody); erasing a person redacts
every document they hold; and the compliance screen gained three measured lines
— purpose, retention, and who can look.

*Verified:* 61 rule tests (twice, from a dirty database), 27 browser checks,
lint green (147 files), 83 screens.

### Also this round
- **`auth_run_maintenance()` was never called.** Written in 3.2a, wired to
  nothing — so authorisations would never have expired or auto-suspended on a
  live system. Now runs nightly beside the retention sweep. Found only because
  I went looking for somewhere to hang the new sweep.
- **`b_bills.js` had never actually reset anything.** Its reset script ran
  against the *default* SQLite path while the server under test ran against
  another, so eighteen bills had piled up on one deputation and the suite was
  passing for the wrong reason. Now points at the served database.
- **`b_imp.js` made idempotent** the same way `b_auth.js` was, via
  `reset_imp.php`. Third time this class of bug has appeared; each suite is now
  checked by running it twice in a row before it is believed.

## ✅ ROADMAP 3.3 + 3.7 — impartiality, and Type A / non-A (§4.1) (July 2026)

`lib/impartiality.php`. **The clause a third-party body exists to satisfy.**
Everything else in the standard is about doing the work properly; §4.1 is about
being *entitled to do it at all*. The app had nothing.

**The 2026 edition made this heavier, not lighter** — threats now explicitly
include those from **organisational relationships**, **outsourcing** and
**financial pressure**. The last is the one bodies find hardest to write down
honestly, because it means *"this client is 40% of the branch's revenue and
everybody knows it"*. All three are on the list.

Three things it makes true:

1. **A declaration exists, per person, with a date** — not a policy on a wall.
   One with no end date is treated as running a year, because a statement made
   once and never renewed is not a current statement, and saying so is cheaper
   than arguing with an assessor about it.
2. **A declared threat is a record, not a conversation** — who it involves,
   which kind, what was decided, by whom, and when it is reviewed again.
   **Clearing one without saying how is refused**: that is precisely the finding
   §4.1 exists to prevent.
3. **An uncleared threat stops the allocation.** A register nobody acts on is
   worse than none — it proves the body knew. A threat with *no party named*
   counts against every client, which is the point of raising it that way.

Unlike the competence gates this one is **not opt-in**: a threat only exists on
the register because somebody deliberately put it there, so the body already
knows. There is also a **per-deputation confirmation** kept on the job, because
that is the record an assessor pulls when they pick one inspection at random.

**3.7 · Type A / Type non-A** — the 2026 model change, replacing A/B/C. On the
organisation record, with rubbish falling back to A rather than breaking.

*Verified:* 34 rule tests, 10 browser checks, lint green (146 files), 83 screens.

### Also this round
- Made `b_auth.js` reset its own state first. It had been passing only on a
  clean database — the same lesson the bills suite taught, relearned. A test
  that cannot run twice proves nothing.

### Identity documents — purpose now stated
The owner has confirmed passport / visa / ID holding is for **site-access
identity verification**, which is a lawful purpose under the DPDP Act.
**Built** — see the entry at the top of this file. It shipped with its
guardrails in the same commit, not after them.

## ✅ ROADMAP 3.2a — the competence & authorisation spine (§6.1) (July 2026)

Built into `lib/competence.php` beside the certificate gate, because "is this
person allowed" should have one home. Tables `authorisations`,
`witness_assessments`, `qualifications`.

**The distinction the whole module rests on:**

> a **qualification** is what somebody *has* — CSWIP 3.1, valid to 2027
> an **authorisation** is what we *permit* — may sign final inspections for this
> client, to March, at this level

§6.1 asks for the second. The app only had the first.

**Four decisions, all written into the file because they will be argued about:**

1. **Enforcement is opt-in and default OFF.** Switching it on for an existing
   customer with an empty matrix would make every allocation fail on the same
   afternoon. The screen **refuses to switch it on** while nobody is authorised,
   and when it is switched on it says how many people it has just stopped.
2. **Scope reuses the app's own masters** — type of inspection (37 configurable
   values already), activity code, client. Inventing parallel industry / asset /
   activity taxonomies would mean three more lists to keep true and no link to
   the work actually being scheduled.
3. **Suspended, never deleted.** A withdrawn permission is evidence of a
   decision, on a date, by somebody. Changing a status **requires a reason** —
   refused without one.
4. **Levels are a list, not an enum** — Trainee through Technical Authority
   ships as a starting point and is editable.

**It maintains itself.** From cron: an authorisation past its end date becomes
EXPIRED; one belonging to somebody whose *required* certificate has lapsed is
SUSPENDED automatically, with the certificate named in the reason. A witnessed
assessment recorded as anything other than "competent" **suspends that person's
authorisations** — which is the point of watching somebody work rather than
filing a form about it.

**Audit-readiness** is the matrix screen itself: people, how many hold a live
authorisation, how many have a lapsed required certificate, how many are due a
witnessed check — the four questions in the order an assessor asks them.

*Verified:* 26 rule tests (including that a person with no authorisation is
**still allocatable while enforcement is off** — the property that stops this
breaking every existing install), 13 browser checks, lint green (144 files),
83 screens, and all 12 other suites still pass.

### Still to come — 3.2b, from the owner's fuller specification
- [ ] Competency scoring, learning management, technical knowledge library.
- [ ] Deliberately **not** built as "AI": these are rules engines. Calling them
      AI invites an assessor to ask for the AI validation record that
      ISO/IEC 17020:2026 now requires for AI tools. Same behaviour, no exposure.
- [x] Personal data in the fuller spec (passport, visa, medical fitness, police
      verification) — **done**, with the lawful basis stated on the screen, a
      retention limit that runs nightly, and a separate permission. See the
      identity-documents entry at the top of this file.
- [ ] Risk category / grading of a person is still not built, and should not be
      until somebody can say what it would be *used for*. A label on a colleague
      with no decision attached to it is data held for no purpose.

## ✅ ROADMAP 3.1 — equipment & calibration (ISO/IEC 17020 §6.2) (July 2026)

`lib/equipment.php`, `equipment` · `equipment_calibrations` · `report_equipment`.

Before this the app had **no equipment at all** — yet a report could print the
standard sentence *"the measuring and test equipment used was verified to be
within its valid calibration period"* with nothing behind it. An assessor asks
for the certificate.

Four things it now makes true:

1. **Every instrument is on a register** with the identity somebody reads off
   the label: code, serial, make/model, range, accuracy, owning branch, who
   holds it, ownership (ours / hired / client's), and state.
2. **Calibration is a HISTORY, not a field.** This is the design decision that
   matters. "Valid to 12 Aug" says nothing about the instrument's state on
   3 March. `equipment_calibration_on($id, $date)` returns *the certificate that
   was in force on that date* — so a report is judged against the certificate
   live when the work was done, and re-calibrating an instrument next year
   cannot quietly rewrite what an issued report rested on. The link row also
   stamps **which certificate** was relied on.
3. **The instrument is named on the report** that used it. A register nothing
   links to is paperwork; the link is what makes it evidence.
4. **A report will not issue** naming an instrument that was out of calibration
   on the inspection date — checked in `document-finalize` on the server, and
   **not overridable**. Unlike a lapsed personnel ticket there is no honest
   reading under which a measurement taken with an uncalibrated instrument is
   still valid. An out-of-calibration instrument *can* still be recorded, so
   the record stays truthful about what was actually used.

Also: state beats calibration (quarantined / under repair / retired is unusable
however good the certificate), a FAIL certificate is not a calibration, and
30-day reminders run from `cron.php` beside the personnel ones.

**A bug worth remembering.** The register came back permanently empty in
testing. The cause was `status <> 'RETIRED'` — ambiguous, because `inspectors`
also has a `status` column and the query joins it. What *hid* it was my own
`catch (Throwable) { return []; }`, meant to tolerate a pre-migration database.
A broken query and an empty table are not the same thing. Every such catch in
this file now re-throws unless the message really is "no such table".

*Verified:* 26 rule tests (including the certificate-on-the-day case both ways),
9 browser checks adding an instrument, filing a certificate and quarantining it,
lint green (143 files), 83 screens render.

## ✅ ROADMAP PHASE 2 — one product, licensed by module (July 2026)

The answer to *"can we separate every module and provide them separately?"*,
delivered the way that gets the commercial outcome without the refactor.

- [x] **2.1 · Module licensing.** `lib/licence.php`. Six saleable modules —
      Operations, Administration, Sales & CRM, Inspection reporting, Money,
      People & hiring. Switch one off and its menu group disappears, its routes
      refuse, and its dashboard panels drop out.
      **The whole thing is one hook inside `can()`**, because every screen and
      every menu item in this app already asks `can('mod.<x>.view')`. That is
      why this took days instead of the 6–10 weeks a real code split would have
      cost for nothing a customer can see.
      Four decisions worth keeping:
      · the licence is checked **before** the Master Admin bypass — a module the
        customer has not bought is not a permissions question, and an admin who
        could still open it would be looking at something unsupported;
      · only `mod.*` permissions can be licensed away — `data.salary` is a role
        question, not a contract question, and licensing it here would silently
        change who sees salaries;
      · **Operations and Administration cannot be switched off**, and naming
        them is ignored rather than obeyed — an inspection system without calls,
        deputations, masters and users is not a smaller product;
      · an access module nobody has claimed stays **visible**, so a module added
        next year cannot vanish because somebody forgot to license it.
      Settable at Settings → Modules (Master Admin only), or pinned from outside
      the database with `MODULES_OFF` for an on-premise install. 27 rule tests
      plus 16 browser checks switching Sales and Money off and back on.
- [x] **2.2 · Pre-flight server check** — Settings → Server check. PHP version,
      every extension, the PDO driver actually configured, php.ini upload limits
      against the app's own limit, and HTTPS. Required items are separated from
      optional ones, and an optional miss says **what feature you lose** rather
      than showing an error, because the app genuinely keeps working.
- [x] **2.3 · A version.** `APP_VERSION` (`2026.07.1`, date-based so it means
      something read aloud on a support call). On the login screen — which used
      to say a hardcoded "v1.0" — and on the server check.
- [x] **2.4 · Release packaging** — `tools/release.sh` builds a dated, versioned
      `.tar.gz` and `.zip` with a `.sha256`, from tracked files only.
      **It refuses to build from a dirty working tree.** That guard is not
      theoretical: the first run of this script produced a package that died on
      its first page load, because three new files were not yet committed and
      `git ls-files` correctly left them out. A customer would have received a
      broken product.

*Verified:* lint green (140 files), 83 screens render, t_lic 27, t_comp 22,
t_bills 31, t_sched 39, t_rev 24, t_profit 22, t_cost 20, t_mis 45, t_hours 7,
t_session 12, plus 15 + 16 + 13 browser checks.

## ✅ ROADMAP PHASE 1 — the broken flows are fixed (July 2026)

Six items from the gap review, all verified in a real browser as the role that
suffers from them. See `docs/ROADMAP.md` for the phases that follow.

- [x] **B1 — the director was offered "Settings" and then refused it.** The menu
      tested `mod.settings.view`; the screen requires `settings.manage`. Two
      different permissions. The menu now tests both, and the gate on the screen
      is unchanged — so nothing was loosened to make a link work.
- [x] **B3 — the engineer's two-item menu.** They already held
      `mod.idems.view/edit`; the nav simply branched past it. The person who
      writes every report now has the report register, "write a report" and the
      endorsement register on their menu.
- [x] **B2 — the report engine was unreachable from a deputation** whenever
      deliverables had not been ticked on the call, because the whole "Reports
      owed" panel was inside that condition. It now always renders, lists
      reports written under a format nobody agreed, and offers "write one
      anyway". Checked on 8 of 8 deputations.
- [x] **C5 — utilisation on the director's dashboard.** Billable engineer-days
      over available engineer-days, FY to date. Deliberately computed by a new
      shared `mis_utilisation()` that the management dashboard uses too, so the
      two screens can never quote different numbers for the same word.
- [x] **B4 — sales → operations was pull-only.** An accepted quotation now
      offers **"Raise an inspection call"**, and the call opens with the client,
      the contract number, the business unit and the quotation link already
      filled. Only on ACCEPTED, verified both ways.
- [x] **A4(a) — an engineer with a lapsed certificate could still be deputed.**
      New `lib/competence.php`. A certificate can be marked **required**; a
      required one that had already lapsed *on the date the work happens* stops
      the allocation. Two deliberate design calls, both explained in the file:
      only *required* certificates gate (a lapsed first-aid card must not stop a
      welding inspection, or the desk stops trusting the system), and a manager
      may override **with a reason recorded on the deputation** (refusing
      outright pushes people to back-date the certificate, which destroys the
      very record an assessor reads). 22 tests.

*Verified:* lint green (137 files), 81 screens render, 15 browser checks across
five roles, and t_comp 22, t_bills 31, t_sched 39, t_rev 24, t_profit 22,
t_cost 20, t_mis 45, t_hours 7, t_session 12 all pass.

## 🖥️ INSTALLING ON A CLIENT'S OWN SERVER (July 2026)

Question: can the whole app be handed to a client to run on their own server?
**Yes — it already can, and it was proved rather than assumed.** A pristine copy
of the tracked files (2.8 MB, 149 files, no database) was unpacked and opened:
the first page load built **81 tables, the admin account and 64 master lists
with nothing configured**, took 3.7 s, and settled to 3 ms from the second load
on. All 73 screens then rendered on that empty install. See **`INSTALL.md`**.

Why it is this easy — and these are properties to protect, not accidents:

- **Single-tenant by design.** There is no `tenant_id` anywhere. One database is
  one company, which is exactly the shape an on-premise install needs.
- **No build step.** No Composer, no Node, no bundler. PHP files and one CSS.
- **Self-migrating.** `boot()` is the installer *and* the upgrader.
- **Optional extensions already degrade.** `zip`, `gd` and `curl` are each
  checked with `class_exists`/`function_exists` and lose one feature with a
  plain message instead of taking the app down.
- **MySQL or SQLite**, both first-class. A pilot needs no database server.

### Two real defects found on the way, and fixed

- [x] **Uploads could not survive on MySQL.** Files are held base64 in the row.
      Base64 costs a third more, and MEDIUMTEXT stops at 16,777,215 bytes — so a
      file at the 12 MB upload limit encodes to 16,777,216 characters and misses
      **by one byte**. Outside strict mode MySQL *truncates* rather than
      complains: the file is stored broken and nobody finds out until somebody
      opens it months later. SQLite has no such ceiling, which is exactly why
      months of testing never showed it — **the bug only existed where
      production runs.** The setting also allows up to 64 MB, so anyone raising
      it broke every upload silently. Now: seven upload columns declared
      `LONGTEXT`, `widen_file_columns()` migrates existing MySQL installs, and
      `file_columns_pending()` is asserted in the boot probe — a column that is
      merely too *narrow* is not a *missing* column, so nothing else would ever
      have noticed. Verified with a 12 MB round-trip, byte-for-byte.
      *The `ALTER … MODIFY` is untested here — no MySQL in the build box — but
      it mirrors the identical statement already running in `access.php:313`.*
- [x] **The failure message named the hosting provider.** A client with a wrong
      password was told to look in "MilesWeb → Databases", which is meaningless
      on their own server. All hosting-specific wording is now generic (config
      hint, the two DPDP compliance checks, the cron header).

### Still to do before this is a sellable on-premise product

- [ ] **A pre-flight page** — "PHP 8.1 ✓, pdo_mysql ✓, zip ✗ (Word export off)"
      before install rather than after a failure. The single highest-value
      addition; today the requirements are only in `INSTALL.md`.
- [ ] **A version number in the app**, and a stated upgrade path. Right now
      nobody can tell which build a client is running.
- [ ] **A release artifact** — a versioned, dated zip with a checksum, rather
      than "copy what git has".
- [ ] **Licensing.** `SEAT_LIMIT` is read from the environment and displayed,
      but nothing enforces it. Decide whether on-premise is licensed at all.
- [ ] **A web setup screen** so `config.php` need not be hand-edited (optional —
      a client's IT team will not mind, a small client will).


## 🔍 GAP REVIEW — the app used as every role, July 2026

Method: a demo database was built and the app was driven in a real browser as
**inspection engineer → coordinator → asst. manager → operation manager →
branch manager → business unit head → business director → finance → admin**,
then measured against **ISO/IEC 17020** (the standard a third-party inspection
body is accredited to) and against what competing inspection-management
products ship. Nothing below is a guess; each item was reproduced.

**What is genuinely strong** — 191 routes, 74 tables; the sales→call→deputation
→schedule→close→bills→voucher→profit chain holds end to end; access control is
sound (an engineer probing 16 management URLs was bounced home from every one);
the phone experience is real (no horizontal scroll on any screen tested, card
layouts, bottom tab bar, camera capture, GPS stamping, offline draft queue).

### A. Accreditation-blocking (only if ISO/IEC 17020 matters to you)

- [ ] **A1 · Equipment & calibration register (§6.2).** There is no register of
      measuring and test equipment — gauges, UT/DFT meters, hardness testers —
      with ID, owner, calibration certificate and due date. Today a report can
      print the standard phrase *"equipment was verified to be within its valid
      calibration period"* with nothing behind it. An assessor will ask for the
      certificate. **Also needed:** the report should refuse to finalise naming
      an instrument whose calibration has lapsed.
- [ ] **A2 · Impartiality / conflict-of-interest declaration (§4.1).** This is
      the defining clause for a Type A body and there is nothing in the app. A
      per-deputation declaration by the engineer ("I have no interest in this
      vendor / have not worked for them"), held with the record, plus a register
      of declared threats and how they were resolved.
- [ ] **A3 · Complaints & appeals register (§7.5, §7.6).** Only a DPDP grievance
      *contact name* exists in Settings. Accreditation needs a logged complaint,
      acknowledgement, investigation, outcome and closure — and appeals decided
      by people who were not involved in the original inspection.
- [ ] **A4 · Competence & authorisation, and enforce it (§6.1).** Certificates
      are held (`inspector_certs`) and an e-mail goes out 30 days before expiry,
      but **an engineer whose certificate has expired can still be allocated** —
      nothing checks it at allocation. Missing entirely: which inspection types
      / methods / clients each person is *authorised* for, the witnessed-
      inspection record, and the periodic monitoring §6.1.8 requires.
- [ ] **A5 · Internal audit, management review, corrective action (§8.5–8.8).**
      No register for any of the three. `security_incidents` covers security
      only. A CAPA log with root cause, action, owner, due date and
      effectiveness check is the usual shape.

### B. Flows that are wrong or dead-ended (found by using it)

- [ ] **B1 · The Business Director is offered "Settings" and then refused it.**
      The menu item renders; opening it says *"Only admins can change
      settings."* Either hide it for that role or grant it. A menu item that
      refuses reads as a broken app. *(Smallest fix on this list.)*
- [ ] **B2 · Two disconnected reporting worlds.** The Close screen asks for a
      *"report link"* — a URL to a file kept somewhere else — while a full
      report engine (templates, sections, approvals, signatures, IRN, evidence)
      sits alongside it. The bridge is the "Reports owed on this deputation"
      panel, but that panel **only renders when deliverables were ticked on the
      call**. Miss that tick and the engineer has no route from the job into the
      report engine at all. The panel should always show, with a plain "no
      formats agreed — write one anyway" path.
- [ ] **B3 · The engineer's menu has two items.** `My deputations` and `My
      vouchers`. Not the report register, not evidence upload, not their own
      certificates, not their availability/leave. They *have* permission for
      `/documents` — it is simply never offered. (The dashboard tiles do link to
      `/my-jobs?f=reports`, so it is not a dead end, but the person who writes
      every report has no menu entry for reports.)
- [ ] **B4 · Sales → operations is pull-only.** A won quotation lands the client
      in the master and the call form can pick the quote up, but there is no
      **"Raise an inspection call from this quotation"** button. Somebody has to
      remember. Every comparable product pushes.

### C. Not in line with the market

- [ ] **C1 · No client portal.** The single biggest differentiator competing
      products sell on: the client logs in, sees call status, downloads reports,
      raises the next call. Also removes the daily "where is my report" e-mail.
- [ ] **C2 · Tax stops at the quotation.** A single `gst_pct` / `gst_amount`
      exists on the quote. On the invoice side there is **no GST at all** — no
      HSN/SAC code, no place of supply, no IGST vs CGST+SGST split, no
      e-invoice/IRN. `/invoicing` is an invoice *tracker* (raised / paid flags),
      not an invoicing module. Fine if billing is done in Tally — but then the
      export to Tally is the missing piece, and it should be said out loud.
- [ ] **C3 · No receivables ageing.** Overdue is a yes/no. Collections work off
      0–30 / 31–60 / 61–90 / 90+ buckets by client.
- [ ] **C4 · No customer satisfaction / feedback capture** after a job closes —
      an ISO 9001 expectation and a normal account-management tool.
- [ ] **C5 · Director's dashboard has no utilisation.** Pipeline and conversion
      are there; the one number a director asks about an inspection business —
      *what percentage of engineer-days were billable* — is not on it, even
      though the man-day data to compute it exists.

### D. Mobile — already most of the way there

The app is **already a working PWA**: `manifest.php` (standalone display,
portrait, icons), a registered service worker with field-friendly caching, an
offline draft queue in `localStorage`, `capture="environment"` for the camera
and `navigator.geolocation` for GPS-stamped evidence. Every screen tested at
Pixel-7 size had zero horizontal scroll, and the engineer gets a card layout
with a bottom tab bar. See the fuller answer in the review notes; the short
version is that "Add to Home Screen" is available today, and store presence is
a packaging job (Trusted Web Activity for Play, WKWebView shell for the App
Store) rather than a rewrite — with **HTTPS being the hard prerequisite**,
since a service worker will not run over plain HTTP.

**Suggested order** if the list is worked: B1 (minutes) → B3 + B2 (the field
user's day) → A4 (expired certificates are both a safety and an accreditation
problem) → A1 → C5 → B4 → A2/A3/A5 → C1 → C2/C3.


## 🧾 EXPENSES THE CLIENT PAYS FOR, AND THE BILLS THAT BACK THEM (July 2026)

Owner: *"There shall also be option to mark if travelling, lodging boarding with
multiple selection chargeable to client; if tick or selected it is required for
inspector to upload all related bills."*

**Ticked on the call, corrected at allocation.** A tick-list of expense headings
sits on the inspection call and again on the allocate screen. Any number can be
ticked. The list is the company's own **expense-heading master**, not a fixed
three — travelling, lodging, boarding, local conveyance, misc, and anything
added under Masters → Expense headings appears there by itself.

**A tick is a promise, so it is enforced.** Every heading ticked must have at
least one bill uploaded against the deputation. Until it does:

- the deputation screen names exactly which bills are outstanding,
- the Close screen says so **before** anything is filled in, and greys the
  Close button,
- and the server refuses the close even if the button is bypassed. Checked in
  `job_bills_block()`, called from the `job-close` handler — not only in the
  browser, because this is a promise made to a customer.

**Who can file one.** The engineer on the deputation, the coordinator, or a
manager. Once the deputation is closed the bills are part of what was invoiced,
so only a manager may remove one — an engineer cannot quietly withdraw the
evidence for something already charged.

**A bill the client pays is not a cost this branch bears.** `job_profit()` now
carries a `recovered` line: the bills filed under a ticked heading come back out
of the cost, and the deputation shows *"Less: recovered from the client"*.
Without it a branch that laid out ₹9,600 and billed all of it back showed a
₹9,600 loss it never made. Two guards on that figure:

- it is **capped at what the job actually cost** in expenses and voucher claims,
  so a mis-keyed bill can never invent profit;
- un-ticking a heading stops its bills counting, but **keeps them on file** —
  they are shown as *"no longer charged"* rather than vanishing.

**Two foot-guns closed while here.** `tools/check-columns.php` kept a
hand-written list of modules to load, so a save could name a column in a module
the checker had never heard of and still pass — it now loads every `lib/*.php`.
And `job_profit()` calls into the new module through `function_exists()`, the
same way `boot()` does, so a partial upload cannot take the profitability screen
down.

*Verified:* 31 rule tests, and a real browser run that ticks the three headings,
is refused at the Close screen, is refused again when the disabled button is
bypassed with a valid CSRF token, uploads the three bills, opens one back, and
then closes — 13 checks, repeatable from a clean reset. Lint green (136 files),
81 screens render, fresh-install boot creates both columns and the table.

## 🏷️ THE ACRONYMS ARE GONE (July 2026)

Owner: *"delete SGS, BOSS etc. and replace them with suitable name — for BOSS it
is Contract. Rename SBU with Business Unit or something like that."*

**BOSS → Contract Number.** Already the shipped term; this pass finished the job
by clearing the word out of every remaining comment, screen and document.

**SBU → Business Unit.** The shipped term is now `Business Unit` / `Business
Units`. Every screen that used to print the acronym now asks the terminology
engine instead (`T('sbu')` / `Tl('sbu')` / `TP('sbu')`), so a company that wants
a different word sets it once under **Settings → Terminology** and every screen
follows. The JavaScript no longer hard-codes it either — the page publishes
`window.TERM_SBU` and `app.js` reads that.

**The role stays `SBU_HEAD` in the database, and reads "Business Unit Head" on
screen.** Renaming the stored code would have orphaned every existing user row.

**Live databases are renamed too, not just fresh ones.** A database set up on an
older version still carried the acronyms as *data* — the list heading `SBU`, the
list heading `BOSS status`, and the designation value `SBU Head`. Two new
helpers, `lk_rename_type_label()` and `lk_rename_value_label()`, rename those in
place from `lk_migrate()`, and the boot probe in `index.php` asserts the old
wording is gone so an existing install actually runs the upgrade. Both only match
the *exact* old wording, so a company that has already renamed a list keeps its
own word.

**"SGS India Pvt. Ltd." on the login screen is not in the code.** It is the
`app_name` setting — whatever was typed into **Settings → Application name**.
Changing it there changes the login screen, the sidebar, the browser tab, the
PDF letterhead and the "From" name on every e-mail. Grep confirms no third-party
inspection-agency or real client name appears anywhere in the source, the seeds
or the documents.

*Verified:* lint green (135 files), 81 screens render, and every screen in the
app was crawled and grepped — no `SBU`, `SBUs`, `BOSS` or `SGS` survives in any
rendered page. Fresh-install boot produces the new wording directly.

## 📉 PROFIT ON ONE INSPECTION (July 2026)

**Profit by call** — a branch manager and the managers under them see what each
inspection actually made. Scoped to the branches they can already see; no new
visibility anywhere else.

    Revenue              invoice less any credit passed over
  − Engineer's salary    unloaded daily cost × days worked
  − Overhead             the branch percentage on that salary
  − Expenses at close    booked against the job when it closed
  − Voucher claims       what the engineer claimed on the monthly voucher
  − Sub-contractor
  − Anything else        hired instrument, permit, courier
  − Contingency          the branch percentage on all of the above
  ──────────────────────────────────────────────────
  = Profit, and margin = profit ÷ revenue

### Two things this corrected

- **Voucher claims were never in the per-job sum.** Closure expenses were;
  what the engineer actually claimed for travel and lodging was not. Every job
  looked better than it was.
- **Overhead was hidden inside the salary.** The daily rate had it baked in, so
  there was no line to point at. Salary and overhead are separate lines now and
  add to exactly what the loaded rate gave — the total is unchanged, the
  statement is readable.

Both percentages are per branch (Masters → Offices), falling back to the
company default in Settings. Nothing is hard-coded.

The branch's own shared costs — rent, the manager's salary, the back office —
are **not** pushed onto individual jobs. A job is judged on what it directly
caused; the branch is judged on the Business Unit P&L, where those are shared out at
month end.

## 🧮 RATE × DAYS, ON BOTH SIDES, AND THEN THE REVENUE (July 2026)

The client charge and the inter-office credit are agreed the same way — a rate
per man-day — so they are entered and totalled the same way, and the one figure
that follows from both is stated rather than left to be worked out on paper.

**On the call and on the allocation:**

| | |
|---|---|
| Unit rate | per man-day, from the order line |
| Total invoice value | rate × days |
| Credit per man-day | what the executing branch is paid for each day |
| Total credit | credit rate × days |
| **Revenue** | **total invoice − credit** |

Worked example, verified end to end: 6 days at 3,000 credited at 1,800 a day →
invoice 18,000, credit 10,800, revenue **7,200**. Change the man-days to 8 at
allocation and all three move: 24,000, 14,400, **9,600**. Either total can be
typed over when that one is billed or credited differently, and typing stops it
being recalculated.

Only the credit total used to be stored. A six-day deputation could carry one
day's credit with nothing on screen to show which figure was wrong.

**Revenue has its own permission — `data.revenue`.** A coordinator has to see
the credit to do the job and has no business seeing what the branch earns on it.
Where it is not granted the screen says so rather than leaving a blank. Granted
by default to the roles that already had contract profitability; the credit
boxes are untouched.

## 💰 THE MAN-DAYS ARE THE QUANTITY (July 2026)

Six man-days entered on the allocate screen, against an order line at 3,000 a
man-day, and the value stayed at 3,000.

The invoice value was carried across from the call **once** and then followed
nothing. If the call was priced as a single visit — which it usually is, because
the man-days are not known until somebody is allocated — the deputation kept
that one day's figure however many days were actually worked. Every multi-day
deputation raised off a single-day call was set up to be invoiced short.

The allocate screen now shows the **unit rate carried from the call** (read-only)
and works the invoice value out as **rate × man-days**, live. Man-days left at 0
means "count them from the dates", and the schedule says how many that is. Typing
over the value stops it being recalculated, and says so.

Recomputed on the server as well as in the browser — a figure that only exists
if JavaScript ran is a figure that will one day be wrong.

## 🧨 TWO THINGS THE SHAPE REWRITE BROKE (July 2026)

Both were mine, both were reported from the live site, and both are the same
kind of mistake — changing one thing and not following the thread to what read
from it.

**The billable value stopped calculating.** The quantity was worked out by
counting filled-in date boxes on the page. The moment the form only shows the
boxes the chosen shape needs, a continuous run of six days has no date boxes at
all — so the count read zero, the quantity fell back to one, and six days at
3,000 priced as 3,000. The quantity now comes from the schedule itself, which
is the only thing that knows: six days is six, a posting is however many
man-months are claimable, a lump sum is one. A hand-typed quantity still wins.

**Allocate died with "Unknown column 'schedule_weekdays' in 'INSERT INTO'".**
The pattern columns existed on `calls` and were added to the deputation's save
list, but never to the `jobs` table. `php -l` cannot see that, and neither can
any test that does not press the button.

`tools/check-columns.php` now closes that whole class: it builds a throwaway
database so every migration runs, then checks each save's field list against
the schema it will actually meet. Wired into `tools/lint.sh`. Verified by
removing the column again and watching it fail.

## 📅 FIVE SHAPES OF ENGAGEMENT (July 2026)

The three dates mean three different things and were being typed as though they
were interchangeable:

| | |
|---|---|
| **Call received** | the day the contracting branch got it |
| **Required** | the day the client asked for |
| **Scheduled** | the day we are actually going |

Only the third is chosen at allocation; the first two are settled on the call
and shown read-only. The end date is never typed — it follows.

An engagement is one of five shapes, and only the boxes that shape needs are
ever shown:

- **Single day** — one date, nothing else.
- **Continuous** — type the number of days. The end date counts *working* days:
  Sundays, the branch's Saturday pattern and the branch's own public holidays
  are stepped over. Five days from Thursday 30 July ends Tuesday 4 August at a
  six-day branch and Wednesday 5 August at a five-day one.
- **Multiple dates** — two date lines, and one more each time you ask. It runs
  from the earliest to the latest; the days between are not inspection days.
  Each visit can carry a different engineer.
- **Pattern** — chosen weekdays, N times a week, every N days, fortnightly or
  once a month, until a date. The dates are worked out, not typed.
- **Monthly deputation** — a posting at the works on a man-month basis.

Holidays now belong to a branch (blank = national). All the arithmetic lives in
`lib/schedule.php` and is asked of the server as the form is filled in, so the
holiday rules exist in exactly one place. Where an engineer is already booked,
the clash is named and the branch's free engineers are offered instead.

### Settled

- **Saturday is a full working day for an inspection engineer.** The 5 / 5.5 / 6
  pattern on an office is about office staff; it never applied to a man on a
  site, and applying it made every end date a day late. Only Sundays and the
  branch's public holidays are stepped over now.
- **A Sunday followed by a Monday holiday pushes the visit to Tuesday** — and
  the coordinator can pull either day back in. Every skipped day inside a run is
  offered as a tick-box; ticking one records that it will be worked and the end
  date moves back with it.
- **A monthly deputation runs the 1st to the last day of the month**, whatever
  day it starts.

### Man-months — configured in three places, most specific wins

| Where | When to use it |
|---|---|
| **Settings → Financial & operations** | the company default |
| **Client master** | this client's contract says something different |
| **The call / the allocation** | this one order differs from what that client normally agrees |

Two definitions:

- **Calendar month** — one man-month whatever the working days come to. 24 days
  or 27, it is one.
- **Minimum working days** (usually 26) — a month falling short is claimable
  pro-rata (21 working days = 21/26 = 0.81 man-months); a month running over is
  still exactly one. The extra day is not billable.

The allocate screen shows the month-by-month working, says which of the three
places the definition came from, and the claimable total becomes the billable
quantity.

## 🛑 SAVE MUST NEVER FAIL SILENTLY (July 2026)

Reported twice. The first fix was wrong, and this records why so it is not
attempted that way again.

A browser refuses to submit a form holding an invalid field and tries to point
at it. If that field is not on screen it cannot point at anything — so it
refuses, says nothing, and the button looks dead. Every searchable dropdown in
this app hides its real `<select>` behind a text box, so **any** required
dropdown was one empty answer away from killing the whole form.

The first attempt listened for the form's `submit` event and took the
requirement off anything hidden. It could never have worked: the browser blocks
*before* `submit` fires, so the listener never ran.

The browser's own checking is now switched off (`noValidate`) and done in the
page instead, where it can be seen:

- on screen and wrong → rung in red, scrolled to, named in a message above the
  form; nothing is submitted;
- off screen → let through to the server, which checks it anyway and answers
  with a message that opens the section it lives in.

Either way something visible happens when Save is pressed. The guard also stops
the one-shot-ticket handler when it blocks, so the button no longer greys out
reading "Saving…" over a form that is not going anywhere.

## 🔗 WINNING IT PUTS THE COMPANY ON FILE (July 2026)

### Bugs fixed, from the live site

- **The PO line item list was empty even when the order existed.** The chain
  broke one step earlier than it looked. A quotation raised against a *typed*
  client name was never bound to the client master, so the Purchase Orders tab
  had no quotation to offer, the order was typed in by hand, and the lines that
  already existed on the quotation never came across. Marking a quotation
  **accepted** now registers the company — deliberately incomplete, tax details
  and address to follow — links every revision to it, and carries the types of
  inspection and the contact across. An order that has no lines now says so, on
  the order itself, on the client master's order list, and inside the call
  form's own dropdown, and offers to take the lines from the quotation.
- **"Allocate & send email" did nothing.** The expected inter-office credit was
  mandatory on *every* deputation, including one where a single office both
  holds the order and does the work — where there is no such credit to state.
  The browser refused the submit and the button died silently. The credit is now
  required only when the deputation really does cross offices; where it does
  not, the value carried is what the client is billed on the call.
- **contract number is now the contract number.** It is not chosen from a register
  any more — it comes down from the quotation to the call to the deputation, and
  the register entry is created on saving. The old register is still there and
  still holds the renewal chain; it just fills itself.

### Revenue and invoice value — the owner's definition, now the only one

    INVOICE VALUE  what the client is charged, as agreed on the purchase order
                   or the quotation. Once a bill is raised it is the bill.
    REVENUE        what a branch keeps out of it. Same branch holding the order
                   and doing the work → the whole invoice value. Two branches →
                   the holder keeps invoice − credit, the executor books the
                   credit.

Every screen reads this from one function. Two things it guarantees, both of
which were wrong before: branch revenues added together come back to the
invoice value exactly, and a same-office job is no longer worth nothing.

Jobs now carry the invoice value and the contracting branch of their own, both
carried from the call. Older rows were filled in from the call they came from
on the next boot; a cross-office job that only ever recorded a credit reads the
credit as the whole of it, so the holding branch shows nil rather than a loss
it never made.

## 🧾 QUOTATIONS, MASTERS & THE CALL FORM (July 2026)

### Bugs fixed, from the live site

- **"Save inspection call is not working."** The credit box was made required
  the moment *any* executing office was chosen — including when it was the same
  office holding the contract, in which case the whole box is hidden. A browser
  will neither submit a form with a required field it cannot show, nor say why.
  The button just died. Now the credit is only required when the call really
  does cross offices, and as a safety net across the whole app no form can be
  blocked by a field nobody can see.
- **A revision lost the sites and the types of inspection.** See the previous
  section — carried-across columns are now derived from the table rather than
  written out by hand.
- **PO line items were never fetched.** Because purchase orders were created
  empty and the lines had to be typed by hand afterwards. A PO recorded against
  its quotation now copies every quoted line, so the call register finds them.

### What to test

1. **Quotations** — approve, then *Take my approval back*. Mark one sent: it
   locks for everybody, and offers *Raise a revision* instead.
2. **Purchase Orders tab** — pick the quotation first: the contract number, business unit,
   value and every line item come across.
3. **Clients** — complete a client from a name sales typed into an inquiry: the
   types of inspection, the contact and the inquiry/quote links come with it.
4. **Quick-add a vendor from a call** — the GSTIN fills the PAN and the state,
   the state is a dropdown, and the same company cannot be added twice.
5. **A refused save** — the box that stopped it is ringed in red and the form
   scrolls to it.

### Closed since

- [x] **Duplicate review** — Clients → *Possible duplicates* finds pairs the add
      screens cannot: different spellings, M/s, Pvt Ltd, word order. Shows both
      sides with everything hanging off each, and merges only when a person says
      so. The dropped record is marked *merged*, never deleted.
- [x] **An order follows its quotation.** A PO raised against a quotation that is
      later revised says so and offers to pull the new lines through — refused
      once any of the order has been consumed, because the balances were measured
      against the old quantities.
- [x] **Client and vendor lists are one register across all offices** — verified,
      no change needed. If a branch should only see its own, that is a new
      requirement, not a fix.

## 📈 MANAGEMENT DASHBOARD, FINANCIAL YEARS & THE CLOSE-ON-TIME LOCK (July 2026)

### What to test

1. **Management dashboard** (new, in the menu). Filter by financial year,
   month, business unit, activity code, executing office, contracting office, engineer
   and client. Nine KPI cards, eight breakdown tables, each downloadable as a
   spreadsheet. Every figure is counted off ONE set of jobs by ONE rule, so no
   two tables can disagree.
2. **Financial years follow the data.** Enter work dated next year and next
   year appears in the list; the year ahead is always offered so work can be
   entered before April.
3. **The close-on-time lock.** An engineer has 2 days after the inspection ends
   (Settings → *Days to close a job*). Miss it and the job locks: nothing can
   be changed, the engineer/coordinator/branch manager/administrators are
   e-mailed once, and **documents can still be uploaded**. A manager can reopen
   it for a few days with a reason, which is written to the audit trail. The
   register shows a count of locked jobs and marks each row.
4. **Sub-contractor cost** now lands in the month-end run on the job, its
   contract number, its business unit and its activity code — never spread.

### Closed since

- [x] **The lock sweep no longer waits on cron.** Once a day, the first page
      somebody opens runs it. Setting the cPanel cron is still worth doing (it
      is tidier and does not depend on somebody signing in), but the alerts now
      go out either way.
- [x] **Year-on-year** — inspections, man-days, revenue and profit each carry
      the same slice of the calendar a year earlier. A period with nothing a
      year ago says so rather than inventing a percentage.
- [x] **Utilisation %** — man-days worked against days available (working days
      less holidays, across every engineer the filter covers).

### Still open

- [ ] **The dashboard reads jobs, not vouchers.** Where a voucher timesheet
      disagrees with a job's man-days, the job wins here. The reconciliation
      screen is the place that surfaces the difference — this is a deliberate
      choice, recorded so nobody has to rediscover it.

## 💰 COSTING & PROFITABILITY — complete, ready to test (July 2026)

Five commits: `9c75b5f` (the allocation engine), `4bc4c97` (expense heads master +
monthly cost entry), `23068db` (client/vendor import, clear records, the written
explanation in `docs/COSTING.md`), and this one (person cost & split, outstation
tick, month-end run, business unit / activity / contract P&L).

**Read `docs/COSTING.md` first** — the whole model in plain words with worked
figures, and the order to do things in month by month.

### What to test, in order

1. **Masters → Office expense heads** — add one of your own, change how it
   spreads, retire one. Delete the ones you do not use; they stay deleted.
2. **Each person's record → Cost & where it belongs** — monthly cost, and the
   tick for whether they do inspections. Non-engineers get one percentage box
   per business unit in their branch; the running total has to reach 100.
3. **A call → Outstation** — tick it, allocate the call, check it carried over.
4. **Office costs & overheads → Actual costs** — a month of real figures, then
   *Copy last month* into the next one.
5. **Month-end cost run** — preview, read the warnings, *Calculate and store*,
   then *Close the month*. Closing locks the entry screens; reopening is
   recorded in the audit trail.
6. **business unit profit & loss** — revenue against cost by business unit, by activity code, and
   by contract number.

### Closed since

- [x] **The Profitability screen and the Business Unit P&L now cross-link**, each saying
      what it does and does not include.
- [x] **Year to date and whole year** on the Business Unit P&L, alongside one month. It
      says how many of the months in the span have actually been run, and warns
      that months not yet run contribute revenue but no cost.
- [x] **Sub-contractor cost** is in the allocation run, on the job, its contract
      number, its business unit and its activity code.
- [x] **Closing a month closes its timesheets.** A frozen month refuses voucher
      edits with the reason, on every write path including a typed URL. Reopen
      the month to correct a day.

## 🔐 SECURITY & COMPLIANCE — shipped 2026-07, with what is still open

Four commits: `220d5cd` (forgery, sessions, login limits, uploaded files), `1dede08`
(passwords, two-step sign-in, session expiry), `ad44ecb` (attachment checks, host
lockdown), `2ec3caa` (compliance screen, incident register, data-subject rights, SBOM).

### ⚠️ OWNER ACTIONS — not code, nobody else can do these

In priority order. The first five cost almost nothing and matter more than anything
in the code.

- [x] **1. HTTPS on the live site — DONE (July 2026).** Certificate live at MilesWeb.
      The redirect in `.htaccess` was still commented out and has now been switched **on**:
      until it was, anyone reaching the bare `http://` address still got a session cookie
      with no `Secure` flag and **no service worker at all**, so the installable phone app
      did not work. Verified: announced as HTTPS the app sets `Secure` on the cookie and
      sends `Strict-Transport-Security`; on plain HTTP it correctly does neither.
      *Was:* THE biggest single gap. Free Let's Encrypt
      certificate in the MilesWeb cPanel. Once `https://yourdomain` works in a
      browser, remove the `#` from the four lines at the bottom of `phpapp/.htaccess`.
      **Do not uncomment before the certificate works** — visitors would be sent to an
      address that does not answer and the site would look down. Until this is done the
      session cookie cannot be marked "never send unencrypted" and passwords travel in
      the clear on any hotel or plant wifi. The compliance screen reports it red.
- [ ] **2. Change `admin/admin12345`** and the demo passwords (`demo12345` — director,
      bmanager, nisha.mehta, account, insp.ravi). The Users screen now names every
      account still on a shipped password, in red. Tick "they must choose their own at
      the next sign-in" when handing one over.
- [ ] **3. Grievance officer + privacy notice** — Settings → Compliance. Both are
      required by the DPDP Act. A complete draft notice written for an inspection
      business sits in the box as placeholder text: read it, correct what is not true
      of you, paste it in. An afternoon's work.
- [ ] **4. Two e-mails to MilesWeb, replies filed:**
      (a) confirm the account **and its backups** sit in an **Indian data centre**
      (CERT-In log-localisation); (b) confirm NTP is synchronised to
      **time.nplindia.org** or an NIST server (CERT-In clock-sync). Software cannot
      answer either — the compliance screen says "not ours to answer" for both.
- [ ] **5. Backups, and a restore that has actually been tried.** Every photograph,
      report and voucher is in one database. Schedule it in the panel, download a copy
      somewhere else, and **restore it once to prove it works.** Not on any regulatory
      list and more likely to save the business than everything above it.
- [ ] **6. Switch on two-step sign-in** for the roles that can move money or change
      permissions — Settings → Security → "Roles that must use two-step sign-in".
      Those people get nudged on every screen until they set it up.
- [ ] **7. Book the CERT-In empanelled audit.** Required annually. List published on
      cert-in.org.in; roughly ₹75,000–2,50,000 for an app this size. **Do items 1–4
      first** or you will pay to be told what is already written here. Record the date
      in Settings and the compliance screen turns green.
- [ ] **8. ISO/IEC 27001 or SOC 2 — only when a client asks.** These certify the
      *company*, not this software. Voluntary. Roughly ₹4–10 lakh and 6–12 months.
      The IT Act names ISO 27001 as *a* benchmark for "reasonable security", not the
      only one; demonstrable practices plus a CERT-In audit report is defensible for a
      company this size.

### 🔧 CODE — deliberately not built, or built only part way

- [ ] **Content-Security-Policy still carries `'unsafe-inline'`.** The screens use
      inline `onclick` handlers and inline `<style>`, so script injected into a page
      could still run. What it could NOT do is load code from another site or send what
      it found anywhere — `connect-src 'self'`, `object-src 'none'`, `form-action
      'self'` all hold. Closing it properly means moving every inline handler and style
      block out to files and adding a per-request nonce. Sizeable, low urgency while
      the escaping in the views holds.
- [ ] **Consent register is built but not wired in.** `data_consents` exists; nothing
      writes to it yet. For most of what this app holds the lawful basis is
      contract-performance, not consent, so this matters mainly if marketing e-mails
      are ever sent. Wire it at the contact-capture points if that changes.
- [ ] **No encryption at rest, by decision.** On shared hosting the key would sit in
      `config.php` next to the database password — anyone who can read one can read the
      other, so it buys almost nothing and risks losing the data if the key is lost.
      Real disk/database encryption is the hosting provider's to offer. Revisit only if
      a client contract demands it in writing, and then encrypt named columns (salary,
      bank details) rather than everything.
- [ ] **No IP allow-listing.** Would lock the app to the offices' addresses. Not built
      because inspectors work from client sites on mobile data. Worth adding for the
      *accounts and settings screens only* if ever wanted.
- [ ] **Two-step enrolment has no QR code** — the setup key is typed into the
      authenticator app by hand. A QR needs a pure-PHP encoder (~300 lines with
      Reed-Solomon) since no library can be installed on the host. Manual entry works
      with every app; add the QR if enrolment turns out to be a support burden.
- [ ] **Erasure covers system users and client contact persons only.** Candidates
      (`candidates`, with CVs) and inspectors-without-a-login are also personal data and
      have no erase path yet. Add `person_erase_preview()` / `person_erase()` branches
      for both.
- [ ] **Data-subject requests have no clock.** Incidents count down from six hours;
      requests just sit as "Open". The DPDP Act says "without undue delay" rather than
      naming days, but a visible ageing pill would stop one arriving on a Tuesday and
      being remembered in December.
- [ ] **Audit trim is a button, not a schedule.** `audit_trim_old()` runs only when
      somebody clicks it on the compliance screen. Wire it into `cron.php` so retention
      is applied whether or not anyone remembers.
- [ ] **The CERT-In incident report is composed by hand.** The screen states the address
      and everything they expect, all on one page, but does not send it. Wiring it to
      the existing `ops_mail` composer would remove a step at exactly the moment
      somebody is panicking. Worth doing.
- [ ] **SBOM is regenerated by hand** — `php tools/sbom.php` from the `phpapp` folder.
      Should run as part of whatever the release routine becomes, so `SBOM.json` cannot
      quietly go stale. Currently: 127 files, **zero third-party runtime dependencies,
      zero resources loaded from other sites** — measured, not asserted. This is the
      strongest compliance card the app has; keep it true.
- [ ] **Per-source login throttle could lock a whole office.** Counted per IP at 30
      failures / 15 minutes. A NAT'd office shares one address. Deliberately loose, but
      watch it once real staff are on it; the admin can release an account from the
      Users screen.

### 👀 OBSERVED ONCE, NOT REPRODUCED

- [ ] **A session ended unexpectedly straight after sign-in**, once, during testing.
      Never recurred across many later runs. The likely cause is the standard race when
      the session id is replaced at sign-in (`session_regenerate_id(true)`) and a second
      request arrives carrying the old id — every framework has this, and the
      alternative leaves the pre-login id valid, which is the thing being closed. Left
      as-is. **If anyone reports being thrown back to the sign-in page immediately after
      signing in, this is the first place to look.**

### 🐛 TWO REAL BUGS FOUND AND FIXED WHILE TESTING (for the record)

- A submission that was turned away sent the person back to the **wrong screen**. The
  browser reports where it came from as a full address; the code tested it for a
  leading `/`, which never matches, so the record id was dropped and they landed on an
  empty register. It also compared the host *with* the port against the host *without*
  it. **The same flaw was in the duplicate-submission path and had been doing this
  silently all along.** Now parsed properly in `redirect_back()`, same-host only.
- The default-password check ran **four bcrypt comparisons per account on every page
  load** of two screens. Bcrypt is deliberately slow; at fifty staff that was seconds
  of waiting. Now tests only accounts whose password has never been set through the
  app — anything set through it has already been refused by the policy.

### 🧪 TEST-SUITE HOUSEKEEPING (dev only, not shipped)

- [ ] Several scratchpad suites hard-code record ids (`job=315`, `call=326`,
      `document?id=1`) from whichever database existed when they were written, and fail
      as "precondition missing" on a fresh one. Worth changing to *find* a suitable
      record instead. `tools/smoke.js` and `tools/lint.sh` (incl. `check-dupes.php`,
      `check-strings.php`) are the two that always work and should be run before every
      upload: `sh tools/lint.sh && node tools/smoke.js`.

## 🚀 NEXT BIG BUILD — IDEMS: Intelligent Inspection Documentation, Reporting & Endorsement Engine (TPIA Industry Pack) — owner spec 2026-07, roadmap pending approval

A world-class TPIA documentation ecosystem. 24-part spec. Two core workflows:
(A) TPIA prepares & issues its OWN reports; (B) TPIA reviews/verifies/endorses/certifies
manufacturer/vendor/contractor documents. One platform for both. Configurable, mobile-friendly,
offline-capable, AI-ready, no-code report builder.

REUSE (already built — do NOT duplicate): crm_templates docx engine + doc/format-number stamping,
lib/pdf.php SimplePDF + signature image + per-company letterhead, custom-fields engine (dynamic
fields on any entity, cascading lookups), lookup masters, approval-chain (quote_approval_rules +
REPORTS_TO reporting-manager chain), report-approval + escalation, lib/ai.php provider seam,
email_log, deliverables master (IR/IRN/NCR/CoC…), FY/office/business unit scope.

Proposed phasing (each phase = its own commit, tested):
- P1 Foundation ✅ SHIPPED — lib/idems.php: report_types registry (32 TPIA types seeded + admin CRUD,
  unlimited) + configurable no-code IRN engine (token format, zero-duplicate via unique index +
  scope counters) + report_docs instance model + DRAFT→SUBMITTED→ISSUED with immutable finalize +
  idems_audit log + Document Register + IRN-rules + audit-log viewer. Module 'idems' + perms
  (idems.finalize/type.manage/timestamp.edit/audit.view) wired into roles.
- P2 No-code Report Builder ✅ SHIPPED — report_sections/report_fields/report_files schema; builder UI
  (18 field types incl. conditional show-if, calculated formulas, mandatory/hidden, repeatable tables,
  photo/file/GPS/signature, options incl. lookup:key); fill renderer with live conditional/calc JS,
  table add-row, signature canvas, GPS capture, file/photo upload (base64 in report_files, 6 MB cap);
  values saved to report_docs.data JSON; detail renders filled body + evidence thumbnails; finalize
  still locks it. Routes: /report-builder, /report-field-edit, /document-fill, /report-file.
- P3 Workflow & approvals ✅ SHIPPED — idems_approver_map (per-inspector approver, common approver,
  temp cover during leave), idems_approval_rules (configurable multi-level chain matched by report
  type/office/client/business unit; approver = inspector-map / reporting-manager / specific user / role; per-level
  SLA), report_approvals steps built on submit (submit blocked if no approver), approve/reject/
  send-back/delegate with mandatory remarks, finalize gated on full approval, SLA auto-escalation in
  cron, approval-chain panel + act buttons on the report detail. Routes: /approver-map,
  /idems-approval-rules(-edit), /document-approve.
- P4 Auto signatures + immutable timestamps ✅ SHIPPED — signature on users + inspectors; self-service
  /my-signature (draw or upload) + inspector-master signature panel; report PDF (/document-pdf) with
  letterhead + key refs + designed body (fields/tables/photos) + auto signature block (inspector +
  final approver, images + name/designation/emp-code/branch + system timestamps) + DRAFT watermark;
  signatures snapshotted onto the report at finalize (frozen); Branch-App-Mgr-only date edit
  (/document-timestamp) with mandatory reason → tamper-proof audit (old/new/user/reason/ip/time).
- P5 Client-specific formats ✅ SHIPPED — report_templates (upload .docx per report type/client/office,
  with doc/format/revision numbers); generic {{token}} fill reusing the docx engine + a generalised
  repeatable-table row expander ({{fkey.col}}); token map from standard fields + the type's designed
  fields; most-specific template auto-selected; /document-docx generates output in the client's exact
  format (fonts/headers/footers/logo/tables preserved); token-reference UI. NO agency names seeded
  (owner instruction) — admin uploads their own client templates. Routes: /report-templates(-edit/
  -download), /document-docx.
- P6 Manufacturer document verification & endorsement ✅ SHIPPED — endorsements + endorsement_files
  tables; upload manufacturer quality records (MTC/NDT/hydro/PWHT/hardness/FAT/calibration/welding/HTR/
  etc.) — the ORIGINAL is stored & never altered; metadata + supporting evidence + linked inspection
  report; auto endorsement number (reuses IRN engine, END type); assigned approver (mapped or picked);
  submit→review→endorse/endorse-with-comments/reject (reject remarks mandatory); auto inspector+approver
  signatures snapshotted at endorse; SEPARATE endorsement-certificate PDF (green band) referencing the
  original; immutable after endorse; full endorsement audit trail; soft-delete archive. Routes:
  /endorsements, /endorsement(-new/-edit/-submit/-approve/-delete/-file), /endorsement-cert.
- P7 Technical Writing Assistant (no AI) ✅ SHIPPED — tech_phrases library (35 standard phrases seeded
  across observation/acceptance/rejection/conclusion/recommendation/hold/witness/deviation; admin adds
  unlimited); rule-based tech_expand(): per-line shorthand→library match (exact/contains/word-overlap)
  else tidy pass (domain spell-check, abbreviation expansion, capitalisation, terminal punctuation);
  standalone /writing-assistant tool + clickable phrase panel; inline "✒️ Improve wording" button on
  every report textarea via AJAX; usage counters feed future self-learning (P13). Routes:
  /writing-assistant, /phrase-library, /phrase-edit.
- P8 Smart Remarks & auto Release Note ✅ SHIPPED — rule-based idems_smart_remarks() scans the filled
  body for adverse signals (not-ok/reject/deviation/defect/expired/…) and drafts summary, observations,
  deviations, hold/witness points, conclusion, acceptance and recommendations from the phrase library;
  proposes result + release status; /document-smart preview screen (editable) with one-click apply.
  /document-release-note auto-drafts an RN report from an APPROVED/ISSUED report — carries all refs
  across, wording follows the findings, links back to the source (shown as a banner), duplicate-guarded
  on source_report_id, left as DRAFT for review before issue.
- P9 AI-assisted documentation ✅ SHIPPED — ai_chat() POST seam added to lib/ai.php (openai-compatible,
  anthropic, gemini, perplexity, copilot; normalised reply, graceful errors). Source-document library
  per report (PO/call/QAP/drawing/spec/standard/MTC/calibration/previous report/customer instruction)
  stored as report_files kind=src_*; best-effort text extraction (text, docx via zip, uncompressed PDF).
  Rule-based checks ALWAYS run without AI: missing expected documents, blank PO/drawing/QAP/standard
  traceability, drawing-not-found-in-attachments, drawing REVISION MISMATCH, expired calibration vs
  inspection date. Optional AI layer reads the pack + findings and returns missing docs / revision-spec
  conflicts / traceability issues / suggested hold + witness points / draft remarks, parsed into
  sections. Inspector is always the approving authority (stated in UI). Route: /document-review.
- P10 Smart photo & evidence management ✅ SHIPPED — report_files gains sha1/caption/taken_at/bytes/
  orig_bytes. Two-stage compression: browser-side canvas shrink before upload (saves mobile data) +
  server-side GD resize/recompress (max 1600px, q82, transparency flattened) — ~80-90% smaller on real
  camera photos, deterministic output. EXIF capture-time extraction, auto GPS capture on photo select,
  sha1 duplicate detection (same content skipped, reported in the flash). New /document-evidence
  gallery: organised by report section → field, thumbnails, capture time, clickable GPS (maps link),
  size + saving per item, editable captions, remove; KPI strip (items / stored size / space saved /
  GPS-tagged). Locked once the report is finalized.
- P11 Super-Admin audit & compliance dashboard ✅ SHIPPED — login/logout/failed-login now audited too.
  /audit-log rebuilt as a compliance dashboard: KPI strip (total events, today, high-risk 30d, active
  users), automated compliance checks (inspectors with no approver, reports stuck in review >7d,
  timestamp changes in 30d, users without a signature, failed-login spikes, soft-deleted reports),
  activity-by-action bar chart (high-risk flagged), most-active users, by-branch chips, full filter set
  (action / branch / user / date range / free-text over IRN+user+value+reason / high-risk-only),
  plain-English action labels, high-risk rows highlighted, and CSV export of the filtered view.
  Records are immutable and never purged; deletes stay soft.
- P12 Offline-first / mobile field UX ✅ SHIPPED (PWA-lite, as scoped for shared hosting) —
  manifest.php (installable, app name/theme follow Settings, inline SVG icon so no binary assets),
  sw.js service worker (network-first pages cached for re-open, cache-first assets, offline fallback
  page, never caches POSTs or auth), assets/js/offline.js: per-form localStorage draft autosave with a
  "restore this draft?" prompt, offline submit queue replayed automatically on reconnect (forms with
  photos are held back with a clear message since images need a live upload), and a fixed connection
  banner showing offline / syncing state. Fill form marked data-autosave + field-mode CSS (single
  column, 16px inputs to stop iOS zoom, 44px+ touch targets). index.php serves /sw.js, /manifest.php
  and /assets/* before the auth gate (path-traversal guarded) so it works on any host rewrite;
  .htaccess sets Service-Worker-Allowed + no-cache for sw.js.
- P13 Self-learning suggestions ✅ SHIPPED — learned_suggestions table; learn_from_report() harvests
  wording when a report is APPROVED or ISSUED (never from drafts): per report-type field wording,
  per-client wording, remark sentences, and recurring NCR causes (adverse results). Ranked
  learn_suggestions() (client-specific first, then type-wide) surfaces as clickable "Used before ×N"
  chips on text/textarea fields in the fill form — click to insert, nothing auto-applied. /learning
  insights screen: KPIs (learned entries, times seen, reports learned from, NCR patterns), most-used
  standard phrases chart, scope filter, and per-entry Standardise (promote into tech_phrases) / Mute /
  Restore. Suggestions only ever enhance — technical conclusions and approvals are never altered.

=== IDEMS COMPLETE: all 13 phases shipped ===

- ➕ Form-from-format generator ✅ SHIPPED (owner request) — /report-form-from-template reads an
  uploaded client .docx, extracts every {{token}} in document order, derives the LABEL from the text
  immediately before the token ("Description of non-conformance: {{nc_description}}" → that label),
  guesses the field type from key+label (date/time/number/textarea/select/text; "…No." stays text),
  groups {{field.col}} tokens into a repeatable table with its columns, skips standard header tokens
  (irn/client/po/drawing/…) and fields that already exist, then shows an EDITABLE plan (tick, rename,
  change type, edit table columns, choose/name the section) and creates the fields in one click.
  Buttons on Report templates ("🪄 Build form") and in an empty form builder. Verified round-trip:
  NCR format uploaded → form generated → filled → client-format .docx produced with no leftover tokens.

- ➕ Prefill from the call / job ✅ SHIPPED (owner request) — idems_context_for() gathers everything
  already known (call code, client, vendor, business unit, office, product, inspection type, PO number + line
  item, site address, notes; job code, inspector, inspection dates, contract number, quote/contract).
  "New report" from a call or job (buttons on call_detail + job_detail, plus a call picker on the
  new-report screen) prefills the whole header. idems_autofill() then ALIGNS THE DESIGNED FORM FIELDS
  via an alias map (customer/purchaser→client, supplier/manufacturer/works→vendor, dwg_no→drawing,
  date_of_inspection→date, inspected_by→inspector, works_location/site→location, equipment/item→
  product, call_no→call code, division→business unit, contract_ref/boss→contract no., qty_offered→PO line qty …),
  so a client-worded format fills itself. Never overwrites what the inspector typed; auto-filled
  fields are badged "auto" with a summary banner. report_docs.job_id now stored alongside call_id.
  Verified: customer→Northern Petrochem, supplier→Vapi Chem, date_of_inspection→2026-07-13, inspected_by→Ravi
  Kumar, equipment→Pressure vessel, call_no→C-2607-001, division→Industrial, contract_ref→40231.

Constraint note: MilesWeb shared PHP hosting — no Node/build; "offline-first" is delivered as a
responsive PWA-lite (localStorage drafts + autosave + sync), not a native app.

## 🧭 NEXT BIG BUILD — Workforce, hierarchy & permissions pack (owner request 2026-07, before dashboards)

Five owner requirements, to be built as extensions of existing tables (reuse, don't duplicate):

1. **Daily inspector-availability board** on the dashboard of the *office's* Coordinator,
   Operation Manager & Branch Manager: how many inspectors are FREE vs ALLOCATED today,
   with a one-click per-inspector dropdown to set today's status — Available / On job /
   On leave / Training / In office / Half-day / etc. "Allocated" is auto-derived from jobs
   scheduled today; the dropdown writes a date-specific override.
   → new `inspector_day_status` table (inspector_id, day, status, note, set_by), AJAX set
   endpoint, dashboard panel scoped to the viewer's office(s).
2. **8.5-hour daily cap** — no inspector may log > 8 h 30 m (= 8.5) of working hours on any
   single working day. Enforce on the timesheet/voucher `hours` entry (sum per inspector+date),
   block + warn on exceed.
3. **Weekly working days = 5 or 5.5 per employee** — configurable field on inspectors (and
   surfaced on users). Feeds per-person working-day / leave / utilisation maths.
4. **Reporting-manager chain + auto org hierarchy** — assign each position a reporting manager
   manually (name, position, email; link a system user when one exists). System then derives the
   N+1 chain automatically (already have `users.reports_to_id`). Wire the chain into:
   (a) CRM quote approvals — option to route up the reporting chain in addition to the existing
   amount/business unit rules; (b) inspection/report approval — report/closure routes to the inspector's
   reporting manager. Org-hierarchy view under Settings.
5. **Exhaustive, role-divided permissions with one-click presets** — make the permission catalogue
   complete & clearly worded, grouped, and give the access admin a "recommended set per role"
   that applies in a single selection, with a readable per-role explanation so an admin knows
   at a glance who gets what.

Status: 1 ✅ 2 ✅ 3 ✅ 4 ✅ 5 ✅ — ALL SHIPPED. (availability board, 8.5h cap, weekly-days,
reporting chain + hierarchy + CRM REPORTS_TO approval routing + inspection report sign-off,
grouped exhaustive permissions + one-click recommended-per-role presets.)

Follow-on requests (2026-07) — ALL SHIPPED:
- Working weekly hours/days per designation per office → work_norms master + inheritance ✅
- Automate reporting → overdue-report escalation to reporting manager + weekly/monthly MIS digest ✅
- Dashboards for all roles → executive strategic board (FY revenue + YoY + target + top clients +
  sales won) added on top of the existing role-based section ordering ✅


## 🧭 NEXT BIG BUILD — CRM / Marketing & Sales module (roadmap pending owner approval, 2026-07)

The whole pre-operations sales funnel: **inquiry → quotation → approval → send →
follow-up → acceptance → client/contract registration → hand-off to Operations →
revenue tracking.** To be built as a new module that plugs into the EXISTING
operations system (do NOT duplicate — reuse the tables/masters/email/roles below).
**Build CRM first; the Executive-Director dashboard rebuild is parked to do
afterwards (current landing dashboard "is not what we're expecting").**

### Owner's 25-point requirement (verbatim intent, condensed)
1. Customer inquiry received via email (capture it).
2. Quotation number generation (auto).
3. Quotation generation (the document).
4. Allocate to different business unit(s) when generating the quote (per line if needed).
5. Upload the quote WORD format; it must adapt to a new/revised format whenever a
   new .docx is uploaded (template-driven, hot-swappable).
6. Fields designable — create / edit / delete fields to match the new format.
7. Person only enters data → system auto-creates the quotation document.
8. Every data field should be able to offer drop-downs for fast entry.
9. After creation it moves through an approval chain as required.
10. Once fully approved → sent to the customer directly on their email.
11. Auto follow-up reminder emails at 3 / 6 / 9 days, fortnight, month, with a
    pre-drafted follow-up message. Templates editable by admin / marketing mgr.
12. If acceptance arrives mid-chain → close the follow-up chain → route to Accounts
    to register the client → create a contract + contract number (here or external).
13. On contract-number entry, auto-float an Operations packet: client name, quote
    no., contract no., contact person / email / mobile, service requirement,
    techno-commercial proposal.
14. Always show OPEN / PENDING / CLOSED (accepted) quotes.
15. Monthly report.
16. Integrate operations so revenue is tracked line-item-wise against each quote
    number AND contract number.
17. Two order types: OPEN (no PO — e.g. ARC) vs line-item orders (X days, Y months,
    technical-audit days, …). Sales rep enters the order lines once the contract
    number is generated.
18. Job type configurable: Inspection, Project deputation, … Inspection sub-cats:
    Product Inspection, Expediting Visit, Tender Document Review, Document Review,
    Vendor Assessment, Vendor Audit, …; Project-deputation sub-cats: Site QA/QC,
    Commissioning, O&M, Erection, … — multiple-selection allowed.
19. Inspection location may differ from the client's registered/contract address —
    "agreed location" or "Pan-India" must be clearly stated.
20. CV-to-client tracking for deputation: client requirement, which CVs submitted &
    when, client feedback (shortlisted / rejected), interview required? planned for /
    completed on / outcome; on selection → auto-email candidate requesting all
    credentials (CV, salary slip, …) via a configurable template.
21. Advance-payment flag so Operations knows payment must be received BEFORE
    scheduling the inspection.
22. Payment-linked deliverable: report released only against payment; show the
    inspector a HOLD so they don't issue the deliverable; fetch this rule from the
    quotation / final accepted terms.
23. Quote revision is compulsory when needed → auto Rev. 01 numbering, with an edit
    history of what changed (reuse the contract supersede/hierarchy pattern).
24. Fetch the required deliverable(s) from the quote into Operations.
25. Job types (full list): Inspection, Project deputation, Supply-chain deputation,
    Site supervision, Commissioning & Installation, Site QA/QC, Technical audit,
    Type test, Vendor Assessment, Vendor Audit, Document Review, Tender Review, …

### What ALREADY EXISTS to reuse (integration surface — researched)
- `business_partners` (clients/vendors, GSTIN, `contract_number`, inspection_types),
  `partner_contacts` (name/email/mobile), `partner_site_addresses` (locations),
  `partner_contracts` (contract_number/title/sbu/value/dates),
  `partner_purchase_orders` + `po_line_items` (trade/skill/site/manpower/activity/
  gst/base/tax/total — this is largely the "order line items" of §17), contract numbers.
- Calls→Jobs pipeline with `inspection_type`, `job_type` (INSPECTION/DEPUTATION),
  `deliverables`, `site_address_id`, `po_id`, `po_line_item_id`, activity, business unit,
  mandays; invoicing + payment + credit + profitability.
- Masters/lookups engine (configurable dropdowns → §8), `INSPECTION_TYPES` (30+),
  `JOB_TYPES`, `DELIVERABLES` constants + lookup overrides (§18/§25).
- Candidate + Requisition + offer-stage pipeline (partial base for §20).
- Email infra (`ops_mail`, SMTP settings) + daily reminder cron (`ops_run_reminders`,
  cert reminders) — the scaffold for §11 follow-ups.
- Access/roles + per-module View/Edit + scope; theme/masters admin.

### GAPS I flagged (owner may have missed / to confirm)
- **No quotation/inquiry module at all** — quotations, quote numbers, quote line
  items, revisions, approval chain, send-to-customer: all NEW.
- **Marketing/Sales roles missing** — add BDM, Key Accounts Manager, Marketing
  Manager, Marketing Executive (Business Development). Map to access modules.
- **Word-template engine (§5–7) is the key technical decision.** On MilesWeb shared
  PHP (no Composer/PHPWord), the pragmatic path is a **.docx with placeholder tokens**
  (e.g. `{{client_name}}`, `{{line_items}}`) that we fill by unzipping the docx with
  PHP `ZipArchive` and string-replacing in `word/document.xml` — no library, upload-
  and-go, hot-swappable format. Alternative: HTML→print-to-PDF. Needs owner's pick.
- **Approval chain needs a rule/matrix** — "as required" must become configurable
  (levels by value threshold and/or business unit, with named approvers). Confirm the rule.
- **Lost/Rejected quote status** — owner listed Open/Pending/Closed(accepted) only;
  need a LOST/REGRETTED state + reason for win/loss analytics.
- **One quote → multiple business units** (§4) implies business unit per quote line (split), not one business unit
  per quote — confirm.
- **Advance %/payment terms & "report-vs-payment" gate** need explicit fields on the
  quote that flow to the job and show the inspector a HOLD (§21/§22).
- **Revision history** — capture field-level diff, not just a new rev number (§23).
- **Duplicate-inquiry / duplicate-quote** guard; attachments (techno-commercial PDF).

### Proposed phased roadmap (see chat for the approved-pending version)
- P0 Foundations: sales roles + access modules; CRM masters (job-type / inspection
  sub-category as multi-select configurable masters); quote/inquiry numbering.
- P1 Inquiry + Quotation core: inquiry capture, quote header + line items (business unit per
  line), auto quote number, revisions (Rev 01) + history, dropdown-driven entry.
- P2 Template engine: upload .docx template, map/define fields (add/edit/delete),
  auto-fill via ZipArchive token replacement → downloadable quote.
- P3 Approval + send + follow-up: configurable approval chain → approve → auto-email
  customer with the quote → 3/6/9/fortnight/month follow-ups with editable templates;
  Open / Pending / Closed / Lost states.
- P4 Acceptance → hand-off: acceptance closes follow-ups → Accounts registers client +
  contract number → auto-float Operations packet (§13); OPEN (ARC) vs line-item
  orders (§17) entered by sales rep after contract number.
- P5 Ops integration + revenue: link quote/contract line items to calls/jobs; revenue
  line-item-wise per quote & contract (§16); advance-payment flag + payment-before-
  report HOLD visible to inspector (§21/§22); deliverables/terms fetched from quote.
- P6 CV-to-client tracking (§20): client submission → feedback → interview → selection
  → auto credential-request email (configurable template).
- P7 CRM dashboards + monthly report + win/loss analytics.

### ✅ P0 shipped (2026-07) — CRM foundations
- **4 sales roles** added (Business Development Manager, Key Accounts Manager,
  Marketing Manager, Marketing Executive) with sensible scope + management
  classification; they appear in the user-creation role dropdown.
- **CRM permission selection list** built: 4 access modules (Inquiries, Quotations,
  Orders/contracts, Sales reports) each with View/Edit, plus fine-grained actions
  (create / approve / send quote, manage follow-ups, register client+contract,
  manage templates) — all editable per role (Settings → Roles & access) and per user.
- **Lost-reason master** (`quote_lost_reason`, 12 researched reasons incl. "Other
  (specify)" → free-text on the form in P1) + **CRM service-type master**
  (`crm_service_type`, §18/§25) — both editable lookups.
- **CRM data model created** (idempotent, SQLite+MySQL): `crm_inquiries`,
  `quotations` (+rev/parent for §23 revisions, advance/report-vs-payment flags),
  `quote_lines` (business unit per line, order type, units), `quote_revisions`,
  `quote_approvals`, `quote_approval_rules` (amount band and/or business unit — §owner),
  `quote_followups`, `crm_templates` (docx + email, with a JSON field map for §6).
- Boot probe extended so live MySQL auto-creates the CRM tables on next load.
- **Next: P1** — Inquiry + Quotation screens (list/form/detail), quote numbering,
  revisions, dropdown-driven entry.

### ✅ P1 shipped (2026-07) — Inquiry + Quotation core
- **Inquiry register** (§1): list + new/edit, auto `INQ-#####` number, client/contact/
  business unit/source/status, "Quote" button that carries an inquiry into a new quotation.
- **Quotation core** (§2,3,4,8,14,23): list with **Open / Pending / Closed(won) / Lost**
  views + KPI counts; new/edit form with header (client, contacts, business unit, site +
  location type, validity, payment terms, **advance % + advance-required**,
  **report-vs-payment** flag, GST) and **dynamic line items** (business unit per line, service
  type, order type OPEN/LINE, qty/unit/rate, live totals); auto `Q-#####` number.
- **Status workflow**: Draft → Submit → Approve → Send → Accepted / Lost, gated by
  the CRM permissions; on **Sent** the 3/6/9-day, fortnight, month **follow-ups are
  scheduled**; on **Lost** the researched reason dropdown + "Other (specify)" free text.
- **Revisions** (§23): "Revise" creates Rev 01/02… as a fresh draft, keeps the old
  version + a change-note in history; list shows only the current revision.
- CRM nav group (Sales / CRM → Inquiries, Quotations) gated on the CRM modules.
- **KNOWN DEV-ONLY QUIRK:** the *revise* row-copy binds only leading columns under
  **SQLite + PHP's built-in `php -S` dev server** (a pdo_sqlite/built-in-server
  defect — every CLI run and the create/status/lost paths are fine). **Production is
  MySQL, which uses real prepared statements and is unaffected.** Verify "Revise" once
  on the live site; if a revision ever copies blank there, tell me.
### ✅ P2 shipped (2026-07) — .docx quote template engine
- **Quote templates admin** (CRM → Quotations → Templates, gated by
  `crm.template.manage`): upload the Word quotation **format** (.docx), set it default,
  activate/deactivate, re-upload a revised format anytime, download the original.
- **Controlled-document identity carried from the uploaded format** (owner's ask):
  each template stores a **Document number, Format number, Revision and Issue date**;
  these stamp onto every generated quote via `{{doc_number}}` / `{{format_number}}` /
  `{{doc_rev}}` / `{{doc_date}}`. Upload a revised format with a new number → new quotes
  show the new number automatically.
- **Token engine** (no external library — `ZipArchive` + string replace): fills header
  tokens (quote no, client, contacts, business unit, commercials, totals, **amount in words**),
  **repeats a table row per line item** (`{{l_desc}}` etc.), and **repairs tokens Word
  splits across runs** (verified: `{{cli|ent_name}}` rejoined correctly). Tokens are
  documented on the template form.
- **"⬇ Generate Word quote"** button on the quote detail → downloads the filled .docx.
- Verified end-to-end over HTTP (upload → create quote → generate): doc/format numbers
  stamped, line rows repeated, totals + words correct, no unreplaced tokens.
### ✅ P3 shipped (2026-07) — approval chain + send + follow-up emails
- **Configurable approval matrix** (Quotations → Approval rules): rules by **amount
  band** and/or **business unit**, with a **level** (chain order) and an **approver role or a
  specific person**. On "Submit for approval" the matching rules become the quote's
  chain; with no rule it needs one approval from any approver.
- **Approval flow**: each approver sees Approve/Reject (with remarks) for their step
  on the quote; the quote auto-moves to **Approved** when all steps pass; a reject
  sends it back to draft. Gated by the "Approve quotations" permission.
- **Send to customer** (§10): "✉ Send to customer" generates the .docx and **e-mails
  it (attached) to the contact** using the EMAIL_QUOTE template (or a sensible default),
  marks Sent, and schedules the follow-ups. Uses the existing SMTP settings; if SMTP
  isn't set it's logged (email_log) and still marked sent.
- **E-mail now supports attachments** (multipart/mixed added to smtp_send/ops_mail).
- **Follow-up e-mails** (§11): `crm_run_followups()` (wired into cron.php) sends any due
  3/6/9-day, fortnight, month follow-up whose quote is still awaiting a reply, using the
  EMAIL_FOLLOWUP template; skips once the quote is accepted/lost.
- Verified end-to-end: rule match → chain built → approve → Approved → send (logged) →
  follow-up cron sends the due one and skips the rest.
### ✅ P4 shipped (2026-07) — acceptance → contract → Operations hand-off
- **Acceptance → register client** (§12): marking a quote **Accepted (won)** opens an
  Accounts panel; entering the **contract number** auto-registers the customer as a
  client (if only a name was typed → `GEN-<name>-####`) and links `client_id`.
- **Contract record** (§13): a `partner_contracts` row is created (number, title,
  value, dates) and linked to the quotation (`contract_id`, `contract_number`).
- **Operations packet auto-floated** (§13): on contract entry an e-mail goes to the
  coordinators + ops managers with **client, quotation no, contract no, contact
  person/email/mobile, business unit, location, value, advance/report-vs-payment flags, the
  service requirement, and the order lines** — with the **techno-commercial (.docx)
  attached**. A "Re-send to operations" button re-floats it.
- **Open (ARC) vs line-item orders** (§17): each order line is labelled
  **[Open order (ARC / call-off)]** or **[Line-item order]** in the packet (the type
  is captured per line on the quote).
- Verified end-to-end: typed-name client auto-registered (GEN-BRAND-0001), contract
  linked (₹7,67,000), packet body correct with ARC vs line-item labels + service req.
### ✅ P5 shipped (2026-07) — operations / revenue integration
- **Job ↔ quotation link** (§16): the job allocate/edit form has an "Against quotation
  / contract" picker (accepted/in-flight quotes for that client); `jobs.quotation_id`.
- **Revenue per quote/contract** (§16): the quote detail shows a "Jobs &amp; revenue
  against this order" panel — ordered vs invoiced vs received, with each linked job.
- **Advance / report HOLD for the inspector** (§21,§22): linking a job inherits the
  quote's **advance-required / advance-% / report-vs-payment** onto the job; the
  inspector's **My Jobs** cards and the job detail show a red **"HOLD — do not issue the
  report/deliverable"** banner while the advance/payment is pending. Coordinator/Accounts
  can **Mark advance received** (`/job-advance`); the hold clears on payment.
- **Deliverables from the quote** (§24): a linked job with no deliverables inherits the
  ones listed on the quote's lines.
- Verified: inherit set adv_required/adv_pct/report_hold + deliverables (IR,COC), both
  HOLD reasons shown when unpaid and cleared when paid, revenue panel + advance toggle.
### ✅ P6 shipped (2026-07) — CV analysis + client-submission tracking
- **CV keyword analysis &amp; search** (owner's ask): on the candidate, upload the CV
  (.docx / .txt; .pdf best-effort) or paste the text → an **internal, dependency-free
  engine** extracts keywords (a curated inspection/QA-QC/TIC vocabulary — CSWIP, NACE,
  ASNT, NDT methods, API/ASME codes, disciplines, sectors — plus the trade/skill masters
  and top frequent terms) and stores them. Keywords show as clickable chips; the hiring
  search now matches **cv_keywords + cv_text**, so you can find CVs by skill for future
  requirements. **AI-ready:** `cv_extract_keywords()` has a `cv_ai_available()` seam so
  it can defer to a provider once the AI-keys feature lands — no caller changes needed.
- **CV-to-client tracking** (§20): per candidate — CV submitted-to-client date, client
  feedback (Shortlisted / Rejected) + date + note, interview required / planned-for /
  completed-on / outcome (Selected / Rejected / Hold).
- **On Selected → credential-request e-mail** (§20): one click e-mails the candidate for
  CV, salary slips, IDs, certificates (EMAIL_CREDENTIAL template or a sensible default).
- Verified: keyword extraction, keyword search hit, tracking saved, credential e-mail
  logged to the candidate.
### ✅ P7 shipped (2026-07) — Sales / CRM dashboard + monthly report + win/loss
- **Sales dashboard** (CRM → Sales dashboard, gated `mod.crm_reports.view`): FY-filtered,
  scope-aware. KPIs — quotations, **open pipeline value**, **won value**, **win rate**.
- Charts: quotes by status (donut), quoted value by business unit, **top customers by quoted &
  by won value**, and **"Why we lost" win/loss** breakdown by reason (§ lost-reason master).
- **Monthly performance table** (§15): per month — raised / won / lost / won value.
- **CSV export** of all quotes in scope for the FY.

### ✅ Client PDF + signature + customisable letterhead (2026-07)
- **Client-facing quote is now a PDF** (the .docx stays for internal editing). A
  dependency-free pure-PHP writer (`lib/pdf.php`, no Composer/library) renders a
  professional quotation — line items, totals, amount-in-words, terms — and the
  **"Send to customer" e-mail now attaches the PDF**. Buttons on the quote: **PDF (for
  client)** + **Word (editable)**.
- **Signature image** (upload PNG/JPG under CRM → Templates) + name/designation are
  **stamped on the PDF** (GD normalises PNG → JPEG; embedded via DCTDecode).
- **Customisable per-company letterhead** (owner's ask): upload **logo**, set company
  **name / address / contact line / footer** — rendered as the PDF letterhead; the
  document & format numbers from the uploaded format print top-right.
- Verified: valid PDF (correct xref/EOF), letterhead + logo + signature embedded,
  generated over HTTP (application/pdf), admin panels save.

### ✅ CRM ROADMAP COMPLETE (P0–P7)
Inquiry → quotation (+ revisions, Word template w/ doc & format numbers) → approval
chain (amount/business unit) → send-to-customer (Word attached) → follow-up e-mails → acceptance
→ client + contract registration → Operations hand-off → job link (revenue, HOLD,
deliverables) → CV analysis + client-submission tracking → sales dashboard/reports.
Remaining big items: the **AI-keys** master-settings feature, then **dashboards for all
roles** (incl. the Executive-Director rebuild).

### ✅ AI keys in master settings — SHIPPED (2026-07)
- **Settings → AI providers & models** (`/ai-settings`, master/settings.manage): store an
  **API key per provider** — OpenAI, Claude (Anthropic), Google Gemini, Perplexity,
  GitHub Copilot / Models — masked in the UI (never shown in full; re-save with the mask
  keeps the key; "Clear key" removes it). Per-provider **enable** toggle + optional base URL.
- **Auto-refreshing model lists:** "Refresh models" pulls each provider's live list
  (OpenAI `/v1/models`, Anthropic models list, Gemini `/v1beta/models`; Copilot catalog);
  **retired models drop off** (active selection is intersected with the fresh list).
  Providers without a public list API (Perplexity) use a curated known-model list; any
  refresh failure falls back to known models so a model can still be picked.
- **Pick active model(s)** per provider (checkbox grid).
- Foundation helpers `ai_enabled()` / `ai_active()` + a proxy/CA-aware cURL client;
  `cv_ai_available()` now reflects the config so the CV keyword engine can defer to AI.
- Verified: save/mask/enable/select, masked re-save keeps key, refresh fallback works.
- **Follow-up (not yet wired):** actually calling the selected model in features (e.g.
  CV analysis, quote drafting) — a generic `ai_chat()` per provider. Keys/models are ready.

### 🤖 (superseded) original AI-keys request note
Administrator can, under master settings, enter **API keys for multiple AI
platforms** of their choice — **Copilot, Gemini, Claude, Perplexity, OpenAI** —
and then **select which model(s)** to use under each provider. Requirements:
- Per-provider key entry (stored in settings; masked in the UI).
- Model list **auto-updates** from each provider and **auto-discards** old/retired
  models no longer offered (so the dropdown always reflects live availability).
- Select one/more active models per enabled provider.
- Research note: auto-refresh needs each provider's "list models" endpoint (e.g.
  OpenAI `/v1/models`, Gemini `/v1beta/models`, Anthropic models list); "Copilot"
  has no public models API — confirm whether that means GitHub Models or Azure
  OpenAI. Outbound network from MilesWeb must be confirmed. To sequence after (or
  alongside) CRM — owner to confirm ordering.

### PARKED (do after CRM): Executive-Director dashboard rebuild
Current landing dashboard for the Business/Executive Director "is not what we're
expecting." Rebuild to a strategic C-suite view (pipeline value, win rate, revenue
by business unit/customer/project, forecast vs actual, top accounts) — AFTER the CRM lands so
it can draw on real pipeline data.

## ✅ Just shipped (2026-07 — owner's screenshot batch)
- **Distinct employee-code series for contractors.** A new inspector saved with a
  blank Employee code now auto-gets a code by engagement kind: **SC-###** for
  sub-contractors, **FL-###** for freelancers, **EMP##** for our own staff — so
  payroll/accounts can tell them apart at a glance. Manually typed codes are kept
  as-is. Demo sub-con Mohan reseeds as **SC-001**. (`next_emp_code()` in ops.php.)
- **"Food bills (actual)" expense head** added alongside "Food allowance (meals)"
  (now an ALLOWANCE; the new head is an actual BILL needing a receipt). Expense
  heads are now ensured **by code** on boot, so existing live databases gain the
  new head automatically without wiping custom heads.
- **contract numbers list** (Profitability screen) rebuilt as an accessible table with
  **Sr No · contract number · Client · Status · Created on · Expires on · Renewed into
  (renewal hierarchy) · Jobs · Invoicing done · Expenses booked** + salary-gated
  **Salary costing · Profit INR · Profit %**, KPI cards, expiry pills, and CSV.
- **Vouchers screen role-scoped cards** — Total expense claimed · This month ·
  Awaiting approval · Paid, scoped to the role (inspector sees only their own).
- **Insights dashboard (/reports):** added a **client-name filter** to the filter
  bar, a **Top 10 customers by revenue** chart and a **Revenue by contract**
  chart in the Financial section. The **Certificates-expiring** panel is now hidden
  for the **Business Director** role (strategic view, not an ops-compliance task).
- **Demo reload guidance:** the "already loaded" message now tells the user to
  Remove + Load again to pick up newer sample records. Root cause of "agencies /
  requisitions look empty" is the one-shot `demo_seeded` flag — the seed itself is
  correct (verified: 2 agencies, 2 requisitions render for admin *and* director).
- Remaining voucher/contract polish parked below (§ Reports Phase 2, deputation).

## 🆕 Requested — to build next (noted 2026-07, owner)

### 1a-i. Recruitment-fee costing — the GUARANTEE model (resolves owner's confusion)
The one-time recruitment fee is **conditional**, so it is NOT a fixed cost:
- Agency contract carries a **guarantee / replacement period** (e.g. 90 days).
- On the hire, the fee has a status: **Provisional** (still inside the guarantee
  window) → **Confirmed** (person stayed past it → fee is a real cost) OR
  **Waived** (person left inside the window → we don't pay; agency gives a free
  replacement → fee cost = 0, carried to the replacement).
- **Costing rule:** the fee counts in the inspector's cost **only when Confirmed**;
  shown as "provisional" until the guarantee lapses; ₹0 if Waived. No arbitrary
  monthly spreading. Build: `guarantee_days` on the agency; `fee_status` +
  `guarantee_upto` on the inspector; a small daily check (cron) that flips
  Provisional→Confirmed when the date passes; a "provisional fees / guarantees
  lapsing" dashboard card.

### 1a-ii. Offer stage: released → declined (candidate reneges)
- Add pipeline stages **Offer released** and **Offer declined** (candidate backed
  out at the last moment) so we can see offer-decline rate and re-open the
  requisition to the next candidate.

### 1e. Manpower Requisition / Position Approval module — ✅ CORE DONE
- [x] **DONE** — `requisitions` (New/Replacement, office/business unit/designation/site,
      budgeted cost, approval ref/date/by, status Open→Proposed→Offer→Hired→
      Closed); Requisitions screen (list/form/detail) under Hiring; REPLACEMENT
      links the outgoing engineer; detail shows **Outgoing vs Budgeted vs Hired**
      monthly-cost comparison (salary-gated); the candidate CV form **requires an
      approved requisition** and Accept auto-fills it (status→HIRED, inspector
      linked); sidebar item + dashboard "open requisitions" card. Guarantee-fee
      costing + Offer/Declined stages also done (§1a-i, §1a-ii).
- [ ] **Remaining polish**: hard-block hiring if no requisition (currently the
      form requires it, but server doesn't reject a hand-crafted post); WAIVE the
      placement fee automatically when a replacement is raised for someone who
      left within guarantee; requisition CSV/PDF approval register; email on
      approval / on fill.
- [ ] *(original design, for reference)* Management approves **every position**
Management approves **every position** (new or replacement); the whole hiring
chain hangs off that approval.
- [ ] **`requisitions` table**: req_code, office, business unit, designation/position,
      project/site, **type NEW vs REPLACEMENT**, budgeted monthly cost, approved_by,
      approval ref + date, status (Open → Proposed → Offer released → Hired →
      Closed / Cancelled), notes.
- [ ] **Replacement linkage**: when REPLACEMENT, link the **outgoing (resigned)
      inspector** — auto-fetch their current salary/cost as the benchmark.
- [ ] **Candidate ↔ requisition**: a candidate CV is raised **against a
      requisition**; the pipeline (with the new offer stages) runs inside it; on
      Accept the **hired candidate → inspector** is linked back to the requisition
      and it closes.
- [ ] **Auto-fetch salary / cost comparison**: pull the proposed candidate's
      expected rate and the hired inspector's salary_ctc / agency_cost; for a
      replacement, show **outgoing vs new** cost side by side (budget vs actual).
- [ ] **Dashboard**: open requisitions, positions pending fill, replacements in
      progress; approval register export (CSV/PDF).
This becomes the front of the hiring flow:
**Requisition (approval) → Candidate(s) → Offer → Hire (inspector) → close**,
and feeds the agency/roll/fee logic already built.

### 1. Complete demo / seed dataset (uploadable, all expected values) — ✅ DONE
- [x] **200+ edge cases** — DONE. The seed now generates **332 edge-case records**
      (150 calls + 150 jobs + 32 vouchers) covering same/cross office, missing
      vendor/dates, overdue, zero & large amounts, billable-vs-credit, every
      invoice/payment/credit state, sub-con, zero man-days, 0-day/null TAT, all
      stages, and voucher statuses incl. leave-only zero-total. Count shown on load.
- [x] **DONE** — `lib/seed_demo.php` + a Master-Admin-only **"Load demo data"**
      button in **Settings** (POST `/seed-demo`, idempotent via `demo_seeded`).
      One click inserts 3 peer offices (Mumbai HO + Ahmedabad + Pune), 11 users
      (every role, password `demo12345`), 4 inspectors (incl. an agency sub-con)
      with entitlements, 3 clients + 2 vendors, 3 contract numbers, 6 calls, 6 jobs
      across the full lifecycle (paid / awaiting / overdue / unbilled / in-progress
      / sub-con), closure expenses, and 2 vouchers (DRAFT + APPROVED). Every
      screen shows live figures immediately. *Follow-ups when the credit rules
      below land: extend the seed with same-vs-different-office credit examples.*
- [ ] *(original ask, for reference)* A **ready-made sample dataset** that can be loaded into a fresh install so
      the whole system can be explored end-to-end with realistic values —
      **from user creation → multiple offices → clients/vendors → contract
      numbers → calls → job allocation & scheduling → inspection → voucher
      (km + bills) → closure → invoicing → payment → inter-office credit.**
      Purpose: demos, training, and testing every screen with data already in
      place. Should include: several **offices** (peer offices + Mumbai as the
      commercial HO), **users of every role** (Master Admin, Business Director,
      Business Unit Head, Branch/Branch-App Manager, Operation/Asst. Manager, Coordinator,
      Accountant, Inspector), **inspectors** with salary + entitlements, a few
      **clients/vendors/sites**, **contract numbers**, a spread of **calls & jobs**
      (some same-office, some cross-office), **completed vouchers**, and
      **invoiced + paid + credit-settled** examples so profitability, dashboards
      and the money desk all show live figures immediately. Delivered as a
      one-click "Load demo data" action or an importable seed the owner can run.

### 1b. Full access / permission control (every module & feature) — ✅ DONE
- [x] **DONE** — per-module **View + Edit** permissions for all 14 modules
      (Calls, Jobs, Vouchers, Invoicing, Profitability, Hiring, Reconcile,
      Clients, Vendors, Masters, Overheads, Reports, Users, Settings) plus the
      fine data/feature perms. Managed **both** ways: a **Settings → Roles &
      access** editor (per-role, "edit implies view", stored as an override) and
      the **per-user** panel (full checklist). Sidebar + every module route are
      gated on the view perm; inspector My Jobs/My Voucher stay exempt;
      backward-compatible for users saved before the change. Verified across all
      demo roles. *Follow-up ideas: office-scoped module grants; an audit log of
      access changes.*
- [ ] *(original ask, for reference)* **Comprehensive access matrix** — today only ~17 permissions exist and most
      screens are gated by *role level* (coordinator/admin), not fine permissions.
      Owner wants the super admin to grant/deny **each and every module and
      feature**, not a limited set, and to manage it **in Settings** (like the
      permission checkboxes already in the user-create panel, but complete).
      Build: (a) expand the `PERMISSIONS` catalogue to one "can access" entry per
      module — Calls, Jobs, Vouchers, Invoicing, Profitability, Hiring, Reconcile,
      Clients, Vendors, Masters, Overheads, Reports, Users, Settings — plus the
      finer action perms (create call, allocate/close job, see credit, see salary,
      manage masters, reconcile, etc.), grouped by module in the user panel;
      (b) gate the sidebar nav + each route on these perms (defaulting them ON for
      roles that have access today, so nothing locks out); (c) a **Settings →
      Roles & access** editor — a role × permission grid the super admin edits,
      stored in settings and overriding `role_defaults()`. Backbone change —
      scope confirmed with owner before building.
- [x] **Clearer "inspector not linked" message** — DONE. An Inspector login with
      no linked inspector profile now gets one actionable message on My Jobs and
      My Voucher (Users → Linked inspector) instead of "You cannot view vouchers".

### 1c. Agency master + contracts + roll-conversion + costing — ✅ CORE DONE
Two agency types, each with a contract and a different fee model — both feed
**inspector costing** and need **renewal reminders**.
- [x] **DONE** — `agencies` master (type Recruitment/Manpower, contact, contract
      no. + start/end, one-time fee, monthly rate); **renewal reminder card** on
      the dashboard (≤30 days, colour-coded); Candidate **Accept** now picks the
      supplying agency + roll (own payroll vs agency) + fee, and the new inspector stores
      agency_id / roll_type / placement_fee (one-time, tracked separately) /
      agency_cost (monthly, into loaded cost). One-time recruitment fee is
      **recorded, not amortised** (owner: tenure is unpredictable).
- [ ] **Remaining follow-ups**: (a) show/edit agency, roll, placement fee on the
      **inspector edit form** + inspector costing breakdown; (b) turn the renewal
      reminder into an **email** via `cron.php` (currently a dashboard card only);
      (c) **manpower pass-through invoicing** — we invoice the client our rate
      while the agency bills us their monthly charge (margin = our rate − agency
      charge); ties into §1d monthly invoicing.

- [ ] **Agency master with a type**: **Recruitment agency** (CVs only, one-time
      placement) vs **Manpower / supply agency** (supplies people we run).
      Reuse/extend `subcons` or `business_partners` (is_subcontractor) with a
      `agency_type` flag.
- [ ] **Agency CONTRACT with renewal reminder** — every agency engagement is a
      contract with a start/end (or renewal) date. Send a **reminder ~1 month
      before the due/renewal date** (reuse the existing cert-expiry reminder
      pattern in `cron.php` + a dashboard "expiring soon" card). Applies to
      BOTH recruitment and manpower agencies.
- [ ] **Fee model by type → inspector costing**:
      • **Recruitment** → person is on **our own payroll** (salary CTC) **plus a
        one-time fixed placement/consulting fee** paid to the agency; that fee is
        **included in the inspector's costing** (decide: one-time in the hire
        month vs amortised over expected tenure — confirm with owner).
      • **Manpower** → agency **bills us monthly**; that monthly charge is the
        inspector's `agency_cost`, and **we invoice the client** for the manpower
        (pass-through — ties to §1d monthly invoicing).
- [ ] **On Accept, choose the roll + agency + fee**: own payroll (salary) vs agency
      roll (monthly charge); pick the agency (from the master) and its
      contract; capture the one-time fee (recruitment) or monthly charge
      (manpower). Writes `agency_name` + `agency_cost` (+ new one-time-fee field)
      on the inspector so loaded-cost/profitability already reflect it.

### 1d. Monthly / recurring invoicing for deputation (man-month / man-day)
- [ ] **NOT built yet.** Invoicing is currently **one invoice per job**. A
      man-month resident deputation (or a man-day contract billed monthly) needs
      a **billing schedule**: for a deputation job with a rate + start/end,
      generate a **monthly invoice line** per active month, so the accountant
      gets a **month-wise list of pending invoices** ("Deputations to bill for
      July", man-day contracts rolling up that month's man-days, etc.). Pairs
      with the Invoicing FY/Month filter (item under §2 reports). Applies to
      man-month, man-day and lumpsum. New model: an `invoices` / `billing_lines`
      table keyed by job + month, feeding the money desk and CSV/PDF exports.

### 2. Credit tab — driven by contracting vs executing office — ✅ DONE
- [x] **DONE** — calls carry a **contracting office** + executing office. On the
      call form the credit section toggles: **same office → "Billable value
      (ex-GST)" + basis** (no inter-office credit); **different office → "Credit
      to executing office" + type** (mandatory). Call detail shows a "Credit /
      billing & cost" panel; for cross-office the **executing office can revert
      with the credit it requires** (COUNTERED / AGREED). **Cost incurred**
      (vouchers + expenses) is shown to **both** offices, and the calls list is
      visible to both contracting & executing offices with a **cost column +
      min-cost filter**. (Also fixed a latent scope bug so branch users actually
      see their office's records.) *Follow-ups: voucher auto-download+submit step;
      email the executing office when credit is proposed/countered.*
- [ ] *(original ask, for reference)* **Same contracting & executing office** → the **Credit tab is DISABLED**
      (no inter-office credit to record), BUT the call must still **show the
      billable value** — invoice / **man-day** / **man-month** value —
      **excluding GST**. (So a single-office job still shows what it's worth,
      just with no credit hand-off.)
- [ ] **Different contracting & executing office** → the **Credit tab is FULLY
      OPEN**. The **credit to be given to the executing office** is **clearly
      stated** on the call. The **executing office can revert with the value of
      credit it requires** for that call (a counter-value / negotiation back to
      the contracting office), so both sides agree the credit.
- [ ] **Voucher officially downloaded & submitted** — the prepared voucher
      (Statement of Travelling Expenses) must be **officially downloadable** and
      **submitted** as the record for the call (PDF download + submit step).
- [ ] **Both offices see the spend** — the **contracting office AND the executing
      office** can both **check the amount spent on the call**. Both can **filter
      the inspection list and see the cost incurred** (including **all expenses**)
      for each inspection, shown to each office **according to its scope**
      (contracting sees its calls; executing sees the calls it executed).
- [ ] **Invoicing filters** — the Invoicing / money desk (`/invoicing`) needs a
      **filter bar** like the Dashboards: **Financial Year, Month**, plus office,
      business unit, client and status bucket (pending / awaiting / overdue / credit). The
      counts and worklist recompute for the chosen period so an accountant can
      pull, e.g., "unpaid invoices for FY 2026-27, July, Ahmedabad." Filters
      respect the user's scope, and the filtered view should also be exportable
      (ties into the downloadable-reports item below).

### 3. Downloadable reports — ✅ PHASE 1 DONE (CSV exports)
- [x] **DONE** — dependency-free CSV export (UTF-8 BOM for Excel). "Download CSV"
      buttons on **Jobs, Calls (with cost incurred), Invoicing, Profitability**
      (each respects the current scope + filters), a **Download-reports** section
      on the Dashboards page (permission-gated), and **voucher download**
      (`/voucher-csv` → full Statement of Travelling Expenses, plus Print/Save-PDF).
      *Remaining from the catalogue below (future): TAT report, office/Business Unit P&L,
      utilization/productivity, overdue-aging, inter-office credit statement,
      and PDF statements for invoices/credit notes.*

### 3b. Downloadable reports — remaining catalogue (research, for later)
Goal: let every function pull the data it needs as a file (Excel/CSV for
analysis, PDF for official statements). Proposed catalogue to build:

**Operations**
- [ ] Call register (with lead-times, status, pending-scheduling flag)
- [ ] Job register / allocation report (inspector, dates, contract numbers, status)
- [ ] **TAT report** — on-time vs late, average TAT, by office / business unit / inspector
- [ ] Overdue-closure report (jobs past scheduled/required date)
- [ ] Scheduling / dispatch board export (what's due, who's free)
- [ ] Inspection volume by client / vendor / site / inspection-type

**Finance**
- [ ] **Profitability by contract / contract** (revenue − labour − exp − subcon − OH − contingency)
- [ ] **Office P&L** (per peer office; own targets vs achieved)
- [ ] **Business Unit P&L** (credit vs distributed loaded cost vs net)
- [ ] **Voucher / expense register** (per inspector/month, per expense head)
- [ ] **Invoicing & payment** — raised / received / outstanding / **overdue aging** (30/60/90)
- [ ] **Inter-office credit statement** — given vs received, expected vs actual, reconciliation
- [ ] Expense analysis by head (travel, food, lodging, bills, …)
- [ ] Cost-per-call and cost-per-man-day
- [ ] **Billable value (ex-GST)** per call — man-day / man-month / invoice value

**Efficiency / People**
- [ ] **Inspector utilization** (man-days, % of working days) monthly + trend
- [ ] Attendance / leave summary (from voucher-derived present/leave)
- [ ] Inspector productivity (jobs, man-days, credit, cost, net)
- [ ] Work-type mix (day-based vs deputation vs sub-con)
- [ ] Certificate-expiry / compliance report

**Formats & mechanics to decide**: CSV + Excel (`.xls` via HTML table or
`.csv`, no library needed on MilesWeb) for data; **PDF/print** for official
statements (voucher, credit note, invoice summary) using the existing
print-page approach; every report **respects the user's office/business unit scope** and
the current dashboard **filters** (FY, month, office, business unit, inspector).


## 🚧 Expense / Inspector-Voucher module (IN PROGRESS — the profitability engine)

Modelled on the real "Statement of Travelling Expenses" the inspectors use.
Wide grid: one column per configurable expense head, TOTAL row at bottom + grand
total. Auto-fills from Jobs; inspector only enters hours + km + bills.

- [x] **P1 · Masters & codes** — `expense_heads` master (code, type PER_KM/BILL/
      ALLOWANCE, default rate, needs-receipt, column order — each is a voucher
      column); `travel_modes` master (Bike ₹6, Car ₹12, Own-car, Ola/Uber, Auto,
      Bus, Train, Air — per-km vs actual); `leave_type` + `day_code` lookups
      (CL/SL/PL/LWP/COMPOFF/ML + OFFICE/WFH/TRAINING/HOLIDAY/WEEKOFF). Seeded on
      fresh + upgrade; both masters on the Masters page; boot probes added.
- [x] **P2 · Inspector entitlements (Super-Admin only)** — DONE.
      `inspector_allowances` table + a "🔒 Allowances & rates" panel on the
      inspector edit page. Per inspector: tick which travel modes & expense heads
      they may claim, and set a **personal rate override** (blank = master
      default). Helpers `inspector_mode_rate()` / `inspector_head_allowed()` /
      `inspector_mode_allowed()` drive the voucher later. Verified: panel + save
      work for Super Admin; a normal inspector save does NOT wipe the
      entitlements; and a Branch Manager cannot see the panel nor POST to it
      (0 rows written). Boot probe added.
- [x] **P3 · Voucher auto-fill** — DONE. `vouchers` + `voucher_entries` tables;
      new **Vouchers** tab (inspectors see "My Voucher"). Open/create a voucher
      per inspector+month; **"Pull working days from jobs"** auto-fills one row per
      inspection day (date, **vendor display name** as site, **File No = contract number**,
      business unit, 8h, tagged `auto`) — idempotent, never duplicates. **Multiple rows per
      date** supported with a per-day **hours subtotal** + month total; hours
      **editable**; **Line No editable** (from Accounts), File No editable on work
      rows. Add non-inspection days (Office / Leave-with-code / Holiday / Week-off).
      Access: inspector = own; coordinator+ = any. Verified end-to-end; boot probe
      added. (KM/expense columns + totals arrive in P4.)
- [x] **P4 · Fast entry** — DONE. Wide grid: per row a **Mode** select + **KM**
      (auto-filled from vendor memory ↺, editable) → **Travel ₹ = km × the
      inspector's entitled rate**, plus one **bill column per entitled expense
      head** (only the heads/modes the Super Admin allowed appear). **Bottom TOTAL
      row** sums every column + **Grand Total**; JS recomputes live as you type,
      server recomputes authoritatively on **Save all**. `vendor_km_memory`
      remembers km per inspector+vendor. Verified: Bike ₹6 → 38/40/38 km, Food/
      Hotel bills, grand total ₹1,839, memory stored, Bus/Train hidden (not
      entitled). Boot probe added. *(One supporting file per voucher lands in P5.)*
- [x] **P5 · Output & workflow** — DONE. On the voucher: a **Summary — particulars**
      panel (Travel + each head → Grand Total, Less Advance, Less Office-incurred,
      **Balance to be paid/recovered**); a **single supporting file** per voucher
      (one upload backs all bills; streamed via `/voucher-file`); a **printable
      "Statement of Travelling Expenses"** (`/voucher-print`, standalone, browser
      print) matching the real format with the 3 signature blocks; and the
      **status workflow** DRAFT → SUBMITTED → APPROVED → PAID with **Checked /
      Approved / Authorized** captured, edit-locked once out of DRAFT, and Reopen.
      Verified: total ₹1,611, balance ₹1,011, file round-trip, print page, all
      transitions. `supporting_mime` migration + boot-safe.
- [x] **P6 · Attendance reconciliation** — DONE. New **Reconcile** tab. Upload the
      HR payroll Leave &amp; Attendance export **saved as CSV**; it is parsed **in
      memory only and never stored** (respects "don't copy the company doc").
      Auto-detects the header row + Employee Code / Present / Leave columns, matches
      by Employee Code to the inspector master, and compares **HR present/leave vs
      the app's voucher-derived present/leave** for the month — flagging OK /
      MISMATCH / In-HR-only / In-app-only with the differing cells highlighted.
      Verified: match=OK, HR4-vs-app2=MISMATCH, unknown code=In-HR-only.
- [x] **P7 · Profitability by contract number/Contract** — DONE. New **Profitability** tab
      (gated by new `data.profitability` perm — granted to Master Admin, Business
      Director, Business Unit Head, Branch Manager, **Operation Manager** [manager under the
      branch manager] and Finance; **not** Coordinator/Inspector). List of contract number
      numbers with Revenue / Expenses / Sub-con / Labour / **Margin ₹ + %**;
      detail page with stat row + **expense drill-down** (each line shows which
      inspector visited which vendor, hours, travel + bills + line total, with a
      **+ toggle** for the per-head breakdown) + invoice/job lines. Expenses roll
      voucher `row_total` (by boss_id) + job-closure expenses. Labour counted only
      when salary is visible (else "Contribution"). Verified: revenue 50k, expenses
      668, margin/%, drill-down. Super Admin can grant/revoke the perm per user.
- [x] **P8 · Contract/contract carry-forward** — DONE. On a contract profitability page,
      **Renew / change contract number (ARC/Open)** creates a new contract number
      linked to the old (`supersedes`/`superseded_by`), carries the **open jobs
      (and their voucher lines) forward** to the new number, closes the old, and
      shows the chain both ways ("continues from…", "renewed as…"). Closed/
      historical jobs stay on the old number. Verified: open job → new, closed job
      stays, chain linked.

## UI/UX rebuild v2 (staged, signed off) — DONE

Full app-wide rebuild on the new design system, done screen-by-screen with
sign-off between stages. Sidebar + slim top bar replace the old header; a
role-aware dashboard, an accountant money desk, and every core screen now share
the same cards / status pills / summary chips / clean tables — all driven by
the theme builder (no colour hardcoded, no CSS variable renamed).

- [x] **Sidebar shell** — grouped left rail (Operations / Money / Insights /
      Directory / Admin) with active highlighting + per-role visibility; slim
      top bar (office + FY chips, search, user); mobile drawer + scrim.
- [x] **Role-aware dashboard** — one template filled per role & scope (Director,
      Business Unit Head, Branch/Branch-App Manager, Manager/Asst, Coordinator, Accountant,
      Inspector); KPI tiles, money desk, expected-credit-by-office bars, job
      donut, quick actions, pending-scheduling — sections shown by permission,
      ordered per role.
- [x] **Accountant money desk** (`/invoicing`) — confirm-cards (Invoice pending /
      Awaiting payment / Overdue / Credit not received) over a worklist that
      writes the invoice/payment/credit fields already on jobs; office-scoped.
- [x] **Inspector phone view** — card-based My Jobs (To do / Completed, overdue
      flag) + a mobile bottom tab bar (Home / My Jobs / Voucher).
- [x] **Jobs & Calls lists** — summary chips + clean tables + status/money pills.
- [x] **Voucher grid** — status pill + headline KPI strip; mechanics (form=,
      live recalc, totals) untouched.
- [x] **Profitability list + detail** — KPI cards, margin pills, clean drill-down.
- [x] **Reports/Dashboards** — theme-variable colours; sticky filter re-aligned.

## UI/UX refresh — DONE

- [x] **Role-appropriate landing** — every user lands on the **home dashboard**
      after login (login → `/`); it is role-aware (managers get New Call / Jobs /
      Vouchers / Profitability / Dashboards / Masters; inspectors get My Jobs /
      My Voucher) with KPI cards + a live status chart, and all other screens are
      reached from there.
- [x] **Agency hiring cost on inspector** — when an engineer is engaged via an
      external agency, capture the **hiring agency** + **annual agency cost** on
      the inspector (salary-gated). It adds to that engineer's loaded labour
      (`salary_ctc + agency_cost`) so profitability/dashboards reflect the true
      cost. Verified: ₹240k agency cost on ₹600k CTC raised loaded labour
      correctly.
- [x] **Dashboards visual polish** — filter bar is now a sticky card; the four
      family sections (Operations / Financial / Utilization / People) have bold
      accent-underlined headers; chart panel sub-headers tidied.


- [x] **Professional UI refresh** — a design layer on top of `app.css` (sticky
      top bar with pill-hover nav, softer card radii + real elevation, gradient
      buttons with coloured shadow, soft form fields with focus rings, hover-lift
      stat/master cards, gentle table row-hover). Kept as a layer so it restyles
      **every existing screen** while the **theme builder** still drives all
      colours. `theme_style_tag()` now also emits `--field` and a luminance-aware
      `--shadow` (dark themes get proper dark fields/shadows).
- [x] **Landing / sign-in redesign** — `views/login_page.php`: a branded
      split-screen sign-in (value story + live chips on the left, clean sign-in
      card on the right, show/hide password), rendered standalone (no top-bar) and
      fully theme-driven (brand-gradient from `--brand`, accent glow from
      `--accent`). Verified: renders 200, theme (Forest) applies app-wide, no
      warnings.

## Per-office finance (overhead / contingency) — DONE

- [x] **Per-office Overhead % + Contingency %** — new **Overheads** screen
      (`/office-finance`). Each office sets its own Overhead % and Contingency %
      (Branch Application Manager edits their own office; global managers edit any
      office + the global default). Loaded labour = (CTC/12 × (1 + Overhead%)) /
      working days; Contingency % adds a buffer on (labour + expenses + sub-con).
      Both flow into `job_profit` and `boss_profit` → Profitability + Financial
      dashboards. Verified: OH 20% + contingency 5% raised labour 40k→44.4k, added
      ₹2,472 contingency, margin 55k→48.1k. (Replaces the flat 8% constant, which
      remains only as the ultimate fallback.)

## 🔮 Future phases (noted 2026-07, owner)

### Reports — Phase 2 (advanced, downloadable)
- [ ] Beyond the Phase-1 CSV exports (Jobs / Calls / Invoicing / Profitability /
      voucher), build the deeper analytics from the catalogue: **TAT report**
      (on-time vs late, avg, by office/business unit/inspector), **Office P&L** and **business unit
      P&L**, **inspector utilization & productivity**, **overdue aging (30/60/90)**
      on receivables, **inter-office credit statement** (given/received, expected
      vs actual reconciliation), and **true PDF** documents for invoices / credit
      notes / the signed voucher (currently print-to-PDF). Reuse the same
      scope + dashboard-filter pattern; add FY/Month/office/business unit pickers to each.

### CRM system (new module — before the Call in the chain)
- [ ] **CRM / quotation pipeline** — a front-end sales process that feeds the
      existing operations spine: **Lead / Enquiry → Quotation → Follow-up →
      Won/Lost → (on Won) auto-create a Call/contract number**. Scope to define with owner,
      but likely includes: enquiry capture (client, contact, business unit, scope, source),
      **quotation builder** (line items, rates, GST, validity, revisions,
      PDF/print + email to client), **follow-up reminders & status** (open /
      quoted / negotiating / won / lost with reason), a **sales pipeline board**
      + conversion dashboard, and a hand-off that turns a won quotation into a
      **contract number + Call** (carrying client, business unit, PO, agreed value) so nothing
      is re-keyed. Reuses clients/contacts, offices, business units, access control and the
      CSV/PDF export already built. Sits *before* Calls in the Enquiry → Quotation
      → Call → Job → Voucher → Profitability chain. **Note:** the git branch is
      already named `…quotation-management-workflow…`, but no CRM/quotation code
      exists yet — this item is that module.

## 💡 Separate product idea (future — not part of this app)

- [ ] **Freelancer ⇄ Agency connect platform** — a standalone application (its own
      product, separate from this inspection system) where **freelancers and
      agencies can find and connect with each other**: freelancers publish
      profiles/skills/availability/rates, agencies post requirements, and the two
      sides discover, message and engage each other (a two-sided marketplace).
      Could reuse concepts from our CV/hiring pipeline (candidate profiles, trade/
      skill masters, shortlisting) but is a NEW app for a broader audience — to be
      scoped separately later. Owner's idea, parked here so it isn't forgotten.

## Additional features (user will provide details / build later)

- [ ] **Inspector expenses linked strictly to the job done** — an inspector's
      expenses must attach only to the job they performed (fuller rules to be
      provided by the user).
- [x] **CV / hiring pipeline (deputation resourcing)** — DONE. New "Hiring" tab.
      Add a candidate CV (name, trade→skill, client, against-call, proposed site,
      business unit, designation, source [asset/freelancer/sub-con], experience, rate, CV
      link, CV-received date). Move through **CV received → Submitted to client →
      Shortlisted → Interview → Hold / Reject / Accept(=Hired) / Withdrawn**, each
      transition logged with a remark + who/when (full history on the candidate).
      On **Accept** you can tick "add to Inspectors" and the person is created as
      an inspector (carrying trade/skill/business unit/designation and the freelancer/
      sub-con type) ready for deputation-job allocation. Stage filter chips +
      counts on the list. Tables: `candidates`, `candidate_events`.

## Parked (agreed to do later)

- [ ] **Full organisation structure** — model **independent, peer offices**.
      IMPORTANT org model (confirmed by owner): commercially the **HO is Mumbai**,
      but **operationally there is NO head office** — every office is its own unit
      with **its own targets, operations and P&L**. Offices are peers; inter-office
      work is a **credit handoff between equals**, never HQ→branch. Build: users
      linked to their office; **multiple** Operation Managers / Coordinators per
      office; per-office targets; each office's dashboards default to *its own*
      numbers, with cross-office roll-ups only for roles whose scope spans offices
      (e.g. a commercial/Director view from Mumbai). Do **not** treat Ahmedabad (or
      any office) as a managing HQ — the old `is_ahmedabad` "managing office" idea
      is being unwound. This is a role/permission + org redesign for a dedicated
      pass; needs the owner's intended per-office roles before building.
- [x] **Multi-business unit cost distribution in dashboards** — DONE. The Financial
      dashboard's "By business unit" panel now shows Credit vs **distributed loaded cost**
      vs Net per business unit. Each active engineer's monthly loaded cost (CTC/12 + 8%
      overhead) is split equally across the business units they're tagged to, respecting
      business unit scope + the business unit/inspector filter. Salary-gated (`data.salary`).


- [ ] **Reminder cron jobs** — set up the two cPanel Cron entries (07:00 report-due,
      18:00 overdue-closure) pointing at `cron.php`. Deferred by user; steps are in
      `README-MilesWeb-PHP.md`.
- [x] **Office 365 automatic email sending (SMTP)** — DONE. A **Settings → Email
      (Office 365 SMTP)** section takes host / port / username / app-password /
      from. When filled, assignment/closure/forward/reminder emails **auto-send**
      via a built-in SMTP client (STARTTLS + AUTH LOGIN, no library — works on
      MilesWeb). Left blank = current behaviour (logged + Open-in-Outlook). Safe:
      SMTP failures are caught and logged, never crash. Password blank-keeps the
      stored one. *(User just needs to enter their mailbox + app password.)*
- [ ] **License server + per-user billing** — support **both** deployment models
      (client's own server **and** our hosted server) for different industries,
      with remote seat-limit enforcement, subscription expiry, module toggles, and
      a control panel we run from our side. Ties to Razorpay/Stripe for the
      one-time setup fee + monthly per-user recurring. (Roadmap artifact already
      shared.)

## Module A/B sub-items — DONE (this session)

- [x] PO/line-item selection on a call + qty tracking + near-completion alert.
- [x] Project deputation → client sites dropdown (shown only for deputation).
- [x] Executing-branch confirmation status on the call.
- [x] PO line items: manpower/site/trade→subcategory + GST/Tax/Total + rollup;
      activity per line respecting the PO's business unit; multi-business unit on the PO.
- [x] Projects tab lists the partner's calls.
- [x] City light auto-correct; Type-of-inspection 'Other' free text on the call.

## Modules C / D / E — not yet started

- [ ] C: Logo upload + editable theme (kept legible); per-business unit expense headings.
- [ ] D: inspection lifecycle/status flow; designations master (Inspector,
      Sr. Inspector, Sr. Executive…); back-office staff with costing (CTC,
      allowances).
- [ ] E: add your own dropdown/text box to any master form (custom fields on
      masters).

## Dashboards — refinements to follow

- [x] **Configurable expense headings** — DONE (global). The 5 base headings can
      now be **renamed** via the `expense_heading` list, and **any extra headings**
      you add there appear automatically on the job-close form, flow into the job
      total/profit, the job-detail expense table, and the Financial dashboard's
      "Expenses by heading" breakdown. Extras are stored per expense row as JSON
      (`expenses.extra`), so nothing about the fixed 5 columns changed — fully
      backward-compatible. *Remaining refinement:* scope headings **per business unit**
      (make `expense_heading` a child list under business unit) — small follow-up.
- [ ] **Persona landing pages** — today all four dashboard families live on one
      /reports page with each section gated by permission (so each person sees
      only their allowed sections). A future refinement gives each role a
      tailored default landing layout (Director = office comparison, Business Unit Head =
      business unit-across-offices, etc.).

## Nice-to-have / minor

- [ ] Generic master "checkbox" fields default to ticked on new records (fine for
      "Active" on sub-cons, not ideal for "is Ahmedabad" on a new office). Low impact.

## Done recently (for reference)

- [x] Configurable master lists + dependent (hierarchical) lists + custom fields.
- [x] New Call: quick-add client/vendor/office/product/activity, executing-branch
      forwarding with mandatory credit + coordinator/manager email, lead times.
- [x] Readable error page instead of blank 500.
- [x] Full operations system (Calls, Jobs, Closure, Expenses, SubCon, Attendance,
      Holidays, Comp-off, Credit, Dashboards) with 4 roles + salary security.

## Consistency pass — headings, dropdowns, terminology (done)

Audited first (no code), reported, then built to the agreed decisions.

- **Terminology engine** `lib/terms.php` + `/terminology`: 27 business nouns,
  each renameable once and followed everywhere. Shipped vocabulary: Client,
  Quote, Inspection Call, Deputation, Report, Inspection Engineer + User, IBO,
  business unit, contract Number, Man-day; Vendor / Manufacturer / Supplier / Sub-vendor all
  kept as distinct parties.
- **One heading standard** across all 55 screens; sidebar label = the heading it
  opens; emoji and trailing spaces removed from card titles.
- **Screens merged**: Approval rules (was 2), Document templates (was 2),
  Masters (was 2), Users vs Roles & permissions.
- **Every dropdown editable**: ~60 lists / ~500 values under Masters, grouped by
  module with a search box. Constants remain as fallbacks.
- **One list per concept**: work type (Sales + Ops), charge unit (quote + rate +
  PO), deliverables (from the report-types register), ISO/IEC 17020 result
  wording, and only two rejection words (Rejected / Sent back).
- **Settings** gained: hours cap, default weekly working days, employee-code
  prefix, currency symbol, date format, required source documents, high-risk
  audit actions.
- **De-branded**: no third-party agency name anywhere in code, seeds, themes,
  e-mails or placeholders.

Data-level upgrades (renamed lists, rewritten codes, dropped lists) cannot be
detected by a missing table or column, so the boot probe asserts them and each
assertion self-cancels once applied. All were tested against a simulated older
database as well as a fresh one.

## Quote / CRM pack (done)

- Client picked ⇒ free-text name disabled; contact person / e-mail / mobile
  auto-filled from the client's primary contact, still editable.
- Sites are real addresses, many per quote; every line item names its site.
  The client's addresses on file are offered as one-click additions.
- Executing office: a primary office plus any others, and a per-line office.
- Types of inspection = the call's master, narrowed to that client's types.
- Payment terms, quote origin and site type are masters (17 payment terms).
- Product category unifies spelling automatically (exact → plural → edit
  distance), office-scoped, cumulative for cross-office access.
- Editable grids readable: minimum width, room per cell, full-height controls.
- Approvals show who they are waiting on by name; reject needs a comment;
  rejected shows as Rejected by whom, when and why.
- Accepted / lost quotes lock; re-edit is a request the Super Admin grants for
  N hours, after which it re-locks itself.
- Multiple attachments per quote, typed (our quotation, attachment, client doc,
  PO, inspection doc); PO number and date captured; shared files follow the
  work to the job so the engineer sees them.
- External quotes (client / tender portal) get their own registration screen.
- Terms & conditions default in Settings, editable per quote.
- Stored signature of the named signatory stamped on the quote PDF.
- Change log shows field-level differences; accepted quotes offer a final copy.
- Register export with submission / approval / sent / acceptance dates, contact
  details, contract and PO numbers, sites and every follow-up.
- Follow-ups editable (date, status, done-on, note) and can be added by hand.

## Calls pack (done)

New call
- Client → quotation → contract number; the quote's line items are listed and
  the call can be tied to one. business unit, activity, type of inspection, product,
  billable value and basis all inherit from the quote (blanks only, on edit).
- Up to 5 visit dates, or a weekday pattern to an end date that expands into
  real dates — all editable afterwards.
- Cross-office credit explained in the offices' own names, on the form and in
  the refusal. Every office both contracts and executes.
- Clickable shared folder / drive link, carried to the deputation.
- Region shown only to business unit heads and the Business Director.

Call register
- Executing office, activity, credit to give, coordinator, engineer, received /
  forwarded / allocated / required / scheduled dates, three lead times, delay
  pill, late-row tint, days-waiting when unallocated. Export matches.

Allocate
- Everything inherits from the call, shown in a "from the call" strip.
- Inspection dates up to 20 (replaces random date 1/2/3; old values folded in).
- Own employee vs not-ours → freelancer / sub-contractor, engineer list filtered.
- Credit direction defaults to Given for cross-office.
- Filters: engineer, office, month, date range, nobody-allocated.

## Inspector availability (done)

- Six cards: total, available, on job, on leave, in office, training/other.
- "Free to allocate" = free today AND tomorrow only.
- Date check: pick a date + days needed -> who is free, for how long, and what
  they are on next; whole-period cover highlighted; 45-day horizon.
- Filters: name, office, business unit, status; plus a month grid (free / on job / leave /
  other per day, with a free-day count).
- Look-ahead reads open deputations (scheduled day, start-end period, and the
  deputation's visit dates) plus manual day statuses - the same sources as the
  day view, so the two can never disagree.
