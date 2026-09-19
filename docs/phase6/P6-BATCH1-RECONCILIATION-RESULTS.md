# Phase 6 · Batch 1 — Reconciliation results

*What the batch changed in the data, what it did not touch, and what it now
reports instead of quietly fixing.*

---

## 1. Schema changes

| Object | Change | Destructive? |
|---|---|---|
| `cx_identity_link.uq_pro_insp` | new `INT NULL` | no — additive |
| `cx_identity_link.uq_insp` | new `INT NULL` | no — additive |
| `cx_identity_link.uq_cand` | new `INT NULL` | no — additive |
| `ux_cx_idlink_pro_insp` | new UNIQUE index (U1) | no |
| `ux_cx_idlink_insp` | new UNIQUE index (U2) | no |
| `ux_cx_idlink_cand` | new UNIQUE index (U3) | no |

**No table created. No column removed or retyped. No row deleted. No business
field written.**

The three columns are added through the existing `ensure_column()`, inside the
existing `connect_identity_migrate()`, behind the existing `db_epoch()` guard —
no new migration mechanism.

## 2. The back-fill

One `UPDATE` per key, stamping the live key onto rows that pre-date it:

```
uq_pro_insp = professional_id   where LINKED and the row is inspector-axis
uq_insp     = inspector_id      where LINKED and the row is inspector-axis
uq_cand     = candidate_id      where LINKED and the row is candidate-axis
                                everything else stays NULL
```

**It touches only the three new columns.** No `status`, no `linked_at`, no
`linked_by`, no business fact is altered. A row that was UNLINKED before is
UNLINKED after, with the same timestamps.

> **A note worth keeping.** The back-fill re-runs whenever the migration runs,
> which makes the protection self-repairing — and made it possible for a writer
> that forgot its keys to look protected. Probe `F8` and mutant `M17` exist
> because of exactly that; see the mutation results.

## 3. Legacy duplicates — surfaced, never resolved

If a database already holds two live rows for one key, that index is **not**
built. Proved end-to-end in a database of its own (`_p6_legacy_worker.php`):

| | Result |
|---|---|
| Migration over duplicated data | **completed** — deployment never fails for data it found |
| The two duplicate rows | **both survive, unchanged** |
| The affected index | skipped |
| The other two indexes | **still built** |
| The duplicate | reported by `connect_identity_duplicates()` and on system status |
| After a person resolves it | duplicate gone, and the next migration **builds the index itself** |

**Nothing is merged, unlinked or deleted to make a constraint fit.** Choosing
which of two live links survives is a judgement about who a person is, and this
programme does not make that silently.

## 4. Repeat-run behaviour

| Run | Effect |
|---|---|
| First | columns added · keys back-filled · indexes built |
| Second and later | `db_epoch()` guard; `ensure_column()` no-op; `CREATE UNIQUE INDEX` fails harmlessly inside `try/catch`; back-fill is idempotent by construction |
| On a database with duplicates | as above, minus the affected index, every time, until a person resolves it |

**Deployment cannot fail.** Every step is wrapped; the worst case degrades to the
behaviour that existed before the batch.

## 5. Engine behaviour

| | SQLite 3.45.1 | MariaDB 10.11.14 |
|---|---|---|
| `ALTER TABLE ADD COLUMN` | ✔ | ✔ |
| Unlimited NULLs in a UNIQUE index | ✔ | ✔ |
| Duplicate live row rejected | ✔ | ✔ |
| Slot released by nulling the key | ✔ | ✔ |
| Driver-specific branch needed | **none** | **none** |

> The money module's `books_unique_number_index()` needs a SQLite **partial**
> index because MySQL has none. Here the same effect comes from the value
> itself — the key is NULL when the slot is free — so one statement gives
> identical semantics on both engines. That is the `books.php` insight used
> deliberately rather than as a fallback.

## 6. Operational data reconciliation

### Unlinked inspector logins

`inspectors_list()` used to create a team member for any active inspector-role
login that lacked one. That is gone. The residual population is a **finite
historical backlog** — every current path that creates such a login already links
it explicitly (`org_import_link_team()` on the register import, and the
single-user form inline).

| Before | After |
|---|---|
| created silently, on any of 17 read paths | **reported** by `team_unlinked_logins()` |
| invisible | named on the People screen, with one button |
| invisible | a `warn` row on system status |
| could orphan, and repeat the orphan on every page load | one authorised, transactional action |

### Duplicate identity relationships

| Before | After |
|---|---|
| two live rows could coexist, and did | rejected by the database |
| the resolver silently preferred the newest | axis-aware, and the duplicate cannot be created |
| nothing reported them | `connect_identity_duplicates()` + a system-status row |

## 7. What was NOT reconciled — and why

| Not done | Why |
|---|---|
| Existing orphan inspectors from past runs | **R2/R20**, out of this batch. Resolving them means deciding whether two inspector rows are one person — a Phase 6 convergence question |
| `agencies` → organisation cross-reference | **R4**, not in this batch |
| `candidates.person_ref` audit / reversal | **R18/R21**, not in this batch |
| `partner_contacts` duplicate control | **R30**, gated on **Q17** |
| Any identity merge, anywhere | **explicitly forbidden** by the batch mandate |

## 8. Data written by this batch

| Category | Rows |
|---|---|
| Business records created | **0** |
| Business records modified | **0** |
| Historical records deleted | **0** |
| Derived key columns populated | every `cx_identity_link` row, all three columns |

The only thing this batch put into a live database is a number the database uses
to keep a promise the application was making without it.
