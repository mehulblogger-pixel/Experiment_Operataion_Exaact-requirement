# UX-A7 — Duplicate workflow audit

**Phase A, step 7. Audit only — no code changed.**

Scope: Part 37. For every business action, find every route that can start it,
judge each door legitimate or not, and recommend one obvious primary route while
keeping contextual shortcuts.

**Counting links is the weak version of this question.** The census counted nine
screens linking to `/document-new` and eight to `/call-new` — but those are the
same route reached from different places, which is *contextual shortcutting* and
exactly what Part 37 says to keep. The real question is how many distinct code
paths **create the same record**, because that is where behaviour can diverge.

It did. This step found the most consequential defect in the audit so far.

---

## Method

Counted `INSERT INTO <table>` sites per core record, then separated production
paths from seeders, demo scenarios and the trace harness:

| Record | Insert sites | Of which production |
|---|---:|---:|
| `inspectors` (workforce) | 11 | **3** |
| `candidates` | 7 | **2** |
| `requisitions` | 5 | — |
| `calls` | 11 | — |
| `jobs` | 12 | — |
| `business_partners` | 19 | — |

The raw counts badly overstate the problem: 8 of the 11 inspector inserts are
`seed_demo`, `seed_connect`, `seed_scenario_s01/s02/s03/s06` and `trace_audit`.

---

## F-A7-1 · Three doors create a workforce record, and one shows the user a raw SQL error
**Class: workflow · Severity: CRITICAL · Confidence: reproduced in a browser**
**Status: FIXED — see "How it was fixed" at the end of this finding.**

| Door | Entry point | Pre-submit duplicate check |
|---|---|---|
| **A** Hire a candidate | candidate screen → `rcv_convert()` | **Yes** — `workforce_matches()` + acknowledgement |
| **B** Add via user form / org admin | `team_member_create()` | **Yes** — `workforce_direct_matches()` + `dup_ack` |
| **C** Masters → Add a person | `/m/inspectors/new` | **No** — employee-code clash only |

Door C issues its own raw `INSERT INTO inspectors` and never calls
`team_member_create()`, so it bypasses the duplicate guard entirely.

### What actually happens — reproduced twice in Chromium

Adding the same person twice through **Masters → Add a person**:

| Attempt | Result |
|---|---|
| 1st | *"Inspector added. You can now add certifications and upload the scans."* |
| 2nd | **`SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'dupdoor3…`** |

**The data is safe.** Only one row was created — the `ux_inspectors_email` unique
key catches it. That is the protection working.

**The person is not.** They are shown a raw database exception, on an ordinary
task, in the middle of the product. The insert at `lib/ops.php:4768` has **no
try/catch at all**, so the `PDOException` reaches the global handler at
`index.php:109`, which renders `$ex->getMessage()` with *"Please screenshot this
and send it over."*

So the same action gives three different experiences:
- Doors A and B: *"Somebody with that e-mail may already be on your team"* —
  named, before the attempt, with a way to proceed deliberately.
- Door C: a SQLSTATE.

### This corrects two of my own earlier statements

1. **UX-A5 said errors were "substantially already met — 7 reachable, 6 of them
   admin-only demo loaders."** That was wrong, and wrong in the comfortable
   direction. My grep looked for `getMessage()` inside `flash()`; this error
   reaches the page through an **uncaught exception**, which that search could
   never find. Part 16's rule — never show a SQL error — is violated on a routine
   screen.

2. **The business UAT playbook I wrote for the owner is wrong on this point.**
   Test DUP-003 tells them: *"Add a second person with a current colleague's
   e-mail → Stopped, and told who they may already be, by name."* True for doors
   A and B. **False for the door the playbook sends them to.** Anyone running
   that test as written will see a SQLSTATE and, reasonably, record it as a
   failure — of the wrong thing. The playbook must be corrected.

### Recommended treatment

Part 37 says do **not** merge legitimate different scopes, and all three doors
are legitimate: hiring a candidate, creating a login for someone, and adding a
person who was never a candidate are genuinely different jobs.

The fix is to make them behave the same, not to remove any:
- Door C routes its insert through `team_member_create()`, inheriting the guard.
- Failing that, at minimum: wrap the insert, catch the key violation, and return
  the same sentence doors A and B already produce — the helper for this already
  exists (`email_key_is_taken()` + `team_member_last_refusal()`, written for R20).

**This is a UX defect with a code fix, not a product decision** — no status,
permission, lifecycle or schema changes. It belongs early in C-phase, ahead of
cosmetic work.

### How it was fixed

Fixed ahead of C-phase on the owner's instruction, using only machinery that
already existed. **The person/workforce model was not changed** — the
`dup_ack` column and the `ux_inspectors_email` unique key were both built for
R20; this door simply was not using them.

