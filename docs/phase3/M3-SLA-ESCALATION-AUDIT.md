# Phase 3 · M3 — SLA, Escalation, Inbox & Notifications — AUDIT

Written **before** any code was changed. Its purpose is to establish what EXAACT
already does, so M3 builds only what is genuinely missing.

The headline: **most of M3 already exists.** Phase 6 shipped a per-level SLA, a
reminder/escalation tick, an entitlement-gated cron and a mailer that logs every
attempt. M3 is therefore a *correction and completion* milestone, not a build.

---

## 1. Existing SLA functionality — SUBSTANTIAL

`recruit_approval_levels` already carries the whole SLA policy, **per level**,
which is what §6 asks for (the SLA belongs to the step, not the request):

| Column | Meaning |
|---|---|
| `sla_days` | days the approver has |
| `reminder_days` | when the nudge goes out |
| `escalate_role` | who is told if it is not done |
| `escalate_user_id` | …or a named person |

`recruit_approval_steps` carries the **snapshot** of that policy per step:
`sla_due`, `reminder_at`, `reminded_at`, `escalated`.

**§31 (immutability) is already satisfied by construction.** The due date is
stamped onto the step, so an administrator editing the policy tomorrow cannot
rewrite an approval that is already running.

## 2. Existing notification functionality — PRESENT

`appr_email_approver()` (assignment + reminder), `appr_email_escalate()`,
`appr_email_requester()` (decision). All route through one helper, `appr_mail()`.

## 3. Existing email infrastructure — COMPLETE, AND ALREADY HONEST

`ops_mail()` (`lib/ops.php:1873`) tries SMTP, falls back to PHP `mail()` when
`OPS_MAIL_ENABLED` is set, and otherwise records *"mail disabled"*. **Every
attempt — success or failure — is written to `email_log` with `sent_ok` and
`error`.** It never throws and never reports a send it did not make.

**§20 is already met.** There is a read-only outbox screen over that table
(`ops_notifications()`, `/notifications`) with a failed-send filter.

**M3 must not build a notification framework. There is one.**

## 4. Existing scheduled / background execution — PRESENT AND ENTITLEMENT-GATED

`cron.php` is the runner (CLI or URL with `CRON_KEY`). It derives its library
list from `index.php` rather than hardcoding it, and **gates every step on module
entitlement** via `$m8('hiring', 'People & hiring')`. The approval tick is
already inside that gate (`cron.php:421`).

**§22 needs nothing built.** One invocation resolves one tenant from `HTTP_HOST`,
which is how tenant-safety is achieved — an operational requirement to document,
not a gap to code.

## 5. Existing inbox architecture — PRESENT, M2-HARDENED

`appr_inbox()` → `appr_visible()` → `appr_can_act()`. Entity-aware, never wider
than actionability. `/my-approvals` renders it and **already shows a due date and
an Overdue pill**.

## 6. Existing dashboard indicators

Recruitment Command Centre exists and is entitlement-aware. No approval-SLA tile.

## 7. Existing ageing helpers

Several, per module (`cmp_sla()`, `tapi_bd_sla_status()`, `idems_run_sla_escalations()`).
None is shared; none applies to recruitment approvals.

## 8. Existing audit trail — PRESENT

`act_log()` on the `activities` spine. `HIRING_REQUEST`, `REQUISITION`,
`APPROVAL_POLICY` and `APPROVAL_DELEGATE` are all registered (M1/M2).
**No second audit system is needed or permitted.**

## 9. Existing approval-step data

Sufficient. `sla_due`, `reminder_at`, `reminded_at`, `escalated`, `status`,
`acted_by`, `acted_at` already exist. **M3 needs no new table.**

## 10. Existing delegation interaction — A DISAGREEMENT

M2's delegation is honoured by `appr_can_act()`, so a delegate **sees** the item
in their inbox. But `appr_email_approver()` mails only the named approver or the
role. **The inbox and the email disagree about who is being asked to act.**

## 11. Existing entitlement checks

`licence_blocks('mod.hiring.view')` guards `/my-approvals`; `appr_guard()` guards
the decision; `$m8()` guards the cron. Sound.

## 12. Existing branch / entity scope checks

M2's entity-aware model: `appr_step_context()` establishes an office for
`HIRING_REQUEST` only. **M3 must not globalise this** — it was explicitly
rejected in M1/M2.

## 13. Existing cron / job mechanism — see §4. Reuse.

## 14. Existing reusable components

