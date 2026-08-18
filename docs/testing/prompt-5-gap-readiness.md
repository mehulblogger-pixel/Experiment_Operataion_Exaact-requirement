# Inspection Ops — Gap, Risk & Readiness Assessment

> **Prompt 5 · Gap / Risk / Readiness.** Consolidates every DEF/GAP raised across the 31
> module reports (MOD-01…MOD-36) and the 9 end-to-end threads (Prompt 4) into one risk-ranked
> register, grouped by theme, with a per-area readiness verdict and an overall release
> recommendation. Traces to Inventory v1.0 and Governance v1.0.

| | |
|---|---|
| **Inputs** | 31 module reports · 9 E2E threads · automated suite (`php tests/run.php`, **1532 passing**, 3 accepted pre-existing NCDCA failures) |
| **Findings** | ~70 verify-items across 10 themes; **2 Critical, ~18 Major, the remainder Moderate/Low** |
| **Overall verdict** | **Strong release candidate with conditions** — operational spine and accreditation controls present and mostly enforced server-side; residual risk concentrated in cross-tenant isolation, cross-module money consistency, and a set of enforce-on-POST / independence / evidence items |

---

## A. How to read this

Every finding is a **verify-item**, not a confirmed live defect — the module reads establish
the code path and the risk; runtime confirmation on the crafted-POST / money-reconciliation
cases is the closing evidence. Severity is the impact if the risk is real; Priority is the
order to verify. **Critical** = data leak or an unforgeable control defeated; **Major** =
wrong money, a bypassable control, or a broken independence/evidence guarantee; **Moderate** =
scoping/atomicity/config integrity; **Low** = robustness/validation hardening.

---

## B. Risk register by theme (ranked)

### Theme 1 — Cross-tenant isolation (external surfaces) · **CRITICAL**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-PORTAL-001 | **Critical** | Confirm no client can reach another client's call/report/invoice/deputation by crafted id on any route incl. the PDF endpoint | 10 |
| GAP-VP-001 | **Critical** | Confirm no vendor can list/open an INTERNAL/CLIENT_VISIBLE report or another vendor's report/NCR (visibility engine) | 11 |
| GAP-PORTAL-002 / VP-002 | Major | Invite/session security: single-use expiring tokens, clean session, access-window enforced every request | 10,11 |
| GAP-CONF-003 | Major | Visibility engine fail-closes for every portal read (INTERNAL/RESTRICTED/MGMT_ONLY never reach a portal) | 27 |

### Theme 2 — Money & figure consistency · **MAJOR**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-PRF-001 | **Major** | `job_profit` labour divisor uses the *current* month's working days, not the job's — historical labour cost drifts and diverges from `costing_run` | 32 |
| GAP-PRF-002 | **Major** | MIS aggregates a hand-rolled cost (drops overhead/voucher/other/contingency/recovered) — MIS profit ≠ call-profit profit for the same jobs; breaks "same figure everywhere" | 32,34 |
| GAP-OVH-001 | **Major** | Two unreconciled overhead models — office expense heads never reduce contract-level profit; CONTINGENCY head vs contingency% double-count risk | 33 |
| GAP-INV-001 | **Major** | GST IGST-vs-CGST+SGST split + per-line rounding reconcile to the paisa across invoice/ledger/PDF/export; unknown state defaults local (not guessed) | 09 |
| GAP-INV-002 | **Major** | GST-safe numbering: gap-free series, issued invoice immutable, corrections are credit notes | 09 |
| GAP-QUOTES-001 | Major | Editing an APPROVED quote must re-open approval, not silently change a sent price | 03 |
| GAP-QUOTES-003 / GAP-CON-002 | Major | Amount-in-words == total; contract quantity `used` (man-days) vs `total` (countable lines) unit consistency | 03,18 |
| GAP-REC-003 | Major | Inter-office credit reconciled in bulk, not matched expected→received per job; `credit_received` unverified across offices | 31 |
| GAP-PRF-003 / GAP-OVH-003 | Moderate | SBU-P&L mixes live revenue with frozen allocations; freeze drift undetected | 32,33 |
| GAP-OVH-002 | Moderate | No ledger/books link for office overheads (manual GL reconciliation) | 33 |

