# Inspection Ops — Nonconformities (NCR) — Test & Documentation Report

> **Prompt 3 · Module MOD-NCR.** Read from `lib/ncr.php` (`ops_ncr`, `ncr_create`,
> `ncr_close_missing`/`ncr_close_block`, `ncr_to_capa`, `ncr_ref_next`, `ncr_where`/
> `ncr_all`/`ncr_counts`, `ncr_is_overdue`, `ncr_run_reminders`, `ncr_can_view`/
> `ncr_can_raise`/`ncr_can_close`, `ncr_log`, `NCR_SOURCES`/`NCR_SEVERITY`/
> `NCR_DISPOSITIONS`/`NCR_STATUS`), integration points (`idems_raise_ncrs_from_audit`,
> report-issue signatory NCR, complaints, equipment, portals), views `ncr/register.php`,
> `ncr/item.php`, `ncr/new.php`.

| | |
|---|---|
| **Module** | Nonconformities / NCR (MOD-NCR) · Area Quality |
| **Personas** | P-QA (`mod.ncr.edit`), P-QM (`ncr.close`), P-INSP/P-COORD (raise), P-VENDOR/P-CLIENT (respond — portals), P-MASTER |
| **Risk weight** | **High** — the ISO 17020 §7/§8 control; closing one without cause/CAPA is an audit finding in itself |
| **Verdict** | Complete-with-defects (confirm close gating, MAJOR→CAPA, single-way-in numbering at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

The NCR register records anything that did not meet a requirement, from **any source**
through **one way in** (`ncr_create`): internal, an issued **report**, a **complaint**, an
**audit finding**, the **equipment** register, or a **client/vendor** via the portals.
Every caller gets the same reference numbering, branch derivation (lands on the office
that did the work), and opening event. A nonconformity moves **OPEN → CONTAINED →
DISPOSITIONED → CLOSED**, carries a **severity** (Major / Minor / Observation) and a
**disposition** (rework / re-issue / accept-with-reason / withdraw / void / none), and a
**MAJOR one cannot be closed without a corrective action (CAPA)**.

Screens: `/ncr` (register: open/overdue/closed filters, counts), `/ncr-item?id=`,
`/ncr-new`, and the action routes `/ncr-contain`, `/ncr-disposition`, `/ncr-capa`,
`/ncr-assign`, `/ncr-close`, `/ncr-reopen`. Tables: `nonconformities`, `ncr_events`,
links to `capa`.

---

## B. Screen-by-screen catalogue

**`/ncr`** — register scoped by office/SBU; filter open / overdue / closed; counts; each
row shows ref, source, severity, status pill, due/overdue. **`/ncr-new`** — source, linked
job/report/complaint/equipment/partner, title, description, clause, severity, detected-on/
by, owner, due-on. **`/ncr-item`** — full record + events; actions: **contain** (immediate
action), **disposition** (what happened to the wrong work; ACCEPT needs a reason),
**escalate to CAPA**, **assign** owner/due, **close** (gated), **reopen**.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-NCR-form-001 | Title + severity required; severity defaults to MINOR when unset. |
| TC-NCR-form-002 | Source valid; a linked entity (job/report/complaint/equipment/partner) recorded and navigable. |
| TC-NCR-form-003 | Due-on drives overdue; detected-on ≤ today. |
| TC-NCR-form-004 | Disposition = ACCEPT **requires a reason** (`disposition_note`). |

---

## D. Functions & logic  *(close gating — highest scrutiny)*

- **Single way in** (`ncr_create`): shared `ncr_ref_next()` numbering, branch derived from
  the job's executing office (fallback raiser's office), one opening event — whoever
  raises it (portal, complaint, audit, equipment, manual). **TC-NCR-fn-001** — two
  different sources produce consistent refs/branch/events.
- **Close gating** (`ncr_close_missing`): closing requires **disposition**, **containment**,
  a **reason if ACCEPT**, and for a **MAJOR** a **CAPA that is itself closed first**.
  **TC-NCR-fn-002..005** — each missing element blocks close with the exact reason;
  **TC-NCR-fn-006** — a MAJOR with no CAPA, or an open CAPA, cannot close.
- **Escalation** (`ncr_to_capa`): creates a linked CAPA, navigable both ways (`capa.ncr_id`
  / `nonconformities.capa_id`). **TC-NCR-fn-007** — one-to-one, no duplicate.
- **Overdue + reminders** (`ncr_is_overdue`, `ncr_run_reminders`): past due & not closed →
  flagged and reminded. **TC-NCR-fn-008.**
- **Auto-raise** (`idems_raise_ncrs_from_audit`, report-issue signatory rule): a
  Major/Minor audit finding and an unauthorised-signatory issue become NCRs. **TC-NCR-fn-009.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| (new) → OPEN | raise (any source) | title + severity; ref + branch + event |
