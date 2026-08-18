# Inspection Ops — Inspection Reporting (IDEMS core) — Test & Documentation Report

> **Prompt 3 · Module MOD-IDEMS (core).** Read from `lib/idems.php`
> (`ops_idems_documents`, `ops_idems_report_types`, `ops_idems_builder`, `ops_idems_fill`,
> `idems_existing_twin`, `idems_po_locked`, `idems_generate_irn`, `idems_completeness_check`,
> `idems_can_edit_doc`/`idems_status_allows_edit`, `idems_handle_uploads`,
> `idems_compress_image`, `report_pdf_build`, `idems_context_for`, `idems_prefill_values`,
> `idems_qa_run`, `idems_field_score`), `lib/pdf.php` (`SimplePDF`, `image_to_jpeg`), views
> `idems/register.php`, `idems/doc_form.php`, `idems/doc_detail.php`, `idems/fill.php`,
> `idems/report_types.php`, `idems/builder.php`.

| | |
|---|---|
| **Module** | Inspection Reporting — IDEMS core (MOD-IDEMS) · Area Reporting |
| **Personas** | P-INSP (create/fill/submit), P-COORD (create/oversee), P-VET/P-APPR (downstream — MOD 07/08), P-MASTER, P-CLIENT-neg |
| **Risk weight** | **Highest** — produces the signed, numbered document the client relies on; the app's reason to exist |
| **Verdict** | Complete-with-defects (photo fidelity, IRN uniqueness, completeness gate, edit-lock at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. Photo-in-PDF fix (caption + data reload) verified. |

---

## A. Module overview

IDEMS is the report engine. A **report type** (`report_types`) carries a **schema** of
**sections** (`report_sections`) and **fields** (`report_fields`, 20+ field types incl.
tables, media, signature blocks, conditional visibility). A **report document**
(`report_docs`) is one filled instance: a header (client/vendor/call/job/PO/drawing/QAP/
standards/inspector/dates), a JSON `data` body, uploaded **files/photos**
(`report_files`), an **IRN** (permanent client-facing number), a **lifecycle** and a
**tamper-evident audit chain** (`idems_audit`).

Core lifecycle (this module): **DRAFT → SUBMITTED/VETTING** (submission locks editing).
Vetting/approval (MOD-07) and Release Notes (MOD-08) continue it. Statuses:
DRAFT / SUBMITTED / VETTING / UNDER_REVIEW / APPROVED / ISSUED / REJECTED / ARCHIVED.

Screens: `/documents` (register: search, type/status filter, "awaiting my approval" chip,
open/issued counts), `/document-new?call=&job=` `/document-edit`, `/document?id=`
(detail: playbook, QA auditor, scorecard, AI advisory, files, audit, actions), `/fill`
(the form), `/report-types` `/builder` (schema authoring), `/document-pdf` `/document-docx`.

---

## B. Screen-by-screen catalogue

**`/documents`** — register scoped by office/SBU; filters q / type / status; **"Awaiting
my approval"** chip (`idems_awaiting_my_approval_clause`); open vs issued counts.

**`/document-new`** — report type (narrowed to the job's **deliverables**;
"(no form yet)" when a type has no fields), client (blocked-client gate via masters),
vendor, call/job link, project code/name, office/SBU, PO ref, drawing no/rev, QAP rev,
standards, location, product category, material grade, inspector, approver (auto-filled
from inspector→approver map), inspection date, result, release status, remarks. Prefills
from `idems_context_for(call, job)` when opened from a call/job.

**`/fill`** — renders the schema: text/textarea/number/date/select/checkbox/table/media/
signature blocks with conditional visibility (`idems_cond_visible`), option sources
(call/column lookups), autofill (`idems_autofill`), photo/evidence upload with
caption + GPS/EXIF stamping.

**`/document?id=`** — playbook (guided next step), status pill, completeness, QA traffic
light, scorecard (scored types only), AI advisory (if configured), files gallery, audit
trail, and the submit/vet/approve/RN actions (downstream modules).

**`/report-types`, `/builder`** — author report types, sections, fields, conditions,
templates; seed the core set; preview.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-IDEMS-form-001 | Report type required; a type with **no schema** warns "(no form yet)" and does not open a blank fill screen. |
| TC-IDEMS-form-002 | Deliverables narrowing: the type dropdown is limited to what the job owes (never narrowed to empty). |
| TC-IDEMS-form-003 | **Twin guard:** same type + same job/client + same inspection date = the same report — the second create redirects to the first, burns no second IRN; a different date creates a new one. |
| TC-IDEMS-form-004 | **PO-lock:** once a report of this type for this PO is APPROVED/ISSUED, a new one for the same PO is refused (different PO allowed; Master may override). |
| TC-IDEMS-form-005 | Conditional fields: `idems_cond_visible` shows/hides by another field's value (eq/ne/in/nonempty/empty). |
| TC-IDEMS-form-006 | Table/media/signature fields save and reload intact; numbers/dates validated. |

---

## D. Functions & logic  *(the report body — highest scrutiny)*

- **IRN generation** (`idems_generate_irn`): company/branch/FY/serial tokens →
  a **permanent, unique** number stamped at DRAFT and never silently reused.
  **TC-IDEMS-irn-001:** serial is unique per scope (width configurable);
  **TC-IDEMS-irn-002:** FY rollover and branch code correct; a revision keeps lineage.
- **Photos in the PDF** (the fixed bug): `idems_doc_files` returns **caption** and
  `report_pdf_build` **reloads full file rows (with `data`)** when given light rows;
  each photo embeds via `image_to_jpeg` (GD→Imagick, HEIC-capable); a failed embed draws
  a **labelled placeholder**, never a silent blank. **TC-IDEMS-pdf-001** (photos appear
  with captions), **TC-IDEMS-pdf-002** (HEIC decodes), **TC-IDEMS-pdf-003** (undecodable
  image → placeholder, not blank).
- **Completeness gate** (`idems_completeness_check`): every applicable mandatory check
  must pass before submit; Master may override **only with a recorded reason** (audited).
  **TC-IDEMS-gate-001** (fail blocks submit), **TC-IDEMS-gate-002** (override needs reason
  + logs GATE_OVERRIDE).
- **Blank→NA on submit:** empty text/textarea/select fields become "NA" (or
  `report_blank_fill`) so nothing reads as forgotten. **TC-IDEMS-submit-001.**
- **Edit-lock** (`idems_status_allows_edit`): editable only in ''/DRAFT/REJECTED; once
  SUBMITTED/VETTING/UNDER_REVIEW/APPROVED/ISSUED the body is frozen
  (`idems_can_edit_doc`). **TC-IDEMS-lock-001** — a crafted `/document-edit` /
  `/document-submit` on a locked doc is refused.
- **QA auditor** (`idems_qa_run`) + **scorecard** (`idems_score_doc`): advisory only —
  the human remains the approver. **TC-IDEMS-qa-001** (traffic light + issues shown, no
  gate unless configured).
- **Prefill/autofill** (`idems_context_for`, `idems_prefill_values`, `idems_autofill`):
  from call/job — client/vendor/PO/QAP/standards/deliverables carried in, never
  overwriting typed values.

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| (new) → DRAFT | create | type + master details; twin & PO-lock guards; IRN stamped |
| DRAFT/REJECTED → editable | edit/fill | `idems_status_allows_edit` |
| DRAFT → **VETTING** | submit (vetting gate on, not RN) | completeness gate; chain built later (MOD-07) |
| DRAFT → **UNDER_REVIEW** | submit (vetting gate off) | completeness gate; approval chain built (MOD-07) |
| any → REJECTED | sent back | reopens editing |
| APPROVED → ISSUED | issue | MOD-07/08 |

- **TC-IDEMS-life-001:** submission **locks editing** (status leaves DRAFT); the inspector
  can no longer change the body.
- **TC-IDEMS-life-002:** a rejected report returns to editable and can be resubmitted.
- **TC-IDEMS-life-003:** the RN flag (`document-rn-flag`) stays settable after body-lock
  (it is a disposition decision, not report body) — MOD-08.

---

## F. Roles, permissions & data scope

Create/edit/fill: `is_master()` or `can('mod.idems.edit')`. Vet: `idems.finalize` /
master (MOD-07). Approve: resolved approver (MOD-07). Register scoped by office/SBU
(`scope_clause('d.office_id','d.sbu')`). Files authorised via `idems_file_authorized`.

- TC-IDEMS-perm-001 (unauthorised create POST) → refused.
- TC-IDEMS-perm-002 (P-INSP editing a **locked/other-office** doc via crafted id) →
  refused (scope + lock both hold).
- TC-IDEMS-scope-001: register/PDF/file download respect office/SBU scope.

---

## G. Settings

Company/branch codes, serial width, FY label; IRN format; report-type catalogue &
schema; core-set curation; blank-fill token (`report_blank_fill`); vetting gate & checklist
(MOD-07); completeness-check rules; terminology (report/inspector/client via `T*()`);
signature capture; AI provider (advisory). **TC-IDEMS-set-001:** serial width/format change
reflects in new IRNs only (existing untouched); **TC-IDEMS-set-002:** blank-fill token
honoured on submit.

---

## H. Cross-module integration

**Calls/Jobs** (context prefill; deliverables → owed types; final-docs close gate),
**Clients/Vendors** (masters; blocked-client; vendor assessment prefill from complaints/
inspections/NCRs), **Vetting/Approval** (MOD-07), **Release Notes** (MOD-08), **NCR**
(`idems_raise_ncrs_from_audit`), **Hold/Witness** (RN gating), **Equipment/Competence/
Identity** (evidence & gates), **Client portal** (issued report delivery). Idempotency:
double create/submit must not duplicate IRNs or chains — TC-IDEMS-int-001.

---

## I. Data integrity & audit

`idems_audit` is a **hash-chained, tamper-evident** log (CREATE, EDIT, IRN_GEN,
GATE_OVERRIDE, SUBMIT, vet/approve/issue). `idems_audit_verify` detects a broken chain.
Content seal (`idems_content_seal_compute`/`idems_seal_content`) fixes the issued body;
`idems_content_check` detects post-issue tampering. **TC-IDEMS-int-010:** an out-of-band
row edit breaks the chain and is detected; **TC-IDEMS-int-011:** the IRN never changes
after generation; a revision is a new lineage, not a rewrite.

---

## J. Reports & outputs

`report_pdf_build` (`SimplePDF`, JPEG-only): letterhead, header block, sectioned body,
tables, **photo grid with captions**, KPIs, signatures (snapshotted at issue),
watermark/copy. Also `/document-docx`. **TC-IDEMS-out-001:** the PDF matches the on-screen
form field-for-field; **TC-IDEMS-out-002:** photos + captions present; **TC-IDEMS-out-003:**
issued PDF carries the frozen signatures and the content seal.

---

## K. Negative, edge & resilience

Report type with no schema; a huge photo / HEIC / corrupt image; a twin double-click; a
second report for a settled PO; a completeness fail; an override without a reason
(refused); editing after submit (refused); a crafted cross-office id; an offline-queued
fill replay; a table field with 100 rows; a conditional field toggling mid-fill.

---

## L. TPIA operational suitability

Configurable schemas cover the real TPIA report family (inspection, final, release note,
vendor assessment/audit, expediting, progress, endorsement) without agency-specific
hardcoding. IRN discipline, twin/PO settlement, completeness gate, evidence with GPS/EXIF,
and the tamper-evident chain match third-party-inspection assurance needs.

## M. Management usefulness

Register counts (open/issued), "awaiting my approval" queue, QA traffic light, scorecards,
expediting risk, vendor 360. Confirm counts and the awaiting-me clause match reality.

## N. UI/UX

Guided playbook (next step), type narrowing + "(no form yet)" warning, prefill from
call/job, inline "+ Add new" client, evidence upload with caption, conditional fields.
Terminology throughout via `T*()`.

## O. Security

Create/edit/submit authorised server-side; edit-lock and PO-lock hold on crafted POSTs;
file downloads authorised & scoped; completeness override is Master-only + reason-logged;
audit chain integrity; content seal after issue; no client-facing number leaks across
scope.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D; body + photos priority |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E (core), MOD-07/08 |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I — chain + seal + IRN |
| 14 Audit | Y | §I hash chain |
| 15 Outputs | **Priority** | §J — PDF/photo fidelity |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Partial | template→form autoform |
| 22 Notifications | Y | submit/vet/approve (MOD-07) |
| 23 Offline | Y | PWA fill; replay must not duplicate |
| 24 AI | Y | QA auditor + advisory (advisory only) |
| 25 Licensing | Y | IDEMS module seat |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | IRN FY, dates |
| 28 Performance | Partial | large tables / photo sets |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| DEF-IDEMS-001 | Fixed (verify) | Photos now embed with captions (`idems_doc_files` caption + `report_pdf_build` full-row reload). Confirm on a real multi-photo, HEIC-bearing report. |
| GAP-IDEMS-001 | (verify — Major) | Confirm **IRN uniqueness** under concurrency (two creates racing the same serial) and that the twin/PO guards hold on a crafted POST, not just the form. |
| GAP-IDEMS-002 | (verify) | Confirm the **completeness gate** and **edit-lock** are enforced on `document-submit`/`document-edit` POSTs (crafted request refused; override always logged). |
| GAP-IDEMS-003 | (verify) | Confirm the **content seal** fixes the issued body and `idems_content_check`/`idems_audit_verify` detect post-issue tampering. |

---

## R. Traceability

RTM slice: `/documents`, `/document-*`, `/fill`, `/report-types`, `/builder` × dims 1–29 →
TC-IDEMS-* → results → DEF/GAP. **Verdict: Complete-with-defects** — photo/PDF fidelity,
IRN uniqueness, and completeness/edit-lock enforcement are the exit conditions.
Vetting/approval and release-note flows continue in MOD-07 and MOD-08.
