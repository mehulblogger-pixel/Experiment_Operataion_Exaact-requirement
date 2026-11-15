# Sales — decision pack (V51.1)

15 Nov 2026 · Every item below was confirmed on screen on a private copy of V51.1 with made-up data. Nothing has been changed yet.

**How to answer:** reply with the item numbers, for example *"A1 yes, A2 yes, A3–A13 yes, A14 no, B ok, C1 agree, C5 refuse, D yes"*. Anything you don't mention stays untouched.

## Where Sales stands

| | Tests |
| --- | --- |
| Passed | 73 |
| Failed — 38 real defects (one per root cause) | 43 |
| Blocked — 28 test wordings out of date (screen changed on purpose by your later decisions) and 1 test whose set-up is missing (SAL-060 needs a quotation Word template on the copy) | 29 |
| Need your decision (section C) | 14 |
| Cannot be tested here (how a print looks, real Gmail/Outlook, no data on the copy) | 12 |
| **Total** | **171** |

The 4 failures caused by my robot scripts were corrected and rerun; they are not counted as defects. The cross-branch contract problem was found twice and is counted once.

## A — Fixes that need your yes (they touch money, approvals, contracts, security or a business rule)

**Serious (P1)**

| # | Defect | What happens today | Proposed fix | Already built in your held update? |
| --- | --- | --- | --- | --- |
| A1 | DEF-SEC-001 | A Branch Manager or Operation Manager of one branch can see, endorse and give the final approval to **another branch's** contract (Ahmedabad opened a Mumbai contract) | Each signer sees and signs only contracts of the branches they cover (see C2) | No — new fix |
| A2 | DEF-SAL-011 | A contract registered from a quotation takes the value **with GST** (₹7,08,000 instead of ₹6,00,000) | Use the value before GST. Also tell me whether existing contracts should be corrected (C17) | Yes — v52 (F6) |
| A3 | DEF-SAL-019 | A blocked customer can still be converted from a lead, and an approver can still approve their quotation | Apply the rule you choose in C1 everywhere, with a warning banner | No — new fix |
| A4 | DEF-SAL-020 | Converting a lead creates a **second copy** of a customer that exists in another branch | Look for the same company (name, GSTIN, PAN) in all branches and offer to attach to it | No — new fix |
| A5 | DEF-SAL-006 | Set up Sales answer "the preparer may approve within their own authority" is ignored; the quotation waits on its own preparer | Apply the answer | Yes — v53 (F13) |
| A6 | DEF-SAL-012 | In a business unit with no Business Unit Head, the quotation keeps that level and **can never be signed** | A level nobody holds passes up to the next level | Yes — v53 (F5) |
| A7 | DEF-SAL-004 | An approved quotation prepared by a Marketing Executive can be sent **only by the administrator** | The preparer and the person assigned may send it | Yes — v53 (F1) |
| A8 | DEF-DB-001 | **On MySQL (your host)** part-month invoice quantities are cut to 3 decimals: ₹6,41,637 billed instead of ₹6,41,580 in the test case | Keep 4 decimals and work the amount from the exact quantity. See C18 about invoices already issued on the live site | No — new fix |

**Medium (P2/P3), but in an approval, contract or money area**

| # | Defect | What happens today | Proposed fix | Held update? |
| --- | --- | --- | --- | --- |
| A9 | DEF-SAL-005 | A quotation needing no approval has no Submit step and stays "Draft" | "✓ Submit — approved at once", with the reason | v53 (F10) |
| A10 | DEF-SAL-007 | An approved quotation has no Edit / Create revision button | Offer "Create revision" on an approved quotation | v53 (F19) |
| A11 | DEF-SAL-008 | When a delegate approves, the record does not say whom they signed for | "Test KAM, for Test MktMgr" | v53 (F12) |
| A12 | DEF-SAL-009 | After giving the first contract signature, the signer is still offered "Reject" | Remove Reject for the person who already signed | v53 (F28) |
| A13 | DEF-SAL-035 | An approval rule naming a person who has left can be saved; that level then waits forever | Refuse it, with a plain message | v52 (F37) |
| A14 | DEF-DB-002 | **On MySQL** approving a contract billing-schedule change can show an error page although it was saved; the history line is missing | Do the table check before the save starts, so it is all-or-nothing | No — new fix |
| A15 | DEF-SAL-014 | A negative salary head (−₹10,000) is saved and pulls the costing negative | Refuse negative heads | v52 (F8) |
| A16 | DEF-SAL-022 | Once a quotation is sent, the client's purchase order cannot be attached to it | Allow attaching documents (not changing prices) after sending | No |
| A17 | DEF-SAL-028 | Finance sees "Quality & Accreditation" in the menu (only for "Hold & witness points") | Hide it for Finance, as decided (D-07) | No |
| A18 | DEF-SAL-029 | The Business Director may apply an industry template but cannot reach it from the menu | Add the menu link | No |

## B — Fixes I will make in the Sales batch (small or medium, no rule changes)

