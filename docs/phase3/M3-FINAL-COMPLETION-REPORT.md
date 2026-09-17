# M3 FINAL COMPLETION REPORT

The twenty-two required points, in order.

### 1 · Final condition-state architecture
**One `settings` row per condition**, key `apprcond<32 hex>` (40 chars, inside
`skey`'s VARCHAR(60)), value a single JSON record `{st, k, row, why, at}` with
states `UNARMED`, `NOT_RECORDED`, `TERMINAL`, `CORRUPT`. The upsert is on the
PRIMARY KEY, so it is atomic per condition. No shared document, no cap, no shared
parse, no high-water mark. Reconciliation stays in `appr_tick()`; migration stays
in `appr_migrate()`. Full detail in ARCHITECTURE.

### 2 · Why the previous JSON ledger was unsafe
Every condition lived in one row, rewritten whole from a snapshot taken through a
cache that is loaded once per epoch. That single shape produced all four defects:
a concurrent writer's condition was erased (D-1); a 200-entry cap silently dropped
the oldest unresolved one (D-2); one bad character emptied the register and empty
read as "nothing wrong" (D-3); and recovery keyed on `MAX(activities.id)`, which
moves backwards when rows are deleted (D-4).

### 3 · Migration approach
Additive, idempotent, non-destructive, tenant-safe, repeatable; runs in
`appr_migrate()`. Valid entries become their own rows; the old document is removed
**only after** everything it held was written; a repeat is a no-op; an unparsable
document is **left in place** and reported. No activity row is ever deleted.

### 4 · Concurrency proof
Real separate `php` processes with their own connections. Two conditions → both
survive. Reverse order → both survive. **Eight processes at once → all eight
survive.** Four writers on one condition → one row. The final persisted state is
asserted, never the shape of the code.

### 5 · No-cap proof
199, 200, 201, 500 and 1000 conditions stored. At every size the count is exact,
the **oldest unresolved condition is still present and still recoverable**, and
all 1000 are reported as active faults.

### 6 · Corruption isolation proof
One unparsable record among three: it is reported `CORRUPT`, the other two are
untouched, the active-fault count **does not become zero**, reconciliation reports
it rather than ignoring it, a diagnostic exists, and the dashboard renders.

### 7 · Recovery proof
The marker is re-armed through `act_set_cond_key()`, which reads the value back;
`STORED` is the only thing that clears a warning. Where the event is gone the
condition becomes `TERMINAL` with a truthful reason and no manufactured marker.

### 8 · No-recurrence recovery proof
The condition never fires again; reconciliation recovers it anyway, the marker is
genuinely persisted, and the warning clears.

### 9 · Cache consistency proof
This process holds a loaded settings cache; a worker process writes a condition;
**this process sees it**, and writing a third does **not** erase the worker's.
Caching is not disabled — condition state simply never reads it, and the cache no
longer loads condition rows.

### 10 · Tenant isolation proof
Real `db(true)` switching, the database naming itself, **DB A ≠ DB B**. B cannot
count, see or read A's conditions; B's settings hold none; reconciling in B changes
nothing of A's; B's delete attempt and a real B worker leave A with both of its
own, and B's condition never reaches A.

### 11 · Performance / query proof
20 000 activity rows, 500 stored conditions. MariaDB: the #14 dashboard query was
`type=ALL key=NULL rows=20000` at 5.796 ms; it is now `type=range key=PRIMARY
rows=500 Using index` at 0.214 ms, and a single-condition read is a PRIMARY KEY
const at 0.068 ms. SQLite: `SCAN activities` (1.477 ms) → covering index (0.039 ms)
→ index search (0.005 ms). **No index was added.** In a healthy workspace there are
zero condition rows.

### 12 · Idempotency proof
1, 2, 3, 10 and 30 further passes: no duplicate recovery row, no duplicate
notification, no duplicate warning, no duplicate permanent record. One
authoritative state per condition.

### 13 · Partial-failure proof
A recoverable, B malformed, C blocked, D recoverable → **A and D recover**, B is
explicitly diagnosable, C stays unresolved, and B stops neither A nor D. No global
success result — only counts.

### 14 · Crash / concurrent-writer proof
Eight simultaneous writers; four writers on the same condition; a worker writing
while this process holds a stale cache. In every case the surviving condition does
not disappear because another process wrote its own.
**Limitation, stated rather than claimed:** a mid-statement crash cannot be forced
from PHP, so the closest real failure mode was used — genuinely concurrent
processes on independent connections, plus a write that fails inside a trigger.
A single-row upsert is atomic at statement level on both engines, so there is no
partially-written condition record to observe.

### 15 · SQLite results
Suite **78/0**. Complete regression **10534/0**.

### 16 · MariaDB results
Suite **78/0**. Complete regression **10535/0**. MariaDB is authoritative.
No behavioural difference; the two implementation differences are named in
TEST-RESULTS rather than averaged away.

### 17 · Mutation results
**Attempted 10 · Caught 10 · Survived 0.** No inherited count is claimed.

### 18 · Exact mutation assertions

| mutation | detected by |
|---|---|
| `M-F1` shared read-modify-write restored | `FS.2 · eight processes writing eight conditions at once: all eight survive` — want 8, got 5; and `FS.4 · writing a third does NOT erase the other process's` — want 3, got 2 |
| `M-F2` 200-entry cap restored | `FS.5 · with 201 conditions stored` — want 201, got 200 |
| `M-F3` corrupt treated as empty | `FS.6 · the corrupt one is reported as CORRUPT, not as absent` — want CORRUPT, got TERMINAL |
| `M-F4` `MAX(activities.id)` recovery restored | `C15.2 · its event is gone, so it is closed as unrecoverable` — want 1, got 0 |
| `M-F5` reads go back through the per-epoch cache | `C13.3 · it reports PENDING_RETRY — still unarmed, and it says so` — want PENDING_RETRY, got UNARMED |
| `M-F6` tenant scoping removed | `C15.1 · the dashboard warning is showing` — want 1, got 6 |
| `M-F7` reconciliation handles only the recurring condition | `C15.1 · reconciliation recovered it WITHOUT the condition recurring` — want 1, got 0 |
| `M-F8` one corrupt condition aborts reconciliation | `C15.7 · case 3 · the good candidate still recovers` — want 1, got 0 |
| `M-F9` recovery leaves the record behind | `C15.1 · only now does the warning clear` — want 0, got 1 |
| `M-F10` dashboard full-table scan restored | `C15.6 · with the activities table GONE the dashboard count still answers` — want 2, got 0 |

`M-F1`'s **first form survived and the mutation was at fault** — it re-read fresh
from the database, which is not what #15 did. Reported in full in TEST-RESULTS.

### 19 · Complete M3 regression
SQLite **10534/0**, MariaDB **10535/0**. Hiring Request approval, approval matrix,
authority, delegation, inbox, SLA, reminders, escalation, notifications, audit,
condition suppression, recovery, scheduler, dashboard and tenant isolation all
green, together with Operations, Quality, Reporting, Money, Workforce, Marketplace
and CRM. No unrelated product behaviour changed.

### 20 · Files changed
- `phpapp/lib/recruit_approval.php` — the per-condition store, `CORRUPT`, the
  migration, condition-specific recovery, reconciliation over per-condition records.
- `phpapp/lib/access.php` — the settings cache no longer loads condition rows; the
  key prefix is classified as a non-audited marker.
- `phpapp/tests/test_p3m3fs_stabilise.php` — 78 assertions.
- `phpapp/tests/_fs_worker.php` — the real concurrent worker process.
- `phpapp/tests/test_p3m3c13_consume.php`, `test_p3m3c14_chain.php`,
  `test_p3m3c15_recover.php` — ported to the new store; two obsolete assertions
  re-pointed (`maxid` no longer exists; a corrupt record is now kept, not dropped).
- `phpapp/deploy_check.php` — regenerated.
- `docs/phase3/M3-FINAL-STABILISATION-{ARCHITECTURE,TEST-RESULTS,ADVERSARIAL-READY}.md`
  and this report.

No permission, role, status or lifecycle transition changed, so `docs/01-roles.md`,
`docs/02-permission-matrix.md` and `docs/03-object-lifecycles.md` are unchanged and
still agree with the code.

### 21 · Commit
Branch `claude/testing-branch-setup-0gqe8n`, commit titled *M3 final stabilisation
— one settings row per condition*.

### 22 · Known limitations
1. **`appr_cond_outcome()` is a proximity contract** — it reads state left by the
   immediately preceding `act_log()`. Nothing violates it today; nothing enforces it.
2. **`appr_cond_unarmed_count()` decodes every open record** on each call — free at
   zero records, ~1 ms at 500. It is not paginated.
3. **`NOT_RECORDED` recovery writes one activity row** as its proof of writability;
   bounded and idempotent, but it is a write.
4. **Two writers on the same condition**: one row, last-writer-wins on content.
   Proved not to duplicate; not proved to merge.
5. **`TERMINAL` records are never pruned** — deliberately, because pruning risks
   exactly the silent loss D-2 was about. They are small and inert.
6. **The settings cache exclusion is a global change** to a shared function, made
   for one caller.
7. **A mid-statement crash cannot be forced from PHP** — see point 14.
8. **C-3 is untouched by instruction**: the fingerprint is still written to `body`.

**M3 is NOT self-accepted.** It is submitted for one final independent adversarial
acceptance gate. **M4 is not started.** H1, H3, S2, S3, U2, C-3 and A-2…A-7 remain
open and were not reopened here.
