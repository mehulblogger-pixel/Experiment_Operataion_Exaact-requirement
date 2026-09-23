# UX-B6 — Forms and progressive disclosure

**Status:** implemented, evidenced, awaiting review
**Scope:** presentation only. No database change, no new permission, no new status
or transition, no change to any action handler, no field removed.

---

## 1. The headline: the audit was wrong about four of the six forms

B6 was commissioned against finding **F-A5-1**, which says:

| Form | Controls | Disclosure (as the audit stated) |
|---|---:|---|
| Job | 57 | **none** |
| Test request | 54 | **none** |
| Engineer | 48 | **none** |
| User | 40 | **none** |
| Requirement | 77 | stepped |
| Candidate | 32 | disclosed |

Measured in a browser against the running application, that table is wrong for
four of the six rows — and in *both* directions.

| Form | Route | Controls | Visible on open | Panels | Page height | Audit said |
|---|---|---:|---:|---:|---:|---|
| Job | `/job-new?call=N` | 146 | **9** | 5 | 913px | "none" ✗ |
| Test request | `/call-new` | 154 | **8** | 6 | 900px | "none" ✗ |
| User | `/user-new` | 179 | **13** | 4 | 900px | "none" ✗ |
| Engineer (add) | `/m/inspectors/new` | 37 | **33** | 0 | 1162px | "none" ✓ |
| Engineer (edit) | `/m/inspectors/edit?id=N` | 65 | 26 | 4 | 1134px | — |
| Requirement | `/requisition-new` | 82 | 14 | 5 steps | 998px | "stepped" ✓ |
| Candidate | `/candidate-new` | 38 | **29** | 0 | 1436px | "disclosed" ✗ |

Two things follow.

**First, this was not staleness.** The panel engine and its use on the Job, Test
request and User forms arrived in commit `b963490` (27 Aug 2026), which
`git merge-base --is-ancestor` confirms is an **ancestor** of `0b1d58d`, the
commit that first added the A5 audit. The disclosure was already there, in the
tree, when the audit said it was absent.

**Second, the audit's one positive claim is also wrong.** Candidate, listed as
"disclosed", had no disclosure at all — 29 of 38 controls on a single 1436px
page. It was the *densest* undisclosed form in the product, and the audit
pointed the other way.

> The earlier measurement of the Job form as "3 controls, 9628px" was also wrong,
> and the reason is worth recording: `/job-new` requires a `?call=N` parameter and
> redirects to `/calls` without one. That measurement had captured the calls
> **register**, not the job form.

---

## 2. What the application already owns

B6 built no disclosure machinery, because the product already has two mature
mechanisms and a third for configuring fields. All three were reused as-is.

| Mechanism | Where | What it does |
|---|---|---|
| `[data-tabs]` / `[data-tab]` | `assets/js/app.js` · `initSectionTabs()` · 19 views | Turns sibling panels into a tab bar. With `.form-tabs` it also adds Back / Next, "Step N of M", and shows Save only on the last panel. Remembers the open panel in the URL hash. **With scripting off every panel simply renders**, so nothing is ever hidden from somebody who cannot run it. |
| `details.fold` | `assets/css/app.css` · 46 views | A styled inline collapse — heading, chevron, `.fold-body`. |
| Form Designer | `lib/formdesign.php` · `fd_overlay_html()` | Per-company rename / **hide** / reorder / require, applied as a display-only overlay. Emitted by all six of these forms. |

The single most important property of all three: **hiding is display only.** The
tab engine's own comment says it — *"every field stays in the DOM, merely hidden
— so a value typed on step one is still there, and still submitted"* — and the
Form Designer overlay says the same of a hidden field: *"kept in the DOM so
their value is never blanked on save"*. B6 keeps that invariant exactly.

Because that configuration mechanism already exists, B6 did **not** build a
second one. No new way to hide a field was introduced.

---

## 3. What changed

