# Phase 3 · M3 — SLA POLICY

Written for the person configuring it, not for a developer.

## The one sentence

> **For this type of Hiring Request, these authorities must approve it — each
> within so many working days, or they are reminded, and then their escalation
> contact is told.**

## Where it is configured

**Recruitment approvals** (`/recruit-approvals`). Each level of an approval rule
carries four settings, and they are the whole SLA model:

| Setting | In plain words |
|---|---|
| **SLA days** | how many **working days** this approver has |
| **Reminder days** | after how many working days they get a nudge |
| **Escalate to** | who is told if the SLA passes — a role or a named person |

There is no separate SLA screen, no SLA master and no second policy engine. An
SLA belongs to the approval level it measures, and that is where it is edited.

## Who may change it

An **administrator**, or a **Recruitment Manager** (`hiring.admin`), on a
workspace that has bought People & hiring. Asked **at the write**, not only on the
screen — a direct POST or AJAX call is refused exactly as the screen is. A master
on a workspace without recruitment is refused too; there is no master bypass of
entitlement.

## When the clock starts

When the step becomes **the one being waited on** — not when the request was
raised.

```
Request submitted  →  level 1 active  →  level 1 clock starts
level 1 approved   →  level 2 active  →  level 2 clock starts
```

A level that has not been reached has **no due date** and reads *Not started*.
This is the correction at the heart of M3: previously every level's clock started
at once, so a later approver could be handed a request that was already overdue.

## When it stops

The moment the step is decided — approved, rejected or cancelled. A finished step
reads *Completed* however far past its date it is, and is never chased again. A
cancelled request stops being reminded and escalated at once.

## Working days

A "2-day SLA" means **2 working days** at that request's branch: Sundays and that
branch's public holidays are skipped, using the holiday calendar the application
already keeps. A request raised on Friday with a two-day SLA is due on Tuesday,
not over the weekend.

For offers, salary structures and requisitions — which carry no branch — the
company-wide holidays apply.

## The seven states

Calculated from the step and the clock. Nothing is stored, so nothing can go
stale.

| State | Means |
|---|---|
| **Not started** | this level has not been reached yet |
| **On track** | inside its time |
| **Due soon** | past the reminder point, not yet due |
| **Due today** | last day to act |
| **Overdue** | past the agreed date |
| **Escalated** | overdue, and the escalation contact has been told |
| **Completed** | decided |

The screen always shows **two** facts, never one blended one:

> Approval: **Pending** with Unit head · SLA: **Overdue by 2 days**

## What happens if nobody acts

1. **Reminder** at the reminder point — to the approver, and to anyone currently
   holding their authority by delegation. Once, however often the scheduler runs.
2. **Overdue** once the date passes — visible in the inbox, on the request, and
   on the Recruitment Command Centre.
3. **Escalation** — the escalation contact is told, **once**. If nobody could be
   reached, the timeline records exactly that; it is never recorded as a
   successful escalation.

## What an escalation is not

**It is not a transfer of authority.** The manager who is told still cannot
approve unless the approval rules independently say they may. Nothing in the
approval engine reads the escalation columns.

The current model has no way to hand authority from one approver to another on a
deadline, and M3 did not invent one. The supported way to have somebody else act
is **delegation** (M2), which is deliberate, dated, scoped, revocable and
recorded.

## Changing the policy later

A change applies to **new** approvals. An approval already running keeps the
deadline and the policy it was given — an administrator editing the matrix today
cannot rewrite an approval that is halfway through, and cannot make one retro
overdue.

## Running it

The reminders and escalations run from the existing nightly job (`cron.php`),
which already refuses to do paid-module work for a workspace that has not bought
the module. Nothing else is needed. Running it twice, or ten times, sends
nothing twice.

> **Operational requirement:** one `cron.php` invocation serves one workspace. For
> a URL-triggered cron, each workspace's own address must be called — that is how
> tenant safety is achieved, and it is unchanged from Phase 6.

## Time zone — stated plainly

The platform has **no per-tenant time-zone setting**; server time is the only
clock it has ever had, for every module. M3 uses it and does not pretend
otherwise. Introducing tenant time zones is a platform change, not an SLA
feature, and is recorded as a deferred item rather than invented here.
