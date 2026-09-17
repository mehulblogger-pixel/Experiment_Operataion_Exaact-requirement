# M3 CORRECTION #15 — TEST RESULTS

Suite: `phpapp/tests/test_p3m3c15_recover.php` — **72 assertions**.

| run | engine | result |
|---|---|---|
| focused C15 suite | SQLite 3.45.1 | **72 passed, 0 failed** |
| focused C15 suite | MariaDB 10.11.14 | **72 passed, 0 failed** |
| **full regression** | SQLite | **10455 passed, 0 failed** |
| **full regression** | MariaDB | **10456 passed, 0 failed** |

No test was weakened, deleted or skipped; no skip was introduced.
**No engine difference in any #15 behaviour.**

---

## C15.1 — the exact scenario the attacker must be able to reach

`UNARMED` exists **and** the condition will never recur **and** storage is later
repaired. The condition writers are never called again, so #14 could not have
recovered this in any number of ticks.

- reconciliation recovers it **without the condition recurring**;
- the marker is **read back from the table** — `cond_key` genuinely begins `PC|`;
- **only then** does the warning clear;
- **no new row** was invented to do it;
- and ordinary suppression is proved to work again afterwards.

## C15.2 — PART C, the source is gone
The event row is deleted. Reconciliation closes the condition as **TERMINAL**, it
leaves the active count, it is **not forgotten** (the ledger keeps it with a
truthful reason), and **no marker is manufactured** for an event that does not
exist.

## C15.3 — PART D, idempotency
Ten runs: the first recovers, the tenth examines nothing and recovers nothing;
**not one extra activity row**, not one extra marker, and the warning count never
moves again.

## C15.4 / C15.5 — PART E/F, bounded by proven state
30 ticks with the `activities` INSERT failing: every tick `NOT_RECORDED`, no rows
written, and **exactly ONE diagnostic line** (C-2 measured 30).

The state transition is proved, not merely the repetition count: the ledger holds
that condition in the `NOT_RECORDED` state with its `maxid` evidence, and
`SELECT COUNT(*) FROM settings WHERE skey='appr_cond_ledger'` is 1 — **the fact
survives without any `activities` INSERT**. Then one ordinary event proves the
spine writable, reconciliation closes it as TERMINAL with **one** signal, and ten
further runs do not repeat it.

## C15.6 — PART G/L, proved behaviourally
Not a source search. The **`activities` table is renamed away**, and the dashboard
count and the gate lookup both still answer correctly — a path that still touched
`activities` could not. Terminal entries are excluded from the count.

## C15.7 — PART J, attacking reconciliation
- **case 1** storage still unavailable → nothing recovered, one still unresolved,
  **no false marker**, warning still shown;
- **case 3** a malformed entry and an unidentifiable one → the good candidate
  still recovers with its marker genuinely on the row; the malformed entry is
  dropped; the unidentifiable one is **closed, not silently re-armed**.

## C15.9 — PART J case 2, partial failure across candidates
Two unarmed conditions; the marker is blocked for **one of them only**. Exactly
one recovers and carries its marker; the other is **not** falsely marked
recovered and stays in the ledger; one warning clears and one correctly still
shows; a later pass finishes the job.

## C15.8 — PART I, real tenant isolation
`db(true)` switching; the database names itself; **DB A ≠ DB B ≠ DB C**. With A
holding a genuine unarmed condition, B and C can neither **count** it, **inspect**
it, nor **recover** it — reconciliation there examines nothing. A is returned
exactly as A left it, then recovers **its own** condition and only its own.

---

## PART L — performance evidence, measured on 20 000 activity rows

| | query | SQLite plan | SQLite | MariaDB plan | MariaDB |
|---|---|---|---|---|---|
| **#14** | `COUNT(DISTINCT body) … body LIKE 'PCX|%'` | `SCAN activities` + temp B-tree | 1.446 ms | `type=ALL key=NULL rows=20000` | 5.643 ms |
| **#15** | `SELECT svalue FROM settings WHERE skey=?` | `SEARCH settings USING INDEX (skey=?)` | 0.006 ms | `type=const key=PRIMARY rows=1` | 0.063 ms |
| **#15 as the dashboard calls it** | `appr_cond_unarmed_count()` | cached — no query | **0.0012 ms** | cached — no query | **0.0014 ms** |

The full table scan is gone on both engines: MariaDB moves from examining 20 000
rows to 1, SQLite from a table scan to an index search. No index was added.

---

## A test defect of my own, found by mutation and reported

`M15-7` — *recovering one candidate marks them all recovered* — **survived the
first battery**. C15.7 case 3 had only **one** recoverable candidate, so "wipe
all" and "wipe one" were indistinguishable. PART J case 2 asks for exactly this
case and I had not built it. **C15.9** now runs two candidates with one blocked,
and the mutation is caught.

A second, smaller one: C15.6's fixture first invented ledger keys like
`PCX|probe1` and then asked for the row of `PC|PROBE|1`, whose fingerprint is a
sha1 — the lookup correctly found nothing and the probe blamed the product for
its own fixture. It now computes the real fingerprints.
