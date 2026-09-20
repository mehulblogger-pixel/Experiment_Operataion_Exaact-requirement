# Phase 6 · Batch 3 — Corrective Implementation: evidence

Companion to `P6-BATCH3-CORRECTIVE-IMPLEMENTATION.md`. Numbers only, each one
produced by a run recorded here rather than expected.

---

## 1 · Focused suite

`tests/test_p6_batch3_corrective.php` — 132 assertions, sections CA–CH.

| Engine | Result |
|---|---|
| SQLite 3.45.1 | **132 passed, 0 failed** |
| MariaDB 10.11.14 | **132 passed, 0 failed** |

Coverage against §18:

| §18 asks for | Section |
|---|---|
| primary contact: sequential, 2 concurrent, 3 concurrent, retry, dirty data, migration after dirty data, migration rerun, both engines | CA0–CA22, CG |
| e-mail: upper, lower, leading space, trailing space, both, duplicate registration, login, invitation, account creation | CB0–CB10 |
| public registration: unknown, known, same company, same tax identifier, response equivalence, no identifier leakage | CC0–CC9 |
| security/audit: unauthenticated refusal, repeated flood, dashboard integrity, genuine activity still visible, evidence retained, tenant isolation | CD0–CD4, CH6–CH7 |
| status: ACTIVE, INACTIVE, ON_HOLD, BLACKLISTED, PROSPECT, MERGED, unknown, NULL/missing | CE0–CE2 |
| merge: merge, merged duplicate detection, survivor resolution, new registration on a merged identifier, claim routing, historical preservation, cross-tenant survivor | CF0–CF13 |

`CLOSED` and `SUSPENDED` are **not** used as company-status test values (§18, §11).

## 2 · Full regression (§20)

Run from the final implementation commit, whole suite, both engines.

| Engine | Result |
|---|---|
| SQLite | **12 806 passed, 0 failed** |
| MariaDB *(authoritative)* | **12 810 passed, 0 failed** |

The four-assertion difference is engine-conditional assertions inside the suite,
not a divergence in behaviour.

Protected areas green: Operations, Reporting, Money, Workforce, Marketplace,
Recruitment, Phase 4, Phase 5, Batch 1, Batch 2, Batch 3, SaaS entitlement,
tenant isolation, organisation/account controls.

### Tests re-aimed, and why

Seven assertions asserted the behaviour the audit condemned. They were re-aimed
to the corrected invariant — **none was weakened**, and each now checks the
database rather than a return code.

| Assertion | Was | Is now |
|---|---|---|
| A3 / A7 / M1 / M12 | "the attempt is refused" | the reply is **identical** to a new company's, and A4/A5/A8/A9 still prove nothing was created |
| B4 | "exactly one process reported success" | all three read the **same** answer; B1–B3 still prove one account, one partner, one organisation |
| C1 | "refused" | reads like every other sign-up, and no second account exists |
| **I3** | **required the A4 defect** — it demanded the stranger's refusal be written to the customer's activity feed | the feed is **untouched**, the evidence **is** retained in the staff trail, and ten anonymous attempts change nothing on the dashboard |
| `test_onboarding_engines` | "a second company on the same e-mail is refused" | creates nothing; the address still has exactly one account |
| `test_connect_org_register` | "a duplicate e-mail is refused" | reads like any other sign-up; no account, no organisation |

I3 is the one worth dwelling on: a test had been written that **agreed with the
code instead of checking it**, and so locked the defect in.

## 3 · Real HTTP (§9 · §21)

A dev server, a fresh install, one known organisation
(`Meridian Offshore Services Ltd`, GSTIN `27MERID1234M1Z5`), 12 unknown
companies and 12 attempts on the known one, equal-length e-mail addresses so any
byte difference must come from the product.

| | Audit (before) | Now |
|---|---|---|
| Response size, unknown | 3 507 bytes | **3 574 bytes** |
| Response size, known | 12 389 bytes | **3 574 bytes** |
| Distinguishable by size | 200 / 200 | **0 / 24** |
| Time, unknown | 284 ms | 253–291 ms |
| Time, known | 21 ms | 253–371 ms |
| Separable by timing | complete separation | **ranges overlap; no single observation classifies** |

Database state after the run:

