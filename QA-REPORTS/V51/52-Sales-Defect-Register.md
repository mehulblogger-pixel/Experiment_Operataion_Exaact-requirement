# EXAACT V51.1: Sales defect register

**Opened:** 21 Nov 2026. These problems were found while auditing the Sales screens (Phase 1 inventory) and building the lead page.

**What this register is**
- None of these problems were caused by the redesign. They were already in the code.
- None of them is fixed under the UI-only authorisation. Each one waits for your decision.
- **Status (21 Nov):** fixed — DEF-01, 02, 03, 04, 08, 09, 14, 15, 16, 19, 21, 25, 26, 27 (DEF-14, the deal part of DEF-15 and DEF-16 in Stage 3). Owner decisions recorded for DEF-05, 06, 07, 10, 11, 14, 15, 17, 23 (built in their stages). Still open — DEF-12, 13, 18, 20, 22, 24.
- **How Stages 2–6 are approved (owner, 21 Nov: option B):** one picture pack for Stages 2–6, one OK, then built stage by stage, with evidence after each stage.
- **Owner, 21 Nov ("All as recommended"):**
  - **Stages 2–6 designs** (`EXAACT-Sales-Stages2-6-Designs.pdf`, 28 screens) approved, with all 20 backend changes. Questions: (1) the preparer may withdraw a quotation waiting for approval back to draft, with a note (a new lifecycle move; docs/03 updated when built); (2) renewal contract value hidden from people without the contract-money right; (3) a renewal call is logged on the client's timeline with the contract number; (4) quotations decided, and contract signatures listed, in the Approvals inbox; (5) /crm-reports renamed "Quotation report"; (6) paging and sorting on the Enquiries and Quotations registers; (7) DEF-07 "Received" built as recommended; Finance to confirm on opstest.
  - **Permission screen** (`EXAACT-Permission-Screen-Redesign-Proposal.pdf`): Q1–Q9 and Q11 yes, each new switch starting with exactly the people who can do it today; Q10 (whose records: own / team / branch / all) decided after Stage 2.
  - **Q10 — owner, 21 Nov: "Keep this."** A per-role setting (with a per-person override) for whose records a person sees: Own / Team (theirs + people who report to them) / Branch / All. Day one = today's behaviour for every role. Build with Stage 6 and the "Who can do what" screen.
  - **Owner, 21 Nov — (1) "Finance may open the deal."** The Deals register and deal page to open on "View deals" alone; Finance's default gets "View deals" (view only). **Not built yet — one question raised in Stage 3 (report 57):** opening on "View deals" alone would also let the Branch Manager and the Business Unit Head open deals (they hold "View deals" since foundation A and see deals on Sales home and search, but are refused on opening one). (2) The customer ledger follows branches, but central finance (all branches) sees every customer's ledger and details — Stage 5. (3) Finance confirmed DEF-07 with a nuance: Received = payments + TDS only where TDS was actually deducted on that receipt (never an assumed rate); credit notes separately — Stage 5.
  - **Documents after Stage 6:** a Sales edge-case test book (Excel to fill in, plus PDF; whole journey; every role; every fixed defect; run under the TPIA, recruitment, technical manpower and auditor industry templates); one status document (built / fixed / left out / pending); a customer-facing product document (USP, features, benefits, how it works, per industry) with a short internal appendix. Drafts after Stage 2.

**Severity used**
- **High:** wrong people can see money, data is lost, or a task cannot be done.
- **Medium:** a figure is wrong or misleading, or a workflow is broken.
- **Low:** wording or tidiness.

## Security review: DEF-01 (done 21 Nov, as you asked)

**Finding (confirmed in the code).** The *Advertising return* screen (`/ads-roi`) and its CSV export show, per advertising campaign:
- the money **invoiced** to customers who came from ads;
- the money **actually collected** from them.

**Who can see it:** anyone who can view **Leads**. The screen does not ask for the receivables right that every other money screen in Sales asks for (Finance, or credit control). It also does not limit itself to the person's own branches. A salesperson in Mumbai sees the invoiced and collected totals of every branch's ad leads.

