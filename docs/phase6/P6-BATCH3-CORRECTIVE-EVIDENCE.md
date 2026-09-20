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

Three batteries were run. **Only the third is the gate**; the first two are
history, and no result from them is carried into the matrix below.

Each mutant is applied to a fresh copy of the tree with its own database, then
measured against four suites (`p6_batch3_corrective`, `p6_batch3`,
`connect_org`, `onboarding_engines`). A FATAL is not a catch. An unapplied
mutant is not a catch. A dirty baseline aborts the battery.

| Battery | Source state | Result |
|---|---|---|
| 1 | `311d191` | `caught: 15 of 20` — survivors CM3, CM6, CM8, CM9, CM20 |
| 2 | `70b5e22` | `caught: 17 of 20` — survivors CM3, CM6, CM9 |
| **3 — the gate** | **`939de8a`** | **`caught: 19 of 20`** — sole survivor CM9 |

Battery 3 baselines: `mysql 0 (no failures)`, `sqlite 0 (no failures)`.

### The complete matrix

| # | Mutant | Engine | Verdict |
|---|---|---|---|
| CM1 | the one-primary guard is never installed | MariaDB | CAUGHT |
| CM2 | a plain index instead of a unique one (the original A1 defect) | MariaDB | CAUGHT |
| CM3 | the guard reports success without verifying the index exists | MariaDB | CAUGHT |
| CM4 | the reconciler keeps a survivor at random | MariaDB | CAUGHT |
| CM5 | the reconciler deletes the losing contacts | MariaDB | CAUGHT |
| CM6 | DDL is allowed inside a caller's transaction | MariaDB | CAUGHT |
| CM7 | the e-mail key stops trimming (PHP side) | MariaDB | CAUGHT |
| CM8 | the database e-mail key stops trimming | MariaDB | CAUGHT |
| **CM9** | **the invitation look-up stops trimming** | **MariaDB** | **PROVEN EQUIVALENT** |
| CM10 | the merge stops recording where the record went | MariaDB | CAUGHT |
| CM11 | a match on a retired record is not resolved to the survivor | MariaDB | CAUGHT |
| CM12 | retired records are reported as duplicates again (R1 removed) | MariaDB | CAUGHT |
| CM13 | the detector only looks at ACTIVE companies (the tempting wrong fix) | MariaDB | CAUGHT |
| CM14 | the public answer differs when the organisation is already ours | MariaDB | CAUGHT |
| CM15 | a taken address is told so, and a new one is not | MariaDB | CAUGHT |
| CM16 | the refusal is written into the customer's activity feed (A4 restored) | MariaDB | CAUGHT |
| CM17 | the access request points at the retired record | MariaDB | CAUGHT |
| CM18 | approving an access request hands over an account | MariaDB | CAUGHT |
| CM19 | the migration detector returns to `PRAGMA table_info` (A6 restored) | SQLite | CAUGHT |
| CM20 | a failed ALTER skips the index again (the A6 handler) | MariaDB | CAUGHT |

**CAUGHT 19 · PROVEN EQUIVALENT 1 · UNEXPLAINED SURVIVOR 0.**

### CM9 — the equivalence, demonstrated rather than argued

Two trees identical but for the one line, run against a workspace holding a
**legacy padded row** (`" ann.legacy@example.test"`), on both engines:

```
A (correct)  sqlite  err="That address already has portal access."  created=0  canonical_count=1  guard=OK
A (correct)  mysql   err="That address already has portal access."  created=0  canonical_count=1  guard=OK
B (mutant)   sqlite  err="That address already has portal access."  created=0  canonical_count=1  guard=OK
B (mutant)   mysql   err="That address already has portal access."  created=0  canonical_count=1  guard=OK
```

Identical sentence verbatim, no account created, one canonical address, no
exception, no tenant or permission difference. The PHP check is the courtesy
that produces the friendly message; the database constraint is what decides, and
`portal_acct_is_duplicate()` translates the refusal into the same words.

**Classification: PROVEN EQUIVALENT — the database backstop absorbs the
application look-up regression.**

**The condition that makes this honest.** The first run of this experiment was
invalid: `portal_acct_migrate()` keeps a static epoch marker and had already run
during boot, so the rebuild call did nothing and the backstop was absent. In
that state the mutant **did** create a second account (`canonical_count: 2`)
while the correct tree refused. The equivalence therefore holds *only while the
guard is installed* — which is precisely what A6's ledger exists to report. The
invalid run is kept in the record rather than discarded.

