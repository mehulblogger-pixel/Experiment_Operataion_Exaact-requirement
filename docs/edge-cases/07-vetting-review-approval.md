# Module 07 — Vetting / Technical Review / Approval · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Decisions: 11.1→(A) soft warning+ack · 11.2→also email ·
11.3→yes. Tests in `tests/test_module07_quality_gate.php`. This module is **P0** and sits on
the segregation-of-duties controls; nothing here weakened a control — every change is
additive UX, verified by regression assertions.

---

## 0. Scope & non-destructive guarantees

**This module makes the existing quality gate legible — it does not change how it
works.** No status, transition, permission, guard, or button behaviour is altered.

### The hard control that must NOT be weakened
- **Issuer ≠ approver** is server-enforced at `idems.php:4472-4477` (finalize refuses if
  the current user approved the report) and mirrored in the UI (`idems.php:3528-3529`,
  Finalize button hidden from the approver). Master is the standing one-person-office
  exception. **Preserve exactly.** My UX will *explain* this, never bypass it.
- **Return reasons are mandatory** on reject (`idems.php:5763`), send-back (`:5769`) and
  vetting-return (`:5861`). **Preserve.**
- The completeness gate (`idems_completeness_check`) and issue-time gates (QA-critical,
  uncalibrated-instrument, RN blockers) **preserve** their block/override behaviour.

### Non-destructive guarantees
- **Add** read-only UI: a provenance strip, a return-reason banner, a consolidated
  readiness panel, clearer status/next-action labelling. **Add** no new route that changes
  state. **Introduce no new permission.**
- **Do NOT** touch `ops_idems_vet`, `ops_idems_approve`, `document-submit`,
  `document-finalize`, the approval-chain builder, or any gate's pass/fail logic.

---

## 1. Grounding (verified against the code)

- **Statuses:** DRAFT · SUBMITTED · VETTING · UNDER_REVIEW · APPROVED · ISSUED · REJECTED
  (**displayed "Sent back"**) · ARCHIVED (vestigial, never written). Vet sub-state:
  VETTED / RETURNED / DEBRIEFED. Approval-step status: PENDING / APPROVED / REJECTED /
  SENTBACK / DELEGATED.
- **Two "returned to inspector" paths, both land at DRAFT** (already surfaced in Module 39):
  approver **send-back** (`report_approvals.status=SENTBACK`) and vetting **RETURNED**
  (`vet_status=RETURNED` + `report_vetting.note`). A **reject** keeps `status=REJECTED`.
- **Who-did-what is stored** (scattered on screen today): preparer `report_docs.inspector_id`;
  vetter `vet_by/vet_at` + `report_vetting`; approver(s) `report_approvals` + `approved_by`;
  issuer `finalized_by/finalized_at`.
- **Return reasons are stored & mandatory** (`report_approvals.remarks`, `report_vetting.note`)
  but shown **only on the report's own detail panels** — **no email/alert on return.**
- **Gates already return structured reasons:** `idems_completeness_check` (18 checks, each
  PASS/FAIL/NA), `idems_rn_blockers`, plus issue-time QA-critical & instrument/signer hooks.

---

## 2. Proposed additive UX (so edge cases are concrete)

1. **Provenance strip** — one compact block at the top of the report detail:
   **Prepared → Vetted → Approved → Issued**, each showing name + date, or "pending" /
   "not required", in workflow order. Pure read of existing data.
2. **Return-reason banner** — when a report is REJECTED or was returned to DRAFT, a
   prominent banner (for the inspector especially) quoting *the latest* reason, who wrote
   it, when, and via which step (vetting vs approval). Data already stored & mandatory.
3. **Readiness panel** — a consolidated "what's still blocking this" view driven by the
   existing gate functions: at submit → `idems_completeness_check`; at issue → approval
   complete + QA-critical + instrument/signer + (for RN) `idems_rn_blockers`. Read-only.
4. **Status & next-action clarity** — a one-line "current state → who it's waiting on →
   what's next", plus disambiguating the REJECTED="Sent back" label collision.
5. **Segregation visibility** — where the Finalize button is hidden because the viewer is
   the approver, say so ("You approved this — a different person must issue it").

---

## 3. Provenance strip — edge cases

1. **Vetting not required** (gate off / RN type auto-routes to review) → show "Vetting —
   not required", not an empty/failed slot.
2. **Step still pending** → "Approved — pending" with the current step's expected actor if
   known (from `report_approvals` resolved user/role); never a blank.
3. **Multi-step approval chain** → the strip shows the *chain outcome* (approved / in
   progress at level N of M) and links to the full existing Approval-chain panel; it does
   not duplicate the whole chain.
4. **Delegated step** → show the delegate as the actor, marked "(delegated)".
5. **Legacy report** with only `approved_by` name and no `resolved_user_id` → show the
   name; do not error on the missing user id.
