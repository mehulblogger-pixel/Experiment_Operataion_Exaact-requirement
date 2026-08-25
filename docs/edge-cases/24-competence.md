# Module 24 — Competence (eligibility at allocation) · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Decision: (A) verdict mirrors the gate; trade/SBU advisory.
Additive `inspector_eligibility()` verdict + allocation-picker pill; the existing hard gate,
override authority and enforcement toggle are unchanged (asserted by tests in
`tests/test_module24_competence.php`). P0.

---

## 0. Headline: the gate exists — the *verdict* is what's missing

- **Allocation is already gated.** Saving a job with an inspector fires
  `pack_fire('work.assign')` (`ops.php:5490-5533`): a **lapsed mandatory certificate on the
  work date hard-blocks the save** (manager override with a recorded reason). An
  authorisation-scope gate also blocks, but only when enforcement is switched **on** (default
  off). Impartiality + site-doc + double-booking gates fire in the same place. ✅
- **Advisory for an assigned job** — `tosrm_competence_warn($job)` fuses certs + authorisations
  into error/warn signals. But it runs *after* assignment.
- **What's missing:** a single **per-(inspector × job) verdict** — ELIGIBLE / EXPIRING / CHECK
  / BLOCKED — shown **while choosing** the inspector (the hard block currently only appears on
  submit). And `inspectors.skill_ids` / `sbus` / `sbu` are stored but **used by no gate or
  ranking** — ready-made advisory inputs.
- **Guardrail gap:** the competence spine (`competence_lapsed`, `auth_covers`, `auth_block`,
  `auth_run_maintenance`) has **no automated behavioural test** — only a manual guide. Module
  24 should add that coverage.

So Module 24 = a unified **eligibility verdict** that *predicts the existing gate* + surfaces
advisory signals, shown at the point of assignment — not a new blocking engine.

---

## 1. Proposed additive layer

1. **`inspector_eligibility($inspectorId, $ctx)`** — read-only verdict
   `['status'=>'ELIGIBLE'|'EXPIRING'|'CHECK'|'BLOCKED', 'reasons'=>[...]]`, fusing the signals
   that already exist:
   - **BLOCKED** — a lapsed **mandatory** cert on the work date (mirrors the real hard gate),
     or authorisation not covering the scope **when enforcement is on**.
   - **EXPIRING** — a cert expiring soon (≤45d), or an authorisation review/witness due.
   - **CHECK** — advisory mismatches: the job's `req_trade_id` ≠ the inspector's trade, or the
     job/call SBU outside the inspector's `sbus`/`sbu` (today unused). Never a hard stop.
   - **ELIGIBLE** — none of the above.
   The verdict *reflects* the gate; it does not become a second gate.
2. **Surface the verdict at the assignment picker** (`job_form.php` dropdown + the "Suggested"
   chips) — a per-candidate pill **✓ Eligible / ⏳ Expiring / ⚠ Check / ⛔ Blocked** so the
   coordinator sees it *before* submit, not only as a save error. Nobody is hidden (matches the
   current never-filter rule).
3. **Fill the test gap** — behavioural tests for the verdict and for the existing hard gate
   (lapsed mandatory cert blocks; override records a reason; non-mandatory cert does not block).

No new permission; the `pack_fire('work.assign')` gate, the override authority, and the
enforcement toggle are untouched.

---

## 2. Edge cases

1. **No certs at all** → no cert block; verdict driven by trade/SBU (CHECK) or ELIGIBLE.
2. **Only non-mandatory certs lapsed** → **not** BLOCKED (only mandatory certs gate) — must
   match the real gate exactly, else the pill lies.
3. **Cert expiring vs lapsed on the *work date*** → the verdict uses the job's work date
   (`competence_job_date`), not "today", so a cert valid today but lapsed on a future
   inspection date reads correctly.
4. **Authorisation enforcement OFF (default)** → an uncovered scope is **CHECK/advisory**, not
   BLOCKED — mirrors `auth_block` returning '' when not enforced.
5. **Authorisation enforcement ON** → uncovered scope is BLOCKED (matches the gate).
6. **Job has no `req_trade_id`** → no trade-mismatch signal (can't mismatch an unspecified
   discipline).
7. **Inspector has no trade set** → trade signal is neutral (unknown), not a false CHECK storm.
8. **No job context (inspector profile)** → verdict runs on certs/authorisations only (no
   trade/SBU/work-date), so the profile can show an overall standing.
9. **Master override** → the pill shows BLOCKED, but the existing save still lets a manager
   override with a reason; the pill must not imply it's un-overridable.
10. **Subcon / freelancer** (`staff_kind`) → same verdict; no special-casing unless a signal
    genuinely differs.
11. **Performance** → the picker computes a verdict per candidate (N inspectors). Batch the
    inputs (certs/authorisations per inspector in as few queries as possible); do not run
    per-candidate N+1 queries in the dropdown loop.
12. **Un-migrated / missing tables** → every probe guarded; verdict degrades to ELIGIBLE/CHECK
    rather than erroring (consistent with `hwp_missing_table`-style safety).
13. **Mobile** → the pill is compact; the picker stays usable on a phone.

## 3. Guardrails (must stay green)

- `test_discipline_alloc` — nobody is hidden from the picker; trade matches ranked first;
  `req_trade_id` saved; double-booking + override columns intact. The verdict must **annotate**,
  never filter.
- `test_call_allocation_scope` — allocation office rights unchanged.
- `test_job_screen_simplify` — the competence panel when `auth_enforced()`.
- The `pack_fire('work.assign')` block/override behaviour — unchanged.

---

## 4. OPEN DECISION — how strict is the verdict?

Today the hard gate blocks a **lapsed mandatory cert** (and authorisation only if enforcement
is on). Trade/SBU are **not** gated. Should the new verdict change that?

- **(A) Verdict mirrors the existing gate; trade/SBU are advisory "Check" only (recommended):**
  BLOCKED means exactly what the save will block on; a wrong-discipline or out-of-SBU pick is a
  loud ⚠ Check, not a block. Non-destructive; changes no permission or enforcement; keeps the
  coordinator's judgement (e.g. a multi-skilled inspector the trade field doesn't capture).
- **(B) Also hard-block a discipline mismatch** (job `req_trade_id` ≠ inspector trade, manager
  override): stronger, but it **adds a new hard control** on data (`trade_id`) that is currently
  soft — and trade is a single field that often under-describes a multi-skilled inspector, so it
  may block legitimate assignments. I'd update `docs/` and the allocation gate if you choose it.

Default if you don't specify: **(A)**.

## 5. Tests

1. `inspector_eligibility`: ELIGIBLE (clean); EXPIRING (cert ≤45d); BLOCKED (lapsed mandatory
   cert on the work date); CHECK (trade mismatch / out-of-SBU); authorisation enforced vs off.
2. Matches the real gate: a lapsed **mandatory** cert blocks the job save; a non-mandatory one
   does not; override records a reason (fills the current coverage gap).
3. The picker shows the verdict pill and hides nobody (regression on `test_discipline_alloc`).
4. No new permission; the enforcement toggle and override authority unchanged.
