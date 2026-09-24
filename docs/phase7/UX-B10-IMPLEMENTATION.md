# UX-B10 — Contextual feedback & final accessibility corrections

| | |
|---|---|
| Baseline | `f5ce8a1` (Phase B closure audit) · B8 `2682177` · B9 `78f04ba` · My Work `6dae7c4` |
| Scope | CL-1 … CL-6 only |
| Schema changes | **0** |
| Data migrations | **0** |
| Workflow changes | **0** |
| Permission changes | **0** |
| KPI changes | **0** |
| Search-engine changes | **0** |
| Identity / workforce / marketplace model changes | **0** |

> **Numbering.** B10 renumbered the closure audit's IDs. This document uses B10's:
> CL-1 = audit CL-1 · CL-2 = CL-2 · **CL-3 = audit CL-6** (terminology) ·
> **CL-4 = audit CL-3** (header strip) · **CL-5 = audit CL-4** (breadcrumbs) ·
> **CL-6 = audit CL-5** (touch targets).

Every item follows the same shape: *the application already knows this — say it
where the user is.* No new engine, no new state, no new rule.

---

## CL-1 · Silent Finance action links

**Original behaviour.** A finance user saw **31** "Allocate" buttons on the
Operations home. Each one 302'd to `/` via `ops_require()`.

**Verification (before).** `FINANCE` → 31 links; clicking `/job-new?call=39` →
`302 → /`. `COORDINATOR` → 32 links, all working.

**What the application already did.** Four places render Allocate.
`call_detail.php` and the cross-office panel already gate it with
`call_can_allocate()` — the *same* predicate `/job-new` enforces. Two places did
not: the Operations-home card and the scheduling board. So hiding it where the
user cannot act **is** the established pattern (§11), not a new visibility rule.

**Implementation.**
* `views/ops/operations_home.php` — the card gates Allocate with
  `call_can_allocate($pc)`. "Open" is untouched and still shown to everyone who
  can see the card.
* `views/ops/schedule_board.php` — gated with `is_coordinator_level()`, which is
  literally the predicate inside `/job-new`, so the button can never appear where
  the route would refuse. Non-coordinators get "Open" to the register instead.
* `lib/ops.php` — a *direct* `/job-new` navigation (bookmark, stale page) now
  renders the B9-4 access outcome rather than bouncing.

**Permission impact: none.** `is_coordinator_level()`, `call_can_allocate()` and
`ops_require()` are all unchanged. Finance could not allocate before and cannot now.

**Result.** FINANCE: **31 → 0** Allocate links, **31 Open links kept**;
direct `/job-new` → **403** "Allocating a job isn't available to your role." with
links to Home and My Work. COORDINATOR: **32 → 32**, unchanged.

---

## CL-2 · Inspector "My Jobs" applicability

**Original behaviour.** `/my-jobs` → `302 → /`, with the explanation flashing past.

**The exact condition (§14), read from source — not inferred:**

```php
if (!$insId && !is_coordinator_level()) { flash(inspector_link_msg(), 'error'); redirect('/'); }
```

So it is an **identity-link** state: the login carries the field role but is not
tied to an engineer record. It is *not* "no permission", and *not* "no jobs
assigned" (§16). The application even has dedicated wording for it —
`inspector_link_msg()` — which the My Work notice already uses.

**Implementation.** `ops_my_jobs()` renders the access outcome carrying that
existing message. `ops_access_notice()` gained an optional third argument so a
caller with a precise explanation is not followed by generic reassurance.

**Result.** INSPECTOR → **403**, `<h1>My jobs</h1>`, *"This screen lists the jobs
assigned to you, and your login is not linked to a team member record yet — so
there is nothing it can show"*, plus the administrator's two-step fix and links
onward. The word "permission" does not appear, because that is not the problem.
A coordinator-level user still reaches the real screen — no regression.

---

## CL-3 · Contextual terminology

**Original behaviour.** B4 wrote six definitions into the terminology registry.
`T_NOTE()` — the renderer — had exactly **one** call site (`hiring_request.php`).
The other five were reachable only from `/terminology`.

**Implementation.** The same helper, the same registry, one line on the screen
the term is the *subject* of (§22, §24):

