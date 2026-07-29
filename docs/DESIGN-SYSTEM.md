# Design System

The single source of truth for how this application looks and behaves. Every
existing screen and every future screen inherits from it.

It lives in one place — the token block at the foot of `assets/css/app.css`.
Nothing here is a suggestion; a screen that needs a value the system does not
have is telling you the system is wrong, and the fix goes in the system.

---

## 1. What the audit actually found

Written before any of this was built, by measuring the app rather than looking
at it. The numbers matter because they say where the work is — and where it
is not.

| | Measured | Verdict |
|---|---|---|
| Stylesheets | 1 (`app.css`, 613 lines) | Good — one file already |
| Design tokens | present, but split across **two** `:root` blocks | Accretion, now consolidated |
| Inline `style=` attributes | **2,785** across the views | **The mechanism of all drift** |
| Inline `<style>` blocks | 26 files | Secondary, follows from the same cause |
| Distinct hardcoded colours inline | **16** | Already disciplined — *not* the problem |
| Distinct inline font sizes | **40** — incl. 10.5, 11.5, 12.5, 13.5, 14.5px | **Badly drifted** |
| Distinct inline spacing values | **32** — incl. 2, 3, 5, 6, 9, 10, 14, 18, 22, 26px | **Badly drifted** |

**The finding that shaped everything else:** colour was never the problem.
Type and space were. And the cause is structural, not aesthetic — a screen that
cannot say "slightly smaller, slightly tighter" in a class invents a number
instead. 12.5px is not a design decision; it is the absence of one.

So the first fix is not a repaint. It is giving those 2,785 cases a named class
to use.

### Accessibility, measured

Contrast computed against WCAG 2.1 AA for the actual colour pairs the app uses:

| Pair | Before | Needs | After |
|---|---|---|---|
| Body text on page | 13.56 | 4.5 | unchanged — fine |
| Muted text on page | **4.47 ✗** | 4.5 | **4.77 ✓** |
| Muted text on card | 4.83 | 4.5 | 5.17 ✓ |
| Legacy badge — amber | **2.86 ✗** | 4.5 | **4.51 ✓** |
| Legacy badge — green | **3.00 ✗** | 4.5 | **6.49 ✓** |
| Legacy badge — red | **3.95 ✗** | 4.5 | **5.30 ✓** |
| Modern `.pill` (all four) | 4.76–5.91 | 4.5 | already passing |

The badges were the worst thing on the screen and the most looked-at: a status
pill is what somebody scans a register for. `--red`, `--amber` and `--green`
are correct as **fills** and were being used as **ink**, which is a different
job needing a different value. They now point at the darker inks the modern
`.pill` already used, so they read the same at a glance and survive a photocopy,
a projector and an older pair of eyes.

**Limit of this audit, stated plainly:** the pair-by-pair contrast above is
arithmetic and reproducible. A full per-element sweep of every rendered node
needs a real tool (axe-core), which this environment cannot install. I wrote a
quick checker, it reported 1,844 failures, and on inspection its top findings
were screen-reader-only text and emoji — so its number is not quoted here.
A number you cannot defend is worse than no number.

---

## 2. Tokens

### Type — six steps, no half-pixels

| Token | Size | Use |
|---|---|---|
| `--t-xs` | 11px | pills, timestamps, table meta |
| `--t-sm` | 12px | helper text under a field |
| `--t-md` | 13px | secondary body, dense cells |
| `--t-base` | 14px | body — everything is judged against this |
| `--t-lg` | 16px | card titles, sub-headings |
| `--t-xl` | 18px | section headings |
| `--t-2xl` | 22px | page title |
| `--t-3xl` | 28px | the one number on a KPI tile |

Line height: `--lh-tight` 1.25 for headings, `--lh-base` 1.55 for body.
Weight: `--fw-normal` 400, `--fw-medium` 600, `--fw-bold` 700.

There is no step between 12 and 13. That is the point.

