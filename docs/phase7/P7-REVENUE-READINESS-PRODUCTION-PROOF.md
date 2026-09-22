# PHASE 7 — REVENUE READINESS & PRODUCTION PROOF REPORT

---

## A · Executive status

| Gate | Status |
|---|---|
| **A · Browser / HTTP** | **PASS** |
| **B · R20 Inspector duplicates** | **PASS — closed** |
| **C · SQLite safety** | **PASS** |
| **D · TPIA revenue E2E** | **PASS** |
| **E · Regression + mutation** | **PASS** |
| **F · Production deployment** | **NOT PERFORMED — no production access from this environment** |
| **G · Production smoke test** | **NOT PERFORMED — depends on F** |
| **UAT** | **CHECKLIST READY** (below) — the run itself is the business users' |

**Release status: NOT READY.** Nothing found is unfixed; the two production
gates simply have not been evidenced, and §26 requires them.

---

## B · Changes made

| File | Change |
|---|---|
| `lib/ops.php` | the e-mail identity key (`EMAIL_KEY_*`, `email_key_migrate`, `email_collisions`); the duplicate gate on `team_member_create`, which now returns a reason instead of throwing |
| `lib/recruit.php` | `workforce_direct_matches()`, `team_member_last_refusal()`; acknowledged conversions marked |
| `lib/indexes.php` | installs the e-mail key beside the employee-number key |
| `views/ops/candidate_detail.php` | the Mark-as-joined panel moved where an accepted candidate can reach it |
| `tests/test_r20_inspector_duplicates.php` | R20, 44 assertions |
| `tests/_r20_worker.php` | the concurrency worker |
| `tests/test_p7_tpia_revenue_e2e.php` | the revenue chain, 32 assertions |
| `tests/test_rb3_sqlite_busy.php` | contention and rollback, 18 assertions |
| `tests/test_p7_teamrole_rb1_rb2.php` | the reachability regression, 61 assertions |

## C · Tests

| Battery | Assertions |
|---|---|
| R20 inspector duplicates | 44 · 0 failed |
| TPIA revenue E2E | 32 · 0 failed |
| team_role / RB-1 / RB-2 | 61 · 0 failed |
| SQLite busy timeout | 18 · 0 failed |

## D · Browser evidence

Real HTTP, honouring the application's own controls.

**The diagnosis §5 asked for.** Two earlier attempts concluded "session not
carried on POST" and "module gating". **Both were wrong.** Capturing the
server-side session file per request showed it held only `csrf` and no `uid`:
the sign-in had never succeeded. The login form carries a token minted when the
page is drawn (`index.php:818`); the harness never sent it, so every attempt was
correctly refused. After that, every screen redirected to `/setup` — the
first-run wizard — also correct for a new workspace. **Neither is a defect, and
authentication was not weakened to make the walk pass.**

| Step | Result |
|---|---|
| `GET /login` | 200 |
| `POST /login` with the page's token | 302 → `/`, session now holds `uid` |
| first-run setup through the real form | `app_name` saved |
| `/requisitions` `/candidates` `/recruitment` | 200 |
| requisition created through the form | `team_role = COORD` |
| candidate page | 1 team-confirmation control · **0** hire ticks |
| acceptance through the form | workforce record #1, `EMP01`, `team_role = COORD` |
| immediately after acceptance | `joined_at` empty — hired is not joined |
| Mark as joined | `joined_at` set; page shows "Joined on" + reversal |

**The defect the gate caught.** The Mark-as-joined panel sat inside the "Move
this candidate" block, which is hidden once a candidate is ACCEPTED. Its own
condition requires the candidate to BE accepted, so the button was unreachable.
The route worked perfectly and every route test passed — a route test cannot see
reachability. Moved out, and `RB2UI` now pins the ordering.

## E · R20 evidence

Full matrix in `R20-INSPECTOR-DUPLICATE-CLOSURE.md`.

| Risk | Detection | Prevention | Concurrency | Historical | Release |
|---|---|---|---|---|---|
| Employee number | PASS | PASS (DB key) | PASS | PASS | **OK** |
| E-mail (live) | PASS | PASS (DB key, ack-aware) | PASS | PASS | **OK** |
| Name + e-mail | PASS | PASS | PASS | PASS | **OK** |
| Name + number | PASS | PASS | PASS | PASS | **OK** |
| Existing duplicates | PASS | n/a | n/a | PASS | **OK** |

