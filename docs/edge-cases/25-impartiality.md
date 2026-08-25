# Module 25 — Impartiality / Conflict of Interest · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Decision: (A) familiarity is an advisory Review (threshold
setting, default 6). Additive `inspector_impartiality()` verdict + allocation pill; the
non-overridable declared-threat gate and the register are unchanged (asserted by tests in
`tests/test_module25_impartiality.php`, which also fill the gate's missing coverage). P0.

---

## 0. Headline: a register that already gates — but computes nothing

- **Allocation is already hard-gated.** An `OPEN` or `UNACCEPTABLE` threat in
  `impartiality_threats` for this inspector (against this client/vendor, or person-general)
  makes `imp_block()` refuse the job save via `pack_fire('work.assign')`. It is **non-
  overridable** — the only way past is to *decide* the threat in the register
  (MITIGATED/ACCEPTED, with mandatory safeguards). ✅ It is a gate, not just a list.
- **But it computes nothing.** The block is driven **only by human-entered rows**. Of the
  spec's seven COI signals, the app has a taxonomy (8 threat kinds) and manual free-text —
  and **no automated evaluation of any of them**. Most notably, there is **no repeated-
  assignment / rotation (familiarity) logic anywhere**, and **no person↔party relationship
  record** beyond an ad-hoc threat row.
- **No test coverage.** `imp_block()` and the whole `imp_*` spine are untested — a guardrail
  gap to fill.

So Module 25 = add the **one COI signal that is genuinely computable from data we hold
(repeated assignment / familiarity)** as an advisory verdict, mirror the existing hard block
in that verdict, surface it at allocation, and add the missing tests. It does **not** try to
capture prior-employment / consultancy / financial-quantum structurally (those are declarations
a human makes — out of scope for a computed engine and better left to the register).

---

## 1. Proposed additive layer

1. **`inspector_impartiality($inspectorId, $ctx)`** — a read-only verdict
   `['status'=>'CLEAR'|'REVIEW'|'CONFLICT', 'reasons'=>[{level,text}]]`:
   - **CONFLICT** — a declared blocking threat applies (mirrors `imp_block` exactly: an
     `OPEN`/`UNACCEPTABLE` threat, person-general or scoped to this client/vendor).
   - **REVIEW** — advisory flags a human should weigh:
     - **Repeated assignment (familiarity)** — this inspector has served this client on ≥ N
       jobs in the last 12 months (N a setting, sensible default). *This is the missing
       computed signal.*
     - **Declaration due/expired** — no current impartiality declaration on file.
   - **CLEAR** — none of the above.
   `$ctx`: client_id, vendor_id, on_date.
2. **Surface the verdict as a pill at allocation** (next to the Module 24 competence pill on
   the suggested-inspector chips): **✓ Clear / ⚠ Review / ⛔ Conflict**. Advisory — it does not
   add a new hard block (the declared-threat block remains the one authoritative stop).
3. **Fill the test gap** — behavioural tests that an `OPEN`/`UNACCEPTABLE` threat makes
   `imp_block` non-empty (the existing hard gate), that a decided threat clears it, and that
   the verdict computes CONFLICT/REVIEW/CLEAR correctly.

No new permission; the register, the decide lifecycle, the per-job declaration checkbox, and
the non-overridable `imp_block` gate are all untouched.

## 2. Edge cases

1. **No threats, current declaration, no repeat history** → CLEAR.
2. **Declared OPEN threat for this client** → CONFLICT; must match `imp_block` exactly.
3. **Threat scoped to a *different* client** → does not apply to this job → not CONFLICT
   (mirrors `imp_blocking_for` partner matching).
4. **Person-general threat** (`partner_id` NULL/0) → applies to every client → CONFLICT.
5. **Decided threat** (MITIGATED/ACCEPTED) → no longer blocking → not CONFLICT (may still be a
   REVIEW note if a review date has passed — optional, low priority).
6. **UNACCEPTABLE threat** → CONFLICT (permanent).
7. **Repeated-assignment boundary** → exactly N counts as REVIEW; N−1 does not; window is the
   last 12 months from the work date; only distinct jobs for the *same client* count.
8. **New inspector / new client pairing** → no history → not a familiarity REVIEW.
9. **No declaration on file** → REVIEW (declaration due), never CONFLICT (missing paperwork
   isn't a conflict, it's a prompt).
10. **Vendor vs client** → the familiarity signal keys on the *client* (the party whose
    independence matters); a threat may be scoped to either client or vendor.
11. **Performance** → the verdict is one small count query + one threat lookup per candidate;
    computed only for the small suggested set at allocation, not the whole dropdown.
12. **Un-migrated tables** → every probe guarded; degrades to CLEAR, never errors.
13. **Mobile** → compact pill beside the competence pill.

## 3. Guardrails / observations

- The declared-threat **hard block and its non-overridability are preserved** — the verdict is
  advisory and never relaxes the gate.
- **Observation (not changing it):** the impartiality *screen* is currently gated on
  `mod.competence.view`, not `mod.impartiality.view` (`impartiality.php:197`). Changing that is
  a permission change — I will **flag it, not touch it**, unless you ask.
- No status/transition/permission/table changed; no report/PDF touched.

## 4. OPEN DECISION — how should the familiarity (repeated-assignment) signal behave?

Today nothing flags an inspector who keeps getting the same client. The computed signal is new.

- **(A) Advisory REVIEW only (recommended):** flag "same inspector on this client N+ times in
  12 months — consider rotation" as a ⚠ Review pill; it never blocks and adds no permission.
  Keeps the coordinator's judgement (some clients genuinely need the same specialist). N is a
  setting (I'll default it to **6**).
- **(B) Also require an acknowledgement** at allocation when the familiarity threshold is hit
  (a tick, like the self-review ack in Module 07) — a soft speed-bump, still not a hard block.
  Slightly stronger; adds a small friction to every repeat assignment past the threshold.

Default if you don't specify: **(A)**, threshold 6 (configurable).

## 5. Tests

1. The existing hard gate (fills the gap): an OPEN threat → `imp_block` non-empty; a
   client-scoped threat only blocks that client; a decided (ACCEPTED) threat → `imp_block` ''.
2. `inspector_impartiality`: CONFLICT on a blocking threat; REVIEW on repeated assignment ≥ N;
   REVIEW on missing declaration; CLEAR otherwise; vendor/client scoping correct.
3. The allocation picker shows the impartiality pill; nobody is hidden.
4. No new permission; the non-overridable block unchanged.
