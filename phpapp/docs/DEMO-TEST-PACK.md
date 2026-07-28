# Demo test pack — what is seeded, and what should happen

**How to use this.** Settings → Demo / sample data → **Load demo data**. Then work
down this document. Each row names a screen, what to do, and what the application
should do back. A row that does not behave as written is a defect — note the row
number and it can be traced straight to the rule.

Sign in as `admin`, or as any demo user: `director`, `sbuhead`, `bmanager`,
`appmanager`, `opmanager`, `asstmgr`, `coord.amd`, `coord.pun`, `account`,
`insp.ravi`, `insp.anil` — all with password `demo12345`. The client portal user
is `buyer@narmada.example`, same password.

**Read this before you start.** Roughly half the seeded records are deliberately
wrong. An expired calibration, a lapsed authorisation, a complaint nobody
acknowledged in time, a corrective action that did not work, an identity document
held past its retention date. That is on purpose: a register full of tidy,
compliant rows cannot tell you whether a rule is working or whether the rule was
never written. **Every red flag listed below is expected.** Anything red that is
*not* listed below is worth reporting.

---

## 0. Before anything else

| # | Screen | Do this | Expected |
|---|---|---|---|
| 0.1 | Settings → Demo / sample data | Expand **Module coverage** | All 32 modules ticked, none showing "no demo data" |
| 0.2 | `/data-control` | Run the integrity checks | **Exactly one** failure: an identity document held past its retention date (row 8.2). Everything else passes |
| 0.3 | Any screen | Look at the sidebar | Every module you have licensed appears. Registers are not empty |

---

## 1. Sales — inquiries and quotations

| # | Screen | Do this | Expected |
|---|---|---|---|
| 1.1 | `/inquiries` | Open the register | Two inquiries. `INQ-2607-01` converted, `INQ-2607-02` still open |
| 1.2 | `/inquiries` | Look at `INQ-2607-02` | Received 26 days ago and still open — it should read as needing a chase |
| 1.3 | `/quotes` | Open the register | Three quotations: one **accepted**, one **sent**, one **lost** |
| 1.4 | `Q-2607-001` | Open it | Accepted, linked to inquiry `INQ-2607-01`, contract number `40231`, two line items totalling ₹1,50,000 + 18% GST = ₹1,77,000 |
| 1.5 | `Q-2607-002` | Open it | Sent 13 days ago with 15 days' validity — **2 days left**. Advance 20% required |
| 1.6 | `Q-2607-003` | Open it | Lost, reason **Price**. It should appear in the loss analysis, not the pipeline |
| 1.7 | Sales dashboard | Open it | Win/loss reflects 1 won, 1 open, 1 lost — not 3 open |

---

## 2. Operations — calls and deputations

| # | Screen | Do this | Expected |
|---|---|---|---|
| 2.1 | `/calls` | Open the register | 156 calls (6 written by hand, 150 generated to spread the edges) |
| 2.2 | `C-2607-001` | Open it | Same-office job: Ahmedabad manages and executes. Billable ₹1,50,000, **no** inter-office credit |
| 2.3 | `C-2607-002` | Open it | Cross-office: Mumbai manages, Ahmedabad executes. Credit ₹1,84,000 **to Ahmedabad**, billable zero |
| 2.4 | `/calls` | Sort by lead time | Calls with no required-by date do not crash the sort or show as overdue |
| 2.5 | `J-2607-105` | Open it | Allocated with **no engineer** — a sub-contractor deputation. Cost ₹24,000 |
| 2.6 | `/jobs` | Filter to overdue | Open deputations scheduled in the past appear; closed ones do not |
| 2.7 | `/availability` | Open the board | Four engineers, with the closed deputations shown against their days |

---

## 3. Inspection reports (IDEMS)

| # | Screen | Do this | Expected |
|---|---|---|---|
| 3.1 | `/documents` | Open the register | Four reports: issued, issued-rejected, submitted, draft |
| 3.2 | `MGH/AMD/2026/D-0001` | Open it | Issued, result **Accepted**, released. Carries a verification code |
| 3.3 | `MGH/AMD/2026/D-0002` | Open it | Issued, result **Rejected**, release status **Not released**. This is the outcome that matters — a report can be final and still refuse the goods |
| 3.4 | `MGH/PUN/2026/D-0003` | Open it | **Submitted**, waiting with the approver. Not editable as if it were a draft |
| 3.5 | `MGH/AMD/2026/D-0004` | Open it | **Draft**. Must not appear anywhere client-facing (see 11.4) |
| 3.6 | `D-0001` | Try to edit the evidence | Refused — a finalised report's evidence is locked |
| 3.7 | `D-0001` → PDF | Open the PDF | Renders. Header, findings, signature block present |
| 3.8 | `/endorsements` | Open the register | Two: one **endorsed**, one **rejected** with the reason (photocopy, no mill stamp) |

