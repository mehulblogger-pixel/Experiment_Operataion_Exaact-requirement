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

## 2. What "ready to earn" means

A company can **sign up, pay the subscription, and run its daily work** without confusion or wrong figures:

> enquiry → costing → quotation → contract → work order → job → inspection report → invoice → payment, plus inspectors' expenses.

**My recommendation: launch with these 6 core areas only.** The other areas (Recruitment, Quality & Accreditation, Marketplace, client/vendor portals) stay **switched off for new customers** at launch, using the existing module switches, and come later. This cuts the work to finish roughly in half and gets you to revenue sooner. Nothing is deleted; your own company can keep using them.

## 3. The plan, week by week (from 18 Nov 2026)

| Week | Dates | What I deliver | What you do (about 1 hour) |
| --- | --- | --- | --- |
| 1 | 18–24 Nov | **Expenses** rebuilt (your v3 design). **Foundation batch**: one office check on every action, and closed records stay closed (edge themes 1 and 2), across all modules | OK the voucher gap table; 10 checks on opstest |
| 2 | 25 Nov–1 Dec | **Reports** simplified, with your V3, V4, V5 | OK the screen pictures; 10 checks |
| 3 | 2–8 Dec | **Work orders & jobs** simplified | OK pictures; 10 checks. **First live update** (if you say testing is complete) |
| 4 | 9–15 Dec | **Invoices & payments** (needs your money sheet answers) | OK pictures; 10 checks |
| 5 | 16–22 Dec | **Sales**: enquiry, costing, quotation, contract | OK pictures; 10 checks |
| 6 | 23–29 Dec | **Sign-up, subscription payment, users & roles**; remaining serious faults | OK pictures; 10 checks |
| 7 | 30 Dec–5 Jan | Full re-test of everything, your final check, **live update with the go-live checklist** | Final check; one real ₹1 payment and refund on live |

**Ready to sell: around 5 January 2027**, if each of your OKs comes within 1–2 days.

**Honest limits of this estimate:**
- It moves by the number of days any answer takes.
- My working time is limited by the account's usage limit. Today the work stopped for about 2½ hours because of it.
- A module can show something unexpected. Allow **1–2 weeks of margin**: the realistic range is **5–20 January 2027**.

**Earlier use:** after the week-3 live update, your own company (or one friendly customer) can start using expenses, reports and jobs on the live site, while I finish the rest.

## 4. Testing — who does what (no more storm)

| Who | What | How often |
| --- | --- | --- |
| **Me: automatic suite** | About 21,000 checks of rules, permissions and figures, on two database types | Before every zip |
| **Me: robot** | Real screens, clicked like a person, before and after every change | Before every zip |
| **You** | **Only** a short check list per module (10–15 checks, about 30–45 minutes): *does this make sense for my business?* | Once per module |

- **Pause the 991-test edge-case book and the old Part C/D.** They test screens that are about to change. Your result taps stay saved.
- Your manual testing tonight was valuable: it found things only a business owner sees (the voucher screen, approvers editing claims). That kind of feedback is exactly your part.

## 5. Your three decisions now

| # | Decision | My recommendation |
| --- | --- | --- |
| L1 | Launch with the 6 core areas; others switched off for new customers | **Yes** |
| L2 | Edge answer sheet 3 | **"Themes 1–14 as proposed"**. Themes 1 and 2 go into week 1's foundation batch; each change is still explained with examples first |
| L3 | Money answer sheet 2 | **Before week 4** (needed for invoices and payments) |

**One-line answer:** *"L1 yes, L2 themes 1–14 as proposed, L3 by week 4."*
