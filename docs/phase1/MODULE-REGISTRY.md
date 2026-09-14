# Phase 1 · Milestone 2 — Authoritative Module Registry

**Date:** 2026-09-14 · **Milestone:** 2 of 16
**Application code changed: NONE.** Documentation and tests only.

---

## 1. Product module registry

One registry: `PRODUCT_MODULES`, `lib/licence.php:29`. Six commercial modules.
This is the unit a customer buys.

## 2–4. Keys, labels, core status

| Key | Label | Core | Sellable |
|---|---|---|---|
| `operations` | Operations | no | yes |
| `admin` | Administration | **YES** | never sold separately |
| `sales` | Sales & CRM | no | yes |
| `reporting` | Inspection reporting | no | yes |
| `money` | Money | no | yes |
| **`hr`** | **People & hiring** | no | yes |

`admin` is core because every install needs masters, users and settings; a
workspace that lost it would not be a smaller product, it would be a broken one.
Core status is unchanged in this milestone.

## 5. Access-module mappings

31 fine-grained access modules, catalogued in `ACCESS_MODULES`
(`lib/access.php:302`) and each owned by exactly one product module through the
`covers` list in `PRODUCT_MODULES`.

| Product module | Access modules it owns | n |
|---|---|---|
| `operations` | calls, jobs, reconcile, vouchers, equipment, competence, impartiality, identity, complaints, ncr, capa, audits, datacontrol, confidentiality, overheads | 15 |
| `admin` | masters, users, settings, clients, vendors, reports, portal | 7 |
| `sales` | leads, inquiries, quotes, crm_orders, crm_reports | 5 |
| `reporting` | idems | 1 |
| `money` | invoicing, profitability | 2 |
| `hr` | **hiring** | 1 |

**Verified mechanically, not by reading:**

| Check | Result |
|---|---|
| access modules in the catalogue | 31 |
| access modules claimed by a product module | 31 |
| catalogued but owned by nobody | **0** |
| claimed but not in the catalogue | **0** |
| claimed by more than one product module | **0** |
| `licence_owner()` disagreeing with the registry | **0** |

**The registry is structurally sound. No code change was required.**

## 6. The `licence_owner()` relationship

`licence_owner($accessModule)` (`lib/licence.php:55`) builds a static map from
the `covers` lists and answers which product module owns a given access module.
It is the single bridge between the two namespaces, and everything downstream
uses it:

```
route  →  ops_module_gate()  →  access module  →  can('mod.<access>.view')
                                                        ↓
                                              licence_blocks()
                                                        ↓
                                              licence_owner()  →  product module
                                                        ↓
                                              licence_enabled()
```

Menus take the same path in read-only form via `ops_module_gate($route, true)`,
which calls `licence_owner()` then `licence_enabled()` directly — deliberately
the same authoritative map, so a menu can never offer what the gate will refuse.

## 7. Recruitment = `hr`

`hr` is the one commercial identifier for Recruitment. Its access module is
`hiring`, carrying **27 route entries** in `ops_module_gate` and **22
permission sites**. No `recruitment`, `talent` or `recruitment_module`
identifier exists, and the tests refuse to let one appear.

## 8. Quality = inside Operations

Quality is not a product module and is not registered as one. The access modules
that carry it — `audits`, `ncr`, `capa`, `datacontrol`, `confidentiality`,
`impartiality`, `competence` — are all owned by `operations`. Not split, not
duplicated, not redesigned.

## 9. Marketplace = deferred to Milestone 9

Not in `PRODUCT_MODULES`, so no ceiling governs it today. Cloud installs default
it ON, and all three live workspaces show it never configured and present in no
purchase record: **on by legacy default, purchased by nobody.** Adding it to the
registry is Milestone 9 work and is not done here. The default has not been, and
will not be, treated as a purchase.

## 10. Anomalies discovered — documented, none fixed

None of these are registry defects. All are recorded for the milestone that owns
them.

| # | Finding | Effect today | Owner |
|---|---|---|---|
| **A1** | `ops_module_gate` maps 3 routes — `notifications`, `integrations`, `system-status` — to `'admin'`, which is a **product** key, not an access module. `licence_owner('admin')` is therefore `null`, so `can('mod.admin.view')` is never licence-blocked. | **None.** `admin` is core and always enabled, so the correct answer and the accidental answer coincide. | M6 |
| **A2** | `vouchers` (8 permission sites) and `vendors` (19) are owned access modules with **no `ops_module_gate` entries** — protected by permission, not by route. | Route-level gap; a bookmarked URL is not module-gated. | M6 |
| **A3** | `crm_orders` is catalogued and owned by `sales` but referenced by no route and no permission. | None — a reserved name. Not removed; removal would be a behaviour change outside M2. | note only |
| **A4** | `ops_module_gate` holds two auxiliary maps (`$moduleRoute` for enforcement, `$peekExtra` for menus) keyed by **product** module, calling `licence_enabled()` directly — `service-scope`→operations, `service-formats`→reporting, `industry`/`industry-apply`→sales. | Correct and commented; a second small mapping surface to keep in view. | M6 |
| **A5** | `licence_owner()` returns `null` for anything unclaimed, and the code comment states unclaimed means **always available**. | This is the fail-open posture at the permission layer. | M3/M5 |
| **A6** | A scan for `mod.<x>.<verb>` reported an access module named `x`. | **False positive** — it is an illustrative comment at `lib/licence.php:157`. No defect. | closed |

## 11. Changes made

**No application code was changed.** Added:

* `tests/test_module_registry.php` — 63 assertions
* `docs/phase1/MODULE-REGISTRY.md` — this document

## 12. Intentionally deferred

Fail-closed behaviour (M3/M5), entitlement precedence (M3), tenant
migration (M4), route/API/export/cron enforcement (M6–M8), Marketplace
entitlement (M9), the `is_master()` audit (M10), administrator and error UX
(M11–M12). Blank-ceiling behaviour, `module_entitled()`, `licence_disabled()`,
route gating, API, cron, Marketplace, `is_master()` and tenant migration all
remain exactly as they were.

## 13. Evidence

| | |
|---|---|
| Milestone 2 targeted tests | **63 passed, 0 failed** |
| Full regression | **7,239 passed, 0 failed** (baseline 7,176; +63, zero regressions) |
| Environment | PHP 8.4.19, SQLite harness |
| MySQL/MariaDB | **not exercised** — no MySQL server in this environment. No "MySQL tested" claim is made. |
