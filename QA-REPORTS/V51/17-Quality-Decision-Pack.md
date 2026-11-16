# Quality & Accreditation — decision pack (V51.1)

16 Nov 2026 · I ran all 235 Quality & Accreditation tests twice on private copies of V51.1 with made-up data. The second run is the one that counts. The module covers complaints, nonconformities (NCR), corrective actions (CAPA), hold & witness points, equipment and calibration, methods, decision rules, competence, impartiality, confidentiality, consents, site entry documents and management reviews. I checked both serious findings in the program code myself. Nothing has been changed in the app.

**How to answer:** reply with item numbers, for example *"A1 yes, A2 yes, A3 managers manage, A6 quality only, B ok, C1 agree, C6 365 days"*. Anything you don't mention stays untouched.

## Where Quality & Accreditation stands

| | Tests |
| --- | --- |
| Passed | 130 |
| Failed: 22 defects | 49 |
| Need your decision (sections A and C) | 43 |
| Blocked (test wording out of date) | 8 |
| The robot cannot do it (real phone photo; three forms the screen checker cannot fill) | 4 |
| Cannot be tested on a clean copy | 1 |
| **Total** | **235** |

The 49 failed tests come from 22 defects: **2 serious, 11 medium and 9 small.** None of them is fixed in the held v52/v53.

## A — Fixes that need your yes (permissions, approval workflow, lifecycle)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-QUA-002 (P1)** | **Nobody can use Hold & witness points, not even the Master Admin.** The register and every hold-point action say "This feature — not available", while the Quality hub still shows the tile. *I confirmed the cause in the code: the screen is linked to a module called "hold-points" that does not exist, so the access check fails for everyone. It is a wiring mistake, not a plan limit.* | Link the screen to the rights the screen itself already uses (Reporting or Jobs). Then run the 7 hold-point tests that this defect blocked (QA-040 to QA-046) |
| A2 | **DEF-QUA-006 (P1)** | **A Branch Manager or Operation Manager can work a nonconformity, not just record it.** They can record containment, decide the disposition (including "Accept as it stands") and reopen a closed nonconformity. Your permission list gives them **View · Record** only (your rule Q-B1 of 19 Oct). *Confirmed in the code: the "may work it" check also accepts the corrective-action Edit right, which both roles hold, and Reopen has no "may close" check at all.* | Containment, disposition and reopening go to whoever may close (the Quality or Technical Head, and admins). The managers keep View and Record |
| A3 | DEF-QUA-003 (P2) | On **Items & samples, Method library and Decision rules**, the view right and the manage right contradict each other. Coordinators hold the right to manage them but are refused the screen | **Tell me who should manage them.** My proposal: Coordinators view only; the quality function manages. The permission list then says so |
| A4 | DEF-QUA-004 (P2) | **An Inspector can clear the flag on their own flagged photograph**, so the person whose evidence was questioned closes the question | Only a reviewer other than the inspector clears a flag |
| A5 | DEF-QUA-013, 014 (P2) | Coordinators are refused the **agency-staff roster**, which the screen itself says is "for coordinators and managers" (013). The **SLA targets** tile is shown to Coordinators, but the screen refuses them because it is locked behind Settings (014) | 013: let Coordinators open the roster, as the screen says. 014: hide the tile from anyone who cannot open it. **Both touch who sees what: needs your yes** |
| A6 | HF-172 | The **Master Admin and Admin can still close nonconformities and corrective actions**, although your rule of 18 Oct says "only Quality or Technical Head can close it". The permission list already records this as your open question | **Your choice:** keep admins able to close (as today), or quality roles only. For an accredited body, quality-only is the cleaner separation of duties |
| A7 | QA-090, 139, 166, 172 | The **Business Director** can add instruments and switch competence enforcement on, but can only view management reviews and confidentiality | **Business Director views only; the quality function manages.** Or tell me what the Director should do |
| A8 | QA-179 | Coordinators cannot see **Site entry documents**, although they prepare people for site visits | **Coordinators see them read-only** |
| A9 | QA-077, 078, 080 | **Departures (approved deviations) have no lifecycle.** Any status can move to any other. An approved departure never expires, because the expiry routine is never called | Write the departure lifecycle into `docs/03-object-lifecycles.md` and run the expiry each night. **This adds a lifecycle: needs your yes** |
| A10 | 8 blocked tests | **Complaints** is now open to the operations roles, but the permission list has no Complaints row for them | **Which is right?** My proposal: the app is right, so I add the row to the permission list |

