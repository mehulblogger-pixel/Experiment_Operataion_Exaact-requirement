# Scripted Test Run — Job → Close → Profit → Invoice (with exact figures)

**Scope.** One stage of the E2E flow (`docs/qa-edge-cases/00-end-to-end-flow.md`), expanded into a
step-by-step run a tester can execute and check to the rupee. Every expected figure below was **produced by
the actual engine** (`job_profit()`, `invoice_readiness()`) on the seed data in §1 — not hand-derived — so a
correct build reproduces them exactly.

**Why this stage.** It is where the money is decided: the frozen cost basis (§30), the one canonical profit
(§28), the invoice-readiness gate (§33) and the GST split all meet here.

---

## 1. Seed data (the exact inputs)

Create these rows (a coordinator/finance user with office = Ahmedabad; SBU = NDT):

| Object | Field | Value |
|---|---|---|
| **Contract** `CN-DEMO` | value | **100000** · open_status OPEN |
| **Call** `CALL-DEMO` | status / op_status | ALLOCATED / ASSIGNED · **po_id = 5001** (a PO on file) |
| **Job** `JOB-DEMO` | contract_number | CN-DEMO · executing office = Ahmedabad · **closed** |
| | mandays | **5** |
| | frozen cost basis (§30) | daily_base **2000** · overhead **15%** · contingency **5%** (`cost_basis_at` set) |
| | subcon_cost | **4000** · other_cost **800** |
| | invoice_amount (revenue) | **50000** |
| **Expenses** (on the job) | travel/food/lodging/misc | 3000 + 1000 + 2000 + 500 = **6500** |
| **Voucher** (against the job) | row_total | **1500** |
| **Report** on the job | status | **ISSUED** (type FIR) |
| Client-recovered bills | — | **0** (none) |

> The cost basis is *frozen* on purpose: it makes the run deterministic (independent of today's salary or
> the working calendar) and is exactly what a real closed job carries after §30.

---

## 2. Step-by-step, with exact expected figures

### Step A — Open the job's profit (Profitability / Job Money tab)

**Action:** open `JOB-DEMO`; view its P&L. **Engine:** `job_profit($job, office=Ahmedabad)`.

**Expected — every line, exactly:**

| Line | Formula | **Expected** | Reflected in |
|---|---|---|---|
| Man-days | seeded | **5** | Job-360 header; P&L |
| Daily base (unloaded) | seeded | **₹2,000** | P&L (salary line) |
| Loaded daily rate | 2000 × (1+15%) | **₹2,300** | P&L (display rate) |
| Labour | 2000 × 5 | **₹10,000** | P&L |
| Overhead | 10000 × 15% | **₹1,500** | P&L (own line) |
| Expenses (closure) | seeded sum | **₹6,500** | P&L |
| Voucher (claimed) | seeded | **₹1,500** | P&L |
| Sub-contractor | seeded | **₹4,000** | P&L |
| Other cost | seeded | **₹800** | P&L |
| Client-recovered | min(0, exp+vouch) | **₹0** | P&L (credit) |
| Direct = L+OH+Exp+V+Sub+Other−Rec | 10000+1500+6500+1500+4000+800−0 | **₹24,300** | P&L subtotal |
| Contingency | 24300 × 5% | **₹1,215** | P&L |
| **Cost** | 24300 + 1215 | **₹25,515** | P&L total cost |
| Revenue | seeded invoice | **₹50,000** | P&L |
| **Profit** | 50000 − 25515 | **₹24,485** | P&L; management reports | 
| Margin | 24485 / 50000 | **49.0%** | P&L; MIS |

✅ **PASS if** cost = **25,515** and profit = **24,485**. ❌ Any other profit means a cost line was dropped.

### Step B — Prove the §28 "one truth"

Look at the **same job** on MIS, the SBU-P&L contract table and the boss/owner view.

| Setting | What each dashboard shows | Value |
|---|---|---|
| `finance_truth_unified` **ON** (default) | canonical profit — identical everywhere | **₹24,485** |
| `finance_truth_unified` **OFF** (legacy) | the partial formula: revenue − labour − expenses − subcon | **₹29,500** |
| **Overstatement** the legacy view carried | overhead 1500 + voucher 1500 + other 800 + contingency 1215 | **₹5,015** |

✅ **PASS if** with the switch ON, MIS = SBU-P&L = boss = **24,485**; and `/system-status` → *Profit-figure
consistency = "Unified"* (green). The legacy 29,500 must appear **only** if someone sets the switch to `0`.

### Step C — §30 historical reproducibility

**Action:** after closing, change the office overhead % (e.g. to 25%) and re-open the P&L.

**Expected:** the closed job's profit is **still ₹24,485** — the frozen basis is used, today's rate is
ignored. ✅ **PASS if** the number does not move. (An *open* job would recompute live; a closed one must not.)

### Step D — Invoice readiness (Job → Money tab)

**Action:** on the closed job, view the invoice-readiness verdict. **Engine:** `invoice_readiness($job)`.

**Expected — verdict READY, every check:**

| Check | Severity | **Expected** | Reflected in |
|---|---|---|---|
| Job is closed | block | ✅ Closed | readiness panel |
| Reports issued | block | ✅ "All 1 report(s) issued." | readiness panel |
| PO on file | warn | ✅ "PO linked." | readiness panel |
| Within contract value | warn | ✅ "Billed 0 of 100000." | readiness panel |
| **Overall** | — | **READY to invoice** | green pill on Money tab |

✅ **PASS if** verdict = **READY** with no blockers. See §3 for how each edge below flips it.

### Step E — Raise the GST invoice

**Action:** one click "Raise GST invoice from this job". Amount carries from the job (no re-key). GST is
decided from the client's state and **frozen** on the invoice.

