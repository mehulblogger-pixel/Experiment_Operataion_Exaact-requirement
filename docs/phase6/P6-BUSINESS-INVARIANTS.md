# Phase 6 — Business Invariants

*The rules that must hold once Phase 6 is done — each with the domain that owns
it, the condition that will test it, and whether it holds today.*

**Sources — established facts only.** `P6-PREIMPLEMENTATION-AUDIT.md` ·
`P6-PERSON-REPRESENTATION-ADDENDUM.md` · `P6-CANONICAL-DOMAIN-MODEL.md` ·
`P6-DUPLICATE-AND-IDENTITY-RULES.md` · `P6-ACTION-PATH-MATRIX.md` ·
`P6-EXTERNAL-ACCOUNT-REPRESENTATION-ADDENDUM.md`.

**This document performs no new repository discovery.** Every status below cites a
finding already established and reviewed. Where the earlier work said *not
established*, this document says so too rather than closing the gap by assertion.

**Scope:** documentation only. No product code, schema, migration, route, API,
permission or workflow is changed. **R1–R31 are not implemented. Q1–Q18 are not
answered.**

---

## How to read this

Every invariant carries four things, because a rule without a test is a wish:

| Column | Meaning |
|---|---|
| **Owning domain** | Who is answerable for it. An invariant with no owner is nobody's |
| **Testable condition** | What a probe will assert. Written so it can be turned into a test without further design |
| **Status** | Where it stands **today**, against the established findings |

**Status values:**

| | |
|---|---|
| **HOLDS** | Evidence shows it is true today |
| **VIOLATED** | A finding proves it is false today — the requirement that fixes it is named |
| **PARTIAL** | True on some paths, false on others |
| **NOT ESTABLISHED** | Neither proved nor disproved. **Not** a synonym for "holds" |
| **PENDING DECISION** | Cannot be settled until a named open question is answered |

> **The status column is the point of this document.** Of 42 invariants, **14 are
> VIOLATED today** and **6 more are PARTIAL**. That is not a complaint about the
> system — it is the Phase 6 work list, expressed as things that will be testable
> rather than as intentions.

---

# Family A — One person, many representations

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I1** | One real person may legitimately have **multiple system representations** | Identity | Create a candidate, an inspector and a professional for one human; all three persist, none is rejected or auto-merged | **HOLDS** |
| **I2** | Multiple representations must **not silently become multiple people** | Identity | With links in place, one resolver call from any representation returns all of them | **PARTIAL** — holds via `cx_identity_link`; the candidate→inspector edge is missing, so a hired candidate resolves to *no* inspector (§2 audit; **R1**, **R5**) |
| **I3** | **Candidate ≠ Person.** One person may hold several candidate records at once | Recruitment | Two applications by one human both persist; neither is treated as a duplicate to remove | **HOLDS** — `person_applications()` / `person_ref` already express it |
| **I4** | **Professional ≠ Person** | Marketplace | A professional record survives unchanged when the person is linked to a candidate or inspector | **HOLDS** |
| **I5** | **Inspector ≠ Person** | Operations | An inspector record survives unchanged when linked | **HOLDS** |
| **I6** | Identity links are **explicit and auditable** | Identity | Every link creation, rejection and reversal produces an audit entry naming actor, timestamp and reason | **VIOLATED** — only `cx_identity_link` audits. `candidates.inspector_id`, `users.inspector_id` and `person_ref` write no audit (addendum §5; **R18**) |
| **I7** | **Ambiguous matches are never auto-merged** | Identity | Given evidence resolving to more than one counterpart, no link is written and the conflict is presented | **PARTIAL** — **holds for the suggesters**: `connect_identity_suggestions()`, `candpool_*` and `vocab_match()` all suggest only. **VIOLATED for the automatic paths** — see I23, I24 |

