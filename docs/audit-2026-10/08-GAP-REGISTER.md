# D8 — Gap register

Findings carry a grade. **CONFIRMED** means evidence is saved under `evidence/`
and the command that produced it is re-runnable. **SUSPECTED** means the code
reads that way but the behaviour has not been reproduced. **QUESTION** means it
cannot be settled without the owner.

Severity: **P0** security / data integrity / business control · **P1** core
workflow failure · **P2** missing function, validation or important usability ·
**P3** refinement.

This began as a discovery register. Where the owner has since given a rule, the
change made is named in the finding.

---

## ERRATUM — 2026-10-18: four role names in my own evidence were wrong

`evidence/role-rights-probe.php` originally carried a **hand-typed** role list,
and four of the sixteen names did not exist: `BRANCH_ADMIN_MANAGER` for
`BRANCH_APP_MANAGER`, `ASSISTANT_MANAGER` for `ASST_MANAGER`,
`KEY_ACCOUNT_MANAGER` for `KEY_ACCOUNTS_MANAGER`, `MARKETING_EXEC` for
`MARKETING_EXECUTIVE`. A role key that does not exist falls through
`role_defaults_base()` to an empty permission set, so those four rows printed
"no right held" for everything — and read as findings.

Two of them reached this register before it was caught:

| Claimed | Actually |
|---|---|
| Assistant Manager holds no CAPA right | **Holds `mod.capa.view`** — so **five** roles see a corrective action without the nonconformity behind it, not four (GAP-004) |
| Key Accounts Manager holds no `data.credit` | **Holds `data.credit`** — it shares a `case` with the Business Development Manager. It still cannot reach the billing chain, which also needs `mod.invoicing.view` |

The script now reads the role list from the application's own `ORG_ROLES`, so it
cannot drift and a newly added role appears automatically. Every role table in
this folder was regenerated from it. Recorded here rather than quietly fixed,
because a reader needs to know which numbers were re-derived and why.

---

## CORRECTION TO THE PHASE B HEADLINE — read this first

The Phase A/B report said **95 screens are genuinely unreachable**. That number
was **wrong**, and the method that produced it was wrong.

It counted area tiles and "Find a screen" entries and called everything else
unreachable. It never followed **links on the pages themselves**. The Money
area's *Billing workspace* tile opens `/to-bill`, and that screen links onward
to `/invoices`, `/receipts`, `/receivables` and `/tally` — so all four are two
clicks from the Money menu. Counting doors without walking through them
over-reports.

`evidence/reachability-crawl.py` now crawls from the whole rail, follows every
in-page link to a fixed point, and records the shortest click path to each
screen. Corrected figures, as an administrator:

| | Count |
|---|---|
| Screens that render | 256 |
| Reached by crawling from the menu | **194** |
| Not reached | **62** |
| &nbsp;&nbsp;0 clicks (rail itself) | 15 |
| &nbsp;&nbsp;1 click | 123 |
| &nbsp;&nbsp;2 clicks | 50 |
| &nbsp;&nbsp;3 clicks | 6 |

Of the 62, most are correctly address-only: POST handlers that re-render their
parent (`*-save`, `*-add`, `tenant-*`, `cpanel-*`), public pages (`/verify`,
`/welcome`, `/agreement`), and platform-owner consoles (`/control-panel`,
`/issue-licence-*`). **GAP-002** lists the ones that are not.

The original claim is left standing in `01-APPLICATION-INVENTORY.md` with this
correction linked, rather than quietly edited away.

---

## GAP-012 — the three external portals were never audited, and the audit's universe was incomplete

> **Reduced, not closed, 18 Oct 2026.** The 77 portal routes are now inventoried
> and both security questions are answered: cross-party isolation **holds** on
> all 25 routes that read an id, and the two enforcement defects found are fixed
> (**GAP-013**, **GAP-014**). What remains is the *quality* audit of those 77
> screens — fields, terminology, 390px, accessibility. See
> `11-EXTERNAL-PORTAL-AUDIT.md` §3.

**CONFIRMED · P1 · coverage · the largest single gap in this audit**

Full account: **`00a-ERRATUM-COVERAGE.md`.** Summary here.

`evidence/routes-dispatched.txt` was built by parsing route literals out of
**`phpapp/lib/ops.php` alone**, and every count in this folder rested on it.
Routes dispatched from the other 20-odd `lib/` files, and the three portals
dispatched from `index.php`, were never in the universe.

Found while tracing KPI functions: `/analytics`, `/analytics-kpis`,
`/analytics-quality` and `/analytics-drill` all serve **HTTP 200 with real
headings** and were absent from the inventory.

Re-parsed and then **probed** (`evidence/route-list-recount.py`,
`route-recount-probe.py`):

| | Count |
|---|---|
| Real routes the list was missing | **160** |
| …rendering as a usable screen | **70** |
| False candidates (field types, provider names, columns) | 178 |
| **Corrected totals** | **≈725 routes · ≈326 screens** (was 565 · 256) |

**The part that matters:** the 160 include the **entire external-facing
surface** —

| Family | Routes | As a staff admin |
|---|---|---|
| Client portal `/portal/*` | 33 | 404 (portal off in the test workspace) |
| Vendor portal `/vendor/*` | 11 | 404 (same) |
| Professional portal `/pro/*` | 26 | 23 redirect, 3 screen |
| Analytics (TAPI) | 10 | 9 screen, 1 partial |

The 404s are **correct behaviour and good architecture**: `index.php:1024`
dispatches the portals *before* staff authentication, because "`require_login()`
means a STAFF account — a client has none and must never be pushed towards
one", and the vendor portal is "a THIRD audience… its own table
(`vendor_users`), its own session key (`vuid`), its own `/vendor` addresses".
Three audiences, three tables, three session keys, dispatched ahead of staff
login — a stronger separation than most products manage.

