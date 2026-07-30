# Test script — one deal, end to end

One customer, one enquiry, one quotation, one job, one invoice, one payment.
About 40 minutes. Print this and tick as you go.

**For every step, write one of three things:** `OK` · `BROKEN — what happened` ·
`CONFUSING — what I expected instead`.

"Confusing" matters as much as "broken". A screen you had to think about is a
screen every customer will have to think about.

---

## Step 0 — before you start (5 minutes)

The server is running older code than the branch, so without this you will
report things already fixed.

1. Pull `claude/quotation-management-workflow-5dokb2` onto the server.
2. Sign in and open **any** screen with money on it.
   - **Check:** does it show `₹` or `?` — if `?`, the pull did not take effect.
3. Note the time. You are testing the code as of that pull.

Use a **real-looking but fake** customer so you can delete it afterwards.

---

## Part A — The lead (8 minutes)

**A1. Sales → Leads → + New lead.** Fill in:

| Field | Use this |
|---|---|
| Company | `Vikram Steel Fabricators` |
| Contact | `Ramesh Vikram` |
| E-mail | `ramesh@vikramsteel.test` |
| Telephone | `9825012345` |
| What they want | `Third-party inspection of 40 fabricated skids, IS 2062, at Rajkot` |
| Value | `250000` |
| Expected close | 3 weeks from today |
| Next thing to do | `Send capability statement` |
| By when | tomorrow |

- **Check:** on a phone, is every field full width and readable?
- **Check:** did the value show `₹2,50,000` afterwards, or `?2,50,000`?

**A2. Open the lead you just made.**
- **Check:** the **Score** panel — does it list the rules that fired, and do they
  make sense to you?
- **Check:** is there an **Allocated to** dropdown? Pick a real person and Save.
- **Check:** after saving, does it still show that person?

**A3. Log a phone call.** Use the activity/timeline area.
- **Check:** does it appear on the timeline with today's date?

**A4. Move it on.** Press the stage buttons — Contact made, then Qualified.
- **Check:** does "in this stage" reset to 0 days each time? *(It should — the
  stage genuinely changed.)*
- **Now press the same stage twice.** It should **refuse** and say it is already
  there. If it says "Moved." instead, that is a bug — write it down.

**A5. Try to delete the lead.** There should be a **Delete this lead** panel.
Do **not** confirm — just check it exists and reads sensibly. Cancel.

---

## Part B — The deal (8 minutes)

**B1. From the lead, press Open an opportunity.**
- **Check:** does it carry the company, contact, value and requirement across
  without you retyping anything?

**B2. On the opportunity:**
- **Check:** is there a **Pipeline** dropdown, and does it offer only *deal*
  pipelines (not lead pipelines)?
- **Check:** is **Allocated to** a dropdown, not a text box?
- Change the pipeline. **Check:** does the stage move to the new pipeline's first
  stage, and does **How it moved** record it?

**B3. Press Move it without changing the stage.**
- **Check:** refused with a clear message. If it flashes "Moved." — bug.

**B4. Move it forward one real stage.** Check the history row is right.

---

## Part C — The quotation (10 minutes)

**C1. From the deal, create a quotation.**
- **Check:** client, contact and requirement carried across?

**C2. Add two line items:**

| Description | Qty | Rate |
|---|---|---|
| `Stage inspection — skids` | `40` | `4500` |
| `Final inspection & report` | `4` | `7500` |

- **Check:** does the total compute, and is it `₹`?
- **Check:** payment terms and T&C — offered from a list, or blank boxes?

**C3. Submit for approval.**
- **Check:** does it say who it went to and why?
- Sign in as that approver (or use your admin) and **approve** it.
- **Check:** back on the quote, is the approval visible with a name and time?

**C4. Revise it.** Change a rate and use **Revise**.
- **Check:** does it keep the old version, or overwrite it? *(It should keep it.)*

**C5. Send it.** Use the compose/send option.
- **Check:** does it open your own mail program with the PDF and a sensible body?
- **Check:** the PDF — company name, GSTIN, signature, terms all present?

---

## Part D — Winning it, and the work (8 minutes)

**D1. Move the deal to Won.**
- **Check:** what does it tell you to do next? It should point at raising the
  order, not leave you guessing.

**D2. Raise the work order.**
- **Check:** does it carry customer, site, quotation, contract number, business
  unit and value **without you retyping**? List anything you had to type again —
  that list is the most valuable thing you'll produce today.

**D3. Allocate a job** to a person, for a date.
- **Check:** does it warn you if that person is unavailable, over their hours, or
  has a lapsed certificate?

**D4. Close the job.** Enter the dates and value.
- **Check:** does it ask for anything it should already know?

---

## Part E — The money (8 minutes)

**E1. Money → Work waiting to be billed.**
- **Check:** is your closed job listed, under the right customer?

**E2. Start the invoice from there.**
- **Check — this is the one I fixed, so please be strict:** are **payment terms,
  credit days, PO number and contract number** already filled, each with a small
  line saying where it came from? Anything blank that the system already knows is
  a defect.

**E3. Issue the invoice.**
- **Check:** does it get a number only now, not before?
- **Check:** GST split correct — CGST/SGST for same state, IGST for another?

**E4. Record a part payment** — say `₹1,00,000` — and allocate it.
- **Check:** does the invoice read as part-paid, not paid?
- **Check:** does the ageing report show the balance in the right bucket?

---

## Part F — Did the system notice? (3 minutes)

**F1. Sales dashboard.** **Check:** does your deal appear in the pipeline and the
forecast?

**F2. What to fix.** **Check:** does it mention the part-paid invoice or anything
else from your test?

**F3. Where the flow is broken.** **Check:** does it correctly say nothing is
broken about your chain — or does it flag something you know is fine?

**F4. Customer 360** for Vikram Steel. **Check:** lead, deal, quote, order, job,
invoice and payment all visible in one place?

**F5. The thread strip** at the top of any of those records. **Check:** can you
walk the whole chain from it?

---

## Afterwards

Delete the test data in reverse: payment → invoice → job → work order → quote →
deal → lead → customer.

- **Check:** does anything refuse to delete, and does it say why? *(Some refusals
  are correct — a converted lead should refuse.)*

---

## What to send me

Just the numbered steps with a word each, plus:

1. **Everything you had to type twice.** The single most useful output.
2. **Every screen where you paused to work out what to do.**
3. **Anything that said it worked when it hadn't** — or the reverse.

Do not tidy it up or group it. Raw notes in step order are more use to me than a
written report.
