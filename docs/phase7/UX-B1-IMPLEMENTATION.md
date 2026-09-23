# UX-B1-IMPLEMENTATION — Accessibility and global visual tokens

**Scope: B1 only.** No navigation, form, search, dashboard or area-home change.
No database change. No business logic, workflow, permission or entitlement
change.

---

## 1. Baseline

Entering B1 at commit `b22c1ce`, both engines green (SQLite 13,486 / MariaDB
13,489, 0 failed). The audit finding under repair is **F-A2-2**, with **F-A2-1**
(default brand colour) addressed alongside it because both live in the same
token.

---

## 2. Root cause

`app.css` declares `--muted:#656e7a`, which measures **5.17:1** on white and
passes WCAG AA. It is never the value a user sees.

`theme_style_tag()` (`lib/access.php`) emits a second `:root` **on every page** —
it is called from `views/layout_top.php`, so this is not a branded-workspace
problem, it is **every** workspace — and re-derives the token as:

```php
$muted = theme_mix($surface, $ink, 0.45);   // a fixed 45% mix
```

**A fixed fraction cannot promise a contrast ratio.** With the built-in defaults
(`#ffffff` surface, `#1f2937` ink) it yields `#9a9fa5` — **2.67:1**.

The same mistake was repeated in three other places, and two of them were worse
than the finding that started this:

| Token | How it was derived | Measured |
|---|---|---|
| `--muted` | fixed 45% mix | 2.67 / 2.43 / 2.46:1 (card / panel / page) |
| placeholder | `--muted` then `opacity:.7` | **1.91:1** |
| `--field-line` | aliased to `--line` (a 14% mix) | **1.31:1** — WCAG 1.4.11 asks 3:1 |
| focus ring, links, buttons | the raw tenant brand colour | **2.10:1** on a pale-gold brand |

The last row is the most serious, and it is the one the audit did not find:
`a{color:var(--brand)}` means **every link in the product** is painted in the
tenant's chosen colour, and `.btn` hard-coded `color:#fff` on that same colour.
A workspace that picks a pale brand gets unreadable links, unreadable primary
buttons, and a focus ring a keyboard user cannot see.

---

## 3. The correction

One central change, in the engine that produces the tokens. No screen was
patched individually.

Three pure functions added to `lib/access.php`:

- `theme_rel_lum($hex)` — WCAG 2.1 relative luminance. (The existing
  `theme_lum()` is the older perceived-brightness formula; it is kept for the two
  call sites that only pick black-or-white, and is now documented as **not** a
  contrast measure.)
- `theme_contrast($a, $b)` — the ratio, 1–21.
- `theme_readable($c, $bgs, $target)` — returns `$c` **adjusted only as far as
  necessary** to meet `$target` against every background given. A colour that
  already passes is returned byte-identical, so a compliant workspace is never
  repainted. It darkens on light surfaces, lightens on dark ones, and terminates
  at black or white rather than looping, because a mid-grey background genuinely
  cannot carry 4.5:1 in either direction.

Tokens now published by `theme_style_tag()`:

| Token | Derivation | Guarantee |
|---|---|---|
| `--muted` | as before, then made readable on card, panel **and** page | ≥ 4.5:1 |
| `--field-line` | from `--line`, made perceivable | ≥ 3:1 (WCAG 1.4.11) |
| `--focus` | from the brand | ≥ 3:1 |
| `--brand-ink` | from the brand, at the text threshold | ≥ 4.5:1 |
| `--btn-bg` + `--on-brand` | label colour the brand can carry; background moved only if even that is not enough | ≥ 4.5:1 |

`--line` itself is deliberately **unchanged**: it also draws table row
separators, and forcing 3:1 there would make every list look like a spreadsheet.
`--field-line` already existed as a separate token and merely pointed at
`--line`; it now carries the requirement that belongs to it.

**Branding is preserved.** A gold workspace keeps gold links and gold buttons —
they simply become legible (`#d4af37` → `#a5892b` for text, black rather than
white button labels). Deep teal already passed and is returned untouched.

---

## 4. Files changed

| File | Change |
|---|---|
| `phpapp/lib/access.php` | +3 contrast functions; 5 derived tokens; Deep Teal default brand; top-bar text now chosen by contrast rather than the old brightness formula |
| `phpapp/assets/css/app.css` | base `:root` fallbacks for the new tokens; 3 focus indicators off the raw brand; 14 brand-as-text uses → `--brand-ink`; `.btn` label → `--on-brand`; 3 placeholder rules lose their opacity multiplier; bare `.form-control` border → `--field-line`; hard-coded `rgba(30,64,175,…)` removed |
| `phpapp/tests/test_b1_accessibility_tokens.php` | **new** — 112 assertions |
| `phpapp/tools/b1-contrast-check.js` | **new** — 15 checks, 299 rendered samples per theme |
| `phpapp/deploy-check.php` | regenerated |

**F-A2-1** is fixed at the same time: the fallback brand is now Deep Teal
(`#0f5f5c`), per the blueprint. Only workspaces that have chosen **no** colour
are affected.

---

## 5. Accessibility measurements

*Representative B1 accessibility verification. This is not a full WCAG audit of
the product.*

### Computed tokens, six themes (server test)

