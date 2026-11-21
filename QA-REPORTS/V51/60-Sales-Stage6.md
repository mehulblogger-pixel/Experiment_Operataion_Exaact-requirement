# Sales redesign — Stage 6: settings, set-up, pipelines, stage gates, Advertising return, funnel report

**Date:** 21 Nov 2026
**Approved by the owner:** the Stages 2–6 design pack, "All as recommended", 21 Nov
**Status:** built and tested on a working branch, `claude/stage6-ui-0gqe8n`. It will be joined to the main test branch together with Stage 5, then sent to opstest as one update. Nothing is live.

## In one paragraph

The last Sales screens now look like the rest of Sales, with nothing lost: Sales settings, Set up Sales, Sales approvals, Pipelines & funnels, the approval rules for deal stages, Advertising return and the old "Sales dashboard". Every field, button, link and figure is still there; a test proves it for 13 people. Five problems from the register are fixed in this stage:

1. Sales settings no longer offers links that refuse you (DEF-13).
2. Only one screen is now called "Sales dashboard" (DEF-17).
3. The Dashboard no longer works out the slow management summary it was told not to (DEF-20).
4. Advertising return now follows a deal won through a quotation and contract (DEF-22).
5. Sales home only shows tiles you can open (test-book finding F-02).

No permission, status or table was added. Nobody can do anything new.

## 1. What changed, screen by screen

| Screen | What you will notice | Example |
|---|---|---|
| **Sales settings** `/sales-settings` | The Sales bar at the top and two columns of cards: follow-up days, stages and win %, lists, weekly off (tap the days), stale deals, who gets new leads. One Save bar at the bottom. | A Marketing Manager who may change Sales settings but is not an administrator no longer sees "Lead sources ›", which used to refuse them. Instead it says: "Lead sources — kept by an administrator (Admin → Lists); ask them to change it." |
| **Set up Sales** `/sales-setup` | The Sales bar, and a row of jump links to each part of the page (Quotations, Costing, Contract opening, Deal stages, Leads). Nothing is hidden behind tabs. The level editor now says that each level is saved by its own button. | With no approval levels the page now says "No levels yet — so a quotation needs no approval", your rule 19k. It used to say only "Add the first one". |
| **Sales approvals** `/sales-approvals` | Same tabs and rules, inside the Sales look. On a phone all tabs show (wrapped) instead of scrolling out of sight. | — |
| **Pipelines & funnels** `/pipelines`, `/pipeline?id=` | The Sales look. "In use" meant three different things; now each says what it counts: "Records on it", "In use (offered for new work)" and "Active". The edit screen has "All pipelines & funnels" to go back. On a phone each stage is a small card instead of an eight-column table. | — |
| **Approval rules for deal stages** `/stage-gates` | The Approver column shows the role's name, "Branch Manager", not the code "BRANCH_MANAGER". A rule that has already held requests now offers **Switch off** instead of **Remove**, because that is what the system already did with it. | "Large wins need the head of sales" (used once) → Switch off; an unused rule → Remove. |
| **Advertising return** `/ads-roi` | The Sales look. DEF-22: a deal won through its quotation and contract is now followed to the work order and its invoice. | Sunrise Valves: quotation Q-AMD-2026-0950 → contract SV/2026/031 → work order WO-2026-0444 → invoice INV-2026-0311 ₹3,54,000. It used to show as "Won, but no order was ever raised" with ₹0 invoiced; now it shows ₹3,54,000 invoiced. |
| **Sales funnel report** `/crm-dashboard` (was "Sales dashboard") | The name, the Insights tile and the advisor link now say "Sales funnel report". "Pipeline board" opens the Sales pipeline board. The period buttons say they are a rolling window ("Window · last 30 days…"). | Only `/sales-dashboard` is called Sales dashboard now. |
| **Sales home** `/sales-home` | "Where to go" shows only screens you can open (F-02). The Sales bar and the phone bottom bar follow the same rule. | Branch Manager: the Leads tile and the "Leads" link in the Sales bar are gone, because both refused them. When you open leads to Branch Managers (your yes of 21 Nov) they come back by themselves. |
| **Dashboard / Operations home** | DEF-20: the "Your next actions" strip no longer works out the management summary (Needs attention / Outstanding / Platform) on these two pages, as was always intended. It is slow on a large company. The actions listed are exactly the same. | The summary is still on the area home pages and the Command Centre. |

