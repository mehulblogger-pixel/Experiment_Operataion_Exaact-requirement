# UX-B8 — Search & findability · AUDIT ONLY

## 1 · Metadata

| | |
|---|---|
| Commit audited | `c60db316a05252f5b091cc9542ca4c8c8996789e` |
| Branch | `claude/testing-branch-setup-0gqe8n` |
| Working tree | clean before and after |
| Date | 24 Sep 2026 |
| Engine | MariaDB, workspace `rqv_ui` (seeded UI-test data) |
| Browser | Chromium via the existing harness |
| Widths | 1280×900 · 360×800 · 390×844 · 412×915 (touch emulated) |
| Production code changed | **none** |

---

## 2 · Architecture map

```
/search?q=…&in=…
      ↓
search_run($q, $only)                    lib/search.php
      ↓
search_sources()        ← the registry; each source declares label, icon,
      ↓                    a PERMISSION, and its own SQL
$add($key,…,$can,$run)  ← if (!$can) return;  a source the person may not see
      ↓                    is NEVER QUERIED — not queried and then filtered
per-source SQL  (bound LIKE '%term%', own LIMIT, own scope clause)
      ↓
search_sbu_clause()     ← branch / SBU scoping, mirroring each register
      ↓
group per source → views/ops/search.php → record URL
```

`search_only_hit()` jumps straight to the record when exactly one thing matches.
`SEARCH_MIN = 2`, `SEARCH_PER = 6` per source on the mixed page, `SEARCH_PER_1 = 60`
when narrowed to one source.

**It is deliberately `LIKE '%term%'`, not an index.** The file says so and says
why: the product runs on both SQLite and MariaDB, and two search implementations
that must agree would not stay in step. It also names the point at which that
stops being enough (millions of rows) and states the replacement is an index.
That is an informed engineering decision, not an oversight.

### Is there a second engine? (§18)

| Candidate | Verdict |
|---|---|
| `cockpit_search()` / `cockpit_search_index()` | **Not a record search.** A hardcoded index of *configuration destinations* ("where do I configure the candidate form" → `/form-designer?form=candidate`). Different job. |
| `connect_pro_search()`, `connect_geo_search()`, `connect_client_search()` | Marketplace matching over the shared professional pool — a different problem (filters, tiers, geography), not global discovery. |
| `ops_search()` | The route handler; delegates to `search_run()`. |

**One record-search engine. No duplication to consolidate.**

---

## 3 · Source inventory — 16 registered sources

| Source | Permission gate | Destination |
|---|---|---|
| Customers & vendors | `mod.clients.view` | `/partner?id=` |
| Contacts | `mod.clients.view` | contact's partner |
| Leads | `leads_can_view()` | `/lead?id=` |
| Inquiries | `mod.inquiries.view` | inquiry |
| Quotes | `mod.quotes.view` | quote |
| Opportunities | `opp_can_view()` | opportunity |
| Invoices | `books_can()` | `/invoice?id=` |
| Contracts | contract view | contract |
| Work orders (calls) | `mod.calls.view` | `/call?id=` |
| Jobs | `mod.jobs.view` | job |
| Reports | `mod.idems.view` | report |
| Complaints & appeals | `mod.complaints.view` | complaint |
| Nonconformities | `ncr_can_view()` | NCR |
| Corrective actions | `mod.capa.view` | CAPA |
| **People** (workforce / inspectors) | `mod.masters.view` | `/m/inspectors/edit?id=` |
| Equipment | `mod.equipment.view` | equipment |

**Not present:** candidates, requisitions, hiring requests, marketplace
requirements, professionals, users.

`grep -nE "FROM (candidates|requisitions|hiring_requests|req_)" lib/search.php`
returns nothing — **no recruitment table is queried by global search.**

Note for the inventory: **Workforce and Inspector are one record** (Q32 Model D
§10), so the People source covers both. Workforce is globally discoverable.

---

