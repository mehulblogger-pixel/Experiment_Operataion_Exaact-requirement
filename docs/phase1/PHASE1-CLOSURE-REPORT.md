# EXAACT — Phase 1 Closure Report

**Recommendation: 🟡 CLOSE WITH DOCUMENTED LIMITATIONS**
**Release: `d3f5244` + M16 · SQLite 8,267 passed / MariaDB 10.11 8,268 passed · 0 failed**

---

## 1. What Phase 1 set out to do, and what it did

EXAACT began Phase 1 as a working multi-tenant PHP operations platform whose
commercial boundary — *who has paid for what, and who may see whose data* — was
only partly enforced. Phase 1 closed that boundary, then attacked it, then proved
it on the production database engine, then deployed and exercised it over real
HTTP.

| Milestone | What it established |
|---|---|
| **M2–M4** | The entitlement engine: one chain, one chokepoint, seven states, **UNKNOWN = DENY** |
| **M5–M8** | Enforcement at routes, actions, portals, exports, public routes and background jobs |
| **M9** | Marketplace/Connect became a commercial module (the 7th product) |
| **M10** | Master privilege is **not** entitlement |
| **M11–M12** | One landing answer; a lock screen that says which "no" this is and what to do |
| **M13** | Attacked tenant identity — **3 critical cross-tenant paths found and fixed** |
| **M14** | Attacked object authorization — **7 IDORs found and fixed**, incl. cross-branch delete |
| **M15** | Ran on MariaDB — **3 defects only production could reveal** |
| **M16** | Deployed and exercised over real HTTP — **1 entitlement bypass found and fixed** |

**Every milestone that attacked the system found something.** That is the
strongest evidence in this report: the boundary was not assumed, it was tested
until it stopped yielding.

## 2. Status by area

| Area | Status | Evidence |
|---|---|---|
| **Architecture** | 🟢 | One entitlement chain, one route chokepoint, one lock screen. No second security system was ever created |
| **SaaS entitlement** | 🟢 | 540 assertions on MariaDB; S-1 verified by **direct URL** on a live host, as a master |
| **Tenant security** | 🟢 | Cross-tenant refused **both directions**, with both tenants holding identical ids. Two independent barriers: separate databases + M13 workspace binding |
| **Object authorization** | 🟢 | 7 IDORs closed; re-verified on a live host by URL, POST and download |
| **Database** | 🟢 | MariaDB 10.11: fresh install, idempotent re-run ×3, migration preserving all data, 185 indexes |
| **Production deployment** | 🟡 | Proven over **real HTTP** here; **the mPanel host itself is NOT TESTED** |
| **Operations** | 🟢 | Registers, branch scope, Quality inside it — all pass |
| **Reporting** | 🟢 | Dashboards render; entitlement gates them |
| **Money** | 🟢 | Invoices gated and branch-scoped |
| **Sales** | 🟢 | Quotes, leads, opportunities gated and branch-scoped |
| **Recruitment** | 🟢 | Existing surface intact (regression only — Phase 2 builds on it) |
| **Marketplace** | 🟢 | After the M16 fix; lifecycle OFF→ON→OFF verified live |
| **Quality** | 🟢 | Complaints, CAPA, NCR inside Operations |
| **Backup / restore** | 🟢 | Deleted records recovered on the live host |
| **Cron** | 🟢 | Exit 0, 23 integrity checks, 0 failed |
| **File storage** | 🟢 | Served to the owner, refused cross-branch |
| **UAT** | 🟡 | Completed on the live host; **e-mail NOT TESTED** |
| **Project costing** | 🟢 | **Decided and implemented** — see §4 |

## 3. The security record

| Found | Severity | Milestone | Status |
|---|---|---|---|
| Session identity not bound to its workspace | **CRITICAL** | M13 | FIXED |
| `/reset?w=` moved a session to any workspace | **CRITICAL** | M13 | FIXED |
| Failed login left you in another workspace | **CRITICAL** | M13 | FIXED |
| Call-detail cross-branch IDOR | High | M13 | FIXED |
| Quote / opportunity / lead / lead-document / requisition / complaint / receipt IDOR — incl. **cross-branch DELETE** | High | M14 | FIXED |
| `LIMIT ?` — 7 features silently blank on MySQL | High | M15 | FIXED |
| KPI versioning corrupted its own audit history | Medium | M15 | FIXED |
| **Marketplace desk reachable without entitlement** | **High** | **M16** | **FIXED** |
| Error page leaked paths to any signed-in user | Medium | M13 | FIXED |

**No critical or high issue remains open.**

## 4. Project Costing — decision taken (§25)

**Decision: Option B — branch-scoped.** Costings now follow branch scope, exactly
as leads, complaints and receipts do.

**Reason:** a costing carries day rates, overheads, contingency, negotiation
margin and expected revenue. Those are the numbers a branch manager should not
see for another branch. The register previously applied no scope at all.

**Affected:** every user holding the costing permission; the Project Costing
module; no other module. **Implemented in M16** with the M14 one-gate pattern —
list and object gate together, an unassigned costing still visible to all, and
head office (ALL scope) unchanged. 16 regression assertions, mutation-verified.

## 5. Test results

| | |
|---|---|
| **SQLite** | **8,267 passed, 0 failed** |
| **MariaDB 10.11.14** | **8,268 passed, 0 failed** |
| M13 · M14 · M16 | 51 · 46 · 22, all green on both engines |
| M5–M12 entitlement | 540, green on both engines |
| Live-host UAT | §1–§7 of `M16-REAL-HOST-UAT.md` |

No test was deleted, skipped or weakened at any point in Phase 1. Every fix was
reverted in isolation to confirm its test fails without it.

## 6. Known limitations — all non-critical

**NOT TESTED:** the mPanel host itself (L1) · e-mail (L2).
**DOCUMENTED:** no load testing (L3) · mobile/accessibility sanity only (L4) ·
two first-run steps that surprise people (L5) · `marketplace-*` routes absent
from the module-gate family table — defect fixed, shape recorded (L6) ·
pre-M13 sessions bind at next sign-in · `invoice_no` column default (latent) ·
object-level coverage is high-value-first (L7).

None is a tenant-security bypass, an entitlement bypass, or a destructive
migration problem.

## 7. Deferred to Phase 2 or later

Adding `marketplace-*` to the module-gate family table (needs an owner-console
decision) · exhaustive object-level audit of the remaining fetch-by-id tail ·
`invoices.invoice_no` column default (a schema change) · load testing ·
accessibility conformance.

## 8. Recommendation

**Close Phase 1 with documented limitations**, subject to one action:

> **Run `M16-REAL-HOST-DEPLOYMENT-CHECKLIST.md` on the MilesWeb host and record
> steps 5–10.** That converts L1 and L2 from NOT TESTED to evidence.

Phase 1's job was to make the commercial and security boundary real and prove it.
It is real, it has been attacked five times by five different methods, and the
last two attacks had to reach a production database and a live web server to find
anything at all. The remaining gap is not a doubt about the software — it is one
deployment nobody has performed yet.

Phase 2 (Recruitment Structural Foundation) can begin on this base.
