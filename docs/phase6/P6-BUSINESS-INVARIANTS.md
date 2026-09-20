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

> **The status column is the point of this document.** When it was written, of 42
> invariants **14 were VIOLATED** and **6 were PARTIAL**. **Phase 6 Batch 1 has
> since been implemented, and its evidence is recorded here**, moving sixteen of
> them. The figures below are post-Batch 1; every changed row says so, because a
> register that quietly forgets what it used to say is not a register.
>
> This is still the work list, not a report card: **4 invariants remain VIOLATED**
> and **10 PARTIAL**, each with the requirement that closes it.

---

# Family A — One person, many representations

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I1** | One real person may legitimately have **multiple system representations** | Identity | Create a candidate, an inspector and a professional for one human; all three persist, none is rejected or auto-merged | **HOLDS** |
| **I2** | Multiple representations must **not silently become multiple people** | Identity | With links in place, one resolver call from any representation returns all of them | **PARTIAL** — holds via `cx_identity_link`; the candidate→inspector edge is missing, so a hired candidate resolves to *no* inspector (§2 audit; **R1**, **R5**) |
| **I3** | **Candidate ≠ Person.** One person may hold several candidate records at once | Recruitment | Two applications by one human both persist; neither is treated as a duplicate to remove | **HOLDS** — `person_applications()` / `person_ref` already express it |
| **I4** | **Professional ≠ Person** | Marketplace | A professional record survives unchanged when the person is linked to a candidate or inspector | **HOLDS** |
| **I5** | **Inspector ≠ Person** | Operations | An inspector record survives unchanged when linked | **HOLDS** |
| **I6** | Identity links are **explicit and auditable** | Identity | Every link creation, rejection and reversal produces an audit entry naming actor, timestamp and reason | **PARTIAL** *(was VIOLATED; the original status also understated it — see the correction below)* — `cx_identity_link` now writes attributable, queryable entries for creation, reversal and identified refusals (Batch 1). `candidates.inspector_id`, `users.inspector_id` and `person_ref` still write none (**R18**) |
| **I7** | **Ambiguous matches are never auto-merged** | Identity | Given evidence resolving to more than one counterpart, no link is written and the conflict is presented | **PARTIAL** — **holds for the suggesters**: `connect_identity_suggestions()`, `candpool_*` and `vocab_match()` all suggest only. **VIOLATED for the automatic paths** — see I23, I24 |

# Family B — Authority and authorisation

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I23** | **A read operation must never create or modify identity** | Identity + Operations | Call every list/search/read entry point with a user who has read rights only; assert row counts in `inspectors`, `users`, `cx_identity_link` and `candidates` are unchanged | **HOLDS** *(Batch 1; was VIOLATED)* — the write-on-read call is removed and nothing lazy replaces it; probes `A1–A5`, mutant `M1`. *(Taxonomy creation on a search path is the separate invariant **I24**, still VIOLATED — **R23**, out of Batch 1. It was listed in this condition when written; it is stated here rather than left to make I23 look narrower than it is.)* |
| **I24** | **An unresolved taxonomy value cannot become canonical without authorised resolution** | Taxonomy | Run the marketplace talent search with a professional carrying an unknown free-text skill; assert `cx_tax_nodes` gains no row | **VIOLATED** — `connect_pro_search_smart()` → `connect_tax_backfill_pending()` creates a node when a term does not resolve (§26 Finding B; **R23**) |
| **I25** | **A record ID is never proof of authorisation** | All | Post a valid id belonging to a record outside the caller's scope to every relationship route; assert refusal, not action | **HOLDS** *(Batch 1)* — the ledger takes the record the caller acts for and refuses any link that is not that record's, with the same words as for a link that does not exist; probes `B1–B5`, mutant `M3`. `W2` asserts every production caller states it |
| **I26** | **Entitlement is consistent across every writer of one mechanism** | Entitlement | With Connect not bought, assert **every** path that writes `cx_identity_link` refuses | **HOLDS** *(Batch 1)* — all three writers ask `connect_identity_admin_can()` themselves; probes `C1–C5`, mutants `M5`, `M5b`. Permission matrix updated in the same change |
| **I27** | An action's permission belongs to **the action**, not to whatever page invoked it | All | Assert each identity-writing function refuses on its own when the caller lacks the right, independently of the calling route | **HOLDS** *(Batch 1)* — `link_inspector_users()` asks for the People right itself and creates nothing without it; the ledger writers ask their own gate; probes `A9–A12`, mutant `M2` |
| **I16** | **Branch scope cannot be bypassed** | Scope | As a Branch-A user, attempt a link whose other end is Branch B; assert refusal | **PARTIAL** *(Batch 1)* — **per-end visibility** is enforced: you may not build or break a relationship out of a record you cannot open (`D1–D5`, mutants `M6`, `M7`). Whether the **relationship itself** carries a branch is **Q5/Q11, still open**; mutant `M8` exists to stop that being answered by the back door |
| **I15** | **Cross-tenant identity links are impossible** | Tenancy | Attempt a link naming an id that exists only in another tenant's database; assert it cannot resolve | **HOLDS** *(Batch 1)* — **tested, not argued**: two real separate tenant databases on both engines; B's ids are refused in A, A gains nothing, B is untouched (`E0–E5`, mutant `M18`) |

