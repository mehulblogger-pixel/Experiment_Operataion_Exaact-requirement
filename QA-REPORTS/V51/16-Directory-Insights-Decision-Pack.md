# Directory & Insights — decision pack (V51.1)

15 Nov 2026 · I ran all 104 Directory (clients, vendors, client-portal users, stamps) and Insights (dashboards, KPIs, analytics) tests on private copies of V51.1 with made-up data. I checked the serious finding in the program code myself. Nothing has been changed in the app.

**How to answer:** reply with item numbers, for example *"A1 yes, A2 yes, A3 yes, A4 refuse, A5 yes, B ok, C1 agree"*. Anything you don't mention stays untouched.

## Where Directory & Insights stand

| | Tests |
| --- | --- |
| Passed | 48 |
| Failed: 18 defects | 16 |
| Need your decision (section C) | 17 |
| Blocked (test wording out of date) | 16 |
| The robot cannot do it (real phone, or forms that only appear with certain data) | 7 |
| **Total** | **104** |

Most of the 16 blocked tests follow your **10 Nov decision**: the Management dashboard now needs both the Operations and the Financial dashboard rights. That changed who may open the Analytics screens. The tests are out of date, not the app.

## A — Fixes that need your yes (security, money figures, permissions)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-DIR-005 (P1)** | **The client-portal "Change permissions" screen edits the wrong person.**<br>• It shows the signed-in staff member instead of the portal user you clicked.<br>• Save writes the permissions and the "Access ends on" date to whichever portal user happens to have the same number as the staff member. In the test that was a user of another client.<br>• The user you meant to change keeps full access.<br>*I confirmed the cause in the code: the page frame replaces the screen's own "user" before the screen is drawn.* | Make the screen use its own user. Then check on the test copy whether any portal user's permissions were changed this way. On the live site, that check needs your separate permission |
| A2 | DEF-INS-002 (P2) | A branch's **own** revenue is counted as "Received from another office" and in "Credit received vs actual". The Invoice tracker's "credit not received" count has the same problem | Count it as "received" only when it really came from another office. **Money figure: needs your yes** |
| A3 | DEF-INS-003 (P2) | Dashboard **"Loaded cost"** ignores both the office scope and the filters. Both branch managers see the whole company's ₹1,62,000, including other offices' salary cost. An empty future year shows a net of −₹1,62,000 | Apply the office scope and the filters. **Money figure and scope: needs your yes** |
| A4 | DEF-INS-005 (P2) | **Anyone who can open Analytics can change company KPI targets and take or lock period snapshots.** A Coordinator, whose permission is View only, saved a target and took a snapshot | Only Master Admin / Admin (or whoever you name) change targets and lock snapshots |
| A5 | DEF-DIR-009 (P2) | **Working norms are saved but nothing uses them**, so utilisation never changes when you change a norm | Make utilisation use the norms. **This changes utilisation figures: needs your yes** |
| A6 | DEF-INS-001 (P3) | The Management dashboard "Direct cost" card and its explanation leave out the office overhead that the figure includes: ₹18,780 is explained as ₹16,000 + ₹0 + ₹1,500. The totals themselves are right | Show the overhead line in the explanation |

## B — Fixes I will make once you say "B ok" (small, no rule change)

| # | Defect | Problem |
| --- | --- | --- |
| B1 | DEF-DIR-001 (P2) | **Automatic client codes repeat.** Four companies share AHM-26-TBGRO-00003. The import matches companies by code, so a repeat code can attach data to the wrong company. I would fix how the next number is found. Existing duplicates need a one-off clean-up, which I will list for you rather than change |
| B2 | DEF-DIR-003 (P2) | In Activities, the "Customer" box only accepts the internal customer number. Typing the name saves the activity with no customer |
| B3 | DEF-INS-004 (P2) | Sales dashboard "Who is selling" counts won and lost deals by the date the deal was created, not when it closed. So it shows Won 1 while the same screen's headline says Won 2 |
| B4 | DEF-DIR-002 | Find duplicates calls a match on name alone "Certain — same tax number" |
| B5 | DEF-DIR-004 | A merged record stays on Find duplicates and can be merged again. Its name grows "[merged] [merged]…" |
| B6 | DEF-DIR-006 | Finance (read-only) is shown "+ Add Client" and "Edit", which are refused only after the click |
| B7 | DEF-DIR-007 | A button reads "Raise inspection inspection call": the word is doubled by your wording pack |
| B8 | DEF-DIR-008 | The Vendor register opened from Directory shows the Reporting breadcrumb, and Back goes there |
| B9 | DEF-INS-006 | Data quality shows "—" for all 23 check names, and Detail is empty |
| B10 | DEF-INS-007 | Typing "Sept" for a month says "period_key still needs filling in" (an internal name) |
| B11 | DEF-INS-008 | The "Contracting office" filter returns nothing on SQLite copies. Your live MySQL site is probably not affected, but that cannot be checked here |
| B12 | DEF-INS-009 | A heading shows an internal key: "Breakdown — service_mix" |