## 2. Nothing underneath changed

- No new permission, status or database table.
- Settings, pipelines, rules and approvals save through exactly the same handlers, form addresses and field names as before.
- One small backend change, approved as item 20 (DEF-22): `roi_calls_via_quote()` in `lib/adsroi.php` reads the quotation and contract links a work order already has. It writes nothing.
- DEF-01 is unchanged: invoiced and collected money still needs the receivables right, and branch rules still apply.

## 3. Feature preservation matrix (inventory: `F-G-commercial-config.md`)

| Inventory item | Where it is now |
|---|---|
| Outcomes table (name, working days 0–365, example badge, empty to remove, keep at least one) | "What happened → next follow-up" card, same fields `oc_key/oc_label/oc_days` |
| Weekly off `off[]` (none = every day) | "Weekly off" card, as day pills |
| Stale after (1–365, default 15) | "Stale deals" card |
| New leads go to `own[office]` (only people of that office; warning when dropped) | "New leads go to" card |
| Stages and win % (default pipeline), link to change them (when Pipelines is on) | "Stages and win %" card; the link only for someone who may open Pipelines |
| Lead sources, Reasons for losing, Buying roles, Public holidays, Who approves quotes | "Lists kept in their own place" (links when you may open them, otherwise named with who keeps them, DEF-13) |
| Save + audit chain | Save bar |
| Set up Sales: every question, the level editor, cover link, checklist questions, facts, contract signers, deal-stage facts, lead facts, Save these answers, Back to Sales | Same sections, in the same order, with jump links |
| Sales approvals: tabs, the rule in sentences, Save this rule, level forms | Unchanged inside the Sales look |
| Pipelines: list per kind, stages count, records, default / Make default, active/retired, Edit stages; Create one (name, kind, start from, default) | Same; "In use" renamed "Records on it" |
| Pipeline edit: problems banner, name, in use, stage rows (order, name, kind, probability, days, active, count, remove), Save the funnel, Add a stage, other pipelines | Same; back link; phone cards |
| Stage gates: list, explaining sentence, band, guards, approver, used, Edit, Remove/switch off, precedence note, empty state, the rule form | Same; approver role in words; "Switch off" for used rules |
| Advertising return: header, CSV (by right), date filter, empty states, tiles, summary, table, breaks, "How to read this" | Same; one more line in "How to read this" (DEF-22) |
| Funnel report: windows, pipeline picker, Funnel CSV, 10 cards, funnel, stopped moving, why we lose, who is selling, effort, industry link | Same; renamed; board link; window labelled |

## 4. Tests

- **New test `tests/test_sales_stage6.php`: 1,146 checks, 0 failures.**
  - It draws every Stage 6 screen as the old page and as the new page, for 13 people, through the real program: Business Director, Master Admin, Admin, Business Unit Head, Branch Manager (Mumbai), Finance (Mumbai), Finance (all branches), Coordinator, BDM (Mumbai), KAM (Mumbai), Marketing Manager (all branches), Marketing Executive (Mumbai), and a non-administrator who may change Sales settings.
  - It covers 12 pages: settings, set-up, two approval tabs, pipelines, one pipeline, stage gates, one rule, Advertising return, two funnel windows, and Sales home.
  - For each one it checks that every figure, amount, link, form and button is the same, and that refusals are unchanged. The only allowed differences are the decided changes, each proved: a link only disappears when its screen really refuses that person.
  - It also checks DEF-13, DEF-17, DEF-20 (the summary is not drawn when not wanted; the actions are identical to My Work) and DEF-22. The DEF-22 check uses a quotation → contract → work order → invoice chain, a won deal with neither (still reported), the contract number alone, and a person without the receivables right (no money read).
  - It checks F-02: every Sales home tile shown opens, for each of the 13 people.
