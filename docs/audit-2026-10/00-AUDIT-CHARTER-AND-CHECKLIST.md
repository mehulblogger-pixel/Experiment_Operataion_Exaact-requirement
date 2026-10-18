# EXAACT master audit — charter, method and checklist

**Started** 2026-10-18 · **Code audited** `claude/testing-branch-setup-0gqe8n` @ `f9fb003`
**Live site** https://operations.mghaiapps.com — see *Access limitations* below.

This is a **discovery and audit** exercise. No application code, schema,
configuration, permission or business record is modified by it. Everything in
this folder is evidence or analysis.

---

## 1. What "evidence" means here

Three grades are used throughout, and nothing is promoted between them silently.

| Grade | Means | How it was obtained |
|---|---|---|
| **MEASURED** | A command was run and its output is saved under `evidence/` | route probe, navigation dump, test runs, schema reads |
| **READ** | Asserted by code or a document that was opened and quoted | file path + line cited |
| **OPEN QUESTION** | Cannot be settled from the repository; needs the owner | listed in §6 of each deliverable |

A screen is **not** called working because a route, a menu item, a table or a
test exists for it. The route probe (`evidence/route-probe.py`) actually
requested every dispatched route and recorded what came back.

---

## 2. Access limitations — stated up front

These bound every claim in this audit.

1. **The live production site was not driven.** All runtime evidence comes from
   the application running locally against a **throwaway copy** of a UAT
   database. Production behaviour may differ where production data or
   configuration differs. Nothing was read from, or written to, production.
2. **The route probe ran as a single role — administrator.** Role-dependent
   navigation (notably the inspector's own rail, which `views/layout_top.php`
   draws from a different branch) is therefore **not** covered by the Phase B
   numbers. A per-role probe is Phase F work. Until then, a screen listed as
   "unreachable" means *unreachable for an administrator*.
3. **Destructive routes were not fired.** 18 routes whose names contain
   delete / erase / purge / reset / restore / wipe / drop were skipped by
   design; they are listed in `evidence/routes-not-probed.txt` and must be
   covered by controlled, scripted tests instead.
4. **GET-only.** The probe issues GET. A route that only acts on POST shows as
   a redirect or as its parent screen; it is not evidence that the action works.
5. **Sibling projects not yet audited.** `licence-server/`, `mgh-hire/`,
   `mgh_books_receiver/`, `sop/` sit in this repository but are separate
   deployables. Whether they are in scope is **open question Q1**.

### Two measurement errors made and corrected during Phase A

Recorded because they affect how much the numbers can be trusted.

- The first route probe walked the routes alphabetically and hit **`/logout`**,
  logging itself out; every route after it reported "redirect to login". The
  run was discarded. The probe now refuses to start unless it can load `/admin`
  as a signed-in administrator, and re-authenticates if it is ever logged out.
- The second run died three quarters through on a route returning a **PDF**,
  decoding it as UTF-8. Responses are now read as bytes.

---

## 3. Phase checklist

| Phase | Deliverable | State |
|---|---|---|
| **A** Repository discovery & existing-document review | §4 of this file + `01-APPLICATION-INVENTORY.md` | **DONE** |
| **B** Navigation & module inventory | `01-APPLICATION-INVENTORY.md` | **DONE (admin role only)** |
| **C** Cross-module dependency & data ownership | `02-MODULE-DEPENDENCY-MAP.md` | not started |
| **D** End-to-end workflow mapping | `03-WORKFLOW-CATALOGUE.md` | not started |
| **E** Screen, field, terminology & usability audit | `04-SCREEN-AND-FIELD-AUDIT.md`, `05-BUSINESS-GLOSSARY.md` | not started |
| **F** Security, permissions, calculations & reporting | `06-DATA-AND-PERMISSION-AUDIT.md`, `07-DASHBOARD-AND-KPI-AUDIT.md` | not started |
| **G** Test design & controlled verification | `09-UAT-PLAN.md` | not started |
| **H** Consolidated gap register & roadmap | `08-GAP-REGISTER.md`, `10-ROADMAP.md` | seeded in Phase B |

