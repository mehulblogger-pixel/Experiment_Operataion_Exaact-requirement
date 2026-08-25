# Module 22 — Complaints (unified workflow + SLA badge) · Edge-case analysis (pre-build)

**Status:** edge-cases drafted — **awaiting your go + one decision (§4) before code.** P1.
Additive; the lifecycle, close-gate and impartiality gate are preserved.

---

## 0. Headline: mature handler — make the stage and the SLA legible

Complaints already run a full path: **create → acknowledge → validity/triage (§7.5.3) →
investigate + root cause → decide (§7.5.4 impartiality-gated) → CAPA (auto-create, close-gated
for upheld/partly) → notify complainant → close** with a real completeness gate
(`cmp_close_missing`). SLA exists as **two configurable clocks** (ack default 3 days, decide
default 30) with overdue computation, nightly reminders, and red-pill/KPI display on register
and detail. Portal intake, client-360 and vendor-360 inbound views exist.

**Gaps vs the spec:**
1. **No single "where is this?" stage** — `status` is only OPEN/CLOSED; the nine stages are
   inferred from which columns are filled, and the "Handle" screen is a stack of sequential
   forms, not a visible pipeline.
2. **SLA is two clocks, not one prominent badge** — the spec wants SLA "shown prominently"; it's
   there but split.
3. **No automated test coverage** of the lifecycle, SLA math, close-gate or the §7.5.4 decide
   gate — a real guardrail gap.

**Deliberately out of scope (noted, not built):** new persisted columns (severity/category,
real due-date columns, an effectiveness stage on the complaint itself), a `vendor_id`/report FK.
Effectiveness already lives on the linked CAPA. Those are schema changes — see §4.

---

## 1. Proposed additive layer (recommended = §4-A)

1. **`cmp_stage($c)`** — a read-only derived stage from the existing columns:
   Received → Acknowledged → Triaged → Investigated → Decided → Corrective action → Complainant
   told → Closed — plus the current owner-ish "next step". Rendered as a **prominent progress
   strip** on the complaint detail and a **Stage pill** on the register (a "where is this in the
   flow" indicator, no schema change).
2. **`cmp_sla($c)`** — one consolidated SLA status: **On track / Ack overdue / Decision overdue
   / Met** with the governing deadline and days, shown as a **single prominent SLA badge** on
   detail + register (consolidating the two existing clocks; the clocks stay as the source).
3. **Fill the test gap** — behavioural tests for `cmp_create`, the SLA overdue math
   (`cmp_ack_overdue`/`cmp_decide_overdue`), the close-gate (`cmp_close_missing` incl.
   upheld⇒CAPA-required), and the decide impartiality gate (`cmp_decide_block`).

Reuses the existing columns, clocks, and gates. No schema change; no new permission.

## 2. Edge cases

1. **Fresh complaint (nothing done)** → stage "Received"; SLA "On track" until the ack deadline.
2. **Acknowledged, not triaged** → stage "Acknowledged"; ack clock stops, decide clock runs.
3. **Turned away (validity NOT_OURS/OUT_OF_SCOPE)** → stage reflects triage; the close-gate's
   outcome/notify requirements adapt exactly as `cmp_close_missing` already does (don't diverge).
4. **Upheld/partly, no CAPA yet** → stage "Corrective action" (the gate still needs a `capa_ref`
   before close); the SLA badge is independent of the CAPA.
5. **Decided but not notified** → stage "Decided"; SLA may still show "Decision met" while a
   "not yet told" flag remains (mirror the existing `unnotified` KPI).
6. **Closed** → stage "Closed"; SLA "Met" (or "Met late" if it breached before closure — read
   from the recorded dates, don't recompute against today).
7. **Anonymous with no contact** → the notify step is not required (as the gate already
   allows); the stage must not stall on "told" for such a complaint.
8. **Appeal (`kind=APPEAL`)** → same stage/SLA logic; the §7.6 independence gate on decide is
   untouched.
9. **SLA math** → overdue is days past `received_on + N`; zeroed once the corresponding step is
   done; never negative; safe when `received_on` is blank (treat as not-started, not overdue).
10. **Performance** → stage/SLA are pure per-row computations; the register maps them over the
    already-loaded rows (no extra query per row).
11. **Mobile** → the progress strip is compact/wraps; the SLA badge is one pill.

## 3. Guardrails (must stay green)

- The close-gate (`cmp_close_missing`/`cmp_close_block`), the §7.5.4 decide gate
  (`cmp_decide_block`), the CAPA auto-create + close-conditional-on-CAPA rule, and the SLA
  settings — all unchanged (and now covered by new tests).
- `test_simplify_quality_split` (the `/complaints` route in the quality split) — untouched.
- The portal intake path — untouched.

---

## 4. OPEN DECISION — surface only, or also persist new fields?

- **(A) Surface the unified stage + SLA badge + tests (recommended, P1-sized):** derive
  everything from existing columns; no schema change; delivers "unified workflow display" and
  "SLA shown prominently" and fills the coverage gap.
- **(B) Also persist new fields** — add `severity`/`category` columns, real due-date columns, or
  an effectiveness stage on the complaint. Schema + workflow change; effectiveness already lives
  on the linked CAPA, so this partly duplicates CAPA. Higher risk; its own migration + tests.

Default if you don't specify: **(A)** — surface stage + SLA and add coverage now; keep
effectiveness on CAPA.

## 5. Tests

1. `cmp_stage` returns the right stage across: received, acknowledged, triaged, investigated,
   decided, corrective-action-pending, responded, closed.
2. `cmp_sla` = On track / Ack overdue / Decision overdue / Met, with correct days; safe on a
   blank received date.
3. Close-gate: a complaint can't close without ack + validity + outcome + decided-by + notify,
   and an UPHELD complaint additionally needs a `capa_ref`.
4. The decide impartiality gate still refuses the involved party.
5. No new permission; no schema change (diff-scoped).
