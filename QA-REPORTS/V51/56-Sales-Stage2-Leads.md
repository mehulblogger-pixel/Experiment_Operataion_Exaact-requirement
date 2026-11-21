# Sales redesign, Stage 2: Leads (21 Nov 2026)

**What you approved** (21 Nov, "All as recommended"): build Stages 2–6 to the picture pack (`EXAACT-Sales-Stages2-6-Designs.pdf`), one stage after another, with evidence after each, and the backend changes on its page 2. Stage 2 is the lead screens: the leads register, the full lead form, the quick lead, the lead → customer screen, and the lead page's alignment. Also in this stage: backend items 1 and 2, DEF-15 (lead part) and DEF-21.

**This update** is **c1a747b** (deploy-check stamp *c1a747b · 2026-11-21 07:18 UTC · 746 files*). It builds on the "Who can do what" update (b225216). Nothing in the database changed: no new table, no new column, no new status, no new permission.

**Rollback point:** update **b225216** (report 55). To go back, re-upload that update's zip.

---

## 1. What changed, screen by screen

| Screen | Before | Now |
|---|---|---|
| **Leads register** `/leads` | Five cards (one alone on the last row), a grey toolbar, two CSV buttons in different places, raw source codes, no way to filter by status, no way to preview a bulk action | The Sales bar, **six** tiles (the "Not pursued" count was worked out but never shown), one row of controls (Board · List, Year, **Status**, Pipeline, Find, **one** CSV), the attention banner with a button that lists the oldest first, a cleaner board, and on the list: **Call · WhatsApp · Log** on each open lead, the source **in words**, and **"Preview first"** for bulk actions |
| **New lead** `/lead-new` | One long page, no duplicate check unless opened from a link, no business unit | **Five numbered steps** (steps 3–5 fold on a phone until used), a right-hand column saying what happens next, **the business unit** (DEF-15), and the **duplicate check while typing and on save** (backend item 1) |
| **Quick lead** `/sales-lead` | Its own older look | The same look as the other lead screens; every box, check and button kept |
| **Lead → customer** `/lead-convert` | Raw source code, a client list you could not search, nothing said what Yes does | The client list is **searchable**, the source is in words, and a card lists the five things that happen when you press Yes |
| **Lead page** `/lead?id=` | The approved page (20 Nov) | Unchanged, except: the Sales bar above, the phone bottom bar below, and the **business unit** shown under Details and editable in "Edit the details" (DEF-15) |

### The two backend items and two fixes

1. **Duplicate check on the full form (backend item 1).** The quick lead's own check, now also used by the full form.
   - It looks for the same name, the same mobile on an open lead, or a contact with that mobile.
   - **While typing:** the matches appear under step 1.
   - **On save:** if there are matches and the box "It is a different requirement — save it anyway" is not ticked, nothing is saved. The form comes back with everything you typed and the matches.
   - **Example:** typing "Nova Castings" when an open lead L-2026-0002 Nova Castings exists shows "An open lead: L-2026-0002 — Nova Castings (same name)".
   - A client picked from the master is not its own duplicate. An open lead for that client is.
2. **Business unit on a lead (backend item 2, DEF-15).**
   - It is set on the full form and in "Edit the details", and shown under Details.
   - Only the units in the person's own data scope are offered and accepted, just as the branch list is limited to the person's branches.
   - An older page that does not send the unit leaves it as it is.
3. **DEF-21: "How promising" said "nobody has logged a conversation" although the History showed a call.**
   - **Root cause:** the score counted only conversations logged against the lead's **client**. Most leads have no client yet, so a call logged on the lead itself was never counted.
   - **Now:** it counts the calls, WhatsApps, e-mails, meetings and site visits logged on the lead (the entries its History shows), as well as the client's, and takes the later of the two. A "note to self" does not count.
   - **Example:** Nova Castings, with a call logged today, reads "spoken to this week" and its score rises by 20 (from 12 to 32 on the test copy).
4. **A lead's source in words.** For example, "They telephoned us" instead of CALL_IN. This applies on the register (the Source column you can add), in the CSV and on the lead → customer screen.

## 2. What did not change

