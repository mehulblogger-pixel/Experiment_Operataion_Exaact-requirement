# RB-3 · Step 3 — transaction map

*Every persistent write on the acceptance path, traced from the code rather than
assumed. Companion to `RB3-STEP3-TRANSACTION-BOUNDARY-STOP-REPORT.md`, which
carries the two architectural decisions this map implements.*

---

## 1. The flow as built

```
Recruiter posts /candidate-stage  (to_stage = ACCEPTED)
      │
      ├─ ops_require(is_coordinator_level())            route gate
      ├─ M4 execution boundary  ·  M6 early seat courtesy refusal
      │
      ▼  PRE-TRANSACTION REFUSALS  —  rcv_refusal_before_transaction()
      │     · application exists and is in scope
      │     · already converted?              → ALREADY      (idempotency, §13)
      │     · branch resolvable?              → NO_BRANCH    (BD1)
      │     · duplicate staff + the tick      → WORKFORCE_MATCH
      │       (calls Step 2's workforce_matches() / workforce_ack_ok();
      │        nothing is re-implemented)
      │   any refusal → audited, nothing written, candidate untouched
      │
      ▼  rcv_prewarm_migrations()            ← 8 migrations, OUTSIDE the transaction
      │
      ▼  BEGIN
      │   1 · SELECT requisitions … FOR UPDATE      lock  (MySQL; SQLite serialises)
      │   2 · seat re-checked under that lock       → SEAT| refusal
      │   3 · UPDATE candidates SET stage, decided_at, drop_*
      │   4 · rkpi_stage_log()  → INSERT candidate_events      (must succeed)
      │   5 · rcv_convert()  — joins this transaction, commits nothing, re-throws
      │         5a · INSERT inspectors        (emp_code claimed under Step 1's key)
      │         5b · UPDATE candidates SET inspector_id   (conditional)
      │         5c · INSERT cx_identity_link  (when entitled — BD2)
      │   6 · UPDATE requisitions SET hired_inspector_id
      │   7 · rful_enforce_candidate()   source credit   (swallows its own errors)
      │   8 · reqf_sync()                requirement standing (swallows its own errors)
      ▼  COMMIT
      │
      ▼  rcv_audit_or_report()   ← OUTSIDE (invariant I41), and never silent
```

**Lock order: requirement → candidate → workforce insert.** One direction
everywhere. `rcv_convert()` takes the candidate row itself; nothing takes it
before the requirement.

## 2. Every persistent write, and how it participates

| # | Write | Where | In the transaction? |
|---|---|---|---|
| 1 | `candidates.stage / decided_at / drop_*` | `ops.php` | **yes** |
| 2 | `candidate_events` (KPI stage ledger) | `recruit_kpi.php:90` | **yes**, and a failure stops the acceptance |
| 3 | `inspectors` (the workforce record) | `recruit.php:1332` | **yes** |
| 4 | employee number | inside #3, `emp_code_claim()` | **yes** — a rollback consumes none |
| 5 | `candidates.inspector_id` | `recruit.php` | **yes** |
| 6 | `cx_identity_link` | `recruit.php` | **yes**, when entitled |
| 7 | `requisitions.hired_inspector_id` | `ops.php` | **yes** |
| 8 | `candidates.allocation_id` (P4 credit) | `recruit_fulfil.php:803` | **yes** |
| 9 | `requisitions.status` (`reqf_sync`) | `reqfulfil.php:153` | **yes** |
| 10 | `activities` (audit) | `activity.php:467` | **no — deliberately.** Invariant I41 |

## 3. Helpers that could have broken atomicity — checked, not assumed

| Helper | Commits internally? | Own connection? | Verdict |
|---|---|---|---|
| `rcv_convert()` | **no** — detects a borrowed transaction, joins it, commits nothing, **re-throws** | no | safe |
| `rkpi_stage_log()` | no | no | safe; its failure is now propagated |
| `reqf_sync()` | no | no | safe; swallows its own errors |
| `rful_enforce_candidate()` | no | no | safe; swallows its own errors |
| `emp_code_claim()` | no | no | safe; retries inside the caller's transaction |
| the 8 migrations | **DDL — implicitly commits on MariaDB** | no | **run BEFORE `BEGIN`**, and the warm-up refuses to run inside one |

**Nothing uses a second connection. Nothing commits internally.**

## 4. External side effects — none

Searched the whole acceptance path for mail, notification, webhook, HTTP,
document generation and external services. **There are none.** `licence_blocks()`
is read-only. No outbox or event mechanism is needed, and none was built.

## 5. Idempotency (§13) — reused, not invented

No new table and no new mechanism. `candidates.inspector_id` already records
which team member an application produced, and it is the guard: the
pre-transaction pass refuses `ALREADY`, and `rcv_convert()` refuses it again on
its own so a forged POST gains nothing. The conditional
`UPDATE … WHERE inspector_id IS NULL OR = 0` remains the database-level backstop.

## 6. Failure points and what rolls back

| Failure | Refused where | What survives |
|---|---|---|
| not authorised · not in scope · already accepted · no branch · unacknowledged duplicate | **before `BEGIN`** | everything — no transaction was opened |
| seat taken (decided under the lock) | inside, step 2 | nothing |
| the application vanished | inside, step 3 | nothing |
| stage ledger unwritable | inside, step 4 | nothing |
| workforce insert fails | inside, step 5 | nothing |
| employee number unobtainable | inside, step 5 | nothing, and **no number consumed** |
| identity ledger refuses | inside, step 5 | nothing |
| deadlock / lock-wait / database busy | inside | nothing; the recruiter is told to try again |
| audit write fails | **after COMMIT** | **the hire stands** (I41), and the loss is counted and reported |
