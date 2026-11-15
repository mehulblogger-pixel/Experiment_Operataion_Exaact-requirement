# EXAACT V51 — Stage 1 discovery report and execution plan

Report date: 15 Nov 2026 · Prepared by: A00 Master QA Orchestrator · Status: **awaiting owner approval before Stage 3**

Everything below was measured, not assumed, unless it says "estimate".

## 1. What is being tested

| Item | Finding |
| --- | --- |
| Source | Git repository, branch `claude/testing-branch-setup-0gqe8n`. The app is in `phpapp/` (1,551 tracked files in the repo). |
| Version | **V51 + the v51.1 change** (commit `c4530f7`, 15 Nov 2026: "Create quotation asks revise or new; history says who created each quotation"). V51 itself is commit `333cb40`, confirmed identical to the V51 zip you uploaded. The only difference between V51 and what is tested is v51.1, which you approved and received as a zip. |
| Code | 1,472 PHP files (244 libraries, 433 screens), 25 JavaScript files, 1 stylesheet. No SQL files: the database is created and upgraded by the PHP code itself (343 table definitions). |
| Database | **328 tables** on a fresh copy. Production runs MySQL; every test here runs on SQLite copies with made-up data. |
| Screens and actions | **480** routes in the app's own permission map, in **30** permission modules and **10** menu areas. Buttons inside the screens are not counted yet (Stage 3 crawler). |
| Existing automated tests | **722** test files in `phpapp/tests`. A full baseline run on v51.1 is in progress. |
| Existing test book | **1,431** test cases (EXAACT Test Book, 22 Oct 2026). All 170 Sales cases have robot scripts; the other 1,261 do not yet. |
| Separate apps in the repo | `licence-server/` (licence issuing), `mgh-hire/`, `mgh_books_receiver/` (books bridge). Whether they can run here is still to be checked (L-13). |
| Deployed sites | **Not reachable from here.** The network policy refuses connections to opstest and live (L-01), so it is not yet known whether they run V51 or V51.1. |

### Your 12 modules, as found in the app

| Your module | In the app | Test cases |
| --- | --- | --- |
| Setup and Masters | Admin → Masters, company set-up | from "Start here" and set-up sections |
| Directory | Directory | 58 |
| CRM and Sales | Sales | 170 |
| Operations | Operations | 147 |
| People and Organisation | Recruitment, people, organisation chart | 133 |
| Reporting / IDEMS | Reporting | 119 |
| Quality and Accreditation | Quality & Accreditation | 235 |
| Money and Finance | Money | 152 |
| Insights and Dashboards | Insights | 46 |
| Licensing and SaaS | New company, licence, subscription screens | 48 + licence items |
| Client Portal | Client portal screens | within Admin/Directory |
| Admin and Security | Admin | 150 |
| **Marketplace** (not in your list) | Marketplace | 136 |
| End-to-end | "Across modules" | 15 |

## 2. Capabilities (Part 23, step 1)

| Capability | Status | Detail |
| --- | --- | --- |
| Complete V51 source | AVAILABLE | Full repository, with history |
| Terminal and file system | AVAILABLE | |
| Database access | PARTIALLY AVAILABLE | Full access to the private SQLite test copies. No access to production MySQL, by your rule and by the network. |
| Working test environment | AVAILABLE | Private copies of the app with 19 test users (every role), 8 made-up "TB" clients and the approval levels; up to 9 copies at once |
| Browser automation | AVAILABLE | Playwright + Chromium |
| Multiple independent agents | AVAILABLE, with limits | Real separate agents, but they share one machine: about 8 browser lanes at a time |
| Coordinating agent updates | AVAILABLE | Append-only event log; the Excel workbook is generated from it |
| Excel and PDF reports | AVAILABLE | Excel, Word, and PDF via LibreOffice |
| Screenshots and logs | AVAILABLE | Screenshots, browser console errors and failed network requests (shown in the demo) |
| Modify code | AVAILABLE — **only after your approval** | Your standing instruction |
| Run regression tests | AVAILABLE | 722 automated tests + robot re-runs |
| Reach the test and live sites | UNAVAILABLE | Network refused (L-01) |
| Real phones | UNAVAILABLE | Phone-size emulation only (L-06) |
| MySQL-identical testing | UNAVAILABLE now | Possible if you approve a local MariaDB test server (L-02) |

## 3. Safe test environment (Part 23, step 3)

- Every test runs on a **private copy** of the app inside this machine, at `127.0.0.1`, never on opstest or live.
- Each test starts from the same clean database. Agents use **separate copies** (one database per lane), so they cannot overwrite each other.
- Data is made up: "TB …" clients, "Test …" users. No customer or employee data is used.
- Code fixes, once you approve them, are made in the repository and run through the regression suite before you see an update zip. The update zip keeps the rules in CLAUDE.md: no `config.local.php`, `tenants.php`, `tests/`, databases or data folders.

## 4. The live tracker

`QA-REPORTS/V51/EXAACT_LIVE_TESTING_TRACKER.xlsx` — generated from `data/events.jsonl` (append-only, one line per change, never edited) by `tools/qa.js build`.

Sheets: 1 Live Dashboard · 2 Module Tracker · 3 Agent Tracker · 4 Screen & Button Tracker · 5 Test Case Register · 6 Incomplete & Pending · 7 AI Cannot Do / Cannot Test · 8 Defect Register · 9 Fix & Retest · 10 Business Workflows · 11 Finance & Billing · 12 Evidence Register · 13 Historical Findings · 14 Activity Log.

