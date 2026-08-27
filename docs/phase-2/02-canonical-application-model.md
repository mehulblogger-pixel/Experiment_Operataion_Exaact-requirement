# Exaact TPIA OS — Canonical Application Model (§79) + Legacy Compatibility (§80)

**Status:** the authoritative model every future change must follow. It records the *one* canonical
concept for each dimension, the code that owns it, and — per the non-destructive rule — the legacy
concepts that are **retained** with their migration/deprecation path. Nothing here is a table merge;
convergence happens through canonical engines, mapping layers and read views.

Rule of thumb for new development: **read through the canonical engine/helper named below. Do not add
a second table, a second calculation, or a second status system.**

---

## 1. Canonical entities

| Canonical concept | Owner / resolver | Backing tables (all retained) | Notes |
|---|---|---|---|
| **Party / Person** | `party_key()`, `party_records_for()`, `party_key_of()` (`lib/party.php`, §23/24) | `users`, `inspectors`, `candidates`, `partner_contacts`, `client_users`, `vendor_users` | One human resolved across stores by ref→mobile→email. **Mapping layer, never a merge.** New code reads a person via `party_*`. |
| **Organisation** | `business_partners` + `dedupe.php` | `business_partners` (client/vendor flags) | One org record; `partner_contacts` are its people. |
| **Engagement → Contract → Call → Job → Visit → Report** | `engagement()` (`lib/engagement.php`, §25) over the spine | `partner_contracts`/`quotations`, `calls`, `jobs`, `site_visits`, `report_docs`, `invoices` | Contract linkage is the string `contract_number` (retained). `engagement($contractNumber)` is the **read-only grouping** that returns the whole spine (quotes→calls→jobs→reports→invoices) + rollup. **View over `contract_number`, not a new table.** Do not add an engagement module. |
| **Report** | `lib/idems.php` (URFE) | `report_docs` + `report_files`/`report_fields`/… | The Universal Report Foundation Engine is authoritative; every report type reuses it. No per-type mini-engines. |
| **Evidence** | `report_files` + trust chain (`lib/trust.php`) | `report_files`, `site_visits` | First-class object with hash chain; served only through access-checked handlers. |
| **Equipment** | `lib/equipment.php` | `equipment`, `equipment_calibrations` | Calibration impact is *review*, never auto-invalidation. |
| **Quality Case** (view) | `quality_case()` (`lib/qualitycase.php`, §39) | `nonconformities`, `capa`, `complaints`, audits, risks | A read-only umbrella over the existing modules via their FKs. Not a new table. |
| **Financial event** | `financial_events()` / `financial_rollup()` (`lib/finevent.php`, §27) | `quotations`, `invoices`, `receipts`, `credit_notes` | A **read-only projection** over the existing money records into one uniform, time-ordered stream (accepted quote → invoice → receipt → credit) + rollup. Reads the books ledger, so it cannot drift. **Not a new event table** — dashboards read `financial_events`, they do not re-shape the money tables themselves. |

## 2. Canonical statuses

| Lifecycle | Canonical status source | Legacy retained | Disagreement handling |
|---|---|---|---|
| **Call** | `tosrm_call_status()` = `op_status` else derived from legacy (`lib/tosrm.php`) | `calls.status` | **§46 — `call_status_disagrees()` flags a terminality mismatch** as a §7.11 integrity check (Module 29). |
| **Job** | `closed_flag` (+ `invoice_raised`, approvals); `job_now()` header (`lib/ops.php`) | `jobs.stage` (vestigial, explicitly labelled) | `stage` is display-only history; the real state is `closed_flag`. |
| **Report** | `report_docs.status` (DRAFT→SUBMITTED→VETTING→UNDER_REVIEW→APPROVED→ISSUED→REJECTED) + `vet_status` | — | Vetting is optional/configurable (`vetting_gate_required`). |
| **Contract** | `partner_contracts.open_status` (PENDING→OPEN→CLOSED/REJECTED) + `contract_state()` live verdict | — | `contract_classify()` is the single expiry/quantity verdict for gate + 360. |
| **NCR / CAPA / Complaint** | each module's own `status` | — | Linked by the Quality Case view (§39). |

**Rule:** never let two statuses silently disagree. If a new dual-status appears, add a
`*_disagrees()` detector to the Module 29 integrity engine (the §46 pattern).

## 3. Canonical workflows

