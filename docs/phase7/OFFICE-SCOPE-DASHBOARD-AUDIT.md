# Office scope — dashboard & Command Centre audit

**Raised by:** the owner, after a Coordinator scoped to Mumbai reported seeing
Ahmedabad data.
**Scope of this audit:** every tile and number on the Dashboard (`/`) and the
Command Centre (`/command-centre`).
**Audited against:** commit `cd96ace`.

> **STATUS: FIXED.** This document was the audit. The owner then decided all
> three points, and D1, D2, D3 and I1 were fixed, plus the client/vendor
> directory scoped by branch. See the closing section for what shipped.

Each finding is marked with how it was established:

- **PROVEN** — reproduced with real numbers against a live database.
- **CODE-VERIFIED** — read end to end through every layer it delegates to.
- **BY DESIGN** — works this way deliberately; recorded so it is not re-raised.

---

## Summary

| | Count |
|---|---|
| Confirmed defects | **3** |
| Inconsistencies (not leaks, but wrong-looking) | **1** |
| By design — needs an owner decision | **2** |
| Verified correct | the rest |

**No cross-office data leak was found.** The three defects are *counts* that
ignore office scope. The lists behind them are correctly scoped, so a user can
see a number that includes other offices but cannot open those records.

That distinction matters: this is a **reporting accuracy** problem, not a
**security** problem.

---

## Confirmed defects

### D1 — "Open leads" KPI ignores office scope — **PROVEN**

`views/dashboard.php:140`

```
SELECT COUNT(*) FROM leads WHERE status='OPEN'
```

`leads.office_id` exists, and the `/leads` register filters on it
(`leads_where()` → `scope_office_clause('l.office_id')`). The KPI does not.

Measured on a live database, for a user scoped to one office:

| | Value |
|---|---|
| Dashboard KPI shows | **4** |
| The list behind it shows | **3** |
| Difference | 1 lead belonging to another office |

**Effect:** the number is company-wide; clicking it shows fewer rows.
**Severity: Medium.** No record is exposed — only a count.

### D2 — "Open deals" KPI ignores office scope — **PROVEN**

`views/dashboard.php:141`

```
SELECT COUNT(*) FROM opportunities WHERE status='OPEN'
```

Same shape: `opportunities.office_id` exists, `/opportunities` filters on it in
four places, the KPI does not.

| | Value |
|---|---|
| Dashboard KPI shows | **9** |
| The list behind it shows | **8** |

**Severity: Medium.**

### D3 — "Quotations lapsed" ignores office scope — **CODE-VERIFIED**

`quotes_expired_count()` in `lib/crm.php`, surfaced on the Command Centre
through `attention_summary()`:

```
SELECT COUNT(*) FROM quotations WHERE status='EXPIRED' AND COALESCE(is_current,1)=1
```

`quotations.office_id` exists. The dashboard's **own** quotation block, thirty
lines away, scopes correctly on it (`scope_clause('q.office_id','q.sbu')`) —
which is what makes this an oversight rather than a policy.

**Severity: Medium.**

**What D1–D3 have in common:** each is a hand-written `COUNT(*)` that bypassed
the scope helper the rest of the screen uses. All three are one-line fixes.

---

## Inconsistency

### I1 — the open-work-orders count and the work-order list use different rules

**CODE-VERIFIED.**

A work order carries **two** offices: the **contracting** office (who holds the
order) and the **executing** office (who does the work).

| Screen | Rule used |
|---|---|
| `/calls` register | `COALESCE(executing_office_id, Ahmedabad) IN (scope) OR ibo_office_id IN (scope)` |
| Dashboard open-calls KPI | `scope_clause('c.executing_office_id', …)` — executing office only |

So the register deliberately shows a branch the orders it **contracted** even
when another branch executes them; the dashboard count does not include those.

**The count under-reports against its own list.** Not a leak — the opposite. But
it makes the dashboard disagree with the register, which is the same class of
problem as D1/D2 seen from the other side.

**Severity: Medium.** Whichever way it is resolved, the two should match.

---

## By design — please confirm as business decisions

### B1 — Clients and Vendors are counted company-wide

`index.php:1251-1252` counts every client and vendor with no scope filter.

`business_partners.home_branch_id` exists but is **never used for office
scoping anywhere in the application** — verified across the whole codebase. The
client/vendor directory is a shared company-wide master list.

**This is consistent, not a bug.** But it is a business decision worth stating
out loud: *should a Mumbai coordinator see that the company has 451 clients, or
only Mumbai's?* Today: the company's.

### B2 — Some attention counters cannot be office-scoped at all

| Counter | Why |
|---|---|
| Contracts expiring soon | `partner_contracts` has **no office column** |
| Inquiries awaiting a quote | `crm_inquiries` has **no office column** — it scopes by Business Unit instead |

These are data-model facts, not omissions. Office-scoping them would require a
schema change and a rule for existing rows, which is a separate decision.

---

## Verified correct — do not re-raise

| Checked | Result |
|---|---|
| 14 of the 18 queries the dashboard runs inline | Correctly scoped via `scope_clause()` |
| Command Centre money panel — quotations, invoices, receipts, credit notes | All four scoped (`_finevent_scope('office_id')`) |
| "Leads due for follow-up" (Command Centre) | Scoped — chains through `leads_where()` |
| System health panel | Reports system state, not branch business data — global is correct |
| Work orders / Jobs registers | Correctly isolated (see the note below) |
| My Pending Tasks | Scoped on `d.office_id` |

