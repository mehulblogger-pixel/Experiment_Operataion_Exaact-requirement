# M3 CORRECTION #15 — COMPLETION REPORT

The twenty required points, in order.

### 1 · Existing recovery/reconciliation mechanisms audited
`appr_tick()` (the approval scheduler, run per workspace by `cron.php`);
`joblock_sweep_daily()`; `saas_tenant_seen_alive_sweep()`;
`act_cond_column_ready()` (the optional-column repair ledger, bounded per epoch);
`settings` + `settings_cache()` + `setting_change_class()`; and the four existing
indexes on `activities`. Full table in AUDIT §PART A.

### 2 · Selected architectural home
**Reconciliation** lives in `appr_tick()` — the approval module's own scheduler,
already per-workspace and already licence-gated. No new job, no new entry point.
**The ledger** lives in `settings`, in the tenant's own database, keyed by
`skey` PRIMARY KEY and read through the existing in-memory cache. `settings` was
chosen because the case that breaks `activities` is exactly the case that must be
recorded, so the record cannot live in `activities`.

### 3 · UNARMED recovery lifecycle
Seven ordered steps per candidate — row exists → workspace is this one → marker
genuinely absent → storage available → arm **and read back** → ledger updated →
**warning clears only after the read-back succeeded**. Full text in ARCHITECTURE.

### 4 · Resolved-condition policy
Event still exists → re-armed, regardless of whether the chain is resolved.
Event gone → **TERMINAL** with a truthful reason, removed from the active count,
never re-diagnosed, no marker manufactured. Unidentifiable → TERMINAL.
`NOT_RECORDED` → TERMINAL once `MAX(id)` proves the spine writable again,
because the original event cannot be reconstructed and inventing one would be
manufacturing history.

### 5 · Reconciliation idempotency
Ten runs: one recovery, then nothing. **No extra activity row, no extra marker,
no repeated recovery message, no repeated warning, no change to approval
history.** Proved in C15.3.

### 6 · NOT_RECORDED diagnostic lifecycle
First failure → one signal. Second tick → nothing. Thirtieth tick → nothing
(**measured: exactly 1 line for 30 ticks; C-2 measured 30**). Recovery → one
signal, and ten further passes do not repeat it. A **materially changed reason**
is treated as a new diagnostic condition and speaks again; unrelated failures are
never collapsed into one permanent state.

### 7 · Dashboard query changes
`appr_cond_unarmed_count()` no longer queries `activities` at all; it counts over
an already-cached settings value. `appr_cond_unarmed_row()` no longer scans
`body`; it reads the row id from the ledger. Reconciliation's row check is a
PRIMARY KEY lookup.

### 8 · Indexes involved
`settings.skey` PRIMARY KEY (pre-existing) and `activities.id` PRIMARY KEY
(pre-existing). `idx_act_cond (cond_key)` continues to serve
`appr_condition_seen()`, unchanged. **No index was added** — the right answer was
to stop asking `activities` a question it was never indexed to answer.

### 9 · Performance evidence
20 000 activity rows. MariaDB: `type=ALL key=NULL rows=20000` (5.643 ms) →
`type=const key=PRIMARY rows=1` (0.063 ms). SQLite: `SCAN activities` (1.446 ms)
→ `SEARCH settings USING INDEX` (0.006 ms). As the dashboard actually calls it:
**0.0012 ms / 0.0014 ms**, no query. Table in TEST-RESULTS §PART L.

### 10 · Tenant isolation evidence
Real `db(true)` switching, the database naming itself, **DB A ≠ DB B ≠ DB C**.
B and C can neither count, inspect nor recover A's condition — reconciliation
there examines nothing. A then recovers only its own. C15.8.

### 11 · Failure-during-recovery evidence
Case 1 (storage still unavailable): nothing recovered, no false marker, warning
still shown. Case 2 (partial failure across candidates): exactly one recovers,
the blocked one is **not** falsely marked recovered and stays for the next pass.
Case 3 (malformed/unidentifiable candidate): the good candidate still recovers;
the malformed entry is dropped; the unidentifiable one is closed, not re-armed.

### 12 · SQLite results
Focused **72 passed, 0 failed**. Full regression **10455 passed, 0 failed**.

### 13 · MariaDB results
Focused **72 passed, 0 failed**. Full regression **10456 passed, 0 failed**.
MariaDB is authoritative. No engine difference in any #15 behaviour.

### 14 · Mutation results
**Attempted 9 · Caught 9 · Survived 0.** No inherited count is claimed.

