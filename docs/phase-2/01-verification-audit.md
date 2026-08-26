# Phase 2 · W1 — Verification Audit (all 84 points)

**Status:** IN PROGRESS (read-only; no code changes in this workstream — findings only).
**Method:** every claim is grounded in the actual code (file:line) or an executed test, not memory.
Areas needing fresh verification were investigated by dedicated read-only agents; the rest is
cross-checked against Phase 1 evidence. Where the code exists but is not operationally complete
(§82), it is marked **PARTIAL**, never "done".

## Verdict legend

- **IMPLEMENTED** — exists and verified working against the spec point.
- **PARTIAL** — core exists; specific sub-requirements of the point are missing or thin.
- **MISSING** — not present; would be net-new (candidate for a *justified*, non-duplicating build).
- **N/A-OK** — spec point is a rule/process we already comply with (e.g. non-destructive).

## Defect classification (§81) applied to every gap

`Severity` Critical / High / Medium / Low · `Priority` P0 / P1 / P2 / P3 ·
`Effort` XS / S / M / L / XL · `Business impact` Low / Medium / High / Very High.

## How to read this

Each Phase 2 section (§n) gets: **Verdict**, **Evidence** (file:line), **Gap**, **Classification**.
No fixes are made here — the fix backlog is compiled at the end for your approval (per the
"audit-first, then confirm fixes" decision).

---

## Section-by-section audit

_(Being filled as the four investigation agents report. Sections already grounded in Phase 1
evidence are drafted first; security, report-workflow, financial-reproducibility, and
consolidation/platform sections are pending their agents and will be inserted with evidence.)_

<!-- AUDIT BODY APPENDED BELOW AS EVIDENCE LANDS -->

## W4 — Financial architecture (§27–33) — investigated, evidence-grounded

### §28 One calculation service — **PARTIAL**
- **Evidence:** `job_profit()` (`lib/ops.php:1629-1695`) is a genuine single per-job engine (revenue, labour, overhead, expenses, voucher, subcon, other, recovered, contingency, cost, profit, margin, cross-office). Faithful consumers: `callprofit.php:77-80`, `costing.php:212` (overhead recovery), the ops dashboard `ops.php:7044-7045` (uses full `$p['profit']`), job detail `ops.php:6049`.
- **Gap:** MIS (`mis.php:161/183/288`) and SBU-PL (`views/ops/sbu_pl.php:122`, `costing.php:761-762/902-903`) **re-derive** `profit = credit − labour − expenses − subcon`, dropping overhead+voucher+other+contingency−recovered → overstate profit. Module 32 `profit_reconciliation()` (`mis.php:105-136`) **measures** the drift but changes no figure. Not yet a single service.
- **Class:** High · P1 · Effort M · Business impact High.

### §28–29 boss_profit vs Σ job_profit — **PARTIAL / DIVERGE**
- **Evidence:** `boss_profit()` (`lib/ops.php:6318-6344`) is a second contract-level formula. Divergences vs Σ`job_profit`: loaded-vs-split labour (`:6333` loaded `inspector_daily_cost` vs `:1649-50` split), omits `other_cost` (`:1660`), omits `recovered` (`:1670-71`), voucher summed by `boss_id` (`:6339`) vs `job_id` (`:1702`), different contingency base (`:6337` vs `:1674`). Revenue agrees (both `job_money()['invoice']`).
- **Gap:** no reconciliation between them; `pc_estimate_vs_actual` (`projcosting.php:172-189`) inherits all five divergences into estimate-vs-actual variance.
- **Class:** High · P1 · Effort M · Business impact High.

### §29 Estimate / committed / actual / invoiced / collected — **PARTIAL**
- **Evidence:** estimate = `project_costings` (`projcosting.php:201-262`, DRAFT/SUBMITTED/APPROVED); committed/agreed = `job_money()['agreed']` (`ops.php:1513-1536`); invoiced = books ledger `invoices/invoice_lines` (`books.php:388-389`); collected = `receipt_allocations`+`books_settled()` (`books.php:367-379`); outstanding = `receivables.php`.
- **Gap:** (a) "**recognised**" is not a distinct concept — `job_revenue_for()` (`ops.php:1548-58`) derives it live, recognised≡invoiced/agreed. (b) **Two parallel invoice truths** — legacy `jobs.invoice_amount`+`payment_received` flag (`ops.php:5781-85`) vs the books ledger; `boss_profit`/MIS/dashboard read the legacy columns, `receivables` reads the ledger; they can disagree.
- **Class:** Medium · P2 · Effort M · Business impact Medium.

