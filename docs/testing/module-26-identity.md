# Inspection Ops — Identity Documents — Test & Documentation Report

> **Prompt 3 · Module MOD-IDENTITY.** Read from `lib/identity.php` — register
> (`iddoc_add`/`iddoc_list`/`iddoc_current`/`iddoc_mask`/`iddoc_reveal`/`iddoc_redact`,
> `iddoc_run_retention`, `iddoc_holders`, `iddoc_log`/`iddoc_access_log`, `ops_identity`)
> and the site-doc gate (`sitedoc_required`/`sitedoc_check`/`sitedoc_block_message`,
> `ops_sitedocs`), gate wiring `lib/packs.php` (`work.assign`). Views `identity.php`,
> `site_docs.php`, `job_form.php`.

| | |
|---|---|
| **Module** | Identity Documents (MOD-IDENTITY) · Area Privacy/Operations |
| **Personas** | P-IDDOC (`person.iddoc.manage`), P-COORD (allocate), P-MANAGER (site-doc override), P-MASTER |
| **Risk weight** | **High** — holds passports/IDs (DPDP-sensitive) and gates site entry; a leak or a missing site doc are both serious |
| **Verdict** | Complete-with-defects (confirm masking/access-log, retention, site-doc gate) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Two subsystems: (A) a **DPDP-conscious identity-document register** (`person_documents`) —
ID proofs for personnel, stored with a **masked** number (`•••• last4`, full number revealed
only with a logged reason), a **retention** clock (`retain_until = max(expiry, filed) +
retain_days`, default 730) with nightly **auto-redaction**, an **access log** of every
view/download/reveal/share, and an "who can open a passport" report; and (B) **site-document
requirements** (`site_doc_requirements`) with an allocation gate — a mandatory site doc the
inspector lacks (valid on the visit date + margin) **blocks** the job, **overridable** by a
manager with a recorded reason (`sitedoc_override_note`/`_by`), a non-mandatory one warns.

Screens: `/identity` (register + access log), `/iddoc-add/-file/-reveal/-share/-redact/
-retention`, `/site-docs` + add/delete; site-doc override box on `job_form`. Tables:
`person_documents`, `person_document_access`, `site_doc_requirements`, `jobs.sitedoc_*`.

---

## B. Screen-by-screen catalogue

**`/identity`** — readiness rollup (verified/without/expiring/expired/copies-held/
due-redaction), people, per-person docs (masked), access log. Actions: add, reveal (reason),
share (recipient), redact, set retention. **`/site-docs`** — requirements per client/site
(mandatory, valid-days margin). **Job form** — advisory identity note + site-doc override box.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-ID-form-001 | Doc kind in the lookup; doc number required; **expiry required** (so the copy can be retired). |
| TC-ID-form-002 | File MIME-gated; number masked in the list; full number never selected by default. |
| TC-ID-form-003 | Reveal requires a non-empty reason; share requires a recipient. |
| TC-ID-form-004 | Site requirement: mandatory flag; valid-days-after margin. |

---

## D. Functions & logic  *(privacy + gate — highest scrutiny)*

- **Masking + logged reveal** (`iddoc_mask`, `iddoc_flash_number`): full number shown only
  via a session-only flash with a logged reason; `iddoc_list` never selects `file_data`.
  **TC-ID-fn-001** — a reveal without a reason is refused and every access is logged.
- **Retention** (`iddoc_run_retention`, cron): redacts docs past `retain_until` (empties
  file/number, keeps the row as evidence the check happened). **TC-ID-fn-002.**
- **Site-doc gate** (`sitedoc_check` via `work.assign`): a mandatory doc the inspector lacks
  (or expired) on the visit date + margin **blocks**, overridable with a manager reason;
  distinguishes "no doc on file" from "expired". **TC-ID-fn-003** — a crafted allocation is
  blocked; the override is authority + reason logged.
