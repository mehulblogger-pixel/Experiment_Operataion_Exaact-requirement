# Phase 2 · M3 — Department Vocabulary Audit

*Produced before any structural change, per §2. Nothing in the application was
modified to produce this document.*

## 1. Summary

EXAACT has **three** department vocabularies, **no** department master table,
**no** department CRUD screen, and **two** uncontrolled ways for new department
values to appear. Four defects were reproduced end-to-end, one of which silently
bypasses a configured approval chain.

Tenant isolation, by contrast, is structurally sound: EXAACT gives each customer
its own database, so vocabulary cannot leak between tenants except through
caching — which M2 already found and fixed.

## 2. Where department is stored

Nine columns, across eight tables. Taken with every lazy migration run (the M2
`quantity` lesson — a schema read without `*_migrate()` understates the truth).

| Table | Column | Type |
|---|---|---|
| `back_office_staff` | `department` | VARCHAR(40) |
| `candidates` | `department` | VARCHAR(60) |
| `partner_contacts` | `department` | VARCHAR(120) |
| `positions` | `department` | VARCHAR(160) |
| `requisitions` | `department` | VARCHAR(60) |
| `users` | `department` | VARCHAR(120) |
| `recruit_approval_rules` | `applies_department` | VARCHAR(160) |
| `recruit_pipelines` | `applies_department` | VARCHAR(160) |
| `lookup_values` | `attr_department` | VARCHAR(160) |

**There is no `departments` table.** Every one of these is a loose string.
Note also that `inspectors` has **no** department column at all — the workforce
register is filed by designation and office only.

## 3. The three vocabularies

| # | Vocabulary | Where | Values | Written by |
|---|---|---|---|---|
| 1 | `lookup_values` type **`department`** | tenant DB, `is_system=1` | 17 | people, positions, org chart, approvals, back-office |
| 2 | `lookup_values` type **`hr_department`** | tenant DB, `is_system=0` | 8 | **requisitions, candidates** |
| 3 | `RECRUIT_DEPARTMENTS` constant | `lib/lookups.php:379` | 8 (different again) | swapped into vocabulary 1 by `lk_replace_if_default()` on a recruitment-only install |

Only two lookup **type keys** exist (`department`, `hr_department`) — the surface
is bounded, which makes consolidation tractable.

Five `hr_department` codes have no equivalent in the authoritative master:
`QAQC`, `NDT`, `HSE`, `HR`, `FINANCE`.

## 4. The value-space split

The same column is written as a **code** by some screens and as a **label** by
others:

| Screen | Source | Stores |
|---|---|---|
| Partner contact (`views/detail.php`) | `department` master | **code** — `QUALITY` |
| Back-office staff (`lib/ops.php`) | `department` master | **code** |
| Requisition (`views/ops/requisition_form.php`) | **`hr_department`** master | **code** — `QAQC` |
| Candidate (`views/ops/candidate_form.php`) | **`hr_department`** master | **code** |
| Team member (`views/ops/user_form.php`) | `department` master, datalist | **label** — `Quality` |
| Position master (`views/ops/positions.php`) | `department` master, datalist | **label** |
| Approval rule (`views/ops/approval_rules.php`) | **nothing — bare text input** | **free text** |

M2's `dept_canon()` reconciles code-vs-label *within* vocabulary 1 for display
and grouping. It does not, and deliberately did not, bridge vocabularies 1 and 2.

## 5. Consumers

27 library files and 14 views reference department. The load-bearing ones:

| Area | File | Role |
|---|---|---|
| Department hub / org chart | `lib/deptorg.php` (58 refs) | grouping, headcount rollup, HOD resolution |
| Org-chart import | `lib/organogram.php` (18) | **creates** department master values |
| Interview panels | `lib/recruit_iv.php` (16) | defaults a panel to the candidate's department |
| Command Centre | `lib/recruit_cc.php` (14) | demand//analytics grouping |
| Requisition + masters | `lib/ops.php` (14) | forms, list labels |
| Position master | `lib/position.php` (12) | establishment |
| Documents | `lib/doc_templates.php` (10) | offer/appointment letters |
| Approvals | `lib/recruit_approval.php` (9) | **rule matching** |
| Public careers | `lib/careers.php` (5) | **public rendering** |

## 6. Governance: who can create a department value

| Path | Controlled? | Duplicate detection |
|---|---|---|
| Settings → Masters (`lk_admin`) | admin-only | **none** |
| Org-chart import (`lib/organogram.php:290`) | any importer | **none** — auto-codes every distinct label |
| "Other (add new)…" (`resolve_new_lookup`, `lib/ops.php:954`) | any form user | **none** — derives a code by uppercasing the text |
| Position form free text | any coordinator | n/a — never reaches the master |

`resolve_new_lookup()` turns "Human Resources" into `HUMANRESOURCES` and "Human
Resource" into `HUMANRESOURCE`: two departments, no warning. The organogram
importer does the same from a spreadsheet.

**There is no Department CRUD screen.** `/departments` (`ops_departments()`) is
read-only apart from filing a designation under a department.

## 7. Defects reproduced

Each was demonstrated by running the real code, not inferred.

### 7.1 Approval routing silently fails across vocabularies — HIGH

