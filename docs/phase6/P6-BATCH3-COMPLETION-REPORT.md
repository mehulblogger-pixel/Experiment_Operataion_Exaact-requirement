# Phase 6 · Batch 3 — Completion report

**Organisation representation, duplicate safety and cross-reference.**

| | |
|---|---|
| Branch | `claude/testing-branch-setup-0gqe8n` |
| Baseline commit | `42c2c42` |
| **Final commit measured** | **`8de9607`** — the application under test. `b59134a` adds documents only; `git diff 8de9607 b59134a -- phpapp` is empty |
| Engines | MariaDB 10.11.14 **(authoritative)** · SQLite 3.45.1 (supplementary) · PHP 8.4.19 |

**Status: implementation and validation complete. Batch 3 is NOT declared
locked, and Batch 4 has NOT been started. Awaiting owner acceptance.**

---

## 1 · In one page, for the business

A company can appear in EXAACT through several doors: a salesperson adds it, a
quotation is accepted, a lead is won, an agency contract is signed, or the
company registers itself on the public sign-up page. Before this batch, only
some of those doors asked "do we already know this company?" — and the one door
with **nobody standing behind it**, the public sign-up, asked nothing at all.

Now every door asks, and each answers in the way that suits it:

* **The public page** refuses politely and tells the visitor nothing. It does
  not confirm that we know the company, it shows no name, code or tax number,
  and it hands nobody an existing organisation. It invites them to ask their
  contact at the company, or to contact us.
* **An accepted quotation** attaches to the company we already have rather than
  creating a second one.
* **A won lead** stops and names the customer on file, so the salesperson can
  point the lead at it — because a person *is* there to decide.
* **A near-match on the name alone** is allowed through, because two real
  companies do share a name — and it is written down, so it can be reviewed.

An agency contract can now say which company it is with. That is a **link, not a
merger**: an agency row is a *contract* (fee, rate, guarantee, renewal) and a
company record is a *legal identity*. One company may hold several contracts
over time, and a contract with no link is perfectly valid. Nothing is guessed.

Each organisation has **one** primary contact, or none — never two, so "the
primary contact" stops being ambiguous. The same person may still be a contact
at several companies, because that is normal and true.

A single e-mail address can no longer hold **two active portal logins in the
same list**, which is what made people land in the wrong company's portal. The
database itself refuses it, so no screen can forget. One person may still hold a
client login *and* a supplier login, because those are separate doors.

Finally, the duplicates **already** in a workspace are visible in the existing
identity report, in plain words, each saying whether a person must decide.
**Nothing historical was changed, merged or deleted.**

---

## 2 · Proven closed

### Findings

| Finding | Subject | Status |
|---|---|---|
| **F1** | `/join` duplicate protection | **CLOSED** |
| **F2** | `/join` concurrency | **CLOSED within the approved uniqueness boundary** — see limitation 1 |
| **F3** | `/join` transaction | **CLOSED** |
| **F4** | agency cross-reference | **CLOSED** |
| **F5** | CRM and lead writers call the guard | **CLOSED** |
| **F6** | exact / possible detector | **CLOSED** |
| **F7** | one primary contact | **CLOSED** — after the primary-contact fix and its own mutants M27/M28 |
| **F8** | organisation audit | **CLOSED** |
| **F9** | historical state detection | **CLOSED** |

### Owner decisions

| Decision | Subject | Status | Proved by |
|---|---|---|---|
| **Q19** | agency `party_id` — optional, never inferred, never unique | **CLOSED** | D1–D17: no inference from an identical name **or** an identical GSTIN; two contracts may point at one company; unmapped stays valid; a dangling reference is refused; no other master screen gains a rule |
| **Q20** | extend the existing detector; EXACT = authoritative identifier, POSSIBLE = name | **CLOSED** | F1–F12: the original `[row][by]` shape still serves every existing caller, and an identifier outranks a name on an **earlier** record |
| **Q21** | neutral public boundary and claim path, no new approval engine | **CLOSED** | A3–A10, M1–M2: byte-identical refusal for both kinds of match, disclosing nothing |
| **Q22** | one primary contact; **no** global contact-e-mail uniqueness | **CLOSED** | G1–G11, including that one address at two organisations stays valid, and that a demote which merely *reports* success still leaves one primary |
| **Q23** | no universal identity architecture; establish the real account boundary from the code | **CLOSED** | H1–H11: the boundary was read out of the two login queries and enforced per table, per active account — a client login and a vendor login for one person coexist |

