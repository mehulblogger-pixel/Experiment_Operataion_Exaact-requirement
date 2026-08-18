# Inspection Ops — Vendors — Test & Documentation Report

> **Prompt 3 · Module MOD-VENDORS.** Read from `lib/idems.php` (`idems_vendor_profile`/
> `_ensure`/`_save`, `idems_vendor_apply_assessment`, `idems_vendor_status_from`,
> `idems_vendor_log_status`, `idems_vendor_status_events`, `idems_vendor_360`,
> `idems_vendor_performance`, `idems_vendor_requal_months`, `idems_vendor_run_reminders`,
> `idems_vendor_delivery_risk`, `ops_idems_vendors`), `lib/ops.php` (`vendors_list`,
> business_partners is_vendor). Builds on MOD-MASTERS.

| | |
|---|---|
| **Module** | Vendors (MOD-VENDORS) · Area Directory / Quality |
| **Personas** | P-QA (assess/qualify), P-COORD (maintain), P-VENDOR (portal — MOD-11), P-MASTER |
| **Risk weight** | **Medium-High** — vendor qualification status gates who work can be placed against; a stale approval is a control gap |
| **Verdict** | Complete-with-verification (confirm status derivation, requalification validity, 360 scoping) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

A vendor is a `business_partners` row with `is_vendor=1`, plus a **qualification profile**
(`vendor_profiles`): **approval status** (PROSPECT / UNDER_ASSESSMENT / APPROVED /
CONDITIONAL / SUSPENDED), last score/band, **valid-until / reassess-on** dates, and a
**status timeline** (`vendor_status_events`). Status is derived from an **issued Vendor
Assessment or Audit** (`idems_vendor_apply_assessment` → `idems_vendor_status_from`:
recommendation wins; else score ≥75 APPROVED, ≥60 CONDITIONAL, else SUSPENDED), with a
**requalification window** (`vendor_requal_months`) setting valid-until. The vendor also
has **performance** (accepted vs rejected inspections), a **360 view** (reports / NCRs /
complaints), **delivery risk** (from expediting), and the **vendor portal** (MOD-11) for
sharing reports and running the NCR response loop.

Screens: `/vendors` (register + qualification status), `/vendor-360` / vendor detail,
vendor-portal admin. Tables: `business_partners`, `vendor_profiles`, `vendor_status_events`.

---

## B. Screen-by-screen catalogue

**`/vendors`** — register scoped; qualification status pill, score/band, valid-until,
reassess-due; search/filter. **Vendor detail / 360** — identity + qualification timeline +
reports/NCRs/complaints + performance + delivery risk. **Vendor assessment** — a scored
report type that, on issue, updates the profile (MOD-06/08). **Portal admin** — invite
vendor users (MOD-11).

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-VEN-form-001 | Legal name required; display falls back to legal. |
| TC-VEN-form-002 | Tax identity for a billable vendor; contacts for portal. |
| TC-VEN-form-003 | Qualification status is **derived**, not free-typed — set by an issued assessment/audit or an authorised manual change with a reason. |
| TC-VEN-form-004 | Requalification months ≥ 0; valid-until / reassess-on computed from it. |

---

## D. Functions & logic  *(status derivation — highest scrutiny)*

- **Status from assessment** (`idems_vendor_status_from`): recommendation text
  ("not approved" → SUSPENDED, "condition" → CONDITIONAL, "approv" → APPROVED) overrides
  the score band; else band by score (≥75 / ≥60 / else). **TC-VEN-fn-001..003** — each
  path yields the right status.
- **Apply on issue** (`idems_vendor_apply_assessment`): fail-open (never blocks issuing);
  records score/band/status, sets approved-on and valid-until (for APPROVED/CONDITIONAL),
  and logs a status event. **TC-VEN-fn-004** — issuing an assessment updates the profile
  and timeline exactly once.
- **Requalification** (`idems_vendor_requal_months`, `idems_vendor_run_reminders`):
  valid-until drives a reassess-due reminder; an expired qualification should be visible.
  **TC-VEN-fn-005** — a vendor past valid-until is flagged for reassessment.
- **360 + performance + risk** (`idems_vendor_360`, `idems_vendor_performance`,
  `idems_vendor_delivery_risk`): vendor-scoped aggregation. **TC-VEN-fn-006** — 360 shows
  only this vendor's data.

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| PROSPECT → UNDER_ASSESSMENT | assessment started | — |
| → APPROVED / CONDITIONAL / SUSPENDED | assessment/audit issued | derived status; valid-until set |
| APPROVED → (reassess due) | valid-until passed | reminder; reassessment |
| any → SUSPENDED | not-approved recommendation / audit finding | logged with reason |

