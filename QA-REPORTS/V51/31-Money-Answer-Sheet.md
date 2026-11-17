# Answer sheet 2 — "Money" (17 Nov 2026)

These are the money items found in the audit. They come from five packs (Money, Directory & Insights, Marketplace, Client portal, Operations), collected here so you can answer **in one reply**.

**Nothing here has been changed.** Each problem was found and shown during the audit. No later batch touched these parts of the program, and no fix for them was ever made. I re-checked M1 against today's code (release 74a263f) and it is still there.

**The edge-case helpers are re-testing the rest on today's code right now.** If any item turns out to be already fixed, I will tell you and drop it.

**Two kinds of item:**
- **Fixes (sections 1–4):** the figure shown is wrong, and the proposal makes it right. I recommend yes for each.
- **Your rules (section 5):** the app needs a rule only you (or your accountant) can give. **I will not guess these.**

**The easiest answer:** *"Money sheet: 1–4 as proposed. 5: …"*, plus your answers to section 5. If you disagree with one, name it, for example *"all as proposed except M7"*.

After your answer I will:
1. explain each change with an example before touching the code, as you asked;
2. build it, test it and send an opstest zip.

## 1. Wrong figures on screens and printouts

| # | Problem today, with an example | My proposal |
| --- | --- | --- |
| M1 | **Every printed or PDF tax invoice shows the wrong "Settled so far".** A ₹11,800 invoice that is fully paid prints *"Settled so far ₹1.00 · Outstanding ₹11,799.00"*. The amounts stored are right; only the printout is wrong (Money A1) | Print the real settled and outstanding amounts. When nothing is owed, print no "Outstanding" line |
| M2 | **Profit counts GST as income.** A job billed ₹16,000 + 18% GST shows revenue **₹18,880** instead of ₹16,000 in Profit by work order, Business Unit P&L, the Contract register and the Management dashboard. Profit and margin look ₹2,880 better than they are (Money A2) | Count revenue **before GST** everywhere. GST is owed to the government, not earned. Jobs already invoiced are recalculated automatically; nothing is stored differently |
| M3 | **The same expense is counted twice.** A ₹2,500 hotel bill entered on the job closure **and** on the inspector's voucher shows as ₹5,000 of cost (Money D1 = Operations A11) | Count it **once** when the job, the date and the amount match. Doubtful cases (same job and date, different amounts) stay on the "duplicates" worklist for a person to decide |
| M4 | **"Direct cost" does not explain itself.** The Management dashboard shows ₹18,780 but explains it as ₹16,000 + ₹0 + ₹1,500 = ₹17,500. The missing ₹1,280 is the office overhead (Insights A6) | Add the overhead line to the explanation. The total does not change |
| M5 | **A branch's own income is shown as "Received from another office".** It is also counted in "Credit received vs actual", and the invoice tracker's "credit not received" count has the same problem (Insights A2) | Count it as "from another office" only when another office actually did the work |
| M6 | **The dashboard "Loaded cost" ignores branch and filters.** Both branch managers see the whole company's ₹1,62,000, including other offices' salaries. An empty future year shows **−₹1,62,000** (Insights A3) | Apply the person's offices and the chosen filters, the same as every other card |
| M7 | **The job's Money tab hides "other cost".** The Operation Manager sees "profit: Master Admin only". The job's "total credit" box is required but never filled in (Operations A13) | Show other cost. Show profit to whoever your permission list allows. Work out the total credit automatically |

## 2. Who sees which money (following your permission list)

| # | Problem today | My proposal |
| --- | --- | --- |
| M8 | Coordinator, Business Director, Business Unit Head and Branch Manager **may raise** invoices (your list) but have **no button** to start a draft. Only Finance and admins do (Money A3) | Show "Draft invoice" to everyone your list allows. **Issue stays with Finance** |
| M9 | Work sold by Mumbai and done by Ahmedabad: **Ahmedabad sees the full client amount; Mumbai does not see the job's money at all** (Money A4) | Both offices see the job's money, each with its own share, as set in the inter-office split |
| M10 | A Branch Manager sees **all offices'** Contract register, Business Unit P&L and month-end runs (Money A5) | Only the offices the person covers |
| M11 | The Operation Manager is **refused** Office costs & overheads, although your list gives them Edit (Money A6) | Allow it as the list says. *(Or tell me the list is wrong, and I correct the list instead)* |

