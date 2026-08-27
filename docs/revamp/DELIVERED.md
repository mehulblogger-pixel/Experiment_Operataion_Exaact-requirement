# EXAACT → ITOP Revamp — Delivered on `exaact-ops-system-tpia-manpower`

A consolidated index of the revamp work on this branch. Every slice is additive
and non-destructive; the default branch was never touched. Full test suite:
**3886 passing, 0 failing** at time of writing (baseline was 3817; the revamp
added ~69 assertions and fixed two latent bugs).

Read the program charter (`00-program.md`), the audit/architecture
(`01`–`03`), and each slice's Part-25 change-control record under `slices/`.

## Slices delivered

| Slice | What | New/changed | Tests |
|---|---|---|---|
| **P1** Credential Vault | Per-person vault (certs/quals/auths/witness/identity) with a canonical status vocabulary; Entity-360 tab; `verify_status`; customforms requirement register | EXTEND (additive `verify_status`) | `test_credential_vault.php` (27) |
| **P2** Mobilization readiness | One "what's blocking this person from mobilizing?" verdict composing checklist + competence + site-docs + credentials + assets; deputation panel + board column | CONNECT (no new tables/status) | `test_mobilization_readiness.php` (14) |
| **P3** Offline sync-state | Uniform 5-state vocabulary (Saved/Pending/Syncing/Synced/Failed) + "saved on device" chip + failed-send backoff retry | IMPROVE (front-end only) | `js/sync_state.test.mjs` (11) |
| **P4a/b/c** Billable Event ledger | Persisted operational→commercial bridge: `billable_events`, lifecycle, real-time job-close hook, sync (derive+reconcile), Commercial-cockpit board, Customer-360 unbilled figure, timesheet + placement sources, attested billing | BUILD (one additive table) | `test_billable_events.php` (25), `_hook` (10), `_party` (6), `_sources` |
| **P5a** Metric consolidation (R10) | Ops-desk `report_pending` now reads real state (was always 0) | REFACTOR (read-side) | `test_report_pending_metric.php` (3) |
| **P5b** Contract control (R4) | Partner-screen contract door now enters the two-signature PENDING→endorse→approve lifecycle | control-integrity | `test_contract_backdoor_guard.php` (+2) |
| **P6** Product packages | One-click TPIA / Staffing / Recruitment / Enterprise presets over packs + licence bundles | CONFIGURE (no new mechanism) | `test_product_package.php` (44) |
| **P7** Engagement entity (groundwork) | First-class `engagements` (keyed to `contract_number`) + nullable `engagement_id` on calls/jobs/quotes/invoices + backfill + dual-read; string never dropped | BUILD (additive, no status) | `test_engagement_entity.php` (16) |
| **P8** Cost dual-write detector (R9) | Surfaces jobs with reimbursables on both the closure `expenses` and the `voucher` (job_profit sums both); job-detail warning + scan | detector (read-only) | `test_cost_dualwrite.php` (11) |
| **P9** Revenue reconciliation (§29) | Read-only worklist + summary + `/revenue-reconciliation` screen where the legacy invoice snapshot disagrees with the books ledger; drives to green before a §28 reader switch | diagnostic (read-only) | `test_revrecon_worklist.php` (10) |
| **Infra** cron require list | cron.php now derives its lib list from index.php — every nightly task runs again (was all silently dead) | maintenance | verified end-to-end |
| **Infra** module46 isolation | Fixed a latent test leak (bogus `licence_key` left set globally) | test hygiene | suite order-independent |

## Guardrails honoured throughout

- **Additive-only:** every schema change is a new table/column via
  `ensure_column`/`CREATE TABLE IF NOT EXISTS` + a bootstrap probe; no column
  dropped or repurposed.
- **No new permission** without asking: billable reuses `finance.reconcile`;
  product packages are master-gated; nothing new added to
  `docs/02-permission-matrix.md`.
- **New statuses documented in lockstep:** the Billable-event lifecycle (incl. the
  attested-billed transition) is in `docs/03-object-lifecycles.md`.
- **Docs move with code:** `docs/99-gaps-and-risks.md` (R4, R10), the lifecycle
  doc, and a Part-25 record per slice.
- **Tested + regression-clean** before every push; pushed only to the working
  branch.

## Open items (need a decision / dedicated focus — deliberately NOT rushed)

| Item | Why deferred | Plan |
|---|---|---|
| **R9 — cost dual-write** | Job-close expenses and the inspector voucher both write cost; converging touches the profit engine | Introduce a canonical read (prefer `job_profit()` snapshot everywhere) with a `*_disagrees()` detector first; migrate readers only after parity is shown. A focused slice. |
| **§29/§80 — financial dual-truth** | **Worklist delivered (P9)** — read-only `revrecon_summary()`/`revrecon_list()` + `/revenue-reconciliation` screen + Money tile; drives divergences toward green. | Remaining (§28, sign-off): once green, switch the legacy revenue readers (`mis`, contract-360, `crm` order, money dashboard) onto the ledger one at a time — *changes displayed figures*, so deliberate + validated per reader. |
| **Engagement entity** | ~~deferred~~ **Groundwork delivered (P7)** — additive `engagements` + `engagement_id` + backfill + dual-read; string never dropped. | Remaining (future): stamp `engagement_id` on write; prefer the id in 360/rollup reads; engagement-level attributes. Not required now. |

These three are financial-truth / structural changes where haste is the enemy;
each deserves its own validated slice rather than a tail-end addition.
