# Phase 6 — Pre-Implementation Audit

*What exists today across Person, Organisation, Taxonomy and Marketplace — and
where the domains genuinely fail to converge. Written before any product code is
changed, as §2 requires. Every finding below was **measured** through a probe
that called production functions and read the database; none is inferred from
reading source alone.*

---

## The headline finding

**There are two person-identity mechanisms, and the one Recruitment uses is
invisible to the one everything else asks.**

A candidate converted into an inspector — the ordinary "we hired them" path —
records the fact in `candidates.inspector_id` and writes **nothing** to the
identity ledger `cx_identity_link`. Measured:

```
candidate #A converted to inspector #A the way the stage route does it
  candidates.inspector_id                        = the inspector      ✔ recorded
  rows in cx_identity_link for either record     = 0                  ✘ nothing
  connect_person_resolve('candidate', #A)        → inspectors: 0      ✘ blind
  connect_identity_roles(['inspector_id' => …])  → linked: false      ✘ "not linked"
```

The roles card `connect_identity_roles()` returns
`name, professional_id, inspector_id, is_professional, is_inspector, linked,
bench_count` — **it has no concept of a candidate at all.**

So the system already knows "this marketplace professional and this inspector are
one person", and separately "this candidate and this marketplace professional are
one person" — but the most common real-world case, *we recruited this person and
they now work for us*, is recorded in a different mechanism that the person
resolver never reads.

This is precisely the convergence Phase 6 exists to establish, and it needs
**CONNECT**, not BUILD: both mechanisms already exist and both are correct within
their own domain.

---

## 1. Person / identity — what exists

| Thing | Where | State |
|---|---|---|
| `cx_identity_link` | `lib/connect_identity.php` | **The right architecture already.** A link ledger, not a merge: `professional_id`, `inspector_id`, `candidate_id`, `party_id`, `method`, `status` (LINKED/UNLINKED), `linked_by`, `linked_at`, `unlinked_at`. Reversible, audited through `act_log()`, and it explicitly never merges or deletes |
| `connect_identity_link_create()` | same | Professional ↔ inspector. Guards both rows exist and neither is already linked elsewhere |
| `connect_identity_candidate_link_create()` | same | Candidate ↔ professional, same ledger |
| `connect_identity_suggestions()` | same | Deterministic suggestions from a **shared e-mail (primary) or mobile (secondary)**. **Never links automatically — a person confirms** |
| `connect_person_resolve($kind, $id)` | `lib/connect_person.php` | A **transitive** resolver: climbs from candidate or inspector to the professional hub, then back out to every linked record. A de-facto person spine already exists — the professional row is the hub |
| `candidates.inspector_id` | `lib/ops.php` | The Recruitment→Operations link. **A column, not the ledger** |
| `cx_applications.inspector_id` / `applicant_professional_id` | `lib/connect_market.php` | The original per-application bridge that predates the ledger |

**Assessment:** there is no missing identity engine. There is a **missing edge**
(candidate ↔ inspector) and a **resolver that does not read one of the two
mechanisms**.

### Duplicate control on the three person pools — measured

| Pool | Unique index |
|---|---|
| `cx_professionals` | **`ux_cx_pro_email` — UNIQUE on email** |
| `inspectors` | **none declared** |
| `candidates` | **none declared** |

The marketplace pool has deterministic duplicate control on the strongest
identifier. The two internal pools have none. That asymmetry is why a duplicate
person is easy to create on the Recruitment/Operations side and hard on the
marketplace side.

---

## 2. The candidate → inspector conversion (§9) — answered

The conversion lives in the `candidate-stage` route (`lib/ops.php`), fired when
the stage moves to `ACCEPTED` **and** `make_inspector` is posted **and** the
candidate has no inspector yet.

| §9 question | Measured answer |
|---|---|
| What creates the inspector? | A direct `INSERT INTO inspectors` in the stage route |
| What fields are copied? | name, first/middle/last, email, mobile, `trade_id`, `skill_ids` (from `skill_id`), `sbu`/`sbus`, designation, plus roll/agency commercials (`staff_kind`, `agency_id`, `roll_type`, `agency_name`, `agency_cost`, `placement_fee`, `fee_status`, `guarantee_upto`), `status='ACTIVE'` |
| What identity link is created? | **`candidates.inspector_id` only. No `cx_identity_link` row** |
| If inspector creation fails? | The route has **no transaction**. A failure leaves the stage moved and no inspector |
| If the identity link fails? | The `UPDATE candidates` is separate and unguarded — a failure leaves a **live inspector nobody points at** |
| Can duplicate inspectors be created? | **Yes.** The guard reads `inspector_id` from a row fetched *earlier* in the request — a check-then-write. Measured: two inspector rows for one person are accepted, the candidate ends up pointing at the second, and **the first stays live and orphaned** |
| How does rollback work? | **It does not.** No `beginTransaction`, no `rollBack` in the conversion block |
| Does the candidate stay historically linked? | Yes — `candidates.inspector_id` survives, and the candidate row is never deleted |

