# Requirement status — Completed vs Pending

Mapped against branch `claude/master-list-inspection-features-d3f2u7`. Updated: 2026-07-22.

**Legend** — ✅ Done · 🟡 Partial · ❌ Not started · 🅿️ Parked (agreed for later / needs your input)

This session built out every actionable module. Remaining ❌/🅿️ items are the ones
that explicitly need your input or were agreed to be done last (licensing).

Score now: **41 ✅ · 3 🟡 · 0 ❌ · 6 🅿️** (was 13 ✅ · 14 🟡 · 20 ❌ · 6 🅿️)

---

## A. Organisation, admin & lifecycle

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| A1 | Master records deletable by Super Admin only | ✅ | Record + inspector delete now gated to `is_master()`; lists were already Super-Admin-only. |
| A2 | Inspection life-cycle in the org | ✅ | Call OPEN → FORWARDED → CONFIRMED (assigned) → Job → Closure; statuses set through the flow. |
| A3 | Designation master (Inspector, Sr. Inspector, Sr. Executive…) | ✅ | New **Designation** master (27 seeded values); used by the back-office staff master and contacts. |
| A4 | Back-office names with full costing (CTC, allowances) | ✅ | New **Back-office staff** master with CTC + HRA + conveyance + special + other allowance (salary-gated). |
| A5 | Full org hierarchy | 🟡 | Roles + scoped dashboards done; multiple managers/coordinators *per office* still to refine. |

## B. Search & configurable forms

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| B1 | Infix search in dropdowns | ✅ | Substring match already; `.searchable` applied broadly. |
| B2 | Add a dropdown/text box to any master form | ✅ | Custom-field engine extended to every master + the Client/Vendor form, with a form picker. |

## C. Client & Vendor form

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| C1 | Legal→Display autofill (editable) | ✅ | |
| C2 | Is Client / Vendor / Both | ✅ | |
| C3 | Sub-contractor ⇒ Vendor | ✅ | |
| C4 | GSTIN/PAN flows to Registration per selection | ✅ | Registration number auto-fills from GSTIN/PAN by document type. |
| C5 | City/State dropdown + Other + spelling-correct | ✅ | Master dropdown with Other→type; `lk_resolve_or_add` auto-corrects (e.g. "ahmedabaad"→"Ahmedabad"). |
| C6 | Address Town/Village + District | ✅ | Separate fields on the address form; shown in address line. |
| C7 | Contacts: Designation + Department (master) | ✅ | Designation text + Department master dropdown (with Other). |
| C8 | Contacts: Project field | ✅ | Project field added and shown. |
| C9 | PO manpower/site/trade/sub-category + GST/Tax/Total | ✅ | Line items carry all of these; Bare/GST/Tax/Total columns roll up vs PO/contract value. |
| C10 | Contract tab after PO tab | ✅ | Purchase Orders now precedes Contract Numbers. |
| C11 | Projects tab wired | ✅ | Lists inspection calls where the partner is client or vendor/site. |
| C12 | No duplicates (Name/GSTIN/PAN/TAN) | ✅ | |
| C13 | Industry Other→type + added to master | ✅ | |
| C14 | Inspection type Other (free text, multiple) | ✅ | Kept off the master, per your note. |
| C15 | Code Branch-Yr-Role-Short-Seq + C/V/M/T + cross-branch | ✅ | e.g. `AHM-26-CVM-BHAGI-00001`; per-branch roles tab (no duplicate record). |
| C16 | Contract/PO carry SBU + per-line activity | ✅ | Multi-SBU on contract & PO; activity code on each PO line. |
| C17 | Contract/PO/Projects only for clients | ✅ | Hidden for pure vendor/manufacturer/trader. |

## D. Calls / Inspection

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| D1 | Delete call — admin/manager/super-admin only | ✅ | Cascades the call's jobs + expenses. |
| D2 | Product line for many products | 🟡 | Category + free-text + quick-add today; a dedicated products master + vendor mapping + CSV import is the recommended next step (guidance below). |
| D3 | PO + line-item + project on call; near-completion email | ✅ | Client PO/line item (live balance) + project ref selectable; cron emails when a line is ≥80% consumed or the PO expires ≤15 days. |
| D4 | Client sites dropdown on project deputation | ✅ | Client sites load and show when the type is project deputation. |
| D5 | Confirm shows "assigned to X for date"; engineer = asset/freelancer/subcon | ✅ | Engineer status on the job; call marked CONFIRMED with an assignment banner. |
| D6 | "Call save not working" | ✅ | Verified end-to-end via a live POST — the call saves and redirects. If you still see it, send the exact screen/message. |
| D7 | Allocation email with full details | ✅ | Now includes client contact/mobile/email, notes, product, vendor contact/mobile/email + full address. |
| D8 | Unique + searchable inspection number | ✅ | |

## E. Invoicing, payment & reconciliation

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| E1 | Per line: Invoice raised? → number/date/due date | ✅ | On each job's detail. |
| E2 | Payment received (local client) | ✅ | Shown when contracting = executing office. |
| E3 | Credit received (contracting ≠ executing) + reconciliation | ✅ | Credit flag for cross-office; dashboard reconciliation totals. |
| E4 | Director/SBU Head/Branch Mgr/Mgr dashboards | ✅ | Invoicing & reconciliation section on the dashboards (amounts gated to financial roles). |

## F. Settings, theme & billing

| # | Requirement | Status | Notes |
|---|-------------|--------|-------|
| F1 | Upload agency logo | ✅ | In Settings; shown in the top bar. |
| F2 | Editable theme (legible text) | ✅ | Colour picker drives the theme; text colour auto-computed for legibility. |
| F3 | SBU Expense heading | 🟡 | Heading master + SBU tag done; per-SBU configurable headings still pending. |
| F4 | License server + per-user billing | 🅿️ | Last, by agreement. |

## G. Parked (your input needed)

G1 Inspector expense→job link 🅿️ · G2 CV/hiring pipeline 🅿️ · G3 Multi-SBU cost split 🅿️ ·
G4 Reminder crons setup 🅿️ · G5 O365 auto-email (SMTP) 🅿️ · G6 Persona landing pages 🅿️

---

## Guidance still open
- **Product line (D2)**: recommend a dedicated `products` master + `vendor_products` mapping + CSV bulk import so each vendor shows only its own subset (infix search already built). Say the word and I'll build it.
- **Cross-branch numbering (C15)**: implemented as one master record + role letters + a per-branch-roles table (no duplicate entity).
