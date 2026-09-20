# EXAACT — Programme Change Ledger

## What existed when Phase 1 began, what changed, and what it is worth

**Covers:** Phase 1 → Phase 6 Batch 3 (corrective) · 13 Sep 2026 → 20 Sep 2026
**Branch:** `claude/testing-branch-setup-0gqe8n` · **Head:** `ea96819`
**Baseline audited:** Phase 0 (`docs/phase0/`), commit `d1af5b0`, 13 Sep 2026

---

## 0 · How to read this, and how much to trust it

Every "before" statement in this document was **measured**, not inferred — Phase 0
and each phase's pre-implementation audit ran probes against a booted
application and read the database, and each finding carries `file:line` evidence
in the source documents. Where a claim is only partly proved, it says so.

Three honesty markers are used throughout:

| Marker | Meaning |
|---|---|
| **LOCKED** | Owner-accepted. Evidence recorded. Not to be changed without a new instruction. |
| **PENDING** | Implemented and evidenced, but the owner has not accepted it yet. |
| **OPEN** | Known, recorded, deliberately not done. |

**Nothing in this document claims production deployment or customer UAT.** All
evidence is test, mutation, reconciliation, security, adversarial and
real-HTTP/browser evidence on the development branch. Deployment is a separate
exercise with its own evidence.

---

## 1 · The starting point, measured

At Phase 0 the supplied working codebase measured:

| | |
|---|---|
| PHP | **154,873 lines** |
| Library files | **221** |
| View files | **394** |
| Test files | **444** |
| Application routes | **435** |
| Operational screens | **266** |
| Production database | MySQL / MariaDB |
| Automated test engine | SQLite **only** — production engine was not exercised by the suite |

This was **not** a prototype. It was a large, working, multi-tenant operations
platform in use. The programme was therefore never "build the product"; it was
**"make the commercial and safety boundaries of an existing product actually
hold, without breaking the parts that work."**

That distinction matters for every row below: almost nothing here is a new
feature. Most of it is *a rule that was assumed, made real.*

### The five findings that decided the programme

Phase 0 reduced the whole audit to five findings. Each became a phase.

| # | Finding | Severity | Became |
|---|---|---|---|
| **F1** | **Entitlement failed OPEN.** A tenant whose module ceiling was never written was entitled to *everything* (`lib/licence.php:122-152`) | Critical | Phase 1 |
| **F2** | **Marketplace was not a sellable module.** It defaulted **ON** for every cloud tenant via a plain setting, bypassing the entitlement ceiling. A "Marketplace + Operations + Reporting" customer was **not expressible** | Critical (commercial) | Phase 1 M9 |
| **F3** | **Public routes bypassed the module gate.** The gate ran at one chokepoint invoked from the *last line* of the front controller. `lib/careers.php` had **zero** entitlement references — a tenant that lost the `hr` module kept serving jobs and collecting applicants | High | Phase 1 M8 |
| **F4** | **One human existed as up to eleven records**, across three identity mechanisms that could not see each other. Five of those tables had **no uniqueness constraint at all** | High | Phase 6 |
| **F5** | **Multi-source fulfilment did not exist, and the first hire closed the requisition.** A requirement for 20 welders could not be fulfilled as specified | High | Phase 2 M3 + Phase 4 |

---

## 2 · The change ledger

### Phase 1 — The commercial boundary · **LOCKED**

*Who has paid for what, and who may see whose data.*

