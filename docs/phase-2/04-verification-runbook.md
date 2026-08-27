# Phase 2 — verification runbook

How to prove every Phase-2 control is real and correct. Four layers, cheapest first. Layer 1 alone
covers everything; Layers 2–4 let you confirm a specific control or see it with your own eyes.

---

## Layer 1 — the automated suite (the source of truth)

One command verifies the whole application, Phase-2 controls included:

```bash
cd phpapp && php tests/run.php
```

Expected tail: **`RESULT: 3583 passed, 0 failed`**. A green suite means every control below is
exercising real code paths on a throwaway database seeded per run. If any line reads `FAIL`, it names
the file and assertion. This is the check to trust over any prose claim.

The harness auto-discovers every `tests/test_*.php`; each runs in its own rolled-back DB transaction, so
running it never touches real data.

## Layer 2 — verify ONE control

Each Phase-2 control has its own test file. Run just it:

```bash
cd phpapp && php tests/run.php test_p2_finance_truth      # substring-matches the file
```

Control → test map (24 files):

| Area | § | Test file |
|---|---|---|
| Seal fail-closed | §11 | `test_p2_seal_failclosed.php` |
| Issuance readiness | §10 | `test_p2_issue_gate.php` |
| Financial reproducibility | §30 | `test_p2_cost_reproducibility.php` |
| Single-record IDOR scope | §51 | `test_p2_idor_scope.php` |
| Identity encryption at rest | §53 | `test_p2_identity_encryption.php` |
| Audit-chain protection | §54 | `test_p2_audit_protection.php` |
| Four report roles on PDF | §4 | `test_p2_report_roles.php` |
| Applicability override audit | §6 | `test_p2_applicability_override.php` |
| Structured return-to-inspector | §9 | `test_p2_return_detail.php` |
| Canonical person | §23/24 | `test_p2_party.php` |
| Call status agreement | §46 | `test_p2_status_agree.php` |
| Quality-case umbrella | §39 | `test_p2_quality_case.php` |
| Evidence reuse detection | §68 | `test_p2_evidence_reuse.php` |
| Evidence/field visibility | §72 | `test_p2_visibility.php` |
| Engagement grouping | §25 | `test_p2_engagement.php` |
| Bulk preview | §48 | `test_p2_bulk_preview.php` |
| Activity-spine entities | §17 | `test_p2_timeline_entities.php` |
| Global-search SBU scope | §22/51 | `test_p2_search_scope.php` |
| Revenue reconciliation | §29 | `test_p2_rev_recon.php` |
| Settings governance registry | §47 | `test_p2_setting_meta.php` |
| Inter-office settlement | §32 | `test_p2_settlement.php` |
| Invoice readiness | §33 | `test_p2_invoice_readiness.php` |
| Unified financial truth (1+2) | §28 | `test_p2_finance_truth.php` |
| Profit-basis reconciliation (3) | §28 | `test_p2_basis_reconcile.php` |

Cross-reference: every row is also catalogued with its edge cases in
`docs/edge-cases/51-phase-2-controls.md`.

## Layer 3 — the app verifies itself (self-reporting surfaces)

Some controls surface their own status inside the running app — no test needed, just look:

- **`/system-status`** — audit-chain integrity, data-integrity checks (incl. §46 call-status agreement),
  compliance, licence, email, **profit-figure consistency (§28/§29)**, revenue reconciliation. A green
  board is the live health verdict.
- **`/profitability`** — the §28 **before/after preview**: the exact profit each dashboard shows today vs
  what it becomes when unified is switched on, itemised by the omitted costs.
- **`/sbu-pl`** — the §28 **Option 3** panel "Two ways of counting cost — reconciled": period-costing vs
  job-costing profit and the gap, per SBU. (Additive — changes no other figure.)
- **`/data-control`** — the §7.11 integrity checks and revenue reconciliation count.

## Layer 4 — see it with your own eyes (manual UI)

For the human-facing controls, the fastest proof is to drive the screen:

- **§33 invoice readiness** — open a closed job's **Money** tab: the readiness verdict (reports issued /
  release accepted / contract value) shows before the billing button. Turn on `invoice_gate_strict` in
  Settings to see it hard-block.
- **§10 issuance readiness** — open a report not yet complete and try to issue: the readiness gate lists
  the blockers.
- **§39 quality case** — open an NCR or CAPA that links to a complaint: the "Quality case — the full
  story" panel shows all three linked.
- **§23/24 person** — open a candidate who is also a contact/user: the "also appears as" panel links them.
- **§53 identity** — add an ID document with `APP_ENCRYPTION_KEY` set: stored value carries `enc:v1:`.

## The §28 numbers, on real data

To see the exact drift the §28 review is about, on the 104-job demo dataset:

```
Jobs measured:            104
Jobs whose profit drifts:  92
Canonical (job_profit):   profit 5,747,287.28
MIS/SBU-PL (partial):     profit 5,821,590.00
Overstatement:               +74,302.72   (overhead 59,168 + contingency 11,304.72 + voucher 3,830)
```

This is what `/profitability` previews and what turning `finance_truth_unified` ON would correct —
downward, to the canonical figure. Default is OFF; nothing has changed until you flip it.

## The paper trail

- `docs/phase-2/00-program.md` — the Done log, every batch with its suite count.
- `docs/phase-2/01-verification-audit.md` — the 84-point register with a DELIVERED section.
- `docs/phase-2/02-canonical-application-model.md` — the canonical model (§79/80).
- `docs/phase-2/03-financial-truth-review.md` — the §28 review + decision.
- `git log --oneline` — each control is one commit with its test count and suite result in the message.

---

*One-line answer: `cd phpapp && php tests/run.php` → `3583 passed, 0 failed`. Everything else here lets
you confirm a specific piece.*
