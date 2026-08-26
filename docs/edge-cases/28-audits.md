# Module 28 — Internal Audits (§8.8) + Management Review (§8.9) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: mature subsystem, three real holes

The audits/MR subsystem (`lib/audits.php`) is complete: a clause **coverage board**
(`audit_coverage`), auto-gathered §8.9.2 **management-review inputs** (`mr_measure`/
`mr_refresh_measures`), the §8.8.2 **auditor-independence** refusal, a finding→CAPA loop, and
close/complete gates. Three genuine gaps:

1. **No overdue reminder.** Every sibling accreditation register (NCR, CAPA, complaints, calibration,
   vendor re-qualification) is chased by cron; audits and management review were the only ones with a
   `*_readiness()` function but no reminder — the board flagged overdue, nobody was told.
2. **Management-review decisions never reached the CAPA register.** `mr_actions.capa_ref` existed but
   was dead in the live path — asymmetric with the fully-wired finding→CAPA loop.
3. **Zero tests** over the safety gates (independence refusal, close-block, completeness).

---

## 1. Built (additive, read-mostly, no hard control)

1. `audits_run_reminders()` + `reviews_run_reminders()` — read **only** the already-computed
   `audits_readiness()` / `reviews_readiness()` and e-mail the audit/quality owner (QAC_EMAIL or
   coordinators) about uncovered clauses, unactioned findings, an overdue review, or open decisions.
   Wired into `cron.php`, **guarded at most weekly** (a year-long cycle needs no daily nag). They
   notify; they change no record.
2. **MR-decision → CAPA link** — a `review-action-capa` route + "🛠 Raise CAPA" button that raises a
   corrective action from a management-review decision (`source='MGMT_REVIEW'`), writing `capa_ref`/
   `capa_id` back (new additive `mr_actions.capa_id` column, mirroring `audit_findings.capa_id`). The
   linked CAPA now renders on the review. Closes the asymmetry with the §8.8 finding→CAPA loop.
3. **Per-clause days-since + next-due** — additive keys on `audit_coverage()`, so the board and the
   reminder can name a specific clause and when its next audit is due.
4. The **first tests** over the subsystem: the reminders, the independence refusal, the close-block,
   and the coverage enrichment.

---

## 2. Edge cases handled

1. The cron reminder is **weekly-guarded** (`audit_reminder_week` = ISO year-week); a failed run never
   stops the nightly job.
2. A reminder fires **only** when something is actually overdue and a recipient exists; otherwise 0.
3. The MR→CAPA route refuses a decision that already has a CAPA (idempotent), and is a no-op if the
   CAPA module isn't present.
4. `capa_id` is added via `ensure_column` (additive; older installs gain it silently).
5. The coverage enrichment adds keys, never removes `label`/`last`/`covered` — the existing board
   keeps working.
6. Independence (§8.8.2): auditor == area owner is refused, both on plan and on record (unchanged);
   now tested.

## 3. Guardrails (green)

The coverage board, the MR auto-gather, the independence gate, the close/complete gates, and every
route are unchanged. No new permission (reuses `mod.audits.*`); no schema change beyond one additive
nullable column; no access or data changed; nothing deleted.

## 4. Tests

`tests/test_module28_audits.php` (15 assertions): the reminders fire when overdue; coverage carries
days-since/next-due; the §8.8.2 independence refusal; the close-block on an unactioned NC; the
MR-decision CAPA link column; the cron wiring is weekly-guarded; the MR→CAPA handler raises with a
management-review source.
