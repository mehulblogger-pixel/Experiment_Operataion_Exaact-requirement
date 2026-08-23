# CLAUDE.md

Guidance for any session working in this repository.

## The application

`phpapp/` is an inspection & operations management system for a third-party
inspection company. See `docs/00-README.md` for what it does, the words it uses, and
how the pieces fit together.

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

## Known gaps

`docs/99-gaps-and-risks.md` lists twenty findings, ranked by risk, each with a
recommended fix. Read it before assuming a permission behaves the way its name
suggests — several do not. In particular, `ops.job.allocate` and `ops.job.close` are
not enforced on the routes they name, and the central route gate checks only
`mod.<module>.view`, never `.edit`.

`phpapp/PENDING.md` records what is deliberately unfinished. Items there are
intentional — do not "fix" them without asking.
