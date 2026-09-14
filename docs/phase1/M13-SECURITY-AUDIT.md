# Milestone 13 — Security Audit

**Status:** complete · **Suite:** 8,199 passed, 0 failed · **Baseline:** 65c7f47 (M12)

M5–M10 built the entitlement boundary. M11–M12 made it understandable. **M13
attacked it** and changed code only where something actually broke.

Four things did. One of them was **critical**.

---

## 1. V1 — CRITICAL — a signed-in identity was not bound to its workspace

### What was wrong

Every company has its own database, and which one is live is resolved per
request. `current_user()` then did this:

```php
$q = db()->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
$q->execute([$_SESSION['uid']]);
```

— against **whatever database happened to be live**, with nothing tying that
`uid` to the workspace it was issued in.

### Proven, not assumed

Two databases were built, each with a **user id 1**: Alice in one, Bob in the
other. The same session resolved to **Alice** on one connection and **Bob** on
the other. The session carried exactly one relevant key — `uid` — and no
workspace binding existed anywhere.

That turned **any** path able to switch the live workspace into a cross-tenant
authentication bypass. An attacker with an account in any workspace could become
whoever holds the same user id in someone else's.

### Two reachable paths to it

**V2 — the public password-reset page.** `lib/pwreset.php:163` read the workspace
key straight from the query string:

```php
$wkey = (string) ($_GET['w'] ?? ($_POST['w'] ?? ''));
if ($wkey !== '') pwreset_enter_workspace_key($wkey);
```

So `/reset?t=anything&w=<workspace>` let **one unauthenticated request** point a
session at any workspace it could name — and workspace names are subdomains, so
effectively public.

**V3 — signing in.** The login switches the live database *before* checking the
password, because the password has to be verified against that workspace's own
users table. On a **wrong password** it did not switch back — leaving the session
standing in a workspace the person had just failed to enter.

### How it was closed

**At the root.** The session now records which workspace issued the identity, and
`current_user()` refuses to resolve it anywhere else. Any future code that
switches workspace with a stale session now fails closed **by default** rather
than by remembering to.

Only server-side code that has already authorised the switch stamps the binding —
signing in, completing two-factor, single sign-on, and the platform owner's
deliberate "open this company". An attacker-driven switch can never carry an
identity with it.

**And at both sites.** The reset page refuses to move a session that is already
signed in (resetting a password is something you do when you are *not* signed
in). A failed login steps back out of the workspace it had switched into.

**Deliberate, documented gap:** a session created *before* this change carries no
binding and is not broken by it — it acquires one at the next sign-in. Both
reachable paths are closed independently, so an unbound legacy session cannot be
moved by either. It is a bounded gap that closes as sessions expire.

---

## 2. V4 — within-tenant IDOR on the call detail

### What was wrong

The call **list** is office/branch-scoped through `scope_clause()`. The call
**detail** fetched by id with no scope check at all — while the job detail two
thousand lines below had carried exactly that guard since Phase 2.

### Proven

Two branches, one coordinator scoped to Branch A, one call belonging to Branch B:

```
calls visible in the LIST : (none)
detail fetch by id        : RETURNED call CALL-B-001 (office 2)
VERDICT: *** IDOR — the list hides it, the detail serves it ***
```

### How it was closed

The same one-line guard the job detail already uses. The list and the detail now
agree.

**The wider shape is recorded rather than swept up:** 72 list-level scope clauses
against 9 object-level checks. The object-level checks cover the highest-value
records — job, report (×3), endorsement file, invoice (×2), check-in photo — and
now the call. Auditing every remaining fetch-by-id is real work and is deferred
as a named limitation, not implied to be done.

---

## 3. V5 — the fatal page showed internals to any signed-in user

`ops_fatal()` prints the exception message, the **filesystem path** and the line
number. That message routinely carries SQL and table names.

An unauthenticated visitor correctly saw only a reference code — so the rule was
already right, it was just drawn in the wrong place: `!empty($_SESSION['uid'])`
meant **every member of staff**.

Now administrators only. Everyone else gets the reference to quote, which is what
they would relay anyway.

---

## 4. What was attacked and found sound

Reported because "we tried to break it and could not" is the point of this
milestone.

| Boundary | Result |
|---|---|
| **CSRF** | **Sound, and better than expected.** One *global* gate covers every staff POST (`index.php:1119`) rather than each handler remembering; tokens are auto-stamped into every POST form; the compare is `hash_equals`; rejections are audited; the two portals have their own gates. Missing, invalid, null and session-less tokens all refused |
| **Cross-tenant data** | Structurally strong — one database per company, so a record id from one simply does not exist in another. The risk was never the data layer; it was tenant resolution, which is V1 |
| **Tenant resolution** | The host name only resolves a **registered** workspace, and the session key is written by exactly two functions. After V1–V3, none of them can carry an identity |
| **Entitlement bypass** | M5–M12 hold. Under S-1 as a master: Operations and Reporting work; HR, Sales, Money and Marketplace are refused at the route gate *and* at the gates behind them |
| **Master privilege** | M10 holds — master is not exempt from entitlement |
| **Secret scan** | **Clean.** Three matches, all false positives on inspection: a TOTP `otpauth://` URI *parameter name*, and two placeholder/help strings in the licence tool. `config.local.php` and `tenants.php` are gitignored and absent from the tree. No value was printed at any point |
| **Error disclosure** | No SQL, path, table name or module key reaches a user through a refusal — asserted for seven leak patterns |
| **Cache isolation** | M6's epoch-keyed entitlement cache holds; the new workspace binding adds a second, independent barrier |

---

## 5. Changes made — five files, all surgical

| File | Change |
|---|---|
| `lib/helpers.php` | `auth_workspace_key()`, `auth_bind_workspace()`; `current_user()` refuses an identity outside the workspace that issued it |
| `index.php` | login stamps the binding; a **failed** login leaves the workspace; the fatal page shows detail to administrators only |
| `lib/security.php` | `complete_login()` stamps the binding |
| `lib/mghsso.php` | single sign-on stamps the binding |
| `lib/pwreset.php` | the public reset page will not move a signed-in session |
| `lib/saas_tenants.php` | "open this company" stamps the binding; the operator's scoped push carries and restores it |
| `lib/ops.php` | the call detail is office/branch-scoped, like the job detail |

No architecture was replaced. No second security mechanism was created. No
schema change. No data touched.
