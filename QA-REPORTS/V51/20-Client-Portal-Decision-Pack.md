# Client & Vendor Portal — decision pack (V51.1)

16 Nov 2026 · The test book had no portal tests, so the tester wrote 29 from `docs/04-flows/portals-client-and-vendor.md` and from every portal address (CP-001 to CP-029). They were run twice on a private copy with made-up data, and the second run counts. Two client companies, sites, restricted users, an agency and a vendor were set up. I checked the important findings in the program code myself. No real payment or e-mail was used. Nothing has been changed in the app.

**How to answer:** reply with item numbers, for example *"A1 yes, A2 yes, A3 yes, A4 agree, B ok, C1 agree, C2 PDF only"*. Anything you don't mention stays untouched.

## Where the portal stands

| | Tests |
| --- | --- |
| Passed | 18 |
| Failed: 5 new defects (and 2 known ones still present) | 7 |
| Need your decision (section C) | 4 |
| **Total** | **29** |

The 5 new defects are **2 medium and 3 small**. None is fixed in the held v53.

**Good news, checked carefully:**
- **One client cannot see another client's records.** The tester tried every portal address with the other client's record numbers: work orders, reports, report files, report decisions, invoices, complaints, nonconformities, deputations, requests, hiring, vouchers and team. Every attempt was refused or answered "not found", and nothing was changed (table at the end).
- **The Messages security hole (A1, now fixed) has no twin in the portal.** Harmless test code was typed into all 10 places where a client's text reaches staff. It was then checked on 15 staff screens, and it always showed as plain text.
- **Staff and portal sign-ins stay apart.** A portal sign-in cannot open staff pages, and an inspector cannot manage portal users.

## A — Fixes that need your yes (scope, permissions, money)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-CP-003 (P2)** | **A client person limited to one site sees every deputation of their company and can approve attendance for another site.** That approval feeds billing. *Confirmed in the code (`lib/pdso.php:756-775`): the deputation lists and approvals filter by company, never by site.* | Apply the person's site limit to the deputation list, the approvals list and the approve action. **Billing and scope: needs your yes** |
| A2 | **DEF-CP-002 (P2)** | **Alerts ignore a person's permissions and site limit.** An accounts-only person sees "Report X issued to you" and "Nonconformity Y needs your response", and each link then refuses them. So they learn of reports they may not open. *Confirmed in the code (`lib/cvp.php`): alerts filter by company only.* | Show each person only the alerts for what they may open. **Permission: needs your yes** |
| A3 | DEF-CP-001 (P3) | The online-payment confirmation accepts **any** order number. A client who sends a made-up confirmation with **another client's** order number turns that client's pending payment to "Failed". The real risk is small, because order numbers are long random strings | Accept a confirmation only for the signed-in client's own order. **Payment: needs your yes** |
| A4 | CP-021 | **A Coordinator sees and manages the portal people of every client, not only their own office's clients.** They can withdraw access, change access, or press "New link". "New link" makes a fresh sign-in link and shows it **to the staff member**, and whoever opens it sets that client person's password. *Confirmed in the code (`lib/portal.php:1727-1738`): no office check, and the link goes on screen.* | Limit the portal-people register to the same clients as the invite list. **Send "New link" to the client person's own e-mail** and show staff only "sent". **Security and permission: needs your yes** |

**Known defects still present (not counted again):**
- **DEF-DIR-005 (Directory A1):** "Change" on a client person still opens the Coordinator's own details and saves them onto a person of another client (TB Steel Works). This is the same fix as Directory A1 and Quality B4.
- **DEF-OPS-043 (Operations B):** a work order closed by the desk still reads "With our coordinators" in the portal.

## B — Fixes I will make once you say "B ok" (small, no rule change)

- **B1 DEF-CP-004 (P3):** an agency's menu shows "My bench" twice, going to two different pages. I would keep one entry for each page, with clear names.
- **B2 DEF-CP-005 (P3):** the server stores impossible values typed into the portal:
  - a job posted with a rate of ₹9,000 to −₹50 and a start date of "not-a-date" goes live on the marketplace as Open;
  - a request "wanted by" 31/31/2026 is stored as typed;
  - **"Access ends on" accepts any text.** "20/12/2026" (written the Indian way) locks the person out at once, and "garbage" means access never ends.

  I would refuse each with a plain sentence and read dates the way the screen shows them. *(Negative rates are the same question as Marketplace C12; I follow your answer there.)*

