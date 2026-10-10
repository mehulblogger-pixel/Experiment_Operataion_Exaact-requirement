# Application structure audit — navigation, duplication, and whether to overhaul

**Date:** 2026-10-10 · **Branch:** `claude/testing-branch-setup-0gqe8n`
**Asked for by the owner:** *"track and back track are not at all linked. Also there
is duplications. Can you audit the whole application module by module. Don't code,
I need report… because testing is of no use when application itself is not built
right. You can always correct me if I am wrong."*

This is a report. No application code was changed to produce it. Every number below
came from reading the source mechanically; the scripts are listed in §9 so anything
here can be re-checked.

---

## 1. The short answer

**You are right about the problem. I think you are wrong about the cure, and the
reason is good news.**

Right: navigation is not twenty-three separate bugs, it is one missing piece of
architecture, and no amount of testing will find its way out of that. The numbers in
§3 are not close.

Right: there is real duplication, and it is the kind users feel — the same job done
in several places under different names.

Wrong, I believe: that this needs the application rebuilt. The thing that makes a
breadcrumb trail possible — knowing which screen belongs where — **already exists in
this codebase, in three pieces that were never connected to each other.** Wiring
them is a matter of weeks. Rebuilding would throw away 128,000 lines of working
business rules, including the accreditation separations that took real effort to get
right, and would reintroduce every bug already fixed.

I set out the evidence first and the recommendation last, so you can disagree with
the recommendation without having to take my word on the facts.

---

## 2. What this application actually is, by the numbers

| | |
|---|---|
| Screens (view files) | **405** |
| Routes the router serves | **450** |
| Code files (`lib/`) | **233** |
| Lines of application code | **128,107** |
| Automated tests | **564 files · 16,479 checks, all passing** |
| Modules | **31**, grouped into **9** areas |

This is not a small or a young application. That matters to the recommendation.

---

## 3. Finding 1 — navigation: there is no model of where anything lives

You described it exactly: *"track and back track are not at all linked."* Here is
what that is, measured.

### 3.1 Most screens have no way back at all

| | Count |
|---|---|
| Screens with **no Back link whatsoever** | **343 of 405** |
| Screens with a Back link | 67 |
| …of those, pointing at a **fixed, hard-coded destination** | **51** |
| …built from a variable (so they can vary) | 16 |

### 3.2 The breadcrumbs are hand-typed, one screen at a time

277 screens carry a breadcrumb. Every one is typed into that screen by hand, as
literal text:

```html
<div class="crumbs"><a href="/">Home</a> › <a href="/masters">Masters</a> › Agency staff</div>
```

There are **80 different hand-typed parents** across the application. There is no
central map that says which screen belongs under which. Nothing checks these; nothing
can.

And **108 of those 277 breadcrumbs declare their parent as simply "Home"** — meaning
the trail reads *Home › This screen*, with the entire middle missing. For those
screens there is no trail to follow back even in principle.

### 3.3 Why a fixed parent is guaranteed to be wrong

This is the heart of it. A hard-coded Back is only ever correct if a screen has one
way in. Measured:

| | Count |
|---|---|
| Routes reachable from **more than one** place | **228 of 404** |
| Routes reachable from **four or more** places | **88** |
| The Job screen is linked from | **30 different files** |

So the Job screen's single Back is, at best, right for one arrival in thirty. The
same is true of `/settings` (24 ways in), `/documents` (21), `/call` (16),
`/quote` (15).

**Your example, precisely.** Approval rules are not one screen — they are reached
from Sales (`/quote-approval-rules`), from Reporting (`/idems-approval-rules`) and
from a general approvals area (`/approval-rules`). Whichever parent each one names,
it must be wrong for everybody who came the other way. You did not find an edge
case; you found the rule.

### 3.4 What is actually missing

One sentence: **the application has no model of its own shape.** It knows how to
render every screen and it knows who may open it — the permission work proved that
gate is sound — but nothing anywhere can answer *"where does this screen sit?"*

That is why this cannot be fixed screen by screen, and why fixing the twenty you
notice would leave the other three hundred and eighty-five.

---

## 4. Finding 2 — duplication: one idea, several separate homes

Not duplicated *code* — duplicated *concepts*, which is the kind users feel.

### 4.1 Approvals — the worst case

**12 routes across at least 4 separate subsystems, touching 10 code files.**

| Route | What it is |
|---|---|
| `/approvals`, `/approval-act` | a general approvals inbox |
| `/my-approvals` | a *second*, separate inbox for recruitment |
| `/recruit-approvals` | the recruitment approval matrix |
| `/quote-approval-rules` (+ new/edit/delete) | approval rules for quotations |
| `/idems-approval-rules` | approval rules for inspection reports |
| `/approval-delegations` | who may act for whom |

