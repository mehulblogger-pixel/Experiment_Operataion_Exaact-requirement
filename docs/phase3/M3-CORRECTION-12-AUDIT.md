# M3 CORRECTION #12 — AUDIT

**Scope:** X1 (`STORED` did not mean stored), X2 (three of the four error
channels had never been exercised), X3 (the "workspace switch" never switched
workspace). Nothing else. H1, H3, S2, S3 and U2 remain open by instruction.

---

## X1 — `act_set_cond_key()` returned `STORED` whenever PDO did not throw

### What the code did

```php
db()->prepare("UPDATE activities SET cond_key=? WHERE id=?")->execute([$key, $id]);
return ACT_COND_STORED;
```

### Root cause

`execute()` reports **whether the statement ran**, not **whether it changed
anything**. An `UPDATE ... WHERE id = <id that does not exist>` is a perfectly
valid statement. It matches no row, writes nothing, and throws nothing. The
function then announced `STORED`.

Two real cases produced a false `STORED`:

1. **A nonexistent activity id.** The caller believes a condition key is on the
   record. It is on no record.
2. **An activity id belonging to another workspace.** Because EXAACT is
   one-database-per-tenant, a foreign id simply is not present in the connected
   database. The `UPDATE` matches nothing, and the answer was still `STORED`.

Case 2 is the serious one. The idempotency guard in `appr_condition_seen()`
asks "has this condition already been recorded?" A `STORED` that recorded
nothing means the guard is reasoning about a row that does not exist.

### Why `rowCount() > 0` is NOT the fix

The obvious repair — treat `rowCount() < 1` as failure — is wrong, and the
brief said so. Affected-row semantics differ between engines when the new value
equals the existing value. Measured on this machine, not assumed:

| case | MariaDB 10.11.14 | SQLite 3.45.1 |
|---|---|---|
| row exists, value **changes** | 1 | 1 |
| row exists, value is **already identical** | **0** | 1 |
| no such row | 0 | 0 |

So on MariaDB `rowCount() > 0` reports **FAILED for a correct, idempotent
re-write** — a false negative — while still failing to distinguish "no such
row" from "already correct". It swaps one wrong answer for another.

### The fix — read the value back

`STORED` is defined as *"the required `cond_key` value is actually present on
the intended activity record in the intended workspace."* The only statement
that can answer that is a read of that record in that connection:

```php
$row = ops_one("SELECT cond_key FROM activities WHERE id=?", [(int) $id]);
if (!$row)                                    return ACT_COND_FAILED;  // absent / foreign workspace
if ((string) $row['cond_key'] !== (string) $key) return ACT_COND_FAILED;  // not persisted as given
return ACT_COND_STORED;
```

This is engine-independent by construction: it asks the database what it holds
rather than asking the driver what it did. It is correct for the idempotent
re-write (the value is already right, so it reads back right, so `STORED`), and
it additionally catches silent truncation by the column — a case neither
`rowCount()` nor an exception would ever surface.

---

## X2 — three of the four channels had never held anything

Correction #11 introduced four workspace-keyed error channels: `core`, `col`,
`idx`, `write`. The #11 suite asserted that Workspace B could not see A's
errors in all four. Only `core` was ever given an error. The other three were
empty in A, so B reading empty proved nothing. Three of the four isolation
claims could not fail.

**Confirmed by mutation before the fix:** a mutation that made the `col`
channel global survived the entire M3 suite. So did one for `idx`.

A second, separate defect was found while repairing the first — see
TEST-RESULTS §"Two false-green fixtures".

**A third defect was found in the product, not the test:** there was no way to
read the `col` and `write` channels independently. `act_optional_error()`
returned `col ?: write`. A channel that cannot be observed on its own cannot be
tested on its own. Two pure read-only accessors were added,
`act_cond_column_error()` and `act_cond_write_error()`, and
`act_optional_error()` now composes them, keeping its meaning for existing
screens.

---

## X3 — the "workspace switch" never switched workspace

The #11 suite changed workspace by assigning `$GLOBALS['__db_epoch']`. That
changes the *key* the channels are stored under while leaving the connection,
the database and the data exactly where they were. Tenant A and Tenant B were
the same database throughout. Every isolation result in #11 was measured
against a fiction.

Worse, raw assignment collides with the private counter inside `db(true)`,
which is what actually issues epochs — an interference this project has already
been bitten by once.

The corrected suite switches workspace through the application's real path —
repoint the connection, `db(true)`, let `db()` rebuild from `config.php` — the
same path "Log in as", provisioning and the tenant sweep take, and **proves the
database identity changed** (`SELECT DATABASE()` / `sqlite_path`) before making
any claim about isolation.

---

## Scope discipline

No status, transition or permission was added or changed. `docs/01-roles.md`,
`docs/02-permission-matrix.md` and `docs/03-object-lifecycles.md` are unaffected
by this correction, so they are unchanged — the code and the docs do not
disagree.
