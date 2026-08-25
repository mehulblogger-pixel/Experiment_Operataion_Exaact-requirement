# Module 12 — NCR (toward a Quality Case) · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Decision: (A) surface & fix only. Fixed the per-job/report NCR filter + added the Job-360 Quality panel; the mature NCR lifecycle and its gates are preserved (asserted by tests in `tests/test_module12_ncr.php`). P1.

---

## 0. Headline: mature register — two concrete gaps to close

The NCR subsystem is already substantial: a 4-state lifecycle (OPEN → CONTAINED →
DISPOSITIONED → CLOSED) with **gated closure**, an event timeline (`ncr_events`), **six wired
auto-origins** (report-issue unauthorised-signer, audit findings, client rejection,
confidentiality breach, ops events, manual/complaint-prefill), a **bidirectional NCR↔CAPA
link** (MAJOR must have a closed CAPA), per-partner surfacing, and a `/issues` workspace that
already seeds the "Quality Case."

**Two real gaps this module closes (surface & fix, don't rebuild):**
1. **The per-job NCR link is broken.** The job glance chip links `/ncr?job=<id>`
   (`ops.php:1586`), but `ops_ncr()` never reads `$_GET['job']` (`ncr.php:451-473`) — so it
   lands on the **full** open register, not the job's NCRs. Same for `report`.
2. **The Job 360 "Quality" section is missing.** On the job screen NCR is only a count chip;
   there is no per-job list of the findings, their severity/status, or their linked CAPA
   (Module 05 flagged Quality as the weakest Job-360 section).

**Deliberately out of scope (noted, not built):** re-architecting NCR + CAPA + complaint into
one physical "case" record; adding `inspector_id` / a standard-master FK to NCR; moving
RCA/verification onto non-MAJOR NCRs. Those are schema/workflow changes — see §4.

---

## 1. Proposed additive layer

1. **Fix the per-entity register filter** — teach `ops_ncr()` / `ncr_where()` to honour
   `?job=<id>` and `?report=<id>` (scoped, respecting existing office scope), so the job/report
   chip lands on **that entity's** NCRs. The full register is unchanged when no such param.
2. **Job "Quality" panel** — a `data-tab="Reports & QA"` (or a new "Quality") panel on the job
   screen listing this job's NCRs: ref, severity, status, disposition, owner/due, and the
   **linked CAPA** ref/status — each linking to the NCR detail. A "Raise an NCR" link reusing
   the existing `/ncr-new?job=…&report=…` prefill. Read-only list; raising stays gated.
3. **Report detail** — surface the NCRs raised on/from a report (the report screen already has
   the pieces; a small "Nonconformities" line linking to `/ncr?report=<id>`).

Reuses `ncr_all` / the register query, `ncr_counts`, the CAPA link columns. No new permission;
the lifecycle, gated closure, and MAJOR-must-have-CAPA rule are untouched.

## 2. Edge cases

1. **Job with no NCRs** → the Quality panel says "No nonconformities on this job" + the raise
   link; never an empty error.
2. **Closed vs open NCRs** → the panel shows all for the job with a clear status pill; the chip
   count and the panel agree.
3. **`?job=` / `?report=` filter** → returns only that entity's NCRs; an unknown/զero id falls
   back to the normal register (no fatal, no leaking everything as "job 0").
4. **Office scope** → the filtered view still respects the viewer's office/SBU scope (a filter
   must never widen visibility).
5. **Accreditation pack OFF / no `mod.ncr.view`** → the NCR module is pack-gated today; the job
   Quality panel must only render when the viewer can actually reach NCRs (else it's a dead
   chip). Degrade to nothing, not a broken link.
6. **Field inspector** → may see the findings on their own job read-only (transparency), but
   raising/closing stays gated as now; no money/internal leakage.
7. **MAJOR NCR with an open CAPA** → the panel shows the CAPA ref + "open"; closure still
   blocked by the existing gate (unchanged).
8. **Partner column conflates client/vendor** → the panel labels the party plainly (it's the
   job's client/vendor); we do not invent a new column.
9. **Performance** → one scoped query per job screen; the register filter adds a WHERE clause,
   no N+1.
10. **Mobile** → the Quality panel is single-column, compact pills.

## 3. Guardrails (must stay green)

- `test_ncdca` (issue-type dimension, reclassify, recurrence) — untouched.
- `test_simplify_nonconformity` (the `/issues` vs `/ncr` switch, views exist) — untouched.
- `test_uvaae` (audit→NCR raise) and `test_job_glance` (the NCR chip) — untouched.
- The `ncr_create` funnel, the 4-state lifecycle, `ncr_close_missing` gate, and NCR↔CAPA
  coupling — all unchanged.

---

## 4. OPEN DECISION — how far toward a "Quality Case"?

- **(A) Surface & fix only (recommended, P1-sized):** fix the broken per-entity filter + add
  the job Quality panel + the report NCR line. Reuses the existing tables; no schema change; no
  workflow change. Delivers the "NCRs linked to job/report/client, visible in context" the spec
  asks for, and fills the Job-360 Quality gap.
- **(B) Also extend the NCR record** — add RCA/verification capture to non-MAJOR NCRs and/or a
  unified case-status spanning NCR+CAPA. This is a **schema + lifecycle change** (new columns,
  new gates), higher risk, and overlaps the CAPA module (13) which already owns RCA/verification.
  I'd want to do it carefully with its own migration + tests, and update the lifecycle docs.

Default if you don't specify: **(A)** — surface and fix now; keep RCA/verification on CAPA
(Module 13) where it already lives.

## 5. Tests

1. `?job=<id>` returns only that job's NCRs; `?report=<id>` only that report's; no param → the
   full register (regression); office scope still applied.
2. The job Quality panel lists the job's NCRs with severity/status and the linked CAPA; empty
   state for a clean job.
3. The lifecycle/gates are unchanged (a MAJOR NCR still needs a closed CAPA to close).
4. No new permission; pack-gating respected (panel hidden when NCRs unreachable).
