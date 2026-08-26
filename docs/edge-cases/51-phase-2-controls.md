# Module 51 — Phase-2 controls · Edge-case catalogue

**Status:** ✅ BUILT (2026-08-26, Phase 2). This file catalogues the edge cases for the controls added
in the Phase-2 consolidation-and-hardening program. Those controls layer on top of the existing modules
(they never replace them), so their edge cases live here rather than being scattered — but each row
names the module it strengthens, and the per-module files (`09-invoicing.md`, `26-identity.md`, …)
remain the primary reference for everything else.

Every row is grounded in code and **verified by an automated test** (the "Verified by" column names the
`phpapp/tests/test_p2_*.php` file). The full suite is **3559 passed, 0 failed**.

Legend — **Severity:** 🔴 regulatory/financial integrity · 🟠 security · 🟡 workflow correctness · ⚪ UX.
Design posture for the readiness/consistency controls is **advisory-first**: they surface a verdict and
only hard-block when an installation opts in via a setting — so the default behaviour of every existing
screen is unchanged.

---

## §33 — Invoice readiness (Module 09 · `lib/invready.php`)

The point where a job becomes money owed. Advisory by default; blocks `/job-bill` only when
`invoice_gate_strict` is on. Verified by `test_p2_invoice_readiness.php`.

| # | Case | Precondition | Action | Expected | Sev |
|---|---|---|---|---|---|
| 33.1 | Open job never billable | `closed_flag=0` | `invoice_readiness()` | `ready=false`, first blocker `closed` | 🔴 |
| 33.2 | Clean closed job | closed, one ISSUED report, under contract value | readiness | `ready=true`, no blockers | 🟡 |
| 33.3 | Unissued report | closed job has a report in DRAFT/VETTING/UNDER_REVIEW | readiness | blocker `reports_issued`; `ready=false` | 🔴 |
| 33.4 | Release not accepted | RN/IRN report present AND `rn_require_client_acceptance=1`, no acceptance | readiness | blocker `client_acceptance` | 🔴 |
| 33.5 | Over contract value | billed-so-far + this job > `partner_contracts.value` | readiness | **warning** `contract_value` (not a blocker); `ready` still true | 🟠 |
| 33.6 | No PO on the call | `calls.po_id` null | readiness | **warning** `po` (advisory — some clients bill without one) | ⚪ |
| 33.7 | Advisory posture | `invoice_gate_strict` unset, a blocker fails | `/job-bill` | billing proceeds; `invoice_readiness_block()` returns `''` | 🟡 |
| 33.8 | Strict gate blocks | `invoice_gate_strict=1`, a blocker fails | `/job-bill` | billing refused with the blocker message | 🔴 |
| 33.9 | Strict gate + ready | `invoice_gate_strict=1`, all blockers pass | `/job-bill` | proceeds (warnings never block) | 🟡 |

## §10 — Issuance readiness (Module 08 · `idems_issue_readiness`)

Expanded from a status check to a completeness gate. Advisory unless `issue_gate_strict`; vetting is the
one hard-block, and only when the body enabled vetting. Verified by `test_p2_issue_gate.php`.

| # | Case | Precondition | Expected | Sev |
|---|---|---|---|---|
| 10.1 | Vetting required, not vetted | `idems_vetting_required` on, report not VETTED | hard block regardless of strict setting | 🔴 |
| 10.2 | Vetting not required | body never enabled vetting | vetting probe absent — not a blocker | 🟡 |
| 10.3 | Incompleteness | `idems_completeness_check` finds empty mandatory sections | advisory blocker (hard under strict) | 🟡 |
| 10.4 | Competence lapsed | `competence_block` for the inspector | advisory blocker | 🟠 |
| 10.5 | Impartiality unresolved | `imp_block` set | advisory blocker | 🟠 |
| 10.6 | Open NCR on the job | ≥1 open nonconformity | advisory blocker | 🟡 |
| 10.7 | Release w/o acceptance | RN/IRN + acceptance required + not accepted | advisory blocker | 🔴 |

## §11 — Seal fail-closed (Module 08 · `lib/idems.php`)

A failed content-seal must never read as "sealed". Verified by `test_p2_seal_failclosed.php`.

| # | Case | Precondition | Expected | Sev |
|---|---|---|---|---|
| 11.1 | Seal write fails | `idems_seal_content()` cannot persist | writes `SEAL_FAILED` sentinel, returns false | 🔴 |
| 11.2 | Reader sees a failed seal | sentinel present | `idems_content_check()` → `sealed=false, problem=seal_failed` (never a false "ok") | 🔴 |
| 11.3 | Self-heal | cron runs `idems_reseal_failed()` | failed seals re-sealed; `idems_seal_failed_count()` drops | 🟡 |
| 11.4 | Compliance surfacing | any failed seal | a compliance-check row flags it | 🟡 |

