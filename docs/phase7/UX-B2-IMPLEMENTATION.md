# UX-B2-IMPLEMENTATION — Navigation + Recruitment Command Centre

**Scope: B2 only.** No workflow, permission, entitlement, lifecycle, KPI,
search, dashboard, area-home or database change. Nothing from B3–B10.

---

## 1. Baseline

Entering at `bad3251` (B1), both engines green. Measured in Chromium at
1280×900, signed in as an administrator.

| Screen | Height | Headings | Links (container) | Visible at once | Distinct destinations |
|---|---:|---:|---:|---:|---:|
| `/recruitment-cc` | 2,621px | 20 | **43** | 30 | 25 |
| `/recruitment` | 556px | 4 | 18 | — | — |
| `/operations` | 1,510px | 6 | 24 | — | — |
| `/` dashboard | 2,146px | 12 | 40 | — | — |

**The audit's "43 links" is correct.** My first count said 30; that was the
*visible* subset. 43 is every link in the container, 13 of which sit inside the
admin-only Setup menu and are hidden until opened. Recorded so the after-number
is compared against the same measure.

---

## 2. Navigation inventory

### A1 · Primary navigation — the left rail, in order

Dashboard `/` · **Owner home `/owner`** *(superadmin only)* · Search records
`/search` · Where the flow is broken `/flow-gaps` · What to fix `/advisor` ·
My jobs `/my-jobs` · My reports `/documents` · New report · Endorsements ·
My vouchers · **Sales · Marketplace · Operations · Recruitment `/recruitment-cc`
· Quality · Reporting · Money · Insights · Directory · Admin** · account items.

### A3–A5 · The "home" concepts, and what each actually is

| Route | Handler | Purpose | In the rail? | Otherwise reachable? |
|---|---|---|---|---|
| `/` | dashboard | role/permission overview (21 conditionals) | yes | — |
| `/owner` | `ops_owner_home` | platform-owner landing | yes, **superadmin only** | — |
| `/my-work` | `ops_my_work` | personal assigned work | no | **yes** — every area home |
| `/command-centre` | `ops_command_centre` | management state-of-the-business | no | **yes** — every area home |
| `/operations` | `ops_operations_home` | Operations module home | yes | — |
| `/recruitment` | `ops_recruitment_home` | compact recruitment *work* view (Today / Risks / Opportunities) | **no** | from 4 recruitment screens |
| `/recruitment-cc` | `ops_recruitment_cc` | recruitment *analytics* board | yes, as "Recruitment" | — |
| 8 area homes | `ops_area_home` | module entry points | yes | — |

**A correction to my own working note.** Mid-audit I recorded `/my-work` and
`/command-centre` as having "no navigation entry anywhere". That was wrong: I
had checked `lib/areas.php` and the rail but not `views/ops/area_home.php`,
which links to both from every area home. They are not orphans. Corrected
before anything was built on it.

### A8 · Recruitment entry points — the real inventory

The B2 brief asks for "the five competing recruitment starts". **That phrase
does not appear in the UX audit**, and the five things the earlier
`P7-ENTRY-AUDIT` actually names are five competing answers to *"where do I
start?"* — `/`, `/owner`, `/advisor`, `/flow-gaps`, `/recruitment-cc`, plus
`/command-centre` and `/my-work` behind them. They are **competing homes, not
competing recruitment-creation routes.** Reported rather than assumed.

The recruitment entry points, enumerated from code:

