# Phase 6 · Batch 2 — Pre-implementation audit
## Person relationship integrity & conversion safety

*What the shipped code actually does today with Candidate, Inspector,
Professional and User relationships. Read-only. Nothing here is fixed.*

**Baseline:** Phase 6 Batch 1 — ACCEPTED / LOCKED (`db02289`, `03d805b`).
**Method:** repository sweep plus **behavioural probes run against a throwaway
database**. No product code, schema, route or permission was touched, and no
defect found here was fixed.

**Authoritative inputs:** `P6-CANONICAL-DOMAIN-MODEL.md` ·
`P6-DUPLICATE-AND-IDENTITY-RULES.md` · `P6-ACTION-PATH-MATRIX.md` ·
`P6-BUSINESS-INVARIANTS.md` · `P6-FOUNDATIONAL-IMPLEMENTATION-PLAN.md` ·
the shipped Batch 1 implementation and its five completion documents.

---

## 0. In plain words

Three things are true of the system today, and each was proved by running it,
not by reading it:

1. **Hiring one person three times at once creates three staff records.** Two of
   them belong to nobody and nothing reports them.
2. **Saying "these two applications are the same person" can quietly split an
   existing group** — somebody previously declared the same person becomes a
   different person, silently.
3. **The hiring conversion writes nothing to the identity ledger Batch 1 built.**
   A person hired through recruitment is, to the identity system, not linked to
   anything.

None of this is new behaviour introduced by Batch 1. Batch 1 made the *ledger*
safe; these are the paths that never used the ledger.

---

## 1. Finding A — **MATERIAL** · the conversion is not atomic and has no ceiling

### Evidence

Three OS processes, synchronised on one wall-clock microsecond, each dispatching
the real `/candidate-stage` route with `make_inspector=1` for **one** candidate:

```
inspectors named 'Race Hire'  : 3
candidate.inspector_id        : 3
  inspector #1  emp_code=''  office=NULL  sbu='IND'  email='race.hire@x.test'
  inspector #2  emp_code=''  office=NULL  sbu='IND'  email='race.hire@x.test'
  inspector #3  emp_code=''  office=NULL  sbu='IND'  email='race.hire@x.test'
cx_identity_link rows         : 0
audit rows naming the change  : 0
```

**Three staff records for one hire. The candidate points at the third. The first
two are live, indistinguishable from real people, and nothing reports them.**

### The code

`lib/ops.php:5392–5412`, inside the `candidate-stage` route:

```php
if ($to === 'ACCEPTED' && !empty($_POST['make_inspector']) && empty($cand['inspector_id'])) {
    …
    $pdo->prepare("INSERT INTO inspectors (…)")->execute([…]);   // WRITE A
    $insId = $pdo->lastInsertId();
    $pdo->prepare("UPDATE candidates SET inspector_id=? WHERE id=?")->execute([$insId, $id]);   // WRITE B
    …
    $pdo->prepare("UPDATE requisitions SET hired_inspector_id=? WHERE id=?")->execute([…]);     // WRITE C
```

| Question | Answer |
|---|---|
| Which function creates the Inspector? | **None** — a raw `INSERT` inline in the route |
| Which function links the Candidate? | **None** — a raw `UPDATE` inline in the route |
| Multiple writers? | **One** conversion trigger (`make_inspector`), proved by sweep |
| Transactional? | **No.** No `beginTransaction` anywhere on this path |
| A succeeds, B fails? | **A live orphan inspector.** The candidate stays unlinked and the next attempt creates another |
| B succeeds, later work fails? | `requisitions.hired_inspector_id` and `reqf_sync()` are outside any boundary |
| Retried sequentially? | Safe — `empty($cand['inspector_id'])` is re-read |
| Two processes at once? | **Two (or three) inspectors.** Proved above |
| Candidate already converted? | Skipped — correct, but by a read-then-write check with no constraint behind it |
| Another Inspector already represents this person? | **Not checked at all** |
| Candidate already has an identity relationship? | **Not checked, and none is created** |
| Session becomes invalid mid-flight? | The route gate ran at entry; the writes do not re-ask |
| Candidate/requisition state changes mid-flight? | M6 gates the seat before the write; the conversion itself does not re-read |

