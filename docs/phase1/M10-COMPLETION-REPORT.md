# Milestone 10 — Completion Report

## Verdict: **PASS WITH DOCUMENTED LIMITATIONS**

Every acceptance criterion met. Fully green suite, no schema change, no RBAC
redesign, no cosmetic mass rewrite. Eight limitations recorded, of which L3 (non-
module permissions) is the largest remaining structural item in Phase 1.

---

## 1. Inventory and classification

A fresh inventory was taken from the branch after M9 — **400 `is_master()` sites
across 133 files**, 105 `is_master_of()` sites, plus the equivalents that contain
neither word (`is_admin_level()`, `is_coordinator_level()`).

Rather than read 400 sites and hope, the question was asked empirically: sign in
as a **master with nothing entitled but core administration**, call **every
zero-argument gate predicate in `lib/`**, and see which still say yes. 95 gates
opened under S-1; 56 opened on core administration alone. Each was traced —
route → handler → gate → access module → product module — and classified.

| Category | Meaning | Outcome |
|---|---|---|
| **A** | Genuine paid-module bypass | **8 sites / 7 gates — all corrected** |
| **B** | Entitlement established earlier (route gate, `pcan()`, `connect_enabled()`) | the large majority — no change |
| **C** | Legitimately core | 52 gates — recorded as the probe baseline |
| **D** | Not an authorisation decision (audit actors, labels, UI copy) | no change |
| **E** | Ambiguous | **none left** |

**8 of 400 sites changed.** No cosmetic rewrite: reordering
`is_master() || can(...)` was explicitly avoided, because M5 established the
reorder achieves nothing.

---

## 2. The defects corrected

| # | Gate | Module | Why it was reachable |
|---|---|---|---|
| A1–A3 | `books_can()`, `books_can_issue()`, `books_can_cancel()` | **Money** | `finance.reconcile` / `data.credit` / bare master — **not one module question** — and `books_can()` is what the **ungated global search** asks before offering an Invoices section |
| A4 | `rating_can()` | **Operations** | ungated `ratings` routes |
| A5 | `timesheet_can()` | **HR / Operations** | ungated `timesheets` routes |
| A6 | `inspector_profile_can()` | **HR / Operations** | ungated `inspector-profile` route |
| A7 | `ads_can_manage()` | **Sales** | ungated `adspro*` routes — M8 gated the nightly lead sync, the screen was left open |
| A8 | global search, contracts section | **Sales / core** | bare master on the ungated `/search` |
| A9 | `ops_books_bridge()` | **Money** | ungated `books-bridge*` — M8 gated the cron drain, the screen was left open |

**The most serious is A1–A3.** A paid module's records were readable through an
ungated, cross-module surface — the global search — by a master or by anyone
holding a finance permission, in a workspace that had never bought Money.

**A7 and A9 share a shape worth remembering:** M8 gated the nightly job, and the
screen that performs the same operation stayed open. One operation, two doors,
disagreeing.

Four further gates (`tally_can`, `tally_can_manage`, `billable_can`,
`billable_can_manage`) were hardened as defence in depth. They were **Category B
and not exploitable** — stated plainly so the finding count is not inflated.

---

## 3. Security

| Property | Evidence |
|---|---|
| **Master ≠ entitlement** | Every corrected gate: ALLOW entitled, DENY not-entitled, blocked, tenant-disabled, unknown, invalid |
| **HR / Sales / Money / Reporting / Marketplace** | Each asserted individually under S-1 as a master |
| **Direct URL / POST / AJAX** | The corrected gates run inside `ops_require()` at handler entry, on every method — the endpoint cannot be reached another way |
| **Core preserved** | Seven core access modules open on the narrowest plan; administration stays CORE |
| **RBAC not weakened** | Coordinator ALLOW when entitled, DENY when not; inspector DENY even when entitled; signed-out DENY |
| **Tenant isolation** | Both directions across a connection switch, no cache reload called |
| **Control install** | Never limited — all six corrected gates stay open to the platform owner |

