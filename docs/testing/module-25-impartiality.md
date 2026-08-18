# Inspection Ops — Impartiality & Conflicts — Test & Documentation Report

> **Prompt 3 · Module MOD-IMPART.** Read from `lib/impartiality.php` (`imp_declarations`/
> `imp_current_declaration`/`imp_declaration_due`, `imp_threats`/`imp_blocking_for`/
> `imp_block`, `imp_job_declaration_due`, `ib_type`, `ops_impartiality`, `IMP_THREATS`/
> `IMP_STATUS`/`IMP_BLOCKING`), gate wiring `lib/packs.php` (`work.assign`), per-deputation
> tick `lib/ops.php` (`impartiality_ok`). Views `impartiality.php`, `job_form.php`.

| | |
|---|---|
| **Module** | Impartiality & Conflicts (MOD-IMPART) · Area Quality |
| **Personas** | P-ADMIN/P-MASTER (decide threats), P-COORD (per-deputation tick), P-INSP (declaration) |
| **Risk weight** | **High** — ISO/IEC 17020 §4.1; an unmanaged conflict undermines every report the person touches |
| **Verdict** | Complete-with-defects (confirm the allocation block, per-deputation attestation, no-self-approval seam) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

The impartiality control has three parts: (1) **per-person declarations**
(`impartiality_declarations`) — a signed statement, valid ~1 year, lapse reported (advisory,
does not block); (2) a **threat register** (`impartiality_threats`) — eight threat kinds
(self-interest, familiarity, financial, organisational, outsourcing …), each OPEN →
MITIGATED / ACCEPTED / UNACCEPTABLE, where **OPEN and UNACCEPTABLE threats block allocation**
(`imp_block` via `work.assign`, **non-overridable**); a threat with no `partner_id` blocks
**all** clients; and (3) a **per-deputation declaration** (`impartiality_ok` on the job) —
the allocator ticks "nothing to declare", stamped with who/when. Inspection-body type
(A / non-A) is a setting.

Screens: `/impartiality` (people, threats, readiness), `/imp-type`, `/imp-declare`,
`/imp-threat-add`, `/imp-threat-decide`; per-deputation checkbox on `job_form`. Tables:
`impartiality_declarations`, `impartiality_threats`, `jobs.impartiality_*`.

---

## B. Screen-by-screen catalogue

**`/impartiality`** — people + declaration status; the threat register (OPEN first) with
add/decide; readiness counts; inspection-body type. **Job form** — the per-deputation
impartiality tick + note, shown back with attribution.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-IMP-form-001 | Declaration with conflicts set but empty statement → refused. |
| TC-IMP-form-002 | Threat kind coerced to a valid key; empty description refused. |
| TC-IMP-form-003 | Decide MITIGATED/ACCEPTED requires **safeguards** text. |
| TC-IMP-form-004 | Threat with no partner blocks every client (`imp_blocking_for`). |

---

## D. Functions & logic  *(the block — highest scrutiny)*

- **Allocation block** (`imp_block` via `work.assign`): an OPEN or UNACCEPTABLE threat
  touching this person/party (or a partner-less threat = all clients) blocks the allocation,
  **non-overridable** (`override => ''`). **TC-IMP-fn-001** — a crafted allocation with an
  open threat is refused; **TC-IMP-fn-002** — a mitigated/accepted threat does not block.
- **Per-deputation attestation** (`impartiality_ok`): stamps `impartiality_by`/`_at` when
  ticked, clears when not — but it is a **self-attested checkbox not validated against the
  threat register** (GAP-IMP-002). **TC-IMP-fn-003.**
- **Declarations** (`imp_declaration_due`): a lapsed/absent declaration is advisory only —
  it does **not** block allocation (GAP-IMP-003). **TC-IMP-fn-004.**
- **Threat review** (`review_on`): recorded but never enforced/alerted (GAP). **TC-IMP-fn-005.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| threat → OPEN | raise | description |
| OPEN → MITIGATED/ACCEPTED | decide | safeguards required |
| OPEN → UNACCEPTABLE | decide | blocks allocation |
| declaration → valid | declare | statement if conflicts |
| allocation | job save | OPEN/UNACCEPTABLE threat blocks (non-overridable) |

