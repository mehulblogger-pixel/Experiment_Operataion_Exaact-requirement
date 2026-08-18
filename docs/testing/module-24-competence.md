# Inspection Ops — Competence & Authorisation — Test & Documentation Report

> **Prompt 3 · Module MOD-COMPETENCE.** Read from `lib/competence.php` (`competence_lapsed`/
> `competence_block`, `competence_job_date`, `competence_can_override`, `auth_enforced`/
> `auth_covers`/`auth_block`, `auth_grant`, `auth_run_maintenance`, `witness_*`,
> `report_signatory_warning`, `ops_competence`), gate wiring `lib/packs.php`
> (`work.assign`), issue side `lib/idems.php` (`document.issue` signatory→NCR). Views
> `competence.php`, `job_form.php` (lapsed pills + override box).

| | |
|---|---|
| **Module** | Competence & Authorisation (MOD-COMPETENCE) · Area Quality/HR |
| **Personas** | P-QA/P-ADMIN (grant/authorise), P-COORD (allocate), P-MANAGER (override), P-MASTER |
| **Risk weight** | **High** — ISO/IEC 17020 §6.1; putting an unqualified/unauthorised inspector on work is the control this prevents |
| **Verdict** | Complete-with-defects (confirm the allocation gate, authorisation scope match, threshold consistency) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Two overlapping personnel controls at **job allocation**: (1) a **required-certificate lapse
gate** (`competence_block`) — a mandatory cert lapsed on the work date blocks the allocation,
**overridable** by a manager with a recorded reason (`cert_override_note`/`_by`); and (2) an
opt-in **authorisation matrix** (`auth_covers`/`auth_block`) — scope-based (ANY /
INSPECTION_TYPE / ACTIVITY / CLIENT), **not overridable** at allocation. Both fire through
the `work.assign` pack hook; no accreditation pack ⇒ no gate. A **witnessed assessment**
programme monitors ongoing competence (a failing witness suspends the person's
authorisations). At **report issue**, an unauthorised signatory is a **warning + auto-NCR**
(never a block) — the deliberate counterpart to the calibration hard-block.

Screens: `/competence` (matrix/register), `/auth-enforce`, `/auth-add`, `/auth-status`,
`/witness-add`; allocation pills + override box on `job_form`. Tables: `inspector_certs`,
`authorisations`, `qualifications`, `witness_assessments`.

---

## B. Screen-by-screen catalogue

**`/competence`** — the matrix: people, certs (valid/expiring/expired), authorisations
(scope, validity, status), witness cadence, readiness. Actions: toggle enforcement, grant/
suspend/expire/withdraw an authorisation (reason required), record a witnessed assessment.
**Allocation form** — per-inspector lapsed pills + a manager override box when a gate fires.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-CMP-form-001 | Scope kind ∈ AUTH_SCOPES (else ANY); ANY forces empty scope value. |
| TC-CMP-form-002 | Status change to non-ACTIVE **requires a reason**. |
| TC-CMP-form-003 | Witness scores 1–5; outcome ∈ WITNESS_OUTCOME. |
| TC-CMP-form-004 | Enforcement cannot be turned ON when nobody is authorised (refused). |

---

## D. Functions & logic  *(the gate — highest scrutiny)*

- **Cert gate** (`competence_block`): a mandatory cert lapsed on `competence_job_date`
  blocks allocation; a manager may override with a recorded reason (`competence_can_override`
  = `jobs.edit` + admin/master). **TC-CMP-fn-001** (lapsed mandatory cert blocks),
  **TC-CMP-fn-002** (override records who/why; a stale note for a non-firing gate is cleared).
- **Authorisation gate** (`auth_block`, when `auth_enforced`): not authorised for the work
  type/activity/client on the date blocks allocation, **no override**. **TC-CMP-fn-003** —
  an unauthorised-scope allocation is refused and cannot be overridden.
- **Scope match** (`auth_covers`): INSPECTION_TYPE is **exact string equality** on the work
  type — a label/casing drift silently fails coverage (GAP-CMP-002). **TC-CMP-fn-004.**
- **Witness maintenance** (`auth_run_maintenance`): expires run-out auths, suspends auths on
  a lapsed cert; a failed witness suspends **all** the person's authorisations (GAP).
  **TC-CMP-fn-005.**
- **Issue signatory** (`report_signatory_warning` via `document.issue`): unauthorised
  signatory = warn + `ncr_create` (clause 6.1, MAJOR), never a block. **TC-CMP-fn-006.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| authorisation → ACTIVE | grant | scope + validity |
| ACTIVE → EXPIRED | past valid_to (cron) | system, reason recorded |
| ACTIVE → SUSPENDED | underlying cert lapsed / failed witness | system/witness |
| any → WITHDRAWN | manual | reason required |
| allocation | job save | cert gate (overridable) + auth gate (not) |

- **TC-CMP-life-001:** a failed witness assessment suspends the person's authorisations.
- **TC-CMP-life-002:** an open-ended authorisation (blank valid_from/to) reads as always
  live (GAP-CMP-003).

