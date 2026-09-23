# UX-SCORECARD (internal)

**Part 41. For identifying which screens need work — not for publication.**

Scored **1–5**, 5 best. A cell is scored **only where this audit actually
measured it**; `–` means not measured, and is left blank rather than guessed.
Several screens were measured on a sparse test workspace, so a low Action score
may reflect an empty record rather than an empty design — flagged where it
applies.

**Dimensions:** Nav = navigation clarity · Vis = visual clarity ·
Act = action clarity · Den = information density · Mob = mobile usability ·
Err = error clarity · Flow = workflow continuity · Sta = status clarity ·
Rel = data-relationship clarity.

---

## Screens measured

| Screen | Nav | Vis | Act | Den | Mob | Err | Flow | Sta | Rel | Worst |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|---|
| **Masters → Add a person** | 4 | 4 | 4 | 3 | – | **1** | 3 | – | 2 | **Err — raw SQLSTATE (F-A7-1)** |
| **Recruitment Command Centre** | **2** | 3 | **2** | **1** | – | – | 3 | 4 | 2 | Den — 2,643px, 43 links |
| **Test request (record)** | **2** | 3 | **1** | 2 | – | – | 4 | 4 | 3 | Act — 6 primaries, no breadcrumb |
| **Requirement (record)** | 4 | 4 | **2*** | 3 | – | – | 4 | 4 | 2 | Act — 0 primary (*sparse data) |
| **Job form** | 3 | 3 | 3 | **1** | 2 | – | 4 | 4 | 3 | Den — 57 fields, no disclosure |
| **Test request form** | 3 | 3 | 3 | **1** | 2 | – | 4 | – | 3 | Den — 54 fields |
| **Engineer form** | 4 | 3 | 3 | **2** | 2 | **1** | 3 | 3 | 2 | Err + Den |
| **User form** | 4 | 3 | 3 | **2** | 2 | – | 4 | – | 2 | Den — 40 fields |
| **Dashboard** | 3 | 4 | **2** | **2** | 3 | – | 4 | 4 | 3 | Den — 30 unranked cards |
| **Money (area home)** | 4 | 4 | 3 | 4 | 4 | – | 3 | **1** | 2 | Sta — no counts at all |
| **Quality (area home)** | 3 | 4 | 3 | 3 | 4 | – | 3 | **1** | 2 | Sta — 28 links, 0 counts |
| **Marketplace (area home)** | 3 | 4 | 3 | 3 | 4 | – | 3 | **1** | 2 | Sta + flat, ungrouped |
| **Sales / Directory / Insights** | 4 | 4 | 3 | 4 | 4 | – | 3 | **1** | 2 | Sta |
| **Admin (area home)** | 4 | 4 | 3 | 4 | 4 | – | 3 | 2 | 2 | well grouped, 7 sections |
| **Operations home** | 3 | 4 | 3 | 3 | – | – | 4 | 3 | 3 | — |
| **Owner home** | 4 | 4 | 4 | 4 | – | – | 4 | 3 | 3 | most focused screen measured |
| **Requirement form** | 5 | 4 | 4 | 4 | **5** | 4 | 4 | 4 | 3 | the reference implementation |
| **Form Designer** | 4 | 4 | 4 | 4 | **5** | 3 | 4 | 3 | 3 | — |
| **Candidate (record)** | 4 | 4 | 4 | 3 | – | – | 4 | 4 | **4** | best relationship clarity |
| **List screens (general)** | 3 | 4 | 3 | 3 | **1** | – | 4 | 4 | 2 | Mob — sideways scroll |
| **`/search`** | 4 | 4 | 3 | 4 | 4 | – | 3 | – | **1** | Rel — Recruitment absent |

\* measured on a sparse workspace; C5 must re-check across lifecycle states.

---

## Where the work is

Counting every score of **1 or 2**:

| Dimension | Screens scoring 1–2 | Root finding |
|---|---:|---|
| **Status clarity** | 6 | F-A3-1 — 103 tiles, 0 counts |
| **Information density** | 6 | F-A5-1, F-A3-3, F-A3-4 |
| **Data relationships** | 9 at ≤2 | F-A8-1/2/3 — definitions hidden, 5 missing |
| **Mobile** | 4 | F-A4-3 — 249 of 251 tables |
| **Action clarity** | 4 | F-A6-3 — both too many and too few |
| **Navigation** | 2 | F-A2-5, F-A3-3 |
| **Error clarity** | 2 | F-A7-1 |

**Data-relationship clarity is the weakest dimension across the product** — nine
screens at 2 or below — and it is also the cheapest to lift: 30 definitions that
already exist or need one sentence each, surfaced where the word is used.

**Status clarity is the second weakest and the cheapest of all** — the badge is
already built and rendered; 103 tiles simply never pass a number to it.

## The two reference screens

The **Requirement form** and the **Form Designer** score highest, and both were
rebuilt during this session. They are not aspirational: their patterns — stepped
disclosure, save-from-step-one, labelled cards instead of sideways tables, a
guard test that refuses a half-wired screen — are in the repository and verified
in a browser. **C-phase should copy them rather than invent.**
