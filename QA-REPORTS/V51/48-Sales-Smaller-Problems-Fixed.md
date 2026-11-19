# Sales: every smaller problem fixed, and your three answers (19 Nov 2026)

**You said**

1. *Saturday working day — "Yes but configurable."*
2. *Target for this financial year — "Full year Target."*
3. *Branch GST — "Yes if required at all."*
4. *"Fix all smaller problems."*

**This zip** (update **329f18f**) replaces update 69287af and contains everything in it.

---

## 1. Your three answers

| Your answer | What happens now |
|---|---|
| **Saturday is a working day, but configurable** | Already so. Sales → Sales settings → **Weekly off** is ticked for Sunday only, so Saturday counts as a working day. Tick Saturday there if that ever changes. Nothing was changed. |
| **Full-year target** | Sales dashboard → **"This financial year"** now compares what was won with the **whole year's target**. Before, it used only the months up to today. "This quarter" and "This month" also use the whole quarter's and the whole month's target. The card says "for the whole financial year". *Example:* targets of ₹5 L a month from April to March make the year's target **₹60 L**, not ₹40 L in November. |
| **Branch GST: "yes, if required"** | **Not changed yet. I need a clear yes from you first,** because this changes GST on invoices, and you asked never to change billing rules without your approval. See the question below. |

### The one question: branch GST

**Today.** Every invoice and quotation uses one state for the company: Settings → Tally → "Our state". The GST is split by comparing that state with the client's state.

**What I would add, only if you say yes.**

- A box **"Branch GSTIN"** on each branch, under Admin → Organisation.
- Leave it **empty**, and that branch bills exactly as today, under the company's GSTIN.
- Fill it in, for example 27ABCDE1234F1Z5 for Mumbai, and **that branch's** quotations and invoices use its own state (27 = Maharashtra).

| Example | Today | With a Mumbai branch GSTIN |
|---|---|---|
| Mumbai branch → client in Pune (Maharashtra) | **IGST** 18% (company state is Gujarat) | **CGST 9% + SGST 9%** |
| Mumbai branch → client in Ahmedabad (Gujarat) | CGST + SGST | **IGST** 18% |
| Ahmedabad branch (no GSTIN filled) → anyone | unchanged | unchanged |

**Reply "Yes, add branch GSTIN" or "No, keep one state".**

## 2. Every smaller problem fixed

All 82 problems in the Sales test book are now dealt with:

- **38** fixed in this update;
- **42** fixed earlier;
- **1** checked and found to work as you approved;
- **1** waiting for your answer: contract value with or without GST (your Sales pack A2).

### Leads

- A **date that does not exist** is refused, on the new-lead form and on edit. Examples: 29 Feb 2027, 2026-13-45.
- On **edit**, an empty company name and a **negative value** (−₹5,000) are also refused, as the new-lead form already did.
- **Files**
  - An empty file is called "empty", not "over 8 MB".
  - A file of exactly 8 MB is now accepted.
  - A file in **Hindi or Gujarati** downloads with its own name. Before, "स्पेक EC.txt" became "________EC.txt".
  - **Removing** a file is now written in the lead's history.
- **Bulk "Mark lost"** now does the same as marking one lead lost:
  - the lead moves to the Lost stage, and the history records it;
  - a later reopen returns it to the stage it left.

  The preview names leads by number (L-2026-0001), not "1".
- **"They said yes"** (lead → customer):
  - the customer gets the **lead's branch**;
  - the enquiry gets a **business unit**, asked on the screen when the lead has none;
  - the lead moves to the **Won** stage. Before, the CSV said "New enquiry · Won".
- **Bringing leads across from Ads Pro** now says the real reason it is refused.

### Deals

- A deal can no longer be moved onto **another funnel's stage** from an old tab. Before, it vanished from its board.
- A **blocked customer** gets no quotation from the deal. The deal page shows a red banner.
- **Funnels**
  - Two "Won" stages, or a "Won" stage first, are flagged, and such a funnel cannot be made the default.
  - New deals always open on an open stage.
- **Deal approvals**
  - They go only to people of the deal's branch. Before, a role-wide rule named both branches' managers.
  - The person a deal waits on, for example the Business Unit Head, can now approve it from **Approvals** even without access to Leads. Before, the deal was stuck.
  - "Withdraw the request" shows only to the person who asked.
