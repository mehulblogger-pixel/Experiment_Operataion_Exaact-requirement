# Inspection Ops — Attendance & Reconcile — Test & Documentation Report

> **Prompt 3 · Module MOD-RECONCILE.** Read from `lib/attend.php` (`attend_punch`,
> `attend_row`, `attend_avail_map`, `attend_render_widget`, `ops_attend_action`),
> `lib/trust.php` (`site_checkin`, `site_visit_close_missing`/`_block`,
> `can_close_attendance_missing`, `punch_state`), `lib/ops.php` (`ops_check_compoff`,
> `ops_invoicing` credit bucket, `credit-recon`), `lib/pdso.php` (deputation timesheet/
> approval). Views `dashboard.php` (widget), `attendance_recon.php`.

| | |
|---|---|
| **Module** | Attendance & Reconcile (MOD-RECONCILE) · Area Operations/Money |
| **Personas** | P-INSP (self-mark, site check-in), P-COORD/P-MANAGER (override close), P-ACCTS (credit recon), P-CLIENT (deputation approval) |
| **Risk weight** | **Medium-High** — site check-in gates job close; inter-office credit reconciliation moves money between branches |
| **Verdict** | Complete-with-defects (confirm site-gate default, self-mark spoofing, credit matching) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Four related things: (1) **self-attendance** (`attendance` SELF rows + `inspector_day_status`)
— an inspector marks IN/OUT with browser GPS (OFFICE/SITE require location), mirrored to the
availability board; (2) **site check-in** (`site_visits`, `site_checkin`) — GPS + geofence +
photo + clock-skew evidence, whose arrival/departure completeness **gates job close** (§WO-9,
manager override + reason); (3) **comp-off** (`ops_check_compoff`) — a Sunday inspection date
grants a comp-off; and (4) **inter-office credit reconciliation** — a cross-office job carries
`expected_credit` (RECEIVED/GIVEN); the money-desk "credit" bucket lists closed non-GIVEN jobs
awaiting credit, and monthly actuals are recorded in `credit_recon`.

Screens: `/attend-mark` (widget), `/availability`, `/attendance-recon`, `/invoicing` (credit
bucket), `credit-recon` datatable; deputation `/dep-timesheet/-approval`. Tables: `attendance`,
`inspector_day_status`, `site_visits`, `credit_recon`.

---

## B. Screen-by-screen catalogue

**Dashboard widget** — Mark IN / Mark OUT with geolocation. **`/availability`** — day-status
board. **Site check-in** — arrival/departure with GPS/photo (job). **`/attendance-recon`** —
voucher-derived attendance vs HR CSV (OK/MISMATCH/in-app-only/in-HR-only). **`/invoicing`
credit bucket** + **`credit-recon`** — inter-office credit tracking.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-REC-form-001 | Punch kind IN/OUT; status a valid self-status; **location mandatory for OFFICE/SITE** on both IN and OUT; OUT needs a prior IN; CSRF checked. |
| TC-REC-form-002 | Site check-in: GPS mandatory, geofence enforced when set, photo when configured, clock-skew flagged. |
| TC-REC-form-003 | Cross-office job requires `expected_credit > 0` at creation. |
| TC-REC-form-004 | `credit_recon`: month required; direction RECEIVED/GIVEN. |

---

## D. Functions & logic  *(site gate + credit — highest scrutiny)*

- **Self-punch** (`attend_punch`): writes the SELF row + one `inspector_day_status`
  (SITE→ON_JOB), logged CHECK_IN/OUT. **Location is self-reported browser GPS with no
  geofence/accuracy validation on the self-mark path** — trivially spoofable, unlike
  `site_checkin` (GAP-REC-002). **TC-REC-fn-001.**
- **Site close gate** (`site_visit_close_missing` at job-close §WO-9): attendance "expected"
  if `checkin_entry_exit_required` OR a site geofence OR any check-in exists; missing arrival/
  departure blocks close unless a manager overrides with a reason (dents the rating). **Off by
  default** (`checkin_entry_exit_required`=0, no geofence) — for many installs the requirement
  never fires (GAP-REC-001). **TC-REC-fn-002.**
- **Comp-off** (`ops_check_compoff`): a Sunday inspection date grants a comp-off (idempotent
  per inspector+date); 30-day expiry stored but not enforced (GAP). **TC-REC-fn-003.**
- **Credit reconciliation**: `credit_recon` actuals keyed by month/office/client/boss, **not
  linked to specific jobs**; the finance rollup sums all rows ignoring date scope — manual,
  not auto-matched expected→received (GAP-REC-003); `credit_received` is a manual flag.
  **TC-REC-fn-004.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| day → IN → OUT | punch | location for OFFICE/SITE; OUT needs IN |
| site visit → ENTRY/EXIT | check-in | GPS + geofence + photo |
| job close | close | arrival+departure (if expected) or manager override |
| Sunday work → comp-off | job-close | idempotent per date |
| credit expected → received | manual flag / recon | monthly actuals |