# Family B — Authority and authorisation

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I23** | **A read operation must never create or modify identity** | Identity + Operations | Call every list/search/read entry point with a user who has read rights only; assert row counts in `inspectors`, `users`, `cx_identity_link`, `candidates`, `cx_tax_nodes` are unchanged | **VIOLATED** — `inspectors_list()` calls `link_inspector_users()`, creating inspectors and writing `users.inspector_id`, at **17 call sites** (§26 Finding A; **R22**) |
| **I24** | **An unresolved taxonomy value cannot become canonical without authorised resolution** | Taxonomy | Run the marketplace talent search with a professional carrying an unknown free-text skill; assert `cx_tax_nodes` gains no row | **VIOLATED** — `connect_pro_search_smart()` → `connect_tax_backfill_pending()` creates a node when a term does not resolve (§26 Finding B; **R23**) |
| **I25** | **A record ID is never proof of authorisation** | All | Post a valid id belonging to a record outside the caller's scope to every relationship route; assert refusal, not action | **VIOLATED** — `/candidate-unlink-pro` removes any link in the workspace by posted id (§26; **R24**). Also `/candidate-link-person` and `/candidate-link-pro` accept ids with no scope gate |
| **I26** | **Entitlement is consistent across every writer of one mechanism** | Entitlement | With Connect not bought, assert **every** path that writes `cx_identity_link` refuses | **VIOLATED** — the marketplace console checks Connect; the recruitment route writes the same ledger gated on `hiring` alone (§26; **R25**) |
| **I27** | An action's permission belongs to **the action**, not to whatever page invoked it | All | Assert each identity-writing function refuses on its own when the caller lacks the right, independently of the calling route | **VIOLATED** — `link_inspector_users()` inherits the calling page's gate entirely (§26; **R22**) |
| **I16** | **Branch scope cannot be bypassed** | Scope | As a Branch-A user, attempt a link whose other end is Branch B; assert refusal | **VIOLATED** — *no identity-link path evaluates branch scope at all*; every one checks only that both rows exist (§26 summary; **R15**) |
| **I15** | **Cross-tenant identity links are impossible** | Tenancy | Attempt a link naming an id that exists only in another tenant's database; assert it cannot resolve | **NOT ESTABLISHED** — argued from the database-per-tenant structure, never tested (addendum §7; **R16**). Tenancy is structural and no cross-connection query was found, but §22 requires proof and this is not it |

# Family C — Duplicates and uniqueness

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I28** | **A live relationship requiring uniqueness is protected at database level**, not by a PHP check | Identity | Insert the same active link twice directly via SQL; assert the second is rejected by a constraint | **VIOLATED** — all three indexes on `cx_identity_link` are non-unique; two identical live rows were created and coexist (§2 audit; **R3**) |
| **I29** | **Two processes creating the same relationship simultaneously produce one relationship** | Identity | Two concurrent processes, one barrier, same pair; assert exactly one live link and a deterministic refusal for the other | **VIOLATED** — follows from I28. Every duplicate control in the identity paths is read-then-write (**R3**, **R11**, **R31**) |
| **I30** | **A person representation cannot be duplicated by an ordinary business action** | Operations + Recruitment | Run the hiring conversion twice for one candidate; assert one inspector, no orphan | **VIOLATED** — two inspector rows accepted, candidate points at the second, **first stays live and orphaned** (§2 audit; **R2**). The same failure mode exists on the read path (**R22**) |
| **I31** | Where a duplicate cannot be prevented, it is **detected and surfaced** | All | For each person pool, assert a detector exists and returns the known duplicate | **PARTIAL** — professionals ✔ (e-mail + mobile at source), candidates ✔ (`candpool`, `person_applications`), **inspectors ✘ nothing** (**R20**), `partner_contacts` ✘ nothing (**R30**) |
| **I32** | **"Keep separate" is a recordable decision** | Identity | Reject a suggestion; assert it does not reappear | **VIOLATED** — no mechanism anywhere (§20 rule 9; **R21**) |
| **I33** | A confirmed link is **reversible**, and the reversal is audited | Identity | Link, then unlink; assert both recorded and the source records unchanged | **PARTIAL** — `cx_identity_link` ✔. `person_ref` has **no unlink route at all** (§26; **R18**); the three column mechanisms have none |

# Family D — Organisation

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I8** | **One organisation may hold multiple business roles without becoming multiple organisations** | Organisation | Mark an existing client as a vendor; assert `business_partners` row count is unchanged and both flags are set | **HOLDS** — roles are flags on one row |
| **I34** | **Organisation identity is not duplicated merely because its business role differs** | Organisation | Create a supplier whose GSTIN matches an existing client; assert the existing organisation is offered, not a second row created | **PARTIAL** — `find_duplicate_partner()` (GSTIN→PAN→TAN→name) covers the business-partner paths; **`/join` and `agencies` have none** (§20 corrected; **R27**) |
| **I35** | Every organisation representation **resolves to the organisation spine** | Organisation | For each `cx_organisations` and `agencies` row, assert a cross-reference to `business_partners` exists or is explicitly absent-and-reported | **VIOLATED** — `agencies` has **no cross-reference at all**; `cx_organisations.party_id` exists but nothing enforces or suggests it (§2 audit; **R4**) |

