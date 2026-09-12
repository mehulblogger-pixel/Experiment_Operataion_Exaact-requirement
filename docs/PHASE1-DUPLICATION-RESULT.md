# PHASE 1 — Duplication Audit Result

Per §43. Each configuration concept is classified. **No DELETE in Phase 1** — nothing is removed until proven redundant and superseded (§42).

Classification key: **KEEP** (authoritative, unchanged) · **EXTEND** (add without duplicating) · **MERGE** (fold two into one) · **REDIRECT** (legacy route kept, points to canonical) · **DEPRECATE** (mark for later removal) · **DELETE** (only after proof — none this phase).

| # | Concept | Systems found | Duplicate? | Classification | Action in Phase 1 |
|---|---|---|---|---|---|
| 1 | First-run setup | `setup.php` (`/setup`) | No | **KEEP** | Unchanged; remains the boot gate |
| 2 | Guided onboarding | `onboarding.php` (`/welcome`) | Partial overlap with the new cockpit home | **MERGE (consume)** | Cockpit consumes `onboarding_steps()`; `/welcome` **REDIRECT** → cockpit. No second wizard. |
| 3 | Module activation | `licence.php` (`/settings` Modules, `/licence`, `/product-package`) | No | **KEEP** | Cockpit calls `licence_save()`; single engine |
| 4 | Navigation | `areas.php` + `layout_top.php` + `navindex.php` | `navindex.php` is a search index over the same areas — not a rival menu | **KEEP** | Cockpit reads `areas.php`; no new menu system (§70) |
| 5 | Forms — built-in | `formdesign.php` (`/form-designer`) | No | **KEEP** | Cockpit links to it (canonical builder) |
| 6 | Forms — custom | `customforms.php` (`/cforms`) | No | **KEEP** | Cockpit links to it |
| 7 | Custom fields | `lookups.php` (`/custom-fields`) — also surfaced inside form-designer | Same table (`custom_fields`), two entry points | **KEEP (one store)** | Both entry points already write the one `custom_fields` table — not a duplicate store. Cockpit links contextually. |
| 8 | Masters / dropdowns | `lookups.php` (`/lookup`, `/masters`) | No | **KEEP** | Cockpit links; capability catalogue reuses this engine |
| 9 | Permissions | `access.php` (`/access`) | No | **KEEP** | Cockpit links; single engine (§71) |
| 10 | Terminology | `terms.php` (`/terminology`) | No | **KEEP** | Cockpit links (§27) |
| 11 | Audit | `idems.php` (`idems_log`) + `setting_set()` auto-audit | No | **KEEP** | Cockpit changes are audited via existing chain (§48) |
| 12 | **Capabilities** | `connect_capability.php` (`cx_org_capabilities`, per marketplace party) **vs** the new tenant-level `company_capabilities` | Potential — both express "what the org does" | **EXTEND, not duplicate** | See ¶ below. Kept separate by scope, vocabulary aligned. |
| 13 | Company profile | `company-profile` screen + `settings` company keys | No | **KEEP + EXTEND** | Scalar profile reused as-is; only the multi-select capability is added |
| 14 | Setup/getting-started status | `onboarding.php` counts | Overlaps new "configuration status" | **EXTEND** | Cockpit's status engine *includes* onboarding counts rather than re-deriving them differently |

## The one real duplication risk — capabilities (#12), resolved

Two capability notions exist:

- **`cx_org_capabilities`** (`connect_capability.php`): capabilities of a **marketplace organisation** (a party id), gating **marketplace visibility** (TPIA / technical-manpower supply / freelance resource supply). It is off entirely for tenants that don't use the marketplace.
- **`company_capabilities`** (new, Phase 1): the **tenant company's** business mix ("Recruitment + Technical Manpower"), used by the cockpit to orient setup and, in Phase 2, to drive templates.

**Why not merge now:** `cx_org_capabilities` is party-scoped and marketplace-coupled; forcing the tenant-level profile through it would (a) require a marketplace party for every tenant and (b) couple recruitment-only tenants to a module they've switched off. That is more coupling, not less.

**How duplication is avoided anyway:** the **catalogue** of capability values is a single master list (`business_activity`, via `lookups.php`), and its codes are aligned with `connect_capability.php`'s vocabulary. So the two tables reference the same words and can never disagree. If a future phase unifies them, it does so over one shared catalogue — no data migration of divergent vocabularies.

**Classification:** EXTEND (add tenant-level selection) — reviewed for MERGE in a later phase, not Phase 1.

## Deprecations scheduled: none

No route is deprecated or deleted in Phase 1. Every existing configuration screen keeps working and is reached both directly (legacy) and contextually (cockpit). Redundancy will be reconsidered only after the cockpit is proven in production (§42, §90).
