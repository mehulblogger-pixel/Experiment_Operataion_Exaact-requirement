# Phase 6 · Batch 3 — Completion report

**Organisation representation, duplicate safety and cross-reference.**
Branch `claude/testing-branch-setup-0gqe8n` · baseline commit `42c2c42`.
PHP 8.4.19 · MariaDB 10.11.14 (authoritative) · SQLite 3.45.1 (supplementary).

**Status: complete and awaiting owner review. Batch 3 is NOT declared locked,
and Batch 4 has NOT been started.**

---

## 1 · In one page, for the business

A company can appear in EXAACT through several doors: a salesperson adds it, a
quotation is accepted, a lead is won, an agency contract is signed, or the
company registers itself on the public sign-up page. Before this batch, only
some of those doors asked "do we already know this company?" — and the one door
with **nobody standing behind it**, the public sign-up, asked nothing at all.

Now every door asks, and each one answers in the way that suits it:

* **The public page** refuses politely and tells the visitor nothing. It does not
  confirm that we know the company, it does not show a name, a code or a tax
  number, and it does not hand anybody an existing organisation. It invites them
  to ask their contact at the company, or to contact us.
* **An accepted quotation** attaches to the company we already have rather than
  creating a second one.
* **A won lead** stops and names the customer on file, so the salesperson can
  point the lead at it — because a person *is* there to decide.
* **A near-match on the name alone** is allowed through, because two real
  companies do share a name — and it is written down, so it can be reviewed.

An agency contract can now say which company it is with. That is a **link**, not
a merger: an agency row is a *contract* (fee, rate, guarantee, renewal) and a
company record is a *legal identity*. One company may hold several contracts over
time, and a contract with no link is perfectly valid. Nothing is ever guessed.

Each organisation has **one** primary contact, or none — never two, so "the
primary contact" stops being ambiguous. The same person may still be a contact
at several companies, because that is normal and true.

A single e-mail address can no longer hold **two active portal logins in the
same list**, which is what made people land in the wrong company's portal. The
database itself refuses it, so no screen can forget. One person may still hold a
client login *and* a supplier login, because those are separate doors.

Finally, the duplicates that are **already** in a workspace are now visible in
the existing identity report — described in plain words, each saying whether a
person needs to decide. **Nothing historical was changed, merged or deleted.**

## 2 · What was built

| Ref | Change | Verdict |
|---|---|---|
| **F1 · F2 · F3** | `/join` duplicate protection, neutral reply, one transaction, audited | CONNECT |
| **F5** | the quotation and lead writers call the shared guard | CONNECT |
| **F6** | the detector reports EXACT vs POSSIBLE, backward-compatibly | EXTEND |
| **F4** | `agencies.party_id` — optional, never inferred, never unique | MAP |
| **F8** | organisation creation and refusal are audited through `act_log()` | REUSE |
| **F9** | organisation states added to `identity_state_findings()` | EXTEND |
| **Q22** | 0 or 1 primary contact per organisation | EXTEND |
| **Q23** | one active account per address, per account list — a **generated** key | EXTEND |

No new engine, no new table, no new permission, no merge, no migration, no
fuzzy matching, no historical cleanup. One nullable column and one generated
column are the whole schema change.

## 3 · Owner decisions, and how each was honoured

| | Decision | Honoured by |
|---|---|---|
| **Q19** | agency `party_id`: nullable, additive, no automatic population, no inference, **no uniqueness** | D1–D12 prove no inference from an identical name **or** an identical GSTIN, and that two contracts may point at one company |
| **Q20** | extend the existing detector; EXACT = authoritative identifier, POSSIBLE = name; do not build a second engine | F1–F8 prove the original shape still works for existing callers; E2/E2b prove the confidences |
| **Q21** | `/join` on a match: no automatic organisation, **neutral reply**, controlled claim path, no new approval engine | A3–A10, M1–M2 — the reply is byte-identical for both kinds of match and discloses nothing |
| **Q22** | one primary contact; **do not** impose global contact-e-mail uniqueness | G-series — including the assertion that one address at two organisations **stays valid** |
| **Q23** | **do not** build a universal identity architecture; establish the real account boundary from the code, then constrain only there | the boundary was read out of the two login queries and enforced per table, per active account. H1–H4 |

## 4 · Evidence

| | SQLite | MariaDB (authoritative) |
|---|---|---|
| Batch tests `p6_batch3` | **121 / 0** | **121 / 0** |
| Full regression | *(see §6)* | *(see §6)* |
| Baseline before implementation | 25 passed, 31 failed | — |

Concurrency is real: separate OS processes released on a shared wall-clock
target. Every assertion reads the database back; a return code is never
evidence.

Documents produced: implementation plan · test results · security results ·
reconciliation results · mutation results · adversarial audit · this report.

## 5 · The three things I would want an owner to read

1. **A defect that would have broken a live feature.** The first version of the
   portal-invite guard recognised only staff authority, and would have stopped
   every client admin inviting a colleague. The batch's own tests did not catch
   it — the full regression did.
2. **A defect MariaDB has and SQLite hides.** A migration ran inside the
   registration transaction; MariaDB commits on DDL, so the first registration
   in a fresh process was reported as failed **for an account that existed**.
   Fixed at the cause. Test C4 reproduces it.
3. **One gap left open on purpose.** Two people registering the same company
   *name* in the same instant can still both get through. Closing that needs a
   uniqueness rule on `business_partners` — which is exactly what **Q1–Q18**
   leave open, and which cannot be added while historical duplicates exist. It
   is **detected**, not prevented, and it is written down rather than smoothed
   over.

## 6 · Still open — nothing here is claimed as done

* **Q1–Q18** remain open, untouched.
* **R20** (inspector duplicate detection) and **R30** (contact uniqueness) remain
  deferred; **R30**'s states are now *reported*, which is not the same as closed.
* The public sign-up form does not ask for a tax identifier, so its strongest
  check is dormant in production. **Recommended for owner decision.**
* No rate limiting on the public route.
* The detector reads the whole organisation table on every call — invisible
  today, a real cost at tens of thousands of organisations.
* `identity_state_findings()` reports; it never repairs. Deciding what to do
  about a historical duplicate stays a business decision.
