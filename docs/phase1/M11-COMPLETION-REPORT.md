# Milestone 11 — Completion Report

## Verdict: **PASS WITH DOCUMENTED LIMITATIONS**

Fully green suite, no schema change, no data change, nothing deleted. Nine
limitations recorded — **L4 (form-level UX) is the largest deliberately
unfinished part** and is flagged as wanting its own milestone rather than being
quietly claimed.

---

## 1. Inspection came first, and it changed the plan

The §2 inventory was completed and committed (`422c6b4`) **before any code
change**, and it contradicted the premise:

| Measure | Before |
|---|---|
| Areas in the rail | 8 (+ Operations) |
| Tiles | 99 across 98 distinct routes |
| Routes offered by two areas | **1** |

`lib/areas.php` is a deliberate single source of truth; `lib/navindex.php`
explicitly reuses it. **There was no duplicated menu system to consolidate.**

Reporting that honestly mattered more than finding something to change. The real
fragmentation was elsewhere, and that is what M11 fixed.

---

## 2. What was changed — six things, five source files

| # | Finding | Fix |
|---|---|---|
| **C1** | Nine home screens and a four-branch cascade inline in `index.php`; nothing could say why a person landed where they did | `ops_landing_decide()` — one **pure** resolver in the file that already owned landings. No screen removed |
| **C2** | **A real defect.** `index.php` said "once per session either way" but set the flag only on the cockpit branch — so an ordinary user in a company mid-setup was sent to `/welcome` **every time they clicked Home** | Flag set on both paths. Home reaches Home |
| **C3** | Sales and Admin both offered a tile labelled "Document templates" pointing at the **bare** `/templates` router — which screen you got depended on your permissions, not your click | Each tile names its destination: `?kind=quote` / `?kind=report`, with distinct labels. **0 destinations now offered by two areas** |
| **C4** | The partner contract form stated the authoritative path **only after** the partner already had contracts | Guidance stated before the form. Door kept — it carries an "against quotation" selector, so removing it would remove a legitimate action (§15) |
| **C5** | Eleven screens reachable only by typing the address | Three unambiguous utilities given entries (`/backup`, `/ai-settings` → Admin; `/duplicates` → Directory). Eight documented rather than guessed |
| **C6** | **Found by the manual walkthrough.** The gate's peek answered "yes, this link works" for Marketplace routes in a company without Marketplace; the click was then refused | Peek made honest via the existing `$peekExtra` mechanism. Peek and handler now agree both ways |

---

## 3. Testing

| | Result |
|---|---|
| **Focused M11** | **58 passed, 0 failed** |
| **Full regression** | **8,060 passed · 0 failed · 0 skipped · 459 files · 102 s · PHP 8.4.19** (M10 baseline 8,000) |
| **Operations / Reporting / Recruitment / Marketplace / Money / Sales** | all pass |
| **Manual walkthrough** | performed — and it is what found C6 |
| **MySQL/MariaDB** | **NOT EXECUTED** — no server installed (verified). No claim of validation |

### Two existing tests changed — both strengthened

Both pinned a **literal label** that legitimately changed. `test_simplify_reportcfg.php`
is about **where** the tile lives (move it back to Reporting and it still fails);
`test_admin_area_honesty.php` is about the **gate** (unchanged — and a new
assertion now pins the absence of a `crm.template.manage` grant, making it
stricter). Nothing else was touched.

One assertion of my own was wrong on first run: it matched the bare string
`crm.template.manage`, which also appears in a *comment*. **I corrected my
assertion, not the code.**

---

## 4. Nothing was destroyed

No schema change. No migration. No record deleted — no candidate, requirement,
organisation, interview, offer, report, Marketplace record or audit row. No route
deleted: every legacy address still resolves (§25). No permission semantics
changed (§27). Asserted by test: seven key functions and the secondary contract
door all still exist.

---

## 5. Entitlement UX

The rail hides an unentitled area, the palette and launchpad offer none of its
routes, a refusal says **which kind of "no"** it is, and — asserted — the server
still refuses whatever the UI shows. M5–M10 remain the authoritative boundary; a
hidden tile is not a security mechanism.

---

## 6. Acceptance criteria

| Criterion | Result |
|---|---|
| Existing navigation inventoried | **PASS** — committed before any change |
| Major user journeys mapped | **PASS** |
| Duplicate flows identified | **PASS** — by reading call sites; the automated pass was discarded as unreliable |
| Authoritative flows established | **PASS** |
| Duplicate navigation removed/consolidated | **PASS** — 1 → **0** |
| Legacy routes safely redirected or deprecated | **PASS** — none deleted |
| Recruitment navigation reflects the workflow | **PASS** — recruitment-only lands on the command centre; journey walked |
| Recruitment functionality intact | **PASS** |
| Master/permission semantics intact | **PASS** |
| SaaS entitlement enforcement intact | **PASS** — asserted |
| Dashboard consolidated without rebuilding | **PASS** — not rebuilt; module visibility already corrected in M6 |
| Operations / Reporting / Money / Sales functional | **PASS** |
| Marketplace functional and entitlement-controlled | **PASS** — and C6 closed its dead-end risk |
| No historical data deleted | **PASS** |
| Important forms clearer | **PARTIAL** — the contract form's authoritative path (C4). **Form-level work is L4, deliberately deferred** |
| Primary actions obvious | **PARTIAL** — same limitation |
| Status terminology consistent | **PARTIAL** — audited and mapped; renames deliberately not made (L3) |
| Error / empty states understandable | **PASS for errors** (asserted); empty states are part of L4 |
| Mobile usability reviewed | **REVIEWED, not remediated** (L5) |
| Accessibility basics reviewed | **REVIEWED, not remediated** (L5) |
| Manual journeys walked through | **PASS** |
| Full regression passes | **PASS** — 8,060/0 |
| No test unjustifiably weakened | **PASS** — two updated, both stricter |
| Documentation complete | **PASS** — six documents |
| Known limitations documented | **PASS** — nine |
| M12 NOT started | **PASS** |
| Phase 2 NOT started | **PASS** |

**Four criteria are marked PARTIAL rather than PASS.** Form-level clarity, primary
actions, status terminology and empty states span hundreds of screens; doing them
properly is a milestone, and doing them superficially would be the frontend
redesign §33 forbids. They are stated as unfinished rather than claimed.

---

## 7. Carried forward

**L4** — form-level UX (fields, disclosure, primary actions, empty states) wants
its own milestone with a screen-by-screen priority list. **L2** — eight
address-only screens each need one product decision. **L7** — six customer-creation
doors and four contract doors documented, not closed; reducing them is a business
decision with data and training impact.

**Before any deployment, M9's L1 still applies:** hosted workspaces need `connect`
added to their entitlement, or the marketplace switches off for them.

---

**STOP. M11 ends here.** M12 and Phase 2 have not been started.
