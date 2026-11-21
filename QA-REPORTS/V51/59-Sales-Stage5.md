# EXAACT V51.1 — Report 59: Branch Manager and SBU Head access, quotation tabs, Sales Stage 5

**Date:** 21 Nov 2026 · **Built to:** your answers of 21 Nov and the approved Stages 2–6 design pack ("All as recommended") · **Rollback point:** bd3b6d6 (Stage 4)

## In one paragraph

Three pieces of work, each saved on its own. **(1)** As you decided, the **Branch Manager** can now open, read and
**download** the leads and deals of their branch, and the **SBU Head** those of their business unit — read only;
**Finance still does not open deals**. **(2)** The quotation page now has the **six tabs** of the approved design.
**(3) Stage 5:** Orders & payments, Renewals and Targets in the new Sales look, and the client register's Sales
view inside the Sales workspace, with your decisions: **Received = payments + the TDS actually deducted**
(credit notes apart), **before-GST** order values, an **Orders CSV**, **open contracts only** with **one renewal
window (60 days to start)**, **contract values only for people who may see contract money**, **renewal calls
logged on the client's timeline**, **who sets targets** (now also the Marketing Manager, for their branches),
**empty box = no target**, the **full target history** and a **year picker**. The **customer ledger follows
branches**; central Finance (every branch) still sees every customer whole. Defects closed: DEF-06 (Orders),
DEF-07, DEF-10, DEF-11, DEF-12 and test-book finding F-01.

## 1. Branch Manager and SBU Head — leads and deals (C1)

Your words: *"finance does not need to see the details of deal. However branch manager and SBU head can sure
open the lead and check all other details … Yes but yes they shall be able to download."*

| Who | Before | Now |
|---|---|---|
| **Branch Manager (Mumbai)** | Saw deals on Sales home and the board, but "You cannot open" on a lead or a deal | Opens the Leads and Deals lists, every lead and deal page **of Mumbai**, and downloads both lists (Mumbai rows only). Cannot add, edit, move, mark won/lost or delete. |
| **SBU Head (Industrial)** | Same as above | Opens and downloads the leads and deals **of the Industrial unit, in every branch**. An Oil & Gas lead or deal is refused. Read only. |
| **Finance** | No deal pages | **Unchanged** — still no deal pages. Sales home, Today, orders and payments as before. |
| Everybody else | — | **Unchanged** (proved below). |

Example: the Mumbai Branch Manager opens deal "Tank calibration — Mumbai, Industrial, ₹4,80,000" and reads it;
pressing "Edit" or "Mark won" is refused and the deal is unchanged. The Ahmedabad deal is refused.

Also opened for them, read only and within their branch / unit: the Pipelines tile, the stage-gate register
and *What the advertising actually earned* (money only with the receivables right, as since DEF-01). The Sales
bar's "Leads" link now works for them (test-book finding **F-01**). New rights appear on "Who can do what", so
you can switch them off for anyone.

## 2. The quotation page — six tabs (C2)

**Overview** (next step, the approval chain with Approve / Reject, pre-order checklist, costing, client and
commercials) · **Lines & milestones** · **Approvals** (new: the full approval record of this and earlier revisions —
level, who, status, when, comment) · **Documents** · **Contract & order** · **History & follow-ups**.
Every panel is the one that was there; only its tab changed. The decision buttons stay on Overview, as the
design shows ("Your decision" and "Approval chain" are on Overview). Links such as "Pre-order checklist",
"Contract" or "Documents" at the top open the right tab by themselves.

## 3. Stage 5 — Orders & payments, Renewals, Targets, Client register

