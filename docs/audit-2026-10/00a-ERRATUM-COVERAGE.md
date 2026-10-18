# ERRATUM — the audit's universe was incomplete

**Found 2026-10-18, during Phase F (D7).** Read this before trusting any count
in this folder.

---

## 1. What happened

Phase A built `evidence/routes-dispatched.txt` — **565 routes** — and every
later count rested on it:

- "256 screens that render"
- "194 reached by crawling / 62 not reached"
- "200 dispatched routes have no module gate" (GAP-008)
- the per-area screen counts in `workflows/`

**That list was built by parsing route literals out of `phpapp/lib/ops.php`.**
Routes dispatched from *other* files were never in it.

The error surfaced while tracing KPI functions for D7: `kpi_defs`,
`kpi_targets`, `kpi_snapshots`, `kpi_versions` and `kpi_alerts` turned out to be
read by `lib/tapi_dash.php`, `tapi_score.php` and `tapi_gov.php` — files the
parse never read. Requesting their routes directly returned **HTTP 200 with
real headings**:

| Route | Heading |
|---|---|
| `/analytics` | **Analytics & performance** |
| `/analytics-kpis` | **KPI library** |
| `/analytics-quality` | **Data quality** |
| `/analytics-drill` | **Breakdown** |

Four working screens, absent from the inventory.

---

## 2. How big the gap was

`evidence/route-list-recount.py` re-parses **every** file in `phpapp/lib/` plus
`index.php`, and `evidence/route-recount-probe.py` then **requests each
candidate** and classifies it exactly as Phase A did, so the two counts are
comparable.

| | Count |
|---|---|
| Route literals found across all of `lib/` | 920 |
| Not in the committed list | 355 |
| …**probed** (17 destructive names skipped) | 338 |
| …**not routes at all** (404 — field types, provider names, column names) | **178** |
| …**real routes the list was missing** | **160** |
| …of which render as a usable **screen** | **70** |

The 178 false candidates are why a literal is not proof of a route:
`idems.php` yields `checkbox`, `date`, `empty`; `ai.php` yields `openai`,
`gemini`; `partnerimport.php` yields `city`, `pincode`. Probing settles it.

### Corrected totals

| | Was | **Is** |
|---|---|---|
| Dispatched routes | 565 | **≈725** |
| Screens that render | 256 | **≈326** |

---

## 3. The part that matters most: three external portals were never audited

The 160 missing routes are not evenly spread. **They include the whole
external-facing surface of the product**, which the audit never touched:

| Family | Routes probed | Result as a staff admin |
|---|---|---|
| **Client portal** `/portal/*` | 33 | all **404** |
| **Vendor portal** `/vendor/*` | 11 | all **404** |
| **Professional portal** `/pro/*` | 26 | 23 redirect, 3 screen |
| **Analytics (TAPI)** `/analytics*` | 10 | 9 screen, 1 partial |

### Why they 404, and why that is good architecture

`index.php:1024` dispatches them **before** staff authentication, and the
comment says why:

> *"It is dispatched HERE, in front of `require_login()`, because
> `require_login()` means a STAFF account — a client has none and must never be
> pushed towards one. Everything past this point is the staff application;
> nothing a client does ever reaches it."*

And for the vendor portal:

> *"a THIRD audience, kept as separate from the client portal as the client
> portal is from staff: its own table (`vendor_users`), its own session key
> (`vuid`) and its own `/vendor` addresses."*

**Three audiences, three tables, three session keys, three address spaces,
dispatched ahead of staff login.** That is a stronger separation than most
products manage, and it is *why* a staff administrator gets 404: those
addresses genuinely do not exist for a staff session.

The client and vendor portals return 404 in the test workspace because they are
**off** — each is enabled per party via **"Switch it on"** on
`/portal-users` / `/vendor-users` (`workflows/09-DIRECTORY.md` §2.8).
`/pro/login` returns **200**, because the professional portal is the
marketplace side and needs no per-party switch.

### What this means for the audit

**Everything in this folder describes the staff application only.**

