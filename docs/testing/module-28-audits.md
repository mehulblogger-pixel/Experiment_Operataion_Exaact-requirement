# Inspection Ops — Internal Audits & Management Review — Test & Documentation Report

> **Prompt 3 · Module MOD-AUDITS.** Read from `lib/audits.php` (`ops_audits`,
> `audit_auditor_block`, `audit_coverage`, `audit_close_missing`/`_block`, `audit-finding-capa`,
> `ops_reviews`, `mr_measure`/`mr_refresh_measures`, `review_complete_missing`/`_block`,
> `reviews_readiness`) and the tamper-evident trail `lib/idems.php` (`idems_log`,
> `idems_audit_payload`, `idems_audit_verify`, `audit_high_risk`, `ops_idems_audit`,
> `idems_compliance_checks`). Views `audits_list.php`, `audit_detail.php`, `reviews_*.php`,
> `idems/audit.php`.

| | |
|---|---|
| **Module** | Internal Audits, Management Review & Audit Trail (MOD-AUDITS) · Area Quality |
| **Personas** | P-QM (`mod.audits.edit`), P-AUDITOR, P-MASTER (audit-log/compliance) |
| **Risk weight** | **High** — ISO/IEC 17020 §8.8/§8.9 self-verification; the hash-chained trail is the system's evidential backbone |
| **Verdict** | Complete-with-defects (confirm auditor independence, close gates, chain integrity, QMS-record sealing) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Three related capabilities: (1) **internal QMS audits** (`internal_audits` + `audit_findings`,
§8.8) — planned per clause with **coverage** tracking (`audit_coverage`, cycle 365 days),
**auditor independence** (`audit_auditor_block`: auditor ≠ area owner), findings that escalate
to **CAPA** (NC_MAJOR/NC_MINOR need one), and a **close gate** requiring carried-out date +
summary + a finding + a CAPA for every NC; (2) **management review** (`mgmt_reviews` +
`mr_inputs` + `mr_actions`, §8.9) — a genuine cross-module aggregation where 11 of 15 inputs
are **auto-measured** from live modules (complaints, CAPA, audits, equipment, competence,
impartiality, work volume) with a completion gate; and (3) the **tamper-evident audit trail**
(`idems_audit`) — every logged action sealed as `entry_hash = sha256(prev_hash|payload)`,
verified by `idems_audit_verify` (content + link tests), surfaced read-only on `/audit-log`.

Screens: `/internal-audits`, `/internal-audit?id=`, `/audit-*`, `/management-reviews`,
`/management-review?id=`, `/review-*`, `/audit-log`. Tables: `internal_audits`,
`audit_findings`, `mgmt_reviews`, `mr_inputs`, `mr_actions`, `idems_audit`.

---

## B. Screen-by-screen catalogue

**`/internal-audits`** — list + readiness + clause coverage. **`/internal-audit`** — plan,
record, findings (add/delete, → CAPA), close (gated). **`/management-reviews`** — list;
**`/management-review`** — header, refresh auto measures, per-input notes, decisions/actions,
complete (gated). **`/audit-log`** — the hash-chained trail with filters, risk-30 stat, CSV,
and compliance checks (master).

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-AUD-form-001 | Auditor mandatory; **auditor ≠ area owner** (§8.8.2, hard block). |
| TC-AUD-form-002 | ≥1 valid clause on plan; finding detail required. |
| TC-AUD-form-003 | Finding→CAPA blocked if already linked; NC needs a CAPA to close. |
| TC-AUD-form-004 | MR complete: held date + chair + every required input note + ≥1 decision. |
| TC-AUD-form-005 | Timestamp edit (`/idems-timestamp`): only inspection/issue date, **reason mandatory**, logs TIMESTAMP_EDIT. |

---

## D. Functions & logic  *(independence + chain — highest scrutiny)*

- **Auditor independence** (`audit_auditor_block`): auditor must differ from the area owner —
  but the check is **name-string** (`strcasecmp`), trivially bypassed by a variant (GAP-AUD-001).
  **TC-AUD-fn-001.**
- **Coverage** (`audit_coverage`): each clause mapped to last-covered within the cycle;
  uncovered clauses surfaced. **TC-AUD-fn-002.**
- **Close gates** (`audit_close_missing`, `review_complete_missing`): audit needs carried-out
  + summary + finding + CAPA-per-NC; MR needs held date + chair + input notes + a decision.
  **TC-AUD-fn-003..004.**
- **Hash chain** (`idems_log`/`idems_audit_verify`): each row seals the prior; verify runs a
  content test (recompute) + a link test (prev_hash must reference a seen seal → catches
  interior deletions). **A tail deletion of the newest rows is undetectable** (no forward
  pointer) (GAP-AUD-002). **TC-AUD-fn-005** — an interior edit/deletion is detected;
  **TC-AUD-fn-006** — internal-audit/MR mutations are **not** written to `idems_audit`
  (GAP-AUD-003).

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| audit PLANNED → IN_PROGRESS → REPORTED → CLOSED | record/close | close gate |
| finding → CAPA | escalate | not already linked |
| MR DRAFT → COMPLETE | complete | completion gate |
| MR action OPEN → DONE | done | — |
| audit-log row appended | any logged action | sealed into the chain |

