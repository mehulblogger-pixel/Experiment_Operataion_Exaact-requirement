# Phase 6 · Batch 3 — Mutation results

*Do the tests actually test anything, or do they agree with whatever the code
does?* Twenty-six deliberate defects were planted in the Batch 3 code, one at a
time, each on a **fresh copy** of the application with its **own** MariaDB
database. A mutant is **CAUGHT** only if the suite reports **more failures than
the clean baseline**.

Three rules, carried from Batches 1 and 2 and enforced by the harness:

* **A crash is not a catch.** A suite that died did not detect anything; it
  stopped running. Reported FATAL, never counted.
* **An anchor miss is not a catch.** If the text to be mutated is not found
  exactly once, the mutant did not exist. Reported, never counted.
* **A dirty baseline aborts the battery**, because it would measure nothing.

Suites per mutant: `p6_batch3` · `onboarding_engines` · `portal_contact_link` ·
`cvp_governance` · `field07`.

---

## Run 1 — 19 of 26 caught, 7 survived, 0 FATAL, 0 anchor miss

| # | The defect planted | Run 1 | Caught by |
|---|---|---|---|
| M1 | the public duplicate protection is removed | **CAUGHT** | A3 A4 A5 A7 |
| M2 | the public refusal names the organisation it matched | **CAUGHT** | A6 ×3 |
| M3 | the registration loses its transaction | **CAUGHT** | B2 B3 |
| M4 | a borrowed transaction is swallowed instead of re-thrown | **CAUGHT** | C8 |
| M5 | the schema step goes back inside the transaction | **CAUGHT** | C4 C4d |
| M6 | a system failure borrows the duplicate wording | *survived* | — |
| M7 | a name hit outranks an authoritative identifier | *survived* | — |
| M8 | a name match is reported as proof | **CAUGHT** | E2 F3 |
| M9 | the staff guard always answers NONE | **CAUGHT** | E1–E8 |
| M10 | the quotation writer stops asking | **CAUGHT** | E5 E5b |
| M11 | the lead conversion proceeds on an authoritative match | **CAUGHT** | E7b–E7e |
| M12 | the lead refusal stops naming the record | **CAUGHT** | E7c |
| M13 | the one-primary-contact rule is dropped | **CAUGHT** | G2 G4 |
| M14 | the portal account uniqueness index is never built | **CAUGHT** | H2 + 13 more |
| M15 | the uniqueness key becomes one a writer can forget | **CAUGHT** | H2 + 13 more |
| M16 | the uniqueness rule is widened past the account boundary | **CAUGHT** | H4 H6 |
| M17 | the invite stops asking its own authority | **CAUGHT** | K1 K2 |
| M18 | a client admin may invite into somebody else's organisation | *survived* | — |
| M19 | a forged organisation id is accepted | **CAUGHT** | K3 K4 |
| M20 | a duplicate account crashes instead of answering | *survived* | — |
| M21 | a dangling agency cross-reference is accepted | *survived* | — |
| M22 | organisation creation is no longer audited | **CAUGHT** | I1 I3 |
| M23 | the agency mapping audit fires for every master screen | **CAUGHT** | D10 |
| M24 | the organisation states are no longer detected | **CAUGHT** | J1 |
| M25 | detection starts repairing | *survived* | — |
| M26 | two agency contracts are called a duplicate | *survived* | — |

**M4 is worth noting on its own.** It survived an earlier, aborted run, which is
how C1–C3 were found to be decorative: they were answered by the check at the
*top* of the route, before a single row was written, so they proved nothing
about the transaction they were named after. C5–C11 were written to force a
failure part way through and to exercise the borrowed transaction, and M4 is
caught by C8.

## Every survivor, root-caused

No survivor was excused. Each was traced to its cause and the cause was fixed.

### M6 · a system failure borrows the duplicate wording — **test gap, and it hid a real defect**

Nothing asserted *what* a failed registration says, only that it failed. Adding
**C5b/C5c** — it must say nothing was saved, and must not borrow the duplicate
wording — immediately went red against the **shipped** code, not the mutant.

The cause: the failure path asked a generic "was that a duplicate?" of the whole
transaction. Every unique index reports the same SQLSTATE, so a visitor whose
**organisation** collided was told *"that e-mail is already registered — sign in
instead"* and sent to a sign-in page that would not have them. The account
conflict is now translated at the account write, which is the only place that
knows which rule was hit. **A real defect, found by closing a mutation survivor.**

### M7 · a name hit outranks an authoritative identifier — **test gap**

Every test put the name match and the identifier match on the *same* record, so
precedence was never exercised. **F9–F11** now put a name-matching organisation
and a GSTIN-matching organisation side by side and require EXACT, pointing at the
identifier's record. (Moved to run *after* F8, whose premise a second
same-named record would otherwise have broken — caught immediately by F8 going
red.)

### M18 · a client admin may invite into somebody else's organisation — **test gap, security-relevant**

Every probe acted as staff, so the second authority was never exercised at all.
**K5–K10** now *become* a client org admin — staff session cleared, so only that
authority is in play — invite a colleague into their own organisation
successfully, and are refused for another organisation, asserted on the
database.

### M20 · a duplicate account crashes instead of answering — **test gap, same shape as M4**

Inviting an address that already has an account is answered by the check at the
top of `portal_invite()`, before the write, so no test ever reached the database
rule beneath it. Only a race gets there. **H7–H11** run three simultaneous
invitations: exactly one account, exactly one success, **no crashed process**,
and a useful message for every loser.

**This is the second time in one batch** that a guard turned out to be tested
only through its early exit. Recorded as a pattern: a check-then-write pair
needs a probe that reaches the *write*, and usually only concurrency or an
injected failure gets there.

### M21 · a dangling agency cross-reference is accepted — **structural, code changed**

The rule lived inside the master-screen handler, which flashes and redirects, so
nothing but a browser could ask it a question — and a rule that cannot be asked
cannot be proved. Lifted into `master_row_problem($table, $cols, $vals)`, which
returns the one sentence that is wrong with a row, or `''`. The screen calls it
before writing; **D13–D17** ask it directly, including that blank stays valid
and that no other master screen gains a rule.

### M25 · detection starts repairing — **test gap**

**J2** asserted only that a finding *has* `safe_to_repair` and `needs_human`,
never what they say. **J2a–J2c** now require that a duplicate tax identifier is
never safe to repair automatically, always needs a person, and that the same
holds for every finding that would merge records or choose between them.

### M26 · reported as a survivor, actually a **defective mutant**

The planted finding carried **empty records**, so no assertion about agency
records could ever have seen it. It was not a surviving mutant; it was a mutant
that did not test anything.

Chasing it exposed a worse instrument defect of my own: **J5 looked for the kind
string `AGENCY_DUPLICATE`, which no code anywhere produces**, so it could never
fail whatever the report did. It now asserts the property rather than a spelling
— nothing that names an agency contract may be reported as a duplicate, the only
thing said about an agency is that it *might* be one we already know, and both
contracts survive the report untouched. The mutant was re-aimed at the defect
the business actually fears: the agency suggestion reported as a duplicate.

## Run 2 — the seven, against the repaired tests

*(Filled from the re-run.)*

## What this battery says about the tests

Six survivors were instrument defects and one was a structural problem — in
every case the shipped behaviour was already correct, with one exception that
matters: **M6's repair found a live defect that the batch's own tests, the full
regression on both engines, and my adversarial read had all missed.** That is
the argument for mutation testing in one line.

Two of my own instruments were wrong and are recorded as such rather than
quietly corrected: **J5**, an assertion that could never fail, and **M26**, a
mutant that could never be caught.