---

## 4. The trust layer — evidence bound to place and time

This is the module built around your own correction: an engineer photographs the
work on site, drives home, and writes the report that evening. The location of
the *report*, and of the *upload*, must never stand in for where the inspection
happened.

| # | Screen | Do this | Expected |
|---|---|---|---|
| 4.1 | `/evidence-review` | Open it | Three evidence items across two reports |
| 4.2 | `D-0001` evidence | Look at the two photographs | Location credited **to the photograph** (20.372, 72.908 — the plant), not to the upload |
| 4.3 | Same | Look at the upload location | Shown separately as 23.02, 72.57 — Ahmedabad, the engineer's home. **Labelled as the upload**, never as the inspection site |
| 4.4 | Same | Look at the times | Taken 11:41, uploaded 21:14 the same day. **No flag.** A late upload is the normal working day and flagging it would train everybody to ignore flags |
| 4.5 | `D-0002` evidence | Look at `joint-07-root.jpg` | Flagged **No location / upload only** — a corporate handset that strips EXIF. Flagged as unknown, not as a lie |
| 4.6 | `J-2607-101` | Look at the site check-in panel | Arrived 09:12, left 14:40 — **"On site 09:12 to 14:40"**, the line a client actually wants |
| 4.7 | `J-2607-106` | Look at the check-in panel | An arrival with **no departure**. The engineer forgot; the screen should say so |
| 4.8 | `J-2607-103` | Look at the check-in | Device clock **47 minutes** out from ours. Recorded, compared, and our time used. Flagged as clock skew |
| 4.9 | `/verify` | Copy the code from `D-0001` and open `/verify` **signed out** | "Genuine, and unaltered since it was issued." Shows 2 of 3 items located on site. Shows **no** client name, no findings, no prices |
| 4.10 | `/verify` | Type `ZZZZ-ZZZZ-ZZZZ-ZZZZ` | "We do not recognise that code", and an invitation to report it |

---

## 5. Equipment and calibration (§6.2)

| # | Screen | Do this | Expected |
|---|---|---|---|
| 5.1 | `/equipment` | Open the register | Four instruments |
| 5.2 | `EQ-UT-001` | Open it | Calibrated, valid another 215 days. Issued to Ravi Kumar. **Usable** |
| 5.3 | `EQ-VC-002` | Open it | Calibration expires in **12 days** — flagged as due |
| 5.4 | `EQ-DFT-003` | Open it | Calibration **expired 18 days ago** and the instrument is still marked Active. This must be visible as a problem — an instrument nobody withdrew is the realistic failure |
| 5.5 | `EQ-HT-004` | Open it | Hired instrument whose calibration **FAILED**. The failed certificate is still on the register, because a failure is the record |
| 5.6 | `D-0001` | Look at equipment used | `EQ-UT-001` linked, with the calibration certificate that was current on the day |

---

## 6. Competence and authorisation (§6.1)

| # | Screen | Do this | Expected |
|---|---|---|---|
| 6.1 | `/competence` | Open it | Three qualifications in the library, four authorisations |
| 6.2 | Ravi Kumar | Look at his authorisation | Senior Inspector, Welding, valid another 240 days |
| 6.3 | Anil Sharma | Look at his | Expires in **9 days** — flagged as due for renewal |
| 6.4 | Priya Nair | Look at hers | **Suspended**, with the reason on the record (re-assessment after the Mundra observation) |
| 6.5 | Mohan Rao | Look at his | **Expired 5 days ago** — and he is a sub-contractor, the combination most often missed |
| 6.6 | Settings | Turn authorisation enforcement **on**, then try to allocate Mohan Rao to a deputation | **Refused**, naming the expired authorisation. Turn it back off afterwards |
| 6.7 | Witness assessments | Open them | Ravi **passed**; Priya **failed** with specifics (gauge calibration not checked, records not contemporaneous) — which is what should drive 6.4 |

---

## 7. Impartiality (§4.1)

| # | Screen | Do this | Expected |
|---|---|---|---|
| 7.1 | `/impartiality` | Open it | Four declarations, three threats |
| 7.2 | Priya Nair's declaration | Open it | **Declares a conflict**: brother is a production supervisor at Mundra Fabrication Yard |
| 7.3 | Mohan Rao's declaration | Open it | **Lapsed 6 days ago** — should be flagged for renewal |
| 7.4 | Threat register | Look at the familiarity threat | **Closed**, with real safeguards recorded (removed from that vendor; second review of any report naming it) |
| 7.5 | Threat register | Look at the self-interest threat | **Open**, and its review date passed 9 days ago — overdue |
| 7.6 | Threat register | Look at the intimidation threat | Client asked for a rejection to be re-graded. Open. This is the one that connects to complaint 9.4 |

