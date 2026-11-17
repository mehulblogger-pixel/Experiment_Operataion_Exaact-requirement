# Roadmap to revenue — where we are and what is left (17 Nov 2026)

**One page to end the confusion.** Read this first; everything else is detail.

## 1. Where we are today

| Area | State |
| --- | --- |
| **Test copy (opstest)** | Latest program, release **74a263f**, installed today. Includes the security fix, India time, the branch & access fixes and batch 2 |
| **Live site (operations)** | Still on the **20 Oct 2026** program, about 4 weeks behind. Its settings are now safe for updates (moved to `config.local.php`) |
| **What we know about the program** | Every screen (480) has been tested twice: the test book (1,431 tests) and the edge-case book (991 tests). We know **281 faults**, **47 of them serious**. Most come from **14 root causes**, so they are fixed in a few batches, not one by one |
| **Your decisions so far** | Branch & access (done), batch 2 (done), V1–V5 (yes), expenses design v3 (OK), the working rules (now in the project rules) |
| **Waiting for you** | Answer sheet 2 (Money) by week 4; answer sheet 3 (Edge cases): one line, *"themes 1–14 as proposed"* |

## 2. Your decision: launch with all modules at once (17 Nov 2026)

You chose to launch with the **complete application**:
- Sales; Work orders & jobs; Reports; Invoices & payments; Expenses;
- Recruitment; Quality & Accreditation; Marketplace; client and vendor portals;
- Sign-up, subscription, users, Insights.

So every module gets the same treatment, one after another:
1. **Pictures** of the simplified screens, for your OK.
2. **Build and test** by me: the automatic suite and the robot.
3. **Your 10–15 checks** on opstest.

## 3. The plan, week by week (from 18 Nov 2026)

| Week | Dates | Module(s) |
| --- | --- | --- |
| 1 | 18–24 Nov | **Expenses**, plus the **foundation fix across all modules**: one office check on every action, and closed records stay closed |
| 2 | 25 Nov–1 Dec | **Reports** (with your V3, V4, V5) and **Quality & Accreditation** (they work together) |
| 3 | 2–8 Dec | **Work orders & jobs**, then the **first live update** if you say testing is complete |
| 4 | 9–15 Dec | **Invoices & payments** (needs your Money answers) |
| 5 | 16–22 Dec | **Sales**: enquiry, costing, quotation, contract |
| 6 | 23–29 Dec | **Recruitment** |
| 7 | 30 Dec–5 Jan | **Marketplace** and the **client and vendor portals** |
| 8 | 6–12 Jan | **Sign-up, subscription payment, users & roles, Insights** |
| 9 | 13–19 Jan | Full re-test of everything, your final check, **live update with the go-live checklist** |

**Ready to sell with all modules: around 19 January 2027**, if each of your OKs comes within 1–2 days.

**Honest limits of this estimate:**
- It moves by the number of days any answer takes.
- My working time is limited by the account's usage limit.
- A module can show something unexpected.
- The realistic range is **19 January to early February 2027**.

**Your time:** about 1–2 hours a week. In weeks with two modules, about 2 hours.

## 4. Testing — who does what (no more storm)

| Who | What | How often |
| --- | --- | --- |
| **Me: automatic suite** | About 21,000 checks of rules, permissions and figures, on two database types | Before every zip |
| **Me: robot** | Real screens, clicked like a person, before and after every change | Before every zip |
| **You** | **Only** a short check list per module (10–15 checks, about 30–45 minutes): *does this make sense for my business?* | Once per module |

- **Pause the 991-test edge-case book and the old Part C/D.** They test screens that are about to change. Your result taps stay saved.
- Your manual testing tonight was valuable: it found things only a business owner sees (the voucher screen, approvers editing claims). That kind of feedback is exactly your part.

## 5. Your decisions now

| # | Decision | My recommendation |
| --- | --- | --- |
| L1 | Launch with all modules | **Your decision: yes.** The plan above includes them all |
| L2 | Edge answer sheet 3 | **"Themes 1–14 as proposed"**. Themes 1 and 2 go into week 1's foundation fix; each change is still explained with examples first |
| L3 | Money answer sheet 2 | **Before week 4** (needed for invoices and payments) |
| — | Expenses "existing vs needed" table (document 36) | **"Expenses table OK, build it"** |

**One-line answer:** *"L2 themes 1–14 as proposed, L3 by week 4, expenses table OK, build it."*
