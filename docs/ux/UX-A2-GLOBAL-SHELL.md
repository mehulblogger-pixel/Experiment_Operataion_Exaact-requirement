# UX-A2 — The global shell

**Phase A, step 2. Audit only — no code changed.**

Scope: `views/layout_top.php`, `assets/css/app.css`, `theme_style_tag()` in
`lib/access.php`, the sidebar, the top bar, breadcrumbs and the theme system,
measured against `docs/05-ui-ux-blueprint.md`.

Findings are numbered, classed **cosmetic / structural / workflow**, and carry
the evidence they rest on. Where something is already right, that is stated too —
an audit that only lists faults gives a false picture of the work needed.

---

## F-A2-1 · The stylesheet and the running product are different colours
**Class: cosmetic · Severity: HIGH · Confidence: browser-verified**

`app.css` line 1 declares the design tokens:

```
--brand:#1e40af   (deep blue)
--accent:#0ea5e9  (sky blue)
```

The blueprint mandates **Deep Teal** primary with a **controlled gold** accent.
Neither is in the stylesheet.

The teal you actually see is injected at runtime by `theme_style_tag()`
(`lib/access.php:926`), which re-declares `:root` on every page from tenant
settings. So:

- reading `app.css` tells a designer the product is **blue**;
- a workspace that has never set `c_primary` **gets blue**, because the fallback
  on line 927 is `'#1e40af'`;
- measured live on `/requisitions`: `--brand` = `#1e40af`. Blue, not teal.

**Why it causes confusion:** anyone designing or building against the stylesheet
designs against the wrong palette, and the blueprint's stated primary colour is
not the product's default.

**Recommended treatment:** make the stylesheet's declared defaults *be* the
blueprint (Deep Teal primary, gold accent), so the theme engine overrides a
correct base rather than rescuing an incorrect one. Tenant theming is untouched.

**Risk:** low. Token values only; no selector, markup or logic changes.

---

## F-A2-2 · Helper text fails WCAG AA on every page — because the theme engine overrides a value that passed
**Class: cosmetic · Severity: HIGH · Confidence: browser-verified**

This is the most consequential finding in the shell, and it is the opposite of
what it looks like.

`app.css` hardcodes `--muted:#656e7a`. On white that measures **5.17:1** —
comfortably past the 4.5:1 AA needs. **The stylesheet is accessible.**

But `theme_style_tag()` recomputes it on every page load as a blind 45 % blend of
surface and ink, and emits it into `:root`, overriding the good value:

```php
$muted = theme_mix($surface, $ink, 0.45);
```

Measured in Chromium on `/requisitions`, on a real `.sub` element:

| | |
|---|---|
| `--muted` as declared in app.css | `#656e7a` — 5.17:1 **PASS** |
| `--muted` as the browser computes it | `#9a9fa5` |
| Live contrast on the element | **2.46:1 — FAIL** |

And this happens at the **default** colours. A tenant does not have to choose
anything unusual; the derivation fails on its own:

| Surface | Ink | Derived muted | Contrast |
|---|---|---|---|
| `#ffffff` | `#1f2937` (default) | `#9a9fa5` | 2.67:1 FAIL |
| `#111827` | `#e5e7eb` (dark) | `#70757f` | 3.84:1 FAIL |
| `#f7f6f4` | `#4b5563` | `#aaaeb3` | 2.07:1 FAIL |
| `#ffffff` | `#6b7280` | `#bcc0c6` | 1.83:1 FAIL |

`--muted` colours nearly all helper text, field labels, sub-headings and table
headers, so this is not a corner case — it is most of the secondary text in the
product.

**Why it causes confusion:** the brief's instruction "avoid tiny grey text, never
use low-contrast text" is currently violated by default, and the blueprint's
"maintain WCAG throughout" is not met. In bright sunlight on a site — the
blueprint's own test condition — this text is the first thing to disappear.

**Recommended treatment:** keep the derivation, but floor it. Darken (or lighten,
on a dark surface) the mix until it clears 4.5:1 against the surface it will sit
on, exactly as `theme_style_tag()` already does for `$navtext` using a luminance
test. The machinery to do this is already in the file; it is simply not applied
to `--muted`.

**Risk:** low, and entirely visual. No tenant loses their chosen colours — only
the derived grey moves, and only far enough to be readable.

---

## F-A2-3 · Body text is 14px, including on a phone
**Class: cosmetic · Severity: MEDIUM · Confidence: browser-verified**

`--fs` defaults to `14px`; `body{font-size:var(--fs)}`; the admin setting clamps
to 12–20 with a default of 14. **No media query raises it on a small screen** —
verified: zero mobile `@media` blocks touch body font size. Live on a phone
viewport, body renders at 14px.

The blueprint is explicit: *"Never reduce body text below 16px on mobile."*

The requirement form already fixes this locally (`.rq-sec .form-control{font-size:16px}`
below 760px), which also stops iOS zooming on focus — but it is one screen's fix,
not the product's.

**Recommended treatment:** raise the floor at the mobile breakpoint globally
rather than per screen. The per-screen fix then becomes redundant and can be
folded in.

