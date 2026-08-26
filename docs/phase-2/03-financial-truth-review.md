# §28 — Financial Truth Engine · convergence review (for sign-off)

**Status:** ANALYSIS — no code changed. This is the delta review the phase requires before any §28
convergence, because §28 **changes displayed profit numbers** and therefore needs explicit sign-off.

Measured on the demo dataset (104 jobs) via `profit_reconciliation()` — the §29/Module-32 read-only
consistency engine — with all closed jobs' cost basis frozen (§30).

---

## 1. The question

§28 asks for *one* financial truth: the same job should not show one profit on the MIS dashboard,
another on the SBU P&L, another on the boss/owner view, and another on management reports. Today it can.

## 2. What's actually there — not one number, four bases

| # | Surface | How it computes job profit | Frozen (§30)? |
|---|---|---|---|
| **A** | **Canonical** — `job_profit()['profit']`, used by **management reports** | revenue − (labour + **overhead** + expenses + **voucher** + subcon + other − **recovered** + **contingency**) | ✅ yes |
| **B** | **MIS dashboard** (`mis_summary`) + **SBU-PL contract table** (`byBoss`) | revenue − labour − expenses − subcon — **drops overhead, voucher, other, contingency; ignores recovered** | reads A's inputs, but re-derives partial |
| **C** | **Boss / owner view** (`boss_profit`) | revenue − **loaded-labour** (overhead baked in) − expenses − voucher − subcon − contingency — **ignores recovered; recomputed live** | ❌ no (drifts) |
| **D** | **SBU P&L main rows** (`ops_sbu_pl`) | **period-costing**: real month-end office expenses allocated to SBUs (`costing_stored_by_sbu`) vs SBU revenue — **not job-summed at all** | n/a (month-end freeze) |

**A, B and C are the same accounting model (bottom-up job-costing) computed three different ways — that
is the real inconsistency.** D is a *different, legitimate* model (top-down period-costing from actual
office ledger); it answers "how did this SBU do this month", not "what did this job earn". D must be
**reconciled to**, never merged with, A/B/C.

## 3. The measured divergence (104-job demo)

```
Jobs measured:            104
Jobs whose profit drifts:  92   (88% of jobs)

Canonical (A):   profit 5,747,287.28    cost 788,712.72
MIS/SBU-PL (B):  profit 5,821,590.00    cost 714,410.00
Overstatement (B − A):     +74,302.72   (MIS shows MORE profit than real)

The gap, itemised (what B omits):
  overhead      59,168.00
  contingency   11,304.72
  voucher        3,830.00
  other              0.00
  recovered          0.00   (would widen the gap where recoveries exist)
```

**The drift is systematic and one-directional: the MIS/SBU dashboards overstate profit** (they hide real
cost). Here it's 1.3% because the demo's salaries/overheads are light; on live data where overhead is a
material share of cost, the gap is proportionally larger. 92 of 104 jobs disagree between two screens
today.

**C (boss view)** diverges a *third* way: it loads overhead into labour (so its cost mix differs from
both A and B), never credits client-recovered expenses, and — because it recomputes live — a closed
job's owner profit **moves when today's salary or office % changes**, which §30 fixed everywhere else.

## 4. Why this matters beyond tidiness

- A manager comparing the MIS dashboard to management reports sees two profit figures for the same jobs
  and cannot tell which to trust.
- The overstatement is in the **optimistic** direction on the most-looked-at screen (MIS), so decisions
  (which SBU is healthy, which boss is profitable) are made on inflated margins.
- C's live recompute means a historical owner-P&L is not reproducible — the one thing §30 set out to
  guarantee.

## 5. The decision — a menu, not a switch

Each option is non-destructive (no records deleted; legacy columns retained). They differ in how much
displayed number moves and how much sign-off each needs.

| Option | What changes on screen | Risk / effort | Sign-off |
|---|---|---|---|
| **0. Do nothing** | nothing; the drift stays, now *quantified* on `/system-status` (§29) | none | — |
| **1. Converge B → A** (MIS + SBU-PL contract table read `$p['profit']`/`$p['cost']`) | MIS & SBU-PL profit **drops** to the true figure (−₹74k on the demo); 92 jobs' shown profit corrects downward | low code (swap the inline formula for the engine's fields); **numbers move** — every dashboard reader sees lower, correct margins | **REQUIRED** |
| **2. Converge C → A** (boss view uses `job_profit` with the frozen basis) | owner P&L becomes reproducible and matches reports; small shifts per boss | low-med; **numbers move** | **REQUIRED** |
| **3. Reconcile D ↔ A** (show the job-costing vs period-costing gap on the SBU-PL screen, both bases labelled) | adds a reconciliation line; **no existing number changes** | low; additive | recommended, no sign-off needed |

## 6. My recommendation

**Do 1 + 2 together, in one change, behind a preview.** They are the actual "one truth" §28 wants: all
three job-costing surfaces read the single frozen engine, so a job shows the same profit everywhere.
Then **3** to make the period-costing lens (D) explicitly a *different question*, reconciled and labelled,
rather than a fourth silent number.

Because 1 + 2 move displayed profit **downward to the correct value**, I would ship it as:
1. a **`finance_truth_unified` setting (default OFF)** so nothing changes until you turn it on;
2. a **before/after preview** on `/system-status` (it already computes both via `profit_reconciliation`)
   showing the exact figure each dashboard will change to, per SBU/office/boss;
3. flip the default ON only on your explicit say-so, with the preview as the record of what moved.

That keeps it non-destructive and fully reversible, and you approve the *specific numbers* before any
user sees them — which is exactly the §28 sign-off gate.

## 7. What needs your sign-off

- **Options 1 and 2** (they change displayed profit) — **yes, explicit sign-off.** Nothing built until you say go.
- **Option 3** (reconciliation display, no number moves) — I can do this now under the normal
  non-destructive rule if you want the two bases shown side by side first, as evidence before deciding 1+2.

---

*Evidence engine: `profit_reconciliation()` (`lib/mis.php:105`), surfaced on `/system-status` (Module 32/50).
Canonical engine: `job_profit()` (`lib/ops.php:1635`), frozen per §30. No figures in this document are
hypothetical — they are the demo dataset measured both ways.*
