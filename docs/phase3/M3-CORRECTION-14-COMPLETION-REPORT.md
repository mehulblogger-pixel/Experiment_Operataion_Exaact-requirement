# M3 CORRECTION #14 — COMPLETION REPORT

The twenty-two required points, in order.

### 1 · Exact ORIGINAL production chain
```
appr_tick()  /  appr_email_requester()
      │   (result DISCARDED at all 5 call sites)            ← B-1
      ▼
appr_audit_notify() / appr_audit_sla()
      │   appr_condition_key() → appr_condition_seen() → appr_audit_subject()
      ▼
act_log(…, ['cond_key' => …])
      │   status carried, ROW ID NOT carried                ← B-3
      ▼
act_set_cond_key()   truthful
      ▼
appr_cond_outcome()  → UNARMED whenever the status was not STORED,
                       INCLUDING when no row had been written at all,
                       and it logged "the event itself was recorded"   ← B-3
      ▼
one new audit row and one identical log line PER SCHEDULER TICK, for ever ← B-4, B-5
      ▼
nothing a business user could see                                   ← B-2
```

### 2 · Exact CORRECTED production chain
```
appr_tick()   → captures every outcome, counts what it could not arm,
                appr_tick_unarmed() → cron.php PRINTS it
appr_email_requester() → appr_email_cond_note() at all three sites
      ▼
appr_audit_notify() / appr_audit_sla()
      │   appr_cond_gate():  SUPPRESS (armed) · RETRY (unarmed row exists) · WRITE
      ▼
act_log(…, ['cond_key' => …, 'body' => appr_cond_fingerprint(…)])
      │   act_cond_status_set($status, $rowId)     ← the status travels with its row
      ▼
appr_cond_outcome():
        row == 0                  → NOT_RECORDED   (C)
        row > 0 && STORED         → RECORDED       (A)
        row > 0 && not STORED     → UNARMED        (B)
   appr_cond_retry():
        marker now writes         → RECOVERED
        still cannot              → PENDING_RETRY  (no new row, no log line)
   appr_cond_gate() earlier       → SUPPRESSED     (D)  /  NONE, NO_SUBJECT (E)
      ▼
ONE row ever per unarmed condition; the marker is retried on that row
      ▼
appr_sla_summary()['suppression_unarmed'] → Recruitment Command Centre warning
```

### 3 · B-1 fix
All five call sites above the corrected writers now consume the outcome: the two
`appr_audit_sla()` sites inside `appr_tick()` count it, and the three
`appr_audit_notify()` sites in `appr_email_requester()` pass it through
`appr_email_cond_note()`. The outcome **affects a real production decision** —
`appr_cond_gate()` decides whether a row is written at all — and it **leaves the
system**: `cron.php` prints what the run could not arm.

### 4 · B-3 contract correction
The status now travels with the id of the row it is about
(`act_cond_status_set($st, $row)`, `act_last_cond_row()`). Five states describe
what happened: `RECORDED` (A), `UNARMED` (B), `NOT_RECORDED` (C), `SUPPRESSED`
(D), `NONE`/`NO_SUBJECT` (E), plus `PENDING_RETRY` and `RECOVERED` for the bounded
path. Every diagnostic line is **generated from the status**, so the words "the
event was recorded" appear only under `UNARMED`, where a row provably exists.

### 5 · B-4 bounded-failure behaviour
- **First failure:** the event is written once, the marker is attempted and fails,
  the row carries the condition fingerprint, the caller gets `UNARMED`, the
  diagnostic is written **once**.
- **Subsequent ticks:** the condition is recognised by its fingerprint. **No new
  event, no new diagnostic.** The marker is retried on the existing row. Caller
  gets `PENDING_RETRY`.
- **Retried?** Yes, once per tick, as one `UPDATE` that writes no row.
- **Retry stops** when it succeeds, or when the condition stops occurring.
- **Intervention state:** `appr_cond_unarmed_count()`, surfaced on the dashboard.
- **Never pretends:** the extra ROW is suppressed on the strength of a row that
  genuinely exists, never on a marker that does not. No outcome ever reports
  `SUPPRESSED` or `RECORDED` while unarmed — asserted across 30 ticks.
