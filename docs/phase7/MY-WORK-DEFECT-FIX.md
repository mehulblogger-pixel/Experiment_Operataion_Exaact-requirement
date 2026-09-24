# `/my-work` — view-name collision · DEFECT FIX

A release-safety correction found by the UX-B9 audit. It is a **functional defect
fix**, not part of the B9 UX implementation.

| | |
|---|---|
| Found by | UX-B9 audit (`c145a52`) |
| Defect present since | `b963490`, 27 Aug 2026 — four weeks |
| Severity | **Critical** — the screen was unusable for every user |
| Schema changes | **0** |
| Permission changes | **0** |
| Workflow changes | **0** |
| Route changes | **0** (`/my-work` unchanged) |
| Business-data changes | **0** |

---

## 1 · Root cause

`view($name, $vars)` ran `extract($vars)` over its own `$name` parameter — and
`$name` is the **view identifier**, the thing that decides which file to open:

```php
function view($name, $vars = []) {
    extract($vars);                          // writes the caller's vars into this scope
    …
    $viewFile = __DIR__ . "/views/$name.php";   // $name may no longer be the view
```

`ops_my_work()` was the one caller in the application that passed a key called
`name` — the signed-in person's display name:

```php
view('ops/my_work', [ …, 'name' => user_name($u) ]);
```

So the renderer went looking for `views/<the person's name>.php`:

| Display name | Resolved file | Result |
|---|---|---|
| `Zoya Kapoor` | `views/Zoya Kapoor.php` | missing → **HTTP 500** |
| `admin` | `views/admin.php` | **exists** → the **Admin area home** |
| `../index` | `views/../index.php` | **the front controller** → fatal redeclare |

The second row is why this survived a month: `views/admin.php` is a defensive
fallback for stale deployments, and it renders the Admin area home. The account
most likely to be used for testing therefore saw a wrong-but-plausible page
instead of an error.

The third row is worse than a broken screen. A display name could **traverse out
of `views/`** and make the application `require` an arbitrary `.php` file beneath
its own root. It was surfaced by mutation M1 during this work, not by the
original audit.

---

## 2 · Who was affected

Reproduced on this commit, in the browser and against the server directly:

| Role | Display name | Before |
|---|---|---|
| ADMIN (real account) | `admin` (username fallback) | Admin area home — **wrong page** |
| COORDINATOR | Zoya Kapoor | **HTTP 500** |
| SR_INSPECTOR | Imran Shaikh | **HTTP 500** |
| INSPECTOR | Neha Rao | **HTTP 500** |
| FINANCE | Farah Mistry | **HTTP 500** |
| OPERATION_MANAGER | Vikram Sen | **HTTP 500** |

`/my-work` is linked from the left rail, from the "Your pending tasks" panel on
`/` and `/operations`, and from the "Your next actions" strip on all eight area
homes — so this was a promoted destination that never worked.

---

## 3 · Why the old test did not catch it

`tests/test_my_work.php` never called the real `view()`. It re-implemented the
extract-and-include inside a private closure:

```php
return (function () use ($vars) { extract($vars); ob_start();
    include __DIR__ . '/../views/ops/my_work.php'; return ob_get_clean(); })();
```

That closure has **no `$name` parameter to collide with**, so it rendered a page
the production renderer could not produce. The test exercised the template but
not the seam that was broken.

`view()` lives in `index.php`, which cannot be required from a test because
requiring it runs the whole front controller. That is the structural reason the
imitation existed — and it is now solved properly (§5).

---

## 4 · The fix

Two parts. The first removes today's collision; the second makes the class of
defect impossible.

**1 · the caller stops using a reserved key** — `lib/ops.php`, `views/ops/my_work.php`

```php
-  'name'     => function_exists('user_name') ? user_name($u) : '',
+  // NB: never call this key 'name' — view() extract()s these vars over its own
+  // $name parameter, which is the view identifier.
+  'userName' => function_exists('user_name') ? user_name($u) : '',
```

**2 · the renderer's identifier can no longer be overwritten** — `index.php`

```php
 function view($name, $vars = []) {
+    $__view = (string)$name;      // captured BEFORE extract()
     extract($vars);
…
-    $viewFile = __DIR__ . "/views/$name.php";
+    $viewFile = __DIR__ . "/views/$__view.php";
```

`$name` is still extracted and still reaches templates, so **no caller and no
template changes behaviour** — only the file `view()` opens is now immune.

### Why the shared helper was touched (§6)

Blast radius established before changing it:

* **507** `view()` call sites; **all 507** pass a *literal* view identifier.
  **0** pass a computed one (tokenised check, not text matching).
* **1** call site passed a `name` key — `ops_my_work()`. Confirmed twice:
  statically (tokeniser over every application file) and at runtime (a temporary
  detector inside `view()` while crawling **400 routes** — one hit,
  `ops/my_work`).
* Every template that uses `$name` **assigns it itself** (`$name = …`,
  `foreach … as $name`, list-destructuring). Only `my_work.php` read it from the
  caller. So nothing depended on `$name` holding the view identifier.

The change is therefore inert for every caller except the one that was broken.
Part 1 alone would have fixed My Work; part 2 is what makes the guarantee —
*a person's name can never become the renderer's view identifier* — structural
rather than a convention somebody must remember.

---

## 5 · Regression coverage

**The tests now run the production `view()`, in a separate process.**

`tests/_my_work_view_worker.php` takes the **genuine bytes** of `view()` out of
`index.php` and loads them from a file in the application root, so `__DIR__`
resolves exactly as it does in production. A separate process is required
because three other test files (`module38`, `module46`, `module49`) install a
capturing **stub** `view()`; whichever runs first owns the global function name,
so an in-process render could silently go through a stub — the very mistake that
hid this defect.

