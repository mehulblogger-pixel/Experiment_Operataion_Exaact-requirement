# UX-B9 — Dashboard, area home & information priority · IMPLEMENTATION

| | |
|---|---|
| Audit | `c145a52` (accepted / locked) |
| My Work defect fix | `6dae7c4` (accepted / locked, untouched here) |
| Scope | B9-1 … B9-4 only |
| Schema changes | **0** |
| Business-data changes | **0** |
| Workflow changes | **0** |
| Permission changes | **0** |
| KPI logic changes | **0** |
| New queries | **0** — B9 adds no SQL of its own |
| Engines created | **none** |

The audit's conclusion held: the intelligence already existed. Every change below
moves existing output or defers it; none of it computes anything new.

---

## B9-1 · Operations home density

### What was actually wrong

The audit said 57,082px. The cause was narrower than "the page is long":

| | at 360px |
|---|---|
| **"Backlog & registers" tab** | **46,666px — 82% of the page** |
| Pending scheduling (31 cards) | 5,362px |
| Cross-office block | 2,274px |
| Everything else | ~3,000px |

The tabs engine was already working (the other three panes measured 0px,
`display:none`). All of the length was in the *default* tab, which stacks four
registers — and the data layer already caps them at 60/60/40 rows. The length is
purely presentational: on a phone the B7 responsive-table engine renders each row
as a card, 79–262px tall, so 60 rows becomes 14,000px.

### Change

Each register shows its first 8 rows inline and keeps the rest in the same
`<details class="fold">` B6 uses in 46 other views. The pending-scheduling grid
does the same. No new mechanism, no query change, no row removed.

### Result

| | before | after | |
|---|---|---|---|
| **360px** | 57,082px (71.4 screens) | **14,657px (18.3 screens)** | −74% |
| **390px** | 56,028px | 14,394px | −74% |
| **412px** | 54,222px | 13,530px | −75% |
| **desktop** | 12,480px (13.9 screens) | **5,535px (6.2 screens)** | −56% |
| registers pane (360) | 46,666px | 7,870px | −83% |
| pending scheduling (360) | 5,362px | 1,334px + fold | −75% |

Desktop improved too, so §9 is satisfied rather than traded away.

### First viewport at 360px (§8)

| | before | after |
|---|---|---|
| What area am I in? | `<h1>Operations</h1>` at 140px | unchanged, 140px |
| What can I start? | "＋ New call" at ~180px | unchanged, ~180px |
| What needs attention? | "Your pending tasks" heading at 346px, its content running to 1,148px; the KPI row off-screen | **"Your next actions" — the ranked list — fully visible, 344→631px** |

### Everything is still reachable (§7)

Nothing was deleted. The focused tests render the *real* partial with 20 backlog
rows and assert all 20 are present (8 inline, 12 behind one fold), that the fold
says how many it holds, and that a register under the cap grows no fold at all.
The `#backlog`, `#schedule` and `#assignments` anchors still exist, so
`/ops-desk → /operations#backlog` still lands where it always did.

---

## B9-2 · Next-action placement

### The problem, restated

| Surface | Engine | Was shown on |
|---|---|---|
| "Your next actions" (**ranked**, top 4) | `dashboard_glance()` → `action_centre(4)` | the 8 area homes **only** |
| "Your pending tasks" (**unranked** counts) | `ops_pending_tasks()` | `/`, `/operations`, `/reports` |
| Full ranked queue (top 10) | `action_centre(10)` | `/my-work` only |

The ranked view was on pages people pass through; the unranked counts were on the
page they land on.

### Change

The strip's markup moved, verbatim, into `views/ops/_glance_strip.php`. The area
homes now include it (identical output), and so do the Dashboard and the
Operations home — above the unranked counts. There is one strip, one engine, and
no second ordering anywhere.

`/` and `/operations` take the ranked **actions only**, not the management pulse.
That is deliberate and measured: the pulse's `system_status_worst()` bcrypt-tests
every account that has never had its password changed through the app, which on
this 191-account workspace is 30s+ whenever its cache fingerprint moves. Those two
pages already carry their own KPI row, and the pulse belongs to `/command-centre`.
The area homes keep the full strip exactly as before.

### Proof it is not a second engine

