# Phase 6 · Batch 3 — Organisation implementation plan

*A plan, not an instruction. Nothing here is implemented, and nothing here may be
implemented until the owner approves a scoped Batch 3 implementation step.*

**Source:** `P6-BATCH3-ORGANISATION-AUDIT.md`.
**Locked and untouched:** Batch 1 · Batch 2 · Operations · Reporting · Money ·
Workforce · Marketplace architecture · Phase 5 KPI · Phase 4 allocation ·
Recruitment conversion.

---

## How to read this

Each finding carries: **risk · proposed action · architectural verdict ·
files · tables · tests · dependency · owner decision · which batch.**

Nothing is proposed that builds an engine. The strongest recommendation in this
document is *"call the function you already have."*

---

## F1 — `/join` creates organisations with no duplicate detection

| | |
|---|---|
| **Finding** | The public sign-up writes `business_partners` and `cx_organisations` with no check. An existing client self-registering gets a **second** partner — and `find_duplicate_partner()` would have caught it by name |
| **Risk** | **CRITICAL.** Unauthenticated organisation injection. A shadow record beside a real customer, reachable by anyone who knows the company's name |
| **Proposed action** | Call the existing detector before the first INSERT. On an **exact** match (GSTIN / PAN / TAN) refuse or route to a claim flow; on a **possible** match (name only) the behaviour is **Q21** |
| **Verdict** | **CONNECT** — the detector exists and is not called |
| **Files** | `lib/connect_org.php` (`connect_org_register`, `connect_org_apply`) |
| **Tables** | none changed |
| **Tests** | exact-match refusal · possible-match behaviour once Q21 is answered · legitimate distinct company still registers · **no disclosure**: the public refusal must not confirm that a company exists |
| **Dependency** | none |
| **Owner decision** | **Q21 — required before implementation** |
| **Batch** | **Batch 3 implementation** |

## F2 — `/join` is not safe under concurrency

| | |
|---|---|
| **Finding** | Three simultaneous sign-ups with the same e-mail produced three logins, three partners and three marketplace organisations. **Tested** |
| **Risk** | **CRITICAL.** The portal's account-uniqueness rule is defeated from a public route |
| **Proposed action** | A unique index on the portal-account e-mail, and the writer translating the conflict into a business answer — **exactly the Batch 1 pattern, already proven on both engines**. The PHP check stays as the courtesy that gives the good message |
| **Verdict** | **EXTEND** — Batch 1's uniqueness protocol applied to a second table |
| **Files** | `lib/connect_org.php` · `lib/portal.php` · `lib/cvp.php` |
| **Tables** | `client_users`, `vendor_users` — one additive unique index each; **no column added, no row changed** |
| **Tests** | three real OS processes, one e-mail → one account · legacy duplicates skip the index and are reported · the loser is told the truth, never a crash · retry is idempotent |
| **Dependency** | Batch 1's `books.php`-derived protocol (skip-and-report on legacy duplicates) |
| **Owner decision** | **Q23 — global or per-organisation uniqueness** |
| **Batch** | **Batch 3 implementation** |

## F3 — `/join` has no transaction

