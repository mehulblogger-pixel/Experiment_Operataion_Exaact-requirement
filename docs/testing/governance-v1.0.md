# Inspection Ops — Test Governance · v1.0

> **Output of Prompt 2.** The framework every module document (Prompt 3), the
> end-to-end report (Prompt 4) and the readiness audit (Prompt 5) obey. It traces to
> **Master Inventory v1.0** and does not invent modules beyond it.

| | |
|---|---|
| **Application** | Inspection Ops — multi-tenant TPIA platform (ISO/IEC 17020-aligned) |
| **Traces to** | Master Screen & Function Inventory **v1.0** |
| **Automated baseline** | `php tests/run.php` = **1532 passed, 3 failed** (3 pre-existing NCDCA release-dependency tests, accepted) |
| **Governance version** | **v1.0** — lock before running Prompt 3 |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp on lock) | QA (Prompt 2) | Initial governance. |

---

## 1. Scope & objectives

**In scope:** functional correctness; control & governance (roles, scope, audit trail,
immutability); TPIA operational suitability (ISO/IEC 17020); management usefulness;
security; data integrity; output fidelity (PDF/Word/QR); the 29 coverage dimensions
for every module in the inventory; the 13 end-to-end journeys.

**Out of scope this cycle (declare explicitly):** load/soak beyond "realistic volume"
sampling; penetration testing beyond the module-level security checks below;
third-party integration internals (MGH Books, cPanel, Razorpay, SSO IdP) beyond the
app's own request/response handling; native mobile apps (the app is a PWA).

**Objective:** a defensible, traceable verdict — per module and for the release — that
a reader can audit from headline down to the failing field.

---

## 2. Test levels

| Level | What | How |
|---|---|---|
| Component / handler | A single function or route handler | Targeted checks; the repo's throwaway-DB harness (`php tests/run.php`) where a unit exists |
| Module (Prompt 3) | One module, all 29 dimensions | Manual + scripted, as each persona; one Word doc per module |
| Integration & E2E (Prompt 4) | Cross-module business journeys | As the changing chain of personas |
| Regression | The standing automated suite | `php tests/run.php` — **pass count is a gate**; a drop below 1532 (excluding the 3 accepted) is a **release blocker** |
| Non-functional | Security, performance, data integrity, output fidelity, licensing, timestamps | Gates in §12 |

---

## 3. Identifier scheme (the traceability chain)

Fixed, human-readable IDs. Every artefact cites the one above it.

| ID | Meaning | Example |
|---|---|---|
| `REQ-<MOD>-nnn` | A requirement or ISO/IEC 17020 control this module must satisfy | `REQ-IDEMS-014` "an issued report is immutable" |
| `TC-<MOD>-<SCREEN>-nnn` | A test case against a screen/field/button | `TC-IDEMS-document-032` |
| `TC-E2E-<Jn>-nnn` | An end-to-end journey case | `TC-E2E-J4-007` |
| `DEF-<MOD>-nnn` | A defect | `DEF-IDEMS-005` |
| `GAP-<MOD>-nnn` | A gap / risk / recommendation | `GAP-IDEMS-002` |

Chain realised: **Inventory → Module → Screen → Field/Button → Function → Test Case →
Result → Defect → Gap → Recommendation.** Module codes are the inventory's `MOD-*`;
screen slugs are the inventory's `SCR-*` suffix.

---

## 4. Test-case template

Every case in every module document uses exactly these fields:

| Field | Rule |
|---|---|
| **TC ID** | `TC-<MOD>-<SCREEN>-nnn` |
| **Title** | one line, states the check |
| **Dimension** | which of the 29 (see §6) |
| **Requirement** | the `REQ-*` it proves |
| **Precondition** | persona (role), data, settings state, record state |
| **Steps** | numbered, unambiguous, reproducible |
| **Test data** | exact values, including boundary and negative |
| **Expected result** | observable and specific — including the **exact message/state** |
| **Actual result** | filled at execution |
| **Status** | Pass / Fail / Blocked / N-A |
| **Severity · Priority** | see §5 |
| **Evidence** | screenshot / log line / record ID |
| **Linked** | REQ-*, and DEF-* if failed |

---

## 5. Severity & priority matrix

| Severity | Definition |
|---|---|
| **Blocker** | Data loss; a wrong figure reaches a client/invoice; a control is bypassed; a finalised/immutable record changes; an evidence timestamp/geo is spoofable; a security hole |
| **Critical** | A core workflow is broken (cannot submit/vet/approve/issue/invoice) |
| **Major** | A function is wrong but a work-around exists |
| **Minor** | Cosmetic, wording, layout |
| **Info** | Observation, no action needed |

| Priority | Meaning |
|---|---|
| P1 | Fix now — blocks the release |
| P2 | Fix this cycle |
| P3 | Scheduled |
| P4 | Backlog |

**Standing rule:** anything that lets a **finalised/immutable record change**, an
**unauthorised action succeed**, a **wrong number reach a client or invoice**, or an
**evidence timestamp/geo be spoofed** is **Blocker / P1** by definition — regardless of
how "unlikely" it seems.