## 3. Safety on settings and payments

| # | Problem today | My proposal |
| --- | --- | --- |
| M12 | Pressing **Save on the empty Marketplace requirement settings** sets commission to **0%** and the open period to **1 day** (Marketplace A6) | Refuse an empty save and keep the old values |
| M13 | The **same coupon code can be created twice**, so it is unclear which discount applies (Marketplace A7) | Refuse a duplicate code |
| M14 | The online-payment confirmation accepts **any** order number. A client who sends a made-up confirmation with another client's order number can turn that client's pending payment to "Failed". The risk is small because order numbers are long random strings (Client portal A3) | Accept a confirmation only for the signed-in client's **own** order |
| M15 | **A deleted work order can still be billed.** Its report and billable event stay behind (Operations A12) | **Refuse to delete** a work order that has a report or a billable event; cancel it instead. That way nothing billable disappears silently. *(The other choice: delete them with it. I do not recommend that, because it removes history)* |

## 4. Marketplace money

| # | Question | My recommendation |
| --- | --- | --- |
| M16 | The commission rate changes from 10% to 12% while a voucher is still a draft. Which rate does the draft carry? (Marketplace C5) | **Keep the rate from the day the voucher was raised (10%)**. The screen shows "rate 10%, fixed on 12 Nov 2026" |
| M17 | No screen lists requirements that were **awarded but never sent to billing** (Marketplace C6) | Add an **"Awarded — not billed yet"** list on the Marketplace board and on Money → Billable events |
| M18 | One unusual rate (₹95,000 when the rest are ₹8,000–₹12,000) distorts the rate guide, and cancelled requirements are counted (Marketplace C7) | Use the **middle value (median)**, and leave out Cancelled and Draft |

## 5. Your rules — please write them in your own words

**I will not set these myself.** Short answers are fine. "Same as today" is also an answer.

**R1. Invoice numbers when the format changes mid-year (Money D2).**
- **Today:** after `AHM/2627/0002`, a format change made the next invoice `AHM-2026-27-0001`, so the count restarted within the same financial year.
- **GST rules expect one unbroken series per financial year.**
- Which do you want?
  - (a) The count continues whatever the format (`…-0003`).
  - (b) The format cannot be changed in the middle of a financial year.
- **Please check with your accountant.**

**R2. Must every inspector expense name a job? (Money D3)**
- **Today:** voucher lines may have no job, so those costs reach no job's profit.
- Choose:
  - (a) every expense line must name a job;
  - (b) a line may be "general / office", and it then counts as office overhead.

**R3. Man-day billing (Money C14).** For work billed per man-day:
- **R3.1** What is the billing point: the **stage completion**, or **each report**?
- **R3.2** If the inspector works **more man-days than the order allows** (e.g. 6 on an order for 5): bill the extra day automatically, bill it only after approval (by whom?), or never bill it without a new order?
- **R3.3** If the client **ends the work early or gives notice:** is there a charge? If yes, how much?
- **R3.4** **Part completion** (e.g. 3 of 5 days done when the work stops): bill the 3 days only, or something else?

**R4. Man-month billing (Money C14).** For work billed per man-month:
- **R4.1** When a **replacement inspector** takes over mid-month, is it the same job (one bill for the month) or a new job (two bills)?
- **R4.2** **Extensions:** does an extension continue the same order and rate?
- **R4.3** **Extra days:** can extra days in a month be billed? Is there a cap, for example at most one month's value?
- **R4.4** **Billing cycle:** always the calendar month (1st–30th/31st), or from the start date (e.g. 15 Nov–14 Dec)?

**When you answer section 5,** I will show you, before building anything, the exact screens and a worked example with numbers for each of your rules.

---

**One-line answer:** *"Money sheet: M1–M18 as proposed. R1 (a)/(b) … R2 … R3 … R4 …"*
