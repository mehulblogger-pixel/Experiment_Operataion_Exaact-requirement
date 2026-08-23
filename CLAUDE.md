# CLAUDE.md

Guidance for any AI/coding session working on this repository.

## Roles and flows — read before building anything

Before implementing any feature, read `docs/01-roles.md`,
`docs/02-permission-matrix.md` and the relevant `docs/04-flows/<role>.md`.

Hard rules:

- Never grant a role a permission that is not in `docs/02-permission-matrix.md`.
  If a feature needs a new permission, stop and ask.
- Never add a status or transition that is not in `docs/03-object-lifecycles.md`
  without asking.
- When a change alters who can do what, update `docs/` in the same commit as the
  code. The docs and the code must never disagree.
- Inspectors are phone-first in the field. Coordinators, managers and finance are
  desk-first on a laptop. Design for both; never average them into one middle.