The external surface — where a client, a vendor or a self-listed professional
signs in — has **not been inventoried, not been probed per role, not had its
forms tested, and not been checked for the ungated-route problem.** For a
multi-tenant SaaS, that is the surface where a mistake costs most: a client
seeing another client's reports, a vendor seeing another vendor's assessment, a
professional seeing somebody else's identity documents.

The permission matrix does cover it in principle — portal access runs on a
**separate** permission system (`PORTAL_PERMS`, `pcan()`, matrix footnote 20)
rather than the staff `mod.*` rights, and the matrix states that a client sees
only its own requirements and privacy-safe cards of professionals. **None of
that was verified.**

**This is now the largest single gap in the audit**, and it is recorded as
**GAP-012**.

---

## 4. What is and is not affected

### Affected — counts and coverage

| Claim | Status |
|---|---|
| "565 dispatched routes" | **undercount** → ≈725 |
| "256 screens render" | **undercount** → ≈326 |
| "194 reached / 62 not reached" | computed over an incomplete universe; the *ratio* is probably indicative, the absolutes are not |
| GAP-008 "200 routes with no module gate" | **undercount**. The 160 newly-found routes have not been classified for gating |
| Per-area screen counts in `workflows/` | **undercounts**, mostly of action handlers (`ncr-*`, `capa-*`, `audit-*`, `complaint-*`, `equip-*`, `hw-point-*`, `iddoc-*`, `billable-*`, `lead-*`, `opportunity-*`, `project-costing-*`, `pipeline-*`) |

### Not affected — every per-screen measurement

Nothing measured about a screen that *was* inventoried becomes wrong. These all
stand:

- The four forms that create a numbered record from an empty submit, and the
  records they wrote (`CALL-00001`, `CV-00001`, `INC-2026-001`,
  `MGH/AHM/2026/GEN/END/000001`).
- The per-role access matrix for the routes that were probed, including the
  books write-authority test and the receipt a Coordinator actually recorded.
- The concurrency finding (`stale_edit_block()` covers `calls` and `jobs` only).
- The terminology and glossary analysis.
- Every field, button and column list in `workflows/`.
- The GAP-011 dead-door fix and its guard test.
- The 63 status vocabularies and the lifecycle-coverage gap.

**It is a coverage error, not an accuracy error.** What is written about what was
measured is still true; there was more to measure than the audit knew.

---

## 5. Why it happened, and the fix

**Why:** one file was treated as the router. `ops.php` is the largest dispatcher
but not the only one — 20-odd `lib/` files dispatch their own routes, and
`index.php` dispatches three portals ahead of everything.

**The deeper mistake:** I never checked the route list against the running
application. A probe that only ever asks about routes it already knows cannot
discover a route it does not. **The list should have been validated by asking
the app what it serves, not by reading one file.**

**The fix, and it is cheap:** `evidence/route-list-recount.py` parses every
`lib/` file; `route-recount-probe.py` verifies each candidate against the live
app. Both are committed and re-runnable. A better version still would have the
application enumerate its own routes — worth adding, because then the inventory
could never drift again.

---

## 6. What must now be done

| | Work | Why |
|---|---|---|
| 1 | **Inventory and probe the three portals** per external role — client user, vendor user, professional | The external surface, entirely unaudited (GAP-012) |
| 2 | **Verify tenant and party isolation on the portals** — can a client reach another client's reports? | The highest-consequence question in a multi-tenant product |
| 3 | **Re-run the module-gate classification** over all ≈725 routes | GAP-008's number is an undercount |
| 4 | **Re-run the reachability crawl** over the corrected universe | The 194/62 split needs restating |
| 5 | **Fold the 70 newly-found screens into `04-SCREEN-AND-FIELD-AUDIT.md`** and the affected `workflows/` documents | Field, button and validation coverage |
| 6 | Document the **analytics/TAPI** screens | They are the KPI framework, and D7 now covers them |

Items 1 and 2 are the priority. Everything else is arithmetic; those two are
about whether one customer can see another's data.
