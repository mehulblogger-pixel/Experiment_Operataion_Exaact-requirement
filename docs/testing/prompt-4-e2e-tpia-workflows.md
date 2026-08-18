# PROMPT 4 — End-to-End TPIA Workflow Testing

> **Run after the module documents are done.** A set of individually "green" modules
> can still produce a broken company. This proves the **business journeys that cross
> many modules** work, with the right role at each hop, the right data carried
> forward, and the right controls firing.

---

## Paste this to the AI developer/tester

You are proving **Inspection Ops** works **end to end** as a third-party inspection
agency would run it (ISO/IEC 17020). The locked inventory, governance and all module
reports are attached. Do **not** re-test individual fields here — test the **joined-up
journeys**: hand-offs between modules, data carried forward without re-keying,
role changes at each step, controls that must fire, and the state of every record at
the end.

### Output — one Word (.docx) document titled *"Inspection Ops — End-to-End TPIA Workflow Test Report"*

For **each journey** below, produce: an **actors-and-steps table** (step · role ·
screen · action · expected system state · data carried forward · notification ·
control fired), the **test cases** (`TC-E2E-<JOURNEY>-nnn`), the **cross-module
integrity checks**, and the **defects/gaps**. Run each journey **as the real chain of
personas** — the person changes at each hop; a step that the wrong role can perform
is a defect.

### The core journeys (cover all; add any the inventory reveals)

**J1 — Pre-order to order.** Lead → Quotation (with the **pre-order review checklist**
if enabled; **blocked-client** stop) → approval chain → send → **Order/Contract**
registered (contract number, no duplicate) → carried into a Call. Prove the
**delegation/value-based approval** routing and that a blocked client cannot be
ordered by a non-manager.

**J2 — Call to allocation.** Call registered (client, vendor, PO, ITP/QAP, dates,
reporting frequency, deliverables) → forwarded to executing office → **Job/deputation
allocated** (inspector(s) per date, multi-day, capacity/best-inspector suggestion) →
inspector notified. Prove **inter-office credit** when contracting ≠ executing office.

**J3 — Site execution.** Inspector: site check-in (geo), records the inspection,
captures **geo-tagged photos**, uses **QAP → scope auto-fill**, dictation/polish
where enabled → attendance/entry-exit → per-day closure. Prove nothing is lost with
**poor/no network** (offline capture + sync) and that evidence timestamps/geo cannot
be spoofed by the device.

**J4 — Reporting to issue (the spine).** Inspection Report drafted → **completeness
gate** → **submit locks editing** → **vetting authority** (side-by-side against the
report; checklist if enabled) → on vet, **auto-forwarded to the approver** →
approver (a **manager / coordinator / inspector / senior inspector**) approves →
**finalise & issue** → **automatic signature** stamped, record **immutable**, **QR**
verifiable. Prove: an inspector **cannot edit after submit**; a **release note is not
possible** until issued; **no second report for the same PO** (but a **different PO
for the same vendor on the same day is allowed**); a **revision** reopens correctly.

**J5 — Release Note.** From an **issued (accepted)** inspection report, marked
**"Release Note to be issued"**, with **no open hold/witness points, no open
deviations/NCRs, and client acceptance in order** → **Create Release Note** (no
vetting; straight to approver) → items/PO/outcome carried over → approve → issue.
Prove the button is **not clickable** while any of those are pending, with the
reasons shown, and becomes clickable once cleared (revision if needed).

**J6 — Nonconformity to corrective action.** A finding / client rejection raises an
**NCR** → containment/disposition → **CAPA** (many actions, owners, dates) →
verification → close, feeding back to release eligibility. Prove multi-branch.

**J7 — Client portal loop.** Client user signs in → sees the issued report →
**accepts or rejects** → a rejection **raises an NCR** at the agency and blocks
release. Prove portal **permissions** (who at the client can see money vs only
reports) and that a client sees **only their own** documents.

**J8 — Vendor portal loop.** Vendor sees their inspection outcomes / documents /
external NCRs and responds. Prove scoping and confidentiality.

**J9 — Progress reporting (project site).** Daily → Weekly → Fortnightly → Monthly
progress reports over a deputation, each fillable, rolling up correctly.

**J10 — Bill to books.** Job closed (report + attendance gates) → **invoice** raised
(only after close; finance/admin override) → payment/receipt → **profitability** and
**inter-office reconciliation** reflect it. Prove no invoice before close, and no
double-count.

**J11 — Compliance backbone (ISO/IEC 17020).** Equipment & calibration validity gates
use; competence/authorisation gates who may inspect what; impartiality/conflict
declared; identity documents gate site access; confidentiality undertakings;
internal audit & management review. Prove an expired calibration / lapsed competence
/ undeclared conflict **blocks or flags** as designed.

**J12 — Management visibility.** Every role dashboard (ED, SBU head, branch manager,
coordinator, senior inspector, inspector) shows that role's **pending tasks in list
form** and the right KPIs (TAT, overdue, utilisation, profitability, risk). Prove the
pending-task counts match the underlying records and each links to the real work.

**J13 — Tenant & lifecycle.** Terminology renamed (e.g. "Work Order" → "Call") flows
to **every** screen and output; **seat/module licensing** enforces at the limit;
**no hardcoded agency name** anywhere (the platform is generic for all TPIAs).

### Cross-cutting checks to assert on every journey

- **Data carried forward** (client/vendor/PO/ITP/scope/items) is never re-keyed and
  never diverges between screen, PDF and Word output.
- **Right role at each hop**; the wrong role is refused (UI **and** direct request).
- **Notifications** reach the correct next actor and no one else.
- **Audit trail** captures each hop; **immutability** holds after issue; the **hash
  chain verifies** across the whole journey.
- **Timestamps/FY/TAT** correct across the journey; nothing spoofable by a device.
- **Idempotency**: replaying a step or double-clicking never creates a duplicate
  record or a second IRN.

### Deliverable close
- A **journey status table** (J1–J13: Pass / Fail / Blocked, open Blocker/Critical).
- A **systemic findings** list — breaks that only appear when modules are joined,
  which no single module report would catch.
- The updated master RTM slice for the E2E layer.

### Rules
- Test the **seams**, not the fields (those are Prompt 3).
- Always run as the **changing chain of personas**; assert the negative at each hop.
- Evidence per step (record IDs, states, messages), not assertions.
