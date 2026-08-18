# Inspection Ops — End-to-End TPIA Workflow Test Report

> **Prompt 4 · End-to-End.** Traces the golden threads that cross module boundaries, so a
> defect that hides between two green modules is caught. Built on the 31 module reports
> (MOD-01…MOD-36), Inventory v1.0 and Governance v1.0. Each thread is a persona-driven
> scenario with preconditions → steps → cross-module gates → expected result → the modules
> and defects it exercises. **This is a test *plan and trace*, not a claim that every step has
> been executed** — the automated suite (`php tests/run.php`, 1532 passing) covers the unit
> layer; the runtime steps below are the acceptance path for a release sign-off.

| | |
|---|---|
| **Scope** | 9 end-to-end threads spanning Sales → Operations → Reporting → Money → Quality |
| **Personas** | P-MKT, P-BDM, P-COORD, P-INSP, P-VET, P-APPR, P-FINAL, P-ACCTS, P-BM, P-CLIENT, P-VENDOR, P-MASTER |
| **Method** | Each thread runs on a throwaway SQLite (seed demo), then verified live in the browser for the money- and control-critical steps |
| **Verdict** | **Release-candidate with conditions** — the spine works end to end; the exit conditions are the cross-module money-consistency and control-enforcement gaps listed in §Exit |

---

## Golden thread map

```
Lead ─► Inquiry ─► Quotation ─► Order/Contract ─► Call ─► Job ─► Report
  17     19          03            18            04     05     06
                                                                │
                              Vetting ─► Approval ─► Issue ─► Release Note
                                07         07         08        08
                                                       │
                                            Invoice ─► Payment ─► Profit
                                              09         09         32
Quality spine (parallel): HWP 21 · NCR 12 · CAPA 13 · Complaints 22
Personnel gates (at allocation & issue): Competence 24 · Impartiality 25 · Identity 26 · Equipment 23
Governance: Confidentiality 27 · Audits/Trail 28 · Data control/Verify 29 · Portals 10/11
```

---

## Thread E2E-1 — Win-to-Invoice (the commercial spine)

**Persona chain:** P-BDM → P-COORD → P-INSP → P-VET → P-APPR → P-FINAL → P-ACCTS.
**Precondition:** a client master with complete tax identity, not on hold; the operations +
sales + reporting + money modules licensed.

**Steps & cross-module gates:**
1. **Lead** (MOD-17) raised, moved to WON → **forced conversion** creates the customer +
   **Inquiry** (MOD-19). *Gate:* WON cannot be a bare tick.
2. **Quotation** (MOD-03) built from the inquiry; money recalc (`crm_quote_recalc`);
   **pre-order checklist** (B2) must complete when require-all is on; approval chain.
   *Gate:* **blocked-client** (B1) refuses a quote for a BLOCKED client (non-manager).
3. Quote APPROVED → **Order/Contract** (MOD-18): unique contract number, two-signature
   opening, operations packet floated.
4. **Call** (MOD-04) raised from the won quote (carries client/contract/SBU); forwarded to the
   executing branch. *Gate:* **master-gap** check at first forward.
5. **Job** (MOD-05) allocated: WHO required, executing-office-only, **contract cover gate**
   (expiry/quantity), **competence + impartiality + identity + (later) equipment** gates,
   scheduling engine resolves dates.
6. **Report** (MOD-06) created against the job — twin-guard + **PO-lock**; filled; photos with
   captions.
7. **Submit** → **Vetting** (MOD-07) → auto-forward on VETTED → **Approval** → **Issue**
   (MOD-08): QA-critical + calibration hard-block gates; immutability (seal/freeze/signatures).
8. **Release Note** (MOD-08): only from an issued report, flag ticked, blockers clear
   (HWP/NCR/client-acceptance).
9. **Invoice** (MOD-09) raised from the closed job; GST tax split; payment recorded → feeds
   **Profit** (MOD-32).

