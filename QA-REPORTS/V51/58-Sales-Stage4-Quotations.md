# EXAACT V51.1 — Report 58: Sales redesign, Stage 4 (Quotations, costing, templates, approvals)

**Date:** 21 Nov 2026 · **Built to:** the approved Stages 2–6 design pack (owner: "All as recommended") · **Code:** d83578a (stamp 203bd8d) · **Rollback point:** 79de494 (Stage 3)

## In one paragraph

The quotation screens now have the Sales look: the quotation register (with paging, sorting, columns and
better filters), the quotation page (a header card and three figures — before GST first), and every other
Stage 4 screen inside the Sales frame. Five approved backend items are built: **Withdraw to draft** for the
person who prepared a quotation, **deciding a quotation from the Approvals inbox**, **contract signatures in
the inbox**, the register's new search and filters, and the two dead addresses fixed. Four defects are
closed: DEF-05 (a revision shows the earlier revisions' files), DEF-06 (before GST is the main figure for
quotations), DEF-18 (buttons follow the server's rules) and DEF-23 (dead addresses). Nobody gained a right;
the one new move (Withdraw to draft) is the one you approved and is written into docs/03.

## 1. What changed, screen by screen

| Screen | What you will see | Example |
|---|---|---|
| **Quotation register** `/quotes` | Sales bar; five figure tiles (plus "Awaiting your approval" when you have some); one row of tabs (All · Open · Pending · Won · Lost · Expired) with "Awaiting your approval" and "To register a contract"; a search that also finds the **contact** and the **contract number**; filters **Assigned to**, **Branch**, **Business unit** (they only narrow your own rows); the shared register with **paging (25/50/100), sortable columns and a Columns chooser**; Export as before. | Q-AMD-2026-0907 shows **₹4,50,000** and under it "₹5,31,000 incl. GST". Typing "CON/2026/0142" finds the quotation with that contract. |
| **Quotation page** `/quote?id=` | Sales bar; the chain strip; a header card — number, status, "Rev 01 · 2 versions", client · subject, PDF / Word / Edit / Raise; three figures — **Before GST**, **With GST 18%**, **Validity**; everything below exactly as before (playbook, approval chain, checklist, costing, tabs Overview / Jobs & order / History / Sites & docs). | Before GST ₹6,80,000 · With GST ₹8,02,400 · Validity 30 days. |
| **Withdraw to draft** (new) | On a quotation waiting for approval, its preparer sees "Withdraw to draft — take it back from its approvers to change it". A note is required. | "Client added two sites" → the quotation is a Draft again; the Branch Manager it was waiting on gets an e-mail; the history says who, why, and which levels had already signed. |
| **Approvals inbox** `/approvals` | Figure tiles per queue; **Quotations** decided in the row (comment + Approve / Reject); **Contracts waiting for a signature** signed in the row; deal stage moves and lead hand-overs as before. | The Branch Manager approves Q-AMD-2026-0912 without opening it and lands back on the inbox. |
| **Quotation form**, **External quotation**, **Templates**, **Approval rules** and **form**, **Delegations** (from Sales), **Pre-order checklist**, **Project costing** list, page and heads | Inside the Sales frame (Sales bar on a laptop, bottom bar on a phone), page head and panels in the card look; every field, button and rule unchanged. Never inside the in-page popup (the costing opened over a quotation keeps its plain look). | — |
| **Quotation report** `/crm-reports` (owner Q5: renamed from "Sales dashboard") | Every value is **before GST** and says so; the CSV has both "Before GST" and "Total with GST". | A won Q-AMD-2026-0882 counts ₹15,60,000 (before GST), not ₹18,40,800. |

## 2. The owner's decisions built in this stage

| Item | Built | How it is checked |
|---|---|---|
| 8 — register: paging, sorting, columns, owner/branch/unit filters, search on contact and contract no.; CSV behind "Download quotations" | Yes | test_sales_stage4 §8 (search by contact, by contract number, owner filter, branch filter never widens, paging, CSV scope, no CSV without the switch) |
| 9 — Withdraw to draft (preparer only, with a note; new move in docs/03) | Yes | §9 (button only for the preparer; others refused by the server; note required; open step WITHDRAWN, signed level kept; history line; a draft cannot be "withdrawn") |
| 10 — DEF-05: earlier revisions' files, "from Rev NN", linked | Yes | §DEF-05 (Rev 01 lists Rev 00's file; still one file of its own; opens from Rev 00) |
| 11 — DEF-06: before GST main figure (register, quotation page, Quotation report) | Yes (quotations). Orders follow in Stage 5. | §DEF-06 (₹4,50,000 / ₹5,31,000 on the page; report labelled and totalled before GST) |
| 12 — decide from the Approvals inbox | Yes | §12 (only the person the level waits on; comment to reject; same outcome as the page; back to the inbox; logged) |
| 13 — contract signatures in the inbox | Yes | §13 (listed for the signer, signed from the inbox, opens the contract; none for a BDM) |
| 14 — DEF-23: dead addresses → register | Yes | §DEF-23 |
| DEF-18 — buttons follow the server | Yes | §DEF-18, for every one of the 13 people: the file ✕ and the Attach form show exactly when the server allows it; Edit on APPROVED; master may attach to a sent quotation |
| Q5 — "Quotation report" | Yes | §DEF-06 |

