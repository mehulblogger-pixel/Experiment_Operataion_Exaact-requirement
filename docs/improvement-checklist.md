# EXAACT TPIA OS — Improvement Program Checklist

Living record of the non-destructive, module-by-module improvement program.
The full drill-down spec is in **`docs/improvement-program.docx`**; the per-module
edge-case analyses live in **`docs/edge-cases/`**.

**Rules of engagement**
- One module at a time. Nothing simultaneous.
- Before building a module: a very-detailed edge-case analysis (down to individual
  buttons) is written to `docs/edge-cases/NN-*.md` and reviewed.
- Non-destructive: nothing existing is deleted; improvements are additive.
- Each module ships tested, on branch `claude/quotation-management-workflow-5dokb2`.

**Status key**
`⬜ not started` · `📝 edge-cases drafted (awaiting go)` · `🛠️ building` · `✅ done & pushed`

---

## Progress

| # | Module | Priority | Status | Commit / date |
|---|--------|----------|--------|----------------|
| 01 | Masters | P2 | ⬜ | — |
| 02 | Users / Access / Roles | P0 | ⬜ | — |
| 03 | Quotations | P1 | ⬜ | — |
| 04 | Calls / Service Requests | P1 | ⬜ | — |
| 05 | Jobs (Job 360) | P1 | ⬜ | — |
| 06 | Inspection / IDEMS core + Applicability | P0 | ⬜ | — |
| 07 | Vetting / Technical Review / Approval | P0 | ⬜ | — |
| 08 | Report Release / Issue | P0 | ⬜ | — |
| 09 | Invoicing | P0 | ⬜ | — |
| 10 | Client Portal | P0 | ⬜ | — |
| 11 | Vendor / Supplier-Inspector Centre | P1 | ⬜ | — |
| 12 | NCR | P1 | ⬜ | — |
| 13 | CAPA | P2 | ⬜ | — |
| 14 | Settings | P2 | ⬜ | — |
| 15 | Clients / Customer 360 | P2 | ⬜ | — |
| 16 | Vendors / Vendor 360 | P2 | ⬜ | — |
| 17 | Leads | P2 | ⬜ | — |
| 18 | Orders / Contracts (Contract 360) | P1 | ⬜ | — |
| 19 | Inquiries / Requirements | P2 | ⬜ | — |
| 20 | Project Costing | P1 | ⬜ | — |
| 21 | Hold / Witness Points | P0 | ⬜ | — |
| 22 | Complaints | P1 | ⬜ | — |
| 23 | Equipment (Equipment 360) | P1 | ⬜ | — |
| 24 | Competence (Competence 360) | P0 | ⬜ | — |
| 25 | Impartiality | P0 | ⬜ | — |
| 26 | Identity | P0 | ⬜ | — |
| 27 | Confidentiality | P0 | ⬜ | — |
| 28 | Audits | P2 | ⬜ | — |
| 29 | Data Control / Governance | P0 | ⬜ | — |
| 30 | Vouchers / Expenses | P1 | ⬜ | — |
| 31 | Attendance / Reconciliation | P1 | ⬜ | — |
| 32 | Profitability (canonical engine) | P0 | ⬜ | — |
| 33 | Overheads | P1 | ⬜ | — |
| 34 | Dashboards / Command Centre | P2 | ⬜ | — |
| 35 | Recruitment / Workforce | P1 | ⬜ | — |
| 36 | Licensing / SaaS Admin | P1 | ⬜ | — |
| 37 | Global Search | P1 | ⬜ | — |
| 38 | Notification Centre | P2 | ⬜ | — |
| **39** | **My Work** | **P1** | **📝 edge-cases drafted (awaiting go)** | — |
| 40 | Activity Timeline | P2 | ⬜ | — |
| 41 | Document Control | P1 | ⬜ | — |
| 42 | Change Control | P2 | ⬜ | — |
| 43 | Training | P2 | ⬜ | — |
| 44 | Evidence | P1 | ⬜ | — |
| 45 | AI / Intelligence | P3 | ⬜ | — |
| 46 | Integrations | P2 | ⬜ | — |
| 47 | Mobile / PWA | P1 | ⬜ | — |
| 48 | Report Template Builder | P1 | ⬜ | — |
| 49 | Entity 360 Standard | P2 | ⬜ | — |
| 50 | Cross-module Consistency & Regression | P0 | ⬜ | — |

---

## Completion log

_Each module, once done, gets a dated entry here: what was added, what was preserved,
which edge cases were handled, and the commit._

<!-- Append entries below as modules complete. -->
