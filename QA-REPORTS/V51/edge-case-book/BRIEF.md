# EXAACT edge-case test book — brief for every helper (17 Nov 2026)

## Why
The owner (a non-developer) will test the app **by hand** on the TEST COPY
`https://opstest.mghaiapps.com`, with **actual numbers and examples**. They asked:
*"edge cases to test each and every button and screen without leaving anything … flow of the data
and so on … with actual numbers and examples."*

Your job: write the edge-case test book for **your area**, cover every screen and every button in it,
and check every expected result on a private copy of the **current** code.

## Ground rules (also read `/home/user/Experiment_Operataion_Exaact-requirement/QA-REPORTS/V51/AGENT-BRIEF.md`, sections Paths and "never")
- **Read-only:** the repo, `phpapp/`, `docs/`, `book.json`, `robot.js` and other helpers' files.
  - No code changes.
  - No git command that changes anything.
  - Do NOT write to the tracker (`qa.js`); the orchestrator does that.
- **Never touch** opstest, the live site, a real e-mail, a real payment or real customer data.
- **Business rules come only from:**
  - `docs/01-roles.md`;
  - `docs/02-permission-matrix.md`;
  - `docs/03-object-lifecycles.md`;
  - `docs/04-flows/*.md`;
  - the owner's decisions in `QA-REPORTS/V51/2*.md` (packs 10–30, especially 27–30, the latest);
  - the code itself, for what it does.
- **Never invent** a business rule, a financial policy, a threshold or a screen. If the right answer is unclear:
  - write the case anyway;
  - put the question in `question`;
  - state the impact.
- If the app does something that breaks a rule, the `expect` stays the **correct** behaviour. Record what you saw in `finding`.

## The test users and data on opstest (the same on your private copy)
- **Users:** the 19 test logins in `$R/harness/README.md`, for example:
  - MASTER_ADMIN, BUSINESS_DIRECTOR, SBU_HEAD, FINANCE;
  - COORDINATOR, COORDINATOR2, INSPECTOR, INSPECTOR2;
  - and the others listed there.
- **Their setup:** names, offices, reporting lines, the 8 "TB …" customers and the approval levels are in:
  - `docs/test-book/test-copy/setup_copy.py`;
  - `docs/test-book/source/setup_guide.py`.
- Use the **display names** the owner sees on screen, e.g. "Test Coord", and the opstest customers ("TB …").
- New records you ask the owner to create start with **`EC-`**, for example the customer "EC Steel Works Pvt Ltd" or the PO "EC-PO-0001". That keeps them apart from earlier test data.
- **Private copy of the CURRENT code** (batch 2, release 74a263f), with the same users and customers:
  - start it with `bash $R/ecworker.sh <your code> <your port>`;
  - run robot scripts with `cd $R/harness && ROBOT_DB=… ROBOT_BASELINE=… node robot.js <port> <ID>`;
  - list a screen's boxes and buttons with `node fields.js <port> <PERSONA> /route`;
  - run SQL with `php $R/harness/tq.php <db> '<sql>'`.
  - **Use only your port.** Do NOT use `$R/app` or `qaworker.sh`: that is the old baseline code.

## What to cover (your route list: `$S/ec/routes/<CODE>.txt`; tab-separated route, module, area, label)
1. **Every screen** in your list gets at least one case.
2. **Every button and action** on each screen gets at least one case: buttons, links that act, form submits, row actions, filters, exports and prints.
   - Find them with `fields.js` on your copy, logged in as a persona who sees the most, and by reading the view in `phpapp/views/...`.
   - Pure navigation links need no case; list them in the coverage file as "navigation only".
