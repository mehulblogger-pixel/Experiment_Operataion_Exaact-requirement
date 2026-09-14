# Milestone 11 — Duplicate Flow Matrix

Verified by reading call sites. An automated `INSERT INTO`-by-route pass produced
16 candidates but misattributes (it binds an INSERT to the nearest preceding route
label), so it was **discarded** rather than reported. Seed and demo files excluded
throughout.

---

## 1. Navigation duplicates

| Existing route/screen | Function | Duplicate? | Authoritative | Action | Redirect / deprecation | Data impact | Test |
|---|---|---|---|---|---|---|---|
| `/templates` tile in **Sales** | Template library | **Yes** — same label, same bare route as the Admin tile | `/templates` (a router) | Tile now asks `?kind=quote`, labelled "Quotation templates" | none — the route is unchanged | none | ✅ |
| `/templates` tile in **Admin** | Template library | **Yes** — the other half | same | Tile now asks `?kind=report`, labelled "Report templates" | none | none | ✅ |
| All other 97 tiles | — | **Unique** | — | none | — | none | ✅ |

**Result: 0 destinations offered by two areas.**

---

## 2. Duplicate business doors — assessed, mostly kept

The instruction's own rule (§15: *do not remove legitimate actions*; §1: *do not
merge concepts that are intentionally different*) decides most of these.

| Business action | Doors | Authoritative | Verdict | Action taken | Data impact |
|---|---:|---|---|---|---|
| **Register a contract** | 4 | The won-quotation path (`crm.php`) | **Secondary doors are legitimate** — the partner form carries an "against quotation" selector *and* a "recorded directly" option | Authoritative path now stated **before** the form, not only after a partner already has contracts (C4). Door kept, already permission-gated in M6 | none |
| **Create a customer / partner** | 6 | `partner-new` (`index.php`) | **Legitimately separate** — bulk import, lead conversion and marketplace onboarding each have a real reason to create a partner | None. Documented; merging them would remove working capability | none |
| **Create a voucher** | 2 | `voucher` | Second is a deliberate quick-add | None. Documented | none |
| **Add PO line items** | 2 | `po` | Second is the partner-screen sub-form for the same PO | None. Documented | none |
| **Books drain** (screen vs nightly job) | 2 | the nightly job | Was a genuine disagreement — the screen did not ask about entitlement | **Already fixed in M10** | none |
| **Ads Pro sync** (screen vs nightly job) | 2 | the nightly job | Same | **Already fixed in M10** | none |

---

## 3. Legacy and address-only routes

Per §25, no route was deleted. Every address still resolves.

| Route | Status | Action |
|---|---|---|
| `/backup` | was address-only | **Given an Admin tile** |
| `/ai-settings` | was address-only | **Given an Admin tile** |
| `/duplicates` | was address-only | **Given a Directory tile** |
| `/feature-gates`, `/financial-control`, `/compliance-rules` | super-admin / control-install screens | Left — they are not tenant navigation |
| `/dt-columns` | a preferences endpoint, not a screen | Left |
| `/billing`, `/entity-360`, `/ads-roi`, `/boss-renew` | real screens, but which area owns them is a product decision | Left, documented as limitation L2 |
| `/templates` (bare) | still resolves, and still routes by `?kind=` | Kept for bookmarks and links |

---

## 4. Things explicitly NOT merged

| Kept distinct | Why |
|---|---|
| `requisitions` vs `cx_requirements` | §9 — distinct architectural objects |
| candidates / persons / employees / inspectors / `cx_professionals` | §8 — identity architecture is authoritative |
| Security role vs staff position vs internal department vs vacancy department vs vacancy designation | §7 — intentionally different concepts |
| `business_partners` vs `cx_organisations` | §15 of M9 — CONNECT/MAP architecture |
| Quality inside Operations | architecture lock |

No master was merged and no database record was touched.
