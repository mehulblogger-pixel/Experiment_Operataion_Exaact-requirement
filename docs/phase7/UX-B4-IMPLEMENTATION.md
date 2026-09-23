# UX-B4-IMPLEMENTATION — Terminology and relationship visibility

**Scope: B4 only.** No area-home counts (B5), form redesign (B6), mobile tables
(B7), search (B8), dashboard (B9) or visual polish (B10). No terminology
*semantics* changed, no database model, no lifecycle state, no permission, no
identity architecture, no Person Hub. **ADR-001 not decided.**

---

## 1. Audit — measured, not assumed

| Claim | Measured | Verdict |
|---|---|---|
| "25 curated definitions" | **26** | minor correction |
| "…render only on the admin rename screen" | `views/ops/terminology.php:56` is the **only** place a definition is printed | **confirmed** |
| "Five words have no definition" | **Six** — `hiring_request`, `workforce`, `inspector`, `qa`, `billing_readiness`, `professional` | corrected upward |
| "No screen states the relationship between two confusable objects" | **Wrong.** `requisition_detail.php` has said *"Raised from hiring request HR-xxx, approved by … on …"* since M4 | **corrected** |

**What that changed.** The relationship line the confusion audit recommends for
pair 1 already existed, so B4 did not build it. What it found instead were two
real gaps either side of it:

- a requirement raised **directly** said *nothing at all* about its origin, so a
  reader could not tell whether the approval step had been skipped or had never
  applied;
- the chain was visible in **one direction only**. The candidate screen says
  *"Hired — this candidate is now Team Member #12"*; from the team member's own
  screen there was nothing.

No tooltip or inline-help mechanism exists anywhere in the product — confirmed
by searching the stylesheet. That is a deliberate constraint, not an oversight:
a tooltip cannot be read on a phone, and inspectors are phone-first.

---

## 2. The six definitions

Each is taken from a decision already recorded. **None is invented here.**

| Word | Definition | Source |
|---|---|---|
| **Hiring request** | A request for headcount, raised before recruiting starts so it can be approved. Approving it is what allows a requisition to be raised against it. | M4 — the request layer |
| **Workforce** | Everybody employed or engaged by this company. A workforce record is created when somebody is hired, whatever job they do. | Q32 Model D §10 |
| **Inspector** | A workforce member whose team role is Field — the ones who can be sent to site. Every inspector is workforce; not every workforce member is an inspector. | Q32 Model D §10 |
| **QA** | The review a report goes through before it is issued. QA is a stage in a report's life, not a separate document. | confusion audit pair 10 |
| **Billing readiness** | A check that everything needed to bill is present and agreed. It is not an invoice and raises no money — it is what tells you an invoice can safely be raised. | confusion audit pair 11 |
| **Professional** | Somebody who lists themselves on the marketplace. A professional is not this company's staff until they are hired, which is what makes them workforce. | confusion audit pair 2 |

They were added to `TERM_DEFAULTS` — **the one system that already exists** — so
they appear on the rename screen with the other 26 and are renameable like any
other word. 26 before, **32** now; none removed, and no existing definition
altered. Both facts are asserted by test, and deliberately breaking one was
proved to fail.

---

## 3. Where a definition now appears

Two accessors, `T_HELP()` and `T_NOTE()`, render the sentence as **the same
muted one-liner that 295 of 401 views already carry**. No new component, no
tooltip, no glossary page.

Used **only at the points the confusion audit measured**, never sprayed across
every screen:

| Screen | What it now says | Pair |
|---|---|---|
| Hiring request | the definition, under the title | 1, 7 |
| Team member form, at the **Team** field | *"Everyone here is workforce. Choosing **Field** is what also makes somebody an inspector — the ones who can be sent to site."* | 4 |

The second is the important one: the workforce-vs-inspector rule lived in the
code and nowhere else, and it is stated **at the field that decides it** rather
than in a help page somebody would have to go looking for.

---

## 4. The chain, now visible both ways

| Direction | Before | After |
|---|---|---|
| Hiring request → requirement | *"Raised from hiring request HR-00231, approved by … on …"* | unchanged — it already worked |
| **Direct requirement → origin** | **nothing** | *"Recorded directly — there is no hiring request behind this one. Both ways of starting are supported."* |
| Candidate → workforce | *"Hired — this candidate is now Team Member #12"* | unchanged |
| **Workforce → candidate** | **nothing** | *"Hired through recruitment as candidate CV-RC-055, recruited for REQ-… — Joined on …"*, or *"No joining date recorded yet — accepted is not the same as joined."* |

`workforce_origin()` reads `candidates.inspector_id` **the other way round**.
Nothing new is stored, no column added. It returns null for somebody added by
hand through Masters — a real and common case, said rather than guessed at.

### ADR-001 is not decided by a sentence

The direct-path line **describes what happened and recommends nothing.** The
test suite fails if the screen ever says *"should have been raised"*,
*"preferred"*, *"bypassed"*, *"incorrectly"*, *"ought to"* or *"skipped the
approval"* — and that guard was proved by deliberately inserting one.

---

## 5. Verification

| Layer | Result |
|---|---|
| `tests/test_b4_terminology_relationships.php` (new, 67 assertions) | **67 passed, 0 failed** |
| `tools/b4-terms-check.js` (new, 12 checks in Chromium) | **12 passed, 0 failed** |
| Back-link exercised on a real record | team member **#11** names the candidate they were hired as |
| JavaScript / server errors | none |

### Mutation tests

| Mutation | Caught by | Result |
|---|---|---|
| Quietly restate what "candidate" means | B1 | FAIL, as required |
| Editorialise on ADR-001 (*"should have been raised…"*) | E2 + E3 | 2 assertions FAIL |

---

## 6. Deferred

| Finding | Phase |
|---|---|
| Remaining pairs (QA vs report on the report screen; billing readiness vs invoice on the money screens; professional vs candidate at the marketplace join) — definitions now exist but are not yet surfaced there | **B4 follow-on / B10** |
| The other 26 definitions still appear only on the rename screen | as above — B4 surfaced the measured confusion points, not all 32 |
| `.btn.small` 36px vs the blueprint's 44px | B7/B10 |
| Hiring request has no `allowed_next()` | product decision |
| Consolidating the nine hand-written `.nowband` blocks | B10 |

---

## 7. Files changed

| File | Change |
|---|---|
| `phpapp/lib/terms.php` | six definitions; `T_HELP()` and `T_NOTE()` |
| `phpapp/lib/workforce.php` | `workforce_origin()` — reads the existing link backwards |
| `phpapp/views/ops/hiring_request.php` | definition under the title |
| `phpapp/views/ops/requisition_detail.php` | neutral origin line for the direct path |
| `phpapp/views/ops/inspector_form.php` | workforce-vs-inspector at the Team field; origin block |
| `phpapp/tests/test_b4_terminology_relationships.php` | **new** — 67 assertions |
| `phpapp/tools/b4-terms-check.js` | **new** — 12 checks |
| `phpapp/deploy-check.php` | regenerated |

**`app.css` unchanged.** No database change, no migration.

---

## 8. Regression

| Engine | Result |
|---|---|
| SQLite | **13,762 passed, 0 failed** |
| **MariaDB (authoritative)** | **13,762 passed, 0 failed** |

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
| **Terminology engine** | `terms` | **69 / 0** |
| B1 accessibility (not regressed) | `b1_access` | 112 / 0 |
| B2 navigation (not regressed) | `b2_nav` | 42 / 0 |
| B3 next action (not regressed) | `b3_next` | 55 / 0 |
| B4 terminology & relationships | `b4_term` | 67 / 0 |
