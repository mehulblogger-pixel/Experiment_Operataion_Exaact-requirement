# M3 FINAL STABILISATION — ARCHITECTURE

## What was audited before anything was written (§B)

| existing mechanism | finding | decision |
|---|---|---|
| `settings (skey VARCHAR(60) PRIMARY KEY, svalue TEXT/MEDIUMTEXT)` | one row per key, per tenant database; upsert is `ON CONFLICT`/`ON DUPLICATE KEY` on the PRIMARY KEY, i.e. **atomic per key** | **REUSE** — the store was already right; only the *shape* was wrong |
| `settings_cache()` | loaded **once per epoch**, refreshed only on workspace switch | **CONNECT** — correct for workspace identity, wrong for state another process writes. Condition state never reads it |
| `setting_set()` | writes the value *and* the cache, and consults the audit classifier | **not used for conditions** — condition rows are written by a direct single-row upsert, so 1 000 open faults never enter the global cache |
| `appr_tick()` | the approval scheduler, per workspace, already licence-gated, already run by `cron.php` | **REUSE** — still the home of reconciliation |
| `appr_migrate()` | the module's own migration, idempotent per epoch | **REUSE** — the home of the ledger migration |
| `activities` indexes | `idx_act_partner`, `idx_act_entity`, `idx_act_when`, optional `idx_act_cond` | unchanged; **no index was added** |

**Nothing new was framed.** No second settings framework, no second audit framework,
no second notification framework, no change to the approval engine.

---

## The shape

**One settings row per condition.** Key `apprcond<32 hex>` — 40 characters, inside
`skey`'s VARCHAR(60), with no underscore at the prefix boundary so a `LIKE`
prefix needs no escaping and stays on the PRIMARY KEY.

```
settings
  skey = apprcond3f2a…            (PRIMARY KEY — atomic upsert, one condition)
  svalue = {"st":"UNARMED","k":"PC|DECISION|…","row":8123,"why":"FAILED","at":"…"}
```

| field | meaning |
|---|---|
| `st` | `UNARMED` · `NOT_RECORDED` · `TERMINAL` · `CORRUPT` |
| `k` | the condition key, so the marker can be re-armed later |
| `row` | the activity row to arm, or `0` when none was ever written |
| `why` | the reason last observed — a **changed** reason speaks again |
| `at` | when it was recorded |

There is **no shared document**, **no cap**, **no shared parse**, and **no
high-water mark**.

## Why the previous JSON ledger was unsafe

| | #15 | FINAL |
|---|---|---|
| storage | one row holding every condition | one row per condition |
| write | read-modify-write of the whole document | single-row upsert on the PRIMARY KEY |
| visibility | through a cache loaded once per epoch | read straight from the database |
| **D-1** | a concurrent writer's condition was erased | two writers cannot touch each other's row |
| **D-2** | 200-entry cap, oldest silently dropped | no cap; nothing is discarded to make room |
| **D-3** | one bad character emptied the register | each record parses alone; a bad one becomes `CORRUPT` |
| **D-4** | recovery keyed on `MAX(activities.id)` | condition-specific; no high-water mark exists |

## Consistency model (§H)

**Condition state is always read from the database and never from
`settings_cache()`.** The cache is loaded once per epoch by design — right for
workspace identity, wrong for state another process is writing concurrently.

Caching is not disabled: it keeps its behaviour for every other setting. It is
simply not on this path, and it no longer *loads* condition rows at all
(`WHERE skey NOT LIKE 'apprcond%'`), so a workspace with many open faults does
not enlarge every unrelated settings read.

## Recovery (§F/§G)

- **`UNARMED`** → the activity row is fetched by **PRIMARY KEY**, and the marker
  is re-armed through `act_set_cond_key()`, which reads the value back (#12 · X1).
  `STORED` is the only thing that clears the warning.
- **Row gone / unidentifiable** → `TERMINAL`, with a truthful reason. It leaves
  the active count, is remembered so it is never re-diagnosed, and no marker is
  ever manufactured.
- **`NOT_RECORDED`** → the original event was never written and reconstructing it
  would be manufacturing history. Recovery is **condition-specific**: one record
  is attempted for *this* condition. If the spine accepts it the fault is over and
  the condition closes as `TERMINAL`; if not, nothing is written and nothing is
  said. `MAX(activities.id)` is not consulted anywhere.
- **`CORRUPT`** → reported, counted as an active fault, and never silently ignored.

## Migration (§N)

Runs inside `appr_migrate()`. Each valid entry of the old shared document becomes
its own row; the document is deleted **only after** everything it held has been
written, so a repeat run is a no-op and an interrupted run resumes. An
**unparsable** old document is **left in place** and reported — discarding it
would be the silent loss this stage exists to remove. No activity row is ever
deleted.
