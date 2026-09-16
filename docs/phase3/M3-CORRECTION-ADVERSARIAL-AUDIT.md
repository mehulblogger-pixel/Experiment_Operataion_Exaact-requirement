# Phase 3 · M3 CORRECTION — ADVERSARIAL AUDIT

An attack on the M3 correction, after it was reported complete. Every finding
proved with a running probe.

**Verdict: the correction does NOT hold. F1's rule is sound and its machinery is
safe, but the rule was applied to the approver and escalation paths and not to the
third notification path in the same file.**

The regression figures stand and are unaffected: SQLite 9286/0, MariaDB 9287/0,
correction suite 68/0 on both. Neither defect below is caught by any test — which
is, again, part of the finding.

---

## C1 · HIGH — The decision e-mail goes to the wrong person, and the right one is never told

### What happens

`appr_email_requester()` resolves the requester from
`recruit_approval_requests.requester`, which stores a **display-name string**, with

```sql
WHERE email<>'' AND (username=? OR TRIM((first_name || ' ' || last_name))=?) LIMIT 1
```

`LIMIT 1` with no ordering. Two people called "Ravi Sharma" and the one with the
lower id wins — whoever that happens to be.

### The probe

An `INSPECTOR` at branch B with **no recruitment permission at all**, created
before the real requester, sharing their display name:

```
FAIL  P2 · a DIFFERENT person sharing the requester's display name is NOT told
          the outcome                                        (want 0, got 1)
FAIL  P2 · while the real requester IS                       (want ≥1, got 0)
```

So the hiring request's **title** and its outcome were e-mailed to an unrelated
person in another branch with no rights to it, and the person who actually raised
it heard nothing.

### Why this is the correction's, not merely Phase 6's

The mechanism is Phase 6's and I did not change it — I reverted my only edit to
that function during M3, correctly, when its supposed portability bug was
disproved. But the M3 correction declared a rule:

> **Notification eligibility is never broader than approval visibility.**

and then applied it to the approver path and the escalation path while leaving the
**third notification path in the same file** untouched. I audited "all recipients"
and looked at two of three. That is the same recurring shape this project keeps
finding — *a question asked on one path and not carried to its sibling* — now at
the fourth time of asking, and this time I was the one who wrote the rule it
breaks.

### Root cause beneath the symptom

The chain stores **who asked** as a name, not as an identity.
`recruit_approval_requests.requester` is a string. For a `HIRING_REQUEST` the
canonical answer already exists and is authoritative —
`hiring_requests.requested_by_id`, which M4's correction established for exactly
this reason. The lookup should not be resolving a person from prose when an id is
sitting next to it.

### Classification
**M3 correction defect.** Must be fixed before acceptance. Any fix should resolve
the recipient by **id** for `HIRING_REQUEST`, and must not silently widen the
other entities.

---

## C2 · MEDIUM — A step whose request has vanished notifies everybody

### What happens

`appr_step_recipients()` fetches the request and passes it to `appr_visible()`. If
the request row is gone, `$req` is `null`, so `$entity` resolves to `''`, so
`appr_visible()` takes its "not a hiring request" branch and returns
`appr_can_act()` **alone** — the entity-aware guard is skipped entirely.

```
FAIL  P5 · a step whose request no longer exists notifies nobody   (want 0, got 13)
```

Thirteen recipients, with no entitlement, branch or segregation question asked.

### Why it matters even though it should not happen

It **fails open**. A missing record makes the guard weaker rather than stronger,
and for a disclosure decision the default must be the other way round. Cancellation
does not delete request rows today, so this is not reachable through the ordinary
lifecycle — but "not currently reachable" is a statement about today's callers,
not a protection.

### Classification
**M3 correction defect** (low likelihood, wrong-direction failure). The fix is one
line: no request, no recipients.

---

## What held up under attack

Stated as plainly as the failures.

### The impersonation machinery is sound — and it was the biggest risk I took

`appr_as_user()` swaps `$_SESSION['uid']` to evaluate the existing guard as
another person. If it leaked, it would be an authorization defect inside a request,
which is far worse than the leak it was written to close. It does not:

```
ok  the signed-in user is restored after impersonation
ok  …and their role
ok  …and their BRANCH SCOPE is not left holding B's
ok  …and their permissions
ok  A still cannot reach branch B after the swap
ok  an exception inside the callback still restores the session
ok  …and the scope with it
ok  nested impersonation unwinds to the original user
```

`ua(true)` rebuilds everything derived from the user, so scope and permissions
come back with the session, and the `finally` holds under an exception and under
nesting.

### F5's cost concern is not material at this scale

```
ok  15 holders of the approver role resolve to 13 recipients
ok  and building that list took 4 ms (one step, one tick)
```

Four milliseconds. The per-candidate evaluation is real but small; the deferral
stands, and it is a deferral rather than a problem.

### And the rest

- **F2** — an unlicensed workspace escalates nothing and tells nobody; the
  delegation screen is module-gated as well as the write, so it does not open and
  then fail on save.
- **F1's approver and escalation paths** — no cross-branch recipient, no requestor
  asked to approve their own, named users and delegates refused by the same line.
- **F3** — one hundred runs after escalation send nothing, and the step stays
  visible and counted.

---

## Honest note

I reported this correction complete. On this evidence that was **premature**, for
the same reason as last time and with less excuse: the rule I wrote is right, and I
did not carry it to the end of the file I wrote it in.

**Recommended status: M3 CORRECTION NOT ACCEPTED**, pending C1 and C2.
