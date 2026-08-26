# Module 18 — Orders / Contracts 360 · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: the contract's live state drives the scheduling gate but was never shown on its 360

The contract spine is `partner_contracts` (+ the won `quotations` row carrying the same number, +
`partner_purchase_orders`). `contract_state($quoteId)` (`lib/contracts.php:157`) computes the live
verdict — **EXPIRING / EXPIRED / QTY_LOW / EXHAUSTED**, with `days_left` and quantity
total/used/left — and `contract_state_blocks()` uses it to **block scheduling** on an expired or
exhausted contract. But the Contract 360 (`ops_contract_360`, `contracts.php:850`; view
`views/ops/contract_detail.php`) rendered only the *idle-close* warning — it **never called
`contract_state()`**. So a coordinator looking at the contract could not see that it was expired or
out of quantity, even though the system would refuse to schedule against it. Classic
"signal computed, gate enforced, but never surfaced where it's read."

Second gap: the money row showed value / invoiced / received / outstanding but **never `value −
invoiced`** — the one figure that says a contract is under- or over-billed.

**Noted, deferred (separate concerns, flagged not built):**
- **Audit of open_status transitions & override decisions** — endorse/approve/reject/auto-close log
  into the quotation thread only *if a quote is linked* (`if ($qid)`), so a directly-recorded
  contract's transitions and every override grant/refuse go unrecorded. A good additive next
  (an `idems_log` write like Modules 14/42), but its own change.
- **Office-scoping the approval queues** (`/contract-openings`, `/contract-overrides` run unscoped) —
  this *narrows who sees what*, a visibility/control change that needs an explicit decision, not a
  silent build.
- Two invoice sources (jobs vs the `invoices` table) never reconciled; `open_status`/expiry columns
  on the client Contracts tab; surfacing the nominated `coordinator_id`.

---

## 1. Built (additive, read-only, no access change; one shared formula)

1. **Extracted `contract_classify($out)`** — the pure verdict (date rules then quantity rules).
   `contract_state()` now sets `end_date`/`days_left`/qty and **delegates** to it. Behaviour is
   identical (all existing contract/scheduling tests stay green); the point is that the gate and the
   360 now read **one** formula, not two.
2. **`contract_state_row($c)`** — the contract-keyed state for the 360: when a quotation drives the
   gate it returns the canonical `contract_state()` verdict (exactly what scheduling uses); a contract
   recorded directly on the client (no quote) is classified from its own `end_date`/`qty_total`
   through the same `contract_classify()`.
3. **`contract_state_label($state)`** — tone (bad/warn/ok/mut) + title + description for the banner.
4. **The 360 now shows a state banner** (EXPIRED/EXHAUSTED red with a "Request an override →" link;
   EXPIRING/QTY_LOW amber; OK green), with days-left and quantity-used detail.
5. **A "Left to invoice" (or "Over-billed") KPI** = `value − invoiced`, money-gated with the row.

---

## 2. Edge cases handled

1. A past end date → EXPIRED with negative days-left; inside the warning window → EXPIRING; far
   future → OK; no term & no quantity → NONE (banner suppressed — no gate applies).
2. Zero quantity left → EXHAUSTED; under 10% → QTY_LOW; dates decide before quantity.
3. A directly-recorded contract (no `quotation_id`) is still classified from its own row.
4. `value − invoiced` negative → shown as "Over-billed" (billed beyond value), styled as a downturn.
5. The banner only offers the override link for the two *blocking* states.
6. The view guards `$state ?? null` and `$money['remaining'] ?? 0`, so the pre-existing
   `test_contract_360.php` (which passes neither) still renders without error.
7. A DB caught mid-upgrade (row present, `qty_total` column absent) is handled by the existing `??`
   in the engine — no warning on the screen.

## 3. Guardrails (green)

`contract_state()` behaviour, `contract_state_blocks()`, the scheduling gate, `contract_idle_status`
and its idle banner, the commercial rollup, and the 360's permission gate — all unchanged. No new
permission; no schema change; nothing deleted. The refactor is behaviour-preserving (extract method).

## 4. Tests

`tests/test_module18_contract360.php` (24 assertions): the classifier and row-state decide
EXPIRED/EXPIRING/OK/EXHAUSTED/QTY_LOW/NONE correctly; blocking states; the label tone; the shared
"one formula" delegation; the 360 renders the banner, the override link, and the remaining/over-billed
KPI. Suite 3019 passing (only the 3 pre-existing baseline failures remain).
