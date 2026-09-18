# Phase 5 — Action Path Matrix

*Every path that can change a recruitment figure, and what now happens on it.
The recurring defect family in this programme has been a rule applied where
somebody remembered to apply it — so every path is listed, including the ones
that needed no change.*

## Paths that WRITE the stage ledger

| Path | Before Phase 5 | After |
|---|---|---|
| Candidate stage change (`candidate-stage` route) | Wrote a raw event **after** the M6 and Phase 4 compensators — so a reverted move left **no entry at all**, because the gate redirects and exits | Writes through the canonical writer **at the moment the move happens**, with stage code, ladder and kind |
| Candidate intake (`candidate-new`) | Wrote a raw event | Canonical writer · `LEGACY` / `RECEIVED` / `MOVE` |
| Careers-page application | Wrote a raw event | Canonical writer · same, wording unchanged |
| Pipeline advance / back / jump | Wrote the stage **name** only | Canonical writer · plus the stage's stable **key** and `PIPELINE` track |
| Pipeline workflow switch | Wrote a `"Workflow: …"` string | Canonical writer · marked `SWITCH`, so no duration is measured across it |
| **Offer issued** | **Moved the candidate to OFFERED and wrote NOTHING** | Canonical writer · `LEGACY` / `OFFERED` / `MOVE` |
| **Execution gate reverting a joining** | **Moved the candidate back and wrote NOTHING** — the ledger still ended at ACCEPTED, claiming a hire | Canonical writer · marked `REVERT` |

The last two are the defects the pre-implementation audit measured. Both were
proved by driving the production paths and reading the database, not by reading
the source.

## Paths that READ a recruitment figure

| Surface | Before | After |
|---|---|---|
| Recruitment Command Centre — headline demand | Its own arithmetic over `quantity`; no concept of cancellation or allocation | Reads `rkpi_demand()` |
| Command Centre — "needs attention" list | `quantity − filled` | Approved − filled |
| Command Centre — requirement tracker | `quantity`, `quantity − filled` | Approved headcount, and profit-per-head still divided by the original ask (that is a commercial rate, not demand) |
| Command Centre — project headcount | `quantity` | Approved headcount |
| Command Centre — recruiter table | Delivery from the **current** recruiter column | *Carrying* from the current column; *delivered* from the M5 ledger, with the unattributable shown |
| Command Centre — time to hire | Its own date subtraction from `created_at` | The canonical helper and the one published definition |
| Command Centre — join trend, filled counts | Literal `'ACCEPTED'` | M3's `REQF_FILLED_STAGES` |
| Recruitment home — "3 of 10" work list | `quantity` | Approved headcount |
| CSV exports | Consume `rcc_data()` | Unchanged — they inherit the correction automatically |
| Analytics / dashboards / scorecards | No recruitment metrics existed | Eleven registered in TAPI, entitlement-gated |

## Paths Phase 5 deliberately did NOT change

| Path | Why |
|---|---|
| `rasg_workload()` (M5) | It answers *what am I carrying now*, and the current column is the correct source for that question |
| `reqf_counts()`, `reqf_sync()` (M3) | They are the owners. Phase 5 asks them |
| `rful_summary()` (Phase 4) | Same |
| `appr_sla_*` | The approval engine owns SLA |
| `is_working_day()`, `next_working_day()` | The scheduling module owns the branch calendar |
| Any seat, approval, ownership or execution decision | Out of scope, and out of bounds |

## A carried-forward observation

`lib/recruit_assign.php` (M5) also spells out `stage='ACCEPTED'` as a literal
rather than reading `REQF_FILLED_STAGES`. **There is no behavioural difference
today** — `REQF_FILLED_STAGES` is the single value `ACCEPTED` — so this is not a
defect and was not changed inside a locked milestone. It is recorded here as a
tidiness item for whenever M5 is next opened.