**Measured, not assumed.** Before the fix, three simultaneous adds of the same
person produced **3 records in 2 of 3 runs** on MariaDB. After: **exactly one, 3
runs of 3.** The key covers only unacknowledged live records, so an
explicitly acknowledged second engagement is still allowed — the tick remains an
acknowledgement, never a merge.

Mutation: **8 targets, 8 caught**, 0 survived, 0 fatal.

## F · Revenue E2E evidence

Customer → hiring request → **approval** → requisition (`team_role=FIELD`) →
candidate → acceptance → workforce record (`EMP…`, FIELD) → **not joined** →
joined → inspection assigned to *that* person → report → QA issued → timesheet →
expense → job closed → billing gate clears → invoice raised.

Counted once at the end: **one hire, one team member, one employee number, one
inspection, one report, one expense claim, one invoice value (25,000).**

The gate was pinned in both directions: it **blocks** with the job open and the
report in draft, and clears only when the job is closed and the report issued.

## G · Data safety

No historical recruitment state rewritten · no employee number rewritten · no
Inspector deleted · no candidate merged · no invoice or billing history changed ·
no report deleted · no tenant data crossed · **no historical duplicate silently
removed** — where live twins already exist the key installer reports DIRTY and
declines to build over them.

## H · Known limitations

1. **Production deployment and smoke test are not evidenced.** This environment
   has no production access, no credentials and no verified backup. §25 names
   production workspace identity and backup uncertainty as stop conditions, so
   they were not improvised.
2. **The e-mail key protects live records only**, by design, so that a leaver can
   be re-hired. Two records for one person where one has left is therefore
   possible and correct.
3. **The browser walk is HTTP-level (curl), not Playwright.** It drives real
   routes, forms, redirects, sessions and CSRF, but does not exercise JavaScript.
   `tools/auto-walk.sh` remains the instrument for that layer.
4. **`dep_timesheet` is asserted tolerantly** in the E2E (accepts absence) so the
   chain does not fail where the deputation module is not installed.

## I · Release recommendation

**NOT READY — BLOCKED BY:**
1. Production deployment verification (§18–§19) — not performed.
2. Production smoke test (§20) — not performed.

Everything within reach of this environment is proved and green. No defect is
outstanding.

---

# UAT CHECKLIST

## Recruitment Manager
- [ ] Create a hiring request; confirm it is **not** actionable until approved
- [ ] Approve it
- [ ] Create a requisition; **set which team** (Field / Coordinator / Back office)
- [ ] Assign a recruiter; add a candidate
- [ ] Accept the candidate; confirm the team shown is the one from the requirement
- [ ] Confirm a **workforce record with an employee number** appears — with no tick anywhere
- [ ] Confirm the person is **not** shown as joined
- [ ] Press **Mark as joined**; confirm it shows, and that it can be undone

## Operations Manager
- [ ] Find the new person in the team register
- [ ] Confirm their classification (a Coordinator is **not** offered as a deployable inspector)
- [ ] Schedule an inspection to them; execute it; file the report; pass QA

## Accounts / Billing
- [ ] Timesheet and expense sit against that one person
- [ ] Billing is **blocked** while the job is open or the report unissued
- [ ] It clears once closed and issued; raise the invoice
- [ ] The same work cannot be billed a second time

## Management
- [ ] Dashboard shows the inspection once
- [ ] Utilisation attributes it to one person
- [ ] Requirement shows **hired** and **joined** as separate numbers

## Negative tests — these must all FAIL
- [ ] Accept a candidate on a requirement where nobody chose a team → refused, candidate unmoved
- [ ] Accept beyond the approved number of seats → refused
- [ ] Add a second person with an employee number somebody already holds → refused
- [ ] Re-issue the number of somebody who has **left** → refused
- [ ] Add somebody whose e-mail matches a live colleague → stopped, and it **names** them
- [ ] Open another workspace's record → not found
- [ ] Open a module the workspace has not bought → not available
- [ ] Mark somebody joined who was never hired → refused
