# Pending / parked items — SGS Ahmedabad Inspection Management System

Living list of things explicitly deferred, so nothing is forgotten. Newest on top.

## 🚀 NEXT BIG BUILD — IDEMS: Intelligent Inspection Documentation, Reporting & Endorsement Engine (TPIA Industry Pack) — owner spec 2026-07, roadmap pending approval

A world-class TPIA documentation ecosystem. 24-part spec. Two core workflows:
(A) TPIA prepares & issues its OWN reports; (B) TPIA reviews/verifies/endorses/certifies
manufacturer/vendor/contractor documents. One platform for both. Configurable, mobile-friendly,
offline-capable, AI-ready, no-code report builder.

REUSE (already built — do NOT duplicate): crm_templates docx engine + doc/format-number stamping,
lib/pdf.php SimplePDF + signature image + per-company letterhead, custom-fields engine (dynamic
fields on any entity, cascading lookups), lookup masters, approval-chain (quote_approval_rules +
REPORTS_TO reporting-manager chain), report-approval + escalation, lib/ai.php provider seam,
email_log, deliverables master (IR/IRN/NCR/CoC…), FY/office/SBU scope.

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
  type/office/client/SBU; approver = inspector-map / reporting-manager / specific user / role; per-level
  SLA), report_approvals steps built on submit (submit blocked if no approver), approve/reject/
  send-back/delegate with mandatory remarks, finalize gated on full approval, SLA auto-escalation in
  cron, approval-chain panel + act buttons on the report detail. Routes: /approver-map,
  /idems-approval-rules(-edit), /document-approve.
- P4 Auto signatures + immutable system timestamps (inspector from profile, approver on approve),
  Branch-App-Mgr-only timestamp edit w/ tamper-proof audit (old/new/user/reason/time).
- P5 Client-specific formats: upload DOCX/PDF template, map fields, pixel-match output.
- P6 Manufacturer document verification & endorsement (upload→review→verify→comment→approve/reject→
  digitally endorse→archive) without altering the original; full endorsement audit trail.
- P7 Technical Writing Assistant (no AI): grammar/spelling, engineering phrase library, plain
  observation → engineering language, standard conclusions/acceptance/rejection statements.
- P8 Smart Remarks & auto Release Note from finalized report.
- P9 AI-assisted (uses ai.php): read PO/QAP/drawings/specs/MTCs, missing-doc + revision-mismatch +
  conflict detection, draft remarks/narratives — inspector always final authority.
- P10 Smart photo/evidence mgmt (compress, timestamp, GPS, link to checkpoint, annotate, dedupe).
- P11 Super-Admin audit & compliance dashboard (every critical action, soft-delete only, searchable).
- P12 Offline-first / mobile field UX (constraint: plain PHP on shared hosting → responsive +
  localStorage autosave/draft + sync-on-reconnect; true native offline is a later PWA effort).
- P13 Future self-learning suggestions from approved reports (suggestions only, never auto-alter).

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
   amount/SBU rules; (b) inspection/report approval — report/closure routes to the inspector's
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
4. Allocate to different SBU(s) when generating the quote (per line if needed).
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
    history of what changed (reuse the BOSS supersede/hierarchy pattern).
24. Fetch the required deliverable(s) from the quote into Operations.
25. Job types (full list): Inspection, Project deputation, Supply-chain deputation,
    Site supervision, Commissioning & Installation, Site QA/QC, Technical audit,
    Type test, Vendor Assessment, Vendor Audit, Document Review, Tender Review, …

### What ALREADY EXISTS to reuse (integration surface — researched)
- `business_partners` (clients/vendors, GSTIN, `contract_number`, inspection_types),
  `partner_contacts` (name/email/mobile), `partner_site_addresses` (locations),
  `partner_contracts` (contract_number/title/sbu/value/dates),
  `partner_purchase_orders` + `po_line_items` (trade/skill/site/manpower/activity/
  gst/base/tax/total — this is largely the "order line items" of §17), BOSS numbers.
- Calls→Jobs pipeline with `inspection_type`, `job_type` (INSPECTION/DEPUTATION),
  `deliverables`, `site_address_id`, `po_id`, `po_line_item_id`, activity, SBU,
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
  (levels by value threshold and/or SBU, with named approvers). Confirm the rule.
- **Lost/Rejected quote status** — owner listed Open/Pending/Closed(accepted) only;
  need a LOST/REGRETTED state + reason for win/loss analytics.
- **One quote → multiple SBUs** (§4) implies SBU per quote line (split), not one SBU
  per quote — confirm.
