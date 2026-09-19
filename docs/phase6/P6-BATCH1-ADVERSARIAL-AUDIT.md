# Phase 6 · Batch 1 — Adversarial audit

*One complete attack on the finished batch, performed after implementation. Not
a second opinion on the tests — an attempt to defeat the thing they passed.*

**Rule:** no product code was changed during the attack. One material defect was
found; it was then fixed with the smallest targeted change, and the affected
test, the complete regression and the mutation battery were re-run. No further
correction rounds were manufactured.

---

## The method

Six questions, each aimed at the way this batch could be *technically* correct
and still not protect anything:

1. Is the canonical writer actually canonical, or does something else write the
   ledger?
2. Does every production unlink state whose link it is removing?
3. Does any other read path still create people?
4. Can a guard be skipped by a caller that simply omits an argument?
5. Does the protection depend on somebody remembering to apply it?
6. Does the test battery prove what it claims, or does it agree with the code?

---

## Finding A — **MATERIAL** · a second writer of the identity ledger

`lib/seed_scenario_s06.php` wrote `cx_identity_link` with a raw `INSERT`:

```php
db()->prepare("INSERT INTO cx_identity_link (professional_id,inspector_id,status,linked_at) …")
db()->prepare("INSERT INTO cx_identity_link (professional_id,candidate_id,status,linked_at) …")
```

It therefore bypassed the canonical writers **and the live-key columns**, so the
rows it created were **invisible to U1/U2/U3** — live relationships the database
could not keep unique, on a path that a Master Admin can run from a button in
Settings.

This is the batch's own protection defeated by a file written before the batch
existed, and it is the defect family this whole programme keeps finding: *a rule
applied where somebody remembered to apply it.*

**Severity: material.** Not a live security hole — the seed is master-only and
namespaced demo data — but the protection was genuinely absent on a real,
shipped path, and any future writer would have inherited the same silence.

**Smallest targeted fix.** The seed now calls the canonical writers, exactly as
the S01 and S02 seeds already did:

```php
connect_identity_link_create($pro, $insp, 'manual', 'DEMO-S06');
connect_identity_candidate_link_create($cand, $pro, 'manual', 'DEMO-S06');
```

and `tools/seed-scenario-s06.php` now acts as Master Admin on the command line,
as the in-app button already did.

**And the guard that would have caught it.** Probe `W1` asserts that **no file
outside `lib/connect_identity.php` inserts into the ledger**. It is a
source-level assertion on purpose: no behavioural probe can see a writer that
does not exist yet.

**Re-verification after the fix:** `test_s06_gap_showcase` green, complete
regression green on both engines, mutation battery re-run in full against the
shipped tree.

## Finding B — **MATERIAL (test)** · pre-existing cross-test pollution

The S06 test then failed **in the full suite but passed alone**. The cause was
not the fix: `tests/test_recruit_onboarding_kit.php` applies the
`RECRUITMENT_HR` package, which sets `connect_enabled='0'`, and restoring
`ENTERPRISE` does **not** turn it back on — correctly, because that setting
records the *tenant's own* choice and a package apply must not presume to reverse
it. In a shared test process, every later test inherited a workspace with the
marketplace switched off.

This pollution had been there all along. It only became visible once identity
linking started asking the entitlement — the batch did not cause it, it
**revealed** it.

**Fix:** that test now snapshots and restores the switch, following the pattern
`test_edition_autoapply.php` already uses, and asserts the restore worked. No
assertion anywhere was weakened.

> Worth stating plainly: this class of failure — *passes alone, fails in the
> suite* — is the one Phase 5 was bitten by twice. It is always worth chasing to
> the actual polluting file rather than making the failing test defensive.

## Finding C — **RESIDUAL, accepted** · the live key must be remembered

U1/U2/U3 constrain the `uq_*` columns, and those columns are set by the writer.
A future writer that forgets them creates a live relationship the database cannot
keep unique — precisely Finding A, in a file not yet written.

**Mitigations now in place:** the three canonical writers are the only writers
(`W1`), `F8` proves each one stamps its keys *at the moment of the write*, and
mutant `M17` proves `F8` has teeth.

