# RB-3 · Step 3 — acceptance is one transaction
## Implementation and evidence

**Engines:** MariaDB 10.11.14 (authoritative) · SQLite 3.45.1 (supplementary)

---

## 1. What changed

Accepting a candidate is now one transaction: the stage move, the KPI stage
ledger, the workforce record, the employee number, the requirement's standing
and the source credit either all happen or none of them do. Every refusable
question is asked **before** it opens.

**The defect this removes.** The seat ceiling used to be a *compensating* check —
two recruiters both passed it and the loser was put back afterwards. That was
only safe because the revert ran before the workforce record was created.
Wrapping acceptance in a transaction without touching it would have destroyed
that ordering and left the loser holding a real team member and a permanent
employee number for a hire that had been undone: **worse than the problem being
fixed.** The seat is now decided inside the transaction, under a lock on the
requirement, and the second recruiter is stopped at the save.

**Lock order:** requirement → candidate → workforce insert. One direction
everywhere. Step 1 paid for that lesson with a deadlock in 3 runs out of 3.

**Migrations.** Eight are reachable from this path and each runs DDL on a cold
process, which MariaDB commits implicitly. They are warmed before `BEGIN`, and
the warm-up refuses to run inside a transaction.

**Audit.** Outside, per invariant I41 — a failed note must not undo a completed
hire. What changed is that a lost entry is now counted and reported instead of
silently swallowed.

The full write-by-write trace is `RB3-STEP3-TRANSACTION-MAP.md`.

---

## 2. Regression

Both engines, run **sequentially** (see §6, D7 — running them at once is not valid):

| Engine | Full suite | `test_rb3_step3_atomic.php` |
|---|---|---|
| **SQLite 3.45.1** | **13,064 passed · 0 failed** | 83 passed · 0 failed |
| **MariaDB 10.11.14** | **13,076 passed · 0 failed** | 92 passed · 0 failed |

No skips — the harness has no skip facility.
`php tools/make_deploy_check.php` re-run.

### Why the totals differ, measured rather than assumed

The gap is **12**, and every assertion of it is accounted for by comparing the
two runs assertion by assertion:

| File | Difference | Why |
|---|---|---|
| `test_rb3_step3_atomic.php` | **+9** | **by design.** T7, T9 and T10 are MariaDB-only; on SQLite each emits one marker assertion naming itself as skipped, so 3 markers stand in for 12 real assertions |
| `test_storage_safety.php` | **+2** | engine-conditional and deterministic — reproduces in isolation |
| `test_p4_concurrency.php` | **+1** | **race-sensitive.** Measured between 0 and +2 across runs; it asserts more when a real overlap is actually achieved |
| `test_rb3_step2_dupmatch.php` | **+1** | **race-sensitive** — X14, already documented in Step 2's evidence as engine-honest |
| `test_m11_ux_consolidation.php` | **−1** | **state-dependent, not engine-dependent.** In isolation both engines run 58. The test branches on whether the workspace reports setup complete: that branch asserts 1, the unfinished branch asserts 2, and the test says in its own words that the other branch is covered by its A and C cases |
| **net** | **+12** | |

**MariaDB's total is not stable between runs** — 13,076 and 13,079 were both
observed, differing only in the three race- and state-sensitive files above.
SQLite's is stable at 13,064 because it cannot stage a write race at all. This is
reported rather than smoothed over: the totals are *not* expected to be
identical, and the variation has a named cause in each case.

## 3. The test matrix

### T — the transaction itself

| | Claim | Engine |
|---|---|---|
| **T1** | two real recruiters, one seat: exactly one accepted, the loser left **nobody** behind and no stage history | MariaDB |
| **T2** | a refused acceptance changes nothing — no stage, no record, no ledger entry — **but the refusal is recorded** | both |
| **T3** | every non-joining move is untouched | both |
| **T4** | a successful acceptance writes *everything*, and the audit lands after the commit | both |
| **T5** | the warm-up refuses to run inside an open transaction, and sits before it | both |
| **T6** | a lost audit entry keeps the hire and is **reported** (owner decision 2) | both |
| **T7** | the seat is decided **inside**, proved with a manufactured overlap rather than hoping for a race | MariaDB |
| **T8** | a ledger this path cannot write **stops** the acceptance | both |
| **T9** | the warm-up has an **effect**, not merely a position | MariaDB |
| **T10** | accepting **without** a conversion is guarded too — the only path where the in-transaction seat check stands alone | MariaDB |

### X — the adversarial matrix