### Gates

| Gate | Status | Evidence |
|---|---|---|
| **Gate 1** — adversarial mutation validation | **CLOSED** | **34 mutants · 33 killed · 1 proved equivalent · 0 genuine survivors · 0 FATAL · 0 anchor misses** |
| **Gate 2** — primary-contact integrity | **CLOSED** | fixed at the cause; failure injected for real with a trigger; its own mutants M27/M28 both killed |
| **Gate 3** — final regression, four runs | **CLOSED** | 189/0 · 189/0 · 12 649/0 · **12 653/0 authoritative** |

### Gate 3 — **CLOSED**

The authoritative MariaDB full regression *did* find a real defect in Batch 3's
own code on the first attempt. It was reported rather than repaired mid-run, the
owner chose the remedy, and all four measurements were re-taken from the fixed
tree. §5a records what it was and how it was closed.

---

## 3 · Gate 1 — mutation evidence

**28 mutants · 28 killed · 0 survivors · 0 FATAL · 0 anchor misses**, from the
final tree. A crash is never counted as a catch, an anchor miss is never counted
as a catch, and a dirty baseline aborts the battery.

| # | Defect planted | Final |
|---|---|---|
| M6 | a system failure borrows the duplicate wording | **CAUGHT** (C5b C5c) |
| M7 | a name hit outranks an authoritative identifier | **CAUGHT** after the precedence probe was re-aimed (F9–F11) |
| M13 | the one-primary-contact rule is dropped | **CAUGHT** after re-aiming — its anchor had moved when the Gate-2 fix rewrote that line |
| M18 | a client admin may invite into another organisation | **CAUGHT** (K9 K10) |
| M20 | a duplicate account crashes instead of answering | **CAUGHT** (H10) |
| M21 | a dangling agency cross-reference is accepted | **CAUGHT** (D15) |
| M25 | detection starts repairing | **CAUGHT** (J2a–J2c) |
| M26 | an agency contract reported as a duplicate organisation | **CAUGHT** after J5's premise was corrected (J5a J5 J5b) |
| M27 | a failed demote is ignored and the second primary written | **CAUGHT** (G8 G9) |
| M28 | the demote trusts the UPDATE instead of reading it back | **CAUGHT** (G10 G11) |
| M1–M5, M8–M12, M14–M17, M19, M22–M24 | *(see the mutation results)* | **CAUGHT** |

Full detail, including why M28 is **not** an equivalent mutant, is in
`P6-BATCH3-MUTATION-RESULTS.md`.

## 4 · Gate 2 — primary-contact integrity

`partner_contact_clear_primary()` used to swallow a failed demote, so the new
primary could be written beside the old one — the exact state Q22 forbids. It
now guards the missing-column case, reports failure instead of hiding it, and
**reads back** that no other primary survives; `partner_contact_add()` refuses
rather than returning a false success.

The fix was then attacked with **its own** mutants rather than the old ones
re-pointed — M27 (ignore the failure and write anyway) and M28 (trust the UPDATE
instead of reading back) — and both are caught.

**M28 was nearly dismissed as equivalent.** The claim "if the UPDATE succeeded,
the demote happened" was tested instead of assumed, and it is false on **both**
engines: a trigger can leave the old primary standing while the statement still
returns `true` (SQLite `AFTER UPDATE`, MariaDB `BEFORE UPDATE … SET NEW.…`).
G10/G11 install exactly that condition.

## 5 · Gate 3 — final regression, all four measurements

Measured on the application tree of `8de9607`. The harness (`tests/run.php`)
requires every `tests/test_*.php` and prints one `RESULT` line; **it has no skip
facility and emits no duration**, so SKIPPED is structurally 0 and no duration is
invented here. FATAL is detected by a missing `RESULT` line or a PHP fatal in the
log — both were checked and both are zero.

