# Phase 3 · M3 — COMPLETION REPORT
## SLA, Escalation, Inbox & Notifications

## Scope

Make the approval workflow M1 and M2 built **operationally manageable**: who must
act, by when, what is pending, how late it is, and what happens if nobody acts.

The audit came first, and its headline is that **most of M3 already existed**.
Phase 6 shipped a per-level SLA, a reminder/escalation tick, an entitlement-gated
cron and a mailer that logs every attempt. M3 is therefore a correction-and-
completion milestone against that implementation, not a build.

## Files changed

`lib/recruit_approval.php` · `lib/schedule.php` · `lib/recruit_cc.php` ·
`views/ops/my_approvals.php` · `views/ops/hiring_request.php` ·
`views/ops/recruitment_cc.php` · `tests/test_p3m3_sla.php` (new) ·
`tests/test_p3m1_approval.php` · `tests/test_p3m2_matrix.php` ·
`tests/test_recruit_approval.php` · `tests/test_offer_appr_dept.php` ·
`deploy-check.php` · `docs/02-permission-matrix.md` · six documents in
`docs/phase3/`.

## Database changes · migrations

**No new table.** Three additive, idempotent columns on the **existing**
`recruit_approval_steps`, through the existing `ensure_column()`:

| Column | Why |
|---|---|
| `sla_days` · `reminder_days` | the policy, frozen onto the step at chain creation (§31) |
| `activated_at` | when the step became the one being waited on (§7) — and what makes activation idempotent |

They exist because §7 and §31 pull in opposite directions and both must hold: the
clock must start on activation, while the policy must be frozen at creation. That
means the step has to carry its own policy. Existing rows are untouched; a chain
created before M3 keeps the dates it already had.

## Reused · extended · built

- **Reused unchanged** — `cron.php` and its per-module `$m8()` gate; `ops_mail()`,
  `email_log` and the `/notifications` outbox; `act_log()` and the activity spine;
  `appr_inbox()` / `appr_visible()` / `appr_can_act()` / `appr_guard()`;
  `is_working_day()` / `office_holidays()`; `hiring_admin_can()`; `licence_blocks()`.
- **Extended** — `appr_start()`, `appr_act()`, `appr_tick()`, the three e-mail
  helpers, `appr_delegators_for()` (refactored onto one shared validity rule),
  `/my-approvals`, the hiring-request screen, the Recruitment Command Centre.
- **Built** — `appr_activate_step()`, `appr_due_at()`, `appr_sla_state()` and its
  three companions, `appr_step_recipients()`, `appr_delegates_of()`,
  `appr_valid_delegations()`, `appr_audit_sla()`, `appr_delivery_note()`,
  `appr_sla_summary()`, `appr_waiting_on_others()`, `appr_config_can()`.
- **Built as tables** — nothing.

## SLA model

Per **approval level**: `sla_days`, `reminder_days`, `escalate_role` /
`escalate_user_id`. Snapshotted onto the step at chain creation. Counted in
**working days** at that request's branch (Sundays and that branch's public
holidays), reusing the calendar the application already kept.

**The clock starts when the step becomes active, and only then.** A level not yet
reached has no due date and reads *Not started*.

## Reminder model

At the reminder point, to the approver **and to anyone currently holding their
authority by delegation**. Once. Its identity is (step, threshold), and moving
the threshold forward is what makes a second run — or a tenth — a no-op.

## Escalation model

At the SLA point, once, to the configured contact. **It is notification, not
authority**: nothing in the approval engine reads the escalation columns, and the
recipient can approve only if the approval rules independently say so. Where no
recipient resolves, the timeline records *NO RECIPIENT*, never a success.

Authority transfer on a deadline is **not supported and was not invented** — see
limitations.

## Notification model

One mailer, already honest: SMTP → PHP `mail()` → recorded failure, every attempt
written to `email_log` with its error, readable on the existing outbox screen.
Nothing claims a delivery it did not make.

## Security model

```
ENTITLEMENT → CAPABILITY → ACTUAL AUTHORITY → TENANT → BRANCH/ENTITY SCOPE
            → SEGREGATION → VALID STATE → ACTION → AUDIT
```

M3 adds entitlement questions at four more places (the scheduler, the
configuration gate, the waiting list, the dashboard KPI) and removes none. The
configuration gate asks entitlement **before** capability, so a master on a
workspace without recruitment is refused.

```
SLA ≠ AUTHORITY        ESCALATION ≠ AUTHORITY
NOTIFICATION ≠ AUTHORITY    INBOX VISIBILITY ≠ AUTHORITY
```

## Tenant isolation · branch isolation

Tenant isolation is structural — one database per tenant, no `tenant_id` column —
and M3 introduced no cross-database read. A substituted foreign id simply does not
exist and is refused. **One pre-existing violation of that model was fixed
because M3 depends on it:** `office_holidays()` cached without keying on
`db_epoch()`, so a process serving two workspaces would have answered the second
with the first one's public holidays. It is now keyed, and a behavioural test
holds it.

Branch isolation is M2's entity-aware model, untouched: no global
`WHERE office_id = current_office` was introduced, and the test asserts its
absence.

