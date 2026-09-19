# Phase 6 · Batch 1 — Security results

*Every attack in the owner's mandatory list, what it did, and what the database
said afterwards.*

**Battery:** `phpapp/tests/test_p6_batch1.php` (115 assertions) plus three
real-process workers: `_p6_worker.php`, `_p6_tenant_worker.php`,
`_p6_legacy_worker.php`.

**Rule applied throughout:** *a return code is never evidence.* Every verdict
below is read back from the database after the attack. A guard that returned
"refused" and wrote the row anyway would pass a return-code test and fail every
one of these.

---

## 0. The baseline — written first, against the unmodified code

The battery was written **before** the implementation and run against the code as
it stood.

```
BASELINE (pre-implementation)   35 passed, 39 failed
FINAL   (shipped)              115 passed,  0 failed
```

Each of those 39 failures is a defect this batch removes. That ordering is the
whole point: a test written after the fix proves only that the fix matches
itself.

---

## 1. Direct-URL attack · 2. Forged POST

| Attack | Result |
|---|---|
| `POST /candidate-unlink-pro?id=<A>` with `link_id` = **candidate B's link**, in its own OS process, through the real router | **Refused.** Candidate B's link read back from the database, still `LINKED`, same row id |
| The same through the ledger function directly | Refused, same wording as a link that does not exist |
| `/candidate-link-pro` with a `pro_id` that names no professional | Refused, nothing written |

The refusal for *"exists but is not yours"* is **word-for-word identical** to
*"does not exist"*, so the refusal cannot be used to enumerate other people's
relationships. Probes `B1 · B2 · B3 · B4 · B5`.

## 3. Wrong-candidate `link_id`

Covered above. `B4` is the forged POST; `B1/B2` the direct call. In both cases
the *owner's own* link still unlinks normally (`B5`) — the guard closed the hole
without removing the feature.

## 4. Wrong-branch attack

| Actor | Attack | Result |
|---|---|---|
| Coordinator scoped to Mumbai + unit IND | link an **Ahmedabad** inspector | **Refused**, ledger count unchanged (`D1 · D2`) |
| same | link a candidate in **another business unit** | **Refused** (`D3`) |
| same | link an **in-branch** inspector to a tenant-global professional | **Allowed** (`D4`) |
| same | link an **in-unit** candidate to the same professional | **Allowed** (`D5`) |

`D4`/`D5` are the deliberate negative: a marketplace professional carries no
branch, and filtering it by one would be **deciding Q5/Q11**. Mutation **M8**
proves these probes fail the moment anyone does.

## 5. Entitlement-off attack

With Connect switched off, **every** writer of `cx_identity_link` was asked:

| Writer | Result |
|---|---|
| `connect_identity_link_create()` | refused (`C1`) |
| `connect_identity_candidate_link_create()` | refused (`C2`) |
| `connect_identity_unlink()` | refused (`C3`) |
| the ledger itself | **byte-for-byte unchanged** (`C4`) |

Switched back on, all three work again (`C5`) — the gate was added, not a
capability removed.

## 6. Direct function bypass

Every gate is asked **by the function**, not by the route (invariant I27):

- `connect_identity_unlink()` carries its own ownership expectation (`M1`).
- `link_inspector_users()` refuses a caller without the People right and creates
  **nothing** — asserted on row counts, not on the return value (`A9 · A10 · A11`) —
  while an authorised caller still reconciles normally (`A12`).

## 7. Direct SQL uniqueness attack

Raw `INSERT`s from a separate process, with **no PHP guard running at all**:

| Attack | Result |
|---|---|
| duplicate live **inspector-axis** row | rejected by the database (`F2`) |
| duplicate live **candidate-axis** row | rejected by the database (`F3`) |
| rows surviving afterwards | exactly one of each (`F4 · F5`) |

And the deliberate negative — history is never constrained: one pair linked and
unlinked four times leaves four rows, none live (`F6 · F7`).

`F8a–f` then assert that **the writer** stamps the live keys at the moment of the
write, not the migration's back-fill. Without that probe a writer that forgot its
keys would look protected as soon as any other process touched the database, and
be completely unprotected inside its own request. Mutation **M17** survived until
this probe existed.

