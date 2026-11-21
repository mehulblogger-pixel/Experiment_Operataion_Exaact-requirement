# Report 57 — Sales redesign Stage 3: Enquiries and Deals

**Date:** 21 Nov 2026 · **Built on:** 8d60c50 (Stage 2) · **Approved by the owner:** the Stages 2–6 design pack ("All as recommended", 21 Nov 2026)

## In one paragraph

The six enquiry and deal screens now look and work like the Stage 1 and Stage 2 screens: the Sales bar on a laptop, the bottom bar on a phone, figure tiles, and record pages built like the lead page. Nothing was taken away: every figure reads the same as before, every link, button and form field is still there (or, where the approved design moved it, on the page it moved to), and nobody can open anything they could not open before — except one new, read-only page that you asked for (DEF-14). Three defects are fixed (DEF-14, the deal half of DEF-15, DEF-16) and five approved backend changes are in.

## What changed, screen by screen

| Screen | Before | Now |
|---|---|---|
| **Inquiries register** `/inquiries` | A hand-built table: every row of the year loaded, no export, no column choice, raw dates, an Edit button shown even to people who could not work the inquiry. | On the shared register (like Leads and Deals): **pages of 50, sortable columns, Columns, CSV**. Tiles for Inquiries · Quoted · Won · Lost·Dropped (the same figures, same links). The "waiting for a quotation" banner with **Show only these**. Each row: **Open**, the Quote button by status (unchanged rule), Delete for administrators. Dates in words. |
| **Inquiry page** `/inquiry?id=` — **new** (DEF-14) | Did not exist. The only way to see an inquiry was its edit form, which needs the edit right just to open. | A read-only record page: client, number, subject, status, contact; Received · Assigned to · Business unit; tabs **Overview** (where it stands, its quotations in words, Drop / Reopen), **Details**, **Quotes**, **History**. Edit, Quote and Project costing appear only for the people allowed. |
| **Inquiry form** `/inquiry-new`, `/inquiry-edit` | One long form; "Where it stands", Drop and the history under it; saving went back to the list. | Two numbered sections; the same fields, checks and duplicate guard; **saving returns to the inquiry page**. "Where it stands", Drop / Reopen and the history moved to the inquiry page. |
| **Deals register** `/opportunities` | Board scrolled sideways on a phone; no pipeline picker (the server supported one); a year picker on the board that did nothing; won/lost counts hidden. | Board **stacks on a phone**; **Pipeline** picker; the year and a **Status** filter on the list (where they apply); six tiles incl. **Won · Lost (12 months)**; "Why deals were lost" kept. |
| **Deal page** `/opportunity?id=` | Long page; the log form and timeline outside the tabs; quotation statuses as codes (SENT); the customer opened the finance ledger. | Record page like the lead page: header, three boxes (worth, likely %, close), the **stage path**, tabs **Overview · Quotes · Actions · History**. Log form and timeline inside History. Statuses in words. The customer opens its **customer page**. "Move it" now has a **note for the approver** box (the server always took one). |
| **New deal** `/opportunity-new` | Short form, weaker checks than the lead form, wrong help text, the company's extra fields after the buttons. | Three numbered sections; **business unit**; value **0 or more** and **real dates** checked; help text corrected; extra fields above the buttons; the pipeline's stages shown beside the form. |

## Backend changes (all approved in the design pack)

| # | Change | Who it affects |
|---|---|---|
| 3 | Inquiries register on the shared register: paging, sorting, column chooser, CSV | Everyone who can open the register. The CSV holds exactly the rows the person can see (their branches and business units). |
| 4 | Read-only inquiry route `/inquiry?id=` (DEF-14) | Opens with the **Inquiries view** right and the register's branch / business-unit scope. |
| 5 | After saving an inquiry, back to the inquiry page | Create, edit, drop and reopen all land on the inquiry page. |
| 6 | Deal form checks: value 0 or more, real dates | New deal and "Edit the details" — the lead form's own rule. |
| 7 | Deal business unit; typed win % ignored (DEF-15) | Units from the person's own scope; a unit not sent is left as it is; the win % follows the stage. |
| Q6 | Paging and sorting on the Inquiries register | As item 3. |

