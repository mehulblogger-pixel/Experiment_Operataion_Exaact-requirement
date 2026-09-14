# Milestone 9 — Test Results

**Run date:** 2026-09-14 · **Command:** `php tests/run.php`

---

## 1. Headline

| | M8 baseline | After M9 |
|---|---|---|
| Test files | 456 | **457** |
| Assertions passed | 7,800 | **7,907** |
| Failed | 0 | **0** |
| Skipped | 0 | **0** |
| Runtime | 101 s | **98 s** |

- **PHP:** 8.4.19 · **Harness:** `tests/run.php`, the real application on a throwaway SQLite database
- New assertions: **91** in `tests/test_m9_marketplace_entitlement.php`; the rest of the increase is the strengthened registry assertions.

---

## 2. MySQL / MariaDB — explicitly

**MySQL/MariaDB remains the production-authoritative database, and it was NOT
executed.** Verified rather than assumed: no `mysql`, `mysqld` or `mariadb`
binary is installed here; `pdo_mysql` is loaded but has no server to reach.

**No claim of MySQL validation is made.**

M9 introduces **no schema change** — no table, column or index. The changes are a
registry entry and a boolean check in front of an existing function, neither of
which is engine-specific.

---

## 3. The new suite — 91 assertions

| Group | Covers | Assertions |
|---|---|---|
| **A** | Commercial identity — registered, labelled, sellable, claims no access modules, exactly one marketplace key, owns nothing belonging to another product | 12 |
| **B** | Every entitlement state — ENTITLED, NOT_ENTITLED, UNKNOWN (blank), TENANT_DISABLED, rubbish record, LICENCE_BLOCKED (with core surviving) | 14 |
| **C** | The company's own switch still works both ways, and cannot switch ON what was never bought | 3 |
| **D** | Master — ALLOW entitled; DENY unentitled, tenant-disabled and blank; non-master unaffected | 8 |
| **E** | All **16** `connect_*_can()` gates: none opens when unentitled, all reopen when entitled | 2 (over 16 gates each) |
| **F** | Public surfaces — front door and freelancer portal, both directions | 4 |
| **G** | Marketplace data through the client and vendor portals; Operations tiles unaffected | 8 |
| **H** | Marketplace OFF ≠ Operations OFF — Operations, Reporting, route gates and core all still work | 9 |
| **I** | Lifecycle ON → OFF → ON, with the historical rows counted and intact | 7 |
| **J** | Forged `module`, `connect_enabled`, `tenant`, `saas_entitled_modules` buy nothing | 4 |
| **K** | Tenant isolation across a connection switch, **both directions** | 5 |
| **L** | S-1, with the marketplace off and then added | 13 |
| **M** | The control install is never limited | 2 |

`RESULT: 91 passed, 0 failed`

---

## 4. Both directions were proved

Groups A–M assert the ALLOW beside every DENY. Group E is the clearest case: with
the module absent, **no** marketplace gate opens; with it present, **all sixteen**
reopen for a master. An implementation that denied everything would fail half the
suite.

---

## 5. Mutation testing

| Mutation | Result |
|---|---|
| Entitlement check removed from `connect_enabled()` | **20 assertions fail** |
| `connect` unregistered from `PRODUCT_MODULES` | **24 assertions fail** |
| Both restored | **91 passed, 0 failed** |

Both guards are load-bearing.

---

## 6. Existing tests changed — two files, with reasons

Neither was weakened. Both were **inverted to the new architecture and made
stricter**, as §4 requires ("Update the registry tests").

### `tests/test_module_registry.php` (M2)

- **`count(PRODUCT_MODULES) === 6` → `=== 7`.** Not relaxed: it still pins an
  *exact* number, so a module arriving without a decision still fails here. The
  named list gained `connect`.
- **`!isset(PRODUCT_MODULES['connect'])` → inverted.** This assertion carried its
  own comment — *"Marketplace is Milestone 9 and its legacy cloud default is not
  a purchase"* — so it was written as a placeholder **until this milestone**.
  Replaced with four stronger assertions: Marketplace **is** registered; there is
  **no rival `marketplace` key** (one commercial identity, never two); it is
  sellable, not core; it claims **no** access modules; and it owns none of
  `hiring`, `idems`, `invoicing`, `quotes`, `jobs`, `masters`.
- **The Quality assertions are untouched.** Quality remains not independently
  registered, per the architecture lock.

### `tests/test_m6_api_action_entitlement.php`

- One assertion stated the portal's marketplace keys were "deliberately not bound
  to a product module" — true only because no such module existed. Now asserts
  they are bound to `connect`, plus a second assertion for `market.vouchers`.

**No other existing test was modified, skipped or deleted.**

---

## 7. Regression scope

| Area | Result |
|---|---|
| Connect / Marketplace suites (market, bench, match, engage, vouchers, ratings, trust, verify, taxonomy, KPI, geo, privacy, messaging) | pass |
| Client portal, vendor portal, freelancer portal | pass |
| Operations (calls, jobs, vouchers, scheduling, deployment) | pass |
| Reporting / idems | pass |
| Money, Sales / CRM, Recruitment / HR | pass |
| Identity, organisation, taxonomy | pass — untouched |
| Entitlement M2–M8 suites | pass |
| Deploy verification | pass — checksums regenerated |
