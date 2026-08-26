# Module 37 — Global Search · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: well-built search, a few spine records unsearchable

Global search (`lib/search.php`) is one of the better-engineered parts of the app: a registry of
permission-gated, mostly scope-aware source closures; a bound term; **no query-then-filter leak** (a
source the user cannot see is never queried, via the `if (!$can) return;` gate). It covers partners,
contacts, leads, inquiries, quotes, contracts, calls, jobs, reports, complaints, NCR, CAPA, people,
equipment. The gaps: several **spine records are reachable but not findable by their own
reference** — invoices (only via a job's `invoice_number`), **opportunities** (own OPP- ref +
register), vouchers, and POs.

**Flagged, NOT changed (needs your explicit go — access-changing):** the existing `contracts` and
`inquiries` sources are permission-gated but **not** `scope_clause`-scoped, unlike quotes/calls/jobs/
reports beside them. If those registers are office-partitioned elsewhere, search is a cross-office
bypass. Tightening it changes who-sees-what, so per the program rules it is **left as-is and flagged
here** for a deliberate decision — this module does not touch it.

---

## 1. Built (additive, scope-respecting, no access change)

Two new sources, each following the exact existing pattern — declaring its permission (`$can`, so it
is never queried for users who can't see it) **and** its `scope_clause('…office_id','…sbu')` (so it is
properly office+SBU scoped from day one — a correctly-scoped new path, nothing loosened):

1. **Opportunities** — gated by `opp_can_view()`, office+SBU scoped; matches OPP ref, name,
   partner, contact; deep-links `/opportunity?id=`.
2. **Invoices** — gated by `books_can()` (finance), office+SBU scoped; matches invoice number,
   partner, PO, contract number; deep-links `/invoice?id=`.

Plus the **first executable search test** (there was none) — pinning the permission-gate and
scope-clause behaviour.

**Deferred:** vouchers (no clean human reference — identified by inspector+month, low search value)
and POs (the `/po` route/permission couldn't be cleanly verified from the map).

---

## 2. Edge cases handled

1. A draft invoice with a NULL `invoice_no` → shown as "draft #id" and not matched by a number LIKE
   (NULL never matches), so it never surfaces on a number search but is reachable by partner.
2. A cancelled invoice / non-open opportunity → shown dimmed (`dim`), consistent with other sources.
3. Both sources are office+SBU scoped: an ALL-scope user sees everything; a branch user sees only
   theirs — the same behaviour as quotes/jobs.
4. Permission gating is *before* the query (a finance-less user gets no invoices source at all), not
   a post-filter — asserted by a guest test.
5. The bound-term escaping (`%`/`_`/`\`), the `SEARCH_MIN`/`SEARCH_PER` limits, and the failure
   isolation are inherited unchanged.

## 3. Guardrails (green)

Every existing source, the `$can` gate, the scope clauses, the omnibox, the command palette, and the
reference-jump are unchanged. No new permission; no schema change; no access changed; the
contracts/inquiries scoping is untouched (flagged, not modified); nothing deleted.

## 4. Tests

`tests/test_module37_search.php` (11 assertions): the two sources exist, are office+SBU scoped and
deep-link; a privileged user gets both and search finds an opportunity by OPP ref and an invoice by
number; a guest without finance permission never gets the invoices source (gated, not filtered); the
permission gate and existing sources are intact.