---

## 8. Identity documents (DPDP)

Kept deliberately small — it is real personal data even in a demo.

| # | Screen | Do this | Expected |
|---|---|---|---|
| 8.1 | `/identity` | Open it as `admin` | Three documents. Numbers are **masked** — you see the last four digits only |
| 8.2 | Anil Sharma's licence | Look at it | **Past its retention date.** This is the one integrity-check failure in 0.2, and it is meant to be there |
| 8.3 | Priya Nair's passport | Look at it | Already **redacted** — the number is gone, the record that we held it remains |
| 8.4 | Ravi Kumar's passport | Look at it | Purpose stated (site access pass), consent recorded, retention date in the future |
| 8.5 | Any document | Reveal a number | You are asked why, and the reveal is written to the access log with your name |
| 8.6 | Sign in as `coord.amd` | Open `/identity` | **Refused.** Running operations is not a reason to read a colleague's passport |

---

## 9. Complaints and appeals (§7.5, §7.6)

| # | Screen | Do this | Expected |
|---|---|---|---|
| 9.1 | `/complaints` | Open the register | Four: one closed, two open, one appeal |
| 9.2 | `CMP-2026-001` | Open it | The complete journey: received → acknowledged next day → valid → investigated → **upheld** → client told → closed. Raised `CAPA-2026-001` |
| 9.3 | `CMP-2026-002` | Open it | Open, received 9 days ago, **never acknowledged** — past the deadline and it should say so |
| 9.4 | `CMP-2026-003` | Open it | **Anonymous**, raised internally, about pressure to soften a finding. No complainant name anywhere |
| 9.5 | `APL-2026-001` | Open it | An **appeal** against the decision on `CMP-2026-001` |
| 9.6 | `APL-2026-001` | Sign in as `bmanager` (Meena Shah, who decided the original) and try to decide the appeal | **Refused**, citing §7.6 — the person who decided the complaint may not decide the appeal |
| 9.7 | `APL-2026-001` | Sign in as `director` (Rahul Desai) and decide it | Allowed |
| 9.8 | `CMP-2026-002` | Try to close it without telling the complainant | **Refused** — a complaint cannot close until the complainant has been told |
| 9.9 | `/complaints-policy` | Open it **signed out** | Readable without an account — §7.5.1 requires it to be available to any interested party |

---

## 10. Corrective actions, audit and review (§8.7–8.9)

| # | Screen | Do this | Expected |
|---|---|---|---|
| 10.1 | `/capa` | Open the register | Three corrective actions |
| 10.2 | `CAPA-2026-001` | Open it | Complete: root cause, method (five whys), the §8.7.2 d) similar-work answer, plan, completed, independently verified, **effective YES**, closed |
| 10.3 | `CAPA-2026-002` | Open it | Open, due date **passed 4 days ago**. No root cause recorded yet |
| 10.4 | `CAPA-2026-002` | Try to close it | **Refused** — no root cause, no method, no similar-work answer, no verification |
| 10.5 | `CAPA-2026-003` | Open it | Done, verified, and **effective NO** — the action did not work. Honest about why: asking people to change a setting the handset resets on reboot |
| 10.6 | `CAPA-2026-003` | Try to close it as done | **Refused.** An action that did not work cannot close as effective; it needs a successor action |
| 10.7 | `/internal-audits` | Open the register | `IA-2026-001` closed with three findings; `IA-2026-002` **planned and overdue** |
| 10.8 | `IA-2026-001` | Look at the findings | One nonconformity (linked to `CAPA-2026-002`), two observations |
| 10.9 | Audit coverage | Open the coverage view | Clauses 6.2, 7.3, 7.4 audited; the rest **not yet** — the gaps are visible, which is the point |
| 10.10 | `MR-2026-01` | Open it | Completed, all 15 required inputs recorded, three actions arising |
| 10.11 | `MR-2026-02` | Try to complete it | **Refused** — its inputs have not been recorded |
| 10.12 | `MR-2026-01` actions | Look at them | One done, two open — one of which is overdue |

---

## 11. Client portal

The portal is switched **on** by the demo. A real install starts with it off, and
"Remove demo data" switches it back off.