**This is a real integrity defect**, of exactly the class §9 anticipated. §9
authorises "the smallest compatible change". Two candidates for that change, to be
decided in the design step and not before: wrap the two writes in one transaction,
and give the pair a deterministic duplicate control that is not a PHP check.

---

## 3. Concurrency on the identity ledger itself (§29) — measured

```
indexes on cx_identity_link:
  ix_cx_idlink_cand   unique=0
  ix_cx_idlink_insp   unique=0
  ix_cx_idlink_pro    unique=0
```

**There is no unique constraint on the link pair.** Duplicate control is PHP-side
only — `connect_identity_link_create()` checks, then inserts. Proved: two
identical `LINKED` rows for the same professional and inspector coexist happily.

That is exactly the state a race would leave, and §29 says in terms: *use unique
constraints where appropriate; do not rely solely on PHP checks.*

---

## 4. Organisations — three representations, two of them joined

| Table | Owner | Role model | Statutory identifiers |
|---|---|---|---|
| **`business_partners`** | Core / Operations | **`is_client`, `is_vendor`, `is_subcontractor` — role flags on ONE row** | `gstin`, `pan`, `cin`, `tan`, `msme_udyam`, plus `parent_id` for group structure |
| `cx_organisations` | Connect / Marketplace | `org_type`, `package_key` | — · **carries `party_id` → `business_partners`** |
| `agencies` | Recruitment | `agency_type` | `gstin` only · **no cross-reference to `business_partners` at all** |

**`business_partners` is already the organisation spine §10 describes** — one
organisation with several business roles expressed as capabilities, not as
separate rows, and already carrying the evidence §11 wants for matching.

`cx_organisations` already cross-references it. **`agencies` does not** — it is a
third organisation representation with no join to the spine, so the same manpower
supplier can exist as both an agency and a business partner with nothing recording
that they are one organisation.

---

## 5. Taxonomy — two stacks, both legitimate

| Stack | Where | Covers |
|---|---|---|
| **Internal (Phase 2, LOCKED)** | `lib/deptorg.php` over `lookup_values` | **Department** (canonical, with aliases, synonyms, legacy mappings, hierarchy, pending mappings, audit) and **Designation**, linked by `desig_set_department()` |
| **Marketplace (Connect)** | `connect_taxonomy.php`, `connect_qualtax.php`, `connect_tax_graph.php` | `cx_sectors`, `cx_disciplines`, `cx_equipment_groups`, `cx_equipment_types`, `cx_materials`, `cx_inspection_stages`, `cx_standards`, `cx_certifications_registry`, `cx_qualification_levels`, `cx_job_families`, `cx_roles`, `cx_iti_trades`, `cx_prof_certifications` — plus a **graph**: `cx_tax_nodes` / `cx_tax_edges` / `cx_tax_aliases` / `cx_profile_tax` |

The graph already provides what §13 asks for: `tax_norm()` normalisation,
`cx_tax_aliases` for synonyms and abbreviations, `connect_tax_resolve()` for
term → canonical node, `connect_tax_suggest()` for suggestion, and typed edges
(`RELATED`, `SUGGESTS`).

**Neither stack should replace the other.** The Phase 2 Department architecture is
locked and authoritative for Department; the graph is far richer for discipline,
trade, specialisation and equipment. §3's requirement that *NDT be a Department
and independently a discipline/trade* is satisfied by keeping both and **mapping**
between them — which is what `cx_tax_aliases` and the node graph already exist to
do. No new taxonomy engine is needed.

---

## 6. Marketplace ↔ Recruitment demand (§14) — the relationship already exists

**Do not build an adapter. Phase 4 already is one.**

```php
RFUL_SOURCE_ENTITY['MARKETPLACE'] = [
    'table'  => 'cx_requirements',
    'module' => 'connect',
    'what'   => 'marketplace requirement',
];
```

A requisition reaches a marketplace requirement through a **Phase 4 fulfilment
allocation**: `requisition_allocations.source = 'MARKETPLACE'`,
`source_entity_id → cx_requirements.id`, existence-checked, and **refused outright
when the workspace has not bought Connect** (`NO_ENTITLEMENT`, never a hidden
option).

Because the allocation is bounded by Phase 4's COMMITTED ceiling, **one approved
demand cannot be promised twice** — that is exactly what Phase 4 proved and
locked.

Two measured gaps remain:

| Gap | Measured |
|---|---|
| The relationship is **one-directional** | `cx_requirements` has **no `requisition_id`**. From a marketplace requirement you cannot find the approved demand it serves |
| The marketplace requirement carries **its own headcount** | `cx_requirements.positions` is independent of the requisition. Nothing ties "3 seats allocated to MARKETPLACE" to "this requirement advertises 10 positions" |

The second is the §14 risk stated plainly: the allocation is capped, but the
*advertisement* is not. Whether that is a defect or correct behaviour (a
marketplace requirement may legitimately serve several buyers) is a design
question for the next step — **not something to assume either way here.**

---

## 7. Marketplace module ownership — who owns what