### 3.1 Team member / Engineer — add screen (`views/ops/inspector_form.php`)

The one target form the finding got right, and only on **add**: its Signature,
Certificates and Allowances panels are all `$isEdit`-only, and the panel engine
needs two panels before it draws a tab bar. So editing a person showed four
tabs; adding one showed everything in a single column.

The screen holds several independent `<form>` elements under one `[data-tabs]`
wrapper, so splitting the main form into panels would leave its card and Save
button on screen with no fields whenever a sibling tab was in front. It uses
`details.fold` instead — which also keeps Save visible at all times.

| Group | Fields |
|---|---|
| **Core — always visible** | first name*, middle name, last name, employee code, email, mobile, designation, engineer type, team, trade, status |
| Fold · *Skills and business units* | skills box, business units |
| Fold · *Agency, posting and reporting line* | agency, posted office, weekly working days, reporting manager |
| Fold · *Cost* (behind `can_see_salary()`, unchanged) | annual CTC, agency hiring cost |
| Fold · *First certificate* | certificate name, number, valid from, valid to, file, required-for-work |

**33 → 13** controls on open; **1162px → 900px**.

### 3.2 Candidate (`views/ops/candidate_form.php`)

Four panels on the existing engine: *Requirement & person*, *Role & where*,
*Sourcing & money*, *Paperwork & outcome*.

Declared `[data-tabs]` **without** `.form-tabs`, deliberately. `.form-tabs` is
wizard mode, which folds the action row into the nav and shows Save only on the
last panel. F-A5-1's own recommended treatment is *"named steps, **save
available from step one**"*, and that is what the Requirement form does. Keeping
the Save row outside the panels gives exactly that.

**29 → 11** controls on open; **1436px → 936px**.

### 3.3 The submit guard (`assets/js/app.js`)

This is the part that mattered most, and it is a root-cause fix rather than a
patch.

The application switches the browser's own validation off (`f.noValidate = true`)
and runs its own guard, because a searchable dropdown hides its real `<select>`
and the browser cannot point at a box it cannot see. For a failing box the guard
judged off screen, it did nothing and let the post go to the server to refuse.

Putting fields behind folds and panels made both branches worse:

* a field on another **panel** is `display:none`, so it was posted blind — a
  round trip to learn that a box was empty;
* a field inside a closed **fold** still reports a layout box (a closed
  `<details>` hides its body with `content-visibility`, not `display:none`), so
  it would have been ringed red *inside a fold nobody had opened*.

`activateTabForField()` was renamed `revealField()` and now opens every
`<details>` above the field before bringing its panel to the front. The guard
reaches it through the `invalid` event that its own `checkValidity()` call
fires. A second call re-reveals the box named **first** in the refusal, so the
panel in front is the panel the message is about.

**Nothing about what is required changed.** The server checks exactly what it
checked before; `' required'` occurs 5 times in the team-member form both
before and after, and twice in the candidate form both before and after.

This fix also reaches the Job, Test request and User forms, which B6 did not
otherwise touch: a required box on a panel that is not in front is no longer
posted blind there either.

### 3.4 Touch targets (`assets/css/app.css`)

A panel tab is how a form is navigated on a phone, and `.tabbtn` was 33px
against the blueprint's 44px. One line added to the **existing**
`@media (pointer:coarse)` block covers `.tabbtn` and fold summaries, with
wrapping so a fold's title and its explanation stack rather than squeeze.
`.btn` / `.btn.small` remain deferred to B7/B10 as instructed.

---

## 4. Evidence

| Check | Result |
|---|---|
| `tests/test_b6_forms_disclosure.php` | **113 passed, 0 failed** |
| Browser, desktop (`b6-check.js`) | **26 passed, 0 failed** |
| Browser, phone 390×844 with touch (`b6-mobile.js`) | **6 passed, 0 failed** |

Every trap is armed before it is asserted — that a field really starts on a
closed panel, that a fold really is closed, that the touch media query really
matches, that the stylesheet really loaded, that the viewport is really still
390×844.

