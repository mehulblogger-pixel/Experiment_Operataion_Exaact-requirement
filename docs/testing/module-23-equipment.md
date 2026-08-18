# Inspection Ops — Equipment & Calibration — Test & Documentation Report

> **Prompt 3 · Module MOD-EQUIP.** Read from `lib/equipment.php` (`equipment_all`,
> `equipment_calibration_on`, `equipment_block`, `report_equipment_add`,
> `report_equipment_block`, `report_equipment_date`, `equipment_run_cal_reminders`,
> `ops_equipment`), integration `lib/packs.php` (`document.issue` hard block),
> `lib/idems.php` (`idems_equipment_active`, finalize gate). Views `equipment_list.php`,
> `equipment_form.php`, `idems/doc_detail.php` (#equipment).

| | |
|---|---|
| **Module** | Equipment & Calibration (MOD-EQUIP) · Area Quality |
| **Personas** | P-QA/P-LAB (register/certs), P-INSP (name instrument on report), P-MASTER |
| **Risk weight** | **High** — a report from an out-of-calibration instrument is invalid; the issue block is **non-overridable** |
| **Verdict** | Complete-with-defects (confirm the hard block, date-in-force logic, cert-download auth) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. Calibration hard-block on issue verified as non-overridable. |

---

## A. Module overview

The equipment register (`equipment` + `equipment_calibrations` + `report_equipment`) tracks
measuring/test instruments and their **calibration certificate history** (never overwritten).
The evidence engine is **point-in-time**: `equipment_calibration_on(id, date)` returns the
cert **in force on the work date** (PASS, issued ≤ date ≤ valid_to), and `report_equipment_add`
**freezes** the relied-upon `calibration_id` so later recalibration cannot rewrite history.
The control that matters: `report_equipment_block` is a **hard, non-overridable block** on
**issuing** a report that names an instrument out of calibration on the report date — fired
through `pack_fire('document.issue')`; not even a master QA override bypasses it. When the
accreditation pack is off, the hook is empty so non-accredited tenants issue freely.

Screens: `/equipment`, `/equip-new/-edit`, `/equip-cal-add/-del`, `/equip-cert` (download),
`/report-equip-add/-del`, doc panel #equipment. Tables: `equipment`,
`equipment_calibrations`, `report_equipment`.

---

## B. Screen-by-screen catalogue

**`/equipment`** — office-scoped register; status (ACTIVE/CALIBRATION/REPAIR/QUARANTINE/
RETIRED), calibration validity, days-left banner. **`/equip-*`** — instrument identity
(code, kind, make/model/serial, range, accuracy, office, owner, interval) + certificate
history (cert no, cal date, valid-to, body, traceable-to, PASS/FAIL, file). **Report panel** —
name instruments used on a report (each flagged "(not valid on this date)"); remove.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-EQ-form-001 | Identification `code` required; interval clamped ≥ 0. |
| TC-EQ-form-002 | Cert: valid-to auto-computed from cal date + interval when blank; file MIME-gated. |
| TC-EQ-form-003 | Result normalised to PASS unless explicitly FAIL; a FAIL cert never counts as valid. |
| TC-EQ-form-004 | Duplicate instrument on one report refused. |

---

## D. Functions & logic  *(the hard block — highest scrutiny)*

- **In-force cert** (`equipment_calibration_on`): the cert live on the work date, not the
  newest; FAIL skipped. **TC-EQ-fn-001.**
- **Frozen evidence** (`report_equipment_add`): stamps `calibration_id` at link time so a
  later recalibration cannot rewrite what the report relied on. **TC-EQ-fn-002.**
- **Hard block on issue** (`report_equipment_block` via `document.issue`): any named
  instrument out of calibration on `report_equipment_date` (inspection_date → issue_date →
  job dates → today) blocks finalize, **non-overridable** (no override key, unlike
  competence/site-doc). **TC-EQ-fn-003** — a crafted finalize with a lapsed instrument is
  refused even for a master; **TC-EQ-fn-004** — the QA-critical master override is a
  separate gate and does not bypass this one.
- **Reminders** (`equipment_run_cal_reminders`, cron): ≤30-day/overdue emails. **TC-EQ-fn-005.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| ACTIVE ↔ CALIBRATION/REPAIR/QUARANTINE/RETIRED | status change | unusable set never usable |
| cert valid → due → lapsed | time | PASS + valid_to |
| instrument named on report | report-equip-add | freezes calibration_id |
| report issue | finalize | **hard block if any instrument lapsed on the date** |

- **TC-EQ-life-001:** a QUARANTINE/REPAIR/RETIRED instrument is never usable regardless of
  calibration.
- **TC-EQ-life-002:** the calibration block cannot be overridden by any role.

---

## F. Roles, permissions & data scope

View register / name on report: `mod.equipment.view`/master. Manage register+certs
(`equipment_can_manage`): `master.manage`/admin/master. Register office-scoped
(`scope_clause('e.office_id')`). **Note:** the register list is readable without
`mod.equipment.view`; `/equip-cert` download has only ambient route auth (GAP-EQ-002).

- TC-EQ-perm-001 (manage without permission) → refused.
- TC-EQ-scope-001: office-scoped register.

---

## G. Settings

Per-instrument `cal_interval_months` (12), `owned_by` (OWN/HIRED/CLIENT/VENDOR), env
`QAC_EMAIL` (reminder recipient). Whole feature pack-gated (`accredited_pack_on`).
**TC-EQ-set-001:** interval drives auto valid-to; **TC-EQ-set-002:** pack off ⇒ no issue
gate (non-accredited tenants unaffected).

---

## H. Cross-module integration

**IDEMS** (name instruments on a report; the `equip:` master-linked field autofills from
`idems_equipment_active`; issue hard block — MOD-06/08), **NCR** (`nonconformities.
equipment_id` links; but the block itself does not auto-raise an NCR — the adjacent
signatory rule does). Idempotency: cert history is append-only.

---

## I. Data integrity & audit

Cert history is never overwritten; the report freezes the cert id it relied on; the block
is date-in-force, not newest-cert. `idems_equipment_active` autofill uses the **newest**
cert (display) which can differ from the date-aware one that validates (GAP-EQ-003).
**TC-EQ-int-010:** a recalibration after issue does not change what the report shows;
**TC-EQ-int-011:** `report_equipment_date` uses the real work date (falls back to today if
absent — GAP-EQ-001).

---

## J. Reports & outputs

The register + days-left banner, the certificate download, the report's "Measuring & test
equipment used" panel, and reminder emails. **TC-EQ-out-001:** the report lists the
instruments with the certs relied upon.

---

## K. Negative, edge & resilience

Issue a report with a lapsed instrument (hard block, any role); a FAIL cert (never valid);
a QUARANTINE instrument (unusable); an instrument with no dated inspection (falls back to
today — could pass a truly-lapsed one); cert download without `mod.equipment.view`; the
autofill showing a newer cert than the one validating.

---

## L. TPIA operational suitability

Directly serves ISO/IEC 17020 §6.2 / 17025 §6.4-6.5: point-in-time calibration evidence,
append-only cert history, frozen evidence per report, and a non-overridable issue block —
exactly the assurance an accreditation body expects, and cleanly off for non-accredited
tenants.

## M. Management usefulness

Calibration-due banner and reminders keep instruments in date; the register shows fleet
status. Confirm the block fires on the true work date.

## N. UI/UX

Instrument picker on the report flags out-of-date instruments; days-left banner; cert
history. Terminology via `T*()`.

## O. Security

Manage/cert actions gated server-side; the calibration block is non-overridable; **register
readable and cert download under-scoped (fix)**; feature pack-gated.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Partial | §F register read |
| 9 Scope | Partial | §F cert download |
| 10 Settings | Y | §G |
| 11 Workflow | **Priority** | §D hard block |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I frozen evidence |
| 14 Audit | Y | §I append-only |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O download auth |
| 21 Import | N-A | — |
| 22 Notifications | Y | cal reminders |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | accreditation pack |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | date-in-force |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-EQ-001 | (verify) | `report_equipment_date` **falls back to today** when no date is recorded — an instrument lapsed on the true (unrecorded) inspection date could pass. Require a work date before the block runs. |
| GAP-EQ-002 | (verify — Major) | The **register list is readable without `mod.equipment.view`** and `/equip-cert` download has only ambient route auth — add the view permission + office scope to both. |
| GAP-EQ-003 | (verify) | The IDEMS autofill (`idems_equipment_active`) shows the **newest** cert and does not exclude QUARANTINE/REPAIR, so an unusable/differently-validated instrument can appear in the picker (the hard block still catches it at issue). Align the picker with the date-aware validity. |

---

## R. Traceability

RTM slice: `/equipment`, `/equip-*`, `/report-equip-*`, issue gate × dims 1–29 → TC-EQ-* →
results → DEF/GAP. **Verdict: Complete-with-defects** — the non-overridable issue block,
work-date correctness, and cert-download authorisation are the exit conditions.