## C — Questions only you can answer (my proposal in **bold**)

| # | Test | Question | Proposal |
| --- | --- | --- | --- |
| C1 | CP-004 | Changing a portal password does not ask for the current one and does not sign out other devices | **Ask for the current password and sign out other devices, except on the forced first change after an invitation or reset link** |
| C2 | CP-010 | Clients can see their invoice list but cannot download an invoice PDF or pay an invoice in the portal | **Add a PDF download for the client's own invoices now. Leave online invoice payment until you decide (money); meanwhile show "how to pay"** |
| C3 | CP-012 | **When a client complains, the first 25 active users are e-mailed, whatever their role.** On the copy that was 20 people, including 3 inspector logins. That can include the very inspector the complaint is about | **E-mail only the people who may record or decide complaints, never the inspector named in it, with no 25-person cut-off** |
| C4 | CP-021 | Signing in as staff in the same browser does not end a client's portal session (the reverse does) | **A staff sign-in ends any portal session in that browser** |

## D — Older items checked again on V51.1

- **HF-012, fixed:** issue (nonconformity) views exist and are kept apart in both the client and vendor portals.
- **HF-158, still exists:** the client-portal permissions are now in `docs/02`, but the **vendor portal's 4 permissions are not**. *Proposal: I add them to the permission list, as documentation only.*
- **HF-168, still exists (client part):** bad input is stored (B2). Empty screens and phone width pass. Its professional-portal part was not checked.
- **HF-130, no longer applies:** it asked for the client-portal tests to be run, which is now done.

## E — Not built

E1 invoice PDF download in the portal, and E2 online invoice payment (C2). Both are new work, not defects.

## F — What is NOT tested yet

- **Real Razorpay checkout** (CP-011): only the app's side was tested, never a real payment.
- **The portal assistant with AI switched on:** the copy has no AI provider.
- **Self-service sign-up through /join:** not started. The New company tester covers sign-up and may reach it.
- **Real e-mail delivery:** only the app's mail record was checked.
- **The professional portal's own write actions** (`/pro/*`, the rest of HF-168): outside this tester's scope. Marketplace covered most of them.
- **Only SQLite was used.** The MariaDB run comes with the fix batch.

So the portal picture is: **separation between clients holds; 2 medium and 3 small defects; 1 serious design question (A4: who manages client sign-ins, and where "New link" goes); 2 known defects still present; 4 questions.**

## After you answer

- **A1, A2 and A4** belong in the "branch and access" batch: they are the same "who may see or change a record" gap as the other packs. DEF-DIR-005 is fixed there too.
- **A3** goes in the Money batch.
- **B** goes in a small portal batch.
- Each batch gets:
  - the full suite on SQLite and MariaDB;
  - the robot tests again;
  - a zip for the test copy;
  - the changed-files list;
  - the rollback steps.

## Separation table (client A trying client B's records, or a person without the right)

| Address | Result |
| --- | --- |
| Work order | Not found (also with a site limit) |
| Report, report file, report decision | Not found for B's reports, drafts, deleted or made-up ones and other sites; nothing stored |
| Invoices | B's not listed |
| Pay (confirmation) | **Spoils B's pending order (A3)** |
| Complaints, new complaint, appeal | B's not listed; B's job dropped; appeal of B's complaint refused |
| Nonconformities | Not found for B's and internal ones |
| Deputations | Refused between clients; **approved across sites within a company (A1)** |
| Requests, hiring, vouchers, voucher files | Not found or "not yours"; nothing changed |
| Agency bench and roster | Other agency's entry refused or untouched |
| Team (withdraw, access end) | "Not in your organisation" |
| Alerts | B's untouched; **restricted people still see their company's alerts (A2)** |
| Accounts-only person | Refused at all 18 other gated addresses; 8 forced changes changed nothing |
| Portal sign-in on staff pages / staff sign-in on portal pages | Sent to the right sign-in page |
| Inspector on portal-people management | Refused (403) |
