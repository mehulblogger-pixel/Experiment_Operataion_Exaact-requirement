# Phase 5 — Business Invariants

*Things that must be true of every recruitment figure this system publishes,
whatever screen it appears on.*

| # | Invariant | Why it exists |
|---|---|---|
| **K1** | Every screen reads ONE calculation. The dashboard and the records can never disagree | Measured before Phase 5: the Command Centre showed **7 open positions where the records said 3** |
| **K2** | A vacancy the business gave up is not open demand | The four cancelled vacancies in that fixture were still being counted as work to do |
| **K3** | A figure that cannot be computed truthfully is NO DATA, never zero | "0 days late" on a requirement with no target date reads as *on time* |
| **K4** | Historical credit survives reassignment | Hand a requirement over and last month's performance table must not rewrite itself |
| **K5** | Calendar, business and SLA ageing are never mixed | They answer three different questions; an average of them answers none |
| **K6** | A stage duration is never measured across a revert or a workflow switch | Neither is a transition. Timing across one invents a step nobody worked |
| **K7** | A metric with no entitlement is withheld, not zeroed | A zero is a number a manager acts on. An unlicensed figure is not a zero |
| **K8** | A branch sees only its own figures | Scope is a security boundary, not decoration |
| **K9** | Every sum is computed per requirement before it is added up | Netting across requirements lets one over-filled requirement hide another that is short |
| **K10** | An unattributable outcome is shown as unattributable | Sharing it among the people who *can* be named credits the wrong person |
| **K11** | A target date is never invented | A KPI measured against a made-up target is worse than no KPI |
| **K12** | The stage ledger records every stage movement | A ledger with holes in it is worse than none: it looks complete |

---

## What Phase 5 is forbidden to do

Nothing an earlier milestone owns is re-decided here.

- **M3** still owns what "filled" means, what was requested, and what was cancelled.
- **M4** still owns the approved ceiling and the hiring request — and Phase 5 asks
  it through `hreq_get()`, never by reading its table.
- **M5** still owns who is accountable; the ledger is read, never rewritten.
- **M6** still owns whether recruitment may execute and who holds a seat.
- **Phase 4** still owns what is promised to which source.
- The **approval engine** still owns SLA.
- The **scheduling module** still owns which days a branch works.

Phase 5 decides only **how those answers are presented, aged and attributed** —
and it publishes them in one place so that every screen gives the same answer.

## The practical consequence

The worst outcome of any Phase 5 refusal is that **a figure is withheld**. No
Phase 5 code can move a candidate, change a requirement, grant a seat, alter an
approval or reassign accountability. It reads, and it says when it cannot.
