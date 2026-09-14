# Milestone 12 — Test Results

**Run date:** 2026-09-14 · **Command:** `php tests/run.php`

---

## 1. Headline

| | M11 baseline | After M12 |
|---|---|---|
| Test files | 459 | **460** |
| Assertions passed | 8,060 | **8,148** |
| Failed | 0 | **0** |
| Skipped | 0 | **0** |
| Runtime | 102 s | **99 s** |

- **PHP:** 8.4.19 · **Harness:** `tests/run.php` — the real application on a throwaway SQLite database
- New assertions: **88** in `tests/test_m12_locked_module_ux.php`

---

## 2. MySQL / MariaDB — explicitly

**MySQL/MariaDB remains production-authoritative, and it was NOT executed.**
Verified rather than assumed: no `mysql`, `mysqld` or `mariadb` binary is
installed here.

**No claim of MySQL validation is made.** M12 introduces **no schema change and
no data change** — it adds a presentation layer over decisions already made.

---

## 3. The new suite — 88 assertions

| Group | Covers | Assertions |
|---|---|---|
| **A** | One state model, all eight states declared, and the presenter is **pure** — prints nothing, writes no session, stable when asked twice | 13 |
| **B** | Every state gets its **own** sentence: NOT_ENTITLED says subscription and never permission; NO_PERMISSION says permission and never subscription; TENANT_DISABLED reassures about the recorded work; LICENCE_BLOCKED says licence; INVALID_MODULE never shows the internal key | 15 |
| **C** | An available module is described as available — not falsely reported as refused | 9 |
| **D** | Administrator is offered `/subscription`; an ordinary user is offered no privileged control and told who to ask; **no price and no payment mechanism invented** | 7 |
| **E** | A master is still subject to entitlement, and the screen never says being an admin grants access | 6 |
| **F** | **The UI is not the boundary** — permission gate, route gate, export URL, entitlement engine and Marketplace all still refuse; and the gate never asks the presenter *whether* to allow | 7 |
| **G** | A blocked `fetch()` gets JSON with `reason`, 403 not 200, and the presenter never redirects | 7 |
| **H** | **One** locked screen reached from the gate's two refusal points; real `<h1>`, real link, existing panel style, state carried by words not colour, no new stylesheet or script, always a way back | 10 |
| **I** | Lifecycle ON → OFF → ON with rows counted — nothing deleted | 6 |
| **J** | S-1: Operations, Reporting and core available; HR, Sales, Money locked; entitled modules open normally | 8 |
| **K** | Cross-tenant, both directions | 3 |
| restore | teardown | 1 |

`RESULT: 88 passed, 0 failed`

---

## 4. Manual UX walkthrough (§26) — performed, and it found a defect

Each role and state was rendered through the **actual view** and the output read.

| Journey | State | What the screen said |
|---|---|---|
| **Workspace admin** clicks Recruitment, HR not bought | NOT_ENTITLED | 🔒 "People & hiring — not available… isn't included in your current subscription. You can add it to your plan, or contact your provider." → **Review your subscription** |
| **Ordinary user**, same click | NOT_ENTITLED | same sentence, but "Your workspace administrator can add it to your plan." → **no privileged control** |
| **Ordinary user**, HR *is* bought but they may not open it | NO_PERMISSION | 🔐 "restricted — You don't have permission to open People & hiring." |
| **Master**, Marketplace not bought | NOT_ENTITLED | 🔒 "Marketplace & Connect isn't included in your current subscription." → Review your subscription |
| **Admin**, company switched Money off itself | TENANT_DISABLED | 🔒 "Money has been switched off for this workspace. You can switch it back on — the work already recorded in it is untouched." |
| **Admin**, a module that IS available | ENTITLED | ✅ "available" |

**The walkthrough found a real defect.** The heading read *"not available"*
whenever the screen was rendered, taking its cue from the permission flag alone —
so an available module would have read as locked. The heading and the icon now
follow the **state**. The last row above is the fix verified.

---

## 5. An existing test changed — one, and it is the guard working

`tests/test_m10_master_entitlement.php` group D enumerates every `*_can()`
predicate and fails, **naming the offender**, if one opens for a master with
nothing entitled and is not on the classified baseline.

It fired on M12's new `access_can_subscribe()`. That is **exactly what it is
for** — a new gate must be classified before it is accepted.

**Classification: Category C (core).** `access_can_subscribe()` answers "may this
person act on a subscription", which is core billing administration, not a paid
module. A master holding only core admin **must** be able to reach it, or a
workspace could never buy anything. It was added to the baseline with that reason
recorded in the file.

The assertion itself was **not** weakened — it still fails for any *other*
unclassified opener. **No other existing test was modified, skipped or deleted.**

Two assertions of my own failed on first run: both matched text inside **comments**
(the word "redirect" in the header comment describing the old behaviour, and
`access_deny()` named in the gate's explanatory comment). A comment is not code —
**my assertions were corrected** to strip comments before matching, not the code.

---

## 6. Regression scope (§27)

| Area | Result |
|---|---|
| **Operations** (calls, jobs, vouchers, scheduling) | pass |
| **Reporting** (idems, templates, reviews) | pass |
| **Money** (books, invoices, tally, billable, receivables) | pass |
| **Sales / CRM** (quotes, leads, contracts) | pass |
| **Recruitment / HR** (requisitions, candidates, pipeline, careers) | pass |
| **Marketplace / Connect** | pass |
| Entitlement suites **M2–M11** | pass |
| Dashboard, portals, navigation, search | pass |
| Deploy verification | pass — checksums regenerated |