| # | Screen | Do this | Expected |
|---|---|---|---|
| 11.1 | `/portal-users` | Open it as `admin` | Three client logins: one active, one **invited but not accepted**, one **withdrawn** |
| 11.2 | `/portal/login` | Sign in as `buyer@narmada.example` / `demo12345` | Lands on the client overview, naming **Narmada Industries** |
| 11.3 | Portal → Reports | Look at the list | `MGH/AMD/2026/D-0001` only |
| 11.4 | Portal → Reports | Look for the draft | `MGH/AMD/2026/D-0004` is **not there**. A draft is not a report |
| 11.5 | Portal → Reports | Look for another client's report | `D-0002` (Suryavan) and `D-0003` (Girnar) are **not there** |
| 11.6 | Portal | Edit the URL to `/portal/call?id=` another client's call id | **404**, not 403 — it does not exist to them |
| 11.7 | Portal → Invoices | Look at the list | Narmada's invoices only, with the overdue one marked. No cost, no margin, no credit figures anywhere |
| 11.8 | Portal | Try `/calls`, `/documents`, `/portal-users` while signed in as the client | Thrown out to the **staff** sign-in every time |
| 11.9 | Portal → Requests | Look at the list | Two: one new, one accepted with our reply |
| 11.10 | `/portal-users` | Try to decline the new request with no reason | **Refused** — declining in silence is why clients stop using a portal |
| 11.11 | `/portal-users` | Switch the portal **off**, then open `/portal` signed out | Bare **404**. No shell, no hint that a portal exists. Switch it back on |

---

## 12. Money

| # | Screen | Do this | Expected |
|---|---|---|---|
| 12.1 | `/invoicing` | Open it | Invoices raised, paid and outstanding across the generated set |
| 12.2 | `/profitability` | Open it | Figures per contract number. Same-office jobs show billable value, cross-office show credit |
| 12.3 | Sign in as `coord.amd` | Open `/profitability` | **Refused** or figures hidden — a coordinator does not see what the branch earns |
| 12.4 | Sign in as `account` | Open a deputation | Sees invoice and payment, **not** salary or loaded cost |
| 12.5 | `/vouchers` | Open one | 34 vouchers across four engineers and eight months, in all four states, including leave-only months at ₹0 |

---

## 13. Access and roles

| # | Screen | Do this | Expected |
|---|---|---|---|
| 13.1 | Settings → Roles & access | Open it | Every module appears in a group. Nothing under "Not yet grouped" |
| 13.2 | Sign in as each demo user | Look at the sidebar | Each sees only what their role allows; no blank screens and no dead links |
| 13.3 | Sign in as `insp.ravi` | Look around | My deputations and my voucher. Not the profitability screens, not other engineers' vouchers |
| 13.4 | `/compliance` | Open it as `director` | Each accreditation clause measured, including the ones failing above |

---

## 14. Putting it back

| # | Screen | Do this | Expected |
|---|---|---|---|
| 14.1 | Settings → Demo | **Remove demo data** | Every seeded register empties. Around 590 records deleted |
| 14.2 | Settings → Demo | Expand Module coverage | Now shows the modules as empty — the check reads the database, it does not remember |
| 14.3 | `/portal` signed out | Open it | **404** — removing the demo switched the portal back off |
| 14.4 | Settings → Demo | **Load demo data** again | Loads cleanly a second time. The demo is a cycle, not a one-way door |

---

## Known and expected "failures"

Listed here so they are never mistaken for defects:

1. **One integrity check fails** — an identity document held past its retention
   date (8.2). Deliberate: a demo where every check passes cannot show you that
   the checks run at all.
2. **An instrument with an expired calibration is still Active** (5.4).
   Deliberate: this is the realistic failure, and the register must show it.
3. **An authorisation and an impartiality declaration have both lapsed**
   (6.5, 7.3). Deliberate.
4. **A complaint is past its acknowledgement deadline** (9.3). Deliberate.
5. **A corrective action was verified as not effective** (10.5). Deliberate, and
   the most valuable row in the pack — it is the one that proves the close gate
   distinguishes "done" from "worked".
6. **An internal audit is planned and overdue** (10.7). Deliberate.
7. **Audit coverage has gaps** (10.9). Deliberate — a coverage screen that shows
   full coverage on a demo teaches nothing.

## What the demo does not cover

Stated plainly rather than left to be discovered:

- **GST and e-invoicing.** Tax stops at the quotation. There is no HSN/SAC, no
  place of supply, no IGST/CGST split and no IRN. That is roadmap item 5.2 and
  depends on the MGH Books decision.
- **Outbound e-mail.** Nothing is actually sent; the mail log records what would
  have been. Configure SMTP in Settings to exercise it for real.
- **Receivables ageing, satisfaction capture, consolidated invoicing** — roadmap
  5.3–5.5, not built.
- **File uploads at realistic size.** Evidence photographs in the demo are
  1-pixel placeholders, so the register is fast to load. Upload a real
  photograph to exercise compression and EXIF reading.
