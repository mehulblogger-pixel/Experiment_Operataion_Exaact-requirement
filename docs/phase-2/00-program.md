# Exaact TPIA OS — Phase 2 Program (Consolidation · Hardening · Validation)

**Character:** verify → fix → consolidate → validate. **Not** a new feature cycle.
**Governing rule:** the absolute non-destructive rule stays in force (nothing deleted; establish
canonical concepts, add compatibility/migration, converge gradually, preserve history).
**Stop feature creep:** every change must answer the 10-question test (Phase 2 §2) and reuse/improve
existing capability rather than spawn a new subsystem.
**No premature "complete"** (§82): a thing is done only when UI + workflow + permissions + DB + audit
+ errors + mobile/portal + existing-still-works + tests + e2e + persona all hold.

Branch: `claude/quotation-management-workflow-5dokb2`. Suite baseline at Phase 2 start: **3240 passed,
0 failed** (the former "3 baseline failures" were fixed honestly — see §Done).

---

## What Phase 1 already delivers toward Phase 2 (to be VERIFIED, not rebuilt)

Phase 2 must not re-build these — verify, then only fix gaps:

| Phase 2 point | Already exists (Phase 1 / pre-existing) | Verify status |
|---|---|---|
| §6 Applicable report engine | applicability engine + provenance + override audit | to verify |
| §7 Universal report foundation (URFE) | field/section/table/evidence/finding/workflow/versioning/audit | to verify |
| §8 Report builder validation | `idems_template_validate` / `idems_format_validate` (Module 48) | partial (persona previews?) |
| §10 Issuance readiness gate | `idems_issue_readiness` (Module 08/44) | to verify |
| §11 Issued immutability + hash | sealed report_docs + evidence chain | to verify |
| §12–14 Evidence engine/object/integrity | evidence chain + readiness (Module 44) | partial (requirement config?) |
| §15 Job 360 | job_now header (Module 05) | to verify decision-first |
| §16–17 Entity 360 + activity timeline | act_render_timeline (Modules 40/49) | partial (coverage/consistency) |
| §18 My Work | ops_pending_tasks + my_work (Module 39) | to verify inbox richness |
| §19 Notification split | /notifications outbox (Module 38) + attention band | partial (action-centre verbs) |
| §20/21 Command Centre vs System Health | attention_summary + /system-status (Modules 34/50) | partial (business KPI drill) |
| §22 Global search | search sources (Module 37) | partial (coverage/scope gaps flagged) |
| §28 Financial truth | job_profit canonical + profit_reconciliation (Module 32) | partial (one-engine convergence) |
| §31 Overhead engine | overhead_recovery (Module 33) | to verify versioned rates |
| §33 Invoice readiness | books/receivables gates (Module 09) | to verify configurable gate |
| §34 Change control | controlled_changes (Module 42) | partial (full CR lifecycle) |
| §35 Training | competence_training_watch (Module 43) | partial (attendance/assessment) |
| §36 Competence | competence eligibility verdict (Module 24) | to verify server-side gate |
| §37 Impartiality | impartiality verdict + gates (Module 25) | to verify |
| §38 Equipment impact | equipment_calibration_impact (Module 23) | to verify review-not-invalidate |
| §40 QMS doc control | cdoc lifecycle (Module 41) | to verify |
| §45/46 Terminology/status std | terminology + lk mapping | partial (canonical mapping) |
| §47 Settings governance | setting_set audit + secret redaction (Module 14) | partial (category/impact) |
| §51–54 Security/audit | idems_audit sealed chain + gates | to TEST (real pentest) |
| §67 AI governance | AI provenance on chain (Module 45) | to verify no silent auto-act |

---

## Workstreams (proposed batches)

- **W0 — Test truth & harness** (§55/56/82): one reproducible `qa/run-all-tests`, categorized output,
  honest PASS/FAIL/SKIPPED/ENV, no unexecuted-as-passed. **[first slice done: 3 failures fixed]**
- **W1 — Verification Audit** (§1, §83-A/L): map all 84 points to Implemented/Partial/Missing with
  code evidence + defect class (§81). The anti-feature-creep foundation.
- **W2 — Security & tenant isolation P0** (§51-54, §52): real IDOR / authz / CSRF / portal-isolation /
  identity / audit-integrity tests for CLIENT/VENDOR/JOB/REPORT/INVOICE/DOCUMENT/EVIDENCE/NCR/CAPA.
- **W3 — Report workflow hardening** (§4-11, §43): prepared→vetted→approved→issued clarity, submit UX,
  return-to-inspector detail, issuance readiness, immutability — the operating spine.
- **W4 — Financial truth convergence** (§27-33): one calculation service; reconcile boss_profit vs
  Σjob_profit; historical reproducibility (effective dates / period snapshots); invoice readiness.
- **W5 — Consolidation & canonical models** (§23-26, §45/46, §79/80): party/person/engagement/task
  canonical concepts + mapping layer + status mapping + CANONICAL APPLICATION MODEL doc.
