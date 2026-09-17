# PHASE 3 · M6 — NEGATIVE SECURITY MATRIX

Every row was **executed**, not reasoned about. Each calls the production
function the route calls, with the payload a crafted request would carry.

## A · Entitlement (§28)

| Condition | Expected | Actual |
|---|---|---|
| module licensed | ALLOW | allowed |
| module not licensed | DENY | `can()` returns false — it asks `licence_blocks()` **before** the master flag, so there is no master bypass anywhere in the system |
| module **unknown / missing** | DENY | an invented module right (`mod.notarealmodule.view`) returns false |
| every recruitment route | inside the licensed module | `candidate-*`, `requisition-*`, `hiring-request*`, `recruitment*` are mapped, and `ops_module_family()` catches an unmapped member of the family |

## B · Tenant isolation (§29) — with a real second database

| Attempt with tenant A's id, as tenant B | Expected | Actual |
|---|---|---|
| read the hiring request | DENY | `null` |
| read the requisition | DENY | 0 rows |
| read the candidate | DENY | 0 rows |
| **execute** against the requisition | DENY | the gate refuses — *"That requirement no longer exists."* |
| write ownership to it | DENY | `NO_RECORD` |
| edit the hiring request | DENY | refused |
| **and afterwards, in workspace A** | untouched | request, title and recruiter all intact |

Isolation is **structural** — one database per tenant. The probe switches to a
real second database and back; it does not change a variable.

## C · Branch isolation (§30)

| Branch-B user against branch-A work | Expected | Actual |
|---|---|---|
| see the hiring request | DENY | out of scope |
| edit it | DENY | refused |
| decide it | DENY | refused |
| raise a requisition from it | DENY | refused |
| take ownership of its requisition | DENY | `OUT_OF_SCOPE` |
| see its workload | DENY | 0 |
| see its recruiter on the dashboard | DENY | absent |

## D · Role matrix (§31)

| Least-privilege role (`INSPECTOR`, a real role) | Expected | Actual |
|---|---|---|
| raise a hiring request | DENY | `hreq_can_create()` false, and the write refuses |
| decide one | DENY | `hreq_can_decide()` false |
| change recruiter accountability | DENY | `NO_PERMISSION` |
| reach the recruitment write band | DENY | `is_coordinator_level()` false |

## E · Authorization order (§32)

| Attempt | Expected | Actual |
|---|---|---|
| unauthorised caller, **wrong** baseline | a refusal that reveals nothing | `NO_PERMISSION` |
| unauthorised caller, **right** baseline | the same refusal | `NO_PERMISSION` |
| unauthorised caller naming the **current owner** | the same refusal | `NO_PERMISSION` — not "no change" |
| unauthorised caller, an id that does not exist | no materially different answer | `NO_PERMISSION` / `NO_RECORD` |

Authorization is answered **before** the stale answer and before the no-change
answer, because both of those are statements about the current owner.

## F · Input type / normalisation (§14, §39)

| Value posted into an identity field | Expected | Actual |
|---|---|---|
| array `['x']` | REFUSE | `BAD_VALUE` — **not** the integer 1 |
| nested array | REFUSE | `BAD_VALUE` |
| `"abc"` | REFUSE | `BAD_VALUE` — **not** 0, which would read as "unassign" |
| `"7x"` | REFUSE | `BAD_VALUE` |
| `-5` | REFUSE | `BAD_VALUE` |
| `"1.9"` | REFUSE | `BAD_VALUE` |
| `"1e3"` | REFUSE | `BAD_VALUE` |
| whitespace | REFUSE | `BAD_VALUE` |
| `"007"` | resolve to 7 | resolves to 7 |
| `""` / null | unassign | unassigns — the legitimate empty choice |
| a very long numeric string | not a live person | refused / no such person |
| a requirement id that is an array or a word | must not resolve to a live requirement | does not |

After ten malformed attempts in a row the owner is unchanged.

## G · Stale state / TOCTOU (§33)

| Attempt from an old screen | Expected | Actual |
|---|---|---|
| offer, after a material change landed | DENY | gate refuses; no offer written |
| ownership save, after the owner moved | DENY | `STALE`; the newer owner stands |
| ownership save with **no** baseline | DENY | `STALE` — no baseline, no overwrite |
| a POST that omits the field | change nothing | unchanged |
| recruiter assignment on a requisition M4 blocked while the screen was open | DENY | `M4_BLOCKED` |
