# ADR-001 — Should the direct requisition path remain available?

- **Status:** **DECIDED — 26 Sep 2026.** Option **(c)**: a workspace policy,
  `requisition_requires_request`, **default ON (enforced)**. Option (d) —
  aligning the direct route's role band with the governed route's capability —
  remains **open**; it narrows an existing gate and needs its own regression
  pass, so it was deliberately not bundled in.
- **Raised by:** Phase 2 · M4 correction.
- **Decision owner:** the business owner (this is a product policy decision, not
  a technical one).
- **Implemented.** See the decision at the end of this file.

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


---

## Decision (26 Sep 2026)

**"Recruitment may only start from an approved hiring request."** The owner chose
to close the direct path, implemented as option (c) — a workspace policy, not a
hard-coded block — because the right answer genuinely differs per customer and
one database per tenant means each can hold its own.

**Default: ON (enforced).** This differs from the recommendation above, which
suggested defaulting OFF to preserve existing behaviour on upgrade. The owner's
instruction was explicit, and the refusal is not a dead end: it names the
alternative and an administrator can switch the policy off on
**Admin → System settings** in one click. A workspace whose authorisation is its
client's order — the manpower-services case this ADR was written to protect —
turns it off and is exactly as it was.

### What was built

| | |
|---|---|
| Policy | `hreq_direct_path_allowed()` / `hreq_direct_path_block_reason()` in `hiringreq.php` — one definition |
| Setting | `requisition_requires_request`, default `'1'`, on Admin → System settings |
| Gated | The direct requisition form (at the INSERT) **and** project costing → requirement |
| Never gated | `hreq_to_requisition()` — the governed route is the only door left and must stay open |
| Untouched | Every requisition that already exists |

### The four consequences this ADR said to work through

1. **Requisitions created before the policy** — they stay valid, editable,
   recruitable and closeable. Nothing is retro-fitted with an invented approval
   and nothing is stranded. Verified live: an existing direct requisition opens
   and edits normally with the policy on.
2. **What the refusal says** — it names the approval, names the *Start
   recruiting* button that does the job, and says an administrator can change the
   policy in settings. Shown before the wizard, not after five steps of it.
3. **Does project costing count as "direct"?** **Yes.** The route's own comment
   already called it the direct path, and a costing is a *commercial* estimate
   carrying no headcount approval. Leaving it open would have left the policy
   with a side door: anyone refused on the form could raise the same requirement
   from a costing.
4. **Should the approval matrix decide rather than a boolean?** Not yet. The
   matrix decides *who approves a request*; this policy decides *whether
   recruitment may begin without one*. They are different questions and the
   boolean is the honest shape of this one.

### Evidence

`tests/test_adr001_direct_path.php` — 30 assertions covering enforcement, the
reversible switch, every creation door, and (the one that matters most) that an
existing requisition stays workable. Each guard was mutation-tested: flipping the
default, unguarding the form, and opening the costing side door each make the
suite fail. Full regression 14,207 passed / 0 failed on MariaDB, 14,205 on SQLite.
