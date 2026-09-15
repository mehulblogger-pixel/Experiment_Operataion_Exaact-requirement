# Phase 3 · M3 — TEST RESULTS

## Focused suite — `tests/test_p3m3_sla.php`

**172 assertions, 0 failed, on both engines.**

| Section | Assertions | What it holds |
|---|---:|---|
| M3.0 · reuse, not rebuild | 18 | six tables M3 did **not** create; the four SLA columns that already existed; the three added to the existing table; the existing cron and its entitlement gate |
| M3.1 · the clock starts on activation (§7) | 14 | level 2 has **no** due date while level 1 is pending; it is stamped on approval; **its due date is in the future even when the chain has been running ten days**; re-activating cannot move a deadline |
| M3.2 · working days (§9) | 14 | a 1–5 day SLA never falls due on a non-working day; a branch holiday moves that branch's date and no other; the company calendar applies where there is no branch; **the holiday cache answers a new database for itself** |
| M3.3 · where the clock stops (§8) | 6 | a decided step reads Completed however late; the scheduler ignores it; a **cancelled** request is never reminded or escalated again |
| M3.4 · the seven states (§10) | 13 | each state derived from the step and the clock; lateness in whole days; the one readable sentence; nothing stored |
| M3.5 · reminders (§11, §21) | 10 | sent once and audited once; **ten further runs send nothing**; step, request, approver and deadline all untouched |
| M3.6 · overdue ≠ authority (§12) | 7 | a non-approver is still refused; the requestor still cannot approve their own late request; the real approver still can |
| M3.7 · escalation (§13–§15, §23) | 13 | escalated once, to the configured contact; **the recipient cannot approve**; ten further runs do not re-escalate; an escalation nobody could receive is audited as NO RECIPIENT |
| M3.8 · delegation (§16) | 11 | the delegate is notified; expired, revoked, inactive-delegator and wrong-branch delegations are not; unscoped still works; notification never became authority |
| M3.9 · the inbox (§17–§19) | 21 | every operational field present; no global branch filter; "waiting on someone else" shows only your own, never twice, never anybody else's; entitlement on every read |
| M3.10 · notifications (§20, §21, §24) | 11 | failures recorded, never faked; **the requester is told the outcome — the assertion that only passes on MariaDB because the lookup is now portable**; no second store |
| M3.11 · configuration security (§25) | 10 | a coordinator's direct POST cannot stretch an SLA, rename a level, make themselves the approver, delete the level, rewrite or switch off the policy; **a master without the module is refused too** |
| M3.12 · immutability (§31) | 4 | a running step keeps its deadline and its policy; a new request takes the new one |
| M3.13 · tenant and branch (§18, §32) | 10 | a foreign id does not exist and cannot be acted on; a scoped-out same-role approver sees nothing, is refused, and writes nothing |
| M3.14 · the scheduler decides nothing (§23) | 10 | five runs leave the step PENDING with nobody recorded as having acted, nothing recruitable, no reassignment, no advance; a decision landing just before a run is left alone |

## Regression

| Suite | Result |
|---|---|
| **Whole suite · SQLite** | **9214 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** (fresh `exaact_m3b`) | **9215 passed, 0 failed** |
| M1 approval (`p3m1_approval`) | 114 / 0 |
| M2 matrix & delegation (`p3m2_matrix`) | 111 / 0 |
| Phase-6 approvals (`recruit_approval`) | 25 / 0 |
| Offer approval context (`offer_appr_dept`) | 2 / 0 |
| M4 hiring request (`m4_hiring_request`) | 78 / 0 |
| M4 correction (`m4_correction`) | 107 / 0 |
| Recruitment admin (`hiring_admin`) | 11 / 0 |
| Background/public entitlement (`m8_public_background_entitlement`) | 125 / 0 |
| Master entitlement (`m10_master_entitlement`) | 93 / 0 |

Operations, Reporting, Quality, Money, Workforce and Marketplace are inside the
whole-suite figures above; both engines ran the identical source.

## Four existing suites had to be corrected, and why that is a finding

The §25 write-path gate failed `recruit_approval`, `offer_appr_dept`,
`p3m1_approval` and `p3m2_matrix` the moment it was added: **their fixtures
configured approval policy with nobody signed in**, which the product has never
permitted. One of them even carried the comment *"A rule an administrator would
configure"* above a call made by no administrator at all.

They now act as the administrator they always claimed to be, and hand the session
straight back so every other assertion still runs as whoever it was written for.
**No assertion was weakened, removed or skipped**; the fixtures were made
truthful.

## Two of my own test defects, found by mutation and fixed

- **M3.13** asserted branch refusal against `appr_can_act()`, which by design only
  answers *"does this step name you"*. The refusal happens in `appr_guard()`. The
  assertion now tests where the decision is actually made, and states both
  questions so the distinction is visible rather than papered over.
- **M3.1**'s idempotence check compared two timestamps taken in the same second,
  so it could not tell whether the guard existed — mutation **M04** survived
  against it. It now checks against a deliberately different deadline.
