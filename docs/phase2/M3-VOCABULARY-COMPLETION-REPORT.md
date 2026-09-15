# Phase 2 · M3 — Completion Report
### Department Vocabulary Consolidation & Controlled Vocabulary Foundation

**The architectural decision, now implemented: EXAACT standardises identity, not
terminology.** A customer calls a department whatever is right for their
organisation. Once a word is approved, EXAACT knows exactly which department it
means. When EXAACT does not know a word, it asks rather than guesses — and the
customer can teach it or create something new.

## 1. What the audit found (§2)

Produced before any code changed: `M3-DEPARTMENT-VOCABULARY-AUDIT.md`.

- **Three** department vocabularies, **no** department master table, **no**
  department CRUD screen anywhere, and **two** uncontrolled ways for new
  department values to appear (the organogram import and "Other (add new)…",
  neither with duplicate detection).
- Nine department columns across eight tables; `inspectors` has none at all.
- Four defects reproduced end-to-end, one of which **silently bypassed a
  configured approval chain**.

## 2. What was built

**EXTEND, not BUILD.** The lookup engine already provided stable identity, code,
label, hierarchy (`parent_value_id`), active flag and per-tenant storage. M3 added
what it lacked.

| Piece | File |
|---|---|
| Generic controlled-vocabulary engine | `lib/vocab.php` (new) |
| `lookup_terms` — every word that resolves to a value | created by `vocab_migrate()` |
| 10 additive columns on `lookup_values` | `display_name`, `description`, `attr_type`, `attr_owner_id`, `effective_from/to`, `external_ref`, `source`, `updated_by/at` |
| Canonical Department layer | `lib/deptorg.php` |
| Department list + "Words to confirm" screens | `views/ops/department_admin.php` (new) |

Nothing is department-specific in `lib/vocab.php` — Designation, Skill and the
rest can adopt it later without schema change (§11).

## 3. The three defects fixed

| | Defect | Fix |
|---|---|---|
| **1** | An offer approval rule scoped to a department **never fired** on a requisition raised through the standard form — free-text rule vs coded value from a different list | `appr_match()` compares canonical departments. Matches any **approved** word; still refuses an unapproved one |
| **2** | The **public careers page** showed applicants raw codes (`QAQC`) | Rendered through `dept_label()` |
| **3** | `/departments` is ungated but rolled up **positions and sanctioned headcount**, which belong to the paid `hr` module | `dept_hub()` asks `licence_enabled('hr')` before computing any of it |

Each was reproduced first. Each is pinned by tests and by a mutation.

## 4. Testing

| Engine | Passed | Failed |
|---|---|---|
| SQLite | **8422** | **0** |
| MariaDB 10.11.14 | **8423** | **0** |

Whole suite, both engines. M3 adds **100 assertions** (~41 scenarios). **Nine
mutations**, all caught. No test weakened, no skip introduced. Full detail in
`M3-VOCABULARY-TEST-RESULTS.md`.

Worth recording: the first full run failed on `every table-creating migration is
wired into boot() — MISSING: vocab_migrate`. The existing suite caught an
architectural omission in new work. Fixed and re-run.

## 5. The four questions that are still yours

M3 deliberately merged nothing. Five legacy hiring-department values are waiting
on **Departments → Words to confirm**:

```
QAQC      NDT      HSE      HR      FINANCE
```

1. Is **QA / QC** the same department as **Quality**, or separate?
2. Is **HSE / Safety** the same as **Safety / HSE**? (Suggested at 80%.)
3. Does **Finance** sit inside **Commercial / Finance**, or stand alone? (80%.)
4. Is **NDT** a department at all — or a *discipline*? It already exists in the
   `trade` master alongside Welding and Instrumentation. Filing it as a
   department may be mixing organisation structure with professional taxonomy.

Answering takes four clicks and **rewrites no data**. Details in
`M3-LEGACY-DEPARTMENT-MAPPING.md`.

## 6. The twenty questions (§54)

**1. What is now the single authoritative Department source?**
`lookup_values` rows of type `department`. `lookup_values.id` is the canonical
identity. There is no `departments` table and no second authority; a test asserts
neither exists.

**2. What happened to `department`?**
It became authoritative and gained canonical attributes (display name,
description, type, head, effective dates, external reference) plus an approved-term
layer. Its values, codes and ids are unchanged.

**3. What happened to `hr_department`?**
Nothing was deleted or merged. It remains as a legacy list. Two of its values
(`INSPECTION`, `ENGINEERING`) already resolve because they are identical to
canonical departments; the other five are recorded as **pending** words for a
person to decide. It is no longer an independent authority for display or
approval routing, but it is still the list the requisition and candidate forms
offer — see Q15.

**4. What happened to existing `lookup_values` department values?**
Untouched. Each had its own code and label registered as approved terms so the
matcher has one index.

**5. Where are synonyms stored?**
`lookup_terms`: `term`, `term_norm`, `term_compact`, `term_type`, `lang`,
`source`, `status`, `suggested_value_id`, `approved_by`, `approved_at`.

**6. How does exact matching work?**
Level 1 is a character-for-character match against an approved term.

