# PHASE 3 · M6 — COMPLETION REPORT
## Integration, Security & End-to-End Lock

### 1 · Executive summary

M6 built no feature. It asked the one question no single milestone can ask — *can
a valid-looking action at one stage produce an invalid business state at another?*
— and the answer was yes, in five places.

An **offer** could be created, approved, **issued** and accepted against a
requisition whose approval had been invalidated. An **interview** could be
scheduled on the same one. The **configured pipeline** advanced candidates without
asking anything. **Three people could join a two-seat requirement**, because the
headcount ceiling was enforced when the requisition was raised and never again.
And the **approval engine** wrote requisition statuses that the requisition
lifecycle does not contain, so an approved requirement fell out of the dashboard
and out of recruitment altogether.

All five are fixed by **one composed question** asked at every path that spends a
requirement. Approval is still answered by M4 and counting by M3; M6 re-decides
nothing.

### 2 · Starting commit
`9492e67` — Phase 3 M5 adversarial audit.

### 3 · Final commit
See the commit recorded with this report.

### 4 · Files changed
**New:** `phpapp/lib/recruit_exec.php` (the gate). **Changed:** `lib/ops.php`
(stage + candidate saves), `lib/recruit_offer.php` (five offer steps),
`lib/recruit_iv.php` (scheduling), `lib/recruitpipe.php` (the pipeline engine),
`lib/recruit_approval.php` (the requisition callback), `index.php`.
**Tests:** `test_p3m6_lifecycle.php`, `test_p3m6_security.php`,
`test_p3m6_reconcile.php`, `test_p3m6_concurrency.php`, `tests/_m6_worker.php`,
and four re-pointed assertions in `test_p3m4_security.php`.
`deploy-check.php` regenerated. Twelve documents in `docs/phase3/`;
`docs/02-permission-matrix.md` updated in the same commit.

### 5 · Database changes
**None.** No table, no column, no index, no status. M6 is behaviour only.

### 6 · M6 implementation changes
`rexec_block_reason($requisitionId, $action, $candidateId)` — one question, four
actions (`ADVANCE`, `INTERVIEW`, `OFFER`, `JOIN`), composed from the requirement's
lifecycle status, M4's boundary and M3's seat count; plus
`rexec_join_enforce_after_write()`, the compensating revert for the seat.

### 7 · Integration points
Offer (×5 steps), interview scheduling, the configured pipeline, the joining, the
candidate save, the requisition save, and the approval engine's requisition
callback. See M6-INTEGRATION-MATRIX.md.

### 8 · Tests performed
Lifecycle, negative/security matrix, reconciliation, real-process concurrency,
plus the complete existing regression. **191** new assertions.

### 9 · SQLite evidence
Baseline **11090 / 0** (117 s) · final **11180 / 0**.

### 10 · MariaDB evidence
Baseline **11091 / 0** (119 s) · final **11181 / 0**. Authoritative.

### 11 · Concurrency evidence
**21 / 21**, six races, real processes, both engines. See M6-CONCURRENCY-RESULTS.md.

### 12 · Mutation evidence
**36 attempted · 36 caught · 0 survived**, against a clean baseline, with the
harness refusing to run on an unclean one — which it did once, correctly. See M6-MUTATION-RESULTS.md.

### 13 · Security evidence
M6-NEGATIVE-SECURITY-MATRIX.md — every row executed, not reasoned about.

### 14 · Tenant evidence
A real second database; read, write and execute all refused across the boundary;
workspace A verifiably untouched afterwards.

### 15 · Branch evidence
Seven branch-crossing attempts, all refused, including the dashboard and workload.

### 16 · RBAC evidence
A real least-privilege role refused at create, decide, assign and the write band.

### 17 · Entitlement evidence
`can()` asks `licence_blocks()` **before** the master flag; an unknown module
right is denied; every recruitment route is inside the licensed module.

### 18 · Dashboard reconciliation
Thirteen figures reconciled three ways. See M6-RECONCILIATION-RESULTS.md.

### 19–23 · Operations / Marketplace / Reporting / Money / Workforce regression
All pass inside the complete regression on both engines. No module was rebuilt, no
semantics changed, no duplicate record created, and `requisitions` and
`cx_requirements` remain separate.

### 24 · Manual E2E evidence
E1 corporate hire (L1), E2 material change (L3, L4), E3 multi-vacancy with twelve
candidates (reconciliation), E4 reassignment (M5 suite), E5 cross-branch (S2),
E6 cross-tenant (S7), E7 marketplace (no duplicate requirement — sweep §B),
E9 public careers (M5 K). E8 TPIA runs on the same spine and is covered by the
complete Operations regression.

### 25 · Adversarial findings
**Four passes.** Pass 1 (before implementation) found five product defects and one
probe defect. Pass 2 (after the fixes) was clean at 24/24 once two of my probes
were repaired. **Pass 3, after M6 was first declared accepted, found five more —
all material.** **Pass 4 found three more** (a move asked as an advance, a decision
stamp left on an undone decision, and a compensator that refused everybody in a
dead heat), plus one no-op mutation reported rather than counted.
See M6-ADVERSARIAL-AUDIT.md.

---

## FIXED

1. Offers could be created, submitted, approved, **issued** and accepted on a
   requisition whose approval M4 had invalidated. *(BLOCKER)*
