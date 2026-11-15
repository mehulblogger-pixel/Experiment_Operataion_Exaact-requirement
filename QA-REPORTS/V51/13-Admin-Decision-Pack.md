# Admin & Setup — decision pack (V51.1)

15 Nov 2026 · I ran 168 Admin tests on a private copy of V51.1 that holds only made-up data. Nothing has been changed in the app yet.

**How to answer:** reply with item numbers, for example *"A1 yes, A2 yes, A3 read-only, A4 refuse, A5 docs, B ok, C1 agree, C3 agree, D2 yes"*. Anything you don't mention stays untouched.

## Where Admin stands

| | Scenario tests (ADM / SET) | Screen checks (SCR) | Total |
| --- | --- | --- | --- |
| Passed | 56 | 18 | 74 |
| Failed: 12 real defects | 8 | 1 | 9 |
| Need your decision (section C) | 10 | 6 | 16 |
| Blocked | 10 | 34 | 44 |
| The robot cannot decide; a person must look | 3 | 17 | 20 |
| Cannot be tested here, or does not apply here | 5 | 0 | 5 |
| **Total** | **92** | **76** | **168** |

**Read the "Blocked" row with care.** It is work not finished, not work that passed (section F):

- **10 scenario tests are blocked.** In most of them a screen was changed on purpose after the test was written, so the test wording is out of date.
- **34 screen checks are blocked.** In 31 of them the automatic screen checker reported a mismatch that I have not confirmed. Its reports on custom-built screens are often wrong, so a person must look before I call any of them a defect. The other 3 (Roles & access, Role workspaces, Settings) took longer than 15 minutes on this machine and stopped.

## A — Fixes that need your yes (security, permission, GST)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-ADM-002 (P1)** | **A login meant to see one branch, but with no home office set, sees every branch's work orders.** The banner on the same page says "Your account has not been given a branch yet. That is why these lists look empty". The office filter treats "no office" as "all offices". Not fixed in the held v52/v53 either. I confirmed it in the code (`lib/access.php`) | No office → see nothing (as the banner says), until an admin sets the office. Master Admin / Admin keep seeing everything |
| A2 | DEF-ADM-006 (P2) | A Coordinator, or anyone who can view Masters, can add dropdown lists and fields to the Candidate form through **Build forms with AI → Add selected to my forms**. The permission list gives form design to Master Admin and Admin only | Refuse it for everyone else, as the permission list says |
| A3 | DEF-ADM-008 (P2) | The **Business Director** can switch services on or off for the whole company (**Admin → Service scope → Save services offered**). The permission list gives Settings to Master Admin and Admin only | Show the Director the page read-only (or hide it). Tell me if the Director should be allowed; the permission list would then change |
| A4 | DEF-ADM-009 (P2) | **An invalid GST number is saved.** "24ABC" was stored on the company profile with only a warning. It then prints on quotations, invoices and the GST export, and the State box was filled in from its first two digits | Refuse a GSTIN that is not 15 characters in the right pattern, keep the old one, and say why in plain words |
| A5 | DEF-ADM-001 (P3) | The role descriptions (`docs/01-roles.md`) no longer match the menus that 6 roles get. Examples: the Operation Manager and the Coordinator see **Sales** (Project costing only), although the description says "no Sales". The Marketing Manager no longer has Admin | **Which is right: the menus or the descriptions?** My proposal: the menus are right (each came from an earlier change you approved), so I update the descriptions. If any menu is wrong, I remove it |

## B — Fixes I will make once you say "B ok" (small, no rule change)

| # | Defect | Problem |
| --- | --- | --- |
| B1 | DEF-ADM-003 | When a Branch Manager adds a person, the locked Home office box shows "—" instead of the manager's own office. The person is saved in the right office |
| B2 | DEF-ADM-004 | Deleting a dropdown value that records still use gives only "Remove this value?". The promised warning ("1 record still uses this value…") never appears for Industry and most lists |
| B3 | DEF-ADM-005 | "Lands on after sign-in" offers **Export data**, which is a file download, not a screen. A role given it gets a download when they press Home |
| B4 | DEF-ADM-007 | The Build forms with AI page shows a second block, "Not found", under itself on every visit |
| B5 | DEF-ADM-010 | A logo bigger than the upload limit is silently ignored, and the page still says "Company profile saved" |
| B6 | DEF-ADM-011 | A service that "requires itself" is not stored, yet the screen says "Dependency saved." |
| B7 | DEF-ADM-012 | On "What does your company do?", unticking every activity cannot be saved: the last tick comes back |
| B8 | HF-119 | At 1366 px wide, the company name at the top of the left menu is cut ("TB Inspection…") |
| B9 | HF-169 | Typing "Backup" in the left "Find a screen…" box finds nothing. It searches only the menu items, while Ctrl/Cmd-K finds the screen. I would make both search the same list |

Plus I would update the wording of the 10 tests that are out of date because the screens changed on purpose.

## C — Questions only you can answer (my proposal in **bold**)

