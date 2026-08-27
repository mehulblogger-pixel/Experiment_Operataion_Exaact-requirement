# End-to-End Edge-Case Walkthrough — Onboarding → Final Invoicing

**What this is.** A tester's walk through the *whole* lifecycle of one job of work, module by module, in the
order data actually flows. At each stage: the happy path, then the edge cases — with the **input**, the
**expected result**, and **where it is reflected** (which screen, field, badge, PDF, audit row or dashboard).
This complements the per-module deep catalogue in `docs/edge-cases/` (module 01–51); here the emphasis is the
*handoffs* — how a value set in one module must show up correctly in the next.

**How to read a row.** `Case` · `Do this (input)` · `Expected` · `Reflected in` · `Sev`.
Severity: 🔴 regulatory/financial integrity · 🟠 security/scope · 🟡 workflow correctness · ⚪ UX.

**The spine.** `Onboarding → Masters (client/vendor) → Lead → Inquiry → Quotation → Contract → Call → Job →
Site execution → Report (IDEMS) → Vetting/Approval/Issue → Invoice → Payment → Financial truth`, with the
cross-cutting surfaces (tasks, action centre, command centre, quality case, attendance review, entity-360)
threaded through.

> Ground truth: statuses/transitions from `docs/03-object-lifecycles.md`; roles/scope from `docs/01-roles.md`
> + `docs/02-permission-matrix.md`. Canonical engines from `docs/phase-2/02-canonical-application-model.md`.

---

## Stage 0 — Onboarding & access

| # | Case | Do this (input) | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 0.1 | Create a user | Admin adds a user, role COORDINATOR, home office = Ahmedabad | User can sign in; role defaults apply | Users list; login | ⚪ |
| 0.2 | Per-user override replaces role | Give a user a custom permission list | The custom list **replaces** role defaults entirely (footgun) | Users → permissions; `can()` checks everywhere | 🟠 |
| 0.3 | Office/SBU scope on a branch user | Set scope to one branch | User sees only that branch's lists **and** single records (§51) | Every register list; `/job?id=` out-of-branch → denied | 🟠 |
| 0.4 | Master bypass | Sign in as `is_superuser=1` | Sees everything, all offices/SBUs | All screens | 🟠 |
| 0.5 | 2FA + lockout | Wrong password ×N | Account locks per policy; 2FA prompt if enabled | Sign-in; `login_attempts` | 🟠 |
| 0.6 | Lock-out footgun | Give a user an override lacking any admin/settings perm | User cannot reach admin — recoverable only by a master | Admin area hidden (Module 02) | 🟠 |
| 0.7 | Accreditation pack off | Turn `accredited_pack_on()` off | ISO registers (NCR/CAPA/audits/equipment/competence/impartiality/data-control) hidden even for a master | Nav; area homes | 🟡 |

**Handoff:** the user's **office/SBU scope** now silently filters everything downstream — a quotation raised by a
branch user is that branch's, and a manager in another branch won't see it. Verify scope leaks nowhere (lists,
single-record fetch §51, global search §22).

---

## Stage 1 — Masters: client / vendor onboarding

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 1.1 | New client | Add business partner, `is_client=1`, GSTIN, state | Client appears in the register; state drives later GST (CGST/SGST vs IGST) | `/clients`, customer-360 header | 🟡 |
| 1.2 | Duplicate partner | Add a partner whose name/GSTIN matches an existing one | Dedupe surfaces the possible duplicate (Module 15/16) | Add screen warning; `dedupe.php` | 🟡 |
| 1.3 | Same org as client **and** vendor | Set both flags on one partner | One record, both flags; appears in both registers | `/clients` + `/vendors` | ⚪ |
| 1.4 | Contact who is also a candidate/user | Add a contact whose mobile matches a candidate | §23/24 links them — "also appears as" | customer-360/vendor-360 contacts panel (party) | 🟡 |
| 1.5 | Group (parent) company | Set a parent on a client | Earlier quotations across the group show together | customer-360 "Group"; timeline | ⚪ |
| 1.6 | Identity document (DPDP) | Attach an ID number to a person | Stored **encrypted** (`enc:v1:`) when `APP_ENCRYPTION_KEY` set; masked on screen (§53) | `person_documents`; reveal/download gated | 🟠 |
| 1.7 | Site with geofence | Set site lat/lon + radius on a client address | Later SITE attendance/check-in is distance-checked against it | geofence target for the job (§35) | 🟡 |

