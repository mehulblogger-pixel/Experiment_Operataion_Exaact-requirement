# Module 38 — Notification Centre · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: every email is logged, and the log is shown nowhere

There is one mail primitive — `ops_mail($to,$subject,$body,$cc,$kind,$attachments)` (`ops.php:1766`) —
and **every call is written to `email_log`** (`to_addr, cc_addr, subject, body, kind, sent_ok, error,
created_at`, `ops.php:187`), success or failure. ~48 senders across the app use it (assignment,
reminders, invoice, quote, receivables, contract/cert/audit/licence reminders, report-issued…). But
`email_log` is **rendered nowhere** — no route, no view. So "did the client actually get the
report-issued email?" and "did last night's cron reminders go out, or did SMTP fail?" are answerable
only by raw SQL, and a nightly SMTP outage loses that day's reminders **invisibly**.

**Noted, deferred (flagged, not built — each is a behaviour/state change to decide):**
- **CC-only digests never send (a real bug):** `capa_run_reminders`/`cmp_run_reminders` call
  `ops_mail('', …, managers-in-CC, …)`, and `ops_mail` gates the whole send on `if ($to)` — so those
  digests are logged `sent_ok=0, error='no recipient'` and never delivered. Fixing it changes what
  `ops_mail` dispatches (a send-behaviour change). This outbox now makes the failure **visible**;
  the fix is a separate decision.
- **Throttle armed on failure:** several cron reminders set their "already reminded" flag
  (`jobs.last_reminder`, `expiry_notified`, …) regardless of `sent_ok`, so a failed send is recorded
  as done and never retried. Honouring `sent_ok` before arming is a correctness change to the senders.
- **No retry / dead-letter / bounce handling; no per-user notification preferences; no staff in-app
  bell** (the read-tracked feed exists only for portal users via `portal_notifications`). Larger builds.

---

## 1. Built (additive, read-only; no sender touched; no schema change)

A **Notification log / outbox** over the existing `email_log`:
- `ops_notifications($method)` (`ops.php`) — route `/notifications`, mapped to the core `admin`
  module, gated by `notifications_can_view()` (`is_master` / `idems.audit.view` / `settings.manage` /
  admin-level). Lists the most recent 400 sends with recipient, category (`kind`), subject and outcome
  (sent / failed + error), filterable by category, by **failed-only**, and by a recipient/subject
  search. 30-day KPIs: sent, failed (with how many were "no recipient"), total.
- `email_failed_count($days=7)` — a helper for a failure count (future banner/alert).
- `views/ops/notifications.php` — the read-only screen; a warning strip when there are recent
  failures, linking to the failed-only view.
- A link from the Settings screen ("Notification log — what email went out"), beside the audit-trail
  link, so admins can find it.

---

## 2. Edge cases handled

1. A successful send shows "sent"; a failed one shows "failed" with its error — so the CC-only
   silent-drop and any SMTP outage are now **visible**, not lost.
2. The failed-only filter and category filter narrow correctly; a recipient/subject search matches
   `to_addr`/`cc_addr`/`subject`.
3. A CC-only row (empty `to_addr`) still shows its CC recipient, not a blank.
4. Every query is guarded (missing table ⇒ empty list/zero, never fatal).
5. Read-only — the handler only SELECTs; the view holds no SQL; `ops_mail` and its logging are
   untouched. A "sent" row means handed to the mail server, not a delivery receipt — stated on-screen.
6. Admin-gated — a non-admin cannot open it.

## 3. Guardrails (green)

`ops_mail`, `email_log` and its schema, every sender and every cron reminder — all unchanged. No new
permission (reuses existing admin gates); no schema change; nothing deleted.

## 4. Tests

`tests/test_module38_notifications.php` (20 assertions): the route is dispatched and module-mapped;
`email_failed_count` counts failures; the handler surfaces sent + failed rows and the 30-day
sent/failed/no-recipient KPIs; the failed-only, category and search filters each narrow correctly; the
settings link is wired; and `ops_mail` is unchanged (read-only module). Suite 3116 passing (only the 3
pre-existing baseline failures remain).