| Problem that existed | What was resolved | How it eases the business | USP |
|---|---|---|---|
| **Entitlement failed open (F1).** An empty ceiling meant "everything is allowed". This was the root cause of a recruitment-only workspace displaying the full ERP | One entitlement chain, one chokepoint, seven states, and the rule **UNKNOWN = DENY**. A module is off unless it was bought | A customer sees the product they bought, not a warehouse of screens they must ignore. Sales can stop apologising for the demo | **Fail-closed by construction.** Most SaaS bolts entitlement on as a UI filter; here a missing answer is a refusal, not a permission |
| **Marketplace could not be sold (F2).** It was a setting, defaulting ON, outside the ceiling — so it could not be sold, withheld, suspended or audited | Marketplace/Connect became the 7th commercial module, inside the same ceiling as the rest | Any combination of modules is now a priceable package. New plans need no code | **The plan catalogue is data, not code.** A new commercial package is a configuration decision |
| **Public routes bypassed the gate (F3).** Careers pages and application intake ran with no entitlement check and wrote `candidates` rows | Enforcement extended to routes, actions, portals, exports, public routes and background jobs | A lapsed or downgraded customer stops collecting data they are no longer entitled to collect — automatically | **The boundary holds where nobody is signed in**, which is exactly where boundaries usually leak |
| **Master privilege was treated as entitlement.** An administrator could reach modules the tenant had not bought | M10 separated the two: privilege says *what you may do*, entitlement says *what this company bought* | Support staff cannot accidentally demonstrate, or enable, an unsold module | **Two independent questions, asked separately** — an audit-grade distinction |
| **Tenant isolation was assumed.** | M13 attacked it: **3 critical cross-tenant paths found and fixed.** Two independent barriers now: separate databases *plus* workspace binding | One customer's data cannot surface in another's screens | **Structural isolation** — one database per tenant, so isolation is not a `WHERE` clause somebody can forget |
| **Object authorization was assumed.** | M14 attacked it: **7 IDORs found and fixed**, including a cross-branch delete | A record number stops being a skeleton key | **A record id is never a credential** — stated as a rule and tested as one |
| **The suite never ran on the production engine.** SQLite only | M15 ran everything on MariaDB: **3 defects only production could reveal** | Defects are found here rather than by a customer | **Dual-engine truth**, with MariaDB authoritative |
| **Nothing had been exercised over real HTTP.** | M16 deployed and drove it over real HTTP: **1 entitlement bypass found and fixed** | The thing that ships is the thing that was tested | **Verified through the front door**, not only through function calls |

> **Every milestone that attacked the system found something.** That is the
> strongest single sentence in the Phase 1 record: the boundary was not assumed,
> it was tested until it stopped yielding.

**Evidence:** SQLite **8,267** passed / MariaDB **8,268** passed, 0 failed.

---

### Phase 2 — Organisation, vocabulary and the requirement · **LOCKED**

| Problem that existed | What was resolved | How it eases the business | USP |
|---|---|---|---|
| **Ten vacancies did not behave as ten vacancies (F5).** Accepting *one* candidate set `status='HIRED'` on the whole requisition, whatever the quantity. Reproduced: a requisition for five closed after the first hire, with four seats still open | The requisition now closes when it is actually filled. Crucially, the audit found **every individual hire was already being recorded** — so the fix read existing data correctly rather than adding a new ledger | A recruiter stops re-opening requisitions by hand, and headcount reporting stops under-counting demand | **The fix was a correction of interpretation, not new machinery** — the cheapest kind of fix to maintain |
| **Three department vocabularies, no department master, no CRUD screen, and two uncontrolled ways for new values to appear.** One of the four reproduced defects **silently bypassed a configured approval chain** | One canonical department, one controlled vocabulary engine, one screen | A typo in a department name can no longer route an approval to nobody | **Controlled vocabulary as a safety feature**, not a tidiness feature — the link to approvals is the point |
| **Nothing distinguished "we would like to hire" from "this is approved, start sourcing."** A requisition was created directly, already `OPEN` — a status whose own label read *"Open (approved, sourcing)"* | A hiring-request layer: `hiring_requests`. **35 of the 45 fields it needed already existed** and were reused, so it added one table and one column | A manager's wish and an approved mandate are different objects, so cost is committed only once someone with authority says so | **Demand is separated from authority** — the control most recruitment tools leave to convention |

---

### Phase 3 — Approval, authority, SLA · **LOCKED**

