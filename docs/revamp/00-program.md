# EXAACT → ITOP Revamp — Program Charter

**Working branch:** `exaact-ops-system-tpia-manpower` (all work commits here; the
default branch is never touched).
**Status:** Step 1 of the agreed plan — *Audit & Reuse Map* (documentation only,
no code changes).

This folder is the control record for the controlled transformation of EXAACT
into an **Integrated Technical Operations Platform (ITOP)** — the operating
system of record for technical-services execution:
**Talent → Competence → Deployment → Field Execution → Evidence → Approval →
Billable Event → Invoice → Revenue.**

Read order: `00-program.md` (this file) → `01-current-state-inventory.md` →
`02-gap-and-reuse-map.md`.

---

## 1. The one finding that governs everything

**EXAACT is not a blank field for a rebuild. It is a mature, ~120-engine,
~215-table platform that already implements most of the ITOP spine — and already
enforces the exact "one spine / one master / no duplicate engines" philosophy the
directive demands, as written law.**

That law lives in `docs/phase-2/02-canonical-application-model.md` (§79/80,
"Canonical Application Model"). Its governing rule:

> "Read through the named canonical engine; never add a second table,
> calculation, or status system. Convergence is done through read-views and
> mapping layers, never table merges."

Consequences for this revamp:

- The correct default is almost never **BUILD**. The evidence (see
  `02-gap-and-reuse-map.md`) is that the overwhelming majority of the directive's
  requirements resolve to **KEEP / EXTEND / CONNECT / CONFIGURE**.
- The revamp's real work is **connecting disconnected capabilities, retiring
  vestigial state, converging dual-truth data, and packaging role experiences** —
  plus a *small* number of genuine strategic builds (chiefly the persisted
  **Billable Event** bridge).
- Doing this the app's own way (read-views + mapping layers + additive
  migrations) is what makes "no destruction" achievable rather than aspirational.

---

## 2. Strategic positioning (what EXAACT is, and is not)

**Is:** the Industrial Technical Operations Platform — the operational system of
record connecting technical talent, competence, mobilization, TPIA/TIC
inspection, field execution, evidence, approval, and commercial realization for
technical-service organizations (Indian TPIA / technical staffing / manpower /
technical recruitment).

**Is not, and must not drift into:** a generic HRMS, ATS, payroll engine, generic
ERP/CRM, generic project-management tool, or generic inspection-checklist app.
EXAACT goes **deeper vertically** into industrial technical operations, and stays
**selective horizontally** — it *orchestrates* payroll/accounting/job-boards, it
does not become them.

---

## 3. Non-negotiable guardrails (how we guarantee "no destruction")

These are not promises; they are the method, and each is checkable per change.

1. **Additive-only by default.** New columns/tables via the existing idempotent
   `ensure_column()` / `CREATE TABLE IF NOT EXISTS` path; never drop, rename, or
   repurpose a live column without an approved migration. New engines *wrap* old
   ones (read-views + mapping) rather than replace them — the app's §79/80 rule.
2. **Behind switches that already exist.** Every new experience rides on the
   installed packaging machinery — industry packs (`lib/packs.php`), product
   licensing (`lib/licence.php`), and per-role/per-user module gates
   (`mod.<key>.view/.edit` through the single `can()` choke point). Nothing new is
   exposed until it is switched on.
3. **No new permission without asking.** Per `CLAUDE.md`, a role is never granted
   a permission absent from `docs/02-permission-matrix.md`; a new permission stops
   work and asks first.
4. **No new status/transition without asking.** Per `CLAUDE.md`, no status or
   transition outside `docs/03-object-lifecycles.md` is added without asking.
5. **Docs move with code.** Any change that alters who-can-do-what updates
   `docs/` in the *same commit* as the code. Code and docs never disagree.
6. **Regression pass before every commit.** Run `phpapp/tests/` (the suite the
   Phase 2/3 work grew to ~3,740 passing) plus `php -l` on changed files. No
   feature is "done" on the happy path alone (directive Part 27).
7. **Change-control record per major change** (directive Part 25): existing state,
   problem, proposed solution, reuse opportunity, DB impact, API impact, migration
   requirement, dependency impact, regression risk, rollback strategy.
8. **Phone-first inspectors, desk-first everyone-else** (`CLAUDE.md`) — never
   averaged into one middle.

---

## 4. Decision hierarchy for every proposed change

Never start at BUILD. For each requirement, walk the ladder and stop at the first
viable rung:

**CONFIGURE → REUSE → EXTEND → CONNECT → REFACTOR → BUILD.**

Classification tags used in `02-gap-and-reuse-map.md`:

| Tag | Meaning |
|---|---|
| **KEEP** | Exists and is strategically + technically appropriate. |
| **IMPROVE** | Exists; needs UX/workflow improvement (not rebuild). |
| **EXTEND** | Existing infrastructure supports the new requirement additively. |
| **CONNECT** | Capability exists but is disconnected from another module. |
| **REFACTOR** | Duplicated / vestigial / hard to maintain; converge safely. |
| **BUILD** | Strategically necessary and genuinely missing. |
| **HIDE/DEFER** | Present or possible but out of scope for the current package/priority. |
| **AVOID** | Outside EXAACT's strategic focus — do not build. |

---

## 5. Phase plan (directive Phases A–G, mapped to this repo)

