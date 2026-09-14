# Milestone 8 — Completion Report

## Verdict: **PASS WITH DOCUMENTED LIMITATIONS**

M8 met every acceptance criterion with a fully green suite, no modified tests and
no schema change. The qualifier records nine boundaries in
`M8-KNOWN-LIMITATIONS.md`, two of which matter operationally (L1, L5).

---

## 1. Implementation

### Files changed — three source files

| File | Change |
|---|---|
| `cron.php` | Per-step entitlement gate on **28 paid steps**; 7 core steps deliberately left ungated; an operator-facing summary of what was skipped |
| `cron_ads.php` | The advertising lead sync gated on Sales & CRM, asked **before** the feature switch |
| `lib/careers.php` | `careers_apply()` — the function that creates a candidate — asks the HR question itself |
| `tests/test_m8_public_background_entitlement.php` | **new** — 125 assertions |
| `deploy-check.php` | checksums regenerated |

### Helpers reused / extended

**Reused, none created.** Every check goes through `licence_module_live()` →
`licence_blocks()` → `licence_owner()` → the existing registry. `cron.php` wraps
it in one local closure so each step reads as a single condition; the file
mentions the entitlement engine on exactly **one line**, asserted by test.

The M3 precedence model is unchanged. No defect in it was found.

### Routes protected

The public careers page (M5) **and** its application POST (M8). Every other
public route was inventoried and classified — see §4.

### Background jobs protected

28 nightly steps across People & hiring, Sales & CRM, Money, Inspection reporting
and Operations, plus the frequent advertising sync. 7 core steps — audit
trimming, licence sync, licence reminders, integrity checks, identity-document
encryption, the engagement backfill and analytics alerts — run whatever the plan
says.

### Public functions protected

`careers_enabled()` (M5) and `careers_apply()` (M8) — page and submission, so
securing one is not mistaken for securing the other.

### Webhook / CLI paths reviewed

All reviewed, none required a gate. `lib/saas_provision_cli.php` and
`lib/saas_sync_cli.php` are core platform mechanisms — one creates a workspace,
the other **writes** its entitlement — so gating them on entitlement would be
circular. `lib/webhookq.php` is dormant with no callers. No inbound webhook in
this repository creates or modifies tenant business records.

### Tests added / modified

**Added:** one file, 125 assertions. **Modified: none.**

---

## 2. Security

| Property | Evidence |
|---|---|
| **Fail-closed** | NOT_ENTITLED, LICENCE_BLOCKED, TENANT_DISABLED, blank entitlement, unowned module and unknown module all deny paid background work — asserted individually |
| **Master** | There is no cron = master. A master is denied HR background work; with **nobody signed in at all** the answer is identical |
| **Tenant isolation** | Across a live connection switch, in one process, **both directions**: A ON → B OFF, and B OFF → C ON. Neither inherits the other |
| **Cache isolation** | The M6 epoch-keyed entitlement cache continues to apply; the isolation assertions deliberately call no cache reload |
| **Direct URL** | The public careers page refuses without HR, whatever the Careers flag says |
| **Direct POST** | A direct application POST is refused **and writes nothing** — candidate rows counted before and after |
| **Background** | Verified by executing `cron.php` twice, unrestricted and restricted |
| **No disclosure** | The public refusal names no licence, entitlement or module internals — asserted |
| **No trusted input** | Forged `module`, `tenant`, `product` and `saas_entitled_modules` parameters change nothing; the workspace comes from the **host name** |

---

## 3. Testing

| | Result |
|---|---|
| **Focused M8** | **125 passed, 0 failed** |
| **Full regression** | **7,800 passed · 0 failed · 0 skipped · 456 files · 101 s · PHP 8.4.19** (M7 baseline 7,675) |
| **S-1** | Operations and Reporting background work **WORKS**; core work **WORKS**; HR, Sales and Money background work **DENIED**; the public careers page **DENIED**; a public application POST **DENIED** and writes nothing |
| **Cross-tenant** | Pass, both directions, same process |
| **MySQL/MariaDB** | **NOT EXECUTED** — no server available (verified). No claim of MySQL validation |
| **Mutation tests** | Performed. HR cron gate removed → 1 failure; `careers_apply()` check removed → 7 failures; ads gate removed → 1 failure |

