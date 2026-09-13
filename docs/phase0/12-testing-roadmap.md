# 12 — DETAILED TESTING ROADMAP
Covers deliverable **30**. Implements the brief's §24–§34.

**Baseline to beat (measured at Phase 0): 444 files / 6948 assertions / 0 failures / 81.83 s.**

**Production database: MySQL/MariaDB. Existing automated regression harness: SQLite.** The current automated suite therefore does not fully exercise the production MySQL/MariaDB database engine. SQLite remains acceptable as a fast supplementary layer; it is never the production database.

---

## 1. The ten required levels, mapped to what exists

| Level | Required | Exists today | Gap |
|---|---|---|---|
| 1 Static | syntax, lint, includes, routes | `php -l` used ad hoc | **no CI gate** |
| 2 Unit | business logic | strong (6948 assertions) | fine |
| 3 Database | CRUD, constraints, tenant isolation, migrations, rollback, idempotency | partial | **production MySQL/MariaDB engine not exercised; no rollback exists to test; no cross-tenant test** |
| 4 API | every endpoint | N/A — no tenant API | genuinely N/A |
| 5 UI | every screen/field/state | none in this suite (separate Playwright crawl, not run) | **not integrated** |
| 6 Workflow | every legal transition | only Connect + Offer guard transitions | **requisition/candidate unguarded** |
| 7 Negative | invalid actions | scattered | **no matrix** |
| 8 Security | authn, authz, tenant isolation, direct URL, API bypass, **entitlement bypass** | 24 files/311 assertions; entitlement = **22 assertions, none end-to-end** | **the critical gap** |
| 9 Cross-module regression | affected modules | implicit (one shared suite) | **no per-change impact mapping** |
| 10 End-to-end | real customer journeys | none | **absent** |

---

## 2. Mandatory new test infrastructure (delivered inside PHASE 1)

**T-1 MySQL/MariaDB suite run.** Production is MySQL/MariaDB; the existing harness pins SQLite (`tests/bootstrap.php`). Two production defects this session (`ON CONFLICT` → SQLSTATE 1064; `||` → `"0"`) were invisible to 6948 green assertions. **Until this exists, no green result is trustworthy evidence for production.**

**T-2 End-to-end entitlement denial.** No test today drives a real request through the router for an un-entitled module and asserts denial. Required per surface: menu, route, **direct URL**, form POST, AJAX, public route, cron, export, report, notification, deep link.

**T-3 Cross-tenant isolation.** Tenant A must not reach Tenant B's entitlements or data. Isolation is by DSN, so the test must target *resolution* (session key, Host header), which is where the residual risk is.

**T-4 Self-skip guards must fail.** 51 guards convert a missing function into a green tick. One fired in this run — the risk is latent, not theoretical.

**T-5 Test isolation.** 444 files share one mutating SQLite database in one process. Add per-file reset or transaction wrapping.

---

## 3. Mandatory scenarios from the brief

### S-1 Operations-only customer (brief §27) — **regression gate for every phase**
New tenant; activate Operations + Reporting only; **do not** activate Recruitment. Verify: onboarding, org setup, users, Operations, Reporting, dashboard, reports all work; Recruitment is inaccessible by menu **and** direct URL **and** public careers route; no Recruitment configuration is forced; no Recruitment dependency breaks Operations.

*This scenario is the single best protection against the brief's §39 no-break rule, because recruitment CRUD currently lives inside `lib/ops.php` (`09-…` §1).*

### S-2 Corporate HR without a career page (brief §28)
Requestor → Hiring Request → Approval → Recruiter assignment → manual CV intake → Candidate → Screening → Interview → Selection → Offer → Appointment → Joining → Confirmation. Verify target dates and recruiter KPIs. *Blocked until Phases 4 and 6 (Multi-Source Fulfilment, Person/Organisation/Marketplace Convergence).*

### S-3 Corporate HR with career page (brief §29)
Repeat S-2 with the career page on; verify the applicant lands in the **same** candidate engine. *Already true today (`lib/careers.php:65-139`) — lock it with a test. Career Page remains OPTIONAL: manual CV intake must work fully without it.*

### S-4 Agency (brief §30)
Client → Requirement → Recruiter → Candidate → Submission → Client interview → Selection → Placement; verify recruiter KPI and commercial outcome.

### S-5 Multi-source (brief §31)
One requirement for 20; allocate internal/direct/agency/supplier/marketplace; verify total, allocated, remaining, duplicate prevention, source traceability, progress, closure. *Blocked until Phase 4 — Multi-Source Fulfilment. **Today this fails at the first placement** (`lib/ops.php:5091`).*

### S-6 TPIA (brief §32)
Project → Workforce requirement → multi-source → verification → mobilisation → deployment → Operations → Quality → Reporting → Money; verify **one person identity persists throughout**. *Blocked until Phases 5 and 7.*

### S-7 Entitlement lifecycle (brief §26)
Paid/active → usable. Unpaid → refused on all surfaces. Upgrade (Ops-only buys Recruitment) → becomes available, **existing Operations data intact**. Downgrade → inaccessible per policy, **historical records preserved** (today verified: no deletion path exists). Re-activation → data returns.

---

## 4. Negative test matrix (brief §34)
Missing required field · invalid value · duplicate candidate / person / organisation / requirement · invalid date · expired date · wrong status · forbidden transition · insufficient permission · **inactive module** · **unpaid module** · **cross-tenant access** · over-allocation · partial fulfilment · candidate withdrawal · interview cancellation · offer rejection · joining failure · recruiter reassignment · requirement reassignment · supplier replacement · requirement reopening · archive/deactivate · concurrent update.

Each becomes a named test with an ID, carried in the phase completion record.

---

## 5. Manual test evidence format (brief §33)
Manual scenarios must start from a **genuinely new tenant**, not seeded data, and record:

`Test ID · Persona · Preconditions · Login · Starting state · Screen · Field · Input · Action · Expected · Actual · Pass/Fail · Evidence · Defect ID`

---

## 6. Per-phase testing procedure (brief §24)
1 define acceptance criteria → 2 test cases → 3 negative → 4 edge → 5 regression → 6 cross-module → 7 implement → 8 automated run (**MySQL/MariaDB authoritative; SQLite supplementary**) → 9 manual → 10 fix → 11 re-run → 12 UAT → 13 record evidence → **14 only then mark COMPLETED**.

**A phase may never be marked COMPLETED on a quoted test figure. Numbers must be re-measured and restated.**
