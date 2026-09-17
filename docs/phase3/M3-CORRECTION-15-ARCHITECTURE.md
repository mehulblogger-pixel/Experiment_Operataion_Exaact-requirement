# M3 CORRECTION #15 — ARCHITECTURE

## The ledger

`settings['appr_cond_ledger']` — JSON, keyed by condition fingerprint, in the
tenant's own database.

| field | meaning |
|---|---|
| `k` | the condition key, so the marker can actually be re-armed later |
| `row` | the activity row to arm, or `0` when none was ever written |
| `st` | `UNARMED` · `NOT_RECORDED` · `TERMINAL` |
| `why` | the reason last observed — a **changed** reason may speak again |
| `maxid` | `MAX(activities.id)` when a `NOT_RECORDED` was recorded |
| `at` | when it was first recorded |

Bounded at 200 entries; 50 candidates examined per scheduler run.

## The state machine

```
          marker stored                       ┌──────────────┐
   ─────────────────────────────────────────► │  (no entry)  │  suppression armed
                                              └──────────────┘
                                                     ▲
  event written,          ┌────────────┐   reconcile │ marker persists (read back)
  marker failed  ───────► │  UNARMED   │ ────────────┘
                          └─────┬──────┘
                                │ the event row no longer exists
                                ▼
  event NOT written       ┌────────────┐      ┌──────────────┐
  at all         ───────► │NOT_RECORDED│ ───► │   TERMINAL   │  closed, NOT an active fault
                          └────────────┘      └──────────────┘
                            spine writable again (MAX(id) moved)
```

`UNARMED` and `NOT_RECORDED` are **active faults** and are counted for the
dashboard. `TERMINAL` is not — it is remembered, with its reason, so it is never
re-diagnosed, but it stops being reported as an outstanding problem.

## PART B · the recovery lifecycle, per candidate

1. the activity row still exists — else **TERMINAL**, it cannot be armed;
2. the workspace is this one — structural: one database per tenant, and the
   ledger is read from the connected database;
3. the marker is genuinely still absent — else it recovered by other means;
4. marker storage is available now — else leave it, say nothing;
5. arm it, and **read it back** — only `act_set_cond_key()` may decide, and
   `STORED` means the value is on the row (#12 · X1);
6. the ledger is updated truthfully;
7. the warning clears **only after step 5 succeeded**.

Nothing manufactures a marker, resurrects a decision, touches approval lifecycle,
or reaches outside the connected database.

## PART C · the resolved-condition policy, stated exactly

- **The event still exists** → it is re-armed, whether or not the chain is
  resolved, whether or not the condition will ever fire again. This is the C-1
  scenario and it recovers.
- **The event is gone** (deleted, trimmed, cleaned up) → **TERMINAL**, reason
  *"the event it belonged to no longer exists, so its marker cannot be re-armed"*.
  It leaves the active count; nothing claims its marker was stored.
- **The condition can no longer be identified** (no key or no row id) → TERMINAL.
- **`NOT_RECORDED`** → no row was ever written, and inventing one would be
  manufacturing history. The only honest question is whether the spine has become
  writable since, answered **read-only** by whether `MAX(id)` has moved. If it
  has → TERMINAL, *"the spine is writable again; the original event was never
  written and cannot be reconstructed"*. If not → it stays, silently.

## PART E/F · the diagnostic lifecycle

Bounded by **state**, not by luck. A line is written on **first detection**,
again only if the failure **reason materially changes** (a different fault is a
different condition, not a repeat), and **once on recovery or terminal closure**.
Never otherwise. The bound lives in `settings`, which does not depend on the
`activities` INSERT that is failing.

## PART G · where the queries went

| call | #14 | #15 |
|---|---|---|
| `appr_cond_unarmed_count()` (every dashboard load) | `COUNT(DISTINCT body) … body LIKE 'PCX|%'` — full scan | count over an already-cached settings value — **no query against `activities` at all** |
| `appr_cond_unarmed_row()` (every condition event) | `WHERE body = ?` — full scan | ledger read → the row id directly |
| reconciliation's row check | — | `WHERE id = ?` — **PRIMARY KEY** |
| `appr_condition_seen()` | `WHERE cond_key = ?` — uses the existing optional `idx_act_cond` | **unchanged** (U2 is out of scope) |

**No index was added.** The right answer was to stop asking `activities` a
question it was never indexed to answer, not to index a wrong query.
