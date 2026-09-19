# Phase 6 · Batch 2 — Completion report
## Person relationship integrity & conversion safety

*What changed, why, and what the evidence says. Batch 2 converges no identities.
It makes the relationships between the representations reliable, so that
convergence — if it is ever approved — has something solid to stand on.*

**Contract:** `P6-BATCH2-PREIMPLEMENTATION-AUDIT.md` ·
`P6-BATCH2-IMPLEMENTATION-PLAN.md` · the owner's locked decisions BD1–BD4 ·
Batch 1 as shipped and **ACCEPTED / LOCKED**.

---

## 1. In plain words

Three things were true of this system, each proved by running it:

1. **Hiring one person three times at once created three staff records.** Two
   belonged to nobody and nothing reported them. Every converted person also
   silently landed in Ahmedabad's branch, whatever branch they were hired for,
   and had no employee code.
2. **Saying "these two applications are the same person" could quietly split an
   existing group** — somebody previously declared the same person became a
   different person, with no audit entry anywhere.
3. **The hiring conversion wrote nothing to the identity ledger.** A person hired
   through recruitment was, to the identity system, connected to nothing.

All three are closed. Nothing was merged, no Person record was created, and the
five existing identity mechanisms all still exist.

---

## 2. The owner's decisions, as implemented

| | Decision | What was built |
|---|---|---|
| **BD1** | branch = requirement → recruiter → **refuse** | `rcv_branch_for()`. The requirement's branch, else the recruiter's; if neither, **the conversion is refused and nothing is created**. **The generic "no office means Ahmedabad" fallback is gone**, and mutant **M7** exists to stop it coming back |
| **BD2** | the marketplace add-on is **not** required for recruitment | The conversion never asks for it. It reports **STATE A** (`LINKED`) or **STATE B** (`NOT_ENTITLED`) and never conflates them; without the add-on it writes **no ledger row at all**, and the state is reported so it can be linked later. Mutants **M11** and **M12** guard both halves |
| **BD3** | **R20 stays open** | No universal uniqueness rule was added. Only **U4**: *one live conversion per application*. Two applications may still convert to the same person — a re-hire, or supply through two agencies — because forbidding that would decide what nobody has decided |
| **BD4** | split-group repair, strict limits | `person_group_repair()` reads **only** the activity spine's record of which applications a link named together. No name, no e-mail, no mobile, no similarity. Dry-run by request, audited always, and it **reports instead of repairing** when the previous state cannot be proved. Mutant **M13** makes it fuzzy; `G8e` kills it |

---

## 3. What changed, file by file

| File | Change |
|---|---|
| `lib/recruit.php` | **`rcv_convert()`** — the conversion, lifted out of the route: its own gates, one transaction, branch resolution, the ledger row, the audit, and a result that always matches committed state. **`rcv_branch_for()`** (BD1). **`person_link_rows()`** now computes the whole closure (I43). **`person_group_repair()`** (BD4) |
| `lib/ops.php` | `/candidate-stage` calls the function; the raw conversion INSERT is gone. The message tells the user which identity state resulted |
| `lib/connect_identity.php` | A **third axis** — candidate↔inspector — with its resolver, writer and **U4**. The candidate predicate narrowed so the axes cannot collide. **`identity_state_findings()`** — detection only |
| `lib/connect_person.php` | The resolver now traverses the conversion axis, so a person hired through recruitment resolves at all |
| `tests/` | `test_p6_batch2.php` (78 assertions) + `_p6b2_worker.php` |

**Engines reused unchanged:** `act_log()` · `scope_allows()` /
`scope_sbu_allows()` · `connect_identity_admin_can()` · `ensure_column()` /
`db_epoch()` · `next_emp_code()` · `rexec_block_reason()` (M4/M6) ·
`rasg_*` (M5) · `reqf_sync()` (M3) · Phase 4 allocation.

**New components: one** — the conversion function. It is not an engine; it is the
route's own code, moved where it can be tested and attacked. **No new engine,
table, permission, route or screen.**

---

## 4. Evidence

### Tests

| | |
|---|---|
| New battery | **78 assertions** |
| Written | **before** the implementation |
| Baseline against unmodified code | **19 passed · 30 failed** |
| Final | **78 passed · 0 failed**, both engines |

### Regression

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **12 460 passed · 0 failed** |
| **MariaDB 10.11.14** (authoritative, restarted first) | **12 467 passed · 0 failed** |

Operations · Workforce · Recruitment · Marketplace · Reporting · Money ·
Dashboard/KPI all green, **including the locked Phase 5 KPI battery, numerically
unchanged**. **No existing test needed changing**, and none was weakened, deleted
or skipped.

### Mutation

**18 of 18 caught · no survivors · no FATALs.** The first run scored 14/18; all
four survivors were **defective probes, not defective code**. One equivalent
mutant was proved and re-aimed; one anchor miss was re-anchored and re-run.
Detail in `P6-BATCH2-MUTATION-RESULTS.md`.

### Security

Every attack in the owner's list: direct route, forged POST, direct internal
function, wrong branch, wrong unit, entitlement on and off, three-process
concurrency, retry, stale process, transaction integrity, second writer,
repair-as-merge. All held. Detail in `P6-BATCH2-SECURITY-RESULTS.md`.

### Adversarial

