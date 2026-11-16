# Marketplace — decision pack (V51.1)

16 Nov 2026 · I ran all 136 Marketplace tests twice on private copies of V51.1 with made-up data, and both runs gave the same results. The tests covered requirements, applications, awards, engagements and vouchers, the professional portal (/pro), agency benches, verification, talent search, taxonomy, messages, ratings, plans and coupons, and the client portal's marketplace screens. I checked the critical finding in the program code myself. No real payment, e-mail or WhatsApp message was used. Nothing has been changed in the app.

**How to answer:** reply with item numbers, for example *"A1 yes now, A2 yes, A3 branch, B ok, C1 agree, C3 freeze"*. Anything you don't mention stays untouched.

## Where Marketplace stands

| | Tests |
| --- | --- |
| Passed | 82 |
| Failed: 23 defects | 20 |
| Need your decision (section C) | 24 |
| Blocked (test wording out of date) | 7 |
| The robot cannot do it (real phone QR scan, real WhatsApp, one 1,258-step screen check) | 3 |
| **Total** | **136** |

The 23 defects are: **1 critical (P0), 11 medium and 11 small.** The critical one is the first P0 in this whole audit.

**Good news:** the clients are kept apart. A client signed in to the portal cannot open another client's requirement or applicant, and a stale browser tab changes nothing.

## A — Fixes that need your yes (security, scope, money)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-MKT-016 (P0, critical)** | **Anyone who registers as a professional can plant a script in the staff Messages inbox.**<br>• The inbox list shows the first 60 characters of each message without making them safe.<br>• A test message containing a hidden script ran in the Coordinator's browser the moment Messages was opened.<br>• Such a script could act as that staff member for as long as they are signed in.<br>*I confirmed it in the code (`views/ops/connect_messages.php:51`): the safety function is closed before the message text is added.*<br>• The same line is in the held v52 and v53.<br>• Anyone on the internet can register at /pro/register and apply to an open requirement. | **Make the message text safe before it is shown (a one-line fix), then check every Marketplace and portal screen for the same mistake.**<br>A quick scan of the other screens found no second case. A full check belongs with this fix.<br>**I recommend this as its own small, urgent batch.** Your earlier approval covers P2 and P3 fixes only, so this needs your yes.<br>If the Marketplace is switched on for your live site, please also tell me. I would then ask you for permission to look for planted messages there (read-only) |
| A2 | DEF-MKT-014 (P2) | **A professional can see ratings that belong to a staff inspector** whose internal number happens to match theirs (/pro/reputation) | Look up ratings by the professional's own record only. **Privacy: needs your yes** |
| A3 | DEF-MKT-001, 002 (P2) | Switching off the "Marketplace add-on" box does not switch off the staff Marketplace (001). With the Marketplace off, a requisition still shows marketplace matches (002) | Off means off: hide the staff screens and the matches. **Licensing: needs your yes** |
| A4 | DEF-MKT-006 (P2) · DEF-MKT-008 (P3) · MKT-039 | **Requirements carry no branch**, so anyone can award and bill any branch's requirement (006). Team passports are not limited to the user's office (008) | Give each requirement the poster's branch, and allow award, booking and billing only inside the person's branch scope. Limit team passports the same way. **Scope: needs your yes.** Belongs in the "branch and access" batch |
| A5 | DEF-MKT-010 (P2) | A verification decision can be overwritten from an old browser tab, with no "changed since you opened it" warning | Refuse the older save, as work orders already do. **Approval workflow: needs your yes** |
| A6 | DEF-MKT-020 (P2) | **Pressing Save on the empty requirement settings sets commission to 0% and the open period to 1 day** | Refuse an empty save and keep the old values. **Commission: needs your yes** |
| A7 | DEF-MKT-023 (P2) | The same coupon code can be created twice, so which discount applies is unclear | Refuse a duplicate code. **Billing: needs your yes** |

## B — Fixes I will make once you say "B ok" (small, no rule change)

