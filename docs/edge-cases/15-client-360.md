# Module 15 — Client / Customer 360 · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Decision: (A) fill cheap sections; defer margin (Module 32) &
shared scaffold (Module 49). Additive `c360_reports/_sites/_satisfaction` on the gated 360
assembly (asserted by tests in `tests/test_module15_client360.php`). P2.

---

## 0. Headline: a rich 360 with a few real gaps

`/customer` (`lib/customer360.php` + `views/ops/customer360.php`) is the canonical Customer 360,
assembled read-only by `c360_load($pid)` with per-section licence+permission gates and
one-missing-table safety. It already carries: company, contacts, primary site, contracts,
quotes, work (open calls/jobs + unbilled), invoices, payments/outstanding + ageing, complaints,
NCRs, a report-**rejection count**, billed revenue, and a real **activity timeline** (+ pipeline
and group as extras).

**Missing vs the spec:**
1. **Issued-reports register** — the biggest gap. Only a *count* of rejected reports exists; no
   list of the reports actually issued to this client (Contract 360 has a full Reports panel to
   mirror).
2. **Full multi-site list** — only the single primary address is loaded (`LIMIT 1`); the full
   site list lives on `/partner`.
3. **Satisfaction / rating** — not surfaced (a `satisfaction`/`rating` lib exists in the code).
4. **Margin / profitability** — not shown per client.
5. **Upcoming / forecast demand** — not present.

**Design note:** margin must come from the **canonical profitability engine (Module 32)**, not a
bespoke per-client calc here — the program forbids a second profit formula. So margin is
**deferred** to when the canonical engine exposes a per-client figure. Likewise a **shared 360
scaffold** across Client/Vendor/Job/Contract is **Module 49**, not this module.

---

## 1. Proposed additive layer (recommended = §4-A)

Add the cheap, high-value read-only sections that reuse existing data, each behind the same
`c360_on(...)` gate pattern already used:

1. **Issued reports panel** — `report_docs` finalized/issued for this client (IRN, format,
   status, date, link to `/document?id=`), mirroring Contract 360's Reports panel. Fills the
   biggest gap.
2. **Full site list** — show all `partner_addresses` for the client (not just the primary),
   consistent with the `/partner` Addresses tab (read-only here, with map links).
3. **Satisfaction / rating** — if a per-client helper exists (`lib/satisfaction.php` /
   `lib/rating.php`), surface the score as a small card; skip gracefully if none.

**Deferred (noted, not built):** per-client **margin** → Module 32 (canonical engine);
**upcoming demand** → needs the PO-consumption/forecast work; a **shared 360 component** →
Module 49; **/partner vs /customer de-duplication** → a bigger consolidation.

No new permission; each new section reuses `c360_on()`/`c360_try()` so it stays gated and
crash-safe.

## 2. Edge cases

1. **Client with no issued reports** → the panel says "No reports issued yet", never an empty
   error; the existing rejected-count line stays.
2. **Reports the viewer may not see** → the reports section is gated by `c360_on('idems')` (the
   reporting module gate); if the viewer lacks it, the panel doesn't render (no leak).
3. **Missing table / un-migrated** → `c360_try()` wrapper returns empty; the screen never breaks.
4. **Many sites** → the full site list renders compactly; a very large list is capped with a
   "see all on the master record" link to `/partner?tab=addresses`.
5. **Satisfaction helper absent** → the card is skipped entirely (function_exists guard); no
   placeholder.
6. **Performance** → each new section is one bounded query (reports LIMIT N, addresses for the
   partner); no per-row N+1; loaded once via `c360_load`.
7. **Margin not shown** → intentional (deferred to Module 32); the Money panel stays billed/
   settled/outstanding, so no bespoke/duplicate profit figure appears.
8. **Mobile** → panels are single-column, consistent with the existing 360.

## 3. Guardrails (must stay green)

- The `c360_load` assembly, per-section gates (`c360_on`), and crash-safety (`c360_try`) —
  unchanged; new sections follow the same pattern.
- The Money section keeps reading `books_outstanding`/`books_settled` (one source of truth) —
  unchanged; no new financial math.
- `/partner`, `/ledger`, Contract 360 — untouched.
- `test_invoice_references_panel` (customer links to ledger) and `test_contract_360` — untouched.

---

## 4. OPEN DECISION — how far?

- **(A) Fill the cheap missing sections; defer margin/demand/scaffold (recommended, P2-sized):**
  add issued-reports + full site list + satisfaction (if available) to Customer 360, reusing the
  existing gated assembly. No schema change, no new profit math, no new permission. Margin stays
  with the canonical engine (Module 32); a shared 360 component stays Module 49.
- **(B) Also add per-client margin now** — compute a client-level margin here. Risk: it either
  duplicates the profitability engine (forbidden by the "one profit formula" rule) or requires
  looping `job_profit` over every client job (a performance and correctness hazard). Better done
  once, in Module 32, then surfaced here.

Default if you don't specify: **(A)**.

## 5. Tests

1. Customer 360 now loads an issued-reports section for a client (reused report_docs), with an
   empty state for a client with none.
2. The full site list is loaded (more than the single primary) when several exist.
3. New sections respect the existing `c360_on()` gating and `c360_try()` crash-safety.
4. No bespoke margin/profit query added to `lib/customer360.php`; no new permission.
