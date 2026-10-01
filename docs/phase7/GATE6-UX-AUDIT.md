# GATE 6 — UX, CONTEXTUALISATION & OPERATIONAL CLARITY: AUDIT

Starting base `135f6d1` (Gate 5 closed), working tree clean, branch
`claude/testing-branch-setup-0gqe8n`, local and remote identical.

**No implementation has been done.** This document is the §20 deliverable. It
exists so the changes can be agreed before any code moves.

---

## HEADLINE

Gate 5's business rule is correct in the engine and **is not fully enforced at one
screen**. One job-assignment dropdown still admits a person who has not joined.

Everything else found is genuine UX/contextualisation work: the system knows the
right answer and does not always say it out loud.

---

## A. UX AUDIT — FINDINGS

### A-F1 · P0 — A JOINING-PENDING PERSON CAN STILL BE ASSIGNED A JOB

`lib/tosrm.php:975`

```php
$insps = ops_all("SELECT id, name FROM inspectors WHERE COALESCE(status,'')<>'INACTIVE' ORDER BY name") ?: [];
```

This feeds the **inspector dropdown of the job assignment panel**
(`tosrm_render_job_panel`) — the control a coordinator uses to put a person on a
job. It filters **negatively**: "anyone not INACTIVE". `PENDING_JOINING` is not
INACTIVE, so a person who has accepted an offer and has not started appears in
the dropdown and can be assigned work before their first day.

This is exactly the failure mode §9 of the Gate 6 brief warns about, and it
breaks **Gate 5 Invariant 3** today, on `135f6d1`, in shipped code.

**It is also a gap in my own Gate 5 audit, and I should say so plainly.** Gate 5
reported that no reader treats "not ACTIVE" as usable. I tested for two negative
forms — `status <> 'ACTIVE'` and `status = 'INACTIVE'` — and did not test for
`<> 'INACTIVE'`, which is what this site uses. The Gate 5 statement was therefore
too strong. The full classified census is now:

| Form | Count | Verdict |
|---|---|---|
| Positive (`='ACTIVE'`, or the NULL/blank-tolerant variants, or `wf_active_sql()`) | 25 | correct |
| **Negative (`<>'INACTIVE'`)** | **1** | **A-F1, must be fixed** |
| By explicit id (fetching one known person) | 63 | not a gating decision |
| Unfiltered | 40 | reviewed individually below |

The 40 unfiltered reads were each read, not sampled. They are: boot column
probes (`index.php`), seed/demo fixtures, `DELETE` cleanups, employee-number
duplicate checks, data-integrity orphan checks, placement-fee commercial reports,
the admin team list (**must** show everyone — §11), `schedboard.php:92` (enriches
skills for people already gated upstream), and three whose `WHERE` is built in a
variable and which were confirmed positive by reading the variable:
`mis.php:274` (`status='ACTIVE'`), `schedule.php:551`
(`COALESCE(status,'ACTIVE')='ACTIVE'`), `workforce.php:415`
(`status='ACTIVE' AND …`). None of these gates work.

### A-F2 · P1 — THE AVAILABILITY BOARD NEVER EXPLAINS AN ABSENCE

A coordinator knows a new engineer was hired. They open the availability board to
schedule them and the person is not there. The board says nothing. There is no
way to tell apart:

* nobody was hired for that role;
* somebody was hired and has not joined yet;
* somebody joined and is on leave;
* the filters are hiding them.

The board's only empty-state text is for a different panel
(`views/ops/availability.php:81`, "Nobody is free across both days"). A silent
absence is the single most likely cause of a user concluding the product has lost
their data, and it is the main reason Gate 6 exists.

### A-F3 · P1 — HR CANNOT LIST THE PEOPLE AWAITING A JOINING

`lib/ops.php:4936-4940` — the team list supports exactly one filter, a free-text
search over name / employee code / skills. There is no status filter, server-side
or on screen. The Gate 5 badge correctly shows "Joining pending" **per row**, so
the information is present but only findable by reading every row of the whole
team.

The one business question this gate should make easy — *"who have we hired that
has not started yet?"* — cannot be asked.

### A-F4 · P1 — NEXT ACTION IS ONLY ON DETAIL PAGES, NEVER AGGREGATED

`lib/nextaction.php` already produces the right answer for a joining-pending
person: state "Accepted / hired", next action "Mark as joined once they actually
arrive", with the CTA deep-linking to the joining control
(`na_candidate()`, lines 182-192).

