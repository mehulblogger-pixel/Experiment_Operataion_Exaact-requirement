# Milestone 14 — Known Limitations

Nothing here is a demonstrated critical or high-risk vulnerability. Each item is
a classified, understood surface.

---

## L1 — Coverage is high-value-first, not exhaustive

**Severity: Medium — measured and bounded.**

1,290 by-id statements exist across 189 tables. M14 classified all of them by
scope dimension and **attacked the branch-scoped registers that have a
user-addressable detail route** — the commercial and operational core:
quotations, opportunities, leads, requisitions, complaints, receipts, calls,
jobs, invoices, vouchers, CAPA, candidates, project costings, plus documents and
exports hanging off them.

Not individually attacked: the long tail of child records reached only through
an already-guarded parent screen (line items, allocations, events, log rows),
and registers whose lists carry no scope clause at all (classified global, L2).

This is stated as coverage, not as a clean bill of health. **Objects not tested
are not described as secure.**

---

## L2 — Registers that are branch-global by their own list

**Severity: Medium as a design question, not as a defect.**

`project_costings`, `internal_audits`, `risk_items` and `sample_items` carry an
`office_id` column, but **their lists never filter on it** — `pc_all()`, for
example, has no scope clause whatsoever. So the detail matching the list is
correct behaviour, and gating it would hide records the application intends to
show. §19 and §30 forbid changing that on suspicion.

They are protected at module level (`pc_can()` and equivalents), not at branch
level. Whether project costings *should* be branch-scoped is a **product
decision**, not a security finding, and it is put to architectural review rather
than settled unilaterally inside a security milestone. Commercial rates and
margins are the sensitive content, which is why it is raised rather than merely
noted.

---

## L3 — Candidates are tenant-wide by design

**Severity: Informational.**

A candidate record carries `sbu` but no branch, and the candidates list shows
the same records to the same user that the detail does. There is no list/detail
disagreement, so there is nothing to fix. Recorded because it *looked* like a
finding during the sweep and was ruled out on evidence.

---

## L4 — A master crosses branch scope

**Severity: Informational — intended and documented.**

`scope_allows()` and `scope_office_allows()` both return true for a master, by
architecture: a master has ALL office scope. This is not the master-bypass class
M10 closed — that was about *entitlement*, and it still holds. A master is also
still bound to a single tenant by M13's workspace binding, so this is a
within-tenant privilege, not a cross-tenant one.

---

## L5 — MySQL / MariaDB was not exercised

**Severity: Informational.**

No MySQL server exists in this environment (no binary, port 3306 closed). **No
MySQL result is claimed.** The M14 changes add two `SELECT … WHERE id = ?`
lookups and comparison logic in PHP — no engine-specific SQL — but that is
reasoning, not a test result.

---

## L6 — Source-level and in-process, not a live penetration test

**Severity: Informational.**

Attacks ran against the real application booted in-process, with real routes,
sessions and databases — not simulated. But there was no live HTTP target, so
nothing was tested at the transport layer (TLS, security headers, cookie flags
as set by the production web server). Those belong to the mPanel deployment.
