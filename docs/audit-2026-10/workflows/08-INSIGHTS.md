# 8 — INSIGHTS

**Menu:** left rail → **Insights** → `/insights`
**Screens:** 13 reachable in or from this area, but **only 3 are tiles** and
**only 3 screens are genuinely Insights' own**
**Modules:** `reports` (plus `crm_reports` for the sales funnel)
**Who can open it** (measured): Master Admin · Business Director · Branch
Manager · Operation Manager · Coordinator · Finance.
**Inspector gets 403 on `/insights`** — inspectors have `View(own)` on
dashboards per the matrix, which they get on their own phone screens, not here.

---

## 1. What this area is, honestly

**Insights is a thin area, and that is the finding.** Of the 13 screens
reachable from it, ten belong to other areas and are documented there:
`/calls` and `/jobs` (Operations), `/invoicing`, `/profitability`, `/sbu-pl`,
`/cost-run` (Money), `/opportunities` (Sales), `/activities` (Directory),
`/industry` (Admin).

Three screens are its own:

| Screen | Module | What it is |
|---|---|---|
| `/reports` — **Dashboards** | `reports` | Role dashboards across the business |
| `/mis` — **Management dashboard** | `reports` | The MIS overview — also a Money tile |
| `/crm-dashboard` — **Sales dashboard** | `crm_reports` | Win rates and value by stage |

So the area is largely a **set of shortcuts into other areas' reports**, with
one genuine aggregator (`/reports`) and two dashboards it shares.

**That is not necessarily wrong** — a reporting area that gathers links is a
reasonable pattern. But it means two things worth saying plainly:

1. **There is no single place that answers "how is the business doing".** The
   answer is spread across `/mis`, `/reports`, `/sbu-pl`, `/call-profit`,
   `/crm-dashboard` and `/capacity-outlook`, each with its own filters and its
   own definition of a period.
2. **Nothing in this area is reconciled.** Every number here is computed by one
   of **149 candidate metric functions** found in the code
   (`../evidence/lifecycle-and-ownership.txt` §D), and this audit has not
   verified that any two of them agree. That work is
   `../07-DASHBOARD-AND-KPI-AUDIT.md`, and it is **not yet done** — which is why
   this document describes the screens rather than vouching for the figures.

---

## 2. The three screens

### 2.1 `/reports` — Dashboards

| | |
|---|---|
| Module `reports` · **8 filters**: `fy`, `month`, `office`, `sbu`, `client`, `insp`, `act`, `itype` |
| Controls | **Reset** · **Job register** · **Call register** · **Invoicing & payments** · **Profitability by Contract Number** |
| Columns | Business Unit · **Credit** · **Loaded cost** · **Net** |
| Tile | *"Role dashboards across the business."* |

The aggregator. Eight filters — year, month, office, business unit, client,
inspector, activity, inspection type — then four links out to the registers
behind the numbers.

**`Credit · Loaded cost · Net` is the inter-office view:** "Credit" is the
inter-office credit an executing office earns, "Loaded cost" is cost with
overhead apportioned, "Net" is what is left. Per ADR-005 this is the correct
way to present cross-office work, and the column names say it compactly.

**The four links out are the right design.** A number you cannot drill into is a
number you cannot trust, and each of these goes to the register that produced
it.

### 2.2 `/mis` — Management dashboard

| | |
|---|---|
| Module `reports` · **8 filters**: `fy`, `m`, `sbu`, `activity`, `office`, **`ibo`**, `inspector`, `client` |
| Controls | **Apply** · **Clear** · **⬇ Download as CSV** |
| Columns | Business Unit · **Inspections** · **Closed** · **Man-days** · Revenue · **Direct cost** · **Profit** · **Margin** |

The MIS overview, and **the most complete single view in the application**:
volume (Inspections, Closed), effort (Man-days), and money (Revenue, Direct
cost, Profit, Margin), by business unit, with eight filters including `ibo` for
inter-branch work.

**It appears as a tile in both Money and Insights**, which is sensible — it is
the screen a director opens first.

*(**Clear** is a filter reset, not a destructive control — checked, after
mis-reading a similarly-labelled control in Reporting.)*

### 2.3 `/crm-dashboard` — Sales dashboard

| | |
|---|---|
| Module `crm_reports` · no own fields |
| Controls | **⬇ Funnel CSV** · **Pipeline board** · **30 days** · **90 days** · **6 months** · **12 months** · **2 years** |
| Tile | *"Win rates and value by stage."* |

Period buttons rather than date pickers — the right choice for a funnel, where
the question is almost always "the last quarter" rather than a specific range.

**This is one of the two screens headed "Sales dashboard"** (`01-SALES.md`
S-F2): `/crm-dashboard` is the period-filtered funnel, `/crm-reports` is a
month-by-month table, neither has a Sales tile, and both answer to the same
name.

