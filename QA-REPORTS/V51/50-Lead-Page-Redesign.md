# The lead page, redesigned from your picture (20 Nov 2026)

**You said**

1. *"Can you improvise our screen as shown in this image ... make it mobile friendly."*
2. *"Hope you have not missed ... each and everything what was seen in the old screen right??"*
3. *"When I marked lead as lost, under overview it still shows how promising it is 35/100."*
4. *"Yes built at present looks ok. But this shall be made universal ... for all the screen."*

And the three fixes you agreed earlier:

- a refused edit keeps what you typed;
- "Full lead form ›" keeps what you typed in the quick form;
- files can be attached when a lead is created.

**This zip** (update **__STAMP__**) replaces update 9999868 and contains everything in it.

---

## 1. What the lead page looks like now

It follows your picture (pictures 1–11 you approved).

| Part | What it shows |
|---|---|
| **Header card** | Company name, lead number, branch. The **status pill** at the right: tap it to move the stage. The contact person, phone and e-mail. Three buttons: ✏️ Edit, 📎 Attach, ⋯ More |
| **Three boxes** | Lead value · Hoped to close · Handled by — always the same size |
| **Lead stage** | Your own stages, then **Quotation**, **Won**, **Lost**. Ticks for the stages passed; a red ✕ only on a lost lead |
| **Five tabs** | **Overview** · **Details** · **Contacts** · **Quotes** · **More** (with counts) |
| **Overview** | Next action with the one main button; Quick actions (Log call, Send e-mail, Add task, Add note); Lead summary; latest History; What they want; How promising |
| **Details** | Every field with an icon, Call / WhatsApp / Copy beside the number and e-mail, the Edit form, Manpower deputation |
| **Contacts** | Every call, message and visit, newest first, and the one log form |
| **Quotes** | Each quotation in plain words, e.g. "Sent — waiting for their answer"; Make a quotation; Project costing |
| **More** | Documents, Full history, View flow (how it moved), How promising, Work as a deal, Delete |

**On a phone:**

- everything stacks in one column;
- the status pill drops under the company name, so a long name keeps its width;
- the stage menu opens across the card.

**On a laptop:**

- the header sits beside the three boxes;
- the Overview cards sit in pairs of the same height.

**Example — a lead where the quotation has been sent:**

- the pill reads **Quotation sent**;
- the stage path shows New enquiry ✓ → Contact made ✓ → Qualified ✓ → **Quotation** (now);
- the main button is **Open quotation Q-AMD-2026-0907**.

### Made universal (your instruction)

The pieces are built once and kept for the next screens:

- the header card, the three boxes, the stage path, the icon tabs and the cards;
- the label/value rows, the history list and the quick actions;
- one line-icon set.

They live in `views/ops/_rec_ui.php` and one block of the stylesheet. They are written down as the standard in `docs/05-ui-ux-blueprint.md` ("The record page kit"). The deal, quotation, enquiry, project costing and order pages will use the same pieces after you approve their pictures.

## 2. "How promising" only on a lead you are still chasing

| Lead | Before | Now |
|---|---|---|
| Open | Score shown, e.g. 62 / 100 | Same |
| **Lost** | 35 / 100 was still shown | **Not shown** — the page shows "Why it was lost" |
| **Won** | Score shown | **Not shown** — the page shows the green "is now a customer" banner |
| Leads list, Score column | A number for every lead | **"—"** for won and lost leads |
| Leads list, CSV download | A number for every lead | **Blank** for won and lost leads |

Nothing is recalculated or stored differently. Only what is shown changed.

## 3. The three fixes you agreed

**a) A refused edit keeps what you typed.**

- **Example:** open a lead → Details → Edit the details. Clear the company name, change the contact to "Mehta", and press Save.
- **Before:** you landed on a page that does not exist, and everything you typed was gone.
- **Now:** you stay on the lead, and the Edit form is open. "A lead needs a company or person to chase" is shown. "Mehta" is still in the box.

**b) "Full lead form ›" keeps what you typed.**

- **Example:** on Sales → New lead, type "Shree Engineering", 98250 12345 and ₹2,50,000, then press **Full lead form ›**.
- **Now:** the full form opens with all three already filled in.
- Carried across: company, contact, mobile, source, value, office, owner and first follow-up date.
- A chosen file cannot travel between pages (no browser allows it). If you had picked one, the page asks you before leaving.

**c) Attach files when the lead is created.**

- Both new-lead forms have **Attach files (optional)**.
- The full form also asks "What is it?" and takes a note.
- The limit is 8 MB per file, as on the lead's Documents.
- **Example:** create a lead with "RFQ.pdf". The message says "Lead L-2026-0008 created. 1 file(s) attached." The file is under More → Documents, and History shows "1 … file(s) attached".

**Also fixed on the way:**