But `na_html()` is rendered on three **detail** screens only
(`candidate_detail.php:20`, `requisition_detail.php:17`, `hiring_request.php:44`).
Nothing aggregates it. To chase joiners, HR must already know who they are and
open them one at a time — which is the problem A-F3 describes, from the other
side.

The mechanism exists and must be reused, not rebuilt (§8). What is missing is a
way in.

### A-F5 · P2 — THE TEAM LIST COUNTS JOINERS AS WORKFORCE

`views/ops/inspector_list.php:3` prints `count($rows) . " inspector(s)"` over an
unfiltered list, so the headline figure mixes people who work here with people who
have not arrived. The rows are individually labelled correctly; the total is not.
This is the §10 "must not silently inflate operational figures" concern, in its
mildest form.

### A-F6 · P2 — MOBILE MENU CLOSE BUTTON (carried forward, §2.2)

`.side-close` is 20px tall (`assets/css/app.css:361`, shown at ≤640px by line
610; markup at `views/layout_top.php:68`). Below any reasonable touch target, on
every screen in the product. Pre-existing and unchanged since `b1e793c`.

### A-F7 · P2 — REPORTS THAT LIST EVERYONE

`inspectors_list(false)` at `lib/ops.php:9588` (manday report) and `:5912`, and
the report filter at `lib/mis.php:373`, include joining-pending people. A joiner
contributes zero mandays, so no figure is wrong — but they appear as a row of
zeros. **This is a decision, not a defect**, and I am not changing it without a
ruling: a report filter that cannot name a person is also unhelpful.

---

## B. LIFECYCLE CONSISTENCY MATRIX

Measured from the code at `135f6d1`, not from intent.

| Lifecycle state | Where it lives | What the user sees today | Actions offered | Operational? | HR follow-up? |
|---|---|---|---|---|---|
| Candidate (applied) | `candidates.stage` | configured pipeline stage label | advance / reject | no | n/a |
| In selection | `candidates.stage` | configured stage label | advance / reject / interview | no | n/a |
| Offer issued | `job_offers.status` | offer panel | accept / decline | no | n/a |
| **Offer accepted / hired** | `stage=ACCEPTED`, `inspectors.status=PENDING_JOINING`, `joined_at` empty | candidate: "hired is not the same as started" ✓ · team list: amber **Joining pending** ✓ · team record: "becomes Active automatically when their joining is recorded" ✓ | **Mark as joined** ✓ | **no** — excluded from availability, allocation picker, capacity, reports ✓ **except A-F1** ✗ | Next Action on the candidate page ✓ — **but not findable in aggregate (A-F3, A-F4)** ✗ |
| **Joined / workforce active** | `joined_at` set, `status=ACTIVE` | green **Active** ✓ | full operational actions | **yes** ✓ | nothing required ✓ |
| Left | `status=INACTIVE` | grey **Inactive** ✓ | asset recovery ✓ | no ✓ | kit chase ✓ |

Terminology is already consistent in the places Gate 5 touched. No screen labels
an accepted-but-not-joined person "Active", "Joined", "Available" or "Employee",
and none calls a joiner a leaver. The §4 prohibitions are satisfied today.

---

## C. GATE 5 PROTECTION MATRIX

| # | Gate 5 invariant | Existing protection | Gate 6 risk | Test that proves it |
|---|---|---|---|---|
| 1 | Accepting an offer does not activate | `rcv_convert()` writes `WF_ST_JOINING` | low — not touched | G5 A1–A9; mutation M1 |
| 2 | Joining is the activation boundary | `wf_join_activate()` from the one route | low — not touched | G5 D1–D7; mutations M2, M3 |
| 3 | **Only positive ACTIVE qualifies** | 25 positive reads | **BREACHED at `tosrm.php:975` (A-F1)** | mutations M6, M8 — **neither covers tosrm**; a new test is required |
| 4 | Joining-pending stay visible to HR | admin team list unfiltered | low, but **not usable** (A-F3/A-F4) | G5 A9 |
| 5 | A joiner is not a leaver | `wf_has_left()` / `wf_left_sql()` | low — must not regress | G5 C9–C12, H1–H3; mutation M7 |
| 6 | Joining is a typed event | `JOINED`/`JOINING_CLEARED` in `ACT_KINDS` | low — must not regress | G5 L1–L7; mutation M12 |
| 7 | MariaDB cannot implicitly commit the hire | in-transaction guard in `connect_identity_migrate()` | low — must not regress | G5 O1–O5; mutation M14 (MariaDB) |
| 8 | No duplicate record on retry | conditional claim + `FOR UPDATE` | low — not touched | G5 B4, B5, M1–M4 |

