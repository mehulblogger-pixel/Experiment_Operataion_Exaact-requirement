# PHASE 1 — Routes

## New routes (all handled by `ops_cockpit()` in `lib/setup_cockpit.php`)

| Route | Method | Purpose | Delegates / reads |
|---|---|---|---|
| `/workspace/setup` | GET | Cockpit home — sections, readiness, health, checklist, search, quick actions | orchestrator (reads all engines) |
| `/workspace/setup/profile` | GET | Business profile — name + multi-select capabilities | `connect_cap_catalog()`, `company_capabilities` |
| `/workspace/setup/profile-save` | POST | Save name + capabilities | `cockpit_capabilities_set()`, `setting_set()` |
| `/workspace/setup/modules` | GET | Customer-friendly features screen (+ disable confirmation) | `licence_summary()` |
| `/workspace/setup/module-toggle` | POST | Turn a feature on/off | **`licence_save()`** (the one module engine) |
| `/workspace/setup/forms` | GET | Forms hub — list + Configure links | `fd_forms()` |

- Dispatched in `lib/ops.php` via `strncmp($route,'workspace/setup',…)`.
- Gated by `cockpit_can()` (master or `settings.manage`) — a normal user is refused (§47).
- Protected by the global CSRF gate (`index.php`) on every POST.
- Conceptual, non-technical URLs (§78) — no file names exposed.

## Contextual links OUT to canonical screens (unchanged, kept working — §42/§72/§73)

`/masters`, `/lookup`, `/custom-fields` · `/form-designer` · `/users`, `/access`, `/role-workspaces` · `/terminology` · `/company-profile` · `/subscription`. The cockpit **links** to these; it never reimplements them.

## Changed navigation / entry points

- `lib/areas.php` — Admin gains a first card **“Company setup” → `/workspace/setup`** (front door, §37); `workspace/setup` added to the admin area's `routes` so the rail marks it current.
- `index.php` — first-login redirect: a workspace admin with incomplete setup lands on `/workspace/setup` once per session; everyone else keeps `/welcome`; `/welcome` still works (§36/§37).

## Routes removed

None. Every legacy configuration route continues to work and is reachable directly and via the cockpit (§90).
