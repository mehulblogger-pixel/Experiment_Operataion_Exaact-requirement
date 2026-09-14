# Milestone 6 — Test Results

**Run date:** 2026-09-14 · **Command:** `php tests/run.php`

---

## 1. Headline

| | Before M6 | After M6 |
|---|---|---|
| Test files | 453 | **454** |
| Assertions passed | 7,474 | **7,567** |
| Failed | 0 | **0** |
| Skipped | 0 | **0** |
| Runtime | — | **95 seconds** |

- **PHP:** 8.4.19
- **Database:** SQLite (see §2)
- New assertions: **93** — 92 in the new M6 suite, 1 added to an existing test.

---

## 2. Database — stated plainly

**The suite ran on SQLite. MySQL/MariaDB was NOT available and was NOT tested.**

Verified, not assumed: no `mysql`, `mysqld` or `mariadb` binary is installed in
this environment. The `pdo_mysql` extension is loaded, but there is no server for
it to reach.

**Nothing here is evidence of MySQL production behaviour.** No claim of
production database validation is made.

What that does and does not limit:

- M6 adds **no SQL, no schema change, no new table, column or index**. The
  changes are boolean decision logic — entitlement lookups, permission ordering,
  and one cache keyed on an existing counter.
- The one change that touches database *behaviour* rather than decision logic is
  the cross-tenant cache fix, and it keys on `db_epoch()`, which is driver-
  independent and already used the same way by `settings_cache()`.
- It remains an honest gap, not a dismissed one.

---

## 3. The new suite — `tests/test_m6_api_action_entitlement.php` (92 assertions)

Every group is written as a pair: the DENY it must now produce **and** the ALLOW
that proves a paying customer is unaffected.

| Group | Covers | Assertions |
|---|---|---|
| Helper | `licence_module_live()` — core always live, unowned fails closed, and it agrees with `licence_blocks()` for every module ("one rule, two readers") | 11 |
| Client portal | Entitled serves; downgrade stops reports, the report-decision **write**, and invoices; entitled modules untouched; blank entitlement closes everything; a self-disabled module stops too; marketplace keys unaffected | 21 |
| Vendor portal | Live sign-in, entitled allows, unentitled denies, core never withdrawn | 7 |
| Staff pre-gate | `ar_can()` and the HR panel/home — ALLOW when entitled, DENY when not, **including to a master** | 7 |
| Tampered input | Forged `module`, `product`, `tenant`, `permission`, `role`, `saas_entitled_modules`, `modules_off` in GET, POST and REQUEST change nothing | 8 |
| Cross-tenant | Company B does not inherit company A's cached entitlement; B gets its own; the portal follows the switch | 4 |
| S-1 | The mandatory scenario through the action paths, as a master | 15 |
| Core / public | Seven core access modules stay live; `api.php` deliberately not module-gated; control install never enforced against | 17 |
| Fixtures / restore | sign-in fixtures and teardown | 2 |

`RESULT: 92 passed, 0 failed`

### The §13 matrix, point by point

| Scenario | Expected | Result |
|---|---|---|
| Entitled paid API | Allowed | pass |
| Unentitled paid API | Denied | pass |
| Disabled module API (company switched it off) | Denied | pass |
| Licence-blocked API | Denied | pass |
| Blank entitlement | Denied | pass |
| Unknown action / module | Denied where paid or unsafe | pass |
| Master + unentitled | Denied | pass |
| Master + entitled | Normal RBAC | pass |
| Cross-tenant entitlement | Isolated | pass |
| Core API | Preserved | pass |
| Valid existing API consumer | Preserved | pass (licence server asserted unchanged) |
| Tampered module input | Cannot bypass | pass |
| Tampered tenant input | Cannot bypass | pass |

---

## 4. The tests were checked against mutation, not just run

A test that passes whether or not the fix is present proves nothing. Two of the
most important claims were verified by breaking the code on purpose:

| Mutation | Result |
|---|---|
| Epoch guard removed from `licence_disabled()` | **3 isolation assertions fail** — the cross-tenant test genuinely catches it |
| `crm.contract.register` guard removed from `index.php` | corrected test **fails** — it still detects removal |
| Same guard weakened to "any signed-in user" | corrected test **fails** — it still detects weakening |

---

## 5. Existing test changed — one, with the reason

**`tests/test_contract_backdoor_guard.php`** — one assertion corrected, and a
second added.

- **What it asserted:** that `if ($kind === 'contract') {` is *immediately*
  followed by `ops_require(can('crm.contract.register') || is_master()`.
- **Why it failed:** M6 added an earlier check in front of it — the Sales & CRM
  module must be licensed at all. The permission guard the test exists to protect
  was untouched; what broke was an assertion about **layout**, not about the guard.
- **How it was corrected:** the pattern still requires that exact permission
  guard inside the contract branch, but no longer insists it be the first
  statement. Proven by the mutation check in §4 — removal and weakening both
  still fail it.
- **And strengthened:** a new assertion requires the M6 entitlement check to be
  present too, so that guard cannot be silently dropped either.

This test's expectation was **not** weakened to obtain a green result, and no
other existing test was modified, skipped or deleted.

One bug was found in **my own new test** during the run — the cross-tenant case
asserted the tenant context before the connection was rebuilt, and `config.php`
re-resolves that context on rebuild, so it was reading as a control install. The
test setup was corrected; the implementation was correct throughout, which §4's
mutation check confirms.

---

## 6. Regression scope

| Area | Result |
|---|---|
| Client portal | pass |
| Vendor portal (CVP) + governance | pass |
| Operations (calls, jobs, vouchers) | pass |
| Reporting / idems | pass |
| Money (invoicing, receivables, profitability) | pass |
| Sales / CRM (quotes, leads, contracts) | pass |
| Recruitment / HR + careers | pass |
| Dashboard | pass |
| TAPI (analytics) | pass |
| Entitlement engine M3 / migration M4 / registry M2 / runtime M5 | pass |
| Permissions, no-lockout, dead-gate checks | pass |
| Deploy verification | pass — checksums regenerated after the code change |
