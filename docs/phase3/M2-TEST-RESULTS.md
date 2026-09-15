# Phase 3 · M2 — Test Results

## 1. Environment

| | |
|---|---|
| PHP | 8.4.19 |
| Engine 1 | SQLite (bundled) |
| Engine 2 | **MariaDB 10.11.14** over TCP, into a **freshly created** database (`exaact_p3m2b`) so every migration ran from nothing |
| New file | `tests/test_p3m2_matrix.php` — **86 assertions**, 11 sections |

## 2. Results — both engines, identical source

| Engine | Whole suite | M2 suite | Skips |
|---|---|---|---|
| SQLite | **9017 passed, 0 failed** | 86 assertions, 0 failed | none introduced |
| **MariaDB 10.11.14** (authoritative) | **9018 passed, 0 failed** | **86 assertions, 0 failed** | none introduced |

The M2 suite was confirmed to have **actually executed on MariaDB**: all 11
sections appear in its output and the assertions were counted **inside** that
section, not inferred from the total. MariaDB's total is one higher than
SQLite's because of a pre-existing engine-specific assertion, unchanged by this
work.

Earlier runs are recorded for honesty, not counted: SQLite 9006/0 and MariaDB
9007/0 with 75 M2 assertions both **predate** the three sections added after the
first mutation battery.

## 3. Shared approval regression

Every known consumer of the shared engine, after M2 changed it:

| Suite | Result |
|---|---|
| `p3m1_approval` (Hiring Request approval) | 114 passed, 0 failed |
| `recruit_approval` (Requisition chain, inbox, levels) | 25 passed, 0 failed |
| `offer_appr_dept` (Offer chain, department matching) | 2 passed, 0 failed |
| `m4_correction` | 107 passed, 0 failed |
| `m4_hiring_request` | 78 passed, 0 failed |
| `m3_multi_vacancy` | 99 passed, 0 failed |
| `hiring_admin` | 11 passed, 0 failed |

No test was weakened, deleted or skipped.

## 4. What the M2 suite covers

| Section | Holds in place |
|---|---|
| **M2.1 reuse** | the four Phase-6 tables are what M2 uses; no second rules engine; the three columns were added to the **existing** table; priority reuses `sort`; one new table only |
| **M2.2 matching** | no rule → no match · one rule · department beats global · **branch beats department** · a branch rule does not apply elsewhere · ties fall to match order · **the same answer five times running** · runners-up visible · inactive ignored · not-yet-started ignored · expired ignored · in-window applies |
| **M2.3 orphan roles** | a level nobody holds is reported, with the reason; the warning **clears** when somebody holds the role and **returns** when they are switched off — the condition is constructed, not assumed |
| **M2.4 preview** | names the winning policy · says so when nothing matches · surfaces a level nobody could action |
| **M2.5 delegation** | created · delegator gains nothing · no self-delegation · no unknown user · end-before-start refused · **future grants nothing** · **expired grants nothing** · entity scope respected · **revocation immediate** · a chain A→B→C is detectable and **does not reach through** |
| **M2.6 segregation** | the **requestor** is given the approver's delegated authority and is **still refused** — then a genuine delegate approves it and only then is it executable |
| **M2.6b authority** | a delegator holding **no approver role** delegates nothing |
| **M2.6c queue** | a foreign-branch approver holds the role, is refused the decision, and the row is **absent from their queue** — present in a rightful approver's |
| **M2.6d branch context** | a request raised in a branch is **routed by that branch's policy**, asserted on the chain's recorded rule |
| **M2.7 history** | the chain records which policy required the approval, by id **and** name; renaming the rule afterwards does not rewrite it |
| **M2.8 configuration security** | both screens gated · route entitlement-mapped · **HR off refuses a master** · a partial update keeps department, branch and match order · policy and delegation changes audited, revocation included |

## 5. Mutation testing

Eighteen mutations, each applied to live source, the four guard suites re-run,
the source restored from a byte-for-byte backup, a clean baseline re-confirmed —
all against a **copy of the application outside the repository**, so a mutation
can never be committed.

| # | Protection removed | Actual | Verdict |
|---|---|---|---|
| D1 | branch condition — a rule for another branch applies | 1 failed | **CAUGHT** |
| D2 | effective dates | 2 failed | **CAUGHT** |
| D3 | specificity — every rule scores the same | 2 failed | **CAUGHT** |
| D4 | tie-break ordering | 6 failed | **CAUGHT** |
| D5 | inactive rules matched again | 1 + 3 failed | **CAUGHT** |
| D6 | orphan-role detection | 4 failed | **CAUGHT** |
| D7 | delegation expiry | 1 failed | **CAUGHT** |
| D8 | delegation start date | 2 failed | **CAUGHT** |
| D9 | delegation entity scope | 2 failed | **CAUGHT** |
| D10 | revoked delegations still work | 1 failed | **CAUGHT** |
| D11 | a delegation may be created to oneself | 1 failed | **CAUGHT** |
| D12 | **delegation manufactures an authority the delegator never held** | 4 failed | **CAUGHT** |
| D13 | **queue visibility decoupled from actionability (Finding 2 reopened)** | 1 failed | **CAUGHT** |
| D14 | configuration authorization on the delegation screen | 1 failed | **CAUGHT** |
| D15 | a partial rule update erases what it does not carry | 3 failed | **CAUGHT** |
| D16 | the applied policy no longer recorded on the chain | 5 failed | **CAUGHT** |
| D17 | policy / delegation configuration no longer audited | 1 failed | **CAUGHT** |
| D18 | **the branch no longer passed into rule matching** | 2 failed | **CAUGHT** |

**Eighteen of eighteen caught. None survived.**

### First run: three survived, and all three were my tests' fault

| # | First run | Why it survived | Action |
|---|---|---|---|
| D12 | SURVIVED | every delegation test used a delegator who genuinely held the approver role, so dropping the role check changed nothing observable — yet §21 makes this an explicit rule | a delegator holding **no** approver role was added |
| D13 | SURVIVED | M1 Finding 2's closure had been proved with an **ad-hoc probe** and never encoded — the same failure mode as the `activity_log()` episode: verified once, not guarded | a behavioural queue test was added |
| D18 | SURVIVED | the matrix tests call `appr_match()` with a context they build themselves, so blanking `office_id` in `hreq_appr_ctx()` was invisible to them | an end-to-end branch-routing test was added |

D15 additionally reported an **anchor miss** — the harness's fault, not the
code's: the anchor text matched twice because `appr_rule_save()` and
`appr_delegation_save()` share the same `$val` closure shape. Made specific, and
the mutation is caught.

None of the three was excused as defence-in-depth. The tests were strengthened
and the battery re-run in full.
