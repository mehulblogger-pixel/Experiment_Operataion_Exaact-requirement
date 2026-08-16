# Masters explained — the whole screen in 5 minutes

> Read this first. Everything else in this folder assumes you know these three layers.

## Why "Masters" exists

A form should never make someone **type** a choice that everyone should be
**picking** from the same list. If ten people type the business unit by hand, you
get ten spellings and no report can count them. So we set the choices up **once**,
in Masters, and every form offers the same list.

"Masters" is simply **all the reference data the app draws from**. It comes in three
layers.

---

## Layer 1 — Records you keep

**What:** real things and people you maintain one by one.

- Offices / branches
- Back-office staff
- **Clients** (the party that engages you)
- **Vendors** (the manufacturer / supplier)
- Working norms (weekly days & hours)

**How you use it:** open the card, add records, edit records. A client is a full
record — name, address, GST, contacts — not just a word in a dropdown.

**Rule of thumb:** if it has *many details* (address, phone, contacts), it belongs in
Layer 1.

---

## Layer 2 — Dropdown lists (the important one)

**What:** the choices behind every dropdown in the app. For example:

- Business Unit, Region, Activity
- Inspection result, Report status
- Payment term, Quote status
- Designation, Department, Leave type

**Two kinds of list:**

### a) A plain (top-level) list
Just a set of choices. Example — **Region**: North, South, East, West.

### b) A dependent list (a list under a list)
The choices change depending on what was picked in a *parent* list.

> **Example — Activity under Business Unit.**
> Pick Business Unit = *Inspection* → Activity offers *Vendor Inspection, Stage
> Inspection, Final Inspection*.
> Pick Business Unit = *Oil & Gas* → Activity offers *Welding Inspection, NDT
> Witness*.
>
> You can go deeper: Product → Wax type → Tier is three levels. Any depth works.

**Where:** `Masters → All master lists` (`/lookups`).

**The new bit — "Show this list on…"**
When you make a list you can tick **which forms it should appear on** right there. The
three everyday forms (Call, Job, Client/Vendor) are shown up front; **"More forms…"**
opens the rest — Operations & compliance (Sample, Test method, Risk, Decision rule,
Controlled document, Satisfaction survey), People & hiring (Requisition, Candidate),
your Master records, and any custom forms you've built. You no longer have to make the
list *and then* go somewhere else to put it on a form.

**Rule of thumb:** if it's *just a word you pick from a short menu*, it belongs in
Layer 2.

---

## Layer 3 — Extra fields on a form

**What:** your own boxes added to a form that didn't have them.

- A text box ("Rig number")
- A number ("Thickness in mm")
- A date ("Calibration due")
- A **dropdown** that uses any Layer-2 list (including a dependent one, which gives
  cascading selects)

**Where:** `Masters → Fields on the … form` (`/custom-fields?entity=call`).

**Rule of thumb:** reach for Layer 3 only when the form is *missing a box*. If you
just need to change the *choices inside an existing dropdown*, that's Layer 2.

---

## How the three layers connect

```
Layer 2 (a dropdown list)  ──feeds──►  a dropdown on a form
        │                                     ▲
        └── ticking "Show on Call" ───────────┘  (creates the field for you)

Layer 3 (custom field)  ──can point at──►  a Layer 2 list
```

- Ticking **"Show this list on the Call form"** in Layer 2 quietly creates a Layer-3
  field for you. Same result, one step.
- Use Layer 3 directly when you want a **plain text/number/date** box, want the field
  on a form that isn't in the quick tick-list, or want to mark a field **required**.

---

## Built-in vs Custom lists

| | Built-in list | Custom list |
|---|---|---|
| Who made it | Ships with the app | You made it |
| Can edit choices | Yes | Yes |
| Can delete the list | Only Super Admin (it falls back to shipped choices) | Anyone (admin) |
| Example | Business Unit, Inspection result | "Rig type", "Client tier" |

Deleting a **built-in** list can never break a screen — the form falls back to the
choices shipped in the code. Deleting a **custom** list removes it everywhere.

---

## Renaming words (Terminology)

The *words* on the screen are yours. If your company calls a "Work Order" a "Call",
go to **Settings → Terminology** and rename it once — every screen follows.

This is **different** from Masters: Terminology changes the *label of a thing* (the
word "Job"); Masters changes the *choices inside a dropdown*.

---

## The five-second decision guide

- Real thing with lots of details → **Layer 1 (a record)**
- A word people should pick, not type → **Layer 2 (a dropdown list)**
- Choices depend on another choice → **Layer 2, dependent list**
- The form is missing a box → **Layer 3 (a custom field)**
- The *word itself* is wrong → **Settings → Terminology**
