# Phase 6 — Duplicate and Identity Rules

*How the system decides that two records are the same business thing — and what it
is allowed to do about it.*

**Sources:** `P6-PREIMPLEMENTATION-AUDIT.md` (`69e2539`),
`P6-PERSON-REPRESENTATION-ADDENDUM.md` (`20971e9`),
`P6-CANONICAL-DOMAIN-MODEL.md` (`cdd86e2`, §3 locked).

**Scope:** documentation only. No PHP, JavaScript, HTML, CSS, database, schema,
migration, route, API, permission or workflow is changed. **Nothing from R1–R17 is
implemented.** No open question is answered.

---

## The rule that governs every class below

> ## DETECT → SUGGEST → CONFIRM → LINK → AUDIT
>
> **No silent physical merge. Ever.**

A duplicate is **detected** by deterministic evidence, **suggested** to a person,
**confirmed** by somebody authorised, **linked** as an explicit relationship, and
**audited** so the decision can be read back and undone.

**Where a physical merge would ever be unavoidable, Phase 6 does not implement it.**
§20 of the master prompt is explicit, and this document holds the line:
**prefer CONNECT / MAP.**

---

## A finding this document produced: a fifth identity mechanism

While establishing what already detects duplicates, one more mechanism surfaced
that neither the §2 audit nor the addendum recorded.

**`candidates.person_ref`, with `person_key()` and `person_link_rows()`
(`lib/recruit.php`).**

```php
// The stable person key for a candidate row: an explicit link if set, else the
// last 10 digits of the mobile, else the lower-cased e-mail. '' when unknowable.
function person_key($cand)  →  'ref:…' | 'mob:##########' | 'em:…' | ''

// Link a set of candidate rows as one person: they all take a single shared
// person_ref … Additive — only stamps person_ref, never merges or deletes an
// application.
function person_link_rows(array $ids)
```

**Recruitment already has a person concept.** `person_ref` groups several
candidate rows as one human; where it is unset, `person_key()` falls back to
mobile, then e-mail. `person_link_rows()` is additive, refuses a group whose
members no longer all exist, reuses an existing ref if the group already has one,
and mints `P000123` from the lowest id otherwise.

**This is the closest thing to a person spine in the repository** — but it is
confined to `candidates`. It does not reach inspectors, professionals, users or
back-office staff.

### The documented inventory is now FIVE mechanisms

**Owner-approved and applied** across `P6-PERSON-REPRESENTATION-ADDENDUM.md` and
`P6-CANONICAL-DOMAIN-MODEL.md`:

| # | Mechanism | Domain |
|---|---|---|
| 1 | `cx_identity_link` | Marketplace ledger — cross-domain, reversible, audited |
| 2 | `candidates.inspector_id` | Recruitment → Operations |
| 3 | `users.inspector_id` | Administration → Operations |
| 4 | The legacy per-application bridge (`cx_applications`) | Marketplace, per application |
| 5 | **`candidates.person_ref` / `person_key()` / `person_link_rows()`** | **Recruitment-internal** |

> **These are five EXISTING MECHANISMS to be evaluated for convergence.**
> They are **not** five canonical identity systems.
>
> **No hub is chosen. None is retired. None is merged. Nothing is implemented.**

### What `person_ref` is — and what it is not

**It IS** a **Recruitment-domain person grouping mechanism**, and it is evidence
that **Recruitment already has a concept of "same human"**. That is a real and
useful finding.

**It is NOT** evidence that it should become the **universal Person identity**.
It groups candidate rows with one another and reaches **no** inspector, no
professional, no user and no back-office record.

**Its fallback behaviour is subject to the identity authority rules in this
document, exactly like every other mechanism.** `person_key()` falls back to
last-10-digit mobile, then lower-cased e-mail, when `person_ref` is unset. Under
the rules below that fallback is **rank-2 and rank-3 evidence**:

- it **may** produce a candidate or a suggestion;
- it **may not** silently establish authoritative **cross-domain** identity.

A mobile number matching between a candidate and a marketplace professional is a
**suggestion for a person to confirm** — never an automatic link, and never a
statement that the two records are one identity.

**No decision is taken. Q3, Q7 and Q13 all remain OPEN.**

---

## What already exists — the reuse surface

Phase 6 invents no new detection. Everything below is already in the repository
and was found by this review.

