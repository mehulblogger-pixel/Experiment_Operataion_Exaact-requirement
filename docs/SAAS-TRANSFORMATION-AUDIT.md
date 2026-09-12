# EXAACT — Universal Configurable Multi-Tenant SaaS: Transformation Audit

**Prepared by:** Lead SaaS Architect (per the "Universal Configurable Multi-Tenant SaaS Transformation" brief, Section 0)
**Scope:** `phpapp/` — the live EXAACT operations platform (single-URL multi-tenant, MilesWeb managed hosting, PHP 8, MySQL prod / SQLite dev, PDO, no framework).
**Method:** Inspect first, reuse first, never duplicate. This report inventories what already exists before any architecture change, as the brief mandates.

---

## 1. Executive summary (plain language)

**This application is already about 80% of a configurable multi-tenant SaaS.** Every framework the brief warns against duplicating — multi-tenancy, dynamic modules, terminology, master data, custom fields, a form builder, permissions, billing, public signup, first-run setup — **already exists and works.** There is nothing to rebuild.

What is *missing* is not more engines. It is the **connective layer** on top of them:

1. **Customer-driven setup** — today a new company is configured mostly by you (the platform owner). The goal is that the *customer* answers a few plain questions and the platform configures itself (modules, wording, forms, lists) from an industry template.
2. **One configuration cockpit** — today those engines live on ~9 separate admin screens. The goal is a single "Company Setup" home that presents them in one guided place.
3. **Customer-configurable public pages** — a company's own public home/landing page, careers page and payment/subscription page, editable by the customer. *(Payment and careers pages already exist; the home/landing builder is the main new piece — see §4.)*

**The transformation is therefore orchestration + a small number of new configurable surfaces — not a rewrite, and not a second framework.**

---

## 2. Reuse inventory — what already exists (do NOT duplicate)

Every row below is a working system in the current codebase. The brief's "do NOT create duplicate X" list is satisfied by these.

| Capability | Where it lives | What it already does | Verdict |
|---|---|---|---|
| **Multi-tenant spine** | `lib/saas_tenants.php`, `lib/tenants.php`, `config.php` | Isolated database **per company**, session/subdomain tenant resolution, Companies console, exec-free provisioning, self-healing registry | **Reuse** |
| **Public workspace signup** | `lib/tenant_signup.php` | A company applies for its own workspace → lands PENDING → Super-Admin approves → auto-provisions | **Reuse & surface** |
| **First-run setup wizard** | `lib/setup.php` | Browser-based: writes DB config, then walks the admin once through password, company name, **industry**, FY, currency, privacy contact; then never shows again | **Reuse & extend** |
| **Guided onboarding** | `lib/onboarding.php` | Computes "getting started" next-steps from real data, mode-aware (cloud vs licence) | **Reuse & extend** |
| **Dynamic modules (licensing)** | `lib/licence.php` | 6 sellable modules (Admin core, Operations, Sales, Reporting, Money, People & hiring) switched on/off **per company**; `licence_enabled()` gates every screen | **Reuse** — this *is* dynamic modules |
| **Industry packages** | `lib/licence.php` (`PRODUCT_PACKAGES`) | One-click presets: TPIA, Staffing, Recruitment, Recruitment-HR, Enterprise → sets modules + industry pack | **Reuse & extend** |
| **Plan → modules** | `lib/superadmin.php` (`superadmin_tiers`) | Each subscription plan maps to a module set (e.g. Recruitment = Admin + People) | **Reuse** |
| **Terminology system** | `lib/terms.php` | Rename any label on any screen **per company** (`/terminology`); `T/Tl/TH/TP` helpers everywhere | **Reuse** — single source |
| **Master data / dropdowns** | `lib/lookups.php` | `lookup_types`/`lookup_values`, hierarchical lists, "appears on which forms", module-grouped | **Reuse** — single source |
| **Custom fields** | `lib/lookups.php` (`custom_fields`/`custom_values`) | Add fields of any type (text, paragraph, number, date, dropdown, cascading) per company, per form | **Reuse** |
| **Form builder** | `lib/formdesign.php` + `/form-designer` | One-screen builder: rename/reorder/hide/require built-in fields **+ add/delete fields + build dropdowns** (unified this session) | **Reuse** |
| **Custom forms** | `lib/customforms.php` (`/cforms`) | Company designs entirely new forms with their own records | **Reuse** |
| **Permissions & roles** | `lib/access.php` | Roles (`ORG_ROLES`), per-module `can()`, role overrides, master scoping, module-aware | **Reuse** — single source |
| **Self-service billing (payment)** | `lib/billing.php` | **Razorpay** checkout, per-seat monthly/annual, HMAC-verified callback → grants paid seats; price configurable | **Reuse** — payment page already exists |
| **Seat limits & subscription state** | `lib/licencekey.php` | Live paid-until state, seat enforcement, signed-key + paid-subscription merge | **Reuse** |
| **Public careers page** | `lib/careers.php` | Public job posting + application intake that creates candidates | **Reuse** |
| **Navigation / areas** | `lib/areas.php` | Flat, module-gated area homes (the left rail) — one source of truth for what each company sees | **Reuse & extend** |
| **UI/UX standard** | `docs/05-ui-ux-blueprint.md`, `docs/DESIGN-SYSTEM.md` | The governing "zero-training" UI standard | **Follow** |