---

## 3. What the numbers are built from

**149 functions** in the running application have names that say they compute a
headline figure — `*_counts`, `*_total`, `*_summary`, `*_readiness`,
`*_pending_count`, `*_ageing`. A sample of what that includes:

`appr_inbox_count` · `appr_sla_summary` · `attend_review_count` ·
`attention_summary` · `audits_readiness` · `billable_mismatch_count` ·
`billable_pending_count` · `books_counts` · `books_invoices_count` ·
`books_outbox_counts` · `calls_status_disagreement_count` · `candpool_summary` ·
`capa_readiness` · `cdoc_readiness` …

Two of those names are worth pausing on, because they are the system telling you
where it does not trust itself:

- **`billable_mismatch_count`** — billable events whose figures disagree.
- **`calls_status_disagreement_count`** — work orders whose status disagrees
  with something. (With what is not obvious from the name; it deserves a look.)

**There is also a KPI definition system** that this area does not surface:
`kpi_defs`, `kpi_targets`, `kpi_snapshots`, `kpi_versions`, `kpi_alerts` —
five tables implying defined metrics with targets, point-in-time snapshots,
**versioning** of a definition, and alerts. **No screen measured in this audit
reads them.**

**FINDING (I-F1, P2) — a KPI framework exists in the schema and no screen uses
it.** Either it is unfinished, or it is driven from somewhere this audit did not
reach. Versioned KPI definitions with targets and snapshots is exactly what a
business needs to say "our margin target was 18% and here is how we tracked
against it, measured the same way all year" — and the tables are already there.
*Worth asking the owner whether this was built and abandoned, or is next.*

---

## 4. Data flow

Insights is almost entirely a **consumer**. It writes nothing.

### Arrives from

| From | What | Grade |
|---|---|---|
| **Operations** | Inspections, Closed, Man-days, unallocated demand, capacity | **CONFIRMED** (columns render) |
| **Money** | Revenue, Direct cost, Profit, Margin, Credit, Loaded cost, Net, ageing | **CONFIRMED** |
| **Sales** | Funnel stages, win rates, value by stage | **CONFIRMED** |
| **Recruitment** | Nothing measured reaches Insights, although the data exists (`drop_point`, time-to-hire, source effectiveness) | **CONFIRMED (absence)** |
| **Quality** | Open nonconformities, overdue corrective actions, `capa_readiness`, `audits_readiness` — the functions exist; **no Insights screen measured shows them** | **SUSPECTED** |
| **Marketplace** | `/connect-analytics` is its own screen in its own area and does not feed here | **CONFIRMED (absence)** |

### Leaves for

Nothing. Every screen here is read-only, with CSV download.

**FINDING (I-F2, P2) — three areas do not reach Insights.** Recruitment,
Quality and Marketplace each generate exactly the kind of figure a director
wants — time-to-hire and where candidates are lost, open nonconformities and
overdue corrective actions, marketplace supply and demand — and none of it
appears in the management dashboard. The counts exist as functions and as tile
badges; they do not reach the place a director looks.

---

## 5. Scenarios

### S-1 — A director's Monday morning

1. **Insights → Management dashboard** (`/mis`). Set `fy` and `m`. Read, per
   business unit: **Inspections · Closed · Man-days · Revenue · Direct cost ·
   Profit · Margin**.
2. Narrow it: `office`, `sbu`, `activity`, `inspector`, `client`, or **`ibo`**
   to isolate inter-branch work.
3. **⬇ Download as CSV** for the board pack.
4. **Insights → Dashboards** (`/reports`) for the inter-office view —
   **Credit · Loaded cost · Net** — then click into **Job register**,
   **Call register**, **Invoicing & payments** or **Profitability by Contract
   Number** to see what produced a number that looks wrong.
5. **Insights → Sales dashboard** for the forward view: win rates and value by
   stage, over **90 days**.
6. **Operations → Capacity outlook** for whether the sold work can be delivered:
   **Free inspector-days · Unallocated demand · Gap · Signal**.

**Before trusting step 1, run the three reconciliations** in
`07-MONEY.md` S-3 — revenue, cost and reimbursable duplication. The system
itself provides those screens because it knows it has two sources for some
numbers.

### S-2 — "Why is this business unit's margin down?"

1. `/mis`, filtered to that business unit → Margin is down.
2. **Money → Business Unit profit & loss** (`/sbu-pl`): Revenue · **Engineers** ·
   Other salary · **Sub-contractor** · **Office costs** · Total cost · Profit.
   Is it revenue, people, sub-contractors, or apportioned overhead?
3. If sub-contractor cost: **Money → Profit by work order** (`/call-profit`) to
   find which jobs.
