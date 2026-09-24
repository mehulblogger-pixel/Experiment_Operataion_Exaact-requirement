# Phase B — Consolidated UX Closure Audit (B0 → B9)

| | |
|---|---|
| HEAD audited | `78f04baa12cdaa784b0505f7ac6899906a66ec53` |
| Branch | `claude/testing-branch-setup-0gqe8n` |
| Working tree | **CLEAN** before and after |
| Remote | in sync (ahead 0, behind 0) |
| B8 (`2682177`) · My Work fix (`6dae7c4`) · B9 (`78f04ba`) | all present in HEAD |
| Date | 24 Sep 2026 |
| Engines | SQLite + MariaDB (authoritative) |
| Production code changed | **none** — audit only |

---

## 1 · Executive conclusion

> ## B0–B9 COHERENT — B10 CAN BE SCOPED

No blocking finding. Across 1,593 role-scoped page loads there were **0 server
errors, 0 broken links and 0 JavaScript errors**; both engines pass their full
suites; tenant isolation holds in both directions across every surface Phase B
touched; and the architecture invariants are intact — Phase B added **14
functions in total** and **not one of them** is a KPI, ordering, permission or
workflow engine.

Six B10 candidates are identified. All are presentation-only, all can be solved
with mechanisms that already exist, and none requires an architecture, workflow
or permission change. One item is a product decision and one is technical debt;
both are excluded from B10.

**B10 implementation: NOT STARTED.**

---

## 2 · Phase-by-phase verification

| Phase | Verdict | Evidence at HEAD |
|---|---|---|
| **B0** Baseline | **PASS** | `b22c1ce`; baseline documents present and still describe the tree they were written against |
| **B1** Accessibility / tokens | **PASS** | `0c02577`; 3 production files (`app.css`, `lib/access.php`, a checker). Added `theme_contrast/theme_readable/theme_rel_lum` only |
| **B2** Navigation / Command Centre | **PASS** | `2881e61`, `811f947`; `layout_top.php`, `recruitment_cc.php`. Recruitment CC re-measured: 1,843px, 4 headings — the A3 figure of 2,643px/20 headings is retired |
| **B3** Next Action | **PASS** | `b550f30`; `lib/nextaction.php` + 5 views. `.nowband` present and answering on all 5 record types checked (§4 below) |
| **B4** Terminology / relationships | **PASS (with CL-6)** | `e750b6f`; 6 definitions in the registry, all reachable on `/terminology`. Only 1 of 6 is at point of use — see CL-6 |
| **B5** Area-home counts | **PASS** | `2765b4e`; `lib/areas.php`. 103 tiles across 8 area homes; counts render on seeded data |
| **B6** Forms / disclosure | **PASS** | `2938e53`; 6 forms re-checked at 360px — **0 unreachable required fields**, submit present on all |
| **B7** Mobile tables | **PASS (with CL-3, CL-5)** | `d9604cf`, `b8d8bff`; 60 screens × 3 widths — **0 page-level horizontal overflow** |
| **B8** Global search | **PASS** | `214932c`, `2682177`; **19 sources**, one registry defined once; all three recruitment searches verified live |
| **B9** Dashboard / home / priority | **PASS** | `78f04ba`; Operations home 14,657px @360 (was 57,082px); ranked actions on the landing pages; 8 access outcomes; recruitment in the role ordering |

---

## 3 · Architecture invariants

| Invariant | Verdict | Evidence |
|---|---|---|
| **One KPI engine** | **PASS** | Phase B added no KPI function. `financial_rollup()`, `kpi_card()/kpi_card_row()`, `connect_kpi_board*` all pre-date Phase B and are unchanged |
| **One search registry** | **PASS** | `search_sources()` defined exactly once, 19 `$add()` sources. `cockpit_search()` indexes *setup destinations*, not records — re-confirmed, not a competing engine |
| **One area-tile builder** | **PASS** | `ops_area_def()` — exactly one definition, no second tile registry |
| **One next-action mechanism** | **PASS** | `action_centre()` ranks "what is waiting on me"; `lib/nextaction.php` answers "what happens next to *this record*". The file states the boundary in its own header and does not rank. Complementary by design, not competing |
| **One dashboard ordering mechanism** | **PASS** | One `// role-based ordering` block, 5 role branches. B9-3 made recruitment *join* it; no second sort exists (`usort`/`uasort`/`array_multisort` absent from the dashboard and the strip) |
| **Permission architecture** | **PASS** | `can()`, `is_master()`, `is_admin_level()`, `licence_enabled()`, `ops_area_licence_ok()` — exactly one definition each. **No Phase B commit defines a permission primitive.** `ops_require()` itself is byte-identical |
| **Workflow engines** | **PASS** | No workflow function added or removed in Phase B |