| # | Run | TOTAL | PASSED | FAILED | SKIPPED | FATAL |
|---|---|---:|---:|---:|---:|---:|
| 1 | Batch 3 battery · SQLite | 189 | **189** | **0** | 0 | 0 |
| 2 | Batch 3 battery · MariaDB | 189 | **189** | **0** | 0 | 0 |
| 3 | **Full regression · SQLite** | 12 649 | **12 649** | **0** | 0 | 0 |
| 4 | **Full regression · MariaDB (authoritative)** | 12 653 | **12 653** | **0** | 0 | 0 |

508 test files executed in each full run. The engines do not disagree anywhere.

```
=== GATE 3 (final) · application tree 8de9607 ===
--- 1of4 batch SQLite ---    RESULT: 189 passed, 0 failed
--- 2of4 batch MariaDB ---   RESULT: 189 passed, 0 failed
--- 3of4 full SQLite ---     RESULT: 12649 passed, 0 failed
--- 4of4 full MariaDB ---    RESULT: 12653 passed, 0 failed
```

## 5a · The Gate 3 defect — found, reported, then closed

| | |
|---|---|
| **Found by** | the authoritative MariaDB **full** regression (C8, C9) |
| **Module** | `lib/connect_org.php` → `connect_org_register()` |
| **Invisible to** | SQLite (its DDL is transactional) **and** to the batch battery alone |
| **Handling** | reported, **not repaired mid-run**; owner chose the remedy; fixed in `8de9607` |

**What it was.** Taking the schema steps before the route's *own* transaction
stopped it destroying that one. It did not stop the same steps running inside a
**caller's** transaction — and MariaDB commits implicitly on any DDL, the no-op
`CREATE TABLE IF NOT EXISTS` kind included. The caller's transaction ended
unannounced, and the route then believed it owned what it had just destroyed.

**How it was closed — one rule, applied where the DDL actually is:**

* `connect_org_prepare_schema($caps)` prepares everything this route can reach
  when it owns the connection. Inside a borrowed transaction it does **nothing**:
  it only reports whether what is needed is already there, and refuses *before a
  single row is written* if it is not — safe precisely because nothing has been
  written.
* `act_migrate()` and `connect_cap_migrate()`, reached through other people's
  functions, carry the same rule at their own door and deliberately do **not**
  mark themselves done when they decline, so the real preparation still happens
  later, outside.

**A stricter first attempt was wrong, and is recorded.** It proceeded only when
*this function* had done the warming. The mutation baseline came back **dirty —
7 failures in `onboarding_engines`** — a legitimate existing caller that wraps
the route in its own transaction. Safety that breaks a working feature is not
safety, and it was a dirty baseline, not review, that caught it.

**The transaction contract is untouched.** A failure after the writes begin is
still re-thrown; the callee still neither commits nor rolls back what it
borrowed. **C8 and C9 are unchanged** — only C8's *precondition* was made valid,
which is what a real caller must do.

**Twelve cases** now cover the six required combinations and more: warm schema,
stale schema, success, failure, borrowed transaction, function-owned
transaction, the rule at each of the two other doors, and a table that is
genuinely absent (C12–C25). Mutation-tested by M5 and M29–M34.

## 6 · Protected regression areas

Attributed from the **existing** canonical suite — no duplicate regression suite
was created for this table. Areas overlap deliberately, so the rows sum to more
than the total; the TOTAL row is the authoritative count of the single run.