**A stronger option exists, and was measured rather than assumed.** A
`GENERATED ALWAYS AS (…) VIRTUAL` column would make the key impossible to forget.
Tested directly on both engines:

| | SQLite 3.45.1 | MariaDB 10.11.14 |
|---|---|---|
| `ALTER TABLE … ADD COLUMN … GENERATED … VIRTUAL` | ✔ | ✔ |
| `CREATE UNIQUE INDEX` on it | ✔ | ✔ |
| Duplicate live row rejected | ✔ | ✔ (`ERROR 1062`) |
| Slot released after unlink | ✔ | ✔ |

**Not done in this batch, deliberately.** It replaces the core protection after
12 000+ assertions and 23 mutants have already proved the current design green on
both engines, which is a redesign, not the "smallest targeted fix" this gate
allows. **Recommended as a named follow-up for owner decision.**

## Finding D — **RESIDUAL, accepted** · an unlink with no stated expectation

`connect_identity_unlink($id)` called with no `$expect` performs no ownership
check — it cannot, because a ledger cannot know which record a screen is acting
for. Both production callers state it; a future one might not.

**Mitigation:** probe `W2` asserts that every production call site states its
expectation. Making the argument mandatory was considered and rejected: the
marketplace console legitimately acts on a link it has just resolved itself, and
a required-but-ignorable argument is a worse guard than an asserted convention.

## Finding E — **RESIDUAL, accepted** · the scope helpers fail open if absent

`connect_identity_scope_ok()` is written `!function_exists('scope_allows') || scope_allows(…)`.
If `lib/access.php` were not loaded, the scope check would silently pass.

This matches the load-order idiom used throughout the codebase, and `access.php`
is required by `index.php` on every path. **Recorded rather than changed**, because
making it fail closed would break boot ordering in exactly the places the idiom
exists to protect. Noted so it is a known shape, not a surprise.

## Finding F — no other read path creates people

Swept every caller of `team_member_create()`:

| Call site | Verdict |
|---|---|
| `ops.php:1029` — `link_inspector_users()` | explicit, authorised, transactional ✔ |
| `ops.php:8414` — the single-user save form | an explicit write action ✔ |
| `orgadmin.php:1032` — `org_import_link_team()` | an explicit import ✔ |

**No read, list, search, export, report, dashboard, boot or API path creates a
person.** Probes `A1–A5` assert it across all seventeen consumers of
`inspectors_list()`.

> **Out of scope, and still true:** `connect_tax_backfill_pending()` still creates
> taxonomy nodes from a marketplace search. That is **R23**, excluded from this
> batch by the owner's mandate. It is not fixed and is not claimed to be.

## Finding G — the battery was attacked, and lost four times

The mutation battery is itself an instrument, so it was attacked as one. Five
mutants survived the first run; **four of them were defective probes, not
defective code**:

| | The probe's flaw |
|---|---|
| `M16` | the partial-failure test deleted the login *before* the function read it, so the code path under test never ran |
| `M17` | the migration's back-fill repaired the damage before any probe looked |
| `M18` | the only relevant probe ran as a branch-scoped actor, so the *other* end's check refused first and it passed for the wrong reason |
| `M13` | nothing exercised the path at all |

The fifth, `M14`, was an **equivalent mutant** — proved, not assumed — and was
re-aimed rather than argued away.

**This is the finding that matters most about the evidence:** a battery at 18/23
looked like good code with a few gaps. It was good code with four probes that
would have passed whatever the code did.

---

## Verdict

| | |
|---|---|
| Material defects found | **2** — Finding A (product), Finding B (pre-existing test pollution) |
| Both fixed, smallest change | ✔ |
| Affected tests re-run | ✔ |
| Complete regression re-run, both engines | ✔ |
| Mutation battery re-run in full | ✔ |
| Residuals accepted and recorded | **3** — Findings C, D, E |
| Correction rounds manufactured | **none** |

**The attack is closed.** Nothing found here reopens the batch's scope, changes
its architecture, or touches R20 or Q1–Q18.