4. If office costs: **Money → Office costs & overheads** — check the
   **"Spreads across Business Units by"** basis. An apportionment basis changed
   mid-year will move margin without anything real happening.
5. If revenue: **Money → Revenue reconciliation** — does the job's stored figure
   match the ledger?
6. If man-days are up but revenue flat: **Operations → Capacity outlook** and
   the job register's **`unallocated`** filter.

### S-3 — Building the board pack

1. `/mis` → CSV, filtered by period.
2. `/reports` → the inter-office Credit / Loaded cost / Net view.
3. `/crm-dashboard` → **⬇ Funnel CSV** for pipeline.
4. **Money → Receivables ageing** → CSV, by **due date**.
5. **Quality** → open nonconformities and overdue corrective actions —
   **manually, from the Quality tiles**, because they do not appear in Insights
   (I-F2).
6. **Recruitment** → open requisitions and time-to-hire — again manually.

**Steps 5 and 6 are the finding.** A board pack needs them and the reporting
area does not carry them.

---

## 6. Edge cases

| # | Edge case | What happens | Grade |
|---|---|---|---|
| E-1 | An inspector opens Insights | **403.** Correct — they get `View(own)` on their own phone screens | **CONFIRMED** |
| E-2 | Finance opens Insights | **Opens.** Correct — the matrix gives FIN `View(money)` on dashboards | **CONFIRMED** |
| E-3 | A coordinator sees revenue figures | They hold `data.credit` but **not** `data.revenue`, so the financial section is visible without revenue or profit. That is the documented intent (`docs/01-roles.md`: *"Must never: see revenue/salary/profit"*) | **READ** |
| E-4 | Cross-office job: whose revenue is it? | The **contracting** office books client revenue; the executing office earns the **Credit**. `data.revenue` says *whether* you see revenue, never *whose* | **READ** (ADR-005) |
| E-5 | Two dashboards disagree about the same month | **Untested, and the central question of `../07-DASHBOARD-AND-KPI-AUDIT.md`.** `billable_mismatch_count` and `calls_status_disagreement_count` exist as functions, so the system anticipates disagreement | **UNTESTED** |
| E-6 | A period filter means different things on different screens | `/mis` uses `fy` + `m`; `/reports` uses `fy` + `month`; `/crm-dashboard` uses rolling buttons (30/90 days, 6/12 months, 2 years). **Comparing them requires care** | **CONFIRMED (the fields differ)** |
| E-7 | A KPI target is set | **No screen measured reads `kpi_targets`** (I-F1) | **CONFIRMED (absence)** |
| E-8 | Figures at real volume | The test database has **0 work orders and 0 jobs**, so nothing here was exercised with data. Every dashboard rendered empty | **NOT MEASURED** |

---

## 7. Findings raised in this area

| Id | Severity | Finding |
|---|---|---|
| **I-F1** | **P2** | A **KPI framework exists in the schema** — `kpi_defs`, `kpi_targets`, `kpi_snapshots`, `kpi_versions`, `kpi_alerts` — and **no screen measured in this audit reads it**. Versioned definitions with targets and snapshots is exactly what the business needs; ask whether it was abandoned or is next |
| **I-F2** | **P2** | **Recruitment, Quality and Marketplace do not reach Insights.** Time-to-hire, where candidates are lost, open nonconformities, overdue corrective actions and marketplace supply/demand all exist as data and none appears in the management dashboard |
| **I-F3** | **P2** | **Three screens, three definitions of a period** (`fy`+`m`, `fy`+`month`, rolling buttons). Cross-reading them is error-prone |
| **I-F4** | **P2** | Two screens headed **"Sales dashboard"**, neither with a Sales tile (also `01-SALES.md` S-F2) |
| **I-F5** | **P3** | The area is mostly **shortcuts into other areas**; only `/reports` is a genuine aggregator. There is no single "how is the business doing" screen |
| **I-F6** | — | *Credit:* **Credit · Loaded cost · Net** naming the inter-office model in three words · four **drill-through links** from `/reports` into the registers behind the numbers · rolling **period buttons** on the funnel rather than date pickers · and the existence of `billable_mismatch_count` and `calls_status_disagreement_count`, which show the system expects its own numbers to disagree and counts the cases |

---

## 8. What this document deliberately does not claim

**No figure in this area has been verified.** The screens, filters, columns and
controls are measured; the arithmetic behind them is not. With 149 candidate
metric functions and an empty test database, checking that two screens agree
about one month needs a populated database and an independent recomputation.

That is `../07-DASHBOARD-AND-KPI-AUDIT.md`, and it is the largest piece of the
audit still outstanding. Until it is done, treat these screens as *described*,
not as *validated*.