## 4 · Measured behaviour

Searches run as ADMIN against seeded records that demonstrably exist.

| What | Term | Hits | Registers |
|---|---|---:|---|
| Candidate, full name | `Arjun Ghosh` | **0** | — |
| Candidate, surname | `Ghosh` | **0** | — |
| Candidate code | `CV-RC-055` | **0** | — |
| Requisition code | `REQ-2607-09` | **0** | — |
| Requisition, partial | `REQ-2607` | **0** | — |
| Hiring request code | `HRQ-2026-000125` | **0** | — |
| Hiring request, partial | `HRQ-2026` | **0** | — |
| Hiring request job title | `P5 Screen` | **0** | — |
| Workforce | `Asha` | 5 | Contacts, Jobs, People |
| Workforce, partial | `Ash` | 11 | Customers, Contacts, Jobs, People |
| Customer | `Reliance` | 4 | Customers, Quotes |
| Broad numeric | `2026` | 26 | 9 registers |

### What works well

| Behaviour | Evidence |
|---|---|
| Case-insensitive | `asha` / `ASHA` / `Asha` → identical 5 hits |
| Whitespace-tolerant | `" Asha"` / `"Asha "` → identical 5 hits |
| Partial matching | `Ash` → 11 across 4 registers |
| Minimum-length guard | `a` → *"Type at least 2 characters — one letter matches nearly everything."* |
| Result context | `Asha Rao — QA Lead at Reliance asha@ril.example` (title · subtitle · meta) |
| Result destination | real record URLs, not list pages |
| Narrowing | per-register chips (`&in=`) plus "Everything" |
| Wildcard safety | `%` and `_` escaped before binding |

### Performance (§16)

| | ms |
|---|---:|
| Baseline: `/candidates` register page | **1776** |
| Global search, typical | **1250–1340** |

**Search is faster than an ordinary register page.** The ~1.3s is this dev
server's per-request application boot with no opcache, not search cost.
No performance finding.

---

## 5 · Permissions (§10) — tested with four roles

Temporary probe users (`b8probe_*`, since removed) in the throwaway `rqv_ui`
workspace, because the seeded accounts' password hashes were unknown.

| Role | Registers offered | `2026` → hits | Registers matched |
|---|---:|---:|---|
| ADMIN | 16 | 26 | 9 |
| SR_INSPECTOR | **1** (Reports) | 5 | 1 |
| COORDINATOR | 11 | 22 | 6 |
| FINANCE | 7 | 10 | 4 |

The registry's `if (!$can) return;` means an unauthorised source is **never
queried**, so no row count can leak from it. This is the correct pattern; the
common mistake is to query and then filter.

### Search is not a way round a permission

Direct URLs as SR_INSPECTOR:

| Target | Result |
|---|---|
| `/invoice?id=55` | **HTTP 403** — "Money — restricted" |
| `/lead?id=22` | **HTTP 403** — "Sales & CRM — restricted" |
| `/candidates` | **HTTP 403** — "People & hiring — restricted" |
| `/partner?id=832` | redirected to `/` — "You do not have permission to view this client" |

Enforcement is at the route and independent of search.

---

## 6 · Tenant isolation (§11)

EXAACT is **one database per tenant with no `tenant_id` column** — isolation is
structural. Verified for search specifically:

* `lib/search.php` contains **no** cross-database reference, no `USE`, no
  `INFORMATION_SCHEMA`, no schema-qualified table name. Every query is
  unqualified and therefore runs in the signed-in tenant's own connection.
* `lib/search.php` contains **no** `tenant_id` — consistent with the model.

There is no mechanism by which global search could reach another tenant's data
without the connection itself being wrong, which is outside search's scope.
**PASS**, on architecture plus source evidence.

---

## 7 · Mobile (§14)