# Family C — Duplicates and uniqueness

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I28** | **A live relationship requiring uniqueness is protected at database level**, not by a PHP check | Identity | Insert the same active link twice directly via SQL; assert the second is rejected by a constraint | **HOLDS** *(Batch 1; residual closed by Batch 3 corrective)* — U1/U2/U3 over three NULL-able live-key columns; raw SQL duplicates rejected on both engines (`F0–F8`, mutants `M9`, `M17`). The Batch 1 residual — *the key is set by the writer* — is **closed for the keys introduced since**: `partner_contacts.uq_primary` and `client_users`/`vendor_users.uq_active_email` are **generated columns, computed by the database**, so no writer can set, forget or bypass them. Proven by a raw `INSERT` claiming to be primary being refused (`CA6`), a raw padded-address `INSERT` being refused with no application code involved (`CB12`), and mutants `CM1`, `CM2`, `CM8`, `CM20` |
| **I29** | **Two processes creating the same relationship simultaneously produce one relationship** | Identity | Two concurrent processes, one barrier, same pair; assert exactly one live link and a deterministic refusal for the other | **HOLDS** *(Batch 1; widened by Batch 3 corrective)* — four real processes, one live link, no crash, and every process told it succeeded names a link that really is live (`G1–G7`, mutants `M12`, `M13`). Batch 3 corrective widens it to two more relationships, each with real operating-system processes on a wall-clock barrier: two, three and three **raw** concurrent writers each leave exactly one main contact (`CA10–CA16`), and three concurrent boots installing the same protection all succeed with none reporting failure (`CG13–CG18`). **R31** (portal e-mail) is **no longer untouched** — the account key exists and is database-enforced |
| **I30** | **A person representation cannot be duplicated by an ordinary business action** | Operations + Recruitment | Run the hiring conversion twice for one candidate; assert one inspector, no orphan | **VIOLATED** — two inspector rows accepted, candidate points at the second, **first stays live and orphaned** (§2 audit; **R2**). The same failure mode exists on the read path (**R22**) |
| **I31** | Where a duplicate cannot be prevented, it is **detected and surfaced** | All | For each person pool, assert a detector exists and returns the known duplicate | **PARTIAL** — professionals ✔, candidates ✔, **inspectors ✘** (**R20**, deferred). `partner_contacts` and the organisation pools ✔ *(Batch 3)*: duplicate contacts, ambiguous primaries, duplicate tax identifiers, shared names, unmapped and dangling representations and duplicate active accounts are all reported by `identity_state_findings()` — detection only, nothing repaired (**R30** *surfaced*, its uniqueness still deferred by **Q22**) |
| **I32** | **"Keep separate" is a recordable decision** | Identity | Reject a suggestion; assert it does not reappear | **VIOLATED** — no mechanism anywhere (§20 rule 9; **R21**) |
| **I33** | A confirmed link is **reversible**, and the reversal is audited | Identity | Link, then unlink; assert both recorded and the source records unchanged | **PARTIAL** — `cx_identity_link` ✔. `person_ref` has **no unlink route at all** (§26; **R18**); the three column mechanisms have none |