### 15 · Exact mutation assertions

| mutation | detected by |
|---|---|
| `M15-1` reconciliation removed | `C15.1 · reconciliation recovered it WITHOUT the condition recurring` — want 1, got 0 |
| `M15-2` warning clears without the marker persisting | `C15.7 · case 1 · nothing is recovered while storage is still broken` — want 0, got 1 |
| `M15-3` the same condition recovered twice | `C15.1 · only now does the warning clear` — want 0, got 1 |
| `M15-4` unbounded NOT_RECORDED logging | `C15.4 · 30 ticks produced exactly ONE diagnostic line` — want 1, got 30 |
| `M15-5` tenant B reconciles A's condition | `C14.G · B's screen shows none of A's unresolved conditions` — want 0, got 5 |
| `M15-6` dashboard full-table scan restored | `C15.6 · with the activities table GONE the dashboard count still answers` — want 2, got 0 |
| `M15-7` one recovery marks all recovered | `C15.9 · one warning cleared, one correctly still showing` — want 1, got 0 |
| `M15-8` terminal counted as an active fault | `C15.2 · it stops being reported as an ACTIVE fault` — want 0, got 1 |
| `M15-9` a vanished event silently re-armed | `C15.2 · its event is gone, so it is closed as unrecoverable` — want 1, got 0 |

`M15-7` **survived the first battery**; the reason and the fix are reported in
TEST-RESULTS.

### 16 · Full regression
SQLite **10455/0**, MariaDB **10456/0**. No regression in approval workflow,
approval inbox, SLA, scheduler, notification, audit/activity, Recruitment Command
Centre, tenant isolation, Operations, Quality, Money or Marketplace.

### 17 · Files changed
- `phpapp/lib/recruit_approval.php` — the ledger, the TERMINAL state, the bounded
  diagnostic, `appr_cond_reconcile()`, the two scan-free lookups, and
  reconciliation wired into `appr_tick()`.
- `phpapp/lib/access.php` — one key added to the non-audited cron-marker list.
- `phpapp/cron.php` — the run reports what reconciliation recovered and closed.
- `phpapp/tests/test_p3m3c15_recover.php` — 72 assertions.
- `phpapp/tests/test_p3m3c13_consume.php`, `test_p3m3c14_chain.php` — each clears
  the ledger it creates (see *Known limitations* 1).
- `phpapp/deploy_check.php` — regenerated.
- `docs/phase3/M3-CORRECTION-15-{AUDIT,ARCHITECTURE,TEST-RESULTS,COMPLETION-REPORT}.md`.

No permission, role, status or lifecycle transition changed, so `docs/01-roles.md`,
`docs/02-permission-matrix.md` and `docs/03-object-lifecycles.md` are unchanged
and still agree with the code.

### 18 · Commit
Branch `claude/testing-branch-setup-0gqe8n`, commit titled *M3 correction #15 —
condition failure recovery, bounded diagnostics and scan control*.

### 19 · Known limitations
1. **The ledger outlives the rows.** Deleting an activity row leaves its ledger
   entry behind. That is handled — reconciliation closes such an entry as
   TERMINAL — but it also means a test suite that deletes its rows must clear the
   ledger, and two existing suites were updated to do so.
2. **Recovery is only as frequent as the scheduler.** A repaired workspace clears
   on the next `appr_tick()`, not instantly. There is no on-demand re-run.
3. **`NOT_RECORDED` closes as TERMINAL, it does not restore the lost event.** The
   event was never written and cannot be reconstructed; the ledger records that
   truthfully rather than inventing history.
4. **`MAX(id)` is a proxy for writability.** If nothing at all is written in a
   workspace after the failure, a `NOT_RECORDED` entry stays active — correctly,
   but it means a completely idle workspace does not self-clear.
5. **The ledger is capped at 200 entries**; beyond that the oldest are dropped.
6. **The `settings` write is not transactional with the marker write.** A crash
   between them leaves a stale entry, which the next pass resolves.
7. **C-3 is deliberately untouched** — the fingerprint is still written to `body`.
   It is simply never searched for.

### 20 · Remaining M3 findings
Untouched by instruction: **H1**, **H3**, **S2**, **S3**, **U2**, and **C-3**.
From the #12 audit, still open: **A-2**, **A-3**, **A-4**, **A-5**, **A-6**,
**A-7**.

**M3 is NOT accepted. M4 is NOT started. Correction #15 is ready for independent
adversarial attack only.**
