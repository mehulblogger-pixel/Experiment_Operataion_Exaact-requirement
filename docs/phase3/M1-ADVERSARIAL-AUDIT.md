# Phase 3 · M1 — Adversarial Audit

Conducted **after** M1 was reported complete, against the committed code
(`bd02f0c`), by probing the seams rather than re-reading the report. Every
finding below was reproduced with a running application; none is a code-reading
opinion.

**No code was changed by this audit.** M1 is under HARD STOP.

---

## FINDING 1 — Cancelling a request leaves its approval chain open *(defect, mine)*

**Severity: moderate. Integrity and audit trail, not access control.**

`hreq_cancel()` knows nothing about approval chains. M4 wrote it before chains
existed, and M1 connected chains without revisiting it.

Reproduced:

```
after submit    : request=UNDER_REVIEW  chain=PENDING
after cancel    : request=CANCELLED     chain=PENDING     ← chain still live
approver acts   : ok=true  msg="Approved — fully cleared."
RESULT          : request=CANCELLED     chain=APPROVED    step=APPROVED
executable?     : false
```

Three things are wrong:

1. The step **stays in the approver's inbox** after the request is cancelled, so
   somebody is asked to approve something that no longer exists.
2. Approving it **reports success** — "Approved — fully cleared" — because
   `appr_act()` calls `appr_callback()` and **ignores its result**. The one
   writer correctly refused (a `CANCELLED` request cannot be decided), but the
   approver is told the opposite.
3. The approval history now shows an **APPROVED step against a CANCELLED
   request**. For an approval system, a false entry in the record is the part
   that matters.

**What held:** `hreq_is_executable()` returned false throughout, so no
recruitment could start. The boundary M4 built did its job — this is an
integrity defect, not an escape.

**Recommended fix (small, self-contained):** `hreq_cancel()` should close any
open chain for that request, and `appr_act()` should surface a failed callback
instead of discarding it. Both are a few lines; both belong in M1's own domain,
since "cancel rules preserved" is an M1 acceptance criterion.

---

## FINDING 2 — The approval inbox is not branch-scoped *(pre-existing, made consequential by M1)*

**Severity: low-moderate. Information disclosure across branches; no ability to act.**

`appr_inbox()` filters only by `appr_can_act()` — approver identity. It asks no
branch question. Reproduced:

```
foreign approver inbox rows: 1
  sees: Hiring request HRQ-2026-000002 — CONFIDENTIAL branch-A role
can they act? : false  (guard: outside your office / branch scope)
```

A branch-B approver holding the configured role **reads the subject line** of a
branch-A hiring request — job title included — in their own inbox. M1's guard
correctly refuses the decision, so nothing can be *done*; but the request should
not have been visible.

This predates M1 (the inbox has never been scoped, and the same applies to
offers and salary chains). M1 made it consequential by putting hiring requests
into that inbox.

**Recommended fix:** filter `appr_inbox()` with the same guard that governs the
decision, so what is listed and what may be acted on are the same set. Note this
would change the offer and salary inboxes too, which is why it is being reported
rather than done inside a milestone that was told not to change their behaviour.

---

## FINDING 3 — A chain whose approver role nobody holds *(minor; recoverable)*

If an administrator configures a level for a role no active user holds, the
request sits at `UNDER_REVIEW` and `hreq_decide()` refuses ("This request is with
its approvers"). Probed with a `FINANCE` level and zero Finance users:

```
users with that role : 0
master in inbox?     : 1        ← recoverable
master decides directly: refused, correctly, with the right message
FINAL                : UNDER_REVIEW, executable=false
```

**It is not stranded:** `appr_can_act()` returns true for `is_master_of('hiring')`,
so a master sees the step and can clear it from *My approvals* — and the refusal
message points them there. A workspace with no master **and** no user in the
configured role would be stuck, which in practice does not occur because every
workspace has a superuser.

**No fix recommended.** Worth documenting for whoever builds the matrix UI: it
should warn when a level names a role nobody holds.

---

## What the audit did NOT find

- No way to reach `APPROVED` without passing the guard.
- No way to recruit from an unapproved, rejected or cancelled request.
- No entitlement escape, including as a master.
- No second approval engine, permission, status store or audit spine.
- `appr_tick()` (the cron) only sends reminders and marks escalation — it
  **cannot decide**, so the SLA path is not a back door.

## Honest summary

M1's access-control claims survived the audit. Its **state-integrity** claim did
not: cancelling a request leaves a live approval chain behind it, and the system
will cheerfully tell an approver they approved something that was cancelled.
That is my defect, introduced by connecting chains without revisiting cancel.