3. **Kinds of edge case.** Use each kind wherever it applies:
   - **NUMBERS:** exact calculations with real figures. Show the working in `expect`, for example "₹1,00,000 + 18% GST = ₹1,18,000.00 (CGST ₹9,000 + SGST ₹9,000, same state)". Also cover rounding to paise, discounts, IGST for another state, a TDS/retention if the app has one, and totals that must add up across screens.
   - **BOUNDARY:**
     - 0, 1, minus, the maximum, decimals (₹0.01, 33.333%), very large (₹99,99,99,999);
     - dates: today, past, future, 29 Feb, 31 Mar → 1 Apr (financial year rollover), end-of-month;
     - times near midnight India time.
   - **VALIDATION:**
     - required fields left empty, wrong format, duplicates (same PO number, same mobile);
     - very long text (500 characters);
     - names like O'Brien, "Shah & Sons", Hindi or Gujarati text, an emoji;
     - spaces only.
   - **PERMISSION:**
     - for each role, who may and who may not, per `docs/02`;
     - another office's record;
     - typing the address directly when the button is hidden.
   - **STATE:**
     - the button in the wrong status, for example approving something already approved, or editing after issue;
     - an old browser tab;
     - double-clicking Save;
     - Back after Save;
     - two people editing at once.
   - **FLOW (the data flow):** after the action, where the same data must appear next, with the same numbers. For example: "the invoice total ₹1,18,000.00 appears on the client's outstanding, the Money dashboard 'Unbilled' drops by ₹1,00,000, and the History tab shows 'Invoice INV-… created by Test Finance'". Name the exact screen and figure to check.
   - **BUTTON:** each button does exactly what its label says. A Cancel or Close saves nothing.
4. **Depth.**
   - Money, approvals, numbering, status changes and anything with a total: 5–15 cases per screen.
   - Simple registers: 1–3 cases.
   - Quality over count, but **leave nothing out**.

## Verify before you write the expectation
For each case, set `verified`:
- **"private copy"** if you ran it, by robot script, by hand through `node`/Playwright, or by a SQL check after the action. Put what you saw in `saw`, for example "₹1,18,000.00 shown; CGST 9,000.00".
- **"code reading only"** if it cannot be run on the copy (real e-mail, Razorpay page, a phone camera). Give the `basis` with file:line.

**Prefer the private copy for every number.** Robot scripts you write for this go in `$R/harness/cases/EC-<CODE>-NNN.js`. They are optional; a quick check is fine too.

## Output (write as you go, so nothing is lost)
- `$S/ec/out/<CODE>.jsonl`: **one case per line**, as JSON:
```json
{"id":"EC-MON-001","area":"Money","module":"Invoices","screen":"/invoice-new","buttons":["Save draft"],
 "kind":"NUMBERS","title":"Invoice for a same-state client splits 18% GST into CGST + SGST",
 "persona":"FINANCE","why":"A wrong tax split makes the GST return wrong.",
 "before":"Client TB Gujarat Steel (Gujarat, same state as your Ahmedabad office); job JOB-… closed with 4 man-days at ₹12,500/day.",
 "steps":[{"do":"Money → Invoices → + New invoice; pick TB Gujarat Steel; add the job; press Save draft.",
           "expect":"Taxable ₹50,000.00; CGST 9% ₹4,500.00; SGST 9% ₹4,500.00; Total ₹59,000.00. IGST is not shown."}],
 "flow":"Client page → Outstanding shows ₹59,000.00 only after Issue (a draft is not outstanding).",
 "basis":"lib/invoicing.php:812 gst_split(); docs/02 Money row",
 "verified":"private copy","saw":"Taxable 50,000.00; CGST 4,500.00; SGST 4,500.00; Total 59,000.00",
 "finding":"","question":""}
```
- `$S/ec/out/<CODE>-coverage.json`, structured as:
  - `{"routes":{"<route>":{"cases":["EC-…"],"buttons":{"<button label>":["EC-…"] or "navigation only" or "not shown to any test user: <why>"}}}}`;
  - every route in your list must be present.
- `$S/ec/out/<CODE>-findings.jsonl`: real faults you saw, as `{"case":"EC-…","screen":"…","what":"…","rule":"…","severity":"high|medium|low"}`.

## Writing style (the owner reads this on a phone)
- Plain English. No code words in `do` or `expect`.
- Use ₹ with Indian grouping (₹1,18,000.00) and dates like 17 Nov 2026.
- Menus as "Money → Invoices → + New invoice", and button labels exactly as on screen.
- One action per step. The expected result is something the owner can **see**.

## When you finish
Reply with:
- the number of cases;
- coverage: routes covered out of the total, and buttons covered;
- the findings, one line each;
- the open questions;
- anything you could not do and why.
