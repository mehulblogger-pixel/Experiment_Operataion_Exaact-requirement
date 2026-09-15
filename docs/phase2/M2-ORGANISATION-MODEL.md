# Phase 2 · M2 — The Organisation Model

*What EXAACT already knows about how a company is arranged, and what M2 changed.*

## 1. The short version

EXAACT already had a complete organisation model. M2 did **not** design a new
one and did **not** add a single new table. What M2 found was that the model
was sound but was being *read* inconsistently — three screens wrote the same
department in different ways, so one real department showed up as several.
That is what M2 fixed.

## 2. The hierarchy that exists today

```
Company  (= one database — there is no tenant_id column; each workspace
          is a separate database, selected per request)
  │
  ├── Office / Branch          table: offices
  │     ├── parent_office_id   → offices nest inside offices (a real tree)
  │     ├── office_type        HEAD OFFICE / BRANCH / SITE …
  │     ├── region, sbus       where it sits, what business it does
  │     └── head_user_id       who runs it
  │
  ├── Department               master: lookup_values, type "department"
  │     └── (a label, not a table — see §4 for why that is correct)
  │
  └── Position                 table: positions  ("the establishment")
        ├── department, sbu, grade, level
        ├── office_id          which branch the seat belongs to
        ├── reports_to_id      → positions (the reporting line / org chart)
        └── sanctioned / occupied / budgeted headcount
```

**Verdict on §5 (the minimum hierarchy actually needed):** it is already
present and it is enough. Company → Office tree → Department → Position →
Person. No additional organisational layer was required, so none was added.

## 3. What each level is for

| Level | Table | Answers |
|---|---|---|
| Company | *(the database)* | Whose data is this? |
| Office / Branch | `offices` | Where does the work happen? Who sees it? (branch scope) |
| Department | `lookup_values` | What function is this? (Quality, Engineering…) |
| Position | `positions` | What seat exists, under whom, with how many sanctioned heads? |
| Person | `users`, `inspectors` | Who actually sits there? |

Branch scope (`scope_office_clause()` / `scope_office_allows()`, hardened in
M14/M16) already enforces "which offices may this person see" at both the list
and the record level. M2 changed none of it.

## 4. Why Department is a master list and not a table

A department in EXAACT carries no data of its own — no budget, no address, no
separate permissions, no lifecycle. It is a *name you group by*. The
`lookup_values` engine already provides exactly that: an admin-editable list,
per workspace, with codes, labels, ordering and an active flag.

Creating a `departments` table would have produced a second master for the same
concept — which M2 was explicitly told not to do, and which would have been the
wrong call regardless. **The lookup master is authoritative.** M2's job was to
make the application actually behave as though that were true.

## 5. What M2 changed

Nothing structural. Four read-side corrections:

1. **`dept_canon()`** (new, `lib/deptorg.php`) — resolves a stored department
   value to the one label the master already defines for it, whether the value
   was stored as a code (`QUALITY`) or a label (`Quality`). Applied where the
   app *groups* by department: `dept_names()`, `dept_hub()`, `dept_org_groups()`.
   Nothing stored is rewritten; an unrecognised value passes through untouched.
2. **Back-office staff form** now reads the live designation and department
   masters instead of a frozen PHP constant.
3. **Recruitment Command Centre** renders designations from the live master, so
   a newly added title shows its label instead of a raw code.
4. **Position master** — the Department box is now steered by the master list
   (a datalist, the pattern already used on the team-member form) while still
   accepting anything already typed.

## 6. What M2 deliberately did not change

- `hired_inspector_id`, `status='HIRED'`, vacancy quantity, fill count and
  closure rules — see `M2-MULTI-VACANCY-BOUNDARY.md`.
- `pipelines` / `pipeline_stages` (Sales) and `recruit_pipelines` /
  `recruit_stages` (Recruitment) — both untouched, both still the only engines.
- Candidate ↔ agency relationships.
- The `hr_department` vocabulary — see the open decision in
  `M2-DEPARTMENT-DESIGNATION-MAP.md` §5. Merging it needs a business decision
  and a data migration, so it was reported rather than improvised.