- **W6 — UX / persona / click-tax** (§15-22, §44, §58-64, §71): decision-first surfaces, role desks,
  error/empty-state/filter audits, persona walk-throughs with click counts.
- **W7 — Scale & performance** (§65/66, §57): N+1/query audit; 5→50→500→5,000 simulation company.
- **W8 — Mobile offline real validation** (§41/42): the 16-step offline/recovery test with a
  local-transaction ledger (local id/op/status/retry/ack).
- **W9 — Final deliverable** (§83): Executive summary, risk register, deferred work, Phase 3 rec.

Each workstream: investigate (real code/flow) → fix non-destructively → test → doc → commit+push.
Findings classified per §81 (Critical/High/Medium/Low · P0-P3 · effort XS-XL · business impact).

---

## Done

- **2026-08-26 — W0 slice: test truth.** Investigated the 3 long-standing failures
  (`test_services.php:86/87/95`). Root cause: `seed_demo_c.php` seeded a GLOBAL
  `RELEASE→INSPECTION` service dependency, violating the module's "none by default; configured =
  scoped" design and forcing the workflow on every client. Fixed non-destructively by scoping the two
  demo dependencies to the demo client (`CL-NIL`). Suite → **3240 passed, 0 failed**. Commit `17f8254`.

- **2026-08-26 — W1 Verification Audit COMPLETE** (`docs/phase-2/01-verification-audit.md`). All 84
  points mapped to Implemented/Partial/Missing with code evidence (four read-only investigations +
  Phase-1 grounding) and §81 classification. Headline: **§30 historical financial reproducibility is
  MISSING (P0 Critical)**; systemic **§51 IDOR on single-record/PDF/file reads (P1)**; **§53 identity
  not encrypted at rest (P1)**; **§54 audit chain master-wipeable (P1)**; **§10 issue gate omits
  vetting/competence/impartiality/NCR/client-acceptance (P0/P1)**; **§11 seal fail-open**. Consolidation
  (canonical person/engagement/financial-event, quality-case umbrella) genuinely absent but P2/P3
  convergence-layer work. Full defect register + exec summary in the audit doc.

- **2026-08-26 — P0 fix batch COMPLETE** (approved). Non-destructive, tested, pushed:
  - **§11** seal fail-closed / self-healing (commit adds SEAL_FAILED sentinel + cron re-seal + compliance flag).
  - **§10** issuance-readiness completeness (vetting/completeness/competence/impartiality/NCR/client-acceptance
    probes; advisory by default, vetting hard-blocks only when the body enabled vetting; `issue_gate_strict`).
  - **§30** historical financial reproducibility — frozen job cost basis (snapshot at close + nightly backfill);
    `job_profit()` prefers the snapshot; freezing changes no number today, stops all future drift. **P0 Critical closed.**
  - Suite 3270 → **3288 passed, 0 failed**.

- **2026-08-26 — P1 SECURITY batch COMPLETE** (approved; decisions: env-key `APP_ENCRYPTION_KEY`,
  fail-closed IDOR). Non-destructive, tested, pushed:
  - **§51** cross-office IDOR closed on /job, /document, /document-pdf, /invoice(+print),
    /endorsement-file, /checkin-photo via `scope_allows()` (scalar twin of scope_clause; masters/ALL exempt).
  - **§54** audit chain: retention trim re-anchored (legitimate purge no longer reads as tampering, real
    tampering still caught); wholesale wipe leaves durable evidence + needs a distinct "ERASE AUDIT" phrase.
  - **§53** identity docs encrypted at rest (AES-256-GCM, env key), self-describing ciphertext, legacy
    plaintext coexists, nightly backfill, compliance nudge. No key ⇒ unchanged behaviour.
  - Suite **3343 passed, 0 failed**.

- **2026-08-26 — REPORT-WORKFLOW P1 batch COMPLETE** (approved). Non-destructive, tested, pushed:
  - **§6** applicability overrides audited + `not_allocated` flag stored + reason captured.
  - **§4** all four report roles printed (Vetted-by + Issued-by PDF blocks) + Prepared timestamp.
  - **§9** structured return-to-inspector detail (section/field + deadline) on both return paths.
  - Suite **3381 passed, 0 failed**.
  - **First-tranche recommendation fully delivered: P0 (§30/§10/§11) + P1 security (§51/§54/§53) +
    report-workflow P1s (§6/§4/§9) — 9 fixes, suite 3240 → 3381, 0 failures throughout.**

## Open decisions

- **Fix scope/order** (per "audit-first, then confirm fixes"). Recommended first fixes:
  **P0 §30 (financial reproducibility snapshot) + §10 (issue-gate completeness) + §11 (seal fail-closed)**,
  then **P1 security §51/§53/§54**, then report-workflow P1s (§4/§6/§9). **Item #7 (financial
  convergence, §28) changes displayed numbers → needs explicit sign-off.** Awaiting your selection.
