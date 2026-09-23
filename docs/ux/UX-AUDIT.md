# UX-AUDIT — EXAACT pre-launch experience pass

**Phase A complete. No code was changed during the audit** (Part 1), with one
exception the owner asked for mid-audit and which is recorded in the changelog:
the KPI card unification.

Eight steps, **25 findings**, every one traced to a measurement rather than an
impression. Supporting detail lives in `UX-A1` … `UX-A8` in this folder.

---

## The headline

**EXAACT is in considerably better shape than the brief assumes.** Nine things
the brief expects to be broken are already built and working, and reporting them
as faults would have sent this pass to rebuild what is right.

**But one defect outranks everything else in this document**, and it is not a
design problem: adding a person through Masters shows the user a raw
`SQLSTATE[23000]` database error. That should be fixed before any cosmetic work.

---

## Findings

Severity: **CRITICAL** = a user meets a fault today · **HIGH** = a daily task is
materially harder · **MEDIUM** = friction · **LOW** = tidying.

Class: **workflow** = behaviour · **structural** = layout or information
architecture · **cosmetic** = presentation only.

| ID | Screen / area | Problem | Why it confuses | Class | Sev | Risk of fixing |
|---|---|---|---|---|---|---|
| **F-A7-1** | Masters → Add a person | Bypasses the duplicate guard; uncaught exception prints `SQLSTATE[23000]` | Two other doors name the person you may already have; this one shows a database error | workflow | **CRIT** | low — helpers exist |
| **F-A2-2** | Every page | Theme engine overrides a compliant `--muted` (5.17:1) with a derived grey measuring **2.46:1** | Most secondary text in the product fails WCAG AA, by default | cosmetic | HIGH | low |
| **F-A3-1** | 8 area homes | 103 tiles, **0** carry a count — the badge is built, rendered, and unused | Every area answers "what can I do" and none answers "what needs attention" | structural | HIGH | low |
| **F-A4-1** | `/search` | Excludes candidates, requisitions, hiring requests, samples, methods, controlled docs, risks | Screen promises "every register you are allowed to see"; a candidate is findable once hired, invisible before | structural | HIGH | low |
| **F-A4-3** | 249 of 251 table views | Tables scroll sideways on a phone | Blueprint forbids it; field users are phone-first | structural | HIGH | medium |
| **F-A5-1** | Job 57, Test request 54, Engineer 48, User 40 | Every field shown at once | Part 9's "wall of fields"; four of the most-used forms | structural | HIGH | medium |
| **F-A6-3** | Test request / Requirement | Test request has **6** primary buttons and no breadcrumb; Requirement has **0** | "Override & proceed" sits at the same weight as "Save"; an open requirement suggests nothing to do | structural | HIGH | low |
| **F-A3-3** | Recruitment Command Centre | 2,643px, 20 headings, 43 links, config at the same weight as daily work | The densest screen in the product; fails all five 5-second questions | structural | HIGH | medium |
| **F-A8-1** | Everywhere | 25 curated definitions render only on the admin rename screen | The answers to Part 38 are written and unreachable | structural | HIGH | low |
| **F-A8-2** | Recruitment / Money | Hiring request, workforce, inspector, QA, billing readiness have **no** definition | Exactly the terms people confuse | structural | HIGH | low — 5 sentences |
| **F-A2-1** | Stylesheet | Declares `--brand:#1e40af` (blue); blueprint mandates Deep Teal; unthemed workspaces get blue | Anyone designing against the stylesheet designs the wrong product | cosmetic | HIGH | low |
| **F-A2-5** | Sidebar | Four of the first five entries all mean "what needs my attention"; two share an icon | Fails the 3-second rule at the top of the menu | structural | MED | medium — routes |
| **F-A2-6** | `/recruitment-cc` | 20 flat destinations, configuration beside daily work | The "database table" screen the brief warns about | structural | MED | medium |
| **F-A3-2** | All homes | 3 dashboards and 8 flat link menus both called "home" | "Which application am I in?" | structural | MED | low |
| **F-A3-4** | Dashboard | 30 cards, unranked; quick-actions band effectively absent (1 create link) | Nowhere for the eye to land first | structural | MED | low |
| **F-A4-2** | List screens | Candidate and requirement lists render every row, no pagination | Invisible now; the slowest screen in the product at scale | structural | MED | medium — behaviour |
| **F-A5-2** | 24 forms | Office pre-filled on 3, current user on 2; assumption stated on 3 | The system asks for what it already knows | cosmetic | MED | low |
| **F-A5-3** | Record pages | No shared next-action component (14 views mention one) | User arrives in the right place and is told nothing to do | structural | MED | low |
| **F-A6-1** | 752 pills | Colour + label, **4** carry an icon | Part 8 requires icon + label + colour | cosmetic | MED | low |
| **F-A8-3** | Record pages | No screen states the relationship between two confusable objects | The chain candidate→workforce→job→report→billing is invisible | structural | MED | low |
| **F-A2-3** | Global | Body text 14px, no mobile floor | Blueprint: never below 16px on mobile | cosmetic | MED | low — visible reflow |
| **F-A6-2** | 6 helpers | Status tone mapping duplicated six times | A vocabulary change needs six edits | structural | LOW | low |
| **F-A5-4** | 3 views | Bare "No records" (2 are shared components) | — | cosmetic | LOW | low |
| **F-A5-5** | 7 sites | Raw exception text in a flash, 6 admin-only | — | cosmetic | LOW | low |
| **F-A2-4** | `app.css` | ~20 rules style a `.topbar` shell **0 of 401 views** render | Costs the next developer an afternoon, not the user | cosmetic | LOW | low — verify portals |