---

## 6. Coverage model — the 29 dimensions

A module is **Not Complete** if any dimension is `N` or unexamined. For each, the
module doc records **Covered? (Y/Partial/N) · Evidence · TC IDs · Findings**.

| # | Dimension | "Covered" means… | Minimum evidence |
|---|---|---|---|
| 1 | UI / Screens | every screen, tab, panel, modal, empty/loading/locked state exercised | screenshots per state |
| 2 | Fields | every field's type, default, required, format, dependency, prefill checked | field table + cases |
| 3 | Buttons / Actions | every button/link/menu — effect, destination, enabled/disabled/hidden | action table + cases |
| 4 | Functions / Logic | every calc/derivation/auto-fill/numbering proven with inputs→output | function cases |
| 5 | Statuses / Lifecycle | every legal transition works, every illegal one refused | lifecycle cases |
| 6 | Validation | field- and form-level rules, exact messages | validation cases |
| 7 | Negative / Edge | empty, huge, malformed, duplicate, boundary, double-submit, back-button, expired session, concurrent edit | negative cases |
| 8 | Roles & Permissions | each role's allowed actions AND forbidden ones, as the persona | role matrix proven |
| 9 | Data scope | office/branch + BU scoping; no cross-scope leak | scope cases |
| 10 | Settings | each behaviour-changing setting on AND off | settings cases |
| 11 | Business Workflow | the real process end-to-end, in role | workflow walk-through |
| 12 | Cross-Module Integration | in/out hand-offs; upstream/downstream effects; idempotency | integration cases |
| 13 | Data Integrity | no orphans/double-count; referential correctness; rollups | integrity cases |
| 14 | Audit Trail & Immutability | every change logged; finalised = immutable; hash chain verifies | audit/chain evidence |
| 15 | Reports & Outputs | register/PDF/Word/QR/export content, layout, figures match source | output diff |
| 16 | TPIA Operational Suitability | fits ITP/QAP, hold/witness, stage/final, release notes, multi-PO, ISO 17020 | suitability notes |
| 17 | Management Usefulness | the decision info a manager/ED/SBU-head/branch-mgr needs; on their dashboard | usefulness notes |
| 18 | UI / UX & Accessibility | clarity, least-clicks, one next action, terminology, mobile, a11y basics | UX notes |
| 19 | Gap Analysis | what is missing/weak/confusing/risky + recommendation | gap register |
| 20 | Security | authorisation on the **action** not just the UI; CSRF; file-auth; injection; no escalation | security cases |
| 21 | Data migration / Import | imports build correct records+relations; malformed rows rejected with a reason | import cases |
| 22 | Notifications / Email | right recipient at the right step; failures never block workflow | notification cases |
| 23 | Offline / Mobile (PWA) | capture with poor/no signal; queued actions sync; nothing lost | offline cases |
| 24 | AI-assist | works on AND off; never invents/alters facts; degrades with no provider | AI cases |
| 25 | Licensing & Seats | seat/module limits enforce at the limit and one past it | licensing cases |
| 26 | Multi-tenant & Terminology | no hardcoded agency name; renamed terms flow to every screen+output | terminology cases |
| 27 | Time / Timezone / FY | dates, TAT, FY boundaries, controlled timestamps correct & unspoofable | time cases |
| 28 | Performance & Scale | registers/dashboards usable at realistic volume; no run-away query | volume sample |
| 29 | Backup / Restore / Continuity | data backs up and restores; shared-host install survives & reports failures | continuity check |

---

## 7. Requirements / Coverage Traceability Matrix (RTM)

One master matrix, appended to as each module doc is produced.

**Columns:** `Module | Screen (SCR-*) | Dimension (1–29) | REQ-* | TC IDs | Covered (Y/P/N) | Result (Pass/Fail/Blocked) | Open DEF-* | GAP-*`.

Seed one row per (Screen × applicable dimension) from the inventory; a row that stays
`Covered = N` at module exit is a visible hole. Prompt 5 rolls the RTM into the
coverage matrix (module × dimension, R/A/G).

---

## 8. Defect lifecycle

`New → Triaged → In-progress → Fixed → Retested → Closed` (or `Reopened`, or
`Deferred` with a written reason and risk acceptance). Rules:
- No defect closes without a **retest reference** (the TC re-run that passed).
- Duplicates link to the master defect; do not close silently.
- A **Blocker** cannot be Deferred for a release — it is fixed or the release does not go.
- Every defect cites the **failing TC** and carries **severity·priority** and evidence.

---

## 9. Testing personas (roles)

Provision one concrete test user per role, each with an explicit **office/branch** and
**business-unit** scope, plus the two external portal users. Permission tests run **as
the persona**, and every module must include **negative** checks (the persona
attempting a forbidden action **via the UI and via a crafted direct request/URL**,
expecting refusal — not a hidden button that still submits).

