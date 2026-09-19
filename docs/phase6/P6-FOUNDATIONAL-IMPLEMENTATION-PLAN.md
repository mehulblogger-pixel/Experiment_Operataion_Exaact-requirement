# Phase 6 — Batch 1 Implementation Plan
## Identity Write Safety · Authority · Scope Foundation

*The plan for the first foundational batch. No product code, schema, route,
permission or migration is changed by this document.*

**Contract:** `P6-PREIMPLEMENTATION-AUDIT.md` · `P6-PERSON-REPRESENTATION-ADDENDUM.md` ·
`P6-CANONICAL-DOMAIN-MODEL.md` · `P6-DUPLICATE-AND-IDENTITY-RULES.md` ·
`P6-ACTION-PATH-MATRIX.md` · `P6-EXTERNAL-ACCOUNT-REPRESENTATION-ADDENDUM.md` ·
`P6-BUSINESS-INVARIANTS.md`.

**In scope:** R22 · R24 · R25 · R15 · R16 · R3 · R11 (concurrency aspect).
**Invariants:** I15 · I16 · I23 · I25 · I26 · I27 · I28 · I29, and the parts of
I22 / I41 / I42 that the batch's own writes create.

**Explicitly NOT in this batch:** R1 · R2 · R4 · R7 · R18 · R19 · R20 · R21 ·
R23 · R26 · R27 · R28 · R29 · R30 · R31, and every form of identity convergence.

---

## 0. Plain-English summary

Today the system can create people while you are only *reading* a screen, can
act on a record number posted from a browser without asking whether you are
allowed to touch that record, checks two different purchases for the same
action depending on which screen you came from, never asks which branch you
belong to, and relies on PHP to prevent duplicate links that the database
itself does not prevent.

**Batch 1 does not converge any identities.** It makes identity writing safe
*first*, so that when convergence starts it is building on ground that holds.
Nothing in this batch merges, links, retires or renames anything.

Two things this audit found that were **not previously recorded**, both proved
by running the code rather than reading it — see §17.

---

# 1. Audit of the existing implementation surface

*Read-only inspection. Nothing below was modified.*

## 1.1 The link ledger

`lib/connect_identity.php:23` · `connect_identity_migrate()`

```
cx_identity_link
  id · professional_id · inspector_id · party_id · candidate_id
  method · status(LINKED|UNLINKED) · note
  linked_by · linked_at · unlinked_at
```

Indexes — **all three non-unique**: `ix_cx_idlink_pro`, `ix_cx_idlink_insp`,
`ix_cx_idlink_cand`. `candidate_id` is added additively via `ensure_column()`.

**Two independent relationship axes share this one table:**

| Axis | Rows look like | Created by |
|---|---|---|
| **Inspector axis** | `professional_id>0, inspector_id>0, candidate_id=0` | `connect_identity_link_create()` |
| **Candidate axis** | `professional_id>0, inspector_id=0, candidate_id>0` | `connect_identity_candidate_link_create()` |

## 1.2 The writers

| Function | Line | Guards it has | Guards it lacks |
|---|---|---|---|
| `connect_identity_link_create()` | `connect_identity.php:98` | both rows exist; neither end already linked | scope · entitlement · DB uniqueness · axis awareness |
| `connect_identity_candidate_link_create()` | `:148` | both rows exist; candidate not already linked | scope · entitlement · DB uniqueness · **professional side unchecked** |
| `connect_identity_unlink()` | `:121` | link exists and is LINKED | **ownership · scope · entitlement** |

All three do `SELECT → if absent → INSERT`. **No transaction, no constraint.**

## 1.3 The resolvers

`connect_identity_of_professional()` · `connect_identity_of_inspector()` ·
`connect_identity_of_candidate()` — each `WHERE … AND status='LINKED' ORDER BY
id DESC LIMIT 1`.

> **`ORDER BY id DESC LIMIT 1` is how duplicates hide.** With two live rows the
> resolver silently prefers the newest and never reports the conflict. This is
> why R3 cannot be closed in PHP alone.

**None of the three filters by axis** — the finding in §17.1.

## 1.4 The routes

| Route | Registered | Module gate | Role gate | Entitlement |
|---|---|---|---|---|
| `/connect-identity` | `ops.php:3506` | — | `connect_identity_admin_can()` | **`licence_module_live('connect')`** |
| `/candidate-link-pro` | `ops.php:5680` | `hiring` (`ops.php:2497`) | `is_coordinator_level()` | **none** |
| `/candidate-unlink-pro` | `ops.php:5690` | `hiring` | `is_coordinator_level()` | **none** |
| `/candidate-link-person` | `ops.php:5667` | `hiring` | `is_coordinator_level()` | **none** |

`connect_identity_admin_can()` (`:265`) = `connect_enabled()` **and**
`connect_market_can()`. `connect_enabled()` (`connect_market.php:391`) asks
`licence_module_live('connect')`. **This is the entitlement asymmetry, R25,
located exactly.**

`/candidate-unlink-pro` reads `$_POST['link_id']` and passes it straight to
`connect_identity_unlink()`. It never checks that the link belongs to candidate
`$id`. **This is R24, located exactly.**

## 1.5 The read path that writes — R22

`lib/ops.php:1021`, inside `inspectors_list()`:

```php
    // Self-heal the "imported people don't show for allocation" gap …
    link_inspector_users();
```

`link_inspector_users()` (`ops.php:984`) selects active `INSPECTOR`-role logins
with no `inspector_id`, calls `team_member_create()` for each, then
`UPDATE users SET inspector_id=…`.

**`inspectors_list()` is its only production caller.** Confirmed:

```
grep link_inspector_users( → lib/ops.php:1021  (1 production call site)
                             tests/test_import_team_link.php:44,52  (explicit)
```

**The 17 production call sites of `inspectors_list()`** — all of them consumers
of a person list, none of them wanting a write:

| # | File:line | Why it calls | Needs a person list? | Relies on the side effect? |
|---|---|---|---|---|
| 1 | `lib/ops.php:1093` | name map for job display | yes (read) | no |
| 2 | `lib/ops.php:4991` | allocate picker | yes | no |
| 3 | `lib/ops.php:5120` | requisition form picker | yes | no |
| 4 | `lib/ops.php:5944` | voucher list filter | yes | no |
| 5 | `lib/ops.php:6314` | schedule board | yes | no |
| 6 | `lib/ops.php:6962` | visit plan | yes | no |
| 7 | `lib/ops.php:6987` | visit plan (second read) | yes | no |
| 8 | `lib/ops.php:8175` | manday utilisation | yes | no |
| 9 | `lib/ops.php:8198` | activity picker | yes | no |
| 10–13 | `lib/ops.php:8330, 8344, 8431, 8540` | **user form** — the inspector dropdown | yes | **see note** |
| 14 | `lib/equipment.php:423` | equipment custody picker | yes | no |
| 15 | `lib/rating.php:118` | rating roll-up | yes | no |
| 16 | `lib/callprofit.php:94` | profitability picker | yes | no |
| 17 | `lib/timesheet.php:132` | timesheet grid | yes | no |

> **Note on 10–13.** These four render `ops/user_form` — the screen on which an
> administrator links a login to a team member by hand. It is the *one* place
> where the side effect is adjacent to the intent. Even here it is wrong: the
> form offers a picker, and the side effect pre-empts the choice the
> administrator came to make. **No caller reads a value that only the side
> effect produces.**

**The controlled path already exists.** `org_import_link_team()`
(`lib/orgadmin.php:1020`) links on the way in, called at `orgadmin.php:985` and
`:995`; the single-user save links inline at `ops.php:8324`. The read-path call
is a *third* copy of a job two explicit paths already do.

**Existing users without a link** are therefore a **finite historical backlog**,
not an ongoing need — every current creation path links explicitly.

## 1.6 Scope and tenancy helpers that already exist

| Helper | File | Semantics |
|---|---|---|
| `scope_allows($officeId, $sbu)` | `access.php:820` | scalar guard; NULL office → Ahmedabad |
| `scope_office_allows($officeId)` | `access.php:852` | scalar guard; NULL office → visible to all |
| `scope_offices()` / `scope_sbus()` | `access.php:772` | `'ALL'` or a list |
| `inspector_login_conflict()` | `access.php:~862` | one inspector ↔ at most one active login |

**No new scope engine is needed.** `scope_allows()` is the exact scalar twin
Phase 2 §51 built for this class of problem.

**Scope classes of the three endpoints** — read from the live schema:

| Endpoint | Office | SBU | Class |
|---|---|---|---|
| `inspectors` | `home_office_id` ✔ | `sbu`, `sbus` ✔ | **office + SBU scoped** |
| `candidates` | ✘ none | `sbu` ✔ | **SBU scoped only** |
| `cx_professionals` | ✘ none | ✘ none | **tenant-global** |

**The three ends do not share a scope class.** This is the §6 mismatch, and it
is why R15 is split in §5 below.

## 1.7 Tenancy

One database per tenant; `db()` returns the tenant connection; there is no
column, no join and no second connection in any identity path. Isolation is
**structural**. It has **never been tested**. I15 stays NOT ESTABLISHED until
the test in §11 E exists.

## 1.8 Uniqueness and concurrency precedents already in the codebase

| Precedent | File | What it proves |
|---|---|---|
| `books_unique_number_index()` | `books.php:234` | build a UNIQUE index **only when legacy data is clean**; surface duplicates instead of aborting boot |
| SQLite partial index vs MySQL NULL | `books.php:242–247` | the two engines' divergence, already solved once |
| `ux_cx_pro_email` | `connect_pro.php:42` | `try { CREATE UNIQUE INDEX } catch {}` inside an epoch-guarded migration |
| `ux_cx_cbench_pro` | `connect_client_bench.php:42` | composite UNIQUE on a link table — the closest analogue |
| `ensure_column()` | `db.php` | additive, idempotent column add |
| `db_epoch()` | `db.php:12` | run-once-per-database migration guard |

**Nothing new is required.** R3 is the `books.php` pattern applied to a link
table.

## 1.9 Audit helper

`act_log($entityKind, $entityId, $kind, $subject, $opt)` — `activity.php:433`,
writing to `activities`. Normalises an unregistered `$entityKind` to `''`
(`:441`) and an unregistered `$kind` to `'NOTE'` (`:442`). **See §17.2.**

---

# 2. R22 — Remove the write-from-read design

**Target architecture: READ → READ ONLY.** No replacement side effect anywhere.

## 2.1 The change

| Step | Where | What |
|---|---|---|
| **R22-a** | `lib/ops.php:1021` | **Delete the `link_inspector_users();` call.** One line |
| **R22-b** | `lib/ops.php:984` | **Keep `link_inspector_users()` as a function.** It becomes an explicitly-invoked reconciliation, not a hidden one. Add the guard in R22-d |
| **R22-c** | existing health engine (`system_status()`) | **Report** the count of active inspector-role logins with no team member as a health finding. Read-only. Visibility replaces the silent write |
| **R22-d** | `lib/ops.php:984` | Require an authorised, explicit caller: refuse unless the caller has the People-administration right the user form already requires. A function that creates people must check on its own — **I27** |
| **R22-e** | existing People/users screen | One explicit action that runs the reconciliation and reports what it did. **Extends an existing screen; adds no new screen** |

> **R22-a alone satisfies I23.** R22-c/d/e exist so that removing the side
> effect does not lose the business capability it was serving.

## 2.2 Why no caller breaks

- No caller reads a field only the side effect produces (§1.5 table).
- Both creation paths already link explicitly (`org_import_link_team()`,
  `ops.php:8324`).
- **The existing test already separates the two.** `test_import_team_link.php`
  asserts the import result via `org_import_apply()`, and tests the self-heal by
  calling `link_inspector_users()` **directly** at lines 44 and 52 — never
  through `inspectors_list()`. **The test is expected to pass unmodified.**
  This is a prediction, and §11 N treats a failure of it as a real signal, not
  as a test to adjust.

## 2.3 What must NOT happen

- No lazy "link on first use" anywhere else. That is the same defect relocated.
- No linking from a search, export, report, dashboard or API read.
- No automatic run on boot. Boot is not an authorised actor.

---

# 3. R24 — Authorisation is not a record number

## 3.1 The rule

**Every identity route must prove the actor may act on *this* relationship**,
established from the business relationship, not from the fact that a row with
that id exists.

## 3.2 Refusal order — locked from Phase 3/5

