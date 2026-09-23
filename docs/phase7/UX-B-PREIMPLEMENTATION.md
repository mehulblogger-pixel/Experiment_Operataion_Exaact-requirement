# UX-B-PREIMPLEMENTATION — Phase B baseline and protection

**B0 deliverable. No implementation has begun.** This records what is true on
the day Phase B starts, so that every later claim of improvement can be measured
against something rather than asserted.

| | |
|---|---|
| **Branch** | `claude/testing-branch-setup-0gqe8n` |
| **Commit at baseline** | `d5bd60f` — *fix(F-A7-1): Masters "add a person" now refuses a duplicate in words, not SQLSTATE* |
| **Engines** | SQLite **13,486 passed / 0 failed** · MariaDB (authoritative) **13,489 passed / 0 failed** |
| **Scale** | 401 views · 520 test files · 103–104 area-home tiles · 9 browser/mobile tools |

---

## 1. A correction to the audit, issued before any work is planned

**Finding F-A3-1 says "103 tiles, 0 carry a count — the badge is built, rendered,
and unused."** That is wrong as a statement about the code, and planning B5
against it would have produced the wrong work.

Measured by running `ops_area_def()` for all eight areas as a fully-entitled
administrator:

| Measurement | Result |
|---|---|
| Tiles carrying a count on an **empty** workspace | **0 of 103** — which is what the audit saw |
| Tiles carrying a count on a **seeded** workspace (`seed_demo`) | **13 of 104** |
| Tiles wired to a count **in source** (`$num(...)`) | **24** |

Per area, wired in source: Quality 10, Marketplace 7, Money 4, Sales 1,
Directory 1, Admin 1. Marketplace's seven produce nothing on demo data because
the demo workspace has no marketplace records — that is the mechanism working
correctly, not a fault.

**The corrected finding:** the badge is built, rendered, and **already in use on
24 tiles**. About **80 tiles are unwired**, and the gap is concentrated in the
areas a daily user actually lives in — Sales 1/10, Reporting 0/8, Insights 0/4,
Admin 1/26.

**What this changes about B5.** The work is *not* "build a count mechanism for
103 tiles". It is "extend a proven, already-used pattern to the tiles where an
actionable count exists, and leave the rest alone". It also means **B5 must be
verified on a populated workspace**; an empty one cannot tell a wired tile from
an unwired one. That is precisely how the original figure went wrong.

This is the fifth correction issued against my own audit figures. The four
earlier ones are recorded in `UX-AUDIT.md`.

---

## 2. Findings re-verified before planning

The three findings that drive the largest share of Phase B were re-measured
rather than taken on trust. All three **stand**.

| Finding | Re-verification | Verdict |
|---|---|---|
| **F-A2-2** — secondary text fails contrast | `app.css` declares `--muted:#656e7a` = **5.17:1** on white (passes AA). `theme_style_tag()` in `lib/access.php` then re-declares it per tenant as `theme_mix($surface, $ink, 0.45)` ≈ **2.7:1**. Every themed workspace overrides a compliant value with a failing one. | **Confirmed** |
| **F-A4-1** — global search misses Recruitment | `search_sources()` registers **16** sources: partners, contacts, leads, inquiries, quotes, opportunities, invoices, contracts, calls, jobs, reports, complaints, ncr, capa, people, equipment. **No candidate, requisition, hiring request or marketplace requirement.** | **Confirmed** |
| **F-A6-2** — status tone mapping duplicated | Nine separate helpers found: `inspector_eligibility_pill`, `credential_status_pill`, `idems_status_pill`, `template_status_pill`, `endorse_status_pill`, `inspector_impartiality_pill`, `tapi_status_tone`, `avail_tone`, plus the area tile tone. The audit said six; it is **nine**. | **Confirmed, and larger than reported** |

---

## 3. Patterns that must be preserved, not "improved"

The audit's "what is already right" table is binding. In particular this phase
must not touch:

- **One KPI engine** — `connect_kpi_board`, shared by inspector, client and
  freelancer boards. `kpi_card()` / `kpi_card_row()` in `lib/tapi.php` are the
  render side. Do not add a second.
- **One search registry** — `search_sources()` with a permission gate per
  source. B8 **extends this**; it never creates a parallel engine.
- **One area-tile builder** — `ops_area_def()` in `lib/areas.php`, whose `$t()`
  closure already accepts `count` and `tone`. B5 **uses this signature**.
- **Workflow continuity** — 398 redirects already land on the record just
  touched. Do not reroute any of them.
- **Licence and permission gating** — the reason cross-role vocabulary confusion
  does not exist. No tile, count, search result or next-action may appear to a
  user the gate would refuse.
- **The two reference screens** — the Requirement form and the Form Designer
  score highest and were verified in a browser. B6 copies them.
- **The Accepted-vs-Joined pattern** on the candidate screen — the one confusion
  pair already solved. It is the model for B4, not a thing to replace.

---

## 4. Components to reuse