**But none of it was audited.** Not inventoried, not probed per external role,
no forms tested, no isolation check. Portal access runs on a **separate**
permission system (`PORTAL_PERMS`, `pcan()`, matrix fn. 20) which states that a
client sees only its own requirements and privacy-safe professional cards —
**and that was never verified.**

**What is NOT affected:** every per-screen measurement stands. The four
empty-record forms and the records they wrote, the per-role access matrix for
probed routes, the books write-authority test, the concurrency finding, the
terminology analysis, the GAP-011 fix and its guard test. **A coverage error,
not an accuracy error.**

**Why it happened:** one file was treated as the router, and the route list was
never validated against the running application. A probe that only asks about
routes it already knows cannot discover one it does not.

**Acceptance.** The three portals are inventoried and probed as each external
role; **party isolation is tested** (can a client reach another client's
reports? a vendor another vendor's assessment?); the module-gate classification
and the reachability crawl are re-run over all ≈725 routes; and the application
enumerates its own routes so the inventory cannot drift again.

**Priority within this gap:** the isolation test first. Everything else is
arithmetic; that one is about whether one customer can see another's data.

---

## GAP-001 — "Find a screen…" searches 15 items; the 167-screen index is behind a keyboard shortcut

**CONFIRMED · P1 · navigation · every role**

**Evidence.** `views/layout_top.php:244-258` and the rendered page.

The box in the left rail is labelled **"Find a screen…"**. Its filter runs over
`.side-nav .s-item` — **the 15 rail items only**:

> Dashboard · Owner home · Search records · Where the flow is broken · What to fix ·
> Sales · Marketplace · Operations · Recruitment · Quality & Accreditation ·
> Reporting · Money · Insights · Directory · Admin

Typing `invoice` into it matches **0** items and prints *"Nothing matches that."*

The full index — **167 screens**, built by `ops_nav_index()` — is loaded into
the page as `#cmdkData` and searched only by the **⌘K / Ctrl-K command
palette**. Typing `invoice` there finds *Invoice tracker → /invoicing*.

**Why it matters.** The visible control labelled "find a screen" cannot find
screens. The control that can is a keyboard shortcut a non-technical user is
unlikely to discover. This is the mechanism behind the owner's repeated UAT
reports that a feature "does not exist" — the search says so.

**Expected.** The visibly-labelled search finds any screen the person may open.

**Recommendation.** Make the rail box query the same index as ⌘K and show
matches as a result list, or relabel it "Filter the menu" and surface the ⌘K
palette as a visible control. The first is better; the second is honest.

**Acceptance.** Typing `invoice`, `receipt`, `NCR`, `delegation` into the rail
box each return at least one opening result for an administrator.

---

## GAP-002 — business screens with no door

**CONFIRMED · P2 · several modules**

From `evidence/unreachable-after-crawl-admin.tsv`, after removing action
handlers, public pages and platform consoles, these render and work but no
link reaches them:

| Screen | Heading | Module |
|---|---|---|
| `/deputations` | Deputation & site operations | Operations |
| `/contract-openings` | Contract openings | Operations |
| `/attendance-review` | Attendance review | Operations |
| `/crm-reports` | Sales dashboard | Sales |
| `/crm-templates` · `/report-templates` | Document templates | Sales / Reporting |
| `/quote-approval-rules` | Quote & report approvals | Approvals |
| `/report-ack` | Client acceptance of reports | Reporting |
| `/candidate-pool` | Candidate pool convergence | Recruitment |
| `/ratings-config` | Inspector ratings | Operations |
| `/tasks` | My tasks | Home |
| `/my-interviews` | Interviews to attend | Recruitment |
| `/raise-call` | Raise an inspection work order | Operations |

`/tasks` and `/my-interviews` are in the ⌘K index, so GAP-001 is part of their
cause. `/quote-approval-rules` is notable: the Approvals workbench was built to
be the one way in to approval configuration and does not link to this one.

**Recommendation.** Decide per screen: give it a tile, link it from its parent,
or retire it. Do not add twelve tiles — most belong as links on the screen
that owns the record.

---

## GAP-003 — the Business Director and SBU Head can open the billing chain the matrix withholds from them

**CONFIRMED · P2 · Money**

**Evidence.** `evidence/role-access-matrix.md`, `docs/02-permission-matrix.md:215`.

I first wrote this up as "the billing chain is refused to an Operation Manager
and allowed to a Coordinator", and called both readings defects. Reading the
permission matrix line by line, as the charter requires before calling anything
a defect, shows that **most of it is the documented intent**:

`docs/02-permission-matrix.md:215` — Invoicing / Receipts:

| MA | AD | BD | SBU | BM | BAM | OM | CO | AM | FIN | INS |
|---|---|---|---|---|---|---|---|---|---|---|
| Full | Full | — | — | View | — | **—** | **View** | — | Issue | — |

Measured against it:

| Role | Matrix says | Measured | Verdict |
|---|---|---|---|
| Master Admin | Full | open | ✔ as intended |
| Business Director | **—** | **open** | ✗ **more than intended** |
| SBU Head | **—** | open (holds `data.credit`) | ✗ **more than intended** |
| Branch Manager | View | open | ✔ |
| Operation Manager | — | 403 | ✔ **as intended — not a defect** |
| Coordinator | View | open | ✔ **as intended — not a defect** |
| Inspector | — | 403 | ✔ |
| Finance | Issue | open | ✔ |

So a Coordinator seeing invoices while an Operation Manager does not is **the
business rule as written**: coordinators bill the work they coordinate;
operation managers do not touch money. The owner's question Q-A is therefore
already answered by the owner's own matrix, and is withdrawn.

**What remains a defect** is the Business Director and SBU Head, who hold the
chain through `data.credit` although the matrix gives them nothing here. The
matrix's own footnote 17 records the drift rather than resolving it: it lists
the audience as *"FIN, CO, BD/SBU/BM (via data.credit), MA"* — the footnote
describes the code, the table describes the intent, and the two disagree.

**Recommendation.** Decide which is right and make them agree. If a Business
Director should see billing, change the table; if not, stop `data.credit` from
opening the module (see GAP-010, which has to be fixed anyway).

**Acceptance.** The measured per-role table matches row 215 of the matrix
exactly, cell for cell.

---

## GAP-010 — a permission for *seeing* a figure is the key to *writing* to the cash book

**CONFIRMED BY EXECUTION · P0 · Money · business control**

**Evidence.** `evidence/books-write-authority.py` and its saved output
`books-write-authority.json`, executed against a throwaway copy of a UAT
database. Nothing was run against production.

`booksui.php:225` guards every writing route in the books module with:

```php
// Everything below writes.
ops_require($canIssue || can('data.credit'), 'You cannot change the books.');
```

`data.credit` is defined at `access.php:133` as **"See the inter-office credit
figures"**, and its own help text at `access.php:460` says who it is for:
*"See the inter-office credit figures on calls / jobs. Who: coordinators &
managers who balance inter-branch work."* It is a **read** permission about a
**figure on a screen**. It is being used as a **write** permission over the
**cash book**.

**What that lets people do — executed, not inferred:**

| Role | Matrix row 215 | Record money received | Raise an invoice | Cancel an invoice |
|---|---|---|---|---|
| Master Admin | Full | yes | yes | yes |
| **Business Director** | **—** | **YES** | **YES** | refused |
| **Branch Manager** | **View** | **YES** | **YES** | refused |
| Operation Manager | — | refused | refused | — |
| **Coordinator** | **View** | **YES** | **YES** | refused |
| Inspector | — | refused | refused | — |
| Finance | Issue | yes | yes | yes |

The receipt really appeared: signing in as the Coordinator and posting the
receipt form wrote **RCP/2627/0001, amount 1, created_by "UAT Coord"** into the
`receipts` table of the throwaway database. This is not a reading of the code;
it is a record that a coordinator created.

**Correctly restricted, and worth saying:** issuing an invoice, cancelling one
and raising a credit note all need `books_can_issue()` / `books_can_cancel()` =
`finance.reconcile ‖ master` (`books.php:90-91`, `booksui.php:297, 305, 388`).
Those three were tested and refused for Business Director, Branch Manager and
Coordinator. So the breach is bounded: **draft invoices and receipts, not
issued invoices.**

**Why it matters in business terms.** Recording money received is the heart of
a receivables control. If three roles outside Accounts can record a receipt,
then "what the customer still owes us" can be changed by someone who is not
answerable for it, and an auditor cannot rely on the receipts ledger. For an
accredited inspection body this is a control weakness an assessor will look
for. It also contradicts the design principle the repository states for itself
— that Accounts owns the money.

### OWNER'S DECISION, 2026-10-18 — accepted, severity lowered to P2

Put to the owner as Q-E and Q-F, the answer was **"That's ok"**: a coordinator,
branch manager and business director recording a receipt and starting a draft
invoice is how this company intends to work. Accounts still issues.

So **no code was changed**, and the *outcome* is no longer a defect. What moved
instead was the documentation: `docs/02-permission-matrix.md` row 215 read
`BD — · SBU — · BM View · CO View`, which contradicted the code, and now reads
`Raise` for those four — may open the billing workspace, record a receipt, start
a draft; may not issue, cancel or credit-note. CLAUDE.md requires the docs and
the code to agree, and here it was the table that was wrong.

**The mechanism remains a defect, at P2.** `data.credit` — *"See the
inter-office credit figures"* — is still the key to the write gate. It is
harmless only because every money route also demands `mod.invoicing.view`
(verified: all 17 routes in the books handler are module-gated, two of them via
the `ops_module_family()` prefix fallback). A money route added without a module
gate would be writable on a read permission alone, and 99 routes already have no
module (GAP-008). The permanent fix stands: a named `finance.books.write`
granted to exactly the roles in row 215, leaving `data.credit` to do its own
job. Worth doing when the money module is next touched; not urgent on its own.

**Why it happened.** One three-key test, `finance.reconcile ‖ data.credit ‖
master`, is used in three places for three different questions: may you open
the module (`books_can()`), may you see a tile (`areas.php:219`), and **may you
change the books** (`booksui.php:225`). The first two are reasonable. The third
needs a right that means "may change the books", and no such right exists —
`data.credit` was pressed into service because it was the nearest thing to
hand.

**Recommendation — the permanent fix, not a patch.** Introduce an explicit
right for writing to the books (e.g. `finance.books.write`), grant it to the
roles the matrix intends, and use it at `booksui.php:225` in place of
`data.credit`. Leave `data.credit` doing only the job it is named for. The same
three-key test at `billable.php:150` should be reviewed with it.

~~**Q-E.** Today a coordinator can record money received from a customer. Should
they?~~ · ~~**Q-F.** Today a coordinator and a branch manager can start a draft
invoice. Is that intended?~~ — **both answered 2026-10-18: accepted.** See the
owner's decision above.

**Acceptance.** Re-running `evidence/books-write-authority.py` produces a table
that matches row 215 of the permission matrix (it now does), and — when the
mechanism is eventually fixed — no role writes to the books without a right that
says so by name.

---

## GAP-004 — the two halves of the quality loop have different audiences, and only an administrator can close either

**CONFIRMED · P1 · Quality & Accreditation**

**Evidence.** `evidence/role-access-matrix.md` for the measured 403s; the rights
below read from the application's own role presets (`role_perms()`), not
assumed.

| Role | `mod.ncr.view`<br/>see a nonconformity | `mod.capa.view`<br/>see the corrective action | `ncr.close` | `capa.close` |
|---|---|---|---|---|
| Master Admin | yes | yes | yes | yes |
| Admin | yes | yes | yes | yes |
| Business Director | yes | yes | **no** | **no** |
| SBU Head | **no** | yes | no | no |
| Branch Manager | **no** | yes | no | no |
| Branch Application Manager | no | no | no | no |
| Operation Manager | **no** | yes | no | no |
| Coordinator | **no** | yes | no | no |
| Assistant Manager | no | **yes** (see erratum) | no | no |
| Finance | no | no | no | no |
| Inspector | **no** | **no** | no | no |

**Three separate problems, in order of severity.**

1. **Nobody but an administrator can close a nonconformity or a corrective
   action.** `ncr.close` and `capa.close` are held only by MASTER_ADMIN and
   ADMIN. For an accredited inspection body that is both a bottleneck and a
   segregation-of-duties weakness: the person who administers the software also
   signs off that the fix was carried out. The application's own help text
   disagrees with its own role presets — `access.php:498` says `ncr.close` is
   for *"the responsible quality / coordinator"*, and no coordinator holds it.

2. **Five roles can see the corrective action but not the finding that caused
   it.** SBU Head, Branch Manager, Operation Manager, Coordinator and Assistant
   Manager all hold `mod.capa.view` and none holds `mod.ncr.view`. A CAPA exists
   *because of* an NCR; being shown the remedy with the finding hidden is
   incoherent, and it reads as a broken screen rather than a permission.
   *(Four in the first version of this register — see the erratum at the top.)*

3. **An inspector can see neither.** The people who meet the nonconformity in
   the field cannot record or read one. Whether that is intended is the owner's
   call, but it is worth stating plainly: under ISO/IEC 17020 the inspecting
   staff are normally expected to raise nonconformities, and today they cannot.

**The permission matrix did not cover this.** `docs/02-permission-matrix.md` had
no NCR or CAPA row in its module table — the only mentions were footnotes about
the `ncr.close` and `ncr-reopen` guards. So unlike GAP-003 this could not be
settled against the documentation, because the documentation was missing.

---

### RESOLVED (part 1) — the owner's rule, 2026-10-18

> **"Yes only Quality or Technical Head can close it."**

Neither position existed in the system, so two roles were added:
**`QUALITY_HEAD` ("Quality Head")** and **`TECHNICAL_HEAD` ("Technical Head")**,
each holding `mod.ncr.view/edit`, `mod.capa.view/edit`, `ncr.close`,
`capa.close`, `idems.audit.view` and a read of the work the finding came off
(calls, jobs, inspection reports, complaints). Scope **ALL / ALL** — a
nonconformity raised in one branch is the company's nonconformity. Neither role
is in `MGMT_ROLES`, so no operational widget leaks to them: quality judges the
work, it does not run it.

Changed together, as CLAUDE.md requires: `phpapp/lib/access.php` (the roles),
`docs/02-permission-matrix.md` (a **Quality roles** table, an NCR row, a CAPA
row and footnote 20 — all three generated from `role_perms()`, not hand-typed),
`docs/01-roles.md` (both role entries) and
`phpapp/tests/test_quality_head_roles.php` (68 assertions, including one that
fails if the roles are ever quietly widened).

**Deliberately not done:** the accreditation registers (equipment, competence,
impartiality, confidentiality, internal audits, data control) were **not**
granted to the quality roles. A quality head plausibly owns them; nobody has
said so, and granting ten modules unasked is how a role becomes an
administrator by accident. A test asserts they are still absent.

**Still open, and worth one line from the owner:**

- **Q-B1.** Who may **raise** a nonconformity? Today nobody below Business
  Director can even open the register, and an inspector — the person who meets
  the problem — cannot. Recommendation: inspectors and coordinators may raise,
  quality closes.
- **Q-B2.** Should the five roles who see a corrective action also see the
  nonconformity behind it? Recommendation: yes, read-only. It is one loop.
- **Q-B3.** MASTER_ADMIN and ADMIN still hold the closing rights, because they
  resolve to the whole permission list. Strictly, *"only Quality or Technical
  Head"* would remove ADMIN's. That is a removal, not an addition, so it was not
  done unasked.

**Acceptance (met for part 1).** The matrix carries an NCR row and a CAPA row;
the measured per-role table matches them; and closing a nonconformity is
possible for a named quality role without administrator rights.

---

## GAP-011 — the Quality menu offered five roles a door that answered 403 — FIXED

**CONFIRMED BY EXECUTION · was P1 · Quality · fixed 2026-10-18**

Found while implementing the owner's quality rule, by asking a question the
first reachability crawl had not: *not "is there a door", but "does the door
open for the person being shown it".*

`areas.php:134` offered the **Nonconformity workspace** tile to anybody holding
`mod.ncr.view` **or** `mod.capa.view`, and always pointed it at `/issues` (or
`/ncr`) — both module `ncr`. Five roles hold `capa` and not `ncr`. So the tile
was a visible, inviting door onto a refusal.

Driven before the fix (`evidence/quality-door-check-before.py`):

| Role | Tile offered | `/issues` | `/ncr` | `/capa` |
|---|---|---|---|---|
| Master Admin · Business Director | yes | opens | opens | opens |
| **Branch Manager** | **yes** | **403** | **403** | opens |
| **Operation Manager** | **yes** | **403** | **403** | opens |
| **Coordinator** | **yes** | **403** | **403** | opens |
| Inspector · Finance | no | 403 | 403 | 403 |

And after (`evidence/quality-door-check.py`), reading each role's tile href out
of its own rendered page and following it:

| Role | Tile now says | Points at | And it |
|---|---|---|---|
| Master Admin · Business Director | Nonconformity workspace | `/issues` | **opens** |
| Branch Manager · Operation Manager · Coordinator | Corrective actions | `/capa` | **opens** |
| Inspector · Finance | not offered | — | — |

**The fix sends the tile where the person can actually go, and names it for what
they will find.** Nobody gained or lost a right: gating the tile on
`mod.ncr.view` instead would have been the obvious move and would have taken
away a CAPA reader's only link to `/capa`. The open-nonconformity badge is also
withheld from a CAPA-only reader — a count of records they cannot open is an
errand they cannot run.

**Why this matters beyond one tile.** This is a measurable class of defect, not
a one-off: *a tile whose visibility condition is weaker than its destination's
gate.* Phase E should test every tile in every area this way, because the
reachability crawl cannot see it — the crawl runs as one role and asks only
whether a link exists.

**Guarded.** `tests/test_quality_head_roles.php` walks every role, and for each
one that is offered the tile, asserts it holds the module the destination
requires. That assertion would have failed on the original code.

---

## GAP-005 — a Business Director is redirected away from Users, Roles and Settings; a Branch Manager is not

**CONFIRMED (behaviour) · SUSPECTED (defect) · P2 · Admin**

| Screen | Director | Branch Mgr |
|---|---|---|
| `/users` | **302** | **open** |
| `/access` | **302** | 403 |
| `/settings` | **302** | 403 |

A Branch Manager can open the user register; the Business Director above them
cannot. Suspected role-hierarchy inversion, same class as GAP-003.

---

## GAP-006 — refusals come in two shapes, and one is indistinguishable from "record not found"

**CONFIRMED · P2 · application-wide**

`ops_require()` flashes a message and issues a **302**; `access_deny()` returns
a **403**. Both mean "you may not". In the role probe, **130–241 routes per
role** answered 302 — a mixture of genuine refusals and detail screens waiting
for a `?id=`. From outside, the two are identical.

**Consequences.** (a) A tester cannot tell a refusal from a dead link — the
owner has reported both as "nothing happens". (b) Any audit of *what a role may
reach* is approximate until they are distinguished.

**Recommendation.** One shape per meaning: 403 with an explanation for a
refusal (`ops_access_notice()` already does this well), 404 for a record that
does not exist, 302 only after a successful action.

---

## GAP-007 — `mgh-hire/` is a second recruitment product — NARROWED by the owner's correction

**CONFIRMED · P2 · scope · owner decision**

**This finding was originally stated far too widely, and the owner corrected it
on 2026-10-18.**

I wrote that EXAACT contained **four implementations of matching people to
work** — Recruitment, Deputation, Marketplace and `mgh-hire/` — and asked which
was the business. **Three of those four are one coherent design:**

- **Recruitment** is the **internal** branch: hiring into your own
  organisation, and supplying people to clients.
- **Marketplace** is the **external** branch: when a position is open and no
  suitable manpower is available internally, you search the market **by
  location** for somebody suitable, subject to a marketplace subscription.
- **Deputation** is not a module at all — it is an **engagement basis**
  (man-day or man-month), alongside **direct on-roll** (`roll_type` = OWN).

And the two branches are **joined in code**, which I had claimed they were not:

| Join | Where |
|---|---|
| A requisition automatically sees the ranked marketplace pool, scored on **location** (`base_city`, `preferred_locations`, `pan_india`), discipline, skills, work type, availability and verification tier, with **explainable factors** | `recruit_pro_pool()` / `recruit_pro_fit_score()`, `lib/recruit.php:697-780` |
| A marketplace professional is linked to a candidate row — this is what `/candidate-pool` ("candidate pool convergence") is for | `candidate-link-pro` / `candidate-unlink-pro` |
| An **awarded** requirement becomes a **PENDING billable event** in the core ledger, which finance then attests into an invoice — *"We do NOT build a new invoicing engine; we hand the award to the existing one"* | `lib/connect_bridge.php` |
| The engagement basis sets the billing unit — visit · day · engagement | `connect_bridge_unit()` |

**What remains, and it is the original question properly scoped:**
**`mgh-hire/`** is a standalone recruitment product (2,555 lines, its own
`requisitions`, `candidates`, `interviews`, `offers`, `stages`, `documents`
tables) with **no shared code** with `phpapp`'s recruitment module, which is the
one in UAT. Two implementations of one business process will diverge.

**Open question Q-C.** Is `mgh-hire` a live product, a prototype, or dead code?
Note that `/product-package` offers four product packs — TPIA, Technical
Staffing, Recruitment, Recruitment In-house HR — so the answer may be that
`mgh-hire` was an earlier attempt at the pack that `phpapp` now serves.

**Why I got it wrong:** I inferred four competing implementations from the
*absence of a link in the navigation*, having never read `recruit_pro_pool()`,
`connect_bridge.php` or `candidate-link-pro` — all in files my route inventory
never covered (`00a-ERRATUM-COVERAGE.md`). I graded it **"CONFIRMED
(absence)"**, and that was the wrong grade: **absence of evidence in an
incomplete map is not evidence of absence.** Every "CONFIRMED (absence)" in
this folder should be read with that in mind; they are now the gradings I trust
least.

---

## GAP-008 — 200 dispatched routes have no module gate — TESTED, no live exposure found

**CONFIRMED · P2** (was P1, before it was tested) **· security · defence in depth**

Full write-up: **`06-DATA-AND-PERMISSION-AUDIT.md` §2–3.** Summary here.

**The count was wrong, and it was wrong in the unsafe direction.** This read
"99 routes have no module", from `ops_route_module_map()` having **466 keys** —
which I took for "466 of the 565 dispatched routes are gated". It is not the
same thing: **140 of those keys are not dispatched routes at all** (`lead`,
`lead-new`, `lead-move` …), and many dispatched routes are absent from the map.
Resolved properly, exactly as the dispatcher does
(`evidence/ungated-routes-list.php`):

| | Count |
|---|---|
| Dispatched routes | 565 |
| Module from the map | 326 |
| Module recovered by the prefix fallback | 39 |
| **No module at all** | **200** — twice what was reported |

**Then it was tested, which is the point of the finding.** Inspector and Finance
(32 and 59 of 256 screens) were used as probes, because "no gate" only matters
if the screen opens for someone who should not be there
(`evidence/ungated-triage.py`, `evidence/ungated-deep-probe.py`):

| | Count |
|---|---|
| Refuse a least-privileged role anyway — the handler checks for itself | **158** |
| Open for one probe | 17 |
| Open for both | 8 |
| Personal screens (the signed-in person's own record) | 8 |
| Public by design | 9 |

All 25 that open were then read for the only two things that matter — can they
**change** anything (every form target on the page) and can they **see** another
person's records (data-table row count).

**Result: no reachable hole.** Every ungated route an inspector can open is a
read-only explainer, a hub showing only tiles they can use, their own signature,
a local phrase expander with no external call, or the public marketplace
storefront whose form is a GET search. **Zero data rows on every one; no write
reached a business record.** `approvals-setup` answers an inspector with the
heading *"Nothing here is yours to change"* and simply omits the editable half —
better behaviour than most systems manage. Finance's four writable screens are
all reconciliation screens, which is what `finance.reconcile` is for.

**Severity lowered to P2, and the risk restated honestly.** The weakness is
about tomorrow, not today: protection rests on 200 handlers each remembering to
check, 158 of which do, and a route added next month inherits nothing. The one
place it already bites is the money module, where the write gate accepts
`data.credit` (GAP-010) and is safe *only* because every money route also
carries a module — two of the seventeen getting theirs from the prefix fallback,
a rename away from losing it.

**Recommendation — a guard rail, not a repair.** Not "add a module to 200
routes"; many have none to add. Instead make the absence deliberate: an explicit
allow-list of routes intended to be module-free (public, personal, hub, action
handler), plus a test that fails when a dispatched route is in neither the module
map nor that list. That stops a new ungated route being added by accident, which
is the actual risk.

**Acceptance.** Such a test exists and passes; re-running
`evidence/ungated-triage.py` shows no route in OPEN-WIDE or OPEN-ONE that can
post to a business record or list another person's data.

---

## GAP-022 — a voucher line accepted any quantity and any date

**CONFIRMED BY EXECUTION · P2 · Professional portal (billing) · FIXED 19 Oct 2026**

A marketplace voucher line is the document a client is asked to approve and pay.
`/pro/voucher` passes `$_POST` straight into `connect_engv_add_line()`, which
accepted every wrong value put to it: `units = -5`, `0` and `abc` were silently
corrected to **1** and reported as *"Day added."*; `units = 999999` put **Rs
2,499,997,500** on one line and `1e9` put **Rs 2.5 trillion**; a negative
expense was silently zeroed; and `work_date` of "banana", "2026-13-45",
"1899-01-01" or "2099-01-01" was stored verbatim. One voucher reached **Rs
2,502,500,020,000**.

**Two qualifications, or the finding is overstated.** It is **not** a
script-injection hole -- both voucher views render `work_date` through `e()` and
truncate it to ten characters. And **none of it bills anyone by itself** -- a
voucher goes DRAFT -> SUBMITTED -> APPROVED with a human approving on the client
side. The finding is that a claim document should not be able to say those
things, and that a professional should not be told "Day added." when the system
quietly changed what they typed.

**Fixed:** the quantity must be a number above zero and at most 366; an expense
must be non-negative; the day must be a real date, inside an explicitly declared
period where there is one and otherwise within a plausibility window (five years
back, one year forward). A half day, a full month on one line and a line with no
day at all are still accepted.

**And the professional is now told.** `/pro/voucher` discarded the function's
return value, so every refusal it already produced -- including the
long-standing *"Add lines only while the voucher is a draft."* -- was invisible
to the one person filling the voucher in. The staff desk always captured it.
Same lost-message pattern as GAP-015.

My first rule was too strict and an existing test caught it: it derived the
allowed dates from `period_label`, which is defaulted to the current month when
no label is given, so it refused a professional filing last month's days --
ordinary practice. See `11-EXTERNAL-PORTAL-AUDIT.md` §2.9. **A validation rule
that is too strict is a defect too.**

---

## GAP-021 — the marketplace's own posting form refused in silence

**CONFIRMED BY EXECUTION · P2 · Client portal · FIXED 19 Oct 2026**

`/portal/hire` is where a client posts a manpower requirement -- the front door
of the marketplace. Its handler reads `elseif (trim($_POST['title']) !== '')`,
so an **empty title matched no branch at all**: the page re-rendered, nothing
was saved and **nothing was said**. The client types their need, presses *Post
requirement*, the page comes back looking the same, and they believe it is
posted.

Its four sibling forms all answer plainly. This one -- the one that earns
marketplace revenue -- did not. The codebase already states the principle, in
`portal_request_create()`: *"A request nobody sees is worse than no portal at
all -- the client believes they have asked, and nothing happens for a week."*

**Fixed:** *"Give the requirement a one-line title -- for example 'Welding
inspector, Ahmedabad, 20 man-days'. Nothing was posted."*, tagged as a refusal.

---

## GAP-020 — the professional portal had no protection against forged requests

**CONFIRMED BY EXECUTION · P1 · Professional portal · FIXED 19 Oct 2026**

All three portals are dispatched **before** `require_login()`, so nothing
downstream applies the application's CSRF gate. `portal.php` and `cvp.php` each
carry their own -- stamping a token into every POST form and refusing a POST
without it. `connect_pro.php` carried **neither**.

**Proven by execution:** a POST to `/pro/profile` with no token at all and
`Referer: https://evil.example/attack` returned **HTTP 200** and rewrote the
professional's record.

Every POST route there was forgeable. A signed-in professional who opened a
hostile page in another tab could have their rates and contact details
rewritten, their documents deleted, their applications withdrawn, their contact
details revealed to a client, their subscription changed -- and worst, a voucher
marked **"payment received"**, the handshake that releases the deliverable to
the client and settles the engagement. Forging it releases the work product for
a payment the professional never received.

**Fixed** by mirroring the sibling portals: `connect_pro_view()` buffers and
stamps every POST form; `connect_pro_csrf_or_die()` refuses any POST without a
valid token with a 400 and a plain-words page. Collateral damage checked, not
assumed -- the four `/pro/tax-*` endpoints the screens fetch are GET, all 43
portal screens still render, and a real browser was driven through the profile
form to confirm a legitimate save still works.

Found by chasing two forms an earlier probe had **skipped**. A skip is not a
pass.

---

## GAP-019 — the professional portal's header failed colour contrast

**CONFIRMED · P3 · Professional portal · FIXED 19 Oct 2026**

`tools/b1-contrast-check.js` has measured the staff application all along, but it
signs in as staff and can never reach the three portals, so they had never been
measured. Running the same algorithm against them found one failure: **"Sign
out" in the professional header at 4.33:1**, where WCAG 1.4.3 asks 4.5:1.

Not the brand colour's fault -- the teal gives white text **4.94:1**, already
compliant. `opacity:.9` pulled it to 4.33. **Fixed** by dropping the fade and
leaving the palette untouched. Everything else in all three portals passes.

See `11-EXTERNAL-PORTAL-AUDIT.md` §2.7 for the 1.07:1 reading my first run
produced -- a gradient background the algorithm could not see -- and note that
`b1-contrast-check.js` shares that blind spot.

---

## GAP-018 — customer-facing screens hardcoded the words a tenant chooses

**CONFIRMED · P2 · Client and professional portals · FIXED 19 Oct 2026**

`lib/terms.php` lets a tenant pick its vocabulary, and the presets differ:
`report` is "Inspection Report" under **inspection** but plain "Report" under
**manufacturing** and **trading**, where `job` becomes "Operation" and
"Consignment".

Four customer-facing screens hardcoded those nouns, so a **trading** tenant's
own client would read a heading saying *"Inspection report"* and a column headed
*"Visits"*. On an outside-facing screen that tells the customer the software was
built for somebody else.

**Fixed** in `portal/voucher.php`, `pro/voucher.php`, `portal/call.php` and
`portal/complaint_new.php` -- 10 places, via `T()` / `Tl()` / `TP()`.

**Deliberately not changed:** the first scan counted 22 hardcoded nouns; reading
all 22 in context cut it to 10. The rest are **trade names** in the manpower
marketplace -- *"inspector, welder, NDT technician, site engineer"* -- which are
occupations a client is hiring, not the tenant's word for its own people.
Replacing those would have been wrong and would have damaged the marketplace
wording the owner described. A test asserts they survived.

---

## GAP-017 — 113 fields had a label that was never attached to them

**CONFIRMED BY EXECUTION · P2 · All three portals · FIXED 18 Oct 2026**

Across the portals the pattern was `<label>Name</label><input name="name">` --
adjacent, never associated. No `for`, no wrapping. It looks correct and is
invisible to a screen reader, and tapping the label does not focus the field.
It matters most on the professional portal, used on a phone on a site.

**Fixed.** 113 pairs wired with matching `for`/`id` derived from each field's own
name; the four with a PHP-built name carry an explicit `for` or `aria-label`.
The convention already existed -- 18 labels used `for=` -- it had just not been
applied consistently. See `11-EXTERNAL-PORTAL-AUDIT.md` §2.6 for the regression
my first transform introduced (a label reading "Rating" pointed at the name
field) and how the browser caught it.

---

## GAP-016 — in the client portal a refusal arrived wearing the success colour

**CONFIRMED · P2 · Client portal · FIXED 18 Oct 2026**

`views/portal/top.php` hardcoded `class="pmsg ok"`, so every message was painted
as a success. Of 42 message sites at least nine are refusals or failures --
*"That deployment is not yours."*, *"That payment is no longer available."*,
*"Your access here does not include…"* -- plus the failure branch of several
`$ok ? 'Done.' : $msg` pairs. The CSS defined `.pmsg.err` and never used it here.

**Fixed.** A tagged channel, plus `role="alert"` on all three renderers so a
message is announced and not merely seen. The vendor portal shares the
hardcoded class but **not** the defect (its only flash is a genuine
confirmation, and a refusal there goes to its own page); its renderer was made
tag-capable and nothing else.

---

## GAP-015 — the professional portal wrote messages nobody ever read

**CONFIRMED BY EXECUTION · P1 · Professional portal · FIXED 18 Oct 2026**

`lib/connect_pro.php` wrote `$_SESSION['pro_flash']` in **ten** places. Every one
is the outcome of money or reputation: a payment, a subscription, a credit pack,
a rating report -- including *"That payment is no longer available."*, the
payment-verification result, and the error branch of starting a subscription.

**No view read that key.** Proven by rendering a real `/pro` screen with the
message set (`evidence/pro-flash-lost.php`): it does not appear, and it was
never cleared, so it accumulated in the professional's session.

A professional whose payment failed was therefore told **nothing at all** --
redirected to the plans page and left to guess. The client and vendor portals
both rendered their channel; this one was missed.

**Fixed.** `connect_pro_flash_set()` / `connect_pro_flash_take()` with a
severity, rendered in `views/pro/top.php` with the `.msg.err` / `.msg.ok` styles
that already existed there. All ten writes go through the helper, each tagged by
its own outcome flag.

---

## GAP-014 — the portal assistant answered questions the user had been refused

**CONFIRMED BY EXECUTION · P1 · Client and vendor portals · FIXED 18 Oct 2026**

Both portals offer an **Assistant**. Its context was built by
`cvp_ai_scope_context()`, which scoped by **company** and never consulted the
signed-in user's own permissions.

A contact on the **ACCOUNTS** preset — *"invoices only"*, refused
`/portal/reports` and `/portal/issues` at the front door — was handed
`IRN/PROBE/0001 · Pressure vessel weld inspection · result: FAIL · release:
NOT_RELEASED`, plus every nonconformity's reference, severity, status and due
date, by asking the assistant instead.

A failed, unreleased inspection result is exactly what a client's quality
manager withholds from a purchasing contact. The per-colleague permission
control the product advertises was bypassable by typing a question.

**Not a tenant leak** — every query stayed bound to the one company. The control
it defeated was the one *inside* the client company, which is the control the
portal team editor exists to provide.

**Fixed.** `cvp_ai_scope_context()` now takes the audience's permission test —
the same signature `cvp_action_centre()` two functions away already used
correctly — and includes each section only on the matching right. The permission
guard runs ahead of the availability guard. The model is told its context may be
a subset. Both menus hide the Assistant from a user who can read nothing through
it, and both screens say what it can and cannot see.

**Evidence.** `evidence/portal-assistant-scope.php` (prints the leak before the
fix, nothing after it) · `phpapp/tests/test_portal_perm_enforcement.php`.

---

## GAP-013 — "Ask for a new job" was offered to the administrator and never enforced

**CONFIRMED BY EXECUTION · P2 · Client portal · FIXED 18 Oct 2026**

`/portal/request` had no permission check. The menu link was correctly hidden
from a user without the right; the **address** was reachable by anyone signed in.
A contact on **ACCOUNTS** (*"invoices only"*) or **READONLY** (*"sees
everything, changes nothing"*) could raise a job request in the company's name.

Three screens **invited them to** — the invoices screen, which an Accounts-only
contact can open, carried *"say so on the request form"* with the link
unconditional.

This is the precise bug `lib/portal.php:1745` says the file exists to avoid:
*"Refuse rather than hide: hiding a link but leaving the address reachable."*

**Fixed** in three places: `portal_need()` on the route; the same check inside
`portal_request_create()`, so the write defends itself whichever door calls it;
and all three invitations behind `pcan('request')`. Ten of the eleven client
portal permissions were already enforced correctly — this was the one.

**Evidence.** `evidence/portal-perm-enforcement.php` ·
`phpapp/tests/fixtures/portal_routes_gated.tsv` (a lost gate now fails by name).

---

## GAP-009 — `/hierarchy` and `/users` both render "Organisation & people"

**CONFIRMED (duplicate heading) · SUSPECTED (duplicate function) · P2 · Admin**

Two reachable routes render the same heading. Which owns the person record is
not documented. Phase C must establish the authoritative source before either
screen is changed.

---

## Questions for the owner

| Id | Question | Why it blocks |
|---|---|---|
| ~~Q-A~~ | ~~Should an Operation Manager raise and see invoices? Should a Coordinator?~~ **Withdrawn** — answered by the owner's own permission matrix, row 215: CO yes (View), OM no. | — |
| **Q-E** | Today a coordinator can **record money received** from a customer. Should they, or should that be Accounts only? | GAP-010 · P0 |
| **Q-F** | Today a coordinator and a branch manager can **start a draft invoice** for Accounts to issue. Is that the intended way of working? | GAP-010 · P0 |
| **Q-B** | Who may raise a nonconformity — inspectors and coordinators, or managers only? | GAP-004 |
| **Q-C** | Is `mgh-hire` live, a prototype, or dead? | GAP-007; also decides whether it is audited further |
| **Q-D** | Is a non-production workspace available on the live host? | Everything is currently "verified locally" |
| **Q-B1** | Who may **raise** a nonconformity? Today nobody below Business Director can open the register, and an inspector cannot. *Recommend: inspectors and coordinators raise, quality closes.* | GAP-004 |
| **Q-B2** | Should the five roles who see a corrective action also see the nonconformity behind it? *Recommend: yes, read-only.* | GAP-004 |
| **Q-B3** | Should the two administrator logins keep the right to close a nonconformity? Strictly *"only Quality or Technical Head"* would remove it. | GAP-004 |
| **Q-G** | Who should see the **vendor register** — the people who write reports against a vendor's work, the people who pay the vendor, or both? Today it is the former; **Finance is the one role refused**, which looks backwards. | `06-DATA-AND-PERMISSION-AUDIT.md` §4 |
