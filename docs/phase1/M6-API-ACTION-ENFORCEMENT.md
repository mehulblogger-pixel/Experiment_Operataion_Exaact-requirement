# Milestone 6 — API / Action Entitlement Enforcement

**Status:** complete · **Suite:** 7,567 passed, 0 failed · **Baseline:** 724b0ba (M5)

---

## 1. What this milestone was for, in plain language

M5 put a guard on the front door — every screen the router serves now asks
whether the company has bought the module.

M6 is about the **other doors**. A web application is not reached only through
its menu. It has side entrances: a customer-facing portal with its own password,
a vendor portal with another, panels that are drawn before the router gets
involved, and actions that are posted straight to the server.

The rule M6 enforces is simple: **knowing how to call something is not the same
as being entitled to it.** The browser is never the security boundary.

---

## 2. Where the doors actually were

The enforcement point added in M5 is `ops_module_gate()`, called as the first
statement of `ops_dispatch()`. So the honest definition of M6's surface is
mechanical rather than a matter of opinion:

> Everything that reaches the server **without passing through
> `ops_dispatch()`**.

Working from the code rather than from assumption, that is exactly five things.

### F1 · The client portal — the biggest one

The client portal is a **second front door**: its own sign-in, its own table
(`client_users`), its own session key, its own permission list. It is dispatched
in `index.php` ahead of the staff application and never touches the route gate.

Its permissions include `reports` (Inspection reporting), `invoices` (Money),
`calls` / `deputation` / `complaint` / `issues` (Operations) — and
`reports.decide`, which **accepts or rejects a report on the company's behalf**.
None of them ever asked whether the company still has the module.

**The commercial case this closes is a downgrade.** A customer stops paying for
Money. From the next day the staff screens refuse. The portal carried on showing
that customer's clients their invoices and ageing, indefinitely.

**Closed by** asking the entitlement question inside `pcan()` — the single
function every portal permission check already goes through — with an explicit
map from each portal permission to the access module it reads.

### F2 · The vendor portal

A **third front door**, with the same shape and the same gap: `reports`
(Reporting) and `issues` (Operations) served with no entitlement question.
Closed the same way, in `vcan()`.

### F3 · The dashboard, drawn before the router

`index.php` renders the dashboard itself; `ops_dispatch()` is never reached. Three
paid-module paths ran there:

| Path | What it did | Why it slipped through |
|---|---|---|
| `views/dashboard.php` placement fees | **Wrote** — `confirm_lapsed_placement_fees()` flips provisional fees to confirmed — and read HR money figures | `can('mod.hiring.view') \|\| is_master()` — the master flag answered first |
| `ar_can()` (receivables / ageing) | Read the Money ledger | Gated on `finance.reconcile` / `data.credit`, which are **RBAC permissions, not module permissions**, so no licence question was ever asked |
| Attention tiles (`lib/ops.php`) | Read Sales quotations and HR interviews | `crm.quote.create` (not a module permission), and `is_master()` |

This is worth stating plainly because **M5's known limitation L5 said this
surface was cosmetic.** It was not. One of these paths performs a database
write. That correction is recorded in `M6-KNOWN-LIMITATIONS.md`.

### F4 · The entitlement cache outlived its connection

`licence_disabled()` held its answer in a plain static variable. That answer is
read from **one company's database**. The application switches the live database
inside a single process in several ordinary situations — choosing a company at
login, "log in as", provisioning, the owner console walking its tenants — and
`db(true)` bumps a counter (`db_epoch()`) each time it does.

Correctness depended on roughly ten scattered `licence_disabled(true)` calls at
those switch points. A forgotten one is a cross-tenant entitlement leak.

The codebase had already solved this for settings. `settings_cache()` keys on the
epoch, and its own comment says why: *"Without this, the settings read inside a
company's workspace would be the CONTROL database's — a cross-company leak of
modules, seat limits and every other setting."* `licence_disabled()` now follows
the same idiom. The explicit calls still stand; a missed one is now harmless
instead of a leak.

### F5 · Two Sales actions posted ahead of the router

| Action | Operation | Fix |
|---|---|---|
| `partner-add&kind=contract` | Creates a `partner_contracts` row — registering a contract, which is Sales & CRM work | The CRM door for the same operation sits behind the route gate and is refused without the module. This second door now asks the same question, so the two agree. |
| `po` POST `pull-quote` | Reads a **quotation** and copies its lines into a purchase order | The purchase order itself is core commercial data and stays open. Only this one sub-action reaches into a paid module, and only it is refused. |

---

## 3. What `api.php` actually is — a correction to the Phase-0 audit

The Phase-0 audit recorded: *"api.php currently has no entitlement check."*

That is literally true, and on inspection it is **correct as it stands**.
`api.php` is not a tenant API. It is the **licence server** endpoint: no session,
no tenant, exactly one action (`?action=licence`), and the only thing it returns
is a key that was already issued for the install id that asks. Customers' installs
call it to pull their own licence.

Module entitlement is not the applicable control for it, and adding one would
break licence sync for every customer while protecting nothing. It is classified
PUBLIC / SYSTEM and deliberately left unchanged — asserted by four tests so the
decision cannot be quietly reversed.

---

## 4. The order, everywhere

Every change above enforces the same sequence, and never the reverse:

```
tenant entitlement  →  user permission  →  business validation  →  action
```

No client-supplied value takes part in the first step. The module is derived
server-side from the registry; the tenant from the request's own resolution.

---

## 5. What was NOT built

- **No new entitlement system.** One helper was added — `licence_module_live()` —
  and it is `licence_blocks()` read the other way round. Asserted in the tests as
  "one rule, two readers" so the two can never drift.
- **No schema change.** No new table, column or index.
- **No redesign of API responses.** Denials use the existing refusal conventions;
  no SQL error, stack trace, licence internal or tenant secret is exposed.
- **No rebuild** of the entitlement engine, RBAC, Operations, Quality, Dashboard,
  Reporting, Money, Marketplace, Recruitment or TAPI.
- **No M7/M8 work.** Cron, background jobs, exports and public sign-up were
  inventoried and left alone — see `M6-KNOWN-LIMITATIONS.md`.

---

## 6. Files changed

| File | Change |
|---|---|
| `lib/licence.php` | `licence_module_live()` added; `licence_disabled()` cache keyed on `db_epoch()` |
| `lib/portal.php` | `PORTAL_PERM_MODULES` map; `pcan()` asks entitlement first |
| `lib/cvp.php` | `VENDOR_PERM_MODULES` map; `vcan()` asks entitlement first |
| `lib/receivables.php` | `ar_can()` asks entitlement first |
| `lib/ops.php` | Two pre-gate attention tiles (Sales, HR) |
| `lib/recruit.php` | `recruit_home_can()` — itself an entry point from `index.php` |
| `views/dashboard.php` | Money counts, HR placement fees (a write), NCR/CAPA, confidentiality |
| `index.php` | Contract registration and quote-pull gated on Sales & CRM |
| `tests/test_m6_api_action_entitlement.php` | new — 92 assertions |
| `tests/test_contract_backdoor_guard.php` | one assertion corrected and strengthened (documented in-file) |
| `deploy-check.php` | checksums regenerated |
