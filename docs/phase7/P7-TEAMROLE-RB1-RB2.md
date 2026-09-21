# Phase 7 · team_role · RB-1 · RB-2 — implementation report

Built on the owner's three decisions of this cycle:

1. `requisitions.team_role` — approved as a nullable column.
2. RB-2 — `candidates.joined_at` plus an explicit "Mark as joined" action.
3. §4 applies to `make_inspector` only; `dup_ack` stays.

---

## A · Decisions implemented

| | What changed |
|---|---|
| **team_role** | Decided on the requirement, confirmed at acceptance, and **never** allowed to reach the FIELD default that `inspectors.team_role` carries. An undecided hire is refused with an actionable sentence. |
| **capability** | The existing catalogue answers whether the Inspector concept exists at all. Three honest states: **YES**, **NO**, **UNCONFIGURED** — and silence is never read as yes. |
| **RB-1** | Accepting somebody **is** hiring them. Every accepted candidate gets a workforce record. The hidden checkbox is gone from the route and the screen; `dup_ack` is untouched. |
| **RB-2** | `joined` now counts people somebody said actually joined. On existing data that is zero, which is the truth. |

## B · Files changed

| File | Purpose |
|---|---|
| `lib/recruit.php` | `WF_TEAM_ROLES`, `wf_team_role_normalise/resolve`, `wf_ops_capability`, `wf_inspector_applies`; gate 6a in `rcv_convert`; the role written on the workforce insert; `cockpit_migrate` added to the pre-warm list |
| `lib/ops.php` | `requisitions.team_role` and `candidates.joined_at` migrations; `team_role` in the requisition save list and read from the acceptance POST; RB-1 (`$wantHire`); the `candidate-joined` route |
| `lib/reqfulfil.php` | `joined` counts real joinings |
| `views/ops/requisition_form.php` | the team decision, where it is made |
| `views/ops/candidate_detail.php` | checkbox removed; team confirmation added; "Mark as joined" panel |
| `tests/test_p7_teamrole_rb1_rb2.php` | the new battery (57 assertions) |
| `tests/test_rb3_step3_atomic.php` | **T10 re-aimed** — see below |
| `tests/test_p4_routes.php`, `test_p5_kpi.php`, six RB-3/P6 test files | acceptances now state which team, exactly as the screen does |

## C · Database changes

Two nullable columns, no defaults, no data rewritten:

```
requisitions.team_role  VARCHAR(10) NULL
candidates.joined_at    VARCHAR(30) NULL
```

Both are deliberately without a default: "not decided" and "we do not know" must
stay distinguishable from a decision, or the question can never be asked again.

## D · Tests

`test_p7_teamrole_rb1_rb2.php` — **57 passed · 0 failed** on both engines.

- **TR** — nobody decided → refused, nothing created, and *no FIELD team member
  appeared anywhere*; the requirement's decision is what gets written for each of
  FIELD / COORD / OFFICE; the confirmation at acceptance overrides it; an
  unrecognised role is ignored rather than coerced.
- **CAP** — all three workspace states **constructed and asserted in turn**, then
  restored. Unconfigured does not become "yes". A recruitment-only workspace
  records OFFICE explicitly and invents no Inspector.
- **CAPTX** — asking the capability question inside a transaction leaves the
  transaction open (see the defect below).
- **RB1** — a workforce record appears although nothing asked for one; the route
  no longer reads a checkbox; the screen no longer offers one; `dup_ack` is still
  there.
- **RB2** — two hired, **joined = 0**; one joining recorded → 1 joined, 2 hired;
  PARTIALLY_FILLED behaviour unchanged; clearing the joining reverses it and
  leaves the hire intact.

## E · Mutation — 11 targets, 11 caught

| | Mutant | Result |
|---|---|---|
| Q1 | the resolver falls back to FIELD when nobody decided | **CAUGHT** |
| Q2 | acceptance no longer refuses an undecided hire | **CAUGHT** |
| Q3 | the confirmation at acceptance is ignored | **CAUGHT** |
| Q4 | the requirement's own decision is ignored | **CAUGHT** |
| Q5 | the resolved role is discarded and FIELD written | **CAUGHT** |
| Q6 | any string is accepted as a team role | **CAUGHT** |
| Q7 | an unconfigured workspace is treated as configured | **CAUGHT** |
| Q8 | Inspector applicability stops depending on the workspace | **CAUGHT** |
| Q9 | the hidden checkbox is put back in charge | **CAUGHT** |
| Q10 | the old false "joined" count comes back | **CAUGHT** |
| Q11 | a requirement counts only joined people as filled | **CAUGHT** |

