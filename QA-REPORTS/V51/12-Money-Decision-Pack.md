# Money & Finance — decision pack (V51.1)

15 Nov 2026 · All 152 Money tests were run on a private copy of V51.1 with made-up data. Nothing has been changed yet.

**How to answer:** reply with the item numbers, for example *"A1 yes, A2 yes, A3 yes, A4 no, B ok, C1 agree, C3 accrual, D1 yes, D3 every expense names a job, E1 yes"*. Anything you don't mention stays untouched.

## Where Money stands

| | Tests |
| --- | --- |
| Passed | 104 |
| Failed — 10 real defects | 14 |
| Need your decision (section C) | 14 |
| Blocked — 10 test wordings out of date (labels changed on purpose) | 10 |
| Cannot be tested here (Tally import, MGH Books, real Razorpay / printer / inbox) | 8 |
| Not built (no Cancel on the billable-events board; no warning when two people edit one draft) | 2 |
| **Total** | **152** |

The billing scenarios (man-day / man-month) are only partly run: 5 of 27 are partly executed, and all 12 man-month scenarios are not started. They raise rule questions of their own (section C, last rows).

## A — Fixes that need your yes (money, billing, permission)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | DEF-FIN-003 (P1) | The **printed / PDF tax invoice** says "Settled so far ₹1.00 · Outstanding ₹(total − 1)" on every issued invoice — a fully paid ₹11,800 invoice prints ₹11,799 outstanding. A programming slip (a list of figures read as the number 1); the amounts stored are right | Print the real settled and outstanding figures; print nothing when nothing is owed |
| A2 | DEF-FIN-009 (P1) | Once a job is invoiced, **revenue includes GST** in Profit by work order, Business Unit P&L, the Contract register and the Management dashboard (₹16,000 shows as ₹18,880). Profit and margin are overstated | Count revenue before GST; re-work the figures of jobs already invoiced |
| A3 | DEF-FIN-001 (P1) | Coordinator, Business Director, Business Unit Head and Branch Manager may **raise** invoices (permission list) but no button lets them start a draft; only Finance / admin see it | Show "Draft invoice" to everyone allowed to raise; "Issue" stays Finance-only |
| A4 | DEF-FIN-006 (P2) | Work contracted by one office and done by another: the executing office sees the full client amount, the contracting office does not see the job | Show it to both offices, each with its own share |
| A5 | DEF-FIN-007 (P2) | A Branch Manager sees **all offices'** Contract register, Business Unit P&L and month-end runs | Limit them to the offices the person covers |
| A6 | DEF-FIN-005 (P2) | The Operation Manager is refused Office costs & overheads, although the permission list gives them edit | Allow it as the list says — or tell me the list is wrong |

## B — Fixes I will make (small, no rule changes)

| # | Defect | Problem |
| --- | --- | --- |
| B1 | DEF-FIN-002 | A draft invoice in the Invoices list cannot be opened (empty link) |
| B2 | DEF-FIN-008 | Money received but not yet matched to an invoice is not shown on the home screen (the gap register says it was) |
| B3 | DEF-FIN-010 | With nothing ticked, "Draft invoice for the ticked work" still makes an empty draft |
| B4 | DEF-FIN-004 | "Sync from closed work" reports old events as newly found |

Plus the wording of the 10 out-of-date tests.

## C — Questions only you (or your accountant) can answer — my proposal in **bold**

| # | Test | Question | Proposal |
| --- | --- | --- | --- |
| C1 | MON-032 | An invoice dated 31 Mar 2026 got a 2026-27 number, because the number follows the day the draft was started | **Number from the invoice date's financial year — ask your accountant** |
| C2 | MON-069 | Should a credit note reduce the job's revenue in profit reports? | **Yes** |
| C3 | MON-119 | Profit by work order counts the agreed value before any invoice (accrual). Keep that, or ₹0 until invoiced? | **Your call — accrual is today's behaviour** |
| C4 | MON-051 | Downloading the CSV preview "uses up" the Tally batch, so the XML is then refused until Undo | **A preview should not use it up** |
| C5 | MON-067 / MON-097 | Who may switch the revenue / cost "reader mode" (what every dashboard reads)? Not in the permission list | **Finance and admin only** |
| C6 | MON-122 | Finance is refused the Management dashboard that the Profit screen links to | **Give Finance the dashboard, or remove the link** |
| C7 | MON-094 | A voucher dated in a closed cost month can still be approved / reopened | **Read-only, with "That month is closed"** |
| C8 | MON-028 | The Rounding card shows ₹0 for a ₹0.22 round-off | **Show paise** |
| C9 | MON-012 | A fully paid job leaves the "open items" list, so its paid row never shows there | **Fine — update the test** |
| C10 | MON-008 | No part-bill box on screen (the server logic exists) | **Add the box — or drop the step** |
| C11 | MON-064 | Custom roles cannot be deleted; no "new custom role" button | **Allow deleting an unused custom role** |
| C12 | MON-091 | An empty month shows no "Calculate and store" button | **Fine — update the test** |
| C13 | MON-100 | A sub-contractor cost listed before the month-end run makes reconciliation amber every month | **Ignore months not yet run** |
| C14 | Billing MD/MM | Man-day: a stage completion or an ordinary report as the billing point; extra man-days without approval; termination / notice charges; part completion. Man-month: a replacement inspector raised as a new job; extensions; extra days capped at a month; billing cycles that are not calendar months | **These need your billing rules written down before I test them — I will not guess** |