### Theme 3 — Control enforcement on crafted POSTs (not just hidden buttons) · **MAJOR**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-IDEMS-002 | **Major** | Completeness gate + edit-lock enforced on `document-submit`/`-edit` POST; override always logged | 06 |
| GAP-RN-001 | **Major** | Every RN blocker (issued-source, flag, HWP, NCR, client-rejected, acceptance) enforced on the raise POST; master override logged | 08 |
| GAP-APPR-002 | **Major** | Can-act enforced on the approve POST (non-current-step / non-approver refused); no concurrent double-advance | 07 |
| GAP-IDEMS-001 | Major | IRN uniqueness under concurrency; twin/PO guards hold on a crafted POST | 06 |
| GAP-CON-001 | Major | Contract gate + override two-signature enforced on allocation/grant POST (EXHAUSTED not master-grantable) | 18 |
| GAP-CLI-001 | Major | Blocked/on-hold client gate enforced on the quote/call POST | 15,03,04 |
| GAP-NCR-001 / GAP-CAPA-001 | Major | Close gates (MAJOR→closed-CAPA; verified-effective) enforced on the close POST | 12,13 |
| GAP-JOBS-001/002 | Major | Contract/scheduling gate on allocation POST; close idempotency (offline replay) | 05 |
| GAP-INQ-002 / GAP-QUOTES(accept) | Moderate | Inquiry list & quote ACCEPTED/LOST transitions need a server-side view/permission gate | 19,03 |

### Theme 4 — Independence & segregation of duties · **MAJOR**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-APPR-001 / GAP-IMP-001 | **Major** | **No automatic no-self-approval** on report vetting/approval — same person can author and approve; SELF_REVIEW is only a manual threat | 07,25 |
| GAP-CMP-001 | Major | Complaint decider-independence is **name-string** based (bypassable by a variant) — move to user-id | 22 |
| GAP-AUD-001 | Major | Internal-audit auditor-independence is name-string based | 28 |
| GAP-PC(approve) | Moderate | Confirm a project-costing creator cannot approve their own bid where independence is required | 20 |

### Theme 5 — Evidence & tamper-evidence · **MAJOR**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-DC-001 | **Major** | Content seal is **unkeyed SHA-256** — recomputable after a DB edit so `/verify` passes again; add an HMAC/secret | 29 |
| GAP-AUD-002 | **Major** | Audit-chain link test **cannot detect a tail deletion** of the newest sealed rows — add a periodic anchor | 28 |
| GAP-AUD-003 | Major | Internal-audit/MR mutations are **not sealed** into the tamper-evident trail (finding-delete is a hard DELETE) | 28 |
| GAP-DC-004 | Moderate | Seal/freeze fail-open at issue (silent unsealed report); seal excludes attachments/signatures | 29 |
| GAP-IDEMS-003 | Moderate | Content seal + `idems_content_check`/`idems_audit_verify` detect post-issue tampering | 06 |

### Theme 6 — Privacy & security-at-rest · **MAJOR**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-ID-001 | **Major** | Identity numbers + file bytes stored **plaintext/base64, no encryption at rest**; access-log swallows its own errors | 26 |
| GAP-REC-002 | Major | Self-attendance GPS un-validated (spoofable), unlike evidenced site check-in | 31 |
| GAP-EQ-002 | Major | Equipment register readable without `mod.equipment.view`; `/equip-cert` download under-scoped | 23 |
| GAP-DASH-002 | Major | TAPI user-defined KPI formula engine — confirm `tapi_formula_valid` blocks injection | 34 |

