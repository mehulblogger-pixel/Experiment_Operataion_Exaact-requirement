# Milestone 6 — API / Action Entitlement Matrix

Actual endpoints found in the repository, classified from the code.

Classification: **CORE** (every install has it) · **PAID** (needs a product
module) · **PUBLIC** (no account) · **SYSTEM** (operator/internal) ·
**UNKNOWN** (could not be classified — none remain).

---

## 1. Web-root entry points

| File | Class | What it is | Entitlement |
|---|---|---|---|
| `index.php` | mixed | Front controller. Everything from `ops_dispatch()` onward is gated by M5; the pre-gate part is this milestone's subject (§3). | per route |
| `api.php` | **PUBLIC / SYSTEM** | **Licence server.** One action (`?action=licence&install=ID`). No session, no tenant. Returns only a key already issued for that install id. | **not applicable — deliberately none** |
| `cron.php` | **SYSTEM** | Daily reminder runner, token-gated (`CRON_KEY`). | **deferred to M7/M8** |
| `cron_ads.php` | **SYSTEM** | Frequent Ads Pro lead sync (touches Sales data). | **deferred to M7/M8** |
| `refresh.php` | **SYSTEM** | Clears the PHP code cache. Loads none of the application by design. | n/a |
| `diagnose.php` | **SYSTEM** | Workspace diagnostics, gated by the admin password. | n/a |
| `deploy-check.php` | **SYSTEM** | Upload verifier, admin-gated. | n/a |
| `phase1-inventory.php` | **SYSTEM** | Read-only inventory tool. | n/a |
| `manifest.php` | **PUBLIC** | PWA manifest (app name, theme colour). | n/a |
| `router.php` | **SYSTEM** | Built-in PHP server router for the laptop launchers. Not used on hosting. | n/a |
| `config.php`, `config.local.sample.php`, `tenants.sample.php` | **SYSTEM** | Configuration, not endpoints. | n/a |

---

## 2. The client portal — `pcan()` (`lib/portal.php`)

A second front door: own sign-in, own table, own session, never reaches the route
gate. **Closed in M6.**

| Portal permission | Reads / does | Access module | Product module | Entitlement |
|---|---|---|---|---|
| `calls` | Work orders and visits | `calls` | **operations** | required |
| `reports` | Issued reports, downloads | `idems` | **reporting** | required |
| `reports.decide` | **Accepts / rejects a report** (write) | `idems` | **reporting** | required |
| `invoices` | Invoices and outstanding amounts | `invoicing` | **money** | required |
| `request` | Asks for a new job | `calls` | **operations** | required |
| `complaint` | Raises a complaint or appeal | `complaints` | **operations** | required |
| `deputation` | Deputed personnel, attendance, site reports | `jobs` | **operations** | required |
| `deputation.approve` | **Approves / returns attendance** (write) | `jobs` | **operations** | required |
| `issues` | Nonconformities raised to them | `ncr` | **operations** | required |
| `market.post` | Posts manpower requirements | — | Marketplace | none — governed by `connect_enabled()` |
| `market.vouchers` | Reviews vouchers on own posted jobs | — | Marketplace | none — governed by `connect_enabled()` |

---

## 3. The vendor portal — `vcan()` (`lib/cvp.php`)

A third front door, same shape. **Closed in M6.**

| Vendor permission | Reads / does | Access module | Product module | Entitlement |
|---|---|---|---|---|
| `reports` | Their reports | `idems` | **reporting** | required |
| `issues` | Nonconformities raised to them | `ncr` | **operations** | required |
| `qualification` | Their own approval status | `vendors` | admin (**core**) | always available |
| `market.apply` | Browses and applies to requirements | — | Marketplace | none — own switch |

The freelancer portal (`/pro`, `lib/connect_pro.php`) and public organisation
onboarding (`/join`) are **Marketplace / Connect**, which M2 established is not a
product module. They have their own `connect_enabled()` switch and are correctly
outside entitlement.

---

## 4. Staff paths handled before `ops_dispatch()` (`index.php`)

| Route / action | Class | Operation | Gate after M6 |
|---|---|---|---|
| `''` → dashboard: placement fees | **PAID (hr)** | Reads HR fees and **writes** confirmations | `can('mod.hiring.view') \|\| is_master_of('hiring')` |
| `''` → dashboard: money counts | **PAID (money)** | `ops_invoicing_counts()` | entitlement, then `data.credit` / `finance.reconcile` |
| `''` → dashboard: receivables (`ar_can()`) | **PAID (money)** | Ledger ageing | entitlement, then the RBAC permissions |
| `''` → dashboard: NCR / CAPA, confidentiality | **PAID (operations)** | Register counts | `is_master_of()` instead of `is_master()` |
| `''` → recruitment home (`recruit_home_can()`) | **PAID (hr)** | Recruitment command centre | `is_master_of('hiring')` |
| `partner-add&kind=contract` | **PAID (sales)** | Creates `partner_contracts` | Sales module, then `crm.contract.register` |
| `po` POST `pull-quote` | **PAID (sales)** | Reads a quotation into a PO | Sales module required for this sub-action only |
| `po` (view / add line) | **CORE** | Purchase-order master data | clients/vendors permissions — unchanged |
| `clients`, `vendors`, `partner*` | **CORE** | Commercial master data | `mod.clients.*` / `mod.vendors.*` — unchanged |
| `login`, `logout`, `forgot`, `reset`, `change-password` | **CORE** | Authentication | none — must never be module-gated |
| `setup`, `setup-save` | **CORE** | First-run setup | unchanged |
| `verify`, `verify-pdf` | **PUBLIC** | Client verifies a report they already hold, by its printed code | none by design — "verify it yourself" cannot sit behind a password |
| `complaints-policy` | **PUBLIC** | Policy page | none |
| `careers` | **PAID (hr)** | Public jobs page | **closed in M5** |
| `buy`, `buy-verify` | **PUBLIC** | Self-hosted customer pays for users on the licence server | none — identified by install id |
| `get-started` | **PUBLIC** | New workspace application | **deferred to M7/M8 (public sign-up)** |
| `portal/*`, `vendor/*`, `pro/*`, `join`, `connect*` | external | Separate audiences | §2, §3 |
| `sw.js`, `assets/*`, `manifest.php` | **PUBLIC** | Static assets | none |

---

## 5. Everything reached through `ops_dispatch()`

Gated by `ops_module_gate()` since M5 — explicit route map, then the paid-family
fallback for unmapped routes. All HTTP methods, POST included. Unchanged by M6;
see `M5-ROUTE-ENTITLEMENT-MATRIX.md`.

---

## 6. Inputs that can never establish entitlement

Asserted by test: forging any of these changes nothing.

| Supplied by the client | Effect |
|---|---|
| `module`, `product` (GET or POST) | none — the module is derived server-side from the registry |
| `tenant` (GET or POST) | none — the tenant comes from the request's own resolution |
| `permission`, `role` | none — RBAC resolves from the authenticated user row |
| `saas_entitled_modules`, `modules_off` | none — read from the tenant's settings, written only by provisioning / billing |
