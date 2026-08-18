# Inspection Ops — Hold / Witness Points — Test & Documentation Report

> **Prompt 3 · Module MOD-HWP.** Read from `lib/hwpoints.php` (`hwp_create`, `hwp_close`,
> `hwp_derive_from_doc`, `hwp_open_count`, `hwp_for_job`, `hwp_open_all`, `ops_hwpoints`,
> `HW_POINT_TYPES`/`HW_POINT_STATUS`/`HW_DISPOSITION_MAP`), integration in `lib/idems.php`
> (`hwp_derive_from_doc` on issue; `idems_rn_blockers` uses `hwp_open_count`). Views
> `hwpoints.php`, `job_detail.php` (#holdpoints).

| | |
|---|---|
| **Module** | Hold / Witness Points (MOD-HWP) · Area Quality/Operations |
| **Personas** | P-INSP/P-COORD (raise/close), P-BM (register), P-MASTER |
| **Risk weight** | **High** — an open hold/witness point must stop a release; it is an ITP control on dispatch |
| **Verdict** | Complete-with-defects (confirm RN gating, derivation fidelity, master bypass) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. RN gating on open points (MOD-08) verified. |

---

## A. Module overview

Hold / Witness / Review / Clearance points (`hw_points`) are the ITP checkpoints that must
be satisfied before dispatch. They are **derived automatically** from an issued report's
activity tables (`hwp_derive_from_doc` scans table fields, classifies columns, and matches
a cell to `HW_DISPOSITION_MAP` — held / witnessed / reviewed / deviation / client-clearance
→ HOLD / WITNESS / REVIEW / CLEARANCE), idempotently (`dedupe_key`), and can be **raised
manually**. Each is OPEN → CLEARED / WAIVED / CANCELLED. The count of OPEN points on a job
(`hwp_open_count`) is a **Release-Note blocker** (MOD-08), enforced server-side.

Screens: `/hold-points` (manager register, office-scoped), `/hw-point-new/-derive/-clear/
-waive/-cancel/-reopen`, and the per-job panel in `job_detail.php` (#holdpoints).
Tables: `hw_points`.

---

## B. Screen-by-screen catalogue

**`/hold-points`** — office-scoped open register across jobs. **Per-job panel** — the job's
points, raise manual, re-scan issued reports (`hw-point-derive`), clear/waive/cancel/reopen.
Point carries type, QAP clause/sub-clause, activity, description, source (AUTO/MANUAL), IRN,
raised/cleared by+at, clearance ref, remark.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-HWP-form-001 | Manual raise requires a QAP clause **or** a description. |
| TC-HWP-form-002 | Type coerced to a known kind (else HOLD); source AUTO/MANUAL only. |
| TC-HWP-form-003 | **Waive requires a reason**; close status ∈ CLEARED/WAIVED/CANCELLED. |
| TC-HWP-form-004 | Derived point is idempotent — a re-scan creates no duplicate (`dedupe_key`). |

---

## D. Functions & logic  *(derivation + gating — highest scrutiny)*

- **Derivation** (`hwp_derive_from_doc`): decodes report `data`, iterates `table` fields,
  classifies columns (clause/sub-clause/activity/observation), matches a cell to a
  disposition → an OPEN point; dedup by `md5(docId|fkey|rowIndex|type|clause)`. Runs on
  **report issue** (never blocks issue). **TC-HWP-fn-001** — a report with a held/witness
  disposition raises the right points; **TC-HWP-fn-002** — re-issue/re-scan does not
  duplicate.
- **RN gate** (`hwp_open_count` → `idems_rn_blockers`): any OPEN point blocks the Release
  Note; enforced server-side in the RN handler (MOD-08). **TC-HWP-fn-003** — a crafted RN
  POST with an open point is refused (non-master).
- **Close** (`hwp_close`): CLEARED (satisfied) / WAIVED (reason) / CANCELLED; reopen
  returns to OPEN. **TC-HWP-fn-004.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| (report issued) → OPEN (AUTO) | derivation | disposition match |
| (manual) → OPEN | raise | clause or description |
| OPEN → CLEARED/WAIVED/CANCELLED | close | waive needs reason |
| CLEARED/etc → OPEN | reopen | edit permission |

- **TC-HWP-life-001:** an open point blocks the Release Note until cleared/waived/cancelled.
- **TC-HWP-life-002:** WAIVED/CANCELLED do not count toward `hwp_open_count`.

---

## F. Roles, permissions & data scope

View: `mod.idems.view`/`ops.job.view`/master. Edit (raise/close/derive/reopen):
`mod.idems.edit`/`ops.job.edit`/master. Register office-scoped (`scope_offices`).

- TC-HWP-perm-001 (mutating route without edit) → refused.
- TC-HWP-scope-001: register shows only own-office points.

---

## G. Settings

No HWP-specific settings; related RN setting `rn_require_client_acceptance`. Disposition
vocabulary is label/heuristic (`HW_DISPOSITION_MAP`). **TC-HWP-set-001:** localized
dispositions must still map (see GAP-HWP-002).

---

## H. Cross-module integration

**IDEMS** (report issue → derivation; report field `current_holdpoints` is descriptive,
separate from tracked points), **Release Notes** (open points block RN — MOD-08), **Jobs**
(per-job register; links back to `report_doc_id`/IRN), **Audit** (`idems_log('hw_point')`).
Idempotency: re-scan safe via dedupe.

---

## I. Data integrity & audit

Every raise/clear/waive/cancel/reopen logged to `idems_log`. Dedup ties a point to
`docId|fkey|rowIndex` — reordering report table rows could orphan/duplicate on re-scan
(GAP). A revised/re-issued report does not auto-close prior AUTO points (GAP-HWP-003).
**TC-HWP-int-010:** `hwp_open_count` matches the OPEN rows for the job.

---

## J. Reports & outputs

The open register (manager), the per-job panel, and the RN blocker line. No document of its
own. **TC-HWP-out-001:** the RN screen lists open points as a blocker with the count.

---

## K. Negative, edge & resilience

A report with a held disposition (raises a point); a re-scan (no duplicate); a waive with no
reason (refused); a crafted RN with an open point (refused, non-master); a master bypassing
the RN gate (allowed by design — GAP-HWP-001); a localized disposition label that does not
match (no point — GAP-HWP-002); a revised report leaving stale AUTO points.

---

## L. TPIA operational suitability

Directly models the ITP hold/witness discipline: checkpoints derived from the inspection
record, tracked to closure, and gating dispatch via the Release Note. Manual points cover
what the schema can't infer; the clearance-ref and remark give an auditable close.

## M. Management usefulness

A branch-wide open-points register surfaces what is blocking dispatch; per-job visibility
ties it to the work. Confirm the open count matches reality.

## N. UI/UX

Points on the job's Reports & QA tab; one-click clear/waive/cancel; re-scan button.
Terminology via `T*()`.

## O. Security

Raise/close authorised server-side; RN gate enforced on the RN POST; register scoped;
a UI inconsistency shows a Reopen button to non-editors but the route still refuses (GAP).

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
| 10 Settings | Partial | §G heuristic map |
| 11 Workflow | **Priority** | §D RN gate |
| 12 Integration | Y | §H |
| 13 Data integrity | Y | §I dedupe |
| 14 Audit | Y | §I |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Partial | §O reopen button |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | — |
| 22 Notifications | N-A | — |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | IDEMS/accreditation pack |
| 26 Terminology | Y | — |
| 27 Time/FY | N-A | — |
| 28 Performance | Partial | register at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-HWP-001 | (verify) | A **master bypasses all RN blockers** including open hold points — confirm this is intended and is at least audit-visible; consider requiring a reason. |
| GAP-HWP-002 | (verify) | Derivation is **label/heuristic-driven** (English disposition strings) — a localized/renamed disposition silently raises no point. Confirm coverage or make the map configurable. |
| GAP-HWP-003 | (verify) | A **revised/re-issued report does not reconcile prior AUTO points** — confirm stale points from a superseded revision are closed or re-derived. |

---

## R. Traceability

RTM slice: `/hold-points`, `/hw-point-*`, job panel × dims 1–29 → TC-HWP-* → results →
DEF/GAP. **Verdict: Complete-with-defects** — RN-gate enforcement, derivation fidelity, and
revision reconciliation are the exit conditions.
