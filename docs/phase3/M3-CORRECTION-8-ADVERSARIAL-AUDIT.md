# Phase 3 · M3 CORRECTION #8 — ADVERSARIAL AUDIT

Scope: commits `345901e` … the correction #8 completion report — the split of the
shared audit spine into a core write and optional metadata.

**Two findings. S1 itself holds; both findings are in what surrounds it.**

---

## What held up

**The core audit row survived every failure I could construct.** In all four
scenarios probed — column dropped, table replaced by a view, optional migration
failing, index name taken — the core row was still written, or its failure was
recorded where it genuinely could not be. That is the primary objective of this
correction and it is met.

**The retry bound behaves.** Under a permanently failing migration the log shows
exactly three attempts and then silence, per workspace per process, as designed.

---

## 🟠 T1 · MEDIUM · The core-failure test proves different things on the two engines

`C8.6` claims to prove that **the core INSERT** can fail and that the failure is
observable. It forces this by replacing `activities` with a **view of itself**.

On SQLite that is exactly what happens — a view cannot be inserted into:

```
INSERT into view: THROWS — cannot modify activities because it is a view
```

**On MariaDB, the authoritative engine, it is not what happens.** A MySQL view over
a single table is *updatable*, and the INSERT succeeds:

```
MariaDB INSERT into view: SUCCEEDED (view is updatable)
MariaDB ALTER on view:    THROWS — 'act_v' is not of type 'BASE TABLE'
MariaDB CREATE INDEX:     THROWS — 'act_v' is not of type 'BASE TABLE'
```

So on MariaDB the assertion passes because **`act_migrate()`'s index creation
throws first**, and `act_log()` catches that. The audit row never gets as far as
the INSERT. The assertion is true on both engines; the **mechanism differs**, and
the sentence "the core INSERT fails and is reported" is proved only on SQLite.

The same qualification applies to mutation **S1-M2** (restore silent swallowing),
which is caught — but the battery runs on SQLite only.

This is the shape this whole sequence keeps finding, arriving this time in my own
evidence: **an assertion satisfied for a different reason than the one it names**,
on the engine that is meant to be authoritative. Nothing here says the fix is
wrong; it says one claim about it is under-proved where it matters most.

The remedy is a MariaDB-specific way to make the core INSERT itself fail — for
example a column made too narrow for the value, or a constraint the row cannot
satisfy — asserted separately from the migration path.

## 🟡 T2 · LOW–MEDIUM · A missing INDEX disables a working COLUMN

`act_migrate_optional()` does two things and reports one answer:

```php
ensure_column('activities', 'cond_key', …);     // correctness — can we store it?
act_index('activities', 'idx_act_cond', …);     // performance — can we find it fast?
```

If the index cannot be created, the function returns **false**, and
`act_set_cond_key()` therefore refuses to store metadata into a column that exists
and works perfectly.

**Probe — the column present, only the index name taken:**

```
T2 · cond_key column present: yes
T2 · act_migrate_optional() returned: false
T2 · act_log returned: 1                      <- the core row is fine
T2 · cond_key actually stored: ''             <- but the metadata was abandoned
FAIL T2 · metadata that COULD be stored is not abandoned over a missing index
```

A performance concern is being treated as a correctness precondition. It is the
same conflation that started this thread — *a supported type is not an openable
record* — in a new costume: **a usable column is not an indexed column.**

Low severity because the core audit trail is untouched and the practical trigger is
narrow. But it is the exact category of defect correction #8 exists to remove, so
leaving it would be inconsistent.

---

## Note, not a finding

`act_migrate_optional()` keeps a per-epoch attempt counter in a static array that is
never pruned. In a long-running process that switches workspace many times this
grows by one integer per workspace. Harmless at any realistic scale; recorded so it
is a decision rather than an oversight.

---

## Verdict

S1 is genuinely fixed: 76 call sites across seven modules keep their audit trail
when the optional column is absent, reproduced at **0 of 3 → 3 of 3**, with 10/10
mutations caught and a clean full regression on both engines.

But one acceptance criterion — *"false-green tests specifically ruled out"* — is not
met: `C8.6` is a false green **on MariaDB**, in the sense that it passes for a
reason other than the one it states. And T2 is a small instance of the very
conflation this correction was written to remove.

**Recommended status: M3 CORRECTION #8 NOT ACCEPTED**, pending **T1** and **T2**.

Open across M3: **H1, H3, S2, S3, T1, T2**.
