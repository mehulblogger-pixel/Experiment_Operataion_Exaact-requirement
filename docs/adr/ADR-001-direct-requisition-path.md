# ADR-001 — Should the direct requisition path remain available?

- **Status:** OPEN — to be decided in **Phase 3**.
- **Raised by:** Phase 2 · M4 correction.
- **Decision owner:** the business owner (this is a product policy decision, not
  a technical one).
- **Nothing in this ADR is implemented.** M4 changed no behaviour here.

## Context

EXAACT has two supported routes into recruitment (see
`docs/phase2/M4-REQUISITION-PATHS.md`):

- **Path A — Governed:** Hiring Request → Approval → Recruitment Requisition.
- **Path B — Legacy / Direct:** a Recruitment Requisition created straight away,
  already `OPEN`, with `hiring_request_id = NULL`.

Path B pre-dates M4, is how every existing requisition in every existing
database was created, and is the natural route for a manpower-services business
whose authorisation is the client's own order. Path A is what an employer
hiring into its own establishment needs, because the approval *is* the control.

Leaving both permanently open means a customer who adopts Path A for governance
can still bypass it by using Path B. Closing Path B outright would break
existing customers and an entire market segment.

## Options

### (a) Path B always permitted
Both routes stay open for everyone, forever.
*For:* nothing breaks; no configuration; suits manpower-services customers.
*Against:* a customer who adopts approval governance has no way to enforce it.

### (b) Path B disabled for selected customers
Switched off per customer by the platform, not self-service.
*For:* governance-minded customers get a real control; no configuration surface
for anyone who does not need one.
*Against:* support overhead; the customer cannot change their mind unaided; a
hidden behaviour difference between installations.

### (c) Path B governed by a tenant / workspace policy setting
A customer-owned setting — e.g. *"Recruitment may only start from an approved
hiring request"* — off by default.
*For:* the customer owns their own governance; default preserves today's
behaviour exactly; visible and auditable; matches how every other EXAACT policy
works.
*Against:* one more setting; needs a clear screen, a sensible default and an
answer for records created before it was switched on.

### (d) Align Path B's permission with Path A's *(may be combined with any of the above)*
Path B's create gate is still the role band `is_coordinator_level()`; Path A's is
now the capability `mod.hiring.edit`. Aligning them makes one capability govern
both routes.
*For:* one rule, in one vocabulary; matches the permission matrix, which grants
Asst. Manager no hiring right at all. *Against:* it narrows an existing gate, so
it needs its own regression pass and a note to any customer who has hand-edited
role permissions.

## Recommendation (to be confirmed in Phase 3)

**(c) plus (d).** A workspace-level policy, default OFF so nothing changes for
anyone on upgrade, and one capability governing both routes. This keeps the
product honest for both kinds of customer: a manpower business is untouched, and
an employer that wants approval enforced can switch it on and have it mean
something.

## Consequences to work through when it is decided

1. What happens to requisitions created **before** the policy was switched on —
   they stay valid and are not retro-fitted with an invented request (M4 §39).
2. What the direct route says to a user when the policy refuses it, and what it
   offers them instead (raise a hiring request).
3. Whether project costing → requisition and the client-order route count as
   "direct" for the policy, or as separately authorised.
4. Whether Phase 3's approval matrix should be the thing that decides, rather
   than a boolean.
