# Expense setup made simple: fix report and opstest update (18 Nov 2026)

**Why this update.** Your checks 2 and 3 of report 38 failed, and you found the setup screens "very very confusing". This update has three fixes:

- **No more jumping to Masters.** The "Add or change an expense type" link used to open the old Masters screen.
- **Band amounts are no longer hidden.** The ₹ per km and hotel limit boxes only appeared after pressing "Open", at the bottom of a long page.
- **A band can now come from the role.** Before, a band could only be given one person at a time.

You approved the picture (artifact "Expense Setup Redesign", version 3) and your answers on 18 Nov:

- **The band is company policy.** Your words: *"Band limits are company fixed limits"*, for example hotel ₹10,000 for a director and ₹2,500 for an inspector.
- **A pay top-up is a personal allowance.** *"A person gets his own additional allowance to adjust the salary."* It is added on top of his band. Nobody is moved to a higher band just to raise his allowance.
- **P2: everyone in the approval chain sees the personal lines.**

Nothing else changed. The claim, the approval chain, the inspector's phone screens and the deadlines are the ones you are testing from report 38.

## What you will see

| Before | Now |
| --- | --- |
| Expense rules was reachable only through Approvals → Expense vouchers | **Admin → Expense setup**: its own tile. Breadcrumb **Home › Admin › Expense setup** |
| One long page; the type editor was on the Masters screen | **One screen, 4 tabs:** 1 Claim rules · 2 Expense types · 3 Bands · 4 People |
| Types were changed on Masters → Expense heads (codes, order numbers) | **Tab 2:** add or change a type right there, vehicles (per km) included. Masters → Expense heads now brings you here |
| Band amounts appeared only after "Open", at the bottom | **Tab 3:** the first band opens by itself. **One box per type**, with its meaning written under the name: *per km*, *per site day*, *per month*, *up to, per bill* |
| A band could only be given on each person's record | **Tab 4:** a **default band for each role**, plus a dropdown for each person. A band picked on the person wins; blank means he follows his role ("Field Inspector (from role)") |
| A figure typed on a person *replaced* the band, mixed into one big table | **The band is never changed on a person.** His record (tab **Expenses**) shows his band's policy as text and his **personal allowances**: on/off, amount, and from date |
| — | A **personal allowance** is an expense type with the tick "Personal allowance". It is paid **for each closed site day** or **once a month**, always **on top** of the band, as its own line marked **"personal"** that the approver sees |

**Example (your figures).**

- **Band "Inspector":** food ₹200 a site day, hotel up to ₹2,500, bike ₹6 a km.
- **Test Inspector:** gets a personal **site allowance of ₹100** a site day from 1 Dec.

On 2 Dec, one site day with 40 km fills in by itself:

| Line | Amount |
| --- | --- |
| Travel (40 km × ₹6) | ₹240 |
| Food allowance | ₹200 |
| Site allowance (marked *personal*) | ₹100 |

Everyone else in the band still gets ₹200 for food. His claims before 1 Dec keep their amounts.

**Two rules to know:**

- **A once-a-month personal allowance** starts with the first claim period that **begins on or after** its from date. Give it the 1st of a month as its from date.
- **A person with no band, and no band on his role**, keeps the old per-person rates. They are under "No band: this person's own rates (the old way)" on his record. As soon as he has a band, the band decides. His old rows are kept, not deleted.

## Reuse Gate: existing versus new

| Needed | Existing piece reused | New |
| --- | --- | --- |
| One setup screen | The same address, `/voucher-rules`, and the same Master Admin-only rule | Its 4 tabs and an Admin tile |
| Add or change types here | The same `expense_heads` / `travel_modes` rows the Masters lists show | Nothing new stored |
| Personal allowance | An expense type (`expense_heads`); amounts and from dates are the existing `inspector_allowances` rows | **One tick** on the type: `personal` (one new column) |
| Default band per role | The band list from report 38 | **One setting**, `exp_role_bands` (role → band) |
| A person's band | `inspectors.band_id` from report 38 | — |

**Database changes:** one column was added and one setting created. Nothing is deleted or rewritten.

**Permissions:** none new. Expense setup stays Master Admin only. A person's band and personal allowances stay Master Admin only, as the old "Allowances & rates" panel was.

**Docs updated:** `docs/04-flows/master_admin_and_admin.md` and `docs/02-permission-matrix.md`.

**Also fixed:** the breadcrumb showed "Home › Operations › Admin › …" on screens placed under Admin. The extra "Operations" came from the browser adding the highlighted menu area a second time. It now shows one area only, which also corrects "Expense voucher approvals".

## How it was tested

TBD-RESULTS

## The zip (opstest only)

TBD-ZIP

## Your checks (about 25 minutes)

Use the **Mehul Blogger** company, the one with your settings, signed in as **admin** unless the table says otherwise.

| # | Sign in as | Do | You should see |
| --- | --- | --- | --- |
| 1 | admin | **Admin** → tile **Expense setup** | Breadcrumb **Home › Admin › Expense setup** and 4 tabs. The Claim rules from check 1 of report 38 are still there |
| 2 | admin | Tab **2 · Expense types** → **Auto / local conveyance** → **Change** → Kind **No bill** → Save | You stay on the same screen. It shows "No bill · amount + note" |
| 3 | admin | Same tab → **Food allowance** → Change → Kind *Per day* → "By itself on every closed site day" → Save | It shows "Per day · added by itself" |
| 4 | admin | Same tab → **＋ Add an expense type** → Name **Site allowance**, Kind *Per day*, tick **Personal allowance** → Add | Listed, with the label **personal** |
| 5 | admin | Tab **3 · Bands** → open the band **Field Inspector** you added (or **＋ New band**). Tick and fill: bike ₹ __, food ₹ __, hotel ₹ __ ("up to, per bill") → Save band | "Saved, applies from today". Every box is on one table, with its meaning under each name |
| 6 | admin | **＋ New band** → "Director", **Start as a copy of** Field Inspector → Add → change the hotel to ₹10,000 → Save band | Director has the same amounts, except the hotel |
| 7 | admin | Tab **4 · People** → role **Inspector** → *Field Inspector* → Save | Test Inspector and Test Inspector2 show "**Field Inspector (from role)**" |
| 8 | admin | Same tab → Test Inspector → **Add** (personal allowances) → tick **Site allowance**, amount **100** → Save | "Expenses saved". The row shows ₹100 and today as its "from" date. His band is shown as text and cannot be changed there |
| 9 | Test Inspector (phone) | Today's job → **Close job** → type the km → Close the day | Travel = km × your rate, food allowance, and **Site allowance ₹100** as its own line |
| 10 | Test Inspector | **My expenses** → this claim → **Submit** | The site allowance line carries the tag **personal** |
| 11 | Test SrInspector | Open the claim from Approvals | He sees the **personal** line too. Only Approve / Send back |
| 12 | admin | **Admin → Masters → Expense heads** | It lands on **Expense setup → Expense types** |

Then carry on with **checks 5, 6, 8, 9 and 10 of report 38** (hotel without photo, auto ₹80 twice, photo + submit, approver, Finance advance).

**Your answers to X1–X5 (report 38) are still open:** *"Expense setup OK; X1–X5 as recommended"*, plus anything that did not match (a screenshot helps).

## Rollback
1. Restore the backup taken before uploading.
2. Or upload the previous zip, `EXAACT-V51.1-Expenses-TEST-COPY-update.zip` (24028c5).

The new tick and setting then stay unused.
