# Module 35 — Recruitment / Workforce · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: an interview whose date has passed with no outcome is chased nowhere

Recruitment is two data domains meeting at one handoff: `requisitions`/`candidates`/`candidate_events`
(newer) and `inspectors`/`agencies` (older, where placement fees live). Candidate stage moves ARE
audited (`candidate_events`). But **every interview query in the module filters
`interview_date >= today`** — the KPI (`recruit.php:554-556`), the Today card (`:573-576`), the
command-centre counts — so an interview whose date has **passed** with no `interview_done_date` and no
`interview_outcome` is surfaced on **no** screen. The outcome simply never gets chased; the candidate
stalls silently. This is the module's clearest "the data is right there but nobody sees it" gap.

**Noted, deferred (flagged, not built — each is a bigger or state-changing change):**
- **`WAIVED` is dead vocabulary** — a fee status defined but never written; an agency hire who leaves
  inside the guarantee window keeps a PROVISIONAL fee forever. Resolving it means a cron that writes a
  fee state on inspector termination — a business-logic/state change to decide explicitly.
- **Confirmed placement fees never become a payable** — nothing in billing reads `placement_fee`.
- **Per-agency performance** (fill rate, time-to-fill, fees paid) — computed nowhere; a new rollup.
- **Requisition transitions un-audited** — no `requisition_events` to mirror `candidate_events`.
- **The conversion isn't transactional** and dedups only on `inspector_id` — a correctness fix that
  touches the hire write path.
- **Two recruitment landings** (`/recruitment` vs `/recruitment-cc`) and **agency vs subcon** parallel
  stores — consolidation, not additive polish.

---

## 1. Built (additive, read-only; scoped like every candidate read)

1. **`recruit_overdue_interviews($limit=50)`** — the worklist of interviews with a **past**
   `interview_date`, no `interview_done_date`, no `interview_outcome`, and a candidate **still in play**
   (not terminal/hired). Oldest first; SBU-scoped via `recruit_sbu_clause`; guarded (missing
   table/column ⇒ empty, never fatal).
2. **`recruit_overdue_interviews_count()`** — the same, as a count.
3. Surfaced in **two places that reuse the one helper**:
   - a **"Interviews awaiting an outcome"** risk group on the `/recruitment` risks board
     (`recruit_data()['r_interviews']`), including it in the board's "any risk" test;
   - a hiring-gated **tile in the home "Needs attention" band** (Module 34 `attention_summary()`),
     linking to `/recruitment`.

---

## 2. Edge cases handled

1. A past-date interview with no outcome and an in-play candidate is chased; an **upcoming** one is not.
2. An interview with a `done_date` **or** an `outcome` recorded is resolved — not chased.
3. A **terminal/hired** candidate's stale interview is not chased (only active stages).
4. Scoped like every other candidate read, so a branch user sees only their own.
5. Guarded end-to-end: a pre-migration table yields an empty list/zero, never a crash; the attention
   tile and risk card both `function_exists`-guard the helper.
6. Read-only — the existing upcoming-interview KPIs/cards are untouched; this is a new query.

## 3. Guardrails (green)

`recruit_data`, the upcoming-interview KPI/Today card, `candidate_events`, the fee/guarantee engine,
the command centre — all unchanged. No new permission (reuses `mod.hiring.view`); no schema change;
nothing deleted.

## 4. Tests

`tests/test_module35_recruitment.php` (18 assertions): exactly the past-date/no-outcome/in-play
interview is counted and listed; upcoming, done, outcome-recorded and terminal cases are correctly
excluded; the row carries the fields the card renders; the risks board and the attention band are
wired; the existing upcoming query is preserved. (Migrations are run before the test transaction, so
`req_migrate`'s ALTERs are not rolled back.) Suite 3069 passing (only the 3 pre-existing baseline
failures remain).
