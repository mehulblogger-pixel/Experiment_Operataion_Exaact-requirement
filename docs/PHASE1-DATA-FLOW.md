# PHASE 1 — Data Flow

How a customer's intent flows through the **existing** engines. The Cockpit is the front door; every arrow lands on a system that already owns that data.

```
   Customer (Tenant Admin, non-technical)
        │
        ▼
   ┌─────────────────────────────────────────────────────────┐
   │  COMPANY SETUP COCKPIT   (new orchestration — lib/setup_cockpit.php) │
   │  "What do we do? What's on? What's configured? What's next?"        │
   └─────────────────────────────────────────────────────────┘
        │
        ├───────────────► Company / Business Profile
        │                   • scalar info → settings (REUSE company-profile)
        │                   • capabilities (multi-select) → company_capabilities
        │                       catalogue ← business_activity master (lookups.php)
        │
        ├───────────────► Modules & Features
        │                   reads  licence_summary()      (licence.php)
        │                   writes licence_save()          → settings.modules_off
        │                   dependency map reads PRODUCT_MODULES
        │
        ├───────────────► Navigation
        │                   reads ops_area_def()/ops_area_has()  (areas.php)
        │                   (what each enabled feature surfaces — read only)
        │
        ├───────────────► Forms
        │                   reads fd_forms()/fd_custom_fields()  (formdesign.php)
        │                         cforms_all()                   (customforms.php)
        │                   link  → /form-designer  (canonical builder)
        │
        ├───────────────► Masters & Dropdowns
        │                   reads lookup_types/lookup_values     (lookups.php)
        │                   link  → /lookup , /custom-fields
        │
        ├───────────────► Roles & Permissions
        │                   reads ORG_ROLES, role_perms()        (access.php)
        │                   link  → /access , /users
        │
        ├───────────────► Terminology
        │                   reads term_overrides()               (terms.php)
        │                   link  → /terminology
        │
        └───────────────► Configuration Health & Checklist
                            computed from the reads above
                            + onboarding_steps()                 (onboarding.php)
        │
        ▼
   ┌─────────────────────────────────────────────────────────┐
   │  CUSTOMER WORKSPACE  (unchanged runtime)                  │
   │  Every screen still gates on can() + licence_enabled()    │
   │  exactly as before. The cockpit changed no runtime path.  │
   └─────────────────────────────────────────────────────────┘
```

## Read vs write, made explicit

| Cockpit action | Direction | Lands on |
|---|---|---|
| Show module list & state | read | `licence_summary()` |
| Turn a feature on/off | write | `licence_save()` → `settings.modules_off` (audited) |
| Set business capabilities | write | `company_capabilities` (new orchestration table) |
| Show/choose capability options | read | `business_activity` master (`lookups.php`) |
| Show form status | read | `fd_forms()`, `custom_fields_for()`, `cforms_all()` |
| Configure a form | redirect | `/form-designer` (canonical) |
| Add a dropdown value | redirect | `/lookup` / form-designer inline (canonical) |
| Show/edit roles | redirect | `/access`, `/users` (canonical) |
| Show/edit terminology | redirect | `/terminology` (canonical) |
| Compute readiness % | read | all of the above + `onboarding_steps()` |
| Record a change | write | `idems_log()` via `setting_set()` (existing audit) |

## First-login sequence (§36, §37)

```
Signup (tenant_signup.php)
   → Super-Admin approve → workspace provisioned (isolated DB)
      → First login as owner
         → setup_needed()?  yes → /setup (setup.php, unchanged)
         → onboarding_incomplete()?  yes → COCKPIT (was /welcome)
         → else → normal dashboard
```

No duplicate questions: `/setup` still owns first-run basics; the cockpit owns everything after, and consumes `onboarding_steps()` rather than re-asking.

## Canonical-source guarantee (proved by test §59, §60)

A change made in the cockpit's contextual link and a change made on the legacy route are **the same write** to the **same table**, because the cockpit only *links to* those screens (forms, masters, roles, terminology) — it never stores a copy. The only data the cockpit owns is the business profile capabilities, which no other screen owns.