One complete attack after implementation. **One material defect found and fixed**
(a reported *failure* that left a committed orphan inside a caller's
transaction); **two residuals recorded**. No correction treadmill. Detail in
`P6-BATCH2-ADVERSARIAL-AUDIT.md`.

---

## 5. Invariants

| Invariant | Before | After |
|---|---|---|
| **I30** a representation cannot be duplicated by an ordinary business action | VIOLATED | **HOLDS** |
| **I43** linking two records must never make a third a different person | VIOLATED *(new in the Batch 2 plan)* | **HOLDS** |
| **I2** multiple representations must not silently become multiple people | PARTIAL | **HOLDS** — the candidate↔inspector edge exists at last |
| **I22 · I42** no orphan, no silent half-state | PARTIAL | **PARTIAL** — HOLDS for the conversion path; still PARTIAL while **R20** is open and other dangling states exist |
| **I6 · I41** audit | PARTIAL | **PARTIAL** — the conversion and person links now audit; `users.inspector_id` and the per-application bridge still do not (**R18**) |
| **I16** branch scope | PARTIAL | **PARTIAL** — unchanged. **Q5/Q11 stay open** |
| **I28 · I29** uniqueness and concurrency | HOLDS | **HOLDS**, extended to the new axis by U4 |
| **I23 · I25 · I27** | HOLDS | **HOLDS** — no read-path write added; every gate asked at the function |
| **I1 · I3** one person, many representations | HOLDS | **HOLDS** — asserted directly (`E4`) |
| **I32** "keep separate" | VIOLATED | **VIOLATED** — **R21**, untouched |
| **I39** history valid after convergence | NOT ESTABLISHED | **NOT ESTABLISHED** — no convergence happened |

**The PARTIALs are PARTIAL on purpose and are not rounded up.**

---

## 6. Known limitations — stated, not minimised

| | |
|---|---|
| **R20 stays open.** No universal staff-uniqueness rule. Two applications may convert to the same person; the People form still creates staff with no duplicate check |
| **R21 untouched.** A completed hire cannot be reversed, and "these are different people" still cannot be recorded |
| **R18 partial.** `users.inspector_id` and the per-application bridge still write no audit |
| **No back-fill.** Conversions predating this batch have no ledger row. They are **reported**, not invented — and the report cannot yet distinguish them from the marketplace-unavailable case (adversarial Finding E) |
| **Detection only.** Six of the seven contradictory-state kinds are reported and repaired by nobody. That is the decision, not an omission |
| **Q5/Q11 open**, so relationship-level branch scope is still undecided |
| **No deployment claim.** Nothing here asserts a production deploy or a UAT |

---

## 7. What this batch did NOT do

- **No Person table, no Person hub, no merge engine, no AI identity resolver, no
  universal human uniqueness, no automatic fuzzy matching.**
- **Candidate, Professional, Inspector, Employee and User remain distinct.**
- **Q1–Q18 remain unanswered.** **Q3/Q7/Q13 in particular are untouched**:
  `person_ref` was stopped from corrupting itself, and was not promoted to
  anything.
- **Batch 1 was not modified**, beyond the axis predicate the third relationship
  required — a correctness extension, proved by Batch 1's own battery passing
  unchanged.
- **M4's execution boundary and M5's recruiter accountability remain
  authoritative** and are asked before anything here.
- **Marketplace, Operations, Reporting, Money and Dashboard/KPI were not
  redesigned.**

---

## 8. Acceptance criteria

| # | Criterion | Met |
|---|---|---|
| 1 | One application cannot produce multiple conversions through concurrency | ✔ `A1` |
| 2 | No conversion leaves orphan staff records | ✔ `A3 A19` |
| 3 | Branch = requirement → recruiter → refuse | ✔ `B1–B8` |
| 4 | No silent Ahmedabad fallback | ✔ `B9`, mutant M7 |
| 5 | The marketplace is not required for recruitment conversion | ✔ `C4`, mutant M11 |
| 6 | The unavailable state is explicit, never a false link success | ✔ `C6 C7`, mutant M12 |
| 7 | Existing marketplace identity linking still works | ✔ full regression |
| 8 | Candidate / Inspector / Professional axes remain distinct | ✔ `E2 E3 E4` |
| 9 | The split-group repair is deterministic and auditable | ✔ `G4–G8e`, mutant M13 |
| 10 | Unprovable situations are reported, not guessed | ✔ `G8`, `H1–H3` |
| 11 | R20 remains open | ✔ |
| 12 | Q1–Q18 remain open | ✔ |
| 13 | No Person hub | ✔ |
| 14–16 | Operations · Recruitment · Marketplace fully functional | ✔ both engines |
| 17 | Phase 5 KPI numerically consistent | ✔ |
| 18 | All mandatory mutations caught | ✔ 18/18 |
| 19 | SQLite green | ✔ 12 460/0 |
| 20 | MariaDB green | ✔ 12 467/0 |
| 21 | Real-process concurrency green | ✔ three OS processes, independent connections |
| 22 | No test weakened, deleted or skipped | ✔ |

---

## 9. Verdict

Every acceptance condition is met, with the PARTIALs above stated rather than
rounded up.

**PHASE 6 — BATCH 2: COMPLETE, PENDING OWNER REVIEW.**

Phase 6 is **not** complete. **Batch 3 has not been started**, and will not be
without an explicit instruction.