**Where in the code:**
- `lib/adsroi.php`: `roi_can()` checks only `mod.leads.view`.
- The invoice and collection query has no branch filter.

**Why it is wrong:** your permission rules (docs/02) say Sales screens show receivables only to people with the receivables right, and only for their own branches. This screen breaks that rule.

**When it applies.** The screen exists only when Ads Pro data is present, and the "Simple start" setting switches it off. I cannot check opstest or live from here (production is not touched).

**What you can do today, with no code change:** Admin → Feature switches → switch off **Advertising return**, if you don't use it.

**Recommended fix (needs your OK, because it changes who sees what):**
1. Show the *Invoiced*, *Collected* and *Outstanding* figures and their CSV columns only to people with the receivables right.
2. Count only the person's own branches.
3. Everyone else still sees spend, leads, won and "in play".

This brings the screen in line with docs/02. It adds no right to anyone.

**Fixed, 21 Nov 2026** (owner: "proceed with your recommendation"; and "Yes, branch-limited users will no longer see cost per lead or return per rupee").
- Measured with the real roles: BDM, KAM and Marketing Manager already hold the receivables right. With the standard roles, the **Marketing Executive** (and any custom role with Leads view) saw money it should not. The bigger gap was branches: a Mumbai-only BDM saw every branch's invoices.
- Now: leads and deals are the viewer's branches only. Invoiced, collected and not-yet-received figures (and their CSV columns) appear only with the receivables right. Cost per lead and return per rupee appear only to someone who sees every branch. The Business Director, Admin and Master Admin see exactly what they saw before.
- The *What to fix* item about advertising that produced nothing is made only for someone who sees every branch.
- Parked by the owner: splitting ad spend by branch (PENDING.md).

## The register

