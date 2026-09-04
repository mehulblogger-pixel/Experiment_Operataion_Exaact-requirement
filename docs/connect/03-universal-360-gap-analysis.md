# Connect — Universal 360 Platform: User Types & Gap Analysis

Research findings for the owner's vision: **one universal platform where individual
technical manpower build rich self-service profiles, that pool is discoverable by
agencies / TPIAs / companies, and each organisation type gets the module bundle it
needs — nobody leaves the app.** This document lists the user types that exist
today and the real gaps between here and that vision, with how to fill them.

## 1. User types in the platform today

### A. Internal staff — `ORG_ROLES` (16 roles, `access.php`)
The TPIA's own employees; access by permission. Full app.
`MASTER_ADMIN` · `BUSINESS_DIRECTOR` · `SBU_HEAD` · `BRANCH_MANAGER` ·
`BRANCH_APP_MANAGER` · `OPERATION_MANAGER` · `ASST_MANAGER` · `COORDINATOR` ·
`BUSINESS_DEV_MANAGER` · `KEY_ACCOUNTS_MANAGER` · `MARKETING_MANAGER` ·
`MARKETING_EXECUTIVE` · `FINANCE` · `SR_INSPECTOR` · `INSPECTOR` · `ADMIN` (legacy).

### B. Client portal users — companies (`client_users`, `pcan()`)
External **client/company** logins with their own permission system. Limited
self-service: see their calls/reports/invoices, request work, and (Connect K2b)
**post manpower requirements** + manage applications.

### C. Vendor portal users — vendors/agencies (`vendor_users`, `vcan()`)
External **vendor/agency** logins, separate permission system. Limited: their
reports/issues/qualification, and (Connect K2b) **browse open requirements + apply**.

### D. Data-only records (not logins yet)
- **Inspectors** — the pool. Carry `staff_kind` (asset / **freelancer** / subcon),
  `agency_id`, `trade_id`, `skill_ids`, `passport_token`. **No self-service login.**
- **Agencies** — `agencies` table, `agency_type` default **`MANPOWER`**. A supplier
  record, **not** an account with its own module access.
- **Subcons** — subcontracted individuals.

### E. Public (no login)
Anyone viewing a professional's public **Passport** (`/p/<token>`).

**In short:** logins today = internal staff + client-company + vendor-agency.
The **individual freelancer** and the **agency-as-an-account-with-modules** are
*records*, not *self-service accounts* — which is the heart of the gap.

## 2. How module access is bundled today

- **Product modules** (`licence.php PRODUCT_MODULES`): `operations`, `admin` (core),
  `sales`, `reporting`, `money`, `hr` — switchable bundles.
- **Product packages** (P6): `TPIA`, `STAFFING`, `RECRUITMENT`, `ENTERPRISE` —
  one-click presets over the bundles + accreditation packs.
- **Accreditation packs**: `inspection` (17020) / `labs` (17025).
- **Multi-tenancy** (`tenants.php`): SaaS maps a **subdomain → its own isolated
  workspace**; *"nothing is shared between workspaces except the code."*

**The critical fact:** all of the above gate access **per installation / per tenant
workspace** — every user of one install shares the same module set — and tenants are
**isolated** (no shared data). There is **no per-organisation module entitlement**
inside a shared app, and **no shared pool across organisations**.

## 3. The gaps between today and the universal-360 vision

| # | Gap | Today | The vision needs |
|---|---|---|---|
| **G1** | **Freelancer self-service profile (M4)** | Inspectors are staff-managed records; **no** location/availability/work-type-preference/day-rate/overseas fields; no freelancer login | A freelancer signs up, builds a rich profile: *site jobs vs man-day inspections, disciplines they take, locations they'll work, availability, overseas, rate bands*, and owns their Passport |
| **G2** | **Shared talent-pool discovery** | Matching runs only *inside a requirement*; no open pool search | Agencies / TPIAs / companies **search the whole pool** by those preference filters and invite/hire |
| **G3** | **Per-organisation module entitlements** | Module bundles are per-install/tenant | Each **org account** carries its own bundle: a **TPIA** org → the full operations platform; a **manpower hiring agency** → the marketplace modules — in one shared app |
| **G4** | **Org account types + onboarding** | Only internal staff + limited client/vendor portals | Self-service onboarding for **freelancer**, **manpower agency**, **TPIA agency**, **company/client**, each provisioned with its bundle |
| **G5** | **Shared pool vs isolated tenancy** | Tenants are fully isolated workspaces | A **shared talent/marketplace layer** across orgs, while each org's *own* ops/reports/invoices stay **private** |

## 4. How to fill the gaps

### Phase A — additive, no architectural change (on-mission, buildable now)
Delivers "freelancers build rich profiles; the pool is discoverable by agencies/TPIAs."

