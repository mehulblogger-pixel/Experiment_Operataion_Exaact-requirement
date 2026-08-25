# Module 21 — Hold / Witness Points · Edge-case analysis (pre-build)

**Status:** edge-cases drafted — **awaiting your go + one decision (§4) before code.** P0
(touches whether work can proceed). Additive; the existing model and the one hard gate stay.

---

## 0. Headline: the system exists and is partly enforced

Hold/Witness points are already a real, well-modelled subsystem (`hw_points` table; types
HOLD / WITNESS / REVIEW / CLEARANCE; statuses OPEN / CLEARED / WAIVED / CANCELLED; auto-derived
from report activities on issue **and** entered by hand; idempotent; audited).

**Enforcement today:**
- **Release Note — HARD gate** (`idems_rn_blockers` → `ops_idems_release_note`): open points
  block raising an RN, master override only. ✅
- **Report submit — gated** via the completeness check's "Hold / Witness status" item.
- **Report issue — NOT gated** (explicitly "never blocks issue").
- **Job close — NOT gated** (the close handler never consults hold/witness).
- **Despatch — no system action** (the "do not despatch" text is advisory).

**Surfaces today:** job detail (the fold) ✅, job blockers header ✅ (Module 05), report release
checklist ✅, RN blockers ✅, manager register `/hold-points` ✅, quality nav badge ✅.
**Missing surfaces:** schedule board ❌, prominent inspector-completion warning ❌, call detail ❌.

So Module 21 is: **surface where they're missing** + **decide the enforcement level** — not
rebuild.

---

## 1. Proposed additive layer (recommended)

1. **`hwp_job_summary($jobId)`** — one reusable helper returning open counts by type + total
   (callers currently loop `hwp_for_job` by hand; `ops.php:1624`, `job_detail.php:625`).
2. **Schedule board** — a small ✋ badge / count on any job (or day) that has open hold/witness
   points, so scheduling and reshuffling see the constraint. Read-only.
3. **Inspector-completion warning** — a prominent notice at the points where an inspection could
   be *accidentally completed* with an open point: **marking a visit/day complete**, **site
   check-out**, and **job close**. Wording: "This job has N open hold/witness point(s) — the
   manufacturer should not proceed/despatch until they are cleared or waived." Behaviour set by
   §4.
4. **Call detail** — show whether the call's job(s) carry open points (a light indicator).

No new permission; no change to the `hw_points` model, the job fold, the register, or the RN
gate. Reuses `hwp_for_job` / `hwp_open_count` / `hwp_type_label` / `hwp_can_edit`.

---

## 2. Edge cases

1. **No points / all cleared or waived** → no badge, no warning; summary total 0.
2. **Mixed statuses** → only OPEN counts as a blocker/badge; CLEARED/WAIVED/CANCELLED don't.
3. **Auto vs manual origin** → both count identically for surfacing (the `source` flag is not a
   filter here).
4. **The RN hard gate is untouched** → open points still block the Release Note (master
   override) exactly as now; §4 only concerns *additional* surfaces/gates.
5. **Field inspector** → sees the completion warning (it's their action); the schedule badge is
   a coordinator surface. No money/internal leakage.
6. **Multi-day job** → the day-completion warning fires per the job's open points (not per day,
   since points are job-scoped today); badge shows on the job row.
7. **Deputation job** → same helper; deputation completion paths get the same warning if open
   points exist.
8. **Master override** → wherever a hard block exists (RN today; job-close/issue only if §4-B),
   master may proceed, audited — consistent with existing overrides.
9. **Helper/table missing** (un-migrated) → `hwp_missing_table` already makes reads safe; the
   summary returns zeros and nothing renders.
10. **Performance** → `hwp_job_summary` is one query per job; the schedule board badge must not
    run N queries per row — batch or cache (one `hwp_open_all` grouped by job_id for the board).
11. **Mobile** → the completion warning is top-of-screen, single-column, unmissable.

## 3. Guardrails (must stay green)

- `test_job_detail_declutter` pins the `#holdpoints` fold structure — untouched.
- `test_rn_blockers` pins the RN gate — untouched.
- `test_module05_job360` pins the fold's presence and the job header — untouched.
- Quality nav route `/hold-points` — untouched.

---

## 4. OPEN DECISION — enforcement level (the "first-class control" question)

Today open points hard-block only the **Release Note**. Your spec says *"No accidental
completion of a blocked inspection."* How far should that go?

- **(A) Advisory + prominent warnings (recommended, non-destructive):** add the schedule badge
  and unmissable warnings at day-complete / check-out / job-close, but **do not add new hard
  blocks**. The RN gate remains the one hard stop. Preserves legitimate flows (e.g. issuing a
  daily report while a later-stage hold point is still open) and changes no permission.
- **(B) Also hard-block job close** when open HOLD/WITNESS points exist (master override): a
  stronger control. This **changes who-can-do-what** (a coordinator can't close a job with an
  open point) — I'd update `docs/` in the same commit, and it may block a legitimate close
  where the open point belongs to a *later* scope. Report *issue* would stay ungated (a daily
  report must still be issuable), unless you want that gated too.

Default if you don't specify: **(A)** — surface hard, warn loudly, keep the RN as the hard gate.

## 5. Tests

1. `hwp_job_summary`: correct open-by-type counts; zero when none/all cleared; safe on missing
   table.
2. Schedule board shows a badge only for jobs with open points; batched (no per-row query
   storm).
3. Completion paths (day-complete / check-out / job-close) render the warning when open points
   exist; none when clear; §4 behaviour matches the chosen option.
4. Regression: RN hard gate, the job fold, and the nav route unchanged; no new permission.
