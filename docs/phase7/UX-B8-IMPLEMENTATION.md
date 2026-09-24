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
| `lib/hiringreq.php` | `hreq_search()` — the hiring-request query, in the layer that owns the table |
| `tests/test_b7_mobile_tables.php` | a B7 assertion removed: it diffed against `HEAD~1` and so failed on unrelated later work |
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

### Hiring requests — the query lives in the layer, not in search

| | |
|---|---|
| Permission | `hreq_can_view()`, checked by the source **and** again inside the layer |
| **Scope** | **`scope_office_clause('office_id')` — office ONLY, no SBU** |
| Fields | `req_no`, `designation`, `job_title` |
| Destination | `/hiring-request?id=` |
| Query | **`hreq_search()` in `lib/hiringreq.php`** — search asks the layer |

The brief was right to require this be verified rather than assumed. `hreq_list()`
scopes by office alone. Reusing the requisition helper would have added an SBU
restriction the register itself does not apply, hiding requests from people
entitled to see them. `C4c` and `C5` pin that it was not.

**And the first version of this stage got the location wrong.** The SQL went
into `lib/search.php`, which broke an M4 invariant the full regression caught on
both engines:

> `D · no file outside the layer reads or writes hiring_requests`

`lib/hiringreq.php` owns that table so that a record carrying an approval
decision has exactly one door. The fix was **not** to add `search.php` to an
allowlist — it was to respect the boundary. `hreq_search()` now lives in the
owning layer, applies that register's own scope, and refuses on its own via
`hreq_can_view()` so it is safe wherever it is called from. Search asks the
layer, exactly as the candidate and requisition sources ask recruitment for
`rasg_cand_scope()` and `rcc_scope_req()`.

Mutations **M10** (put the SQL back in `search.php`) and **M5** (strip the gate
from both places) now guard it.

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

## Mutation results — 10 of 10 caught

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
| M9 | Layer given an SBU clause its register never applies | C4c, C5 |
| M10 | Hiring-request SQL moved back into `search.php` | C4, C4b, **and M4's own boundary test** |

M4 and M7 fail in **opposite directions**, which is what makes the pair useful:

* **M4** — the generic clause makes candidates vanish (0 found, because the
  column it names does not exist and the source is skipped). Users lose search.
* **M7** — no clause at all leaks **6 out-of-scope candidates**. Users gain data
  they must not see.

One helper, two ways to be wrong, both covered.

---

## What the regression caught, and one broken mutation

**The full regression earned its place twice over.**

1. **A real architectural violation by this stage** — the hiring-request SQL in
   `search.php`, described above. Caught on both engines by a test written for
   M4 months earlier.
2. **A badly scoped assertion written during B7** — `test_b7_mobile_tables.php`
   diffed against `HEAD~1` and demanded that only presentation files had
   changed. That does not describe B7; it describes whoever commits next, and it
   failed on B8 for touching a file B8 was authorised to touch. Removed, with
   the reasoning left in the file. A test that fails on unrelated future work
   teaches people to ignore it.

**One mutation appeared to survive and did not.** M9 targets the office-only
scope inside `hreq_search()`. The line it replaces appears **twice** in
`hiringreq.php`, and a first-occurrence replace hit the copy in `hreq_list()` —
`hreq_search()` was never mutated at all. Re-run so the mutation lands where it
was aimed, it is caught by `C4c` and `C5`. The test was never weak; the mutation
was.

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


---

## Final gate — measured results

| Gate | Result |
|---|---|
| Focused B8 (`test_b8_recruitment_search.php`) | **34 / 34** |
| B7 regression (`test_b7_mobile_tables.php`) | **21 / 21** |
| M4 boundary (`test_m4_correction.php`) | **107 / 107** |
| Mutations | **10 / 10 caught** |
| **SQLite full regression** | **13,968 passed, 0 failed** |
| **MariaDB full regression** (authoritative) | **13,971 passed, 0 failed** |
| Desktop browser UAT | **33 / 33** |
| Mobile 360×800 | **PASS** — 0px overflow, 44px box, 3 rows, 0 clipped, 0 JS errors |
| Mobile 390×844 | **PASS** — same |
| Mobile 412×915 | **PASS** — same |
| JS errors / failed requests across the desktop run | **0 / 0** |

Desktop UAT verified, per source: the search returns matches, the results are
grouped under the right label, the first result links to the right route, **and
that record actually opens with HTTP 200**.

| Source | Term | Matches | Group | Opens |
|---|---|---:|---|---|
| Candidates | `Ghosh` | 3 | 🧑‍💼 Candidates | `/candidate?id=1238` → 200 |
| Requisitions | `REQ-2607` | 6 | 📋 Recruitment · Requisitions | `/requisition?id=292` → 200 |
| Hiring requests | `HRQ-2026` | 6 | 📨 Hiring requests | `/hiring-request?id=203` → 200 |

Role behaviour in the browser: COORDINATOR sees candidate results, SR_INSPECTOR
does not.

---

## Did the `hreq_search()` refactor change any existing Hiring Request behaviour?

**No.** The function is new, additive and read-only. Evidence:

| Check | Result |
|---|---|
| `lib/hiringreq.php` diff stat, `214932c` → `2682177` | **30 added, 0 removed** |
| Diff hunks | one — a single insertion after `hreq_list()` |
| `hreq_list`, `hreq_get`, `hreq_save`, `hreq_submit`, `hreq_cancel`, `hreq_scope_gate`, `hreq_can_view` | **byte-identical** (per-function checksum, both commits) |
| Callers of `hreq_search()` | exactly one — `lib/search.php` |
| `test_m4_correction.php` | 107 / 107 |

No route, dispatcher, approval path, migration or scope gate was touched. The
hiring-request register behaves exactly as it did before B8; the only new
capability is that global search can ask the layer a read-only question.

---

## Test data

Three probe user accounts (`b8probe_*`) were created in the throwaway `rqv_ui`
UI-test workspace to exercise office scoping and the role matrix, and **deleted**
after the final run (verified: 0 rows). No business data was created, altered or
removed. No schema change, no migration.