## 3. Nothing underneath changed (except what you decided)

- **No new permission, status or table.** One new *move*, PENDING_APPROVAL → DRAFT ("Withdraw to draft"), approved and written in docs/03; approval steps it closes are marked WITHDRAWN and kept.
- **No figure changed** except the before-GST rule you chose (DEF-06): the register and page now lead with the before-GST value and still show the with-GST total; the Quotation report's values are before GST.
- **Access:** nobody gained a right. People who sign contract openings may now open the Approvals inbox, which shows them only their own signatures (docs/02 19w). Buttons that only led to a refusal are gone (DEF-18).
- **Files changed:** `lib/crm.php`, `lib/approvals_hub.php`, `lib/contracts.php`, `lib/ops.php`; views `crm/quote_list`, `crm/quote_detail`, `crm/quote_form`, `crm/quote_external`, `crm/template_list`, `crm/template_form`, `crm/reports`, `crm/approval_rule_list`, `crm/approval_rule_form`, `approval_delegations`, `approvals`, `project_costing`, `project_costings`, `costing_heads`, `preorder_checklist`; `assets/css/app.css` ("SALES — QUOTATIONS…" block); docs 02, 03, 05; register 52; PENDING.md. Old views kept in `tests/fixtures/pre_stage4/`.

## 4. Preservation — every inventory item (Phase 1 inventory, section E)

Every screen was drawn **twice, old and new, as 13 real people** (Business Director, Master Admin, Admin, Business Unit Head, Branch Manager Mumbai, Finance Mumbai, Finance all branches, Coordinator, BDM, KAM, Marketing Manager, Marketing Executive, BDM without the download right) on **23 pages** — 299 comparisons. For each one the test checks that **every figure reads the same, every link, every form (address and every field) and every amount of the old page is on the new one, and refusals are unchanged**. The only differences allowed are the ones you decided, and each is checked on its own (§2).