**7. How does synonym matching work?**
Levels 2–3 match the normalised or compacted form against approved terms.
`H.R.` → `h r` → compact `hr` reaches `HR`. Level 3 is reported when the matched
term is a synonym, alias, legacy, customer term, acronym or translation.

**8. How are ambiguous values handled?**
They are never resolved. Levels 4–5 return a scored suggestion and **no value**;
`vocab_resolve()` returns null below level 3. A mutation making a suggestion
resolve fails the suite.

**9. How can a customer create a completely new value?**
**Departments → Department list → Add a department.** Near-duplicate names are
shown first, but the customer can confirm and create anyway. A duplicate *code*
is always refused. The vocabulary is never a closed dictionary.

**10. How is tenant isolation guaranteed?**
Structurally: EXAACT gives each customer its own database, so there is no shared
term table. The only real risk is a cache outliving its database — every M3 cache
is keyed on `db_epoch()`, and a mutation removing that key fails the suite.

**11. How is branch scope guaranteed?**
Departments are organisation-wide by design (§30) — `HR-Ahmedabad` / `HR-Mumbai`
is the anti-pattern the model avoids. Branch scope continues to be enforced where
it belongs, on the records (`scope_office_clause()` / `scope_office_allows()`,
hardened in M14/M16). M3 changed none of it.

**12. How is entitlement enforced?**
Vocabulary administration sits on the core side (`admin` is the only CORE
module), guarded by role. M3 additionally **closed** a gap: the establishment
rollup on the ungated `/departments` route now follows `licence_enabled('hr')`.
No entitlement check was weakened, and a master user still does not bypass
entitlement.

**13. How are historical values preserved?**
Nothing stored was rewritten. Resolution happens at read time. An unknown value
displays exactly as stored. Deactivating a department never deletes it.

**14. What was migrated?**
**No business data.** Only additive schema: one new table and ten nullable
columns. The `department` values had their own code and label registered as
terms — new rows, no edits.

**15. What was deliberately not migrated?**
The five ambiguous legacy values — they need your decision, and nothing was
guessed. (The requisition and candidate **forms** were also deferred at the time
of this report; they have since been moved onto the canonical master — see
`M3-DEPARTMENT-FORMS.md`. No stored department value was rewritten in doing so.)

**16. What remains ambiguous?**
`QAQC`, `NDT`, `HSE`, `HR`, `FINANCE` — the four questions in §5 above. Also
recorded: `positions.grade` and `requisitions.grade` remain free text with no
grade master (carried from M2), and `lk_usage_map()` still does not cover
department or designation.

**17. What tests prove the implementation?**
`tests/test_m3_department_vocabulary.php` — 100 assertions across foundation,
normalisation, CRUD, identity-vs-wording, matching priority, conflict, hierarchy,
duplicates, negative input, legacy handling, approve/reject, the three fixes,
permissions and tenant cache.

**18. What was mutation-tested?**
Nine guards: conflict refusal, suggestion-never-resolves, cycle prevention,
duplicate detection, the tenant cache epoch key, the entitlement guard, canonical
approval matching, the admin permission guard, and the term reconcile that keeps
a department added the old way matchable. All nine were caught.

**19. What was tested on MariaDB/MySQL?**
The **whole suite**, on MariaDB 10.11.14 over TCP — **8423 passed, 0 failed**,
including every M3 assertion. Actually run, not inferred from SQLite.

**20. What existing modules were regression-tested?**
All of them, via the full suite: Operations, Quality, Reporting, Money, Sales,
Recruitment, Workforce, Marketplace/Connect, Identity, Organisation, Dashboard,
Permissions and Entitlement.

## 7. Protected areas — untouched (§51, §52)

Operations, Quality, Dashboard, Reporting, Money, Marketplace/Connect, the
identity architecture, the approval **engine** (only its department comparison
changed), the pipeline engines, the custom-field engine and the audit/activity
system. No hiring-request workflow, no requisition approval workflow, no
multi-source fulfilment, no KPI/SLA, no identity convergence, no new RBAC,
approval or pipeline engine, no multilingual platform, no AI matching platform.

The M2 boundary also still holds: `hired_inspector_id`, `status='HIRED'`,
vacancy quantity, fill count and closure rules are all unchanged.

## 8. Known limitations

- ~~Requisition and candidate **entry** still uses the legacy list (Q15).~~
  **CLOSED** — both forms now offer the canonical Department master and record
  the Department identity (`department_id`) alongside the existing text column.
  See `M3-DEPARTMENT-FORMS.md`.
- Multi-language is a *foundation* only — `lang` exists and is matched on; no
  translation UI was built (§24 says not to).
- Similarity scoring is deterministic (prefix / containment / Levenshtein). It
  will not spot a synonym that shares no characters — which is exactly why
  approval exists.
- The suggestion offered at seed time depends on what already exists in the
  master; a department created later will not retro-suggest against older
  pending words until they are looked at.

## 9. Phase 1 status — unchanged

Still **not** formally closed. MilesWeb production UAT remains pending, as do:
which build is live there, the config/database incident on that host, and e-mail,
never tested anywhere. See `docs/phase1/PHASE1-PENDING-WORK.md`.

## 10. HARD STOP (§55)

M3 stops here. No next recruitment feature has been started. Awaiting
architectural review and your answers to the four questions in §5.
