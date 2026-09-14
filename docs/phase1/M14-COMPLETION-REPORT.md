# Milestone 14 — Completion Report
## Object-Level Authorization, IDOR & Data-Scope Attack

**Verdict: PASS WITH DOCUMENTED LIMITATIONS**
**Suite: 8,245 passed, 0 failed** · M14 focused suite: 46 passed, 0 failed

---

### 1. Executive summary

M13 left an asymmetry on the record: 72 list-level scope checks against 9
object-level ones. M14 attacked that gap. **Seven object-level vulnerabilities
were found, proven by execution, and fixed** — including cross-branch **delete**,
**edit**, **stage-change** and **full document download**.

The objective was never to make the numbers match. It was to establish why every
important object is reachable and who may reach it. Four registers that *looked*
vulnerable were proven **not** to be and deliberately left alone.

### 2. Attack methodology

For every object: does the register's own **list** hide this record from this
user while the **detail** serves it? A disagreement between two doors is a defect
by the application's own standard; agreement means the register is not
branch-scoped by design, and changing it would hide data users are meant to see.

The attacker was built to isolate one variable: **every permission in the
product, and the wrong branch.** Because the claim under test is that permission
is not scope.

### 3. Object inventory

1,290 by-id statements, 189 tables, classified by scope dimension: **166 tables
carry no scope column** (configuration — global by design); 43 branch-scoped
registers are reachable by id. Those 43 were the target.

### 4. Confirmed vulnerabilities — all fixed

| # | Object | Proven attack | Severity |
|---|---|---|---|
| **O1** | Quotation | Opened another branch's quote by id — customer, subject, commercial terms; PDF too | **High** |
| **O2** | Opportunity | Opened, **edited**, **stage-changed**, **DELETED** | **High** |
| **O3** | Lead | Opened, **edited**, **DELETED** | **High** |
| **O4** | Lead document | Another branch's file **downloaded in full** | **High** |
| **O5** | Requisition | Opened by id, with its candidates | Medium |
| **O6** | Complaint | Opened by id | Medium |
| **O7** | Receipt | Payer and amount opened by id | Medium |

Proof (verbatim): `lead-delete → *** OBJECT DELETED ***`,
`opportunity-edit → DIFF={"name":"MARKEROPPNAME -> PWNED"}`,
`lead-file → MARKERS=["MARKERFILEBODY"]`.

### 5. Fix applied

**One gate per module, not one per route.** Thirty-five routes were reachable;
guarding each would be thirty-five chances to forget one. The guard went where
the module is entered — the same shape `ops_module_gate()` already has for
entitlement. Three route families name a *child* id (an attached file, an
approval step) and are resolved to their parent, since a document is as
confidential as the record it is filed against.

**One new helper: `scope_office_allows()`** — the object-level twin of
`scope_office_clause()`, which had none. The codebase has two list rules that
disagree about what a *missing* branch means; borrowing the wrong one would have
silently hidden **unassigned** records that every branch is meant to see. Tested
explicitly.

### 6. Regression tests added

`phpapp/tests/test_m14_object_authorization.php` — 46 assertions. Every fix was
reverted in isolation and confirmed to break the suite. **No existing test
weakened, skipped or deleted.** The test removes every row it inserts.

### 7. Objects tested and passed

Calls, jobs, invoices, vouchers, CAPA (already guarded, re-attacked); the seven
fixed above; exports and prints (`quote-pdf`, `invoice-print`, `voucher-print`).

### 8. Classified, not fixed — and why

**Candidates** — the list shows the same record; no disagreement, not
branch-scoped by design. **Project costings, internal audits, risks, samples** —
their lists carry no scope clause at all; gating the detail would hide records
the list shows. Project costings hold commercial rates, so this is **raised for
architectural review** (L2) rather than silently passed or unilaterally changed.

### 9. Cross-tenant, cross-branch, role, master

**Cross-tenant: refused in both directions**, by two independent barriers
(separate database per tenant; M13 workspace binding). **Cross-branch: refused**
for read, edit, delete, state-change, download and export. **Roles:** full
permissions plus wrong branch is refused; the owning branch keeps full use.
**Master:** crosses branch scope by architecture (ALL-scope, documented), still
tenant-bound by M13.

### 10. Negative testing

Eleven malformed identifiers — zero, negative, 20-digit, nonexistent, `7abc`,
empty, array, `1 OR 1=1`, padded — all reach a clean decision. No PHP warning,
no SQL error, no partial mutation.

### 11. M13 and S-1 regression

**M13 intact** — all 51 assertions pass inside the full run. **S-1 intact** —
Operations and Reporting work; HR, Sales, Money and Marketplace still refused
when unentitled.

### 12. Full regression

**8,245 passed, 0 failed, 0 skipped** · 462 files · PHP 8.4.19.
(8,199 before M14 + 46 new.)

### 13. MySQL / MariaDB

**NOT EXECUTED — environment unavailable.** No binary, port 3306 closed.
`pdo_mysql` loaded but no server. **No MySQL result is claimed.**

### 14. Files changed

`lib/access.php`, `lib/leads.php`, `lib/opportunities.php`, `lib/complaints.php`,
`lib/crm.php`, `lib/ops.php`, `lib/booksui.php` — **122 insertions, 0 deletions**.
Plus `deploy-check.php` regenerated and the new test file.

**No schema change. No data touched.** No existing authorization weakened, no
second authorization system, no RBAC redesign, no mass edit of by-id queries.

### 15. Remaining attack surface / limitations

**L1** coverage is high-value-first, not exhaustive — the long tail of
parent-guarded child records is untested and **not described as secure** ·
**L2** four registers are branch-global by their own list; project costings
raised for review · **L3** candidates tenant-wide by design · **L4** master
crosses branch scope by design · **L5** MySQL not exercised · **L6** no
transport-layer testing.

### 16. Final verdict

**PASS WITH DOCUMENTED LIMITATIONS.** No demonstrated critical or high-risk IDOR
remains. Cross-tenant and cross-branch access are denied for read, mutation,
deletion, approval, export and download. Remaining surface is classified, not
assumed.