---

## What is already right

Recording this is as important as the findings. Each was checked, not assumed,
and **none of it should be "improved" by this pass**:

| The brief expects | What is actually there |
|---|---|
| No role-based home | Dashboard carries **21** role/permission conditionals; inspectors get their own cockpit; an approval queue appears only when something waits |
| No "needs attention" | Already named sections on the dashboard |
| Duplicate KPI logic | **One** engine (`connect_kpi_board`) across inspector, client and freelancer boards — its own comment states the rule |
| Broken workflow continuity | Already correct: **398** redirects land on the record just touched; every journey Part 18 names lands on the record |
| A wall of filters | Worst screen has **8**; most have 1–3. **76** views already use chip/tab quick filters vs 23 raw bars |
| Duplicate search engines | **One** registry with a permission gate per source, and a single-hit shortcut |
| Status colour is decorative | **492 of ~534** pill uses are already a five-tone semantic vocabulary |
| Everything styled as primary | Primary **448**, secondary **436** — almost exactly 1:1 |
| No record header pattern | `.master-head` in **280 of 401** views; **178** pair a title with a status |
| Poor screen explanation | **295 of 401** views carry a one-line explanation |
| Cross-role vocabulary confusion | Prevented structurally by licence and permission gating |
| Jargon in the defaults | Already avoided — `call` ships as "Work Order", `engineer` as "Team Member" |
| Breadcrumbs missing | **274 of 401** views |
| Tiny touch targets | 44px already enforced at the mobile breakpoint |

---

## Corrections issued during the audit

Four of my own figures were wrong, all of them in the direction that would have
caused wasted work. They are corrected in place in the source documents:

| Reported | Corrected to | Where |
|---|---|---|
| "497 uses of one generic pill class — status colour is decorative" | Regex artefact. **492 semantic uses**; ~42 legacy tail | A6 |
| "1,317 confirmations, 3 say what's next" as the largest finding | Messages are **well written** and explain consequence; the gap is a missing **link** | A5 |
| "18 views with a bare no-records message" | **3** — the rest was prose beginning "Nothing here…" | A5 |
| "Errors substantially handled — 7 reachable, 6 admin-only" | **Wrong.** An *uncaught* exception shows a SQLSTATE on a routine screen | A7 |

A fifth correction went to the owner's own documents: **UAT test DUP-003 is
wrong** and now carries a warning not to run it until F-A7-1 is fixed.

---

## Nothing here requires a product decision

Every finding is presentation, information architecture or a contained code
defect. **No new status, table, relationship, permission, approval or workflow is
needed** for any of the 25. The product-decision register (UX-B4) is therefore
expected to be empty — which is itself a finding: the model underneath is sound.