### Three further defects in the same block

| | |
|---|---|
| **No `emp_code`** | The column list omits it. `team_member_create()` generates one via `next_emp_code()`; the people form takes one; **the conversion leaves it blank**. This matters directly to R20 (§4) |
| **No `home_office_id`** | Omitted, so every converted inspector has a NULL office, which `scope_allows()` reads as **Ahmedabad**. A person hired for the Mumbai branch becomes an Ahmedabad-scoped inspector |
| **No `cx_identity_link` row** | The conversion pre-dates the ledger and was never connected to it. The candidate↔inspector edge that `P6-PREIMPLEMENTATION-AUDIT.md` recorded as missing (**R1/R5**) is missing *here* |

### Classification

**MUST FIX IN BATCH 2.** This is invariant **I30** ("a person representation
cannot be duplicated by an ordinary business action") and **I22/I42**, which
Batch 1 left PARTIAL precisely because this path was out of scope.

---

## 2. Finding B — **MATERIAL** · linking two applications can split a person group

### Evidence

Four applications. A+B declared one person. C+D declared another person. Then
B and C declared the same person:

```
before : #1=P000001  #2=P000001  #3=P000003  #4=P000003
link B+C returned: ''            (success, no message)
after  : #1=P000001  #2=P000001  #3=P000001  #4=P000003
  group P000001: 3 application(s)
  group P000003: 1 application(s)
audit rows for person linking: 0
```

**D was previously asserted to be the same person as C. After linking C to B, D
is a different person. Nobody was told, and there is no audit entry anywhere.**

### The code

`lib/recruit.php:1085–1099`:

```php
foreach ($rows as $r) if (trim((string)$r['person_ref']) !== '') { $ref = trim((string)$r['person_ref']); break; }
if ($ref === '') $ref = 'P' . str_pad((string)min($ids), 6, '0', STR_PAD_LEFT);
db()->prepare("UPDATE candidates SET person_ref=? WHERE id IN ($ph)")->execute(array_merge([$ref], $ids));
```

The `UPDATE` touches **only the ids passed in**. Every other member of an
absorbed group keeps its old reference. The operation is not the transitive
closure it appears to be.

| Property | Today |
|---|---|
| Authorisation | route-level `is_coordinator_level()` only; the function asks nothing |
| Scope | Batch 1 added an anchor check on the route; the function asks nothing |
| Audit | **none** |
| Reversal | **none** — there is no unlink route for `person_ref` at all |
| Duplicate protection | n/a |
| Transitive closure | **not performed** — hence the split |
| Historical meaning | a grouping label, overwritten in place |

### Classification

**MUST FIX IN BATCH 2** — the split. The *reversal* and *audit* gaps are **R18 /
R21** and may be deferred; the silent split is a data-integrity defect that gets
worse the longer it runs, because every future grouping inherits the wrong state.

> **Not to be fixed by inventing a person key.** The correct repair is to make
> the operation close over the whole group. Deciding that `person_ref` should
> become a canonical person identifier is **Q3/Q7/Q13** and stays open.

---

## 3. Finding C — **MATERIAL** · the conversion never reaches the identity ledger

Batch 1 built `cx_identity_link` with axis-aware resolvers, database uniqueness,
entitlement, scope and an attributable audit. **The hiring conversion writes none
of it.** A person hired through recruitment carries `candidates.inspector_id` and
nothing else.

Consequences, all read from the resolver's own behaviour:

- `connect_person_resolve('candidate', X)` cannot reach the inspector, because it
  climbs through `cx_identity_link` only.
- The marketplace identity console cannot see the relationship.
- Batch 1's uniqueness protection does not apply to it.
- Nothing can unlink it: there is no reversal path for `candidates.inspector_id`.

### Classification

**MUST FIX IN BATCH 2**, and it is the single change that makes the other two
worth making: once the conversion writes the ledger, U1/U2 apply to it and the
race in Finding A is stopped by the database rather than by hope.

> **This does not merge anything.** `candidates.inspector_id` stays exactly where
> it is and keeps every reader working. The ledger row is **additive** — the same
> "relationship, never a merge" rule the ledger was built on.

---

## 4. R20 — inspector duplicate protection: assessed

The owner deferred R20 from Batch 1 and asked whether it belongs in Batch 2.

### Candidate business keys, assessed against the shipped code

| Key | Set by | Viable? |
|---|---|---|
| **`emp_code`** | `team_member_create()` generates it · the people form takes it · **the conversion leaves it blank** · `trace_audit_seed()` sets its own | **No, not today.** A key that one live creation path never sets cannot be unique. And `next_emp_code()` is a read-max-then-add-one generator with no constraint — itself raceable |
| **`email`** | optional everywhere; blank on many rows | **No.** Shared family addresses and agency addresses are ordinary in this business, and `P6-DUPLICATE-AND-IDENTITY-RULES.md` already rules e-mail a *suggestion*, never a resolution |
| **`mobile`** | optional | **No**, same reason, and the vocabulary rules already say so |
| **login (`users.inspector_id`)** | `inspector_login_conflict()` — enforced at **one** call site (`ops.php:8430`), and **not** by `link_inspector_users()` or `org_import_link_team()`; no unique index | **Partially.** This is a real rule ("one team member ↔ at most one active login") that is simply not enforced at the database level. It is *not* a person key |
| **external identity (PAN/Aadhaar)** | not present on `inspectors` | **No** — the column does not exist |
| **name** | free text | **No** — the duplicate rules already reject name as a resolver |

### Legitimate multiplicity

The audit found at least three cases where more than one `inspectors` row for one
human is **correct business data**, not a duplicate:

1. A person who left and was re-hired on different terms (`roll_type` OWN vs
   AGENCY, different `agency_id`, different `placement_fee` and guarantee window).
2. A person supplied by two different agencies at different times — the rows carry
   different commercial facts that Money and Profitability read historically.
3. A demo/diagnostic namespace (`trace_audit_seed()`, DEMO-S0x) deliberately
   creating parallel records.

### Recommendation

> **R20 stays OPEN as a general rule. Do NOT adopt a business identity key for
> `inspectors` in Batch 2.**
>
> No field on `inspectors` today is both reliably populated and reliably unique
> per human, and the fields that look convenient (e-mail, mobile, name) are ones
> the locked duplicate rules already forbid as resolvers. Choosing one because it
> is technically available is exactly what §7 of the instruction forbids.

**What Batch 2 *can* do instead, and should:** stop the *specific* duplication
this batch is about — **one candidate must not produce two inspectors** — by
making the conversion transactional and writing the `cx_identity_link` row inside
that transaction, where Batch 1's **U2** (one live inspector-axis row per
inspector) and a new candidate-axis rule already constrain it. That closes
Finding A without deciding what makes two inspectors the same human.

