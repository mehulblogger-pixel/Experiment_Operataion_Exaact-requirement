# Demo / Test Pack — the whole application, populated and traceable

This is the guided tour of the demo dataset. Load it from **Settings → Load demo
data** (Master Admin only), then sign in as any demo user below. Every figure on
every screen is computed from these seeded records — nothing is hard-coded.

- **Users** (password `demo12345`): `director`, `bmanager`, `appmanager`,
  `opmanager`, `asstmgr`, `account`, `insp.ravi`, `insp.anil`, plus `admin`.
- **The demo company**: three offices — **Ahmedabad (AMD)**, **Mumbai (MUM)**,
  **Pune (PUN)**. Clients: **Narmada Industries**, **Suryavan Ports & SEZ**,
  **Girnar Energy Hydrocarbon**, **Kutch Petrochem** (+ 10 edge clients).
  Vendors: **Vapi Chem**, **Mundra Fab** (+ 6 edge vendors). Engineers: Ravi
  Kumar, Anil Sharma, Priya Nair, Mohan Rao (sub-contractor).
- **Coverage**: 200 of 207 tables carry demo rows. The 7 left empty are
  transient runtime queues (login/SSO attempt counters, e-mail/API send
  outboxes, ads sync log, install telemetry, per-user UI prefs) — they fill
  only through live use and are intentionally not seeded.

Roughly half the rows below are **deliberate edge cases** — the expired
certificate, the rejected report, the breached KPI, the exhausted PO. A
register full of tidy compliant rows proves nothing; you cannot tell a working
gate from a missing one. So the demo shows both.

---

## 1. The golden thread — one order, end to end

This is the spine: **Lead → Contact → Deal → Quotation → Accept → Contract →
Work order → Job → Site check-in → Report → Invoice → Money in.** The
traceability walk (**Settings → Build traceability thread**) creates one record
(customer `GT-CLIENT`) and verifies every link was written at every step.

| Stage | Screen | What flows in / out |
|---|---|---|
| Lead | `/leads` → `/lead?id=` | `DEMO-L-2607-01` Kutch Petrochem, stage *Contacted*, with a stage history and two attached files (one flagged to carry into the quote). |
| Deal | `/opportunities` → `/opportunity?id=` | Four deals: one **Won** (linked to Q-2607-001), one **open/quotation-sent** (Q-2607-002), one **Lost on price** (competitor named), one early-stage sprung from the lead. |
| Quotation | `/quotes` → `/quote?id=` | Q-2607-001 accepted, -002 sent (validity running out), -003 lost — each with line items, revisions, approvals, follow-ups, files. |
| Work order | `/calls` → `/call?id=` | Calls carry the quotation link (`quotation_id`), the contract number, contracting vs executing office. |
| Job | `/jobs` → `/job?id=` | Allocation, per-day closure, QAP upload, visits, deputation history. |
| Report | `/reports`, `/document?id=` | Draft → vetted → approved → issued; client acceptance / rejection. |
| Invoice | `/invoicing`, `/invoice?id=` | Invoice lines summing to the total, GST split, a credit note. |
| Money in | `/receivables`, `/receipt?id=` | A receipt allocated against the invoice; inter-office credit reconciliation. |

---

## 2. Sales & CRM

| Register | Screen | What to look for |
|---|---|---|
| Leads | `/leads` | Kutch Petrochem lead, *Contacted*, next action overdue. |
| Lead files | `/lead?id=` | Enquiry PDF + scope worksheet (one marked "carry to quote"). |
| Lead stage history | `/lead?id=` | New → Contacted, with days-in-stage. |
| Opportunities | `/opportunities` | 4 deals across Won / Open / Lost / Qualified. |
| Opportunity ↔ quote | `/opportunity?id=` | Each deal linked to its quotation. |
| Opportunity stage history | `/opportunity?id=` | Full walk of the Won deal to closure. |
| CRM templates | `/crm-templates` | Follow-up e-mail / WhatsApp / covering-letter templates. |
| Quotation approvals | `/quote?id=` | Q-002 **pending** at Branch Manager then Director; Q-001 approved (history). |
| Quotation approval rules | `/quote-approval-rules` | OGC > ₹2.5L → BM; any > ₹5L → Director. |
| Quotation revisions | `/quote?id=` | Rev 0 superseded by Rev 1 (price cut in negotiation). |
| Quotation follow-ups | `/quote?id=` | An **overdue** chase on the sent quote. |
| Quotation files | `/quote?id=` | Signed PO, client spec, our signed copy. |
| Quotation edit requests | `/quote?id=` | A request to reopen an accepted quote to fix a vessel count. |
| Stage gates / requests | `/stage-gates`, `/approvals` | A Won-deal move **blocked** pending BM approval. |

