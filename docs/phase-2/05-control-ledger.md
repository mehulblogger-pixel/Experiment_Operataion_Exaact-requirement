# Phase 2 Hardening Ledger

Every control delivered in the Phase 2 pass on the Exaact TPIA platform, set out one at a time:
**what it affects · why it was required · what it adds · how it eases the work** (and who it helps).

- **3,584 tests passing, 0 failed** · **26 controls shipped** · all changes non-destructive (nothing removed).
- §28 unified financial truth is **live** (shipped default, reversible).
- Full commit trail + test counts: `docs/phase-2/00-program.md`. Verify with `docs/phase-2/04-verification-runbook.md`.

| Band | Count | In short |
|---|---|---|
| Integrity foundations (P0) | 3 | the figures and the seal you can now trust |
| Security & workflow (P1) | 8 | leaks and gaps closed |
| Consolidation & finance (P2) | 12 | one honest way to see each thing |
| Assurance | 3 | the model, the catalogue, the proof |

---

## Integrity foundations — P0

### §30 · Reproducible historical profit  `test_p2_cost_reproducibility`
- **Affects:** every profit and margin shown for a closed job, contract, the MIS dashboard and the SBU P&L.
- **Why required:** a past job's profit silently changed whenever today's salary, office overhead % or the working calendar changed — last quarter's number wouldn't reproduce.
- **Adds:** a closed job *freezes* the rate basis it was costed on; the engine reads that snapshot, with a nightly backfill for older jobs.
- **Eases:** *Finance & management* — a historical report shows the same figure every time, so audits, year-on-year comparisons and disputes rest on stable ground.

### §10 · Issuance readiness gate  `test_p2_issue_gate`
- **Affects:** the moment a report is issued.
- **Why required:** a report could go out while vetting, competence, impartiality, a blocking NCR or client acceptance were still outstanding.
- **Adds:** a readiness verdict listing every blocker before issue — vetting a hard stop when the lab enabled it, the rest advisory unless `issue_gate_strict`.
- **Eases:** *The issuer* sees exactly what's missing before a certificate leaves the building, instead of after — protecting the accreditation.

### §11 · Seal that fails closed  `test_p2_seal_failclosed`
- **Affects:** the tamper-evident content seal on an issued report.
- **Why required:** a seal that failed to write could still read back as "sealed" — a silent loss of integrity.
- **Adds:** a `SEAL_FAILED` marker, a self-healing re-seal job, and a compliance flag; a failed seal never reads as OK.
- **Eases:** *The compliance officer* is told when something needs re-sealing rather than trusting a false green.

---

## Security hardening — P1

### §51 · Single-record access scope  `test_p2_idor_scope`
- **Affects:** opening a job, document, report PDF, invoice, endorsement file or check-in photo directly by its id.
- **Why required:** office/branch scope was enforced on *lists* but dropped on direct id access — a user could open another branch's record by guessing a number.
- **Adds:** a fail-closed single-record check (`scope_allows()`) mirroring the list scope exactly; masters exempt.
- **Eases:** *Every branch* — data stays inside the branch that owns it, with no accidental cross-office disclosure.

### §53 · Identity documents encrypted at rest  `test_p2_identity_encryption`
- **Affects:** stored government-ID numbers (DPDP).
- **Why required:** ID numbers were masked on screen and logged, but sat in the database as plain text.
- **Adds:** AES-256-GCM encryption with an environment key, self-describing ciphertext, coexistence with legacy rows, and a nightly backfill.
- **Eases:** *The organisation* — a database leak no longer exposes ID numbers, and data-protection expectations are met without changing anyone's workflow.

### §54 · Audit chain protected both ways  `test_p2_audit_protection`
- **Affects:** the sealed, tamper-evident audit log.
- **Why required:** legitimate retention-trimming looked identical to tampering, and a wholesale wipe left no trace.
- **Adds:** a trim anchor so a real purge verifies clean while genuine tampering is still caught; a wipe writes durable evidence and needs a distinct `ERASE AUDIT` phrase.
- **Eases:** *Compliance* can do honest housekeeping without false alarms, and no one can quietly erase the trail.

### §22 · Search respects branch scope  `test_p2_search_scope`
- **Affects:** global search across inquiries and contracts.
- **Why required:** the search box could surface rows from outside the viewer's SBU/branch scope.
- **Adds:** the same scope every list uses, now applied to the search query itself.
- **Eases:** *Every user* — search stays inside the same boundaries as the rest of the app.

---

## Report & workflow trust — P1

