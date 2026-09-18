# Phase 4 — Screens

*Governed by `docs/05-ui-ux-blueprint.md`. The blueprint is a UI/UX law, not a
permission grant: nothing on these screens decides anything — every button is
re-decided by the engine, which a crafted POST reaches exactly as the form does.*

---

## The allocation panel (requisition screen)

Sits directly under the existing vacancy counts, because it answers the next
question a coordinator asks after *"how many are still open?"* — namely *"so
where are the remaining people coming from?"*

**What it shows, in one glance:**

```
Where these people come from      20 approved · 15 promised to 3 sources · 5 not yet sourced

████████████░░░░░░░░░░░░░░░░░░░░░
■ 8 joined  ·  ■ 7 promised, still to arrive  ·  ■ 5 still to be sourced

Source                          Promised  Arrived  Still owed
Own payroll                           10        6           4    [ 10 ] Change   Give back
Manpower supply agency — Sterling       5        2           3    [  5 ] Change   Give back
```

One bar, one row per source, one box to add another. The bar is the whole status
report: green is *joined*, blue is *promised but not yet here*, grey is *not yet
sourced*.

**Zero-Training gate:**

1. *Understood in 5 seconds?* The heading is a sentence a coordinator already
   says out loud — "twenty approved, fifteen promised, five to source".
2. *Primary task in 2 minutes?* Adding a source is one row: pick, type a number,
   press. The number box is **pre-filled with everything still unsourced** and
   capped at it, so the commonest action is two clicks.
3. *Usable on a phone in bright sunlight?* The panel wraps to a single column;
   the bar and the big numbers carry the meaning without reading the table.
   Coordinators are desk-first, so the table is optimised for a laptop — but it
   is not unusable on a phone.
4. *Jargon?* None. Not "allocation", not "fulfilment model" — "where these people
   come from", "promised", "arrived", "still owed", "give back".
5. *80% without a manual?* The one non-obvious concept — that a person found
   directly also uses up a seat — is stated in the panel itself the moment it
   matters, in words, with the number and the remedy.

**What the panel refuses to do:**

- It never hides a refusal behind a missing button. When the requirement cannot
  be sourced, it says **why** in plain words rather than silently omitting the
  form.
- It never shows a form that cannot succeed: with nothing left to source it
  explains what to do instead ("reduce or give back one of the sources above").
- It carries the total it was showing in a hidden field, so a screen left open
  while somebody else allocated is **refused**, not silently added on top.

## The over-commitment notice

When somebody joins directly after promises were already made, the requirement is
over-committed. Phase 4 never removes anybody and never trims a supplier's
promise on its own, so the panel says so:

> **1 too many.** 1 person has joined without a named source, so 1 of the
> promised seats is already filled. Reduce or give back one of the sources below
> by 1. *Nobody has been removed and no promise has been changed for you.*

That last sentence is deliberate. A person seeing an alarming number needs to
know immediately that nothing has been done behind their back.

## The candidate screen

**On the form** — *"Arriving through"*, listing only sources that still have a
seat (plus whichever one this person already holds, so an existing link never
vanishes from its own form). Shown only once the requirement is known, so the
field can never be the reason a long form is refused. The help text does the
teaching: *"Leave this blank if the person was found directly. It only decides
which source gets the credit — never whether they get the job."*

**On the detail screen** — one line in the details grid, *Arriving through:
Manpower supply agency — Sterling*, or *found directly*. Display only.

## Responsive and accessible

The panel is flex-based and reflows to one column; the table scrolls
horizontally rather than squashing; every input carries a label (the compact
in-row quantity box uses `aria-label`); colour is never the only carrier of
meaning — every segment of the bar is also named in the legend with its number.