| Problem that existed | What was resolved | How it eases the business | USP |
|---|---|---|---|
| Recruitment had no approval boundary of its own; the approval engine existed but recruitment did not use it | The hiring request was connected to the **existing** entity-agnostic approval engine. No second approval system was built | One approval mechanism to understand, configure and audit across the platform | **Reuse over rebuild**, enforced as a rule |
| Approval authority was not modelled by dimension; delegation was informal | A configurable matrix (entity + department / BU / grade / position + value band), deterministic precedence, and explicit delegation with scope and delegator authority | "Who signs off ₹X for a Grade-4 welder in Kochi?" has one answer the system can state | **Deterministic precedence** — no ambiguity about which rule wins |
| No SLA, no escalation, no reminder control, no inbox | SLA per level, reminders, escalation targets, notification gating | Work stops sitting silently in somebody's queue | **Time is a first-class dimension of the approval**, not a report written afterwards |
| Executable state was implicit | An end-to-end state matrix: every combination of request state × re-approval state that can produce an invalid business state, with the service that refuses it | An approved-then-materially-changed request cannot quietly keep its old approval | **Re-approval is modelled**, which is where most workflow tools quietly fail |

This phase is also the clearest demonstration of the programme's method: it went
through **fifteen numbered corrections**, each opened by an adversarial audit of
the previous one, and several of those audits found defects *in the correction
itself*. Nothing was declared complete because it compiled.

---

### Phase 4 — Multi-source fulfilment · **LOCKED**

| Problem that existed | What was resolved | How it eases the business | USP |
|---|---|---|---|
| **No per-source allocation quantity existed anywhere (F5).** `sourcing_model` was a single `VARCHAR(24)` driving **cost arithmetic only**. The marketplace awarded a single application even when `positions > 1` | `requisition_allocations` — one row per source's promise against one requirement — plus an append-only event ledger and one nullable column on `candidates` recording which source a person arrived through | "Twenty welders: ten in-house, five agency, five freelance" is **one** requirement with three promises — so the business is never accidentally committed to forty people | **One requirement, many sources, no duplication.** The moment sourcing creates a second requirement, headcount doubles; this is the control that prevents it |
| Sources would normally require a new master | The vocabulary **extends the existing configurable lookup**; source entities are **existing records** (supplier, marketplace requirement, professional, inspector). No new master of anything | A workspace adds its own sourcing channel without a developer | **Extends what exists rather than adding a parallel model** |
| Over-allocation under concurrency was untested | The gate made the races actually race, and **found a real over-allocation defect** that the first version of the tests could not see | Two recruiters allocating at the same instant cannot over-promise headcount | **Concurrency proved with real OS processes on a wall-clock barrier**, not simulated |

---

### Phase 5 — Recruitment KPI & performance · **LOCKED**

| Problem that existed | What was resolved | How it eases the business | USP |
|---|---|---|---|
| Recruitment numbers were computed in more than one place, and the pre-implementation audit **measured a KPI defect** and found the stage ledger "not trustworthy yet" | **One authoritative recruitment KPI engine**; the screens read it rather than each computing their own answer | Two screens can no longer show two different truths about the same funnel | **One number, one owner.** Dashboards become reports of record instead of opinions |
| Built on nothing? No — on the platform's existing KPI / TAPI / Command Centre architecture | Extended, not replaced | Consistent with every other dashboard in the product | **No parallel analytics stack to maintain** |

**Evidence:** battery 145/0 both engines · SQLite **12,252** / MariaDB **12,255**,
0 failed · mutation **39 of 39 caught**, 0 survivors.

---

### Phase 6 — Identity · Batches 1–3

*The hardest phase, and the one still in motion. This is finding F4: one human
existing as up to eleven records across three mechanisms that could not see each
other.*

#### Batch 1 — Identity write safety, authority, scope · **LOCKED**

