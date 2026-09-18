# Phase 4 — Terminology

*The words this phase uses, fixed once, so that two people reading the same
screen mean the same thing. Where a word already had a meaning in this product,
that meaning is kept — Phase 4 invents no synonyms.*

---

## The five quantities

These are the whole phase. Confusing any two of them is the failure this work
exists to prevent, so they are named once and used nowhere loosely.

| Term | Plain meaning | Where it comes from | Who owns it |
|---|---|---|---|
| **AUTHORISED** | How many people this requirement is approved to take on | M3's `reqf_counts()` — approved quantity less cancelled vacancies | **M4 / M3.** Phase 4 never writes it |
| **ALLOCATED** | How many of those the business has **promised to sources** | Sum of every allocation's `allocated_qty` | **Phase 4** |
| **SOURCED-FULFILLED** | How many have actually **arrived through a source** | People in a filled stage carrying an allocation link | **Phase 4** (counted, never stored) |
| **DIRECT-FULFILLED** | How many arrived **without** a named source | People in a filled stage carrying no link | **Phase 4** (counted, never stored) |
| **FULFILLED** | How many have joined **in total** | M3's own filled count | **M3** |

Two derived figures the screens show:

- **UNALLOCATED** = AUTHORISED − COMMITTED — how many seats are still to be sourced.
- **REMAINING** = AUTHORISED − FULFILLED — how many people are still to be found.

And one that names the real risk:

- **COMMITTED** = ALLOCATED + DIRECT-FULFILLED — everything the requirement has
  already spent. A person found directly fills an approved position just as
  surely as an agency's placement does, so **their seat is gone and must not be
  promised to anybody**. A requirement whose COMMITTED exceeds its AUTHORISED is
  **over-committed** — see below.

**The invariant, in words:** a source can never be credited with more people than
it was promised; the promises together can never exceed what was approved; and a
seat somebody has already filled cannot be promised to somebody else.

```
SOURCED-FULFILLED  ≤  ALLOCATED  ≤  AUTHORISED
COMMITTED (= ALLOCATED + DIRECT-FULFILLED)  ≤  AUTHORISED
```

---

## The objects

**Fulfilment source** — *where* people come from: our own payroll, direct
recruitment, an internal transfer, a manpower supply agency, a third-party
(sub-contract) agency, a supplier, a freelancer, a consultant, the marketplace,
a client bench. The list is the **existing configurable `req_sourcing_model`
lookup** already registered in Masters; a workspace may add its own without a
line of code. Phase 4 built **no new master**.

**Source entity** — *which one*: a named supplier or client from
`business_partners`, a marketplace requirement from `cx_requirements`, a
professional from `cx_professionals`, a person from `inspectors`. Always an
**existing record in this workspace**; never free text pretending to be an
identity. The free-text label beside it is a **display attribute, never a
security identity**.

**Allocation** (`requisition_allocations`) — the promise that **one source** will
supply **part of one requirement's** approved headcount. It is the only new table
in this phase.

**Allocation link** (`candidates.allocation_id`) — which source a person arrived
through. One additive nullable column. It decides **credit**, never employment.

---

## Words that are deliberately *not* used

**"Sub-requisition", "child requirement", "split requisition".** There is no such
thing here and there must not be. Twenty people from five sources is **one**
requirement with five allocations. The moment sourcing creates a second
requirement, the approved headcount has been duplicated and the business is
committed to forty people — the exact failure this phase prevents.

**"Vendor requisition."** A supplier does not hold a requisition. They hold an
allocation against ours.

**"Assign"** is reserved for M5's meaning — *who is accountable* for chasing the
work (`recruiter_id`, `manager_id`). Phase 4 uses **allocate** (promise seats to
a source) and **credit** (record that a person arrived through one). They are
different questions and must not share a verb.

**"Fill."** M3 owns what "filled" means (a candidate in `REQF_FILLED_STAGES`).
Phase 4 reuses that definition and does not offer a second one.

---

## Verbs, and exactly what each one does

| Verb | What happens | What it never does |
|---|---|---|
| **Allocate** | Promise a source *n* of the approved seats | Change the approved quantity; create a requisition |
| **Reallocate** | Change that promise up or down | Cut below what the source already delivered |
| **Credit / attach** | Record that this person arrived through this source | Decide whether the person is hired |
| **Release** | Give the source's **undelivered** seats back | Take away the people it did deliver |
| **Cancel** | Say the source will not deliver at all | Remove anybody already placed by it |

---

## A sentence a coordinator would actually say

> *"Twenty welders approved. Ten are coming off our own payroll, five through
> Sterling, five freelance. Sterling has sent two so far, so they still owe three.
> Two people walked in directly, so I've only got three seats left to place."*

Every noun in that sentence is a term above, and the screen shows exactly those
numbers. If a screen ever needs a paragraph of explanation, the screen is wrong —
not the reader.
