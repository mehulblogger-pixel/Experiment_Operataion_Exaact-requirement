# Reporting (inspection reports) — decision pack (V51.1)

15 Nov 2026 · I ran all 119 Reporting tests twice on private copies of V51.1 with made-up data. The second run is the one that counts. I checked the three serious findings in the program code myself. Nothing has been changed in the app.

**How to answer:** reply with item numbers, for example *"A1 yes, A2 yes, A3 yes, A4 yes, B ok, C1 agree, C11 financial year"*. Anything you don't mention stays untouched.

## Where Reporting stands

| | Scenario tests (REP) | Screen checks (SCR) | Total |
| --- | --- | --- | --- |
| Passed | 62 | 0 | 62 |
| Failed: 15 real defects | 12 | 4 | 16 |
| Need your decision (section C) | 18 | 1 | 19 |
| Blocked: test wording out of date (screens changed on purpose) | 3 | 18 | 21 |
| The robot cannot do it (voice dictation on a real phone) | 1 | 0 | 1 |
| **Total** | **96** | **23** | **119** |

Most of the 21 blocked tests are not app problems. Your 9 Nov decision moved the Governance tab to Admin → Data protection & security. Your 31 Oct decision renamed the Vetting checklist to "Review & issue rules". Some headings also follow your "inspection" wording. Each blocked test has suggested new wording ready (section B).

## A — Fixes that need your yes (security, approval workflow)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-REP-015 (P1)** | **Someone from another branch can change a report they are not allowed to see.** The report page refuses them ("outside your office / branch scope"). But typing the edit address with the report number opens the edit form, and saving it stores the change. It also wipes the report's client and vendor. The report page and the PDF check the branch; the edit and fill pages do not. *I confirmed this in the code.* | Apply the same branch check on edit and fill as on the report page |
| A2 | **DEF-REP-001 (P1)** | **An Inspector can open and change another inspector's draft.** "✎ Edit details" is shown and the save works, although your permission list gives the Inspector "Edit (own)" only. *I confirmed in the code that the edit check has no "own report" condition.* | An Inspector edits only reports where they are the inspector or the one who created it. Coordinators and managers keep what the permission list gives them |
| A3 | **DEF-REP-002 (P1)** | **"♻️ Reissue as new revision" leads to "Not found".** So an issued report that is wrong cannot be corrected the documented way. *Confirmed: the revision code exists, but the page is never routed to it.* | Connect the page, so a revision (R1, R2 …) can be made |
| A4 | DEF-REP-007 (P2) | On New inspection report, "Start from an inspection call" lists **every branch's** work orders. Load then fills in that branch's client, vendor and PO, so an Ahmedabad Coordinator sees and loads a Mumbai work order | Show only work orders in the person's own branch scope |

## B — Fixes I will make once you say "B ok" (small, no rule change)

| # | Defect | Problem |
| --- | --- | --- |
| B1 | DEF-REP-013 (P2) | "Adjust dates" accepts an empty value, which **erases the issue date** of an issued report. I would refuse an empty date |
| B2 | DEF-REP-006 (P2) | On a phone, the bottom menu bar covers the "Generate report" button, so an inspector cannot tap it |
| B3 | DEF-REP-003 (P2) | Reasons are saved but never shown on the report's Audit tab: a gate-override reason, the "approved and issued by the same person" note and a date-adjustment reason |
| B4 | DEF-REP-004 | A report that is only submitted says "This report is finalized" when someone opens its edit page. It is not finalized |
| B5 | DEF-REP-005 | On a phone, the long report number in the heading does not wrap, so the page moves a few pixels sideways |
| B6 | DEF-REP-008 | "Load" from a work order does not fill in the inspector already allocated to it |
| B7 | DEF-REP-009 | Buttons lead only to a refusal: "Vendors" for the Inspector and "Phrase library" for the Coordinator. I would hide them for those roles |
| B8 | DEF-REP-010 | A Branch Manager may archive an endorsement, but there is no button for it |
| B9 | DEF-REP-011 | Learning insights counts each report twice (once at approval, once at issue) |
| B10 | DEF-REP-012 | A consent saved with "Given on" blank is shown as given today. I would refuse a blank date |
| B11 | DEF-REP-014 | Saving an empty form still stores a record: an approval rule with no name, which then matches every report, and an empty report-format draft |

Plus I would update the wording of the 21 out-of-date tests.

## C — Questions only you can answer (my proposal in **bold**)