| OPEN → CONTAINED | contain | immediate action recorded |
| CONTAINED → DISPOSITIONED | disposition | ACCEPT needs a reason |
| any → CLOSED | close | disposition + containment + (MAJOR ⇒ closed CAPA) |
| CLOSED → OPEN | reopen | authorised; audited |

- **TC-NCR-life-001:** a MAJOR nonconformity cannot be closed without a corrective action.
- **TC-NCR-life-002:** closing while its CAPA is still open is refused.
- **TC-NCR-life-003:** reopen is authorised and logged.

---

## F. Roles, permissions & data scope

View: `ncr_can_view` (`mod.ncr.view`/`mod.capa.view`/master-of). Raise: `ncr_can_raise`
(`mod.ncr.edit`/`mod.capa.edit`). Close/reopen: `ncr_can_close` (`ncr.close`/`capa.close`/
master). Register scoped by office/SBU. External responders (vendor/client) see only their
own vendor-/client-visible NCRs (MOD-10/11).

- TC-NCR-perm-001 (raise without permission) → refused.
- TC-NCR-perm-002 (close without `ncr.close`) → refused.
- TC-NCR-scope-001: only own-office NCRs in register/counts.

---

## G. Settings

Severity/disposition/source vocabularies (lookup-configurable), clause references, due/SLA
defaults, reminder cadence, portal visibility of an NCR. **TC-NCR-set-001:** the MAJOR→CAPA
close rule is enforced regardless of configuration (it is a control, not a toggle).

---

## H. Cross-module integration

**IDEMS** (report/audit auto-raise; report reference), **CAPA** (escalation, close
dependency — MOD-13), **Complaints** (complaint → NCR), **Equipment** (calibration/failure
→ NCR), **Jobs** (branch derivation), **Portals** (vendor/client response loop),
**Audits** (findings → NCR). Idempotency: an auto-raise must not create duplicate NCRs for
the same finding — TC-NCR-int-001.

---

## I. Data integrity & audit

`ncr_events` logs raise / contain / disposition / assign / escalate / close / reopen with
actor + note. The NCR↔CAPA link is one-to-one and navigable both ways. The close gate
guarantees no MAJOR closes without a closed CAPA — the auditable invariant. **TC-NCR-int-010:**
every state change is evented; **TC-NCR-int-011:** counts (`ncr_counts`) reconcile with the
register rows.

---

## J. Reports & outputs

The register (open/overdue/closed) + counts, the NCR item sheet, reminder emails, and the
feed into the CAPA register and the quality dashboard. **TC-NCR-out-001:** overdue and
open counts match the rows; **TC-NCR-out-002:** a portal-raised NCR appears with its
source correctly attributed.

---

## K. Negative, edge & resilience

Close with no disposition; close a MAJOR with no CAPA; close with an open CAPA; ACCEPT
with no reason; a duplicate auto-raise from one finding; an overdue NCR; reopening a
closed NCR; a portal response on a not-visible NCR (blocked — MOD-11).

---

## L. TPIA operational suitability

Directly serves ISO/IEC 17020 §7.6/§8.7: one register, every source funnelled through it,
containment then root-cause via CAPA for majors, dispositions that answer the assessor's
question ("what happened to the work that was already wrong?"), and a close gate that
prevents a nonconformity being waved through. The external response loop closes it with
the vendor/client.

## M. Management usefulness

Open/overdue counts, ageing, severity mix, source analysis, and the CAPA linkage give QA
and management a live view of quality control. Confirm overdue reconciles with due dates.

## N. UI/UX

One register, clear status pills, guided item sheet (contain → disposition → close), and a
single "raise" path. Terminology via `T*()`.

## O. Security

Raise/close/reopen authorised server-side; the MAJOR→CAPA close gate holds on a crafted
close POST; scope enforced; portal responders confined to their visible NCRs; events
immutable.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | **Priority** | §E close gate |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I MAJOR→CAPA invariant |
| 14 Audit | Y | §I ncr_events |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L (17020 §7/§8) |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | — |
| 22 Notifications | Y | reminders |
| 23 Offline | N-A | — |
| 24 AI | Partial | QA auditor feeds NCR |
| 25 Licensing | Y | NCR/CAPA module |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | due/overdue |
| 28 Performance | Partial | register at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-NCR-001 | (verify — Major) | Confirm the **close gate** (disposition + containment + ACCEPT-reason + MAJOR-needs-closed-CAPA) is enforced on the close POST for a crafted request, not only in the UI. |
| GAP-NCR-002 | (verify) | Confirm **single-way-in** numbering and branch derivation are consistent across all sources (portal / complaint / audit / equipment / manual), with no gaps or duplicate refs. |
| GAP-NCR-003 | (verify) | Confirm auto-raise (audit findings, unauthorised signatory) does **not** duplicate NCRs for the same finding on re-issue. |

---

## R. Traceability

RTM slice: `/ncr`, `/ncr-*` × dims 1–29 → TC-NCR-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — close gating (esp. MAJOR→CAPA), single-way-in
integrity, and no-duplicate auto-raise are the exit conditions.
