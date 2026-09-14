# Milestone 8 — Execution Matrix

Every public and non-interactive execution path found in the repository, with the
module that owns the work it does. Classification is by the **library each
function actually lives in**, not by the function's name.

Legend — **Guard:** what now decides. **Result:** ✅ enforced · ➖ core/public,
correctly ungated · ⏸ deferred.

---

## 1. The nightly run — `cron.php`

| Entry point | Operation | Module | Entitlement | Guard | Result |
|---|---|---|---|---|---|
| `ops_run_reminders()` | Overdue job-closure reminders | `jobs` → operations | required | `$m8('jobs')` | ✅ |
| `contracts_expiry_reminders()` | Contract expiry warnings | `quotes` → **sales** | required | `$m8('quotes')` | ✅ |
| `contracts_idle_warn()` | Idle-contract warning | `quotes` → **sales** | required | `$m8('quotes')` | ✅ |
| `contracts_idle_autoclose()` | Auto-close idle contracts | `quotes` → **sales** | required | `$m8('quotes')` | ✅ |
| `confirm_lapsed_placement_fees()` | Flip placement fees provisional → confirmed (**a write**) | `hiring` → **hr** | required | `$m8('hiring')` | ✅ |
| `joblock_sweep()` | Lock late jobs, alert | `jobs` → operations | required | `$m8('jobs')` | ✅ |
| `equipment_run_cal_reminders()` | Calibration reminders | `equipment` → operations | required | `$m8('equipment')` | ✅ |
| `auth_run_maintenance()` | Expire/suspend inspector authorisations | `competence` → operations | required | `$m8('competence')` | ✅ |
| `crm_run_followups()` | Quotation follow-up e-mails | `quotes` → **sales** | required | `$m8('quotes')` | ✅ |
| `crm_expire_quotes()` | Expire quotations past validity | `quotes` → **sales** | required | `$m8('quotes')` | ✅ |
| `ar_overdue_reminders()` | Chase overdue invoices | `invoicing` → **money** | required | `$m8('invoicing')` | ✅ |
| `idems_run_sla_escalations()` | Report approval SLA escalation | `idems` → **reporting** | required | `$m8('idems')` | ✅ |
| `tosrm_run_recurring()` | Generate recurring calls | `calls` → operations | required | `$m8('calls')` | ✅ |
| `billable_events_sync()` | Maintain the billable-event ledger | `invoicing` → **money** | required | `$m8('invoicing')` | ✅ |
| `ops_run_mis_digest()` | Weekly/monthly MIS digest | `jobs` → operations | required | `$m8('jobs')` | ✅ |
| `ncr_run_reminders()` | Nonconformity chases | `ncr` → operations | required | `$m8('ncr')` | ✅ |
| `capa_actions_overdue()` | Overdue corrective-action tasks | `capa` → operations | required | `$m8('capa')` | ✅ |
| `capa_run_reminders()` | Corrective-action chases | `capa` → operations | required | `$m8('capa')` | ✅ |
| `cmp_run_reminders()` | Complaint chases | `complaints` → operations | required | `$m8('complaints')` | ✅ |
| `idems_vendor_run_reminders()` | Vendor re-assessment reminders | `idems` → **reporting** | required | `$m8('idems')` | ✅ |
| `sitedoc_expiring()` | Site-entry document expiry notices | `identity` → operations | required | `$m8('identity')` | ✅ |
| `competence_due()` | Competence items out of date | `competence` → operations | required | `$m8('competence')` | ✅ |
| `ads_on()` / `ads_sync_now()` | Advertising lead sync | `leads` → **sales** | required | `$m8('leads')` | ✅ |
| `books_bridge_drain()` | Accounts bridge drain | `invoicing` → **money** | required | `$m8('invoicing')` | ✅ |
| `cdoc_run_reminders()` | Controlled-document review chases | `datacontrol` → operations | required | `$m8('datacontrol')` | ✅ |
| `idems_reseal_failed()` | Re-seal failed report seals | `idems` → **reporting** | required | `$m8('idems')` | ✅ |
| `jobs_backfill_cost_basis()` | Backfill job cost basis | `jobs` → operations | required | `$m8('jobs')` | ✅ |
| `appr_tick()` | Recruitment approval SLA tick | `hiring` → **hr** | required | `$m8('hiring')` | ✅ |
| `engagement_backfill()` | Normalise engagement ids across tables | — | **core** | none | ➖ |
| `tapi_alerts_run()` | Analytics alerts | — | **core** (metrics gated in M7) | none | ➖ |
| `licsync_checkin()` | Licence server check-in | — | **core** | none | ➖ |
| `licence_run_reminders()` | Licence expiry reminders | — | **core** | none | ➖ |
| `audit_trim_old()` | Trim the audit trail | — | **core** | none | ➖ |
| `integrity_run()` | Data-integrity self-check | — | **core** | none | ➖ |
| `iddoc_encrypt_backfill()` | Encrypt stored identity documents | — | **core** (privacy obligation) | none | ➖ |