**Conclusion:** there is no missing framework. The engines are present, tested, and multi-tenant-aware.

---

## 3. What is genuinely missing (the real gap)

The gap is **orchestration and customer self-service**, not capability:

- **G1 — Customer-driven configuration.** The industry template that pre-fills modules + terminology + forms + lists exists only partially (product packages set modules + pack, but not terminology/forms/lists in one motion). A customer can't yet answer "what does your business do?" and get a fully shaped workspace.
- **G2 — Scattered setup.** The nine configuration tools (modules, terminology, masters, fields, forms, custom forms, roles, company profile, billing) are nine separate menu items. A non-technical owner has no single "set up my company" home.
- **G3 — No customer-editable public/home page.** A company can post jobs (careers) and take payment (Razorpay), but cannot yet compose its **own public landing/home page** (logo, tagline, sections, links to its careers/payment pages).
- **G4 — Industry coverage.** Templates today lean recruitment/inspection. A "universal" platform needs a clean way to add new industry templates without code (or with a thin data layer).

Everything else the brief asks for is **already delivered** by §2.

---

## 4. Direct answer to your question — payment page, home page, per-company pages

> *"If a company can set up their pages as per their requirements, will we be able to add the payment page, home page etc. accordingly afterwards?"*

**Yes — and here is exactly how each one stands:**

| Page a company wants | Status today | What's needed |
|---|---|---|
| **Payment / subscription page** | ✅ **Already exists** — `lib/billing.php` (Razorpay, per-seat, monthly/annual, secure). A company can buy seats/modules and pay online now. | Only to *surface* it in the setup cockpit and (optionally) let them price their own plans. |
| **Careers / public job page** | ✅ **Already exists** — `lib/careers.php`. A company posts jobs publicly and applications become candidates. | Only to let them brand/configure it. |
| **Public home / landing page** | ⚠️ **New** — this is the main new surface. | A small, reuse-first page builder: the company edits logo, name, tagline, a few content blocks, and links to their careers/payment pages. Stored as tenant settings + custom content, rendered on a public route. No new framework — it reuses the existing settings + custom-content + terminology engines. |
| **Any other internal screen** (dashboards, extra forms) | ✅ **Already configurable** — custom forms + form designer + custom fields + role workspaces. | Nothing new. |

**Architecture guarantee:** because every page is driven by *per-tenant settings + reusable engines*, adding a new configurable page later (home, landing, a microsite, a client portal skin) is **additive** — it never touches another company and never requires a second configuration system. So yes: we can keep adding customer-configurable pages afterwards, safely, one at a time.

---

## 5. Target architecture (reuse-first)