| # | Test | Question | Proposal |
| --- | --- | --- | --- |
| C1 | ADM-012 | "Add a person" with no first or last name is accepted, and the person is named after their username ("zz.empty"). Should a name be required? | **Yes, require a first or last name** |
| C2 | ADM-022 | Two admins edit the same role's access at the same time. The second save silently wipes out the first admin's change | **Warn the second admin ("Somebody changed this since you opened it — reload") and refuse that save** |
| C3 | ADM-009 · HF-200 | Two different screens are both headed "Masters" | **Rename the second one "All dropdown lists"** |
| C4 | ADM-018 · HF-200 | Two screens are both headed "Organisation & people" | **That is intended: they are two tabs of one place. I update the test** |
| C5 | ADM-057 | When the platform owner uses "log in as" for a customer company, is it recorded where that company can see it? The copy has no companies, so this could not be seen. In the code, only a note in the session is kept | **Record every log-in-as in that company's audit trail under the platform owner's own name, and show the company a notice. This is a security change and needs your yes** |
| C6 | ADM-028 · Money D2 | When the invoice-number rule is changed mid-year, what happens to the series? (Money pack D2 found that the series restarts) | **One unbroken series per financial year. Ask your accountant; answer it once for Money D2 and here** |
| C7 | ADM-039 | After "Quotation" is renamed (e.g. to "Proposal"), should a PDF made earlier still say "Quotation"? | **Yes. An old PDF is a record of what was sent; new PDFs use the new word** |
| C8 | ADM-040 | The standard words are shown in sentence case. Should your own typed wording keep its capitals ("Site Visit")? | **Keep your capitals exactly as you typed them** |
| C9 | ADM-065 | Pressing Refresh for an AI provider that has no key says "showing known models", not "add a key first" | **Say "Add an API key first"** |
| C10 | ADM-071 | The Gaps tab still lists "17 offices have no head" after every person has a branch and a manager. Should "nothing missing" include office heads? | **Yes. The screen is right; I update the test** |
| C11 | ADM-061 | Is "MGH Books is connected" meant to be on, on the live site? It is off on the copy (the default). Only you can read the live setting | **Off, unless you have decided to connect Books** |
| C12 | 6 screens | Pressing Save on an empty form says "saved" although nothing changed. The 6 screens are Books connection, Letterhead, Feature gates, the Owner page, Report formats and Terminology | **Say "Nothing to save — no changes" instead** |
| C13 | HF-114 | **Admin → Expense heads → New** opens with "needs receipt" and "active" already ticked | **Keep: those are the usual choices** |

## D — Older Admin problems checked again on V51.1 (from the earlier audit list)

I re-checked 61 older Admin items:

- **15 still exist.**
- **9 are fixed or no longer apply.**
- **37 could not be settled here:** 21 need you or your hosting company, and 16 need further investigation. The 37 are listed in section F.

Of the 15 that still exist, B8, B9 and C3/C4 above are four. The other 11 are below.

| # | Item | What is true today | Proposal |
| --- | --- | --- | --- |
| D1 | HF-043 | A security incident starts a 6-hour CERT-In clock in the app. The report itself is filed outside the app by a person, and the app records that it was filed | **By design. Keep it** |
| D2 | HF-094 | Data is not encrypted at rest. The earlier audit recorded this as your decision | **Confirm it is still your decision** |
| D3 | HF-134 | No load test or penetration test has been run, and nothing has been tested at scale on MySQL. This audit cannot do it either: the copies are small and run on a shared 4-core machine | **A separate job, on a hosting copy you approve** |
| D4 | HF-044 | The software parts list (SBOM) is made by hand, not at each release | Add it to the release script. Within P3; I can do it |
| D5 | HF-087 | The mail code still sits in the general program file. A technical tidy-up; nothing you can see | Leave for later |
| D6 | HF-143 | Screens use about 8,500 inline style settings, which makes the look harder to keep consistent. Technical, nothing you can see now | Leave for later |
| D7 | HF-061 | There is no public API for other systems to connect to | Not built; your call whether you want one (see E) |
| D8 | HF-069 | Settings cannot be exported, imported or versioned, and there is no "try it in a sandbox" mode | Not built (see E) |
| D9 | HF-070 | Screens are in English only. Currency and date format can be set | Not built (see E) |
| D10 | HF-163 | The form designer can rename, reorder, require, hide, add and delete fields. It cannot duplicate a field, change its type, add a check rule, show a field only on a condition, or preview live | Not built (see E) |
| D11 | HF-164 | Company setup's "Needs your attention" checks only a few things | Not built (see E) |

## E — Not built (features named in the earlier audit; the app has none)

E1 public API · E2 export/import of settings with versions and a sandbox · E3 other languages · E4 a richer form designer · E5 a fuller setup health check. **None of these is a defect against V51.1.** Each is new work and needs your yes and a priority before anyone builds it.

## F — What is NOT tested yet in Admin

- **34 screen checks are blocked** (above). Three of them ran out of time (Roles & access, Role workspaces, Settings) and need a rerun on a quiet machine. Until a person has looked at each one, **none of the 34 counts as passed.**
- **17 screen checks where the robot cannot decide.** They concern the platform-owner pages (Companies, Licences, Signing), Attendance review and My signature. The pages open without error, but a person has to judge the result.
- **Steps that need another module's data first:**
  - a report format reaching a job (ADM-046, ADM-072);
  - wording on a quotation PDF (ADM-039, ADM-047);
  - invoice numbering on an issued invoice (ADM-028).

  These will be done when the Operations, Sales and Money chains are built on one copy.
- **Outside services:** a paid AI provider (ADM-038, ADM-067 via the real button), Ads Pro (ADM-059), single sign-on (ADM-076: needs the sister app).
- **37 older items:** 21 cannot be checked from a private copy (hosting, backups off the server, e-mail delivery, your own policies), and 16 need further investigation.
- **Your own clean-up** on the real test copy after testing (SET-12) is yours to do; I cannot do it from here.

So the Admin picture is: **1 serious and 3 medium defects + 8 small ones, 15 older problems still present (4 of them overlap B and C), 5 features not built, 13 questions, and 51 screen checks (34 blocked + 17 the robot cannot decide) plus 37 older items still to settle.**

## After you answer

One Admin fix batch, the same way as Sales: the full suite on SQLite and MariaDB, the Admin robot tests again, one zip for the test copy, the list of changed files and the rollback steps. A1 (DEF-ADM-002) is the most urgent item in this pack. If you say yes to A1 alone, I can do it as its own small batch first.
