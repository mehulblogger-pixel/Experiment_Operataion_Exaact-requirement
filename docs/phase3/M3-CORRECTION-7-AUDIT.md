# Phase 3 · M3 CORRECTION #7 — AUDIT (before any code was written)

§1 requires the paths to be traced before changing anything. Two of the findings
below changed the shape of the fix, so this audit was not a formality.

---

## 1 · The paths (§1 A–G)

| | Path | Writer | Guard before #7 |
|---|---|---|---|
| A–D | Hiring Request / Requisition / Offer / Salary **decision refusal** | `appr_email_requester()` → `appr_audit_notify()` | reason allow-list (`APPR_NOTIFY_AUDITED`) + correction #6's openable-subject rule. **No repetition control at all.** |
| E | **Scheduler** reminder / escalation | `appr_tick()` → `appr_audit_sla()` | correction #6's openable-subject rule |
| F | Event creation | `act_log()` (`lib/activity.php`) — the single spine | blanks an unsupported kind, still writes the row |
| G | **Existing idempotency** | see below | — |

## 2 · Existing idempotency mechanisms (§1 G) — there were already three

This matters, because the module already thinks in states rather than events:

* **`recruit_approval_steps.reminded_at` / `reminder_at`** — after a reminder the
  threshold is pushed forward 24 hours. *The identity of a reminder is (step,
  threshold).*
* **`recruit_approval_steps.escalated`** — a once-only flag for a transition.
* **`recruit_approval_steps.activated_at`** — makes activation idempotent so the
  SLA clock cannot be restarted.

The new rule is deliberately built in the same idiom rather than beside it.

## 3 · FINDING — H2 is not "every run". It is every DAY.

Because `reminder_at` already advances 24 hours, a second scheduler run in the
same day is **already** a no-op. The H2 defect is one row **per day, for ever**,
over a chain whose source record is gone.

A fix aimed at "one row per run" would therefore have changed nothing at all.
This is why §5's three-run test is written to **advance the clock between runs**
and to assert that the scheduler *genuinely ran* each time (`acted > 0`) — a
version that simply called `appr_tick()` three times would have passed on the
pre-existing threshold and proved nothing.

## 4 · FINDING — correction #6 had already silenced H2, by dropping the history

`appr_tick()` builds its own request array:

```php
$req = ['id' => …, 'entity' => …, 'entity_id' => …, 'subject' => …, 'status' => 'PENDING'];
```

**There is no `rule_id` in it.** After correction #6, an orphaned chain therefore
had no openable subject — neither the source record nor a policy — so
`appr_audit_subject()` returned nothing and **the SLA event was dropped entirely**.

So H2's symptom had already disappeared, through K3's lost-history branch rather
than by recording the condition once. My own first run of the new test caught it:
**run 1 recorded 0 rows where it should have recorded 1.**

That also re-prices K3. It is not the narrow legacy case I described in the
correction #6 completion report — for the **scheduler it was the normal case**.

**Decision (§8):** the scheduler now carries `rule_id` through, so the event has a
subject that opens and is recorded once. Dropping the event stays as the last
resort only, when neither the record nor a policy can be opened.

## 5 · Classification (§2) — and why it is narrow

| | |
|---|---|
| **PERMANENT** | `ENTITY_UNRESOLVED`, `TENANT_MISMATCH`, `IDENTITY_UNRESOLVED` |
| **TRANSIENT** | `RECIPIENT_INACTIVE`, `RECIPIENT_UNLICENSED`, `RECIPIENT_OUT_OF_SCOPE`, `SEGREGATION_BLOCKED`, `RECIPIENT_NOT_VISIBLE`, `NO_EMAIL`, `PROVIDER_FAILURE`, `SENT` |

The test is not "did this fail twice" but **"can re-asking change the answer
without the underlying data changing?"** A person is reactivated, a module is
bought, an address is added, a provider recovers — all transient, all still
recorded every time. And when a permanent condition *is* resolved by a data
change, the reason changes with it, which produces a different key and therefore a
new event.

## 6 · The identity (§3/§4) — what is deliberately NOT in it

`PC | event | entity | entity_id | R<chain> | S<step> | reason`

* **Not `rule_id`.** Two dead offers governed by one policy are two conditions,
  and a chain with no rule still has an identity. §4's two cases are tested
  explicitly *and the fixtures are asserted* to have / not have a rule — which is
  what K2 was: an assertion that passed because its fixture had no rule.
* **No timestamp.** That is exactly what made every observation unique.
* **Tenant is the connection**, not a column — one database per workspace.
* **Event class included**, so an escalation on a step that has already recorded a
  reminder condition is still a new event (§7).

## 7 · Where duplicate detection can safely occur

On the **existing spine**. A new additive, nullable `activities.cond_key` column
holds the canonical key, so "have we already said this?" is one exact-match query.
No second event engine, and no deduplication derived by parsing display prose —
which would have broken the moment a subject line changed.

A check that cannot run (`cond_key` missing, query error) returns *not seen*, so a
failure of the suppression mechanism can never swallow a first observation.