6. **Master self-approved / small office** → strip shows the same person in two slots
   truthfully (e.g. Approved & Issued by same master) — that is the documented exception,
   shown honestly, not hidden.
7. **Report reset to DRAFT after a return** → prior vetted/approved slots must reflect that
   the chain was cleared (a send-back deletes/So the strip shows "returned", not a stale
   "approved"). Edge: do not show a stale approver on a report that bounced back to DRAFT.
8. **RN vs inspection report** → provenance applies to both; RN has its own issue actor.
9. **Deleted / archived** report → strip still renders from stored fields; no fatal.

## 4. Return-reason banner — edge cases

1. **Which reason wins** — the *latest* return event: compare the most recent
   `report_vetting` RETURNED note vs the most recent `report_approvals` SENTBACK/REJECTED
   remark by timestamp; show that one, labelled with its source ("Returned at vetting" /
   "Sent back by approver" / "Rejected by approver").
2. **REJECTED vs DRAFT-reset** — banner appears for both; wording differs ("Rejected —
   revise and resubmit" vs "Returned for correction").
3. **Cleared on resubmission** — once the inspector resubmits (status leaves DRAFT /
   REJECTED), the banner must disappear; the reason stays in history panels.
4. **Multiple prior returns** — banner shows the *current* reason; a "history" affordance
   links to the existing vetting log / approval chain for older ones.
5. **Audience** — the banner is most prominent for the preparer; other roles see a neutral
   "this report was returned for correction on <date>" (no accidental leak of internal
   remarks beyond who can already see the detail — same visibility as the existing panel).
6. **Empty/whitespace reason** — impossible (mandatory server-side), but the banner must
   degrade gracefully if a legacy row has an empty note ("no reason recorded").
7. **No proactive push today** — see §11 open decision (email/notification on return).

## 5. Readiness panel — edge cases

1. **Submit-time vs issue-time are different gate sets** — the panel must show the set
   relevant to the *current* status: DRAFT/REJECTED → completeness checks; APPROVED → issue
   gates (QA-critical, instruments, signer, RN blockers if flagged). Never mix them.
2. **NA vs FAIL** — reuse the existing PASS/FAIL/NA exactly; an NA check (e.g. location not
   applicable) is not a blocker and must render as neutral, not red.
3. **Master override** — where the existing code allows a master to override with a recorded
   reason (submit gate; QA-critical), the panel shows the override path but the reason stays
   mandatory; a non-master sees a hard block with no override button.
4. **Uncalibrated instrument at issue** — non-overridable block (existing) → panel shows it
   as hard, even for master. Do not imply it can be overridden.
5. **Unauthorised signer** — existing behaviour is warn + auto-NCR, not a block → panel
   shows a warning, not a blocker.
6. **Pending approval step** — "waiting on <approver>" is a readiness item at issue, sourced
   from `report_approvals`, not a completeness re-check.
7. **Counts** — reasons like "N activities not completed" already carry the N; render as-is.
8. **The panel changes nothing** — it reflects the gate result; pressing Submit/Issue still
   calls the unchanged handler which re-evaluates authoritatively (panel is advisory mirror).

## 6. Status & next-action clarity — edge cases

1. **REJECTED shows "Sent back" collision** — REJECTED (resubmit) and send-back-to-DRAFT
   both read "sent back" today. Disambiguate labels **without changing the stored status**:
   REJECTED → "Rejected — revise & resubmit"; DRAFT-after-return → "Returned for correction".
   (Label-only; `IDEMS_STATUS` value unchanged for compatibility.)
2. **Vestigial ARCHIVED** — never written; do not add it to any user-facing picker, but keep
   the constant so old data/labels don't break.
3. **Next-action owner** — "Under review — waiting on <approver>"; "At vetting — with
   <vetter authority>"; "Approved — waiting to be issued (not you — you approved it)" for the
   approver. Derive from status + report_approvals; never assert a person the data doesn't have.
4. **Delegated** — next-action owner reflects the delegate.
5. **Master exception** — where master can both approve and issue, the next-action line must
   not falsely say "waiting on someone else".

## 7. Segregation-visibility — edge cases

1. **Approver viewing an APPROVED report** — Finalize is correctly hidden (existing). Add a
   one-line explanation ("You approved this report — a different person must issue it").
   Never render a disabled-looking Finalize that 500s; keep it hidden + explained.
2. **Master** — sees Finalize (documented exception); explanation not needed, or a subtle
   "acting as administrator" note.
3. **Front-half self-vet / self-approve gap** (see §11.1) — today a user who is both the
   inspector and a finalize-holder could vet/approve their own report; there is no hard
   person-inequality guard on the front half. Any visible warning must match whatever the
   §11.1 decision is — do not imply a block that isn't there, and do not add a block without
   the decision.

## 8. Every button — edge cases (preserve behaviour, clarify presentation)

For each existing control, the edge matrix is: **shown-when correctness · reason-mandatory
preserved · hidden-not-disabled · confirm dialog intact · double-submit/idempotency ·
stale-status between render and click**. Buttons (from the map):

| Button | Route | Must stay true |
|---|---|---|
| Submit for review | `/document-submit` | shown only DRAFT/REJECTED & editable; blocked unless completeness ok (master override reason mandatory) |
| Vet cleared / Return / Debrief | `/document-vet` | shown only to vetter, not finalized; **Return note mandatory** |
| Approve / Send back / Reject / Delegate | `/document-approve` | shown only to the current-step actor; **send-back & reject remark mandatory**; delegate needs a target |
| Finalize & issue | `/document-finalize` | hidden from the approver (SoD); QA-critical override reason mandatory; instrument block non-overridable |
| RN flag / Draft RN / Reissue revision | `/document-rn-flag`, `/document-release-note`, `/document-revise` | RN blocked while `idems_rn_blockers` non-empty; revise creates a NEW revision, never overwrites the issued one |

Edge cases common to all:
- **Stale status** (status changed between page render and click) → the handler re-checks and
  refuses safely; my UI must not assume the rendered status is still current.
- **Double-click / resubmit** → handlers are status-guarded (e.g. can't submit a non-DRAFT);
  no new double-submit window is introduced.
- **Hidden vs disabled** → controls the user may not use are hidden (existing pattern); I keep
  that, adding an explanation only where the absence is confusing (Finalize for approver).
- **No new button changes state** — everything I add is read-only display.

## 9. Permissions & data edge cases

1. **No new permission.** `idems.finalize` already does double duty (vetting authority AND
   issuer) — I will *surface* this fact in the provenance/segregation copy but not change it.
2. **Legacy reports** (pre-chain, only `approved_by` name) render without error.
3. **Deleted (`deleted=1`)** excluded from any new query, as elsewhere.
4. **Actor name missing** (blank `acted_by`) → "unknown", never a fatal.
5. **RN type** reports flow through the same provenance/readiness with RN-specific gates.

## 10. Mobile / field & backward-compat

1. Inspector is phone-first: the **return-reason banner** and **provenance strip** must read
   top-of-screen on a phone; the readiness panel collapses.
2. All existing panels (Approval chain, Vetting & debriefing, completeness anchor) remain;
   new elements sit above/beside them, not replacing them.
3. No route, status, permission, PDF, or template touched.

---

## 11. OPEN DECISIONS — I need your call before building

**11.1 — Front-half segregation gap (the important one).**
There is **no hard "the person who vetted/approved cannot be the report's preparer" guard**
today; the front half relies on role separation. A user who is both an inspector and holds
`idems.finalize` could vet/approve their own report. Options:
  - **(A) Soft, visible warning (recommended, non-destructive):** if the current user is the
    report's preparer, show "You prepared this report — vetting/approving your own work
    breaks segregation of duties" and require a typed acknowledgement, but do not block.
    Preserves the master/small-office exception and changes no permission.
  - **(B) Hard block** (approver/vetter ≠ preparer, master exempt): stronger, but this
    **changes who-can-do-what** → I'd update `docs/` in the same commit and it may break a
    legitimate one-person-office flow. Needs your explicit yes.
  - **(C) Visibility only:** just show who prepared it in the provenance strip; add no
    warning or block.
  Default if you don't say: **(A) soft warning.**

**11.2 — Notify the inspector on return?**
Today reject/send-back/vetting-return fire **no email** — the inspector learns only by
opening the report (now also via My Work). Add a notification on return (email + in-app),
like the existing "approval required" alerts? Default: **in-app banner + My Work only**
(already covers it); add email **only if you want it**.

**11.3 — Status label disambiguation.**
Relabel REJECTED as "Rejected — revise & resubmit" and DRAFT-after-return as "Returned for
correction" (display only, stored status unchanged). Default: **yes, do it** (pure clarity).

---

## 12. Tests to write (before marking done)

1. Provenance strip renders correct slots for: not-yet-vetted, vetted, in-review, approved,
   issued, returned-to-draft, delegated, legacy (name-only), RN, master self-approve.
2. Return-reason banner shows the *latest* reason with correct source label; disappears after
   resubmission; degrades on empty legacy note; correct for REJECTED vs DRAFT-reset.
3. Readiness panel shows the submit set vs the issue set by status; NA≠FAIL; master-override
   path shown only to master; instrument block shown as non-overridable.
4. Segregation: the issuer≠approver guard still refuses (unchanged) and the UI explains the
   hidden Finalize; §11.1 behaviour matches the chosen option.
5. No new permission constant; no transition/gate function modified (diff-scoped assertion).
6. Every button's shown-when and reason-mandatory conditions unchanged (regression).