| Need | Existing component | Location |
|---|---|---|
| Record header + status | `.master-head` (280 of 401 views) | `assets/css/app.css` |
| Breadcrumb | `.crumbs` (274 of 401 views) | app.css |
| Status pill | `.pill` + `p-ok/p-warn/p-bad/p-info/p-mut` (492 semantic uses) | app.css |
| KPI / count card | `.kpi`, `.qcard`, `.tile` — unified banded design | app.css |
| Area tile with badge | `$t($show,$icon,$label,$route,$desc,$count,$tone)` | `lib/areas.php` |
| Form sections + disclosure | Requirement form + `fd_sections()` | `lib/formdesign.php` |
| Custom/extra fields | `fd_extra_fields($entity,$id)` | `lib/formdesign.php` |
| Terminology + definitions | `T()/TP()/Tl()/TH()`, `TERM_DEFAULTS` (25 terms, each with a definition) | `lib/access.php` |
| Search | `search_sources()` | `lib/search.php` |
| Quick filters | chip/tab pattern (76 views) | app.css |

**Components expected to be created (and only these):** a shared **next-action**
block (F-A5-3), a shared **relationship line** (F-A8-3), a shared **definition
tooltip/inline** surface (F-A8-1), and a **mobile record card** treatment for
list screens (F-A4-3). Each is a presentation component. None carries business
logic, and none may decide permission.

---

## 5. Screens in scope

Ordered by the scorecard's worst dimension.

| Screen | Worst score | Phase step |
|---|---|---|
| Recruitment Command Centre | Den 1 (2,643px, 43 links) | B2 |
| Job form (57 fields) | Den 1 | B6 |
| Test request form (54) | Den 1 | B6 |
| Test request record | Act 1 (6 primaries, no breadcrumb) | B3 |
| List screens (general) | Mob 1 (249 of 251 tables) | B7 |
| `/search` | Rel 1 (Recruitment absent) | B8 |
| Money / Quality / Marketplace / Sales / Directory / Insights homes | Sta 1 | B5 |
| Dashboard | Act 2, Den 2 (30 unranked cards) | B9 |
| Engineer form (48), User form (40) | Den 2 | B6 |
| Requirement record | Act 2 (0 primary, sparse data) | B3 — re-check on populated data |
| Every page | Vis — muted contrast | B1 |

---

## 6. Risks

| Risk | Why it matters here | Control |
|---|---|---|
| **A count that leaks past a permission gate** | A badge is data. Showing "3 pending approvals" to someone who may not see approvals is a disclosure, not a cosmetic slip. | Every count reuses the tile's existing `$show` gate; no count is computed for a tile the user cannot open. Asserted in test. |
| **A next-action that offers a forbidden transition** | B3 is the highest-value change and the easiest to get wrong. | The action must be derived from the existing lifecycle and the existing permission check — never from a hard-coded list. Where no action is permitted, show the neutral state. |
| **Contrast fix changing tenant identity** | `theme_style_tag()` is per-tenant. A blunt fix would override the owner's chosen colours. | Darken only until AA is met, from the tenant's own colours. Never substitute a fixed grey. |
| **Mobile card duplicating the table** | Two renderings of one list drift apart. | One data source, two presentations, in a shared partial. |
| **Search extension widening visibility** | A new source could expose records a role never saw. | Each new source carries its own `can()` gate, mirrored from the owning module. Asserted per source. |
| **Pagination changing behaviour** | F-A4-2 is classed "medium — behaviour". | Treated as presentation only: same rows, same order, rendered a page at a time. If it cannot be done without changing what a screen returns, it is recorded as a product decision, not implemented. |
| **Measuring on an empty workspace** | Already cost one wrong finding (§1). | Every count, list and relationship claim is verified on a seeded workspace. |

---

## 7. Test suites that must remain green

Nothing in Phase B may turn any of these red.

| Protected area | Representative suites |
|---|---|
| Recruitment | `test_recruit*` (12), `test_rb3*` (5) |
| Workforce / identity | `test_r20_inspector_duplicates`, `test_fa7_masters_duplicate_door` |
| Operations | `test_p2*` (24), `test_p3*` (10) |
| Reporting | `test_report*` (9) |
| Money / billing | `test_billable*` (7), `test_voucher*` (6) |
| Marketplace | `test_connect*` (56), `test_mkt*` (8) |
| Tenant isolation & entitlement | `test_saas*` (6) |
| Navigation / UX consolidation | `test_m11_ux_consolidation` |
| Form engine | `test_rqform_vocabulary`, Form Designer suites |

**Gate for every batch:** focused suite → affected-module suites → full SQLite →
full MariaDB (authoritative) → browser → mobile. `php tools/make_deploy_check.php`
must be re-run after any source change.

### Browser and mobile tools already in the repository

`tools/smoke.js` · `tools/p7-browser-uat.js` · `tools/p7-mobile-uat.js` ·
`tools/p7-area-capture.js` · `tools/p7-uat-capture.js` · `tools/portal-crawl.js` ·
`tools/rqform-ui-check.js` · `tools/fd-ui-check.js` · `tools/fa7-door-check.js`

Phase B extends these. It does not start a new harness.

---

## 8. Product decisions required so far

**None.** The audit concluded that no finding needs a new status, table,
relationship, permission, approval or workflow, and nothing in this baseline
changes that. The register stays open: any step that cannot be completed without
a business change stops and is recorded here rather than implemented.

---

## 9. Gate

B0 is complete. B1 may begin.