**Expected:** money is identical at every hand-off (quote total = order = invoice to the
paisa; PDF = screen = export); every gate fires server-side on a crafted bypass; the issued
report verifies genuine on `/verify` (MOD-29).
**Exercises:** MOD-03,04,05,06,07,08,09,15,18,32 + gates 21,23,24,25,26.
**Watch (from module DEF/GAP):** GAP-QUOTES-001 (edit after approval), GAP-CON-002 (quantity
unit), GAP-INV-001/002 (GST split, numbering immutability), GAP-PRF-001/002 (figure
consistency into profit).

---

## Thread E2E-2 — Vetting-before-approver & the auto-forward (the control W1–W5)

**Persona chain:** P-INSP → P-VET → P-APPR → P-FINAL.
**Precondition:** `vetting_gate_required` on; an inspector→approver map or a form-picked
approver.

**Steps:** submit locks editing (W1) → status VETTING, chain deferred (W2) → vetter uses the
**side-by-side vet-review** (checklist vs the actual report) → VETTED **auto-forwards**, builds
the chain, notifies the approver (W2) → approver (may be a manager/coordinator/inspector/senior
inspector — W5) approves → **lock on approval + no duplicate report for the same items/PO**,
but **multiple POs same vendor same day allowed** (W3) → finalize (W4 gating).

**Expected:** editing is impossible once submitted; the report cannot reach an approver without
being vetted; an unresolved approver blocks the auto-forward with guidance (no dead-end); a
second report for a settled PO is refused; the pending queue (W6) shows the task on **every**
dashboard.
**Exercises:** MOD-06,07,08,34 (W6) + access MOD-02.
**Watch:** GAP-APPR-001 (no self-approval — the independence seam, MOD-25 GAP-IMP-001),
GAP-VET-001 (single clean chain), GAP-IDEMS-001 (PO-lock on crafted POST).

---

## Thread E2E-3 — Release gated by the quality spine (HWP · NCR · client acceptance)

**Persona chain:** P-INSP → P-QA → P-CLIENT → P-FINAL.
**Precondition:** an issued inspection report with an open hold point and/or an open NCR;
`rn_require_client_acceptance` on.

**Steps:** the report issue **derives hold/witness points** (MOD-21) from its activity tables;
an audit/finding **raises an NCR** (MOD-12); the client **accepts/rejects** on the portal
(MOD-10, `rcr_decide` → `client_decision`). The **Release Note** button (MOD-08) stays
**disabled** while any blocker stands (open HWP, open NCR, client not accepted / rejected), each
reason spelled out; once all clear + the RN flag ticked → RN raiseable.

**Expected:** the RN cannot be raised (button *and* server) until the spine is clear; a master
override is audited; a rejected report can never release.
**Exercises:** MOD-08,10,12,21 + settings MOD-14.
**Watch:** GAP-HWP-001 (master bypass), GAP-RN-001/004 (blockers on POST; client-acceptance
authenticity), GAP-NCR-001 (MAJOR→CAPA close), GAP-PORTAL-003 (decision authenticity).

---

## Thread E2E-4 — Nonconformity → Corrective action → effectiveness (§8.7)

**Persona chain:** P-QA → P-OWNER → P-QM.
**Steps:** an NCR is raised (any source, one way in — MOD-12) → MAJOR ⇒ **escalate to CAPA**
(MOD-13) → root cause + method + **similar-occurrence check** + actions with owners →
completion → **effectiveness verification** → close. The NCR **cannot close until its CAPA is
closed**; the CAPA **cannot close without a verified-effective outcome**; an ineffective CAPA
becomes CLOSED_FAILED + a follow-on action.

**Expected:** no MAJOR NCR closes without a closed, verified-effective CAPA; every step is
evented.
**Exercises:** MOD-12,13,22,28 (findings→CAPA), MOD-11 (vendor NCR response loop).
**Watch:** GAP-NCR-001, GAP-CAPA-001/003, GAP-CMP-003 (validated CAPA linkage).