---

## 3. Directory — partners & vendors

| Register | Screen | What to look for |
|---|---|---|
| Partner notes | Customer 360 | Narmada's working preferences; Mundra's suspension note. |
| Partner relationships | Customer 360 | Group link (Narmada ↔ Suryavan); vendor sub-contracting. |
| Partner registrations | Vendor profile / partner meta | GSTIN, PAN, and an **expired** ISO 9001. |
| Partner contracts | Customer 360 → Contracts | A live 200-manday rate contract; a closed campaign. |
| Purchase orders | `/partner-pos` | A release order against the contract. |
| PO line items | `/po-lines` | A line **~85% consumed** — the near-exhausted balance the alert wants. |
| Vendor profiles | Vendor register | Approved (Grade A), **Suspended**, and a never-assessed Prospect. |
| Vendor qualifications | Vendor profile | Qualified vs **Expired** (welder PQR lapsed). |
| Vendor status events | Vendor profile | Prospect → Approved → **Suspended** trail with reasons. |
| Vendor portal users | `/vendor-users` | An active portal user; a pending invite. |
| Vendor access log | Vendor portal audit | Sign-in → view report → download. |
| Vendor travel memory | (voucher entry) | Pre-fills km for an inspector↔vendor trip. |

---

## 4. Money — books & finance

| Register | Screen | What to look for |
|---|---|---|
| Invoice lines | `/invoice?id=` | Two lines summing exactly to the subtotal, GST 18% split. |
| Credit notes | `/invoice?id=`, `/tally` | A partial credit (one vessel de-scoped). |
| Receipt allocations | `/receipt?id=` | A receipt settling the invoice. |
| Credit reconciliation | `/mis` | Inter-office credit — one received, one given. |
| Deputation bills | `/job-bill?job=` | Travel + lodging bills on a job. |
| Billing orders | `/billing` | A paid 25-seat annual order (SaaS billing history). |
| Tally exports | `/tally` | One export batch: invoice + receipt vouchers. |
| Office overheads | `/office-finance` | Rent / electricity / internet for July (one pending approval by note). |
| Cost runs | `/cost-run` | An open July run per office (Ahmedabad + Mumbai). |
| Cost allocations | `/cost-run`, `/mis` | Payroll / overhead / sub-con apportioned across SBUs. |
| Issued licences | `/issue-licence` | One issued customer licence (provider console). |

---

## 5. Reporting & IDEMS

| Register | Screen | What to look for |
|---|---|---|
| Report templates | `/report-templates` | Published default, one **in review**, one superseded. |
| Approver mapping | `/approver-map` | One approver per engineer; one **delegated while on leave**. |
| Approval rules | `/idems-approval-rules` | L1 technical → L2 branch-manager (high value). |
| IRN counters | (numbering engine) | Per-office serial counters feeding the next IRN. |
| Report approvals | `/document?id=` | One report **awaiting approval**; one fully approved. |
| Per-document review | `/document?id=` | MTC/QAP received, WPS with an **observation**, NDT awaited. |
| Technical vetting | `/document?id=` | Returned with an observation, then vetted clear. |
| Client report reviews | `/report-reviews` | One **accepted**, one **rejected** (PO number mismatch, unacknowledged). |
| Release Note links | `/release-notes` | A Release Note linked to the inspection it releases. |
| Job QAPs | `/job?id=` | Approved QAP rev C with hold/witness points. |
| Endorsement files | `/endorsement?id=` | The manufacturer's original mill certificate. |
| Learned suggestions | `/learning` | Phrase suggestions learned from past reports. |
| Hold / witness points | `/hw-points` | Two open interventions (hydro-test hold, DFT witness). |

---

## 6. Quality & accreditation (ISO/IEC 17020 · 17025)

| Register | Screen | What to look for |
|---|---|---|
| Inspector certificates | Inspector edit → Certificates | One **expiring in 20 days**, one **already lapsed** (a sub-contractor). |
| Test methods | `/methods` | Validated CURRENT method, one with an **overdue review**, one draft. |
| Decision rules | `/drules` | A guard-band acceptance (ILAC-G8) and a simple-acceptance draft. |
| Controlled documents | `/cdocs` | Quality Manual; an SOP **past its review date**; a draft awaiting approval. |
| Risk register | `/risks` | Impartiality risk, calibration-lapse risk, a growth opportunity. |
| Samples | `/samples` | One in testing, one returned — each with a custody chain. |
| Sample custody | `/sample?id=` | Received → moved → in-testing / returned, timestamped. |
| Satisfaction surveys | `/satisfaction` | A 5/5, a **2/5 with a follow-up flagged**, one awaiting response. |
| Security incidents | `/incidents` | An **open** phishing case; a closed lost-laptop (CERT-In reported). |
| Site document requirements | `/site-docs` | Gate pass, medical, police verification per client. |
| Equipment & calibration | `/equipment` | (Base seed) in-date, expiring, **expired-but-active**, and a **failed** cal. |