Also: **DEF-16** — the deal form now says a name-only deal *can* be quoted (true since 13 Nov), and the stage-gates page no longer talks about dragging. Links that open an inquiry from the chain strip, the activity list, search, the advisor and the costing page now open the inquiry page.

**No new table, column, status or permission.** Documented in `docs/02` item 19v and `docs/05`.

## The owner's decisions received during Stage 3

| Decision | What happens |
|---|---|
| **Q10** "Keep this" — whose records a person sees (Own / Team / Branch / All, per role with a per-person override; day one = today) | Recorded. Built with Stage 6 and the "Who can do what" screen. |
| **(1) "Finance may open the deal"** | **Not built yet — one question first.** Opening the Deals register and deal page on "View deals" alone would **also** let the **Branch Manager** and the **Business Unit Head** open deals: since the permission foundation they hold "View deals" (from their Quotations / Enquiries view) and already see deals on Sales home, Today, the board and search — but are refused when they click one. You also said no other role should gain anything. Please choose: **(a)** all three may open deals (recommended — BM and BU Head only gain the pages of deals they already see in lists), or **(b)** Finance only (needs a narrower rule than one switch). Nothing changed until you answer. |
| **(2)** The customer ledger follows branches; central finance (all branches) sees every customer | Recorded. Stage 5. |
| **(3)** DEF-07 confirmed by Finance: Received = payments + TDS **only where TDS was actually deducted on that receipt**; credit notes separate | Recorded. Stage 5. |

## Preservation — every item of today's screens, and where it is now

### Inquiries register
| Today | Now |
|---|---|
| Title (terminology), "N shown", + New inquiry | Page head, note line, **+ New inquiry** |
| "Waiting for a quotation" banner (open past the SLA, default 7 days) → What to fix | Banner + **Show only these** |
| Cards Enquiries / Quoted / Won / Lost·Dropped (+ hit rate), each a link keeping search and year | Tiles, same figures, same links |
| Filters: search, status, year, Search, Reset; the "group" explanation | Status and Year selects, the register's search box, Reset; the same explanation |
| Columns: number (+ from-lead link), Received, Client, Subject, Status (+ ⏳ days, drop reason), Assigned to (user / lead owner / Nobody yet), Branch ("All branches"), BU, Source | All kept; Client and Subject together, Branch and BU together; dates in words; drop reason shown under the status |
| Quote button by status (Quote / Open Q + another / Quote again / none) | Same rule, in the row's actions |
| Delete (management / master, not Quoted / Won / Lost, confirm) | Same rule, ✕ in the row (its own form, as before) |
| Edit | **Moved** to the inquiry page (shown only to people who may work it) |
| Status sync on open | Unchanged (server) |

### Inquiry form → inquiry page
| Today (edit form) | Now |
|---|---|
| form_once, duplicate check (15 min) + "Open INQ-…" + "Save it as a separate inquiry" | Unchanged; the open link now opens the inquiry page |
| client_id (+ Add new; a blocked client refused), client_name (similar-name warning) | Section 1 |
| received_date (not in the future; browser warning), contact name / e-mail / mobile | Section 1 |
| sbu, source, assigned_to, subject, service_requirement, notes; status read-only | Section 2 |
| "Came from lead" | Banner, now a link |
| ＋ Project costing; ← back | Header buttons |
| Create / Save inquiry, Cancel; 🔒 line when the person cannot work it | Action bar (same words) |
| "Where it stands", quotes with status, Drop (reason + note), Reopen | **Moved** to the inquiry page, Overview tab — same forms, same rule |
| History (activity timeline) | **Moved** to the inquiry page, History tab |