**CAUGHT 11 · SURVIVED 0 · FATAL 0 · ANCHOR-MISS 0.**

**Q7 survived the first run**, and the reason mattered: the CAP tests *branched*
on whatever state the workspace happened to be in, so a mutation that changed
that state simply sent the test down its other branch and it passed either way.
The test adapted to the code instead of pinning it. It now constructs each of the
three states.

## F · Full regression

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **13,167 passed · 0 failed** |
| **MariaDB 10.11.14** | **13,171 passed · 0 failed** |

Run sequentially. Every protected module PASS on both:
Operations 563 · Reporting 653 · Money/Billing 641 · Workforce 249 ·
Marketplace 1317 · Recruitment 5172/5175 · Tenant isolation 298 · Entitlement 2039.

## G · Defects found while building this

### G1 · asking what the workspace does ENDED the caller's transaction

`wf_ops_capability()` consults the capability catalogue, and that catalogue
installs its own table on first use. **On MariaDB any DDL commits the open
transaction implicitly.** Asking the question from inside a caller's transaction
therefore committed that caller's half-written work and left it with nothing to
roll back — the existing Batch 2 probe A17/A18 caught it, and the run died on a
`rollBack()` with no transaction to undo.

Inside a transaction the question is now answered by **reading**, never by
migrating, and `cockpit_migrate` joins the list warmed *before* acceptance opens
its transaction. `CAPTX` pins both halves.

This only appeared on MariaDB. SQLite's DDL is transactional, so the hazard does
not exist there — the authoritative engine earned its title again.

### G2 · T10 was asserting something that had stopped being true

T10 existed because accepting somebody *without* creating a workforce record was
the one path the in-transaction seat check guarded alone. **RB-1 deleted that
path**, and T10b — "the loser asked for no workforce record, so rcv_convert never
ran to guard them" — went on passing only because the loser had been refused.
A true-by-accident assertion.

T10 now asserts the new truth, and **T10d** proves RB-1 directly: a POST that asks
for no workforce record produces one anyway, with an employee number.

A consequence recorded honestly: with that path gone, the route's own seat check
is doubled everywhere, so removing it is now an **equivalent** mutation rather
than a detectable one. Section E of the Step 3 battery already proves the
doubling, so the claim is tested rather than asserted.

### G3 · a tautology I wrote and removed

A first draft of T10 contained `$x === 0 || $x > 0` — always true. Deleted rather
than shipped: an assertion that cannot fail is worse than no assertion, because
it looks like coverage.

## H · Data safety

**No historical data was changed.** No inspector deleted, no person merged, no
employee number rewritten, no existing `team_role` reclassified, no recruitment
state rewritten. The two new columns are NULL on every existing row, which is the
honest value: those requirements really did not record a decision, and nobody
really did record a joining.

Existing accepted candidates keep whatever `team_role` their workforce record
already has. Nothing was back-filled.

## I · What this changes for users

A recruiter accepting somebody is now asked **one** extra question — which team
they join — pre-filled from the requirement when it was set there. In a
recruitment-only workspace they are not asked at all.

An acceptance can now be refused for a new reason: nobody has decided which team
the person joins. The message says exactly that and what to do about it, and the
candidate stays where they were.

## J · Browser / HTTP (§18) — what was and was not verified

**Verified over real HTTP** against a throwaway workspace served by `php -S`:
the application boots, `GET /login` renders (200), signing in as the
administrator succeeds and establishes a session, and the authenticated landing
page renders.

**Not completed over TCP:** the acceptance walk itself. With the workspace
configured (capabilities set to TPIA, Operations on — `wf_ops_capability()`
returns `YES`), the ops screens still answer `302 → login` for this session
while `/` correctly resolves to the signed-in landing page. That behaviour
**predates this cycle** — it was observed before any of the team_role, RB-1 or
RB-2 work — so it is a property of this headless harness's session handling, not
of the changes reported here. The project's own browser harness
(`phpapp/tools/auto-walk.sh`, Playwright) is the right instrument for that layer;
no new harness was built for it.

**What covers the route instead.** The route handler is exercised by real
separate processes that call `ops_dispatch('candidate-stage', 'POST')` — the same
entry point a web request reaches — in `_rb3s3_worker.php`, `_p4_worker.php` and
`_p6b2_worker.php`. That is where **T10d** proves RB-1 through the route (a POST
that asks for no workforce record produces one anyway, with an employee number),
and where the concurrency proofs run. The gap is the TCP layer, not the route.