Authorisation is evaluated **before** any information-revealing refusal. A
caller who may not act on a record must not learn, from the wording of the
refusal, whether that record exists.

```
1. module enabled?          → generic refusal
2. entitlement?             → generic refusal
3. permission / role?       → generic refusal
4. scope on the anchor?     → generic refusal
5. ── only now may a refusal name the record ──
6. anchor exists?
7. target exists?
8. relationship belongs to the anchor?
9. state valid (LINKED / not already linked)?
10. duplicate / uniqueness
11. write + audit
```

**Steps 1–4 must all produce the same message.** Today `/candidate-unlink-pro`
starts at step 11.

## 3.3 Per-route design

### `/candidate-unlink-pro` — **the defect**

| Field | Today | Batch 1 |
|---|---|---|
| Actor | `is_coordinator_level()` | unchanged |
| Entitlement | none | `connect_identity_admin_can()` (§4) |
| Permission | module `hiring` | module `hiring` + entitlement |
| Anchor | `$_GET['id']` (candidate) — **unused** | candidate `$id`, **loaded and scope-checked** |
| Target | `$_POST['link_id']` — **trusted blindly** | link loaded, **must satisfy `candidate_id === $id`** |
| Relationship | any link in the workspace | only this candidate's own live link |
| Scope | none | `scope_allows(null, $cand['sbu'])` |
| State | `status='LINKED'` | unchanged |
| Audit | untyped note (§17.2) | attributable entry, **including the refusal** |
| Refusal | "No such active link." | generic before step 5; "That link does not belong to this candidate." after |

> **The simplest correct form uses no new API.** The route resolves the link
> through `connect_identity_of_candidate($id)` and compares ids before calling
> `connect_identity_unlink()`. The ledger function keeps its signature.

### `/candidate-link-pro`

| Field | Batch 1 |
|---|---|
| Anchor | candidate `$id` — load, scope-check on `sbu` |
| Target | `$_POST['pro_id']` — must exist; tenant-global, so no scope check (§5) |
| Entitlement | `connect_identity_admin_can()` — **the R25 change** |
| Duplicate | DB constraint U3 decides, not PHP (§6) |
| Audit | attributable entry |

### `/candidate-link-person`

Writes `candidates.person_ref` via `person_link_rows()` — a **different**
mechanism, not `cx_identity_link`. In batch 1 it receives **only** the anchor
scope check and the refusal ordering. Its own duplicate/audit/reversal gaps are
**R18/R21 — not this batch.**

### `/connect-identity`

Already carries the entitlement. Batch 1 adds: scope check on the inspector end
(`scope_allows($insp['home_office_id'], $insp['sbu'])`), and the refusal
ordering. **The unlink action on this console has the same trust-the-id shape as
`/candidate-unlink-pro`** and gets the same ownership check.

---

# 4. R25 — One entitlement rule for one ledger

## 4.1 The rule

> **Every writer of `cx_identity_link` asks the same question:
> `connect_identity_admin_can()`.**

No new entitlement engine, no new permission, no second identity gate. The
existing function already composes `licence_module_live('connect')` +
`connect_market_can()`.

| Writer | Today | Batch 1 |
|---|---|---|
| `/connect-identity` | `connect_identity_admin_can()` | unchanged |
| `/candidate-link-pro` | `hiring` + coordinator | `hiring` + coordinator + `connect_identity_admin_can()` |
| `/candidate-unlink-pro` | `hiring` + coordinator | same |
| any future writer | — | same, enforced by the function-level guard below |

**Enforced at the function, not the route** (I27): `connect_identity_link_create()`,
`connect_identity_candidate_link_create()` and `connect_identity_unlink()` each
check for themselves, so a future caller cannot inherit a weaker gate.

## 4.2 The business consequence — **OWNER APPROVAL REQUIRED**

A customer who bought Recruitment but **not** Connect can link a candidate to a
marketplace professional today. After this change they cannot.

**Recommendation: correct, and make the change.** The link's whole purpose is to
treat a marketplace professional as the same person; a customer without the
marketplace has no professional to link to, and the capability is unusable in
any case. Allowing the write is entitlement leakage.

**This is not blocked on Q1–Q18.** It is a business consequence the owner must
accept before implementation, because it removes a capability from a live
configuration and requires `docs/02-permission-matrix.md` to be updated in the
same commit (CLAUDE.md hard rule).

---

# 5. R15 / R16 — Scope and tenant

## 5.1 The scope split — and why it is a split

The three endpoints have three different scope classes (§1.6). §6 of the
instruction forbids inventing a universal rule. So batch 1 enforces only what is
already decided and defers what is not:

### 5.1-A — **ENFORCED NOW: per-end visibility**

> **You may not create or remove a relationship involving a record you are not
> allowed to open.**

This is not a new rule. It is the rule `scope_allows()` already applies to every
detail page, PDF and file stream. Applying it to a relationship endpoint is
consistency, not invention.

| End | Guard | Helper |
|---|---|---|
| Inspector | office + SBU | `scope_allows($insp['home_office_id'], $insp['sbu'])` |
| Candidate | SBU only | `scope_allows(null, $cand['sbu'])` |
| Professional | none — tenant-global | no scope check; **tenancy is the boundary** |

### 5.1-B — **DEFERRED: the relationship's own scope class**

> Does a link between a **branch-scoped inspector** and a **tenant-global
> professional** itself have a branch? Can a Branch-A coordinator link a
> Branch-B inspector to a professional both branches use?

**5.1-A does not answer this, and does not pretend to.** It only guarantees that
the actor could already see both ends. Whether the *relationship* is additionally
branch-bound is **Q5 / Q11**, and remains open.

**Consequence, stated plainly:** after batch 1, I16 moves from VIOLATED to
**PARTIAL**, not to HOLDS. Claiming otherwise would be exactly the failure this
programme keeps finding.

## 5.2 R16 — tenancy: proof, not argument

| Claim | Status after batch 1 |
|---|---|
| **Structural tenant isolation** — separate database, no tenant column, no cross-connection query in any identity path | **PROVEN BY INSPECTION** (§1.7) |
| **Tested tenant isolation** — an id from tenant B cannot resolve in tenant A | **PROVEN ONLY IF the test in §11 E is written and passes** |

