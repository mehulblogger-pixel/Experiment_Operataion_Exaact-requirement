# Using Master Lists — a guide for your team

> This is for the **client's own administrator**. It explains, in plain language, how
> to manage the choices your forms offer. You do **not** need any technical
> knowledge. If a screen ever looks confusing, come back to this page.

---

## What is a "master list"?

Every dropdown in the app — Business Unit, Region, Activity, statuses, and so on —
gets its choices from a **master list**. Set a list up once and everyone on your team
picks from the same choices, instead of typing them differently each time. Clean
lists mean clean reports.

There are **three layers**. You'll mostly use the second one.

1. **Records you keep** — your offices, staff, customers and suppliers.
2. **Dropdown lists** — the choices behind every dropdown. *This is the one you'll
   use most.*
3. **Extra fields** — your own boxes added to a form.

You reach all of them from **Masters** in the menu.

---

## Task 1 — Add or change a choice in a dropdown

*Example: add a new Region called "West".*

1. Menu → **Masters**.
2. Under **2 · Dropdown lists**, click the list you want (e.g. **Region**). Or click
   **All master lists** and pick it there.
3. In **Add a value**, type the choice (e.g. `West`) → **Add value**.
4. To change one, click **Edit** next to it. To remove one, click **Delete**.

That's it — the new choice appears in that dropdown everywhere, straight away.

> To hide a choice without losing history, edit it and set **Active** to No (where
> shown), rather than deleting it.

---

## Task 2 — Make a brand-new list

*Example: a "Rig type" list that isn't in the app yet.*

1. Menu → **Masters → All master lists**.
2. Open **➕ Add a new master list**.
3. **List name:** `Rig type`.
4. **Show this list on:** tick the forms it should appear on (e.g. **Call**).
5. **Create list.**
6. On the next screen, add your choices (each one → **Add value**).

Your new dropdown is now on the forms you ticked.

---

## Task 3 — Make a list that depends on another

*Example: "Activity" should change depending on the "Business Unit" chosen.*

1. Menu → **Masters → All master lists → ➕ Add a new master list**.
2. **List name:** `Activity`.
3. **Depends on:** choose **Business Unit**.
4. **Show this list on:** tick **Call**.
5. **Create list.**
6. Add each Activity, and for each one pick which **Business Unit** it belongs under.

Now, on the form, choosing a Business Unit automatically narrows the Activity list to
the right choices.

---

## Task 4 — Put an existing list on a form (or take it off)

1. Menu → **Masters → All master lists** → click the list.
2. Open **🖥️ Appears on these forms**.
3. Tick the forms it should show on; untick to remove it.
4. **Save where it appears.**

Removing a list from a form only **hides** it — anything already chosen stays saved.

---

## Task 5 — Add a box the form doesn't have

*Example: add a "Rig number" text box to the Call form.*

1. Menu → **Masters → Fields on the Call form**.
2. **Add a field** → label `Rig number`, type **Text** → **Add field**.

For a dropdown box, choose type **Dropdown** and pick which master list it uses.

---

## Task 6 — Change a word the app uses

If the app's wording doesn't match yours (say it calls something a "Work Order" and
you call it a "Call"):

- Menu → **Settings → Terminology** → change the word → save.

Every screen updates. (This changes the *word*, not the choices in a dropdown.)

---

## Do's and don'ts

**Do**

- Edit a list's choices freely — it's safe and instant.
- Use a dependent list when one choice should narrow another.
- Keep customer and supplier records complete (address, contact) so forms don't nag.

**Don't**

- Don't type a choice by hand on a form if it should be a list — add it to the list
  instead, so everyone shares it.
- Don't delete a built-in list to tidy up; just edit its choices.
- Don't create a second list that does the same job — check what's there first.

---

## Quick answers

| I want to… | Go to |
|---|---|
| Add/change a choice in a dropdown | Masters → the list → Add value |
| Make a new dropdown | Masters → All master lists → Add a new master list |
| Make one dropdown depend on another | …Add a new master list → set **Depends on** |
| Put a list on a form | The list → **Appears on these forms** |
| Add a text/date box to a form | Masters → Fields on the … form |
| Change a word the app uses | Settings → Terminology |