**OWNER DECISION REQUIRED** if you want general inspector uniqueness: it needs a
business identity field that does not exist yet (an employee identifier that
every creation path must populate), which is a data-and-process change, not a
schema change.

---

## 5. The five identity mechanisms — current state

| | Mechanism | Purpose | Writer(s) | Authorisation | Scope | Audit | Reversal | Duplicate protection |
|---|---|---|---|---|---|---|---|---|
| 1 | **`cx_identity_link`** | professional↔inspector and candidate↔professional, as a reversible relationship | `connect_identity_*` only (`W1` asserts it) | **entitlement + permission, at the function** | **per-end visibility** | **attributable** | **yes** | **U1/U2/U3 at database level** |
| 2 | **`candidates.inspector_id`** | "this application became this staff member" | `ops.php:5412` (conversion) only | route gate only | none | **none** | **none** | **none** |
| 3 | **`users.inspector_id`** | "this login is this team member" | `ops.php:1033`, `ops.php:8557`, `orgadmin.php:1033` | People right (at `8557` and `1033`) | none | **none** | via the user form | `inspector_login_conflict()` at **one** of three writers; no index |
| 4 | **`candidates.person_ref`** | "these applications are one person" | `person_link_rows()` only | route gate only | anchor check (Batch 1) | **none** | **none** | n/a — but **splits groups** (Finding B) |
| 5 | **per-application bridge** (`cx_applications.inspector_id` / `applicant_professional_id`) | the original pre-ledger bridge | marketplace application flow | marketplace gate | marketplace scope | partial | n/a | n/a |

