# UX-B9 — Dashboards, area homes & information priority · AUDIT ONLY

## 1 · Metadata

| | |
|---|---|
| Commit audited | `598078ffd9b4356f1d8eacaff22683cb2a0a6255` |
| Branch | `claude/testing-branch-setup-0gqe8n` |
| Working tree | clean before and after |
| Date | 24 Sep 2026 |
| Engine | MariaDB, workspace `rqv_ui` (seeded UI-test data) |
| Browser | Chromium via the existing harness |
| Widths | 1280×900 · 360×800 · 390×844 · 412×915 (touch emulated) |
| Roles measured | super-admin, OPERATION_MANAGER, COORDINATOR, SR_INSPECTOR, INSPECTOR, FINANCE |
| Production code changed | **none** |
| Schema changed | **none** |
| Temporary probe data | 5 `b9probe_*` users in the throwaway `rqv_ui` workspace — **deleted** (verified 0 remaining) |
| Temporary instrumentation | a traced copy of the app outside the repo, on port 8809 — **deleted**, server stopped |

Every number below was reproduced against this commit. No figure was carried
forward from A1/A3 without re-measurement; where a carried finding survived it is
marked **reproduced**, where it did not it is marked **retired** with the reason.

---

## 2 · The home architecture, as it actually is

There are **14 reachable home-like destinations**, served by **5 distinct engines**:

```
/                 views/dashboard.php        ← the role-ranked KPI board
/command-centre   views/ops/command_centre.php  ← management state-of-business
/my-work          views/ops/my_work.php      ← the personal do-next queue
/recruitment      views/ops/recruitment_home.php
/operations       views/ops/operations_home.php  ← its own richer home
/sales /quality /money /reporting /insights /directory /admin /marketplace
                  views/ops/area_home.php    ← ONE generic engine, 8 areas
/crm-dashboard    views/ops/crm_dashboard.php
```

The 8 area homes are **one engine driven by one data source** (`ops_area_def()`
in `lib/areas.php`). That is not duplication and must not be "consolidated" —
it is a single template rendered eight times.

---

## 3 · Headline finding — `/my-work` is broken for every user

**B9-F1 · SEVERITY: CRITICAL · reproduced on this commit · NOT a UX issue**

`/my-work` — the personal do-next queue, linked from the left rail, from the
"Your pending tasks" panel on `/` and `/operations`, and from the "Your next
actions" strip on all 8 area homes — **does not work for anybody**.

### Evidence

| User | display name | `/my-work` result |
|---|---|---|
| `admin` | *(blank → falls back to username "admin")* | HTTP 200, renders **the Admin area home** |
| Zoya Kapoor (COORDINATOR) | Zoya Kapoor | **HTTP 500** — "This screen could not be loaded · `Zoya Kapoor`" |
| Imran Shaikh (SR_INSPECTOR) | Imran Shaikh | **HTTP 500** |
| Neha Rao (INSPECTOR) | Neha Rao | **HTTP 500** |
| Farah Mistry (FINANCE) | Farah Mistry | **HTTP 500** |
| Vikram Sen (OPERATION_MANAGER) | Vikram Sen | **HTTP 500** |

Same result at 360 / 390 / 412 px. Confirmed both in the browser and with `curl`
straight at the server, so it is not a probe artefact.

### Root cause

`view()` extracts the caller's variables **over its own parameter**:

```php
// index.php:747
function view($name, $vars = []) {
    extract($vars);                       // ← $vars['name'] overwrites $name
    …
    $viewFile = __DIR__ . "/views/$name.php";   // ← now the USER'S NAME
```

`ops_my_work()` is the one caller in the codebase that passes a `name` key:

```php
// lib/ops.php:8152
view('ops/my_work', [ …, 'name' => function_exists('user_name') ? user_name($u) : '' ]);
```

So `$viewFile` becomes `views/<the signed-in person's name>.php`:

