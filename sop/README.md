# Masters & Dropdown Lists — SOP pack

Plain-English standard operating procedures for the **Masters** area of the app —
the reference data (offices, clients, vendors) and the **dropdown lists** that feed
every form.

Keep this folder open while you build a demo. It is written so a non-technical
person can follow it click-by-click.

## What's in here

| File | Who it's for | Use it when |
|------|--------------|-------------|
| [`01-masters-explained.md`](01-masters-explained.md) | Everyone | You want to understand the whole screen in 5 minutes. Read this first. |
| [`02-admin-demo-builder-guide.md`](02-admin-demo-builder-guide.md) | **Us** — the team setting up demos | You are preparing the app for a prospect or a new client. Step-by-step build order + checklist. |
| [`03-end-user-guide.md`](03-end-user-guide.md) | **The client's own admin** | Hand this to the customer. Day-to-day "how do I add a choice / a new list / a new field". |
| [`04-quick-reference.md`](04-quick-reference.md) | Everyone | The one-page cheat sheet. Print it. |
| [`05-lead-vs-opportunity.md`](05-lead-vs-opportunity.md) | Sales & anyone learning the flow | You want to understand **Lead vs Opportunity**, the two pipelines, and the full path Lead → Opportunity → Won → Order. |
| [`06-writing-a-report.md`](06-writing-a-report.md) | **The inspector** (field staff) | Hand this to the person who writes reports. Dead-simple, step-by-step: open → auto-fill from QAP → speak the findings → photos → submit → issue. Written for someone with no computer knowledge. |
| [`12-seat-pricing.md`](12-seat-pricing.md) | **Us / the Master Admin** | You need to set or explain seat prices — the ₹1,799 / ₹499 / ₹99 (10 free) defaults and exactly where to change them. |
| [`10-licensing-for-us.md`](10-licensing-for-us.md) | **Us** — the vendor | How to issue licence keys (incl. for a role mix), how money/billing works, and the pricing gaps. |
| [`11-licensing-for-customer.md`](11-licensing-for-customer.md) | **The customer** | Installing on their own server, entering the key, seats, renewals. Hand this over. |

## The one thing to remember

There are **three layers** under Masters. They look similar but do different jobs:

1. **Records you keep** — real things and people (offices, staff, clients, vendors).
2. **Dropdown lists** — the choices behind every dropdown (Business Unit, Region, Activity, statuses).
3. **Extra fields on a form** — your own boxes added to a form.

Everything in these SOPs hangs off those three layers. If you are ever lost,
ask: *"Which layer is this?"*

## Where it lives in the app

- **Masters home:** `Masters` in the menu (`/masters`) — the map of all three layers.
- **All dropdown lists:** `/lookups` — add a list, add a dependent list, tick which forms it shows on.
- **A single list's choices:** click any list, or `/lookup?key=…`.
- **Extra fields:** `/custom-fields?entity=call` (or `job`, `partner`, …).
- **Rename a word** (e.g. "Work Order" → "Call"): `Settings → Terminology`.