- **Same addresses.** Every route works as before: `/leads`, `/lead-new`, `/sales-lead`, `/lead-convert`, `/lead`, `/lead-edit`, `/leads-bulk`.
- **Same gates.** Who may open, add, edit, allocate, mark lost or download is unchanged. The checks are `leads_can_view`, `leads_can_edit`, the branch scope and the Batch A "Download leads" switch.
- **Same figures.** Every figure, list and refusal is the same. They are checked old against new below.
- **Same saves.** The forms post the same fields to the same places. Only `sbu` (the business unit) and `confirm` (the "save it anyway" tick) are new.
- **The bulk actions** keep their extra boxes and confirmations.
- **The board** keeps every column, the carried-forward switch, the 30-card cap on closed columns and "+N more — see the list".

## 3. Feature preservation matrix

Every item of the Phase 1 inventory (`audit/B-leads-inquiries-deals.md`, sections 1, 2, 3, 5) and where it is now. "Same" means the same name, address and rule.

**Leads register `/leads`**

| Before | Now |
|---|---|
| Board / List toggle (`v`) | Board · List switch at the start of the control row — same addresses |
| Year (`fy`), carrying `v`, `pipeline`, `only`, `q` | Same select, same hidden fields (and `status` on the list) |
| Pipeline select (board, more than one lead pipeline) | Same, in the control row |
| Quick find on the board → list (`v=list&q=`) | Same form; the note under the row now says it opens the list |
| ⬇ CSV on the board (header) / inside the register (list) | Board: in the control row; list: inside the register — one per mode, both still need "Download leads" |
| 5 cards: Open, Open pipeline value, Past their stage's service level, Won — now customers, Need attention now | Same five figures (each tile carries its old title, checked old v new) + **Not pursued** (already computed, now shown) |
| Attention banner, link to /advisor | Same words and link + "Show the oldest first" (the list sorted by In stage) |
| Carried-forward banner and its two links | Same, same links |
| Board columns: name, count, value, win % | Same, in the column header |
| Open card: company, ref, value, carried-forward, score, stalled days, close date passed, ◆ deal | All kept (red edge for stalled kept) |
| Closed cards (Won / Not pursued, capped 30) and "+N more — see the list" | Same, same link |
| Footer legend | Same sentence |
| List columns Ref, Company + contact, Stage, Value, Owner, In stage, Score, Status; optional Branch, Source, Expected close | All kept, same sort; Source now in words; **+ Reach them** (Call · WhatsApp · Log) |
| Search, row count, Columns, rows per page, pager | Same register component |
| Bulk: Allocate to… (+ person, reason), Take these myself (+ reason), Mark lost (+ reason, remarks), confirmations | Same buttons, boxes and confirmations |
| Bulk dry run (`preview`, server only) | Now a tick box: **Preview first** |
| Status filter (`status`, server only) | Now a **Status** select on the list |
| CSV columns (14) and file name | Same 14 columns and name; Source in words |
| Who sees what (`leads_can_view`, branch scope, `crm.lead.export`, edit right for bulk) | Unchanged |

**New lead `/lead-new`**

| Before | Now |
|---|---|
| Client on the master (searchable) + "+ Add new", or a typed name; picking fills the name and the main contact | Step 1, same boxes, same behaviour |
| Contact name, E-mail, Telephone, Where they came from | Step 1 |
| The requirement, Value (≥ 0), Expected to close | Step 2 |
| — | **Business unit** (step 2, DEF-15) |
| Type of deputation, How many people, Skills, Site (required for man-month) | Step 3, same show/hide and required rule |
| Allocated to (default me), Branch (only own branches), Pipeline, Next thing to do (+ list, Masters link), By when | Step 4 |
| Files, What is it?, Note (8 MB each) | Step 5 |
| Your company's own fields (Form Designer) | Step 5 |
| "We may already know them" panel (`?company=`) | Same panel and links |
| — | **Duplicate check while typing and on save**, with "save it anyway" |
| "Nothing was lost" after a refused save | Same |
| Create it / Cancel | Same, at the end of the form |

**Quick lead `/sales-lead`**