2. Interviews could be scheduled on a blocked requisition. *(MATERIAL)*
3. The configured pipeline advanced candidates without asking the boundary.
   *(MATERIAL)*
4. **More people could join than were approved** — three into two seats.
   *(BLOCKER)*
5. Interviews and offers ran on **cancelled** and **closed** requirements.
   *(MATERIAL)*
6. The approval engine wrote requisition statuses that are not in the lifecycle,
   removing an approved requirement from the dashboard and from recruitment.
   *(MATERIAL)*
7. The gate decided **which** requirement by type conversion: an array became
   requisition #1, a word and a negative number became the "no requirement"
   answer — and all three answers were **allow**. *(MATERIAL)*
8. A malformed *"except this candidate"* value **conjured a seat** on a full
   requirement — the one place a bad value created capacity instead of refusing
   it. *(MATERIAL)*
9. The interview and offer routes **announced success on a refused operation**,
   telling a coordinator the interview was booked and the offer drafted while
   nothing had been written. *(MATERIAL)*
10. A cast in the caller defeated its own repair — the helper validated, the
    caller had already destroyed the evidence. *(MATERIAL)*
11. A **move** was asked as an advance, so moving somebody who already held a
    seat onto a full requirement put two people into one. *(MATERIAL)*
12. A reverted joining **left its decision stamp**, so a decision that was undone
    still appeared to have been taken. *(MATERIAL)*
13. **Two approvers deciding one request both succeeded** — check-then-write
    around the decision, leaving two contradictory decisions in one audit trail.
    Intermittent on MariaDB, invisible on SQLite. *(MATERIAL)*

## HELD

Tenant isolation · branch isolation · RBAC · entitlement (no master bypass) ·
the M4 re-approval boundary · the M4 allocation ceiling · M5 recruiter
accountability, including target-requisition validation, input types, staleness
and concurrency · dashboard/export reconciliation · audit integrity ·
ADR-001 direct candidates.

## DEFERRED

Nothing. No finding was postponed.

## LIMITATION (stated design positions, not defects)

1. **`iv_record()` is not gated** — recording the outcome of an interview that
   already happened advances nothing and refusing it would destroy information.
2. **Offers are not seat-limited** — the business deliberately runs more offers
   than seats because offers are declined. Only the joining consumes a seat.
3. **Closing moves are always allowed** — reject / withdraw / decline / hold, so a
   pending re-approval never traps a candidate.
4. **ADR-001 direct candidates** have no approval to respect and no ceiling.
5. **A rejection through the approval chain writes no requisition status**, because
   the lifecycle has no state for it. It is recorded in the chain and on the audit
   spine. Adding one is a business decision, and I have not made it for you.
6. **Non-transactional boundaries are compensated, not wrapped.** A joining and a
   reassignment are protected by a compensating revert rather than a database
   transaction, because the callers do not own one. Proved under real concurrency.
7. **An unrecognised role maps to ADMIN** (`ua()`) — pre-existing, recorded since
   M4, untouched here.
8. *(moved)* The dead-heat behaviour is **no longer carried as a limitation.** The
   business has ratified it as policy — invariant **I21** — so it is now specified
   behaviour and appears in M6-BUSINESS-INVARIANTS.md and the state matrix. See
   §29c below.

## CARRIED-FORWARD FINDINGS

The eleven recorded in M3-CARRIED-FORWARD-INVENTORY.md remain carried forward.
None was reopened; none was removed; none blocks M6.

## KNOWN NON-BLOCKING ISSUES

None beyond the limitations above.

---

### 29c · Capacity and incumbency — ratified policy (invariant I21)

The business has stated the rule this milestone was circling:

> *Recruitment must never exceed approved capacity and must never displace an
> established holder merely to manufacture a concurrency winner. Where
> simultaneous claims cannot be deterministically resolved without risking
> displacement, the system may refuse the contested claims and leave the capacity
> available for a subsequent valid transaction.*

The implementation already behaves exactly this way; what changed is its
**status**. The dead-heat refusal was being carried as a known limitation — an
apology for pessimism. It is now a **specified outcome**, recorded as invariant
**I21** with its three parts (never over capacity, never displace an incumbent, and
a refused dead heat must leave the capacity usable). No code changed; the tests
that already proved it now say so in the language of the policy, and the
documents no longer describe an approved rule as a shortfall.

### 29b · Why the first M6 verdict was withdrawn

M6 was declared accepted after two adversarial passes. A third, independent pass
found five more material defects — including one inside the repair for another,
and one that only showed as an intermittent red in my own suite. That verdict was
premature and is recorded here as withdrawn rather than quietly replaced.

### 30 · Final acceptance decision

Every applicable box in the M6 gate is met: clean baselines on both engines, the
full lifecycle proved end to end, approval and re-approval authoritative, the
execution boundary enforced on every path that spends a requirement, quantity
enforced at allocation **and** at joining, multi-vacancy arithmetic correct,
recruiter accountability preserved, target-requisition authorization correct,
candidate/interview/offer/joining protections proved, tenant and branch isolation
proved with a real second database, RBAC and entitlement proved, dynamic write
paths audited, input-type and stale-data attacks passed, real-process concurrency
passed, partial failure compensated, audit integrity intact, dashboard and export
reconciled, migrations unchanged (none added), mutation baseline clean and all
mandatory mutations caught, no test weakened, deleted or skipped.

# M6 ACCEPTED
# PHASE 3 — ACCEPTED / LOCKED