* "Zoya Kapoor" → `views/Zoya Kapoor.php` → missing → the contained 500 panel.
* "admin" → `views/admin.php` → that file **exists** (a defensive fallback added
  for stale deployments) and renders the **Admin area home**. That is why the
  one account most likely to be used for testing silently shows a wrong-but-
  plausible page instead of an error.

Traced on an instrumented copy: `ops_my_work()` **is** entered, `view('ops/my_work')`
**is** called exactly once, and `ops_area_home()` is **never** called — the
substitution happens inside `view()`.

### Blast radius

A scan of every `view(` call site in the application (excluding tests):
**exactly one** passes a `name` key — `ops_my_work()`. So the damage is one
screen. The latent trap in `view()` remains for any future caller.

### Why no test caught it

`tests/test_my_work.php` does not call the real `view()`. It re-implements the
extract-and-include locally:

```php
// tests/test_my_work.php:67
return (function () use ($vars) { extract($vars); ob_start();
    include __DIR__ . '/../views/ops/my_work.php'; return ob_get_clean(); })();
```

In that private closure there is no `$name` parameter to clobber, so the test
renders the page perfectly while production 500s. The test exercises the
template but not the seam that is broken.

### Age

Introduced in `b963490` (27 Aug 2026), an ancestor of HEAD. `/my-work` has been
broken for every non-"admin" user for **four weeks**, through B3–B8.

**This is a production defect, not a UX-polish item.** B9 is audit-only, so it
has not been touched. It needs its own authorisation.

---

## 4 · Carried findings, re-measured

| Ref | Original claim | Verdict on this commit |
|---|---|---|
| F-A3-1 | "8 area homes, 103 tiles, **0** carry a count" | **Half reproduced.** 8 area homes and **exactly 103 tiles** reproduced. "0 counts" was measured on an **empty database** — re-running the same census on an empty DB gives 103 tiles / 0 counts, on seeded data **24 of 103** tiles render a count badge. B5 did its job; the original number was an artefact of the fixture, not a defect. |
| F-A3-2 | "3 dashboards and 8 flat link menus both called home" | **Reproduced, and understated.** 14 home-like destinations, 5 engines. |
| F-A3-3 | "Recruitment CC — 2,643 px, 20 headings, 43 links" | **Retired.** Now **1,843 px, 4 headings (Today / Risks / Opportunities), 65 links.** B2 restructured it; the page is shorter and better-grouped, but carries *more* links. |
| F-A3-4 | "Dashboard — 30 cards, unranked; quick-actions band effectively absent (1 create link)" | **Split.** "Unranked" is **retired** — see §5. "1 create link" is **reproduced exactly**: of 31 quick cards, precisely one (`/raise-call`) creates anything. Card count today is **47** (16 KPI + 31 quick), not 30. |
| UX-A1 | "Operations home — 211 lines, 19 cards, 7 destinations" | **Retired.** Still 211 source lines, but renders **29 tiles** and is by far the largest page in the app — see §6. |

---

## 5 · The Dashboard is already ranked — do not build a ranking engine

`views/dashboard.php` has a **5-branch role-based ordering engine** (lines
480–493). A fixed spine runs first for everyone:

```
KPI cards → Your pending tasks → Compliance → Needs attention
```

then the tail is reordered by role: executive, sales/marketing,
money-first, schedule-first, and a default. This is real, working prioritisation.

**B9-F2 · SEVERITY: MEDIUM.** Three sections are echoed *after* the ordering
block and therefore ignore it entirely — "Recruitment placement fees",
"Open manpower requisitions", "Agency contracts renewing soon". They are always
last, for every role, including the roles that own them. Recruitment work sits
below the charts for a recruitment manager.

Measured, as super-admin on seeded data: **16 sections, 16 KPI cards, 31 quick
cards, 25 distinct destinations (6 duplicated), 4,589 px desktop.**
The long lists are responsibly sliced (`array_slice($openReqs, 0, 9)` renders 9
of 165), so length is not a runaway-data problem.

---

## 6 · Information priority — the real problem is length, not clutter

