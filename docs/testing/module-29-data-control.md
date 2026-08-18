# Inspection Ops — Data & Information Control — Test & Documentation Report

> **Prompt 3 · Module MOD-DATACTRL.** Read from `lib/idems.php` (`idems_content_payload`/
> `idems_content_seal_compute`/`idems_seal_content`/`idems_content_check`,
> `idems_freeze_presentation`/`idems_render_schema`/`idems_render_template`,
> `document-delete` soft-delete), `lib/trust.php` (`verify_code_for`/`verify_url`/
> `verify_lookup`, evidence `chain_verify`), `lib/datacontrol.php` (`sw_create`,
> `integrity_run`, `access_report`, `failure_create`/`failure_close_block`), `lib/controldocs.php`
> (`cdoc_*`), `lib/retention.php` (`retention_*`). Views `verify.php`, `data_control.php`,
> `cdocs.php`, `retention.php`.

| | |
|---|---|
| **Module** | Data & Information Control (MOD-DATACTRL) · Area Accreditation |
| **Personas** | P-QM/P-DOCCTRL (controlled docs, retention), P-MASTER (integrity, failures), **public** (verify) |
| **Risk weight** | **High** — ISO/IEC 17020 §8.3/§8.4; the seal + verify prove an issued report is unaltered |
| **Verdict** | Complete-with-defects (confirm seal strength, restore path, backup, integrity checks) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Control of records and documents, plus public verification. On **issue**, a report becomes
immutable: `idems_seal_content` freezes a **content hash** (`content_seal`),
`idems_freeze_presentation` freezes the schema + template so later config never alters the
issued render, and a **verify code** is minted. The public **`/verify?c=`** page answers a
deliberately narrow question — genuine? altered? evidence on-site? — by combining the
content seal (`idems_content_check`) with the **evidence hash chain** (`chain_verify` over
`report_files.content_sha1`), while hiding findings/prices. **Soft-delete** keeps the row +
logs DELETE (no restore route). A **data-control register** adds software-validation records,
16 live **integrity checks** (incl. `audit_chain` via `idems_audit_verify`), an **access
report** (dormant/no-2FA/admin powers), and a **system-failure** register (§7.11) with a
close gate. **Controlled documents** (`controlled_docs`) and a **retention** schedule round
it out.

Screens: `/verify`, `/data-control`, `/data-check-run`, `/sw-validation-add`, `/failure-*`,
`/cdocs`+`/cdoc-*`, `/retention*`. Tables: `report_docs` (seal/freeze/verify_code),
`idems_audit`, `sw_validations`, `data_check_runs`, `system_failures`, `controlled_docs`,
`retention_rules`.

---

## B. Screen-by-screen catalogue

**`/verify`** — public, no account: enter the code → genuine/altered/unsealed + evidence
status, no confidential content. **`/data-control`** — software validation, integrity runs,
access report, system failures. **`/cdocs`** — controlled documents (DRAFT → CURRENT →
SUPERSEDED → WITHDRAWN, supersede clones a new rev). **`/retention`** — the retention schedule.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-DC-form-001 | Verify code from a reduced alphabet (no O/0, I/1, S/5), unique; lookup trims/uppercases. |
| TC-DC-form-002 | `sw_create` requires component + what-tested + **purpose** (else refused). |
| TC-DC-form-003 | Failure close gate: data-affected answered; YES needs a note + CAPA; immediate action recorded. |
| TC-DC-form-004 | Timestamp edit: only inspection/issue date, **reason mandatory**, logs TIMESTAMP_EDIT. |

---

## D. Functions & logic  *(seal + verify — highest scrutiny)*

- **Content seal** (`idems_content_payload` → `idems_content_seal_compute` → `idems_seal_content`):
  identical payload function at issue and verify guarantees agreement; `idems_content_check`
  returns sealed/altered/unsealed. **The seal is an unkeyed SHA-256** — anyone with DB write
  access who recomputes it after editing content makes `/verify` pass again (GAP-DC-001).
  **TC-DC-fn-001** (an unaltered issued report verifies genuine), **TC-DC-fn-002** (a tampered
  body reads altered — via the seal AND the evidence chain).
- **Presentation freeze** (`idems_freeze_presentation`): once-only snapshot; issued reports
  render from it so later template/schema edits never change them. **TC-DC-fn-003.**
- **Public verify** (`verify_lookup`): narrow answer, hides confidential content, excludes
  deleted, drafts → not_issued, fully error-wrapped. **TC-DC-fn-004.**
- **Soft-delete**: `deleted=1` + logged; **no restore route** (GAP-DC-002). **TC-DC-fn-005.**
- **Integrity checks** (`integrity_run`): 16 live DB checks incl. audit_chain, iddoc
  retention, stale permissions, file-decode sampling. **TC-DC-fn-006.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| report issued → immutable | finalize | seal + freeze + verify code |
| issued → corrected | revise (new rev) | original untouched |
| any → deleted | soft-delete | row + DELETE log kept; no restore |
| controlled doc DRAFT → CURRENT → SUPERSEDED/WITHDRAWN | status/supersede | back-link |
| system failure OPEN → resolved | close gate | data-affected + CAPA |

- **TC-DC-life-001:** an issued report cannot be edited; a correction is a new revision.
- **TC-DC-life-002:** a soft-deleted report stays in the audit log and cannot be un-deleted
  via the UI.

---

