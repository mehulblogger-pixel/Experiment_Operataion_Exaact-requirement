# New company, plans & licensing — decision pack (V51.1)

16 Nov 2026 · I ran all 48 New company tests on private copies with made-up data. They cover setting up the platform, workspace requests, self sign-up and trials, creating companies, first sign-in, the setup cockpit, seats, plans and modules, storage, professionals and client sign-up, and a self-hosted install. Each test ran twice, and the second run counts.

- **Multi-company copy:** the tester used its own, with a local mail catcher (no real e-mail) and made-up payment keys (no real payment).
- **Code check:** I checked the serious finding in the program code myself.
- **App changes:** none.

**Whom this affects:** mostly companies that use EXAACT as **your customers** on the cloud platform (plans, trials, modules, seats). Your own company's daily work is not affected by most of these.

**How to answer:** reply with item numbers, for example *"A1 yes, A2 yes, B ok, C1 agree"*. Anything you don't mention stays untouched.

## Where New company stands

| | Tests |
| --- | --- |
| Passed | 9 |
| Failed: 12 defects | 11 |
| The robot cannot do it (a real Razorpay payment, a real inbox, cPanel, a real licence key) | 12 |
| Blocked (test wording out of date) | 2 |
| Need your decision | 1 |
| Run only in part (deeper steps are covered by the module testers) | 13 |
| **Total** | **48** |

**Read with care:** 25 of the 48 are not fully proven. The 12 "cannot do" tests were checked up to the payment or inbox step, but not beyond it. The 13 partial ones are listed in section F.

## A — Fixes that need your yes (money, licensing, business rule)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-ONB-008 (P1)** | **A customer company can switch on modules it has not paid for.** When you, the platform owner, untick modules in "Modules this company has bought", they are only hidden. The plan still decides what the company may switch on. A Professional-plan company cut down to Sales could switch Operations back on itself and use it without paying. It also works the other way: Marketplace bought for an Enterprise company shows "🔒 Not in plan". *The code agrees: the plan writes what a company may switch on, and the ticks only hide modules.* | What you tick as bought **is** what the company may use: no more, no less. **Money and licensing: needs your yes** |
| A2 | DEF-ONB-006 (P2) | **Ticking "What does your company do?" on the company profile switches off modules the company bought.** No tick maps to Sales or Money, so ticking only "Expediting" leaves just Operations and Administration | Those ticks should only shape suggestions and wording, never switch a bought module off. **Business rule: needs your yes** |
| A3 | DEF-ONB-009 (P2) | After applying the "In-house HR" product package and then "Enterprise", the Marketplace stays off | Applying a package sets exactly that package's modules. **Licensing: needs your yes** |
| A4 | DEF-ONB-007 (P2) | **At the seat limit, "Add a person" is refused, but a team-member record is still created** and what was typed is lost. That leftover record then blocks adding the same person after more seats are bought | Create nothing when refused, keep what was typed, and say how to buy a seat. **Seats are billing: needs your yes** |

## B — Fixes I will make once you say "B ok" (small, no rule change)

- **B1 DEF-ONB-010 (P2):** a company created by the platform owner has no "Your subscription" tile on any Admin tab. The company's admin can only reach it by typing the address. I would add the tile.
- **B2 DEF-ONB-001 (P3):** a company account signing in at the Super Admin address is told "Invalid username or password". I would say "This is the platform owner's sign-in. Your company signs in at …".
- **B3 DEF-ONB-002 (P3):** when "Add a company" is refused, the form panel comes back closed, so what was typed looks lost. I would keep the panel open.
- **B4 DEF-ONB-003 (P3):** the /get-started form silently cuts the company name to 150 characters and the note to 500. I would say so in the form.
- **B5 DEF-ONB-004 (P3):** a workspace key like "TB Owner!" is silently turned into "tb-owner". I would show the key that will be used before it is saved.
- **B6 DEF-ONB-005 (P3):** the owner of a **suspended** company is told "Your workspace is still being set up…". I would say it is suspended and whom to contact.
- **B7 DEF-ONB-011 (P3):** after a trial has ended, a non-admin's refused save sends them to the Dashboard, and they lose their screen and what they typed. I would keep them on the page with a clear message.
- **B8 DEF-ONB-012 (P3):** the "storage full" message says nothing else was lost, but the other fields are lost. I would keep them.