## Delegation interaction

M2's semantics are unchanged. M3 needed the delegation table read the other way
round, and that is a second **reading**, never a second **rule**: both directions
now come through `appr_valid_delegations()`, the one place dates, entity, branch
and delegator-active status are decided. Expired, revoked, wrong-branch and
inactive-delegator delegations stop the notification exactly as they stop the
authority.

## Inbox behaviour

`/my-approvals` shows, per item: entity, subject, level, requester, raised date,
**with you since**, **due date**, **SLA status as one sentence**, and an
escalation marker — plus a read-only **"waiting on someone else"** list of the
viewer's own requests. The hiring-request screen states the two facts separately:
*Approval: Pending · SLA: Overdue by 2 days*. The Recruitment Command Centre gains
four approval tiles on the dashboard that already exists.

## Test counts

**172 → 176 assertions** in `tests/test_p3m3_sla.php`, 0 failed, across 15
sections. Full breakdown in `M3-TEST-RESULTS.md`.

## Mutation counts

**27 designed · 26 CAUGHT · 1 withdrawn.** Full table in
`M3-MUTATION-RESULTS.md`. Three survived on the first pass; **all three were gaps
in my own tests**, each closed and re-run to CAUGHT, none excused.

## SQLite result

**9218 passed, 0 failed.**

## MariaDB result

**MariaDB 10.11.14, fresh database `exaact_m3d`: 9219 passed, 0 failed.**
Identical source, run serially.

## Full regression

M1 approval 114/0 · M2 matrix & delegation 111/0 · Phase-6 approvals 25/0 · offer
approval context 2/0 · M4 hiring request 78/0 · M4 correction 107/0 · recruitment
admin 11/0 · background/public entitlement 125/0 · master entitlement 93/0.
Operations, Reporting, Quality, Money, Workforce and Marketplace are inside the
whole-suite figures. **No test was weakened, deleted or skipped.**

Four existing suites had to be corrected — their fixtures configured approval
policy with nobody signed in, which the product has never permitted. They now act
as the administrator their own comments already claimed.

## Known limitations

1. **Escalation does not transfer authority.** The model has no way to move an
   approval from one approver to another on a deadline, and M3 did not invent one.
   The supported route is M2 delegation. *(Intentional limitation.)*
2. **No mandatory-approval switch.** M2's behaviour is preserved deliberately: where
   no rule matches, the request is decided directly. Still an open business
   decision. *(Intentional limitation, unchanged.)*
3. **No per-tenant time zone.** The platform has never had one — server time is the
   only clock, for every module. Stated rather than silently assumed.
   *(Deferred architecture debt.)*
4. **Parallel approval is still unsupported** — levels are sequential.
   *(Deferred.)*
5. **Four rule conditions remain unimplemented** — `request_type`,
   `employment_type`, `priority`, `required_by`. *(Deferred.)*
6. **SLA events for OFFER and SALARY are logged but not linkable.** Those entity
   kinds are not registered on the activity timeline, so their events carry the
   entity and id in the subject rather than a link. Registering them means touching
   modules M3 was told not to change. *(Deferred.)*
7. **The escalation fallback is company-wide.** With no escalation contact
   configured, the notice goes to all active administrators, not a branch subset.
   Pre-existing Phase-6 behaviour, deliberately not changed. *(Pre-existing.)*

## Defects found and classified (§30)

| Defect | Class |
|---|---|
| Every step's SLA clock started at chain creation | **M3 defect (pre-existing, Phase 6)** — fixed |
| No SLA event was audited at all | **pre-existing** — fixed |
| Approval policy authorized only by the route | **pre-existing** — fixed |
| A delegate was never notified | **pre-existing** — fixed |
| A step marked escalated with nobody told | **pre-existing** — fixed |
| "Overdue" re-implemented per view; no Due soon | **pre-existing** — fixed |
| Calendar days although a working-day calendar existed | **pre-existing** — fixed |
| `office_holidays()` cache not keyed on `db_epoch()` | **pre-existing** — fixed, because M3 depends on it |
| The inbox could not say "waiting on someone else" | **pre-existing** — fixed |
| `appr_email_requester()`'s `||` "portability bug" | **NOT A DEFECT — my audit was wrong**, withdrawn, change reverted |
| Four suites configured approval policy with nobody signed in | **pre-existing test defect** — fixtures made truthful |
| Two of my own new tests could not tell a mutation apart | **my defect** — both closed |
| `test_saas_clean_company` writes `phpapp/tenants.php` and a concurrent run then fails `test_tenant_signup` | **pre-existing test-isolation defect, not M3's.** It surfaced only because I ran two suites concurrently in one checkout — my error, not the product's. Serial runs are clean. Recorded, not silently altered |

## Manual / UAT evidence

Automated only. The screens were changed (`/my-approvals`, the hiring-request
approval panel, the Recruitment Command Centre) and **have not been exercised by a
human in a browser**. Phase 1 UAT on MilesWeb production remains open and this
milestone does not close it.

## Commit · working tree

See the final message. Mutations ran against a copy outside the repository, so the
working tree is clean.