| Persona | Role | Scope | Notes |
|---|---|---|---|
| P-MASTER | MASTER_ADMIN | ALL / ALL | sees everything; the only override authority |
| P-ED | BUSINESS_DIRECTOR | ALL / ALL | read-only cross-business; dashboards |
| P-SBU | SBU_HEAD | ALL offices / OWN BU | may finalise; BU scope |
| P-BM | BRANCH_MANAGER | OWN office / ALL BU | edits ops; may finalise; manages branch users |
| P-BAM | BRANCH_APP_MANAGER | OWN / ALL | the only role that may edit locked timestamps |
| P-OM | OPERATION_MANAGER | OWN / OWN | ops + may finalise |
| P-AM | ASST_MANAGER | OWN / OWN | ops, no commercials |
| P-COORD | COORDINATOR | OWN / OWN | calls/jobs/reports; sees credit not salary/profit |
| P-BDM | BUSINESS_DEV_MANAGER | OWN / ALL | quotes only |
| P-MKT | MARKETING_MANAGER | ALL / ALL | quotes incl. approve |
| P-FIN | FINANCE | ALL / ALL | invoicing, sees money; no clients edit |
| P-SRINSP | SR_INSPECTOR | OWN / OWN | writes reports; may vet/finalise; can be a named approver |
| P-INSP | INSPECTOR | self / OWN | writes own reports; can be a named approver |
| P-CLIENT-Q | client-portal QUALITY | own company | accepts/rejects reports; no money |
| P-CLIENT-C | client-portal COMMERCIAL | own company | sees reports + invoices; cannot accept |
| P-VENDOR | vendor-portal | own company | own outcomes / external NCRs |

Each persona's exact permission set derives from `role_defaults` / `module_defaults`
(inventory §3) and the 147 `PERMISSIONS`.

---

## 10. Test environments & data

**Environment:** a clean, seedable instance. Two ways to stand one up (both in the
repo): the **throwaway-SQLite test harness** (`php tests/run.php` boots and tears down
its own DB) for scripted checks, and a **booted server** (`php -S … index.php`, first-run
`/setup-db` in SQLite mode, admin password set directly) for UI/persona testing. Reset
by restoring `data.sqlite` and removing `config.local.php`. **Never touch production.**

**Representative dataset (mandatory):** ≥2 offices and ≥2 business units; ≥3 clients
(incl. one on HOLD, one BLOCKED) and ≥3 vendors; **multiple POs for one vendor on one
day**; multi-day jobs with per-date inspectors; a report in **each** lifecycle state
(DRAFT, VETTING, UNDER_REVIEW, APPROVED, ISSUED, REJECTED); an issued report with an
open hold point, one with an open NCR, one client-rejected; a quotation at each status;
an invoice pre- and post-close; an expired calibration; a lapsed competence; an
undeclared conflict. This dataset exercises the gates the workflow depends on.

---

## 11. Entry & exit criteria

**Entry (per module):** inventory v1.0 locked; personas provisioned; representative
dataset seeded; automated suite green (1532/3-accepted).

**Exit (per module):** all 29 dimensions examined (scorecard has no unexamined cell);
no open Blocker or Critical; every Major triaged with an owner; RTM updated; the module
Word document signed off with a verdict.

**Exit (release / Prompt 5):** every module exited; the 13 E2E journeys pass with no
open Blocker; automated suite green and **not regressed**; non-functional gates (§12)
pass; the readiness verdict is recorded with its conditions.

---

## 12. Non-functional gates (each pass/fail)

| Gate | Pass criterion |
|---|---|
| **Security** | Authorisation enforced on every state-changing action (not just UI); CSRF token required on POST; file/download authorisation; no SQL/HTML injection; no privilege escalation via crafted input |
| **Audit & immutability** | Every change logged with who/when; a finalised report cannot be altered; the hash chain **verifies** and detects a deliberate tamper |
| **Data scope** | No cross-office / cross-BU data returned to a scoped role, in any list, export or crafted request |
| **Output fidelity** | Figures on register, PDF and Word match the source record; photos embed or show a labelled placeholder; captions print; QR resolves to the right report |
| **Timestamp / geo** | Server time is the record of truth; a device clock or spoofed location never becomes the recorded fact |
| **Performance** | Key registers and dashboards render within an acceptable time at the representative volume; no unbounded query |
| **Licensing** | Seat and module limits enforce exactly at the limit and one past it; a disabled module is refused everywhere |

---

## 13. Sign-off

| Event | Signs | Record kept |
|---|---|---|
| Module complete | QA lead + module owner | the module Word doc + RTM slice |
| Release ready | QA lead + product owner | the Prompt 5 readiness assessment |

Records are retained as controlled documents (ISO/IEC 17020 documented-control
expectation). Every document carries the app build/commit and the inventory version it
pertains to.

---

*End of Test Governance v1.0. Lock this, then run Prompt 3 once per module in the
inventory, starting with Masters → Users & Access → Settings, then the operational and
reporting spine.*