### Theme 7 — Data scoping (visibility beyond one's office/SBU) · **MODERATE**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-PC-001 | Major | Project costings have **no office/owner scoping** — any quotes/hiring viewer sees all bids (salary-like data) | 20 |
| GAP-CMP-002 | Major | Complaint single-record detail view not office-scoped (open another branch's by id) | 22 |
| GAP-AUD-004 | Moderate | Internal audits/reviews not office-scoped | 28 |
| GAP-LEAD-003 / GAP-HIRE-003 / GAP-IMP-004 | Moderate | Leads/candidates SBU scoping leaky (blank SBU in-scope); impartiality register unscoped | 17,35,25 |

### Theme 8 — Idempotency & atomicity · **MODERATE**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-VCH-001 | Major | Closure-expense double-claim guarded only by `closed_flag` (INSERT before flag; non-transactional) | 30 |
| GAP-LEAD-002 / GAP-HIRE-001 | Moderate | Lead conversion & candidate→inspector not transactional (partial links / duplicate inspectors) | 17,35 |
| GAP-RN-003 / GAP-VEN-003 | Moderate | RN raised twice links (no dup); re-issued assessment does not double-log the timeline | 08,16 |

### Theme 9 — Config & model integrity · **MODERATE**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-LIC-001 | Major | Seat caps enforce on new-user creation only — reactivation-via-edit bypasses | 36 |
| GAP-LIC-002 | Moderate | Seats = live count, no reservation table (race); two seat sources reconciled only by max() | 36 |
| GAP-PC-002 | Moderate | Bid margin silently falls back to zero-margin at target_margin ≥100 (COST) / tm+load ≥100 (RATE) | 20 |
| GAP-CMP-001(threshold) | Low | Cert "expiring" threshold inconsistent (45 vs 30 days) | 24 |
| GAP-SET-001/002 | Moderate | Setting clamps hold on crafted POST; a switched-off module's routes guard server-side | 14,36 |

### Theme 10 — Robustness, heuristics & validation · **LOW–MODERATE**
| ID | Sev | Finding | Module |
|---|---|---|---|
| GAP-INQ-001 | Moderate | Inquiry capture has no validation (a blank inquiry is accepted) | 19 |
| GAP-VCH-002 | Moderate | `needs_receipt` never enforced; no negative/ceiling amount validation | 30 |
| GAP-LEAD-001 | Moderate | Bulk "mark lost" doesn't require a reason (weakens win/loss data) | 17 |
| GAP-HWP-002 / GAP-CMP-002(scope-match) | Low | Derivation & authorisation-scope matching are label/string-based (localized labels fail) | 21,24 |
| GAP-HIRE-002 | Low | Person identity heuristic (mobile/email) double-counts the pipeline | 35 |
| GAP-CONF-001 | Low | Undertaking lapse has no cron/alert (surfaces only on the screen) | 27 |
| GAP-EQ-001/003 · GAP-ID-002 · GAP-REC-001/004 | Low | Date-fallback-to-today; newest-vs-date-aware cert display; empty-expiry perpetual validity; opt-in site gate; comp-off expiry | 23,26,31 |

---

## C. Readiness by area

| Area | Modules | Readiness | Rationale |
|---|---|---|---|
| **Reporting spine (IDEMS)** | 06,07,08 | **High, conditional** | Lifecycle, vetting/approval, RN gating and immutability all present and mostly server-enforced; conditions: enforce-on-POST verification (IDEMS-002, RN-001, APPR-002) + no-self-approval (APPR-001) + seal strength (DC-001) |
| **Operational spine** | 04,05,18 | **High, conditional** | Allocation gates, scheduling, contract cover, close discipline solid; conditions: contract-gate/quantity-unit (CON-001/002), close idempotency (JOBS-002) |
| **Money** | 03,09,30,32,33 | **Medium** | Correct primitives, but the "same figure everywhere" promise is not fully met and two overhead models coexist; the highest-value cluster to close (PRF-001/002, OVH-001, INV-001/002) |
| **External / portals** | 10,11,27 | **Medium, gating** | Feature-complete but the two **Critical** isolation items (PORTAL-001, VP-001) gate release |
| **Quality controls** | 12,13,21,22,23,24,25,26,28,29 | **High, conditional** | Strong ISO-17020 coverage (NCR/CAPA close gates, calibration hard-block, hold-points, audit trail); conditions: independence id-based (CMP-001, AUD-001, IMP-001), evidence integrity (DC-001, AUD-002), at-rest privacy (ID-001) |
| **CRM / pipeline** | 15,16,17,19,20 | **Medium** | Works; scoping + validation + bid data-exposure gaps (PC-001, INQ-001, LEAD-003) |
| **Platform / admin** | 01,02,14,34,35,36 | **High** | Access clamps verified secure (MOD-02); settings/terminology/licensing solid; conditions: KPI-formula safety (DASH-002), seat enforce-on-edit (LIC-001) |

---

## D. Recommended close-out order (highest value first)

1. **Both Critical isolation items** (PORTAL-001, VP-001) — verify with crafted cross-tenant
   ids on every route + PDF endpoint. *Release-gating.*
2. **Money consistency cluster** (PRF-001/002, OVH-001, INV-001/002) — one reconciliation pass
   across quote→invoice→profit and the four profit screens. *Release-gating for a money
   product.*
3. **Enforce-on-POST sweep** (IDEMS-002, RN-001, APPR-002, CON-001, CLI-001, NCR-001) — a
   single crafted-request test matrix confirms every gate is server-side.
4. **Independence** (APPR-001/IMP-001; CMP-001/AUD-001 to id-based) — add the no-self-approval
   check and move name-based independence to user ids.
5. **Evidence integrity** (DC-001 keyed seal, AUD-002 anchor, AUD-003 seal QMS records).
6. **At-rest & injection** (ID-001 encryption, DASH-002 formula validation, REC-002 GPS,
   EQ-002 download auth).
7. **Scoping + idempotency + validation** (Themes 7–10) — mechanical, lower risk, do in a
   batch.

---

## E. What is already strong (do not regress)

- **Access model** (MOD-02): privilege-escalation clamps verified — a branch manager cannot
  mint a Master Admin; `ua()` is a memoized choke point; licence gating precedes the master
  bypass.
- **Non-overridable calibration block** (MOD-23) and **MAJOR→closed-CAPA** (MOD-12/13) — the
  two hardest ISO controls are enforced.
- **Vetting-before-approver + auto-forward + RN gating** (MOD-07/08) — the workflow overhaul
  (W1–W6) works live.
- **Tamper-evident hash chain + public verify** (MOD-28/29) — present and honest about
  unsealed legacy rows.
- **Terminology engine + module licensing** (MOD-14/36) — genuinely multi-TPIA, no agency
  hardcoding.
- **1532 automated tests passing** — a real regression net under all of the above.

---

## F. Overall recommendation

**Ship as a release candidate to a controlled pilot**, with the two **Critical** isolation
items and the **money-consistency cluster** closed or explicitly risk-accepted first, and the
enforce-on-POST / independence / evidence items scheduled immediately after. The product is
functionally complete across all 31 modules, the accreditation controls are present and
largely server-enforced, and the residual risk is well-localised and itemised — every finding
here carries a module, a severity, and a concrete verification. Re-run this assessment after
the close-out order in §D; the exit is met when Themes 1–5 are closed/accepted and the E2E
"same figure" checks (Prompt 4) pass to the paisa.

---

## Traceability

Every finding → its module report's §Q → the E2E thread(s) that exercise it (Prompt 4) → the
§D close-out order → this recommendation. Inventory v1.0 (scope), Governance v1.0 (method), the
31 module reports (evidence), Prompt 4 (integration), Prompt 5 (this — risk & readiness) form
the complete testing & documentation pack.