| Area | Files | SQLite passed / failed | MariaDB passed / failed |
|---|---:|---:|---:|
| Operations | 52 | 625 / 0 | 625 / 0 |
| Reporting | 20 | 405 / 0 | 405 / 0 |
| Money | 43 | 659 / 0 | 659 / 0 |
| Workforce | 12 | 177 / 0 | 177 / 0 |
| Marketplace | 77 | 1441 / 0 | 1441 / 0 |
| Recruitment | 34 | 958 / 0 | 958 / 0 |
| Phase 4 allocation | 5 | 924 / 0 | 927 / 0 |
| Phase 5 KPI / seats | 2 | 152 / 0 | 152 / 0 |
| **Phase 6 Batch 1** | 1 | 115 / 0 | 115 / 0 |
| **Phase 6 Batch 2** | 1 | 78 / 0 | 78 / 0 |
| **Phase 6 Batch 3** | 1 | 189 / 0 | 189 / 0 |
| SaaS entitlement / permissions | 27 | 1112 / 0 | 1112 / 0 |
| Tenant isolation | 11 | 251 / 0 | 251 / 0 |
| Organisation / account integrity | 19 | 403 / 0 | 403 / 0 |
| **TOTAL (all files)** | **508** | **12 649 / 0** | **12 653 / 0** |

**Every protected area is clean on both engines**, Batch 1 and Batch 2 included.

## 7 · F1–F9 closure matrix

*Closed means the evidence demonstrates closure, not that code exists.*

| ID | Finding | Implementation | Evidence | Tests | Disposition |
|---|---|---|---|---|---|
| **F1** | `/join` creates organisations with no duplicate check | detector called before any write; neutral refusal; refusal audited | a new company still registers; an EXACT match creates nothing, not even an account; a similar-but-different company is not blocked | A1–A10, M1–M2 | **CLOSED** |
| **F2** | `/join` has no concurrency protection | settled by the database key on the account | 3 simultaneous registrations → 1 partner, 1 organisation, 1 account, 1 success, 0 crashes | B0–B6 | **CLOSED within the approved uniqueness boundary** (limitation 1) |
| **F3** | `/join` writes three tables unguarded | one transaction; Batch 2 borrowed-transaction contract; **and no schema work inside a transaction it did not open** | a failure part way through leaves no orphan; a failure inside a caller's transaction is re-thrown; stale schema never commits a borrowed transaction; a missing table is refused, not built | C1–C25 | **CLOSED** — see §5a |
| **F4** | `agencies` has no cross-reference | one nullable `party_id`, set by a person, never inferred | no inference from identical name or GSTIN; two contracts may share one organisation; dangling reference refused; no other master gains a rule | D1–D17 | **CLOSED** |
| **F5** | CRM and lead writers create blind | both call the shared guard | quotation attaches on a tax identifier; lead conversion refuses and names the record; lead untouched | E1–E8 | **CLOSED** |
| **F6** | the detector cannot say how confident it is | EXACT / POSSIBLE / NONE, backward-compatible | original shape preserved; identifier outranks a name on an earlier record | F1–F12 | **CLOSED** |
| **F7** | contact integrity | one primary; failed demote cannot produce two | demote failure injected with a trigger; a demote that *reports* success still leaves one primary | G1–G11, M13, M27, M28 | **CLOSED** |
| **F8** | no organisation creation is audited | `act_log()` reused, outside the transaction | creation and refusal both audited; no other master screen writes organisation audit | I1–I3, D9, D10 | **CLOSED** |
| **F9** | historical duplicates are invisible | organisation states added to the existing report | duplicates, look-alikes, unmapped and dangling references reported; detection changes nothing; agencies never called duplicates | J1–J5d | **CLOSED** |

## 8 · Q19–Q23 decision matrix

