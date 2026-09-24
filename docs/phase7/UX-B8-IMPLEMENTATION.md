# UX-B8 — Recruitment in global search · IMPLEMENTATION

Audit: `UX-B8-AUDIT.md` (audited HEAD `c60db31`, document `5b3610b`).
Scope: three sources added to the one existing registry, plus the empty-state
wording. No new engine, registry, renderer, permission, schema or workflow.

---

## Before → after

| | Before | After |
|---|---:|---:|
| Registered search sources | **16** | **19** |
| Candidate findable by name / code | no | **yes** |
| Requisition findable by code / designation / site | no | **yes** |
| Hiring request findable by number / title | no | **yes** |
| Empty state | claimed the whole database | states what was searched |

Measured, same terms that returned nothing in the audit:

| Term | Before | After |
|---|---|---|
| `Arjun Ghosh` | 0 | **1** — Candidates |
| `Ghosh` | 0 | **3** — Candidates |
| `CV-RC-055` | 0 | **jumps to `/candidate?id=1238`** |
| `REQ-2607-09` | 0 | **jumps to `/requisition?id=293`** |
| `REQ-2607` | 0 | **6** — Recruitment · Requisitions |
| `HRQ-2026-000125` | 0 | **jumps to `/hiring-request?id=203`** |
| `HRQ-2026` | 0 | **6** — Hiring requests |
| `P5 Screen` | 0 | **jumps to `/hiring-request?id=203`** |

Everything the audit found working still works unchanged: case-insensitivity,
whitespace tolerance, partial matching, the two-character minimum, per-register
narrowing, and the existing result layout.

---

## Files changed

| File | Change |
|---|---|
| `lib/search.php` | three `$add(...)` entries in the existing registry |
| `views/ops/search.php` | empty-state wording |
| `tests/test_b8_recruitment_search.php` | new — 31 assertions |
| `deploy-check.php` | regenerated |

**No schema change. No migration. No route added. No permission defined.**

---

## The three sources

### Candidates — `$add('candidates', THP('candidate'), '🧑‍💼', …)`

| | |
|---|---|
| Permission | `is_coordinator_level()` — the candidate register's own gate |
| **Scope** | **`rasg_cand_scope('c')`** |
| Fields | `first_name`, `last_name`, `cand_code`, `email`, `mobile`, and `first_name \|\| ' ' \|\| last_name` |
| Destination | `/candidate?id=` |

**The scope is the whole point of this stage.** A candidate has no office of its
own. `rasg_cand_scope()` reaches it *through the requisition* —
`requisitions.office_id` via `c.requisition_id` — and adds the recruitment SBU
clause. Handing the generic office/SBU helper a candidate alias, the way all
sixteen neighbouring sources are written, would look correct and scope nothing.

Mutation **M4** does exactly that, and the behavioural test catches it.

**Identity fields only.** The candidate *register* also searches `cv_keywords`
and `cv_text`, and should — reading CVs is what that screen is for. In a box
that searches everything, one word of a résumé would drag in every candidate who
ever mentioned it and bury the invoice you were after. `E3` pins this.

**Full name is matched as well as its parts**, because "Arjun Ghosh" is what a
recruiter types and neither column contains it. `||` is safe on both engines:
`db.php` sets `PIPES_AS_CONCAT` on every MySQL connection precisely so the
application's SQLite dialect means the same thing on MariaDB.

### Recruitment requisitions

| | |
|---|---|
| Permission | `is_coordinator_level()` |
| **Scope** | **`rcc_scope_req('r')`** — office **and** SBU |
| Fields | `req_code`, `designation`, `project_site` — the register's own three |
| Destination | `/requisition?id=` |

### Hiring requests

| | |
|---|---|
| Permission | `hreq_can_view()` — the register's own gate |
| **Scope** | **`scope_office_clause('office_id')` — office ONLY, no SBU** |
| Fields | `req_no`, `designation`, `job_title` |
| Destination | `/hiring-request?id=` |

The brief was right to require this be verified rather than assumed. `hreq_list()`
scopes by office alone. Reusing the requisition helper would have added an SBU
restriction the register itself does not apply, hiding requests from people
entitled to see them. `C5` pins that it was not.

**Three sources, three different scoping rules, none interchangeable.**

---

## "Requirement" — distinguished without renaming

Neither entity is renamed. The requisition group label is:

```php
(TERM_DEFAULTS['requisition'][2] ?? 'Recruitment') . ' · ' . THP('requisition')
```

→ **"Recruitment · Requisitions"**

