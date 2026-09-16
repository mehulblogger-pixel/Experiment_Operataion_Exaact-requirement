# Phase 3 · M3 CORRECTION #4 — COMPLETION REPORT
## Notification Eligibility — Entitlement-First & Fail-Closed Subject

## E1 — root cause

`appr_told_reason()` asked the licence question **inside** the hiring-request
branch, so an offer, a salary structure and a requisition reached **eligible**
having never been asked for an entitlement. Proved: with **People & hiring
switched off**, an offer decision still produced a send to its raiser, naming the
offer, in `email_log`.

**The actionable path had the same hole** — `appr_may_be_asked()` →
`appr_visible()` → `appr_guard()`, which also returns `''` for non-hiring
entities. It was masked only because `appr_tick()` carries its own licence gate.

The shape is mine from correction #1, but it was **dormant** — correction #2 had
stopped offer notifications entirely. **Correction #3 restored them and activated
it.**

## E1 — fix

Not four duplicated checks. One **common gate** before any entity-specific
question can return:

```
appr_notify_gate($req, $user)
    1 · valid security subject
    2 · known, supported entity
    3 · applicable module entitlement   (APPR_ENTITY_MODULE)
        ↓  entity-specific visibility and scope — which can only NARROW
```

Both readers pass through it — `appr_told_reason()` (informational) and
`appr_may_be_asked()` (actionable) — so the second hole is closed with the first.
`APPR_ENTITY_MODULE` records which module each approval entity belongs to, so a
future entity cannot arrive without one.

**`appr_guard()` was not touched**: it governs *decisions* and belongs to M1/M2.
The gate sits at the *notification* predicate, so decision semantics are unchanged.

### Achieved security order

```
1 valid subject → 2 tenant/security context → 3 entitlement → 4 entity resolution
→ 5 entity visibility/scope → 6 active user / delegation validity → 7 segregation
→ 8 eligibility
```

No entity-specific early return can skip 1–3.

## E1 — all-four-entity results

| | HIRING_REQUEST | OFFER | SALARY | REQUISITION |
|---|---|---|---|---|
| entitled workspace | notified | notified | notified | notified |
| **unlicensed workspace** | **denied** | **denied** | **denied** | **denied** |

Asserted against actual recipients and `email_log`, not only the reason code, and
on both the informational and actionable paths — including the delegate path.

## E2 — root cause

`appr_as_user()` returns `null` for an unusable id; `(string) null` is `''`; and
`''` was this predicate's word for **eligible**. A type conversion was deciding a
security question. Its sibling cast the same `null` with `(bool)` and failed
**closed** — the two readers of one rule disagreed **by accident of a cast**.

## E2 — fix and subject matrix

The subject is tested **explicitly**, and no cast encodes policy: `is_string($r) ?
$r : 'IDENTITY_UNRESOLVED'` on the informational path, `=== true` on the
actionable one.

| Subject | Result |
|---|---|
| valid id | normal behaviour |
| `null` | denied |
| `0` | denied |
| negative | denied |
| missing `id` key | denied |
| not an array | denied |
| non-existent user | denied (`TENANT_MISMATCH` at resolution) |
| inactive | denied (`RECIPIENT_INACTIVE`) |
| cross-tenant | denied |
| no entitlement | denied (`RECIPIENT_UNLICENSED`) |
| out of scope | denied (`RECIPIENT_OUT_OF_SCOPE`, hiring request) |
| segregation conflict | denied on the actionable path |

Run for **all four entities**.

## Regression — C1 / C2 / D1 / D2 / D3 / F1 / F2 / F3

| Suite | Result | Covers |
|---|---|---|
| `p3m3c4_gate` | **129 / 0** | E1, E2, the matrix |
| `p3m3c3_raiser` | **53 / 0** | D1, D2, D3 |
| `p3m3c2_identity` | **50 / 0** | C1, C2 |
| `p3m3c_notify` | **68 / 0** | F1, F2, F3 |
| `p3m3_sla` | 176 / 0 | SLA, escalation, inbox |
| `p3m1_approval` · `p3m2_matrix` | 114 / 0 · 111 / 0 | M1, M2 + M2 correction |
| `recruit_approval` · `offer_appr_dept` | 25 / 0 · 2 / 0 | offer / salary / requisition approval |
| `m4_hiring_request` · `m4_correction` · `hiring_admin` | 78 / 0 · 107 / 0 · 11 / 0 | |

No display-name lookup returned; `appr_can_act()` still does not rescue a missing
entity; canonical raiser identity still works for offer, salary and requisition;
no repetitive audit noise; no dangling `ACT_ENTITIES`; reasons still distinct.

## SQLite · MariaDB · full regression

| | |
|---|---|
| **SQLite** | **9518 passed, 0 failed** |
| **MariaDB 10.11.14** (fresh `exaact_m3h`) | **9519 passed, 0 failed** |

Operations, Reporting, Quality, Money, Workforce, Marketplace and the Command
Centre are inside those figures. Run **serially**. **Nothing skipped, weakened or
re-baselined**, and no existing suite needed altering — the fix made three
entities stricter and nothing that passed depended on the hole.

## Mutation result

**35 caught · 5 survived, every one paired and proved · 9 anchors superseded by
earlier rewrites, every intent re-run against current code.** Q07+Q08 caught
together (9 failures); M11 + the gate caught together (8); `appr_can_act()` alone
on the actionable path caught (9); `RECIPIENT_INACTIVE` collapsed caught (5).
Q09 and Q10 are reported as **defensive lines, not protections** — the gate makes
them unreachable today.

## Known limitations

1. **Q09/Q10 are unreachable defence.** Kept deliberately, labelled honestly.
2. **Historical offers, salary structures, requisitions and chains carry no
   identity and notify nobody** — fail closed by design.
3. **Requisitions have no `created_by_id`** — identity comes from the chain.
4. **`OFFER` and `SALARY` notification outcomes are not on the activity timeline**
   — they are not `ACT_ENTITIES`, and a dangling reference was refused.
5. **`appr_visible($step, $req, $user)` still only half-honours its `$user`** — it
   passes it to `appr_can_act()` and evaluates the guard for the session user. Not
   a defect: every caller asking about somebody else goes through
   `appr_may_be_asked()`, which impersonates. Recorded, not silently redesigned.
6. Escalation does not transfer authority · no mandatory-approval switch · no
   per-tenant time zone · parallel approval unsupported · four rule conditions
   unimplemented.

## Deferred F4 / F5

**F4** — the Recruitment Command Centre remains company-wide by design.
**F5** — unbounded scans deferred; measured at 4 ms for 15 candidates.

## Manual / UAT evidence

**None.** This correction changed no screen. The M3 screens still have not been
exercised by a human in a browser, and Phase 1 UAT on MilesWeb production remains
open.

## Commit · working tree

See the final message. Mutations ran against copies outside the repository, so the
working tree is clean.