### §4 · All four sign-off roles on the PDF  `test_p2_report_roles`
- **Affects:** the issued report PDF a client or accreditor receives.
- **Why required:** the PDF printed only 2 of the 4 roles — *vetted by* and *issued by* were invisible on paper.
- **Adds:** all four — Prepared / Vetted / Approved / Issued — captured and printed, each shown only where that role actually applied.
- **Eases:** *Clients & assessors* see the full chain of responsibility the standard expects, with nothing to ask back for.

### §9 · Structured return-to-inspector  `test_p2_return_detail`
- **Affects:** sending a report back to the inspector for correction.
- **Why required:** a return was free text — the inspector wasn't told which section or field, or by when.
- **Adds:** a structured section/field reference plus a deadline, captured on both return paths.
- **Eases:** *The inspector* (phone-first, in the field) knows exactly what to fix and by when — fewer blind round-trips.

### §6 · Applicability overrides on the record  `test_p2_applicability_override`
- **Affects:** which report types are marked applicable to a job.
- **Why required:** forcing a report type on or off wasn't recorded — no reason, no trace.
- **Adds:** an override stores a reason, an audit entry, and a `not_allocated` flag.
- **Eases:** *Reviewers* can see why a report type was forced, with accountability, instead of guessing at an anomaly.

### §46 · Call statuses can't silently disagree  `test_p2_status_agree`
- **Affects:** a call's legacy `status` versus its canonical `op_status`.
- **Why required:** the two could drift apart — one saying CLOSED, the other OPEN — with nothing flagging it.
- **Adds:** a disagreement detector surfaced as a data-integrity (§7.11) check on the health board.
- **Eases:** *Coordinators & data control* — bad records surface for repair instead of quietly feeding wrong reports downstream.

---

## Consolidation — P2 (read-only views over what already exists; no new tables, nothing removed)

### §23/24 · One person, seen whole  `test_p2_party`
- **Affects:** the six identity stores — users, inspectors, candidates, contacts, client and vendor logins.
- **Why required:** one human showed up as separate, unlinked records across those stores.
- **Adds:** a mapping layer that resolves one person by reference → mobile → email, and an "also appears as" panel — no merge, no data touched.
- **Eases:** *Recruiters & account managers* — you see the whole of a person (a candidate who is also a client contact) without hunting module by module.

### §39 · The full quality-case story  `test_p2_quality_case`
- **Affects:** the NCR, CAPA and Complaint modules.
- **Why required:** the full story of one quality issue was spread across three separate modules.
- **Adds:** a read-only umbrella linking complaint → NCR → CAPA with the corrective-action outcome (root cause, effectiveness, closure).
- **Eases:** *Quality assessors* see the whole case on one panel — finding, action, and whether it actually worked.

### §25 · The whole engagement at a glance  `test_p2_engagement`
- **Affects:** the contract → call → job → report → invoice spine.
- **Why required:** there was no single view of everything happening under one contract.
- **Adds:** a read-only rollup grouped on the contract number, returning the whole spine plus totals.
- **Eases:** *Coordinators & managers* — the whole engagement reads at once instead of being reassembled from five screens.

### §29 · Revenue reconciliation  `test_p2_rev_recon`
- **Affects:** the legacy per-job invoice figure versus the real books ledger.
- **Why required:** the two could diverge with no signal — and the older figure still fed dashboards.
- **Adds:** a read-only count of jobs whose invoice figure disagrees with the ledger, on the health board and data-control.
- **Eases:** *Finance* sees exactly where a figure can't be trusted yet — and it became the evidence base for the §28 decision.

### §48 · Preview before a bulk action  `test_p2_bulk_preview`
- **Affects:** bulk actions (leads are the reference adopter).
- **Why required:** a bulk action ran without first showing what it would and wouldn't touch.
- **Adds:** a dry-run that partitions the selection into will-apply / will-skip (with the reason) using the *same* rule the executor uses.
- **Eases:** *Anyone running a batch* sees the effect before committing — no surprise mass-changes to undo.

### §72 · One vocabulary for what's visible  `test_p2_visibility`
- **Affects:** which evidence and fields are client-visible versus internal-only.
- **Why required:** there was no single vocabulary for visibility — a risk of internal notes reaching the client portal.
- **Adds:** one visibility vocabulary plus a single-record gate that enforces it.
- **Eases:** *The portal* shows only what's meant for the client; internal working notes stay internal, by rule not by luck.

### §68 · Reused evidence, detected  `test_p2_evidence_reuse`
- **Affects:** photographic evidence attached to reports.
- **Why required:** the same photograph could be reused across different jobs undetected.
- **Adds:** detection of a shared image fingerprint (hash) across reports.
- **Eases:** *Quality & vetting* get an integrity signal against copied evidence, before it reaches a certificate.