| Home | desktop | 360 px | screens at 360 | tiles |
|---|---|---|---|---|
| **Operations** | 12,480 px | **57,082 px** | **71.4** | 29 |
| Dashboard `/` | 4,589 px | 9,906 px | 12.4 | 47 |
| CRM dashboard | 1,650 px | 3,669 px | 4.6 | 0 |
| Recruitment | 1,843 px | 3,710 px | 3.9 | 6 |
| Command Centre | 1,151 px | 2,061 px | 2.6 | 0 |
| Marketplace | 1,022 px | 2,012 px | 2.5 | 14 |
| Sales | 900 px | 1,526 px | 1.9 | 10 |
| Quality | 900 px | 1,323 px | 1.7 | 23 |
| Directory | 900 px | 1,206 px | 1.5 | 8 |
| Money | 900 px | 1,078 px | 1.3 | 11 |
| Insights | 900 px | 984 px | 1.2 | 4 |
| Reporting | 900 px | 961 px | 1.2 | 8 |
| Admin | 900 px | 945 px | 1.2 | 26 |

**B9-F3 · SEVERITY: HIGH.** The Operations home is **71 phone screens** long.
It carries four register tables (Backlog, Schedule register, Assignment register,
Data-quality flags) inline on a landing page. Every other home is under 5 screens.
This is one page, not a systemic problem — and the tabs engine it already uses
(4 tabs) is the obvious lever.

**Horizontal scrolling: none, at any width, on any home.** B7 holds.

The eight area homes are *not* cluttered: 1.2–1.9 screens each. A 26-tile Admin
home fits in 1.2 phone screens because the tabs engine hides the rest. Tile count
is not the measure; rendered length is.

---

## 7 · Role matrix — what each role actually gets

Tiles / count-badges, measured per role on seeded data:

| Home | super-admin | OPS_MGR | COORDINATOR | SR_INSPECTOR | INSPECTOR | FINANCE |
|---|---|---|---|---|---|---|
| Dashboard | 47 | 28 | 30 | 8 | 7 | 21 |
| Command Centre | ✓ | 0 tiles | 0 tiles | → `/` | → `/` | 0 tiles |
| **My Work** | **wrong page** | **500** | **500** | **500** | **500** | **500** |
| Recruitment | 6 | 6 | 6 | 403 | 403 | 403 |
| Operations | 29 / 7 | 29 / 7 | 29 / 7 | → `/` | → `/` | 27 / 7 |
| Sales | 10 / 3 | **1** | **1** | → `/` | → `/` | 4 / 1 |
| Quality | 23 / 12 | 9 / 7 | 9 / 7 | 3 / 3 | 3 / 3 | 3 / 3 |
| Money | 11 / 4 | 4 / 1 | 3 / 1 | → `/` | → `/` | 9 / 4 |
| Reporting | 8 / 1 | 7 / 1 | 7 / 0 | 7 / 1 | 7 / 0 | 6 / 0 |
| Insights | 4 | 3 | 3 | → `/` | → `/` | 4 |
| Directory | 8 / 1 | 8 / 1 | 7 / 1 | → `/` | → `/` | 1 |
| Admin | 26 | **1** | **1** | → `/` | → `/` | → `/` |
| Marketplace | 14 / 3 | 12 / 2 | 12 / 2 | → `/` | → `/` | → `/` |
| CRM dashboard | ✓ | 403 | 403 | 403 | 403 | ✓ |

`→ /` = silently redirected to the dashboard.

**B9-F4 · SEVERITY: MEDIUM.** Three different refusal styles for the same class
of event: a **403 page** (`/recruitment`, `/crm-dashboard`), a **silent redirect
to `/`** (8 destinations for inspectors), and an in-page **"you do not have
access"**. An inspector who taps "Money" in the rail is returned to the home
screen with no explanation. (This extends the refusal-style item already deferred
from B7/B8 — now with home-level evidence.)

**B9-F5 · SEVERITY: LOW.** A one-tile area home. Sales and Admin render a single
tile for OPERATION_MANAGER and COORDINATOR. A landing page whose whole content is
one link is a redirect wearing a costume. Not a defect — a product question.

