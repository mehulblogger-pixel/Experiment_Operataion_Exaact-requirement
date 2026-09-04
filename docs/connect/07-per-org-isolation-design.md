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
today is **per-feature, application-level** — real and tested for what exists, but
hand-applied in each handler rather than enforced by one guard.

### 3a. How each actor is scoped today — in the real code

The session identity is resolved once, from the session cookie, never from request
input:

| Actor | Resolver (file:line) | Owner value |
|---|---|---|
| Client | `portal_user()` → `portal_partner_id()` (`portal.php:138,163`) | `partner_id` (from `$_SESSION['cuid']`) |
| Vendor / agency | `cvp_vendor_user()` → `cvp_vendor_id()` (`cvp.php:126,149`) | `vendor_id` (from `$_SESSION['vuid']`) |
| Professional | `connect_pro_user()` → `connect_pro_id()` (`connect_pro.php`) | `cx_professionals.id` (from `$_SESSION['cxpid']`) |
| Staff | `current_user()` + `can()` / `is_*_level()` | host operator |

The ownership predicates that exist today (the ones #9 consolidates and makes
mandatory):

- **Requirement, client-owned** — the client portal never trusts an id alone; it
  re-checks the poster on every private access (`portal.php:907`):
  ```php
  if (!$req || (int)$req['poster_party_id'] !== (int)portal_partner_id()) {
      http_response_code(404); portal_view('notfound'); exit;
  }
  ```
  and a post stamps the owner from the session, not the request (`portal.php:891`):
  `$in['poster_party_id'] = portal_partner_id();`
- **Application, professional-owned** — `connect_pro_applications()` reads only the
  caller's own rows (`connect_pro.php:207`):
  `... WHERE a.applicant_professional_id=? ...`  (bound to `$me['id']`).
- **Message thread, engagement-owned** — `connect_msg_pro_owns()`
  (`connect_msg.php`) gates every professional read/post:
  ```php
  function connect_msg_pro_owns($applicationId, $proId) {
      $app = connect_msg_app($applicationId);
      return $app && (int)($app['applicant_professional_id'] ?? 0) === (int)$proId;
  }
  ```
- **Bench, agency-owned** — `connect_bench_get($id, $orgId)` scopes by owner
  (`connect_bench.php`): `... WHERE id=? AND org_id=?`, and `connect_bench_allocate()`
  refuses a member that is not this org's. Asserted end-to-end by
  `test_connect_bench.php` ("a different agency sees an empty bench").
- **Verification, subject-owned** — `connect_verify_subject_checks($kind, $id)`
  (`connect_verify.php:292`): `... WHERE subject_kind=? AND subject_id=? ...`.

### 3b. The structural weakness (why a guard is needed)

The scoping above is correct, but the **owner value is passed in by each caller**,
not enforced by a single layer. The clearest illustration is in our own code:
`ops_connect_bench()` resolves the org from the request —

```php
// connect_bench.php:217,219 — SAFE here, because the whole handler is
// staff-gated by connect_bench_can() (a coordinator may view ANY agency):
ops_require(connect_bench_can(), '…');
$orgId = (int)($_GET['org'] ?? $_POST['org_id'] ?? 0);
```

This is safe *because staff are allowed to see any agency*. But the exact same
pattern copied into a **portal** handler — deriving the owner from `$_GET`/`$_POST`
instead of the session — would be an instant cross-org leak. Nothing structural
stops that mistake today: **I5 (deny-by-default) is not guaranteed** — a new,
un-scoped `SELECT * FROM cx_bench` in some future handler would return every
agency's roster. #9 exists to make that class of mistake impossible, not merely
absent.

### 3c. Data classification (every `cx_*` table)

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

1. **A resolved caller context** per request — one function, built from the
   session resolvers that already exist (`portal_partner_id()`, `cvp_vendor_id()`,
   `connect_pro_id()`, `current_user()`): `{actor_kind, actor_id, org_id?,
   party_id?, is_staff}`. **Never** taken from `$_GET`/`$_POST` (the anti-pattern
   flagged in §3b). This replaces the per-handler `$_SESSION['…']` reads.
2. **A private-table access contract.** Reads/writes to a table classed PRIVATE in
   §3c go through a small set of helpers that take the caller context and apply the
   owner filter for that table. A private-table access with no owner scope **throws**
   — it never silently returns all rows (I5). The taxonomy/shared masters and the
   open-requirement pool are exempt (they are read-shared by design).
3. **Ownership predicates**, one per private table, promoted from today's ad-hoc
   checks into one place and made mandatory. They already exist and are correct —
   #9 unifies them:
   - `cx_bench` / `cx_bench_alloc` → `row.org_id === ctx.org_id || ctx.is_staff`
     (today: `connect_bench_get($id,$orgId)`'s `WHERE id=? AND org_id=?`).
   - `cx_requirements` (private facets) → `row.poster_party_id === ctx.party_id ||
     ctx.is_staff` (today: `portal.php:907`).
   - `cx_applications` / `cx_messages` → engagement ownership (today:
     `connect_msg_pro_owns()`, `connect_pro_applications()`'s
     `WHERE applicant_professional_id=?`).
   - `cx_verifications` → `subject_kind`+`subject_id` (today:
     `connect_verify_subject_checks()`).
4. **Staff override is explicit** (`ctx.is_staff`), logged, and confined to the host
   operator role via the existing `can()` / `is_*_level()` gates — never inherited by
   a portal actor. The current staff-only handlers that read across all rows
   (e.g. `connect_msg_all_thread_apps()`, `ops_connect_bench()`'s request-supplied
   `org`) stay valid *only* under `ctx.is_staff` and must be re-expressed through the
   guard so that assumption is checked, not assumed.
5. **The shared layer** keeps its own rule: read-open, write-owned — a professional
   edits only their own `cx_professionals` row; a poster edits only their own
   `cx_requirements` row. The shared *read* path (the recommender
   `connect_match_for_requirement()`, talent search) is unchanged.

Deliverable of implementation (later): one reviewed module (`connect_scope.php`) the
handlers must call, the predicates above moved into it, every current caller
migrated to it, and a `test_connect_isolation.php` that *attempts* every cross-org
read/write (client↔client, agency↔agency, pro↔pro) and asserts denial — the same
shape as the passing cross-agency assertions already in `test_connect_bench.php`.

---

## 6. Migration & rollout (when approved)

1. **Freeze the classification** (§3c) with the owner.
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

- [ ] **Cross-tenant read** — as client A, attempt to read client B's requirement
      (`/portal/hire-req?id=B`), application, messages, verification: **denied** for
      each private table. (Extends the existing `portal.php:907` poster check to a
      guard-enforced predicate.)
- [ ] **Cross-tenant write** — as agency A, attempt `connect_bench_allocate()` /
      `connect_bench_update()` on agency B's member, or change B's requirement
      status: **denied**. (Today `connect_bench_get($id,$orgId)` already blocks this;
      the test must prove the guard blocks it even if a handler forgets to pass
      `$orgId`.)
- [ ] **IDOR / enumeration** — sequential-id probing of every private route
      (`connect-requirement?id=`, `pro/messages?a=`, `connect-verify`) returns
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
  frozen §3c contract + the cross-org test suite; a missing classification is a
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

Approve **§3c (data classification)** and the **staged topology (Option A now,
Option B later)**, commission the **security review of the scoping-layer design**,
then implement in audit-mode → enforce. Until that sign-off, **#9 stays design-only**
and the marketplace continues on the current single shared store with per-feature
scoping (which is correct and tested for what exists today, just not yet structurally
guaranteed for what comes next).