- **TC-IMP-life-001:** an OPEN threat blocks allocation until decided.
- **TC-IMP-life-002:** an UNACCEPTABLE threat blocks even after "decision".

---

## F. Roles, permissions & data scope

View: `mod.competence.view`/master-of-competence/admin+licence. Record/decide
(`imp_can_decide`): admin/master. Module pack-gated (accreditation). **No office/tenant
scoping** despite an `office_id` on threats (GAP).

- TC-IMP-perm-001 (raise/decide without authority) → refused.

---

## G. Settings

`inspection_body_type` (A / non-A). Threat kinds are consts. Requires an accreditation pack.
**TC-IMP-set-001:** the register only shows with a pack on.

---

## H. Cross-module integration

**Jobs allocation** (block via `work.assign` — MOD-05), **Competence/Identity** (sibling
`work.assign` gates), **Vetting/Approval** (the **no-self-approval** independence question —
MOD-07: **NOT enforced in code**; SELF_REVIEW exists only as a manually-raised threat kind —
GAP-IMP-001), **Complaints** (decider independence is a separate name-based check — MOD-22).

---

## I. Data integrity & audit

Threat decisions stamp decided-by/on; declarations kept per person. Dates are plain VARCHAR
string-compared (malformed compares wrong — GAP). **TC-IMP-int-010:** the current threat
status drives the block; **TC-IMP-int-011:** the per-deputation tick records who attested.

---

## J. Reports & outputs

The register + readiness, the per-deputation attestation on the job, threat safeguards.
No document of its own. **TC-IMP-out-001:** an open threat surfaces on the allocation as the
block reason.

---

## K. Negative, edge & resilience

Allocate with an open threat (blocked); a partner-less threat (blocks all clients); a
mitigated threat with no safeguards (refused); a lapsed declaration (does not block — by
design); the same person as author and approver of a report (**not caught** — GAP-IMP-001);
a threat past its review date (stays cleared silently).

---

## L. TPIA operational suitability

Covers §4.1's core: declarations, a threats-to-impartiality register with mitigation/
acceptance, a non-overridable allocation block, and a per-deputation attestation.
Deliberately manual analysis (no auto-scoring) fits the standard's judgement-based
requirement — but the no-self-approval seam and the advisory-only declaration are gaps.

## M. Management usefulness

`imp_readiness` (people, declarations due, open/unacceptable threats, body type) surfaces
impartiality risk; feeds management review. Confirm open threats reconcile with blocks.

## N. UI/UX

Threat register with decide, per-deputation tick, readiness. Terminology via `T*()`.

## O. Security

Decide/allocate block enforced server-side (non-overridable); **no self-approval control on
reports (fix)**; per-deputation attestation is self-asserted (consider validating against
the register); no office scoping (fix).

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | **Gap** | §F no scoping |
| 10 Settings | Y | §G |
| 11 Workflow | **Priority** | §D allocation block |
| 12 Integration | **Priority** | §H self-approval seam |
| 13 Data integrity | Partial | §I string dates |
| 14 Audit | Y | §I |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L §4.1 |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O |
| 21 Import | N-A | — |
| 22 Notifications | Partial | review dates unenforced |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | accreditation pack |
| 26 Terminology | Y | — |
| 27 Time/FY | Partial | string dates |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-IMP-001 | (verify — Major) | **No automatic no-self-approval / segregation-of-duties check** on report vetting/approval — the same person can be author and approver; SELF_REVIEW is only a manually-raised threat. Add an independence check tying MOD-07 to this register (also GAP-APPR-001). |
| GAP-IMP-002 | (verify) | The **per-deputation `impartiality_ok` is a self-attested checkbox** not validated against the threat register — an allocator can tick it despite an open threat on that person/party. Cross-check it. |
| GAP-IMP-003 | (verify) | A **lapsed/absent declaration does not block allocation** and `review_on` re-review dates are never enforced/alerted — confirm intended, or surface them. |
| GAP-IMP-004 | — | Add office/tenant scoping to the threat/declaration register (the `office_id` column is unused). |

---

## R. Traceability

RTM slice: `/impartiality`, `/imp-*`, allocation block, per-deputation tick × dims 1–29 →
TC-IMP-* → results → DEF/GAP. **Verdict: Complete-with-defects** — the allocation block,
the no-self-approval independence seam, and per-deputation validation are the exit
conditions.
