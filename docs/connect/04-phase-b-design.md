# Connect — Phase B Design: One Universal App for Every Organisation

**Status: design proposal for the owner's sign-off. No code yet.** Phase B is the
architectural layer that turns EXAACT + the Connect marketplace into *"one universal
application used by all, without leaving the app for each user we onboard."* It
reshapes how **organisations** are provisioned and what each can see, so it is
decided deliberately — not slipped in.

Prerequisite reading: `03-universal-360-gap-analysis.md` (the gaps), and this repo's
`docs/01-roles.md` / `02-permission-matrix.md`.

---

## 1. What Phase B must deliver

From the owner's own scenarios and rules:

1. **Every organisation type lives in the same app**, each with the right slice:
   - a **TPIA** organisation gets the **full operations platform** (calls → jobs →
     reports → invoicing → accreditation …);
   - a **manpower / technical-staffing agency** gets the **marketplace modules**
     (post/search/award → billing) and lighter ops;
   - a **company / client** gets the **hire + track** surface;
   - an **individual freelancer** gets the **/pro** self-service portal (done — A1/A2).
2. **Private stays private:** an organisation's own employees, jobs, reports and
   invoices are **never** visible to another organisation.
3. **The pool stays shared:** self-listed professionals (and open requirements) are a
   **single shared layer** discoverable by every organisation.

So Phase B is two decisions:
- **B-Entitlements** — how an *organisation account* gets its module bundle.
- **B-Topology** — how a *shared pool* coexists with *isolated private data*.

---

## 2. What already exists to build on (so we reuse, not reinvent)

| Capability | Where | Reuse for Phase B |
|---|---|---|
| Module bundles (`operations`, `admin`, `sales`, `reporting`, `money`, `hr`) | `licence.php PRODUCT_MODULES` | The unit of "what an org can do" |
| Product packages (**TPIA / STAFFING / RECRUITMENT / ENTERPRISE**) | `licence.php PRODUCT_PACKAGES` (P6) | The **preset per org type** |
| Accreditation packs (`inspection` / `labs`) | `packs.php` | 17020/17025 for TPIA orgs |
| Multi-tenant workspaces (subdomain → isolated workspace) | `tenants.php` | Per-org **private** isolation |
| Office / branch scoping (`scope_clause()`, `ua()`) | `access.php` | Row-level scoping precedent |
| Connect marketplace + pool (`cx_*`, `/pro`) | `lib/connect_*` | The **shared** layer |
| Org records: `agencies` (`agency_type`), client/vendor portals | `ops.php`, `portal.php`, `cvp.php` | Existing org concepts |

**Key finding:** per-org module access is *almost already there* — an org that is its
own **tenant** can already carry its own **product package** (TPIA vs STAFFING). What
EXAACT does **not** have is a **pool shared across those tenants** — because tenants
are isolated ("nothing shared between workspaces except the code").

So Phase B's real new work is the **shared marketplace layer across organisations**,
plus a clean **org-account + org-type** concept to hang entitlements on.

---

## 3. The organisation-account model (proposed)

Introduce a first-class **organisation account** with an `org_type` that maps to a
package. This is the single new concept everything else hangs on.

| `org_type` | Package (existing preset) | Gets | Private data |
|---|---|---|---|
| `TPIA` | ENTERPRISE / TPIA + `inspection` pack | Full ops platform + marketplace | Its own jobs, reports, invoices, staff |
| `MANPOWER_AGENCY` | STAFFING | Marketplace (post/search/award → billing) + light ops | Its own requirements, its bench, its invoices |
| `RECRUITMENT_AGENCY` | RECRUITMENT | Requisitions/candidates + marketplace | Its own pipeline |
| `COMPANY` (client) | (client surface) | Hire + track (post requirements, see applicants) | Its own postings |
| `FREELANCER` | — (the `/pro` portal) | Self-service profile + apply | Their own profile + applications |

The owner's rule holds exactly: an org's **employees/roster are private** to that org;
the **self-listed pool is shared**.

---

## 4. B-Topology — three options (the real decision)

### Option 1 — Single shared database, org-scoped rows
All organisations in one database. Every *private* table gains an `org_id`; queries
are scoped by it (extending the existing `scope_clause()` pattern). The pool
(`cx_professionals`) and marketplace (`cx_requirements`/`cx_applications`) are shared
(marketplace rows carry the posting `org_id` but are visible cross-org).

- **Pros:** simplest infra (one DB); the pool is *naturally* shared; reuses EXAACT's
  scoping precedent; per-org entitlement is a row on the org account.
- **Cons:** the biggest data-model change — `org_id` must be added and enforced on
  every private table; a scoping bug becomes a cross-org data leak, so it needs a
  hard, audited scoping layer. Contradicts today's per-tenant isolation model.

### Option 2 — Per-organisation workspaces + a shared marketplace store (recommended)
Each organisation is a **tenant workspace** (isolated private data, exactly as
`tenants.php` does today) provisioned with its **product package** by `org_type`.
The **Connect marketplace + pool** move to a **single shared store** that every
workspace reads/writes for marketplace actions only.

- **Pros:** private data stays *physically* isolated per org (strongest guarantee for
  "an agency's 1,000 employees are private"); **per-org module access is already
  solved** by per-tenant packages; the only shared thing is the marketplace, which is
  *meant* to be shared. Matches the owner's private/shared rule cleanly.
- **Cons:** needs a shared marketplace store distinct from each workspace DB, and a
  thin access path from a workspace to it (a shared connection or an internal API).
  The `cx_*` Connect tables migrate from per-workspace to the shared store.

### Option 3 — Hybrid: shared-marketplace host + federated heavy orgs
The current install *is* the shared marketplace host; light orgs (companies,
agencies, freelancers) are accounts within it; a heavy **TPIA** runs its own workspace
and **federates** to the shared pool via the marketplace API.