# Family E — Taxonomy

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I9** | **Department ≠ Designation** | Taxonomy | Assert they remain distinct vocabulary types; a designation cannot be saved as a department | **HOLDS** — Phase 2, locked |
| **I10** | **Vacancy designation ≠ security role**; staff title ≠ vacancy designation | Taxonomy + Access | Assert the designation list and `users.role` are separate sources and neither reads the other | **HOLDS** |
| **I17** | **Taxonomy mappings do not rewrite stored historical values** | Taxonomy | Map "QA/QC"→Quality; assert the stored value on an existing record still reads "QA/QC" and resolves to Quality through the mapping | **HOLDS** — the locked mappings are mappings, not rewrites |
| **I36** | One term may be **several things at once** in different kinds | Taxonomy | Assert "NDT" can exist as a Department **and** a discipline **and** a trade simultaneously | **HOLDS** — nodes are deduped by `kind`+`slug`, so different kinds cannot collide |
| **I37** | A **fuzzy or AI suggestion never resolves on its own** | Taxonomy | Assert a level 4–5 similarity match returns a suggestion and writes nothing | **HOLDS** — `vocab_match()`'s own rule, enforced today. *(Distinct from I24, which is about node* creation *on a search path)* |

# Family F — Demand and the marketplace boundary

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I11** | **Recruitment requisition ≠ Marketplace requirement** | Recruitment + Marketplace | Assert the two tables are never merged and neither is written by the other's engine | **HOLDS** |
| **I12** | **One business demand is not double-counted because of mapping** | Phase 4 | Allocate seats to a marketplace source; assert COMMITTED never exceeds the approved ceiling however many mappings exist | **HOLDS** — Phase 4's ceiling, already locked and proved |
| **I13** | **Phase 4 allocation remains the source-allocation authority** | Phase 4 | Assert no Phase 6 code writes `requisition_allocations` or computes a source credit | **HOLDS** — and Phase 6 must keep it so |
| **I38** | A marketplace source is **refused without entitlement**, never hidden | Entitlement | With Connect not bought, assert allocating to MARKETPLACE returns `NO_ENTITLEMENT` | **HOLDS** — and it is the model I26 should follow |

# Family G — History

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I14** | **Historical records are never silently rewritten** | All | Link two records; assert every field on both, and every historical row referencing them, is byte-identical afterwards | **HOLDS** — every link mechanism found is additive |
| **I39** | **Historical business records remain historically valid after identity convergence** | All | Take a KPI, a recruiter credit and a source credit before linking; assert all three are unchanged after | **NOT ESTABLISHED** — no Phase 6 link has been made, so nothing has been measured. This is the §32 regression requirement, and it needs implementation first |
| **I21** | Existing dashboards and KPIs remain **numerically consistent** | Phase 5 | Run the Phase 5 battery and full regression after any Phase 6 change; assert unchanged | **NOT ESTABLISHED** — same reason |
| **I40** | Historical credit does **not** follow a later reassignment | M5 + Phase 5 | Reassign a record after an outcome; assert the credit stays with the recorded owner | **HOLDS** — Phase 5 proved it, and Phase 6 must not undo it |

# Family H — Failure behaviour

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I22** | **Mapping failure cannot corrupt the source record** | All | Force the second write of every two-step relationship to fail; assert no orphan and no half-linked state | **VIOLATED** — the hiring conversion leaves a live orphan inspector (**R2**); the read path leaves one *and repeats it on every subsequent page load* (**R22**) |
| **I41** | A **failed observation is never a failed transaction** | All | Force an audit/ledger write to fail; assert the business action still stands and the failure is visible | **PARTIAL** — Phase 5 established this pattern for its ledger; no Phase 6 mechanism implements it yet |
| **I42** | Where two writes cannot be one transaction, the incomplete state is **explicit and reconcilable** | All | Assert any half-completed relationship is discoverable by a reconciliation query, not silent | **VIOLATED** — an orphan inspector is indistinguishable from a legitimate one; nothing reports it (**R2**, **R22**) |

# Family I — Existing engines keep working

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I18** | **Existing Operations identity remains usable** | Operations | Full Operations regression after any Phase 6 change | **NOT ESTABLISHED** — nothing changed yet. 51 files read `inspectors`, so this is the largest regression surface |
| **I19** | **Existing Marketplace identity remains usable** | Marketplace | Full Marketplace regression | **NOT ESTABLISHED** — same |
| **I20** | **Existing Recruitment identity remains usable** | Recruitment | Full Recruitment regression | **NOT ESTABLISHED** — same |

# Family J — Invariants that cannot be stated until a decision is made

**These are not omissions.** Each depends on an open question, and inventing the
rule now would be deciding the question by the back door.

