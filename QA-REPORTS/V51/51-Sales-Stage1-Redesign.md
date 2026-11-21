# Sales redesign, Stage 1: Sales home, Today, Pipeline board, Sales dashboard (21 Nov 2026)

**What you authorised** (21 Nov): build the four Stage 1 screens on laptop and phone to the approved designs. Preserve every field, button, filter, action, route, permission and calculation. Sales home becomes the Sales landing page while /sales and /sales-home both keep working. Add a Sales-only phone bottom bar. Use dark teal only for the Sales navigation. Leave the app's own header alone.

**This update** is **f3a287e** (deploy-check stamp *f3a287e · 2026-11-21 03:05 UTC · 744 files*) and builds on the lead page update (cec7530). The lead page and its three fixes are unchanged.

**Rollback point:** update **9f4a530** (the lead page report). To go back, re-upload the previous zip (Lead Page Redesign, cec7530). Nothing in the database changed, so nothing has to be undone there.

---

## 1. What changed, screen by screen

**Sales home** is now the **Sales Command Centre**. It has:
- your greeting, and the "target changed" notice with *Open Targets* and *OK, seen*;
- six quick tiles;
- the Me / Everyone / person filter;
- six figure tiles;
- the daily routine beside "Where to go", which now shows count badges;
- your next actions beside Data health;
- "Set up & tools": the other tiles from the Sales menu page, with the same rules and badges;
- Who does what (folded), and the colour legend.

**Today / My work** has:
- seven figure tiles, each jumping to its list;
- "Who has what today" for managers;
- one list of deal and lead follow-ups, each row marked **Deal** or **Lead**, in the same order as before (overdue, no next step, today, this week);
- in-page search, and sorting by clicking a column title;
- an Owner column;
- tabs for Stale deals, Payments to collect and Renewals due.

On a laptop the list is a table. On a phone each row becomes a card with **Call · WhatsApp · Log**.

**Pipeline board** has:
- the Confirmed orders line;
- a **Leads / Deals** switch: Leads groups the same first 25 open leads by their own stage;
- a box per stage with count · value · win %, and a Total box;
- the cards, with a red edge for overdue or no next step and amber for due today.

On a phone you tap a stage box to see that stage's cards. There is no drag-and-drop: the stage is still changed on the record.

**Sales dashboard** has:
- the same eight figures with clearer labels. "Won value" says *deals marked won, not invoices*. Collections says *Accounts' outstanding invoices*.
- Won vs target as a column chart with the target marked. Every month is also written out underneath, so nothing needs a hover.
- Pipeline by stage as a ring, with value, win % and weighted value per stage.
- Lead sources, why we lose, collections ageing (only for people who may see receivables), the team leaderboard, and the top 10 open deals.

**Navigation on every one of the four screens:**
- **Sales section bar** (laptop and tablet). This is the only dark-teal element. It lists only the Sales screens the person may open, read from the Sales menu itself, plus "All Sales screens" (/sales).
- **Phone bottom bar:** Home · Leads · Pipeline · Actions (Today, with the due count) · Reports (Sales dashboard). Each item shows only when the person may open it.
- **Left menu:** "Sales" now opens **Sales home** for anyone who may open it. Anyone who may not (for example a coordinator) still lands on /sales. /sales still opens for everyone, and nothing redirects.
- **Not changed:** the app's own left menu style, the top bar, and every other module.

## 2. Confirmation: nothing underneath changed

| Item | Changed? |
|---|---|
| Database tables, columns, data | **No.** No migration. |
| Business logic, SQL, calculations (`lib/*.php`) | **No.** Not one line in `lib/` changed. |
| Routes, form addresses, field names | **No** |
| Permissions, roles, approvals, feature switches | **No.** Every tile and bar item uses the existing rules. |
| The app's header and other modules | **No.** The only shared-file change is the address of the left menu's "Sales" link (see above). |

**Files changed:**
- **Rewritten (look only):** `views/ops/sx_home.php`, `sx_today.php`, `sx_board.php`, `sx_dash.php`.
- **New:** `views/ops/_sxw_ui.php`, the shared Sales pieces, built on the lead page's record kit `_rec_ui.php`.
- **Styles:** `assets/css/app.css` gained a "SALES WORKSPACE" block.
- **Left menu:** `views/layout_top.php`, the address of the Sales link only.
- **Docs:** `docs/05-ui-ux-blueprint.md` now describes the Sales workspace standard.
- **Tests:**
  - new: `tests/test_sales_stage1.php`, `tests/_stage1_worker.php`, and copies of the old pages in `tests/fixtures/pre_stage1/`;
  - updated: one check in `tests/test_sales_simple.php` that looked for the old heading "Deal follow-ups" now looks for "Deal and lead follow-ups".