The module word is read from the term's **own group field** in the terminology
dictionary, not hard-coded, so it follows whatever a tenant renames the entity
to. Marketplace requirements are not a search source, so there is no collision
today; when one is added, the same pattern gives it its own module prefix.

All three labels come from the terminology engine — `THP('candidate')`,
`THP('requisition')`, `THP('hiring_request')`. No second dictionary.

---

## Empty state

| | |
|---|---|
| Before | a claim about every register the reader may open — i.e. the whole database |
| After | `No match for X in the N registers searched.` |

The count is `count($res['sources'])` — the labels of **this person's own
permitted registry**, which `search_run()` already returns. Nothing inaccessible
is consulted to produce it, and no state detection was introduced.

The three states hold:

| State | Behaviour |
|---|---|
| Accessible, indexed, no match | "No match … in the N registers searched" |
| Not accessible | the source was never registered — not counted, not named |
| Accessible, not indexed | likewise not counted — the message no longer claims it was searched |

State C cannot leak, because the registry the count is drawn from is built by
the permission gate itself.

---

## Security results

### Candidate scope (the architectural exception)

Office 9671 holds candidates coded `P4CC-*`; office 9661 holds `P4RC-*`.

| Probe | `P4CC` | `P4RC` |
|---|---:|---:|
| Coordinator, all offices *(arming)* | 6 | 6 |
| Coordinator scoped to 9671 | **6** | **0** |

Plus: no out-of-scope name, code or metadata appears anywhere on the page.

### Permission matrix

| Role | Candidates offered | Requisitions offered | `&in=candidates` forced |
|---|---|---|---|
| COORDINATOR | yes | yes | allowed |
| SR_INSPECTOR | no | no | **returns nothing from it** |
| FINANCE | no | no | **returns nothing from it** |

The gate is `if (!$can) return;` **inside `$add`**, which returns before the
closure is stored. An ungranted source has no closure to call — it is never
queried, not queried and then filtered (`D4`).

### Tenant isolation

Unchanged and structural: one database per tenant, no `tenant_id`, and
`lib/search.php` still contains no cross-database reference. The three new
sources are unqualified queries on the tenant's own connection, exactly like the
sixteen before them.

---

## Mutation results — 8 of 8 caught

| # | Defect | Caught by |
|---|---|---|
| M1 | Candidate source removed | 9 assertions (A4, B1, B2, C1, D1, E1, E2, F2, H1) |
| M2 | Requisition source removed | 8 assertions |
| M3 | Hiring Request source removed | 7 assertions |
| M4 | `rasg_cand_scope()` → generic office/SBU clause | PHP C1, C2 **and** browser S2 |
| M5 | Hiring Request gate removed | D3 |
| M6 | Requisition gate removed | D2 |
| M7 | Candidate scope removed entirely | browser S3, S4 |
| M8 | Misleading universal empty state restored | G1, G2 |

M4 and M7 fail in **opposite directions**, which is what makes the pair useful:

* **M4** — the generic clause makes candidates vanish (0 found, because the
  column it names does not exist and the source is skipped). Users lose search.
* **M7** — no clause at all leaks **6 out-of-scope candidates**. Users gain data
  they must not see.

One helper, two ways to be wrong, both covered.

---

## Two probe faults corrected before any result was believed

1. **Exact identifiers appeared to return zero.** `CV-RC-055`, `REQ-2607-09`,
   `HRQ-2026-000125` and `P5 Screen` all reported 0 matches — while their
   *partial* terms returned the very same records. The cause was
   `search_only_hit()`: one unambiguous match for a reference-looking term goes
   straight to the record, and the probe was counting matches on a page it had
   already left. Reported as a miss, it was the single-match shortcut working —
   and it confirms §18: all three new sources participate in it correctly.
2. **Two assertions failed on the author's own comments.** `C2` and `G1` search
   for forbidden strings; both appeared inside explanatory comments that quoted
   the very thing being forbidden. The comments were reworded rather than the
   assertions weakened — the same fault B7 hit with a `details.fold` comment.

---

## Deliberately not done

| | |
|---|---|
| Marketplace requirements, professionals, users as sources | Not in the authorised three. Recorded for a future decision. |
| `.btn.small` chips at 36px | Deferred to B7/B10; not reopened. |
| Ranking across sources | Unchanged — grouped by register in registry order. |
| `search_only_hit()` | Unchanged; the new sources simply participate. |
| Result card layout | Unchanged. The audit found it already good. |
| `cv_text` / `cv_keywords` in global search | Excluded on purpose; they remain in the candidate register. |
