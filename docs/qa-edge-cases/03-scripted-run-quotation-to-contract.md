# Scripted Test Run — Quotation → Contract (with exact figures & verdicts)

**Scope.** The commercial stage of the E2E flow (`docs/qa-edge-cases/00-end-to-end-flow.md`): inquiry → quote →
approval → accept → register the contract → open it → the contract-state verdict that gates all downstream
work. Verifiable figures (the §27 "committed" amount, the `contract_classify` verdicts, the §25 engagement
rollup) were **produced by the live engine**; a ⚙ marks those, a 📘 marks a rule stated from the code.

---

## 1. Seed data

| Object | Field | Value |
|---|---|---|
| Client `Acme` | is_client, state, GSTIN | 1 · a state (drives GST) · a GSTIN |
| Inquiry | requirement, SBU | captured · NDT |
| **Quotation `Q-DEMO`** | subtotal | **₹200,000** |
| | GST | **18%** → **₹36,000** |
| | **total_amount** | **₹236,000** |
| | status | starts **DRAFT** |
| Owner/branch | office | Ahmedabad (scopes visibility) |

---

## 2. Step-by-step

### Step A — Create the quote

**Expected:** `total_amount = subtotal + GST = 200000 + 36000 = ₹236,000`; `status = DRAFT`.
**Reflected in:** the quote screen; `quotations.total_amount`; the inquiry now shows its quote.

### Step B — Submit for approval

| What | Expected | Reflected in |
|---|---|---|
| The current step is the approver's | Appears in the approver's queue | `crm_quotes_awaiting_me()`; `ops_pending_tasks` "quotes to approve"; My Work |
| **Segregation** | The submitter cannot approve their own quote where SoD applies | approval action | 🟠 |
| Branch scope | Another branch's manager does **not** see this quote (§22/§51) | `/quotes`; global search | 🟠 |

### Step C — Revise (versioning)

Edit an already-issued quote → a **new revision**.
**Expected:** new `rev`, `is_current=1` on the new one, the old revision retained with `parent_id`; nothing
overwritten. **Reflected in:** the quote's revision history. 🟡

### Step D — Client accepts ⚙

Mark the quote **ACCEPTED**.

| What | **Expected** | Reflected in |
|---|---|---|
| Status / date | `status = ACCEPTED`, `accepted_date` set | quote |
| **§27 money stream** | a **QUOTE_ACCEPTED** event, direction **committed**, amount **₹236,000** | client-360 Money timeline; Command Centre money band |
| **Rollup** (verified) | **committed = 236,000** · billed = 0 · received = 0 · outstanding = 0 | `financial_rollup(partner)` |
| Finance nag | Surfaces as "contracts to register" until a contract number is booked | `quotes_awaiting_contract_count()`; the Sales→Finance handoff wall (C1) |

✅ **PASS if** the accepted quote shows as **committed ₹236,000** (not billed) and nags Finance to register it.
An ACCEPTED quote is a *commitment*, not revenue — it must never show as billed until an invoice is raised.

### Step E — Lost / rejected (the other branch)

Mark the quote **lost** with a reason.
**Expected:** `status` = lost/rejected, `lost_reason` recorded; **no** contract, **no** §27 committed event.
**Reflected in:** the quote; pipeline/loss analytics. 🟡

### Step F — Register the contract (Finance)

From the accepted quote, Finance books a contract number.
**Expected:** a `partner_contracts` row; `open_status = PENDING`; the quote now carries `contract_number`
(the spine key everything downstream matches on). **Reflected in:** `/contract-openings`; the quote. 🔴

### Step G — Endorse → approve the opening

| Action | Expected | Reflected in |
|---|---|---|
| Manager endorses | `mgr_endorsed_at` set; still PENDING | contract-openings queue |
| Branch manager approves | `bm_approved_at` set; **PENDING → OPEN** | contract-360; the gate now allows work |

✅ **PASS if** the contract only reaches **OPEN** after both endorsement and approval.

### Step H — The contract-state verdict (the gate everything reads) ⚙

`contract_classify()` is the *single* verdict every surface uses (scheduling gate + Contract-360). Warning
window = **30 days** (`contract_warn_days()`). Verified verdicts:

| Situation | days_left / qty | **State** | Blocks new work? |
|---|---|---|---|
| Valid, quantity plenty | 200 · 100 of 100 | **OK** | no |
| Expiring soon | ≤ 30 days | **EXPIRING** | no (warns) |
| **Expired** | < 0 days | **EXPIRED** | **YES** |
| Quantity low | qty_left ≤ 10% | **QTY_LOW** | no (warns) |
| **Exhausted** | qty_left ≤ 0 | **EXHAUSTED** | **YES** |
| Expired **and** qty fine | −26 days, 50 left | **EXPIRED** | **YES** (dates decide first) |
| No end-date and no quantity | — | **NONE** | no |