- **The old Edit form had no manpower deputation boxes.** Saving it emptied a lead's man-month / man-day details. The new Edit form carries them, so saving keeps them.
- **The 📎 button at the top** jumps straight to More → Documents.

## 4. Backend changes (owner-approved fixes)

Only these server-side changes were made. Each is limited to an approved fix or to hiding the score:

1. **Hide the score on won and lost leads (display only)**, `lib/leads.php`:
   - the Score column shows "—";
   - the CSV leaves the cell blank.
2. **Refused edit keeps what was typed (fix a)**, `lib/leads.php`, lead-edit:
   - each refusal keeps the typing for the lead page (the same "keep what was typed" helper other forms use);
   - it returns to `/lead?id=…&edit=1` instead of the non-existent `/lead-edit?id=…`;
   - the checks themselves are unchanged.
3. **Attach on create (fix c):**
   - `lib/leads.php` gains one helper, `lead_attach_uploads()`. It holds the upload code moved word for word out of the Documents handler: the same 8 MB limit, the same "empty file" check, the same kinds, the same database insert and the same History line;
   - the Documents handler, the full form (`/lead-new`) and the quick form (`/sales-lead`, `lib/sales_more.php`) all call it;
   - the success message now says how many files were attached.
4. **Quick → full form carries values (fix b):** no server change. The full form already read values from the address. Only the quick form's link was changed, in `views/ops/sx_lead.php`.

What did **not** change:

- no database table, column or migration;
- no permission, status, stage or approval step;
- no route or form address;
- no existing field name;
- no CSRF or escaping rule.

The only new field names are those on the two new-lead forms that fix (c) needed: `files[]`, `file_kind`, `file_note`.

## 5. Needs separate backend work (not done here)

1. **The manpower wipe can still happen from other pages.** The lead-edit handler still saves an empty manpower deputation when a form does not send those fields. The new page always sends them, so this no longer happens from the lead page. It would still happen from any old bookmark or other page posting to `/lead-edit`. A one-line server guard would close it; it was not added, under the "approved fixes only" rule.
2. **The "put back what you typed" note says "signed out".** It shows for any refused form, not only after a sign-out. On the lead page it is now suppressed, because the page puts the typing back itself. Elsewhere the wording could be made general.
3. **No view-only Sales role exists in this install.** The "can see but not change" layout is built and tested by its conditions. It is not drawn for a real role, because none exists.

## 6. Everything from the old screens — where it is now

**The lead page — every state:**

| Old screen | New screen | Who / when |
|---|---|---|
| Thread strip (lead → deal → quotation → … ) | Top of the page, unchanged | Once something follows the lead |
| Company, lead number, status, stage | Header card; status pill | Everyone |
| Delete button (header) | ⋯ More → Delete this lead; More → Remove this lead | People who could before (administrators, or anyone who edits leads); never on a won lead |
| Reassignment waiting + Approve / Reject + note | Yellow banner under the three boxes | Banner for everyone; Approve / Reject for the manager who decides |
| Won banner + links (customer, accepted quotation, enquiry, deal) | Overview: green banner with link buttons | Won lead |
| Lost: reason, remarks, Reopen (with a reason) | Overview: "Why it was lost" (locked) + Reopen this lead | Lost lead; Reopen for those allowed |
| Three plain steps | Lead stage path (your stages + Quotation + Won + Lost) — your picture | Everyone |
| "Your quotation … is waiting for approval" / "Nothing is logged yet" sentence | Next action card | Open lead |
| Next step and its date | Next action card, with "N days late" / "Today" | Open lead |
| "N days without progress" | Lead summary → In this stage | Open lead past its stage's limit |
| Main button: Log first contact / Make the quotation / Open quotation | Next action card (same rule) | Open lead, people who edit |
| Open the deal | Next action card; ⋯ More | Lead with a deal |
| "More — change stage, mark lost…" | Status pill ▾ (Change the stage) | Open lead, people who edit |
| "Closes by itself as won when they accept" | Next action card | Open lead |
| Contact, telephone, e-mail, best reached by | Header contact line; Details | Everyone |
| Worth about, hoped to close, handled by, branch | Three boxes; header; Details | Everyone |
| Effort so far / to win | Lead summary | Everyone |
| What they want | Overview card; Details | Everyone |
| Manpower deputation (type, how many, site, skills) | Details → Manpower deputation | Leads that have it |
| Contacts list + the one log form | Contacts tab (same form) | List for everyone; form for people who edit an open lead |
| Quotations list, Make a quotation, Project costing | Quotes tab | Same rules as before |
| Documents: file, kind, note, who, when, remove ✕; attach with "What is it?" and note | More → Documents; 📎 at the top jumps there | List for everyone; attach / remove for people who edit |
| Move to an open stage | Status pill ▾ → Mark how far it has got (asks to confirm) | Open lead, people who edit |
| Won without a quotation | Status pill ▾ → They said yes without a quotation (asks to confirm) | Same |
| Lost: reason + remarks; "only the person responsible" note | Status pill ▾ → Not pursued (reason + remarks) | Same rule (lead owner) |
| Work it as a deal | More → Work it as a deal; ⋯ More | Same rule |
| How promising + reasons | Overview + More | **Open leads only** (your instruction) |
| Edit the details (all fields + reason for reassigning) | Details → Edit the details; ✏️ and every "Edit" link open it | Open lead, people who edit; now with manpower fields |
| History: what happened + how it moved | Overview (latest 3), More → Full history, More → View flow | Everyone |
| Remove this lead (and why a won lead cannot be removed) | More → Remove this lead | Same rule |