```
duplicate copies of the known organisation created : 1   (unchanged)
portal accounts created for the KNOWN attempts     : 0
portal accounts created for the NEW attempts       : 24  (genuine sign-ups still work)
access requests raised for a human                 : 24
  ...each one naming the correct organisation        : 24
rows written to the CUSTOMER'S activity feed       : 0
security evidence retained for staff               : 24
one-primary guard state                            : OK
client-account guard state                         : OK
```

## 4 · Browser (§21)

Playwright against the same install — **15 of 15 assertions passed**.

1. organisation registration (new company) — neutral confirmation
2. duplicate organisation handling — **the same** confirmation, same length
3. no disclosure of `Meridian`, the GSTIN, `already`, `exists`, `duplicate`, `EXACT`
4. portal login typing the address with capitals **and** surrounding spaces (Q25)
5. permission failure — a signed-out visitor is refused `/access-requests`
6. the staff queue opens for a master admin and names the organisation
7. the screen states plainly that nothing on it grants access
8. the customer's own record shows **no** anonymous sign-up attempt (A4)

Screenshots: `01-signup-new-company.png`, `02-signup-existing-company.png`,
`03-portal-signed-in.png`, `04-access-requests-refused.png`,
`05-access-requests-queue.png`, `06-customer-record.png`.

## 5 · Provenance (§22)

Established with `git log -S` bounded to the history before this work, not from
memory.

| Defect | Introduced by | Verdict |
|---|---|---|
| A1 one-primary race | `c3d558d` (Batch 3) | introduced by Batch 3 |
| A2 whitespace identity | `c3d558d` (Batch 3) | introduced by Batch 3 |
| A3 the response oracle | `c3d558d` (Batch 3) | introduced by Batch 3 |
| A4 activity pollution | `c3d558d` wrote into a feed that `b70cf8d` had already limited to 8 rows | **a combination** — the 8-row feed pre-existed; Batch 3 made it reachable by an unauthenticated stranger, which is what made it a defect |
| A5 status never consulted | `b963490` (Phase 2) | **pre-existing**, exposed by Batch 3 calling the detector from a public route |
| A6 SQLite generated-column blind spot | `c3d558d` (Batch 3) | introduced by Batch 3 |

Five of six are Batch 3's own. That is recorded as found; nothing has been
rewritten to make the implementation look cleaner.

### The instrumentation record (§11 · §22)

The A5 probe planted `CLOSED` and `SUSPENDED` on company records and observed
the detector ignoring them. It ignored them because **the product cannot produce
those values** — the vocabulary is `ACTIVE`, `INACTIVE`, `ON_HOLD`,
`BLACKLISTED`, `PROSPECT`, plus `MERGED` which only the merge writes.

That was the **eighth defective instrument** in this batch. It is preserved here
rather than quietly corrected, because the pattern it belongs to is the most
expensive one in the whole phase: *an assertion with no valid subject passes
while proving nothing.*

A5's underlying claim was independently verified and stands — the detector read
`SELECT ... FROM business_partners WHERE id <> ?` with no status filter at all —
and is addressed by R1–R6. A ninth instrument slip was caught during this work
and is recorded for the same reason: the first merge probe read a `data` key the
findings never had, and a tenth (CF8/CF9) passed against a case it did not
exercise, because both records carried the identifier so the scan met the living
one first and never had to resolve anything. Both were re-aimed.

## 6 · Mutation (§19)

**RUN IN PROGRESS — results not yet recorded.** This section will be completed
from the run itself; it is deliberately left empty rather than filled with
expected numbers.

Twenty mutants, one per corrected invariant, each applied to a fresh copy of the
tree with its own database, then measured against four suites
(`p6_batch3_corrective`, `p6_batch3`, `connect_org`, `onboarding_engines`).

Rules the battery holds itself to:

- a **FATAL is not a catch** — a mutant that stops the suite running proves
  nothing about whether the tests would have noticed it;
- an **ANCHOR-MISS is not a catch** — if the text the mutant edits is not found
  exactly once, the mutant was never applied and is reported as a miss;
- a **dirty baseline aborts** the whole battery, because a suite that already
  fails cannot measure anything.

Survivors, anchor misses and fatals will be named individually. Two mutants
(CM19, CM20) target the A6 SQLite blind spot and therefore run on SQLite, where
that defect lives; the rest run on MariaDB.

Note on §19's "remove the tenant predicate": in this architecture there is no
tenant predicate to remove — isolation is structural, one database per tenant.
Rather than invent a fake mutant, that slot is taken by **CM17**, which redirects
an access request to the wrong organisation, and the isolation *property* is
asserted directly by CH6 and CH7.