### Every function Phase B added (all 9 phases)

```
B1  theme_contrast, theme_readable, theme_rel_lum
B3  na_answer, na_candidate, na_hiring_request, na_html, na_requisition,
    na_resolvers, na_state
B4  workforce_origin
B8  hreq_search
B9  ops_access_notice, ops_area_denied
```

**14 added, 0 removed.** None is an engine of the kinds this audit protects.

---

## 4 · UX flow verification

```
Dashboard (/)                     ranked next actions, then unranked counts,
   ↓                              then role-ordered sections
Area home                         tiles + counts + ranked strip
   ↓
Register                          e.g. /requisitions, /candidates
   ↓
Record                            e.g. /requisition?id=30
   ↓
Status + .nowband                 "0 of 1 filled · Next: Put candidates
   ↓                              forward — 1 still to fill  [Add candidates →]"
Workflow step                     the existing module screen
```

Verified on five record types:

| Record | `.nowband` | Breadcrumbs | State → Next, as rendered |
|---|---|---|---|
| Hiring request | yes | yes | "Approved" |
| Requisition | yes | yes | "0 of 1 filled · **Next:** Put candidates forward — 1 still to fill" |
| Candidate | yes | yes | stage + origin shown |
| Job | yes | **no** | "Blockers ✓ None · The report is written. **Next:** issue it to the client" |
| Call | yes | **no** | "Nothing has been given out yet. The order is in…" |

The next-action layer answers all five of the §8 questions. Breadcrumbs are the
one inconsistency — see **CL-4**.

---

## 5 · Cumulative verification results

### Navigation (1,593 role-scoped page loads, 7 roles)

| Role | Visited | Status mix | 500s | 404s | JS errors |
|---|---|---|---|---|---|
| ADMIN | 260 | 254×200, 6 same-page | 0 | 0 | 0 |
| EXECUTIVE | 260 | 258×200, 1×403 | 0 | 0 | 0 |
| OPS_MANAGER | 260 | 254×200, 5×403 | 0 | 0 | 0 |
| COORDINATOR | 260 | 256×200, 3×403 | 0 | 0 | 0 |
| INSPECTOR | 33 | 22×200, 11×403 | 0 | 0 | 0 |
| FINANCE | 260 | 247×200, 7×403 | 0 | 0 | 0 |
| RECRUITMENT | 260 | 254×200, 6 same-page | 0 | 0 | 0 |

Every 403 is a B9-4 access outcome that stays on its URL and explains itself.
**No dead links, no loops, no unrelated landings.** The redirects that remain are
all *action* routes — see **CL-1** and **CL-2**.

### Mobile (20 screens × 360/390/412)

**0 page-level horizontal overflow. 0 off-screen CTAs. 0 JS errors.**
Intentional internal table scrolling was excluded, per the B7 distinction.

### Forms (6 forms at 360px)

| Form | Fields | Required | Unreachable required | Panels/folds | Submit |
|---|---|---|---|---|---|
| Candidate | 38 | 2 | **0** | 4 tabs | yes |
| Engineer | 37 | 1 | **0** | 4 folds | yes |
| Job | 3 | 0 | **0** | — | yes |
| Requisition | 82 | 0 | **0** | — | yes |
| User | 1 | 0 | **0** | — | yes |
| Requirement | 14 | 1 | **0** | — | yes |

### Search

Registry states **"the 19 registers"**. Live:

* Candidate full name `Ravi Sharma` → 2 matches under **Candidates**, linking to `/candidate?id=…`
* Requisition code `M8-REQ-1` → direct navigation to `/requisition?id=30`
* Hiring-request code `HRQ-2026-000001` → direct navigation to `/hiring-request?id=79`

### My Work (protected — `6dae7c4`)

| User | Display name | Result |
|---|---|---|
| admin | `admin` | 200, `<h1>My Work</h1>`, "Everything waiting on admin right now." |
| Zoya Kapoor | two words + space | 200, "Everything waiting on Zoya Kapoor right now." |
| Neha Rao | ordinary | 200, correct |
| Farah Mistry | ordinary | 200, correct |

No response contained the Admin area home or the failure panel. The traversal
class stays closed: the view identifier is captured before `extract()`, and the
suite's 6 mutations still kill.

### Tenant isolation (two separate tenant databases)

| Surface | A sees own | A sees B | B sees own | B sees A |
|---|---|---|---|---|
| next actions (`action_centre`) | YES | **no** | YES | **no** |
| glance (`dashboard_glance`) | YES | **no** | YES | **no** |
| global search (`search_run`) | YES | **no** | YES | **no** |
| attention, pending tasks, area homes, financial rollup | — | **no** | — | **no** |