The batch requires no code change for R16. It requires a **test that does not
exist**. If that test cannot be built in the harness, R16 stays NOT ESTABLISHED
and is reported as such — **it is not upgraded by argument.**

---

# 6. R3 / R11 — Database-level uniqueness and concurrency

## 6.1 What is actually unique

| Question | Answer | Evidence |
|---|---|---|
| Which relationships are unique? | Three, not one | §6.2 |
| Which columns identify them? | See U1–U3 | §6.2 |
| Does direction matter? | **No — direction is structural.** `professional_id`, `inspector_id`, `candidate_id` are separate typed columns; a pair cannot be stored reversed | `connect_identity.php:26` |
| Can the same pair appear in reverse? | **Structurally impossible** | as above |
| How are inactive relationships treated? | **Never constrained.** History is unlimited | §6.3 |

## 6.2 The three unique keys

| Key | Rule | Business meaning |
|---|---|---|
| **U1** | one live **inspector-axis** row per `professional_id` | a professional is at most one inspector |
| **U2** | one live **inspector-axis** row per `inspector_id` | an inspector is at most one professional |
| **U3** | one live **candidate-axis** row per `candidate_id` | a candidate is at most one professional |

**There is deliberately no unique key on `professional_id` for the candidate
axis.** One person may legitimately hold several candidate records (**I3**), and
each may link to the same professional. Constraining it would make I3
unenforceable — this is the one place where the obvious constraint is the wrong
one.

> This is also the **only** point where §6 and §7 of the instruction could have
> been satisfied by a single composite key. They could not: one table, two axes,
> asymmetric cardinality.

## 6.3 How the constraint is expressed — identically on both engines

Three **NULL-able key columns**, maintained by the writer, added via
`ensure_column()`:

| Column | Value | NULL when |
|---|---|---|
| `uq_pro_insp` | `professional_id` | not LINKED, **or** not the inspector axis |
| `uq_insp` | `inspector_id` | not LINKED, **or** not the inspector axis |
| `uq_cand` | `candidate_id` | not LINKED, **or** not the candidate axis |

Then three plain `CREATE UNIQUE INDEX` statements.

> **Why this and not a partial index.** `books.php:242` needs a SQLite partial
> index because MySQL has none. Here the same effect is achieved by the value
> itself: **both SQLite and MySQL permit unlimited NULLs in a UNIQUE index.** One
> statement, identical semantics on both engines, no driver branch. It is the
> `books.php` insight — *"MySQL relies on unissued drafts being NULL"* — applied
> deliberately rather than as a fallback.

Unlinking sets `status='UNLINKED'` **and** all three key columns to NULL in the
same `UPDATE`, releasing the slot. History is retained in full; **no row is ever
deleted** (locked architecture).

## 6.4 Legacy duplicates — surfaced, never merged

The `books_unique_number_index()` protocol exactly:

1. **Detect** live duplicates per key before building each index.
2. **If any exist: do not build that index.** Boot never fails for data it found.
3. **Report** them as a health finding and a review list.
4. **Never auto-resolve.** Choosing which of two live links survives is a
   business judgement about who a person is — the one thing Phase 6 forbids
   doing silently (*"AI may suggest. AI must NOT silently merge."*).
5. The index builds itself on a later boot once a human has resolved them.

**Each index is decided independently** — duplicates on U3 must not block U1.

## 6.5 Concurrency — the database decides

```
1. run the existing PHP pre-checks      (fast, friendly message, unchanged)
2. INSERT inside try/catch
3. on integrity violation:
      re-read the live row that won
      same pair?      → [true,  'Already linked.', <winner id>]   (idempotent)
      different pair? → [false, '<the same refusal the pre-check gives>', 0]
4. never swallow the exception; never retry blindly; never re-INSERT
```

**The PHP check stays** — it gives the good message in the common case. It is no
longer the *protection*; it is the *courtesy*. The constraint is the protection.

**Rejecting the second writer is a correct outcome, not an error.** Two
coordinators confirming the same person simultaneously must produce **one**
link, and the loser must be told the truth — not shown a crash, and not silently
given a second row.

**No transaction is required** for the single-row INSERT. Where the writer
performs two writes, §8 applies.

---

# 7. Migration strategy

**Forward-only · additive · idempotent · non-destructive.**

| Question | Answer |
|---|---|
| Migration required? | **YES** — inside the existing `connect_identity_migrate()` |
| New table? | **No** |
| New columns? | `uq_pro_insp`, `uq_insp`, `uq_cand` — all `INT NULL`, via `ensure_column()` |
| New indexes? | `ux_cx_idlink_pro_insp`, `ux_cx_idlink_insp`, `ux_cx_idlink_cand` |
| Existing columns changed? | **None** |
| Data rewritten? | **One additive back-fill only**: populate the three key columns for rows already `LINKED`. No business field is touched |
| Rows deleted? | **None, ever** |
| Legacy duplicates | Detected; the affected index is skipped and the duplicates surfaced (§6.4) |
| Quarantine? | **A review list, not a state change.** No row is unlinked, hidden or altered |
| Can deployment fail? | **No.** Every step is `try/catch`; a skipped index degrades to today's behaviour |
| Repeat behaviour | `db_epoch()` guard + `ensure_column()` + `CREATE UNIQUE INDEX` in `try/catch` — a second run is a no-op |
| SQLite | `ALTER TABLE ADD COLUMN` ✔ · multiple NULLs in UNIQUE ✔ |
| MariaDB | `ALTER TABLE ADD COLUMN` ✔ · multiple NULLs in UNIQUE ✔ |
| Rollback | **None invented.** Forward-only is the locked architecture. An index can be dropped by hand; nothing else changes |

---

# 8. Partial-failure model

## 8.1 The one multi-write in this batch's own scope

`link_inspector_users()` (`ops.php:996–997`):

```
WRITE A:  INSERT INTO inspectors …        (team_member_create)
WRITE B:  UPDATE users SET inspector_id=… 
```

