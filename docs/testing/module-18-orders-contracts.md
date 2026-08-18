# Inspection Ops — Orders / Contracts — Test & Documentation Report

> **Prompt 3 · Module MOD-CRMORD.** Read from `lib/contracts.php` (`contract_state`,
> `contract_state_blocks`, `contract_gate`, `override_live`/`_open`/`_consume`,
> `gen_contract_number`, `contract_link_quotation`, `contracts_expiry_reminders`,
> `contracts_idle_autoclose`, `ops_contract_open`, `ops_contract_overrides`,
> `can_grant_override`/`can_endorse_*`), `lib/crm.php` (`quote-contract`,
> `crm_float_ops_packet`, `contract_no_clash`), `lib/ops.php` (`contract_ref_ensure`,
> `contract_number_for`, allocation gate). Views `contract_overrides.php`.

| | |
|---|---|
| **Module** | Orders / Contracts (MOD-CRMORD) · Area Sales → Operations |
| **Personas** | P-BDM/P-COORD (register), P-BM/P-SBU (endorse), P-MASTER (grant override), P-ACCTS |
| **Risk weight** | **High** — the contract cover that authorises (and limits) field work; its expiry/quantity gates stop over-servicing |
| **Verdict** | Complete-with-defects (confirm gate enforcement, override two-signature, quantity-unit consistency) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

A won quote becomes an **order/contract**: a client is registered and a **contract number**
generated (`gen_contract_number`, BRANCH/C/FY/NNNNN, unique), the operations **order packet**
is floated to the executing branch (`crm_float_ops_packet`), and from then on the contract
governs scheduling. `contract_state` computes NONE / OK / EXPIRING / EXPIRED / QTY_LOW /
EXHAUSTED from **end date** and **quantity used vs total**; **EXPIRED and EXHAUSTED block**
new work (`contract_state_blocks`), EXPIRING/QTY_LOW only warn.

`contract_gate` is the single scheduling decision, enforced at **job allocation** (new
jobs only): blocked unless a **granted, unexpired, unspent override** exists. Overrides are
governed: **EXPIRED** = one approval, **Super Admin only**; **EXHAUSTED** = **two-step**
(Branch Manager endorses, Super Admin approves — over-servicing is a commercial decision),
consumed once per allocation. A contract-**number opening** is itself two-signature (manager
endorse → branch manager approve). Cron runs expiry reminders and idle auto-close (60 days).

Screens: `/contract-overrides` (queue + request/endorse/grant/refuse), `/contract-open`,
`/quote-contract`, `/quote-float`. Tables: `partner_contracts`, `contract_overrides`,
`boss_numbers`, `quotations`.

---

## B. Screen-by-screen catalogue

**`/quote-contract`** — register client + contract from a won quote; number generated;
order held (PENDING) or floated (OPEN). **`/contract-open`** — endorse / approve / reject /
reopen a number opening (two signatures). **`/contract-overrides`** — the queue: request an
override, endorse (BM), grant (Master), refuse; shows live/pending overrides and reasons.
**Allocation form** (MOD-05) shows the contract position *before* typing.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-CON-form-001 | Contract number unique (DB `uq_contract_number` + app `contract_no_clash`: same number on a **different** client refused; same number/same client = rate contract, allowed). |
| TC-CON-form-002 | Override request needs a non-empty reason + a quotation; one open request per quotation+kind. |
| TC-CON-form-003 | Contract-open approver must differ from the endorser (unless Master). |
| TC-CON-form-004 | Start/end dates well-formed; quantity total from countable quote lines. |

---

## D. Functions & logic  *(the gate — highest scrutiny)*

- **State** (`contract_state`): dates first (EXPIRING within warn window, EXPIRED past end),
  then quantity (QTY_LOW ≤10% left, EXHAUSTED ≤0). **TC-CON-fn-001..003.**
- **Gate** (`contract_gate` at allocation, ops.php ~4990): EXPIRED/EXHAUSTED block new jobs
  unless a **live override**; a granted override is **consumed once** on allocation
  (`override_consume`). **TC-CON-fn-004** — a crafted allocation POST against an
  expired/exhausted contract is refused; **TC-CON-fn-005** — an override is spent exactly
  once (uses_taken), not on grant.
- **Override governance:** EXPIRED → Master-only single grant; EXHAUSTED → BM endorse then
  Master grant. **TC-CON-fn-006** — the two-signature path cannot be short-circuited by a
  non-master; **TC-CON-fn-007** — refuse at any reached step works.
- **Number opening** (two signatures) + **idle auto-close** (60 days no activity → CLOSED,
  reopenable) + **expiry reminders** (once per window). **TC-CON-fn-008..010.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| won quote → contract PENDING | register | number generated |
| PENDING → OPEN | number opening approved | two signatures (or Master) |
| OPEN → CLOSED | idle auto-close / manual | 60 idle days; pending items flagged |
| CLOSED → PENDING | reopen | authorised |
| contract OK → EXPIRED/EXHAUSTED | time / quantity | blocks new allocation |
| override PENDING → (ENDORSED) → APPROVED/REFUSED | governance | kind-dependent signatures |