| # | Test | Question | Proposal |
| --- | --- | --- | --- |
| C1 | REP-006 | Two people edit the same report at once; the second save silently overwrites the first | **Warn and refuse the second save, as work orders already do** |
| C2 | REP-050 | Anyone with the Finalise right can endorse a document assigned to someone else | **Only the assigned approver, their delegate, or a master admin. This changes an approval workflow** |
| C3 | REP-093 | Who may trim the audit trail, and how long is it kept? Today it is 400 days, and neither the right nor the period is in the permission list | **Admins only; 400 days by default, never below 180. I add it to the permission list** |
| C4 | REP-036 | A Branch Manager sees every branch's audit entries | **Should the audit trail follow the own-branch rule? I propose yes** |
| C5 | REP-019 | There is no "superseded / withdrawn" status, but the old report's page says "superseded by …/R1" | **Show the same marker in the register and the client portal; withdraw only through a nonconformity** |
| C6 | REP-024 | A report type your company added can be removed outright, even if reports were filed under it | **Offer "Deactivate" instead for any type that has reports** |
| C7 | REP-025 · SCR | A duplicate report-type code is accepted, and codes have no length limit | **Refuse duplicates; at most 10 letters or digits** |
| C8 | SCR-irn-rules | The company code in the report number may be empty or 300 characters long | **Require 2–8 letters or digits** |
| C9 | REP-049 · HF-190 | An empty endorsement still gets a number | **Refuse it until the original document is attached** |
| C10 | REP-089 · HF-190 | An incident whose summary is a single space is created | **Refuse it** |
| C11 | REP-044 | The year in the report number follows the calendar year (31 Mar and 1 Apr 2027 both show 2027) | **Your choice: calendar year, as today, or financial year** |
| C12 | REP-043 · HF-024 | 28 report types have no form yet ("no form yet"), and an inspector who picks one is stuck | **Hide those types from people who cannot design forms** |
| C13 | REP-026 | What still causes the "split table rows" that the repair button fixes? | **If the cause is gone, hide the button. I would check this first** |
| C14 | REP-078 | A duplicate phrase shorthand is accepted, and the oldest wording wins | **Refuse duplicates** |
| C15 | REP-002 | The Inspector's phone menu has no "Reporting" item; the hub is reached from My inspection reports | **Keep the short phone menu, add "Technical writing" to it, and update the test** |
| C16 | REP-011 · REP-059 · REP-060 · REP-071 · REP-084 | Five behaviours where the app is reasonable and the test expected otherwise: the completeness check speaks first; a second expediting report on the same PO is allowed; the Inspector can read the Expediting register; the phrase "Used" count is for the Branch Application Manager; a muted wording stays muted | **Accept all five; I update the tests** |
| C17 | SCR | Saving or converting with everything empty gives no clear message (vetting checklist, writing assistant) | **Say what is missing** |
| C18 | Time zone | **The app never sets a time zone.** If the live or opstest server runs on UTC rather than India time, the six-hour CERT-In clock and "When it was noticed" will be off by 5½ hours | **Please send me the time shown on `/deploy-check.php` for both sites (with your screenshots). If it is not India time, I set India time in the app, with your yes** |

## D — Older Reporting problems checked again on V51.1

**11 older items checked:**
- 6 still exist;
- 3 are fixed (a Release Notes register exists; the register and phone flow exist; consents are recorded);
- 2 no longer apply: the UAT and the report-to-issue run have now been done.

| # | Item | Still true today | Proposal |
| --- | --- | --- | --- |
| D1 | HF-006 | A report is never set to "Archived", although the lifecycle has that status | **Decide whether Archive is needed. If yes, it needs your yes, because it adds a working step** |
| D2 | HF-024 | 28 report types still have no form | Content for you or your team to fill in; see C12 for the stop-gap |
| D3 | HF-190 | An endorsement or incident with nothing filled in still gets a number | Same as C9 / C10 |
| D4 | HF-018 | There is no "Insert from library" and no configuration centre for report content | Not built (E) |
| D5 | HF-019 | There is no library of reusable report parts | Not built (E) |
| D6 | HF-023 | The Release Note has no table of reference documents | Not built (E) |

## E — Not built

E1 Insert from library · E2 a library of reusable report parts · E3 a reference-documents table on the Release Note · E4 an Archive step (D1). **None of these is a defect against V51.1.** Each is new work that needs your yes and a priority.

## F — What is NOT tested yet in Reporting

- **21 blocked tests** are waiting for new wording. After that they must be run again; until then they count as neither passed nor failed.
- **Voice dictation** on a real phone (REP-074) cannot be done by the robot. Typed text was checked and works.
- **Three single steps** depend on data the copy does not have: 19 offices instead of 17, the full token list, and the hidden "Active" box.
- **The time zone** on both real sites (C18).
- **What a client sees in the client portal** for superseded reports (C5). The Client Portal is still to be tested.

So the Reporting picture is: **3 serious, 4 medium and 8 small defects; 6 older problems still present; 4 features not built; 18 questions; 21 tests waiting for new wording.**

## After you answer

One Reporting fix batch, the same way as Sales: the full suite on SQLite and MariaDB, the Reporting robot tests again, one zip for the test copy, the list of changed files and the rollback steps. A1–A3 are the most urgent. If you say yes to them, I can do them first as a small separate batch, together with Admin A1 if you approve that too.
