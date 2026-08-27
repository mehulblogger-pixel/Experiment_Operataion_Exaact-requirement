# Exaact TPIA OS — Phase 3 program (the deferred builds)

**Purpose.** Phase 2 deliberately held back a set of items under its "no feature creep" rule — they are
genuine new builds, not hardening. Phase 3 builds them, each as its own change, under the **same rules**
that governed Phase 2.

## Rules carried forward (unchanged)

- **Non-destructive.** Never delete modules, routes, tables, fields, permissions, reports, workflows,
  records or APIs. Add; converge; retain.
- **Reuse before rebuild.** Read `docs/02-permission-matrix.md`, `docs/03-object-lifecycles.md` and the
  canonical model (`docs/phase-2/02-canonical-application-model.md`) first. Extend the canonical engine;
  never fork a second table/calculation/status.
- **No new permission without asking.** If a feature needs a permission not in the matrix, stop and ask.
  Prefer gating on existing checks (`current_user`, `is_coordinator_level`, `scope_allows`).
- **Advisory-first.** New gates surface a verdict; they hard-block only behind an opt-in setting
  (documented in the §47 governance registry).
- **Test-backed + docs in lockstep.** Every build ships with `tests/test_p3_*.php` and updates the docs
  in the same commit. The suite stays green (currently **3,584 passing, 0 failed**).
- **Sign-off for anything that moves displayed numbers.** (None of the Wave A–C items should; Wave D
  items are flagged individually.)

## The pathway — four waves, sequenced by dependency

Foundations first, because the management surfaces consume them.

### Wave A — Foundations  *(start here)*
| # | Item | What it is | Reuses / builds on | Sign-off |
|---|---|---|---|---|
| **§26** | **Canonical task** | A persisted, human-authored task (create / assign / due / done) — the thing the read-time aggregators (`ops_pending_tasks`, `attention_summary`) **cannot** hold. The aggregators keep emitting derived counts; §26 adds the individual, mutable items and feeds one count back into the aggregator. | `ops_pending_tasks` shape; `scope_allows` (§51); activity spine (§17) | none |
| §27 | Financial-event stream | A canonical, append-only money-event log (quote→invoice→receipt→credit) that dashboards can read instead of re-deriving. | `job_profit` (§28), revenue reconciliation (§29) as its first consumer/validator | none (read model) |

### Wave B — Surfaces (consume Wave A)
| # | Item | What it is | Reuses | Status |
|---|---|---|---|---|
| §19 | Action Centre | One personal "everything waiting on me" — approvals + my §26 tasks — in priority order. Delivered as the **"Next actions" band on My Work**, not a competing screen (non-destructive: My Work already IS the personal action page). | `ops_pending_tasks`, `task_mine` (§26) | ✅ **done** |
| §20 | Command Centre | One management "state of the business" board — attention band, money (§27), platform health — kept as separate bands (§20/§21). | `attention_summary`, `system_status`, §27 | ✅ **done** |

### Wave C — Platform
| # | Item | What it is | Reuses | Status |
|---|---|---|---|---|
| §50 | Generic integration queue | One reusable outbox (`integration_outbox`) with dedupe + bounded retry/backoff + injectable delivery, so a NEW integration writes a delivery callback, not a table + loop. The bespoke `books_outbox`/`ads_outbox` are untouched; the generic queue reports into the existing `integration_health()` (Module 46). | existing outboxes (retained); `integration_health` | ✅ **done** |

### Wave D — Larger UX / lifecycle builds (each its own change; schedule individually)
| # | Item | Note |
|---|---|---|
| §8  | Builder persona previews | Preview a report template as each role/persona sees it. |
| §34 | Dashboard expansion | Deeper role dashboards on top of §20. |
| §35 | Training attendance | Attendance capture + competence linkage. |
| §16 | Vendor-360 depth | Bring vendor-360 to client-360 parity. |
| §49 | Entity-360 | A uniform 360 shell across entities. |

**Not in Phase 3 without explicit sign-off:** anything that changes displayed financial figures (the §28
engine is already the one truth; Wave D must read it, never re-derive).

## Sequencing rationale

1. **§26 → §19:** the Action Centre is mostly a *view* of tasks; the task model must exist first.
2. **§27 → §20:** the Command Centre's financial band reads the event stream; the stream comes first.
3. **§50 and Wave D** are independent of A/B and can run in parallel or after, by appetite.

## Done log

