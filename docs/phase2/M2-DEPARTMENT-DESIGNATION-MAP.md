# Phase 2 · M2 — Department & Designation Map

*Where these two concepts live, who writes them, and the one decision still open.*

## 1. The authoritative masters (§6, §7)

Both already existed. Both are reused. Neither was duplicated.

| Concept | Where it lives | Values | System list? |
|---|---|---|---|
| **Department** | `lookup_values`, type key **`department`** | 17 | yes (`is_system=1`) |
| **Designation** | `lookup_values`, type key **`designation`** | 19 | yes (`is_system=1`) |

There is **no** `departments` table and **no** `designations` table, and M2
created neither. Tests pin this (`tests/test_m2_org_structure.php`).

A designation may optionally belong to a department, stored additively on the
lookup value as `attr_department` (`desig_set_department()`). Shared titles
(Manager, Intern) stay "General" so they are not duplicated per department.
This also already existed; M2 kept it.

## 2. The six concepts, kept separate (§4)

These are routinely confused. In EXAACT they are distinct and must stay so:

| Concept | Meaning | Stored as |
|---|---|---|
| **Security Role** | What you may *do* in the software | `users.role` + `permissions` |
| **Staff Title** | What a serving employee is *called* | `users.position_title` |
| **Internal Department** | Which function a person/seat belongs to | `department` master |
| **Vacancy Designation** | The title being *recruited for* | `requisitions.designation` |
| **Position** | A *sanctioned seat* with headcount and a reporting line | `positions` |
| **Requisition** | A *request to fill* seats | `requisitions` |

A Security Role is never a job title, and a job title never grants access.
M2 changed nothing about permissions.

## 3. Every screen that writes a department — audited (§11)

This is what M2 actually found, by tracing each form:

| Screen | Source of the list | Stores |
|---|---|---|
| Person quick-add (`views/detail.php`) | `department` master | **code** (`QUALITY`) |
| Team member (`views/ops/user_form.php`) | `department` master (datalist) | **label** (`Quality`) |
| Position master (`views/ops/positions.php`) | *was a bare text box* → **now** the master | **label** |
| Back-office staff (`lib/ops.php`) | *was a frozen constant* → **now** the master | **code** |
| Requisition (`views/ops/requisition_form.php`) | **`hr_department`** master | **code** |
| Candidate (`views/ops/candidate_form.php`) | **`hr_department`** master | **code** |

### The defect this caused, and the fix

Because some screens store the code and others the label, grouping on the raw
value split one real department into several. Reproduced end-to-end before
fixing:

```
BEFORE                                   AFTER
[Quality]  positions=1 sanctioned=3      [Quality]  positions=1 sanctioned=3
           people=1                                 people=2
[QUALITY]  positions=0 sanctioned=0
           people=1
```

The Department hub showed the headcount on one row and a person on another.
`dept_canon()` now resolves both spellings to the single label the master
already defines. **No stored value was rewritten** — it is a read-side
resolver, and a department the master does not recognise (genuine free text
typed on the old Position form) passes through unchanged, so nothing is lost.

## 4. Designation bindings — audited (§11)

| Screen | Source | Stores |
|---|---|---|
| Requisition | live `designation` master | code |
| Candidate | live `designation` master | code |
| Inspector | live `designation` master | code |
| Work norms | live `designation` master | code |
| Team member | live `designation` master | **label** |
| Back-office staff | *was a frozen constant* → **now** the master | code |
| Command Centre display | *was a frozen constant* → **now** the master | — |
| Partner contact (`views/detail.php`) | free text box | free text |

The two frozen-constant bindings meant a designation added in Settings never
appeared on the back-office form and rendered as a raw code in the Command
Centre. Both now read the live master, with the constant retained only as the
fallback for a workspace whose lookups have not been seeded.

## 5. OPEN DECISION — two department vocabularies (§21)

**This is reported, not resolved. M2 did not improvise a merge.**

Two department lists exist on the one lookup engine:

| | `department` | `hr_department` |
|---|---|---|
| Used by | people, positions, org chart, approvals, back-office | requisitions, candidates |
| System list | yes | no |
| Values | 17 | 8 |

Five `hr_department` codes have **no equivalent** in the authoritative master:

```
QAQC   NDT   HSE   HR   FINANCE
```

Three of those are arguably the same department under another name
(`QAQC`≈Quality, `HSE`≈Safety / HSE, `FINANCE`≈Commercial / Finance) and two
are genuinely new (`NDT`, `HR`).

**Why M2 stopped here.** Deciding whether "QA / QC" *is* the "Quality"
department is a business judgement about how the company is organised, not a
technical one. Acting on it means rewriting stored `requisitions.department`
and `candidates.department` values — a destructive migration of live
recruitment data. M2's stop conditions cover exactly this case.

**Consequence while it stands.** A requisition's department cannot be compared
with a position's department, so requisitions do not appear under their
department in the Department hub.

**Recommendation.** Treat `department` as the single master; add `NDT` and `HR`
to it; map `QAQC → QUALITY`, `HSE → SAFETY`, `FINANCE → COMMERCIAL` in a
one-off, reversible migration with a dry-run report; then point the requisition
and candidate forms at the authoritative master and retire `hr_department`.
This is a self-contained milestone. It needs a decision on the three mappings
first.

## 6. Not addressed, and why

- **`positions.grade` / `requisitions.grade`** are free text and no `grade`
  master is registered. Creating one is a new master, not a reuse, and was
  outside what the audit proved necessary. Recorded as an open item.
- **`lk_usage_map()`** (which powers "how many records use this value" on the
  Lookups screen) covers neither department nor designation. It maps one table
  per key, while department spans eight — a partial entry would under-report and
  mislead an admin into deleting a value in use. Left alone deliberately.