| Candidate invariant | Depends on | Why it cannot be written yet |
|---|---|---|
| The canonical Person representation is *X* | **Q3, Q7, Q13** | Three live options: keep Person emergent, promote `users` or `person_ref`, or introduce a record. Writing the invariant chooses |
| `partner_contacts` must / must not participate in identity | **Q17** | It records a real human with more identity fields than `users` has, and has never been treated as one |
| `back_office_staff` must resolve to a person | **Q8, Q9** | Already deprecated with a migration; its disposition is undecided |
| A marketplace advert's positions are bounded by approved demand | **Q1** | May legitimately serve several buyers |
| Portal e-mail uniqueness is global / per-organisation | **Q18** | Today global and raceable; whether global is right is a business judgement |
| The scope class governing a link with mismatched ends | **Q5, Q11** | Branch-scoped inspector ↔ tenant-global professional; neither end's scope is obviously the answer |

---

## The register, at a glance

| Status | Count | Invariants |
|---|---|---|
| **HOLDS** | 16 | I1, I3, I4, I5, I8, I9, I10, I11, I12, I13, I14, I17, I36, I37, I38, I40 |
| **PARTIAL** | 6 | I2, I7, I31, I33, I34, I41 |
| **VIOLATED** | 14 | I6, I16, I22, I23, I24, I25, I26, I27, I28, I29, I30, I32, I35, I42 |
| **NOT ESTABLISHED** | 6 | I15, I18, I19, I20, I21, I39 |
| | **42** | every invariant I1–I42 appears exactly once |

Family J's six candidate invariants are deliberately **unnumbered** — an
unnumbered rule cannot be tested by accident before the decision behind it is
made.

### Every VIOLATED invariant maps to an existing requirement

| Invariant | Requirement | One-line defect |
|---|---|---|
| I23, I27 | **R22** | The inspector list creates identity while reading |
| I24 | **R23** | Marketplace search creates taxonomy nodes |
| I25 | **R24** | Unlink acts on a posted id with no scope check |
| I26 | **R25** | Two writers of one ledger check different entitlements |
| I16 | **R15** | No identity path evaluates branch scope |
| I28, I29 | **R3** | `cx_identity_link` uniqueness is PHP-side only |
| I30, I22, I42 | **R2** | Hiring conversion can orphan an inspector |
| I6 | **R18** | Three of five mechanisms write no audit |
| I32 | **R21** | "Keep separate" cannot be recorded |
| I35 | **R4** | `agencies` has no organisation cross-reference |

**No VIOLATED invariant lacks a requirement, and no requirement was invented
here.**

**The shape of the work:** every VIOLATED invariant already has a requirement
(R1–R31). Nothing in this document is a new demand — it is the same findings,
restated so that a probe can be written against each.

---

## Coverage of the owner's eight named rules

Every rule named in the §27 instruction has a numbered invariant, an owner and a
testable condition:

| Owner's wording | Invariant | Status |
|---|---|---|
| A read operation must never create or modify identity | **I23** | VIOLATED (R22) |
| A record ID is never proof of authorisation | **I25** | VIOLATED (R24) |
| An unresolved taxonomy value cannot become canonical without authorised resolution | **I24** | VIOLATED (R23) |
| Uniqueness protected at database level | **I28** (+ **I29** concurrency) | VIOLATED (R3) |
| An identity relationship must satisfy tenant/scope rules | **I15** tenant · **I16** scope | NOT ESTABLISHED · VIOLATED (R15, R16) |
| One person may legitimately have multiple representations | **I1** (+ **I3**, **I4**, **I5**) | HOLDS |
| Historical records remain valid after convergence | **I14** (today) · **I39** (after convergence) | HOLDS · NOT ESTABLISHED |
| Organisation identity not duplicated merely because role differs | **I8** (roles) · **I34** (duplicates) | HOLDS · PARTIAL (R27) |

**I1–I22 are the master prompt's mandated minimum, kept at their original
numbers.** I23–I42 are the additions the §20, §26 and R26 findings made
necessary — chiefly the authority family, which did not exist as invariants
before the hidden write paths were found.

---

## What this document does NOT do

- **No new repository discovery.** Every status cites an already-reviewed finding.
- **Implements nothing.** R1–R31 unimplemented.
- **Answers nothing.** Q1–Q18 open; Family J exists precisely so that no invariant
  quietly decides one.
- **Chooses no Person hub**, and does not classify `partner_contacts`.
- **Changes no earlier document.**
- **Claims no test evidence.** Every "testable condition" is a condition a future
  probe will assert. **None has been run.** Six invariants are NOT ESTABLISHED for
  exactly that reason, and saying "holds" of them would be the kind of claim this
  programme has spent six phases refusing to make.
