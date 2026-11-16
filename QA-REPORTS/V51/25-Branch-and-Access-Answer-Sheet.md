# Answer sheet 1 — "Branch and access" (16 Nov 2026)

The most serious problems found in the audit share one cause: **a person can see or change records outside their branch, client, site or role.** They come from 9 different packs. This sheet puts them in one place so you can answer them **in one reply**.

**The easiest answer:** *"Branch batch: all as proposed."* If you disagree with an item, add it, for example *"all as proposed, except R1 allowed to change"*.

Every item follows your permission list (`docs/02`). Nothing here gives anyone a new right. Where a role does more today than the list allows, the fix brings it back to the list.

## Branches and offices

| # | Problem today | My proposal |
| --- | --- | --- |
| B1 | A person with **no home office set** sees **every branch's** work orders (Admin A1 = Operations A1) | No office → sees nothing until an admin sets their office. Master Admin and Admin still see everything |
| B2 | A Coordinator of **another branch can change a work order's status, priority and criticality**. The change is saved before the "outside your scope" message (Operations A2) | Check the branch **before** saving anything |
| B3 | The Operations home and scheduling board show other branches' work orders when the executing office is blank. The deputations list is not limited by branch. The contracting office **cannot** open a work order it forwarded (Operations A7) | Apply the branch rule on these screens. The contracting office may open what it forwarded |
| B4 | Someone from **another branch can edit a report** by typing its edit address, although the report page refuses them. Saving also wipes the report's client and vendor (Reporting A1) | The same branch check on edit and fill as on the report page |
| B5 | "New inspection report → Start from an inspection call" lists **every branch's** work orders (Reporting A4) | Only work orders in the person's own branch |
| B6 | Candidates of **other branches** show in the Recruitment home panels, "Best matches" and the candidate pool (Recruitment A2) | The same branch limit as the candidate register |
| B7 | Marketplace **requirements carry no branch**, so anyone can award and bill any branch's requirement. Team passports are not limited by office (Marketplace A4) | Each requirement gets the poster's branch. Award, booking, billing and team passports only within the person's branch |

## Roles doing more than the permission list allows

| # | Problem today | My proposal |
| --- | --- | --- |
| R1 | The **Business Director** and the **Business Unit Head** can change recruitment records: candidates, interviews, offers and joining. The list says "View" (Recruitment A1) | **View only, as the list says.** *(Or say "R1 allowed to change", and I correct the list instead)* |
| R2 | An **Inspector can open and change another inspector's draft report**. The list says "Edit (own)" (Reporting A2) | Inspectors edit only their own reports |
| R3 | A **Branch Manager or Operation Manager can work a nonconformity**: containment, disposition, reopening. The list gives them "View · Record" (Quality A2) | Those steps go to whoever may close a nonconformity (Quality / Technical Head, admins) |
| R4 | A **Business Development Manager** can open the Operations home. The **Branch Application Manager** can allocate jobs (Operations A8) | Refuse both, as the list says |
| R5 | **Expense vouchers:** Inspectors cannot open **their own** vouchers. Finance is refused, although your decision D-07 gives Finance "View". The Asst. Manager is refused. The Business Director is offered Approve and Reopen (Operations A3) | Match the list row by row: inspectors see, edit and submit their own; Finance views; the Asst. Manager may approve; **the Director views only, with no Approve** |

## Clients and the portal

| # | Problem today | My proposal |
| --- | --- | --- |
| P1 | "Change permissions" on a client portal user **edits the wrong person**, even one belonging to another client (Directory A1) | The screen edits the person you clicked, and only within that client |
| P2 | A client person **limited to one site sees every deputation of their company** and can approve attendance for another site, and that approval feeds billing (Portal A1) | Apply their site limit to the lists and to approval |
| P3 | **Portal alerts ignore permissions**: an accounts-only person is told of reports they may not open (Portal A2) | Each person sees only alerts for what they may open |
| P4 | **A Coordinator manages every client's portal people**, not only their own office's clients. "New link" shows a password-setting link **on the staff screen** (Portal A4) | Limit the list to the Coordinator's own clients. **"New link" goes to the client person's own e-mail**; staff see only "sent" |
| P5 | Staff can raise new work from a portal request **for a client on hold** (Chains A3) | The same hold check as everywhere else: refused with the usual hold message |

## One broken screen in the same area

| # | Problem today | My proposal |
| --- | --- | --- |
| X1 | **Hold & witness points cannot be used by anyone**, not even the Master Admin: the screen is linked to a module name that does not exist (Quality A1) | Link it to the rights the screen already uses, then run its 7 blocked tests |

## What happens after your answer

1. I fix the whole batch.
2. I test it on both database types, then rerun the robot tests of every affected module.
3. I send a **test-copy (opstest) zip** with the changed-files list and the rollback steps. **India time (your T1 yes) comes in the same zip.**
4. You check it on opstest, as before.

Nothing goes to the live site until you say testing is complete and answer yes to "Shall I prepare the live update?"

**After this sheet** I will send answer sheet 2 for the money items, and then the "B ok" lists.