| Before | After |
|---|---|
| No check before the insert | Asks `workforce_direct_matches()` first, exactly as door B does |
| Raw `INSERT`, no `try/catch` | Wrapped; `email_key_is_taken()` recognises the key violation and answers in words |
| `SQLSTATE[23000]…` on screen | *"This person may already be on your team — Arun Verma (EMP01). Open the team register and check before adding them again."* |
| No way to proceed deliberately | A tick — *"I have checked; this really is a different person"* — writes `dup_ack`, as door B does |
| Typed values lost | The refused page re-renders the same form with every value the user typed |

Three supporting repairs came with it, all found by testing rather than reading:

1. `inspector_form_extra_vars()` was extracted so the **refused** re-render is
   given the same agencies, offices, managers and document lists as the normal
   form. Without it the refusal page would have been a degraded form — a second
   defect hidden behind the first.
2. The form's new/edit switches keyed off `$ins` being set. On a refused add,
   `$ins` **is** set (it holds what the user typed), so the page would have
   claimed to be an edit and posted to `/edit?id=0`. It now keys off
   `$isEdit = !empty($ins['id'])`.
3. **A correction to point 2, caught in review before this was committed.**
   Switching the title, the breadcrumb and the main form action was not enough.
   The page carries five further sections that only make sense for a row that
   exists — the signature pad, the certificate register, the Super-Admin
   allowances form, the document checklist and the "Documents & KYC" button —
   and every one of them was still keyed off `$ins` being truthy. On a refused
   add they would each have rendered against **record 0**: three more forms
   posting to `/m/inspectors/edit?id=0` and a KYC link to `/identity?i=0`. The
   same mistake ran the other way too: the "First certificate" section, which
   belongs to *adding*, was hidden by `if (!$ins)` on a page that is still an
   add.

   This was my own fix being half-done, and the first round of tests passed
   over it because they asked *"does the main form post to /new?"* rather than
   *"does anything on this page point at a record that does not exist?"*. The
   rule is now the stronger one: **`$ins` is for reading values back; `$isEdit`
   decides what the page is** — and both the server battery (E8) and the browser
   walk (U3d/U3e/U3f) enforce it, each proven to fail when one switch is put
   back.

**Acknowledging is not merging.** Ticking the box creates a *second* record, as
it does on door B. The owner's rule — the system never decides two people are
one — is untouched.

#### Proof

| Check | Result |
|---|---|
| `tests/test_fa7_masters_duplicate_door.php` (new, 29 assertions) | pass — the three doors are asserted to agree, and arming assertions prove each trap was set |
| `tools/fa7-door-check.js` (new, 16 checks in Chromium) | pass — no SQLSTATE on screen; the existing person is named; the tick is offered; typed values survive; **nothing on the page points at record 0**; acknowledging leaves **two** records, not one |
| Mutation test | one `$isEdit` switch put back to `$ins` — E8 and U3d both fail; restored, both pass |
| Full regression, SQLite and MariaDB (authoritative) | see commit message |

One existing test had to be strengthened rather than satisfied:
`test_m11_ux_consolidation.php`'s rule *"no SQL error text reaches a user"*
asked whether the word `SQLSTATE` appeared anywhere in a 10,000-line file, so it
failed on the **comment** explaining this defect. A rule that forbids naming a
defect in a comment discourages the documentation that stops it returning — and
would equally forbid a legitimate `catch` that *recognises* a SQLSTATE, which is
precisely the defensive code wanted here. It now strips comments and inspects
the 259 emitting calls instead. Stronger, not looser.

---

## F-A7-2 · Two doors create a candidate, and they agree
**Class: — · Severity: none — recorded as verified, not as a finding**

| Door | Entry point |
|---|---|
| A | `/candidate-new` (internal) |
| B | Public careers page application (`careers.php`) |

Both are legitimate and clearly different scopes. No divergence found.

---

## On the link counts the census raised

| Action | Screens linking | Verdict |
|---|---:|---|
| New report `/document-new` | 9 | **Legitimate.** One route, many contexts. |
| New test request `/call-new` | 8 | **Legitimate.** Same. |
| Add candidate | 4 | Legitimate |
| Raise requirement | 4 | Legitimate |

Part 37 explicitly asks for *"one obvious primary route, keep contextual
shortcuts"*. That is what these are. **No action needed** — and had this step
stopped at link-counting, it would have proposed tidying nine harmless links and
missed the SQLSTATE entirely.

---

## Summary

| ID | Finding | Class | Severity |
|---|---|---|---|
| F-A7-1 | Masters "add a person" bypasses the duplicate guard and shows a raw SQLSTATE | workflow | ~~**CRITICAL**~~ **FIXED** |
| F-A7-2 | Candidate creation: two doors, consistent | — | none |
| — | Multi-screen links to create routes | — | legitimate, keep |

**Corrections issued:** UX-A5's error finding was too comfortable; the business
UAT playbook's DUP-003 is wrong and must be amended before the owner runs it.

**No product decision required.** F-A7-1 needed a code fix using helpers that
already existed, and has been made. The UAT playbook's DUP-003 correction block
has been lifted: the test may now be run as written.