- **TC-VEN-life-001:** an issued VAR (audit) with Major findings raises NCRs and can move
  status to SUSPENDED/CONDITIONAL (MOD-06/12).
- **TC-VEN-life-002:** a manual status change requires authority and a reason, and is
  logged.

---

## F. Roles, permissions & data scope

Maintain: coordinator (`crm.partner.edit`/`mod.vendors.edit`). Assess/qualify: QA
(`mod.idems.edit`/assessment). Register scoped by office/SBU. Portal admin:
`mod.portal.edit`/master.

- TC-VEN-perm-001 (free-typing an approval status without an assessment/authority) →
  refused.
- TC-VEN-scope-001: only own-scope vendors in register/360.

---

## G. Settings

`vendor_requal_months`, score bands, assessment report-type schema, portal visibility,
requalification reminder cadence. **TC-VEN-set-001:** requal-months change moves the
valid-until on the next assessment.

---

## H. Cross-module integration

**IDEMS** (Vendor Assessment/Audit → qualification; assessment prefill from complaints/
inspections/NCRs — MOD-06), **NCR** (audit findings → NCR against vendor — MOD-12),
**Complaints** (vendor complaints), **Vendor portal** (share reports; NCR loop — MOD-11),
**Jobs** (sub-contracting a vendor; delivery risk), **Expediting** (delivery risk feed).
Idempotency: re-issuing an assessment must not double-log the status event.

---

## I. Data integrity & audit

`vendor_status_events` is the qualification timeline (old→new, source, score, reason,
actor, time). Status is always attributable to an assessment/audit or an authorised
manual change. Valid-until/reassess-on kept consistent with the requal window.
**TC-VEN-int-010:** the current status equals the latest event; **TC-VEN-int-011:**
performance/360 totals reconcile with the source reports/NCRs.

---

## J. Reports & outputs

The vendor register (with qualification), the Vendor Assessment/Audit report (MOD-06), the
360 view, requalification reminders, and the vendor-visible report share (MOD-11).
**TC-VEN-out-001:** the register status matches the latest assessment; **TC-VEN-out-002:**
a reassess-due vendor appears on the reminder.

---

## K. Negative, edge & resilience

A vendor with no assessment (PROSPECT/UNDER_ASSESSMENT); an expired qualification; a
not-approved recommendation overriding a high score; a re-issued assessment (no double
event); a manual status change with no reason (refused); a 360 crossing into another
vendor (blocked).

---

## L. TPIA operational suitability

Gives the vendor-qualification discipline a TPIA needs: assessments/audits drive an
auditable approval status with a validity window and reassessment, findings escalate to
NCRs, and the vendor engages through the portal. Status is evidence-derived, not a
free-typed flag.

## M. Management usefulness

Qualification mix, reassess-due list, performance and delivery risk give QA and
procurement a live vendor view; the 360 traces cause to control. Confirm reassess-due
matches valid-until.

## N. UI/UX

Register with status pills, 360 tabs, assessment-driven status (no manual guesswork),
reminders. Terminology via `T*()`.

## O. Security

Status changes authorised + evidence/reason-backed; qualification cannot be free-typed to
APPROVED without an assessment/authority; 360 scoped; portal share gated by visibility
(MOD-11).

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | **Priority** | §E qualification |
| 6 Validation | Y | §C derived status |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §D assessment→status |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I timeline |
| 14 Audit | Y | §I status events |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Y | partner import |
| 22 Notifications | Y | reassess reminders |
| 23 Offline | N-A | — |
| 24 AI | Partial | assessment scorecard |
| 25 Licensing | Y | vendor platform |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | valid-until/reassess |
| 28 Performance | Y | §D vendor performance |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-verification.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-VEN-001 | (verify) | Confirm qualification **status is only set by an issued assessment/audit or an authorised, reason-logged manual change** — never free-typed to APPROVED via a crafted POST. |
| GAP-VEN-002 | (verify) | Confirm **requalification validity**: valid-until/reassess-on are computed from the requal window and an expired qualification is surfaced (and, where intended, gates fresh work placement). |
| GAP-VEN-003 | (verify) | Confirm re-issuing an assessment does not double-log the status timeline, and 360/performance reconcile with source records. |

---

## R. Traceability

RTM slice: `/vendors`, vendor-360/detail, assessment issue × dims 1–29 → TC-VEN-* →
results → DEF/GAP. **Verdict: Complete-with-verification** — evidence-derived status,
requalification validity, and 360 scoping are the exit conditions.