- **No infinite loop:** one row, one log line, per condition, ever.
- **Recovery:** the retry arms the existing row → `RECOVERED` → the count falls to
  zero → ordinary suppression resumes with no new mechanism and no manual repair.

### 6 · B-5 diagnostic behaviour
Bounded **structurally**: only states that occur once per condition speak — first
detection, the event that could not be written, and recovery. `PENDING_RETRY` is
silent, so 29 of 30 ticks write nothing. Each line is attributable to the
workspace (one database per tenant), the condition (its fingerprint), the status
and the real reason. No other tenant's information can appear, because no other
tenant's database is connected.

### 7 · B-2 business observability
`appr_sla_summary()` — already rendered by the Recruitment Command Centre — gains
`suppression_unarmed`. The existing approval strip shows a warning when it is
non-zero, **even when nothing is pending**, because the problem is with the
record-keeping rather than the backlog. No second dashboard; the existing one is
not redesigned. The message is business language and the rendered HTML is asserted
to contain no SQL state, column name, fingerprint, table name, password or path.

### 8 · Successful-storage evidence
C14.A — `RECORDED`, one event, nothing flagged, **ten identical refusals produce
one entry**.

### 9 · Marker-failure evidence
C14.B — the write really failed, a row really exists, the caller gets `UNARMED`,
never `RECORDED` or `SUPPRESSED`.

### 10 · Core-event-failure evidence
C14.C — no row exists, the row id is zero, the status is `NOT_RECORDED`, asserted
explicitly **not** to be `UNARMED`.

### 11 · Repeated-scheduler evidence
C14.D — 30 ticks, **one entry**; tick 1 `UNARMED`, ticks 2–30 `PENDING_RETRY`;
no tick claimed suppressed or recorded; 29 of 30 silent.

### 12 · Recovery evidence
C14.E — `RECOVERED` on the existing row, no new row, then `SUPPRESSED` and the
count stops moving.

### 13 · SQLite results
Focused **55 passed, 0 failed**. Full regression **10383 passed, 0 failed**.

### 14 · MariaDB results
Focused **55 passed, 0 failed**. Full regression **10384 passed, 0 failed**.
MariaDB is authoritative. **No engine difference in any #14 behaviour** — see
TEST-RESULTS for how the engine-specific fixtures are kept equivalent.

### 15 · Real tenant-switching evidence
C14.G — switching through `db(true)`; `__db_epoch` never assigned by hand; the
database names itself; **DB A ≠ DB B**; with A genuinely holding an unarmed
condition, B's count, B's approval summary, the marker status, the row id, the
notifier's outcome and the scheduler's count are all clean, and A is unchanged.

### 16 · Mutation results
**Attempted 10 · Caught 10 · Survived 0.** No inherited count is claimed.

### 17 · Exact mutation assertions

| mutation | detected by |
|---|---|
| `M14-1` discard the status at the caller | `C14.D · the first tick reports UNARMED and writes the row` — want `UNARMED`, got `RECORDED` |
| `M14-2` treat FAILED as stored | `C13.3 · the caller observed FAILED and did NOT take the stored path` — want `UNARMED`, got `RECORDED` |
| `M14-3` UNARMED when no row was written | `C14.C · the status says NOT_RECORDED, not UNARMED` — want `NOT_RECORDED`, got `UNARMED` |
| `M14-4` remove the bound | `C13.3 · NO second row was written — the loop is bounded at one` — want 1, got 2 |
| `M14-5` customer-visible state disappears | `C14.D2 · the unresolved conditions are counted for the screen` |
| `M14-6` recovery keeps reporting failure | `C14.E · the retry arms the row that already existed` — want `RECOVERED`, got `PENDING_RETRY` |
| `M14-7` the slot drops the row id | `C13.1 · the PRODUCTION CALLER observed STORED and returned RECORDED` — want `RECORDED`, got `NOT_RECORDED` |
| `M14-8` the count ignores the workspace | `C14.G · B's screen shows none of A's unresolved conditions` — want 0, got 3 |
| `M14-9` remove the message from the screen | `C14.D2 · the RENDERED screen states the problem in business words` |
| `M14-10` cron stops reporting | `C14.F · and the cron run itself reports it` |

