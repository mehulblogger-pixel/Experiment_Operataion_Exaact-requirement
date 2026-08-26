# Module 46 — Integrations · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: every integration tracks its own health, and there is no place to see it together

There is no single "Integrations" module — each integration is its own `lib/*.php` (Ads Pro, Razorpay,
licence auto-renew, MGH Books, AI, cPanel, SSO, SMTP) with a consistent pattern (settings config, env
override, outbox + payload-hash for two-way sync, `_try()` swallow wrappers). Each **already tracks
its own status** — Ads Pro `ads_counts()['last']` + `ads_outbox_counts()['GIVEN_UP']`, Books
`books_outbox_counts()['stuck']`, licence `licsync_status()['error']`, SMTP `email_failed_count()`
(Module 38) — but those signals live **only on that integration's own screen**. The "Connections" nav
badges only Books `stuck`. So **a sync that fails quietly is invisible** until someone asks why data
is stale — the sync analogue of the Module-38 email gap.

Second, a secret-classification gap (Module 14 tie-in): **`licence_key`** (the signed entitlement
artifact) did **not** match the secret regex (it needs `api_key`, not bare `key`), so on change it was
audited on the **non-secret** path — its value written to the trail (only capped when >200 chars).

**Noted, deferred (flagged, not built — each is a behaviour add or new capability):**
- **Books has no `sync_log` and its status-pull swallows every error** (`books_fetch_status` returns
  null silently) — a failing Books status-sync is invisible even to this surface; giving Books an
  `ads_sync_log`-style log is a sender-side change.
- **No connection test** for Books / Razorpay / SMTP (Ads Pro & cPanel have one) — adding a live test
  button is a new outbound call per integration.
- **`OPS_SMTP_*` silently beats the UI SMTP config** with no banner (Ads Pro's env override is shown;
  SMTP's is not) — a transparency fix that parallels the deferred Module-36 SEAT_LIMIT one.
- **No async Razorpay payment-captured webhook** — a payment whose browser never returns is never
  reconciled.

---

## 1. Built (additive, read-only, passive; no sync/sender touched)

1. **`integration_health()`** (`lib/ops.php`) — one passive read (no live API call) that aggregates
   each integration's already-stored signal into a row: label, severity (ok/warn/bad), last-sync,
   detail, link. Ads Pro (gave-up outbox ⇒ bad), Books (stuck ⇒ bad / queued ⇒ warn), licence
   auto-renew (check-in error ⇒ bad), Email/SMTP (recent failed sends ⇒ warn), plus presence-only rows
   for Razorpay and AI. Each guarded so a missing helper/table is skipped, never fatal.
2. **`integration_health_attention()`** — count of non-healthy integrations.
3. **`/integrations`** route (core `admin` module) → `ops_integrations()`, gated by
   `notifications_can_view()` (master / idems.audit.view / settings.manage / admin), rendering a
   read-only health table; a warning strip when any integration needs attention.
4. **A home attention-band tile** (Module 34) — admin-gated — when an integration is failing/stuck.
5. **A link from the Settings screen**, beside the audit-trail and notification-log links.
6. **Gap-4 fix** — `licence_key` / `licence_install` now match the Module-14 secret regex, so a
   licence-key change records the event, never the value.

---

## 2. Edge cases handled

1. Only *connected* integrations appear — a bare install shows an empty, healthy surface (no false
   alarm), and the attention count is zero.
2. `attention` equals exactly the count of non-ok rows.
3. The read is **passive** — opening the page calls no external service (it does not invoke the live
   `ads_health()` API); each integration's own screen is where a live test lives.
4. Every integration's read is wrapped in try/catch and `function_exists`, so a pre-migration table or
   an absent integration is skipped, never fatal.
5. The licence-key secret fix records the change on the chain but never the key value (asserted).

## 3. Guardrails (green)

Every integration's config, sync, outbox, retry and `_try()` wrappers — all unchanged. No external
call is added to any page load. No new permission (reuses the admin observability gate); no schema
change; nothing deleted.

## 4. Tests

`tests/test_module46_integrations.php` (21 assertions): the route is dispatched and module-mapped;
`licence_key`/`licence_install` are now secret and a licence-key change never writes its value;
`integration_health()` returns well-formed rows; `attention` counts exactly the non-healthy ones; the
handler hands rows to the view; the home tile and settings link are wired; the read is passive. Suite
3149 passing (only the 3 pre-existing baseline failures remain).