- **TC-CON-life-001:** an EXPIRED/EXHAUSTED contract blocks new jobs but existing jobs stay
  editable.
- **TC-CON-life-002:** an override, once granted, allows exactly its `uses_allowed`
  allocations and expires at `valid_until`.

---

## F. Roles, permissions & data scope

Register: `crm.contract.register`/master. Override endorse: BM/SBU head/BD or
`users.manage.branch`. Override grant: **Master only**. Contract-open endorse: op-manager/
SBU head/admin; approve: BM/branch-app-manager. Allocation: coordinator-level. Notifications
scoped to owner + reporting manager + office head.

- TC-CON-perm-001 (non-master granting an override) → refused.
- TC-CON-perm-002 (endorser == approver, non-master) → refused.

---

## G. Settings

`contract_warn_days` (30, in UI), `contract_idle_close_days` (60, **code-default only —
GAP**), quote numbering, pre-order checklist. **TC-CON-set-001:** warn-days change moves the
EXPIRING window.

---

## H. Cross-module integration

**Quotations** (won → order; contract_id link down the revision chain), **Calls** (order →
`call-new?quote=`; carries client/contract/SBU), **Jobs** (allocation gate; contract number
resolved via `contract_number_for`; `boss_numbers` via `contract_ref_ensure`), **Invoicing**
(contract number on invoice), **Profitability** (`boss_numbers` register). Idempotency:
double-register must not create two contracts for one quote.

---

## I. Data integrity & audit

Contract number unique (DB index + app clash check); override consumption tracked
(uses_taken); opening/override decisions recorded with endorser/approver. **TC-CON-int-010:**
quantity used (`quote_qty_used`, man-days) vs total (`quote_qty_total`, countable lines) —
**confirm the units are comparable** (GAP-CON-002); **TC-CON-int-011:** the contract number
on the job/invoice equals the registered contract.

---

## J. Reports & outputs

The operations order packet (email: client, quote/contract no, lines, advance flags, T&C),
expiry/idle reminder emails, the override queue. **TC-CON-out-001:** the order packet lists
the correct contract, lines and terms.

---

## K. Negative, edge & resilience

Allocate against an expired/exhausted contract (blocked); grant an override as a non-master
(refused); endorse == approve (refused); a duplicate contract number on another client
(refused); a rate contract (same number, same client, allowed); an idle contract past 60
days (auto-closed); mixed quantity units misreporting EXHAUSTED.

---

## L. TPIA operational suitability

Models the commercial cover a TPIA works under: a numbered contract with an expiry and a
quantity, gates that stop over-servicing, a governed exception route (with tighter control
for exhausting quantity than for a lapsed date), and an idle-close so dead contracts do not
linger. Rate contracts (repeat draw-down on one number) are supported.

## M. Management usefulness

Expiring/exhausted warnings, the override queue, idle-close summaries, and the
contract-number register give sales and operations control over commercial exposure.
Confirm the quantity math reflects the real unit sold.

## N. UI/UX

The contract position is shown on the allocation form before typing; the override queue
spells out the reason; two-signature flows are explicit. Terminology via `T*()`.

## O. Security

Override grant Master-gated; two-signature separation enforced (non-master); allocation
gate holds on a crafted POST; contract-number uniqueness enforced; notifications scoped.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | **Priority** | §E gate + override |
| 6 Validation | Y | §C uniqueness |
| 7 Negative | Y | §K |
| 8 Roles | **Priority** | §F two-signature |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | **Priority** | §D gate at allocation |
| 12 Integration | Y | §H quote→order→call→job |
| 13 Data integrity | **Priority** | §I quantity units |
| 14 Audit | Y | §I override consume |
| 15 Outputs | Y | §J order packet |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | — |
| 22 Notifications | Y | expiry/idle/order |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | N-A | — |
| 26 Terminology | Y | — |
| 27 Time/FY | **Priority** | §D expiry |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-CON-001 | (verify — Major) | Confirm the **contract gate** and **override two-signature** are enforced on the allocation and grant POSTs (crafted request refused; EXHAUSTED cannot be granted by a non-master; endorser ≠ approver). |
| GAP-CON-002 | (verify — potential Major) | **Quantity-unit consistency:** `quote_qty_total` sums countable quote-line quantities while `quote_qty_used` sums job man-days — confirm these are the same unit, or EXHAUSTED can misreport. |
| GAP-CON-003 | (verify) | Confirm the gate holds when an existing job is **re-dated** (the exemption for editing existing jobs is not a hole), and that `contract_idle_close_days` is intentionally code-only (or surface it in settings). |

---

## R. Traceability

RTM slice: `/quote-contract`, `/contract-open`, `/contract-overrides`, allocation gate ×
dims 1–29 → TC-CON-* → results → DEF/GAP. **Verdict: Complete-with-defects** — gate
enforcement, override governance, and quantity-unit consistency are the exit conditions.
