# Inspection Ops — Testing & Documentation Governance Pack

A **controlled, repeatable** system for testing and documenting the whole
application, module by module, without ever relying on a single giant pass that
would truncate, drift in depth, or miss screens.

It is built as **five master prompts plus a reusable module generator**, executed
in a fixed order so that everything traces back to one locked inventory:

> **Master Inventory → Module → Sub-module → Screen → Section → Field → Button →
> Function → Test Case → Result → Defect → Gap → Recommendation**

Every test case, defect and recommendation can be walked back up that chain to the
exact field and screen it came from, and every screen in the inventory can be
walked down to the test cases that cover it. Nothing floats unattached.

---

## The files in this pack

| File | Prompt | Run it… |
|------|--------|---------|
| [`prompt-1-discovery-inventory.md`](prompt-1-discovery-inventory.md) | **1 — Application Discovery & Master Screen/Function Inventory** | **First.** Once. Produces the master inventory, which is then **locked**. |
| [`prompt-2-testing-governance.md`](prompt-2-testing-governance.md) | **2 — Testing Framework & Test Governance** | After the inventory is locked. Once. Establishes IDs, severities, the RTM, the defect lifecycle, entry/exit criteria. |
| [`prompt-3-module-doc-generator.md`](prompt-3-module-doc-generator.md) | **3 — Individual Module Word Document Generator** | **Once per module** in the inventory (`Run Prompt 3 for "Call Registration"`, then `"Scheduling"`, …). The core of the pack. |
| [`prompt-4-e2e-tpia-workflows.md`](prompt-4-e2e-tpia-workflows.md) | **4 — End-to-End TPIA Workflow Testing** | After the modules are documented. Proves the company-wide journeys work when the modules are joined up. |
| [`prompt-5-gap-readiness-audit.md`](prompt-5-gap-readiness-audit.md) | **5 — Final Gap / Risk / Readiness Assessment** | Last. Rolls everything into a go / no-go readiness verdict. |
| [`master-inventory-baseline.md`](master-inventory-baseline.md) | *(reference)* | A **pre-filled baseline** of the modules, roles, statuses and cross-cutting subsystems that are known to exist in this codebase. Use it to **validate** what Prompt 1 discovers — anything the AI misses against this baseline is itself a finding. |

> **Format note.** Prompts 1, 3, 4 and 5 are written to emit **Microsoft Word (.docx)**
> deliverables (they are the auditable record a TPIA hands to an assessor). Prompt 2
> emits the governance workbook. The prompts themselves are plain text you paste to
> the AI developer/tester; the *outputs* are the Word documents.

---

## Execution runbook (do it in this order)

1. **Run Prompt 1.** Get back the *Master Application Screen & Function Inventory*.
2. **Validate it** against [`master-inventory-baseline.md`](master-inventory-baseline.md).
   Add anything missing. **Lock v1.0** of the inventory (date + version it; every
   later document cites this version).
3. **Run Prompt 2.** Get back the *Test Governance* (ID scheme, RTM skeleton,
   severity/priority matrix, defect workflow, environments, roles, test data,
   entry/exit criteria). Lock it.
4. **Run Prompt 3, once per module**, in dependency order (masters and access first,
   then the operational spine, then the peripheral modules). Each run produces one
   module Word document. Append each module's test cases to the RTM.
5. **Run Prompt 4.** Get back the *End-to-End TPIA Workflow* document — the real
   business journeys crossing many modules.
6. **Run Prompt 5.** Get back the *Gap, Risk & Readiness Assessment* — the roll-up
   and the go / no-go call.
7. **Re-run only what changed.** When a module's code changes, re-run Prompt 3 for
   that one module and re-run the E2E journeys it touches. The rest stays locked.

**Suggested module order for step 4** (each is a Prompt 3 run):
Masters → Users & Access (roles/permissions/scope) → Settings & Terminology →
Clients → Vendors → Leads → Quotations (CRM) → Orders/Contracts → Call
Registration → Scheduling/Allocation → Inspection Execution (field) → Inspection
Reporting (IDEMS) → Vetting & Approval → Release Notes → Hold/Witness Points →
Nonconformities (NCR) → Corrective Action (CAPA) → Complaints & Appeals →
Equipment & Calibration → Competence & Authorisation → Impartiality → Identity
Documents → Confidentiality → Internal Audits & Management Review → Client Portal
→ Vendor Portal → Vouchers/Expenses → Attendance & Reconcile → Invoicing →
Profitability → Dashboards & Analytics (TAPI) → Recruitment/Workforce → Deputation
& Site Operations → Data & Information Control → Licensing & Seats → Audit Trail &
Immutability → Notifications/Email → Offline/Mobile (PWA) → AI-Assist Features.