- **Inspection → issue:** assignment → **competence gate** (`inspector_eligibility`/`competence_block`, server-side) → **impartiality gate** (`imp_block`) → schedule → inspect → evidence → **applicable report(s)** (`idems_job_applicability`; overrides audited as `APPLICABILITY_OVERRIDE`, §6) → **submit** → (optional) **vetting** → **return-to-inspector** (structured: section/field/deadline, §9) → **approval chain** → **issuance readiness gate** (`idems_issue_readiness`, §10) → **issue** (sealed, immutable, `content_seal`; fail-closed §11) → deliver → invoice → pay → archive.
- **Roles on every report:** Prepared / Vetted / Approved / Issued — all four captured and **printed on the PDF** (§4).
- **Corrective action:** finding/NCR/complaint/audit → CAPA (RCA → action → effectiveness → closure), viewed as one Quality Case (§39).

## 4. Canonical financial calculation

- **The one engine:** `job_profit($job, $officeId)` (`lib/ops.php`) — per-job revenue/labour/overhead/
  expenses/voucher/subcon/other/recovered/contingency/cost/profit/margin + cross-office split.
- **Reproducibility (§30):** a closed job freezes its rate basis (`cost_daily_base`/`cost_oh_pct`/
  `cost_contingency_pct`/`cost_basis_at`); `job_profit` prefers the snapshot, so historical profit does
  **not** drift. `job_cost_snapshot()` at close + `jobs_backfill_cost_basis()` nightly.
- **One truth across every screen (§28 — DONE):** MIS, the SBU-PL contract table and the owner/boss view
  all read `job_profit` — `finance_truth_unified` is the shipped **default (ON)** (sign-off 2026-08-26),
  so a job shows the same profit everywhere. Revert to the legacy partial dashboards with
  `finance_truth_unified='0'`. `profit_reconciliation()` (§32/Module 32) still measures the canonical-vs-
  partial gap for the SBU-PL basis reconciliation (§28 Option 3) and reports "unified" on `/system-status`.
- **Overhead:** period-locked pool (`cost_runs`/`office_expenses`) + `overhead_recovery()`; the per-job
  rate is now frozen at close (§30).

## 5. Canonical evidence

- First-class `report_files` with EXIF time/GPS, SHA1 dedup, and a tamper-evident chain (`lib/trust.php`).
- Served only via access-checked, office/SBU-scoped handlers (`idems_file_authorized`; §51 closed the
  fetch-by-id gaps). Redacted evidence is flagged, never silently removed.

## 6. Canonical tasks

- **Derived "waiting on me":** `ops_pending_tasks()` (personal approvals/actions). **Business "needs
  attention":** `attention_summary()` (leads/inquiries due, expiries, AR, certs, interviews, integrations).
  Both are read-time aggregators over the owning modules — a new *derived* task-like signal feeds one of
  these, not a new store.
- **Human-authored tasks (§26 — Phase 3):** `lib/tasks.php` / `user_tasks` — the persisted, assignable,
  due-dated items a person writes down and ticks off (which the aggregators cannot represent). Create via
  `task_create()`, read via `task_mine()` / `task_for_entity()`; assigning to others is coordinator-level +
  branch scope. It feeds **one** derived count back into `ops_pending_tasks`; it does not replace the
  aggregators. New task UIs read `task_*`; do not add a second task store.

## 6a. Canonical bulk actions

- Bulk actions run CONFIRM → **PREVIEW** → EXECUTE → AUDIT. The preview is `bulk_plan($ids, $classify)`
  (`lib/bulk.php`, §48) — a pure partition into will-apply / will-skip(reason) — computed from the **same
  eligibility rule the executor uses**, so the two can never disagree. Leads are the reference adopter
  (`leads_bulk_eligible`/`leads_bulk_plan`/`leads_bulk`). **Rule:** a new bulk action extracts one
  `*_eligible()` used by both its `*_plan()` preview and its executor; never write the skip rule twice.

## 7. Canonical permissions

- One catalogue: `all_permissions()` / `role_defaults()` (`lib/access.php`); enforced through `can()`
  (which also applies licence module-gating). Scope via `scope_clause()` (lists) and **`scope_allows()`**
  (single-record, §51). Never grant a permission not in the matrix; never widen access without updating
  `docs/02-permission-matrix.md` in the same commit.
- Audit is the sealed `idems_audit` chain (`idems_log`), tamper-evident and now trim-anchored (§54).

## 8. Canonical terminology

