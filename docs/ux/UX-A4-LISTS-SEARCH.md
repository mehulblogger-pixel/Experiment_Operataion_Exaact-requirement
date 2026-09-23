# UX-A4 — Lists, tables, filters and search

**Phase A, step 4. Audit only — no code changed.**

Scope: Parts 12 (search), 13 (list screens), 14 (tables and mobile).

Two of the four areas in this step turned out to be **already good**, and are
recorded as such so this pass does not "fix" them. The two genuine findings are
both specific and both verifiable.

---

## F-A4-1 · The universal search silently excludes Recruitment
**Class: structural · Severity: HIGH · Confidence: read from the source registry**

`/search` describes itself on screen as:

> **"One box across every register you are allowed to see."**

It registers **16 sources**:

> partners · contacts · leads · inquiries · quotes · opportunities · invoices ·
> contracts · calls · jobs · reports · complaints · ncr · capa · people ·
> equipment

**Not searchable anywhere in it:**

| Missing | Module |
|---|---|
| `candidates` | Recruitment |
| `requisitions` | Recruitment |
| `hiring_requests` | Recruitment |
| `samples` | Operations |
| `methods` | Reporting |
| `controlled_docs` | Reporting |
| `risk_items` | Reporting |

The brief's own worked examples for Part 12 are:

> Rahul Sharma → *Candidate · Recruitment*
> REQ-00123 → *Requisition · Recruitment*

**Both are exactly the two things the search cannot find.** A recruiter who
types a candidate's name into the box the product calls universal gets nothing,
with no indication that the register was never consulted — which is worse than
an empty result, because it reads as *"that person is not in the system."*

`people` searches the `inspectors` register, so a person who has been **hired**
is findable while the same person as a **candidate** is not. That inconsistency
sits directly on the Accepted-vs-Joined confusion already flagged for A8.

**Recommended treatment (C9):** register the missing sources in
`search_sources()`. The registry is a clean `$add(key, label, icon, $can, $run)`
with a permission gate per source, so each addition is one closure and one
permission check — **no new search engine**, which Part 12 explicitly forbids.

**Risk:** low, but each new source is a query on a page that currently runs 16;
they must respect the same `$can` gate and the existing try/catch, which already
degrades a failing source into a named warning rather than a broken page.

**Honest caveat:** I could not demonstrate this live. On the test workspace the
search returned zero results for *every* term tried, including ones that should
hit covered registers, so the browser run proves nothing either way. The finding
rests on the source registry, which is unambiguous. Whether search also has a
live defect on a populated workspace is **a separate question this step did not
answer**, and it should be checked against real data before C9.

---

## F-A4-2 · List screens render every row, with no pagination
**Class: structural · Severity: MEDIUM · Confidence: read from the queries**

Part 13 asks every list screen for: title, explanation, primary action, search,
quick filters, list, **pagination**.

Pagination is effectively absent. Checked directly, the two largest recruitment
lists build their rows with no cap at all:

```php
// candidate list  — lib/ops.php:6303
$rows = ops_all("SELECT c.*, … FROM candidates …")   // no LIMIT
// requisition list — lib/ops.php:5667
$rows = ops_all("SELECT r.*, … FROM requisitions …") // no LIMIT
```

Today that is invisible, because a young workspace has tens of rows. At a few
thousand candidates it becomes the slowest screen in the product, on the module
the brief cares most about, and the failure will arrive as *"the system has got
slow"* rather than as a list problem.

**Deliberately not overstated:** a crude grep suggested "0 of 46 requisition
queries carry a LIMIT", which is misleading — most of those are `COUNT()` or
single-row lookups where a limit would be meaningless. The finding above is
based on the two actual list-feeding queries, read individually.

**Recommended treatment (C6):** a shared pager on the list component, so one
change covers every list rather than 251 edits. This is the strongest argument
in the whole audit for doing C2 (shared components) before the module passes.

**Risk:** medium, and it is a *behaviour* change, not a cosmetic one — a user who
today sees all 900 rows on one page will see 50. It needs the list route tests
plus a deliberate check that exports still cover the full set, not just page one.

---

## F-A4-3 · Tables scroll sideways on every phone
**Class: structural · Severity: HIGH · Confidence: measured in A1**

Carried forward from the census, unchanged:

| | |
|---|---|
| Views containing a table | **251** |
| …with any card fallback | **2** |
| Global rule | `table.dt{display:block;overflow-x:auto;white-space:nowrap}` |

Part 14 is explicit: *"Do not force users to horizontally scroll large desktop
tables on a phone."* The current global rule does exactly that, for 249 of 251
views.

The two exceptions show the pattern that works — the requirement form's
deployment groups and the Form Designer's field rows both become labelled cards
below 760px using `data-l` attributes and a `::before`.

**Recommended treatment (C6):** promote that proven pattern into the shared list
component. It needs no per-screen markup beyond a label attribute per cell.

---

## What is already good — and must not be "fixed"

### Filters are not the problem the brief expects
Part 13 warns against "10 filters displayed simultaneously". Measured across
every list screen:

| Screen | Filter controls |
|---|---|
| jobs | 8 |
| voucher list | 3 |
| samples, risks, methods, decision rules, controlled docs, candidates, calls | 2 |
| satisfaction, requisitions | 1 |

**The worst screen in the product has eight filters; almost every other has one
to three.** There is no filter wall to dismantle. Moreover **76 views already use
chip / tab style quick filters** against only 23 using a raw filter bar — the
pattern Part 13 recommends is already the majority pattern.

### Search is one engine, not several
Part 12 says *"Do NOT create duplicate search engines."* There is one:
`search_run()` over a single `search_sources()` registry, with per-source
permission gates and a `search_only_hit()` shortcut that jumps straight to a
record when exactly one thing matches. The other `*_search` functions found in
the libraries are Marketplace-specific matching (professional search, geo
search, saved searches) — a genuinely different job, not a duplicate.

F-A4-1 is therefore a **coverage gap in a good engine**, not a call to rebuild it.

---

## Summary

| ID | Finding | Class | Severity |
|---|---|---|---|
| F-A4-1 | Universal search excludes candidates, requisitions, hiring requests and 4 more | structural | HIGH |
| F-A4-2 | Lists render every row; no pagination | structural | MEDIUM |
| F-A4-3 | 249 of 251 table views scroll sideways on a phone | structural | HIGH |

**No product decision required.** All three are presentation and coverage.

**One open question for C9**, recorded rather than assumed: on the test
workspace `/search` returned nothing for any term, including ones that should
have matched covered registers. That may be a data artefact of a near-empty test
database, or a live defect. It must be re-tested against a populated workspace
**before** any source is added, because adding sources to a search that is not
returning results would hide the real fault rather than fix it.
