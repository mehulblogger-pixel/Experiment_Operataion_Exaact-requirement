# Module 40 — Activity Timeline · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: entities that log but never show their timeline

The "activity spine" (`lib/activity.php`, `act_log()` → `activities` table) is well-built: denormalised
`partner_id` for a one-query Customer 360, an `auto` flag separating system events from person-typed
notes, indexed reads, a global `/activities` feed, and per-entity timelines on Customer 360, Lead,
Opportunity, Call and Job. But **Complaint, NCR, Report and Invoice all write to the spine yet never
surface it on their own detail screen** — the readers (`act_for_entity`) exist and are called
elsewhere; those screens just never call them. Complaints and NCRs are exactly the ISO 17020 records
people most need a history on.

**Flagged, NOT changed (behaviour/access-affecting — needs your go):** (a) `act_log()` coerces any
unknown `$kind` to `NOTE`, and several call sites pass verbs (CREATED/ISSUED/OWNER/…) with no `auto`
flag, so system events render as green "person-typed" notes — an attribution bug, but fixing the
write path touches ~10 call sites. (b) The global feed stores `office_id`/`sbu` but **no reader filters
on them**, so a feed viewer sees every office's activity — a scoping leak whose fix narrows what some
users currently see. Both are left as-is and flagged.

---

## 1. Built (additive, read-only, no write, no access change)

1. `act_render_timeline($entityKind, $entityId, $title)` — a reusable read-only panel that echoes
   this entity's own activity (date, kind pill grey=system/green=person, subject, body, `with_whom`,
   actor), reusing `act_for_entity()`. No write form; no new data; only this entity's history.
2. Wired onto the **Complaint** detail (`History of this complaint`) and the **NCR** detail
   (`History of this nonconformity`) — the two biggest "logged but never shown" ISO records.
3. The **first tests** of the per-entity timeline surfacing (the spine had only one CALL test).

---

## 2. Edge cases handled

1. An entity with no activity → "Nothing recorded yet" panel, no crash.
2. The panel shows **only** this entity's rows (`entity_kind` + `entity_id` in the reader) — never
   another entity's or another office's.
3. System (auto) vs person-typed rows are visually distinguished (grey vs green pill), the design's
   own intent.
4. `act_render_timeline` guarded with `function_exists` in the views, so an install without the spine
   simply omits the panel.
5. Read-only — it never writes, so it cannot make a screen fail (the writer already swallows errors).

## 3. Guardrails (green)

`act_log`, the `activities` table, the global feed, and the existing lead/opportunity/call/job
timelines — all unchanged. No new permission (the detail screens' own gates already govern who sees
them); no schema change; no write path touched; the attribution coercion and the feed scoping are
**flagged, not modified**; nothing deleted.

## 4. Deferred / flagged

The kind-coercion attribution fix (write-path, ~10 call sites); the unscoped-feed office/SBU filter
(narrows current visibility — a hard-ish access change); Report/Invoice timelines (same pattern, easy
follow-on); adding the activity timeline to the retention register.

## 5. Tests

`tests/test_module40_activity.php` (13 assertions): activities are read back per entity; the panel
renders its title + rows and shows only this entity's history; the empty state; system vs person
distinction; both detail views are wired; the writer is unchanged.