| ID | Severity | Where | What is wrong | Suggested fix | Needs |
|---|---|---|---|---|---|
| DEF-01 | **High** | Advertising return | Invoiced and collected money shown without the receivables right, for every branch. The links in it go to screens these users cannot open. | See above | **Fixed 21 Nov** (`tests/test_def01_ads_roi_money.php`, 23/23) |
| DEF-02 | **High** | Quotation edit | Saving an edited quotation drops each line's sub-type and deliverables, because the form no longer sends them. | Send them back with the form | **Fixed 21 Nov** (batch 1, `tests/test_def_batch1.php`) |
| DEF-03 | **High** | Quotation milestones, phone | The "Releases retention" choice is hidden under 640 px wide. A schedule with retention cannot be saved from a phone. | Show it on phones | **Fixed 21 Nov** (batch 1). Re-checked in a real browser: on the current code the choice was already visible at 390 px (the hiding rule only reached the read-only list); the phone-hiding class is removed so it cannot be hidden by a later CSS change. `tests/test_def_batch1.php` |
| DEF-04 | **High** | Pre-order checklist | Rewording a checklist point clears its ticks on **every** quotation, including old ones. | Keep old ticks; reword only going forward | **Fixed 21 Nov** (batch 1): a started checklist keeps the points it was ticked against; a changed list applies to quotations not yet started. `tests/test_def_batch1.php` |
| DEF-05 | Medium | Quotation revision | A revision does not copy the attachments. | Copy them, or link to the earlier revision's files | **Fixed 21 Nov** (Stage 4): a revision lists the earlier revisions' files, "from Rev NN", linked not copied. `tests/test_sales_stage4.php` |
| DEF-06 | Medium | Quotations, Orders, Board, reports | The quote register, reports, external quotes and "Orders won" show totals **including** GST. The board, approval bands and contract value use the total **before** GST. | One rule with clear labels on every screen | **Fixed 21 Nov for quotations** (Stage 4): before GST is the main figure on the register, the quotation page and the Quotation report (incl. GST labelled beside it; the report's CSV carries both). Orders follow in Stage 5. `tests/test_sales_stage4.php` |
| DEF-07 | Medium | Orders, Advertising return, Contract | "Received" means three different things (settled including TDS and credit notes; job payment flags). | One definition, clearly labelled | **Owner decided 21 Nov: A** — Received = receipts + TDS; credit notes shown separately; outstanding = invoice − received − credit notes. Confirm with Finance before build (Stage 5). |
| DEF-08 | **High** | Orders & payments, Customers, Advertising return | Some figures ignore the branch and the person filter: "Received this month", "Days to get paid", Customers "Owed to us", and every Advertising return figure. | Apply the same branch / person filter | **Fixed 21 Nov** (DEF-01 for Advertising return; batch 1 for the rest, `tests/test_def_batch1.php`). Owner's principle: every figure follows the person's permissions and branch scope as set in Roles & access. |
| DEF-09 | Medium | Receivables (Sales view) | "Open →" passes the invoice number to the job page, which opens the wrong page. The "Due" column always reads "not set". | Open the invoice; show its due date | **Fixed 21 Nov** (batch 1, `tests/test_def_batch1.php`) |
| DEF-10 | Medium | Renewals | The list includes pending, rejected and closed contracts. It looks 90 days ahead, while Today and Sales home look 30 days ahead. | Active contracts only; one window from Settings | **Owner decided 21 Nov:** active contracts only; one look-ahead window in Sales settings, starting at 60 days. Build in Stage 5. |
| DEF-11 | Medium | Targets | Only management roles can see everyone or set targets; the Sales manager roles cannot. Clearing a box saves 0 instead of "no target". The history shows only the last 30 changes. | Decide who sets targets; blank = no target; full history | **Owner decided 21 Nov: A** — management plus holders of `mod.leads.edit` or `crm.quote.approve` set targets for people in their branches; blank = no target; full history. Build in Stage 5 (docs/02 updated then). |
| DEF-12 | Low | Sales home | If Targets is switched off, the "your target was changed" notice can never be dismissed. | Dismiss it on Sales home itself | Your OK |
| DEF-13 | Low | Sales settings | Its links to lookups and holidays need admin level, which the screen itself does not require. | Hide the links or explain who can open them | Your OK |
| DEF-14 | Medium | Enquiries | No view-only page exists. Opening an enquiry needs edit rights, and saving returns to the list. | A read-only enquiry page | **Fixed 21 Nov (Stage 3)** — the inquiry's own read-only page `/inquiry?id=`, opened with the Inquiries view right and the register's branch / business-unit scope; Edit, Quote, Drop and the history are on it; saving returns to it. `tests/test_sales_stage3.php` |
| DEF-15 | Low | Leads, deals | The server accepts some fields that no form shows (lead SBU, deal probability, deal SBU). Some filters exist but have no button. | Show them, or remove them from the server | **Fixed 21 Nov** — leads in Stage 2; deals in Stage 3: business unit on the new-deal form and "Edit the details" (units in the person's scope; not sent = unchanged), and a typed win % is not taken — it follows the stage. `tests/test_sales_stage2.php`, `tests/test_sales_stage3.php` |
| DEF-16 | Low | Deal form, stage gates | Wrong wording. The deal form says a deal with only a typed name cannot be quoted, but it can since 13 Nov. "Drag" is mentioned, but no board supports dragging. | Correct the words | **Fixed 21 Nov (Stage 3)** — the deal form says a name-only deal can be quoted (since 13 Nov); no screen tells anyone to drag. `tests/test_sales_stage3.php` |
| DEF-17 | Low | Insights / Sales | Two screens are both called "Sales dashboard" (/sales-dashboard and /crm-dashboard). | Rename one, e.g. "Sales funnel report" | **Owner decided 21 Nov: yes** — rename /crm-dashboard to "Sales funnel report". Build in its stage. |
| DEF-18 | Medium | Quotation page | Some buttons and the server rules disagree: + External, file delete, master upload on a locked quote, Edit hidden for APPROVED, and the playbook "Edit" link drawn without checking rights. The server rules decide, so a button can appear that then refuses. | Make each button follow its server rule | **Fixed 21 Nov** (Stage 4): + External, file ✕, Attach (master on a sent one), Edit on APPROVED and the playbook Edit follow their server rules. `tests/test_sales_stage4.php` |
| DEF-19 | Medium | Lead edit (older forms) | An older form posting to /lead-edit without the manpower fields would wipe manpower deputation. The new lead page sends them, so it is safe. | Keep the fields when they are not sent | **Fixed 21 Nov** (batch 1, `tests/test_def_batch1.php`) |
| DEF-20 | Medium | Dashboard / Operations home speed | The shared "Your next actions" strip ignores its "actions only" setting, so the slow management pulse is worked out on every visit. | Respect the setting | Your OK |
| DEF-21 | Low | Lead "How promising" | On one test lead the score said "nobody has logged a conversation" while its History showed a logged call. | Check which records the score counts | **Fixed 21 Nov (Stage 2):** the score read only conversations logged against the lead's *client*, so a lead with no client yet (most leads) always said "nobody has logged a conversation". It now also counts the calls, WhatsApps, e-mails, meetings and site visits logged on the lead itself — the entries its History shows — and takes the later of the two. A "note to self" does not count. `tests/test_sales_stage2.php` |
| DEF-22 | Medium | Advertising return | A deal won through quotation → contract → work is reported as "Won, but no order was ever raised", because the deal carries no work-order link. | Follow the quotation / contract link too | Your OK |
| DEF-23 | Low | Quotations | The "quotes-export" and "quote-final" addresses exist but do nothing. | Remove or finish them | **Fixed 21 Nov** (Stage 4): /quotes-export and /quote-final open the Quotations register. `tests/test_sales_stage4.php` |
| DEF-24 | Medium | Plan limits (outside Sales) | Found while re-testing, 21 Nov. The existing test `test_m8_public_background_entitlement` fails 3 of its 125 checks on the current version (9f4a530), before any Sales change. Under a plan limited to Operations and Reporting, the hiring, quotes and invoicing background jobs still run. | Make background jobs follow the plan limit | To check, then your OK |
| DEF-25 | Medium | Customer 360 / customer screen | Found in batch 1. "Owed" on Customer 360 and the customer screen is the whole customer's figure across every branch (`books_outstanding()`), while the client register's Sales view now shows only the person's branches. The Sales view's figure links to that page, so a branch-limited person sees a larger figure one click later. | Same branch rule as the Sales view, under the owner's principle ("data shown as per the permission set") | **Fixed 21 Nov** (batch A, owner: "All as recommended"): Owed, overdue, ageing and the invoices listed on Customer 360 count only the viewer's branches; "Overdue" on Orders & payments follows the salesperson picked. `tests/test_perm_a_switches.php`; docs/02 19s. The customer ledger and the advisor keep the whole-customer figure. |
| DEF-26 | Low | Roles & permissions (outside Sales) | Found in batch B. A role the company added (e.g. "PA Field Sales", based on Marketing Executive) could be opened and saved on the old Roles screen, but the save went under its own name, which the program never reads — the role always uses its base role's switches. The save looked accepted and changed nothing. | Show the base role's switches, locked, with a link to change them there | **Fixed 21 Nov** (batch B, "Who can do what"): the new screen does exactly that; the old screen is unchanged and stays reachable for one release. `tests/test_whocan_screen.php`; docs/02 19t. |
| DEF-27 | Medium | Person form (outside Sales) | Found in batch B. Since a person's own access is stored as "@diff,…" (batch A, Q11), the guard "you cannot remove your own user-management access" looked for the bare words in that text. A branch user manager saving their own record with any exception was refused, and a "-users.manage…" one would not have been caught. | Read what the stored value grants | **Fixed 21 Nov** (batch B): `user_access_keeps_user_mgmt()`, used by the person form and the new screen. `tests/test_whocan_screen.php`; docs/02 19t. |

## Suggested order

1. **Security and branch scope:** DEF-01 and DEF-08.
2. **Data that is lost or wrongly changed:** DEF-02, DEF-04, DEF-19.
3. **Tasks that cannot be done:** DEF-03 (phone retention), DEF-09.
4. **Business decisions:** DEF-06, DEF-07, DEF-10, DEF-11. These are one short meeting with Finance and Sales.
5. **The rest**, when their screen's redesign batch comes up.
