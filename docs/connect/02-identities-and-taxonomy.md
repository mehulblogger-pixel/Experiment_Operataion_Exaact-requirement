# Connect — Adopted Identities & Taxonomy

The Inspect Connect blueprint (and its reference code) **already defines** the account
types and the full industry taxonomy. Per the owner's direction, we **adopt these as
given** rather than invent new ones — this resolves decision §5a of
[`00-integration-program.md`](00-integration-program.md) at the *role-set* level. What
remains is only *how each role maps onto EXAACT's existing access model* (recommended
below, for confirmation).

Source of truth: `mgh-inspection-bridge` — `app/Models/User.php`,
`app/Models/CompanyUser.php`, `database/migrations/*_create_taxonomy_tables.php`,
`database/seeders/data/taxonomy.json`.

## 1. Account roles (adopted)

Top-level `users.role` (default `inspector`):

| Role | Who they are | In the marketplace |
|---|---|---|
| **inspector** | An individual technical professional (inspector, welder, NDT, QA/QC, site engineer…). | **Applies for / supplies** requirements. |
| **company** | A client / employer organisation. Has a team (sub-roles below). | **Posts** requirements. |
| **agency** | A **manpower / technical agency** — supplies technical manpower from its own bench. | **Both:** posts requirements *and* applies/supplies on behalf of its people. |
| **admin** | Platform operations. | Moderates: verification, job monitor, payouts, disputes. |
| **superadmin** | Platform root. | Full control. |

**Company team sub-roles** (`company_user` pivot, scoped **per company** — the same
person can be a requester at one company and an approver at another):
`owner` · `requester` · `approver` · `finance`.

**Verification tiers** (`users.verification_tier`, default `registered`):
`registered → identity → credentials → proven` (M2).

### "Anyone posts, anyone applies" — resolved against these roles
- **Post a requirement:** `company` (via `requester`/`approver`) and `agency`.
- **Apply / supply:** `inspector` (individual) and `agency` (from its bench).
- **Moderate:** `admin` / `superadmin`.

So the open two-sided marketplace the owner wants is exactly this role set — no new
role type needs inventing; `agency` is the "manpower technical agency" the owner
called out, and it deliberately sits on **both** sides.

## 2. Mapping onto EXAACT's access model (recommended — please confirm)

EXAACT already has three access systems: internal `ORG_ROLES` (`access.php`), a
**client portal** (`pcan()`), and a **vendor portal** (`vendor_users`). The cleanest,
smallest-change mapping (decision §5a Option A) is:

| Blueprint role | EXAACT home | Note |
|---|---|---|
| `company` | **Client portal** (`pcan()`) + a `requester/approver/finance` team layer | A client/employer is already EXAACT's "client". Add the per-company team sub-roles. |
| `agency` | **Vendor portal** (`vendor_users`), extended with a **manpower bench** | An agency that supplies people is closest to EXAACT's "vendor/supplier"; extend it so it can also post and supply. |
| `inspector` | An **external professional** login | Extend the vendor/inspector portal concept for a self-registering individual, OR a lightweight external-professional account. Kept **out of** internal `ORG_ROLES`. |
| `admin` / `superadmin` | EXAACT **admin / master** | Existing. |

New *permissions* (post / apply / shortlist / award / …) get written into
`docs/02-permission-matrix.md` when their slice lands. The **role set** is adopted;
only the portal mapping above needs a yes/adjust.

## 3. Industry taxonomy (adopted — imported wholesale in slice K0)

Already defined as **9 versioned master tables**, seeded from
`database/seeders/data/taxonomy.json`. K0 imports this exact content into EXAACT as
admin-extensible seed data (never hard-coded), reconciled with EXAACT's existing
`industry.php` / `methods.php` / `competence.php`.

| Table | Count | Shape (example) |
|---|---|---|
| `sectors` | 27 | `{code: OG_UP, name: "Oil & Gas — Upstream", detail: …}` |
| `equipment_groups` (+ `equipment_types`) | 11 groups | `{code: STATIC, name: "Static equipment", types: […]}` |
| `materials` | 18 | `{code: CS, name: "Carbon steel", grades: "A105, A106, SA 516…"}` |
| `disciplines` | 22 | `{code: WELD, name: "Welding inspection"}` |
| `inspection_stages` | 17 | `{seq: 1, code: PIM, name: "Pre-inspection meeting"}` |
| `standards` | 13 families | `{family: ASME, codes: [I, II, V, VIII Div 1, …]}` |
| `certifications_registry` | 24 | `{code: CSWIP_3_0, name: …, issuer: TWI, verify_route: public_register}` |
| `taxonomy_versions` | — | versioning of the master data |

This taxonomy is the backbone of matching, the Passport Expertise Index, the Concierge,
and assessment routing — every requirement = sector + equipment + material +
discipline(s) + stage + standards, and every professional profile carries the same
dimensions.

## 4. Effect on the plan

- **§5a (roles):** resolved by adoption — the role *set* is fixed; only the EXAACT
  portal mapping in §2 needs the owner's yes/adjust.
- **K0 (taxonomy):** content is now concrete — import `taxonomy.json`'s 9 tables.
- **K1 (Passport), K2 (post/apply), K3 (matching):** all read these dimensions, so
  K0 lands first and they build on it.
