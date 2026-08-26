# Module 29 — Data Control / Governance (ISO 17020 §7.11) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built. (Module 02
already added the access-audit / effective-access / toxic-combo work here; not re-done.)

---

## 0. Headline: the evidence machinery exists but is starved of runs

The Data Control console (`lib/datacontrol.php`, `/data-control`) is a real §7.11 governance surface:
a software-validation register, a **data-integrity check registry** (`integrity_checks()`, ~16
referential/consistency/trail checks), an effective-access matrix (Module 02), and an actionable
failure log. The integrity checks write a dated pass/fail row to `data_check_runs` via
`integrity_run()` — but **nothing fires that on a schedule**. The checks render on every page view
and record **only** when a human presses "Run them now", so the history table is starved and
`run_stale` is permanently red — exactly the "records only the failures somebody was in the mood to
admit to" anti-pattern the file itself warns against (and the same reason `audit_trim_old()` was
moved to nightly cron). Two additional gaps: the **money tables** (voucher lines, invoice lines) had
no orphan detector, and the sealed **audit chain** is verified inside the console's `audit_chain`
check and public `/verify` but **not surfaced on `/audit-log`** where the trail is actually read.

---

## 1. Built (recommended additive, evidence-only — no access/data change)

1. **Scheduled integrity self-test** — a per-day-guarded `cron.php` block calling the existing
   `integrity_run()` (mirroring the blessed nightly `audit_trim_old()` guard). It writes **one dated
   pass/fail evidence row** to `data_check_runs` per day — nothing else. The console's "Runs on file"
   history, the stale banner, and the §7.11 compliance line now have real data instead of only
   button-press rows.
2. **Two money orphan detectors** added to `integrity_checks()`: `ventry_voucher` (voucher line → real
   voucher) and `invline_invoice` (invoice line → real invoice) — COUNT-only, skipped-safe if a table
   is absent, exactly like the existing referential checks.
3. **Audit-chain-intact banner** on `/audit-log` — surfaces `idems_audit_verify()` (already used by
   the console's `audit_chain` check) where the trail is read: "chain intact" (all N sealed entries
   verify) or "chain broken" (N failed, first at #id — investigate).
4. The **first tests** over the integrity registry / `integrity_run()` / the new detectors.

---

## 2. Edge cases handled

1. The cron runner is **guarded once per day** (`integrity_run_day` setting) so running cron.php more
   often costs one comparison; a failed run never stops the rest of the nightly job (try/catch).
2. `integrity_run()` in cron has no logged-in user → `ran_by` is empty/system; harmless.
3. A missing table makes a check **skipped**, not failed (existing `$add` try/catch) — the new
   detectors inherit this, so an install without vouchers/invoices doesn't false-fail.
4. The orphan detectors compare against real ids only (`voucher_id NOT IN (SELECT id FROM vouchers)`);
   a NULL foreign key is not an orphan.
5. The chain banner is hidden when there are no sealed rows (`skipped`), so a fresh install shows
   nothing rather than a false "intact".
6. The banner reads the same verifier the tests cover (`test_audit_chain.php`), so its verdict can't
   drift from the console's `audit_chain` check.

## 3. Guardrails (green)

The check registry, `integrity_run()`/`data_check_runs`, the failure log, the access matrix, the
retention/DSAR surfaces, and every gate are unchanged. No new permission; no schema change (reuses
`data_check_runs` + a `setting`); no access or data changed; nothing deleted. `test_audit_chain`,
`test_module02_access`, `test_retention` untouched.

## 4. Tests

`tests/test_module29_datacontrol.php` (14 assertions): the two new orphan detectors are in the
registry and fire on a real orphan; `integrity_run()` records exactly one evidence row with the right
summary; the daily cron runner is wired and per-day guarded; the audit-log console shows the
sealed-chain signal.