- **Pros:** incremental; nothing forces a big-bang migration.
- **Cons:** two access paths to maintain; the most moving parts long-term.

### Recommendation
**Option 2.** It honours the private/shared rule with physical isolation, and it
**reuses** EXAACT's tenant + product-package machinery for per-org modules — so the
genuinely new build is confined to *one* thing: making the Connect marketplace a
shared store. Option 1 is the fallback if a single-deployment (no per-tenant infra) is
preferred, accepting the org-scoping rework.

---

## 5. An incremental path (so we don't need the big infra step first)

Phase B can land in additive stages, each shippable and safe:

- **B0 — Organisation accounts + org-type + per-account entitlement (additive, now).**
  Add an `organisations` concept (or extend `agencies`/party) carrying `org_type` and a
  **module-bundle** field; a login belongs to an org; the module gate reads the org's
  bundle where set, else the install default. *Within a single deployment this already
  delivers "a TPIA org sees full modules, a manpower agency sees the marketplace,"
  sharing the pool naturally (one DB) — Option 1-lite, scoped to the marketplace era.*
  > **B0 ✅ delivered** — `cx_organisations` registry + `connect_org_types()`
  > (TPIA/MANPOWER_AGENCY/RECRUITMENT_AGENCY/ENTERPRISE/COMPANY/FREELANCER) each
  > mapped to a module bundle derived from the existing **product packages**
  > (`connect_org_type_modules`), a `connect_org_can_module()` gate helper, and a
  > master-only admin screen at `/connect-orgs`. `lib/connect_org.php`; 19 tests.
  > **This is representation + provisioning only — it does not yet change any live
  > gate for external orgs** (that is B2, behind the topology decision + a security
  > review). Next: **B1 onboarding**, then **B2** when true isolation is required.
- **B1 — Onboarding per org type.** Self-service sign-up that provisions the account
  with its package (reusing `product_package_apply`).
  > **B1 ✅ delivered** — a public onboarding page at **`/join`** (dispatched
  > pre-login) where an organisation applies (name, type — with a live preview of
  > the modules it will get — and contact), landing **PENDING**; a platform admin
  > approves it to **ACTIVE** on `/connect-orgs` (pending badge on the tile).
  > `connect_org_apply` / `connect_org_approve`; 11 tests. Still representation +
  > an approval queue — no live gate change (B2).
- **B2 — Marketplace store separation (only if/when true per-tenant isolation is
  required).** Move the `cx_*` layer to a shared store and point each workspace at it
  (Option 2). This is the infra step; do it when multi-deployment isolation is actually
  needed, not before.

B0 + B1 are additive and get the *product* behaviour the owner wants inside one app.
B2 is the infra hardening for scale/isolation, taken deliberately.

---

## 6. Access enforcement

- **Per-org modules:** the existing module gate (`ops_module_gate()` → `mod.<key>.view`)
  and `licence_enabled()` already decide visibility. Phase B feeds them from the
  **org's bundle** (via `product_package_apply` semantics) instead of only the install
  setting. No new permission *type* — the same gates, sourced per-org.
- **Marketplace visibility:** the shared pool + open requirements are visible to any
  org whose bundle includes the marketplace module (a new `connect` module, or reuse
  `hr`/`sales`). A requirement's private detail (applicants, terms, billing) is visible
  only to its posting org and platform admins.
- **Private data:** never crosses orgs — enforced by workspace isolation (Option 2) or
  `org_id` scoping (Option 1).

---

## 7. Data model (proposed, additive-first)

- `organisations` — `id, name, org_type, package_key, status, created_at` (or extend
  `agencies` with `org_type` + `package_key`, which already has `agency_type`).
- A link from each **login** (staff/client/vendor) to its `organisation_id`.
- Marketplace rows tagged with the **posting org** (`cx_requirements.org_id`) so the
  shared board can show "who posted" and scope private detail.
- Nothing on the **shared pool** (`cx_professionals`) is org-owned — it is the person's.

All of the above is **additive** (new tables/columns), consistent with everything built
so far.

---

## 8. Risks & the decisions that need your sign-off

1. **Topology (§4):** Option 2 (per-tenant + shared store) vs Option 1 (single DB,
   org-scoped). This is *the* decision — it sets the isolation model.
2. **Cross-org data isolation is a security boundary.** Whichever option, it needs a
   hard, tested scoping/isolation layer + a security review before any real multi-org
   data goes in. (A leak here is the worst failure mode.)
3. **New permission surface:** a `connect` product module and org-type entitlements go
   into `docs/02-permission-matrix.md` with your approval (per the standing rule).
4. **Money & compliance per org:** each org invoices its own clients; platform
   commission (blueprint's 10–15%) is a separate money flow to design with finance/CA
   sign-off (ties to K8 escrow).
5. **Migration:** existing single-install data becomes "org #1"; the path must be
   non-destructive and reversible.

---

## 9. Recommendation

1. Adopt the **organisation-account + org-type** model (§3) — the clean hook for
   everything.
2. Build **B0 (per-org entitlement) + B1 (onboarding)** additively now — this delivers
   the *product* behaviour ("360 by all") inside one deployment, reusing the existing
   package machinery, with the pool shared naturally.
3. Take **Option 2 / B2 (shared marketplace store + per-tenant isolation)** as the
   deliberate infra step, with a security review, when true multi-deployment isolation
   is required.

**Nothing here is built yet.** On your nod to the topology (§4) and the org-account
model (§3), I'll turn B0 into a slice plan (additive, tested, docs-with-code) exactly
like the K/A slices — starting with the organisation account + org-type + per-org
module gate.