| Question | Answer |
|---|---|
| One transaction? | **Yes.** Same database, same connection, adjacent statements. There is no reason they are not |
| If A succeeds and B fails? | Today: a **live orphan inspector**, indistinguishable from a real one, and the next call creates **another** — because the filter is `inspector_id IS NULL`. This is I22/I42, and the read-path call repeated it on **every page load** |
| Batch 1 | Wrap A+B in one transaction. A rollback leaves **no** inspector row |
| Retry | Safe — the transaction is all-or-nothing, and the `inspector_id IS NULL` filter makes a completed link a no-op |
| Two simultaneous retries? | Both may insert; neither can partially commit. **Duplicate-inspector prevention is R20, deliberately not in this batch** — §14 |
| Reconcilable? | The health finding in R22-c lists unlinked logins; existing orphans are **reported, not auto-fixed** |

> **Honest limit.** Batch 1 stops the *read path* creating orphans, and stops one
> operation creating a *half* one. It does **not** stop two authorised
> reconciliations racing to create two inspectors for one login — that needs a
> uniqueness rule on `inspectors`, which is **R20**. After batch 1, I22 and I42
> improve but do not reach HOLDS. Stated here so no one later reads the batch as
> closing them.

## 8.2 The batch's own writes

| Operation | Writes | Design |
|---|---|---|
| link create | 1 INSERT + 1 audit | audit failure must **not** fail the link (**I41** — the Phase 5 pattern); audit failure is itself visible |
| unlink | 1 UPDATE (status + three keys) + 1 audit | one statement; atomic by definition |

**No candidate→inspector conversion is implemented in this batch.**

---

# 9. Action-path coverage

Baseline: the accepted §26 matrix. **No new paths are created.**

### P1 · `inspectors_list()` → `link_inspector_users()`

| | |
|---|---|
| Current writer | `inspectors`, `users.inspector_id` |
| Current gap | write on read; 17 call sites; inherits the caller's gate; can orphan |
| Proposed control | call removed; function requires explicit authorised invocation; A+B in one transaction |
| Owning domain | Operations / Workforce |
| Entitlement | as the People screen today |
| Permission | People administration, checked **by the function** |
| Tenant | `db()` |
| Scope | the reconciliation acts on the caller's visible offices |
| State | only logins with no link |
| Duplicate | **R20 — out of batch** |
| Audit | the reconciliation reports what it did |
| Transaction | **yes** |
| Concurrency | transaction only; full protection is R20 |
| Expected refusal | unauthorised caller → refused, **nothing created** |

### P2 · `/candidate-unlink-pro`

| | |
|---|---|
| Current writer | `cx_identity_link.status` |
| Current gap | **any link id in the workspace**; no ownership, scope or entitlement |
| Proposed control | link must satisfy `candidate_id === $id`; candidate scope-checked; entitlement added |
| Owning domain | Identity |
| Entitlement | `connect_identity_admin_can()` |
| Permission | `is_coordinator_level()` + module `hiring` |
| Tenant | `db()` |
| Scope | `scope_allows(null, $cand['sbu'])` |
| State | link must be LINKED |
| Duplicate | n/a |
| Audit | entry on success **and on refusal** |
| Transaction | single UPDATE |
| Concurrency | second unlink is a no-op |
| Expected refusal | steps 1–4 generic; then "That link does not belong to this candidate." |

### P3 · `/candidate-link-pro`

| | |
|---|---|
| Current gap | no entitlement; no scope; PHP-only duplicate check |
| Proposed control | entitlement; candidate scope; **U3** |
| Duplicate | database (U3) |
| Concurrency | integrity violation → `[true,'Already linked.']` if same pair, else refusal |
| Expected refusal | no Connect → generic; wrong SBU → generic; already linked → named |

### P4 · `/connect-identity` (link **and** unlink)

| | |
|---|---|
| Current gap | no scope on the inspector end; unlink trusts the posted id |
| Proposed control | `scope_allows($insp['home_office_id'], $insp['sbu'])`; unlink re-reads and verifies |
| Duplicate | database (**U1**, **U2**) |
| Expected refusal | out-of-scope inspector → generic refusal |

### P5 · `/candidate-link-person`

| | |
|---|---|
| Current gap | writes `person_ref`; no audit, no reversal, no scope |
| Proposed control **in this batch** | **anchor scope check and refusal ordering only** |
| Not in this batch | audit (**R18**), reversal (**R18**), duplicate control (**R21**) |

---

# 10. Existing-operations safety

**51 files read `inspectors`. None is redesigned.**

| Surface | Exposure | Why it should be unaffected | Regression required |
|---|---|---|---|
| **Operations** | **Highest** — `inspectors_list()` feeds allocate, schedule, visit plan, timesheet, equipment | The list query is unchanged; only the write before it is removed | **Full** |
| **Workforce / People** | user form, import, org chart | explicit link paths untouched; user form loses only a side effect | **Full** |
| **Recruitment** | three candidate routes gain gates | linking gains an entitlement — an intended, owner-approved change | **Full** |
| **Marketplace** | `/connect-identity` gains scope | already entitlement-gated | **Full** |
| **Reporting** | reads `inspectors` | read-only; unaffected | **Full** |
| **Money** | vouchers, profitability read the list | read-only; unaffected | **Full** |
| **Dashboard / KPI** | Phase 5 engine reads the list | read-only; **Phase 5 is locked and must be numerically identical** | **Full + the Phase 5 battery** |

**The one behavioural change a user can observe:** a login created *outside* the
import and the user form no longer appears in the allocate list until someone
runs the reconciliation. That is the point — it becomes **visible and
attributable** instead of silent.

---

# 11. Test plan — written **before** implementation

Every test reads **actual business state** (row counts, stored values,
relationships), never only a return code.

