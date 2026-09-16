# Phase 3 · M3 CORRECTION — AUDIT

The audit made **before** writing the correction, of the three defects the M3
adversarial audit proved.

---

## F1 · Notification eligibility was broader than approval visibility

### Root cause

One assumption, in one place: **holding the approver's role was treated as
eligibility to be notified.**

`appr_step_recipients()` began with `appr_role_emails($role)`, whose whole answer
is *"every active holder of this role, anywhere"*. Nothing then narrowed it.
`appr_email_escalate()` did the same for the escalation role and for its
all-administrators fallback.

Meanwhile the approval layer had spent M1 and M2 deciding, carefully and per
entity, **who may see a hiring request** — and the notification layer never asked.

Two disclosures followed, both proved with probes:

| | |
|---|---|
| **Cross-branch** | branch B's `SBU_HEAD` is hidden from the queue and refused at the engine, and was e-mailed the request's **title** anyway — twice, because M3 added reminders |
| **Segregation** | the requestor holding the approver role was asked by e-mail to approve the request segregation will never let them approve |

### Why the obvious fix is the wrong one

Checking `role == approver_role` harder does not help: that comparison **is** the
defect. Neither would a new list of rules for notification — that is a second
authorization engine, and two engines drift, which is what M2's correction was
about.

### The fix

Recipients become a **candidate** list, and **the existing model decides**.

```
CANDIDATES                              THE DECISION
named user, or the holders of a role    appr_visible($step, $req, $user)
+ their current delegates (M2)            = appr_can_act()  AND  appr_guard()
```

`appr_visible()` is the same function `/my-approvals` uses. If a person would not
see the item in their queue, they are not written to. There is no path around it:
named user, role holder and delegate all pass through the same line.

### The one real obstacle, and how it is handled

`appr_guard()` reads the **current** user — entitlement, branch scope and
segregation all do. Asking *"may this other person be told"* therefore means
asking the question **as** them. `appr_as_user()` swaps the session, asks, and
restores it in a `finally`, so no caller can leave the session somewhere it should
not be, not even on an exception.

That is deliberate reuse rather than reimplementation: the alternative was to
rewrite entitlement, scope and segregation as user-parameterised copies — four new
rules that could disagree with the originals.

### Two disclosure levels, from the same primitives

| Level | Used for | Asks |
|---|---|---|
| **Actionable** — *"approve this"* | assignment, reminder | `appr_visible()` in full, **segregation included** |
| **Informational** — *"this is late"* | escalation | entitlement + record exists + branch scope, **without segregation** |

Segregation is dropped for the informational level on purpose: telling the person
who **raised** a request that it has gone overdue is the point of the message, not
a leak. Everything else still applies, so an escalation contact who cannot see the
branch is not told what it is about.

### Role path · named-user path · delegation path

All three were audited separately, because the audit's lesson was that a question
asked on one path does not reach its sibling:

- **Role** — holders are candidates; each is asked the question.
- **Named user** — being named is **not** a bypass. A named approver outside the
  branch scope is refused by the same line, and the engine refuses their decision
  too. Proved, not assumed.
- **Delegation** — M2's rules are untouched and now also decide the e-mail:
  expired, revoked, wrong-branch and inactive-delegator all stop the notification
  exactly as they stop the authority.

### `appr_role_emails()` is deleted

It answered the question that caused the leak, and nothing calls it now. Leaving
it in place would leave the defect within reach of the next person who needs a
recipient list.

---

## F2 · Delegation bypassed the entitlement that policy respects

### Root cause

**Mine, and introduced by M3.** M3 put entitlement in front of capability for
approval-policy writes (`appr_config_can()`) and left delegation writes asking
`hiring_admin_can()` alone.

So on a workspace that had stopped paying for recruitment:

| | |
|---|---|
| edit the approval matrix | **refused** |
| move approval authority from one person to another | **allowed** |

The second is the more privileged of the two.

This is the recurring shape again — a question asked on one path and not carried
to its sibling — this time in the write path, and this time authored by me.

### The fix

`appr_delegation_save()` and `appr_delegation_revoke()` ask `appr_config_can()` —
**the same gate**, not a second entitlement rule. Entitlement before capability,
at the write, so a direct POST or AJAX call meets it exactly as the screen does.
The route gate stays; it is now the second boundary rather than the only one.

Every delegation write is covered: create, edit, revoke, and deactivate through
the save path (`active`).

---

## F3 · The two signals ran the wrong way round

### Root cause

`appr_tick()` escalated once (`escalated=1`) and then, on every later run, fell
through to the reminder branch. For an abandoned request:

- the **escalation contact** was told **once, ever**
- the **approver** was reminded **every day, without limit**, each time to every
  recipient, each time with a row on the activity timeline

The signal that should grow louder fired once; the one that should stop repeated
for ever, and the audit trail grew without bound.

### The fix

One clause: the routine reminder does not run once the step has been escalated.

**The approval does not go quiet.** It stays `PENDING`, reads **Escalated** on
every screen, and is still counted on the dashboard — that is the durable signal.
What stops is the daily repetition, not the visibility. No arbitrary reminder
count was introduced, because the policy architecture has no field for one.

The scheduler still approves nothing, rejects nothing, reassigns nobody and
manufactures no authority.

---

## Scope held

No new table, no new column, no migration. Nothing outside
`lib/recruit_approval.php` and its tests was touched. **F4** (the Recruitment
Command Centre is company-wide) and **F5** (unbounded scans) are deferred by the
brief and were deliberately left alone.
