# Phase 6 · Batch 2 — Mutation results

*Eighteen deliberate defects, each a way this batch could have been written
wrong, and the named assertion that catches it.*

**Harness:** `b2mut.py`, the Batch 1 harness retargeted. Each mutant is applied
to a **fresh copy** of `phpapp/`, the deploy checksum is regenerated, and the
battery runs against its **own MariaDB database**. The baseline must be clean or
the run aborts.

**Rules enforced:**

- Clean baseline mandatory — `baseline: 0 (no failures)`.
- Every mutant names **one specific assertion** that detects it.
- **A FATAL is NOT a catch**, and neither is an **ANCHOR-MISS** — a mutation that
  never applied tested nothing. Both are reported separately.
- No test weakened, deleted or skipped to make a mutant die.

---

## Result

```
baseline:  0 (no failures)
caught:    18 of 18
survivors: none
FATALs:    none
anchor misses: none (one occurred mid-batch and was re-anchored — see §3)
```

---

## The mutants

### The transaction and the conversion ceiling

| # | The defect | Caught by |
|---|---|---|
| **M1** | the conversion loses its transaction | `A1 A3 A7 A8` — three processes produce three team members and two orphans |
| **M2** | the candidate conversion uniqueness is dropped (`WHERE … inspector_id IS NULL` removed) | `A1` |
| **M3** | a second concurrent conversion is allowed through (the `rowCount` check removed) | `A1` |
| **M4** | the team member is created but the ledger row skipped | `E1 E5 C3` |
| **M5** | the relationship is written but the application left unchanged | 23 assertions — the conversion stops connecting anything |

### Branch resolution (BD1)

| # | The defect | Caught by |
|---|---|---|
| **M6** | branch resolution removed entirely | `B1 B2 B3 B4` + 3 more |
| **M7** | **the unsafe Ahmedabad fallback is restored** | `B5 B6 B7 B9` — the conversion stops refusing, and a hire lands in a branch nobody chose |

> **M7 is the guard on a decision, not a behaviour.** It exists so nobody can
> quietly reinstate the silent default the audit exposed.

### The locked boundaries and authority

| # | The defect | Caught by |
|---|---|---|
| **M8** | the M4/M6 execution boundary is bypassed | `D3 D4` |
| **M9** | the candidate scope check is bypassed | `D1 D2` |
| **M10** | the actor permission check is bypassed | `A14 A15 A16` — an actor with no recruitment right converts, **and a team member appears** |

### Entitlement (BD2)

| # | The defect | Caught by |
|---|---|---|
| **M11** | **the marketplace is made a requirement for recruitment** | `C4 C5 C6 C9` — conversion refused without the add-on |
| **M12** | **a false ledger row is written when the add-on is unavailable** | `C6 C7 C9` — STATE B reported as STATE A |

> M11 and M12 are the two halves of owner decision BD2. One makes recruitment
> depend on the marketplace; the other lies about what was recorded. Both die.

### Person groups (BD4 · invariant I43)

| # | The defect | Caught by |
|---|---|---|
| **M13** | **the repair reaches for a shared e-mail when the recorded evidence is absent** | `G8c G8d G8e` — two people sharing a handset are merged |
| **M14** | a provable split group is **not** repaired (the closure is dropped) | `G1 G2 G3` — the original Finding B defect returns |

### The writer boundary

| # | The defect | Caught by |
|---|---|---|
| **M15** | a seed writes the conversion directly again | `W1` |
| **M16** | a raw conversion writer is added to an unrelated library | `W1` |

### Truthful results

| # | The defect | Caught by |
|---|---|---|
| **M17** | a failed conversion reports success | `A9 A10` — processes claim a hire that does not exist |
| **M18** | a committed conversion reports failure | `A10 A12 B1 B3` |

---

## 2. The first battery, and what it changed

The first run scored **14 of 18**. Each survivor was root-caused rather than
argued away:

| Survivor | Why it lived | What was done |
|---|---|---|
| **M10** | nothing converted as an unauthorised actor | added `A14–A16` |
| **M17** | nothing checked whether a process reporting success named a record that exists | added `A9–A11`, and `A12–A13` for the converse |
| **M15** | `W1` excused seed files — the exact exclusion that let a second writer through in Batch 1 | widened `W1` to cover seeds |
| **M13** | nothing tested that an *unrelated* group survives a **live** repair | added `G8a–G8e`, with a fixture where two records share a mobile **and** an e-mail |

**Four of the four were defective probes, not defective code** — assertions that
would have passed whatever the code did. That is the second time in Phase 6 the
mutation battery has found the *tests* rather than the product, and it is the
argument for running it.

## 3. One equivalent mutant, and one re-anchor

**M13's first formulation was an equivalent mutant, proved not assumed.** It
deleted the *"still where the record says"* check; for an intact group the repair
then rewrites the very reference the rows already carry, so nothing observable
changes. Rather than record a survivor that cannot be caught, it was re-aimed at
what owner decision BD4 actually forbids — reaching for a similarity — which is
both non-equivalent and the real failure mode.

**M1 was re-anchored.** The adversarial pass rewrote the exact line M1 targeted
(Finding A: transaction ownership), so the mutation no longer applied and the
harness reported `ANCHOR-MISS`. That is **not** a catch and was not counted; the
mutation was re-pointed at the new line and re-run, where it is caught by
`A1 A3 A7 A8`.

## 4. What the mutation battery does not cover

- **R20** — general staff uniqueness is open by decision BD3, so there is no rule
  to mutate.
- **Repair of the other contradictory states** — detection only; there is no
  repair to defeat.
- **Reversal of a completed hire** — **R21**, not implemented.

Stated so the 18/18 is read for what it is: complete cover of what this batch
claims, and silent about what it does not.
