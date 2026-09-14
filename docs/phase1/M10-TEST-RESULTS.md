# Milestone 10 — Test Results

**Run date:** 2026-09-14 · **Command:** `php tests/run.php`

---

## 1. Headline

| | M9 baseline | After M10 |
|---|---|---|
| Test files | 457 | **458** |
| Assertions passed | 7,907 | **8,000** |
| Failed | 0 | **0** |
| Skipped | 0 | **0** |
| Runtime | 98 s | **99 s** |

- **PHP:** 8.4.19 · **Harness:** `tests/run.php` — the real application on a throwaway SQLite database
- New assertions: **93**, all in `tests/test_m10_master_entitlement.php`
- **No existing test was modified, weakened, skipped or deleted.**

---

## 2. MySQL / MariaDB — explicitly

**MySQL/MariaDB remains production-authoritative, and it was NOT executed.**
Verified rather than assumed: no `mysql`, `mysqld` or `mariadb` binary is
installed here; `pdo_mysql` is loaded but has no server to reach.

**No claim of MySQL validation is made.** M10 introduces **no schema change** —
the changes are boolean checks in front of existing gate functions, nothing
engine-specific.

---

## 3. The new suite — 93 assertions

| Group | Covers | Assertions |
|---|---|---|
| **A** | All 11 corrected/hardened gates, each asserted **both ways** — opens for a master when its module is entitled, refuses when it is not | 22 |
| **B** | Every entitlement state as a master: ENTITLED, NOT_ENTITLED, TENANT_DISABLED, UNKNOWN, INVALID, LICENCE_BLOCKED (with core surviving), unowned module | 13 |
| **C** | Ordinary RBAC is not weakened — coordinator, inspector and signed-out cases | 8 |
| **D** | **The permanent probe** — every gate predicate in `lib/` called as a master with nothing entitled; no unclassified opener; the fixed gates never reappear | 13 |
| **E** | Core master functionality intact on the narrowest plan | 10 |
| **F** | S-1 as a signed-in master, including Marketplace both ways | 15 |
| **G** | Tenant isolation, both directions | 5 |
| **H** | The control install is never limited | 6 |
| restore | teardown | 1 |

`RESULT: 93 passed, 0 failed`

### Group D is the one that matters most

It is not a fixed list of things to check — it **re-runs the audit** on every
build. It enumerates every zero-argument gate predicate in `lib/`, signs in as a
master with nothing but core administration, calls them all, and fails if
anything opens that is not on the recorded 52-gate core baseline, naming the
offender.

A gate added in six months that opens for an unentitled master breaks the build
with its own name in the message. That is the only way an audit like this stays
done.

---

## 4. Both directions were proved

Every one of the 11 gates is asserted twice — ALLOW when its module is entitled,
DENY when it is not. An implementation that simply denied everything would fail
11 assertions in group A alone, plus groups C, E, F and H.

---

## 5. Mutation testing

Each guard was removed or weakened in turn and the suite re-run:

| Mutation | Result |
|---|---|
| `books_can()` module guard replaced with `return true` | **15 assertions fail** |
| `rating_can()` module guard removed | **3 fail** |
| `timesheet_can()` weakened back to a bare `is_master()` | **3 fail** |
| `ads_can_manage()` module guard removed | **4 fail** |
| `inspector_profile_can()` module guard removed | **3 fail** |
| all restored | **93 passed, 0 failed** |

Every guard is load-bearing.

---

## 6. RBAC was verified intact, not assumed

Tightening entitlement must not quietly loosen or tighten permissions. Asserted
with three different users:

| User | Holds | Money entitled | Result |
|---|---|---|---|
| COORDINATOR | `data.credit` by role | yes | **ALLOW** — ordinary RBAC still works |
| COORDINATOR | `data.credit` by role | no | **DENY** — entitlement binds non-masters too |
| INSPECTOR | neither finance permission | yes | **DENY** — the permission is still required |
| signed out | — | yes | **DENY** |

One test assumption of mine was wrong during this run: I expected a COORDINATOR
to be refused the books. They legitimately hold `data.credit`, so the correct
result is ALLOW when Money is entitled. **The test was corrected, not the code**
— and the corrected version is stronger, because it now proves entitlement
applies to non-masters as well as masters.

---

## 7. Regression scope

| Area | Result |
|---|---|
| Money (books, invoices, tally, billable events, receivables) | pass |
| Operations (jobs, calls, ratings, timesheets, scheduling) | pass |
| Reporting / idems | pass |
| Sales / CRM (quotes, leads, contracts, Ads Pro) | pass |
| Recruitment / HR (incl. inspector profiles) | pass |
| Marketplace / Connect | pass |
| Global search | pass |
| Dashboard, portals, cron | pass |
| Entitlement M2–M9 suites | pass |
| Permissions / no-lockout / dead-gate checks | pass |
| Deploy verification | pass — checksums regenerated |