`M14-9` **survived the first battery** and the reason is reported in full in
TEST-RESULTS: the assertion searched the view's source and matched the same words
in the comment above the markup. It now renders the view.

### 18 · Full regression
SQLite **10383/0**, MariaDB **10384/0**. No regression in approval, notifications,
SLA, scheduler, audit/activity, Recruitment, Operations, Quality, Money,
Marketplace or tenant isolation.

### 19 · Files changed
- `phpapp/lib/activity.php` — the status slot carries the row id; `act_last_cond_row()`.
- `phpapp/lib/recruit_approval.php` — the corrected taxonomy, the fingerprint, the
  gate, the retry, the bounded diagnostic, the unarmed count on the approval
  summary, and consumption in `appr_tick()` / `appr_email_requester()`.
- `phpapp/views/ops/recruitment_cc.php` — the warning on the existing approval strip.
- `phpapp/cron.php` — the run reports what it could not arm.
- `phpapp/tests/test_p3m3c14_chain.php` — 55 production-path assertions.
- `phpapp/tests/test_p3m3c13_consume.php` — two assertions re-pointed at the
  corrected contract (see *Known limitations*).
- `phpapp/deploy_check.php` — regenerated.
- `docs/phase3/M3-CORRECTION-14-{TEST-RESULTS,ADVERSARIAL-READY,COMPLETION-REPORT}.md`.

No permission, role, status or lifecycle transition was added or changed, so
`docs/01-roles.md`, `docs/02-permission-matrix.md` and
`docs/03-object-lifecycles.md` are unchanged and still agree with the code.

### 20 · Commit
Branch `claude/testing-branch-setup-0gqe8n`, commit titled *M3 correction #14 —
the complete condition-suppression decision chain*.

### 21 · Known limitations
1. **Two #13 assertions were re-pointed, not weakened.** They asserted the
   unbounded behaviour (`UNARMED` again, a second row) that §4 required removing.
   The rule they existed to protect is unchanged and still asserted first: the
   repeat is never suppressed on the strength of a marker that does not exist.
2. **`NOT_RECORDED` is not bounded across ticks.** When `activities` itself cannot
   be written there is no row to count, so the bound that fixes B-4 has nothing to
   stand on. `act_log()` already logged every core failure before #14; that is
   unchanged and out of scope.
3. **The retry is unbounded in attempts** — one `UPDATE` per tick, writing nothing.
   Bounded in rows and log lines, which is what ran away. Stopping it would
   prevent automatic recovery.
4. **The reminder e-mail itself is untouched.** #14 bounds the audit row; the send
   happens before it. Changing send behaviour was out of scope.
5. **`appr_cond_outcome()` is a proximity contract** — it reads state from the
   immediately preceding `act_log()`. Nothing violates it today; nothing enforces it.
6. **`suppression_unarmed` counts distinct fingerprints**, not distinct conditions.
7. `M14-9` survived the first battery; a source assertion matched its own comment.

### 22 · Remaining M3 open findings
Untouched by instruction: **H1**, **H3**, **S2**, **S3**, **U2**.
From the #12 adversarial audit, still open: **A-2** (a colliding id stamps the
wrong record), **A-3** (`STORED` survives a rollback), **A-4** (engines disagree on
an over-long key), **A-5** (byte-truncation mangles a non-ASCII key), **A-6** (the
loose-comparison mutation is unpinned), **A-7** (the column channel masks the write
channel — now more visible, since the diagnostic surface has real readers).

**M3 is NOT accepted. M4 is NOT started. Correction #14 is ready for adversarial
attack only.**