**Handoff:** the client's **state** (→ GST basis), **GSTIN** and **site geofence** are captured now and must
surface unchanged at invoicing and site execution respectively.

---

## Stage 2 — Lead

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 2.1 | New lead | Create a lead, set source/stage | Lead in pipeline at its stage | `/leads`; Customer-360 timeline | ⚪ |
| 2.2 | Bulk stage move | Select N leads → change stage | **Preview first** (§48): will-apply / will-skip(reason), from the same rule the executor uses | Bulk preview panel; audit | 🟡 |
| 2.3 | Due/overdue lead | Set a follow-up date in the past | Counts as "needs attention" | `attention_summary` band; Command Centre (§20) | 🟡 |
| 2.4 | Convert lead → inquiry | Convert | Inquiry created, linked back; lead marked converted | `/inquiries`; timeline shows the hop | 🟡 |

## Stage 3 — Inquiry

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 3.1 | New inquiry | Capture requirement, client, SBU | Inquiry recorded | `/inquiries` | ⚪ |
| 3.2 | Validation error keeps input | Submit with a missing required field | Error shown; typed values preserved (new vs edit kept straight) | Inquiry form | ⚪ |
| 3.3 | Scope leak check | Branch user searches inquiries | Only in-scope inquiries returned (§22 fix) | Global search; `/inquiries` | 🟠 |
| 3.4 | Inquiry → quotation | Raise a quote from the inquiry | Quote pre-filled from inquiry; linked | `/quotes`; inquiry shows its quote | 🟡 |

---

## Stage 4 — Quotation

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 4.1 | Draft quote | Create quote, add lines, GST% | `total_amount = subtotal + gst`; status DRAFT | Quote screen; `quotations.total_amount` | 🔴 |
| 4.2 | Revise (versioning) | Edit an issued quote | New revision (`rev`, `is_current`), old kept | Quote history; `parent_id`/`is_current` | 🟡 |
| 4.3 | Approval chain | Submit for approval | Sits with the named/role approver; appears in their queue | `ops_pending_tasks` "quotes to approve"; My Work | 🟡 |
| 4.4 | Approver = submitter | Same person approves own quote | Blocked where segregation applies | Approval action | 🟠 |
| 4.5 | Accept | Client/owner accepts | `status=ACCEPTED`, `accepted_date` set | Quote; **§27 money stream = "committed"** on customer-360 | 🔴 |
| 4.6 | Lost / rejected | Mark lost with reason | `status`, `lost_reason` recorded; no contract | Quote; pipeline analytics | 🟡 |
| 4.7 | Accepted but no contract yet | Accept, don't register | Surfaces as "contracts to register" for Finance | `ops_pending_tasks` (finance); handoff wall (C1) | 🟡 |

**Handoff:** an **ACCEPTED** quote is a *commitment*, not cash — it must appear as **committed** (not billed) in
the §27 rollup and Command Centre money band, and must nag Finance until a contract number is registered.

---

