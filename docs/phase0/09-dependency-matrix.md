# 09 — CROSS-MODULE DEPENDENCY MATRIX
Covers deliverable **24**. Required by the brief **before** any implementation.

---

## 1. THE HEADLINE COUPLING RISK

**Recruitment is not a separate module in code. Its core write paths live inside Operations' own file.**

Measured:

| Write path | Location | Owning module by filename |
|---|---|---|
| `INSERT INTO requisitions` | `lib/ops.php:4932` | **Operations** |
| `UPDATE requisitions` (save) | `lib/ops.php:4919, 4922, 4935` | **Operations** |
| `UPDATE requisitions … status='HIRED'` | `lib/ops.php:5091` | **Operations** |
| `INSERT INTO candidates` | `lib/ops.php:5172` | **Operations** |
| `INSERT INTO requisitions` | `lib/projcosting.php:323` | **Money** |
| `UPDATE requisitions SET position_id` | `lib/position.php:297` | Recruitment |

`lib/ops.php` is 8,285 lines and is the Operations module's principal file. **Any change to requisition or candidate persistence is therefore a change to Operations.**

**Consequence for the brief's §39 "absolute no-break rule":** Recruitment work cannot be assumed isolated. Every Phase touching requisition/candidate write paths **must** run the Operations regression suite (128 files / 2010 assertions) — this becomes a mandatory gate.

---

## 2. Table reference spread (measured)

| Recruitment table | Referenced in NON-recruitment lib files |
|---|---|
| `candidates` | **31** |
| `positions` | **19** |
| `requisitions` | **11** |
| `job_offers` | 1 |

Recruitment libraries in turn touch: `jobs` (2 files), `inspectors` (1), `business_partners` (1), `cx_*` (1), `invoices` (0).

**Reading:** the dependency is **asymmetric and inbound**. Recruitment depends on little; a great deal depends on recruitment's tables. This is the opposite of what "a cleanly separable module" looks like, and it raises the blast radius of every recruitment change.

---

## 3. Module → module dependency matrix

Rows depend on columns. **H** = hard (code/schema), **S** = soft (shared masters/settings), **—** = none.

| ↓ depends on → | Admin | Ops | Recruit | Connect | Report | Quality | Money | Sales |
|---|---|---|---|---|---|---|---|---|
| **Admin (core)** | — | — | — | — | — | — | — | — |
| **Operations** | H | — | **H** (owns req/cand CRUD) | S | H | H | S | S |
| **Recruitment** | H | **H** (`inspectors` on hire; CRUD in `ops.php`) | — | S (`cx_identity_link`) | — | — | S | S (`business_partners`) |
| **Connect/Marketplace** | H | H (`jobs` deployment, `inspectors`) | S | — | — | — | H (`mkt_*`) | — |
| **Reporting/IDEMS** | H | H (`jobs`, `report_docs`) | — | — | — | H | S | — |
| **Quality** | H | H | — | — | H | — | — | — |
| **Money** | H | H (`jobs`, `expenses`) | **H** (`projcosting` inserts requisitions) | S | S | — | — | H (quotes) |
| **Sales/CRM** | H | S | S | — | — | — | H | — |

### Shared services every module touches
`settings`, `lookup_types`/`lookup_values`, `users` + `can()`, `offices` + `scope_clause()`, `business_partners`, `ops_mail()`, TAPI metrics.

**Implication:** a change to lookups, RBAC or scope is a **platform-wide** change and must trigger the full suite, not a module suite.

---

## 4. Protected paths — touching these requires full regression

| Path | Why |
|---|---|
| `lib/ops.php` | 8,285 ln; owns Operations **and** recruitment CRUD; 58 bare `is_master()` |
| `lib/access.php` | single `can()` choke point for the whole platform |
| `lib/licence.php` | the entitlement engine itself |
| `lib/db.php` `run_schema()` | order-dependent, hand-ordered, no rollback |
| `config.php:87-151` | tenant resolution — a bug here swaps an entire customer's database |
| `lib/idems.php` | 10,942 ln; healthy; out of programme scope |
| `views/dashboard.php` | protected asset (brief §21) |

---

## 5. Dependency rules adopted for all later phases

1. **No new inbound dependency on recruitment tables** from other modules.
2. Recruitment persistence must migrate **out of `lib/ops.php`** into recruitment libraries before it is extended — otherwise every recruitment feature keeps enlarging Operations' blast radius. (Sequenced as **Phase 2 item 8 — safe extraction only**, behind Operations regression. Operations itself is never rebuilt: `15-ARCHITECTURE-LOCK.md` §5.)
3. `lib/projcosting.php:323` inserting requisitions must be re-pointed at a recruitment-owned function, not duplicated.
4. Any schema change is **additive only** (no rollback exists).
5. Any change to shared services (lookups, RBAC, scope, licence) runs the **full 6948-assertion suite** plus the MySQL suite once it exists.
