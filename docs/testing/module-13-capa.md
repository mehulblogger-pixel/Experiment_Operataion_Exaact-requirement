# Inspection Ops — Corrective Action (CAPA) — Test & Documentation Report

> **Prompt 3 · Module MOD-CAPA.** Read from `lib/capa.php` (`ops_capa`, `capa_create`,
> `capa_from_complaint`, `ncr_to_capa` link, `capa_close_missing`/`capa_close_block`,
> `capa_actions`/`capa_action_add`/`capa_action_done`/`capa_action_cancel`/
> `capa_action_reopen`, `capa_action_overdue`/`capa_verify_overdue`, `capa_readiness`,
> `capa_run_reminders`, `capa_due_days`/`capa_verify_days`, `CAPA_STATUS`/`CAPA_SEVERITY`/
> `CAPA_RC_METHODS`), views `capa/register.php`, `capa/item.php`, `capa/new.php`.

| | |
|---|---|
| **Module** | Corrective Action / CAPA (MOD-CAPA) · Area Quality |
| **Personas** | P-QA/P-QM (raise/act/close), P-OWNER (action owner), P-MASTER |
| **Risk weight** | **High** — ISO/IEC 17020 §8.7 effectiveness control; a CAPA closed without verification is a false record |
| **Verdict** | Complete-with-defects (confirm close gating, effectiveness rule, action-completeness at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

CAPA drives a nonconformity to root cause and **verified effectiveness**. A CAPA carries a
**root cause** (worked out by a stated **method**), a **similar-occurrence check**
(§8.7.2 d — "did/could this happen elsewhere?"), an **action plan** decomposed into
**actions** (sub-tasks with owners/dues), a **completion** date, and — the reason the
register exists — a **verification** that the action actually worked, after a settling
period. Status: **OPEN → (in progress) → CLOSED (verified effective)** or
**CLOSED_FAILED (checked, ineffective → a further action raised)**.

Defaults: action due in `capa_due_days` (30), verification `capa_verify_days` (60) after
completion. Raised standalone, from a **complaint**, or escalated from an **NCR**
(`ncr_to_capa`, one-to-one, navigable both ways).

Screens: `/capa` (register: open / action-overdue / verify-overdue / failed / closed),
`/capa-item?id=` (record + actions + verification), `/capa-new`, plus action routes.
Tables: `capa`, `capa_actions`, `capa_events`.

---

## B. Screen-by-screen catalogue

**`/capa`** — register scoped by office/SBU; filters; readiness counts (open / action-late
/ verify-late / failed / no-similar-check / closed). **`/capa-new`** — source, linked
NCR/complaint, title, description, severity, root cause, RC method, similar-checked,
action plan, owner, due. **`/capa-item`** — record + events; **add/done/cancel/reopen
actions**; record completion; **record verification (effective yes/no + note)**; **close**
(gated); reopen.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-CAPA-form-001 | Title + severity required; source/linked entity recorded. |
| TC-CAPA-form-002 | RC method from the allowed set; root cause text captured. |
| TC-CAPA-form-003 | Similar-occurrence check is an explicit yes/no answer (not blank). |
| TC-CAPA-form-004 | Action sub-task: description + owner + due; done/cancel need a note. |
| TC-CAPA-form-005 | Verification records effective = YES/NO + a note; NO cannot mark the CAPA done. |

---

## D. Functions & logic  *(close gating + effectiveness — highest scrutiny)*

- **Close gate** (`capa_close_missing`): closing requires **root cause**, **RC method**,
  the **similar-occurrence answer**, an **action plan or actions**, **all actions
  closed/cancelled**, a **completion date**, and a **verification date**; and a CAPA
  **verified NOT effective cannot be closed as done** (raise a further action instead).
  **TC-CAPA-fn-001..007** — each missing element blocks with the exact reason;
  **TC-CAPA-fn-008** — effective=NO ⇒ close-as-done refused.
- **Actions** (`capa_action_*`): sub-tasks add/done/cancel/reopen; the parent cannot close
  while a task is OPEN (cancelled counts as settled, with a reason). **TC-CAPA-fn-009.**
- **Overdue** (`capa_action_overdue`, `capa_verify_overdue`): action past due, or
  verification past `completed_on + verify_days`. **TC-CAPA-fn-010.**
- **Escalation links** (`ncr_to_capa`, `capa_from_complaint`): one-to-one, both-way
  navigable; the NCR cannot close until its CAPA is closed (MOD-12). **TC-CAPA-fn-011.**
- **Reminders** (`capa_run_reminders`): action-late and verify-late nudges. **TC-CAPA-fn-012.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| (new) → OPEN | raise / escalate | title + severity |
| OPEN → (actions done) | action completion | each action closed/cancelled |
| → completed | record completion | completion date |
| completed → CLOSED | verify effective + close | full close gate; effective ≠ NO |
| completed → CLOSED_FAILED | verify ineffective | a further action raised |
| CLOSED → OPEN | reopen | authorised; audited |

- **TC-CAPA-life-001:** a CAPA cannot be closed without a recorded, verified-effective
  outcome.
- **TC-CAPA-life-002:** an ineffective CAPA closes as CLOSED_FAILED and spawns a further
  action, never as done.
- **TC-CAPA-life-003:** the parent cannot close while a sub-action is open.

---

## F. Roles, permissions & data scope

View: `mod.capa.view`/master-of. Raise/act: `mod.capa.edit`. Close/reopen: `capa.close`/
master. Register scoped by office/SBU. Action owners are named staff.

- TC-CAPA-perm-001 (act without permission) → refused.
- TC-CAPA-perm-002 (close without `capa.close`) → refused.
- TC-CAPA-scope-001: only own-office CAPAs in register/counts.

---

## G. Settings

`capa_due_days` (30), `capa_verify_days` (60), RC-method vocabulary, severity vocabulary,
reminder cadence, accreditation standard name (`accreditation_std_name` for the §8.7.3
message). **TC-CAPA-set-001:** verify-due derives from completion + verify_days;
**TC-CAPA-set-002:** the effectiveness-review close rule holds regardless of settings.

---

## H. Cross-module integration

**NCR** (escalation source; NCR close depends on CAPA close — MOD-12), **Complaints**
(`capa_from_complaint`), **Audits/Management review** (systemic findings → CAPA),
**Quality dashboard** (readiness counts). Idempotency: escalating the same NCR twice must
not create two CAPAs — TC-CAPA-int-001.

---

## I. Data integrity & audit

`capa_events` logs raise / action add-done-cancel-reopen / verify / close / reopen with
actor + note. The CAPA↔NCR link is one-to-one. The close gate guarantees no CAPA closes
without root cause, similar-check, completed actions, and a verified-effective outcome —
the auditable §8.7 invariant. **TC-CAPA-int-010:** every step evented; **TC-CAPA-int-011:**
readiness counts reconcile with the register.

---

## J. Reports & outputs

The register + readiness counts, the CAPA item sheet (root cause, method, similar-check,
actions, verification), reminder emails, feed to the quality dashboard and management
review. **TC-CAPA-out-001:** action-late/verify-late counts match the rows;
**TC-CAPA-out-002:** a CLOSED_FAILED CAPA shows its follow-on action.

---

## K. Negative, edge & resilience

Close with no root cause / no similar-check / open action / no completion / no
verification; close a verified-ineffective CAPA as done (refused); a verify-overdue CAPA;
a double escalation from one NCR; reopening a closed CAPA; cancelling the last action then
closing.

---

## L. TPIA operational suitability

Implements ISO/IEC 17020 §8.7 faithfully: root cause with a stated method, the
most-missed "elsewhere?" check, decomposed actions with owners, and — the crux —
effectiveness verification after a settling period, with an ineffective outcome forcing a
further action rather than a false close. Directly assessor-facing.

## M. Management usefulness

Readiness dashboard (open / action-late / verify-late / failed / no-similar-check /
closed) gives QM a live systemic view; the NCR/complaint linkage traces cause to control.
Confirm the counts reconcile with due/verify dates.

## N. UI/UX

Guided item sheet (cause → method → similar-check → actions → completion → verification →
close), clear status pills incl. CLOSED_FAILED, action sub-tasks. Terminology via `T*()`.

## O. Security

Raise/act/close authorised server-side; the close gate and the effectiveness rule hold on
a crafted close POST; scope enforced; events immutable.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | **Priority** | §E close + failed |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E |
| 12 Integration | Y | §H NCR/complaint |
| 13 Data integrity | **Priority** | §I §8.7 invariant |
| 14 Audit | Y | §I capa_events |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L (17020 §8.7) |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | — |
| 22 Notifications | Y | reminders |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | NCR/CAPA module |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | due/verify days |
| 28 Performance | Partial | register at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-CAPA-001 | (verify — Major) | Confirm the **close gate** (root cause + method + similar-check + closed actions + completion + verification) and the **effective=NO cannot-close-as-done** rule are enforced on the close POST for a crafted request. |
| GAP-CAPA-002 | (verify) | Confirm the parent CAPA cannot close while a sub-action is OPEN, and that cancel requires a reason. |
| GAP-CAPA-003 | (verify) | Confirm escalation from one NCR/complaint creates exactly one CAPA (no duplicate), and the NCR↔CAPA close dependency is mutually consistent. |

---

## R. Traceability

RTM slice: `/capa`, `/capa-*` × dims 1–29 → TC-CAPA-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — close gating, the effectiveness rule, and
single-escalation integrity are the exit conditions.
