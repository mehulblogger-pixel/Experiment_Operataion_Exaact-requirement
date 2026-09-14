# Milestone 9 — Completion Report

## Verdict: **PASS WITH DOCUMENTED LIMITATIONS**

Every acceptance criterion met, fully green suite, no schema change, no RBAC
surface added. The qualifier records eight limitations — **L1 requires action
before deployment** and is repeated in §9 below.

---

## 1. Implementation

### What was found

Marketplace / Connect is a large subsystem — **40 libraries**, **21 staff
routes**, its own public front door, a freelancer portal, a public organisation
join page, a public professional passport, and tiles inside both the client and
vendor portals. In front of all of it sat one setting, `connect_enabled`, which
**defaulted to ON** and which the company could flip for itself. There was no
commercial control of any kind.

### The commercial identity

Registered under the key the product already used — `PRODUCT_PACKAGES` carries an
optional `'connect'` key and the setting is `connect_enabled`:

```php
'connect' => ['Marketplace & Connect', '…', [], false],
```

**It claims no access modules, deliberately.** The marketplace has never had
them; minting `mod.marketplace.*` permissions that nothing reads and no role
grants is how you lock customers out of a module you meant to sell. Entitlement
resolves through the product key — the same route `admin` already takes.

### The enforcement point

One function: `connect_enabled()`. Measured, not assumed — **15 of the 16**
`connect_*_can()` gates consult it, the 16th delegates to one that does, the
freelancer portal switch *is* a call to it, the public front door and join page
consult it, the client portal's hiring tiles consult it. **34 call sites.**

Adding the entitlement question there means every surface inherits it, rather
than repeating the check thirty-four times and missing one.

### Master users — no rewrite needed

`connect_market_can()` already asked `connect_enabled()` **before** `is_master()`.
The ordering was right; it just had nothing commercial to enforce. It does now.
**No `is_master()` usage was rewritten anywhere in M9.**

### Files changed — four source files

| File | Change |
|---|---|
| `lib/licence.php` | `connect` registered as the seventh product module |
| `lib/connect_market.php` | `connect_enabled()` asks entitlement first |
| `lib/portal.php` | `market.post`, `market.vouchers` → `connect` |
| `lib/cvp.php` | `market.apply` → `connect` |
| `tests/test_m9_marketplace_entitlement.php` | new, 91 assertions |
| `tests/test_module_registry.php`, `tests/test_m6_api_action_entitlement.php` | expectations updated and strengthened (§6) |
| `deploy-check.php` | regenerated |

### Helpers reused

`licence_module_live()`, `licence_blocks()`, `licence_owner()`, `module_state()`,
`licence_enabled()`. **None created.** No `marketplace_is_licensed()`.

---

## 2. Security

| Property | Evidence |
|---|---|
| **Fail-closed** | NOT_ENTITLED, LICENCE_BLOCKED, TENANT_DISABLED, UNKNOWN (blank), rubbish record — all deny, each asserted |
| **Master** | ALLOW entitled; DENY unentitled, tenant-disabled and blank. No `is_master()` rewrite |
| **Direct URL** | All 16 gates asserted closed when unentitled and open when entitled — the gates run on every method, so the page cannot be reached by typing its address |
| **POST / AJAX** | Same gates, same chokepoint; the handlers require them before any action |
| **Data via another route** | Client- and vendor-portal marketplace keys now owned by `connect`; Operations tiles in the same portal unaffected |
| **Public surfaces** | Front door, freelancer portal, join page and passport all inherit the switch. Nothing was made public that was not already |
| **Tenant isolation** | Both directions across a live connection switch, no cache reload called |
| **No trusted input** | Forged `module`, `connect_enabled`, `tenant`, `saas_entitled_modules` buy nothing |
| **Lifecycle** | ON → OFF → ON; access disappears immediately, **historical rows counted and intact**, access restored |

---

## 3. Testing

| | Result |
|---|---|
| **Focused M9** | **91 passed, 0 failed** |
| **Full regression** | **7,907 passed · 0 failed · 0 skipped · 457 files · 98 s · PHP 8.4.19** (M8 baseline 7,800) |
| **S-1** | Operations **WORKS**, Reporting **WORKS**, HR/Sales/Money **DENIED**, Marketplace **DENIED** — and once subscribed, Marketplace **ALLOWED** with every other module's state unchanged |
| **Cross-tenant** | Pass, both directions |
| **Mutation** | Entitlement check removed → **20 failures**; module unregistered → **24 failures** |
| **MySQL/MariaDB** | **NOT EXECUTED** — no server installed (verified). No claim of validation |

