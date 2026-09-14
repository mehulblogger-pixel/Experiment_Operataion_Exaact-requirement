# Milestone 13 — Security Test Matrix

`phpapp/tests/test_m13_security_boundary.php` — **51 assertions, 0 failed.**
Full suite: **8,199 passed, 0 failed** · 461 files · PHP 8.4.19.

Every attack below was executed as an attack **before** any code changed, and is
now kept as a permanent guard: if the fix is removed, the test fails.

---

## Attacks that succeeded, then were closed

| # | Attack | Before M13 | After M13 |
|---|---|---|---|
| **A1** | Present a valid session against a second workspace holding the same user id | **uid 1 = Alice in A, Bob in B** — cross-tenant identity | Refused — identity does not resolve outside the workspace that issued it |
| **A2** | `/reset?t=…&w=<other workspace>` while signed in | Session moved to the named workspace | Refused — a signed-in session is never moved |
| **A3** | Fail a login, then use the session | Left standing in the workspace of the failed attempt | Workspace released on failure |
| **A4** | A1 + A3 chained (the realistic exploit) | Full cross-tenant authentication bypass | Refused at both ends |
| **A5** | Fetch another branch's call by id | Detail served a record the list hid | Refused — office/branch scope, like the job detail |
| **A6** | Trigger a fatal as an ordinary member of staff | Exception text + file path + line | Reference code only; detail for administrators |

---

## Assertions by area

| Area | n | What is pinned |
|---|---:|---|
| **Workspace binding (V1)** | 11 | The binding helpers exist; `current_user()` refuses a foreign workspace; the compare is present; it is stamped on sign-in, on two-factor, on single sign-on and on the operator's "open this company"; the legacy-session gap is bounded and deliberate |
| **Password reset (V2)** | 6 | The refusal is at the *entry* function, before any workspace switch; a signed-in session is untouched; a signed-out reset still works (the fix does not break the feature) |
| **Failed login (V3)** | 5 | The workspace is released before **both** refusal replies — wrong password and rate-limited — and before the reply, not after |
| **Chained V1+V3** | 3 | The realistic exploit path is closed at both ends independently |
| **Call scope (V4)** | 5 | The detail carries `scope_allows()`; it matches the job detail's shape; refusal is `ops_require`, so it is a real 403, not a hidden link |
| **CSRF** | 10 | Correct / missing / invalid / null token; no session; `hash_equals` (timing-safe); the **global** staff gate; auto-stamping; portal gates |
| **Entitlement under S-1** | 6 | As a **master** with Operations+Reporting only: Operations and Reporting open; HR, Sales, Money and Marketplace are refused at the route gate and at the gates behind it |
| **Error disclosure** | 5 | A refusal never contains `SQLSTATE`, `SELECT `, `users`, `sqlite`, `mysql`, `/home/`, or `.php` |

---

## Mutation testing — the tests are load-bearing

Each fix was reverted in isolation and the suite re-run:

| Fix removed | Test result |
|---|---|
| Workspace compare in `current_user()` | **FAIL** (5 assertions) |
| `auth_bind_workspace()` at sign-in | **FAIL** |
| Reset-page refusal | **FAIL** (3 assertions) |
| `saas_leave_tenant()` on failed login | **FAIL** (4 assertions) |
| `scope_allows()` on the call detail | **FAIL** (3 assertions) |
| Administrator check on the fatal page | **FAIL** |

No test passes for a reason other than the fix being present.

---

## Environment — stated plainly

| | |
|---|---|
| **SQLite** | Full suite executed. 8,199 passed, 0 failed |
| **MySQL / MariaDB** | **NOT EXECUTED.** No `mysql`, `mysqld` or `mariadb` binary in this container; nothing listening on 3306. `pdo_mysql` is loaded but there is no server to connect to. **No MySQL result is claimed.** The M13 changes are pure PHP — session handling, one `WHERE`-free scope check, one display condition — and add no SQL, so nothing in them is engine-specific; that is reasoning, not a test result |
| **Schema** | **No change.** No migration, no new column, no new table |
| **Data** | Untouched |
