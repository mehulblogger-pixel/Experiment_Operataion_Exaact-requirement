# Inspection Ops — Manual Test Execution Guide (Hands-On Runbook)

> **Read this first.** The 31 module reports and the two synthesis reports tell you *what*
> each control should do and *why*. **This guide tells you how to sit at the screen and prove
> it** — which account to log in as, which screen to open, the exact values to type, the
> button to press, and the exact result to expect. Work top to bottom, or jump to any single
> test. Every test has a **Pass / Fail** box and a **Notes** line. Each test also names the
> module report and TC-ID it comes from, so a failure is traceable.

**App:** https://operations.mghaiapps.com  ·  **Golden-thread order:** Sales → Operations →
Reporting → Money → Quality.

---

## 0. Before you start (5 minutes of setup)

### 0.1 Log in

Open the app → you land on the **Login** screen (`/login`). Enter a username + password →
**Sign in**.

- **On a demo/test database** (recommended for testing), the sample accounts below all use the
  password **`demo12345`**. If they don't exist yet, log in as your **Master Admin** first and
  **load the demo data** (§0.3).
- **On your live database**, use your own real accounts. The *roles* below are what matter —
  substitute one of your users who holds that role.

### 0.2 The test accounts (roles you'll switch between)

| Username (demo) | Role | Office | You use it for |
|---|---|---|---|
| *(your admin)* | **Master Admin** | — | settings, super-admin, overrides, load demo |
| `director` | Business Director | Mumbai | executive dashboards, org-wide view |
| `sbuhead` | Business Unit Head | Mumbai | SBU oversight, pending-tasks on dashboard |
| `bmanager` | Branch Manager | Ahmedabad | approvals, overrides, hold/block a client |
| `appmanager` | Branch App Manager | Ahmedabad | timestamp edits, contract-open approve |
| `opmanager` | Operation Manager | Ahmedabad | operations, close-override |
| `coord.amd` | Coordinator | Ahmedabad | raise calls, allocate jobs, quotes |
| `coord.pun` | Coordinator | Pune | scope test (should NOT see Ahmedabad's work) |
| `account` | Accountant / Finance | Ahmedabad | invoices, payments, credit |
| `insp.ravi` | Inspector | Ahmedabad | fill reports, submit, my-jobs, my-voucher |
| `insp.anil` | Inspector | Pune | scope test |

> **Tip:** keep two browsers (or one normal + one private window) open so you can be two people
> at once — e.g. `coord.amd` submits a report while `bmanager` approves it.

### 0.3 Load (or reset) the demo data

Log in as **Master Admin** → **Admin** area → **Super-admin** (`/super-admin`) *or* **Settings**
(`/settings`) → find **"Load demo data"** → click it.
**Expect:** a confirmation that the sample offices, clients, vendors, inspectors, calls, jobs
and reports were created, and the eleven demo logins are shown. Re-running it is safe (it only
touches its own demo rows).

Demo data you'll refer to:
- **Clients:** Narmada Industries Ltd (Gujarat), Suryavan Ports & SEZ Ltd (Gujarat), Girnar
  Energy Hydrocarbon (Maharashtra).
- **Vendors:** Vapi Chemical Works, Mundra Fabrication Yard.
- **Inspectors:** Ravi (Ahmedabad), Anil (Pune), Priya.
- **Contacts:** Rakesh Menon (Purchase Head), Sunita Rao (QA Manager).
- **Sites:** Plot 42 GIDC Vapi; Mundra SEZ; MIDC Chakan, Pune.

### 0.4 How to read a test step

Each test is a small table:

| # | Do this (screen · action · value) | Expect |
|---|---|---|

- **Screen** is given as a **menu path** *and* a **URL** you can type after the domain
  (e.g. `/document-new`). Either works.
- **Value** is exactly what to type. Where a value is your choice, it's marked *(any)*.
- **Expect** is what you should see — a message, a new record, or a refusal. A refusal that is
  *supposed* to happen is a **PASS**, not a failure.

### 0.5 Your test log

For each test, tick the box and add a note if it didn't match.

`[ ] PASS  [ ] FAIL  — Notes: ____________________`

---

# PART A — The full happy path (drive the whole spine once)

This one walkthrough takes a job of work from a won quote all the way to a paid invoice and a
verified report. Do it first — if it flows end to end, the spine is alive. Use **`coord.amd`**
unless a step says otherwise.

### A1 — Raise a quotation
| # | Do this | Expect |
|---|---|---|
| A1.1 | **Sales → Quotations → New quote** (`/quote-new`). Client = **Narmada Industries Ltd**. Subject *(any, e.g. "Third-party inspection — pipe spools")*. Add one line: description "Stage inspection", qty **2**, unit **man-day**, rate **8000**. GST **18**. Save. | Quote is created in **DRAFT**; the total shows **subtotal 16,000 + GST 2,880 = 18,880**. Note the quote number. |
| A1.2 | On the quote → **Submit for approval**. | Status → **PENDING_APPROVAL**; an approval step appears. |
| A1.3 | Log in as **`bmanager`** → open the same quote → **Approve**. | Status → **APPROVED**. |

**Pass criterion:** the money on screen equals **18,880** and matches when you open the PDF
(`/quote-pdf`). *(Money fidelity — MOD-03 TC-QUOTES-out-001.)*
`[ ] PASS  [ ] FAIL — Notes: __________`

### A2 — Turn it into an order, then a call
| # | Do this | Expect |
|---|---|---|
| A2.1 | On the approved quote → **Register contract / Raise order**. Give a contract number *(any, e.g. TEST-C-001)*. | A contract is registered; the operations order is floated. |
| A2.2 | **Raise the order** → this opens **New call** (`/call-new?quote=…`) pre-filled with Narmada + contract. Vendor = **Vapi Chemical Works**. Executing office = **Ahmedabad**. PO ref = **PO-9001**. Inspection date = **today**. Deliverables: tick **Inspection Report** (and **Release Note** if listed). Save. | A call is created (note the call code) and can be **forwarded** to Ahmedabad. |
| A2.3 | Forward the call to the executing branch. | Status → **FORWARDED**; an assignment email is composed. |

`[ ] PASS  [ ] FAIL — Notes: __________`

### A3 — Allocate the job
| # | Do this | Expect |
|---|---|---|
| A3.1 | Open the call → **Allocate** (`/job-new?call=…`). Inspector = **Ravi**. Scheduled date = **today**. Save. | Job is created (note the job code); the call status → **ALLOCATED**. |
| A3.2 | *(Negative)* Try to allocate with **nobody** selected (clear inspector and sub-contractor) and save. | **Refused:** "Choose who will carry out this job…". No job is created. **(This refusal = PASS.)** |

`[ ] PASS  [ ] FAIL — Notes: __________`

### A4 — Create and fill the report (as the inspector)
| # | Do this | Expect |
|---|---|---|
| A4.1 | Log in as **`insp.ravi`**. **Reporting → Documents → New** (`/document-new`), or open the job and click **New report**. Report type = **Inspection Report**. It should pre-fill Narmada / Vapi / PO-9001 from the job. Save. | Report is created in **DRAFT** with an **IRN** (note it). |
| A4.2 | Open the report → **Fill** the form: complete the required fields; attach at least **one photo** with a **caption**. Save. | Fields and the photo save; the photo shows in the report with its caption. |
| A4.3 | *(Twin guard)* Try to create a **second** Inspection Report for the **same job, same date**. | You are redirected to the first one with a note that it "already covers this" — **no second IRN is burned. (PASS.)** |

`[ ] PASS  [ ] FAIL — Notes: __________`

### A5 — Submit → vetting → approval → issue
> If your installation has the **vetting gate on** (Master Admin → `/vetting-checklist` →
> "Vetting required" ticked), do A5.1–A5.3. If it's off, submitting goes straight to the
> approver — skip to A5.3.

| # | Do this | Expect |
|---|---|---|
| A5.1 | As `insp.ravi`, open the report → **Submit**. | Status → **At vetting (VETTING)**; the report is now **locked from editing** (try to edit — you can't). *(W1 lock — MOD-06.)* |
| A5.2 | Log in as a **vetting authority** (`bmanager`, or anyone who can finalize). Open the report → **Vet — side by side** (`/document-vet-review`): the real report on one side, the checklist on the other. Tick the checklist, then **Vet (cleared)**. | The report **auto-forwards** to the approver: status → **Under review**, approver notified. *(W2 auto-forward — MOD-07.)* |
| A5.3 | As the **approver** (`bmanager`) → open the report → **Approve**. | Status → **APPROVED** ("can now be finalized & issued"). |
| A5.4 | As a **finalizer** (`bmanager` or Master Admin) → **Finalise & issue**. | Status → **ISSUED**; report becomes **immutable** (locked); the client is notified if that setting is on. |

**Pass criterion:** you could not edit after submit, could not reach the approver without
vetting, and the issued report is locked.
`[ ] PASS  [ ] FAIL — Notes: __________`

### A6 — Release Note
| # | Do this | Expect |
|---|---|---|
| A6.1 | On the **issued** report, tick **"Release Note to be issued"** (`document-rn-flag`). | The flag is set. |
| A6.2 | Click **Raise Release Note**. | If there are no open hold points / NCRs and the client hasn't rejected, an **RN is drafted** with its own IRN, carrying the released items. Submit + issue it like a report (RN **skips vetting** — straight to approver). |
| A6.3 | *(Gating check)* Before ticking the flag, hover the **Release Note** button. | It is **shown but not clickable**, and the reason is spelled out ("not marked for a Release Note…"). **(That block = PASS.)** |

`[ ] PASS  [ ] FAIL — Notes: __________`

### A7 — Invoice and payment
| # | Do this | Expect |
|---|---|---|
| A7.1 | Log in as **`account`** (or coordinator). Open the **closed** job → **Raise invoice** (`job-bill`). | A **draft GST invoice** is created with the amount filled from the quote/call; the **tax split** is CGST+SGST (Narmada = Gujarat = same state as Ahmedabad). |
| A7.2 | Issue the invoice, then record a **payment**. | Invoice gets a gap-free number and becomes immutable; the job shows **Paid**. |

**Pass criterion:** invoice total == the quote figure to the paisa; a Gujarat client shows
**CGST+SGST**, a Maharashtra client (Girnar Energy) would show **IGST**.
`[ ] PASS  [ ] FAIL — Notes: __________`

### A8 — Verify the report is genuine (public)
| # | Do this | Expect |
|---|---|---|
| A8.1 | On the issued report, find its **verify code**. Open **`/verify`** (works with no login) and enter the code. | The page says the report is **genuine**, without showing findings or prices. |

`[ ] PASS  [ ] FAIL — Notes: __________`

> **You have now driven the entire commercial + reporting + money spine.** Everything below is
> testing the *guards* one at a time.

---

# PART B — Control tests (prove each gate, one at a time)

Each of these is a deliberate attempt to do something the system should stop. **A refusal is a
PASS.**

### B1 — Blocked client cannot start work
| # | Do this | Expect |
|---|---|---|
| B1.1 | As **`bmanager`** → **Directory → Client holds** (`/client-holds`). Set **Girnar Energy Hydrocarbon** to **BLOCKED** with a reason *(any)*. | The client is marked blocked. |
| B1.2 | As **`coord.amd`** → **New quote** (`/quote-new`) or **New call** → choose **Girnar Energy**. Try to save. | **Refused** — a message names the block; no quote/call is created. **(PASS.)** A client on **HOLD** would only warn. |
| B1.3 | Reset: set Girnar back to **Active**. | Work is allowed again. |

*MOD-15 TC-CLI-fn-001 / MOD-03 gate.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B2 — Pre-order checklist blocks approval
| # | Do this | Expect |
|---|---|---|
| B2.1 | As Master Admin → **`/preorder-checklist`** → turn **on** + **require all**, add 2–3 items. | Saved. |
| B2.2 | As `coord.amd` → a quote in PENDING_APPROVAL whose checklist is **not** complete → try to **Approve** (as `bmanager`). | **Blocked** until every checklist item is ticked. **(PASS.)** |

*MOD-03 TC-QUOTES-gate-002.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B3 — Duplicate report for the same PO is refused
| # | Do this | Expect |
|---|---|---|
| B3.1 | Ensure one Inspection Report for **PO-9001** is **APPROVED/ISSUED** (from Part A). | — |
| B3.2 | As `insp.ravi` → **New report**, same type, same client, **same PO-9001**. Save. | **Refused** — "items on this P.O. are settled…"; you're sent to the existing report. **(PASS.)** |
| B3.3 | Now create a report with a **different PO** (PO-9002) for the same vendor, same day. | **Allowed** — multiple POs for one vendor on one day is fine. **(PASS.)** |

*MOD-06 PO-lock (W3).* `[ ] PASS  [ ] FAIL — Notes: __________`

### B4 — Cannot edit after submit; cannot reach approver without vetting
| # | Do this | Expect |
|---|---|---|
| B4.1 | Submit a report (vetting on) → try to open **Edit**. | **Refused** — "finalized/cannot be changed". **(PASS.)** |
| B4.2 | While it sits at **VETTING**, confirm there is **no approve action** yet. | Approve appears only **after** it is vetted. **(PASS.)** |

*MOD-06/07 (W1/W2).* `[ ] PASS  [ ] FAIL — Notes: __________`

### B5 — Release Note blocked by an open hold point / NCR
| # | Do this | Expect |
|---|---|---|
| B5.1 | On the job of an issued report, open **Hold / witness points** (`/hold-points` or the job's Reports & QA tab) → raise a **manual hold point**. | An **OPEN** point exists. |
| B5.2 | Try to raise the **Release Note** for that report. | **Blocked** — the reason lists "1 hold/witness point still open — close them first". **(PASS.)** |
| B5.3 | **Clear** the point → try again. | Now allowed. |

*MOD-08/21 RN blockers.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B6 — Calibration hard-block on issue (non-overridable)
> Requires the inspection/accreditation pack **on** and the equipment feature visible.

| # | Do this | Expect |
|---|---|---|
| B6.1 | **Quality → Equipment** (`/equipment`) → add an instrument with a calibration certificate whose **valid-to is in the past**. | The instrument shows **out of calibration**. |
| B6.2 | On a report, **name that instrument** as used. Try to **Finalise & issue**. | **Blocked** — the report will not issue with an out-of-calibration instrument. **Even a Master Admin cannot override this one.** **(PASS.)** |
| B6.3 | Replace with a valid certificate → issue. | Now allowed. |

*MOD-23 TC-EQ-fn-003.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B7 — Contract cover gate (expiry / quantity)
| # | Do this | Expect |
|---|---|---|
| B7.1 | On a contract, set the **end date in the past** (or exhaust its quantity). | — |
| B7.2 | As `coord.amd` → try to **allocate a new job** against it. | **Blocked** — expired/exhausted; you're told to request an exception. **(PASS.)** An existing job stays editable. |

*MOD-18 contract_gate.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B8 — Mandatory-remark on reject / send-back
| # | Do this | Expect |
|---|---|---|
| B8.1 | As an approver, **Reject** a report with the remark box **empty**. | **Refused** — "a remark is mandatory when rejecting". **(PASS.)** |
| B8.2 | Same for **Send back** with an empty remark. | **Refused.** **(PASS.)** |

*MOD-07 TC-APPR-form-001.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B9 — NCR / CAPA close discipline
| # | Do this | Expect |
|---|---|---|
| B9.1 | **Quality → Nonconformities → New** (`/ncr-new`). Severity **MAJOR**, title *(any)*. Save. | An NCR is raised (OPEN). |
| B9.2 | Try to **Close** it with no disposition / no containment. | **Refused** — it lists what's still needed. **(PASS.)** |
| B9.3 | For a MAJOR NCR, try to close with **no corrective action (CAPA)**. | **Refused** — "a major nonconformity cannot be closed without one". Raise a CAPA, and the NCR still won't close until the **CAPA is closed** (with a **verified-effective** outcome). **(PASS.)** |

*MOD-12/13 close gates.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B10 — Complaint decider must be independent
| # | Do this | Expect |
|---|---|---|
| B10.1 | **Quality → Complaints → New** (`/complaint-new`) against a report Ravi inspected. | A complaint (OPEN). |
| B10.2 | Log in **as the person who inspected/approved that report** and try to **Decide** the complaint. | **Refused** — a decider involved in the work cannot decide (§7.5.4). **(PASS.)** *(Note the module report flags this check as name-based — GAP-CMP-001 — so also try a user whose name is a near-variant to confirm.)* |

*MOD-22 cmp_decide_block.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B11 — Data scope: a coordinator sees only their office
| # | Do this | Expect |
|---|---|---|
| B11.1 | As **`coord.pun`** (Pune) → open **Jobs** (`/jobs`), **Documents** (`/documents`), **Calls** (`/calls`). | You see **only Pune** work — none of the Ahmedabad jobs/reports from Part A. **(PASS.)** |
| B11.2 | Try to open an **Ahmedabad** job by guessing its URL, e.g. `/job?id=<an Ahmedabad job id>`. | You should be **refused / not found**. **(PASS.)** |

*MOD-05/06 scope_clause.* `[ ] PASS  [ ] FAIL — Notes: __________`

### B12 — Privilege-escalation is impossible (the access clamp)
| # | Do this | Expect |
|---|---|---|
| B12.1 | As **`bmanager`** → **Admin → Users** (`/users`) → try to create/edit a user with role **Master Admin** or the global "manage users everywhere" power. | The **Master Admin role / global power is not offered** to you — a branch manager cannot mint a super-admin. **(PASS.)** |

*MOD-02 escalation clamp (verified secure).* `[ ] PASS  [ ] FAIL — Notes: __________`

### B13 — Client portal shows only that client's data
| # | Do this | Expect |
|---|---|---|
| B13.1 | As Master Admin → **`/portal-users`** → invite a contact of **Narmada** (e.g. Rakesh Menon's email). Copy the invite link, set a password (accept). | A client-portal login exists for Narmada. |
| B13.2 | Log into the **client portal** (`/portal/login`) as that Narmada user → open **Reports**, **Calls**, **Invoices**. | You see **only Narmada's** items. |
| B13.3 | *(Isolation)* Try to open another client's report by editing the URL id (e.g. `/portal/report?id=<a Suryavan report id>`). | **Not found / refused — no data leaks.** **(This is the Critical isolation check — PASS only if nothing from another client shows.)** |

*MOD-10 GAP-PORTAL-001 (Critical).* `[ ] PASS  [ ] FAIL — Notes: __________`

### B14 — Vendor portal shows only shared reports + their NCRs
| # | Do this | Expect |
|---|---|---|
| B14.1 | As Master Admin → enable the vendor portal (`vendor_portal_enabled`) and invite a **Vapi Chemical Works** contact. | A vendor login exists. |
| B14.2 | Log into the **vendor portal** (`/vendor/login`). | You see **only reports marked vendor-visible** and the **NCRs raised to Vapi** — nothing internal, nothing about another vendor. **(PASS.)** |
| B14.3 | On an NCR raised to them, submit a **one-word** response ("noted"). | **Refused** — a substantive response is required. **(PASS.)** |

*MOD-11 GAP-VP-001 (Critical).* `[ ] PASS  [ ] FAIL — Notes: __________`

### B15 — Money fidelity across screen, PDF, export
| # | Do this | Expect |
|---|---|---|
| B15.1 | Take the quote from A1 (total **18,880**). Open the **screen total**, the **PDF total** (`/quote-pdf`), and the **CSV export**. | All three are **18,880** to the paisa, GST line **2,880**, amount-in-words matches. **(PASS.)** |
| B15.2 | Raise the invoice and compare its total to the quote/job figure. | They agree to the paisa. **(PASS.)** *(The module reports flag profit-screen consistency — MOD-32 — as the area to watch; if you have salary visibility, also compare the same job's profit on `/call-profit`, `/sbu-pl` and `/mis`.)* |

*MOD-03/09/32 money fidelity.* `[ ] PASS  [ ] FAIL — Notes: __________`

---

# PART C — Per-module quick checks (all 31 modules)

Compact, runnable checks so every module gets touched. Each row: **navigate + do + value →
expect**. Log ✓/✗ next to each.

### Sales
| Module | Do this | Expect |
|---|---|---|
| **01 Masters** | Admin → Masters → add an inspection type *(any)* | It appears in dropdowns on quote/call forms |
| **02 Users & Access** | Admin → Users → create a Coordinator, then Access (`/access`) → change a role's default permissions | New user can log in with only that role's screens; B12 clamp holds |
| **03 Quotations** | Part A1 + B2 + B15 | Money correct; approval + pre-order gates work |
| **17 Leads** | Sales → Leads → New (company *(any)*, pipeline) → move to WON | WON **forces conversion** (creates customer + inquiry); a bare tick is refused |
| **19 Inquiries** | Sales → Inquiries → New from a lead, then quote from it | Inquiry flips to **QUOTED** when the quote is raised |
| **18 Orders/Contracts** | Part A2 + B7 | Unique contract number; expiry/quantity gate blocks new allocation |
| **20 Project Costing** | Sales → Project costings → New; add a role line, submit, approve | Margin computes; a submitted costing is **read-only** |

### Operations
| Module | Do this | Expect |
|---|---|---|
| **04 Calls** | Part A2; also try scheduling **beyond the contract's ordered days** | Over-run is refused |
| **05 Jobs/Scheduling** | Part A3; set engagement **MULTIPLE** with 3 dates | 3 visit rows created; a Sunday date grants a **comp-off** on close |
| **06 IDEMS core** | Part A4 + B3 | IRN unique; twin/PO guards; photo shows with caption |
| **07 Vetting & Approval** | Part A5 + B4 + B8 | Vetting-before-approver; auto-forward; mandatory remarks |
| **08 Release Notes** | Part A6 + B5 | RN only from issued + flagged + blockers clear; RN skips vetting |
| **21 Hold/Witness** | B5 | Open point blocks the Release Note |
| **31 Attendance/Reconcile** | Operations → mark **IN/OUT** with location; then a job's site check-in | Location required for OFFICE/SITE; missing site check-in blocks close (if required) |

### Reporting & Quality
| Module | Do this | Expect |
|---|---|---|
| **12 NCR** | B9 | Close gate; MAJOR needs a closed CAPA |
| **13 CAPA** | B9.3 → open the CAPA, complete actions, record **verification** | Cannot close without a **verified-effective** outcome; ineffective → CLOSED_FAILED + follow-on |
| **22 Complaints** | B10; also close an **upheld** complaint | Independence enforced; upheld needs a CAPA before close |
| **23 Equipment** | B6 | Calibration hard-block (non-overridable) |
| **24 Competence** | Give Ravi a **mandatory cert with a past expiry** → allocate a job to Ravi | Allocation **blocked** unless a manager overrides with a recorded reason |
| **25 Impartiality** | Impartiality (`/impartiality`) → raise an **OPEN threat** on Ravi for Narmada → allocate Ravi to a Narmada job | **Blocked** (non-overridable) until the threat is decided |
| **26 Identity** | Site docs (`/site-docs`) → require a gate-pass for a site; allocate an inspector without it | **Blocked** unless a manager overrides with a reason; ID numbers show **masked** |
| **27 Confidentiality** | Quality → Confidentiality → record a **breach** | A **MAJOR NCR is auto-raised**; the breach can't close until it's contained + party told + NCR closed |
| **28 Audits & Trail** | Internal audits (`/internal-audits`) → plan an audit with auditor = the **area owner** | **Refused** (auditor must be independent). Also open `/audit-log` and run the integrity check → chain verifies |
| **29 Data control** | A8 verify; then Master Admin → Data control (`/data-control`) → run integrity checks | Verify says genuine; integrity run passes (incl. audit chain) |

### Money
| Module | Do this | Expect |
|---|---|---|
| **09 Invoicing** | Part A7 + B15; raise an invoice for **Girnar (Maharashtra)** | Gujarat = CGST+SGST, Maharashtra = **IGST**; number gap-free; issued invoice immutable |
| **30 Vouchers/Expenses** | Inspector → My Voucher (`/vouchers`) → generate the month → submit; coordinator approves | Travel = km×rate server-side; DRAFT→SUBMITTED→APPROVED→PAID; a frozen month blocks edits |
| **32 Profitability** | With salary visibility, open `/call-profit`, `/sbu-pl`, `/mis` for the same job | Profit should agree across screens *(the reports flag this as the area to watch — GAP-PRF)* |
| **33 Overheads** | Money/Admin → Office finance (`/office-finance`) → enter a month's overheads → Cost run (`/cost-run`) → **freeze** | Frozen month blocks edits; SBU P&L reflects the allocation |

### Directory, Platform & Dashboards
| Module | Do this | Expect |
|---|---|---|
| **15 Clients** | B1; open **Customer 360** for Narmada | 360 shows only Narmada's quotes/jobs/reports/invoices |
| **16 Vendors** | Issue a **Vendor Assessment** report against Vapi | Vapi's qualification **status is set from the assessment**, not free-typed; requal date set |
| **10 Client portal** | B13 | Only that client's data; isolation holds (Critical) |
| **11 Vendor portal** | B14 | Only shared reports + their NCRs (Critical) |
| **34 Dashboards** | Log in as **each** role and look at the home page | **Every** dashboard shows the **pending-tasks list** (to vet / approve / issue / release) scoped to that user (W6) |
| **35 Recruitment** | HR → Requisitions → New; Candidates → move a candidate to **ACCEPTED + make inspector** | A new inspector is created and the requisition flips to HIRED |
| **14 Settings** | Admin → Settings → change the **currency symbol** or a **terminology** word (`/terminology`) | Every screen and new PDF follows the new wording/symbol immediately |
| **36 Licensing** | Master Admin → Settings → **switch off a module** (e.g. Money) | It **disappears for everyone including you**; its screens refuse. Switch it back on |

Log each: `01 ✓/✗ · 02 ✓/✗ · … · 36 ✓/✗`

---

# PART D — What to do with a failure

1. **Note the exact screen, the values you typed, and what you saw** (vs the Expect column).
2. Find the matching module report (e.g. a Release-Note problem → **MOD-08**) and its **§Q
   Defects & gaps** — many of the "what to watch" items are already listed there with a
   GAP-id.
3. If it's one of the **Critical/Major** items in the **Gap, Risk & Readiness Assessment**
   (Prompt 5), it's already on the close-out list; record your result against that GAP-id.
4. A **refusal that was supposed to happen is a PASS** — don't log it as a failure.

---

## How this guide maps back to the reports

- **Part A** = the golden thread in **Prompt 4 (End-to-End)**, made click-by-click.
- **Part B** = the control gates, one per major DEF/GAP, drawn from the module reports.
- **Part C** = one runnable check per module, tied to that module's report.
- The **TC-IDs** in each module report (§C/§D/§P) are the formal catalogue; the steps here are
  how you *execute* them by hand.

*Tester: __________  ·  Date: __________  ·  Build/URL: __________  ·  Overall result:
___ passed / ___ failed / ___ blocked.*