| # | Defect | Problem | Held update? |
| --- | --- | --- | --- |
| B1 | DEF-SAL-001 | Money shown as ₹500,000 instead of ₹5,00,000 (display only) | v52 (F15) |
| B2 | DEF-SAL-002 | Messages name internal boxes ("waiver_reason", "l_qty[]", "make") | v52 (F30), partly |
| B3 | DEF-SAL-003 | A too-large file is reported as "session timed out", or the page goes blank | v52 (F24), partly |
| B4 | DEF-SAL-010 | Contract rule "None" shows the wrong message | v52 (F29) |
| B5 | DEF-SAL-013 | Set up Sales history writes all answers in one line, cut off at 255 characters | v52 (F26) |
| B6 | DEF-SAL-015 | Editing a role in the costing pop-up replaces the quotation screen; typing is lost | v52 (F7) |
| B7 | DEF-SAL-016 | Sales funnel shows conversions above 100% | v52 (F11) |
| B8 | DEF-SAL-017 | A man-month lead for 0 people is saved without a word | v52 (F34) |
| B9 | DEF-SAL-018 | Paise not shown (₹7,999.99 printed as ₹8,000) — display only; stored amounts are right | No |
| B10 | DEF-SAL-021 | "Your next actions" never shows Sales follow-ups | No |
| B11 | DEF-SAL-023 | Pipeline stages cannot be put in order | No |
| B12 | DEF-SAL-024 | Enquiry cards open lists that do not match their counts | No |
| B13 | DEF-SAL-025 | Lead loss reasons not counted in "Why we lose" | No |
| B14 | DEF-SAL-026 | Leads Year box misses years that have only leads | No |
| B15 | DEF-SAL-027 | On a phone, jump links hide box headings under the top bar | No |
| B16 | DEF-SAL-030 | A new deal has no History tab until its first move | No |
| B17 | DEF-SAL-031 | A long-past "Expected to close" date is not flagged overdue | No |
| B18 | DEF-SAL-032 | Pipeline "In use" untick is not saved | v52 (F39) |
| B19 | DEF-SAL-033 | An empty quotation follow-up is saved | v52 |
| B20 | DEF-SAL-034 | A nameless report-format draft is saved | v52 (F40) |
| B21 | DEF-SAL-036 | After a session time-out the sign-in page gives no reason and typing is lost | No |
| B22 | DEF-SAL-037 | Back + "Create quote" makes a **second quotation** (same gap as the inquiry form, fixed the same way) | No |

I will also update the wording of the 28 out-of-date tests (and add the missing set-up to SAL-060) in the test book, using the suggested wording recorded for each. This changes no app code.

## C — Questions only you can answer (my proposal in **bold**)

| # | Question | Proposal |
| --- | --- | --- |
| C1 | A blocked customer (on hold, blacklisted, inactive): which rule? The document says "nothing new at all"; your later answer (F2/F3) said business may go up to acceptance with a warning, but no contract or work order | **Your F2/F3 rule, with a red banner** |
| C2 | Should the Business Unit Head and Business Director, who cover several branches, sign contract openings for all their branches? | **Yes; Branch / Operation Managers own branch only** |
| C3 | Should the Business Director be able to **delete** enquiries and deals by default? (docs give Delete to all management roles; the test book says no) | **No — remove it from the Director by default** |
| C4 | Does one approved costing price only one quotation? | **Yes** |
| C5 | Two "Won" stages in one funnel | **Refuse** |
| C6 | A quotation line left with no site after its site is removed | **Allow, warn before submit** |
| C7 | Lost reason "Other (specify)" with no text | **Refuse** |
| C8 | Unknown {{token}} in a template is left as typed | **Keep, and warn on save** |
| C9 | Approval level 0 or −1 is treated as level 1 | **Refuse** |
| C10 | A deal with no customer | **Keep refusing it** |
| C11 | Lead conversion with empty boxes = use the lead's own details | **Yes** |
| C12 | Settings screens saved empty say "saved" | **Keep current values and say what is missing** |
| C13 | Letters accepted in the telephone box | **Refuse** |
| C14 | Negative deal value; negative percentages on a costing | **Refuse** |
| C15 | Lab tests: names do not fit the Service box; goods-seller terms for product companies | **Record as gaps for later; no change now** |
| C16 | Should Accounts be able to add a contract from the client record? (today only the administrator can) | **Your call** |
| C17 | Contracts already registered with GST included (A2): correct them? | **Yes, list them first for your review** |
| C18 | Invoices already issued on the live site with part-month lines (A8) may carry small errors. Check them? | **Yes — you or Finance look, or allow one read-only check** |

C15's Excel-style costing check (SAL-056) also needs your eye: the robot's arithmetic matches the screen, but only you can compare it with your own spreadsheet.

## D — Your held updates

Several of the fixes above are already built and tested in your held v52 (A2, A13, A15, B1–B8, B18–B20) and v53 (A5–A7, A9–A12).

**Question D:** for those items, may I take the same code from v52/v53 into V51.1 — only those changes, item by item, retested? The alternative is that I re-make them, which takes longer but gives the same result. Either way v52 and v53 stay on hold as a whole.

## After you answer

1. I fix everything approved in one Sales batch.
2. I run the full 722-test suite on **both** databases (SQLite and MariaDB), plus every Sales robot test.
3. You get **one update zip**, with the list of changed files and rollback steps.
