# M3 CORRECTION #14 — ADVERSARIAL AUDIT

An attack on my own completed work. Probes ran against a **copy** of `phpapp/` in
the scratchpad; **no product code was modified**. Every result is measured on
**both engines**.

**Four findings. One is material and live. One is a claim in my own completion
report that the evidence does not support.**

---

## C-1 · MATERIAL — the alert can never be cleared

This is the finding I did not anticipate, and it is the one that matters.

Recovery in #14 happens **only when the same condition occurs again**. The retry
lives inside `appr_audit_notify()` / `appr_audit_sla()`, so if nothing raises that
condition, nothing retries it. Measured, both engines:

| step | result |
|---|---|
| a condition is left unarmed | confirmed |
| the dashboard warning appears | confirmed |
| the storage fault is **completely repaired** | — |
| the condition does not recur (the chain was resolved or cleaned up) | — |
| the warning | **still showing** |
| any supported way to clear or re-arm it | **none exists** |

`appr_cond_resync()`, `appr_cond_clear()`, `appr_cond_reconcile()` — none of them
exist. The unarmed row sits in `activities` with an empty marker for ever, and
`appr_cond_unarmed_count()` keeps counting it.

**Why this is worse than the problem it replaced.** The whole point of B-2 was to
put a truthful signal in front of a business user. What it actually produces is a
**permanent warning with no clearing path**, on the Recruitment Command Centre,
for a fault that has already been fixed. The first time a customer sees it they
will act. The second week they will learn to ignore that strip — and it is the
same strip that carries *Overdue* and *Escalated*. A stuck alert does not merely
fail to inform; it trains people to stop reading the panel that does.

And the ordinary case makes it likely, not rare: an orphaned approval chain is
exactly the thing that gets cleaned up, and a decided request stops raising its
condition by definition. **The conditions most likely to go unarmed are the ones
least likely to recur.**

I listed "the retry is unbounded in attempts" as a known limitation and framed it
as a cost. The real exposure is the opposite: the retry does not run **often
enough** — it does not run at all unless the condition returns.

---

## C-2 · MY COMPLETION REPORT OVERSTATES B-5

Report §6 says the diagnostic is "bounded **structurally**". Measured over 30
ticks while the `activities` table itself could not be written:

- every tick returned `NOT_RECORDED` — correct;
- no rows were written — correct;
- **30 identical diagnostic lines were written.**

So B-5 is fixed for `UNARMED` and **not fixed for `NOT_RECORDED`**. I did disclose
the mechanism honestly (known limitation 2: "`NOT_RECORDED` is not bounded across
ticks"), but §6's headline claim and that limitation contradict each other, and
§6 is the sentence a reader takes away. The accurate statement is: *the diagnostic
is bounded in the marker-failure case and unbounded in the core-failure case, for
a reason that cannot be solved from inside the activities table.*

I also under-tested it: C14.D proves the bound for `UNARMED` across 30 ticks;
**there is no equivalent 30-tick assertion for `NOT_RECORDED`**, which is why my
own suite reports a clean bound while the behaviour is unbounded.

---

## C-3 · The fingerprint is in a column built to be shown to people

Confirmed on both engines: the condition event carries `PCX|dc9e228111…` in
**`body`**, and `tosrm_render_comms()` — the shared timeline panel — renders
`$a['body']` verbatim underneath the subject.

**Not live today.** That panel is wired only for `JOB` and `CALL`, and
`act_partner_for()` returns no partner for `APPROVAL_POLICY`, `HIRING_REQUEST`,
`OFFER`, `SALARY` or `REQUISITION`, so these events reach no customer timeline.
My C14.D2 assertion that the screen never exposes `PCX|` is true of the dashboard
and says nothing about the timeline.

But the moment anyone adds an activity panel to a hiring-request or offer screen —
an obvious, likely next feature — a 32-character hash appears on a business
screen. `CLAUDE.md` requires every user-facing screen to pass the "Zero Training
UI" gate; a raw hash under an audit entry does not. I chose `body` because it was
base-schema and free, and I did not weigh that it is also **display**.

---

## C-4 · The bound costs two more unindexed scans on a table that grows for ever

`appr_cond_gate()` now runs `appr_condition_seen()` (unindexed `cond_key` — this
is U2, already documented) **and** `appr_cond_unarmed_row()` (unindexed `body`),
on every condition event. `appr_cond_unarmed_count()` adds a third, a
`LIKE 'PCX|%'` over the whole table, **on every dashboard load**. Confirmed: no
index on `body` exists on either engine.

`activities` is the spine — it only grows. U2 was accepted as one documented
scan; #14 quietly made it three, and the third is on the busiest screen in the
product. I did not mention this in the completion report at all.

---

## What survived the attack

Stated as plainly as the failures, and these are the load-bearing claims:

- **The bound holds.** 30 ticks, one row, on both engines. Not one tick reported
  `SUPPRESSED` or `RECORDED` while unarmed.
- **The taxonomy is truthful.** `NOT_RECORDED` where no row exists,
  `UNARMED` only where a row provably exists; every log line generated from the
  status. B-3 is genuinely closed.
- **The chain is connected.** All five call sites consume the outcome, it drives a
  real decision, and the cron run reports it. B-1 is genuinely closed.
- **Recovery works** — when the condition recurs. The retry arms the existing row,
  no new row, and ordinary suppression resumes. It is the *reachability* of the
  retry that C-1 attacks, not its correctness.
- **Tenant isolation holds**, proved by real database switching.
- **All ten mutations are caught**, including the screen mutation that survived
  the first battery.

## Two defects in my own probes, reported rather than discarded

1. **I counted my own fixture. Again.** The first probe read absolute row counts
   and reported "1 row" where it meant "none" — creating an approval policy is
   itself an audited act. Third time this exact fixture shape has caught me; it is
   now baselined.
2. **I selected the wrong row.** The fingerprint probe took the *first* row for the
   policy — the policy-creation event, whose `body` is empty — read an empty
   string and concluded there was no fingerprint. Asking for the condition row
   shows it plainly. Had I trusted the first run I would have reported a false
   clean on C-3.

---

## The pattern

#12 fixed the helper and not the caller. #13 fixed the caller and not its caller.
**#14 connected the whole chain and did not ask what happens when the chain stops
running.** Every correction has been sound about the path it traced and blind to
the state left behind when that path is not taken.

| # | finding | live? | severity |
|---|---|---|---|
| C-1 | the business alert can never be cleared; recovery only runs if the condition recurs | **yes** | **high** |
| C-2 | B-5 is unbounded in the `NOT_RECORDED` case; §6 of my report overstates it, and no test covers it | **yes** | medium-high |
| C-4 | two extra unindexed scans, one on every dashboard load; not mentioned in the report | **yes** | medium |
| C-3 | the fingerprint sits in a display column and the shared timeline renders it verbatim | no (not wired for these entities) | medium if any timeline is added |

Nothing here was fixed. No product code was changed during this audit.
H1, H3, S2, S3, U2 and A-2…A-7 all remain open.
**M3 is not accepted. M4 is not started.**