- **Related test files:** 142 files, 11,956 checks pass. 4 fail, and each fails identically on the code before this stage (bd3b6d6): `test_m2_org_structure` ×1 (outside Sales) and `test_m8_public_background_entitlement` ×3 (DEF-24).
  - Two older tests were updated for your decisions. `test_sales_stage1` now allows a Sales home tile to go when its screen refuses that person at the door (F-02). `test_simplify_dashboards` now looks for "Sales funnel report" (DEF-17).
- **Screen sizes:** 9 pages × 8 widths (1920, 1440, 1366, 1024, 768, 430, 390, 360) = 72 browser checks.
  - No sideways scrolling, nothing cut off, no program message.
  - The only buttons under 32 px are in the administrators' "Finish setting up" panel, which is not a Sales screen.
  - Two problems that existed before were fixed. At 768–1024 px the funnel report pushed the page 51–61 px sideways. At 390 px its pipeline picker and the Sales approvals tabs ran off the screen.
- **Robot:** not run on this branch. It runs on the merged branch together with Stage 5.

## 5. Things to know

1. **The robot needs three cases updated for the new name** (your DEF-17 decision): INS-001, INS-002 and INS-004 look for "Sales dashboard" under Insights and as the funnel page's heading. They should look for "Sales funnel report". "Pipeline board" keeps its wording.
2. **The renewal window (DEF-10)** is drawn on the design's Sales settings card, but it is built in Stage 5 with the Renewals screen. When the branches are joined it goes into the "Stale deals" card.
3. **Set up Sales keeps one long page with jump links, not tabs.** Hiding parts behind tabs would hide questions the robot and people look for. Every part is one tap away.
4. **Stage gates are not added to the Sales tiles.** They stay reachable from Pipelines and from Set up Sales ("Deal stages"). Adding a tile would touch the Sales menu file the Stage 5 work is changing.
5. **"Received" on Advertising return** (DEF-07: payments + TDS, credit notes separately) is built in Stage 5.

## 6. Your check list on opstest (10 checks)

1. As the administrator, open **Sales → Sales settings**. The Sales bar is at the top. Change "Asked us to call back later" from 7 to 6 days, press **Save Sales settings**, and see "Sales settings saved". Put it back.
2. On the same page, tick **Sat** under Weekly off and save. Untick it and save again.
3. As a Marketing Manager who has been given "Change Sales settings" but is not an administrator, open Sales settings. "Lead sources" etc. are named with "kept by an administrator", and there is no link that refuses you.
4. Open **Set up Sales**. Tap "Contract opening" in the row of links; the page jumps there. Change one answer and press **Save these answers**.
5. Open **Pipelines & funnels → Edit stages** on a funnel. Use "All pipelines & funnels" to go back. On a phone each stage is a card.
6. Open **Approval rules for deal stages**. The approver shows as "Branch Manager", not a code. A rule that has been used says **Switch off**.
7. Open **Insights**. The tile reads **Sales funnel report**. Open it and press **Pipeline board**; the Sales pipeline board opens.
8. Open **Advertising return** as the Business Director. A deal won through a quotation and contract shows its invoice, not "no order".
9. Sign in as a Branch Manager and open **Sales home**. Every tile under "Where to go" opens.
10. Open the main **Dashboard**. "Your next actions" shows as before, without the Needs attention / Outstanding / Platform line above it.

## 7. Related test files

142 test files cover these screens and the pieces they share: Sales Stages 1–4, the defect fixes, permissions, the "Who can do what" screen, stage gates, pipelines, Set up Sales, approvals, areas and navigation, dashboards and the advisor.

- 11,956 checks pass.
- 4 fail, all also failing before this stage (listed in section 4).
- The new Stage 6 test: 1,146 out of 1,146.
