# Milestone 5 — Runtime Module Enforcement

**Status:** complete · **Suite:** 7,474 passed, 0 failed · **Baseline:** 45d55f9

---

## 1. What this milestone was for, in plain language

Milestone 3 made the app able to give the **right answer** to "has this company
bought this module?".

Milestone 5 makes the running application actually **ask the question** —
everywhere a customer can arrive.

An answer nobody asks for protects nothing. The safety check measured four
places where a request reached a paid module without the question ever being
put. All four are now closed.

---

## 2. The four holes, and what closed each

### A · A route nobody added to the list was protected by nothing

Every screen was gated by an explicit list of `route → module`. The list had
453 entries. The dispatcher serves far more routes than that, and **203 of the
routes it serves were not in the list**. Anything not named was simply let
through.

**Closed by** `ops_module_family()` in `lib/ops.php`, consulted only when the
explicit list has no entry. A route beginning `candidate…`, `requisition…`,
`invoice…`, `lead…`, `quote…` belongs to that module whether or not anyone
remembered to list it.

**Deliberately not a clever guess.** An automatic recogniser was built and
measured first, and it was wrong in ways that would have broken working
screens — it read `issue-licence` as the NCR register and `approval-rules` as
Sales. The shipped table is hand-verified. A route the table does not name stays
unclaimed rather than being guessed at, because wrongly *blocking* a paying
customer is as much a defect as wrongly allowing one.

### B · The public careers page never reached the gate

`/careers` is served from `index.php` and finishes there, **before** the router
reaches the one place every other screen is checked. A company with no People &
hiring entitlement could publish a public jobs site and take real applications
into a module it had not bought.

**Closed by** asking the entitlement question inside `careers_enabled()`
(`lib/careers.php`) — the single reader both the public site and the admin
screen already use. Two separate questions, in the only safe order:

1. Does this installation **have** People & hiring?
2. Has this company **chosen** to publish a careers page?

The careers page stays opt-in. Entitlement permits it; it never imposes it.

### C · A master user saw every module, bought or not

`is_master()` is a single flag read — `ua()['master']`. It knows nothing about
licensing. Wherever a screen asked `is_master() || can('mod.idems.view')`, the
master flag answered first and the entitlement question was never reached.

**An important correction to the plan.** The instruction for this item was to
reorder these to `can(...) || is_master()`. Reordering does **not** close the
bypass: `can()` returns false for a module the company has not bought, and then
`is_master()` returns true anyway. The ordering is cosmetic.

What closes it is asking the entitlement question *about the module in play* —
which is exactly what the codebase's existing `is_master_of()` helper already
expresses: *"a master, but only for a module this installation actually has."*
All 37 paid-module sites now use it. Nothing new was invented and no second
entitlement engine was built.

The commercial rule this produces:

| Situation | Result |
|---|---|
| Paid module + entitled + master | normal access |
| Paid module + **not** entitled + master | **denied** |
| Core module + master | normal access, always |
| Not a master | unchanged in every case |

One site was deliberately left alone — `lib/areas.php:206`, where the
expression is `can('mod.invoicing.view') && (… || is_master())`. Entitlement is
already established by the `&&` before master picks among sub-permissions. That
is master authority applied *after* the question, which is correct.

### D · A module owned by nobody was treated as free

`licence_blocks()` answered "no block" for an access module that no product
module claims — *"nobody licensed this, so it must be free."* That is the same
absence-of-evidence defect Milestone 3 closed on the entitlement ceiling, one
layer further in.

**Closed by** `licence_blocks()` now refusing an unowned access module, with one
verified exception handled first: `admin` is a *product* key used as a gate
value at three routes (registry anomaly A1). Without that exception,
notifications, integrations and system status would have been shut off for every
installation, including paying ones.

`is_master_of()` carried the identical fail-open and was corrected to match, so
there is one rule with two readers rather than two rules that can drift apart.

---

## 3. What did NOT change

- No database schema changes.
- No second entitlement, permission, routing or RBAC engine. Every change
  reuses `can()`, `licence_enabled()`, `module_state()` and `is_master_of()`.
- No renaming of `hr`. One authoritative Recruitment/HR module identity.
- No change to Operations, Quality, the Dashboard, the Money engine, the
  Reporting engine, Marketplace/Connect, or the approval and pipeline engines.
- **No existing test was weakened, skipped or deleted.** The suite grew from
  7,398 to 7,474 passing, with zero failures and zero skips.

---

## 4. Files changed

| File | Change |
|---|---|
| `lib/ops.php` | `ops_module_family()` added; gate consults it for unmapped routes; 2 master sites scoped |
| `lib/licence.php` | `licence_blocks()` refuses unowned modules; `is_master_of()` made consistent |
| `lib/careers.php` | `careers_enabled()` asks the HR entitlement question first |
| `lib/idems.php` | 17 master sites scoped to the module in play |
| `lib/hwpoints.php`, `lib/costing.php`, `lib/areas.php` | 4 master sites scoped |
| `views/` (7 files) | 14 master sites scoped |
| `tests/test_m5_runtime_entitlement.php` | new — 76 assertions |
| `deploy-check.php` | checksums regenerated |