The focused tests assert the partial contains no `usort`/`uasort`/`sort`/`rsort`/
`array_multisort`, never assigns to `$gsGlance['actions']`, and takes its items
from `action_centre`/`dashboard_glance`. Mutation M2 — sorting the list
alphabetically after the engine returns it — is caught.

---

## B9-3 · The three sections join the existing ordering

`views/dashboard.php` has a five-branch role ordering (`$isExec`, sales/marketing,
`$moneyFirst`, `$schedFirst`, default) over buffered `$secXxx` sections. Placement
fees, open manpower requisitions and agency renewals were echoed *after* it, so
they were last for everyone.

They are now captured as `$secRecruit` and take part in the same sequence. No
second sort, no numeric priorities, no "recruitment first" rule.

One wrinkle, handled honestly: the block's queries include a write
(`confirm_lapsed_placement_fees()`), so moving the block earlier could have
shifted a figure. Instead the block stays exactly where it runs today and only
its **output** is deferred: the ordered page is buffered with a one-off random
slot token where recruitment belongs for that role, and the section is dropped
into the slot. Execution order is byte-for-byte what it was.

### Measured result (seeded data)

| Role | Recruitment's position among the dashboard's sections |
|---|---|
| super-admin (exec) | **12–14 of 15** — now above "Pending scheduling" (was last) |
| Operations manager | **6–8 of 10** — above Job status and Pending scheduling |
| Finance | **absent** — no hiring permission, correctly not forced in |

First for nobody, which is what §18 asked.

---

## B9-4 · Inspector navigation

`ops_require()` flashes and redirects to `/`. That is right for a deep action
route, and it is used at **490** call sites, so it was not touched. The home
*destinations* — the 8 area homes, the Operations home and the Command Centre —
now render an outcome instead.

### Before / after, measured as an INSPECTOR

| Destination | Before | After |
|---|---|---|
| `/operations` | redirect → `/` | **403**, stays, "This area isn’t available to your role." |
| `/sales` | redirect → `/` | **403**, stays, explained |
| `/money` | redirect → `/` | **403**, stays, explained |
| `/insights` | redirect → `/` | **403**, stays, explained |
| `/directory` | redirect → `/` | **403**, stays, explained |
| `/admin` | redirect → `/` | **403**, stays, explained |
| `/marketplace` | redirect → `/` | **403**, stays, explained |
| `/command-centre` | redirect → `/` | **403**, stays, "This board isn’t available to your role." |
| `/quality` | 200 | **200 — unchanged** |
| `/reporting` | 200 | **200 — unchanged** |
| `/recruitment`, `/crm-dashboard` | existing 403 | **unchanged** |

Each notice names the area, explains in one sentence, and offers two destinations
the person can always open (`/` and `/my-work`).

### Cases kept apart (§22)

* **No permission** → "This area isn’t available to your role."
* **Module not enabled** → "This area is not switched on for your organisation."
  (`ops_area_licence_ok()`, a distinction the application already models.)
* **Invalid / deleted record** → untouched; existing not-found behaviour.
* **Valid and permitted** → opens normally.

### No bypass, no leak (§20, §23)

The gate predicates are unchanged — `ops_area_has()`, the Operations
`can('mod.calls.view') || can('mod.jobs.view') || …`, the Command Centre
`can('dash.operations') || can('dash.financial') || is_admin_level()`. The tests
impersonate an inspector and assert all six checked areas are still refused and
that Admin still yields zero tiles. The notice contains no permission name, no
capability key, no count, no record identifier and no SQL.

---

## Testing

| | |
|---|---|
| Focused B9 tests | **57 / 57** (`tests/test_b9_placement.php`) |
| Mutations | **6 / 6 killed** |
| SQLite full suite | **14,088 passed / 0 failed** |
| MariaDB full suite (authoritative) | **14,088 passed / 0 failed** |
| Browser + mobile | **140 / 140** (5 roles × 7 routes × desktop/360/390/412) |
| JS errors | **0** |
| Application failed requests | **0** |
| Horizontal scrolling | **0** at every width |
| Fold summaries under 44px | **0** |
| Tenant isolation | **PASS** — two isolated tenant databases |

### Mutations

