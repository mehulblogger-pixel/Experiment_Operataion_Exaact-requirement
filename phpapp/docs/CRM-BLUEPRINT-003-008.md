# CRM Blueprints 003–008 — audited, and the one decision that blocks three of them

**Status:** received, measured against the running system. Not yet built.
**Companions:** `CRM-BLUEPRINT-001.md` (data/features), `CRM-BLUEPRINT-002.md` (UI/UX).

---

## 0 · Read this first — the blocking contradiction

Blueprint 005 asks for **millions of records, thousands of concurrent users,
caching, background job queues, high API traffic, point-in-time recovery**.

The application runs on **MilesWeb shared PHP hosting**: one MySQL instance, no
Composer, no Redis, no queue worker, no process manager, and a `cron.php` that
runs a sequence of 26 steps once a day. Uploads live base64-encoded inside table
rows. The seed of a few thousand rows was hitting the 30-second page limit two
days ago.

**That target and that host cannot both be true.** Nothing in blueprints 003–008
is impossible — but "scales to millions without redesign" on shared hosting is
not a thing I can build, and I will not pretend otherwise.

The decision to make, before 005 means anything:

| Option | What it buys | What it costs |
|---|---|---|
| **A · Stay on shared hosting** | Cheapest, works today | Cap the promise: hundreds of users, low millions of rows, no queue, no cache, backups via the hosting panel |
| **B · Move to a VPS** (~₹2–5k/month) | Redis, a real queue worker, proper cron, object storage for files, point-in-time recovery | You now run a server. Someone has to patch it |
| **C · Managed cloud** | Everything 005 asks for | Costs multiples of B, and needs the on-premise story rethought |

**My recommendation: B, and not yet.** Move when a paying customer's volume
demands it — not before. Meanwhile, build everything in 003–008 that is
host-independent, and write the file-storage and job-running code behind an
interface so B is a swap, not a rewrite.

---

## 1 · Blueprint 003 — Database, Workflow, Automation

**Already built (11 of the major asks):** configurable multi-level approval
chains by amount and business unit · workflow states through to reopen ·
auto-numbering on every register · auto follow-up, reminder and escalation ·
auto status change · auto activity logging · custom fields · the lookup/masters
engine · field-change audit history · login history · soft delete.

**Missing:** territories · a *visual* workflow builder (the rules exist; the
designer does not) · a general business-rules engine (each register has its own
hard-coded rules today) · report builder with pivot and scheduling.

**Honest note on "no coding required":** the approval matrix genuinely is
configurable without code. The rest is not — the gates I built last week
(a major nonconformity needs a CAPA; a breach needs the party-told decision) are
**deliberately in code**, because they encode a standard, not a preference. A
rules engine that lets an administrator switch off §8.7.3 is a rules engine that
loses your accreditation. **Some rules should not be configurable, and the
blueprint should say which.**

---

## 2 · Blueprint 004 — AI, Analytics, BI

**Already built:** provider keys and model selection for five providers, with
live model refresh · a dependency-free CV keyword engine · the sales dashboard
with win/loss and monthly performance.

**Ships immediately (no history needed):** e-mail drafting · WhatsApp replies ·
meeting summaries · proposal and cover-letter assistance · complaint responses ·
document classification · natural-language search over existing registers.

**Cannot ship yet, and saying so is the point:** lead scoring, opportunity
scoring, churn probability, renewal probability, payment-delay prediction, sales
forecasting. **All of them learn from outcome history. You have no leads table,
no opportunities, no activities — so there is nothing to learn from.** These
become possible roughly 6–12 months after blueprint 001 P1–P2 ship and start
accumulating real outcomes.

**"Reduce manual work by at least 60%" is not measurable today.** Nothing counts
clicks or time-on-task. Blueprint 007's usage analytics has to exist before that
target means anything — so 007 precedes the 004 performance goals, not follows
them.

---

## 3 · Blueprint 005 — Integration, Security, Enterprise

**Already built:** two-factor authentication · session hardening (strict mode,
HttpOnly, SameSite, Secure) · CSP, HSTS, nosniff, frame and referrer policy ·
role and module permissions · branch and company scoping · audit trail ·
soft delete · PWA with camera, GPS and offline capture · a health/pre-flight
screen · DPDP tooling (consent, erasure, incident register, retention runs).

**Missing, and each is real:**

| Gap | Note |
|---|---|
| **REST API** | Nothing exists. This is the single biggest item in 005 and the prerequisite for every external integration |
| **Webhooks, API keys, OAuth** | Follows the API |
| **Field-level and record-level permissions** | Today permissions stop at module + branch |
| **Password policy** | No minimum, no age, no reuse rule |
| **Encryption at rest** | Deliberately not built — on shared hosting the key sits next to the data, which is theatre. Changes under option B |
| **IP restrictions** | Would lock the app to office addresses |
| **Caching and job queue** | Blocked on the hosting decision above |
| **Automatic backups** | I need to correct an earlier impression: there is **no backup feature**. The compliance screen *tells you to take one*. That is advice, not a feature |