| | Test | Asserts |
|---|---|---|
| **A** | Read-path immutability | Snapshot `inspectors`, `users.inspector_id`, `cx_identity_link`, `candidates`, `cx_tax_nodes`; call **all 17** call sites; **every count and value identical** |
| **A2** | Unlinked login stays unlinked | An inspector login with no link, after `inspectors_list()`, still has `inspector_id = 0` |
| **A3** | Reconciliation still works when asked | Explicit call links it — the capability is preserved, not deleted |
| **B** | Authorisation | Coordinator A posts a **valid** `link_id` belonging to **another candidate** → refused **and the link is still LINKED** |
| **B2** | Refusal ordering | Unentitled + out-of-scope + nonexistent id produce the **same** message |
| **B3** | Function-level guard | `connect_identity_unlink()` called **directly** with no session refuses — route-independent (**I27**) |
| **C** | Entitlement | Connect off → **every** writer refuses; ledger count unchanged |
| **C2** | Entitlement symmetry | Loop every writer; assert one identical gate |
| **D** | Branch scope | Branch-A user links a Branch-B inspector → refused; **no row written** |
| **D2** | Scope class honesty | A tenant-global professional is **not** office-filtered — asserts we did **not** invent the deferred rule |
| **E** | **Tenant isolation** | Two tenant databases; an inspector id valid only in B; attempt in A → no link, and B's data untouched. **This is the test that upgrades I15** |
| **F** | DB uniqueness | Two identical live rows **by direct SQL**, bypassing PHP → second rejected by the constraint (U1, U2, U3 separately) |
| **F2** | History unconstrained | Link/unlink the same pair five times → five history rows coexist |
| **F3** | I3 preserved | Two candidates → one professional: **both succeed** (the key we deliberately did not create) |
| **G** | Real-process concurrency | Two OS processes, one barrier, same pair → **exactly one** live link; loser gets a deterministic result, not a crash |
| **G2** | Idempotent race | Same pair twice → `[true,'Already linked.']`, **one** row |
| **H** | Legacy duplicates | Seed a duplicate before migration → index skipped, **both rows still present and LINKED**, duplicate reported |
| **H2** | Self-healing | Resolve by hand, re-run migration → index now builds |
| **I** | Partial failure | Force WRITE B to fail → **no** orphan inspector |
| **J** | Retry / idempotency | Run the reconciliation three times → no new rows after the first |
| **K** | Direct URL | GET each identity route with crafted ids → no write |
| **L** | POST | Forged `link_id`, `pro_id`, `inspector_id` → refused, no write |
| **M** | Internal bypass | Call each ledger function directly → same gates (**I27**) |
| **N** | Operations regression | **`test_import_team_link.php` passes unmodified** (§2.2) + full Operations suite |
| **O** | Recruitment regression | Full suite |
| **P** | Marketplace regression | Full suite |
| **Q** | Reporting regression | Full suite |
| **R** | Money regression | Full suite |
| **S** | Dashboard / KPI regression | Full suite **+ the Phase 5 battery, numerically identical** |

**Both engines.** SQLite for speed; **MariaDB is authoritative**. No MySQL claim
from SQLite evidence. MariaDB restarted between batteries.

---

# 12. Mutation plan — *specified here, run at implementation*

| # | Mutation | Test that must catch it |
|---|---|---|
| M1 | Restore `link_inspector_users()` in `inspectors_list()` | **A** |
| M2 | Remove the authorisation guard inside `link_inspector_users()` | **B3**, **M** |
| M3 | Drop the `candidate_id === $id` ownership check | **B** |
| M4 | Move the authorisation check **after** the existence check | **B2** |
| M5 | Remove `connect_identity_admin_can()` from the recruitment writer | **C**, **C2** |
| M6 | Remove the inspector scope check | **D** |
| M7 | Remove the candidate scope check | **D** |
| M8 | Make the professional end office-scoped (invent the deferred rule) | **D2** |
| M9 | Skip `CREATE UNIQUE INDEX` | **F** |
| M10 | Set the key columns on UNLINKED rows too | **F2** |
| M11 | Add the unique key we deliberately omitted (candidate-axis `professional_id`) | **F3** |
| M12 | `catch { return [true,'ok']; }` — swallow the conflict | **G** |
| M13 | `catch {}` then re-INSERT | **G**, **G2** |
| M14 | Build the index despite legacy duplicates | **H** |
| M15 | Auto-unlink legacy duplicates instead of reporting | **H** |
| M16 | Remove the transaction from the two-write operation | **I** |
| M17 | Bypass the canonical writer — raw INSERT into the ledger | **F**, **M** |
| M18 | Resolve a cross-tenant id | **E** |
| M19 | Make the four early refusals distinguishable | **B2** |
| M20 | Make the audit write failure fail the business write | **I41** probe |

**Each mutation names one assertion.** A suite crash (FATAL) is **not** a catch —
the Phase 5 rule, carried forward. No mutation testing is run in this task.

---

# 13. REUSE · EXTEND · CONNECT · MAP — and the one new helper

| Component | Verdict | Where |
|---|---|---|
| `cx_identity_link` | **EXTEND** — three NULL-able key columns | `connect_identity.php:23` |
| `connect_identity_link_create()` | **EXTEND** — gate, scope, conflict handling | `:98` |
| `connect_identity_candidate_link_create()` | **EXTEND** — same | `:148` |
| `connect_identity_unlink()` | **EXTEND** — gate, ownership | `:121` |
| `connect_identity_admin_can()` | **REUSE unchanged** — *the* entitlement gate | `:265` |
| `connect_enabled()` / `licence_module_live()` | **REUSE unchanged** | `connect_market.php:391` |
| `scope_allows()` / `scope_office_allows()` | **REUSE unchanged** | `access.php:820`, `:852` |
| `act_log()` | **CONNECT** — register the entity kind (§17.2) | `activity.php:433` |
| `ensure_column()` / `db_epoch()` | **REUSE unchanged** | `db.php` |
| `books_unique_number_index()` | **MAP** — its protocol, not its code (money-specific) | `books.php:234` |
| `link_inspector_users()` | **EXTEND** — authorised + transactional | `ops.php:984` |
| `org_import_link_team()` | **REUSE unchanged** — already the right shape | `orgadmin.php:1020` |
| `system_status()` | **EXTEND** — two new health findings | existing |
| `ops_require()` | **REUSE unchanged** | `ops.php:649` |

**New components proposed: one.**

> A small private helper inside `lib/connect_identity.php` that computes the
> three key-column values from a link row. It exists so the INSERT, the UPDATE
> and the back-fill cannot disagree about when a slot is occupied — the single
> most likely source of a subtle bug here. **It is a two-line pure function, not
> an engine**, and it is confined to the file that owns the ledger.

**No new engine of any kind is proposed.** No new table. No new permission. No
new route. No new screen.

---

# 14. File-level change plan

