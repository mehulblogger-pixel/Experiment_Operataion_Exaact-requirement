# M3 CORRECTION #13 — ADVERSARIAL AUDIT

An attack on my own completed work, following the instruction that came with the
correction: **do not stop when the caller receives FAILED — follow it to the
actual notification behaviour.** Probes ran against a **copy** of `phpapp/` in the
scratchpad; **no product code was modified**. Every result below is measured on
both engines.

**Five findings. Two are material. One is a falsehood in my own contract and in
the log line I wrote to announce it.**

---

## B-1 · MATERIAL — I moved the missing link one step up and left it there

Correction #13's entire point was that a truthful answer with no reader is inert.
I gave `appr_audit_notify()` and `appr_audit_sla()` a five-valued outcome. Then I
checked who reads **that**.

Nobody. Searched across every non-test file outside the one that defines them:

| symbol | production readers outside `lib/recruit_approval.php` |
|---|---|
| `APPR_COND_RECORDED` / `_UNARMED` / `_SUPPRESSED` / `_NONE` / `_NO_SUBJECT` | **0** |

And the five call sites that invoke the two functions I "fixed" all discard it:

```
lib/recruit_approval.php:866    appr_audit_sla($req, $step, 'SLA started', …);          ← dropped
lib/recruit_approval.php:1349   appr_audit_sla($req, $s, 'Approval overdue — escalated' …); ← dropped
lib/recruit_approval.php:1371   appr_audit_sla($req, $s, 'Approval reminder sent', …);  ← dropped
lib/recruit_approval.php:1790   if (!$u) { appr_audit_notify($req, $result, $why); return $why; }   ← dropped
lib/recruit_approval.php:1792   if ($why !== '') { appr_audit_notify($req, $result, $why); return $why; }  ← dropped
```

The chain now reads:

```
marker written?  →  truthful status  →  act_log records it  →  caller consumes it
                                                                      ↓
                                                            returns RECORDED/UNARMED
                                                                      ↓
                                                                 ?????????
```

**This is the same defect, one link along.** #13's completion report says the
contract "now has real readers". It has *one more* reader than it had. The chain
still ends in nothing, and the scheduler that runs every day is still not told
that suppression failed to arm.

That is not a reason to have skipped #13 — the status genuinely had to survive
`act_log()` first, and it now does. But the correction did **not** deliver a
product guarantee. It delivered one more link of a chain that still has no end.

---

## B-2 · MATERIAL — the only signal is a server log the customer cannot see

Probe B2, both engines: **not one user-facing screen** reads
`act_last_cond_status()`, any `APPR_COND_*` value, `act_optional_error()` or
`act_optional_state()`.

So when duplicate suppression silently stops working, the sole evidence is a line
written by `@error_log` into the PHP error log on the shared host. No coordinator,
manager, administrator or finance user has any way to see it, and EXAACT's own
diagnostic surface — three corrections' worth — displays nothing.

"Observable to the caller" was satisfied literally, and "observable to the
business" was not attempted. My completion report should have said which one it
meant.

---

## B-3 · MATERIAL — `UNARMED` is not true, and my own log line says so falsely

I defined `APPR_COND_UNARMED` as **"row written, marker NOT persisted"**.

Probe B6 blocks the **core INSERT** with a genuine `BEFORE INSERT` failure
(`RAISE(ABORT)` on SQLite, `SIGNAL SQLSTATE '45000'` on MariaDB), so the event row
is never written at all. On both engines:

| measured | result |
|---|---|
| event rows written | **zero** — the core INSERT genuinely failed |
| core error channel | populated, correctly |
| what the caller returns | **`APPR_COND_UNARMED`** |
| what `UNARMED` is documented to mean | *"row written*, marker not persisted" |

The first half of the documented meaning is **false** in this case. Worse, the
log line I wrote to announce the failure ends with the words:

> `… duplicate suppression is NOT armed for this condition; the event itself was recorded`

