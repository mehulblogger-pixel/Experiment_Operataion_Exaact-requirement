# Phase 2 — Closing the Department Forms

*The deferred item from the vocabulary milestone. Requisition and candidate
entry now uses the canonical Department.*

## 1. What was left open, and why

The vocabulary milestone unified how a department is **read**. It deliberately
left **entry** alone: the requisition and candidate forms still offered the
legacy `hr_department` list, so a requisition raised today still wrote a value
the canonical master did not know.

It was deferred because the obvious fix — rewriting every stored department onto
canonical values — is the destructive migration the milestone forbids, and
because the five ambiguous legacy words still need a customer decision.

## 2. How it is closed without a migration

Additively. The record now carries **both**:

| Column | Holds | Why |
|---|---|---|
| `department` | text — for new records, the canonical **code** | every existing reader keeps working, untouched |
| `department_id` | the canonical **identity** (NEW) | survives a rename or a code change |

On both `requisitions` and `candidates`. **No stored value was rewritten**, and
the original free-text column was not dropped.

## 3. What a user sees

The Department box on a requisition or a candidate now lists the canonical
departments, indented by hierarchy — and nothing else. The legacy hiring-only
codes are gone from *new* entry; they are decided on **Departments → Words to
confirm**.

One thing that matters more than it looks: if a record already holds a legacy or
free-text value, the picker **keeps offering that value**. Opening an old
requisition never silently blanks its department. A mutation removing that
behaviour fails the suite.

## 4. What a save does

```
picked "Quality"            ->  department = 'QUALITY'   department_id = 90
picked an unknown wording   ->  department = as typed     department_id = NULL
picked nothing              ->  department = ''           department_id = NULL
```

Wording the master does not recognise is **preserved exactly as typed** and gets
no identity. Nothing is guessed into a department it is not.

## 5. What reading does

`dept_of_row()` / `dept_row_label()` take the identity first and fall back to the
wording. So:

- a record written today resolves by identity — a rename follows it;
- a record written years ago resolves by wording — and still displays a readable
  name rather than a raw code;
- a record whose identity has been deleted falls back to its wording rather than
  losing its department.

Applied to the Command Centre, the three CSV exports, offer and appointment
letters, and the public careers page. **A stored code no longer renders raw
anywhere.**

## 6. A fresh install no longer depends on where you click first

Closing this needed `department_id` on `requisitions`, whose columns live in
`req_migrate()` — the lazy, route-triggered migration the earlier quantity
finding recorded. A fresh database therefore did not carry `quantity`,
`start_date` or `department_id` until somebody happened to open a requisition
page.

`req_migrate()` is now called from `boot()`. It is additive and epoch-guarded, so
this is idempotent, and a freshly started database now reports **79 columns** on
`requisitions` instead of 25. `M2-QUANTITY-COLUMN-FINDING.md` is updated and
marked closed.

## 7. Testing

`tests/test_m3_department_forms.php` — **62 assertions**: structure, fresh
install, the picker (including that it keeps a legacy value), what a save means,
what reading means, both handlers resolving, no raw codes in any renderer, and
the negative cases.

| Engine | Full suite |
|---|---|
| SQLite | **8484 passed, 0 failed** |
| MariaDB 10.11.14 | **8485 passed, 0 failed** |

| # | Mutation | Result |
|---|---|---|
| F1 | The save no longer records the identity | **4 failed** ✅ |
| F2 | The picker drops an existing legacy value (old requisitions silently blanked) | **3 failed** ✅ |
| F3 | Reading ignores the identity | **2 failed** ✅ |
| F4 | Unknown wording is invented into a department | **2 failed** ✅ |
| F5 | The candidate handler stops resolving | **1 failed** ✅ |
| F6 | Requisition structure goes back to route-triggered only | **1 failed** ✅ |

**F3 survived the first time.** The rename test passed under it, because the text
column also holds the canonical code, so a fallback found the same department
anyway — the test did not prove what it claimed. It was replaced with the case
that genuinely separates the two: a record whose *wording* cannot resolve but
whose identity is sound. F3 is caught now. Recorded because a surviving mutation
is a defect in the test, not a detail to quietly fix.

## 8. What is still deliberately open

The five legacy words — `QAQC`, `NDT`, `HSE`, `HR`, `FINANCE` — still await a
customer decision on **Departments → Words to confirm**. Existing records holding
them read correctly; only *new* entry has moved. Confirming a word writes one
term row and rewrites no data.