**Risk:** low, but it is a *visible* reflow on every screen, so it belongs in the
same batch as the other typography work and needs the mobile walk re-run.

---

## F-A2-4 · Dead CSS for a shell that no longer exists
**Class: cosmetic · Severity: LOW · Confidence: verified by grep**

`app.css` carries roughly **20 rules** for `.topbar`, `.nav-wrap` and
`.nav-toggle`, including a full `@media (max-width:900px)` block that builds a
collapsing mobile menu.

**No view renders that shell.** `class="topbar"` appears in **0** of 401 views.
The live shell is `.topbar-slim` + `.side`, which has its own, separate rules.

`theme_style_tag()` also emits three `.topbar …` selectors into every page's
inline `<style>` on every request, styling nothing.

**Why it matters:** it is not a user-visible bug. It matters because the next
person to change the top bar will edit the dead rules, see no effect, and
conclude the CSS is not loading — which is how an afternoon disappears.

**Recommended treatment:** remove, in the same batch as the token work, with a
grep-based assertion that nothing renders those classes.

**Risk:** low — but it must be a *measured* removal, not an assumed one: the
portals and public Connect pages use different layouts and must be checked
before deleting, not after.

---

## F-A2-5 · Four sidebar entries all mean "tell me what needs attention"
**Class: structural · Severity: MEDIUM**

The first five sidebar entries are:

```
🏠 Dashboard            /
🧭 Owner home           /owner
🔍 Search records       /search
🔗 Where the flow is broken  /flow-gaps
🧭 What to fix          /advisor
```

Four of those five answer the same user question — *what needs my attention?* —
and two of them share the same 🧭 icon. Nothing in the labels tells a user which
to open first, or how "Where the flow is broken" differs from "What to fix".

**Why it causes confusion:** this is precisely the brief's *"Which of these 8
screens should I open?"*. It fails the 3-second navigation rule at the very top
of the menu, which is the worst place to fail it.

**Recommended treatment:** for B1/C3, not to be decided here. The likely shape is
one **Home** that absorbs today/needs-attention, with the diagnostic screens
(`/flow-gaps`, `/advisor`) reached from within it rather than competing with it.
Nothing is deleted.

**Risk:** structural — changes routes users may have bookmarked. Needs the
existing route tests plus a redirect check.

---

## F-A2-6 · The Recruitment home is the "database table" screen the brief warns about
**Class: structural · Severity: MEDIUM**

The sidebar is **not** a list of tables — it is 21 reasonable entries. But
`/recruitment-cc` puts **20 distinct destinations on one screen**, with
configuration at the same visual weight as daily work:

> availability · candidate · candidate-new · candidates · careers-admin ·
> comp-setup · departments · doc-templates · my-approvals · positions ·
> positions-import · positions-org · project-costings · recruit-approvals ·
> recruit-export · recruit-pipelines · requisition · requisition-new ·
> requisitions

Pipelines, document templates, compensation setup and org-chart import are
level-4 configuration sitting beside "candidates".

**Recommended treatment:** B1/C3 — level 2 keeps needs-attention, open positions,
candidates, hired; configuration moves to level 4 under Admin **and stays
reachable from the module home under a "Set up" grouping**. Hide is not delete.

---

## What is already right

Stating these matters, because they should not be "improved" in this pass:

- **Contrast of the fixed palette.** `--ink` on card measures 14.68:1 and
  `--brand` 8.72:1. The problem in F-A2-2 is the *derived* grey, not the palette.
- **The badge contrast fix is already done**, and the reasoning is written into
  the stylesheet: the legacy badges used fill colours as ink at 2.86–3.95:1 and
  were re-pointed at darker inks measuring 4.51–6.49:1. That is exactly the
  standard the rest of this pass should meet.
- **`theme_style_tag()` already guards navigation contrast** with a luminance
  test for `$navtext`/`$navlink`. The mechanism F-A2-2 needs exists in the same
  function.
- **Breadcrumbs reach 274 of 401 views** — good coverage, not a gap.
- **Touch targets** are already handled at the mobile breakpoint
  (`.btn,.tabs a,select.form-control,input.form-control{min-height:44px}`).
- **The sidebar has a search box and collapsible groups** — a real navigation
  aid, and the collapsed rail is properly built.

---

## Summary

| ID | Finding | Class | Severity |
|---|---|---|---|
| F-A2-1 | Stylesheet declares blue; blueprint says teal; default is blue | cosmetic | HIGH |
| F-A2-2 | Theme engine overrides compliant `--muted` with a 2.46:1 value | cosmetic | HIGH |
| F-A2-3 | Body text 14px, no mobile floor | cosmetic | MEDIUM |
| F-A2-4 | ~20 rules of dead shell CSS | cosmetic | LOW |
| F-A2-5 | Four top-level entries answer the same question | structural | MEDIUM |
| F-A2-6 | Recruitment home carries 20 flat destinations | structural | MEDIUM |

**Nothing here requires a product decision.** All six are presentation or
navigation; none touches permissions, lifecycles, financial logic or the data
model. F-A2-5 and F-A2-6 change routes and so belong to the navigation batch
(C3) with route tests, not to the CSS batch.