- **2026-08-27 — §50 generic integration queue (Wave C).** `lib/webhookq.php` — one reusable outbox
  (`integration_outbox`) any new integration can enqueue onto: `webhookq_enqueue()` (with dedupe against
  an identical still-pending item), `webhookq_dispatch($limit, $deliver)` (one loop with bounded retries
  and exponential backoff — 2/4/8… min, cap 60; FAILED → GIVEN_UP at the attempt cap), `webhookq_counts()`.
  **Delivery is injectable and OFF by default** — with no deliverer wired, an item stays queued rather
  than sent to a fabricated endpoint (real outbound HTTP is a per-install concern with its own config +
  security review; this ships the durable queue, not an unreviewed sender). The bespoke `books_outbox` /
  `ads_outbox` are untouched; the generic queue reports into the existing `integration_health()`
  (Module 46, already on system-status + Command Centre). Wired into `boot()`. Test `test_p3_webhookq.php`
  (17 assertions — enqueue/dedupe, no-op default, success, retry→give-up, backoff parking, channel scope).
  Suite **3660 passed, 0 failed**. *Wave C complete. Remaining: Wave D — larger UX/lifecycle builds
  (§8/§34/§35/§16/§49), each its own change.*

- **2026-08-27 — §20 Command Centre (Wave B, item 2) — Wave B complete.** `command_centre()` +
  `/command-centre` (`lib/ops.php`, `views/ops/command_centre.php`) COMPOSE the three aggregators that
  already exist into one management board with three **separate** bands — Business "needs attention"
  (`attention_summary`), Money (the §27 `financial_rollup` — committed/billed/received/outstanding), and
  Platform health (`system_status`) — keeping business KPIs and technical health apart on purpose
  (§20/§21). Computes nothing new. Gated to management (`dash.operations`/`dash.financial`/admin — no new
  permission). Nav entries added for Command Centre (management) and My tasks (§26, everyone). Test
  `test_p3_command_centre.php` (10 assertions — composition, band separation, routing/nav). Suite
  **3643 passed, 0 failed**. *Next: Wave C — §50 generic integration layer (independent), then Wave D.*

- **2026-08-27 — §19 Action Centre (Wave B, item 1).** `action_centre($limit)` (`lib/ops.php`) merges the
  derived "waiting on me" buckets (`ops_pending_tasks`) with my individual §26 tasks (`task_mine`) into
  one priority-ordered "do this next" list: overdue task (0) → due-today (1) → blocking/warning approval
  (2) → due-soon (3) → dated-later (5) → undated (6). Reuses both aggregators; computes no new counts.
  Delivered **non-destructively as the "Next actions" band at the top of My Work** — not a competing new
  screen, since My Work already IS the personal action page (the count-card lanes stay below). The
  "all caught up" state now also accounts for open tasks. Test `test_p3_action_centre.php` (11 assertions —
  ordering, overdue flag, record-linked routing, limit). Suite **3633 passed, 0 failed**.
  *Next: §20 Command Centre (management board over attention_summary + system_status + §27).*

- **2026-08-27 — §26 canonical persisted task (Wave A, item 1).** `lib/tasks.php` adds the one thing the
  read-time aggregators can't hold: a human-authored, assignable, due-dated item you tick off — with a
  new `user_tasks` table (additive; wired into `boot()`'s migrate chain). Create / mark-done / reopen;
  assign to yourself always, to another user only at coordinator level and within branch scope
  (`scope_clause`); optional link to a record (job/report/NCR/…) with an activity-spine `TASK_ADDED`
  entry (§17). A `/tasks` screen (my open tasks + create form + recently-done) and a
  `task_render_for_entity()` panel for record pages. **No new permission** — gated on `current_user` and
  `is_coordinator_level`. The aggregators are untouched; §26 feeds **one** derived "my tasks" count back
  into `ops_pending_tasks`, so My Work stays unified. Test `test_p3_tasks.php` (19 assertions).
  Suite **3603 passed, 0 failed**.

- **2026-08-27 — §27 financial-event stream (Wave A, item 2).** `lib/finevent.php` — a **read-only
  projection** that turns the existing money records (accepted quotations, issued/cancelled invoices,
  receipts, credit notes) into one uniform, time-ordered stream (`financial_events()`) with a rollup
  (`financial_rollup()`: committed · billed · cancelled · received · credited · net-billed · outstanding).
  It reads the books ledger + quotations, so it **cannot drift** from them (unlike a parallel event
  table — the mistake §29 exists to catch); every projection is guarded and office-scoped (fail-closed).
  A `financial_events_render()` panel wired onto the **client-360** screen (a client's money story), and
  reusable by the coming §20 Command Centre. Filters: partner / contract / office / date / limit.
  Test `test_p3_finevent.php` (19 assertions — every kind projected, DRAFT excluded, rollup arithmetic,
  date + partner filters). Suite **3622 passed, 0 failed**. *Wave A complete. Next: Wave B — §19 Action
  Centre (reads §26 tasks), then §20 Command Centre (reads §27 stream + attention band).*