| Before | Now |
|---|---|
| Company or person (required), Mobile (10 digits), Where did it come from, Contact person, Rough value, First follow-up (next working day), Office (if more than one), Give it to (managers), Attach files | All kept, same names and rules |
| Duplicate list + hidden `confirm` → "Save anyway" | Same |
| Save lead / Save & add another / Cancel → Today | Same |
| "Full lead form ›" carrying the typed boxes (and the warning about files) | Same, in the header |
| Error banner | Same message, new look |

**Lead → customer `/lead-convert`**

| Before | Now |
|---|---|
| hidden `id`, `stage_id` | Same |
| Attach to a client (+ Add new, "create a new one") | Same, now **searchable** |
| Name for the new one, State, Business unit (only when the lead has none) | Same |
| What comes across: contact, e-mail, telephone, source, value, owner, requirement | Same, source in words |
| "They may already be on the master" panel | Same |
| Yes — make them a customer / Not yet | Same buttons |
| Server rules (refuses converted / lost, 2-minute lock, exact match blocks, possible match noted) | Unchanged; now explained in the right-hand card |

## 4. Old against new: the same figures, links, fields and access, for every role

Each screen was drawn twice through the real application, as the same person on the same data. One drawing used the **old** page, kept in `tests/fixtures/pre_stage2/`; the other used the **new** one. For every person and page, the test checks that:
- the page opens, or is refused, exactly as before;
- every figure reads the same;
- every link of the old page is on the new one;
- every form keeps its address and every field it had;
- every amount of the old page is on the new one.

**People** (12):
- Business Director, Master Admin and Admin
- Business Unit Head
- Branch Manager (Mumbai) and Finance (Mumbai), who are refused the lead register, as before
- Coordinator
- BDM (Mumbai), KAM (Mumbai), Marketing Manager (all branches) and Marketing Executive (Mumbai)
- a BDM whose own exception removes "Download leads"

**Pages** (11):
- the board
- the list, the list filtered to open leads, the board limited to the year's own leads, and a search
- the new-lead form, and the form with a known company
- the quick lead
- two lead pages (one in another branch)
- the lead → customer screen

**Result:** **846 of 846 checks pass.** For all 12 people on all 11 pages, the page opens or is refused as before, and nothing is lost: no figure, link, form field or amount. Sixty-five figure comparisons on the register alone (5 per page) read the same.

**Also checked:**
- The CSV is shown only with the download right, and hidden for the BDM with the exception (on the list and on the board).
- **Log** on a row, and **+ New lead**, appear only for people who may edit leads.
- A Mumbai BDM sees the Mumbai leads and not the Ahmedabad one. An all-branch Marketing Manager sees both.
- The "Not pursued" count, the status filter and "Preview first" are on the page.

## 5. Screen sizes

All six lead screens were checked at 8 widths (1920, 1440, 1366, 1024, 768, 430, 390 and 360 px) in a real browser: **48 page-and-width checks, 0 problems**.
- **New:** no sideways scroll, nothing cut off, no PHP message, and no control under 32 px.
- **Old (for comparison):** the register's buttons were 27–28 px on five widths.

Before and after pictures, laptop (1366 px) and phone (390 px), are at the end of this report.

## 6. Tests

- **New test `test_sales_stage2`: 846 / 846.** It covers:
  - old against new for 12 people × 11 pages;
  - what each role sees;
  - DEF-21 (5 checks);
  - DEF-15 (9 checks, including a person scoped to one business unit);
  - the duplicate check (12 checks: on save, while typing, by mobile, a client picked from the master, someone without the right, a clean lead saved at once, a bad unit refused).
- **157 related test files** (leads, Sales, Stage 1, permission foundation A, "Who can do what", the defect tests, branch and scope, quotations, enquiries, deals, the m-series entitlement tests, areas, navigation, clients): **6,231 checks pass**.
  - **One check was updated** in `test_lead_page_v3`. It looked for the lead page's exact wrapper, which now also carries the Sales bar (234 / 234).
  - **10 checks fail, and each fails exactly the same on the code before this change (b225216):**
    - `test_m7_report_export_entitlement`: 6 (known);
    - `test_m8_public_background_entitlement`: 3 (DEF-24);
    - `test_m2_org_structure`: 1, the back-office staff form, outside Sales.