**Wrong figures on screen (display only, no calculation changes)**
- **B1 DEF-MKT-004 (P2):** "10 man-days" shows as "1", "20" as "2", and so on. The trailing zero is cut off. *Not fixed in v53.*
- **B2 DEF-MKT-005 (P3):** the desk voucher card shows no commission, although the voucher holds one.
- **B3 DEF-MKT-007 (P3):** Talent search stops at 60 people and still shows "60" as the total.

**Things saved or set but never shown or used**
- **B4 DEF-MKT-019 (P2):** a professional's passport never shows their credentials or verification tier.
- **B5 DEF-MKT-009 (P3):** talent cards show no verification tier.
- **B6 DEF-MKT-013 (P3):** the role is stored but shown nowhere.
- **B7 DEF-MKT-011 (P2):** a discipline newly added to the taxonomy is not offered when posting a requirement.

**Messages that never go out**
- **B8 DEF-MKT-015 (P2):** a professional is never told when they are shortlisted, awarded or matched to a job. *(This uses the app's existing in-app and e-mail channels; nothing is sent from the test copies.)*

**Forms that accept nonsense or say the wrong thing**
- **B9 DEF-MKT-003 (P3):** the posting form accepts a "Rate from" above the "Rate to", and an end date before the start date.
- **B10 DEF-MKT-012 (P3):** an NSQF band of −1, or a minimum above the maximum, is saved. *(The allowed range is your call: C9.)*
- **B11 DEF-MKT-018 (P3):** an allocation to a Closed requirement is accepted. *(The negative day rate in the same defect is C12.)*
- **B12 DEF-MKT-017 (P3):** saving an empty message template is silently ignored, with no message.
- **B13 DEF-MKT-021 (P3):** a duplicate taxonomy name answers "Node added." but adds nothing.
- **B14 DEF-MKT-022 (P3):** the star box on a new rating starts at 5 stars instead of empty.

Plus new wording for the 7 out-of-date tests.

## C — Questions only you can answer (my proposal in **bold**)

**Rules for posting and expiry**

| # | Tests | Question | Proposal |
| --- | --- | --- | --- |
| C1 | MKT-010, 019 | A requirement can be posted as Open with only a title | **"Post it" needs discipline, location and rate; a draft needs only a title** |
| C2 | MKT-011 | May a requirement start in the past? | **Refuse on "Post it"; allow on a draft** |
| C3 | MKT-029 | What happens to open applications when a requirement expires? | **Close them as "Not selected — requirement expired", tell each applicant, and show the professional "offer withdrawn"** |
| C4 | MKT-030 | An expired key credential is neither warned about nor enforced | **Warn on the requirement and the booking; block "Create deployment" until it is renewed or a manager overrides with a reason. Workflow: needs your yes** |

**Money (each needs your yes)**

| # | Tests | Question | Proposal |
| --- | --- | --- | --- |
| C5 | MKT-014 | After the commission rate changes, which rate does a Draft voucher carry? | **Freeze the rate when the voucher is raised.** Or recalculate all open drafts at once and say so on screen |
| C6 | MKT-025 | No screen lists requirements that were awarded but never sent to billing | **Add an "Awarded — not billed yet" list on the Marketplace board and on Money → Billable events** |
| C7 | MKT-096 | One unusual rate distorts the rate benchmark, and cancelled requirements are counted | **Use the median; leave out Cancelled and Draft** |
| C8 | MKT-114 | For a requirement the desk posts for a client, the client approves the vouchers and the desk cannot | **Please confirm that this is intended** |

**Who may do what (each changes a permission: needs your yes)**

| # | Tests | Question | Proposal |
| --- | --- | --- | --- |
| C9 | MKT-063 | What NSQF levels are allowed? | **1 to 10, with minimum ≤ maximum** |
| C10 | MKT-043 | Should Finance see professionals' passports? | **Coordinator level and above only; Finance views through the public link** |
| C11 | MKT-050 | May a Coordinator verify a professional's identity? | **No. Operation Manager / Branch Manager and above, the same level that may open staff identity documents** |

**Small rules**

| # | Tests | Question | Proposal |
| --- | --- | --- | --- |
| C12 | MKT-088 | A negative day rate is saved | **Refuse: "Day rate cannot be below 0"** |
| C13 | MKT-092 | One bench person is confirmed on two requirements at the same time | **Allow several "Proposed"; refuse a second overlapping "Confirmed"** |
| C14 | MKT-075 | May phone numbers and e-mail addresses be shared in messages before the award? | **Hide them until the award and flag the thread for review** |
| C15 | MKT-101, 104, 109 | A duplicate organisation name; changing the type of an organisation that has a bench; repeat sign-ups from one e-mail | **Warn and ask to confirm (first two); merge repeat sign-ups into one waiting request** |
| C16 | MKT-057, 060 | A duplicate taxonomy item; two admins edit one item at once | **Answer "already exists — opened it"; keep last-save-wins only if every change is logged** |
| C17 | MKT-082 | SMS length | **Show a live character count and warn above 160** |
| C18 | MKT-093, 095 | The funnel and the requirement list disagree; "Pool growth" vs the Talent search total | **Label the bars "Posted (not drafts)" and "All (incl. drafts)"; show a running total next to the monthly count** |
| C19 | MKT-064 | Taxonomy admin is slow and hard to use on a phone (771 boxes on one page) | **One edit form at a time with a search box per tab; laptop screen, read-only on phone; target under 3 s** |
| C20 | MKT-035 | A place the app does not know shows the people who said "all India", with an amber note | **Keep; I update the test** |
| C21 | 4 screen checks | An empty save on match weights, escrow, ratings and ratings settings | **Refuse with "Nothing to save — no changes" (same as Admin C12)** |
| C22 | SCR-marketplace-plans | Accepted terms cannot be withdrawn | **Please confirm whether they may be** |

## D — Older Marketplace problems checked again on V51.1

**5 older items checked:**
- **Fixed (1):** HF-202. The client portal's "Find" list no longer exposes professionals' contact details.
- **Still exists (1):** HF-200, Marketplace parts. The guided posting page now has a heading (fixed), but the qualifications page still draws every form at once (C19).
- **No longer applies (1):** HF-107. The freelancer-and-agency marketplace it asked for now exists.
- **Needs you (1):** HF-205, commission and escrow. Only a master can change commission, and the change is logged (good). But the **escrow** screen opens for every role from Coordinator up, and the permission list does not say who may release escrow money. **Who may release escrow? My proposal: Master Admin, Admin and Finance only. Money and permission: needs your yes.**
- **Partly checked (1):** HF-168, every write action of the professional and client portals. Most were exercised in this run; a full sweep comes with the Client Portal tests.

## E — Not built

None found in this module.

## F — What is NOT tested yet

- **7 tests waiting for new wording.** They need a rerun after that. Later decisions changed these screens on purpose: the hub tabs and one-press award (10 Nov), the Owner home switch (10 Nov), one commission rate (11 Nov), and organisations that /join approves automatically.
- **Real phone:** scanning a passport QR code (MKT-041).
- **Real WhatsApp provider** (MKT-083).
- **Qualifications screen check:** it stopped at the 15-minute cap after 1,040 of its 1,258 steps. 514 of those steps passed, and 525 could not be judged because they sit in hidden tabs. The last 218 steps did not run.
- **Real payments:** none were made. Plan and coupon payments were tested in the app's test mode only.
- **Only SQLite was used for this module.** The MariaDB run comes with the fix batch.

So the Marketplace picture is: **1 critical, 11 medium and 11 small defects; 1 older problem still present; 23 questions (C1–C22 and the escrow question in D); 7 tests waiting for new wording.**

## After you answer

1. **A1 first, on its own:** one small security batch. It includes the fix, a check of every Marketplace and portal screen for the same mistake, the full suite on SQLite and MariaDB, the Marketplace robot tests again, a zip for the test copy, the list of changed files and the rollback steps.
2. **A4** goes into the "branch and access" batch.
3. **A6, A7 and C5–C7** go into the Money batch.
4. **The rest:** one Marketplace batch with B.
