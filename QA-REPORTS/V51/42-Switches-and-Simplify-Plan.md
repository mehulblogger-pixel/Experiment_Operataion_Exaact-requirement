# Switches first, then simplify: the plan (18 Nov 2026)

You said: *"Let's move with your recommendation, but we must have a switch to make the features on and off. First let me know what you will do, then start changes. It shall be easy to get back all the things as it is today."*

## The promise

1. **Nothing is deleted** in any step of this plan. A switched-off feature keeps all its records, and switching it on brings it back exactly as it was.
2. **One button brings back today's application:** **"Everything on (as on 18 Nov 2026)"**.
3. **Installing the update changes nothing you see.** Your two opstest companies stay exactly as they are today until **you** press a switch.
4. **Every switch change is recorded in History:** who changed it and when.
5. **Code is never lost.** It stays in the program and in GitHub. Deleting code (your S2) comes much later, in its own step, only with your OK.

## What exists today vs what is needed (Reuse Gate)

| Needed | Exists today | Gap | What I will do |
| --- | --- | --- | --- |
| Switch whole modules on/off | **Admin → Modules & features** (Sales, Operations, Money, Inspection reporting, People & hiring, Marketplace); data kept when off | None | Reuse as it is |
| Switch single features on/off | About 10 switches **scattered** over different screens (pre-order checklist, customer satisfaction, client portal, careers page, marketplace …) | No single place; most features have no switch at all | Add a **"Features" section on the same Modules & features screen**. The existing switches are shown there (the same settings, not copies), plus new switches for the features that have none |
| Get back to today | Nothing | A one-click return | Two buttons: **"Everything on (as today)"** and **"Simple start (recommended)"** |
| Who may switch | Modules & features: the Master Admin (or "manage settings") | None | Same people. **No new permission** |

## The feature switches (first list; you may change it)

| Module | Feature with a switch | When OFF |
| --- | --- | --- |
| Sales | Project costing (costing sheet, library, templates) | No Costing tab on quotes and no menu item; old costings stay stored |
| Sales | Stage gates · Pipelines & funnels setup · Advertising return · Pre-order checklist | Menu item and buttons hidden |
| Sales | Quote approval chain | A quote is sent without approval (only if you choose so) |
| Sales | Milestone billing on contracts | Plain billing only |
| Money | Tally / books link · Business Unit P&L · Cost reconciliation | Hidden; their figures stay |
| Home | Owner home · What to fix · Where the flow is broken | One Home page only |
| Quality | Customer satisfaction · Items & samples · Hold & witness points · Evidence review | Hidden |
| Operations | Client portal · Careers page | As today's switches |

**Example.** You switch off **Project costing**:
- The Sales menu no longer shows "Project costing".
- The quote screen has no Costing tab.
- Your old costings stay in the database, untouched.

Switch it on next month and the menu, the tab and all your old costings are back, exactly as before.

## The order of work (each step: picture first → your OK → build → full tests + robot → opstest zip → your short checks)

| Step | What | Your decision |
| --- | --- | --- |
| **A** | **Switches**: the Features section, "Everything on" and "Simple start" | This plan |
| **B** | **Simple menu**: 16 items become 8, with its own switch "Simple menu" (off = today's menu) | D2 |
| **C** | **Sales rebuilt on your Excel's pattern** (Today, Leads, Deals, Activities, Quotes with GST by state, Orders & payments, Renewals, Targets, Dashboard, one Settings page). It reuses today's records (leads, opportunities, quotes, contracts). Switch **"Classic Sales screens"**: on = today's screens | S1 |
| **D** | "Who approves what" on one screen · 7 starting roles for **new** companies · then Reports | D3, D4 |
| **E** | **Dead code list**: code nothing uses even today, proved by the tests. Shown to you; deleted only with your OK | S2 |

Time: Step A about 2 days, B about 2 days, C about 1 week (pictures first). This fits the roadmap, because Recruitment and the Marketplace are off at launch.

**One-line answer:** *"Plan 42 OK, start step A"*. I will then send the picture of the Features section before writing any code.
