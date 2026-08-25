# Module 16 — Vendors / Vendor 360 · Edge-case analysis (pre-build)

**Status:** edge-cases drafted — **awaiting your go + one decision (§4) before code.** P2.
Additive; the existing scoring engines and qualification lifecycle are preserved.

---

## 0. Headline: the signals exist — consolidate them into a scorecard

`/vendor-profile` (`views/ops/idems/vendor_detail.php`, built by `ops_idems_vendors()`) is the
canonical Vendor 360. It already shows: latest assessment, classification, a **live composite
performance score/100** (`idems_vendor_performance`), recent reports, NCRs, complaints,
expediting performance, predictive delivery risk, and assessment/qualification history. Expiry
is tracked and **auto-enforced** (`idems_vendor_run_reminders`).

**Gaps vs the spec:**
1. **No single consolidated scorecard.** Three composites exist separately — quality
   performance (`idems_vendor_performance`), delivery risk (`idems_vendor_delivery_risk`),
   expediting reliability (`idems_vendor_expediting_perf`) — plus qualification currency
   (profile `valid_until`/`reassess_on`) and open NCR/complaint counts. They're scattered across
   panels; there's no one card that answers "how good is this vendor, across all domains".
2. **No CAPA section** — CAPA appears only as a `capa_ref` folded into assessment prefill text.
3. **No financial view** (rates / invoices / payments) — and **no vendor financial data exists**
   in the schema at all.

**Design note:** the scorecard must **reuse the existing scoring engines** and **invent no new
composite metric** — it consolidates the signals already computed, with the existing performance
score as the headline. No second scoring formula.

---

## 1. Proposed additive layer (recommended = §4-A)

1. **`idems_vendor_scorecard($partnerId)`** — a read-only assembler that calls the *existing*
   engines and returns one structure: headline **performance score + band**
   (`idems_vendor_performance`), **delivery risk** (`idems_vendor_delivery_risk`), **expediting
   reliability** (`idems_vendor_expediting_perf`), **qualification** (approval status + expiring/
   expired from the profile), and **open NCRs / complaints** counts. No new score computed.
2. **A "Scorecard" card** at the top of the Vendor 360 — one grid of the five domains, each with
   its existing value + a colour, so the whole picture is one glance (the panels below stay as
   the detail).
3. **A CAPA section** — the CAPAs linked to this vendor (via an NCR or complaint attributed to
   the vendor's `partner_id`), mirroring the existing NCR panel, each linking to `/capa-item`.

Reuses the existing engines and gates. No new permission; no new scoring math; no schema change.

**Deferred (noted, not built):** vendor **financial** (rates/invoices/payments) — no vendor
financial data exists, so this needs new plumbing (a separate future module); vendor
**certificate/competence** store — likewise absent today.

## 2. Edge cases

1. **Vendor with no assessment yet** → performance score shows "not assessed"; the scorecard
   still renders the domains it has (NCRs/complaints/qualification), never a fatal.
2. **No open NCRs/complaints** → those tiles read 0/clear; the headline isn't penalised (matches
   the existing engine, which only penalises open items).
3. **Qualification expired** → the qualification tile is red/"expired"; consistent with the
   existing overdue flag on the assessment panel.
4. **Missing engine / un-migrated** → each engine call is guarded (`function_exists` +
   try/catch); a missing one drops its tile, the card still renders.
5. **CAPA links** → a CAPA counts for the vendor if its source NCR (`capa.ncr_id` →
   `nonconformities.partner_id`) or source complaint (`capa.complaint_id` →
   `complaints.partner_id`) is attributed to this vendor. De-dup if both paths hit the same CAPA.
6. **No CAPAs** → the section says "No corrective actions linked to this vendor", not an empty
   error.
7. **No new metric** → the card shows only existing values; it does not compute a new overall
   number that could diverge from the engine's score.
8. **Performance** → the scorecard is a handful of bounded engine calls, computed once in the
   handler (like the existing `$perf`/`$xperf`/`$xrisk`); no per-row N+1.
9. **Mobile** → the scorecard grid wraps to one column.

## 3. Guardrails (must stay green)

- `idems_vendor_performance` / `_delivery_risk` / `_expediting_perf` and the qualification
  lifecycle (`idems_vendor_apply_assessment`, `idems_vendor_run_reminders`) — unchanged; the
  scorecard only *reads* them (and this adds the first tests around the composite score).
- The existing Vendor 360 panels, the register, and the vendor portal — untouched.
- `test_idems_vendor_autoflow`, `test_tapi_domain` (vendor_profiles) — untouched.

---

## 4. OPEN DECISION — how far?

- **(A) Consolidated scorecard (reusing existing engines) + CAPA section (recommended, P2):**
  no new scoring math, no schema change; delivers the "vendor scorecard" the spec asks for and
  adds the missing CAPA linkage. Also adds the first automated coverage of the composite score.
- **(B) Also build the vendor financial view** (rates/invoices/payments) — but **no vendor
  financial data exists** in the schema, so this means new tables + intake + a payables concept.
  That's a substantial module of its own, not a 360 surfacing; better done separately.

Default if you don't specify: **(A)**.

## 5. Tests

1. `idems_vendor_scorecard` assembles the existing signals (performance, delivery risk,
   expediting, qualification, open NCR/complaint counts) without inventing a new score; safe when
   an engine is absent.
2. The CAPA section returns the vendor's linked CAPAs (via NCR or complaint) and de-dups.
3. The Vendor 360 renders the scorecard card and the CAPA section.
4. No new permission; no new scoring formula; no vendor financial query added.
