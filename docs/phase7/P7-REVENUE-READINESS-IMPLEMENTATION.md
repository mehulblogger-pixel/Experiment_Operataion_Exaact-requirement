# Phase 7 · Revenue readiness — implementation report

**Engines:** MariaDB 10.11.14 (authoritative) · SQLite 3.45.1 (supplementary)
**Blockers:** `P7-REVENUE-READINESS-BLOCKERS.md`

---

## A · Decisions implemented

| Locked decision | This cycle |
|---|---|
| **1** Employee numbers are tenant-wide, permanent, never re-issued | completed — including the half nobody had tested: another tenant *may* use the same number, because tenancy is one database per customer |
| **2** `team_role` decided at the requisition, confirmed at acceptance | **BLOCKED** — there is nowhere to record it (blockers §1) |
| **3** Workspace capability classification | **ready, not wired** — the existing catalogue already answers the question (blockers §4) |
| **4** RB-1, mandatory workforce creation | **BLOCKED** on decision 2 (blockers §2) |
| **5** Acceptance is atomic | already delivered in RB-3 Step 3; re-proved green here |
| **6** Refusal UX — inline, actionable, nothing partial | already delivered in RB-3 Step 3 |
| **7** Applicant matching existing staff | already delivered in RB-3 Step 2; **§4's instruction to remove `dup_ack` conflicts with §7 and was not obeyed** (blockers §2) |
| **8** RB-2, accepted ≠ joined | **defect located and reported; correction blocked** on a joining signal (blockers §3) |
| **9** Employee-number test matrix | completed |
| **10** SQLite busy timeout | completed |
| **11** Demo unload safety | completed, **plus a second defect found in the same area** |

## B · Files changed

| File | Purpose |
|---|---|
| `phpapp/lib/db.php` | SQLite busy timeout, named constant, SQLite only |
| `phpapp/lib/seed_demo.php` | the demo records what it created; the unload deletes by id, never by employee number; demo entitlements no longer attach to a reused real employee |
| `phpapp/tests/test_rb3_emp_code.php` | new **K** section — the number across two real tenant databases |
| `phpapp/tests/_rb3_tenant_emp.php` | the two-tenant probe, in its own process |
| `phpapp/tests/test_rb3_sqlite_busy.php` | the busy timeout, proved by effect |
| `phpapp/tests/_rb3_busy_worker.php` | the lock holder / second writer |
| `phpapp/tests/test_rb3_demo_unload.php` | a real employee must survive a demo unload |
| `phpapp/deploy-check.php` | regenerated |

## C · Database changes

**None.** No new table, no new column, no migration, no index change. The demo's
record of what it created lives in the existing `settings` table.

## D · Tests — focused

| Battery | SQLite | MariaDB |
|---|---|---|
| `rb3_emp_code` (now 66) | 66 · 0 | 66 · 0 |
| `rb3_sqlite_busy` | 14 · 0 | 1 · 0 (marker — SQLite-only by design) |
| `rb3_demo_unload` | 23 · 0 | 23 · 0 |
| all `rb3` batteries together | 274 · 0 | — |

## E · Mutation — targeted, 10 of 10 caught

Whole-repository copy and a fresh database per mutant. **FATAL is not a catch.
ANCHOR-MISS is not a catch. A dirty baseline aborts.**

| | Mutant | Result | Killed by |
|---|---|---|---|
| P1 | the busy timeout is not applied at all | **CAUGHT** | BUSY1/BUSY2 |
| P3 | the timeout is defined as zero — the old behaviour renamed | **CAUGHT** | BUSY2b/BUSY2 |
| P4 | the unload goes back to deleting by employee number | **CAUGHT** | DU1 |
| P5 | the seed records nothing, so the unload falls back | **CAUGHT** | DU1e1/DU1e2 |
| P6 | the seed claims a REUSED row as its own | **CAUGHT** | DU1e/DU1 |
| P7 | demo entitlements attach to a reused real employee again | **CAUGHT** | DU1h |
| P8 | the unload deletes nothing — "safe" by doing no work | **CAUGHT** | DU2 |
| P9 | the record of the demo is never cleared | **CAUGHT** | DU2a |
| P10 | the number rule stops installing in a second tenant | **CAUGHT** | K1a/K1b |
| P11 | the key stops normalising case and whitespace | **CAUGHT** | A8/A9/C5 |

**CAUGHT 10 · SURVIVED 0 · FATAL 0 · ANCHOR-MISS 0.**

## F · Full regression

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **13,110 passed · 0 failed** |
| **MariaDB 10.11.14** | **13,112 passed · 0 failed** |

Run **sequentially** — the engines share one control plane and must never be run
at once (recorded in RB-3 Step 3's evidence, §6 D7).

| Protected area | SQLite | MariaDB | |
|---|---|---|---|
| Operations | 563 · 0 | 563 · 0 | **PASS** |
| Reporting | 653 · 0 | 653 · 0 | **PASS** |
| Money / Billing | 641 · 0 | 641 · 0 | **PASS** |
| Workforce | 192 · 0 | 192 · 0 | **PASS** |
| Marketplace | 1317 · 0 | 1317 · 0 | **PASS** |
| Recruitment | 5115 · 0 | 5116 · 0 | **PASS** |
| Tenant isolation | 298 · 0 | 298 · 0 | **PASS** |
| Entitlement | 2039 · 0 | 2039 · 0 | **PASS** |

## G · Browser / HTTP — partial, and why

Driven with real HTTP against a throwaway workspace served by `php -S`.

**Verified:** the application boots and serves over HTTP; `GET /login` renders
(200); signing in as the administrator succeeds and establishes a session; the
workspace cockpit renders (`GET /connect` → 200).

**Not completed:** the end-to-end acceptance walk. In a **brand-new workspace
whose capabilities have never been configured**, the recruitment screens and the
`candidate-stage` action redirect rather than render. That is the product
behaving correctly — it is exactly the "unconfigured capability" state in §3,
which the owner ruled must never be inferred from — and configuring it
meaningfully requires the capability and `team_role` decisions that are blocked.

So the HTTP walk is blocked by the same two decisions as RB-1, and is reported
as incomplete rather than presented as passing. The acceptance flow's behaviour
is nonetheless covered by the automated batteries, including real multi-process
concurrency on MariaDB.

For a browser-level walk once the workspace can be configured, the project
already has `phpapp/tools/auto-walk.sh` (Playwright). No new harness was built.

## H · Data safety

**No historical data was changed.** No inspector deleted, no person merged, no
employee number rewritten, no `team_role` reclassified, no recruitment state
rewritten, no duplicate removed to make a test pass. No migration ran. No dirty-
data population was found that would block a constraint, because no constraint
was added.

One behaviour was *made safer*: the demo unload can no longer delete a real
employee, and the demo no longer leaves its entitlements on a real employee's
record.

## I · Remaining blockers

Three, all needing an owner decision, all documented with evidence in
`P7-REVENUE-READINESS-BLOCKERS.md`:

1. `requisitions` has no `team_role` column — one nullable column is needed.
2. RB-1 cannot be built until 1 is decided, or every hire silently becomes FIELD.
3. RB-2 needs a real joining signal — one nullable column or one new stage.

Plus one **conflict inside the prompt**: §4 says remove `dup_ack`; §7 requires it.
Read as applying to `make_inspector` only.

## J · Git

Commit hash and tree state recorded in the commit itself; the working tree was
clean and in sync with the remote at the end of this cycle.