```
                    ┌─────────────────────────────────────────────┐
                    │  CONTROL PLANE (platform owner)              │
                    │  saas_tenants.php · Companies console        │
                    │  plans → modules · provisioning · billing    │
                    └───────────────────────┬─────────────────────┘
                                            │  provisions isolated DB per company
                    ┌───────────────────────▼─────────────────────┐
                    │  PER-COMPANY WORKSPACE (isolated database)   │
                    │                                              │
                    │  ①  Customer-driven SETUP  ← NEW orchestration│
                    │      (industry answers → template applies)   │
                    │        │ reuses ▼                            │
                    │  ┌─────┴──────────────────────────────────┐  │
                    │  │  EXISTING ENGINES (single source each)  │  │
                    │  │  • licence.php     modules on/off       │  │
                    │  │  • terms.php       terminology          │  │
                    │  │  • lookups.php     masters + fields     │  │
                    │  │  • formdesign.php  form builder         │  │
                    │  │  • customforms.php custom forms         │  │
                    │  │  • access.php      roles & permissions  │  │
                    │  │  • billing.php     payment (Razorpay)   │  │
                    │  │  • careers.php     public careers page  │  │
                    │  └─────────────────────────────────────────┘  │
                    │  ②  One "Company Setup" COCKPIT  ← NEW surface │
                    │      (guided links into the engines above)   │
                    │  ③  Public HOME/LANDING builder  ← NEW surface │
                    └──────────────────────────────────────────────┘
```

The two new orchestration pieces (① setup, ② cockpit) and the new public surface (③ home) **call the existing engines** — they store nothing the engines don't already own. Zero duplication.

---

## 6. Anti-duplication guarantees (mapped to code)

Per Section 0 of the brief, the following are hard commitments, each enforced by reusing a named single-source system:

- **No parallel application** — all work lands in `phpapp/`, on the existing router (`index.php` → `lib/ops.php` dispatch).
- **No second configuration framework** — configuration = the existing `settings` table + `licence.php` + `terms.php`. New setup screens *write into these*, never a new store.
- **No duplicate master system** — all lists = `lookups.php` (`lookup_types`/`lookup_values`).
- **No duplicate permission system** — all access = `access.php` (`can()`, `ORG_ROLES`, role overrides).
- **No duplicate form builder** — all form shaping = `formdesign.php` + `custom_fields` + `customforms.php`.
- **No duplicate terminology system** — all wording = `terms.php` (`T/Tl/TH/TP`).
- **No duplicate billing** — all payment = `billing.php` (Razorpay).

Any new file added during the transformation will be an **orchestrator or a view** over these — never a re-implementation.

---

## 7. Proposed phase roadmap (so your "Phase 1" aligns)

This is a suggested order of value; your pasted Phase 1 will take precedence.

- **Phase 1 — Company Setup cockpit (G2).** One guided "Set up your company" home that gathers the nine existing tools with plain-language steps and progress. Pure orchestration; lowest risk, immediate clarity. *(Reuses everything; builds no engine.)*
- **Phase 2 — Industry templates / customer-driven setup (G1, G4).** Extend product packages into full templates that also set terminology + starter lists + form shape, chosen by the customer answering a few questions on first login. *(Extends `licence.php` presets + `setup.php`.)*
- **Phase 3 — Public home/landing page builder (G3).** A per-company public page (logo, tagline, content blocks, links to careers + payment). *(New view + tenant settings; reuses careers + billing.)*
- **Phase 4 — Polish & universality.** Add further industry templates, per-plan pricing controls, and any per-company page skins as needed — all additive.

Each phase is independently shippable, fully tested, and deployable on MilesWeb (no exec, no Composer, no Node).

---

## 8. Constraints honoured throughout

- **Managed shared hosting (MilesWeb):** no `exec()`, no Composer, no Node build; opcache stale-serving handled via `/refresh.php`.
- **Dual database:** every schema change works on both MySQL (prod) and SQLite (dev/test) via the existing `db.php` helpers.
- **Tenant isolation:** no write ever crosses a company boundary; provisioning only ADDS a workspace.
- **PWA + existing modules:** preserved; nothing valid is rewritten or removed.
- **Test discipline:** the suite (6,500+ checks) stays green on every phase.

---

*This audit is the "inspect-first" deliverable. It changes no code. It exists so the transformation reuses the platform's own proven engines and adds only the thin orchestration and configurable surfaces that are genuinely missing.*