| Width | Page overflow | Search box | Result rows | Clipped | JS errors |
|---|---:|---:|---:|---:|---:|
| 360×800 | **0px** | 44px | 5 | 0 | 0 |
| 390×844 | **0px** | 44px | 5 | 0 | 0 |
| 412×915 | **0px** | 44px | 5 | 0 | 0 |

One issue: the register-narrowing chips and the Search button are **36px**,
below the 44px touch rule. They are `.btn.small`, which is the pre-existing
global touch-target item already deferred to B7/B10 — **not specific to search.**

---

## 8 · Empty / no-result states (§15)

| Case | Wording |
|---|---|
| No query | Lists the registers this role can search, and says branch scoping applies |
| No match | *"Nothing matches **zzqqxx9** in any register you can open."* + partial-match hint |
| Below minimum | *"Type at least 2 characters — one letter matches nearly everything."* |

These are good: they name the term, scope the claim to what the person can
open, and suggest what to try.

**But this is exactly what makes the recruitment gap harmful.** Searching for a
candidate who exists returns:

> *"Nothing matches **Arjun Ghosh** in any register you can open."*

The record exists and this user **can** open it. The message does not merely
fail to find — it asserts absence. A blank result would be less misleading.

---

## 9 · Old-finding verification (§19)

| Old finding | Current evidence | Status |
|---|---|---|
| **F-B8-1** Global search excludes Recruitment | No recruitment table queried in `lib/search.php`; 0 hits for every recruitment term | **CONFIRMED** |
| **F-B8-2** Candidates not globally discoverable | `Arjun Ghosh` 0, `Ghosh` 0, `CV-RC-055` 0 | **CONFIRMED** |
| **F-B8-3** Requirements not globally discoverable | `REQ-2607-09` 0, `REQ-2607` 0 | **CONFIRMED** |
| **F-B8-4** Hiring Requests not globally discoverable | `HRQ-2026-000125` 0, `HRQ-2026` 0, `P5 Screen` 0 | **CONFIRMED** |
| **F-B8-5** Result context insufficient | Rows carry title · subtitle · meta (`Asha Rao — QA Lead at Reliance asha@ril.example`) and dim inactive rows | **FALSE POSITIVE** for the 16 sources that exist |
| **F-B8-6** Results give no useful next navigation | Every row links to the record itself, and per-register chips narrow in place | **FALSE POSITIVE** |
| **F-B8-7** Search inconsistent between modules | One engine, one registry, one result renderer, one permission pattern. Module filters and record pickers are different mechanisms by design (§6), not inconsistency | **FALSE POSITIVE** |
| **F-B8-8** Permission / tenant leakage risk | Unauthorised sources are never queried; direct URLs 403; no cross-database reference | **FALSE POSITIVE** |

**4 confirmed · 0 partially confirmed · 4 false positives · 0 already resolved ·
0 intentional-design reclassifications.**

---

## 10 · Scorecard (§20) — diagnostic only, no ranking

| Dimension | Score | Evidence |
|---|---:|---|
| Discovery | **2** | 16 registers findable; an entire business module (recruitment) is not |
| Relevance | 4 | Ordered, active-first, per-source limits; no ranking across sources |
| Context | 4 | Title, subtitle, meta; inactive dimmed |
| Actionability | 4 | Lands on the record; the record carries its own actions |
| Terminology | 4 | Labels come from the B4 terminology engine (`THP()`); "Requirement" ambiguity is latent, not yet visible, because requirements are absent |
| Permission safety | **5** | Never-queried, not query-then-filter; direct URLs 403 |
| Tenant isolation | **5** | Structural; no cross-database reference in search |
| Mobile | 4 | No overflow at any width; only the shared `.btn.small` chips under 44px |
| Empty state | 3 | Well written, but currently asserts absence for records that exist |
| Consistency | 5 | One engine, one registry, one renderer |

---

## 11 · Confirmed findings

### B8-F1 — Recruitment is absent from global search
**Severity: HIGH** · route `/search` · all roles with recruitment access