| | Claim |
|---|---|
| **X2** | an acknowledged duplicate is accepted and creates exactly one record |
| **X4** | a **forged** tick is refused, creates nothing, and is refused **before the transaction opens** (X4d — §7) |
| **X6 / X11** | the workforce write itself fails → the stage move rolls back with it, ledger included |
| **X11b** | …and the rolled-back failure is still recorded, outside the transaction that vanished |
| **X8** | no employee number → no acceptance, nothing created (X8e cleans its blockers away) |
| **X12** | the recruiter retries and it works, with nothing left over from the failed attempt |
| **X13** | three simultaneous submissions of the **same** acceptance produce one record and one number |
| **X16** | an application id from another tenant is refused by the route **and** by the action itself |
| **X17** | accepting twice changes nothing (§13 idempotency), and the action refuses it **on its own**, without the route |

### E — why two mutations are equivalent (see §5)

| | Claim |
|---|---|
| **E1 / E1a / E1b / E1c** | neither the source credit nor the requirement recompute can fail the transaction — both swallow their own errors, proved by taking their tables away |
| **E2 / E2a / E2b** | `rcv_convert()` re-throws on a borrowed transaction instead of returning a failure, and the refusals it *does* return are early gates the pre-transaction pass already settles |

### J — nothing else moved

M6's execution boundary, Step 1's employee-number key, Step 2's acknowledgement,
and **exactly one** conversion call site — the old post-commit one is gone, not
left to drift.

---

## 4. Mutation — 15 targets

Whole-repository copy and a fresh database per mutant. **FATAL is not a catch.
ANCHOR-MISS is not a catch. A dirty baseline aborts.** Baselines: SQLite 83
passed, MariaDB 92 passed, both 0 failed.

| | Mutant | Engine | Result | Killed by |
|---|---|---|---|---|
| **A1** | the lock on the requirement is removed | MariaDB | **CAUGHT** | T1 (10 assertions) |
| **A2** | the seat is no longer decided inside the transaction | MariaDB | **CAUGHT** | **T10** |
| **A3** | there is no transaction at all | SQLite | **CAUGHT** | T1 (9) |
| **A4** | a failed acceptance commits instead of rolling back | SQLite | **CAUGHT** | T8, X6, X11 |
| **A5** | a ledger this path cannot write no longer stops it | SQLite | **CAUGHT** | **T8** |
| **A6** | the migrations are no longer warmed before the transaction | MariaDB | **CAUGHT** | **T9** |
| **A7** | a refusal is no longer recorded | SQLite | **CAUGHT** | **X11b** |
| **A8** | the warm-up stops refusing to run inside a transaction | SQLite | **CAUGHT** | T5 |
| **A9** | the seat check asks the wrong question (ADVANCE does not count seats) | MariaDB | **CAUGHT** | **T10** |
| **A10** | the COMMIT is removed | SQLite | **CAUGHT** | 20 assertions |
| **A11** | it commits immediately after the stage move | SQLite | **CAUGHT** | T8, X6, X11 |
| **A12** | it commits immediately after the workforce record is created | SQLite | **SURVIVED** | — see §5 |
| **A13** | the pre-transaction refusal pass is bypassed | SQLite | **CAUGHT** | **X4d** |
| **A14** | a candidate can be accepted twice | SQLite | **CAUGHT** | **X17d** |
| **A15** | a failed workforce creation no longer stops the acceptance | MariaDB | **SURVIVED** | — see §5 |

**CAUGHT 13 · SURVIVED 2 · FATAL 0 · ANCHOR-MISS 0.**

---

## 5. The two survivors — stated plainly, not rounded to zero

This is **not** a clean sweep, and it is not reported as one. Two mutants live.
Both were investigated empirically rather than argued away, and both turn out to
be **unreachable backstops**: code whose removal cannot change any observable
behaviour, because nothing downstream of them can ever reach them.

**A12 — committing straight after the workforce record.** The only writes left
after that point are the source credit (`rful_enforce_candidate()`) and the
requirement recompute (`reqf_sync()`). A mutation that commits early is only
detectable if one of those can *fail*. Both were tested with their tables
removed outright: **neither throws.** They swallow their own errors by design,
which predates this step. With nothing able to fail after the workforce insert,
an early commit there is indistinguishable from a late one.

**A15 — a failed workforce creation no longer stopping the acceptance.** This
guard reads `if (empty($cv['ok'])) throw`. It can only matter if `rcv_convert()`
can *return* a failure while running inside a borrowed transaction. It cannot:
`lib/recruit.php` re-throws in that case (`if (!$own) throw $e;`), so a genuine
failure arrives as an exception the surrounding `catch` already handles, and the
refusal codes it does return are early gates the pre-transaction pass has already
settled. The guard is a correct backstop that nothing can currently trip.

**The equivalence claim is itself tested.** The E-section above asserts each of
those facts — the swallowing, the re-throw, the early-gate overlap. If the code
ever changes so that any of them becomes reachable, **those assertions fail and
this equivalence claim is withdrawn automatically.** The guards stay in the code
regardless: removing a backstop because today nothing trips it is how the next
defect gets in.