| Decision | What was decided | Implementation | Evidence | Status |
|---|---|---|---|---|
| **Q19** | agency → organisation is a **map, not a merge**: nullable, additive, no automatic population, no inference, **no uniqueness** | `agencies.party_id` + an optional picker on the agency screen; `master_row_problem()` refuses a dangling reference | D1–D17 — creating an agency never infers a party from an identical name **or** an identical GSTIN; two contracts may point at one organisation; unmapped stays valid; linking is audited; no commercial field changes | **CLOSED** |
| **Q20** | extend the existing detector; EXACT = authoritative identifier, POSSIBLE = name; **do not build a second engine** | `find_duplicate_partner()` gained `confidence`; `partner_find_or_problem()` wraps it for staff | F1–F12 — the `[row][by]` shape still serves every existing caller; an identifier outranks a name on an earlier record; a name alone is never proof | **CLOSED** |
| **Q21** | on a public match: no automatic organisation, **neutral response**, controlled claim path, **no new approval engine** | one sentence, identical for both confidences, disclosing nothing; refusal audited against the matched organisation | A3–A10, M1–M2 — no name, code, identifier or id disclosed; both kinds of match read byte-identically | **CLOSED** |
| **Q22** | 0 or 1 primary contact; **do not** impose global contact-e-mail uniqueness | demote-then-write, with the demote now honest and read back | G1–G11 — including that one address at two organisations **stays valid**, and that a failed or falsely-successful demote never yields two primaries | **CLOSED** |
| **Q23** | **do not** build a universal identity architecture; establish the account boundary from the code and constrain only there | boundary read out of both login queries; a database-**generated** key per account table | H1–H11 — a raw insert bypassing the application is refused; deactivation releases the address; one person may hold a client **and** a vendor account | **CLOSED** |

No decision was introduced or reinterpreted during Gate 3.

## 9 · Known limitations — retained, not solved

**Limitation 1 · simultaneous registrations of one company name.** Measured with
real concurrent processes: same name, different addresses, two at once → **2
organisations, both successful**. The name check is a read followed by a write
with nothing serialising the pair. Only a database uniqueness rule can settle it,
and that is what **Q1–Q18** leave open. **No `UNIQUE(normalised_company_name)`
was invented** — legitimately distinct companies share names, and such a rule
would refuse real customers.

> **Detected but not prevented under the currently approved organisation
> uniqueness model.** Reported as `PARTNER_POSSIBLE_DUPLICATE_NAME`.

**Limitation 2 · public `/join` does not collect a tax identifier.**
`views/ops/connect_join.php` asks for a name, a contact and capabilities. The
**EXACT** branch is therefore dormant on that route and the protection there is
name-based.

> **Exact tax-identifier protection is implemented, but its availability in
> public self-registration depends on that form collecting the identifier.**

**GSTIN collection was not added during Gate 3.** The code is ready for it —
M11/M12 register *with* an identifier and show the same company refused under
another name next time.

**Also left, and stated:** the e-mail-in-use reply still confirms an address has
an account (pre-existing); no rate limiting on the public route; the detector
reads the whole organisation table per call; the agency picker lists vendor
records; the state report never repairs.

## 10 · Instrument defects discovered

Four of my own instruments passed while proving nothing. All were found by
mutation, none by reading, and all are recorded rather than quietly corrected.

| Instrument | What was wrong | Found by |
|---|---|---|
| **C1–C3** | named after the transaction, but answered by the check at the *top* of the route, before a single row was written | M4 surviving |
| **J5** | looked for the finding kind `AGENCY_DUPLICATE`, **which no code anywhere produces** — it could never fail | M26 surviving |
| **M26** | the planted finding carried **empty records**, so no assertion about agency records could ever have seen it — a defective mutant, not a survivor | re-reading it after it "survived" twice |
| **precedence probe** | put the identifier's record **earlier** in the register, so the scan met the identifier first and answered correctly whatever the rule said | M7 surviving twice |

| **C1–C3** *(named again)* | the transaction test answered by the check at the top of the route | M4 surviving |
| **C25c** | read the very table its own mutant removes, so the mutant killed the suite — a FATAL, which is never a catch | M5 reporting FATAL |
| **the first Gate 3 rule** | stricter than it needed to be; refused a legitimate caller that wraps the route in its own transaction | a **dirty mutation baseline** (7 failures in `onboarding_engines`) |

> **An assertion without a valid subject or premise is not evidence.**

And the finding that justifies the whole exercise:

> **M6's repair found a live defect that the batch's own tests, the full
> regression on both engines, and my adversarial read had all missed** — a
> visitor whose *organisation* collided was told their e-mail was already
> registered and sent to a sign-in page that would not have them.

## 11 · Security and integrity confirmation

Evidence-based only. Anything that could not be evidenced is marked **NOT
ESTABLISHED** rather than claimed.

