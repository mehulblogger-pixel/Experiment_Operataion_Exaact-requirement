# Module 19 — Inquiries / Requirements · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: the one un-instrumented rung of the funnel

The inquiries subsystem (`crm_inquiries`, handler `ops_crm_inquiries`) is healthy on capture,
validation and delete-guarding, and the funnel links exist end-to-end (lead → inquiry → quote →
contract). But the inquiry rung is **blind to time**: an OPEN inquiry sits un-quoted forever, with
no aging, no reminder, no worklist — even though `received_date`/`created_at` are captured, and the
exact pattern was built two modules over (`leads_due()`, Module 17) and three over (`quote_validity()`,
Module 03). Separately, `assigned_to` is **dead data** — stored but never shown or queried.

---

## 1. Built (additive, read-only, no hard control)

1. `inquiries_due($today)` + `inquiries_due_count()` — OPEN inquiries older than the response service
   level (`inquiry_sla_days()`, a new setting, default 7), aged from `received_date` (fallback
   `created_at`), SBU-scoped exactly as the register is; carries the owner name. QUOTED/DROPPED are
   never chased. Read-only — it never changes a status (`quote_validity` discipline).
2. `adv_inquiries_unquoted()` — a business-advisor "Inquiries waiting for a quotation" card mirroring
   `adv_cold_leads()`, registered in `adv_all()`, ranked oldest-first, explicitly advisory ("an
   inquiry is never dropped for you").
3. Register surfacing: a **waiting-for-quote banner** + count, an **Owner column** (finally reading
   the dead `assigned_to`), and a **⏳ Nd** pill on OPEN rows past the service level.
4. The **first tests** over the aging behaviour (none existed).

---

## 2. Edge cases handled

1. OPEN + older than the SLA → due; fresh OPEN → not chased.
2. QUOTED / DROPPED → never chased (only OPEN).
3. Age from `received_date`, falling back to `created_at`; an inquiry with neither is skipped (never
   crashes).
4. SBU scope is the same `scope_sbus()` the register applies — a BU-restricted user sees only theirs.
5. The advisor card returns null when nothing is due, and is gated by `adv_on('inquiries')`.
6. The detector never writes — asserted by a test that the status is untouched after a scan.

## 3. Guardrails (green)

The register, the form, the create/edit/delete guards, the auto-QUOTED flip on quote raise/accept,
the lead↔inquiry↔quote links — all unchanged. No new permission; no schema change (a setting +
reading existing columns); no access changed; no inquiry auto-dropped; nothing deleted.

## 4. Tests

`tests/test_module19_inquiries.php` (14 assertions): stale OPEN inquiries are due; fresh / QUOTED /
DROPPED are not; the age is carried; the detector leaves the status untouched; the register banner +
owner column; the advisor card is registered and advisory; the SLA is a configurable setting.