## D — Older Money problems confirmed again on V51.1 (from the earlier audit list)

These came from the earlier findings list and were checked again on V51.1: they still exist. I left them out of the first version of this pack. That was a gap, now corrected.

| # | Item | What happens today | Proposal | Your yes needed? |
| --- | --- | --- | --- | --- |
| D1 | HF-005 | **The same expense is counted twice in profit by default.** A ₹2,500 hotel recorded on the job closure *and* on the inspector's voucher shows as ₹2,500 expenses + ₹2,500 voucher. A "duplicates" worklist exists, but someone has to clear it by hand | Count it once automatically (match on job, date, amount), keep the worklist for doubtful cases | **Yes — money** |
| D2 | HF-194 | **Changing the invoice-number format mid-year restarts the series in the same year.** After AHM/2627/0002 the next invoice became AHM-2026-27-0001 | Continue the count within the financial year whatever the format; or forbid changing the format mid-year. **Ask your accountant — GST expects one unbroken series per year** | **Yes — GST** |
| D3 | HF-108 | Expenses on a job closure always name the job, but voucher lines may have no job — so some costs never reach any job's profit | **Your rule:** must every inspector expense name a job? | **Decision** |
| D4 | HF-171 | Recording receipts and starting invoices is allowed to anyone with "see inter-office credit" — a Coordinator recorded a receipt. There is no separate Finance permission for writing to the books | Give book-keeping its own permission (Finance and those you name) | **Yes — permission** |
| D5 | HF-036 | Tally is one-way: EXAACT exports to Tally but never reads back what Tally changed | Record as a known limit, or plan a read-back later | Decision |
| D6 | HF-103 | When a credit between offices is proposed or countered, the other office gets **no e-mail**, and the voucher download + submit step does not exist | Send the e-mail; add the step | Within P2 — I can fix |
| D7 | HF-104 | The Invoice tracker has no filters (year, month, office, business unit, client, status) — only a CSV download | Add the filter bar | Within P2 — I can fix |
| D8 | HF-197 | No screen shows documents waiting / sent / failed to MGH Books (Books is not connected on the copy, so a failure could not be produced) | Add a "Sent to Books" status list | Within P3 — I can fix |
| D9 | HF-086 | Money logic still sits in the general program file (technical tidy-up, nothing you can see) | Leave for later | No |

Also: **DEF-DB-001** (part-month invoice quantities cut to 3 decimals on MySQL — ₹6,41,637 instead of ₹6,41,580) is a Money problem too; it is in the Sales pack as **A8**, with **C18** (check invoices already issued on the live site).

## E — Not built (the documents describe them, the app has no button)

| # | Test | Gap | Proposal |
| --- | --- | --- | --- |
| E1 | MON-007 | The billable-events board has no **Cancel** button, although the rules allow Pending / Approved / Disputed → Cancelled (the server route exists) | Add the button (with a reason) — needs your yes: it changes what can be billed |
| E2 | MON-057 | Two people editing the same draft invoice get **no warning**; the later save silently wins | Warn and refuse the second save, as other screens already do |

## F — What is NOT tested yet in Money (so you know what the numbers above do not cover)

- **Billing scenarios:** 22 of the 27 man-day / man-month scenarios are not started (5 partly). Man-month is not touched at all. These need the rules in C14 first, so that I test against your rules and not my guesses.
- **Outside systems:** Tally import, MGH Books, a real Razorpay payment page and real printing cannot be done from the private copy.
- **Live data:** invoices already issued on the live site are not checked (see Sales C18). Nothing on the live site is touched without your separate permission.

So the Money picture is: **10 defects + 8 older problems still present + 2 features not built + 14 questions + most billing scenarios still untested.**

## After you answer

Same as Sales: one Money fix batch, the full suite on SQLite and MariaDB, the Money robot tests, one zip with the changed files and rollback steps.