| Phase | What | Output | State |
|---|---|---|---|
| **A — Discovery** | Inventory engines, tables, screens, roles, lifecycles, config switches | `01-current-state-inventory.md` | **This step** |
| **B — Gap analysis** | Map existing → target ITOP; classify every capability | `02-gap-and-reuse-map.md` | **This step** |
| **C — Consolidation** | Duplicate data, vestigial state, disconnected workflows to converge | §"Disconnects" in `02` | **This step (identified)** |
| **D — Target architecture** | Person master lifecycle, spine, Billable Event model over existing tables + additive migration sketch | `03-target-architecture.md` | **Drafted (Step 2)** |
| **E — UX revamp** | Role cockpits over existing area homes / dashboards | `04-ux-cockpits.md` | Later |
| **F — Strategic development** | Priority 1→5 vertical slices, one reversible commit each | per-slice change-control docs | Later |
| **G — Testing** | Regression + positive/negative/boundary/offline/permission cases | test additions | Continuous |

**Priority order for Phase F** (from the directive): 1) Technical competence,
2) Mobilization & deployment, 3) Inspection execution, 4) Operational→commercial
automation, 5) Integrations.

---

## 6. Deliverables register (directive Part 28)

| # | Deliverable | Where | State |
|---|---|---|---|
| 1 | Existing Application Inventory | `01-current-state-inventory.md` | Drafted (this step) |
| 2 | Module-by-Module Assessment | `02-gap-and-reuse-map.md` | Drafted (this step) |
| 3 | Screen & Function Inventory | `01` §Screens | Drafted (this step) |
| 4 | Workflow Inventory | `01` §Lifecycles + `docs/03-object-lifecycles.md` | Referenced |
| 5 | Existing Architecture Assessment | `01` §Engines + README | Drafted (this step) |
| 6 | Duplicate System Analysis | `02` §Disconnects & duplicates | Drafted (this step) |
| 7 | Master Data Analysis | `02` §Person/Party master | Drafted (this step) |
| 8 | Market Gap Analysis | `02` §Target spine vs existing | Drafted (this step) |
| 9 | Product-Market-Fit Mapping | `02` §Packages | Drafted (this step) |
| 10 | Build/Extend/Integrate/Avoid Matrix | `02` §Classification | Drafted (this step) |
| 11 | Target Architecture | `03-target-architecture.md` | Drafted (Step 2) |
| 12 | UX/UI Revamp Plan | `04-ux-cockpits.md` | Pending (Phase E) |
| 13 | Database Migration Plan | `03` §7 + per slice | Sketched (Step 2); detailed per slice |
| 14 | Integration Plan | `03` §6 | Sketched (Step 2) |
| 15 | Implementation Roadmap | `03` §8 | Drafted (Step 2) |
| 16 | Regression Testing Plan | `phpapp/tests/` + `docs/testing/` | Continuous |
| 17 | Final Gap Closure Report | end of program | Pending |

---

## 7. What we do next

This step delivers items 1–10 as the *contract* for everything after. Nothing is
built until it is on the map in `02-gap-and-reuse-map.md`. The recommended first
real slice (Priority 1, Technical Competence) is proposed at the end of `02`, with
a change-control skeleton — to be approved before any code is written.

---

## 8. Decision log (durable memory across sessions)

This is the authoritative record of decisions taken. Any future session (or any
future Claude reading `docs/revamp/`) must honour these unless the user changes
them here. Newest at the bottom.

| Date | ID | Decision | Rationale | Status |
|---|---|---|---|---|
| 2026-08-27 | D1 | The Billable Event **APPROVED** step reuses the existing `finance.reconcile` permission. A dedicated `billing.approve` permission is **not** created now. | Avoids adding a permission (respects the "no new permission without asking" guardrail); `finance.reconcile` already gates commercial review. Revisit only if segregation-of-duties later demands a distinct approver. | Active |
| 2026-08-27 | D2 | Build in **lowest-risk-first** order, starting with **P1 — Technical Competence / Credential Vault**. | P1 is highest directive priority, purely additive, ~70% reuse of existing `competence`/`identity` engines. | Active |
| 2026-08-27 | D3 | The first-class **Engagement** entity stays **deferred until after P4** (Billable Event). Keep threading the spine by `contract_number` until then. | Matches §80; avoids a structural change before the higher-value Billable Event work. | Active |

## 9. Parking lot & revisit triggers (so nothing is forgotten)

Written triggers that survive across sessions. Each is checked at the named
checkpoint; if its condition is met, we act on it — it does not rely on anyone
remembering.

| ID | Parked item | Revisit **trigger** (when to act) | Checkpoint that enforces it |
|---|---|---|---|
| **RT1** | **Escalate to the Billable Event headline (P4).** The user's explicit concern: if the lowest-risk EXTEND/CONNECT slices do **not** close the core promise — *"operational work must never disappear before reaching billing"* — pull **P4 (Billable Event ledger)** forward as the next slice instead of continuing down the priority list. | At **every slice's acceptance review** (each `docs/revamp/slices/*.md`), answer the standing question: *"Does the operational→commercial gap still exist and is it now the biggest blocker to value?"* If **yes**, the next slice becomes **P4**, regardless of the roadmap order. | The "Revisit-trigger check" section that every slice change-control record must contain (see the P1 record). The answer is recorded in the slice doc, so the decision is visible in the repo, not held in memory. |
| **RT2** | Dedicated `billing.approve` permission (see D1). | When a slice introduces a real segregation-of-duties need between "who reconciles finance" and "who approves a billable event." | Billable Event (P4) acceptance review. |
| **RT3** | First-class `Engagement` entity (see D3). | After P4 ships, if the `contract_number` string proves limiting (e.g. cannot model multi-contract engagements). | P4 post-implementation review. |

**How the user "remembers":** you don't have to. RT1 lives here in the repo and
is re-checked at the top of every slice's acceptance review. If P1 (or any slice)
leaves the billing gap open and it's the biggest blocker, the very next planning
step switches to P4 automatically — and the reason is written into that slice's
doc. Optionally, the user may also ask for a scheduled check-in reminder; the repo
record is the primary, session-independent memory.