---

## F. Roles, permissions & data scope

View: `mod.users.view`/admin/master-of-users. Grant/authorise/enforce/witness
(`competence_can_authorise`): admin/master. Override at allocation
(`competence_can_override`): `jobs.edit` + admin/master. **No per-office scoping** — the
matrix lists all active inspectors company-wide (GAP).

- TC-CMP-perm-001 (grant without authority) → refused.
- TC-CMP-perm-002 (override without `jobs.edit`+admin) → refused.

---

## G. Settings

`authorisation_enforce` (0/1, global). Levels/scopes/bases/witness criteria are PHP consts
(intended to be master-editable). Cert "expiring" thresholds are **inconsistent** — 45 days
in `inspector_cert_state`, 30 in the matrix (GAP-CMP-001). **TC-CMP-set-001:** enforcement
on ⇒ the auth gate blocks; off ⇒ only the cert gate applies.

---

## H. Cross-module integration

**Jobs allocation** (both gates via `work.assign` — MOD-05), **IDEMS issue** (signatory →
NCR — MOD-06/12), **Recruitment** (cert/qualification data feeds fit-score — MOD-35),
**Impartiality/Identity** (sibling `work.assign` gates — MOD-25/26). Idempotency: the gate
is advisory-pre-checked in TOSRM too.

---

## I. Data integrity & audit

Authorisation status changes recorded with actor + reason (system for cron transitions).
Cert gate wraps errors returning `[]` pre-migration → **fails open** (gate silently
disabled) (GAP). `auth_covers` treats blank validity as always-live (GAP). **TC-CMP-int-010:**
a suspended authorisation blocks re-allocation; **TC-CMP-int-011:** a person blocked from
allocation but later reassigned as signatory produces only an NCR, not a block (design
seam — GAP-CMP-004).

---

## J. Reports & outputs

The competence matrix, readiness/due buckets, expiring-cert reminders (cron), and the
allocation pills. **TC-CMP-out-001:** the matrix shows expired/expiring correctly (with a
single threshold); **TC-CMP-out-002:** a lapsed-cert allocation surfaces the reason.

---

## K. Negative, edge & resilience

Allocate with a lapsed mandatory cert (block, overridable); allocate unauthorised (block,
not overridable); a non-mandatory required cert (never gates — data-entry risk); an
open-ended authorisation (never expires); a label drift on INSPECTION_TYPE (coverage fails);
a failed witness (all auths suspended); a pre-migration DB error (gate fails open).

---

## L. TPIA operational suitability

Implements §6.1: certificated competence and scoped authorisation checked at the point work
is placed, a monitored witnessed-assessment cadence, and a signatory-authorisation control
at issue. The overridable-cert / non-overridable-authorisation split matches how a body
distinguishes a lapsed ticket from working outside one's scope.

## M. Management usefulness

Readiness (expired/review/witness/no-basis) and the matrix give QA a live competence picture;
reminders keep certs current. Confirm the thresholds are consistent and the gate does not
fail open.

## N. UI/UX

Lapsed pills on the allocation form, override box only when a gate fires, matrix view.
Terminology via `T*()`.

## O. Security

Grant/authorise/override gated server-side; the auth gate is non-overridable; the cert gate
override is authority + reason logged; **matrix not office-scoped (fix)**; gate must not
fail open on a DB error.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | **Priority** | §E auth lifecycle |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | **Gap** | §F no office scope |
| 10 Settings | Partial | §G threshold gap |
| 11 Workflow | **Priority** | §D allocation gate |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I fail-open |
| 14 Audit | Y | §I |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L §6.1 |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O |
| 21 Import | N-A | — |
| 22 Notifications | Y | cert reminders |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | accreditation pack |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | validity dates |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-CMP-001 | (verify) | **Inconsistent "expiring" thresholds** (45 vs 30 days across `inspector_cert_state`/matrix) — pick a single source of truth. |
| GAP-CMP-002 | (verify — Major) | `auth_covers` INSPECTION_TYPE match is **exact string equality** — a label/casing drift between the call master and the stored scope silently fails coverage (a person reads as unauthorised, or the block is skipped). Normalise. |
| GAP-CMP-003 | (verify) | An authorisation with **blank valid_from/valid_to reads as always-live** and never expires — require explicit validity. |
| GAP-CMP-004 | (verify) | The **cert gate fails open** on a pre-migration/DB error (returns `[]`), silently disabling the control; and a person blocked at allocation can still be a report signatory (warn+NCR only). Confirm both are intended and monitored. |

---

## R. Traceability

RTM slice: `/competence`, `/auth-*`, `/witness-add`, allocation gate, issue signatory ×
dims 1–29 → TC-CMP-* → results → DEF/GAP. **Verdict: Complete-with-defects** — allocation
gate enforcement, scope-match normalisation, and fail-closed behaviour are the exit
conditions.