| Problem that existed | What was resolved | How it eases the business | USP |
|---|---|---|---|
| **Opening a list created people.** Seventeen ordinary screens, *just by being read*, could invent staff records and link logins to them | Reading is reading. A screen no longer writes identity | The staff register stops filling with records nobody created on purpose | **Read paths cannot create identity** — a boundary most systems never state |
| **A record number was treated as permission.** The candidate screen would remove *any* identity link in the workspace if you posted its number | Ownership is checked before the action, not assumed from the id | One user cannot unlink another's records by editing a URL | **An id is a request, never a permission** |
| **The same action asked for different purchases** depending on which screen you arrived from | One action, one entitlement question | Pricing and permissions stop depending on navigation history | **The answer does not depend on the route** |
| **Nothing asked which branch you belong to** | Scope is asked | Branch managers see their branch | **Scope is part of the question, not a filter afterwards** |
| **Duplicate protection lived in the code, not the database — and two identical live links had already been created** | Database-enforced uniqueness | The duplicate that already existed could not have been created | **The database, not the developer, remembers the rule** |
| *(found during the work)* **Linking a candidate silently blocked the inspector link**, refusing with a message naming an inspector that did not exist — so exactly the people the feature exists for, recruited *and* deployed, were the ones who could not be linked | Fixed | The feature works for its primary case | — |
| *(found during the work)* **The audit trail could not be retrieved** — entries stored as untyped notes with a dangling reference, findable from nowhere | Typed, retrievable, attributable | An audit trail nobody can query is not an audit trail | **Retrievability is part of the definition of "audited"** |

**Evidence:** 115 new assertions (baseline against unmodified code: 35 passed /
**39 failed**) · SQLite **12,382** / MariaDB **12,387**, 0 failed · mutation
**23 of 23 caught**.

> The baseline figure is the important one: **39 of the new assertions failed
> against the code as it stood.** The tests were written first, against reality.

#### Batch 2 — Person relationship integrity & conversion safety · **LOCKED**

| Problem that existed | What was resolved | How it eases the business | USP |
|---|---|---|---|
| **Hiring one person three times at once created three staff records.** Two belonged to nobody and nothing reported them. Every converted person also silently landed in **Ahmedabad's branch** whatever branch they were hired for, and had **no employee code** | One person, one record, correct branch, real employee code | Payroll and deployment stop inheriting phantom staff | **Concurrency-safe conversion** — the "hire" button pressed twice is still one hire |
| **Saying "these two applications are the same person" could quietly split an existing group.** Somebody previously declared the same person became a different person, **with no audit entry anywhere** | Merging cannot silently un-merge; the change is recorded | Identity decisions accumulate instead of fighting each other | **Identity decisions are append-only knowledge** |
| **The hiring conversion wrote nothing to the identity ledger.** A person hired through recruitment was, to the identity system, connected to nothing | The edge is written | "Show me everything about this person" includes the fact that you hired them | **The recruit→employee edge exists**, which was F4's headline gap |

Nothing was merged, no Person record was created, and all five existing identity
mechanisms still exist — a deliberate constraint, so that convergence later
builds on ground that holds.

#### Batch 3 — Organisation representation, duplicate safety & cross-reference

**Original implementation: LOCKED** (`c3d558d` … `c38d243`)
**Corrective implementation: PENDING owner acceptance** (`311d191`, `ea96819`)

Batch 3 was accepted and locked — and then a fresh adversarial pass **reopened
it**. Six defects, five of them introduced by Batch 3 itself. That is recorded
here rather than smoothed over, because the ability to reopen one's own accepted
work is the control that makes the rest of this document credible.