# Family D — Organisation

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I8** | **One organisation may hold multiple business roles without becoming multiple organisations** | Organisation | Mark an existing client as a vendor; assert `business_partners` row count is unchanged and both flags are set | **HOLDS** — roles are flags on one row |
| **I34** | **Organisation identity is not duplicated merely because its business role differs** | Organisation | Create a supplier whose GSTIN matches an existing client; assert the existing organisation is offered, not a second row created | **PARTIAL → substantially closed** *(Batch 3)* — the detector now reports EXACT (GSTIN/PAN/TAN) vs POSSIBLE (name) and is wired to **every** creating door: `/join` (refuse, neutrally), the quotation path (attach, never duplicate), the lead conversion (refuse and name it). Probes `A1–A10`, `E4–E8`, `M1–M12`; mutants M1, M7–M12. **Still PARTIAL**, deliberately: two *simultaneous* registrations of one name are detected but not prevented, because that needs a uniqueness rule on `business_partners` (**Q1–Q18 open**) |
| **I35** | Every organisation representation **resolves to the organisation spine** | Organisation | For each `cx_organisations` and `agencies` row, assert a cross-reference to `business_partners` exists or is explicitly absent-and-reported | **VIOLATED → PARTIAL** *(Batch 3, **R4** closed)* — `agencies.party_id` now exists: optional, never inferred, never unique, set by a person on the agency screen and audited (**Q19**). Every representation that is *not* resolved is now **explicitly reported** — `MARKETPLACE_UNMAPPED`, `MARKETPLACE_PARTY_MISSING`, `AGENCY_POSSIBLE_ORGANISATION`. It stays PARTIAL because the cross-reference is a map, not a constraint: nothing *enforces* resolution, by decision |

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
| **I21** | Existing dashboards and KPIs remain **numerically consistent** | Phase 5 | Run the Phase 5 battery and full regression after any Phase 6 change; assert unchanged | **HOLDS** *(Batch 1)* — the locked Phase 5 KPI battery passes unchanged on both engines |
| **I40** | Historical credit does **not** follow a later reassignment | M5 + Phase 5 | Reassign a record after an outcome; assert the credit stays with the recorded owner | **HOLDS** — Phase 5 proved it, and Phase 6 must not undo it |

# Family H — Failure behaviour

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I22** | **Mapping failure cannot corrupt the source record** | All | Force the second write of every two-step relationship to fail; assert no orphan and no half-linked state | **PARTIAL** *(Batch 1)* — the reconciliation is transactional and two racing administrators leave no orphan (`I1–I4`, mutant `M16`); the read path no longer repeats one. The hiring conversion (**R2**) and inspector uniqueness (**R20**) are **deferred**, so this is not HOLDS |
| **I41** | A **failed observation is never a failed transaction** | All | Force an audit/ledger write to fail; assert the business action still stands and the failure is visible | **PARTIAL** *(Batch 1)* — holds for the identity ledger: an audit write the database rejects neither throws nor undoes the relationship (`I41a–c`). It cannot hold generally while three of the five identity mechanisms write no audit at all (**R18**), so this is PARTIAL and not HOLDS |
| **I42** | Where two writes cannot be one transaction, the incomplete state is **explicit and reconcilable** | All | Assert any half-completed relationship is discoverable by a reconciliation query, not silent | **PARTIAL** *(Batch 1)* — unlinked logins and duplicate relationships are both reported (`team_unlinked_logins()`, `connect_identity_duplicates()`, two system-status rows). Pre-existing orphan inspectors are still indistinguishable — **R2/R20**, deferred |

# Family I — Existing engines keep working