### Batteries 1 and 2 — what the survivors were, and why

Every survivor was a gap in a test, not a weakness in the product. All are
closed.

| Mutant | Why it survived | What closed it |
|---|---|---|
| CM3 | Every probe watched the guard **succeed**; none put it where the protection could not be created. The first correction then failed at the *column* step and returned before reaching the index verification the mutant deletes | CG12s/CG12a–c force the failure at the index step on both engines — SQLite refuses a name a table already holds; MariaDB is filled to InnoDB's 64-key limit, so the key is added and only the index is refused. CG12c asserts the key **was** added, so the probe cannot pass by failing earlier |
| CM6 | The probe compared index names in-process, and `partner_contact_migrate()` had already run, so the guarded path was never entered. The first correction still warmed that marker in the worker, and its assertion — "the row did not survive" — is an **absence**, which is also what you get when nothing ran | `txddl` now skips the warm-up, and the worker reports the **positive** fact: whether the key exists after the migration was invited in. CG7b asserts it **REFUSED** (`WORK:REFUSED:0`) |
| CM8 | Every e-mail test went through `email_key()`. The database key exists for the writer who forgets, and no probe ever wrote without the application | CB11–CB15 write straight to the table with a **leading** space (leading, because MariaDB ignores trailing spaces when comparing), including over a legacy padded row |
| CM20 | Only reachable when two boots race and one loses with "duplicate column" — and it was mis-targeted at SQLite, where file locking serialises them | Re-aimed at MariaDB; CG13–CG18 race three real processes and assert none reports FAILED. CG15 asserts the race genuinely happened |

## 7 · Final verification, from source state `939de8a`

Every figure below was produced from the final source state. No count is reused
from an earlier run.

| Check | SQLite | MariaDB *(authoritative)* |
|---|---|---|
| Focused corrective suite | **160 passed, 0 failed** | **160 passed, 0 failed** |
| Full regression | **12 834 passed, 0 failed** | **12 839 passed, 0 failed** |
| Test files executed | 510 | 510 |
| `FAIL` lines anywhere | 0 | 0 |

### Protected areas (§7) — all green, both engines

Operations (24 files) · Reporting (28) · Money (17) · Workforce (6) ·
Marketplace (58) · Recruitment (25) · Phase 4 (8) · Phase 5 (1) · Batch 1 (1) ·
Batch 2 (1) · Batch 3 (2) · Entitlement (74) · Tenant isolation (4) ·
Organisation & accounts (19). **Zero failures in any of them.**

### Real HTTP — the oracle, re-established (§13)

Rebuilt from the final source state, 15 samples per group, equal-length
addresses so any byte difference must come from the product.

| | Adversarial audit | Final state |
|---|---|---|
| Unknown organisation | 3 507 bytes · 284 ms | **3 574 bytes** · 255–339 ms |
| Known organisation | 12 389 bytes · 21 ms | **3 574 bytes** · 256–282 ms |
| Distinguishable by size | 200 / 200 | **0 / 30** |
| Separable by timing | complete separation | ranges overlap; no single observation classifies |

Database state after the run:

```
copies of the known organisation (must stay 1)        1
accounts created for KNOWN attempts (must be 0)       0
accounts created for NEW attempts (genuine)          15
access requests raised for a human                   15
  ...naming the SURVIVING organisation               15
rows in the CUSTOMER'S activity feed (must be 0)      0
security evidence retained for staff                 15
one-primary guard / client guard / vendor guard      OK / OK / OK
guards NOT ok                                         0
access requests pointing outside this workspace       0
survivor pointers outside this workspace              0
```

### Browser (§14) — 18 of 18

Content and controls verified, not navigation. Includes the two guards added
after the first run produced a false positive:

- the queue screen is the **real** screen, not the first-run setup wizard;
- the secondary action uses the house quiet class (`btn-ghost`), the primary
  action is present, and no invented class survives in the markup.

Also: neutral confirmation identical for a new and an existing company; no
disclosure of the organisation name, tax identifier, `already`, `exists`,
`duplicate` or `EXACT`; sign-in with capitals **and** surrounding spaces; a
signed-out visitor refused the queue; nothing from an anonymous attempt on the
customer's own record.

### Concurrency, backstop, migration and isolation evidence