| Mechanism | Where | What it does | Merges? |
|---|---|---|---|
| **`vocab_match()`** | `lib/vocab.php` | **A six-level resolution ladder** for any controlled vocabulary — see below | **No** |
| **`vocab_duplicate_check()`** | `lib/vocab.php` | Near-duplicate check on save: exact term, similar approved terms, and code collision | **No** — returns candidates for a person |
| **`candpool_*`** | `lib/candpool.php` | Candidate ↔ marketplace professional detector, indexed by mobile-10 / e-mail / name key, with strength ranking | **No** — "read-only DETECTOR… never merges the two rows" |
| **`person_key()` / `person_link_rows()`** | `lib/recruit.php` | Person grouping **within** the recruitment pool | **No** — "only stamps person_ref, never merges or deletes an application" |
| **`connect_identity_suggestions()`** | `lib/connect_identity.php` | Professional ↔ inspector suggestions from shared e-mail (primary) or mobile (secondary) | **No** — "Never links automatically — a person confirms" |
| **`connect_identity_link_create()` / `…_unlink()`** | `lib/connect_identity.php` | The explicit, **reversible, audited** link | **No** |
| **`dd_key()` / `dd_similar()`** | shared | Normalised name key (strips corporate suffixes) and a 0–1 similarity score | **No** |
| **`connect_tax_resolve()` / `tax_norm()`** | `lib/connect_tax_graph.php` | Normalise a term and resolve it to a taxonomy node, through aliases | **No** |
| **`ux_cx_pro_email`** | `cx_professionals` | The only **database-level** uniqueness on any person pool | Prevents, does not merge |

### The six-level ladder, already implemented

`vocab_match()` returns a `level`, and its own comment states the principle:

> *"Levels 4–5: deterministic similarity only. No AI, and a suggestion **NEVER**
> resolves on its own. Ambiguity is a question for a person."*

| Level | Meaning | Outcome class |
|---|---|---|
| **1** | Exact match on an approved term | **EXACT MATCH** |
| **2** | Matches an approved term, ignoring case and punctuation | **EXACT MATCH** |
| **3** | Approved alias / synonym / abbreviation / acronym / legacy / customer term | **EXACT MATCH** |
| **4–5** | Deterministic similarity — prefix 90, substring 80, Levenshtein ≥ 70 | **POSSIBLE MATCH** — suggestion only |
| **6** | Nothing found | **UNKNOWN** |

Term states already exist: `APPROVED` · `PENDING` · `REJECTED`. Sources already
exist: `SYSTEM` · `CUSTOMER` · `IMPORT` · `LEGACY` · `SUGGESTED`.

**This is the pattern every class below reuses.** It is not re-implemented.

---

## The four outcome classes, applied uniformly

| Class | Definition | What may happen automatically | Who may confirm |
|---|---|---|---|
| **EXACT MATCH** | A single strong deterministic identifier agrees **and** resolves to exactly one counterpart | **Suggest** and pre-select. The confirmation is still recorded | Authorised user |
| **POSSIBLE MATCH** | Supporting evidence agrees; no strong identifier confirms it | **Suggest only**, never pre-selected | Authorised user |
| **AMBIGUOUS** | Evidence resolves to **more than one** counterpart, or two identifiers disagree | **Nothing.** Present the conflict | Authorised user, explicitly |
| **NO MATCH** | Nothing links them | Leave unlinked | — |

> **"Exact" describes the *evidence*, not the *authority*.** An EXACT MATCH still
> requires a person. Automatic resolution is what this phase exists to prevent.

**Evidence strength — one ordering, used everywhere.** Taken from the ranking
`candpool.php` already applies (`mobile 3 · email 2 · name 1`):

| Rank | Evidence | Class it can reach alone |
|---|---|---|
| **1** | Government / statutory identifier (GSTIN, CIN, PAN) — **organisations** | EXACT |
| **2** | Mobile, last 10 digits normalised | EXACT |
| **3** | E-mail, lower-cased and trimmed | EXACT |
| **4** | Existing system identifier (`emp_code`, `cand_code`, `person_ref`) | EXACT, where the identifier is genuinely controlled |
| **5** | Certification or licence number, where legitimately held | POSSIBLE |
| **6** | Date of birth **with** a name agreement | POSSIBLE |
| **7** | Name key (`dd_key`) or similarity score | **POSSIBLE at best — never EXACT** |
| **8** | Same employer · same city · same designation · same department | **No class. Context only** |