**Not a finding:** inspectors see a 7–8 tile dashboard and little else. That is
the permission matrix working, and their phone-first surface is deliberately thin.

---

## 8 · The attention system is built three times and lands in the wrong places

Two engines — `ops_pending_tasks()` and `action_centre()` — feed three surfaces:

| Surface | Engine | Shown on |
|---|---|---|
| "Your pending tasks" (unranked counts) | `ops_pending_tasks()` | `/`, `/operations`, `/reports` |
| "Your next actions" (ranked, top 4) | `dashboard_glance()` → `action_centre(4)` | **only** the 8 area homes |
| Full prioritised queue (top 10) | `action_centre(10)` | **only** `/my-work` |

**B9-F6 · SEVERITY: HIGH.** The logic is not duplicated — placement is inverted.
The **ranked** view appears only on menu pages people pass through, the
**unranked counts** appear on the page people land on, and the **full ranked
queue** is on the one screen that returns 500. The Next Action system built in
B3 is, in practice, invisible to every user on the page they actually start from.

`/command-centre` and `/recruitment` carry neither.

---

## 9 · Performance

Server-side TTFB, MariaDB, seeded data:

| Route | TTFB | bytes |
|---|---|---|
| `/command-centre` | **1.47 s** | 65 KB |
| `/quality` | 0.98 s | 64 KB |
| `/` | 0.50 s | 83 KB |
| `/operations` | 0.42 s | **151 KB** |
| `/recruitment` | 0.41 s | 71 KB |

**B9-F7 · SEVERITY: LOW.** `/command-centre` is 3× the cost of the dashboard for
a page with 3 sections and no tiles. Worth a look, not urgent. (One browser run
recorded a >30 s stall here; a direct server measurement did not reproduce it, so
it is recorded as unexplained, not as a defect.)

---

## 10 · Findings summary

| Ref | Severity | Finding |
|---|---|---|
| B9-F1 | **CRITICAL** | `/my-work` returns 500 for every named user; `admin` gets the Admin area home. `view()`'s `extract($vars)` clobbers its own `$name`. Production defect, 4 weeks old, one call site, untested seam. |
| B9-F3 | HIGH | Operations home is 57,082 px — 71 phone screens. |
| B9-F6 | HIGH | Ranked next-actions only on area homes; unranked counts on the dashboard; full queue only on the broken page. |
| B9-F2 | MEDIUM | Three dashboard sections bypass the role-ordering engine and are always last. |
| B9-F4 | MEDIUM | Three refusal styles; 8 silent redirects for inspectors. |
| B9-F7 | LOW | `/command-centre` TTFB 1.47 s. |
| B9-F5 | LOW | One-tile area homes for some roles. |

**Retired as false on this commit:** F-A3-3 (Recruitment CC metrics),
F-A3-4's "unranked", UX-A1's "19 cards", and F-A3-1's "0 counts" (fixture artefact).

**Reproduced exactly:** 103 tiles across 8 area homes; 1 create link on the dashboard.

---

## 11 · What B9 implementation must NOT do

* Do not build a second KPI, dashboard, counter, attention or role-routing engine.
  All of them exist and work. B9-F6 is a **placement** problem.
* Do not consolidate the 8 area homes. They are already one template.
* Do not treat tile count as clutter. Admin has 26 tiles in 1.2 phone screens.
* Do not fix B9-F1 as part of a UX change. It is a production defect in `view()`
  and deserves its own authorisation, its own test against the **real** `view()`,
  and a decision about whether `views/admin.php` should keep masking it.

---

## 12 · Audit hygiene

* No application code, schema, route, permission, CSS or query was modified.
* 5 `b9probe_*` users created in the throwaway `rqv_ui` workspace, **deleted**
  (verified: 0 rows remaining).
* One temporary census test file was run and **deleted**; `git status` clean.
* The instrumented copy of the app lived outside the repository and is deleted.
* Working tree verified clean before and after.

**B9 = AUDIT ONLY. No implementation performed. B10 not started.**
