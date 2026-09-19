# Phase 6 · Batch 3 — Test results

**Environment** · PHP 8.4.19 · SQLite 3.45.1 · MariaDB 10.11.14
**Branch** `claude/testing-branch-setup-0gqe8n` · **Baseline commit** `42c2c42`
**Harness** `php tests/run.php [filter]` — one process, one shared throwaway
database, files included alphabetically.

Every assertion reads the result back **from the database**. A return code is
never accepted as evidence.

---

## 1 · Baseline — recorded BEFORE any product code changed

`php tests/run.php p6_batch3` against unmodified code:

```
RESULT: 25 passed, 31 failed
```

Thirty-one red assertions, each naming a behaviour the batch had to produce. The
tests were written first, against the code as it stood; nothing was adjusted to
match what the code already did. (Two later corrections to the *tests* are
recorded in §5 — both were my errors, and both made the test stricter, not
weaker.)

## 2 · Final — both engines

| Suite | SQLite | MariaDB (authoritative) |
|---|---|---|
| `p6_batch3` (batch tests) | **189 passed, 0 failed** | **189 passed, 0 failed** |
| **Full regression** | **12 649 passed, 0 failed** | **12 653 passed, 0 failed** |

Taken from the final application tree `8de9607`. An earlier run of this same
gate was **not** clean — `C8` and `C9` failed on MariaDB, exposing the
borrowed-transaction defect described in §5a of the completion report. It was
reported rather than repaired mid-run, fixed on the owner's decision, and all
four measurements were re-taken.

The two totals differ because a handful of assertions are engine-specific
(driver behaviour, generated-column support); no assertion is skipped to make an
engine pass.

## 3 · What the assertions cover

| § | Subject | Proves |
|---|---|---|
| **A** | public `/join` duplicate protection | a new company still registers · an EXACT tax-identifier match is refused and **nothing** is written · no portal account is created either · the refusal discloses no name, identifier or id · an existing organisation cannot be re-registered by name · a *similar but different* company is not blocked |
| **B** | `/join` concurrency — **real processes** | three simultaneous registrations of one company *from one address* leave exactly **one** partner, **one** marketplace organisation and **one** account · exactly one process reports success · **no process crashes** · every loser is told something useful. (The case this does **not** cover — two different addresses registering one *name* in the same instant — is measured and reported in the adversarial audit, finding **A5**.) |
| **C** | `/join` transaction | an address already taken is refused before a row is written (C1–C3) · **a failure part way through** — forced with a temporary unique index, so step 1 has already written — leaves no orphan party, organisation or account (C5–C7) · inside a caller's transaction the failure is **re-thrown**, and the callee neither commits nor rolls back what it borrowed (C8–C11) · **C4** the first registration in a fresh process succeeds, and what it *reports* and what it *wrote* agree |
| **D** | agency cross-reference | the column exists · creating an agency **never** infers a party, not from an identical name and not from an identical GSTIN · **two** contracts may point at one organisation · an unmapped agency is valid · a person can set it on the agency screen · linking is audited · **no other master screen** writes organisation audit entries · no commercial field changes · Recruitment reads what it read before |
| **E** | the staff writers | the shared guard exists · a name match is **POSSIBLE**, a tax identifier is **EXACT** · the staff message names the record · an accepted quotation attaches to an existing organisation instead of creating a second · a name look-alike is allowed **and recorded** · a lead conversion on an existing tax identifier is refused, names the record, creates nothing and **leaves the lead untouched** |
| **F** | detector backward compatibility | the original `[row][by]` shape is unchanged · it still finds the right organisation · GSTIN, PAN and TAN are each EXACT · no match still returns `null` · the exclude-id argument still works |
| **G** | one primary contact | setting a new primary demotes the old one · 0 or 1 primary, never two · **the same contact e-mail at two organisations stays valid** |
| **H** | the portal account boundary | a second **active** account for one address is refused in the same account list · a **raw** insert that bypasses the application is refused too · deactivating releases the address · a client account and a vendor account for one person coexist |
| **I** | organisation audit | creation writes an attributable entry with a registered kind · a **refused** public registration is audited against the organisation it matched |
| **J** | historical detection | two organisations sharing a tax identifier are reported · the finding states what, which records, why, and whether a person is needed · **detection changes nothing** · two agency contracts for one organisation are **not** called a duplicate |
| **K** | authorisation and forged ids | an actor without the right cannot invite, and **no account is created** · a forged organisation id is refused and nothing is written |
| **L** | tenant isolation | the detector never reaches beyond the workspace (one database per tenant; isolation is structural) |
| **M** | the public route under attack | both kinds of match read the **same sentence** — the reply is not an oracle · a visitor cannot award itself a role, a status or permissions through extra form fields · the account belongs to the organisation actually created, not one it named · an identifier given at registration is **stored**, so the same company under another name is refused next time · a name containing SQL is stored as data |

Concurrency is real: `tests/_p6b3_worker.php` processes are launched with
`proc_open` and released on a shared wall-clock microsecond target, with every
one-time cost paid **before** the barrier. Nothing is simulated sequentially.

## 4 · Regressions found by the full suite, and fixed

**13 failures — `portal_invite()` recognised only one of its two authorities.**
The function serves both the staff portal register and a client's own admin
inviting a colleague. The first guard asked only for staff authority, which
would have stopped every client admin in the product. Fixed by asking for either
authority, with the client admin bounded to their **own** organisation at the
function itself. `test_cvp_governance` went green with no change to the test.

**1 failure — `test_portal_contact_link` had no authorised actor.** Same class as
the twenty-two in Batch 1: the test called a function that now asks its own
authority. Fixed with the `t_as_admin()` / `t_as_nobody()` fixture. **No
assertion was weakened.**

**1 crash — the same test inserted a second active account for one address and
then never read it.** The test's own comment already said the row was redundant.
It was removed; nothing that asserts anything changed.

**C1–C3 were weaker than their names claimed.** They were answered by the check
at the *top* of the route, before any row was written, so they proved nothing
about the transaction. Found by mutation **M4**, which survived them. C5–C11 were
added to force a failure part way through and to exercise the borrowed
transaction. This is recorded rather than quietly corrected.

**1 MariaDB-only failure — the implicit-commit defect.** Root-caused rather than
worked around; see the implementation plan and the adversarial audit. Test
**C4** now reproduces it.

## 5 · Corrections to my own tests, recorded honestly

* **E2** asserted that a name match was `EXACT`. Owner decision **Q20** reserves
  EXACT for an authoritative identifier. My assertion was wrong; it was
  corrected, and **E2b** / **E2c** were added to prove the tax-identifier case
  and the staff wording separately.
* **Section D** crashed at baseline because it read a column the batch had not
  yet created. It was guarded so the baseline could be recorded at all — the
  assertions themselves were unchanged, and all now run.

## 6 · Deploy checksum

`php tools/make_deploy_check.php` was re-run after the final source change;
`deploy-check.php` is in the commit, and the suite's own checksum test passes on
both engines.
