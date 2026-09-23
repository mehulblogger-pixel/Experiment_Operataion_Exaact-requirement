# UX-B3-IMPLEMENTATION — Next Action

**Scope: B3 only.** No terminology work (B4), area-home counts (B5), form
redesign (B6), mobile tables (B7), search (B8), dashboard (B9) or visual polish
(B10). No workflow, KPI, SLA, notification or business-rule engine. No database
change.

---

## 1. A correction, before anything was built

**Finding F-A5-3 said there was "no shared next-action component".** That is
wrong, and checking it before writing code is what stopped B3 producing a second
component beside the one that already exists.

`.nowband` — a `.step` headline, a `.next` sentence and an optional `.cta` — is
defined in `app.css` and is **already rendered by nine record screens**:

`call_detail` · `job_detail` · `lead_detail` · `opportunity_detail` ·
`crm/quote_detail` · `invoice_detail` · `receipt_detail` ·
`idems/doc_detail` · `trace_thread`

**What was actually missing is the Recruitment records.** Candidate, requirement
and hiring request had no such block at all — which matches the scorecard, where
Recruitment scores worst for action and relationship clarity.

So B3 did not introduce a pattern. **It fed the existing one.** The architecture
rule in the brief is REUSE → EXTEND → … → BUILD; this is the reuse case, and I
had already written ~40 lines of a competing `.na-block` component before
checking. That code was deleted.

This is the sixth correction issued against my own audit figures.

---

## 2. What B3 is

`phpapp/lib/nextaction.php` — a **presentation layer**. Every answer is read out
of logic that already exists, and each answer records *which* helper produced it
in a `src` field, so the claim can be checked rather than believed.

| Record | State read from | Next step read from | Permission asked of |
|---|---|---|---|
| **Hiring request** | `HREQ_STATUS` | the conditions the hiring-request screen **already renders** (it has no allowed-next helper, so inventing a transition table would have been inventing a business rule) | `hreq_can_create()` / `hreq_can_decide()` |
| **Requirement** | `reqf_counts()` | `reqf_counts()` — requested / filled / joined / remaining | `can('mod.hiring.edit')` |
| **Candidate** | `recruitpipe_cand_state()` | the next **effective** stage of the configured pipeline; `recruitpipe_legacy_terminal()` for terminal stages | `is_coordinator_level()` |

**The work order has no resolver here on purpose.** `call_detail.php` already
renders its own band, and it is better than anything generic — it distinguishes
the contracting office from the executing one and says *"allocating is the
executing office's responsibility"* rather than hiding the button. A second
answer on a screen that already has a good one would have been a regression.

### Three rules, each enforced by test

1. **No invented step.** A resolver may only name a transition its module
   already allows. An unrecognised status yields no step at all — silence is
   correct, a guess is not.
2. **No granted permission.** `can` is always the module's own gate, called and
   never re-implemented. When it is false the block shows **words, never a
   button**. The server remains the boundary; this is a label.
3. **No misleading action.** Where nothing is due, or the wait is on somebody
   else, the block says so rather than offering something to click.

### What it is deliberately not

`ops_pending_tasks()`, `action_centre()` and `lib/advisor.php` already answer
*"what is waiting for me across the business"*. This answers *"what happens next
to the record I am looking at"* — which no existing code answered. It is not a
second queue.

---

## 3. What a user now sees

| Record | State | Next |
|---|---|---|
| Requirement, nothing filled | `0 of 5 filled` | **Put candidates forward — 5 still to fill** → *Add candidates* |
| Requirement, all filled, nobody marked joined | `All 5 filled` | **Confirm who has actually joined — 5 not yet marked** → *Record joiners* |
| Requirement, filled and joined | `Filled and joined` | *Nothing required right now.* |
| Candidate, accepted, no joining date | `Accepted / hired` | **Mark as joined once they actually arrive** → *Mark as joined* |
| Candidate, mid-pipeline | the stage name | **Move to \<next stage\>** |
| Hiring request, draft | `Draft` | **Submit it for approval** |
| Hiring request, submitted, you may decide | `Submitted` | **Approve or reject it** → *Review it* |
| Hiring request, submitted, you may **not** | `Submitted` | *Waiting for an approver. Nothing is recruited until it is approved.* **(no button)** |
| Hiring request, approved, all being recruited | `Approved` | *Approved, and everything asked for is already being recruited.* |

The requirement and candidate rows carry the **RB-2 distinction the owner
already locked**: filled is not joined, and accepted is not joined. B3 surfaces
it; it does not redefine it.

---

## 4. Verification