### 4.1 Mutation tests — code

| # | Defect reintroduced | Caught by |
|---|---|---|
| M2 | fold-opening loop removed from `revealField` | E6, E8 |
| M5 | panel-opening half removed | C7b, C8b, C8 |
| M6 | re-reveal of the first named box removed | F3 |
| M7 | `checkValidity()` replaced by a cached flag, so no `invalid` fires | C7b, C8b, C8 |
| P1 | a candidate field deleted | D2 |
| P2 | a fold shipped open | B1, B2, B5 |
| P3 | another candidate field quietly made required | E4 |
| P4 | the user form's panels "tidied" away | G2 |

### 4.2 A mutation that **survived**, and what was done about it

**M1 — removing the explicit `revealField(el)` this stage added to the guard
changed nothing: 21 of 21 still passed.** The reason is that the guard's own
`el.checkValidity()` call *fires the element's `invalid` event*, which the
existing listener already handles. The line was dead weight, so it was removed
rather than kept and explained away. The comment left in its place records why
the `checkValidity()` call must not be "tidied" into a cached validity flag —
which is the mutation M7 now guards.

### 4.3 Two measurement faults found and corrected in this stage's own tooling

Both would have produced a confident, wrong answer.

1. **`offsetParent` does not detect a folded field.** A closed `<details>` hides
   its body with `content-visibility`, which leaves `offsetParent` non-null, so
   the first "after" measurement reported 33 visible controls on a screen a
   screenshot plainly showed as collapsed. The counter was corrected to walk the
   `<details>` ancestors — and then **re-run against the original files**, where
   it reproduced the baseline 33 / 29 exactly, proving the reduction is real and
   not an artefact of the new counter.
2. **A `fullPage` screenshot corrupts the next page's measurements.** It
   stretches the emulated viewport to the document height. Measuring the second
   form in the same context reported a 36px Save button that is 44px on every
   clean measurement. The mobile check now uses a fresh context per form and
   screenshots last, and asserts the viewport is still 390×844 before measuring.

A third probe fault was in the regression test itself: a code comment
containing the literal text `<details class="fold">` was being counted as a
fifth fold. The comment was reworded rather than the count fudged.

---

## 5. Deliberately not done

| Item | Why |
|---|---|
| Job / Test request / User restructuring | They already have working disclosure. Changing them would be churn against muscle memory for no measured gain. `tests/test_b6_forms_disclosure.php` §G now guards them against a later stage "tidying" them away. |
| Requirement step rebalancing | Step 1 carries **37 of 73** named fields — half the form on the first step. Real, but this form is the *reference pattern*, and its contract ("you can save after step 1") makes moving fields off step 1 a product decision, not a UX one. **Recorded as a finding, not changed.** |
| Removing any field | The brief's hardest rule. Nothing was removed. The field inventory of both edited forms is byte-identical before and after. |
| Prefilling anything new | Out of scope for B6 as delivered; F-A5-2 is a separate finding. No inference, fuzzy matching or guessing was added. |
| `.btn` / `.btn.small` touch targets | Deferred to B7/B10 as instructed. |
| Global header search button (34px) | Pre-existing on every screen, not a form control. Recorded for the stage that owns global touch targets. |
| Consolidating the Requirement form's own stepper onto the shared engine | It is a second, form-specific implementation (`.rq-sec[data-step]`). Consolidating it is a B10 candidate, not a B6 change. |

## 6. Open items carried forward

* **ADR-001 remains OPEN.** Nothing here selects, implies or ranks a recruitment
  entry path.
* Requirement Step 1 density (37 of 73) — product decision.
* Requirement stepper vs the shared panel engine — B10.
* `.btn` / `.btn.small` and the header search button touch targets — B7/B10.
* Hiring Request `allowed_next()` — product decision, untouched.
* Nine hand-written `.nowband` blocks — B10.