| # | Invariant | Owning domain | Testable condition | Status |
|---|---|---|---|---|
| **I18** | **Existing Operations identity remains usable** | Operations | Full Operations regression after any Phase 6 change | **HOLDS** *(Batch 1)* — full regression green on SQLite **and** MariaDB after the batch |
| **I19** | **Existing Marketplace identity remains usable** | Marketplace | Full Marketplace regression | **HOLDS** *(Batch 1)* — full regression green on SQLite **and** MariaDB after the batch |
| **I20** | **Existing Recruitment identity remains usable** | Recruitment | Full Recruitment regression | **HOLDS** *(Batch 1)* — full regression green on SQLite **and** MariaDB after the batch |

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

*The "before" column is what this document recorded when it was written, so the
movement stays visible rather than being overwritten.*

| Status | Before Batch 1 | After Batch 2 | **After Batch 3** | Invariants now |
|---|---|---|---|---|
| **HOLDS** | 16 | 27 | **27** | I1, I3, I4, I5, I8, I9, I10, I11, I12, I13, I14, I15, I17, I18, I19, I20, I21, I23, I25, I26, I27, I28, I29, I36, I37, I38, I40 |
| **PARTIAL** | 6 | 10 | **11** | I2, I6, I7, I16, I22, I31, I33, I34, **I35**, I41, I42 |
| **VIOLATED** | 14 | 4 | **3** | I24, I30, I32 |
| **NOT ESTABLISHED** | 6 | 1 | **1** | I39 |
| | **42** | **42** | **42** | every invariant I1–I42 appears exactly once |

Batch 3 moves exactly one invariant between buckets: **I35**, out of VIOLATED,
because the agency cross-reference now exists and every unresolved representation
is reported. **I39 stays NOT ESTABLISHED** — Batch 3 made no identity
convergence, so there is still nothing to measure, and "we did not break it" is
not the same as "it was proved".

## Batch 3 — what moved, and what deliberately did not

| Invariant | Before | After | Why |
|---|---|---|---|
| **I25** a record id is never proof of authorisation | HOLDS *(identity routes)* | **HOLDS**, now also on the organisation routes | a posted organisation id is checked at the function; probes `K3` `K4`, mutant M19 |
| **I27** protection belongs to the action | HOLDS *(identity routes)* | **HOLDS**, now also for `portal_invite()` | it asks its own authority — **both** of the two that legitimately exist; probes `K1` `K2`, mutants M17 M18 |
| **I34** organisation not duplicated by role | PARTIAL | **PARTIAL** *(substantially closed)* | every door now asks; simultaneous same-name registration still needs Q1–Q18 |
| **I35** every representation resolves to the spine | **VIOLATED** | **PARTIAL** | **R4 closed** — the agency cross-reference exists and unresolved rows are reported |
| **I31** duplicates detected where not prevented | PARTIAL | **PARTIAL** *(wider)* | organisation and contact states now reported; inspectors (**R20**) still open |
| **I41** an audit failure never fails a business write | HOLDS | **HOLDS** | organisation audit is outside the transaction and silent on failure |

**Not moved, on purpose:** I2 · I6 · I7 · I16 · I22 · I24 · I30 · I32 · I33 ·
I42. Batch 3 touched none of them, and none is upgraded on the strength of work
that did not test it. **Q1–Q18 remain open.**

**Register after Batch 3:** HOLDS **27** · PARTIAL **11** · VIOLATED **3**
(I24 · I30 · I32) · NOT ESTABLISHED **1** (I39) — 42 in total. I35 moves out of
VIOLATED into PARTIAL; nothing else changes bucket.

**Moved by Batch 1's own work (11):** I15 · I23 · I25 · I26 · I27 · I28 · I29 to
HOLDS; I6 · I16 · I22 · I42 to PARTIAL. **I41 stays PARTIAL**: it holds for the
identity ledger and cannot hold generally while R18 is open.
**Moved by its regression evidence (4):** I18 · I19 · I20 · I21 to HOLDS.

> **LOCKED on Batch 1 acceptance (2026-09-19).** These statuses are the accepted
> record. The PARTIALs below are not to be re-argued upward, and Q1–Q18 stay
> open, until a new owner instruction changes the underlying facts.

