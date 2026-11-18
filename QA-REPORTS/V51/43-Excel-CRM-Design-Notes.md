# Excel CRM: design notes for the Sales rebuild (step C of plan 42)

**Owner, 18 Nov 2026:** *"All the dashboard, colour coding, KPI cards etc. shall be as per the Excel sheet. Design shall be as per our application."*

**What that means for the build:**

- **Taken from the Excel:** what is shown, the KPI cards and their order, and the colour meaning.
- **Taken from EXAACT:** fonts, spacing, the left menu, light/dark, phone layout and accessibility.
- **Never copied:** company names, people or customers from the file. The file is only a pattern, so it holds no live data for us.

Read from the owner's file *Sales_CRM-2.xlsx* (rendered and checked on 18 Nov 2026).

## 1. Colour meaning (the Excel's legend, used everywhere)

| Colour | Means |
| --- | --- |
| 🔴 Red | Overdue / problem |
| 🟠 Amber | Due today / warning |
| 🟡 Yellow | This week · a required field still empty |
| 🟢 Green | On track / healthy / won |
| Grey | Worked out automatically (not typed) |

## 2. Home (the Excel's HOME sheet)

- **Header:** the company name plus *Sales*, and a welcome line with today's date.
- **One-click shortcuts:** New lead · Log activity · New deal · New quote · New order · Print quote.
- **⚡ Right now: 6 KPI cards.**

| Card | Colour |
| --- | --- |
| Overdue follow-ups | red |
| Due today | orange |
| Open pipeline ₹ | blue |
| Won this FY ₹ | green |
| Overdue payments ₹ | amber/brown |
| Renewals due in 30 days | pink |

- **🧭 Your daily routine, 4 steps (15 minutes):** Open Today → Take action (WhatsApp/call) → Log it (2 min) → Update the deal.
- **🗂 Where to go:** coloured tiles, each with what it is for and who uses it, how often.
- **🩺 Data health, each with "Fix ›":** deals with a health warning · open deals/leads with no next step · possible duplicate leads · GSTIN/duplicate account issues · quotes needing approval or expired · lost deals without a reason.
- **👥 Who does what:** Sales executive · Sales manager · Accounts · Admin, one line each.

## 3. Today (the Excel's TODAY sheet)

- **5 + 2 cards:** 🔴 Overdue (deals + leads) · 🟠 Due today · 🟡 This week (next 7 days) · ❗ No next step ("set a date!") · 💤 Stale deals (no activity > 15 days) · 💰 Overdue ₹ · 🔁 Renewals.
- **👥 Who has what today:** one row per salesperson, with the same columns as the cards.
- **Lists, worked top to bottom:**
  - deal follow-ups
  - lead follow-ups
  - stale deals
  - payments to collect (overdue and due in 7 days)
  - renewals due
  - birthdays & anniversaries (next 7 days)
- **On each row:** a status pill, ₹, days idle, and **WhatsApp / Call** in one tap.

## 4. Pipeline board

- **Columns:** Open leads, then each stage, coloured cool → warm → green as the deal advances.
- **Each column head shows:** count · ₹ (lakh) · win %.
- **Each card shows:** company · ₹ · owner · days idle, with 🔴/🟠/❗ markers.
- **Filters:** pipeline and salesperson.
- **Side panel:** confirmed orders: POs received (FY to date) · PO value · unbilled order value.

## 5. Dashboard (managers)

- **Filters:** salesperson · period · pipeline.
- **Key numbers, 8 cards:** New leads · Won value · Win rate · Avg deal size · Open pipeline · Weighted forecast (pipeline × win %) · Target · Achievement (won ÷ target). Each card also shows a one-line "how" under the figure.
- **Each table is paired with a chart:**
  - pipeline by stage (deals, ₹, weighted ₹)
  - monthly won vs target (financial year)
  - lead source performance (leads, deals, won ₹, conversion)
  - why we lose (lost reasons)
  - collections ageing (outstanding by age)
  - team leaderboard (pipeline, won, activities, target, achievement)
  - top 10 open deals

## 6. Forecast

**Order in hand + pipeline, by month,** with the order book behind every number.

## 7. Every screen

- **A title,** then **"Why you're here"** and **"What to do" (1-2-3)** in one or two lines.
- **Dropdowns** for every list.
- **Required fields** are highlighted until filled.
