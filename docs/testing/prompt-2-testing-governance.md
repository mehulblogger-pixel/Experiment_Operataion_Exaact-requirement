# PROMPT 2 — Master Testing Framework & Test Governance

> **Run after the Master Inventory is locked. Once.** Its output is the governance
> spine every module document plugs into: how test cases are numbered, how defects
> are graded and tracked, how coverage is measured, and when the app is allowed to
> be called "ready".

---

## Paste this to the AI developer/tester

You are the QA governance lead for **Inspection Ops** (multi-tenant TPIA platform,
ISO/IEC 17020-aligned). The *Master Application Screen & Function Inventory
v[VERSION]* is locked and attached. Produce the **Test Governance** document — the
framework that Prompt 3 (per-module), Prompt 4 (end-to-end) and Prompt 5 (readiness)
all obey. Do not test anything yet.

### Output — one Word (.docx) document titled *"Inspection Ops — Test Governance v1.0"*

Include every section below.

**1. Scope & objectives.** What is being verified (functional correctness, control &
governance, TPIA operational suitability, management usefulness, security, data
integrity, output fidelity) and what is explicitly out of scope this cycle.

**2. Test levels.** Component/handler, module (Prompt 3), integration & end-to-end
(Prompt 4), regression, and the standing automated suite (the repo's
`php tests/run.php` — treat its pass count as a gate; a drop is a release blocker).

**3. Identifier scheme (the traceability chain).** Fixed, human-readable IDs:
- Requirement / control: `REQ-<MOD>-nnn` (a requirement or ISO 17020 clause).
- Test case: `TC-<MOD>-<SCREEN>-nnn`.
- Defect: `DEF-<MOD>-nnn`.
- Gap/risk: `GAP-<MOD>-nnn`.
Each test case cites the Screen ID and Field/Button it exercises and the REQ it
proves. Each defect cites its failing test case. Each gap cites its evidence. This
realises: **Inventory → Module → Screen → Field/Button → Function → TC → Result →
Defect → Gap → Recommendation.**

**4. Test-case template** (the shape every case in every module doc uses):

| Field | Meaning |
|---|---|
| TC ID | `TC-<MOD>-<SCREEN>-nnn` |
| Title | one line |
| Dimension | which of the 29 coverage dimensions (see Governance §6) |
| Precondition | role, data, settings, state |
| Steps | numbered, unambiguous |
| Test data | exact values (incl. boundary/negative) |
| Expected result | observable, specific (incl. the exact message) |
| Actual result | filled at execution |
| Status | Pass / Fail / Blocked / N-A |
| Severity·Priority | see §5 |
| Evidence | screenshot/log/record ID |
| Linked REQ / Defect | IDs |

**5. Severity & priority matrix.**
- **Severity:** Blocker (data loss, wrong figure to client, control bypass,
  security), Critical (core workflow broken), Major (function wrong but
  work-around), Minor (cosmetic/wording), Info.
- **Priority:** P1 fix-now … P4 backlog.
- Rule: anything that lets a **finalised/immutable record change**, an
  **unauthorised action succeed**, a **wrong number reach a client/invoice**, or an
  **evidence timestamp/geo be spoofed** is **Blocker/P1** by definition.

**6. Coverage model — the 29 dimensions.** Restate the coverage rule from the pack
README (UI, Fields, Buttons, Functions, Statuses, Validation, Negative/Edge, Roles &
Permissions, Data scope, Settings, Business Workflow, Cross-Module Integration, Data
Integrity, Audit Trail & Immutability, Reports/Outputs, TPIA Operational
Suitability, Management Usefulness, UI/UX, Gap Analysis, Security, Data
Migration/Import, Notifications/Email, Offline/Mobile PWA, AI-assist, Licensing &
Seats, Multi-tenant & Terminology, Time/Timezone/FY, Performance & Scale,
Backup/Restore/Continuity). Define, for each, **what "covered" means** and the
**minimum evidence** required. A module is **Not Complete** if any dimension is
unexamined.

**7. Requirements/Coverage Traceability Matrix (RTM).** Define the master RTM
columns and seed it from the inventory: every Screen × the dimensions → the TC IDs
that cover it → pass/fail → open defects. The RTM is appended to as each module doc
is produced; a Screen with an uncovered mandatory dimension is visible as a hole.

**8. Defect lifecycle.** New → Triaged → In-progress → Fixed → **Retested** →
Closed / Reopened / Deferred(with reason). No defect closes without a retest
reference. Duplicates link to the master.

**9. Roles for testing (personas).** Concrete test users for **every** role in the
inventory's role matrix — including Master Admin, Business Director, SBU Head,
Branch Manager, Operation Manager, Coordinator, **Senior Inspector**, **Inspector**,
Finance, and the external **client-portal** and **vendor-portal** users. Each
persona lists its office/BU scope. Permission tests must be run **as the persona**,
and must include **negative** checks (a role attempting what it must not do — via UI
and via a crafted direct request).

**10. Test environments & data.** A clean, seedable environment (note the repo's
throwaway-DB test harness and the browser boot pattern); a representative dataset
covering multiple offices/business-units, multiple clients/vendors, multiple POs per
vendor per day, multi-day jobs, and each lifecycle state. State the reset procedure
between runs and how NOT to touch production data.

**11. Entry & exit criteria.**
- *Entry (per module):* inventory locked, personas provisioned, data seeded,
  automated suite green.
- *Exit (per module):* all 29 dimensions examined; no open Blocker/Critical; every
  Major triaged; RTM updated; module Word doc signed off.
- *Exit (release / Prompt 5):* all modules exited; end-to-end journeys pass; no open
  Blocker; automated suite green and not regressed; readiness verdict recorded.

**12. Non-functional gates.** Security (authorisation-on-action, CSRF, file-auth,
injection), audit-trail integrity (hash chain verifies), data scoping (no
cross-office leak), output fidelity (PDF/Word/QR figures match source), timestamp/geo
integrity, performance at volume, and licensing enforcement — each with a
pass/fail gate.

**13. Sign-off.** Who signs a module complete, who signs the release ready, and the
record kept (aligned to ISO/IEC 17020 documented-control expectations).

### Rules
- Reuse the inventory's IDs; do not invent modules.
- Everything measurable: a criterion a reader cannot objectively check is not a
  criterion.
- Keep the RTM and the severity rules **machine-checkable** where possible so a
  coverage hole is obvious, not buried in prose.

Output only the governance document.