| Evidence | Assertions | Result |
|---|---|---|
| Primary-contact concurrency | CA10–CA16 | 2 writers, 3 writers and 3 **raw** writers each leave exactly one main contact; no process crashes |
| Dirty-data reconciliation | CA17–CA22 | three primaries repaired to one; **nobody deleted**; earliest record kept, never at random; repair written to the organisation trail |
| Database e-mail backstop | CB11–CB15 | raw padded insert refused by the database with no application code involved; a legacy padded row still blocks the clean duplicate |
| Vendor door | CB16–CB18 | one canonical rule, one account |
| Borrowed-transaction DDL | CG7a–CG7d | `WORK:REFUSED:0` — the migration is observed **refusing**, and the caller's row does not survive the rollback |
| Migration honesty | CG8–CG12c | FAILED reported at the key step and at the index step, with a reason, visible in the not-OK list |
| Concurrent-boot ALTER race | CG13–CG18 | 3 of 3 saw the key missing; **none** reported FAILED; protection present afterwards |
| Migration idempotency | CG runs 1–3 + recovery | converges after repeat runs and after the index is torn off |
| Tenant isolation | CH6, CH7 | every access request and every survivor pointer names a record in this workspace |

## 8 · Defective instruments found in this batch

Recorded, not repaired quietly. The pattern is the finding.

| # | Instrument | Defect |
|---|---|---|
| 1–7 | (Batch 3 original) | recorded in `P6-BATCH3-MUTATION-RESULTS.md` |
| 8 | A5 status probe | planted `CLOSED` / `SUSPENDED`, values the product **cannot produce** |
| 9 | first merge probe | read a `data` key the findings never had |
| 10 | CF8/CF9 | both records carried the identifier, so the scan met the live one first and never resolved anything |
| 11 | CG7 (first form) | compared index names in-process; `partner_contact_migrate()` had already run, so the guarded path was never entered — and an index list cannot show an implicit commit |
| 12 | CB14/CB15 (first form) | called `portal_acct_migrate()` to rebuild the key; its static marker made it a no-op, so the clean insert had nothing to collide with |
| 13 | CG13–CG18 (first form) | the worker warm-up re-installed the key before the barrier, leaving nothing to race |
| 14 | CM9 experiment (first run) | same static marker; the backstop was absent in **both** trees, so the experiment measured nothing |
| 15 | CG8 (first form) | a 70-character index name made MariaDB reject the index **and** the ledger row, destroying the record it was checking for |
| 16 | CG7a (first form) | asserted that work was *pending*, not that the migration *attempted* it — "pending" and "attempted" are different facts |

Eleven of these sixteen are one failure: **a test that executes without
exercising what it names.** Every probe added in this batch now asserts that it
had a subject before asserting the behaviour.

## 9 · Remaining limitations

1. **A second step can still distinguish outcomes.** A genuine sign-up produces
   a usable account and a matched one does not, so somebody who then attempts to
   sign in learns which happened. Closing it would mean no account is usable
   until an e-mail is confirmed — a product decision about onboarding, not a
   defect fix, and out of scope here.
2. **Timing is levelled, not equalised.** The dominant cost is paid on every path
   before anything is decided; no artificial padding was added, per §9.
3. **`BLACKLISTED` on a company record still blocks nothing.** The enforced
   control is `hold_status`. Out of scope; recorded in the status-semantics audit.
4. **The access-request queue has no notification.** Staff see a count on the
   admin tile; nobody is e-mailed when a request arrives.
5. **`portal_acct_migrate` reports DIRTY rather than repairing duplicate live
   accounts.** Deliberate: that repair would deactivate somebody's login.
6. **§15 has no tenant predicate to remove**, because isolation is structural —
   one database per tenant. The tests assert the property rather than claim a
   filter exists; CM17 occupies that mutation slot instead.
7. **CM9's equivalence is conditional** on the guard being installed, as §6 states.
8. **No production deployment or customer UAT is claimed.**

## 10 · Status

**BATCH 3 CORRECTIVE IMPLEMENTATION — ACCEPTED / LOCKED, 2026-09-20.**

Accepted by the owner on the evidence above. Source state `939de8a`, evidence
`c3042b6`. What is locked, and what remains deliberately open, is recorded in
`P6-BATCH3-CORRECTIVE-IMPLEMENTATION.md`.

The eight limitations in §9 were accepted **as stated**, not resolved. They
remain true of the product and are the honest starting position for whatever
comes next.
