# UX-A6 — Record pages, status design and buttons

**Phase A, step 6. Audit only — no code changed.**

Scope: Parts 6 (one primary action), 7 (record detail pattern), 8 (status
design), 20 (breadcrumbs), 25 (button system).

**This step begins by correcting a second census figure of mine.** Reported
badly, it would have sent C1 to rebuild something that is already right.

---

## Correcting UX-A1 again: the status vocabulary is good

UX-A1 reported:

> *"22 distinct pill classes … `pill p` 497 uses … one generic class does 497
> jobs while the semantic ones are barely used — so status colour is effectively
> decorative rather than meaningful."*

**That is the opposite of the truth.** The regex `pill[- ][a-z]+` collapsed
`pill p-ok`, `pill p-warn`, `pill p-mut`, `pill p-bad` and `pill p-info` into a
single phantom class called "pill p". Counted properly:

| Class | Uses | Meaning |
|---|---:|---|
| `pill p-ok` | 148 | healthy / done |
| `pill p-warn` | 103 | attention |
| `pill p-mut` | 96 | inactive / draft |
| `pill p-bad` | 91 | blocked / overdue |
| `pill p-info` | 54 | in progress |
| **semantic total** | **492** | |
| legacy tail (`pill ok`, `pill warn`, `pill bad`, `pill insp`, `pill pro`, …) | ~42 | |

**492 of ~534 uses — 92 % — already use a five-tone semantic vocabulary that
maps almost exactly onto Part 8's green / amber / grey / red / blue.** There is
nothing to design here. The finding is a **legacy tail of about 42 uses** across
nine ad-hoc class names, which is tidying, not a system.

I am recording this as prominently as the original error, because "status colour
is decorative" would have justified a product-wide restyle that is not needed.

---

## F-A6-1 · Status has colour and label, but no icon
**Class: cosmetic · Severity: MEDIUM · Confidence: measured**

Part 8 is explicit: *"Do not rely only on colour. Always use ICON + LABEL +
COLOUR."*

| | |
|---|---|
| `class="pill"` uses in views | **752** |
| …that carry an icon character | **4** |

Every status in the product is **colour + label**. The label does carry the
meaning, so this is not a colour-blindness failure in the severe sense — a user
who cannot distinguish the tints still reads "OVERDUE". But it is not what the
brief asks for, and in the blueprint's own test condition (a phone in sunlight
at a site) a glyph is read faster than a tint.

**Recommended treatment (C1/C2):** add the icon to the **five semantic classes
only**, via CSS `::before`, so 492 uses gain it without touching a single view.
The legacy tail then gets mapped onto those classes in the same change.

**Risk:** low. Pure CSS, no markup.

---

## F-A6-2 · The status vocabulary is implemented six times
**Class: structural · Severity: LOW**

Six separate helpers each map a domain status to a pill:

```
credential_status_pill()   endorse_status_pill()      idems_status_pill()
inspector_eligibility_pill()  inspector_impartiality_pill()  template_status_pill()
```

Each is reasonable in isolation, and each owns genuinely different domain logic.
But the **tone mapping** — which state counts as ok / warn / bad — is repeated in
all six, so a change to the vocabulary has to be made six times.

**Recommended treatment (C2):** one `StatusBadge` helper that takes a tone and a
label; the six keep their domain logic and delegate the rendering. Part 28's
"do not create duplicate components if equivalent components already exist"
applies in reverse here — the duplicates already exist and should converge.

---

## F-A6-3 · One primary action is violated in both directions
**Class: structural · Severity: HIGH · Confidence: browser-measured**

Measured on real record pages at 1280×900:

| Screen | h1 | Breadcrumb | Status | **Primary** | Secondary | Danger | Tabs |
|---|:--:|:--:|:--:|---:|---:|---:|---:|
| Test request | Y | **–** | Y | **6** | 7 | 1 | 8 |
| Job | Y | Y | Y | 2 | 4 | 0 | 5 |
| Candidate list | Y | Y | – | 1 | 11 | 0 | 0 |
| Engineer | Y | Y | – | 1 | 2 | 0 | 1 |
| Instrument | Y | Y | – | 1 | 1 | 0 | 0 |
| Requirement | Y | Y | Y | **0** | 3 | 0 | 0 |

**Too many — the Test request page carries six equally prominent primaries:**

> Override & proceed · Set · Save · Raise · Record delay · Log

That is precisely Part 6's *"Do NOT display 8 equally prominent buttons"*, and
"Override & proceed" sitting at the same weight as "Save" is the kind of thing
that gets clicked by accident. It is also the **only record page measured with no
breadcrumb**, on the deepest screen in Operations.

**Too few — the Requirement page has no primary action at all.** Its buttons are
Edit · No longer needed · Add source · Link position, all secondary. A user
arriving at an open requirement is told nothing about what to do with it, which
is the same gap F-A5-3 found from the other side.

**Caveat, stated rather than buried:** these are single-record measurements on a
sparse test workspace. The Requirement "0 primary" is consistent with the code,
but a requirement in a different lifecycle state may well render an action that
this one does not. **C5 must re-measure across states, not trust this row.**

**Recommended treatment (C5):** one primary per record, chosen by lifecycle
state; everything else demoted to secondary or into a "More" menu. Nothing is
removed — Part 36's rule that hide is not delete applies.

---

## What is already right

- **The record header pattern exists and is widely adopted.** `.master-head`
  appears in **280 of 401 views**, and **178 views** already pair an `h1` with a
  status pill. Part 7's header (name, number, status, action) is mostly in place
  — it needs completing, not inventing.
- **Breadcrumbs on 5 of the 6 record pages measured**, consistent with the 274
  of 401 found in the census. Part 20 is substantially met.
- **The button tier system already exists**: `btn` (448), `btn secondary` (436),
  `btn ghost` (42), `btn danger` (18). Primary and secondary are used at almost
  exactly 1:1 — so the product is **not** styling everything as primary. Part 25
  needs enforcement on specific screens, not a new system.
- **The semantic status vocabulary** — see the correction above.

---

## Summary

| ID | Finding | Class | Severity |
|---|---|---|---|
| F-A6-1 | 752 pills, 4 icons — Part 8's icon requirement unmet | cosmetic | MEDIUM |
| F-A6-2 | Tone mapping duplicated across six pill helpers | structural | LOW |
| F-A6-3 | Test request: 6 primaries, no breadcrumb. Requirement: 0 primaries | structural | HIGH |

**Retired as false alarms:** the status vocabulary (92 % already semantic), the
button tier system (exists, used 1:1), the record header pattern (280 views),
breadcrumbs (substantially met).

**No product decision required.** F-A6-3 is the one substantial item, and it is
a demotion exercise — no action is removed, only re-weighted.
