# Your Excel CRM vs EXAACT Sales: honest comparison (18 Nov 2026)

**What I read:** your file *Sales_CRM-2.xlsx*. It has 20 working sheets, 6 training sheets and 1 hidden calculation sheet. Its flow is **Lead → Deal → Activity → Quote (items + print) → Order / Invoice / Payment → Renewal**, plus Today, Pipeline, Forecast, Dashboard, Targets, Team, Products and Settings. It holds only a few test rows today, so this compares the *design*, not real use.

## The short answer

**For a salesperson's day, your Excel is simpler and better designed than our Sales module.**

- **Your Excel is built around the salesperson's day.** It starts from one Today list, needs only 3 fields to start a lead, works out the next follow-up for you, and explains every sheet with "Why you're here / What to do".
- **Our Sales module is built around control.** It has approvals, project costing, contracts, stage gates, office scope and history. These are strong for the company, but heavy for the person selling.

## Side by side

| Point | Your Excel CRM | EXAACT Sales today | Better |
| --- | --- | --- | --- |
| Where the day starts | **Today** sheet: overdue, due today, this week, no next step, stale deals, overdue ₹, renewals, birthdays | No single "today" list for a salesperson | **Excel** |
| Start a lead | 3 fields (company, mobile, source); the owner is auto-assigned by region | More fields and screens; no auto-assign rule | **Excel** |
| Follow-ups | Log an activity → the next date is set from the outcome, skipping holidays | Next action is typed by hand | **Excel** |
| One-tap WhatsApp / call / e-mail | Yes, on every list | Not in Sales | **Excel** |
| Targets per salesperson per month | Yes | No | **Excel** |
| Forecast (order in hand + pipeline) | Yes | Partly (pipelines) | **Excel** |
| Renewals, birthdays | Yes | Contract renewal only; no birthdays | **Excel** |
| GST type from the customer's state | Automatic (CGST+SGST / IGST / export) | In invoicing / Tally, not on the quote | **Excel** |
| Help on every screen | "Why you're here / What to do" + 6 training sheets | Partial | **Excel** |
| Who can see and change what | Anyone with the file can change anything | Logins, roles, office scope | **EXAACT** |
| History (who changed a price, when) | None | Full history | **EXAACT** |
| Quote approvals, costing, margins | None | Yes, multi-level | **EXAACT** (if you need them) |
| Many people at once | OneDrive can clash; rows are pre-built (800 leads, 600 accounts) | No limit, no clash | **EXAACT** |
| Quote → contract → jobs → invoice | Stops at payments; nothing flows to the inspection work | One chain, end to end | **EXAACT** |
| Changing the lists (sources, stages, products) | One SETTINGS sheet, easy | 27 Sales settings across several screens | **Excel** |

## The Excel is not perfect either (brutal both ways)

- **Very wide sheets:** DEALS has 81 columns, QUOTES 84 and ORDERS 87, most of them hidden helper columns.
- **Quotes is growing a second copy of itself.** A second "register" block already repeats Quotation Status, SBU, Prepared By and Remarks. That is the same drift that made EXAACT complicated.
- **No protection:** no permissions, no history, and formulas that one wrong paste can break.

## My recommendation

**Use your Excel as the blueprint for EXAACT Sales.**

1. **Rebuild Sales on your Excel's pattern:**
   - **Today** list
   - **Leads** (3 fields)
   - **Deals** with one page each
   - **Activities** with automatic next follow-up
   - **Quotes**, with GST from the customer's state, items and print
   - **Orders and payments**
   - **Renewals**
   - **Targets**
   - **Dashboard**
   - **One Settings page** for the lists
2. **Keep from EXAACT only what Excel cannot do:** logins and permissions, history, quote approval (only if you want it), and the link from quote to contract to jobs to invoice.
3. **Put away** (switched off under Advanced, not deleted): the project costing library, stage gates, advertising return, the pre-order checklist and pipeline/funnel setup. Any company that wants one switches it on.

This fits "Simplify first" (document 40, D1–D5). Sales becomes the second module done this way, after Expenses.

## "Can we delete the old code and keep only the new?"

**Yes, in two steps, never in one.**

| Step | What happens | Why |
| --- | --- | --- |
| 1 · Switch off | The old screen or module is hidden; its data stays | Old records (for example, old month vouchers) still open. The live site has real data |
| 2 · Delete | After 1–2 months with nobody needing it, its code and tests are deleted, in its own release with a backup | If something was still needed, that one release is undone |

- **Nothing is lost forever.** Git keeps every deleted line, so any piece can be brought back.
- **First I make a "dead code" list.** It covers code that nothing uses even today, and I prove that with the automatic tests. That list can be deleted straight away.
- **Code that real records depend on is the exception.** It is deleted only after those records are converted, or after you decide they are no longer needed.

## Your decisions

- **S1** Rebuild EXAACT Sales on your Excel's pattern (picture first, then build): Yes / No
- **S2** Delete old code in two steps (switch off, then delete after 1–2 months), starting with a "dead code" list: Yes / No

**One-line answer:** *"D1–D5 yes, S1 yes, S2 yes"*, or tell me what to change.
