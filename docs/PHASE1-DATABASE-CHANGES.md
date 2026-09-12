# PHASE 1 — Database Changes

Phase 1 adds **one** orchestration table and reuses every existing store. No existing table was altered, renamed, or dropped.

## New table

### `company_capabilities`
The tenant's multi-select "what does my company do?" (§11 many-to-many — one row per chosen capability).

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `capability_code` | VARCHAR(48) | a code from `connect_cap_catalog()` (catalogue reused, not duplicated) |
| `enabled` | INT | 1 = chosen |
| `chosen_by` | VARCHAR(120) | audit convenience |
| `chosen_at` | VARCHAR(30) | ISO timestamp |

- Unique index `ux_company_cap (capability_code)`.
- Created idempotently by `cockpit_migrate()` in the `db.php` boot chain (epoch-guarded), on both SQLite and MySQL via `pk_clause()`.
- **Tenant-scoped by construction:** it lives inside each company's own isolated database, so Tenant A can never read Tenant B's rows (§46/§62). No global/control-DB copy exists.

## New settings keys (existing `settings` table, no schema change)

| Key | Purpose |
|---|---|
| `cockpit_profile_done` | marks the profile step visited, for "resume" (§38) |
| `company_name` | optional display name captured on the profile page (reuses the existing settings store) |

Both are written through `setting_set()`, which **auto-audits** via `idems_log()` (§48) — no new audit store.

## Reused stores (unchanged)

Modules → `settings.modules_off` · Terminology → `settings` (`term_overrides`) · Masters → `lookup_types`/`lookup_values` · Custom fields → `custom_fields` · Forms → `form_field_layout`/`custom_forms` · Roles → `settings.role_access` + `users` · Audit → `idems_log` chain.

## Migrations / derived state (§40)

No data migration is required. Existing configuration is **read live**, so an existing tenant's modules, forms, masters, terminology and roles are detected as-is — nothing is reset (§39). `company_capabilities` simply starts empty until the admin chooses on the profile page; an empty selection changes no runtime behaviour.