| # | Route | Label | Who | Creates | Leads to | Duplicate? | B2 decision |
|---|---|---|---|---|---|---|---|
| 1 | `/hiring-requests` | Hiring requests | `hreq_can_view()` | a hiring **request** (approval first) | request detail | No — the governed path | **KEEP; door added.** Was reachable only from inside an individual request |
| 2 | `/requisition-new` | ＋ New requirement | hiring edit | a **requisition** directly | requisition detail | No — the direct path (**ADR-001**) | **KEEP AS PRIMARY** (unchanged) |
| 3 | `/candidate-new` | ＋ Add candidate | hiring edit | a **candidate** (supply, not demand) | candidate detail | No — different object | **KEEP AS SECONDARY** (unchanged) |
| 4 | `/recruitment` | Action view | `recruit_home_can()` | nothing — a work view | — | No — different purpose from the CC | **KEEP AS CONTEXTUAL** (unchanged) |
| 5 | `/recruitment-cc` | Recruitment (rail) | `recruit_home_can()` | nothing — an analytics board | — | No — same gate as #4, different job | **KEEP AS PRIMARY HUB** |
| 6 | `/positions-import` | Import organogram | hiring admin | positions / headcount structure | positions | No | **KEEP, inside Setup** (unchanged) |

**Nothing was deleted, redirected or hidden.** One door was *added*.

---

## 3. What was changed

### 3.1 The Command Centre leads with work

The page put **"Needs attention today" fourteenth of twenty headings**. Measured:
you scrolled **2,018px** — past six analytics sections, more than two
screenfuls — before the page told you anything you could act on.

Sections were reordered. Nothing else: not a number, a query, a filter, a
permission or a route.

| | Before | After |
|---|---|---|
| 1st section | Hiring demand & pipeline volume | **Needs attention today** |
| 2nd | Manpower P&L | Approvals *(conditional)* |
| 3rd | Approvals *(conditional)* | Ownership, deployment & requirement tracker |
| 4th–6th | Conversion / Funnel / Trend | Hiring demand · approved headcount · Manpower P&L |
| — | — | **── Analysis ──** *(divider)* |
| rest | … Needs attention today **14th** … | Conversion · Funnel · Trend · Recruiter performance · Drop reasons — **original order kept** |

**The headline number: scroll-to-first-action 2,018px → 371px.** In a 900px
viewport that moves it from the third screenful to above the fold.

### 3.2 The hiring-request register had no door

`/hiring-requests` — the register for the **first step of the documented chain**
— appeared in **no** rail entry, **no** area tile and **nowhere** on the
Command Centre. It was linked only from an individual hiring request's own
detail screen: you had to already be inside one to find the list.

Added to the Command Centre header, behind `hreq_can_view()` — the same gate the
route handler itself uses.

### 3.3 One neutral sentence, and a product decision NOT taken

The page now says: *"Hiring starts either way: raise a **hiring request** when
the headcount has to be approved first, or create a **requirement** directly
when it does not."*

**It deliberately does not say which one you should prefer.** That is
**ADR-001**, open since Phase 2 and recorded in `P7-ENTRY-AUDIT` as *"a business
decision that Phase 7 cannot take for the owner"*. Both paths are implemented,
supported and tested (`M5-ASSIGNMENT-STATE-MATRIX`: a requisition with no hiring
request is ALLOW — "there is no approval to respect"). The test suite asserts
that no preference is claimed.

### 3.4 Rail icons

Three collisions, each making two different destinations look alike:

| Icon | Was shared by | Now |
|---|---|---|
| 🧭 | Owner home **and Recruitment** | Recruitment → 🧑‍💼 |
| 📑 | My reports **and the Reporting module** | My reports → 📄 |
| 🛠 | *(introduced by my own first pass)* What to fix vs Operations 🛠️ | What to fix → 🩺 |

The third was my own mistake: my first fix gave "What to fix" a spanner, which
is visually identical to Operations' spanner. Caught by the uniqueness scan and
corrected. All 20 rail icons are now distinct, asserted by test.

---

## 4. Measurements, before and after

| Measure | Before | After | Note |
|---|---:|---:|---|
| **Scroll to first actionable section** | **2,018px** | **371px** | the point of the phase |
| Page height | 2,621px | 2,801px | **up 180px** — see below |
| Headings | 20 | 21 | +1, the Analysis divider |
| Links (container) | 43 | 46 | +3 |
| Distinct destinations | 25 | 26 | +1, `/hiring-requests` |
| Destinations removed | — | **0** | asserted by test |
| Rail icon collisions | 3 | **0** | |

