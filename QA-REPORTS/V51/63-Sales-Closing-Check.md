# EXAACT V51.1 — Sales redesign: closing check (report 63)

**Date:** 21 Nov 2026 · **Code:** branch `claude/testing-branch-setup-0gqe8n`, fixes `9f38df2`, release stamp `f5ac7ed` (751 files) · **Rollback point:** `9f4a530` (before the Sales redesign).

You asked that nothing be missed. Before the final package, everything built since 21 Nov — Stages 1–6, the permission switches and the "Who can do what" screen, Q10 (whose records a person sees), and the customer GSTIN / PAN batch — was checked again **as one piece**, on the final code.

## 1. What was checked, in one table

| Check | Result |
|---|---|
| Every automatic test file (779 files) | **27,712 checks pass. 12 fail — every one fails identically on the version before the redesign (`9f4a530`), all outside the Sales screens.** (The release-stamp test fails only before the stamp is made; 14/14 after it.) Details in §2. |
| The robot: all 104 cases in a real browser, compared case by case with the last full run | **No case worse.** 46 passed (was 45), 23 failed (was 24), 24 incomplete, 10 need a person, 1 out of date. SAL-134 now passes (your Branch Manager decision). §3. |
| Every Sales screen at 8 screen sizes (50 screens × 8 = 400 checks) | **No sideways scrolling, no cut-off text, no PHP message, no page error.** Controls under 32 px only on 4 screens outside the redesign, listed in §4. |
| Phase 1 inventory (57 pages, 965 items) | **None removed without your decision, none missing.** 5 changed by your decisions. §5 and the full list in report 63-Inventory-Tickoff. |
| Your decisions (§6) and the defect register (§7) | Every decision built and tested; 33 of 34 defects fixed. **Only DEF-24 is open** (outside Sales, needs your OK). |

## 2. Automatic tests

- **Run:** all 779 test files, three at a time, on the final code (90 minutes). Files that stopped during the parallel run (shared temporary files) were run again on their own and pass: contract billing amendment 16/16, contract duplicate guard 8/8, onboarding welcome 17/17, pending scheduling 4/4, agency bench 11/11, polish text 5/5, the four cloud-company tests (7/7, 30/30, 9/9, 20/20) and the main-site address 38/38.
  - One test had left a cloud-company registry file in the working folder (`phpapp/tenants.php`, never in git or a zip), which made two cloud tests fail; it was removed and both pass. This is a test-tidiness issue, not the application.
- **Fixed in this check (our work had caused them):**
  - *Master-entitlement guard* — the Stage 5 rule "who sees a renewal's contract value" was not yet classified; classified (it sits behind the Sales gate). 93/93.
  - *Journey test (add the customer from a won deal)* — still expected the rule before your Branch Manager / SBU Head read-only decision; updated to the read-only rule. 42/42.
  - *Sales settings* — a comment closed PHP mode (the page output was right, but fragile); written the safe way. 8/8; Stage 6 test 1146/1146.
- **Still failing — identical on `9f4a530`, outside the Sales redesign:**

| Test | Checks | What it is, in plain words |
|---|---|---|
| Plan limits on background jobs (m8) | 3 | **DEF-24.** Under a plan limited to Operations and Reporting, the hiring, quotes and invoicing background jobs still run. Needs your OK. |
| Report exports under a plan limit (m7) | 6 | A company without Sales can still reach three Sales download addresses (quote PDF, quote export, template download) by typing them. Same family as DEF-24. |
| Wording count (b4 terminology) | 1 | The words list has 33 entries where the test expects 32 — a test count to update, nothing broken. |
| Back-office staff form (m2) | 1 | The back-office staff form does not offer the live department list. Not Sales. |
| Lookup links (master & lookup links) | 1 | The test does not know the "Buying role (contacts)" list that Sales settings links to; the list exists. A test to update. |

## 3. Robot (104 cases)

- The robot copy was updated to the final code (old files backed up) and every case was run.
- **Seven cases met your new rules and their scripts were updated to the new correct behaviour — no check was weakened:**
  - **GSTIN / PAN when a customer is won (your D1–D3):** FLOW-01, SAL-160, SAL-180, SAL-182 now type a made-up but valid GSTIN (real check character) when a quotation for a new name is accepted, a lead is converted, or a customer is added from a won deal. The state is read from the GSTIN.
  - **"Sales dashboard" renamed "Sales funnel report" (DEF-17):** INS-033, SAL-030, SAL-120.
- **One real gap found by the robot and fixed:** since the quotation page has its six tabs, the GST amount was only on the "Lines & milestones" tab. Your approved design shows it on **Overview** ("Commercials and totals"). Overview now shows Before GST, GST (with its CGST + SGST or IGST split) and Total with GST. Stage 4 test 1857/1857, Stage 5 62/62.
- **Final: 46 passed · 23 failed · 24 incomplete · 10 need a person · 1 out of date** (last full run: 45 · 24 · 24 · 10 · 1). The failures and incomplete cases are the same ones as before the redesign (they test other modules or need data the robot copy does not have).

## 4. Screen sizes

