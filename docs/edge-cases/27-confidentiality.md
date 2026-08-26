# Module 27 — Confidentiality (ISO 17020 §4.2) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: two pillars that never meet

Confidentiality lives in two independent code paths:

- **Governance / evidence** (`lib/confidentiality.php`) — staff **undertakings**, client **NDAs**, a
  **breach register** (auto-raising a §4.2 NCR). Complete and well-formed.
- **Runtime disclosure gate** (`lib/cvp.php` — `cvp_visibility_sql`/`cvp_can_see`, fail-closed) — the
  strong, well-tested engine that stops one external audience reading another's or internal records.

The gaps: the governance pillar is **disconnected from the work and from the compliance board** —
`conf_readiness()` was never called by `lib/compliance.php`, and the undertaking that an inspector
signed was never surfaced at the job they work. It was a filing-cabinet record. And the whole
governance pillar had **no tests**.

Deliberately **not** touched (needs a separate decision): making an undertaking a **hard gate** on
allocation (like impartiality's `imp_block`) — that changes who-can-do-what; a per-job
acknowledgement **write-stamp** is left as a future option; internal cross-client "need to know"
partitioning is a larger design change.

---

## 1. Built (recommended additive, read-only, no hard control)

1. `conf_job_status($job)` — the §4.2 picture for a job: whether the **assigned inspector** has a
   current confidentiality undertaking (ok / lapsed / none, reusing `conf_undertakings` +
   `conf_undertaking_live`), and the **client's own NDA obligation-end** (`conf_nda_obligation_ends`).
   Read-only; shows only this job's inspector and client — never another client's terms.
2. `conf_open_breach_count()` — open (not-closed) confidentiality breaches, for the readiness board.
3. A read-only **Confidentiality (§4.2) panel** on the job Overview (managers/coordinators, not field
   inspectors): the assigned person's undertaking status + the client NDA obligation run-out date,
   explicitly **advisory — it blocks nothing**.
4. **`conf_readiness()` registered on the compliance readiness board** (`lib/compliance.php`), beside
   impartiality and competence — so §4.2 is finally visible where accreditation posture is reviewed
   (covered / lapsed / none + open breaches).
5. The **first tests** for the governance pillar (undertaking in-force logic, coverage states, NDA
   obligation math, the job status, the breach count).

---

## 2. Edge cases handled

1. Inspector with a live undertaking → **covered**; expired → **lapsed** (still shown, with the last
   signed date + a renew link); nothing on file → **none**.
2. Undertaking dated in the future is **not yet** in force; one with no `valid_to` never expires.
3. NDA obligation runs `survives_months` beyond `valid_to`; no `valid_to` → no computed end (not
   shown as expired).
4. A job with no client NDA → only the inspector line shows; a job with no assigned inspector → only
   the NDA line (if any).
5. Field inspectors don't see the panel (it's a management/assessor evidence surface); it's gated by
   `conf_can_view()`.
6. Only this job's client's NDA is read — never another client's.

## 3. Guardrails (green)

The disclosure gate (`cvp_visibility_sql`), the undertaking/NDA/breach registers and their routes,
the impartiality allocation gate, and the rest of the compliance board — all unchanged. No new
permission; no schema change; no hard control; nothing deleted. The board row is **additive** beside
the impartiality row, not a replacement.

## 4. Tests

`tests/test_module27_confidentiality.php` (21 assertions): undertaking in-force logic; coverage
states (ok/lapsed/none); NDA obligation math; `conf_job_status` for covered / none inspectors + the
client NDA end; the breach count; the compliance board now carries a §4.2 row (impartiality row
preserved); the job panel is advisory.
