# EXAACT V51.1 — Report 55: "Who can do what", the new permission screen (batch B)

**Date:** 21 Nov 2026 · **Test copy only** (opstest). Nothing is live.
**Your request:** *"In permission settings a clear and detailed feature, then its sub features, shall be able to allow or disallow. At present the permission screen is also very weird and confusing."* — proposal approved *"All as recommended"*. Batch A (the new switches, report 54) came first; this is the screen.

## In one paragraph

There is now **one screen, "Who can do what"** (Admin → *Who can do what*). You pick a **role** or a **person** and see every area, then each feature inside it, then each sub-feature, each with an **Allowed / Not allowed** switch. The same page shows the person's **branches**, a plain-English box **"What they will see"**, and the **recent changes**. **Nobody's access changed.** The screen saves through exactly the same places the old screens saved, and a test proves that the same choices store the same value. The old *Roles & permissions* screen is still there for one release ("Old layout" button).

**Update for opstest:** `EXAACT-V51.1-Permission-Screen-TEST-COPY-update.zip`
- 841 files, sha256 `32c3fe0f…daff7`;
- code commit 06eb340, release stamp e8b2b2c;
- also includes Stage 1, DEF-01, defects batch 1 and permission foundation A;
- no `config.local.php`, no root `tenants.php`, no tests, no data.

Install it as before: back up, upload and extract it in the opstest app folder only, open `/deploy-check.php` (all green, red TEST COPY bar still showing).

## 1. What changed

| | Before | Now |
|---|---|---|
| Where | Two different screens: *Roles & permissions* (a 6-column tick grid and a second list underneath) and the *Permissions* tab of each person (a different layout) | One screen with tabs: **Roles · People · Compare roles · Company switches · Plan** |
| How a right is shown | The same right sometimes twice, under two names ("Approve" in the grid and "Approve quotations" in the list) | **Once**, as a switch, under its area and feature (e.g. Sales → Quotes → *Send to the client*) |
| Branches | On a third tab of the person form | On the same page as the person's switches |
| What the person will see | Not shown anywhere | A plain-English list on the right, worked out by asking the same checks the screens ask |
| Exceptions for one person | Not visible | Each switch says **"From role"** or **"Changed for this person"**; the top says "follows the role, with 2 exceptions" and offers **Reset to the role** |
| Rules nobody can tick | Not shown | Shown in grey with the rule, e.g. *Mark a lead lost — decided by a rule: the lead's owner* |
| Your open question 10 | — | Shown once, marked **"Needs your decision"** (whose records: own / team / branch / all) |
| Phone | 303 tick-boxes, 14,282 px tall | Features fold away; a drop-down picks the role or person; Save stays at the bottom of the screen |

## 2. What each part does (plain English)

- **Roles tab.** Left: the roles and how many people are in each. Middle: the role's switches. Right: what a person in this role will see.
  - **Copy from role…** shows another role's switches on this role. Nothing is saved until you press Save.
  - **Reset to recommended** and **Built-in default** do what the old buttons did, and ask first on the page.
  - **Save for N people** says how many people the change reaches.
- **People tab.** Left: the people you may manage (a branch user manager sees only their branch). Middle: the person's branches and switches. Right: what they will see and their recent changes.
  - **Only the exceptions** filter shows only what differs from their role.
  - **Name, role, password** opens the person form for everything else.
- **Find a feature** (search) and the filters **All / Allowed / Not allowed / Needs decision** work on both tabs.
- A **feature's own switch** turns all of that feature on or off (half-filled when some are on).
- Turning on **Delete** also turns on Edit and View, exactly as the old grid did.
- **Compare roles** lists only where two roles differ.
- **Company switches** summarises which modules and screens are switched off for everybody, with a button to the existing *Modules & features* screen.
- **Plan** lists the modules in your plan (read-only).

## 3. The five most common jobs

1. **Give a role a new right** — Roles → pick the role → open the feature → switch the sub-feature on → **Save for N people**.
2. **Give one person an exception** — People → pick the person → switch → **Save**. The switch now says *Changed for this person*.
3. **Take a person back to their role** — People → the person → **Reset to the role** → *Yes, go ahead*.
4. **Change which branches a person sees** — People → the person → tick the branches (or *Every office*) → **Save**.
5. **Check what someone can see** — People → the person → read *What … will see* on the right (on a phone: *What they will see* link under the search box).

## 4. What did NOT change (checked by tests)

- **No right was added or removed for anybody.** No new permission, no new status, no database table or column.
- **Roles are saved by the old Roles handler** (the new screen posts the same fields to it).
- **People are saved by the same rule as the person form**: the person form now calls the one shared helper (`user_access_from_post`), so the two cannot drift.
- **The same administrators get in**: Roles needs the Master Admin, as before; People needs the right to manage users, as before; a branch user manager sees only their branch and only the rights they may hand out, as before.
- **Every change goes on the sealed audit trail**, with who and when, as before.

## 5. Found and fixed while building (outside Sales; added to the register)