**Only mechanism 1 is safe.** Batch 1 made it so. Mechanisms 2 and 4 are the ones
this batch is about; 3 and 5 are recorded and **deferred**.

> **Do not eliminate mechanisms 2–5.** They carry historical business meaning that
> readers depend on. The objective stated in §8 of the instruction is to **prevent
> contradictory states**, not to reduce the count.

---

## 6. Contradictory state matrix

Assessed against the shipped code. **No data was changed.**

| # | State | Verdict | Detectable today? | Repairable without a business decision? |
|---|---|---|---|---|
| 1 | Candidate with no inspector | **Valid** — not every hire is field staff | n/a | n/a |
| 2 | Candidate → inspector, inspector exists and is active | **Valid** | yes | n/a |
| 3 | Candidate → inspector, inspector row deleted or INACTIVE | **Invalid (dangling)** | **No** — nothing checks | Yes — report it |
| 4 | Candidate → professional (ledger) | **Valid** | yes | n/a |
| 5 | Candidate → inspector **and** → professional | **Valid** — one person, three representations (**I1**) | partly | n/a |
| 6 | Candidate → professional P1; that professional → inspector I1; candidate → inspector **I2** | **Contradictory** — two inspectors for one person | **No** | **No — human review** |
| 7 | Candidate has `person_ref` and an inspector; a sibling application in the same group has a **different** inspector | **Contradictory** | **No** | **No — human review** |
| 8 | Candidate in a `person_ref` group that was split by Finding B | **Invalid, silently created** | **No** | **Yes** — recompute the closure; no business decision needed |
| 9 | Two candidates → the same professional | **Valid** — invariant **I3** | yes | n/a |
| 10 | Two candidates → the same inspector | **Ambiguous** — legitimate for a re-hire, contradictory for a duplicate | **No** | **No — human review** |
| 11 | Inspector → professional (ledger) | **Valid** | yes | n/a |
| 12 | Inspector with two active logins | **Invalid** | `inspector_login_conflict()`, at one call site only | Yes — report it |
| 13 | User → inspector, inspector row missing | **Invalid (dangling)** | **No** | Yes — report it |
| 14 | Live and historical (`UNLINKED`) ledger rows coexisting | **Valid by design** | yes | n/a |
| 15 | Two live ledger rows for one endpoint | **Impossible since Batch 1** | constraint + health report | n/a |
| 16 | Orphan inspector from a raced conversion (Finding A) | **Invalid** | **No** | **No — human review** (which of the three is the person?) |

**Eleven of sixteen states are undetectable today.** Five of those are repairable
mechanically; the rest need a person, because deciding which of two records is
the human is precisely what this programme never does silently.

---

## 7. Action-path sweep — what changed since the Batch 1 matrix

The §26 matrix remains accurate for the identity ledger. This sweep looked
specifically for paths that create or change **Candidate / Inspector /
Professional** relationships.

