# Module 36 — Licensing / SaaS Admin · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: the licence knows it's about to lapse; nobody is told until it's read-only

The signed-key state machine (`lk_state()`, `licencekey.php:190-239`) already computes the licence
**state** (OPEN/TRIAL/VALID/GRACE/READONLY/INVALID/MISSING), **days-left**, and **seat pressure**, and
`lk_summary()` (`:353`) packages it. But it is surfaced **only** on `/licence`, `/billing`,
`/super-admin` — screens an admin must deliberately open. There is **no ambient banner** on the home
dashboard or top bar, so an install in GRACE gets no warning until it hits read-only and the
POST-path flash fires (`index.php:809-815`). And a subscription (`billing_paid_until`) lapses with
**no cron reminder** at all — a self-service Razorpay install gets zero pre-lapse notice.

Two more concrete defects:
- **Gap G (a real mis-render):** `views/ops/super_admin.php` keyed its licence-health tone/KPI on
  `ACTIVE`/`EXPIRED`/`READ_ONLY` — names `lk_state()` **never emits** (it emits `VALID`/`READONLY`/…).
  So a healthy `VALID` licence never showed green and an expired `READONLY` one was **not** flagged red
  on the operator's at-a-glance panel.
- The Module-14 audit now logs `setting_set`, and the existing cron week-markers
  (`audit_reminder_week`, `mis_last_weekly`, …) are not system-classified, so they were writing weekly
  `SETTING_CHANGED` noise to the sealed chain.

**Noted, deferred (flagged, not built — state-changing or bigger):**
- **Over-seat via reactivation** — `lk_seat_block` guards only the INSERT branch; the UPDATE branch
  (`is_active=?`) can push active seats over the cap with no check. Adding a guard there is a
  *control* change (it could refuse a save) — needs an explicit decision, not a silent build.
- `billing_paid_until` **not on the audit chain** (explicitly skipped) — auditing subscription-date
  extensions is a good additive next, but its own change.
- **Per-tenant seat/user counts** not surfaced on the operator console; a **dunning/lockout** flow;
  the `SEAT_LIMIT` env vs key-limit **transparency** divergence on `/users`.
- **Tenant suspension** is the one genuine hard-strand path (403 before any DB opens) — flagged, not
  touched (no hard controls).

---

## 1. Built (additive, read-only/advisory; no hard control; no new permission)

1. **`licence_health()`** (`licencekey.php`) — one normalized verdict from `lk_summary()` +
   `licsync_status()`: `needs_attention`, `severity` (ok/warn/bad), `state`, `headline`, `detail`,
   `url`, `days_left`, `used`, `seats`, `over_seat`, `sync_error`. READONLY/INVALID/GRACE ⇒ bad;
   TRIAL ≤5d / VALID ≤30d / at-seat ⇒ warn; over-seat ⇒ bad; a failing auto-renew adds a note.
2. **An ambient licence banner** on the home dashboard, shown **only** to admins (`lk_can_manage`) and
   **only** when `needs_attention` — so a lapse or seat-limit is seen before it bites.
3. **`licence_run_reminders()`** + a weekly-guarded `cron.php` block (mirroring the Module 28/41
   reminders) — a pre-lapse email to `QAC_EMAIL`/coordinators. Advisory; changes no enforcement.
4. **Gap G fix** — `super_admin.php` now keys tone/KPI on the real state names, so VALID shows green
   and READONLY/INVALID show red.
5. **Audit-noise fix** — `setting_change_class()` now classes `*_week` / `*_last_weekly` /
   `*_last_monthly` cron markers as system (not audited), so weekly guards stay off the sealed chain.

---

## 2. Edge cases handled

1. An OPEN (unlicensed, all-on) install needs no attention and sends no reminder.
2. `needs_attention` is true exactly when severity ≠ ok.
3. Seat pressure escalates independently of expiry (at-limit ⇒ warn, over-limit ⇒ bad).
4. The banner is admin-gated and self-hiding; the reminder is at most weekly (cron guard) and only
   when there is a genuine problem or an approaching renewal (≤14d).
5. The state-tone fix covers every state `lk_state()` emits; unknown states fall back to neutral.
6. The audit skip-list extension is suffix-anchored, so a genuine setting (`pwd_min_len`) is still
   audited — verified by test.

## 3. Guardrails (green)

`lk_state`, `lk_summary`, `lk_seat_block`, the read-only POST gate, `can()` module scoping, billing,
the tenant model — all unchanged. No enforcement added or removed; no new permission; no schema
change; nothing deleted.

## 4. Tests

`tests/test_module36_licensing.php` (27 assertions): the health verdict's shape and the
`needs_attention ⇔ severity≠ok` invariant; an OPEN install is quiet and sends no reminder; the
super-admin panel now maps VALID→green / READONLY→red and the never-emitted names are gone; the
dashboard banner and weekly cron are wired; and the `*_week`/`*_last_weekly` markers are excluded from
the audit while a real setting is still audited. Suite 3096 passing (only the 3 pre-existing baseline
failures remain).
