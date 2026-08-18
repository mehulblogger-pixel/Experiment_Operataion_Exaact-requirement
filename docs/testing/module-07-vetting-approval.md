# Inspection Ops — Vetting & Approval — Test & Documentation Report

> **Prompt 3 · Module MOD-IDEMS (vetting & approval workflow).** Read from `lib/idems.php`
> (`idems_vetting_gate_on`, `idems_vetting_required`, `ops_idems_vet`,
> `ops_idems_vet_review`, `idems_vetting_checklist_*`, `ops_idems_vetting_checklist`,
> `idems_build_approval_chain`, `idems_resolve_approver`, `idems_inspector_approver`,
> `idems_current_step`, `idems_can_act_step`, `idems_awaiting_my_approval_clause`,
> `ops_idems_approve`, `ops_idems_approver_map`, `ops_idems_approval_rules`,
> `idems_run_sla_escalations`, `idems_notify_vetters`, `idems_notify_approver`), views
> `idems/doc_detail.php`, `idems/vet_review.php`, `idems/vetting_checklist.php`,
> `idems/approver_map.php`, `idems/approval_rules.php`.

| | |
|---|---|
| **Module** | Vetting & Approval (MOD-IDEMS/workflow) · Area Reporting |
| **Personas** | P-INSP (submit), P-VET (vetting authority = `idems.finalize`/master), P-APPR (approver; manager/coordinator/inspector/sr-inspector), P-MASTER |
| **Risk weight** | **Highest** — the control that decides whether a report becomes a signed client document; independence and no-self-approval live here |
| **Verdict** | Complete-with-defects (confirm gate ordering, auto-forward, can-act authority, mandatory-remark, SLA at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. Vetting-before-approver + auto-forward verified live. |

---

## A. Module overview

Two controls stand between a filled report and an issued one:

1. **Vetting** — when the **vetting gate** (`vetting_gate_required`) is on, a submitted
   inspection report goes to the **vetting authority** *first* (status `VETTING`). The
   approval chain is **not** built yet. The vetter clears it (**VETTED**), returns it
   (**RETURNED** → back to DRAFT, chain cleared), or **DEBRIEFED**. On **VETTED** the
   report **auto-forwards**: the approval chain is built, the approver resolved and
   notified, status → `UNDER_REVIEW` — no extra click. Release notes (RN/IRN) skip
   vetting by design.
2. **Approval** — a configurable multi-level chain (`report_approvals`). Each level
   resolves to a concrete approver by **report-pick** (chosen on the form),
   **rule** (type/office/client/SBU-scoped), **inspector→approver map**, **reports-to**,
   or **role**. The current approver approves (→ next level or APPROVED), **rejects**
   (→ REJECTED, remark mandatory), **sends back** (→ DRAFT, remark mandatory), or
   **delegates**. SLA hours per level drive escalation.

Screens: `/document?id=` (vet + approve panel), `/document-vet-review` (side-by-side:
the real report + the checklist), `/vetting-checklist` (config), `/approver-map`,
`/idems-approval-rules`, `/documents?mine=approve` (my queue).
Tables: `report_vetting`, `report_approvals`, `idems_approver_map`, `idems_approval_rules`.

---

## B. Screen-by-screen catalogue

**Vet panel** (`doc_detail`) — vet action (VETTED / RETURNED / DEBRIEFED), note, the
configurable checklist (ticks), gated "Vet (cleared)".

**`/document-vet-review`** — side-by-side: the actual report rendered on one side, the
checklist on the other; tick + vet/return/debrief posts to the same `/document-vet`
handler (the exact refinement the user asked for: *verify the checklist against the
actual report, side by side*).

**Approve panel** — approve / reject / send-back / delegate, remarks, delegate-to
picker (shown only when the viewer can act on the current step).

**`/vetting-checklist`** — enable checklist, require-all, **gate-required** (turns the
vetting gate on/off), and the item list (starter default offered, admin-editable).

**`/approver-map`** — per-inspector approver + temporary (dated) delegate.

**`/idems-approval-rules`** — level, scope (type/office/client/SBU), approver kind/user/
role, SLA hours, sort order.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-VET-form-001 | Vet action required; **RETURNED requires a note**. |
| TC-VET-form-002 | Checklist require-all: **VETTED** refused until every applicable point is ticked (count shown); Return/Debrief unaffected; nothing when checklist off. |
| TC-APPR-form-001 | **Reject requires a remark**; **send-back requires a remark** (else refused). |
| TC-APPR-form-002 | Delegate requires a target person. |
| TC-APPR-form-003 | Approval-rule save: level ≥ 1, SLA ≥ 0, kind in the allowed set. |

---

## D. Functions & logic  *(the control — highest scrutiny)*

- **Gate ordering** (`idems_vetting_required`): gate on + not RN ⇒ submit → `VETTING`,
  chain deleted; gate off ⇒ submit → chain built → `UNDER_REVIEW`. **TC-VET-fn-001.**
- **Auto-forward** (`ops_idems_vet`, VETTED at status VETTING): builds the chain; if it
  resolves to **nobody**, the report is *not* forwarded and the vetter is told to set the
  approver — no silent dead-end. On success → `UNDER_REVIEW`, approver notified.
  **TC-VET-fn-002** (forward happens), **TC-VET-fn-003** (unresolved approver blocks with
  guidance, chain cleaned).
- **Chain resolution** (`idems_build_approval_chain` + `idems_resolve_approver`):
  report-pick wins; else rules; else default single level to the inspector's map / then
  reports-to. A rule pointing at nobody falls back so a configured approver always drives
  the chain (never the "no approver" refusal). **TC-APPR-fn-001** — each kind resolves;
  **TC-APPR-fn-002** — fallback chain when a rule resolves empty.
- **Can-act authority** (`idems_can_act_step`): master, the resolved user, a delegate, a
  matching **role**, or (open "any approver" step) anyone with `idems.finalize`.
  **TC-APPR-fn-003** — a non-approver is refused on the approve POST; **TC-APPR-fn-004**
  — an approver can act only on the **current lowest-pending** level, not a later one.
- **Multi-level progression**: approve routes to the next pending step and notifies; the
  last approval sets status APPROVED. **TC-APPR-fn-005.**
- **Awaiting-my queue** (`idems_awaiting_my_approval_clause`): the register chip lists
  exactly the docs whose current step resolves to me (by name / delegation / role / open
  finalizer). **TC-APPR-fn-006** — the SQL clause matches `idems_can_act_step` for the
  same user.
- **SLA escalation** (`idems_run_sla_escalations`, cron): only the current step, only
  once (`escalated=0`), past `sla_due`, notifies managers/approver. **TC-APPR-fn-007.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| SUBMITTED/VETTING | submit + gate on | completeness gate (MOD-06); chain deferred |
| VETTING → UNDER_REVIEW | vet = VETTED | approver resolves to someone; auto-forward |
| VETTING → DRAFT | vet = RETURNED | note mandatory; chain cleared |
| UNDER_REVIEW → (next level) | approve | can-act = current step |
| UNDER_REVIEW → APPROVED | last approve | all levels approved |
| UNDER_REVIEW → REJECTED | reject | remark mandatory |
| UNDER_REVIEW → DRAFT | send-back | remark mandatory; reopens editing |

- **TC-VET-life-001:** a report already **issued/finalized** cannot be vetted (closed).
- **TC-APPR-life-001:** reject/send-back reopen the correct state (REJECTED vs DRAFT) and
  are audited with the remark.
- **TC-APPR-life-002:** delegation records who and notifies the delegate; the delegate can
  then act (`delegated_to`).

---

## F. Roles, permissions & data scope

Vet: `idems_can_vet()` = master or `idems.finalize`. Approve: the resolved approver of the
current step (which may be a **manager, coordinator, inspector or senior inspector** —
the widened approver set, MOD-05/W5), a matching role, a delegate, or master.
Checklist/rules/map config: master / `idems.type.manage` / `idems.finalize`. Notifications
scoped to active users with the right permission/role.

- TC-VET-perm-001 (a non-finalizer vet POST) → refused.
- TC-APPR-perm-001 (someone not on the current step approves) → refused.
- TC-APPR-perm-002 (**independence**): confirm the approver is a different person from the
  inspector where independence is required (GAP-APPR-001) — see §Q.

---

## G. Settings

`vetting_gate_required` (gate on/off), `vetting_checklist_on`, `vetting_checklist_require`,
`vetting_checklist_items` (admin-edited; starter default), approval rules table, per-
inspector approver map (+ temporary dated delegate), SLA hours per level. All
configurable, nothing agency-hardcoded; gate & checklist off by default so existing
installs are unchanged. **TC-VET-set-001:** turning the gate on reroutes new submissions
through VETTING; off restores direct-to-approval.

---

## H. Cross-module integration

**IDEMS core** (submission builds/defers the chain; edit-lock), **Release Notes** (RN
skips vetting; goes straight to approver — MOD-08), **Users & roles** (approver set;
`idems.finalize`), **Inspectors** (map / reports-to), **Notifications** (vet/approve/SLA
emails), **Dashboards** (pending-approval queue on every dashboard — W6). Idempotency: a
double-vet or double-approve must not double-advance the chain — TC-VET-int-001.

---

## I. Data integrity & audit

Every vet/return/debrief (`report_vetting`), every approve/reject/sendback/delegate
(`report_approvals` + `idems_audit`: VET_*, VET_FORWARD, APPROVE, REJECT, SENDBACK,
DELEGATE) is recorded with who/when/why. Returning clears the half-built chain (no
orphaned pending steps). **TC-VET-int-010:** after a RETURNED then resubmit, exactly one
fresh chain exists; **TC-APPR-int-011:** the approved figure/version is the one that
issues (content seal, MOD-06).

---

## J. Reports & outputs

Vetting log and approval trail on the report; approver/vetter notification emails; SLA
overdue emails; the my-approval queue and register chip. No PDF of its own — it gates the
report PDF. **TC-VET-out-001:** the notification names the IRN/type/title and links to the
document; **TC-APPR-out-001:** the trail shows each level, actor, decision and remark.

---

## K. Negative, edge & resilience

Vet a report with no approver mappable (blocked with guidance); vet an already-issued
report; return without a note; approve out of turn; reject/send-back with no remark;
delegate to nobody; a rule that resolves to a deactivated user; two approvers of the same
level acting concurrently; SLA firing twice; the gate toggled mid-flight for a report
already at VETTING.

---

## L. TPIA operational suitability

Mirrors real TPIA governance: an independent technical **vetting** step before an
**authorising** approver, a configurable multi-level chain scoped by type/office/client,
delegation for cover, SLA discipline, and a side-by-side check of the checklist against
the actual report. The RN-skips-vetting rule matches how release notes are authorised
directly.

## M. Management usefulness

Every dashboard shows the pending-approval/vetting queue (W6); SLA escalation surfaces
stuck reports; the trail evidences who approved what. Confirm the queue counts match
`idems_can_act_step`.

## N. UI/UX

One-screen side-by-side vetting; guided playbook next-step; my-approval chip; delegate
picker only when actionable; checklist ticks with a live count. Terminology via `T*()`.

## O. Security

Vet/approve authorised server-side by `idems_can_vet`/`idems_can_act_step` (not hidden
buttons); a crafted approve POST from a non-approver is refused; mandatory remarks
enforced server-side; delegation cannot escalate beyond the step; config screens gated;
notifications never leak cross-scope reports.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | **Priority** | §E — gate + chain ordering |
| 6 Validation | Y | §C (mandatory remarks/notes) |
| 7 Negative | Y | §K |
| 8 Roles | **Priority** | §F — can-act + independence |
| 9 Scope | Y | §F notifications |
| 10 Settings | Y | §G |
| 11 Workflow | **Priority** | §A/§D — auto-forward |
| 12 Integration | Y | §H |
| 13 Data integrity | Y | §I (clean chain) |
| 14 Audit | Y | §I VET_*/APPROVE etc. |
| 15 Outputs | Y | §J notifications/trail |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N side-by-side |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | — |
| 22 Notifications | **Priority** | §J vet/approve/SLA |
| 23 Offline | N-A | reviewer desktop |
| 24 AI | Partial | QA advisory feeds the vetter |
| 25 Licensing | Y | IDEMS module |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | SLA hours |
| 28 Performance | Partial | queue at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-APPR-001 | (verify — potential Major) | Confirm **no self-approval** where independence is required — an inspector who is also the resolved approver of their own report should be caught (either blocked or flagged), not silently allowed. |
| GAP-APPR-002 | (verify) | Confirm **can-act** is enforced on the approve POST for a crafted request (non-current-step / non-approver refused), and that concurrent same-level approvals cannot double-advance. |
| GAP-VET-001 | (verify) | Confirm the **auto-forward** builds exactly one chain, notifies exactly the resolved approver, and the unresolved-approver path leaves no orphan pending rows. |
| GAP-APPR-003 | — | Confirm SLA escalation fires once per step and only for the current pending level. |

---

## R. Traceability

RTM slice: `/document` (vet/approve), `/document-vet-review`, `/vetting-checklist`,
`/approver-map`, `/idems-approval-rules`, `/documents?mine=approve` × dims 1–29 →
TC-VET-*/TC-APPR-* → results → DEF/GAP. **Verdict: Complete-with-defects** — gate
ordering + auto-forward, can-act authority + independence, and SLA correctness are the
exit conditions.