- **Advance %/payment terms & "report-vs-payment" gate** need explicit fields on the
  quote that flow to the job and show the inspector a HOLD (§21/§22).
- **Revision history** — capture field-level diff, not just a new rev number (§23).
- **Duplicate-inquiry / duplicate-quote** guard; attachments (techno-commercial PDF).

### Proposed phased roadmap (see chat for the approved-pending version)
- P0 Foundations: sales roles + access modules; CRM masters (job-type / inspection
  sub-category as multi-select configurable masters); quote/inquiry numbering.
- P1 Inquiry + Quotation core: inquiry capture, quote header + line items (SBU per
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
  `quote_lines` (SBU per line, order type, units), `quote_revisions`,
  `quote_approvals`, `quote_approval_rules` (amount band and/or SBU — §owner),
  `quote_followups`, `crm_templates` (docx + email, with a JSON field map for §6).
- Boot probe extended so live MySQL auto-creates the CRM tables on next load.
- **Next: P1** — Inquiry + Quotation screens (list/form/detail), quote numbering,
  revisions, dropdown-driven entry.

### ✅ P1 shipped (2026-07) — Inquiry + Quotation core
- **Inquiry register** (§1): list + new/edit, auto `INQ-#####` number, client/contact/
  SBU/source/status, "Quote" button that carries an inquiry into a new quotation.
- **Quotation core** (§2,3,4,8,14,23): list with **Open / Pending / Closed(won) / Lost**
  views + KPI counts; new/edit form with header (client, contacts, SBU, site +
  location type, validity, payment terms, **advance % + advance-required**,
  **report-vs-payment** flag, GST) and **dynamic line items** (SBU per line, service
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
  tokens (quote no, client, contacts, SBU, commercials, totals, **amount in words**),
  **repeats a table row per line item** (`{{l_desc}}` etc.), and **repairs tokens Word
  splits across runs** (verified: `{{cli|ent_name}}` rejoined correctly). Tokens are
  documented on the template form.
- **"⬇ Generate Word quote"** button on the quote detail → downloads the filled .docx.
- Verified end-to-end over HTTP (upload → create quote → generate): doc/format numbers
  stamped, line rows repeated, totals + words correct, no unreplaced tokens.
### ✅ P3 shipped (2026-07) — approval chain + send + follow-up emails
- **Configurable approval matrix** (Quotations → Approval rules): rules by **amount
  band** and/or **SBU**, with a **level** (chain order) and an **approver role or a
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
  person/email/mobile, SBU, location, value, advance/report-vs-payment flags, the
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
- Charts: quotes by status (donut), quoted value by SBU, **top customers by quoted &
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
chain (amount/SBU) → send-to-customer (Word attached) → follow-up e-mails → acceptance
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
by SBU/customer/project, forecast vs actual, top accounts) — AFTER the CRM lands so
it can draw on real pipeline data.

## ✅ Just shipped (2026-07 — owner's screenshot batch)
- **Distinct employee-code series for contractors.** A new inspector saved with a
  blank Employee code now auto-gets a code by engagement kind: **SC-###** for
  sub-contractors, **FL-###** for freelancers, **EMP##** for SGS staff — so
  payroll/accounts can tell them apart at a glance. Manually typed codes are kept
  as-is. Demo sub-con Mohan reseeds as **SC-001**. (`next_emp_code()` in ops.php.)
- **"Food bills (actual)" expense head** added alongside "Food allowance (meals)"
  (now an ALLOWANCE; the new head is an actual BILL needing a receipt). Expense
  heads are now ensured **by code** on boot, so existing live databases gain the
  new head automatically without wiping custom heads.
- **BOSS numbers list** (Profitability screen) rebuilt as an accessible table with
  **Sr No · BOSS number · Client · Status · Created on · Expires on · Renewed into
  (renewal hierarchy) · Jobs · Invoicing done · Expenses booked** + salary-gated
  **Salary costing · Profit INR · Profit %**, KPI cards, expiry pills, and CSV.
- **Vouchers screen role-scoped cards** — Total expense claimed · This month ·
  Awaiting approval · Paid, scoped to the role (inspector sees only their own).
- **Insights dashboard (/reports):** added a **client-name filter** to the filter
  bar, a **Top 10 customers by revenue** chart and a **Revenue by project (BOSS)**
  chart in the Financial section. The **Certificates-expiring** panel is now hidden
  for the **Business Director** role (strategic view, not an ops-compliance task).
- **Demo reload guidance:** the "already loaded" message now tells the user to
  Remove + Load again to pick up newer sample records. Root cause of "agencies /
  requisitions look empty" is the one-shot `demo_seeded` flag — the seed itself is
  correct (verified: 2 agencies, 2 requisitions render for admin *and* director).
- Remaining voucher/BOSS polish parked below (§ Reports Phase 2, deputation).

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
- [x] **DONE** — `requisitions` (New/Replacement, office/SBU/designation/site,
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
- [ ] **`requisitions` table**: req_code, office, SBU, designation/position,
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
      with entitlements, 3 clients + 2 vendors, 3 BOSS numbers, 6 calls, 6 jobs
      across the full lifecycle (paid / awaiting / overdue / unbilled / in-progress
      / sub-con), closure expenses, and 2 vouchers (DRAFT + APPROVED). Every
      screen shows live figures immediately. *Follow-ups when the credit rules
      below land: extend the seed with same-vs-different-office credit examples.*
- [ ] *(original ask, for reference)* A **ready-made sample dataset** that can be loaded into a fresh install so
      the whole system can be explored end-to-end with realistic values —
      **from user creation → multiple offices → clients/vendors → BOSS/contract
      numbers → calls → job allocation & scheduling → inspection → voucher
      (km + bills) → closure → invoicing → payment → inter-office credit.**
      Purpose: demos, training, and testing every screen with data already in
      place. Should include: several **offices** (peer offices + Mumbai as the
      commercial HO), **users of every role** (Master Admin, Business Director,
      SBU Head, Branch/Branch-App Manager, Operation/Asst. Manager, Coordinator,
      Accountant, Inspector), **inspectors** with salary + entitlements, a few
      **clients/vendors/sites**, **BOSS numbers**, a spread of **calls & jobs**
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
      supplying agency + roll (SGS vs agency) + fee, and the new inspector stores
      agency_id / roll_type / placement_fee (one-time, tracked separately) /
      agency_cost (monthly, into loaded cost). One-time recruitment fee is
      **recorded, not amortised** (owner: tenure is unpredictable).
- [ ] **Remaining follow-ups**: (a) show/edit agency, roll, placement fee on the
      **inspector edit form** + inspector costing breakdown; (b) turn the renewal
      reminder into an **email** via `cron.php` (currently a dashboard card only);
      (c) **manpower pass-through invoicing** — we invoice the client our rate
      while the agency bills SGS their monthly charge (margin = our rate − agency
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
      • **Recruitment** → person is on **SGS roll** (salary CTC) **plus a
        one-time fixed placement/consulting fee** paid to the agency; that fee is
        **included in the inspector's costing** (decide: one-time in the hire
        month vs amortised over expected tenure — confirm with owner).
      • **Manpower** → agency **bills us monthly**; that monthly charge is the
        inspector's `agency_cost`, and **we invoice the client** for the manpower
        (pass-through — ties to §1d monthly invoicing).
- [ ] **On Accept, choose the roll + agency + fee**: SGS roll (salary) vs agency
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
      SBU, client and status bucket (pending / awaiting / overdue / credit). The
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
      *Remaining from the catalogue below (future): TAT report, office/SBU P&L,
      utilization/productivity, overdue-aging, inter-office credit statement,
      and PDF statements for invoices/credit notes.*

### 3b. Downloadable reports — remaining catalogue (research, for later)
Goal: let every function pull the data it needs as a file (Excel/CSV for
analysis, PDF for official statements). Proposed catalogue to build:

**Operations**
- [ ] Call register (with lead-times, status, pending-scheduling flag)
- [ ] Job register / allocation report (inspector, dates, BOSS, status)
- [ ] **TAT report** — on-time vs late, average TAT, by office / SBU / inspector
- [ ] Overdue-closure report (jobs past scheduled/required date)
- [ ] Scheduling / dispatch board export (what's due, who's free)
- [ ] Inspection volume by client / vendor / site / inspection-type

**Finance**
- [ ] **Profitability by BOSS / contract** (revenue − labour − exp − subcon − OH − contingency)
- [ ] **Office P&L** (per peer office; own targets vs achieved)
- [ ] **SBU P&L** (credit vs distributed loaded cost vs net)
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
print-page approach; every report **respects the user's office/SBU scope** and
the current dashboard **filters** (FY, month, office, SBU, inspector).


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
      inspection day (date, **vendor display name** as site, **File No = BOSS**,
      SBU, 8h, tagged `auto`) — idempotent, never duplicates. **Multiple rows per
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
- [x] **P7 · Profitability by BOSS/Contract** — DONE. New **Profitability** tab
      (gated by new `data.profitability` perm — granted to Master Admin, Business
      Director, SBU Head, Branch Manager, **Operation Manager** [manager under the
      branch manager] and Finance; **not** Coordinator/Inspector). List of BOSS
      numbers with Revenue / Expenses / Sub-con / Labour / **Margin ₹ + %**;
      detail page with stat row + **expense drill-down** (each line shows which
      inspector visited which vendor, hours, travel + bills + line total, with a
      **+ toggle** for the per-head breakdown) + invoice/job lines. Expenses roll
      voucher `row_total` (by boss_id) + job-closure expenses. Labour counted only
      when salary is visible (else "Contribution"). Verified: revenue 50k, expenses
      668, margin/%, drill-down. Super Admin can grant/revoke the perm per user.
- [x] **P8 · Contract/BOSS carry-forward** — DONE. On a BOSS profitability page,
      **Renew / change contract number (ARC/Open)** creates a new BOSS number
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
      SBU Head, Branch/Branch-App Manager, Manager/Asst, Coordinator, Accountant,
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
      (on-time vs late, avg, by office/SBU/inspector), **Office P&L** and **SBU
      P&L**, **inspector utilization & productivity**, **overdue aging (30/60/90)**
      on receivables, **inter-office credit statement** (given/received, expected
      vs actual reconciliation), and **true PDF** documents for invoices / credit
      notes / the signed voucher (currently print-to-PDF). Reuse the same
      scope + dashboard-filter pattern; add FY/Month/office/SBU pickers to each.

### CRM system (new module — before the Call in the chain)
- [ ] **CRM / quotation pipeline** — a front-end sales process that feeds the
      existing operations spine: **Lead / Enquiry → Quotation → Follow-up →
      Won/Lost → (on Won) auto-create a Call/BOSS**. Scope to define with owner,
      but likely includes: enquiry capture (client, contact, SBU, scope, source),
      **quotation builder** (line items, rates, GST, validity, revisions,
      PDF/print + email to client), **follow-up reminders & status** (open /
      quoted / negotiating / won / lost with reason), a **sales pipeline board**
      + conversion dashboard, and a hand-off that turns a won quotation into a
      **BOSS number + Call** (carrying client, SBU, PO, agreed value) so nothing
      is re-keyed. Reuses clients/contacts, offices, SBUs, access control and the
      CSV/PDF export already built. Sits *before* Calls in the Enquiry → Quotation
      → Call → Job → Voucher → Profitability chain. **Note:** the git branch is
      already named `…quotation-management-workflow…`, but no CRM/quotation code
      exists yet — this item is that module.

## 💡 Separate product idea (future — not part of this app)

- [ ] **Freelancer ⇄ Agency connect platform** — a standalone application (its own
      product, separate from the SGS inspection system) where **freelancers and
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
      SBU, designation, source [asset/freelancer/sub-con], experience, rate, CV
      link, CV-received date). Move through **CV received → Submitted to client →
      Shortlisted → Interview → Hold / Reject / Accept(=Hired) / Withdrawn**, each
      transition logged with a remark + who/when (full history on the candidate).
      On **Accept** you can tick "add to Inspectors" and the person is created as
      an inspector (carrying trade/skill/SBU/designation and the freelancer/
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
- [x] **Multi-SBU cost distribution in dashboards** — DONE. The Financial
      dashboard's "By SBU" panel now shows Credit vs **distributed loaded cost**
      vs Net per SBU. Each active engineer's monthly loaded cost (CTC/12 + 8%
      overhead) is split equally across the SBUs they're tagged to, respecting
      SBU scope + the SBU/inspector filter. Salary-gated (`data.salary`).


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
      activity per line respecting the PO's SBU; multi-SBU on the PO.
- [x] Projects tab lists the partner's calls.
- [x] City light auto-correct; Type-of-inspection 'Other' free text on the call.

## Modules C / D / E — not yet started

- [ ] C: Logo upload + editable theme (kept legible); per-SBU expense headings.
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
      backward-compatible. *Remaining refinement:* scope headings **per SBU**
      (make `expense_heading` a child list under SBU) — small follow-up.
- [ ] **Persona landing pages** — today all four dashboard families live on one
      /reports page with each section gated by permission (so each person sees
      only their allowed sections). A future refinement gives each role a
      tailored default landing layout (Director = office comparison, SBU Head =
      SBU-across-offices, etc.).

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