**The event was not recorded.** I wrote a diagnostic that states the opposite of
what happened, in exactly the scenario the diagnostic exists for.

**Why my own suite never caught it:** C13.3 asserts `UNARMED` *and* `S1 holds —
the EVENT itself was still written` in the same breath. I only ever exercised
`UNARMED`-with-a-row. The case where nothing was written was never tried, so the
contract was never tested against its own wording. A test that asserts two things
together can never tell you they have come apart.

---

## B-4 · The documented fail-safe is H2, renamed

Probe B4 blocks the marker permanently and runs thirty daily reminders through
the real production caller. Both engines:

- all thirty return `UNARMED` — correct and consistent;
- **thirty identical rows are written, one per tick, unbounded.**

H2 was *"the scheduler has written one row a day, for ever, over a chain whose
source record is gone."* Under a permanently unwritable marker, that is exactly
what happens again. #13 documented this as the chosen fail-safe and called the
cost *"a visible, correctable annoyance"*.

**Thirty rows a month for ever is not an annoyance**, and the phrase understates
it. The reasoning behind fail-open still stands — failing closed would suppress on
a marker that does not exist, and a second store is forbidden — but the honest
statement is: *when the marker cannot be written, H2 returns in full, and the only
thing #13 added is that it is now attributable in a log nobody reads.* My
completion report should have said that in those words.

The unbounded case is reachable wherever the `cond_key` column cannot be created
or written — an older MySQL host, a restricted grant, a full disk — and it is the
same population of hosts the optional-column machinery exists for.

---

## B-5 · The log floods in exactly the case it is meant to report

Thirty ticks produced thirty identical `error_log` lines, one per tick, for ever.
A diagnostic whose failure mode is to write the same line daily until someone
notices is not a diagnostic; and because of B-2 the only person who could notice
is the host administrator, not the customer.

---

## What survived the attack

- **The status genuinely survives `act_log()`**, is genuinely workspace-keyed
  (proved by real database switching after `M-Y1-GLOBAL` exposed that it was
  untested), and is genuinely reset per event so no event inherits another's.
- **Both production callers genuinely consume it**, and the mutation that puts
  either of them back to "status ignored" is caught by a production-path
  assertion, not by a helper test.
- **Suppression still works when the marker stores**: ten identical refusals, one
  row; and it re-arms by itself once the obstruction clears.
- **No approval decision, authority, permission or lifecycle changed**, and the
  full regression is clean on both engines.

---

## The pattern, stated plainly

#12 fixed the helper and not the caller. #13 fixed the caller and not **its**
caller. The defect is not any one missing link — it is that I keep repairing the
link I was shown and declaring the chain sound.

The chain the instruction asked for, with today's real state marked:

```
act_set_cond_key()          ✅ truthful          (#12)
        ↓
act_log()                   ✅ carries it        (#13)
        ↓
appr_audit_notify / _sla    ✅ consumes it       (#13)
        ↓
appr_email_requester, appr_tick, the scheduler   ❌ discards it      (B-1)
        ↓
duplicate-suppression decision                   ⚠️ fails open, unbounded (B-4)
        ↓
actual behaviour a user or admin can see         ❌ nothing          (B-2)
```

Ranked for whoever writes the next correction prompt:

| # | finding | live? | severity |
|---|---|---|---|
| B-3 | `UNARMED` claims a row was written when none was; the log line states the opposite of the truth | **yes** | **high** — a false diagnostic is worse than none |
| B-1 | the five call sites above the fixed callers all discard the outcome | **yes** | **high** |
| B-4 | under a permanently unwritable marker the fail-safe is H2 unbounded, and #13 understated it | **yes** | **medium-high** |
| B-2 | no screen anywhere surfaces any of it | **yes** | medium |
| B-5 | the diagnostic floods the log daily, for ever | **yes** | low |

Nothing here was fixed. No product code was changed during this audit.
H1, H3, S2, S3, U2 and the #12 audit's A-2…A-7 all remain open.
**M3 is not accepted. M4 is not started.**
