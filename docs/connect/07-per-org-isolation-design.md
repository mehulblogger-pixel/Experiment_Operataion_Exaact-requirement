# Connect — Per-Organisation Data Isolation (Backlog #9): Design for Review

**Status:** DESIGN ONLY — for review. **No code is to be written against this until
it is signed off** (topology decision + a security review), per the audit's rule
that "these are financial-truth / structural changes where haste is the enemy."

**Author:** ITSN build. **Reviewers needed:** the owner (topology + policy calls),
plus a security review of the scoping layer before any implementation.

**Related:** `docs/connect/04-phase-b-design.md` (Phase B topology sketch — this doc
is the detailed B2 design), `docs/connect/06-system-audit.md` (backlog #9),
`docs/02-permission-matrix.md`, `lib/tenants.php` (existing tenancy).

---

## 1. Purpose & scope

**In scope (#9):** guaranteeing that one organisation on the platform can never
read or write another organisation's **private** data, while the **shared
marketplace layer** (the self-listed pool + open requirements) stays discoverable
by everyone. This is a data-isolation and access-control design.

**Explicitly out of scope:** escrow / money movement (that is **#10**, gated on
CA / RBI / legal and designed separately). Nothing here holds or moves money.

**Why now / why a doc first:** every slice so far (#1–#8) has been an *additive*
feature on a single shared store. Isolation is not a feature — it is a change to
the **trust boundary** of the whole marketplace. A mistake here is a cross-tenant
data leak, the worst failure a talent network can have. So the deliverable of this
step is a reviewed design, not code.

---

## 2. Threat model — what isolation must guarantee

**Actors** (each has its own session identity today):

| Actor | Identity today | Trust |
|---|---|---|
| Internal staff | `ORG_ROLES` + `can()` (session user) | Operator of the host |
| Client (company) | `portal.php` — `cuid`, `client_users`, `poster_party_id` | Semi-trusted tenant |
| Vendor / agency | `cvp.php` — `vuid`, `vendor_users`, vendor party | Semi-trusted tenant |
| Professional (freelancer) | `connect_pro.php` — `cxpid`, `cx_professionals` | Semi-trusted individual |
| Public | pre-login routes (`/p/<token>`, `/join`) | Untrusted |

**Guarantees required (the isolation invariants):**

1. **I1 — Private read isolation.** An org (client/vendor/agency) can read only:
   its own private records, plus the shared marketplace layer. It can never read
   another org's private records (bench, its own applicants' contact details
   pre-award, its internal notes, its verification evidence).
2. **I2 — Private write isolation.** An org can create/modify only rows it owns.
   No cross-org writes, status changes, or deletes.
3. **I3 — Shared layer is read-shared, write-owned.** The shared pool
   (`cx_professionals`, open `cx_requirements`) is discoverable by all, but a row
   is mutable only by its owner (the professional owns their profile; the poster
   owns their requirement).
4. **I4 — No enumeration / IDOR.** Guessing a numeric id must not reveal a private
   row. Every private fetch is scoped by owner, not just by id.
5. **I5 — Deny by default.** A new query with no explicit scope must fail closed
   (return nothing / error), never leak the whole table.
6. **I6 — Boundary holds across every exit.** The guarantee must survive exports
   (CSV/PDF), logs, notifications/channels, backups, and the AI seam (no private
   data crosses to a provider without consent).

**The privacy invariant already established** (agency employees are private) is a
special case of I1/I2 and is already enforced for `cx_bench` by `org_id` scoping
(`test_connect_bench.php`). #9 generalises that guarantee to *every* private table
and *every* actor, and makes it structural rather than per-feature.

---

## 3. Current state — what is and isn't isolated

**Already isolated (physical, today):** `lib/tenants.php` maps a subdomain to its
**own database**. Two tenant workspaces share nothing but the code — the strongest
possible isolation. This is how the *operations* app (P1–P6) is multi-tenanted.

**Not yet isolated (the gap #9 closes):** the Connect marketplace `cx_*` tables
live in **one shared store** so the pool can be shared. Within that store, isolation
today is **per-feature, application-level**:

- `cx_bench` / `cx_bench_alloc` — scoped by `org_id` (good, tested).
- `cx_requirements` — carries `poster_party_id`; the client portal filters on it
  (`portal.php` — "a client only ever sees its own postings").
- `cx_applications` — a professional sees only their own (`connect_msg_pro_owns`,
  `connect_pro_applications`); a vendor applies as its party.
- `cx_messages` — scoped by engagement ownership (`connect_msg_pro_owns`,
  staff-gated).
- `cx_verifications` — subject-scoped; only the moderation desk reviews.

The weakness is that this scoping is **hand-applied in each handler**. There is no
single, audited guard that *forces* every query to be scoped, so a future careless
query is a latent cross-org leak (I5 is not structurally guaranteed).

### 3a. Data classification (every `cx_*` table)

| Table | Class | Owner key | Notes |
|---|---|---|---|
| `cx_professionals` | **SHARED** (self-listed pool) | the professional (`cxpid`) | Public-safe fields discoverable; profile mutable only by owner |
| `cx_requirements` | **SHARED when OPEN**, else **PRIVATE** to poster | `poster_party_id` / staff | Open reqs discoverable; drafts/awarded details private |
| `cx_applications` | **PRIVATE** (poster + applicant) | requirement owner + applicant | Two-sided ownership |
| `cx_messages`, `cx_message_reads` | **PRIVATE** (thread parties) | engagement (application) | Contact-redaction pre-award already enforced |
| `cx_bench`, `cx_bench_alloc` | **PRIVATE** to agency | `org_id` | The privacy invariant; already scoped |
| `cx_verifications` | **PRIVATE** (subject + desk) | `subject_kind`+`subject_id` | Holds ID evidence — highest sensitivity |
| `cx_ratings`, `cx_disputes` | **PRIVATE** (engagement parties + desk) | requirement / party | |
| `cx_terms`, `cx_readiness`, `cx_positions` | **PRIVATE** to requirement | requirement owner | |
| `cx_channel_messages` | **PRIVATE** (recipient + desk) | `pro_id` | Masked contacts only |
| `cx_organisations` | **HOST-ADMIN** | host | The tenant registry itself |
| `cx_channel_templates` | **HOST config** | host (admin) | Shared config, not tenant data |
| Taxonomy masters (`cx_disciplines`, `cx_sectors`, `cx_equipment_*`, `cx_materials`, `cx_standards`, `cx_inspection_stages`, `cx_certifications_registry`, `cx_job_families`, `cx_roles`, `cx_qualification_levels`, `cx_iti_trades`, `cx_prof_certifications`, `*_versions`) | **SHARED reference** | host | Read-shared; admin-editable (K0/K13) |

This table is the **contract** the scoping layer must enforce. It should be
reviewed and frozen before implementation.

---

## 4. Target architecture — the topology decision

Two viable models (from Phase B §4). **This decision is the main thing to sign off.**

### Option A — Single shared store + a mandatory org-scoping layer
Keep one store. Add an **audited scoping guard**: every access to a private table
goes through helpers that *require* an owner scope and fail closed without one.
Private tables gain/keep an explicit owner column; a query builder refuses to run a
private-table read/write that isn't owner-scoped.

- **Pros:** no new infrastructure; the pool stays naturally shared; smallest step
  from today; testable with cross-org attempt tests.
- **Cons:** isolation is *logical*, not *physical* — a bug in the guard is a leak.
  Requires disciplined, reviewed enforcement and ongoing audit.

### Option B — Per-org tenant workspaces + a shared marketplace store *(Phase B recommendation)*
Each org is a `tenants.php` workspace with its **own DB** (physical isolation, as
operations already works). The Connect **marketplace + shared pool** move to a
single **shared store** every workspace reaches only for marketplace actions.

- **Pros:** physical isolation of private data (strongest I1/I2); reuses the proven
  tenant + product-package machinery; matches the private/shared rule cleanly.
- **Cons:** real infra — a shared marketplace store distinct from each workspace DB,
  and a thin, audited access path from a workspace to it; the `cx_*` tables split
  into "moves to shared store" vs "stays per-workspace." Provisioning/billing per
  tenant.

### Recommendation
**Stage it: Option A now (logical guard), Option B when scale/customers demand
physical isolation** — the incremental path in Phase B §5. Option A closes I4/I5
structurally on the current single deployment and is fully testable; Option B is the
end state for heavyweight tenants (a large TPIA) and is a provisioning project, not a
code slice. The scoping layer built for Option A is exactly what a workspace uses to
reach the shared store under Option B, so the work is not thrown away.

---

## 5. The scoping layer (design for Option A; reused by B)

**Principle: deny-by-default, owner-scoped, in one place.**

1. **A resolved caller context** per request: `{actor_kind, actor_id, org_id?,
   party_id?, is_staff}` derived once from the session (staff / `cuid` / `vuid` /
   `cxpid`). Never taken from request input.
2. **A private-table access contract.** Reads/writes to a PRIVATE table go through a
   small set of helpers that take the caller context and apply the owner filter for
   that table (from the §3a classification). A private-table access with no owner
   scope throws — it never silently returns all rows (I5).
3. **Ownership predicates**, one per private table, expressing "may this caller
   touch this row?" (e.g. a `cx_bench` row: `row.org_id == ctx.org_id || is_staff`).
   These already exist ad hoc (`connect_msg_pro_owns`, the bench `org_id` checks) —
   #9 consolidates them and makes them mandatory.
4. **Staff override is explicit**, logged, and confined to the host operator role —
   never inherited by a portal actor.
5. **The shared layer** keeps its own rule: read-open, write-owned (a professional
   edits only their profile; a poster edits only their requirement).

Deliverable of implementation (later): one reviewed module the handlers must use,
plus tests that *attempt* every cross-org read/write and assert denial.

---

## 6. Migration & rollout (when approved)

1. **Freeze the classification** (§3a) with the owner.
2. **Backfill owner columns** where missing; verify every existing private row has a
   valid owner (no orphans that would fail closed).
3. **Introduce the guard behind a flag**, in *audit mode* first: it logs any query
   that would be denied without blocking — surfacing every un-scoped access in the
   current code before enforcement.
4. **Fix all flagged accesses**, then flip the guard to **enforce**.
5. **Cross-org attempt test suite** green (see §7) before enforce ships.
6. **(Option B, later)** stand up the shared marketplace store; move the SHARED/
   marketplace tables; point workspaces at it through the guard; migrate tenants.

No destructive step; additive columns + a guard; the flag makes it reversible.

---

## 7. Security review checklist (the sign-off gate)

Implementation may begin only after this is agreed, and must ship only after every
item passes:

- [ ] **Cross-tenant read** — as client A, attempt to read client B's requirement,
      application, messages, verification: **denied** for each private table.
- [ ] **Cross-tenant write** — as agency A, attempt to allocate/modify agency B's
      bench member, or change B's requirement status: **denied**.
- [ ] **IDOR / enumeration** — sequential-id probing of every private route returns
      nothing for non-owners (I4).
- [ ] **Deny-by-default** — a deliberately un-scoped private query fails closed (I5).
- [ ] **Portal boundaries** — `cuid` / `vuid` / `cxpid` sessions cannot reach staff
      routes or each other's data; no session-fixation across portals.
- [ ] **Injection** — the scoping filter is parameterised; no string-built owner
      clause (re-run the existing SQLi review over new code).
- [ ] **Exit points (I6)** — exports, notifications/channels, AI seam, and logs
      carry no other org's private data; masked contacts stay masked pre-award.
- [ ] **Backups / restore** — a per-tenant restore (Option B) cannot resurrect
      another tenant's data into a workspace.
- [ ] **Staff override** — logged, role-gated, not reachable by any portal actor.
- [ ] **Regression** — full suite green; no existing marketplace flow broken.

---

## 8. Failure modes & rollback

- **Guard too strict** (false denials break a legit flow): caught in audit mode
  before enforce; the flag rolls enforcement back instantly without data change.
- **Guard too loose** (a private table not classified PRIVATE): prevented by the
  frozen §3a contract + the cross-org test suite; a missing classification is a
  test failure, not a silent leak.
- **Option B access-path outage** (workspace can't reach the shared store): the
  marketplace degrades to read-only for that tenant; private ops are unaffected.

---

## 9. Open questions for sign-off

1. **Topology:** approve the staged A-now / B-later plan, or go straight to B?
2. **Requirement visibility:** confirm "OPEN requirements are discoverable by all
   professionals/agencies; drafts and awarded details are private to the poster."
3. **Applicant visibility to the poster:** how much of an applicant's profile does a
   posting org see before shortlisting vs after award? (Ties to the #4 contact
   redaction already in place.)
4. **Staff/host reach:** confirm the host operator may read across tenants for
   support/moderation, logged — or must that be constrained further?
5. **Verification evidence residency:** `cx_verifications` holds the most sensitive
   data (ID references). Does it stay in the shared store (Option A) or must it be
   per-tenant/host-only even under Option A?
6. **Deletion / data-subject requests (DPDP):** an org or professional off-boarding
   — what is deleted vs retained (dispute/evidence trail) and where?

---

## 10. Recommendation

Approve **§3a (data classification)** and the **staged topology (Option A now,
Option B later)**, commission the **security review of the scoping-layer design**,
then implement in audit-mode → enforce. Until that sign-off, **#9 stays design-only**
and the marketplace continues on the current single shared store with per-feature
scoping (which is correct and tested for what exists today, just not yet structurally
guaranteed for what comes next).