## §30 — Historical financial reproducibility (Modules 05/32 · `lib/ops.php`)

A closed job's profit must not drift when today's rates change. Verified by `test_p2_cost_reproducibility.php`.

| # | Case | Precondition | Expected | Sev |
|---|---|---|---|---|
| 30.1 | Freeze at close | job closes | `cost_basis_at` + `cost_daily_base`/`cost_oh_pct`/`cost_contingency_pct` snapshotted | 🔴 |
| 30.2 | Rate change after close | overhead rate edited later | `job_profit()` for the closed job is unchanged (reads the snapshot) | 🔴 |
| 30.3 | Live job unaffected | open job, no snapshot | `job_profit()` computes live (current rates) | 🟡 |
| 30.4 | Backfill | `jobs_backfill_cost_basis()` on legacy closed jobs | basis populated from the historical rate; no number changes on next view | 🟠 |

## §51 — Single-record IDOR scope (Module 02 · `scope_allows`)

List scoping had a scalar twin gap on fetch-by-id. Fail-closed, mirrors list scope. Verified by
`test_p2_idor_scope.php`.

| # | Case | Precondition | Action | Expected | Sev |
|---|---|---|---|---|---|
| 51.1 | Cross-office job fetch | user scoped to office A | `/job?id=` for office B | denied | 🟠 |
| 51.2 | Cross-office document/PDF | scoped user | `/document`, `/document-pdf` out of scope | denied | 🟠 |
| 51.3 | Cross-office invoice | scoped user | `/invoice`, `/invoice-print` out of scope | denied (only when `office_id` set) | 🟠 |
| 51.4 | Evidence file by id | scoped user | `/endorsement-file`, `/checkin-photo` out of scope | denied via parent office/SBU | 🟠 |
| 51.5 | Master exempt | MASTER_ADMIN or office=ALL | any id | allowed | 🟡 |
| 51.6 | Null office default | record `office_id` empty | scope check | treated as Ahmedabad (fail-closed default) | 🟠 |

## §53 — Identity encryption at rest (Module 26 · `lib/security.php` + `lib/identity.php`)

AES-256-GCM, env key `APP_ENCRYPTION_KEY`, self-describing `enc:v1:` ciphertext, legacy plaintext
coexists. Verified by `test_p2_identity_encryption.php`.

| # | Case | Precondition | Expected | Sev |
|---|---|---|---|---|
| 53.1 | New doc encrypted | key present, `iddoc_add` | stored value carries `enc:v1:` prefix | 🟠 |
| 53.2 | No key configured | `APP_ENCRYPTION_KEY` unset | behaviour unchanged (plaintext), no fatal | 🟡 |
| 53.3 | Round-trip | encrypted value | `iddoc_number_read()` decrypts to the original | 🟠 |
| 53.4 | Legacy plaintext read | old row, no prefix | read returns it as-is (coexistence) | 🟡 |
| 53.5 | Backfill | `iddoc_encrypt_backfill()` | plaintext rows encrypted; `iddoc_plaintext_count()` drops | 🟠 |
| 53.6 | Tamper/no-key decrypt | ciphertext but key missing/wrong | fails safe (no plaintext leak) | 🟠 |

## §54 — Audit-chain protection (Module 29 · `lib/compliance.php` + `lib/reset.php`)

Legitimate retention-trim must not read as tampering; a wholesale wipe must leave evidence. Verified by
`test_p2_audit_protection.php`.

| # | Case | Precondition | Expected | Sev |
|---|---|---|---|---|
| 54.1 | Retention trim | `audit_trim_old()` runs | boundary hash saved as `audit_trim_anchor`; verify treats it as a legit break | 🟠 |
| 54.2 | Real tampering still caught | a row altered mid-chain | `idems_audit_verify()` reports `broken` | 🔴 |
| 54.3 | Wholesale wipe | reset run on audit | durable `audit_reset_log` + `AUDIT_RESET` entry written | 🔴 |
| 54.4 | Wipe needs intent | reset handler | requires the distinct phrase "ERASE AUDIT" | 🟠 |

## §46 — Status agreement (Module 29 · `lib/tosrm.php`)

A legacy `status` and a canonical `op_status` must not silently disagree on terminality. Read-only
detection. Verified by `test_p2_status_agree.php`.

