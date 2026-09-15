# Phase 2 · M3 — Legacy Department Mapping

*What exists, what it means, and what was deliberately left for you to decide.*

## 1. The rule this document follows

**No destructive migration, and no guess.** Every value below is preserved
exactly as stored. Where the meaning is obvious the term already resolves; where
it is a judgement about how your company is organised, it is presented as a
question on the **Words to confirm** screen.

## 2. The sources

| Source | Where | Values | Status after M3 |
|---|---|---|---|
| `lookup_values` type `department` | tenant DB, system list | 17 | **authoritative** — the canonical Department master |
| `lookup_values` type `hr_department` | tenant DB | 8 | **legacy** — kept, not deleted; values offered for a decision |
| `RECRUIT_DEPARTMENTS` constant | `lib/lookups.php:379` | 8 | unchanged — a starter set for recruitment-only installs |

## 3. Per-value assessment (§25, §26)

| Value | Source | Current consumers | Resolves today? | Canonical candidate | Decision needed |
|---|---|---|---|---|---|
| `INSPECTION` | both | requisitions, candidates, people, positions | **yes** — identical in both lists | Inspection | none |
| `ENGINEERING` | both | as above | **yes** — identical in both lists | Engineering | none |
| `COMMERCIAL` | both | as above | **yes** — same code | Commercial / Finance | wording only |
| `QAQC` / `QA / QC` | `hr_department` | requisitions, candidates | **no** — pending | *Quality?* | **yes** |
| `HSE` / `HSE / Safety` | `hr_department` | requisitions, candidates | **no** — pending, suggested at 80% | *Safety / HSE?* | **yes** |
| `FINANCE` | `hr_department` | requisitions, candidates | **no** — pending, suggested at 80% | *Commercial / Finance?* | **yes** |
| `HR` | `hr_department` | requisitions, candidates | **no** — pending, no suggestion | new department | **yes** |
| `NDT` | `hr_department` | requisitions, candidates | **no** — pending, no suggestion | **possibly not a department at all** | **yes** |

### The `NDT` caution

`NDT` already exists in the **`trade`** master as a discipline, alongside
Welding, Painting & Coating and Instrumentation. Filing it as a *department*
may be mixing organisation structure with professional taxonomy — the thing §3
explicitly separates. Worth deciding deliberately rather than by default.

## 4. The four questions only you can answer

1. Is **QA / QC** the same department as **Quality**, or a separate one?
2. Is **HSE / Safety** the same as **Safety / HSE**? (Almost certainly yes.)
3. Does **Finance** sit inside **Commercial / Finance**, or stand alone?
4. Is **NDT** a department, or a discipline that people in other departments hold?

## 5. How to answer them — no migration required

Open **Departments → Words to confirm**. Each pending word is listed with
EXAACT's suggestion. Choose the department it means, or mark it "Not a
department".

What happens on confirmation:

- the word becomes an approved term for that department;
- every existing record keeps the value it already stores — **nothing is
  rewritten**;
- from then on, the hub, the org chart, reports, search and approval routing all
  treat that word and the department as one thing;
- the public careers page shows the department's name instead of a raw code.

If a word means something genuinely new, create the department on
**Departments → Department list** and point the word at it.

## 6. Reversibility (§47)

A mapping is one row in `lookup_terms`. Removing it on the department's edit
screen undoes the decision completely — because no stored department value was
ever changed, there is nothing to roll back in the business data.

That is the whole reason M3 resolves at read time rather than migrating: the
decision stays reversible until you are sure.

## 7. Deprecating `hr_department` — not yet

§27 permits retiring a legacy source only once every consumer is migrated or
adapted. Today:

- **Display** is adapted — `dept_label()` resolves any stored value, from either
  list, for showing.
- **Approval routing** is adapted — it compares canonical departments.
- **Entry** is not yet moved: the requisition and candidate forms still offer the
  `hr_department` list.

Moving entry to the canonical master should follow your answers to §4, so that
new requisitions land on departments that exist rather than widening the split.
Until then both vocabularies read correctly, and nothing is lost either way.
