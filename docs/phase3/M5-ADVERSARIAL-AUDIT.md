# PHASE 3 · M5 — INDEPENDENT ADVERSARIAL AUDIT

Run **after M5 had already been declared accepted**, against the committed tree,
on the assumption that the verdict was wrong. Every probe ran in a throwaway copy
of `phpapp/` in the scratchpad, against the real production functions. **No
product code was modified during the attack.**

**The first verdict was premature.** Four findings, three of them material. All
four are fixed, pinned and re-verified on both engines.

---

## The attack plan

| # | Hypothesis | Method |
|---|---|---|
| F1 | The door validates a *person* without first checking that it was given one | post an array, and a non-numeric string, into the recruiter field |
| F2 | The stale-screen baseline is trusted the same way | post an array as the baseline |
| F3 | One save can change the owner **and** move the record to another branch | set a branch-A recruiter in the same POST that moves the candidate to a branch-B requirement |
| F4 | The dashboard and the workload counter use different scope rules | count the same candidate both ways |
| F5 | The order of refusals leaks the current owner | ask as a user with no permission, with a wrong and then a right baseline |
| F6 | The impersonation used to evaluate scope leaks the session | check the session after `rasg_user_covers()` |
| F7 | A holder who is later deleted strands the record | delete the user, then look |
| F8 | The ledger can be made to record moves that did not happen | replay, and refuse |

---

## F1 — MATERIAL. An array became a person.

**Proved.** PHP casts a non-empty array to the integer `1`. A crafted POST of
`recruiter_id[]=anything` therefore reached the door as **user #1**, was validated
as a real, active, in-scope person, and the work was assigned to them:

```
F1 · posting an ARRAY as the recruiter gives: ACCEPTED
F1 · the column now holds: 1
```

The same cast turns `"abc"` into `0`, which the door reads as **unassign** — so a
typo or a crafted field silently removed the owner instead of being refused.

**Root cause.** Every control in the door was correct, and all of them ran *after*
`(int)`. The identity was decided by a type conversion before a single question
was asked. This is the rule M1 wrote down for names — *"names are display
attributes, never security identities"* — reappearing one layer down: **a person
is never inferred from a coercion.**

**Fix.** `rasg_person_id()` examines the value before anything casts it. Only
three answers are legitimate: nothing at all (unassign), a run of digits, or an
integer. Anything else is `BAD_VALUE`. Applied to the posted value, the baseline,
and the compensator's snapshot.

## F2 — HELD, and hardened anyway

An array baseline was already refused (as `STALE`), so nothing was overwritten.
It is now refused as `BAD_VALUE`, which is the accurate reason.

## F3 — MATERIAL. The scope question was asked about the wrong record.

**Proved.** The candidate save applies ownership against the requisition the
candidate is **currently** on, and writes the new `requisition_id` afterwards. One
POST carrying both therefore checked a branch-A recruiter against branch A — and
left them accountable for a candidate that ended up on a **branch-B** requirement,
which they cannot see.

**Root cause.** The same family this phase keeps producing: the rule was applied
to the record as it **is** rather than to the record the save is **producing**.

**Fix.** `rasg_row_scope()` and `rasg_m4_block()` accept the destination
requisition, and the candidate save passes the posted one. And because moving work
across a branch is a change of accountability even when nobody edits the name,
`rasg_move_blocks()` refuses a move that would strand the existing owner, naming
them and saying what to do.

## F4 — MATERIAL (reconciliation). Two scope rules for one question.

**Proved.** A candidate attached to **no requirement** was counted by the
dashboard and **not** by its own recruiter's workload:

```
F4 · the dashboard scope counts it: 1
F4 · the workload counter counts 0 candidate(s) for that recruiter
```

M5 shipped with two candidate scope rules — `scope_office_clause()` in the command
centre (no office means everybody) and `scope_clause()` in the workload counter
(no office means Ahmedabad). **Two numbers about the same person, disagreeing**,
which is precisely the defect this milestone exists to remove. It survived the
reconciliation suite only because every fixture candidate happened to have a
requirement.

**Fix.** The rule is defined once, in `rasg_cand_scope()`, and `recruit_cc.php`
reads it. Two consumers, one definition.

## F5 — REAL, lower severity. A refusal answered a question the caller could not ask.

**Proved.** The stale-screen test ran before the permission test, so a user with
**no permission at all** could tell a wrong baseline (`STALE`) from a right one
(`NO_PERMISSION`) and read off the current owner one guess at a time.

**Fix.** Authorization was split into `rasg_may_touch()` — entitlement, permission,
the record, the actor's scope — and is answered **before** both the stale answer
and the no-change answer, since both are statements about the current owner. The
same caller now gets `NO_PERMISSION` whatever they guess (probes C6–C8).

## F6, F7, F8 — HELD

The session is restored after scope is evaluated as somebody else; a record whose
holder is deleted is reported as a phantom and can still be reassigned; and the
ledger cannot be made to lie — a replay adds no history, a refusal adds no
history, and the owner is untouched.

---

## An invalid mutation battery, reported rather than discarded

The first re-run of the battery after these fixes reported **21 survivors**. It
measured nothing: the database server had gone down, so the **baseline itself was
FATAL**, and every mutant "survived" against a broken run. One mutation anchor was
also stale, because the fix had changed the line it matched.

The battery now **aborts when its baseline is not clean**, the anchor was
corrected, and it was re-run properly: **baseline 0 · attempted 22 · caught 22 ·
survived 0**, including five new mutations written to attack the fixes themselves
(M18–M22).

## Verification after the fixes

| | SQLite | MariaDB 10.11 |
|---|---|---|
| `p3m5_assign` (with section **P**, the audit's findings) | **127 / 0** | **127 / 0** |
| `p3m5_reconcile` | 35 / 0 | 35 / 0 |
| `p3m5_concurrency` | 27 / 0 | 27 / 0 |
| **Complete regression** | **10920 / 0** | **10921 / 0** |

Mutations: **22 attempted · 22 caught · 0 survived**, clean baseline.

## What this audit did not find

No way past entitlement, permission, the state gate or the M4 boundary. No tenant
leak. No lost update. No phantom ledger row. No way to make an unauthorised write
stick. The controls held; what failed was **what the controls were given** (F1),
**which version of the record they were asked about** (F3), **whether two counters
agreed** (F4) and **the order they answered in** (F5).
