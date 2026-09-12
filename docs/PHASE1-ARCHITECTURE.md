# PHASE 1 — Architecture

## The one new capability: a Configuration Orchestrator

`lib/setup_cockpit.php` is the single new architectural piece. It is a **read-and-link orchestrator**: it answers "what has this tenant enabled / configured / still needs, and where do I change it?" by consuming the existing engines. It holds no configuration another system owns.

```
        Tenant Admin (non-technical)
                 │
                 ▼
   ┌───────────────────────────────────────────┐
   │  lib/setup_cockpit.php  (ORCHESTRATOR)     │
   │                                            │
   │  cockpit_sections()      status per area   │
   │  cockpit_readiness()     overall %         │
   │  cockpit_modules()       ← licence.php     │
   │  cockpit_module_dependents() ← PRODUCT_MODULES │
   │  cockpit_capabilities()  ← company_capabilities │
   │  cockpit_capability_catalogue() ← connect_cap_catalog() │
   │  cockpit_health()        real checks       │
   │  cockpit_checklist()     ← onboarding_steps() │
   │  cockpit_search()        config index      │
   │  §76 getters (enabled_modules/features,    │
   │    can_use_feature, configuration)         │
   └───────────────────────────────────────────┘
        │ reads             │ links (redirects)
        ▼                   ▼
  ┌──────────────┐   ┌──────────────────────────────┐
  │ licence.php  │   │ /form-designer  /masters      │
  │ areas.php    │   │ /users /access /terminology   │
  │ terms.php    │   │ /company-profile /subscription│
  │ lookups.php  │   └──────────────────────────────┘
  │ formdesign   │        (canonical screens, unchanged)
  │ customforms  │
  │ access.php   │
  │ idems (audit)│
  └──────────────┘
```

## Design rules honoured

- **Single source of truth (§44):** the orchestrator writes only `company_capabilities` and two `settings` keys. Every other change is delegated (`licence_save()`) or reached by link. Proven by tests: a form field added on the canonical engine is seen by the cockpit; a module toggled through `licence_save()` is reflected by the cockpit; toggling stores to the single `modules_off` setting.
- **One access decision (§77):** visibility (`cockpit_can()`, `licence_enabled()`, area gating) and backend security (`ops_require()` + the global module gate) use the same `can()`/licence rules — the cockpit adds no parallel permission logic (§71).
- **Read-mostly, cache-light (§53/§54):** the cockpit computes from summaries (`licence_summary()`, counts) and caches health per request; it does not load every configuration object, and introduces no new cache layer.
- **Customer language (§34/§35):** "Features", "what does your company do?", "Turn off this feature", "Dropdown lists" — no technical identifiers surface.
- **Not a giant settings page (§82/§83):** the home is a contextual control centre — section cards, an attention list, a checklist, quick actions — with the detail living on focused sub-pages and the canonical screens.

## What is deliberately deferred (Phase 2+, not built — §3/§12)

The capability→module mapping exists and is **read** to explain "why is this available", but the cockpit does **not** auto-enable modules from capabilities, and builds **no** industry-template engine, public landing-page builder, or new business workflow. Those are later phases.
