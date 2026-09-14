# Milestone 6 — Completion Report

## Verdict: **PASS WITH DOCUMENTED LIMITATIONS**

M6 met every acceptance criterion with a fully green suite and no weakened tests.
The qualifier records eight boundaries M6 did not set out to close — two of them
(cron/background jobs, public sign-up) explicitly assigned to M7/M8 by the
instruction itself — listed in `M6-KNOWN-LIMITATIONS.md`.

---

## 1. Implementation summary

M5 guarded the front door. M6 found the other doors, by working from a mechanical
definition rather than an assumption: **everything that reaches the server without
passing through `ops_dispatch()`**. That produced five findings, all closed.

| # | Finding | Severity | Closed by |
|---|---|---|---|
| **F1** | The **client portal** — a second front door with its own sign-in — served reports, invoices, work orders and deputations, and allowed a client to **accept or reject a report**, without ever asking whether the company still has the module | **High** | `pcan()` asks entitlement first |
| **F2** | The **vendor portal** — a third front door — same gap for reports and nonconformities | **High** | `vcan()` asks entitlement first |
| **F3** | The **dashboard**, drawn before the router, **wrote** HR placement-fee data and read the Money ledger and Sales quotations, bypassed by the master flag and by RBAC-only permissions | **High** | entitlement first, `is_master_of()` for master |
| **F4** | The **entitlement cache outlived the connection it was read from** — correctness depended on ten scattered manual reload calls, and a missed one is a cross-tenant leak | **High** | cache keyed on `db_epoch()`, the idiom `settings_cache()` already uses |
| **F5** | Two **Sales actions posted ahead of the router** — registering a contract, pulling quotation lines | Medium | the Sales module asked for first |

**F1's commercial shape is worth stating on its own.** It is a downgrade leak. A
customer stops paying for Money; from the next day the staff screens refuse — and
the portal carried on showing that customer's own clients their invoices and
ageing, indefinitely. The same for Reporting, where the portal also permitted a
**write**.

---

## 2. Files changed

| File | Change |
|---|---|
| `lib/licence.php` | `licence_module_live()` helper; `licence_disabled()` cache keyed on the database epoch |
| `lib/portal.php` | `PORTAL_PERM_MODULES` map; `pcan()` entitlement-first |
| `lib/cvp.php` | `VENDOR_PERM_MODULES` map; `vcan()` entitlement-first |
| `lib/receivables.php` | `ar_can()` entitlement-first |
| `lib/ops.php` | two pre-gate attention tiles (Sales, HR) |
| `lib/recruit.php` | `recruit_home_can()` — itself an entry point from `index.php` |
| `views/dashboard.php` | money counts, HR placement fees (a write), NCR/CAPA, confidentiality |
| `index.php` | contract registration and quote-pull gated on Sales & CRM |
| `tests/test_m6_api_action_entitlement.php` | **new** — 92 assertions |
| `tests/test_contract_backdoor_guard.php` | one assertion corrected **and strengthened** |
| `deploy-check.php` | checksums regenerated |
| `docs/phase1/M6-*.md` | five documents |

---

## 3. API / action matrix

In `M6-API-ENTITLEMENT-MATRIX.md`, built from actual endpoints in the repository:
11 web-root entry points, 11 client-portal permissions, 4 vendor-portal
permissions, and every `index.php` path handled before the router — each
classified CORE / PAID / PUBLIC / SYSTEM with its access module, product module
and resulting gate. **No endpoint was left UNKNOWN.**

---

## 4. Security findings

Beyond F1–F5 above:

**`api.php` — a correction to the Phase-0 audit.** Phase-0 recorded *"api.php
currently has no entitlement check."* True, and correct as it stands: `api.php`
is the **licence server**, not a tenant API. No session, no tenant, one action,
and it returns only a key already issued for the install id that asks. Module
entitlement is not the applicable control; adding one would break licence sync
for every customer while protecting nothing. Left unchanged, and four tests now
assert that decision so it cannot be silently reversed.

**A correction to M5.** M5's limitation L5 judged the pre-gate panel surface
"cosmetic". It was not — one of those panels performs a database write. Recorded
as C1 in `M6-KNOWN-LIMITATIONS.md` rather than quietly fixed.

