# UX-B7 — Mobile lists and tables · PHASE 1 AUDIT (no code changed)

Measured 24 Sep 2026 against the running application on the seeded `rqv_ui`
workspace, at 360×800, 390×844 and 412×915 with touch emulation.

---

## B7-A — Corrected inventory

The finding B7 was commissioned against (**F-A4-3**) states: *"249 of 251 table
views scroll sideways on a phone"*, with *"…with any card fallback: **2**"*.

Measured, crawling 320 pages from the application's own navigation:

| | F-A4-3 claimed | Measured |
|---|---|---:|
| Views containing a `<table>` in source | 251 | 251 ✓ |
| Distinct reachable pages that render a table | — | **64** |
| **Pages whose body scrolls sideways** | 249 | **0** |
| Tables rendered as labelled cards | 2 | **100** |
| Tables containing a field (engine skips) | — | 36 |
| Pages with any **inner** horizontal scroll | — | **11** (5 families) |
| JS errors | — | 0 |

Identical at all three widths — the responsive rules live under
`@media (max-width:640px)`, so 360, 390 and 412 all behave the same.

Coverage saturated: doubling the crawl from 160 to 320 pages added one
table-bearing page and one exception.

### Why the finding is wrong

The application has had a **global responsive-table engine** since commit
`b963490` (27 Aug) — the same commit that carried the tab engine B6 found, and
`git merge-base --is-ancestor` confirms it is an **ancestor** of the commit that
added the A4 audit. It was in the tree when the audit said it was absent.

`initResponsiveTables()` in `app.js` tags `table.grid/.dt/.tbl`, copies each
column heading onto its cells as `data-label`, and adds `.rtable`; the
stylesheet then renders every row as a `Field: value` card.

F-A4-3 quotes `table.dt{display:block;overflow-x:auto;white-space:nowrap}` —
which is real, and is the **fallback for tables JS did not convert**, labelled as
such in the source. The card rules sit **ten lines below it in the same media
block**. The audit read the fallback and stopped.

> **"249 of 251 tables scroll sideways" is false. The measured figure for pages
> that scroll sideways is 0. The figure for registers that scroll *inside their
> own box* is 34 tables across 5 screen families.**

---

## B7-B — Classification of the exceptions

| Family | Type | Table | Width in 326px | Rows |
|---|---|---|---:|---:|
| `/availability` | A — operational | 9 × `dt.av-tbl`, 5 cols | 680–728px | ≤80 |
| `/report-reviews` | A — operational | `dt`, 6 cols | **1197px** | 3 |
| `/contract-openings` | A — operational | `dt`, 6 cols | 736px | 6 |
| `/to-bill` | B — transactional | 22 × `dt`, 5 cols | 418px | 2–16 |
| `/call?id=N` | F — technical history | `tbl`, headerless | 390–524px | 1–2 |

No Type C (master data), D (reporting) or E (configuration) screen overflows at
all — they are already carded.

---

## B7-C — Existing mechanisms (all predate the audit)

| Mechanism | Where | Status |
|---|---|---|
| `initResponsiveTables()` + `.rtable` | `app.js` / `app.css` | **The** card engine. 100 tables carded. |
| `data-l` + `::before` labelled cards | requirement form, Form Designer | the "2 exceptions" the audit found |
| `overflow-x:auto` fallback | `app.css` | intentional, for tables JS did not convert |
| `.chain-step` / `.tile` / `.kv-grid` / `details.fold` | `app.css` | already responsive |

**No new mobile component is needed.** B7 does not need its one permitted new
pattern.

---

## B7-D — The genuine problem, and it is one line

All four `.dt` families are excluded by a **single predicate**:

```js
// An entry grid (has editable fields) is left alone; a data table with an
// inline action form (only hidden inputs + a submit button) is fine to card.
if (t.querySelector('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea')) return;
```