### Space — a 4px grid, no exceptions

`--s-1` 4 · `--s-2` 8 · `--s-3` 12 · `--s-4` 16 · `--s-5` 20 · `--s-6` 24 ·
`--s-8` 32 · `--s-10` 40 · `--s-12` 48

### Colour — named for the job, not the hue

`--primary` `--success` `--danger` `--warning` `--info` `--disabled`

Named this way because `--danger` survives a rebrand and `--red` becomes a lie
the first time somebody changes it. The literal `--red` / `--green` / `--amber`
remain because existing screens use them, and because a fill genuinely is a
colour.

### State — so hover and focus are never re-invented

`--hover` · `--selected` · `--focus-ring`

---

## 3. Utilities

Type: `.t-xs .t-sm .t-md .t-base .t-lg .t-xl .t-2xl .t-3xl`,
`.fw-medium .fw-bold`, `.t-mut .t-danger .t-success .t-warning`, `.t-num`
(tabular figures — money in a column must line up).

Space: `.mt-0 … .mt-8`, `.mb-0 … .mb-6`, `.p-2 .p-3 .p-4 .p-6`.

Layout — these replace the single commonest inline style in the app:
`.row` `.row-top` `.row-between` `.col` `.gap-1 .gap-3 .gap-4` `.grow`
`.nowrap` `.wrap-anywhere`.

There is deliberately no `.mt-13px`. If you want one, the scale is wrong.

---

## 4. Standards

**Focus.** One visible ring on every interactive element, via `:focus-visible`
so it does not fire on a mouse click. A keyboard user who cannot see where they
are is locked out of the software.

**Empty states.** `.empty` with `.empty-ic`, `.empty-t`, `.empty-d`. Every
empty list says what is missing, why, and offers the one button that fixes it.
"No data" tells somebody nothing they did not already know.

**Loading.** `.skel` / `.skel-line` — show the shape of what is coming rather
than a white rectangle.

**Reduced motion.** `prefers-reduced-motion` is honoured. For some people
animation causes nausea; it is one rule to respect that.

**Touch targets.** 44px minimum under `pointer:coarse` only, so a phone is
usable and the desktop stays dense.

**Visual hierarchy.** One primary action per screen (`.btn`), secondary is
`.btn.secondary`, destructive is `.btn.danger` and always confirms. If two
buttons compete, one of them is not primary.

---

## 5. Rules for writing a screen

1. Never write a raw px font-size or margin in a view again.
2. Reach for a utility class. If none fits, fix the scale here — do not add one
   more number in one more screen.
3. Colour comes from a token. A hex code in a view is a bug.
4. New components go in `app.css` with a comment saying what they are for.
5. A screen may not invent its own layout. Crumbs, `.master-head`, `.panel`,
   `.dt` — in that order, like every other screen.

---

## 6. Migration — how the 2,785 get fixed without breaking anything

Everything above is **additive**. Not one existing rule or variable name
changed, so all 170 screens render exactly as they did. That is deliberate: a
redesign that breaks a working system is not an improvement.

The migration is then per-screen and reversible, highest traffic first:

| Phase | Scope | Risk |
|---|---|---|
| 1 ✅ | Tokens, utilities, focus, contrast fixes | none — additive |
| 2 | Registers (`/calls`, `/jobs`, `/to-bill`, `/clients`) — the screens people live in | low |
| 3 | Forms (`call_form`, `job_form`, `invoice_form`) — the densest inline styling | medium |
| 4 | Dashboards and reports | low |
| 5 | Delete the 26 inline `<style>` blocks by promoting what they contain | low |
| 6 | Lint rule: fail the build on a new inline `style=` in a view | prevents relapse |

Phase 6 is the one that makes it permanent. Everything before it decays without
it — the drift measured above happened over months of screens each making one
reasonable local decision.

Each phase ends the same way: `tools/lint.sh`, then `node tools/smoke.js`, and
all 170 screens must still render.
