# Phase 3 · M3 CORRECTION — COMPLETION REPORT
## Notification Visibility, Entitlement & Escalation Reminder Control

## F1 — root cause

One assumption in one place: **holding the approver's role was treated as
eligibility to be notified.** `appr_step_recipients()` started from
`appr_role_emails($role)` — *"every active holder of this role, anywhere"* — and
nothing narrowed it; `appr_email_escalate()` did the same for the escalation role
and its all-administrators fallback. The approval layer had spent M1 and M2
deciding carefully who may see a hiring request, and the notification layer never
asked. E-mail leaves the application, and M3's reminders made it repeat.

## F1 — fix

Recipients are now a **candidate** list and **the existing model decides**. No
second authorization engine.

## F1 — notification architecture

```
CANDIDATES                                   THE DECISION
named user, or the holders of a role   ──►   appr_may_be_asked()
+ their current delegates (M2 rules)           = appr_visible($step,$req,$user)
                                               = appr_can_act() AND appr_guard()
```

`appr_visible()` is the function `/my-approvals` already uses. **If a person would
not see it in their queue, they are not written to.** One line, on the path every
candidate crosses.

The single obstacle: `appr_guard()` reads the **current** user — entitlement,
branch scope and segregation all do — so asking *"may this other person be told"*
means asking **as** them. `appr_as_user()` swaps the session, asks, and restores
it in a `finally`, so no caller can leave the session misplaced even on an
exception. The alternative was user-parameterised copies of four existing rules,
which is precisely the drift M2's correction was about.

## F1 — recipient eligibility: two levels, same primitives

| Level | Used by | Asks |
|---|---|---|
| **Actionable** — *"approve this"* | assignment, reminder | `appr_visible()` in full, **segregation included** |
| **Informational** — *"this is late"* | escalation | entitlement + record exists + branch scope, **without segregation** |

Segregation is dropped for the informational level deliberately: telling the
person who **raised** a request that it has gone overdue is the message's purpose.
Everything else still applies, so an escalation contact who cannot see the branch
is not told what it concerns, and the body states that it confers no authority.

## F1 — role path · named-user path · delegation path

- **Role** — holders are candidates only. The other-branch holder of the same role
  is refused, and their queue, the engine and the mailer now say the same thing.
- **Named user** — audited separately and **not assumed safe**: a named approver
  outside the branch scope is refused by the same line, and the engine refuses
  their decision too.
- **Delegation** — M2's rules are untouched and now also decide the e-mail:
  expired, revoked, wrong-branch and inactive-delegator each stop the notification
  exactly as they stop the authority.

`appr_role_emails()` is **deleted** — nothing calls it, and it embodies the defect.

## F1 — segregation · tenant · branch

Segregation is proved in **both halves**, as required: the decision is refused
*and* the e-mail is not sent. Tenant isolation is structural (one database per
tenant) — a foreign user id resolves to no recipient. Branch protection is M2's
entity-aware model, asked rather than re-implemented.

## F2 — root cause, fix, write-path enforcement

**Mine, introduced by M3.** Policy writes got `appr_config_can()` (entitlement
then capability); delegation writes were left asking `hiring_admin_can()` alone.
So a workspace that had stopped paying for recruitment could not edit its approval
matrix but **could still move approval authority** — the more privileged of the
two. The same recurring shape: a question asked on one path and not carried to its
sibling.

`appr_delegation_save()` and `appr_delegation_revoke()` now ask **the same gate**,
at the write — create, edit, revoke and deactivate-through-save all covered — so a
direct POST or AJAX call meets it exactly as the screen does. The route gate stays
as the second boundary, no longer the only one.

## F3 — root cause, reminder policy, escalation behaviour

The two signals ran the wrong way round: the escalation contact was told **once,
ever**, while the approver was reminded **every day without limit**, each time with
an e-mail and an activity row. Unbounded, and backwards.

Now: reminders before escalation as configured; escalation once; **after
escalation the routine reminder stops**. The approval does not go quiet — it stays
`PENDING`, reads **Escalated** on every screen, and is still counted on the
dashboard. *Repetition stopped; visibility did not.* No arbitrary reminder count
was invented, because the policy architecture has no field for one. The scheduler
still approves nothing, rejects nothing, reassigns nobody and manufactures no
authority.

## Files changed

`lib/recruit_approval.php` · `tests/test_p3m3c_notify.php` (new) ·
`deploy-check.php` · `docs/02-permission-matrix.md` · four documents in
`docs/phase3/`.

Nothing outside the approval library was touched.

## Database changes · migration status

**None.** No new table, no new column, no migration.

## Tests

New suite **68 assertions, 0 failed**, on both engines, covering F1 A–O, F2 1–11
and F3 1–15. Recipient lists are asserted as **lists of addresses**, and the
decisive cases again against `email_log`.

## Mutations

**17 designed · 15 CAUGHT · 2 survived as a proved mutual redundancy.** M01, M08
and M09 each restore one audited defect verbatim, so none can quietly return. The
two survivors are the same active-status guarantee written in two places; removing
**both** is CAUGHT (`M3.8 · an INACTIVE DELEGATE is not written to either`), which
is the evidence that the protection is real and tested rather than absent. The
brief's *"remove tenant isolation"* has no line to remove — isolation is
structural — and is asserted behaviourally instead of mutated.

## SQLite · MariaDB · regression

| | |
|---|---|
| **SQLite** | **9286 passed, 0 failed** |
| **MariaDB 10.11.14** (fresh `exaact_m3e`) | **9287 passed, 0 failed** |

M3 correction 68/0 (both engines) · M3 SLA 176/0 · M1 114/0 · M2 111/0 ·
Phase-6 approvals 25/0 · offer context 2/0 · M4 hiring request 78/0 · M4
correction 107/0 · recruitment admin 11/0. Operations, Reporting, Quality, Money,
Workforce, Marketplace and the Command Centre are inside the whole-suite figures.
Nothing skipped, weakened or re-baselined.

The two engines were run **serially**; running them concurrently in one checkout
produces five spurious failures from a pre-existing test-isolation defect
(`test_saas_clean_company` writes a real `phpapp/tenants.php`), recorded in the M3
completion report and not this correction's to fix.

## Limitations

1. **Escalation does not transfer authority** — unchanged, by design.
2. **No mandatory-approval switch** — M2 behaviour preserved.
3. **No per-tenant time zone** — the platform has never had one.
4. **Parallel approval unsupported**; four rule conditions unimplemented.
5. **Offer/Salary SLA events are logged but not linkable** on the timeline.
6. **Eligibility is evaluated per candidate**, which costs a session rebuild each —
   correct, and deliberately not optimised (see F5).

## Deferred F4 / F5

- **F4** — the Recruitment Command Centre remains **company-wide by design**. No
  branch filtering was added. Documented as a known design characteristic.
- **F5** — the unbounded scans in `appr_waiting_on_others()` and
  `appr_step_recipients()` are **deferred**, and F1's per-candidate evaluation adds
  to that cost. Not optimised, because correctness was the brief.

## Manual / UAT evidence

**None.** This correction changed no screen, so there was nothing new to look at;
but the M3 screens themselves still have not been exercised by a human in a
browser, and Phase 1 UAT on MilesWeb production remains open.

## Commit · working tree

See the final message. Mutations ran against a copy outside the repository, so the
working tree is clean.