**The page got taller, not shorter, and that is stated plainly.** The brief
(Part Q) forbids an arbitrary height target, and height was never the defect —
*order* was. The extra 180px is one explanatory sentence, one divider, and one
added button. The same content is present; it is now in the order a recruiter
works in.

---

## 5. Verification

| Layer | Result |
|---|---|
| `tests/test_b2_navigation_cc.php` (new, 42 assertions) | **42 passed, 0 failed** |
| `tools/b2-nav-check.js` (new, 27 checks in Chromium) | **27 passed, 0 failed** |
| Mobile 360×800 / 390×844 / 412×915 | **PASS** — no overflow; still leads with the work; recruitment entry reachable at every width |
| Direct-URL authorization | **PASS** — signed out, `/hiring-requests`, `/recruitment-cc`, `/requisition-new` all refused; armed by proving they open again when signed in |
| Entitlement | **PASS** — a workspace without the module is refused all three routes at the server, whatever the menu shows |
| JavaScript / server errors | none |

### Mutation tests

| Mutation | Caught by | Result |
|---|---|---|
| A destination deleted from the page | server C1 | FAIL, as required |
| Analytics put back on top | server B1–B5 | 8 assertions FAIL |
| Page claims a preferred recruitment path | server D4/D5 | FAIL — ADR-001 stays the owner's |

A fourth fault was in the **checks themselves**: the browser check asserted
`/my-approvals` was rendered, but it lives inside the Approvals band which only
appears when something is awaiting approval — it was absent before B2 too. The
browser check now asserts only unconditionally-rendered destinations; the full
set including conditional ones is pinned at source level, which is the right
layer for it.

---

## 6. Deferred — found during B2, not fixed here

| Finding | Phase |
|---|---|
| **ADR-001 undecided** — which recruitment path a workspace should prefer | **PRODUCT DECISION — owner** |
| `/recruitment` (work view) is not in the rail; the rail's "Recruitment" goes to the analytics board | **B9** (needs the dashboard/home decision) |
| Rail's first entries still overlap in meaning (Dashboard / flow-gaps / advisor) | **B9** — changing rail *routes* is medium-risk and touches M11 invariants |
| Two `Approvals` bands share one heading word | **B10** |
| Area-home counts | **B5** — untouched, per instruction |
| Command Centre has no next-action block | **B3** |
| Hiring request ↔ requisition relationship line | **B4** |
| 21 headings is still a lot for one screen | **B10** |

---

## 7. Files changed

| File | Change |
|---|---|
| `phpapp/views/ops/recruitment_cc.php` | sections reordered; Analysis divider + its style; hiring-request door; neutral two-paths sentence |
| `phpapp/views/layout_top.php` | three rail icons made unique |
| `phpapp/tests/test_b2_navigation_cc.php` | **new** — 42 assertions |
| `phpapp/tools/b2-nav-check.js` | **new** — 27 checks |
| `phpapp/deploy-check.php` | regenerated |

### Full regression

| Engine | Result |
|---|---|
| SQLite | **13,640 passed, 0 failed** |
| **MariaDB (authoritative)** | **13,641 passed, 0 failed** |

### Protected modules — MariaDB

| Module | Suite | Result |
|---|---|---|
| Recruitment | `recruit` | 296 / 0 |
| Recruitment — numbers & races | `rb3` | 276 / 0 |
| Workforce identity | `r20` | 44 / 0 |
| Workforce duplicate doors | `fa7` | 29 / 0 |
| Operations | `p2` | 437 / 0 |
| Operations | `p3` | 2,611 / 0 |
| Reporting | `report` | 352 / 0 |
| Money — billing | `billable` | 90 / 0 |
| Money — vouchers | `voucher` | 117 / 0 |
| Marketplace | `connect` | 1,020 / 0 |
| Marketplace | `mkt` | 174 / 0 |
| Tenant isolation & entitlement | `saas` | 169 / 0 |
| Tenant API | `tapi` | 170 / 0 |
| Navigation invariants | `m11` | 60 / 0 |
| B1 accessibility (not regressed) | `b1_access` | 112 / 0 |
| B2 navigation | `b2_nav` | 42 / 0 |