| | |
|---|---|
| **Finding** | Three tables written unguarded. A failure after the first leaves an organisation nobody can sign in to, and nothing reports it |
| **Risk** | **HIGH.** Silent orphan on a public route |
| **Proposed action** | One transaction around party + organisation + login, with the borrowed-transaction contract Batch 2 established (join a caller's transaction; never commit or roll back what is not ours) |
| **Verdict** | **EXTEND** |
| **Files** | `lib/connect_org.php` |
| **Tables** | none |
| **Tests** | force each write to fail → **no orphan** · retry after rollback succeeds cleanly · no false success, no false failure |
| **Dependency** | Batch 2's transaction contract |
| **Owner decision** | none |
| **Batch** | **Batch 3 implementation** |

## F4 — `agencies` has no cross-reference to the organisation spine

| | |
|---|---|
| **Finding** | No `party_id`, no `partner_id` — **no column exists**. An agency can carry a GSTIN that already belongs to a partner, and nothing connects them. This is **R4** |
| **Risk** | **HIGH** for reporting and for any later convergence; **LOW** operationally today, because nothing depends on the link |
| **Proposed action** | One **optional, nullable** `party_id` on `agencies` — a cross-reference a person sets, **never inferred, never back-filled**, never required. An agency with no party stays valid |
| **Verdict** | **MAP** — explicitly **not** MIGRATE and **not** merge |
| **Files** | `lib/ops.php` (the agency master definition) |
| **Tables** | `agencies` — one nullable column |
| **Tests** | an agency may exist with no party · two agencies may point at one party (**several contracts over time — legitimate**) · setting it changes no commercial field · Recruitment and Money read exactly what they read before |
| **Dependency** | none |
| **Owner decision** | **Q19 — required.** An agency is a *contract*; a partner is a *legal identity*. One company may hold several agency contracts, so this must never become a uniqueness rule |
| **Batch** | **Batch 3 implementation, if Q19 is approved** |

## F5 — `crm.php` creates a business partner with no check

| | |
|---|---|
| **Finding** | `crm.php:2628` writes a partner directly. `leads.php:366` checks **name only** |
| **Risk** | **MEDIUM.** Staff-gated, so not an outside attack — but it is how the register fills with near-duplicates |
| **Proposed action** | Call `find_duplicate_partner()` on both paths, with the exact/possible distinction from F6 |
| **Verdict** | **CONNECT** |
| **Files** | `lib/crm.php` · `lib/leads.php` |
| **Tests** | an exact match is refused with the existing record named · a name match warns and allows an override that is recorded |
| **Dependency** | F6 |
| **Owner decision** | none |
| **Batch** | **Batch 3 implementation** |

## F6 — the detector cannot say how confident it is

| | |
|---|---|
| **Finding** | GSTIN, PAN, TAN and name all return through one door. A caller cannot refuse a tax-identifier match while merely warning on a name match |
| **Risk** | **MEDIUM.** Every caller must either over-refuse or under-refuse |
| **Proposed action** | Return a `confidence` of `EXACT` (tax identifier) or `POSSIBLE` (name) alongside the existing shape. **Purely additive — every current caller keeps working** |
| **Verdict** | **EXTEND** |
| **Files** | `lib/ops.php` / `lib/partnerimport.php` (wherever the function lives) |
| **Tests** | GSTIN match → EXACT · name match → POSSIBLE · **existing callers unchanged** |
| **Dependency** | none |
| **Owner decision** | **Q20** |
| **Batch** | **Batch 3 implementation** |

## F7 — `partner_contacts` accepts unlimited duplicates and several primaries

| | |
|---|---|
| **Finding** | Three identical contacts accepted on one organisation, all `is_primary = 1`. "The primary contact" is ambiguous, and readers take whichever sorts first. This is **R30** |
| **Risk** | **MEDIUM.** Wrong person contacted; the portal auto-link picks a row by sort order |
| **Proposed action** | Two separate questions. (a) duplicate control on `(partner_id, lower(email))` where the e-mail is non-empty; (b) **at most one primary per organisation**. (b) is the cheaper and more valuable half |
| **Verdict** | **EXTEND** |
| **Files** | the five contact writers |
| **Tables** | `partner_contacts` |
| **Tests** | **one person may still be a contact at several organisations** (the negative that proves it is not over-tight) · a blank e-mail is never constrained · setting a new primary clears the old one |
| **Dependency** | none |
| **Owner decision** | **Q22** |
| **Batch** | **LATER BATCH** — five writers, and it is not a security defect |

## F8 — no organisation creation is audited

| | |
|---|---|
| **Finding** | None of the eighteen writers writes an audit entry |
| **Risk** | **MEDIUM.** A partner appearing from a public route leaves no trace of who or what created it |
| **Proposed action** | `act_log()` against a registered `PARTNER` entity — **already in `ACT_ENTITIES`**. Outside any transaction, per **I41** |
| **Verdict** | **REUSE** |
| **Files** | the organisation writers |
| **Tests** | creation, refusal and the `/join` path each write an attributable entry · an audit failure never fails the business write |
| **Dependency** | Batch 1's audit registration |
| **Owner decision** | none |
| **Batch** | **Batch 3 implementation** — narrow, and it makes F1/F2 observable |

## F9 — historical duplicates are invisible

| | |
|---|---|
| **Finding** | Twelve patterns classified in the audit §13; none detectable today |
| **Risk** | **LOW** immediately, **HIGH** before any convergence |
| **Proposed action** | Extend Batch 2's `identity_state_findings()` with organisation kinds. **Detection only** — each finding states what, which records, why, whether repair is safe, whether a person must look |
| **Verdict** | **EXTEND** |
| **Files** | `lib/connect_identity.php` (the existing report) |
| **Tables** | none |
| **Tests** | each pattern detected · **detection changes nothing** · a legitimate multiple is **not** reported as a duplicate |
| **Dependency** | Batch 2's report |
| **Owner decision** | none |
| **Batch** | **Batch 3 implementation** |

## F10 — no unique index on any organisation table

| | |
|---|---|
| **Finding** | Confirmed across all six |
| **Risk** | **HIGH** where uniqueness is genuinely required — which is **only** portal-account e-mail (F2) and possibly contact e-mail (F7) |
| **Proposed action** | **Do not invent universal uniqueness.** `business_partners` must **not** get a unique GSTIN index: subsidiaries and group entities legitimately share tax registrations in this business, and the audit did not establish otherwise |
| **Verdict** | **REUSE** the Batch 1 protocol, narrowly |
| **Owner decision** | **none to add uniqueness; an explicit decision would be needed to add it anywhere beyond F2** |
| **Batch** | F2 only |

---

## Proposed Batch 3 implementation scope

**In** — narrow, and each closes a proved defect:

| | |
|---|---|
| **F1** | `/join` calls the detector *(gated on Q21)* |
| **F2** | portal-account e-mail uniqueness at database level *(gated on Q23)* |
| **F3** | `/join` becomes transactional |
| **F5** | `crm.php` and `leads.php` call the detector |
| **F6** | the detector reports exact vs possible *(gated on Q20)* |
| **F8** | organisation creation is audited |
| **F9** | organisation states are detected and reported |
| **F4** | the agency cross-reference *(gated on Q19)* |

**Out — deliberately:**

F7 contact uniqueness (**R30**, five writers, no security exposure) · historical
repair of anything · any merge · any migration · any new table beyond F4's one
nullable column · `business_partners` uniqueness · branch scope on organisations
· **Q1–Q18**.

---

## Test plan for the eventual implementation

Written before any code, as in Batches 1 and 2. Baseline recorded first.

| Area | Must prove |
|---|---|
| Duplicate organisation | an exact match is refused; a possible match behaves per Q21; **a genuinely different company still registers** |
| Duplicate agency | per Q19 |
| Duplicate marketplace organisation | a second one for an existing party is refused or reported |
| Duplicate contact | per Q22 — **and one person at several organisations still works** |
| External account isolation | ownership from the session; a forged organisation id changes nothing |
| Tenant isolation | two real tenant databases, as Batch 1 |
| Branch isolation | **not applicable** — stated, not faked |
| Forged ids | organisation, contact and agency ids all refused as authorisation |
| **Concurrency** | **three real OS processes** on `/join`: one account, one party, one organisation |
| Retry / idempotency | a retry after rollback creates nothing extra |
| Historical orphans | detected, **never repaired** |
| Legitimate multiples | subsidiaries · one contact at several organisations · several agency contracts for one company — **all still allowed** |
| Ambiguous duplicates | reported for review, **never merged** |

**No existing test may be weakened, deleted or skipped.** Both engines, MariaDB
authoritative. Mutation targets to be written before implementation, including:
the detector is not called · the exact/possible distinction collapses · the
unique index is not built · the conflict is swallowed · the transaction is
removed · a legitimate multiple is forbidden · detection auto-repairs.

---

## Dependencies on earlier batches

| Batch 3 needs | From |
|---|---|
| The uniqueness protocol (skip-and-report on legacy duplicates, conflict translated to a business answer) | **Batch 1** |
| The attributable audit and its registered entities | **Batch 1** |
| The borrowed-transaction contract | **Batch 2** |
| `identity_state_findings()` | **Batch 2** |

**Nothing in Batch 3 requires reopening either batch.**

---

## Owner decisions required

| | Decision | Recommendation |
|---|---|---|
| **Q19** | Should `agencies` carry an optional `party_id`? | **Yes** — nullable, human-set, never inferred, never a uniqueness rule. It closes **R4** without claiming an agency *is* a partner |
| **Q20** | Should the detector report exact vs possible? | **Yes** — additive, and every other fix depends on being able to tell them apart |
| **Q21** | On a public exact match: refuse, or route to a claim flow? | **Route to a claim flow with a neutral message.** A refusal that says "this company already exists" discloses it to anyone who can type a name |
| **Q22** | Contact uniqueness key, and one primary? | **One primary now; the uniqueness key later.** The ambiguity is what actually misdirects |
| **Q23** | Portal e-mail uniqueness: global or per organisation? | **Global** — it is a login, and the product already assumes it |

**None of these is decided by this plan.**

---

## Scope confirmation

**This plan creates no Person hub, Organisation hub, merge engine, fuzzy identity
engine, duplicate engine, new permission engine or new KPI engine. No table is
merged, no history is migrated, and no product code was changed.**