---

## The coverage rule (do not skip this)

> **A module is NOT "100% tested" because every visible screen was clicked.**

A module is **fully covered** only when **all** of the following dimensions have
been examined, evidenced and signed off. Prompt 3 forces every one of them.

**Functional core**
1. **UI / Screens** — every screen, tab, panel, modal, empty state, loading state.
2. **Fields** — every field: type, label, default, required/optional, min/max,
   format mask, dependent/cascading behaviour, prefill/carry-forward.
3. **Buttons / Actions** — every button, link and menu item; what it does; where it
   lands; disabled/enabled conditions.
4. **Functions / Logic** — every calculation, derivation, auto-fill, numbering rule.
5. **Statuses / Lifecycle** — every state and every legal transition (and the
   illegal ones that must be refused).
6. **Validation** — field-level and form-level rules; the exact error messages.
7. **Negative & edge cases** — empty, huge, malformed, duplicate, boundary,
   out-of-order, double-submit, back-button, expired session, concurrent edit.

**Control & governance**
8. **Roles & Permissions** — every role's view/edit/act rights on the module,
   including what each role must NOT see or do.
9. **Data scope** — office/branch and business-unit scoping; a user sees only their
   scope; cross-scope leakage is a defect.
10. **Settings** — every setting that changes the module's behaviour, on and off.
11. **Business Workflow** — the intended real-world process end to end, in role.
12. **Cross-Module Integration** — what flows in from and out to other modules;
    what breaks upstream/downstream if this module misbehaves.
13. **Data Integrity** — no orphans, no double-count, referential correctness,
    idempotency (no duplicate on resend/second click), correct rollups.
14. **Audit Trail & Immutability** — every change is logged with who/when; a
    finalised record is immutable; the tamper-evident hash chain verifies.

**Fitness for purpose**
15. **Reports & Outputs** — on-screen registers, PDF, Word (.docx), QR/verify,
    exports — content, layout and figures all correct and consistent.
16. **TPIA Operational Suitability** — does it actually fit how a third-party
    inspection agency works (ITP/QAP, hold/witness, release notes, ISO/IEC 17020)?
17. **Management Usefulness** — does a manager/ED/SBU-head get the decision
    information they need (pending work, TAT, profitability, utilisation, risk)?
18. **UI / UX** — clarity, least-clicks, no dead ends, one obvious next action,
    consistent language, mobile/field usability, accessibility.
19. **Gap Analysis** — what is missing, weak, confusing or risky; the recommendation.

**Cross-cutting technical (added — these are where "working" systems still fail)**
20. **Security** — authorisation on every action (not just the UI), CSRF, file
    access authorisation, injection, and no privilege escalation via crafted input.
21. **Data migration / Import** — spreadsheet/CSV imports build correct records and
    relationships; malformed rows are rejected with a reason, not silently dropped.
22. **Notifications / Email** — the right people are told at the right step; nothing
    is sent to the wrong recipient; failures never block the workflow.
23. **Offline / Mobile (PWA)** — capture works with poor/no signal; queued actions
    sync; nothing is lost.
24. **AI-assist features** — behave when enabled AND when disabled; never invent or
    alter facts, numbers or dispositions; degrade gracefully with no provider.
25. **Licensing & Seats** — seat counts and module licences enforce correctly at the
    limit and one past it.
26. **Multi-tenant & Terminology** — the app is generic (all TPIAs, not one agency):
    no hardcoded agency names; renamed terminology flows to every screen and output.
27. **Time, timezone & Financial Year** — dates, TAT, FY boundaries and “controlled
    timestamps” are correct and not spoofable by a device clock.
28. **Performance & Scale** — registers and dashboards stay usable at realistic
    volumes; no run-away query.
29. **Backup / Restore / Continuity** — the data can be backed up and restored;
    shared-hosting install survives and reports its own failures.

For each dimension, Prompt 3 records: **Covered? (Y/N/Partial) · Evidence · Test
case IDs · Findings**. A module with any dimension unexamined is **Not Complete**,
regardless of how many screens were clicked.

---

## Deliverable set (what you end up holding)

- **1 ×** Master Application Screen & Function Inventory (locked, versioned).
- **1 ×** Test Governance workbook (IDs, RTM, severity, defect lifecycle, criteria).
- **N ×** Module Test & Documentation reports (one Word doc per module).
- **1 ×** End-to-End TPIA Workflow Test report.
- **1 ×** Final Gap, Risk & Readiness Assessment (with the go / no-go verdict).
- **1 ×** Requirements/Coverage Traceability Matrix (RTM) spanning all of the above.

Every document carries: title, app version/build, environment, date, author,
inventory version it traces to, and a revision history.