---

## 4. Marketplace OFF ≠ Operations OFF

Asserted directly: with the marketplace off, Operations runs, its calls run,
Reporting runs, both route gates still allow, and core administration is
untouched.

---

## 5. Architecture preserved

No Operations table, business rule, deployment or workforce logic moved. **Quality
remains inside Operations** — no separate Quality module was created. Identity
(`cx_professionals`, `cx_identity_link`), organisations (`business_partners`,
`cx_organisations`) and the Recruitment requirement split (`requisitions` vs
`cx_requirements`) are all untouched. M9 is entitlement work, not convergence
work.

---

## 6. Existing tests changed — two, both strengthened

`test_module_registry.php` — the module count moved 6 → 7 (still an **exact**
count, so an undecided module still fails), and the "Marketplace is not
registered" assertion was **inverted**. That assertion carried its own comment
saying *"Marketplace is Milestone 9"* — it was written as a placeholder until
now. It was replaced by four stricter assertions: registered, **no rival
`marketplace` key**, sellable not core, claims no access modules, and owns none
of another product's. **The Quality assertions are untouched.**

`test_m6_api_action_entitlement.php` — one assertion said the portal marketplace
keys were "deliberately not bound to a product module", true only while no such
module existed. Now asserts they are bound to `connect`, plus one more key.

Nothing else was modified, skipped or deleted.

---

## 7. Background, public and export review

No marketplace step exists in `cron.php` (all 35 were classified in M8) and
`cron_ads.php` is Sales, not marketplace. Marketplace matching, notifications and
settlement run in-request, behind the same gates. The TAPI analytics export
carries no marketplace metric, so no marketplace data leaves through the core
export. The dormant webhook queue is unchanged (M8 L3).

---

## 8. Acceptance criteria

| Criterion | Result |
|---|---|
| Marketplace has a commercial product-module identity | **PASS** |
| Access modules correctly owned | **PASS** — claims none; owns nothing of another product's |
| Existing entitlement engine reused | **PASS** — no new helper |
| Direct URLs protected | **PASS** |
| POST/AJAX actions protected | **PASS** |
| Master cannot bypass | **PASS** |
| Data not obtainable via an alternate route | **PASS** |
| Exports/reports protected | **PASS** |
| Background execution inventoried | **PASS** — none is marketplace-owned |
| Public surfaces reviewed | **PASS** |
| Cross-tenant isolation | **PASS** |
| Marketplace OFF does not damage Operations | **PASS** |
| Marketplace OFF does not damage Reporting | **PASS** |
| Quality/Operations architecture unchanged | **PASS** |
| Identity architecture unchanged | **PASS** |
| Recruitment requirement architecture unchanged | **PASS** |
| No duplicate licence/entitlement engine | **PASS** |
| Focused tests pass | **PASS** — 91/0 |
| Mutation checks pass | **PASS** — 20 and 24 failures on removal |
| Full regression passes | **PASS** — 7,907/0 |
| S-1 passes | **PASS** |
| MySQL/MariaDB status reported | **PASS** — not executed |
| Documentation complete | **PASS** — five documents |
| Known limitations documented | **PASS** — eight |
| M10 NOT started | **PASS** |
| Phase 2 NOT started | **PASS** |

---

## 9. Action required before deployment

**L1.** Marketplace is now a paid module, and `connect_enabled` used to default to
ON. Any hosted workspace whose entitlement does not name `connect` — including
workspaces currently using the marketplace — will lose it.

No entitlement was manufactured for them on purpose: inferring a purchase from a
setting that defaults to ON would be inventing evidence, which M4 forbids.

**Before deploying, add `connect` to the entitlement of every workspace that has
bought the marketplace**, in the Companies console. It already appears there as a
sellable tick-box. The control install and self-hosted installs are unaffected.

**L2.** No plan tier names `connect` yet, so it is sold per workspace until a tier
includes it — a pricing decision to make deliberately.

---

**STOP. M9 ends here.** M10–M16 and Phase 2 have not been started.
