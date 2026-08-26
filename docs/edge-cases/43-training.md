# Module 43 — Training · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: no training register — competence is the spine, and only counts are shown

There is **no dedicated training-attendance register**. Staff competence lives in `lib/competence.php`
— `inspector_certs` (qualifications held, with `valid_to`), `authorisations` (what the body permits),
`witness_assessments` (§6.1.8 monitoring). Certs already have an expiry cron reminder
(`ops_run_cert_reminders`) and a per-inspector **matrix** (`competence_matrix`). The nav labels the
screen "Training, assessment and authorisation", but the matrix shows only **counts** (N lapsed, N
due-soon per person) — there is no **actionable cross-inspector drill-down** of *which person + which
certificate* needs a refresh. The genuinely missing, non-entangled piece.

**Noted, deferred:** a true training-attendance/course register, the dormant `qualifications` library
+ `renewal_months`, and an induction record are larger write-heavy additions (PENDING A1/A3) — left
for a deliberate build.

---

## 1. Built (additive, read-only, no access change)

1. `competence_training_watch($withinDays=45)` — a flat, ranked worklist across every **active**
   inspector of each certificate/ticket that has lapsed or comes up for refresh within the window,
   naming the exact inspector, ticket, number, mandatory flag, valid-to and state. Reuses
   `inspector_certs`; read-only.
2. `competence_training_watch_counts()` — lapsed / expiring / total split.
3. A **"Training & certification watch"** panel on the `/competence` screen (lapsed-first), linking
   each row to that inspector's competence record.
4. The **first tests** of this surface (competence had only the Module 24 eligibility test).

---

## 2. Edge cases handled

1. A lapsed mandatory cert and a soon-to-expire cert are both listed; a far-future cert is not.
2. An **inactive** engineer's lapsed cert is not chased (only `status='ACTIVE'`).
3. Lapsed sorts before expiring, and lapsed reports a negative days-to-expiry (e.g. "lapsed 10d ago").
4. A cert with a blank `valid_to` (open-ended) is never on the watch.
5. Pre-migration (no `inspector_certs`) → empty list, no crash (try/catch).
6. Read-only — it never writes; certs already remind on their own expiry, so this adds no reminder,
   only the worklist.

## 3. Guardrails (green)

`competence_matrix`, `competence_lapsed`/`competence_block`, the cert reminder cron, the
authorisation/witness engine, and the eligibility verdict (Module 24) — all unchanged. No new
permission (reuses the `/competence` manager gate); no schema change; nothing deleted.

## 4. Tests

`tests/test_module43_training.php` (16 assertions): lapsed + expiring certs are on the watch; a
far-future cert and an inactive engineer's cert are not; lapsed sorts first with negative days; the
watch names the exact inspector; the counts split correctly; the panel is wired; the matrix is
unchanged.