- **A working-day engine already exists and is branch-aware**: `is_working_day()`,
  `office_holidays()`, `next_working_day()` (`lib/schedule.php`). Sundays and the
  branch's public holidays. §9 says reuse it if it exists. It exists.
- `ops_mail()` / `email_log` / `/notifications`
- `act_log()` / `ACT_ENTITIES`
- `cron.php` + `$m8()`
- `appr_inbox()` / `appr_visible()` / `appr_can_act()` / `appr_guard()`
- `hiring_admin_can()`

---

## 15. Gaps that genuinely require implementation

Ten, each with the evidence that it is real.

### G1 · The SLA clock starts for EVERY step when the chain is created — §7 violation
`appr_start()` stamps `sla_due` and `reminder_at` on **all** levels at once, from
*now*. `appr_act()` advances `current_seq` without restamping.

> A three-level chain with 2 days per level: level 1 is approved on day 3.
> Level 2 becomes active **already one day overdue** and escalates on the next
> tick, before its approver has had a single minute. Level 3 is two days overdue
> before anyone has seen it.

This is the central M3 defect and precisely what §7 forbids.

### G2 · No SLA event is audited at all — §24
`appr_tick()` writes **zero** rows to the activity spine. Reminder, overdue,
escalation and notification outcome are invisible on the timeline.

### G3 · SLA and escalation configuration have no write-path authorization — §25
`appr_rule_save()`, `appr_rule_set_active()`, `appr_level_save()` and
`appr_level_delete()` trust the route. `sla_days`, `reminder_days`,
`escalate_role` and `escalate_user_id` all live on the level. **This is the same
shape M2's correction found in delegation** — a capability asked on the route and
not at the write.

### G4 · A delegate is never notified — §16, §11
See §10 above. The person the system expects to act is not told to act.

### G5 · The requester is never told the outcome on MariaDB — §34
`appr_email_requester()` matches on `TRIM((first_name || ' ' || last_name))`.
`||` is string concatenation in SQLite and **logical OR in MySQL/MariaDB**, so
in production the comparison is against a number and never matches. Silent,
because the call is wrapped in `try/catch`. **A production-only defect that
SQLite testing cannot see.**

### G6 · A step is marked escalated even when nobody was notified — §24, §20
`appr_tick()` sets `escalated=1` unconditionally after calling
`appr_email_escalate()`, which returns nothing. If no recipient resolves, the
step is permanently marked escalated with no one told and no record. That is
reporting an escalation that did not happen.

### G7 · No derived SLA state — §10, §19
"Overdue" is re-implemented inline in the view as `strtotime($sla_due) < time()`.
There is no *Due soon*, no *Escalated*, no single helper — so no screen can
answer §19 consistently.

### G8 · SLA is counted in calendar days though a branch-aware working-day engine exists — §9, §6
`_appr_days()` is `strtotime('+N days')`. A two-day SLA issued on Friday expires
over the weekend and escalates on Sunday.

> **A dependency defect found while auditing this:** `office_holidays()` keeps a
> `static $cache` **not keyed on `db_epoch()`**. Every cache in this codebase must
> be — a violation of exactly this kind caused a proven cross-tenant leak in
> Phase 2 M2. It is pre-existing, but M3 would be *depending* on it, so M3 fixes it.

### G9 · The inbox cannot say "waiting on someone else" — §19
`appr_inbox()` returns only what the user can act on. A requester cannot see, in
one place, that their own request is sitting with an approver.

### G10 · "Overdue widens authority" is untested — §12, §23
Nothing in `appr_can_act()` reads an SLA field, so authority is almost certainly
intact. **Almost certainly is not evidence.** It must be proved behaviourally and
mutation-tested, not asserted from reading the source.

---

## Deliberate non-goals

| Not doing | Why |
|---|---|
| Authority transfer on escalation | §13 — the model does not support it; documented as a limitation |
| Mandatory-approval switch | §26 — M2 behaviour preserved deliberately |
| Tenant timezone engine | **The platform has no tenant timezone setting at all** — no `date_default_timezone_set` anywhere. Server time is the only clock there has ever been. Building one for M3 would be a platform change, not an SLA feature. Documented, not invented |
| New notification table | §21 — `email_log` plus the step's own `reminded_at`/`escalated` already give a send its identity |
| New audit table | §24 — the spine exists |
| New dashboard | §27 — extend the existing one |
| Globalising branch scope in the inbox | §18 — explicitly rejected in M1/M2 |

## Verdict

**Two new columns, no new tables.** M3 is a correction-and-completion milestone
against an existing Phase 6 implementation.
