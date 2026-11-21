# 61 — Whose records a person sees (Q10), the Sales manager's team, and the last Sales items

**Date:** 21 Nov 2026 · **For:** the owner · **Built on:** a83effc (Stages 1–6 merged)

## What you decided

- **Q10:** *"Keep this"*. For each role, and for one person if needed, choose whose records they see:

  | Option | Meaning |
  |---|---|
  | **Own** | only the records they own |
  | **Team** | theirs and those of everyone who reports to them |
  | **Branch** | everything in their branches |
  | **All** | the whole company |

- **Sales manager:** *"Sales manager can see only their own list on Sales home and Today is ok. But they can also see details of his reportee."*

## What was built

### 1. "Whose records they see" on *Who can do what*

- **On a role:** a choice of **Own / Team / Branch**.
- **On one person:** **As the role / Own / Team / Branch / All**. "All" also sets the person's branches to *All branches*. Only someone who manages users company-wide can set it, the same rule as before.
- **Where it applies:**
  - leads, deals, inquiries, quotations, orders & payments;
  - Sales home, Today, the Pipeline board, the Sales dashboard, the funnel report, search and Advertising return;
  - opening a single record by its address.
- **Example:** Rakesh, a Marketing Manager in Mumbai, set to **Team**. Asha reports to him, and Bala reports to Asha.

  | Rakesh is set to | He sees in the Leads register |
  |---|---|
  | Branch (as today) | every Mumbai lead (Asha's, Bala's, Chetan's, his own, and leads with no owner) |
  | Team | his own, Asha's and Bala's |
  | Own | only his own |

  If he types the address of Chetan's lead, he is told it belongs to someone outside the records he sees.
- **Own and Team only narrow what a person sees.** Someone in Mumbai set to Team never sees an Ahmedabad record.
- **On day one nothing changes.** Every role starts on **Branch**, which is exactly what every register did before. The Master Admin is never limited.
- **Every save goes into the history** that cannot be edited afterwards.

### 2. The Sales manager's team

- On Sales home, Today, the board and the Sales dashboard, a manager without *"See other salespeople's lists"* still opens on **their own list**.
- They can now pick **anyone who reports to them**, however many levels down, from a **My team** list.
- There is still no "Everyone", and they cannot pick anyone outside their team.
- These are records they could already open in their branch's registers. Nothing new becomes visible to them.

### 3. Renewal window on Sales settings (DEF-10)

- A **Renewals** box: "Show contracts ending within [60] days".
- It is used by Renewals, Today and Sales home.
- 60 is marked as an example until you change it.

### 4. The defect register and the pending list are up to date

- DEF-13, 17, 20, 22 and test-book finding F-02 are marked **fixed** (Stage 6).
- Q10 is marked **built**.
- The robot scripts now expect:
  - the renamed "Sales funnel report" (INS-001, 002, 004);
  - the Branch Manager opening leads, deals and pipelines read-only (SAL-134).

  The old scripts are kept as `*.bak_e`.

## What did NOT change

- No permission was added and no right moved. Q10 is a setting, stored apart from the permission ticks, so "Select everything" or the Master Admin's every-right can never switch someone to "own records only".
- No database table or column was added.
- Accounts' "awaiting a contract number" list is a work queue, not a salesperson's list. It keeps branch rules only.

## Proof

- **`tests/test_q10_scope.php`: 37 of 37 checks pass.**
  - **Day one:** for 51 test people the lead, deal and quotation rules are identical to before Q10. They cover every role, branch-limited and all-branch, unit-limited, management and the master.
  - **Every register:** Own, Team and Branch were checked on leads, deals, quotations, inquiries, orders, Sales home and Today.
  - **Opening a record by its address:** checked for leads, deals, quotations and inquiries.
  - **Person over role:** a person's own choice overrides their role's, and "All" works.
  - **Team picker:** only the manager's own team, with no "Everyone".
  - **Saving:** saving from the role page and the person page stores the choice and leaves the person's switches untouched. The history entry is written.
- **Related test files:** 87 files covering Sales, leads, deals, inquiries, quotations, permissions, branches and stages 1–6.

  | Result | Detail |
  |---|---|
  | 7,833 checks pass | across all 87 files |
  | 1 check updated, on purpose | The permission-screen test said "exactly one row waits for your decision (question 10)". Question 10 is now built, so it now expects none. It passes 27/27. |
  | 1 check fails, not caused by this batch | `test_m10_master_entitlement` asks that a new gate from Stage 5 (`sx_contract_money_can`) be classified. It fails the same way on the code before this batch (a83effc). Noted for the closing check. |
- **Screen sizes:** Sales settings, the role page, the person page, Sales home and Today, at all 8 sizes. That is 40 checks: nothing scrolls sideways, there are no error messages, and no control is under 32 px.

## Your opstest checks (8)

1. Open **Admin → Who can do what → Roles → Marketing Manager**. "Whose records they see" shows **Branch**.
2. Open the leads list as a Marketing Manager. It is the same as yesterday.
3. Set the Marketing Manager role to **Team** and save. As that manager, the leads list now shows only their own leads and those of people who report to them.
4. As that manager, type the address of a lead owned by someone outside their team. A message explains it is outside the records they see.
5. Put the role back to **Branch**. Everything returns.
6. As a Marketing Manager with reportees, open **Sales home**. It shows your own list, and a **My team** list offers the people who report to you. Pick one: you see that person's work. There is no "Everyone".
7. On **People**, set one person to **Own**, then back to **As the role**. Each change appears in the history.
8. Open **Sales settings → Renewals**. It shows 60 days, marked as an example. Change it to 45, then check that Renewals looks 45 days ahead.

## Questions for later (on the pending list, not built)

- Should records with **no owner** (an unassigned lead or inquiry) stay visible to people set to Own or Team? Today they are hidden from them, and visible to everyone on Branch.
- The person form (*Edit user*) does not show the choice. It is on *Who can do what* only.