A manager who signs things off has **two different inboxes** and must know which kind
of thing they are approving before they know where to look. "What needs me today?"
has no single answer.

### 4.2 The others

| Idea | How many separate homes |
|---|---|
| Templates | **14 routes** — `crm-templates`, `doc-templates`, `templates`, `org-template`, `partner-template`… |
| Settings | **6 separate settings screens** — `tally-settings`, `checkin-settings`, `portal-settings`, `vendor-settings`, `ai-settings`, plus `/settings` |
| Audit trail / history | **3 unrelated systems** — `trace-audit`, `audit-log`, `internal-audits` |
| Reconciliation | **3 separate** — attendance, revenue, cost |
| Configuration | 3 more — `recruit-config`, `billing-config`, `ratings-config` |

**A fair caveat, so you can weigh this honestly.** Some of these are *correct*.
An internal audit (a quality process) and an audit log (a security record) are
genuinely different things that share an English word. Attendance, revenue and cost
reconciliation are three real business processes. I am not claiming all of these
should be merged — I am claiming nobody has ever stood back and decided which should
be, and the result is a product where related things are scattered by accident
rather than by decision.

The one I am confident about is approvals. Two inboxes for one human question is not
a design, it is an accident of two features being built at different times.

### 4.3 The same pattern, already proven once

The permission audit two days ago found the identical shape: **154 hand-written gate
helpers**, each inventing its own private rule, because no shared vocabulary existed.
That is the same disease in a different organ — and it was fixed by giving the
application a shared vocabulary rather than by rewriting it. I think that is the
template for this too.

---

## 5. Finding 3 — two files are doing far too much

| File | Lines |
|---|---|
| `lib/idems.php` | **10,942** |
| `lib/ops.php` | **10,242** |

These two hold 17% of the application between them. `ops.php` in particular is the
router, the module gate, dozens of handlers and a pile of helpers in one file. It is
not broken — 16,479 tests say so — but it is where changes are riskiest and where a
new developer would take longest to become useful.

This is a maintainability finding, not a user-facing one. It should not drive the
decision; it should be cleaned up gradually, module by module, as each is touched.

---

## 6. The finding that changes the answer

Before recommending anything I looked for whether the navigation model would have to
be invented from nothing. **It would not.** Three pieces already exist:

| Piece | Where it lives | Coverage |
|---|---|---|
| **route → module** | the module gate's map in `lib/ops.php` | **452 entries**, plus a prefix fallback the code itself calls *"where the route nobody added to the map hole closes"* |
| **module → area** | `permission_nav_groups()` in `lib/access.php` | **all 31 modules → 9 areas** |
| **area → its home screen** | `ops_area_def()` in `lib/areas.php` | all 9 areas, with titles and icons |

Chain them and you get *screen → module → area → Home*: a complete, three-level trail
for essentially every screen in the product. **A full trail is already derivable for
53% of routes from the maps alone**, and the prefix fallback covers most of the rest.

None of this is used for navigation. The route→module map is used only to decide
*permission*. The module→area grouping is used only to lay out the *permissions
screen*. They have never been introduced to each other.

So the work is: build one small function that answers *"where does this screen sit?"*,
feed it from the maps that already exist, and have every screen's breadcrumb and Back
read from it instead of from hand-typed text. That is a wiring job with a known
shape — not a rebuild.

---

## 7. Module-by-module inventory

Routes resolved to their owning module and area. This is the skeleton a deeper
functional audit would hang off (see §8 for what it does *not* yet tell you).

| Module | Area | Routes |
|---|---|---|
| hiring | Recruitment | 46 |
| idems (inspection reports) | Reporting | 43 |
| quotes | Sales | 31 |
| jobs | Operations | 24 |
| settings | Admin | 16 |
| vouchers | Operations | 12 |
| portal | Directory | 11 |
| calls | Operations | 9 |
| invoicing | Money | 9 |
| crm_reports | Sales | 9 |
| users | Admin | 8 |
| clients | Directory | 7 |
| leads | Sales | 7 |
| audits | Quality & Accreditation | 6 |
| identity | Quality & Accreditation | 5 |
| inquiries | Sales | 4 |
| profitability | Money | 4 |
| competence | Quality & Accreditation | 4 |
| datacontrol | Quality & Accreditation | 3 |
| reports | Insights | 2 |
| complaints | Quality & Accreditation | 2 |
| equipment | Quality & Accreditation | 2 |
| overheads | Money | 2 |
| masters | Directory | 2 |
| reconcile · confidentiality · ncr · capa · impartiality | Operations / Quality | 1 each |

