# UX-B-CHANGELOG

One row per change, across all of Phase B. Newest phase last.

---

## B0 — Baseline and protection · commit `b22c1ce`

| File | Component | Problem | Change | Reason | Risk | Test |
|---|---|---|---|---|---|---|
| `docs/phase7/UX-B-PREIMPLEMENTATION.md` | — | Phase B had no recorded starting point | Baseline written: commit, engine results, patterns to preserve, components to reuse, screens in scope, 7 risks, protected suites | A later claim of improvement needs something to be measured against | none — no source changed | both engines green at `d5bd60f` |

**Correction issued:** F-A3-1's "103 tiles, 0 carry a count" was an
empty-workspace artefact. Measured: 0/103 empty, **13/104 seeded**, **24 wired
in source**. B5 is therefore an extension of a working pattern, not the building
of one — and must be verified on populated data.

---

## B1 — Accessibility and global visual tokens · commit `0c02577`

### Source changes

| File | Component | Problem | Change | Reason | Risk | Test |
|---|---|---|---|---|---|---|
| `lib/access.php` | theme engine | No way to express "readable" — tokens were fixed mixes that cannot promise a ratio | Added `theme_rel_lum()`, `theme_contrast()`, `theme_readable()` | One central place to derive an accessible colour | low — pure functions, no state, no I/O | B1 A1–A5 arming |
| `lib/access.php` | `--muted` | 2.67 / 2.43 / 2.46:1 on card / panel / page — **every** workspace, not only branded ones | Derived as before, then made readable against all three surfaces | WCAG AA for normal text | low — a compliant value is returned unchanged | B1 C, 6 themes |
| `lib/access.php` | `--field-line` | Aliased to `--line`; a control's boundary measured 1.31:1 | Own derivation at 3:1 | WCAG 1.4.11. `--line` left alone because it also draws row separators | low | B1 C |
| `lib/access.php` | `--focus` *(new)* | Focus ring painted in the raw tenant brand — 2.10:1 on pale gold | Derived from the brand at 3:1 | A keyboard user must see where they are; identity kept | low | B1 C, browser V3 |
| `lib/access.php` | `--brand-ink` *(new)* | `a{color:var(--brand)}` made **every link** 2.10:1 on a pale brand | Derived from the brand at 4.5:1 | Links are text | low | B1 C, browser V1/V2 |
| `lib/access.php` | `--btn-bg`, `--on-brand` *(new)* | `.btn` hard-coded `color:#fff` on the brand — unreadable primary buttons | Label colour the brand can carry; background moved only if that is still not enough | Buttons must be readable in every workspace | low — hue preserved | B1 C, browser V1/V2 |
| `lib/access.php` | top-bar text | Chosen by the old perceived-brightness formula, not a contrast ratio | Now uses `--on-brand` | One rule, correctly measured | low | B1 C |
| `lib/access.php` | default `--brand` | Shipped `#1e40af` blue; blueprint mandates Deep Teal (**F-A2-1**) | Fallback is now `#0f5f5c` | Unbranded workspaces showed the wrong product | **visible** — but only where no colour was ever chosen | B1 E4 |
| `assets/css/app.css` | placeholders ×3 | `--muted` multiplied by `opacity:.7` → 1.91:1 | Opacity removed | The token is now compliant on its own; the multiplier undid it | low — still clearly lighter than entered text | B1 E1 |
| `assets/css/app.css` | focus rules ×3 | `border-color`/`box-shadow` in the raw brand | `var(--focus)` | Found by the browser, invisible to the source guard | low | B1 E2b, browser V3 |
| `assets/css/app.css` | brand-as-text ×14 | Links, crumbs, tabs, active items, sort headers | `var(--brand-ink)` | Same defect, product-wide | low | B1 E2c |
| `assets/css/app.css` | `.btn`, `.btn.secondary` | Hard-coded white / raw brand label | `--on-brand` / `--brand-ink` | Readable in every workspace | low | B1 E2d |
| `assets/css/app.css` | bare `.form-control` | Bordered with `--line` (a separator token) | `--field-line` | Correct member of the same token family | low | browser V1/V2 |
| `assets/css/app.css` | `.tabbtn` | Hard-coded `rgba(30,64,175,…)` blue fallback | Removed | Last literal of the old brand | low | B1 E4 |

### Test changes

| File | Change | Reason |
|---|---|---|
| `tests/test_b1_accessibility_tokens.php` | **new**, 112 assertions | Contrast maths written out independently so the code under test cannot flatter itself; 6 themes × every surface a token lands on; guards that the stylesheet cannot undo the engine |
| `tools/b1-contrast-check.js` | **new**, 15 checks, 299 rendered samples per theme | Only a browser can prove what is finally painted. It found two defects the server test could not see |

