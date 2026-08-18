# PROMPT 3 — Individual Module Word Document Generator

> **The core of the pack. Run once for each module** named in the locked Master
> Inventory: `Run Prompt 3 for the exact module "Call Registration"`, then
> `"Scheduling"`, then `"Inspection Execution"`, and so on. One run → one Word
> document. Never combine modules in a single run.

---

## Paste this to the AI developer/tester (fill in `[MODULE]`)

You are testing and documenting **exactly one module — `[MODULE]`** — of
**Inspection Ops** (multi-tenant TPIA platform, ISO/IEC 17020-aligned). The locked
*Master Inventory v[VERSION]* and the *Test Governance v[VERSION]* are attached; use
their IDs, personas, severity matrix and RTM. **Do not test or document any other
module** except where this module integrates with it (Section H).

Read the real code for `[MODULE]` (its route handlers, its views, its tables and its
status logic) before writing. Do not rely on the screen looking right — exercise the
function and the data behind it. Where the module has a setting, test it **both on
and off**. Where it has roles, test **as each role**, including the negatives.

### Output — one Word (.docx) document titled *"Inspection Ops — [MODULE] — Test & Documentation Report"*

Cover page: module name + code, inventory version traced, app build/commit,
environment, personas used, date, author, revision history. Then:

**A. Module overview.** Purpose, where it sits in the TPIA operating flow, the
sub-modules and screens it contains (from the inventory), and the entities/tables it
owns. A simple diagram of its screens and states is welcome.

**B. Screen-by-screen catalogue.** For **every** screen in this module:
- Screen ID, route, purpose, roles.
- **Every field** — name, type, required?, default, validation, dependency/cascade,
  prefill/carry-forward source. Table (grid) fields: every column and its type.
- **Every button/link/menu action** — label, what it does, where it lands, and its
  **enabled/disabled/hidden** conditions.
- Empty state, loading state, and locked/read-only state.

**C. Field & validation test cases.** Positive, boundary and **negative** for each
field and for the form as a whole (empty, too long, wrong format, duplicate,
injection-y input, double-submit, back-button, expired session, concurrent edit).
Record the **exact expected message**.

**D. Function & calculation test cases.** Every derivation, auto-fill, numbering
rule, rollup and total — with inputs and the exact expected output, including
rounding, currency, dates, TAT and FY boundaries.

**E. Status & lifecycle test cases.** Every state and every **legal** transition
(and proof that **illegal** transitions are refused). Who can drive each transition;
what becomes locked/immutable; what notifications fire.

**F. Roles, permissions & data scope.** The role × action matrix **for this module**,
each cell proven by a test case run **as that persona** — including the negatives
(a role attempting a forbidden view/edit/action **via the UI and via a crafted
direct request/URL**, expecting refusal, not a silent allow). Prove **office/branch
and business-unit scoping**: a user sees only their scope; cross-scope data is never
returned.

**G. Settings.** Every setting that changes this module's behaviour, tested **on and
off**, with the observable difference recorded.

**H. Cross-module integration.** What flows **in** to this module and **out** of it,
and the effect if the neighbour misbehaves. Name the upstream and downstream modules
and prove the hand-offs (e.g. Call → Job → Schedule → Inspection → Report → Vetting
→ Approval → Release Note → Invoice; or Report → NCR → CAPA; or Quote → Order →
Call). Prove **idempotency** (no duplicate on resend / second click).

**I. Data integrity & audit trail.** No orphans, no double-count, correct referential
links and rollups. **Every change is logged** with who/when. A **finalised/immutable**
record cannot be altered, and the **tamper-evident hash chain verifies** after normal
use (and is shown to detect a deliberate tamper). Controlled timestamps and, where
evidence is captured, **geo/EXIF integrity** (a device clock or spoofed location does
not become the record of truth).

**J. Reports & outputs.** Every artefact this module produces or contributes to —
on-screen register, **system PDF**, **company Word (.docx)**, Release Note,
certificate, **QR verify** page, exports — checked for correct content, layout and
**figures that match the source data**. Photos/evidence render (all formats); a
non-renderable format shows a labelled placeholder, never a blank; captions print.

**K. Negative, edge & resilience.** Beyond field validation: empty module (no
records), very large volumes, interrupted/duplicate submits, poor/no network
(offline capture + later sync where applicable), missing optional module,
AI-provider absent (AI features degrade, never block; never alter facts).

**L. TPIA operational suitability.** Does this module fit how a third-party
inspection agency actually works — ITP/QAP scope, hold/witness points, stage vs
final inspection, release/discrepancy notes, multiple POs per vendor per day,
revisions, and ISO/IEC 17020 impartiality/competence/records expectations? Note any
operational mismatch.

**M. Management usefulness.** Does a manager / ED / SBU head / branch manager get the
decision information this module should give them (pending work in list form, TAT,
overdue, profitability/utilisation, risk)? Is it visible on their dashboard?

**N. UI / UX & accessibility.** Clarity, least-clicks, one obvious next action, no
dead ends, consistent terminology (and that renamed terminology flows through),
mobile/field usability, and basic accessibility (labels, contrast, keyboard).

**O. Security review (module-level).** Authorisation enforced on the **action**, not
just hidden in the UI; CSRF on state-changing posts; file/download authorisation;
no injection; no privilege escalation via crafted input; sensitive data (salary,
cost, client identity) shown only to entitled roles.

**P. Coverage scorecard (mandatory).** A table of the **29 coverage dimensions**
(Governance §6) with: **Covered? (Y / Partial / N) · Evidence · Test-case IDs ·
Findings**. The module is **Not Complete** if any dimension is N or unexamined —
state this verdict explicitly.

**Q. Defects & gaps.** Every defect (`DEF-<MOD>-nnn`: title, severity·priority,
steps, expected vs actual, evidence, linked TC) and every gap/risk
(`GAP-<MOD>-nnn`: description, impact, recommendation, effort). Rank by severity.

**R. Traceability appendix.** The slice of the RTM for this module: Screen × dimension
→ TC IDs → pass/fail → open defects. Plus a one-line **module verdict**: *Complete /
Complete-with-defects / Not Complete*, and what must close before it can be Complete.

### Rules
- **One module only.** Depth over breadth. If the module is large, cover it fully
  and say so — never thin out to fit.
- **Evidence, not assertion.** "Works" is not a result; the observed output, record
  ID, message or screenshot is.
- **Test as the persona**, and always include the negative-permission and
  crafted-request checks — a control that only hides a button in the UI but allows
  the action on submit is a **Blocker**.
- **Reuse IDs** from the inventory and governance; append this module's TCs to the
  master RTM.
- **Do not claim 100%** on screens-clicked alone; the verdict comes from the 29-
  dimension scorecard.
