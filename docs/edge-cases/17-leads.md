# Module 17 — Leads · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: a rich funnel top where cold leads rot silently

The leads subsystem (`lib/leads.php`) is mature — a dedicated `leads` table, a Kanban board, a
rules-engine score (with human-readable "why"), advisory dedupe, conversion to client + inquiry +
opportunity with `lead_id` carried through the whole spine. Two real, damaging gaps:

- **`next_action_on` is a dead follow-up field.** It is stored (and the detail screen promises it
  "drives your follow-up list") but **no query anywhere reads it** — there is no follow-ups-due list,
  filter, tile, or cron.
- **No cold-lead detector / reminder.** `lead_stalled()` exists but is surfaced only in-page; the
  business advisor's stalled-deal check covers **opportunities only**; there is no lead entry in
  `adv_all()` and no lead cron. A lead past its stage SLA, or with an overdue follow-up, produces
  **zero** proactive signal — leads sit forever, exactly like pre-Module-03 quotes.

---

## 1. Built (additive, read-only, no hard control)

1. `leads_due($today)` — the open leads that need attention now: past their stage service level
   (`lead_stalled`), **or** with a `next_action_on` follow-up date that has passed (finally reading
   the dead field). Each row carries plain-English `due_reasons`. Converted/lost leads are never
   chased. `leads_due_count()` for the tile.
2. `adv_cold_leads()` — a business-advisor card mirroring the existing `adv_stalled_deals()`
   opportunity check, registered in `adv_all()`. Ranked by value, with steps and an explicit
   "nothing automatic — a lead is never closed for you".
3. A **"Need attention now"** tile + banner on the leads register (links to the advisor / the sortable
   register).
4. The **first tests** over the subsystem — cold-lead detection, and the previously-untested lifecycle
   guards (WON forces conversion, LOST needs a reason).

---

## 2. Edge cases handled

1. A lead past its stage SLA → due. A lead with an overdue `next_action_on` → due. Both reasons can
   apply at once (listed together).
2. A fresh lead with a **future** follow-up → not chased.
3. A **converted** or **lost** lead → never chased (only OPEN leads).
4. Ranking: most urgent (longest in stage) first in the list; by value in the advisor card.
5. Office scope is the same `scope_office_clause` the register already applies (via `leads_all`).
6. The advisor check returns null (no card) when there's nothing due, and is gated by the existing
   `adv_on('leads')` — it appears only where the leads module is installed.

## 3. Guardrails (green)

The board, the score engine, the dedupe, conversion, `lead_move` guards, and every route are
unchanged. No new permission (reuses `mod.leads.view` / `adv_on('leads')`); no schema change; no
access changed; no lead is ever auto-closed; nothing deleted.

## 4. Tests

`tests/test_module17_leads.php` (14 assertions): stalled + overdue-follow-up leads are due; a healthy
lead and a converted lead are not; the reasons are readable; the advisor check is registered and
advisory; and the WON-forces-convert / LOST-needs-reason guards (first coverage).