| Problem that existed | What was resolved | How it eases the business | USP |
|---|---|---|---|
| **A1 — three concurrent writers produced three "main contacts"**, 3 runs out of 3 on MariaDB, straight through a non-unique index. There was no transaction, no lock and no constraint | A generated column carrying the organisation's id **only** for rows claiming to be primary, under a UNIQUE index. A second primary cannot be written by anyone — not a racing request, not a retry, not raw SQL | "Who is the main contact at this company?" has exactly one answer, permanently | **The illegal state is unrepresentable**, rather than merely discouraged |
| **A2 — a leading space made `" ann@x.com"` a different person from `"ann@x.com"`**, creating a second account on one address that its owner could never sign in to | One canonical rule, `LOWER(TRIM(...))`, in PHP *and* in the database's own uniqueness key, so the two cannot disagree | Nobody is locked out of an account that has their name on it | **The application and the database compute identity the same way** |
| **A3 — the public sign-up form answered "is this company already your customer?" 200 times out of 200** — by response size (12,389 vs 3,507 bytes) and timing (21ms vs 284ms), without reading a word | Every outcome a stranger can steer returns **one answer, one page, one size**; the difference is carried by e-mail to the address they typed. Measured after: **byte-identical (3,574 every request)**, overlapping timing | The sign-up page stops being a free customer-list lookup service for competitors | **Side-channel-aware privacy** — treating size and timing as disclosure, which almost no business application does |
| **A4 — an unauthenticated stranger could rewrite a customer's dashboard.** A refused sign-up was written to the matched customer's activity feed, which shows the latest 8 entries; **ten anonymous posts wiped their real history** | Security evidence moved to the staff access trail. Customer activity and security evidence are different things in different places | A customer's own record cannot be vandalised by someone with no account | **Evidence retained, exposure removed** — the fix keeps the forensics and drops the blast radius |
| **A5 — the duplicate detector never consulted company status** | Status is consulted — but only ever to say *less*, never to allow more | A duplicate already resolved stops being reported; a dormant or blacklisted company still cannot re-register itself as clean | **"Status may make the system say less; it must never make it allow more"** — one sentence a non-technical owner can audit the behaviour against |
| **A6 — a safety constraint silently never existed.** SQLite does not list generated columns in `PRAGMA table_info`, so the migration reported the key missing on every boot, the `ALTER` failed, and the UNIQUE INDEX behind it was skipped **for the life of the install**. Nothing said so | Detection and repair separated; a failed `ALTER` re-checked rather than believed; the index **verified to exist** before success is reported; every guard records OK / DIRTY / FAILED with a reason in a ledger | An operator can *read* whether a protection is installed, instead of assuming it | **Safety migrations that cannot lie.** A constraint that could not be installed says so |
| **R4 — a merge left only prose.** After merging two duplicate companies, the dashboard reported the pair as duplicates **for ever** — the product's own remedy could not clear its own warning — and a new applicant matching the retired company's tax id was sent to the **dead** record | An explicit machine-readable successor pointer, following the convention this codebase already uses twice. Identifiers on the retired record are deliberately **not** wiped | Cleaning up duplicates actually clears the warning, and new applicants reach the company that trades | **Evidence-preserving merge.** The retired record keeps the proof of why it was merged |

**Evidence (corrective):** focused suite **132/132** both engines · full
regression **SQLite 12,806 / MariaDB 12,810, 0 failed** · **15/15** real-browser
assertions · real HTTP byte-identical · mutation battery **in progress at the
time of writing**.

---

## 3 · What changed that is not a feature

The most valuable outcomes of this programme are not in any screen.

| Before | Now | Why it matters commercially |
|---|---|---|
| Tests ran on SQLite only; production was MariaDB | Every phase is proved on **both**, MariaDB authoritative | Defects that only production can reveal are found before production |
| "It works" meant the tests passed | **Mutation testing**: the implementation is deliberately broken to prove the tests would notice. A FATAL is not a catch; an unapplied mutant is not a catch; a dirty baseline aborts the battery | Prevents the most expensive failure mode in software — *tests that agree with the code instead of checking it* |
| Concurrency was reasoned about | Proved with **real operating-system processes released on a wall-clock barrier** | The races that cost money happen under load, not in a single process |
| A phase ended when the work was done | A phase ends when an **adversarial audit** of the finished work stops finding things — and the auditor is required to attack their own work | Phase 3 needed fifteen corrections; Batch 3 was reopened after being accepted |
| Business rules lived in developers' heads | **42 testable business invariants**, each with an owner, a condition and a status of HOLDS / PARTIAL / VIOLATED / NOT ESTABLISHED. "NOT ESTABLISHED" is explicitly **not** a synonym for "holds" | A non-technical owner can read the safety position of the platform without reading code |
| Rules were applied where someone remembered | Rules are pushed into the **database** (generated columns + unique indexes) wherever possible | A rule in code is a rule a future writer can forget |

### The register, honestly

Of **42** business invariants, **14 were VIOLATED and 6 PARTIAL** when the
register was written. The current register records **30 HOLDS · 12 PARTIAL ·
4 VIOLATED · 1 NOT ESTABLISHED** across its 47 classified rows.

