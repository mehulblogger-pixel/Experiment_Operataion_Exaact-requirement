# M3 CORRECTION #15 — ADVERSARIAL AUDIT

An attack on my own completed work. Probes ran against a **copy** of `phpapp/` in
the scratchpad; **no product code was modified**. Every result is measured on
**both engines**.

**Four findings. Two are material and live.** All four are in the ledger — the
thing #15 introduced to fix three older findings.

---

## D-1 · MATERIAL — two processes, one JSON blob, no locking

The ledger is a **single `settings` row holding one JSON document**, and every
write is a read-modify-write of the whole document. `settings_cache()` is loaded
**once per epoch and never revalidated** — by design, for workspace switching —
so a process cannot see anything another process wrote after its own cache loaded.

Measured, identically on SQLite and MariaDB:

| step | result |
|---|---|
| this process records condition A | ledger = {A} |
| another process (the cron tick, a second request) records condition B | the database really holds {A, B} |
| this process reads the ledger | **it cannot see B at all** |
| this process records condition C, exactly as the product does | it writes {A, C} |
| condition B | **erased — a lost update, with no error anywhere** |

**This is not a hypothetical race.** `appr_tick()` runs from cron while the
application serves requests, and both write this key. The cron pass is precisely
the one that writes the most: every recovery, every terminal closure. So the two
writers most likely to collide are the two #15 created.

What is lost is an **active fault record**: a condition that genuinely could not
be armed disappears from the ledger, its warning clears, and nothing will ever
reconcile it — because the ledger is now the only thing that knows it exists.
#14's version kept that fact on the row itself, where a concurrent writer could
not erase it. **I traded a scan for a race and did not notice.**

The store was the right choice; **the shape was not.** One row per condition —
keyed `appr_cond_<fingerprint>`, which `settings` supports natively and which its
PRIMARY KEY upsert makes atomic — would have had the same cost profile and no
lost update. I chose a blob because it was one key to write.

---

## D-2 · MATERIAL — the cap silently discards unresolved conditions

`appr_cond_ledger_save()` keeps the last 200 entries with `array_slice()`.
Measured: with 206 entries, **the oldest UNARMED condition is dropped** — neither
recovered nor closed, with no diagnostic and no TERMINAL state.

PART C of the brief says: *"Do not silently leave a permanent warning forever"*
and *"the system must transition it to an explicit terminal diagnostic state"*.
The cap does the opposite of both: it makes the warning **silently disappear**
while the row on the spine is still unarmed. Suppression for that condition stays
broken for ever, and now nothing reports it.

It also discards in the **wrong order**: oldest-first means the conditions that
have been stuck longest — the ones most in need of attention — are the first to
be thrown away.

---

## D-3 · A ledger that cannot be parsed is indistinguishable from "nothing wrong"

`appr_cond_ledger()` ends `return is_array($l) ? $l : [];`. Measured on both
engines, with the stored JSON truncated mid-value:

- the active-fault count goes to **0** — the warning silently vanishes;
- reconciliation finds **nothing to recover**;
- **not one diagnostic line** is written about the loss.

Truncation is not far-fetched: `access.php` widens `svalue` to `MEDIUMTEXT` in a
`try/catch`, and MySQL runs here **without strict mode** (`sql_mode` is set to
`PIPES_AS_CONCAT` only). On a host where that `ALTER` failed, a growing ledger is
silently truncated by the column and the entire fault register empties itself.

This is the same class of defect as X1 four corrections ago — *a failure that is
indistinguishable from success* — reintroduced in the component built to make
failures visible.

---

## D-4 · The recovery proxy is not monotonic

`NOT_RECORDED` clears when `MAX(activities.id)` exceeds the value stamped at
failure. `MAX(id)` is taken over **surviving rows**, so deleting the newest rows
moves it **backwards** — confirmed on both engines (SQLite additionally reuses
rowids).

Any deletion at the top of the table — and the codebase does delete activity rows
in more than one place — leaves the stamp above the live maximum, and that
`NOT_RECORDED` entry **can never reach TERMINAL**. It stays an active warning for
ever, which is the very defect (C-1) #15 exists to remove, reappearing through
the mechanism written to fix it.

I documented "MAX(id) is a proxy for writability" as limitation 4 and described
only the idle-workspace case. I did not consider that it can move backwards.

---

## What survived the attack

These are the load-bearing claims and they hold:

- **C-1 is genuinely closed in the single-process case.** An unarmed condition
  recovers with the condition never recurring, and the warning clears only after
  `act_set_cond_key()` has read the value back off the row. Nine mutations
  confirm it.
- **C-2 is genuinely closed.** 30 ticks, one diagnostic line, and the state
  transition is proved in the ledger rather than inferred from a repetition count.
- **C-4 is genuinely closed and the numbers are real.** MariaDB
  `type=ALL rows=20000` → `type=const rows=1`; the dashboard makes no query at
  all. Proved behaviourally by renaming the table away.
- **Tenant isolation holds** across three real databases.
- **Idempotency, partial failure and malformed candidates** all behave.

## The pattern

#12 fixed the helper, not the caller. #13 fixed the caller, not its caller. #14
connected the chain and never asked what happens when it stops running. **#15
moved the state somewhere faster and did not ask who else writes there.**

Each correction has been sound about the mechanism it examined and blind to the
environment around it — and this time the blindness produced a *new* class of
fault (concurrency) in the component built to remove old ones.

| # | finding | live? | severity |
|---|---|---|---|
| D-1 | concurrent writes silently erase active fault records | **yes** | **high** |
| D-2 | the cap silently discards the longest-stuck conditions | **yes** | **high** |
| D-3 | a corrupt or truncated ledger reads as "nothing wrong", silently | **yes** | medium-high |
| D-4 | the recovery proxy moves backwards when rows are deleted | **yes** | medium |

All four are confined to the ledger, and all four have the same smallest fix
available: **one settings row per condition** instead of one blob for all of them
— atomic by the PRIMARY KEY, uncapped in practice, individually parseable, and no
high-water mark needed.

Nothing here was fixed. No product code was changed during this audit.
H1, H3, S2, S3, U2, C-3 and A-2…A-7 all remain open.
**M3 is not accepted. M4 is not started.**