| Screen | What changed | Example (test copy) |
|---|---|---|
| **Orders & payments** | New look; **Orders won before GST** (with GST beside each order); **Received = payments + TDS deducted**, with the TDS shown; **Reduced by credit note** as its own figure; an **Invoices** column with links; **CSV** button. Billing and payment figures only with the receivables right, as before. | Q-…-0866: order ₹16,00,000 before GST (₹18,88,000 with GST); billed ₹9,44,000; received ₹1,88,800 (no TDS on that receipt); credit note ₹60,000 shown apart; status "Payment due". |
| **Renewals** | New look; **open contracts only**; window **60 days** to start (Sales settings; "example value" until you set it); **contract value only for people who may see contract money**; **Log a call** writes on the client's timeline with the contract number; contract number links only for people who may open contracts. | A pending and a rejected contract no longer appear. A Marketing Executive sees "TBP/2025/044 · 14 days" without the ₹8,40,000. |
| **Targets** | New look; **year picker**; **empty box = "no target"** (kept in the history as "₹4,00,000 → no target"); **full history, 30 per page**; setters as you decided (DEF-11 A). | Test BDM's November target 4 L → 6 L shows "↻ was ₹4 L" and the history row "▲ ₹2 L". |
| **Client register — Sales view** | Inside the Sales workspace (section bar, phone bottom bar); list, filters and figures unchanged. | — |
| **Sales home** | "OK, seen" on a target change is answered on Sales home itself (works even when Targets is switched off). | DEF-12 |
| **Customer ledger** | Follows the person's branches; **central Finance (every branch) sees every customer whole**; the "What to fix" unmatched-receipts and silent-customer balances follow the same rule. | Mumbai Finance sees the Mumbai invoices of TB Valves; central Finance sees Mumbai and Ahmedabad. |

**One renewal window for every Sales screen (DEF-10):** Renewals, Today and Sales home now look the same
distance ahead (60 days to start). The field to change it on the Sales settings screen comes with Stage 6; the
setting is already saved by Sales settings.

## 4. What changed in who can do what (written in docs/02, items 19x and 19y)

1. **Branch Manager and SBU Head** — Leads view and the two downloads, read only, own branch / unit (your decision).
2. **Marketing Manager** — "Set sales targets for others", for the people of their own branches (DEF-11 A: holders of quotation approval). Management and the Master Admin as before.
3. **Narrowed:** contract values on Renewals (Q2); the customer ledger and its balance for branch-limited people (your ledger decision).

Nothing else. A test compares every role and 41 test people with the snapshot of 21 Nov: the only
differences are the gains in 1 and 2, for those roles.

## 5. Tests

| Check | Result |
|---|---|
| `test_bm_sbu_lead_view` (open, download, branch / unit limits, every write refused, Finance and others unchanged, the one-time rule reaches saved settings) | 40 / 40 |
| `test_perm_a_equivalence` (every role and 41 people vs the 21 Nov snapshot; only the intended gains) | 12 / 12 — 59 intended gains, nobody else differs |
| `test_sales_stage5` (DEF-06, 07, 10, 11, 12, Q2, Q3, CSV, ledger, client register, every screen as 6 roles) | 62 / 62 |
| `test_sales_stage4` on the six-tab quotation page (old vs new as 13 people) | 1857 / 1857 |
| Related test files — C1 (65 files) | only the three known failures that also fail on bd3b6d6 (m2 org structure ×1, m7 ×6, m8 ×3 = DEF-24) |
| Related test files — C2 + C3 (139 files: approvals, books, receivables, contracts, sales stages 1–5, leads, deals, quotations, permissions, scope) | 139 / 139 pass. One file (`test_sales_d01_leads`) first failed 23 / 24: its check "only the D-01 rule is new" did not know the two 21 Nov rules. The test now marks every later rule as applied (commit 27a855f); 24 / 24 on the new code and on bd3b6d6. |
| Screen sizes — 10 pages × 8 widths, real browser | 80 checks, no sideways scroll, nothing cut off, no PHP message. Small controls only on the ledger (not a Stage 5 screen) and the register's Standard / Sales view switch (28 px, unchanged). |
| Robot — all 59 cases that open a quotation page, run on the new code and on the Stage 4 code, step by step | 58 of 59 end exactly as on Stage 4 (same result in every step). The one difference is **SAL-134** and is intended: it still expects the Branch Manager to be refused Leads, Deals and Pipelines, which you have now allowed (read only; Pipelines "change" is still refused). See section 5a. |