Plus new wording for the 16 out-of-date tests (and INS-001/002, DIR-022).

## C — Questions only you can answer (my proposal in **bold**)

| # | Test | Question | Proposal |
| --- | --- | --- | --- |
| C1 | INS-022 | The Coordinator sees Revenue on the Management dashboard, but `docs/01-roles.md` says the Coordinator "must never see revenue" | **Hide revenue from the Coordinator, as the document says** |
| C2 | DIR-014 · HF-193 | A merge of two clients goes through with no reason, and there is no way to undo it | **Require a reason. Undo: your call (it is new work)** |
| C3 | DIR-034 | The same stamp number can be issued to two people | **Refuse it** |
| C4 | DIR-021 | A lost hard stamp raises no warning anywhere | **Flag it on the Endorsement register and offer to raise an incident** |
| C5 | DIR-008 · INS-016 | Two people edit the same client, or the same KPI, at once: the last save silently wins | **Warn and refuse the older save** |
| C6 | DIR-031 | A Branch Manager can create a "branch company" only in their own office, although the panel says "for another office" | **Decide who may do it; I then reword the panel to match** |
| C7 | SCR-work-norms | Saving an all-blank norm silently sets the company-wide default | **Ask for confirmation first** |
| C8 | INS-005 | The "Invoiced value" KPI and the Management dashboard "Revenue" differ unless every job of the month is billed. Revenue includes agreed value not yet billed (see Money C3) | **Treat them as two measures and rename the KPI card "Invoiced"** |
| C9 | INS-006 | The scorecard weighs a fixed set of 6 KPIs; "Jobs closed" is not one of them | **Let you choose which KPIs count; until then, update the test** |
| C10 | INS-013 · INS-019 | The Sales dashboard has no month filter, and two screens are both called "Sales dashboard" | **Add Financial year + Month; rename the second one "Sales by month"** |
| C11 | INS-017 | There are no KPI targets per office or business unit | **Your call (new work)** |
| C12 | INS-018 · HF-187 | There is no recruitment KPI, although 11 hiring measures exist | **Add two: time to hire and offer acceptance rate** |
| C13 | HF-120 | Label wording on Insights screens | **Keep the current labels unless you want them changed** |

## D — Older Directory & Insights problems checked again on V51.1

**19 older items checked:**

- **Still exist (8):**
  - HF-010 (= Operations DEF-OPS-045)
  - HF-047
  - HF-092
  - HF-139
  - HF-142
  - HF-186
  - HF-187 (C12)
  - HF-193 (C2)
- **Fixed or explained (3):**
  - HF-113: role workspaces exist
  - HF-184: the refusal now names the module
  - HF-188: the unused functions are now used
- **No longer apply (2):** HF-121 and HF-128. They asked for these modules to be tested, which is now done.
- **Your decision (1):** HF-120 (C13).
- **Cannot be checked here (5):**
  - HF-048 and HF-189: need live data
  - HF-116: the card is not shown on the copy
  - HF-130 and HF-199: only partly covered

## E — Not built

E1 an un-merge for clients (C2) · E2 KPI targets per office or business unit (C11) · E3 recruitment KPIs (C12). None is a defect against V51.1.

## F — What is NOT tested yet

- **16 tests waiting for new wording.** They need a rerun after that.
- **Find duplicates screen check:** about 230 of its 366 steps hit the robot's time limit, so they are robot limits, not app results.
- **Five screen checks** could not reach forms inside collapsed panels. The same screens were covered by the scenario tests.
- **Real-phone steps:** the check-in location and the GPS prompt.
- **Live-only checks:**
  - the reconciliation screens on live data (INS-014);
  - load time at live volume (INS-027);
  - HF-048 and HF-189.

  Nothing on the live site is touched without your separate permission.

So the Directory & Insights picture is: **1 serious, 7 medium and 10 small defects; 8 older problems still present; 3 features not built; 13 questions; 16 tests waiting for new wording.**

## After you answer

A1 belongs in the "branch and access" batch I recommended in the Operations pack. A2, A3, A5 and A6 belong in the Money batch, because they are dashboard money figures. B goes in its own small batch. Each batch gets the full suite on SQLite and MariaDB, the robot tests again, a zip for the test copy, the list of changed files and the rollback steps.