| Term | Screen | Why there |
|---|---|---|
| Workforce | `recruitment_home.php` | the page is titled "Recruitment & Workforce" |
| Inspector | `inspector_list.php` | where "every inspector is workforce, not every workforce member is an inspector" bites |
| QA | `idems/vet_review.php` | the vetting review **is** the QA stage — exactly what the definition says |
| Billing readiness | `candidate_detail.php` | the panel carrying that heading |
| Professional | `connect_talent.php` | the self-listed pool, where Professional vs Candidate bites |

**Not done:** no glossary engine, no tooltip framework, no new table, no renames.
Each screen carries **exactly one** note — asserted by test. Nothing was added to
table rows, and the note appears only where the panel itself renders (the billing
readiness definition appears on candidates 47/48/59… and not on candidate 7,
because the panel is conditional).

**Role relevance (§43).** ADMIN and the recruitment user see 3 of the 3 checked
screens; COORDINATOR 2; FINANCE and INSPECTOR 0 — definitions do not leak to
roles that cannot open the screen.

---

## CL-4 · Clipped header strip

**Original behaviour.** A ~45px clipped strip above the first card on some mobile
registers.

**Cause, confirmed.** In `@media (max-width:640px)`, `.rt-head{display:none}` has
specificity (0,1,0) and is *followed* by `table.rtable tr{display:block}` at
(0,1,1). The later, stronger rule won, so a register whose header is a bare `<tr>`
(rather than a `<thead>`) turned its header into a card too.

**Implementation.** One added selector — `table.rtable tr.rt-head{display:none}`
(0,2,1) — declared before the card rule. The original rule is left in place for
tables that use a real `<thead>`. The card mechanism, the data and the internal
scrolling are untouched.

**Result.** 17 `.rt-head` rows across `/candidates`, `/calls`, `/requisitions`,
`/operations`, `/jobs`, `/invoicing` — **0 visible** at 360 / 390 / 412.
**Desktop table headers still visible** (asserted, §28).

---

## CL-5 · Missing breadcrumbs

**Original behaviour.** `/job` and `/call` had no breadcrumbs and no back-link,
while `/requisition`, `/candidate` and `/hiring-request` had both.

**Implementation.** The existing `.crumbs` component, with the application's own
hierarchy — the rail groups both under Operations, and each record belongs to its
register. **Each ancestor is a link only where the viewer may open it** (§32):
Operations is a link only under the same predicate the Operations home enforces,
and the register only under `can('mod.jobs.view')` / `can('mod.calls.view')`.

A blank `job_code` is a real state the page title already handles, so the trail
falls back to `#id` rather than ending in an empty segment.

**Result.** `Home › Operations › Jobs › JA-1` · `Home › Operations › Work Orders
› CC-1` · `/job?id=1` (no code) → `… › #1`.

---

## CL-6 · Touch targets

**Verification first (§34).** Measured at 360px across 10 screens:

| Selector | Height | Count |
|---|---|---|
| `a.btn.small.secondary` | 36px | 1,646 |
| `a.btn.small` | 36px | 1,396 |
| `a.dz-chip` | 22px | 4 |
| **Total** | | **3,046** |

**3,042 of 3,046 were one selector** — `.btn.small`, which B7 deliberately held at
`min-height:36px` while setting 44px for everything else in the same block. It is
the row action on every register (Allocate, Open, Edit).

**Implementation.** Two lines in the **existing** `@media (pointer:coarse)` block:
`.btn.small` 36 → 44px, and `.dz-chip` given the same floor. Because the whole
block is behind the coarse-pointer query, **desktop is untouched** — asserted:
`.btn.small` still renders at 27px on a mouse.

**Result.** **3,046 → 0** sub-44px interactive elements at 360 / 390 / 412.

---

## Testing

| | |
|---|---|
| Focused B10 | **39 / 39** (`tests/test_b10_contextual.php`) |
| Mutations | **6 / 6 killed** |
| SQLite full suite | **14,127 passed / 0 failed** |
| MariaDB full suite (authoritative) | **14,128 passed / 0 failed** |
| Browser + mobile | 5 roles × desktop, plus 360 / 390 / 412 |
| JS errors | **0** |
| Application 500s | **0** |
| Page-level horizontal overflow | **0** |
| Tenant isolation | **PASS** — two separate tenant databases |