| # | File | Function / section | Purpose | Req | Inv | Risk | Regression surface | Tests |
|---|---|---|---|---|---|---|---|---|
| 1 | `lib/connect_identity.php` | `connect_identity_migrate()` | 3 columns, 3 unique indexes, duplicate pre-check, back-fill | R3 | I28 | **Med** — touches every install | Marketplace, Recruitment | F, F2, F3, H, H2 |
| 2 | `lib/connect_identity.php` | *(new private helper)* | one definition of an occupied slot | R3 | I28 | Low | — | F, F2 |
| 3 | `lib/connect_identity.php` | `connect_identity_link_create()` | entitlement + scope + conflict handling | R25, R15, R11 | I26, I27, I29 | Med | Marketplace | C, D, G, G2, M |
| 4 | `lib/connect_identity.php` | `connect_identity_candidate_link_create()` | same | R25, R15, R11 | I26, I27, I29 | Med | Recruitment | C, D, G, M |
| 5 | `lib/connect_identity.php` | `connect_identity_unlink()` | entitlement + caller-supplied ownership expectation | R24, R25 | I25, I27 | Med | both | B, B3, M |
| 6 | `lib/ops.php:1021` | `inspectors_list()` | **delete one line** | R22 | I23 | **High reach, low depth** — 17 callers | **Operations (all)** | A, A2, N |
| 7 | `lib/ops.php:984` | `link_inspector_users()` | authorisation guard + transaction | R22, I22 | I23, I27, I42 | Med | Workforce | A3, I, J, M |
| 8 | `lib/ops.php:5690` | `/candidate-unlink-pro` | ownership + scope + entitlement + refusal order | R24, R15, R25 | I25, I16 | Med | Recruitment | B, B2, D, K, L |
| 9 | `lib/ops.php:5680` | `/candidate-link-pro` | scope + entitlement + refusal order | R25, R15 | I26, I16 | Med | Recruitment | C, D, L |
| 10 | `lib/ops.php:5667` | `/candidate-link-person` | **anchor scope + refusal order only** | R15 | I16 | Low | Recruitment | D, L |
| 11 | `lib/connect_identity.php` | `ops_connect_identity()` | inspector scope + unlink ownership | R24, R15 | I25, I16 | Med | Marketplace | B, D, P |
| 12 | `lib/activity.php:49` | `ACT_ENTITIES` | register the identity-link entity kind | R24 audit | I6 (partial) | Low | Activity timeline | audit probe |
| 13 | *existing health engine* | `system_status()` | unlinked logins · duplicate links | R22, R3 | I42 | Low | Dashboard | A, H |
| 14 | *existing People screen* | explicit reconcile action | replaces the side effect | R22 | I23 | Low | Workforce | A3, J |
| 15 | `docs/02-permission-matrix.md` | identity rows | **same commit** — CLAUDE.md hard rule | R25 | — | — | — | — |
| 16 | `tests/test_p6_batch1.php` | *(new)* | the §11 battery | all | all | — | — | — |
| 17 | `tools/make_deploy_check.php` | *(re-run)* | checksum after source change | — | — | — | build | deploy-check test |

**Item 12 is inside the batch only because R24's refusal-audit requires an
attributable entry to write.** It does not implement R18.

---

# 15. Implementation order

Derived from the actual dependency chain, not a template.