- 50 Sales screens (Sales menu, Sales home as admin and as a BDM, Today, Pipeline board ×2, Sales dashboard, Leads ×2, quick and full lead form, lead page, conversion, Enquiries, enquiry page and form, Deals ×2, deal page and form, Quotations, quotation page / edit / new / external, costing list, cost heads, templates, Approvals, checklist, Quotation report, approval rules, delegations, Orders & payments, Renewals, Targets, client register Sales view, Customer 360, customer record, new customer, invoice, Sales settings, Set up Sales, Sales approvals, Pipelines, Stage gates, Advertising return, Sales funnel report, Who can do what ×2) at 1920, 1440, 1366, 1024, 768, 430, 390 and 360 px.
- **No sideways scrolling, no cut-off text, no PHP message, no page error on any of the 400.**
- **Fixed in this check:** the "attach files" box on the two lead forms was the browser's own 28 px; now 36 px.
- **Controls under 32 px, outside the redesign (left as they are):** the "Command Centre →" link on the Sales menu page's shared action strip; the "360" button in the client register (an existing 28 px switch); the Customer 360 page buttons; two invoice buttons ("Record money received", "Customer ledger"). Changing them would change every module that shares them.

## 5. Phase 1 inventory tick-off

Every bullet of the 57-page inventory you received on 21 Nov was checked against the final code — routes, field names, functions and on-screen words searched for in the program; items that describe layout or behaviour are covered by each stage's preservation matrix (reports 51, 56–60) and by the stage tests that draw every page old against new as 12–13 real people.

| Result | Items |
|---|---|
| Found in the final code | 573 |
| Found (some words are filled in at run time, e.g. "Rev NN", the word for "client") | 38 |
| Checked by hand (wording built from your terminology settings) | 12 |
| Layout / behaviour item, covered by the stage preservation matrix and old-vs-new test | 336 |
| **Changed by your decision** | **5** — amounts before GST (DEF-06), one renewal window (DEF-10), "drag" wording removed (DEF-16), lead History heading (approved lead page), the leads banner pointing to a column that exists |
| Not an item (an observation in the audit) | 1 |
| **Removed without a decision / not found** | **0** |
| **Total** | **965** |

## 6. Your decisions — every one checked