---

## 4. Testing

| | Result |
|---|---|
| **Focused M10** | **93 passed, 0 failed** |
| **Full regression** | **8,000 passed · 0 failed · 0 skipped · 458 files · 99 s · PHP 8.4.19** (M9 baseline 7,907) |
| **S-1 as a master** | Operations **ALLOW**, Reporting **ALLOW**, HR **DENY**, Sales **DENY**, Money **DENY**; Marketplace **DENY** unentitled and **ALLOW** once entitled |
| **Cross-tenant** | Pass, both directions |
| **Mutation** | 15 / 3 / 3 / 4 / 3 assertions fail when each guard is removed or weakened |
| **MySQL/MariaDB** | **NOT EXECUTED** — no server installed (verified). No claim of validation |
| **Existing tests** | **None modified, weakened, skipped or deleted** |

### The audit is now a test, not a document

Group D re-runs the whole probe on every build: it enumerates every gate
predicate in `lib/`, calls each as a master with nothing entitled, and fails —
**naming the offender** — if anything opens that is not on the recorded 52-gate
core baseline. A gate added later that leaks a paid module breaks the build with
its own name in the message.

---

## 5. One test assumption I got wrong

I expected a COORDINATOR to be refused the books. They legitimately hold
`data.credit` by role, so the correct answer is ALLOW when Money is entitled.
**The test was corrected, not the code** — and the corrected version is stronger,
because it now proves entitlement binds non-masters as well as masters.

---

## 6. Files changed

`lib/books.php`, `lib/rating.php`, `lib/timesheet.php`,
`lib/inspectorprofile.php`, `lib/adspro.php`, `lib/booksbridge.php`,
`lib/search.php`, `lib/tally.php`, `lib/billable.php` — **nine source files** —
plus the new test and the regenerated `deploy-check.php`.

No business logic changed in Operations, Quality, Reporting, Money, Recruitment,
Marketplace or the Dashboard. No new permission, role default, helper or schema.

---

## 7. Acceptance criteria

| Criterion | Result |
|---|---|
| Current master-bypass inventory completed | **PASS** — 400 sites, fresh from the branch |
| Every occurrence classified | **PASS** — A/B/C/D, none left ambiguous |
| Every genuine paid-module bypass corrected | **PASS** — 8 |
| No cosmetic mass rewrite | **PASS** — 8 of 400 changed |
| Existing module-aware master architecture reused | **PASS** — `is_master_of()`, `licence_module_live()` |
| Master cannot bypass SaaS entitlement | **PASS** |
| Core master functionality intact | **PASS** |
| HR / Sales / Money / Reporting / Marketplace respected | **PASS** — each asserted |
| Direct routes, POST/AJAX protected | **PASS** |
| Cross-tenant isolation | **PASS** |
| S-1 passes | **PASS** |
| Mutation tests prove the guards | **PASS** |
| Full regression passes | **PASS** — 8,000/0 |
| No schema change | **PASS** |
| MySQL/MariaDB status reported | **PASS** — not executed |
| Documentation complete | **PASS** — five documents |
| Known limitations documented | **PASS** — eight |
| M11 NOT started | **PASS** |
| Phase 2 NOT started | **PASS** |

---

## 8. Carried forward

**L3** — non-module permissions (`finance.reconcile`, `data.credit`,
`settings.manage`, `dash.*`) are still RBAC-only. M10 closed every instance where
one stood alone in front of a paid module on an ungated route; the general fix is
registry work and is the largest remaining structural item from Phase 1.

**L6** — a full `is_admin_level()` sweep was not done; it is out of this
milestone's stated scope and deserves its own pass.

**M9's L1 still applies before any deployment:** hosted workspaces need `connect`
added to their entitlement, or the marketplace switches off for them.

---

**STOP. M10 ends here.** M11–M16 and Phase 2 have not been started.
