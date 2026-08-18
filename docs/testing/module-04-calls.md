# Inspection Ops — Calls (Work Orders) — Test & Documentation Report

> **Prompt 3 · Module MOD-CALLS.** Read from `lib/ops.php` (`ops_calls`,
> `call_schedule_limit_error`, `call_ordered_days`, `call_contract_end`,
> `call_carry_forward_to_jobs`, `send_forward_email`, `call_lead_times`, blocked-client
> gate), `lib/tosrm.php` (clarifications, SLA), views `calls.php`, `call_form.php`,
> `call_detail.php`.

| | |
|---|---|
| **Module** | Calls / Work Orders (MOD-CALLS) · Area Operations |
| **Personas** | P-COORD (register/allocate), P-BM/P-SBU (oversight), P-MASTER, P-INSP (negative) |
| **Risk weight** | High — the entry point of every inspection; feeds jobs, credit and billing |
| **Verdict** | Complete-with-defects (confirm scheduling-limit + carry-forward at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---| 
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

A **Call** is the client's request for inspection — the work order that starts the
operational spine. It carries client, vendor/site, **PO**, **ITP/QAP**, dates
(multi-date + weekly pattern), **reporting frequency**, **deliverables** (which report
types are owed), executing office, and **inter-office credit**. It is **forwarded** to
the executing branch, then **allocated** as one or more Jobs.

Screens: `/calls` (register with lead-time & delay columns), `/call-new`, `/call?id=`
(detail: lead-time calcs, forward, clarifications, quote context, contract override).
Tables: `calls`, `call_clarifications`, `call_nudges`, `call_status_events`,
`recurring_services`.

---

## B. Screen-by-screen catalogue

**`/call-new` / `/call-edit`** — client (**blocked-client gate**), vendor/site,
executing office, managing (contracting) office, SBU, product category, **inspection
type**, PO ref, **ITP/QAP** ref, **inspection dates** (multi-date; weekly pattern
`start..end × weekdays`), **reporting frequency** (+ custom days), **deliverables**
(multiselect of report types), **expected credit** (when contracting ≠ executing),
credit type, billable value/basis, notes, folder link. **`/call?id=`** — lead-time and
delay calcs, "Open in Outlook" forward, clarification raise/respond/status, nudge.
**`/calls`** — register: code, client, dates, lead time, delay, status.

States: `OPEN` (created, not forwarded) → `FORWARDED` (sent to branch) → allocated →
… ; per-day clarification and SLA sub-states (TOSRM).

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-CALLS-form-001 | **Blocked client** refused for non-manager; **HOLD** warns *(I added this — TC-CALLS-gate-001)*. |
| TC-CALLS-form-002 | Master gaps: **before first forward**, a client/site missing tax/contact details is refused with the exact gap named (`partner_missing_text`). |
| TC-CALLS-form-003 | Dates: multi-date CSV + weekly-pattern expansion (`call_expand_pattern`, cap 60) produce the right set; past dates flagged. |
| TC-CALLS-form-004 | Inter-office credit: when contracting ≠ executing office, **expected_credit > 0 is required** (`crossOffice` gate). |
| TC-CALLS-form-005 | Deliverables + reporting frequency stored and carried to the job. |

---

## D. Functions & logic

- **`call_schedule_limit_error`:** blocks scheduling **beyond ordered days** and
  **beyond the contract end** (`call_ordered_days`, `call_contract_end`). TC-CALLS-lim-001
  (beyond ordered days → refused), TC-CALLS-lim-002 (after contract end → refused).
- **`call_lead_times`:** received→required, delay days — verify the arithmetic and that
  weekends/holidays are handled per the intended rule.
- **`call_carry_forward_to_jobs`:** editing a forwarded call carries changed fields
  (dates/days/reporting/formats/contract) to open jobs, but **leaves job-overridden
  fields** as they are. TC-CALLS-cf-001 (change flows), TC-CALLS-cf-002 (a job-level
  override is not clobbered).
- **Numbering:** `ops_next_code('calls','call_code','CALL')` — unique call codes.

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| (new) → OPEN | create | master gaps only gate the **forward**, not the save |
| OPEN → FORWARDED | forward | master details complete; `send_forward_email` |
| FORWARDED → allocated | job allocation | scheduling limits (§D) |
| any → clarification raised/responded/closed | TOSRM | reason required |

**TC-CALLS-life-001:** a call already forwarded stays editable (a typo must be
correctable) but re-forward is controlled; **TC-CALLS-life-002:** clarification requires
a target and text.

---

## F. Roles, permissions & data scope

Register/edit/allocate: coordinator-level+ (`ops.call.create`, `ops.job.allocate`).
Delete: `ops.call.delete` (branch app manager). A coordinator sees only their office's
calls (scope). TC-CALLS-perm-001 (P-INSP crafted create → refused), TC-CALLS-scope-001
(no cross-office calls in list/export).

---

## G. Settings

Reporting frequency master + custom days; SLA/TAT targets (`tat_threshold_days`,
`dsr_target_days`); Office365/SMTP forward mail. TC-CALLS-set-001: SLA breach flags in
the register; TC-CALLS-set-002: forward email uses the configured mailbox.

---

## H. Cross-module integration

**Client/Vendor** (masters, blocked-client), **Quotation/Order** (call from quote
context; contract override), **Jobs** (allocation; carry-forward), **Credit/Reconcile**
(inter-office credit), **IDEMS** (deliverables → which reports the job owes), **Invoicing**
(billable value/basis). Idempotency: double-submit must not create two calls (unique
code) — TC-CALLS-int-001.

---

## I. Data integrity & audit

`call_status_events` records transitions; edits logged. Carry-forward must not create
orphaned/duplicate job rows. Credit figures must reconcile with `credit_recon`. Confirm
a forwarded call's deliverables always match the job's owed reports.

---

## J. Reports & outputs

The forward **email** (assignment) with all known details; `call_dates_csv` export; the
call feeds the job brief and the report header (client/vendor/PO/ITP prefill). No PDF of
its own. TC-CALLS-out-001: the forward email lists the correct dates/deliverables/office.

---

## K. Negative, edge & resilience

Call with no dates; weekly pattern exceeding the 60-day cap (truncated + noted); a call
whose contract is already exhausted/expired (scheduling refused); a client on hold;
editing a call that already has closed jobs.

---

## L. TPIA operational suitability

Strong: models the real work-order → deputation flow with ITP/QAP, reporting frequency,
deliverables, multi-office credit, and contract/ordered-day limits that stop
over-servicing. Clarification loop supports the vendor/client back-and-forth.

## M. Management usefulness

Register shows lead time and delay (TAT); pending-scheduling; SLA breaches; per-call
profit (`call-profit`). Confirm delay/TAT match the dates.

## N. UI/UX

Multi-date + pattern entry; "Open in Outlook"; flow-through prefill from quote; inline
"+ Add new" client. Terminology (call/work-order) via `T*()`.

## O. Security

Authorisation on forward/allocate/delete (not just UI); CSRF; a scoped coordinator
cannot act on another office's call via crafted id; the blocked-client gate holds on a
crafted POST.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §L |
| 12 Integration | Y | §H |
| 13 Data integrity | Partial | carry-forward + credit reconcile |
| 14 Audit | Y | status events |
| 15 Outputs | Y | forward email |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | Partial | dates CSV |
| 22 Notifications | Y | forward/assignment |
| 23 Offline | N-A | coordinator desktop |
| 24 AI | N-A | — |
| 25 Licensing | N-A | — |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | lead time, contract end |
| 28 Performance | Partial | register at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-CALLS-001 | (verify) | Confirm carry-forward never clobbers a **job-level override** and never duplicates job rows. |
| GAP-CALLS-002 | (verify) | Confirm the scheduling-limit gate (`call_schedule_limit_error`) is enforced on the **allocation POST**, not only on the form (crafted request must be refused). |
| GAP-CALLS-003 | — | Confirm inter-office **credit** figures reconcile end-to-end with `credit_recon`. |

---

## R. Traceability

RTM slice: `/calls`, `/call-*` × dims 1–29 → TC-CALLS-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — scheduling-limit enforcement and carry-forward
integrity are the exit conditions.