> **Ranks 7 and 8 can never produce an automatic outcome.** §5 of the master
> prompt forbids it, and the existing detectors already honour it.

---

# The nine duplicate classes

## 1 · Duplicate CANDIDATE

Two candidate rows that are the same human.

**Legitimate, and common.** One person applying for two roles is **not** a
duplicate — it is two applications. §7 of the master prompt: *a candidate can
participate in multiple recruitment processes.*

| | |
|---|---|
| **DETECT** | `person_key()` — `person_ref`, else mobile-10, else e-mail. `person_applications()` already surfaces the other applications of the same person |
| **SUGGEST** | Show the person's other applications on the candidate screen |
| **CONFIRM** | A recruiter confirms "same person" |
| **LINK** | `person_link_rows()` — stamps a shared `person_ref`. **Additive** |
| **AUDIT** | **GAP.** `person_link_rows()` writes no audit entry and cannot be undone |
| **Merge?** | **Never.** Each application is permanent business evidence |

**Gaps:** no audit, no reversal, no concurrency protection, and no duplicate
control on `candidates` itself.

## 2 · Duplicate PERSON

The same human appearing as more than one *representation*.

**This is the Phase 6 subject, and it is not a defect** — five representations of
one person is the normal, correct state. The defect is when the system **cannot
tell**.

| | |
|---|---|
| **DETECT** | `candpool_*` (candidate ↔ professional) · `connect_identity_suggestions()` (professional ↔ inspector) · **nothing** for candidate ↔ inspector, user ↔ anything, back-office ↔ anything |
| **SUGGEST** | Both existing detectors suggest; neither links |
| **CONFIRM** | `connect_identity_link_create()` / `connect_identity_candidate_link_create()` |
| **LINK** | `cx_identity_link` — reversible, audited |
| **AUDIT** | Present for the ledger (`act_log`), **absent** for the three column-based mechanisms |
| **Merge?** | **Never.** §1 lock 11 forbids it explicitly |

**Gaps:** the missing edges (R1, R5); no database-level duplicate protection
(R3); no branch-scope evaluation (R15).

## 3 · Duplicate PROFESSIONAL

| | |
|---|---|
| **DETECT** | **`ux_cx_pro_email` prevents** a second professional with the same e-mail — the only such protection in the system |
| **SUGGEST** | No duplicate-professional suggester exists |
| **CONFIRM / LINK** | `cx_identity_link` can relate a professional to an inspector or candidate, **but not to another professional** |
| **AUDIT** | Via the ledger, where a link is used |
| **Merge?** | **Never** — ratings, verifications and trust standing are the professional's own record |

**Gap:** two professionals with different e-mails and the same mobile are
undetected. The unique index constrains one identifier only.

## 4 · Duplicate INSPECTOR

**The one with proven consequences.**

| | |
|---|---|
| **DETECT** | **None.** No unique index. No detector. The §2 audit created two inspector rows for one person and the system accepted both |
| **SUGGEST** | Nothing exists |
| **CONFIRM / LINK** | `cx_identity_link` relates an inspector to a professional — not to another inspector |
| **AUDIT** | — |
| **Merge?** | **Never.** 51 library files read `inspectors`; deployments, timesheets, vouchers and invoices point at the row |

**Proven failure (§2):** the hiring conversion can create a second inspector, the
candidate points at the second, **the first stays live and orphaned**. That is
R2 — MATERIAL.

## 5 · Duplicate ORGANISATION

| | |
|---|---|
| **DETECT** | **No organisation duplicate detector exists.** `dd_key()` exists and is *built for* company names (it strips *india, group, industries, corporation, enterprises, international, technologies, services, solutions, systems*) but nothing applies it to `business_partners` |
| **SUGGEST** | — |
| **CONFIRM** | — |
| **LINK** | `cx_organisations.party_id` → `business_partners`. **`agencies` has no cross-reference at all** (R4) |
| **AUDIT** | — |
| **Merge?** | **Never.** `parent_id` already expresses group structure without merging |

**The evidence is unusually good and unused:** `business_partners` carries
**GSTIN, PAN, CIN, TAN, MSME/Udyam** — rank-1 evidence — and nothing compares them.