**This register has not yet been updated with the Batch 3 corrective evidence**,
which closes several more. It is deliberately quoted as it stands rather than as
it will read — a register that quietly forgets what it used to say is not a
register.

### Test growth

| Point | Suite |
|---|---|
| Phase 1 close | **8,267** |
| Phase 5 lock | **12,252** |
| Phase 6 Batch 3 corrective | **12,806** (SQLite) · **12,810** (MariaDB) |

**+4,543 assertions, ~55% growth**, with **zero failures on both engines** at
every lock.

---

## 4 · The USP, stated plainly

Not marketing claims — each is a consequence of something in the ledger above.

1. **Fail-closed commerce.** Entitlement is a hard security boundary. A module
   the customer did not buy is not hidden, it is *refused*, including on public
   routes and background jobs where nobody is signed in.
2. **Structural tenant isolation.** One database per customer, plus workspace
   binding. Isolation is not a `WHERE` clause that a future query can omit.
3. **The illegal state is unrepresentable.** Where a rule can be a database
   constraint, it is one — so it survives concurrency, retries, raw SQL and
   future developers.
4. **One requirement, many sources.** Multi-source fulfilment without
   duplicating the requirement is the difference between committing to twenty
   people and accidentally committing to forty.
5. **Identity is respected, not flattened.** One human may legitimately be a
   candidate, an inspector and a marketplace professional at once. The platform
   links them without merging them and without inventing a master Person record.
6. **Privacy against side channels.** A public form that cannot be used to
   enumerate customers — verified by size and timing, not by reading the words
   on the page.
7. **Evidence a non-engineer can audit.** Invariants with statuses, a guard
   ledger that says whether each protection is actually installed, and
   completion reports that record what was found rather than what was hoped.
8. **A method that reopens its own accepted work.** Batch 3 was locked, then
   reopened by its own adversarial pass. That is the reason to believe the rest.

---

## 5 · What is deliberately not done

Stated because a change ledger that lists only wins is a sales document.

| Item | Status |
|---|---|
| Phase 6 Batch 3 corrective implementation | **PENDING owner acceptance.** Not locked |
| Mutation battery for the corrective batch | **In progress** at the time of writing; three survivors already identified as gaps in the tests, with fixes queued |
| Batch 4 | **NOT STARTED.** Not to be begun without an explicit owner instruction |
| Identity convergence / a Person hub | **OPEN by design.** Batches 1–3 make identity writing safe *first* |
| `BLACKLISTED` on a company record | **OPEN.** It is a red badge that blocks nothing; the enforced control is a separate `hold_status` field |
| Public sign-up requiring e-mail confirmation | **OPEN.** Would close the last residual registration side channel; a product decision, not a defect |
| Production deployment / customer UAT | **NOT CLAIMED** anywhere in this programme |
| Q1–Q18 (Phase 6 foundational questions) | **OPEN**, recorded |

---

## 6 · Source documents

| Area | Where |
|---|---|
| The original audit and the five findings | `docs/phase0/` — especially `00-INDEX.md`, `15-ARCHITECTURE-LOCK.md` |
| Phase 1 | `docs/phase1/PHASE1-CLOSURE-REPORT.md` |
| Phase 2 | `docs/phase2/M3-COMPLETION-REPORT.md`, `M4-COMPLETION-REPORT.md` |
| Phase 3 | `docs/phase3/M6-END-TO-END-STATE-MATRIX.md` + M1–M6 reports |
| Phase 4 | `docs/phase4/P4-COMPLETION-REPORT.md` |
| Phase 5 | `docs/phase5/P5-COMPLETION-REPORT.md` |
| Phase 6 | `docs/phase6/` — audits, batch completion reports, `P6-BUSINESS-INVARIANTS.md` |
| Batch 3 corrective | `docs/phase6/P6-BATCH3-CORRECTIVE-IMPLEMENTATION.md`, `-CORRECTIVE-EVIDENCE.md`, `-CORRECTIVE-ADVERSARIAL-AUDIT.md`, `-STATUS-SEMANTICS.md` |
| Programme status | `docs/PHASE-STATUS.md` |
