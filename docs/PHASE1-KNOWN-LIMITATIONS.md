# PHASE 1 — Known Limitations

Recorded honestly (§86). None of these block Phase 1 acceptance; several are Phase 2+ by design.

## Deferred by phase boundary (§3) — intentionally NOT built
- **Industry template engine (Phase 2):** capabilities are captured and their module mapping is *read* to explain availability, but choosing a capability does **not** auto-configure modules/terminology/forms yet.
- **Public landing-page builder (Phase 3).**
- **General app-wide UX polish (Phase 4)** and any new business workflows.

## Form-builder capability audit (§22) — gaps documented, not worked around
The canonical Form Designer supports: add, delete, reorder, hide, required/optional, change label, build dropdown, edit dropdown options, and field types text / paragraph / number / date / dropdown. **Not yet supported** (to be implemented in the canonical engine in a later phase — no second builder was created to compensate):
- Duplicate a field
- Change the *type* of an existing field (kept fixed so saved data is never orphaned)
- Explicit validation rules (min/max, regex, ranges)
- Conditional/visibility rules (show field B only if A = x)
- In-page live preview

## Cockpit scope limits (acceptable for Phase 1)
- **Health checks are a small, honest set** (§32) — a lightweight extensible list, not a full rule engine. It flags: single-user team, empty dropdown lists, unset capabilities. More checks are additive later.
- **Masters / roles / terminology** are reached by contextual link to their canonical screens rather than embedded editors. This satisfies "reach it from the hub" (§23/§25/§27) without duplicating those editors; a future phase may inline a drawer.
- **Module toggle vs signed licence:** if a company runs on a *signed* licence key (`lk_modules()`), that key outranks the settings toggle (existing, documented behaviour). Cloud/plan tenants (the norm here) use the `modules_off` setting, which the cockpit drives correctly.
- **Unsaved-changes guard (§49):** the cockpit uses plain per-form POSTs (each action saves immediately), so there is no multi-field dirty state to lose. A cross-field "unsaved changes" prompt is unnecessary in Phase 1; if richer inline editors are added later, add the guard then.

## Not regressions
- Existing tenants are read live; nothing is reset (§39/§40).
- All legacy configuration routes still work (§90).