| Property | Status | Evidence |
|---|---|---|
| Tenant isolation | **HOLDS** | structural (one database per tenant); 11 isolation files, 251 assertions clean on both engines; the detector opens no second connection and switches no tenant (L1 L2) |
| Authenticated scope | **HOLDS** | K1–K10 |
| Account-boundary enforcement | **HOLDS** | H1–H11, including a raw insert that bypasses the application |
| No record-ID-as-authorisation | **HOLDS** | K3 K4; mutant M19 |
| No cross-tenant relationship | **HOLDS** | structural; Batch 1 proved it with two real tenant databases |
| No unauthorised organisation takeover | **HOLDS** | A3–A10; nothing is created and nothing is handed over |
| Neutral public duplicate messaging | **HOLDS** | A6, M1 M2 — byte-identical for both confidences |
| No automatic public claim or takeover | **HOLDS** | A4 A5 A8 A9 |
| Agency ≠ business partner | **HOLDS** | D2 D3 — no inference from name or GSTIN |
| Agency mapping remains explicit | **HOLDS** | D6–D9, D13–D17 |
| Suggestions do not map records | **HOLDS** | J5d — reporting an agency leaves `party_id` at 0 |
| One primary contact | **HOLDS** | G1–G11 |
| A failed demotion cannot allow two primaries | **HOLDS** | G8–G11 with the failure injected, plus mutants M27 M28 |
| No fuzzy matching | **HOLDS** | exact identifier or normalised-name equality only |
| No Person hub, no Organisation hub | **HOLDS** | no such table or module exists |
| No historical rewrite, no silent merge | **HOLDS** | J3 J4 J5c; nothing merged, deleted or back-filled |
| **Borrowed-transaction contract on `/join`** | **NOT ESTABLISHED** | **C8/C9 fail on MariaDB under a stale migration guard — see §5a** |

## 12 · Test statistics

| | |
|---|---|
| Baseline before implementation (batch file, unmodified code) | 25 passed, **31 failed** |
| Batch 3 assertions, final | **189** |
| Full suite, SQLite | **12 649 passed, 0 failed** (508 files) |
| Full suite, MariaDB (authoritative) | **12 653 passed, 0 failed** (508 files) |
| Mutants | **34 planted · 33 killed · 1 proved equivalent · 0 genuine survivors** |
| FATAL / anchor misses counted as catches | **0** — by rule, neither ever is |
| Skipped tests | **0** — the harness has no skip facility |
| PHP fatals | **0** in both full logs |
| Defects found by validation and fixed at the cause | **4** — dual authority · MariaDB implicit commit (own transaction) · duplicate wording on a system failure · **borrowed-transaction schema work** |
| Instruments found defective and corrected | **7** |
| Schema added, in total | one nullable column · one generated column per account table |

## 13 · Final disposition

> ## READY FOR OWNER ACCEPTANCE
>
> Gate 1 **CLOSED** · Gate 2 **CLOSED** · Gate 3 **CLOSED**

All four Gate 3 measurements are clean from the final application tree
`8de9607`, the authoritative MariaDB run included. Mutation stands at 34
planted, 33 killed and one **proved** equivalent. Every protected area is clean
on both engines, Batch 1 and Batch 2 among them. F1–F9 and Q19–Q23 are
reconciled. The two approved limitations are retained, unchanged and unsolved,
exactly as agreed.

**This is not a claim that Batch 3 is accepted.** Acceptance and locking are the
owner's, and Batch 4 has not been started.

Two things the owner should weigh before locking:

1. **The borrowed-transaction defect was found by the full MariaDB regression,
   not by 189 clean batch assertions.** That is the second time in this batch a
   clean battery said nothing about a real defect. The four-run gate earned its
   place.
2. **A defect exists outside this batch's scope**, found while capturing product
   screenshots: `lib/recruitpipe.php:529` closes its PHP block one line early, so
   a line of template code is printed on every candidate screen and the
   administrator's "Edit workflow" link never renders. It is **not** Batch 3's,
   it is **not** fixed, and it is raised here so it is not lost.
