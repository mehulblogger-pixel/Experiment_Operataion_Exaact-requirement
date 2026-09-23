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

## B2 — Navigation + Recruitment Command Centre · commit `__B2_COMMIT__`

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