- **DEF-26 — a role your company added could be "saved" on the old screen but nothing changed.** Such a role always uses the switches of the role it was copied from. The new screen shows those switches locked, with a link to change them on the base role. The old screen is unchanged.
- **DEF-27 — the "you cannot remove your own user-management" guard misread the new storage.** Since batch A, a person's own access is stored as differences ("@diff…"). The guard looked for the bare words, so a branch user manager saving their own record with any exception was refused. It now reads what the stored value actually grants.

## 6. Tests

**The new test, `tests/test_whocan_screen.php`: 27 of 27 checks pass.** In plain words:

| What was proved | How | Result |
|---|---|---|
| Every right is drawn exactly once, and nothing the old screens drew is missing | The role tab draws the same 206 rights as the old Roles screen; the People tab the same 207 rights a company-wide user manager may hand out (23 for a branch user manager) | ✓ |
| **(1) An untouched Save stores the same value** | For every role (17 roles, with the built-in defaults and with a saved grid — 34 role screens), the new screen draws the same boxes, ticked the same way, as the old one, and posts them to the same handler. For the 41 people of the batch A snapshot (every role, one branch or every branch, custom roles, every stored format), the same switches and branches as the person form | ✓ |
| **(2) Switching a sub-feature stores the same value** | 33 real saves, made once through the old screen and once through the new one, compared: untouched saves, switched rights (a verb with its weaker ones, a sign-off right, a download, customer money, see-all), a changed branch, and a branch user manager's save | ✓ identical every time |
| **(3) "What they will see" tells the truth** | For all 41 people, every line was compared with the real check signed in as that person; the administrator stays signed in as themself. For all 17 roles, the role's preview agrees with a person who follows the role exactly | ✓ |
| **(4) The same administrators get in** | Master Admin, Admin, Branch Manager, Coordinator, Finance, Business Director, Marketing Executive: the Roles tab opens exactly when the old Roles screen does, a person exactly when the person form does; a Marketing Executive posting to the save changes nothing | ✓ |
| History, custom roles, the self-lockout guard | A save is on the audit trail in words; a company-added role is locked to its base; the guard reads the "@diff" value correctly | ✓ |

**Real browser** (on a throwaway copy): 18 of 18 checks pass.
- Switching on *Delete* also switches on *Edit* and *View*.
- A feature's own switch, search and filters, and *Undo changes* work.
- A real Save works and shows in the history; an exception shows *Changed for this person*, and *Reset to the role* works.
- *Compare roles* works, and no script error appears on any page.
- **8 screen widths × 4 pages = 32 checks:** nothing scrolls sideways, nothing is cut off, and every button and switch is at least 32 px tall.

**Related existing tests:** 95 files, 3,659 checks; 3,650 pass.
- **The 9 that fail are not caused by this change.** `test_m7_report_export_entitlement` (6) and `test_m8_public_background_entitlement` (3, DEF-24) fail exactly the same way on the code before this batch (58d8c4e).
- **Two older tests were updated to follow the move, with the same intent:**
  - `test_m10_master_entitlement` now lists the new screen's three gates, which give the same answers as the old screens;
  - `test_perms_no_lockout` now finds the person save in the shared helper.

**Robot:** all 104 cases, compared case by case with the previous run: identical (46 passed, 23 failed, 24 incomplete, 10 need you, 1 out of date — none worse, none better).

## 7. Your check list on opstest (10 checks)

1. Admin → **Who can do what** opens on the Roles tab. The old *Roles & permissions* tile is gone; the **Old layout** button still opens the old screen.
2. Pick **Marketing Executive**. Open **Quotes**: with the standard roles, *Send to the client* and *Approve quotes* say **Not allowed**, and the right side says "cannot send, cannot approve". (Feature names use your own words from Admin → Terminology, as every other screen does.)
3. Switch **Send to the client** on — the bottom bar says "1 switch changed — not saved yet". Press **Undo changes** — it goes back.
4. Open **Leads**, switch **Delete leads** on — *Edit leads* and *View leads* switch on too. Undo.
5. Type **download** in *Find a feature* — the three download switches show (leads, deals, quotations).
6. **People** → a Marketing Executive → switch **Send to the client** on → **Save**. The top now says "with 1 exception" and the switch says **Changed for this person**. Open the old person form (*Name, role, password* → Permissions): the same box is ticked.
7. On that person press **Reset to the role** → *Yes, go ahead* — back to "follows the role exactly".
8. **Compare roles**: Marketing Executive and Marketing Manager — the list shows only their differences.
9. Sign in as a **Branch Manager** (who may manage users of their branch): **Who can do what** shows only the People tab and only people of their branch.
10. On a **phone**: the role and person are picked from a drop-down; nothing scrolls sideways; Save stays at the bottom of the screen.

If anything is wrong, restore the backup taken before installing.

## 8. Pictures

The pictures follow on the next pages:
- before (old screens);
- after on a laptop (1366 px);
- after on a phone (390 px).

On the full-page pictures the Save bar is drawn at the end of the page. On the screen it stays at the bottom while you scroll.