Plus new wording for the 2 out-of-date tests:
- **PLT-001:** the go-live checklist now has 14 items. A webhook line was added on 8 Nov.
- **ONB-051:** there is no billing screen inside a company any more. /billing leads to "Your subscription" (your 8 Nov decision). The important part of that test passed: a forged payment confirmation was refused, and a correctly signed one for an unknown order changed nothing.

## C — Questions only you can answer (my proposal in **bold**)

| # | Test | Question | Proposal |
| --- | --- | --- | --- |
| C1 | ONB-031 | The dashboard's "Finish setting up" list asks for currency and financial year, which the setup wizard already saved. The setup cockpit's Company profile card stays "In progress" until the "What does your company do?" ticks are made | **Count the wizard's answers as done, and make the card say exactly what is still missing** |
| C2 | FLOW-01 | A quotation raised from an enquiry is not tied to the deal its lead created, so the deal and the lead stay Open after the quotation is accepted | **Tie the quotation to that deal, so winning the quotation wins the deal** |

**Known defects met again (not counted twice):**
- **DEF-SAL-011:** the contract value includes GST (₹1,18,000 instead of ₹1,00,000). This is Sales pack A.
- **DEF-ADM-009:** an invalid GSTIN is accepted. This is Admin A4. Also: the test book's own sample GSTIN has a wrong check character. I will correct the test.

## D — Older items checked again on V51.1

- **Fixed:** HF-078. The seat limit is enforced in a cloud company.
- **Still exists:**
  - HF-154. With no licence key, the licence page says "everything is switched on". *Proposal: say "No licence key — this is a full-access install" in plain words.*
  - HF-001. It is the main end-to-end chain, which is only partly run (F).
- **No longer apply:** HF-129 and HF-210. Both asked for these tests to be run, which is now done.
- **Cannot test here:** HF-155. It needs a payment with live keys.

## E — Not built

None found in this area.

## F — What is NOT tested yet

- **Real payments:**
  - **What was tested:** everything up to the payment step. For example, the plan price shows correctly: ₹3,495 = 999 + 999 + 3 × 499.
  - **What was not:** the Razorpay checkout window, paid plans, tax invoices, storage packs, and "Check payment keys" against Razorpay. These need a real payment on the test copy with test keys, which you can do yourself.
- **Real e-mails:** every e-mail was checked on a local catcher (sender, subject, links, how long a link is valid), but none was sent to a real inbox.
- **Self-hosted install** (ONB-060): a real MGH licence key and the 14-day wait could not be done here.
- **13 tests run only in part:**
  - ONB-042 and ONB-052;
  - the seven "what to set up after buying a module" tests (MOD-*);
  - PRO-001, CLI-001 and CLI-002.

  Their screens open and their first steps work. The deeper steps are the same ones the module testers already ran in a single company.
- **The 15 end-to-end chains (Across modules):** only the first 17 steps of FLOW-01 ran. Up to there, the chain worked: lead → deal → enquiry → quotation → approval → sent → accepted → contract opened. The two breaks were C2 and DEF-SAL-011. **I am starting a tester now for the other 14 chains and the rest of FLOW-01.**
- **Watch point (not yet a defect):** twice, a server process on the test copy got stuck using full processor power and stopped answering until restarted. Once it followed test data the tester had put straight into the database. The other time the cause was not found. The next tester is asked to catch exactly which screen does this if it happens again. I will report it as soon as it is understood.

So the New company picture is: **1 serious, 4 medium and 7 small defects; 2 older problems still present (one only partly checked); 2 questions; 25 tests not fully proven (real payments, real e-mail, partial runs); the end-to-end chains still to run.**

## After you answer

A1–A4 form one "plans, modules and seats" batch. Those are money rules, so they go only with your yes. B goes in a small batch. Each batch gets:
- the full suite on SQLite and MariaDB;
- the robot tests again;
- a zip for the test copy;
- the changed-files list;
- the rollback steps.
