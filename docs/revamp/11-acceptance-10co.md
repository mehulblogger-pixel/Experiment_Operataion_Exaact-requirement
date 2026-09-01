# 11 — 10-Company Acceptance Gate (Stage 9)

The master prompt's acceptance matrix (§38) and Definition of Done (§40), made
runnable. `phpapp/tools/acceptance-10co.php` drives each archetype A–J: it sets
the company as the **operating company** with its real capability mix and asserts
the workspace the Combination Engine gives it, then checks the cross-cutting
product invariants. It self-seeds a throwaway SQLite DB and never touches real
data.

```
php tools/acceptance-10co.php     # exit 0 = ALL PASS, 1 = a failure
```

## What each archetype must see

`✓` shown (expected) · `·` hidden (expected). Ops = Operations, Rec = Recruitment
(hr), Rep = Reporting, Qual = Quality/ISO inspection registers. Money, Sales,
Marketplace and Admin are universal (never gated) and are asserted for every row.

| ID | Company | Ops | Rec | Rep | Qual |
|---|---|:---:|:---:|:---:|:---:|
| A | Pure TPIA | ✓ | · | ✓ | ✓ |
| B | Technical Manpower Supplier | ✓ | ✓ | · | · |
| C | Freelance Resource Supplier | ✓ | ✓ | ✓ | · |
| D | Technical Recruitment | · | ✓ | · | · |
| E | Project Management | ✓ | · | · | · |
| F | TPIA + Technical Staffing | ✓ | ✓ | ✓ | ✓ |
| G | TPIA + Freelance Supplier | ✓ | ✓ | ✓ | ✓ |
| H | Staffing + Recruitment | ✓ | ✓ | · | · |
| I | TPIA + Staffing + PM | ✓ | ✓ | ✓ | ✓ |
| J | Full multi-capability | ✓ | ✓ | ✓ | ✓ |

A pure recruiter (D) loses field Operations and inspection Reporting/Quality; a
pure TPIA (A) or project company (E) loses Recruitment; everyone keeps the
universal areas. Each archetype gets a coherent, capability-appropriate app — the
§40 product invariant.

## Product invariants asserted

- Capability catalogue populated (≥20) across ≥4 groups.
- **Freelance Supplier is a first-class capability** (FREELANCE_SUPPLY +
  FREELANCE_INSPECTOR_SUPPLY).
- A company can hold **many** capabilities at once.
- An **unset** operating company is **fully permissive** — every existing install
  is unaffected until an admin tailors it.

Last run: **ALL PASS** (10/10 archetypes + 5/5 invariants).
