# Closing check — Phase 1 inventory tick-off (21 Nov 2026)

Every bullet of the 57-page Phase 1 inventory (`EXAACT-Sales-Screen-Inventory-Phase1.pdf`, source `audit/all.md`) was checked against the final code (commit after 9e6e240).
Method: every route, field name, function and quoted on-screen wording in an item was searched for in the final program (lib, views, index.php, scripts). Items that describe layout or behaviour without a literal word are covered by each stage's preservation matrix (reports 51, 56–60) and by the stage tests that draw every page old against new as 12–13 real people and compare every link, form field, button, amount and refusal.

## Counts

| Result | Items |
|---|---|
| preserved — found in the final code | 573 |
| preserved — structural item, covered by the stage preservation matrix and old-vs-new test | 336 |
| preserved — found (some words are filled in at run time) | 38 |
| preserved — checked by hand | 12 |
| changed by owner decision | 5 |
| not an item (audit observation) | 1 |
| **Total** | **965** |

**Removed without an owner decision: none. Not found: none.**

## Items checked by hand or changed by decision

| Line | Screen | Item | Result |
|---|---|---|---|
| 122 | 1. Leads register — `/leads` (board and list) | **Attention banner** (`:29-36`) when `dueCount > 0`. It links to `/advisor` and tells the  | Wording corrected in Stage 2 (the banner pointed to a column name that did not exist). |
| 134 | 1. Leads register — `/leads` (board and list) | "↻ Carried forward from FY x" | Present: board card reads "↻ Carried forward from FY …" (FY filled in at run time; views/ops/leads.php). |
| 315 | 4. Lead details — `/lead?id=` (HEAD version,  | Note: "closes by itself as won when they accept". | Present on the lead page (views/ops/lead_detail.php, "closes by itself"). |
| 343 | 4. Lead details — `/lead?id=` (HEAD version,  | "What's happened": table When / What (ACT_KINDS pill, direction, subject, outcome) / Notes | Renamed "History" on the approved lead page v3 (owner, 20 Nov) — same table. |
| 414 | 5. Lead conversion — `/lead-convert` | Sets the lead CONVERTED on the WON stage; back-fills `opportunities.partner_id`; `ads_queu | Present: code sets opportunities partner_id (column name written without the table prefix). |
| 487 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Delete: admin-level/master + scope + no quotations + module verb `mod.inquiries.delete`. | Present: the delete verb is built as mod.inquiries.<verb> at run time (lib/ops.php). |
| 520 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Text "this follows the quote" if quotes exist. | Present: wording assembled from terminology — "Status is not typed: it follows the <quote>" (inquiry form). |
| 523 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Missing from the inquiry screen:** a link to the customer record, a link to the deal, an | Not an item: an observation in the audit that these links were missing; Stage 3 added customer and deal links and the Quote button. |
| 702 | 10. Deal details — `/opportunity?id=` | **History** fold (open): stage moves table. If there are none: "Opened by X on date". | Present: "Opened by <name> on <date>" (deal page History). |
| 796 | 12. Stage gates (approval rules for deal stag | The empty-state copy mentions "drags it", but no board supports drag. | Removed by owner decision (DEF-16): no screen mentions dragging. |
| 1121 | 3. Quotation details | **Page layout order:** record strip → header (title, status pill, "🕑 Rev NN · n versions"  | Present: "Rev NN · N versions" in the quotation header (numbers filled in at run time). |
| 1172 | 3. Quotation details | APPROVED: "✉ Send to client" or reason. | Present: "Send to the <client>" — word from terminology (quote page). |
| 1203 | 3. Quotation details | **Approval chain panel** (`:411-483`, when steps exist): "⏳ Waiting with <names> — level n | Present: "Waiting with <names> — level n of N" (quote page Approvals panel). |
| 1754 | 1. Orders & payments | Header "💰 Orders & payments" (`sx_orders.php:12`); "Why you're here … Amounts include GST. | Changed by owner decision DEF-06 A: amounts before GST are now the main figure, GST labelled beside. |
| 1768 | 1. Orders & payments | Empty: "No accepted quotes in this period." (:27). | Present: "No accepted <quotes> in this period." — word from terminology (Orders & payments). |
| 1834 | 3. Renewals | Empty: "Nothing ends in the next d days." (:19). | Present: Renewals empty state (views/ops/sx_renewals.php). |
| 1837 | 3. Renewals | Look-ahead chips "Next 30/60/90/180 days" → `/sales-renewals?d=` (:10). | Changed by owner decision DEF-10: one look-ahead window from Sales settings (60 days to start) instead of 30/60/90/180 buttons. |
| 2053 | 12. Set up Sales / Sales approvals (discovere | Linked from Sales settings ("✅ Who approves quotes"), Sales area tile 16, Admin → Approval | Present: "Who approves" link on Sales settings. |

## Every item

| Line | Screen | Item | Result |
|---|---|---|---|
| 99 | 1. Leads register — `/leads` (board and list) | **Source** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 100 | 1. Leads register — `/leads` (board and list) | View: `views/ops/leads.php` (188 lines) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 101 | 1. Leads register — `/leads` (board and list) | Handler: `ops_leads` → `route === 'leads'` at `lib/leads.php:1506-1564`; bulk at `:1482-1504` | preserved — found in the final code |
| 102 | 1. Leads register — `/leads` (board and list) | Shared register component: `lib/datatable.php` (`dt_state` :74, `dt_render` :137, `ops_dt_columns` :289) | preserved — found in the final code |
| 103 | 1. Leads register — `/leads` (board and list) | **Routes** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 104 | 1. Leads register — `/leads` (board and list) | GET `/leads?v=board/list` (default `board`) | preserved — found in the final code |
| 105 | 1. Leads register — `/leads` (board and list) | GET `/leads?...&export=csv` | preserved — found in the final code |
| 106 | 1. Leads register — `/leads` (board and list) | POST `/leads-bulk` (`:1482`) | preserved — found in the final code |
| 107 | 1. Leads register — `/leads` (board and list) | POST `/dt-columns` (column chooser; saves the per-user preference `dt.leads.cols`) | preserved — found (some words are filled in at run time) |
| 108 | 1. Leads register — `/leads` (board and list) | **Purpose:** the funnel of prospects, as a kanban board per pipeline or a paged register. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 109 | 1. Leads register — `/leads` (board and list) | **Side effect on GET:** `lead_close_won_deals_sweep()` (`:1477`, defined `:1125`). It closes up to 50 leads wh | preserved — found in the final code |
| 110 | 1. Leads register — `/leads` (board and list) | **Header** (`leads.php:7-18`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 111 | 1. Leads register — `/leads` (board and list) | H1 "Leads", plus a one-paragraph description. | preserved — found in the final code |
| 112 | 1. Leads register — `/leads` (board and list) | `+ New lead` → `/lead-new`, shown if `$canEdit`. | preserved — found in the final code |
| 113 | 1. Leads register — `/leads` (board and list) | `⬇ CSV` → current query + `export=csv`. Shown on the **board only**; the list has its own CSV inside `dt_rende | preserved — found in the final code |
| 114 | 1. Leads register — `/leads` (board and list) | **KPI cards** (`.qcards`, `:20-28`, from `leads_counts($fyWin,$onlyYear)` `:1292` and `leads_due_count()` `:53 | preserved — found in the final code |
| 115 | 1. Leads register — `/leads` (board and list) | Open — labelled with the FY tag, including carried-forward leads | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 116 | 1. Leads register — `/leads` (board and list) | Open pipeline value | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 117 | 1. Leads register — `/leads` (board and list) | Past their stage's service level (stalled) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 118 | 1. Leads register — `/leads` (board and list) | Won — now customers (FY) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 119 | 1. Leads register — `/leads` (board and list) | Need attention now (`leads_due`: past SLA, follow-up date passed, or expected close passed; **not FY-filtered* | preserved — found in the final code |
| 120 | 1. Leads register — `/leads` (board and list) | Cards are not links. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 121 | 1. Leads register — `/leads` (board and list) | `leads_counts` also computes `lost`, which is **not shown**. | preserved — found in the final code |
| 122 | 1. Leads register — `/leads` (board and list) | **Attention banner** (`:29-36`) when `dueCount > 0`. It links to `/advisor` and tells the reader to "sort by * | changed by owner decision |
| 123 | 1. Leads register — `/leads` (board and list) | **Toolbar** (`:38-75`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 124 | 1. Leads register — `/leads` (board and list) | Board/List toggle buttons (`v`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 125 | 1. Leads register — `/leads` (board and list) | FY select `fy`: `fy_select_html` submits on change. It carries `v`, `pipeline`, `only`, `q`. | preserved — found in the final code |
| 126 | 1. Leads register — `/leads` (board and list) | Pipeline select `pipeline`: board only, and only when more than one LEAD pipeline exists. It carries `v=board` | preserved — found in the final code |
| 127 | 1. Leads register — `/leads` (board and list) | Quick find: board only. It switches to `v=list&q=`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 128 | 1. Leads register — `/leads` (board and list) | **Board** (`:77-136`; data from `lead_board($pipelineId,$fyWin,$only)` `lib/leads.php:1196`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 129 | 1. Leads register — `/leads` (board and list) | Pipeline auto-fallback: if no pipeline is chosen and the default has no open leads, the board shows the pipeli | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 130 | 1. Leads register — `/leads` (board and list) | Carried-forward banner (`:81-92`): "↻ N open leads carried forward" with a toggle link to `only=1` ("Only lead | preserved — found (some words are filled in at run time) |
| 131 | 1. Leads register — `/leads` (board and list) | One column per stage: name, count (`total`), value, and stage probability %. | preserved — found in the final code |
| 132 | 1. Leads register — `/leads` (board and list) | Open card → `/lead?id=`. It shows: | preserved — found in the final code |
| 133 | 1. Leads register — `/leads` (board and list) | company, ref, value | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 134 | 1. Leads register — `/leads` (board and list) | "↻ Carried forward from FY x" | preserved — checked by hand |
| 135 | 1. Leads register — `/leads` (board and list) | score pill (≥60 ok, ≥35 warn, else muted) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 136 | 1. Leads register — `/leads` (board and list) | stalled days pill, plus a red left border | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 137 | 1. Leads register — `/leads` (board and list) | "⏰ close date passed" pill | preserved — found in the final code |
| 138 | 1. Leads register — `/leads` (board and list) | "◆ deal" pill when a deal exists (`deal_id` filled in by one query `:1250-1265`) | preserved — found in the final code |
| 139 | 1. Leads register — `/leads` (board and list) | Closed cards (WON / LOST columns, capped at `LEAD_BOARD_CLOSED_CAP=30`, `:1194`): grey with "✓ Won — customer" | preserved — found (some words are filled in at run time) |
| 140 | 1. Leads register — `/leads` (board and list) | Footer legend paragraph. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 141 | 1. Leads register — `/leads` (board and list) | **No drag-and-drop.** A card can only be opened. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 142 | 1. Leads register — `/leads` (board and list) | **List** (`:138-187`) via `dt_render()` | preserved — found in the final code |
| 143 | 1. Leads register — `/leads` (board and list) | Columns (`leads_dt_columns()` `lib/leads.php:1316`); sort expression in brackets: | preserved — found in the final code |
| 144 | 1. Leads register — `/leads` (board and list) | Ref (link) [`l.ref`] | preserved — found in the final code |
| 145 | 1. Leads register — `/leads` (board and list) | Company + contact [`l.company_name`] | preserved — found in the final code |
| 146 | 1. Leads register — `/leads` (board and list) | Stage [`s.seq`] | preserved — found in the final code |
| 147 | 1. Leads register — `/leads` (board and list) | Value [`l.value`, num] | preserved — found in the final code |
| 148 | 1. Leads register — `/leads` (board and list) | Owner [`l.owner_name`] | preserved — found in the final code |
| 149 | 1. Leads register — `/leads` (board and list) | Branch* [`o.name`] | preserved — found in the final code |
| 150 | 1. Leads register — `/leads` (board and list) | Source* [`l.source`] — **raw code shown, e.g. `CALL_IN`** | preserved — found in the final code |
| 151 | 1. Leads register — `/leads` (board and list) | Expected close* [`l.expected_close`] | preserved — found in the final code |
| 152 | 1. Leads register — `/leads` (board and list) | In stage [`l.stage_since`] days, plus a "late" pill | preserved — found in the final code |
| 153 | 1. Leads register — `/leads` (board and list) | Score (not sortable; dash when not OPEN — **[WIP]** `:1343-1345`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 154 | 1. Leads register — `/leads` (board and list) | Status pill [`l.status`] | preserved — found in the final code |
| 155 | 1. Leads register — `/leads` (board and list) | `*` = optional column, hidden by default. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 156 | 1. Leads register — `/leads` (board and list) | Parameters: `q` (company / contact / ref / e-mail LIKE, from `leads_where` `:432`), `sort`, `dir`, `per` (`DT_ | preserved — found in the final code |
| 157 | 1. Leads register — `/leads` (board and list) | Default sort is value desc. The fallback order is open-first, then stage seq, then value. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 158 | 1. Leads register — `/leads` (board and list) | `dt_render` chrome: search box with Clear, row count, `Columns` chooser (`<details class="dt-cols">`), rows-pe | preserved — found in the final code |
| 159 | 1. Leads register — `/leads` (board and list) | Bulk actions (only if `$canEdit`). Checkbox per row plus select-all; posts `ids[]` and `bulk`: | preserved — found in the final code |
| 160 | 1. Leads register — `/leads` (board and list) | `assign` "Allocate to…" — extra controls `assign_to` (active users) and `reassign_reason`. Runs through `lead_ | preserved — found in the final code |
| 161 | 1. Leads register — `/leads` (board and list) | `mine` "Take these myself" — extra control `reassign_reason_mine`. | preserved — found in the final code |
| 162 | 1. Leads register — `/leads` (board and list) | `lost` "Mark lost" (danger) — extra controls `lost_reason` (`lk_options_or('quote_lost_reason')`) and `lost_no | preserved — found in the final code |
| 163 | 1. Leads register — `/leads` (board and list) | The extra controls show on hover / focus / touch of their button (`datatable.php:254-268`). Each button has a  | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 164 | 1. Leads register — `/leads` (board and list) | The handler supports **dry-run preview** (`$_POST['preview']`, `:1488-1500`, `leads_bulk_plan` `:1387`), but * | preserved — found in the final code |
| 165 | 1. Leads register — `/leads` (board and list) | **CSV** (`:1516-1541`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 166 | 1. Leads register — `/leads` (board and list) | The list exports every row its filters match. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 167 | 1. Leads register — `/leads` (board and list) | The board exports its open cards (carried-forward included) plus that FY's CONVERTED and LOST leads. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 168 | 1. Leads register — `/leads` (board and list) | Columns: Ref, Company, Contact, Stage, Status, Value, Owner, Branch, Source (raw code), Expected close, Days i | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 169 | 1. Leads register — `/leads` (board and list) | File name `leads-FY…-date.csv`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 170 | 1. Leads register — `/leads` (board and list) | **Permissions:** `lead_scope_gate`; `ops_require(leads_can_view())` (`:1475`); bulk `ops_require($canEdit)`; b | preserved — found in the final code |
| 171 | 1. Leads register — `/leads` (board and list) | **Backend** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 172 | 1. Leads register — `/leads` (board and list) | Functions: `leads_all`, `leads_count`, `lead_board`, `leads_counts`, `leads_due`, `lead_score` (rules engine ` | preserved — found in the final code |
| 173 | 1. Leads register — `/leads` (board and list) | Tables: `leads`, `pipeline_stages`, `pipelines`, `offices`, `business_partners`, `opportunities` (deal badge), | preserved — found in the final code |
| 174 | 1. Leads register — `/leads` (board and list) | **Links out:** lead detail; `/lead-new`; `/advisor`; the deal is visible only as a badge (the card does not li | preserved — found in the final code |
| 175 | 1. Leads register — `/leads` (board and list) | **Phone vs desktop** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 176 | 1. Leads register — `/leads` (board and list) | Board uses `.board-wrap/.board/.board-col`. At ≤820px the columns stack vertically (`app.css` HEAD :1116-1129) | preserved — found in the final code |
| 177 | 1. Leads register — `/leads` (board and list) | List: `.dt-scroll`; `initResponsiveTables()` (`app.js` HEAD :1728) turns `table.dt` into label/value cards; `. | preserved — found in the final code |
| 178 | 1. Leads register — `/leads` (board and list) | KPI cards: 4-across grid, 2-across on small screens (`app.css` :519,:623). Five cards means an orphan in the l | preserved — found in the final code |
| 179 | 1. Leads register — `/leads` (board and list) | **Design problems observed** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 180 | 1. Leads register — `/leads` (board and list) | Two CSV buttons in different places depending on mode (header for the board, inside the register for the list) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 181 | 1. Leads register — `/leads` (board and list) | Quick find on the board silently switches to list mode. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 182 | 1. Leads register — `/leads` (board and list) | FY control means different things on board (carry-forward) and list (created-in-year). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 183 | 1. Leads register — `/leads` (board and list) | The "Need attention now" card ignores the FY that every other card states. | preserved — found in the final code |
| 184 | 1. Leads register — `/leads` (board and list) | Raw source codes in the list and CSV. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 185 | 1. Leads register — `/leads` (board and list) | The status filter has no visible control. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 186 | 1. Leads register — `/leads` (board and list) | The bulk preview exists but is unreachable. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 187 | 1. Leads register — `/leads` (board and list) | Inline styles everywhere (board cards are inline-styled `<a>`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 188 | 1. Leads register — `/leads` (board and list) | The "Lost" count is computed but not shown. | preserved — found in the final code |
| 189 | 1. Leads register — `/leads` (board and list) | The deal badge is not a link. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 190 | 1. Leads register — `/leads` (board and list) | No per-row quick actions (call / WhatsApp / log) on the list, even though `sx_contact_btns` exists. | preserved — found in the final code |
| 194 | 2. New lead — full form — `/lead-new` | **Source:** `views/ops/lead_form.php` (241 lines). Handler at `lib/leads.php:1632-1662`. Create logic is `lead | preserved — found in the final code |
| 195 | 2. New lead — full form — `/lead-new` | **Routes** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 196 | 2. New lead — full form — `/lead-new` | GET `/lead-new`. Prefill from `$_GET`, so any field name can be prefilled. `?company=` triggers the duplicate  | preserved — found in the final code |
| 197 | 2. New lead — full form — `/lead-new` | POST `/lead-new`. | preserved — found in the final code |
| 198 | 2. New lead — full form — `/lead-new` | Error: `form_stash('lead-new')`, flash, redirect back. Values are restored through `form_old()`; the banner "N | preserved — found in the final code |
| 199 | 2. New lead — full form — `/lead-new` | Success: redirect to `/lead?id=`, flashing "Lead L-YYYY-NNNN created". | preserved — found (some words are filled in at run time) |
| 200 | 2. New lead — full form — `/lead-new` | **Purpose:** a complete lead intake. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 201 | 2. New lead — full form — `/lead-new` | **Fields** (name · type · rule; S = server-side check): | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 226 | 2. New lead — full form — `/lead-new` | **Accepted by `lead_create` but with no input on this form:** `source_note`, `sbu`, `stage_id`. | preserved — found in the final code |
| 227 | 2. New lead — full form — `/lead-new` | **Buttons:** `Create it` (submit), `Cancel` → `/leads`, "Masters" link (new tab) in the next-action help text. | preserved — found in the final code |
| 228 | 2. New lead — full form — `/lead-new` | **JS:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 229 | 2. New lead — full form — `/lead-new` | Picking a customer fills the name box and makes it read-only. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 230 | 2. New lead — full form — `/lead-new` | Picking a customer pulls the primary contact from `/partner-contact?id=`. It never overwrites typed values (`d | preserved — found (some words are filled in at run time) |
| 231 | 2. New lead — full form — `/lead-new` | **Side effects on create:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 232 | 2. New lead — full form — `/lead-new` | ref from `lead_ref_next()` (`L-YYYY-NNNN`) | preserved — found (some words are filled in at run time) |
| 233 | 2. New lead — full form — `/lead-new` | `act_log` LEAD SYSTEM "created" | preserved — found in the final code |
| 234 | 2. New lead — full form — `/lead-new` | `ads_queue_lead` | preserved — found in the final code |
| 235 | 2. New lead — full form — `/lead-new` | default `stage_since` | preserved — found in the final code |
| 236 | 2. New lead — full form — `/lead-new` | **Permissions:** `leads_can_view` + `ops_require($canEdit)` (`:1600`). The office must be in scope. | preserved — found in the final code |
| 237 | 2. New lead — full form — `/lead-new` | **Duplicate warning panel** (`:12-26`): partner match → `/client?id=`; open lead → `/lead?id=`. It appears **o | preserved — found in the final code |
| 238 | 2. New lead — full form — `/lead-new` | **Phone/desktop:** `.form-grid` / `.ff` / `.ff-wide` / `.pair-2`; inputmode attributes set for e-mail, tel, de | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 239 | 2. New lead — full form — `/lead-new` | **Problems** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 240 | 2. New lead — full form — `/lead-new` | Long single page (5 sections + custom fields). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 241 | 2. New lead — full form — `/lead-new` | The duplicate check needs a URL parameter, so a typed name is never checked. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 242 | 2. New lead — full form — `/lead-new` | SBU cannot be set, although a later conversion requires it (the convert screen asks for it). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 243 | 2. New lead — full form — `/lead-new` | Source list differs from the inquiry source list (`INQUIRY_SOURCES`). | preserved — found in the final code |
| 247 | 3. Quick lead — `/sales-lead` | **Source** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 248 | 3. Quick lead — `/sales-lead` | View: `views/ops/sx_lead.php` (63 lines; uses `_sx_ui.php`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 249 | 3. Quick lead — `/sales-lead` | Handler: `ops_sales_more` case `sales-lead` (`lib/sales_more.php:457-477`) | preserved — found in the final code |
| 250 | 3. Quick lead — `/sales-lead` | Logic: `sx_lead_save()` `:137`, `sx_lead_dupes()` `:115` | preserved — found in the final code |
| 251 | 3. Quick lead — `/sales-lead` | **Routes** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 252 | 3. Quick lead — `/sales-lead` | GET `/sales-lead` | preserved — found in the final code |
| 253 | 3. Quick lead — `/sales-lead` | POST `/sales-lead`: | preserved — found in the final code |
| 254 | 3. Quick lead — `/sales-lead` | success → `/lead?id=`, or back to `/sales-lead` if `another=1` | preserved — found in the final code |
| 255 | 3. Quick lead — `/sales-lead` | duplicates found → page re-renders with the dupes list, hidden `confirm=1`, and buttons renamed "Save anyway" | preserved — found in the final code |
| 256 | 3. Quick lead — `/sales-lead` | error → re-render with the `sxerr` banner | preserved — found in the final code |
| 257 | 3. Quick lead — `/sales-lead` | **Permissions:** `sx_can()` (`:453`); `leads_can_edit()` (`:458`). The owner picker shows only for `sx_can_see | preserved — found in the final code |
| 258 | 3. Quick lead — `/sales-lead` | **Fields** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 273 | 3. Quick lead — `/sales-lead` | **Hard-coded defaults:** `next_action` = "First call" (`:160`). | preserved — found in the final code |
| 274 | 3. Quick lead — `/sales-lead` | **Duplicate check** `sx_lead_dupes`: same name (partner or open lead), same last-10-digit mobile on open leads | preserved — found in the final code |
| 275 | 3. Quick lead — `/sales-lead` | **Buttons:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 276 | 3. Quick lead — `/sales-lead` | `Save lead` / `Save anyway` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 277 | 3. Quick lead — `/sales-lead` | `Save & add another` (`another=1`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 278 | 3. Quick lead — `/sales-lead` | `Cancel` → `/sales-today` | preserved — found in the final code |
| 279 | 3. Quick lead — `/sales-lead` | Chip "Full lead form ›" → `/lead-new`. **[WIP]** JS carries the typed values as a query string and warns that  | preserved — found in the final code |
| 280 | 3. Quick lead — `/sales-lead` | **Phone/desktop:** `.sx .sxform` auto-fit grid (min 220px) and `.sxactions`; chips have a 34px minimum height. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 281 | 3. Quick lead — `/sales-lead` | **Problems** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 282 | 3. Quick lead — `/sales-lead` | A second create path with different validation from `/lead-new`: the phone ≥10-digit rule and the duplicate ch | preserved — found in the final code |
| 283 | 3. Quick lead — `/sales-lead` | Different visual language (`.sx*` classes, own CSS colour tokens) from the rest of the lead screens. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 284 | 3. Quick lead — `/sales-lead` | The tile "New lead" opens this form while the register's "+ New lead" opens the full form. | preserved — found in the final code |
| 288 | 4. Lead details — `/lead?id=` (HEAD version,  | **Source:** `git show HEAD:phpapp/views/ops/lead_detail.php`. Handler `route === 'lead'` at `lib/leads.php:156 | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 289 | 4. Lead details — `/lead?id=` (HEAD version,  | **Data passed:** `l` (`lead_row`), `score`, `history` (`lead_stage_history`), `stages`, `timeline` (`act_for_e | preserved — found in the final code |
| 290 | 4. Lead details — `/lead?id=` (HEAD version,  | **Unused in HEAD view:** `clients`, `methods`, `today`. | preserved — found in the final code |
| 291 | 4. Lead details — `/lead?id=` (HEAD version,  | **`canQuote`** = licence `sales` ∧ (`crm.quote.create` ∨ `mod.quotes.edit` ∨ master(quotes)). | preserved — found in the final code |
| 297 | 4. Lead details — `/lead?id=` (HEAD version,  | H1 company; sub-line: ref, status pill (`LEAD_STATUS`), stage pill. | preserved — found in the final code |
| 298 | 4. Lead details — `/lead?id=` (HEAD version,  | **Delete** button (small, danger) → POST `/lead-delete`. Shown to `is_admin_level() // is_master()` and not co | preserved — found in the final code |
| 300 | 4. Lead details — `/lead?id=` (HEAD version,  | Shows who asked, to whom, the reason, and up to 4 approver names. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 301 | 4. Lead details — `/lead?id=` (HEAD version,  | If `canDecide` (manager over the lead, and not the requester unless master): form POST `/approval-lead-decide` | preserved — found in the final code |
| 303 | 4. Lead details — `/lead?id=` (HEAD version,  | **Converted:** "🎉 Won". Links: customer `/client?id=converted_partner_id`; accepted quote `/quote?id=`; "the i | preserved — found (some words are filled in at run time) |
| 304 | 4. Lead details — `/lead?id=` (HEAD version,  | **LOST:** reason label plus note. If `leads_can_reopen()`: `<details>` "Reopen this lead" → POST `/lead-reopen | preserved — found in the final code |
| 305 | 4. Lead details — `/lead?id=` (HEAD version,  | **OPEN:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 306 | 4. Lead details — `/lead?id=` (HEAD version,  | 3-step tracker `.lead-steps` (Talk to them → Send a quote → Their answer), computed from manual contacts and t | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 307 | 4. Lead details — `/lead?id=` (HEAD version,  | A plain sentence about the newest quote (`$plainQ` map of quote statuses). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 308 | 4. Lead details — `/lead?id=` (HEAD version,  | Next step plus date; stalled pill. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 309 | 4. Lead details — `/lead?id=` (HEAD version,  | If `canEdit`, CTA row: | preserved — found in the final code |
| 310 | 4. Lead details — `/lead?id=` (HEAD version,  | quote exists and not lost/expired → `Open quote X →` (`/quote?id=`) + `Log a contact` (`#log`, `data-tab-go="C | preserved — found in the final code |
| 311 | 4. Lead details — `/lead?id=` (HEAD version,  | nothing logged → `📞 Log your first contact` + `Make a quote` (`/quote-new?lead=id`, if canQuote) | preserved — found in the final code |
| 312 | 4. Lead details — `/lead?id=` (HEAD version,  | otherwise → `Make the quote →` + `Log a contact` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 313 | 4. Lead details — `/lead?id=` (HEAD version,  | plus `Open the deal` if a deal exists | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 314 | 4. Lead details — `/lead?id=` (HEAD version,  | plus link "More — change stage, mark lost…" (`#more-actions`, `data-tab-go="Actions"`) | preserved — found (some words are filled in at run time) |
| 315 | 4. Lead details — `/lead?id=` (HEAD version,  | Note: "closes by itself as won when they accept". | preserved — checked by hand |
| 317 | 4. Lead details — `/lead?id=` (HEAD version,  | **Overview** (`:139-167`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 318 | 4. Lead details — `/lead?id=` (HEAD version,  | Label/value `table.grid`: Who to talk to, Telephone, E-mail, Best reached by (`LEAD_PREF_CONTACT`), Worth abou | preserved — found in the final code |
| 319 | 4. Lead details — `/lead?id=` (HEAD version,  | "What they want" requirement. | preserved — found in the final code |
| 320 | 4. Lead details — `/lead?id=` (HEAD version,  | Manpower deputation table: type, how many, site, skills. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 321 | 4. Lead details — `/lead?id=` (HEAD version,  | Fold "How promising is it? score N/100" with the rule reasons (`:383-391`, `data-tab="Overview"`). | preserved — found (some words are filled in at run time) |
| 322 | 4. Lead details — `/lead?id=` (HEAD version,  | **Contacts** (`:169-231`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 323 | 4. Lead details — `/lead?id=` (HEAD version,  | Count, plus `_log_form.php` (§13) when `canEdit && open`. It is open by default if there are no contacts or `? | preserved — found in the final code |
| 324 | 4. Lead details — `/lead?id=` (HEAD version,  | Contact cards `.ct`: method icon and label, direction, with whom, outcome pill, date, owner, note, time spent  | preserved — found in the final code |
| 325 | 4. Lead details — `/lead?id=` (HEAD version,  | **Quotes** (`:233-262`), shown if canQuote or quotes exist. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 326 | 4. Lead details — `/lead?id=` (HEAD version,  | `+ Make a quote` → `/quote-new?lead=`, if canQuote and not LOST. | preserved — found in the final code |
| 327 | 4. Lead details — `/lead?id=` (HEAD version,  | `+ Project costing` → `/project-costings?lead_id=`, if `pc_can_edit()`. | preserved — found in the final code |
| 328 | 4. Lead details — `/lead?id=` (HEAD version,  | Table: Quotation (link, rev), Status (**raw status code pill**), Value, Made. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 329 | 4. Lead details — `/lead?id=` (HEAD version,  | **Documents** (`:264-299`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 330 | 4. Lead details — `/lead?id=` (HEAD version,  | Table: file link `/lead-file?id=`, kind label, note, added date/by. | preserved — found in the final code |
| 331 | 4. Lead details — `/lead?id=` (HEAD version,  | `✕` remove → POST `/lead-file-delete?id=` (confirm), if canEdit. | preserved — found in the final code |
| 332 | 4. Lead details — `/lead?id=` (HEAD version,  | Upload form POST `/lead-files?id=` (multipart): `files[]` required multiple (8 MB), `kind`, `note`. Button `At | preserved — found in the final code |
| 333 | 4. Lead details — `/lead?id=` (HEAD version,  | **Actions** (`:301-367`), shown if `canEdit && open`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 334 | 4. Lead details — `/lead?id=` (HEAD version,  | "Mark how far it has got": one button per other OPEN stage → POST `/lead-move` (`id`, `stage_id`). | preserved — found in the final code |
| 335 | 4. Lead details — `/lead?id=` (HEAD version,  | "They said yes without a quote": one button per WON stage → `/lead-move`. The handler answers `convert` and re | preserved — found (some words are filled in at run time) |
| 336 | 4. Lead details — `/lead?id=` (HEAD version,  | "It did not happen": hidden behind `lead_can_mark_lost`, with a message naming the owner. Per LOST stage, a fo | preserved — found in the final code |
| 337 | 4. Lead details — `/lead?id=` (HEAD version,  | "Work it as a deal" (if no deal and `opp_can_edit()`) → POST `/opportunity-from-lead` with `lead_id`. | preserved — found in the final code |
| 338 | 4. Lead details — `/lead?id=` (HEAD version,  | Fold "Edit the details" (`:393-453`, `data-tab="Actions"`, canEdit && open) → POST `/lead-edit`. | preserved — found in the final code |
| 339 | 4. Lead details — `/lead?id=` (HEAD version,  | Fields: `company_name`, `contact_name`, `source` (**free-text input showing the raw code**, not the select), ` | preserved — found in the final code |
| 340 | 4. Lead details — `/lead?id=` (HEAD version,  | **No deputation fields.** The handler keeps the stored values when those keys are absent (`:1720-1721`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 341 | 4. Lead details — `/lead?id=` (HEAD version,  | Fold "Remove this lead" (`:498-514`), shown if `can('mod.leads.edit') // is_master()`. When converted it shows | preserved — found in the final code |
| 342 | 4. Lead details — `/lead?id=` (HEAD version,  | **History** (fold, open, `:455-496`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 343 | 4. Lead details — `/lead?id=` (HEAD version,  | "What's happened": table When / What (ACT_KINDS pill, direction, subject, outcome) / Notes (body, time, next)  | changed by owner decision |
| 344 | 4. Lead details — `/lead?id=` (HEAD version,  | "How it moved": table When / From / To / Days there / Who. | preserved — found in the final code |
| 349 | 4. Lead details — `/lead?id=` (HEAD version,  | `GET /lead-file?id=` (`:1602`): view right only; streams base64 from `lead_files`. | preserved — found in the final code |
| 350 | 4. Lead details — `/lead?id=` (HEAD version,  | `POST /lead-files?id=` (`:1615`): `lead_attach_uploads`; act_log FILE. | preserved — found in the final code |
| 351 | 4. Lead details — `/lead?id=` (HEAD version,  | `POST /lead-file-delete?id=` (`:1622`): deletes the row; act_log "File removed". | preserved — found in the final code |
| 352 | 4. Lead details — `/lead?id=` (HEAD version,  | `POST /lead-move` (`:1664`) → `lead_move()` `:872`: | preserved — found in the final code |
| 353 | 4. Lead details — `/lead?id=` (HEAD version,  | refuses if not OPEN, wrong pipeline, or same stage | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 354 | 4. Lead details — `/lead?id=` (HEAD version,  | WON → `convert` redirect | preserved — found in the final code |
| 355 | 4. Lead details — `/lead?id=` (HEAD version,  | LOST → `lead_lost_problem` (owner only, reason on list, remarks required) | preserved — found in the final code |
| 356 | 4. Lead details — `/lead?id=` (HEAD version,  | writes `lead_stage_history`, act_log, `ads_queue_lead` | preserved — found in the final code |
| 357 | 4. Lead details — `/lead?id=` (HEAD version,  | `POST /lead-reopen` (`:1675`) → `lead_reopen()` `:227`: LOST only, reason required, returns to the stage it le | preserved — found in the final code |
| 358 | 4. Lead details — `/lead?id=` (HEAD version,  | `POST /lead-edit` (`:1703-1771`) | preserved — found in the final code |
| 359 | 4. Lead details — `/lead?id=` (HEAD version,  | checks: company required; `lead_fields_err`; MANMONTH site and count; owner active | preserved — found in the final code |
| 360 | 4. Lead details — `/lead?id=` (HEAD version,  | a refusal → **[WIP]** `form_stash('lead')` + redirect to `/lead?id=&edit=1#lead=details`. HEAD redirected to ` | preserved — found in the final code |
| 361 | 4. Lead details — `/lead?id=` (HEAD version,  | updates the fields; `ads_queue_lead` | preserved — found in the final code |
| 362 | 4. Lead details — `/lead?id=` (HEAD version,  | owner change through `lead_change_owner` (direct if allocating or a manager; otherwise creates a `lead_reassig | preserved — found in the final code |
| 363 | 4. Lead details — `/lead?id=` (HEAD version,  | `POST /lead-contact` (`:1773`): **retired** (no UI); still validates and calls `lead_log_contact`. | preserved — found (some words are filled in at run time) |
| 364 | 4. Lead details — `/lead?id=` (HEAD version,  | `POST /lead-delete` (`:1797`) | preserved — found in the final code |
| 365 | 4. Lead details — `/lead?id=` (HEAD version,  | needs `mod.leads.edit` ∨ master, plus the module-gate verb `mod.leads.delete` | preserved — found (some words are filled in at run time) |
| 366 | 4. Lead details — `/lead?id=` (HEAD version,  | refuses if CONVERTED or linked to a partner/inquiry | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 367 | 4. Lead details — `/lead?id=` (HEAD version,  | act_log DELETED, then row delete | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 368 | 4. Lead details — `/lead?id=` (HEAD version,  | `POST /approval-lead-decide` (`lib/stagegate.php:425`) → `lead_reassign_decide()` `:353`. Back is limited to ` | preserved — found in the final code |
| 375 | 4. Lead details — `/lead?id=` (HEAD version,  | `data-tabs` becomes a `.tabbar` (buttons wrap; `.tabbtn` min 44px on phone). | preserved — found in the final code |
| 376 | 4. Lead details — `/lead?id=` (HEAD version,  | `table.grid` label/value is auto-converted to `rt-kv` cards (app.js :1756-1767). | preserved — found in the final code |
| 377 | 4. Lead details — `/lead?id=` (HEAD version,  | `.nowband .cta` wraps. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 378 | 4. Lead details — `/lead?id=` (HEAD version,  | `.lead-steps` pills wrap. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 379 | 4. Lead details — `/lead?id=` (HEAD version,  | Contact cards are phone-friendly. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 382 | 4. Lead details — `/lead?id=` (HEAD version,  | Two Delete controls with **different visibility rules**: the header uses admin-level; the fold uses `mod.leads | preserved — found in the final code |
| 383 | 4. Lead details — `/lead?id=` (HEAD version,  | The edit form's source is a raw free-text code. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 384 | 4. Lead details — `/lead?id=` (HEAD version,  | The edit form lacks deputation and SBU. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 385 | 4. Lead details — `/lead?id=` (HEAD version,  | Edit lives inside the "Actions" tab behind a fold. | preserved — found in the final code |
| 386 | 4. Lead details — `/lead?id=` (HEAD version,  | Stage buttons, Won, Lost and "work as a deal" sit in one long Actions panel. | preserved — found in the final code |
| 387 | 4. Lead details — `/lead?id=` (HEAD version,  | Raw quote status codes. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 388 | 4. Lead details — `/lead?id=` (HEAD version,  | The converted state links to the inquiry register instead of the inquiry. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 389 | 4. Lead details — `/lead?id=` (HEAD version,  | The deal link is missing on converted leads. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 390 | 4. Lead details — `/lead?id=` (HEAD version,  | The CTA mixes a big `.btn` with `.btn small secondary` and a muted link. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 391 | 4. Lead details — `/lead?id=` (HEAD version,  | History shows auto entries and contacts together while Contacts shows contacts only (deliberate, but duplicate | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 392 | 4. Lead details — `/lead?id=` (HEAD version,  | Many inline styles; inline `<style>` blocks for `.lead-steps` and `.ct`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 396 | 5. Lead conversion — `/lead-convert` | **Source:** `views/ops/lead_convert.php` (52 lines). Handler `lib/leads.php:1682-1701`. Logic `lead_convert()` | preserved — found in the final code |
| 397 | 5. Lead conversion — `/lead-convert` | **Routes:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 398 | 5. Lead conversion — `/lead-convert` | GET `/lead-convert?id=&stage_id=`, reached from `/lead-move` to a WON stage. | preserved — found in the final code |
| 399 | 5. Lead conversion — `/lead-convert` | POST `/lead-convert` → back to `/lead?id=` with flash "Done — X is now a customer, and an inquiry is raised…". | preserved — found in the final code |
| 400 | 5. Lead conversion — `/lead-convert` | **Permissions:** leads_can_view + `$canEdit`; `lead_scope_gate`. | preserved — found in the final code |
| 401 | 5. Lead conversion — `/lead-convert` | **Fields** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 402 | 5. Lead conversion — `/lead-convert` | hidden `id`, `stage_id` | preserved — found in the final code |
| 403 | 5. Lead conversion — `/lead-convert` | `partner_id` (select of clients in `partner_office_sql()`, LIMIT 500, "— create a new one —", "+ Add new" quic | preserved — found in the final code |
| 404 | 5. Lead conversion — `/lead-convert` | `company_name` (200) | preserved — found in the final code |
| 405 | 5. Lead conversion — `/lead-convert` | `state` (60) | preserved — found in the final code |
| 406 | 5. Lead conversion — `/lead-convert` | `sbu` (required select, only if the lead has no SBU) | preserved — found in the final code |
| 407 | 5. Lead conversion — `/lead-convert` | Read-only "What comes across" table: contact, e-mail, telephone, source (raw code), value, owner, requirement. | preserved — found in the final code |
| 408 | 5. Lead conversion — `/lead-convert` | **Server rules** (`lead_convert`) | preserved — found in the final code |
| 409 | 5. Lead conversion — `/lead-convert` | Refuses CONVERTED and LOST. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 410 | 5. Lead conversion — `/lead-convert` | Claim lock (`converted_at`, 2-minute window). | preserved — found in the final code |
| 411 | 5. Lead conversion — `/lead-convert` | `partner_find_or_problem`: an EXACT match blocks; a POSSIBLE match is allowed and noted in the partner audit. | preserved — found in the final code |
| 412 | 5. Lead conversion — `/lead-convert` | Creates `business_partners` (code `gen_partner_code`, `home_branch_id` = lead office) and `partner_contacts` ( | preserved — found in the final code |
| 413 | 5. Lead conversion — `/lead-convert` | Creates `crm_inquiries`: subject = requirement (max 180); assignee from `lead_enquiry_assignee` `:1133`; `lead | preserved — found in the final code |
| 414 | 5. Lead conversion — `/lead-convert` | Sets the lead CONVERTED on the WON stage; back-fills `opportunities.partner_id`; `ads_queue_*`; history; act_l | preserved — checked by hand |
| 415 | 5. Lead conversion — `/lead-convert` | **Buttons:** `Yes — make them a customer` (submit), `Not yet` → `/lead?id=`. | preserved — found in the final code |
| 416 | 5. Lead conversion — `/lead-convert` | **Duplicate panel:** shown when `lead_possible_duplicate` returns a partner. | preserved — found in the final code |
| 417 | 5. Lead conversion — `/lead-convert` | **Phone/desktop:** `.form-grid`; max-width 820; `table.grid` → kv cards. | preserved — found in the final code |
| 418 | 5. Lead conversion — `/lead-convert` | **Problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 419 | 5. Lead conversion — `/lead-convert` | Raw source code shown. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 420 | 5. Lead conversion — `/lead-convert` | "+ Add new" and "create a new one" are two ways to create a customer. | preserved — found in the final code |
| 421 | 5. Lead conversion — `/lead-convert` | The partner list is capped at 500 and is not searchable (no `searchable` class). | preserved — found in the final code |
| 422 | 5. Lead conversion — `/lead-convert` | Reachable only through a WON stage button, which is hidden in the Actions tab. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 426 | 6. Inquiries register — `/inquiries` | **Source:** `views/ops/crm/inquiry_list.php` (102 lines). Handler `ops_crm_inquiries` `lib/crm.php:2115-2151`. | preserved — found in the final code |
| 427 | 6. Inquiries register — `/inquiries` | **Route:** GET `/inquiries` only. No POST, no CSV, no pagination, no column chooser. | preserved — found in the final code |
| 428 | 6. Inquiries register — `/inquiries` | **Permissions:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 429 | 6. Inquiries register — `/inquiries` | module gate `mod.inquiries.view` | preserved — found in the final code |
| 430 | 6. Inquiries register — `/inquiries` | SBU scope (`scope_sbus()`; rows with blank SBU always visible) | preserved — found in the final code |
| 431 | 6. Inquiries register — `/inquiries` | office scope `scope_office_clause('i.office_id')` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 432 | 6. Inquiries register — `/inquiries` | FY window on `created_at` | preserved — found in the final code |
| 433 | 6. Inquiries register — `/inquiries` | **Side effect on GET:** `crm_inquiries_sync_all()` (`:1193`) recomputes the status from quotations. | preserved — found in the final code |
| 434 | 6. Inquiries register — `/inquiries` | **Header:** title `T_REG('inquiry')`, "N shown", `+ New inquiry` → `/inquiry-new` (if `can('mod.inquiries.edit | preserved — found in the final code |
| 435 | 6. Inquiries register — `/inquiries` | **Banner:** due count `inquiries_due_count()` (OPEN past `inquiry_sla_days()` setting, default 7, `:2070`). Li | preserved — found in the final code |
| 436 | 6. Inquiries register — `/inquiries` | **Funnel KPI cards** (`.kpi-row`, links). Counts are taken before the status filter: | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 437 | 6. Inquiries register — `/inquiries` | Enquiries → `/inquiries` | preserved — found in the final code |
| 438 | 6. Inquiries register — `/inquiries` | Quoted → `st=QUOTED_ANY` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 439 | 6. Inquiries register — `/inquiries` | Won → `st=WON` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 440 | 6. Inquiries register — `/inquiries` | Lost · Dropped → `st=LOST_DROPPED`, with the hit rate | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 441 | 6. Inquiries register — `/inquiries` | Card links keep `q` and `fy`. | preserved — found in the final code |
| 442 | 6. Inquiries register — `/inquiries` | **Filter bar:** `q` (no. / subject / client), `st` (select of `crm_inquiry_status_opts()`, submits on change), | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 443 | 6. Inquiries register — `/inquiries` | **Table columns** (`:52`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 444 | 6. Inquiries register — `/inquiries` | Inquiry no. — plus "from lead L-…" → `/lead?id=` when `lead_id` is set | preserved — found (some words are filled in at run time) |
| 445 | 6. Inquiries register — `/inquiries` | Received (raw `Y-m-d`, **not `fdate`**) | preserved — found in the final code |
| 446 | 6. Inquiries register — `/inquiries` | Client | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 447 | 6. Inquiries register — `/inquiries` | Subject | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 448 | 6. Inquiries register — `/inquiries` | Status pill — plus "⏳ Nd" when past SLA; tooltip shows the drop reason | preserved — found in the final code |
| 449 | 6. Inquiries register — `/inquiries` | Assigned to (user, else the lead owner's name, else "Nobody yet") | preserved — found in the final code |
| 450 | 6. Inquiries register — `/inquiries` | Branch ("All branches" if none) | preserved — found in the final code |
| 451 | 6. Inquiries register — `/inquiries` | SBU label | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 452 | 6. Inquiries register — `/inquiries` | Source (label from `lk_options_or('inquiry_source')`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 453 | 6. Inquiries register — `/inquiries` | Actions | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 454 | 6. Inquiries register — `/inquiries` | **Row actions** (`:78-95`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 455 | 6. Inquiries register — `/inquiries` | `Edit` → `/inquiry-edit?id=` (if `mod.inquiries.edit`). Shown even when the person can only view because it is | preserved — found in the final code |
| 456 | 6. Inquiries register — `/inquiries` | Quote button by status (`$mayQuote` = `mod.quotes.edit` ∧ `crm_inquiry_can_work`): | preserved — found in the final code |
| 457 | 6. Inquiries register — `/inquiries` | OPEN → `Quote` (`/quote-new?inquiry=`) | preserved — found in the final code |
| 458 | 6. Inquiries register — `/inquiries` | QUOTED / WON → `Open Q-…` (`/quote?id=`, needs `mod.quotes.view`), plus for QUOTED `+ another` (confirm) | preserved — found in the final code |
| 459 | 6. Inquiries register — `/inquiries` | LOST → `Quote again` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 460 | 6. Inquiries register — `/inquiries` | DROPPED → nothing | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 461 | 6. Inquiries register — `/inquiries` | `Delete` (danger, confirm) → POST `/inquiry-delete`. Shown to admin-level/master when the status is not QUOTED | preserved — found in the final code |
| 462 | 6. Inquiries register — `/inquiries` | **N+1 note:** current quotes for all rows are fetched in one query inside the view (`:55-59`), i.e. SQL in a v | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 463 | 6. Inquiries register — `/inquiries` | **Tables:** `crm_inquiries`, `offices`, `business_partners`, `leads`, `users`, `quotations`. | preserved — found in the final code |
| 464 | 6. Inquiries register — `/inquiries` | **Phone/desktop:** `.tbl-scroll` with `overflow-x:auto`. `table.dt` → cards via `initResponsiveTables`. Ten co | preserved — found in the final code |
| 465 | 6. Inquiries register — `/inquiries` | **Problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 466 | 6. Inquiries register — `/inquiries` | No pagination: every row of the FY loads. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 467 | 6. Inquiries register — `/inquiries` | No export. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 468 | 6. Inquiries register — `/inquiries` | Inconsistent with the leads/deals registers (no `dt_render`, no column chooser, different KPI component `.kpi- | preserved — found in the final code |
| 469 | 6. Inquiries register — `/inquiries` | Edit shown to view-only users. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 470 | 6. Inquiries register — `/inquiries` | Raw date. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 471 | 6. Inquiries register — `/inquiries` | SQL in the view. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 475 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Source:** `views/ops/crm/inquiry_form.php` (112 lines). Handler `lib/crm.php:2153-2264`; drop/reopen `:2266- | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 476 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Routes:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 477 | 7. Inquiry form / detail — `/inquiry-new`, `/ | GET/POST `/inquiry-new` | preserved — found in the final code |
| 478 | 7. Inquiry form / detail — `/inquiry-new`, `/ | GET/POST `/inquiry-edit?id=` (GET syncs the status first, `:2157`) | preserved — found in the final code |
| 479 | 7. Inquiry form / detail — `/inquiry-new`, `/ | POST `/inquiry-drop`, `/inquiry-reopen` (`id`, `drop_reason`, `drop_note`) | preserved — found in the final code |
| 480 | 7. Inquiry form / detail — `/inquiry-new`, `/ | POST `/inquiry-delete` (`id`) | preserved — found in the final code |
| 481 | 7. Inquiry form / detail — `/inquiry-new`, `/ | The chain strip links INQUIRY → `/inquiry-edit?id=` (`lib/chain.php:45`). This is the de-facto inquiry detail. | preserved — found in the final code |
| 482 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Permissions:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 483 | 7. Inquiry form / detail — `/inquiry-new`, `/ | `can('mod.inquiries.edit')` is needed even to **open** the form or edit page (`:2154`). A view-only user there | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 484 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Then `crm_inquiry_in_scope`. | preserved — found in the final code |
| 485 | 7. Inquiry form / detail — `/inquiry-new`, `/ | On POST, `crm_inquiry_can_work` (assignee or their reporting chain, or master, `:1708-1722`). | preserved — found in the final code |
| 486 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Drop/reopen: `mod.inquiries.edit` + scope + can_work. | preserved — found in the final code |
| 487 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Delete: admin-level/master + scope + no quotations + module verb `mod.inquiries.delete`. | preserved — checked by hand |
| 488 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Header:** title "New client inquiry" or "Edit inquiry — INQ-…". Buttons: `＋ Project costing` → `/project-cos | preserved — found (some words are filled in at run time) |
| 489 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Banners:** error (`$error`); "Came from lead L-…" (`originRef`); duplicate-of (`dupOf`) with an `Open INQ-…` | preserved — found (some words are filled in at run time) |
| 490 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Fields** (all in `.form-grid`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 510 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Buttons:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 511 | 7. Inquiry form / detail — `/inquiry-new`, `/ | `Create inquiry` / `Save inquiry`. Replaced by a 🔒 message when the person cannot work it. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 512 | 7. Inquiry form / detail — `/inquiry-new`, `/ | `Cancel` → `/inquiries`. | preserved — found in the final code |
| 513 | 7. Inquiry form / detail — `/inquiry-new`, `/ | After save, both create and update **redirect to `/inquiries`**, not back to the record. | preserved — found in the final code |
| 514 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **"Where it stands" panel** (edit only, `:72-110`) | preserved — found in the final code |
| 515 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Status pill and a sentence. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 516 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Drop info (reason, note, by, date). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 517 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Linked current quotes (`/quote?id=`) with the status label. | preserved — found in the final code |
| 518 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Fold "Drop this inquiry": `drop_reason` (required, `INQUIRY_DROP_REASONS`) and `drop_note` (300). Button `Drop | preserved — found (some words are filled in at run time) |
| 519 | 7. Inquiry form / detail — `/inquiry-new`, `/ | `Reopen it` (if DROPPED). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 520 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Text "this follows the quote" if quotes exist. | preserved — checked by hand |
| 521 | 7. Inquiry form / detail — `/inquiry-new`, `/ | SQL in the view (`:75`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 522 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Activity timeline:** `act_render_timeline('INQUIRY',id)`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 523 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Missing from the inquiry screen:** a link to the customer record, a link to the deal, and a "raise a quote"  | not an item (audit observation) |
| 524 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Phone/desktop:** `.form-grid`; inline JS for the date warning; no phone specifics. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 525 | 7. Inquiry form / detail — `/inquiry-new`, `/ | **Problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 526 | 7. Inquiry form / detail — `/inquiry-new`, `/ | One screen is both "detail" and "edit form", and needs edit rights to open. | preserved — found in the final code |
| 527 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Save returns to the list. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 528 | 7. Inquiry form / detail — `/inquiry-new`, `/ | No quote button on the record. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 529 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Lead origin is not a link. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 530 | 7. Inquiry form / detail — `/inquiry-new`, `/ | The source list differs between form and list. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 531 | 7. Inquiry form / detail — `/inquiry-new`, `/ | Status is a fake input. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 535 | 8. Deals register — `/opportunities` (board a | **Source:** `views/ops/opportunities.php` (103 lines). Handler `lib/opportunities.php:1131-1164`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 536 | 8. Deals register — `/opportunities` (board a | **Routes:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 537 | 8. Deals register — `/opportunities` (board a | GET `/opportunities?v=board/list&pipeline=&fy=&q=&status=&sort=&dir=&per=&page=` | preserved — found in the final code |
| 538 | 8. Deals register — `/opportunities` (board a | `&export=csv` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 539 | 8. Deals register — `/opportunities` (board a | POST `/opportunity-delete` (`:1166`) | preserved — found in the final code |
| 540 | 8. Deals register — `/opportunities` (board a | **Permissions:** `opp_scope_gate`, `opp_can_view()`, the module gate `mod.leads.view`, and office scope in `op | preserved — found in the final code |
| 541 | 8. Deals register — `/opportunities` (board a | **Header:** H1 `THP('opportunity')` + a long explanation paragraph; `+ New deal` → `/opportunity-new` (canEdit | preserved — found in the final code |
| 542 | 8. Deals register — `/opportunities` (board a | **KPI cards** (`opp_counts()` `:259`, `opp_win_rate(365)` `:276`) | preserved — found in the final code |
| 543 | 8. Deals register — `/opportunities` (board a | Open; Pipeline estimate; Weighted forecast; Past stage SLA; Win rate over 12 months. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 544 | 8. Deals register — `/opportunities` (board a | **None is FY-filtered**, and they carry no FY tag. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 545 | 8. Deals register — `/opportunities` (board a | `won` and `lost` counts are computed but not shown. | preserved — found in the final code |
| 546 | 8. Deals register — `/opportunities` (board a | **Toolbar:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 547 | 8. Deals register — `/opportunities` (board a | Board/List toggle. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 548 | 8. Deals register — `/opportunities` (board a | FY select. The comment says "for the List (the Board shows the live pipeline regardless)" — the board **ignore | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 549 | 8. Deals register — `/opportunities` (board a | Quick find (switches to list). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 550 | 8. Deals register — `/opportunities` (board a | **No pipeline selector.** The handler honours `?pipeline=` (`:1157`), but only the "+N older" link uses it. | preserved — found in the final code |
| 551 | 8. Deals register — `/opportunities` (board a | **Board** (`opp_board()` `:309`) | preserved — found in the final code |
| 552 | 8. Deals register — `/opportunities` (board a | Every deal of the default (or `?pipeline=`) pipeline. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 553 | 8. Deals register — `/opportunities` (board a | WON / LOST columns windowed to `OPP_BOARD_DAYS=90`, with "+N older — show all T" → `/opportunities?v=list&stat | preserved — found (some words are filled in at run time) |
| 554 | 8. Deals register — `/opportunities` (board a | Column header: name, count, value, prob %, weighted. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 555 | 8. Deals register — `/opportunities` (board a | Card: name, client, ref + value, stalled-days pill. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 556 | 8. Deals register — `/opportunities` (board a | **Inline-styled flex columns (min/max 250px), not `.board` classes**, so the deal board **does not stack on ph | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 557 | 8. Deals register — `/opportunities` (board a | Empty column "—". | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 558 | 8. Deals register — `/opportunities` (board a | **"Why deals were lost — last 12 months"** panel (board only, `opp_loss_reasons(365)` `:294`): table Reason /  | preserved — found in the final code |
| 559 | 8. Deals register — `/opportunities` (board a | **List** (`dt_render`, `opp_dt_columns()` `:1080`) | preserved — found in the final code |
| 560 | 8. Deals register — `/opportunities` (board a | Columns; sort expression in brackets: | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 561 | 8. Deals register — `/opportunities` (board a | Ref [`o.ref`] | preserved — found in the final code |
| 562 | 8. Deals register — `/opportunities` (board a | Deal name + client [`o.name`] | preserved — found in the final code |
| 563 | 8. Deals register — `/opportunities` (board a | Stage [`s.seq`] | preserved — found in the final code |
| 564 | 8. Deals register — `/opportunities` (board a | Estimate [`o.value`] | preserved — found in the final code |
| 565 | 8. Deals register — `/opportunities` (board a | Weighted (not sortable) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 566 | 8. Deals register — `/opportunities` (board a | %* [`o.probability`] | preserved — found in the final code |
| 567 | 8. Deals register — `/opportunities` (board a | Expected close [`o.expected_close`] | preserved — found in the final code |
| 568 | 8. Deals register — `/opportunities` (board a | Owner [`o.owner_name`] | preserved — found in the final code |
| 569 | 8. Deals register — `/opportunities` (board a | Branch* [`f.name`] | preserved — found in the final code |
| 570 | 8. Deals register — `/opportunities` (board a | Against* [`o.competitor`] | preserved — found in the final code |
| 571 | 8. Deals register — `/opportunities` (board a | In stage [`o.stage_since`] + late pill | preserved — found in the final code |
| 572 | 8. Deals register — `/opportunities` (board a | Status [`o.status`] | preserved — found in the final code |
| 573 | 8. Deals register — `/opportunities` (board a | Search `q` (ref / name / partner name / contact). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 574 | 8. Deals register — `/opportunities` (board a | **No bulk actions.** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 575 | 8. Deals register — `/opportunities` (board a | `status` and `pipeline` filters honoured but with no UI. | preserved — found in the final code |
| 576 | 8. Deals register — `/opportunities` (board a | **CSV:** Ref, Deal, Customer, Stage, Status, Estimate, Weighted, %, Expected close, Owner, Branch, Against, Da | preserved — found in the final code |
| 577 | 8. Deals register — `/opportunities` (board a | **Delete** (`:1166-1182`): admin-level/master; refused if WON or linked quotes (`opportunity_quotes`); deletes | preserved — found in the final code |
| 578 | 8. Deals register — `/opportunities` (board a | **Tables:** `opportunities`, `pipeline_stages`, `pipelines`, `offices`, `business_partners`, `opportunity_quot | preserved — found in the final code |
| 579 | 8. Deals register — `/opportunities` (board a | **Phone/desktop:** list → `.dt-scroll` + responsive cards; board → sideways scroll (inline styles); KPI cards  | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 580 | 8. Deals register — `/opportunities` (board a | **Problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 581 | 8. Deals register — `/opportunities` (board a | Board markup duplicated from the leads board but diverged (inline styles, no stacking, no score, no carried-fo | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 582 | 8. Deals register — `/opportunities` (board a | FY control is inert on the board. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 583 | 8. Deals register — `/opportunities` (board a | No pipeline picker. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 584 | 8. Deals register — `/opportunities` (board a | KPIs not year-scoped. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 585 | 8. Deals register — `/opportunities` (board a | A second deals board exists at `/sales-board` (`views/ops/sx_board.php`; open leads column plus the open deal  | preserved — found in the final code |
| 589 | 9. Deal form — `/opportunity-new` | **Source:** `views/ops/opportunity_form.php` (74 lines). Handler `lib/opportunities.php:1277-1300`. Logic `opp | preserved — found in the final code |
| 590 | 9. Deal form — `/opportunity-new` | **Routes:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 591 | 9. Deal form — `/opportunity-new` | GET (prefill from `$_GET`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 592 | 9. Deal form — `/opportunity-new` | POST: success → `/opportunity?id=`; error → re-render with `form_err` and the typed values. It does not redire | preserved — found in the final code |
| 593 | 9. Deal form — `/opportunity-new` | **Permissions:** `opp_can_view` + `ops_require($canEdit)` (`:1274`). The office must be in scope. | preserved — found in the final code |
| 594 | 9. Deal form — `/opportunity-new` | **Fields** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 613 | 9. Deal form — `/opportunity-new` | **Accepted by the server, with no input:** `lead_id`, `inquiry_id`, `stage_id`, `probability`, `owner_name`, ` | preserved — found in the final code |
| 614 | 9. Deal form — `/opportunity-new` | **Owner is always the current user** (`owner_user_id` = me). | preserved — found in the final code |
| 615 | 9. Deal form — `/opportunity-new` | **Buttons:** `Open the deal`, `Cancel` → `/opportunities`. Custom fields render **after** the buttons (`:54-56 | preserved — found in the final code |
| 616 | 9. Deal form — `/opportunity-new` | **JS:** picking a customer pulls the contact (same code as the lead form, duplicated). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 617 | 9. Deal form — `/opportunity-new` | **Problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 618 | 9. Deal form — `/opportunity-new` | Help text "A name-only deal can't be quoted until you link a real customer" contradicts `opp_create_quote()` ( | preserved — found in the final code |
| 619 | 9. Deal form — `/opportunity-new` | Weaker validation than the lead form. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 620 | 9. Deal form — `/opportunity-new` | No owner / SBU pick. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 621 | 9. Deal form — `/opportunity-new` | Custom fields after the submit buttons. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 625 | 10. Deal details — `/opportunity?id=` | **Source:** `views/ops/opportunity_detail.php` (554 lines). Handler `lib/opportunities.php:1184-1250`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 626 | 10. Deal details — `/opportunity?id=` | **Data passed:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 627 | 10. Deal details — `/opportunity?id=` | `o`, `quotes` (`opp_quotes` `:214`: explicit links plus quotes on the same lead), `history`, `stages` | preserved — found in the final code |
| 628 | 10. Deal details — `/opportunity?id=` | `timeline` (**unused** — `act_render_timeline` refetches) | preserved — found in the final code |
| 629 | 10. Deal details — `/opportunity?id=` | `lostReasons`, `canEdit` | preserved — found in the final code |
| 630 | 10. Deal details — `/opportunity?id=` | `costing` / `costingUnlinked` / `costingCan` (project costing) | preserved — found in the final code |
| 631 | 10. Deal details — `/opportunity?id=` | `users`, `pipelines` (OPPORTUNITY only) | preserved — found in the final code |
| 632 | 10. Deal details — `/opportunity?id=` | `orderBlock`, `orderQuote`, `tradeBlock` | preserved — found in the final code |
| 633 | 10. Deal details — `/opportunity?id=` | `canOrder` (operations licence ∧ (`mod.calls.edit` ∨ master(calls))) | preserved — found in the final code |
| 634 | 10. Deal details — `/opportunity?id=` | `days`, `stalled` | preserved — found in the final code |
| 635 | 10. Deal details — `/opportunity?id=` | `gate`, `gateCanAct`, `gateWaiting` | preserved — found in the final code |
| 636 | 10. Deal details — `/opportunity?id=` | `offices`, `clients`, `effort` | preserved — found in the final code |
| 637 | 10. Deal details — `/opportunity?id=` | `openQuotes` (the client's other quotes) | preserved — found in the final code |
| 638 | 10. Deal details — `/opportunity?id=` | `canRegisterContract` (`crm.contract.register` ∨ master) | preserved — found in the final code |
| 639 | 10. Deal details — `/opportunity?id=` | `winQuote`, `quoteNow` | preserved — found in the final code |
| 640 | 10. Deal details — `/opportunity?id=` | `canAddCustomer` (leads_can_edit) | preserved — found in the final code |
| 641 | 10. Deal details — `/opportunity?id=` | `contractRow` | preserved — found in the final code |
| 647 | 10. Deal details — `/opportunity?id=` | H1 deal name; ref, status pill, customer, stage. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 648 | 10. Deal details — `/opportunity?id=` | The customer link goes to **`/ledger?id=`** (the finance ledger), not the customer page. | preserved — found in the final code |
| 649 | 10. Deal details — `/opportunity?id=` | `Delete` (admin-level/master, not WON, no quotes) → POST `/opportunity-delete` (confirm). | preserved — found in the final code |
| 652 | 10. Deal details — `/opportunity?id=` | "Held for approval" — to-stage, requester, date, waiting on, note. | preserved — found in the final code |
| 653 | 10. Deal details — `/opportunity?id=` | If `gateCanAct`: form POST `/approval-act` with `id`, `back`, `remarks` (required to send back), and buttons ` | preserved — found in the final code |
| 654 | 10. Deal details — `/opportunity?id=` | Else, for the requester or master: `Withdraw the request` (CANCELLED, confirm). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 655 | 10. Deal details — `/opportunity?id=` | `All approvals` → `/approvals`. | preserved — found in the final code |
| 657 | 10. Deal details — `/opportunity?id=` | **WON:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 658 | 10. Deal details — `/opportunity?id=` | work order link `/call?id=`, else `opp_won_next()` text | preserved — found in the final code |
| 659 | 10. Deal details — `/opportunity?id=` | "Reopen this deal" `<details>` → POST `/opportunity-reopen` (`reason` required), when `leads_can_reopen` and n | preserved — found in the final code |
| 660 | 10. Deal details — `/opportunity?id=` | **LOST:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 661 | 10. Deal details — `/opportunity?id=` | reason label, lost_to, note | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 662 | 10. Deal details — `/opportunity?id=` | Reopen (same form) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 663 | 10. Deal details — `/opportunity?id=` | **OPEN:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 664 | 10. Deal details — `/opportunity?id=` | stage sentence plus overdue pill | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 665 | 10. Deal details — `/opportunity?id=` | `quoteNow` sentence + `Open quote X →` | preserved — found in the final code |
| 666 | 10. Deal details — `/opportunity?id=` | Next action / date | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 667 | 10. Deal details — `/opportunity?id=` | CTA (canEdit): `Move it on` (`#move`, **no `data-tab-go`**, so the tab may not open), `See the lead` → `/lead? | preserved — found in the final code |
| 669 | 10. Deal details — `/opportunity?id=` | **Overview** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 670 | 10. Deal details — `/opportunity?id=` | `.kv-grid`: Worth about, Likely value (weighted, %), Hoped to close, Handled by, Branch, At this step for, Up  | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 671 | 10. Deal details — `/opportunity?id=` | "What they want". | preserved — found in the final code |
| 672 | 10. Deal details — `/opportunity?id=` | Project costing panel (if `costingCan`): | preserved — found in the final code |
| 673 | 10. Deal details — `/opportunity?id=` | linked → `/project-costing?id=` + revenue, plus `Unlink` (POST `/project-costing-attach` with `opportunity_id= | preserved — found in the final code |
| 674 | 10. Deal details — `/opportunity?id=` | unlinked → `＋ Create costing for this deal` (POST `/project-costing-new`) + "attach existing…" select + `Attac | preserved — found in the final code |
| 675 | 10. Deal details — `/opportunity?id=` | **Quotes** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 676 | 10. Deal details — `/opportunity?id=` | `＋ Create a new quotation` → POST `/opportunity-new-quote` (canEdit && open; disabled if there is no customer  | preserved — found in the final code |
| 677 | 10. Deal details — `/opportunity?id=` | Note that the customer is not on the master, plus fold "Already a customer? pick them" → POST `/opportunity-ed | preserved — found (some words are filled in at run time) |
| 678 | 10. Deal details — `/opportunity?id=` | Quotes table: link + rev, **raw status code**, value, `Detach` (POST `/opportunity-quote`, `act=unlink`; an ac | preserved — found in the final code |
| 679 | 10. Deal details — `/opportunity?id=` | "Attach a quotation" select `quotation_id` + `Attach` (POST `/opportunity-quote`). | preserved — found in the final code |
| 680 | 10. Deal details — `/opportunity?id=` | `＋ Generate quotation` → POST `/opportunity-generate-quote`; only when WON with a customer and no quotes. Land | preserved — found in the final code |
| 681 | 10. Deal details — `/opportunity?id=` | **Actions — WON section** (when operations is installed) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 682 | 10. Deal details — `/opportunity?id=` | call raised → success message + link | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 683 | 10. Deal details — `/opportunity?id=` | contract registered → status; `▶ Raise call` → `/call-new?contract_id=` (`ops.call.create` ∨ master) | preserved — found in the final code |
| 684 | 10. Deal details — `/opportunity?id=` | `orderBlock` message: | preserved — found in the final code |
| 685 | 10. Deal details — `/opportunity?id=` | "Convert the lead" link, if the deal has a lead | preserved — found in the final code |
| 686 | 10. Deal details — `/opportunity?id=` | add-customer form POST `/opportunity-add-customer` with `company_name`, `state`, and `confirm_new` (only when  | preserved — found in the final code |
| 687 | 10. Deal details — `/opportunity?id=` | "Or pick one already on the master" → `/opportunity-edit` with `partner_id` | preserved — found in the final code |
| 688 | 10. Deal details — `/opportunity?id=` | won through a quote → panel with the contract state + `Open quote …` (`/quote?id=#contract`) | preserved — found in the final code |
| 689 | 10. Deal details — `/opportunity?id=` | sent to Accounts → panel. If `canRegisterContract`, form POST `/opportunity-contract` with `branch_id`, `contr | preserved — found in the final code |
| 690 | 10. Deal details — `/opportunity?id=` | otherwise → `Send to Accounts to register the contract` (POST `/opportunity-send-to-accounts`) | preserved — found in the final code |
| 691 | 10. Deal details — `/opportunity?id=` | fold "Or raise a work order directly" (`canOrder`) → POST `/opportunity-raise-order` with `executing_office_id | preserved — found in the final code |
| 692 | 10. Deal details — `/opportunity?id=` | **Actions — Move** (canEdit && open, `#move`) | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 693 | 10. Deal details — `/opportunity?id=` | `stage_id` select (all stages with % and close hints) | preserved — found in the final code |
| 694 | 10. Deal details — `/opportunity?id=` | lost block shown by JS for LOST kind: `lost_reason` (required by the server), `lost_to`, `lost_note` | preserved — found in the final code |
| 695 | 10. Deal details — `/opportunity?id=` | `Move it` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 696 | 10. Deal details — `/opportunity?id=` | Server `opp_move` `:408`: stage must be in the same funnel and not the same stage; LOST needs a reason; gate m | preserved — found in the final code |
| 697 | 10. Deal details — `/opportunity?id=` | The form posts no `gate_note`, although the server reads one. | preserved — found in the final code |
| 698 | 10. Deal details — `/opportunity?id=` | **Actions — fold "Edit the details"** (canEdit && open) → POST `/opportunity-edit` | preserved — found in the final code |
| 699 | 10. Deal details — `/opportunity?id=` | Fields: `name`, `partner_id`, `value`, `expected_close`, `owner_user_id`, `pipeline_id` (open only; moves the  | preserved — found in the final code |
| 700 | 10. Deal details — `/opportunity?id=` | Server `opp_update` `:501`: absent keys keep their values; refuses a partner change after an order, a lead pip | preserved — found in the final code |
| 701 | 10. Deal details — `/opportunity?id=` | Not editable: source, SBU, probability. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 702 | 10. Deal details — `/opportunity?id=` | **History** fold (open): stage moves table. If there are none: "Opened by X on date". | preserved — checked by hand |
| 704 | 10. Deal details — `/opportunity?id=` | the log form `_log_form.php` (§13) in a panel, if `canEdit` — **also for WON/LOST deals** | preserved — found in the final code |
| 705 | 10. Deal details — `/opportunity?id=` | `act_render_timeline('OPPORTUNITY',id,'Activity')` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 708 | 10. Deal details — `/opportunity?id=` | `/opportunity-edit`, `/opportunity-reopen` (`opp_reopen` `:478`) | preserved — found in the final code |
| 709 | 10. Deal details — `/opportunity?id=` | `/opportunity-move` | preserved — found in the final code |
| 710 | 10. Deal details — `/opportunity?id=` | `/opportunity-new-quote` (`opp_create_quote` `:646`; trade-block check; DRAFT quote linked) | preserved — found in the final code |
| 711 | 10. Deal details — `/opportunity?id=` | `/opportunity-quote` (link / unlink `:607` / `:625`) | preserved — found in the final code |
| 712 | 10. Deal details — `/opportunity?id=` | `/opportunity-raise-order` (`opp_raise_order` `:829`) | preserved — found in the final code |
| 713 | 10. Deal details — `/opportunity?id=` | `/opportunity-send-to-accounts` (`:891`) | preserved — found in the final code |
| 714 | 10. Deal details — `/opportunity?id=` | `/opportunity-contract` (`opp_register_contract` `:911`; placed **before** the canEdit guard so Accounts can u | preserved — found in the final code |
| 715 | 10. Deal details — `/opportunity?id=` | `/opportunity-generate-quote` (`:1053`) | preserved — found in the final code |
| 716 | 10. Deal details — `/opportunity?id=` | `/opportunity-add-customer` (`:978`; redirects to `#opp=actions`) | preserved — found in the final code |
| 717 | 10. Deal details — `/opportunity?id=` | `/opportunity-from-lead` (`opp_from_lead` `:682`; lead-office scope; one deal per lead) | preserved — found in the final code |
| 728 | 10. Deal details — `/opportunity?id=` | The log form and timeline sit outside the tab system (the lead page has them inside Contacts / History). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 729 | 10. Deal details — `/opportunity?id=` | Two Reopen blocks duplicated. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 730 | 10. Deal details — `/opportunity?id=` | Customer link goes to the ledger, while the lead page uses `/client` and the quick-lead dupes use `/partner`. | preserved — found in the final code |
| 731 | 10. Deal details — `/opportunity?id=` | Raw quote status codes. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 732 | 10. Deal details — `/opportunity?id=` | "Move it on" does not switch tabs. | preserved — found in the final code |
| 733 | 10. Deal details — `/opportunity?id=` | Two "set the customer" forms (Quotes tab and WON block). | preserved — found in the final code |
| 734 | 10. Deal details — `/opportunity?id=` | The Actions tab holds 3–6 different panels depending on state. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 735 | 10. Deal details — `/opportunity?id=` | Missing `gate_note` input. | preserved — found in the final code |
| 736 | 10. Deal details — `/opportunity?id=` | `timeline` fetched twice. | preserved — found in the final code |
| 740 | 11. Pipelines & funnels — `/pipelines`, `/pip | **Source:** `views/ops/pipelines.php` (72 lines), `views/ops/pipeline_edit.php` (116 lines). Handler `ops_pipe | preserved — found in the final code |
| 741 | 11. Pipelines & funnels — `/pipelines`, `/pip | **Routes:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 742 | 11. Pipelines & funnels — `/pipelines`, `/pip | GET `/pipelines` | preserved — found in the final code |
| 743 | 11. Pipelines & funnels — `/pipelines`, `/pip | GET `/pipeline?id=` | preserved — found in the final code |
| 744 | 11. Pipelines & funnels — `/pipelines`, `/pip | POST `/pipeline-new` (`name`, `entity_kind`, `clone_from`, `make_default`) | preserved — found in the final code |
| 745 | 11. Pipelines & funnels — `/pipelines`, `/pip | POST `/pipeline-save` (`id`, `pipeline_name`, `pipe_active`, `order[sid]`, `name[sid]`, `kind[sid]`, `probabil | preserved — found in the final code |
| 746 | 11. Pipelines & funnels — `/pipelines`, `/pip | POST `/pipeline-stage-add` (`id`, `name`, `kind`, `probability`, `sla_days`) | preserved — found in the final code |
| 747 | 11. Pipelines & funnels — `/pipelines`, `/pip | POST `/pipeline-stage-delete` (`id`, `stage_id`) | preserved — found in the final code |
| 748 | 11. Pipelines & funnels — `/pipelines`, `/pip | POST `/pipeline-default` (`id`) | preserved — found in the final code |
| 749 | 11. Pipelines & funnels — `/pipelines`, `/pip | **Permissions:** view `pipe_can_view()` (+ module gate `mod.leads.view`); every POST `pipe_can()`. Inputs are  | preserved — found in the final code |
| 750 | 11. Pipelines & funnels — `/pipelines`, `/pip | **List screen** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 751 | 11. Pipelines & funnels — `/pipelines`, `/pip | Header buttons: `Approval rules for deal stages` → `/stage-gates` (`gate_can_view ∧ gate_can_manage`); `Start  | preserved — found in the final code |
| 752 | 11. Pipelines & funnels — `/pipelines`, `/pip | One panel per entity (Deal funnels, Lead pipelines). Table: Name (link), Stages, In use, Default (pill or `Mak | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 753 | 11. Pipelines & funnels — `/pipelines`, `/pip | "Create one" form: Name (required, 120); What is it for (`entity_kind`); Start from (`clone_from`, default = 4 | preserved — found in the final code |
| 754 | 11. Pipelines & funnels — `/pipelines`, `/pip | **Edit screen** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 755 | 11. Pipelines & funnels — `/pipelines`, `/pip | Problems banner (`pipe_problems` `:85`: more than one WON, first stage closed, no WON, no LOST, no OPEN). | preserved — found in the final code |
| 756 | 11. Pipelines & funnels — `/pipelines`, `/pip | Name, "In use". | preserved — found in the final code |
| 757 | 11. Pipelines & funnels — `/pipelines`, `/pip | Stage table: Order (number), Stage name, What it means (`PIPE_KINDS`), Probability (clamped, WON=100 / LOST=0) | preserved — found in the final code |
| 758 | 11. Pipelines & funnels — `/pipelines`, `/pip | `Save the funnel`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 759 | 11. Pipelines & funnels — `/pipelines`, `/pip | "Add a stage" form (defaults prob 50, SLA 14). | preserved — found in the final code |
| 760 | 11. Pipelines & funnels — `/pipelines`, `/pip | Links to the other pipelines of the same kind. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 761 | 11. Pipelines & funnels — `/pipelines`, `/pip | **Rules (server):** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 762 | 11. Pipelines & funnels — `/pipelines`, `/pip | The default cannot be retired. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 763 | 11. Pipelines & funnels — `/pipelines`, `/pip | A stage with records cannot be deleted. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 764 | 11. Pipelines & funnels — `/pipelines`, `/pip | The default needs no problems. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 765 | 11. Pipelines & funnels — `/pipelines`, `/pip | Re-sort by the Order box. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 766 | 11. Pipelines & funnels — `/pipelines`, `/pip | **Tables:** `pipelines`, `pipeline_stages`, `leads`, `opportunities`. | preserved — found in the final code |
| 767 | 11. Pipelines & funnels — `/pipelines`, `/pip | **Phone/desktop:** a wide 8-column editable table in `.dt-scroll`. It has more than one field column, so `init | preserved — found in the final code |
| 768 | 11. Pipelines & funnels — `/pipelines`, `/pip | **Problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 769 | 11. Pipelines & funnels — `/pipelines`, `/pip | No "← Pipelines" back link on the edit screen. | preserved — found in the final code |
| 770 | 11. Pipelines & funnels — `/pipelines`, `/pip | Raw kind codes in the clone select (entity labels are fine). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 771 | 11. Pipelines & funnels — `/pipelines`, `/pip | Changing stage order means typing numbers. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 772 | 11. Pipelines & funnels — `/pipelines`, `/pip | "In use" means two different things (pipeline active vs stage active vs the "In use" count column on the list) | preserved — found in the final code |
| 776 | 12. Stage gates (approval rules for deal stag | **Source:** `views/ops/stage_gates.php` (186 lines). Handler `ops_stage_gates` `lib/stagegate.php:374-410`. En | preserved — found in the final code |
| 777 | 12. Stage gates (approval rules for deal stag | **Routes:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 778 | 12. Stage gates (approval rules for deal stag | GET `/stage-gates[?new=1/?edit=id]` | preserved — found in the final code |
| 779 | 12. Stage gates (approval rules for deal stag | POST `/stage-gate-save` (`id`, `name`, `entity_kind`, `pipeline_id`, `stage_kind`, `stage_id`, `min_amount`, ` | preserved — found in the final code |
| 780 | 12. Stage gates (approval rules for deal stag | POST `/stage-gate-delete` (`id`) | preserved — found in the final code |
| 781 | 12. Stage gates (approval rules for deal stag | Decisions: POST `/approval-act` (`id`, `decision` = APPROVED / REJECTED / CANCELLED, `remarks`, `back`). This  | preserved — found in the final code |
| 782 | 12. Stage gates (approval rules for deal stag | **Permissions:** `gate_can_manage()` for the whole page. Note that `/stage-gate*` routes map to module `leads` | preserved — found in the final code |
| 783 | 12. Stage gates (approval rules for deal stag | **Form validation:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 784 | 12. Stage gates (approval rules for deal stag | name required | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 785 | 12. Stage gates (approval rules for deal stag | max ≥ min | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 786 | 12. Stage gates (approval rules for deal stag | stage or kind required | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 787 | 12. Stage gates (approval rules for deal stag | `entity_kind` fixed hidden OPPORTUNITY (the LEAD gate kind exists in the engine but is not offered) | preserved — found in the final code |
| 788 | 12. Stage gates (approval rules for deal stag | editing reroutes pending requests to the new approver (`gate_reroute_pending`) | preserved — found in the final code |
| 789 | 12. Stage gates (approval rules for deal stag | **List:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 790 | 12. Stage gates (approval rules for deal stag | Table: Rule (name, Off pill, sentence `gate_explain`), Value band, Guards ("Any won stage" — lower-cased raw k | preserved — found (some words are filled in at run time) |
| 791 | 12. Stage gates (approval rules for deal stag | Precedence note. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 792 | 12. Stage gates (approval rules for deal stag | Empty state with a `＋ Write the first rule` button. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 793 | 12. Stage gates (approval rules for deal stag | **Related:** `/approvals` (`views/ops/approvals.php`) lists gate requests (to act / to watch, filter `f=PENDIN | preserved — found in the final code |
| 794 | 12. Stage gates (approval rules for deal stag | **Problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 795 | 12. Stage gates (approval rules for deal stag | Raw role code in the Approver column (the sentence uses the `ORG_ROLES` label). | preserved — found in the final code |
| 796 | 12. Stage gates (approval rules for deal stag | The empty-state copy mentions "drags it", but no board supports drag. | changed by owner decision |
| 797 | 12. Stage gates (approval rules for deal stag | The page cannot be found from Sales tiles. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 801 | 13. Shared log form — `views/ops/_log_form.ph | **Included by:** lead detail Contacts tab (HEAD :192-201) and deal detail (below the tabs, `opportunity_detail | preserved — found in the final code |
| 802 | 13. Shared log form — `views/ops/_log_form.ph | **Inputs expected:** `$lf` = kind (LEAD / OPPORTUNITY), id, office, who, pref (lead), back, open; **[WIP]** `m | preserved — found in the final code |
| 803 | 13. Shared log form — `views/ops/_log_form.ph | **Form** POST `/sales-log` inside `<details class="ct-log lf" id="log">`, summary button "📝 Log a conversation | preserved — found in the final code |
| 804 | 13. Shared log form — `views/ops/_log_form.ph | hidden `lead` or `deal`; hidden `back` | preserved — found in the final code |
| 805 | 13. Shared log form — `views/ops/_log_form.ph | "1 · How": `method` radio pills (`LEAD_CONTACT_METHODS`: CALL, WHATSAPP, EMAIL, MEETING, VISIT, NOTE). Pre-sel | preserved — found in the final code |
| 806 | 13. Shared log form — `views/ops/_log_form.ph | "2 · What happened": `outcome` radio pills from `sx_outcomes()` (setting `sales_outcomes`, each with days → `d | preserved — found in the final code |
| 807 | 13. Shared log form — `views/ops/_log_form.ph | `note` (textarea), `with_whom` (prefilled with the contact), `next_action`, `next_action_on` (date, min today, | preserved — found in the final code |
| 808 | 13. Shared log form — `views/ops/_log_form.ph | fold "More details (optional)": `direction` (OUT / IN), `occurred_on` (date, max today, default today), `time_ | preserved — found in the final code |
| 809 | 13. Shared log form — `views/ops/_log_form.ph | Button `Save this contact`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 810 | 13. Shared log form — `views/ops/_log_form.ph | **Server** (`ops_sales_more` `sales-log` `lib/sales_more.php:479-498`; `sx_log_save` `:186`) | preserved — found in the final code |
| 811 | 13. Shared log form — `views/ops/_log_form.ph | Target via `sx_log_target()` `:169`; needs edit rights on the record (`leads_can_edit` / `opp_can_edit`) and o | preserved — found in the final code |
| 812 | 13. Shared log form — `views/ops/_log_form.ph | Outcome or note required. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 813 | 13. Shared log form — `views/ops/_log_form.ph | Dates must be real; next date not in the past; occurred date not in the future. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 814 | 13. Shared log form — `views/ops/_log_form.ph | Outcome with 0 days on an open record requires a date. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 815 | 13. Shared log form — `views/ops/_log_form.ph | Next action defaults to "Follow up". | preserved — found in the final code |
| 816 | 13. Shared log form — `views/ops/_log_form.ph | Lead → `lead_log_contact` (`lib/leads.php:722`); deal → `act_log` + `opportunities.next_action*` update. | preserved — found in the final code |
| 817 | 13. Shared log form — `views/ops/_log_form.ph | Redirect to the safe `back` (`sx_safe_back` `:241`). A GET goes to the record's form. | preserved — found in the final code |
| 818 | 13. Shared log form — `views/ops/_log_form.ph | **Phone:** `.lf-pick` pills min 40px; `scroll-margin-top:84px` for the sticky bar; auto-opens and scrolls on ` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 819 | 13. Shared log form — `views/ops/_log_form.ph | **Problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 820 | 13. Shared log form — `views/ops/_log_form.ph | The CSS lives in an inline `<style>` per include. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 821 | 13. Shared log form — `views/ops/_log_form.ph | Visit icon differs: 🚗 here, 📍 in `LEAD_CONTACT_ICONS` (`lib/leads.php:794`). | preserved — found in the final code |
| 822 | 13. Shared log form — `views/ops/_log_form.ph | On the deal page it shows for closed deals too. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 965 | 1. Quotation register | **Source:** `views/ops/crm/quote_list.php`; handler `lib/crm.php:2349-2410`; export `crm_quotes_export()` `lib | preserved — found in the final code |
| 966 | 1. Quotation register | **Routes:** GET `/quotes` (`?v=`, `?q=`, `?fy=`, `?export=1`, `?mine=approve`, `?mine=contract`). Route | preserved — found in the final code |
| 969 | 1. Quotation register | **Purpose:** the list of current-revision quotations with funnel counts. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 970 | 1. Quotation register | **Fields displayed (table, `quote_list.php:80-101`):** Quote (`quote_label` = no + "Rev NN", link), | preserved — found in the final code |
| 974 | 1. Quotation register | **Header area:** title `T_REG('quote')`; variant pills "Awaiting your approval" / "To register a | preserved — found in the final code |
| 977 | 1. Quotation register | **KPI cards (links to tabs):** Open, Pending (approval/reply), Closed (won), Lost, Expired | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 979 | 1. Quotation register | **Filters / search / sort / pagination / export:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 980 | 1. Quotation register | Tabs `v` = all / open / pending / closed / lost / expired (`quote_list.php:4`, `lib/crm.php:2385-2391`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 981 | 1. Quotation register | `q` searches `quote_no`, `subject`, `client_name` (LIKE) (`:2392-2393`). | preserved — found in the final code |
| 982 | 1. Quotation register | `fy` financial year on `created_at`, default current/pinned FY, `ALL` = all years (`fy_filter()` | preserved — found in the final code |
| 984 | 1. Quotation register | `mine=approve` — `crm_quote_ids_awaiting_me()` (`lib/crm.php:4536`); any office; counts card | preserved — found in the final code |
| 987 | 1. Quotation register | Sort: fixed `ORDER BY q.id DESC` (contract view: accepted_date). No column sort. No pagination | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 989 | 1. Quotation register | Export: `?export=1` → CSV of the same rows, 33 columns (no, rev, status, origin, client, contact | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 994 | 1. Quotation register | **Buttons / links (header, `quote_list.php:17-23`):** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 995 | 1. Quotation register | ⬇ Export → `/quotes?…&export=1` (everyone who sees the list). | preserved — found in the final code |
| 996 | 1. Quotation register | Approval rules → `/approval-rules?module=quote` — shown if `crm.quote.approve` or master. | preserved — found in the final code |
| 997 | 1. Quotation register | Templates → `/templates?kind=quote` — `crm.template.manage` or master. | preserved — found in the final code |
| 998 | 1. Quotation register | + External → `/quote-external` — `crm.quote.create` or `mod.quotes.edit`. | preserved — found in the final code |
| 999 | 1. Quotation register | + New quotation → `/quote-new` — `crm.quote.create` or `mod.quotes.edit`. | preserved — found in the final code |
| 1000 | 1. Quotation register | Row: quote label and "Open" → `/quote?id=`. | preserved — found in the final code |
| 1001 | 1. Quotation register | **Permission checks:** module gate `mod.quotes.view`; `scope_clause('q.office_id','q.sbu')` on list and | preserved — found in the final code |
| 1003 | 1. Quotation register | **Backend / tables:** `quotations` (is_current=1), `business_partners`, `offices`, `quote_approvals`, | preserved — found in the final code |
| 1005 | 1. Quotation register | **Links to other records:** quotation only (no lead/deal/costing column). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1006 | 1. Quotation register | **Phone vs desktop:** table `.dt` → card per row on phone; 5 KPI cards wrap (5th card on its own row | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1008 | 1. Quotation register | **Observed design problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1009 | 1. Quotation register | No pagination and no sort; whole FY loads at once. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1010 | 1. Quotation register | No filter by owner/assignee, office, business unit, client, date sent, value; search does not cover | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1012 | 1. Quotation register | "Contract pending" pill also on APPROVED (not yet sent/won) quotations — misleading (`quote_list.php:94`). | preserved — found in the final code |
| 1013 | 1. Quotation register | `mine=approve`/`mine=contract` views show 0 in other KPI cards and still render tab chips that drop the | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1015 | 1. Quotation register | Total shown includes GST while approval bands use before-tax subtotal — two different "values". | preserved — found in the final code |
| 1016 | 1. Quotation register | Header has 5 buttons of equal visual weight; duplicate-number panel is a DB query inside the view. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1017 | 1. Quotation register | Dead dispatcher routes `quotes-export`, `quote-final`. | preserved — found in the final code |
| 1021 | 2. New / Edit quotation (form) | **Source:** `views/ops/crm/quote_form.php` (776 lines); costing tab partial `views/ops/crm/_quote_costing_tab. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1025 | 2. New / Edit quotation (form) | **Routes:** GET/POST `/quote-new` (`?inquiry=`, `?lead=`, `?client=` prefill); GET/POST `/quote-edit?id=`. | preserved — found in the final code |
| 1035 | 2. New / Edit quotation (form) | **Purpose:** create a draft quotation or edit header, sites, commercials, line items, terms. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1036 | 2. New / Edit quotation (form) | **Access:** `crm.quote.create` or `mod.quotes.edit` or `is_master_of('quotes')` (`:2553`). Edit also | preserved — found in the final code |
| 1040 | 2. New / Edit quotation (form) | **Form structure:** one `<form id="qform">` posting to `/quote-new` or `/quote-edit?id=`; tabbed | preserved — found in the final code |
| 1045 | 2. New / Edit quotation (form) | **Inputs (name — type — rule):** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1046 | 2. New / Edit quotation (form) | Client & header: `client_id` (searchable select, "+ Add new" quick-add `data-qa="client"`); | preserved — found in the final code |
| 1054 | 2. New / Edit quotation (form) | Offices: `office_id` (primary/owning office); `exec_office_ids[]` checkboxes; `vendor_id` | preserved — found in the final code |
| 1056 | 2. New / Edit quotation (form) | Sites table (repeatable rows): `loc_id[]` hidden, `loc_label[]`, `loc_type[]` (SITE_LOCATION_TYPES), | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1061 | 2. New / Edit quotation (form) | Commercials: `validity_days` number (0–3650), `currency` text (default INR), `gst_pct` number 0–100 | preserved — found in the final code |
| 1067 | 2. New / Edit quotation (form) | Line items (repeatable): `l_sbu[]`, `l_activity[]` (depends on line BU via `activity_options_by_sbu()`), | preserved — found in the final code |
| 1073 | 2. New / Edit quotation (form) | Costing tab (edit only): partial shows costing code/status/revenue/cost/margin, margin flags, role | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1079 | 2. New / Edit quotation (form) | `costing_note` textarea "Why the prices differ from the costing" (edit, linked costing) — **required** | preserved — found in the final code |
| 1081 | 2. New / Edit quotation (form) | Terms: `terms_conditions` textarea (default `crm_default_terms()` on new). | preserved — found in the final code |
| 1082 | 2. New / Edit quotation (form) | **Server validation order (`lib/crm.php:2567-2621`):** form_once replay → stale `row_version` → | preserved — found in the final code |
| 1087 | 2. New / Edit quotation (form) | **On save:** new → `quote_no` from `crm_next_quote_no()`, status DRAFT, owner, links inquiry/lead, | preserved — found in the final code |
| 1091 | 2. New / Edit quotation (form) | **Buttons:** Save/Create (submit), Cancel → `/quote?id=` or `/quotes`, ← Back (`nav_back`). | preserved — found in the final code |
| 1092 | 2. New / Edit quotation (form) | **Statuses:** reachable for DRAFT, PENDING_APPROVAL, REJECTED, APPROVED (not locked); SENT/EXPIRED never | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1094 | 2. New / Edit quotation (form) | **Backend / tables:** `quotations`, `quote_lines`, `quote_locations`, `quote_revisions` (log), | preserved — found in the final code |
| 1096 | 2. New / Edit quotation (form) | **Links:** inquiry, lead (hidden ids), client master (quick add), vendor (quick add), costing (tab). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1097 | 2. New / Edit quotation (form) | **Phone vs desktop:** Sites table has 12 columns with min-widths (~1.6k px) and Line items 11 columns | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1100 | 2. New / Edit quotation (form) | **Observed design problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1101 | 2. New / Edit quotation (form) | Billing milestones are not on the form — only on the detail page (§3), although they are part of the | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1103 | 2. New / Edit quotation (form) | GST is one rate per quotation; no per-line tax, no CGST/SGST/IGST split on the form (the split is shown | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1105 | 2. New / Edit quotation (form) | "Currency" is free text; "Product category" free text; line "Activity" options are invisible until a | preserved — found in the final code |
| 1107 | 2. New / Edit quotation (form) | Very wide entry grids on desktop and phone; duplicate controls (`vendor_id` header + per-site vendor). | preserved — found in the final code |
| 1108 | 2. New / Edit quotation (form) | Hidden legacy inputs `l_office[]`; `l_subtypes[]` / `l_deliv[]` are read by save but no longer posted | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1110 | 2. New / Edit quotation (form) | Tabs hide required fields (subject on tab 1, lines on tab 5); errors arrive as a flash only. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1114 | 3. Quotation details | **Source:** `views/ops/crm/quote_detail.php` (1086 lines); milestones partial `views/ops/_milestones.php`; | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1118 | 3. Quotation details | **Route:** GET `/quote?id=`. Breadcrumb/back sits under the lead (`crm_quote_lead_link()`) or deal | preserved — found in the final code |
| 1120 | 3. Quotation details | **Purpose:** one quotation's status, next step, all actions, approval chain, contract, documents, history. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1121 | 3. Quotation details | **Page layout order:** record strip → header (title, status pill, "🕑 Rev NN · n versions" → `#revisions`, | preserved — checked by hand |
| 1127 | 3. Quotation details | **View data / computed figures (`lib/crm.php:2782-2857`):** lines, preorder on/items/state/requireAll/ | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1140 | 3. Quotation details | **Header buttons (`quote_detail.php:15-37`):** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1141 | 3. Quotation details | ▶ Raise inspection call → `/call-new?contract_id=&contract=` — ACCEPTED + contract OPEN + (`ops.call.create`/m | preserved — found in the final code |
| 1142 | 3. Quotation details | ⬇ PDF (for client) → `/quote-pdf?id=` (always; primary style unless ACCEPTED). | preserved — found in the final code |
| 1143 | 3. Quotation details | Word (editable) → `/quote-doc?id=` (always). | preserved — found in the final code |
| 1144 | 3. Quotation details | Warning pill when text has non-Latin characters the PDF cannot print (`crm_quote_pdf_unprintable()` `lib/crm.p | preserved — found in the final code |
| 1145 | 3. Quotation details | Edit → `/quote-edit?id=` — `canEdit` and status DRAFT/PENDING_APPROVAL/REJECTED, or "Edit (re-opened | preserved — found in the final code |
| 1147 | 3. Quotation details | **Jump nav:** Actions; Approval chain (if steps); Pre-order checklist (if on); Contract (ACCEPTED or has | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1149 | 3. Quotation details | **Now band texts per status** (`:66-105`): DRAFT (submit / send / who submits), PENDING_APPROVAL (with | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1153 | 3. Quotation details | **Conditional banners:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1154 | 3. Quotation details | Won handed to Accounts (ACCEPTED + "sales viewer" = create/send/followup.manage but not contract/call/master)  | preserved — found in the final code |
| 1155 | 3. Quotation details | "How to send" — APPROVED/SENT + contact email + canSend: 3 ways explained (system e-mail, Gmail, .eml) (`:130- | preserved — found in the final code |
| 1156 | 3. Quotation details | "Contract number is yet to be created" — ACCEPTED, no number; "waiting N days" pill (`contract_wait_days()`); | preserved — found (some words are filled in at run time) |
| 1160 | 3. Quotation details | Rejected banner (by, date, comment) (`:182-189`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1161 | 3. Quotation details | 🔒 Locked panel (`:191-228`): SENT/sent and current and not ACCEPTED → "Raise a revision" (opens revbox); | preserved — found in the final code |
| 1165 | 3. Quotation details | Validity: SENT past validity → "⏳ Past validity"; ≤7 days left → "Validity ends in N day(s)" (`:232-242`). | preserved — found (some words are filled in at run time) |
| 1166 | 3. Quotation details | Earlier revision → "This is an earlier revision" + "Open the current revision →" (no actions at all) (`:245-24 | preserved — found in the final code |
| 1167 | 3. Quotation details | **Actions panel (current revision only, `:254-319`), by status:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1168 | 3. Quotation details | DRAFT/REJECTED and no approval needed: "✉ Send to client" (`quote-status` to=SENT) if canSend and no | preserved — found (some words are filled in at run time) |
| 1170 | 3. Quotation details | DRAFT/REJECTED and approval needed and canSubmit: "Submit for approval" / "↻ Submit for approval again" | preserved — found in the final code |
| 1172 | 3. Quotation details | APPROVED: "✉ Send to client" or reason. | preserved — checked by hand |
| 1173 | 3. Quotation details | SENT/EXPIRED: if canDecide and a similar existing customer (`crm_quote_possible_customer()`): "Yes — same | preserved — found in the final code |
| 1177 | 3. Quotation details | APPROVED/SENT + contact email + canSend: "✉️ Open in Gmail" (`/quote-compose?id=&mode=gmail`, new tab, | preserved — found in the final code |
| 1180 | 3. Quotation details | canDecide and DRAFT/SENT: "Mark lost" (opens lostbox). | preserved — found in the final code |
| 1181 | 3. Quotation details | canRevise and SENT/APPROVED/LOST/REJECTED/EXPIRED and current: "Revise (new rev)" (opens revbox). | preserved — found in the final code |
| 1182 | 3. Quotation details | PENDING_APPROVAL: no action at all here (only approval chain); DRAFT has no Revise; ACCEPTED none. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1183 | 3. Quotation details | **Lost box** (`:485-500`): POST `/quote-status?id=` to=LOST; `lost_reason` select (QUOTE_LOST_REASONS / | preserved — found in the final code |
| 1186 | 3. Quotation details | **Revision box** (`:502-510`): POST `/quote-revise?id=`, `summary` optional. Server (`:3115-3146`): | preserved — found in the final code |
| 1190 | 3. Quotation details | **Costing panel** (`:321-366`, shown if `pc_can()`): linked → button to `/project-costing?id=` (code — | preserved — found in the final code |
| 1196 | 3. Quotation details | **Pre-order review panel** (`:368-409`, when setting on and items exist): progress pill (n/N done or | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1203 | 3. Quotation details | **Approval chain panel** (`:411-483`, when steps exist): "⏳ Waiting with <names> — level n of N" and | preserved — checked by hand |
| 1210 | 3. Quotation details | Server approve/reject (`lib/crm.php:3212-3293`): step must be PENDING; `crm_can_act_approval()` | preserved — found in the final code |
| 1216 | 3. Quotation details | Server take-back (`:3295-3333`): `quote_can_retract()` (APPROVED/PENDING_APPROVAL, not locked) and own | preserved — found in the final code |
| 1218 | 3. Quotation details | **"Send back"** in Sales quotations is the approver's **Reject** (status REJECTED); there is no separate | preserved — found in the final code |
| 1220 | 3. Quotation details | **Overview tab:** playbook card (6 steps: Build & submit [CTA "Edit the quote" → `/quote-edit`], Get it | preserved — found in the final code |
| 1227 | 3. Quotation details | **Milestones panel** (`_milestones.php`, included `quote_detail.php:577-586`, shown if rows or | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1237 | 3. Quotation details | **Jobs & order tab:** Calls under this order (count, invoiced, received; table Call → `/call?id=`, Raised, | preserved — found in the final code |
| 1241 | 3. Quotation details | **Contract panel** (`#contract`, ACCEPTED only, `:640-873`): | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1242 | 3. Quotation details | Registered: kv Registered client (code), Contract number + open-status pill (PENDING/OPEN/REJECTED/CLOSED), | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1248 | 3. Quotation details | Group-company contracts: list of other contracts on this quote (+ Raise call when OPEN); `<details>` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1252 | 3. Quotation details | Not registered + canContract: summary (generated number, billing branch, value before GST from PO or | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1261 | 3. Quotation details | Not registered, not canContract: "Awaiting Accounts…". | preserved — found in the final code |
| 1262 | 3. Quotation details | **History tab** (`<details id="revisions">`, open when >1 version or any change): Revision history table | preserved — found in the final code |
| 1266 | 3. Quotation details | **Follow-ups** (same History tab, `:943-1008`): cards per follow-up (kind label FOLLOWUP_KINDS D3/D6/D9/ | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1275 | 3. Quotation details | **Sites & docs tab:** Sites table (Site, Type, Address, Contact, Nearest office). Documents table (File → | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1283 | 3. Quotation details | **Customer response accept/lost:** see Actions; ACCEPTED effects (`lib/crm.php:2991-3103`): same-company | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1287 | 3. Quotation details | **Send to customer (system e-mail):** `quote-status` to=SENT without `by_hand` → `crm_send_quote_email()` | preserved — found in the final code |
| 1290 | 3. Quotation details | **Delegations:** not shown on the quote page; covering approvers appear inside "With whom" via | preserved — found in the final code |
| 1292 | 3. Quotation details | **PO:** only as a document kind with `po_number`/`po_date` on the quote; structured POs | preserved — found in the final code |
| 1295 | 3. Quotation details | **Permissions summary:** module + scope; per action as listed above; `quote-status` server checks: | preserved — found in the final code |
| 1298 | 3. Quotation details | **Links to other records:** lead / deal (breadcrumb + chain strip), enquiry (inquiry_sync), client master, | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1301 | 3. Quotation details | **Phone vs desktop:** jump nav wraps; topbar-aware `scroll-margin-top` (96px desktop, 176px ≤760px, | preserved — found in the final code |
| 1305 | 3. Quotation details | **Observed design problems:** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1306 | 3. Quotation details | Very long single page: 11+ panels before/around 4 tabs; approval chain, costing, checklist, actions | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1308 | 3. Quotation details | Three different "send" paths plus "mark sent" in one row; Word download offered 3 times (header, | preserved — found in the final code |
| 1310 | 3. Quotation details | In PENDING_APPROVAL the preparer has no "withdraw to draft" button although the refusal message for | preserved — found in the final code |
| 1312 | 3. Quotation details | Edit button is hidden for APPROVED although the handler allows editing an APPROVED quote (and voids | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1314 | 3. Quotation details | Playbook "Edit the quote" CTA is drawn regardless of edit rights/lock. | preserved — found in the final code |
| 1315 | 3. Quotation details | Delete-file button visible to `mod.quotes.edit` holders (`canEdit`) but server requires | preserved — found in the final code |
| 1317 | 3. Quotation details | Follow-up "Chase" link shown to view-only users; `followup-compose` has no permission check beyond | preserved — found in the final code |
| 1319 | 3. Quotation details | On a phone the milestone "Releases retention" radio is hidden, so a schedule with retention cannot be | preserved — found in the final code |
| 1321 | 3. Quotation details | Follow-up delete exists server-side but not in the UI. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1322 | 3. Quotation details | Contract panel exists only in ACCEPTED; jump link "Contract" also shown when a non-accepted quote | preserved — found in the final code |
| 1324 | 3. Quotation details | Revision does not carry documents (files), so the new draft starts with no attachments. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1328 | 4. Quotation preview / print / PDF / Word rou | **`GET /quote-pdf?id=`** (`lib/crm.php:3161-3170`): builds PDF with `quote_pdf_build()` (`lib/pdf.php:311`), | preserved — found in the final code |
| 1332 | 4. Quotation preview / print / PDF / Word rou | **`GET /quote-doc?id=[&tpl=]`** (`:3147-3160`): fills the uploaded .docx (`docx_fill()` `:4864`, | preserved — found in the final code |
| 1335 | 4. Quotation preview / print / PDF / Word rou | **`GET /quote-compose?id=&mode=`** (`:3506-3536`): `crm.quote.send`/master + can_work; `mode` = `eml` | preserved — found in the final code |
| 1338 | 4. Quotation preview / print / PDF / Word rou | **`GET /followup-compose?id=<followup>&mode=`** (`:3538-3550`): EMAIL_FOLLOWUP template or default; .eml or | preserved — found in the final code |
| 1340 | 4. Quotation preview / print / PDF / Word rou | **No HTML preview/print page exists for a quotation** (only file downloads). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1341 | 4. Quotation preview / print / PDF / Word rou | **Phone:** downloads; .eml cannot be opened by mobile browsers' Gmail (the page says so). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1345 | 5. External quotation (register a portal / te | **Source:** `views/ops/crm/quote_external.php`; handler `lib/crm.php:2414-2493`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1346 | 5. External quotation (register a portal / te | **Routes:** GET/POST `/quote-external`. Entry: "+ External" on the register. | preserved — found in the final code |
| 1347 | 5. External quotation (register a portal / te | **Purpose:** put a quotation submitted outside our format on the register. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1348 | 5. External quotation (register a portal / te | **Access:** `crm.quote.create` or master (`:2415`) — the register button shows for `mod.quotes.edit` too | preserved — found in the final code |
| 1350 | 5. External quotation (register a portal / te | **Inputs:** `origin` select (QUOTE_ORIGINS except OWN) required; `origin_portal`; `origin_ref`; | preserved — found in the final code |
| 1357 | 5. External quotation (register a portal / te | **Validation:** figures (`crm_quote_figures_problem()` + total ≥0), trade block when SENT, origin | preserved — found in the final code |
| 1360 | 5. External quotation (register a portal / te | **On save:** INSERT quotation (status SENT/LOST, origin fields), lines via `crm_save_lines_from_post()`, | preserved — found in the final code |
| 1362 | 5. External quotation (register a portal / te | **Phone:** lines table has inputs in 5 columns → stays scroll table. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1363 | 5. External quotation (register a portal / te | **Problems:** no file upload here (told to attach later); no deal/lead/enquiry link; GST forced to 0 so | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1368 | 6. Quote approval rules (levels) | **Source:** `views/ops/crm/approval_rule_list.php`, `approval_rule_form.php`, `approval_levels_inline.php`; | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1371 | 6. Quote approval rules (levels) | **Routes:** GET `/quote-approval-rules` (list) and `/approval-rules?module=quote` (merged screen, | preserved — found in the final code |
| 1376 | 6. Quote approval rules (levels) | **Access:** `crm.quote.approve` or master (`:4756`). | preserved — found in the final code |
| 1377 | 6. Quote approval rules (levels) | **List fields:** Level, Rule name, Applies to (Any BU / BU: name via `crm_rule_is_unit()`), From ₹, Up to | preserved — found in the final code |
| 1380 | 6. Quote approval rules (levels) | **Form inputs:** `name` (required in HTML; server auto-names "Level n — <who>" when blank), `level` | preserved — found (some words are filled in at run time) |
| 1386 | 6. Quote approval rules (levels) | **Inline editor (Set up Sales / Sales approvals):** one card per level with `level`, `min_amount`, | preserved — found in the final code |
| 1391 | 6. Quote approval rules (levels) | **Statuses involved:** rule active/off; steps PENDING/APPROVED/REJECTED. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1392 | 6. Quote approval rules (levels) | **Problems:** three UIs for the same table (list+form, inline cards, merged tab) with different labels | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1400 | 7. Sales setup (`/sales-setup`) and Sales app | **Source:** `lib/setupqa.php` (`setupqa_sales_set()` `:103-251`), views `views/ops/setup_questions.php`, | preserved — found in the final code |
| 1403 | 7. Sales setup (`/sales-setup`) and Sales app | **Routes:** GET/POST `/sales-setup` (`ops_setup_questions('sales')` `:87`); GET/POST | preserved — found in the final code |
| 1406 | 7. Sales setup (`/sales-setup`) and Sales app | **Access:** master, `settings.manage` or `crm.quote.approve` (`:110`); contract section editable only by | preserved — found in the final code |
| 1408 | 7. Sales setup (`/sales-setup`) and Sales app | **Questions (radio, setting keys):** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1409 | 7. Sales setup (`/sales-setup`) and Sales app | quote tab: `quote_unmatched_approval` (top / any / none, default top); `quote_self_approval` (yes/no, | preserved — found in the final code |
| 1415 | 7. Sales setup (`/sales-setup`) and Sales app | costing tab: `quote_manmonth_costing` (yes/no). | preserved — found in the final code |
| 1416 | 7. Sales setup (`/sales-setup`) and Sales app | contract tab: `contract_open_signatures` (2/1/0), `contract_same_person` (no/yes, only when 2), signer | preserved — found in the final code |
| 1418 | 7. Sales setup (`/sales-setup`) and Sales app | deals tab: facts from `gate_rules()` + link "Add or change the rules for deal stages" → `/stage-gates`. | preserved — found in the final code |
| 1419 | 7. Sales setup (`/sales-setup`) and Sales app | leads tab: facts only. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1420 | 7. Sales setup (`/sales-setup`) and Sales app | **Save:** only offered values written; one activity-log line per changed answer (`setupqa_save()` `:34-55`); | preserved — found in the final code |
| 1423 | 7. Sales setup (`/sales-setup`) and Sales app | **Phone:** radio choices are full-width bordered labels; level cards auto-fit min 190px. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1424 | 7. Sales setup (`/sales-setup`) and Sales app | **Problems:** same rules reachable from two screens with different wording; the quote tab mixes | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1430 | 8. Approval delegations (quotation cover) | **Source:** `views/ops/approval_delegations.php`; handlers `ops_quote_delegations()` `lib/crm.php:4435-4474` | preserved — found in the final code |
| 1433 | 8. Approval delegations (quotation cover) | **Routes:** GET/POST `/quote-delegations` (Sales; entity fixed to QUOTE); GET/POST | preserved — found in the final code |
| 1435 | 8. Approval delegations (quotation cover) | **Access (quote):** `crm_quote_delegation_can()` (`lib/crm.php:4430`): quotes licence live AND (master of | preserved — found in the final code |
| 1438 | 8. Approval delegations (quotation cover) | **Fields:** table Who is away, Acting for them, Applies to (entity / "Everything they approve"), Branch | preserved — found in the final code |
| 1443 | 8. Approval delegations (quotation cover) | **Validation:** both people, not the same, both active, date order etc. in `appr_delegation_save()`. | preserved — found in the final code |
| 1444 | 8. Approval delegations (quotation cover) | **Effect:** delegates appear in "With whom", receive approval e-mails, can sign (`crm_can_act_approval()`). | preserved — found in the final code |
| 1445 | 8. Approval delegations (quotation cover) | **Phone:** form grid 3 → 1 col ≤820px; table `.table` class is not in the card list (stays scroll table). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1446 | 8. Approval delegations (quotation cover) | **Problems:** no edit of an existing delegation (only revoke + add); generic screen unreachable for Sales | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1452 | 9. Quotation templates, letterhead & signatur | **Source:** `views/ops/crm/template_list.php`, `template_form.php`; handler `lib/crm.php:4928-5050`; | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1454 | 9. Quotation templates, letterhead & signatur | **Routes:** GET `/templates?kind=quote` or `/crm-templates`; GET/POST `/crm-template-new`, | preserved — found in the final code |
| 1457 | 9. Quotation templates, letterhead & signatur | **Access:** `crm.template.manage` or master (`:4929`); merged screen also needs a Sales/Reporting/Ops licence. | preserved — found in the final code |
| 1458 | 9. Quotation templates, letterhead & signatur | **Letterhead form** (`#letterhead`): `logo` file (PNG/JPG, converted by `signature_to_jpeg()`), `lh_name` | preserved — found in the final code |
| 1461 | 9. Quotation templates, letterhead & signatur | **Signature form** (`#signature`): `sig` image (required if none on file), `sig_name` (required), | preserved — found in the final code |
| 1463 | 9. Quotation templates, letterhead & signatur | **Template list:** Name (+ default pill), Kind (CRM_TEMPLATE_KINDS: QUOTE_DOC, EMAIL_QUOTE, EMAIL_FOLLOWUP, | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1466 | 9. Quotation templates, letterhead & signatur | **Template form:** `name` (required HTML), `kind`, `is_default`, `active`, `document_number`, | preserved — found in the final code |
| 1469 | 9. Quotation templates, letterhead & signatur | **Problems:** e-mail fields always visible even for a .docx template (id `emailfields` never toggled); no | preserved — found in the final code |
| 1475 | 10. Pre-order checklist configuration | **Source:** `views/ops/preorder_checklist.php`; handler `ops_preorder_checklist()` `lib/ops.php:900-924`; | preserved — found in the final code |
| 1477 | 10. Pre-order checklist configuration | **Routes:** GET/POST `/preorder-checklist`. | preserved — found in the final code |
| 1478 | 10. Pre-order checklist configuration | **Access:** master, `settings.manage` or `crm.quote.approve`. | preserved — found in the final code |
| 1479 | 10. Pre-order checklist configuration | **Inputs:** `enabled` checkbox (`preorder_checklist_on`), `require_all` checkbox | preserved — found in the final code |
| 1482 | 10. Pre-order checklist configuration | **Effect:** panel on every quote; require-all gates APPROVED, SENT and ACCEPTED (not only approval as | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1484 | 10. Pre-order checklist configuration | **Problems:** items are keyed by an md5 of their text (`preorder_item_key()`), so editing a point's wording | preserved — found in the final code |
| 1491 | 11a. Approvals inbox `/approvals` | **Source:** `views/ops/approvals.php`; handler `ops_approvals()` `lib/stagegate.php:412-456`; sections | preserved — found in the final code |
| 1494 | 11a. Approvals inbox `/approvals` | **Routes:** GET `/approvals?f=PENDING/APPROVED/REJECTED/CANCELLED`; POST `/approval-act` (`id`, | preserved — found in the final code |
| 1497 | 11a. Approvals inbox `/approvals` | **Sales items that appear:** Quotations waiting at my level (`crm_quote_ids_awaiting_me()`; row: label — | preserved — found in the final code |
| 1502 | 11a. Approvals inbox `/approvals` | **Deal gate blocks:** filter buttons by status; "Waiting on you" (Deal → `/opportunity?id=`, partner, | preserved — found in the final code |
| 1507 | 11a. Approvals inbox `/approvals` | **Server:** `gate_act()` (`lib/stagegate.php:206-243`): requester cannot approve own; REJECTED needs | preserved — found in the final code |
| 1510 | 11a. Approvals inbox `/approvals` | **Problems:** quotation and costing rows only link out ("Decide") — no inline decision, unlike lead | preserved — found in the final code |
| 1515 | 11b. Stage gate rules `/stage-gates` | **Source:** `views/ops/stage_gates.php`; handler `ops_stage_gates()` `lib/stagegate.php:374-410`; save | preserved — found in the final code |
| 1518 | 11b. Stage gate rules `/stage-gates` | **Routes:** GET `/stage-gates[?new=1/?edit=id]`; POST `/stage-gate-save`; POST `/stage-gate-delete`. | preserved — found in the final code |
| 1519 | 11b. Stage gate rules `/stage-gates` | **Access:** `gate_can_manage()` = `settings.manage` or `crm.quote.approve` or master of leads (`:66`); | preserved — found in the final code |
| 1521 | 11b. Stage gate rules `/stage-gates` | **Inputs:** `name` (required, ≤140), hidden `entity_kind=OPPORTUNITY`, `pipeline_id` (0 = every), | preserved — found in the final code |
| 1524 | 11b. Stage gate rules `/stage-gates` | **List:** Rule (+Off pill, sentence), Value band, Guards, Approver (role shown as raw code), Used count, | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1526 | 11b. Stage gate rules `/stage-gates` | **Problems:** approver role shown as code not label (`stage_gates.php:164`); "Remove" actually switches off | preserved — found in the final code |
| 1531 | 12. Project costing list | **Source:** `views/ops/project_costings.php`; handler list branch `lib/projcosting.php:2095-2108`; | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1533 | 12. Project costing list | **Routes:** GET `/project-costings?q=&link=none&status=&inquiry_id=&lead_id=`; POST `/project-costing-new`. | preserved — found in the final code |
| 1534 | 12. Project costing list | **Access:** `pc_can()` (`:172`: licence quotes/inquiries/hiring + `mod.quotes.view` / `mod.hiring.view` / | preserved — found in the final code |
| 1537 | 12. Project costing list | **New costing form** (`<details>`, open when list empty or prefilled): `inquiry_id` or `lead_id` | preserved — found in the final code |
| 1542 | 12. Project costing list | **Filters:** `q` (code, title, client, quotation no.), `link=none` ("Not on a quotation"), `status` | preserved — found in the final code |
| 1545 | 12. Project costing list | **Columns:** Code → `/project-costing?id=`, Title + site, Client, Quotation (→ `/quote?id=`, "Rn") or "not | preserved — found in the final code |
| 1547 | 12. Project costing list | **Header:** "🧾 Costing heads & templates" → `/project-costing-heads` when `ch_can_manage()`. | preserved — found in the final code |
| 1548 | 12. Project costing list | **Phone:** `.grid` table → cards; new-costing form 1 col. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1549 | 12. Project costing list | **Problems:** status filter invisible in UI and "Clear" not shown when only status is applied; no | preserved — found in the final code |
| 1554 | 13. Project costing page (builder) and print | **Source:** `views/ops/project_costing.php` (854 lines); handler `lib/projcosting.php:1652-2093`; | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1556 | 13. Project costing page (builder) and print | **Routes (all POST unless noted):** GET `/project-costing?id=[&edit=lineId][&embed_stay=1]`; | preserved — found in the final code |
| 1565 | 13. Project costing page (builder) and print | **Statuses (`PC_STATUS` `:27`):** DRAFT, SUBMITTED ("Submitted for approval"), APPROVED, REJECTED ("Sent | preserved — found in the final code |
| 1571 | 13. Project costing page (builder) and print | **Header:** title + status pill; revision list (Rev 00…, links to other revisions); subtitle; "For" links | preserved — found in the final code |
| 1575 | 13. Project costing page (builder) and print | **KPI cards:** Project revenue, Project cost, Project profit, Margin (`pc_rollup()` `:716`). Margin-band | preserved — found in the final code |
| 1577 | 13. Project costing page (builder) and print | **Settings panel (editable when `canEdit` = `pc_can_edit()` and not locked):** `title`, `client_id`, | preserved — found in the final code |
| 1581 | 13. Project costing page (builder) and print | **Team roles table:** group rows; Role, shown heads (only heads in use), Direct cost, Loaded cost, Rate, | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1584 | 13. Project costing page (builder) and print | **Add / edit role form (`#add-role`):** `group_label`, `role_label` (required), `headcount` (≥1), | preserved — found in the final code |
| 1594 | 13. Project costing page (builder) and print | **Use this costing panel (`#use`, shown when roles exist and `mod.quotes.edit` or `mod.hiring.edit`):** | preserved — found in the final code |
| 1601 | 13. Project costing page (builder) and print | **Recruitment for this costing** (`mod.hiring.edit`): gate `pc_hire_gate()` (quote must be accepted); | preserved — found in the final code |
| 1606 | 13. Project costing page (builder) and print | **Loadings panel:** "How this price is built" build-up table (`pc_buildup()` `:1570`). | preserved — found in the final code |
| 1607 | 13. Project costing page (builder) and print | **Approval panel:** preview of levels it will go to (`pc_preview_levels()` `:340`) for DRAFT/REJECTED; | preserved — found in the final code |
| 1613 | 13. Project costing page (builder) and print | **Approver resolution:** same levels as a quotation of this value/BU (`pc_build_approvals()` `:231`, | preserved — found in the final code |
| 1616 | 13. Project costing page (builder) and print | **Print sheet** (`project_costing_print.php`, standalone HTML): "🖨 Print / Save as PDF" (`window.print()`) | preserved — found in the final code |
| 1619 | 13. Project costing page (builder) and print | **Phone vs desktop:** settings grid 4 → 2 (≤980px) → 1 col (≤560px) (`project_costing.php:124-135`); | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1622 | 13. Project costing page (builder) and print | **Problems:** very long page (KPIs, settings, roles, use, recruitment, role editor, loadings, approval, | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1629 | 14. Costing heads & templates | **Source:** `views/ops/costing_heads.php`; handler `lib/projcosting.php:1724-1769`; library | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1631 | 14. Costing heads & templates | **Routes:** GET/POST `/project-costing-heads` (`do` = head_save / head_active / dpm / template_save / | preserved — found in the final code |
| 1633 | 14. Costing heads & templates | **Fields:** working days in a month `dpm` (1–31); library table (heads, active/retired toggle); | preserved — found in the final code |
| 1638 | 14. Costing heads & templates | **Rule:** effective-dated values — existing costings keep the value they were priced with. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1639 | 14. Costing heads & templates | **Problems:** formula/percentage concepts exposed raw; no preview of a head on a sample CTC. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1643 | 15. CRM reports (`/crm-reports`) | **Source:** `views/ops/crm/reports.php`; handler `ops_crm_reports()` `lib/crm.php:5053-5096`. | preserved — found in the final code |
| 1644 | 15. CRM reports (`/crm-reports`) | **Route:** GET `/crm-reports?fy=&export=csv`. | preserved — found in the final code |
| 1645 | 15. CRM reports (`/crm-reports`) | **Access:** `mod.crm_reports.view` or master of crm_reports; scope `scope_clause('q.office_id','q.sbu')`. | preserved — found in the final code |
| 1646 | 15. CRM reports (`/crm-reports`) | **Content:** title "Sales dashboard"; FY select (6 options, no "All years"); "⬇ Download CSV"; KPI cards | preserved — found in the final code |
| 1651 | 15. CRM reports (`/crm-reports`) | **Problems:** values use `total_amount` (incl. GST) while everything else uses before-tax; FY filter done | preserved — found in the final code |
| 1747 | 1. Orders & payments | **Source**: `views/ops/sx_orders.php` (41 lines); handler `lib/sales_more.php:500-509` | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1750 | 1. Orders & payments | **Routes**: `GET /sales-orders` only. No POST. No export. | preserved — found in the final code |
| 1751 | 1. Orders & payments | **Purpose**: every accepted quotation (= order won) in the period, with how much was invoiced and settled. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1754 | 1. Orders & payments | Header "💰 Orders & payments" (`sx_orders.php:12`); "Why you're here … Amounts include GST." (:16). | changed by owner decision |
| 1755 | 1. Orders & payments | KPI cards (:17-24): | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1756 | 1. Orders & payments | "🏆 Orders won" = Σ `quotations.total_amount` (incl. GST) of the rows; hint "N orders · <period label>" (:18).  | preserved — found (some words are filled in at run time) |
| 1757 | 1. Orders & payments | Only when `ar_can()`: "🧾 Still to bill" = Σ order − Σ billed, floor 0, hint "order in hand" (:19); | preserved — found in the final code |
| 1763 | 1. Orders & payments | When not `ar_can()`: note "Billed and received amounts are shown to people who may see receivables." (:25). | preserved — found in the final code |
| 1764 | 1. Orders & payments | Table "Orders · <period>" (:26-40), columns: Quote (no + " R<rev>", link `/quote?id=`) · Client (link | preserved — found (some words are filled in at run time) |
| 1767 | 1. Orders & payments | Status pill rule (:31): billed ≈ 0 → "Not billed yet" (amber); received ≥ billed → "Fully paid" (green, if bil | preserved — found in the final code |
| 1768 | 1. Orders & payments | Empty: "No accepted quotes in this period." (:27). | preserved — checked by hand |
| 1771 | 1. Orders & payments | Period chips `/sales-orders?p=fy/quarter/month/90` (+ `&who=`) (:14). | preserved — found in the final code |
| 1772 | 1. Orders & payments | Person chips (managers) `/sales-orders?who=me/all/<id>&p=` (:15). | preserved — found in the final code |
| 1773 | 1. Orders & payments | KPI "⏰ Overdue" → `/receivables` (ar_can only). | preserved — found in the final code |
| 1774 | 1. Orders & payments | Row links: `/quote?id=`, `/partner?id=`. | preserved — found in the final code |
| 1775 | 1. Orders & payments | **No "raise order" here.** "Raise the work order" exists only on the won-deal page — `views/ops/opportunity_de | preserved — found (some words are filled in at run time) |
| 1776 | 1. Orders & payments | **No payment links**: no payment-link / pay-now / UPI / Razorpay link exists for invoices anywhere in Sales, r | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1781 | 1. Orders & payments | `sx_can()`; billing columns/KPIs only `ar_can()` (`lib/receivables.php:47-50`: licence `invoicing` live AND `f | preserved — found in the final code |
| 1782 | 1. Orders & payments | Whose orders (`sales_more.php:501-503`): managers per `who`; a non-manager with `ar_can()` (Finance) → everyon | preserved — found in the final code |
| 1783 | 1. Orders & payments | Office scope (`sales_more.php:267`): quotations with an `office_id` outside `scope_allows()` dropped; quotatio | preserved — found in the final code |
| 1786 | 1. Orders & payments | Orders: `quotations` `is_current=1 AND status='ACCEPTED'`, date = `accepted_date` else `updated_at` (`sales_mo | preserved — found in the final code |
| 1787 | 1. Orders & payments | Billed: invoices with `quotation_id = q.id` OR (`partner_id = client_id` AND `contract_number = q.contract_num | preserved — found in the final code |
| 1788 | 1. Orders & payments | Received: Σ `books_settled()['settled']` = cash + TDS + issued credit notes (`lib/books.php:449-461`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1789 | 1. Orders & payments | Received this month: raw `receipts` table, no office or person scope (`sales_more.php:586`). | preserved — found in the final code |
| 1790 | 1. Orders & payments | Overdue: `ar_rows('DUE')` — office/SBU scoped (`receivables.php:71-110`), not person-scoped, not limited to th | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1791 | 1. Orders & payments | Days to get paid: company-wide, no scope (`sales_more.php:286-294`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1792 | 1. Orders & payments | `_invs` (the matched invoices) is computed per row (:280) but never rendered. | preserved — found in the final code |
| 1799 | 1. Orders & payments | "Received" means settled (includes TDS and credit notes), while the hint says "against these orders"; Contract | preserved — found (some words are filled in at run time) |
| 1800 | 1. Orders & payments | "Received this month" and "Days to get paid" are company-wide (not office- or person-scoped) beside person/per | preserved — found in the final code |
| 1801 | 1. Orders & payments | Order totals here are incl. GST; the Pipeline board's "Confirmed orders" for the SAME set (it calls `sx_orders | preserved — found in the final code |
| 1802 | 1. Orders & payments | Billed is matched by contract number too, so two accepted quotations sharing one contract number each count al | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1803 | 1. Orders & payments | No link from an order row to its invoices or to the next action (bill it / chase it), although the intro says  | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1804 | 1. Orders & payments | No raise-order entry point although the owner's list mentions it; it lives only on the deal page. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1810 | 2. Receivables ageing (reached from Sales) | **Source**: `views/ops/receivables.php` (95 lines); handler `ops_receivables()` `lib/receivables.php:238-262`; | preserved — found in the final code |
| 1811 | 2. Receivables ageing (reached from Sales) | **Entry from Sales**: Orders "⏰ Overdue" KPI; Advertising return "chase it" and "Invoiced, but nothing has bee | preserved — found in the final code |
| 1812 | 2. Receivables ageing (reached from Sales) | **Routes**: `GET /receivables?basis=DUE/INVOICE&client=<id>`; `&export=csv` (`wants_csv()`) downloads `receiva | preserved — found in the final code |
| 1813 | 2. Receivables ageing (reached from Sales) | **Fields**: KPI cards Outstanding / Past due (%) / 90+ days (%) / Not yet due (view :20-25); "By client" table | preserved — found in the final code |
| 1814 | 2. Receivables ageing (reached from Sales) | **Buttons**: "⬇ Download CSV" (:10); Age by "Due date" / "Invoice date" (:15-16); "Clear →" (:68). | preserved — found in the final code |
| 1815 | 2. Receivables ageing (reached from Sales) | **Permission**: `ar_can()` (:239). Office/SBU scope via `scope_clause` (:74). | preserved — found in the final code |
| 1816 | 2. Receivables ageing (reached from Sales) | **Backend**: `ar_rows()` (:114) → `ar_rows_from_books()` (:71) when any ISSUED/PART_PAID invoice exists (outst | preserved — found in the final code |
| 1817 | 2. Receivables ageing (reached from Sales) | **Phone**: table `overflow-x:auto`; no phone layout. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1818 | 2. Receivables ageing (reached from Sales) | **Design problems observed** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1819 | 2. Receivables ageing (reached from Sales) | With book invoices, "Open →" links `/job?id=<invoice id>` (view :88; `id` is `i.id` from `receivables.php:76`) | preserved — found in the final code |
| 1820 | 2. Receivables ageing (reached from Sales) | Book rows have `due_date` but the view reads `invoice_due_date` (:84), so "Due" shows "not set" on every book  | preserved — found in the final code |
| 1821 | 2. Receivables ageing (reached from Sales) | Two separate "money owed" screens for Sales: Today's "Payments to collect" (link to `/invoice`) and this one. | preserved — found in the final code |
| 1827 | 3. Renewals | **Source**: `views/ops/sx_renewals.php` (31 lines); handler `sales_more.php:511-514`; data `sx_renewals_list() | preserved — found in the final code |
| 1828 | 3. Renewals | **Routes**: `GET /sales-renewals?d=30/60/90/180` (default 90; anything else → 90, :512). No POST. | preserved — found in the final code |
| 1829 | 3. Renewals | **Purpose**: contracts ending within d days, plus those that ended in the last 30 days. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1832 | 3. Renewals | KPI cards (`sx_renewals.php:13-16`): "⛔ Ended (last 30 days)" count, hint "call now"; "🟠 Ending in 30 days" co | preserved — found (some words are filled in at run time) |
| 1833 | 3. Renewals | Table "Contracts" (:18-30): Contract (number else title, link `/contract?id=`, title below), Client (link `/pa | preserved — found in the final code |
| 1834 | 3. Renewals | Empty: "Nothing ends in the next d days." (:19). | preserved — checked by hand |
| 1837 | 3. Renewals | Look-ahead chips "Next 30/60/90/180 days" → `/sales-renewals?d=` (:10). | changed by owner decision |
| 1838 | 3. Renewals | "🧾 Renewal quote" → `/quote-new?client=<partner_id>` (:28), shown when `partner_id` and (`crm.quote.create` /  | preserved — found in the final code |
| 1851 | 3. Renewals | Does not filter `open_status`: PENDING, REJECTED or CLOSED contracts are listed; the contract register's own e | preserved — found in the final code |
| 1852 | 3. Renewals | "Renewal quote" pre-fills only the client — not the contract, its lines or value; nothing shows that a renewal | preserved — found (some words are filled in at run time) |
| 1853 | 3. Renewals | Three different windows: Renewals screen default 90 d (+30 d back); Today "Renewals due" and Sales home KPI us | preserved — found in the final code |
| 1854 | 3. Renewals | Row link `/contract?id=` needs `mod.clients.view`/`mod.vendors.view`/`crm.contract.register`/`data.credit`/`fi | preserved — found in the final code |
| 1855 | 3. Renewals | "Their value" mixes ended and upcoming contracts and before-GST/as-typed values; contract value KPI is hidden  | preserved — found in the final code |
| 1861 | 4. Targets | **Source**: `views/ops/sx_targets.php` (39 lines); handler `sales_more.php:516-540`; data `sxt_months()` :312- | preserved — found in the final code |
| 1862 | 4. Targets | **Routes** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1863 | 4. Targets | `GET /sales-targets` → grid + history. | preserved — found in the final code |
| 1864 | 4. Targets | `POST /sales-targets` with `seen=1` → acknowledge (from Sales home), `UPDATE sales_targets SET seen_at` for cu | preserved — found in the final code |
| 1865 | 4. Targets | `POST /sales-targets` (save) → `ops_require(sxt_can_set())` (:524), `sxt_save()`, flash, redirect `/sales-targ | preserved — found in the final code |
| 1866 | 4. Targets | **Purpose**: monthly sales target per person for the active FY, with won value against it, and a change histor | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1869 | 4. Targets | One row per person, one cell per FY month (12, from `fy_range(active_fy())`), current month header in brand co | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1870 | 4. Targets | Input `t[<uid>][<YYYY-MM>]` text, `inputmode="numeric"`, value = latest target (:19); hidden `o[<uid>][<YYYY-M | preserved — found in the final code |
| 1871 | 4. Targets | Server validation (`sxt_save`): only listed people/months; commas, ₹ and spaces stripped; must be numeric ≥ 0  | preserved — found in the final code |
| 1872 | 4. Targets | Under each cell: "↻ was <prev>" pill with tooltip "Changed <date> by <who> — was <amount>" when the month has  | preserved — found (some words are filled in at run time) |
| 1873 | 4. Targets | Row end: Year (Σ 12 targets), Won (Σ), % pill (≥100 green, ≥70 yellow, else red) (:25-26). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1874 | 4. Targets | Non-managers: read-only figures (`sx_short`) for their own row only (:19, handler :536). | preserved — found in the final code |
| 1875 | 4. Targets | "Save targets" button + "Leave a box empty for no target." (:29). | preserved — found in the final code |
| 1884 | 4. Targets | View: `sx_can()`. | preserved — found in the final code |
| 1885 | 4. Targets | Set: `sxt_can_set()` = `sx_can_see_all() && crmdash_can()` (`sales_more.php:310`) — MGMT_ROLES/master with Sal | preserved — found in the final code |
| 1886 | 4. Targets | Who can carry a target: `sx_target_people()` = `sx_people()` (office-scoped owners) PLUS every active user who | preserved — found in the final code |
| 1895 | 4. Targets | Sales managers in SALES_ROLES (e.g. Business Development / Key Accounts / Marketing Manager) are not `is_admin | preserved — found in the final code |
| 1896 | 4. Targets | SALES_ROLES people are added regardless of office — a branch manager sees (and can set targets for) salespeopl | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1897 | 4. Targets | Clearing an existing target stores 0 (shown "0"), not "no target", contrary to "Leave a box empty for no targe | preserved — found (some words are filled in at run time) |
| 1898 | 4. Targets | History limited to the last 30 changes, no paging/filter; the "↻ was" pill shows only the immediately previous | preserved — found in the final code |
| 1899 | 4. Targets | Year % compares won-to-date with the whole year's target (by design per owner 19 Nov), but no "to date" figure | preserved — found in the final code |
| 1900 | 4. Targets | If the Targets feature is switched off, the Sales-home notice still renders but its "OK, seen" form posts to a | preserved — found in the final code |
| 1901 | 4. Targets | Grid cell aria-label uses the raw `YYYY-MM` (:19). | preserved — found in the final code |
| 1907 | 5. Sales settings | **Source**: `views/ops/sx_settings.php` (50 lines); handler `sales_more.php:542-580`; readers `sx_outcomes()`  | preserved — found in the final code |
| 1908 | 5. Sales settings | **Routes**: `GET /sales-settings`; `POST /sales-settings` (one form for all saved settings). Redirects back wi | preserved — found in the final code |
| 1909 | 5. Sales settings | **Permission**: `is_master() // can('settings.manage')` (:543). Each `setting_set()` is recorded on the settin | preserved — found in the final code |
| 1912 | 5. Sales settings | Card "📝 What happened → next follow-up" (+ "Example values — edit them" badge while the setting is empty, :17) | preserved — found in the final code |
| 1913 | 5. Sales settings | per outcome: hidden `oc_key[i]`, text `oc_label[i]` (placeholder "Add another…" on the one blank row), number  | preserved — found in the final code |
| 1914 | 5. Sales settings | Server (:545-555): blank label skipped; key from posted key else from label (A–Z0–9_, ≤24 chars), non-Latin la | preserved — found (some words are filled in at run time) |
| 1915 | 5. Sales settings | Meaning: 0 days = "they pick the date" (enforced in `sx_log_save`, :197-198). | preserved — found in the final code |
| 1916 | 5. Sales settings | Card "📆 Weekly off": checkboxes `off[]` values 0–6 (Mon…Sun) (:29). Stored `sales_weekly_off` CSV; none ticked | preserved — found in the final code |
| 1917 | 5. Sales settings | "💤 A deal is "stale" after": number `stale` min 1 max 365 "days with no activity" (:30-31). Stored `sales_stal | preserved — found in the final code |
| 1918 | 5. Sales settings | Card "🏢 New leads go to": per office a select `own[<office id>]` = "— whoever types it —" or a person; options | preserved — found in the final code |
| 1919 | 5. Sales settings | Button "Save Sales settings" (:39). | preserved — found in the final code |
| 1922 | 5. Sales settings | "🧭 Stages and win %": stages of the default OPPORTUNITY pipeline (`pipeline_default`, `pipeline_stages`) with  | preserved — found in the final code |
| 1923 | 5. Sales settings | "📋 Lists kept in their own place" (:5-11, 47-48), each hidden when its feature is off: 📣 Lead sources `/lookup | preserved — found in the final code |
| 1924 | 5. Sales settings | Holidays are only linked (not edited here); `sx_add_working_days()` skips weekly off + national holidays + the | preserved — found in the final code |
| 1931 | 5. Sales settings | Sales settings opens on `settings.manage`, but the Lead sources / Lost reasons / Buying roles links need `is_a | preserved — found in the final code |
| 1932 | 5. Sales settings | Only one blank "Add another…" row per save; no reorder; renaming an outcome has no history on the outcome itse | preserved — found in the final code |
| 1933 | 5. Sales settings | Stages shown only for the default deal pipeline; other pipelines are not mentioned. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1934 | 5. Sales settings | Sales configuration is spread over five screens (this, Set up Sales, Sales approvals, Pre-order checklist, gen | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1935 | 5. Sales settings | Not linked from Sales home's "Where to go" tiles (`sx_home.php:11-23`); only the area tile. | preserved — found in the final code |
| 1941 | 6. Sales area landing page (`/sales`) | **Source**: `lib/areas.php:93-136` (`ops_area_def('sales')`), renderer `views/ops/area_home.php`; handler `ops | preserved — found in the final code |
| 1942 | 6. Sales area landing page (`/sales`) | Every tile also disappears when its feature is off (`areas.php:36`, `feat_on($route)`). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1943 | 6. Sales area landing page (`/sales`) | Page: title "Sales", subtitle "Leads, opportunities, inquiries, quotes and the pipeline." (:95); the shared "Y | preserved — found (some words are filled in at run time) |
| 1971 | 6. Sales area landing page (`/sales`) | Rail highlight routes `$routes` (:96) include all `sales-*`, leads/opportunities/inquiries/quotes, pipelines,  | preserved — found in the final code |
| 1972 | 6. Sales area landing page (`/sales`) | **Phone**: tile grid 3 columns → 1 column under 820 px (`area_home.php:15,31`); description shown on the tile  | preserved — found in the final code |
| 1973 | 6. Sales area landing page (`/sales`) | **Design problems observed** | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1974 | 6. Sales area landing page (`/sales`) | Two tiles both called "Sales dashboard": `/sales-dashboard` here and `/crm-dashboard` in Insights (`areas.php: | preserved — found in the final code |
| 1975 | 6. Sales area landing page (`/sales`) | The Quotes badge counts accepted quotes waiting for a contract number, but the tile says "Quotations, revision | preserved — found in the final code |
| 1976 | 6. Sales area landing page (`/sales`) | Two icons reused for different tiles (🎯 Leads and 🎯 Targets/area icon; 📝 Quotes and 📝 Quote templates; 🧭 Pipel | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1977 | 6. Sales area landing page (`/sales`) | Configuration tiles (Sales settings in "Start here", Set up Sales / Pre-order checklist in "Records") are spli | preserved — found in the final code |
| 1978 | 6. Sales area landing page (`/sales`) | `_glance_strip.php:30-35` computes `$gsGlance` twice; the second assignment ignores `$glanceWantMgmt`. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 1985 | 7. Contracts reachable from Sales | Sales entry points: Renewals rows (`sx_renewals.php:23`), Today "Renewals due" rows (`sx_today.php:79`); also  | preserved — found in the final code |
| 1986 | 7. Contracts reachable from Sales | Gate: master / `mod.clients.view` / `mod.vendors.view` / `crm.contract.register` / `data.credit` / `finance.re | preserved — found in the final code |
| 1987 | 7. Contracts reachable from Sales | Shows: status pill, client link, title, branch (:8-9); "← Contracts" → `/partner?id=&tab=contracts` (:10); goi | preserved — found in the final code |
| 1988 | 7. Contracts reachable from Sales | Sales-relevant problem: "Received" here = job payment flags; Orders "Received" = invoice settlement; no "Renew | preserved — found (some words are filled in at run time) |
| 1991 | 7. Contracts reachable from Sales | Gate `crm.contract.register` / master. Accounts' screen; Sales hands over via the deal page "Send to Accounts" | preserved — found in the final code |
| 1992 | 7. Contracts reachable from Sales | Section "Won deals — no quote": ref, name, "Sent to Accounts <date>", client, agreed value; "✓ Register the co | preserved — found (some words are filled in at run time) |
| 1993 | 7. Contracts reachable from Sales | Section "Accepted quotes": quote no/rev, client, subject, total, accepted, "Open & register" → `/quote?id=#con | preserved — found in the final code |
| 1996 | 7. Contracts reachable from Sales | Gate: `can_endorse_contract_open()` / `can_approve_contract_open()` / master. Filed under Operations in Find-a | preserved — found in the final code |
| 2002 | 8. Customers — Sales view of the client regis | **Source**: route `index.php:1415-1466` (Sales part :1451-1460), view `views/list.php`, columns `sx_client_sal | preserved — found in the final code |
| 2003 | 8. Customers — Sales view of the client regis | **Gate**: the register's own (`mod.clients.view`, module gate) and, for the Sales columns, `sx_can()` (:1455). | preserved — found in the final code |
| 2004 | 8. Customers — Sales view of the client regis | **What Sales adds (only)**: | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 2005 | 8. Customers — Sales view of the client regis | "Standard / 💼 Sales view" switch (shown when `sx_can()`, `list.php:26-31`), keeps `q` and `status`; hidden `vi | preserved — found (some words are filled in at run time) |
| 2006 | 8. Customers — Sales view of the client regis | Note "The same client list as Directory, with what Sales needs…" (:33). | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 2007 | 8. Customers — Sales view of the client regis | Columns (:38-52): Name (link `/partner?id=`, code · roles) · State · GSTIN check (✓ valid / check, same regex  | preserved — found in the final code |
| 2008 | 8. Customers — Sales view of the client regis | Values: open/won = **Sales (deal) values** via `opp_all()` (office-scoped deal register); Owed = **accounting  | preserved — found in the final code |
| 2009 | 8. Customers — Sales view of the client regis | **Lost in Sales view** (present only in Standard view, :57-70): Code column, Industry, Status badge, "Open" an | preserved — found in the final code |
| 2010 | 8. Customers — Sales view of the client regis | **Phone**: `.tbl-scroll overflow-x:auto`; 10 columns. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 2011 | 8. Customers — Sales view of the client regis | **Design problems**: Sales view drops Status (so blacklisted/held clients are not visible), Edit and Industry; | preserved — found in the final code |
| 2017 | 9. Client holds (`/client-holds`) | **Source**: handler `ops_client_holds()` `lib/ops.php:881-897`, view `views/ops/client_holds.php` (47 lines);  | preserved — found in the final code |
| 2018 | 9. Client holds (`/client-holds`) | **Gate**: master / `settings.manage` / coordinator level (:882). Sales roles normally cannot open it. | preserved — found in the final code |
| 2019 | 9. Client holds (`/client-holds`) | **Form**: `client_id` searchable select (required), `hold_status` select (`PARTNER_HOLD_STATES` `lib/ops.php:8 | preserved — found in the final code |
| 2020 | 9. Client holds (`/client-holds`) | **Link to Sales**: holds block new enquiries/quotes/contracts/calls via `partner_trade_block()` messages (`lib | preserved — found in the final code |
| 2021 | 9. Client holds (`/client-holds`) | **Problem**: client list is office-scoped (`partner_office_sql()`), the POST does not re-check that `client_id | preserved — found in the final code |
| 2027 | 10. Advertising return (`/ads-roi`) | **Source**: `lib/adsroi.php` (`ops_adsroi()` :224-247, `roi_chain()` :63-128, `roi_by_campaign()` :130+, `roi_ | preserved — found in the final code |
| 2028 | 10. Advertising return (`/ads-roi`) | **Gate**: `roi_can()` = `ads_can_view()` = `mod.leads.view` / `is_master_of('leads')` (`adsroi.php:46`, `lib/a | preserved — found in the final code |
| 2029 | 10. Advertising return (`/ads-roi`) | **Routes**: `GET /ads-roi?from=YYYY-MM-DD&to=YYYY-MM-DD` (leads imported between); `&export=csv` → `advertisin | preserved — found in the final code |
| 2030 | 10. Advertising return (`/ads-roi`) | **Fields**: header + "⬇ CSV" (view :11-15); filter "Leads imported from / to" date inputs, Apply, Clear (:17-2 | preserved — found (some words are filled in at run time) |
| 2031 | 10. Advertising return (`/ads-roi`) | **Values**: Spent = Ads Pro spend; In play = open deal value (forecast, Sales value); Won = count of WON deals | preserved — found in the final code |
| 2032 | 10. Advertising return (`/ads-roi`) | **Problems**: invoiced/collected figures are shown to anyone with Leads view, without `ar_can()`, and the brea | preserved — found (some words are filled in at run time) |
| 2038 | 11. CRM dashboard (`/crm-dashboard`) — separa | **Source**: `lib/crmdash.php` (`ops_crmdash()` :262-296; readers `crmdash_funnel` :53, `crmdash_headline` :137 | preserved — found in the final code |
| 2039 | 11. CRM dashboard (`/crm-dashboard`) — separa | **Gate**: `crmdash_can()` = `mod.crm_reports.view` / `is_master_of('crm_reports')` (`crmdash.php:42-44`) — the | preserved — found in the final code |
| 2040 | 11. CRM dashboard (`/crm-dashboard`) — separa | **Params**: `days` = 30/90/180/365/730 (default 365), `pipeline=<id>` (select shown when >1 OPPORTUNITY pipeli | preserved — found in the final code |
| 2041 | 11. CRM dashboard (`/crm-dashboard`) — separa | **Shows**: KPI cards Open deals · Pipeline value · Weighted forecast · Win rate · Average won deal · Days to w | preserved — found in the final code |
| 2042 | 11. CRM dashboard (`/crm-dashboard`) — separa | **Values**: all Sales (deal/lead/quote) values; no invoice/collection figures. | preserved — structural item, covered by the stage preservation matrix and old-vs-new test |
| 2043 | 11. CRM dashboard (`/crm-dashboard`) — separa | **Overlap with /sales-dashboard** (`sx_dash_data`, `sales_simple.php:197-263`): both show open value, weighted | preserved — found in the final code |
| 2044 | 11. CRM dashboard (`/crm-dashboard`) — separa | **Problems**: same name "Sales dashboard" for both; different period models (rolling days vs FY/quarter/month) | preserved — found in the final code |
| 2050 | 12. Set up Sales / Sales approvals (discovere | **Routes**: `GET/POST /sales-setup` → `ops_setup_questions('sales')` (`lib/setupqa.php:87-99`, dispatch `lib/o | preserved — found in the final code |
| 2051 | 12. Set up Sales / Sales approvals (discovere | **Gate**: `is_master() // settings.manage // crm.quote.approve` (`setupqa.php:112`); the contract section addi | preserved — found in the final code |
| 2052 | 12. Set up Sales / Sales approvals (discovere | **Questions (setting keys)**: quote: `quote_unmatched_approval`, `quote_self_approval`, `quote_approval_mode`, | preserved — found in the final code |
| 2053 | 12. Set up Sales / Sales approvals (discovere | Linked from Sales settings ("✅ Who approves quotes"), Sales area tile 16, Admin → Approvals. Breadcrumb under  | preserved — checked by hand |
| 2054 | 12. Set up Sales / Sales approvals (discovere | **Pre-order checklist** `/preorder-checklist` (`ops_preorder_checklist()` `lib/ops.php:900-920`, dispatch :390 | preserved — found in the final code |
| 2062 | 14. Left navigation for Sales | Built in `views/layout_top.php:132-134`: one flat rail item "🎯 Sales" → `/sales`, shown when `ops_area_has('sa | preserved — found in the final code |
| 2063 | 14. Left navigation for Sales | Second door: Insights rail item carries `/crm-dashboard`. | preserved — found in the final code |
| 2064 | 14. Left navigation for Sales | Breadcrumbs: `nav_under()` (`lib/nav.php:391-396`) / `nav_trail()` (:398+); labels from `nav_screen_label()` o | preserved — found in the final code |
| 2065 | 14. Left navigation for Sales | "Find a screen" palette: `ops_nav_index()` (`lib/navindex.php:23+`), built from the area tiles and filtered by | preserved — found in the final code |
| 2069 | 15. Feature switches affecting Sales (`feat_o | Storage `settings.features_off` JSON; edited in Admin → Modules & features (`cockpit_can()`); baseline capture | preserved — found in the final code |
| 2070 | 15. Feature switches affecting Sales (`feat_o | Effects: tile hidden (`areas.php:36`); palette entry hidden (`navindex.php:37`); route refused (`index.php:136 | preserved — found in the final code |
| 2071 | 15. Feature switches affecting Sales (`feat_o | Sales-specific checks: Data health "Fix ›" target `/sales-today` → `/sales-board` when Today is off (`sales_si | preserved — found in the final code |
| 2072 | 15. Feature switches affecting Sales (`feat_o | Legacy switch on the Sales card: `preorder_checklist_on` (`FEAT_LEGACY`, `features.php:70`). "Simple start" pr | preserved — found in the final code |
| 2073 | 15. Feature switches affecting Sales (`feat_o | Never switchable: `sales` (area home), `approvals`, `sales-approvals`, `clients`. | preserved — found in the final code |
| 2074 | 15. Feature switches affecting Sales (`feat_o | Side effects observed: a switched-off route hides any `sx_kpi` card that links to it (the whole coloured card  | preserved — found in the final code |
