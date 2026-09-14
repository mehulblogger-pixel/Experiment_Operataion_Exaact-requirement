# Milestone 9 — Marketplace / Connect Entitlement

**Status:** complete · **Suite:** 7,907 passed, 0 failed · **Baseline:** eaf4748 (M8)

---

## 1. What was actually there

Marketplace / Connect is not a small feature. The inventory found:

- **40 libraries** (`lib/connect_*.php`) — requirements, sourcing, matching,
  bench, deployment, engagement, vouchers, credentials, verification, ratings,
  trust, disputes, messaging, channels, taxonomy, geo, privacy, analytics, KPIs;
- **21 staff routes**, **none** of which appear in the M5 route-gate map;
- its **own public front door** (`/connect`), a **freelancer portal** (`/pro`), a
  public **organisation join** page, and a public **professional passport**
  (`/p/<token>`);
- marketplace tiles inside the **client portal** and the **vendor portal**.

And in front of all of it: `connect_enabled()` — a setting that **defaulted to
ON** and which the company could flip for itself. There was no commercial
control of any kind.

---

## 2. The commercial identity — `connect`

The product already half-had one, which is why no new concept was invented:

- `PRODUCT_PACKAGES` carries an optional **`'connect'`** key, and the
  `RECRUITMENT_HR` preset already uses it to hide the marketplace;
- the setting is called **`connect_enabled`**;
- `product_package_apply()` already writes it.

So M9 registers the product module under the key the product already uses:

```php
'connect' => ['Marketplace & Connect',
              'The shared professional marketplace: requirements, sourcing,
               matching, bench, engagement, ratings and settlement',
              [], false],
```

**It claims no access modules, deliberately.** The marketplace has never had
fine-grained access modules — its screens gate on `connect_market_can()` and the
coordinator level, not on `mod.*` permissions. Minting `mod.marketplace.view` and
`mod.marketplace.edit` here would add RBAC surface that nothing reads and that no
role grants, which is precisely how you lock every customer out of a module you
meant to sell them.

Entitlement resolves through the **product key directly** — the same route
`admin` already takes (registry anomaly A1, handled in M5's `licence_blocks()`).
No new mechanism, no second engine.

The registry test now pins this: exactly **one** marketplace identity, no rival
`marketplace` key, no access module reassigned from another product.

---

## 3. The enforcement point — one function, thirty-four callers

`connect_enabled()` is the single place the whole subsystem already runs through.
Measured, not assumed:

- **15 of the 16** `connect_*_can()` staff gates consult it;
- the 16th (`connect_concierge_can()`) delegates to `connect_market_can()`, which
  does;
- `connect_pro_portal_on()` — the freelancer portal — *is* a call to it;
- the public front door and the organisation join page consult it;
- the client portal's hiring tiles consult it;
- **34 call sites** in total.

So the question is asked there, and everything inherits it:

```php
function connect_enabled() {
    if (function_exists('licence_module_live') && !licence_module_live('connect')) return false;
    return setting_get('connect_enabled', '1') === '1';
}
```

Two questions, in the only safe order: **has this company bought the marketplace**,
then **has it switched it on**.

This is the same shape as M5's `careers_enabled()` and M6's `pcan()` — put the
question at the reader everything already goes through, rather than repeating it
thirty-four times and missing one.

### Master users need no separate fix

`connect_market_can()` already read:

```php
if (!connect_enabled()) return false;     // FIRST
if (is_master()) return true;
```

The ordering was already correct; it simply had nothing commercial to enforce.
Now that `connect_enabled()` carries entitlement, **a master is denied a
marketplace the company has not bought** — with no `is_master()` rewrite
anywhere. Asserted for entitled, unentitled, tenant-disabled and blank states.

---

## 4. Marketplace data reached through another route

M7's lesson — route ownership is not data ownership — applies here. The
marketplace tiles a **client** sees in their own portal, and what a **vendor**
sees in theirs, are marketplace data reached through a different front door.

M6 mapped those keys to `null` ("not bound to a product module") because no such
module existed. They are now bound:

| Portal permission | Was | Now |
|---|---|---|
| `market.post` (client) | unowned | `connect` |
| `market.vouchers` (client) | unowned | `connect` |
| `market.apply` (vendor) | unowned | `connect` |

---

## 5. What Marketplace OFF does *not* do

The rule is **Marketplace OFF ≠ Operations OFF**, and it is asserted:

with Operations and Reporting entitled and the marketplace not, Operations runs,
its calls run, Reporting runs, the Operations and Reporting route gates still
allow, core administration is untouched — and the marketplace is closed.

No Operations table, business rule, deployment or workforce logic was moved or
changed. Quality remains inside Operations. Identity
(`cx_professionals` / `cx_identity_link`), organisations (`business_partners` /
`cx_organisations`) and the Recruitment requirement architecture
(`requisitions` vs `cx_requirements`) are all untouched — M9 is entitlement work,
not convergence work.

---

## 6. Lifecycle — and nothing is destroyed

Asserted end to end: marketplace **ON** → data on record → **OFF** → access
disappears immediately **and the historical rows are still there** → **ON again**
→ access restored, same data.

Entitlement withholds access. It never deletes.

---

## 7. The commercial consequence — read this before deploying

Marketplace is now a **paid module**, and the entitlement rules that govern every
other paid module now govern it:

| Workspace | Result |
|---|---|
| Control install / self-hosted | **Unaffected** — no ceiling applies, the marketplace stays on |
| Hosted, ceiling names `connect` | Marketplace works as before |
| Hosted, ceiling does **not** name `connect` | **Marketplace closes** |
| Hosted, blank entitlement record | **Marketplace closes** (the M3 rule) |

No entitlement was manufactured for existing workspaces, deliberately — that is
M4's standing rule, and inferring a purchase from a setting that **defaults to
ON** would be inventing evidence, not reading it.

**What the platform owner must do:** for each workspace that has bought the
marketplace, add `connect` to its entitlement in the Companies console. The
console already offers it — it iterates `PRODUCT_MODULES`, so the new module
appears as a sellable tick-box with no console change. Plan tiers
(`superadmin_tiers()`) do not name `connect` yet, so it is sold as a separately
paid module until a tier includes it.

---

## 8. What was NOT built

- **No second entitlement system.** No `marketplace_is_licensed()`,
  `marketplace_entitlements` table or parallel licence path. Everything goes
  through `licence_module_live()` → `licence_blocks()` → the existing registry.
- **No schema change.** No table, column or index.
- **No redesign** of Marketplace, Connect, identity, organisation, matching,
  deployment, workforce, Operations, Quality or Recruitment.
- **No RBAC surface added** — no new permissions, no role-default changes.
- **No M10+ work.**

---

## 9. Files changed

| File | Change |
|---|---|
| `lib/licence.php` | `connect` registered as the seventh product module |
| `lib/connect_market.php` | `connect_enabled()` asks entitlement first — 34 callers inherit |
| `lib/portal.php` | `market.post`, `market.vouchers` owned by `connect` |
| `lib/cvp.php` | `market.apply` owned by `connect` |
| `tests/test_m9_marketplace_entitlement.php` | new — 91 assertions |
| `tests/test_module_registry.php` | M2 expectations updated (see the test-results doc) |
| `tests/test_m6_api_action_entitlement.php` | one M6 assertion updated |
| `deploy-check.php` | checksums regenerated |

**Four source files.**
