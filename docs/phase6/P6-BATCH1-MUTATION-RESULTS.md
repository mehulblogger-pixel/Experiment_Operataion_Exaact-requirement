# Phase 6 · Batch 1 — Mutation results

*Twenty-three deliberate defects, each one a way this batch could have been
written wrong, and the named assertion that catches it.*

**Harness:** `p6mut.py`, derived from the Phase 4/5 harness. Each mutant is
applied to a **fresh copy** of `phpapp/`, the deploy checksum is regenerated, and
the battery is run against its **own MariaDB database**. The baseline must be
clean or the run aborts.

**Rules carried forward and enforced:**

- A clean baseline is mandatory — `baseline: 0 (no failures)`.
- Every mutant names **one specific assertion** that detects it. A mutant
  "caught" because something unrelated broke is not evidence.
- **A FATAL (a suite that crashed) is NOT a catch.** The Phase 5 harness scored
  crashes as catches; this one reports them separately and excludes them. Zero
  occurred.
- No test was weakened, deleted or skipped to make a mutant die.

---

## Result

```
baseline: 0 (no failures)
caught:   23 of 23
survivors: none
FATALs:    none
anchor misses: none
```

---

## The mutants

### Read must never create identity

| # | The defect | Caught by |
|---|---|---|
| **M1** | the read path creates people again | `A1 A2 A3 A5` — reading the team list creates no inspector, links no login, leaves the stray login untouched |
| **M2** | the reconciliation stops checking its own authority | `A9 A10 A11 A12` — an unauthorised caller reconciles nobody **and creates nothing**, while an authorised one still works |

### A record id is never authorisation

| # | The defect | Caught by |
|---|---|---|
| **M3** | the unlink drops the ownership check | `B1 B2 B4` — another candidate's link is refused, and is still `LINKED` afterwards, including through the forged POST |
| **M4** | authorisation is asked **after** the record is named | `B2b` — "no such inspector" stops reading identically to "not entitled" |
| **M19** | the out-of-scope refusal is made distinguishable | `B2a B2b` — the branch refusal starts naming the branch |
| **M18** | the professional end stops being checked at all | `B2e B2f B2h` — an unknown professional is accepted by an all-scope actor, and a row appears |

### One entitlement for one ledger

| # | The defect | Caught by |
|---|---|---|
| **M5** | the recruitment writer stops asking for the entitlement | `C2 C4` — it links with Connect off, and the ledger count moves |
| **M5b** | the unlink writer stops asking | `C3 C4` |

### Scope

| # | The defect | Caught by |
|---|---|---|
| **M6** | the inspector end is no longer scope-checked | `D1 D2 D4 B2a` |
| **M7** | the candidate end is no longer scope-checked | `D3` |
| **M8** | the tenant-global professional is given a branch — i.e. **Q5/Q11 decided by the back door** | `D4 D5` |

> **M8 is the guard on a decision, not on a behaviour.** It exists so that nobody
> can quietly answer an open business question by adding a scope check that looks
> tidy.

### Database uniqueness

| # | The defect | Caught by |
|---|---|---|
| **M9** | the unique indexes are never built | `F2 F3 F4 F5` — raw duplicate INSERTs succeed and two live rows survive |
| **M10** | an UNLINKED row keeps its live key, so the slot is never released | `X-G2 X-G3 F6` — a freed candidate can no longer be re-linked |
| **M11** | the key we **deliberately omitted** is added | `X-C` — two candidates may no longer share one professional, breaking locked invariant **I3** |
| **M17** | the candidate-axis writer bypasses the live keys | `F8d` — the key is missing **at the moment of the write** |

> **M17 is the one that taught us something.** It survived the first battery,
> because the migration's back-fill re-stamps every row and repaired the damage
> before any other probe looked. A writer that forgot its keys was therefore
> *invisible* — protected-looking, and completely unprotected inside its own
> request. `F8` now reads the row back immediately, in the same process, with no
> migration in between.

### Concurrency

| # | The defect | Caught by |
|---|---|---|
| **M12** | the duplicate conflict is swallowed and reported as success | `G6` — three racing processes are told they succeeded while naming no live link |
| **M13** | a **non-duplicate** exception is swallowed instead of re-thrown | `G7` — a disk error is answered as "Linked." |
| **M16** | the two-write reconciliation loses its transaction | `I2 I4` — two simultaneous reconciliations create two team members and leave an orphan |

### Legacy data

| # | The defect | Caught by |
|---|---|---|
| **M14** | duplicate detection is broken, so nothing is skipped or reported | `H4` |
| **M15** | legacy duplicates are **auto-unlinked** to make the constraint fit | `H3 H4 H5` — one of the two rows disappears |

> **M14 was re-aimed.** Its first formulation — deleting the pre-check — turned
> out to be an **equivalent mutant**: the `CREATE UNIQUE INDEX` is attempted, the
> database rejects it because the duplicates are there, and the existing
> `try/catch` swallows it, so the observable behaviour is identical. Rather than
> record a survivor that could not be caught, the mutation was re-aimed at the
> thing that actually protects the data — the duplicate **detection** — which is
> both non-equivalent and the real failure mode.

### Audit

| # | The defect | Caught by |
|---|---|---|
| **M20** | the audit reverts to passing the table name | `Y-A1 Y-B` |
| **M20b** | the identity entity registration is removed | `Y-A1 Y-B` |

### Axis separation

| # | The defect | Caught by |
|---|---|---|
| **M21** | the resolvers stop distinguishing the two axes | `X-A X-E X-F1 X-F2` — the original defect returns |

---

## The first battery, and what it changed

The first run scored **18 of 23**. Rather than argue the five survivors away,
each was root-caused:

| Survivor | Why it lived | What was done |
|---|---|---|
| **M13** | nothing exercised a non-duplicate exception | added `G7`, a direct probe on the conflict translator |
| **M14** | **equivalent mutant** — proved so | re-aimed at duplicate detection |
| **M16** | the partial-failure probe deleted the login *before* the read, so the function never saw it — the probe agreed with the code instead of testing it | replaced with a **two-process race**, which is also what really happens |
| **M17** | the migration back-fill repaired the damage before any probe looked | added `F8`, which reads the keys back at the moment of the write |
| **M18** | the only probe for it ran as a branch-scoped actor, so the *other* end's scope check refused first and the probe passed for the wrong reason | added `B2e–h`, run as an all-scope actor |

Four of those five were **defective instruments, not defective code** — a probe
that could pass whatever the code did. That is the second recurring failure
family this programme keeps finding, and it is exactly what mutation testing is
for.

After the fixes: **23 of 23**, re-run in full against the shipped tree.
