# Admin / demo-builder guide (for Us)

> Use this while **preparing the app for a prospect or a new client**. It gives the
> order to set things up in, exact click-paths, and a checklist to sign off before
> you show anyone.

Read [`01-masters-explained.md`](01-masters-explained.md) first if you haven't.

---

## 0 · Before you start

- Log in as an **admin-level** user (Manager / Admin / Super Admin). Layers 2 and 3
  are admin-only.
- Know the prospect's world in one paragraph: their business units, the words they
  use, the extra fields they care about. That paragraph *is* your build list.
- Decide the vocabulary first (see step 1) — doing it last means re-recording the demo.

---

## 1 · Set the vocabulary (Terminology) — do this FIRST

`Settings → Terminology`.

Rename the app's words to the client's words **before** you build lists, so every
screen and every SOP screenshot already reads in their language.

Common ones:

| App default | Rename to (example) |
|---|---|
| Work Order | Call / Job / Assignment |
| Client | Customer / Party |
| Vendor | Supplier / Manufacturer |
| Inspection engineer | Inspector / Surveyor |

> Why first: Terminology changes labels everywhere. If you build lists and record a
> demo, then rename "Work Order" to "Call", you have to redo the walkthrough.

---

## 2 · Layer 1 — put in the real records

`Masters` (`/masters`) → **1 · Records you keep**.

Add just enough to look real:

1. **Offices / branches** — at least the head office + one branch.
2. **Clients** — 2–3, with full details (address, GST, a contact). A thin client
   record makes the Call form nag about missing master details, which looks bad in a
   demo.
3. **Vendors** — 1–2 if the demo touches vendor inspection.
4. **Working norms** — only if the demo shows scheduling / utilisation.

**Checklist:** every client you'll use in the demo has an address and at least one
contact.

---

## 3 · Layer 2 — shape the dropdown lists

`Masters → All master lists` (`/lookups`).

### 3.1 Edit the built-in lists to match the client
Open the lists the demo will show and make the **choices** theirs:

- **Business Unit** — replace with their real units (e.g. *Third-Party Inspection,
  Vendor Inspection, NDT, Environmental*).
- **Region** — their regions.
- **Inspection result / Report status / Payment term** — their wording.

Click a list → edit / add / delete choices.

### 3.2 Add a dependent list (the wow moment)
The classic is **Activity under Business Unit**:

1. `/lookups` → **Add a new master list**.
2. **List name:** `Activity`.
3. **Depends on:** choose `Business Unit`.
4. **Show this list on:** tick **Call** (and **Job** if the demo carries it through).
5. **Create list.**
6. You're taken to the value editor. Add each Activity and pick which **Business
   Unit** it sits under.

Now on the Call form, choosing a Business Unit filters the Activity dropdown live.

> Deeper chains work the same way — make *Product*, then *Wax type* depending on
> *Product*, then *Tier* depending on *Wax type*. When you put the deepest list on a
> form you get all three cascading selects automatically.

### 3.3 Add a brand-new plain list
Anything the client picks that we don't ship. Example — *Rig type*:

1. `/lookups` → **Add a new master list** → name `Rig type`, leave "Depends on" empty.
2. Tick the forms it shows on → **Create list** → add the choices.

### 3.4 Put an existing list on a form
Open the list → **"Appears on these forms"** panel → tick/untick the forms →
**Save where it appears**. Unticking hides it but keeps any data already chosen.

**Checklist:** the `/lookups` table's **"Shows on"** column reads correctly for every
list the demo uses.

---

## 4 · Layer 3 — add extra fields the form is missing

`Masters → Fields on the … form` (`/custom-fields?entity=call`).

Use this when the client wants a box that isn't there:

- Plain **text / number / date** (Layer 2 can't do these — only Layer 3 can).
- A field on a form **not** in the Layer-2 quick tick-list (e.g. a requisition, a
  sample, a custom form).
- A field that must be **required**.

Steps: pick the **Form** at the top → **Add a field** → label, type, (for a dropdown)
the master list, order, required → **Add field**. It appears on that form
immediately.

> For a cascading custom field, set type = **Dependent dropdown** and pick the
> **deepest** list (e.g. *Tier*). It renders Product → Wax type → Tier on its own.

---

## 5 · Walk the forms as the client will

Open each form the demo touches (New Call, New Job, New Client) and confirm:

- Every dropdown shows the client's choices, in their words.
- The dependent dropdown filters correctly (pick the parent, watch the child change).
- Custom fields appear in the right order, required ones are starred.

---

## 6 · Demo-ready checklist

- [ ] Terminology set to the client's words (done **before** anything else).
- [ ] 2–3 fully-detailed clients; 1–2 vendors if needed.
- [ ] At least one office/branch.
- [ ] Built-in lists (Business Unit, Region, statuses) edited to the client's world.
- [ ] One dependent list demonstrated (Activity under Business Unit).
- [ ] Every demo list's **"Shows on"** column is correct.
- [ ] Any missing boxes added as custom fields, in sensible order.
- [ ] Walked New Call / New Job / New Client end-to-end — no stray defaults, no empty
      dropdowns, no "missing master details" nag.
- [ ] Recorded/rehearsed after all of the above (never before Terminology).

---

## Pitfalls we've hit

- **Renaming words after recording** — always set Terminology first.
- **Empty dropdown in the demo** — you made the list and put it on a form but never
  added choices. Add values before showing it.
- **Thin client record** — the Call form asks for master details the client is
  missing; fill clients fully.
- **Two lists doing the same job** — check the `/lookups` table before adding a new
  list; a built-in one may already exist.
- **Deleting a built-in list to "clean up"** — don't. It falls back to shipped
  choices but disappears from the admin screen and confuses the next person. Just
  edit its choices instead.