| # | Case | `op_status` / `status` | Expected | Sev |
|---|---|---|---|---|
| 46.1 | Finished vs open | CLOSED / OPEN | disagreement → flagged | 🟡 |
| 46.2 | Open vs finished | RECEIVED / CLOSED | disagreement → flagged | 🟡 |
| 46.3 | Both finished | CLOSED / CLOSED | agree | ⚪ |
| 46.4 | Both in-progress | ASSIGNED / ALLOCATED | benign — not flagged | ⚪ |
| 46.5 | No canonical yet | '' / CLOSED | nothing to disagree with | ⚪ |
| 46.6 | Integrity surfacing | a mismatched call exists | `integrity_checks()` `call_status_agree` fails (found ≥ 1) | 🟡 |

## §39 — Quality-case umbrella (Modules 12/13/22 · `lib/qualitycase.php`)

Read-only view linking complaint→NCR→CAPA via existing FKs. Verified by `test_p2_quality_case.php`.

| # | Case | Precondition | Expected | Sev |
|---|---|---|---|---|
| 39.1 | Assemble from NCR | NCR ← complaint, NCR → CAPA | 3 members, no duplicates | 🟡 |
| 39.2 | Same case from any anchor | anchor on CAPA or complaint | identical 3-member case | 🟡 |
| 39.3 | Outcome read from CAPA | root cause + effective=YES + verified_on | `rca=true, effective=YES, closed=true` | 🟡 |
| 39.4 | Standalone record | NCR with no links | one-member case; panel renders nothing | ⚪ |

## §23/24 — Canonical person (Modules 02/15/16/35 · `lib/party.php`)

One human resolved across six identity stores by ref→mobile→email. Verified by `test_p2_party.php`.

| # | Case | Precondition | Expected | Sev |
|---|---|---|---|---|
| 23.1 | Match by mobile | same last-10 digits across stores | records linked | 🟡 |
| 23.2 | Match by email only | email shared, no mobile | still linked (UNION matcher, any shared id) | 🟡 |
| 23.3 | Optional `person_ref` absent | store lacks the column | that dimension skipped, others still match (per-query try/catch) | 🟡 |
| 23.4 | No shared identifier | disjoint people | not linked (no false merge) | 🟠 |

## Consolidation views (advisory / read-only)

| §  | Control | Module | Verified by | Key edge case |
|----|---|---|---|---|
| §4  | Four report roles on the PDF | 08 | `test_p2_report_roles.php` | Vetted/Issued rows print only when those roles applied |
| §6  | Applicability override audit | 06 | `test_p2_applicability_override.php` | override stores reason + `APPLICABILITY_OVERRIDE` log; `not_allocated` flag |
| §9  | Structured return-to-inspector | 07 | `test_p2_return_detail.php` | section/field + deadline captured on both return paths |
| §25 | Engagement rollup | 18 | `test_p2_engagement.php` | read-only spine over `contract_number`; empty contract → empty rollup, no error |
| §27/§29 | Revenue reconciliation | 32 | `test_p2_rev_recon.php` | register vs job-mirror divergence counted; 0 when consistent |
| §32 | Inter-office settlement | 20 | `test_p2_settlement.php` | matrix balances; same-office job has no cross-credit |
| §47 | Settings governance registry | 14 | `test_p2_setting_meta.php` | every behavioural setting documented (forward/live, impact) |
| §48 | Bulk preview partition | 17 | `test_p2_bulk_preview.php` | preview uses the SAME `*_eligible()` as the executor — cannot disagree |
| §68 | Evidence reuse detection | 44 | `test_p2_evidence_reuse.php` | same photo hash across reports counted; unique evidence → 0 |
| §72 | Field/evidence visibility | 44 | `test_p2_visibility.php` | internal-only fields hidden from client/portal view |
| §17 | Activity spine entities | 40 | `test_p2_timeline_entities.php` | CANDIDATE et al. registered; timeline renders per entity |
| §22/§51 | Global-search SBU scope | 37 | `test_p2_search_scope.php` | search cannot leak rows outside the viewer's SBU scope |

---

## Deferred (documented, not yet built — no edge cases to assert)

These remain design-gated and are **not** claimed as done:

- **§28** MIS/SBU-PL/`boss_profit` convergence onto `job_profit` — **changes displayed numbers; needs
  explicit sign-off.** `§29` revenue reconciliation is its evidence base (see divergent jobs on
  `/system-status` before deciding).
- **§26** persisted Task entity · **§50** generic integration/webhook layer · **§19/§20** action/command
  centres — held under STOP FEATURE CREEP; the read-time aggregators (`ops_pending_tasks`,
  `attention_summary`) cover the need today.

See `docs/phase-2/01-verification-audit.md` for the full 84-point register and
`docs/phase-2/02-canonical-application-model.md` for the canonical model these controls converge on.
