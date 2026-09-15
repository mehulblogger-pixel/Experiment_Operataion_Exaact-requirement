# Phase 2 · M3 — Requirement ⇄ Requisition Map

*What the different "how many people do we need" objects are, and why none of
them was merged.*

## 1. The §44 scan

Before any schema change, the whole database was scanned for a competing
requisition system and for any other quantity field. **308 tables.**

| Table | Cols | What it actually is | Decision |
|---|---|---|---|
| `requisitions` | 79 | the internal hiring request | **the canonical Requisition** |
| `cx_requirements` | 34 | a Marketplace/Connect requirement posted to the network | untouched (§7) |
| `cx_positions` | 11 | a **line item** of a Marketplace requirement — role + quantity + rate | untouched |
| `requisition_groups` | 10 | a **site / reporting split** of one requisition's headcount | untouched |
| `dep_manpower` | 13 | a deployment planning board: required / planned / mobilized per client-project | untouched |
| `site_doc_requirements` | 9 | document requirements for a site — nothing to do with people | untouched |

**No competing requisition system exists.** `cx_requirements` is a different
object with a different audience, and M3 did not merge it.

## 2. Other quantity-like columns — all different questions

| Column | Question it answers |
|---|---|
| `requisitions.quantity` | how many people this hiring request needs |
| `cx_requirements.positions` | how many the Marketplace posting needs |
| `cx_positions.quantity` | how many of one *role line* within that posting |
| `requisition_groups.headcount` | how many at one *site*, reporting to one person |
| `dep_manpower.required` | how many a client project needs, planned vs mobilized |

These are not duplicates of each other. **No second requisition quantity field
was created**, and `requisition_groups` continues to drive
`requisitions.quantity` exactly as it did.

## 3. The relationship, conceptually (§7, §8)

```
Hiring need
     ↓
Requirement          — may come from inside (a manager) or from the
     ↓                 Marketplace (a client posting a need)
Requisition          — the internal record that gets it filled
     ↓
Fulfilments          — one candidate row per seat
     ↓
Candidate / Offer / Joining
```

`requisitions` and `cx_requirements` are **both** requirements, seen from
different sides: one is "we need to hire", the other is "a client needs people
and the network may supply them". They are related, not the same, and M3 keeps
them separate.

## 4. Ready for multi-source, without implementing it (§8, §21)

The structure does not prevent one requirement being met from several sources,
because a fulfilment **is** a candidate row and each candidate already carries
its own `source`, `agency`, `roll_type` and cost. So:

```
Requirement = 20
   internal    5      →  5 candidate rows, source ASSET
   Agency A    5      →  5 candidate rows, source HR_AGENCY, agency A
   Agency B    7      →  7 candidate rows, agency B
   Marketplace 3      →  3 candidate rows
```

already counts correctly today. What is **not** built — deliberately (§45) — is
allocation: reserving a share of a requirement to a named supplier and tracking
it against them. That needs a supplier-allocation object and is Phase 4.

## 5. Candidate relationship (§22)

`candidates.requisition_id` is the link, and it is sufficient for multi-vacancy:
many candidates point at one requisition, each with its own stage and its own
workforce record. That is what makes "3 of 10 filled" computable without any new
table.

What it does **not** express is a seat with nobody in it — which is why a
cancelled vacancy is a count on the requisition, not a phantom candidate. Person
identity is untouched (§45).

## 6. Offer / appointment / joining (§23)

Audited, connected, not rebuilt:

```
candidate  →  job_offers          (an offer, via candidate_id)
candidate  →  inspectors          (joining, via candidates.inspector_id)
requisition → hired_inspector_id  (preserved; names the most recent hire)
```

One requisition already reaches many offers and many joinings, because each goes
through its own candidate row. The Offer and Onboarding engines were not
modified.