## F. Roles, permissions & data scope

Issue/lock/delete: `idems.finalize`. Timestamp edit: `idems.timestamp.edit` (Branch App
Manager). Template approve: `idems.template.approve`. Data-control/controlled-docs/retention:
`mod.datacontrol.view/edit`/`mod.audits.view`/`settings.manage`/master. **Verify is public.**
Reports scoped by office/SBU; the access report reads the live permission engine.

- TC-DC-perm-001 (delete without `idems.finalize`) → refused.
- TC-DC-perm-002: verify never exposes confidential content regardless of who opens it.

---

## G. Settings

`public_base_url` (verify URL), `idems_irn_format`/`idems_company_code`/`idems_serial_width`
(controlled numbering), `SW_REVIEW_DEFAULT` (365), `INTEGRITY_STALE_DAYS` (90), retention
schedule (audit-trail 180-day floor). Controlled-docs + retention pack-gated. **TC-DC-set-001:**
the verify URL uses the configured base.

---

## H. Cross-module integration

**IDEMS issue** (seal/freeze/verify-code — MOD-06/08), **Audit trail** (`audit_chain` check;
every state change sealed — MOD-28), **Evidence** (`report_files.content_sha1` chain feeds
verify), **CAPA** (system failures → CAPA clause 8.3), **Identity** (retention redaction).
Idempotency: seal/freeze are once-only.

---

## I. Data integrity & audit

The seal + evidence chain + hash-chained audit trail together attest an issued report. Gaps:
the **content seal is unkeyed** (recomputable after a DB edit); `verify_code` is a **bare
column** (an edited row still resolves — the seal/chain must flag the tamper); the seal
**excludes attachments/signatures** from the payload (covered only by the evidence chain +
signature snapshot); seal/freeze are **fail-open** (a silent failure leaves a report unsealed,
reported later as "unsealed" not "error"). **TC-DC-int-010:** a body tamper is detected;
**TC-DC-int-011:** an attachment swap is caught by the evidence chain.

---

## J. Reports & outputs

The public verify page, the data-control register (software validation, integrity runs,
access report, failures), controlled documents, and the retention schedule. **No first-class
encrypted DB backup/export** (only per-module CSV + DPDP subject export) (GAP-DC-003).
**TC-DC-out-001:** verify shows genuine/altered without leaking content.

---

## K. Negative, edge & resilience

An unaltered issued report (genuine); a DB-edited body (altered — via seal/chain); a
DB-edited body **with the seal recomputed** (verify wrongly passes — unkeyed seal risk); an
attachment swap (evidence chain catches); a soft-deleted report (no restore); a report where
sealing silently failed at issue (reads "unsealed"); an integrity run flagging a broken
audit chain.

---

## L. TPIA operational suitability

Serves §8.3/§8.4: controlled numbering, immutable issued records with a public verification
that preserves confidentiality, a controlled-document lifecycle, a retention schedule, and
live integrity checks. The unkeyed seal, the missing restore/backup, and the fail-open
sealing are the items to harden for full evidential and continuity assurance.

## M. Management usefulness

`datacontrol_readiness`, the integrity runs, the access report, and the failure register give
QM/master a data-governance view; the public verify builds client trust. Confirm integrity
runs are current (stale > 90 days flagged).

## N. UI/UX

A dead-simple public verify (code in → answer out), a data-control dashboard, controlled-doc
supersede. Terminology via `T*()`.

## O. Security

Delete/timestamp/template gated; verify public but content-safe; seal + evidence chain +
audit chain layered — but the **content seal is unkeyed** (add an HMAC/secret), there is **no
restore** and **no encrypted backup**, and sealing is fail-open. Address these for §8.4.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E immutability |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I seal strength |
| 14 Audit | **Priority** | §I chain |
| 15 Outputs | Y | §J verify |
| 16 TPIA suitability | Y | §L §8.3/§8.4 |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O seal/backup |
| 21 Import | Partial | CSV/DPDP export |
| 22 Notifications | N-A | — |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | accreditation pack |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | retention |
| 28 Performance | Partial | — |
| 29 Backup | **Gap** | §J no first-class backup |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-DC-001 | (verify — Major) | The **content seal is an unkeyed SHA-256** — anyone with DB write access can edit content and recompute the seal so `/verify` passes again; `verify_code` is a bare column that still resolves after an edit. Add an HMAC/secret so the seal can't be forged; integrity currently rests on the separate hash-chained audit trail. |
| GAP-DC-002 | (verify) | **No restore for soft-deleted reports** — `deleted=1` is one-way in the UI (only a count is shown). Add a controlled restore or document the intent. |
| GAP-DC-003 | (verify) | **No first-class encrypted DB backup/export** despite §8.4 — only per-module CSV + DPDP subject export; retention disposal is descriptive, not enforced (except iddoc redaction). Add a backup/retention-disposal path. |
| GAP-DC-004 | — | Seal/freeze are **fail-open** at issue (a silent failure leaves a report unsealed, later read as "unsealed" not flagged as an error), and the seal excludes attachments/signatures — surface issue-time sealing failures. |

---

## R. Traceability

RTM slice: `/verify`, `/data-control`, `/cdocs`, `/retention`, issue seal/freeze × dims 1–29
→ TC-DC-* → results → DEF/GAP. **Verdict: Complete-with-defects** — seal strength (keyed),
restore/backup, and fail-closed sealing are the exit conditions.