---

## 6. Defects found and fixed while building this evidence

Reported openly rather than presented as green. The recurring theme across all
three RB-3 steps: **tests that execute without exercising what they name.**

### D1 · A2 and A9 — the protection was **doubled**, and my tests could not see mine

T1 races two real processes; T7 manufactures the overlap deterministically. Both
showed the loser being refused — so both passed, and both mutations survived.

The reason is that **`rcv_convert()` re-asks the same seat gate itself**
(`lib/recruit.php:1560`), inside the transaction too. Whenever a conversion is
requested the seat is checked twice, so deleting the check in the route changed
nothing either probe could observe.

The case that is **not** doubled is accepting somebody **without** creating a
workforce record. There `rcv_convert` never runs. **T10** exercises it, and both
mutants die. *The gap was real: on that path the mutation produces two people in
one approved seat.*

### D2 · A5 — nothing ever made the ledger fail

The owner named the KPI stage ledger as a write that must participate, which
means more than sitting inside the transaction: a ledger that **cannot** be
written must stop the acceptance. **T8** takes the table away *while an
acceptance is in flight* — after the child has warmed its migrations, so the
route's own warm-up cannot quietly put it back — and restores it by renaming
rather than dropping, so other tests' rows come back untouched.

### D3 · A6 — my probe tested a **position**, not an effect

T5 proved the warm-up call exists before the transaction. Disabling it
(`if (false)`) left the call text where it was, so T5 passed regardless. **T9**
tests the effect: a cold process, an absent ledger table, and a failure landing
*after* the ledger step.

### D4 · …and A6 was also run on the wrong engine

Even after T9 existed, A6 reported SURVIVED — because the harness ran it against
**SQLite, where T9 skips by design**. A mutation run against an engine that
cannot express the defect proves nothing. *The bug was in the instrument's
instrument.*

### D5 · §7 — not every refusable check was actually before the transaction

The owner's §0.2 decision requires every refusable check to be settled before
`BEGIN`. Some were still being discovered inside and rolled back out. They are
now composed in one place, `rcv_refusal_before_transaction()`, which re-uses
Step 2's matching and acknowledgement engines and re-implements nothing.

**The hoist broke two passing tests, and that was the point.** T9's and T2e's
candidates had been refused *inside*; afterwards they never reached the
transaction at all, so A6 and A7 walked straight through probes that still
passed. T9 was re-aimed at a genuine database failure that cannot be refused
early, and **X11b** was added to assert the **post-rollback** audit specifically.

### D6 · X8's trap was not armed, and then it poisoned the test after it

X8 blocks every employee number so none can be issued. The blockers *raised the
generator's ceiling*, so it simply started past them and the trap never sprang —
caught by X8's own arming assertion, not by luck. Fixed with **leading-space**
blockers invisible to the generator's `LIKE 'EMP%'` scan (leading, never
trailing: MySQL ignores trailing spaces in comparison). The 30 blockers then
poisoned X13's numbering, so **X8e** now cleans up after itself.

### D7 · the regression harness itself: two engines cannot be run at once

To account for the per-engine difference I ran the full suite on both engines
**concurrently**. Both came back red — 16 failures on one, 6 on the other — in
the SaaS and workspace-signup tests.

It was not a regression. Those tests share one control plane, including
`phpapp/tenants.php`, the machine-local routing file that records whether cloud
mode is switched on. It is git-ignored, so nothing in `git status` showed it.
Two suites running at once trampled it, and worse, **one of them left it behind
configured**, so every *subsequent* run — sequential and clean — kept failing the
five assertions that check approval is blocked until cloud mode is set. The
system under test was reporting, correctly, that cloud mode was already on.

Resetting that file to its pristine state returned the signup battery to 20
passed · 0 failed, and the final sequential run to 0 failures on both engines.

Recorded because it is a trap worth knowing: **the engines must be run one after
the other**, and a red suite whose failures are all in the SaaS control plane is
a contaminated routing file before it is a code defect. The residue was
preserved rather than deleted while it was being diagnosed.

---

## 7. Why several claims are proved only on MariaDB

**SQLite cannot stage a write race** — one database-wide lock, no busy timeout
(recorded finding F1) — and **its DDL is transactional**, so the implicit-commit
hazard does not exist there. Asserting either on SQLite would be asserting the
engine rather than the behaviour. T7, T9 and T10 say so in their own names and
skip deliberately; MariaDB, the authoritative engine, is where they run.

## 8. What is NOT done

See `RB3-STEP3-COMPLETION.md` §13. Nothing on the deferred list was touched.
