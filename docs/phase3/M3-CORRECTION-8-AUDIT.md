# Phase 3 · M3 CORRECTION #8 — AUDIT (before any code was written)

## 1 · Who depends on the shared spine (§1 F, §10)

`act_log()` in `lib/activity.php` is the single audit entry point. Counted across
the application, excluding tests:

**76 call sites in 25 files.**

| Module | Files |
|---|---|
| CRM | `opportunities.php` (11) · `leads.php` (10) · `customer360.php` · `crm.php` |
| Money | `books.php` (6) · `contracts.php` · `licenceissue.php` · `licencesync.php` |
| Quality | `ncr.php` · `reportreview.php` · `stagegate.php` |
| Operations | `ops.php` · `pdso.php` · `tasks.php` · `idems.php` · `industry.php` |
| Marketplace / Connect | `connect_identity.php` (4) · `tosrm.php` (3) · `connect_source.php` |
| Recruitment | `recruit_approval.php` (7) · `hiringreq.php` (7) · `reqfulfil.php` |
| Platform / seeds | `activity.php` · `seed_demo.php` · `seed_scenario_s03.php` |

**Call sites that pass `cond_key`: 2.** Both in `recruit_approval.php`.

That ratio is the defect in one line: correction #7 gave **74 call sites a
precondition for a feature they do not use** — and gave the other two one as well.

## 2 · Core vs optional columns (§1 A, B)

| | |
|---|---|
| **CORE** — guaranteed by the base `CREATE TABLE`, every row needs them | `kind`, `entity_kind`, `entity_id`, `partner_id`, `subject`, `body`, `direction`, `occurred_at`, `duration_mins`, `outcome`, `with_whom`, `owner`, `office_id`, `sbu`, `auto`, `created_by`, `created_at` |
| **OPTIONAL** — added by `ensure_column()`, used by one feature | `cond_key` (correction #7) |

## 3 · The failure chain (§1 C, D, E)

```
ensure_column()    re-throws anything that is not "duplicate column"
        ↓
act_migrate()      latched $doneAt on its FIRST LINE, before any work
        ↓
act_log()          calls act_migrate() inside its try, and swallows everything
        ↓
                   returns 0 — to every caller, in every module, silently
```

Three separate decisions, each defensible alone. Together they mean **one failed
`ALTER` silences the whole application's audit trail for the life of the process**,
and because the guard is already latched it is never retried.

Reproduced with the column genuinely dropped on the running engine:

```
PRE-FIX   act_log returned: 0, 0, 0     audit rows written: 0 of 3
POST-FIX  act_log returned: 1, 2, 3     audit rows written: 3 of 3
```
(a CRM note, a quotation e-mail, a quality nonconformity)

## 4 · The design

```
act_log()
   ├── CORE INSERT ── base-schema columns only ── cannot depend on cond_key
   └── OPTIONAL ───── act_set_cond_key() ── separate UPDATE, own failure path
```

* The core row is written **first**. Optional metadata is applied afterwards, so
  its failure cannot reach back and undo an event already recorded.
* `act_set_cond_key()` **returns whether it actually stored anything**, so a caller
  can tell *stored* from *unavailable* instead of assuming.
* `act_migrate()` latches its guard **only when the core spine is complete**, which
  is what `act_log()` actually needs.
* `act_migrate_optional()` is separate, attempted **after** the guard so it can
  never un-do the core, **bounded** to three attempts per workspace per process so
  a host that refuses DDL does not run an `ALTER` on every audit write, and
  **never latched on failure**.
* A core failure is still non-fatal — a timeline is a record of work, not a
  precondition for it — but it is no longer **invisible**: `act_last_error()`.

## 5 · What this correction does NOT change (§16)

`cond_key` remains correction #7's optional metadata. When the column is absent,
`appr_condition_seen()` returns *not seen*, so the permanent-condition suppression
degrades to correction #6 behaviour — a repeated row rather than a lost audit
trail. **Audit integrity outranks noise suppression**, and that is the deliberate
order.

S2, S3, H1 and H3 are untouched and remain separately tracked.
