# Milestone 10 — Master Privilege Is Not Entitlement

**Status:** complete · **Suite:** 8,000 passed, 0 failed · **Baseline:** 24ebc5d (M9)

---

## 1. The rule this milestone enforces

> A master may skip a permission check. A master may not conjure a purchase.

Master privilege is an **RBAC** concept — it says "this person may do anything
the product does". Entitlement is a **commercial** concept — it says "this
workspace bought these modules". M10 finishes separating the two.

---

## 2. How the audit was actually done

A fresh inventory was taken from the branch after M9: **400 `is_master()` sites
across 133 files**, plus 105 `is_master_of()` sites and the equivalents that do
not contain the words at all (`is_admin_level()`, `is_coordinator_level()`).

Reading 400 sites by hand would have produced a plausible-looking list and missed
things. So the question was asked the only way that actually answers it:

> Sign in as a **master** with **nothing entitled but core administration**, call
> **every zero-argument gate predicate in `lib/`**, and see which ones still say
> yes.

That probe found **95 gates open** under S-1 and **56 open on core-only**. Each
was then traced — route → handler → gate → access module → product module — and
classified. The probe is now a **permanent test** (§D of the suite), so a gate
added later that opens for an unentitled master fails the build **by name**.

### Why static classification alone was not enough

Most `is_master()` sites sit inside route handlers that `ops_module_gate()`
already refuses (Category B) — a master in an unentitled workspace never reaches
them. The dangerous ones are the sites reachable on routes the gate does **not**
map. The probe finds those directly; grep cannot.

---

## 3. Classification

| Category | Meaning | Count |
|---|---|---|
| **A** | Genuine paid-module bypass — master reached a paid module with no entitlement | **8 sites, 7 gates** |
| **B** | Entitlement already established before the master check (route gate, `pcan()`, `connect_enabled()`) | the large majority |
| **C** | Legitimately core — master access is correct | 52 gates, recorded as the probe baseline |
| **D** | Not an authorisation decision (labels, audit actor, UI copy) | remainder of the 400 |
| **E** | Ambiguous | **none left** — every candidate was traced to a route and a module |

---

## 4. The eight Category A defects

### A1–A3 · `books_can()`, `books_can_issue()`, `books_can_cancel()` — **Money**

`can('finance.reconcile') || can('data.credit') || is_master()`. **Not one term
is a module question.**

The invoice *routes* are gated on Money — but `books_can()` is also what the
**global search** asks before offering an Invoices section, and `/search` is
gated by nothing. So a master, or anyone holding `finance.reconcile`, could read
invoice records in a workspace that had never bought Money.

This is the most serious finding in M10: **a paid module's data reachable through
an ungated, cross-module surface.** It is the same defect and the same fix as
`ar_can()` in M6.

### A4 · `rating_can()` — **Operations**

Inspector ratings are derived from jobs. The `ratings` / `ratings-config` routes
are in neither the gate map nor a paid family, and the gate was
`is_master() || is_coordinator_level() || can('mod.jobs.view')` — only the last
term asked the licence.

### A5 · `timesheet_can()` — **HR or Operations**

Same shape, same story, on the ungated `timesheets` / `timesheet` routes.

### A6 · `inspector_profile_can()` — **HR or Operations**

Identical to A5, on the ungated `inspector-profile` route.

### A7 · `ads_can_manage()` — **Sales**

`can('settings.manage') || is_master()` on the ungated `adspro*` routes. Ads Pro
pulls advertising **leads into the CRM**. M8 already gated the nightly sync in
`cron_ads.php`; the screen that does the same thing was left open. **Two doors to
one operation, disagreeing** — exactly the pattern M7 found with project costing.

### A8 · The global search's **contracts** section — **Sales**

A bare `is_master()` on the ungated `/search` route. A contract is what a won
quotation becomes (Sales) and is also partner master data (core), so master
authority is **scoped** to those two rather than withdrawn.

---

## 5. Four more hardened — honestly labelled as not exploitable

`tally_can()`, `tally_can_manage()`, `billable_can()`, `billable_can_manage()`.

These are **Category B**: their routes are mapped to `invoicing`, so entitlement
is established before they are reached, and they were **not** exploitable. They
were hardened anyway because their shape is precisely the one that *was*
exploitable in `books_can()` — every term RBAC or a bare master flag — which
means their safety rests entirely on the route map staying correct. Asking the
module in the gate itself makes the guarantee structural.

This is stated plainly so the finding count is not inflated: **8 real defects,
4 precautionary.**

---

## 6. What was deliberately NOT done

- **No cosmetic mass rewrite.** 400 `is_master()` sites were audited; **8 were
  changed**. Reordering `is_master() || can(...)` into `can(...) || is_master()`
  was explicitly avoided — M5 established that the reorder achieves nothing,
  because `is_master()` is a bare flag read either way.
- **No new mechanism.** Every fix uses `licence_module_live()` or the existing
  `is_master_of()` helper from M5.
- **No RBAC redesign**, no new permission, no role-default change.
- **No schema change.**
- **No business logic changed** in Operations, Quality, Reporting, Money,
  Recruitment, Marketplace or the Dashboard.

---

## 7. Ordinary permission enforcement was not weakened

Asserted directly, because tightening entitlement must not quietly loosen RBAC:

- a **COORDINATOR** holds `data.credit` by role, so the books legitimately open
  for them **when Money is entitled** — and are refused when it is not;
- an **INSPECTOR**, holding neither finance permission, is refused **even when
  Money is entitled**;
- a signed-out request is refused.

Entitlement became **necessary**. The permission is still necessary too.

---

## 8. Files changed

| File | Change |
|---|---|
| `lib/books.php` | `books_money_live()`; the three `books_can*()` gates ask it first |
| `lib/rating.php` | `rating_can()` requires Operations; master scoped via `is_master_of('jobs')` |
| `lib/timesheet.php` | `timesheet_modules_live()`; master scoped to `['hiring','jobs']` |
| `lib/inspectorprofile.php` | same treatment |
| `lib/adspro.php` | `ads_can_manage()` requires Sales |
| `lib/booksbridge.php` | `ops_books_bridge()` requires Money — matching M8's cron gate |
| `lib/search.php` | contracts section: bare master → `is_master_of(['clients','quotes'])` |
| `lib/tally.php`, `lib/billable.php` | defence in depth (§5) |
| `tests/test_m10_master_entitlement.php` | new — 93 assertions, including the permanent probe |
| `deploy-check.php` | regenerated |

**Nine source files. No existing test modified.**
