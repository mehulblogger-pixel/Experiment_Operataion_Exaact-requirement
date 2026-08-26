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