A note on the two-office rule, because it caused the original report: a work
order **executed by Ahmedabad but contracted to Mumbai** is visible to a Mumbai
user — deliberately. A work order both executed **and** contracted by Ahmedabad
is **not**. Both were tested:

| Work order | Visible to a Mumbai-scoped user? |
|---|---|
| Executed Ahmedabad, contracted Mumbai | **Yes** — by design |
| Executed Ahmedabad, contracted Ahmedabad | **No** |
| No executing office, contracted Ahmedabad | **No** |

Isolation is intact.

**One asymmetry worth knowing:** work orders honour the two-office rule; **jobs
do not** (`scope_clause('j.executing_office_id', …)` only). So a Mumbai user can
open a work order their branch contracted and find no jobs listed under it. Not
a leak, but confusing. Logged here as a separate observation.

---

## Two things that make "Ahmedabad" appear where people do not expect it

1. **Ahmedabad is flagged as the managing office** (`offices.is_ahmedabad`).
   Wherever a record has no branch, the system falls back to Ahmedabad —
   including reference codes, which get an `AHM-` prefix (`branch_abbr()`). Much
   of what reads as "Ahmedabad data" is **unassigned** data wearing Ahmedabad's
   name.

2. **Complaints, CAPA, confidentiality, receipts and the CRM dashboards
   deliberately show records with no branch to every office**
   (`scope_office_clause()` admits `office_id IS NULL`). The stated reason: a
   complaint nobody assigned to a branch must not become invisible to every
   branch.

---

## Suggested fixes (not applied)

| # | Fix | Size |
|---|---|---|
| D1 | Add `scope_office_clause('office_id')` to the open-leads count | One line |
| D2 | Same for the open-deals count | One line |
| D3 | Add office scope to `quotes_expired_count()` | One line |
| I1 | Decide one rule for work orders and apply it to both count and list | Small, needs an owner decision |

D1–D3 should each ship with a test that fails on the unscoped version, so they
cannot silently regress. The pattern to copy already exists in this codebase:
build the clause with the shared helper, never hand-write the `WHERE`.

---

## For the UAT playbook

The real rule is not written down anywhere, and testers will otherwise raise it
as a defect. Suggested wording:

> A branch user sees work orders their branch **contracted**, even when another
> branch executes them — and sees nothing from a work order their branch has no
> part in. Records with no branch set are shown as the managing office
> (Ahmedabad) and, on complaints and CAPA, are visible to every branch.

Also worth adding as a check: **every dashboard count must match the number of
rows in the list it links to.** That single test catches D1, D2, D3 and I1.


---

## What shipped (owner decisions applied)

The owner settled the three open points:

1. *"Coordinator of respective office will see only client of that particular
   office."* → the client and vendor directory and its dashboard tiles now scope
   on `home_branch_id`. A party with **no** branch set stays visible to everyone,
   because every existing party is unassigned and a strict filter would have
   emptied the register on day one. It tightens as the field is filled in.
2. *"For a contract or work order for their local office must be shown to that
   office."* → confirms the two-office rule is correct, so **I1 was fixed by
   bringing the count up to the register**, not by narrowing the register. The
   rule now has one definition, `call_office_clause()`, which both callers use.
3. *"Fix all the three."* → D1, D2 and D3 fixed with the same helper their own
   registers use.

**Evidence.** `tests/test_office_scope_counts.php` (34 assertions) seeds two
offices, signs in as a coordinator scoped to one, and checks both directions —
including that a master still sees everything. Each fix was mutation-tested:
reverting the two-office rule, the directory scope, or the quotations scope each
makes the suite fail. The directory was additionally verified over HTTP as a
Mumbai-scoped coordinator, who sees the Mumbai and unassigned clients and not the
Ahmedabad one. Full regression: **14,167 passed / 0 failed on MariaDB**, 14,163 on
SQLite.

**Pickers — now done too (second owner decision).** The owner then asked for the
client dropdown to be scoped as well: *"every scope of access shall be applied.
Client drop down shall be scoped to that office only."* Every party list in the
product now asks one rule, `partner_office_sql()` — the two shared helpers
`clients_list()` / `vendors_list()` (which feed ~20 pickers) plus ~9 lists that
built their own SQL inline, across operations, sales, reporting, quality, books
and the portals. **29 call sites.** The register and the dropdown beside it now
return the same parties, which is the point: a register that hides a party while
the form still offers it tells two different stories about the same data.

Verified live: a Mumbai-scoped coordinator opening **New work order** is offered
the Mumbai and unassigned clients and **not** the Ahmedabad one, while a Master
Admin on the same form is still offered all three.

**Three party queries are deliberately NOT scoped**, and each would be a bug if
it were:

| Query | Why it must stay unscoped |
|---|---|
| The duplicate check before creating a party (`crm.php`) | It asks "does this name already exist?" A branch-blind answer is the whole point — scope it and two branches create two records for one client. |
| An internal audit/trace helper | Not user-facing; picks any row to trace. |
| `inspectors_list()` — the team-member picker | **Deliberate and important.** EXAACT is built for cross-office deputation: it carries an *inter-office credit* on every job for exactly the case where one office's person does another office's work. Scoping this picker would break that. It is a separate business decision, not an oversight — see below. |

### The one question this raises

**Should a branch be able to depute another branch's person?** Today: yes, and the
product settles the money for it through inter-office credit. Scoping the
team-member picker would end that. That is a real operational choice about how
the business runs, not a reporting fix, so it was **not** made here. If the answer
is "each branch uses only its own people", say so and it is a small change to the
same helper — but it should be a decision, not a side effect.
