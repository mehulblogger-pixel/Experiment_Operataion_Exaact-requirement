# Phase 2 · M3 — Department & Controlled Vocabulary Architecture

**The decision: EXAACT standardises identity, not terminology.**

A customer calls a department whatever is right for their organisation. Once a
term is approved, EXAACT knows exactly which canonical department it means. When
EXAACT does not know a term, it says so — and the customer can teach it, or
create something new. The vocabulary is never a closed dictionary.

## 1. REUSE → EXTEND, not BUILD (§4)

The audit found the lookup engine already provided most of what a canonical
Department needs. So M3 **extended** it. **No `departments` table was created,
and no second department authority exists.**

| Requirement | Already there? | What M3 did |
|---|---|---|
| Stable identity | ✅ `lookup_values.id` | reused unchanged |
| Code | ✅ `code` | reused; uniqueness now enforced on save |
| Canonical name | ✅ `label` | reused |
| **Hierarchy** | ✅ `parent_value_id` | reused; added cycle prevention |
| Status | ✅ `active` | reused |
| Ordering | ✅ `sort_order` | reused |
| Per-tenant | ✅ one database per customer | reused |
| Display name ≠ canonical | ❌ | added `display_name` |
| Description, type, head, dates, external ref | ❌ | added as generic columns |
| **Synonyms / aliases** | ❌ | **new `lookup_terms` table** |
| Term types | ❌ | `lookup_terms.term_type` |
| Approval trail | ❌ | `status` + `approved_by` + `approved_at` |

## 2. The three layers

```
lookup_types      the vocabulary      "department", "designation", "skill" …
      │
lookup_values     the CANONICAL values — id is the identity, forever
      │           label · display_name · code · parent_value_id · active …
      │
lookup_terms      every word that RESOLVES to a canonical value          (NEW)
                  term · term_norm · term_compact · term_type · status …
```

A value's own code and label are registered as terms too, so matching has
exactly **one** index to consult.

```
Human Resources  (lookup_values.id = 1255 — never changes)
    ├── "Human Resources"   CANONICAL
    ├── "HR"                ABBREVIATION
    ├── "H.R."              ABBREVIATION
    ├── "Personnel"         LEGACY
    ├── "People & Culture"  CUSTOMER_TERM   ← what this customer sees
    └── "People Ops"        SYNONYM
```

Renaming the display wording changes none of this. Identity is the id.

## 3. Deliberately generic (§11)

Nothing in `lib/vocab.php` is department-specific. `vocab_match()`,
`vocab_term_add()`, `vocab_resolve()` and the rest take a vocabulary key.
Department is simply the first adopter; Designation, Skill, Discipline, Sector
and the rest can adopt it later without schema change.

M3 deliberately did **not** convert every master — §11 says to build the
foundation and apply it to Department.

## 4. Separation of concepts (§3)

Department answers *where in the organisation does this belong* — and nothing
else. M3 keeps these apart and the tests pin it:

```
Organisation: Organisation · Business Unit · Division · DEPARTMENT · Branch
Job:          Job Family · Job Profile · Designation · Position · Vacancy
Professional: Sector · Discipline · Domain · Specialisation · Skill · Certification
Security:     User · Role · Permission
```

Department Type (`CORPORATE`, `TECHNICAL`, `QUALITY` …) is descriptive only. It
grants nothing — §8.

Note from the audit: **`NDT` already exists in the `trade` master as a
discipline.** Treating it as a department would conflate organisation structure
with professional taxonomy. It is therefore left for the customer to decide, not
merged.

## 5. Tenancy and cache (§22, §23, §34)

EXAACT gives each customer **its own database**. There is no `tenant_id` and no
shared term table, so vocabulary isolation is *structural*: tenant A's terms are
in tenant A's database and unreachable from tenant B.

The one real risk is a cache outliving the database it was read from — proven
real in M2. Every cache added by M3 is keyed on `db_epoch()`, and a mutation test
removing that key fails the suite.

"Global vocabulary" here means the defaults seeded from code; "tenant
vocabulary" means the rows in the customer's own database. A tenant can diverge
locally but cannot alter the global set.

## 6. Permissions and entitlement (§35, §36)

| Action | Bar |
|---|---|
| View the department hub | coordinator level |
| File a designation under a department | coordinator level |
| Create / rename / deactivate a department | **admin level** |
| Approve or reject a term | **admin level** |

The admin guard runs **once, before any edit action** — not per action — and a
test pins that ordering. No parallel permission mechanism was created.

Department vocabulary correctly sits on the core side (`admin` is the only CORE
product module). The audit found one inconsistency: `/departments` is ungated but
rendered the establishment (positions and sanctioned headcount), which belongs to
the paid **hr** module. `dept_hub()` now asks `licence_enabled('hr')` before
computing any of it — shared vocabulary must not become a way into a module the
customer has not bought.

## 7. Legacy `hr_department` (§27)

```
Legacy hr_department value   →   recorded as a PENDING term   →   customer decides   →   canonical Department
```

`hr_department` is **not deleted** and **not merged**. Its values are offered for
a decision on a "Words to confirm" screen. Until a customer approves one, it
resolves to nothing and displays through the legacy list — so nothing breaks and
nothing is guessed.

Exactly two of its values (`INSPECTION`, `ENGINEERING`) already resolve, because
they are character-identical to canonical departments. The other five are
presented as questions.

## 8. What was not done, and why

- **No mass migration.** §47's preconditions are not all met: the ambiguous
  values are identified but the customer has not decided them. Migration without
  that decision would be a guess.
- **The requisition and candidate forms still offer `hr_department`.** Switching
  them to the canonical master is correct (§28) but should follow the customer's
  decisions on the five pending words, so that new data lands on values that
  exist rather than creating a fresh split. The display side is already unified,
  so both old and new values render correctly today.
- **No other master was converted.** §11 — foundation first.
- **No AI.** §45 — matching is deterministic: exact, normalised, approved
  synonym, then a Levenshtein/containment score that can only ever *suggest*.