> **Progress:** **A1 ✅ delivered** — the self-registered freelancer pool
> (`cx_professionals`, a *distinct* entity from the org's `inspectors` staff) with
> its own **fourth-audience portal** at `/pro` (register, sign in, M4 profile:
> disciplines, work types, locations/pan-India/overseas, availability, rate bands,
> languages) + a passport token. 13 tests, incl. proof a professional is never
> written into an org's staff roster.
>
> **A2 ✅ delivered** — the freelancer **browses open requirements and applies**
> as themselves (`/pro/jobs`, one application per job, deduped on
> `applicant_professional_id`) and **tracks their applications** with live status
> (`/pro/applications`); those applications reach the client/staff side on the
> requirement. 8 tests.
>
> **A3 ✅ delivered** — **talent search over the shared pool** for org accounts
> (`/connect-talent`, a Marketplace tile): filter self-listed professionals by
> discipline / work type / location / availability / free text, open their
> Passport, and **invite** one onto an open requirement. Proven never to return an
> org's private staff. 9 tests. **Next: the award → engagement → invoice bridge**
> (Scenario 3).

**Phase A is complete** (A1 profile · A2 apply · A3 talent search): freelancers
build rich profiles, find and apply to jobs, and are discoverable by
organisations — the shared pool, exactly as specified.

**Award → Engagement → Invoice bridge ✅ delivered** — an AWARDED requirement is
turned (by an explicit "Send to billing" staff action) into a **PENDING billable
event in the existing P4 ledger** (`source = connect/MARKETPLACE_AWARD`),
idempotently, priced from the winning bid × positions, carrying the client to
bill. It then flows through the invoicing chain EXAACT already has (billable
board → finance attestation → books invoice) — no new invoicing engine; the books
ledger stays the single money truth. 10 tests. **This closes Scenario 3's
requirement→invoicing path.**

**M10 crew booking ✅ delivered** — a requirement can carry a **position manifest**
(`cx_positions`: role × discipline × quantity × rate × shift) for shutdown/
turnaround-scale hiring; a crew rollup sums headcount + value, and the
award→invoice bridge **bills the whole crew** (else the single-role figure). A
single-role job needs none of it. 11 tests.

All three owner scenarios are now real: **1** (TPIA finds a freelancer),
**2** (freelancer finds jobs), **3** (staffing co: find candidates → award →
into invoicing).
- **A1 — Freelancer preference profile (M4).** Additive columns on the pool
  (work types: per-visit / day-rate / man-day / long deployment / shutdown;
  base + preferred locations; pan-India / overseas willingness; travel radius;
  availability + notice; site-readiness; rate bands; languages). Reuses the K0
  taxonomy for disciplines/sectors. Feeds matching (K3) and Trust (K5).
- **A2 — Freelancer self-service portal.** A freelancer login (mirroring the
  client/vendor portals): edit their profile, own their Passport, browse open
  requirements, apply, track applications + ratings. This is the individual
  supply side of "find each other."
- **A3 — Talent search over the pool.** A discovery screen (for agency / TPIA /
  company accounts with marketplace access) that searches the pool by the M4
  filters and links to Passports — the pool "accessible to all."

### Phase B — architectural, needs the owner's sign-off (the "360 by all" mechanism)
- **B1 — Per-organisation module entitlements.** Introduce an **org account** whose
  `org_type` (TPIA / MANPOWER_AGENCY / COMPANY / FREELANCER) maps to a module
  bundle, so a TPIA agency gets the full ops platform and a manpower agency gets the
  marketplace — **within one shared platform**. This extends the access model beyond
  per-install packaging and is the biggest decision.
- **B2 — Shared-pool + private-workspace architecture.** Decide how the shared
  marketplace/pool (the `cx_*` layer + profiles, visible across orgs) coexists with
  each org's **private** operations data (jobs, reports, invoices). Recommended shape:
  the **Connect marketplace/pool is the shared layer**; each org's operations stay
  private (a scoped account or its own workspace). This reconciles the isolated-tenant
  model with the shared-pool vision.

**Why the split:** Phase A is additive and safe (new columns, a new portal, a search
screen) and moves the mission forward immediately. Phase B changes the access/tenancy
model — it touches how organisations are provisioned — so it is designed and signed
off deliberately, not slipped in.

## 5. Recommendation

Build **Phase A now** (A1 → A2 → A3): it directly realises "technical manpower create
rich profiles; the pool is accessible to agencies/TPIAs," additively and safely.
Take **Phase B** (per-org entitlements + shared-pool architecture) as a designed
decision with the owner, since it reshapes how each organisation type is onboarded
and what it can see — the true "one universal application used by all" layer.
