# Module 04 — Calls (one user-facing lifecycle) · Edge-case analysis (pre-build)

**Status:** edge-cases drafted — **awaiting your go + one decision (§4) before code.** P1.
Additive; both status systems and the R6 transition rules are preserved.

---

## 0. Headline: the lifecycle exists — stop leaking the raw one

Calls carry **two status columns**:
- **Legacy `calls.status`** — live values `OPEN → FORWARDED → ALLOCATED` (`CLOSED` is seed-only);
  no label map.
- **Operational `calls.op_status`** — `CALL_STATUSES` (15 values) = **exactly the spec's
  lifecycle** (Received → … → Closed, + On hold / Rejected / Cancelled, + Draft), governed by
  the R6 transition rules.

They are **not synced**; `op_status` is set independently, and when empty is **derived one-way**
from legacy by `tosrm_derive_status()`. The helper **`tosrm_call_status($call)` already returns
the single lifecycle value** (op_status if set, else derived) and `CALL_STATUSES` gives its
label. **So the one user-facing lifecycle already exists in code — it just isn't shown.**

**The actual problem (presentation):**
1. **The call detail Overview prints the RAW legacy status** (`$call['status']` →
   `OPEN`/`FORWARDED`/`ALLOCATED`) to **every** user (`call_detail.php:219`). That's the leak the
   spec says to fix ("show system status only where needed for administrators").
2. **The register shows a job-count-derived 3-state** (Closed / To schedule / In progress),
   ignoring the real lifecycle.
3. The proper lifecycle label appears **only** in the manager-gated TOSRM panel.

So Module 04 = present the already-existing `tosrm_call_status` lifecycle as the single
user-facing status; keep the raw legacy value for admins only. **No new status values, no
sync-writes** (in scope A).

---

## 1. Proposed additive layer (recommended = §4-A)

1. **`call_status_label($call)`** — one source of the user-facing lifecycle label:
   `CALL_STATUSES[tosrm_call_status($call)] ?? …`, plus a tone/pill mapping.
2. **Call detail Overview** — replace the raw-legacy `Status` line with the unified lifecycle
   label. Show the **raw legacy value only to admins/masters** ("system: FORWARDED"), so it's
   still diagnosable but not leaked to normal users.
3. **Register** — show the unified lifecycle status pill per row (additively — the existing
   header chips / job-count derivation that drive "To schedule / overdue" counts stay, so those
   tests don't move).

Reuses `tosrm_call_status`, `tosrm_derive_status`, `CALL_STATUSES`. No new permission; the R6
transition rules, the TOSRM panel, the playbook, the nowband, and the register chips are
untouched.

## 2. Edge cases

1. **`op_status` empty (most legacy calls)** → `tosrm_call_status` derives from legacy
   (`OPEN→Received`, `FORWARDED→Ready for scheduling`, `ALLOCATED→Assigned`, `CLOSED→Closed`);
   the label must render, never blank.
2. **`op_status` set** → it wins over the derived value (the manager set it deliberately).
3. **Terminal states** (Rejected / Closed / Cancelled) → shown as such; no "next" implied.
4. **Admin view** → sees both the lifecycle label and the raw legacy value; a normal user sees
   only the lifecycle label.
5. **Unknown/legacy value** → falls back to the raw string rather than a blank or a fatal.
6. **Register header chips** (To schedule / in progress / overdue) → unchanged; the new pill is
   additive so `test_pending_scheduling` / `test_simplify_scheduling` stay green.
7. **The nowband + order playbook** (the "what next" prose) → untouched; they remain the
   coordinator's action guide.
8. **Performance** → the label is a pure per-row computation; no extra query.
9. **Mobile** → one compact pill.

## 3. Guardrails (must stay green)

- `test_tosrm` (op_status added, legacy untouched, `OPEN→RECEIVED` derive, lookup options) —
  untouched; I don't change derivation or the enum.
- `test_call_status_transitions` (R6 rules) — untouched; no transition logic changes.
- `test_tosrm_authz` (writes gated) — untouched; I add no writes (scope A).
- `test_call_allocation_scope` / `_carry_forward` / `_coordinator` / `_from_contract` — the
  legacy flow is unchanged.

---

## 4. OPEN DECISION — present only, or also sync the two systems?

- **(A) Present the unified status; raw legacy for admins only (recommended, presentation-only):**
  use `tosrm_call_status` (which already derives when `op_status` is empty). No writes to either
  status column; zero change to the legacy forward/allocate flow or the R6 rules. Lowest risk;
  delivers "one user-facing lifecycle, system status for admins."
- **(B) Also write `op_status` on the legacy transitions** (set `op_status` when a call is
  forwarded/allocated, so it's always populated rather than derived). Makes `op_status`
  authoritative, but it **changes the legacy write path** and must respect the R6 transition
  rules — higher risk, and the derive-when-empty bridge already makes it unnecessary for display.

Default if you don't specify: **(A)**.

## 5. Tests

1. `call_status_label` returns the lifecycle label for: op_status set; op_status empty (derived
   from each legacy value); terminal states; unknown value → raw fallback.
2. The call detail shows the unified label; the raw legacy value is admin-only.
3. The register shows the unified status pill; the header chips are unchanged.
4. No new permission; no writes to `status`/`op_status`; R6 rules untouched.
