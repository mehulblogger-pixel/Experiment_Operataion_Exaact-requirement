# D8 — Gap register

Findings carry a grade. **CONFIRMED** means evidence is saved under `evidence/`
and the command that produced it is re-runnable. **SUSPECTED** means the code
reads that way but the behaviour has not been reproduced. **QUESTION** means it
cannot be settled without the owner.

Severity: **P0** security / data integrity / business control · **P1** core
workflow failure · **P2** missing function, validation or important usability ·
**P3** refinement.

Nothing here has been changed in the application. This is a discovery register.

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

**This needs the owner's decision before anything is changed**, because
tightening it removes an ability three roles have today, and because CLAUDE.md
forbids granting or changing a role's permissions without asking. Two
questions, in business language:

- **Q-E.** Today a coordinator can record money received from a customer.
  Should they? (If the answer is no, who should — Accounts only?)
- **Q-F.** Today a coordinator and a branch manager can start a draft invoice,
  which Accounts then issues. Is that the intended way of working, or should
  only Accounts start one?

**Acceptance.** Re-running `evidence/books-write-authority.py` produces a table
that matches row 215 of the permission matrix, and no role writes to the books
without a right that says so by name.

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
| Branch Admin Manager | no | no | no | no |
| Operation Manager | **no** | yes | no | no |
| Coordinator | **no** | yes | no | no |
| Assistant Manager | no | no | no | no |
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

2. **Four roles can see the corrective action but not the finding that caused
   it.** SBU Head, Branch Manager, Operation Manager and Coordinator all hold
   `mod.capa.view` and none holds `mod.ncr.view`. A CAPA exists *because of* an
   NCR; being shown the remedy with the finding hidden is incoherent, and it is
   the kind of thing that reads as a broken screen rather than a permission.

3. **An inspector can see neither.** The people who meet the nonconformity in
   the field cannot record or read one. Whether that is intended is the owner's
   call (Q-B), but it is worth stating plainly: under ISO/IEC 17020 the
   inspecting staff are normally expected to raise nonconformities, and today
   they cannot.

**The permission matrix does not cover this.** `docs/02-permission-matrix.md`
has no NCR or CAPA row in its module table — the only mentions are in footnotes
about the `ncr.close` and `ncr-reopen` guards (lines 124, 143, 177). So unlike
GAP-003, this cannot be settled against the documentation; the documentation is
missing. **Per CLAUDE.md the matrix must be written before any of this is
changed** — a new audience for a module is a new permission, and that is a stop
and ask.

**Open question Q-B, restated precisely.** Three answers are needed, not one:
(a) who may **raise** a nonconformity — inspectors? coordinators? managers
only? (b) who may **close** one, given that today only a system administrator
can; (c) should everyone who can see a CAPA also see the NCR behind it?

**Acceptance.** `docs/02-permission-matrix.md` carries an NCR row and a CAPA row
with an agreed audience; the measured per-role table matches them; and closing a
nonconformity is possible for a named quality role without administrator rights.

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

## GAP-007 — two recruitment products in one repository

**CONFIRMED · P2 · scope · owner decision**

`mgh-hire/` is a **standalone white-label Recruitment & Selection product**
(2,555 lines) with its own `requisitions`, `candidates`, `interviews`, `offers`,
`stages`, `documents` tables — covering, by its own README, "Staff Requisition
through sourcing, screening, interviews, documents, salary structure, offer and
onboarding". `phpapp` contains a full recruitment module doing the same job,
and that is the one in UAT.

They share no code. Two implementations of one business process will diverge.

**Open question Q-C.** Is `mgh-hire` a live product, a prototype, or dead code?

---

## GAP-008 — 99 dispatched routes have no module

**CONFIRMED (count) · SUSPECTED (impact) · P1 · security**

565 routes are dispatched; `ops_route_module_map()` places **466** of them in a
module. `ops_module_family()` recovers some by prefix; the rest have **no
module**, and therefore no module gate and no area. A route with no module may
escape the licence switch and the per-module permission check.

**This is the highest-value Phase F task.** Each of the 99 must be tested
server-side, as each role, for whether it enforces anything.

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