### Deals register
| Today | Now |
|---|---|
| + New deal; CSV (download right) | Header button; CSV on the board's control row and in the list |
| Five cards: Open, Pipeline estimate, Weighted, Past stage level, Win rate (12 months) | Tiles, same figures, each saying its period; **+ Won · Lost (12 months)** |
| Board / List; year (list); find a deal | Same; the year picker only on the list (it never applied to the board) |
| ?pipeline= and ?status= filters (no controls) | **Pipeline** and **Status** selects |
| Board columns: name, count, value, win %, weighted; cards name / client / ref / value / stalled; Won/Lost last 90 days, "+N older — show all" | Kept |
| "Why deals were lost — 12 months" | Kept, under the board |
| List columns and CSV columns | Kept (the shared register) |

### Deal page
| Today | Now |
|---|---|
| Chain strip; name, ref, status, customer, stage; Delete (management / master, not won, no quotations) | Header card; Delete under Actions (same rule) and in ⋯ |
| Trade-block banner; "Held for approval" (to-stage, who asked, when, waiting on, note; Approve / Send back with remarks / Withdraw; All approvals) | Banners at the top — same forms |
| Now-band: Won (work order link / next step, Reopen) · Lost (reason, lost to, note, Reopen) · Open (stage, overdue, quote now, next action, Move it on, See the lead) | Overview → first card, by state, same text and forms |
| Worth, Likely, Hoped to close, Handled by, Branch, At this step for, Up against, Came from, Effort; What they want | Three boxes + Deal summary (+ business unit, + how many quotations — "n — on the Quotes tab") + What they want |
| Project costing (open / unlink / 🔒 / create / attach) | Overview → Project costing card, same forms |
| Quotes: create new, "Already a customer?", list with Detach, attach, Generate (won, none) | Quotes tab, same forms; statuses in words |
| Won: work order / contract / order block (+ add customer, set customer) — these sat outside the tabs | **Still above the tabs**, so they show on every tab (the robot found this: kept as before) |
| Won through a quote / with Accounts (+ register contract) / send to Accounts; raise a work order | Actions tab, one panel per state, same forms; the tab opens with "Won — … said yes. 🎉" and its one next step |
| Move it (stage, lost reason / to / note) | Actions tab, **+ note for the approver**; after a win the page opens on Actions, after a loss on Overview ("Reason: … — lost to … .", the same sentence as before) |
| Edit the details (every field) | Actions tab, **+ business unit** |
| How it moved; log form; activity timeline | History tab (the log form and timeline were outside the tabs) |