**Where the weight sits:** recruitment, inspection reports and quotations are
**120 of the ~276 resolved routes** — nearly half the application in three modules.
Any overhaul should start there, because that is where most of the user's time is
spent and where the cost of leaving it broken is highest.

---

## 8. What this audit does NOT cover — stated plainly

You asked for a module-by-module audit. What I have given you is a **structural**
audit of all 31 modules: how they are wired, how they are reached, where they
duplicate each other. That is the layer your two complaints live on, and it is
measurable, which is why it is trustworthy.

I have **not** audited each module's *business logic* — whether the recruitment flow
asks the right questions, whether the invoicing arithmetic is right, whether the
quality registers satisfy the standard. That is a different exercise: roughly one to
two days per significant module, done by reading the flow against `docs/04-flows/`
and the standard, and it cannot be mechanised.

I would rather tell you that than pad this report into looking complete. If you want
that deeper pass, the sensible order is the three heavyweight modules above, and it
should happen **after** the structural work, because half of what it would otherwise
report is "I could not find my way back", which we already know.

---

## 9. Recommendation

### 9.1 Not a rewrite — and here is the case against one

I want to argue this properly rather than just assert it.

**What a rewrite would cost you.** 128,000 lines of business rules; 16,479 passing
checks that encode what "correct" means; the accreditation separations (the person
who writes a report cannot be the person who signs it off); the permission model just
built and proven; and every defect already found and fixed — including the ones you
personally found in UAT. All of that is knowledge, and almost none of it is written
down anywhere except in the code.

**What the evidence says.** The two problems you raised are both *missing shared
structure*, not *wrong structure*. Nothing I measured says the foundations are wrong.
The permission model turned out to have exactly one central gate, correctly placed,
guarding every route — that is a sign of a well-built spine, not a rotten one. The
navigation data exists and is simply unconnected. A rewrite is the right answer when
the foundations cannot carry the building; that is not what I found.

**Where I think your instinct is sound.** Patching these one screen at a time *is*
futile, and you were right to refuse it. The answer is not "rebuild" or "patch" but a
third thing: **give the application the shared structure it is missing, once, and let
385 screens inherit it.** That is what fixed the permission model, and it worked.

### 9.2 What I would do, in order

| # | Work | Why it is first | Rough size |
|---|---|---|---|
| **1** | **One navigation model.** A single function answering "where does this screen sit?", fed by the maps that already exist. Every breadcrumb and Back reads from it. Back follows where you actually came from, falling back to the true parent — never to a guess. | Fixes R-21, R-22 and your approval-rules example **and the other 380 screens**, in one change | ~1–2 weeks |
| **2** | **Unify approvals.** One inbox answering "what needs me?", one place to configure who signs off what. | The clearest real duplication; visible to every manager daily | ~1 week |
| **3** | **Decide the duplication list.** Go through §4.2 and rule, for each: merge, or keep separate and name them so they stop looking like duplicates. | Cheap, and it is a decision only you can make | ~2 days + your time |
| **4** | **Then the deep module pass**, heaviest three first. | Now worth doing, because findings will be about the business, not the plumbing | 1–2 days each |

### 9.3 The one place I would push back on "testing is of no use"

You are right that testing *navigation* is pointless until navigation exists. But
**Journey H — the security journey — does not depend on navigation at all.** It asks
whether somebody can reach data they should not. If there is a hole there, it is a
hole whether or not the Back button works, and it is the one class of defect that can
genuinely harm you and your clients.

It is about an hour's work. I would do that one before starting any of the above, and
leave the rest of the UAT until after step 1 — because you are right that the rest
would mostly re-report the same navigation problem in thirty different voices.

---

## 10. How to re-check any number here

The audit scripts are in this session's scratchpad and are read-only. Nothing in them
writes to the application. The measurements, in order of appearance:

| Number | How it was obtained |
|---|---|
| view / route / file counts | `find`, `wc -l`, and the router's own `case $route ===` statements |
| back-link and breadcrumb counts | parsing every file under `views/` for `class="crumbs"` and Back anchors |
| inbound-link counts | every `href="/…"` in `views/` and `lib/`, grouped by target |
| duplication groups | route names grouped by concept keyword |
| route → module → area coverage | the gate's `static $map`, `ops_module_family()`, and `permission_nav_groups()` |

If any single figure here matters to your decision, ask and I will show you the exact
lines it came from.