- **TC-AUD-life-001:** an audit with an unclosed NC's CAPA cannot close (but a later-deleted
  CAPA can leave the finding falsely satisfied — GAP).
- **TC-AUD-life-002:** a legacy unsealed audit-log row is skipped by verify, not failed.

---

## F. Roles, permissions & data scope

Audits/MR: `mod.audits.view`/`mod.audits.edit`; settings admin/master. Audit-log:
`is_master()`/`idems.audit.view`. Timestamp edit: `idems.timestamp.edit` (Branch App Manager).
**No office/SBU scoping on audits/reviews** — all rows visible to any viewer (GAP-AUD-004).

- TC-AUD-perm-001 (edit without permission) → refused.
- TC-AUD-perm-002 (audit-log without master/`idems.audit.view`) → refused.

---

## G. Settings

`audit_cycle_days` (365), `audit_high_risk` (CSV of high-risk action codes flagged on
`/audit-log`), clause list (`audit_clause` lookup). **TC-AUD-set-001:** high-risk actions
drive the risk filter/stat; **TC-AUD-set-002:** cycle days move coverage.

---

## H. Cross-module integration

**CAPA/NCR** (findings → CAPA; MR pulls readiness — MOD-12/13), **every module** (MR
auto-measures from complaints/CAPA/audits/equipment/competence/impartiality/work volume),
**Data control** (`audit_chain` integrity check consumes `idems_audit_verify` — MOD-29),
**IDEMS** (the trail seals report lifecycle, logins, evidence, DPDP events). Idempotency:
verify is read-only.

---

## I. Data integrity & audit

The hash chain is the system's evidential backbone; `idems_audit_verify` detects interior
edits/deletions. **Weaknesses:** tail deletion undetectable; internal-audit/MR events not
sealed into the chain (finding-delete is a hard DELETE); a transient seal-read failure
restarts the chain (a new row becomes an unlinkable genesis). **TC-AUD-int-010:** an
out-of-band content edit fails verify; **TC-AUD-int-011:** MR completeness checks the notes
are present but not that the auto-measures were refreshed for the review period (GAP).

---

## J. Reports & outputs

The audit list + coverage, the audit report + findings, the MR record + auto-measured inputs
+ actions, the `/audit-log` (filters, risk-30, CSV), and the compliance checks (stuck
reports, timestamp edits, failed logins, soft-deletes). **TC-AUD-out-001:** coverage shows
uncovered clauses; **TC-AUD-out-002:** the audit-log CSV carries the sealed rows.

---

## K. Negative, edge & resilience

Self-audit (blocked, but name-based); close an audit with an open NC CAPA (refused); a
finding whose CAPA is later deleted (falsely satisfied); complete an MR with a blank input
(refused); a tail-trimmed audit log (undetected); an interior-edited row (detected); a
timestamp edit with no reason (refused).

---

## L. TPIA operational suitability

Serves §8.8/§8.9 with a real programme (clause coverage, independent auditor, findings→CAPA)
and a management review that genuinely aggregates the running system, plus a tamper-evident
trail that is the assurance an assessor wants. The name-based independence, the unsealed QMS
records, and the tail-deletion blind spot are the gaps to close for full evidential weight.

## M. Management usefulness

`audits_readiness`/`reviews_readiness` and the compliance checks give QM a live self-
verification view; the MR auto-measures cut preparation to a refresh. Confirm coverage and
the chain verify clean.

## N. UI/UX

Guided audit (plan → record → findings → close), MR with one-click refresh, read-only audit
log with risk filter. Terminology via `T*()`.

## O. Security

Audit-log master-gated; timestamp edit tightly gated + reason-logged; the chain detects
interior tampering; **but QMS records are not sealed, tail deletion is undetectable, and
audits/reviews are unscoped** — address these.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | **Gap** | §F unscoped |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E close gates |
| 12 Integration | **Priority** | §H MR aggregation |
| 13 Data integrity | **Priority** | §I hash chain |
| 14 Audit | **Priority** | §I the trail itself |
| 15 Outputs | Y | §J audit-log |
| 16 TPIA suitability | Y | §L §8.8/§8.9 |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O |
| 21 Import | N-A | — |
| 22 Notifications | Partial | reminders |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | accreditation pack |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | cycle days |
| 28 Performance | Partial | — |
| 29 Backup | Partial | §I |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-AUD-001 | (verify) | **Auditor independence is name-string based** (`strcasecmp` auditor vs area owner) — trivially bypassed by a spelling/spacing variant. Tie it to user identity. |
| GAP-AUD-002 | (verify — Major) | The hash chain's link test **cannot detect a tail deletion** of the newest sealed rows (no forward pointer) — add a periodic anchor/high-water mark. |
| GAP-AUD-003 | (verify) | **Internal-audit and management-review mutations are not sealed into `idems_audit`** (finding-delete is a hard DELETE) — these QMS records have no tamper-evident history; and a finding's satisfying CAPA, if later deleted, leaves the audit falsely closeable. |
| GAP-AUD-004 | — | Add office/SBU scoping to audits/reviews (currently every viewer sees all). |

---

## R. Traceability

RTM slice: `/internal-audit*`, `/management-review*`, `/audit-log` × dims 1–29 → TC-AUD-* →
results → DEF/GAP. **Verdict: Complete-with-defects** — auditor independence (id-based),
chain tail-integrity, and QMS-record sealing are the exit conditions.