- **TC-REC-life-001:** a job with a missing site check-in (when required) cannot close without
  a manager override + reason.
- **TC-REC-life-002:** a Sunday inspection date grants exactly one comp-off.

---

## F. Roles, permissions & data scope

`/attend-mark`: any authenticated user with a linked inspector, own day only (ungated route,
handler self-checks). Availability: `can_manage_availability`. Invoicing/credit: `data.credit`/
`finance.reconcile`. Close override: op/branch/reporting manager or master. `credit-recon`
datatable: admin. Office+SBU scoping throughout invoicing/finance.

- TC-REC-perm-001: an inspector marks only their own day.
- TC-REC-perm-002 (credit columns without `data.credit`) → hidden.

---

## G. Settings

`checkin_photo_required` (0), `checkin_entry_exit_required` (0), per-job `site_geofence_m`,
lookup maps (`attendance_self`, `avail_status`), credit types/directions. **TC-REC-set-001:**
turning `checkin_entry_exit_required` on makes the close gate demand arrival/departure.

---

## H. Cross-module integration

**Jobs close** (site check-in gate §WO-9; comp-off — MOD-05), **Deputation** (timesheet →
client attendance approval → billing — MOD-05/10), **Invoicing** (credit bucket; `credit_
received`; job-bill — MOD-09), **Vouchers** (recon from voucher day-types — MOD-30),
**Availability** (self-mark → board). Idempotency: comp-off per date.

---

## I. Data integrity & audit

`idems_log` CHECK_IN/OUT; site visits carry GPS/geofence/skew flags. Self-mark GPS is
un-validated (spoofable); credit recon is loosely coupled (not job-matched); comp-off expiry
not enforced; two `inspector_day_status` CREATE statements risk schema drift. **TC-REC-int-010:**
site check-in evidence is tamper-flagged; **TC-REC-int-011:** credit received is not verified
against the receiving office (no double-entry — GAP).

---

## J. Reports & outputs

The attendance-recon report, the availability board, the site-visit window, the credit
bucket + `credit_recon` totals. **TC-REC-out-001:** recon flags OK/MISMATCH correctly against
the HR CSV.

---

## K. Negative, edge & resilience

A spoofed self-mark GPS (accepted — no validation); a site-less/geofence-off install (close
gate never fires); an OUT with no IN (refused); a Sunday job (comp-off); ad-hoc Sunday work
not tied to a job date (no comp-off); a credit received flag with no counter-office check;
an HR-vs-app mismatch (advisory).

---

## L. TPIA operational suitability

Covers the field-attendance and inter-office-credit realities: evidenced site check-in that
gates close, comp-off for Sunday work, a self-mark for daily status, and a monthly credit
reconciliation between contracting and executing branches. The opt-in site gate and the
manual credit matching are the areas to tighten.

## M. Management usefulness

The recon flags attendance discrepancies; the credit bucket and `credit_recon` surface
inter-office balances; the availability board shows who is free. Confirm credit reconciles
per job, not just in bulk.

## N. UI/UX

One-tap mark IN/OUT with GPS, availability board, recon report. Terminology via `T*()`.

## O. Security

Self-mark own-day + CSRF; site check-in evidenced + skew-flagged; **self-mark GPS spoofable,
credit received unverified across offices** — tighten; office-scoped finance.

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
| 11 Workflow | **Priority** | §D close gate |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I self-mark/credit |
| 14 Audit | Y | §I check-in log |
| 15 Outputs | Y | §J recon |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O GPS spoof |
| 21 Import | Y | HR CSV |
| 22 Notifications | Partial | — |
| 23 Offline | Partial | self-mark |
| 24 AI | Partial | deputation advisory |
| 25 Licensing | Y | — |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | month recon |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-REC-001 | (verify) | The **site check-in close gate is off by default** (`checkin_entry_exit_required`=0, no geofence) — the "someone was on site" guarantee is opt-in. Confirm the intended default per tenant. |
| GAP-REC-002 | (verify — Major) | **Self-attendance GPS is un-validated** (no geofence/accuracy/skew checks on the self-mark path, unlike `site_checkin`) — coordinates are trivially spoofable. Apply the same evidence checks or mark self-mark as low-assurance. |
| GAP-REC-003 | (verify) | **Inter-office credit reconciliation is manual and not job-matched** — `credit_recon` sums all rows ignoring date scope, and `credit_received` is an unverified flag with no double-entry between the two offices. Match expected→received per job. |
| GAP-REC-004 | — | Comp-off 30-day expiry is stored but not enforced; two `inspector_day_status` CREATE statements risk schema drift. |

---

## R. Traceability

RTM slice: `/attend-mark`, `/availability`, site check-in, `/attendance-recon`, `credit-recon`
× dims 1–29 → TC-REC-* → results → DEF/GAP. **Verdict: Complete-with-defects** — the site-gate
default, self-mark assurance, and per-job credit matching are the exit conditions.
