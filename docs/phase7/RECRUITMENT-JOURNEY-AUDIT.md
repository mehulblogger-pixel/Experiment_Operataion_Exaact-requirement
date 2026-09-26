# Recruitment journey — six findings

**Date:** 2026-09-26 · Raised by the owner after walking the candidate journey.
**Full write-up:** https://claude.ai/code/artifact/4363f07e-91f7-4e3c-8c2c-5a65e77f3036

Each point was checked against the running code, not the screenshots. All six
are real; two are worse than they appeared; two are already fixed.

| # | Complaint | Verdict | Evidence |
|---|---|---|---|
| 1 | Add candidate saves with no further input | Confirmed | `views/ops/candidate_form.php` — the submit button is placed after `</div>` closing `data-tabs`, i.e. **outside all four panes**, so it is live while three tabs are unseen |
| 2 | The fixed Overview/Pipeline/Interviews tabs fight the workflow | Confirmed, worse | `views/ops/candidate_detail.php` has 8 `data-tab` sections **plus** the configured stage strip. The pipeline is configurable; the screen is not |
| 3 | CTC should build the salary structure | Confirmed, missing | `comp_compute()` / `sal_save()` run **bottom-up only** (each FIXED component typed by hand). No top-down CTC solver exists |
| 4 | Budget at requisition *and* approval | Confirmed, sharpest | `views/ops/hiring_request.php` captures **no money field at all**. `budgeted_cost` lives on the requisition, created *after* approval |
| 5 | Manual "same person" tick is weird | Confirmed, cheap | `cand_dupes()` (lib/recruit.php:573) already scores 96 same-mobile / 94 same-email / 72 same-name. The UI discards the score and asks regardless |
| 6 | Blank approved commercial accepted | Confirmed — **FIXED** | See commit `b309485` and `tests/test_commercial_approval_guard.php` |

## The one cause

Every capability was built well and built separately; nobody owned the journey
through them. In four of the six cases the engine already does the right thing
and is simply not being asked — point 5 is the clearest, where the confidence
score is computed and then thrown away three lines later.

## Corrections made to my own first reading

- Point 6 is a **validation** defect, not a security one. CSRF is enforced
  globally at `index.php:1161`, so the handler was never unprotected.
- A zero-rate approval could never actually be **billed**:
  `assignment_billing_packet()` independently checks `appr['rev'] > 0`. What was
  broken was the record and the green "Approved" assurance, not the invoice.

## Recommended order

1. Cost on the hiring request and on the approval screen (point 4) — medium
   effort, low risk, largest business exposure.
2. Confidence-tiered marketplace linking (point 5) — ~1 day.
3. Quick-add candidate (point 1) — small.
4. Stage-driven candidate screen (point 2) — large; **spike one workflow first**.
5. CTC → salary structure (point 3) — medium; statutory values must be
   configuration, never constants, since TPIA and PMC customers operate outside
   India.

## Open decisions for the owner

- Spike the stage-driven screen before committing to the rebuild?
- Should cost at approval drive an approval **threshold**, or is showing the
  number enough for now?
- Which business model do we optimise for first (manpower supply / recruitment
  agency / PMC / TPIA)?

## Already shipped for this thread

- `a236c76` — one word per object; every recruitment screen asks the terminology
  engine (ADR-002).
- `b309485` — an approval carrying no figures is refused, in a way that still
  accepts an agency's fee-only deal and a fixed whole-order price.