## 3. Feature preservation matrix

The same table as in the approved design document. Every item was then checked automatically: the old page and the new one were drawn for the same person, on the same data, and compared (section 4).

| Screen | Before | Now |
|---|---|---|
| Sales home | Company · Sales title, greeting, date | Header and greeting card |
| | Target changed (month, old → new, by whom, date, *OK, seen*, *Open Targets*) | Same form at the top, same place |
| | 6 quick buttons | 6 quick tiles, same addresses |
| | Person filter (managers) | Me · Everyone + person list, same addresses |
| | 6 figures with their links (Overdue payments only if allowed; "Today switched off" fallback) | 6 figure tiles, same links and rules |
| | Daily routine, 4 steps | Routine card, same links |
| | 11 "Where to go" tiles (hidden when switched off) | "Where to go", now with count badges |
| | Data health, 5 checks + Fix | Data health card, same counts and links |
| | Who does what | Folded panel |
| | Colour legend | One line at the bottom |
| | *(Sales menu page)* next actions, all Sales tiles | "Your next actions" (ranked, as My Work) + "Set up & tools" (same rules, badges) |
| Today | 7 figures jumping to their lists (Overdue ₹ only if allowed) | 7 tiles, same jumps (#deals, #none, #stale, #pay, #renew) |
| | Who has what today (managers) | Same table |
| | Deal follow-ups (when, deal, customer, stage, value, next step, idle, WhatsApp, Call, Log) | One list, marked Deal |
| | Lead follow-ups (when, company, next step, est. value, WhatsApp, Call, Log) | Same list, marked Lead |
| | Stale deals (first 15) · Payments to collect (first 15, message without receivables) · Renewals due | Three tabs, same rows and messages |
| | *(new view only)* | Owner column; search; sort |
| Pipeline board | Pipeline chips (more than one pipeline) | Pipeline choice, same addresses |
| | Person filter | Same |
| | Confirmed orders (count, value before GST, See them) | Same line |
| | Open leads column (first 25, + more) | Leads tab, by stage, + more |
| | Each OPEN stage (count · value · win %) with cards | Stage boxes + columns; Total box |
| | Won / lost on the Deals screen | Unchanged (a line says so) |
| Dashboard | Period chips (FY, quarter, month, 90 days), person filter, dates | Same |
| | 8 figures with links | 8 tiles, clearer labels |
| | Pipeline by stage (deals, value, win %, deal's own %, weighted) | Ring + legend, same numbers |
| | Won vs target by month | Column chart + the same numbers written out |
| | Lead sources · Why we lose · Collections ageing (if allowed) · Team leaderboard · Top 10 | Same panels |

## 4. Old against new: the same figures, links and access, for every role

**How it was checked:**
- The old pages (update 9f4a530) and the new ones were run side by side on **the same copy of the test data**.
- Each person signed in, and each page was opened in its usual variants: Me and Everyone, and on the dashboard this year, this quarter and this month.
- For every page the check compared:
  - each figure;
  - every link and every form (address and fields);
  - every rupee amount;
  - whether the page was refused;
  - whether payments were shown.

| Your role name | Real role used | Pages opened | Figures compared | Differences |
|---|---|---|---|---|
| Sales Executive | Marketing Executive (`test.mktexec`) | 9 | 46 | **0** |
| Sales Executive (BDM) | Business Development Manager (`test.bdm`) | 9 | 50 | **0** |
| Sales Manager | Marketing Manager (`test.mktmgr`) | 9 | 50 | **0** |
| Branch Manager | Branch Manager (`test.branchmgr`) | 9 | 50 | **0** |
| Business Head | Business Director (`test.director`) | 9 | 50 | **0** |
| Business Unit Head | SBU Head (`test.sbuhead`) | 9 | 50 | **0** |
| Finance | Finance (`test.finance`) | 9 | 50 | **0** |
| Master admin | Admin | 9 | 50 | **0** |
| No Sales access | Coordinator (`test.coord`) | refused 9 of 9, **as before** | — | **0** |

**Totals:** 81 page comparisons, **0 differences**. No link, form or amount of an old page is missing from its new page. Payments are shown to exactly the same people as before.

The same comparison also runs inside the automatic test suite (`test_sales_stage1.php`) with its own users and data, including a check with "Today" switched off.

## 5. Screen sizes

All four pages were tested as the Business Director and the Marketing Executive, at 8 sizes each (64 checks):

| Size | Sideways scrolling | Cut-off text | Controls under 32 px |
|---|---|---|---|
| 1920×1080, 1440×900, 1366×768 (laptop) | none | none | none |
| 1024×768, 768×1024 (tablet) | none | none | none |
| 430×932, 390×844, 360×800 (phone) | none | none | none |

On the old pages, Today and the Pipeline board had buttons under 32 px at every one of the 8 sizes.

Two problems this check caught were fixed before delivery:
- Today's table was too wide on a tablet. It now turns into cards up to tablet width.
- The "Fix ›" links were too small.

## 6. Tests

| Check | Result |
|---|---|
| New test `test_sales_stage1` (old vs new for 8 people × 8 pages, payments visibility, "Today" switched off, left-menu link, section bar, bottom bar, /sales still opens) | **428 / 428** |
| Sales 1,024 · Leads 383 · Deals 46 · Quotations 265 · CRM 31 · Pipelines 256 · Approvals 627 · Stage gates 1,297 · Branch reach 581 · Feature switches 53 · Areas 45 · Navigation 109 · Dashboard 51 · UAT guide 9 · Boards 153 · Glance 29 · Mobile 50 · UI 1,198 · Layout 44 · Menu 38 · CSRF 38 · Escaping 6 · Views 428 · Customers 249 · Renewals 15 · Orders 14 · Contracts 403 · Bounce 49 · Chain 208 | **all passed** |
| Terminology (237 checks) | 236 passed, **1 failed: it fails the same way on the old code** (a count of terms: 33 instead of 32). Not caused by this change; listed below. |
| Robot, screen by screen | **104 cases: none worse than before.** Totals the same as the previous run (45 passed, 20 failed, 23 incomplete, 10 need you, 1 out of date). Three cases first failed only because the robot clicked the old address of the left-menu Sales link: SAL-120, SAL-174, SAL-180. Their scripts now follow the new route (left menu Sales → Sales home → All Sales screens), with backups kept. Each then returns its previous result: SAL-120 and SAL-174 pass; SAL-180 fails only steps 8–9, as before. |

## 7. Decisions taken and things left out

1. **"Your next actions" on Sales home** uses the same ranked list as My Work. The Sales menu page also shows a *workspace pulse* (needs attention, outstanding, platform health). Its code warns that the pulse can take a very long time on a large company.
   - **Measured:** on the test copy, the pulse adds 0.03–0.04 s to the first load. That is small here, but the warning is about large companies.
   - **Decision:** the pulse is **not** drawn on Sales home. Instead there are links to the **Command Centre** and the **Sales menu page**, and both still show it.
   - Your instruction allowed this if the page would get slower. If you would rather have the pulse on Sales home too, it is a one-line change.
2. **The Sales section bar** sits inside the Sales pages and is not built into the app's own left menu. Changing the left menu would have touched every module.
3. **"View all (n)" under each deal column** was not added. The board already shows every open deal in each column, as before, and no deals list filtered by stage exists to link to.
4. **The pipeline-choice list** only appears when the company has more than one deal pipeline, as before.

## 8. Outstanding issues (not changed — they need your decision)

- **Defect register:** the 18 existing problems from the inventory are unchanged.
- **Terminology test:** the count of terms (33 found, 32 expected) also fails on the old code.
- **"Next actions" helper on the Sales menu page:** the shared part that draws it ignores its own "actions only" switch (`_glance_strip.php` always calls the full pulse). Noted, not changed.

## 9. Your check list on opstest (14 checks)

1. Click **Sales** in the left menu. **Sales home** (Sales Command Centre) opens. The breadcrumb's **Sales** still opens the Sales menu page.
2. Sign in as a coordinator with no Sales access. **Sales** in the left menu opens the Sales menu page as before.
3. On Sales home, compare the six figures with what you saw yesterday. They are the same numbers.
4. Tap each quick tile (New lead, Log activity, New deal, New quote, Orders won, Print a quote). Each opens the same screen as before.
5. As a manager, switch **Me / Everyone** and pick a person. The figures change as before.
6. Open **Set up & tools**. Templates, Set up Sales, Pre-order checklist, Project costing, Approvals, Pipelines, Advertising return: you see only the ones you could see on the Sales menu page.
7. **Today:** tap **Overdue**. The Follow-ups list opens. Rows are marked Deal / Lead, overdue first.
8. Type a company name in the search box. The list narrows. Click **Value**: the list sorts.
9. Press **Log** on a row. The log form opens and returns you to Today.
10. Open the **Payments to collect** tab: as Finance you see invoices; as a sales executive you see "Payments are shown to people who may see receivables."
11. **Pipeline board:** switch **Leads / Deals**. The counts and values in the stage boxes match the columns, and a card opens its record.
12. **Sales dashboard:** pick *This month*. The eight figures match the old dashboard for the same month. "Won value" says *not invoices*.
13. On a **phone**, open each of the four screens. The bottom bar shows Home · Leads · Pipeline · Actions · Reports, and nothing is hidden behind it. On the board, tap a stage box to see its cards.
14. On a **phone**, nothing scrolls sideways and every button is easy to tap.