- **Holders report** (`iddoc_holders`): lists every non-superuser who can open a passport
  (the auditor's question). **TC-ID-fn-004.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| doc → current | add | not redacted + not past expiry |
| current → expiring/expired | time | expiry date |
| current → redacted | manual/retention | file+number emptied, row kept |
| site requirement present | allocation | mandatory doc valid on date (overridable) |

- **TC-ID-life-001:** a doc past `retain_until` is auto-redacted, the row retained.
- **TC-ID-life-002:** a legacy doc with no expiry reads as perpetually valid (GAP-ID-002).

---

## F. Roles, permissions & data scope

`person.iddoc.view` (see masked), `person.iddoc.manage` (add/reveal/share/redact) —
deliberately **not** tied to admin-level (a branch manager does not automatically get
passports). Master always passes and is logged. Site-docs: iddoc/clients view+edit or
master. **Register scoped to active inspectors company-wide** (no office scoping); person
kind hard-coded INSPECTOR (GAP).

- TC-ID-perm-001 (reveal without `iddoc.manage`) → refused.
- TC-ID-perm-002 (a branch manager without iddoc.manage) cannot open a passport.

---

## G. Settings

`iddoc_retain_days` (730), `iddoc_purpose`, `IDDOC_KINDS` lookup (editable). Site-doc
requirements per client/site. **TC-ID-set-001:** retention days change moves `retain_until`
for new docs.

---

## H. Cross-module integration

**Jobs allocation** (site-doc gate via `work.assign` — MOD-05), **Personnel/Recruitment**
(keyed to `inspectors`; candidate erase references `iddoc.manage`), **Clients** (site
requirements join clients/addresses), **Audit** (access log; retention runs from cron).
Idempotency: retention is idempotent.

---

## I. Data integrity & audit

Every access (view/download/reveal/share) logged with IP + time; redaction keeps the row
as evidence. **Files stored base64 in the DB, plaintext number, no encryption at rest**
(GAP-ID-001). The access log swallows its own errors (a broken log table silently loses the
trail — GAP). **TC-ID-int-010:** the holders report matches who can actually reveal;
**TC-ID-int-011:** a redacted doc no longer exposes the number/file.

---

## J. Reports & outputs

The register + readiness, the access log, the holders report, the site-doc requirements,
retention/expiry chases. **TC-ID-out-001:** the access log attributes every reveal to a
user + reason.

---

## K. Negative, edge & resilience

Reveal without a reason (refused); a branch manager without `iddoc.manage` (cannot open);
a doc past retention (auto-redacted); a legacy doc with no expiry (perpetually valid — GAP);
a site-doc-less allocation (blocked, overridable); a job with a blank site (site-specific
requirements skipped — GAP); a cert download `why` defaulting to "Not stated".

---

## L. TPIA operational suitability

Handles the DPDP reality of holding personnel IDs — masking, logged reveal, retention with
auto-redaction, and a "who holds copies" report — and gates client-site entry on the
documents a site demands. The override-with-reason for site docs matches real gate-pass
exceptions.

## M. Management usefulness

Readiness (verified/expiring/copies-held/due-redaction) and the holders report give a
privacy-and-compliance view; site-doc chases keep entry documents current. Confirm
"verified" means what it should.

## N. UI/UX

Masked numbers, one-shot reveal with reason, site-doc override box, advisory identity note.
Terminology via `T*()`.

## O. Security

**The privacy-sensitive module.** Manage gated (not admin-implied); every access logged;
retention auto-redacts; but **no encryption at rest**, plaintext numbers, base64 file bytes
in the DB, and the `/iddoc-file` download `why` is weakly required — harden these.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | **Priority** | §F least-privilege |
| 9 Scope | Partial | §F inspector-only |
| 10 Settings | Y | §G |
| 11 Workflow | **Priority** | §D site-doc gate |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I at-rest privacy |
| 14 Audit | Y | §I access log |
| 15 Outputs | Y | §J holders report |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O at-rest |
| 21 Import | N-A | — |
| 22 Notifications | Y | expiry chases |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | privacy/accreditation |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | expiry/retention |
| 28 Performance | Partial | base64 in DB |
| 29 Backup | Partial | §I |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-ID-001 | (verify — Major) | Identity numbers and file bytes are stored **plaintext/base64 in the DB with no encryption at rest** — masking is presentation-only. Encrypt at rest, and confirm the access-log table cannot silently fail (it swallows its own errors). |
| GAP-ID-002 | (verify) | `iddoc_current` treats an **empty expiry as valid**, but legacy/seeded rows without expiry then read as perpetually valid and never auto-redact. Backfill or gate on expiry. |
| GAP-ID-003 | (verify) | A job with a **blank/unknown site silently skips site-specific requirements** (only company-wide rules apply) — confirm the gate is not bypassable by omitting the site. |
| GAP-ID-004 | — | Register is inspector-only and company-wide (no office scope); non-inspector person kinds and multi-office scoping are unhandled. |

---

## R. Traceability

RTM slice: `/identity`, `/iddoc-*`, `/site-docs*`, site-doc gate × dims 1–29 → TC-ID-* →
results → DEF/GAP. **Verdict: Complete-with-defects** — encryption at rest, retention
correctness, and site-doc gate completeness are the exit conditions.