- An existing **enquiry** cannot be switched to a blocked customer.

### Costing

- **"I know the rate" versus "CTC → rate": checked, works as you approved on 13 Nov. Not changed.**
  - With a **fixed client rate**, the margin you ask for is kept at that final rate, because nothing is left to negotiate.
  - With **"cost + my margin"**, the negotiation padding is added on top, so you have room to give it away.
  - *Example:* CTC ₹50,000 at 20% plus 5% padding is quoted at ₹65,625. A fixed rate of ₹65,625 at 20% affords CTC ₹52,500, because the 5% padding then becomes extra margin. Both are correct for their question.
- The approval box and the print sheet show the value to the paisa, e.g. ₹5,00,000.03, so the approval level is clear.
- ✕ on a role of a locked costing now says why nothing happened.
- A quotation made from a costing for a **typed (not yet registered) customer** keeps the customer's name.

### Quotations

- **After sending**, documents cannot be removed. Only a master can still add one, such as the client's PO.
- **Revise**
  - It is not offered on an **accepted** quotation. The won order stays with Accounts.
  - It is not offered on an older revision. Before, that gave an error page.
- **"Mark lost"** is offered only on a Draft or Sent quotation, as docs/03 draws it. On any other, the screen says what to do instead.
- **Follow-ups**
  - Impossible dates and made-up statuses are refused.
  - Finance sees the follow-ups without buttons that only refuse.
- **New lines** start as a "line-item" order, not "Open order (ARC)".
- **Totals**
  - The live total matches the saved one to the paisa.
  - The register shows paise when there are any.
- **Approve pressed twice** says "already approved", not "you are not an approver".
- The **e-mailed PDF** is named with its revision (Quote-Q-…-R1.pdf), like the download.
- A **contract cannot end before it starts**.
- A **delegation dated 31 Feb** is refused.
- **Checklist points** in Gujarati are no longer cut in the middle of a letter, which used to empty the whole box.
- **Milestone shares** show as typed (33.334%, not 33.33%), with amounts to the paisa.
- The **subject** takes up to 255 letters and says so. A longer one is refused with its length, instead of being cut on the live site.
- **Gujarati or Hindi letters on the quotation PDF**
  - The PDF's built-in font has English letters only, so these letters print as "?".
  - The quotation page now says so and points to the **Word** document, which keeps them; you can save it as PDF from Word.
  - A full fix needs a different PDF engine than your hosting has, so it is not part of this update.

## 3. Tests

| Check | Result |
|---|---|
| New automatic tests for this round (one per problem, with the book's example) | **35 / 35 ✅** |
| Every other test file for Sales, leads, deals, quotations, costing, approvals, contracts, clients, invoices, switches | **5,613 checks, 0 failed ✅** (Sales 596, quotations 265, costing 444, approvals 627, stage gates 1,297, pipelines 256, contracts 403, clients 249, invoices 81, branch scope 263 + 559, and the rest). On the first run four older checks failed. They showed that two of my changes went against your earlier decisions (costing 13 Nov; Business Unit Head approvals). Both were corrected, as described above, before this zip. |
| Robot: the screen-by-screen cases | **104 cases: none got worse.** One case (SAL-160) stopped at the new "business unit" question on "They said yes". The robot was taught to answer it, and the case is now exactly as before. |

## 4. Your check list on opstest (10 checks)

1. Sales dashboard → **This financial year**. The target is the **whole year's** total.
2. Lead → Edit. Clear the company name, or type −5000 as the value. You are told why, and nothing is saved.
3. Attach a file named in Gujarati to a lead, then download it. The name is kept. Remove it, and the lead's History shows "File removed".
4. Leads → tick two → **Mark lost**. Each lead now shows the **Lost** stage.
5. "They said yes" on a lead with no business unit. The screen asks for one. The new customer shows the lead's branch.
6. On a deal of a **blocked** customer, the red banner shows, and "Create quotation" is refused.
7. On an **accepted** quotation there is **no Revise**. On an approved, not-sent one there is **no Mark lost**.
8. Quotation → **+ Add line**. The new line says "Line item", not "Open order (ARC)".
9. As the Business Unit Head, open **Approvals** with a deal waiting on you. Approve it. The deal moves.
10. **Branch GSTIN**: answer the question above.