**Invariant 3 has no test at the assignment dropdown.** That is why A-F1 survived
Gate 5's mutation battery: M6 and M8 break the *helpers*, and `tosrm.php` never
calls them. Any fix must come with a test that fails without it.

---

## D. PROPOSED CHANGES

| # | File / surface | Current behaviour | Problem | Proposed behaviour | Why required | Regression risk | Test required |
|---|---|---|---|---|---|---|---|
| D1 | `lib/tosrm.php:975` (job assignment dropdown) | `COALESCE(status,'')<>'INACTIVE'` | a non-joined person can be assigned a job | use `wf_active_sql()` — the one canonical positive test | Gate 5 Invariant 3; §9 | **low** — narrows a picker; a joiner should never have been in it. An INACTIVE person was already excluded, and a blank status still reads active | new: the dropdown excludes a joining-pending person and includes an active one; a mutation reverting it must fail |
| D2 | `views/ops/availability.php` | silent absence | user cannot tell "not hired" from "hired, not joined" | one line under the board when the office has joining-pending people: "N hired, not yet joined — not available for scheduling", linking to the filtered team list | A-F2; §4, §13 | **none** — additive text, no query change to the roster itself | the note appears only when such people exist, and names the right count |
| D3 | `lib/ops.php:4936` + `views/ops/inspector_list.php` | text search only | HR cannot list joining-pending people | add a status filter using `wf_statuses()`; default unchanged (everyone) | A-F3; §9 | **low** — new optional filter; must use positive matching on the chosen value, never negation | filtering to Joining pending returns exactly those; default still returns everyone (preserves §11) |
| D4 | `views/ops/inspector_list.php:3` | `N inspector(s)` | conflates workforce with pipeline | `N on the team · M joining pending` when M > 0 | A-F5; §10 | **none** — display only | the split count is correct for a mixed list |
| D5 | `assets/css/app.css` (`.side-close`) | 20px | fails touch target | min 44px, unchanged appearance otherwise | A-F6; §2.2, §11 | **low** — global chrome; must be verified at 360/390/412 and on desktop | browser check asserts no control under 32px, with `.side-close` no longer excluded |

**Deliberately NOT proposed:**

* No aggregated "joining follow-up" screen. D3 + D4 make the existing team list
  answer the question, and §8/§14 forbid a second follow-up system. If D3 proves
  insufficient in use, that is a separate decision with evidence behind it.
* No change to A-F7 (reports listing everyone) — awaiting your ruling.
* No new permission, role, column, table, status value or lifecycle.

---

## E. NO-CHANGE AREAS — explicitly out of scope

* The whole Gate 5 engine: `rcv_convert()`, `wf_join_activate()`,
  `wf_join_stand_down()`, `wf_status_move()`, the status vocabulary, the survey
  and migration. Correct and proved.
* `connect_identity_migrate()`'s in-transaction guard and the `rcv_convert()`
  pre-transaction call. Invariant 7. Not to be weakened for any reason.
* `wf_has_left()` / `wf_left_sql()` and the four asset-recovery call sites.
  Invariant 5.
* `ACT_KINDS` joining events and `act_kinds_manual()`. Invariant 6.
* All 25 positive operational reads, including `inspector_availability()`'s
  strict `status='ACTIVE'` — deliberately not loosened to the blank-tolerant form,
  which would newly admit blank-status people to the board.
* `reqfulfil.php`'s `joined` vs `filled` counters. Already Gate 5-aware.
* Gate 2 versioning, Gate 3 reviews, Gate 4 approval governance.
* Candidate-side wording, the joining panel, the inspector form status select and
  the recruitment-origin narration — all correct as of Gate 5.

---

## CARRIED-FORWARD ITEMS (§2) — status

* **§17 data audit — PENDING, and cannot be run from here.** No production or
  development database is reachable from this build container; the tool
  (`php tools/g5-data-audit.php`, read-only) needs to run where a copy of the live
  data is. I will not manufacture figures. Either restore a copy where this
  session can reach it, or run the tool and paste the output.
* **Gate 3 "redefined" trigger — PENDING your confirmation.** Shipped ON and
  configurable. No Gate 6 change proposed here depends on it. If implementation
  touches code that reads it, the existing behaviour will be preserved and the
  dependency reported, not reinterpreted.
