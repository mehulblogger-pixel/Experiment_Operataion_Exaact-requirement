# Milestone 8 — Test Results

**Run date:** 2026-09-14 · **Command:** `php tests/run.php`

---

## 1. Headline

| | M7 baseline | After M8 |
|---|---|---|
| Test files | 455 | **456** |
| Assertions passed | 7,675 | **7,800** |
| Failed | 0 | **0** |
| Skipped | 0 | **0** |
| Runtime | 98 s | **101 s** |

- **PHP:** 8.4.19
- **Harness:** `tests/run.php` — boots the real application on a throwaway SQLite database
- New assertions: **125**, all in `tests/test_m8_public_background_entitlement.php`
- **No existing test was modified, weakened, skipped or deleted.**

---

## 2. MySQL / MariaDB — explicitly

**MySQL/MariaDB remains the authoritative production database, and it was NOT
executed.**

Verified, not assumed: no `mysql`, `mysqld` or `mariadb` binary is installed in
this environment. `pdo_mysql` is loaded but there is no server for it to reach.

**No claim of MySQL validation is made.** SQLite results are supplementary only.
The database architecture was not changed for M8, and M8 adds **no SQL, no schema
change and no new table, column or index** — the three changes are boolean
authorisation decisions taken before existing work runs.

---

## 3. The new suite — 125 assertions

| Group | Covers | Assertions |
|---|---|---|
| **A** | Public careers page — HR entitled + on, entitled + off (with HR proven still ENTITLED), unentitled with the flag left on, blank entitlement, tenant-disabled | 7 |
| **B** | The **application submission** — direct POST refused with nothing written, entitled POST creating exactly one candidate, refusal leaking no internals, forged parameters | 11 |
| **C** | The nightly run — 23 paid steps each gated on the right module (46 assertions), the HR/Sales/Operations opening steps, 6 core steps proven **un**gated, one helper, no blanket exit | 56 |
| **D** | The advertising sync gated on Sales, asked before the feature switch | 2 |
| **E** | Every background module in every entitlement state — entitled, not entitled, blank, tenant-disabled, unowned, core | 19 |
| **F** | There is no cron = master — master denied, and the same answer with nobody signed in | 5 |
| **G** | Tenant isolation across a background switch, **both directions** | 5 |
| **H** | How cron resolves a workspace; the control install is never limited | 6 |
| **I** | S-1 through the public and background paths | 12 |
| restore | teardown | 2 |

`RESULT: 125 passed, 0 failed`

### The §20 requirements, point by point

| Requirement | Result |
|---|---|
| Public route — entitled HR | pass |
| Public route — unentitled HR | pass |
| HR licence blocked / tenant disabled | pass |
| Unknown module | pass — fails closed |
| Direct URL | pass |
| Direct POST | pass — nothing written |
| Public application / CV upload / candidate creation | pass — candidate rows counted before and after |
| Careers: HR on + Careers on | works |
| Careers: HR on + Careers off | blocked, **and HR stays ENTITLED** |
| Careers: HR off + Careers flag on | blocked |
| Careers: HR off + direct URL | blocked |
| Careers: HR off + direct application POST | blocked |
| Background job entitled → runs | pass |
| Background job unentitled / blocked / disabled / unknown → no paid work | pass |
| Multi-tenant A ON / B OFF **and** A OFF / B ON in one process | pass |
| No entitlement cache leakage | pass |

---

## 4. Both directions were proved

Every paid path is asserted twice — refused without the module and working with
it. An implementation that simply blocked everything would fail group B's ALLOW
assertions (a real candidate created through the normal pipeline) and group E's
entitled cases.

---

## 5. Mutation testing

Each guard was removed in turn and the suite re-run:

| Mutation | Result |
|---|---|
| HR gate removed from the `appr_tick` cron step | **1 assertion fails** |
| Entitlement check removed from `careers_apply()` | **7 assertions fail** |
| Sales gate removed from `cron_ads.php` | **1 assertion fails** |
| all restored | **125 passed, 0 failed** |

---

## 6. End-to-end smoke test of the nightly run

`cron.php` was executed against a throwaway database, twice:

- **Unrestricted** — every step ran and reported normally (jobs, calibration,
  authorisations, quote follow-ups, quotation expiry, overdue invoices, IDEMS
  SLA, MIS digest, NCR/CAPA/complaints, vendor approvals, analytics, site
  documents, competence, licence, audit, controlled documents, integrity).
- **With Sales, Money, HR and Reporting switched off** — those steps were skipped
  and the run ended with
  `Skipped — not enabled for this workspace: Sales & CRM, People & hiring, Money,
  Inspection reporting`, while Operations and every core step continued.

This is the §10 requirement demonstrated rather than asserted: one lapsed module
does not take the run down.

---

## 7. Regression scope

| Area | Result |
|---|---|
| Careers and recruitment intake | pass — 19 assertions, unchanged |
| Recruitment / HR (requisitions, candidates, approvals, exports) | pass |
| Operations (calls, jobs, vouchers, scheduling, competence, equipment) | pass |
| Reporting / idems | pass |
| Money (invoicing, receivables, billable events) | pass |
| Sales / CRM (quotes, leads, contracts) | pass |
| Client and vendor portals | pass |
| MIS, TAPI analytics | pass |
| Entitlement M2–M7 suites | pass |
| Deploy verification | pass — checksums regenerated |