### What was NOT changed

Navigation · forms · record layouts · search · dashboards · area homes · tables ·
pagination · statuses · buttons' behaviour · permissions · entitlements ·
lifecycle · database. **Zero** migrations, columns or tables.

### Deferred

Gold accent → **B10** (`--accent` is wired to `--info`; changing it would change
a status meaning). `--line` at 3:1 → B10. Nine duplicated status-tone helpers →
B10. 16px mobile type floor → B7. Dead `.topbar` CSS → B10.

---

## B2 — Navigation + Recruitment Command Centre · commit `2881e61`

### Source changes

| File | Component | Problem | Change | Reason | Risk | Test |
|---|---|---|---|---|---|---|
| `views/ops/recruitment_cc.php` | section order | "Needs attention today" was 14th of 20 headings — **2,018px** of scrolling, past six analytics sections, before the page said anything actionable | Action sections moved above analysis; an `Analysis` divider marks the break | The page is a recruiter's daily screen, not a monthly report | low — markup moved only; each block verified to depend solely on variables defined in the page header | B2 B1–B5; browser N1 |
| `views/ops/recruitment_cc.php` | hiring-request door | `/hiring-requests` had **no** entry in the rail, in any area tile, or on this page — reachable only from inside an individual request | Added behind `hreq_can_view()` | The register for step one of the chain was unreachable unless you were already in it | low — same gate as the handler | B2 D1–D3; browser N2 |
| `views/ops/recruitment_cc.php` | the two paths | The page offered "New requirement" and never mentioned hiring requests | One neutral sentence naming both, **declaring neither preferred** | ADR-001 is open and is the owner's decision | low | B2 D4–D5 (asserts no preference is claimed) |
| `views/layout_top.php` | rail icons | 🧭 shared by Owner home **and Recruitment**; 📑 by "My reports" **and the Reporting module** | Recruitment → 🧑‍💼, My reports → 📄, What to fix → 🩺 | Two different destinations must not look alike | low — one character each | B2 E1 |

### What was NOT changed

Routes · permissions · entitlements · lifecycle · KPI engine · search registry ·
area-tile builder · dashboard · area homes · forms · tables · workflow
redirects · breadcrumbs · database. **Zero destinations removed** — asserted.

### Deferred

ADR-001 preferred path → **owner decision** · rail route restructure → B9 ·
duplicate `Approvals` heading → B10 · next-action block → B3 · relationship
line → B4 · area-home counts → B5 · heading count → B10.

---

## B3 — Next Action · commit `b550f30`

### Source changes

| File | Component | Problem | Change | Reason | Risk | Test |
|---|---|---|---|---|---|---|
| `lib/nextaction.php` | **new** | Candidate, requirement and hiring request told a user nothing about what to do next | Three resolvers, each reading the module's own state + own allowed-next + own permission gate, and recording which helper answered | A record screen should answer "what now?" without a second engine deciding anything | low — reads only; never writes, never permits | B3 A–G, 55 assertions |
| `views/ops/hiring_request.php` | band + anchors | — | Renders the band; `#na-submit`, `#na-decide`, `#na-recruit` anchors so the button reaches the control | A button that reloads the page you are on is noise | low | B3 G, D7 |
| `views/ops/requisition_detail.php` | band | — | Renders the band from `reqf_counts()` | | low | B3 G |
| `views/ops/candidate_detail.php` | band + anchor | — | Renders the band; `#na-joined` anchor | | low | B3 G |
| `index.php` | load order | — | Registers the library **after** the engines it reads from | It is a consumer, not a peer | low | boot |

### What was NOT built

`app.css` is **unchanged**: `.nowband` already existed. No workflow, KPI, SLA,
notification or business-rule engine. No status, transition, permission,
entitlement or database change. The work order keeps its own richer band — no
second one was stacked on it.

### Correction

F-A5-3's "no shared next-action component" was **wrong** — `.nowband` is used by
nine record screens. ~40 lines of a competing `.na-block` component were written
and then deleted once that was checked. The real gap was Recruitment.

### Deferred

`.btn.small` is 36px on touch, blueprint asks 44px → **B7/B10** · hiring request
has no `allowed_next()` helper → product/later · relationship line on the band →
**B4** · consolidating the nine hand-written bands onto `na_state()` → **B10**.

---

## B4 — Terminology + relationship visibility · commit `e750b6f`

### Source changes