The worker then **fingerprints, by reflection, whichever function it actually
loaded**, and the test asserts that fingerprint equals `index.php`'s. If the
harness ever regresses to a hand-written imitation, that assertion fails.

`tests/test_my_work.php` was corrected to render through the same worker rather
than its own copy of the extraction.

### Coverage

* Every role renders through `ops_my_work()` → `view()` → template: ADMIN,
  COORDINATOR, SR_INSPECTOR, INSPECTOR, FINANCE, OPERATION_MANAGER.
* Each asserts it is **My Work** (`<h1>My Work</h1>` + crumbs), that it is **not**
  the Admin area home, that the failure panel is absent, and that the person's
  own name appears in the subtitle.
* A user called **`admin`**, and a user with no name at all (username fallback) —
  the case that masked the defect.
* 16 awkward display names: existing view names (`admin`, `dashboard`, `list`,
  `form`, `notfound`, `login`, `detail`), spaces, mixed case, punctuation
  (`O'Brien`, `Anne-Marie Smith`), digits (`R2 D2`), and traversal
  (`ops/my_work`, `../index`, `../config`).
* A guard that no application call site passes `view()` a key called `name`,
  tokenised so a **comment** mentioning `['name' => …]` is not flagged — there is
  one such comment, inside `view()` itself.
* The scanner is proved non-vacuous against a synthetic offender.

### Mutation testing — 6/6 killed

| | Mutation | Result |
|---|---|---|
| M1 | Restore the whole historical defect | **KILLED** (43 failures) |
| M2 | Make My Work render the Admin screen | **KILLED** (34) |
| M3 | Revert only the renderer hardening | **KILLED** (3) |
| M4 | Replace the real `view()` with an imitation | **KILLED** (1) |
| M5 | Stop the display name reaching the template | **KILLED** (6) |
| M6 | Revert only the caller's key | **KILLED** (8) |

M4 **survived the first attempt** — the harness had no way to tell the real
renderer from a copy. The reflection fingerprint was added for exactly that, and
M4 then failed as it should.

---

## 6 · Verification

| Check | Result |
|---|---|
| SQLite, full suite | **14030 passed / 0 failed** |
| MariaDB, full suite (authoritative) | **14030 passed / 0 failed** |
| Mutation battery | **6 / 6 killed** |
| Browser + mobile, `/my-work`, 7 logins × 4 widths | **28 / 28 pass** |
| JS errors | **0** |
| Application failed requests | **0** |
| Unexpected redirects | **0** |
| Horizontal scrolling | none at 360 / 390 / 412 |
| 400-route crawl, before vs after | identical: 393×200, 1×403, **0×500** |
| Per-module render identity | Operations, Recruitment, Workforce, Marketplace, Reporting, Money, Invoicing, Quality, Admin, Directory each render their own screen |

The browser run records 56 HTTP 404s for a nonsense all-`A` path. These are
**Chromium's own soft-404 probes** (its NavigationCorrectionService — the same
browser reaches for `www.google.com` against a plain static server). The string
appears nowhere in the application, the service worker does not request it, and
it occurs on pages this change never touched. They are counted separately and are
not application requests.

The MariaDB suite drops every table, so it was run against a dedicated
`rqv_regress` database rather than `rqv_ui`, leaving the browser fixture intact.
`rqv_regress` was dropped afterwards.

### A cross-engine bug in the new test, caught by MariaDB

The first MariaDB run aborted: the new test's cleanup used
`LIKE 'vnc\_%' ESCAPE '\'`, which SQLite accepts and MariaDB rejects. Changed to
`LIKE 'vnc%'`, which is engine-neutral. Worth recording — it is exactly the kind
of thing that running only SQLite would have shipped.

---

## 7 · Data safety

* Schema changes: **0**. Permission changes: **0**. Workflow changes: **0**.
  Business-data changes: **0**. Route changes: **0**.
* In-suite probe users are created in the throwaway test database and deleted by
  the worker as it goes; the test asserts **0 remain**.
* Browser probe users (`b9probe_*`) were created in the throwaway `rqv_ui`
  workspace and **deleted** — verified 0 remaining.
* The temporary `rqv_regress` database was dropped.
* The temporary collision detector inside `view()` was reverted before any real
  change was made.
* `.viewprobe_*.php` files are removed immediately after loading, on shutdown,
  and swept before each load in case a hard-killed run left one behind.

---

## 8 · Files changed

| File | Change |
|---|---|
| `phpapp/index.php` | `view()` captures the view identifier before `extract()` |
| `phpapp/lib/ops.php` | `ops_my_work()` passes `userName`, not `name` |
| `phpapp/views/ops/my_work.php` | reads `$userName` |
| `phpapp/tests/_my_work_view_worker.php` | **new** — renders through the production `view()` in a clean process, and fingerprints it |
| `phpapp/tests/test_my_work_view_name.php` | **new** — the defect regression |
| `phpapp/tests/test_my_work.php` | corrected: renders through the real `view()` instead of a local imitation |
| `phpapp/deploy-check.php` | regenerated |

---

## 9 · Not done here

This fix does not touch My Work's lanes, ranking, filters, ownership, counts,
permissions, navigation or terminology, and starts no part of the B9 UX work.
The B9 findings — the Operations home's length, where the ranked next-actions
strip belongs, the dashboard sections that bypass role ordering, the silent
inspector redirects — remain open and unauthorised.
