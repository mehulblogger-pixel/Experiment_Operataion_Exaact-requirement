# Milestone 14 — IDOR Test Matrix

`phpapp/tests/test_m14_object_authorization.php` — **46 assertions, 0 failed.**
Full suite: **8,245 passed, 0 failed** · 462 files · PHP 8.4.19 · ~100s.

Every attack was executed against the real application — real routes, real
dispatch, real session — **before** any code changed. Each victim record carried
a marker string that either came back in the response or did not.

---

## Attack setup

```
Tenant A (one database)          Tenant B (a separate database)
├── Branch A (91)  ← attacker    └── the cross-tenant direction, both ways
└── Branch B (92)  ← every victim record
```

The attacker holds **every permission in the product** and **only Branch A**.

---

## 1. Read attacks — detail by id

| Object | List hides it? | Detail before | Detail after |
|---|---|---|---|
| Quotation | yes | **SERVED** | refused |
| Opportunity | yes | **SERVED** | refused |
| Lead | yes | **SERVED** | refused |
| Requisition | yes | **SERVED** | refused |
| Complaint | yes | **SERVED** | refused |
| Receipt | yes | **SERVED** | refused |
| Call | yes | refused (M13) | refused |
| Job / Invoice / Voucher / CAPA | yes | refused | refused |
| Candidate | **no — list shows it too** | served | served — *correct, not branch-scoped* |

## 2. Mutation attacks — the part that mattered most

| Route | Before | After |
|---|---|---|
| `lead-delete` | **OBJECT DELETED** | object unchanged |
| `lead-edit` | **OBJECT CHANGED** (`company_name → PWNED`) | object unchanged |
| `lead-move` | no change | object unchanged |
| `opportunity-delete` | **OBJECT DELETED** | object unchanged |
| `opportunity-edit` | **OBJECT CHANGED** (`name → PWNED`) | object unchanged |
| `opportunity-move` | **OBJECT CHANGED** (stage + probability) | object unchanged |

Every mutation attack verified **both** that it was denied **and** that the
object was byte-for-byte unchanged afterwards.

## 3. File / download and export

| Route | Before | After |
|---|---|---|
| `lead-file` (another branch's document) | **FULL FILE BODY SERVED** | refused |
| `quote-pdf` | refused* | refused |
| `invoice-print`, `voucher-print` | refused | refused |
| `project-costing-print` | served | served — *branch-global by design; see L2* |

\* `quote-pdf` sits inside the quotation dispatcher, so it closed with the module gate.

## 4. Cross-tenant — both directions (§24)

```
A -> B : session bound to 'tenant-a', presented in 'tenant-b' => REFUSED
B -> A : session bound to 'tenant-b', presented in 'tenant-a' => REFUSED
Tenant B database tables at creation: 0  (an id from A names nothing here)
```

Two independent barriers: separate databases per tenant, and M13's workspace
binding on the identity itself.

## 5. Negative / malformed identifiers (§16)

`missing · zero · negative · 20-digit · nonexistent · 7abc · empty · array ·
1 OR 1=1 · float · padded`

All eleven reach a **clean decision**. No PHP warning, no SQL error, no throw,
no partial write. The test installs an error handler that turns any notice into
a failure, so "it happened not to crash" cannot pass for "it is handled".

## 6. Role and master (§14, §15)

| | Result |
|---|---|
| Full permissions + wrong branch | **Refused** — permission is not scope |
| Correct branch | Full access retained (read, edit, delete, download) |
| Master | Crosses branch scope — ALL-scope by architecture, documented; still bound to one tenant by M13 |

---

## Mutation testing — every fix is load-bearing

Each fix reverted in isolation, suite re-run:

| Fix removed | Result |
|---|---|
| `scope_office_allows()` branch check → always allow | **FAIL** |
| leads gate not called | **FAIL** |
| opportunities gate removed | **FAIL** (45/1) |
| complaints gate removed | **FAIL** (45/1) |
| quotations gate removed | **FAIL** (45/1) |
| requisitions gate removed | **FAIL** (45/1) |
| receipt guard removed | **FAIL** (45/1) |
| *all restored* | **46 passed, 0 failed** |

No assertion passes for a reason other than the fix being present.

---

## Regressions re-run

| | Result |
|---|---|
| **M13** (workspace binding, reset, failed login, call IDOR, disclosure, CSRF, entitlement, master, cache) | intact — 51/51 within the full run |
| **S-1** (Operations + Reporting entitled; HR / Sales / Money / Marketplace not) | intact |
| **Not over-fixed** | owning branch keeps full use; unassigned records stay visible to every branch |
| **Full suite** | **8,245 passed, 0 failed, 0 skipped** |

## Environment

| | |
|---|---|
| **SQLite** | Full suite executed. 8,245 passed, 0 failed |
| **MySQL / MariaDB** | **NOT EXECUTED — environment unavailable.** No `mysql`/`mysqld`/`mariadb` binary; port 3306 closed. `pdo_mysql` loaded, no server. **No MySQL result is claimed** |
| **Schema** | No change |
| **Data** | Untouched. The M14 test removes every row it inserts |
