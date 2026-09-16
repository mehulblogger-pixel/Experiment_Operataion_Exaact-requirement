# Phase 3 · M3 CORRECTION #2 — COMPLETION REPORT
## Requester Identity & Fail-Closed Entity Resolution

## C1 — root cause

`appr_email_requester()` resolved its recipient from
`recruit_approval_requests.requester`, which is **prose** — a first/last-name
string written by `_appr_actor()` — using `LIMIT 1` with no ordering and no
tenant, branch or permission question. Whoever held the lower id won.

Proved: an `INSPECTOR` at another branch **holding no recruitment permission at
all** received a hiring request's title and outcome; the person who raised it
received nothing.

## C1 — fix, and the canonical identity source

**`hiring_requests.requested_by_id`** — established by M4 as the requestor of
record and made to survive an omitting edit by M4's own correction. It is the same
id segregation already uses and `appr_waiting_on_others()` already matches on.

```
approval request → entity_id → hiring_requests.requested_by_id → users.id
```

No second identity mechanism was introduced. `requester` returns to being what it
always was: a label for a screen.

## C1 — affected callers

Two, both inside `appr_act()` (the reject path and the final-approve path), and
**both already pass `$req`, which carries `entity` and `entity_id`** — everything
the canonical lookup needs. **No signature change was necessary, so none was
made.**

## C1 — legacy requester handling

`appr_requester_user()` returns **null**, and nothing is sent, when the entity is
not a hiring request, the record cannot be fetched, `requested_by_id` is absent,
or the id does not exist in this database. Each case is written to the **existing**
activity spine — *"Decision not notified — no canonical requester identity"* — so
the silence is visible.

**Offers, salary structures and requisitions record their raiser as text and
nothing else, so they now notify nobody on a decision.** That is a deliberate
behaviour change, mandated by the brief's *do not guess* rule, and is listed as a
limitation below.

## C1 — security

Identity settles **who**; the existing model settles **whether**.
`appr_may_be_told()` — entitlement, the record existing, branch scope, active
status — at the **informational** level, because telling somebody the outcome of
their **own** request is not asking them to approve anything, so segregation must
not silence it. Knowing `requested_by_id` authorizes nothing by itself, and N05
proves it.

## C2 — root cause and fail-closed behaviour

With `$req` null, `$entity` came out `''`, `appr_visible()` read that as *"not a
hiring request"* and returned **true** — so a missing record skipped entitlement,
branch scope and segregation and notified everyone holding the role. **A missing
security subject made the answer looser.**

Now three states are distinguished against the same `APPR_ENTITIES` map the engine
already uses:

| | |
|---|---|
| known hiring request | full guard |
| known supported entity (offer, salary, requisition) | **unchanged** — can-act alone |
| unknown, blank or unresolvable | **deny** |

A hiring request whose record is missing is denied by `appr_guard()`'s own
*"no longer exists"* branch, which the fix now lets the code reach. **No redundant
second check was added** — this milestone has already had two mutations survive on
redundancy.

## Files changed · database · migration

`lib/recruit_approval.php` · `tests/test_p3m3c2_identity.php` (new) ·
`deploy-check.php` · four documents in `docs/phase3/`.

**No database change. No migration. No new column, no new table.**

## Tests · mutations

New suite **47 assertions, 0 failed** on both engines, covering C1-1…C1-10 and
C2-1…C2-10, plus `appr_as_user()` re-tested as regression only (§6 — not
redesigned).

**22 mutations · 20 CAUGHT · 2 survived as a proved mutual redundancy.** N01 and
N06 restore the two audited defects verbatim. A harness defect was found and
corrected in the process, and is reported.

## SQLite · MariaDB · full regression

| | |
|---|---|
| **SQLite** | **9333 passed, 0 failed** |
| **MariaDB 10.11.14** (fresh `exaact_m3f`) | **9334 passed, 0 failed** |

M3 correction #2 47/0 · M3 correction 68/0 (the whole F1/F2/F3 matrix) · M3
original 176/0 · M1 114/0 · M2 111/0 · Phase-6 approvals 25/0 · offer context 2/0 ·
M4 hiring request 78/0 · M4 correction 107/0 · recruitment admin 11/0. Operations,
Reporting, Quality, Money, Workforce, Marketplace and the Command Centre are inside
the whole-suite figures. Run **serially**. **Nothing skipped, weakened or
re-baselined.**

## Limitations

1. **A decision on an offer, salary structure or requisition now notifies nobody.**
   Those entities have no canonical raiser id, and the brief forbids guessing. Giving
   them one is a schema change outside this correction.
2. **`appr_visible($step, $req, $user)` only half-honours its `$user`** — it passes
   it to `appr_can_act()` and evaluates `appr_guard()` for the session user. Not a
   defect today (the only caller asking about somebody else goes through
   `appr_may_be_asked()`, which impersonates), but it is a trap, and it is recorded
   rather than silently redesigned.
3. Escalation still does not transfer authority; no mandatory-approval switch; no
   per-tenant time zone; parallel approval unsupported; four rule conditions
   unimplemented; Offer/Salary SLA events logged but not linkable.

## Deferred F4 / F5

- **F4** — the Recruitment Command Centre remains **company-wide by design**. No
  branch filtering added. Known design characteristic.
- **F5** — unbounded scans deferred; measured at **4 ms** for 15 candidates, so not
  material at present scale.

## Manual / UAT evidence

**None.** This correction changed no screen. The M3 screens still have not been
exercised by a human in a browser, and Phase 1 UAT on MilesWeb production remains
open.

## Commit · working tree

See the final message. Mutations ran against copies outside the repository, so the
working tree is clean.