> **A role is not a duplicate.** One organisation that becomes a supplier as well
> as a client sets `is_vendor` on the existing row. Creating a second row is the
> defect.

## 6 · Duplicate DEPARTMENT

**The best-defended class in the system, and the template for the rest.**

| | |
|---|---|
| **DETECT** | `vocab_duplicate_check('department', …)` on every save — exact term, similar approved terms, **and code collision** |
| **SUGGEST** | `dept_save()` refuses a near-duplicate and shows what it matched, unless `$allowNearDuplicate` is passed |
| **CONFIRM** | A person overrides deliberately |
| **LINK** | Aliases, synonyms and legacy mappings on the canonical value |
| **AUDIT** | Vocabulary states `APPROVED` / `PENDING` / `REJECTED` and sources including `LEGACY` and `SUGGESTED` |
| **Merge?** | **Never.** The locked mappings (QA/QC → Quality, HSE → Safety/HSE, FINANCE → Commercial/Finance, NDT → Department, HR → HR) are **mappings**, not rewrites of stored values |

## 7 · Duplicate DESIGNATION

Same engine, same vocabulary type family, with one extra rule:

> **A designation duplicate is judged within its department context.**
> "Inspector" in Quality and "Inspector" in Operations may be two legitimate
> designations. `designations_by_department()` and `desig_set_department()`
> already carry that relationship.

**Gap:** whether the near-duplicate check is applied on every designation write
path is **not established** — it is an action-path question (§26), and it is **not
answered here**.

## 8 · Duplicate TAXONOMY TERM

| | |
|---|---|
| **DETECT** | `connect_tax_node_add()` is *"deduped by kind+slug"* — same kind and same normalised name returns the existing node |
| **SUGGEST** | `connect_tax_resolve()` resolves a term through `cx_tax_aliases`; `connect_tax_suggest()` offers related nodes |
| **CONFIRM** | An administrator adds an alias or a typed edge |
| **LINK** | `cx_tax_aliases` (synonym → canonical node), `cx_tax_edges` (`RELATED`, `SUGGESTS`) |
| **AUDIT** | `source` on the node; `cx_taxonomy_versions` / `cx_qualtax_versions` |
| **Merge?** | **Never.** A wrong alias is removed (`connect_tax_alias_delete`), not merged away |

**The `kind+slug` scope is the important detail:** *NDT* as a **discipline** and
*NDT* as a **department** are different kinds, so they are **not** duplicates of
each other. That is what makes §3's "NDT is both" possible.

## 9 · Duplicate REQUIREMENT MAPPING

Two mappings claiming the same demand.

| | |
|---|---|
| **DETECT** | **Phase 4 already prevents the damaging case.** The COMMITTED ceiling means the same approved seats cannot be promised twice, whatever mapping exists |
| **SUGGEST** | — |
| **CONFIRM** | Making the allocation *is* the confirmation |
| **LINK** | `requisition_allocations.source = 'MARKETPLACE'` + `source_entity_id → cx_requirements.id`, entitlement-gated |
| **AUDIT** | `requisition_allocation_events` (Phase 4) |
| **Merge?** | Not applicable — and `requisitions` / `cx_requirements` may never be merged |

**Open, not decided:** nothing prevents **two allocations on different
requisitions pointing at the same marketplace requirement**. Whether that is a
duplicate or a legitimate shared advert is **Q1**, and it stays open.

---

## Summary of protection, by class

| Class | Detect | Suggest | Confirm | Link | Audit | DB-level protection |
|---|---|---|---|---|---|---|
| Candidate | ✔ | ✔ | ✔ | ✔ | **✘** | ✘ |
| Person (cross-representation) | **partial** | partial | ✔ | ✔ | partial | **✘** |
| Professional | ✔ e-mail only | ✘ | partial | partial | partial | **✔ e-mail** |
| **Inspector** | **✘** | **✘** | partial | partial | ✘ | **✘** |
| Organisation | **✘** | **✘** | **✘** | partial | ✘ | ✘ |
| Department | **✔** | **✔** | **✔** | **✔** | **✔** | ✘ |
| Designation | ✔ | ✔ | ✔ | ✔ | ✔ | ✘ |
| Taxonomy term | **✔** | **✔** | ✔ | **✔** | ✔ | ✘ |
| Requirement mapping | n/a | n/a | ✔ | ✔ | **✔** | n/a |