| Module | Owns |
|---|---|
| `connect_pro.php` | `cx_professionals`, `cx_pro_files` |
| `connect_credentials.php` | `cx_pro_certs`, `cx_pro_projects` |
| `connect_verify.php` | `cx_verifications` |
| `connect_ratings.php` | `cx_ratings` |
| `connect_bench.php` | `cx_bench`, `cx_bench_alloc` |
| `connect_market.php` | `cx_requirements`, `cx_applications` |
| `connect_org.php` | `cx_organisations` |
| `connect_identity.php` | `cx_identity_link` |
| `connect_tax_graph.php` | `cx_tax_nodes`, `cx_tax_edges`, `cx_tax_aliases`, `cx_profile_tax` |
| `connect_match.php`, `connect_trust.php`, `connect_deploy.php`, `connect_source.php`, `connect_bridge.php` | **No tables — pure logic over the above.** They are consumers, not owners |

That last row matters: five of the modules §15 lists own no data at all. They are
exactly the reuse surface Phase 6 should connect to, and exactly the wrong place
to add persistence.

---

## 8. Operations blast radius (§33) — measured

**51 library files read `inspectors`** (`FROM inspectors` or `JOIN inspectors`),
spanning assets, attendance, audits, billing, call profitability, competence,
complaints, compliance, confidentiality, the marketplace modules and more.

Any change to the inspector identity mechanism touches all of them. This is the
single strongest argument in the audit for **CONNECT / MAP and never MIGRATE**:
a link ledger costs those 51 files nothing, and a change of identity storage would
require regression evidence from every one of them.

---

## 9. REUSE → EXTEND → CONNECT → MAP → MIGRATE → DEPRECATE → BUILD

| Need | Decision | Why |
|---|---|---|
| Person identity relationships | **REUSE** `cx_identity_link` | It is already a reversible, audited link ledger that never merges |
| Transitive person view | **REUSE** `connect_person_resolve()` | The hub-and-spoke resolver already exists |
| Candidate ↔ inspector edge | **CONNECT** — the missing edge, on the existing ledger | The concept is already modelled for the other two pairs |
| The person resolver reading `candidates.inspector_id` | **EXTEND** the resolver | So one mechanism's facts are visible to the other; no data moves |
| Roles card reporting a candidate representation | **EXTEND** `connect_identity_roles()` | It currently cannot express the case at all |
| Candidate → inspector conversion integrity | **FIX** — smallest compatible change (§9) | Measured: not transactional, no rollback, duplicates permitted, first inspector orphaned |
| Duplicate control on the link ledger | **EXTEND** — a unique constraint (§29) | Measured: PHP-side only; two identical active links coexist |
| Organisation spine | **REUSE** `business_partners` | Already one row with role flags and statutory identifiers |
| `agencies` ↔ `business_partners` | **CONNECT** — a cross-reference, as `cx_organisations` already has | Measured: no join exists today |
| Department vocabulary | **REUSE** Phase 2, unchanged — it is LOCKED | Authoritative for Department |
| Technical taxonomy | **REUSE** the Connect graph and its aliases | Already does normalise / alias / resolve / suggest |
| Department ↔ marketplace taxonomy | **MAP** via the existing alias and node mechanisms | Satisfies "NDT is a Department *and* a discipline" without merging |
| Requisition ↔ marketplace requirement | **REUSE** the Phase 4 allocation — it already IS the adapter | Entitlement-gated and ceiling-bounded |
| Reverse lookup, requirement → demand | **OPEN QUESTION** — design step, not assumed here | Measured as absent; whether it should exist is a business decision |
| Marketplace / Recruitment / Operations engines | **UNTOUCHED** | §1 locks |

**No new identity engine. No new organisation engine. No new taxonomy engine. No
new marketplace engine. No new KPI engine. No physical merge of any table.**

---

## 10. What this audit did NOT establish

Stated so that the next step does not mistake silence for clearance:

- **Cross-tenant behaviour (§22) is argued, not yet probed.** Tenancy is
  structural (one database per tenant), so a cross-tenant link should be
  impossible by construction — but §22 demands it be *tested*, and it has not been.
- **Branch scope (§23) on identity and organisation links is not yet mapped.**
  Which of these entities are GLOBAL, TENANT, ORGANISATION, BRANCH, USER or
  PROJECT scoped is a decision the next document must record.
- **The action-path matrix (§26) is not yet enumerated.** Which UI, GET, POST,
  AJAX, API, import, bulk and background paths can create each relationship —
  including the ones that are ABSENT — remains to be swept.
- **`connect_match`, `connect_trust`, `connect_deploy`, `connect_source` and
  `connect_bridge` were surveyed for ownership only**, not for their matching
  semantics. They own no tables, which is what mattered for the convergence
  decisions above.
- **Legacy data volumes (§30) are unmeasured.** The counts seen during this audit
  came from a freshly-provisioned test workspace and say nothing about a real
  installation. Reconciliation (§31) must be run against real data.

No product code was changed by this audit. The probe used to obtain these
measurements was a throwaway and has been removed.
