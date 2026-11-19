# Deal wording, DEAL numbers and the Sales test book (19 Nov 2026)

**You asked**

1. *"It must be deal but check opportunity is mentioned … In pipeline it is mentioned as enquiry. Email when target changed are not triggered."*
2. *"Yes change it and provide me all edge cases particularly for sales a detailed testing."*

**This zip** (update **@@RELEASE@@**) replaces update 906e79f. It contains everything in that update plus the changes below.

---

## 1. "Deal" everywhere

| Before | Now |
|---|---|
| "Opportunity", "Open opportunity", "Opportunity pipeline" | **Deal**, **Open deal**, **Deal pipeline**, on every screen, button, breadcrumb and message |
| First stage of a deal: "Enquiry received" | **"New deal"** (renamed once; a stage you renamed yourself is left alone) |
| Deal numbers OPP-… | **DEAL-2026-0001**, DEAL-2026-0002, … Older OPP- numbers stay as they are |

You can change the deal number's prefix, year style and padding in **Settings → Numbering → Deals**, the same place as the other numbers.

## 2. Target-change e-mails: you are now told whether each one went

When you save a target, the green message names each salesperson and says either **"E-mailed Kiran."** or **"Could not e-mail Kiran: …"** with the reason. If any e-mail failed, the message turns orange.

The usual reasons are:

- the person has no e-mail address on their login (Admin → Users);
- e-mail is not set up on this site;
- on the test copy, e-mails go only to your own address.

Why the earlier e-mails did not arrive is shown under **Settings → Notification log**; please look there once.

## 3. The Sales test book

**What it is.** **266 test cases** for Sales, in three parts. Each case gives:

- who to log in as;
- what to set up first, with real figures;
- each step: **Do this → You should see**, with a ☐ to tick.

The three parts are:

| Part | Covers | Cases |
|---|---|---|
| 1 | Simple Sales: Sales home, Today, Pipeline board, new lead, the one log form, orders, renewals, targets, Sales settings, one firm record | 102 |
| 2 | Leads, deals, enquiries and costing | 81 |
| 3 | Quotations, approvals, billing schedule and contracts | 83 |

Every case was first worked through by a helper on a private copy of this code, with dummy data. Each problem found is marked in the book: **green** = fixed in this update, **blue** = fixed earlier, **amber** = needs your decision, **red** = not fixed yet.

**What the testing found: 82 problems**

| | Count |
|---|---|
| Fixed in this update | **36** |
| Fixed in the previous update | 6 |
| Needs your decision | 1 |
| Not fixed yet (listed in the book, for the next round) | 39 |
| **Serious ("high") problems** | **6 found, 6 fixed** |

### What was fixed in this update, in plain words

**Branch boundaries.** A person now sees and changes only their own offices' work.

- **Renewals** (the screen, Today and Sales home) list only contracts of your offices. Before, a Mumbai salesperson saw Ahmedabad customers' contracts and values.
- The **Pipeline board's "Confirmed orders"** shows the same orders as Orders & payments. Before: 5 orders, ₹19.8 L, for a salesperson whose own list showed 4.
- **Sales home → Data health** and the **salesperson chips** count only people and records of your offices.
- A **new lead or deal** can be opened only in an office you work for. **"Work this as a deal"** works only on a lead of your office. A **quotation of another office** cannot be attached to a deal.
- **"Give it to"** on a new lead, and **"New leads go to"** in Sales settings, offer and accept only people who work for that office. Before, a lead given to someone of another branch was invisible to them.
- A quotation's **billing schedule** and **follow-up e-mail** follow the quotation's branch and the "assigned to" rule.

**Serious ones.** All five could be done by typing an address or sending a form directly.

- A salesperson without the Work-orders right could **raise a work order** from a won deal. Now refused.
- An **accepted quotation could be detached** from its won deal, so one sale could reach Accounts twice. Now it stays attached.
- Any approver could make **someone else's approvals go to another person**, even to the quotation's own preparer. Now an approver can name cover only for **their own** approvals. An administrator can still do it for anyone.
- A client's **fixed rate** drifted by paise. For example, ₹50,000 × 10 became ₹5,00,000.03, which crossed the ₹5 lakh approval line. Now it is exactly ₹5,00,000.
- After withdrawing an approval request, the app could be **sent to an outside website**. Now it returns only to a page of this site.

**Everyday fixes**

- **Accounts (Finance)** now see every order of their offices on Orders & payments. Before, they saw none, because they own no quotations.
- **Targets**
  - A second manager working in an older open page can no longer undo the first manager's change. The second manager is told which month to check.
  - Minus figures are refused with a message.
  - Paise are rounded to whole rupees, so History and the e-mail show exactly what is stored.
