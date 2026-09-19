# Phase 6 · Batch 2 — Reconciliation results

*What changed in the data, what was deliberately left alone, and what the system
now reports instead of hiding.*

---

## 1. Schema changes

| Object | Change | Destructive? |
|---|---|---|
| `cx_identity_link.uq_cand_insp` | new `INT NULL` live key (**U4**) | no — additive |
| `ux_cx_idlink_cand_insp` | new UNIQUE index | no |

**No new table. No column removed or retyped. No row deleted.** Nothing was added
to `candidates`, `inspectors` or `users` — the batch adds one derived key to a
ledger and nothing else.

Added through the existing `ensure_column()`, inside the existing
`connect_identity_migrate()`, behind the existing `db_epoch()` guard. No new
migration mechanism.

## 2. The third axis

One ledger now carries three independent relationships, distinguished by which
columns are populated:

| Axis | Shape | Live key |
|---|---|---|
| professional ↔ inspector | `candidate_id = 0` | `uq_pro_insp`, `uq_insp` |
| candidate ↔ professional | `candidate_id > 0`, `inspector_id = 0` | `uq_cand` |
| **candidate ↔ inspector** (new) | `candidate_id > 0`, `inspector_id > 0` | **`uq_cand_insp`** |

**The candidate predicate was narrowed** from *"has a candidate"* to *"has a
candidate and no inspector"*. Without that, a conversion row would answer as a
candidate↔professional link and the two would share one key — which would forbid
one person from holding both, breaking invariant **I1**. This is a correctness
extension of Batch 1 required by the new axis, not a change to any Batch 1 rule:
the entire Batch 1 battery passes unchanged, which is the proof.

## 3. U4 — what it constrains, and what it deliberately does not

> **One live conversion per application.**

It constrains the **application side only**. Two different applications may
convert to the same team member — a re-hire on different terms, or the same
person supplied through two agencies — because forbidding that would be a
technical rule standing in for a business decision nobody has made
(**R20**, open by owner decision BD3). Probe `G1`-adjacent coverage: the
acceptance battery asserts the allowed case explicitly.

## 4. The back-fill — and why there isn't one

Batch 1 back-filled its live keys onto existing rows. **Batch 2 does not
back-fill historical conversions into the ledger**, and that is deliberate.

`candidates.inspector_id` rows created before this batch have no ledger row.
Writing them in would be a bulk data write nobody approved, over records whose
history this batch cannot see. Instead they are **reported** as
`CONVERTED_NO_LEDGER` and can be linked later through the authorised path.

> **Honest limitation, recorded rather than smoothed over:** that report does not
> distinguish *"this pre-dates Batch 2"* from *"the marketplace add-on is off"*
> (**STATE B**). Both are legitimate, neither is a fault, and the finding says so
> — but an administrator cannot yet tell which is which from the report alone.
> Distinguishing them needs a fact the system does not record today.

## 5. Repeat-run behaviour

| Run | Effect |
|---|---|
| First | column added · key back-filled for rows the new writer creates · index built |
| Later | `db_epoch()` guard · `ensure_column()` no-op · `CREATE UNIQUE INDEX` fails harmlessly inside `try/catch` |
| On a database with live duplicates | the affected index is **skipped** and the duplicate **reported**, exactly as U1/U2/U3 do. Nothing merged |

**Deployment cannot fail.** Every step is wrapped; the worst case degrades to the
behaviour that existed before the batch.

## 6. Engine behaviour

| | SQLite 3.45.1 | MariaDB 10.11.14 |
|---|---|---|
| `ALTER TABLE ADD COLUMN` | ✔ | ✔ |
| Unlimited NULLs in a UNIQUE index | ✔ | ✔ |
| Conditional `UPDATE … WHERE inspector_id IS NULL` decides the race | ✔ | ✔ |
| Driver-specific branch needed | **none** | **none** |

## 7. Operational data reconciliation

### The conversion

| Before | After |
|---|---|
| three browsers → three staff records, two orphaned | **one atomic operation**; the losers roll back |
| `emp_code` blank | generated, as every other creation path does |
| `home_office_id` NULL → read as Ahmedabad | the **requirement's** branch, else the **recruiter's**, else **refuse** |
| no identity-ledger row | written when the workspace may record identity; **explicitly not** when it may not |
| no audit entry | success, refusal and rollback all audited against the application |

### Person groups

| Before | After |
|---|---|
| linking B↔C silently split C from D | the **whole closure** moves; nobody is left behind |
| no audit | every link audited |
| no way to find groups already split | `person_group_repair()` reports them, and repairs only the ones the system's own records prove |

### Contradictory states

`identity_state_findings()` reports seven kinds, each stating *what* is
inconsistent, *which records*, *why it was detected*, *whether repair is safe*
and *whether a person must look at it*:

`CANDIDATE_INSPECTOR_MISSING` · `USER_INSPECTOR_MISSING` ·
`CONVERTED_NO_LEDGER` · `CONVERSION_DISAGREES` · `INSPECTOR_TWO_LOGINS` ·
`TWO_INSPECTORS_ONE_PERSON` · `DUPLICATE_RELATIONSHIP`

**It repairs none of them.** Detection changed nothing — asserted (`H3`).

## 8. What was NOT reconciled — and why

| Not done | Why |
|---|---|
| Repairing dangling references | Detection only; deleting or re-pointing a reference is a business judgement |
| Repairing two-team-members-one-person | **R20 / BD3** — legitimate for a re-hire, a duplicate otherwise. Only a person can tell |
| Reversal of a completed hire | **R21** — undoing a hire means deciding what happens to the staff record, the seat and the money recorded against it |
| Audit for `users.inspector_id` and the per-application bridge | **R18**, deferred |
| Back-filling historical conversions | §4 |
| General staff uniqueness | **R20**, open |

## 9. Data written by this batch

| Category | Rows |
|---|---|
| Business records created by the migration | **0** |
| Business records modified by the migration | **0** |
| Historical records deleted | **0** |
| Derived key column populated | `cx_identity_link.uq_cand_insp`, on rows the new writer creates |
| Person groups repaired | **only** where the system's own recorded link proves a previous state, and every one audited |

The migration itself puts nothing into a live database except a number the
database uses to keep a promise the application was making without it.