| Step | Work | Why here |
|---|---|---|
| **0** | Write the §11 battery against **today's** code; record which fail | A test written after the fix proves only that the fix matches itself |
| **1** | `ACT_ENTITIES` registration (#12) | Everything after it wants an attributable audit entry |
| **2** | Migration: columns → duplicate pre-check → back-fill → indexes (#1, #2) | The constraint must exist before the writer relies on it |
| **3** | Canonical writers: entitlement, scope, conflict handling (#3, #4, #5) | The one place every route will call |
| **4** | Routes: ownership, scope, refusal order (#8, #9, #10, #11) | Depends on step 3 |
| **5** | **Read-path removal** (#6) + authorised transactional reconciliation (#7) | Last of the code changes — highest blast radius, so it lands on a green tree |
| **6** | Health findings + explicit action (#13, #14) | Restores visibility the side effect provided |
| **7** | Permission matrix (#15) in the **same commit** as step 3 | CLAUDE.md hard rule |
| **8** | Re-run `tools/make_deploy_check.php` (#17) | Or the checksum test fails |
| **9** | Full regression: SQLite, then **MariaDB restarted** | MariaDB is authoritative |
| **10** | Mutation battery (§12), own DB and own directory per run | Phase 5 protocol |

> **Steps 2 and 5 are the two that can hurt.** Step 2 touches every install's
> schema; step 5 has 17 call sites. They are deliberately separated so a
> regression points at one of them, not at both.

---

# 16. Stop conditions

| Check | Result |
|---|---|
| Does batch 1 require answering **Q1–Q18**? | **No** — with one bounded exception |
| **Exception** | Full R15 (a relationship's own scope class) needs **Q5/Q11**. **Resolved by scoping down, not by deciding**: batch 1 enforces per-end visibility only (§5.1-A) and leaves the relationship-level rule open (§5.1-B) |
| Is any business question converted into a technical default? | **No.** §5.1-B is stated as open; §6.2's omitted key is justified by locked invariant I3; §4.2 is escalated for approval, not assumed |
| Does batch 1 need R1/R2/R4/R7/R18–R21/R23/R26–R31? | **No** — with two bounded exceptions, both disclosed |
| **Exception 1** | R18's *registration* only (#12), because a refusal audit needs a place to go. No reversal, no ledger, no coverage of the other mechanisms |
| **Exception 2** | R20 is **not** implemented, so I22/I42 improve to PARTIAL only (§8.1). Disclosed rather than glossed |
| Newly discovered defects forcing scope change? | **Two found (§17). Neither forces it.** Both are reported for owner decision |

---

# 17. Newly discovered repository facts

*Both found during this audit. Both proved by executing the code, not by reading
it. Neither appears in any accepted Phase 6 document.*

## 17.1 A candidate link silently blocks the independent inspector link

`connect_identity_of_professional()` does **not** filter by axis. A
candidate-axis row (`inspector_id = 0`) is returned as "the professional's active
link", so `connect_identity_link_create()` refuses the **unrelated** inspector
link — with a message that is factually untrue.

**Reproduction — one person, one professional, one candidate, one inspector:**

```
A. candidate->pro : OK — Confirmed … recorded as one person
B. pro->inspector : REFUSED — "That professional is already linked to
                               another inspector — unlink it first."
C. resolver row   : id=1  inspector_id=0  candidate_id=1
D. roles card     : is_inspector=false  inspector_id=0  linked=false
```

**There is no other inspector.** The professional is linked to a *candidate*.

**Why it matters to batch 1:** §6 cannot define the unique key without deciding
whether the two axes are independent. **They are** — `P6-CANONICAL-DOMAIN-MODEL.md`
(locked) declares them distinct relationship axes sharing one ledger. So U1–U3
are correct as specified, and this defect is **a guard bug, not a key question**.

**Severity: material.** It makes the marketplace↔Operations link unreachable for
exactly the people most likely to need it — someone recruited *and* deployed. The
fix is one axis predicate in each resolver.

**Recommendation: fix it inside batch 1** (steps 2–3 touch these exact lines, and
the U1–U3 back-fill must classify each row's axis anyway). **OWNER APPROVAL
REQUIRED** — it is behaviour change beyond the literal R-list.

**If not approved:** U1–U3 remain correct, and the defect is recorded as a new
requirement for a later batch. Batch 1 is **not blocked** either way.

## 17.2 The identity audit trail is not attributable

`act_log()` normalises an unregistered entity kind to `''` (`activity.php:441`)
and an unregistered kind to `'NOTE'` (`:442`). `connect_identity.php` passes
`'cx_identity_link'` — a **table name**, where `ACT_ENTITIES` holds entity kinds
(`PARTNER`, `CANDIDATE`, `INSPECTOR`, …). It passes `'IDENTITY_LINKED'`, which is
not in `ACT_KINDS`.

**Reproduction — one link, then the stored row:**

```
link ok=true id=1
activities row: kind=NOTE  entity_kind=[]  entity_id=1
                subject="Linked Audit Probe (pro #1 …"
rows matching kind LIKE 'IDENTITY%': 0
```

The entry is stored as an **untyped note with a dangling id**. It cannot be
filtered, linked, or found on any record's timeline.

**Why it matters:** `P6-BUSINESS-INVARIANTS.md` records **I6** as *"VIOLATED —
only `cx_identity_link` audits."* That understates it. The one mechanism believed
to audit properly writes an entry nobody can retrieve. R24's refusal audit has
nowhere to go until this is fixed — which is why registration (#12) is inside
this batch.

**Severity: material for audit; nil for business behaviour.** Nothing is lost —
the text is stored. It is unfindable.

**`P6-BUSINESS-INVARIANTS.md` §I6 needs correction.** Per the standing rule, **it
has not been modified** — reported here for approval.

---

# 18. Required output (§18.1–19)

| # | Item | Answer |
|---|---|---|
| 1 | File created | `docs/phase6/P6-FOUNDATIONAL-IMPLEMENTATION-PLAN.md` |
| 2 | Commit | *see the report accompanying this document* |
| 3 | Requirements covered | **R22 · R24 · R25 · R15 (partial, §5.1) · R16 (test only) · R3 · R11.** Touched only as a prerequisite: **R18** registration (§16 Exception 1) |
| 4 | Invariants covered | **I23 · I25 · I26 · I27 · I28 · I29** → target HOLDS. **I16** → PARTIAL. **I15** → HOLDS only if §11 E passes. **I22 · I41 · I42** → PARTIAL (R20 excluded) |
| 5 | Files / functions / routes | 17 items, §14 |
| 6 | Engines reused | `connect_identity_admin_can()` · `connect_enabled()` · `licence_module_live()` · `scope_allows()` · `scope_office_allows()` · `act_log()` · `ensure_column()` · `db_epoch()` · `ops_require()` · `org_import_link_team()` · `system_status()`. Protocol mapped from `books_unique_number_index()` |
| 7 | New components | **One** — a two-line private key-value helper in `connect_identity.php`. No engine, table, permission, route or screen |
| 8 | Database changes | 3 NULL-able `INT` columns + 3 unique indexes on `cx_identity_link`. No table, no type change, no deletion |
| 9 | Migration | Forward-only, additive, idempotent, non-destructive; duplicate pre-check; index skipped and surfaced rather than boot failing; identical on SQLite and MariaDB (§7) |
| 10 | Security model | Authorisation from the business relationship, never from a posted id; evaluated **before** information-revealing refusal; enforced at the **function**, not the route (§3) |
| 11 | Scope / tenant | Per-end visibility enforced via `scope_allows()`; relationship-level scope class **deferred to Q5/Q11**; tenancy structural now, **tested** only if §11 E passes (§5) |
| 12 | Concurrency | Database constraint is the protection; PHP check is the courtesy; integrity violation translated to a safe business result; never swallowed (§6.5) |
| 13 | Partial failure | The two-write operation becomes one transaction; audit failure never fails the business write; **R20 excluded, so I22/I42 reach PARTIAL only** (§8) |
| 14 | Test plan | A–S, §11; business state not return codes; both engines; MariaDB authoritative |
| 15 | Mutation plan | M1–M20, §12, each naming one catching assertion; FATAL ≠ catch |
| 16 | Regression plan | Operations · Workforce · Recruitment · Marketplace · Reporting · Money · Dashboard + **the locked Phase 5 battery, numerically identical** (§10) |
| 17 | Q1–Q18 dependency | **None blocking.** Full R15 needs Q5/Q11 → **scoped down, not decided** (§5.1-B) |
| 18 | New repository facts | **Two, both behaviourally proved** (§17): the cross-axis guard defect; the unattributable identity audit trail |
| 19 | **Owner approval required** | **(a)** §4.2 — recruitment-only customers lose candidate↔professional linking. **(b)** §17.1 — fix the cross-axis defect inside batch 1? **(c)** §17.2 — correct I6 in `P6-BUSINESS-INVARIANTS.md`. **(d)** §5.1-B — accept I16 reaching PARTIAL, not HOLDS. **(e)** §8.1 — accept I22/I42 reaching PARTIAL, R20 deferred |

---

## What this document did NOT do

- **No product code, schema, migration, route or permission was changed.**
- **No requirement was implemented.** R22–R31 all stand.
- **No Q1–Q18 was answered.**
- **No accepted document was modified** — §17.2 identifies a correction to
  `P6-BUSINESS-INVARIANTS.md` and leaves it for the owner.
- **No test or mutation was run** beyond two read-only reproductions against a
  throwaway database (§17), which changed nothing and are reproducible.