---

## 4 · Blueprint 006 — QA and Launch Readiness

**You already have the spine of this, and it is better than most:**
`tools/lint.sh` runs five checks on every file — parse, stray PHP tags inside
strings, duplicate function declarations, duplicate array keys, and every column
a save writes actually existing in the schema. Plus a smoke crawl and a
122-screen HTTP pass.

**Missing:** automated functional tests (everything today is verified by hand
over HTTP — thorough, but not repeatable) · performance testing · security
testing · UAT scripts per role · user and admin manuals · API documentation.

**One disagreement:** 006 is written as a gate before launch. It should be a
loop that runs on every commit. The lint sweep already works that way; the rest
should join it rather than waiting for a launch date.

---

## 5 · Blueprint 007 — Innovation and Continuous Evolution

**Nothing of this exists**, and it is cheaper than it looks. The three things
worth building first, in order:

1. **Usage analytics** — which screens are opened, which are abandoned, how long
   a task takes. Without it, every simplification decision is a guess. It also
   unlocks the 004 performance targets.
2. **In-app feedback capture** — one button, on every screen, that files against
   the screen it was pressed on.
3. **The innovation backlog with scoring** — business value, impact, effort,
   risk. `PENDING.md` is that backlog today, in prose; it wants to become rows.

---

## 6 · Blueprint 008 — Configuration Engine and No-Code

**More of this exists than you may realise:** the lookup engine (34 lists moved
into it) · custom fields per entity · the terminology engine (every business
noun renameable) · per-installation module licensing (modules switch on and off)
· configurable approval rules · e-mail templates with placeholders · the .docx
quote template engine with hot-swappable formats.

**Missing:** drag-and-drop form designer · visual workflow designer · dashboard
builder · report builder · industry template packs · configuration
export/import with version history and rollback · sandbox environment ·
localisation beyond English and ₹.

**One caution on industry templates.** Twenty templates is twenty things to
maintain, and each one is a claim you support that industry. Ship **one** —
inspection and certification, which you actually know — prove the configuration
carries a second, and only then talk about a library.

---

## 7 · The Business OS list

Measured against what exists, the "future modules" list is already part-built:

| Proposed module | Today |
|---|---|
| Operations Management | **Built** — calls, deputations, scheduling, closure, TAT |
| Quality Management (QMS) | **Built** — the whole ISO/IEC 17020:2026 pack, NCR, CAPA, audits, management review |
| Document Management | **Built** — IDEMS: report engine, templates, approvals, signatures, hash-chained evidence |
| Customer Portal | **Built** — with per-user permissions and site scoping |
| HRMS & Competency | **Part** — competence, authorisations, training basis, vouchers, attendance. No payroll, no leave |
| Finance & Accounting | **Part** — invoicing tracker, receivables ageing, Tally export, cost runs. Not a ledger |
| Executive Cockpit | **Part** — role dashboards and the sales board exist |
| Procurement · Inventory · Assets · Vendor Portal · AI Agents | **None** |

**So the honest framing is: you have a Business OS with a weak CRM, not a CRM
that needs a Business OS built around it.** That inverts the build order in a
useful way — the CRM work in blueprint 001 is filling the gap in something that
already exists, which is much less risky than it sounded.

---

## 8 · What I propose

Sequenced across all eight blueprints by value per day, ignoring blueprint order:

| # | Work | From |
|---|---|---|
| 1 | **Activity spine** — one polymorphic table | 001 P1 |
| 2 | **Shared table component** — one build, 42 screens | 002 U1 |
| 3 | **Global search** | 002 U2 |
| 4 | **Leads + pipeline** | 001 P2 |
| 5 | **Customer 360** | 001 P3 / 002 U3 |
| 6 | **REST API + keys + webhooks** | 005 |
| 7 | **Notification centre** | 002 U4 |
| 8 | **Usage analytics + feedback capture** | 007 |
| 9 | **AI that needs no history** — drafting, summaries, NL search | 004 |
| 10 | **Tasks + calendar** | 001 P4 |

Everything else waits on either the hosting decision (§0) or on data that does
not exist yet.

---

## 9 · Decisions I need

1. **Hosting: A, B or C?** Blocks caching, queues, real backups, encryption at
   rest, and every scale claim.
2. **Which rules must stay in code?** My position: anything encoding an
   accreditation clause. Confirm or overrule.
3. **Leads: do you sell to companies not already in the master?** Still open
   from blueprint 001, and still blocking P1.
4. **Is an opportunity distinct from a quotation for you?** Still open.
5. **One industry template or twenty?** My position: one, proven.