- `T()/TP()/Tl()/Tlp()` over `TERM_PACKS` + overrides (`lib/terms.php`). Internal acronyms
  (URFE/IDEMS/PDSO/TAPI) are route/capability keys only — **never** user-facing labels.

## 7a. Canonical activity timeline

- One spine: `act_log($entityKind,$id,$kind,$subject)` writes, `act_for_entity()` / `act_render_timeline()`
  read. Entities are registered in `ACT_ENTITIES` (label + route), including CANDIDATE / RECEIPT / CONTRACT
  (§17). **Rule:** record an event with `act_log` against a registered entity and show it with
  `act_render_timeline()`; do not build a module-private history renderer. New entity → add it to
  `ACT_ENTITIES`, don't invent a parallel log.

## 8a. Canonical visibility

- One vocabulary — `VIS_CLASSES` (PUBLIC / SHARED / CLIENT / VENDOR / INTERNAL / CONFIDENTIAL) and
  `VIS_AUDIENCES` (INTERNAL / CLIENT / VENDOR / PUBLIC) in `lib/visibility.php` (§72). `visibility_can_see($class,$audience)`
  is the single **single-record** gate (the scalar twin of `cvp_visibility_sql`), fail-closed by default; it
  **delegates** to `cvp_can_see()` for every code cvp owns so the two can never diverge. `visibility_class_of($kind,$row)`
  reads a record's existing flag (`report_docs.vendor_visible`, `nonconformities.visibility`, `*_client_visible`)
  and returns its canonical class. Per-record flags are retained; this is a reading layer over them, not a merge.
- **Rule:** classify with `visibility_normalize()`/`visibility_class_of()` and gate a single record with
  `visibility_can_see()`; keep filtering lists with `cvp_visibility_sql()`. Do not invent a new per-record flag.

## 9. Canonical observability

- **Business:** `attention_summary()` band + role dashboards. **Platform health:** `/system-status`
  (`system_status()`, Module 50) — audit chain, integrity, compliance, licence, integrations, email,
  profit consistency. **Notifications:** `/notifications` over `email_log`. Keep business KPIs and
  technical health separate (§20/§21).

---

## §80 — Legacy compatibility register

For each retained legacy concept: why kept, where it lives, its canonical replacement, and status.

| Legacy concept | Why retained | Current tables / routes | Canonical replacement | Deprecation status |
|---|---|---|---|---|
| Multiple identity tables | Historical; each has live records + routes | `users`/`inspectors`/`candidates`/`partner_contacts`/`client_users`/`vendor_users` | `party.php` mapping layer (§23/24) | **Active-compat.** New code resolves via `party_*`; tables not merged. |
| `calls.status` (legacy) | Old records + reports read it | `calls.status` | `op_status` via `tosrm_call_status()` | **Active-compat + monitored** (§46 disagreement check). |
| `jobs.stage` (vestigial) | Old data; some views show history | `jobs.stage` | `closed_flag` + `job_now()` | **Deprecated** (display-only; not the lifecycle). |
| `jobs.invoice_amount`/`payment_received` (legacy invoice) | `boss_profit`/MIS/dashboard read them | `jobs.*` | books ledger (`invoices`/`receipts`) | **Dual-truth (flagged §29).** Reconcile before switching readers. |
| Plaintext `person_documents.doc_number` | Rows filed before encryption | `person_documents.doc_number` | `doc_number_enc` (§53) | **Migrating** (nightly `iddoc_encrypt_backfill`, key-gated). |
| Per-integration bespoke outboxes | Each integration works | `ads_outbox`/`books_outbox`/… | generic queue `integration_outbox` (`lib/webhookq.php`, §50) | **Active-compat.** New integrations use `webhookq_enqueue`/`webhookq_dispatch` (dedupe + retry/backoff, injectable delivery); the bespoke outboxes are retained and both report into `integration_health()`. |
| Contract linkage by `contract_number` string | Whole spine matches on it | `calls`/`jobs`/`quotations`/`partner_contracts` | canonical Engagement (§25) | **Deferred (P2/P3).** Use the string today. |

**Deferred canonical work (do NOT build ad-hoc; schedule as its own change):**
§26 persisted Task entity, §27 Financial-event stream, §28 profit-engine convergence (needs sign-off —
changes displayed numbers), §50 generic integration layer. *(§25 engagement view, §68 anomaly-flag
surface and §72 visibility gate are now delivered as read-only layers — see above.)*

---

*Every future developer must follow this model. When a requirement seems to need a new entity, status,
calculation or workflow, first check this document — the canonical one almost always already exists.*