- **Completion %** = applicable test cases executed ÷ applicable test cases × 100.
- **Pass rate %** = tests passed ÷ tests executed × 100.

Blocked, pending, not-implemented and cannot-test items never count as completed. Only your 14 status labels are used.

## 5. Demonstration test cycle (Part 23, step 6) — DEMO-01

Screen: Sales → Inquiries → "+ New inquiry", as the Marketing Executive.

| # | Check | Result |
| --- | --- | --- |
| 1 | Open the form through the menu | PASS |
| 2 | Empty form sent past the browser's own checks: does the **server** refuse it? | PASS — "Please add a client …; the contact person; a contact e-mail or mobile; the business unit; a subject." 0 rows saved |
| 3 | Valid data → Create inquiry | PASS — "INQ-00001 created." |
| 4 | Database read-back | PASS — exactly one new row in `crm_inquiries` with the typed client, e-mail, unit, source, status OPEN, created by Test MktExec |
| 5 | Same form sent twice (Back, then Create again) | **FAIL — a duplicate INQ-00002 was created** |
| 6 | Shows in the register | PASS |
| 7 | Browser console errors / failed requests | PASS — none |

Result: **COMPLETED – FAIL**, defect **DEF-0001 (P2)** raised.
- Root cause: the inquiry form has no single-use token; the quotation form has one.
- The fix is proposed and awaits your approval.
- An earlier run of the demo stopped at step 4 because my test script used a wrong table name. That is recorded in the activity log as a script error, not an app defect.
- Evidence: `evidence/DEMO-01-*.png`, `evidence/DEMO-01-db-row.json`, `evidence/DEMO-01-result.json`.

## 6. Execution plan

**Stage 2 — parallel, by module** (each agent on its own private copies):

| Agent | Work |
| --- | --- |
| A04 | Script and run the test book, module by module. Order: Operations → Money → Reporting → Admin & Security → Directory → People → Quality → Insights → Marketplace → Licensing/Portal. |
| A02 | Crawler: open every screen as every role, list every button, link, tab and form, and press each safe one. |
| A03 + A06 | Forms: empty, wrong formats, too long, duplicates, double-submit (DEF-0001 pattern), server-side checks. |
| A07 | Security: every role against every route (direct URL), CSRF, XSS, SQL injection, file access, client-data separation. Copies only. |
| A10 | Phone viewport run of every screen and the key flows. |
| A11 | Page and query timings on a copy with a larger made-up data set. |
| A05 | Database: orphans, keys, duplicate cost paths (job closure vs inspector vouchers). |

**Stage 3 — coordinated cross-module** (in order, because each needs the previous one's records): WF-01 … WF-07 and the 27 billing scenarios MD-01…15 and MM-01…12 (A08 + A09), with a reconciliation sheet per scenario.

**Stage 4 — fixes and regression:** each defect gets an ID, severity, root cause and proposed fix. **Nothing is changed without your approval.** After each approved batch:
1. The 722-test suite and the affected robot cases are re-run.
2. The tracker is updated.
3. You get one update zip.

## 7. Time estimate (initial — to be revised after the first two modules)

| Phase | Effort (agent time) | Runs in parallel with |
| --- | --- | --- |
| 1 Source and documentation review | 0.5 day — in progress | 2, 4 |
| 2 Environment set-up | done; +0.5 day if MariaDB approved | 1 |
| 3 Automation set-up | done for the robot; crawler 0.5 day | 4 |
| 4 Module discovery (button crawl) | 1 day | 3 |
| 5 UI and button testing | 1–2 days | 6, 10, 11 |
| 6 Forms and validation | 2–3 days | 5 |
| 7 End-to-end workflows | 2–3 days | after 5–6 per module |
| 8 Finance and billing (27 scenarios) | 3–4 days | after 7 (needs contracts and jobs) |
| 9 Database integrity | 1–2 days | 5–8 |
| 10 Security | 2–3 days | 5–8 |
| 11 Mobile | 1–2 days | 5–8 |
| 12 Performance | 1 day | 9 |
| 13 Defect correction | unknown until defects are counted; typically 3–6 days | after your approvals |
| 14 Regression | about half a day per fix batch | after each 13 |
| 15 Reports and owner acceptance | 1–2 days + your review time | end |

**Calendar estimate: about 3 to 5 weeks of working sessions.** Most phases run in parallel; the critical path is test-book scripting (1,261 cases), then billing, then fix rounds.

What could stretch it:
- many defects;
- waiting for your decisions on rules with no fixed answer (Sales alone has about 30 such steps);
- the deployed-site question;
- the separate licence apps turning out not to run here.

**Sales robot run (v51.1):** 168 of 170 cases finished. The last 2 are very long screen checks; the per-module documents follow when they finish.

## 8. What I need from you

1. **Approve this plan** so Stage 3 can start.
2. **Deployed version:** send a screenshot of `https://opstest.mghaiapps.com/deploy-check.php` (and the live one), **or** allow this environment read-only access to opstest.
3. **MySQL fidelity:** may I install a local MariaDB test server, with made-up data only, to run the suite and key flows on the same engine as your host?
4. **Fixes:** may I fix approved defects in batches, each batch tested before you get a zip? And do you want to approve each defect, or approve by severity (for example "fix all P2/P3, ask me for P0/P1 and anything touching money or approvals")?
5. **Scope:** include **Marketplace** (136 test cases), which is in the app but not in your 12-module list?
6. **Held work:** V51 is tested as it is; v52 and v53 stay on hold until you decide. Confirm.