Candidates, requisitions and hiring requests cannot be found by name, code or
title. `lib/search.php` registers 16 sources and none reads a recruitment table.

*Impact:* recruitment is a primary EXAACT workflow with its own Command Centre
(B2), its own next-action states (B3) and its own terminology (B4). A recruiter
who knows a candidate's name must first know which register to open. Every other
module's records are one box away.

*Existing mechanism involved:* `search_sources()` — a source is ~25 lines and
carries its own permission gate. Adding one is the established extension point.

### B8-F2 — The no-result message asserts absence
**Severity: MEDIUM** (HIGH in combination with B8-F1) · route `/search`

*"Nothing matches X in any register you can open"* is accurate for the 16
registered sources but false from the user's point of view when X is a candidate
they can open from `/candidates`.

*Impact:* a user may conclude the record does not exist, or was deleted, and
re-create it. Duplicate-candidate creation is a known sensitivity in this
product (F-A7-1, R20).

### B8-F3 — Search narrowing chips are 36px on touch
**Severity: LOW** · all widths under 640px

`.btn.small` chips and the Search button are 36px against the 44px rule. Shared
component; already deferred to B7/B10. Recorded here for completeness, not as a
search-specific defect.

---

## 12 · False positives — mandatory section (§16 of the brief)

Four of the eight carried-forward findings are **not defects**:

* **F-B8-5 (context)** — results already carry three lines of context and dim
  inactive records.
* **F-B8-6 (navigation)** — results link to records, not lists, and narrow in
  place by register.
* **F-B8-7 (consistency)** — there is exactly one engine. What looks like
  inconsistency is the deliberate distinction between global discovery, module
  filters and record pickers (§6), which should not be merged.
* **F-B8-8 (permission/tenant)** — the never-query pattern is stronger than
  query-then-filter, and isolation is structural.

Preserving these as work items would have produced changes with no user benefit
and real regression risk.

---

## 13 · Observations (not defects)

1. **Refusal styles differ.** `/invoice` and `/lead` return HTTP 403 with a
   branded "restricted" page; `/partner` redirects to `/` with a flash. Both
   refuse correctly. Consistency is a B10 candidate, not a defect.
2. **No ranking across sources.** Results are grouped by register in registry
   order, not relevance-ranked. At six rows per source this is not yet felt.
3. **`search_only_hit()` exists** — a single match jumps straight to the record.
   Good behaviour that the old audit did not credit.
4. **The LIKE-not-index decision is documented in the file**, including the
   volume at which it must be revisited. That is unusually honest and should be
   preserved rather than "fixed" opportunistically.
5. **Users are not searchable.** Deliberate or not, "find the user account for
   Asha" is not answerable globally. Recorded as a question, not a finding.

---

## 14 · Recommendations (audit-level only)

1. **Consider adding recruitment sources to the existing registry** —
   candidates, requisitions, hiring requests. Each is a `$add(...)` entry with
   its own permission gate, using the mechanism already in place. No new engine.
   This is a proposal for decision, not an instruction.
2. **Decide what the no-result message should say** when a module exists but is
   not indexed. This is a product/wording decision, not a code decision.
3. **Terminology check before any such addition:** "Requirement" already means a
   marketplace requirement in Connect and a requisition in Recruitment. If both
   ever become searchable, results need distinguishing context. B4's engine
   supplies the labels; the decision is which words.

---

## 15 · Scope boundary

```
No production code changed.
No database schema changed.
No workflow changed.
No permission changed.
No new search engine created.
No search source added.
Repository HEAD unchanged:  c60db316a05252f5b091cc9542ca4c8c8996789e
```

Temporary audit instrumentation: three probe user accounts (`b8probe_insp`,
`b8probe_coord`, `b8probe_fin`) were created in the throwaway `rqv_ui` UI-test
workspace to test role scoping, and **deleted at the end of the audit**
(verified: 0 rows remain). No business data was created, altered or removed.