## Stage 5 — Contract / order

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 5.1 | Register contract | Finance registers a contract number from the accepted quote | `partner_contracts` row; `open_status=PENDING` | `/contract-openings`; quote shows contract_number | 🔴 |
| 5.2 | Endorse → approve opening | Manager endorses, branch manager approves | PENDING → OPEN; both timestamps set | Contract-openings queue; `mgr_endorsed_at`/`bm_approved_at` | 🟡 |
| 5.3 | Direct-win (no quote) | Finance registers a contract with no prior quote | Allowed via the direct path | Contract register | 🟡 |
| 5.4 | Contract value & validity | Set value + validity dates | `contract_classify()` verdict drives the gate and 360 | Contract-360; the gate on call/job | 🔴 |
| 5.5 | Expiry / over-quantity | Contract past validity or quantity exhausted | Gate warns/blocks raising new work; single verdict everywhere | `contract_state()` badge; call-new gate | 🟠 |
| 5.6 | Add-door warn-and-allow | Raise work against a not-yet-open contract | Warn but allow (documented) with the warning recorded | Call/job add screen (#1) | 🟡 |
| 5.7 | Idle contract auto-close | Contract sits idle past threshold | Heads-up before auto-close (#2) | Contract-360; notification | 🟡 |

**Handoff:** the **contract number** becomes the spine key. Calls, jobs, quotes and invoices all match on it
(§25 engagement view rolls the whole thing up). The **contract value** is the ceiling §33 invoice-readiness
checks against.

---

## Stage 6 — Call (work order)

A call carries **two** statuses — legacy `status` and canonical `op_status`. They must not silently disagree (§46).

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 6.1 | Raise a call | From a contract, no executing office | legacy `status=OPEN`; `op_status` derived (RECEIVED) | `/calls`; Job-360 header | 🟡 |
| 6.2 | Forward to executing office | Set executing office | `OPEN → FORWARDED` | Call; op_status advances | 🟡 |
| 6.3 | op_status transition rules (R6) | Try to skip a rank (e.g. RECEIVED→SCHEDULED) | Only a legal next step allowed (`tosrm_allowed_next`) | Call status picker | 🟡 |
| 6.4 | Inspector tries to change op_status | Inspector uses the picker | Refused (`tosrm_can_edit` excludes inspectors) | Call screen | 🟠 |
| 6.5 | Terminal state | Reach CLOSED/CANCELLED/REJECTED | No exit transition offered | Picker; op_status | 🟡 |
| 6.6 | **Two statuses disagree** | Force `op_status=CLOSED` while legacy `status=OPEN` | Flagged as a §7.11 integrity check (`call_status_agree`, §46) | `/system-status`, `/data-control` | 🟡 |
| 6.7 | PO linkage | Attach a PO to the call | `po_id` set; later §33 invoice-readiness treats PO as present | Call; invoice-readiness panel | 🟡 |
| 6.8 | Optimistic lock (R9) | Two users edit the same call | Second save refused (stale) — no silent overwrite | Call edit | 🟡 |

**Handoff:** the call's **billable rate/qty/basis** (§13 — worked out, not typed) and **PO** carry into the job
and, eventually, the invoice amount. Its **op_status** must agree with the job it spawns (§46).

---

## Stage 7 — Job (allocation)

Real job state = `closed_flag` + `report_approval` + `invoice_raised` (the rich `stage` is vestigial).

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 7.1 | Allocate job to inspector | Coordinator allocates | Job Open; call `FORWARDED → ALLOCATED`; `boss_id`/exec office set | `/job`; Job-360 status header (Module 05) | 🟡 |
| 7.2 | **Competence gate** | Allocate an inspector missing a mandatory certificate | Server-side verdict blocks/flags (`competence_block`, §24) | Allocation screen; readiness | 🟠 |
| 7.3 | **Impartiality gate** | Allocate an inspector with a declared conflict on this client | `imp_block` — flagged/blocked (§25) | Allocation; impartiality verdict | 🟠 |
| 7.4 | SR_INSPECTOR field UI (R7) | Allocate to a senior inspector | Gets the inspector field UI | Job screen | ⚪ |
| 7.5 | Job-close gate (R2) | Close without `ops.job.close` | Refused; also business blocks (deadline lock, bills, site check-in, open visit-days, final docs) | job-close action | 🟠 |
| 7.6 | Advance received | Mark advance received | Scheduling can proceed | Job; `adv_received` | 🟡 |
| 7.7 | Cross-office job | Executing office ≠ contracting office | Inter-office credit tracked; settlement matrix balances (§32) | Job money; `/system-status` settlement | 🔴 |
| 7.8 | §30 cost freeze | Close the job | Rate basis **frozen** (`cost_basis_at`); profit reproducible | job_profit; profitability (never drifts) | 🔴 |
| 7.9 | Add a manual task to the job | Create a §26 task linked to the job | Shows on the job (entity-360), on My Work, in the action centre | `/tasks`; entity-360 `JOB`; §19 band | ⚪ |

**Handoff:** a **frozen** job (§30) feeds a stable profit figure everywhere thereafter; the **competence /
impartiality** verdicts must also gate **issue** later (§10), not just allocation.

---

## Stage 8 — Site execution

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 8.1 | Self-mark attendance OFFICE | Inspector marks OFFICE | Recorded; counts immediately (advisory, §35) | `attendance`; availability board | 🟡 |
| 8.2 | Self-mark SITE with GPS | Inspector marks SITE on-site | Recorded with in_lat/lon | attendance; timesheet | 🟡 |
| 8.3 | **SITE marked off-site** | Mark SITE with GPS >radius from the job geofence | Anomaly flagged for review (§35); still counts (advisory) | `/attendance-review`; `attention_summary` | 🟡 |
| 8.4 | Never checked out | Check in on a past day, no check-out | Flagged; coordinator can **send back** to re-mark | `/attendance-review`; inspector's "returned" list | 🟡 |
| 8.5 | Back-dated mark | Mark >2 days after the date | Flagged for review | attendance-review | 🟡 |
| 8.6 | Re-mark clears flag | Inspector fixes the returned entry | Flag resets; leaves the queue (§35) | attendance-review count drops | 🟡 |
| 8.7 | Geofenced check-in | Check in at the site | Distance validated (`geo_distance_m`) when geofence on | check-in photo/record; trust chain | 🟡 |
| 8.8 | Hold / witness point | Reach a hold point | First-class intervention surfaced; closing warns it isn't cleared (Module 21) | Job; hold/witness panel | 🟡 |
| 8.9 | Evidence photo (EXIF) | Upload a site photo | EXIF time/GPS + SHA1 hash captured; tamper-evident chain | `report_files`; trust chain | 🟡 |
| 8.10 | **Reused evidence** | Upload a photo already used on another report | Detected (shared hash, §68) | Evidence reuse signal (Module 44) | 🟡 |

**Handoff:** attendance is **advisory** — it never blocks the timesheet/cost run; the review just corrects
errors after. Evidence + hold/witness carry into the report.

---

## Stage 9 — Report (IDEMS)

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 9.1 | Create applicable report(s) | From the job | Only **applicable** report types offered (`idems_job_applicability`) | document-new; applicability surfacing (Module 06) | 🟡 |
| 9.2 | **Applicability override** | Force a non-applicable type on/off | Override stores a **reason** + `APPLICABILITY_OVERRIDE` audit + `not_allocated` flag (§6) | Report; audit log; report_docs | 🟠 |
| 9.3 | Fill fields; conditional fields | Fill a field that triggers a conditional one | Conditional field appears per rule; hidden fields never on the client copy | Report fill; persona preview (§8) confirms who-sees-what | 🟡 |
| 9.4 | Uncaptured value | Leave an optional field empty | Renders as N/A, not a failure | Report PDF | ⚪ |
| 9.5 | Submit for vetting | Submit | `status → SUBMITTED/VETTING` | `/documents?status=VETTING`; My Work "to vet" | 🟡 |
| 9.6 | Vendor report (VASR/VAR) | Assess/audit a vendor | Scored; feeds vendor scorecard/status | vendor-360; scorecard (Module 16/24) | 🟡 |

## Stage 10 — Vetting → Review → Approval → Issue

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 10.1 | Vet (if enabled) | Vetter reviews | `vet_status` advances; hard-block on issue until VETTED **when the lab enabled vetting** (§10) | issuance readiness; My Work | 🔴 |
| 10.2 | **Structured return** | Vetter/approver returns the report | Section/field + **deadline** captured; inspector told exactly what to fix (§9) | Return detail; `idems_latest_return()` | 🟡 |
| 10.3 | Approval chain | Approver approves | `report_approval` advances (Module 07) | Report; My Work "to approve" | 🟡 |
| 10.4 | **Issuance readiness gate** | Try to issue with gaps | Lists blockers: vetting, completeness, competence (§24), impartiality (§25), open NCR, client acceptance for RN/IRN (§10) — advisory unless `issue_gate_strict` | Issue readiness panel (Module 08) | 🔴 |
| 10.5 | Issue | Issue a clean report | Sealed & immutable; IRN assigned; `status=ISSUED`, `issue_date` | Report; audit; `content_seal` | 🔴 |
| 10.6 | **Seal fails** | Seal write fails | Reads `SEAL_FAILED`, never a false "sealed"; self-heal cron re-seals; compliance flag (§11) | compliance check; `/system-status` | 🔴 |
| 10.7 | **Four roles on the PDF** | Open the issued PDF | Prepared / Vetted / Approved / Issued all printed, each only where the role applied (§4) | Report PDF second sign-off block | 🔴 |
| 10.8 | Release note acceptance | Issue an RN/IRN with acceptance required | Blocked until client acceptance recorded (§10/§33) | issue gate; invoice readiness | 🔴 |
| 10.9 | Immutable revision | "Edit" an issued report | Controlled revision only — original preserved | Report history; audit | 🔴 |
| 10.10 | Quality case linkage | A finding raises an NCR → CAPA | The whole chain shown as one Quality Case (§39) | NCR/CAPA detail "full story"; entity-360 | 🟡 |

**Handoff:** an **ISSUED** report is the precondition §33 invoice-readiness checks. An **open NCR** or an
**unaccepted release** should stop both issue (§10) and billing (§33).

---

## Stage 11 — Invoicing

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 11.1 | **Invoice readiness** | Open a closed job's Money tab | READY/NOT-READY verdict: reports issued, release accepted, PO on file, within contract value (§33) — advisory unless `invoice_gate_strict` | Job Money tab; readiness panel | 🔴 |
| 11.2 | Bill before report issued | Try to bill with a report still in DRAFT | Blocker "reports not issued" (hard-blocks only under strict gate) | invoice readiness | 🔴 |
| 11.3 | Raise GST invoice | One click from the closed job | Invoice created; amount from quote/call (no re-key); GST decided from state (CGST/SGST vs IGST), frozen | `/invoice`; `invoices` row | 🔴 |
| 11.4 | Idempotent bill | Press "Raise invoice" twice | Opens the SAME invoice, not a second empty one | job-bill | 🟡 |
| 11.5 | Duplicate invoice number | Two invoices collide on number | DB UNIQUE refuses; `books_issue` re-allocates; legacy dupes surfaced (Module 09) | invoicing; integrity | 🔴 |
| 11.6 | Over contract value | Bill beyond the contract value | **Warning** on readiness (not a hard block) (§33) | readiness panel | 🟠 |
| 11.7 | Part-payment (receipt) | Record a receipt < invoice | TDS on receipt; running balance; ageing bucket | `/ledger`; receipts; §27 "received" | 🔴 |
| 11.8 | Credit note | Issue a credit note vs an invoice | Value returned; net billed reduced | ledger; §27 "credited" | 🔴 |
| 11.9 | Cancel invoice | Cancel an issued invoice | `status=CANCELLED`; §27 shows a reversal; net billed drops | invoice; §27 stream | 🔴 |
| 11.10 | Cross-office billing scope | Branch user opens another branch's invoice by id | Denied (§51) | `/invoice?id=` | 🟠 |
| 11.11 | Money-mirror to job | Issue a Books invoice | Mirrors figures onto legacy `jobs.invoice_*` so old screens keep working | job; profitability | 🟡 |
| 11.12 | **Legacy vs ledger drift** | Legacy job invoice figure ≠ books ledger | Flagged by revenue reconciliation (§29) | `/system-status`, `/data-control` | 🟡 |

## Stage 12 — Payment → close-out

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 12.1 | Payment received | Mark paid | AR clears; overdue reminder stops | ledger; AR ageing | 🔴 |
| 12.2 | Overdue AR | Invoice past due, unpaid | Overdue reminder cron; "needs attention" | `attention_summary`; AR bucket | 🟡 |
| 12.3 | Tally export | Export to Tally | Only what hasn't been handed over is exported | `tally.php`; export | 🟡 |

---

## Stage 13 — Financial truth (the numbers everyone reads)

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 13.1 | **Unified profit (§28 ON)** | Look at the same job's profit on MIS, SBU-P&L, boss view | **Identical** figure everywhere = canonical `job_profit` (overhead/voucher/contingency/recovered included) | MIS, SBU-P&L, profitability | 🔴 |
| 13.2 | Revert switch | Set `finance_truth_unified='0'` | Dashboards return to the legacy partial formula (documented) | MIS/SBU-P&L | 🟠 |
| 13.3 | Historical reproducibility | Change today's salary/overhead % after a job closed | The closed job's profit **does not move** (§30 freeze) | profitability; management reports | 🔴 |
| 13.4 | Two costing bases | Open SBU-P&L | Period-costing vs job-costing shown side-by-side with the gap (§28 Option 3) | SBU-P&L reconciliation panel | 🟡 |
| 13.5 | Profit consistency check | Open `/system-status` with §28 ON | "Profit-figure consistency = Unified" (green) | system-status | 🟡 |

---

## Stage 14 — Cross-cutting surfaces (threaded through the whole flow)

| # | Case | Do this | Expected | Reflected in | Sev |
|---|---|---|---|---|---|
| 14.1 | Action centre priority | Have an overdue task + a routine approval | Overdue task out-ranks the approval in "Next actions" (§19) | My Work; area-home glance (§34) | ⚪ |
| 14.2 | Command centre | Manager opens `/command-centre` | Attention + money (§27) + platform health, as **separate** bands (§20/§21) | Command Centre | 🟡 |
| 14.3 | Engagement rollup | Open a contract's engagement | Whole spine (quotes→calls→jobs→reports→invoices) + totals in one view (§25) | engagement view | 🟡 |
| 14.4 | Entity-360 | Open `/entity-360?kind=NCR&id=` | Uniform panels: tasks + history + quality case (§49) | entity-360 | 🟡 |
| 14.5 | Integration queue | An outbound event fails delivery | Retries with backoff; gives up at the cap → "stuck"; surfaces in integration health (§50) | `/integrations`; system-status | 🟡 |
| 14.6 | Audit chain integrity | Any sealed action | Appended to the tamper-evident chain; verify catches tampering; legit trim anchored (§54) | `idems_audit`; system-status | 🔴 |
| 14.7 | Settings governance | Admin flips a behavioural setting | Setting documented (impact, forward/live) in the §47 registry; change audited (SETTING_CHANGED) | settings; audit | 🟠 |

---

## Golden end-to-end assertions (the flow, proven at the seams)

These are the cross-module invariants a full pass must hold:

1. **Scope holds end-to-end** — a branch user who raised the quote is the only branch that sees the quote →
   call → job → invoice, on lists **and** by direct id (§51/§22). 🟠
2. **The money adds up one way** — quote ACCEPTED = committed; invoice ISSUED = billed; receipt = received;
   credit = credited; and MIS/SBU-P&L/boss profit all equal `job_profit` (§27/§28). 🔴
3. **Nothing issues or bills prematurely** — a report can't issue with an open blocker (§10) and a job can't
   bill before its report is issued/accepted (§33). 🔴
4. **History is stable** — a closed job's profit and an issued report both reproduce identically later
   (§30 freeze, §11 seal, §54 audit). 🔴
5. **Two statuses never silently disagree** — a call's legacy vs canonical status mismatch is flagged (§46),
   and the job's `stage` is treated as vestigial, not truth. 🟡
6. **Every hand-off is visible** — Sales→Finance→Operations handoffs surface as pending tasks / attention,
   never fall silently between modules (C1, §19, `attention_summary`). 🟡

---

*This is the E2E spine. For per-field, per-screen exhaustiveness within a single module, see
`docs/edge-cases/01…51`. For the controls referenced by § number, see `docs/phase-2/` and `docs/phase-3/`.*
