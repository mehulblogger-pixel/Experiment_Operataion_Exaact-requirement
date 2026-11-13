# Flow — MASTER_ADMIN and ADMIN

These two are documented together because they share the same defaults (all
permissions, all modules, ALL/ALL scope — `access.php:437-438,328`); the difference is
the **master bypass**.

```mermaid
flowchart TD
  A[Login → / dashboard<br/>full exec board] --> B[Any area / any module]
  B --> C[MASTER only: Roles & permissions,<br/>Control panel, Super admin,<br/>module-licence toggle, seed/reset]
  B --> D[ADMIN: everything else]
```

## MASTER_ADMIN
- **Who:** the installation's root key (`is_superuser`). Meant to be one/few.
- **Can:** everything — `can()` returns true for all (`access.php:530`); admin-only tiles show (Roles & permissions `areas.php:209`, Control panel `:226`, Super admin); module-licence toggle and seed/reset are master-only (`ops.php:6812,2531+`).
- **Must never:** be routinely restricted or used for daily data entry — it has no guardrail. Keep it as the recovery key (see the recovery note in `99-gaps-and-risks.md`).

## Setting who approves what in Sales (owner, 13 Nov 2026)
- **Admin → Approvals → Sales** (`/sales-approvals`) — one tab for each approval in Sales:
  **Quotation** (the value levels, what happens above the top level, self-approval, one-after-another
  or together, escalation), **Project costing** (uses the quotation levels; whether a man-month
  quotation must come from an approved costing), **Contract** (how many approvals open a contract —
  two, one or none; which roles and/or one named person give each; whether one person may give
  both), **Deal stages** (the stage rules) and **Leads** (the rules in force, stated).
- Each tab says the rule in force in plain sentences, warns when a signature has nobody to give it,
  and is changed right there with "Save this rule". The same rules are the sections of
  **Sales → Set up Sales** — one copy, two ways in.
- The **Contract** rule is changed only by someone with `settings.manage` (or the Master Admin);
  others who can open the tab read it. Nothing here grants a permission: it chooses which existing
  people sign.
- **Company setup → Approvals** points a new company at the same place.

## ADMIN (legacy)
- **Who:** a full administrator that is **not** the super-user, so it is governed by permissions and **can be narrowed**. Also the fallback role for an unknown role string (`access.php:483`).
- **Can:** all areas and modules by default; but `is_master()`-only tiles (Roles & permissions, Control panel) are hidden unless also super-user.
- **Must never:** be assumed unbreakable — a per-user override or role change can strip it (this caused the recent lock-out). Always keep a real `MASTER_ADMIN` as the recovery key.
- **Recovery (if locked out with no master):** `reset-admin.txt` in the app folder, or `config.local.php` admin password change, or a phpMyAdmin `UPDATE users SET is_superuser=1 …` — see `99-gaps-and-risks.md`.