### §32 · Inter-office settlement, in one place  `test_p2_settlement`
- **Affects:** cross-office jobs — one branch holds the contract, another does the work.
- **Why required:** the inter-office credit picture wasn't visible in any single place.
- **Adds:** a read-only settlement matrix that balances across branches.
- **Eases:** *Finance* sees who owes whom across branches at a glance, instead of reconstructing it.

### §47 · Every setting, explained  `test_p2_setting_meta`
- **Affects:** the behavioural settings an admin can toggle.
- **Why required:** settings that change how the app behaves weren't documented with their impact.
- **Adds:** a governance registry: for each setting, what it affects, whether it applies going-forward or live, and its impact level.
- **Eases:** *Admins* understand what a toggle does before flipping it — far fewer accidental behaviour changes.

### §17 · Nothing missing from the history  `test_p2_timeline_entities`
- **Affects:** the activity timeline shown per entity.
- **Why required:** some entities (candidate, receipt) were being logged but not registered on the timeline, so their history looked empty.
- **Adds:** those entities registered on the one activity spine, so their timeline renders.
- **Eases:** *Anyone reading a record's history* gets the complete trail — nothing silently absent.

---

## One financial truth (sign-off given)

### §33 · Invoice readiness  `test_p2_invoice_readiness`
- **Affects:** raising an invoice against a job.
- **Why required:** a job could be billed before its reports were issued, before a release was accepted, or beyond the contract value.
- **Adds:** a READY / NOT-READY verdict on the job's Money tab — advisory by default, a hard block under `invoice_gate_strict`.
- **Eases:** *Finance* stops billing prematurely — fewer credit notes, fewer client disputes, cleaner receivables.

### §28 · Unified financial truth  `test_p2_finance_truth` · `test_p2_basis_reconcile`
- **Affects:** the MIS dashboard, the SBU-P&L contract table, and the owner/boss view.
- **Why required:** the same job showed *different* profit on different screens — the dashboards used a partial formula that dropped overhead, vouchers, contingency and recovered cost, overstating profit by ~₹74k on the demo set (92 of 104 jobs disagreeing).
- **Adds:** all three now read the one canonical engine, so a job shows the same, true profit everywhere. Shipped on by default; fully reversible with `finance_truth_unified='0'`; a side-by-side panel reconciles job-costing against period-costing.
- **Eases:** *Management* decides on one trustworthy profit figure — no reconciling three dashboards, no wondering which screen is right.

---

## Assurance — model, catalogue, proof

### §79/80 · The canonical application model
- **Affects:** how every future change is built.
- **Why required:** without one named model, new work keeps re-forking the same concept a fourth way.
- **Adds:** a document (`02-canonical-application-model.md`) naming the one canonical concept and its owning code for each dimension, plus a legacy-compatibility register for what's retained and why.
- **Eases:** *Every future developer* reads through the canonical engine instead of adding another table, calculation or status.

### Catalogue · Edge-case catalogue for the new controls
- **Affects:** the module-by-module QA catalogue.
- **Why required:** the existing catalogue predated every Phase-2 control, so the new behaviour had no documented edge cases.
- **Adds:** an edge-case matrix (`docs/edge-cases/51-phase-2-controls.md`) for each new control, every row tied to the automated test that proves it.
- **Eases:** *QA & auditors* can read the expected behaviour and see its proof, control by control.

### Runbook · Verification runbook & one-control check
- **Affects:** how anyone confirms the work is real.
- **Why required:** there was no single guide to prove the controls, and the test runner couldn't check just one of them.
- **Adds:** a four-layer runbook (`04-verification-runbook.md`) — full suite → one control → self-reporting screens → manual UI — and a filter so `php tests/run.php <name>` runs a single control.
- **Eases:** *Anyone* can prove the whole app in one command, or one control in one command — trust on demand.

---

## Held back on purpose (documented, not dropped)

Phase 2 carried a firm "no feature creep" rule. These are genuine new builds, each scheduled as its own future change rather than folded quietly into a hardening pass.

| § | Item | Why deferred |
|---|---|---|
| §19/20 | Action & Command centres | New cross-module dashboards — the existing "waiting on me" / "needs attention" aggregators cover the immediate need. |
| §26 | Persisted task store | A real task table; today's read-time task lists serve the day-to-day without one. |
| §27 | Financial-event stream | A canonical money-event log — the read-only §29 reconciliation covers the pressing case. |
| §50 | Generic integration layer | A unified webhook/queue to replace the per-integration outboxes, which all still work today. |
| §8/34/35/16/49 | Larger UX & lifecycle builds | Builder persona previews, dashboard expansion, training attendance, vendor-360 depth, entity-360 — each a change in its own right. |

---

*Exaact TPIA OS · Phase 2 — Consolidation, Hardening & Enterprise Readiness. All changes non-destructive; full detail in `docs/phase-2/`.*
