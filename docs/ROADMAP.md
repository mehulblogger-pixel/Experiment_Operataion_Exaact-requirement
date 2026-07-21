# Roadmap

The requirements describe a full enterprise suite. This document records the
phased plan so the build stays coherent. **Phase 1 (Operations core) is
implemented in this repository**; later phases are specified but not yet built.

## Phase 1 — Operations / Scheduling core ✅ (this release)

- Roles & offices foundation (`accounts`, `masters`).
- Configurable master data seeded from the Excel (offices, SBUs, activity codes,
  product categories, vendor locations, clients, inspectors, job types, report
  formats, payment terms).
- **Call Register** (`InspectionCall`) with the full field set from requirement 5.
- Auto-generated call reference numbers.
- **Schedule Board** — colour-coded pending-call cards (red = overdue / not
  scheduled), filters (5gg).
- Inspector allocation & reshuffle, tentative flag (6).
- Lead-time & scheduling-delay computation (5s, 5t).
- Reject-with-reason (recorded permanently, 5ee); mark-complete + invoice prompt
  (5g/5h); advance-payment / deliverable-against-payment flags (21, 22).
- Inter-office **NetworkCredit** model (contracting vs executing office, 5cc).
- End-of-day digest + escalation to Branch Manager / SBU Head (`daily_digest`,
  5gg, 5ii).
- Open / pending / closed call lists (14).

## Phase 2 — Operations completeness

- Scheduled-inspector **email notification** with full call details (6d).
- Inspector **reporting portal**: upload deliverables, TAT clock, "no report →
  no timesheet/attendance" lock (Reporting a–i), report-pending dashboard.
- Non-availability / call-rejection / client-cancellation accounting per
  executing office (5ee, 5ff); "no response in 3/5 days" auto-escalation job.
- Subcontractor-arranged calls after rejection (5jj).
- Project-deputation continuous-deputation handling distinct from spot
  inspection (5hh, Reporting i).
- Tentative-allocation cross-office visibility (Complaint-management section 1–3).
- Utilization & profitability views (from the Excel's Utilization / Overhead /
  Profit model).

## Phase 3 — Quotation / Sales workflow (requirements 1–25)

- Inquiry intake (email → record, no separate register).
- Auto quotation-number generation; revisioning with Rev. 01 history (23).
- **Configurable quote template** upload (Word format) with field
  create/edit/delete (5, 6); data-entry-only quote generation (7).
- Approval chain routing (9) → send to customer email on final approval (10).
- Reminder automation at 3 / 6 / 9 days / fortnight / month with configurable
  templates (11).
- Acceptance closes the follow-up chain → Accounts registers client + contract
  number (12) → auto hand-off packet to Operations (13).
- Open / pending / closed quote views (14); monthly reports (15); revenue
  line-item tracking per quotation/contract (16); ARC/open-order line items (17).

## Phase 4 — Inspector Hiring / CV pipeline (requirement 20 + Hiring section)

- Requirement → CV sourcing (HR bank / external agency / internal / client) with
  counts per source.
- CV submission tracking to client: submitted date, shortlisted/rejected,
  interview pending/planned/done, outcome.
- On selection: automated document-request email to candidate (configurable
  template); rejected CVs retained in the bank.
- Multiple engineers / positions per requirement.

## Phase 5 — Reporting, dashboards & hardening

- Manager/SBU-head/director dashboards; branch-wise delay & rejection reports.
- Monthly report exports; revenue-sharing settlement reports.
- Production hardening (HTTPS settings, backups), role-based permissions review,
  audit trail coverage.

## Migrating historical Excel data

`masters/seed_data.json` already carries the master lists extracted from the
workbook. A follow-up `import_master_calls` command can load the ~5,000 rows of
`tblMaster` into `InspectionCall` for history once field-mapping is confirmed
with the business (dates, revenue, subcon costs).
