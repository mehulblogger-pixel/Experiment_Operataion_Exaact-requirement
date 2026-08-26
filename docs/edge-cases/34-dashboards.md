# Module 34 — Dashboards / Command Centre · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: the home page shows open *totals*, but every "what's due" signal already exists — elsewhere

The home dashboard (`views/dashboard.php`) is genuinely role-aware (phone-first inspector branch at
`:23`; desk block with role ordering at `:413-424`) and already fans compliance signals into a
"Waiting on somebody" band (`:112-151`). But a whole class of **already-computed** counts is surfaced
on **no** dashboard — each lives only on its own register screen:

- `leads_due_count()` (`leads.php:306`) → only `/leads`.
- `inquiries_due_count()` (`crm.php:1209`) → only `/inquiries`.
- contract expiries — `contract_state()` EXPIRING is per-record; the cron emails; **no count helper**.
- expired quotations — the EXPIRED sweep stamps status; surfaced on **no** dashboard.
- AR overdue — `ar_*` (`receivables.php`) only on `/receivables`; the home money desk uses a different
  job-level proxy (`ops_invoicing_counts`), so two "overdue" numbers can be quoted.
- `competence_training_watch_counts()` (`competence.php:417`) → only `/competence`.

So a BDM landing on `/` sees *open lead/deal totals* but not what's **due**; Finance sees a money desk
but not the real overdue-receivables figure. The recurring "signal computed but never surfaced"
pattern, at the dashboard level.

**Noted, deferred (flagged, not built — each touches global chrome or is a bigger change):**
- A **header notification badge / bell** carrying the attention + pending-task count (touches
  `layout_top.php` on every page — a global UX change to decide explicitly).
- A visible **"My Work" rail entry** for phone-first inspectors (today only in the command palette).
- Retiring the home view's **inline-SQL tiles** in favour of canonical helpers (a refactor of existing,
  working tiles — changes nothing a user sees, so low priority and out of this additive scope).
- Surfacing the heavier **`adv_all()`** cash-at-stake / **`chain_gaps()`** on the home band (perf — both
  already have their own rail entries).

---

## 1. Built (additive, read-only; reuses canonical counts; gated per item)

1. **`attention_summary()`** (`lib/ops.php`, beside `ops_pending_tasks`) — fans the existing counts
   into one list: leads due, inquiries due, contracts expiring, quotations lapsed, overdue
   receivables (ledger `ar_*`, the real figure), certifications lapsed. Each item is **gated by the
   same right its destination screen uses** (`leads_can_view`, `ar_can`, the `/competence` viewer set,
   the contract/quote gates), carries a link to its list, and is included **only when its count > 0**.
   Distinct from `ops_pending_tasks()` (personal approvals) — this is the business signal.
2. **`contracts_expiring_count($withinDays=null)`** (`contracts.php`) — a canonical count of OPEN
   contracts inside the `contract_warn_days()` window (no rule re-derivation).
3. **`quotes_expired_count()`** (`crm.php`) — current EXPIRED quotations (`is_current` so superseded
   revisions aren't double-counted).
4. A **"Needs attention"** band on the home desk dashboard, rendered with the existing `qcards`
   markup, right after "Waiting on somebody", before the role-ordered sections — so every desk role
   sees it. Empty band ⇒ hidden.

---

## 2. Edge cases handled

1. Every tile links to its list — no dead-end metric.
2. A tile appears only when it has something outstanding (count > 0, or a non-null money value).
3. Each tile is permission-gated by its destination's own right — nobody sees a count they can't act
   on, and the AR/competence/contract tiles never leak to a role without those rights.
4. The AR tile uses the **ledger** `ar_*` overdue (the canonical figure), not the job-level proxy.
5. `contracts_expiring_count` excludes closed and far-future contracts; `quotes_expired_count`
   excludes superseded revisions.
6. All new counts are wrapped in try/catch → a pre-migration table yields 0, never a crash; the whole
   band is guarded by `function_exists('attention_summary')`.
7. Read-only — no figure on the existing dashboard is altered; the band is purely additive.

## 3. Guardrails (green)

The inspector phone-first branch, the compliance "Waiting on somebody" band, `ops_pending_tasks`,
the money desk, charts, exec board, the role ordering — all unchanged. No new permission; no schema
change; nothing deleted.

## 4. Tests

`tests/test_module34_dashboard.php` (12+ assertions): `contracts_expiring_count` counts only OPEN
in-window contracts; `quotes_expired_count` counts only current EXPIRED; `attention_summary` returns
gated, linked, non-empty-only tiles with the fields the band renders; the band is wired into the
role-ordered output; it reuses the existing due-count/training helpers; and `ops_pending_tasks` is
preserved. Suite 3051 passing (only the 3 pre-existing baseline failures remain).
