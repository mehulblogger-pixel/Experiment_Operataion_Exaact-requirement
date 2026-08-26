# Module 14 — Settings · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: every settings surface funnels through `setting_set()`, and none of them left a trace

Settings are a flat key/value table (`settings(skey, svalue)`, `lib/access.php:625`) read/written by
`setting_get`/`setting_set` (`access.php:687/691`) — ~219 distinct keys, 616 call sites. There is a
tabbed primary screen (`/settings` → `ops_settings`, `lib/ops.php:7169`) plus sibling surfaces
(company profile, AI settings, module on/off via `licence_save`, `role_access`), **all gated on
`settings.manage` / `is_master`** — and **all funnelling through `setting_set()`**.

The platform has a sealed, hash-chained audit (`idems_log`, `lib/idems.php:3316`) that **already
supports a `'setting'` entity type** — but it was used exactly **once** (the vetting checklist,
`idems.php:6210`). So for an ISO-17020 platform, security-relevant configuration — password policy,
session timeouts, 2FA roles, audit-retention days, the job-auto-lock toggle, module on/off, SMTP,
company profile, even the permission map — could change with **no record of who changed it or when**.
That is the canonical "the mechanism exists but is never used" gap, and `setting_set()` is the single
choke-point where it can be closed for **every** surface at once.

**Noted, deferred (not built — separate concerns):**
- **Env-var overrides that silently beat the UI value** (`OPS_SMTP_*`, `LICENCE_*`, `ADSPRO_*`) — the
  screen shows one value, the server uses another. The `MODULES_OFF` case already shows a banner;
  extending that transparency banner to SMTP/licence is a good future add but touches several views.
- A **key registry / defaults catalog** (to detect orphaned settings and drift like
  `brand_color` vs `c_primary`) — a larger structural addition.
- **Per-office/SBU setting scope** — settings are company-wide singletons by design (tenancy is at
  the database level); a scope column is a real schema change, not additive polish.

---

## 1. Built (additive; one choke-point; no access change)

`setting_set()` now, after its unchanged write, records the change on the sealed chain:
`idems_log('setting', 0, 'SETTING_CHANGED', ['field'=>$key, 'old'=>…, 'new'=>…])`. Because every
settings surface calls `setting_set`, this covers the primary screen, company profile, AI settings,
module toggles and `role_access` **in one place**. Companion classifier `setting_change_class($key)`
decides:

- **Auditable?** User configuration → yes. Internal/bootstrap markers → **no**
  (`*_seeded*`, `*_repaired*`, `*_sig`, `*_checked_at`, `setup_done`, `schema_sig`, `partners_seeded`,
  `demo_*`, `billing_paid_until`). This keeps migration/seed churn off the chain and avoids firing
  during bootstrap.
- **Secret?** `pass` / `secret` / `token` / `api_key` / `ai_config` / `rzp_key` → the **event** is
  recorded (`(unset)`→`(updated)`/`(cleared)`) but the **value never touches the trail**.

Plus: no-op writes (same value) log nothing; oversized non-secret values (logo base64, `role_access`
JSON) are summarised as `(N chars changed)` so the chain stays lean; a reentrancy guard and a
try/catch mean logging can never block a save; and a 🛡️ note on `/settings` links admins to
`/audit-log?action=SETTING_CHANGED`.

---

## 2. Edge cases handled

1. A user setting change records old → new; the value reads back correctly (write unaffected).
2. Writing the same value again logs nothing (no audit noise).
3. Bootstrap/system markers (`schema_sig`, `*_seeded_v1`, `*_checked_at`) are never audited.
4. A secret change is recorded as an event; the secret value is never written to the trail.
5. A very large value is summarised, not copied wholesale into every entry.
6. Logging happens only when `idems_log` exists and never throws out of `setting_set` (guarded) —
   safe during early bootstrap before the audit table is migrated (idems_log already self-degrades).
7. No recursion: `idems_log` does not call `setting_set`; a reentrancy flag is a belt-and-braces.
8. Old value is captured from the cache **before** the overwrite, so the trail is accurate.

## 3. Guardrails (green)

The `settings` table, `setting_get`, the UPSERT in `setting_set`, `ops_settings`, `licence_save`,
company profile and AI settings screens, and their `settings.manage`/`is_master` gates — all
unchanged. No new permission; no schema change; nothing deleted. The screen's own inline validation
(clamps/regex/allow-lists) is untouched.

## 4. Tests

`tests/test_module14_settings.php` (24 assertions): the classifier splits auditable vs system vs
secret; a change writes SETTING_CHANGED with old→new; a no-op logs nothing; a system marker is never
audited; a secret records the event but never the value; a large value is summarised; the underlying
write still works; the wrap and screen link are wired. Suite 2996 passing (only the 3 pre-existing
baseline failures remain).