**The full lead form:** every field is kept as it was. Added: Attach files, What is it?, Note.

**The quick lead form:** every field and button is kept (Save lead, Save & add another, Save anyway, Cancel). Added:

- Attach files;
- "Full lead form ›" now carries what you typed.

**Checked automatically.** The test keeps copies of the three old pages. For the lead page it lists every form address, every field, every link and every decision button on the old page — 42 in all — and confirms each is:

- in the new page;
- drawn on the screen in at least one lead state.

It also checks 39 labels (Approve, Reopen, Days there, …). For the two new-lead forms it confirms every old form address, field and link is still in the page.

## 7. Phone, tablet and laptop sizes

The page was checked at 8 screen sizes, with five leads (new, quotation sent + manpower, lost, won, reassignment waiting) at each size:

- 1920 × 1080, 1440 × 900, 1366 × 768, 1024 × 768 (laptops);
- 768 × 1024 (tablet);
- 430 × 932, 390 × 844, 360 × 800 (phones).

| Check | Before | Now |
|---|---|---|
| Page wider than the screen (sideways scrolling) | 0 | **0** |
| Buttons and boxes smaller than a thumb (under 32 px high) | 43 | **0** |
| Buttons or boxes cut off at the screen edge | — | **0** |
| Company name squeezed into a narrow strip | — | **0** (the 1024 px laptop with the side menu open was caught and fixed) |

Before and after pictures at each size are in the work folder.

## 8. Tests

| Check | Result |
|---|---|
| New automatic test of the lead page: every state (new, quotation sent + manpower, lost, won, reassignment waiting) drawn through the real screen code; every old element of the three pages looked for; the score; the refused edit; quick → full form; attaching on create; the quick actions | **234 / 234 ✅** |
| Every other test file for leads, Sales, deals, quotations, CRM, pipelines, approvals, stage gates, branch reach, deputation, won deals, form designer and feature switches | **4,580 checks, 0 failed ✅** (leads 383, Sales 596, quotations 265, deals 46, CRM 31, pipelines 256, approvals 627, stage gates 1,297, branch reach 581, form designer 193, feature switches 53, and the rest) |
| Real browser, on a copy of the test site | **11 / 11 ✅**: quick → full form carried company, mobile and value; full form created a lead with a file; the file showed under More → Documents; quick form saved a lead with a file; a refused edit stayed on the lead, gave the reason, kept the typing and left the form open; "Log call" opened the form with Telephone call ticked |
| Robot: the 104 screen-by-screen cases | **None got worse.** Four cases followed the old layout and were updated to the new one: SAL-020, SAL-160, SAL-180 and FLOW-01. Examples: "More — change stage" became the status pill; the "History" tab became More → Full history; the three steps became the stage path. After the update each gives the same result as on update 9999868 |
| Eight screen sizes × five leads | No sideways scrolling, nothing cut off, no button under 32 px (see section 7) |

## 9. Your check list on opstest (12 checks)

1. Open any open lead on your **phone**. The header, the three boxes, the stage path and five tabs appear, and nothing scrolls sideways.
2. Tap the **status pill**. Pick the next stage, confirm, and the stage path moves on.
3. **Overview → Log call.** The log form opens with "Telephone call" already ticked.
4. **✏️ Edit**: clear the company name and change the contact person. Press Save. You stay on the lead with a message, and your contact person is still there.
5. Edit the lead properly and save. It is saved, and a man-month lead keeps its site and people.
6. Press **📎**. More → Documents opens. Attach a file with "What is it?" and a note, then remove it. History shows both.
7. **Sales → New lead**: type a company, mobile and value, then press **Full lead form ›**. All three are filled in.
8. On the full form attach a file and press **Create it**. The lead shows "1 file(s) attached" and the file under More → Documents.
9. **Sales → New lead** with a file → **Save lead**. The message says the file is attached.
10. Mark a lead **lost** (status pill → Not pursued, with a reason). Overview shows "Why it was lost" (locked) with Reopen, and **no "How promising"**.
11. In the **Leads list**, that lost lead's Score shows "—".
12. Open a **won** lead. The green "is now a customer" banner with its links appears, with no score and no stage menu.