---

## 7. Confidentiality & data protection (DPDP)

| Register | Screen | What to look for |
|---|---|---|
| Confidentiality undertakings | `/confidentiality?t=people` | One in force; a sub-con's **lapsed** undertaking. |
| Client NDAs | `/confidentiality?t=ndas` | Mutual NDA (Narmada); one-way NDA (Suryavan). |
| Confidentiality breaches | `/confidentiality?t=breaches` | A wrong-recipient breach **under investigation**. |
| Consent register | `/consents` | Contract / consent bases; one **withdrawn** consent. |
| Data requests (DSAR) | `/data-requests` | An access request **in progress**; an erasure request **closed**. |
| Disclosure consents | `/disclosure` | Consent to disclose results to an insurer; one awaiting sign-off. |
| Identity document access | `/identity?i=` | View → reveal → share log against a passport. |

---

## 8. Workforce, hiring & scheduling

| Register | Screen | What to look for |
|---|---|---|
| Attendance | `/m/attendance`, self-service | An engineer's month — site / office / WFH / leave / travel, with geo. |
| Public holidays | `/m/holidays` | FY 2026-27 national list + one Gujarat office day. |
| Work norms | `/work-norms` | Weekly days/hours per designation & office + a company default. |
| Back-office staff | `/m/back-office` | A coordinator and an accountant. |
| Availability board | `/availability` | Today's status per engineer (on-job / available / leave / travel). |
| Candidates | `/candidates` | One candidate **mid-pipeline** (interview stage). |
| Candidate events | `/candidate?id=` | Received → submitted → shortlisted → interview. |
| Sub-contractors | `/m/subcons` | Two agencies with compliance notes. |
| Sub-con rate matrix | `/m/subcon-rates` | Per-skill / region / rate-type cards. |
| Contract exceptions | `/contract-overrides` | A PO **quantity-exhausted** override, approved as a one-off. |

---

## 9. Analytics & KPI governance

| Register | Screen | What to look for |
|---|---|---|
| KPI targets | `/analytics-kpis` | Company SLA floor 90%, an Ahmedabad stretch target. |
| KPI snapshots | `/analytics`, `/analytics-snapshot` | SLA compliance **82.5% — BAD (breached)**; closure rate healthy. |
| KPI alerts | `/analytics-alerts` | "SLA below floor" — **fired** on the breach. |
| KPI versions | `/analytics-kpi-edit` | The baseline SLA definition adopted at go-live. |
| Analytics periods | `/analytics-review` | The current month **under review** before sign-off. |
| Disruptions & changes | `/operations`, `/analytics` | Client cancellations, engineer changes, office cancellations, no-shows. |

---

## 10. Cross-cutting

| Register | Screen | What to look for |
|---|---|---|
| CAPA action plans | `/capa?id=` | Three actions — one done, one **overdue**, one future. |
| Service scope | `/service-scope` | Per-client service enablement; one trialled-then-off. |
| Service dependencies | `/service-scope` | Release requires an accepted inspection; NCR/CAPA follows inspection. |
| Custom forms | `/cforms`, `/cform?f=toolbox-talk` | A "Toolbox Talk" register with fields and one filled record. |
| Job visits | `/job?id=` | A planned visit and a closed one with report evidence. |
| Client portal | `/portal`, portal audit | Report-issued / visit-scheduled notifications + an access trail. |
| Ad-campaign ROI | `/ads-roi`, `/adspro` | Spend rows and attributed WhatsApp leads. |

---

## Loading & removing

- **Load**: Settings → *Load demo data*. Safe to re-run after removing.
- **Remove**: Settings → *Remove demo data*. Clears every row above (all carry a
  `demo` marker) in FK-safe order, leaving zero orphans — verified by the
  load → remove → reload cycle.
- **Self-check**: the Settings page shows live coverage from
  `demo_modules_expected()` (83 tracked registers). Add a module tomorrow, add
  its line there, and the screen says "no demo data" in plain sight until it has
  some — so this pack can never quietly rot.
