# EXAACT — complete workflow documents, one per navigation area

One document per item in the left navigation, in the order the menu shows them.
Each covers every screen in that area, every control on those screens, what
each one does, the data that arrives from other areas and the data that leaves,
then end-to-end scenarios and edge cases.

| # | Area | Screens | Document |
|---|---|---|---|
| 1 | Sales | 24 | [01-SALES.md](01-SALES.md) |
| 2 | Marketplace | 18 | [02-MARKETPLACE.md](02-MARKETPLACE.md) |
| 3 | Operations | 65 | [03-OPERATIONS.md](03-OPERATIONS.md) |
| 4 | Recruitment | 29 | [04-RECRUITMENT.md](04-RECRUITMENT.md) |
| 5 | Quality & Accreditation | 25 | [05-QUALITY.md](05-QUALITY.md) |
| 6 | Reporting | 32 | [06-REPORTING.md](06-REPORTING.md) |
| 7 | Money | 20 | [07-MONEY.md](07-MONEY.md) |
| 8 | Insights | 13 | [08-INSIGHTS.md](08-INSIGHTS.md) |
| 9 | Directory | 17 | [09-DIRECTORY.md](09-DIRECTORY.md) |
| 10 | Admin | 60 | [10-ADMIN.md](10-ADMIN.md) |

> **Updated 19 Oct 2026.** Each document now opens with a *Status at 19 Oct 2026*
> section listing every change since first issue (18 Oct), with its gap or
> question id and how it was verified. Passages those changes made untrue are
> corrected in place and marked **(updated 19 Oct)**. The changes reach the
> live site when it is updated — `../14-LIVE-CHECKS-GUIDE.md`.

**303 screen slots in total** (a screen reachable from two areas is listed under
both), **5,492 form fields**, **1,064 screen controls**, **463 tables**.

---

## How these documents were built, and what that means for trusting them

Every screen list, field list, button list and column list here is **measured
from the running application**, not written from memory or read off the source.
The harnesses are in `../evidence/`:

| What | How |
|---|---|
| Which screens exist in an area | `area-roster.py` — the area's own tiles, plus every screen whose gating module belongs to the area, plus everything the tiles link to |
| Every field on a screen | `screen-field-harness.py` — loaded each screen as an administrator and read its inputs, selects and textareas, with their names, types and whether they are marked required |
| Every button | the same harness; `<button>`, submit inputs, and links styled as buttons |
| Which module gates a screen | `../../phpapp/tests/fixtures/routes_gated.tsv`, resolved the way the dispatcher resolves it |
| What a screen leads to | the links on the rendered page, followed |
| Who may open it | `route-probe-by-role.py`, every route as each of seven roles |
| Statuses a record passes through | `lifecycle-and-ownership.php`, read from the application's own constants |

**Two things are stripped out of every count**, because they are the page
frame rather than the screen:

- **Chrome buttons** that repeat on all 256 screens: `✕ « ★ ☰ ← 🔍` and
  `🧭 Go to…⌘K`.
- **The field `q`**, which is the rail's "Find a screen…" box, and its twin in
  the ⌘K palette. A screen described below as having "no fields" has none of
  its own.

### Three limitations, stated plainly

1. **Structure, not volume.** The test database carries very little
   operational data (0 work orders, 0 jobs, 665 business partners). So these
   documents describe accurately *what a screen asks and offers*; they do not
   test how it behaves with 10,000 rows. Pagination, slow lists and sorting at
   volume are listed as remaining work in `../09-UAT-PLAN.md`.
2. **Administrator's view, except where stated.** Field and button lists were
   taken as an administrator, who sees the most. Where a role sees something
   different, it is called out — and the per-role access is measured in
   `../evidence/role-access-matrix.md`.
3. **Not every button was pressed.** Buttons are inventoried completely;
   behaviour is stated as **CONFIRMED** only where it was actually driven.
   Destructive actions (delete, purge, reset) were deliberately not fired.
   Anything not driven says so.

---

## Reading the data-flow sections

Each document has an **arrives from** and a **leaves for** section. These are
the joins that make EXAACT one system rather than ten, and they are where a
change in one area breaks another. The master picture is in
`../02-MODULE-DEPENDENCY-MAP.md`; each document carries only its own edges.

A flow is graded:

- **CONFIRMED** — driven, or crawled, with the evidence file named.
- **READ** — the code plainly does this; it was not executed.
- **SUSPECTED** — inferred from field names or a screen's wording; needs a test.
