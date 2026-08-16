# Masters — one-page cheat sheet

**Three layers, one question: "which layer is this?"**

| Layer | What it is | Where | Example |
|---|---|---|---|
| **1 · Records** | Real things & people | `Masters` | Offices, Clients, Vendors |
| **2 · Dropdown lists** | Choices behind dropdowns | `Masters → All master lists` (`/lookups`) | Business Unit, Region, Activity |
| **3 · Extra fields** | Your own boxes on a form | `Masters → Fields on the … form` (`/custom-fields`) | "Rig number", "Calibration due" |

## The five-second decision

- Real thing, lots of details → **Layer 1**
- A word people should pick, not type → **Layer 2**
- Choices depend on another choice → **Layer 2, dependent list** (set *Depends on*)
- Form is missing a box → **Layer 3**
- The *word itself* is wrong → **Settings → Terminology**

## Common click-paths

| Task | Path |
|---|---|
| Add a choice to a dropdown | Masters → the list → **Add value** |
| New dropdown list | `/lookups` → **Add a new master list** |
| Dependent (cascading) list | …Add a new master list → set **Depends on** the parent |
| Put a list on a form | The list → **🖥️ Appears on these forms** → tick → Save |
| See where every list shows | `/lookups` → **"Shows on"** column |
| Add a text/number/date box | `/custom-fields?entity=call` → **Add a field** |
| Cascading custom field | Add a field → type **Dependent dropdown** → pick the **deepest** list |
| Rename a word app-wide | **Settings → Terminology** |

## Two facts that prevent mistakes

1. **Ticking "Show this list on the Call form" builds the form field for you.** Making
   a list and putting it on a form is now one step, not two.
2. **Deleting a built-in list can't break a form** — it falls back to shipped choices
   — but it disappears from the admin screen. Edit choices instead of deleting.

## Demo build order (for Us)

1. **Terminology** (set the client's words) — *first, always.*
2. **Records** — offices, clients (full details), vendors.
3. **Dropdown lists** — edit built-ins; add one dependent list (Activity under
   Business Unit); tick which forms each shows on.
4. **Extra fields** — only for boxes the form is missing.
5. **Walk every form**, then record.
