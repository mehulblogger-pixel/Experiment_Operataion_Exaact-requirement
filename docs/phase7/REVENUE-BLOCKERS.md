# EXAACT — Revenue Blockers

**Three release blockers. All small. All in existing engines.**

Baseline `3f5bb75` · 2026-09-20 · nothing implemented in this action.

---

## RB-1 · A hired person may never exist in Workforce

**What happens.** A recruiter moves a candidate to ACCEPTED. A checkbox —
*"On Accept, also add this person to Inspectors"* — is `display:none` until
JavaScript reveals it. If it is not ticked, the person is hired in Recruitment
and **does not exist in Workforce**. No error, no warning, no report.

**Evidence.** `views/ops/candidate_detail.php:379` · `lib/ops.php:5775`.

**Business consequence.** The person cannot be deployed, rostered, given
attendance, or included in utilisation. It is discovered weeks later, by absence
— typically when somebody asks why a job has nobody on it, or at payroll.

**Customer consequence.** "We hired them. Your system lost them."

**Revenue consequence.** Directly undermines the core claim that Recruitment and
Operations are one system.

**Security / data consequence.** None. No corruption — an omission.

**Recommended action.** **CONNECT.** The conversion engine (`rcv_convert()`) is
sound — Batch 2 made it atomic, branch-correct and ledger-writing. Only *whether
it runs* is broken. Shape depends on **Q32**.

**Priority: 1.**

## RB-2 · A requirement reports "filled" when nobody has joined

**What happens.** `requisitions.status` is derived from `filled` (candidates in an
ACCEPTED stage). The engine also computes `joined` (those with a workforce
record) — and never uses it for status.

**Evidence.** `lib/reqfulfil.php:107–110` (both computed), `:120` (`remaining =
requested − filled − cancelled`), `reqf_derive_status()`.

**Business consequence.** A manager reads "10 of 10 filled" and stops recruiting.
Nobody has actually joined. Hiring stalls silently and is discovered at the start
date.

**Customer consequence.** Headcount reporting overstates delivery — the number a
customer is most likely to quote to *their* client.

**Revenue consequence.** For a manpower business this is the number being sold.

**Security / data consequence.** None — misleading, not corrupt.

**Recommended action.** **EXTEND.** Both numbers already exist. No new field and
no new status value is required. Shape depends on **Q32**.

**Priority: 2.**

## RB-3 · Duplicate inspectors are freely creatable and nothing reports them

**What happens.** `inspectors` has no unique index, no generated key and no index
at all. Three rows with identical name, e-mail **and employee code** were created
in a probe without objection. Nothing anywhere reports it.

**Evidence.** `lib/indexes.php` (no `inspectors` entry) · probe
`scratchpad/p7/r20.php` · picker `lib/ops.php:4521` (no de-duplication) ·
`grep INSPECTOR.*DUP lib/connect_identity.php` → none.

**Business consequence.** One human held as two rows splits deployment choice,
attendance, inspection history and per-person utilisation. A coordinator sees two
identical names and cannot tell which to deploy.

**Customer consequence.** Utilisation — what a TPIA business sells — is quietly
wrong, and there is no way to notice.

**Revenue consequence.** Undermines the central reporting claim.

**Security / data consequence.** **Invoice totals are NOT affected** — each job
is billed once. This is misleading per-person status, not incorrect money. Said
plainly so it is not over-stated.

**Recommended action.** **EXTEND `identity_state_findings()`** — detection only,
non-destructive, the cheap half. Prevention (a constraint over existing data) is
**UB-1**, and needs the Batch 3 guard pattern plus a reconciliation decision.

**Priority: 3.**

---

## What is deliberately NOT on this list

Listed so the classification is not read as timid or as padded.

| Not a blocker | Why |
|---|---|
| Five competing start screens | Confusing, not blocking. A customer can complete every workflow |
| Unwrapped tables on mobile | Real, and it matters for field inspectors — but a phone user can still scroll |
| No browser smoke layer | Blocks *us* proving safety, not the customer using the product |
| `BLACKLISTED` enforces nothing | A controlled limitation, recorded and accepted |
| Person Hub / identity convergence | Deferred by programme decision; no customer impact today |
| Taxonomy governance (R23) | Does not prevent setup, recruitment, assignment or reporting |
| Q1/Q2 organisation uniqueness | A narrow simultaneous-registration race; the public route already refuses duplicates |
