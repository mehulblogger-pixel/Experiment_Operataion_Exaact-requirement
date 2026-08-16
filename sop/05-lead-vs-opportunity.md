# Lead vs Opportunity — terms, the two pipelines, and the full flow

> Plain-English guide to the front of the sales chain. Read it once and you'll
> know **what a Lead is, what an Opportunity is, why there are two pipelines, and
> exactly how a deal travels from first contact to a live order.** No technical
> knowledge needed.

---

## The one-sentence version

A **Lead** is *a name you haven't qualified yet.* An **Opportunity** is *a real
piece of business you're actively trying to win.* A Lead becomes an Opportunity
the moment it's worth working; an Opportunity becomes an **Order** the moment the
customer says yes.

```
Lead  ─(qualify)─►  Opportunity  ─(win)─►  Order (inspection call)  ─►  Job  ─►  Report  ─►  Invoice  ─►  Money in
```

That top strip is the same one you see across the top of every deal, lead, quote,
call and job screen — the **lifecycle strip**. A stage in colour has something in
it; a greyed, dashed stage simply hasn't happened yet (it is *not* broken).

---

## The two words, side by side

| | **Lead** | **Opportunity** |
|---|---|---|
| **What it is** | An unqualified enquiry or name — a card at an expo, a website form, a cold call. | A qualified deal you're pursuing, with a customer, a rough value and a next step. |
| **Do you know the customer?** | Maybe not — it can be just a company name. | Yes (or it's clearly for a named company). |
| **Has money attached?** | No — you don't know yet. | Yes — a *working* estimated value (it moves as you learn more). |
| **Where it lives** | **Leads** register (`/leads`). | **Opportunities** board (`/opportunities`). |
| **When it ends** | It's **converted** into an Opportunity (or dropped). | It's **Won** (→ raise the order) or **Lost** (with a reason). |

**Why keep them separate?** So your Opportunity numbers mean something. If every
stray name sat in the Opportunity pipeline, your "pipeline value" and win-rate
would be fiction. Leads are the top of the funnel you *sift*; Opportunities are
the deals you *forecast*.

---

## Why there are two pipelines

A **pipeline** is just the set of stages a deal moves through. There are two
because a Lead and an Opportunity are answering different questions:

- **The Lead pipeline** asks *"is this worth pursuing?"* — e.g. New → Contacted →
  Qualified. It ends in a decision: convert or drop.
- **The Opportunity pipeline** asks *"how close are we to winning?"* — the default
  stages are **Qualified (10%) → Needs understood (25%) → Quotation sent (50%) →
  Negotiation (75%) → Won (100%) / Lost (0%)**. Each stage carries a probability,
  so the weighted forecast is automatic.

Both pipelines are configurable — you can rename or add stages — but each stage
has a fixed **kind**: `OPEN`, `WON` or `LOST`. That kind is what actually decides
what happens (a stage marked `WON` closes the deal as a sale, whatever you call
it), which is why the wording is yours to change and the behaviour is not.

---

## The full flow, step by step

### 1. A Lead comes in
Open **Leads** (`/leads`) → **New lead**. Capture whatever you have — a company
name is enough. Work it through the Lead stages as you make contact.

### 2. Qualify it → Opportunity
When the Lead is real, open it and **convert it to an Opportunity**. The Lead
*stays* as the record of where the business came from; the new Opportunity is what
you now work. (On the Opportunity you'll see it was raised from that Lead.)

### 3. Work the Opportunity
On the **Opportunity** (`/opportunity?id=…`) you move it along the stages, keep the
estimated value honest, attach quotations, and set the next action. Send a
quotation and the **Quotation** stage on the strip lights up.

### 4. Win or lose it
Move the deal to a **Won** or **Lost** stage:
- **Won** — the customer said yes. The deal closes as a sale. *Next: raise the
  order.*
- **Lost** — it didn't come off. A loss **needs a reason** (that's the only thing
  that makes it useful later). A lost deal is **final** — you don't reopen it. If
  the customer comes back, **duplicate it as a new opportunity** so the history of
  the first attempt stays clean.

### 5. Raise the order (Won → operations)
On a Won Opportunity, use **Raise the order**. This creates the **inspection call**
(the operational work order) and carries the customer, the accepted quotation and
the value across. This is the hand-off from sales to operations — from here the
[Order → Job → Report → Invoice](#) spine takes over (each of those screens has its
own guided playbook).

> **Where "order" points:** on the lifecycle strip the **Order** stage *is* the
> inspection call — the first operational step. "Raise the order so operations can
> start" and "create the inspection call" are the same action.

---

## Quick reference

| I want to… | Go to |
|---|---|
| See all unqualified names | **Leads** — `/leads` |
| See deals I'm forecasting | **Opportunities** — `/opportunities` |
| Turn a Lead into a deal | Open the lead → **Convert to opportunity** |
| Record a win | Opportunity → move to the **Won** stage |
| Record a loss (with reason) | Opportunity → move to the **Lost** stage |
| Re-pursue a lost deal | Lost opportunity → **Duplicate as new opportunity** |
| Hand a win to operations | Won opportunity → **Raise the order** |
| Change the stages / probabilities | Pipeline settings (per pipeline) |

---

## The mental model to keep

> **Lead = a question ("is this worth it?"). Opportunity = a forecast ("how
> likely, how much, by when?"). Order = a commitment ("they said yes — go").**

If you're ever unsure which one you're looking at, ask *"has this been
qualified?"* — before, it's a Lead; after, it's an Opportunity.