### §30 Historical financial reproducibility — **MISSING — CRITICAL**
- **Evidence:** `job_profit()` recomputes an old job from **today's** live rates:
  - Working days from the **current** month: `ops.php:1646` `working_days_in_month(date('Y'), date('n'))` — ignores the job's inspection date; `$dailyBase=($salary/12)/$wd` (`:1647`) shifts monthly.
  - `working_days_in_month()` (`ops.php:1402-11`) reads the live `holidays` table with no as-of filter → editing a holiday rewrites past months.
  - **Current** salary: `ops.php:1643-44` `SELECT salary_ctc+agency_cost FROM inspectors WHERE id=?` — a raise today rewrites labour on every past job; no `salary_history`/effective-dated table exists.
  - **Current** office %: `office_overhead_pct()`/`office_contingency_pct()` (`ops.php:1417-23`) read the single mutable `offices.*_pct` column.
  - No effective-dating / versioned rate / period snapshot / accounting-period lock in the profit path. (`cost_runs` FROZEN and `office_expenses` yr/mon only lock the office **allocation pool**, which `job_profit` never reads.)
- **Gap:** historical per-job AND contract profit — and every screen/report built on it — **silently drifts** with any change to salary, office %, holidays, or the calendar month. No period lock anywhere in the profit path.
- **Class:** **Critical · P0 · Effort L · Business impact Very High.** (The single highest-stakes finding of the whole audit.)