| Combination | Before | After | Need |
|---|---:|---:|---:|
| Secondary text on a card | 2.67 | **5.05** | 4.5 |
| Secondary text on a panel | 2.43 | **4.59** | 4.5 |
| Secondary text on the page | 2.46 | **4.66** | 4.5 |
| Placeholder | 1.91 | **5.05** | 4.5 |
| Form control border | 1.31 | **3.40** | 3.0 |
| Focus ring, deep teal brand | 7.47 | 7.47 *(untouched)* | 3.0 |
| Focus ring, pale gold brand | **2.10** | **3.38** | 3.0 |
| Focus ring, dark surface | 2.38 | **3.50** | 3.0 |
| Secondary text, dark surface | 3.84 | **5.19** | 4.5 |

Worst value measured across **all six themes**: secondary text **4.56:1**,
control border **3.12:1**, focus ring **3.06:1**, link **4.51:1**, button label
**7.47:1**.

Themes exercised: default, deep teal, pale gold, near-white brand, dark surface,
cream surface.

### Rendered in Chromium, 15 screens (browser check)

299 samples per run — secondary text, labels, table headers, body text, status
pills, links, primary/secondary/disabled buttons, placeholders and control
borders — each measured against the first opaque ancestor background.

| Run | Result |
|---|---|
| Default workspace | **PASS** — worst 3.14:1 (a control border, needs 3) |
| Pale-gold branded workspace | **PASS** — worst 3.14:1 |
| Focus indicator | visibly changes on focus; **3.12:1**; and is not the raw brand (`#d4af37` → `#a5892b`) |

### Public pages — verified, not changed

Five self-contained pages carry their own `:root` and never receive the theme
tag: `connect_join`, `connect_passport_public`, `connect_front`, `get_started`,
`pro/top`. All measured **5.59–7.93:1** already. They were hand-authored with
accessible values, which is precisely why the defect was confined to the
**derived** tokens. Pinned by test so they stay that way.

---

## 6. Two defects found by the browser that the server test could not see

Recorded because they are the justification for running both layers.

1. **Focus indicators in the raw brand.** The server guard checked
   `outline:2px solid var(--brand)`. But `.form-control:focus` deliberately sets
   `outline:none` and indicates focus with a **border and ring** instead — both
   painted in the raw brand. A pale-gold workspace therefore had a 2.1:1 focus
   indicator. The guard now inspects every rule containing `:focus`.
2. **Links and buttons were never sampled.** The first browser run measured
   `.muted`, labels, headers, body text and pills — and passed. Adding links and
   buttons exposed `a{color:var(--brand)}` and `.btn{color:#fff}`, the widest
   reaching defect in this phase.

A third fault was in the **check itself**: V3 read the computed border
immediately after `focus()`, during a 150 ms CSS transition, so it was measuring
the *unfocused* colour and passed while a deliberately reintroduced defect was
present. It now waits for the transition. Both layers were then mutation-tested.

---

## 7. Mutation tests

| Mutation | Caught by | Result |
|---|---|---|
| `a{color:var(--brand)}` restored | server test E2c | FAIL, as required |
| `$muted` reverted to the fixed 45% mix | server test C | 18 assertions FAIL |
| `.form-control:focus` border back to `var(--brand)` | browser V2 **and** V3 | FAIL at 2.1:1 / 1.94:1 |

All restored; both layers green.

---

## 8. Results

| Check | Result |
|---|---|
| `test_b1_accessibility_tokens.php` | **112 passed, 0 failed** |
| `tools/b1-contrast-check.js` | **15 passed, 0 failed** |
| Mobile 360×800 / 390×844 / 412×915 | **PASS** — no horizontal overflow on any of 8 screens |
| JavaScript errors / failed requests | **none** |
| Full regression — see §10 | |

---

## 9. Deferred, not fixed here

| Item | Why | Phase |
|---|---|---|
| **Gold accent** from the blueprint | `--accent` is wired to `--info` in app.css, so it is the *informational status colour*, not decoration. Changing it would change what a status means — forbidden by B1 Requirement 4. | **B10** |
| `--line` at 3:1 | Shared with table row separators; raising it is a visual-density decision, not an accessibility one. | **B10** |
| Status-tone helpers duplicated **nine** times | Consolidation, not contrast. | **B10** |
| Body text 14px, no 16px mobile floor (F-A2-3) | Changing the base size reflows every screen; belongs with the mobile pass. | **B7** |
| `.topbar` rules styling a shell no view renders (F-A2-4) | Dead CSS, not accessibility. | **B10** |

---

## 10. Regression and protected modules

Recorded in `UX-B-CHANGELOG.md` with the commit hash.

### Full regression

| Engine | Result |
|---|---|
| SQLite | **13,598 passed, 0 failed** |
| **MariaDB (authoritative)** | **13,601 passed, 0 failed** |

### Protected modules — MariaDB

| Module | Suite | Result |
|---|---|---|
| Recruitment | `recruit` | 296 passed, 0 failed |
| Recruitment (employee numbers, races) | `rb3` | 276 passed, 0 failed |
| Workforce identity | `r20` | 44 passed, 0 failed |
| Workforce duplicate doors | `fa7` | 29 passed, 0 failed |
| Operations | `p2` | 437 passed, 0 failed |
| Operations | `p3` | 2,611 passed, 0 failed |
| Reporting | `report` | 352 passed, 0 failed |
| Money / billing | `billable` | 90 passed, 0 failed |
| Money / vouchers | `voucher` | 117 passed, 0 failed |
| Marketplace | `connect` | 1,020 passed, 0 failed |
| Marketplace | `mkt` | 174 passed, 0 failed |
| Tenant isolation & entitlement | `saas` | 169 passed, 0 failed |
| Tenant API | `tapi` | 170 passed, 0 failed |
| Navigation / UX consolidation | `m11` | 60 passed, 0 failed |