### The end-to-end proof of §10

`cron.php` was run against a throwaway database with Sales, Money, HR and
Reporting switched off. Those steps were skipped, the run reported
`Skipped — not enabled for this workspace: Sales & CRM, People & hiring, Money,
Inspection reporting`, and **Operations and every core step continued normally**.
A lapsed module does not take the nightly run down.

---

## 4. Classified and deliberately left alone

| Path | Class | Why |
|---|---|---|
| `saas_provision_cli.php`, `saas_sync_cli.php` | core platform | They create a workspace and set its entitlement — gating them would be circular |
| `get-started` | core platform | Creating a SaaS account is not a paid module (§17); off by default, creates a PENDING application |
| `verify`, `verify-pdf` | public | A client checking a report they already hold, by its printed code |
| `login`, `logout`, `forgot`, `reset` | core | Authentication must never be module-gated |
| `buy`, `buy-verify`, `api.php` | public / licence server | No tenant module operation |
| `backup` | core | A company exporting its own data |
| `webhookq.php` | dormant | No callers anywhere |
| `/pro`, `/join`, `/connect`, `/p/<token>` | Marketplace / Connect | The M9 boundary — documented, not implemented |

---

## 5. Careers remains opt-in

| Situation | Result |
|---|---|
| HR entitled + Careers on | public page serves normally |
| HR entitled + Careers **off** | page does not serve — **HR stays ENTITLED**, the rest of Recruitment untouched |
| HR not entitled + Careers flag on | refused |
| HR not entitled + direct POST | refused, nothing written |

---

## 6. Limitations deliberately deferred

**L1** a new cron step added later without a gate would be ungated (a build-time
check would close it) · **L2** a skipped step is visible to the operator, not
inside the workspace · **L3** the dormant webhook queue will need per-channel
ownership if wired up · **L4** `careers_apply()` checks entitlement, not the
opt-in · **L5** background work is not replayed when a module is re-enabled —
time-window work is simply missed · **L6** Marketplace/Connect deferred to M9 ·
**L7** public signup remains core · **L8** MySQL not executed · **L9** not yet
uploaded to the live server, and note that `cron.php`/`cron_ads.php` sit outside
the fingerprint `deploy-check.php` watches.

---

## 7. Acceptance criteria

| Criterion | Result |
|---|---|
| Public executable surfaces inventoried | **PASS** |
| Careers GET and submission paths protected | **PASS** |
| Public recruitment cannot bypass HR entitlement | **PASS** |
| Background/cron jobs inventoried | **PASS** — 35 steps classified |
| Paid-module background operations enforce entitlement | **PASS** — 28 steps |
| Core background operations remain functional | **PASS** — 7 steps, proven ungated |
| Direct executable/CLI paths reviewed | **PASS** |
| Webhook/callback paths reviewed | **PASS** |
| Master cannot bypass module entitlement | **PASS** |
| Unknown/unowned paid execution fails closed | **PASS** |
| Tenant-disabled/blocked/unentitled fails closed | **PASS** |
| Cross-tenant entitlement/cache isolation | **PASS** — both directions |
| S-1 passes | **PASS** |
| Regression passes with no unjustified weakening | **PASS** — 7,800/0, nothing modified |
| MySQL/MariaDB status explicitly reported | **PASS** — not executed |
| Documentation complete | **PASS** — five documents |
| Known limitations documented | **PASS** — nine |
| No unrelated module redesigned or damaged | **PASS** |
| M9 NOT started | **PASS** |

---

**STOP. M8 ends here.** M9 (Marketplace entitlement) and Phase 2 have not been
started.
