# Phase 1 · M5 — Pre-Implementation Safety Check

**Date:** 2026-09-14 · **Baseline:** `9b285e2` (M4) · **Tests:** 7,398 passing
**No code changed.** Inspection only, per §39/§63 STEP 2.

---

## 1. Current entitlement chain (M3, unchanged)

```
can($perm)                                   lib/access.php:767
 ├─ licence_blocks($perm)   ← entitlement, FIRST
 │    └─ licence_owner(access) → product → licence_enabled() → module_state()
 └─ ua()['master'] || in_array($perm, perms)  ← RBAC, SECOND
```

The ordering is correct and must not be disturbed. M5's problem is not this
chain — it is **the number of runtime paths that never enter it.**

## 2. Current route chain

```
index.php   line  720 … 1684   ~40 route families handled INLINE
index.php   line 1685          ops_dispatch() → ops_module_gate()   ← the only gate
```

`ops_dispatch()` is **the last line of index.php**. Everything resolved above it
never reaches `ops_module_gate()`.

## 3. Module registry usage at runtime

`ops_module_gate()` holds 459 route→access-module entries and resolves
route → access module → `can('mod.<access>.view')` → `licence_owner()` →
`licence_enabled()`. M2 verified all 31 access modules are owned. The mapping is
sound; the question is which routes reach it.

---

## 4. BYPASS FINDINGS — evidence, not suspicion

### B-1 · Unmapped routes fail OPEN — **structural**

```php
$mod = $map[$base] ?? null;
…
if ($mod && !can("mod.$mod.view")) { refuse }
```

A route absent from the map yields `$mod = null`, so **no check runs at all**.
A new or forgotten paid-module route is reachable by anyone who knows the URL.
This is the §30 violation and the core of M5.

### B-2 · The public careers route — **paid module, no entitlement check**

`careers`, `careers/*` are handled at index.php:1057, before the dispatcher.
`careers_route()` gates on `careers_enabled()` — a **settings flag**, not
entitlement. `lib/careers.php` contains **zero** references to
`module_entitled`, `licence_enabled` or `module_state`.

A workspace not entitled to `hr` can still serve its public careers page and
**accept candidate applications into the HR module.**

### B-3 · Master short-circuits entitlement at 31 paid-module sites

Two patterns exist across the codebase:

| Pattern | Sites | Effect |
|---|---|---|
| `can(...) \|\| is_master()` | **100** | entitlement evaluated first — correct |
| `is_master() \|\| can(...)` | **102** | `is_master()` short-circuits; `can()` and therefore `licence_blocks()` **never run** |

Of the 102, by owning product module:

| Permission | Owner | Sites | Class |
|---|---|---|---|
| `mod.idems` | **reporting (PAID)** | 30 | **A — genuine entitlement bypass** |
| `mod.invoicing` | **money (PAID)** | 1 | **A — genuine entitlement bypass** |
| `mod.clients` | admin (CORE) | 11 | B — legitimate |
| `mod.vendors` | admin (CORE) | 10 | B — legitimate |
| `mod.portal` | admin (CORE) | 1 | B — legitimate |
| `mod.masters` | admin (CORE) | 1 | B — legitimate |

**31 category-A sites**, all in two modules. The remaining 23 resolve to core,
where the bypass changes nothing. This is a targeted list, not a mass rewrite.

### B-4 · `licence_owner()` returns `null` → no entitlement block — **latent**

An access module no product module claims is treated as *always available*
(the comment says so explicitly). M2 proved all 31 are owned, so it cannot fire
today. §17 requires it be made fail-closed regardless.

### B-5 · Dashboard / home — **no bypass found**

index.php:1200 routes the home page into the Recruitment command centre only
when `licence_enabled('hr')` is already true. `recruit_home_can()` does contain
`|| is_master()`, but it is reached only *after* that entitlement test, so master
cannot enter HR unentitled by this path. **Category B.**

### B-6 · Inline partner/client/vendor routes — **no paid-module bypass**

index.php:1242–1589 use `is_master() || can('mod.clients…')`. All resolve to
`admin`, which is core. **Category B.**

---

## 5. Marketplace / Connect routes (`connect`, `pro`, `vendor`, `portal`, `join`)

Also inline, also ungated. Marketplace is **not a product module** and its
commercial control is **M9**. Noted, deferred, not touched in M5.

## 6. Risk assessment

| Risk | Likelihood | Mitigation planned for M5 |
|---|---|---|
| Fail-closed on an unmapped route breaks a working core screen | **HIGH** | An explicit allowlist of core/public routes, derived by inspection, not by guessing; full Operations + Reporting regression per change |
| Gating `careers` breaks a live public page | LOW | The workspace must be entitled to `hr` — Acme is. Behaviour for an entitled tenant is unchanged |
| Changing 31 master sites breaks administration | MEDIUM | Reorder only — `is_master() \|\| can()` → `can() \|\| is_master()` preserves the master's permission power while restoring entitlement precedence. No permission removed |
| Redirect loop on denial | MEDIUM | Reuse the existing `ops_require()` denial, which already terminates cleanly. No new error UI |
| Performance | LOW | `licence_disabled()` is already statically cached per request; no new query per route |

## 7. Files proposed for modification

| File | Change |
|---|---|
| `lib/ops.php` | `ops_module_gate()` — fail closed on an unmapped route, against an explicit core/public allowlist. **Additive; no existing mapping removed.** Triggers full Operations regression (§52) |
| `lib/licence.php` | `licence_blocks()` / `licence_owner()` — an unowned access module denies rather than allows (§17) |
| `index.php` | gate the `careers` route family on `hr` |
| ~31 call sites in `lib/` | reorder `is_master() \|\| can()` → `can() \|\| is_master()` for `mod.idems` and `mod.invoicing` only |
| `tests/test_m5_runtime_entitlement.php` | **new** — §48 matrix, S-1, negative matrix |
| `docs/phase1/M5-*.md` | the five required documents |

## 8. Files explicitly protected — not to be modified

`lib/access.php` and `can()` (the ordering is already correct), `lib/tapi.php`,
Operations business logic, Quality, Dashboard rendering, Money, Reporting
engines, Marketplace/Connect engines, Recruitment engines, the approval,
pipeline, permission and audit engines, `lib/entitlement_migrate.php` (M4),
`lib/saas_tenants.php`.

## 9. Database

**No schema change is expected.** M5 is an enforcement change over existing
settings.

## 10. Deferred, documented, not fixed in M5

API (`api.php`), exports, reports, cron (`cron.php`, `cron_ads.php`), public
sign-up, Marketplace/Connect entitlement, and the remaining 71 category-B/C/D
master sites — **M6–M10**.

---

**M5 PRE-IMPLEMENTATION SAFETY CHECK COMPLETE. AWAITING APPROVAL TO IMPLEMENT.**
