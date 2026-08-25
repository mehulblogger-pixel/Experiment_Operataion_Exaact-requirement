# Module 08 — Report Release / Issue · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Additive read-only readiness preview; all controls preserved (asserted by tests in `tests/test_module08_issue_readiness.php`). **P0.** No status/transition/gate/permission changed.

---

## 0. Scope & non-destructive guarantees

The issue gate already works and is strict. This module makes it **legible before the
click**: a consolidated **"Ready to issue"** panel that previews the very gates the
finalize handler enforces, plus clear **immutability / revision** wording. It changes
nothing about how issuing works.

### Controls preserved exactly (verified against `idems.php:4467-4591`)
- Permission `idems.finalize`/master (`:4470`).
- **Issuer ≠ approver** (`:4474`), master exempt.
- Approval chain must be complete when one exists (`:4480`), master exempt.
- **QA critical** findings block; master override needs a recorded reason (`:4487-4501`).
- **Uncalibrated instrument** = non-overridable block; **unauthorised signer** = warning +
  auto-NCR, via the `document.issue` pack hook (`:4511-4530`).
- On issue: immutable lock, signature snapshot, content seal, presentation freeze
  (`:4532-4536`). Revision creates a NEW report (`document-revise`), never overwrites.

### Non-destructive guarantees
- **Add** `idems_issue_readiness($doc)` — a **read-only** aggregation of the above checks
  (each wrapped in try/catch; `pack_fire` is a pure evaluator — it returns block/warn and
  the NCR side-effects live in the handler, not the hook).
- **Add** a "Ready to issue" panel + immutability/revision copy on the report detail.
- **No** new route that changes state, **no** new permission, **no** change to the finalize
  handler, the QA engine, or the pack hooks.

---

## 1. Proposed additive UX

1. **"Ready to issue" panel** — shown when a report is `APPROVED` and not yet finalized (the
   issue decision point), to whoever may finalize. Lists each gate as ✓ ok / ⚠ warn /
   ⛔ block with its reason, and an overall "ready / not ready" line. Read-only preview of
   the same checks the Finalize button will run.
2. **Segregation line in the panel** — if the viewer approved it (and isn't master), an
   explicit ⛔ "You approved this — a different person must issue it."
3. **Immutability / revision clarity** — "Issuing locks the report permanently; a later
   correction is a new revision, never an overwrite of the issued version."

---

## 2. Readiness panel — edge cases

1. **No approval chain yet** → "Approval — submit for approval first" (warn, not a false ✓).
2. **Chain still in progress** → ⛔ "Still going through the approval chain".
3. **Fully approved** → ✓.
4. **QA engine error** → fail-open: the QA row shows ✓/omitted, never a hard error (matches
   the handler's fail-open at `:4488`).
5. **QA critical present** → ⛔ with the first issue title + "an administrator can override
   with a reason" (mirrors the handler; the panel does not itself override).
6. **Uncalibrated instrument** → ⛔, shown as non-overridable (matches `:4513`).
7. **Unauthorised signer** → ⚠ warn (not a block; issue proceeds and raises an NCR — matches
   `:4518`). The panel must not present it as a hard block.
8. **Accreditation pack OFF** (non-accredited business) → the instrument/signer row shows ✓
   "not applicable"; `pack_fire` returns empty and nothing blocks (matches the design).
9. **Issuer = approver (viewer-specific)** → ⛔ line for that viewer; **master** viewer does
   not see the block (documented exception).
10. **Already finalized / ISSUED** → panel is replaced by the existing "locked & issued"
    banner; the readiness panel does not show for an issued report.
11. **Not-yet-approved statuses (DRAFT/VETTING/UNDER_REVIEW)** → the panel does not show at
    all (the submit-time completeness panel already covers those). No premature QA/instrument
    firing on a draft.
12. **RN (Release Note) type** → its own `idems_rn_blockers` gate still governs the RN screen;
    the issue panel does not duplicate it.
13. **Stale status** (status changes between render and click) → the panel is advisory; the
    finalize handler re-evaluates authoritatively and remains the source of truth.
14. **Performance** → readiness runs a handful of pure checks only for an APPROVED report the
    viewer can finalize — not on every report view.
15. **Accessibility / mobile** → ✓/⚠/⛔ carry text labels (not colour alone); single-column
    on a phone.

## 3. Immutability / revision — edge cases

1. Copy appears only where relevant (near the finalize action / in the panel), not on issued
   reports (they already show the locked banner).
2. The existing "Reissue as new revision" action is unchanged; the copy just explains it.
3. No wording implies an issued report can be edited in place.

## 4. Backward-compat & data

- Legacy reports (no chain, name-only approver) render the panel without error.
- Deleted/archived excluded as elsewhere.
- No permission, route, status, PDF, or template touched.

## 5. Tests

1. `idems_issue_readiness` returns ok/block/warn correctly for: no chain, chain-in-progress,
   approved-clean, QA-critical present, and reflects overall ready flag.
2. QA-engine exception → fail-open (no fatal).
3. Issuer=approver viewer → a block item; master viewer → no such block.
4. Panel shows only for APPROVED & not-finalized; hidden for DRAFT and for ISSUED.
5. No new permission constant; finalize handler unchanged (diff-scoped).