**No sensitive information is exposed.** Denials use the existing refusal
conventions and name only the module. No SQL error, stack trace, licence
internal, credential or tenant secret appears in any message added by M6;
`api.php` was re-asserted never to return the underlying error text.

---

## 5. Focused tests

`tests/test_m6_api_action_entitlement.php` — **92 assertions, 0 failed.** Every
group paired DENY + ALLOW. The full §13 scenario matrix passes, point by point
(`M6-TEST-RESULTS.md` §3).

**Checked against mutation, not just run.** Removing the epoch guard makes 3
isolation assertions fail; removing *or* weakening the contract permission guard
makes the corrected test fail. Tests that pass either way would prove nothing.

---

## 6. S-1

Operations ON, Reporting ON, HR / Sales / Money OFF — signed in as a **master**,
exercised through the action paths rather than the routes:

| Through an action path | Result |
|---|---|
| Operations — portal work orders, deputations | **WORKS** |
| Reporting — portal reports, and the client report decision (a write) | **WORKS** |
| Money — portal invoices | **DENIED** |
| Money — pre-gate receivables panel, as a master | **DENIED** |
| Money — profitability to any action path | **DENIED** |
| HR — pre-gate recruitment home, as a master | **DENIED** |
| HR — master authority | **DENIED** |
| Sales — quotes, leads, CRM reporting to any action path | **DENIED** |

---

## 7. Full regression

**7,567 passed · 0 failed · 0 skipped · 454 test files · 95 seconds · PHP 8.4.19.**
Baseline before M6: 7,474.

---

## 8. MySQL / MariaDB status

**Not available, and not tested.** Verified rather than assumed: no `mysql`,
`mysqld` or `mariadb` binary exists in this environment. `pdo_mysql` is loaded but
has no server to reach. **No claim of production database validation is made.**

M6 adds no SQL, no schema change and no new table, column or index. The one change
touching database behaviour keys on `db_epoch()`, which is driver-independent and
already used the same way by `settings_cache()`.

---

## 9. Known limitations

Eight, in `M6-KNOWN-LIMITATIONS.md`: C1 (the M5 correction), L1 (non-module
permissions, narrowed), L2 (dashboard operational widgets), L3 (cron), L4 (public
sign-up), L5 (exports), L6 (hand-written portal maps), L7 (MySQL), L8 (not yet on
the live server).

---

## 10. Deferred items

| Deferred | Why |
|---|---|
| `cron.php`, `cron_ads.php` enforcement | §14 — cron and background jobs are M7/M8 |
| Public sign-up (`get-started`) | §14 — public sign-up is M7/M8 |
| Export / report content enforcement | §14 — routes are already gated by M5; content-level work is later |
| Bringing non-`mod.*` permissions under the ownership map | Registry work (L1), not runtime |
| A test asserting every portal permission is mapped | Build-time guard (L6) |
| Dashboard operational-widget gating | Would be a dashboard redesign (L2) — protected architecture |

---

## 11. Acceptance criteria

| Criterion | Result |
|---|---|
| Paid API/action paths require tenant entitlement | **PASS** |
| Unentitled paid actions denied server-side | **PASS** |
| Master users cannot bypass paid-module entitlement | **PASS** |
| Unknown/unsafe paid actions fail closed | **PASS** |
| Client-supplied module/tenant values cannot bypass | **PASS** — 8 assertions |
| Cross-tenant isolation | **PASS** — mutation-verified |
| Core APIs remain functional | **PASS** |
| Existing valid API behaviour preserved | **PASS** — licence server unchanged and asserted |
| S-1 passes | **PASS** |
| Focused tests pass | **PASS** — 92/0 |
| Full regression passes | **PASS** — 7,567/0 |
| No destructive data changes | **PASS** — no schema change, no data migration |
| No unrelated architecture redesigned | **PASS** |
| M7/M8 work not prematurely implemented | **PASS** — inventoried and deferred |
| Documentation and evidence complete | **PASS** — five documents |

---

## 12. Verdict

**PASS WITH DOCUMENTED LIMITATIONS.**

The commercial outcome: what a company pays for is now what it can reach —
through the menu, through a typed address, through its customers' portal, through
its suppliers' portal, and through an action posted directly at the server. The
rule is the same for the administrator, and it no longer depends on ten scattered
cache resets being remembered.

**STOP. M7 has not been started.**