| Decision (21 Nov unless stated) | Built | Evidence |
|---|---|---|
| Stage 1: Sales home is the Sales landing page; /sales and /sales-home both kept | Yes | test_sales_stage1 428/428 |
| Stage 1: Sales-only phone bottom bar; dark teal for Sales only; global header untouched | Yes | test_sales_stage1; 8-size check |
| Advertising return: money only with the receivables right, own branches only; branch-limited users see no cost per lead / return per rupee | Yes | test_def01_ads_roi_money 23/23 |
| Ad spend split by branch | Parked by you ("later") | PENDING.md |
| Option B: one picture pack for Stages 2–6, then build | Done | reports 56–60 |
| DEF-05, 06, 07, 10, 11, 14, 15, 17, 23 as recommended | Yes | §7 |
| Stages 2–6 designs, 20 backend changes, 7 answers (withdraw to draft; renewal value hidden; renewal call on the client's timeline; decide in the Approvals inbox + contract signatures; "Quotation report"; paging and sorting; "Received") | Yes | test_sales_stage2 846, stage3 1259, stage4 1857, stage5 62, stage6 1146 |
| Permission switches Q1–Q9, Q11 (nobody gains or loses on day one) | Yes | test_perm_a_equivalence 12/12, test_perm_a_switches 68/68 |
| "Who can do what" screen | Yes | test_whocan_screen 27/27 |
| Q10 — Own / Team / Branch / All (day one = today) | Yes | test_q10_scope 37/37 |
| Sales manager: own list by default, can open reportees' lists | Yes | test_q10_scope |
| Finance does not open deals; Branch Manager and SBU Head open and download their branch / unit's leads and deals, read only | Yes | test_bm_sbu_lead_view 40/40; robot SAL-134 |
| DEF-25: customer page follows branches; "Overdue" follows the salesperson | Yes | test_perm_a_switches |
| Customer ledger by branch; central finance sees every customer | Yes | test_sales_stage5 |
| "Received" = payments + TDS only where deducted; credit notes separate | Yes | test_sales_stage5 |
| Duplicate names ignore Pvt Ltd / Ltd / LLP … (Q-02) | Yes | test_gstin_billing |
| GSTIN + PAN when a customer is won; one customer per PAN with billing locations; exceptions; existing customers in Data health; no new permission (D1–D4) | Yes | test_gstin_billing 72/72; robot FLOW-01, SAL-160, SAL-180, SAL-182 |
| Test book, status document, product document | Next, after this check | — |

**Still waiting for your answer:** records with no owner (an unassigned lead or enquiry) for people set to **Own** or **Team** — visible in their branch (recommended) or hidden. Nobody is set to Own or Team today, so nothing is affected yet.

## 7. Defect register — every item

| ID | Severity | Where | Status | Evidence |
|---|---|---|---|---|
| DEF-01 | High | Advertising return | Fixed | test_def01_ads_roi_money |
| DEF-02 | High | Quotation edit | Fixed | test_def_batch1 |
| DEF-03 | High | Quotation milestones, phone | Fixed | test_def_batch1 |
| DEF-04 | High | Pre-order checklist | Fixed | test_def_batch1 |
| DEF-05 | Medium | Quotation revision | Fixed | test_sales_stage4 |
| DEF-06 | Medium | Quotations, Orders, Board, reports | Fixed | test_sales_stage4, test_sales_stage5 |
| DEF-07 | Medium | Orders, Advertising return, Contract | Fixed | test_sales_stage5 |
| DEF-08 | High | Orders & payments, Customers, Advertisin | Fixed | test_def_batch1 |
| DEF-09 | Medium | Receivables (Sales view) | Fixed | test_def_batch1 |
| DEF-10 | Medium | Renewals | Fixed | test_sales_stage5 |
| DEF-11 | Medium | Targets | Fixed | test_sales_stage5, test_perm_a_equivalence |
| DEF-12 | Low | Sales home | Fixed | test_sales_stage5 |
| DEF-13 | Low | Sales settings | Fixed | test_sales_stage6 |
| DEF-14 | Medium | Enquiries | Fixed | test_sales_stage3 |
| DEF-15 | Low | Leads, deals | Fixed | test_sales_stage2, test_sales_stage3 |
| DEF-16 | Low | Deal form, stage gates | Fixed | test_sales_stage3 |
| DEF-17 | Low | Insights / Sales | Fixed | test_sales_stage6; robot INS-001/002/004, INS-033, SAL-030, SAL-120 |
| DEF-18 | Medium | Quotation page | Fixed | test_sales_stage4 |
| DEF-19 | Medium | Lead edit (older forms) | Fixed | test_def_batch1 |
| DEF-20 | Medium | Dashboard / Operations home speed | Fixed | test_sales_stage6 |
| DEF-21 | Low | Lead "How promising" | Fixed | test_sales_stage2 |
| DEF-22 | Medium | Advertising return | Fixed | test_sales_stage6 |
| DEF-23 | Low | Quotations | Fixed | test_sales_stage4 |
| DEF-24 | Medium | Plan limits (outside Sales) | Open — needs your OK | To check, then your OK |
| DEF-25 | Medium | Customer 360 / customer screen | Fixed | test_perm_a_switches |
| DEF-26 | Low | Roles & permissions (outside Sales) | Fixed | test_whocan_screen |
| DEF-27 | Medium | Person form (outside Sales) | Fixed | test_whocan_screen |
| DEF-28 | Medium | Client edit | Fixed | test_gstin_billing |
| DEF-29 | High | Excel import | Fixed | test_gstin_billing |
| DEF-30 | Low | Invoice "not ready to issue" | Fixed | test_gstin_billing |
| DEF-31 | Low | Sales home → Data health | Fixed | test_gstin_billing |
| DEF-32 | Medium | Client edit | Fixed | test_gstin_billing |
| DEF-33 | Medium | Invoice page | Fixed | test_gstin_billing |
| DEF-34 | Low | Invoice print | Fixed | test_gstin_billing |

## 8. Installing on opstest

1. **Back up** the opstest app folder (and its database) first.
2. Upload `EXAACT-V51.1-Sales-Complete-TEST-COPY-update.zip` and **extract it in the opstest app folder only**. It holds every change since `9f4a530` (Stages 1–6, permissions, Q10, GSTIN / PAN and all fixes).
   - It does **not** contain `config.local.php`, the root `tenants.php`, the `tests/` folder, any `*.sqlite` file or the `exaact_data*` / `exaact_backups*` folders, so opstest keeps its own settings and data.
3. Open **/deploy-check.php** — every line should be green.
4. The red **TEST COPY** bar must still be at the top of every page.
5. If anything is wrong, **restore the backup** from step 1.
6. Never copy files from the opstest folder to the live site. When testing is complete, I will ask: "Shall I prepare the live update?"

## 9. Left open

- **DEF-24** (plan limits on background jobs) and the m7 download addresses — outside Sales, need your OK.
- **Unassigned records for Own / Team** — your answer (above).
- **Targets in ₹ lakh** — the boxes stay in rupees (as before), so a figure can never be read 1,00,000 times too big; say if you want lakh entry.
- **Old permission screen** — kept one release behind "Old layout"; remove after you have used the new one.
- Small controls outside the redesign (§4).

## Addendum — F-03, found by the full test book (21 Nov 2026)

The robot run for the full Sales test book (case O-09) found that Today and Sales
home still **wrote** "in 30 days" (from the older contract-warning setting) while,
since DEF-10, they **count** renewals over the window set in Sales settings
(default 60 days). Example: the Today tile counted 4 contracts ending within 60
days but labelled them "in 30 days".

Fix: `views/ops/sx_home.php` and `views/ops/sx_today.php` now take the label from
`sx_renewal_days()`, the same window as the count. Words only; no rule, figure or
permission changed. New test `tests/test_f03_renewal_label.php` (7/7). Re-run
after the fix: stage1 428/428, sales_simple 31/31, sales_book_round2 35/35,
stage5 62/62, stage6 1146/1146, q10_scope 37/37.