| Path | Kind | Creates/changes | New since Batch 1's matrix? |
|---|---|---|---|
| `/candidate-stage` + `make_inspector` | UI POST | inspector, `candidates.inspector_id`, `requisitions.hired_inspector_id` | **Yes — the central Batch 2 path, not previously mapped in this detail** |
| `/candidate-link-person` → `person_link_rows()` | UI POST | `candidates.person_ref` | mapped; **the split behaviour is new** |
| `/candidate-link-pro` · `/candidate-unlink-pro` · `/connect-identity` | UI POST | `cx_identity_link` | mapped; **secured in Batch 1** |
| `ops_inspectors()` people form (`ops.php:4114`) | UI POST | inspector directly | **Yes — a direct creation path, no duplicate check** |
| `/users` save (`ops.php:8557`) | UI POST | `users.inspector_id` | the one place `inspector_login_conflict()` is asked |
| `/users` reconcile → `link_inspector_users()` | UI POST | inspector + `users.inspector_id` | **secured and made transactional in Batch 1** |
| `org_import_apply()` → `org_import_link_team()` | Import | inspector + `users.inspector_id` | **no login-conflict check** |
| `trace_audit_seed()` (`ops.php:3096`, master-only) | UI POST | inspector directly | **Yes — a diagnostic seeding path that writes `inspectors` raw** |
| DEMO-S0x seeds + their CLI tools | Seed / CLI | inspectors, ledger | S06 corrected in Batch 1; **S01/S02/S03 still `INSERT INTO inspectors` directly** |
| `cron.php` | Background | **nothing** — no identity write | confirmed absent |
| AJAX / API | — | **nothing** — no endpoint writes identity | **recorded as ABSENT**, not assumed |

**No new hidden write-on-read path was found.** Batch 1's removal holds: every
creation path above is an explicit action.

---

## 8. What Batch 1 already protects, and must not be re-done

| | |
|---|---|
| Reads never create identity | holds — `A1–A5`, mutant `M1` |
| The ledger has one entitlement, asked by the function | holds — `C1–C5` |
| Per-end scope on ledger writes | holds — `D1–D5` |
| A record id is never authorisation | holds — `B1–B5` |
| Ledger uniqueness at database level | holds — `F0–F8` |
| Tenant isolation | holds, **tested** — `E0–E5` |
| Axis separation | holds — `X-A`–`X-G`, mutant `M21` |
| Attributable audit for the ledger | holds — `Y-A`–`Y-F` |

**Batch 2 reuses all of it.** No new scope engine, entitlement engine, audit
engine, permission or identity engine is required by anything in this audit.

---

## 9. Classification of every finding

| # | Finding | Class |
|---|---|---|
| **A** | Conversion is not atomic; three processes → three inspectors; no `emp_code`, no office, no ledger row, no audit | **MUST FIX IN BATCH 2** |
| **B** | `person_link_rows()` splits an existing person group, silently, unaudited | **MUST FIX IN BATCH 2** (the split) |
| **C** | The conversion never writes `cx_identity_link` | **MUST FIX IN BATCH 2** |
| D | Converted inspector has no `home_office_id`, so branch scope reads it as Ahmedabad | **MUST FIX IN BATCH 2** — it is one field in the same statement |
| E | Dangling `candidates.inspector_id` / `users.inspector_id` undetectable (states 3, 13) | **SAFE TO DEFER** — report-only; belongs with the health work |
| F | Contradictory two-inspector states (6, 7, 10, 16) undetectable | **SAFE TO DEFER** for repair; **MUST FIX** for *detection* only |
| G | `inspector_login_conflict()` asked at one of three writers; no unique index | **SAFE TO DEFER** — no live defect found; record it |
| H | `candidates.inspector_id` and `person_ref` have no audit and no reversal | **SAFE TO DEFER** — **R18/R21** |
| I | S01/S02/S03 seeds write `inspectors` directly | **NOT A DEFECT** — namespaced demo data; the ledger boundary (`W1`) is what mattered and is already enforced |
| J | General inspector uniqueness (**R20**) | **BUSINESS DECISION REQUIRED** — no viable key exists today (§4) |
| K | Whether `person_ref` is a canonical person identifier | **BUSINESS DECISION REQUIRED** — **Q3/Q7/Q13**, untouched |
| L | Whether a relationship carries a branch | **BUSINESS DECISION REQUIRED** — **Q5/Q11**, untouched |

---

## 10. What this audit did NOT do

- **No product code, schema, migration, route or permission was changed.**
- **No defect found here was fixed.**
- **No Q1–Q18 was answered.** No Person hub, no universal person key, no
  e-mail/mobile identity rule, no automatic merge, no organisation convergence.
- **No accepted document was modified.**
- **Batch 1 was not touched.**
- The probes in §1 and §2 ran against a **throwaway database** and changed
  nothing; both are reproducible from the code quoted beside them.