Phase B is marked done **for the administrator role only**. It is not complete
until the probe has been repeated for coordinator, inspector and finance.

---

## 4. Evidence already in the repository — reused, not rebuilt

The repository holds 671 documents, including prior audits. Rule: reuse them.
These were opened and are treated as inputs rather than re-derived.

| Document | Lines | Last changed | Role in this audit |
|---|---|---|---|
| `docs/01-roles.md` | 163 | 2026-08-27 | role definitions — input to Phase F |
| `docs/02-permission-matrix.md` | 974 | 2026-10-15 | **the authority on who may do what**; Phase F tests against it |
| `docs/03-object-lifecycles.md` | 963 | 2026-10-18 | **the authority on statuses and transitions**; Phase D tests against it |
| `docs/05-ui-ux-blueprint.md` | 317 | 2026-10-15 | the UI/UX standard; Phase E tests against it |
| `docs/99-gaps-and-risks.md` | 217 | 2026-08-27 | **stale (8 weeks)** — superseded in practice by the register below |
| `docs/phase7/MASTER-DEFERRED-BACKLOG-REGISTER.md` | 382 | current | the live finding register: 35 R-numbered findings, 3 still carrying OPEN/HELD text |
| `docs/phase7/UAT-MANUAL-EXECUTION-GUIDE-2026.10.1.md` | 1092 | current | 335 owner-facing checks with exact click paths |
| `docs/phase0/`…`docs/phase7/` | 400+ | various | prior milestone audits — mined per topic, not re-run |

Nothing in this folder duplicates those. Where this audit disagrees with one,
the disagreement is stated and the evidence cited.

---

## 5. Deliverable structure

```
docs/audit-2026-10/
  00-AUDIT-CHARTER-AND-CHECKLIST.md   this file — scope, method, limitations, progress
  01-APPLICATION-INVENTORY.md          D1  navigation tree, screens, routes, status
  02-MODULE-DEPENDENCY-MAP.md          D2  ownership, upstream/downstream, gaps
  03-WORKFLOW-CATALOGUE.md             D3  processes, flowcharts, transitions, exceptions
  04-SCREEN-AND-FIELD-AUDIT.md         D4  screen-by-screen, field-by-field, usability
  05-BUSINESS-GLOSSARY.md              D5  standard terms, synonyms, labels
  06-DATA-AND-PERMISSION-AUDIT.md      D6  entities, integrity, authorisation, isolation
  07-DASHBOARD-AND-KPI-AUDIT.md        D7  metric definitions, formulas, reconciliation
  08-GAP-REGISTER.md                   D8  every finding, with evidence and acceptance criteria
  09-UAT-PLAN.md                       D9  scenarios, steps, expected results, status
  10-ROADMAP.md                        D10 P0–P3, dependencies, order of remediation
  evidence/                            raw, reproducible output
```

### Evidence index

| File | What it is |
|---|---|
| `routes-dispatched.txt` | 565 routes the front controller matches exactly |
| `route-probe.py` | the probe itself — re-runnable |
| `route-probe-admin.tsv` | every probed route: status, classification, rendered heading |
| `unreachable-screens.tsv` | 95 screens that render but have no menu entry |
| `action-handler-aliases.tsv` | 21 routes that re-render a parent screen (not lost) |
| `routes-not-probed.txt` | 18 destructive routes deliberately not fired |
| `navigation-dump.json` | the live menu: areas, sections, tiles, find-a-screen index |

---

## 6. Working rules adopted

1. No change to application code, schema, configuration or data during discovery.
2. A menu label is not evidence of what a screen does.
3. A finding is **CONFIRMED** only with saved evidence; otherwise **SUSPECTED**.
4. Confirmed defects and proposed improvements are kept in separate lists.
5. Unclear business rules are recorded as questions, never guessed.
6. No credential, secret or personal datum appears in any artifact here.
7. Scope is not expanded because a feature would be useful.
