# Module 06 — Inspection / IDEMS core + Applicability · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Decision: (A) surface & formalize only. Additive read-only applicability surfacing; existing mechanism + soft-narrow contract preserved (asserted by tests in `tests/test_module06_applicability.php`).

---

## 0. Headline: most of this already exists

The map found that **applicability is already implemented**, softly and non-destructively:

- **`deliverables` (a CSV of report-type codes on the call/job)** is the per-job "reports
  this inspection owes". It already **narrows the report-type dropdown** on the create form
  (`idems.php:4293-4308`) — but **never to nothing** (`:4307`), and a **"Need a different
  one?"** escape link re-adds every other type (`doc_form.php:58-64`). No type is ever
  unreachable.
- **`service_report_map`** (`services.php:102-106`) maps **service line → report types**, with
  **client-specific overrides**, seeded defaults (INSPECTION→IR, EXPEDITING→ER, …), a reader
  `svc_report_codes($service,$client)`, and an admin screen (`/service-formats`). This is the
  real "which reports apply" engine, and it **auto-fills `deliverables`** at job creation when
  none were ticked (`ops.php:4020-4022`, `:5352-5354`).
- **A per-job "Reports on this job" panel already exists** (`job_detail.php:516-567`): it lists
  each owed format with status (not started / draft / issued), a **Write it / Add another**
  button that lands on the right type (`?type=CODE`), and flags any report written under a code
  **not on the agreed list** ("not on the agreed list", `:547`).

**So the "applicability engine" the program asks for is ~80% built.** What is *missing* is the
inspector-facing clarity the spec calls for: **why** each report is applicable (its source),
and an explicit **"these types are NOT applicable to this job"** view. That is the additive gap.

**What does NOT exist:** any `applies_to` / inspection-type / sbu column on `report_types`
itself; and any `inspection_type → report_type` mapping. Applicability is keyed on
**service_code + deliverables**, not on inspection type.

---

## 1. Proposed additive layer (recommended, lightweight — reuse, don't rebuild)

1. **`idems_job_applicability($job)`** — one read-only helper returning:
   - `applicable`: the job's deliverable codes → `{code, name, source, status}` where
     **source** is derived (no new storage) by comparing against
     `svc_report_codes(service_code, client_id)`: *"from the service agreement"* (default),
     *"client-specific"* (override), or *"added at allocation"* (on the job but not in the map).
   - `not_applicable`: the other active report types (catalogue minus applicable) → `{code,name}`.
   - `off_list`: report_docs already written under a code not in `applicable` (already computed
     as `$extraCodes` on the job panel — reused).
2. **Enhance the job "Reports on this job" panel** — add, per owed format, a small **source
   note** ("from the service agreement" / "client-specific" / "added at allocation"), and a
   **collapsed "Other report types — not applicable to this job"** list with the existing
   *add-anyway* link (`?type=CODE`, flagged "not allocated"). Nothing removed; the escape hatch
   is preserved.
3. **Create-form note** — where the dropdown is already narrowed, add a one-line "why these"
   ("limited to what this job owes — from its service agreement") beside the existing "Need a
   different one?" link. Display only.

No new permission. No report type ever hidden from reach. `report_types` unchanged.

---

## 2. Edge cases

1. **Job with no `deliverables` and no service_code** → nothing to narrow: applicable list is
   empty, so the create dropdown offers the full catalogue (current behaviour) and the panel
   says "no specific reports agreed for this job — any type may be raised." Never an empty
   locked state.
2. **`deliverables` present but a code matches no active report_type** (stale/renamed code) →
   skip it silently from "applicable" (don't crash), but keep it visible on the panel as
   "agreed code X — no matching report type" so it isn't lost.
3. **Client override differs from house default** → source shows "client-specific"; the
   comparison uses `svc_report_codes(service, client_id)` so the override is what's compared.
4. **Report written off-list** (a type not in deliverables) → already flagged "not on the
   agreed list" (`job_detail.php:547`); keep that, and it appears under `off_list`, not counted
   as "missing".
5. **Standalone report** (no job/call) → no applicability context; full catalogue, no panel,
   no provenance. Must not error on a null job.
6. **Inactive report type in deliverables** → shown as applicable-but-inactive with a note;
   still reachable (never hard-hidden).
7. **"Never narrow to nothing"** (`idems.php:4307`) and the **"Need a different one?"** escape
   (`doc_form.php:58-64`) must remain — regression-guard both.
8. **Non-applicable list is noise if long** → render it **collapsed** by default; it's a
   secondary affordance, not the primary panel.
9. **Performance** → applicability is a couple of small in-memory list operations over
   `idems_types()` + one `svc_report_codes` read; only on the job screen / create form.
10. **Mobile** → the panel stays single-column; the collapsed "other types" doesn't crowd the
    phone view.
11. **`test_doc_form_declutter.php` / `test_call_carry_forward.php`** guardrails → keep every
    hidden input & visible label on `doc_form.php`, and the `deliverables` carry semantics,
    intact.

## 3. Backward-compat

- `deliverables`, `service_report_map`, the job reports panel, the create-form narrowing, and
  the escape hatch all stay exactly as they are; this layer only *reads* and *annotates* them.
- No status, route, permission, table column, PDF, or template changed.

---

## 4. OPEN DECISION — I need your call before building

**How far should Module 06 go?**

- **(A) Surface & formalize only (recommended, ~1 focused commit):** add the applicability
  helper + provenance notes + the explicit "not applicable" list, reusing the existing
  `service_report_map` + `deliverables`. Lowest risk; no duplicate engine; delivers exactly the
  inspector clarity your spec describes ("which apply / which don't / why").
- **(B) Also add a new `inspection_type → report_type` mapping** (a second mapping table
  parallel to `service_report_map`, keyed on `calls.inspection_type` / `jobs.inspection_type`).
  More configurable, but it introduces a **second overlapping source of truth** for
  applicability (service-based vs inspection-type-based) which can disagree — against the
  program's "one canonical source, fewer duplicate concepts" principle. Only worth it if your
  applicability genuinely varies by inspection type in a way service lines can't capture.

Default if you don't specify: **(A)**.

## 5. Tests (before marking done)

1. `idems_job_applicability`: applicable list carries correct source (agreement / client /
   added-at-allocation); not-applicable = catalogue minus applicable; off-list detected.
2. No-deliverables job → full catalogue offered, no lock.
3. Stale deliverable code → no crash, shown as unmatched.
4. Standalone (no job) → no error, full list.
5. Regression: "never narrow to nothing" + "Need a different one?" escape preserved; no new
   permission constant.
