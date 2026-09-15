# Phase 2 · M3 — Authorization Proof

*A final authorization review of the M3 surface. Not a rebuild: one gap was
found, and closing it took one gate following the pattern M14 already
established.*

## 1. Every M3 function, every call site, every boundary

M3 introduced ten functions in `lib/reqfulfil.php` and one route. Six are
internal helpers reached only from within that file; four are called from
outside it.

### Called from outside the library

| Function | Caller | Route / action | Tenant | Branch / object scope | Permission | Entitlement |
|---|---|---|---|---|---|---|
| `reqf_migrate()` | `lib/db.php:555`, in `run_schema()` | boot — no user input | per-database | n/a — schema only | n/a | n/a |
| `reqf_sync()` | `lib/ops.php:5025` | `requisition-edit` POST | per-database | `req_scope_gate()` on entry to `ops_requisitions()` | `is_coordinator_level()` | `hiring` → **hr** |
| `reqf_sync()` | `lib/ops.php:5210` | `candidate-stage` POST | per-database | **`cand_scope_gate()`** on entry to `ops_candidates()` | `is_coordinator_level()` | `hiring` → **hr** |
| `reqf_sync()` | `lib/ops.php:5243` | `candidate-stage` POST (hire branch) | per-database | **`cand_scope_gate()`** | `is_coordinator_level()` | `hiring` → **hr** |
| `reqf_summary_text()` | `lib/ops.php:5245` | `candidate-stage` POST — read, for the confirmation message | per-database | **`cand_scope_gate()`** | `is_coordinator_level()` | `hiring` → **hr** |
| `reqf_sync()` ×2 | `lib/ops.php:5326` | `candidate-edit` POST — **both** sides of a move | per-database | **`cand_scope_gate()`** — checks the candidate's own requisition **and** the destination | `is_coordinator_level()` | `hiring` → **hr** |
| `reqf_sync()` | `lib/ops.php:5340` | `candidate-new` POST | per-database | **`cand_scope_gate()`** — checks the destination | `is_coordinator_level()` | `hiring` → **hr** |
| `reqf_counts()` | `views/ops/requisition_detail.php:18` | rendered by `requisition` GET — read | per-database | `req_scope_gate()` | `is_coordinator_level()` | `hiring` → **hr** |
| `reqf_summary_text()` | `views/ops/requisition_detail.php:33` | same — read | per-database | `req_scope_gate()` | `is_coordinator_level()` | `hiring` → **hr** |
| `reqf_cancel()` | `ops_requisition_cancel_vacancies()` | `requisition-cancel-vacancies` POST | per-database | `req_scope_gate()` called **first, before any id is read** | `is_coordinator_level()` | `hiring` → **hr** |

### Reached only from inside the library

`reqf_row()`, `reqf_requested()`, `reqf_derive_status()`, `reqf_people()` — plus
`reqf_counts()` and `reqf_summary_text()` where they are used internally. They
take an id and read; none writes. Their authorization is whatever the route that
reached them applied.

### Tenant boundary — the same answer everywhere

EXAACT gives each customer its **own database**. There is no `tenant_id` column
and no cross-tenant query to get wrong. M3 added no cache and no shared state, so
there is no cross-tenant surface to defend. (The one historic risk — a cache
outliving the database it was read from — was found and fixed in M2, and the
audit removed the one cache M3 briefly introduced.)

## 2. Candidate movement — proven both ways

### A move within your own branch recalculates both sides

```
before   RQ-A filled=1 PARTIALLY_FILLED   RQ-A2 filled=0 OPEN
move     candidate A-to-A2
after    RQ-A filled=0 OPEN               RQ-A2 filled=1 PARTIALLY_FILLED
```

The source gives the seat back and reopens; the destination takes it. Both are
recomputed, in one place, from the candidate rows.

### A move across the branch boundary is refused

This is the gap this review found. **It was pre-existing**: M14 gave scope gates
to leads, opportunities, complaints, quotations, requisitions and project
costings — the candidate register was never among them. M3 made it more
consequential, because moving a candidate now also **writes a status change** to
the destination requisition.

Driven end to end as a branch-A coordinator posting `candidate-edit` with a
branch-B `requisition_id`:

```
BEFORE THE GATE
   candidate now on : RQ-B  (Branch B — a requisition this user cannot see)
   RQ-A (Branch A)  : filled=0 status=OPEN
   RQ-B (Branch B)  : filled=1 status=PARTIALLY_FILLED
   >> ALLOWED — and the fulfilment sync wrote a status change to that
      foreign requisition.

AFTER THE GATE
   candidate now on : RQ-A  (unchanged)
   RQ-A (Branch A)  : filled=1 status=PARTIALLY_FILLED
   RQ-B (Branch B)  : filled=0 status=OPEN
   >> REFUSED — the move did not cross the branch boundary.
```

### The fix

One gate, `cand_scope_gate()`, at the top of `ops_candidates()` — the same shape
as `req_scope_gate()`. A candidate has no branch of its own; it inherits the
branch of the requisition it is raised against, so that is what scope is judged
on. It checks **both** ends of a move: the candidate's current requisition, and
the one named in the POST.

## 3. Can direct invocation bypass scope?

**The architecture keeps authorization at the route boundary, deliberately** —
`ops_require()` flashes and redirects, which only makes sense in a request. The
question is therefore not "is there a check inside every helper" but "is there a
**reachable** path that mutates without passing a gate".

Enumerated exhaustively:

| Mutating path | Reachable how | Gate |
|---|---|---|
| `reqf_sync()` | only from the five call sites above | every one is behind `req_scope_gate()` or `cand_scope_gate()` |
| `reqf_cancel()` | only from `ops_requisition_cancel_vacancies()` | `req_scope_gate()` **before** the id is read |
| `reqf_migrate()` | only from `run_schema()` at boot | schema only; no user input reaches it |
| The `cancelled_qty` / `status` columns | only through the two functions above | — |

**There is no route, no AJAX endpoint and no background job that reaches a
fulfilment mutation without first passing a scope gate.** Every entry point in
the table in §1 is inside a dispatcher whose first statement is a gate, and
mutations proved that:

| Mutation | Result |
|---|---|
| Candidate scope gate removed entirely | **1 failed** ✅ — and the end-to-end attack succeeds again |
| Gate stops checking the **destination** requisition | **2 failed** ✅ |
| Gate moved to run **after** the routes instead of on entry | **1 failed** ✅ |
| Scope gate removed from the cancel route | **2 failed** ✅ |

A helper called from a future route with no gate would of course be unguarded —
which is why the gate is on the **module door** rather than sprinkled per route,
and why a test asserts it is the first statement in each dispatcher.

**Master users do not bypass this.** M3 contains no `is_master()` override
anywhere; `scope_allows()` honours master status as the product already defines
it, and entitlement is decided by `licence_enabled()` alone.

## 4. The five Department mappings

Applied after the audit, as proposed. **No stored department value was
rewritten** — each mapping is an approved vocabulary relationship.

| Word | Resolves to | Recorded as |
|---|---|---|
| `QAQC`, `QA / QC` | Quality | approved term on the existing department |
| `HSE`, `HSE / Safety` | Safety / HSE | approved term on the existing department |
| `FINANCE`, `Finance` | Commercial / Finance | approved term on the existing department |
| `NDT`, `n.d.t.` | NDT | new department (Technical) + approved terms |
| `HR`, `hr` | Human Resources | new department (Support) + approved terms |

Evidence, from driving it:

```
RQ-OLD1 stored 'QAQC' -> shows 'Quality'  (stored value unchanged: QAQC)
RQ-OLD2 stored 'NDT'  -> shows 'NDT'      (stored value unchanged: NDT)
words still awaiting a decision: none
departments before=19 after=19            (re-applying duplicates nothing)
```

Three properties, each mutation-tested:

- it runs **once** per workspace;
- it acts **only** on a word still awaiting a decision — a workspace that had
  already decided one of these differently keeps its own mapping (mutation:
  **1 failed**);
- it **rewrites nothing**, so undoing a decision is removing one term.

NDT as a department and NDT as a `trade` discipline remain separate vocabularies
and do not resolve to each other.

## 5. Regression

| Engine | Before this review | After the gate |
|---|---|---|
| SQLite | 8620 passed, 0 failed | _recorded below_ |
| MariaDB 10.11.14 | 8621 passed, 0 failed | _recorded below_ |

Focused suites: multi-vacancy **99 assertions**, vocabulary **144**, forms **67**.