**Deliberately still PARTIAL, not rounded up:** I16 (Q5/Q11 open) · I22 and I42
(R20 deferred) · I6 and I41 (R18 only registered). Mutant `M8` exists specifically to
stop I16 being closed by answering Q5/Q11 quietly.

Family J's six candidate invariants are deliberately **unnumbered** — an
unnumbered rule cannot be tested by accident before the decision behind it is
made.

### Every VIOLATED invariant maps to an existing requirement

| Invariant | Requirement | One-line defect |
|---|---|---|
| I23, I27 | **R22** | The inspector list creates identity while reading — **CLOSED, Batch 1** |
| I24 | **R23** | Marketplace search creates taxonomy nodes — **still open** |
| I25 | **R24** | Unlink acts on a posted id with no scope check — **CLOSED, Batch 1** |
| I26 | **R25** | Two writers of one ledger check different entitlements — **CLOSED, Batch 1** |
| I16 | **R15** | No identity path evaluates branch scope — **per-end visibility done; the relationship's own scope stays open (Q5/Q11)** |
| I28, I29 | **R3** | `cx_identity_link` uniqueness is PHP-side only — **CLOSED, Batch 1** |
| I30 · I22, I42 | **R2**, **R20** | Hiring conversion can orphan an inspector — **deferred**; Batch 1 closed only the read-path and two-write cases |
| I6 | **R18** | Three of five mechanisms write no audit — **the ledger now audits attributably; the other three still do not** |
| I32 | **R21** | "Keep separate" cannot be recorded — **still open** |
| I35 | **R4** | `agencies` has no organisation cross-reference — **CLOSED, Batch 3** (optional, never inferred, never unique; **Q19**) |

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
- **Claimed no test evidence when written.** Every "testable condition" was a
  condition a future probe would assert, and none had been run. **That is no
  longer true for the invariants Batch 1 covers**: each status marked
  *(Batch 1)* cites the probe and the mutant behind it, recorded in
  `P6-BATCH1-SECURITY-RESULTS.md` and `P6-BATCH1-MUTATION-RESULTS.md`. Every
  other status is still a statement about what a future probe will assert, and is
  left that way deliberately.

---

# Batch 3 CORRECTIVE — what the evidence moved

*Recorded on owner acceptance, 2026-09-20. Source state `939de8a`; evidence
`P6-BATCH3-CORRECTIVE-EVIDENCE.md`. Six defects were found by a post-gate
adversarial pass on work that had already been accepted and locked; five of the
six were introduced by Batch 3 itself.*

| Invariant | Movement |
|---|---|
| **I28** — uniqueness protected at database level, not by a PHP check | Batch 1's recorded residual (*the key is set by the writer*) is **closed for the keys introduced since**: they are generated columns the database computes. A raw `INSERT` cannot bypass them |
| **I29** — concurrent creators produce one relationship | **Widened** from identity links to the primary contact and to the installation of the protection itself. Three concurrent writers previously produced **three** main contacts, 3 runs out of 3; they now produce one |
| **I31** — duplicates detected where not prevented | **Improved, still PARTIAL.** A duplicate that a human has already resolved by merging no longer reports for ever: `MERGED` is excluded from the two organisation-duplicate findings and only those. Inspectors (**R20**) remain open |
| **I34** — organisation not duplicated by role | **Unchanged (PARTIAL).** Simultaneous same-name registration is still detected and not prevented; that needs Q1–Q18, which stay open |
| **I35** — every representation resolves to the spine | **Unchanged (PARTIAL).** The cross-reference is still a map, not a constraint, by decision. A retired organisation now carries an explicit machine-readable pointer to its survivor (**R4**), so a match on a retired record resolves to the company that trades |
| **I41** — a failed observation is never a failed transaction | **Unchanged (HOLDS).** Reinforced by Q27: security evidence from an unauthenticated request is written outside the customer's activity feed, and a failure to write it still never fails the business action |

**Deliberately not moved.** No invariant was advanced on the strength of this
batch alone where the evidence does not reach it. Identity convergence did not
begin, no Person hub was created, nothing was merged, and Q1–Q18, R20, R21 and
R23 remain open. A register that flatters the work it records is not a register.
