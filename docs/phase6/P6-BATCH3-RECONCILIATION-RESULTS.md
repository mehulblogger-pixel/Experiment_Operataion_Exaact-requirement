# Phase 6 · Batch 3 — Reconciliation results

*Does the system's own account of itself agree with what is actually in the
database?* Every figure below was read back from the database after the batch,
on both engines.

---

## 1 · What it reports vs what it wrote

| Claim | Reconciled against | Result |
|---|---|---|
| "Your account is ready." | one party + one marketplace organisation + one **active** portal account exist | **agrees** (A1 · A2 · B1–B3) |
| A refused registration | **no** party, **no** organisation, **no** account | **agrees** (A4 · A5 · A8 · A9) |
| A refused lead conversion | no partner created, **and the lead is still OPEN** | **agrees** (E7d · E7e) |
| "attached to the organisation that exists" | the returned id **is** that organisation, and no second record exists | **agrees** (E5 · E5b) |
| A refused portal invitation | no `client_users` row | **agrees** (K2 · K4) |
| The first registration in a fresh process | what it reported and what it wrote **agree** | **agrees** (C4d) — and **disagreed** before the fix |

**C4d is the one that matters.** Before the MariaDB fix, the route reported
failure for an account it had committed. A count that agrees with a *return
code* would have missed it; a count that agrees with the *database* did not.

## 2 · Counts across the transaction boundary

| Situation | party | cx_organisation | client_user | Expected |
|---|---|---|---|---|
| Successful registration | 1 | 1 | 1 | 1 / 1 / 1 ✓ |
| Refused — duplicate | 0 | 0 | 0 | 0 / 0 / 0 ✓ |
| Three at once, same company | 1 | 1 | 1 | 1 / 1 / 1 ✓ |
| Failure part-way (forced) | 0 | 0 | 0 | 0 / 0 / 0 ✓ |
| Borrowed transaction, failure | caller's rollback decides; the callee commits nothing | ✓ |

## 3 · The organisation register, before and after

Nothing was merged, deleted or rewritten, so the counts are **unchanged by
design**. What changed is that the states are now *visible*:

| State | Before | After |
|---|---|---|
| Duplicate organisations by tax identifier | present, invisible | **reported** (`PARTNER_DUPLICATE_TAXID`) |
| Organisations sharing a name | present, invisible | **reported as possible**, never as proof |
| Marketplace organisations with no link to the register | present, invisible | **reported as unmapped**, marked normal for a pending application |
| Dangling references | present, invisible | **reported** |
| Agencies that may be a company already on file | present, invisible | **reported as a suggestion**, never inferred |
| Two agency contracts with one company | present, invisible | **not** reported — legitimate (J5) |
| Contacts duplicated, or two primaries | present, invisible | **reported** |
| Two active accounts on one address | present, invisible | **reported**, and that workspace's index is held back rather than forcing the data |

## 4 · Modules re-checked after the change

Regression across the whole suite on **both** engines, not an assertion that
they were "not touched":

| Area | SQLite | MariaDB |
|---|---|---|
| Operations (calls, jobs, visits, vouchers) | green | green |
| Reporting & dashboards | green | green |
| Money (invoices, bills, costing, allocation) | green | green |
| Workforce & inspectors | green | green |
| Marketplace / Connect (organisations, capabilities, professionals) | green | green |
| Phase 4 allocation | green | green |
| Phase 5 KPI & seats | green | green |
| Recruitment, and recruitment conversion | green | green |
| Identity ledger (Batch 1 · Batch 2) | green | green |
| Client portal · vendor portal | green | green |
| CRM — leads, inquiries, quotations | green | green |
| APIs & exports | green | green |
| **Totals** | **12 615 / 0** | **12 619 / 2** |

**The MariaDB totals are not clean.** The two failures are Batch 3's own C8/C9
(the borrowed-transaction contract under a stale migration guard) — reported, not
repaired, and not weakened. Every other protected area is clean on both engines.

Three regressions were found along the way and fixed at the cause, not by
weakening a test: the `portal_invite()` authority (13 assertions), a test with
no authorised actor (1), and the MariaDB implicit-commit defect (1). All are
described in the test results and the adversarial audit.

## 5 · Invariants touched

| Invariant | Before Batch 3 | After Batch 3 | Evidence |
|---|---|---|---|
| **I34** organisation identity is not duplicated merely because its role differs | PARTIAL — the detector existed but `/join` and `agencies` never called it | **PARTIAL**, substantially closed — every creating door now asks | A1–A10 · E4–E8 · M1–M12 |
| **I35** every organisation representation resolves to the spine | **VIOLATED** — `agencies` had no cross-reference at all | **PARTIAL** — the cross-reference exists (**R4 closed**) and every unresolved row is reported | D1–D12 · J-series |
| **I31** duplicates detected where they cannot be prevented | PARTIAL — nothing for contacts or organisations | **PARTIAL**, wider — contacts and organisations now reported (**R30 surfaced**) | J1–J5 |
| **I25** a record id is never proof of authorisation | HOLDS on the identity routes | **HOLDS**, now on the organisation routes too | K3 · K4 · mutant M19 |
| **I27** protection belongs to the action, not the screen | HOLDS on the identity routes | **HOLDS**, now for `portal_invite()` — with **both** its legitimate authorities | K1 · K2 · mutants M17 M18 |
| **I41** an audit failure never fails a business write | HOLDS | **HOLDS** | the audit is outside the transaction and silent on failure |

**The account boundary** is not one of I1–I42. It was *undefined* before this
batch and is now **established from the code and enforced there**: one active
account per address, per account list. Proved by H1–H4.

The register in `P6-BUSINESS-INVARIANTS.md` is updated in the same commit as the
code, per `CLAUDE.md`. **Q1–Q18 remain open** and no PARTIAL from Batch 1 or
Batch 2 was upgraded on the strength of this batch.