`appr_match()` (`lib/recruit_approval.php:112`) compares
`strtolower(trim($ctx['department']))` against the rule's free-text
`applies_department`. The offer context takes its department from
`requisitions.department` — an `hr_department` **code**.

```
Rule configured for department: 'Quality'   (typed into a free-text box)

ctx 'QAQC'    (a requisition filed under QA / QC)  -> NO RULE MATCHED
ctx 'Quality' (the same department, master label)  -> MATCHED
ctx 'quality' (same label, lower case)             -> MATCHED
```

**Consequence:** an offer on a requisition raised through the standard form is
not routed to the configured approver. The approval chain is silently skipped.
The code already carries a comment about an earlier bug of this same family
("Previously this used the SBU alone, so an approval rule keyed on Department
never matched…"), so this class of failure has bitten before.

### 7.2 The public careers page renders raw codes — MEDIUM

`lib/careers.php` makes **zero** calls to `rcc_departments()` or
`lk_options_or()`, and renders `$job['department']` directly (lines 251, 267).
Since that value is an `hr_department` code, a job applicant sees `QAQC` rather
than `QA / QC`.

### 7.3 An ungated route exposes hr-gated data — MEDIUM

`/positions` resolves to module family `hiring`, owned by the paid **`hr`**
product. `/departments` resolves to **no module** and is guarded only by
`is_coordinator_level()`. But `dept_hub()` rolls up `positions`.

Driven through the real dispatcher with `hr` switched off:

```
/positions    -> refused: "People & hiring has been switched off for this workspace."
/departments  -> rendered, 41,630 bytes, sanctioned headcount visible
```

Pre-existing; not introduced by M2. §36 puts it squarely in this milestone's
remit: shared vocabulary must not become a route into an unentitled module.

### 7.4 Uncontrolled vocabulary growth — MEDIUM

Two entry points (§6) create department values with no duplicate detection and
no approval. This is the mechanism by which `HR` / `H.R.` / `Human Resources`
become three departments.

## 8. QAQC / NDT / HSE / HR / FINANCE — how they are actually used (§26)

Assessed from usage, not assumption. **No interpretation is being forced.**

| Value | Vocabulary | Observed use | Candidate reading |
|---|---|---|---|
| `QAQC` | `hr_department` only | hiring department on requisitions/candidates | Synonym of `QUALITY`, **or** a distinct sub-department |
| `NDT` | `hr_department` only | hiring department | Genuinely new department, **or** a *discipline* — `NDT` is also a value in the `trade` master |
| `HSE` | `hr_department` only | hiring department | Almost certainly the same as `SAFETY` ("Safety / HSE") |
| `HR` | `hr_department` only | hiring department | Genuinely new — vocabulary 1 has no HR department |
| `FINANCE` | `hr_department` only | hiring department | Overlaps `COMMERCIAL` ("Commercial / Finance") |

`NDT` deserves particular care: it already exists in the **`trade`** master as a
discipline. Treating it as a department may be conflating organisation structure
with professional taxonomy — exactly what §3 forbids.

**None of these is safe to auto-merge.** Each needs a customer decision, and that
is precisely what the synonym architecture is for: the mapping becomes approved
data, not a migration guess.

## 9. Tenant and cache position

EXAACT uses **one database per tenant** — there is no `tenant_id` column and no
shared vocabulary table. Tenant isolation of vocabulary is therefore
*structural*: tenant A's `lookup_values` rows are in tenant A's database and are
unreachable from tenant B.

This simplifies §22/§23 considerably. The only cross-tenant risk is a **cache**
that outlives the database it was read from — which M2 proved was real in
`lk_options_or()` and `trade_options()`, and fixed by keying on `db_epoch()`.
Any new vocabulary cache must follow the same rule, and §34 makes that mandatory.

"Global vocabulary" in this architecture means the seeded defaults in code
(`DEPARTMENTS`, `lk_seed()`); "tenant vocabulary" means the rows in the tenant's
own database. A tenant cannot modify the global set — it can only diverge from it
locally, which is the correct behaviour.

## 10. Permissions and entitlement position

| Route | Module gate | Guard |
|---|---|---|
| `/departments` | **none** | `is_coordinator_level()` |
| `/lookups` | **none** | `is_admin_level()` |
| `/hierarchy`, `/users` | **none** | role-level |
| `/positions`, `/positions-import` | `hiring` → **hr** | + role |
| `/requisitions`, `/recruitment-cc` | `hiring` → **hr** | + role |

Department vocabulary administration correctly belongs on the ungated core side
(`admin` is the only CORE product module). The inconsistency is only §7.3 — a
core screen rendering paid-module data.

## 11. What this audit concludes

1. The lookup engine is the right foundation. It already provides per-tenant,
   admin-editable, coded, ordered, activatable lists — and it is already the
   authoritative store for vocabulary 1.
2. It cannot, as it stands, express **synonyms**, **canonical identity separate
   from display name**, **term types**, or an **approval trail** for a mapping.
3. Therefore the correct move under §4's ladder is **EXTEND**, not BUILD: add a
   synonym/alias layer and a display-name distinction to the existing engine, and
   connect `hr_department` to it as a legacy source — rather than introduce a
   second department authority.
4. The `hr_department` → `department` merge itself must remain **data**, decided
   by the customer through approved synonyms, never a migration guess.

Architecture follows in `M3-DEPARTMENT-VOCABULARY-ARCHITECTURE.md`.