CL-1 and CL-2 are exercised **behaviourally**: `tests/_b10_worker.php` runs the
real handler through the production `view()` in a separate process (the pattern
the My Work regression established, because three other test files install a stub
`view()`). Source assertions alone would not satisfy §41.

**The CL-1 test arms itself.** A throwaway database has no work in it, so the
pending-scheduling grid is empty for every role and "finance sees no Allocate
button" would pass vacuously. The worker seeds one open, in-scope call and the
test asserts the card is really there (1/1) before asserting who gets the button.

### Mutations

| | Mutation | Killed by |
|---|---|---|
| M1 | Allocate unconditional again | focused (1 failure) |
| M2 | Restore the My Jobs silent bounce | focused (4) |
| M3 | Remove one contextual definition | focused (2) |
| M4 | Restore the CSS specificity conflict | focused (3) **+ browser** |
| M5 | Remove breadcrumbs from `/job` | focused (3) **+ browser** |
| M6 | Restore the 36px touch target | focused (2) **+ browser** |

M4–M6 are killed by **user-visible browser assertions**, not only by source
checks, as §41 requires.

### Role matrix (desktop)

| Role | Allocate links | `/my-jobs` | definitions | crumbs | search sources | My Work | Ops home |
|---|---|---|---|---|---|---|---|
| ADMIN | 33 | opens | 3 | yes | 19 | ok | 5,535px |
| FINANCE | **0** | **explains** | 0 | yes | 7 | ok | 5,327px |
| INSPECTOR | 403 | **explains** | 0 | 403 | 1 | ok | — |
| COORDINATOR | 33 | opens | 2 | yes | 14 | ok | 5,353px |
| RECRUITMENT | 33 | opens | 3 | yes | 19 | ok | 5,535px |

### Regression of earlier phases

* **B7** — 0 page-level horizontal overflow at all three widths; card conversion
  and internal scrollers unchanged; desktop headers still visible.
* **B8** — registry still **19 sources**; per-role scoping intact and correct
  (19 / 14 / 7 / 1 by role — that is the engine working, not a regression);
  candidate, requisition and hiring-request searches all verified.
* **B9** — Operations home still 5,535px on desktop, the exact post-B9 figure;
  role ordering, ranked next actions and the inspector access outcomes all intact.
* **My Work (`6dae7c4`)** — untouched; `<h1>My Work</h1>` for all five roles,
  correct per-user subtitle, no view-name collision, no traversal regression.

### Performance (§50)

Not touched, and not made worse. Warm timings: `/` 0.66s · `/my-work` 0.24s ·
`/operations` 0.57s · `/quality` 0.93s · `/money` 0.96s · `/candidates` 0.24s ·
`/calls` 0.26s — all in line with the closure-audit figures.

---

## Not implemented (deliberately)

* **Register pagination** — `/candidates` is still 936 rows. **Deferred: product
  decision.**
* **bcrypt / default-password cache** — still ~34.8s for 191 accounts on a cache
  miss. **Deferred: technical debt.**
* No visual redesign, no new components, no new cards, metrics or reports, no
  performance work, no permission or workflow change.
* Nothing else discovered during B10 was fixed opportunistically.

---

## Files changed

| File | Item |
|---|---|
| `views/ops/operations_home.php` | CL-1 |
| `views/ops/schedule_board.php` | CL-1 |
| `lib/ops.php` | CL-1 (`/job-new` outcome), CL-2 (`ops_my_jobs`) |
| `lib/areas.php` | CL-2 (`ops_access_notice()` optional sub-line) |
| `views/ops/access_notice.php` | CL-2 |
| `views/ops/recruitment_home.php`, `inspector_list.php`, `idems/vet_review.php`, `candidate_detail.php`, `connect_talent.php` | CL-3 |
| `assets/css/app.css` | CL-4, CL-6 |
| `views/ops/job_detail.php`, `views/ops/call_detail.php` | CL-5 |
| `tests/test_b10_contextual.php`, `tests/_b10_worker.php` | tests |
| `deploy-check.php` | regenerated |

**CL-1 … CL-6 complete. No further UX phase started.**