### New deal
Every field (name, pipeline, customer + Add new / typed name, value, expected close, branch, source, competitor, contact name / e-mail / phone with auto-fill, next action / by when, what they need, the company's extra fields), Open the deal / Cancel, and the error with nothing lost — all kept; **+ business unit**.

## Tests

| What | Result |
|---|---|
| `test_sales_stage3` — every screen drawn twice (old view kept in `tests/fixtures/pre_stage3/`, new view) as **13 people** (Business Director, Master Admin, Admin, BU Head, Branch Manager, Finance, Coordinator, BDM, KAM, Marketing Manager (all branches), Marketing Executive, a BDM without the deal download, a BDM with inquiries view only) × **15 pages**; plus DEF-14 / 15 / 16, items 3–7, branch scope, CSV scope, paging, sorting, return after save | **1259 passed, 0 failed** |
| The whole automatic suite (every test file) | **24,546 passed, 9 failed.** The same suite on Stage 2 (8d60c50): 23,288 passed, 8 failed. **7 of the 9 already failed on Stage 2** (terminology count, lookup usage counter, permission-foundation scenario A ×2, a comment in `sx_settings.php`, SALA-013, SALA-015) — none is in an enquiry or deal screen. The other **2 are the deployment checker**, which clears when the checksums are stamped (done in the next commit). One Stage 2 failure is now fixed: the "next-action band" check counts the record kit's Next action card (lead and deal pages). Eight older tests were updated to where the approved design moved things (the inquiry page, the duplicate link, the register's columns, the Quote button's icon) — same intent, new address. |
| Screen sizes — 7 pages × 8 widths (1920 to 360) | **56 checks: no sideways scroll, nothing cut off, no control under 32 px.** The old pages: 12 overflow problems and 20 pages with controls of 27–30 px. |
| Robot — all 104 cases, compared case by case with the previous run (Stage 2) | **46 passed, 23 failed, 24 incomplete, 10 need you, 1 out of date — exactly the same as Stage 2, case by case; no case worse.** The first run found 5 cases worse (SAL-030, SAL-031, SAL-120, SAL-160, SAL-182); each was a real change in what a person sees, and each was fixed in the screen, not in the robot: the won deal's "add the customer first" / contract / order box is back above the tabs (it shows whichever tab is open); a win lands on Actions and a loss on Overview; the loss reason reads as one sentence again; the "Why deals were lost" box is found as before; the ⋯ menu item is "Move to another stage" so the visible "Move it on" button is the one pressed; the Deal summary has "n — on the Quotes tab". The 19 deal and enquiry cases were then run again on the final code. |

## Checks for you on opstest (about 12)

1. **Sales → Inquiries.** The four tiles show the same numbers as before; the list shows 50 a page; click a column heading — it sorts.
2. Press **CSV** — the file has only your branches' inquiries.
3. Click an inquiry's **Open** — its own page opens: Overview, Details, Quotes, History.
4. Sign in as someone who can only **view** inquiries — they can open the page, and see no Edit and no Drop.
5. On an inquiry, press **Edit**, change the subject, **Save inquiry** — you come back to the inquiry page, not the list.
6. On an open inquiry with no quotation: **Drop this inquiry** (pick a reason) — it says Dropped; **Reopen it** brings it back.
7. **Sales → Deals** on a phone — the stages stack one under another; no sideways scrolling.
8. On the board, change **Pipeline** (if you have more than one); on the list, change **Status**.
9. Open a deal — the tabs are Overview, Quotes, Actions, History; the quotation's status reads in words.
10. **Actions → Move it**: try "Lost" without a reason — refused, as before; move to the next stage — the likely % follows the stage.
11. **+ New deal**: type −5 as the value — refused; type a business unit — it is kept on the deal.
12. History tab: **Log call** — the log form opens there, and the call appears in the timeline.

## Files

- **New:** `views/ops/crm/inquiry_detail.php`, `tests/test_sales_stage3.php`, `tests/_stage3_worker.php`, `tests/fixtures/pre_stage3/` (the five old views).
- **Rewritten:** `views/ops/crm/inquiry_list.php`, `views/ops/crm/inquiry_form.php`, `views/ops/opportunities.php`, `views/ops/opportunity_detail.php`, `views/ops/opportunity_form.php`.
- **Changed:** `lib/crm.php` (register on the shared component, CSV, the new route, return after save), `lib/opportunities.php` (form checks, business unit, win % not taken), `lib/ops.php` (the route), `lib/activity.php`, `lib/chain.php`, `lib/search.php`, `lib/advisor.php`, `views/ops/project_costing.php` (links open the inquiry page), `views/ops/stage_gates.php` (wording), `assets/css/app.css` ("SALES — ENQUIRIES AND DEALS").
- **Docs:** `docs/02` 19v, `docs/05`, register 52, `PENDING.md`.

## Package for opstest

`EXAACT-V51.1-Sales-Stage3-TEST-COPY-update.zip` — built from commit ada12aa (code 707bf70), files at the top level, 842 files.
SHA-256 `13f3cce95436d68a76d653b27ba57f837c4e9c7ec8d5b72f89e9cec7ad6ec6e9`.
It does **not** contain `config.local.php`, the root `tenants.php`, `tests/`, any `*.sqlite`, or the `exaact_data*` / `exaact_backups*` folders, so the test copy stays the test copy. Upload and extract it in the opstest app folder only, then open `/deploy-check.php`.