**Expected — taxable ₹50,000, GST 18%:**

| Client state vs supplier | Split | **Expected** | Reflected in |
|---|---|---|---|
| **Intra-state** (same state) | CGST 9% + SGST 9% | CGST **₹4,500** + SGST **₹4,500** → **total ₹59,000** | invoice; `/ledger` |
| **Inter-state** (different) | IGST 18% | IGST **₹9,000** → **total ₹59,000** | invoice; `/ledger` |

✅ **PASS if** the invoice total = **₹59,000** and the split matches the state rule. Press the button twice →
the **same** invoice opens (idempotent), never a second empty one.

### Step F — Receipt, then the money stream

**Action:** record a part-receipt of **₹30,000**.

**Expected:** outstanding = 59,000 − 30,000 = **₹29,000** (before TDS adjustments); and the §27 money stream
on the client-360 shows, in order: **QUOTE_ACCEPTED (committed)** → **INVOICE_ISSUED (billed 59,000)** →
**RECEIPT_RECEIVED (30,000)**, with the rollup: committed / billed 59,000 / received 30,000 / **outstanding
29,000**. Reflected in: `/ledger` ageing; client-360 Money timeline; Command Centre money band.

---

## 3. Edge cases on this stage (flip one input, re-check)

| # | Change from the seed | Expected effect | Reflected in | Sev |
|---|---|---|---|---|
| E1 | Report status DRAFT (not issued) | Readiness blocker **"reports_issued"**; NOT-READY. Under `invoice_gate_strict` the bill button is refused | readiness panel; job-bill | 🔴 |
| E2 | Remove the ISSUED report entirely | No report → "reports_issued" absent (nothing to issue); other checks stand | readiness panel | 🟡 |
| E3 | Prior invoices on CN-DEMO = 80,000 | 80,000 + 50,000 > 100,000 → **warning** "contract_value" (NOT a block); still READY | readiness panel | 🟠 |
| E4 | Call `po_id` cleared | **warning** "po" ("No PO linked…") ; still READY (advisory) | readiness panel | ⚪ |
| E5 | RN/IRN report + `rn_require_client_acceptance=1`, no acceptance | Blocker **"client_acceptance"**; NOT-READY | readiness panel; issue gate | 🔴 |
| E6 | Job NOT closed | Blocker **"closed"** ("Close the job before billing.") | readiness; job-bill refuses | 🔴 |
| E7 | `invoice_gate_strict=1` + any blocker | `/job-bill` hard-refused with the blocker message | flash on job-bill | 🔴 |
| E8 | Recovered bills = 2,000 (client reimburses) | Recovered nets into *direct* (2000), so contingency drops too: direct 22,300 → contingency 1,115 → cost **₹23,415**; profit **₹26,585** | P&L | 🔴 |
| E9 | Contingency % = 0 | Contingency 0 → cost **₹24,300**; profit **₹25,700** | P&L | 🟡 |
| E10 | Branch user from another office opens `/invoice?id=` | **Denied** (§51 single-record scope) | 403/scope guard | 🟠 |
| E11 | Raise a 2nd invoice colliding on number | DB UNIQUE refuses; `books_issue` re-allocates the number | invoicing; integrity | 🔴 |
| E12 | Cancel the issued invoice | §27 shows a **reversal**; net billed drops back; outstanding recomputes | client-360 money timeline; ledger | 🔴 |

---

## 4. The arithmetic, so a reviewer can check by hand

```
labour        = daily_base × mandays          = 2000 × 5        = 10,000
overhead      = labour × oh%                   = 10000 × 0.15    =  1,500
direct        = labour+overhead+expenses+voucher+subcon+other−recovered
              = 10000+1500+6500+1500+4000+800−0                 = 24,300
contingency   = direct × cont%                 = 24300 × 0.05    =  1,215
COST          = direct + contingency           = 24300 + 1215    = 25,515
REVENUE                                                          = 50,000
PROFIT        = revenue − cost                 = 50000 − 25515   = 24,485
margin        = profit / revenue               = 24485 / 50000   = 48.97% ≈ 49.0%

partial (legacy MIS/SBU) = revenue − labour − expenses − subcon
              = 50000 − 10000 − 6500 − 4000                      = 29,500
overstatement = partial − canonical            = 29500 − 24485   =  5,015
              ( = overhead 1500 + voucher 1500 + other 800 + contingency 1215 )

GST @18% on 50,000  → intra: CGST 4,500 + SGST 4,500 ; inter: IGST 9,000 ; total 59,000
```

---

## 5. Pass/fail summary (the assertions to record)

| Assertion | Expected | Pass? |
|---|---|---|
| Job profit | **₹24,485** (cost 25,515, margin 49.0%) | ☐ |
| §28 ON — MIS = SBU-P&L = boss | all **₹24,485** | ☐ |
| §28 OFF — legacy partial | **₹29,500** (overstates by 5,015) | ☐ |
| §30 — profit after a later rate change | still **₹24,485** | ☐ |
| §33 — invoice readiness on the seed | **READY** (2 blockers pass, 2 warnings pass) | ☐ |
| GST invoice total | **₹59,000** (state-correct split) | ☐ |
| §27 rollup after 30k receipt | committed → billed 59,000 → received 30,000 → outstanding 29,000 | ☐ |
| E1/E5/E6 blockers | NOT-READY with the named blocker | ☐ |
| E10 cross-office invoice fetch | **denied** (§51) | ☐ |

*Figures verified against the live engine on 2026-08-27; a build that reproduces them is correct for this
stage. For the other stages of the flow see `docs/qa-edge-cases/00-end-to-end-flow.md`.*