## 8. Real-process concurrency

Four **separate OS processes**, synchronised on one wall-clock microsecond, all
linking the same pair:

```
live links afterwards ......... 1   (G1)
processes that crashed ........ 0   (G2)
processes told they succeeded . ≥1, and every one names a link
                                that really is live (G3 · G6)
```

A second race, two processes claiming **different** inspectors for one
professional, leaves exactly one live link (`G5`).

`G7` asserts the other half of the rule: a failure that is **not** a uniqueness
conflict is re-thrown, never answered as "Linked." — a disk error reported as
success is worse than any refusal.

## 9. Cross-tenant isolation — now tested, not argued

Two **real, separate tenant databases** (two MySQL schemas on the MariaDB run,
two SQLite files on the SQLite run), built and used by their own process:

| | |
|---|---|
| Tenant B's ids used on the inspector axis in tenant A | **refused** (`E1`) |
| Tenant B's ids used on the candidate axis in tenant A | **refused** (`E2`) |
| Identity links gained by tenant A | **none** (`E3`) |
| Tenant B's person visible in tenant A | **not at all** (`E4`) |
| Tenant B after the attack | **completely untouched** (`E5`) |

> **This is what moves invariant I15 from NOT ESTABLISHED to HOLDS.** Before this
> batch, tenant isolation was argued from the architecture and had never been
> exercised. Mutation **M18** confirms the probe has teeth.

## 10. Legacy duplicate data

A database that **already** held two live rows for one pair, migrated from
scratch in its own process:

| | |
|---|---|
| Migration completed | **yes** — deployment never fails for data it found (`H2`) |
| Rows after migration | **2 — both survive.** Nothing merged, unlinked or deleted (`H3`) |
| Duplicate reported instead | yes (`H4`) |
| The affected index | **skipped, not forced** (`H5`) |
| The *other* axes | **still protected** — one mess does not disarm everything (`H6`) |
| After a person resolves it by hand | duplicate gone (`H7`), index builds itself (`H8`) |

## 11. Partial failure and retry

| | |
|---|---|
| A reconciliation whose second write cannot land | **no orphan team member** (`I1`) |
| **Two administrators pressing the button in the same instant** | exactly **one** team member created (`I2`), the login really linked (`I3`), **no orphan left by the loser** (`I4`) |
| Running the reconciliation again | creates nothing (`A8 · J`) |
| An audit write the database rejects | does not throw, and the relationship still stands (`I41a–c`) |

## 12. The structural boundary

Two source-level assertions, added by the adversarial pass:

- `W1` — **no file outside `lib/connect_identity.php` inserts into the ledger.**
  This caught a real offender: `lib/seed_scenario_s06.php` had been writing the
  ledger directly, without the live-key columns, since before this batch.
- `W2` — **every production unlink states which record it is acting for.** An
  unlink with no stated expectation performs no ownership check at all.

These are source-level on purpose: no behavioural probe can see a writer that
does not exist yet, and *"a rule applied where somebody remembered to apply it"*
is the defect family this programme keeps finding.

---

## Result

| Attack class | Verdict |
|---|---|
| Direct URL · forged POST · wrong-candidate id | **held** |
| Wrong branch / wrong unit | **held** (per-end visibility; Q5/Q11 still open) |
| Entitlement off | **held** on all three writers |
| Direct function bypass | **held** |
| Direct SQL uniqueness | **held** on both engines |
| Real-process concurrency | **held** — one relationship, truthful answers |
| Cross-tenant | **held and, for the first time, tested** |
| Legacy duplicates | **held** — non-destructive, self-healing |
| Partial failure · retry | **held** for the single operation; see the limitation below |

**Engines:** every probe above ran on **SQLite 3.45.1** and on **MariaDB
10.11.14**. No claim here rests on SQLite alone.

**The one limitation not to overclaim:** two authorised reconciliations racing
can no longer leave an orphan, but duplicate-inspector prevention in general is
**R20**, deliberately out of this batch. I22 and I42 therefore reach **PARTIAL**,
not HOLDS.