---

## Thread E2E-5 — Personnel gates at allocation (competence · impartiality · identity)

**Persona chain:** P-COORD → P-MANAGER.
**Precondition:** the inspection pack on; an inspector with a lapsed mandatory cert, an open
impartiality threat, or a missing site document.

**Steps:** allocate the job (MOD-05) → `work.assign` fires all gates: **competence** cert-lapse
(overridable with a recorded reason), **authorisation** scope (not overridable),
**impartiality** open/unacceptable threat (not overridable), **site-doc** (overridable with a
reason). At **issue** (MOD-06/08), an unauthorised signatory = warn + auto-NCR; an
out-of-calibration instrument = **hard block**.

**Expected:** each gate blocks a crafted allocation; overrides are authority-checked +
reason-logged; the calibration block is non-overridable by any role.
**Exercises:** MOD-05,23,24,25,26 + MOD-12 (signatory NCR).
**Watch:** GAP-CMP-002 (scope-match), GAP-CMP-004 (fail-open), GAP-IMP-001 (self-approval),
GAP-ID-003 (blank-site bypass), GAP-EQ-001 (work-date fallback).

---

## Thread E2E-6 — Multi-day deputation → close discipline (§WO-6/8/9)

**Persona chain:** P-COORD → P-INSP → P-MANAGER.
**Steps:** a MONTHLY/CONTINUOUS engagement resolves working-day dates (MOD-05); per-visit
inspectors; site **check-in** arrival/departure (MOD-31); each visit day closed with its
report; the **final day** needs the Final Inspection Report + Release Note on file; bills for
chargeable heads; then the job closes. A **grace-period lock** freezes an un-closed job.

**Expected:** the job cannot close while any visit day is open, the final docs are missing, a
chargeable-head bill is absent, or a required site check-in is missing (unless a manager
overrides with a reason); a locked job accepts only uploads.
**Exercises:** MOD-05,06,08,09 (bills),30,31.
**Watch:** GAP-VCH-001 (close-replay double-claim), GAP-REC-001/002 (site gate default, GPS
spoof), GAP-JOBS-002 (close idempotency).

---

## Thread E2E-7 — Client & vendor portals (external isolation)

**Persona chain:** P-CLIENT, P-VENDOR (external) + P-COORD (admin).
**Steps:** a client is invited (magic token), signs in (clean session), sees **only their
own** calls/reports/invoices (MOD-10) and **accepts/rejects** a report; a vendor is invited
(MOD-11), sees only **VENDOR_VISIBLE** reports + the NCRs raised to them, and **responds** to a
nonconformity (→ QA decision). Visibility is governed by the classification engine (MOD-27).

**Expected:** no cross-tenant leak — a crafted id for another party's report/invoice/NCR
returns 404 on every route incl. the PDF endpoint; invites are single-use + expiring; the
report decision cannot be spoofed to clear the RN gate.
**Exercises:** MOD-10,11,12,27 + MOD-08 (acceptance→RN).
**Watch:** GAP-PORTAL-001 (Critical: isolation), GAP-VP-001 (Critical: visibility), GAP-VP-003
(NCR response auth), GAP-CONF-003 (fail-closed visibility).

---

## Thread E2E-8 — Vendor qualification loop

**Persona chain:** P-QA → P-VENDOR.
**Steps:** a **Vendor Assessment/Audit** (MOD-06) is issued against a vendor → the profile
status is **derived** (recommendation/score band), valid-until set (MOD-16); a VAR's Major
findings **raise NCRs** (MOD-12) shared to the vendor portal (MOD-11) for response; the vendor
360 aggregates reports/NCRs/complaints; requalification reminders fire on valid-until.

