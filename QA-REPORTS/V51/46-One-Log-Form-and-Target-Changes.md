# One log form, target changes shown, phone lead page fixed (19 Nov 2026)

**You reported (update 3bd0a2f)**

1. *"When clicked on Log button in Today it opened a new and different form than the lead opened from Pipeline."*
2. *"When target is changed then what is changed must be reflected somewhere."*
3. In your phone screenshot, the lead's Overview boxes showed the contact's name and mobile under every heading ("E-mail: Harshal Patil", "Hoped to close: 1597536540").

**Your choices:**

- **Log:** one form everywhere.
- **Targets:** before → after in History, a mark on the month box, a notice for the salesperson, and an e-mail.

**This zip** replaces update 3bd0a2f and contains everything in it plus the fixes below.

---

## 1. One log form everywhere

**Before**

- The lead page had its own long form.
- Sales → Today's 📝 Log opened a second, different screen.
- The deal page had no way to log a call at all.

**Now:** there is **one form**, "📝 Log a conversation":

| Where you are | What 📝 Log does |
|---|---|
| Lead page → Contacts | the form, open |
| **Deal page** (new — it had none) | the same form, above the Activity list |
| Sales → Today → 📝 Log on a row | opens that **lead's or deal's own page** with the form open; after **Save this contact** you land back on Today |

**What the form has**

- **How:** call, WhatsApp, e-mail, meeting, visit, note.
- **What happened:** the choices from Sales settings. Each one fills **"By when" in working days**; your weekly off and holidays are skipped.
- What was said, who you spoke to, the next thing to do, and by when.
- **More details (optional):** which way, when it happened, time it took, and how they prefer to be reached.

**Example.** Today, Thursday 19 Nov, you press 📝 Log on *Blue Ridge Tanks* and pick *"Asked us to call back later"* (7 working days). "By when" becomes **Fri 27 Nov**, with Sunday the 22nd skipped. You press **Save this contact** and you are back on Today, where the row now shows 27 Nov.

**One difference from the old lead form:** "How it went" was a free-text box; now you pick one of the "What happened" choices. If none fits, write it under "What was said". The choices are yours to rename or add to in Sales → Sales settings.

## 2. A changed target is shown in four places

| Where | What you see |
|---|---|
| **Targets → History** | every change as **Before → After**, with ▲/▼ and who changed it, e.g. *Apr 2026 · Test BDM · ₹5,50,000 → ₹5,75,000 ▲ ₹25,000 · admin · 19 Nov* |
| **The month box** | a small **"↻ was ₹5.5 L"** mark; hold or tap it to see who changed it and when |
| **The salesperson's Sales home** | an orange box **"Your target was changed"** with each month before → after, until they press **OK, seen** |
| **E-mail to the salesperson** | one e-mail per save, listing every month that changed. On the test copy it reaches only your own address |

The earlier figure is never overwritten; it stays in the history.

## 3. Phone: label / value boxes fixed

The lead Overview (and every other screen with a "label | value | label | value" table) now shows each value under its own label on a phone: *"Who to talk to: Mr. Example"*, *"Telephone: 98250 55555"*. The cause was the shared phone layout, which read the first row as the headings for every box.

## 4. Also found and fixed while testing

When the deal page first had the form, its **Activity list below went blank**: the form's internal names clashed with the deal page's own. This was caught on the test copy before you saw it, fixed, and is now checked by an automatic test.

## 5. Tests

| Check | Result |
|---|---|
| Automatic tests, Sales parts 2–3 (now including the one form and target changes) | **61/61 ✅** |
| Every other test file touching the lead page, deal page, phone tables or Sales | **all 21 files pass** · leads 149 ✅ · Sales 510 ✅ · deals ✅ · activities ✅ · one firm record 21/21 ✅ |
| Robot: the 98 screen-by-screen cases | **identical to the last update** — nothing got worse |
| Robot: the Sales cases that use the log form | **SAL-020 "log a contact"**: all 3 steps pass (step 3 used to fail). **SAL-160**: same as before. **SAL-180**: only steps 8–9 fail, both about who sends an approved quote — the "approvals & sending" work you put on hold (before this update steps 4, 8 and 9 failed) |

**Found and fixed by the robot before this zip**

- After **Save this contact** on a lead opened from the Pipeline board, you were taken to Today instead of back to the lead. Now you return to where you started, and an automatic test checks this.
- The robot's own "open a Sales tile" step didn't know the Sales area now opens on "Start here", with Leads under the "Records" tab. The robot was updated; the app was not changed for this.

## 6. Your check list on opstest (8 checks)

1. Sales → Today → **📝 Log** on a lead row. The **lead's own page** opens with the form open.
2. Pick **"Spoke — interested"**. "By when" fills in 3 working days ahead, skipping Sunday. Save. You are back on **Today**, and the row shows the new date.
3. Open a lead from the **Pipeline board** → Contacts. It is the **same form**.
4. Open a **deal** and scroll down: the same form, with the Activity list below it.
5. Sales → **Targets**: change one month and Save. **History** shows before → after, and the month box shows "↻ was …".
6. Log in as that salesperson. **Sales home** shows "Your target was changed"; press **OK, seen** and it goes.
7. Check your inbox for the e-mail *"[TEST COPY → …] Your sales target was changed"*.
8. On your **phone**, open a lead → Overview. Each value sits under its own label.