**Zero leakage in either direction.**

### Regression

| | |
|---|---|
| SQLite | **14,088 passed / 0 failed** |
| MariaDB (authoritative) | **14,087 passed / 0 failed** |
| Browser | **1,593 / 1,593** page loads without a server error |
| JS errors | **0** |
| Failed application requests | **0** |

The one-test difference between engines is **engine-conditional by design**, not
a failure: 58 assertions are SQLite-only and 57 MariaDB-only (the SQLite
lock-contention test is explicitly *"skipped by design"* on MariaDB, which has
its own lock-wait handling). Both engines report 0 failed.

---

## 6 · Performance observation (kept separate, per §21)

**Still present. Cache-warm only. Not a B10 UX item.**

Chain, traced at HEAD:

```
dashboard_glance() (management half)   ops.php:8740
command_centre()                       ops.php:9033
        ↓
system_status_worst() → system_status() → compliance_status()
        ↓
accounts_on_default_password()         lib/compliance.php:294
        ↓
bcrypt × 4 per account never re-passworded through the app
```

* **Measured: 34.8s for 191 accounts** (18 flagged).
* Cached against a fingerprint of *(user count, max id, max `pwd_changed_at`,
  active count)* — so it recomputes after **any** user is added, removed or
  deactivated, or any password changes.
* Routes that pay it on a cache miss: the 8 area homes, `/command-centre`,
  `/system-status`, and the compliance/users screens.
* Routes that **no longer** pay it: `/` and `/operations` — B9-2 deliberately
  takes the ranked actions without the management pulse.

**Warm timings (all routes healthy):** `/` 0.63s · `/my-work` 0.24s ·
`/operations` 0.63s · `/quality` 0.90s · `/money` 1.00s · `/command-centre`
0.97s · `/candidates` 0.23s.

Classified **technical debt**, not B10 UX. It becomes user-facing only in the
window after a user-administration change, where a 30s execution limit turns the
first visit to an affected page into an error.

---

## 7 · Confusion pairs — re-tested at HEAD

| # | Pair | Verdict | Note |
|---|---|---|---|
| 1 | Hiring Request vs Requisition | **CLEAR** | Definition renders on the hiring-request screen; requisition detail states its origin, or that it had none |
| 2 | Candidate vs Professional | **LOW RISK** | Separate registers, separate search sources; "Professional" defined only on `/terminology` (CL-6) |
| 3 | Professional vs Workforce | **LOW RISK** | Both defined, neither at point of use (CL-6) |
| 4 | Workforce vs Inspector | **LOW RISK** | Definition is precise ("every inspector is workforce; not every workforce member is an inspector") but lives on `/terminology` only (CL-6) |
| 5 | Client vs Organisation | **CLEAR** | "The party that engages us and gets what we produce" |
| 6 | Marketplace Requirement vs Recruitment Requisition | **CLEAR** | Search labels them distinctly; the Recruitment source is prefixed "Recruitment · " |
| 7 | Approval vs Acceptance | **LOW RISK** | Distinct states in `.nowband` wording; no shared screen observed |
| 8 | Accepted vs Joined | **LOW RISK** | Distinct candidate stages; the chain is readable from the engineer screen |
| 9 | Inspection vs Job | **LOW RISK** | `.nowband` on `/job` speaks in job terms throughout |
| 10 | Report vs QA | **LOW RISK** | "QA is a stage in a report's life, not a separate document" — defined, but on `/terminology` only (CL-6) |
| 11 | Invoice vs Billing readiness | **LOW RISK** | Defined; not at point of use (CL-6) |

**No pair is classified REMAINING CONFUSION.** The recurring cause of every LOW
RISK entry is one thing: the definition exists but is not shown where the word
is. That is CL-6, and it is one mechanism away from resolved.

---

## 8 · Remaining findings

### B10 candidates (6) — presentation only, existing mechanisms

