# Milestone 11 — Test Results

**Run date:** 2026-09-14 · **Command:** `php tests/run.php`

---

## 1. Headline

| | M10 baseline | After M11 |
|---|---|---|
| Test files | 458 | **459** |
| Assertions passed | 8,000 | **8,060** |
| Failed | 0 | **0** |
| Skipped | 0 | **0** |
| Runtime | 99 s | **102 s** |

- **PHP:** 8.4.19 · **Harness:** `tests/run.php` — the real application on a throwaway SQLite database
- New assertions: **58** in `tests/test_m11_ux_consolidation.php`

---

## 2. MySQL / MariaDB — explicitly

**MySQL/MariaDB remains production-authoritative, and it was NOT executed.**
Verified rather than assumed: no `mysql`, `mysqld` or `mariadb` binary is
installed here.

**No claim of MySQL validation is made.** M11 introduces **no schema change, no
migration and no data change of any kind** — it changes navigation definitions,
one resolver, one view and one peek rule.

---

## 3. The new suite — 58 assertions

| Group | Covers | Assertions |
|---|---|---|
| **A** | One landing resolver: every answer is a declared mode, carries a readable reason, prints nothing, writes no session, and is stable when asked twice | 7 |
| **B** | Each branch of the cascade preserved — recruitment-only lands on the recruitment home, a company with Operations keeps the dashboard, unfinished setup orients first | 5 |
| **C** | **"Home" reaches Home** — the once-per-session flag now covers both orientation paths, set in exactly one place, and the old unconditional redirect is gone | 4 |
| **D** | No destination is offered by two areas; the `/templates` router is never offered bare; the libraries are still reachable | 4 |
| **E** | The three address-only screens now have navigation entries, in the right areas | 6 |
| **F** | Entitlement decides what the rail offers — unentitled areas hidden, entitled shown — **and the server still refuses regardless** | 8 |
| **F2** | Peek and handler never disagree about Marketplace, in both directions, with no other module caught by the rule | 9 |
| **G** | A refusal says which kind of "no" it is; no database text reaches a user | 3 |
| **H** | Consolidation removed no screen and no action — seven functions and the secondary contract door all still present | 10 |
| restore | teardown | 2 |

`RESULT: 58 passed, 0 failed`

---

## 4. Manual walkthrough (§30) — performed, not skipped

Driven through the real application with a signed-in master and a real tenant
context. This is what found C6, which no test was looking for.

### Corporate Recruitment — HR only
```
land            -> recruitment   (Operations is not licensed and People & hiring is)
recruitment      open      requisitions     open      candidates       open
candidate-pool   open      recruit-config   open      careers-admin    open
recruit-export   open
```

### Operations + Reporting (S-1)
```
land            -> dashboard     (the default home)
calls  open   jobs  open   documents  open   report-templates  open
schedule  open   vouchers  open

areas hidden from the rail:   money ✓   marketplace ✓   sales ✓
typed addresses refused:      invoices ✓  quotes ✓  recruit-export ✓  connect-requirements ✓*
```
\* `connect-requirements` **was** reported open by the gate's peek before C6 —
the handler always refused it. Fixed during the walkthrough; both now agree.

### Marketplace, both states
```
unentitled: rail hidden    gate denied
entitled:   rail offered   gate open
```

---

## 5. Existing tests changed — two, both strengthened

Neither was weakened. Both pinned a **literal label** that legitimately changed
when the templates tiles were given explicit destinations.

### `tests/test_simplify_reportcfg.php`
Asserted the source contained `'Document templates', '/templates'` inside the
Admin case. The assertion is about **where the tile lives**, not what it is
called. Rewritten to assert the tile is absent from Reporting and present in
Admin at `'/templates?kind=report'`, plus a new assertion that it names the
library it opens. Move it back to Reporting and it still fails.

### `tests/test_admin_area_honesty.php`
Asserted `(can('idems.type.manage') || is_master()), '📝', 'Document templates'`.
The **point** of that assertion is the **gate** — Admin must grant via
`idems.type.manage` or master and **not** via `crm.template.manage`. The gate is
unchanged; only the label moved. Updated, **and made stricter**: a second
assertion now pins the absence of a `can('crm.template.manage')` grant in the
Admin case.

**No other existing test was modified, weakened, skipped or deleted.**

One assertion of my own was wrong on first run — it searched for the bare string
`crm.template.manage`, which also appears in a *comment* recording that the grant
moved to Sales. A comment is not a gate. **My assertion was corrected**, not the
code.

---

## 6. Regression scope (§29)

| Area | Result |
|---|---|
| **Operations** (calls, jobs, vouchers, scheduling, TOSRM, workforce) | pass |
| **Reporting** (idems, templates, endorsements, report reviews) | pass |
| **Recruitment / HR** (requisitions, candidates, pipeline, approvals, careers) | pass |
| **Marketplace / Connect** | pass |
| **Money** (books, invoices, tally, billable, receivables) | pass |
| **Sales / CRM** (quotes, leads, contracts, templates) | pass |
| Navigation, areas, workspace, cockpit, onboarding | pass |
| Dashboard, portals, search | pass |
| Entitlement M2–M10 suites | pass |
| Deploy verification | pass — checksums regenerated |