## B — Fixes I will make once you say "B ok" (small, no rule change)

**Settings that never save**
- **B1 DEF-QUA-001 (P2):** the complaint deadlines and the published complaints description (/complaints-policy) never save. The screen sends you away before it reaches the saving step.
- **B2 DEF-QUA-005 (P2):** the corrective-action deadline settings never save, for the same reason.

**Lost or wrong data**
- **B3 DEF-QUA-009 (P2):** a nonconformity raised from an upheld complaint loses its link to that complaint.
- **B4 DEF-QUA-010 (P2):** right after any save, the Method page opens blank and every form on it points at "method 0". *This is the same cause as Directory A1 (DEF-DIR-005): the page frame reuses some of the screen's own names. Here the name is the Method; in Directory it is the portal user.* I checked every screen in the code for this. One more screen is affected: the audit-trail page's **Office** filter never shows the office you picked, although the list itself is filtered. That one I found by reading the code; I have not seen it on screen yet. **One fix to the page frame cures all three.**
- **B5 DEF-QUA-016 (P2):** saving "Owner and date" on a nonconformity with the boxes empty erases its due date.
- **B6 DEF-QUA-019 (P2):** in the consent register, a second person typed by name for the same purpose is silently dropped, while the screen says "Consent recorded."
- **B7 DEF-QUA-017 (P3):** Hindi or Gujarati text in a short box is cut in the middle of a letter and stored broken.

**Impossible values on the equipment register (DEF-QUA-011, P2)**
- **B8:** refuse a duplicate instrument code, a certificate whose "Valid to" is before "Calibrated on", and a completely empty certificate.

**Things saved but never shown**
- **B9 DEF-QUA-015 (P3):** a site-document override reason is stored on the job but shown nowhere.
- **B10 DEF-QUA-018 (P3):** the person who approved a departure is stored but shown nowhere.

**Wrong wording or counts**
- **B11 DEF-QUA-007 (P3):** a nonconformity page shows two history panels that disagree. I would keep one.
- **B12 DEF-QUA-008 (P3):** the "Quality case" panel says a corrective action is "closed" as soon as a check is recorded.
- **B13 DEF-QUA-012 (P3):** the red number on the Competence tile ignores lapsed certificates.

**Forms**
- **B14 DEF-QUA-020 (P3):** an empty "Hold a review" form opens a numbered management review, and a double press opens two. Same family as Operations B (empty forms that save a numbered record).
- **B15 DEF-QUA-021 (P3):** the "Site" box on site entry documents is free text, and anything typed becomes "every site". I would make it a choice from the client's real sites.
- **B16 DEF-QUA-022 (P3):** the required-document ticks cannot all be cleared at once. *(Its second half, an upper limit on retention days, is a number only you can set: C6.)*

Plus new wording for the 8 out-of-date tests.

## C — Questions only you can answer (my proposal in **bold**)

