# UX-B7 — Mobile lists and tables · IMPLEMENTATION

**Scope:** presentation only. One predicate in the existing card engine.
No new component, engine, query, markup, route, permission or tenant scope.

The Phase 1 audit is `UX-B7-AUDIT.md`; it corrects F-A4-3 and is the basis for
everything below.

---

## B7-G — What changed

| File | Change |
|---|---|
| `assets/js/app.js` | `fieldColumnCount()` added; the card engine's skip test now reads `if (fieldColumnCount(t) > 1) return;` |
| `tests/test_b7_mobile_tables.php` | new — 23 assertions |
| `deploy-check.php` | regenerated |

```diff
- if (t.querySelector('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea')) return;
+ if (fieldColumnCount(t) > 1) return;
```

The engine's own comment already stated the rule — *"An entry grid is left
alone; a data table with an inline action form is fine to card"* — and the test
under it did not implement that sentence. It skipped **any** table holding
**any** field.

`fieldColumnCount()` counts **distinct columns** holding an editable field, not
fields. Counting fields would still skip an 80-row register carrying one control
per row, which is precisely the case that was broken. It stops at two, because
the caller only asks whether the answer is more than one.

---

## B7-H — What was deliberately not changed

| Kept as-is | Why |
|---|---|
| `/call` work-order **status history** | Headerless (`<tbody>` only: timestamp, transition, actor). Excluded by `if (!head) return;` — there are no column names to print beside its values, so carding it would show bare values. Read-only Type F history carrying no status, action or amount. **This is the only remaining inner scroll in the product.** |
| `/companies` pricing tables | `class="sc"` — a class the engine never considers. Unchanged, and they do not overflow. |
| The set of targeted classes | Still `table.grid, table.dt, table.tbl`. Not widened. |
| The `overflow-x:auto` fallback | Still there, still correct, for anything JS does not convert. |
| Desktop rendering | Every rule involved is inside `@media (max-width:640px)`. |
| Pagination | `/availability` renders 80 rows and is now a long card list. §15 forbids inventing pagination in B7. **Deferred — product decision.** |

---

## B7-I — Information preservation

Nothing is hidden; the opposite. Before the change these columns were off-screen
behind a horizontal scroll **with no affordance that they existed**:

| Screen | Was hidden at 360px | Now |
|---|---|---|
| `/availability` | **Today (status)**, **Set status (action)**, Note | labelled and on screen |
| `/report-reviews` | **Why**, **What they said**, Nonconformity, **Acknowledged** | labelled and on screen |
| `/contract-openings` | What it's for, Requested, **Step (stage)** | labelled and on screen |
| `/to-bill` | Closed, **Value (amount)** | labelled and on screen |

Each value keeps its column name, printed beside it by the existing
`td[data-label]::before` rule. No column was removed, reordered or truncated.

## B7-J — Action preservation

The availability status picker — the primary action of a phone-first screen:

| | |
|---|---|
| still present and inside its row | yes |
| labelled | "Set status" |
| reachable without scrolling sideways | yes |
| choices intact | 7 |
| touch target | 44px |

No action was added, removed, moved out of reach, or had its permission changed.
The card layout is pure CSS (`display:block` on `tr`/`td`), so every control
stays inside the form it was already in.

---

## B7-K — Before / after

Crawl of 64 table-bearing pages, at 360×800, 390×844 and 412×915:

| Metric | Before | After |
|---|---:|---:|
| Body scrolls sideways | 0 | **0** |
| Tables rendered as cards | 100 | **134** |
| Pages with an inner horizontal scroll | 11 | **6** |
| Entry grids left alone | 36 | 36 |
| JS errors | 0 | **0** |

All 6 remaining are the same `/call` status-history table, and the reason
reported for each is "no header row" — the intentional exclusion. **No page
reports an unexpected overflow.**

Per screen, at 360px:

| Screen | Overflow before | Overflow after | Status visible | Action reachable | Identity visible |
|---|---:|---:|---|---|---|
| `/availability` | 728px in 326px | **none** | yes | yes (44px) | yes |
| `/report-reviews` | 1197px in 326px | **none** | yes | yes | yes |
| `/contract-openings` | 736px in 326px | **none** | yes | yes | yes |
| `/to-bill` | 418px in 326px | **none** | yes | yes | yes |

Desktop at 1280px, measured as computed style rather than class name: every one
of the four still computes `display:table` / `table-cell` /
`table-header-group`, and prints no per-cell labels. **Desktop is unchanged.**

---

## Evidence, and two weaknesses found in this stage's own tests

`tests/test_b7_mobile_tables.php` — 23 assertions.
Browser check — 45 assertions across five sections (registers, kept-as-tables,
the predicate itself, the row action, desktop).

### Mutations

| # | Defect reintroduced | Caught by |
|---|---|---|
| M1 | the old "any field at all" test restored | 20 browser assertions |
| M2 | count fields instead of distinct columns | 17 browser assertions |
| M3 | threshold loosened to `> 2` | PHP B2 |
| M4 | `if (!head) return;` removed | PHP C1b |
| M5 | early exit removed | PHP B4 |

### Two weaknesses this uncovered, and what was done

1. **A guard that guarded nothing.** The `/companies` pricing grid looked like
   protection against carding a real entry grid. It is `class="sc"` — a class
   the engine never looks at — so it would stay a table whatever the predicate
   said. Loosening the threshold to `> 2` failed **no** assertion.
   Worse, **the application contains no targeted table with fields in two
   columns at all**, so the rule has no natural example to protect. Fixed by
   adding section **[U]**, which runs the *shipped* `fieldColumnCount()` —
   lifted out of the served `app.js`, not a copy — over eight tables built for
   the purpose, and by pinning the threshold in the source test.
2. **The wrong guard was being mutated.** A table with no `<th>` is stopped by
   `if (!head) return;`, not by the `labels.some()` guard below it. `C1b` now
   pins the guard that actually does the work.

The page's CSP is `script-src 'self' 'unsafe-inline'`, so `eval` is refused.
Section [U] hands the shipped source to an inline `<script>` instead rather than
weakening the policy.

### Three probe faults corrected before any result was believed

* `/availability` carries **two** tables answering to "Team Member" — a 149-row
  roster and the availability boards. Probing only the first asked the roster
  for a "Today" column it has never had. The probe now reports the union.
* `/companies` has two `sc` tables; the first has no fields at all. The probe
  now selects by the property under test, not by position.
* Desktop was asserted by the **absence of the `.rtable` class** — wrong: the
  engine tags that class at every width and the stylesheet decides. The probe
  now measures computed appearance, which is both correct and a stronger test.
