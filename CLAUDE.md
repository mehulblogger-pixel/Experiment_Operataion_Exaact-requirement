# CLAUDE.md

Guidance for any AI/coding session working on this repository.

## Roles and flows — read before building anything

Before implementing any feature, read `docs/01-roles.md`,
`docs/02-permission-matrix.md` and the relevant `docs/04-flows/<role>.md`.
Before creating or changing any user-facing screen, also read
`docs/05-ui-ux-blueprint.md` — the governing UI/UX standard.

Hard rules:

- Never grant a role a permission that is not in `docs/02-permission-matrix.md`.
  If a feature needs a new permission, stop and ask.
- Never add a status or transition that is not in `docs/03-object-lifecycles.md`
  without asking.
- When a change alters who can do what, update `docs/` in the same commit as the
  code. The docs and the code must never disagree.
- Inspectors are phone-first in the field. Coordinators, managers and finance are
  desk-first on a laptop. Design for both; never average them into one middle.
- The UI/UX must never look like an ERP (SAP/Oracle/SGS portal). Every user-facing
  screen must pass the "Zero Training UI" gate in `docs/05-ui-ux-blueprint.md`.
  The blueprint governs look/feel/interaction only — it grants no permissions and
  never overrides the permission matrix or object lifecycles above.

## Test copy and going live — the owner's standing instruction (24 Oct 2026)

The owner tests every change on the TEST COPY (https://opstest.mghaiapps.com,
folder `opstest.mghaiapps.com/exaact_test/app` + `exaact_data_test`) before the
LIVE site (https://operations.mghaiapps.com). Both run the SAME code from the
working branch; only each site's own `config.local.php` decides test mode.

- **When the owner says testing is complete, ASK them first:** "Shall I prepare
  the live update?" Do not upload, merge or package for live without that yes.
- **The update package (zip) for either site must NEVER contain:**
  `config.local.php`, `tenants.php`, the `tests/` folder, any `*.sqlite`, or the
  `exaact_data*` / `exaact_backups*` folders. Leaving them out is what keeps the
  test copy in test mode and the live site live — and stops a site losing its
  settings or data. Never tell the owner to copy files from the opstest folder
  to the live folder.
- **Give the owner the live checklist** (`docs/go-live-guide.md`, section
  "Updating the live site after testing"): back up first, upload and extract
  the zip in the live app folder only, leave those files alone, open
  `/deploy-check.php`, confirm no red TEST COPY bar, switch to live Razorpay
  keys, one small real payment and refund. If anything is wrong, restore the
  backup.