> **What "complete" means here, and what it does not.**
>
> For Department and Taxonomy, **the duplicate/matching RULE FRAMEWORK is already
> established and reusable** — detect, suggest, confirm, link and audit all exist
> and work today, and Phase 6 reuses them rather than building anything.
>
> It does **NOT** mean that **Phase 6 taxonomy convergence is implemented.**
> Mapping the canonical Department vocabulary to the marketplace technical
> taxonomy is **R7**, and it remains a **future implementation requirement**. The
> framework is ready; the convergence has not been done.

**Inspector and organisation have no framework at all** — no detector, no
suggester, no database-level protection.

---

## Rules that bind every class

**Detection**
1. Detection is **deterministic**. A fuzzy score may suggest; it may never decide.
2. Detection is **read-only**. It never writes a link.
3. Absence of evidence is **NO MATCH**, never a guess.

**Suggestion**
4. A suggestion states **what matched and why**, in business words.
5. A suggestion is never pre-applied. **AMBIGUOUS is never pre-selected.**
6. AI may suggest. **AI may never merge, link or rewrite an authoritative value.**

**Confirmation**
7. Only an **authorised** user confirms. Existing permissions are reused where
   they suffice (§25).
8. Confirming records **actor · timestamp · source records · decision · reason**.
9. **"Keep separate" is a first-class decision** and must be recordable, so the
   same suggestion does not return for ever.
10. **Merge is never the default action.** The default is LINK or KEEP SEPARATE.

**Linking**
11. A link is a **relationship**, never a rewrite of either record.
12. A link is **reversible**, and reversal is audited.
13. A link must be **unique at the database level** — not by a PHP check (R3).
14. A link must be **valid in scope**, not merely reference two existing rows (R15).
15. A failed link **cannot corrupt the source record** (invariant I22).

**Audit**
16. Every confirmation, rejection and reversal is recorded through the existing
    spine (`act_log`).
17. **History is never rewritten.** Linking two records does not change what
    either of them recorded at the time.

**Unresolved states**
18. `mapped` · `unmapped` · `ambiguous` · `unknown` must all be **representable**,
    and an unknown value must stay safely usable until somebody resolves it.
19. **Legacy data is never mass-migrated on uncertain evidence.**

---

## What this document does NOT do

- **Implements nothing.** R1–R17 remain unimplemented.
- **Answers no open question.** Q1–Q12 all remain open — in particular Q1
  (marketplace advert quantity), Q3 and Q7 (the person hub), Q10 (the
  `bos-import` name-only skip).
- **Chooses no hub**, for Person or Organisation.
- **Does not enumerate action paths.** Which routes, APIs, imports and background
  jobs can create each relationship is §26, and it is deliberately not attempted
  here. Several gaps above are marked *not established* for exactly that reason.
- **Does not decide whether `person_ref` should become the person spine.** It is
  recorded as evidence for Q3 and Q7.
- **Measures nothing about live data.** No counts of actual duplicates exist; that
  is §31 reconciliation, and it needs implementation first.

## New implementation requirements identified

*Added to R1–R17. **None implemented.***

| # | Requirement | Source |
|---|---|---|
| **R18** | `person_link_rows()` must record an **audit entry** and support **reversal**, as the identity ledger already does | Class 1 |
| **R19** | An **organisation duplicate detector** using the statutory identifiers already stored, reusing `dd_key()` / `dd_similar()` | Class 5 |
| **R20** | An **inspector duplicate detector** — the class with proven consequences and no protection at all | Class 4 |
| **R21** | A recordable **"keep separate"** decision, so a rejected suggestion does not return indefinitely | Rule 9 |

## New open question

**Q13 — Should `candidates.person_ref` extend beyond Recruitment, or should it
eventually be retired in favour of one controlled identity mechanism?
OPEN QUESTION.**

It is the closest thing to a person spine that exists, it is additive and safe,
and it is confined to candidates. It gives the person-hub question a **second**
internal answer alongside `users` (**Q7**).

> **Q7 and Q13 are deliberately held open TOGETHER.** Their joint purpose is to
> prevent a premature decision that either `users` **or** `candidates.person_ref`
> is the canonical Person spine. Neither is. Both are existing mechanisms awaiting
> evaluation, and option (a) — keep Person emergent and add the missing edges —
> remains equally open.

**Not decided here.**