### §31 Overhead engine — **PARTIAL**
- **Evidence:** period-locked allocation *pool* exists (`cost_runs` FROZEN, `office_expenses` by office/yr/mon, `overhead_recovery()` `costing.php:199-223`). But the overhead **loaded onto jobs** is a live single `offices.overhead_pct` (`ops.php:1417`) / `setting_get('overhead_pct')` fallback (`:1415`).
- **Gap:** no period/rate/basis/source/approval/**version** row for the per-job overhead rate; no historical basis retained. Two disconnected overhead notions (snapshotted pool vs live % loading).
- **Class:** High · P1 · Effort M · Business impact High (compounds §30).

### §32 Inter-office accounting — **PARTIAL**
- **Evidence:** per-job cross-office split exists (`job_money()` `ops.php:1510-44` → `cross`, `rev_holder`, `rev_executor`; `job_revenue_for()` `:1548-58`). State = `expected_credit` + `credit_direction` + boolean `credit_received` (`ops.php:168/447`). Reconciliation = manual monthly `credit_recon` aggregate (`ops.php:183-186`, admin CRUD `:2249-62`; dashboard flat totals `:7067-68`).
- **Gap:** no expected→approved→disputed→settled lifecycle; no automated per-job reconciliation of expected vs settled credit.
- **Class:** Medium · P2 · Effort M · Business impact Medium.

### §33 Invoice readiness — **PARTIAL**
- **Evidence:** line-add gate = job **closed** + not already billed (`books.php:560-64`, `books_job_invoiced` `:602-09`); issue gate = tax/completeness (`books_issue_missing` `:624-34`: DRAFT, has lines, total>0, date, place-of-supply/GSTIN, billing office).
- **Gap:** no check for milestone / **applicable reports issued & accepted** / client acceptance / required documents / **PO presence** / previous-billing (over-billing) vs contract value; **no READY/NOT-READY panel with itemised blockers**; not configurable per contract/service.
- **Class:** High · P1 · Effort M · Business impact High.

## W3 — Report / inspection workflow (§4–11, §8) — investigated, evidence-grounded

### §4 Report role model (Prepared/Vetted/Approved/Issued) — **PARTIAL**
- **Evidence:** `idems_provenance()` (`idems.php:5818-5858`) emits four clean role rows (Prepared/Vetted/Approved/Issued) with person+state; shown at `doc_detail.php:412-427`. Storage: Vetted (`vet_by/vet_at`), Approved (`approved_by/approved_at`), Issued (`finalized_by/finalized_at`).
- **Gap:** the **PDF prints only two** signatory blocks — "Inspected by" + "Approved by" (`idems.php:7118-19`, `idems_report_signatures()` `:7462-87`); **no Vetted-by / Issued-by** on the printed report. Prepared has **no timestamp** (`at=''`, `:5825`).
- **Class:** High · P1 · Effort S–M · Business impact High (accreditation defensibility of the issued PDF).

### §4 Workflow states — **PARTIAL**
- **Evidence:** `report_docs.status` = DRAFT/SUBMITTED/VETTING/UNDER_REVIEW/APPROVED/ISSUED/REJECTED/ARCHIVED (`idems.php:63`); vetting outcome on `vet_status` (`:65`). Vetting optional & default-off (`idems_vetting_gate_on` `:6048-50`), release notes skip it (`:6051-56`) — **IMPLEMENTED**.
- **Gap:** `SUBMITTED` is a **dead value** (submit jumps to VETTING/UNDER_REVIEW); no distinct `VETTED`/`RETURNED_TO_INSPECTOR`/`RESUBMITTED` doc-states — a return collapses to `DRAFT` (`:6019/:6144`); the distinction is only cosmetic via `idems_status_label`/`idems_latest_return`.
- **Class:** Medium · P2 · Effort M · Business impact Medium.

### §5 Submit UX — **IMPLEMENTED** (minor wording inconsistency)
- **Evidence:** primary button "✓ Submit for review" (`doc_detail.php:39`); post-submit next-step message present ("Report submitted — sent to the vetting authority…" `:4494` / "…routed to the approval chain" `:4511`).
- **Gap:** two submit buttons with different wording ("Submit for review" vs "SUBMIT REPORT" `doc_detail.php:377`); default (gate-off) message says "approval chain".
- **Class:** Low · P3 · Effort XS · Business impact Low.

### §6 Applicable report engine — **PARTIAL**
- **Evidence:** `idems_job_applicability()` (`idems.php:9284-9322`) shows Required (job `deliverables`) vs Not-Applicable, with an "add anyway" escape (`job_detail.php:648-660`). Required/NA per type **IMPLEMENTED**.
- **Gap:** provenance resolves only **3** sources (service/client/manual, `:9297-9301`) — §6's contract/scope/standard/procedure/coordinator/override are absent. **Override is NOT audited** — "add anyway" links to `/document-new` with no reason/person/timestamp; the promised "flagged 'not allocated'" is **never stored** (broken UI promise).
- **Class:** High · P1 · Effort M · Business impact High (accreditation provenance + override trail).

### §9 Return-to-inspector — **PARTIAL**
- **Evidence:** prominent "↩ Returned for correction — {kind} by {person} on {date}. Reason: …" banner (`doc_detail.php:401-410`, `idems_latest_return` `:5862-72`); reason mandatory on both return paths (`:6110`, `:6010/6017`); inspector emailed (`:5886-5901`); prior versions preserved.
- **Gap:** payload is a single free-text `note`/`remarks` — §9's **section / field / evidence-ref / required-correction / deadline / separate comments are not modelled**.
- **Class:** Medium · P1 · Effort M · Business impact High (field correction efficiency).

### §10 Issuance readiness gate — **PARTIAL**
- **Evidence:** `idems_issue_readiness()` (`idems.php:5907-5960`) returns itemised `{label,state,detail}`; UI lists **every** item with READY/NOT-READY (`doc_detail.php:456-472`). Hard-blocks: approval (`:5914`), calibration (`packs.php:238`, non-overridable), QA-critical (`:5924`), segregation (`:5920`).
- **Gap:** **omits** vetting-complete, report-completeness (only at submit), competence (only `work.assign`), impartiality (only `work.assign`, `packs.php:267`), blocking-NCR, client-acceptance. Authorisation/evidence warn-only. Not configurable.
- **Class:** High · P0–P1 · Effort M · Business impact Very High (issuing a report that skipped vetting/competence/impartiality is an accreditation risk).

### §11 Issued immutability — **IMPLEMENTED** (one caveat)
- **Evidence:** SHA-256 `content_seal` frozen at issue (`idems.php:9049-67`, `:4582`), verified by `idems_content_check` (`:9072`); signatures + presentation frozen (`:4581/4583`); edit-locked when finalized (`:3517`); controlled revision creates a NEW row with `revises_id`/`rev+1` (`idems_revise_doc` `:9006-42`); `/verify` code on the PDF (`:7122-28`).
- **Gap/caveat:** the seal write is **fail-open** — an error in `idems_seal_content()` is swallowed (`:9066`) so a report can be issued **unsealed**, and `idems_content_check()` reports "unsealed / ok" rather than failing (`:9074`). Master can finalize bypassing the approval chain (`:4527`).
- **Class:** Medium · P1 · Effort S · Business impact High (seal should fail-closed / flag unsealed issued reports).

### §8 Report builder — persona previews & publish validation — **PARTIAL / mostly MISSING**
- **Evidence:** builder has a single sample-data preview + PDF preview (`builder.php:13-14,47-52`; `idems.php:7582`). `idems_template_validate` (.docx, **blocks** on ERROR `:7768-7807`); `idems_format_validate` (schema, **advisory only**, Module 48, `:7814-48`).
- **Gap:** **persona previews (Inspector/Vetter/Approver/Client/Issuer) do not exist.** Publish validation covers ~3/14 §8 checks; absent: unreachable sections, contradictory rules, missing approval path, invalid signature/evidence config, visibility conflict, page/table overflow. Schema validator never blocks.
- **Class:** Medium · P2 · Effort L · Business impact Medium.

## W2 — Security & tenant isolation (§51–54) — investigated, evidence-grounded

### §51 Authorization / IDOR — **PARTIAL (systemic)**
- **Evidence:** the dispatcher checks only a module permission (`ops_module_gate()` `ops.php:2465-95`); the scope helper `scope_clause()` (`access.php:604-21`) is applied on **list** queries (jobs list `ops.php:5399`; evidence files `idems_file_authorized()` `idems.php:5532-40` — the correct pattern) but **dropped on single-record/PDF/file reads**: job detail (`ops.php:6000-08`, no scope vs the scoped list), report detail (`idems.php:4372-78`), **report PDF** (`idems.php:7501-06`), invoice detail/print (`books.php:346-53` via `booksui.php:165-213`), **check-in photo** (`trust.php:853-58`), endorsement file (`idems.php:8392-97`). Partners/contracts are company-wide master data (no office column) — arguably by design.
- **Gap:** any authenticated user holding the module permission can open/download ANY id cross-office for jobs, reports, report PDFs, invoices, endorsement files, check-in photos. The correct scoped pattern already exists (`idems_file_authorized`) but is applied unevenly. Corroborated by the team's own checklist (lines 454/469/547).
- **Class:** **High · P1 · Effort M · Business impact High** (confidentiality / cross-office data exposure; §52-adjacent for internal users).

### §51 CSRF — **IMPLEMENTED**
- **Evidence:** session-bound token, `hash_equals`, empty-rejecting (`helpers.php:185-236`); auto-stamped into every POST form (`:238-52`); **global fail-closed gate** before handlers (`index.php:836-45`, logs `CSRF_REJECTED`); portals + login carry their own checks; public payment uses Razorpay signature (correct). No unprotected authenticated POST found.
- **Class:** — (no gap).

### §52 Portal tenant isolation — **IMPLEMENTED**
- **Evidence:** both portals put the tenant id **in the WHERE clause** on every fetch incl. single records + PDF: client `portal_call/portal_report` (`portal.php:248-313`, `WHERE …client_id=? AND site_sql`), invoices (`:335-43`), complaints; vendor `cvp_vendor_report` (`cvp.php:228-40`, `WHERE …vendor_id=? AND vendor_visible=1`). The former intra-client site-scope hole was closed. Client A cannot reach client B by id.
- **Class:** — (no gap). *(W2 active pentest will still attempt id-tampering to confirm at runtime.)*

### §53 Identity security — **PARTIAL**
- **Evidence:** reveal requires a reason & is logged `REVEAL` (`identity.php:544-58`); download gated + logged `DOWNLOAD` (`:518-31`) via hardened `send_uploaded_file` (nosniff + `CSP sandbox`); company-wide access review (Module 26, `iddoc_access_review` `:293`). **But** `person_documents.doc_number` is **plaintext** (schema `:145`, insert `:351-67`) and `file_data` is **base64 plaintext** (`:148`); **no encryption anywhere** (zero `openssl_encrypt`/`sodium` matches). Masking is display-only.
- **Gap:** §53 "encrypt at rest" **not met** — government/tax/ID numbers and scanned documents are cleartext in the DB; a dump exposes them. Access is well-governed; the data at rest is not protected.
- **Class:** **High · P1 · Effort M–L · Business impact High** (DPDP / identity-doc breach exposure).

### §54 Audit integrity — **PARTIAL**
- **Evidence:** sealed chain `entry_hash=sha256(prev|payload)` (`idems.php:3338-47`); `idems_audit_verify()` detects edits AND in-band deletions (`:3377-95`; `test_audit_chain.php`). No surgical edit/delete handler exists.
- **Gap:** not tamper-*proof* — `reset_run()` (`reset.php:105-25`, master-gated) can `DELETE FROM idems_audit` wholesale; the nightly retention purge `audit_trim_old()` (`compliance.php:143-50`, `cron.php:288`) deletes the chain head and **trips its own verifier** (broken-link) without re-sealing/marking, so retention is indistinguishable from tampering. No append-only constraint / external anchoring.
- **Class:** Medium–High · P1 · Effort M · Business impact High (evidential defensibility of the trail).

### File / PDF access — **PARTIAL** (same root as §51)
- **Evidence:** uploads held in DB not disk (`security.php:511-25`), content-vs-extension sniff (`:549-89`), served with nosniff + `CSP sandbox` (`helpers.php:322-33`) — not executable, not at a guessable URL. But the **access check is module-level only** on `/document-pdf`, `/endorsement-file`, `/checkin-photo`, `/iddoc-file` (record scope missing).
- **Class:** folded into §51.

### §Session / invitation / 2FA — **IMPLEMENTED**
- **Evidence:** idle+absolute session caps (`security.php:157-66`, `index.php:793-801`), id-regeneration on login (fixation defence), password policy + default-password detection + max-age, TOTP 2FA + hashed recovery codes + role-required 2FA, per-user/per-IP lockout, expiring portal invitations, reversible deactivation preserving audit authorship. Minor: `client_ip()` trusts first XFF (rate-limit attribution only).
- **Class:** — (no gap; XFF note Low/P3).

## W5 — Consolidation & platform capability (§23–27, §39, §45, §46, §48–50, §68, §72) — investigated

### §23–24 Canonical party/person model — **MISSING** (ad-hoc pairwise links only)
- **Evidence:** a human lives in 7 independent stores — `users`, `business_partners`, `partner_contacts`, `inspectors`, `candidates`, `client_users`, `vendor_users`. Hire spawns a NEW inspectors row (`ops.php:4666-90`) + `candidates.inspector_id`; portal accounts link by email match. `person_ref`/`person_key` (`recruit.php:871-938`) threads only candidate rows; `identity.php` is a doc vault, not a registry; `dedupe.php` is org-only.
- **Gap:** no `party`/`person` table, no shared `person_id` across stores, no cross-store identity resolution.
- **Class:** High · P1 (architectural) · Effort XL · Business impact High (but non-destructive convergence only — mapping layer, not table merge).

### §25 Canonical engagement model — **MISSING**
- **Evidence:** highest grouping is the **string** `contract_number VARCHAR` (`ops.php:321/335`, matched not FK'd `:1206/1223-55`). call→job→report are integer FKs; nothing groups multiple contracts/calls into an engagement/project/program.
- **Class:** Medium · P2 · Effort XL · Business impact Medium (future canonical grouping; not blocking).

### §26 Canonical task model — **PARTIAL**
- **Evidence:** `ops_pending_tasks()` (`ops.php:6600-6714`) + `attention_summary()` aggregate COUNTs across engines at read time; **no `tasks`/`reminders`/`work_item` table** exists; some matching is fragile free-text name-match (`:6675-82`).
- **Gap:** no persisted task entity (no assignment/lifecycle/due/reminder object); tasks are recomputed each load.
- **Class:** Medium · P2 · Effort L · Business impact Medium.

### §27 Canonical financial event — **MISSING**
- **Evidence:** no `financial_event`/`ledger`/`journal` table; each of quotations/invoices/receipts/vouchers/expenses/credit_recon owns its own money. books is an outbound Zoho bridge, not an internal event ledger.
- **Class:** High · P1 (architectural, pairs with §28/§30) · Effort XL · Business impact High.

### §39 Quality case — **PARTIAL / effectively MISSING**
- **Evidence:** NCR/CAPA/Complaints/Audits/Risks are separate modules/tables; the only convergence is CAPA as a downstream hub (typed origin FKs `complaint_id`/`audit_finding_id`/`review_id`, `capa.php:32/80/323`; `capa_from_complaint`).
- **Gap:** no single case record linking finding→NCR→complaint→risk→RCA→CAPA→evidence→effectiveness→closure; no shared case id / umbrella lifecycle.
- **Class:** Medium · P2 · Effort L · Business impact Medium (umbrella *view* over existing modules is the non-destructive path).

### §45 Terminology — **IMPLEMENTED**
- **Evidence:** `T/TP/Tl/Tlp/TH` term engine over `TERM_PACKS` + overrides (`terms.php:255-301`), editable at Settings→Terminology (`:329`). Acronyms (IDEMS/URFE/PDSO/TAPI) are internal route/capability keys, not UI labels. — (minor: internal identifiers stay acronymic by design).

### §46 Status standardisation — **PARTIAL**
- **Evidence:** calls have a canonical layer — dual `status`/`op_status`, `tosrm_call_status()` deterministic derive (`tosrm.php:122-49`), rank-validated transitions logged to `call_status_events`. 
- **Gap:** (a) **no disagreement detection** — once `op_status` set, legacy `status` is ignored; nothing flags/repairs when they diverge. (b) **not generalized** — jobs still carry vestigial `stage` + `closed_flag` with no canonical-status layer.
- **Class:** Medium · P1 · Effort M · Business impact Medium (per §46 "flag the record for repair" is currently missing).

### §48 Bulk operations — **PARTIAL**
- **Evidence:** bulk framework in `datatable.php:141-265` (declared actions, select-all, POST-only, count-stated confirm); `leads_bulk()` (`leads.php:796-854`) with per-row `act_log` audit — but **only leads adopt it**, and the flow is CONFIRM→EXECUTE→AUDIT with **no server PREVIEW/dry-run**.
- **Class:** Medium · P2 · Effort M · Business impact Medium.

### §49 Import/export — **IMPLEMENTED (2 domains) / PARTIAL (platform)**
- **Evidence:** validated preview→apply import for partners (`partnerimport.php:395-443`) and org/users (`orgadmin.php:857/965`); broad `csv_download()` export. **Gap:** not generalized to calls/jobs/inspectors/candidates/contracts; no reusable template-import service.
- **Class:** Low–Medium · P3 · Effort M · Business impact Medium.

### §50 API / integration — **PARTIAL**
- **Evidence:** mature per-integration outboxes with retry + idempotency (`ads_outbox`/`ads_sync_log` `adssync.php`; `books_outbox` payload-hash loop-breaker `booksbridge.php:14-24`); `api.php` is a single-purpose licence endpoint. **Gap:** no inbound webhook framework, no generic integration-log/retry-queue/idempotency abstraction, no authenticated public API.
- **Class:** Medium · P2 · Effort L · Business impact Medium.

### §68 Field-quality anomaly detection — **MISSING**
- **Evidence:** building blocks exist but unflagged — Module 44 SHA1 dedup is **same-report upload-time only** (`idems.php:5461/5501`), skips silently, no cross-report reuse detection; EXIF time + GPS stored (`:5400-5466`) but never compared/flagged; geofence is punch-only.
- **Gap:** no detection of identical responses / impossible time / cross-report duplicate photos / unusual GPS / missing evidence; no QUALITY REVIEW FLAG artifact.
- **Class:** Medium · P2 · Effort M · Business impact Medium (fraud/quality assurance).

### §72 Internal/external visibility — **PARTIAL**
- **Evidence:** record-level audience model — `CVP_AUDIENCE`/`CVP_VISIBILITY_AUDIENCE` (`cvp.php:29-41`, enforced `:1004-37`), `NCDCA_VISIBILITY` (`ncdca.php:65`), `report_docs.vendor_visible`, PDSO `client_visible`.
- **Gap:** no single universal PUBLIC/CLIENT/VENDOR/INTERNAL/CONFIDENTIAL classification across **fields/comments/evidence/documents**; per-record-type flags only; validation is per-portal-query, not one gate.
- **Class:** Medium · P2 · Effort L · Business impact Medium.

## W6/UX & observability (§15–22, §44, §47, §67) — Phase-1-grounded

| § | Point | Verdict | Evidence / Gap |
|---|---|---|---|
| §15 | Job 360 decision-first | **PARTIAL** | `job_now()` status/owner/next-action/blocker header + tabs (Module 05). Gap: not the full §15 first-screen (quick-actions row, reports-required inline, due). |
| §16 | Entity 360 standard | **PARTIAL** | Client/Vendor/Job/Contract/Report/Equipment 360s exist; consistency uneven (Module 49). Gap: no shared 360 shell / uniform tab set. |
| §17 | Universal activity timeline | **PARTIAL** | `act_render_timeline()` on complaint/NCR/lead/call/opportunity/invoice/customer. Gap: 4 incompatible renderers; job/contract/candidate/report off the spine; CANDIDATE/RECEIPT kinds unregistered. |
| §18 | My Work as operational inbox | **PARTIAL** | `ops_pending_tasks()`/`my_work` (Module 39). Gap: no SLA/priority/due per item; no snooze/delegate/mark-done. |
| §19 | Notification centre (action vs system) | **PARTIAL** | `/notifications` outbox (Module 38) + attention band. Gap: no user **Action Centre** (Open/Mark-done/Snooze/Delegate). |
| §20/21 | Command Centre vs System Health | **PARTIAL** | attention band + `/system-status` (Module 50) separated. Gap: no dedicated **Business Command Centre** KPI board with per-KPI drill (WHAT/WHY/WHO/WHEN/ACTION). |
| §22 | Global search | **PARTIAL** | permission-gated sources incl. opportunities/invoices (Module 37). Gap: contracts/inquiries sources not office-scoped; not all §22 entities. |
| §44 | Role desks | **PARTIAL** | dashboard role-orders + area homes. Gap: not explicit named persona desks. |
| §47 | Settings governance | **PARTIAL** | tabbed settings + audit + secret redaction (Module 14). Gap: no per-setting purpose/affected-modules/records-affected/before-after panel. |
| §67 | AI governance | **IMPLEMENTED (mostly)** | advisory-marked, never auto-acts, provenance on chain (Module 45). Gap: pre-send §4.2 confidentiality/redaction (flagged). |

## Compliance/quality & intelligence (§34–40, §76–78) — Phase-1-grounded

| § | Point | Verdict | Note |
|---|---|---|---|
| §34 | Change control | **PARTIAL** | `controlled_changes()` view (Module 42); no CR→impact→risk→approval→…→closure lifecycle object. |
| §35 | Training lifecycle | **PARTIAL** | cert watch (Module 43); no attendance/assessment/result flow. |
| §36 | Competence eligibility | **IMPLEMENTED** | server-side verdict + `work.assign` gate (Module 24). |
| §37 | Impartiality | **IMPLEMENTED (assign)** | verdict + assign gate (Module 25); not at issue (see §10). |
| §38 | Equipment impact | **IMPLEMENTED** | review-not-invalidate (Module 23). |
| §40 | QMS doc control | **IMPLEMENTED** | cdoc lifecycle + readiness (Module 41). |
| §76-78 | Contract/Client/Vendor 360 intelligence | **PARTIAL** | Modules 18/15/16; several KPIs partial; margin depends on §28/§30 truth. |

## Process / validation points (§55–66, §69–75, §79–84) — scheduled in later workstreams
- §55/56 Test harness + honest reporting: **PARTIAL** (`tests/run.php` 3240/0). Gap: single `qa/run-all-tests` with runtime/extension/env checks + categorized PASS/FAIL/SKIPPED/ENV. → W0.
- §57 E2E simulation (5→50→500→5,000): **MISSING** as executed. → W7.
- §58–60 Persona / click-tax: **MISSING** as executed. → W6.
- §61–64 Form/error/empty/filter audits: **PARTIAL**. → W6.
- §65/66 Performance & scale: **not measured**. → W7.
- §69 Competitor-learning: **MISSING**. → W6.
- §70/71 No-clutter / target IA: **process rule** — honored. N/A-OK.
- §73/74 Client/Vendor experience: **PARTIAL**. → W6.
- §75 Workforce/recruitment intelligence: **PARTIAL** (no capacity-vs-projected-demand answer).
- §79 CANONICAL APPLICATION MODEL doc / §80 legacy-compat doc: **MISSING** → W5/W9.
- §82 No premature "complete": **honored**. §83 final deliverable → W9.

---

# DEFECT REGISTER — prioritized fix backlog (for your approval)

Non-destructive only: each fix adds a guard/surface, versions data forward, or consolidates via a
canonical layer + mapping. Nothing deletes functionality.

### P0 — Critical
1. **§30 Historical financial reproducibility** (Critical/VeryHigh/L). `job_profit()` uses today's
   working-days/salary/office-% for old jobs. Fix (additive): snapshot the rate basis onto a job at
   close (effective-dated: working-days, daily-base, oh%, contingency%); `job_profit()` prefers the
   snapshot when present, else computes live (open jobs unchanged). Reproducible forward + backfillable.
2. **§10 Issuance readiness completeness** (High/VeryHigh/M). Add vetting-complete / report-completeness
   / competence / impartiality / blocking-NCR / client-acceptance probes to `idems_issue_readiness()`
   as advisory rows first, then configurable block/warn per contract/service. Reuses existing verdicts.
3. **§11 Seal fail-open** (Medium/High/S). Seal failure must **flag** the issued report as "unsealed —
   needs re-seal" and `idems_content_check()` must report unsealed as a problem, not `ok`.

### P1 — High
4. **§51 IDOR scope** (High/High/M) — apply `scope_clause`/`idems_file_authorized` to `/job`,
   `/document`, `/document-pdf`, `/invoice`, `/endorsement-file`, `/checkin-photo`; fail-closed.
5. **§53 Identity encryption at rest** (High/High/M–L) — encrypt `doc_number` + `file_data`; keep
   masking + access log.
6. **§54 Audit chain protection** (Med-High/High/M) — re-anchor the retention-trim head with a signed
   checkpoint; require typed confirmation + audit event before `reset_run()` touches `idems_audit`.
7. **§28 Financial one-engine convergence** (High/High/M) — point MIS + SBU-PL at `job_profit()`'s
   profit/cost; reconcile `boss_profit` to Σ`job_profit`. **Changes displayed figures → explicit sign-off.**
8. **§31 Overhead versioning** (High/High/M) — store per-job overhead/contingency % at close (part of #1).
9. **§33 Invoice readiness** (High/High/M) — READY/NOT-READY panel with blockers (reports issued/
   accepted, PO, previous-billing vs contract value, milestone), configurable, advisory-first.
10. **§6 Applicability override audit** (High/High/M) — capture reason+person+time, audit event, set
    the promised "not allocated" flag.
11. **§4 PDF role model** (High/High/S–M) — print Vetted-by + Issued-by; stamp Prepared timestamp.
12. **§9 Return-to-inspector detail** (Med/High/M) — structured section/field/evidence-ref/correction/
    deadline alongside the free-text note.
13. **§46 Status disagreement detection** (Med/Med/M) — integrity check flagging legacy vs op_status divergence.

### P2 — Medium (additive consolidation layers)
14. §26 Task entity + §18 SLA/priority/snooze/delegate. 15. §19 Action Centre. 16. §20 Business Command
Centre. 17. §68 anomaly-flag surface. 18. §39 Quality Case umbrella. 19. §72 field/evidence visibility.
20. §48 bulk preview + adoption. 21. §50 webhook/integration-log layer. 22. §29 recognised revenue +
two-invoice-truth reconcile. 23. §32 inter-office settle states. 24. §8 builder persona previews.
25. §35 training attendance/assessment.

### P2/P3 — Architectural (non-destructive convergence, staged)
26. §23/24 canonical person/party **mapping layer** (shared id, no merge). 27. §25 engagement grouping
(view over contract_number). 28. §27 financial-event stream (gradual consumer migration). 29. §79/§80
CANONICAL APPLICATION MODEL + legacy-compat docs.

### P3 — Minor
30. §5 submit-wording. 31. XFF note. 32. §49 generalized import.

---

## DELIVERED (see `00-program.md` Done log for commits + test counts)

Non-destructive, tested, pushed on `claude/quotation-management-workflow-5dokb2`. Suite grew to 3544, 0 failed.

- **P0:** §30 (frozen cost basis), §10 (issuance readiness probes), §11 (seal fail-closed).
- **P1 security:** §51 (IDOR scope on job/document/pdf/invoice/file), §53 (identity encryption at rest),
  §54 (audit-chain trim anchor + wipe evidence), §22 (global-search cross-SBU leak closed).
- **P1 report-workflow:** §6 (applicability override audit), §4 (four report roles on PDF), §9 (structured
  return-to-inspector), §46 (call-status disagreement integrity check).
- **P2 consolidation layers:** §23/24 (canonical person `party.php`), §39 (quality-case umbrella),
  §68 (evidence reuse across jobs), §72 (visibility gate), §25 (engagement grouping), §48 (bulk
  preview/dry-run), §17 (CANDIDATE/RECEIPT on the activity spine), §29 (revenue reconciliation, read-only),
  §47 (settings governance registry), §32 (inter-office settlement matrix).
- **P2/P3 architecture:** §79/§80 (`02-canonical-application-model.md`).

**Still open / needs your decision:** §28 (profit-engine convergence — changes displayed numbers, sign-off
gated; §29 is its evidence base) · §31/§33 (overhead versioning done via §30; invoice-readiness panel
pending) · §19/§20/§26/§50 (net-new subsystems — held under STOP FEATURE CREEP) · §8/§34/§35/§16/§49 (larger
UX/lifecycle builds).

---

# EXECUTIVE SUMMARY (W1 audit)

**Solid / verified IMPLEMENTED:** CSRF (global fail-closed), portal tenant isolation (id in WHERE incl.
PDF), session/2FA/lockout/invitations, issued-report immutability record + controlled revision,
terminology engine, competence/impartiality *assignment* gates, equipment impact-review, QMS doc
control, AI advisory-only governance with provenance, DB-held un-executable hardened file storage, and
the canonical per-job `job_profit()` engine.

**Critical (P0):** historical financial reproducibility is missing — profit for any past job/contract
silently changes when today's salary, office %, holidays, or the month change; every financial screen
inherits it. Fix first.

**Systemic security (P1):** the correct office/SBU scope pattern exists but is applied on lists and
dropped on single-record/PDF/file reads → cross-office IDOR on jobs/reports/report-PDFs/invoices/
endorsement-files/check-in-photos. Identity docs are masked+logged but **not encrypted at rest**. The
audit chain is tamper-evident but master-wipeable.

**Report workflow (P1):** the issue gate omits vetting/competence/impartiality/blocking-NCR/client-
acceptance; the content seal is fail-open; the PDF shows only 2 of 4 roles; applicability overrides
aren't audited.

**Consolidation:** canonical person / engagement / financial-event models and a quality-case umbrella
are genuinely absent — but per the non-destructive rule these are convergence layers (mapping/views),
not rewrites, and are P2/P3.

**Nothing rebuilt or claimed done from memory** — every verdict is code-grounded (file:line) or
executed. **Fixes are not yet applied.** Recommended order: P0 §30 + §10 + §11, then P1 security
(§51/§53/§54), then report-workflow P1s. Item #7 (financial convergence) changes displayed numbers and
needs explicit sign-off before I touch it.