| File | Component | Problem | Change | Reason | Risk | Test |
|---|---|---|---|---|---|---|
| `lib/terms.php` | `TERM_DEFAULTS` | Six words people hold apart daily had **no definition anywhere** | Six sentences added, each taken from a recorded decision (M4, Q32 Model D §10, confusion pairs 2/10/11) | A word nobody has written down is a word everybody guesses at | low — additive; no existing definition touched, asserted | B4 A, B |
| `lib/terms.php` | `T_HELP()`, `T_NOTE()` | All 26 definitions printed on ONE screen: the admin rename page | Two accessors rendering the same muted one-liner 295 views already use | The rename screen is the one place nobody is confused | low — no new component, no tooltip | B4 C |
| `lib/workforce.php` | `workforce_origin()` | The chain was visible one way only: candidate → workforce, never back | Reads `candidates.inspector_id` in reverse | A coordinator could not see how a person joined the company | low — read-only, returns null when there is nothing | B4 D |
| `views/ops/hiring_request.php` | definition | — | The definition, under the title | pairs 1 and 7 | low | B4 C7 |
| `views/ops/inspector_form.php` | Team field + origin | The workforce-vs-inspector rule lived only in code | Stated **at the field that decides it**; plus the origin block | pair 4 — the highest-value sentence in this phase | low | B4 C8, D7/D8 |
| `views/ops/requisition_detail.php` | origin, direct path | A directly-raised requirement said **nothing** about its origin | *"Recorded directly — there is no hiring request behind this one. Both ways of starting are supported."* | A reader could not tell whether approval was skipped or never applied | low | B4 E |

### Corrections to the audit

"25 definitions" → **26**. "Five words undefined" → **six**. And *"no screen
states the relationship between two confusable objects"* was **wrong** —
`requisition_detail.php` has said *"Raised from hiring request HR-xxx"* since
M4. B4 therefore did not build that line; it closed the two gaps either side.

### ADR-001

Untouched. The direct-path sentence describes and recommends nothing; the suite
fails if the screen ever says "should have been raised", "preferred",
"bypassed", "incorrectly", "ought to" or "skipped the approval".

### Deferred

QA / billing-readiness / professional definitions written but not yet surfaced
on their screens → **B4 follow-on / B10** · the other 26 still only on the
rename screen → same · `.btn.small` 36px → B7/B10 · hiring-request
`allowed_next()` → product decision · nine hand-written `.nowband` blocks → B10.

---

## B5 — Area-home counts & attention · commit `2765b4e`

### Source changes