- **Robot: all 104 cases are identical, case by case, to the previous run** (46 passed, 23 failed, 24 incomplete, 10 need you, 1 out of date).
  - On the first run, 9 cases fell back. The cause was two wording changes: the button read "New lead" instead of "+ New lead", and the banner read "1 lead needs attention" instead of "1 lead need attention". Both were put back to the exact old words, and all 9 returned to their previous result. No robot script was changed.

## 7. Decisions taken, and things left as they were

1. **The register's list keeps the shared register component** (search, Columns, rows per page, sort, paging, bulk). It is the same one every other register uses, so the column chooser and saved column choices keep working. It is placed in the new card. A new column, "Reach them" (Call · WhatsApp · Log), is added to it.
2. **The duplicate list can name a lead in another branch.** This is the quick lead's existing behaviour, reused as approved. Opening that lead is still refused outside the person's branches. Limiting the list to their branches is a one-line change; it is in PENDING.md for your decision.
3. **The 10-digit mobile rule stays on the quick lead only.** The full form takes any telephone, such as a landline, as before.
4. **The buttons sit at the end of each form,** not floating. On a phone, a floating bar covered half the screen.
5. **The lead page** (approved 20 Nov) is not redesigned again. Only the Sales bar, the bottom bar and the business unit were added.

## 8. Your check list on opstest (12 checks)

1. **Sales → Leads.** Six tiles, including **Not pursued**. The numbers match what the old page showed for the same year.
2. **Board → List.** On the list, choose **Status: Lost**. Only lost leads show.
3. **Columns → tick Source → Apply.** The source reads in words, for example "They telephoned us".
4. **Tick two leads, tick "Preview first", press "Take these myself".** A blue message says what would change. Nothing changes.
5. **Phone and WhatsApp on a row.** They dial and open WhatsApp. **Log** opens the lead's log form.
6. **+ New lead.** Type the name of a lead you already have. Within a second the matches show under step 1.
7. **Press Create it without ticking "save it anyway".** Nothing is saved, and everything you typed is still there. Tick the box and press again: it saves.
8. **Choose a Business unit on the new lead.** After saving, the lead page shows it under **Details**. Change it in **Edit the details**.
9. **On a lead with no client, log a call.** "How promising" now says **"spoken to this week"**.
10. **Quick lead.** Save one. It works exactly as before: next working day, "First call", and Save & add another.
11. **Move a lead to "Won — customer".** On the next screen the client list can be searched, and the card lists what will happen.
12. **On a phone, open the register and the new-lead form.** The bottom bar shows Leads highlighted, nothing scrolls sideways, and steps 3–5 open when tapped.

## 9. Files

- **Changed:**
  - `lib/leads.php`: DEF-21; source in words; the "Reach them" column; business unit on create and edit; the duplicate check on save and while typing.
  - Views: `views/ops/leads.php`, `lead_form.php`, `sx_lead.php`, `lead_convert.php`, `lead_detail.php` (Sales bar, bottom bar, business unit).
  - `views/ops/_rec_ui.php`: 4 icons.
  - `assets/css/app.css`: the "SALES — LEADS" block.
- **Docs:** `docs/02` item 19u (no permission added), `docs/05` (lead screens), register 52 (DEF-15 lead part, DEF-21 fixed), `PENDING.md`.
- **Tests:**
  - new: `tests/test_sales_stage2.php`, `tests/_stage2_worker.php`, and `tests/fixtures/pre_stage2/` (the old pages);
  - updated: `tests/test_lead_page_v3.php` (one check).
- **Update zip:** `EXAACT-V51.1-Sales-Stage2-TEST-COPY-update.zip`.
  - 841 files, sha256 `1596e82b5e5aba014a926feb10e27532327a73b3d0e72a05f5e4028b05dbc398`.
  - It contains no `config.local.php`, no root `tenants.php`, no `tests/`, no database and no data folders.
  - It also carries everything since the lead page: Stage 1, DEF-01, defect batch 1, permission foundation A and "Who can do what".
