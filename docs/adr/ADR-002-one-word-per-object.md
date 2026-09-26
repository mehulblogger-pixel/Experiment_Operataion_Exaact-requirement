# ADR-002 — One word per object, and every screen asks for it

**Status:** DECIDED · 2026-09-26
**Supersedes nothing.** Narrows the wording half of the M4 terminology lock
(`tests/test_m4_correction.php`, section A) and the UX-B4 terminology work.

---

## The question the owner asked

> "Image 1 is hiring request and Image 2 is requisition — are they both different,
> as they both have different screens?"

They are different objects. But the owner could not tell, and that is not a
training problem — the screens genuinely disagreed with each other.

## What was actually wrong

Three separate faults, which together read as "the system has two of everything".

**1 — The Recruitment Command Centre typed the word instead of asking for it.**
Nine places on the busiest recruitment screen printed the literal word
"requirement", while every register printed "Requisition". Same database record,
two names, on two screens a user moves between in one click.

**2 — Because it typed the word, Admin → Terminology did nothing there.**
A workspace that renamed the object got its choice honoured on the registers and
ignored on the Command Centre. The setting was half-real, which is worse than
absent: the owner reasonably concludes the rename failed.

**3 — "Requirement" was the wrong word anyway, in two directions at once.**
   * The application already has `cx_requirements` — a **client-posted
     marketplace requirement**, a different object with a different lifecycle.
   * The requisition's own fields are requirements: *certificates required*,
     *minimum qualification*. So the record and its contents shared a word.

## The decision

1. **Every user-facing recruitment screen names the object by asking the
   terminology engine** — `T()/TP()/Tl()/Tlp()/TH()/THP()/T_NEW()/T_REG()` —
   and never by typing the word. A hard-coded name is now a test failure.

2. **The shipped default is unchanged: "Requisition".** No existing workspace
   sees a word move under it. "Requisition" is also unambiguous: it could never
   be read as a marketplace requirement, so `hreq_label()` leaves it bare.

3. **The staffing packs (`recruitment`, `manpower`) now say "Job Order".**
   That is what a staffing agency calls this record in the market, and it
   collides with nothing. They previously said "Requirement" — the collision in
   fault 3 above, shipped as a default for exactly the customers most likely to
   hit it.

4. **A drift test.** `tests/test_recruit_terminology.php` fails if any
   recruitment screen prints "Requisition" or "Requirement" as a literal, if a
   pack calls two different objects the same word, or if a rename fails to reach
   the helpers the screens read. This is the control that stops fault 1
   happening again; care did not stop it the first time.

## What this does NOT change

* **No permission, role or lifecycle is touched.** This is wording only.
  `docs/02-permission-matrix.md` and `docs/03-object-lifecycles.md` are unchanged
  and unaffected.
* **No table, column or route is renamed.** `hiring_requests` and `requisitions`
  remain two tables joined by one nullable column, exactly as M4 left them.
* **The three objects remain three objects**: Hiring Request (the ask),
  Requisition (the execution record), Marketplace Requirement (`cx_requirements`,
  a client-posted demand). `hreq_label()` still qualifies a workspace's word when
  the workspace picks the ambiguous one.

## Why not merge the two screens instead

Because they are two objects with two lifecycles and two permission sets. A
hiring request is a business ask that needs approving; a requisition is the
approved work being executed against. Merging them would have destroyed the
approval boundary that ADR-001 was decided to protect. The confusion was caused
by inconsistent *wording*, and wording is what was fixed.

## Consequences for existing tests

Two tests borrowed the recruitment pack as a convenient source of the ambiguous
word "Requirement". Their subject is `hreq_label()`'s qualification behaviour,
not the pack's contents, so the fixtures now state the ambiguous word outright:

* `tests/test_m4_correction.php` A3/A4 — sets the override directly.
* `tests/test_term_recruitment_pack.php` — now asserts "Job Order".

`tests/test_b2_navigation_cc.php` had three premises that the owner's later
decisions had overtaken, and they were corrected rather than the code weakened:

* its band scraper could not see a heading that resolves a word from the engine;
* `/hiring-request` is a deliberate second destination, required by ADR-001;
* D5 asserted the page declares **no** preferred path, which was right while
  ADR-001 was open. The ADR is decided and configurable, so the assertion is now
  that the page asks the policy and states whichever sentence is true.

## Verification

* `tests/test_recruit_terminology.php` — 51 assertions.
* Three mutations, all caught: the engine ignoring a saved override; a pack
  applying without reaching the current page; a pack reverting to "Requirement".
* Rename verified in the running application, end to end, through the real
  setting key (`terms`): renaming to "Vacancy" gives 10 occurrences on
  `/recruitment-cc` and 0 of "requisition"; the staffing pack gives 8 of
  "Job Order"; clearing the override restores "Requisition".
* Full regression green on **both** engines — SQLite 14,319 and MariaDB 14,322.

---

## Appendix — a PHP trap this work exposed, now permanently guarded

Writing this feature hit the same silent bug three times, so it earned a test of
its own: `tests/test_php_close_tag_in_comment.php`.

PHP ends a `//` comment at a close tag, not only at the newline. A comment that
quotes one — while explaining an echo tag or a regular expression — ends PHP
mode, and the rest of that comment **plus everything below it in the file**
becomes page output. In a view that prints template source, queries included, to
the browser: information disclosure, not a typo. `php -l` reports the file clean,
because it is valid PHP; it simply does something else entirely.

The test scans all 1,217 shipped PHP files. It distinguishes the bug from the
idiomatic `<?php // comment [close tag]` form by whether the close-tag token
swallowed the newline — text still to come on that line is the leak. It is armed
both ways: it catches a planted offender and names its line, proves `php -l`
misses it and that running it really leaks, and leaves a close tag in a string,
in a block comment, and the idiomatic form alone.