| | Mutation | Result |
|---|---|---|
| M1 | Un-cap the Operations home registers and pending list | **KILLED** |
| M2 | Re-sort the ranked actions after the engine returns them | **KILLED** |
| M3 | Drop recruitment out of one role branch | **KILLED** |
| M4 | Restore an inspector's silent redirect | **KILLED** |
| M5 | Let an inspector through a protected area | **KILLED** |
| M6 | Remove the explanatory message from the notice | **KILLED** |

M5 and M6 **survived the first battery** — both are behavioural and the tests
were source-level only. Two behavioural tests were added: one impersonates an
inspector and asserts six areas are still refused, the other renders the notice
and asserts it says what it was given. Both mutations then failed.

### Tenant isolation

Two databases (`b9_tenantA`, `b9_tenantB`), one distinctive overdue task each.
Reading `action_centre()`, `dashboard_glance()`, `attention_summary()` and
`ops_pending_tasks()` — precisely the surfaces B9 moved — as an admin of each:

```
b9_tenantA: SECRET-A present: YES     SECRET-B present: no
b9_tenantB: SECRET-A present: no      SECRET-B present: YES
```

Both databases were dropped afterwards.

### One existing test was updated, not weakened

`tests/test_p3_dashboard_glance.php` asserted the strip's markup was a substring
of `views/ops/area_home.php`. B9-2 moved that markup into the partial the file now
includes, so the assertions follow the include: the test now also asserts the area
home *pulls in* the shared strip, then checks the composed source. The behavioural
half of that test was not touched and still passes unchanged.

---

## Two defects found and fixed during implementation

**A shared include clobbered its host page's variables.** `_glance_strip.php`
assigned `$c` for a dot colour; `operations_home.php` holds its counts in `$c`.
Including the partial there turned `$c` into a string and 500'd the page. This is
the *same class* as the `/my-work` defect fixed in `6dae7c4` — a shared piece of
code writing over a name its host was using. Every variable the partial assigns is
now namespaced (`$gs*`), and a test asserts it stays that way.

**A false performance finding, caught before it was reported.** `/quality` was
measuring 30s and 500ing, which looked like a pre-existing defect — it reproduced
at HEAD too. It was neither: the default-password check is cached against a
fingerprint of (user count, max id, max `pwd_changed_at`, active count), and *my
own probe users* moved that fingerprint, forcing a 191-account bcrypt recompute
inside a 30s request. Warming the cache once from the CLI restored `/quality` to
0.98s — the exact figure in the B9 audit. Recorded because the near-miss matters:
the "before" measurement was contaminated by the measurer.

---

## Deferred observations (§40 — noticed, not acted on)

* `system_status_worst()` bcrypt-tests every never-changed account whenever its
  cache fingerprint moves — 33.8s measured on 191 accounts. Cached, so it is rare,
  but the first request after any user is added or removed pays it. Out of B9's
  scope; worth its own look.
* The Operations home is still 18.3 phone screens. The next lever is the
  cross-office block (2,274px) and giving each register its own tab — the tabs
  engine already supports nesting and `data-count`. Not done here: it changes
  anchors and default-tab behaviour, which is more than B9-1 asked for.
* `.btn.small` touch targets, global header search button — still deferred to B10.

---

## Files changed

| File | Change |
|---|---|
| `views/ops/_ops_registers.php` | registers defer their overflow into `details.fold` |
| `views/ops/operations_home.php` | pending-scheduling list defers the same way; renders the ranked strip |
| `views/ops/_glance_strip.php` | **new** — the shared ranked/attention strip, namespaced |
| `views/ops/area_home.php` | includes the strip instead of carrying its markup |
| `views/dashboard.php` | renders the ranked strip; recruitment joins the role ordering |
| `views/ops/access_notice.php` | **new** — the access-outcome page |
| `lib/areas.php` | `ops_access_notice()` / `ops_area_denied()`; area homes explain |
| `lib/tosrm.php` | Operations home explains |
| `lib/ops.php` | Command Centre explains |
| `tests/test_b9_placement.php` | **new** — 57 focused tests |
| `tests/test_p3_dashboard_glance.php` | follows the include |
| `deploy-check.php` | regenerated |

**B9-1 … B9-4 complete. B10 not started.**