| Inventory item (section E) | Where it is now | ✓ |
|---|---|---|
| §1 Register: title, "Awaiting your approval" / "To register a contract" variants, sub-line count | Page head; banners for the two lists; "n shown" | ✓ |
| §1 Duplicate-number warning (quote no + rev, contract no) | Red banner above the tabs, same pills | ✓ |
| §1 Cards Open / Pending / Closed (won) / Lost / Expired, each a link | Five tiles, same figures, same links (+ Awaiting your approval) | ✓ |
| §1 Tabs all/open/pending/closed/lost/expired; search q; FY | Tab row (Closed (won) reads "Won"); search form with FY | ✓ |
| §1 mine=approve / mine=contract | Same lists, own banners; tiles and search kept | ✓ |
| §1 Columns Quote, Client, Subject, Unit, Total, Contract, Status (+past validity), Open | Quote (+Rev), Client · subject, Unit, Before GST (+incl. GST), Contract, Status (+past validity), Open | ✓ |
| §1 Buttons Export, Approval rules, Templates, + External, + New | All kept; + External only for its server's right (DEF-18) | ✓ |
| §1 "pending" contract pill on APPROVED | Now on won quotations only (the design's correction) | ✓ |
| §2 Quotation form — every field, section, costing tab, signature, sites, lines | Unchanged inside the Sales frame | ✓ |
| §3 Quotation page — chain strip, status, versions, Raise call, PDF, Word, Edit, unprintable warning, jump bar, "where it stands", locked panel, edit requests, actions row, costing, checklist (+ waive), approval chain (+ approve/reject, take back), lost box, revision box, playbook, client, commercials, lines, GST split, milestones, calls and jobs under the order, contract registration (+ group contracts, duplicate warning, one-click, manual form, signatures), lost reason, revisions and history, follow-ups, sites, documents (+ attach) | All kept in place; header redrawn as a card with three figures; Edit also on APPROVED; documents add the earlier revisions' files; ✕ and Attach follow the server | ✓ |
| §4 PDF / Word / print routes | Unchanged | ✓ |
| §5 External quotation — every field and check | Unchanged inside the Sales frame | ✓ |
| §6 Approval rules list and form | Unchanged inside the Sales frame | ✓ |
| §8 Delegations (quotation cover) | Unchanged; Sales frame when opened from Sales only (vouchers and recruitment keep theirs) | ✓ |
| §9 Templates, letterhead, signature | Unchanged inside the Sales frame | ✓ |
| §10 Pre-order checklist configuration | Unchanged inside the Sales frame | ✓ |
| §11a Approvals inbox — every section, quotation/costing rows, lead hand-over form, deal gate blocks (waiting on you / somebody else / decided), status filter, Withdraw, empty state, total waiting, Approval rules link | All kept; quotations and contracts can now be decided in the row | ✓ |
| §12–14 Costing list, costing page and print, costing heads | Unchanged inside the Sales frame (popup excluded) | ✓ |
| §15 CRM reports | Renamed "Quotation report"; values before GST (labelled); CSV both | ✓ |

## 5. Screen sizes

8 widths (1920, 1440, 1366, 1024, 768, 430, 390, 360) × 12 pages = 96 checks in a real browser: **no sideways scrolling, nothing cut off, no PHP message.** Buttons inside the restyled panels are now at least 34 px tall (several were 27–28 px). The only controls under 32 px left are the company set-up reminder that the app shows administrators on every page (not a Stage 4 screen).

## 6. Tests

| Check | Result |
|---|---|
| `tests/test_sales_stage4.php` (13 people × 23 pages old vs new; items 8–14; DEF-05/06/18/23) | **1,857 passed, 0 failed** |
| Full test suite (774 files) | All 774 files: **26,373 checks pass, 18 fail in 10 files — none caused by Stage 4** (see below) |
| Robot, all 104 cases, compared case by case with Stage 3 | **Identical to Stage 3, case by case and step by step**: 46 passed, 23 failed, 24 incomplete, 10 need you, 1 out of date |

**The 18 failing checks, each one looked at:**
- *Fail the same way before Stage 4 (79de494):* terminology count (33 terms where 32 are expected); a comment in `sx_settings.php` that ends PHP mode.
- *The release stamp:* the deploy checker reads "stale" until the stamp is written; after the stamp it passes (14/14).
- *Pass when run on their own, on the new code:* `test_m6_api_action_entitlement` (93/93), `test_b5_area_counts` (36/36), `test_gate4_self_approval` (142/142), `test_perm_a_equivalence` (11/11), `test_rb3_demo_unload` (23/23), `test_module01_masters` (16/16), `test_p6_batch3` (355/355 — on the old code its timing race failed once). They fail only when the suite runs in several parts at once (they share a cache file and a settings file) or after particular earlier tests.

**The robot.** The first full run had 10 cases worse: all of them press "+ New quote", and the new button read "New quote" beside an icon. The exact old words are back ("+ New quote", "+ External"); those 10, and 2 whose middle steps used the same button, were re-run and now match Stage 3 exactly. No robot script was changed.

## 7. Not done in this stage (listed, not dropped)

- **The quotation page's inner panels** keep their layout; the design's six tabs (Overview, Lines & milestones, Approvals, Documents, Contract & order, History) would move panels the robot finds by wording — left for a later step, in PENDING.md.
- **DEF-06 on Orders & payments** comes in Stage 5 with DEF-07.
- **Finance opening deals** — still your question from Stage 3 (it would also open deal pages to the Branch Manager and BU Head).

## 7a. Screenshots

Before and after, laptop (1366) and phone (390), at the end of this report.

## 8. Your check list on opstest (12 checks)

1. **Sales → Quotes.** The register opens with the Sales bar; values show before GST with "incl. GST" under them.
2. Type a **contact's name**, then a **contract number**, into the search — each finds its quotation.
3. Pick **Assigned to** a colleague — only their quotations remain. Press **Reset**.
4. Click the **Before GST** heading — the list sorts; choose **25 / page** — paging appears. Use **Columns** to add "Branch".
5. **Export** still downloads the CSV. A person whose download switch is off sees no Export.
6. Open a quotation: the header card and the three figures (Before GST, With GST, Validity) are right.
7. As its **preparer**, open one **waiting for approval** → "Withdraw to draft", write a note → it becomes a Draft; the history says so. As someone else, the option is not there.
8. As the **Branch Manager** it waits on, open **Approvals**: the quotation has Approve / Reject in its row. Reject without a comment → refused. Approve → you stay on Approvals; the quotation moves on.
9. Make a contract waiting to be opened; as its signer, open **Approvals** → "Contracts waiting for a signature" → sign → it opens.
10. Open a **revision** (Rev 01) whose Rev 00 had a file → Documents shows it "from Rev 00".
11. As someone with Quotes edit but not "create quotations": no "+ External", no file ✕.
12. **Insights → Quotation report** (was "Sales dashboard" at /crm-reports): values say "before GST".
