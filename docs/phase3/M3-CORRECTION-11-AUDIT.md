# Phase 3 · M3 CORRECTION #11 — AUDIT (before any code was written)

## 1 · W1 — the audit found FOUR channels, not one

§1 asks for every writer and reader of the core audit error. Searching the spine:

| Channel | What it reports | Before #11 |
|---|---|---|
| `__act_last_error` | `act_log()`'s **core INSERT** failure | plain process global |
| `__act_cond_column_error` | the `cond_key` **column** migration | plain process global |
| `__act_cond_index_error` | the `cond_key` **index** migration | plain process global |
| `__act_optional_error` | the `cond_key` **UPDATE** | plain process global |

W1 names the first. All four have the identical defect. Correction #10 had made
the **attempt ledger** per workspace epoch and left every **error** channel global
— so fixing only the reported one would repeat the pattern every audit in this
sequence has found. All four are keyed.

## 2 · Readers — the exposure, sized honestly

```
production readers of act_last_error() / act_optional_error() / act_optional_state()
outside lib/activity.php:   (none)
```

Only tests read them today. W1's exposure is therefore **latent** — it becomes live
the moment a diagnostic screen, support page or health check reads the channel,
which is exactly what these functions exist for. That sizes the finding; it does
not excuse it. The defect is in the contract, not in today's callers.

## 3 · Where the workspace boundary comes from

`db_epoch()` — already the boundary every migration guard, settings cache and the
attempt ledger uses. In-process switching happens in "Log in as", provisioning and
cron tenant sweeps, all of which go through `db(true)`, which bumps it.

No new context object, no new diagnostic framework: the smallest change consistent
with the architecture is to key the error channels on the same epoch.

## 4 · Keyed, not cleared — and deliberately single-slot (§4)

Entering a workspace **wipes nothing**; it simply cannot see what belongs to
another. The store holds the **most recent** error per channel and reveals it only
to the workspace that produced it.

One entry *per workspace* was considered and rejected: that is the **"global
historical error store"** §4 forbids. The consequence is stated rather than hidden
— a workspace whose entry has since been superseded reads **empty**: never stale,
never somebody else's.

## 5 · W2 — the writer's contract (§6, §8)

`act_set_cond_key()` returned `true`/`false`, so *"nothing has tried to create the
column"* and *"the write was attempted and failed"* were the same answer. That is
V1's sentence — **ABSENT IS NOT FAILED** — on the writer side, which correction #10
left standing because it fixed only the instance that had been reported.

§8 lists five separate questions, so the contract distinguishes four outcomes:

| | |
|---|---|
| `STORED` | the UPDATE ran and succeeded |
| `FAILED` | the UPDATE ran and failed — **the write's own** failure |
| `UNAVAILABLE` | the column was attempted and cannot be made |
| `NOT_ATTEMPTED` | nothing was attempted at all |

**Callers.** Only one production caller (`act_log()`, which ignores the result —
the core row is what matters) and four test assertions. The 74 ordinary `act_log()`
callers are untouched, as §6 requires.

**Why a status constant, not a boolean or an int:** a truthy `'FAILED'` would be a
trap for any future `if (act_set_cond_key(...))`. Every caller and assertion
compares explicitly against `ACT_COND_STORED`.

## 6 · Core audit stays primary (§9)

Nothing in this correction touches the order established by correction #8: the core
row is written first, and optional metadata cannot reach back and undo it. Mutation
`W2-M4` exists to hold that line.

## 7 · Scope (§13)

W1 and W2 only. H1, H3, S2, S3 and U2 untouched. No redesign of the audit engine,
workspace switching or approval.