| # | Tests | Question | Proposal |
| --- | --- | --- | --- |
| C1 | QA-061, 081, 123, 127, 164, 170 | **Impossible dates are accepted:** a nonconformity due before it was found; a departure, review or undertaking that ends before it starts; a failure recorded in the future | **Refuse each with one plain sentence** |
| C2 | QA-088, 093, 100, 104, 108 | **"Current" with nothing behind it:** a calibration certificate with no "Valid to" when the interval is 0; a method set Current while unvalidated; a decision rule set Current with no criteria, or while its method is withdrawn; a controlled document set Current with no approval | **Refuse each, and say what is missing.** Also: a FAIL certificate should read "failed calibration", not "cannot be used" |
| C3 | QA-149, 167, 174, SCR-compliance-rules | **Missing basics:** "Consented" with no date or name; a management review with no chair; a .zip accepted as a signed undertaking; tax-admin addresses not checked as e-mail addresses | **Refuse each with a plain sentence; accept only PDF or image for a signed undertaking** |
| C4 | QA-035, 130 | Items and risks close with no note, although the label says a note is required | **Require the note** |
| C5 | QA-094, 133, 160 | Methods due for review, risks due for review and past-dated audits are not marked overdue | **Show "overdue" in red, as Controlled documents already does** |
| C6 | SCR-identity | Retention days for identity documents have no upper limit | **Your number.** I will not choose it |
| C7 | QA-065 | Two people edit the same nonconformity at once; the last save silently wins | **Warn and refuse the older save**, as work orders already do |
| C8 | QA-069 | A corrective action judged ineffective shows "Superseded" | **Show "Closed as ineffective"** |
| C9 | QA-137 | Competence enforcement is not recorded in the permission list | **I add it to `docs/02`** |
| C10 | QA-147 | An expired impartiality declaration shows as "none" | **Show "expired on …"** |
| C11 | QA-021, QA-004 | /report-ack has no menu entry; nothing explains why tiles disappear when the inspection capability is off | **Add the menu entry; add one line: "Some registers are hidden because inspection is switched off"** |
| C12 | QA-007, 025, 031, 086, 087, 109, 190, QA-180 | Display choices where the app is reasonable and the test expected otherwise: the Handle tab shows only steps still to do; CSV of an empty list; Finance does not see Customer satisfaction; an expired instrument is added with a warning, then issue is refused; a quarantined instrument is still offered on new reports; automatic revision label "Rev 0+"; an SLA target of 0 days; a requirement for one site only | **Accept all, except two:** stop offering a quarantined instrument on new reports (QA-087), and refuse an SLA target of 0 days (QA-190). I update the other tests |
| C13 | SCR checks | Several settings screens say "saved" when nothing changed | **Say "Nothing to save — no changes"** (same as Admin C12) |
| C14 | Time zone | Everything ran with the server on UTC. The app never sets a time zone | **Same question as Reporting C18:** please send the time shown on `/deploy-check.php` for both sites |

## D — Older Quality problems checked again on V51.1

**8 older items checked:**
- **Still exist (6):**
  - HF-172: admins can close nonconformities and corrective actions (A6).
  - HF-198: `docs/03` has no lifecycles for the quality objects (A9 is the first of them).
  - HF-200: no two-people-editing warning and no message when tiles are hidden (C7, C11).
  - HF-090: there is no public complaint form for clients (E1).
  - HF-037: a training record does not grant an authorisation. *This is by design.*
  - HF-014: there is still no audit-programme planner (E2).
- **Fixed (1):** HF-195, for the two quality controls. A quarantined instrument's reports are flagged for recall, and competence enforcement refuses an unqualified person. *Its Sales part (the pre-order checklist) was not checked here.*
- **No longer applies (1):** HF-126. It asked for the Quality tests to be run, which is now done.

## E — Not built

- **E1:** a public complaint form for clients (HF-090).
- **E2:** an audit-programme planner (HF-014).
- **E3:** written lifecycles for the quality objects other than departures (HF-198).

None of them is a defect against V51.1. Each is new work that needs your yes and a priority.

## F — What is NOT tested yet

- **Hold & witness points:** the 7 tests reached only the refusal screen (A1). They must be run again after the fix.
- **8 tests waiting for new wording.** They need a rerun after that.
- **Real-phone step:** a photo with a genuine date and GPS position (QA-050).
- **Three forms the screen checker cannot fill:** ticking a clause, and typing into date-and-time boxes (new internal audit, new incident, identity-document access). The same forms were covered by the scenario tests.
- **QA-196:** a read-only walk-through of a management review. The clean copy has none to look at.
- **Only SQLite was used for this module.** The MariaDB run comes with the fix batch.
- **HF-195's Sales part** (the pre-order checklist) was not checked.

So the Quality & Accreditation picture is: **2 serious, 11 medium and 9 small defects; 6 older problems still present; 3 features not built; 10 items that need your yes (A1–A10) and 14 questions (C); 7 hold-point tests to rerun after A1; 8 tests waiting for new wording.**

## After you answer

- **Branch and access batch:** A1 and A2 belong with the other "who may see or change a record" fixes (Admin A1, Operations A1–A5, Reporting A1–A3, Directory A1). B4 also goes in that batch, because it is the same page-frame fix as Directory A1.
- **Quality batch:** the rest of A, once you answer, plus B.
- Each batch gets:
  - the full suite on SQLite and MariaDB;
  - the robot tests again;
  - a zip for the test copy;
  - the list of changed files;
  - the rollback steps.