| ID | Screen | Problem | Evidence | Impact | Existing mechanism | Arch / workflow / permission change? |
|---|---|---|---|---|---|---|
| **CL-1** | `/operations` and other registers | Action links are rendered to users who cannot use them; clicking silently returns to `/` | **Browser + curl verified.** FINANCE sees **31** "Allocate" buttons on `/operations`; every one 302s to `/`. Also COORDINATOR (`/contract-openings`, agency edit), EXECUTIVE (`/call-new`) | A visible action that silently does nothing | `ops_access_notice()` (built in B9-4), or omit the link where the capability is absent | none / none / none |
| **CL-2** | Nav → `/my-jobs` | An inspector whose login is not linked to an inspector record is offered "My Jobs" and is bounced to `/` | **Verified.** `cl_insp` has `inspector_id = NULL`; `/my-jobs` → 302 `/`. My Work handles the identical case with a gentle notice | The inspector's own screen appears broken | the unlinked-inspector notice My Work already renders | none / none / none |
| **CL-3** | `.rtable` registers on mobile | A clipped ~45px header strip renders above the first card. `.rt-head{display:none}` is overridden by the later, higher-specificity `table.rtable tr{display:block}` | **Browser verified.** `/candidates`: `display:block`, height 45px, visible. Correctly hidden where the table uses `<thead>` (`/calls`, `/requisitions`) | Cosmetic; a stray empty strip | one CSS selector in the existing `@media (max-width:640px)` block | none / none / none |
| **CL-4** | `/job`, `/call` | No breadcrumbs and no back-link to their register, while `/requisition`, `/candidate`, `/hiring-request` have both | **Verified.** `.crumbs` count 0 vs 1; no `href="/jobs"` / `href="/calls"` | Dead end on two of the busiest records | the existing `.crumbs` component | none / none / none |
| **CL-5** | Mobile, several registers | Touch targets under 44px, quantified | **Browser verified @360/390/412.** Candidates 1,881 · Calls 426 · Jobs 325 · Requisitions 212 · Invoicing 126 · Operations 63 · Dashboard 7 | Mis-taps on phones | the `@media (pointer:coarse)` block B7 added | none / none / none |
| **CL-6** | 5 of the 6 B4 definitions | Workforce, Inspector, QA, Billing readiness and Professional are defined and reachable on `/terminology`, but have no `T_NOTE()` call at point of use. Only Hiring Request does | **Verified.** `T_NOTE()` has exactly **1** call site; `/terminology` renders the definitions | The cause of every LOW RISK confusion pair above | `T_NOTE()` — already built and already used once | none / none / none |

### Later UX / product decision (1)

| ID | Finding | Evidence | Why not B10 |
|---|---|---|---|
| **CL-7** | Register lists render every row with no pagination | **Verified @360px.** `/candidates` 936 rows → **304,604px** (the page states "935 shown") · `/calls` 186,603px · `/jobs` 78,848px · `/requisitions` 62,083px · `/schedule` 59,742px · `/invoicing` 40,457px. No overflow, no JS errors — purely length | Pagination changes how registers are navigated and was explicitly out of scope in B7 §15. **Product decision required** |

### Technical debt (1)

| ID | Finding | Why not B10 |
|---|---|---|
| **CL-8** | `accounts_on_default_password()` bcrypt scan — 34.8s / 191 accounts, cached against a fingerprint that moves on any user change (§6) | A performance/security-tooling concern, not a UX placement problem. Fixing it means changing how the check is scheduled, not how a screen reads |

### False positives closed (3)

| Suspected | Reality |
|---|---|
| "Register tables overflow the viewport on mobile" | Content inside deliberate `overflow-x:auto` scrollers; `document.scrollWidth == viewport` at every width. **Not page overflow.** Closed in B9 and re-confirmed here |
| "`/quality` is a pre-existing 30s defect" | Caused by the auditor's own probe users invalidating the default-password cache. `/quality` measures **0.898s** here. Closed in B9, re-confirmed |
| "SQLite and MariaDB disagree (14,088 vs 14,087)" | 58 SQLite-only and 57 MariaDB-only assertions, engine-conditional **by design**; both 0 failed |

---

## 9 · B10 scope note

All six B10 candidates share a shape: **existing mechanism, wrong or missing
placement, no new intelligence.** CL-1, CL-2 and CL-6 are the same sentence in
three places — *the application knows the answer and does not say it where the
user is.* CL-3, CL-4 and CL-5 are presentation defects with one-line fixes in
components that already exist.

Nothing in that group requires a new engine, a new workflow, a new permission
model, a new master, or a database, identity, KPI or Marketplace redesign.

---

## 10 · Audit hygiene

* No production PHP, JavaScript, CSS, template, schema, data, permission,
  workflow, route, KPI, search or dashboard logic was modified.
* No test was altered. No commit other than this document.
* 6 `cl_*` probe users created in the throwaway `rqv_ui` workspace — **deleted**
  (verified 0 remaining).
* Databases `cl_tA`, `cl_tB`, `cl_regress` created and **dropped**.
* Working tree verified **CLEAN** before and after.

**Phase B0–B9 is coherent and can be locked. B10 implementation: NOT STARTED.**
