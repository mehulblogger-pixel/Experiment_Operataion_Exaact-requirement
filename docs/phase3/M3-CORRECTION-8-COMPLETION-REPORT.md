# Phase 3 · M3 CORRECTION #8 — COMPLETION REPORT

**Scope: S1 only.** S2, S3, H1 and H3 were not touched. No M4 work.

---

## 1 · The defect in one ratio

`act_log()` is the single audit entry point for this application: **76 call sites
across 25 files** — CRM, Money, Quality, Operations, Marketplace, platform and
Recruitment. **Two** of them pass a `cond_key`.

Correction #7 put `cond_key` into that function's INSERT. Seventy-four call sites
gained a precondition for a feature they do not use — and so did the two that do.

Three separate, individually defensible decisions combined:

```
ensure_column()   re-throws anything that is not "duplicate column"
      ↓
act_migrate()     latched its guard on the FIRST LINE, before any work
      ↓
act_log()         calls act_migrate() inside its try, and swallows everything
      ↓
                  returns 0 — to every caller, in every module, silently, for ever
```

## 2 · The fix

```
act_log()
   ├── CORE INSERT ── base-schema columns only ── cannot depend on cond_key
   └── OPTIONAL ───── act_set_cond_key() ── separate UPDATE, own failure path
```

* The core row is written **first**; optional metadata cannot reach back and undo
  an event already recorded.
* `act_set_cond_key()` **returns whether it stored anything**, so *stored* is
  distinguishable from *unavailable*.
* `act_migrate()` latches its guard **only when the core spine is complete**.
* `act_migrate_optional()` runs **after** the guard, is **bounded** to three
  attempts per workspace per process, and is **never latched on failure**.
* A core failure stays non-fatal but is no longer invisible: `act_last_error()`.

## 3 · Evidence

| | |
|---|---|
| **S1 reproduced pre-fix** | 3 modules, **0 of 3** audit rows written |
| **S1 fixed** | same probe, **3 of 3** |
| **New suite `p3m3c8_spine`** | **62 / 0**, both engines |
| **Full regression · SQLite** | **10 087 passed, 0 failed** |
| **Full regression · MariaDB 10.11.14** | **10 088 passed, 0 failed** |
| **Mutations** | **10 attempted, 10 caught, 0 survivors** |

## 4 · §17 acceptance criteria

| | |
|---|---|
| S1 reproduced before fix · S1 fixed behaviourally | ✅ 0 of 3 → 3 of 3 |
| core `act_log()` does not depend on `cond_key` | ✅ `C8.2`; mutation S1-M1 |
| core row written when `cond_key` absent | ✅ `C8.2`, eight callers |
| optional migration failure does not disable audit | ✅ `C8.5`, `C8.6` |
| migration failure observable | ✅ `act_optional_error()`; mutation S1-M4b |
| failed migration not latched as success | ✅ `C8.5` CASE 2; mutations S1-M4, S1-M4b |
| safe retry after failure works | ✅ `C8.5` CASE 3, `C8.6` CASE 3; mutation S1-M3 |
| `cond_key` works normally when available | ✅ `C8.1`, `C8.5 D` |
| CRM · quotation · Recruitment · Operations without `cond_key` | ✅ `C8.2`, `C8.3`; mutations S1-M5…M8 |
| other representative shared callers functional | ✅ invoice, NCR, contract, partner |
| tenant isolation · actor identity · controls preserved | ✅ `C8.4`; full regression |
| false-green tests ruled out | ✅ three of my own found and fixed — see test results |
| S1 mutations executed and honestly reported | ✅ including the first run's three survivors |
| correction #7 behaviour intact | ✅ `p3m3c7` 86 / 0 |
| M1 · M2 · Recruitment · Operations · Marketplace · Money · Quality green | ✅ within the full regression |
| SQLite · MariaDB green | ✅ 10 087 / 10 088 |
| tree clean · completion report produced | ✅ |

## 5 · Deliberate trade-off (§16)

When `cond_key` is unavailable, `appr_condition_seen()` returns *not seen*, so
correction #7's permanent-condition suppression degrades to correction #6
behaviour — **a repeated audit row rather than a lost audit trail**.

**Audit integrity outranks noise suppression.** That order is deliberate and is
what S1 is about.

## 6 · What this correction did NOT do

**S2, S3, H1 and H3 remain open and untouched.**

* **S2** — the condition identity omits the decision result, so a refused
  *rejection* after a refused *approval* is suppressed.
* **S3** — the condition key never expires, so a condition that clears and recurs
  is never recorded a second time.
* **H1** — a direct `appr_act()` call still approves an orphan chain.
* **H3** — `appr_sla_summary()` still counts orphans in its tiles.

None is affected by this change: correction #8 is about whether an audit row can be
written at all, not about which conditions are recorded or what a decision does.

---

**PHASE 3 — M3 CORRECTION #8 COMPLETE — HARD STOP — READY FOR ADVERSARIAL AUDIT**

M3 itself remains **NOT ACCEPTED** until S2, S3, H1 and H3 are resolved.