- **WhatsApp 💬** shows only for mobile numbers. A landline (022 …) gets Call only.
- **"Meeting fixed"** (0 days = you pick) now asks for the meeting date.
- **Deal page Activity**
  - It shows **Incoming / Outgoing** and what happened.
  - It no longer repeats "Telephone call Telephone call".
- **Sales settings**
  - An outcome written only in Hindi or Gujarati is kept. Before, it vanished.
  - Each office's list shows only that office's people.
- **Wording and screen fixes**
  - **Today**: a contract ending today says **"ends today"**, not "in 0 days".
  - **Phone**: the log form no longer hides under the top bar.
  - **Renewals**: "🧾 Renewal quote" shows only to people who may make quotations.
  - **Sales home**: with "Today" switched off, its cards still show their figures and open the nearest screen that is on.
  - **Sales → Records**: Finance no longer sees the "Deals" and "Pipelines" tiles that only refused them.
- **Quote GST note**: when your own state is missing it says so. Before, it blamed the client's state. Please set **Settings → Tally / accounts → "Our state"** on opstest.
- **Sales dashboard**: the weighted figure now explains itself. It uses each deal's own chance where one is set, otherwise the stage's win %.
- **Client register**: "Last contact" counts only real contacts, not automatic entries such as "Deal opened".
- **Contacts**: the buying role must be one from the list. Masters now shows where that list is used.

### Not fixed yet

39 smaller problems are listed in red in the book, so nothing is lost. Examples:

- impossible dates in follow-ups and on the lead form;
- file names in Hindi or Gujarati downloading as "____";
- a contract end date allowed before its start date;
- Gujarati letters printing as "?" on the quotation PDF.

I suggest fixing them in the next round, after you have tested this zip.

### Questions only you can answer

The book lists **43 questions**. Nothing was changed for them. The ones that matter most:

| # | Question | What it affects |
|---|---|---|
| 1 | Is **Saturday** a working day for follow-ups? Today only Sunday is off (Sales settings → weekly off). | Every "By when" date |
| 2 | **Targets for "This financial year"**: count only the months so far, or the full year? | The target card on the dashboard |
| 3 | Should the **Marketing Manager** set targets and see the whole team? Should a **Branch Manager** set targets only for their own branch? | Who manages targets |
| 4 | Should a customer made from a lead get the **lead's branch** and be asked for **payment terms**? | The new customer's record |
| 5 | **GST for a branch with its own GSTIN**: should Mumbai quotations to Maharashtra clients show CGST + SGST? | The GST split shown, and the invoice |
| 6 | **Client codes** use the calendar year (AHM-27 from 1 Jan) while deals and invoices use the financial year. Keep it? | Client numbering |
| 7 | May the number of a **deleted** lead, deal or contract be given to the next new one? Proposed: no. | History and e-mails pointing at the wrong record |
| 8 | Contract value from a quotation: **with or without GST** (your Sales pack A2)? | Contract value and billing |

## 4. Tests

| Check | Result |
|---|---|
| Automatic tests for this work (Sales parts 2–3, including every fix above, each with a "still allowed" check) | **@@T_MORE@@** |
| Every other test file for Sales, leads, deals, quotations, costing, approvals, contracts, clients, switches | **@@T_REL@@** |
| Robot: the screen-by-screen cases | **@@ROBOT@@** |

## 5. Your check list on opstest (12 checks)

1. Open any deal. Every word says **Deal**. The first stage is **New deal**. A new deal is numbered **DEAL-2026-…**.
2. Sales → Targets: change one month and Save. The message says **"E-mailed …"** or **"Could not e-mail … because …"**.
3. Settings → Notification log: see why the earlier target e-mails did not go.
4. As a Mumbai person, open **Renewals**. Only Mumbai contracts are listed.
5. As a Mumbai person, open **+ New lead**. The Branch box shows only your office(s).
6. Sales settings → **New leads go to**: each office lists only its own people.
7. As **Test Finance**, open Sales → **Orders & payments**. The company's orders are listed.
8. Log a call on a lead and pick **"Meeting fixed"** without a date. You are asked for the date.
9. Targets: type **-5000** in a month and Save. You are told it was not saved.
10. A client with a landline only: **no 💬 WhatsApp**, Call only.
11. On a won deal, try **Detach** on its accepted quotation. It stays attached, with a message.
12. Open the **Sales test book** and work through Part 1 at your pace. Tick what matches and note what does not.