✅ **PASS if** the verdict matches, `EXPIRED`/`EXHAUSTED` **block** and `EXPIRING`/`QTY_LOW` only **warn** —
and the *same* verdict shows on the Contract-360 badge and the scheduling gate (one formula, no second copy).

### Step I — Raising work against the contract (the gate in action)

| Contract state | Raise a new call/job | Reflected in |
|---|---|---|
| OK / EXPIRING / QTY_LOW | Allowed (EXPIRING/QTY_LOW show a warning) | call-new gate; contract badge |
| EXPIRED / EXHAUSTED | Blocked by `contract_state_blocks()` | call-new refused |
| Not-yet-OPEN contract | **Warn-and-allow** (#1) — proceeds, the warning is recorded | add screen |
| Idle contract past threshold | Heads-up before auto-close (#2) | contract-360; notification |

### Step J — The engagement rollup (§25) ⚙

`engagement('CN-DEMO')` gives the whole spine under the contract number in one read.
**Verified shape:** `rollup = { quotes, calls, jobs, reports, invoices, open_calls, open_jobs, billed }`.
Right after registration: **`{ quotes: 1, calls: 0, jobs: 0, reports: 0, invoices: 0, billed: 0 }`** — and it
grows as calls/jobs/reports/invoices are added against the same `contract_number`. **Reflected in:** the
engagement view; Contract-360.

### Direct-win path (no quote)

Finance can register a contract **without** a prior quote (a direct win).
**Expected:** the contract exists with its own value/validity; `contract_state_row()` classifies it through the
**same** `contract_classify()` (no quote needed). **Reflected in:** contract register; Contract-360. 🟡

---

## 3. Edge cases

| # | Change | Expected | Sev |
|---|---|---|---|
| E1 | Approver = submitter | Blocked where segregation applies | 🟠 |
| E2 | Branch user searches another branch's quote | Not returned (§22 scope) | 🟠 |
| E3 | Accept, then never register | Persistent "contracts to register" nag; §27 stays *committed*, never *billed* | 🟡 |
| E4 | Revise an accepted quote | New revision; the accepted one retained; acceptance not silently carried | 🟡 |
| E5 | Contract value set, then bill beyond it | §33 invoice-readiness **warns** "over contract value" (not a hard block) | 🟠 |
| E6 | Contract expires mid-engagement | EXPIRED → new work blocked; existing jobs unaffected; badge flips everywhere at once | 🟠 |
| E7 | Quantity exhausted | EXHAUSTED → new calls blocked; QTY_LOW warned first at ≤10% | 🟡 |
| E8 | GST — inter-state client | The invoice later uses IGST, not CGST/SGST — decided from the client state captured here | 🔴 |
| E9 | Two offices on one contract | Cross-office work settles via the §32 matrix; contract remains the single spine | 🔴 |

---

## 4. Pass/fail summary

| Assertion | Expected | Verified | Pass? |
|---|---|---|---|
| Quote total | subtotal + GST = **₹236,000** | 📘 (arithmetic) | ☐ |
| Accept → §27 committed | committed **236,000**, billed 0 | ⚙ | ☐ |
| Accept nags Finance | "contracts to register" until booked | 📘 | ☐ |
| Register → PENDING | `open_status=PENDING`, quote carries contract_number | 📘 | ☐ |
| Endorse + approve → OPEN | reaches OPEN only after both | 📘 | ☐ |
| contract_classify — valid | **OK** (no block) | ⚙ | ☐ |
| contract_classify — expiring (≤30d) | **EXPIRING** (warn) | ⚙ | ☐ |
| contract_classify — expired | **EXPIRED** (blocks) | ⚙ | ☐ |
| contract_classify — exhausted | **EXHAUSTED** (blocks) | ⚙ | ☐ |
| contract_classify — qty ≤10% | **QTY_LOW** (warn) | ⚙ | ☐ |
| Expired beats qty-fine | **EXPIRED** (dates decide first) | ⚙ | ☐ |
| Engagement rollup after register | `{quotes:1, calls:0, jobs:0, invoices:0, billed:0}` | ⚙ | ☐ |
| Segregation on approval | submitter ≠ approver enforced | 📘 | ☐ |

*⚙ verified against the live engine on 2026-08-27 (warn window = 30 days); 📘 stated from the cited rule. This
completes the scripted set — money (`01`), report/issue (`02`), commercial (`03`) — over the flow map (`00`).*