The comment states the intent correctly — skip **entry grids**, card **registers
with an inline action**. The predicate does not implement that intent: it
excludes any table containing any field at all.

Every one of these four is a register with **one action control per row**:

| Screen | The single control |
|---|---|
| `/availability` | a status `<select>` per row |
| `/report-reviews` | an optional note `<input>` per row |
| `/contract-openings` | a coordinator `<select>` per row |
| `/to-bill` | an "Include" checkbox per row |

### What the user actually loses

Header cells reachable **without** scrolling the table sideways, at 360px:

| Screen | Visible | Requires sideways scroll |
|---|---|---|
| `/availability` | Team Member | Business Unit · **Today (status)** · **Set status (action)** · Note |
| `/report-reviews` | Report · Client | **Why** · **What they said** · Nonconformity · **Acknowledged (status)** |
| `/contract-openings` | Contract · client | What it's for · Requested · **Step (stage)** |
| `/to-bill` | Include · Job · Work Order | Closed · **Value (amount)** |

This breaches the brief's §17 (never hide status), §10/§11 (primary action must
stay discoverable) and §21 (never hide an amount) — and there is **no visual
affordance** that more columns exist. `/availability` reads as a plain list of
names; `/report-reviews` cannot show *why* a report was rejected, which is the
screen's whole purpose.

---

## B7-E — Proposed treatment

**Narrow the predicate to match its own stated intent**: skip a table only when
editable fields appear in **more than one column**. A true entry grid has people
typing across the row; a register has one action column.

Measured blast radius over every field-bearing table in the crawl:

| | Count | Overflowing | Has a usable header |
|---|---:|---:|---:|
| Fields in **one** column → would become cards | **34** | 34 | 34 |
| Fields in **several** columns → stays a table | **2** | **0** | — |

The only two true entry grids are the `/companies` pricing tables, and **neither
overflows**, so neither is affected in practice.

One predicate, `Pattern A` (responsive table) via the existing engine, no new
component, no markup change on any screen, no new query, no duplicate fetch.

**Left as-is deliberately** — the `/call` status-history table (`lib/tosrm.php`)
is headerless (`<tbody>`-only: timestamp, transition, actor). The engine skips it
for a different and correct reason: there are no headings to label cells with.
It is read-only Type F history, ~100–230px over, and carries no status, action
or amount. Recorded, not changed.

---

## B7-F — Risks

| Risk | Assessment |
|---|---|
| A real entry grid becomes cards and breaks data entry | Measured: only 2 tables have multi-column fields, and both are `/companies` pricing, which do not overflow. Must be an explicit test. |
| Row action loses its form binding | None — the card layout is pure CSS (`display:block` on `tr`/`td`). Fields stay inside their form. To be proven, not assumed. |
| Desktop regression | None expected — every rule is inside `@media (max-width:640px)`. Must be verified at desktop width. |
| `/availability` becomes very long | 80 rows × 5 cells as cards is a long page. It is already ~73,000px as a table. Pagination is explicitly out of B7 scope (§15) — recorded as a deferred product decision. |
| Permission / tenant exposure | None — presentation only, no query, no route, no permission touched. Must still be covered by negative and tenant tests. |
| Status/amount visibility | This change *restores* them; regression tests must assert they are present. |

---

## Verdict offered for decision

B7 is **almost entirely already solved**. The honest scope is:

* **one predicate** in `initResponsiveTables()`, fixing 34 register tables across
  4 screen families;
* **zero** new components, engines, queries, markup changes or permissions;
* **one** exception documented and deliberately left alone.

If the owner prefers, B7 can also be accepted with **no code at all** — the page
never scrolls sideways at any of the three widths today. But four operational
and financial registers do currently hide status, the primary action and an
amount behind a horizontal scroll with no affordance, which the brief's own
§17/§10/§21 call out.

**Recommendation: make the one-line change.** It is the smallest possible fix
with the largest measured effect, and it makes the engine do what its own
comment already says it does.
