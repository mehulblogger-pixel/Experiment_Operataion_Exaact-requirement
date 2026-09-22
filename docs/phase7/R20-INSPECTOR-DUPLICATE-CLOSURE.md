# R20 — duplicate Inspector closure

*Release blocker. Two Inspector records for one person is not untidiness: it is
two people in utilisation, two on a timesheet and two on an invoice.*

---

## The release matrix

| Risk | Detection | Prevention | Concurrency | Historical data | Release |
|---|---|---|---|---|---|
| **Employee number** | PASS | PASS — database unique key | **PASS** — 3 racers, 1 record, refused by the key | PASS — never rewritten, collisions reported | **OK** |
| **E-mail (live staff)** | PASS — named, actionable | PASS — database unique key, acknowledgement-aware | **PASS** — 3 racers, 1 record, 3 runs of 3 | PASS — DIRTY reported, never merged | **OK** |
| **Name + e-mail** | PASS | PASS — via the e-mail key | PASS | PASS | **OK** |
| **Name + employee number** | PASS | PASS — via the number key | PASS | PASS | **OK** |
| **Name alone** | n/a — deliberately never blocks | n/a | n/a | n/a | **OK** |
| **Existing duplicates** | PASS — `emp_code_collisions()`, `email_collisions()` | n/a | n/a | PASS — retained, never merged or renumbered | **OK** |

**R20: CLOSED.**

---

## What was actually wrong

Only the employee number was protected. **Add-a-person was a second, unguarded
door** into the team register: the user form, the org-chart import and linking a
login to a team record all went straight to an INSERT. Recruitment had asked
"is this person already on your team?" since RB-3 Step 2; this door never asked
at all, so the same human could be added twice from there and arrive in
utilisation and billing as two people.

## What was built

**1. The same engine answers on both doors.** `workforce_direct_matches()` calls
recruitment's own matcher. No second duplicate rule exists, and a shared *name*
still stops nobody — Rajesh Patel does not block Rajesh Patel.

**2. Detection alone was not enough, and this was measured.** Three processes
adding the same person at the same microsecond all read "nobody there" before
any of them wrote: **3 records created in 2 of 3 runs on MariaDB.** A check that
loses a race is not a protection, which is exactly why §7 forbids relying on
SELECT-then-INSERT.

**3. So the database decides — without removing the acknowledgement.** A unique
key covers only records nobody has acknowledged:

```
unacknowledged + live + has an e-mail  ->  the normalised address   (unique)
acknowledged, or left, or no e-mail    ->  NULL                     (unconstrained)
```

A second unacknowledged record carrying that address is refused by the database,
which no race can get past. A deliberate second engagement that somebody has
explicitly acknowledged is exempt and still allowed: **the tick remains an
acknowledgement, never a merge.**

After the fix: **exactly one record, in 3 runs of 3.**

**4. A refusal is an answer, not a crash.** A key violation returns 0 with a
sentence the person can act on, through the same contract every caller already
understood.

## Two defects the work exposed

**The key disagreed with the matcher.** The first version constrained leavers
too, so a returning employee could not be re-hired. The matcher deliberately
ignores anybody who has left — "history, not a duplicate" — and the key now says
exactly the same thing. Caught by Step 2's X15, and pinned here as **C5b**.

**Three mutants came back FATAL rather than caught.** A key violation was
propagating as an uncaught exception: a user pressing "Add" would have seen a
stack trace. FATAL is not a catch, so this was fixed rather than counted.

## Evidence

- `tests/test_r20_inspector_duplicates.php` — **44 assertions, 0 failed**, both engines.
- Concurrency proved on **MariaDB**, the authoritative engine: SQLite takes one
  database-wide write lock, so a loser there is told "database is locked" before
  the key is ever consulted — true about SQLite, and silent about the protection.
- Mutation: **8 targets, 8 caught, 0 survived, 0 fatal, 0 anchor-miss.**
- Full regression: SQLite **13,215 · 0 failed**; MariaDB **13,220 · 0 failed**.

## Historical data

Untouched. Where a workspace already has two live people sharing an address the
installer reports **DIRTY** and does not build the key over data that breaks it.
Nothing is deleted, merged, renumbered or reactivated to make a constraint fit.
