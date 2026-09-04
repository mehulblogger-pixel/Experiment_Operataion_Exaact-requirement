# Slice P6 — Product Packages

**Change-control record (directive Part 25). Classification: CONFIGURE (presets
over existing switches; additive, non-destructive). Status: DELIVERED.**

Directive Part 19 (one platform, configurable business experiences).
`03-target-architecture.md` §8 P6.

---

## 0. Revisit-trigger check (RT1)

Not applicable — P4 (the billing bridge) is delivered; P6 is packaging.

## 1. Existing state

Three independent switches already exist (see `01-current-state-inventory.md` §5):
industry packs (`lib/packs.php`), product-module licensing (`lib/licence.php`,
6 bundles: `operations, admin[core], sales, reporting, money, hr`), and per-role
module gates. There was no *one-click* "which EXAACT is this" chooser over them.

## 2. Problem

The directive's four packages (TPIA / Technical Staffing / Recruitment /
Enterprise) had to be assembled by hand across packs + licence + roles.

## 3. Solution (delivered — presets, no new mechanism)

`lib/licence.php`:
- **`PRODUCT_PACKAGES`** — four presets, each a `{packs, off[]}` over the existing
  switches:
  - **TPIA** — pack `inspection`; hides `sales`, `hr`.
  - **Technical Staffing** — no pack; hides `sales` (keeps operations, hiring,
    money, and the report engine for deputation reports).
  - **Recruitment** — no pack; hides `operations`, `reporting`.
  - **Enterprise** — pack `inspection`; hides nothing.
- **`product_package_apply($key)`** sets `packs_enabled` (via `packs_save`) +
  `modules_off`, and remembers the choice in a `product_package` setting.
- **`product_package_current()`** reads back the active preset by comparing the
  live switches, so a hand-tuned change on Licence correctly shows "custom".
- **`ops_product_package()`** — the master-only chooser screen `/product-package`
  (deliberately not module-gated, like Licence), with an Admin-area tile.

`admin` is core and can never be switched off. Every choice is reversible, and an
admin can still fine-tune any single module on the existing Licence screen.

## 4–8. Impact

- **DB / permissions:** none. No table, no status, **no new permission** (the
  chooser is `is_master`-gated; `docs/02-permission-matrix.md` unchanged). It
  writes only the existing `packs_enabled` / `modules_off` settings plus a new
  `product_package` marker setting.
- **Routes:** `/product-package`, `/product-package-apply` → `ops_product_package`.
- **Caveat (documented on-screen):** on an install whose modules are pinned by a
  **signed licence key**, the signed licence wins over this chooser (the chooser
  writes the settings path, which the key overrides). Applying a package changes
  settings only; no data is affected.

## 9. Regression & validation

- `php -l` clean on all changed files.
- New `tests/test_product_package.php` — **44 assertions** (each preset writes the
  right pack + off-bundles + marker; bundles flip when the settings path is live;
  Enterprise turns everything on; hand-tuned reads as custom; hermetic
  capture/restore of settings so later tests are unaffected).
- **Full suite: 3861 passed, 0 failed.**

### A latent bug this slice surfaced (and fixed)

Adding a test that legitimately refreshes the licence cache exposed a
pre-existing **test-isolation leak**: `tests/test_module46_integrations.php` set
a bogus `licence_key` inside a transaction it expected to roll back, but a commit
inside the block left the key **persisted** — putting the whole app into an
INVALID-licence state for every subsequent test, silently masked because nothing
refreshed the `licence_disabled` cache. Fixed module46's teardown to **restore
the `licence_key` setting explicitly and re-prime the `lk_state` /
`licence_disabled` caches**. This is proper teardown (stronger isolation), not a
weakened assertion; module46's own assertions are unchanged (16/16), and the
whole suite is now order-independent for licence state.

## 10. Rollback

Apply **Enterprise** (everything on), or revert `packs_enabled` / `modules_off` on
the Licence screen. No data touched.