### 5a. Robot detail

Six robot scripts looked for the old tab names ("History", the lines table under "Overview"). They now press
"History & follow-ups" or "Lines & milestones" first; each was backed up as `*.bak_s4tabs` before the change:
SAL-052, SAL-055, SAL-062, SAL-064, SAL-177, SAL-180 (and, in Stage 4's pass, SAL-060, SAL-068, SAL-102, SAL-103,
lib-g3). After that, every step of those cases gives the same result as on Stage 4 — including steps that were
already failing or waiting on you before (for example SAL-180 steps 8–9, who sends an approved quotation), which
are not changed by this work. **SAL-134** (Branch Manager's Sales tiles) now sees Leads, Deals and Pipelines open:
this is your 21 Nov decision; the robot's expected answer will be updated in the robot book. Pressing a change on
Pipelines is still refused for the Branch Manager (they do not hold "Manage pipelines").

There are no robot cases yet for Orders & payments, Renewals or Targets; those screens are covered by
`test_sales_stage5` (62 checks) and the screen-size check.

## 6. Not done / for you

- **Targets in ₹ lakh:** the picture shows lakh; the boxes stay in rupees as before, so a figure is never read
  1,00,000 times too big or too small. Say if you want lakh entry.
- **The Approvals tab** on the quotation page is the record; the live decision stays on Overview.
- **The Sales settings field** for the renewal window is drawn in Stage 6.
- **Renewal call** is saved from the Renewals screen itself (no new address); it uses the client's existing timeline.
- **Side effect of your Branch Manager / SBU Head decision:** the Pipelines tile, the stage-gate register and
  *What the advertising actually earned* now open for them — read only, their branch / unit. Say if you want
  any of these closed again; each can be switched off on "Who can do what".

## 6a. The update for the test copy

`EXAACT-V51.1-Sales-Stage5-TEST-COPY-update.zip` — 842 files, flat (extract straight into the app folder of
opstest). SHA-256 `76b93a4b89890694a424acf07f56cc539c0d9f7f691c560879582bed55e49cbc`. Stamp 27a855f.
It does **not** contain `config.local.php`, `tenants.php`, the `tests/` folder, any `*.sqlite` file or the
`exaact_data*` / `exaact_backups*` folders, so the test copy stays the test copy and keeps its data. Back up
first, extract, open `/deploy-check.php`. Commits: a88afc2 (Branch Manager / SBU Head), 94af1f7 (quotation tabs),
48af9e4 (Stage 5), 27a855f (test), bc400d7 (stamp).

## 7. Your check list on opstest (12 checks)

1. Sign in as the **Branch Manager**. Sales → Leads opens; only your branch's leads; ⬇ CSV downloads them. Open a lead: no Edit / Mark lost buttons.
2. As the Branch Manager open a deal of your branch: it opens; of another branch: refused.
3. Sign in as the **SBU Head**: leads and deals of your unit from every branch; another unit's deal is refused.
4. Sign in as **Finance**: Deals is still not offered; Sales home and Orders & payments open as before.
5. Open a quotation: six tabs. Approve / Reject (when it waits on you) is on Overview; the **Approvals** tab lists every signature.
6. **Orders & payments** as the Business Director: "Orders won" says before GST; each order shows "… incl. GST" under it.
7. Record a receipt with TDS for one invoice and one without TDS for another: **Received** adds TDS only for the first; a credit note appears under **Reduced by credit note**, not in Received.
8. Press **CSV** on Orders & payments: the file has before GST, with GST, payments, TDS, received, credit notes.
9. **Renewals**: a contract waiting to be opened (pending) or rejected is not listed; "60 days" is the starting window.
10. As a **Marketing Executive**, Renewals shows contracts without their value. As a BDM, **Log a call** on one: it appears on the client's page with the contract number.
11. **Targets** as the **Marketing Manager**: only the people of your branches; empty a box and Save — the history says "→ no target". Pick last year from the Year list.
12. **Ledger** of a customer billed by two branches: branch Finance sees only theirs; central Finance sees both.
