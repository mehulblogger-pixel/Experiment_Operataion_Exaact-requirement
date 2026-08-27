# Scripted Test Run — Report → Vetting → Approval → Issue (with exact verdicts)

**Scope.** One stage of the E2E flow (`docs/qa-edge-cases/00-end-to-end-flow.md`), expanded into a step run a
tester can execute and check. Unlike the money stage, the "figures" here are **states, gate verdicts, seal
outcomes and the sign-off roles** — so the expected column shows the *exact* readiness items and their
`ok / warn / block` states, each **produced by the live engine** (`idems_issue_readiness()`,
`idems_content_check()`) on the seed below. A ⚙ marks a verdict captured from a real run; a 📘 marks one
stated from the engine's documented rule (the branch wasn't exercised in this run but the code path is cited).

**The rule that governs the gate:** readiness is **READY unless at least one item is `block`**. `warn` items are
advisory — they show, but they do not stop issue **unless** `issue_gate_strict=1` escalates them (§10).

---

## 1. Seed data

A report on the closed `JOB-DEMO` from the previous stage:

| Object | Field | Value |
|---|---|---|
| **Report** `RPT-DEMO` | type_code | **FIR** (final inspection report) · job = JOB-DEMO |
| | inspector_id | a competent inspector · inspection_date within cert validity |
| | status | starts **DRAFT** |
| Approval chain | — | none yet |
| Vetting | `idems_vetting_required` | **off** by default (turn on for Step B2) |
| Release acceptance | `rn_require_client_acceptance` | off (turn on for the RN edge) |

---

## 2. Step-by-step, with the exact readiness verdict

### Step A — DRAFT (just created) ⚙

`idems_issue_readiness($doc)` →

| Item | **State** | Detail |
|---|---|---|
| Approval | **warn** | "No approval chain yet — submit it for approval first." |
| Quality check | ok | No critical quality issues. |
| Instruments & authorisation | ok | Calibration and signer authorisation in order. |
| Evidence & on-site | warn | 0 photos. Advisory — does not block issue. |
| Report completeness | warn | 8 required item(s) missing (checked at submit). |
| **Verdict** | **READY = YES** | *(no `block` item — everything is a warning)* |

> **Tester nuance:** a DRAFT reads "READY" from the *gate* because it has only warnings — but you still can't
> issue it through the normal flow, because issuing is done from an **APPROVED** report. The gate is the
> completeness advisor; the workflow status is the hard door. Reflected in: Issue-readiness panel (Module 08).

### Step B — Submit for approval → SUBMITTED, chain pending ⚙

Submit the report; an approval step is created and sits PENDING.

| Item | **State** | Detail |
|---|---|---|
| Approval | **block** | "Still going through the approval chain." |
| … other items | ok / warn | (unchanged) |
| **Verdict** | **READY = NO** | *(the pending approval is a blocker)* |

✅ **PASS if** an un-approved report is **NOT READY** with the Approval blocker. Reflected in: My Work "to
approve"; the readiness panel.

### Step B2 — Vetting required but not vetted 📘

Turn on the body's vetting workflow (`idems_vetting_required($doc)` true) and leave `vet_status ≠ VETTED`:

| Item | **State** | Detail |
|---|---|---|
| Technical vetting | **block** | "Vetting is required for this report and is not yet cleared." |
| **Verdict** | **READY = NO** | *(vetting is the one probe that hard-blocks by config, even without strict mode — §10)* |

✅ **PASS if** issue is blocked until `vet_status = VETTED`. Vetting only appears when the lab actually runs it
— a body that doesn't vet never sees this item. (Engine rule: `idems.php:6007-6011`.)

### Step C — Approve the chain → APPROVED ⚙

The approver signs off (a **different** person than the issuer — segregation).

| Item | **State** | Detail |
|---|---|---|
| Approval | **ok** | "Fully approved through the chain." |
| Quality check | ok | No critical quality issues. |
| Instruments & authorisation | ok | Calibration and signer authorisation in order. |
| Evidence & on-site | warn | 0 photos. Advisory — does not block issue. |
| Report completeness | warn | (advisory) |
| **Verdict** | **READY = YES** | |

✅ **PASS if** an approved, complete report is **READY**. (Add site photos + a full check-in to clear the two
warnings; they never block on their own.)

### Step C2 — An open NCR against the report (advisory) ⚙

Raise a nonconformity (`status ≠ CLOSED`) linked to the report:

| Item | **State** | Detail |
|---|---|---|
| Nonconformities | **warn** | "1 open nonconformity/NCR(s) raised against this report." |
| **Verdict** | **READY = YES** | *(advisory by default — it shows but doesn't block)* |

### Step C3 — Same, with `issue_gate_strict = 1` ⚙

| Item | **State** | Detail |
|---|---|---|
| Nonconformities | **block** | (same detail, now a hard block) |
| **Verdict** | **READY = NO** | *(strict mode escalates competence / impartiality / NCR warnings to blocks — §10)* |

✅ **PASS if** the same open NCR is a **warning** by default and a **blocker** under `issue_gate_strict`.

### Step C4 — Release note without client acceptance 📘

For a `type_code ∈ {RN, IRN}` with `rn_require_client_acceptance = 1` and no recorded decision:

| `client_decision` | Item state | Verdict |
|---|---|---|
| (blank) | Client acceptance = **warn** | READY (advisory) |
| REJECTED | Client acceptance = **block** | **NOT READY** — "the client rejected this report" |
| ACCEPTED | Client acceptance = **ok** | READY |

(Engine rule: `idems.php:6039-6045`.)

### Step D — Issue the report ⚙ (seal verified)

Issue the READY, approved report.

| What | **Expected** | Reflected in |
|---|---|---|
| Status | DRAFT/APPROVED → **ISSUED** | report; `report_docs.status` |
| IRN | assigned per the numbering rule | report header; PDF |
| Immutability | the content is **sealed** | `content_seal` set |
| `idems_seal_content()` | returns **true** | — |
| `idems_content_check()` | **sealed = true · ok = true · problem = —** | compliance check |
| Audit | an append to the tamper-evident chain (§54) | `idems_audit` |

✅ **PASS if** after issue, `content_check` reports **sealed=true, ok=true**.

### Step E — §11 seal fail-closed ⚙ (verified)

Simulate a failed seal (the `SEAL_FAILED` sentinel is written instead of a real seal):

| State | `idems_content_check()` | Meaning |
|---|---|---|
| healthy seal | sealed=**true**, ok=**true**, problem=— | genuine seal |
| **failed seal** | sealed=**false**, ok=**false**, problem=**seal_failed** | never reads as a false "sealed" (§11) |

✅ **PASS if** a failed seal reports **sealed=false** (not a false OK). The self-heal cron
(`idems_reseal_failed`) then re-seals it and `idems_seal_failed_count()` drops to 0; a compliance-check row
flags it meanwhile. Reflected in: `/system-status` compliance; the report's seal badge.

### Step F — §4 four roles on the PDF 📘

Open the issued PDF. `idems_report_signatures()` supplies the sign-off blocks; the PDF prints:

| Role | Printed when | Source |
|---|---|---|
| **Prepared by** | always (the inspector) | inspector signature + prepared timestamp |
| **Vetted by** | only if the report was vetted | last vetting actor |
| **Approved by** | the approval chain's last APPROVED actor | `report_approvals.status='APPROVED'` |
| **Issued by** | the issuer at finalisation | finaliser |

✅ **PASS if** the PDF shows all four roles that actually applied — and shows **only** those that applied (a
report with no vetting step does not print an empty "Vetted by" row). (Engine: `idems_report_signatures`,
`idems.php:7566`.)

### Step G — §9 structured return-to-inspector 📘

At vetting or approval, instead of approving, **return** the report with a specific correction:

| Field | Value | Reflected in |
|---|---|---|
| correction_area | e.g. "Section 4 — Dimensional results" | `report_approvals.correction_area` |
| correction_deadline | e.g. 2026-06-05 | `report_approvals.correction_deadline` |

**Expected:** the inspector is told **exactly** which section/field and **by when** (`idems_latest_return()`
surfaces area + deadline), not a free-text "please fix". ✅ **PASS if** the returned report shows the area and
the deadline to the inspector. Reflected in: the report's return banner; My Work.

---

## 3. Edge cases on this stage

| # | Change | Expected | Sev |
|---|---|---|---|
| E1 | Issuer = the approver | Segregation blocker: "a different person must issue it" (master exempt) | 🟠 |
| E2 | Critical QA finding | Quality check = **block** (admin override with a recorded reason) | 🔴 |
| E3 | Instrument out of calibration | Instruments = **block** — *not overridable* (calibration is hard) | 🔴 |
| E4 | Non-applicable report type forced on | Allowed but stores a reason + `APPLICABILITY_OVERRIDE` audit + `not_allocated` (§6) | 🟠 |
| E5 | Edit an ISSUED report | Refused — controlled revision only; original preserved, sealed | 🔴 |
| E6 | Evidence chain broken | Evidence = **warn** "chain BROKEN" (advisory — does not block, but visible) | 🟡 |
| E7 | Reused evidence photo across reports | Flagged by §68 (shared hash) | 🟡 |
| E8 | Two statuses on the parent call disagree | Flagged by §46 integrity check, independent of the report | 🟡 |
| E9 | Finding → NCR → CAPA | The chain shows as one Quality Case on the report/NCR/CAPA (§39, entity-360) | 🟡 |

---

## 4. Pass/fail summary

| Assertion | Expected | Verified | Pass? |
|---|---|---|---|
| DRAFT readiness | warnings only, no block (advisory READY) | ⚙ | ☐ |
| SUBMITTED, un-approved | **NOT READY** — Approval block | ⚙ | ☐ |
| Vetting required, not vetted | **NOT READY** — Technical vetting block | 📘 | ☐ |
| APPROVED, complete | **READY** | ⚙ | ☐ |
| Open NCR, default | **warn**, still READY | ⚙ | ☐ |
| Open NCR, `issue_gate_strict=1` | **block**, NOT READY | ⚙ | ☐ |
| RN rejected by client | Client acceptance block | 📘 | ☐ |
| After issue | seal **sealed=true, ok=true** | ⚙ | ☐ |
| Failed seal | **sealed=false, ok=false, problem=seal_failed** | ⚙ | ☐ |
| Issued PDF | Prepared / Vetted / Approved / Issued — each only where it applied | 📘 | ☐ |
| Structured return | area + deadline shown to the inspector | 📘 | ☐ |
| Edit issued report | refused (controlled revision only) | 📘 | ☐ |

*⚙ verdicts captured from the live engine on 2026-08-27; 📘 verdicts stated from the cited engine rule. For
the money stage see `01-scripted-run-job-to-invoice.md`; for the whole flow, `00-end-to-end-flow.md`.*