| Layer | Result |
|---|---|
| `tests/test_b3_next_action.php` (new, 55 assertions) | **55 passed, 0 failed** |
| `tools/b3-nextaction-check.js` (new, 32 checks in Chromium) | **32 passed, 0 failed** |
| All three recruitment records render a band, exactly one each | PASS |
| It is the plain `.nowband` class — no B3 variant | PASS |
| The work order still has its own band, not two | PASS |
| Mobile 360×800 / 390×844 / 412×915 | PASS — no overflow, band present, 36px touch target |
| JavaScript / server errors | none |

### Mutation tests

| Mutation | Caught by | Result |
|---|---|---|
| Render the button regardless of the gate | **D5** | FAIL, as required |
| Invent a "Reopen it" step for a terminal status | C4 ×2, C6 | 3 assertions FAIL |

**An honest note on D2.** The first mutation passed D2 and only failed D5. There
are two layers: for a *submitted* request the resolver also withholds the route
when the gate refuses, so D2 stays green even with the renderer's gate removed.
D5 is the load-bearing one, because a *draft* always carries a route and the
gate is the only thing between it and a button. Both layers are kept — defence
in depth is worth having — but the test file now says which test guards which
layer, rather than implying D2 proves more than it does.

### A fault in the check itself

The first mobile run reported a **27px** touch target and I nearly recorded it as
a defect. It was not. The 44px rule in `app.css` is `@media (pointer:coarse)` —
a **touch-device** query, not a width query. Narrowing a desktop viewport leaves
the pointer "fine", so the rule never applied and the button was measured at its
desktop height. The check now emulates a real touch device, with an arming
assertion that the context actually reports a coarse pointer.

---

## 5. Deferred — found during B3, not fixed here

| Finding | Why not now | Phase |
|---|---|---|
| `.btn.small` gets **36px** on a touch device; the blueprint asks 44px | App-wide rule affecting every small button on every screen, including the nine bands that pre-date B3. Changing it for one component would make B3's band inconsistent with the others. | **B7 / B10** |
| Hiring request has no `allowed_next()` helper — its transitions live only in the view's conditions | Extracting one is a lifecycle refactor, not presentation | **product / later** |
| The band could state the relationship ("raised from HR-00231") | That is relationship visibility | **B4** |
| Nine existing bands each hand-write their logic | Consolidating them onto `na_state()` is a refactor of working screens | **B10** |

---

## 6. Files changed

| File | Change |
|---|---|
| `phpapp/lib/nextaction.php` | **new** — three resolvers + one renderer emitting the existing `.nowband` |
| `phpapp/index.php` | registers the library after the engines it reads from |
| `phpapp/views/ops/hiring_request.php` | renders the band |
| `phpapp/views/ops/requisition_detail.php` | renders the band |
| `phpapp/views/ops/candidate_detail.php` | renders the band |
| `phpapp/tests/test_b3_next_action.php` | **new** — 55 assertions |
| `phpapp/tools/b3-nextaction-check.js` | **new** — 32 checks |
| `phpapp/deploy-check.php` | regenerated |

**`app.css` is unchanged** — the component it needed was already there.

---

## 7. Regression

| Engine | Result |
|---|---|
| SQLite | **13,695 passed, 0 failed** |
| **MariaDB (authoritative)** | **13,699 passed, 0 failed** |

An intermediate run failed **one** test — the deploy-check checksum, naming
exactly the three files edited after it was generated. That is the guard doing
its job, not a defect; regenerated and re-run clean.

### Protected modules — MariaDB

| Module | Suite | Result |
|---|---|---|
| Recruitment | `recruit` | 296 / 0 |
| Recruitment — numbers & races | `rb3` | 276 / 0 |
| Workforce identity | `r20` | 44 / 0 |
| Workforce duplicate doors | `fa7` | 29 / 0 |
| Operations | `p2` | 437 / 0 |
| Operations | `p3` | 2,611 / 0 |
| Reporting | `report` | 352 / 0 |
| Money — billing | `billable` | 90 / 0 |
| Money — vouchers | `voucher` | 117 / 0 |
| Marketplace | `connect` | 1,020 / 0 |
| Marketplace | `mkt` | 174 / 0 |
| Tenant isolation & entitlement | `saas` | 169 / 0 |
| Tenant API | `tapi` | 170 / 0 |
| Navigation invariants | `m11` | 60 / 0 |
| B1 accessibility (not regressed) | `b1_access` | 112 / 0 |
| B2 navigation (not regressed) | `b2_nav` | 42 / 0 |
| B3 next action | `b3_next` | 55 / 0 |