**28 paid steps gated · 7 core steps deliberately untouched.** No blanket exit:
a lapsed module never stops the rest of the run.

---

## 2. The frequent sync — `cron_ads.php`

| Entry point | Operation | Module | Entitlement | Guard | Result |
|---|---|---|---|---|---|
| `ads_sync_now()` | Pull advertising leads into the CRM | `leads` → **sales** | required | `licence_module_live('leads')`, asked **before** `ads_on()` | ✅ |
| `licsync_checkin()` | Licence check-in | — | **core** | none | ➖ |

---

## 3. Public routes

| Entry point | Operation | Module | Entitlement | Guard | Result |
|---|---|---|---|---|---|
| `GET /careers` | Public job list | `hiring` → **hr** | required | `careers_enabled()` (M5) | ✅ |
| `GET /careers?job=N` | A single advertised opening | `hiring` → **hr** | required | `careers_route()` → `careers_enabled()` | ✅ |
| `POST /careers?job=N` | **Application submission** — creates a candidate, uploads a CV, writes to the pipeline | `hiring` → **hr** | required | `careers_route()` **and** `careers_apply()` itself | ✅ |
| `careers-admin` | Careers configuration | `hiring` → **hr** | required | route gate (M5) | ✅ |
| `GET /verify`, `/verify-pdf` | A client checks a report they hold, by its printed code | — | **public by design** | none | ➖ |
| `GET/POST /get-started` | New workspace application (PENDING, off by default) | — | **core platform** | operator switch | ➖ |
| `/buy`, `/buy-verify` | Self-hosted customer pays on the licence server | — | **public** | signing key + payment config | ➖ |
| `/login`, `/logout`, `/forgot`, `/reset` | Authentication | — | **core** | none — must never be module-gated | ➖ |
| `/complaints-policy` | Public policy page | — | **public** | none | ➖ |
| `/portal/*` | Client portal | various | required | `pcan()` (M6) | ✅ |
| `/vendor/*` | Vendor portal | various | required | `vcan()` (M6) | ✅ |
| `/pro/*`, `/join`, `/connect`, `/p/<token>` | Marketplace / Connect | Marketplace | — | `connect_enabled()` | ⏸ M9 |
| `sw.js`, `assets/*`, `manifest.php` | Static assets | — | **public** | none | ➖ |

---

## 4. Direct / CLI execution

| File | Operation | Module | Entitlement | Result |
|---|---|---|---|---|
| `php cron.php` | Nightly run on the **control install** (no HTTP host) | mixed | per step; control is never limited | ✅ |
| `php cron_ads.php` | Lead sync on the control install | sales | per step | ✅ |
| `lib/saas_provision_cli.php` | Provision a new workspace database | — | **core platform** — gating it would be circular | ➖ |
| `lib/saas_sync_cli.php` | Write a company's entitlement **into** its store | — | **core platform** — it is the mechanism that sets entitlement | ➖ |
| `lib/backup.php` (CLI path) | Workspace backup | — | **core** — a company's own data | ➖ |
| `refresh.php`, `diagnose.php`, `deploy-check.php`, `phase1-inventory.php` | Operator tools, password/admin-gated | — | **system** | ➖ |
| `router.php` | Dev server router, not used on hosting | — | **system** | ➖ |
| `api.php` | Licence server (classified in M6) | — | **public / system** | ➖ |

---

## 5. Webhooks and callbacks

| Path | Finding |
|---|---|
| `lib/webhookq.php` | An **outbound** delivery queue — `webhookq_enqueue()` and `webhookq_cron()` have **no callers anywhere**. Dormant infrastructure; nothing to enforce today. Recorded as limitation L3 |
| `appr_callback()` | An internal function in the recruitment approval engine, not an HTTP endpoint. Reached only through `appr_tick()`, which is gated |
| `buy-verify` | A payment return on the **licence server**, gated on the server's signing key and payment configuration. No tenant module operation |

**No inbound webhook in this repository creates or modifies tenant business
records.** No external request supplies a module, tenant, account or plan that is
trusted as proof of entitlement.

---

## 6. Inputs that cannot establish entitlement

| Supplied by the caller | Effect |
|---|---|
| `module`, `product` | none — derived server-side |
| `tenant` | none — the workspace comes from the **host name** |
| `saas_entitled_modules`, `modules_off` in a request | none — read from the workspace's own settings |
| `key=` on a cron URL | authenticates the **caller**, never the entitlement |
