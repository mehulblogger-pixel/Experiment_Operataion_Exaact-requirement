# Inspection Ops — Complaints & Appeals — Test & Documentation Report

> **Prompt 3 · Module MOD-COMPLAINTS.** Read from `lib/complaints.php` (`ops_complaints`,
> `cmp_create`, `cmp_involved`, `cmp_decide_block`, `cmp_close_missing`/`cmp_close_block`,
> `cmp_ack_overdue`/`cmp_decide_overdue`, `cmp_run_reminders`, `cmp_all`, `capa_from_complaint`),
> portal side `reportreview.php` (`pcmp_create`/`pcmp_appealable`). Views `complaints.php`,
> `complaint_detail.php`, `complaints_policy.php`.

| | |
|---|---|
| **Module** | Complaints & Appeals (MOD-COMPLAINTS) · Area Quality |
| **Personas** | P-QM (decide, `complaints.decide`), P-QA (record), P-CLIENT (lodge via portal), P-MASTER |
| **Risk weight** | **High** — ISO/IEC 17020 §7.5/§7.6; the decider-independence rule is an impartiality control |
| **Verdict** | Complete-with-defects (confirm independence, close gating, scope of detail view) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

The complaints/appeals register (`complaints` + `complaint_events`) handles the ISO 17020
§7.5/§7.6 loop: a complaint (or an **appeal** of a decision) is **received → acknowledged →
validity decided (is it ours) → investigated → decided → the complainant notified → CAPA
if upheld → closed**. Two independence rules are enforced as **hard refusals**: §7.5.4 — the
decider must not be someone **involved** in the work (`cmp_involved`: the inspector, and the
report's creator/finalizer/approver); §7.6 — an **appeal** cannot be decided by the person
who decided its parent. Acknowledgement and decision each have an **SLA** (`complaint_ack_days`
3, `complaint_decide_days` 30). Clients lodge via the **portal** (a request, source CLIENT).

Screens: `/complaints`, `/complaint?id=`, `/complaint-new`, action routes (`-ack/-validity/
-investigate/-decide/-notify/-capa/-close/-reopen`), `/complaints-policy` (public),
portal `/portal/complaints`. Tables: `complaints`, `complaint_events`.

---

## B. Screen-by-screen catalogue

**`/complaints`** — office-scoped register, OPEN-first. **`/complaint`** — full record +
event trail; actions: acknowledge, set validity, investigate (assign/findings/root-cause),
decide (outcome + note), notify, raise NCR/CAPA, close, reopen. **`/complaint-new`** —
subject, description, source/channel, partner, linked job/report/inspector. **Public policy**
page (pre-auth). **Portal** — client lodges a complaint/appeal against their own decided one.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-CMP-form-001 | **Subject and description both required** (else no row). |
| TC-CMP-form-002 | Validity ∈ VALID/NOT_OURS/OUT_OF_SCOPE; a non-VALID validity needs a note. |
| TC-CMP-form-003 | Decide: outcome ∈ UPHELD/PARTLY/NOT_UPHELD/WITHDRAWN + **decision note required**. |
| TC-CMP-form-004 | Notify blocked until an outcome is set. |
| TC-CMP-form-005 | Portal appeal only against the client's **own decided** complaint. |

---

## D. Functions & logic  *(independence + close — highest scrutiny)*

- **Decider independence** (`cmp_decide_block`): the decider must not be in `cmp_involved`
  (§7.5.4) and an appeal's decider ≠ the parent's decider (§7.6) — a hard refusal.
  **TC-CMP-fn-001** (an involved person's decide is refused), **TC-CMP-fn-002** (appeal by
  the parent decider refused). **Note:** the check is **name-string based** (GAP-CMP-001).
- **Close gate** (`cmp_close_missing`): acknowledged + validity decided + (if ours) outcome
  + decided-by + complainant notified (unless anonymous-unreachable) + CAPA if the outcome
  needs one. **TC-CMP-fn-003..005** — each missing element blocks.
- **SLA** (`cmp_ack_overdue`/`cmp_decide_overdue`, `cmp_run_reminders`): overdue while OPEN;
  nightly reminders. **TC-CMP-fn-006.**
- **Escalation** (`capa_from_complaint`, NCR link): an upheld complaint requires a corrective
  action before closing. **TC-CMP-fn-007.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| received → OPEN | create | subject + description |
| OPEN → acknowledged → validity → investigated → decided → notified | actions | SLA clocks; validity note; decision note |
| decided (upheld) → CAPA | escalate | CAPA before close |
| OPEN → CLOSED | close | full close gate |
| CLOSED → OPEN | reopen | authorised |

- **TC-CMP-life-001:** an upheld/partly complaint cannot close without a CAPA reference.
- **TC-CMP-life-002:** the decider-independence rule blocks the wrong person deciding.

---

## F. Roles, permissions & data scope

View: `mod.complaints.view`. Record: `mod.complaints.edit`. Decide: `complaints.decide`.
Settings: admin/master. Register office-scoped (`scope_office_clause`); **the single-record
detail view (`cmp_row`) is NOT office-scoped** (GAP-CMP-002). Portal: `partner_id`-scoped.

- TC-CMP-perm-001 (decide without `complaints.decide`) → refused.
- TC-CMP-scope-001 (open another branch's complaint by id) → **currently reachable — verify**.

---

## G. Settings

`complaint_ack_days` (3), `complaint_decide_days` (30), `complaints_policy` (public text;
blank → shipped default). Source/channel/validity/outcome are fixed enums. **TC-CMP-set-001:**
SLA days drive the overdue flags.

---

## H. Cross-module integration

**CAPA** (`capa_from_complaint`; upheld → close dependency — MOD-13), **NCR** (`/ncr-new?
source=COMPLAINT` — MOD-12), **Clients/Vendors** (`partner_id`; appeals inherit parent),
**Portal** (client lodges; appeals — MOD-10), **Audits/Management review** (complaints as an
MR input). Idempotency: a portal double-submit must not duplicate.

---

## I. Data integrity & audit

`complaint_events` logs each transition (received/ack/validity/investigate/decide/notify/
close/reopen). The independence check relies on `decided_by` stored as the user's name
(name-based, not id — GAP). SLA clocks parse free-form dates (`strtotime`) — malformed
degrades to "not overdue" (GAP). **TC-CMP-int-010:** every stage is evented;
**TC-CMP-int-011:** the CAPA link that satisfies the close gate references a real CAPA
(a free-text `capa_ref` can satisfy it — GAP-CMP-003).

---

## J. Reports & outputs

The register, the complaint record + trail, the public complaints policy, reminder emails,
and the MR input. **TC-CMP-out-001:** the notification records that the complainant was told;
**TC-CMP-out-002:** overdue reminders reach managers.

---

## K. Negative, edge & resilience

A blank complaint (refused); a decide by an involved person (refused); an appeal by the
parent decider (refused); close an upheld complaint with no CAPA (refused); an
anonymous-unreachable complaint closed without notification (the one allowed exception —
confirm not abusable); a free-text `capa_ref` satisfying the gate; opening another branch's
complaint by id.

---

## L. TPIA operational suitability

Faithful to §7.5/§7.6: validity triage, independent decision, mandatory notification, appeal
route, and CAPA on upheld outcomes, with a public policy page. The portal lets clients lodge
and appeal on the record.

## M. Management usefulness

`cmp_readiness` (open, late ack/decide, unnotified, appeals, upheld) feeds the quality
dashboard and management review. Confirm overdue reconciles with the SLA.

## N. UI/UX

Guided record (ack → validity → investigate → decide → notify → close), event trail, public
policy. Terminology via `T*()`.

## O. Security

Decide gated + independence-enforced; close gate holds; **detail view not office-scoped
(fix)**; portal appeals confined to the client's own decided complaints.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | **Priority** | §F independence |
| 9 Scope | **Gap** | §F detail view |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E |
| 12 Integration | Y | §H CAPA/NCR |
| 13 Data integrity | **Priority** | §I name-based check |
| 14 Audit | Y | §I events |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L §7.5/§7.6 |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O |
| 21 Import | N-A | — |
| 22 Notifications | Y | ack/decide reminders |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | quality pack |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | SLA clocks |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-CMP-001 | (verify — Major) | Decider **independence is name-string based** (`strcasecmp` on `decided_by` vs involved names) — a spelling/case variant or a renamed user can bypass or falsely trip it. Move to user-id matching. |
| GAP-CMP-002 | (verify — Major) | The **single-complaint detail view is not office-scoped** — a user with `view` can open another branch's complaint by guessing the id. Add scope to `cmp_row`/detail. |
| GAP-CMP-003 | (verify) | The close gate's CAPA requirement can be satisfied by an **unvalidated free-text `capa_ref`** — confirm it references a real CAPA record; and confirm the anonymous-unreachable notify exception is not abusable. |

---

## R. Traceability

RTM slice: `/complaints`, `/complaint-*`, `/portal/complaints` × dims 1–29 → TC-CMP-* →
results → DEF/GAP. **Verdict: Complete-with-defects** — decider independence (id-based),
detail-view scoping, and validated CAPA linkage are the exit conditions.
