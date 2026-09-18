# Phase 5 — Reconciliation Results

*A number on a screen is only true if the records underneath it say the same
thing, for the same population, within the same authorised scope.*

## The disagreement this phase was created to remove

Measured **before** any code was changed, by driving a fixture through the
production paths: ten people approved, four vacancies given up, three joined, two
seats promised to an agency.

| Question | M3 `reqf_counts` | Phase 4 `rful_summary` | Command Centre |
|---|---|---|---|
| Requested | 10 | — | 10 |
| Cancelled | 4 | — | *not read* |
| **Approved headcount** | **6** | **6** | **10** ✗ |
| Filled | 3 | 3 | 3 |
| **Open positions** | **3** | **3** | **7** ✗ |
| Promised to a source | — | 2 | *no such concept* ✗ |

**Seven open positions where the records said three.**

## The same fixture, after

| Question | M3 | Phase 4 | KPI engine | Command Centre |
|---|---|---|---|---|
| Requested | 10 | — | 10 | 10 |
| Cancelled | 4 | — | 4 | 4 |
| Approved headcount | 6 | 6 | 6 | 6 |
| Filled | 3 | 3 | 3 | 3 |
| **Open positions** | **3** | **3** | **3** | **3** |
| Promised to a source | — | 2 | 2 | 2 |
| Not yet sourced | — | 1 | 1 | 1 |
| Over-promised | — | 0 | 0 | 0 |

Probes A1–A13 and K4–K4d. The dashboard figure is not *compared* to the engine's
— it **is** the engine's; K4 asserts identity, so the two cannot drift apart
again by anybody editing one of them.

## The aggregate equals the sum of its owners

A dashboard cannot afford one round trip per requirement, so demand is aggregated
set-based. That is only legitimate if the aggregate provably equals the sum of
the per-record owners. Section B asserts exactly that, **row by row**, over a set
deliberately containing:

- a requirement with cancelled vacancies,
- a requirement **over-filled** (six joined against five approved),
- candidates in progress and candidates lost.

| Figure | Sum of `reqf_counts()` per row | `rkpi_demand()` aggregate |
|---|---|---|
| requested | equal | equal |
| cancelled | equal | equal |
| filled | equal | equal |
| remaining | equal | equal |
| in_progress | equal | equal |
| lost | equal | equal |

And the assertion that matters most (B3): **an over-filled requirement never
cancels out a short one.** Netting at the set level would report fewer open
positions than exist; every figure is therefore computed per requirement before
it is summed.

## Engine agreement

| | SQLite 3.45.1 | MariaDB 10.11.14 |
|---|---|---|
| Full regression | **12,252 passed, 0 failed** | **12,255 passed, 0 failed** |

Both were run to completion. MariaDB is authoritative for production-oriented
evidence; neither figure is inferred from the other. The two differ by three
assertions because some probes are driver-conditional.

## The clamps, against data the ordinary paths cannot produce

| State | Where it comes from | Result |
|---|---|---|
| Five joined against two seats | A legacy import, or a requirement edited *down* after people joined | Reports **2** filled, **0** remaining — a seat cannot be filled twice (K1) |
| Nine cancelled against three requested | A corrupted or partially-migrated row | Reports **3** cancelled, **0** approved — never negative (K2) |
| A released promise | Ordinary business, but easy to keep counting | Holds **no** seats (K3) |
