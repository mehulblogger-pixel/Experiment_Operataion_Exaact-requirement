# Phase 3 · M3 — ADVERSARIAL AUDIT

An attack on M3, conducted **after** it was reported complete, against my own
work. Every finding below was **proved with a running probe**, not read out of the
source — that discipline is what caught my mistake in the M3 audit itself, and it
is what caught these.

**Verdict: M3 should NOT be accepted as it stands. Three real defects, one of them
an information leak that leaves the application by e-mail.**

The whole-suite figures in the completion report are accurate and unaffected:
SQLite 9218/0, MariaDB 9219/0. None of these defects is caught by any test,
which is itself part of the finding.

---

## F1 · HIGH — Notification contradicts visibility. A hiring request's title is e-mailed to another branch, repeatedly.

### What happens

For a role-based approval step, `appr_role_emails($role)` returns **every active
holder of that role, in every branch**. M3 kept that and added reminders on top,
so it now repeats on a schedule.

Probe — one hiring request at branch 983, approver role `SBU_HEAD`, and a
`SBU_HEAD` at branch 984 explicitly scoped to their own branch:

```
B0 ok    the far approver is genuinely scoped out of branch 983
B1 ok    M2 HIDES this request from the far approver's queue
B2 ok    and the engine refuses their decision
B3 FAIL  recipients: a2app@x.test, a2far@x.test
B4 FAIL  e-mails naming "AUD2 confidential restructure" reaching the other branch: 2
```

M1 and M2 deliberately decided this person may not see the request and may not
decide it. **The e-mail tells them anyway, by name, and keeps telling them.**

### The same unasked question, second form

The requestor is e-mailed too, when they happen to hold the approver role — the
exact case M1's segregation exists for:

```
C1 ok    segregation refuses the requestor's own decision
C2 ok    and M1/M2 keep it out of their queue
C3 FAIL  recipients: a3req@x.test, a3app@x.test
```

They are asked by e-mail to approve a request the engine will never let them
approve.

### Why this is M3's, not merely inherited

`appr_role_emails()` is Phase 6. But:

1. M3's stated purpose for touching notification was *"the person the system is
   waiting for is the person it tells."* I asked that question on the **delegation**
   path and did not carry it to the **role** path beside it.
2. M3 turned a one-off into a **recurring** disclosure by adding reminders.
3. E-mail leaves the application. An in-app leak is contained; a mailbox is not.

This is the recurring defect I have now named three times and reproduced a fourth:
**a question asked on one path and not carried to its sibling.** It is the same
shape as M1's branch scope on the route but not the write, and M2's delegator
`is_active` on the role path but not the named-user path.

### Classification
**M3 defect** (inherited mechanism, materially worsened and squarely inside M3's
declared scope). Must be fixed before acceptance.

---

## F2 · MEDIUM-HIGH — Entitlement guards approval policy but not approval delegation

M3 put entitlement in front of capability for policy writes (`appr_config_can()`).
It did not do the same for **delegation** writes, which still ask only
`hiring_admin_can()`.

```
A1 ok    with recruitment OFF, approval POLICY may not be written
A1 FAIL  …and approval DELEGATION may not be written either: "Delegation created."
```

So on a workspace that has stopped paying for People & hiring, an administrator
**cannot edit the approval matrix but can still move approval authority from one
person to another.** Delegation is the more privileged of the two.

I created this asymmetry in M3 by hardening one sibling and not the other — the
same pattern as F1, in the write path instead of the read path.

### Classification
**M3 defect.** The fix is one line and must reuse `appr_config_can()`, not add a
second entitlement rule.

---

## F3 · MEDIUM — After escalation, the loud signal stops and the quiet one runs for ever

`appr_tick()` escalates once (`escalated=1`) and then, on every later run, falls
through to the reminder branch. So for an abandoned request:

- the **escalation contact** is told **once, ever**
- the **approver** is reminded **every day, for ever**, each with an e-mail to every
  recipient and a row on the activity timeline

```
A4 FAIL  an abandoned request does not keep e-mailing for ever after escalation (sent 14)
```

That is backwards. The signal that should grow louder fires once; the one that
should stop repeats without limit, and the audit trail grows without bound. There
is no cap, no re-escalation, and nothing that ever gives up.

### Classification
**M3 defect** (behaviour inherited from Phase 6, but M3 is the milestone that owns
reminders, escalation and their audit). A cap, or a second escalation, or both —
a design decision for you, not for me to pick silently.

---

## F4 · LOW — The dashboard tile is company-wide, and so is the dashboard

`appr_sla_summary()` counts every pending step in the workspace, so a branch
approver sees the company-wide overdue count.

```
A3 FAIL  an approver at another branch is not shown this branch's backlog count (got 1)
```

**But this is not a regression, and I will not inflate it.** The Recruitment
Command Centre has **no branch scoping anywhere** — `rcc_cand_where()` and
`rcc_req_where()` filter by month, department, source and recruiter, never by
office. My tile is consistent with every other number on that screen, it is
aggregate-only (a count, never a subject or a name), and the screen is already
gated.

### Classification
**Intentional limitation, consistent with the host screen.** Recorded so the
decision is yours and visible, not fixed unilaterally.

---

## F5 · LOW — Two unbounded scans that are fine now and will not stay fine

- `appr_waiting_on_others()` reads **every** pending hiring-request step and calls
  `hreq_get()` per row, then `appr_can_act()` per row. No limit.
- `appr_step_recipients()` loads **every** holder of the approver's role and runs a
  delegation query per holder. No limit.

Correct today, and quadratic-ish in the wrong direction. Worth a bound before a
customer has a few thousand rows.

### Classification
**Deferred architecture debt.**

---

## What held up under attack

Stated as plainly as the failures, because a report that only lists faults is not
an audit either.

- **The clock (§7).** Not born overdue, not resettable, policy frozen. M02's
  restoration of the original defect is caught.
- **Escalation ≠ authority (§13/§15).** The recipient cannot approve; nothing in the
  engine reads the escalation columns.
- **The scheduler decides nothing (§23).** Five runs, no status written, no chain
  advanced, no approver reassigned.
- **Duplicate protection (§21).** Ten runs, one reminder, one escalation.
- **Delegation semantics (§16).** Expired, revoked, wrong-branch and
  inactive-delegator all behave as M2 defined, through one shared rule.
- **Policy write authorization (§25).** A coordinator's direct POST cannot stretch
  an SLA, rename a level, make themselves the approver, or delete the level.
- **Immutability (§31).** A running approval keeps its deadline and its policy.
- **Segregation, branch scope and entitlement at the decision.** Unbroken.
- **Tenant isolation.** Structural, and the holiday-cache violation M3 found in a
  dependency is fixed and behaviourally tested.

---

## Honest note on the completion report

The completion report said ACCEPTED. On this evidence that was **premature** — not
because its numbers were wrong, but because green tests only prove the questions I
thought to ask. Three of the four defects above are in code M3 deliberately
touched, and none of my 176 assertions or 27 mutations asked about them, because
every one of my notification tests used the **delegation** path and none used the
**role** path.

**Recommended status: M3 NOT ACCEPTED pending correction of F1, F2 and a decision
on F3.**
