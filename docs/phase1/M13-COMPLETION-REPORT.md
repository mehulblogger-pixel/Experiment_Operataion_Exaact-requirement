# Milestone 13 — Completion Report
## Security Hardening, Tenant Isolation & Boundary Validation

**Verdict: PASS WITH DOCUMENTED LIMITATIONS**
**Suite: 8,199 passed, 0 failed** · M13 focused suite: 51 passed, 0 failed

---

### 1. Status

Complete. M13's brief was to attack the boundary M5–M12 built, and to change code
only where something actually broke. **Four things broke.** One was critical, and
it had three reachable paths to a single root cause. All four are fixed, proven
by tests that fail if the fix is removed.

### 2. What was attacked

Tenant isolation · session integrity · authentication · CSRF · object-level
authorisation · entitlement bypass · master privilege · error disclosure ·
secrets in the repository · entitlement cache isolation.

### 3. Security findings

| # | Severity | Finding | Status |
|---|---|---|---|
| **V1** | **CRITICAL** | A signed-in identity was not bound to the workspace that issued it. `current_user()` resolved `uid` against whatever database was live. Proven: **uid 1 resolved to Alice in one workspace and Bob in another** | **FIXED** |
| **V2** | **CRITICAL** | `/reset?w=<workspace>` took the workspace from the query string — one unauthenticated request could point a session at any workspace | **FIXED** |
| **V3** | **CRITICAL** | Login switches workspace *before* checking the password and did not switch back on failure | **FIXED** |
| **V4** | High | The call detail fetched by id with no office/branch check. The list hid other branches' calls; the detail served them. Proven with two offices | **FIXED** |
| **V5** | Medium | The fatal-error page showed exception text, file path and line to **any** signed-in member of staff | **FIXED** |

V2 and V3 were the two reachable ways to trigger V1. Together they were a full
**cross-tenant authentication bypass**: an account in any workspace could become
whoever holds the same user id in someone else's.

### 4. What was attacked and held

**CSRF — sound.** One *global* gate covers every staff POST rather than each
handler remembering; tokens auto-stamped; `hash_equals`; rejections audited;
portals have their own gates.
**Cross-tenant data — structurally strong.** One database per company; a record
id from one does not exist in another.
**Entitlement (M5–M12) — holds.** Under S-1 as a **master**: Operations and
Reporting work; HR, Sales, Money and Marketplace refused at the route gate *and*
at the gates behind it.
**Master privilege — holds.** M10's separation intact.
**Secret scan — clean.** Three matches triaged as false positives (a TOTP
`otpauth://` URI parameter name; two placeholder/help strings in the licence
tool). `config.local.php` and `tenants.php` are gitignored and absent. **No value
was printed. No credential rotation is required.**
**Error disclosure — clean** after V5: no SQL, path, table name or module key
reaches a user through a refusal.

### 5. Files changed — seven, all surgical

`lib/helpers.php` (root-cause fix) · `index.php` · `lib/security.php` ·
`lib/mghsso.php` · `lib/pwreset.php` · `lib/saas_tenants.php` · `lib/ops.php` ·
plus `deploy-check.php` regenerated and the new test file.

No architecture replaced. No second security mechanism created. **No schema
change. No data touched.** Entitlement enforcement from M2–M10 untouched.

### 6. Tests added

`phpapp/tests/test_m13_security_boundary.php` — 51 assertions covering workspace
binding (11), password reset (6), failed login (5), the chained exploit (3), call
scope (5), CSRF (10), entitlement under S-1 (6) and disclosure (5). Each fix was
reverted in isolation to confirm the tests fail without it. **No existing test
was weakened, skipped or deleted.**

### 7. Remaining limitations

**L1** legacy sessions carry no binding until next sign-in — bounded, and both
attack paths are independently closed · **L2** object-level scope checks not
exhaustive (72 list-level vs 9 object-level; the highest-value records covered) —
**a candidate for M14** · **L3** MySQL not exercised · **L4** source-level audit,
no transport-layer testing · **L5** rate limiting covers sign-in only.

Full detail in `M13-KNOWN-LIMITATIONS.md`.

### 8. Environment

**MySQL / MariaDB: NOT EXECUTED.** No `mysql`/`mysqld`/`mariadb` binary; nothing
on 3306. `pdo_mysql` is loaded but there is no server. **No MySQL result is
claimed.** The changes add no SQL.

### 9. Critical or high-risk issues remaining

**None.** All three critical paths and the one high-risk IDOR were found, proven
and fixed inside M13. L2 is a named Medium open *surface* — not a demonstrated
vulnerability.