| File | Component | Problem | Change | Reason | Risk | Test |
|---|---|---|---|---|---|---|
| `lib/areas.php` | 7 tiles | 24 of 103 tiles carried a count; Sales 1/10, Reporting 0/8, Admin 1/24 | Leads, Inquiries, Quotes, Competence, Confidentiality, Report register and Approval rules wired to **existing** counters through the existing `$t()` signature | An area home answered "what can I do" and not "what needs attention" | low — additive, each inside the tile's own `$show` gate | B5 A–F, 36 assertions |
| `tests/test_simplify_reportcfg.php` | area slice | Read a fixed **1600-byte** window; B5's added line pushed a still-present tile outside it | Slice to the next `case '`, plus two arming assertions | A fixed length cannot survive an area gaining a line | low — strictly stronger; 12 → 14 assertions, mutation-confirmed | itself |

**24 → 31 wired. 14 → 17 rendering on the same seeded workspace.**

### What was NOT built

No count engine, KPI engine or dashboard calculation. No new business metric —
four candidate counters were **rejected** because they are per-record
(`imp_declaration_due`, `connect_client_bench_count`, `hwp_open_count`) or
measure volume rather than attention (`consent_open_count`). No view changed;
`area_home.php` already knew how to paint a badge.

### Two test assumptions corrected

`ops_area_def()` builds tiles whatever the licence says — the licence is
enforced one level up by `ops_area_has()`, which the area-home route requires.
And `quality` is deliberately gated on the **operations** licence
("accreditation packs are Operations access-modules"), so entitling Operations
is supposed to bring Quality with it.

### Deferred

~73 tiles still unwired — no workspace-wide counter exists and B5 may not write
one · Insights and most of Admin are configuration destinations where a badge
would be noise · per-record counters would need a product decision to aggregate.

### B5 · a second, unrelated test correction

`test_rb3_emp_code.php` X2d failed on the authoritative engine with
`[RACE_LOST RACE_LOST WORKFORCE_MATCH CONVERTED]`. A straggler in the four-way
conversion race was refused by the **duplicate guard** — the winner's team
record now existed and matched the candidate's e-mail. Specific, deterministic,
and correct. The accepted set (widened once before on `ALREADY` evidence) now
includes it. Pre-existing behaviour; unrelated to B5.

## B6 — Forms and progressive disclosure

**Correction first.** F-A5-1 said Job, Test request, Engineer and User show
"every field at once — Disclosure: none", and that Candidate was "disclosed".
Measured: Job, Test request and User have had tabbed disclosure since `b963490`
(27 Aug), an **ancestor** of the commit that added the audit — so the finding was
wrong when written, not out of date. Candidate had no disclosure at all and was
the densest form in the product. Only the Engineer *add* screen matched the
finding. `docs/ux/UX-A5-FORMS-STATES.md` and `docs/ux/UX-AUDIT.md` now carry that
correction; `docs/phase7/UX-B6-IMPLEMENTATION.md` §1 is authoritative.

**Changed**
* `views/ops/inspector_form.php` — the add screen's single column split into a
  core block plus four `details.fold` groups. **33 → 13** controls on open,
  1162px → 900px. No field removed; the field inventory is identical.
* `views/ops/candidate_form.php` — four panels on the existing `[data-tabs]`
  engine, deliberately **not** `.form-tabs`, so Save stays reachable from panel
  one. **29 → 11** controls on open, 1436px → 936px.
* `assets/js/app.js` — `activateTabForField()` → `revealField()`, which now opens
  every `<details>` above a field as well as bringing its panel to the front. The
  submit guard used to let a box it judged off screen go to the server unchecked;
  a folded box would have been ringed red inside a closed fold, because a closed
  `<details>` still reports a layout box. Now the box is revealed first. This
  reaches the Job, Test request and User forms too.
* `assets/css/app.css` — one line in the existing `@media (pointer:coarse)` block:
  panel tabs and fold summaries reach the blueprint's 44px (they were 33px).

**Not changed** — no database, permission, status, transition or action handler.
No field deleted. No second form, validation, wizard or configuration engine: the
product already has `[data-tabs]`, `details.fold` and the Form Designer overlay,
and all three were reused as they are. Required stays required: `' required'`
occurs 5 times in the team-member form and twice in the candidate form, before
and after.

**Evidence** — 113 PHP assertions, 26 desktop browser assertions, 6 phone
assertions, all green; 8 mutations caught. One mutation **survived** (M1: an
explicit `revealField()` call added to the guard proved redundant, because
`checkValidity()` already fires `invalid`) — that line was removed rather than
kept. Two faults in this stage's own tooling were found and corrected: a closed
`<details>` leaves `offsetParent` non-null, and a `fullPage` screenshot corrupts
the next page's measurements.

**ADR-001 remains OPEN.**

## B7 — Mobile lists and tables

**Correction first.** F-A4-3 said "249 of 251 table views scroll sideways on a
phone" with "any card fallback: 2". Measured at 360/390/412 across a 320-page
crawl: **0** pages scroll the body sideways, **100** tables were already
rendered as labelled cards, and 11 pages had an inner scroll. The card engine
`initResponsiveTables()` has existed since `b963490` (27 Aug), an **ancestor**
of the commit that added the audit; the audit quoted the `overflow-x:auto`
fallback and missed the card rules ten lines below it in the same media block.
Same error class as B6's F-A5-1.

**Changed** — one predicate in `assets/js/app.js`:

```
- if (t.querySelector('input…, select, textarea')) return;   // any field at all
+ if (fieldColumnCount(t) > 1) return;                        // only a real grid
```

The engine's own comment already said an entry grid is left alone but "a data
table with an inline action form is fine to card". The test did not implement
that sentence, which left four registers scrolling sideways with no hint more
columns existed — `/availability` hid the status **and** the Set-status control,
`/report-reviews` hid why a report came back, `/contract-openings` hid the
stage, `/to-bill` hid the value. `fieldColumnCount()` counts distinct columns
holding a field, not fields, and stops at two.

**Result:** tables carded **100 → 134**; inner-scroll pages **11 → 6**, all six
the same `/call` status-history table, which is headerless and correctly
excluded by a different guard. Body overflow 0 and JS errors 0 at all three
widths. Desktop verified unchanged by computed style, not by class name.

**Not changed** — no new component (B7 did not need the one it was permitted),
no engine, query, markup, route, permission or tenant scope. Every rule involved
is inside `@media (max-width:640px)`.

**Evidence** — 23 PHP assertions, 45 browser assertions, 5/5 mutations caught,
SQLite **13,936/0**, MariaDB **13,936/0**.

**Two weaknesses found in B7's own tests and fixed:** the `/companies` pricing
grid was guarding nothing (it is `class="sc"`, which the engine never looks at),
and the application contains **no** targeted multi-column entry grid at all — so
the "must not card a real grid" rule had no live example. A unit section now
runs the *shipped* `fieldColumnCount()` over purpose-built tables. Separately,
the wrong guard was being mutated: a table with no `<th>` is stopped by
`if (!head) return;`, now pinned as C1b.

**Deferred:** `/availability` is now an 80-row card list; pagination is out of
B7 scope (§15) and remains a product decision.