**Expected:** status is only set by an issued assessment or an authorised reason-logged manual
change; re-issuing does not double-log the timeline; an expired qualification is surfaced.
**Exercises:** MOD-06,11,12,16.
**Watch:** GAP-VEN-001/002/003.

---

## Thread E2E-9 — Evidence & tamper-evidence (issue → verify → audit)

**Persona chain:** P-FINAL → public verifier → P-MASTER.
**Steps:** issue seals the content (`content_seal`), freezes presentation, snapshots
signatures, mints a **verify code** (MOD-29); the public **`/verify`** answers genuine/altered/
evidence-on-site without leaking confidential content; every action is sealed into the
**hash-chained audit trail** (MOD-28), verified by `idems_audit_verify`; the data-control
integrity run includes the `audit_chain` check.

**Expected:** an unaltered issued report verifies genuine; a body tamper reads altered; the
audit chain detects interior edits/deletions.
**Exercises:** MOD-06,08,28,29.
**Watch:** GAP-DC-001 (unkeyed seal recomputable), GAP-AUD-002 (tail-deletion undetectable),
GAP-AUD-003 (QMS records unsealed).

---

## Cross-thread integrity checks (the "same figure" contract)

| Check | Threads | Expected | Watch |
|---|---|---|---|
| Money identical quote→order→invoice→profit, to the paisa, across screen/PDF/export | E2E-1 | Zero divergence | GAP-QUOTES-003, GAP-INV-001, GAP-PRF-002 |
| Profit identical on per-job / call-profit / SBU-P&L / MIS | E2E-1 | Same figure everywhere (the design's own promise) | **GAP-PRF-001/002/003, GAP-OVH-001** |
| Inter-office credit expected == received, per job | E2E-1,6 | Reconciles | GAP-REC-003, GAP-CON-002 |
| No control bypass on a crafted POST (gates are server-side, not hidden buttons) | all | Refused | GAP-IDEMS-002, GAP-APPR-002, GAP-RN-001, GAP-CLI-001 |
| No cross-tenant data leak (portals) | E2E-7 | 404, no data | **GAP-PORTAL-001, GAP-VP-001** |
| Tamper-evidence holds after issue | E2E-9 | Detected | **GAP-DC-001, GAP-AUD-002** |

---

## Exit conditions for a release sign-off

A release is **Complete** when every thread passes AND these cross-module exit conditions are
closed/accepted:

1. **Money fidelity** — E2E-1 reconciles to the paisa across quote→invoice, and profit agrees
   across all four screens (close GAP-PRF-001/002/003, GAP-INV-001).
2. **Control enforcement on crafted POSTs** — every gate in E2E-2/3/5 refuses a bypass, not
   just a hidden button (close GAP-IDEMS-002, GAP-APPR-002, GAP-RN-001).
3. **External isolation** — E2E-7 leaks nothing cross-tenant (close GAP-PORTAL-001,
   GAP-VP-001 — both **Critical**).
4. **Independence** — no self-approval where independence is required (close GAP-APPR-001 /
   GAP-IMP-001; move complaint/audit independence to id-based — GAP-CMP-001, GAP-AUD-001).
5. **Evidence integrity** — the issued-report seal cannot be forged and the audit chain
   detects tail deletion (close GAP-DC-001, GAP-AUD-002).

Until these are verified/closed, the system is a **strong release candidate with conditions** —
the operational spine and the accreditation controls are present and mostly enforced
server-side; the residual risk is concentrated in cross-module money consistency and a handful
of enforce-on-POST / isolation / independence items, all itemised in the module reports and
consolidated in **Prompt 5 (Gap, Risk & Readiness)**.

---

## Traceability

Each thread → the module reports it exercises → their TC-* and DEF/GAP ids → this report's
Exit conditions → Prompt 5's risk register. The automated suite (`php tests/run.php`, 1532
passing, 3 accepted pre-existing NCDCA release-dependency failures) is the unit-level evidence
under these threads.
