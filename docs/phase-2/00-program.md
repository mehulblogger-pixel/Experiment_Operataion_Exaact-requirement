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

- **2026-08-26 — §22/§51 global-search scope leak closed.** Every global-search source scoped by
  office/SBU except two — **inquiries** (`crm_inquiries`) and **contracts** (`partner_contracts`) — which
  have an `sbu` column but were queried LIKE-only, so a user could find another SBU's inquiry/contract
  through the search box even though the register hides it. Added `search_sbu_clause()` mirroring those
  modules' own list scope (`sbu IN (mine) OR sbu=''`, master/ALL unrestricted, blank stays visible) and
  applied it to both sources. Fail-closed, consistent with the §51 posture. Test `test_p2_search_scope.php`
  (8 assertions: master pass-through, in-scope found, blank visible, other SBU NOT found, both sources).
  Suite **3505 passed, 0 failed**.

- **2026-08-26 — §17 register spine entities already being logged (CANDIDATE / RECEIPT).** RECEIPT and
  CANDIDATE activities were already written to the shared activity spine (`act_log`) but the entities
  weren't in `ACT_ENTITIES`, so the universal timeline could neither label nor link them and their detail
  screens had no history panel. Registered CANDIDATE / RECEIPT / CONTRACT (additive) and wired
  `act_render_timeline()` onto the candidate and receipt details. Non-destructive — no existing entry,
  route or renderer changed. Test `test_p2_timeline_entities.php` (11 assertions: registration, round-trip
  through `act_for_entity`, per-entity isolation, view wiring). Suite **3497 passed, 0 failed**.

- **2026-08-26 — §48 bulk action preview / dry-run.** The bulk framework ran CONFIRM→EXECUTE with no
  way to see which rows would be skipped and why before committing. Added `lib/bulk.php`: `bulk_plan()`
  partitions the ticked ids into will-apply / will-skip(reason) from a classifier, `bulk_plan_summary()`
  renders the confirm sentence. Made **leads** the reference adopter by extracting one shared eligibility
  rule (`leads_bulk_eligible`/`leads_bulk_allowed`) used by BOTH the new `leads_bulk_plan()` preview and
  the `leads_bulk()` executor, so preview and result can never disagree; wired a `preview` mode on the
  leads-bulk route. Test `test_p2_bulk_preview.php` (16 assertions) proves the preview count equals the
  executed count and the already-closed rows are untouched. Non-destructive (executor behaviour
  preserved). Suite **3486 passed, 0 failed**.

- **2026-08-26 — §25 engagement grouping.** The sales→ops→finance spine already threads one string
  (`contract_number`) through quotations/calls/jobs/invoices (reports hang off jobs), but "show the whole
  engagement behind this contract" was assembled ad-hoc. Added `lib/engagement.php`: `engagement($contractNumber)`
  returns the full spine (quotes→calls→jobs→reports→invoices) as one normalised member list + rollup
  (counts, open calls/jobs, billed total excl. cancelled); `engagement_render()` panel on the contract detail.
  Read-only **view over `contract_number`** — no new table/status. Test `test_p2_engagement.php` (14
  assertions incl. cross-contract isolation, report-via-job_id, cancelled-invoice exclusion). Suite **3471
  passed, 0 failed**.

- **2026-08-26 — §72 canonical visibility gate.** Visibility was enforced by several per-record
  mechanisms (report `vendor_visible`, NCR `visibility` code, site-log `client_visible`) filtered at each
  portal query, with no single-record answer to "who may see this?" and no shared vocabulary. Added
  `lib/visibility.php`: one vocabulary (`VIS_CLASSES`/`VIS_AUDIENCES`), `visibility_can_see()` (the scalar
  twin of `cvp_visibility_sql`, fail-closed) which **delegates to `cvp_can_see()`** so it can never diverge,
  and `visibility_class_of()` reading each record's existing flag. Reading layer over the existing flags —
  none changed or removed. Test `test_p2_visibility.php` (23 assertions incl. the no-divergence property
  across every cvp code × audience). Suite **3457 passed, 0 failed**.

- **2026-08-26 — §68 evidence reuse across jobs.** Upload de-duplication only catches the same photo
  twice in ONE report (`report_doc_id`+`sha1`). Added `evidence_reuse_groups()`/`evidence_reuse_count()`
  (`lib/trust.php`): the same bytes under two DIFFERENT jobs — a photo carried from one inspection to
  another — surfaced on the evidence-review screen as an **advisory** panel (logos/form-scans/re-shoots
  are legitimate, so never a hard block or delete), plus a count on the readiness KPIs. Reuses the sha1
  already stored; read-only. Test `test_p2_evidence_reuse.php` (9 assertions: cross-job counts, same-job
  does not, deleted drops out, wiring). Suite **3434 passed, 0 failed**.

- **2026-08-26 — W5 CONSOLIDATION batch (§23/24, §46, §39) + CANONICAL MODEL (§79/80).**
  Non-destructive convergence layers over the existing modules (no table merges, nothing deleted):
  - **§23/24** canonical person — `lib/party.php` resolves one human across the six identity stores
    (users/inspectors/candidates/partner_contacts/client_users/vendor_users) by ref→mobile→email via a
    UNION matcher; "also appears as" panel on candidate detail.
  - **§46** status standardisation — `call_status_disagrees()` detects a legacy-vs-canonical call
    terminality mismatch and surfaces it as a Module 29 §7.11 integrity check (read-only; auto-repairs nothing).
  - **§39** quality-case umbrella — `lib/qualitycase.php` assembles complaint+NCR+CAPA into one read-only
    "full story" view from the FKs those modules already carry, with the corrective-action outcome
    (root cause / effectiveness / closure); panel on NCR + CAPA details.
  - **§79/80** `docs/phase-2/02-canonical-application-model.md` — the authoritative canonical model
    (entities, statuses, workflows, the one profit engine, evidence, tasks, permissions, terminology,
    observability) + the legacy-compatibility register (why each legacy concept is retained, its canonical
    replacement, and deprecation status). Every future change must read through the canonical engine named there.
  - Suite **3425 passed, 0 failed**. (§28 profit-engine convergence still held for explicit sign-off.)

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
