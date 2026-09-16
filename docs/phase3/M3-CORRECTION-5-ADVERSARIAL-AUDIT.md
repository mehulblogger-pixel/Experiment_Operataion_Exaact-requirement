# Phase 3 · M3 CORRECTION #5 — ADVERSARIAL AUDIT

An attack on correction #5, after it was reported complete. Every finding proved
with a running probe.

**Verdict: NOT ACCEPTED. G1 is correctly fixed inside the notification layer, and
the same question was not carried to the three layers beside it.**

Regression figures stand and are unaffected: SQLite 9698/0, MariaDB 9699/0, new
suite 179/0, G1 mutations 10/10. None of these findings is caught by any test.

---

## H1 · MEDIUM — An approval nobody can see can still be approved

Correction #5 made an orphan chain invisible. It left it **actionable**.

```
ok    setup · the orphan chain is invisible, as correction #5 intends
ok    setup · and it is out of the approver's queue
FAIL  an approval whose source record no longer exists cannot be decided
      → "Approved — fully cleared."
FAIL  and the chain was not closed                     (want PENDING, got APPROVED)
```

The offer was deleted; the step vanished from the queue and generated no
notifications; and a direct call to the decision endpoint still **approved it, and
reported "fully cleared"**.

### Why it matters, stated precisely

It is **not** an authorization bypass — the actor is the chain's legitimate
approver. It is a **state-integrity** defect, and it is one M1 already ruled on:

> *"A decision that could not be applied is not a decision, so nothing about it is
> left behind."* — M1 correction, `appr_undo_step()`

M1 implemented exactly that: `appr_callback()` returns a reason for
`HIRING_REQUEST` and the step is put back. For offer, salary and requisition the
callback is deliberately best-effort — *"their SQL legitimately affects no rows in
ordinary cases"* — so nothing notices the row is gone. The chain closes APPROVED
against a record that does not exist, and the audit says "fully cleared".

It also inverts a principle this project holds: a hidden button is not security.
Correction #5 hid the item and left the endpoint open.

### Classification
**M3 correction #5 defect.** The same sibling-path shape, now in the **decision**
layer rather than the notification layer.

---

## H2 · MEDIUM — The scheduler writes an audit row about an orphan on every run, for ever

```
ok    setup · the orphan chain is still PENDING, so the scheduler really does look at it
FAIL  five scheduler runs over a PENDING ORPHAN write no repetitive audit rows
      (wrote 5)
```

`appr_tick()` finds the orphan, builds an empty recipient list — correctly, that is
G1 working — and then records *"Approval reminder sent — NO RECIPIENT"* on the
activity spine, and moves the reminder threshold forward a day. **Once a day, for
ever.**

This is **D2's defect, in a path D2 did not touch**: a permanent structural
condition logged as a recurring event. D2 fixed it for the decision notifier and I
did not carry the rule to the SLA audit.

### And my own suite's blind spot

`C5.3` asserts *"the scheduler sends nothing about it either"* — and checks only
`email_log`. It never checked whether a **row** was written. The same class of gap
the last two audits found: I asserted the visible half of the consequence.

### Classification
**M3 correction #5 defect.**

---

## H3 · LOW — The dashboard counts an approval nobody can see or act on

```
FAIL  the dashboard does not count an approval nobody can see or act on  (got 1)
```

`appr_sla_summary()` counts pending steps without asking visibility, so an orphan
inflates "awaiting approval" and can sit in the overdue and escalated tiles. Low
severity — it is an aggregate count with no subject or name — but it tells the
operator that work is waiting when none is reachable.

### Classification
**M3 correction #5 defect (minor).** Consistent with F4's company-wide design,
which is *not* what this is: it is counting something no user of any branch can
act on.

---

## A note on my own first probe

My first pass reported H2 and H3 as **passing**. They passed for the wrong reason:
the H1 assertion ran first and **approved** the chain, so by the time the scheduler
and dashboard were probed there was no pending chain left to find. Re-run against
an orphan that stays PENDING throughout, both fail.

A green assertion is only worth what its preconditions are worth. I am reporting
this because it is the same error the milestone keeps punishing, committed inside
the audit itself.

---

## What held up under attack

- **G1 proper** — a deleted record denies on the informational path, the actionable
  path, the recipient list and the decision notifier, for all four entities; the
  approver still passes `appr_can_act()` and visibility still denies. Ten
  mutations, all caught, three of them opening the hole one entity at a time.
- **The resolver's error path** — constructed by renaming the source table, and
  denying.
- **§12 business function** — a valid record still notifies its raiser, for all
  four entities.
- **C1, C2, D1, D2, D3, E1, E2, F1, F2, F3** — re-run and intact.

---

## Honest note

The pattern is unchanged but has moved outward. Corrections #1–#4 kept finding a
rule applied to one **entity** and not its siblings. Correction #5 applied the rule
to every entity — and then left it inside one **layer**, while the decision, the
SLA audit and the dashboard each ask their own version of "does this record still
exist" and none of them asks it.

The next correction should not be "fix three more places". It should be: **when a
record can stop existing, every layer that reads it needs the same answer** —
notification, decision, audit and reporting — and that is one question asked in
four places, not four questions.

**Recommended status: M3 CORRECTION #5 NOT ACCEPTED**, pending H1, H2 and H3.
